<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit holiday — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('organization.holidays.index') }}">&larr; Holiday calendar</a></p>

    <h1>Edit holiday</h1>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('organization.holidays.update', $holiday) }}">
        @csrf
        @method('PUT')
        @include('organization.holidays._fields')
        <button type="submit">Save</button>
    </form>
</body>
</html>
