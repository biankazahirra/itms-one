@props(['title', 'description' => null, 'back' => null])

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-xs text-muted hover:text-ink">
                <span aria-hidden="true">&larr;</span> Kembali
            </a>
        @endif

        <h2 class="text-lg font-semibold tracking-tight">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 max-w-prose text-sm text-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>
