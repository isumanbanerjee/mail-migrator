<?php
declare(strict_types=1);

namespace MailMigrator\Support;

final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warn' => 2, 'error' => 3];

    /** @var array<int,string> */
    private array $lines = [];
    /** @var array<int,string> */
    private array $secrets = [];
    private int $threshold;

    public function __construct(private string $level = 'info', private ?string $file = null)
    {
        $this->threshold = self::LEVELS[$level] ?? 1;
    }

    public function addSecret(string $secret): void
    {
        if ($secret !== '') {
            $this->secrets[] = $secret;
        }
    }

    public function debug(string $m): void { $this->write('debug', $m); }
    public function info(string $m): void  { $this->write('info', $m); }
    public function warn(string $m): void  { $this->write('warn', $m); }
    public function error(string $m): void { $this->write('error', $m); }

    /** @return array<int,string> */
    public function lines(): array { return $this->lines; }

    private function write(string $level, string $m): void
    {
        if (self::LEVELS[$level] < $this->threshold) {
            return;
        }
        $safe = $this->redact($m);
        $line = sprintf('[%s] %s', strtoupper($level), $safe);
        $this->lines[] = $line;
        if ($this->file !== null) {
            file_put_contents($this->file, $line . PHP_EOL, FILE_APPEND);
        }
        fwrite($level === 'error' ? STDERR : STDOUT, $line . PHP_EOL);
    }

    private function redact(string $m): string
    {
        foreach ($this->secrets as $secret) {
            $m = str_replace($secret, '***', $m);
        }
        return $m;
    }
}
