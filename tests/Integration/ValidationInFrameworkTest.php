<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Integration;

use CodeIgniter\Config\Services;
use CodeIgniter\Validation\Validation;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeIgniterApp;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\ValidationConfig;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Validation runs that make CodeIgniter load its language files. The default message lives in src/Language/en/Validation.php, which CodeIgniter finds only through
 * Composer namespace discovery (Config\Modules::$discoverInComposer). A plain file is no coverage
 * target, hence CoversNothing.
 */
#[CoversNothing]
final class ValidationInFrameworkTest extends TestCase
{
    #[Override]
    public static function setUpBeforeClass(): void
    {
        CodeIgniterApp::boot();
    }

    public function testAFailingFieldGetsThePackagesMessage(): void
    {
        $validation = new Validation(new ValidationConfig(), Services::renderer());
        $validation->setRules(['email' => 'required|valid_value_object[' . EmailAddress::class . ']']);

        self::assertFalse($validation->run(['email' => 'nope']));
        self::assertSame(['email' => 'The email field does not contain a valid value.'], $validation->getErrors());
    }

    public function testRequiredIsASeparateRule(): void
    {
        $validation = new Validation(new ValidationConfig(), Services::renderer());
        $validation->setRules([
            'optional' => 'valid_value_object[' . EmailAddress::class . ']',
            'mandatory' => 'required|valid_value_object[' . EmailAddress::class . ']',
        ]);

        self::assertFalse($validation->run(['optional' => '', 'mandatory' => '']));
        self::assertSame(['mandatory'], array_keys($validation->getErrors()));
    }
}
