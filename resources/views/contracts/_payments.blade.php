@php
    $statusClass = match ($paymentSummary['status']) {
        \App\Services\PaymentStatusCalculator::PAID => 'bg-success/10 text-success',
        \App\Services\PaymentStatusCalculator::PARTIALLY_PAID => 'bg-warning/10 text-warning',
        default => 'bg-app text-muted ring-1 ring-inset ring-line',
    };
@endphp

<section class="mt-6 rounded-xl border border-line bg-surface p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-muted">Pembayaran</h3>
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">
            <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
            {{ $paymentSummary['status'] }}
        </span>
    </div>

    <dl class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-line bg-app px-4 py-3">
            <dt class="text-xs text-muted">Kewajiban (LHSTP Final)</dt>
            <dd class="mt-1 text-sm font-semibold tabular-nums">{{ \App\Support\Format::rupiah($paymentSummary['obligation'], 2) }}</dd>
        </div>
        <div class="rounded-lg border border-line bg-app px-4 py-3">
            <dt class="text-xs text-muted">Total Confirmed</dt>
            <dd class="mt-1 text-sm font-semibold tabular-nums">{{ \App\Support\Format::rupiah($paymentSummary['paid'], 2) }}</dd>
        </div>
        <div class="rounded-lg border border-line bg-app px-4 py-3">
            <dt class="text-xs text-muted">Sisa kewajiban</dt>
            <dd class="mt-1 text-sm font-semibold tabular-nums">{{ \App\Support\Format::rupiah($paymentSummary['outstanding'], 2) }}</dd>
        </div>
    </dl>

    @if ($contract->payment_method === \App\Models\Contract::PAYMENT_TERMIN && count($terminProgress) > 0)
        <div class="mt-5 overflow-x-auto rounded-lg border border-line">
            <table class="min-w-full text-sm">
                <thead class="bg-app">
                    <tr>
                        <th scope="col" class="table-head">Termin</th>
                        <th scope="col" class="table-head text-right">Nominal</th>
                        <th scope="col" class="table-head text-right">Dibayar</th>
                        <th scope="col" class="table-head">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($terminProgress as $row)
                        <tr>
                            <td class="table-cell">{{ $row['termin']->termin_name }}</td>
                            <td class="table-cell text-right tabular-nums">{{ \App\Support\Format::rupiah($row['termin']->nominal_amount, 2) }}</td>
                            <td class="table-cell text-right tabular-nums">{{ \App\Support\Format::rupiah($row['paid'], 2) }}</td>
                            <td class="table-cell">{{ $row['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-5">
        @if ($contract->payments->isEmpty())
            <p class="text-sm text-muted">Belum ada catatan pembayaran.</p>
        @else
            <ul class="divide-y divide-line rounded-lg border border-line">
                @foreach ($contract->payments as $payment)
                    <li class="px-4 py-3 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ $payment->payment_reference }}
                                    @if ($payment->isCorrection())
                                        <span class="text-xs font-normal text-muted">· koreksi dari #{{ $payment->corrects_payment_record_id }}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs text-muted">
                                    {{ \App\Support\Format::tanggal($payment->payment_date) }}
                                    @if ($payment->termin) · {{ $payment->termin->termin_name }} @endif
                                    · dicatat {{ $payment->creator?->name ?? 'Sistem' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold tabular-nums {{ bccomp((string) $payment->payment_amount, '0', 2) < 0 ? 'text-danger' : '' }}">
                                    {{ \App\Support\Format::rupiah($payment->payment_amount, 2) }}
                                </p>
                                <p class="mt-0.5 text-xs {{ $payment->isConfirmed() ? 'text-success' : 'text-muted' }}">{{ $payment->status }}</p>
                            </div>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            @if ($payment->isDraft())
                                <form method="POST" action="{{ route('contracts.payments.confirm', [$contract, $payment]) }}"
                                      onsubmit="return confirm('Konfirmasi pembayaran ini? Setelah Confirmed tidak dapat diubah.')">
                                    @csrf
                                    <button type="submit" class="text-sm font-medium text-primary hover:underline">Konfirmasi</button>
                                </form>
                                <form method="POST" action="{{ route('contracts.payments.destroy', [$contract, $payment]) }}"
                                      onsubmit="return confirm('Hapus draf pembayaran ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-danger hover:underline">Hapus</button>
                                </form>
                            @else
                                <details class="relative">
                                    <summary class="cursor-pointer list-none text-sm font-medium text-primary hover:underline">Koreksi</summary>
                                    <form method="POST" action="{{ route('contracts.payments.correct', [$contract, $payment]) }}"
                                          class="mt-2 grid gap-2 rounded-lg border border-line bg-app p-3 sm:w-96">
                                        @csrf
                                        <p class="text-xs text-muted">Nominal koreksi adalah selisih. Nilai negatif mengurangi pembayaran.</p>
                                        <input name="payment_date" type="date" required value="{{ now()->format('Y-m-d') }}" class="input">
                                        <input name="payment_amount" type="number" step="0.01" required placeholder="Nominal koreksi" class="input">
                                        <input name="payment_reference" type="text" required maxlength="150" placeholder="Referensi koreksi" class="input">
                                        <button type="submit" class="btn btn-secondary">Simpan Koreksi</button>
                                    </form>
                                </details>
                            @endif
                        </div>

                        <div class="mt-2">
                            @if ($payment->evidences->isNotEmpty())
                                <ul class="space-y-1">
                                    @foreach ($payment->evidences as $evidence)
                                        <li class="flex flex-wrap items-center gap-3 text-xs">
                                            <span class="font-medium">{{ $evidence->original_filename }}</span>
                                            <span class="text-muted">{{ number_format($evidence->file_size / 1024, 0, ',', '.') }} KB</span>
                                            @if ($evidence->canPreview())
                                                <a href="{{ route('contracts.payments.evidences.preview', [$contract, $payment, $evidence]) }}"
                                                   target="_blank" rel="noopener" class="font-medium text-primary hover:underline">Pratinjau</a>
                                            @endif
                                            <a href="{{ route('contracts.payments.evidences.download', [$contract, $payment, $evidence]) }}"
                                               class="font-medium text-primary hover:underline">Unduh</a>
                                            <form method="POST" action="{{ route('contracts.payments.evidences.destroy', [$contract, $payment, $evidence]) }}"
                                                  onsubmit="return confirm('Pindahkan bukti ini ke Recycle Bin?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-medium text-danger hover:underline">Hapus</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <form method="POST" action="{{ route('contracts.payments.evidences.store', [$contract, $payment]) }}"
                                  enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
                                @csrf
                                <input name="file" type="file" required accept=".pdf,.jpg,.jpeg,.png"
                                       class="block text-xs text-muted file:mr-2 file:rounded-lg file:border-0 file:bg-app file:px-2 file:py-1 file:text-xs file:font-medium file:text-ink">
                                <button type="submit" class="text-sm font-medium text-primary hover:underline">Tambah bukti</button>
                                <span class="text-xs text-muted">PDF/JPG/JPEG/PNG, maks. 10 MB.</span>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <form method="POST" action="{{ route('contracts.payments.store', $contract) }}" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        <x-field name="payment_date" label="Tanggal pembayaran" required>
            <input id="payment_date" name="payment_date" type="date" required value="{{ old('payment_date', now()->format('Y-m-d')) }}"
                   class="input @error('payment_date') input-error @enderror">
        </x-field>

        @if ($contract->payment_method === \App\Models\Contract::PAYMENT_TERMIN)
            <x-field name="contract_termin_id" label="Termin">
                <select id="contract_termin_id" name="contract_termin_id" class="input input-select">
                    <option value="">Tanpa termin</option>
                    @foreach ($contract->termins as $termin)
                        <option value="{{ $termin->id }}" @selected((string) old('contract_termin_id') === (string) $termin->id)>
                            {{ $termin->termin_name }}
                        </option>
                    @endforeach
                </select>
            </x-field>
        @endif

        <x-field name="payment_amount" label="Nominal" required>
            <input id="payment_amount" name="payment_amount" type="number" step="0.01" min="0.01" required value="{{ old('payment_amount') }}"
                   class="input @error('payment_amount') input-error @enderror">
        </x-field>

        <x-field name="payment_reference" label="Referensi" required>
            <input id="payment_reference" name="payment_reference" type="text" maxlength="150" required value="{{ old('payment_reference') }}"
                   class="input @error('payment_reference') input-error @enderror">
        </x-field>

        <div class="sm:col-span-2 lg:col-span-4">
            <button type="submit" class="btn btn-primary">Catat Pembayaran (Draft)</button>
        </div>
    </form>
</section>
