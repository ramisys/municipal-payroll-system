<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $item->exists ? 'Edit' : 'New' }} {{ strtolower($config['label']) }} — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ route('reference-data.index', $type) }}">&larr; {{ $config['label'] }}s</a></p>

    <h1>{{ $item->exists ? 'Edit' : 'New' }} {{ strtolower($config['label']) }}</h1>

    @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $item->exists ? route('reference-data.update', [$type, $item->getKey()]) : route('reference-data.store', $type) }}">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif

        @foreach ($config['columns'] as $column)
            @php
                $field = $column['field'];
                $currentValue = $item->exists ? (int) $item->{$field} : null;
                $selected = old($field, $currentValue);
                $selected = $selected === null ? null : (int) $selected;
            @endphp
            <p>
                <label for="{{ $field }}">{{ $column['label'] }}</label><br>

                @if ($column['type'] === 'boolean_required')
                    <label><input type="radio" name="{{ $field }}" value="1" @checked($selected === 1) required> Yes</label>
                    <label><input type="radio" name="{{ $field }}" value="0" @checked($selected === 0)> No</label>
                @elseif ($column['type'] === 'boolean')
                    <input type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1" @checked($selected === 1)>
                @else
                    <input type="text" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $item->{$field}) }}">
                @endif
            </p>
        @endforeach

        <button type="submit">Save</button>
    </form>
</body>
</html>
