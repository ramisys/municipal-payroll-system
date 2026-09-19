@extends('layouts.app')

@section('title', 'Reverse Finalized Run #' . $run->payroll_run_id)
@section('heading', 'Reverse Finalized Run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Reverse Finalized Run #{{ $run->payroll_run_id }}"
        subtitle="Exceptional reversal of a committed payroll run (FR-4.5 / UC-26 / BR-24)"
        :back="route('payroll-runs.show', $run)" back-label="Back to run">
        <x-slot:actions>
            <x-status-badge :value="$run->run_status" class="text-sm px-2.5 py-1" />
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-2xl mx-auto space-y-6">
        <x-alert type="danger" title="High-Impact Action (NFR-6.3 / BR-24)">
            Reversing a finalized run is an exceptional governance action permitted only before payslips are issued and the pay date has passed.
            <ul class="list-disc list-inside mt-2 space-y-1 text-xs">
                <li>A permanent <strong>REVERSAL_RECORD</strong> will be created, preserving the original figures (Gross: {{ $run->total_gross }}, Net: {{ $run->total_net }}, Count: {{ $run->employee_count }}).</li>
                <li>The run status is returned to <strong>DRAFT</strong> for correction and re-import.</li>
                <li>A cryptographic integrity anchor for the reversal is queued for external ledger anchoring (FR-6.3).</li>
                <li>The reversal and your stated reason are permanently retained in the audit log and transition history.</li>
            </ul>
        </x-alert>

        <x-card title="Original Run Totals">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <x-stat label="Original Gross" :value="$run->total_gross" />
                <x-stat label="Original Deductions" :value="$run->total_deductions" tone="bad" />
                <x-stat label="Original Net" :value="$run->total_net" tone="ok" />
            </div>
        </x-card>

        <x-card title="Reason for Reversal">
            <form method="POST" action="{{ route('payroll-runs.reverse', $run) }}" class="space-y-4">
                @csrf

                <x-field label="Justification & Reason (Required)" :error="$errors->first('reason')">
                    <textarea
                        name="reason"
                        id="reason"
                        rows="4"
                        required
                        class="form-control w-full"
                        placeholder="State the material error discovered and the justification for reversing this finalized run...">{{ old('reason') }}</textarea>
                </x-field>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-stone-200 dark:border-stone-800">
                    <a href="{{ route('payroll-runs.show', $run) }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger">
                        <x-icon name="history" />
                        Confirm Reversal to Draft
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
