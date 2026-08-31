<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sign in — {{ config('app.name') }}</title>
</head>
<body>
    <h1>Sign in</h1>

    @if ($errors->any())
        <p role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="{{ old('username') }}" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Sign in</button>
    </form>
</body>
</html>
