<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use Override;

final class ShortCode extends Code
{
    #[Override]
    protected function pattern(): string
    {
        return '/^[a-z]{1,3}$/D';
    }
}
