<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Csrf;
use App\Support\Session;
use PHPUnit\Framework\TestCase;

final class SessionCsrfTest extends TestCase
{
    public function test_session_put_get_flash(): void
    {
        $s = new Session();
        $s->put('a', 1);
        $this->assertSame(1, $s->get('a'));
        $s->forget('a');
        $this->assertNull($s->get('a'));

        $s->flash('msg', 'saved');
        $this->assertSame('saved', $s->getFlash('msg'));
        $this->assertNull($s->getFlash('msg')); // flash consumed once
    }

    public function test_csrf_token_and_verify(): void
    {
        $s = new Session();
        $token = Csrf::token($s);
        $this->assertNotSame('', $token);
        $this->assertSame($token, Csrf::token($s)); // stable within session
        $this->assertTrue(Csrf::verify($s, $token));
        $this->assertFalse(Csrf::verify($s, 'wrong'));
        $this->assertFalse(Csrf::verify($s, null));
    }
}
