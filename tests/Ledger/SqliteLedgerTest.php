<?php
declare(strict_types=1);

namespace MailMigrator\Tests\Ledger;

use MailMigrator\Ledger\SqliteLedger;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteLedgerTest extends TestCase
{
    private function ledger(): SqliteLedger
    {
        $pdo = new PDO('sqlite::memory:');
        $l = new SqliteLedger($pdo);
        $l->init();
        return $l;
    }

    private function sampleMessage(int $uid = 1, string $status = 'pending'): array
    {
        return [
            'source_folder' => 'INBOX', 'dest_folder' => 'INBOX', 'source_uid' => $uid,
            'message_id' => "<m{$uid}@x>", 'dedupe_hash' => null, 'size_bytes' => 100,
            'internal_date' => '2020-01-01 00:00:00', 'flags' => ['\\Seen'], 'status' => $status,
        ];
    }

    public function test_record_and_read_status(): void
    {
        $l = $this->ledger();
        $l->recordMessage($this->sampleMessage(1));
        $this->assertSame('pending', $l->status('INBOX', 1));
        $this->assertNull($l->status('INBOX', 999));
    }

    public function test_state_transitions(): void
    {
        $l = $this->ledger();
        $l->recordMessage($this->sampleMessage(1));
        $l->markCopied('INBOX', 1);
        $this->assertSame('copied', $l->status('INBOX', 1));

        $l->recordMessage($this->sampleMessage(2));
        $l->markFailed('INBOX', 2, 'boom');
        $this->assertSame('failed', $l->status('INBOX', 2));
    }

    public function test_record_message_is_idempotent_on_uid(): void
    {
        $l = $this->ledger();
        $l->recordMessage($this->sampleMessage(1));
        $l->markCopied('INBOX', 1);
        // re-recording the same uid must not clobber a copied row back to pending
        $l->recordMessage($this->sampleMessage(1));
        $this->assertSame('copied', $l->status('INBOX', 1));
    }

    public function test_summary_counts_by_status(): void
    {
        $l = $this->ledger();
        $l->recordMessage($this->sampleMessage(1));
        $l->markCopied('INBOX', 1);
        $l->recordMessage($this->sampleMessage(2));
        $l->markSkipped('INBOX', 2);
        $l->recordMessage($this->sampleMessage(3));

        $s = $l->summary();
        $this->assertSame(1, $s['copied']);
        $this->assertSame(1, $s['skipped']);
        $this->assertSame(1, $s['pending']);
        $this->assertSame(0, $s['failed']);
    }

    public function test_folder_uidvalidity_roundtrip(): void
    {
        $l = $this->ledger();
        $this->assertNull($l->getFolderUidValidity('INBOX'));
        $l->recordFolder('INBOX', 'INBOX', 12345);
        $this->assertSame(12345, $l->getFolderUidValidity('INBOX'));
        $l->recordFolder('INBOX', 'INBOX', 67890); // upsert
        $this->assertSame(67890, $l->getFolderUidValidity('INBOX'));
    }
}
