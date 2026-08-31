@extends('layouts.app')

@section('title', 'Edit period')
@section('heading', 'Edit period ' . $period->payroll_year . '-' . $period->period_no)

@section('content')
    <x-page-header
        title="Edit period {{ $period->payroll_year }}-{{ $period->period_no }}"
        :back="route('organization.periods.index')" back-label="Pay periods" />

    <div class="max-w-2xl space-y-4">
        <x-note>
            UC-03 A1 — the whole payroll year is re-validated against BR-34 before this save is accepted.
        </x-note>

        <x-card>
            <form method="POST" action="{{ route('organization.periods.update', $period) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-field label="Cut-off start" name="cutoff_start" required>
                    <input type="date" id="cutoff_start" name="cutoff_start" class="input"
                           value="{{ old('cutoff_start', $period->cutoff_start->toDateString()) }}" required autofocus>
                </x-field>

                <x-field label="Cut-off end" name="cutoff_end" required>
                    <input type="date" id="cutoff_end" name="cutoff_end" class="input"
                           value="{{ old('cutoff_end', $period->cutoff_end->toDateString()) }}" required>
                </x-field>

                <x-field label="Pay date" name="pay_date" required>
                    <input type="date" id="pay_date" name="pay_date" class="input"
                           value="{{ old('pay_date', $period->pay_date->toDateString()) }}" required>
                </x-field>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('organization.periods.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
