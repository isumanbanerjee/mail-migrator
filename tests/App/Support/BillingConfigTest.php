<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\BillingConfig;
use PHPUnit\Framework\TestCase;

final class BillingConfigTest extends TestCase
{
    public function test_reads_flags_and_values(): void
    {
        $c = new BillingConfig([
            'PAYWALL_ENABLED' => 'true', 'CURRENCY' => 'USD',
            'FREE_JOB_LIMIT' => '1', 'FREE_EMAIL_LIMIT' => '100', 'PRICE_AMOUNT' => '19.00',
            'BILLING_ONE_TIME_ENABLED' => 'true', 'BILLING_CREDITS_ENABLED' => 'false',
            'BILLING_SUBSCRIPTION_ENABLED' => 'false', 'CREDIT_PACK_SIZE' => '500',
            'CREDIT_PACK_PRICE' => '5.00', 'SUBSCRIPTION_PRICE' => '9.00',
        ]);
        $this->assertTrue($c->enabled());
        $this->assertSame('USD', $c->currency());
        $this->assertSame(1, $c->freeJobLimit());
        $this->assertSame(100, $c->freeEmailLimit());
        $this->assertTrue($c->oneTimeEnabled());
        $this->assertFalse($c->creditsEnabled());
        $this->assertSame(500, $c->creditPackSize());
    }

    public function test_defaults_when_absent(): void
    {
        $c = new BillingConfig([]);
        $this->assertFalse($c->enabled());
        $this->assertSame('USD', $c->currency());
    }
}
