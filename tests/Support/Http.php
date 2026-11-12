<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use CodeIgniter\CodeIgniter;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Validation\Validation;
use Config\App;
use ReflectionProperty;

/**
 * One in-process request through CodeIgniter's router and controller runner, with explicit routes only and the package's rule set registered.
 * The 404 override echoes the exception message, so a 404 comes back as a response instead of a thrown exception.
 */
/**
 * @phpstan-type PostShape array<string, string|array<array-key, string|array<array-key, string>>>
 */
final class Http
{
    /**
     * @param callable(RouteCollection): void $routes
     * @param PostShape|null $post
     */
    public static function request(callable $routes, string $method, string $path, ?array $post = null): ResponseInterface
    {
        CodeIgniterApp::boot();

        // Reason: Config\App lives in the framework's app skeleton, which PHPStan does not scan. '@' is not a permitted URI character by default.
        $config = new App(); // @phpstan-ignore class.notFound
        new ReflectionProperty($config, 'permittedURIChars')->setValue($config, 'a-z 0-9~%.:_\-@');
        Factories::injectMock('config', 'Config\App', $config);
        $superglobals = Services::superglobals();
        $superglobals->setServer('REQUEST_METHOD', $method);
        $superglobals->setServer('REQUEST_URI', '/' . $path);
        $superglobals->setServer('SCRIPT_NAME', '/index.php');

        $uri = new SiteURI($config, $path);
        Services::injectMock('uri', $uri);
        $request = new IncomingRequest($config, $uri, 'php://input', new UserAgent());
        $request->setMethod($method);
        $request->setGlobal('post', $post ?? []);
        $request->setGlobal('request', $post ?? []);

        Services::injectMock('request', $request);
        Services::injectMock('validation', new Validation(new ValidationConfig(), Services::renderer()));

        $collection = Services::routes();
        $collection->resetRoutes();
        $collection->setDefaultNamespace('');
        $collection->setAutoRoute(false);
        $collection->set404Override(static function (string $message): void {
            echo $message;
        });
        $routes($collection);
        $collection->setHTTPVerb($method);

        Services::resetSingle('router');
        Services::resetSingle('filters');

        $app = new CodeIgniter($config);
        $app->initialize();

        $response = $app->setContext('web')->setRequest($request)->run($collection, true);
        \assert($response instanceof ResponseInterface);

        return $response;
    }
}
