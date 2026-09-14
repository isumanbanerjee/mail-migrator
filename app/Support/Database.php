<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

final class Database
{
    public static function make(array $c): PDO
    {
        $driver = $c['driver'] ?? 'mysql';
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . ($c['database'] ?? ':memory:');
            $pdo = new PDO($dsn);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['host'] ?? '127.0.0.1', (int) ($c['port'] ?? 3306), $c['database'] ?? '');
            $pdo = new PDO($dsn, $c['username'] ?? '', $c['password'] ?? '');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }
}
