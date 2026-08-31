<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New column mapping version — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('import-column-maps.index') }}">&larr; Register column mapping</a></p>

    <h1>Publish a new column mapping version</h1>
    <p><small>Enter the exact header text this register layout uses for each field. A renamed or reordered header is absorbed here — no source change (AD-17).</small></p>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @php
        $labels = [
            'BASIC' => 'Basic Pay', 'OT' => 'Overtime Pay', 'NIGHT_DIFF' => 'Night Shift Differential',
            'HOLIDAY_PAY' => 'Holiday Pay', 'ALLOWANCE' => 'Allowance', 'THIRTEENTH_MONTH' => '13th Month Pay',
            'SSS' => 'SSS', 'PHILHEALTH' => 'PhilHealth', 'PAGIBIG' => 'Pag-IBIG', 'WTAX' => 'Withholding Tax',
            'LOAN' => 'Loan Amortization', 'OTHER' => 'Other Deduction',
        ];
        $old = fn (string $path, ?string $default = null) => old($path, data_get($bindings, $path, $default));
    @endphp

    <form method="POST" action="{{ route('import-column-maps.store') }}">
        @csrf

        <p>
            <label for="effective_from">Effective from</label><br>
            <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from') }}" required>
        </p>

        <p>
            <label for="employee_no">Employee number column header</label><br>
            <input type="text" id="employee_no" name="employee_no" value="{{ $old('employee_no', 'Employee No.') }}" required>
        </p>

        <h2>Earnings</h2>
        @foreach ($earningCodes as $code)
            <p>
                <label for="earnings_{{ $code }}">{{ $labels[$code] ?? $code }}</label><br>
                <input type="text" id="earnings_{{ $code }}" name="earnings[{{ $code }}]" value="{{ $old("earnings.{$code}") }}" required>
            </p>
        @endforeach

        <h2>Deductions (employee share)</h2>
        @foreach ($deductionCodes as $code)
            <p>
                <label for="deductions_{{ $code }}">{{ $labels[$code] ?? $code }} Contribution</label><br>
                <input type="text" id="deductions_{{ $code }}" name="deductions[{{ $code }}]" value="{{ $old("deductions.{$code}") }}" required>
            </p>
        @endforeach

        <h2>Employer shares</h2>
        @foreach ($employerShareCodes as $code)
            <p>
                <label for="employer_shares_{{ $code }}">{{ $labels[$code] ?? $code }} ER Share</label><br>
                <input type="text" id="employer_shares_{{ $code }}" name="employer_shares[{{ $code }}]" value="{{ $old("employer_shares.{$code}") }}" required>
            </p>
        @endforeach

        <h2>Totals</h2>
        <p>
            <label for="gross_pay">Gross pay column header</label><br>
            <input type="text" id="gross_pay" name="gross_pay" value="{{ $old('gross_pay', 'Gross Pay') }}" required>
        </p>
        <p>
            <label for="total_deductions">Total deductions column header</label><br>
            <input type="text" id="total_deductions" name="total_deductions" value="{{ $old('total_deductions', 'Total Deductions') }}" required>
        </p>
        <p>
            <label for="net_pay">Net pay column header</label><br>
            <input type="text" id="net_pay" name="net_pay" value="{{ $old('net_pay', 'Net Pay') }}" required>
        </p>

        <button type="submit">Publish version</button>
    </form>
</body>
</html>
