@extends('layouts.app')

@section('title', 'RAB')

@section('content')
    <x-page-header title="Rencana Anggaran Biaya"
                   description="RAB dapat dibuat mandiri atau dari satu PR tersimpan.">
        <x-slot:action>
            <a href="{{ route('rab.create') }}" class="btn btn-primary">Buat RAB</a>
        </x-slot:action>
    </x-page-header>

    <x-session-alerts />

    <form method="GET" action="{{ route('rab.index') }}"
          class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nomor, nama pekerjaan, atau nomor PR"
               aria-label="Cari RAB" class="input lg:col-span-2">

        <select name="status" aria-label="Status" class="input input-select">
            <option value="">Semua status</option>
            @foreach (\App\Models\Budget::STATUSES as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <select name="link" aria-label="Relasi PR" class="input input-select">
            <option value="">Semua relasi</option>
            <option value="linked" @selected($link === 'linked')>Terhubung ke PR</option>
            <option value="standalone" @selected($link === 'standalone')>RAB mandiri</option>
        </select>

        <div class="flex gap-2 lg:col-span-2">
            <input type="date" name="date_from" value="{{ $dateFrom }}" aria-label="Tanggal dari" class="input">
            <input type="date" name="date_to" value="{{ $dateTo }}" aria-label="Tanggal sampai" class="input">
        </div>

        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-6">
            <button type="submit" class="btn btn-secondary">Terapkan</button>
            @if ($search !== '' || $status !== '' || $link !== '' || $dateFrom !== '' || $dateTo !== '')
                <a href="{{ route('rab.index') }}" class="text-sm text-muted underline-offset-2 hover:underline">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Nomor RAB</th>
                    <th scope="col" class="table-head">Nama Pekerjaan</th>
                    <th scope="col" class="table-head">PR terkait</th>
                    <th scope="col" class="table-head">Tanggal</th>
                    <th scope="col" class="table-head text-right">Total Anggaran</th>
                    <th scope="col" class="table-head">Status</th>
                    <th scope="col" class="table-head">Versi</th>
                    <th scope="col" class="table-head text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($budgets as $budget)
                    @php($version = $budget->currentVersion)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell font-medium whitespace-nowrap">{{ $budget->rab_number }}</td>
                        <td class="table-cell">
                            <a href="{{ route('rab.show', $budget) }}" class="hover:underline">
                                {{ $version?->work_name ?? '—' }}
                            </a>
                        </td>
                        <td class="table-cell whitespace-nowrap text-muted">
                            @if ($budget->purchaseRequest)
                                <a href="{{ route('purchase-requests.show', $budget->purchaseRequest) }}" class="hover:underline">
                                    {{ $budget->purchaseRequest->pr_number }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="table-cell whitespace-nowrap text-muted">
                            {{ $version ? \App\Support\Format::tanggal($version->rab_date) : '—' }}
                        </td>
                        <td class="table-cell text-right whitespace-nowrap tabular-nums">
                            {{ \App\Support\Format::rupiah($version?->grand_total, 2) }}
                        </td>
                        <td class="table-cell"><x-document-status :status="$budget->status" /></td>
                        <td class="table-cell text-muted">{{ $version?->label() ?? '—' }}</td>
                        <td class="table-cell text-right">
                            <a href="{{ route('rab.show', $budget) }}"
                               class="text-sm font-medium text-primary hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-muted">
                            Belum ada RAB yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $budgets->links() }}</div>
@endsection
