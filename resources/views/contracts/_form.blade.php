@php
    $paymentMethod = old('payment_method', $contract->payment_method ?: \App\Models\Contract::PAYMENT_MONTHLY);

    $storedTermins = old('termins');

    if ($storedTermins === null) {
        $storedTermins = $contract->exists
            ? $contract->termins->map(fn ($termin) => [
                'termin_name' => $termin->termin_name,
                'percentage' => (string) $termin->percentage,
            ])->all()
            : [['termin_name' => 'Termin 1', 'percentage' => '']];
    }

    $storedTermins = array_values($storedTermins);

    if ($storedTermins === []) {
        $storedTermins = [['termin_name' => 'Termin 1', 'percentage' => '']];
    }

    $methodOptions = \App\Models\Contract::PAYMENT_METHODS;
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6" id="contract-form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Identitas Kontrak</h3>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="po_number" label="PO Number" required help="Unik. Nomor PO yang sudah dipakai akan ditolak.">
                <input id="po_number" name="po_number" type="text" required maxlength="100"
                       value="{{ old('po_number', $contract->po_number) }}"
                       @error('po_number') aria-invalid="true" aria-describedby="po_number-error" @enderror
                       class="input @error('po_number') input-error @enderror">
            </x-field>

            <x-field name="contract_date" label="Tanggal Kontrak" required>
                <input id="contract_date" name="contract_date" type="date" required
                       value="{{ old('contract_date', $contract->contract_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                       @error('contract_date') aria-invalid="true" aria-describedby="contract_date-error" @enderror
                       class="input @error('contract_date') input-error @enderror">
            </x-field>

            <div class="sm:col-span-2">
                <x-field name="purchase_request_id" label="PR Terkait"
                         help="Opsional. Satu kontrak/PO hanya dapat terkait satu PR.">
                    <select id="purchase_request_id" name="purchase_request_id"
                            @error('purchase_request_id') aria-invalid="true" aria-describedby="purchase_request_id-error" @enderror
                            class="input input-select @error('purchase_request_id') input-error @enderror">
                        <option value="">Tanpa PR</option>
                        @foreach ($purchaseRequests as $relatedPr)
                            <option value="{{ $relatedPr->id }}"
                                @selected((string) old('purchase_request_id', $contract->purchase_request_id) === (string) $relatedPr->id)>
                                {{ $relatedPr->pr_number }} — {{ $relatedPr->currentVersion?->subject ?? 'Tanpa perihal' }}
                            </option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <div class="sm:col-span-2">
                <x-field name="work_name" label="Nama Pekerjaan" required>
                    <input id="work_name" name="work_name" type="text" required maxlength="255"
                           value="{{ old('work_name', $contract->work_name) }}"
                           @error('work_name') aria-invalid="true" aria-describedby="work_name-error" @enderror
                           class="input @error('work_name') input-error @enderror">
                </x-field>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Vendor dan Perwakilan</h3>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-field name="vendor_id" label="Vendor" required help="Perwakilan vendor disimpan per kontrak, bukan di Master Vendor.">
                    <select id="vendor_id" name="vendor_id" required
                            @error('vendor_id') aria-invalid="true" aria-describedby="vendor_id-error" @enderror
                            class="input input-select @error('vendor_id') input-error @enderror">
                        <option value="">Pilih vendor</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected((string) old('vendor_id', $contract->vendor_id) === (string) $vendor->id)>
                                {{ $vendor->company_name }}
                            </option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <x-field name="representative_name" label="Nama Perwakilan Vendor" required>
                <input id="representative_name" name="representative_name" type="text" required maxlength="200"
                       value="{{ old('representative_name', $contract->representative_name) }}"
                       class="input @error('representative_name') input-error @enderror">
            </x-field>

            <x-field name="representative_position" label="Jabatan Perwakilan" required>
                <input id="representative_position" name="representative_position" type="text" required maxlength="200"
                       value="{{ old('representative_position', $contract->representative_position) }}"
                       class="input @error('representative_position') input-error @enderror">
            </x-field>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Periode Kontrak</h3>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="start_date" label="Tanggal Mulai" required>
                <input id="start_date" name="start_date" type="date" required
                       value="{{ old('start_date', $contract->start_date?->format('Y-m-d')) }}"
                       class="input @error('start_date') input-error @enderror">
            </x-field>

            <x-field name="end_date" label="Tanggal Berakhir" required help="Harus sama atau setelah tanggal mulai. Kontrak otomatis Nonaktif setelah tanggal ini.">
                <input id="end_date" name="end_date" type="date" required
                       value="{{ old('end_date', $contract->end_date?->format('Y-m-d')) }}"
                       class="input @error('end_date') input-error @enderror">
            </x-field>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Ketentuan Finansial</h3>

        <div class="grid gap-5 sm:grid-cols-3">
            <x-field name="contract_value" label="Nilai Kontrak" required>
                <input id="contract_value" name="contract_value" type="number" min="0.01" step="0.01" required
                       value="{{ old('contract_value', $contract->contract_value) }}"
                       class="input @error('contract_value') input-error @enderror">
            </x-field>

            <x-field name="ppn_status" label="Status PPN" required>
                <select id="ppn_status" name="ppn_status" required
                        class="input input-select @error('ppn_status') input-error @enderror">
                    @foreach (\App\Models\Contract::PPN_STATUSES as $option)
                        <option value="{{ $option }}" @selected(old('ppn_status', $contract->ppn_status) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="payment_method" label="Metode Pembayaran" required>
                <select id="payment_method" name="payment_method" required
                        class="input input-select @error('payment_method') input-error @enderror">
                    @foreach ($methodOptions as $option)
                        <option value="{{ $option }}" @selected($paymentMethod === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Jadwal Pembayaran</h3>

        <div id="monthly-fields" data-payment-block="Bulanan"
             class="{{ $paymentMethod === \App\Models\Contract::PAYMENT_MONTHLY ? '' : 'hidden' }}">
            <x-field name="monthly_payment_amount" label="Nominal Pembayaran Bulanan" help="Wajib diisi untuk metode Bulanan.">
                <input id="monthly_payment_amount" name="monthly_payment_amount" type="number" min="0.01" step="0.01"
                       value="{{ old('monthly_payment_amount', $contract->monthly_payment_amount) }}"
                       data-payment-input="Bulanan"
                       class="input max-w-xs @error('monthly_payment_amount') input-error @enderror">
            </x-field>
        </div>

        <div id="full-fields" data-payment-block="Full Financing"
             class="{{ $paymentMethod === \App\Models\Contract::PAYMENT_FULL ? '' : 'hidden' }}">
            <x-field name="full_financing_amount" label="Nominal Full Financing" help="Wajib diisi untuk metode Full Financing.">
                <input id="full_financing_amount" name="full_financing_amount" type="number" min="0.01" step="0.01"
                       value="{{ old('full_financing_amount', $contract->full_financing_amount) }}"
                       data-payment-input="Full Financing"
                       class="input max-w-xs @error('full_financing_amount') input-error @enderror">
            </x-field>
        </div>

        <div id="termin-fields" data-payment-block="Termin"
             class="{{ $paymentMethod === \App\Models\Contract::PAYMENT_TERMIN ? '' : 'hidden' }}">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium">Jadwal Termin</p>
                    <p class="mt-1 text-xs text-muted">Total persentase harus tepat 100%. Nominal dihitung dari Nilai Kontrak; sisa pembulatan masuk ke termin terakhir.</p>
                </div>
                <button type="button" id="add-termin" class="btn btn-secondary">Tambah Termin</button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-line">
                <table class="min-w-full text-sm" id="termin-table">
                    <thead class="bg-app">
                        <tr>
                            <th scope="col" class="table-head w-12">No</th>
                            <th scope="col" class="table-head">Nama Termin</th>
                            <th scope="col" class="table-head w-40">Persentase (%)</th>
                            <th scope="col" class="table-head text-right">Nominal</th>
                            <th scope="col" class="table-head w-16"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody id="termin-body" class="divide-y divide-line">
                        @foreach ($storedTermins as $index => $termin)
                            <tr data-termin-row>
                                <td class="table-cell text-muted" data-termin-number>{{ $index + 1 }}</td>
                                <td class="table-cell">
                                    <input name="termins[{{ $index }}][termin_name]" data-termin-field="termin_name"
                                           type="text" value="{{ $termin['termin_name'] ?? '' }}" maxlength="120"
                                           aria-label="Nama termin {{ $index + 1 }}" class="input">
                                </td>
                                <td class="table-cell">
                                    <input name="termins[{{ $index }}][percentage]" data-termin-field="percentage"
                                           type="number" min="0.0001" max="100" step="0.0001"
                                           value="{{ $termin['percentage'] ?? '' }}"
                                           aria-label="Persentase termin {{ $index + 1 }}" class="input">
                                </td>
                                <td class="table-cell text-right tabular-nums text-muted" data-termin-nominal>—</td>
                                <td class="table-cell text-right">
                                    <button type="button" data-remove-termin class="text-sm font-medium text-danger hover:underline">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-sm">
                Total persentase:
                <span id="termin-total" class="font-semibold tabular-nums">0</span>%
            </p>
            @error('termins')
                <p role="alert" class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
            @error('termins.*.percentage')
                <p role="alert" class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Lampiran Kontrak</h3>

        @if ($contract->exists && $contract->attachments->isNotEmpty())
            <ul class="mb-5 divide-y divide-line rounded-lg border border-line">
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

        <x-field name="attachments" label="Tambah Lampiran PDF" help="Boleh lebih dari satu berkas. Hanya PDF, maksimal 10 MB per berkas.">
            <input id="attachments" name="attachments[]" type="file" accept="application/pdf" multiple
                   class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-app file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink">
        </x-field>

        @error('attachments.*')
            <p role="alert" class="mt-1 text-sm text-danger">{{ $message }}</p>
        @enderror
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
            <a href="{{ $contract->exists ? route('contracts.show', $contract) : route('contracts.index') }}"
               class="btn btn-secondary">Batal</a>
        </div>
    </section>
</form>

<template id="termin-row-template">
    <tr data-termin-row>
        <td class="table-cell text-muted" data-termin-number></td>
        <td class="table-cell">
            <input name="termins[__INDEX__][termin_name]" data-termin-field="termin_name" type="text" maxlength="120" class="input">
        </td>
        <td class="table-cell">
            <input name="termins[__INDEX__][percentage]" data-termin-field="percentage" type="number" min="0.0001" max="100" step="0.0001" class="input">
        </td>
        <td class="table-cell text-right tabular-nums text-muted" data-termin-nominal>—</td>
        <td class="table-cell text-right">
            <button type="button" data-remove-termin class="text-sm font-medium text-danger hover:underline">Hapus</button>
        </td>
    </tr>
</template>
