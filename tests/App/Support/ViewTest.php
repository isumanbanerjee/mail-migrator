<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    public function test_renders_within_layout_and_escapes(): void
    {
        $view = new View(__DIR__ . '/../fixtures/views');
        $html = $view->render('hello', ['name' => '<script>x</script>']);
        $this->assertStringContainsString('<div id="layout">', $html);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }
}
