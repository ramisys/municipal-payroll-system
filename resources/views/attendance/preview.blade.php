@extends('layouts.app')

@section('title', 'Preview attendance import')
@section('heading', 'Preview attendance import')

@section('content')
    <x-page-header
        title="Preview import"
        subtitle="{{ $period->payroll_year }} P{{ $period->period_no }} ({{ $period->cutoff_start->toDateString() }} to {{ $period->cutoff_end->toDateString() }})"
        :back="route('attendance-import.create')" back-label="Import attendance" />

    {{-- The counts are the decision, so they lead — as tiles to scan, and as a
         sentence underneath, which is also the phrasing AC-1.3.1 is checked on. --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-stat label="Will be committed" :value="count($accepted)" hint="row(s)" tone="ok" />
        <x-stat label="Rejected" :value="count($rejected)" hint="row(s)"
                :tone="count($rejected) > 0 ? 'bad' : 'default'" />
        <x-stat label="Will replace an existing record" :value="$existingCount" hint="employee/date combination(s)"
                :tone="$existingCount > 0 ? 'brand' : 'default'" />
    </div>

    <p class="note">
        {{ count($accepted) }} row(s) will be committed, {{ count($rejected) }} row(s) rejected.
        @if ($existingCount > 0)
            {{ $existingCount }} of the accepted employee/date combinations already have a stored record and will be replaced.
        @endif
    </p>

    <x-alert type="warn" title="Nothing has been written yet.">
        UC-13 AC-1.3.1 — confirming below commits every accepted row in one transaction. If any part
        of the commit fails, none of it is written (all-or-nothing).
        @if ($existingCount > 0)
            <strong class="block mt-1">
                {{ $existingCount }} of the accepted employee/date combinations already have a stored
                record and will be replaced.
            </strong>
        @endif
    </x-alert>

    @if (count($rejected) > 0)
        <x-card title="Rejected rows" subtitle="These will not be committed. Correct them in the source file and import again."
                :flush="true">
            <x-table>
                <x-slot:head>
                    <th class="num">Row</th>
                    <th>Reason</th>
                </x-slot:head>

                @foreach ($rejected as $row)
                    <tr>
                        <td class="num font-medium">{{ $row['row_number'] }}</td>
                        <td class="text-bad-fg">{{ $row['reason'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    @if (count($accepted) > 0)
        <x-card title="Accepted rows" :flush="true">
            <x-table>
                <x-slot:head>
                    <th class="num">Row</th>
                    <th>Employee no.</th>
                    <th>Date</th>
                    <th>Time in</th>
                    <th>Time out</th>
                    <th class="num">Hours worked</th>
                    <th class="num">Late (min)</th>
                    <th class="num">Undertime (min)</th>
                    <th class="num">Overtime (hrs)</th>
                    <th class="num">Night diff. (hrs)</th>
                    <th>Day classification</th>
                </x-slot:head>

                @foreach ($accepted as $row)
                    <tr>
                        <td class="num">{{ $row['row_number'] }}</td>
                        <td class="font-medium tabular">{{ $row['employee_no'] }}</td>
                        <td class="tabular">{{ $row['work_date'] }}</td>
                        <td class="tabular">{{ $row['time_in'] }}</td>
                        <td class="tabular">{{ $row['time_out'] }}</td>
                        <td class="num">{{ $row['hours_worked'] }}</td>
                        <td class="num">{{ $row['late_minutes'] }}</td>
                        <td class="num">{{ $row['undertime_minutes'] }}</td>
                        <td class="num">{{ $row['overtime_hours'] }}</td>
                        <td class="num">{{ $row['night_diff_hours'] }}</td>
                        <td>{{ $row['day_classification'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    {{-- Commit and cancel are separate forms posting to separate routes, so they
         sit side by side here rather than nesting. --}}
    <div class="flex flex-wrap items-center gap-2">
        @if (count($accepted) > 0)
            <form method="POST" action="{{ route('attendance-import.commit') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check" />
                    Confirm and commit {{ count($accepted) }} row(s)
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('attendance-import.cancel') }}">
            @csrf
            <button type="submit" class="btn btn-secondary"><x-icon name="x" />Cancel import</button>
        </form>
    </div>
@endsection
