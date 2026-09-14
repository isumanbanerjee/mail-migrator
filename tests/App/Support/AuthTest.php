<?php
declare(strict_types=1);

namespace App\Tests\Support;

use App\Repositories\UserRepository;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Migrator;
use App\Support\Session;
use App\Tests\TestCase;

final class AuthTest extends TestCase
{
    private function auth(int $max = 5): array
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        $session = new Session();
        $auth = new Auth(new UserRepository($pdo), $session, $pdo, $max, 15);
        return [$auth, $session];
    }

    public function test_register_logs_in(): void
    {
        [$auth] = $this->auth();
        $id = $auth->register('Bob', 'bob@x.com', 'secretpw');
        $this->assertTrue($auth->check());
        $this->assertSame($id, $auth->userId());
        $this->assertSame('Bob', $auth->user()['name']);
    }

    public function test_attempt_success_and_failure(): void
    {
        [$auth] = $this->auth();
        $auth->register('Bob', 'bob@x.com', 'secretpw');
        $auth->logout();
        $this->assertFalse($auth->check());

        $this->assertFalse($auth->attempt('bob@x.com', 'wrong', 'k'));
        $this->assertTrue($auth->attempt('bob@x.com', 'secretpw', 'k'));
        $this->assertTrue($auth->check());
    }

    public function test_lockout_after_max_attempts(): void
    {
        [$auth] = $this->auth(3);
        $auth->register('Bob', 'bob@x.com', 'secretpw');
        $auth->logout();

        for ($i = 0; $i < 3; $i++) {
            $auth->attempt('bob@x.com', 'wrong', 'k');
        }
        $this->assertTrue($auth->lockedOut('k'));
        // even correct password is refused while locked
        $this->assertFalse($auth->attempt('bob@x.com', 'secretpw', 'k'));
    }
}
