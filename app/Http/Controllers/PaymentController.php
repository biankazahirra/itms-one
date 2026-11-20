<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\PaymentEvidence;
use App\Models\PaymentRecord;
use App\Services\PaymentService;
use App\Services\PrivateFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PrivateFileService $files,
    ) {}

    public function store(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'contract_termin_id' => ['nullable', 'integer'],
            'payment_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999999'],
            'payment_reference' => ['required', 'string', 'max:150'],
        ], [], [
            'payment_date' => 'Tanggal pembayaran',
            'contract_termin_id' => 'Termin',
            'payment_amount' => 'Nominal pembayaran',
            'payment_reference' => 'Referensi pembayaran',
        ]);

        $this->payments->record($contract, $data, $request->user());

        return back()->with('status', 'Catatan pembayaran ditambahkan sebagai Draft.');
    }

    public function confirm(Request $request, Contract $contract, PaymentRecord $payment): RedirectResponse
    {
        $this->payments->confirm($payment, $request->user());

        return back()->with('status', 'Pembayaran dikonfirmasi.');
    }

    public function correct(Request $request, Contract $contract, PaymentRecord $payment): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_amount' => ['required', 'numeric', 'not_in:0', 'decimal:0,2', 'min:-9999999999999999', 'max:9999999999999999'],
            'payment_reference' => ['required', 'string', 'max:150'],
        ], [
            'payment_amount.not_in' => 'Nominal koreksi tidak boleh nol.',
        ], [
            'payment_date' => 'Tanggal koreksi',
            'payment_amount' => 'Nominal koreksi',
            'payment_reference' => 'Referensi koreksi',
        ]);

        $this->payments->correct($payment, $data, $request->user());

        return back()->with('status', 'Koreksi pembayaran ditambahkan sebagai Draft.');
    }

    public function destroy(Request $request, Contract $contract, PaymentRecord $payment): RedirectResponse
    {
        $this->payments->deleteDraft($payment, $request->user());

        return back()->with('status', 'Draf pembayaran dihapus.');
    }

    public function storeEvidence(Request $request, Contract $contract, PaymentRecord $payment): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.PaymentEvidence::MAX_FILE_SIZE_KB],
        ], [
            'file.mimes' => 'Berkas harus PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ], [
            'file' => 'Bukti pembayaran',
        ]);

        $this->payments->addEvidence($payment, $request->file('file'), $request->user());

        return back()->with('status', 'Bukti pembayaran ditambahkan.');
    }

    public function preview(Contract $contract, PaymentRecord $payment, PaymentEvidence $paymentEvidence): StreamedResponse
    {
        abort_unless($paymentEvidence->canPreview(), 404);

        return $this->files->preview($paymentEvidence->storage_path, $paymentEvidence->original_filename);
    }

    public function download(Contract $contract, PaymentRecord $payment, PaymentEvidence $paymentEvidence): StreamedResponse
    {
        return $this->files->download($paymentEvidence->storage_path, $paymentEvidence->original_filename);
    }

    public function destroyEvidence(Request $request, Contract $contract, PaymentRecord $payment, PaymentEvidence $paymentEvidence): RedirectResponse
    {
        $this->payments->deleteEvidence($paymentEvidence, $request->user());

        return back()->with('status', 'Bukti pembayaran dipindahkan ke Recycle Bin.');
    }
}
