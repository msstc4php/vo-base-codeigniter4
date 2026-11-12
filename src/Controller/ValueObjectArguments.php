<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Controller;

use CodeIgniter\Exceptions\PageNotFoundException;
use LogicException;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ArgumentBinder;

/**
 * Route parameters as typed controller arguments: `show(EmailAddress $email)` for `users/(:segment)`.
 *
 * Implements `_remap()`, so it needs explicit routes (Auto Routing (Improved) answers 404 for any controller with a
 * `_remap()`), and a controller with its own `_remap()` cannot use it. Scalar-backed value objects and backed enums
 * are converted; anything else is left to the framework.
 */
trait ValueObjectArguments
{
    /**
     * @throws PageNotFoundException when the method is not routable, or a parameter is not a valid value of its type
     * @throws LogicException when a parameter is typed with a class that cannot be bound from a URL segment
     */
    public function _remap(string $method, string ...$params): mixed
    {
        return ArgumentBinder::call($this, $method, array_values($params));
    }
}
