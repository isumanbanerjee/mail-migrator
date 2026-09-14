<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Request;
use App\Support\Response;
use PHPUnit\Framework\TestCase;

final class RequestResponseTest extends TestCase
{
    public function test_request_accessors(): void
    {
        $r = new Request('POST', '/jobs', ['q' => '1'], ['name' => 'Bob'], ['sid' => 'x'], []);
        $this->assertSame('POST', $r->method());
        $this->assertSame('/jobs', $r->path());
        $this->assertTrue($r->isPost());
        $this->assertSame('Bob', $r->input('name'));
        $this->assertSame('1', $r->query('q'));
        $this->assertSame('x', $r->cookie('sid'));
        $this->assertNull($r->input('missing'));
    }

    public function test_response_helpers(): void
    {
        $html = Response::html('<p>hi</p>', 201);
        $this->assertSame(201, $html->status());
        $this->assertStringContainsString('hi', $html->body());

        $redir = Response::redirect('/login');
        $this->assertSame(302, $redir->status());
        $this->assertSame('/login', $redir->headers()['Location']);

        $json = Response::json(['ok' => true]);
        $this->assertSame('application/json', $json->headers()['Content-Type']);
        $this->assertSame('{"ok":true}', $json->body());
    }
}
