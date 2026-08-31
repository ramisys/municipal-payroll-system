<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-brand-900">
<div class="min-h-full flex flex-col items-center justify-center p-4 sm:p-6">

    <main class="w-full max-w-[26rem]">

        <div class="flex items-center justify-center gap-2.5 mb-6">
            <svg class="w-7 h-7 text-brand-200" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>
            </svg>
            <span class="text-base font-semibold text-white">{{ config('app.name') }}</span>
        </div>

        <div class="card p-6 sm:p-7">
            <h1 class="text-lg">@yield('heading')</h1>

            @hasSection('subheading')
                <p class="note mt-1">@yield('subheading')</p>
            @endif

            <div class="mt-5">
                @yield('content')
            </div>
        </div>

        <p class="mt-5 text-center text-[12px] text-slate-400">
            Authorised users only. Sign-in attempts are recorded.
        </p>
    </main>
</div>
</body>
</html>
