<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Repositories\JobRepository;
use App\Services\MeteringService;
use App\Support\Database;
use App\Support\Migrator;
use App\Support\MysqlLedger;
use App\Tests\TestCase;

final class MeteringServiceTest extends TestCase
{
    public function test_counts_jobs_and_copied_emails_per_user(): void
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        $jobs = new JobRepository($pdo);
        $data = fn(string $n) => ['name' => $n, 'mode' => 'live',
            'source_host' => 'h', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'h', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}'];
        $j1 = $jobs->create(1, $data('A'));
        $j2 = $jobs->create(1, $data('B'));
        $jobs->create(2, $data('C')); // other user

        // record 2 copied for job1, 1 copied for job2 (user 1) => 3 emails
        $l1 = new MysqlLedger($pdo, $j1);
        foreach ([1, 2] as $u) { $l1->recordMessage(['source_folder' => 'INBOX', 'dest_folder' => 'INBOX', 'source_uid' => $u, 'message_id' => "<$u>", 'size_bytes' => 1, 'internal_date' => 'd', 'flags' => [], 'status' => 'pending']); $l1->markCopied('INBOX', $u); }
        $l2 = new MysqlLedger($pdo, $j2);
        $l2->recordMessage(['source_folder' => 'INBOX', 'dest_folder' => 'INBOX', 'source_uid' => 1, 'message_id' => '<z>', 'size_bytes' => 1, 'internal_date' => 'd', 'flags' => [], 'status' => 'pending']);
        $l2->markCopied('INBOX', 1);

        $m = new MeteringService($pdo);
        $this->assertSame(2, $m->jobsUsed(1));
        $this->assertSame(1, $m->jobsUsed(2));
        $this->assertSame(3, $m->emailsUsed(1));
        $this->assertSame(0, $m->emailsUsed(2));
    }
}
