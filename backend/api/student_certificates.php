<?php
// ============================================================
// backend/api/student_certificates.php
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors.php';
cors_handle_options_preflight('GET, OPTIONS');
cors_apply_credentials_if_allowed();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/student_auth.php';

requireStudentLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db = getDB();
$uid = (int) $_SESSION['student_user_id'];

$st = $db->prepare('
    SELECT c.certificate_id, c.status, c.sha256_hash, c.ipfs_cid, c.blockchain_tx_hash,
           c.issued_at, c.revoked_at, c.revoke_reason,
           s.full_name, s.matric_number, s.department, s.degree_class, s.graduation_year
    FROM certificates c
    JOIN students s ON c.student_id = s.id
    JOIN student_users su ON su.student_id = s.id
    WHERE su.id = ?
    ORDER BY c.issued_at DESC
');
$st->execute([$uid]);

echo json_encode([
    'success' => true,
    'data'    => $st->fetchAll(),
]);
