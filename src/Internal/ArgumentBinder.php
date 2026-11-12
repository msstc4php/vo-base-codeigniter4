<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Internal;

use BackedEnum;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Calls a controller method with its route parameters converted to the value objects and enums the method declares.
 *
 * @internal
 */
final class ArgumentBinder
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $params
     *
     * @throws PageNotFoundException when the method is not routable, or a parameter is not a valid value of its type
     * @throws LogicException when a parameter is typed with a class that cannot be bound from a URL segment
     */
    public static function call(Controller $controller, string $method, array $params): mixed
    {
        $reflection = self::routable($controller, $method);

        $arguments = [];
        foreach ($reflection->getParameters() as $position => $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $class = self::boundClass($parameter);
            if (\array_key_exists($position, $params)) {
                $arguments[] = $class === null ? $params[$position] : self::convert($params[$position], $class, $parameter);

                continue;
            }

            if ($class === null || $parameter->isDefaultValueAvailable() || !$parameter->allowsNull()) {
                break;
            }

            $arguments[] = null;
        }

        return $reflection->invokeArgs($controller, [...$arguments, ...\array_slice($params, \count($arguments))]);
    }

    private static function routable(Controller $controller, string $method): ReflectionMethod
    {
        if (!method_exists($controller, $method)) {
            throw PageNotFoundException::forMethodNotFound($method);
        }

        $reflection = new ReflectionMethod($controller, $method);
        if (
            !$reflection->isPublic()
            || $reflection->isStatic()
            || str_starts_with($reflection->getName(), '_')
            || method_exists(Controller::class, $method)
        ) {
            throw PageNotFoundException::forMethodNotFound($method);
        }

        return $reflection;
    }

    /**
     * @return class-string<ValueObject|BackedEnum>|null null for a parameter this binder leaves alone
     *
     * @throws LogicException when the class is supported but cannot be bound
     */
    private static function boundClass(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $class = $type->getName();
        $mapper = ValueObjectColumn::mapper();
        if (!$mapper->supports($class)) {
            return null;
        }

        if (!$mapper->supportsUntrusted($class)) {
            throw new LogicException(\sprintf('Parameter $%s of %s::%s() cannot be bound from the route: its payload carries its own constraints, so a URL segment would choose them.', $parameter->getName(), self::declaringClass($parameter), $parameter->getDeclaringFunction()->getName()));
        }

        if ($mapper->isComposite($class)) {
            throw new LogicException(\sprintf('Parameter $%s of %s::%s() cannot be bound from the route: a URL segment is a string, but this class is built from several values.', $parameter->getName(), self::declaringClass($parameter), $parameter->getDeclaringFunction()->getName()));
        }

        \assert(is_a($class, ValueObject::class, true) || is_a($class, BackedEnum::class, true));

        return $class;
    }

    /**
     * @param class-string<ValueObject|BackedEnum> $class
     */
    private static function convert(string $segment, string $class, ReflectionParameter $parameter): ValueObject|BackedEnum|null
    {
        if ($segment === '') {
            return $parameter->allowsNull() ? null : throw PageNotFoundException::forPageNotFound();
        }

        try {
            return ValueObjectColumn::mapper()->fromUntrustedPayload($class, $segment);
        } catch (InvalidValueObjectException) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    private static function declaringClass(ReflectionParameter $parameter): string
    {
        return $parameter->getDeclaringClass()?->getName() ?? '';
    }
}
