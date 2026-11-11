<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use CodeIgniter\Model;
use MSSTC4PHP\ValueObject\CodeIgniter4\DataCaster\ValueObjectCast;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\IntRange;
use Override;

final class AccountModel extends Model
{
    protected $table = 'accounts';

    protected $returnType = 'array';

    protected $allowedFields = ['email', 'port', 'range'];

    protected array $casts = [
        'id' => 'int',
        'email' => 'vo[' . EmailAddress::class . ']',
        'port' => 'vo[' . Port::class . ']',
        'range' => '?vo[' . IntRange::class . ']',
    ];

    #[Override]
    protected function initialize(): void
    {
        $this->castHandlers = ['vo' => ValueObjectCast::class];
    }
}
