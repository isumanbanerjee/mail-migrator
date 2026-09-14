<?php
declare(strict_types=1);

/**
 * One-shot schema migration runner for shared hosting.
 *
 * Usage (from the repo root, after composer install and .env are in place):
 *   php bin/migrate.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Support\Config;
use App\Support\Database;
use App\Support\Migrator;

$basePath = dirname(__DIR__);
$config = Config::load($basePath);
$pdo = Database::make($config->db());
$migrator = new Migrator($pdo, $basePath . '/database/migrations');

$applied = $migrator->migrate();

if ($applied === []) {
    fwrite(STDOUT, "No pending migrations. Database is up to date.\n");
    exit(0);
}

fwrite(STDOUT, "Applied migrations:\n");
foreach ($applied as $name) {
    fwrite(STDOUT, "  - {$name}\n");
}
exit(0);
