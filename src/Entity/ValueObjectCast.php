<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Entity;

use BackedEnum;
use CodeIgniter\Entity\Cast\BaseCast;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use Override;

/**
 * Entity cast handler: register it under a name in `$castHandlers` (e.g. `'vo'`) and cast a property
 * with `'vo[' . EmailAddress::class . ']'`.
 *
 * Null passes both ways, with or without the `?` prefix: an Entity reads an attribute it does not
 * have as null, so rejecting null would break every fresh Entity.
 *
 * @extends BaseCast<ValueObject|BackedEnum|null, string|int|float|null>
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
    public static function get($value, array $params = []): ValueObject|BackedEnum|null
    {
        $class = ValueObjectColumn::targetClass($params);

        return $value === null ? null : ValueObjectColumn::fromStorage($value, $class);
    }

    /**
     * @param mixed $value the value object, or raw input it is built from (form, row)
     * @param array<array-key, string> $params
     *
     * @throws InvalidValueObjectException when the value is neither a value object of the cast class nor valid input for one
     * @throws LogicException when the cast class is missing or not supported
     */
    #[Override]
    public static function set($value, array $params = []): string|int|float|null
    {
        $class = ValueObjectColumn::targetClass($params);

        return $value === null ? null : ValueObjectColumn::toStorage($value, $class);
    }
}
