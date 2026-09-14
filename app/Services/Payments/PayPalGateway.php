<?php
declare(strict_types=1);

namespace App\Services\Payments;

final class PayPalGateway implements PaymentGatewayInterface
{
    public function __construct(private HttpClientInterface $http, private array $cfg) {}

    public function name(): string { return 'paypal'; }

    private function token(): string
    {
        $auth = base64_encode($this->cfg['client_id'] . ':' . $this->cfg['secret']);
        $resp = $this->http->request('POST', $this->cfg['base'] . '/v1/oauth2/token',
            ['Authorization: Basic ' . $auth, 'Content-Type: application/x-www-form-urlencoded'], 'grant_type=client_credentials');
        return (string) ((json_decode($resp['body'], true) ?: [])['access_token'] ?? '');
    }

    public function createOrder(string $product, string $amount, string $currency, array $meta): array
    {
        $token = $this->token();
        $body = json_encode(['intent' => 'CAPTURE', 'purchase_units' => [[
            'amount' => ['currency_code' => $currency, 'value' => $amount],
            'custom_id' => json_encode($meta),
        ]]]);
        $resp = $this->http->request('POST', $this->cfg['base'] . '/v2/checkout/orders',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], $body);
        $data = json_decode($resp['body'], true) ?: [];
        $approve = null;
        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') { $approve = $link['href']; }
        }
        return ['ref' => (string) ($data['id'] ?? ''), 'redirect_url' => $approve, 'extra' => []];
    }

    public function verifyWebhook(array $headers, string $rawBody): array
    {
        $token = $this->token();
        $verifyBody = json_encode([
            'transmission_id' => $this->h($headers, 'Paypal-Transmission-Id'),
            'transmission_time' => $this->h($headers, 'Paypal-Transmission-Time'),
            'cert_url' => $this->h($headers, 'Paypal-Cert-Url'),
            'auth_algo' => $this->h($headers, 'Paypal-Auth-Algo'),
            'transmission_sig' => $this->h($headers, 'Paypal-Transmission-Sig'),
            'webhook_id' => $this->cfg['webhook_id'],
            'webhook_event' => json_decode($rawBody, true),
        ]);
        $resp = $this->http->request('POST', $this->cfg['base'] . '/v1/notifications/verify-webhook-signature',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], $verifyBody);
        $status = (json_decode($resp['body'], true) ?: [])['verification_status'] ?? 'FAILURE';
        if ($status !== 'SUCCESS') {
            return ['ok' => false, 'ref' => null, 'status' => 'invalid', 'product' => null, 'user_id' => null];
        }
        $event = json_decode($rawBody, true) ?: [];
        $resource = $event['resource'] ?? [];
        $meta = json_decode((string) ($resource['custom_id'] ?? '{}'), true) ?: [];
        return ['ok' => true, 'ref' => (string) ($resource['id'] ?? ''), 'status' => 'paid',
            'product' => $meta['product'] ?? null, 'user_id' => isset($meta['user_id']) ? (int) $meta['user_id'] : null];
    }

    private function h(array $headers, string $name): ?string
    {
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) { return is_array($v) ? ($v[0] ?? null) : (string) $v; }
        }
        return null;
    }
}
