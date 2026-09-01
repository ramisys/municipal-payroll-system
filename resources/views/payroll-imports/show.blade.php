@extends('layouts.app')

@section('title', 'Import v' . $import->version_no . ' — run #' . $run->payroll_run_id)
@section('heading', 'Import v' . $import->version_no . ' — run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Import version {{ $import->version_no }}"
        subtitle="Run #{{ $run->payroll_run_id }}"
        :back="route('payroll-imports.history', $run)" back-label="Import history">
        <x-slot:actions>
            <x-status-badge :value="$import->is_current ? 'CURRENT' : 'SUPERSEDED'"
                            :label="$import->is_current ? 'current' : 'superseded'" />
        </x-slot:actions>
    </x-page-header>

    <x-card title="Source file">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-kv label="File name">{{ $import->source_filename }}</x-kv>
            <x-kv label="Mapping">{{ $import->columnMap->map_name }} v{{ $import->columnMap->version_no }}</x-kv>
            <x-kv label="Imported by">{{ $import->importedBy->username }}</x-kv>
            <x-kv label="Imported at">
                <span class="tabular">{{ $import->imported_at->toDateTimeString() }}</span>
            </x-kv>
            <div class="sm:col-span-2">
                <dt class="kv-label">SHA-256</dt>
                <dd class="mt-0.5"><span class="code-cell">{{ $import->source_sha256 }}</span></dd>
            </div>
        </dl>

        <div class="mt-4 pt-4 border-t border-line">
            <a href="{{ route('payroll-imports.download', [$run, $import]) }}" class="btn btn-secondary">
                <x-icon name="download" />
                Download retained source file
            </a>
            <p class="note mt-2">
                Recompute its SHA-256 and compare with the value above to verify it (UC-33 A2).
            </p>
        </div>
    </x-card>

    <x-card title="Stored reconciliation result" :flush="true">
        <x-table>
            <x-slot:head>
                <th>Key</th>
                <th>Value</th>
            </x-slot:head>

            @foreach ($import->reconciliation_result as $key => $value)
                <tr>
                    <td class="font-medium">{{ $key }}</td>
                    <td class="tabular">{{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
@endsection
