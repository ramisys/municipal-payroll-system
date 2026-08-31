@props(['type' => 'info', 'title' => null])

@php
    $tone = ['ok' => 'alert-ok', 'warn' => 'alert-warn', 'bad' => 'alert-bad', 'info' => 'alert-info'][$type] ?? 'alert-info';
    $role = in_array($type, ['bad', 'warn'], true) ? 'alert' : 'status';
    $paths = [
        'ok' => 'M20 6 9 17l-5-5',
        'warn' => 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z',
        'bad' => 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z',
        'info' => 'M12 16v-4m0-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    ];
@endphp

<div {{ $attributes->merge(['class' => "alert {$tone}"]) }} role="{{ $role }}">
    <svg class="w-5 h-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="{{ $paths[$type] ?? $paths['info'] }}"/>
    </svg>
    <div class="min-w-0">
        @if ($title)<p class="alert-title">{{ $title }}</p>@endif
        <div @class(['mt-1' => $title])>{{ $slot }}</div>
    </div>
</div>
