<?php
declare(strict_types=1);

namespace App\Services\Payments;

interface HttpClientInterface
{
    /** @return array{status:int,body:string} */
    public function request(string $method, string $url, array $headers, ?string $body): array;
}
