<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Validation;

use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;

/**
 * Rule set for `Config\Validation::$ruleSets`: `valid_value_object[<class>]` passes when the field
 * builds the value object. Empty input (null, '' or []) passes, as with CI's own format rules:
 * pair it with `required` or `permit_empty`.
 *
 * The message is `Validation.valid_value_object` from this package's language file.
 */
final class ValueObjectRules
{
    /**
     * @throws LogicException when the class parameter is missing, not supported, or may not be built from input (BoundedString)
     */
    public function valid_value_object(mixed $value, ?string $class = null): bool
    {
        $class = ValueObjectColumn::untrustedTargetClass($class);

        if (in_array($value, [null, '', []], true)) {
            return true;
        }

        try {
            ValueObjectColumn::fromInput($value, $class);
        } catch (InvalidValueObjectException) {
            return false;
        }

        return true;
    }
}
