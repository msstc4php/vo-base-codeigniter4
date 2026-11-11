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

    private static ?ValueObjectMapper $mapper = null;

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

        $class = self::className($params[0] ?? '', 'cast');
        if (!self::mapper()->supports($class) || !is_a($class, ValueObject::class, true) && !is_a($class, BackedEnum::class, true)) {
            throw new LogicException(\sprintf('%s is not a value object the cast supports: it must be scalar-backed, a backed enum or a composite with a payload codec.', $class));
        }

        return $class;
    }

    /**
     * @return class-string<ValueObject|BackedEnum>
     *
     * @throws LogicException when the class is missing, not supported, or may not be built from input (BoundedString)
     */
    public static function untrustedTargetClass(?string $class): string
    {
        $class = self::className($class ?? '', 'validation rule');
        if (!self::mapper()->supportsUntrusted($class) || !is_a($class, ValueObject::class, true) && !is_a($class, BackedEnum::class, true)) {
            throw new LogicException(\sprintf('%s cannot be validated from input: it is not supported, or its payload carries its own constraints (BoundedString: check the length with min_length/max_length, or use NonEmptyString).', $class));
        }

        return $class;
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
        return $stored instanceof $class ? $stored : self::read($stored, $class, trusted: true);
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
     * @throws InvalidValueObjectException when $input is not a valid T
     * @throws LogicException when $class may not be built from untrusted input
     */
    public static function fromInput(mixed $input, string $class): ValueObject|BackedEnum
    {
        return $input instanceof $class ? $input : self::read($input, $class, trusted: false);
    }

    /**
     * Accepts the value object or raw input it is built from (a form field, a mass-assigned array), so raw input is
     * validated on the way in rather than stored as is.
     *
     * @param class-string<ValueObject|BackedEnum> $class
     *
     * @throws InvalidValueObjectException when $value is not a valid instance of $class
     * @throws LogicException when $value is raw input for a class that may not be built from it (BoundedString)
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

    /**
     * A composite arrives as JSON text; that is tried only after the raw value failed on its type, so the mapper keeps
     * every kind decision.
     *
     * @template T of ValueObject|BackedEnum
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function read(mixed $raw, string $class, bool $trusted): ValueObject|BackedEnum
    {
        try {
            return self::decode($raw, $class, $trusted);
        } catch (InvalidPayloadException $wrongType) {
            if (!\is_string($raw)) {
                throw $wrongType;
            }

            try {
                return self::decode(self::decodeJsonArray($raw), $class, $trusted);
            } catch (InvalidPayloadException) {
                throw $wrongType;
            }
        }
    }

    /**
     * @template T of ValueObject|BackedEnum
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function decode(mixed $payload, string $class, bool $trusted): ValueObject|BackedEnum
    {
        return $trusted ? self::mapper()->fromPayload($class, $payload) : self::mapper()->fromUntrustedPayload($class, $payload);
    }

    private static function className(string $class, string $usage): string
    {
        // The codec lookup is keyed by the class name as ::class spells it, without a leading backslash.
        $class = ltrim($class, '\\');

        return $class !== '' ? $class : throw new LogicException(\sprintf('The value-object %s needs a class parameter.', $usage));
    }

    /**
     * @return array<array-key, mixed> empty when $json is not a JSON object or array, which no codec accepts
     */
    private static function decodeJsonArray(string $json): array
    {
        try {
            $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }

    private static function mapper(): ValueObjectMapper
    {
        return self::$mapper ??= new ValueObjectMapper();
    }
}
