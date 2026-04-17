<?php
// ============================================================
// Calls Node script for contract.verifyCertificate when Node + artifact exist
// ============================================================

require_once __DIR__ . '/../config/db.php';

/**
 * @return array{ok:bool,error?:string,isValid?:bool,sha256Hash?:string,ipfsCid?:string,isRevoked?:bool}|null
 */
function chainVerifyCertificateRemote(string $certificateId): ?array {
    if (!isContractConfigured()) {
        return null;
    }
    $root   = realpath(__DIR__ . '/../..');
    $script = $root ? $root . '/scripts/chainVerify.js' : '';
    if (!$script || !is_file($script)) {
        return null;
    }
    $node = getenv('NODE_BINARY') ?: 'node';
    $cmd  = escapeshellcmd($node) . ' ' . escapeshellarg($script) . ' '
        . escapeshellarg($certificateId) . ' '
        . escapeshellarg(contractAddress()) . ' '
        . escapeshellarg(sepoliaRpcUrl()) . ' 2>&1';

    $cwd = $root ?: '.';
    $out = shell_exec('cd ' . escapeshellarg($cwd) . ' && ' . $cmd);
    $out = trim((string) $out);
    $j   = json_decode($out, true);
    return is_array($j) ? $j : null;
}
