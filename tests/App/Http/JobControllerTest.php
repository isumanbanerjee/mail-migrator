<?php
declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\FeatureTestCase;

final class JobControllerTest extends FeatureTestCase
{
    private function jobData(string $name = 'My Job'): array
    {
        return [
            'name' => $name,
            'source_host' => 'imap.src', 'source_port' => '993', 'source_encryption' => 'ssl',
            'source_username' => 'srcuser', 'source_password' => 'srcpass',
            'dest_host' => 'imap.dst', 'dest_port' => '993', 'dest_encryption' => 'ssl',
            'dest_username' => 'dstuser', 'dest_password' => 'dstpass',
            'mode' => 'live',
        ];
    }

    public function test_store_creates_encrypted_job(): void
    {
        $uid = $this->registerAndLogin();
        $res = $this->post('/jobs', $this->jobData());
        $this->assertSame(302, $res->status());

        $jobs = $this->app->jobs->listForUser($uid);
        $this->assertCount(1, $jobs);
        $row = $jobs[0];
        $this->assertSame('draft', $row['state']);
        // credentials stored encrypted, not plaintext
        $this->assertStringNotContainsString('srcpass', $row['source_password_enc']);
        $this->assertSame('srcpass', $this->app->encryptor->decrypt($row['source_password_enc']));
    }

    public function test_per_user_isolation_returns_404(): void
    {
        $ownerId = $this->registerAndLogin('owner@x.com');
        $jobId = $this->app->jobs->create($ownerId, [
            'name' => 'O', 'mode' => 'live',
            'source_host' => 'h', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'x', 'source_password_enc' => 'x',
            'dest_host' => 'h', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'x', 'dest_password_enc' => 'x', 'options' => '{}',
        ]);
        // switch to a different logged-in user
        $this->app->auth->logout();
        $this->app->auth->register('Intruder', 'intruder@x.com', 'secretpw');

        $this->assertSame(404, $this->get("/jobs/{$jobId}")->status());
        $this->assertSame(404, $this->post("/jobs/{$jobId}/queue")->status());
    }

    public function test_queue_and_cancel_transitions(): void
    {
        $uid = $this->registerAndLogin();
        $this->post('/jobs', $this->jobData());
        $id = (int) $this->app->jobs->listForUser($uid)[0]['id'];

        $this->post("/jobs/{$id}/queue");
        $this->assertSame('queued', $this->app->jobs->find($id, $uid)['state']);

        $this->post("/jobs/{$id}/cancel");
        $this->assertSame('canceled', $this->app->jobs->find($id, $uid)['state']);
    }

    public function test_test_connection_returns_json_with_folder_map(): void
    {
        $this->registerAndLogin();
        $res = $this->post('/jobs/test-connection', $this->jobData());
        $this->assertSame('application/json', $res->headers()['Content-Type']);
        $data = json_decode($res->body(), true);
        $this->assertTrue($data['source']['ok']);
        $this->assertArrayHasKey('INBOX', $data['folder_map']);
    }

    public function test_guest_cannot_create(): void
    {
        $this->assertSame(302, $this->get('/jobs/create')->status()); // redirect to /login
    }
}
