<?php
declare(strict_types=1);

namespace App\Tests;

final class AssetsAndLayoutTest extends TestCase
{
    public function test_compiled_assets_exist_and_nonempty(): void
    {
        $base = dirname(__DIR__, 2);
        $this->assertFileExists($base . '/public/assets/app.css');
        $this->assertGreaterThan(0, filesize($base . '/public/assets/app.css'));
        $this->assertFileExists($base . '/public/assets/alpine.min.js');
        $this->assertGreaterThan(0, filesize($base . '/public/assets/alpine.min.js'));
    }

    public function test_root_htaccess_denies_sensitive_paths(): void
    {
        $htaccess = file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        $this->assertStringContainsString('app|src|config|database', $htaccess);
        $this->assertStringContainsString('\.env', $htaccess);
    }
}
