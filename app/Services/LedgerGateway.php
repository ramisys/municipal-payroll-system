<?php

namespace App\Services;

use App\Exceptions\LedgerUnreachableException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// system-architecture.md §7.3, §7.8 / FR-6.3 / AC-6.3.3 / AC-6.3.5 / AC-6.3.6.
//
// The single component in the application that touches the external ledger.
// Connects via JSON-RPC 2.0 over HTTP to Hyperledger Besu (QBFT consensus).
//
// Key properties:
// 1. Write-and-read only: Exposes anchor() and readAnchoredHash(). No update or delete calls exist.
// 2. Transmits only deterministic SHA-256 hashes, scope references, and chain positions.
//    No employee names, figures, rates, or personal data ever leave MySQL (AC-6.3.6).
// 3. Unreachability is reported distinctly via LedgerUnreachableException (or false), never as a mismatch.
class LedgerGateway
{
    /**
     * In-memory simulated ledger storage for testing / array driver.
     *
     * @var array<string, array{payload_hash: string, ref: string, chain_position: int, tx_hash: string, block_number: string, timestamp: int}>
     */
    protected static array $simulatedLedger = [];

    protected static bool $simulatedReachable = true;

    protected static bool $isFaked = false;

    /**
     * Check if the external ledger network endpoint is reachable.
     */
    public function isReachable(): bool
    {
        if (self::$isFaked || Config::get('ledger.driver') === 'array' || Config::get('ledger.driver') === 'fake') {
            return self::$simulatedReachable;
        }

        try {
            $rpcUrl = Config::get('ledger.rpc_url', 'http://127.0.0.1:8545');
            $timeout = (int) Config::get('ledger.timeout', 3);

            $response = Http::timeout($timeout)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'web3_clientVersion',
                'params' => [],
                'id' => 1,
            ]);

            return $response->successful() && isset($response->json()['result']);
        } catch (\Throwable $e) {
            Log::debug('LedgerGateway connectivity check failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Submit a deterministic cryptographic payload hash to the immutable ledger.
     *
     * @param  string  $payloadHash  64-char hexadecimal SHA-256 string
     * @param  string  $ref  Human-readable scope identifier (e.g. "RUN:1", "REVERSAL:2")
     * @param  int  $chainPosition  Monotonically increasing position
     * @return array{tx_hash: string, block_number: string}
     *
     * @throws LedgerUnreachableException If node cannot be reached
     */
    public function anchor(string $payloadHash, string $ref, int $chainPosition): array
    {
        if (! $this->isReachable()) {
            throw new LedgerUnreachableException('Cannot anchor hash: external permissioned ledger is unreachable.');
        }

        $normalizedHash = strtolower(trim($payloadHash));

        if (self::$isFaked || Config::get('ledger.driver') === 'array' || Config::get('ledger.driver') === 'fake') {
            $txHash = '0x'.hash('sha256', "tx:{$normalizedHash}:{$chainPosition}:".microtime());
            $blockNumber = '0x'.dechex(1000 + $chainPosition);

            self::$simulatedLedger[$txHash] = [
                'payload_hash' => $normalizedHash,
                'ref' => $ref,
                'chain_position' => $chainPosition,
                'tx_hash' => $txHash,
                'block_number' => $blockNumber,
                'timestamp' => time(),
            ];

            // Also index by payload hash for quick lookups
            self::$simulatedLedger[$normalizedHash] = &self::$simulatedLedger[$txHash];

            return [
                'tx_hash' => $txHash,
                'block_number' => (string) hexdec($blockNumber),
            ];
        }

        // Live Hyperledger Besu JSON-RPC execution
        try {
            $rpcUrl = Config::get('ledger.rpc_url', 'http://127.0.0.1:8545');
            $contract = Config::get('ledger.contract_address', '0x0000000000000000000000000000000000001001');
            $sender = Config::get('ledger.sender_address', '0xfe3b557e8fb62b89f4916b721be55ceb828dbd73');
            $timeout = (int) Config::get('ledger.timeout', 5);

            // Encode calldata for anchor(bytes32,string)
            // Function selector: keccak256("anchor(bytes32,string)")[0..4] = 0xa480fae6
            $selector = '0xa480fae6';
            $paddedHash = str_pad(substr($normalizedHash, 0, 64), 64, '0', STR_PAD_LEFT);

            // ABI encoding for dynamic string parameter ref:
            // offset 0x40 (64 bytes), length, followed by padded UTF-8 bytes
            $offset = str_pad(dechex(64), 64, '0', STR_PAD_LEFT);
            $refHex = bin2hex($ref);
            $refLen = str_pad(dechex(strlen($ref)), 64, '0', STR_PAD_LEFT);
            $paddedRef = str_pad($refHex, ceil(strlen($refHex) / 64) * 64 ?: 64, '0', STR_PAD_RIGHT);

            $calldata = $selector.$paddedHash.$offset.$refLen.$paddedRef;

            $response = Http::timeout($timeout)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_sendTransaction',
                'params' => [[
                    'from' => $sender,
                    'to' => $contract,
                    'gas' => '0x186a0',
                    'gasPrice' => '0x0',
                    'data' => $calldata,
                ]],
                'id' => 2,
            ]);

            if (! $response->successful() || isset($response->json()['error'])) {
                $errMsg = $response->json()['error']['message'] ?? 'JSON-RPC transaction error';
                throw new \RuntimeException("Besu RPC error: {$errMsg}");
            }

            $txHash = $response->json()['result'];

            // Poll for receipt (or return block number if available)
            $blockNumber = '0';
            $receiptResponse = Http::timeout($timeout)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$txHash],
                'id' => 3,
            ]);

            if ($receiptResponse->successful() && isset($receiptResponse->json()['result']['blockNumber'])) {
                $blockNumber = (string) hexdec($receiptResponse->json()['result']['blockNumber']);
            }

            return [
                'tx_hash' => $txHash,
                'block_number' => $blockNumber,
            ];
        } catch (LedgerUnreachableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('LedgerGateway transmission exception: '.$e->getMessage());
            throw new LedgerUnreachableException('Failed to communicate with Hyperledger Besu: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Retrieve the stored cryptographic payload hash from the immutable ledger.
     *
     * @param  string  $txRef  Transaction reference or transaction hash
     * @param  string|null  $payloadHash  Optional payload hash hint
     * @return string|null The 64-character payload hash recorded on the ledger, or null if not found
     *
     * @throws LedgerUnreachableException If ledger is offline
     */
    public function readAnchoredHash(string $txRef, ?string $payloadHash = null): ?string
    {
        if (! $this->isReachable()) {
            throw new LedgerUnreachableException('Cannot read anchor: external permissioned ledger is unreachable.');
        }

        if (self::$isFaked || Config::get('ledger.driver') === 'array' || Config::get('ledger.driver') === 'fake') {
            if (isset(self::$simulatedLedger[$txRef])) {
                return self::$simulatedLedger[$txRef]['payload_hash'];
            }
            if ($payloadHash !== null && isset(self::$simulatedLedger[strtolower(trim($payloadHash))])) {
                return self::$simulatedLedger[strtolower(trim($payloadHash))]['payload_hash'];
            }

            return null;
        }

        // Live Hyperledger Besu JSON-RPC read
        try {
            $rpcUrl = Config::get('ledger.rpc_url', 'http://127.0.0.1:8545');
            $timeout = (int) Config::get('ledger.timeout', 5);

            // Query transaction input by tx hash
            $response = Http::timeout($timeout)->post($rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionByHash',
                'params' => [$txRef],
                'id' => 4,
            ]);

            if (! $response->successful() || ! isset($response->json()['result'])) {
                return null;
            }

            $txData = $response->json()['result'];
            $input = $txData['input'] ?? '';

            // Extract 32-byte payload hash parameter (bytes 10..74 in hex string: 0x + 8 char selector + 64 char hash)
            if (strlen($input) >= 74) {
                return substr($input, 10, 64);
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('LedgerGateway read exception: '.$e->getMessage());
            throw new LedgerUnreachableException('Failed to read from Hyperledger Besu: '.$e->getMessage(), 0, $e);
        }
    }

    // =========================================================================
    // Test & Simulation Helpers
    // =========================================================================

    /**
     * Enable faked in-memory mode for tests.
     */
    public static function fake(bool $reachable = true): void
    {
        self::$isFaked = true;
        self::$simulatedReachable = $reachable;
        self::$simulatedLedger = [];
    }

    /**
     * Restore live client behavior.
     */
    public static function restore(): void
    {
        self::$isFaked = false;
        self::$simulatedReachable = true;
        self::$simulatedLedger = [];
    }

    /**
     * Set reachable state dynamically in tests to simulate network partition or recovery.
     */
    public static function setReachable(bool $reachable): void
    {
        self::$simulatedReachable = $reachable;
    }

    /**
     * In-memory ledger storage lookup (for assertions).
     *
     * @return array<string, mixed>
     */
    public static function getSimulatedLedger(): array
    {
        return self::$simulatedLedger;
    }

    /**
     * Simulate ledger-side tamper (for testing discrepancy where MySQL and ledger differ).
     */
    public static function tamperSimulatedEntry(string $txRef, string $tamperedPayloadHash): void
    {
        if (isset(self::$simulatedLedger[$txRef])) {
            self::$simulatedLedger[$txRef]['payload_hash'] = strtolower(trim($tamperedPayloadHash));
        }
    }
}
