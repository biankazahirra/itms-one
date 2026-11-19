@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight">Selamat datang, {{ auth()->user()->name }}</h2>
            <p class="mt-1 max-w-prose text-sm text-muted">Ringkasan administrasi seluruh modul ITMS.</p>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="month" class="mb-1 block text-xs font-medium text-muted">Periode bulan</label>
                <input type="month" id="month" name="month" value="{{ $month }}" class="input">
            </div>
            <div>
                <label for="date_from" class="mb-1 block text-xs font-medium text-muted">Dari</label>
                <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="input">
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-xs font-medium text-muted">Sampai</label>
                <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="input">
            </div>
            <button type="submit" class="btn btn-secondary">Terapkan</button>
        </form>
    </div>

    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">Ringkasan Status</h3>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [
                ['title' => 'Purchase Requisition', 'key' => 'purchase_request', 'route' => route('purchase-requests.index')],
                ['title' => 'RAB', 'key' => 'budget', 'route' => route('rab.index')],
                ['title' => 'Kontrak', 'key' => 'contract', 'route' => route('contracts.index')],
                ['title' => 'LHSTP', 'key' => 'lhstp', 'route' => route('lhstp.index')],
            ];
        @endphp

        @foreach ($cards as $card)
            <a href="{{ $card['route'] }}"
               class="rounded-xl border border-line bg-surface p-5 transition-colors duration-150 hover:border-primary/40">
                <p class="text-sm font-medium text-muted">{{ $card['title'] }}</p>
                <dl class="mt-3 space-y-1.5">
                    @foreach ($counts[$card['key']] as $status => $total)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sm text-muted">{{ $status }}</dt>
                            <dd class="text-lg font-semibold tabular-nums">{{ $total }}</dd>
                        </div>
                    @endforeach
                </dl>
            </a>
        @endforeach
    </div>

    <div class="mb-8 grid gap-4 lg:grid-cols-3">
        <section class="rounded-xl border border-line bg-surface lg:col-span-2">
            <div class="border-b border-line px-5 py-4">
                <h3 class="text-sm font-semibold">Kontrak Berakhir dalam 30 Hari</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-app">
                        <tr>
                            <th scope="col" class="table-head">Nomor PO</th>
                            <th scope="col" class="table-head">Pekerjaan</th>
                            <th scope="col" class="table-head">Vendor</th>
                            <th scope="col" class="table-head">Berakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($endingContracts as $contract)
                            <tr class="transition-colors duration-150 hover:bg-app/60">
                                <td class="table-cell font-medium whitespace-nowrap">
                                    <a href="{{ route('contracts.show', $contract) }}" class="hover:underline">{{ $contract->po_number }}</a>
                                </td>
                                <td class="table-cell">{{ $contract->work_name }}</td>
                                <td class="table-cell">{{ $contract->vendor?->company_name ?? '-' }}</td>
                                <td class="table-cell whitespace-nowrap text-muted">{{ \App\Support\Format::tanggal($contract->end_date) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-muted">Belum ada kontrak yang berakhir dalam 30 hari.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-line bg-surface p-5">
            <h3 class="text-sm font-semibold">Total Pembayaran LHSTP</h3>
            <p class="mt-1 text-xs text-muted">Periode {{ $periodLabel }}</p>
            <p class="mt-4 text-2xl font-semibold tabular-nums">{{ \App\Support\Format::rupiah($summary['total'], 2) }}</p>
            <p class="mt-1 text-sm text-muted">{{ $summary['count'] }} LHSTP Final</p>
            <p class="mt-4 text-xs text-muted">Hanya LHSTP berstatus Final yang dihitung, berdasarkan tanggal LHSTP.</p>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="rounded-xl border border-line bg-surface lg:col-span-2">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 class="text-sm font-semibold">Aktivitas Terbaru</h3>
                <a href="{{ route('activity.index') }}" class="text-sm font-medium text-primary hover:underline">Lihat semua</a>
            </div>

            <ul class="divide-y divide-line">
                @forelse ($activities as $activity)
                    <li class="flex flex-wrap items-baseline justify-between gap-2 px-5 py-3">
                        <div>
                            <p class="text-sm">{{ $activity->description }}</p>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ \App\Models\ActivityLog::ENTITY_LABELS[$activity->entity_type] ?? $activity->entity_type }}
                                — {{ $activity->user?->name ?? '-' }}
                            </p>
                        </div>
                        <span class="text-xs whitespace-nowrap text-muted">{{ \App\Support\Format::tanggalWaktu($activity->created_at) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-muted">Belum ada aktivitas.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-xl border border-line bg-surface p-5">
            <h3 class="text-sm font-semibold">Akses Cepat</h3>
            <div class="mt-4 grid gap-2">
                <a href="{{ route('purchase-requests.create') }}" class="btn btn-secondary">Buat PR</a>
                <a href="{{ route('rab.create') }}" class="btn btn-secondary">Buat RAB</a>
                <a href="{{ route('contracts.create') }}" class="btn btn-secondary">Buat Kontrak</a>
                <a href="{{ route('lhstp.create') }}" class="btn btn-secondary">Buat LHSTP</a>
            </div>
        </section>
    </div>
@endsection
