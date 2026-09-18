<?php
declare(strict_types=1);

namespace MailMigrator\Mailbox;

interface MailboxWriterInterface
{
    public function ensureFolder(string $folder): void;

    /** @return array<int,string> */
    public function existingMessageIds(string $folder): array;

    /** @param array<int,string> $flags */
    public function append(string $folder, string $raw, array $flags, string $internalDate): bool;

    /** The reason the most recent append() returned false, or null on success / not yet attempted. */
    public function lastError(): ?string;
}
