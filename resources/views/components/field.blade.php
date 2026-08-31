{{-- Label + control + per-field error, kept together so the message lands
     beside the input that caused it rather than only in the page summary. --}}
@props(['label', 'name', 'hint' => null, 'required' => false, 'span' => false])

<div class="{{ $span ? 'sm:col-span-2' : '' }}">
    <label for="{{ $name }}" class="label">
        {{ $label }}
        @if ($required)<span class="text-bad-fg" aria-hidden="true">*</span>@endif
    </label>

    {{ $slot }}

    @if ($hint)
        <span class="hint">{{ $hint }}</span>
    @endif

    @error($name)
        <span class="field-error">{{ $message }}</span>
    @enderror
</div>
