<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Integration;

use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\Config as Database;
use CodeIgniter\Database\SQLite3\Connection;
use CodeIgniter\Exceptions\InvalidArgumentException as CodeIgniterInvalidArgumentException;
use MSSTC4PHP\ValueObject\CodeIgniter4\DataCaster\ValueObjectCast as ModelCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Entity\ValueObjectCast as EntityCast;
use MSSTC4PHP\ValueObject\CodeIgniter4\Internal\ValueObjectColumn;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\Account;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\AccountEntityModel;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\AccountModel;
use MSSTC4PHP\ValueObject\CodeIgniter4\Tests\Support\CodeIgniterApp;
use MSSTC4PHP\ValueObject\Exception\InvalidEmailAddressException;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\IntRange;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A CodeIgniter Model on an in-memory SQLite3 database (the framework's SQLite3 driver needs ext-sqlite3).
 */
#[CoversClass(ModelCast::class)]
#[CoversClass(EntityCast::class)]
#[CoversClass(ValueObjectColumn::class)]
final class ModelRoundTripTest extends TestCase
{
    private Connection $db;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        CodeIgniterApp::boot();
    }

    #[Override]
    protected function setUp(): void
    {
        $db = Database::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '', 'DBDebug' => true], false);
        self::assertInstanceOf(Connection::class, $db);
        $db->query('CREATE TABLE accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, port INTEGER NOT NULL, "range" TEXT NULL)');
        $this->db = $db;
    }

    public function testModelCastsStorePayloadsAndReadValueObjects(): void
    {
        $model = new AccountModel($this->db);
        $id = $model->insert(['email' => EmailAddress::from('a@example.com'), 'port' => Port::from(8080), 'range' => new IntRange(1, 5)]);
        self::assertIsInt($id);
        $model->insert(['email' => EmailAddress::from('b@example.com'), 'port' => Port::from(22), 'range' => null]);

        self::assertSame([
            ['id' => 1, 'email' => 'a@example.com', 'port' => 8080, 'range' => '{"min":1,"max":5}'],
            ['id' => 2, 'email' => 'b@example.com', 'port' => 22, 'range' => null],
        ], $this->rows());

        self::assertEquals(
            ['id' => 1, 'email' => EmailAddress::from('a@example.com'), 'port' => Port::from(8080), 'range' => new IntRange(1, 5)],
            $model->find($id),
        );
        self::assertEquals(
            ['id' => 2, 'email' => EmailAddress::from('b@example.com'), 'port' => Port::from(22), 'range' => null],
            $model->find(2),
        );
    }

    public function testAModelUpdateTakesFormInput(): void
    {
        $model = new AccountModel($this->db);
        $model->insert(['email' => 'a@example.com', 'port' => '80']);
        $model->update(1, ['port' => '443']);

        self::assertSame([['id' => 1, 'email' => 'a@example.com', 'port' => 443, 'range' => null]], $this->rows());
    }

    public function testAnInvalidStoredRowFailsLoudly(): void
    {
        $this->db->query("INSERT INTO accounts (email, port) VALUES ('broken', 80)");

        $this->expectException(InvalidEmailAddressException::class);

        new AccountModel($this->db)->find(1);
    }

    public function testNullForANonNullableModelCastIsRejectedByCodeIgniter(): void
    {
        $this->expectException(CodeIgniterInvalidArgumentException::class);

        new AccountModel($this->db)->insert(['email' => null, 'port' => 80]);
    }

    public function testEntityCastsRoundTripThroughAModelWithoutModelCasts(): void
    {
        $model = new AccountEntityModel($this->db);
        $account = new Account();
        $account->email = EmailAddress::from('a@example.com');
        $account->port = Port::from(8080);
        $account->range = new IntRange(2, 3);

        $model->insert($account);

        self::assertSame([['id' => 1, 'email' => 'a@example.com', 'port' => 8080, 'range' => '{"min":2,"max":3}']], $this->rows());

        $found = $model->find(1);
        self::assertInstanceOf(Account::class, $found);
        self::assertEquals(EmailAddress::from('a@example.com'), $found->email);
        self::assertEquals(Port::from(8080), $found->port);
        self::assertEquals(new IntRange(2, 3), $found->range);

        $found->email = 'c@example.com';
        $model->save($found);

        self::assertSame('c@example.com', $this->rows()[0]['email']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = [];
        $result = $this->db->query('SELECT * FROM accounts ORDER BY id');
        self::assertInstanceOf(BaseResult::class, $result);
        foreach ($result->getResultArray() as $row) {
            self::assertIsArray($row);
            $rows[] = $row;
        }

        return $rows;
    }
}
