<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class MeteringService
{
    public function __construct(private PDO $pdo) {}

    public function jobsUsed(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM jobs WHERE user_id=:u');
        $stmt->execute([':u' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function emailsUsed(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM job_ledger_messages m
             JOIN jobs j ON j.id = m.job_id
             WHERE j.user_id = :u AND m.status = 'copied'"
        );
        $stmt->execute([':u' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
