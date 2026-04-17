<?php
// ============================================================
// backend/api/issue_certificate.php
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors.php';
cors_handle_options_preflight('POST, OPTIONS');
cors_apply_credentials_if_allowed();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ipfs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireLogin();

$input    = json_decode(file_get_contents('php://input'), true);
$required = ['full_name', 'matric_number', 'email', 'department', 'faculty', 'degree_class', 'graduation_year'];

foreach ($required as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Field '{$field}' is required."]);
        exit;
    }
}

$db = getDB();

// Prevent duplicate certificates (pending or issued — revoked allows re-issue)
$check = $db->prepare("
    SELECT c.certificate_id FROM certificates c
    JOIN students s ON c.student_id = s.id
    WHERE s.matric_number = ? AND c.status IN ('issued', 'pending')
");
$check->execute([$input['matric_number']]);
if ($check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'A certificate already exists for this matric number.']);
    exit;
}

try {
    $db->beginTransaction();

    // Upsert student
    $db->prepare("
        INSERT INTO students (matric_number, full_name, email, department, faculty, degree_class, graduation_year)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), email = VALUES(email)
    ")->execute([
        $input['matric_number'],
        strtoupper(trim($input['full_name'])),
        $input['email'],
        $input['department'],
        $input['faculty'],
        $input['degree_class'],
        (int) $input['graduation_year'],
    ]);

    $sidStmt = $db->prepare('SELECT id FROM students WHERE matric_number = ? LIMIT 1');
    $sidStmt->execute([$input['matric_number']]);
    $studentId = $sidStmt->fetchColumn();
    if (!$studentId) {
        throw new RuntimeException('Could not resolve student record after save.');
    }

    // Generate certificate ID and hash
    $certId    = generateCertId();
    $certHash  = computeCertHash([
        'certificate_id'  => $certId,
        'full_name'       => strtoupper(trim($input['full_name'])),
        'matric_number'   => $input['matric_number'],
        'department'      => $input['department'],
        'degree_class'    => $input['degree_class'],
        'graduation_year' => $input['graduation_year'],
    ]);

    $student = [
        'full_name'       => strtoupper(trim($input['full_name'])),
        'matric_number'   => $input['matric_number'],
        'department'      => $input['department'],
        'faculty'         => $input['faculty'],
        'degree_class'    => $input['degree_class'],
        'graduation_year' => $input['graduation_year'],
    ];

    // Generate HTML certificate file
    $filePath = generateCertificateHTML(
        ['certificate_id' => $certId, 'sha256_hash' => $certHash, 'ipfs_cid' => 'pending'],
        $student
    );

    // Upload to IPFS
    $ipfsResult = ipfsUpload($filePath);
    $ipfsCid    = $ipfsResult['success'] ? $ipfsResult['cid'] : 'ipfs-unavailable';

    $verifyUrl = appPublicUrl() . '/index.html?verify=' . $certId;

    // Save to database
    $db->prepare("
        INSERT INTO certificates
            (certificate_id, student_id, issued_by, sha256_hash, ipfs_cid, qr_code_data, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ")->execute([$certId, $studentId, $_SESSION['admin_id'], $certHash, $ipfsCid, $verifyUrl]);

    $db->commit();

    echo json_encode([
        'success'         => true,
        'message'         => 'Certificate generated. Sign with MetaMask to finalize.',
        'certificate_id'  => $certId,
        'sha256_hash'     => '0x' . $certHash,
        'ipfs_cid'        => $ipfsCid,
        'student_name'    => strtoupper(trim($input['full_name'])),
        'matric_number'   => $input['matric_number'],
        'department'      => $input['department'],
        'degree_class'    => $input['degree_class'],
        'graduation_year' => (int) $input['graduation_year'],
        'verify_url'      => $verifyUrl,
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
