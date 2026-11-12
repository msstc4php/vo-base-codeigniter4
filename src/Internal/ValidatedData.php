<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Internal;

use BackedEnum;
use CodeIgniter\Validation\ValidationInterface;
use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;

/**
 * Reads one validated field as a value object.
 *
 * @internal
 */
final class ValidatedData
{
    private function __construct()
    {
    }

    /**
     * @template T of ValueObject|BackedEnum
     *
     * @param non-empty-string $field
     * @param class-string<T> $class
     *
     * @return T|null null when the field is absent, null or ''
     *
     * @throws LogicException when no validation ran, $class cannot be built from input, or the value does not convert
     */
    public static function read(?ValidationInterface $validator, string $field, string $class): ValueObject|BackedEnum|null
    {
        if (!$validator instanceof ValidationInterface) {
            throw new LogicException(\sprintf('No validation ran: call $this->validate() or validateData() before reading "%s".', $field));
        }

        if (str_contains($field, '*')) {
            throw new LogicException(\sprintf('The field "%s" contains a wildcard: read one concrete field at a time.', $field));
        }

        ValueObjectColumn::untrustedTargetClass($class);
        // Reason: helper() and dot_array_search() are globals of CodeIgniter's Common.php and array helper, which PHPStan does not scan.
        helper('array'); // @phpstan-ignore function.notFound
        $value = dot_array_search($field, $validator->getValidated()); // @phpstan-ignore function.notFound
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return ValueObjectColumn::fromInput($value, $class);
        } catch (InvalidValueObjectException $e) {
            throw new LogicException(\sprintf('The validated field "%s" does not convert to the requested class: validate it with valid_value_object[<class>].', $field), $e->getCode(), previous: $e);
        }
    }
}
