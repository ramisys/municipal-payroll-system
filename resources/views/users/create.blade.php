<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New user — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('users.index') }}">&larr; Users</a></p>

    <h1>New account</h1>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('users.store') }}">
        @csrf

        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="{{ old('username') }}" required autofocus>

        <label for="role_id">Role</label>
        <select id="role_id" name="role_id" required>
            <option value="">Select a role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->role_id }}" @selected(old('role_id') == $role->role_id)>{{ $role->role_name }}</option>
            @endforeach
        </select>

        <label for="password">Initial password</label>
        <input type="password" id="password" name="password" required minlength="8">

        <p><em>The account must change this password at first sign-in (AC-0.2.4).</em></p>

        <button type="submit">Create account</button>
    </form>
</body>
</html>
