<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Internal;

use BackedEnum;
use JsonException;
use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;

/**
 * The conversion the cast handlers and the rule set share: a column holds the mapper's payload,
 * a composite's array payload as JSON text. Stored values are trusted; input on the way in
 * (forms, mass assignment, validation) is not.
 *
 * @internal
 */
final class ValueObjectColumn
{
    private const string NULLABLE_MARKER = 'nullable';

    private const string CLASS_NAME = '/^\\\\?[A-Za-z_\\x80-\\xff][A-Za-z0-9_\\x80-\\xff]*(?:\\\\[A-Za-z_\\x80-\\xff][A-Za-z0-9_\\x80-\\xff]*)*$/D';

    private static ?ValueObjectMapper $mapper = null;

    /**
     * @var array<string, class-string<ValueObject|BackedEnum>>
     */
    private static array $castClasses = [];

    /**
     * @var array<string, class-string<ValueObject|BackedEnum>>
     */
    private static array $inputClasses = [];

    private function __construct()
    {
    }

    /**
     * @param array<array-key, string> $params the cast parameters, `vo[<class>]`; CI appends "nullable" for a `?vo[...]` cast of an Entity
     *
     * @return class-string<ValueObject|BackedEnum>
     *
     * @throws LogicException when the class is missing or not supported
     */
    public static function targetClass(array $params): string
    {
        $extra = array_diff(\array_slice($params, 1), [self::NULLABLE_MARKER]);
        if ($extra !== []) {
            throw new LogicException(\sprintf('The value-object cast takes one class parameter, "%s" given in addition.', implode(', ', $extra)));
        }

        $parameter = $params[0] ?? '';

        return self::$castClasses[$parameter] ??= self::checkedCastClass($parameter);
    }

    /**
     * @return class-string<ValueObject|BackedEnum>
     *
     * @throws LogicException when the class is missing, not supported, or may not be built from input (BoundedString)
     */
    public static function untrustedTargetClass(?string $parameter): string
    {
        $parameter ??= '';

        return self::$inputClasses[$parameter] ??= self::checkedInputClass($parameter);
    }

    /**
     * Reads a stored value.
     *
     * @template T of ValueObject|BackedEnum
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws InvalidValueObjectException when $stored is not a valid T
     */
    public static function fromStorage(mixed $stored, string $class): ValueObject|BackedEnum
    {
        return $stored instanceof $class ? $stored : self::mapper()->fromPayload($class, self::payloadOf($stored, $class));
    }

    /**
     * Reads request or form input.
     *
     * @template T of ValueObject|BackedEnum
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws InvalidValueObjectException when $input is not a valid T, or $class may not be built from untrusted input
     */
    public static function fromInput(mixed $input, string $class): ValueObject|BackedEnum
    {
        if ($input instanceof $class) {
            return $input;
        }

        // Refused before any JSON decoding, so the refusal does not depend on what the client sent.
        if (!self::mapper()->supportsUntrusted($class)) {
            return self::mapper()->fromUntrustedPayload($class, $input);
        }

        return self::mapper()->fromUntrustedPayload($class, self::payloadOf($input, $class));
    }

    /**
     * Accepts the value object or raw input it is built from (a form field, a mass-assigned array), so raw input is
     * validated on the way in rather than stored as is.
     *
     * @param class-string<ValueObject|BackedEnum> $class
     *
     * @throws InvalidValueObjectException when $value is not a valid instance of $class, or raw input for a class that
     *                                     may not be built from it (BoundedString)
     */
    public static function toStorage(mixed $value, string $class): string|int|float
    {
        $payload = self::mapper()->toPayload(self::fromInput($value, $class));

        if (!\is_array($payload)) {
            return $payload;
        }

        try {
            return json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidPayloadException(\sprintf('A %s payload cannot be encoded as JSON: %s', $class, $e->getMessage()), $e->getCode(), previous: $e);
        }
    }

    public static function mapper(): ValueObjectMapper
    {
        return self::$mapper ??= new ValueObjectMapper();
    }

    /**
     * @return class-string<ValueObject|BackedEnum>
     */
    private static function checkedCastClass(string $parameter): string
    {
        $class = self::className($parameter, 'cast');
        if (!self::mapper()->supports($class) || (!is_a($class, ValueObject::class, true) && !is_a($class, BackedEnum::class, true))) {
            throw new LogicException(\sprintf('%s is not a value object the cast supports: it must be scalar-backed, a backed enum or a composite with a payload codec.', $class));
        }

        return $class;
    }

    /**
     * @return class-string<ValueObject|BackedEnum>
     */
    private static function checkedInputClass(string $parameter): string
    {
        $class = self::className($parameter, 'validation rule');
        if (!self::mapper()->supportsUntrusted($class) || (!is_a($class, ValueObject::class, true) && !is_a($class, BackedEnum::class, true))) {
            throw new LogicException(\sprintf('%s cannot be validated from input: it is not supported, or its payload carries its own constraints (BoundedString: check the length with min_length/max_length, or use NonEmptyString).', $class));
        }

        return $class;
    }

    /**
     * Checked before any class lookup, so a parameter built from a placeholder never reaches the autoloader.
     */
    private static function className(string $parameter, string $usage): string
    {
        if ($parameter === '') {
            throw new LogicException(\sprintf('The value-object %s needs a class parameter.', $usage));
        }

        if (preg_match(self::CLASS_NAME, $parameter) !== 1) {
            throw new LogicException(\sprintf('The value-object %s parameter "%s" is not a class name.', $usage, InvalidValueObjectException::excerpt($parameter)));
        }

        // The codec lookup is keyed by the class name as ::class spells it, without a leading backslash.
        return ltrim($parameter, '\\');
    }

    /**
     * A composite is a JSON text column; anything else goes to the mapper as is. The result stays mixed: decoded JSON
     * can be any type, and narrowing it here would repeat the type checks the mapper makes anyway.
     */
    private static function payloadOf(mixed $raw, string $class): mixed
    {
        if (!\is_string($raw) || !self::mapper()->isComposite($class)) {
            return $raw;
        }

        try {
            return json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidPayloadException(\sprintf('A %s payload must be JSON text: %s', $class, $e->getMessage()), $e->getCode(), previous: $e);
        }
    }
}
