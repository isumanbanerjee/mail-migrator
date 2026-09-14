<?php
declare(strict_types=1);

namespace EmailMigration\Imap;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

final class ImapConnection
{
    public static function connect(array $account): Client
    {
        $cm = new ClientManager();
        $client = $cm->make([
            'host' => $account['host'],
            'port' => $account['port'],
            'encryption' => $account['encryption'],
            'validate_cert' => $account['validate_cert'] ?? true,
            'username' => $account['username'],
            'password' => $account['password'],
            'protocol' => 'imap',
        ]);
        $client->connect();
        return $client;
    }
}
