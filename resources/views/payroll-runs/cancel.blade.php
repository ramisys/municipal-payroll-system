<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Cancel run #{{ $run->payroll_run_id }} — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('payroll-runs.show', $run) }}">&larr; Run #{{ $run->payroll_run_id }}</a></p>

    <h1>Cancel run #{{ $run->payroll_run_id }}</h1>

    <p><small>UC-17 A2, NFR-6.3 — this is irreversible. The run and its history remain readable but cannot be reopened, imported into, or submitted.</small></p>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('payroll-runs.cancel', $run) }}">
        @csrf

        <label for="reason">Reason</label>
        <input type="text" id="reason" name="reason" required maxlength="255" value="{{ old('reason') }}">

        <button type="submit">Confirm cancellation</button>
    </form>
</body>
</html>
