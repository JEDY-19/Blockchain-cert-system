<?php
// ============================================================
// backend/includes/eth_rpc.php — minimal Sepolia JSON-RPC helpers
// ============================================================

require_once __DIR__ . '/../config/db.php';

function ethRpcCall(string $method, array $params): mixed {
    $rpc     = sepoliaRpcUrl();
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method'  => $method,
        'params'  => $params,
        'id'      => 1,
    ], JSON_THROW_ON_ERROR);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 45,
        ],
    ]);

    $raw = @file_get_contents($rpc, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('RPC request failed for ' . $method);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid RPC JSON response');
    }
    if (isset($decoded['error'])) {
        $msg = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'RPC error') : 'RPC error';
        throw new RuntimeException($msg);
    }
    return $decoded['result'] ?? null;
}

/**
 * @return array<string,mixed>|null Receipt, or null if the RPC has no receipt yet (pending / unknown hash on chain)
 * @throws InvalidArgumentException If $txHash is not a valid 32-byte hex transaction hash
 */
function ethGetTransactionReceipt(string $txHash): ?array {
    $txHash = strtolower(trim($txHash));
    if (!preg_match('/^0x[a-f0-9]{64}$/', $txHash)) {
        throw new InvalidArgumentException('Invalid transaction hash format.');
    }
    $result = ethRpcCall('eth_getTransactionReceipt', [$txHash]);
    return is_array($result) ? $result : null;
}

function assertSuccessfulContractTx(string $txHash, string $expectedContractLower): void {
    $receipt = ethGetTransactionReceipt($txHash);
    if ($receipt === null) {
        throw new RuntimeException('Transaction not found or not yet mined. Wait for confirmation and try again.');
    }
    $status = $receipt['status'] ?? '0x0';
    $ok     = false;
    if (is_string($status) && substr($status, 0, 2) === '0x') {
        $ok = hexdec($status) === 1;
    } else {
        $ok = (int) $status === 1;
    }
    if (!$ok) {
        throw new RuntimeException('Transaction failed on-chain (reverted).');
    }
    $to = isset($receipt['to']) ? strtolower((string) $receipt['to']) : '';
    if ($to !== strtolower($expectedContractLower)) {
        throw new RuntimeException('Transaction was not sent to the configured certificate contract.');
    }
}
