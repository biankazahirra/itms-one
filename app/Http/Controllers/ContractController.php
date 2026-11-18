<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\Vendor;
use App\Services\ContractService;
use App\Services\ContractStatusService;
use App\Services\PrivateFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contracts,
        private readonly ContractStatusService $status,
        private readonly PrivateFileService $files,
    ) {}

    public function index(Request $request): View
    {
        $this->status->syncAll();

        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');
        $vendorId = (string) $request->query('vendor_id', '');
        $method = (string) $request->query('payment_method', '');
        $endFrom = (string) $request->query('end_from', '');
        $endTo = (string) $request->query('end_to', '');

        $contracts = Contract::query()
            ->with(['vendor', 'termins'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('po_number', 'like', "%{$search}%")
                        ->orWhere('work_name', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($query) => $query->where('company_name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, Contract::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($vendorId !== '', fn ($query) => $query->where('vendor_id', (int) $vendorId))
            ->when(in_array($method, Contract::PAYMENT_METHODS, true), fn ($query) => $query->where('payment_method', $method))
            ->when($endFrom !== '', fn ($query) => $query->whereDate('end_date', '>=', $endFrom))
            ->when($endTo !== '', fn ($query) => $query->whereDate('end_date', '<=', $endTo))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('contracts.index', [
            'contracts' => $contracts,
            'vendors' => Vendor::query()->orderBy('company_name')->get(),
            'search' => $search,
            'status' => $status,
            'vendorId' => $vendorId,
            'method' => $method,
            'endFrom' => $endFrom,
            'endTo' => $endTo,
        ]);
    }

    public function create(): View
    {
        return view('contracts.create', [
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('company_name')->get(),
            'contract' => new Contract,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $contract = $this->contracts->create(
            $data,
            $request->user(),
            $request->file('attachments', []),
        );

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', "Kontrak {$contract->po_number} berhasil disimpan.");
    }

    public function show(Contract $contract): View
    {
        $this->status->sync($contract);

        $contract->load([
            'vendor',
            'termins',
            'attachments.uploader',
            'creator',
            'updater',
        ]);

        return view('contracts.show', [
            'contract' => $contract,
            'activityLogs' => $contract->activityLogs()->with('user')->limit(30)->get(),
        ]);
    }

    public function edit(Contract $contract): View
    {
        $this->status->sync($contract);

        $contract->load(['vendor', 'termins', 'attachments.uploader']);

        $vendorIds = Vendor::query()->where('is_active', true)->pluck('id')->push($contract->vendor_id);

        return view('contracts.edit', [
            'vendors' => Vendor::query()
                ->whereIn('id', $vendorIds->filter()->unique())
                ->orderBy('company_name')
                ->get(),
            'contract' => $contract,
        ]);
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $data = $this->validated($request, $contract);

        $this->contracts->update(
            $contract,
            $data,
            $request->user(),
            $request->file('attachments', []),
        );

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', "Kontrak {$contract->po_number} berhasil diperbarui.");
    }

    public function destroy(Request $request, Contract $contract): RedirectResponse
    {
        $this->contracts->delete($contract, $request->user());

        return redirect()
            ->route('contracts.index')
            ->with('status', "Kontrak {$contract->po_number} dipindahkan ke Recycle Bin.");
    }

    public function archive(Request $request, Contract $contract): RedirectResponse
    {
        $this->contracts->archive($contract, $request->user());

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', "Kontrak {$contract->po_number} diarsipkan.");
    }

    public function unarchive(Request $request, Contract $contract): RedirectResponse
    {
        $this->contracts->unarchive($contract, $request->user());

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', "Arsip kontrak {$contract->po_number} dibatalkan.");
    }

    public function storeAttachments(Request $request, Contract $contract): RedirectResponse
    {
        $request->validate([
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
        ], [
            'attachments.required' => 'Pilih minimal satu berkas PDF.',
            'attachments.min' => 'Pilih minimal satu berkas PDF.',
        ], [
            'attachments.*' => 'Lampiran',
        ]);

        $this->contracts->addAttachments(
            $contract,
            $request->file('attachments', []),
            $request->user(),
        );

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', 'Lampiran berhasil diunggah.');
    }

    public function destroyAttachment(Request $request, Contract $contract, ContractAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->contract_id === $contract->id, 404);

        $this->contracts->removeAttachment($contract, $attachment, $request->user());

        return redirect()
            ->route('contracts.show', $contract)
            ->with('status', 'Lampiran berhasil dihapus.');
    }

    public function downloadAttachment(Contract $contract, ContractAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->contract_id === $contract->id, 404);

        return $this->files->download($attachment->storage_path, $attachment->original_filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Contract $contract = null): array
    {
        return $request->validate([
            'po_number' => [
                'required', 'string', 'max:100',
                Rule::unique('contracts', 'po_number')->ignore($contract?->id),
            ],
            'contract_date' => ['required', 'date'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'work_name' => ['required', 'string', 'max:255'],
            'representative_name' => ['required', 'string', 'max:200'],
            'representative_position' => ['required', 'string', 'max:200'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999999'],
            'ppn_status' => ['required', Rule::in(Contract::PPN_STATUSES)],
            'payment_method' => ['required', Rule::in(Contract::PAYMENT_METHODS)],
            'monthly_payment_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999999', 'required_if:payment_method,'.Contract::PAYMENT_MONTHLY],
            'full_financing_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999999', 'required_if:payment_method,'.Contract::PAYMENT_FULL],
            'termins' => ['nullable', 'array', 'min:1', 'required_if:payment_method,'.Contract::PAYMENT_TERMIN],
            'termins.*.termin_name' => ['required_with:termins', 'string', 'max:120'],
            'termins.*.percentage' => ['required_with:termins', 'numeric', 'gt:0', 'decimal:0,4', 'max:100'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
        ], [
            'monthly_payment_amount.required_if' => 'Nominal pembayaran bulanan wajib diisi untuk metode Bulanan.',
            'full_financing_amount.required_if' => 'Nominal pembayaran wajib diisi untuk metode Full Financing.',
            'termins.required_if' => 'Jadwal termin wajib diisi untuk metode Termin.',
            'termins.min' => 'Jadwal termin wajib memiliki minimal satu baris.',
        ], [
            'po_number' => 'Nomor PO',
            'contract_date' => 'Tanggal kontrak',
            'vendor_id' => 'Vendor',
            'work_name' => 'Nama pekerjaan',
            'representative_name' => 'Nama perwakilan',
            'representative_position' => 'Jabatan perwakilan',
            'start_date' => 'Tanggal mulai',
            'end_date' => 'Tanggal berakhir',
            'contract_value' => 'Nilai kontrak',
            'ppn_status' => 'Status PPN',
            'payment_method' => 'Metode pembayaran',
            'monthly_payment_amount' => 'Nominal bulanan',
            'full_financing_amount' => 'Nominal full financing',
            'termins.*.termin_name' => 'Nama termin',
            'termins.*.percentage' => 'Persentase termin',
            'attachments.*' => 'Lampiran',
        ]);
    }
}
