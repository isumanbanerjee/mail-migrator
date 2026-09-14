<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\JobRepository;
use App\Support\StopSignal;
use EmailMigration\FolderMapper;
use EmailMigration\Ledger\LedgerInterface;
use EmailMigration\MessageMigrator;
use EmailMigration\MigrationRunner;
use EmailMigration\Support\Logger;

final class JobRunner
{
    /** @var callable */
    private $ledgerFor;
    /** @var callable */
    private $clock;

    public function __construct(
        private MailboxFactoryInterface $mailboxes,
        private JobRepository $jobs,
        callable $ledgerFor,
        private int $timeBudgetSeconds = 50,
        ?callable $clock = null,
    ) {
        $this->ledgerFor = $ledgerFor;
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function run(array $job): string
    {
        $jobId = (int) $job['id'];
        $mb = $this->mailboxes->forJob($job);
        /** @var LedgerInterface $ledger */
        $ledger = ($this->ledgerFor)($jobId);
        $options = json_decode((string) ($job['options'] ?? '{}'), true) ?: [];
        $mapper = new FolderMapper((array) ($options['folder_map'] ?? []));
        $logger = new Logger('error');
        $migrator = new MessageMigrator($mb['writer'], $ledger, $logger);
        $runner = new MigrationRunner($mb['reader'], $mb['writer'], $ledger, $mapper, $migrator, $logger, [
            'dry_run' => ($job['mode'] ?? 'live') === 'dry_run',
            'since' => $options['since'] ?? null,
            'limit' => $options['limit'] ?? null,
            'throttle_ms' => (int) ($options['throttle_ms'] ?? 0),
        ]);

        $start = ($this->clock)();
        $saveCumulativeProgress = function (?string $currentFolder) use ($jobId, $ledger): void {
            $summary = $ledger->summary();
            $total = $summary['copied'] + $summary['skipped'] + $summary['failed'] + $summary['pending'];
            $this->jobs->saveProgress($jobId, [
                'total_messages' => $total, 'copied' => $summary['copied'], 'skipped' => $summary['skipped'],
                'failed' => $summary['failed'], 'current_folder' => $currentFolder,
                'percent' => $total > 0 ? (int) round(($summary['copied'] + $summary['skipped']) / $total * 100) : 0,
            ]);
        };
        $progress = function (array $s) use ($jobId, $start, $saveCumulativeProgress): void {
            $saveCumulativeProgress($s['folder'] ?? null);
            $state = $this->jobs->currentState($jobId);
            if ($state === 'paused') { throw new StopSignal('paused'); }
            if ($state === 'canceled') { throw new StopSignal('canceled'); }
            if (($this->clock)() - $start >= $this->timeBudgetSeconds) { throw new StopSignal('time'); }
        };

        try {
            $runner->run($progress);
            $saveCumulativeProgress(null);
            $this->jobs->systemTransition($jobId, 'completed');
            $this->jobs->releaseLock($jobId);
            return 'completed';
        } catch (StopSignal $s) {
            return $this->handleStop($jobId, $s->reason);
        } catch (\Throwable $e) {
            $this->jobs->systemTransition($jobId, 'failed', $e->getMessage());
            $this->jobs->releaseLock($jobId);
            return 'failed';
        }
    }

    private function handleStop(int $jobId, string $reason): string
    {
        if ($reason === 'time') {
            $this->jobs->systemTransition($jobId, 'queued');
            $this->jobs->releaseLock($jobId);
            return 'queued';
        }
        // paused / canceled: state already set by the user action; just release the lock.
        $this->jobs->releaseLock($jobId);
        return $reason;
    }
}
