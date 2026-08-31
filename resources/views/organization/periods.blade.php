@extends('layouts.app')

@section('title', 'Pay periods')
@section('heading', 'Pay periods')

@section('content')
    <x-page-header title="Pay periods" subtitle="The payroll calendar every run is created against." />

    <x-org-tabs current="periods" />

    <x-card title="Generate a payroll year">
        <x-note class="mb-4">
            BR-34 — the generated year is validated to have no overlap and no gap before it is saved.
        </x-note>

        <form method="POST" action="{{ route('organization.periods.store') }}"
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            @csrf

            <x-field label="Payroll year" name="payroll_year" required>
                <input type="number" id="payroll_year" name="payroll_year" class="input tabular"
                       value="{{ old('payroll_year') }}" required min="2000" max="2100">
            </x-field>

            <x-field label="Pay frequency" name="pay_frequency" required>
                <select id="pay_frequency" name="pay_frequency" class="select" required>
                    <option value="MONTHLY" @selected(old('pay_frequency') === 'MONTHLY')>Monthly</option>
                    <option value="SEMI_MONTHLY" @selected(old('pay_frequency') === 'SEMI_MONTHLY')>Semi-monthly</option>
                </select>
            </x-field>

            <x-field label="Pay date offset" name="pay_date_offset_days" required hint="Days after cut-off end.">
                <input type="number" id="pay_date_offset_days" name="pay_date_offset_days" class="input tabular"
                       value="{{ old('pay_date_offset_days', 5) }}" required min="0" max="30">
            </x-field>

            <button type="submit" class="btn btn-primary">Generate year</button>
        </form>
    </x-card>

    @forelse ($periodsByYear as $year => $periods)
        <x-card title="{{ $year }}" subtitle="{{ $periods->first()->pay_frequency }}" :flush="true">
            <x-table>
                <x-slot:head>
                    <th class="num">Period #</th>
                    <th>Cut-off start</th>
                    <th>Cut-off end</th>
                    <th>Pay date</th>
                    <th class="actions"><span class="sr-only">Actions</span></th>
                </x-slot:head>

                @foreach ($periods as $period)
                    <tr>
                        <td class="num font-medium">{{ $period->period_no }}</td>
                        <td class="tabular">{{ $period->cutoff_start->toDateString() }}</td>
                        <td class="tabular">{{ $period->cutoff_end->toDateString() }}</td>
                        <td class="tabular font-medium">{{ $period->pay_date->toDateString() }}</td>
                        <td class="actions">
                            <a href="{{ route('organization.periods.edit', $period) }}" class="btn btn-ghost btn-sm">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @empty
        <x-card>
            <p class="note">No payroll years generated yet. Generate one above before creating a payroll run.</p>
        </x-card>
    @endforelse
@endsection
