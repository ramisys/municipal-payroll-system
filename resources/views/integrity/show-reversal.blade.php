@extends('layouts.app')

@section('title', "Integrity Details — Reversal #{$reversal->reversal_record_id}")
@section('heading', 'Reversal Record Integrity Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('integrity.index', ['scope' => 'REVERSAL']) }}" class="link text-sm inline-flex items-center gap-1">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            Back to integrity anchors
        </a>
    </div>

    <x-page-header title="Integrity Anchor — Reversal #{{ $reversal->reversal_record_id }}" subtitle="Cryptographic SHA-256 fingerprint of reversal record anchored to Hyperledger Besu (FR-6.3, UC-26, BR-36).">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if ($latestVerification)
                    <a href="{{ route('integrity.verifications.pdf', $latestVerification) }}" class="btn btn-secondary btn-sm" target="_blank">
                        <x-icon name="document-arrow-down" class="w-3.5 h-3.5 mr-1" />
                        Download PDF Certificate
                    </a>
                @endif
                <form action="{{ route('integrity.reversals.verify', $reversal) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">
                        <x-icon name="check" class="w-3.5 h-3.5 mr-1" />
                        Verify reversal now
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

    {{-- Prominent Outcome Callout Banner --}}
    @if ($latestVerification)
        @if ($latestVerification->result === 'MATCH')
            <div class="mb-6 p-5 rounded-lg bg-emerald-50 border-2 border-emerald-300 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="p-2 bg-emerald-100 text-emerald-800 rounded-full font-bold text-lg">✓</span>
                    <div>
                        <h4 class="text-base font-bold text-emerald-900">REVERSAL INTEGRITY VERIFIED: MATCH</h4>
                        <p class="text-xs text-emerald-800 mt-1 leading-relaxed">
                            {{ $latestVerification->remarks }}
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($latestVerification->result === 'MISMATCH')
            <div class="mb-6 p-5 rounded-lg bg-rose-50 border-2 border-rose-400 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="p-2 bg-rose-100 text-rose-800 rounded-full font-bold text-lg">✕</span>
                    <div>
                        <h4 class="text-base font-bold text-rose-900">SECURITY ALERT: REVERSAL MISMATCH (UC-31 E1)</h4>
                        <p class="text-xs text-rose-800 mt-1 leading-relaxed">
                            {{ $latestVerification->remarks }}
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($latestVerification->result === 'UNVERIFIABLE')
            <div class="mb-6 p-5 rounded-lg bg-amber-50 border-2 border-amber-300 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="p-2 bg-amber-100 text-amber-800 rounded-full font-bold text-lg">?</span>
                    <div>
                        <h4 class="text-base font-bold text-amber-900">STATUS: UNVERIFIABLE</h4>
                        <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                            {{ $latestVerification->remarks }}
                        </p>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Reversal Details Card --}}
    <x-card title="Reversal Information" class="mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-ink-muted block">Reversal Record:</span>
                <span class="font-semibold text-ink block mt-1">#{{ $reversal->reversal_record_id }}</span>
            </div>
            <div>
                <span class="text-ink-muted block">Target Payroll Run:</span>
                <a href="{{ route('payroll-runs.show', $reversal->payroll_run_id) }}" class="link font-semibold block mt-1">
                    Run #{{ $reversal->payroll_run_id }}
                </a>
            </div>
            <div>
                <span class="text-ink-muted block">Reversed At:</span>
                <span class="text-ink font-semibold tabular block mt-1">{{ $reversal->reversed_at ? $reversal->reversed_at->format('Y-m-d H:i:s') : '—' }}</span>
            </div>
            <div>
                <span class="text-ink-muted block">Reversed By:</span>
                <span class="text-ink font-semibold block mt-1">{{ $reversal->reverser?->username ?? 'User #' . $reversal->reversed_by }}</span>
            </div>
            <div class="sm:col-span-4">
                <span class="text-ink-muted block">Mandatory Reversal Reason:</span>
                <p class="p-2 bg-slate-50 border border-line rounded text-ink mt-1 font-medium italic">
                    "{{ $reversal->reason }}"
                </p>
            </div>
        </div>
    </x-card>

    {{-- Side-by-Side Cryptographic Fingerprints Card --}}
    <x-card title="Cryptographic Fingerprint Comparison" class="mb-6">
        <div class="space-y-4 text-xs">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-ink-muted font-semibold">1. Anchored Local Fingerprint:</span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold bg-purple-100 text-purple-800">
                        Chain Pos #{{ $anchor->chain_position }}
                    </span>
                </div>
                <p class="font-mono text-xs font-bold text-ink bg-slate-50 p-2.5 rounded border border-line break-all select-all">
                    {{ $anchor->payload_hash }}
                </p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-ink-muted font-semibold">2. Hyperledger Besu Ledger Fingerprint:</span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold {{ $anchor->anchor_status === 'CONFIRMED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $anchor->anchor_status }}
                    </span>
                </div>
                <p class="font-mono text-xs font-bold text-ink bg-slate-50 p-2.5 rounded border border-line break-all select-all">
                    {{ $ledgerHash ?? ($anchor->anchor_status === 'CONFIRMED' ? $anchor->payload_hash : 'Awaiting ledger inclusion...') }}
                </p>
            </div>
        </div>
    </x-card>

    {{-- Verification History --}}
    <div class="mb-3">
        <h3 class="text-sm font-semibold text-ink">Verification History</h3>
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
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold {{ $v->result === 'MATCH' ? 'bg-emerald-100 text-emerald-800' : ($v->result === 'MISMATCH' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                            {{ $v->result }}
                        </span>
                    </td>
                    <td class="tabular text-2xs font-mono text-ink-muted" title="{{ $v->recomputed_hash }}">
                        {{ substr($v->recomputed_hash, 0, 16) }}...
                    </td>
                    <td class="text-xs text-ink">
                        {{ $v->performer?->username ?? '—' }}
                    </td>
                    <td class="text-xs text-ink-muted">{{ $v->remarks ?? '—' }}</td>
                    <td class="text-right">
                        <a href="{{ route('integrity.verifications.pdf', $v) }}" class="btn btn-secondary btn-xs" target="_blank">
                            Certificate PDF
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-6 text-center text-ink-muted text-sm">
                        No verification checks performed yet.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection
