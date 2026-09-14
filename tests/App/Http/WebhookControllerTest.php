<?php
declare(strict_types=1);

namespace App\Tests\Http;

use App\Support\BillingConfig;
use App\Support\Request;
use App\Tests\FeatureTestCase;

final class WebhookControllerTest extends FeatureTestCase
{
    protected array $billingEnv = [
        'PAYWALL_ENABLED' => 'true',
        'BILLING_ONE_TIME_ENABLED' => 'true',
        'PRICE_AMOUNT' => '19.00',
        'RAZORPAY_WEBHOOK_SECRET' => 'whsec_test123',
        'RAZORPAY_KEY_ID' => 'rzp_test_id',
        'RAZORPAY_KEY_SECRET' => 'rzp_test_secret',
    ];

    private function razorpayBody(int $userId): string
    {
        return json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_abc123',
                'notes' => ['user_id' => (string) $userId, 'product' => 'one_time'],
            ]]],
        ]);
    }

    private function postWebhook(string $body, string $signature): \App\Support\Response
    {
        return $this->app->handle(new Request(
            'POST', '/webhooks/razorpay', [], [], [],
            ['HTTP_X_RAZORPAY_SIGNATURE' => $signature],
            $body,
        ));
    }

    public function test_valid_signature_grants_entitlement_idempotently(): void
    {
        $uid = $this->registerAndLogin();
        $body = $this->razorpayBody($uid);
        $sig = hash_hmac('sha256', $body, 'whsec_test123');

        $res = $this->postWebhook($body, $sig);
        $this->assertSame(200, $res->status());
        $this->assertSame(['ok' => true], json_decode($res->body(), true));
        $this->assertSame(1, (int) $this->app->entitlements->for($uid)['unlimited']);

        // Second identical delivery is idempotent: no error, still unlimited.
        $res2 = $this->postWebhook($body, $sig);
        $this->assertSame(200, $res2->status());
        $this->assertSame(['ok' => true], json_decode($res2->body(), true));
        $this->assertSame(1, (int) $this->app->entitlements->for($uid)['unlimited']);
    }

    public function test_invalid_signature_rejected_and_no_grant(): void
    {
        $uid = $this->registerAndLogin();
        $body = $this->razorpayBody($uid);

        $res = $this->postWebhook($body, 'not-the-right-signature');
        $this->assertSame(400, $res->status());
        $this->assertSame(['ok' => false], json_decode($res->body(), true));
        $this->assertSame(0, (int) $this->app->entitlements->for($uid)['unlimited']);
    }
}
