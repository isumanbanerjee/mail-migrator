<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Database;
use App\Support\Migrator;
use App\Support\MysqlLedger;
use App\Tests\TestCase;

final class MysqlLedgerTest extends TestCase
{
    private function pdo(): \PDO
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        return $pdo;
    }

    private function msg(int $uid, string $status = 'pending'): array
    {
        return ['source_folder' => 'INBOX', 'dest_folder' => 'INBOX', 'source_uid' => $uid,
            'message_id' => "<m{$uid}@x>", 'dedupe_hash' => null, 'size_bytes' => 10,
            'internal_date' => '2020-01-01 00:00:00', 'flags' => ['\\Seen'], 'status' => $status];
    }

    public function test_scoped_by_job_and_state_transitions(): void
    {
        $pdo = $this->pdo();
        $a = new MysqlLedger($pdo, 1);
        $b = new MysqlLedger($pdo, 2);
        $a->init(); $b->init();

        $a->recordMessage($this->msg(1));
        $this->assertSame('pending', $a->status('INBOX', 1));
        $this->assertNull($b->status('INBOX', 1)); // other job can't see it

        $a->markCopied('INBOX', 1);
        $this->assertSame('copied', $a->status('INBOX', 1));

        // idempotent re-record must not reset copied
        $a->recordMessage($this->msg(1));
        $this->assertSame('copied', $a->status('INBOX', 1));

        $a->recordMessage($this->msg(2)); $a->markFailed('INBOX', 2, 'boom');
        $s = $a->summary();
        $this->assertSame(1, $s['copied']); $this->assertSame(1, $s['failed']);
        $this->assertSame(0, $b->summary()['copied']);
    }

    public function test_folder_uidvalidity_scoped(): void
    {
        $pdo = $this->pdo();
        $a = new MysqlLedger($pdo, 1);
        $this->assertNull($a->getFolderUidValidity('INBOX'));
        $a->recordFolder('INBOX', 'INBOX', 111);
        $this->assertSame(111, $a->getFolderUidValidity('INBOX'));
        $this->assertNull((new MysqlLedger($pdo, 2))->getFolderUidValidity('INBOX'));
    }
}
