@extends('layouts.app')

@section('title', 'Integrity Verification')
@section('heading', 'Payroll Record Integrity')

@section('content')
    <x-page-header title="Integrity Verification" subtitle="Cryptographic SHA-256 fingerprints anchored to Hyperledger Besu at finalization and reversal (FR-6.3, UC-31).">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                {{-- Audit Chain Verification Button --}}
                <form action="{{ route('integrity.audit-chain.verify') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm" title="Walk and verify audit log hash chain (BR-35)">
                        <x-icon name="shield" class="w-3.5 h-3.5 mr-1" />
                        Verify Audit Chain
                    </button>
                </form>

                {{-- Outbox Flush Button --}}
                <form action="{{ route('integrity.outbox.process') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm" title="Transmit pending outbox anchors to the external ledger">
                        <x-icon name="refresh" class="w-3.5 h-3.5 mr-1" />
                        Process Outbox
                    </button>
                </form>
            </div>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <x-icon name="check-circle" class="w-5 h-5 text-emerald-600 flex-shrink-0" />
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2">
            <x-icon name="exclamation-circle" class="w-5 h-5 text-rose-600 flex-shrink-0" />
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if (session('warning'))
        <div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium flex items-center gap-2">
            <x-icon name="exclamation-triangle" class="w-5 h-5 text-amber-600 flex-shrink-0" />
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <x-card>
            <div class="text-2xs font-semibold uppercase tracking-wider text-ink-muted mb-1">Total Anchors</div>
            <div class="text-2xl font-bold tabular text-ink">{{ $metrics['total'] }}</div>
            <div class="text-2xs text-ink-muted mt-1">Append-only registry</div>
        </x-card>
        <x-card>
            <div class="text-2xs font-semibold uppercase tracking-wider text-emerald-700 mb-1">Confirmed</div>
            <div class="text-2xl font-bold tabular text-emerald-700">{{ $metrics['confirmed'] }}</div>
            <div class="text-2xs text-emerald-600 mt-1">Anchored on Besu</div>
        </x-card>
        <x-card>
            <div class="text-2xs font-semibold uppercase tracking-wider text-amber-700 mb-1">Outbox Pending</div>
            <div class="text-2xl font-bold tabular text-amber-700">{{ $metrics['pending'] }}</div>
            <div class="text-2xs text-amber-600 mt-1">Awaiting ledger cycle</div>
        </x-card>
        <x-card>
            <div class="text-2xs font-semibold uppercase tracking-wider text-rose-700 mb-1">Stalled Retries</div>
            <div class="text-2xl font-bold tabular text-rose-700">{{ $metrics['stalled'] }}</div>
            <div class="text-2xs text-rose-600 mt-1">&ge; {{ $retryLimit }} failed attempts</div>
        </x-card>
        <x-card>
            <div class="text-2xs font-semibold uppercase tracking-wider text-ink-muted mb-1">Ledger Status</div>
            <div class="flex items-center gap-2 mt-1">
                @if ($metrics['ledger_online'])
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">
                        ONLINE
                    </span>
                    <span class="text-2xs text-ink-muted">Besu QBFT active</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800">
                        OFFLINE
                    </span>
                    <span class="text-2xs text-ink-muted">Payroll non-blocking</span>
                @endif
            </div>
        </x-card>
    </div>

    {{-- Period Batch Verification Card (UC-31 A2) --}}
    <x-card title="Period Batch Verification (UC-31 A2)" class="mb-6">
        <p class="text-xs text-ink-muted mb-3">
            Verify every finalized payroll run in an entire period in a single operation. Summary counts of MATCH, MISMATCH, and UNVERIFIABLE are compiled into the verification history.
        </p>
        <div class="flex flex-wrap items-center gap-3">
            @forelse ($periods->take(6) as $period)
                <form action="{{ route('integrity.period.verify', $period) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-xs">
                        Verify Period {{ $period->payroll_year }}-{{ str_pad($period->period_no, 2, '0', STR_PAD_LEFT) }}
                    </button>
                </form>
            @empty
                <span class="text-xs text-ink-muted">No periods available.</span>
            @endforelse
        </div>
    </x-card>

    {{-- Filter Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-ink-muted">Status:</span>
            <a href="{{ route('integrity.index', ['status' => 'ALL', 'scope' => $scopeFilter]) }}"
               class="px-2 py-1 text-xs rounded font-medium {{ $statusFilter === 'ALL' ? 'bg-primary-700 text-white' : 'bg-slate-100 text-ink hover:bg-slate-200' }}">
                All ({{ $metrics['total'] }})
            </a>
            <a href="{{ route('integrity.index', ['status' => 'CONFIRMED', 'scope' => $scopeFilter]) }}"
               class="px-2 py-1 text-xs rounded font-medium {{ $statusFilter === 'CONFIRMED' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}">
                Confirmed ({{ $metrics['confirmed'] }})
            </a>
            <a href="{{ route('integrity.index', ['status' => 'PENDING', 'scope' => $scopeFilter]) }}"
               class="px-2 py-1 text-xs rounded font-medium {{ $statusFilter === 'PENDING' ? 'bg-amber-700 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' }}">
                Pending ({{ $metrics['pending'] }})
            </a>
            @if ($metrics['stalled'] > 0)
                <a href="{{ route('integrity.index', ['status' => 'STALLED', 'scope' => $scopeFilter]) }}"
                   class="px-2 py-1 text-xs rounded font-medium {{ $statusFilter === 'STALLED' ? 'bg-rose-700 text-white' : 'bg-rose-50 text-rose-800 hover:bg-rose-100' }}">
                    Stalled ({{ $metrics['stalled'] }})
                </a>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-ink-muted">Scope:</span>
            <a href="{{ route('integrity.index', ['status' => $statusFilter, 'scope' => 'ALL']) }}"
               class="px-2 py-1 text-xs rounded font-medium {{ $scopeFilter === 'ALL' ? 'bg-slate-700 text-white' : 'bg-slate-100 text-ink hover:bg-slate-200' }}">
                All Scopes
            </a>
            <a href="{{ route('integrity.index', ['status' => $statusFilter, 'scope' => 'RUN']) }}"
               class="px-2 py-1 text-xs rounded font-medium {{ $scopeFilter === 'RUN' ? 'bg-blue-700 text-white' : 'bg-blue-50 text-blue-800 hover:bg-blue-100' }}">
                Runs
            </a>
            <a href="{{ route('integrity.index', ['status' => $statusFilter, 'scope' => 'REVERSAL']) }}"
               class="px-2 py-1 text-xs rounded font-medium {{ $scopeFilter === 'REVERSAL' ? 'bg-purple-700 text-white' : 'bg-purple-50 text-purple-800 hover:bg-purple-100' }}">
                Reversals
            </a>
        </div>
    </div>

    {{-- Anchors Table --}}
    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Pos</th>
                <th>Scope</th>
                <th>Target Record</th>
                <th>Payload Hash (SHA-256)</th>
                <th>Outbox Status</th>
                <th>Ledger TX Reference</th>
                <th>Queued At</th>
                <th class="text-right">Actions</th>
            </x-slot:head>
            @forelse ($anchors as $a)
                @php
                    $stalled = ($a->anchor_status === 'PENDING' && $a->retry_count >= $retryLimit);
                @endphp
                <tr class="{{ $stalled ? 'bg-rose-50/50' : '' }}">
                    <td class="tabular font-mono text-xs text-ink-muted font-bold">#{{ $a->chain_position }}</td>
                    <td>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold {{ $a->scope_type === 'RUN' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                            {{ $a->scope_type }}
                        </span>
                    </td>
                    <td class="text-xs">
                        @if ($a->scope_type === 'RUN' && $a->run)
                            <a href="{{ route('payroll-runs.show', $a->run) }}" class="link font-semibold">
                                Payroll Run #{{ $a->run->payroll_run_id }}
                            </a>
                            <span class="text-ink-muted block text-2xs">
                                Period {{ $a->run->period?->payroll_year }}-{{ $a->run->period?->period_no }} ({{ $a->run->run_status }})
                            </span>
                        @elseif ($a->reversalRecord)
                            <span class="font-semibold text-ink">Reversal #{{ $a->reversalRecord->reversal_record_id }}</span>
                            <span class="text-ink-muted block text-2xs">Reversed Run #{{ $a->reversalRecord->payroll_run_id }}</span>
                        @else
                            <span class="text-rose-600 font-semibold">Deleted Record #{{ $a->payroll_run_id ?? $a->reversal_record_id }}</span>
                        @endif
                    </td>
                    <td class="tabular text-2xs font-mono text-ink-muted" title="{{ $a->payload_hash }}">
                        {{ substr($a->payload_hash, 0, 16) }}...{{ substr($a->payload_hash, -8) }}
                    </td>
                    <td>
                        @if ($a->anchor_status === 'CONFIRMED')
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-emerald-100 text-emerald-800">
                                CONFIRMED
                            </span>
                        @elseif ($stalled)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-rose-100 text-rose-800" title="{{ $a->retry_count }} retries">
                                STALLED ({{ $a->retry_count }})
                            </span>
                        @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-amber-100 text-amber-800" title="{{ $a->retry_count }} retries">
                                PENDING ({{ $a->retry_count }})
                            </span>
                        @endif
                    </td>
                    <td class="tabular text-2xs font-mono text-ink-muted">
                        @if ($a->ledger_tx_ref)
                            <span title="{{ $a->ledger_tx_ref }}">{{ substr($a->ledger_tx_ref, 0, 12) }}...</span>
                        @else
                            <span class="text-ink-muted italic">Awaiting block</span>
                        @endif
                    </td>
                    <td class="tabular text-xs text-ink-muted">{{ $a->queued_at->format('Y-m-d H:i') }}</td>
                    <td class="text-right space-x-1">
                        @if ($a->scope_type === 'RUN' && $a->run)
                            <form action="{{ route('integrity.verify-run', $a->run) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-xs">
                                    Verify now
                                </button>
                            </form>
                            <a href="{{ route('integrity.show', $a->run) }}" class="btn btn-secondary btn-xs">
                                Details
                            </a>
                        @elseif ($a->reversalRecord)
                            <form action="{{ route('integrity.reversals.verify', $a->reversalRecord) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-xs">
                                    Verify now
                                </button>
                            </form>
                            <a href="{{ route('integrity.reversals.show', $a->reversalRecord) }}" class="btn btn-secondary btn-xs">
                                Details
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="p-8 text-center text-ink-muted text-sm">
                        No integrity anchors match the selected filters. Anchors are automatically queued when a payroll run is finalized or reversed (AC-4.5.5, BR-36).
                    </td>
                </tr>
            @endforelse
        </x-table>
        <div class="p-4 border-t border-line">
            {{ $anchors->links() }}
        </div>
    </x-card>
@endsection
