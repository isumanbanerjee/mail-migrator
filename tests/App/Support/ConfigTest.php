<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_reads_nested_values_from_array(): void
    {
        $cfg = Config::fromArray([
            'key' => 'abc',
            'db' => ['driver' => 'sqlite', 'host' => 'h'],
        ]);
        $this->assertSame('abc', $cfg->appKey());
        $this->assertSame('sqlite', $cfg->get('db.driver'));
        $this->assertSame('h', $cfg->db()['host']);
        $this->assertSame('fallback', $cfg->get('missing.key', 'fallback'));
    }

    public function test_validate_throws_on_empty_app_key(): void
    {
        $cfg = Config::fromArray(['key' => '', 'db' => ['driver' => 'sqlite']]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY is missing');
        $cfg->validate();
    }

    public function test_validate_throws_on_missing_db_driver(): void
    {
        $cfg = Config::fromArray(['key' => 'abc', 'db' => []]);
        $this->expectException(\RuntimeException::class);
        $cfg->validate();
    }

    public function test_validate_passes_with_key_and_driver(): void
    {
        $cfg = Config::fromArray(['key' => 'abc', 'db' => ['driver' => 'sqlite']]);
        $cfg->validate();
        $this->addToAssertionCount(1);
    }
}
