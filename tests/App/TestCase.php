<?php
declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function migrationsDir(): string
    {
        return dirname(__DIR__, 2) . '/database/migrations';
    }
}
