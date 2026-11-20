@extends('layouts.app')

@section('title', 'Detail LHSTP')

@section('content')
    <x-page-header :back="route('lhstp.index')" :title="$lhstp->lhstp_number"
                   :description="$version?->work_name_snapshot">
        <x-slot:action>
            <div class="flex flex-wrap items-center gap-2">
                @if ($lhstp->isDraft())
                    <a href="{{ route('lhstp.edit', $lhstp) }}" class="btn btn-secondary">Ubah Draft</a>

                    <details class="relative">
                        <summary class="btn btn-primary cursor-pointer list-none">Finalisasi</summary>
                        <div class="absolute right-0 z-10 mt-2 w-80 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Finalisasi LHSTP {{ $lhstp->lhstp_number }}?</p>
                            <p class="mt-1 text-xs text-muted">Versi ini tidak dapat diubah setelah final. Perubahan berikutnya memakai revisi baru.</p>
                            <form method="POST" action="{{ route('lhstp.finalize', $lhstp) }}" class="mt-3 space-y-3">
                                @csrf
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" name="generate_document" value="1"
                                           class="mt-0.5 size-4 rounded border-line text-primary focus:ring-primary/20">
                                    <span>Buat PDF setelah finalisasi.</span>
                                </label>
                                <button type="submit" class="btn btn-primary w-full">Ya, finalisasi</button>
                            </form>
                        </div>
                    </details>
                @elseif ($lhstp->status === \App\Models\Lhstp::STATUS_FINAL)
                    <form method="POST" action="{{ route('lhstp.revise', $lhstp) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Buat Revisi</button>
                    </form>

                    <details class="relative">
                        <summary class="btn btn-secondary cursor-pointer list-none">Arsipkan</summary>
                        <div class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Arsipkan LHSTP ini?</p>
                            <p class="mt-1 text-xs text-muted">LHSTP tetap tersimpan dan tidak dapat dihapus permanen.</p>
                            <form method="POST" action="{{ route('lhstp.archive', $lhstp) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full">Ya, arsipkan</button>
                            </form>
                        </div>
                    </details>
                @endif

                @if ($lhstp->canBeDeleted())
                    <details class="relative">
                        <summary class="btn btn-secondary cursor-pointer list-none text-danger">Hapus</summary>
                        <div class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Hapus LHSTP {{ $lhstp->lhstp_number }}?</p>
                            <p class="mt-1 text-xs text-muted">LHSTP dipindahkan ke Recycle Bin.</p>
                            <form method="POST" action="{{ route('lhstp.destroy', $lhstp) }}" class="mt-3">
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
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Snapshot Kontrak {{ $version?->label() }}</h3>

            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-xs text-muted">Nama pekerjaan</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->work_name_snapshot ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Nomor PO</dt>
                    <dd class="mt-0.5 text-sm">
                        @if ($lhstp->contract)
                            <a href="{{ route('contracts.show', $lhstp->contract) }}" class="font-medium text-primary hover:underline">
                                {{ $version?->po_number_snapshot ?? $lhstp->contract->po_number }}
                            </a>
                        @else
                            {{ $version?->po_number_snapshot ?? '-' }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Tanggal kontrak</dt>
                    <dd class="mt-0.5 text-sm">{{ $version ? \App\Support\Format::tanggal($version->contract_date_snapshot) : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Vendor</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->vendor_name_snapshot ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Perwakilan vendor</dt>
                    <dd class="mt-0.5 text-sm">
                        {{ $version?->representative_name_snapshot ?? '-' }}
                        <span class="text-muted">({{ $version?->representative_position_snapshot }})</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Nilai kontrak</dt>
                    <dd class="mt-0.5 text-sm tabular-nums">{{ $version ? \App\Support\Format::rupiah($version->contract_value_snapshot, 2) : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Periode kontrak</dt>
                    <dd class="mt-0.5 text-sm">
                        {{ $version ? \App\Support\Format::tanggal($version->contract_start_snapshot) : '-' }}
                        &ndash;
                        {{ $version ? \App\Support\Format::tanggal($version->contract_end_snapshot) : '-' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Metode pembayaran</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->payment_method_snapshot ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Status PPN kontrak</dt>
                    <dd class="mt-0.5 text-sm">{{ $version?->ppn_status_snapshot ?? '-' }}</dd>
                </div>
                @if ($version?->termin)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-muted">Termin</dt>
                        <dd class="mt-0.5 text-sm">
                            {{ $version->termin->termin_name_snapshot }}
                            ({{ \App\Support\Format::angka($version->termin->percentage_snapshot, 4) }}%,
                            {{ \App\Support\Format::rupiah($version->termin->nominal_snapshot, 2) }})
                        </dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Status</h3>

            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Status LHSTP</span>
                    <x-document-status :status="$lhstp->status" />
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Versi aktif</span>
                    <span class="font-medium">{{ $version?->label() ?? '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Tanggal LHSTP</span>
                    <span>{{ $version ? \App\Support\Format::tanggal($version->lhstp_date) : '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Tanggal opname</span>
                    <span>{{ $version ? \App\Support\Format::tanggal($version->opname_date) : '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Tanggal tanda tangan</span>
                    <span>{{ $version ? \App\Support\Format::tanggal($version->signature_date) : '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Dibuat</span>
                    <span>{{ \App\Support\Format::tanggalWaktu($lhstp->created_at) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Diperbarui</span>
                    <span>{{ \App\Support\Format::tanggalWaktu($lhstp->updated_at) }}</span>
                </div>
                @if ($lhstp->finalized_at)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Difinalisasi</span>
                        <span>{{ \App\Support\Format::tanggalWaktu($lhstp->finalized_at) }}</span>
                    </div>
                @endif
                @if ($lhstp->archived_at)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Diarsipkan</span>
                        <span>{{ \App\Support\Format::tanggalWaktu($lhstp->archived_at) }}</span>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Realisasi dan Dokumen Referensi</h3>

        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-muted">Periode pekerjaan</dt>
                <dd class="mt-0.5 text-sm">{{ $version?->work_period ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Jumlah hari realisasi</dt>
                <dd class="mt-0.5 text-sm">{{ $version?->realization_days ?? '-' }} hari kalender</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Realisasi mulai</dt>
                <dd class="mt-0.5 text-sm">{{ $version ? \App\Support\Format::tanggal($version->realization_start) : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Realisasi selesai</dt>
                <dd class="mt-0.5 text-sm">{{ $version ? \App\Support\Format::tanggal($version->realization_end) : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Jenis dokumen referensi</dt>
                <dd class="mt-0.5 text-sm">
                    {{ $version?->reference_document_type }}
                    @if ($version?->reference_document_name)
                        <span class="text-muted">({{ $version->reference_document_name }})</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Nomor dan tanggal referensi</dt>
                <dd class="mt-0.5 text-sm">
                    {{ $version?->reference_document_number ?? '-' }}
                    &middot;
                    {{ $version ? \App\Support\Format::tanggal($version->reference_document_date) : '-' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Cost Center</dt>
                <dd class="mt-0.5 text-sm">{{ $version?->cost_center ?? '-' }}</dd>
            </div>
        </dl>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Perhitungan Pembayaran</h3>

            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-line">
                    <tr>
                        <td class="py-2 text-muted">Nilai pembayaran</td>
                        <td class="py-2 text-right tabular-nums">{{ $version ? \App\Support\Format::rupiah($version->payment_amount, 2) : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-muted">DPP LAIN</td>
                        <td class="py-2 text-right tabular-nums">{{ $version ? \App\Support\Format::rupiah($version->dpp_lain, 2) : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-muted">PPN ({{ $version ? \App\Support\Format::angka($version->ppn_rate, 2) : '-' }}%)</td>
                        <td class="py-2 text-right tabular-nums">{{ $version ? \App\Support\Format::rupiah($version->ppn_amount, 2) : '-' }}</td>
                    </tr>
                    <tr class="font-semibold">
                        <td class="py-2">Total pembayaran</td>
                        <td class="py-2 text-right tabular-nums">{{ $version ? \App\Support\Format::rupiah($version->total_payment, 2) : '-' }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="mt-3 text-xs text-muted">Terbilang: {{ $version?->terbilang ?? '-' }}</p>
        </section>

        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Kesimpulan dan Ketentuan Pembayaran</h3>

            <div class="space-y-4 text-sm">
                <div>
                    <p class="text-xs text-muted">Kesimpulan pekerjaan</p>
                    <p class="mt-1 whitespace-pre-line">{{ $version?->work_conclusion ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted">Ketentuan pembayaran</p>
                    <p class="mt-1 whitespace-pre-line">{{ $version?->payment_terms ?? '-' }}</p>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Riwayat Versi dan Dokumen</h3>

            <div class="space-y-3">
                @foreach ($lhstp->versions->sortByDesc('version_number') as $historyVersion)
                    @php($documents = $documentsByVersion->get($historyVersion->id, collect()))
                    <div class="rounded-lg border border-line p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium">
                                    {{ $historyVersion->label() }}
                                    <span class="ml-1 text-xs font-normal text-muted">
                                        {{ $historyVersion->isFinal() ? 'Final' : 'Draft' }}
                                    </span>
                                </p>
                                <p class="mt-0.5 text-xs text-muted">
                                    {{ \App\Support\Format::tanggal($historyVersion->lhstp_date) }}
                                    &middot; {{ \App\Support\Format::rupiah($historyVersion->total_payment, 2) }}
                                    &middot; {{ $historyVersion->creator?->name ?? '-' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($documents as $document)
                                    <a href="{{ route('documents.download', $document) }}"
                                       class="text-sm font-medium text-primary hover:underline">
                                        Unduh {{ $document->document_format }}
                                    </a>
                                @endforeach

                                @if ($historyVersion->isFinal())
                                    @foreach ([\App\Models\GeneratedDocument::FORMAT_PDF, \App\Models\GeneratedDocument::FORMAT_DOCX] as $format)
                                        @unless ($documents->contains('document_format', $format))
                                            <form method="POST" action="{{ route('lhstp.documents.store', $lhstp) }}">
                                                @csrf
                                                <input type="hidden" name="document_format" value="{{ $format }}">
                                                <button type="submit" class="text-sm font-medium text-muted hover:underline">
                                                    Buat {{ $format }}
                                                </button>
                                            </form>
                                        @endunless
                                    @endforeach
                                @endif
                            </div>
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
                            &middot; {{ $log->user?->name ?? 'Sistem' }}
                        </p>
                    </li>
                @empty
                    <li class="text-sm text-muted">Belum ada aktivitas.</li>
                @endforelse
            </ol>
        </section>
    </div>
@endsection
