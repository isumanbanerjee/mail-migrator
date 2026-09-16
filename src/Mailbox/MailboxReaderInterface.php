<?php
declare(strict_types=1);

namespace EmailMigration\Mailbox;

interface MailboxReaderInterface
{
    /** @return array<int,string> */
    public function listFolders(): array;

    public function folderUidValidity(string $folder): int;

    /**
     * @param callable(array):void $cb
     * @param int $sinceUid only yield messages with UID greater than this (0 = all).
     *                      Lets a resumed run skip messages already processed instead of
     *                      re-reading the whole folder every time.
     */
    public function eachHeader(string $folder, callable $cb, int $sinceUid = 0): void;

    public function fetchRaw(string $folder, int $uid): string;

    /**
     * Fetch only the body (RFC822.TEXT) of a message, to be combined with the
     * raw header already captured during eachHeader() — avoids re-fetching the
     * header. Returns '' if the body-only fetch is unavailable (caller should
     * fall back to fetchRaw()).
     */
    public function fetchBody(string $folder, int $uid): string;
}
