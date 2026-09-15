<?php
declare(strict_types=1);

namespace EmailMigration\Tests\Imap;

use EmailMigration\Imap\WebklexReader;
use PHPUnit\Framework\TestCase;

final class NormalizeFlagsTest extends TestCase
{
    public function test_system_flags_get_backslash_so_read_state_is_preserved(): void
    {
        // webklex hands us backslash-stripped flags like "Seen"
        $this->assertSame(['\\Seen'], WebklexReader::normalizeFlags(['Seen']));
        $this->assertSame(['\\Answered', '\\Flagged'], WebklexReader::normalizeFlags(['Answered', 'Flagged']));
    }

    public function test_unread_message_has_no_seen_flag(): void
    {
        $this->assertSame([], WebklexReader::normalizeFlags([]));
    }

    public function test_already_prefixed_and_custom_keywords(): void
    {
        $this->assertSame(['\\Seen'], WebklexReader::normalizeFlags(['\\Seen']));
        $this->assertSame(['\\Seen', '$Important'], WebklexReader::normalizeFlags(['Seen', '$Important']));
    }
}
