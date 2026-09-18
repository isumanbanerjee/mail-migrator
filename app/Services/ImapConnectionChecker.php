<?php
declare(strict_types=1);

namespace App\Services;

use MailMigrator\Imap\ImapConnection;
use MailMigrator\Imap\WebklexReader;

final class ImapConnectionChecker implements ConnectionCheckerInterface
{
    public function check(array $account): array
    {
        try {
            $client = ImapConnection::connect([
                'host' => $account['host'],
                'port' => (int) $account['port'],
                'encryption' => $account['encryption'],
                'validate_cert' => true,
                'username' => $account['username'],
                'password' => $account['password'],
            ]);
            $folders = (new WebklexReader($client))->listFolders();
            return ['ok' => true, 'folders' => $folders, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'folders' => [], 'error' => $e->getMessage()];
        }
    }
}
