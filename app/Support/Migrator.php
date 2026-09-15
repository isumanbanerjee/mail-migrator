<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir) {}

    /** @return array<int,string> filenames applied this run */
    public function migrate(): array
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(255) PRIMARY KEY, applied_at DATETIME)');

        $done = $this->pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            $sql = $this->render((string) file_get_contents($file), $driver);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                $this->pdo->exec($stmt);
            }
            $ins = $this->pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (:n, :t)');
            $ins->execute([':n' => $name, ':t' => date('Y-m-d H:i:s')]);
            $applied[] = $name;
        }
        return $applied;
    }

    private function render(string $sql, string $driver): string
    {
        if ($driver === 'sqlite') {
            // SQLite has no MODIFY COLUMN, but its dynamic typing already lets a
            // DATETIME-declared column hold the IMAP date string, so this is a no-op.
            return str_replace(
                ['{{PK}}', '{{ENGINE}}', '{{ALTER_INTERNAL_DATE_TO_VARCHAR}}'],
                ['INTEGER PRIMARY KEY AUTOINCREMENT', '', ''],
                $sql
            );
        }
        return str_replace(
            ['{{PK}}', '{{ENGINE}}', '{{ALTER_INTERNAL_DATE_TO_VARCHAR}}'],
            ['BIGINT AUTO_INCREMENT PRIMARY KEY', 'ENGINE=InnoDB', 'ALTER TABLE job_ledger_messages MODIFY internal_date VARCHAR(64) NULL'],
            $sql
        );
    }
}
