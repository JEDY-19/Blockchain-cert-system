<?php
// ============================================================
// backend/config/db.php
// ============================================================

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');          // blank for XAMPP default
define('DB_NAME',    'cert_system');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

define('APP_NAME',         'LMU Certificate System');
define('APP_URL',          'http://localhost/Project');
define('UPLOAD_PATH',      __DIR__ . '/../../uploads/');
define('CERT_PREFIX',      'LMU');
define('CONTRACT_ADDRESS', '0xYourContractAddressHere');  // update after Remix deploy
define('IPFS_API',         'http://127.0.0.1:5001/api/v0');
define('IPFS_GATEWAY',     'https://ipfs.io/ipfs/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
