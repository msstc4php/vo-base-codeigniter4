<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Controller;

use BackedEnum;
use CodeIgniter\Controller;
use CodeIgniter\Validation\ValidationInterface;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValidatedData;
use MSSTC4PHP\ValueObject\Contract\ValueObject;

/**
 * Validated request data as value objects: after `$this->validate(['email' => 'required|valid_value_object[...]'])`,
 * `$this->validatedValueObject('email', EmailAddress::class)` builds the value object the rule has already vetted.
 * Only the fields the validation covered are visible.
 *
 * @property ValidationInterface|null $validator set by CodeIgniter\Controller::validate()
 *
 * @phpstan-require-extends Controller
 */
trait ValidatedValueObjects
{
    /**
     * @template T of ValueObject|BackedEnum
     *
     * @param non-empty-string $field a field name; dot syntax reaches nested data
     * @param class-string<T> $class
     *
     * @return T|null null when the field is absent, null or ''; a composite is read from an array or JSON text
     *
     * @throws LogicException when no validation ran, $class cannot be built from input, or the value does not convert
     */
    protected function validatedValueObject(string $field, string $class): ValueObject|BackedEnum|null
    {
        return ValidatedData::read($this->validator, $field, $class);
    }
}
