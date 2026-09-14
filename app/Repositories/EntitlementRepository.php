<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EntitlementRepository
{
    public function __construct(private PDO $pdo) {}

    public function for(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM entitlements WHERE user_id=:u');
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row === false
            ? ['user_id' => $userId, 'unlimited' => 0, 'credits' => 0, 'subscription_until' => null]
            : $row;
    }

    private function ensure(int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM entitlements WHERE user_id=:u');
        $stmt->execute([':u' => $userId]);
        if ($stmt->fetchColumn() === false) {
            $this->pdo->prepare('INSERT INTO entitlements (user_id, updated_at) VALUES (:u,:t)')
                ->execute([':u' => $userId, ':t' => date('Y-m-d H:i:s')]);
        }
    }

    public function grantUnlimited(int $userId): void
    {
        $this->ensure($userId);
        $this->pdo->prepare('UPDATE entitlements SET unlimited=1, updated_at=:t WHERE user_id=:u')
            ->execute([':t' => date('Y-m-d H:i:s'), ':u' => $userId]);
    }

    public function addCredits(int $userId, int $n): void
    {
        $this->ensure($userId);
        $this->pdo->prepare('UPDATE entitlements SET credits=credits+:n, updated_at=:t WHERE user_id=:u')
            ->execute([':n' => $n, ':t' => date('Y-m-d H:i:s'), ':u' => $userId]);
    }

    public function deductCredits(int $userId, int $n): void
    {
        $this->ensure($userId);
        $current = (int) $this->for($userId)['credits'];
        $next = max(0, $current - $n);
        $this->pdo->prepare('UPDATE entitlements SET credits=:c, updated_at=:t WHERE user_id=:u')
            ->execute([':c' => $next, ':t' => date('Y-m-d H:i:s'), ':u' => $userId]);
    }

    public function extendSubscription(int $userId, string $until): void
    {
        $this->ensure($userId);
        $this->pdo->prepare('UPDATE entitlements SET subscription_until=:d, updated_at=:t WHERE user_id=:u')
            ->execute([':d' => $until, ':t' => date('Y-m-d H:i:s'), ':u' => $userId]);
    }
}
