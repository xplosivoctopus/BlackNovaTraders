<?php

function bnt_cookie_options(): array
{
    global $cookie_path, $cookie_domain;

    $path = '/';
    if (isset($cookie_path) && is_string($cookie_path) && $cookie_path !== '') {
        $path = $cookie_path;
    }

    $options = [
        'expires' => time() + (3600 * 24 * 365),
        'path' => $path,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    if (isset($cookie_domain) && is_string($cookie_domain) && $cookie_domain !== '') {
        $options['domain'] = $cookie_domain;
    }

    return $options;
}

function bnt_password_is_hashed(string $storedPassword): bool
{
    return str_starts_with($storedPassword, '$2') || str_starts_with($storedPassword, '$argon2');
}

function bnt_clear_login_cookie(): void
{
    $options = bnt_cookie_options();
    $options['expires'] = time() - 3600;

    setcookie('userpass', '', $options);
    setcookie('userpass', '', time() - 3600);
}

function bnt_set_login_cookie(string $username): void
{
    setcookie('userpass', $username, bnt_cookie_options());
}

function bnt_password_needs_upgrade(string $storedPassword): bool
{
    return !bnt_password_is_hashed($storedPassword);
}

function bnt_password_verify_legacy(string $plainPassword, string $storedPassword): bool
{
    if ($storedPassword === '') {
        return false;
    }

    if (bnt_password_is_hashed($storedPassword)) {
        return password_verify($plainPassword, $storedPassword);
    }

    return hash_equals($storedPassword, $plainPassword);
}

function bnt_password_hash_value(string $plainPassword): string
{
    return password_hash($plainPassword, PASSWORD_DEFAULT);
}

function bnt_render_plain_error_page(int $statusCode, string $title, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>"
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . "</title></head><body><h1>"
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . "</h1><p>"
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . "</p></body></html>";
}

function bnt_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function bnt_csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(bnt_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function bnt_validate_csrf(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return is_string($submittedToken)
        && is_string($sessionToken)
        && $submittedToken !== ''
        && hash_equals($sessionToken, $submittedToken);
}

function bnt_require_csrf(): void
{
    if (bnt_validate_csrf()) {
        return;
    }

    if (!defined('BNT_HEADER_RENDERED')) {
        bnt_render_plain_error_page(400, 'Security Check Failed', 'Security check failed. Please retry from the original form.');
    } else {
        http_response_code(400);
        echo 'Security check failed. Please retry from the original form.';
        if (!defined('BNT_FOOTER_RENDERED')) {
            include 'footer.php';
        }
    }

    exit;
}

function bnt_get_current_playerinfo(): ?array
{
    global $db;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['logged_in']) || empty($_SESSION['username'])) {
        return null;
    }

    $result = $db->Execute(
        "SELECT ship_id, email, character_name FROM {$db->prefix}ships WHERE email=? LIMIT 1",
        array($_SESSION['username'])
    );

    if (!$result || $result->EOF) {
        return null;
    }

    return $result->fields;
}

function bnt_is_admin_user(?array $playerinfo = null): bool
{
    global $admin_list, $admin_mail;

    if ($playerinfo === null) {
        $playerinfo = bnt_get_current_playerinfo();
    }

    if (!$playerinfo) {
        return false;
    }

    if (!empty($admin_mail) && isset($playerinfo['email']) && strcasecmp($playerinfo['email'], $admin_mail) === 0) {
        return true;
    }

    foreach ($admin_list as $admin) {
        if (($admin['role'] ?? '') !== 'developer' && ($admin['role'] ?? '') !== 'admin') {
            continue;
        }

        if (isset($playerinfo['character_name'], $admin['character']) && strcasecmp($playerinfo['character_name'], $admin['character']) === 0) {
            return true;
        }
    }

    return false;
}

function bnt_admin_secret_matches(?string $submittedSecret = null): bool
{
    global $adminpass;

    return is_string($submittedSecret)
        && $submittedSecret !== ''
        && is_string($adminpass)
        && $adminpass !== ''
        && hash_equals($adminpass, $submittedSecret);
}

function bnt_is_cli_admin_context(): bool
{
    return PHP_SAPI === 'cli';
}

function bnt_require_admin(bool $allowCli = false): void
{
    if (bnt_is_admin_user()) {
        return;
    }

    if ($allowCli && bnt_is_cli_admin_context()) {
        return;
    }

    if (!defined('BNT_HEADER_RENDERED')) {
        bnt_render_plain_error_page(403, 'Access Denied', 'Administrator access required.');
    } else {
        http_response_code(403);
        echo 'Administrator access required.';
        if (!defined('BNT_FOOTER_RENDERED')) {
            include 'footer.php';
        }
    }

    exit;
}
