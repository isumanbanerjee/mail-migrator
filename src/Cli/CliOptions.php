<?php
declare(strict_types=1);

namespace EmailMigration\Cli;

final class CliOptions
{
    public static function parse(array $argv): array
    {
        $o = [
            'config' => 'config/accounts.php',
            'test_connection' => false, 'dry_run' => false, 'only_folder' => null,
            'since' => null, 'limit' => null, 'retry_failed' => false, 'verbose' => false,
        ];

        foreach (array_slice($argv, 1) as $arg) {
            [$name, $value] = array_pad(explode('=', $arg, 2), 2, null);
            match ($name) {
                '--config' => $o['config'] = (string) $value,
                '--test-connection' => $o['test_connection'] = true,
                '--dry-run' => $o['dry_run'] = true,
                '--folder' => $o['only_folder'] = (string) $value,
                '--since' => $o['since'] = (string) $value,
                '--limit' => $o['limit'] = (int) $value,
                '--retry-failed' => $o['retry_failed'] = true,
                '--verbose' => $o['verbose'] = true,
                default => null,
            };
        }
        return $o;
    }
}
