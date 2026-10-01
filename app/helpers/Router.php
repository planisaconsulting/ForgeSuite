<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use ReflectionFunction;

/**
 * Maps a request method and path to a controller action.
 *
 * A path may contain {id} placeholders. The name inside the braces must match
 * the closure parameter in routes.php. Static paths are listed before
 * placeholders so /customers/new is not treated as an id.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable, auth: bool, csrf: bool, permission: ?string}> */
    private array $routes = [];

    public function get(string $path, callable $handler, bool $auth = true, ?string $permission = null): void
    {
        $this->add('GET', $path, $handler, $auth, false, $permission);
    }

    public function post(
        string $path,
        callable $handler,
        bool $auth = true,
        bool $csrf = true,
        ?string $permission = null
    ): void {
        $this->add('POST', $path, $handler, $auth, $csrf, $permission);
    }

    private function add(
        string $method,
        string $path,
        callable $handler,
        bool $auth,
        bool $csrf,
        ?string $permission
    ): void {
        $normalised = $this->normalise($path);
        $pattern = preg_replace('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', '(?P<$1>[^/]+)', $normalised) ?? $normalised;
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'auth' => $auth,
            'csrf' => $csrf,
            'permission' => $permission,
        ];
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = $this->normalise($path);

        $base = rtrim((string) config('app.base_path', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = $this->normalise(substr($path, strlen($base)) ?: '/');
        }

        $matched = null;
        $params = [];
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $allowed[] = $route['method'];
            if ($route['method'] !== $method || $matched !== null) {
                continue;
            }
            $matched = $route;
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = (string) $value;
                }
            }
        }

        if ($matched === null) {
            if ($allowed !== []) {
                http_response_code(405);
                header('Allow: ' . implode(', ', array_unique($allowed)));
            } else {
                http_response_code(404);
            }
            View::render('errors/404', [
                'title' => $allowed === [] ? 'Page not found' : 'Method not allowed',
                'activeNav' => '',
                'message' => $allowed === []
                    ? 'That page is not part of Sign-Forge.'
                    : 'That action is not available on this address.',
            ], auth_user() ? 'layouts/app' : 'layouts/auth');

            return;
        }

        if ($matched['auth']) {
            AuthMiddleware::handle($path);
        }

        if ($matched['permission'] !== null) {
            PermissionMiddleware::handle($matched['permission']);
        }

        if ($matched['csrf']) {
            Csrf::verify();
        }

        $handler = $matched['handler'];
        $ref = new ReflectionFunction($handler);
        $args = [];
        foreach ($ref->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            }
        }
        $handler(...$args);
    }

    private function normalise(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
