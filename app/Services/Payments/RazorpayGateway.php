<?php
declare(strict_types=1);

namespace App\Services\Payments;

final class RazorpayGateway implements PaymentGatewayInterface
{
    public function __construct(private HttpClientInterface $http, private array $cfg) {}

    public function name(): string { return 'razorpay'; }

    public function createOrder(string $product, string $amount, string $currency, array $meta): array
    {
        $minor = (int) round(((float) $amount) * 100);
        $body = json_encode(['amount' => $minor, 'currency' => $currency, 'notes' => $meta]);
        $auth = base64_encode($this->cfg['key_id'] . ':' . $this->cfg['key_secret']);
        $resp = $this->http->request('POST', 'https://api.razorpay.com/v1/orders',
            ['Authorization: Basic ' . $auth, 'Content-Type: application/json'], $body);
        $data = json_decode($resp['body'], true) ?: [];
        return ['ref' => (string) ($data['id'] ?? ''), 'redirect_url' => null,
            'extra' => ['key_id' => $this->cfg['key_id'], 'amount' => $minor, 'currency' => $currency]];
    }

    public function verifyWebhook(array $headers, string $rawBody): array
    {
        $sig = $this->header($headers, 'X-Razorpay-Signature');
        $expected = hash_hmac('sha256', $rawBody, (string) $this->cfg['webhook_secret']);
        if ($sig === null || !hash_equals($expected, $sig)) {
            return ['ok' => false, 'ref' => null, 'status' => 'invalid', 'product' => null, 'user_id' => null];
        }
        $data = json_decode($rawBody, true) ?: [];
        $entity = $data['payload']['payment']['entity'] ?? [];
        $notes = $entity['notes'] ?? [];
        return ['ok' => true, 'ref' => (string) ($entity['id'] ?? ''), 'status' => 'paid',
            'product' => $notes['product'] ?? null, 'user_id' => isset($notes['user_id']) ? (int) $notes['user_id'] : null];
    }

    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) { return is_array($v) ? ($v[0] ?? null) : (string) $v; }
        }
        return null;
    }
}
