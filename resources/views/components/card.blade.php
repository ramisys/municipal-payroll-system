@props(['title' => null, 'subtitle' => null, 'flush' => false])

<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <div class="card-head">
            <div class="min-w-0">
                @if ($title)<h2 class="card-title">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="note mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $flush ? '' : 'card-body' }}">{{ $slot }}</div>
</section>
