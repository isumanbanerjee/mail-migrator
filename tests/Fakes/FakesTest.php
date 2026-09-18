<?php
declare(strict_types=1);

namespace MailMigrator\Tests\Fakes;

use PHPUnit\Framework\TestCase;

final class FakesTest extends TestCase
{
    public function test_reader_yields_added_messages_and_raw(): void
    {
        $r = new InMemoryReader();
        $r->addMessage('INBOX', ['uid' => 5, 'message_id' => '<a@x>'], 'RAW5');
        $seen = [];
        $r->eachHeader('INBOX', function (array $h) use (&$seen) { $seen[] = $h['uid']; });

        $this->assertSame([5], $seen);
        $this->assertSame('RAW5', $r->fetchRaw('INBOX', 5));
        $this->assertSame(['INBOX'], $r->listFolders());
    }

    public function test_writer_records_appends_and_can_fail(): void
    {
        $w = new InMemoryWriter();
        $w->ensureFolder('INBOX');
        $this->assertTrue($w->append('INBOX', 'RAW', ['\\Seen'], '01-Jan-2020 00:00:00 +0000'));
        $this->assertCount(1, $w->appended);

        $w->failAppend = true;
        $this->assertFalse($w->append('INBOX', 'RAW2', [], '01-Jan-2020 00:00:00 +0000'));
        $this->assertCount(1, $w->appended);
    }
}
