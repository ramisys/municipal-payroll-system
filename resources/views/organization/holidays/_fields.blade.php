@php
    $holidayType = old('holiday_type', $holiday?->holiday_type ?? 'REGULAR');
    $isLocal = old('is_local', $holiday?->is_local ?? false);
@endphp

<div class="form-grid">
    <x-field label="Date" name="holiday_date" required>
        <input type="date" id="holiday_date" name="holiday_date" class="input"
               value="{{ old('holiday_date', $holiday?->holiday_date?->toDateString()) }}" required autofocus>
    </x-field>

    <x-field label="Name" name="holiday_name" required>
        <input type="text" id="holiday_name" name="holiday_name" class="input"
               value="{{ old('holiday_name', $holiday?->holiday_name ?? '') }}" required>
    </x-field>
</div>

<fieldset>
    <legend class="label">Classification <span class="text-bad-fg" aria-hidden="true">*</span></legend>
    <div class="space-y-2">
        @foreach ([
            'REGULAR' => 'Regular holiday',
            'SPECIAL_NON_WORKING' => 'Special non-working day',
        ] as $value => $label)
            <label class="flex items-start gap-2.5 px-3 py-2.5 rounded-md border border-line-strong cursor-pointer hover:bg-slate-50 transition-colors duration-200">
                <input type="radio" name="holiday_type" value="{{ $value }}" class="checkbox rounded-full mt-0.5"
                       @checked($holidayType === $value) @if ($value === 'REGULAR') required @endif>
                <span class="text-sm text-ink">{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @error('holiday_type')<span class="field-error">{{ $message }}</span>@enderror
</fieldset>

<div>
    <label for="is_local" class="inline-flex items-center gap-2 cursor-pointer">
        <input type="checkbox" id="is_local" name="is_local" value="1" class="checkbox" @checked($isLocal)>
        <span class="text-sm text-ink">Local (rather than national)</span>
    </label>
    @error('is_local')<span class="field-error">{{ $message }}</span>@enderror
</div>
