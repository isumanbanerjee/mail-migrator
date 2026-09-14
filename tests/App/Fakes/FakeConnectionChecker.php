<?php
declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Services\ConnectionCheckerInterface;

final class FakeConnectionChecker implements ConnectionCheckerInterface
{
    /** @param array<string,array> $byHost host => result */
    public function __construct(private array $byHost) {}

    public function check(array $account): array
    {
        return $this->byHost[$account['host']] ?? ['ok' => false, 'folders' => [], 'error' => 'unknown host'];
    }
}
