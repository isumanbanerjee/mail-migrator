<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class PaymentRepository
{
    public function __construct(private PDO $pdo) {}

    public function record(array $p): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO payments (user_id,gateway,product,gateway_ref,amount,currency,status,created_at,updated_at)
            VALUES (:u,:g,:pr,:ref,:a,:c,:s,:ca,:ua)');
        $stmt->execute([':u' => (int) $p['user_id'], ':g' => $p['gateway'], ':pr' => $p['product'], ':ref' => $p['gateway_ref'] ?? null,
            ':a' => (string) $p['amount'], ':c' => $p['currency'], ':s' => $p['status'] ?? 'created', ':ca' => $now, ':ua' => $now]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findByRef(string $gateway, string $ref): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE gateway=:g AND gateway_ref=:r');
        $stmt->execute([':g' => $gateway, ':r' => $ref]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function markPaid(int $id): void
    {
        $this->pdo->prepare('UPDATE payments SET status=:s, updated_at=:u WHERE id=:id')
            ->execute([':s' => 'paid', ':u' => date('Y-m-d H:i:s'), ':id' => $id]);
    }
}
