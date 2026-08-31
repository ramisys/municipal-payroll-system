<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $config['label'] }}s — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a> | <a href="{{ route('organization.edit') }}">Organization &amp; calendar</a></p>

    <h1>Reference data — {{ $config['label'] }}s</h1>

    <nav>
        <ul>
            @foreach (['departments' => 'Departments', 'positions' => 'Positions', 'employment-statuses' => 'Employment statuses', 'earning-types' => 'Earning types', 'deduction-types' => 'Deduction types', 'leave-types' => 'Leave types'] as $slug => $label)
                <li>
                    @if ($slug === $type)
                        <strong>{{ $label }}</strong>
                    @else
                        <a href="{{ route('reference-data.index', $slug) }}">{{ $label }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

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

    <p><a href="{{ route('reference-data.create', $type) }}">New {{ strtolower($config['label']) }}</a></p>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                @foreach ($config['columns'] as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    @foreach ($config['columns'] as $column)
                        <td>
                            @if (str_starts_with($column['type'], 'boolean'))
                                {{ $item->{$column['field']} ? 'Yes' : 'No' }}
                            @else
                                {{ $item->{$column['field']} }}
                            @endif
                        </td>
                    @endforeach
                    <td>{{ $item->is_active ? 'Active' : 'Deactivated' }}</td>
                    <td>
                        <a href="{{ route('reference-data.edit', [$type, $item->getKey()]) }}">Edit</a>

                        @if ($item->is_active)
                            <form method="POST" action="{{ route('reference-data.deactivate', [$type, $item->getKey()]) }}" style="display:inline">
                                @csrf
                                <button type="submit">Deactivate</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('reference-data.reactivate', [$type, $item->getKey()]) }}" style="display:inline">
                                @csrf
                                <button type="submit">Reactivate</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($config['columns']) + 2 }}">No entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
