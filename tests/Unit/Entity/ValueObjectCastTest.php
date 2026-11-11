<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Unit\Entity;

use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Entity\ValueObjectCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Account;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\MisconfiguredEntity;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Priority;
use MSSTC4PHP\ValueObject\Exception\InvalidEmailAddressException;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Exception\InvalidPortException;
use MSSTC4PHP\ValueObject\Geo\Latitude;
use MSSTC4PHP\ValueObject\I18n\CurrencyCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\IntRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * A real CodeIgniter Entity, no application boot: Entity casting runs standalone.
 */
#[CoversClass(ValueObjectCast::class)]
#[UsesClass(ValueObjectColumn::class)]
final class ValueObjectCastTest extends TestCase
{
    public function testSettingValueObjectsStoresTheirPayloads(): void
    {
        $account = new Account();
        $account->email = EmailAddress::from('a@example.com');
        $account->port = Port::from(8080);
        $account->latitude = Latitude::from(52.5);
        $account->currency = CurrencyCode::EUR;
        $account->priority = Priority::High;
        $account->range = new IntRange(1, 5);

        self::assertSame([
            'email' => 'a@example.com',
            'port' => 8080,
            'latitude' => 52.5,
            'currency' => 'EUR',
            'priority' => 2,
            'range' => '{"min":1,"max":5}',
        ], $account->toRawArray());
    }

    public function testReadingStoredRowsGivesValueObjects(): void
    {
        $account = new Account();
        $account->injectRawData([
            'email' => 'a@example.com',
            'port' => '8080',
            'latitude' => '52.5',
            'currency' => 'EUR',
            'priority' => '1',
            'range' => '{"min":1,"max":5}',
        ]);

        self::assertEquals(EmailAddress::from('a@example.com'), $account->email);
        self::assertEquals(Port::from(8080), $account->port);
        self::assertEquals(Latitude::from(52.5), $account->latitude);
        self::assertSame(CurrencyCode::EUR, $account->currency);
        self::assertSame(Priority::Low, $account->priority);
        self::assertEquals(new IntRange(1, 5), $account->range);
    }

    public function testConstructingFromFormInputValidatesAndStoresThePayload(): void
    {
        $account = new Account(['email' => 'a@example.com', 'port' => '443']);

        self::assertSame(['email' => 'a@example.com', 'port' => 443], $account->toRawArray());
        self::assertEquals(Port::from(443), $account->port);
    }

    public function testInvalidInputFailsOnTheWayIn(): void
    {
        $this->expectException(InvalidEmailAddressException::class);

        new Account(['email' => 'not an email']);
    }

    public function testAWrongTypeFailsOnTheWayIn(): void
    {
        $this->expectException(InvalidPayloadException::class);

        new Account()->fill(['email' => Port::from(80)]);
    }

    public function testMassAssignmentCannotChooseBoundedStringBounds(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('cannot be read from untrusted input');

        ValueObjectCast::set(['value' => 'x', 'minLength' => 0, 'maxLength' => null], [BoundedString::class]);
    }

    public function testABoundedStringInstanceIsWrittenAndReadBack(): void
    {
        $stored = ValueObjectCast::set(new BoundedString('x', 1, 3), [BoundedString::class]);

        self::assertSame('{"value":"x","minLength":1,"maxLength":3}', $stored);
        self::assertEquals(new BoundedString('x', 1, 3), ValueObjectCast::get($stored, [BoundedString::class]));
    }

    public function testAnInvalidStoredValueFailsLoudlyOnRead(): void
    {
        $account = new Account();
        $account->injectRawData(['port' => '70000']);

        $this->expectException(InvalidPortException::class);

        self::assertNull($account->port);
    }

    public function testNullPassesWithAndWithoutTheNullablePrefix(): void
    {
        $account = new Account();
        $account->email = null;
        $account->range = null;

        self::assertSame(['email' => null, 'range' => null], $account->toRawArray());
        self::assertNull($account->email);
        self::assertNull($account->range);
        self::assertNull($account->port);
    }

    public function testAnUnsupportedClassFailsAtFirstUse(): void
    {
        $entity = new MisconfiguredEntity();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DateTimeImmutable is not a value object the cast supports');

        $entity->created = 'now';
    }

    public function testAMissingClassParameterFailsAtFirstUse(): void
    {
        $entity = new MisconfiguredEntity();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('needs a class parameter');

        self::assertNull($entity->bare);
    }

    public function testCastHandlersCanBeCalledDirectly(): void
    {
        self::assertEquals(Port::from(1), ValueObjectCast::get(1, [Port::class]));
        self::assertSame(1, ValueObjectCast::set(Port::from(1), [Port::class]));
        self::assertNull(ValueObjectCast::get(null, [Port::class, 'nullable']));
        self::assertNull(ValueObjectCast::set(null, [Port::class]));
    }

    public function testNullStillChecksTheConfiguration(): void
    {
        $this->expectException(LogicException::class);

        ValueObjectCast::set(null, []);
    }

    public function testNullOnReadStillChecksTheConfiguration(): void
    {
        $this->expectException(LogicException::class);

        ValueObjectCast::get(null, []);
    }
}
