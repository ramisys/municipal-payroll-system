@extends('layouts.app')

@section('title', 'Audit log')
@section('heading', 'Audit log')

@section('content')
    <x-page-header
        title="Audit log"
        subtitle="Every recorded change, in order. Each entry is hashed against the one before it." />

    @if ($verification !== null)
        {{-- A broken chain is the single most serious thing this screen can
             report, so it is stated as a failure, not a neutral status line. --}}
        @if ($verification['intact'])
            <x-alert type="ok" title="Chain verified intact.">
                Checked {{ $verification['checked'] }} {{ Str::plural('entry', $verification['checked']) }};
                every entry hash matches the entry before it.
            </x-alert>
        @else
            <x-alert type="bad" title="Chain broken.">
                The hash chain fails at audit_log_id
                <strong class="font-mono">{{ $verification['broken_at'] }}</strong>
                (checked {{ $verification['checked'] }} {{ Str::plural('entry', $verification['checked']) }}).
                Entries at or after that point can no longer be shown to be unaltered.
            </x-alert>
        @endif
    @endif

    <x-card title="Filter">
        <form method="GET" action="{{ route('audit-log.index') }}"
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

            <x-field label="User" name="user_id">
                <select id="user_id" name="user_id" class="select">
                    <option value="">Any user</option>
                    @foreach ($users as $option)
                        <option value="{{ $option->user_id }}" @selected(request('user_id') == $option->user_id)>{{ $option->username }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Record type" name="entity_name">
                <input type="text" id="entity_name" name="entity_name" class="input"
                       value="{{ request('entity_name') }}" placeholder="e.g. USER">
            </x-field>

            <x-field label="Record ID" name="entity_id">
                <input type="number" id="entity_id" name="entity_id" class="input tabular"
                       value="{{ request('entity_id') }}">
            </x-field>

            <x-field label="Action" name="action">
                <select id="action" name="action" class="select">
                    <option value="">Any action</option>
                    @foreach ($actions as $option)
                        <option value="{{ $option }}" @selected(request('action') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="From" name="date_from">
                <input type="date" id="date_from" name="date_from" class="input" value="{{ request('date_from') }}">
            </x-field>

            <x-field label="To" name="date_to">
                <input type="date" id="date_to" name="date_to" class="input" value="{{ request('date_to') }}">
            </x-field>

            <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-secondary">Filter</button>
                <button type="submit" name="verify" value="1" class="btn btn-primary">Filter and verify chain</button>
                @if (request()->hasAny(['user_id', 'entity_name', 'entity_id', 'action', 'date_from', 'date_to']))
                    <a href="{{ route('audit-log.index') }}" class="btn btn-ghost">Clear</a>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Occurred at</th>
                <th>User</th>
                <th>Action</th>
                <th>Record</th>
                <th>Previous values</th>
                <th>New values</th>
                <th>Entry hash</th>
            </x-slot:head>

            @forelse ($logs as $log)
                <tr>
                    <td class="tabular whitespace-nowrap">{{ $log->occurred_at->format('Y-m-d H:i:s') }}</td>
                    <td class="font-medium">{{ $log->user->username }}</td>
                    <td><x-status-badge :value="$log->action" /></td>
                    <td class="whitespace-nowrap">
                        {{ $log->entity_name }}@if ($log->entity_id)<span class="text-ink-muted"> #{{ $log->entity_id }}</span>@endif
                    </td>
                    <td class="max-w-xs"><span class="code-cell">{{ $log->previous_values }}</span></td>
                    <td class="max-w-xs"><span class="code-cell">{{ $log->new_values }}</span></td>
                    <td>
                        <span class="code-cell" title="{{ $log->entry_hash }}">{{ substr($log->entry_hash, 0, 12) }}&hellip;</span>
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="7" message="No audit entries match this filter." />
            @endforelse
        </x-table>
    </x-card>

    @if ($logs->hasPages())
        <div>{{ $logs->withQueryString()->links() }}</div>
    @endif
@endsection
