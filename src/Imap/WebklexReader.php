<?php
declare(strict_types=1);

namespace EmailMigration\Imap;

use EmailMigration\Mailbox\MailboxReaderInterface;
use RuntimeException;
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

                    $cb([
                        'uid' => (int) $message->getUid(),
                        'message_id' => (string) $message->getMessageId(),
                        'from' => (string) $message->getFrom()[0]?->mail ?? '',
                        'subject' => (string) $message->getSubject(),
                        'size' => (int) ($message->getSize() ?? 0),
                        'internal_date' => $internalDate,
                        'flags' => array_values((array) $message->getFlags()->all()),
                    ]);
                }
            },
            200
        );
    }

    public function fetchRaw(string $folder, int $uid): string
    {
        $f = $this->getFolder($folder);
        $message = $f->query()->getMessageByUid($uid);
        return (string) $message->getRawBody();
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
