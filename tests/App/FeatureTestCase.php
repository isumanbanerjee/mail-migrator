<?php
declare(strict_types=1);

namespace App\Tests;

use App\App;
use App\Support\BillingConfig;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Encryptor;
use App\Support\Migrator;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Tests\Fakes\FakeConnectionChecker;

abstract class FeatureTestCase extends TestCase
{
    protected App $app;
    protected Session $session;
    protected FakeConnectionChecker $checker;

    /** Default keeps the paywall off so existing feature tests are unaffected. */
    protected array $billingEnv = ['PAYWALL_ENABLED' => 'false'];

    protected function setUp(): void
    {
        $pdo = Database::make(['driver' => 'sqlite', 'database' => ':memory:']);
        (new Migrator($pdo, $this->migrationsDir()))->migrate();
        $this->session = new Session();
        $this->checker = new FakeConnectionChecker([
            'imap.src' => ['ok' => true, 'folders' => ['INBOX'], 'error' => null],
            'imap.dst' => ['ok' => true, 'folders' => [], 'error' => null],
        ]);
        $config = Config::fromArray(['key' => Encryptor::generateKey(), 'db' => ['driver' => 'sqlite']]);
        $this->app = App::boot(dirname(__DIR__, 2), [
            'pdo' => $pdo,
            'session' => $this->session,
            'config' => $config,
            'connectionChecker' => $this->checker,
            'billingConfig' => new BillingConfig($this->billingEnv),
        ]);
    }

    protected function get(string $path): Response
    {
        return $this->app->handle(new Request('GET', $path, [], [], [], []));
    }

    protected function post(string $path, array $data = []): Response
    {
        $data['_csrf'] = Csrf::token($this->session);
        return $this->app->handle(new Request('POST', $path, [], $data, [], []));
    }

    protected function registerAndLogin(string $email = 'me@x.com'): int
    {
        return $this->app->auth->register('Me', $email, 'secretpw');
    }
}
