@extends('layouts.app')

@section('title', 'Report catalogue')
@section('heading', 'Reports')

@section('content')
    <x-page-header title="Report catalogue" subtitle="Governed payroll, remittance, and operational reports (UC-30 / FR-5.3).">
    </x-page-header>

    <x-note class="mb-6">
        <p class="font-medium text-ink">FR-5.3 Report Governance</p>
        <p class="text-xs text-ink-muted mt-0.5">
            Reports draw strictly from stored payroll data without manual compilation (AC-5.3.1).
            Any report generated over an unfinalized run is visibly watermarked provisional (AC-5.3.5).
            Remittance schedules and employer share derivations are scheduled for Milestone P-D (Week 13).
        </p>
    </x-note>

    <div class="space-y-6">
        @foreach ($groupedCatalogue as $category => $items)
            <div>
                <h2 class="text-sm font-semibold text-ink uppercase tracking-wider mb-3 px-1">{{ $category }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($items as $item)
                        @php
                            $badgeClass = match ($item['priority']) {
                                'Must' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'Should' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'Could' => 'bg-slate-100 text-slate-700 border-slate-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <x-card class="flex flex-col justify-between h-full">
                            <div>
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <h3 class="text-sm font-semibold text-ink">{{ $item['name'] }}</h3>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium border {{ $badgeClass }}">
                                        {{ $item['priority'] }}
                                    </span>
                                </div>
                                <p class="text-xs text-ink-muted mb-4">{{ $item['description'] }}</p>
                            </div>
                            <div class="pt-3 border-t border-line flex items-center justify-between">
                                <span class="text-[11px] text-ink-muted">
                                    @if ($item['requires_period'])
                                        By period
                                    @elseif ($item['requires_year'])
                                        By year
                                    @endif
                                </span>
                                <a href="{{ route('reports.show', $item['slug']) }}" class="btn btn-secondary btn-sm">
                                    <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                                    Configure
                                </a>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endsection
