@extends('layouts.app')

@section('title', 'Import history — run #' . $run->payroll_run_id)
@section('heading', 'Import history — run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Import history"
        subtitle="Run #{{ $run->payroll_run_id }} — every register version imported into this run, newest supersedes older."
        :back="route('payroll-runs.show', $run)" back-label="Run #{{ $run->payroll_run_id }}" />

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th class="num">Version</th>
                <th>State</th>
                <th>File</th>
                <th>SHA-256</th>
                <th>Imported by</th>
                <th>Imported at</th>
                <th class="num">Rows</th>
                <th class="num">Gross</th>
                <th class="num">Deductions</th>
                <th class="num">Net</th>
                <th class="actions"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @forelse ($imports as $import)
                <tr @class(['bg-brand-50/40' => $import->is_current])>
                    <td class="num font-medium">v{{ $import->version_no }}</td>
                    <td>
                        {{-- Label is passed explicitly: these exact words are what
                             UC-33's checks look for, so they must not be uppercased. --}}
                        <x-status-badge :value="$import->is_current ? 'CURRENT' : 'SUPERSEDED'"
                                        :label="$import->is_current ? 'Current' : 'Superseded'" />
                    </td>
                    <td class="break-all">{{ $import->source_filename }}</td>
                    <td class="max-w-[16rem]">
                        <span class="code-cell">{{ $import->source_sha256 }}</span>
                    </td>
                    <td>{{ $import->importedBy->username }}</td>
                    <td class="tabular whitespace-nowrap">{{ $import->imported_at->toDateTimeString() }}</td>
                    <td class="num">{{ $import->row_count }}</td>
                    <td class="num">{{ $import->control_total_gross }}</td>
                    <td class="num">{{ $import->control_total_deductions }}</td>
                    <td class="num font-semibold">{{ $import->control_total_net }}</td>
                    <td class="actions">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('payroll-imports.show', [$run, $import]) }}" class="btn btn-ghost btn-sm"><x-icon name="eye" />Detail</a>
                            <a href="{{ route('payroll-imports.download', [$run, $import]) }}" class="btn btn-ghost btn-sm"><x-icon name="download" />Download</a>
                        </div>
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="11"
                               message="No register has been imported into this run yet (UC-18)." />
            @endforelse
        </x-table>
    </x-card>
@endsection
