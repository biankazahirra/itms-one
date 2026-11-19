<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Contract;
use App\Models\Lhstp;
use App\Models\PurchaseRequest;
use App\Models\RecycleBinItem;
use App\Services\RecycleBinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecycleBinController extends Controller
{
    /**
     * Indonesian labels and document identifiers for each module.
     *
     * @var array<string, array{label: string, number: string, description: string}>
     */
    private const MODULES = [
        'purchase_request' => ['label' => 'Purchase Requisition', 'number' => 'pr_number', 'description' => 'subject'],
        'budget' => ['label' => 'RAB', 'number' => 'rab_number', 'description' => 'work_name'],
        'contract' => ['label' => 'Kontrak', 'number' => 'po_number', 'description' => 'work_name'],
        'lhstp' => ['label' => 'LHSTP', 'number' => 'lhstp_number', 'description' => 'work_name_snapshot'],
    ];

    public function __construct(private readonly RecycleBinService $recycleBin) {}

    public function index(Request $request): View
    {
        $module = (string) $request->query('module', '');

        $items = RecycleBinItem::query()
            ->with('deletedBy')
            ->when(array_key_exists($module, self::MODULES), fn ($query) => $query->where('entity_type', $module))
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        $rows = $items->getCollection()->map(fn (RecycleBinItem $item): array => [
            'item' => $item,
            'module' => self::MODULES[$item->entity_type]['label'] ?? $item->entity_type,
            'number' => $this->value($item, 'number') ?? '-',
            'description' => $this->value($item, 'description') ?? '-',
        ]);

        return view('recycle-bin.index', [
            'items' => $items,
            'rows' => $rows,
            'module' => $module,
            'modules' => array_map(fn (array $module): string => $module['label'], self::MODULES),
        ]);
    }

    public function restore(RecycleBinItem $item): RedirectResponse
    {
        $entity = $this->recycleBin->restore($item);

        return back()->with('status', 'Data berhasil dipulihkan: '.class_basename($entity).'.');
    }

    public function destroy(RecycleBinItem $item): RedirectResponse
    {
        $reason = $this->recycleBin->purgeItem($item);

        if ($reason !== null) {
            return back()->with('error', 'Data tidak dapat dihapus permanen. '.$reason);
        }

        return back()->with('status', 'Data dihapus permanen.');
    }

    private function value(RecycleBinItem $item, string $key): ?string
    {
        $modelClass = config('recycle_bin.types.'.$item->entity_type);

        if (! is_string($modelClass)) {
            return null;
        }

        $entity = $modelClass::withTrashed()->find($item->entity_id);

        if ($entity === null) {
            return null;
        }

        $number = $key === 'number';

        return match (true) {
            $entity instanceof PurchaseRequest => $number ? $entity->pr_number : $entity->currentVersion?->subject,
            $entity instanceof Budget => $number ? $entity->rab_number : $entity->currentVersion?->work_name,
            $entity instanceof Contract => $number ? $entity->po_number : $entity->work_name,
            $entity instanceof Lhstp => $number ? $entity->lhstp_number : $entity->currentVersion?->work_name_snapshot,
            default => null,
        };
    }
}
