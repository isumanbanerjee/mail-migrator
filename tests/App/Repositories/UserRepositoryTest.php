<?php
declare(strict_types=1);

namespace App\Tests\Repositories;

use App\Repositories\UserRepository;
use App\Support\Database;
use App\Support\Migrator;
use App\Tests\TestCase;

final class UserRepositoryTest extends TestCase
{
    public function test_create_and_find(): void
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        $repo = new UserRepository($pdo);

        $id = $repo->create('Bob', 'bob@x.com', password_hash('pw', PASSWORD_ARGON2ID));
        $this->assertGreaterThan(0, $id);

        $byEmail = $repo->findByEmail('bob@x.com');
        $this->assertSame('Bob', $byEmail['name']);
        $this->assertSame($id, (int) $byEmail['id']);
        $this->assertSame($id, (int) $repo->findById($id)['id']);
        $this->assertNull($repo->findByEmail('nobody@x.com'));
    }
}
