<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Change password — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Change your password</h1>

    <p>Your account still holds its initial password. You must set a new one before continuing.</p>

    @if ($errors->any())
        <p role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="password">New password</label>
        <input type="password" id="password" name="password" required minlength="8">

        <label for="password_confirmation">Confirm new password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">

        <button type="submit">Change password</button>
    </form>
</body>
</html>
