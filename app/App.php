<?php
declare(strict_types=1);

namespace App;

use App\Repositories\JobRepository;
use App\Repositories\UserRepository;
use App\Services\ConnectionTester;
use App\Services\ImapConnectionChecker;
use App\Support\Auth;
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

    public static function boot(string $basePath, array $overrides = []): self
    {
        $app = new self();
        $app->config = $overrides['config'] ?? Config::load($basePath);
        $app->pdo = $overrides['pdo'] ?? Database::make($app->config->db());
        $app->session = $overrides['session'] ?? Session::fromPhp();
        $app->encryptor = new Encryptor($app->config->appKey());
        $app->users = new UserRepository($app->pdo);
        $app->jobs = new JobRepository($app->pdo);
        $app->auth = new Auth($app->users, $app->session, $app->pdo);
        $app->view = new View($basePath . '/views');
        $checker = $overrides['connectionChecker'] ?? new ImapConnectionChecker();
        $app->tester = new ConnectionTester($checker);
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
        // Later tasks append their routes here (jobs, dashboard).
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
        if ($req->isPost() && !Csrf::verify($this->session, (string) $req->input('_csrf'))) {
            return Response::html('CSRF token mismatch', 419);
        }
        return ($d['handler'])($req, $d['vars']);
    }
}
