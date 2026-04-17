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
require_once __DIR__ . '/../includes/chain_verify_bridge.php';
require_once __DIR__ . '/../includes/keccak_util.php';

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

$chainAudit = null;
if (!empty($cert['blockchain_tx_hash']) && isContractConfigured()) {
    $remote = chainVerifyCertificateRemote($cert['certificate_id']);
    if ($remote === null) {
        $chainAudit = [
            'checked' => false,
            'note'    => 'On-chain cross-check skipped (run npm install && npx hardhat compile in project root, ensure Node is available).',
        ];
    } elseif (empty($remote['ok'])) {
        $chainAudit = [
            'checked' => true,
            'consistent' => false,
            'error'   => $remote['error'] ?? 'Contract call failed',
        ];
    } else {
        $norm = static function (?string $h): string {
            if ($h === null || $h === '') {
                return '';
            }
            return strtolower(preg_replace('/^0x/i', '', $h));
        };
        $dbHash = $norm($cert['sha256_hash']);
        $chainH = $norm($remote['sha256Hash'] ?? '');
        $hashOk = $dbHash !== '' && $chainH !== '' && hash_equals($dbHash, $chainH);
        $chRev  = !empty($remote['isRevoked']);
        $statusOk = $cert['status'] === 'issued'
            ? (!empty($remote['isValid']) && !$chRev)
            : $chRev;

        $chainNH = $norm($remote['studentNameHash'] ?? '');
        $chainMH = $norm($remote['matricNumberHash'] ?? '');
        $dbNameH = keccak256_utf8((string) $cert['full_name']);
        $dbMatH  = keccak256_utf8((string) $cert['matric_number']);
        $piiOk   = true;
        if ($chainNH !== '' || $chainMH !== '') {
            if ($dbNameH && $dbMatH) {
                $piiOk = hash_equals($norm($dbNameH), $chainNH) && hash_equals($norm($dbMatH), $chainMH);
            } else {
                $piiOk = false;
            }
        }

        $chainAudit = [
            'checked'                   => true,
            'consistent'                => $hashOk && $statusOk && $piiOk,
            'hash_matches_record'       => $hashOk,
            'revocation_matches_record' => $statusOk,
            'pii_commitments_match'     => $piiOk,
        ];
    }
}

$cid = $cert['ipfs_cid'] ?? '';
$ipfsUrl = ($cid !== '' && $cid !== 'ipfs-unavailable') ? (IPFS_GATEWAY . $cid) : null;

$payload = [
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
    'ipfs_url'        => $ipfsUrl,
    'blockchain_tx'   => $cert['blockchain_tx_hash'],
    'etherscan_url'   => $cert['blockchain_tx_hash']
        ? 'https://sepolia.etherscan.io/tx/' . $cert['blockchain_tx_hash']
        : null,
    'issued_by'       => $cert['issued_by_name'],
    'issued_at'       => $cert['issued_at'],
    'revoked'         => $cert['status'] === 'revoked',
    'revoke_reason'   => $cert['revoke_reason'] ?? null,
    'chain_audit'     => $chainAudit,
];

echo json_encode($payload);
