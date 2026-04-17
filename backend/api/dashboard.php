<?php
// ============================================================
// backend/api/dashboard.php — stats for admin dashboard
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors.php';
cors_handle_options_preflight('GET, OPTIONS');
cors_apply_credentials_if_allowed();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();

    $verifiedToday = (int) $db->query("
        SELECT COUNT(*) FROM verification_logs
        WHERE result IN ('valid', 'revoked') AND DATE(verified_at) = CURDATE()
    ")->fetchColumn();

    $agg = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'issued'  THEN 1 ELSE 0 END) AS issued,
            SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) AS revoked
        FROM certificates
    ")->fetch(PDO::FETCH_ASSOC);

    $totalCerts   = (int) ($agg['total'] ?? 0);
    $totalIssued  = (int) ($agg['issued'] ?? 0);
    $totalRevoked = (int) ($agg['revoked'] ?? 0);

    echo json_encode([
        'success'            => true,
        'verified_today'     => $verifiedToday,
        'total_issued'       => $totalIssued,
        'total_certificates' => $totalCerts,
        'total_revoked'      => $totalRevoked,
    ]);
} catch (Throwable $e) {
    error_log('dashboard.php: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load dashboard statistics.']);
}
