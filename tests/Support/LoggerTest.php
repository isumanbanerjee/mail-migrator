<?php
declare(strict_types=1);

namespace MailMigrator\Tests\Support;

use MailMigrator\Support\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    public function test_redacts_registered_secrets(): void
    {
        $log = new Logger('debug');
        $log->addSecret('sup3r-secret-pw');
        $log->info('Connecting with password sup3r-secret-pw now');

        $line = $log->lines()[0];
        $this->assertStringNotContainsString('sup3r-secret-pw', $line);
        $this->assertStringContainsString('***', $line);
    }

    public function test_debug_suppressed_at_info_level(): void
    {
        $log = new Logger('info');
        $log->debug('noisy detail');
        $this->assertCount(0, $log->lines());
    }

    public function test_empty_secret_is_ignored(): void
    {
        $log = new Logger('info');
        $log->addSecret('');
        $log->info('hello');
        $this->assertStringContainsString('hello', $log->lines()[0]);
    }
}
