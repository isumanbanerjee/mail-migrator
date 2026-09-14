<?php
declare(strict_types=1);

namespace App\Services\Payments;

interface PaymentGatewayInterface
{
    public function name(): string;

    /** @return array{ref:string,redirect_url:?string,extra:array} */
    public function createOrder(string $product, string $amount, string $currency, array $meta): array;

    /** @return array{ok:bool,ref:?string,status:string,product:?string,user_id:?int} */
    public function verifyWebhook(array $headers, string $rawBody): array;
}
