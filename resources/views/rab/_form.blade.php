@php
    $storedItems = old('items');
    $currentVersion = $budget->exists ? $budget->currentVersion : null;
    $importMode = ! $isEdit && $prefillPr !== null;

    if ($storedItems === null) {
        $storedItems = $currentVersion
            ? $currentVersion->items->map(fn ($item) => [
                'budget_name' => $item->budget_name,
                'description' => $item->description,
                'quantity' => \App\Support\Money::quantity($item->quantity),
                'unit_id' => $item->unit_id,
                'unit_price' => $item->unit_price,
            ])->all()
            : [['budget_name' => '', 'description' => '', 'quantity' => '', 'unit_id' => '', 'unit_price' => '']];
    }

    $storedItems = array_values($storedItems);
    $linkedPr = $budget->exists ? $budget->purchaseRequest : $prefillPr;
    $rabTotal = $currentVersion?->grand_total ?? '0';
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" id="rab-form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Informasi RAB</h3>

        <div class="space-y-5">
            @if (! $isEdit)
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Cara pembuatan</legend>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-4 py-2 text-sm {{ $importMode ? '' : 'border-primary bg-primary/5 text-primary' }}" data-mode-option="standalone">
                            <input type="radio" name="creation_mode" value="standalone" @checked(! $importMode) class="accent-primary">
                            RAB Mandiri
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-4 py-2 text-sm {{ $importMode ? 'border-primary bg-primary/5 text-primary' : '' }}" data-mode-option="import">
                            <input type="radio" name="creation_mode" value="import" @checked($importMode) class="accent-primary">
                            Dari PR
                        </label>
                    </div>
                </fieldset>
            @endif

            <div id="import-fields" class="{{ $importMode ? '' : 'hidden' }} space-y-3">
                <x-field name="purchase_request_id" label="PR terkait" required help="Satu RAB hanya boleh terhubung ke satu PR. Semua item PR diimpor.">
                    <select id="purchase_request_id" name="purchase_request_id" @disabled(! $importMode)
                            @error('purchase_request_id') aria-invalid="true" aria-describedby="purchase_request_id-error" @enderror
                            class="input input-select @error('purchase_request_id') input-error @enderror">
                        <option value="">Pilih PR</option>
                        @foreach ($prOptions ?? [] as $pr)
                            <option value="{{ $pr->id }}" @selected((string) old('purchase_request_id', $prefillPr?->id) === (string) $pr->id)>
                                {{ $pr->pr_number }} — {{ $pr->currentVersion?->subject ?? '(tanpa versi)' }}
                                @if ($pr->currentVersion)
                                    ({{ \App\Support\Format::rupiah($pr->currentVersion->grand_total, 2) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('purchase_request_id')
                        <p role="alert" id="purchase_request_id-error" class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </x-field>
                <p class="text-xs text-muted">Harga RAB disalin langsung dari Harga Satuan PR yang tersimpan. Total RAB wajib sama dengan total PR sebelum RAB difinalisasi.</p>
            </div>

            <div id="standalone-fields" class="{{ $importMode ? 'hidden' : '' }} grid gap-5 sm:grid-cols-2">
                @if ($linkedPr)
                    <div class="sm:col-span-2 rounded-lg border border-line bg-app px-4 py-3 text-sm">
                        <span class="text-muted">PR terkait:</span>
                        <a href="{{ route('purchase-requests.show', $linkedPr) }}" class="font-medium text-primary hover:underline">
                            {{ $linkedPr->pr_number }}@if ($linkedPr->currentVersion) — {{ $linkedPr->currentVersion->subject }}@endif
                        </a>
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <x-field name="work_name" label="Nama pekerjaan" required>
                        <input id="work_name" name="work_name" type="text" required maxlength="255"
                               value="{{ old('work_name', $currentVersion?->work_name) }}"
                               @error('work_name') aria-invalid="true" aria-describedby="work_name-error" @enderror
                               class="input @error('work_name') input-error @enderror">
                    </x-field>
                </div>

                <x-field name="rab_date" label="Tanggal" required>
                    <input id="rab_date" name="rab_date" type="date" required
                           value="{{ old('rab_date', $currentVersion?->rab_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                           @error('rab_date') aria-invalid="true" aria-describedby="rab_date-error" @enderror
                           class="input @error('rab_date') input-error @enderror">
                </x-field>

                <x-field name="location" label="Lokasi">
                    <input id="location" type="text" value="{{ \App\Models\Budget::LOCATION }}" readonly
                           class="input bg-app text-muted">
                </x-field>
            </div>
        </div>
    </section>

    <section id="items-section" class="{{ $importMode ? 'hidden' : '' }} rounded-xl border border-line bg-surface p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-muted">Rincian Anggaran</h3>
                <p class="mt-1 text-xs text-muted">Minimal satu baris. Unit wajib diisi. Jumlah dihitung otomatis dari Qty × Harga.</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2">
                    <input type="hidden" name="_token" id="quick_add_csrf" value="{{ csrf_token() }}">
                    <input id="quick_add_unit_name" name="unit_name" type="text" maxlength="80" placeholder="Unit baru…"
                           aria-label="Tambah unit baru" class="input w-36">
                    <button type="button" id="quick-add-unit-button" class="btn btn-secondary">+ Unit</button>
                </div>
                <button type="button" id="add-rab-item" class="btn btn-secondary">Tambah Baris</button>
            </div>
        </div>

        @error('items')
            <p role="alert" class="mb-3 text-sm text-danger">{{ $message }}</p>
        @enderror

        <div class="overflow-x-auto rounded-lg border border-line">
            <table class="min-w-full text-sm" id="rab-items-table">
                <thead class="bg-app">
                    <tr>
                        <th scope="col" class="table-head w-12">No</th>
                        <th scope="col" class="table-head">Nama Anggaran</th>
                        <th scope="col" class="table-head">Deskripsi</th>
                        <th scope="col" class="table-head w-24">Qty</th>
                        <th scope="col" class="table-head w-32">Unit</th>
                        <th scope="col" class="table-head w-36">Harga</th>
                        <th scope="col" class="table-head w-36 text-right">Jumlah</th>
                        <th scope="col" class="table-head w-12"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody id="rab-items-body" class="divide-y divide-line">
                    @foreach ($storedItems as $index => $item)
                        <tr data-item-row>
                            <td class="table-cell text-muted" data-row-number>{{ $index + 1 }}</td>
                            <td class="table-cell">
                                <input name="items[{{ $index }}][budget_name]" data-field="budget_name" type="text"
                                       value="{{ $item['budget_name'] ?? '' }}" maxlength="500" required
                                       aria-label="Nama anggaran baris {{ $index + 1 }}" class="input">
                                @error("items.{$index}.budget_name")
                                    <p role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="table-cell">
                                <input name="items[{{ $index }}][description]" data-field="description" type="text"
                                       value="{{ $item['description'] ?? '' }}" maxlength="2000"
                                       aria-label="Deskripsi baris {{ $index + 1 }}" class="input">
                            </td>
                            <td class="table-cell">
                                <input name="items[{{ $index }}][quantity]" data-field="quantity" type="number"
                                       value="{{ $item['quantity'] ?? '' }}" min="0.001" step="0.001" required
                                       aria-label="Qty baris {{ $index + 1 }}" class="input">
                                @error("items.{$index}.quantity")
                                    <p role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="table-cell">
                                <select name="items[{{ $index }}][unit_id]" data-field="unit_id" required
                                        aria-label="Unit baris {{ $index + 1 }}"
                                        class="input input-select @error("items.{$index}.unit_id") input-error @enderror">
                                    <option value="">Pilih unit</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" @selected((string) ($item['unit_id'] ?? '') === (string) $unit->id)>
                                            {{ $unit->unit_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("items.{$index}.unit_id")
                                    <p role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="table-cell">
                                <input name="items[{{ $index }}][unit_price]" data-field="unit_price" type="number"
                                       value="{{ $item['unit_price'] ?? '' }}" min="0" step="0.01" required
                                       aria-label="Harga baris {{ $index + 1 }}" class="input">
                                @error("items.{$index}.unit_price")
                                    <p role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="table-cell text-right tabular-nums text-muted" data-line-total>—</td>
                            <td class="table-cell text-right">
                                <button type="button" data-remove-item
                                        class="text-sm font-medium text-danger hover:underline"
                                        aria-label="Hapus baris {{ $index + 1 }}">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-muted">Harga disalin apa adanya dari PR saat impor; tidak dihitung ulang dari total.</p>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted">Total RAB</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-ink" id="rab-grand-total">{{ \App\Support\Format::rupiah($rabTotal, 2) }}</p>
                @if ($linkedPrTotal !== null)
                    <p class="mt-1 text-sm text-muted">Total PR terkait: <span class="tabular-nums font-medium">{{ \App\Support\Format::rupiah($linkedPrTotal, 2) }}</span></p>
                    @if (bccomp((string) $rabTotal, (string) $linkedPrTotal, 2) !== 0)
                        <p class="mt-1 text-sm font-medium text-danger" role="alert">Total RAB belum sama dengan total PR. Samakan sebelum finalisasi.</p>
                    @endif
                @endif
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                <a href="{{ $budget->exists ? route('rab.show', $budget) : route('rab.index') }}"
                   class="btn btn-secondary">Batal</a>
            </div>
        </div>
    </section>
</form>

<template id="rab-item-row-template">
    <tr data-item-row>
        <td class="table-cell text-muted" data-row-number></td>
        <td class="table-cell">
            <input name="items[__INDEX__][budget_name]" data-field="budget_name" type="text" maxlength="500" required class="input">
        </td>
        <td class="table-cell">
            <input name="items[__INDEX__][description]" data-field="description" type="text" maxlength="2000" class="input">
        </td>
        <td class="table-cell">
            <input name="items[__INDEX__][quantity]" data-field="quantity" type="number" min="0.001" step="0.001" required class="input">
        </td>
        <td class="table-cell">
            <select name="items[__INDEX__][unit_id]" data-field="unit_id" required class="input input-select">
                <option value="">Pilih unit</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                @endforeach
            </select>
        </td>
        <td class="table-cell">
            <input name="items[__INDEX__][unit_price]" data-field="unit_price" type="number" min="0" step="0.01" required class="input">
        </td>
        <td class="table-cell text-right tabular-nums text-muted" data-line-total>—</td>
        <td class="table-cell text-right">
            <button type="button" data-remove-item class="text-sm font-medium text-danger hover:underline">Hapus</button>
        </td>
    </tr>
</template>
