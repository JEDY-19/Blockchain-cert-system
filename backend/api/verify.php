<?php
// ============================================================
// backend/api/verify.php
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';

$certId = trim($_GET['id']    ?? '');
$hash   = trim($_GET['hash']  ?? '');

if (empty($certId) && empty($hash)) {
    echo json_encode(['success' => false, 'message' => 'Provide a certificate ID or SHA-256 hash.']);
    exit;
}

$db = getDB();

$sql = "
    SELECT c.*, s.full_name, s.matric_number, s.department, s.faculty,
           s.degree_class, s.graduation_year, s.email,
           a.full_name AS issued_by_name
    FROM certificates c
    JOIN students s ON c.student_id = s.id
    JOIN admins a   ON c.issued_by  = a.id
    WHERE
";

if (!empty($certId)) {
    $stmt = $db->prepare($sql . " c.certificate_id = ? LIMIT 1");
    $stmt->execute([$certId]);
} else {
    $stmt = $db->prepare($sql . " c.sha256_hash = ? LIMIT 1");
    $stmt->execute([ltrim($hash, '0x')]);
}

$cert = $stmt->fetch();

if (!$cert) {
    $db->prepare("
        INSERT INTO verification_logs (certificate_id, verifier_ip, result)
        VALUES (?, ?, 'invalid')
    ")->execute([$certId ?: $hash, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

    echo json_encode(['success' => false, 'valid' => false, 'message' => 'Certificate not found. It may be forged or invalid.']);
    exit;
}

// Log the verification
$db->prepare("
    INSERT INTO verification_logs
        (certificate_id, verifier_ip, verifier_email, organization, result)
    VALUES (?, ?, ?, ?, ?)
")->execute([
    $cert['certificate_id'],
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    trim($_GET['email'] ?? ''),
    trim($_GET['org']   ?? ''),
    $cert['status'] === 'revoked' ? 'revoked' : 'valid',
]);

echo json_encode([
    'success'         => true,
    'valid'           => $cert['status'] === 'issued',
    'status'          => $cert['status'],
    'certificate_id'  => $cert['certificate_id'],
    'student_name'    => $cert['full_name'],
    'matric_number'   => $cert['matric_number'],
    'department'      => $cert['department'],
    'faculty'         => $cert['faculty'],
    'degree_class'    => $cert['degree_class'],
    'graduation_year' => $cert['graduation_year'],
    'sha256_hash'     => $cert['sha256_hash'],
    'ipfs_cid'        => $cert['ipfs_cid'],
    'ipfs_url'        => IPFS_GATEWAY . $cert['ipfs_cid'],
    'blockchain_tx'   => $cert['blockchain_tx_hash'],
    'etherscan_url'   => 'https://sepolia.etherscan.io/tx/' . $cert['blockchain_tx_hash'],
    'issued_by'       => $cert['issued_by_name'],
    'issued_at'       => $cert['issued_at'],
    'revoked'         => $cert['status'] === 'revoked',
    'revoke_reason'   => $cert['revoke_reason'] ?? null,
]);
