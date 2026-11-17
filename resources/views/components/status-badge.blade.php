@props(['active'])

@if ($active)
    <span class="inline-flex items-center gap-1.5 rounded-full bg-success/10 px-2.5 py-1 text-xs font-medium text-success">
        <span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>
        Aktif
    </span>
@else
    <span class="inline-flex items-center gap-1.5 rounded-full bg-app px-2.5 py-1 text-xs font-medium text-muted ring-1 ring-inset ring-line">
        <span class="size-1.5 rounded-full bg-muted" aria-hidden="true"></span>
        Nonaktif
    </span>
@endif
