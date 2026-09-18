<?php
declare(strict_types=1);

namespace MailMigrator\Tests;

use MailMigrator\Config;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_loads_valid_file_with_defaults(): void
    {
        $cfg = Config::fromFile(__DIR__ . '/fixtures/valid-config.php');

        $this->assertSame('imap.hostinger.com', $cfg->source()['host']);
        $this->assertSame(993, $cfg->destination()['port']);
        $this->assertSame(50, $cfg->options()['batch_size']);
        // defaults applied for unspecified options
        $this->assertTrue($cfg->source()['validate_cert']);
        $this->assertSame([], $cfg->options()['folder_map']);
        $this->assertNull($cfg->options()['since']);
    }

    public function test_missing_source_password_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Config::fromArray([
            'source' => ['host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u'],
            'destination' => ['host' => 'h', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p'],
        ]);
    }
}
