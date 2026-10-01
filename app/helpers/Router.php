<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Maps a request method and path to a controller action.
 *
 * Routes are listed in app/routes.php. The optional app.base_path prefix is
 * removed before matching, so the same routes work on a domain root or in a
 * subdirectory on shared hosting.
 */
final class Router
{
    /** @var array<string, array{handler: callable, auth: bool, csrf: bool}> */
    private array $routes = [];

    public function get(string $path, callable $handler, bool $auth = true): void
    {
        $this->add('GET', $path, $handler, $auth, false);
    }

    public function post(string $path, callable $handler, bool $auth = true, bool $csrf = true): void
    {
        $this->add('POST', $path, $handler, $auth, $csrf);
    }

    private function add(string $method, string $path, callable $handler, bool $auth, bool $csrf): void
    {
        $this->routes[$method . ' ' . $this->normalise($path)] = [
            'handler' => $handler,
            'auth' => $auth,
            'csrf' => $csrf,
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

        $route = $this->routes[$method . ' ' . $path] ?? null;

        if ($route === null) {
            $allowed = $this->allowedMethods($path);
            if ($allowed !== []) {
                http_response_code(405);
                header('Allow: ' . implode(', ', $allowed));
                View::render('errors/404', [
                    'title' => 'Method not allowed',
                    'activeNav' => '',
                    'message' => 'That action is not available on this address.',
                ], auth_user() ? 'layouts/app' : 'layouts/auth');

                return;
            }

            http_response_code(404);
            View::render('errors/404', [
                'title' => 'Page not found',
                'activeNav' => '',
                'message' => 'That page is not part of Sign-Forge.',
            ], auth_user() ? 'layouts/app' : 'layouts/auth');

            return;
        }

        if ($route['auth']) {
            require_login($path);
        }

        if ($route['csrf']) {
            Csrf::verify();
        }

        ($route['handler'])();
    }

    private function normalise(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * @return list<string>
     */
    private function allowedMethods(string $path): array
    {
        $allowed = [];
        foreach (['GET', 'POST'] as $method) {
            if (isset($this->routes[$method . ' ' . $path])) {
                $allowed[] = $method;
            }
        }

        return $allowed;
    }
}
