<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;
use MSSTC4PHP\ValueObject\CodeIgniter4\Validation\ValueObjectRules;

/**
 * Config\Validation's rule sets as an application ships them, plus this package's.
 */
final class ValidationConfig
{
    /** @var list<class-string> */
    public array $ruleSets = [Rules::class, FormatRules::class, FileRules::class, CreditCardRules::class, ValueObjectRules::class];
}
