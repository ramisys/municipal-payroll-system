<?php

namespace Tests\Feature;

use App\Exceptions\LedgerUnreachableException;
use App\Services\LedgerGateway;
use Tests\TestCase;

// FR-6.3 / AC-6.3.3 / AC-6.3.5 / AC-6.3.6.
class LedgerGatewayTest extends TestCase
{
    private LedgerGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        LedgerGateway::fake(true);
        $this->gateway = app(LedgerGateway::class);
    }

    protected function tearDown(): void
    {
        LedgerGateway::restore();
        parent::tearDown();
    }

    public function test_is_reachable_returns_true_when_online(): void
    {
        $this->assertTrue($this->gateway->isReachable());
    }

    public function test_is_reachable_returns_false_when_offline(): void
    {
        LedgerGateway::setReachable(false);
        $this->assertFalse($this->gateway->isReachable());
    }

    public function test_anchor_submits_hash_and_returns_tx_and_block_ref(): void
    {
        $hash = hash('sha256', 'canonical-payload-test');
        $ref = 'RUN:1';
        $chainPosition = 1;

        $receipt = $this->gateway->anchor($hash, $ref, $chainPosition);

        $this->assertArrayHasKey('tx_hash', $receipt);
        $this->assertArrayHasKey('block_number', $receipt);
        $this->assertStringStartsWith('0x', $receipt['tx_hash']);

        // Verify stored in simulated ledger
        $storedHash = $this->gateway->readAnchoredHash($receipt['tx_hash'], $hash);
        $this->assertSame($hash, $storedHash);
    }

    public function test_anchor_throws_ledger_unreachable_exception_when_offline(): void
    {
        LedgerGateway::setReachable(false);

        $this->expectException(LedgerUnreachableException::class);
        $this->expectExceptionMessage('external permissioned ledger is unreachable');

        $this->gateway->anchor(hash('sha256', 'sample'), 'RUN:1', 1);
    }

    public function test_read_anchored_hash_throws_ledger_unreachable_exception_when_offline(): void
    {
        $hash = hash('sha256', 'test');
        $receipt = $this->gateway->anchor($hash, 'RUN:1', 1);

        LedgerGateway::setReachable(false);

        $this->expectException(LedgerUnreachableException::class);
        $this->gateway->readAnchoredHash($receipt['tx_hash']);
    }

    public function test_payload_contains_zero_personal_data_or_wages_ac_6_3_6(): void
    {
        $hash = hash('sha256', 'test-confidential-payroll');
        $ref = 'RUN:42';

        $receipt = $this->gateway->anchor($hash, $ref, 10);

        $simulated = LedgerGateway::getSimulatedLedger()[$receipt['tx_hash']];

        // Inspect ledger entry: strictly cryptographic hash, reference token, position, and timestamps
        $this->assertSame($hash, $simulated['payload_hash']);
        $this->assertSame($ref, $simulated['ref']);
        $this->assertSame(10, $simulated['chain_position']);

        $forbiddenKeys = ['gross', 'net', 'salary', 'employee', 'deduction', 'name', 'tin', 'sss'];
        foreach (array_keys($simulated) as $key) {
            foreach ($forbiddenKeys as $forbidden) {
                $this->assertStringNotContainsString($forbidden, strtolower($key));
            }
        }
    }
}
