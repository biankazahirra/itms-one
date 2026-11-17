<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Rules\UniqueNormalized;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');

        $departments = Department::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('master.departments.index', compact('departments', 'search', 'status'));
    }

    public function create(): View
    {
        return view('master.departments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $department = Department::create([
            'name' => $data['name'],
            'is_active' => true,
        ]);

        $this->activity->record(
            'department',
            $department->id,
            'created',
            "Menambahkan departemen {$department->name}.",
            null,
            ['name' => $department->name, 'is_active' => $department->is_active],
        );

        return redirect()
            ->route('master.departments.index')
            ->with('status', 'Departemen berhasil ditambahkan.');
    }

    public function edit(Department $department): View
    {
        return view('master.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $this->validated($request, $department);

        $before = ['name' => $department->name, 'is_active' => $department->is_active];

        $department->update([
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->activity->record(
            'department',
            $department->id,
            'updated',
            "Memperbarui departemen {$department->name}.",
            $before,
            ['name' => $department->name, 'is_active' => $department->is_active],
        );

        if ($before['is_active'] !== $department->is_active) {
            $this->activity->record(
                'department',
                $department->id,
                $department->is_active ? 'activated' : 'deactivated',
                $department->is_active
                    ? "Mengaktifkan departemen {$department->name}."
                    : "Menonaktifkan departemen {$department->name}.",
                ['is_active' => $before['is_active']],
                ['is_active' => $department->is_active],
            );
        }

        return redirect()
            ->route('master.departments.index')
            ->with('status', 'Departemen berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                new UniqueNormalized('departments', 'name', $department?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'Nama Departemen',
        ]);
    }
}
