<?php
declare(strict_types=1);

namespace EmailMigration\Ledger;

use PDO;

final class SqliteLedger implements LedgerInterface
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function fromFile(string $path): self
    {
        return new self(new PDO('sqlite:' . $path));
    }

    public function init(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS ledger_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_folder TEXT NOT NULL,
                dest_folder TEXT NOT NULL,
                source_uid INTEGER NOT NULL,
                message_id TEXT,
                subject TEXT,
                dedupe_hash TEXT,
                size_bytes INTEGER,
                internal_date TEXT,
                flags TEXT,
                status TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                error TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(source_folder, source_uid)
            );
        SQL);
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS ledger_folders (
                source_folder TEXT PRIMARY KEY,
                dest_folder TEXT,
                uidvalidity INTEGER,
                scanned_at TEXT
            );
        SQL);
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_status ON ledger_messages(status);');
    }

    public function recordFolder(string $sourceFolder, string $destFolder, int $uidValidity): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO ledger_folders (source_folder, dest_folder, uidvalidity, scanned_at)
            VALUES (:s, :d, :u, :t)
            ON CONFLICT(source_folder) DO UPDATE SET
                dest_folder = excluded.dest_folder,
                uidvalidity = excluded.uidvalidity,
                scanned_at = excluded.scanned_at
        SQL);
        $stmt->execute([':s' => $sourceFolder, ':d' => $destFolder, ':u' => $uidValidity, ':t' => $this->now()]);
    }

    public function getFolderUidValidity(string $sourceFolder): ?int
    {
        $stmt = $this->pdo->prepare('SELECT uidvalidity FROM ledger_folders WHERE source_folder = :s');
        $stmt->execute([':s' => $sourceFolder]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (int) $v;
    }

    public function recordMessage(array $msg): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO ledger_messages
                (source_folder, dest_folder, source_uid, message_id, subject, dedupe_hash, size_bytes,
                 internal_date, flags, status, created_at, updated_at)
            VALUES (:sf,:df,:uid,:mid,:subj,:dh,:sz,:idt,:fl,:st,:ca,:ua)
            ON CONFLICT(source_folder, source_uid) DO NOTHING
        SQL);
        $stmt->execute([
            ':sf' => $msg['source_folder'], ':df' => $msg['dest_folder'], ':uid' => (int) $msg['source_uid'],
            ':mid' => $msg['message_id'] ?? null, ':subj' => $msg['subject'] ?? null, ':dh' => $msg['dedupe_hash'] ?? null,
            ':sz' => (int) ($msg['size_bytes'] ?? 0), ':idt' => $msg['internal_date'] ?? null,
            ':fl' => json_encode($msg['flags'] ?? []), ':st' => $msg['status'] ?? 'pending',
            ':ca' => $this->now(), ':ua' => $this->now(),
        ]);
    }

    public function status(string $sourceFolder, int $sourceUid): ?string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM ledger_messages WHERE source_folder = :sf AND source_uid = :uid');
        $stmt->execute([':sf' => $sourceFolder, ':uid' => $sourceUid]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    public function folderScanned(string $sourceFolder): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ledger_messages WHERE source_folder = :sf LIMIT 1');
        $stmt->execute([':sf' => $sourceFolder]);
        return $stmt->fetchColumn() !== false;
    }

    public function markCopied(string $sourceFolder, int $sourceUid, int $sizeBytes = 0): void
    {
        $this->setStatus($sourceFolder, $sourceUid, 'copied', null, $sizeBytes);
    }

    public function markSkipped(string $sourceFolder, int $sourceUid): void
    {
        $this->setStatus($sourceFolder, $sourceUid, 'skipped', null);
    }

    public function markFailed(string $sourceFolder, int $sourceUid, string $error): void
    {
        $this->setStatus($sourceFolder, $sourceUid, 'failed', $error);
    }

    public function summary(): array
    {
        $rows = $this->pdo->query('SELECT status, COUNT(*) c FROM ledger_messages GROUP BY status')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'copied' => (int) ($rows['copied'] ?? 0),
            'skipped' => (int) ($rows['skipped'] ?? 0),
            'failed' => (int) ($rows['failed'] ?? 0),
            'pending' => (int) ($rows['pending'] ?? 0),
        ];
    }

    private function setStatus(string $sf, int $uid, string $status, ?string $error, int $sizeBytes = 0): void
    {
        if ($sizeBytes > 0) {
            $stmt = $this->pdo->prepare(<<<SQL
                UPDATE ledger_messages
                SET status = :st, error = :er, size_bytes = :sz, attempts = attempts + 1, updated_at = :ua
                WHERE source_folder = :sf AND source_uid = :uid
            SQL);
            $stmt->execute([':st' => $status, ':er' => $error, ':sz' => $sizeBytes, ':ua' => $this->now(), ':sf' => $sf, ':uid' => $uid]);
            return;
        }
        $stmt = $this->pdo->prepare(<<<SQL
            UPDATE ledger_messages
            SET status = :st, error = :er, attempts = attempts + 1, updated_at = :ua
            WHERE source_folder = :sf AND source_uid = :uid
        SQL);
        $stmt->execute([':st' => $status, ':er' => $error, ':ua' => $this->now(), ':sf' => $sf, ':uid' => $uid]);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
