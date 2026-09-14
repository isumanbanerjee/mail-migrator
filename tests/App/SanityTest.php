<?php
declare(strict_types=1);

/**
 * App dependencies sanity test.
 *
 * Installed library versions:
 * - defuse/php-encryption v2.4.0
 * - nikic/fast-route 1.3.1
 * - vlucas/phpdotenv v5.7.0
 */

namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class SanityTest extends TestCase
{
    public function test_app_autoload_and_deps_available(): void
    {
        $this->assertTrue(class_exists(\Defuse\Crypto\Key::class));
        $this->assertTrue(class_exists(\FastRoute\RouteCollector::class));
        $this->assertTrue(class_exists(\Dotenv\Dotenv::class));
    }
}
