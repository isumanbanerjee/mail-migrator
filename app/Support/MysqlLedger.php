<?php
declare(strict_types=1);

namespace App\Support;

use MailMigrator\Ledger\LedgerInterface;
use PDO;

final class MysqlLedger implements LedgerInterface
{
    public function __construct(private PDO $pdo, private int $jobId) {}

    public function init(): void { /* tables created by migration 004 */ }

    public function recordFolder(string $sourceFolder, string $destFolder, int $uidValidity): void
    {
        $existing = $this->getFolderUidValidity($sourceFolder);
        if ($existing === null) {
            $stmt = $this->pdo->prepare('INSERT INTO job_ledger_folders (job_id, source_folder, dest_folder, uidvalidity, scanned_at) VALUES (:j,:s,:d,:u,:t)');
        } else {
            $stmt = $this->pdo->prepare('UPDATE job_ledger_folders SET dest_folder=:d, uidvalidity=:u, scanned_at=:t WHERE job_id=:j AND source_folder=:s');
        }
        $stmt->execute([':j' => $this->jobId, ':s' => $sourceFolder, ':d' => $destFolder, ':u' => $uidValidity, ':t' => date('Y-m-d H:i:s')]);
    }

    public function getFolderUidValidity(string $sourceFolder): ?int
    {
        $stmt = $this->pdo->prepare('SELECT uidvalidity FROM job_ledger_folders WHERE job_id=:j AND source_folder=:s');
        $stmt->execute([':j' => $this->jobId, ':s' => $sourceFolder]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (int) $v;
    }

    public function recordMessage(array $msg): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM job_ledger_messages WHERE job_id=:j AND source_folder=:sf AND source_uid=:uid');
        $stmt->execute([':j' => $this->jobId, ':sf' => $msg['source_folder'], ':uid' => (int) $msg['source_uid']]);
        if ($stmt->fetchColumn() !== false) {
            return; // idempotent: never clobber an existing row
        }
        $ins = $this->pdo->prepare('INSERT INTO job_ledger_messages
            (job_id, source_folder, dest_folder, source_uid, message_id, subject, dedupe_hash, size_bytes, internal_date, flags, status, created_at, updated_at)
            VALUES (:j,:sf,:df,:uid,:mid,:subj,:dh,:sz,:idt,:fl,:st,:ca,:ua)');
        $now = date('Y-m-d H:i:s');
        $ins->execute([
            ':j' => $this->jobId, ':sf' => $msg['source_folder'], ':df' => $msg['dest_folder'], ':uid' => (int) $msg['source_uid'],
            ':mid' => $msg['message_id'] ?? null, ':subj' => $msg['subject'] ?? null, ':dh' => $msg['dedupe_hash'] ?? null, ':sz' => (int) ($msg['size_bytes'] ?? 0),
            ':idt' => $msg['internal_date'] ?? null, ':fl' => json_encode($msg['flags'] ?? []), ':st' => $msg['status'] ?? 'pending',
            ':ca' => $now, ':ua' => $now,
        ]);
    }

    public function status(string $sourceFolder, int $sourceUid): ?string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM job_ledger_messages WHERE job_id=:j AND source_folder=:sf AND source_uid=:uid');
        $stmt->execute([':j' => $this->jobId, ':sf' => $sourceFolder, ':uid' => $sourceUid]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    public function folderScanned(string $sourceFolder): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM job_ledger_messages WHERE job_id = :j AND source_folder = :sf LIMIT 1');
        $stmt->execute([':j' => $this->jobId, ':sf' => $sourceFolder]);
        return $stmt->fetchColumn() !== false;
    }

    public function maxProcessedUid(string $sourceFolder): int
    {
        $stmt = $this->pdo->prepare('SELECT MAX(source_uid) FROM job_ledger_messages WHERE job_id = :j AND source_folder = :sf');
        $stmt->execute([':j' => $this->jobId, ':sf' => $sourceFolder]);
        return (int) $stmt->fetchColumn();
    }

    public function markCopied(string $sf, int $uid, int $sizeBytes = 0): void { $this->setStatus($sf, $uid, 'copied', null, $sizeBytes); }
    public function markSkipped(string $sf, int $uid): void { $this->setStatus($sf, $uid, 'skipped', null); }
    public function markFailed(string $sf, int $uid, string $error): void { $this->setStatus($sf, $uid, 'failed', $error); }

    public function summary(): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) c FROM job_ledger_messages WHERE job_id=:j GROUP BY status');
        $stmt->execute([':j' => $this->jobId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['copied' => (int) ($rows['copied'] ?? 0), 'skipped' => (int) ($rows['skipped'] ?? 0),
                'failed' => (int) ($rows['failed'] ?? 0), 'pending' => (int) ($rows['pending'] ?? 0)];
    }

    private function setStatus(string $sf, int $uid, string $status, ?string $error, int $sizeBytes = 0): void
    {
        if ($sizeBytes > 0) {
            $stmt = $this->pdo->prepare('UPDATE job_ledger_messages SET status=:st, error=:er, size_bytes=:sz, attempts=attempts+1, updated_at=:ua WHERE job_id=:j AND source_folder=:sf AND source_uid=:uid');
            $stmt->execute([':st' => $status, ':er' => $error, ':sz' => $sizeBytes, ':ua' => date('Y-m-d H:i:s'), ':j' => $this->jobId, ':sf' => $sf, ':uid' => $uid]);
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE job_ledger_messages SET status=:st, error=:er, attempts=attempts+1, updated_at=:ua WHERE job_id=:j AND source_folder=:sf AND source_uid=:uid');
        $stmt->execute([':st' => $status, ':er' => $error, ':ua' => date('Y-m-d H:i:s'), ':j' => $this->jobId, ':sf' => $sf, ':uid' => $uid]);
    }
}
