<?php
declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\FeatureTestCase;

final class JobControlTest extends FeatureTestCase
{
    private function makeJob(int $uid, string $state = 'draft'): int
    {
        $id = $this->app->jobs->create($uid, [
            'name' => 'J', 'mode' => 'live',
            'source_host' => 'h', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'h', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}',
        ]);
        $this->app->jobs->systemTransition($id, $state);
        return $id;
    }

    public function test_pause_resume_cancel_transitions(): void
    {
        $uid = $this->registerAndLogin();
        $id = $this->makeJob($uid, 'running');
        $this->post("/jobs/{$id}/pause");
        $this->assertSame('paused', $this->app->jobs->find($id, $uid)['state']);
        $this->post("/jobs/{$id}/resume");
        $this->assertSame('queued', $this->app->jobs->find($id, $uid)['state']);
        $this->post("/jobs/{$id}/cancel");
        $this->assertSame('canceled', $this->app->jobs->find($id, $uid)['state']);
    }

    public function test_guards_reject_invalid_transition(): void
    {
        $uid = $this->registerAndLogin();
        $done = $this->makeJob($uid, 'completed');
        $this->post("/jobs/{$done}/cancel"); // completed cannot be canceled
        $this->assertSame('completed', $this->app->jobs->find($done, $uid)['state']);

        $draft = $this->makeJob($uid, 'draft');
        $this->post("/jobs/{$draft}/pause"); // only running can pause
        $this->assertSame('draft', $this->app->jobs->find($draft, $uid)['state']);
    }

    public function test_per_user_isolation_404(): void
    {
        $owner = $this->registerAndLogin('owner@x.com');
        $id = $this->makeJob($owner, 'running');
        $this->app->auth->logout();
        $this->app->auth->register('B', 'b@x.com', 'secretpw');
        $this->assertSame(404, $this->post("/jobs/{$id}/pause")->status());
    }
}
