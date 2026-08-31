@extends('layouts.app')

@section('title', 'Cancel run #' . $run->payroll_run_id)
@section('heading', 'Cancel run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Cancel run #{{ $run->payroll_run_id }}"
        :back="route('payroll-runs.show', $run)" back-label="Run #{{ $run->payroll_run_id }}" />

    <div class="max-w-2xl space-y-4">
        <x-alert type="bad" title="This is irreversible.">
            UC-17 A2, NFR-6.3 — the run and its history remain readable but cannot be reopened,
            imported into, or submitted.
        </x-alert>

        <x-card>
            <form method="POST" action="{{ route('payroll-runs.cancel', $run) }}" class="space-y-4">
                @csrf

                <x-field label="Reason" name="reason" required
                         hint="Recorded against the run and written to the audit log.">
                    <input type="text" id="reason" name="reason" class="input"
                           value="{{ old('reason') }}" required maxlength="255" autofocus>
                </x-field>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-danger-solid">Confirm cancellation</button>
                    <a href="{{ route('payroll-runs.show', $run) }}" class="btn btn-secondary">Keep this run</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
