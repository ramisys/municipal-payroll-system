@extends('layouts.app')

@section('title', 'Reference data — ' . $config['label'] . 's')
@section('heading', 'Reference data — ' . $config['label'] . 's')

@section('content')
    @php
        $tabs = [
            'departments' => 'Departments',
            'positions' => 'Positions',
            'employment-statuses' => 'Employment statuses',
            'earning-types' => 'Earning types',
            'deduction-types' => 'Deduction types',
            'leave-types' => 'Leave types',
        ];
    @endphp

    <x-page-header title="Reference data"
                   subtitle="The lookup lists the rest of the system draws from. Entries are deactivated, never deleted.">
        <x-slot:actions>
            <a href="{{ route('reference-data.create', $type) }}" class="btn btn-primary">
                <x-icon name="plus" :stroke-width="2" />
                New {{ strtolower($config['label']) }}
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Six sibling lists — tabs keep them one click apart instead of a
         round trip through the dashboard. --}}
    <nav aria-label="Reference data type" class="border-b border-line">
        <ul class="flex flex-wrap -mb-px">
            @foreach ($tabs as $slug => $label)
                <li>
                    <a href="{{ route('reference-data.index', $slug) }}"
                       @if ($slug === $type) aria-current="page" @endif
                       class="inline-block px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-200
                              @if ($slug === $type)
                                  border-brand-700 text-brand-700
                              @else
                                  border-transparent text-ink-muted hover:text-ink hover:border-line-strong
                              @endif">
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <x-card :flush="true">
        <x-table>
            <x-slot:head>
                @foreach ($config['columns'] as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
                <th>Status</th>
                <th class="actions">Actions</th>
            </x-slot:head>

            @forelse ($items as $item)
                <tr>
                    @foreach ($config['columns'] as $column)
                        <td @class(['font-medium' => $loop->first])>
                            @if (str_starts_with($column['type'], 'boolean'))
                                {{ $item->{$column['field']} ? 'Yes' : 'No' }}
                            @else
                                {{ $item->{$column['field']} }}
                            @endif
                        </td>
                    @endforeach

                    <td><x-status-badge :value="$item->is_active ? 'ACTIVE' : 'DEACTIVATED'" /></td>

                    <td class="actions">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('reference-data.edit', [$type, $item->getKey()]) }}"
                               class="btn btn-ghost btn-sm"><x-icon name="pencil" />Edit</a>

                            @if ($item->is_active)
                                <form method="POST" action="{{ route('reference-data.deactivate', [$type, $item->getKey()]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm text-bad-fg hover:bg-bad-bg"><x-icon name="ban" />Deactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('reference-data.reactivate', [$type, $item->getKey()]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm text-ok-fg hover:bg-ok-bg"><x-icon name="rotate-ccw" />Reactivate</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-empty-state :colspan="count($config['columns']) + 2"
                               message="No {{ strtolower($config['label']) }} entries yet.">
                    <x-slot:action>
                        <a href="{{ route('reference-data.create', $type) }}" class="link">
                            Add the first {{ strtolower($config['label']) }}
                        </a>
                    </x-slot:action>
                </x-empty-state>
            @endforelse
        </x-table>
    </x-card>
@endsection
