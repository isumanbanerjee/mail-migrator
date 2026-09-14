<?php
declare(strict_types=1);

namespace App\Tests;

final class WorkerDocsTest extends TestCase
{
    public function test_env_and_docs_mention_worker(): void
    {
        $base = dirname(__DIR__, 2);
        $env = file_get_contents($base . '/.env.example');
        $this->assertStringContainsString('WORKER_MAX_SECONDS', $env);
        $this->assertStringContainsString('MAX_CONCURRENT_JOBS', $env);
        $doc = file_get_contents($base . '/docs/deploy-shared-hosting.md');
        $this->assertStringContainsString('bin/worker.php', $doc);
        $this->assertStringContainsString('cron', strtolower($doc));
    }
}
