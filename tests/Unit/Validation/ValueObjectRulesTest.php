<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Unit\Validation;

use CodeIgniter\Config\Services;
use CodeIgniter\Validation\Validation;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeIgniterApp;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Priority;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\ValidationConfig;
use MSSTC4PHP\ValueObject\CodeIgniter4\Validation\ValueObjectRules;
use MSSTC4PHP\ValueObject\Geo\Latitude;
use MSSTC4PHP\ValueObject\I18n\CurrencyCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\IntRange;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * CodeIgniter's real Validation service with CodeIgniter's strict rule sets plus this one.
 */
#[CoversClass(ValueObjectRules::class)]
#[UsesClass(ValueObjectColumn::class)]
final class ValueObjectRulesTest extends TestCase
{
    #[Override]
    public static function setUpBeforeClass(): void
    {
        CodeIgniterApp::boot();
    }

    /**
     * @return iterable<string, array{string, mixed, bool}>
     */
    public static function inputs(): iterable
    {
        yield 'valid string-backed' => [EmailAddress::class, 'a@example.com', true];
        yield 'invalid string-backed' => [EmailAddress::class, 'nope', false];
        yield 'int-backed from a form string' => [Port::class, '8080', true];
        yield 'int-backed from JSON' => [Port::class, 8080, true];
        yield 'int-backed out of range' => [Port::class, '70000', false];
        yield 'int-backed not a number' => [Port::class, '80a', false];
        yield 'float-backed' => [Latitude::class, '45.5', true];
        yield 'float-backed out of range' => [Latitude::class, '95', false];
        yield 'string enum' => [CurrencyCode::class, 'EUR', true];
        yield 'string enum unknown case' => [CurrencyCode::class, 'EURO', false];
        yield 'int enum' => [Priority::class, '2', true];
        yield 'int enum unknown case' => [Priority::class, '3', false];
        yield 'composite from an array' => [IntRange::class, ['min' => '1', 'max' => '5'], false];
        yield 'composite from an int array' => [IntRange::class, ['min' => 1, 'max' => 5], true];
        yield 'composite reversed' => [IntRange::class, ['min' => 5, 'max' => 1], false];
        yield 'array for a string' => [EmailAddress::class, ['a@example.com'], false];
        yield 'bool' => [EmailAddress::class, true, false];
        yield 'whitespace is not empty' => [EmailAddress::class, ' ', false];
        yield 'null is empty' => [EmailAddress::class, null, true];
        yield 'empty string is empty' => [EmailAddress::class, '', true];
        yield 'empty array is empty' => [IntRange::class, [], true];
    }

    #[DataProvider('inputs')]
    public function testTheRuleMirrorsTheValueObject(string $class, mixed $value, bool $expected): void
    {
        self::assertSame($expected, new ValueObjectRules()->valid_value_object($value, $class));
    }

    public function testALeadingBackslashIsAccepted(): void
    {
        self::assertTrue(new ValueObjectRules()->valid_value_object('80', '\\' . Port::class));
        self::assertTrue(new ValueObjectRules()->valid_value_object(['min' => 1, 'max' => 2], '\\' . IntRange::class));
    }

    public function testACustomMessageWins(): void
    {
        $validation = $this->validation();
        $validation->setRules(
            ['port' => ['label' => 'Port', 'rules' => 'valid_value_object[' . Port::class . ']', 'errors' => ['valid_value_object' => '{field}: {value} is no port.']]],
        );

        self::assertFalse($validation->run(['port' => '70000']));
        self::assertSame(['port' => 'Port: 70000 is no port.'], $validation->getErrors());
    }

    public function testAValidFormPasses(): void
    {
        $validation = $this->validation();
        $validation->setRules([
            'email' => 'required|valid_value_object[' . EmailAddress::class . ']',
            'port' => 'permit_empty|valid_value_object[' . Port::class . ']',
            'currency' => 'valid_value_object[' . CurrencyCode::class . ']',
        ]);

        self::assertTrue($validation->run(['email' => 'a@example.com', 'port' => '', 'currency' => 'USD']));
        self::assertSame([], $validation->getErrors());
    }

    public function testAMissingParameterIsAProgrammerError(): void
    {
        $validation = $this->validation();
        $validation->setRules(['email' => 'valid_value_object']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The value-object validation rule needs a class parameter');

        $validation->run(['email' => 'a@example.com']);
    }

    public function testAPlaceholderInTheParameterCannotReachTheAutoloader(): void
    {
        $validation = $this->validation();
        $validation->setRules(['value' => 'valid_value_object[{type}]', 'type' => 'permit_empty']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The value-object validation rule parameter "..\\\\Evil" is not a class name.');

        $validation->run(['value' => 'x', 'type' => '..\\Evil']);
    }

    public function testAnUnsupportedClassIsAProgrammerError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('stdClass cannot be validated from input');

        new ValueObjectRules()->valid_value_object('x', stdClass::class);
    }

    public function testAnUnsupportedClassFailsEvenForEmptyInput(): void
    {
        $this->expectException(LogicException::class);

        new ValueObjectRules()->valid_value_object(null, stdClass::class);
    }

    public function testBoundedStringIsRefusedBecauseTheClientWouldChooseTheBounds(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(BoundedString::class . ' cannot be validated from input: it is not supported, or its payload carries its own constraints (BoundedString: check the length with min_length/max_length, or use NonEmptyString).');

        new ValueObjectRules()->valid_value_object('{"value":"x","minLength":0,"maxLength":null}', BoundedString::class);
    }

    public function testBoundedStringIsRefusedForEmptyInputToo(): void
    {
        $this->expectException(LogicException::class);

        new ValueObjectRules()->valid_value_object('', '\\' . BoundedString::class);
    }

    private function validation(): Validation
    {
        return new Validation(new ValidationConfig(), Services::renderer());
    }
}
