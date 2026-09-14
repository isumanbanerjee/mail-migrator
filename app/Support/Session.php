<?php
declare(strict_types=1);

namespace App\Support;

final class Session
{
    private array $store;
    private bool $php;

    public function __construct(?array &$store = null, bool $php = false)
    {
        if ($store === null) {
            $this->store = [];
        } else {
            $this->store = &$store;
        }
        $this->php = $php;
    }

    public static function fromPhp(): self
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => (($_SERVER['HTTPS'] ?? '') !== ''),
            ]);
        }
        $s = new self($_SESSION, true);
        return $s;
    }

    public function get(string $k, mixed $default = null): mixed { return $this->store[$k] ?? $default; }
    public function put(string $k, mixed $v): void { $this->store[$k] = $v; }
    public function forget(string $k): void { unset($this->store[$k]); }
    public function all(): array { return $this->store; }

    public function flash(string $k, mixed $v): void { $this->store['_flash'][$k] = $v; }

    public function getFlash(string $k, mixed $default = null): mixed
    {
        $v = $this->store['_flash'][$k] ?? $default;
        unset($this->store['_flash'][$k]);
        return $v;
    }

    public function regenerateId(): void
    {
        if ($this->php && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
