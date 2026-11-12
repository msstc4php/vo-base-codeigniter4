<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Integration;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\RouteCollection;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValueObjectArguments;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ArgumentBinder;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\UsersController;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Http;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversTrait(ValueObjectArguments::class)]
#[UsesClass(ArgumentBinder::class)]
#[UsesClass(ValueObjectColumn::class)]
final class ControllerArgumentsTest extends TestCase
{
    public function testAValidSegmentBecomesTheValueObject(): void
    {
        $response = $this->get('users/jane@example.com');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('email:jane@example.com', $response->getBody());
    }

    public function testAnInvalidSegmentIs404WithoutAClassName(): void
    {
        $response = $this->get('users/nope');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringNotContainsString('MSSTC4PHP', (string) $response->getBody());
    }

    public function testAnEnumSegmentBecomesTheCase(): void
    {
        self::assertSame('priority:High', $this->get('priority/2')->getBody());
    }

    public function testAnUnknownEnumCaseIs404(): void
    {
        $response = $this->get('priority/9');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringNotContainsString('MSSTC4PHP', (string) $response->getBody());
    }

    public function testAnAbsentNullableParameterIsNull(): void
    {
        self::assertSame('maybe:null', $this->get('maybe')->getBody());
        self::assertSame('maybe:null', $this->get('maybe-required')->getBody());
        self::assertSame('maybe:a@b.co', $this->get('maybe/a@b.co')->getBody());
    }

    public function testAnAbsentParameterWithADefaultKeepsTheDefault(): void
    {
        self::assertSame('a@b.co:dflt', $this->get('dflt/a@b.co')->getBody());
    }

    public function testOtherParametersAreLeftAlone(): void
    {
        self::assertSame('bob:a@b.co', $this->get('plain/bob/a@b.co')->getBody());
        self::assertSame(404, $this->get('plain/bob/nope')->getStatusCode());
    }

    public function testVariadicParametersAreLeftAlone(): void
    {
        self::assertSame('a|b', $this->get('rest/a/b')->getBody());
    }

    #[DataProvider('unreachableMethods')]
    public function testAnUnroutableMethodIs404(string $path): void
    {
        self::assertSame(404, $this->get($path)->getStatusCode());
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function unreachableMethods(): iterable
    {
        yield 'underscore prefix' => ['secret'];
        yield 'controller internals' => ['init'];
        yield 'private' => ['hidden'];
        yield 'static' => ['static'];
        yield 'missing' => ['missing'];
    }

    public function testAUnionTypeIsLeftAlone(): void
    {
        self::assertSame('string:a@b.co', $this->get('union/a@b.co')->getBody());
    }

    public function testATrustedSourceOnlyClassIsAProgrammerError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$text');

        $this->get('bounded/abc');
    }

    public function testACompositeIsAProgrammerError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$range');

        $this->get('composite/abc');
    }

    private function get(string $path): ResponseInterface
    {
        $controller = '\\' . UsersController::class;

        return Http::request(static function (RouteCollection $routes) use ($controller): void {
            $routes->get('users/(:segment)', $controller . '::show/$1');
            $routes->get('maybe', $controller . '::maybe');
            $routes->get('maybe/(:segment)', $controller . '::maybe/$1');
            $routes->get('maybe-required', $controller . '::maybeNoDefault');
            $routes->get('dflt/(:segment)', $controller . '::withDefault/$1');
            $routes->get('plain/(:segment)/(:segment)', $controller . '::plain/$1/$2');
            $routes->get('priority/(:segment)', $controller . '::priority/$1');
            $routes->get('union/(:segment)', $controller . '::union/$1');
            $routes->get('bounded/(:segment)', $controller . '::bounded/$1');
            $routes->get('composite/(:segment)', $controller . '::composite/$1');
            $routes->get('rest/(:segment)/(:segment)', $controller . '::rest/$1/$2');
            $routes->get('secret', $controller . '::_secret');
            $routes->get('init', $controller . '::initController');
            $routes->get('hidden', $controller . '::hidden');
            $routes->get('static', $controller . '::staticAction');
            $routes->get('missing', $controller . '::nope');
        }, 'GET', $path);
    }
}
