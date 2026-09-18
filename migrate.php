<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use MailMigrator\Cli\CliOptions;
use MailMigrator\Config;
use MailMigrator\FolderMapper;
use MailMigrator\Imap\ImapConnection;
use MailMigrator\Imap\WebklexReader;
use MailMigrator\Imap\WebklexWriter;
use MailMigrator\Ledger\SqliteLedger;
use MailMigrator\MessageMigrator;
use MailMigrator\MigrationRunner;
use MailMigrator\Support\Logger;

$opts = CliOptions::parse($argv);
$logger = new Logger($opts['verbose'] ? 'debug' : 'info', 'migration.log');

try {
    $config = Config::fromFile($opts['config']);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Config error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$logger->addSecret($config->source()['password']);
$logger->addSecret($config->destination()['password']);

$mapper = new FolderMapper($config->options()['folder_map']);

try {
    $sourceClient = ImapConnection::connect($config->source());
    $destClient = ImapConnection::connect($config->destination());
} catch (\Throwable $e) {
    $logger->error('Connection failed: ' . $e->getMessage());
    exit(1);
}

$reader = new WebklexReader($sourceClient);
$writer = new WebklexWriter($destClient, $logger);

if ($opts['test_connection']) {
    $logger->info('Both accounts connected. Folder map:');
    foreach ($reader->listFolders() as $f) {
        $logger->info(sprintf('  %s  ->  %s', $f, $mapper->map($f)));
    }
    exit(0);
}

$ledger = SqliteLedger::fromFile(__DIR__ . '/migration.sqlite');
$migrator = new MessageMigrator($writer, $ledger, $logger);
$runner = new MigrationRunner($reader, $writer, $ledger, $mapper, $migrator, $logger, [
    'dry_run' => $opts['dry_run'],
    'only_folder' => $opts['only_folder'],
    'since' => $opts['since'],
    'limit' => $opts['limit'],
    'throttle_ms' => $config->options()['throttle_ms'],
]);

$start = time();
try {
    $summary = $runner->run(function (array $s): void {
        // lightweight inline progress; overwrite a single line
        fwrite(STDOUT, sprintf(
            "\r[%s] copied %d  skipped %d  failed %d  would-copy %d   ",
            $s['folder'], $s['copied'], $s['skipped'], $s['failed'], $s['would_copy']
        ));
    });
} catch (\Throwable $e) {
    fwrite(STDOUT, PHP_EOL);
    $logger->error('Migration error: ' . $e->getMessage());
    exit(1);
}
fwrite(STDOUT, PHP_EOL);

$elapsed = time() - $start;
$logger->info('================ MIGRATION SUMMARY ================');
$logger->info(sprintf('Copied: %d   Skipped: %d   Would-copy: %d   Failed: %d',
    $summary['copied'], $summary['skipped'], $summary['would_copy'], $summary['failed']));
$logger->info(sprintf('Folders: %d   Elapsed: %ds   Ledger: migration.sqlite',
    $summary['folders'], $elapsed));

exit($summary['failed'] > 0 ? 2 : 0);
