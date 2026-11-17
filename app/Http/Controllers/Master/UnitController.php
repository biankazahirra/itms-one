<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Rules\UniqueNormalized;
use App\Services\ActivityLogService;
use App\Support\TextNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');

        $units = Unit::query()
            ->when($search !== '', fn ($query) => $query->where('unit_name', 'like', "%{$search}%"))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('master.units.index', compact('units', 'search', 'status'));
    }

    public function create(): View
    {
        return view('master.units.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $unit = Unit::create([
            'unit_name' => $data['unit_name'],
            'is_active' => true,
        ]);

        $this->activity->record(
            'unit',
            $unit->id,
            'created',
            "Menambahkan satuan {$unit->unit_name}.",
            null,
            ['unit_name' => $unit->unit_name, 'is_active' => $unit->is_active],
        );

        return redirect()
            ->route('master.units.index')
            ->with('status', 'Satuan berhasil ditambahkan.');
    }

    public function edit(Unit $unit): View
    {
        return view('master.units.edit', compact('unit'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $data = $this->validated($request, $unit);

        $before = ['unit_name' => $unit->unit_name, 'is_active' => $unit->is_active];

        $unit->update([
            'unit_name' => $data['unit_name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->activity->record(
            'unit',
            $unit->id,
            'updated',
            "Memperbarui satuan {$unit->unit_name}.",
            $before,
            ['unit_name' => $unit->unit_name, 'is_active' => $unit->is_active],
        );

        if ($before['is_active'] !== $unit->is_active) {
            $this->activity->record(
                'unit',
                $unit->id,
                $unit->is_active ? 'activated' : 'deactivated',
                $unit->is_active
                    ? "Mengaktifkan satuan {$unit->unit_name}."
                    : "Menonaktifkan satuan {$unit->unit_name}.",
                ['is_active' => $before['is_active']],
                ['is_active' => $unit->is_active],
            );
        }

        return redirect()
            ->route('master.units.index')
            ->with('status', 'Satuan berhasil diperbarui.');
    }

    /**
     * Quick-add a unit from the RAB item form.
     *
     * Returns the existing normalized match when present; otherwise creates
     * a new active unit and logs the activity.
     */
    public function quickAdd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'unit_name' => ['required', 'string', 'max:80'],
        ], [], ['unit_name' => 'Nama Satuan']);

        $normalized = TextNormalizer::normalize($data['unit_name']);

        foreach (Unit::query()->cursor() as $unit) {
            if (TextNormalizer::normalize($unit->unit_name) === $normalized) {
                return response()->json(['id' => $unit->id, 'unit_name' => $unit->unit_name]);
            }
        }

        $unit = Unit::create([
            'unit_name' => $data['unit_name'],
            'is_active' => true,
        ]);

        $this->activity->record(
            'unit',
            $unit->id,
            'created',
            "Menambahkan satuan {$unit->unit_name}.",
            null,
            ['unit_name' => $unit->unit_name, 'is_active' => $unit->is_active],
        );

        return response()->json(['id' => $unit->id, 'unit_name' => $unit->unit_name], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'unit_name' => [
                'required',
                'string',
                'max:80',
                new UniqueNormalized('units', 'unit_name', $unit?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'unit_name' => 'Nama Satuan',
        ]);
    }
}
