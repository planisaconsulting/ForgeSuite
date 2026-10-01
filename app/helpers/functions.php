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
        $configured = App\Models\Setting::get('currency_symbol', 'R');
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
    $name = App\Models\Setting::get('company_name', 'Sign-Forge Signs');

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

    $found = App\Models\User::find((int) $id);
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
