<?php
declare(strict_types=1);

namespace MailMigrator\Tests\Support;

use MailMigrator\Support\MimeHeader;
use PHPUnit\Framework\TestCase;

final class MimeHeaderTest extends TestCase
{
    public function test_plain_subject_unchanged(): void
    {
        $this->assertSame('Bank Account Statement', MimeHeader::decode('Bank Account Statement'));
        $this->assertSame('', MimeHeader::decode(''));
    }

    public function test_quoted_printable_encoded_word(): void
    {
        $this->assertSame('Enter The Fabulous World', MimeHeader::decode('=?utf-8?Q?Enter=20The=20Fabulous=20World?='));
    }

    public function test_base64_encoded_word(): void
    {
        // base64("Héllo") in a UTF-8 encoded-word
        $ew = '=?UTF-8?B?' . base64_encode("H\u{00e9}llo") . '?=';
        $this->assertSame("H\u{00e9}llo", MimeHeader::decode($ew));
    }

    public function test_folded_multipart_encoded_word(): void
    {
        $folded = "=?utf-8?Q?Part_one_?=\r\n =?utf-8?Q?part_two?=";
        $this->assertSame('Part one part two', MimeHeader::decode($folded));
    }
}
