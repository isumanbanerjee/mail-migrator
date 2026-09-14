<?php
declare(strict_types=1);

namespace App\Tests\Repositories;

use App\Repositories\JobRepository;
use App\Support\Database;
use App\Support\Migrator;
use App\Tests\TestCase;

final class JobRepositoryTest extends TestCase
{
    private function repo(): JobRepository
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        return new JobRepository($pdo);
    }

    private function data(string $name = 'Job A'): array
    {
        return [
            'name' => $name, 'mode' => 'live',
            'source_host' => 'imap.src', 'source_port' => 993, 'source_encryption' => 'ssl',
            'source_username_enc' => 'ENCu', 'source_password_enc' => 'ENCp',
            'dest_host' => 'imap.dst', 'dest_port' => 993, 'dest_encryption' => 'ssl',
            'dest_username_enc' => 'ENCu2', 'dest_password_enc' => 'ENCp2',
            'options' => '{"batch_size":200}',
        ];
    }

    public function test_create_find_list_scoped_by_user(): void
    {
        $repo = $this->repo();
        $idA = $repo->create(1, $this->data('A'));
        $repo->create(2, $this->data('B')); // other user

        $this->assertSame('A', $repo->find($idA, 1)['name']);
        $this->assertSame('draft', $repo->find($idA, 1)['state']);
        $this->assertNull($repo->find($idA, 2)); // user 2 cannot see user 1's job
        $this->assertCount(1, $repo->listForUser(1));
        $this->assertCount(1, $repo->listForUser(2));
    }

    public function test_update_delete_transition_are_scoped(): void
    {
        $repo = $this->repo();
        $id = $repo->create(1, $this->data('A'));

        $this->assertTrue($repo->update($id, 1, ['name' => 'Renamed'] + $this->data('A')));
        $this->assertSame('Renamed', $repo->find($id, 1)['name']);
        $this->assertFalse($repo->update($id, 2, $this->data('A'))); // wrong user

        $this->assertTrue($repo->transition($id, 1, 'queued'));
        $this->assertSame('queued', $repo->find($id, 1)['state']);
        $this->assertFalse($repo->transition($id, 2, 'canceled'));

        $this->assertFalse($repo->delete($id, 2));
        $this->assertTrue($repo->delete($id, 1));
        $this->assertNull($repo->find($id, 1));
    }
}
