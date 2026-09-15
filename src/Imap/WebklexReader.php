<?php
declare(strict_types=1);

namespace EmailMigration\Imap;

use EmailMigration\Mailbox\MailboxReaderInterface;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;

final class WebklexReader implements MailboxReaderInterface
{
    public function __construct(private Client $client) {}

    public function listFolders(): array
    {
        $paths = [];
        foreach ($this->client->getFolders(false) as $folder) {
            $paths[] = $folder->path;
        }
        return $paths;
    }

    public function folderUidValidity(string $folder): int
    {
        $f = $this->getFolder($folder);
        $status = $f->getStatus(); // ['uidvalidity' => ..., ...]
        return (int) ($status['uidvalidity'] ?? 0);
    }

    public function eachHeader(string $folder, callable $cb): void
    {
        $f = $this->getFolder($folder);
        $f->query()->whereAll()->setFetchBody(false)->setFetchFlags(true)->chunked(
            function ($messages) use ($cb) {
                foreach ($messages as $message) {
                    $dateAttr = $message->getDate();
                    $internalDate = $dateAttr->has() ? $dateAttr->toDate()->format('d-M-Y H:i:s O') : '';

                    $from = $message->getFrom();
                    $fromMail = $from[0]->mail ?? '';

                    $messageId = (string) $message->getMessageId();
                    // Only pay for the RFC822.SIZE round-trip when we'll actually need it
                    // (the sha256 dedupe fallback, used only when there is no Message-ID).
                    $size = $messageId === '' ? (int) ($message->getSize() ?? 0) : 0;

                    $cb([
                        'uid' => (int) $message->getUid(),
                        'message_id' => $messageId,
                        'from' => (string) $fromMail,
                        'subject' => (string) $message->getSubject(),
                        'size' => $size,
                        'internal_date' => $internalDate,
                        'flags' => self::normalizeFlags((array) $message->getFlags()->all()),
                    ]);
                }
            },
            200
        );
    }

    public function fetchRaw(string $folder, int $uid): string
    {
        try {
            $f = $this->getFolder($folder);
            $message = $f->query()->getMessageByUid($uid);

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
