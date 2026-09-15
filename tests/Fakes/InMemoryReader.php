<?php
declare(strict_types=1);

namespace EmailMigration\Tests\Fakes;

use EmailMigration\Mailbox\MailboxReaderInterface;

final class InMemoryReader implements MailboxReaderInterface
{
    /** @var array<string,array<int,array>> folder => list of headers */
    public array $folders = [];
    /** @var array<string,array<int,string>> "folder:uid" => raw */
    public array $raw = [];
    /** @var array<string,string> "folder:uid" => body only (for the fetchBody fast path) */
    public array $body = [];
    /** @var array<string,int> */
    public array $uidValidity = [];

    public function addMessage(string $folder, array $header, string $raw): void
    {
        $this->folders[$folder][] = $header;
        $this->raw["{$folder}:{$header['uid']}"] = $raw;
        $this->uidValidity[$folder] ??= 1;
    }

    public function listFolders(): array
    {
        return array_keys($this->folders);
    }

    public function folderUidValidity(string $folder): int
    {
        return $this->uidValidity[$folder] ?? 1;
    }

    public function eachHeader(string $folder, callable $cb): void
    {
        foreach ($this->folders[$folder] ?? [] as $h) {
            $cb($h);
        }
    }

    public function fetchRaw(string $folder, int $uid): string
    {
        return $this->raw["{$folder}:{$uid}"] ?? '';
    }

    public function fetchBody(string $folder, int $uid): string
    {
        return $this->body["{$folder}:{$uid}"] ?? '';
    }
}
