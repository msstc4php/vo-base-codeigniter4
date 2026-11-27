<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Integration;

use CodeIgniter\Config\Services;
use CodeIgniter\DataCaster\DataCaster;
use CodeIgniter\Router\RouteCollection;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValidatedValueObjects;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValueObjectArguments;
use MSSTC4PHP\ValueObject\CodeIgniter4\DataCaster\ValueObjectCast as ModelCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Entity\ValueObjectCast as EntityCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ArgumentBinder;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValidatedData;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\AppCodecs;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeHolder;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeIgniterApp;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\ProfileController;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\UsersController;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Dimensions;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Http;
use MSSTC4PHP\ValueObject\CodeIgniter4\Validation\ValueObjectRules;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

/**
 * A `valueObjectMapper` service built from a package's ProvidesCodecs set (vo-base 1.4) reaches every entry.
 */
#[CoversClass(ValueObjectColumn::class)]
#[UsesClass(EntityCast::class)]
#[UsesClass(ModelCast::class)]
#[UsesClass(ValueObjectRules::class)]
#[UsesClass(ArgumentBinder::class)]
#[UsesClass(ValidatedData::class)]
#[UsesTrait(ValueObjectArguments::class)]
#[UsesTrait(ValidatedValueObjects::class)]
final class ProvidesCodecsTest extends TestCase
{
    #[Override]
    public static function setUpBeforeClass(): void
    {
        CodeIgniterApp::boot();
    }

    #[Override]
    protected function setUp(): void
    {
        Services::override('valueobjectmapper', new ValueObjectMapper(AppCodecs::all()));
    }

    #[Override]
    protected function tearDown(): void
    {
        Services::resetSingle('valueobjectmapper');
    }

    public function testBothCastsReachTheSet(): void
    {
        $caster = new DataCaster(['vo' => ModelCast::class], ['size' => 'vo[' . Dimensions::class . ']']);
        self::assertSame('{"width":2,"height":3}', $caster->castAs(new Dimensions(2, 3), 'size', 'set'));
        self::assertEquals(new Dimensions(2, 3), $caster->castAs('{"width":2,"height":3}', 'size'));

        $holder = new CodeHolder();
        $holder->fill(['size' => ['width' => 4, 'height' => 5]]);
        self::assertSame(['size' => '{"width":4,"height":5}'], $holder->toRawArray());
        self::assertEquals(new Dimensions(4, 5), $holder->size);
    }

    public function testTheRuleReachesTheSet(): void
    {
        $rules = new ValueObjectRules();

        self::assertTrue($rules->valid_value_object('{"width":2,"height":3}', Dimensions::class));
        self::assertFalse($rules->valid_value_object('{"width":0,"height":3}', Dimensions::class));
    }

    public function testValidatedDataReachesTheSet(): void
    {
        $controller = '\\' . ProfileController::class;
        $response = Http::request(static function (RouteCollection $routes) use ($controller): void {
            $routes->post('profile', $controller . '::size');
        }, 'POST', 'profile', ['size' => '{"width":2,"height":3}']);

        self::assertSame('size:2x3', $response->getBody());
    }

    public function testControllerArgumentsReachTheSet(): void
    {
        $controller = '\\' . UsersController::class;

        // Supported through the set, so the binder refuses it as a composite instead of leaving it to PHP.
        try {
            Http::request(static function (RouteCollection $routes) use ($controller): void {
                $routes->get('size/(:segment)', $controller . '::size/$1');
            }, 'GET', 'size/x');
            self::fail('A LogicException was expected.');
        } catch (LogicException $e) {
            self::assertSame(\sprintf('Parameter $size of %s::size() cannot be bound from the route: a URL segment is a string, but this class is built from several values.', UsersController::class), $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }
}
