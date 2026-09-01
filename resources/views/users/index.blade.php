@extends('layouts.app')

@section('title', 'User accounts')
@section('heading', 'User accounts')

@section('content')
    <x-page-header title="User accounts" subtitle="Accounts are deactivated, never deleted, so the audit trail stays attributable.">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-primary">
                <x-icon name="plus" :stroke-width="2" />
                New account
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Must change password</th>
                <th class="actions">Actions</th>
            </x-slot:head>

            @forelse ($users as $account)
                <tr>
                    <td class="font-medium">{{ $account->username }}</td>
                    <td>{{ $account->role->role_name }}</td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1">
                            <x-status-badge :value="$account->is_active ? 'ACTIVE' : 'DEACTIVATED'" />
                            @if ($account->is_locked)
                                <x-status-badge value="LOCKED" />
                            @endif
                        </div>
                    </td>
                    <td>
                        <x-status-badge :value="$account->must_change_password ? 'YES' : 'NO'"
                                        :label="$account->must_change_password ? 'Yes' : 'No'" />
                    </td>
                    <td class="actions">
                        <div class="flex flex-wrap items-center gap-1">
                            <a href="{{ route('users.edit', $account) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" />Edit</a>

                            @if ($account->is_active)
                                <form method="POST" action="{{ route('users.deactivate', $account) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm text-bad-fg hover:bg-bad-bg"><x-icon name="ban" />Deactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('users.reactivate', $account) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm text-ok-fg hover:bg-ok-bg"><x-icon name="rotate-ccw" />Reactivate</button>
                                </form>
                            @endif

                            @if ($account->is_locked)
                                <form method="POST" action="{{ route('users.unlock', $account) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="unlock" />Unlock</button>
                                </form>
                            @endif

                            {{-- Inline so an administrator resetting a forgotten password
                                 does not lose their place in the list. --}}
                            <form method="POST" action="{{ route('users.reset-password', $account) }}"
                                  class="flex items-center gap-1">
                                @csrf
                                <label for="reset-{{ $account->getKey() }}" class="sr-only">
                                    New password for {{ $account->username }}
                                </label>
                                <input type="password" id="reset-{{ $account->getKey() }}" name="password"
                                       class="input btn-sm w-36" placeholder="New password" required minlength="8">
                                <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="key-round" class="w-3.5 h-3.5" />Reset</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="5" message="No user accounts yet." />
            @endforelse
        </x-table>
    </x-card>
@endsection
