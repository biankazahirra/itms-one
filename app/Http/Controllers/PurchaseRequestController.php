<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Services\PaymentStatusCalculator;
use App\Services\PrTrackingService;
use App\Services\PurchaseRequestService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $purchaseRequests,
        private readonly PaymentStatusCalculator $paymentStatus,
        private readonly PrTrackingService $tracking,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');
        $category = (string) $request->query('category', '');
        $departmentId = (string) $request->query('department', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $tracking = (string) $request->query('tracking', '');

        $latestStage = '(select stage from pr_stage_updates where pr_stage_updates.purchase_request_id = purchase_requests.id order by pr_stage_updates.id desc limit 1)';

        $purchaseRequests = PurchaseRequest::query()
            ->with(['currentVersion.department'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('pr_number', 'like', "%{$search}%")
                        ->orWhereHas('currentVersion', fn ($query) => $query->where('subject', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, PurchaseRequest::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($category !== '', fn ($query) => $query->whereHas('currentVersion', fn ($query) => $query->where('category', $category)))
            ->when($departmentId !== '', fn ($query) => $query->whereHas('currentVersion', fn ($query) => $query->where('recipient_department_id', $departmentId)))
            ->when($dateFrom !== '', fn ($query) => $query->whereHas('currentVersion', fn ($query) => $query->whereDate('pr_date', '>=', $dateFrom)))
            ->when($dateTo !== '', fn ($query) => $query->whereHas('currentVersion', fn ($query) => $query->whereDate('pr_date', '<=', $dateTo)))
            ->when($tracking === PrTrackingService::STATUS_NOT_TRACKED, fn ($query) => $query->whereRaw("{$latestStage} is null"))
            ->when($tracking === PrTrackingService::STATUS_IN_PROGRESS, fn ($query) => $query->whereRaw("{$latestStage} is not null and {$latestStage} <> ?", [PrTrackingService::STAGES[4]]))
            ->when($tracking === PrTrackingService::STATUS_DONE, fn ($query) => $query->whereRaw("{$latestStage} = ?", [PrTrackingService::STAGES[4]]))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('purchase-requests.index', [
            'purchaseRequests' => $purchaseRequests,
            'departments' => $this->recipients(),
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'departmentId' => $departmentId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'tracking' => $tracking,
            'trackingStatuses' => $purchaseRequests->getCollection()
                ->mapWithKeys(fn (PurchaseRequest $pr): array => [$pr->id => $this->tracking->status($pr)])
                ->all(),
        ]);
    }

    public function create(): View
    {
        $purchaseRequest = new PurchaseRequest;

        return view('purchase-requests.create', [
            'departments' => $this->recipients(),
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $purchaseRequest = $this->purchaseRequests->create($data, $request->user());

        $redirect = redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('status', "PR {$purchaseRequest->pr_number} berhasil disimpan sebagai Draft.");

        return $this->warnZeroPrices($redirect, $data['items']);
    }

    public function show(PurchaseRequest $purchaseRequest): View
    {
        $purchaseRequest->load([
            'versions.creator',
            'versions.finalizer',
            'versions.department',
            'currentVersion.items',
            'currentVersion.department',
            'contracts.vendor',
            'prDocuments.uploader',
            'stageUpdates.creator',
        ]);

        $activityLogs = $purchaseRequest->activityLogs()
            ->with('user')
            ->limit(10)
            ->get();

        return view('purchase-requests.show', [
            'purchaseRequest' => $purchaseRequest,
            'activityLogs' => $activityLogs,
            'documentsByVersion' => $purchaseRequest->documents()->get()->keyBy('version_id'),
            'paymentSummaries' => $purchaseRequest->contracts->map(fn ($contract): array => [
                'contract' => $contract,
                'status' => $this->paymentStatus->status($contract),
                'paid' => $this->paymentStatus->confirmedTotal($contract),
                'obligation' => $this->paymentStatus->obligation($contract),
            ])->all(),
            'trackingStatus' => $this->tracking->status($purchaseRequest),
            'trackingStages' => $this->tracking->allowedStages($purchaseRequest),
        ]);
    }

    public function storeStageUpdate(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string', Rule::in(PrTrackingService::STAGES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'stage' => 'Tahap',
            'note' => 'Catatan',
        ]);

        $this->tracking->record($purchaseRequest, $data['stage'], $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Tahap tracking PR diperbarui.');
    }

    public function edit(PurchaseRequest $purchaseRequest): View|RedirectResponse
    {
        if (! $purchaseRequest->isDraft() || $purchaseRequest->currentVersion?->isFinal()) {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('warning', 'Hanya versi Draft yang dapat diubah.');
        }

        $purchaseRequest->load('currentVersion.items', 'currentVersion.department');

        return view('purchase-requests.edit', [
            'purchaseRequest' => $purchaseRequest,
            'departments' => $this->recipients(),
        ]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $data = $this->validated($request);

        $this->purchaseRequests->updateDraft($purchaseRequest, $data, $request->user());

        $redirect = redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('status', 'Draft PR berhasil diperbarui.');

        return $this->warnZeroPrices($redirect, $data['items']);
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->purchaseRequests->deleteDraft($purchaseRequest, $request->user());

        return redirect()
            ->route('purchase-requests.index')
            ->with('status', "PR {$purchaseRequest->pr_number} dipindahkan ke Recycle Bin.");
    }

    public function finalize(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $request->validate([
            'generate_pdf' => ['sometimes', 'boolean'],
        ]);

        $document = $this->purchaseRequests->finalize(
            $purchaseRequest,
            $request->user(),
            $request->boolean('generate_pdf'),
        );

        $message = "PR {$purchaseRequest->pr_number} berhasil difinalisasi."
            .($document !== null ? ' PDF versi ini sudah dibuat.' : ' PDF dapat dibuat kemudian dari halaman detail.');

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('status', $message);
    }

    public function revision(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $version = $this->purchaseRequests->createRevision($purchaseRequest, $request->user());

        return redirect()
            ->route('purchase-requests.edit', $purchaseRequest)
            ->with('status', "Revisi {$version->label()} dibuat sebagai Draft. Periksa lalu finalisasi.");
    }

    public function archive(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->purchaseRequests->archive($purchaseRequest, $request->user());

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('status', "PR {$purchaseRequest->pr_number} diarsipkan.");
    }

    public function generateDocument(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $document = $this->purchaseRequests->generateDocument($purchaseRequest, $request->user());

        return redirect()
            ->route('purchase-requests.show', $purchaseRequest)
            ->with('status', "PDF {$document->original_filename} berhasil dibuat.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $recipientIds = $this->recipients()->pluck('id')->all();

        return $request->validate([
            'recipient_department_id' => ['required', 'integer', 'in:'.implode(',', $recipientIds)],
            'subject' => ['required', 'string', 'max:255'],
            'pr_date' => ['required', 'date'],
            'category' => ['nullable', 'string', 'in:'.implode(',', PurchaseRequest::CATEGORIES)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.budget_name' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999999999'],
        ], [
            'items.required' => 'PR harus memiliki minimal satu baris anggaran.',
            'items.min' => 'PR harus memiliki minimal satu baris anggaran.',
        ], [
            'recipient_department_id' => 'Kepada',
            'subject' => 'Perihal',
            'pr_date' => 'Tanggal',
            'category' => 'Kategori',
            'items.*.budget_name' => 'Nama Anggaran',
            'items.*.quantity' => 'Qty',
            'items.*.unit_price' => 'Harga Satuan',
        ]);
    }

    /**
     * @return Collection<int, Department>
     */
    private function recipients()
    {
        return Department::query()
            ->where('is_active', true)
            ->where('name', '!=', PurchaseRequest::SENDER_DEPARTMENT)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function warnZeroPrices(RedirectResponse $redirect, array $items): RedirectResponse
    {
        $zeroRows = [];

        foreach (array_values($items) as $index => $item) {
            if ((float) $item['unit_price'] === 0.0) {
                $zeroRows[] = $index + 1;
            }
        }

        if ($zeroRows !== []) {
            $redirect->with('warning', 'Harga satuan bernilai 0 pada baris '.implode(', ', $zeroRows).'. Periksa kembali bila tidak disengaja.');
        }

        return $redirect;
    }
}
