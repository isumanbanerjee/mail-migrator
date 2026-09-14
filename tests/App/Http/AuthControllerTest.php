<?php
declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\FeatureTestCase;

final class AuthControllerTest extends FeatureTestCase
{
    public function test_register_creates_user_and_redirects_to_dashboard(): void
    {
        $res = $this->post('/register', ['name' => 'Bob', 'email' => 'bob@x.com', 'password' => 'secretpw']);
        $this->assertSame(302, $res->status());
        $this->assertSame('/dashboard', $res->headers()['Location']);
        $this->assertTrue($this->app->auth->check());
    }

    public function test_register_validation_errors_render_form(): void
    {
        $res = $this->post('/register', ['name' => '', 'email' => 'bad', 'password' => '']);
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString('Register', $res->body());
        $this->assertFalse($this->app->auth->check());
    }

    public function test_login_success_and_wrong_password(): void
    {
        $this->app->auth->register('Bob', 'bob@x.com', 'secretpw');
        $this->app->auth->logout();

        $bad = $this->post('/login', ['email' => 'bob@x.com', 'password' => 'nope']);
        $this->assertSame(200, $bad->status()); // re-render with error
        $this->assertFalse($this->app->auth->check());

        $ok = $this->post('/login', ['email' => 'bob@x.com', 'password' => 'secretpw']);
        $this->assertSame(302, $ok->status());
        $this->assertTrue($this->app->auth->check());
    }

    public function test_post_without_csrf_is_rejected(): void
    {
        $res = $this->app->handle(new \App\Support\Request('POST', '/login', [], ['email' => 'a', 'password' => 'b'], [], []));
        $this->assertSame(419, $res->status());
    }

    public function test_logout_clears_session(): void
    {
        $this->registerAndLogin();
        $res = $this->post('/logout');
        $this->assertSame(302, $res->status());
        $this->assertFalse($this->app->auth->check());
    }
}
