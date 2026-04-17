<?php
// ============================================================
// backend/api/student_me.php
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/cors.php';
cors_handle_options_preflight('GET, OPTIONS');
cors_apply_credentials_if_allowed();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/student_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

echo json_encode([
    'success'    => true,
    'logged_in'  => isStudentLoggedIn(),
    'name'       => $_SESSION['student_name'] ?? null,
    'matric'     => $_SESSION['student_matric'] ?? null,
]);
