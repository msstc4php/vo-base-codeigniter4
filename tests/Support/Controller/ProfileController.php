<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller;

use CodeIgniter\Controller;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValidatedValueObjects;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Temporal\DateRange;

final class ProfileController extends Controller
{
    use ValidatedValueObjects;

    public function email(): string
    {
        if (!$this->validate(['email' => 'required|valid_value_object[' . EmailAddress::class . ']'])) {
            return 'invalid';
        }

        return 'email:' . $this->validatedValueObject('email', EmailAddress::class);
    }

    public function nested(): string
    {
        if (!$this->validate(['profile.email' => 'required|valid_value_object[' . EmailAddress::class . ']'])) {
            return 'invalid';
        }

        return 'email:' . $this->validatedValueObject('profile.email', EmailAddress::class);
    }

    public function optional(): string
    {
        if (!$this->validate(['email' => 'permit_empty|valid_value_object[' . EmailAddress::class . ']'])) {
            return 'invalid';
        }

        return 'email:' . ($this->validatedValueObject('email', EmailAddress::class) ?? 'null');
    }

    public function range(): string
    {
        if (!$this->validate(['range' => 'required|valid_value_object[' . DateRange::class . ']'])) {
            return 'invalid';
        }

        $range = $this->validatedValueObject('range', DateRange::class);

        return 'range:' . ($range?->start->format('Y-m-d') ?? 'null');
    }

    public function beforeValidation(): string
    {
        return (string) $this->validatedValueObject('email', EmailAddress::class);
    }

    public function unvalidatedField(): string
    {
        $this->validate(['other' => 'permit_empty']);

        return (string) $this->validatedValueObject('email', EmailAddress::class);
    }

    public function unconvertible(): string
    {
        $this->validate(['email' => 'required']);

        return (string) $this->validatedValueObject('email', EmailAddress::class);
    }

    public function wildcard(): string
    {
        $this->validate(['emails.*' => 'required']);

        return (string) $this->validatedValueObject('emails.*', EmailAddress::class);
    }

    public function untrusted(): string
    {
        $this->validate(['text' => 'required']);

        return (string) $this->validatedValueObject('text', BoundedString::class);
    }
}
