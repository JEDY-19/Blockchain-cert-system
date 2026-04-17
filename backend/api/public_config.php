<?php
// ============================================================
// backend/api/public_config.php — contract + chain hints for frontend
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (getenv('APP_ENV') === 'production') {
    $u = getenv('APP_PUBLIC_URL') ?: getSetting('app_public_url', '');
    if ($u !== '' && preg_match('#^http://#i', (string) $u)) {
        error_log('cert_system: app_public_url must be HTTPS in production (see schema / .env).');
    }
}

$rpc = sepoliaRpcUrl();
$safeRpc = $rpc;
if (preg_match('#/v3#', $rpc)) {
    $safeRpc = 'https://ethereum-sepolia-rpc.publicnode.com';
}

echo json_encode([
    'success'               => true,
    'contract_address'      => contractAddress(),
    'contract_configured'   => isContractConfigured(),
    'chain_id_hex'          => '0xaa36a7',
    'app_public_url'        => appPublicUrl(),
    'sepolia_rpc_url'       => $safeRpc,
]);
