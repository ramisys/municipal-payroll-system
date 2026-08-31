<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Compensation — {{ $employee->fullName() }} — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('employees.index') }}">&larr; Employees</a> | <a href="{{ route('employees.edit', $employee) }}">Edit employee</a></p>

    <h1>Compensation — {{ $employee->fullName() }} ({{ $employee->employee_no }})</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <h2>Current profile</h2>
    @if ($currentProfile)
        <p>
            {{ $currentProfile->pay_basis }} — {{ number_format((float) $currentProfile->basic_rate, 2) }},
            effective {{ $currentProfile->effective_from->toDateString() }}.
            SSS: {{ $currentProfile->sss_covered ? 'covered' : 'not covered' }},
            PhilHealth: {{ $currentProfile->philhealth_covered ? 'covered' : 'not covered' }},
            Pag-IBIG: {{ $currentProfile->pagibig_covered ? 'covered' : 'not covered' }}.
        </p>
    @else
        <p><em>No compensation profile on file yet.</em></p>
    @endif

    <h3>Record a new dated profile version</h3>
    <p><small>UC-11 E1 — this closes the current open row at the new effective date and opens a new one (BR-08); a past period keeps reading the rate that was in force.</small></p>

    <form method="POST" action="{{ route('employees.compensation.store', $employee) }}">
        @csrf

        <p><label for="pay_basis">Pay basis</label><br>
        <select id="pay_basis" name="pay_basis" required>
            @foreach (['MONTHLY', 'DAILY', 'HOURLY'] as $basis)
                <option value="{{ $basis }}" @selected(old('pay_basis') === $basis)>{{ ucfirst(strtolower($basis)) }}</option>
            @endforeach
        </select></p>

        <p><label for="basic_rate">Basic rate</label><br>
        <input type="text" id="basic_rate" name="basic_rate" value="{{ old('basic_rate') }}" required></p>

        <p><label for="sss_covered"><input type="checkbox" id="sss_covered" name="sss_covered" value="1" @checked(old('sss_covered', true))> SSS covered</label></p>
        <p><label for="philhealth_covered"><input type="checkbox" id="philhealth_covered" name="philhealth_covered" value="1" @checked(old('philhealth_covered', true))> PhilHealth covered</label></p>
        <p><label for="pagibig_covered"><input type="checkbox" id="pagibig_covered" name="pagibig_covered" value="1" @checked(old('pagibig_covered', true))> Pag-IBIG covered</label></p>

        <p><label for="effective_from">Effective from</label><br>
        <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from') }}" required></p>

        <button type="submit">Save</button>
    </form>

    <h3>History</h3>
    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Pay basis</th>
                <th>Basic rate</th>
                <th>SSS</th>
                <th>PhilHealth</th>
                <th>Pag-IBIG</th>
                <th>Effective from</th>
                <th>Effective to</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($profiles as $profile)
                <tr>
                    <td>{{ $profile->pay_basis }}</td>
                    <td>{{ number_format((float) $profile->basic_rate, 2) }}</td>
                    <td>{{ $profile->sss_covered ? 'Yes' : 'No' }}</td>
                    <td>{{ $profile->philhealth_covered ? 'Yes' : 'No' }}</td>
                    <td>{{ $profile->pagibig_covered ? 'Yes' : 'No' }}</td>
                    <td>{{ $profile->effective_from->toDateString() }}</td>
                    <td>{{ $profile->effective_to?->toDateString() ?? '(current)' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Recurring earnings</h2>

    <form method="POST" action="{{ route('employees.compensation.recurring-earnings.store', $employee) }}">
        @csrf
        <label for="earning_type_id">Earning type</label>
        <select id="earning_type_id" name="earning_type_id" required>
            @foreach ($earningTypes as $type)
                <option value="{{ $type->earning_type_id }}">{{ $type->earning_name }}</option>
            @endforeach
        </select>

        <label for="earning_amount">Amount</label>
        <input type="text" id="earning_amount" name="amount">

        <label for="earning_effective_from">Effective from</label>
        <input type="date" id="earning_effective_from" name="effective_from">

        <button type="submit">Add</button>
    </form>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Type</th>
                <th>Amount</th>
                <th>Effective from</th>
                <th>Effective to</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($earnings as $earning)
                <tr>
                    <td>{{ $earning->earningType->earning_name }}</td>
                    <td>{{ number_format((float) $earning->amount, 2) }}</td>
                    <td>{{ $earning->effective_from->toDateString() }}</td>
                    <td>{{ $earning->effective_to?->toDateString() ?? '(current)' }}</td>
                    <td>
                        @if ($earning->effective_to === null)
                            <form method="POST" action="{{ route('employees.compensation.recurring-earnings.end', [$employee, $earning]) }}">
                                @csrf
                                <input type="date" name="effective_to" required>
                                <button type="submit">End</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Recurring deductions</h2>

    <form method="POST" action="{{ route('employees.compensation.recurring-deductions.store', $employee) }}">
        @csrf
        <label for="deduction_type_id">Deduction type</label>
        <select id="deduction_type_id" name="deduction_type_id" required>
            @foreach ($deductionTypes as $type)
                <option value="{{ $type->deduction_type_id }}">{{ $type->deduction_name }}</option>
            @endforeach
        </select>

        <label for="deduction_amount">Amount</label>
        <input type="text" id="deduction_amount" name="amount">

        <label for="deduction_effective_from">Effective from</label>
        <input type="date" id="deduction_effective_from" name="effective_from">

        <button type="submit">Add</button>
    </form>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Type</th>
                <th>Amount</th>
                <th>Effective from</th>
                <th>Effective to</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deductions as $deduction)
                <tr>
                    <td>{{ $deduction->deductionType->deduction_name }}</td>
                    <td>{{ number_format((float) $deduction->amount, 2) }}</td>
                    <td>{{ $deduction->effective_from->toDateString() }}</td>
                    <td>{{ $deduction->effective_to?->toDateString() ?? '(current)' }}</td>
                    <td>
                        @if ($deduction->effective_to === null)
                            <form method="POST" action="{{ route('employees.compensation.recurring-deductions.end', [$employee, $deduction]) }}">
                                @csrf
                                <input type="date" name="effective_to" required>
                                <button type="submit">End</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
