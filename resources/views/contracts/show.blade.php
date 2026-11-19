@extends('layouts.app')

@section('title', 'Detail Kontrak')

@php
    $durationDays = $contract->start_date && $contract->end_date
        ? (int) $contract->start_date->diffInDays($contract->end_date) + 1
        : null;
    $terminTotal = \App\Support\Money::sum($contract->termins->pluck('nominal_amount')->all());
@endphp

@section('content')
    <x-page-header :title="$contract->po_number" :description="$contract->work_name">
        <x-slot:action>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('contracts.edit', $contract) }}" class="btn btn-primary">Ubah Kontrak</a>

                @if ($contract->isSelectableForLhstp())
                    <a href="{{ route('lhstp.create', ['contract_id' => $contract->id]) }}" class="btn btn-secondary">Buat LHSTP</a>
                @endif

                @if ($contract->isArchived())
                    <form method="POST" action="{{ route('contracts.unarchive', $contract) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary">Batal Arsip</button>
                    </form>
                @else
                    <details class="relative">
                        <summary class="btn btn-secondary cursor-pointer list-none">Arsipkan</summary>
                        <div class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-line bg-surface p-4 shadow-lg">
                            <p class="text-sm font-medium">Arsipkan kontrak ini?</p>
                            <p class="mt-1 text-xs text-muted">Kontrak Archived tidak dapat dipakai untuk LHSTP baru. Status ini tidak berubah otomatis.</p>
                            <form method="POST" action="{{ route('contracts.archive', $contract) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full">Ya, arsipkan</button>
                            </form>
                        </div>
                    </details>
                @endif

                <details class="relative">
                    <summary class="btn btn-secondary cursor-pointer list-none text-danger">Hapus</summary>
                    <div class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-line bg-surface p-4 shadow-lg">
                        <p class="text-sm font-medium">Hapus kontrak {{ $contract->po_number }}?</p>
                        <p class="mt-1 text-xs text-muted">Kontrak dipindahkan ke Recycle Bin selama 30 hari.</p>
                        <form method="POST" action="{{ route('contracts.destroy', $contract) }}" class="mt-3">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn w-full bg-danger text-white hover:bg-danger/90">Ya, hapus</button>
                        </form>
                    </div>
                </details>
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
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Data Kontrak</h3>

            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-muted">Nomor PO</dt>
                    <dd class="mt-0.5 text-sm font-medium">{{ $contract->po_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Tanggal kontrak</dt>
                    <dd class="mt-0.5 text-sm">{{ \App\Support\Format::tanggal($contract->contract_date) }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-muted">Nama pekerjaan</dt>
                    <dd class="mt-0.5 text-sm">{{ $contract->work_name }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-muted">Vendor</dt>
                    <dd class="mt-0.5 text-sm">
                        {{ $contract->vendor?->company_name ?? '—' }}
                        @if ($contract->vendor?->company_address)
                            <span class="mt-0.5 block text-xs text-muted">{{ $contract->vendor->company_address }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Nama perwakilan</dt>
                    <dd class="mt-0.5 text-sm">{{ $contract->representative_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Jabatan perwakilan</dt>
                    <dd class="mt-0.5 text-sm">{{ $contract->representative_position }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Periode kontrak</dt>
                    <dd class="mt-0.5 text-sm">
                        {{ \App\Support\Format::tanggal($contract->start_date) }} –
                        {{ \App\Support\Format::tanggal($contract->end_date) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Durasi</dt>
                    <dd class="mt-0.5 text-sm">{{ $durationDays !== null ? $durationDays.' hari' : '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-line bg-surface p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Status dan Nilai</h3>

            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Status</span>
                    <x-document-status :status="$contract->status" />
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Nilai kontrak</span>
                    <span class="font-semibold tabular-nums">{{ \App\Support\Format::rupiah($contract->contract_value, 2) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Status PPN</span>
                    <span>{{ $contract->ppn_status }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Metode pembayaran</span>
                    <span>{{ $contract->payment_method }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Dibuat</span>
                    <span>{{ \App\Support\Format::tanggalWaktu($contract->created_at) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">Diperbarui</span>
                    <span>{{ \App\Support\Format::tanggalWaktu($contract->updated_at) }}</span>
                </div>
                @if ($contract->archived_at)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Diarsipkan</span>
                        <span>{{ \App\Support\Format::tanggalWaktu($contract->archived_at) }}</span>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Jadwal Pembayaran</h3>

        @if ($contract->payment_method === \App\Models\Contract::PAYMENT_MONTHLY)
            <div class="rounded-lg border border-line bg-app px-4 py-3">
                <p class="text-xs text-muted">Nominal pembayaran bulanan</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ \App\Support\Format::rupiah($contract->monthly_payment_amount, 2) }}</p>
            </div>
        @elseif ($contract->payment_method === \App\Models\Contract::PAYMENT_FULL)
            <div class="rounded-lg border border-line bg-app px-4 py-3">
                <p class="text-xs text-muted">Nominal full financing</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ \App\Support\Format::rupiah($contract->full_financing_amount, 2) }}</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm">
                    <thead class="bg-app">
                        <tr>
                            <th scope="col" class="table-head w-12">No</th>
                            <th scope="col" class="table-head">Nama Termin</th>
                            <th scope="col" class="table-head text-right">Persentase</th>
                            <th scope="col" class="table-head text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($contract->termins as $index => $termin)
                            <tr>
                                <td class="table-cell text-muted">{{ $index + 1 }}</td>
                                <td class="table-cell">{{ $termin->termin_name }}</td>
                                <td class="table-cell text-right tabular-nums">{{ rtrim(rtrim((string) $termin->percentage, '0'), '.') }}%</td>
                                <td class="table-cell text-right tabular-nums">{{ \App\Support\Format::rupiah($termin->nominal_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-muted">Belum ada jadwal termin.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t border-line bg-app">
                        <tr>
                            <th scope="row" colspan="2" class="table-cell text-left text-sm font-semibold">Total</th>
                            <td class="table-cell text-right text-sm font-semibold tabular-nums">100%</td>
                            <td class="table-cell text-right text-sm font-semibold tabular-nums">{{ \App\Support\Format::rupiah($terminTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Lampiran Kontrak</h3>

        @if ($contract->attachments->isEmpty())
            <p class="text-sm text-muted">Belum ada lampiran.</p>
        @else
            <ul class="divide-y divide-line rounded-lg border border-line">
                @foreach ($contract->attachments as $attachment)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ $attachment->original_filename }}</p>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ number_format($attachment->file_size / 1024, 0, ',', '.') }} KB
                                · diunggah {{ \App\Support\Format::tanggalWaktu($attachment->uploaded_at) }}
                                @if ($attachment->uploader) oleh {{ $attachment->uploader->name }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('contracts.attachments.download', [$contract, $attachment]) }}"
                               class="text-sm font-medium text-primary hover:underline">Buka</a>
                            <form method="POST" action="{{ route('contracts.attachments.destroy', [$contract, $attachment]) }}"
                                  onsubmit="return confirm('Hapus lampiran ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-danger hover:underline">Hapus</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('contracts.attachments.store', $contract) }}"
              enctype="multipart/form-data" class="mt-5 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-64 flex-1">
                <label for="attachment-upload" class="mb-1.5 block text-sm font-medium">Tambah lampiran PDF</label>
                <input id="attachment-upload" name="attachments[]" type="file" accept="application/pdf" multiple
                       class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-app file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink">
                <p class="mt-1.5 text-xs text-muted">Hanya PDF, maksimal 10 MB per berkas.</p>
            </div>
            <button type="submit" class="btn btn-secondary">Unggah</button>
        </form>
    </section>

    <section class="mt-6 rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Riwayat Perubahan Kontrak</h3>

        <ol class="space-y-3">
            @forelse ($activityLogs as $log)
                @php
                    $actionLabel = match ($log->action) {
                        'created' => 'Kontrak dibuat',
                        'updated' => 'Kontrak diperbarui',
                        'archived' => 'Kontrak diarsipkan',
                        'unarchived' => 'Arsip dibatalkan',
                        default => ucfirst($log->action),
                    };
                    $formatValue = function (string $key, $value) {
                        if ($value === null || $value === '') {
                            return '—';
                        }
                        if ($key === 'termins') {
                            return 'Jadwal termin diperbarui';
                        }
                        if ($key === 'vendor_id') {
                            return \App\Models\Vendor::query()->find($value)?->company_name ?? '#'.$value;
                        }
                        if (in_array($key, ['contract_value', 'monthly_payment_amount', 'full_financing_amount'], true)) {
                            return \App\Support\Format::rupiah($value, 2);
                        }
                        if (in_array($key, ['contract_date', 'start_date', 'end_date'], true)) {
                            return \App\Support\Format::tanggal($value);
                        }
                        return (string) $value;
                    };
                @endphp
                <li class="border-b border-line pb-3 last:border-0 last:pb-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-medium">{{ $actionLabel }}</p>
                        <p class="text-xs text-muted">
                            {{ \App\Support\Format::tanggalWaktu($log->created_at) }}
                            · {{ $log->user?->name ?? 'Sistem' }}
                        </p>
                    </div>

                    @if ($log->action === 'updated')
                        <ul class="mt-1.5 space-y-0.5 text-xs text-muted">
                            @foreach ($log->after_values ?? [] as $key => $value)
                                <li>
                                    {{ \App\Models\ContractActivityLog::FIELD_LABELS[$key] ?? $key }}:
                                    {{ $formatValue($key, $log->before_values[$key] ?? null) }}
                                    → {{ $formatValue($key, $value) }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @empty
                <li class="text-sm text-muted">Belum ada riwayat perubahan.</li>
            @endforelse
        </ol>
    </section>
@endsection
