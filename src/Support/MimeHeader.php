<?php
declare(strict_types=1);

namespace MailMigrator\Support;

final class MimeHeader
{
    /**
     * Decode an RFC 2047 "encoded-word" header value (e.g. a Subject like
     * "=?UTF-8?Q?Hello=20World?=" or a folded/multi-part one) into plain UTF-8.
     * Plain values pass through unchanged. Safe if iconv/mbstring are missing.
     */
    public static function decode(string $value): string
    {
        if ($value === '' || !str_contains($value, '=?')) {
            return $value;
        }

        // Unfold: RFC 2047 words may be split across folded header lines.
        $flat = preg_replace('/\r?\n[ \t]+/', ' ', $value) ?? $value;

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($flat, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        if (function_exists('mb_decode_mimeheader')) {
            $decoded = @mb_decode_mimeheader($flat);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $value;
    }
}
