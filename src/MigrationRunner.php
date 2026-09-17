<?php
declare(strict_types=1);

namespace EmailMigration;

use EmailMigration\Ledger\LedgerInterface;
use EmailMigration\Mailbox\MailboxReaderInterface;
use EmailMigration\Mailbox\MailboxWriterInterface;
use EmailMigration\Support\Logger;
use EmailMigration\Support\TimeoutException;

final class MigrationRunner
{
    private int $copied = 0;
    private int $skipped = 0;
    private int $wouldCopy = 0;
    private int $failed = 0;

    public function __construct(
        private MailboxReaderInterface $reader,
        private MailboxWriterInterface $writer,
        private LedgerInterface $ledger,
        private FolderMapper $mapper,
        private MessageMigrator $migrator,
        private Logger $logger,
        private array $options,
    ) {}

    /** @param callable(array):void|null $progress */
    public function run(?callable $progress = null): array
    {
        $this->ledger->init();
        $dryRun = (bool) ($this->options['dry_run'] ?? false);
        $only = $this->options['only_folder'] ?? null;
        $sinceTs = null;
        if (isset($this->options['since']) && $this->options['since']) {
            $sinceTs = strtotime((string) $this->options['since']);
            if ($sinceTs === false) {
                throw new \InvalidArgumentException(
                    "Invalid --since date: {$this->options['since']}"
                );
            }
        }
        $limit = $this->options['limit'] ?? null;
        $throttle = (int) ($this->options['throttle_ms'] ?? 0);

        $folders = $only !== null ? [$only] : $this->reader->listFolders();
        $folderCount = 0;

        foreach ($folders as $srcFolder) {
            $folderCount++;
            $destFolder = $this->mapper->map($srcFolder);
            // Any IMAP op below (folder status, create, destination scan, header scan) can
            // time out on a hung SSL read. Keep it per-folder: a timeout skips this folder
            // for now and the next run resumes it — it never fails the whole job.
            try {
                // Check before recordFolder/eachHeader touch the ledger for this folder.
                $alreadyScanned = $this->ledger->folderScanned($srcFolder);
                $this->ledger->recordFolder($srcFolder, $destFolder, $this->reader->folderUidValidity($srcFolder));

                if (!$dryRun) {
                    $this->writer->ensureFolder($destFolder);
                }
                // Scanning the whole destination for existing message-ids is expensive and only
                // needed the first time we touch a folder (to dedupe against mail already there).
                // On resumed runs the ledger already tracks what we copied, so skip the rescan.
                $destIndex = $alreadyScanned ? [] : $this->writer->existingMessageIds($destFolder);

                // Resume from the highest UID already recorded so we don't re-read the whole
                // folder on every run (which stalled large folders before they could advance).
                $sinceUid = $this->ledger->maxProcessedUid($srcFolder);
                $this->reader->eachHeader($srcFolder, function (array $header) use (
                    $srcFolder, $destFolder, $destIndex, $dryRun, $sinceTs, $limit, $throttle, $progress
                ) {
                if ($limit !== null && ($this->copied + $this->wouldCopy) >= $limit) {
                    return;
                }
                if ($sinceTs !== null) {
                    $ts = strtotime((string) $header['internal_date']);
                    if ($ts !== false && $ts < $sinceTs) {
                        return;
                    }
                }

                $action = $this->migrator->migrateOne(
                    $this->reader, $srcFolder, $destFolder, $header, $destIndex, $dryRun
                );
                $this->tally($action);

                if ($action === 'copied' && $throttle > 0) {
                    usleep($throttle * 1000);
                }
                if ($progress !== null) {
                    $progress([
                        'folder' => $srcFolder, 'copied' => $this->copied,
                        'skipped' => $this->skipped, 'failed' => $this->failed,
                        'would_copy' => $this->wouldCopy,
                    ]);
                }
                }, $sinceUid);
            } catch (TimeoutException $e) {
                // A hung IMAP op on this folder — don't fail the whole job. Skip to the next
                // folder; the next run resumes this one from the last recorded UID.
                $this->logger->warn("Timed out on folder {$srcFolder}; resuming next run: " . $e->getMessage());
                continue;
            }
        }

        return [
            'copied' => $this->copied, 'skipped' => $this->skipped,
            'would_copy' => $this->wouldCopy, 'failed' => $this->failed,
            'folders' => $folderCount,
        ];
    }

    private function tally(string $action): void
    {
        match ($action) {
            'copied' => $this->copied++,
            'skipped' => $this->skipped++,
            'would_copy' => $this->wouldCopy++,
            'failed' => $this->failed++,
            default => null,
        };
    }
}
