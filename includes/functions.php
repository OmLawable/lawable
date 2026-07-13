<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!defined('LAWABLE_BASE_PATH')) {
    define('LAWABLE_BASE_PATH', '/lawable');
}

/**
 * Start a secure session for authentication.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('lawable_session');
    session_start();
}

/**
 * Store a flash message for the next request.
 */
function set_flash(string $type, string $message): void
{
    start_secure_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Read and clear the flash message.
 */
function get_flash(): ?array
{
    start_secure_session();

    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

/**
 * Generate or reuse a CSRF token.
 */
function csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a posted CSRF token.
 */
function verify_csrf_token(?string $token = null): void
{
    start_secure_session();

    $postedToken = $token ?? ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $postedToken)) {
        throw new RuntimeException('Invalid CSRF token. Please try again.');
    }
}

/**
 * Redirect to a local path.
 */
function redirect(string $path): void
{
    if (preg_match('#^https?://#', $path) || str_starts_with($path, '/')) {
        header('Location: ' . $path);
        exit;
    }

    header('Location: ' . LAWABLE_BASE_PATH . '/' . ltrim($path, '/'));
    exit;
}

/**
 * Escape output safely for HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Check whether the user is authenticated.
 */
function is_logged_in(): bool
{
    start_secure_session();
    return !empty($_SESSION['user']);
}

/**
 * Get the currently logged-in user.
 */
function current_user(): ?array
{
    start_secure_session();
    return $_SESSION['user'] ?? null;
}

/**
 * Ensure the user is logged in, optionally enforcing a role.
 */
function require_login(?string $requiredRole = null): array
{
    start_secure_session();

    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('pages/login.php');
    }

    $user = current_user();

    if ($requiredRole && ($user['role'] ?? '') !== $requiredRole) {
        set_flash('error', 'You do not have permission to access that page.');
        redirect('pages/dashboard.php');
    }

    return $user;
}

/**
 * Send a JSON response and exit.
 */
function json_response(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log out the user.
 */
function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

/**
 * Load optional Turnstile secrets from a file outside the web root.
 */
function get_turnstile_config(): array
{
    $candidates = [];

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lawable-secrets.php';
        $candidates[] = dirname($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . 'lawable-secrets.php';
    }

    $candidates[] = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . 'lawable-secrets.php';
    $candidates[] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lawable-secrets.php';
    $candidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'lawable-secrets.php';
    $candidates[] = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'lawable-secrets.php';

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $config = require $candidate;
            if (is_array($config)) {
                return $config;
            }
        }
    }

    return [];
}

/**
 * Get the configured Turnstile site key.
 */
function get_turnstile_site_key(): string
{
    $config = get_turnstile_config();
    return (string) ($config['turnstile_site_key'] ?? $config['site_key'] ?? '');
}

/**
 * Get the configured Turnstile secret key.
 */
function get_turnstile_secret_key(): string
{
    $config = get_turnstile_config();
    return (string) ($config['turnstile_secret_key'] ?? $config['secret_key'] ?? '');
}

/**
 * Verify a Turnstile token with Cloudflare's siteverify API.
 *
 * The check is automatically skipped when:
 * 1. Turnstile is not configured (secret key is empty), OR
 * 2. Running on localhost/127.0.0.1 (local development)
 *
 * This allows login and registration to work without completing
 * the CAPTCHA during local development. In production you must
 * configure real Turnstile keys in lawable-secrets.php.
 */
function verify_turnstile_token(string $token): bool
{
    // Skip on localhost for easy local development
    $host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    $secretKey = get_turnstile_secret_key();
    if ($secretKey === '') {
        return true; // Turnstile not configured — skip check
    }
    if ($token === '') {
        return false;
    }

    $postData = http_build_query([
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 10,
            ],
        ]);
        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    }

    if ($response === false || $response === '') {
        return false;
    }

    $result = json_decode($response, true);
    return !empty($result['success']);
}

/**
 * Validate an email address.
 */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
