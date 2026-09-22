@extends('layouts.app')

@section('title', 'Run #' . $run->payroll_run_id)
@section('heading', 'Run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Run #{{ $run->payroll_run_id }}"
        subtitle="{{ $run->run_type }} · {{ \App\Services\PayrollRunService::populationScopeLabel($run->population_scope) }}"
        :back="route('payroll-runs.index')" back-label="Payroll runs">
        <x-slot:actions>
            <x-status-badge :value="$run->run_status" class="text-sm px-2.5 py-1" />
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">
            {{ session('status') }}
        </x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="danger" class="mb-6">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    {{-- Reversal Banner if Run was previously reversed --}}
    @if ($run->reversalRecord !== null)
        <div class="mb-6 p-4 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-700/60">
            <div class="flex items-start gap-3">
                <x-icon name="history" class="w-5 h-5 text-amber-600 mt-0.5" />
                <div class="space-y-1 text-sm text-amber-900 dark:text-amber-200">
                    <p class="font-bold">This run has a permanent Reversal Record (#{{ $run->reversalRecord->reversal_record_id }})</p>
                    <p class="text-xs">
                        Reversed by <span class="font-semibold">{{ $run->reversalRecord->reversedBy?->full_name ?? 'Approver' }}</span>
                        on {{ $run->reversalRecord->reversed_at?->toDateTimeString() }}.
                        Original figures: Gross <strong>{{ $run->reversalRecord->original_total_gross }}</strong>,
                        Net <strong>{{ $run->reversalRecord->original_total_net }}</strong>
                        ({{ $run->reversalRecord->original_employee_count }} employees).
                    </p>
                    <p class="text-xs italic bg-white/60 dark:bg-stone-900/60 p-2 rounded border border-amber-200 dark:border-amber-800">
                        Reason: {{ $run->reversalRecord->reason }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Finalized & Locked Notice --}}
    @if ($run->run_status === 'FINALIZED')
        <div class="mb-6 p-4 rounded-xl border border-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 dark:border-emerald-700/60 flex items-center justify-between">
            <div class="flex items-center gap-3 text-emerald-900 dark:text-emerald-200">
                <x-icon name="lock" class="w-5 h-5 text-emerald-600" />
                <div class="text-sm">
                    <span class="font-bold">Finalized & Locked (FR-4.5)</span> — Stored payroll records and child lines are strictly immutable.
                    Finalized at {{ $run->finalized_at?->toDateTimeString() }}.
                </div>
            </div>
            @if ($run->integrityAnchor !== null)
                <div class="text-xs font-mono bg-white dark:bg-stone-900 px-3 py-1.5 rounded-lg border border-emerald-200 dark:border-emerald-800 text-stone-600 dark:text-stone-300">
                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">Anchor:</span> {{ substr($run->integrityAnchor->payload_hash, 0, 16) }}...
                    <x-status-badge :value="$run->integrityAnchor->anchor_status" class="ml-1" />
                </div>
            @endif
        </div>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Summary                                                             --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Summary" class="mb-6">
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <x-kv label="Pay period">
                <span class="tabular">{{ $run->period->payroll_year }}-{{ $run->period->period_no }}</span>
            </x-kv>
            <x-kv label="Cut-off">
                <span class="tabular">{{ $run->period->cutoff_start->toDateString() }} to {{ $run->period->cutoff_end->toDateString() }}</span>
            </x-kv>
            <x-kv label="Status"><x-status-badge :value="$run->run_status" /></x-kv>
            <x-kv label="Population">{{ $run->employee_count }} employee(s)</x-kv>
        </dl>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
            <x-stat label="Gross" :value="$totals['gross']" />
            <x-stat label="Deductions" :value="$totals['deductions']" tone="bad" />
            <x-stat label="Net" :value="$totals['net']" tone="ok" />
        </div>

        <x-note class="mt-4">
            Totals above are derived from the current import's payroll lines, and fixed at finalization (FR-2.5 / FR-4.5).
        </x-note>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Actions & Governance Lifecycle (M5)                                 --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Actions & Governance Workflow (M5)" class="mb-6">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Register Review (UC-21) --}}
                @if ($currentImport !== null)
                    <a href="{{ route('payroll-register.show', $run) }}" class="btn btn-primary">
                        <x-icon name="table" />
                        Payroll Register (UC-21)
                    </a>
                @endif

                {{-- Exception Report (UC-20) --}}
                <a href="{{ route('exception-report.show', $run) }}" class="btn btn-secondary">
                    <x-icon name="shield-check" />
                    Exception Report (UC-20)
                </a>

                {{-- Import History (UC-33) --}}
                <a href="{{ route('payroll-imports.history', $run) }}" class="btn btn-secondary">
                    <x-icon name="history" />
                    Import History (UC-33)
                </a>

                {{-- Worksheet Export (UC-32) --}}
                @if ($canManage)
                    <a href="{{ route('payroll-runs.worksheet', $run) }}" class="btn btn-secondary">
                        <x-icon name="download" />
                        Export Input Worksheet (UC-32)
                    </a>

                    {{-- Intake Import (UC-18) --}}
                    @if (in_array($run->run_status, ['DRAFT', 'RETURNED'], true))
                        <a href="{{ route('payroll-imports.create', $run) }}" class="btn btn-secondary">
                            <x-icon name="upload" />
                            Import Computed Register (UC-18)
                        </a>
                    @endif
                @endif
            </div>

            {{-- State Machine Transitions --}}
            <div class="pt-4 border-t border-stone-200 dark:border-stone-800">
                <div class="flex flex-wrap items-center gap-3">
                    {{-- DRAFT / RETURNED -> Submit for Review (UC-23) --}}
                    @if (in_array($run->run_status, ['DRAFT', 'RETURNED'], true) && $canSubmit)
                        @if ($currentImport === null)
                            <button class="btn btn-secondary opacity-60 cursor-not-allowed" disabled title="Import a register before submission">
                                <x-icon name="send" />
                                Submit for Review (Import Required)
                            </button>
                        @elseif ($hasBlockingExceptions)
                            <button class="btn btn-danger opacity-60 cursor-not-allowed" disabled title="Blocking exceptions outstanding">
                                <x-icon name="alert-triangle" />
                                Submit Blocked (Exceptions Outstanding)
                            </button>
                        @elseif ($hasUnacknowledgedWarnings)
                            <button class="btn btn-warn opacity-60 cursor-not-allowed" disabled title="Acknowledge warnings in exception report">
                                <x-icon name="alert-circle" />
                                Submit Blocked (Warnings Unacknowledged)
                            </button>
                        @else
                            <form method="POST" action="{{ route('payroll-runs.submit', $run) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <x-icon name="send" />
                                    Submit for Review (UC-23)
                                </button>
                            </form>
                        @endif
                    @endif

                    {{-- FOR_REVIEW -> Approve or Return (UC-24) --}}
                    @if ($run->run_status === 'FOR_REVIEW' && $canApproveReturn)
                        @if ($isSubmitter)
                            <span class="text-xs text-red-600 dark:text-red-400 font-semibold flex items-center gap-1">
                                <x-icon name="shield-alert" class="w-4 h-4" />
                                Submitter cannot approve (BR-28 Separation of Duty)
                            </span>
                        @else
                            <form method="POST" action="{{ route('payroll-runs.approve', $run) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <x-icon name="check-circle" />
                                    Approve Run (UC-24)
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('payroll-runs.return-form', $run) }}" class="btn btn-warn">
                            <x-icon name="undo-2" />
                            Return for Correction (UC-24 A1)
                        </a>
                    @endif

                    {{-- APPROVED -> Finalize or Return (UC-25 / UC-24 A2) --}}
                    @if ($run->run_status === 'APPROVED')
                        @if ($canFinalize)
                            <a href="{{ route('payroll-runs.finalize-form', $run) }}" class="btn btn-primary">
                                <x-icon name="lock" />
                                Finalize Run (UC-25)
                            </a>
                        @endif

                        @if ($canApproveReturn)
                            <a href="{{ route('payroll-runs.return-form', $run) }}" class="btn btn-warn">
                                <x-icon name="undo-2" />
                                Return for Correction (UC-24 A2)
                            </a>
                        @endif
                    @endif

                    {{-- FINALIZED -> Reverse (UC-26) --}}
                    @if ($run->run_status === 'FINALIZED' && $canFinalize && $canReverse)
                        <a href="{{ route('payroll-runs.reverse-form', $run) }}" class="btn btn-danger">
                            <x-icon name="history" />
                            Reverse Finalized Run (UC-26)
                        </a>
                    @endif

                    {{-- DRAFT -> Cancel (UC-17 A2) --}}
                    @if ($canManage && $run->run_status === 'DRAFT')
                        <a href="{{ route('payroll-runs.cancel-form', $run) }}" class="btn btn-danger">
                            <x-icon name="ban" />
                            Cancel Run (UC-17 A2)
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Payslips — W11 (UC-27/UC-28, FR-3.1..3.4)                            --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card title="Payslips (M6 · W11)" class="mb-6">
        <div class="flex items-center gap-6 text-sm">
            <x-kv label="Original payslips issued">
                <span class="font-semibold tabular">{{ $originalIssuances }}</span>
            </x-kv>
            <x-kv label="Reports">
                <span class="font-semibold tabular">{{ $reprintIssuances }}</span>
            </x-kv>
            <x-kv label="Population">
                <span class="tabular">{{ $run->employee_count }} employee(s)</span>
            </x-kv>
        </div>

        @if ($run->run_status !== 'FINALIZED')
            <x-note class="mt-4">
                Payslips open only after the run is Finalized (AC-3.1.3 / AC-4.4.4). Current status:
                <span class="font-semibold">{{ $run->run_status }}</span>.
            </x-note>
        @elseif ($canGeneratePayslips)
            <form method="POST" action="{{ route('payslips.generate', $run) }}" class="mt-4 flex flex-wrap items-end gap-3"
                onsubmit="return confirm('Generate the payslip set for all {{ $run->employee_count }} employee(s) of this run as one PDF? Residue exports repeat harmlessly (AC-3.1.4).');">
                @csrf
                <label class="block">
                    <span class="text-xs font-medium text-stone-500 dark:text-stone-400">Filter by department (optional)</span>
                    <select name="department_id" class="select mt-1">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->department_id }}">{{ $department->department_name }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-primary">
                    <x-icon name="download" />
                    Generate All Payslips (UC-27)
                </button>
            </form>
        @else
            <x-note class="mt-4">
                The run is Finalized but you lack the <code>payslips.generate</code> permission (Payroll Officer). A reprint
                of any payslip is available in the payroll-lines table below.
            </x-note>
        @endif
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Transition History (AC-4.4.5)                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    <x-card
        title="State Transition History (AC-4.4.5)"
        subtitle="Append-only governance trail of all status transitions for Run #{{ $run->payroll_run_id }}"
        :flush="true"
        class="mb-6">
        <x-table>
            <x-slot:head>
                <th>Time</th>
                <th>From</th>
                <th>To</th>
                <th>Performed By</th>
                <th>Reason / Stated Justification</th>
            </x-slot:head>

            @forelse ($transitions as $t)
                <tr>
                    <td class="tabular text-xs">{{ $t->performed_at->toDateTimeString() }}</td>
                    <td>
                        @if ($t->from_status)
                            <x-status-badge :value="$t->from_status" />
                        @else
                            <span class="text-stone-400 italic">Initiation</span>
                        @endif
                    </td>
                    <td><x-status-badge :value="$t->to_status" /></td>
                    <td class="font-medium text-xs">{{ $t->performer?->full_name ?? 'User #' . $t->performed_by }}</td>
                    <td class="text-xs text-stone-600 dark:text-stone-300">
                        {{ $t->reason ?? '—' }}
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="5" message="No transitions recorded." />
            @endforelse
        </x-table>
    </x-card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Payroll lines preview                                              --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($currentImport === null)
        <x-card title="Payroll lines">
            <p class="note">No register has been imported into this run yet.</p>
        </x-card>
    @else
        <x-card
            title="Current Payroll Lines"
            subtitle="Version {{ $currentImport->version_no }} · {{ $currentImport->source_filename }} · {{ $currentImport->row_count }} row(s) · imported {{ $currentImport->imported_at->toDateTimeString() }}"
            :flush="true">

            <x-table>
                <x-slot:head>
                    <th>Employee no.</th>
                    <th>Name</th>
                    <th class="num">Gross pay</th>
                    <th class="num">Total deductions</th>
                    <th class="num">Net pay</th>
                    <th>Payslip (UC-27/28)</th>
                </x-slot:head>

                @forelse ($run->lines->where('payroll_import_id', $currentImport->payroll_import_id) as $line)
                    <tr>
                        <td class="font-medium tabular">{{ $line->employee->employee_no }}</td>
                        <td>{{ $line->employee->fullName() }}</td>
                        <td class="num tabular">{{ $line->gross_pay }}</td>
                        <td class="num tabular text-red-600 dark:text-red-400">{{ $line->total_deductions }}</td>
                        <td class="num tabular font-semibold text-emerald-600 dark:text-emerald-400">{{ $line->net_pay }}</td>
                        <td>
                            @if ($run->run_status === 'FINALIZED' && $canReprintPayslips)
                                <div class="flex items-center gap-1.5">
                                    <a href="{{ route('payslips.pdf', [$run, $line->employee]) }}" class="btn btn-sm btn-ghost"
                                        title="View PDF (read-only, nothing recorded)">
                                        <x-icon name="eye" />
                                        PDF
                                    </a>
                                    <form method="POST" action="{{ route('payslips.reprint', [$run, $line->employee]) }}"
                                        onsubmit="return confirm('Reissue employee {{ $line->employee->employee_no }}'s payslip? A reprint is recorded (AC-3.4.3).');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-ghost" title="Reissue and record a reprint">
                                            <x-icon name="rotate-ccw" />
                                            Reprint
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-stone-400 text-xs italic">After finalization</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-empty-state :colspan="6" message="This import contains no payroll lines." />
                @endforelse
            </x-table>
        </x-card>
    @endif
@endsection
