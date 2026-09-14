<?php
declare(strict_types=1);

namespace App\Services;

interface MailboxFactoryInterface
{
    /** @return array{reader:\EmailMigration\Mailbox\MailboxReaderInterface,writer:\EmailMigration\Mailbox\MailboxWriterInterface} */
    public function forJob(array $job): array;
}
