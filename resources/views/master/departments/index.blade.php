@extends('layouts.app')

@section('title', 'Departemen')

@section('content')
    <x-page-header title="Departemen"
                   description="Departemen tujuan pada Purchase Requisition dipilih manual dari daftar ini.">
        <x-slot:action>
            <a href="{{ route('master.departments.create') }}" class="btn btn-primary">Tambah Departemen</a>
        </x-slot:action>
    </x-page-header>

    <x-session-alerts />

    <x-list-filters :action="route('master.departments.index')" :search="$search" :status="$status"
                    placeholder="Cari nama departemen" />

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Nama Departemen</th>
                    <th scope="col" class="table-head">Status</th>
                    <th scope="col" class="table-head">Diperbarui</th>
                    <th scope="col" class="table-head text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($departments as $department)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell font-medium">{{ $department->name }}</td>
                        <td class="table-cell"><x-status-badge :active="$department->is_active" /></td>
                        <td class="table-cell whitespace-nowrap text-muted">
                            {{ \App\Support\Format::tanggal($department->updated_at) }}
                        </td>
                        <td class="table-cell text-right">
                            <a href="{{ route('master.departments.edit', $department) }}"
                               class="text-sm font-medium text-primary hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-muted">
                            Belum ada departemen yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $departments->links() }}</div>
@endsection
