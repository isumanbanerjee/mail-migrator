<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\JobRepository;
use PDO;

final class JobClaimer
{
    public function __construct(
        private PDO $pdo,
        private JobRepository $jobs,
        private int $maxConcurrent = 1,
        private int $staleSeconds = 900,
    ) {}

    public function claim(string $workerId): ?array
    {
        $staleBefore = date('Y-m-d H:i:s', time() - $this->staleSeconds);
        if ($this->jobs->countRunning($staleBefore) >= $this->maxConcurrent) {
            return null;
        }
        $candidate = $this->jobs->nextClaimable($staleBefore);
        if ($candidate === null) {
            return null;
        }
        // Guarded atomic claim: only succeeds if still claimable.
        $stmt = $this->pdo->prepare(
            "UPDATE jobs SET state='running', worker_id=:w, locked_at=:t, updated_at=:t
             WHERE id=:id AND (state='queued' OR (state='running' AND (locked_at IS NULL OR locked_at < :sb)))"
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute([':w' => $workerId, ':t' => $now, ':id' => (int) $candidate['id'], ':sb' => $staleBefore]);
        if ($stmt->rowCount() < 1) {
            return null; // lost the race
        }
        return $this->jobs->find((int) $candidate['id'], (int) $candidate['user_id']);
    }
}
