<?php
declare(strict_types=1);

namespace EmailMigration;

final class Dedupe
{
    public static function hash(string $internalDate, string $from, string $subject, int $size): string
    {
        return hash('sha256', implode('|', [$internalDate, $from, $subject, (string) $size]));
    }

    public static function key(?string $messageId, string $internalDate, string $from, string $subject, int $size): string
    {
        $id = trim((string) $messageId);
        if ($id !== '') {
            return $id;
        }
        return 'hash:' . self::hash($internalDate, $from, $subject, $size);
    }
}
