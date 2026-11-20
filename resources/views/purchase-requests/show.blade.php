@extends('layouts.app')

@section('title', 'Detail PR')

@section('content')
    @php($version = $purchaseRequest->currentVersion)

    <x-page-header :back="route('purchase-requests.index')" title="{{ $purchaseRequest->pr_number }}"
                   description="{{ $version?->subject }}">
        <x-slot:action>
            <div class="flex flex-wrap items-center gap-2">
                @if ($version !== null)
                    <a href="{{ route('rab.create', ['from_pr' => $purchaseRequest->id]) }}" class="btn btn-secondary">Buat RAB dari PR</a>
                @endif

                @if ($purchaseRequest->isDraft())
                    <a href="{{ route('purchase-requests.edit', $purchaseRequest) }}" class="btn btn-secondary">Ubah Draft</a>

                    <details class="relative">
                        <summary class="btn btn-primary cursor-pointer list-none">Finalisasi</summary>
                        <div class="absolute right-0 z-10 mt-2 w-80 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Finalisasi PR {{ $purchaseRequest->pr_number }}?</p>
                            <p class="mt-1 text-xs text-muted">Versi ini tidak dapat diubah setelah final. Perubahan berikutnya memakai revisi baru.</p>
                            <form method="POST" action="{{ route('purchase-requests.finalize', $purchaseRequest) }}" class="mt-3 space-y-3">
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
                @elseif ($purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_FINAL)
                    <form method="POST" action="{{ route('purchase-requests.revision', $purchaseRequest) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Buat Revisi</button>
                    </form>

                    <details class="relative">
                        <summary class="btn btn-secondary cursor-pointer list-none">Arsipkan</summary>
                        <div class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Arsipkan PR ini?</p>
                            <p class="mt-1 text-xs text-muted">PR tetap tersimpan dan tidak dapat dihapus permanen.</p>
                            <form method="POST" action="{{ route('purchase-requests.archive', $purchaseRequest) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full">Ya, arsipkan</button>
                            </form>
                        </div>
                    </details>
                @endif

                @if ($purchaseRequest->isDraft() && ! $purchaseRequest->versions->contains(fn ($item) => $item->isFinal()))
                    <details class="relative">
                        <summary class="btn btn-secondary cursor-pointer list-none text-danger">Hapus</summary>
                        <div class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Hapus PR {{ $purchaseRequest->pr_number }}?</p>
                            <p class="mt-1 text-xs text-muted">PR dipindahkan ke Recycle Bin selama 30 hari.</p>
                            <form method="POST" action="{{ route('purchase-requests.destroy', $purchaseRequest) }}" class="mt-3">
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

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-line bg-surface p-6 lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Data PR {{ $version?->label() }}</h3>

            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-muted">Dari</dt>
                    <dd class="mt-0.5 text-sm">Divisi ITMS / IT &amp; Management System Department</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Kepada</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->department?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Perihal</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->subject ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Tanggal</dt>
                    <dd class="mt-0.5 text-sm">{{ $version ? \App\Support\Format::tanggal($version->pr_date) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Kategori</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->category ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Grand Total</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">{{ \App\Support\Format::rupiah($version?->grand_total, 2) }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Status</h3>

            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Status PR</span>
                    <x-document-status :status="$purchaseRequest->status" />
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Tracking</span>
                    <x-document-status :status="$trackingStatus" />
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Versi aktif</span>
                    <span class="font-medium">{{ $version?->label() ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Dibuat</span>
                    <span>{{ \App\Support\Format::tanggalWaktu($purchaseRequest->created_at) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Diperbarui</span>
                    <span>{{ \App\Support\Format::tanggalWaktu($purchaseRequest->updated_at) }}</span>
                </div>
                @if ($purchaseRequest->finalized_at)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Difinalisasi</span>
                        <span>{{ \App\Support\Format::tanggalWaktu($purchaseRequest->finalized_at) }}</span>
                    </div>
                @endif
                @if ($purchaseRequest->archived_at)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Diarsipkan</span>
                        <span>{{ \App\Support\Format::tanggalWaktu($purchaseRequest->archived_at) }}</span>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-muted">Rincian Anggaran {{ $version?->label() }}</h3>
            @if ($version?->isFinal() && ! $documentsByVersion->has($version->id))
                <form method="POST" action="{{ route('purchase-requests.documents.store', $purchaseRequest) }}">
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
                        <th scope="col" class="table-head text-right">Qty</th>
                        <th scope="col" class="table-head text-right">Harga Satuan</th>
                        <th scope="col" class="table-head text-right">Estimasi Harga</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($version?->items ?? [] as $index => $item)
                        <tr>
                            <td class="table-cell text-muted">{{ $index + 1 }}</td>
                            <td class="table-cell">{{ $item->budget_name }}</td>
                            <td class="table-cell text-right tabular-nums">{{ \App\Support\Money::quantity($item->quantity) }}</td>
                            <td class="table-cell text-right tabular-nums">{{ \App\Support\Format::rupiah($item->unit_price, 2) }}</td>
                            <td class="table-cell text-right tabular-nums">{{ bccomp((string) $item->estimated_total, '0', 2) === 0 ? '' : \App\Support\Format::rupiah($item->estimated_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-muted">Belum ada baris anggaran.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($version)
                    <tfoot class="border-t border-line bg-app">
                        <tr>
                            <th scope="row" colspan="4" class="table-cell text-left text-sm font-semibold">Grand Total</th>
                            <td class="table-cell text-right text-sm font-semibold tabular-nums">
                                {{ \App\Support\Format::rupiah($version->grand_total, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-muted">Tracking PR</h3>
            <x-document-status :status="$trackingStatus" />
        </div>

        <ol class="space-y-3">
            @forelse ($purchaseRequest->stageUpdates as $update)
                <li class="border-b border-line pb-3 last:border-0 last:pb-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-medium">{{ $update->stage }}</p>
                        <p class="text-xs text-muted">
                            {{ \App\Support\Format::tanggalWaktu($update->occurred_at) }}
                            · {{ $update->creator?->name ?? 'Sistem' }}
                        </p>
                    </div>
                    @if ($update->note)
                        <p class="mt-1 text-xs text-muted">{{ $update->note }}</p>
                    @endif
                </li>
            @empty
                <li class="text-sm text-muted">Belum ada tahap tracking.</li>
            @endforelse
        </ol>

        <form method="POST" action="{{ route('purchase-requests.stage-updates.store', $purchaseRequest) }}"
              class="mt-5 grid gap-3 sm:grid-cols-3">
            @csrf
            <x-field name="stage" label="Tahap" required>
                <select id="stage" name="stage" required class="input input-select">
                    @foreach ($trackingStages as $stage)
                        <option value="{{ $stage }}" @selected(old('stage') === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="note" label="Catatan">
                <input id="note" name="note" type="text" maxlength="1000" value="{{ old('note') }}"
                       class="input @error('note') input-error @enderror">
            </x-field>

            <div class="flex items-end">
                <button type="submit" class="btn btn-secondary">Tambah Tahap</button>
            </div>
        </form>
    </section>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Arsip Dokumen PR</h3>

        @if ($purchaseRequest->prDocuments->isEmpty())
            <p class="text-sm text-muted">Belum ada dokumen yang diunggah.</p>
        @else
            <ul class="divide-y divide-line rounded-lg border border-line">
                @foreach ($purchaseRequest->prDocuments as $document)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ $document->original_filename }}</p>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ $document->category }}
                                · {{ number_format($document->file_size / 1024, 0, ',', '.') }} KB
                                · diunggah {{ \App\Support\Format::tanggalWaktu($document->uploaded_at) }}
                                @if ($document->uploader) oleh {{ $document->uploader->name }} @endif
                            </p>
                            @if ($document->notes)
                                <p class="mt-0.5 text-xs text-muted">{{ $document->notes }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($document->canPreview())
                                <a href="{{ route('purchase-requests.pr-documents.preview', [$purchaseRequest, $document]) }}"
                                   target="_blank" rel="noopener"
                                   class="text-sm font-medium text-primary hover:underline">Pratinjau</a>
                            @endif
                            <a href="{{ route('purchase-requests.pr-documents.download', [$purchaseRequest, $document]) }}"
                               class="text-sm font-medium text-primary hover:underline">Unduh</a>
                            <form method="POST" action="{{ route('purchase-requests.pr-documents.destroy', [$purchaseRequest, $document]) }}"
                                  onsubmit="return confirm('Pindahkan dokumen ini ke Recycle Bin?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-danger hover:underline">Hapus</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('purchase-requests.pr-documents.store', $purchaseRequest) }}"
              enctype="multipart/form-data" class="mt-5 grid gap-3 sm:grid-cols-3">
            @csrf
            <x-field name="category" label="Kategori" required>
                <select id="category" name="category" required class="input input-select">
                    @foreach (\App\Models\PrDocument::CATEGORIES as $category)
                        <option value="{{ $category }}" @selected(old('category', 'Official PR Document') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="file" label="Berkas" required help="PDF, JPG, JPEG, atau PNG. Maksimal 10 MB.">
                <input id="file" name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png"
                       class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-app file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink">
            </x-field>

            <x-field name="notes" label="Catatan">
                <input id="notes" name="notes" type="text" maxlength="2000" value="{{ old('notes') }}"
                       class="input @error('notes') input-error @enderror">
            </x-field>

            <div class="sm:col-span-3">
                <button type="submit" class="btn btn-secondary">Unggah Dokumen</button>
            </div>
        </form>
    </section>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Kontrak/PO Terkait</h3>

        @if ($purchaseRequest->contracts->isEmpty())
            <p class="text-sm text-muted">Belum ada kontrak/PO terkait.</p>
        @else
            <ul class="divide-y divide-line rounded-lg border border-line">
                @foreach ($purchaseRequest->contracts as $contract)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <a href="{{ route('contracts.show', $contract) }}"
                               class="font-medium text-primary hover:underline">{{ $contract->po_number }}</a>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ $contract->work_name }} · {{ $contract->vendor?->company_name ?? '—' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-document-status :status="$contract->status" />
                            <form method="POST" action="{{ route('contracts.unlink', $contract) }}"
                                  onsubmit="return confirm('Lepas kaitan PO ini dari PR?')">
                                @csrf
                                <button type="submit" class="text-sm font-medium text-danger hover:underline">Lepas</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Ringkasan Pembayaran</h3>

        @if (count($paymentSummaries) === 0)
            <p class="text-sm text-muted">Belum ada kontrak terkait untuk dihitung.</p>
        @else
            <ul class="divide-y divide-line rounded-lg border border-line">
                @foreach ($paymentSummaries as $summary)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <a href="{{ route('contracts.show', $summary['contract']) }}"
                               class="font-medium text-primary hover:underline">{{ $summary['contract']->po_number }}</a>
                            <p class="mt-0.5 text-xs text-muted">
                                Kewajiban {{ \App\Support\Format::rupiah($summary['obligation'], 2) }}
                                · Confirmed {{ \App\Support\Format::rupiah($summary['paid'], 2) }}
                            </p>
                        </div>
                        <span class="text-xs font-medium">{{ $summary['status'] }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-muted">Ringkasan ini dihitung dari kontrak terkait dan tidak mengubah status tracking PR.</p>
        @endif
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Riwayat Versi</h3>

            <div class="space-y-3">
                @foreach ($purchaseRequest->versions->sortByDesc('version_number') as $historyVersion)
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
                                    {{ \App\Support\Format::tanggal($historyVersion->pr_date) }}
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
