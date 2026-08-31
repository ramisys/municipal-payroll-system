<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Holiday calendar — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('organization.edit') }}">&larr; Organization profile</a></p>

    <h1>Holiday calendar</h1>
    <p><small>FR-0.3 behavior 3 — carried into the input worksheet (FR-2.11, AC-0.3.4) so the accounting office classifies each worked day against the same dates the system reports against.</small></p>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <p><a href="{{ route('organization.holidays.create') }}">New holiday</a></p>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Type</th>
                <th>Local</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($holidays as $holiday)
                <tr>
                    <td>{{ $holiday->holiday_date->toDateString() }}</td>
                    <td>{{ $holiday->holiday_name }}</td>
                    <td>{{ $holiday->holiday_type === 'REGULAR' ? 'Regular holiday' : 'Special non-working day' }}</td>
                    <td>{{ $holiday->is_local ? 'Yes' : 'No' }}</td>
                    <td><a href="{{ route('organization.holidays.edit', $holiday) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="5">No holidays defined yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
