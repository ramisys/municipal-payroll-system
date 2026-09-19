@extends('layouts.app')

@section('title', 'Exception report · Run #' . $run->payroll_run_id)
@section('heading', 'Exception report')

@section('content')
    <x-page-header
        title="Exception report"
        subtitle="Run #{{ $run->payroll_run_id }} · {{ $run->run_type }} · {{ $run->period->payroll_year }}-{{ $run->period->period_no }}"
        :back="route('payroll-runs.show', $run)" back-label="Back to run">
        <x-slot:actions>
            <x-status-badge :value="$run->run_status" class="text-sm px-2.5 py-1" />
        </x-slot:actions>
    </x-page-header>

    <x-card title="Summary">
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <x-kv label="Findings">{{ $exceptions->count() }}</x-kv>
            <x-kv label="Blocking open">
                <span class="{{ $blockingOpen > 0 ? 'text-bad-fg font-semibold' : '' }}">{{ $blockingOpen }}</span>
            </x-kv>
            <x-kv label="Warnings open">{{ $warningOpen }}</x-kv>
            <x-kv label="Submittable">
                @if ($blockingOpen > 0)
                    No — unresolved blocking exceptions
                @else
                    Blocking clear
                @endif
            </x-kv>
        </dl>

        <x-note class="mt-4">
            Blocking exceptions arising from the register itself (EX-03, EX-04, and reconciliation
            refusals EX-11–EX-14) are resolvable only by a corrected import — the system does not
            edit stored figures in place (AC-4.1.5). Warnings must be acknowledged individually
            before the run advances (FR-4.1).
        </x-note>

        @if ($canManageImport && in_array($run->run_status, ['DRAFT', 'RETURNED'], true))
            <div class="mt-4">
                <a href="{{ route('payroll-imports.create', $run) }}" class="btn btn-primary">
                    <x-icon name="upload" />
                    Import corrected register
                </a>
            </div>
        @endif
    </x-card>

    <x-card title="Findings" subtitle="Blocking first, then warnings" :flush="true">
        <x-table>
            <x-slot:head>
                <th>Severity</th>
                <th>Rule</th>
                <th>Employee</th>
                <th>Triggering values</th>
                <th>Resolution</th>
                <th>Status</th>
            </x-slot:head>

            @forelse ($exceptions as $exception)
                @php
                    $employee = $exception->payrollLine?->employee;
                @endphp
                <tr>
                    <td>
                        <x-status-badge
                            :value="$exception->severity"
                            :label="$exception->severity"
                        />
                    </td>
                    <td class="font-medium tabular">{{ $exception->rule_code }}</td>
                    <td>
                        @if ($employee)
                            <span class="tabular">{{ $employee->employee_no }}</span>
                            <span class="note"> · {{ $employee->fullName() }}</span>
                        @else
                            <span class="note">Run-level</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $exception->triggering_values }}</td>
                    <td class="text-sm">{{ $resolutionPath($exception->rule_code) }}</td>
                    <td>
                        @if ($exception->is_resolved)
                            <x-status-badge value="YES" label="Acknowledged" />
                            @if ($exception->acknowledgment_reason)
                                <p class="note mt-1">{{ $exception->acknowledgment_reason }}</p>
                            @endif
                        @elseif ($exception->severity === 'WARNING' && $canAcknowledge)
                            <form method="post" action="{{ route('exception-report.acknowledge', [$run, $exception]) }}" class="space-y-2">
                                @csrf
                                <x-field label="Reason" name="acknowledgment_reason" :required="true">
                                    <input
                                        type="text"
                                        id="acknowledgment_reason_{{ $exception->exception_instance_id }}"
                                        name="acknowledgment_reason"
                                        maxlength="255"
                                        required
                                        class="input"
                                        placeholder="Why this warning is accepted"
                                    >
                                </x-field>
                                <button type="submit" class="btn btn-secondary btn-sm">Acknowledge</button>
                            </form>
                        @elseif ($exception->severity === 'WARNING')
                            <span class="note">Open — acknowledgment requires Payroll Officer or Approver</span>
                        @else
                            <x-status-badge value="NO" label="Unresolved" />
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="6" message="No exceptions for this run. Import a register to evaluate FR-4.1 rules." />
            @endforelse
        </x-table>
    </x-card>
@endsection
