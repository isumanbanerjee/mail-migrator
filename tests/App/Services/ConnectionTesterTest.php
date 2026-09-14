<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\ConnectionTester;
use App\Tests\Fakes\FakeConnectionChecker;
use EmailMigration\FolderMapper;
use PHPUnit\Framework\TestCase;

final class ConnectionTesterTest extends TestCase
{
    public function test_reports_both_sides_and_maps_source_folders(): void
    {
        $checker = new FakeConnectionChecker([
            'imap.src' => ['ok' => true, 'folders' => ['INBOX', 'INBOX.Sent'], 'error' => null],
            'imap.dst' => ['ok' => true, 'folders' => [], 'error' => null],
        ]);
        $tester = new ConnectionTester($checker);

        $result = $tester->test(
            ['host' => 'imap.src', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p'],
            ['host' => 'imap.dst', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p'],
            new FolderMapper()
        );

        $this->assertTrue($result['source']['ok']);
        $this->assertTrue($result['dest']['ok']);
        $this->assertSame('INBOX', $result['folder_map']['INBOX']);
        $this->assertSame('[Gmail]/Sent Mail', $result['folder_map']['INBOX.Sent']);
    }

    public function test_source_failure_yields_empty_map(): void
    {
        $checker = new FakeConnectionChecker([
            'bad' => ['ok' => false, 'folders' => [], 'error' => 'auth failed'],
            'imap.dst' => ['ok' => true, 'folders' => [], 'error' => null],
        ]);
        $tester = new ConnectionTester($checker);
        $result = $tester->test(
            ['host' => 'bad', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p'],
            ['host' => 'imap.dst', 'port' => 993, 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p'],
            new FolderMapper()
        );
        $this->assertFalse($result['source']['ok']);
        $this->assertSame('auth failed', $result['source']['error']);
        $this->assertSame([], $result['folder_map']);
    }
}
