<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Encryptor;
use EmailMigration\Imap\ImapConnection;
use EmailMigration\Imap\WebklexReader;
use EmailMigration\Imap\WebklexWriter;

final class WebklexMailboxFactory implements MailboxFactoryInterface
{
    public function __construct(private Encryptor $enc) {}

    public function forJob(array $job): array
    {
        $source = ImapConnection::connect([
            'host' => $job['source_host'], 'port' => (int) $job['source_port'],
            'encryption' => $job['source_encryption'], 'validate_cert' => true,
            'username' => $this->enc->decrypt($job['source_username_enc']),
            'password' => $this->enc->decrypt($job['source_password_enc']),
        ]);
        $dest = ImapConnection::connect([
            'host' => $job['dest_host'], 'port' => (int) $job['dest_port'],
            'encryption' => $job['dest_encryption'], 'validate_cert' => true,
            'username' => $this->enc->decrypt($job['dest_username_enc']),
            'password' => $this->enc->decrypt($job['dest_password_enc']),
        ]);
        return ['reader' => new WebklexReader($source), 'writer' => new WebklexWriter($dest)];
    }
}
