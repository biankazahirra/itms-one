@extends('layouts.app')

@section('title', 'LHSTP')

@section('content')
    <x-page-header title="Laporan Hasil Serah Terima Pekerjaan"
                   description="LHSTP dibuat dari kontrak tersimpan dan menyimpan snapshot data kontrak per versi.">
        <x-slot:action>
            <a href="{{ route('lhstp.create') }}" class="btn btn-primary">Buat LHSTP</a>
        </x-slot:action>
    </x-page-header>

    <x-session-alerts />

    <form method="GET" action="{{ route('lhstp.index') }}"
          class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nomor, pekerjaan, PO, atau vendor"
               aria-label="Cari LHSTP" class="input lg:col-span-2">

        <select name="status" aria-label="Status" class="input input-select">
            <option value="">Semua status</option>
            @foreach (\App\Models\Lhstp::STATUSES as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 lg:col-span-2">
            <input type="date" name="date_from" value="{{ $dateFrom }}" aria-label="Tanggal dari" class="input">
            <input type="date" name="date_to" value="{{ $dateTo }}" aria-label="Tanggal sampai" class="input">
        </div>

        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-6">
            <button type="submit" class="btn btn-secondary">Terapkan</button>
            @if ($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '')
                <a href="{{ route('lhstp.index') }}" class="text-sm text-muted underline-offset-2 hover:underline">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Nomor LHSTP</th>
                    <th scope="col" class="table-head">Nama Pekerjaan</th>
                    <th scope="col" class="table-head">Nomor PO</th>
                    <th scope="col" class="table-head">Vendor</th>
                    <th scope="col" class="table-head">Periode Pekerjaan</th>
                    <th scope="col" class="table-head">Tanggal LHSTP</th>
                    <th scope="col" class="table-head text-right">Nilai Pembayaran</th>
                    <th scope="col" class="table-head">Status</th>
                    <th scope="col" class="table-head text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($lhstps as $lhstp)
                    @php($version = $lhstp->currentVersion)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell font-medium whitespace-nowrap">{{ $lhstp->lhstp_number }}</td>
                        <td class="table-cell">
                            <a href="{{ route('lhstp.show', $lhstp) }}" class="hover:underline">
                                {{ $version?->work_name_snapshot ?? '-' }}
                            </a>
                        </td>
                        <td class="table-cell whitespace-nowrap">{{ $version?->po_number_snapshot ?? '-' }}</td>
                        <td class="table-cell">{{ $version?->vendor_name_snapshot ?? '-' }}</td>
                        <td class="table-cell whitespace-nowrap text-muted">{{ $version?->work_period ?? '-' }}</td>
                        <td class="table-cell whitespace-nowrap text-muted">
                            {{ $version ? \App\Support\Format::tanggal($version->lhstp_date) : '-' }}
                        </td>
                        <td class="table-cell text-right tabular-nums">
                            {{ $version ? \App\Support\Format::rupiah($version->total_payment, 2) : '-' }}
                        </td>
                        <td class="table-cell"><x-document-status :status="$lhstp->status" /></td>
                        <td class="table-cell text-right whitespace-nowrap">
                            <a href="{{ route('lhstp.show', $lhstp) }}" class="text-sm font-medium text-primary hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-muted">Belum ada LHSTP.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $lhstps->links() }}</div>
@endsection
