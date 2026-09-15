<?php
declare(strict_types=1);

namespace EmailMigration;

use EmailMigration\Ledger\LedgerInterface;
use EmailMigration\Mailbox\MailboxReaderInterface;
use EmailMigration\Mailbox\MailboxWriterInterface;
use EmailMigration\Support\Logger;

final class MessageMigrator
{
    public function __construct(
        private MailboxWriterInterface $writer,
        private LedgerInterface $ledger,
        private Logger $logger,
    ) {}

    /**
     * @param array $header keys: uid,message_id,from,subject,size,internal_date,flags
     * @param array<int,string> $destIndex existing destination message-ids
     * @return string 'copied'|'skipped'|'would_copy'|'failed'
     */
    public function migrateOne(
        MailboxReaderInterface $reader,
        string $srcFolder,
        string $destFolder,
        array $header,
        array $destIndex,
        bool $dryRun,
    ): string {
        $uid = (int) $header['uid'];

        if ($this->ledger->status($srcFolder, $uid) === 'copied') {
            return 'skipped';
        }

        $messageId = (string) ($header['message_id'] ?? '');
        $this->ledger->recordMessage([
            'source_folder' => $srcFolder,
            'dest_folder' => $destFolder,
            'source_uid' => $uid,
            'message_id' => $messageId !== '' ? $messageId : null,
            'dedupe_hash' => $messageId === '' ? Dedupe::hash(
                (string) $header['internal_date'], (string) $header['from'],
                (string) $header['subject'], (int) $header['size']
            ) : null,
            'size_bytes' => (int) ($header['size'] ?? 0),
            'internal_date' => (string) ($header['internal_date'] ?? ''),
            'flags' => $header['flags'] ?? [],
            'status' => 'pending',
        ]);

        if ($messageId !== '' && in_array($messageId, $destIndex, true)) {
            $this->ledger->markSkipped($srcFolder, $uid);
            return 'skipped';
        }

        if ($dryRun) {
            return 'would_copy';
        }

        $raw = $reader->fetchRaw($srcFolder, $uid);
        if ($raw === '') {
            $reason = 'empty source body (fetch failed or message unreadable)';
            $this->ledger->markFailed($srcFolder, $uid, $reason);
            $this->logger->error("Fetch failed: {$srcFolder} uid {$uid}: {$reason}");
            return 'failed';
        }

        $ok = $this->writer->append(
            $destFolder, $raw, (array) ($header['flags'] ?? []), (string) $header['internal_date']
        );

        if ($ok) {
            $this->ledger->markCopied($srcFolder, $uid);
            return 'copied';
        }

        $reason = $this->writer->lastError() ?? 'append failed';
        $this->ledger->markFailed($srcFolder, $uid, $reason);
        $this->logger->error("Append failed: {$srcFolder} uid {$uid}: {$reason}");
        return 'failed';
    }
}
