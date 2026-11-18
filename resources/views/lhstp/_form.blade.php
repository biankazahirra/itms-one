@php
    $value = $version;
    $prefill = $prefill ?? [];
    $dateValue = fn ($date) => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : ($date ?? '');
    $field = fn (string $key, $fallback = null) => old($key, $value?->{$key} ?? $prefill[$key] ?? $fallback);
    $contractId = (string) old('contract_id', $lhstp->contract_id ?: $selectedContract?->id);
    $selected = $contracts->firstWhere('id', (int) $contractId);
    $terminId = old('contract_termin_id', $value?->termin?->contract_termin_id);
    $types = \App\Models\Lhstp::REFERENCE_TYPES;
    $referenceType = old('reference_document_type', $value?->reference_document_type ?? \App\Models\Lhstp::REFERENCE_BERITA_ACARA);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" id="lhstp-form">
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
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Kontrak dan Identitas LHSTP</h3>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-field name="contract_id" label="Kontrak" required
                         help="Kontrak Arsip tidak dapat dipilih. Kontrak Nonaktif tetap dapat dipakai.">
                    <select id="contract_id" name="contract_id" required
                            @if ($version === null && ! $lhstp->exists) onchange="if (this.value) { window.location = '{{ route('lhstp.create') }}?contract_id=' + this.value; }" @endif
                            @error('contract_id') aria-invalid="true" aria-describedby="contract_id-error" @enderror
                            class="input input-select @error('contract_id') input-error @enderror">
                        <option value="">Pilih kontrak</option>
                        @foreach ($contracts as $contract)
                            <option value="{{ $contract->id }}" @selected($contractId === (string) $contract->id)>
                                {{ $contract->po_number }} — {{ $contract->work_name }} ({{ $contract->vendor?->company_name }})
                            </option>
                        @endforeach
                    </select>
                    @if ($selected)
                        <p class="mt-2 text-xs text-muted">
                            {{ $selected->vendor?->company_name }} &middot;
                            Nilai kontrak {{ \App\Support\Format::rupiah($selected->contract_value, 2) }} &middot;
                            {{ $selected->ppn_status }} &middot;
                            Pembayaran {{ $selected->payment_method }}
                        </p>
                    @endif
                </x-field>
            </div>

            <x-field name="lhstp_number" label="Nomor LHSTP" required help="Unik antar LHSTP. Revisi memakai nomor yang sama.">
                <input id="lhstp_number" name="lhstp_number" type="text" required maxlength="100"
                       value="{{ old('lhstp_number', $lhstp->lhstp_number) }}"
                       @error('lhstp_number') aria-invalid="true" aria-describedby="lhstp_number-error" @enderror
                       class="input @error('lhstp_number') input-error @enderror">
            </x-field>

            <x-field name="lhstp_date" label="Tanggal LHSTP" required>
                <input id="lhstp_date" name="lhstp_date" type="date" required
                       value="{{ $dateValue(old('lhstp_date', $value?->lhstp_date ?? now())) }}"
                       class="input @error('lhstp_date') input-error @enderror">
            </x-field>

            <x-field name="work_period" label="Periode Pekerjaan" required help="Contoh: November 2026.">
                <input id="work_period" name="work_period" type="text" required maxlength="150"
                       value="{{ $field('work_period') }}"
                       class="input @error('work_period') input-error @enderror">
            </x-field>

            <x-field name="opname_date" label="Tanggal Opname" required help="Default mengikuti Tanggal LHSTP, dapat diubah.">
                <input id="opname_date" name="opname_date" type="date" required
                       value="{{ $dateValue($field('opname_date', now())) }}"
                       class="input @error('opname_date') input-error @enderror">
            </x-field>

            <x-field name="signature_date" label="Tanggal Tanda Tangan" required help="Default mengikuti Tanggal LHSTP, dapat diubah.">
                <input id="signature_date" name="signature_date" type="date" required
                       value="{{ $dateValue($field('signature_date', now())) }}"
                       class="input @error('signature_date') input-error @enderror">
            </x-field>

            <x-field name="contract_termin_id" label="Termin Kontrak"
                     help="Wajib untuk kontrak dengan metode pembayaran Termin.">
                <select id="contract_termin_id" name="contract_termin_id"
                        @error('contract_termin_id') aria-invalid="true" aria-describedby="contract_termin_id-error" @enderror
                        class="input input-select @error('contract_termin_id') input-error @enderror">
                    <option value="">Tanpa termin</option>
                    @foreach ($termins as $contractKey => $contractTermins)
                        @foreach ($contractTermins as $termin)
                            <option value="{{ $termin['id'] }}"
                                    data-contract="{{ $contractKey }}"
                                    @selected((string) $terminId === (string) $termin['id'])>
                                {{ $termin['name'] }} — {{ \App\Support\Format::rupiah($termin['nominal'], 2) }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
            </x-field>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Realisasi Pekerjaan</h3>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="realization_start" label="Tanggal Mulai Realisasi" required>
                <input id="realization_start" name="realization_start" type="date" required
                       value="{{ $dateValue($field('realization_start')) }}"
                       class="input @error('realization_start') input-error @enderror">
            </x-field>

            <x-field name="realization_end" label="Tanggal Selesai Realisasi" required
                     help="Tidak boleh lebih awal dari tanggal mulai. Di luar periode kontrak hanya memunculkan peringatan.">
                <input id="realization_end" name="realization_end" type="date" required
                       value="{{ $dateValue($field('realization_end')) }}"
                       class="input @error('realization_end') input-error @enderror">
            </x-field>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Dokumen Referensi</h3>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="reference_document_type" label="Jenis Dokumen Referensi" required>
                <select id="reference_document_type" name="reference_document_type" required
                        data-po-number="{{ $selectedContract?->po_number }}"
                        data-po-date="{{ $selectedContract?->contract_date?->format('Y-m-d') }}"
                        class="input input-select @error('reference_document_type') input-error @enderror">
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected($referenceType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="reference_document_name" label="Nama Dokumen Lainnya"
                     help="Wajib bila jenis dokumen adalah Dokumen Lainnya.">
                <input id="reference_document_name" name="reference_document_name" type="text" maxlength="200"
                       value="{{ $field('reference_document_name') }}"
                       class="input @error('reference_document_name') input-error @enderror">
            </x-field>

            <x-field name="reference_document_number" label="Nomor Dokumen Referensi" required>
                <input id="reference_document_number" name="reference_document_number" type="text" required maxlength="150"
                       value="{{ $field('reference_document_number') }}"
                       class="input @error('reference_document_number') input-error @enderror">
            </x-field>

            <x-field name="reference_document_date" label="Tanggal Dokumen Referensi" required>
                <input id="reference_document_date" name="reference_document_date" type="date" required
                       value="{{ $dateValue($field('reference_document_date')) }}"
                       class="input @error('reference_document_date') input-error @enderror">
            </x-field>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Pembayaran</h3>

        <div class="grid gap-5 sm:grid-cols-3">
            <x-field name="payment_amount" label="Nilai Pembayaran" required help="Wajib lebih dari nol.">
                <input id="payment_amount" name="payment_amount" type="number" step="0.01" min="0.01" required
                       value="{{ $field('payment_amount') }}"
                       class="input @error('payment_amount') input-error @enderror">
            </x-field>

            <x-field name="ppn_rate" label="Tarif PPN (%)" required help="Default 12. Dapat disesuaikan.">
                <input id="ppn_rate" name="ppn_rate" type="number" step="0.0001" min="0" max="100" required
                       value="{{ $field('ppn_rate', \App\Models\Lhstp::DEFAULT_PPN_RATE) }}"
                       class="input @error('ppn_rate') input-error @enderror">
            </x-field>

            <x-field name="cost_center" label="Cost Center" required help="Default 3111440.">
                <input id="cost_center" name="cost_center" type="text" required maxlength="40"
                       value="{{ $field('cost_center', \App\Models\Lhstp::DEFAULT_COST_CENTER) }}"
                       class="input @error('cost_center') input-error @enderror">
            </x-field>
        </div>

        <div class="mt-5 rounded-lg border border-line bg-app px-4 py-3 text-sm">
            <p class="text-muted">
                DPP LAIN dan PPN dihitung otomatis mengikuti status PPN kontrak. Hanya Total Pembayaran
                yang dibulatkan ke rupiah penuh.
            </p>
        </div>

        <label class="mt-5 flex items-start gap-2 text-sm">
            <input type="checkbox" name="confirm_exceed" value="1" @checked(old('confirm_exceed'))
                   class="mt-0.5 size-4 rounded border-line text-primary focus:ring-primary/20">
            <span>Total pembayaran boleh melebihi nilai kontrak (konfirmasi).</span>
        </label>
        @error('confirm_exceed')
            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
        @enderror
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Kesimpulan dan Ketentuan Pembayaran</h3>

        <div class="space-y-5">
            <x-field name="work_conclusion" label="Kesimpulan Pekerjaan" required
                     help="Terisi otomatis dari kontrak dan periode. Dapat diubah untuk kasus khusus.">
                <textarea id="work_conclusion" name="work_conclusion" rows="3" required
                          class="input @error('work_conclusion') input-error @enderror">{{ $field('work_conclusion') }}</textarea>
            </x-field>

            <x-field name="payment_terms" label="Ketentuan Pembayaran" required
                     help="Terisi otomatis mengikuti metode pembayaran kontrak. Dapat diubah untuk kasus khusus.">
                <textarea id="payment_terms" name="payment_terms" rows="3" required
                          class="input @error('payment_terms') input-error @enderror">{{ $field('payment_terms') }}</textarea>
            </x-field>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="btn btn-primary">Simpan Draft</button>
        <a href="{{ $lhstp->exists ? route('lhstp.show', $lhstp) : route('lhstp.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>

<script>
    (function () {
        var lhstpDate = document.getElementById('lhstp_date');
        var opnameDate = document.getElementById('opname_date');
        var signatureDate = document.getElementById('signature_date');

        if (!lhstpDate || !opnameDate || !signatureDate) {
            return;
        }

        // Tanggal opname dan tanda tangan mengikuti tanggal LHSTP.
        lhstpDate.addEventListener('change', function () {
            if (!lhstpDate.value) {
                return;
            }

            opnameDate.value = lhstpDate.value;
            signatureDate.value = lhstpDate.value;
        });

        var referenceType = document.getElementById('reference_document_type');
        var referenceNumber = document.getElementById('reference_document_number');
        var referenceDate = document.getElementById('reference_document_date');

        if (referenceType && referenceNumber && referenceDate) {
            // Surat Purchase Order memakai nomor dan tanggal kontrak terpilih.
            referenceType.addEventListener('change', function () {
                if (referenceType.value !== @json(\App\Models\Lhstp::REFERENCE_PURCHASE_ORDER)) {
                    return;
                }

                referenceNumber.value = referenceType.dataset.poNumber || '';
                referenceDate.value = referenceType.dataset.poDate || '';
            });
        }
    })();
</script>

@if ($version === null && ! $lhstp->exists)
    <script>
        (function () {
            var contract = document.getElementById('contract_id');
            var termin = document.getElementById('contract_termin_id');

            if (!contract || !termin) {
                return;
            }

            function filterTermins() {
                var selected = contract.value;

                Array.prototype.forEach.call(termin.options, function (option) {
                    if (!option.value) {
                        return;
                    }

                    var matches = option.dataset.contract === selected;
                    option.hidden = !matches;
                    option.disabled = !matches;

                    if (!matches && option.selected) {
                        termin.value = '';
                    }
                });
            }

            contract.addEventListener('change', filterTermins);
            filterTermins();
        })();
    </script>
@endif
