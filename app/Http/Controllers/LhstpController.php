<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Lhstp;
use App\Models\LhstpVersion;
use App\Services\LhstpDocumentService;
use App\Services\LhstpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LhstpController extends Controller
{
    public function __construct(
        private readonly LhstpService $lhstps,
        private readonly LhstpDocumentService $documents,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $lhstps = Lhstp::query()
            ->with(['currentVersion', 'contract.vendor'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('lhstp_number', 'like', "%{$search}%")
                        ->orWhereHas('currentVersion', function ($query) use ($search): void {
                            $query->where('work_name_snapshot', 'like', "%{$search}%")
                                ->orWhere('po_number_snapshot', 'like', "%{$search}%")
                                ->orWhere('vendor_name_snapshot', 'like', "%{$search}%");
                        });
                });
            })
            ->when(in_array($status, Lhstp::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($dateFrom !== '', fn ($query) => $query->whereHas('currentVersion', fn ($query) => $query->whereDate('lhstp_date', '>=', $dateFrom)))
            ->when($dateTo !== '', fn ($query) => $query->whereHas('currentVersion', fn ($query) => $query->whereDate('lhstp_date', '<=', $dateTo)))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('lhstp.index', [
            'lhstps' => $lhstps,
            'search' => $search,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function create(Request $request): View
    {
        $contract = null;
        $prefill = [];

        if ($request->integer('contract_id') > 0) {
            $contract = Contract::query()->with('vendor')->find($request->integer('contract_id'));

            if ($contract !== null && $contract->isSelectableForLhstp()) {
                $prefill = $this->lhstps->prefill($contract);
            } else {
                $contract = null;
            }
        }

        return view('lhstp.create', $this->formData($contract, $prefill, new Lhstp));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $contract = Contract::query()->findOrFail((int) $data['contract_id']);

        $lhstp = $this->lhstps->create($data, $request->user());

        $redirect = redirect()
            ->route('lhstp.show', $lhstp)
            ->with('status', "LHSTP {$lhstp->lhstp_number} berhasil disimpan sebagai Draft.");

        $warnings = $this->lhstps->warnings($contract, $data, $lhstp->id);

        return $warnings === [] ? $redirect : $redirect->with('warning', implode(' ', $warnings));
    }

    public function show(Lhstp $lhstp): View
    {
        $lhstp->load([
            'contract.vendor',
            'versions.creator',
            'versions.finalizer',
            'versions.termin',
            'currentVersion.termin',
            'creator',
            'updater',
        ]);

        return view('lhstp.show', [
            'lhstp' => $lhstp,
            'version' => $lhstp->currentVersion,
            'activityLogs' => $lhstp->activityLogs()->with('user')->limit(20)->get(),
            'documentsByVersion' => $lhstp->documents()->orderBy('id')->get()->groupBy('version_id'),
        ]);
    }

    public function edit(Lhstp $lhstp): View|RedirectResponse
    {
        if (! $lhstp->isDraft()) {
            return redirect()->route('lhstp.show', $lhstp)->with('warning', 'Hanya LHSTP Draft yang dapat diubah.');
        }

        $lhstp->load(['currentVersion.termin', 'contract.vendor']);

        return view('lhstp.edit', $this->formData(
            $lhstp->contract,
            [],
            $lhstp,
            $lhstp->currentVersion,
        ));
    }

    public function update(Request $request, Lhstp $lhstp): RedirectResponse
    {
        $data = $this->validated($request);
        $contract = Contract::query()->findOrFail((int) $data['contract_id']);

        $this->lhstps->updateDraft($lhstp, $data, $request->user());

        $redirect = redirect()
            ->route('lhstp.show', $lhstp)
            ->with('status', "LHSTP {$lhstp->lhstp_number} berhasil diperbarui.");

        $warnings = $this->lhstps->warnings($contract, $data, $lhstp->id);

        return $warnings === [] ? $redirect : $redirect->with('warning', implode(' ', $warnings));
    }

    public function finalize(Request $request, Lhstp $lhstp): RedirectResponse
    {
        $this->lhstps->finalize($lhstp, $request->user(), $request->boolean('generate_document'));

        return redirect()
            ->route('lhstp.show', $lhstp)
            ->with('status', "LHSTP {$lhstp->lhstp_number} berhasil difinalisasi.");
    }

    public function revise(Request $request, Lhstp $lhstp): RedirectResponse
    {
        $version = $this->lhstps->createRevision($lhstp, $request->user());

        return redirect()
            ->route('lhstp.edit', $lhstp)
            ->with('status', "Revisi {$version->label()} dibuat. Perubahan disimpan sebagai Draft.");
    }

    public function archive(Request $request, Lhstp $lhstp): RedirectResponse
    {
        $this->lhstps->archive($lhstp, $request->user());

        return redirect()
            ->route('lhstp.show', $lhstp)
            ->with('status', "LHSTP {$lhstp->lhstp_number} diarsipkan.");
    }

    public function destroy(Request $request, Lhstp $lhstp): RedirectResponse
    {
        $this->lhstps->deleteDraft($lhstp, $request->user());

        return redirect()
            ->route('lhstp.index')
            ->with('status', "LHSTP {$lhstp->lhstp_number} dipindahkan ke Recycle Bin.");
    }

    public function generateDocument(Request $request, Lhstp $lhstp): RedirectResponse
    {
        $data = $request->validate([
            'document_format' => ['required', 'in:PDF,DOCX'],
        ]);

        $version = $lhstp->currentVersion;

        if ($version === null || ! $version->isFinal()) {
            return back()->withErrors(['document_format' => 'Dokumen hanya dapat dibuat untuk versi Final.']);
        }

        $document = $this->documents->generate($lhstp, $version, $request->user(), $data['document_format']);

        return redirect()
            ->route('lhstp.show', $lhstp)
            ->with('status', "Dokumen {$document->document_format} {$version->label()} siap diunduh.");
    }

    /**
     * @param  array<string, mixed>  $prefill
     * @return array<string, mixed>
     */
    private function formData(?Contract $contract, array $prefill, Lhstp $lhstp, ?LhstpVersion $version = null): array
    {
        return [
            'lhstp' => $lhstp,
            'version' => $version,
            'selectedContract' => $contract,
            'prefill' => $prefill,
            'contracts' => Contract::query()
                ->with(['vendor', 'termins'])
                ->where('status', '!=', Contract::STATUS_ARCHIVED)
                ->orderBy('po_number')
                ->get(),
            'termins' => Contract::query()
                ->with('termins')
                ->get()
                ->mapWithKeys(fn (Contract $item) => [
                    $item->id => $item->termins->map(fn ($termin) => [
                        'id' => $termin->id,
                        'name' => $termin->termin_name,
                        'nominal' => (string) $termin->nominal_amount,
                    ])->all(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
            'lhstp_number' => ['required', 'string', 'max:100'],
            'lhstp_date' => ['required', 'date'],
            'work_period' => ['required', 'string', 'max:150'],
            'realization_start' => ['required', 'date'],
            'realization_end' => ['required', 'date', 'after_or_equal:realization_start'],
            'reference_document_type' => ['required', 'in:'.implode(',', Lhstp::REFERENCE_TYPES)],
            'reference_document_name' => ['nullable', 'required_if:reference_document_type,'.Lhstp::REFERENCE_OTHER, 'string', 'max:200'],
            'reference_document_number' => ['required', 'string', 'max:150'],
            'reference_document_date' => ['required', 'date'],
            'payment_amount' => ['required', 'numeric', 'gt:0'],
            'ppn_rate' => ['required', 'numeric', 'between:0,100'],
            'work_conclusion' => ['required', 'string'],
            'payment_terms' => ['required', 'string'],
            'cost_center' => ['required', 'string', 'max:40'],
            'opname_date' => ['nullable', 'date'],
            'signature_date' => ['nullable', 'date'],
            'contract_termin_id' => ['nullable', 'integer'],
            'confirm_exceed' => ['nullable', 'boolean'],
        ]);
    }
}
