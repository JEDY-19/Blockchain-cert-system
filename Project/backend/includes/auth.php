<?php
// ============================================================
// backend/includes/auth.php
// ============================================================

require_once __DIR__ . '/../config/db.php';

function isLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }
}

function loginAdmin(string $email, string $password): array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];

    return ['success' => true, 'name' => $admin['full_name'], 'role' => $admin['role']];
}

function generateCertId(): string {
    $db    = getDB();
    $count = (int) $db->query("SELECT COUNT(*) FROM certificates")->fetchColumn() + 1;
    return CERT_PREFIX . '-' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

function computeCertHash(array $d): string {
    $payload = implode('|', [
        $d['certificate_id'],
        strtoupper($d['full_name']),
        $d['matric_number'],
        $d['department'],
        $d['degree_class'],
        $d['graduation_year'],
    ]);
    return hash('sha256', $payload);
}
