<?php

use App\Models\Contract;
use App\Models\Lhstp;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DashboardQueryService;
use App\Services\LhstpService;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->vendor = Vendor::create([
        'company_name' => 'PT Dashboard Vendor',
        'company_address' => 'Jl. Dashboard No. 1',
        'is_active' => true,
    ]);

    $this->contract = dashboardContract();
});

function dashboardContract(array $overrides = []): Contract
{
    return Contract::create(array_replace([
        'po_number' => 'PO/DASH/001',
        'contract_date' => '2026-11-01',
        'vendor_id' => test()->vendor->id,
        'work_name' => 'Pekerjaan Dashboard',
        'representative_name' => 'Budi',
        'representative_position' => 'Direktur',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'contract_value' => '120000000',
        'ppn_status' => Contract::PPN_EXCLUDED,
        'payment_method' => Contract::PAYMENT_MONTHLY,
        'monthly_payment_amount' => '10000000',
        'status' => Contract::STATUS_ACTIVE,
        'created_by' => test()->user->id,
        'updated_by' => test()->user->id,
    ], $overrides));
}

function dashboardLhstp(string $number, string $date, bool $finalize = true, ?Contract $contract = null): Lhstp
{
    $data = [
        'contract_id' => ($contract ?? test()->contract)->id,
        'lhstp_number' => $number,
        'lhstp_date' => $date,
        'work_period' => 'November 2026',
        'realization_start' => '2026-11-01',
        'realization_end' => '2026-11-05',
        'reference_document_type' => Lhstp::REFERENCE_BERITA_ACARA,
        'reference_document_number' => 'BA/'.$number,
        'reference_document_date' => '2026-11-06',
        'payment_amount' => '12000000',
        'ppn_rate' => '12',
        'work_conclusion' => 'Hasil pekerjaan diterima.',
        'payment_terms' => 'Pembayaran bulanan.',
        'cost_center' => Lhstp::DEFAULT_COST_CENTER,
        'confirm_exceed' => '1',
    ];

    $service = app(LhstpService::class);
    $lhstp = $service->create($data, test()->user);

    if ($finalize) {
        $service->finalize($lhstp, test()->user);
    }

    return $lhstp->fresh();
}

it('redirects guests from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('counts records by status for every module', function () {
    PurchaseRequest::create([
        'pr_number' => 'PR-000001',
        'status' => PurchaseRequest::STATUS_DRAFT,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    dashboardContract(['po_number' => 'PO/DASH/002', 'status' => Contract::STATUS_ARCHIVED, 'archived_at' => now()]);

    dashboardLhstp('LHSTP/DASH/001', '2026-11-10');

    $counts = app(DashboardQueryService::class)->documentCounts();

    expect($counts['purchase_request']['Draft'])->toBe(1)
        ->and($counts['contract'][Contract::STATUS_ACTIVE])->toBe(1)
        ->and($counts['contract'][Contract::STATUS_ARCHIVED])->toBe(1)
        ->and($counts['lhstp']['Final'])->toBe(1)
        ->and($counts['budget']['Draft'])->toBe(0);
});

it('lists only non archived contracts ending within the next 30 days', function () {
    $soon = dashboardContract(['po_number' => 'PO/SOON/001', 'end_date' => now()->addDays(10)->toDateString()]);
    dashboardContract(['po_number' => 'PO/LATER/001', 'end_date' => now()->addDays(60)->toDateString()]);
    dashboardContract(['po_number' => 'PO/PAST/001', 'end_date' => now()->subDays(2)->toDateString()]);
    dashboardContract([
        'po_number' => 'PO/ARCHIVED/001',
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => Contract::STATUS_ARCHIVED,
        'archived_at' => now(),
    ]);

    $contracts = app(DashboardQueryService::class)->endingContracts();

    expect($contracts->pluck('po_number')->all())->toBe(['PO/SOON/001'])
        ->and($contracts->first()->is($soon))->toBeTrue();
});

it('sums only final LHSTP current versions using the LHSTP date', function () {
    dashboardLhstp('LHSTP/DASH/NOV', '2026-11-10');
    dashboardLhstp('LHSTP/DASH/OCT', '2026-10-10');
    dashboardLhstp('LHSTP/DASH/DRAFT', '2026-11-12', finalize: false);

    $service = app(DashboardQueryService::class);

    $november = $service->paymentSummary(now()->setDate(2026, 11, 1)->startOfDay(), now()->setDate(2026, 11, 30)->endOfDay());
    $october = $service->paymentSummary(now()->setDate(2026, 10, 1)->startOfDay(), now()->setDate(2026, 10, 31)->endOfDay());

    expect($november['count'])->toBe(1)
        ->and($november['total'])->toBe('13320000.00')
        ->and($october['count'])->toBe(1)
        ->and($october['total'])->toBe('13320000.00');
});

it('renders the dashboard with summaries, ending contracts, activity, and quick actions', function () {
    $this->travelTo('2026-11-17 10:00:00');

    dashboardLhstp('LHSTP/DASH/RENDER', '2026-11-10');
    dashboardContract(['po_number' => 'PO/ENDING/001', 'end_date' => '2026-11-30']);

    $response = $this->actingAs($this->user)->get(route('dashboard', ['month' => '2026-11']));

    $response->assertOk()
        ->assertSee('Ringkasan Status')
        ->assertSee('Kontrak Berakhir dalam 30 Hari')
        ->assertSee('PO/ENDING/001')
        ->assertSee('Total Pembayaran LHSTP')
        ->assertSee('Rp 13.320.000,00')
        ->assertSee('Aktivitas Terbaru')
        ->assertSee('Buat PR')
        ->assertSee('Buat RAB')
        ->assertSee('Buat Kontrak')
        ->assertSee('Buat LHSTP');
});

it('supports a custom date range for the payment summary', function () {
    $this->travelTo('2026-11-17 10:00:00');

    dashboardLhstp('LHSTP/DASH/RANGE', '2026-10-10');

    $this->actingAs($this->user)
        ->get(route('dashboard', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
        ->assertOk()
        ->assertSee('1 Oktober 2026 – 31 Oktober 2026')
        ->assertSee('Rp 13.320.000,00');
});

it('shows empty states when the dashboard has no data', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada kontrak yang berakhir dalam 30 hari.')
        ->assertSee('Belum ada aktivitas.');
});
