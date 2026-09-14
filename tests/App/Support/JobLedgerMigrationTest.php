<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Database;
use App\Support\Migrator;
use App\Tests\TestCase;

final class JobLedgerMigrationTest extends TestCase
{
    public function test_job_ledger_tables_created(): void
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('job_ledger_messages', $tables);
        $this->assertContains('job_ledger_folders', $tables);
    }
}
