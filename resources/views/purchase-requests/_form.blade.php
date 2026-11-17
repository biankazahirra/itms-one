@php
    $storedItems = old('items');

    if ($storedItems === null) {
        $storedItems = $purchaseRequest->exists && $purchaseRequest->currentVersion
            ? $purchaseRequest->currentVersion->items->map(fn ($item) => [
                'budget_name' => $item->budget_name,
                'quantity' => \App\Support\Money::quantity($item->quantity),
                'unit_price' => $item->unit_price,
            ])->all()
            : [['budget_name' => '', 'quantity' => '', 'unit_price' => '']];
    }

    $storedItems = array_values($storedItems);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="rounded-xl border border-line bg-surface p-6">
        <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-muted">Data PR</h3>

        <div class="space-y-5">
            <x-field name="from" label="Dari">
                <input id="from" type="text" value="Divisi ITMS / IT & Management System Department" readonly
                       class="input bg-app text-muted">
            </x-field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="recipient_department_id" label="Kepada" required>
                    <select id="recipient_department_id" name="recipient_department_id" required
                            @error('recipient_department_id') aria-invalid="true" aria-describedby="recipient_department_id-error" @enderror
                            class="input input-select @error('recipient_department_id') input-error @enderror">
                        <option value="">Pilih departemen tujuan</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) old('recipient_department_id', $purchaseRequest->currentVersion?->recipient_department_id) === $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="pr_date" label="Tanggal" required>
                    <input id="pr_date" name="pr_date" type="date" required
                           value="{{ old('pr_date', $purchaseRequest->currentVersion?->pr_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                           @error('pr_date') aria-invalid="true" aria-describedby="pr_date-error" @enderror
                           class="input @error('pr_date') input-error @enderror">
                </x-field>
            </div>

            <x-field name="subject" label="Perihal" required>
                <input id="subject" name="subject" type="text" required maxlength="255"
                       value="{{ old('subject', $purchaseRequest->currentVersion?->subject) }}"
                       @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror
                       class="input @error('subject') input-error @enderror">
            </x-field>

            <x-field name="category" label="Kategori" help="Opsional. Pilih satu kategori bila relevan.">
                <select id="category" name="category"
                        @error('category') aria-invalid="true" aria-describedby="category-error" @enderror
                        class="input input-select @error('category') input-error @enderror">
                    <option value="">Tanpa kategori</option>
                    @foreach (\App\Models\PurchaseRequest::CATEGORIES as $option)
                        <option value="{{ $option }}" @selected(old('category', $purchaseRequest->currentVersion?->category) === $option)>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </x-field>
        </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-muted">Rincian Anggaran</h3>
                <p class="mt-1 text-xs text-muted">Minimal satu baris. Estimasi harga dihitung otomatis dari Qty × Harga Satuan.</p>
            </div>
            <button type="button" id="add-item" class="btn btn-secondary">Tambah Baris</button>
        </div>

        @error('items')
            <p role="alert" class="mb-3 text-sm text-danger">{{ $message }}</p>
        @enderror

        <div class="overflow-x-auto rounded-lg border border-line">
            <table class="min-w-full text-sm" id="items-table">
                <thead class="bg-app">
                    <tr>
                        <th scope="col" class="table-head w-12">No</th>
                        <th scope="col" class="table-head">Nama Anggaran</th>
                        <th scope="col" class="table-head w-28">Qty</th>
                        <th scope="col" class="table-head w-40">Harga Satuan</th>
                        <th scope="col" class="table-head w-12"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody id="items-body" class="divide-y divide-line">
                    @foreach ($storedItems as $index => $item)
                        <tr data-item-row>
                            <td class="table-cell text-muted" data-row-number>{{ $index + 1 }}</td>
                            <td class="table-cell">
                                <input name="items[{{ $index }}][budget_name]" data-field="budget_name" type="text"
                                       value="{{ $item['budget_name'] ?? '' }}" maxlength="500" required
                                       aria-label="Nama anggaran baris {{ $index + 1 }}"
                                       class="input">
                                @error("items.{$index}.budget_name")
                                    <p role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="table-cell">
                                <input name="items[{{ $index }}][quantity]" data-field="quantity" type="number"
                                       value="{{ $item['quantity'] ?? '' }}" min="0.001" step="0.001" required
                                       aria-label="Qty baris {{ $index + 1 }}"
                                       class="input">
                                @error("items.{$index}.quantity")
                                    <p role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="table-cell">
                                <input name="items[{{ $index }}][unit_price]" data-field="unit_price" type="number"
                                       value="{{ $item['unit_price'] ?? '' }}" min="0" step="0.01" required
                                       aria-label="Harga satuan baris {{ $index + 1 }}"
                                       class="input">
                                @error("items.{$index}.unit_price")
                                    <p role="alert" class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </td>
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

        <p class="mt-3 text-xs text-muted">Harga satuan disimpan internal dan tidak ditampilkan pada PDF PR.</p>
    </section>

    <div class="flex items-center gap-2">
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ $purchaseRequest->exists ? route('purchase-requests.show', $purchaseRequest) : route('purchase-requests.index') }}"
           class="btn btn-secondary">Batal</a>
    </div>
</form>

<template id="item-row-template">
    <tr data-item-row>
        <td class="table-cell text-muted" data-row-number></td>
        <td class="table-cell">
            <input name="items[__INDEX__][budget_name]" data-field="budget_name" type="text" maxlength="500" required class="input">
        </td>
        <td class="table-cell">
            <input name="items[__INDEX__][quantity]" data-field="quantity" type="number" min="0.001" step="0.001" required class="input">
        </td>
        <td class="table-cell">
            <input name="items[__INDEX__][unit_price]" data-field="unit_price" type="number" min="0" step="0.01" required class="input">
        </td>
        <td class="table-cell text-right">
            <button type="button" data-remove-item class="text-sm font-medium text-danger hover:underline">Hapus</button>
        </td>
    </tr>
</template>
