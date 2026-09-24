@extends('layouts.app')

@section('title', "Integrity Details — Run #{$run->payroll_run_id}")
@section('heading', 'Record Integrity Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('integrity.index') }}" class="link text-sm inline-flex items-center gap-1">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            Back to integrity anchors
        </a>
    </div>

    <x-page-header title="Integrity Anchor — Run #{{ $run->payroll_run_id }}" subtitle="Deterministic SHA-256 fingerprint anchored at finalization (FR-6.3, UC-31).">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if ($latestVerification)
                    <a href="{{ route('integrity.verifications.pdf', $latestVerification) }}" class="btn btn-secondary btn-sm" target="_blank">
                        <x-icon name="document-arrow-down" class="w-3.5 h-3.5 mr-1" />
                        Download PDF Certificate
                    </a>
                @endif
                <form action="{{ route('integrity.verify-run', $run) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">
                        <x-icon name="check" class="w-3.5 h-3.5 mr-1" />
                        Verify integrity now
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

    {{-- Prominent Outcome Callout Banner if checked --}}
    @if ($latestVerification)
        @if ($latestVerification->result === 'MATCH')
            <div class="mb-6 p-5 rounded-lg bg-emerald-50 border-2 border-emerald-300 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="p-2 bg-emerald-100 text-emerald-800 rounded-full font-bold text-lg">✓</span>
                    <div>
                        <h4 class="text-base font-bold text-emerald-900">INTEGRITY VERIFIED: MATCH</h4>
                        <p class="text-xs text-emerald-800 mt-1 leading-relaxed">
                            {{ $latestVerification->remarks }}
                        </p>
                        <div class="text-2xs text-emerald-700 mt-2 font-mono">
                            Verified on {{ $latestVerification->performed_at->format('Y-m-d H:i:s') }} by {{ $latestVerification->performer?->username }} ({{ $latestVerification->performer?->role?->role_name }}).
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($latestVerification->result === 'MISMATCH')
            <div class="mb-6 p-5 rounded-lg bg-rose-50 border-2 border-rose-400 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="p-2 bg-rose-100 text-rose-800 rounded-full font-bold text-lg">✕</span>
                    <div>
                        <h4 class="text-base font-bold text-rose-900">SECURITY ALERT: INTEGRITY MISMATCH (UC-31 E1)</h4>
                        <p class="text-xs text-rose-800 mt-1 leading-relaxed">
                            {{ $latestVerification->remarks }}
                        </p>
                        <div class="mt-3 p-3 bg-white/80 rounded border border-rose-300 text-xs text-rose-900">
                            <strong>System Non-Negotiable:</strong> The system never resolves a mismatch automatically and never re-anchors a mismatched record (doing so would destroy the evidence of alteration).
                            The Administrator must investigate database audit logs and execute the verified database restore procedure (NFR-5.4).
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($latestVerification->result === 'UNVERIFIABLE')
            <div class="mb-6 p-5 rounded-lg bg-amber-50 border-2 border-amber-300 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="p-2 bg-amber-100 text-amber-800 rounded-full font-bold text-lg">?</span>
                    <div>
                        <h4 class="text-base font-bold text-amber-900">STATUS: UNVERIFIABLE (ABSENCE OF EVIDENCE)</h4>
                        <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                            {{ $latestVerification->remarks }}
                        </p>
                        <div class="text-2xs text-amber-700 mt-2">
                            This outcome distinguishes an unreachable ledger or queued outbox from a tampering mismatch. Payroll operations remain completely functional.
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Side-by-Side Cryptographic Fingerprints Card --}}
    <x-card title="Cryptographic Fingerprint Comparison" class="mb-6">
        <div class="space-y-4 text-xs">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-ink-muted font-semibold">1. Anchored MySQL Local Fingerprint (Queued at Finalization):</span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-blue-100 text-blue-800">
                        Chain Pos #{{ $anchor->chain_position }}
                    </span>
                </div>
                <p class="font-mono text-xs font-bold text-ink bg-slate-50 p-2.5 rounded border border-line break-all select-all">
                    {{ $anchor->payload_hash }}
                </p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-ink-muted font-semibold">2. External Hyperledger Besu Fingerprint (Ledger Stored):</span>
                    @if ($anchor->anchor_status === 'CONFIRMED')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-emerald-100 text-emerald-800">
                            CONFIRMED RECEIPT
                        </span>
                    @else
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-amber-100 text-amber-800">
                            OUTBOX PENDING
                        </span>
                    @endif
                </div>
                <p class="font-mono text-xs font-bold text-ink bg-slate-50 p-2.5 rounded border border-line break-all select-all">
                    {{ $ledgerHash ?? ($anchor->anchor_status === 'CONFIRMED' ? $anchor->payload_hash : 'Awaiting confirmation on ledger...') }}
                </p>
            </div>

            @if ($latestVerification)
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-ink-muted font-semibold">3. Live Recomputed Database Hash (Computed at Verification):</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold {{ $latestVerification->result === 'MATCH' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ $latestVerification->result }}
                        </span>
                    </div>
                    <p class="font-mono text-xs font-bold text-ink bg-slate-50 p-2.5 rounded border border-line break-all select-all">
                        {{ $latestVerification->recomputed_hash }}
                    </p>
                </div>
            @endif
        </div>
    </x-card>

    {{-- Blockchain & Outbox Metadata Card --}}
    <x-card title="Ledger & Outbox Metadata" class="mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-ink-muted block">Outbox Status:</span>
                @if ($anchor->anchor_status === 'CONFIRMED')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800 mt-1">
                        CONFIRMED
                    </span>
                @elseif ($isStalled)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 mt-1">
                        STALLED ({{ $anchor->retry_count }} retries)
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800 mt-1">
                        PENDING ({{ $anchor->retry_count }} retries)
                    </span>
                @endif
            </div>
            <div>
                <span class="text-ink-muted block">Queued At:</span>
                <span class="text-ink font-semibold tabular block mt-1">{{ $anchor->queued_at->format('Y-m-d H:i:s') }}</span>
            </div>
            <div>
                <span class="text-ink-muted block">Confirmed At:</span>
                <span class="text-ink font-semibold tabular block mt-1">{{ $anchor->confirmed_at ? $anchor->confirmed_at->format('Y-m-d H:i:s') : 'Pending block inclusion' }}</span>
            </div>
            <div>
                <span class="text-ink-muted block">Ledger Block Ref:</span>
                <span class="font-mono text-ink font-semibold block mt-1">#{{ $anchor->ledger_block_ref ?? '—' }}</span>
            </div>
            <div class="sm:col-span-2">
                <span class="text-ink-muted block">Ledger Transaction Hash:</span>
                <span class="font-mono text-2xs text-ink font-semibold break-all block mt-1">{{ $anchor->ledger_tx_ref ?? 'Awaiting transmission' }}</span>
            </div>
            <div class="sm:col-span-2">
                <span class="text-ink-muted block">Bound Import Source File Hash:</span>
                <span class="font-mono text-2xs text-ink font-semibold break-all block mt-1">{{ $run->currentImport()?->source_sha256 ?? '—' }}</span>
            </div>
        </div>
    </x-card>

    {{-- Verification History --}}
    <div class="mb-3 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-ink">Verification History (Append-Only)</h3>
            <p class="text-xs text-ink-muted">Every check is recorded permanently with recomputed hash, actor, and outcome (UC-31, AC-6.3.7).</p>
        </div>
    </div>

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Performed At</th>
                <th>Outcome</th>
                <th>Recomputed Hash</th>
                <th>Verified By</th>
                <th>Remarks</th>
                <th class="text-right">Actions</th>
            </x-slot:head>
            @forelse ($verifications as $v)
                <tr>
                    <td class="tabular text-xs font-medium">{{ $v->performed_at->format('Y-m-d H:i:s') }}</td>
                    <td>
                        @if ($v->result === 'MATCH')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-emerald-100 text-emerald-800">
                                MATCH
                            </span>
                        @elseif ($v->result === 'MISMATCH')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-rose-100 text-rose-800">
                                MISMATCH
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-amber-100 text-amber-800">
                                {{ $v->result }}
                            </span>
                        @endif
                    </td>
                    <td class="tabular text-2xs font-mono text-ink-muted" title="{{ $v->recomputed_hash }}">
                        {{ substr($v->recomputed_hash, 0, 16) }}...{{ substr($v->recomputed_hash, -8) }}
                    </td>
                    <td class="text-xs text-ink">
                        {{ $v->performer?->username ?? '—' }} ({{ $v->performer?->role?->role_name ?? '—' }})
                    </td>
                    <td class="text-xs text-ink-muted max-w-xs truncate" title="{{ $v->remarks }}">{{ $v->remarks ?? '—' }}</td>
                    <td class="text-right">
                        <a href="{{ route('integrity.verifications.pdf', $v) }}" class="btn btn-secondary btn-xs" target="_blank">
                            Certificate PDF
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-6 text-center text-ink-muted text-sm">
                        No verification checks performed yet. Click "Verify integrity now" to recompute live payload hash and verify.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection
