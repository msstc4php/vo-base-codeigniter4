<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Unit\DataCaster;

use CodeIgniter\DataCaster\DataCaster;
use CodeIgniter\Exceptions\InvalidArgumentException as CodeIgniterInvalidArgumentException;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\DataCaster\ValueObjectCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Priority;
use MSSTC4PHP\ValueObject\Exception\InvalidEmailAddressException;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Geo\Latitude;
use MSSTC4PHP\ValueObject\I18n\CurrencyCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\IntRange;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The Model's own DataCaster, strict as a Model builds it, without a database.
 */
#[CoversClass(ValueObjectCast::class)]
#[UsesClass(ValueObjectColumn::class)]
final class ValueObjectCastTest extends TestCase
{
    private DataCaster $caster;

    #[Override]
    protected function setUp(): void
    {
        $this->caster = new DataCaster(['vo' => ValueObjectCast::class], [
            'email' => 'vo[' . EmailAddress::class . ']',
            'port' => 'vo[' . Port::class . ']',
            'latitude' => 'vo[' . Latitude::class . ']',
            'currency' => 'vo[ ' . CurrencyCode::class . ' ]',
            'priority' => 'vo[' . Priority::class . ']',
            'range' => '?vo[' . IntRange::class . ']',
            'broken' => 'vo[' . stdClass::class . ']',
            'bounded' => 'vo[' . BoundedString::class . ']',
        ]);
    }

    public function testEveryKindRoundTrips(): void
    {
        $values = [
            'email' => [EmailAddress::from('a@example.com'), 'a@example.com'],
            'port' => [Port::from(8080), 8080],
            'latitude' => [Latitude::from(-1.5), -1.5],
            'currency' => [CurrencyCode::JPY, 'JPY'],
            'priority' => [Priority::High, 2],
            'range' => [new IntRange(0, 9), '{"min":0,"max":9}'],
        ];

        foreach ($values as $field => [$php, $stored]) {
            self::assertSame($stored, $this->caster->castAs($php, $field, 'set'), $field);
            self::assertEquals($php, $this->caster->castAs($stored, $field), $field);
        }
    }

    public function testDriverStringsAreRead(): void
    {
        self::assertEquals(Port::from(8080), $this->caster->castAs('8080', 'port'));
        self::assertSame(Priority::Low, $this->caster->castAs('1', 'priority'));
    }

    public function testANullableCastPassesNull(): void
    {
        self::assertNull($this->caster->castAs(null, 'range'));
        self::assertNull($this->caster->castAs(null, 'range', 'set'));
    }

    public function testCodeIgniterRejectsNullForANonNullableCast(): void
    {
        $this->expectException(CodeIgniterInvalidArgumentException::class);
        $this->expectExceptionMessage('Field "email" is not nullable, but null was passed.');

        $this->caster->castAs(null, 'email');
    }

    public function testAnInvalidStoredValueFailsLoudly(): void
    {
        $this->expectException(InvalidEmailAddressException::class);

        $this->caster->castAs('not an email', 'email');
    }

    public function testAWrongTypeFailsOnWrite(): void
    {
        $this->expectException(InvalidPayloadException::class);

        $this->caster->castAs(new IntRange(1, 2), 'email', 'set');
    }

    public function testMassAssignmentCannotChooseBoundedStringBounds(): void
    {
        $this->expectException(LogicException::class);

        $this->caster->castAs('{"value":"x","minLength":0,"maxLength":null}', 'bounded', 'set');
    }

    public function testABoundedStringInstanceIsWrittenAndReadBack(): void
    {
        $stored = $this->caster->castAs(new BoundedString('x', 1, 3), 'bounded', 'set');

        self::assertSame('{"value":"x","minLength":1,"maxLength":3}', $stored);
        self::assertEquals(new BoundedString('x', 1, 3), $this->caster->castAs($stored, 'bounded'));
    }

    public function testAnUnsupportedClassIsAProgrammerError(): void
    {
        $this->expectException(LogicException::class);

        $this->caster->castAs('x', 'broken', 'set');
    }

    public function testAnUnsupportedClassFailsOnReadToo(): void
    {
        $this->expectException(LogicException::class);

        $this->caster->castAs('x', 'broken');
    }
}
