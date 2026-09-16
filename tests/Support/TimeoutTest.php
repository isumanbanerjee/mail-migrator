<?php
declare(strict_types=1);

namespace EmailMigration\Tests\Support;

use EmailMigration\Support\Timeout;
use EmailMigration\Support\TimeoutException;
use PHPUnit\Framework\TestCase;

final class TimeoutTest extends TestCase
{
    public function test_returns_callable_result(): void
    {
        $this->assertSame(42, Timeout::run(5, fn () => 42));
    }

    public function test_zero_seconds_runs_without_timeout(): void
    {
        $this->assertSame('ok', Timeout::run(0, fn () => 'ok'));
    }

    public function test_times_out_when_pcntl_available(): void
    {
        if (!function_exists('pcntl_alarm')) {
            $this->markTestSkipped('pcntl not available on this platform');
        }
        $this->expectException(TimeoutException::class);
        Timeout::run(1, function () { sleep(5); });
    }
}
