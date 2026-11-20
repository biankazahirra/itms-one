@extends('layouts.app')

@section('title', 'Detail RAB')

@php($version = $budget->currentVersion)
@php($linkedPr = $budget->purchaseRequest)

@section('content')
    <x-page-header :back="route('rab.index')" :title="$budget->rab_number"
                   :description="$version?->work_name">
        <x-slot:action>
            <div class="flex flex-wrap items-center gap-2">
                @if ($budget->isDraft())
                    <a href="{{ route('rab.edit', $budget) }}" class="btn btn-secondary">Ubah Draft</a>

                    <details class="relative">
                        <summary class="btn btn-primary cursor-pointer list-none">Finalisasi</summary>
                        <div class="absolute right-0 z-10 mt-2 w-80 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Finalisasi RAB {{ $budget->rab_number }}?</p>
                            <p class="mt-1 text-xs text-muted">Versi ini tidak dapat diubah setelah final. Perubahan berikutnya memakai revisi baru.</p>
                            @if ($linkedPrTotal !== null && $version && bccomp((string) $version->grand_total, (string) $linkedPrTotal, 2) !== 0)
                                <p class="mt-2 rounded-lg border border-danger/30 bg-danger/5 px-3 py-2 text-xs font-medium text-danger">
                                    Total RAB belum sama dengan total PR ({{ \App\Support\Format::rupiah($linkedPrTotal, 2) }}). Finalisasi akan ditolak.
                                </p>
                            @endif
                            <form method="POST" action="{{ route('rab.finalize', $budget) }}" class="mt-3 space-y-3">
                                @csrf
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" name="generate_pdf" value="1" checked
                                           class="mt-0.5 size-4 rounded border-line text-primary focus:ring-primary/20">
                                    <span>Buat PDF setelah finalisasi.</span>
                                </label>
                                <button type="submit" class="btn btn-primary w-full">Ya, finalisasi</button>
                            </form>
                        </div>
                    </details>
                @elseif ($budget->status === \App\Models\Budget::STATUS_FINAL)
                    <form method="POST" action="{{ route('rab.revision', $budget) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Buat Revisi</button>
                    </form>

                    <details class="relative">
                        <summary class="btn btn-secondary cursor-pointer list-none">Arsipkan</summary>
                        <div class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Arsipkan RAB ini?</p>
                            <p class="mt-1 text-xs text-muted">RAB tetap tersimpan dan tidak dapat dihapus permanen.</p>
                            <form method="POST" action="{{ route('rab.archive', $budget) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full">Ya, arsipkan</button>
                            </form>
                        </div>
                    </details>
                @endif

                @if ($budget->isDraft() && ! $budget->versions->contains(fn ($item) => $item->isFinal()))
                    <details class="relative">
                        <summary class="btn btn-secondary cursor-pointer list-none text-danger">Hapus</summary>
                        <div class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Hapus RAB {{ $budget->rab_number }}?</p>
                            <p class="mt-1 text-xs text-muted">RAB dipindahkan ke Recycle Bin selama 30 hari.</p>
                            <form method="POST" action="{{ route('rab.destroy', $budget) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn w-full bg-danger text-white hover:bg-danger/90">Ya, hapus</button>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        </x-slot:action>
    </x-page-header>

    <x-session-alerts />

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-line bg-surface p-6 lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Data RAB {{ $version?->label() }}</h3>

            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-xs text-muted">Nama pekerjaan</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->work_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Tanggal</dt>
                    <dd class="mt-0.5 text-sm">{{ $version ? \App\Support\Format::tanggal($version->rab_date) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Lokasi</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->location ?? \App\Models\Budget::LOCATION }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">PR terkait</dt>
                    <dd class="mt-0.5 text-sm">
                        @if ($linkedPr)
                            <a href="{{ route('purchase-requests.show', $linkedPr) }}" class="text-primary hover:underline">
                                {{ $linkedPr->pr_number }}
                            </a>
                        @else
                            — (RAB mandiri)
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Grand Total</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">{{ \App\Support\Format::rupiah($version?->grand_total, 2) }}</dd>
                </div>
            </dl>

            @if ($linkedPr && $linkedPrTotal !== null && $version)
                @php($differs = bccomp((string) $version->grand_total, (string) $linkedPrTotal, 2) !== 0)
                <div class="mt-4 rounded-lg border {{ $differs ? 'border-danger/30 bg-danger/5' : 'border-line bg-app' }} px-4 py-3 text-sm">
                    <p class="font-medium {{ $differs ? 'text-danger' : 'text-muted' }}">
                        {{ $differs ? 'Total RAB belum sama dengan total PR.' : 'Total RAB sama dengan total PR.' }}
                    </p>
                    <p class="mt-1 text-muted">
                        Total PR {{ \App\Support\Format::rupiah($linkedPrTotal, 2) }} ·
                        Total RAB {{ \App\Support\Format::rupiah($version->grand_total, 2) }}
                    </p>
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Status</h3>

            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Status RAB</span>
                    <x-document-status :status="$budget->status" />
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Versi aktif</span>
                    <span class="font-medium">{{ $version?->label() ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Dibuat</span>
                    <span>{{ \App\Support\Format::tanggalWaktu($budget->created_at) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Diperbarui</span>
                    <span>{{ \App\Support\Format::tanggalWaktu($budget->updated_at) }}</span>
                </div>
                @if ($budget->finalized_at)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Difinalisasi</span>
                        <span>{{ \App\Support\Format::tanggalWaktu($budget->finalized_at) }}</span>
                    </div>
                @endif
                @if ($budget->archived_at)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Diarsipkan</span>
                        <span>{{ \App\Support\Format::tanggalWaktu($budget->archived_at) }}</span>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-muted">Rincian Anggaran {{ $version?->label() }}</h3>
            @if ($version?->isFinal() && ! $documentsByVersion->has($version->id))
                <form method="POST" action="{{ route('rab.documents.store', $budget) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Buat PDF Versi Ini</button>
                </form>
            @endif
        </div>

        <div class="overflow-x-auto rounded-lg border border-line">
            <table class="min-w-full text-sm">
                <thead class="bg-app">
                    <tr>
                        <th scope="col" class="table-head w-12">No</th>
                        <th scope="col" class="table-head">Nama Anggaran</th>
                        <th scope="col" class="table-head">Deskripsi</th>
                        <th scope="col" class="table-head text-right">Qty</th>
                        <th scope="col" class="table-head">Unit</th>
                        <th scope="col" class="table-head text-right">Harga</th>
                        <th scope="col" class="table-head text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($version?->items ?? [] as $index => $item)
                        <tr>
                            <td class="table-cell text-muted">{{ $index + 1 }}</td>
                            <td class="table-cell">{{ $item->budget_name }}</td>
                            <td class="table-cell text-muted">{{ $item->description ?: '—' }}</td>
                            <td class="table-cell text-right tabular-nums">{{ \App\Support\Money::quantity($item->quantity) }}</td>
                            <td class="table-cell">{{ $item->unit_name_snapshot ?? $item->unit?->unit_name ?? '—' }}</td>
                            <td class="table-cell text-right tabular-nums">{{ \App\Support\Format::rupiah($item->unit_price, 2) }}</td>
                            <td class="table-cell text-right tabular-nums">
                                {{ bccomp((string) $item->line_total, '0', 2) === 0 ? '' : \App\Support\Format::rupiah($item->line_total, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-muted">Belum ada baris anggaran.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($version)
                    <tfoot class="border-t border-line bg-app">
                        <tr>
                            <th scope="row" colspan="6" class="table-cell text-left text-sm font-semibold">Grand Total</th>
                            <td class="table-cell text-right text-sm font-semibold tabular-nums">
                                {{ \App\Support\Format::rupiah($version->grand_total, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Riwayat Versi</h3>

            <div class="space-y-3">
                @foreach ($budget->versions->sortByDesc('version_number') as $historyVersion)
                    @php($document = $documentsByVersion->get($historyVersion->id))
                    <div class="rounded-lg border border-line p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium">
                                    {{ $historyVersion->label() }}
                                    <span class="ml-1 text-xs font-normal text-muted">
                                        {{ $historyVersion->isFinal() ? 'Final' : 'Draft' }}
                                    </span>
                                </p>
                                <p class="mt-0.5 text-xs text-muted">
                                    {{ \App\Support\Format::tanggal($historyVersion->rab_date) }}
                                    · {{ \App\Support\Format::rupiah($historyVersion->grand_total, 2) }}
                                    · {{ $historyVersion->creator?->name ?? '—' }}
                                </p>
                            </div>
                            @if ($document)
                                <a href="{{ route('documents.download', $document) }}"
                                   class="text-sm font-medium text-primary hover:underline">
                                    Unduh PDF
                                </a>
                            @else
                                <span class="text-xs text-muted">Tanpa PDF</span>
                            @endif
                        </div>
                        @if ($historyVersion->finalized_at)
                            <p class="mt-2 text-xs text-muted">
                                Difinalisasi {{ \App\Support\Format::tanggalWaktu($historyVersion->finalized_at) }}
                                @if ($historyVersion->finalizer)
                                    oleh {{ $historyVersion->finalizer->name }}
                                @endif
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Aktivitas Terakhir</h3>

            <ol class="space-y-3">
                @forelse ($activityLogs as $log)
                    <li class="border-b border-line pb-3 last:border-0 last:pb-0">
                        <p class="text-sm">{{ $log->description }}</p>
                        <p class="mt-0.5 text-xs text-muted">
                            {{ \App\Support\Format::tanggalWaktu($log->created_at) }}
                            · {{ $log->user?->name ?? 'Sistem' }}
                        </p>
                    </li>
                @empty
                    <li class="text-sm text-muted">Belum ada aktivitas.</li>
                @endforelse
            </ol>
        </section>
    </div>
@endsection
