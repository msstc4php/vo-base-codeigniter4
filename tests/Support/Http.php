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
use Config\App;

/**
 * One in-process request through CodeIgniter's router and controller runner, with explicit routes only.
 * The 404 override echoes the exception message, so a 404 comes back as a response instead of a thrown exception.
 */
final class Http
{
    /**
     * @param callable(RouteCollection): void $routes
     * @param array<string, mixed>|null $post
     */
    public static function request(callable $routes, string $method, string $path, ?array $post = null): ResponseInterface
    {
        CodeIgniterApp::boot();

        // Reason: Config\App lives in the framework's app skeleton, which PHPStan does not scan. '@' is not a permitted URI character by default.
        $config = new App(); // @phpstan-ignore class.notFound
        $config->permittedURIChars = 'a-z 0-9~%.:_\-@'; // @phpstan-ignore class.notFound, class.notFound
        Factories::injectMock('config', 'Config\App', $config);
        $superglobals = Services::superglobals();
        $superglobals->setServer('REQUEST_METHOD', $method);
        $superglobals->setServer('REQUEST_URI', '/' . $path);
        $superglobals->setServer('SCRIPT_NAME', '/index.php');

        $uri = new SiteURI($config, $path);
        Services::injectMock('uri', $uri);
        $request = new IncomingRequest($config, $uri, 'php://input', new UserAgent());
        $request->setMethod($method);
        if ($post !== null) {
            $request->setGlobal('post', $post);
        }

        Services::injectMock('request', $request);
        Services::injectMock('validation', Services::validation(null, false));

        $collection = Services::routes();
        $collection->resetRoutes();
        $collection->setDefaultNamespace('');
        $collection->setAutoRoute(false);
        $collection->set404Override(static function (string $message): void {
            echo $message;
        });
        $routes($collection);
        $collection->setHTTPVerb($method);

        $app = new CodeIgniter($config);
        $app->initialize();

        $response = $app->setContext('web')->setRequest($request)->run($collection, true);
        \assert($response instanceof ResponseInterface);

        return $response;
    }
}
