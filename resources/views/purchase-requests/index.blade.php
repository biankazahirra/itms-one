@extends('layouts.app')

@section('title', 'Purchase Requisition')

@section('content')
    <x-page-header title="Purchase Requisition"
                   description="PR dapat dibuat mandiri dan menjadi sumber opsional untuk RAB.">
        <x-slot:action>
            <a href="{{ route('purchase-requests.create') }}" class="btn btn-primary">Buat PR</a>
        </x-slot:action>
    </x-page-header>

    <x-session-alerts />

    <form method="GET" action="{{ route('purchase-requests.index') }}"
          class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-8">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nomor atau perihal"
               aria-label="Cari PR" class="input lg:col-span-2">

        <select name="status" aria-label="Status" class="input input-select">
            <option value="">Semua status</option>
            @foreach (\App\Models\PurchaseRequest::STATUSES as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <select name="tracking" aria-label="Tracking" class="input input-select">
            <option value="">Semua tracking</option>
            @foreach ([\App\Services\PrTrackingService::STATUS_NOT_TRACKED, \App\Services\PrTrackingService::STATUS_IN_PROGRESS, \App\Services\PrTrackingService::STATUS_DONE] as $option)
                <option value="{{ $option }}" @selected($tracking === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <select name="category" aria-label="Kategori" class="input input-select">
            <option value="">Semua kategori</option>
            @foreach (\App\Models\PurchaseRequest::CATEGORIES as $option)
                <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
            @endforeach
        </select>

        <select name="department" aria-label="Departemen tujuan" class="input input-select">
            <option value="">Semua departemen</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) $department->id === $departmentId)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>

        <div class="flex gap-2 lg:col-span-2">
            <input type="date" name="date_from" value="{{ $dateFrom }}" aria-label="Tanggal dari" class="input">
            <input type="date" name="date_to" value="{{ $dateTo }}" aria-label="Tanggal sampai" class="input">
        </div>

        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-8">
            <button type="submit" class="btn btn-secondary">Terapkan</button>
            @if ($search !== '' || $status !== '' || $category !== '' || $departmentId !== '' || $dateFrom !== '' || $dateTo !== '' || $tracking !== '')
                <a href="{{ route('purchase-requests.index') }}" class="text-sm text-muted underline-offset-2 hover:underline">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-app">
                <tr>
                    <th scope="col" class="table-head">Nomor PR</th>
                    <th scope="col" class="table-head">Perihal</th>
                    <th scope="col" class="table-head">Kepada</th>
                    <th scope="col" class="table-head">Tanggal</th>
                    <th scope="col" class="table-head">Kategori</th>
                    <th scope="col" class="table-head text-right">Total</th>
                    <th scope="col" class="table-head">Status</th>
                    <th scope="col" class="table-head">Tracking</th>
                    <th scope="col" class="table-head">Versi</th>
                    <th scope="col" class="table-head text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($purchaseRequests as $purchaseRequest)
                    @php($version = $purchaseRequest->currentVersion)
                    <tr class="transition-colors duration-150 hover:bg-app/60">
                        <td class="table-cell font-medium whitespace-nowrap">{{ $purchaseRequest->pr_number }}</td>
                        <td class="table-cell">
                            <a href="{{ route('purchase-requests.show', $purchaseRequest) }}" class="hover:underline">
                                {{ $version?->subject ?? '—' }}
                            </a>
                        </td>
                        <td class="table-cell text-muted">{{ $version?->department?->name ?? '—' }}</td>
                        <td class="table-cell whitespace-nowrap text-muted">
                            {{ $version ? \App\Support\Format::tanggal($version->pr_date) : '—' }}
                        </td>
                        <td class="table-cell text-muted">{{ $version?->category ?: '—' }}</td>
                        <td class="table-cell text-right whitespace-nowrap tabular-nums">
                            {{ \App\Support\Format::rupiah($version?->grand_total, 2) }}
                        </td>
                        <td class="table-cell"><x-document-status :status="$purchaseRequest->status" /></td>
                        <td class="table-cell"><x-document-status :status="$trackingStatuses[$purchaseRequest->id]" /></td>
                        <td class="table-cell text-muted">{{ $version?->label() ?? '—' }}</td>
                        <td class="table-cell text-right">
                            <a href="{{ route('purchase-requests.show', $purchaseRequest) }}"
                               class="text-sm font-medium text-primary hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-muted">
                            Belum ada PR yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $purchaseRequests->links() }}</div>
@endsection
