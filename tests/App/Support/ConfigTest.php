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
}
