<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Repositories\EntitlementRepository;
use App\Services\EntitlementResolver;
use App\Services\MeteringService;
use App\Support\BillingConfig;
use App\Support\Database;
use App\Support\Migrator;
use App\Tests\TestCase;

final class EntitlementResolverTest extends TestCase
{
    private function build(array $env): array
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        $ent = new EntitlementRepository($pdo);
        $meter = new MeteringService($pdo);
        $clock = static fn (): int => strtotime('2026-01-01 00:00:00');
        return [new EntitlementResolver(new BillingConfig($env), $ent, $meter, $clock), $ent, $pdo];
    }

    public function test_paywall_off_allows(): void
    {
        [$r] = $this->build(['PAYWALL_ENABLED' => 'false']);
        $this->assertTrue($r->canRunJob(1)['allowed']);
    }

    public function test_free_quota_then_blocked(): void
    {
        // paywall on, free 1 job / 100 emails, no entitlements, no usage => allowed (free)
        [$r, $ent, $pdo] = $this->build(['PAYWALL_ENABLED' => 'true', 'FREE_JOB_LIMIT' => '1', 'FREE_EMAIL_LIMIT' => '100']);
        $this->assertTrue($r->canRunJob(1)['allowed']);
        // simulate 1 job already used → jobsUsed(1)>=1 → blocked
        $pdo->prepare("INSERT INTO jobs (user_id,name,mode,state,source_host,source_port,source_encryption,source_username_enc,source_password_enc,dest_host,dest_port,dest_encryption,dest_username_enc,dest_password_enc,options,created_at,updated_at) VALUES (1,'J','live','draft','h',993,'ssl','x','x','h',993,'ssl','x','x','{}',:t,:t)")->execute([':t' => '2026-01-01 00:00:00']);
        $res = $r->canRunJob(1);
        $this->assertFalse($res['allowed']);
        $this->assertSame('quota_exceeded', $res['reason']);
    }

    public function test_unlimited_and_subscription_and_credits(): void
    {
        [$r, $ent] = $this->build(['PAYWALL_ENABLED' => 'true', 'FREE_JOB_LIMIT' => '0', 'FREE_EMAIL_LIMIT' => '0']);
        // free limits 0 → without entitlement, blocked
        $this->assertFalse($r->canRunJob(7)['allowed']);
        $ent->grantUnlimited(7);
        $this->assertSame('unlimited', $r->canRunJob(7)['reason']);

        $ent2Id = 8;
        $ent->extendSubscription($ent2Id, '2027-01-01 00:00:00'); // future vs clock 2026
        $this->assertSame('subscription', $r->canRunJob($ent2Id)['reason']);

        $ent->addCredits(9, 10);
        $this->assertSame('credits', $r->canRunJob(9)['reason']);
    }
}
