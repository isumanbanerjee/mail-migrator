<?php
declare(strict_types=1);

namespace EmailMigration\Tests;

use EmailMigration\FolderMapper;
use EmailMigration\Ledger\SqliteLedger;
use EmailMigration\MessageMigrator;
use EmailMigration\MigrationRunner;
use EmailMigration\Support\Logger;
use EmailMigration\Tests\Fakes\InMemoryReader;
use EmailMigration\Tests\Fakes\InMemoryWriter;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private function header(int $uid, string $mid, string $date = '01-Jan-2020 00:00:00 +0000'): array
    {
        return ['uid' => $uid, 'message_id' => $mid, 'from' => 'a@x', 'subject' => 'S',
                'size' => 3, 'internal_date' => $date, 'flags' => []];
    }

    private function build(InMemoryReader $reader, InMemoryWriter $writer, array $options): array
    {
        $ledger = new SqliteLedger(new PDO('sqlite::memory:'));
        $mapper = new FolderMapper();
        $logger = new Logger('error');
        $migrator = new MessageMigrator($writer, $ledger, $logger);
        $runner = new MigrationRunner($reader, $writer, $ledger, $mapper, $migrator, $logger, $options);
        return [$runner, $ledger];
    }

    public function test_copies_all_messages_across_folders(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1, '<a@x>'), 'R1');
        $reader->addMessage('INBOX', $this->header(2, '<b@x>'), 'R2');
        $reader->addMessage('INBOX.Sent', $this->header(1, '<c@x>'), 'R3');
        $writer = new InMemoryWriter();
        [$runner] = $this->build($reader, $writer, ['throttle_ms' => 0]);

        $summary = $runner->run();

        $this->assertSame(3, $summary['copied']);
        $this->assertSame(2, $summary['folders']);
        $this->assertCount(3, $writer->appended);
        // folders were created on destination with mapped names
        $this->assertContains('INBOX', $writer->created);
        $this->assertContains('[Gmail]/Sent Mail', $writer->created);
    }

    public function test_second_run_is_idempotent(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1, '<a@x>'), 'R1');
        $writer = new InMemoryWriter();
        [$runner, $ledger] = $this->build($reader, $writer, ['throttle_ms' => 0]);

        $runner->run();
        // simulate a fresh runner sharing the same ledger + destination state
        $writer->existing['INBOX'] = ['<a@x>'];
        $mapper = new FolderMapper();
        $logger = new Logger('error');
        $migrator = new MessageMigrator($writer, $ledger, $logger);
        $runner2 = new MigrationRunner($reader, $writer, $ledger, $mapper, $migrator, $logger, ['throttle_ms' => 0]);

        $summary = $runner2->run();
        $this->assertSame(0, $summary['copied']);
        // Incremental resume: the already-processed UID isn't re-read at all (skipped=0),
        // and crucially there is no duplicate append.
        $this->assertSame(0, $summary['skipped']);
        $this->assertCount(1, $writer->appended); // no new appends
    }

    public function test_dry_run_writes_nothing(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1, '<a@x>'), 'R1');
        $writer = new InMemoryWriter();
        [$runner] = $this->build($reader, $writer, ['throttle_ms' => 0, 'dry_run' => true]);

        $summary = $runner->run();

        $this->assertSame(1, $summary['would_copy']);
        $this->assertSame(0, $summary['copied']);
        $this->assertCount(0, $writer->appended);
    }

    public function test_limit_caps_messages_copied(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1, '<a@x>'), 'R1');
        $reader->addMessage('INBOX', $this->header(2, '<b@x>'), 'R2');
        $reader->addMessage('INBOX', $this->header(3, '<c@x>'), 'R3');
        $writer = new InMemoryWriter();
        [$runner] = $this->build($reader, $writer, ['throttle_ms' => 0, 'limit' => 2]);

        $summary = $runner->run();
        $this->assertSame(2, $summary['copied']);
    }

    public function test_since_filters_older_messages(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1, '<old@x>', '01-Jan-2019 00:00:00 +0000'), 'R1');
        $reader->addMessage('INBOX', $this->header(2, '<new@x>', '01-Jan-2021 00:00:00 +0000'), 'R2');
        $writer = new InMemoryWriter();
        [$runner] = $this->build($reader, $writer, ['throttle_ms' => 0, 'since' => '2020-06-01']);

        $summary = $runner->run();
        $this->assertSame(1, $summary['copied']);
        $this->assertSame('R2', $writer->appended[0]['raw']);
    }

    public function test_invalid_since_throws(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1, '<a@x>'), 'R1');
        $writer = new InMemoryWriter();
        [$runner] = $this->build($reader, $writer, ['throttle_ms' => 0, 'since' => 'not-a-real-date']);

        $this->expectException(\InvalidArgumentException::class);
        $runner->run();
    }

    public function test_dry_run_consults_dest_index(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1, '<a@x>'), 'R1');
        $reader->addMessage('INBOX', $this->header(2, '<b@x>'), 'R2');
        $writer = new InMemoryWriter();
        $writer->existing['INBOX'] = ['<a@x>'];
        [$runner] = $this->build($reader, $writer, ['throttle_ms' => 0, 'dry_run' => true]);

        $summary = $runner->run();

        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(1, $summary['would_copy']);
        $this->assertCount(0, $writer->appended);
        // dry-run must not create folders
        $this->assertSame([], $writer->created);
    }

    public function test_only_folder_restricts_scope(): void
    {
        $reader = new InMemoryReader();
        $reader->addMessage('INBOX', $this->header(1, '<a@x>'), 'R1');
        $reader->addMessage('INBOX.Sent', $this->header(1, '<b@x>'), 'R2');
        $writer = new InMemoryWriter();
        [$runner] = $this->build($reader, $writer, ['throttle_ms' => 0, 'only_folder' => 'INBOX']);

        $summary = $runner->run();
        $this->assertSame(1, $summary['copied']);
        $this->assertSame(1, $summary['folders']);
    }
}
