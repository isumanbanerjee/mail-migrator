<?php
declare(strict_types=1);

namespace EmailMigration\Ledger;

interface LedgerInterface
{
    public function init(): void;
    public function recordFolder(string $sourceFolder, string $destFolder, int $uidValidity): void;
    public function getFolderUidValidity(string $sourceFolder): ?int;
    public function recordMessage(array $msg): void;
    public function status(string $sourceFolder, int $sourceUid): ?string;
    /** True once any message row exists for this folder (i.e. we have scanned it before). */
    public function folderScanned(string $sourceFolder): bool;
    /** Highest source UID recorded for this folder (0 if none) — used to resume incrementally. */
    public function maxProcessedUid(string $sourceFolder): int;
    public function markCopied(string $sourceFolder, int $sourceUid, int $sizeBytes = 0): void;
    public function markSkipped(string $sourceFolder, int $sourceUid): void;
    public function markFailed(string $sourceFolder, int $sourceUid, string $error): void;
    /** @return array{copied:int,skipped:int,failed:int,pending:int} */
    public function summary(): array;
}
