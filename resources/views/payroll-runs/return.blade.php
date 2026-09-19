@extends('layouts.app')

@section('title', 'Return Run #' . $run->payroll_run_id)
@section('heading', 'Return Run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Return Run #{{ $run->payroll_run_id }} for correction"
        subtitle="Current status: {{ $run->run_status }} · Return reason will be recorded permanently in the transition history"
        :back="route('payroll-runs.show', $run)" back-label="Back to run">
        <x-slot:actions>
            <x-status-badge :value="$run->run_status" class="text-sm px-2.5 py-1" />
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-2xl mx-auto">
        <x-card title="Return Details (UC-24)">
            <p class="text-sm text-stone-600 dark:text-stone-300 mb-4">
                Returning this run moves it to <span class="font-semibold">RETURNED</span>, opening it for correction by the Payroll Officer.
                @if ($run->run_status === 'APPROVED')
                    Because this run was previously approved, returning it will clear the recorded approver from the run (FR-4.4 / AC-4.4.6) while keeping the approval in its transition history.
                @endif
            </p>

            <form method="POST" action="{{ route('payroll-runs.return', $run) }}" class="space-y-4">
                @csrf

                <x-field label="Return Reason (Required)" :error="$errors->first('reason')">
                    <textarea
                        name="reason"
                        id="reason"
                        rows="4"
                        required
                        class="form-control w-full"
                        placeholder="State clearly why this run is being returned and what corrections are needed in the register or source data...">{{ old('reason') }}</textarea>
                </x-field>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-stone-200 dark:border-stone-800">
                    <a href="{{ route('payroll-runs.show', $run) }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-warn">
                        <x-icon name="undo-2" />
                        Confirm Return
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
