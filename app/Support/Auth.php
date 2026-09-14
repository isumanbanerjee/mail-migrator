<?php
declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;
use PDO;

final class Auth
{
    public function __construct(
        private UserRepository $users,
        private Session $session,
        private PDO $pdo,
        private int $maxAttempts = 5,
        private int $lockMinutes = 15,
    ) {}

    public function register(string $name, string $email, string $password): int
    {
        // Prefer argon2id, but fall back to bcrypt on PHP builds compiled
        // without argon2 support (password_verify auto-detects the algorithm).
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $id = $this->users->create($name, $email, password_hash($password, $algo));
        $this->session->put('_uid', $id);
        $this->session->regenerateId();
        return $id;
    }

    public function attempt(string $email, string $password, string $throttleKey): bool
    {
        if ($this->lockedOut($throttleKey)) {
            return false;
        }
        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $this->recordFailure($throttleKey);
            return false;
        }
        $this->resetThrottle($throttleKey);
        $this->session->put('_uid', (int) $user['id']);
        $this->session->regenerateId();
        return true;
    }

    public function logout(): void { $this->session->forget('_uid'); }

    public function userExists(string $email): bool
    {
        return $this->users->findByEmail($email) !== null;
    }

    public function check(): bool { return $this->userId() !== null; }
    public function userId(): ?int
    {
        $id = $this->session->get('_uid');
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }
    public function user(): ?array
    {
        $id = $this->userId();
        return $id === null ? null : $this->users->findById($id);
    }

    public function lockedOut(string $key): bool
    {
        $row = $this->throttleRow($key);
        if ($row === null || $row['locked_until'] === null) {
            return false;
        }
        return strtotime((string) $row['locked_until']) > time();
    }

    private function recordFailure(string $key): void
    {
        $row = $this->throttleRow($key);
        $now = date('Y-m-d H:i:s');
        if ($row === null) {
            $stmt = $this->pdo->prepare('INSERT INTO login_throttle (throttle_key, attempts, updated_at) VALUES (:k, 1, :t)');
            $stmt->execute([':k' => $key, ':t' => $now]);
            return;
        }
        $attempts = (int) $row['attempts'] + 1;
        $lockedUntil = $attempts >= $this->maxAttempts ? date('Y-m-d H:i:s', time() + $this->lockMinutes * 60) : null;
        $stmt = $this->pdo->prepare('UPDATE login_throttle SET attempts = :a, locked_until = :l, updated_at = :t WHERE throttle_key = :k');
        $stmt->execute([':a' => $attempts, ':l' => $lockedUntil, ':t' => $now, ':k' => $key]);
    }

    private function resetThrottle(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_throttle WHERE throttle_key = :k');
        $stmt->execute([':k' => $key]);
    }

    private function throttleRow(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM login_throttle WHERE throttle_key = :k');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
