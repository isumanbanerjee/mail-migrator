<?php
declare(strict_types=1);

namespace EmailMigration;

use InvalidArgumentException;

final class Config
{
    private const ACCOUNT_KEYS = ['host', 'port', 'encryption', 'username', 'password'];

    private function __construct(
        private array $source,
        private array $destination,
        private array $options,
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Config file not found: {$path}");
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new InvalidArgumentException("Config file must return an array: {$path}");
        }
        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        $source = self::validateAccount($data['source'] ?? null, 'source');
        $destination = self::validateAccount($data['destination'] ?? null, 'destination');
        $options = self::withOptionDefaults($data['options'] ?? []);
        return new self($source, $destination, $options);
    }

    public function source(): array { return $this->source; }
    public function destination(): array { return $this->destination; }
    public function options(): array { return $this->options; }

    private static function validateAccount(mixed $acct, string $label): array
    {
        if (!is_array($acct)) {
            throw new InvalidArgumentException("Missing '{$label}' config section");
        }
        foreach (self::ACCOUNT_KEYS as $key) {
            if (!array_key_exists($key, $acct) || $acct[$key] === '' || $acct[$key] === null) {
                throw new InvalidArgumentException("Missing '{$label}.{$key}' in config");
            }
        }
        $acct['port'] = (int) $acct['port'];
        $acct['validate_cert'] = $acct['validate_cert'] ?? true;
        return $acct;
    }

    private static function withOptionDefaults(array $opts): array
    {
        return [
            'batch_size' => (int) ($opts['batch_size'] ?? 200),
            'throttle_ms' => (int) ($opts['throttle_ms'] ?? 300),
            'folder_map' => (array) ($opts['folder_map'] ?? []),
            'since' => $opts['since'] ?? null,
            'limit' => isset($opts['limit']) ? (int) $opts['limit'] : null,
        ];
    }
}
