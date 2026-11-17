@extends('layouts.app')

@section('title', 'Vendor')

@section('content')
    <x-page-header title="Vendor"
                   description="Kelola data vendor yang dipakai pada kontrak dan dokumen administrasi.">
        <x-slot:action>
            <a href="{{ route('master.vendors.create') }}" class="btn btn-primary">Tambah Vendor</a>
        </x-slot:action>
    </x-page-header>

    <x-session-alerts />

    <x-list-filters :action="route('master.vendors.index')" :search="$search" :status="$status"
                    placeholder="Cari nama, NPWP, atau email" />

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Vendor</th>
                    <th scope="col" class="table-head">NPWP</th>
                    <th scope="col" class="table-head">Kontak</th>
                    <th scope="col" class="table-head">Status</th>
                    <th scope="col" class="table-head">Diperbarui</th>
                    <th scope="col" class="table-head text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($vendors as $vendor)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell">
                            <p class="font-medium">{{ $vendor->company_name }}</p>
                            <p class="mt-0.5 max-w-md truncate text-muted">{{ $vendor->company_address }}</p>
                        </td>
                        <td class="table-cell text-muted">{{ $vendor->npwp ?: '—' }}</td>
                        <td class="table-cell text-muted">
                            {{ $vendor->phone ?: '—' }}
                            @if ($vendor->email)
                                <span class="block text-xs">{{ $vendor->email }}</span>
                            @endif
                        </td>
                        <td class="table-cell"><x-status-badge :active="$vendor->is_active" /></td>
                        <td class="table-cell whitespace-nowrap text-muted">
                            {{ \App\Support\Format::tanggal($vendor->updated_at) }}
                        </td>
                        <td class="table-cell text-right">
                            <a href="{{ route('master.vendors.edit', $vendor) }}"
                               class="text-sm font-medium text-primary hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-muted">
                            Belum ada vendor yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $vendors->links() }}</div>
@endsection
