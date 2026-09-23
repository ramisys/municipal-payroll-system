@extends('layouts.app')

@section('title', 'Statutory Tables')
@section('heading', 'Statutory Reference Tables')

@section('content')
    <x-page-header title="Statutory Reference Tables" subtitle="Effectivity-dated contribution and tax schedules used for remittance employer share derivation (FR-2.3, UC-05).">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('statutory-schedules.create', ['agency' => $activeAgency]) }}" class="btn btn-primary btn-sm">
                    <x-icon name="plus" class="w-3.5 h-3.5" />
                    New schedule version
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

    {{-- Agency Tabs --}}
    <div class="flex items-center gap-2 border-b border-line mb-6 pb-2">
        @foreach ($agencies as $ag)
            <a href="{{ route('statutory-schedules.index', ['agency' => $ag]) }}"
               class="px-3.5 py-1.5 rounded-md text-sm font-semibold transition-colors duration-150 {{ $activeAgency === $ag ? 'bg-brand-900 text-white shadow-sm' : 'text-ink-muted hover:text-ink hover:bg-slate-100' }}">
                {{ $ag === 'PHILHEALTH' ? 'PhilHealth' : ($ag === 'PAGIBIG' ? 'Pag-IBIG' : $ag) }}
            </a>
        @endforeach
    </div>

    {{-- Schedules List --}}
    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Version</th>
                <th>Effective from</th>
                <th>Effective to</th>
                <th>Issuance reference</th>
                <th>Parameters</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </x-slot:head>
            @forelse ($schedules as $sched)
                @php
                    $isCurrent = $sched->effective_from->isPast() && ($sched->effective_to === null || $sched->effective_to->isFuture());
                @endphp
                <tr>
                    <td class="font-semibold text-ink">
                        <a href="{{ route('statutory-schedules.show', $sched) }}" class="link">
                            {{ $sched->schedule_version }}
                        </a>
                    </td>
                    <td class="tabular text-xs">{{ $sched->effective_from->format('Y-m-d') }}</td>
                    <td class="tabular text-xs text-ink-muted">
                        {{ $sched->effective_to ? $sched->effective_to->format('Y-m-d') : 'Indefinite' }}
                    </td>
                    <td class="text-xs text-ink-muted">{{ $sched->issuance_reference ?? '—' }}</td>
                    <td class="text-xs text-ink-muted">
                        @if ($sched->agency === 'PHILHEALTH')
                            Rate: {{ number_format((float) ($sched->premium_rate * 100), 2) }}%, Floor: ₱{{ number_format((float) $sched->salary_floor, 2) }}, Ceiling: ₱{{ number_format((float) $sched->salary_ceiling, 2) }}
                        @elseif ($sched->agency === 'PAGIBIG')
                            Cap: ₱{{ number_format((float) $sched->compensation_cap, 2) }}
                        @else
                            {{ $sched->brackets->count() }} bracket(s)
                        @endif
                    </td>
                    <td>
                        @if ($isCurrent)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-emerald-100 text-emerald-800">
                                In effect
                            </span>
                        @elseif ($sched->effective_to && $sched->effective_to->isPast())
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-slate-100 text-slate-700">
                                Superseded
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-amber-100 text-amber-800">
                                Future
                            </span>
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('statutory-schedules.show', $sched) }}" class="btn btn-secondary btn-xs">
                            View details
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-6 text-center text-ink-muted text-sm">
                        No schedules recorded for agency {{ $activeAgency }}.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>
@endsection
