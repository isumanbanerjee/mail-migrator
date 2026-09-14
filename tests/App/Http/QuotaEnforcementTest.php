<?php
declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\FeatureTestCase;

final class QuotaEnforcementTest extends FeatureTestCase
{
    private function makeJob(int $uid): int
    {
        return $this->app->jobs->create($uid, ['name' => 'J', 'mode' => 'live',
            'source_host' => 'h', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'h', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}']);
    }

    public function test_queue_allowed_when_paywall_off(): void
    {
        // FeatureTestCase default: paywall off
        $uid = $this->registerAndLogin();
        $id = $this->makeJob($uid);
        $this->post("/jobs/{$id}/queue");
        $this->assertSame('queued', $this->app->jobs->find($id, $uid)['state']);
    }

    public function test_resume_allowed_when_paywall_off(): void
    {
        // FeatureTestCase default: paywall off
        $uid = $this->registerAndLogin();
        $id = $this->makeJob($uid);
        $this->app->jobs->transition($id, $uid, 'paused');
        $this->post("/jobs/{$id}/resume");
        $this->assertSame('queued', $this->app->jobs->find($id, $uid)['state']);
    }
}
