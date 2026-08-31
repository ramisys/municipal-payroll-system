{{-- The organization area has five sibling screens; tabs keep them one click
     apart instead of a round trip through the dashboard. --}}
@props(['current'])

@php
    $tabs = [
        'profile' => ['label' => 'Organization profile', 'href' => route('organization.edit')],
        'periods' => ['label' => 'Pay periods', 'href' => route('organization.periods.index')],
        'holidays' => ['label' => 'Holiday calendar', 'href' => route('organization.holidays.index')],
        'reference' => ['label' => 'Reference data', 'href' => route('reference-data.index', 'departments')],
        'mapping' => ['label' => 'Register column mapping', 'href' => route('import-column-maps.index')],
    ];
@endphp

<nav aria-label="Organization settings" class="border-b border-line">
    <ul class="flex flex-wrap -mb-px">
        @foreach ($tabs as $key => $tab)
            <li>
                <a href="{{ $tab['href'] }}"
                   @if ($key === $current) aria-current="page" @endif
                   class="inline-block px-3 py-2 text-sm font-medium border-b-2 transition-colors duration-200
                          @if ($key === $current)
                              border-brand-700 text-brand-700
                          @else
                              border-transparent text-ink-muted hover:text-ink hover:border-line-strong
                          @endif">
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
