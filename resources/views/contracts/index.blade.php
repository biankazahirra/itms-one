@extends('layouts.app')

@section('title', 'Kontrak')

@section('content')
    <x-page-header title="Contract Management"
                   description="Kontrak menjadi sumber data LHSTP. PO Number unik untuk satu kontrak dan satu vendor.">
        <x-slot:action>
            <a href="{{ route('contracts.create') }}" class="btn btn-primary">Buat Kontrak</a>
        </x-slot:action>
    </x-page-header>

    <x-session-alerts />

    <form method="GET" action="{{ route('contracts.index') }}"
          class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari PO, pekerjaan, atau vendor"
               aria-label="Cari kontrak" class="input lg:col-span-2">

        <select name="status" aria-label="Status" class="input input-select">
            <option value="">Semua status</option>
            @foreach (\App\Models\Contract::STATUSES as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <select name="vendor_id" aria-label="Vendor" class="input input-select">
            <option value="">Semua vendor</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected($vendorId === (string) $vendor->id)>
                    {{ $vendor->company_name }}
                </option>
            @endforeach
        </select>

        <select name="payment_method" aria-label="Metode pembayaran" class="input input-select">
            <option value="">Semua metode</option>
            @foreach (\App\Models\Contract::PAYMENT_METHODS as $option)
                <option value="{{ $option }}" @selected($method === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 sm:col-span-2 lg:col-span-2">
            <input type="date" name="end_from" value="{{ $endFrom }}" aria-label="Berakhir dari" class="input">
            <input type="date" name="end_to" value="{{ $endTo }}" aria-label="Berakhir sampai" class="input">
        </div>

        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="btn btn-secondary">Terapkan</button>
            @if ($search !== '' || $status !== '' || $vendorId !== '' || $method !== '' || $endFrom !== '' || $endTo !== '')
                <a href="{{ route('contracts.index') }}" class="text-sm text-muted underline-offset-2 hover:underline">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Nomor PO</th>
                    <th scope="col" class="table-head">Nama Pekerjaan</th>
                    <th scope="col" class="table-head">Vendor</th>
                    <th scope="col" class="table-head">Tanggal Kontrak</th>
                    <th scope="col" class="table-head">Periode</th>
                    <th scope="col" class="table-head">Berakhir</th>
                    <th scope="col" class="table-head text-right">Nilai Kontrak</th>
                    <th scope="col" class="table-head">Metode</th>
                    <th scope="col" class="table-head">Status</th>
                    <th scope="col" class="table-head text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($contracts as $contract)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell font-medium whitespace-nowrap">{{ $contract->po_number }}</td>
                        <td class="table-cell">
                            <a href="{{ route('contracts.show', $contract) }}" class="hover:underline">
                                {{ $contract->work_name }}
                            </a>
                        </td>
                        <td class="table-cell">{{ $contract->vendor?->company_name ?? '—' }}</td>
                        <td class="table-cell whitespace-nowrap text-muted">{{ \App\Support\Format::tanggal($contract->contract_date) }}</td>
                        <td class="table-cell whitespace-nowrap text-muted">
                            {{ \App\Support\Format::tanggal($contract->start_date) }} –
                            {{ \App\Support\Format::tanggal($contract->end_date) }}
                        </td>
                        <td class="table-cell whitespace-nowrap text-muted">{{ \App\Support\Format::tanggal($contract->end_date) }}</td>
                        <td class="table-cell text-right whitespace-nowrap tabular-nums">
                            {{ \App\Support\Format::rupiah($contract->contract_value, 2) }}
                        </td>
                        <td class="table-cell whitespace-nowrap">{{ $contract->payment_method }}</td>
                        <td class="table-cell"><x-document-status :status="$contract->status" /></td>
                        <td class="table-cell text-right">
                            <a href="{{ route('contracts.show', $contract) }}"
                               class="text-sm font-medium text-primary hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-muted">
                            Belum ada kontrak yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $contracts->links() }}</div>
@endsection
