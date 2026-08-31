<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pay periods — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('organization.edit') }}">&larr; Organization profile</a></p>

    <h1>Pay periods</h1>

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

    <h2>Generate a payroll year</h2>
    <p><small>BR-34 — the generated year is validated to have no overlap and no gap before it is saved.</small></p>

    <form method="POST" action="{{ route('organization.periods.store') }}">
        @csrf
        <p>
            <label for="payroll_year">Payroll year</label><br>
            <input type="number" id="payroll_year" name="payroll_year" value="{{ old('payroll_year') }}" required min="2000" max="2100">
        </p>
        <p>
            <label for="pay_frequency">Pay frequency</label><br>
            <select id="pay_frequency" name="pay_frequency" required>
                <option value="MONTHLY" @selected(old('pay_frequency') === 'MONTHLY')>Monthly</option>
                <option value="SEMI_MONTHLY" @selected(old('pay_frequency') === 'SEMI_MONTHLY')>Semi-monthly</option>
            </select>
        </p>
        <p>
            <label for="pay_date_offset_days">Pay date — days after cut-off end</label><br>
            <input type="number" id="pay_date_offset_days" name="pay_date_offset_days" value="{{ old('pay_date_offset_days', 5) }}" required min="0" max="30">
        </p>
        <button type="submit">Generate</button>
    </form>

    <h2>Existing years</h2>

    @forelse ($periodsByYear as $year => $periods)
        <h3>{{ $year }} ({{ $periods->first()->pay_frequency }})</h3>
        <table border="1" cellpadding="4">
            <thead>
                <tr>
                    <th>Period #</th>
                    <th>Cut-off start</th>
                    <th>Cut-off end</th>
                    <th>Pay date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as $period)
                    <tr>
                        <td>{{ $period->period_no }}</td>
                        <td>{{ $period->cutoff_start->toDateString() }}</td>
                        <td>{{ $period->cutoff_end->toDateString() }}</td>
                        <td>{{ $period->pay_date->toDateString() }}</td>
                        <td><a href="{{ route('organization.periods.edit', $period) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>No payroll years generated yet.</p>
    @endforelse
</body>
</html>
