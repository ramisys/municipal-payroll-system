{{--
    One place that decides what a status looks like, so DRAFT reads the same on
    the run list, the run detail, and the import history.

    Colour is never the only signal: each badge also carries its own word, and
    the dot is a shape cue. A user who cannot separate the greens from the reds
    still reads "CANCELLED".
--}}
@props(['value', 'label' => null])

@php
    $key = strtoupper(trim((string) $value));

    $tones = [
        // Payroll run lifecycle.
        'DRAFT' => 'badge-neutral',
        'PENDING' => 'badge-warn',
        'RETURNED' => 'badge-warn',
        'APPROVED' => 'badge-ok',
        'FINALIZED' => 'badge-ok',
        'CANCELLED' => 'badge-bad',

        // Record state.
        'ACTIVE' => 'badge-ok',
        'DEACTIVATED' => 'badge-neutral',
        'INACTIVE' => 'badge-neutral',
        'LOCKED' => 'badge-bad',
        'CURRENT' => 'badge-info',
        'SUPERSEDED' => 'badge-neutral',

        // Plain answers.
        'YES' => 'badge-info',
        'NO' => 'badge-neutral',

        // Audit actions.
        'CREATE' => 'badge-ok',
        'UPDATE' => 'badge-info',
        'DELETE' => 'badge-bad',
        'IMPORT' => 'badge-info',
        'EXPORT' => 'badge-neutral',
        'APPROVE' => 'badge-ok',
        'FINALIZE' => 'badge-ok',
        'REVERSE' => 'badge-warn',
        'LOGIN' => 'badge-neutral',
    ];

    $tone = $tones[$key] ?? 'badge-neutral';
@endphp

<span {{ $attributes->merge(['class' => "badge {$tone}"]) }}>
    <span class="badge-dot" aria-hidden="true"></span>{{ $label ?? $key }}
</span>
