@extends('layouts.app')

@section('title', 'New column mapping version')
@section('heading', 'Publish a new column mapping version')

@section('content')
    @php
        $labels = [
            'BASIC' => 'Basic Pay', 'OT' => 'Overtime Pay', 'NIGHT_DIFF' => 'Night Shift Differential',
            'HOLIDAY_PAY' => 'Holiday Pay', 'ALLOWANCE' => 'Allowance', 'THIRTEENTH_MONTH' => '13th Month Pay',
            'SSS' => 'SSS', 'PHILHEALTH' => 'PhilHealth', 'PAGIBIG' => 'Pag-IBIG', 'WTAX' => 'Withholding Tax',
            'LOAN' => 'Loan Amortization', 'OTHER' => 'Other Deduction',
        ];
        $old = fn (string $path, ?string $default = null) => old($path, data_get($bindings, $path, $default));
    @endphp

    <x-page-header
        title="Publish a new column mapping version"
        :back="route('import-column-maps.index')" back-label="Register column mapping" />

    <div class="max-w-4xl space-y-4">
        <x-note>
            Enter the exact header text this register layout uses for each field. A renamed or reordered
            header is absorbed here — no source change (AD-17).
        </x-note>

        <form method="POST" action="{{ route('import-column-maps.store') }}" class="space-y-4">
            @csrf

            <x-card title="Version">
                <div class="form-grid">
                    <x-field label="Effective from" name="effective_from" required>
                        <input type="date" id="effective_from" name="effective_from" class="input"
                               value="{{ old('effective_from') }}" required autofocus>
                    </x-field>

                    <x-field label="Employee number column header" name="employee_no" required>
                        <input type="text" id="employee_no" name="employee_no" class="input font-mono"
                               value="{{ $old('employee_no', 'Employee No.') }}" required>
                    </x-field>
                </div>
            </x-card>

            <x-card title="Earnings">
                <div class="form-grid">
                    @foreach ($earningCodes as $code)
                        <x-field :label="$labels[$code] ?? $code" name="earnings[{{ $code }}]" required>
                            <input type="text" id="earnings_{{ $code }}" name="earnings[{{ $code }}]"
                                   class="input font-mono" value="{{ $old("earnings.{$code}") }}" required>
                        </x-field>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Deductions (employee share)">
                <div class="form-grid">
                    @foreach ($deductionCodes as $code)
                        <x-field label="{{ $labels[$code] ?? $code }} Contribution" name="deductions[{{ $code }}]" required>
                            <input type="text" id="deductions_{{ $code }}" name="deductions[{{ $code }}]"
                                   class="input font-mono" value="{{ $old("deductions.{$code}") }}" required>
                        </x-field>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Employer shares">
                <div class="form-grid">
                    @foreach ($employerShareCodes as $code)
                        <x-field label="{{ $labels[$code] ?? $code }} ER Share" name="employer_shares[{{ $code }}]" required>
                            <input type="text" id="employer_shares_{{ $code }}" name="employer_shares[{{ $code }}]"
                                   class="input font-mono" value="{{ $old("employer_shares.{$code}") }}" required>
                        </x-field>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Totals">
                <div class="form-grid">
                    <x-field label="Gross pay column header" name="gross_pay" required>
                        <input type="text" id="gross_pay" name="gross_pay" class="input font-mono"
                               value="{{ $old('gross_pay', 'Gross Pay') }}" required>
                    </x-field>

                    <x-field label="Total deductions column header" name="total_deductions" required>
                        <input type="text" id="total_deductions" name="total_deductions" class="input font-mono"
                               value="{{ $old('total_deductions', 'Total Deductions') }}" required>
                    </x-field>

                    <x-field label="Net pay column header" name="net_pay" required>
                        <input type="text" id="net_pay" name="net_pay" class="input font-mono"
                               value="{{ $old('net_pay', 'Net Pay') }}" required>
                    </x-field>
                </div>
            </x-card>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary">Publish version</button>
                <a href="{{ route('import-column-maps.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
