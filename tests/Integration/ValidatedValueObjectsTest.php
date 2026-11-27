<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Integration;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\RouteCollection;
use JsonException;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValidatedValueObjects;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValidatedData;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller\ProfileController;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Http;
use MSSTC4PHP\ValueObject\CodeIgniter4\Validation\ValueObjectRules;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Temporal\DateRange;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @phpstan-import-type PostShape from Http
 */
#[CoversTrait(ValidatedValueObjects::class)]
#[UsesClass(ValidatedData::class)]
#[UsesClass(ValueObjectColumn::class)]
#[UsesClass(ValueObjectRules::class)]
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
        $exception = $this->logicError('beforeValidation', []);

        self::assertSame('No validation ran: call $this->validate() or validateData() before reading "email".', $exception->getMessage());
        self::assertNull($exception->getPrevious());
    }

    public function testAFieldTheValidationDidNotCoverIsNull(): void
    {
        self::assertSame('', $this->post('unvalidatedField', ['email' => 'jane@example.com'])->getBody());
    }

    public function testAValueThatDoesNotConvertIsALogicErrorThatCarriesNoPartOfTheValueWhileTheCauseQuotesIt(): void
    {
        $exception = $this->logicError('unconvertible', ['email' => 'SeCrEt-7f3a']);

        self::assertSame('The validated field "email" does not convert to the requested class: validate it with valid_value_object[<class>].', $exception->getMessage());
        $previous = $exception->getPrevious();
        self::assertInstanceOf(InvalidValueObjectException::class, $previous);
        self::assertStringContainsString('SeCrEt-7f3a', $previous->getMessage());
    }

    public function testAnArrayWhereAScalarIsExpectedIsALogicError(): void
    {
        $this->expectException(LogicException::class);

        $this->post('unconvertible', ['email' => ['x']]);
    }

    public function testCompositeJsonTextThatDoesNotParseHasTheBridgesPayloadErrorAsPrevious(): void
    {
        $exception = $this->logicError('uncheckedRange', ['range' => '{"start":"SeCrEt-7f3a"']);

        self::assertSame('The validated field "range" does not convert to the requested class: validate it with valid_value_object[<class>].', $exception->getMessage());
        $previous = $exception->getPrevious();
        self::assertInstanceOf(InvalidPayloadException::class, $previous);
        self::assertSame('A ' . DateRange::class . ' payload must be JSON text: Syntax error', $previous->getMessage());
        self::assertInstanceOf(JsonException::class, $previous->getPrevious());
    }

    public function testATrustedSourceOnlyClassIsALogicError(): void
    {
        $exception = $this->logicError('untrusted', ['text' => 'abc']);

        self::assertSame(BoundedString::class . ' cannot be built from input: it is not supported, or its payload carries its own constraints (BoundedString: the input would choose its bounds).', $exception->getMessage());
        self::assertNull($exception->getPrevious());
    }

    public function testAWildcardFieldIsALogicError(): void
    {
        $exception = $this->logicError('wildcard', ['emails' => ['a@b.co']]);

        self::assertSame('The field "emails.*" contains a wildcard: read one concrete field at a time.', $exception->getMessage());
        self::assertNull($exception->getPrevious());
    }

    public function testTheTraitHoldsOnlyItsDocumentedMethod(): void
    {
        $trait = new ReflectionClass(ValidatedValueObjects::class);

        self::assertSame(['validatedValueObject'], array_map(static fn (ReflectionMethod $method): string => $method->getName(), $trait->getMethods()));
        self::assertSame([], $trait->getProperties());
        self::assertSame([], $trait->getReflectionConstants());
    }

    /**
     * @param PostShape $post
     */
    private function logicError(string $action, array $post): LogicException
    {
        try {
            $this->post($action, $post);
        } catch (LogicException $e) {
            self::assertSame(LogicException::class, $e::class);

            return $e;
        }

        self::fail('A LogicException was expected.');
    }

    /**
     * @param PostShape $post
     */
    private function post(string $action, array $post): ResponseInterface
    {
        $controller = '\\' . ProfileController::class;

        return Http::request(static function (RouteCollection $routes) use ($controller, $action): void {
            $routes->post('profile', $controller . '::' . $action);
        }, 'POST', 'profile', $post);
    }
}
