<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

enum Priority: int
{
    case Low = 1;
    case High = 2;
}
