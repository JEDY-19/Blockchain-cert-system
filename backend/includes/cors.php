<?php
// ============================================================
// Credentialed CORS — set CORS_ALLOWED_ORIGINS in .env (comma-separated origins: scheme://host[:port] only)
// ============================================================

/**
 * @return list<string>
 */
function cors_allowed_origins_list(): array {
    $raw = getenv('CORS_ALLOWED_ORIGINS');
    if ($raw && is_string($raw) && trim($raw) !== '') {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
    if (getenv('APP_ENV') === 'production') {
        error_log(
            'cert_system: CORS_ALLOWED_ORIGINS is not set in production; credentialed CORS allowlist is empty (fail closed).'
        );
        return [];
    }
    return [
        'http://localhost',
        'http://127.0.0.1',
        'https://localhost',
        'https://127.0.0.1',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
    ];
}

/**
 * @return string|null Matched Origin header value, null if no Origin sent (same-origin), '' if rejected
 */
function cors_match_request_origin(): ?string {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return null;
    }
    foreach (cors_allowed_origins_list() as $allowed) {
        if ($allowed !== '' && strcasecmp($origin, $allowed) === 0) {
            return $origin;
        }
    }
    return '';
}

function cors_apply_credentials_if_allowed(): void {
    $m = cors_match_request_origin();
    if ($m === null || $m === '') {
        return;
    }
    header('Access-Control-Allow-Origin: ' . $m);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

/**
 * Call early; exits on OPTIONS after sending headers.
 */
function cors_handle_options_preflight(string $methods): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        http_response_code(204);
        exit;
    }
    $m = cors_match_request_origin();
    if ($m === null || $m === '') {
        http_response_code(403);
        exit;
    }
    header('Access-Control-Allow-Origin: ' . $m);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: ' . $methods);
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
    http_response_code(204);
    exit;
}
