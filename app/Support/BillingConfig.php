<?php
declare(strict_types=1);

namespace App\Support;

final class BillingConfig
{
    public function __construct(private array $env) {}

    public static function fromEnv(): self { return new self($_ENV); }

    private function bool(string $k): bool { return in_array(strtolower((string) ($this->env[$k] ?? '')), ['1', 'true', 'yes', 'on'], true); }
    private function int(string $k, int $d): int { return isset($this->env[$k]) && $this->env[$k] !== '' ? (int) $this->env[$k] : $d; }
    private function str(string $k, string $d = ''): string { return (string) ($this->env[$k] ?? $d); }

    public function enabled(): bool { return $this->bool('PAYWALL_ENABLED'); }
    public function currency(): string { return $this->str('CURRENCY', 'USD'); }
    public function freeJobLimit(): int { return $this->int('FREE_JOB_LIMIT', 1); }
    public function freeEmailLimit(): int { return $this->int('FREE_EMAIL_LIMIT', 100); }
    public function priceAmount(): string { return $this->str('PRICE_AMOUNT', '0.00'); }
    public function oneTimeEnabled(): bool { return $this->bool('BILLING_ONE_TIME_ENABLED'); }
    public function creditsEnabled(): bool { return $this->bool('BILLING_CREDITS_ENABLED'); }
    public function subscriptionEnabled(): bool { return $this->bool('BILLING_SUBSCRIPTION_ENABLED'); }
    public function creditPackSize(): int { return $this->int('CREDIT_PACK_SIZE', 0); }
    public function creditPackPrice(): string { return $this->str('CREDIT_PACK_PRICE', '0.00'); }
    public function subscriptionPrice(): string { return $this->str('SUBSCRIPTION_PRICE', '0.00'); }

    public function paypal(): array
    {
        return ['client_id' => $this->str('PAYPAL_CLIENT_ID'), 'secret' => $this->str('PAYPAL_SECRET'),
            'webhook_id' => $this->str('PAYPAL_WEBHOOK_ID'), 'base' => $this->str('PAYPAL_BASE', 'https://api-m.paypal.com')];
    }

    public function razorpay(): array
    {
        return ['key_id' => $this->str('RAZORPAY_KEY_ID'), 'key_secret' => $this->str('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => $this->str('RAZORPAY_WEBHOOK_SECRET')];
    }
}
