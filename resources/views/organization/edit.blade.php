@extends('layouts.app')

@section('title', 'Organization profile')
@section('heading', 'Organization profile')

@section('content')
    <x-page-header title="Organization profile and payroll calendar" />

    <x-org-tabs current="profile" />

    <form method="POST" action="{{ route('organization.update') }}" class="space-y-4 max-w-4xl">
        @csrf
        @method('PUT')

        <x-card title="Identity">
            <div class="form-grid">
                <x-field label="Registered name" name="registered_name" required :span="true">
                    <input type="text" id="registered_name" name="registered_name" class="input"
                           value="{{ old('registered_name', $profile->registered_name) }}" required autofocus>
                </x-field>

                <x-field label="Address" name="address" :span="true">
                    <input type="text" id="address" name="address" class="input"
                           value="{{ old('address', $profile->address) }}">
                </x-field>
            </div>
        </x-card>

        <x-card title="Employer registration numbers">
            <div class="form-grid">
                <x-field label="SSS employer number" name="sss_employer_no">
                    <input type="text" id="sss_employer_no" name="sss_employer_no" class="input font-mono"
                           value="{{ old('sss_employer_no', $profile->sss_employer_no) }}">
                </x-field>

                <x-field label="PhilHealth employer number" name="philhealth_employer_no">
                    <input type="text" id="philhealth_employer_no" name="philhealth_employer_no" class="input font-mono"
                           value="{{ old('philhealth_employer_no', $profile->philhealth_employer_no) }}">
                </x-field>

                <x-field label="Pag-IBIG employer number" name="pagibig_employer_no">
                    <input type="text" id="pagibig_employer_no" name="pagibig_employer_no" class="input font-mono"
                           value="{{ old('pagibig_employer_no', $profile->pagibig_employer_no) }}">
                </x-field>

                <x-field label="BIR TIN" name="bir_tin">
                    <input type="text" id="bir_tin" name="bir_tin" class="input font-mono"
                           value="{{ old('bir_tin', $profile->bir_tin) }}">
                </x-field>
            </div>
        </x-card>

        <x-card title="Payroll basis">
            <x-field label="Standard hours per day" name="standard_hours_per_day" required>
                <input type="text" id="standard_hours_per_day" name="standard_hours_per_day"
                       class="input tabular max-w-[12rem]"
                       value="{{ old('standard_hours_per_day', $standardHoursPerDay) }}" required inputmode="decimal">
            </x-field>

            <x-note class="mt-3">
                BR-03 — used only to derive the input worksheet's hours-worked column. The system holds
                no day factor and applies no rate (A-05, OI-03 closed).
            </x-note>
        </x-card>

        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-primary"><x-icon name="save" />Save changes</button>
        </div>
    </form>
@endsection
