<?php
declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Services\MailboxFactoryInterface;
use EmailMigration\Mailbox\MailboxReaderInterface;
use EmailMigration\Mailbox\MailboxWriterInterface;

final class FakeMailboxFactory implements MailboxFactoryInterface
{
    public function __construct(private MailboxReaderInterface $reader, private MailboxWriterInterface $writer) {}

    public function forJob(array $job): array
    {
        return ['reader' => $this->reader, 'writer' => $this->writer];
    }
}
