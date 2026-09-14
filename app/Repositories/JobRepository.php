<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class JobRepository
{
    private const FIELDS = [
        'name', 'mode', 'source_host', 'source_port', 'source_encryption',
        'source_username_enc', 'source_password_enc', 'dest_host', 'dest_port',
        'dest_encryption', 'dest_username_enc', 'dest_password_enc', 'options',
    ];

    public function __construct(private PDO $pdo) {}

    public function create(int $userId, array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $cols = array_merge(['user_id', 'state'], self::FIELDS, ['created_at', 'updated_at']);
        $place = array_map(static fn($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO jobs (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
        $params = [':user_id' => $userId, ':state' => 'draft', ':created_at' => $now, ':updated_at' => $now];
        foreach (self::FIELDS as $f) {
            $params[':' . $f] = $data[$f] ?? '';
        }
        $this->pdo->prepare($sql)->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $set = implode(', ', array_map(static fn($f) => "{$f} = :{$f}", self::FIELDS));
        $sql = "UPDATE jobs SET {$set}, updated_at = :updated_at WHERE id = :id AND user_id = :user_id";
        $params = [':id' => $id, ':user_id' => $userId, ':updated_at' => date('Y-m-d H:i:s')];
        foreach (self::FIELDS as $f) {
            $params[':' . $f] = $data[$f] ?? '';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM jobs WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jobs WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jobs WHERE user_id = :user_id ORDER BY id DESC');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function transition(int $id, int $userId, string $state): bool
    {
        $stmt = $this->pdo->prepare('UPDATE jobs SET state = :s, updated_at = :u WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':s' => $state, ':u' => date('Y-m-d H:i:s'), ':id' => $id, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }
}
