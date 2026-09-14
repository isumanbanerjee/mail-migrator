<?php
declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Services\Payments\HttpClientInterface;

final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<int,array{status:int,body:string}> */
    private array $responses;
    /** @var array<int,array> */
    public array $requests = [];

    public function __construct(array $responses = []) { $this->responses = $responses; }
    public function queue(int $status, string $body): void { $this->responses[] = ['status' => $status, 'body' => $body]; }

    public function request(string $method, string $url, array $headers, ?string $body): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        return array_shift($this->responses) ?? ['status' => 200, 'body' => '{}'];
    }
}
