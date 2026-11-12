<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Integration;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\RouteCollection;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValidatedValueObjects;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\ProfileController;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidatedValueObjects::class)]
final class ValidatedValueObjectsTest extends TestCase
{
    public function testAValidatedFieldBecomesTheValueObject(): void
    {
        self::assertSame('email:jane@example.com', $this->post('email', ['email' => 'jane@example.com'])->getBody());
    }

    public function testADotKeyReachesANestedField(): void
    {
        self::assertSame('email:jane@example.com', $this->post('nested', ['profile' => ['email' => 'jane@example.com']])->getBody());
    }

    public function testAnEmptyOrMissingFieldIsNull(): void
    {
        self::assertSame('email:null', $this->post('optional', ['email' => ''])->getBody());
        self::assertSame('email:null', $this->post('optional', [])->getBody());
    }

    public function testACompositeComesFromAnArray(): void
    {
        $response = $this->post('range', ['range' => ['start' => '2026-11-01T00:00:00+00:00', 'end' => '2026-11-30T00:00:00+00:00']]);

        self::assertSame('range:2026-11-01', $response->getBody());
    }

    public function testACompositeComesFromJsonText(): void
    {
        $response = $this->post('range', ['range' => '{"start":"2026-11-01T00:00:00+00:00","end":"2026-11-30T00:00:00+00:00"}']);

        self::assertSame('range:2026-11-01', $response->getBody());
    }

    public function testBeforeAnyValidationItIsALogicError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('validate');

        $this->post('beforeValidation', []);
    }

    public function testAFieldTheValidationDidNotCoverIsNull(): void
    {
        self::assertSame('', $this->post('unvalidatedField', ['email' => 'jane@example.com'])->getBody());
    }

    public function testAValueThatDoesNotConvertIsALogicError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"email"');

        $this->post('unconvertible', ['email' => 'nope']);
    }

    public function testAnArrayWhereAScalarIsExpectedIsALogicError(): void
    {
        $this->expectException(LogicException::class);

        $this->post('unconvertible', ['email' => ['x']]);
    }

    public function testATrustedSourceOnlyClassIsALogicError(): void
    {
        $this->expectException(LogicException::class);

        $this->post('untrusted', ['text' => 'abc']);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function post(string $action, array $post): ResponseInterface
    {
        $controller = '\\' . ProfileController::class;

        return Http::request(static function (RouteCollection $routes) use ($controller, $action): void {
            $routes->post('profile', $controller . '::' . $action);
        }, 'POST', 'profile', $post);
    }
}
