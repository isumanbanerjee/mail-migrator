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
        private int $maxMessageBytes = 0,
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
            'subject' => ($header['subject'] ?? '') !== '' ? (string) $header['subject'] : null,
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

        $raw = $this->buildRaw($reader, $srcFolder, $uid, $header);
        if ($raw === '') {
            $reason = 'empty source body (fetch failed or message unreadable)';
            $this->ledger->markFailed($srcFolder, $uid, $reason);
            $this->logger->error("Fetch failed: {$srcFolder} uid {$uid}: {$reason}");
            return 'failed';
        }

        // Skip oversized messages: a large APPEND is where the destination (Gmail) tends
        // to stall, and it can't accept a message over its limit anyway. Fail fast instead
        // of letting one giant email hang or block the queue.
        if ($this->maxMessageBytes > 0 && strlen($raw) > $this->maxMessageBytes) {
            $mb = round(strlen($raw) / 1048576, 1);
            $reason = "message too large ({$mb} MB) — exceeds the destination size limit";
            $this->ledger->markFailed($srcFolder, $uid, $reason);
            $this->logger->error("Skipped: {$srcFolder} uid {$uid}: {$reason}");
            return 'failed';
        }

        $ok = $this->writer->append(
            $destFolder, $raw, (array) ($header['flags'] ?? []), (string) $header['internal_date']
        );

        if ($ok) {
            $this->ledger->markCopied($srcFolder, $uid, strlen($raw));
            return 'copied';
        }

        $reason = $this->writer->lastError() ?? 'append failed';
        $this->ledger->markFailed($srcFolder, $uid, $reason);
        $this->logger->error("Append failed: {$srcFolder} uid {$uid}: {$reason}");
        return 'failed';
    }

    /**
     * Reconstruct the full RFC822 message. When discovery captured the raw header we
     * only need the body (one fetch instead of re-fetching the whole message); if the
     * body-only fetch is unavailable or empty we fall back to a full fetchRaw().
     */
    private function buildRaw(MailboxReaderInterface $reader, string $srcFolder, int $uid, array $header): string
    {
        $rawHeader = (string) ($header['raw_header'] ?? '');
        if ($rawHeader !== '') {
            $body = $reader->fetchBody($srcFolder, $uid);
            if ($body !== '') {
                return rtrim($rawHeader, "\r\n") . "\r\n\r\n" . $body;
            }
        }
        return $reader->fetchRaw($srcFolder, $uid);
    }
}
