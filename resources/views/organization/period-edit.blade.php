<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit period — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('organization.periods.index') }}">&larr; Pay periods</a></p>

    <h1>Edit period {{ $period->payroll_year }}-{{ $period->period_no }}</h1>
    <p><small>UC-03 A1 — the whole payroll year is re-validated against BR-34 before this save is accepted.</small></p>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('organization.periods.update', $period) }}">
        @csrf
        @method('PUT')

        <p>
            <label for="cutoff_start">Cut-off start</label><br>
            <input type="date" id="cutoff_start" name="cutoff_start" value="{{ old('cutoff_start', $period->cutoff_start->toDateString()) }}" required>
        </p>
        <p>
            <label for="cutoff_end">Cut-off end</label><br>
            <input type="date" id="cutoff_end" name="cutoff_end" value="{{ old('cutoff_end', $period->cutoff_end->toDateString()) }}" required>
        </p>
        <p>
            <label for="pay_date">Pay date</label><br>
            <input type="date" id="pay_date" name="pay_date" value="{{ old('pay_date', $period->pay_date->toDateString()) }}" required>
        </p>

        <button type="submit">Save</button>
    </form>
</body>
</html>
