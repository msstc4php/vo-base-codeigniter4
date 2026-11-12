<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Unit\Internal;

use ArgumentCountError;
use CodeIgniter\Exceptions\PageNotFoundException;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValueObjectArguments;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ArgumentBinder;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeIgniterApp;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\OverridingController;
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

    public function testAnAbsentRequiredValueObjectParameterIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

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
        yield 'private' => ['hidden'];
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

    public function testAnOverrideOfABaseControllerMethodIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        new OverridingController()->_remap('initController');
    }

    public function testARequiredParameterBeforeAnAbsentNullableOneKeepsThePhpError(): void
    {
        $this->expectException(ArgumentCountError::class);

        new UsersController()->_remap('mixedTail');
    }

    public function testAnAbsentNullableParameterWithADefaultKeepsTheDefault(): void
    {
        self::assertSame('High', new UsersController()->_remap('defaultedEnum'));
    }

    public function testAnAbsentNullableParameterWithoutADefaultIsNull(): void
    {
        self::assertSame('maybe:null', new UsersController()->_remap('maybeNoDefault'));
    }

    public function testAVariadicReceivesTheRemainingSegmentsOnce(): void
    {
        self::assertSame('a|b,c', new UsersController()->_remap('rest', 'a', 'b', 'c'));
    }

    public function testAUnionTypedParameterIsLeftAlone(): void
    {
        self::assertSame('string:a@b.co', new UsersController()->_remap('union', 'a@b.co'));
    }

    public function testEveryVariadicElementIsConverted(): void
    {
        self::assertSame('a@b.co,c@d.co', new UsersController()->_remap('emails', 'a@b.co', 'c@d.co'));
        self::assertSame('', new UsersController()->_remap('emails'));
    }

    public function testAnInvalidVariadicElementIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        new UsersController()->_remap('emails', 'a@b.co', 'nope');
    }

    public function testAnUnbindableVariadicTypeIsAProgrammerError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$texts');

        new UsersController()->_remap('boundedVariadic', 'abc');
    }

    public function testAnUnbindableParameterIsFoundWhateverTheRequestCarries(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('own constraints');

        new UsersController()->_remap('lateUnbindable');
    }

    public function testAScalarParameterKeepsPhpWeakModeCoercion(): void
    {
        self::assertSame('int:5', new UsersController()->_remap('intId', '5'));
    }
}
