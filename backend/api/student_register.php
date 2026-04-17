<?php
// ============================================================
// backend/api/student_register.php
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors.php';
cors_handle_options_preflight('POST, OPTIONS');
cors_apply_credentials_if_allowed();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$matric   = trim($input['matric_number'] ?? '');
$email    = trim($input['email']         ?? '');
$password = (string) ($input['password'] ?? '');

if ($matric === '' || $email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Matric, email and password are required.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

$db = getDB();

$st = $db->prepare('SELECT id FROM students WHERE matric_number = ? AND email = ? LIMIT 1');
$st->execute([$matric, $email]);
$studentId = $st->fetchColumn();

if (!$studentId) {
    echo json_encode([
        'success' => false,
        'message' => 'No student record matches this matric and email. Contact the registry if this is an error.',
    ]);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $db->prepare('INSERT INTO student_users (student_id, password) VALUES (?, ?)')->execute([(int) $studentId, $hash]);
} catch (PDOException $e) {
    $isDup = is_array($e->errorInfo) && isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
    if ($isDup) {
        echo json_encode(['success' => false, 'message' => 'An account already exists for this student. Use login instead.']);
        exit;
    }
    error_log('student_register INSERT: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration could not be completed.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Account created. You can sign in now.']);
