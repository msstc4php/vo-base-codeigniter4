<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Integration;

use CodeIgniter\DataCaster\DataCaster;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\RouteCollection;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValidatedValueObjects;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValueObjectArguments;
use MSSTC4PHP\ValueObject\CodeIgniter4\DataCaster\ValueObjectCast as ModelCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Entity\ValueObjectCast as EntityCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ArgumentBinder;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValidatedData;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Code;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeHolder;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeIgniterApp;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\ProfileController;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\UsersController;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Http;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\LongCode;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\ShortCode;
use MSSTC4PHP\ValueObject\CodeIgniter4\Validation\ValueObjectRules;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

/**
 * An abstract class implementing ResolvesConcreteClass (vo-base 1.4) through every entry of the bridge.
 */
#[CoversClass(ValueObjectColumn::class)]
#[UsesClass(EntityCast::class)]
#[UsesClass(ModelCast::class)]
#[UsesClass(ValueObjectRules::class)]
#[UsesClass(ArgumentBinder::class)]
#[UsesClass(ValidatedData::class)]
#[UsesTrait(ValueObjectArguments::class)]
#[UsesTrait(ValidatedValueObjects::class)]
final class ResolvesConcreteClassTest extends TestCase
{
    #[Override]
    public static function setUpBeforeClass(): void
    {
        CodeIgniterApp::boot();
    }

    public function testTheModelCastReadsAndWritesTheSubclassTheValueFits(): void
    {
        $caster = new DataCaster(['vo' => ModelCast::class], ['code' => 'vo[' . Code::class . ']']);

        self::assertInstanceOf(ShortCode::class, $caster->castAs('ab', 'code'));
        self::assertInstanceOf(LongCode::class, $caster->castAs('abcd', 'code'));
        self::assertSame('abcd', $caster->castAs('abcd', 'code', 'set'));
        self::assertSame('ab', $caster->castAs(Code::from('ab'), 'code', 'set'));
    }

    public function testTheEntityCastReadsAndWritesTheSubclassTheValueFits(): void
    {
        $holder = new CodeHolder();
        $holder->injectRawData(['code' => 'abcd']);
        self::assertInstanceOf(LongCode::class, $holder->code);

        $holder->fill(['code' => 'ab']);
        self::assertInstanceOf(ShortCode::class, $holder->code);
        self::assertSame(['code' => 'ab'], $holder->toRawArray());
    }

    public function testAValueNoSubclassFitsIsRefusedOnWriteAndOnRead(): void
    {
        $caster = new DataCaster(['vo' => ModelCast::class], ['code' => 'vo[' . Code::class . ']']);

        foreach (['set', 'get'] as $direction) {
            try {
                $caster->castAs('abcdefgh', 'code', $direction);
                self::fail('An InvalidValueObjectException was expected.');
            } catch (InvalidValueObjectException $e) {
                self::assertSame('Not a valid code.', $e->getMessage());
            }
        }
    }

    public function testTheRuleAcceptsWhatASubclassFits(): void
    {
        $rules = new ValueObjectRules();

        self::assertTrue($rules->valid_value_object('ab', Code::class));
        self::assertTrue($rules->valid_value_object('abcd', Code::class));
        self::assertFalse($rules->valid_value_object('abcdefgh', Code::class));
        self::assertFalse($rules->valid_value_object('abcd', ShortCode::class));
    }

    public function testAControllerArgumentIsTheSubclassTheSegmentFits(): void
    {
        self::assertSame('short:ab', $this->get('code/ab')->getBody());
        self::assertSame('long:abcd', $this->get('code/abcd')->getBody());
        self::assertSame(404, $this->get('code/abcdefgh')->getStatusCode());
    }

    public function testValidatedDataIsTheSubclassTheFieldFits(): void
    {
        $controller = '\\' . ProfileController::class;
        $response = Http::request(static function (RouteCollection $routes) use ($controller): void {
            $routes->post('profile', $controller . '::code');
        }, 'POST', 'profile', ['code' => 'abcd']);

        self::assertSame('long:abcd', $response->getBody());
    }

    private function get(string $path): ResponseInterface
    {
        $controller = '\\' . UsersController::class;

        return Http::request(static function (RouteCollection $routes) use ($controller): void {
            $routes->get('code/(:segment)', $controller . '::code/$1');
        }, 'GET', $path);
    }
}
