<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Repositories\JobRepository;
use App\Services\JobRunner;
use App\Support\Database;
use App\Support\Migrator;
use App\Support\MysqlLedger;
use App\Tests\Fakes\FakeMailboxFactory;
use App\Tests\TestCase;
use EmailMigration\Tests\Fakes\InMemoryReader;
use EmailMigration\Tests\Fakes\InMemoryWriter;

final class JobRunnerTest extends TestCase
{
    private function ctx(): array
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        $jobs = new JobRepository($pdo);
        return [$pdo, $jobs];
    }

    private function job(JobRepository $jobs, string $mode = 'live'): array
    {
        $id = $jobs->create(1, ['name' => 'J', 'mode' => $mode,
            'source_host' => 'imap.src', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'imap.dst', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}']);
        $jobs->systemTransition($id, 'running');
        return $jobs->find($id, 1);
    }

    private function reader(): InMemoryReader
    {
        $r = new InMemoryReader();
        $h = fn(int $u, string $m) => ['uid' => $u, 'message_id' => $m, 'from' => 'a@x', 'subject' => 'S', 'size' => 3, 'internal_date' => '01-Jan-2020 00:00:00 +0000', 'flags' => []];
        $r->addMessage('INBOX', $h(1, '<a@x>'), 'R1');
        $r->addMessage('INBOX', $h(2, '<b@x>'), 'R2');
        return $r;
    }

    public function test_runs_to_completion_and_writes_progress(): void
    {
        [$pdo, $jobs] = $this->ctx();
        $job = $this->job($jobs);
        $writer = new InMemoryWriter();
        $factory = new FakeMailboxFactory($this->reader(), $writer);
        $runner = new JobRunner($factory, $jobs, fn(int $id) => new MysqlLedger($pdo, $id), 50);

        $state = $runner->run($job);
        $this->assertSame('completed', $state);
        $this->assertSame('completed', $jobs->currentState((int) $job['id']));
        $this->assertSame(2, (int) $jobs->find((int) $job['id'], 1)['copied']);
        $this->assertCount(2, $writer->appended);
    }

    public function test_pause_stops_cleanly_and_preserves_progress(): void
    {
        [$pdo, $jobs] = $this->ctx();
        $job = $this->job($jobs);
        $writer = new InMemoryWriter();
        $factory = new FakeMailboxFactory($this->reader(), $writer);
        // clock steady; simulate a pause by flipping state after the first progress tick via a writer hook:
        // easiest: pre-set state to 'paused' so the first progress check stops immediately after msg 1.
        $jobs->systemTransition((int) $job['id'], 'paused');
        $runner = new JobRunner($factory, $jobs, fn(int $id) => new MysqlLedger($pdo, $id), 50);

        $state = $runner->run($job);
        $this->assertSame('paused', $state);
        // at least the first message was copied and recorded before stopping
        $this->assertGreaterThanOrEqual(1, (int) $jobs->find((int) $job['id'], 1)['copied']);
        $this->assertSame('paused', $jobs->currentState((int) $job['id']));
    }

    public function test_time_budget_requeues(): void
    {
        [$pdo, $jobs] = $this->ctx();
        $job = $this->job($jobs);
        $writer = new InMemoryWriter();
        $factory = new FakeMailboxFactory($this->reader(), $writer);
        $t = 1000; $clock = function () use (&$t) { $t += 100; return $t; }; // each call jumps 100s
        $runner = new JobRunner($factory, $jobs, fn(int $id) => new MysqlLedger($pdo, $id), 50, $clock);

        $state = $runner->run($job);
        $this->assertSame('queued', $state); // budget exceeded → back to queued for next tick
        $this->assertSame('queued', $jobs->currentState((int) $job['id']));
        $this->assertNull($jobs->find((int) $job['id'], 1)['locked_at']); // lock released
    }

    public function test_multi_tick_resume_reports_cumulative_progress(): void
    {
        [$pdo, $jobs] = $this->ctx();
        $job = $this->job($jobs);
        $writer = new InMemoryWriter();
        $factory = new FakeMailboxFactory($this->reader(), $writer);
        $ledgerFor = fn (int $id) => new MysqlLedger($pdo, $id);

        // Tick 1: force a time-budget stop right after the first message.
        $t = 1000;
        $stopAfterFirst = function () use (&$t) { $t += 100; return $t; };
        $runner1 = new JobRunner($factory, $jobs, $ledgerFor, 50, $stopAfterFirst);
        $state1 = $runner1->run($job);
        $this->assertSame('queued', $state1);
        $row1 = $jobs->find((int) $job['id'], 1);
        $this->assertSame(1, (int) $row1['copied']);

        // Tick 2: re-fetch the job row, run again with a normal clock to completion.
        $jobs->systemTransition((int) $job['id'], 'running');
        $job2 = $jobs->find((int) $job['id'], 1);
        $runner2 = new JobRunner($factory, $jobs, $ledgerFor, 50);
        $state2 = $runner2->run($job2);

        $this->assertSame('completed', $state2);
        $this->assertSame('completed', $jobs->currentState((int) $job['id']));
        $row2 = $jobs->find((int) $job['id'], 1);
        $this->assertSame(2, (int) $row2['copied'] + (int) $row2['skipped']);
        $this->assertSame(100, (int) $row2['percent']);
    }

    public function test_mailbox_connection_failure_marks_job_failed_and_releases_lock(): void
    {
        [$pdo, $jobs] = $this->ctx();
        $job = $this->job($jobs);
        $factory = new class implements \App\Services\MailboxFactoryInterface {
            public function forJob(array $job): array
            {
                throw new \RuntimeException('Connection failed: could not connect to imap.src');
            }
        };
        $runner = new JobRunner($factory, $jobs, fn(int $id) => new MysqlLedger($pdo, $id), 50);

        $state = $runner->run($job);
        $this->assertSame('failed', $state);
        $row = $jobs->find((int) $job['id'], 1);
        $this->assertSame('failed', $row['state']);
        $this->assertNotEmpty($row['last_error']);
        $this->assertNull($row['worker_id']);
        $this->assertNull($row['locked_at']);
    }
}
