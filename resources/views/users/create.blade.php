@extends('layouts.app')

@section('title', 'New user')
@section('heading', 'New account')

@section('content')
    <x-page-header title="New account" :back="route('users.index')" back-label="User accounts" />

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                @csrf

                <x-field label="Username" name="username" required>
                    <input type="text" id="username" name="username" class="input"
                           value="{{ old('username') }}" required autofocus
                           autocapitalize="none" spellcheck="false">
                </x-field>

                <x-field label="Role" name="role_id" required
                         hint="The role decides which modules this account can reach.">
                    <select id="role_id" name="role_id" class="select" required>
                        <option value="">Select a role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->role_id }}" @selected(old('role_id') == $role->role_id)>{{ $role->role_name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Initial password" name="password" required
                         hint="At least 8 characters. The account must change this at first sign-in (AC-0.2.4).">
                    <input type="password" id="password" name="password" class="input"
                           required minlength="8" autocomplete="new-password">
                </x-field>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary"><x-icon name="plus" />Create account</button>
                    <a href="{{ route('users.index') }}" class="btn btn-secondary"><x-icon name="x" />Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
