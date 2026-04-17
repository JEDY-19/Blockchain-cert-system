<?php
// ============================================================
// backend/api/student_login.php
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors.php';
cors_handle_options_preflight('POST, OPTIONS');
cors_apply_credentials_if_allowed();
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/student_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email']    ?? '');
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$db = getDB();
$st = $db->prepare('
    SELECT su.id AS user_id, su.password, s.full_name, s.matric_number
    FROM student_users su
    JOIN students s ON s.id = su.student_id
    WHERE s.email = ?
    LIMIT 1
');
$st->execute([$email]);
$row = $st->fetch();

if (!$row || !password_verify($password, $row['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
session_regenerate_id(true);

$_SESSION['student_user_id'] = (int) $row['user_id'];
$_SESSION['student_name']    = $row['full_name'];
$_SESSION['student_matric']  = $row['matric_number'];

echo json_encode([
    'success' => true,
    'name'    => $row['full_name'],
    'matric'  => $row['matric_number'],
]);
