@extends('layouts.guest')

@section('title', 'Sign in')
@section('heading', 'Sign in')
@section('subheading', 'Use the account issued to you by the system administrator.')

@section('content')
    @if ($errors->any())
        {{-- UC-01 E1 — the message stays deliberately non-specific about which
             half was wrong, so it cannot be used to enumerate usernames. --}}
        <x-alert type="bad" class="mb-4">{{ $errors->first() }}</x-alert>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-field label="Username" name="username" required>
            <input type="text" id="username" name="username" value="{{ old('username') }}"
                   class="input" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
        </x-field>

        <x-field label="Password" name="password" required>
            <input type="password" id="password" name="password"
                   class="input" required autocomplete="current-password">
        </x-field>

        <button type="submit" class="btn btn-primary w-full"><x-icon name="log-in" />Sign in</button>
    </form>
@endsection
