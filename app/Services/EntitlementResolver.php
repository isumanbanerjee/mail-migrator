<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\EntitlementRepository;
use App\Support\BillingConfig;

final class EntitlementResolver
{
    /** @var callable */
    private $clock;

    public function __construct(
        private BillingConfig $cfg,
        private EntitlementRepository $ent,
        private MeteringService $meter,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function canRunJob(int $userId): array
    {
        if (!$this->cfg->enabled()) {
            return ['allowed' => true, 'reason' => 'unlimited'];
        }
        $e = $this->ent->for($userId);
        if ((int) $e['unlimited'] === 1) {
            return ['allowed' => true, 'reason' => 'unlimited'];
        }
        if (!empty($e['subscription_until']) && strtotime((string) $e['subscription_until']) > ($this->clock)()) {
            return ['allowed' => true, 'reason' => 'subscription'];
        }
        if ((int) $e['credits'] > 0) {
            return ['allowed' => true, 'reason' => 'credits'];
        }
        if ($this->meter->jobsUsed($userId) < $this->cfg->freeJobLimit()
            && $this->meter->emailsUsed($userId) < $this->cfg->freeEmailLimit()) {
            return ['allowed' => true, 'reason' => 'free'];
        }
        return ['allowed' => false, 'reason' => 'quota_exceeded'];
    }

    public function consumeCredit(int $userId, int $emails): void
    {
        if (!$this->cfg->enabled() || !$this->cfg->creditsEnabled() || $emails <= 0) {
            return;
        }
        $e = $this->ent->for($userId);
        if ((int) $e['unlimited'] === 1) {
            return;
        }
        if (!empty($e['subscription_until']) && strtotime((string) $e['subscription_until']) > ($this->clock)()) {
            return;
        }
        $this->ent->deductCredits($userId, $emails);
    }
}
