<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use CodeIgniter\Model;

/**
 * Entity casts only: the Model stores the Entity's raw attributes, which already are payloads.
 */
final class AccountEntityModel extends Model
{
    protected $table = 'accounts';

    protected $returnType = Account::class;

    protected $allowedFields = ['email', 'port', 'range'];
}
