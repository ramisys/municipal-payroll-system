@extends('layouts.app')

@section('title', "{$schedule->agency} ({$schedule->schedule_version}) — Statutory Schedule")
@section('heading', 'Statutory Schedule Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('statutory-schedules.index', ['agency' => $schedule->agency]) }}" class="link text-sm inline-flex items-center gap-1">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            Back to {{ $schedule->agency }} schedules
        </a>
    </div>

    <x-page-header title="{{ $schedule->agency }} — {{ $schedule->schedule_version }}" subtitle="Effectivity-dated statutory reference table (FR-2.3).">
        <x-slot:actions>
            @if ($canManage && ! $isApplied)
                <a href="{{ route('statutory-schedules.edit', $schedule) }}" class="btn btn-secondary btn-sm">
                    <x-icon name="edit" class="w-3.5 h-3.5" />
                    Edit schedule
                </a>
            @endif
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

    {{-- Overview Card --}}
    <x-card class="mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-ink-muted font-medium">Agency:</span>
                <span class="text-ink ml-1 font-semibold">{{ $schedule->agency }}</span>
            </div>
            <div>
                <span class="text-ink-muted font-medium">Effective from:</span>
                <span class="text-ink ml-1 font-semibold tabular">{{ $schedule->effective_from->format('Y-m-d') }}</span>
            </div>
            <div>
                <span class="text-ink-muted font-medium">Effective to:</span>
                <span class="text-ink ml-1 font-semibold tabular">{{ $schedule->effective_to ? $schedule->effective_to->format('Y-m-d') : 'Indefinite' }}</span>
            </div>
            <div>
                <span class="text-ink-muted font-medium">Pay frequency:</span>
                <span class="text-ink ml-1 font-semibold">{{ $schedule->pay_frequency ?? 'MONTHLY' }}</span>
            </div>
            @if ($schedule->issuance_reference)
                <div class="sm:col-span-2">
                    <span class="text-ink-muted font-medium">Issuance reference:</span>
                    <span class="text-ink ml-1 font-semibold">{{ $schedule->issuance_reference }}</span>
                </div>
            @endif
            @if ($schedule->agency === 'PHILHEALTH')
                <div>
                    <span class="text-ink-muted font-medium">Premium rate:</span>
                    <span class="text-ink ml-1 font-semibold">{{ number_format((float) ($schedule->premium_rate * 100), 2) }}%</span>
                </div>
                <div>
                    <span class="text-ink-muted font-medium">Salary floor / ceiling:</span>
                    <span class="text-ink ml-1 font-semibold">₱{{ number_format((float) $schedule->salary_floor, 2) }} – ₱{{ number_format((float) $schedule->salary_ceiling, 2) }}</span>
                </div>
            @elseif ($schedule->agency === 'PAGIBIG')
                <div>
                    <span class="text-ink-muted font-medium">Compensation cap:</span>
                    <span class="text-ink ml-1 font-semibold">₱{{ number_format((float) $schedule->compensation_cap, 2) }}</span>
                </div>
            @endif
        </div>

        @if ($canManage && $schedule->effective_to === null)
            <div class="mt-4 pt-4 border-t border-line flex items-center justify-between">
                <span class="text-xs text-ink-muted">Set an end date to close this version when a newer schedule supersedes it (UC-05 A2).</span>
                <form action="{{ route('statutory-schedules.end-date', $schedule) }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <input type="date" name="effective_to" class="input input-sm text-xs py-1" required min="{{ $schedule->effective_from->format('Y-m-d') }}">
                    <button type="submit" class="btn btn-secondary btn-xs">
                        Set end date
                    </button>
                </form>
            </div>
        @endif
    </x-card>

    {{-- Brackets Table (if applicable) --}}
    @if ($schedule->brackets->isNotEmpty())
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-ink">Contribution / Tax Brackets</h3>
            <p class="text-xs text-ink-muted">Sequential contiguous brackets enforced with no gaps and no overlaps (UC-05 E2).</p>
        </div>
        <x-card :flush="true">
            <x-table>
                <x-slot:head>
                    <th>#</th>
                    <th class="num">Salary / Comp Range From</th>
                    <th class="num">Range To</th>
                    @if ($schedule->agency === 'BIR')
                        <th class="num">Base tax</th>
                        <th class="num">Marginal rate</th>
                    @else
                        <th class="num">Employee share</th>
                        <th class="num">Employer share (Derived)</th>
                        <th class="num">Total</th>
                    @endif
                </x-slot:head>
                @foreach ($schedule->brackets as $b)
                    <tr>
                        <td class="tabular text-xs font-mono text-ink-muted">{{ $b->bracket_sequence }}</td>
                        <td class="num tabular text-xs font-mono">₱{{ number_format((float) $b->range_from, 2) }}</td>
                        <td class="num tabular text-xs font-mono">
                            {{ $b->range_to !== null ? '₱' . number_format((float) $b->range_to, 2) : 'and above' }}
                        </td>
                        @if ($schedule->agency === 'BIR')
                            <td class="num tabular text-xs font-mono">₱{{ number_format((float) $b->base_tax, 2) }}</td>
                            <td class="num tabular text-xs font-mono">{{ number_format((float) ($b->marginal_rate * 100), 2) }}%</td>
                        @else
                            <td class="num tabular text-xs font-mono">₱{{ number_format((float) $b->employee_share, 2) }}</td>
                            <td class="num tabular text-xs font-mono font-semibold text-brand-900">₱{{ number_format((float) $b->employer_share, 2) }}</td>
                            <td class="num tabular text-xs font-mono text-ink-muted">
                                ₱{{ number_format((float) ($b->employee_share + $b->employer_share), 2) }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
@endsection
