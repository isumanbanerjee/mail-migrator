<?php
declare(strict_types=1);

namespace App\Support;

use Dotenv\Dotenv;

final class Config
{
    private function __construct(private array $data) {}

    public static function fromArray(array $data): self { return new self($data); }

    public static function load(string $basePath): self
    {
        Dotenv::createImmutable($basePath)->safeLoad();
        $data = require $basePath . '/config/app.php';
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $node = $this->data;
        foreach (explode('.', $key) as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) {
                return $default;
            }
            $node = $node[$seg];
        }
        return $node;
    }

    public function appKey(): string { return (string) $this->get('key', ''); }
    public function db(): array { return (array) $this->get('db', []); }

    public function validate(): void
    {
        if ($this->appKey() === '') {
            throw new \RuntimeException('APP_KEY is missing — generate one and set it in .env');
        }
        if ((string) ($this->db()['driver'] ?? '') === '') {
            throw new \RuntimeException('db.driver is missing — set DB_DRIVER (or config/app.php db.driver)');
        }
    }
}
