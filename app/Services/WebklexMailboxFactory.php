<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Encryptor;
use EmailMigration\Imap\ImapConnection;
use EmailMigration\Imap\WebklexReader;
use EmailMigration\Imap\WebklexWriter;
use EmailMigration\Support\Logger;

final class WebklexMailboxFactory implements MailboxFactoryInterface
{
    public function __construct(private Encryptor $enc) {}

    public function forJob(array $job): array
    {
        $connTimeout = (int) ($_ENV['IMAP_CONNECT_TIMEOUT'] ?? 60);
        $opTimeout = (int) ($_ENV['WORKER_OP_TIMEOUT'] ?? 120);

        $source = ImapConnection::connect([
            'host' => $job['source_host'], 'port' => (int) $job['source_port'],
            'encryption' => $job['source_encryption'], 'validate_cert' => true,
            'username' => $this->enc->decrypt($job['source_username_enc']),
            'password' => $this->enc->decrypt($job['source_password_enc']),
            'timeout' => $connTimeout,
        ]);
        $dest = ImapConnection::connect([
            'host' => $job['dest_host'], 'port' => (int) $job['dest_port'],
            'encryption' => $job['dest_encryption'], 'validate_cert' => true,
            'username' => $this->enc->decrypt($job['dest_username_enc']),
            'password' => $this->enc->decrypt($job['dest_password_enc']),
            'timeout' => $connTimeout,
        ]);
        $logger = new Logger('error');
        foreach ([$this->enc->decrypt($job['source_password_enc']), $this->enc->decrypt($job['dest_password_enc'])] as $secret) {
            $logger->addSecret($secret);
        }

        return [
            'reader' => new WebklexReader($source, $opTimeout),
            'writer' => new WebklexWriter($dest, $logger, $opTimeout),
        ];
    }
}
