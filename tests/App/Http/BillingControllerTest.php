<?php
declare(strict_types=1);

namespace App\Tests\Http;

use App\Tests\FeatureTestCase;

final class BillingControllerTest extends FeatureTestCase
{
    public function test_billing_page_renders_when_logged_in(): void
    {
        $this->registerAndLogin();
        $res = $this->get('/billing');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsStringIgnoringCase('usage', $res->body());
    }

    public function test_billing_requires_login(): void
    {
        $this->assertSame(302, $this->get('/billing')->status());
    }
}
