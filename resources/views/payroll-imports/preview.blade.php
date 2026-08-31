@extends('layouts.app')

@section('title', 'Preview register import — run #' . $run->payroll_run_id)
@section('heading', 'Preview register import — run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Preview register import"
        subtitle="Run #{{ $run->payroll_run_id }} · {{ $map->map_name }} v{{ $map->version_no }}"
        :back="route('payroll-imports.create', $run)" back-label="Import computed register" />

    @if ($defects !== [])
        {{-- A defective register is refused outright — there is no partial
             commit to offer, so the only action here is to cancel. --}}
        <x-alert type="bad" title="Refused — {{ count($defects) }} reconciliation defect(s).">
            This file does not reconcile and cannot be committed. Correct it at source and import again.
        </x-alert>

        <x-card title="Reconciliation defects" :flush="true">
            <x-table>
                <x-slot:head>
                    <th>Type</th>
                    <th class="num">Row</th>
                    <th>Employee no.</th>
                    <th>Message</th>
                </x-slot:head>

                @foreach ($defects as $defect)
                    <tr>
                        <td class="font-medium text-bad-fg">{{ $defect['type'] }}</td>
                        <td class="num">{{ $defect['row'] ?? '—' }}</td>
                        <td class="tabular">{{ $defect['employee_no'] ?? '—' }}</td>
                        <td>{{ $defect['message'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <form method="POST" action="{{ route('payroll-imports.cancel', $run) }}">
            @csrf
            <button type="submit" class="btn btn-secondary">Cancel import</button>
        </form>
    @else
        <x-alert type="warn" title="Nothing has been written yet.">
            UC-18 A2 — confirming below reconciles and commits this exact file in one transaction
            (AC-2.8.7); if any part fails, none of it is written.
        </x-alert>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <x-stat label="Rows" :value="$result->rowCount" />
            <x-stat label="Employees matched" :value="$result->matchedEmployeeCount" />
            <x-stat label="Control gross" :value="$result->controlTotalGross" />
            <x-stat label="Control deductions" :value="$result->controlTotalDeductions" tone="bad" />
            <x-stat label="Control net" :value="$result->controlTotalNet" tone="ok" />
        </div>

        <p class="note">
            {{ $result->rowCount }} row(s) reconcile — gross {{ $result->controlTotalGross }},
            deductions {{ $result->controlTotalDeductions }}, net {{ $result->controlTotalNet }},
            {{ $result->matchedEmployeeCount }} employee(s) matched.
        </p>

        <x-card title="Rows to be committed" :flush="true">
            <x-table>
                <x-slot:head>
                    <th class="num">Row</th>
                    <th>Employee no.</th>
                    <th class="num">Gross</th>
                    <th class="num">Deductions</th>
                    <th class="num">Net</th>
                </x-slot:head>

                @foreach ($rows as $row)
                    <tr>
                        <td class="num">{{ $row['row_number'] }}</td>
                        <td class="font-medium tabular">{{ $row['employee_no'] }}</td>
                        <td class="num">{{ $row['gross_pay'] }}</td>
                        <td class="num">{{ $row['total_deductions'] }}</td>
                        <td class="num font-semibold">{{ $row['net_pay'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('payroll-imports.commit', $run) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Confirm and commit</button>
            </form>

            <form method="POST" action="{{ route('payroll-imports.cancel', $run) }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Cancel import</button>
            </form>
        </div>
    @endif
@endsection
