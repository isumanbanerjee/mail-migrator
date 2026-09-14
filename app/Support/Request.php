<?php
declare(strict_types=1);

namespace App\Support;

final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $post,
        private array $cookies,
        private array $server,
        private string $rawBody = '',
    ) {}

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path, $_GET, $_POST, $_COOKIE, $_SERVER,
            (string) file_get_contents('php://input'),
        );
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function isPost(): bool { return $this->method === 'POST'; }
    public function input(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $default; }
    public function query(string $key, mixed $default = null): mixed { return $this->query[$key] ?? $default; }
    public function cookie(string $key, mixed $default = null): mixed { return $this->cookies[$key] ?? $default; }
    public function server(string $key, mixed $default = null): mixed { return $this->server[$key] ?? $default; }
    public function serverAll(): array { return $this->server; }
    public function all(): array { return $this->post; }
    public function rawBody(): string { return $this->rawBody; }
}
