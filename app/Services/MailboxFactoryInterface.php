<?php
declare(strict_types=1);

namespace App\Services;

interface MailboxFactoryInterface
{
    /** @return array{reader:\MailMigrator\Mailbox\MailboxReaderInterface,writer:\MailMigrator\Mailbox\MailboxWriterInterface} */
    public function forJob(array $job): array;
}
