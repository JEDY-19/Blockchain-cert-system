<?php
// ============================================================
// backend/api/finalize_certificate.php
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$certId = trim($input['certificate_id'] ?? '');
$txHash = trim($input['tx_hash']        ?? '');

if (empty($certId) || empty($txHash)) {
    echo json_encode(['success' => false, 'message' => 'certificate_id and tx_hash are required.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare("
    SELECT id FROM certificates
    WHERE certificate_id = ? AND issued_by = ? AND status = 'pending'
    LIMIT 1
");
$stmt->execute([$certId, $_SESSION['admin_id']]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Certificate not found or already finalized.']);
    exit;
}

$db->prepare("
    UPDATE certificates
    SET blockchain_tx_hash = ?,
        blockchain_address = ?,
        status = 'issued'
    WHERE certificate_id = ?
")->execute([$txHash, CONTRACT_ADDRESS, $certId]);

echo json_encode([
    'success'  => true,
    'message'  => 'Certificate successfully recorded on blockchain!',
    'tx_hash'  => $txHash,
    'explorer' => 'https://sepolia.etherscan.io/tx/' . $txHash,
]);
