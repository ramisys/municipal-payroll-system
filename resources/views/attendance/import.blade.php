@extends('layouts.app')

@section('title', 'Import attendance')
@section('heading', 'Import attendance')

@section('content')
    <x-page-header title="Import attendance"
                   subtitle="Load a cut-off period's attendance file for review before anything is written." />

    <div class="max-w-2xl space-y-4">
        <x-note>
            UC-13, FR-1.3 — nothing is written until the preview is confirmed (AC-1.3.1).
            Expected columns: <strong>Employee No</strong>, <strong>Date</strong>,
            <strong>Time In</strong>, <strong>Time Out</strong>.
        </x-note>

        <x-card>
            <form method="POST" action="{{ route('attendance-import.preview') }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf

                <x-field label="Cut-off period" name="payroll_period_id" required>
                    <select id="payroll_period_id" name="payroll_period_id" class="select" required>
                        @foreach ($periods as $period)
                            <option value="{{ $period->payroll_period_id }}">
                                {{ $period->payroll_year }} P{{ $period->period_no }} ({{ $period->cutoff_start->toDateString() }} to {{ $period->cutoff_end->toDateString() }})
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Attendance file" name="file" required hint="Accepts .xlsx or .csv.">
                    <input type="file" id="file" name="file" class="input-file" required accept=".xlsx,.csv">
                </x-field>

                <button type="submit" class="btn btn-primary">Preview import</button>
            </form>
        </x-card>
    </div>
@endsection
