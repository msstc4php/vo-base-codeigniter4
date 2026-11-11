<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Unit\Internal;

use BackedEnum;
use DateTimeImmutable;
use JsonException;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Priority;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidEmailAddressException;
use MSSTC4PHP\ValueObject\Exception\InvalidIntRangeException;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Exception\InvalidPortException;
use MSSTC4PHP\ValueObject\Geo\Latitude;
use MSSTC4PHP\ValueObject\I18n\CurrencyCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\IntRange;
use MSSTC4PHP\ValueObject\Temporal\DateRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

#[CoversClass(ValueObjectColumn::class)]
final class ValueObjectColumnTest extends TestCase
{
    public function testTargetClassIsTheFirstParameter(): void
    {
        self::assertSame(EmailAddress::class, ValueObjectColumn::targetClass([EmailAddress::class]));
    }

    public function testTargetClassIgnoresTheNullableMarkerAndALeadingBackslash(): void
    {
        self::assertSame(Port::class, ValueObjectColumn::targetClass(['\\' . Port::class, 'nullable']));
        self::assertSame(IntRange::class, ValueObjectColumn::targetClass(['\\' . IntRange::class]));
    }

    public function testTargetClassRejectsAnExtraParameter(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('one class parameter, "json, nullable2" given in addition');

        ValueObjectColumn::targetClass([Port::class, 'json', 'nullable', 'nullable2']);
    }

    public function testTargetClassRequiresAParameter(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The value-object cast needs a class parameter.');

        ValueObjectColumn::targetClass([]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedClasses(): iterable
    {
        yield 'plain object' => [stdClass::class];
        yield 'date' => [DateTimeImmutable::class];
        yield 'unknown class' => ['App\\Missing'];
        yield 'the contract itself' => [ValueObject::class];
    }

    #[DataProvider('unsupportedClasses')]
    public function testAnUnsupportedClassIsAProgrammerError(string $class): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($class . ' is not a value object the cast supports');

        ValueObjectColumn::targetClass([$class]);
    }

    public function testAnUnsupportedClassIsRefusedForInput(): void
    {
        $this->expectExceptionMessage('stdClass cannot be validated from input');

        ValueObjectColumn::untrustedTargetClass(stdClass::class);
    }

    /**
     * @return iterable<string, array{class-string<ValueObject|BackedEnum>, string|int|float, ValueObject|BackedEnum}>
     */
    public static function storedValues(): iterable
    {
        yield 'string-backed' => [EmailAddress::class, 'a@example.com', EmailAddress::from('a@example.com')];
        yield 'int-backed from int' => [Port::class, 8080, Port::from(8080)];
        yield 'int-backed from a PDO string' => [Port::class, '8080', Port::from(8080)];
        yield 'float-backed' => [Latitude::class, 52.5, Latitude::from(52.5)];
        yield 'float-backed from a numeric string' => [Latitude::class, '52.5', Latitude::from(52.5)];
        yield 'string enum' => [CurrencyCode::class, 'EUR', CurrencyCode::EUR];
        yield 'int enum from a string' => [Priority::class, '2', Priority::High];
        yield 'composite from JSON' => [IntRange::class, '{"min":1,"max":5}', new IntRange(1, 5)];
    }

    /**
     * @param class-string<ValueObject|BackedEnum> $class
     */
    #[DataProvider('storedValues')]
    public function testToPhpReadsEveryKind(string $class, string|int|float $stored, ValueObject|BackedEnum $expected): void
    {
        self::assertEquals($expected, ValueObjectColumn::fromStorage($stored, $class));
    }

    public function testToPhpReturnsAnInstanceAsIs(): void
    {
        $email = EmailAddress::from('a@example.com');

        self::assertSame($email, ValueObjectColumn::fromStorage($email, EmailAddress::class));
    }

    public function testToPhpReadsACompositeFromItsArrayPayload(): void
    {
        self::assertEquals(new IntRange(1, 5), ValueObjectColumn::fromStorage(['min' => 1, 'max' => 5], IntRange::class));
    }

    public function testInvalidContentRaisesTheValueObjectsException(): void
    {
        $this->expectException(InvalidEmailAddressException::class);

        ValueObjectColumn::fromStorage('not an email', EmailAddress::class);
    }

    public function testInvalidCompositeContentRaisesTheValueObjectsException(): void
    {
        $this->expectException(InvalidIntRangeException::class);

        ValueObjectColumn::fromStorage('{"min":5,"max":1}', IntRange::class);
    }

    public function testAWrongTypeIsAPayloadError(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('must be a string, int given');

        ValueObjectColumn::fromStorage(42, EmailAddress::class);
    }

    public function testAnotherValueObjectIsAPayloadError(): void
    {
        $this->expectException(InvalidPayloadException::class);

        ValueObjectColumn::fromStorage(Port::from(80), EmailAddress::class);
    }

    public function testMalformedJsonForACompositeIsAPayloadError(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('A ' . IntRange::class . ' payload must be JSON text: Syntax error');

        try {
            ValueObjectColumn::fromStorage('{"min":1,', IntRange::class);
        } catch (InvalidPayloadException $e) {
            self::assertInstanceOf(JsonException::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testJsonScalarForACompositeIsAPayloadError(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('must be an array, int given');

        ValueObjectColumn::fromStorage('5', IntRange::class);
    }

    public function testJsonArrayForAScalarReportsTheOriginalTypeError(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('must be an integer, string given');

        ValueObjectColumn::fromStorage('[1]', Port::class);
    }

    public function testAnArrayForAScalarIsAPayloadError(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('must be an integer, array given');

        ValueObjectColumn::fromStorage([1], Port::class);
    }

    public function testInvalidIntContentIsTheValueObjectsException(): void
    {
        $this->expectException(InvalidPortException::class);

        ValueObjectColumn::fromStorage('70000', Port::class);
    }

    /**
     * @return iterable<string, array{class-string<ValueObject|BackedEnum>, mixed, string|int|float}>
     */
    public static function storageForms(): iterable
    {
        yield 'string-backed' => [EmailAddress::class, EmailAddress::from('a@example.com'), 'a@example.com'];
        yield 'int-backed' => [Port::class, Port::from(8080), 8080];
        yield 'float-backed' => [Latitude::class, Latitude::from(-12.25), -12.25];
        yield 'string enum' => [CurrencyCode::class, CurrencyCode::USD, 'USD'];
        yield 'int enum' => [Priority::class, Priority::Low, 1];
        yield 'composite as JSON' => [IntRange::class, new IntRange(-1, 3), '{"min":-1,"max":3}'];
        yield 'bounded string as JSON' => [BoundedString::class, new BoundedString('ab/é', 1, 9), '{"value":"ab\/\u00e9","minLength":1,"maxLength":9}'];
        yield 'raw form input' => [Port::class, '443', 443];
        yield 'raw composite array' => [IntRange::class, ['min' => 2, 'max' => 2], '{"min":2,"max":2}'];
    }

    /**
     * @param class-string<ValueObject|BackedEnum> $class
     */
    #[DataProvider('storageForms')]
    public function testToStorage(string $class, mixed $value, string|int|float $expected): void
    {
        self::assertSame($expected, ValueObjectColumn::toStorage($value, $class));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function rawBoundedStrings(): iterable
    {
        yield 'array' => [['value' => 'x', 'minLength' => 0, 'maxLength' => null]];
        yield 'JSON' => ['{"value":"x","minLength":0,"maxLength":null}'];
        yield 'plain string' => ['x'];
    }

    #[DataProvider('rawBoundedStrings')]
    public function testABoundedStringIsOnlyWrittenFromAnInstance(mixed $raw): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('A ' . BoundedString::class . ' cannot be read from untrusted input');

        ValueObjectColumn::toStorage($raw, BoundedString::class);
    }

    public function testABoundedStringIsStillReadFromStorage(): void
    {
        self::assertEquals(new BoundedString('x', 0, 3), ValueObjectColumn::fromStorage('{"value":"x","minLength":0,"maxLength":3}', BoundedString::class));
    }

    public function testUntrustedTargetClassRequiresAParameter(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The value-object validation rule needs a class parameter.');

        ValueObjectColumn::untrustedTargetClass(null);
    }

    public function testUntrustedTargetClassAcceptsALeadingBackslash(): void
    {
        self::assertSame(IntRange::class, ValueObjectColumn::untrustedTargetClass('\\' . IntRange::class));
    }

    public function testInputIsReadLikeStorageForOrdinaryClasses(): void
    {
        self::assertEquals(new IntRange(1, 5), ValueObjectColumn::fromInput('{"min":1,"max":5}', IntRange::class));
        self::assertEquals(Port::from(80), ValueObjectColumn::fromInput('80', Port::class));
    }

    public function testInputNeverBuildsABoundedString(): void
    {
        $this->expectException(InvalidPayloadException::class);

        ValueObjectColumn::fromInput('{"value":"x","minLength":0,"maxLength":null}', BoundedString::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notClassNames(): iterable
    {
        yield 'placeholder' => ['{field}'];
        yield 'path' => ['../App/Foo'];
        yield 'trailing backslash' => ['App\\'];
        yield 'double backslash' => ['App\\\\Foo'];
        yield 'leading digit' => ['1App'];
        yield 'newline' => ["App\\Foo\n"];
        yield 'space' => ['App Foo'];
    }

    #[DataProvider('notClassNames')]
    public function testAParameterThatIsNotAClassNameNeverReachesTheAutoloader(string $parameter): void
    {
        $autoloaded = [];
        $probe = static function (string $class) use (&$autoloaded): void {
            $autoloaded[] = $class;
        };
        spl_autoload_register($probe);

        try {
            ValueObjectColumn::untrustedTargetClass($parameter);
            self::fail('A LogicException was expected.');
        } catch (LogicException $e) {
            self::assertStringContainsString('parameter "', $e->getMessage());
            self::assertStringContainsString('" is not a class name.', $e->getMessage());
        } finally {
            spl_autoload_unregister($probe);
        }

        self::assertSame([], $autoloaded);
    }

    public function testAnInvalidCastParameterIsRefusedToo(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The value-object cast parameter "{x}" is not a class name.');

        ValueObjectColumn::targetClass(['{x}']);
    }

    public function testACheckedClassIsCachedPerParameter(): void
    {
        self::assertSame(Port::class, ValueObjectColumn::targetClass(['\\' . Port::class]));
        self::assertSame(Latitude::class, ValueObjectColumn::untrustedTargetClass('\\' . Latitude::class));

        $castClasses = new ReflectionProperty(ValueObjectColumn::class, 'castClasses')->getValue();
        $inputClasses = new ReflectionProperty(ValueObjectColumn::class, 'inputClasses')->getValue();
        self::assertIsArray($castClasses);
        self::assertIsArray($inputClasses);
        self::assertSame(Port::class, $castClasses['\\' . Port::class] ?? null);
        self::assertSame(Latitude::class, $inputClasses['\\' . Latitude::class] ?? null);
    }

    public function testAClassNameMayUseNonAsciiLetters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('App\\Ügyfél is not a value object the cast supports');

        ValueObjectColumn::targetClass(['App\\Ügyfél']);
    }

    public function testADateRangeRoundTrips(): void
    {
        $range = new DateRange(new DateTimeImmutable('2026-11-01T00:00:00.000000+00:00'), new DateTimeImmutable('2026-11-30T23:59:59.500000+02:00'));
        $stored = ValueObjectColumn::toStorage($range, DateRange::class);

        self::assertIsString($stored);
        self::assertEquals($range, ValueObjectColumn::fromStorage($stored, DateRange::class));
        self::assertEquals($range, ValueObjectColumn::fromInput($stored, DateRange::class));
    }

    public function testToStorageValidatesRawInput(): void
    {
        $this->expectException(InvalidEmailAddressException::class);

        ValueObjectColumn::toStorage('nope', EmailAddress::class);
    }

    public function testAPayloadJsonCannotEncodeIsAPayloadError(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('A ' . BoundedString::class . ' payload cannot be encoded as JSON: Malformed UTF-8');

        try {
            ValueObjectColumn::toStorage(new BoundedString("\xB1", 0, 5), BoundedString::class);
        } catch (InvalidPayloadException $e) {
            self::assertInstanceOf(JsonException::class, $e->getPrevious());

            throw $e;
        }
    }
}
