<?php

namespace App\Console\Commands;

use App\Services\LedgerAnchorService;
use App\Services\LedgerGateway;
use Illuminate\Console\Command;

// system-architecture.md §6.7, §7.3 / UC-I6 / FR-6.3.
// Flushes the integrity transactional outbox by transmitting pending anchors
// to Hyperledger Besu and updating confirmation receipts.
class ProcessAnchorOutboxCommand extends Command
{
    protected $signature = 'integrity:process-outbox {--limit=50 : Maximum number of pending anchors to process}';

    protected $description = 'Process pending integrity anchors in the outbox and transmit them to the external ledger (UC-I6)';

    public function handle(LedgerAnchorService $anchorService, LedgerGateway $ledgerGateway): int
    {
        $this->info('Checking external permissioned ledger connectivity...');

        if (! $ledgerGateway->isReachable()) {
            $this->warn('External ledger is currently UNREACHABLE. Pending anchors remain queued.');
            $this->comment('Payroll operations remain completely unaffected (AC-6.3.5). Retries will resume on next cycle.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $this->info("External ledger reachable. Processing up to {$limit} pending anchors in outbox...");

        $results = $anchorService->processOutbox($limit);

        $this->table(
            ['Processed', 'Confirmed', 'Failed / Retrying'],
            [[$results['processed'], $results['confirmed'], $results['failed']]]
        );

        if ($results['confirmed'] > 0) {
            $this->info("Successfully confirmed {$results['confirmed']} anchor(s) on Hyperledger Besu.");
        }

        if ($results['failed'] > 0) {
            $this->warn("{$results['failed']} anchor(s) failed transmission and will be retried on next run.");
        }

        return self::SUCCESS;
    }
}
