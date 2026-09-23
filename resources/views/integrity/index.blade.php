@extends('layouts.app')

@section('title', 'Integrity Verification')
@section('heading', 'Payroll Record Integrity')

@section('content')
    <x-page-header title="Integrity Verification" subtitle="Cryptographic SHA-256 fingerprints anchored at finalization/reversal with verification history (FR-6.3, UC-31).">
    </x-page-header>

    @if (session('success'))
        <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium">
            {{ session('error') }}
        </div>
    @endif

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Chain Pos</th>
                <th>Scope</th>
                <th>Target Record</th>
                <th>Payload Hash (SHA-256)</th>
                <th>Outbox Status</th>
                <th>Queued At</th>
                <th class="text-right">Actions</th>
            </x-slot:head>
            @forelse ($anchors as $a)
                <tr>
                    <td class="tabular font-mono text-xs text-ink-muted">#{{ $a->chain_position }}</td>
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
                            <span class="text-ink-muted block text-2xs">For Run #{{ $a->reversalRecord->payroll_run_id }}</span>
                        @else
                            Record #{{ $a->payroll_run_id ?? $a->reversal_record_id }}
                        @endif
                    </td>
                    <td class="tabular text-2xs font-mono text-ink-muted" title="{{ $a->payload_hash }}">
                        {{ substr($a->payload_hash, 0, 20) }}...
                    </td>
                    <td>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold {{ $a->anchor_status === 'CONFIRMED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $a->anchor_status }}
                        </span>
                    </td>
                    <td class="tabular text-xs text-ink-muted">{{ $a->queued_at->format('Y-m-d H:i:s') }}</td>
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
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-8 text-center text-ink-muted text-sm">
                        No integrity anchors recorded yet. Anchors are automatically generated when a payroll run is finalized or reversed (AC-4.5.5).
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection
