<?php
declare(strict_types=1);

// php-imap 5.5.0 emits E_DEPRECATED under PHP 8.5 for nearly every method call,
// which floods worker.log and buries real errors. Keep everything except deprecations.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';

use App\Repositories\EntitlementRepository;
use App\Repositories\JobRepository;
use App\Services\EntitlementResolver;
use App\Services\JobClaimer;
use App\Services\JobRunner;
use App\Services\MeteringService;
use App\Services\WebklexMailboxFactory;
use App\Support\BillingConfig;
use App\Support\Config;
use App\Support\Database;
use App\Support\Encryptor;
use App\Support\MysqlLedger;

$basePath = dirname(__DIR__);
$config = Config::load($basePath);
$config->validate();
$pdo = Database::make($config->db());
$encryptor = new Encryptor($config->appKey());
$jobs = new JobRepository($pdo);

$maxConcurrent = (int) ($_ENV['MAX_CONCURRENT_JOBS'] ?? 1);
$staleSeconds = (int) ($_ENV['WORKER_STALE_SECONDS'] ?? 900);
$budget = (int) ($_ENV['WORKER_MAX_SECONDS'] ?? 50);

$billingConfig = BillingConfig::fromEnv();
$resolver = new EntitlementResolver($billingConfig, new EntitlementRepository($pdo), new MeteringService($pdo));

$claimer = new JobClaimer($pdo, $jobs, $maxConcurrent, $staleSeconds);
$runner = new JobRunner(new WebklexMailboxFactory($encryptor), $jobs, fn (int $id) => new MysqlLedger($pdo, $id), $budget, null, $resolver);

$workerId = 'w-' . getmypid();
$deadline = time() + $budget;

while (time() < $deadline) {
    $job = $claimer->claim($workerId);
    if ($job === null) {
        break; // nothing to do (or at capacity)
    }
    $state = $runner->run($job);
    fwrite(STDOUT, sprintf("job %d -> %s\n", (int) $job['id'], $state));
    if ($state === 'queued') {
        break; // hit the time budget mid-job; let the next cron tick continue
    }
}
exit(0);
