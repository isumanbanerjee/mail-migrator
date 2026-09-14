<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Repositories\JobRepository;
use App\Services\JobClaimer;
use App\Support\Database;
use App\Support\Migrator;
use App\Tests\TestCase;

final class JobClaimerTest extends TestCase
{
    private function setup2(): array
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        $jobs = new JobRepository($pdo);
        return [$jobs, $pdo];
    }

    private function queued(JobRepository $jobs): int
    {
        $id = $jobs->create(1, ['name' => 'J', 'mode' => 'live',
            'source_host' => 'h', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'h', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}']);
        $jobs->systemTransition($id, 'queued');
        return $id;
    }

    public function test_claims_one_and_marks_running(): void
    {
        [$jobs] = $this->setup2();
        $id = $this->queued($jobs);
        $claimer = new JobClaimer($this->pdoOf($jobs), $jobs, 1, 900);
        $claimed = $claimer->claim('w1');
        $this->assertSame($id, (int) $claimed['id']);
        $this->assertSame('running', $jobs->currentState($id));
    }

    public function test_respects_concurrency_cap(): void
    {
        [$jobs, $pdo] = $this->setup2();
        $a = $this->queued($jobs); $b = $this->queued($jobs);
        $claimer = new JobClaimer($pdo, $jobs, 1, 900);
        $first = $claimer->claim('w1');
        $this->assertNotNull($first);
        $second = $claimer->claim('w2'); // cap=1, one already running
        $this->assertNull($second);
    }

    public function test_returns_null_when_none_queued(): void
    {
        [$jobs, $pdo] = $this->setup2();
        $claimer = new JobClaimer($pdo, $jobs, 1, 900);
        $this->assertNull($claimer->claim('w1'));
    }

    private function pdoOf(JobRepository $jobs): \PDO
    {
        $r = new \ReflectionProperty($jobs, 'pdo');
        return $r->getValue($jobs);
    }
}
