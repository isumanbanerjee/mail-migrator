<?php
declare(strict_types=1);

namespace App\Tests;

final class WorkerScriptTest extends TestCase
{
    public function test_worker_script_has_no_syntax_errors(): void
    {
        $php = PHP_BINARY;
        $script = dirname(__DIR__, 2) . '/bin/worker.php';
        $this->assertFileExists($script);
        exec(escapeshellarg($php) . ' -l ' . escapeshellarg($script), $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }
}
