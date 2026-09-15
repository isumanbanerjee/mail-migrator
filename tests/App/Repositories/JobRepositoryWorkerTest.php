<?php
declare(strict_types=1);

namespace App\Tests\Repositories;

use App\Repositories\JobRepository;
use App\Support\Database;
use App\Support\Migrator;
use App\Tests\TestCase;

final class JobRepositoryWorkerTest extends TestCase
{
    private function repo(): array
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        return [new JobRepository($pdo), $pdo];
    }

    private function data(): array
    {
        return ['name' => 'J', 'mode' => 'live',
            'source_host' => 'h', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'h', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}'];
    }

    public function test_progress_state_and_lock_helpers(): void
    {
        [$repo] = $this->repo();
        $id = $repo->create(1, $this->data());

        $repo->saveProgress($id, ['total_messages' => 100, 'copied' => 40, 'skipped' => 1, 'failed' => 0, 'current_folder' => 'INBOX', 'percent' => 41]);
        $row = $repo->find($id, 1);
        $this->assertSame(100, (int) $row['total_messages']);
        $this->assertSame(41, (int) $row['percent']);
        $this->assertSame('INBOX', $row['current_folder']);

        $repo->systemTransition($id, 'running');
        $this->assertSame('running', $repo->currentState($id));
        $repo->systemTransition($id, 'failed', 'nope');
        $this->assertSame('failed', $repo->currentState($id));
        $this->assertSame('nope', $repo->find($id, 1)['last_error']);
    }

    public function test_reset_ledger_message_and_mark_queued(): void
    {
        [$repo, $pdo] = $this->repo();
        $id = $repo->create(1, $this->data());
        $repo->systemTransition($id, 'failed', 'boom');

        $now = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO job_ledger_messages
            (job_id, source_folder, dest_folder, source_uid, status, attempts, error, created_at, updated_at)
            VALUES (:j,:sf,:df,:uid,:st,:a,:er,:ca,:ua)')
            ->execute([':j' => $id, ':sf' => 'INBOX', ':df' => 'INBOX', ':uid' => 7, ':st' => 'failed',
                ':a' => 2, ':er' => 'append failed', ':ca' => $now, ':ua' => $now]);

        $this->assertTrue($repo->resetLedgerMessage($id, 'INBOX', 7));
        $row = $pdo->query("SELECT status, error FROM job_ledger_messages WHERE job_id={$id} AND source_uid=7")->fetch();
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['error']);

        // markQueued clears state + error + lock so the worker can pick it up
        $this->assertTrue($repo->markQueued($id, 1));
        $job = $repo->find($id, 1);
        $this->assertSame('queued', $job['state']);
        $this->assertNull($job['last_error']);
    }

    public function test_claim_helpers_respect_state_and_stale(): void
    {
        [$repo, $pdo] = $this->repo();
        $future = date('Y-m-d H:i:s', time() - 900);
        $q = $repo->create(1, $this->data());
        $repo->systemTransition($q, 'queued');

        $next = $repo->nextClaimable($future);
        $this->assertSame($q, (int) $next['id']);

        // mark running with fresh lock → not claimable, counts as running
        $repo->systemTransition($q, 'running');
        $pdo->prepare('UPDATE jobs SET locked_at=:t WHERE id=:id')->execute([':t' => date('Y-m-d H:i:s'), ':id' => $q]);
        $this->assertNull($repo->nextClaimable($future));
        $this->assertSame(1, $repo->countRunning($future));

        // stale lock → claimable again
        $pdo->prepare('UPDATE jobs SET locked_at=:t WHERE id=:id')->execute([':t' => date('Y-m-d H:i:s', time() - 1000), ':id' => $q]);
        $this->assertSame($q, (int) $repo->nextClaimable($future)['id']);
        $this->assertSame(0, $repo->countRunning($future));
    }
}
