@extends('layouts.app')

@section('title', 'Edit user')
@section('heading', 'Edit account — ' . $targetUser->username)

@section('content')
    <x-page-header title="Edit account" subtitle="{{ $targetUser->username }}"
                   :back="route('users.index')" back-label="User accounts" />

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('users.update', $targetUser) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-field label="Username" name="username" required>
                    <input type="text" id="username" name="username" class="input"
                           value="{{ old('username', $targetUser->username) }}" required autofocus
                           autocapitalize="none" spellcheck="false">
                </x-field>

                <x-field label="Role" name="role_id" required>
                    <select id="role_id" name="role_id" class="select" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->role_id }}" @selected(old('role_id', $targetUser->role_id) == $role->role_id)>{{ $role->role_name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary"><x-icon name="save" />Save changes</button>
                    <a href="{{ route('users.index') }}" class="btn btn-secondary"><x-icon name="x" />Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
