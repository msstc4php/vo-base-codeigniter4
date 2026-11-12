<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Unit\Internal;

use ArgumentCountError;
use CodeIgniter\Exceptions\PageNotFoundException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValueObjectArguments;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ArgumentBinder;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeIgniterApp;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\UsersController;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArgumentBinder::class)]
#[UsesTrait(ValueObjectArguments::class)]
#[UsesClass(ValueObjectColumn::class)]
final class ArgumentBinderTest extends TestCase
{
    #[Override]
    public static function setUpBeforeClass(): void
    {
        CodeIgniterApp::boot();
    }

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

    #[DataProvider('unroutableMethods')]
    public function testAnUnroutableMethodIsNotFound(string $method): void
    {
        $this->expectException(PageNotFoundException::class);

        new UsersController()->_remap($method);
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function unroutableMethods(): iterable
    {
        yield 'protected' => ['hidden'];
        yield 'static' => ['staticAction'];
        yield 'underscore prefix' => ['_secret'];
        yield 'declared by the base controller' => ['initController'];
        yield 'missing' => ['nope'];
    }

    public function testEveryParameterIsConvertedInPlace(): void
    {
        self::assertSame('a@b.co:High', new UsersController()->_remap('pair', 'a@b.co', '2'));
    }

    public function testAnAbsentNullableNonValueObjectParameterKeepsThePhpError(): void
    {
        $this->expectException(ArgumentCountError::class);

        new UsersController()->_remap('nullableString');
    }
}
