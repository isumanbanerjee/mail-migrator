<?php
declare(strict_types=1);

namespace App;

use App\Repositories\EntitlementRepository;
use App\Repositories\JobRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use App\Services\ConnectionTester;
use App\Services\EntitlementResolver;
use App\Services\ImapConnectionChecker;
use App\Services\MeteringService;
use App\Services\Payments\CurlHttpClient;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\HttpClientInterface;
use App\Support\Auth;
use App\Support\BillingConfig;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Encryptor;
use App\Support\Request;
use App\Support\Response;
use App\Support\Router;
use App\Support\Session;
use App\Support\View;

final class App
{
    public Config $config;
    public \PDO $pdo;
    public Session $session;
    public Encryptor $encryptor;
    public UserRepository $users;
    public JobRepository $jobs;
    public Auth $auth;
    public View $view;
    public ConnectionTester $tester;
    public Router $router;
    public BillingConfig $billingConfig;
    public HttpClientInterface $httpClient;
    public GatewayFactory $gatewayFactory;
    public PaymentRepository $payments;
    public EntitlementRepository $entitlements;
    public MeteringService $metering;
    public EntitlementResolver $resolver;

    public static function boot(string $basePath, array $overrides = []): self
    {
        $app = new self();
        $app->config = $overrides['config'] ?? Config::load($basePath);
        $app->config->validate();
        $app->pdo = $overrides['pdo'] ?? Database::make($app->config->db());
        $app->session = $overrides['session'] ?? Session::fromPhp();
        $app->encryptor = new Encryptor($app->config->appKey());
        $app->users = new UserRepository($app->pdo);
        $app->jobs = new JobRepository($app->pdo);
        $app->auth = new Auth($app->users, $app->session, $app->pdo);
        $app->view = new View($basePath . '/views', $app->auth);
        $checker = $overrides['connectionChecker'] ?? new ImapConnectionChecker();
        $app->tester = new ConnectionTester($checker);
        $app->billingConfig = $overrides['billingConfig'] ?? BillingConfig::fromEnv();
        $app->httpClient = $overrides['httpClient'] ?? new CurlHttpClient();
        $app->gatewayFactory = new GatewayFactory($app->httpClient, $app->billingConfig);
        $app->payments = new PaymentRepository($app->pdo);
        $app->entitlements = new EntitlementRepository($app->pdo);
        $app->metering = new MeteringService($app->pdo);
        $app->resolver = new EntitlementResolver($app->billingConfig, $app->entitlements, $app->metering);
        $app->router = new Router();
        $app->registerRoutes();
        return $app;
    }

    private function registerRoutes(): void
    {
        $this->router->add('GET', '/', function (Request $req): Response {
            return Response::redirect($this->auth->check() ? '/dashboard' : '/login');
        });

        $authC = new \App\Http\Controllers\AuthController($this->auth, $this->view, $this->session);
        $this->router->add('GET', '/register', fn(Request $r) => $authC->showRegister($r));
        $this->router->add('POST', '/register', fn(Request $r) => $authC->register($r));
        $this->router->add('GET', '/login', fn(Request $r) => $authC->showLogin($r));
        $this->router->add('POST', '/login', fn(Request $r) => $authC->login($r));
        $this->router->add('POST', '/logout', fn(Request $r) => $authC->logout($r), true);

        $jobC = new \App\Http\Controllers\JobController($this->jobs, $this->encryptor, $this->tester, $this->auth, $this->view, $this->session, $this->resolver, $this->billingConfig);
        $this->router->add('GET', '/jobs/create', fn(Request $r) => $jobC->create($r), true);
        $this->router->add('POST', '/jobs', fn(Request $r) => $jobC->store($r), true);
        $this->router->add('POST', '/jobs/test-connection', fn(Request $r) => $jobC->testConnection($r), true);
        $this->router->add('GET', '/jobs/{id:\d+}', fn(Request $r, array $v) => $jobC->show($r, $v), true);
        $this->router->add('GET', '/jobs/{id:\d+}/edit', fn(Request $r, array $v) => $jobC->edit($r, $v), true);
        $this->router->add('POST', '/jobs/{id:\d+}', fn(Request $r, array $v) => $jobC->update($r, $v), true);
        $this->router->add('POST', '/jobs/{id:\d+}/delete', fn(Request $r, array $v) => $jobC->destroy($r, $v), true);
        $this->router->add('POST', '/jobs/{id:\d+}/queue', fn(Request $r, array $v) => $jobC->queue($r, $v), true);
        $this->router->add('POST', '/jobs/{id:\d+}/cancel', fn(Request $r, array $v) => $jobC->cancel($r, $v), true);
        $this->router->add('POST', '/jobs/{id:\d+}/pause', fn(Request $r, array $v) => $jobC->pause($r, $v), true);
        $this->router->add('POST', '/jobs/{id:\d+}/resume', fn(Request $r, array $v) => $jobC->resume($r, $v), true);
        $this->router->add('POST', '/jobs/{id:\d+}/retry', fn(Request $r, array $v) => $jobC->retry($r, $v), true);

        $dashC = new \App\Http\Controllers\DashboardController($this->jobs, $this->auth, $this->view, $this->session);
        $this->router->add('GET', '/dashboard', fn(Request $r) => $dashC->index($r), true);
        $this->router->add('GET', '/jobs/{id:\d+}/progress', fn(Request $r, array $v) => $dashC->progress($r, $v), true);

        $billingC = new \App\Http\Controllers\BillingController(
            $this->billingConfig, $this->gatewayFactory, $this->payments, $this->entitlements,
            $this->metering, $this->resolver, $this->auth, $this->view, $this->session,
        );
        $this->router->add('GET', '/billing', fn(Request $r) => $billingC->index($r), true);
        $this->router->add('POST', '/billing/checkout', fn(Request $r) => $billingC->checkout($r), true);
        $this->router->add('GET', '/billing/success', fn(Request $r) => $billingC->success($r), true);
        $this->router->add('GET', '/billing/cancel', fn(Request $r) => $billingC->cancel($r), true);

        $webhookC = new \App\Http\Controllers\WebhookController(
            $this->gatewayFactory, $this->payments, $this->entitlements, $this->billingConfig,
        );
        $this->router->add('POST', '/webhooks/{gateway}', fn(Request $r, array $v) => $webhookC->handle($r, $v));
    }

    public function handle(Request $req): Response
    {
        $d = $this->router->dispatch($req->method(), $req->path());
        if ($d['status'] === 'not_found') {
            return Response::html('Not Found', 404);
        }
        if ($d['status'] === 'method_not_allowed') {
            return Response::html('Method Not Allowed', 405);
        }
        if ($d['auth'] && !$this->auth->check()) {
            return Response::redirect('/login');
        }
        if ($req->isPost() && !str_starts_with($req->path(), '/webhooks/') && !Csrf::verify($this->session, (string) $req->input('_csrf'))) {
            return Response::html('CSRF token mismatch', 419);
        }
        return ($d['handler'])($req, $d['vars']);
    }
}
