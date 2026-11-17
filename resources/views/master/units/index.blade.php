@extends('layouts.app')

@section('title', 'Satuan')

@section('content')
    <x-page-header title="Satuan"
                   description="Satuan dipakai pada item RAB dan dapat ditambahkan dari alur RAB.">
        <x-slot:action>
            <a href="{{ route('master.units.create') }}" class="btn btn-primary">Tambah Satuan</a>
        </x-slot:action>
    </x-page-header>

    <x-session-alerts />

    <x-list-filters :action="route('master.units.index')" :search="$search" :status="$status"
                    placeholder="Cari nama satuan" />

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Nama Satuan</th>
                    <th scope="col" class="table-head">Status</th>
                    <th scope="col" class="table-head">Diperbarui</th>
                    <th scope="col" class="table-head text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($units as $unit)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell font-medium">{{ $unit->unit_name }}</td>
                        <td class="table-cell"><x-status-badge :active="$unit->is_active" /></td>
                        <td class="table-cell whitespace-nowrap text-muted">
                            {{ \App\Support\Format::tanggal($unit->updated_at) }}
                        </td>
                        <td class="table-cell text-right">
                            <a href="{{ route('master.units.edit', $unit) }}"
                               class="text-sm font-medium text-primary hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-muted">
                            Belum ada satuan yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $units->links() }}</div>
@endsection
