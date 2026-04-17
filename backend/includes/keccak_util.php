<?php
// ============================================================
// keccak256(utf8) via project Node + ethers (matches Web3 / CertificateRegistry)
// ============================================================

/**
 * @return non-falsy-string|null Lowercase 0x + 64 hex, or null on failure
 */
function keccak256_utf8(string $input): ?string {
    $root = realpath(__DIR__ . '/../..');
    if ($root === false) {
        return null;
    }
    $script = $root . '/scripts/keccakUtf8.js';
    if (!is_file($script)) {
        return null;
    }
    $node = getenv('NODE_BINARY') ?: 'node';

    $useProcArray = PHP_VERSION_ID >= 70400;
    if ($useProcArray) {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open([$node, $script, $input], $descriptorspec, $pipes, $root);
        if (!is_resource($proc)) {
            return null;
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            return null;
        }
    } else {
        $isWin = (defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows')
            || (strncasecmp(PHP_OS, 'WIN', 3) === 0);
        $stderrRedir = $isWin ? '' : ' 2>/dev/null';
        $cmd = 'cd ' . escapeshellarg($root) . ' && ' . escapeshellarg($node) . ' '
            . escapeshellarg($script) . ' ' . escapeshellarg($input) . $stderrRedir;
        $out = shell_exec($cmd);
    }

    $out = is_string($out) ? trim($out) : '';
    if ($out === '' || !preg_match('/^0x[a-fA-F0-9]{64}$/', $out)) {
        return null;
    }
    return strtolower($out);
}
