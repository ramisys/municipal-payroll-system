@extends('layouts.app')

@section('title', 'Database Backups')
@section('heading', 'Database Backups & Restore')

@section('content')
    <x-page-header title="Database Backups & Restore" subtitle="Logical database dumps with documented restore procedure and SHA-256 integrity verification (NFR-5.4, UC-07).">
        <x-slot:actions>
            <form action="{{ route('backups.store') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <x-icon name="plus" class="w-3.5 h-3.5" />
                    Create backup now
                </button>
            </form>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Open Run Warning (UC-07 E2) --}}
    @if ($openRunsCount > 0)
        <div class="mb-6 p-4 rounded-lg bg-amber-50 border-2 border-amber-300 text-amber-900 flex items-start gap-3">
            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded bg-amber-200 text-amber-900 font-bold text-xs uppercase tracking-wider">
                WARNING
            </span>
            <div class="text-xs">
                <p class="font-semibold text-sm">Active Payroll Run in Progress (UC-07 E2)</p>
                <p class="mt-0.5">
                    There are currently <strong>{{ $openRunsCount }}</strong> open payroll run(s) in Draft, For Review, or Approved status.
                    Restoring a backup while a run is in progress will overwrite uncommitted work.
                </p>
            </div>
        </div>
    @endif

    {{-- Backups List --}}
    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                <th>Backup File</th>
                <th>Created At</th>
                <th class="num">Size</th>
                <th class="num">Tables</th>
                <th class="num">Rows</th>
                <th>Checksum (SHA-256)</th>
                <th class="text-right">Actions</th>
            </x-slot:head>
            @forelse ($backups as $b)
                <tr>
                    <td class="font-semibold text-ink text-xs font-mono">
                        {{ $b['filename'] }}
                    </td>
                    <td class="tabular text-xs">
                        {{ \Carbon\Carbon::parse($b['created_at'])->format('Y-m-d H:i:s') }}
                    </td>
                    <td class="num tabular text-xs font-mono">
                        {{ number_format($b['size_bytes'] / 1024, 1) }} KB
                    </td>
                    <td class="num tabular text-xs">{{ $b['tables_count'] }}</td>
                    <td class="num tabular text-xs">{{ number_format($b['total_rows']) }}</td>
                    <td class="tabular text-2xs font-mono text-ink-muted" title="{{ $b['sha256'] }}">
                        {{ substr($b['sha256'], 0, 16) }}...
                    </td>
                    <td class="text-right space-x-1">
                        <a href="{{ route('backups.download', $b['filename']) }}" class="btn btn-secondary btn-xs">
                            <x-icon name="download" class="w-3 h-3" />
                            Download
                        </a>
                        <button type="button"
                                onclick="openRestoreModal('{{ $b['filename'] }}')"
                                class="btn btn-danger btn-xs">
                            Restore
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-8 text-center text-ink-muted text-sm">
                        No database backups exist yet. Click "Create backup now" or wait for the scheduled system clock job.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </x-card>

    {{-- Restore Confirmation Modal (NFR-6.3) --}}
    <div id="restoreModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-ink/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 text-left border border-line">
            <h3 class="text-lg font-bold text-rose-700 mb-2">Confirm Database Restore</h3>
            <p class="text-xs text-ink-muted mb-4 leading-relaxed">
                Restoring replaces the entire active database with the contents of this archive (NFR-6.3). All changes made after the backup timestamp will be permanently lost.
            </p>

            <form action="{{ route('backups.restore') }}" method="POST">
                @csrf
                <input type="hidden" id="modalFilenameInput" name="filename">

                <div class="mb-4">
                    <label class="label text-xs mb-1">To confirm, type the exact filename below:</label>
                    <p id="modalFilenameDisplay" class="font-mono text-xs font-bold text-ink bg-slate-100 p-2 rounded border border-line mb-2"></p>
                    <input type="text" name="confirmation_text" class="input font-mono text-xs" required placeholder="Type filename here">
                </div>

                @if ($openRunsCount > 0)
                    <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded text-xs text-amber-900">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="force" value="1" class="mt-0.5 rounded text-amber-600 focus:ring-amber-500" required>
                            <span>I acknowledge that <strong>{{ $openRunsCount }}</strong> active run(s) are in progress and accept discarding uncommitted work (UC-07 E2).</span>
                        </label>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-line">
                    <button type="button" onclick="closeRestoreModal()" class="btn btn-secondary btn-sm">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        Confirm and restore database
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRestoreModal(filename) {
            document.getElementById('modalFilenameInput').value = filename;
            document.getElementById('modalFilenameDisplay').innerText = filename;
            document.getElementById('restoreModal').classList.remove('hidden');
        }
        function closeRestoreModal() {
            document.getElementById('restoreModal').classList.add('hidden');
        }
    </script>
@endsection
