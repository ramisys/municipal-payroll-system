<?php

// system-architecture.md §7.3, §7.8 — Ledger Configuration.
// Hyperledger Besu JSON-RPC endpoint and anchor contract settings.
// When LEDGER_DRIVER is 'array' or 'fake', LedgerGateway operates against an
// in-memory simulated ledger store for fast, offline, deterministic testing.
return [
    'driver' => env('LEDGER_DRIVER', 'besu'),

    'rpc_url' => env('LEDGER_RPC_URL', 'http://127.0.0.1:8545'),

    'contract_address' => env('LEDGER_CONTRACT_ADDRESS', '0x0000000000000000000000000000000000001001'),

    'sender_address' => env('LEDGER_SENDER_ADDRESS', '0xfe3b557e8fb62b89f4916b721be55ceb828dbd73'),

    'timeout' => (int) env('LEDGER_TIMEOUT', 3),

    'retry_limit' => (int) env('LEDGER_RETRY_LIMIT', 5),
];
