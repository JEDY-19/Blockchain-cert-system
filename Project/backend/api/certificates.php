<?php
// ============================================================
// backend/api/certificates.php
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list certificates ───────────────────────────────────
if ($method === 'GET') {
    $page   = max(1, (int) ($_GET['page']   ?? 1));
    $limit  = (int) ($_GET['limit'] ?? 15);
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $where  = ['1=1'];
    $params = [];

    if ($search) {
        $where[]  = "(s.full_name LIKE ? OR s.matric_number LIKE ? OR c.certificate_id LIKE ?)";
        $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    if ($status) {
        $where[]  = "c.status = ?";
        $params[] = $status;
    }

    $wc = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM certificates c JOIN students s ON c.student_id = s.id WHERE $wc");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $dataStmt = $db->prepare("
        SELECT c.certificate_id, c.status, c.sha256_hash, c.ipfs_cid,
               c.blockchain_tx_hash, c.issued_at, c.revoked_at, c.revoke_reason,
               s.full_name, s.matric_number, s.department, s.degree_class,
               s.graduation_year, a.full_name AS issued_by
        FROM certificates c
        JOIN students s ON c.student_id = s.id
        JOIN admins a   ON c.issued_by  = a.id
        WHERE $wc
        ORDER BY c.issued_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $dataStmt->execute($params);

    echo json_encode([
        'success' => true,
        'data'    => $dataStmt->fetchAll(),
        'total'   => $total,
        'page'    => $page,
        'pages'   => (int) ceil($total / $limit),
    ]);
    exit;
}

// ── POST: revoke certificate ─────────────────────────────────
if ($method === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $certId = trim($input['certificate_id'] ?? '');
    $reason = trim($input['reason']         ?? 'Revoked by administrator');

    if (empty($certId)) {
        echo json_encode(['success' => false, 'message' => 'certificate_id is required.']);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE certificates
        SET status = 'revoked', revoked_at = NOW(), revoke_reason = ?
        WHERE certificate_id = ? AND status = 'issued'
    ");
    $stmt->execute([$reason, $certId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Certificate not found or already revoked.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => "Certificate {$certId} has been revoked."]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
