@extends('layouts.app')

@section('title', 'Recycle Bin')

@section('content')
    <x-page-header title="Recycle Bin"
                   description="Data yang dihapus disimpan selama {{ \App\Services\RecycleBinService::RETENTION_DAYS }} hari sebelum dihapus permanen." />

    <x-session-alerts />

    <form method="GET" action="{{ route('recycle-bin.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <select name="module" aria-label="Filter modul" class="input input-select max-w-[14rem]">
            <option value="">Semua modul</option>
            @foreach ($modules as $type => $label)
                <option value="{{ $type }}" @selected($module === $type)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn btn-secondary">Terapkan</button>

        @if ($module !== '')
            <a href="{{ route('recycle-bin.index') }}" class="text-sm text-muted underline-offset-2 hover:underline">Reset</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Modul</th>
                    <th scope="col" class="table-head">Nomor</th>
                    <th scope="col" class="table-head">Keterangan</th>
                    <th scope="col" class="table-head">Dihapus</th>
                    <th scope="col" class="table-head">Kedaluwarsa</th>
                    <th scope="col" class="table-head">Dihapus oleh</th>
                    <th scope="col" class="table-head text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell whitespace-nowrap">{{ $row['module'] }}</td>
                        <td class="table-cell font-medium whitespace-nowrap">{{ $row['number'] }}</td>
                        <td class="table-cell">{{ $row['description'] }}</td>
                        <td class="table-cell whitespace-nowrap text-muted">{{ \App\Support\Format::tanggalWaktu($row['item']->deleted_at) }}</td>
                        <td class="table-cell whitespace-nowrap text-muted">{{ \App\Support\Format::tanggal($row['item']->expires_at) }}</td>
                        <td class="table-cell whitespace-nowrap">{{ $row['item']->deletedBy?->name ?? '-' }}</td>
                        <td class="table-cell text-right whitespace-nowrap">
                            <div class="flex justify-end gap-2">
                                <form method="POST" action="{{ route('recycle-bin.restore', $row['item']) }}">
                                    @csrf
                                    <button type="submit" class="text-sm font-medium text-primary hover:underline">Pulihkan</button>
                                </form>

                                <form method="POST" action="{{ route('recycle-bin.destroy', $row['item']) }}"
                                      onsubmit="return confirm('Hapus permanen {{ $row['number'] }}? Data tidak dapat dipulihkan lagi.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-danger hover:underline">Hapus permanen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-muted">Recycle Bin kosong.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
@endsection
