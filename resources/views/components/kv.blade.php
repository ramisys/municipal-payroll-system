{{-- Label-over-value pair for read-only detail panels. --}}
@props(['label'])

<div>
    <dt class="kv-label">{{ $label }}</dt>
    <dd class="kv-value mt-0.5">{{ $slot }}</dd>
</div>
