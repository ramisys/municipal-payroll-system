@extends('layouts.app')

@section('title', "Create {$agency} Statutory Schedule")
@section('heading', 'Create Statutory Schedule')

@section('content')
    <div class="mb-4">
        <a href="{{ route('statutory-schedules.index', ['agency' => $agency]) }}" class="link text-sm inline-flex items-center gap-1">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            Back to schedules
        </a>
    </div>

    <x-page-header title="New {{ $agency }} Schedule Version" subtitle="Define effectivity range and contribution/tax parameters (UC-05). Overlapping effectivity is rejected at save (BR-14).">
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

    <form action="{{ route('statutory-schedules.store') }}" method="POST">
        @csrf
        <input type="hidden" name="agency" value="{{ $agency }}">

        <x-card title="Schedule metadata" class="mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="schedule_version">Schedule version <span class="text-rose-600">*</span></label>
                    <input type="text" id="schedule_version" name="schedule_version" class="input" value="{{ old('schedule_version') }}" required placeholder="e.g. {{ $agency }}-2026">
                </div>
                <div>
                    <label class="label" for="issuance_reference">Issuance reference</label>
                    <input type="text" id="issuance_reference" name="issuance_reference" class="input" value="{{ old('issuance_reference') }}" placeholder="Circular / memo reference number">
                </div>
                <div>
                    <label class="label" for="effective_from">Effective from <span class="text-rose-600">*</span></label>
                    <input type="date" id="effective_from" name="effective_from" class="input" value="{{ old('effective_from') }}" required>
                </div>
                <div>
                    <label class="label" for="effective_to">Effective to</label>
                    <input type="date" id="effective_to" name="effective_to" class="input" value="{{ old('effective_to') }}" placeholder="Leave blank for indefinite">
                </div>
            </div>

            @if ($agency === 'PHILHEALTH')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-line">
                    <div>
                        <label class="label" for="premium_rate">Premium rate (decimal, e.g. 0.05 for 5%)</label>
                        <input type="number" step="0.0001" id="premium_rate" name="premium_rate" class="input" value="{{ old('premium_rate', '0.0500') }}">
                    </div>
                    <div>
                        <label class="label" for="salary_floor">Salary floor (₱)</label>
                        <input type="number" step="0.01" id="salary_floor" name="salary_floor" class="input" value="{{ old('salary_floor', '10000.00') }}">
                    </div>
                    <div>
                        <label class="label" for="salary_ceiling">Salary ceiling (₱)</label>
                        <input type="number" step="0.01" id="salary_ceiling" name="salary_ceiling" class="input" value="{{ old('salary_ceiling', '100000.00') }}">
                    </div>
                </div>
            @elseif ($agency === 'PAGIBIG')
                <div class="mt-4 pt-4 border-t border-line max-w-sm">
                    <label class="label" for="compensation_cap">Maximum compensation cap (₱)</label>
                    <input type="number" step="0.01" id="compensation_cap" name="compensation_cap" class="input" value="{{ old('compensation_cap', '10000.00') }}">
                </div>
            @endif
        </x-card>

        @if ($agency === 'SSS' || $agency === 'BIR' || $agency === 'PAGIBIG')
            <x-card title="Brackets (Optional for rate-based, required for bracket-based)" class="mb-6">
                <p class="text-xs text-ink-muted mb-4">Brackets must be contiguous with no gaps and no overlaps (UC-05 E2).</p>
                <div class="space-y-3">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center bg-slate-50 p-2.5 rounded border border-line text-xs">
                            <div>
                                <label class="label text-2xs mb-0.5">Range from (₱)</label>
                                <input type="number" step="0.01" name="brackets[{{ $i }}][range_from]" class="input input-sm text-xs" value="{{ old("brackets.{$i}.range_from") }}" placeholder="0.00">
                            </div>
                            <div>
                                <label class="label text-2xs mb-0.5">Range to (₱)</label>
                                <input type="number" step="0.01" name="brackets[{{ $i }}][range_to]" class="input input-sm text-xs" value="{{ old("brackets.{$i}.range_to") }}" placeholder="leave empty if upper open">
                            </div>
                            @if ($agency === 'BIR')
                                <div>
                                    <label class="label text-2xs mb-0.5">Base tax (₱)</label>
                                    <input type="number" step="0.01" name="brackets[{{ $i }}][base_tax]" class="input input-sm text-xs" value="{{ old("brackets.{$i}.base_tax") }}" placeholder="0.00">
                                </div>
                                <div>
                                    <label class="label text-2xs mb-0.5">Marginal rate (e.g. 0.15)</label>
                                    <input type="number" step="0.0001" name="brackets[{{ $i }}][marginal_rate]" class="input input-sm text-xs" value="{{ old("brackets.{$i}.marginal_rate") }}" placeholder="0.0000">
                                </div>
                            @else
                                <div>
                                    <label class="label text-2xs mb-0.5">Employee share (₱)</label>
                                    <input type="number" step="0.01" name="brackets[{{ $i }}][employee_share]" class="input input-sm text-xs" value="{{ old("brackets.{$i}.employee_share") }}" placeholder="0.00">
                                </div>
                                <div>
                                    <label class="label text-2xs mb-0.5">Employer share (₱)</label>
                                    <input type="number" step="0.01" name="brackets[{{ $i }}][employer_share]" class="input input-sm text-xs" value="{{ old("brackets.{$i}.employer_share") }}" placeholder="0.00">
                                </div>
                            @endif
                        </div>
                    @endfor
                </div>
            </x-card>
        @endif

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                Save schedule version
            </button>
            <a href="{{ route('statutory-schedules.index', ['agency' => $agency]) }}" class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>
@endsection
