<?php
declare(strict_types=1);

namespace EmailMigration\Imap;

use EmailMigration\Mailbox\MailboxReaderInterface;
use EmailMigration\Support\MimeHeader;
use EmailMigration\Support\Timeout;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\IMAP;

final class WebklexReader implements MailboxReaderInterface
{
    /** Folder currently EXAMINEd on the connection, so fetchBody() selects at most once per folder. */
    private ?string $bodyFolder = null;

    public function __construct(private Client $client, private int $opTimeout = 120) {}

    public function listFolders(): array
    {
        return Timeout::run($this->opTimeout, function (): array {
            $paths = [];
            foreach ($this->client->getFolders(false) as $folder) {
                $paths[] = $folder->path;
            }
            return $paths;
        });
    }

    public function folderUidValidity(string $folder): int
    {
        $f = $this->getFolder($folder);
        $status = Timeout::run($this->opTimeout, fn () => $f->getStatus());
        return (int) ($status['uidvalidity'] ?? 0);
    }

    public function eachHeader(string $folder, callable $cb, int $sinceUid = 0): void
    {
        $f = $this->getFolder($folder);

        // 1) List the UIDs we still need with a cheap SEARCH (no header fetch) so that one
        //    bad message can't block getting the list. Resume from sinceUid.
        $criteria = $sinceUid > 0 ? 'UID ' . ($sinceUid + 1) . ':*' : 'ALL';
        $uids = [];
        try {
            $uids = Timeout::run($this->opTimeout, function () use ($criteria) {
                return (array) $this->client->getConnection()->search([$criteria], IMAP::ST_UID)->validatedData();
            });
        } catch (Throwable) {
            return; // couldn't even list; next run will retry
        }
        $uids = array_values(array_unique(array_map('intval', $uids)));
        sort($uids);
        if ($sinceUid > 0) {
            $uids = array_values(array_filter($uids, static fn ($u) => $u > $sinceUid));
        }

        // 2) Fetch headers in batches; if a batch times out, retry it one message at a time
        //    so a single bad message is isolated (yielded as fetch_failed) instead of
        //    blocking everything after it.
        foreach (array_chunk($uids, 100) as $batch) {
            try {
                $messages = Timeout::run($this->opTimeout, function () use ($f, $batch) {
                    return $f->query()->whereUidIn($batch)->setFetchBody(false)->setFetchFlags(true)->get();
                });
                foreach ($messages as $message) {
                    $cb($this->headerArray($message));
                }
            } catch (Throwable) {
                foreach ($batch as $uid) {
                    try {
                        $message = Timeout::run($this->opTimeout, function () use ($f, $uid) {
                            return $f->query()->whereUid((string) $uid)->setFetchBody(false)->setFetchFlags(true)->get()->first();
                        });
                        $cb($message !== null ? $this->headerArray($message) : $this->failedHeader((int) $uid));
                    } catch (Throwable) {
                        $cb($this->failedHeader((int) $uid));
                    }
                }
            }
        }
    }

    private function headerArray(object $message): array
    {
        $dateAttr = $message->getDate();
        $internalDate = $dateAttr->has() ? $dateAttr->toDate()->format('d-M-Y H:i:s O') : '';
        $from = $message->getFrom();
        $fromMail = $from[0]->mail ?? '';
        $messageId = (string) $message->getMessageId();
        // Only pay for the RFC822.SIZE round-trip when we'll actually need it
        // (the sha256 dedupe fallback, used only when there is no Message-ID).
        $size = $messageId === '' ? (int) ($message->getSize() ?? 0) : 0;
        $header = $message->getHeader();

        return [
            'uid' => (int) $message->getUid(),
            'message_id' => $messageId,
            'from' => (string) $fromMail,
            'subject' => MimeHeader::decode((string) $message->getSubject()),
            'size' => $size,
            'internal_date' => $internalDate,
            'flags' => self::normalizeFlags((array) $message->getFlags()->all()),
            'raw_header' => $header !== null ? (string) $header->raw : '',
        ];
    }

    /** A message whose header couldn't be fetched — the migrator records it failed and moves on. */
    private function failedHeader(int $uid): array
    {
        return [
            'uid' => $uid, 'message_id' => '', 'from' => '', 'subject' => '',
            'size' => 0, 'internal_date' => '', 'flags' => [], 'raw_header' => '',
            'fetch_failed' => true,
        ];
    }

    public function fetchRaw(string $folder, int $uid): string
    {
        try {
            $f = $this->getFolder($folder);
            $message = Timeout::run($this->opTimeout, fn () => $f->query()->getMessageByUid($uid));

            // Webklex fetches the header (BODY[HEADER]) and body (BODY[TEXT]) separately.
            // getRawBody() returns ONLY the body, so appending it alone yields a headerless
            // blob the destination rejects ("Unable to parse message"). Reconstruct the full
            // RFC822 message the same way Webklex's own Message::save() does: header + blank
            // line + body.
            $header = $message->getHeader();
            $rawHeader = $header !== null ? rtrim($header->raw, "\r\n") : '';
            $rawBody = (string) $message->getRawBody();
            if ($rawHeader === '' && $rawBody === '') {
                return '';
            }

            return $rawHeader . "\r\n\r\n" . $rawBody;
        } catch (Throwable) {
            return '';
        }
    }

    public function fetchBody(string $folder, int $uid): string
    {
        try {
            // A UID FETCH needs the folder selected. EXAMINE (read-only) once per
            // folder rather than per message so we don't add a round-trip each time.
            if ($this->bodyFolder !== $folder) {
                $target = $this->getFolder($folder);
                Timeout::run($this->opTimeout, fn () => $target->examine());
                $this->bodyFolder = $folder;
            }
            $data = Timeout::run(
                $this->opTimeout,
                fn () => $this->client->getConnection()->content([$uid], 'RFC822', IMAP::ST_UID)->validatedData()
            );

            return $this->extractContent($data, $uid);
        } catch (Throwable) {
            $this->bodyFolder = null; // force re-select next time
            return '';
        }
    }

    /**
     * webklex's low-level content() returns either the body string directly (single-id
     * fast path) or an array keyed by uid (possibly wrapping ['RFC822.TEXT' => body]).
     */
    private function extractContent(mixed $data, int $uid): string
    {
        if (is_string($data)) {
            return $data;
        }
        if (is_array($data)) {
            $node = $data[$uid] ?? (count($data) === 1 ? reset($data) : '');
            if (is_array($node)) {
                $node = $node['RFC822.TEXT'] ?? reset($node);
            }
            return is_string($node) ? $node : '';
        }
        return '';
    }

    /**
     * webklex strips the leading backslash from flags when parsing (\Seen -> Seen),
     * so re-add it for the IMAP system flags. Without this, an APPEND sends "Seen"
     * as a custom keyword instead of the system \Seen flag and the destination loses
     * the read/unread (and answered/flagged/...) state. Custom keywords pass through.
     *
     * @param array<int,string> $flags
     * @return array<int,string>
     */
    public static function normalizeFlags(array $flags): array
    {
        $system = ['seen' => '\\Seen', 'answered' => '\\Answered', 'flagged' => '\\Flagged',
                   'draft' => '\\Draft', 'deleted' => '\\Deleted', 'recent' => '\\Recent'];
        $out = [];
        foreach ($flags as $flag) {
            $bare = strtolower(ltrim((string) $flag, '\\'));
            $out[] = $system[$bare] ?? (string) $flag;
        }
        return array_values(array_unique($out));
    }

    private function getFolder(string $folder): Folder
    {
        $f = $this->client->getFolderByPath($folder);
        if ($f === null) {
            throw new RuntimeException("IMAP folder not found: {$folder}");
        }
        return $f;
    }
}
