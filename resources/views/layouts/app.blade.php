@php
    // Every nav destination in one list so the sidebar, the active-state check,
    // and the mobile drawer all read from the same source.
    $navGroups = [
        [
            'label' => null,
            'items' => [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'show' => true, 'match' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Payroll',
            'items' => [
                ['route' => 'payroll-runs.index', 'label' => 'Payroll runs', 'show' => $navCan['payrollRuns'] || $navCan['payrollRecords'], 'match' => 'payroll-runs.*|payroll-imports.*'],
                ['route' => 'attendance-import.create', 'label' => 'Import attendance', 'show' => $navCan['attendance'], 'match' => 'attendance-import.*'],
            ],
        ],
        [
            'label' => 'Records',
            'items' => [
                ['route' => 'employees.index', 'label' => 'Employees', 'show' => $navCan['employees'], 'match' => 'employees.*'],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['route' => 'users.index', 'label' => 'User accounts', 'show' => $navCan['users'], 'match' => 'users.*'],
                ['route' => 'organization.edit', 'label' => 'Organization', 'show' => $navCan['organization'], 'match' => 'organization.*'],
                ['route' => 'reference-data.index', 'label' => 'Reference data', 'show' => $navCan['referenceData'], 'match' => 'reference-data.*', 'params' => 'departments'],
                ['route' => 'import-column-maps.index', 'label' => 'Column mapping', 'show' => $navCan['organization'], 'match' => 'import-column-maps.*'],
            ],
        ],
        [
            'label' => 'Oversight',
            'items' => [
                ['route' => 'audit-log.index', 'label' => 'Audit log', 'show' => $navCan['auditLog'], 'match' => 'audit-log.*'],
            ],
        ],
    ];

    $icons = [
        'dashboard' => 'M3 12h7V3H3v9Zm0 9h7v-7H3v7Zm11 0h7V12h-7v9Zm0-18v7h7V3h-7Z',
        'payroll-runs.index' => 'M4 4h16v4H4V4Zm0 6h16v10H4V10Zm3 3h5m-5 3h8',
        'attendance-import.create' => 'M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2',
        'employees.index' => 'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm11.5 9v-1a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
        'users.index' => 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 10v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1',
        'organization.edit' => 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M9 11h.01M15 11h.01',
        'reference-data.index' => 'M4 6h16M4 12h16M4 18h10',
        'import-column-maps.index' => 'M4 5h5v14H4V5Zm11 0h5v14h-5V5Zm-6 7h6',
        'audit-log.index' => 'M9 12h6m-6 4h4M8 3h8l4 4v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h3Zm7 0v5h5',
    ];

    $isActive = function (string $patterns): bool {
        foreach (explode('|', $patterns) as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="min-h-full lg:flex">

    {{-- Scrim behind the mobile drawer. --}}
    <div data-nav-scrim class="hidden fixed inset-0 z-30 bg-ink/50 lg:hidden" aria-hidden="true"></div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Sidebar                                                          --}}
    {{-- ---------------------------------------------------------------- --}}
    <aside data-nav-drawer
           class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 -translate-x-full overflow-y-auto
                  bg-brand-900 transition-transform duration-200
                  lg:static lg:translate-x-0 lg:h-screen lg:sticky lg:top-0">

        <div class="flex items-center gap-2.5 px-4 h-14 border-b border-white/10">
            <svg class="w-6 h-6 text-brand-200" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>
            </svg>
            <span class="text-sm font-semibold text-white leading-tight">{{ config('app.name') }}</span>
        </div>

        <nav class="p-3 pb-6" aria-label="Main">
            @foreach ($navGroups as $group)
                @php
                    $visible = array_filter($group['items'], fn ($item) => $item['show']);
                @endphp
                @if (count($visible) > 0)
                    @if ($group['label'])
                        <p class="nav-section">{{ $group['label'] }}</p>
                    @endif
                    <ul class="space-y-0.5">
                        @foreach ($visible as $item)
                            @php $active = $isActive($item['match']); @endphp
                            <li>
                                <a href="{{ route($item['route'], $item['params'] ?? []) }}"
                                   class="nav-link @if ($active) nav-link-active @endif"
                                   @if ($active) aria-current="page" @endif>
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true">
                                        <path d="{{ $icons[$item['route']] ?? $icons['dashboard'] }}"/>
                                    </svg>
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        </nav>
    </aside>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Main column                                                      --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="flex-1 min-w-0 flex flex-col">

        <header class="sticky top-0 z-20 flex items-center gap-3 h-14 px-4 bg-white border-b border-line">
            <button type="button" data-nav-toggle aria-expanded="false" aria-label="Open navigation"
                    class="lg:hidden inline-flex items-center justify-center w-11 h-11 -ml-2 rounded-md
                           text-ink-muted hover:bg-slate-100 hover:text-ink transition-colors duration-200 cursor-pointer">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="min-w-0 flex-1">
                @hasSection('heading')
                    <p class="text-sm font-semibold text-ink truncate">@yield('heading')</p>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden sm:block text-right leading-tight">
                    <p class="text-[13px] font-medium text-ink">{{ $navUser->username }}</p>
                    <p class="text-[12px] text-ink-muted">{{ $navUser->role->role_name }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/>
                        </svg>
                        Sign out
                    </button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            <div class="mx-auto max-w-[1400px] space-y-4">

                {{-- Global flash region. Controllers flash 'status'; reading it
                     once here beats repeating the block in every view. --}}
                @if (session('status'))
                    <div class="alert alert-ok" role="status">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                {{-- One error summary for the whole page, at the top, where a
                     screen reader lands after the failed submit. Fields also
                     mark themselves individually. --}}
                @if ($errors->any())
                    <div class="alert alert-bad" role="alert">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="alert-title">
                                {{ $errors->count() === 1 ? 'There is a problem with this form.' : "There are {$errors->count()} problems with this form." }}
                            </p>
                            <ul class="mt-1 space-y-0.5 list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>

        <footer class="px-4 sm:px-6 py-4 border-t border-line">
            <p class="text-[12px] text-ink-faint">
                {{ config('app.name') }} &middot; Internal use only. Every create, update, and delete is recorded in the audit log.
            </p>
        </footer>
    </div>
</div>
</body>
</html>
