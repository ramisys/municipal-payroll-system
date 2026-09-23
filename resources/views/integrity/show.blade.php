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

    <x-page-header title="Integrity Anchor — Run #{{ $run->payroll_run_id }}" subtitle="Cryptographic SHA-256 fingerprint anchored at finalization (FR-6.3, UC-31).">
        <x-slot:actions>
            <form action="{{ route('integrity.verify-run', $run) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <x-icon name="check" class="w-3.5 h-3.5" />
                    Verify integrity now
                </button>
            </form>
        </x-slot:actions>
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

    {{-- Anchor Fingerprint Card --}}
    <x-card title="Anchored Fingerprint" class="mb-6">
        <div class="space-y-3 text-xs">
            <div>
                <span class="text-ink-muted font-medium">Canonical Payload Hash (SHA-256):</span>
                <p class="font-mono text-xs font-bold text-ink bg-slate-50 p-2 rounded border border-line break-all mt-1 select-all">
                    {{ $anchor->payload_hash }}
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 pt-2">
                <div>
                    <span class="text-ink-muted font-medium">Algorithm:</span>
                    <span class="text-ink ml-1 font-semibold">{{ $anchor->hash_algorithm }}</span>
                </div>
                <div>
                    <span class="text-ink-muted font-medium">Chain position:</span>
                    <span class="text-ink ml-1 font-semibold">#{{ $anchor->chain_position }}</span>
                </div>
                <div>
                    <span class="text-ink-muted font-medium">Outbox status:</span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold {{ $anchor->anchor_status === 'CONFIRMED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $anchor->anchor_status }}
                    </span>
                </div>
                <div>
                    <span class="text-ink-muted font-medium">Queued at:</span>
                    <span class="text-ink ml-1 font-semibold tabular">{{ $anchor->queued_at->format('Y-m-d H:i:s') }}</span>
                </div>
            </div>
        </div>
    </x-card>

    {{-- Verification History --}}
    <div class="mb-3">
        <h3 class="text-sm font-semibold text-ink">Verification History</h3>
        <p class="text-xs text-ink-muted">Every check is recorded in append-only storage with recomputed hash and actor (UC-31).</p>
    </div>

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Performed At</th>
                <th>Outcome</th>
                <th>Recomputed Hash</th>
                <th>Verified By</th>
                <th>Remarks</th>
            </x-slot:head>
            @forelse ($verifications as $v)
                <tr>
                    <td class="tabular text-xs">{{ $v->performed_at->format('Y-m-d H:i:s') }}</td>
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
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-slate-100 text-slate-700">
                                {{ $v->result }}
                            </span>
                        @endif
                    </td>
                    <td class="tabular text-2xs font-mono text-ink-muted" title="{{ $v->recomputed_hash }}">
                        {{ substr($v->recomputed_hash, 0, 16) }}...
                    </td>
                    <td class="text-xs text-ink">
                        {{ $v->performer?->username ?? '—' }} ({{ $v->performer?->role?->role_name ?? '—' }})
                    </td>
                    <td class="text-xs text-ink-muted">{{ $v->remarks ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-6 text-center text-ink-muted text-sm">
                        No verification checks performed yet. Click "Verify integrity now" to recompute live payload hash and verify.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection
