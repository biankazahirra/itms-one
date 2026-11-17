<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\PurchaseRequest;
use App\Models\Unit;
use App\Services\BudgetService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');
        $link = (string) $request->query('link', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $budgets = Budget::query()
            ->with(['currentVersion', 'purchaseRequest'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('rab_number', 'like', "%{$search}%")
                        ->orWhereHas('currentVersion', fn ($query) => $query->where('work_name', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseRequest', fn ($query) => $query->where('pr_number', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, Budget::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($link === 'linked', fn ($query) => $query->whereNotNull('purchase_request_id'))
            ->when($link === 'standalone', fn ($query) => $query->whereNull('purchase_request_id'))
            ->when($dateFrom !== '', fn ($query) => $query->whereHas('currentVersion', fn ($query) => $query->whereDate('rab_date', '>=', $dateFrom)))
            ->when($dateTo !== '', fn ($query) => $query->whereHas('currentVersion', fn ($query) => $query->whereDate('rab_date', '<=', $dateTo)))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('rab.index', [
            'budgets' => $budgets,
            'search' => $search,
            'status' => $status,
            'link' => $link,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function create(Request $request): View
    {
        $prefillPr = null;
        $fromPrId = $request->integer('from_pr');

        if ($fromPrId > 0) {
            $prefillPr = PurchaseRequest::query()->with('currentVersion')->find($fromPrId);
        }

        return view('rab.create', [
            'units' => $this->activeUnits(),
            'prOptions' => $this->prOptions(),
            'prefillPr' => $prefillPr,
            'budget' => new Budget,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $linkedPrId = $request->integer('purchase_request_id') ?: null;
        $data = $this->validated($request, $linkedPrId === null);

        $sourcePr = null;

        if ($linkedPrId !== null) {
            $sourcePr = PurchaseRequest::query()->find($linkedPrId);

            if ($sourcePr === null) {
                return back()->withInput()->withErrors(['purchase_request_id' => 'PR sumber tidak ditemukan.']);
            }
        }

        $budget = $this->budgets->create($data, $request->user(), $sourcePr);

        $redirect = $sourcePr !== null
            ? redirect()->route('rab.edit', $budget)->with('status', "Item dari {$sourcePr->pr_number} berhasil diimpor. Lengkapi unit dan deskripsi.")
            : redirect()->route('rab.show', $budget)->with('status', "RAB {$budget->rab_number} berhasil disimpan sebagai Draft.");

        return $this->warnZeroPrices($redirect, $data['items'] ?? []);
    }

    public function show(Budget $budget): View
    {
        $budget->load([
            'versions.creator',
            'versions.finalizer',
            'currentVersion.items.unit',
            'purchaseRequest.currentVersion',
        ]);

        $activityLogs = $budget->activityLogs()
            ->with('user')
            ->limit(10)
            ->get();

        $linkedPrTotal = $budget->purchase_request_id !== null
            ? $this->budgets->linkedPrTotal($budget->purchase_request_id)
            : null;

        return view('rab.show', [
            'budget' => $budget,
            'activityLogs' => $activityLogs,
            'documentsByVersion' => $budget->documents()->get()->keyBy('version_id'),
            'linkedPrTotal' => $linkedPrTotal,
        ]);
    }

    public function edit(Budget $budget): View|RedirectResponse
    {
        if (! $budget->isDraft() || $budget->currentVersion?->isFinal()) {
            return redirect()
                ->route('rab.show', $budget)
                ->with('warning', 'Hanya versi Draft yang dapat diubah.');
        }

        $budget->load('currentVersion.items.unit', 'purchaseRequest.currentVersion');

        $linkedPrTotal = $budget->purchase_request_id !== null
            ? $this->budgets->linkedPrTotal($budget->purchase_request_id)
            : null;

        return view('rab.edit', [
            'budget' => $budget,
            'units' => $this->activeUnits(),
            'linkedPrTotal' => $linkedPrTotal,
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $data = $this->validated($request, true);

        $this->budgets->updateDraft($budget, $data, $request->user());

        $redirect = redirect()
            ->route('rab.show', $budget)
            ->with('status', 'Draft RAB berhasil diperbarui.');

        return $this->warnZeroPrices($redirect, $data['items']);
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->budgets->deleteDraft($budget, $request->user());

        return redirect()
            ->route('rab.index')
            ->with('status', "RAB {$budget->rab_number} dipindahkan ke Recycle Bin.");
    }

    public function finalize(Request $request, Budget $budget): RedirectResponse
    {
        $request->validate([
            'generate_pdf' => ['sometimes', 'boolean'],
        ]);

        $document = $this->budgets->finalize(
            $budget,
            $request->user(),
            $request->boolean('generate_pdf'),
        );

        $message = "RAB {$budget->rab_number} berhasil difinalisasi."
            .($document !== null ? ' PDF versi ini sudah dibuat.' : ' PDF dapat dibuat kemudian dari halaman detail.');

        return redirect()
            ->route('rab.show', $budget)
            ->with('status', $message);
    }

    public function revision(Request $request, Budget $budget): RedirectResponse
    {
        $version = $this->budgets->createRevision($budget, $request->user());

        return redirect()
            ->route('rab.edit', $budget)
            ->with('status', "Revisi {$version->label()} dibuat sebagai Draft. Periksa lalu finalisasi.");
    }

    public function archive(Request $request, Budget $budget): RedirectResponse
    {
        $this->budgets->archive($budget, $request->user());

        return redirect()
            ->route('rab.show', $budget)
            ->with('status', "RAB {$budget->rab_number} diarsipkan.");
    }

    public function generateDocument(Request $request, Budget $budget): RedirectResponse
    {
        $document = $this->budgets->generateDocument($budget, $request->user());

        return redirect()
            ->route('rab.show', $budget)
            ->with('status', "PDF {$document->original_filename} berhasil dibuat.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $standalone): array
    {
        $rules = [
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'work_name' => ['nullable', 'string', 'max:255'],
            'rab_date' => ['nullable', 'date'],
            'items' => ['nullable', 'array'],
            'items.*.budget_name' => ['required_with:items', 'string', 'max:500'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999999'],
            'items.*.unit_id' => ['required_with:items', 'integer', 'exists:units,id'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999999999'],
        ];

        if ($standalone) {
            $rules['work_name'] = ['required', 'string', 'max:255'];
            $rules['rab_date'] = ['required', 'date'];
            $rules['items'] = ['required', 'array', 'min:1'];
        }

        return $request->validate($rules, [
            'items.required' => 'RAB harus memiliki minimal satu baris anggaran.',
            'items.min' => 'RAB harus memiliki minimal satu baris anggaran.',
        ], [
            'purchase_request_id' => 'PR terkait',
            'work_name' => 'Nama pekerjaan',
            'rab_date' => 'Tanggal',
            'items.*.budget_name' => 'Nama Anggaran',
            'items.*.quantity' => 'Qty',
            'items.*.unit_id' => 'Unit',
            'items.*.unit_price' => 'Harga',
        ]);
    }

    /**
     * @return Collection<int, Unit>
     */
    private function activeUnits()
    {
        return Unit::query()->where('is_active', true)->orderBy('unit_name')->get();
    }

    /**
     * @return Collection<int, PurchaseRequest>
     */
    private function prOptions()
    {
        return PurchaseRequest::query()
            ->with('currentVersion')
            ->latest('updated_at')
            ->limit(100)
            ->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function warnZeroPrices(RedirectResponse $redirect, array $items): RedirectResponse
    {
        $zeroRows = [];

        foreach (array_values($items) as $index => $item) {
            if ((float) ($item['unit_price'] ?? 0) === 0.0) {
                $zeroRows[] = $index + 1;
            }
        }

        if ($zeroRows !== []) {
            $redirect->with('warning', 'Harga bernilai 0 pada baris '.implode(', ', $zeroRows).'. Periksa kembali bila tidak disengaja.');
        }

        return $redirect;
    }
}
