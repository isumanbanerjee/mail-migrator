<?php
declare(strict_types=1);

namespace EmailMigration\Tests;

use EmailMigration\FolderMapper;
use PHPUnit\Framework\TestCase;

final class FolderMapperTest extends TestCase
{
    public function test_inbox_maps_to_inbox(): void
    {
        $m = new FolderMapper();
        $this->assertSame('INBOX', $m->map('INBOX'));
    }

    public function test_special_folders_map_to_gmail_equivalents(): void
    {
        $m = new FolderMapper();
        $this->assertSame('[Gmail]/Sent Mail', $m->map('INBOX.Sent'));
        $this->assertSame('[Gmail]/Sent Mail', $m->map('Sent'));
        $this->assertSame('[Gmail]/Drafts', $m->map('Drafts'));
        $this->assertSame('[Gmail]/Spam', $m->map('Junk'));
        $this->assertSame('[Gmail]/Trash', $m->map('Trash'));
    }

    public function test_special_match_is_case_insensitive(): void
    {
        $m = new FolderMapper();
        $this->assertSame('[Gmail]/Sent Mail', $m->map('inbox.sent'));
    }

    public function test_custom_folder_strips_inbox_prefix_and_translates_delimiter(): void
    {
        $m = new FolderMapper();
        $this->assertSame('Work/Clients', $m->map('INBOX.Work.Clients'));
        $this->assertSame('Archive', $m->map('INBOX.Archive'));
    }

    public function test_explicit_override_wins(): void
    {
        $m = new FolderMapper(['INBOX.Archive' => 'Old Mail']);
        $this->assertSame('Old Mail', $m->map('INBOX.Archive'));
    }
}
