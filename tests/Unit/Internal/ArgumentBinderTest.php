<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Unit\Internal;

use ArgumentCountError;
use CodeIgniter\Exceptions\PageNotFoundException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ArgumentBinder;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\UsersController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArgumentBinder::class)]
final class ArgumentBinderTest extends TestCase
{
    public function testAnEmptySegmentIsNullForANullableParameter(): void
    {
        self::assertSame('maybe:null', new UsersController()->_remap('maybe', ''));
    }

    public function testAnEmptySegmentIsInvalidForANonNullableParameter(): void
    {
        $this->expectException(PageNotFoundException::class);

        new UsersController()->_remap('show', '');
    }

    public function testAnAbsentNonNullableParameterKeepsThePhpError(): void
    {
        $this->expectException(ArgumentCountError::class);

        new UsersController()->_remap('show');
    }

    public function testASurplusSegmentIsPassedThrough(): void
    {
        self::assertSame('jane@example.com:x', new UsersController()->_remap('withDefault', 'jane@example.com', 'x'));
    }

    public function testNumericEnumAndStringArePositional(): void
    {
        self::assertSame('priority:Low', new UsersController()->_remap('priority', '1'));
    }
}
