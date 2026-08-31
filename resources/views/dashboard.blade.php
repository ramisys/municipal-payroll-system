@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    @php
        // Only the modules this role can actually reach. The sidebar hides the
        // same set; AuthorizationService is what refuses a direct URL.
        $modules = [
            [
                'show' => $canManagePayrollRuns,
                'href' => route('payroll-runs.index'),
                'title' => 'Payroll runs',
                'body' => 'Create a run for a pay period, export its input worksheet, and import the computed register.',
                'icon' => 'M4 4h16v4H4V4Zm0 6h16v10H4V10Zm3 3h5m-5 3h8',
            ],
            [
                'show' => $canImportAttendance,
                'href' => route('attendance-import.create'),
                'title' => 'Import attendance',
                'body' => 'Load a cut-off period\'s attendance file. Nothing is written until you confirm the preview.',
                'icon' => 'M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2',
            ],
            [
                'show' => $canManageEmployees,
                'href' => route('employees.index'),
                'title' => 'Employees',
                'body' => 'Register and maintain the employee master file, employment history, and compensation profiles.',
                'icon' => 'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm11.5 9v-1a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
            ],
            [
                'show' => $canManageUsers,
                'href' => route('users.index'),
                'title' => 'User accounts',
                'body' => 'Create accounts, assign roles, and unlock or reset a sign-in that has been locked out.',
                'icon' => 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 10v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1',
            ],
            [
                'show' => $canManageOrganization,
                'href' => route('organization.edit'),
                'title' => 'Organization',
                'body' => 'Organization profile, the payroll calendar, and the holiday list runs are computed against.',
                'icon' => 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M9 11h.01M15 11h.01',
            ],
            [
                'show' => $canManageOrganization,
                'href' => route('reference-data.index', 'departments'),
                'title' => 'Reference data',
                'body' => 'Departments, positions, employment statuses, and the earning and deduction types.',
                'icon' => 'M4 6h16M4 12h16M4 18h10',
            ],
            [
                'show' => $canManageOrganization,
                'href' => route('import-column-maps.index'),
                'title' => 'Register column mapping',
                'body' => 'Tell the importer which spreadsheet column carries which payroll field.',
                'icon' => 'M4 5h5v14H4V5Zm11 0h5v14h-5V5Zm-6 7h6',
            ],
            [
                'show' => $canViewAuditLog,
                'href' => route('audit-log.index'),
                'title' => 'Audit log',
                'body' => 'Every recorded change, in order, with the hash chain that proves nothing was removed.',
                'icon' => 'M9 12h6m-6 4h4M8 3h8l4 4v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h3Zm7 0v5h5',
            ],
        ];

        $modules = array_values(array_filter($modules, fn ($m) => $m['show']));
    @endphp

    <x-page-header
        title="Welcome, {{ $user->username }}"
        subtitle="You are signed in as {{ $user->role->role_name }}. The modules below are the ones your role grants." />

    @if (count($modules) === 0)
        <x-card>
            <p class="note">Your role does not currently grant access to any module. Contact the system administrator.</p>
        </x-card>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($modules as $module)
                <a href="{{ $module['href'] }}"
                   class="group card p-4 flex gap-3 hover:border-brand-700 hover:shadow-pop transition-all duration-200">
                    <span class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-md bg-brand-50 text-brand-700 group-hover:bg-brand-700 group-hover:text-white transition-colors duration-200">
                        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="{{ $module['icon'] }}"/>
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-700 transition-colors duration-200">
                            {{ $module['title'] }}
                        </span>
                        <span class="block note mt-1">{{ $module['body'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    <x-note>
        Remaining per-role module screens are not built yet — this is the pre-oral W3 milestone landing view.
    </x-note>
@endsection
