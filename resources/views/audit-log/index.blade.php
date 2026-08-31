<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit log — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>

    <h1>Audit log</h1>

    @if ($verification !== null)
        <p role="status">
            @if ($verification['intact'])
                Chain verified intact across {{ $verification['checked'] }} {{ Str::plural('entry', $verification['checked']) }}.
            @else
                Chain broken at audit_log_id {{ $verification['broken_at'] }} (checked {{ $verification['checked'] }} {{ Str::plural('entry', $verification['checked']) }}).
            @endif
        </p>
    @endif

    <form method="GET" action="{{ route('audit-log.index') }}">
        <label for="user_id">User</label>
        <select id="user_id" name="user_id">
            <option value="">Any</option>
            @foreach ($users as $option)
                <option value="{{ $option->user_id }}" @selected(request('user_id') == $option->user_id)>{{ $option->username }}</option>
            @endforeach
        </select>

        <label for="entity_name">Record type</label>
        <input type="text" id="entity_name" name="entity_name" value="{{ request('entity_name') }}" placeholder="e.g. USER">

        <label for="entity_id">Record ID</label>
        <input type="number" id="entity_id" name="entity_id" value="{{ request('entity_id') }}">

        <label for="action">Action</label>
        <select id="action" name="action">
            <option value="">Any</option>
            @foreach ($actions as $option)
                <option value="{{ $option }}" @selected(request('action') === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <label for="date_from">From</label>
        <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">

        <label for="date_to">To</label>
        <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">

        <button type="submit">Filter</button>
        <button type="submit" name="verify" value="1">Filter and verify chain</button>
    </form>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Occurred at</th>
                <th>User</th>
                <th>Action</th>
                <th>Record</th>
                <th>Previous values</th>
                <th>New values</th>
                <th>Entry hash</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->occurred_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user->username }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->entity_name }}@if ($log->entity_id) #{{ $log->entity_id }} @endif</td>
                    <td><code>{{ $log->previous_values }}</code></td>
                    <td><code>{{ $log->new_values }}</code></td>
                    <td><code>{{ substr($log->entry_hash, 0, 12) }}&hellip;</code></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No audit entries match this filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $logs->links() }}
</body>
</html>
