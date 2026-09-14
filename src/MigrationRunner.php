<?php
declare(strict_types=1);

namespace EmailMigration;

use EmailMigration\Ledger\LedgerInterface;
use EmailMigration\Mailbox\MailboxReaderInterface;
use EmailMigration\Mailbox\MailboxWriterInterface;
use EmailMigration\Support\Logger;

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
            $this->ledger->recordFolder($srcFolder, $destFolder, $this->reader->folderUidValidity($srcFolder));

            if (!$dryRun) {
                $this->writer->ensureFolder($destFolder);
            }
            // Reading existing message-ids is read-only and safe in dry-run too; without it,
            // dry-run over-counts would_copy against a destination that already has mail.
            $destIndex = $this->writer->existingMessageIds($destFolder);

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
            });
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
