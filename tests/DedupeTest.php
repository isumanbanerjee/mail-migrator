<?php
declare(strict_types=1);

namespace EmailMigration\Tests;

use EmailMigration\Dedupe;
use PHPUnit\Framework\TestCase;

final class DedupeTest extends TestCase
{
    public function test_key_prefers_message_id(): void
    {
        $this->assertSame('<abc@x>', Dedupe::key('<abc@x>', 'd', 'f', 's', 10));
    }

    public function test_key_trims_message_id(): void
    {
        $this->assertSame('<abc@x>', Dedupe::key('  <abc@x> ', 'd', 'f', 's', 10));
    }

    public function test_key_falls_back_to_hash_when_message_id_missing(): void
    {
        $expected = 'hash:' . Dedupe::hash('d', 'f', 's', 10);
        $this->assertSame($expected, Dedupe::key(null, 'd', 'f', 's', 10));
        $this->assertSame($expected, Dedupe::key('', 'd', 'f', 's', 10));
    }

    public function test_hash_is_stable_and_size_sensitive(): void
    {
        $a = Dedupe::hash('d', 'f', 's', 10);
        $b = Dedupe::hash('d', 'f', 's', 11);
        $this->assertSame($a, Dedupe::hash('d', 'f', 's', 10));
        $this->assertNotSame($a, $b);
    }
}
