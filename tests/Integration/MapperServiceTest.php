<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Integration;

use CodeIgniter\Config\Services;
use CodeIgniter\DataCaster\DataCaster;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\DataCaster\ValueObjectCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeIgniterApp;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Dimensions;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\DimensionsCodec;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ValueObjectColumn::class)]
#[UsesClass(ValueObjectCast::class)]
final class MapperServiceTest extends TestCase
{
    #[Override]
    public static function setUpBeforeClass(): void
    {
        CodeIgniterApp::boot();
    }

    #[Override]
    protected function tearDown(): void
    {
        Services::resetSingle('valueobjectmapper');
    }

    public function testWithoutAServiceTheCoresCodecsAreUsed(): void
    {
        $mapper = ValueObjectColumn::mapper();

        self::assertSame($mapper, ValueObjectColumn::mapper());
        self::assertFalse($mapper->supports(Dimensions::class));
    }

    public function testTheValueObjectMapperServiceIsUsed(): void
    {
        $mapper = new ValueObjectMapper([new DimensionsCodec()]);
        Services::override('valueobjectmapper', $mapper);

        self::assertSame($mapper, ValueObjectColumn::mapper());
    }

    public function testAServiceThatIsNoMapperIsAConfigurationError(): void
    {
        Services::override('valueobjectmapper', new stdClass());

        $this->expectExceptionObject(new LogicException('The valueobjectmapper service is stdClass, not a ' . ValueObjectMapper::class . '.'));

        ValueObjectColumn::mapper();
    }

    public function testACastReachesTheServicesCodecs(): void
    {
        Services::override('valueobjectmapper', new ValueObjectMapper([new DimensionsCodec()]));
        $caster = new DataCaster(['vo' => ValueObjectCast::class], ['size' => 'vo[' . Dimensions::class . ']']);

        $stored = $caster->castAs(new Dimensions(2, 3), 'size', 'set');

        self::assertIsString($stored);
        self::assertEquals(new Dimensions(2, 3), $caster->castAs($stored, 'size'));
    }
}
