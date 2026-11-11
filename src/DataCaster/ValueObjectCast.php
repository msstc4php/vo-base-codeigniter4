<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\DataCaster;

use BackedEnum;
use CodeIgniter\DataCaster\Cast\BaseCast;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use Override;

/**
 * Model field cast handler: register it under a name in the Model's `$castHandlers` (e.g. `'vo'`)
 * and cast a field with `'vo[' . EmailAddress::class . ']'`, or `'?vo[...]'` for a nullable column.
 *
 * The Model's DataCaster is strict: it rejects null for a cast without `?` before this handler runs.
 *
 * @extends BaseCast<ValueObject|BackedEnum, string|int|float>
 */
final class ValueObjectCast extends BaseCast
{
    /**
     * @param array<array-key, string> $params
     *
     * @throws InvalidValueObjectException when the stored value is not a valid value object of the cast class
     * @throws LogicException when the cast class is missing or not supported
     */
    #[Override]
    public static function get(mixed $value, array $params = [], ?object $helper = null): ValueObject|BackedEnum
    {
        return ValueObjectColumn::fromStorage($value, ValueObjectColumn::targetClass($params));
    }

    /**
     * @param mixed $value the value object, or raw input it is built from (form, row)
     * @param array<array-key, string> $params
     *
     * @throws InvalidValueObjectException when the value is neither a value object of the cast class nor valid input for one
     * @throws LogicException when the cast class is missing or not supported
     */
    #[Override]
    public static function set(mixed $value, array $params = [], ?object $helper = null): string|int|float
    {
        return ValueObjectColumn::toStorage($value, ValueObjectColumn::targetClass($params));
    }
}
