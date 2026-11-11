<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support;

use CodeIgniter\Boot;
use RuntimeException;

/**
 * Boots just enough of CodeIgniter for services, config(), lang() and the database: the framework's
 * own app skeleton under vendor/, without the exception handler (it would replace PHPUnit's error
 * handler) and without .env.
 */
final class CodeIgniterApp extends Boot
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        $framework = \dirname(__DIR__, 2) . '/vendor/codeigniter4/framework';
        $writable = sys_get_temp_dir() . '/vo-base-codeigniter4-writable';
        if (!is_dir($writable) && !mkdir($writable, 0o777, true) && !is_dir($writable)) {
            throw new RuntimeException('Cannot create ' . $writable);
        }

        $_SERVER['CI_ENVIRONMENT'] = 'testing';
        \defined('ENVIRONMENT') || \define('ENVIRONMENT', 'testing');
        \defined('CI_DEBUG') || \define('CI_DEBUG', true);
        \defined('WRITEPATH') || \define('WRITEPATH', $writable . \DIRECTORY_SEPARATOR);
        \defined('HOMEPATH') || \define('HOMEPATH', \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR);
        \defined('COMPOSER_PATH') || \define('COMPOSER_PATH', HOMEPATH . 'vendor/autoload.php');
        \defined('TESTPATH') || \define('TESTPATH', $framework . '/tests' . \DIRECTORY_SEPARATOR);
        \defined('SUPPORTPATH') || \define('SUPPORTPATH', TESTPATH . '_support' . \DIRECTORY_SEPARATOR);
        \defined('FCPATH') || \define('FCPATH', $framework . '/public' . \DIRECTORY_SEPARATOR);

        \defined('APPPATH') || \define('APPPATH', $framework . '/app' . \DIRECTORY_SEPARATOR);
        \defined('ROOTPATH') || \define('ROOTPATH', $framework . \DIRECTORY_SEPARATOR);
        \defined('SYSTEMPATH') || \define('SYSTEMPATH', $framework . '/system' . \DIRECTORY_SEPARATOR);

        self::loadConstants();
        self::loadCommonFunctions();
        self::loadAutoloader();
        self::autoloadHelpers();
    }
}
