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
}
