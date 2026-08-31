<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register column mapping — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('organization.edit') }}">&larr; Organization profile</a></p>

    <h1>Register column mapping (CANONICAL)</h1>
    <p><small>AD-17, BR-41 — binds the fields RegisterImportService reads to the accounting office's register header strings. The active version with the highest number is the one applied at import.</small></p>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <p><a href="{{ route('import-column-maps.create') }}">Publish a new version</a></p>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Version</th>
                <th>Effective from</th>
                <th>Effective to</th>
                <th>Status</th>
                <th>Bindings</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($versions as $version)
                <tr>
                    <td>{{ $version->version_no }}</td>
                    <td>{{ $version->effective_from->toDateString() }}</td>
                    <td>{{ $version->effective_to?->toDateString() ?? '—' }}</td>
                    <td>{{ $version->is_active ? 'Active' : 'Retired' }}</td>
                    <td><pre>{{ json_encode($version->column_bindings, JSON_PRETTY_PRINT) }}</pre></td>
                    <td>
                        @if ($version->is_active)
                            <form method="POST" action="{{ route('import-column-maps.deactivate', $version) }}" style="display:inline">
                                @csrf
                                <button type="submit">Retire</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('import-column-maps.reactivate', $version) }}" style="display:inline">
                                @csrf
                                <button type="submit">Reactivate</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No versions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
