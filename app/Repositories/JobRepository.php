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

    public function saveProgress(int $id, array $p): void
    {
        $stmt = $this->pdo->prepare('UPDATE jobs SET total_messages=:t, copied=:c, skipped=:s, failed=:f, current_folder=:cf, percent=:p, updated_at=:u WHERE id=:id');
        $stmt->execute([
            ':t' => (int) ($p['total_messages'] ?? 0), ':c' => (int) ($p['copied'] ?? 0),
            ':s' => (int) ($p['skipped'] ?? 0), ':f' => (int) ($p['failed'] ?? 0),
            ':cf' => $p['current_folder'] ?? null, ':p' => (int) ($p['percent'] ?? 0),
            ':u' => date('Y-m-d H:i:s'), ':id' => $id,
        ]);
    }

    public function currentState(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT state FROM jobs WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    public function systemTransition(int $id, string $state, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare('UPDATE jobs SET state=:s, last_error=:e, updated_at=:u WHERE id=:id');
        $stmt->execute([':s' => $state, ':e' => $error, ':u' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public function releaseLock(int $id): void
    {
        $this->pdo->prepare('UPDATE jobs SET worker_id=NULL, locked_at=NULL, updated_at=:u WHERE id=:id')
            ->execute([':u' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public function countRunning(string $staleBefore): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM jobs WHERE state='running' AND locked_at IS NOT NULL AND locked_at >= :sb");
        $stmt->execute([':sb' => $staleBefore]);
        return (int) $stmt->fetchColumn();
    }

    public function nextClaimable(string $staleBefore): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM jobs WHERE state='queued' OR (state='running' AND (locked_at IS NULL OR locked_at < :sb)) ORDER BY id ASC LIMIT 1");
        $stmt->execute([':sb' => $staleBefore]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Per-status counts from the ledger for one job.
     * @return array{copied:int,skipped:int,failed:int,pending:int,total:int}
     */
    public function ledgerCounts(int $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) c FROM job_ledger_messages WHERE job_id = :j GROUP BY status');
        $stmt->execute([':j' => $jobId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $copied = (int) ($rows['copied'] ?? 0);
        $skipped = (int) ($rows['skipped'] ?? 0);
        $failed = (int) ($rows['failed'] ?? 0);
        $pending = (int) ($rows['pending'] ?? 0);
        return [
            'copied' => $copied, 'skipped' => $skipped, 'failed' => $failed, 'pending' => $pending,
            'total' => $copied + $skipped + $failed + $pending,
        ];
    }

    /**
     * A page of ledger messages for one job, most-recently-updated first.
     * @param string|null $status one of copied|skipped|failed|pending, or null for all
     * @return array<int,array<string,mixed>>
     */
    public function ledgerMessages(int $jobId, ?string $status, int $limit, int $offset): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);
        $where = 'job_id = :j';
        $params = [':j' => $jobId];
        if (in_array($status, ['copied', 'skipped', 'failed', 'pending'], true)) {
            $where .= ' AND status = :st';
            $params[':st'] = $status;
        }
        $sql = "SELECT source_folder, source_uid, message_id, subject, internal_date, size_bytes, flags, status, attempts, error, updated_at
                FROM job_ledger_messages WHERE {$where}
                ORDER BY updated_at DESC, id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
