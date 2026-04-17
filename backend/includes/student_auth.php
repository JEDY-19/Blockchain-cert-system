<?php

require_once __DIR__ . '/../config/db.php';

function isStudentLoggedIn(): bool {
    return isset($_SESSION['student_user_id']) && (int) $_SESSION['student_user_id'] > 0;
}

function requireStudentLogin(): void {
    if (!isStudentLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated as student.']);
        exit;
    }
}
