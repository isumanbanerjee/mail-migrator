<?php
declare(strict_types=1);

namespace EmailMigration\Tests\Cli;

use EmailMigration\Cli\CliOptions;
use PHPUnit\Framework\TestCase;

final class CliOptionsTest extends TestCase
{
    public function test_defaults(): void
    {
        $o = CliOptions::parse(['migrate.php']);
        $this->assertSame('config/accounts.php', $o['config']);
        $this->assertFalse($o['dry_run']);
        $this->assertFalse($o['test_connection']);
        $this->assertNull($o['only_folder']);
        $this->assertNull($o['limit']);
    }

    public function test_parses_flags_and_values(): void
    {
        $o = CliOptions::parse([
            'migrate.php', '--config=cfg.php', '--dry-run', '--folder=INBOX',
            '--since=2020-01-01', '--limit=50', '--retry-failed', '--verbose', '--test-connection',
        ]);
        $this->assertSame('cfg.php', $o['config']);
        $this->assertTrue($o['dry_run']);
        $this->assertSame('INBOX', $o['only_folder']);
        $this->assertSame('2020-01-01', $o['since']);
        $this->assertSame(50, $o['limit']);
        $this->assertTrue($o['retry_failed']);
        $this->assertTrue($o['verbose']);
        $this->assertTrue($o['test_connection']);
    }
}
