<?php
declare(strict_types=1);

namespace EmailMigration\Tests\Fakes;

use EmailMigration\Mailbox\MailboxWriterInterface;

final class InMemoryWriter implements MailboxWriterInterface
{
    /** @var array<int,string> created folders */
    public array $created = [];
    /** @var array<string,array<int,string>> folder => message-ids already present */
    public array $existing = [];
    /** @var array<int,array{folder:string,raw:string,flags:array,date:string}> */
    public array $appended = [];
    public bool $failAppend = false;
    public ?string $appendError = 'simulated append failure';
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function ensureFolder(string $folder): void
    {
        if (!in_array($folder, $this->created, true)) {
            $this->created[] = $folder;
        }
    }

    public function existingMessageIds(string $folder): array
    {
        return $this->existing[$folder] ?? [];
    }

    public function append(string $folder, string $raw, array $flags, string $internalDate): bool
    {
        $this->lastError = null;
        if ($this->failAppend) {
            $this->lastError = $this->appendError;
            return false;
        }
        $this->appended[] = ['folder' => $folder, 'raw' => $raw, 'flags' => $flags, 'date' => $internalDate];
        return true;
    }
}
