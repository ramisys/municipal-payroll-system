@extends('layouts.app')

@section('title', 'Finalize Run #' . $run->payroll_run_id)
@section('heading', 'Finalize Run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Finalize Run #{{ $run->payroll_run_id }}"
        subtitle="Commit payroll run for payment and permanently lock records (FR-4.5 / NFR-6.3)"
        :back="route('payroll-runs.show', $run)" back-label="Back to run">
        <x-slot:actions>
            <x-status-badge :value="$run->run_status" class="text-sm px-2.5 py-1" />
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-2xl mx-auto space-y-6">
        <x-alert type="warning" title="Important Confirmation (NFR-6.3)">
            Finalization commits this payroll run for payment. Once finalized:
            <ul class="list-disc list-inside mt-2 space-y-1 text-xs">
                <li>All payroll lines, earning lines, and deduction lines become strictly <strong>immutable</strong> (FR-4.5, AC-4.5.1).</li>
                <li>Totals are stored permanently on the run record.</li>
                <li>Exactly one cryptographic integrity anchor is computed and queued for ledger anchoring (FR-6.3, AC-4.5.5).</li>
                <li>Payslip generation (UC-27) is unlocked.</li>
                <li>Reversal is restricted and only permitted before payslips are issued and the pay date has passed (BR-24).</li>
            </ul>
        </x-alert>

        <x-card title="Run Verification Figures">
            <dl class="grid grid-cols-2 gap-4">
                <x-kv label="Pay Period">
                    <span class="tabular font-semibold">{{ $run->period->payroll_year }}-{{ $run->period->period_no }}</span>
                </x-kv>
                <x-kv label="Cut-off Dates">
                    <span class="tabular">{{ $run->period->cutoff_start->toDateString() }} to {{ $run->period->cutoff_end->toDateString() }}</span>
                </x-kv>
                <x-kv label="Pay Date">
                    <span class="tabular font-semibold">{{ $run->period->pay_date->toDateString() }}</span>
                </x-kv>
                <x-kv label="Employee Count">
                    <span class="font-bold text-stone-900 dark:text-stone-100">{{ $run->lines->count() }} employee(s)</span>
                </x-kv>
            </dl>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-6 pt-6 border-t border-stone-200 dark:border-stone-800">
                <x-stat label="Gross Pay" :value="$totals['gross']" />
                <x-stat label="Total Deductions" :value="$totals['deductions']" tone="bad" />
                <x-stat label="Net Pay" :value="$totals['net']" tone="ok" />
            </div>
        </x-card>

        <x-card title="Confirm Finalization">
            <form method="POST" action="{{ route('payroll-runs.finalize', $run) }}" class="space-y-4">
                @csrf

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('payroll-runs.show', $run) }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="lock" />
                        Confirm and Finalize Run
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
