<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Users — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('dashboard') }}">&larr; Dashboard</a></p>

    <h1>User accounts</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <p><a href="{{ route('users.create') }}">New account</a></p>

    <table border="1" cellpadding="4">
        <thead>
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Must change password</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $account)
                <tr>
                    <td>{{ $account->username }}</td>
                    <td>{{ $account->role->role_name }}</td>
                    <td>
                        {{ $account->is_active ? 'Active' : 'Deactivated' }}
                        @if ($account->is_locked)
                            &mdash; Locked
                        @endif
                    </td>
                    <td>{{ $account->must_change_password ? 'Yes' : 'No' }}</td>
                    <td>
                        <a href="{{ route('users.edit', $account) }}">Edit</a>

                        @if ($account->is_active)
                            <form method="POST" action="{{ route('users.deactivate', $account) }}" style="display:inline">
                                @csrf
                                <button type="submit">Deactivate</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('users.reactivate', $account) }}" style="display:inline">
                                @csrf
                                <button type="submit">Reactivate</button>
                            </form>
                        @endif

                        @if ($account->is_locked)
                            <form method="POST" action="{{ route('users.unlock', $account) }}" style="display:inline">
                                @csrf
                                <button type="submit">Unlock</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('users.reset-password', $account) }}" style="display:inline">
                            @csrf
                            <input type="password" name="password" placeholder="New password" required minlength="8">
                            <button type="submit">Reset password</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
