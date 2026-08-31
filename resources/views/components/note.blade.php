{{-- The traceability notes carried by these screens (UC/FR/BR references) are
     part of the record, not decoration — this keeps them legible but quiet. --}}
<aside {{ $attributes->merge(['class' => 'flex items-start gap-2.5 px-3 py-2.5 rounded-md bg-slate-50 border border-line text-[13px] text-ink-muted']) }}>
    <svg class="w-4 h-4 shrink-0 mt-0.5 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/><path d="M12 16v-4m0-4h.01"/>
    </svg>
    <div class="min-w-0">{{ $slot }}</div>
</aside>
