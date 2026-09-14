<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\EntitlementRepository;
use App\Repositories\PaymentRepository;
use App\Services\Payments\GatewayFactory;
use App\Support\BillingConfig;
use App\Support\Request;
use App\Support\Response;
use InvalidArgumentException;

final class WebhookController
{
    public function __construct(
        private GatewayFactory $gateways,
        private PaymentRepository $payments,
        private EntitlementRepository $entitlements,
        private BillingConfig $cfg,
    ) {}

    public function handle(Request $req, array $vars): Response
    {
        $gatewayName = (string) ($vars['gateway'] ?? '');

        try {
            $gw = $this->gateways->get($gatewayName);
        } catch (InvalidArgumentException) {
            return Response::json(['ok' => false], 400);
        }

        $result = $gw->verifyWebhook($this->headersFromServer($req->serverAll()), $req->rawBody());
        if (!$result['ok']) {
            return Response::json(['ok' => false], 400);
        }

        $ref = (string) ($result['ref'] ?? '');
        $product = $result['product'] ?? null;
        $userId = $result['user_id'] ?? null;

        if ($ref === '' || $product === null || $userId === null) {
            // Signature verified but no actionable payload; nothing to grant.
            return Response::json(['ok' => true]);
        }

        $existing = $this->payments->findByRef($gw->name(), $ref);
        if ($existing !== null && $existing['status'] === 'paid') {
            // Already processed — idempotent no-op.
            return Response::json(['ok' => true]);
        }

        $amount = $this->gateways->enabledProducts()[$product] ?? '0.00';
        if ($existing === null) {
            try {
                $paymentId = $this->payments->record([
                    'user_id' => (int) $userId,
                    'gateway' => $gw->name(),
                    'product' => $product,
                    'gateway_ref' => $ref,
                    'amount' => $amount,
                    'currency' => $this->cfg->currency(),
                    'status' => 'created',
                ]);
            } catch (\PDOException $e) {
                // Concurrent delivery of the same new event raced us to insert the unique
                // (gateway, gateway_ref) row first. That delivery owns the single grant;
                // treat this one as an idempotent no-op rather than a 500.
                if (!in_array((string) $e->getCode(), ['23000', '19'], true)) {
                    throw $e;
                }
                return Response::json(['ok' => true]);
            }
        } else {
            $paymentId = (int) $existing['id'];
        }
        $this->payments->markPaid($paymentId);

        $this->grant((int) $userId, (string) $product);

        return Response::json(['ok' => true]);
    }

    private function grant(int $userId, string $product): void
    {
        match ($product) {
            'one_time' => $this->entitlements->grantUnlimited($userId),
            'credit_pack' => $this->entitlements->addCredits($userId, $this->cfg->creditPackSize()),
            'subscription' => $this->entitlements->extendSubscription($userId, date('Y-m-d H:i:s', strtotime('+1 month'))),
            default => null,
        };
    }

    /** @return array<string,string> */
    private function headersFromServer(array $server): array
    {
        $headers = [];
        foreach ($server as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', substr($k, 5));
                $headers[$name] = (string) $v;
            }
        }
        return $headers;
    }
}
