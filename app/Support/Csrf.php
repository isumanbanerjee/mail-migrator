<?php
declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    public static function token(Session $s): string
    {
        $t = $s->get('_csrf');
        if (!is_string($t) || $t === '') {
            $t = bin2hex(random_bytes(32));
            $s->put('_csrf', $t);
        }
        return $t;
    }

    public static function verify(Session $s, ?string $token): bool
    {
        $stored = $s->get('_csrf');
        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }
}
