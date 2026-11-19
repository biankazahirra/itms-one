@extends('layouts.app')

@section('title', 'Riwayat Aktivitas')

@section('content')
    <x-page-header title="Riwayat Aktivitas"
                   description="Catatan perubahan data terbaru dari seluruh modul." />

    <form method="GET" action="{{ route('activity.index') }}"
          class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <select name="module" aria-label="Filter modul" class="input input-select">
            <option value="">Semua modul</option>
            @foreach ($modules as $type => $label)
                <option value="{{ $type }}" @selected($module === $type)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="action" aria-label="Filter aksi" class="input input-select">
            <option value="">Semua aksi</option>
            @foreach ($actions as $code => $label)
                <option value="{{ $code }}" @selected($action === $code)>{{ $label }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 lg:col-span-2">
            <input type="date" name="date_from" value="{{ $dateFrom }}" aria-label="Tanggal dari" class="input">
            <input type="date" name="date_to" value="{{ $dateTo }}" aria-label="Tanggal sampai" class="input">
        </div>

        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-6">
            <button type="submit" class="btn btn-secondary">Terapkan</button>
            @if ($module !== '' || $action !== '' || $dateFrom !== '' || $dateTo !== '')
                <a href="{{ route('activity.index') }}" class="text-sm text-muted underline-offset-2 hover:underline">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Waktu</th>
                    <th scope="col" class="table-head">Pengguna</th>
                    <th scope="col" class="table-head">Modul</th>
                    <th scope="col" class="table-head">Aksi</th>
                    <th scope="col" class="table-head">Ringkasan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($logs as $log)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell whitespace-nowrap text-muted">{{ \App\Support\Format::tanggalWaktu($log->created_at) }}</td>
                        <td class="table-cell whitespace-nowrap">{{ $log->user?->name ?? '-' }}</td>
                        <td class="table-cell whitespace-nowrap">{{ \App\Models\ActivityLog::ENTITY_LABELS[$log->entity_type] ?? $log->entity_type }}</td>
                        <td class="table-cell whitespace-nowrap">{{ \App\Models\ActivityLog::ACTION_LABELS[$log->action] ?? $log->action }}</td>
                        <td class="table-cell">{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-muted">Belum ada aktivitas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
