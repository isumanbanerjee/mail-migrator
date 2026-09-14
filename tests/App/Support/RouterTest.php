<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function test_found_with_vars_and_auth_flag(): void
    {
        $r = new Router();
        $r->add('GET', '/jobs/{id}', fn() => 'ok', true);
        $d = $r->dispatch('GET', '/jobs/42');
        $this->assertSame('found', $d['status']);
        $this->assertTrue($d['auth']);
        $this->assertSame('42', $d['vars']['id']);
    }

    public function test_not_found_and_method_not_allowed(): void
    {
        $r = new Router();
        $r->add('GET', '/x', fn() => 'ok');
        $this->assertSame('not_found', $r->dispatch('GET', '/nope')['status']);
        $this->assertSame('method_not_allowed', $r->dispatch('POST', '/x')['status']);
    }
}
