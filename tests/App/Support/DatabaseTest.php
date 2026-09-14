<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function test_makes_sqlite_memory_pdo(): void
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $pdo->exec("INSERT INTO t (v) VALUES ('x')");
        $row = $pdo->query('SELECT v FROM t')->fetch();
        $this->assertSame('x', $row['v']);
    }
}
