@extends('layouts.guest')

@section('title', 'Change password')
@section('heading', 'Change your password')
@section('subheading', 'Your account still holds its initial password. You must set a new one before continuing.')

@section('content')
    @if ($errors->any())
        <x-alert type="bad" class="mb-4">{{ $errors->first() }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf

        <x-field label="Current password" name="current_password" required>
            <input type="password" id="current_password" name="current_password"
                   class="input" required autocomplete="current-password" autofocus>
        </x-field>

        <x-field label="New password" name="password" required hint="At least 8 characters.">
            <input type="password" id="password" name="password"
                   class="input" required minlength="8" autocomplete="new-password">
        </x-field>

        <x-field label="Confirm new password" name="password_confirmation" required>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="input" required minlength="8" autocomplete="new-password">
        </x-field>

        <button type="submit" class="btn btn-primary w-full">Change password</button>
    </form>
@endsection
