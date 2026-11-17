<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\ActivityLogService;
use App\Support\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status', '');

        $vendors = Vendor::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('company_name', 'like', "%{$search}%")
                        ->orWhere('npwp', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('master.vendors.index', compact('vendors', 'search', 'status'));
    }

    public function create(): View
    {
        return view('master.vendors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $duplicates = $this->duplicates($data['company_name']);

        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_duplicate')) {
            return back()
                ->withInput()
                ->with('duplicate_warning', $this->duplicateWarning($duplicates));
        }

        $vendor = Vendor::create([
            'company_name' => $data['company_name'],
            'company_address' => $data['company_address'],
            'npwp' => $data['npwp'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_active' => true,
        ]);

        $this->activity->record(
            'vendor',
            $vendor->id,
            'created',
            "Menambahkan vendor {$vendor->company_name}.",
            null,
            $this->snapshot($vendor),
        );

        return redirect()
            ->route('master.vendors.index')
            ->with('status', 'Vendor berhasil ditambahkan.');
    }

    public function edit(Vendor $vendor): View
    {
        return view('master.vendors.edit', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $this->validated($request);

        $duplicates = $this->duplicates($data['company_name'], $vendor->id);

        if ($duplicates->isNotEmpty() && ! $request->boolean('confirm_duplicate')) {
            return back()
                ->withInput()
                ->with('duplicate_warning', $this->duplicateWarning($duplicates));
        }

        $before = $this->snapshot($vendor);

        $vendor->update([
            'company_name' => $data['company_name'],
            'company_address' => $data['company_address'],
            'npwp' => $data['npwp'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->activity->record(
            'vendor',
            $vendor->id,
            'updated',
            "Memperbarui vendor {$vendor->company_name}.",
            $before,
            $this->snapshot($vendor),
        );

        if ($before['is_active'] !== $vendor->is_active) {
            $this->activity->record(
                'vendor',
                $vendor->id,
                $vendor->is_active ? 'activated' : 'deactivated',
                $vendor->is_active
                    ? "Mengaktifkan vendor {$vendor->company_name}."
                    : "Menonaktifkan vendor {$vendor->company_name}.",
                ['is_active' => $before['is_active']],
                ['is_active' => $vendor->is_active],
            );
        }

        return redirect()
            ->route('master.vendors.index')
            ->with('status', 'Vendor berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'company_address' => ['required', 'string', 'max:5000'],
            'npwp' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'is_active' => ['sometimes', 'boolean'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ], [], [
            'company_name' => 'Nama Perusahaan',
            'company_address' => 'Alamat Perusahaan',
            'npwp' => 'NPWP',
            'phone' => 'Telepon',
            'email' => 'Email',
        ]);
    }

    /**
     * @return Collection<int, Vendor>
     */
    private function duplicates(string $companyName, ?int $ignoreId = null): Collection
    {
        $normalized = TextNormalizer::normalize($companyName);

        return Vendor::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->filter(fn (Vendor $vendor) => TextNormalizer::normalize($vendor->company_name) === $normalized)
            ->values();
    }

    /**
     * @param  Collection<int, Vendor>  $duplicates
     */
    private function duplicateWarning(Collection $duplicates): string
    {
        $names = $duplicates->take(3)->pluck('company_name')->implode(', ');

        return "Nama perusahaan mirip dengan vendor yang sudah ada: {$names}. "
            .'Periksa kembali data vendor. Centang "Tetap simpan" bila data ini memang berbeda.';
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Vendor $vendor): array
    {
        return $vendor->only(['company_name', 'company_address', 'npwp', 'phone', 'email', 'is_active']);
    }
}
