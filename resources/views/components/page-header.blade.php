@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back'])

<div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-brand-700 transition-colors duration-200 mb-1">
                <x-icon name="chevron-left" class="w-3.5 h-3.5" :stroke-width="2" />
                {{ $backLabel }}
            </a>
        @endif
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p class="note mt-1">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
