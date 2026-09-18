<?php
declare(strict_types=1);

namespace MailMigrator\Imap;

use MailMigrator\Mailbox\MailboxWriterInterface;
use MailMigrator\Support\Logger;
use MailMigrator\Support\Timeout;
use Throwable;
use Webklex\PHPIMAP\Client;

final class WebklexWriter implements MailboxWriterInterface
{
    private ?string $lastError = null;

    public function __construct(
        private Client $client,
        private ?Logger $logger = null,
        private int $opTimeout = 120,
    ) {}

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function ensureFolder(string $folder): void
    {
        Timeout::run($this->opTimeout, function () use ($folder): void {
            if ($this->client->getFolderByPath($folder) === null) {
                $this->client->createFolder($folder, false);
            }
        });
    }

    public function existingMessageIds(string $folder): array
    {
        $ids = [];
        $f = Timeout::run($this->opTimeout, fn () => $this->client->getFolderByPath($folder));
        if ($f === null) {
            return $ids;
        }
        $page = 1;
        while (true) {
            $messages = Timeout::run($this->opTimeout, function () use ($f, $page) {
                return $f->query()->whereAll()->setFetchBody(false)->setFetchFlags(false)->limit(500, $page)->get();
            });
            if ($messages->count() === 0) {
                break;
            }
            foreach ($messages as $message) {
                $mid = (string) $message->getMessageId();
                if ($mid !== '') {
                    $ids[] = $mid;
                }
            }
            if ($messages->count() < 500) {
                break;
            }
            $page++;
        }
        return $ids;
    }

    public function append(string $folder, string $raw, array $flags, string $internalDate): bool
    {
        $this->lastError = null;

        $f = $this->client->getFolderByPath($folder);
        if ($f === null) {
            $this->lastError = "destination folder not found: {$folder}";
            $this->logger?->error('IMAP append failed: ' . $this->lastError);
            return false;
        }

        if ($raw === '') {
            $this->lastError = 'refusing to append an empty message body';
            $this->logger?->error('IMAP append failed: ' . $this->lastError);
            return false;
        }

        // Folder::appendMessage(string $message, array $options = null, Carbon|string $internal_date = null): array
        // Confirmed against webklex/php-imap v5.5.0 source (src/Folder.php). It throws a ResponseException
        // (or ImapServerErrorException / ImapBadRequestException) on a NO/BAD APPEND rather than returning
        // false - so reaching the return below without an exception means the append succeeded, even if
        // the response array happens to be empty.
        // An empty internal date is not a valid IMAP APPEND date-time; pass null so the
        // server stamps "now" instead of rejecting a malformed date literal.
        $date = $internalDate !== '' ? $internalDate : null;

        try {
            // Hard timeout: a blocking read of Gmail's APPEND response can hang
            // indefinitely (stream_set_timeout is unreliable on SSL sockets), which
            // would freeze the whole worker. Bound it so it fails and moves on.
            Timeout::run($this->opTimeout, fn () => $f->appendMessage($raw, $flags, $date));
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->logger?->error('IMAP append failed: ' . $e->getMessage());
            return false;
        }

        return true;
    }
}
