<?php
declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\FeatureTestCase;

final class DashboardControllerTest extends FeatureTestCase
{
    private function makeJob(int $uid, string $name): int
    {
        return $this->app->jobs->create($uid, [
            'name' => $name, 'mode' => 'live',
            'source_host' => 'h', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'h', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}',
        ]);
    }

    public function test_dashboard_lists_only_own_jobs(): void
    {
        $uid = $this->registerAndLogin('a@x.com');
        $this->makeJob($uid, 'Alpha Job');
        $res = $this->get('/dashboard');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Alpha Job', $res->body());
    }

    public function test_progress_json_scoped_to_owner(): void
    {
        $uid = $this->registerAndLogin('a@x.com');
        $id = $this->makeJob($uid, 'Alpha');
        $res = $this->get("/jobs/{$id}/progress");
        $this->assertSame('application/json', $res->headers()['Content-Type']);
        $data = json_decode($res->body(), true);
        $this->assertSame('draft', $data['state']);
        $this->assertSame(0, $data['percent']);

        // other user cannot read progress
        $this->app->auth->logout();
        $this->app->auth->register('B', 'b@x.com', 'secretpw');
        $this->assertSame(404, $this->get("/jobs/{$id}/progress")->status());
    }

    public function test_dashboard_requires_login(): void
    {
        $this->assertSame(302, $this->get('/dashboard')->status());
    }
}
