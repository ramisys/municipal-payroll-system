@props(['label', 'value' => null])

<div>
    <dt class="kv-label">{{ $label }}</dt>
    <dd class="kv-value mt-0.5">{{ $value ?? $slot }}</dd>
</div>
