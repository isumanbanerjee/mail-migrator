<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Support\BillingConfig;
use InvalidArgumentException;

final class GatewayFactory
{
    public function __construct(private HttpClientInterface $http, private BillingConfig $cfg) {}

    public function get(string $name): PaymentGatewayInterface
    {
        return match ($name) {
            'paypal' => new PayPalGateway($this->http, $this->cfg->paypal()),
            'razorpay' => new RazorpayGateway($this->http, $this->cfg->razorpay()),
            default => throw new InvalidArgumentException("Unknown gateway: {$name}"),
        };
    }

    /** @return array<string,string> product => price */
    public function enabledProducts(): array
    {
        $p = [];
        if ($this->cfg->oneTimeEnabled()) { $p['one_time'] = $this->cfg->priceAmount(); }
        if ($this->cfg->creditsEnabled()) { $p['credit_pack'] = $this->cfg->creditPackPrice(); }
        if ($this->cfg->subscriptionEnabled()) { $p['subscription'] = $this->cfg->subscriptionPrice(); }
        return $p;
    }
}
