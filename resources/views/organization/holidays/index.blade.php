@extends('layouts.app')

@section('title', 'Holiday calendar')
@section('heading', 'Holiday calendar')

@section('content')
    <x-page-header title="Holiday calendar">
        <x-slot:actions>
            <a href="{{ route('organization.holidays.create') }}" class="btn btn-primary">
                <x-icon name="plus" :stroke-width="2" />
                New holiday
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-org-tabs current="holidays" />

    <x-note>
        FR-0.3 behavior 3 — carried into the input worksheet (FR-2.11, AC-0.3.4) so the accounting
        office classifies each worked day against the same dates the system reports against.
    </x-note>

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Date</th>
                <th>Name</th>
                <th>Type</th>
                <th>Scope</th>
                <th class="actions"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @forelse ($holidays as $holiday)
                <tr>
                    <td class="tabular font-medium">{{ $holiday->holiday_date->toDateString() }}</td>
                    <td>{{ $holiday->holiday_name }}</td>
                    <td>{{ $holiday->holiday_type === 'REGULAR' ? 'Regular holiday' : 'Special non-working day' }}</td>
                    <td>{{ $holiday->is_local ? 'Local' : 'National' }}</td>
                    <td class="actions">
                        <a href="{{ route('organization.holidays.edit', $holiday) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" />Edit</a>
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="5" message="No holidays defined yet.">
                    <x-slot:action>
                        <a href="{{ route('organization.holidays.create') }}" class="link">Add the first holiday</a>
                    </x-slot:action>
                </x-empty-state>
            @endforelse
        </x-table>
    </x-card>
@endsection
