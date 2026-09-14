<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Database;
use App\Support\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = dirname(__DIR__, 3) . '/database/migrations';
    }

    public function test_applies_all_and_is_idempotent(): void
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        $m = new Migrator($pdo, $this->dir);

        $first = $m->migrate();
        $this->assertContains('001_create_users.sql', $first);
        $this->assertContains('002_create_jobs.sql', $first);

        // tables exist
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('users', $tables);
        $this->assertContains('jobs', $tables);

        // second run applies nothing
        $second = $m->migrate();
        $this->assertSame([], $second);
    }
}
