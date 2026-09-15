<?php
declare(strict_types=1);

namespace EmailMigration\Mailbox;

interface MailboxReaderInterface
{
    /** @return array<int,string> */
    public function listFolders(): array;

    public function folderUidValidity(string $folder): int;

    /** @param callable(array):void $cb */
    public function eachHeader(string $folder, callable $cb): void;

    public function fetchRaw(string $folder, int $uid): string;

    /**
     * Fetch only the body (RFC822.TEXT) of a message, to be combined with the
     * raw header already captured during eachHeader() — avoids re-fetching the
     * header. Returns '' if the body-only fetch is unavailable (caller should
     * fall back to fetchRaw()).
     */
    public function fetchBody(string $folder, int $uid): string;
}
