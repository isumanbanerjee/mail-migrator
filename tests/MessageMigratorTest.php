<?php
declare(strict_types=1);

namespace MailMigrator\Tests;

use MailMigrator\Ledger\SqliteLedger;
use MailMigrator\MessageMigrator;
use MailMigrator\Support\Logger;
use MailMigrator\Tests\Fakes\InMemoryReader;
use MailMigrator\Tests\Fakes\InMemoryWriter;
use PDO;
use PHPUnit\Framework\TestCase;

final class MessageMigratorTest extends TestCase
{
    private function ledger(): SqliteLedger
    {
        $l = new SqliteLedger(new PDO('sqlite::memory:'));
        $l->init();
        return $l;
    }

    private function header(int $uid = 1, string $mid = '<m1@x>'): array
    {
        return [
            'uid' => $uid, 'message_id' => $mid, 'from' => 'a@x', 'subject' => 'Hi',
            'size' => 3, 'internal_date' => '01-Jan-2020 00:00:00 +0000', 'flags' => ['\\Seen'],
        ];
    }

    public function test_copies_new_message(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1), 'RAW');
        $writer = new InMemoryWriter();
        $ledger = $this->ledger();
        $m = new MessageMigrator($writer, $ledger, new Logger('error'));

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $this->header(1), [], false);

        $this->assertSame('copied', $action);
        $this->assertCount(1, $writer->appended);
        $this->assertSame('RAW', $writer->appended[0]['raw']);
        $this->assertSame(['\\Seen'], $writer->appended[0]['flags']);
        $this->assertSame('copied', $ledger->status('INBOX', 1));
    }

    public function test_fetch_failed_header_is_recorded_failed_and_advances(): void
    {
        $reader = new InMemoryReader();
        $writer = new InMemoryWriter();
        $ledger = $this->ledger();
        $m = new MessageMigrator($writer, $ledger, new Logger('error'));

        $header = ['uid' => 99, 'message_id' => '', 'subject' => '', 'from' => '',
            'size' => 0, 'internal_date' => '', 'flags' => [], 'raw_header' => '', 'fetch_failed' => true];

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $header, [], false);

        $this->assertSame('failed', $action);
        $this->assertCount(0, $writer->appended);
        $this->assertSame('failed', $ledger->status('INBOX', 99)); // recorded, so scan advances past it
    }

    public function test_oversized_message_is_failed_not_appended(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1), str_repeat('x', 2048));
        $writer = new InMemoryWriter();
        $ledger = $this->ledger();
        $m = new MessageMigrator($writer, $ledger, new Logger('error'), 1024); // 1KB cap

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $this->header(1), [], false);

        $this->assertSame('failed', $action);
        $this->assertCount(0, $writer->appended);
        $this->assertSame('failed', $ledger->status('INBOX', 1));
    }

    public function test_uses_raw_header_plus_body_when_available(): void
    {
        // No full raw is set, only a raw_header on the header + a body-only fetch,
        // so a copy proves migrateOne used the header+body fast path (not fetchRaw).
        $reader = new InMemoryReader();
        $reader->body['INBOX:1'] = "Line one\r\nLine two";
        $writer = new InMemoryWriter();
        $ledger = $this->ledger();
        $m = new MessageMigrator($writer, $ledger, new Logger('error'));

        $header = $this->header(1);
        $header['raw_header'] = "From: a@x\r\nSubject: Hi";

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $header, [], false);

        $this->assertSame('copied', $action);
        $this->assertSame("From: a@x\r\nSubject: Hi\r\n\r\nLine one\r\nLine two", $writer->appended[0]['raw']);
    }

    public function test_falls_back_to_fetch_raw_when_body_fetch_empty(): void
    {
        // raw_header present but no body-only available -> fall back to full fetchRaw.
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1), 'FULLRAW');
        $writer = new InMemoryWriter();
        $ledger = $this->ledger();
        $m = new MessageMigrator($writer, $ledger, new Logger('error'));

        $header = $this->header(1);
        $header['raw_header'] = "From: a@x";

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $header, [], false);

        $this->assertSame('copied', $action);
        $this->assertSame('FULLRAW', $writer->appended[0]['raw']);
    }

    public function test_skips_when_message_id_present_on_destination(): void
    {
        $reader = new InMemoryReader();
        $writer = new InMemoryWriter();
        $ledger = $this->ledger();
        $m = new MessageMigrator($writer, $ledger, new Logger('error'));

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $this->header(1, '<dup@x>'), ['<dup@x>'], false);

        $this->assertSame('skipped', $action);
        $this->assertCount(0, $writer->appended);
        $this->assertSame('skipped', $ledger->status('INBOX', 1));
    }

    public function test_skips_when_already_copied_in_ledger(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1), 'RAW');
        $writer = new InMemoryWriter();
        $ledger = $this->ledger();
        $ledger->recordMessage([
            'source_folder' => 'INBOX', 'dest_folder' => 'INBOX', 'source_uid' => 1,
            'message_id' => '<m1@x>', 'dedupe_hash' => null, 'size_bytes' => 3,
            'internal_date' => 'd', 'flags' => [], 'status' => 'pending',
        ]);
        $ledger->markCopied('INBOX', 1);
        $m = new MessageMigrator($writer, $ledger, new Logger('error'));

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $this->header(1), [], false);

        $this->assertSame('skipped', $action);
        $this->assertCount(0, $writer->appended);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1), 'RAW');
        $writer = new InMemoryWriter();
        $ledger = $this->ledger();
        $m = new MessageMigrator($writer, $ledger, new Logger('error'));

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $this->header(1), [], true);

        $this->assertSame('would_copy', $action);
        $this->assertCount(0, $writer->appended);
        $this->assertSame('pending', $ledger->status('INBOX', 1));
    }

    public function test_append_failure_marks_failed(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1), 'RAW');
        $writer = new InMemoryWriter();
        $writer->failAppend = true;
        $ledger = $this->ledger();
        $m = new MessageMigrator($writer, $ledger, new Logger('error'));

        $action = $m->migrateOne($reader, 'INBOX', 'INBOX', $this->header(1), [], false);

        $this->assertSame('failed', $action);
        $this->assertSame('failed', $ledger->status('INBOX', 1));
    }
}
