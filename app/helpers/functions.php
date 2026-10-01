<?php

declare(strict_types=1);

/**
 * Small functions used across controllers and views.
 *
 * SQL does not live here. Money formatting here is for display only.
 * The calculation services (still to be built) must not use these helpers
 * to price a quote. They must use decimal strings.
 */

function base_path(string $path = ''): string
{
    $root = SF_ROOT;
    if ($path === '') {
        return $root;
    }

    return $root . '/' . ltrim($path, '/');
}

/**
 * Loads config.example.php, then overlays config.local.php, then overlays
 * environment variables. Environment variables win so a host can inject the
 * database password without editing a file.
 *
 * @return array<string, mixed>
 */
function load_config(): array
{
    $config = require SF_ROOT . '/app/config/config.example.php';
    $localFile = SF_ROOT . '/app/config/config.local.php';

    if (is_file($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            $config = config_merge($config, $local);
        }
    }

    $envMap = [
        'SF_APP_ENV' => ['app', 'env'],
        'SF_APP_DEBUG' => ['app', 'debug'],
        'SF_APP_URL' => ['app', 'url'],
        'SF_APP_BASE_PATH' => ['app', 'base_path'],
        'SF_DB_HOST' => ['db', 'host'],
        'SF_DB_PORT' => ['db', 'port'],
        'SF_DB_NAME' => ['db', 'name'],
        'SF_DB_USER' => ['db', 'user'],
        'SF_DB_PASS' => ['db', 'pass'],
    ];

    foreach ($envMap as $envName => $path) {
        $value = getenv($envName);
        if ($value === false || $value === '') {
            continue;
        }
        if ($envName === 'SF_APP_DEBUG') {
            $value = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        if ($envName === 'SF_DB_PORT') {
            $value = (int) $value;
        }
        $config[$path[0]][$path[1]] = $value;
    }

    return $config;
}

/**
 * @param array<string, mixed> $base
 * @param array<string, mixed> $over
 * @return array<string, mixed>
 */
function config_merge(array $base, array $over): array
{
    foreach ($over as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = config_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }

    return $base;
}

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['sf_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

/** Escape a value for HTML text or an attribute. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    $base = rtrim((string) config('app.base_path', ''), '/');
    $path = '/' . ltrim($path, '/');

    if ($path === '/') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . $path;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function asset(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $full = base_path('public' . $path);
    $version = is_file($full) ? (string) filemtime($full) : '1';

    return url($path) . '?v=' . $version;
}

/**
 * Display a stored decimal as South African currency, for example R 1,234.56.
 * This only formats. It is not used to calculate totals.
 */
function money(string|int|float|null $amount): string
{
    $symbol = 'R';
    try {
        $configured = App\Services\SettingsService::get('currency_symbol', 'R');
        if ($configured !== null && $configured !== '') {
            $symbol = $configured;
        }
    } catch (Throwable) {
        $symbol = 'R';
    }

    $value = $amount === null ? '0' : (string) $amount;
    if (!is_numeric($value)) {
        $value = '0';
    }

    $negative = str_starts_with($value, '-');
    $value = ltrim($value, '+-');
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
    $fraction = str_pad($fraction, 2, '0');
    $cents = substr($fraction, 0, 2);
    $roundUp = isset($fraction[2]) && (int) $fraction[2] >= 5;
    if ($roundUp) {
        $asCents = (int) ($whole . $cents) + 1;
        $whole = (string) intdiv($asCents, 100);
        $cents = str_pad((string) ($asCents % 100), 2, '0', STR_PAD_LEFT);
    }

    $whole = ltrim($whole, '0');
    if ($whole === '') {
        $whole = '0';
    }
    $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;

    return ($negative ? '-' : '') . $symbol . ' ' . $whole . '.' . $cents;
}

function format_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($date, 0, 10));
    if ($dt === false) {
        return $date;
    }

    return $dt->format('d M Y');
}

function human_status(string $status): string
{
    return ucwords(strtolower(str_replace('_', ' ', $status)));
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'DRAFT' => 'text-bg-secondary',
        'SENT' => 'text-bg-primary',
        'ACCEPTED' => 'text-bg-success',
        'DECLINED' => 'text-bg-danger',
        'EXPIRED' => 'text-bg-warning',
        'CONVERTED' => 'text-bg-info',
        default => 'text-bg-secondary',
    };
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? $letters : 'SF';
}

function role_label(string $role): string
{
    $case = App\Domain\UserRole::tryFrom($role);

    return $case === null ? $role : $case->label();
}

function company_name(): string
{
    try {
        $name = App\Services\SettingsService::get('company_name', 'Sign-Forge Signs');
    } catch (Throwable) {
        $name = 'Sign-Forge Signs';
    }

    return ($name === null || $name === '') ? 'Sign-Forge Signs' : $name;
}

/**
 * @return array<string, mixed>|null
 */
function auth_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
        return null;
    }

    $found = (new App\Repositories\UserRepository())->find((int) $id);
    if ($found === null || (int) $found['active'] !== 1) {
        unset($_SESSION['user_id']);
        $user = null;

        return null;
    }

    $user = $found;

    return $user;
}

function require_login(string $path): void
{
    $user = auth_user();
    if ($user === null) {
        $_SESSION['intended'] = $path;
        redirect('/login');
    }

    $allowedWhilePasswordChange = ['/account/password', '/logout'];
    if ((int) $user['must_change_password'] === 1 && !in_array($path, $allowedWhilePasswordChange, true)) {
        redirect('/account/password');
    }
}

function safe_internal_path(string $path): string
{
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '://')) {
        return '/';
    }

    return $path;
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

/**
 * @return list<array{type: string, message: string}>
 */
function pull_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);

    return is_array($flashes) ? array_values($flashes) : [];
}

/** HTML hidden field. The token itself is escaped. */
function csrf_field(): string
{
    return App\Helpers\Csrf::field();
}

/**
 * Remember me keeps the sign-in cookie for 30 days.
 * Without it, the cookie is a browser session and disappears when the
 * browser closes. Sign-out clears either cookie.
 */
function remember_login(bool $remember): void
{
    if (!$remember) {
        unset($_SESSION['remember_me']);

        return;
    }

    $_SESSION['remember_me'] = 1;
    send_session_cookie(time() + 60 * 60 * 24 * 30);
}

function refresh_remember_me(): void
{
    if (empty($_SESSION['remember_me']) || empty($_SESSION['user_id'])) {
        return;
    }

    send_session_cookie(time() + 60 * 60 * 24 * 30);
}

function clear_session_cookie(): void
{
    send_session_cookie(time() - 42000);
}

function can(string $permission): bool
{
    return App\Services\AuthorizationService::allows(auth_user(), $permission);
}

function deny_access(string $message): never
{
    http_response_code(403);
    App\Helpers\View::render('errors/403', [
        'title' => 'Not allowed',
        'activeNav' => '',
        'message' => $message,
    ], auth_user() ? 'layouts/app' : 'layouts/auth');
    exit;
}

function abort_not_found(string $message = 'That record was not found.'): never
{
    http_response_code(404);
    App\Helpers\View::render('errors/404', [
        'title' => 'Not found',
        'activeNav' => '',
        'message' => $message,
    ], auth_user() ? 'layouts/app' : 'layouts/auth');
    exit;
}

function route_id(string $value): int
{
    if (!ctype_digit($value) || (int) $value < 1) {
        abort_not_found();
    }

    return (int) $value;
}

/**
 * @param array<string, mixed> $data
 */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function like_term(string $term): string
{
    $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term));

    return '%' . $term . '%';
}

function blank_to_null(mixed $value): ?string
{
    $value = trim((string) $value);

    return $value === '' ? null : $value;
}

/**
 * Checkbox plus a hidden 0 field. The last posted value wins.
 *
 * @param array<string, mixed> $input
 */
function posted_flag(array $input, string $key, int $default = 0): int
{
    if (!array_key_exists($key, $input)) {
        return $default;
    }

    return (string) $input[$key] === '1' ? 1 : 0;
}

/**
 * @param array<string, mixed> $customer
 */
function customer_label(array $customer): string
{
    $company = trim((string) ($customer['company_name'] ?? ''));
    $person = trim(((string) ($customer['first_name'] ?? '')) . ' ' . ((string) ($customer['last_name'] ?? '')));
    if (($customer['customer_type'] ?? '') === 'INDIVIDUAL') {
        return $person !== '' ? $person : ($company !== '' ? $company : 'Customer');
    }

    return $company !== '' ? $company : ($person !== '' ? $person : 'Customer');
}

/**
 * @param array<string, mixed> $old
 */
function old_value(array $old, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $old) || $old[$key] === null) {
        return $default;
    }

    return (string) $old[$key];
}

/**
 * @param array<string, string> $errors
 */
function field_error(array $errors, string $key): string
{
    if (!isset($errors[$key]) || $errors[$key] === '') {
        return '';
    }

    return '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>';
}

function is_checked(mixed $value): string
{
    return (string) $value === '1' ? 'checked' : '';
}

function nl_text(?string $text): string
{
    if ($text === null || $text === '') {
        return '';
    }

    return nl2br(e($text), false);
}

function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($value, 0, 19));
    if ($dt === false) {
        return format_date($value);
    }

    return $dt->format('d M Y H:i');
}

function enum_label(string $class, string $value): string
{
    if (!enum_exists($class)) {
        return $value;
    }
    $case = $class::tryFrom($value);
    if ($case === null) {
        return $value;
    }
    if (method_exists($case, 'label')) {
        return (string) $case->label();
    }

    return $value;
}

function list_status(): string
{
    $status = (string) ($_GET['status'] ?? 'active');

    return in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
}

/**
 * @param array<string, mixed>|null $row
 * @return array<string, mixed>
 */
function audit_decode(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    try {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return [];
    }

    return is_array($data) ? $data : [];
}

function send_session_cookie(int $expires): void
{
    $params = session_get_cookie_params();
    setcookie(session_name(), $expires < time() ? '' : session_id(), [
        'expires' => $expires,
        'path' => ($params['path'] ?? '') !== '' ? $params['path'] : '/',
        'domain' => (string) ($params['domain'] ?? ''),
        'secure' => (bool) ($params['secure'] ?? false),
        'httponly' => true,
        'samesite' => ($params['samesite'] ?? '') !== '' ? (string) $params['samesite'] : 'Lax',
    ]);
}
