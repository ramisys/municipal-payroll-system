@extends('layouts.app')

@section('title', 'Import register — run #' . $run->payroll_run_id)
@section('heading', 'Import register — run #' . $run->payroll_run_id)

@section('content')
    <x-page-header
        title="Import computed payroll register"
        subtitle="Run #{{ $run->payroll_run_id }}"
        :back="route('payroll-runs.show', $run)" back-label="Run #{{ $run->payroll_run_id }}" />

    <div class="max-w-2xl space-y-4">
        <x-note>
            UC-18 preconditions — the run's input worksheet was exported and the accounting office has
            returned a completed register. Select the column mapping version that matches the register's
            layout (AD-17, BR-41).
        </x-note>

        @if ($maps->isEmpty())
            <x-alert type="warn" title="No column mapping is defined yet (BR-41).">
                Maintaining the register column mapping is an Administrator function. Ask an Administrator
                to publish one before a register can be imported.
            </x-alert>
        @endif

        <x-card>
            <form method="POST" action="{{ route('payroll-imports.preview', $run) }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf

                <x-field label="Column mapping" name="import_column_map_id" required>
                    <select id="import_column_map_id" name="import_column_map_id" class="select" required>
                        @foreach ($maps as $map)
                            <option value="{{ $map->import_column_map_id }}">{{ $map->map_name }} v{{ $map->version_no }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Register file" name="file" required>
                    <input type="file" id="file" name="file" class="input-file" required>
                </x-field>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary" @disabled($maps->isEmpty())><x-icon name="eye" />Preview</button>
                    <a href="{{ route('payroll-runs.show', $run) }}" class="btn btn-secondary"><x-icon name="x" />Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
