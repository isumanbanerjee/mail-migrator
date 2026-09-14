<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\Payments\PayPalGateway;
use App\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class PayPalGatewayTest extends TestCase
{
    private function cfg(): array { return ['client_id' => 'cid', 'secret' => 'sec', 'webhook_id' => 'wh', 'base' => 'https://api-m.sandbox.paypal.com']; }

    public function test_create_order_returns_ref_and_approve_url(): void
    {
        $http = new FakeHttpClient();
        $http->queue(200, json_encode(['access_token' => 'tok'])); // oauth
        $http->queue(201, json_encode(['id' => 'ORDER1', 'links' => [['rel' => 'approve', 'href' => 'https://paypal/approve/ORDER1']]]));
        $gw = new PayPalGateway($http, $this->cfg());
        $r = $gw->createOrder('one_time', '19.00', 'USD', ['user_id' => 5, 'product' => 'one_time']);
        $this->assertSame('ORDER1', $r['ref']);
        $this->assertSame('https://paypal/approve/ORDER1', $r['redirect_url']);
        $this->assertStringContainsString('/v1/oauth2/token', $http->requests[0]['url']);
        $this->assertStringContainsString('/v2/checkout/orders', $http->requests[1]['url']);
    }

    public function test_verify_webhook_success_and_failure(): void
    {
        $http = new FakeHttpClient();
        $http->queue(200, json_encode(['access_token' => 'tok']));
        $http->queue(200, json_encode(['verification_status' => 'SUCCESS']));
        $gw = new PayPalGateway($http, $this->cfg());
        $body = json_encode(['resource' => ['id' => 'CAP1', 'custom_id' => json_encode(['user_id' => 5, 'product' => 'one_time'])]]);
        $ok = $gw->verifyWebhook(['Paypal-Transmission-Id' => 't', 'Paypal-Transmission-Sig' => 's'], $body);
        $this->assertTrue($ok['ok']);
        $this->assertSame(5, $ok['user_id']);
        $this->assertSame('one_time', $ok['product']);

        $http2 = new FakeHttpClient();
        $http2->queue(200, json_encode(['access_token' => 'tok']));
        $http2->queue(200, json_encode(['verification_status' => 'FAILURE']));
        $gw2 = new PayPalGateway($http2, $this->cfg());
        $bad = $gw2->verifyWebhook(['Paypal-Transmission-Id' => 't'], $body);
        $this->assertFalse($bad['ok']);
    }
}
