@php
    $holidayType = old('holiday_type', $holiday?->holiday_type ?? 'REGULAR');
    $isLocal = old('is_local', $holiday?->is_local ?? false);
@endphp

<p>
    <label for="holiday_date">Date</label><br>
    <input type="date" id="holiday_date" name="holiday_date" value="{{ old('holiday_date', $holiday?->holiday_date?->toDateString()) }}" required>
</p>
<p>
    <label for="holiday_name">Name</label><br>
    <input type="text" id="holiday_name" name="holiday_name" value="{{ old('holiday_name', $holiday?->holiday_name ?? '') }}" required>
</p>
<p>
    <label>Classification</label><br>
    <label><input type="radio" name="holiday_type" value="REGULAR" @checked($holidayType === 'REGULAR') required> Regular holiday</label>
    <label><input type="radio" name="holiday_type" value="SPECIAL_NON_WORKING" @checked($holidayType === 'SPECIAL_NON_WORKING')> Special non-working day</label>
</p>
<p>
    <label><input type="checkbox" name="is_local" value="1" @checked($isLocal)> Local (rather than national)</label>
</p>
