@extends('layouts.app')

@section('title', "Edit {$schedule->agency} ({$schedule->schedule_version})")
@section('heading', 'Edit Statutory Schedule')

@section('content')
    <div class="mb-4">
        <a href="{{ route('statutory-schedules.show', $schedule) }}" class="link text-sm inline-flex items-center gap-1">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            Back to schedule details
        </a>
    </div>

    <x-page-header title="Edit {{ $schedule->agency }} — {{ $schedule->schedule_version }}" subtitle="Update parameters of an unused schedule (UC-05 A1). Applied schedules are locked (UC-05 E3).">
    </x-page-header>

    @if ($errors->any())
        <div class="mb-6 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('statutory-schedules.update', $schedule) }}" method="POST">
        @csrf
        @method('PUT')

        <x-card title="Schedule metadata" class="mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="schedule_version">Schedule version <span class="text-rose-600">*</span></label>
                    <input type="text" id="schedule_version" name="schedule_version" class="input" value="{{ old('schedule_version', $schedule->schedule_version) }}" required>
                </div>
                <div>
                    <label class="label" for="issuance_reference">Issuance reference</label>
                    <input type="text" id="issuance_reference" name="issuance_reference" class="input" value="{{ old('issuance_reference', $schedule->issuance_reference) }}">
                </div>
                <div>
                    <label class="label" for="effective_from">Effective from <span class="text-rose-600">*</span></label>
                    <input type="date" id="effective_from" name="effective_from" class="input" value="{{ old('effective_from', $schedule->effective_from->format('Y-m-d')) }}" required>
                </div>
                <div>
                    <label class="label" for="effective_to">Effective to</label>
                    <input type="date" id="effective_to" name="effective_to" class="input" value="{{ old('effective_to', $schedule->effective_to?->format('Y-m-d')) }}">
                </div>
            </div>

            @if ($schedule->agency === 'PHILHEALTH')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-line">
                    <div>
                        <label class="label" for="premium_rate">Premium rate</label>
                        <input type="number" step="0.0001" id="premium_rate" name="premium_rate" class="input" value="{{ old('premium_rate', $schedule->premium_rate) }}">
                    </div>
                    <div>
                        <label class="label" for="salary_floor">Salary floor (₱)</label>
                        <input type="number" step="0.01" id="salary_floor" name="salary_floor" class="input" value="{{ old('salary_floor', $schedule->salary_floor) }}">
                    </div>
                    <div>
                        <label class="label" for="salary_ceiling">Salary ceiling (₱)</label>
                        <input type="number" step="0.01" id="salary_ceiling" name="salary_ceiling" class="input" value="{{ old('salary_ceiling', $schedule->salary_ceiling) }}">
                    </div>
                </div>
            @elseif ($schedule->agency === 'PAGIBIG')
                <div class="mt-4 pt-4 border-t border-line max-w-sm">
                    <label class="label" for="compensation_cap">Maximum compensation cap (₱)</label>
                    <input type="number" step="0.01" id="compensation_cap" name="compensation_cap" class="input" value="{{ old('compensation_cap', $schedule->compensation_cap) }}">
                </div>
            @endif
        </x-card>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                Update schedule
            </button>
            <a href="{{ route('statutory-schedules.show', $schedule) }}" class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>
@endsection
