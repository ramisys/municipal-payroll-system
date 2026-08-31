@extends('layouts.app')

@section('title', 'Compensation')
@section('heading', 'Compensation — ' . $employee->fullName())

@section('content')
    <x-page-header
        title="Compensation"
        subtitle="{{ $employee->fullName() }} ({{ $employee->employee_no }})"
        :back="route('employees.index')" back-label="Employees">
        <x-slot:actions>
            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-secondary">Edit employee</a>
        </x-slot:actions>
    </x-page-header>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Current profile                                                     --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Current profile">
        @if ($currentProfile)
            <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <x-kv label="Pay basis">{{ ucfirst(strtolower($currentProfile->pay_basis)) }}</x-kv>
                <x-kv label="Basic rate">
                    <span class="tabular">{{ number_format((float) $currentProfile->basic_rate, 2) }}</span>
                </x-kv>
                <x-kv label="Effective from">
                    <span class="tabular">{{ $currentProfile->effective_from->toDateString() }}</span>
                </x-kv>
                <x-kv label="SSS">
                    <x-status-badge :value="$currentProfile->sss_covered ? 'YES' : 'NO'"
                                    :label="$currentProfile->sss_covered ? 'Covered' : 'Not covered'" />
                </x-kv>
                <x-kv label="PhilHealth">
                    <x-status-badge :value="$currentProfile->philhealth_covered ? 'YES' : 'NO'"
                                    :label="$currentProfile->philhealth_covered ? 'Covered' : 'Not covered'" />
                </x-kv>
                <x-kv label="Pag-IBIG">
                    <x-status-badge :value="$currentProfile->pagibig_covered ? 'YES' : 'NO'"
                                    :label="$currentProfile->pagibig_covered ? 'Covered' : 'Not covered'" />
                </x-kv>
            </dl>
        @else
            <p class="note">No compensation profile on file yet.</p>
        @endif
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- New dated version                                                   --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Record a new dated profile version">
        <x-note class="mb-4">
            UC-11 E1 — this closes the current open row at the new effective date and opens a new one
            (BR-08); a past period keeps reading the rate that was in force.
        </x-note>

        <form method="POST" action="{{ route('employees.compensation.store', $employee) }}" class="space-y-4">
            @csrf

            <div class="form-grid">
                <x-field label="Pay basis" name="pay_basis" required>
                    <select id="pay_basis" name="pay_basis" class="select" required>
                        @foreach (['MONTHLY', 'DAILY', 'HOURLY'] as $basis)
                            <option value="{{ $basis }}" @selected(old('pay_basis') === $basis)>{{ ucfirst(strtolower($basis)) }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Basic rate" name="basic_rate" required>
                    <input type="text" id="basic_rate" name="basic_rate" class="input tabular"
                           value="{{ old('basic_rate') }}" required inputmode="decimal">
                </x-field>

                <x-field label="Effective from" name="effective_from" required>
                    <input type="date" id="effective_from" name="effective_from" class="input"
                           value="{{ old('effective_from') }}" required>
                </x-field>
            </div>

            <fieldset>
                <legend class="label">Statutory coverage</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach ([
                        'sss_covered' => 'SSS covered',
                        'philhealth_covered' => 'PhilHealth covered',
                        'pagibig_covered' => 'Pag-IBIG covered',
                    ] as $field => $label)
                        <label for="{{ $field }}" class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1"
                                   class="checkbox" @checked(old($field, true))>
                            <span class="text-sm text-ink">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button type="submit" class="btn btn-primary">Save profile version</button>
        </form>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- History                                                             --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Profile history" :flush="true">
        <x-table>
            <x-slot:head>
                <th>Pay basis</th>
                <th class="num">Basic rate</th>
                <th>SSS</th>
                <th>PhilHealth</th>
                <th>Pag-IBIG</th>
                <th>Effective from</th>
                <th>Effective to</th>
            </x-slot:head>

            @forelse ($profiles as $profile)
                <tr>
                    <td class="font-medium">{{ ucfirst(strtolower($profile->pay_basis)) }}</td>
                    <td class="num font-medium">{{ number_format((float) $profile->basic_rate, 2) }}</td>
                    <td>{{ $profile->sss_covered ? 'Yes' : 'No' }}</td>
                    <td>{{ $profile->philhealth_covered ? 'Yes' : 'No' }}</td>
                    <td>{{ $profile->pagibig_covered ? 'Yes' : 'No' }}</td>
                    <td class="tabular">{{ $profile->effective_from->toDateString() }}</td>
                    <td>
                        @if ($profile->effective_to)
                            <span class="tabular">{{ $profile->effective_to->toDateString() }}</span>
                        @else
                            <x-status-badge value="CURRENT" />
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="7" message="No profile versions recorded yet." />
            @endforelse
        </x-table>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Recurring earnings                                                  --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Recurring earnings">
        <form method="POST" action="{{ route('employees.compensation.recurring-earnings.store', $employee) }}"
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            @csrf

            <x-field label="Earning type" name="earning_type_id">
                <select id="earning_type_id" name="earning_type_id" class="select" required>
                    @foreach ($earningTypes as $type)
                        <option value="{{ $type->earning_type_id }}">{{ $type->earning_name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Amount" name="amount">
                <input type="text" id="earning_amount" name="amount" class="input tabular" inputmode="decimal">
            </x-field>

            <x-field label="Effective from" name="effective_from">
                <input type="date" id="earning_effective_from" name="effective_from" class="input">
            </x-field>

            <button type="submit" class="btn btn-secondary">Add earning</button>
        </form>

        <div class="mt-4 -mx-4 -mb-4 border-t border-line">
            <x-table>
                <x-slot:head>
                    <th>Type</th>
                    <th class="num">Amount</th>
                    <th>Effective from</th>
                    <th>Effective to</th>
                    <th class="actions">End this earning</th>
                </x-slot:head>

                @forelse ($earnings as $earning)
                    <tr>
                        <td class="font-medium">{{ $earning->earningType->earning_name }}</td>
                        <td class="num">{{ number_format((float) $earning->amount, 2) }}</td>
                        <td class="tabular">{{ $earning->effective_from->toDateString() }}</td>
                        <td>
                            @if ($earning->effective_to)
                                <span class="tabular">{{ $earning->effective_to->toDateString() }}</span>
                            @else
                                <x-status-badge value="CURRENT" />
                            @endif
                        </td>
                        <td class="actions">
                            @if ($earning->effective_to === null)
                                <form method="POST" action="{{ route('employees.compensation.recurring-earnings.end', [$employee, $earning]) }}"
                                      class="flex items-center gap-2">
                                    @csrf
                                    <label for="end-earning-{{ $earning->getKey() }}" class="sr-only">Effective to date</label>
                                    <input type="date" id="end-earning-{{ $earning->getKey() }}" name="effective_to"
                                           class="input btn-sm w-auto" required>
                                    <button type="submit" class="btn btn-secondary btn-sm">End</button>
                                </form>
                            @else
                                <span class="note">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-empty-state :colspan="5" message="No recurring earnings on file." />
                @endforelse
            </x-table>
        </div>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Recurring deductions                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Recurring deductions">
        <form method="POST" action="{{ route('employees.compensation.recurring-deductions.store', $employee) }}"
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            @csrf

            <x-field label="Deduction type" name="deduction_type_id">
                <select id="deduction_type_id" name="deduction_type_id" class="select" required>
                    @foreach ($deductionTypes as $type)
                        <option value="{{ $type->deduction_type_id }}">{{ $type->deduction_name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Amount" name="amount">
                <input type="text" id="deduction_amount" name="amount" class="input tabular" inputmode="decimal">
            </x-field>

            <x-field label="Effective from" name="effective_from">
                <input type="date" id="deduction_effective_from" name="effective_from" class="input">
            </x-field>

            <button type="submit" class="btn btn-secondary">Add deduction</button>
        </form>

        <div class="mt-4 -mx-4 -mb-4 border-t border-line">
            <x-table>
                <x-slot:head>
                    <th>Type</th>
                    <th class="num">Amount</th>
                    <th>Effective from</th>
                    <th>Effective to</th>
                    <th class="actions">End this deduction</th>
                </x-slot:head>

                @forelse ($deductions as $deduction)
                    <tr>
                        <td class="font-medium">{{ $deduction->deductionType->deduction_name }}</td>
                        <td class="num">{{ number_format((float) $deduction->amount, 2) }}</td>
                        <td class="tabular">{{ $deduction->effective_from->toDateString() }}</td>
                        <td>
                            @if ($deduction->effective_to)
                                <span class="tabular">{{ $deduction->effective_to->toDateString() }}</span>
                            @else
                                <x-status-badge value="CURRENT" />
                            @endif
                        </td>
                        <td class="actions">
                            @if ($deduction->effective_to === null)
                                <form method="POST" action="{{ route('employees.compensation.recurring-deductions.end', [$employee, $deduction]) }}"
                                      class="flex items-center gap-2">
                                    @csrf
                                    <label for="end-deduction-{{ $deduction->getKey() }}" class="sr-only">Effective to date</label>
                                    <input type="date" id="end-deduction-{{ $deduction->getKey() }}" name="effective_to"
                                           class="input btn-sm w-auto" required>
                                    <button type="submit" class="btn btn-secondary btn-sm">End</button>
                                </form>
                            @else
                                <span class="note">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-empty-state :colspan="5" message="No recurring deductions on file." />
                @endforelse
            </x-table>
        </div>
    </x-card>
@endsection
