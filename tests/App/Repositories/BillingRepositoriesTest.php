<?php
declare(strict_types=1);

namespace App\Tests\Repositories;

use App\Repositories\EntitlementRepository;
use App\Repositories\PaymentRepository;
use App\Support\Database;
use App\Support\Migrator;
use App\Tests\TestCase;

final class BillingRepositoriesTest extends TestCase
{
    private function pdo(): \PDO
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        return $pdo;
    }

    public function test_payment_record_and_lookup_idempotent(): void
    {
        $repo = new PaymentRepository($this->pdo());
        $id = $repo->record(['user_id' => 1, 'gateway' => 'razorpay', 'product' => 'one_time',
            'gateway_ref' => 'order_1', 'amount' => '19.00', 'currency' => 'USD', 'status' => 'created']);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('order_1', $repo->findByRef('razorpay', 'order_1')['gateway_ref']);
        $repo->markPaid($id);
        $this->assertSame('paid', $repo->findByRef('razorpay', 'order_1')['status']);
        $this->assertNull($repo->findByRef('razorpay', 'nope'));
    }

    public function test_entitlement_grants(): void
    {
        $repo = new EntitlementRepository($this->pdo());
        $this->assertSame(0, (int) $repo->for(1)['unlimited']);
        $repo->grantUnlimited(1);
        $this->assertSame(1, (int) $repo->for(1)['unlimited']);
        $repo->addCredits(1, 500);
        $this->assertSame(500, (int) $repo->for(1)['credits']);
        $repo->deductCredits(1, 200);
        $this->assertSame(300, (int) $repo->for(1)['credits']);
        $repo->deductCredits(1, 9999);
        $this->assertSame(0, (int) $repo->for(1)['credits']); // floors at 0
        $repo->extendSubscription(1, '2030-01-01 00:00:00');
        $this->assertSame('2030-01-01 00:00:00', $repo->for(1)['subscription_until']);
    }
}
