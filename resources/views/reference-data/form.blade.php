@extends('layouts.app')

@section('title', ($item->exists ? 'Edit' : 'New') . ' ' . strtolower($config['label']))
@section('heading', ($item->exists ? 'Edit' : 'New') . ' ' . strtolower($config['label']))

@section('content')
    <x-page-header
        title="{{ $item->exists ? 'Edit' : 'New' }} {{ strtolower($config['label']) }}"
        :back="route('reference-data.index', $type)" back-label="{{ $config['label'] }}s" />

    <div class="max-w-2xl">
        <x-card>
            <form method="POST"
                  action="{{ $item->exists ? route('reference-data.update', [$type, $item->getKey()]) : route('reference-data.store', $type) }}"
                  class="space-y-4">
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

                    @if ($column['type'] === 'boolean_required')
                        {{-- An explicit Yes/No, not a checkbox: this flag has no
                             safe default, so it must be chosen deliberately. --}}
                        <fieldset>
                            <legend class="label">{{ $column['label'] }} <span class="text-bad-fg" aria-hidden="true">*</span></legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ([1 => 'Yes', 0 => 'No'] as $value => $label)
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-md border border-line-strong cursor-pointer hover:bg-slate-50 transition-colors duration-200">
                                        <input type="radio" name="{{ $field }}" value="{{ $value }}"
                                               class="checkbox rounded-full"
                                               @checked($selected === $value) @if ($value === 1) required @endif>
                                        <span class="text-sm text-ink">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error($field)<span class="field-error">{{ $message }}</span>@enderror
                        </fieldset>

                    @elseif ($column['type'] === 'boolean')
                        <div>
                            <label for="{{ $field }}" class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1"
                                       class="checkbox" @checked($selected === 1)>
                                <span class="text-sm font-medium text-ink">{{ $column['label'] }}</span>
                            </label>
                            @error($field)<span class="field-error">{{ $message }}</span>@enderror
                        </div>

                    @else
                        <x-field :label="$column['label']" :name="$field">
                            <input type="text" id="{{ $field }}" name="{{ $field }}" class="input"
                                   value="{{ old($field, $item->{$field}) }}" @if ($loop->first) autofocus @endif>
                        </x-field>
                    @endif
                @endforeach

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="btn btn-primary"><x-icon name="save" />Save</button>
                    <a href="{{ route('reference-data.index', $type) }}" class="btn btn-secondary"><x-icon name="x" />Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
