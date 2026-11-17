@props(['action', 'search' => '', 'status' => '', 'placeholder' => 'Cari data'])

<form method="GET" action="{{ $action }}" class="mb-4 flex flex-wrap items-center gap-2">
    <input type="search" name="q" value="{{ $search }}" placeholder="{{ $placeholder }}"
           aria-label="Cari" class="input max-w-xs">

    <select name="status" aria-label="Filter status" class="input input-select max-w-[11rem]">
        <option value="">Semua status</option>
        <option value="active" @selected($status === 'active')>Aktif</option>
        <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
    </select>

    <button type="submit" class="btn btn-secondary">Terapkan</button>

    @if ($search !== '' || $status !== '')
        <a href="{{ $action }}" class="text-sm text-muted underline-offset-2 hover:underline">Reset</a>
    @endif
</form>
