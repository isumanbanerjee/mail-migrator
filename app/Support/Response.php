<?php
declare(strict_types=1);

namespace App\Support;

final class Response
{
    private function __construct(
        private int $status,
        private string $body,
        private array $headers,
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self($status, '', ['Location' => $to]);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self($status, json_encode($data, JSON_UNESCAPED_SLASHES), ['Content-Type' => 'application/json']);
    }

    public function withHeader(string $k, string $v): self
    {
        $c = clone $this;
        $c->headers[$k] = $v;
        return $c;
    }

    public function status(): int { return $this->status; }
    public function body(): string { return $this->body; }
    public function headers(): array { return $this->headers; }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("{$k}: {$v}");
        }
        echo $this->body;
    }
}
