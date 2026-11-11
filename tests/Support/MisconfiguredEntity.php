<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use CodeIgniter\Entity\Entity;
use DateTimeImmutable;
use MSSTC4PHP\ValueObject\CodeIgniter4\Entity\ValueObjectCast;

/**
 * @property mixed $created
 * @property mixed $bare
 */
final class MisconfiguredEntity extends Entity
{
    /** @var array<string, string> */
    protected $castHandlers = ['vo' => ValueObjectCast::class];

    /** @var array<string, string> */
    protected $casts = [
        'created' => 'vo[' . DateTimeImmutable::class . ']',
        'bare' => 'vo',
    ];
}
