<?php
declare(strict_types=1);

namespace EmailMigration;

final class FolderMapper
{
    /** @var array<string,string> lower-cased source path => dest path */
    private array $lookup = [];

    public function __construct(
        array $overrides = [],
        private string $sourceDelimiter = '.',
        private string $destDelimiter = '/',
    ) {
        foreach (array_merge(self::gmailSpecialMap(), $overrides) as $src => $dest) {
            $this->lookup[strtolower($src)] = $dest;
        }
    }

    /** @return array<string,string> */
    public static function gmailSpecialMap(): array
    {
        return [
            'INBOX' => 'INBOX',
            'Sent' => '[Gmail]/Sent Mail',
            'INBOX.Sent' => '[Gmail]/Sent Mail',
            'Drafts' => '[Gmail]/Drafts',
            'INBOX.Drafts' => '[Gmail]/Drafts',
            'Trash' => '[Gmail]/Trash',
            'INBOX.Trash' => '[Gmail]/Trash',
            'Junk' => '[Gmail]/Spam',
            'Spam' => '[Gmail]/Spam',
            'INBOX.Junk' => '[Gmail]/Spam',
            'INBOX.Spam' => '[Gmail]/Spam',
        ];
    }

    public function map(string $sourceFolder): string
    {
        $key = strtolower($sourceFolder);
        if (isset($this->lookup[$key])) {
            return $this->lookup[$key];
        }

        $path = $sourceFolder;
        $prefix = 'INBOX' . $this->sourceDelimiter;
        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        }
        return str_replace($this->sourceDelimiter, $this->destDelimiter, $path);
    }
}
