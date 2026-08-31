<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit user — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('users.index') }}">&larr; Users</a></p>

    <h1>Edit account</h1>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('users.update', $targetUser) }}">
        @csrf
        @method('PUT')

        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="{{ old('username', $targetUser->username) }}" required autofocus>

        <label for="role_id">Role</label>
        <select id="role_id" name="role_id" required>
            @foreach ($roles as $role)
                <option value="{{ $role->role_id }}" @selected(old('role_id', $targetUser->role_id) == $role->role_id)>{{ $role->role_name }}</option>
            @endforeach
        </select>

        <button type="submit">Save</button>
    </form>
</body>
</html>
