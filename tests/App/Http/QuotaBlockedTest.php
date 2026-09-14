<?php
declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\FeatureTestCase;

final class QuotaBlockedTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        $this->billingEnv = ['PAYWALL_ENABLED' => 'true', 'FREE_JOB_LIMIT' => '0', 'FREE_EMAIL_LIMIT' => '0'];
        parent::setUp();
    }

    private function makeJob(int $uid): int
    {
        return $this->app->jobs->create($uid, ['name' => 'J', 'mode' => 'live',
            'source_host' => 'h', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'h', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}']);
    }

    public function test_queue_blocked_when_paywall_on_and_quota_exhausted(): void
    {
        $uid = $this->registerAndLogin();
        $id = $this->makeJob($uid);

        $res = $this->post("/jobs/{$id}/queue");

        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/billing', (string) ($res->headers()['Location'] ?? ''));
        $this->assertSame('draft', $this->app->jobs->find($id, $uid)['state']);
    }

    public function test_resume_blocked_when_paywall_on_and_quota_exhausted(): void
    {
        // Regression: pause -> resume must not bypass the entitlement check that queue() enforces.
        $uid = $this->registerAndLogin();
        $id = $this->makeJob($uid);
        $this->app->jobs->transition($id, $uid, 'paused');

        $res = $this->post("/jobs/{$id}/resume");

        $this->assertSame(302, $res->status());
        $this->assertStringContainsString('/billing', (string) ($res->headers()['Location'] ?? ''));
        $this->assertSame('paused', $this->app->jobs->find($id, $uid)['state']);
    }
}
