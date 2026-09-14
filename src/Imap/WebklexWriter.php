<?php
declare(strict_types=1);

namespace EmailMigration\Imap;

use EmailMigration\Mailbox\MailboxWriterInterface;
use Webklex\PHPIMAP\Client;

final class WebklexWriter implements MailboxWriterInterface
{
    public function __construct(private Client $client) {}

    public function ensureFolder(string $folder): void
    {
        if ($this->client->getFolderByPath($folder) === null) {
            $this->client->createFolder($folder, false);
        }
    }

    public function existingMessageIds(string $folder): array
    {
        $ids = [];
        $f = $this->client->getFolderByPath($folder);
        if ($f === null) {
            return $ids;
        }
        $f->query()->whereAll()->setFetchBody(false)->setFetchFlags(false)->chunked(
            function ($messages) use (&$ids) {
                foreach ($messages as $message) {
                    $mid = (string) $message->getMessageId();
                    if ($mid !== '') {
                        $ids[] = $mid;
                    }
                }
            },
            500
        );
        return $ids;
    }

    public function append(string $folder, string $raw, array $flags, string $internalDate): bool
    {
        $f = $this->client->getFolderByPath($folder);
        if ($f === null) {
            return false;
        }

        // Folder::appendMessage(string $message, array $options = null, Carbon|string $internal_date = null): array
        // Confirmed against webklex/php-imap v5.5.0 source (src/Folder.php). It returns the raw response
        // data from the underlying APPEND command (an array), and throws a ResponseException on protocol
        // failure rather than returning false - so any array we get back here represents a successful append.
        $result = $f->appendMessage($raw, $flags, $internalDate);
        return $result !== [] && $result !== false;
    }
}
