<?php
// ============================================================
// backend/config/db.php
// ============================================================

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');          // blank for XAMPP default
define('DB_NAME',    'cert_system');
define('DB_CHARSET', 'utf8mb4');

$__envFile = dirname(__DIR__, 2) . '/.env';
if (is_readable($__envFile)) {
    foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $__line) {
        $__line = trim($__line);
        if ($__line === '' || (isset($__line[0]) && $__line[0] === '#')) {
            continue;
        }
        if (strpos($__line, '=') === false) {
            continue;
        }
        [$__k, $__v] = explode('=', $__line, 2);
        $__k = trim($__k);
        $__v = trim($__v, " \t\"'");
        if ($__k !== '') {
            putenv($__k . '=' . $__v);
        }
    }
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function getSetting(string $key, ?string $default = null): ?string {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $db  = getDB();
        $st  = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $row = $st->fetchColumn();
        $cache[$key] = $row !== false ? (string) $row : $default;
    } catch (Throwable $e) {
        error_log(
            'getSetting failed for key "' . $key . '": ' . $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function appPublicUrl(): string {
    static $u = null;
    if ($u === null) {
        $fromEnv = getenv('APP_PUBLIC_URL');
        $base    = $fromEnv ?: getSetting('app_public_url', 'https://localhost/Blockchain-cert-system');
        $u       = rtrim((string) $base, '/');
        if (getenv('APP_ENV') === 'production' && preg_match('#^http://#i', $u)) {
            error_log(
                'cert_system: app_public_url must use HTTPS in production for QR/verify integrity (MITM). Value: ' . $u
            );
        }
    }
    return $u;
}

function contractAddress(): string {
    static $a = null;
    if ($a === null) {
        $fromEnv = getenv('CONTRACT_ADDRESS');
        $a       = $fromEnv ?: getSetting('contract_address', '');
        $a       = is_string($a) ? trim($a) : '';
    }
    return $a;
}

function isContractConfigured(): bool {
    $a = contractAddress();
    return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $a);
}

function sepoliaRpcUrl(): string {
    $fromEnv = getenv('SEPOLIA_RPC_URL');
    if ($fromEnv && is_string($fromEnv) && $fromEnv !== '') {
        return $fromEnv;
    }
    return (string) getSetting('sepolia_rpc_url', 'https://ethereum-sepolia-rpc.publicnode.com');
}

define('APP_NAME',     'LMU Certificate System');
define('UPLOAD_PATH',  __DIR__ . '/../../uploads/');
define('CERT_PREFIX',  'LMU');
define('IPFS_API',     'http://127.0.0.1:5001/api/v0');
define('IPFS_GATEWAY', 'https://ipfs.io/ipfs/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
