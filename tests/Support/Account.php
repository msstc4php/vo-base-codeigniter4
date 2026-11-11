<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use CodeIgniter\Entity\Entity;
use MSSTC4PHP\ValueObject\CodeIgniter4\Entity\ValueObjectCast;
use MSSTC4PHP\ValueObject\Geo\Latitude;
use MSSTC4PHP\ValueObject\I18n\CurrencyCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\IntRange;

/**
 * @property EmailAddress|null $email
 * @property Port|null $port
 * @property Latitude|null $latitude
 * @property CurrencyCode|null $currency
 * @property Priority|null $priority
 * @property IntRange|null $range
 * @property EmailAddress|string|null $email
 * @property Port|int|string|null $port
 * @property Latitude|float|string|null $latitude
 * @property CurrencyCode|string|null $currency
 * @property Priority|int|string|null $priority
 * @property IntRange|string|null $range
 */
final class Account extends Entity
{
    /** @var array<string, string> */
    protected $castHandlers = ['vo' => ValueObjectCast::class];

    /** @var array<string, string> */
    protected $casts = [
        'email' => 'vo[' . EmailAddress::class . ']',
        'port' => 'vo[' . Port::class . ']',
        'latitude' => 'vo[' . Latitude::class . ']',
        'currency' => 'vo[' . CurrencyCode::class . ']',
        'priority' => 'vo[' . Priority::class . ']',
        'range' => '?vo[' . IntRange::class . ']',
    ];
}
