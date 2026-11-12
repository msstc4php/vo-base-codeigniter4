<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Controller;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use MSSTC4PHP\ValueObject\CodeIgniter4\Controller\ValueObjectArguments;
use Override;
use Psr\Log\LoggerInterface;

final class OverridingController extends Controller
{
    use ValueObjectArguments;

    #[Override]
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
    }
}
