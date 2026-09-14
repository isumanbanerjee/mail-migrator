<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class FakeHttpClientTest extends TestCase
{
    public function test_records_and_returns_queued(): void
    {
        $http = new FakeHttpClient();
        $http->queue(201, '{"id":"x"}');
        $r = $http->request('POST', 'https://api/x', ['A: 1'], '{}');
        $this->assertSame(201, $r['status']);
        $this->assertSame('{"id":"x"}', $r['body']);
        $this->assertSame('POST', $http->requests[0]['method']);
    }
}
