@extends('layouts.app')

@section('title', 'Register column mapping')
@section('heading', 'Register column mapping')

@section('content')
    <x-page-header title="Register column mapping (CANONICAL)">
        <x-slot:actions>
            <a href="{{ route('import-column-maps.create') }}" class="btn btn-primary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Publish a new version
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-org-tabs current="mapping" />

    <x-note>
        AD-17, BR-41 — binds the fields RegisterImportService reads to the accounting office's register
        header strings. The active version with the highest number is the one applied at import.
    </x-note>

    @forelse ($versions as $version)
        <x-card :flush="true">
            <div class="card-head">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="card-title">Version {{ $version->version_no }}</h2>
                    <x-status-badge :value="$version->is_active ? 'ACTIVE' : 'INACTIVE'"
                                    :label="$version->is_active ? 'Active' : 'Retired'" />
                    <span class="note tabular">
                        Effective {{ $version->effective_from->toDateString() }}
                        &rarr; {{ $version->effective_to?->toDateString() ?? 'open' }}
                    </span>
                </div>

                @if ($version->is_active)
                    <form method="POST" action="{{ route('import-column-maps.deactivate', $version) }}">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">Retire</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('import-column-maps.reactivate', $version) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">Reactivate</button>
                    </form>
                @endif
            </div>

            {{-- The bindings are JSON the administrator must be able to read
                 exactly, so they stay verbatim in a scrollable mono block. --}}
            <div class="card-body">
                <p class="kv-label mb-1">Bindings</p>
                <pre class="overflow-x-auto p-3 rounded-md bg-slate-50 border border-line font-mono text-[12px] leading-relaxed text-ink">{{ json_encode($version->column_bindings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </x-card>
    @empty
        <x-card>
            <p class="note">
                No versions yet. A register cannot be imported until at least one mapping version is published.
            </p>
            <a href="{{ route('import-column-maps.create') }}" class="btn btn-primary mt-3">Publish the first version</a>
        </x-card>
    @endforelse
@endsection
