<?php
declare(strict_types=1);

return [
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'key' => $_ENV['APP_KEY'] ?? '',
    'url' => $_ENV['APP_URL'] ?? '',
    'db' => [
        'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? '',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
];
