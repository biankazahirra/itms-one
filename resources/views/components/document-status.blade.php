@props(['status'])

@php
    $classes = match ($status) {
        'Final', 'Aktif' => 'bg-success/10 text-success',
        'Archived' => 'bg-app text-muted ring-1 ring-inset ring-line',
        default => 'bg-warning/10 text-warning',
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $classes }}">
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $status }}
</span>
