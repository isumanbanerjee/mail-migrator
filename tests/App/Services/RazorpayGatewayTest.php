<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\Payments\RazorpayGateway;
use App\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class RazorpayGatewayTest extends TestCase
{
    private function cfg(): array { return ['key_id' => 'kid', 'key_secret' => 'ksec', 'webhook_secret' => 'whsec']; }

    public function test_create_order_posts_and_returns_ref(): void
    {
        $http = new FakeHttpClient();
        $http->queue(200, json_encode(['id' => 'order_ABC', 'amount' => 1900, 'currency' => 'USD']));
        $gw = new RazorpayGateway($http, $this->cfg());
        $r = $gw->createOrder('one_time', '19.00', 'USD', ['user_id' => 5, 'product' => 'one_time']);
        $this->assertSame('order_ABC', $r['ref']);
        $this->assertStringContainsString('api.razorpay.com/v1/orders', $http->requests[0]['url']);
        $this->assertStringContainsString('1900', (string) $http->requests[0]['body']); // amount in minor units
    }

    public function test_verify_webhook_signature(): void
    {
        $gw = new RazorpayGateway(new FakeHttpClient(), $this->cfg());
        $payload = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_1', 'notes' => ['user_id' => 5, 'product' => 'one_time']]]]]);
        $sig = hash_hmac('sha256', $payload, 'whsec');

        $ok = $gw->verifyWebhook(['X-Razorpay-Signature' => $sig], $payload);
        $this->assertTrue($ok['ok']);
        $this->assertSame(5, $ok['user_id']);
        $this->assertSame('one_time', $ok['product']);

        $bad = $gw->verifyWebhook(['X-Razorpay-Signature' => 'deadbeef'], $payload);
        $this->assertFalse($bad['ok']);
    }
}
