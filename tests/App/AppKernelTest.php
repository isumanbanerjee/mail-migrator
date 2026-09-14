<?php
declare(strict_types=1);

namespace App\Tests;

final class AppKernelTest extends FeatureTestCase
{
    public function test_home_redirects_to_login_when_guest(): void
    {
        $res = $this->get('/');
        $this->assertSame(302, $res->status());
        $this->assertSame('/login', $res->headers()['Location']);
    }

    public function test_unknown_route_404(): void
    {
        $this->assertSame(404, $this->get('/nope')->status());
    }

    public function test_home_redirects_to_dashboard_when_authed(): void
    {
        $this->registerAndLogin();
        $res = $this->get('/');
        $this->assertSame('/dashboard', $res->headers()['Location']);
    }
}
