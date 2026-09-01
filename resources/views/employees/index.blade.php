@extends('layouts.app')

@section('title', 'Employees')
@section('heading', 'Employees')

@section('content')
    <x-page-header title="Employees" subtitle="The employee master file. Records are deactivated, never deleted.">
        <x-slot:actions>
            <a href="{{ route('employees.create') }}" class="btn btn-primary">
                <x-icon name="plus" :stroke-width="2" />
                Register new employee
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="GET" action="{{ route('employees.index') }}"
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">

            <x-field label="Search" name="q" hint="Name or employee no.">
                <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="input"
                       placeholder="e.g. Dela Cruz or 2024-001">
            </x-field>

            <x-field label="Department" name="department_id">
                <select id="department_id" name="department_id" class="select">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->department_id }}" @selected(($filters['department_id'] ?? null) == $department->department_id)>{{ $department->department_name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Status" name="status">
                <select id="status" name="status" class="select">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Deactivated</option>
                </select>
            </x-field>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-secondary"><x-icon name="filter" />Filter</button>
                @if (array_filter($filters ?? []))
                    <a href="{{ route('employees.index') }}" class="btn btn-ghost"><x-icon name="x" />Clear</a>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Employee no.</th>
                <th>Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Employment status</th>
                <th>Record</th>
                <th class="actions">Actions</th>
            </x-slot:head>

            @forelse ($employees as $employee)
                <tr>
                    <td class="font-medium tabular">{{ $employee->employee_no }}</td>
                    <td class="font-medium">{{ $employee->fullName() }}</td>
                    <td>{{ $employee->currentEmploymentDetail?->department?->department_name ?? '—' }}</td>
                    <td>{{ $employee->currentEmploymentDetail?->position?->position_title ?? '—' }}</td>
                    <td>{{ $employee->currentEmploymentDetail?->employmentStatus?->status_name ?? '—' }}</td>
                    <td><x-status-badge :value="$employee->is_active ? 'ACTIVE' : 'DEACTIVATED'" /></td>
                    <td class="actions">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" />Edit</a>
                            <a href="{{ route('employees.compensation.index', $employee) }}" class="btn btn-ghost btn-sm"><x-icon name="wallet" />Compensation</a>
                            @if ($employee->is_active)
                                <a href="{{ route('employees.deactivate-form', $employee) }}" class="btn btn-ghost btn-sm text-bad-fg hover:bg-bad-bg"><x-icon name="ban" />Deactivate</a>
                            @else
                                <a href="{{ route('employees.reactivate-form', $employee) }}" class="btn btn-ghost btn-sm text-ok-fg hover:bg-ok-bg"><x-icon name="rotate-ccw" />Reactivate</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                @if (array_filter($filters ?? []))
                    <x-empty-state :colspan="7" message="No employees match this filter.">
                        <x-slot:action>
                            <a href="{{ route('employees.index') }}" class="link">Clear the filter</a>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-empty-state :colspan="7" message="No employees on file yet.">
                        <x-slot:action>
                            <a href="{{ route('employees.create') }}" class="link">Register the first employee</a>
                        </x-slot:action>
                    </x-empty-state>
                @endif
            @endforelse
        </x-table>
    </x-card>
@endsection
