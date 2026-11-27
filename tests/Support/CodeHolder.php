<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use CodeIgniter\Entity\Entity;
use MSSTC4PHP\ValueObject\CodeIgniter4\Entity\ValueObjectCast;

/**
 * @property Code|null $code
 * @property Dimensions|null $size
 */
final class CodeHolder extends Entity
{
    /** @var array<string, string> */
    protected $castHandlers = ['vo' => ValueObjectCast::class];

    /** @var array<string, string> */
    protected $casts = [
        'code' => 'vo[' . Code::class . ']',
        'size' => '?vo[' . Dimensions::class . ']',
    ];
}
