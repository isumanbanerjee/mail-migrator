<?php
declare(strict_types=1);

namespace App\Support;

use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:callable,3:bool}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler, bool $auth = false): void
    {
        $this->routes[] = [$method, $path, $handler, $auth];
    }

    public function dispatch(string $method, string $path): array
    {
        $routes = $this->routes;
        $dispatcher = simpleDispatcher(function (\FastRoute\RouteCollector $r) use ($routes): void {
            foreach ($routes as [$m, $p, $h, $a]) {
                $r->addRoute($m, $p, ['h' => $h, 'a' => $a]);
            }
        });
        $info = $dispatcher->dispatch($method, $path);
        return match ($info[0]) {
            Dispatcher::FOUND => ['status' => 'found', 'handler' => $info[1]['h'], 'auth' => $info[1]['a'], 'vars' => $info[2]],
            Dispatcher::METHOD_NOT_ALLOWED => ['status' => 'method_not_allowed', 'handler' => null, 'auth' => false, 'vars' => []],
            default => ['status' => 'not_found', 'handler' => null, 'auth' => false, 'vars' => []],
        };
    }
}
