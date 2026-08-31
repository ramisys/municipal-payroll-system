{{-- A single figure with its label. Used for run totals, where the number is
     the thing the reader came for, so it gets the size and the tabular. --}}
@props(['label', 'value', 'hint' => null, 'tone' => 'default'])

@php
    $valueTone = ['default' => 'text-ink', 'ok' => 'text-ok-fg', 'bad' => 'text-bad-fg', 'brand' => 'text-brand-700'][$tone] ?? 'text-ink';
@endphp

<div {{ $attributes->merge(['class' => 'px-4 py-3 rounded-md bg-slate-50 border border-line']) }}>
    <p class="text-[12px] font-medium uppercase tracking-wide text-ink-muted">{{ $label }}</p>
    <p class="mt-1 text-xl font-semibold tabular {{ $valueTone }}">{{ $value }}</p>
    @if ($hint)<p class="text-[12px] text-ink-faint mt-0.5">{{ $hint }}</p>@endif
</div>
