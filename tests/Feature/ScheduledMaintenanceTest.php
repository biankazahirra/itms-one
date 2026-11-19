<?php

use App\Models\Contract;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Console\Scheduling\Schedule;

function maintenanceContract(array $overrides = []): Contract
{
    return Contract::create(array_replace([
        'po_number' => 'PO/MAINT/001',
        'contract_date' => '2026-11-01',
        'vendor_id' => Vendor::create([
            'company_name' => 'PT Maintenance Vendor',
            'company_address' => 'Jl. Uji No. 1',
            'is_active' => true,
        ])->id,
        'work_name' => 'Pekerjaan Uji Penjadwalan',
        'representative_name' => 'Budi',
        'representative_position' => 'Direktur',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'contract_value' => '1000000',
        'ppn_status' => Contract::PPN_EXCLUDED,
        'payment_method' => Contract::PAYMENT_MONTHLY,
        'monthly_payment_amount' => '100000',
        'status' => Contract::STATUS_ACTIVE,
        'created_by' => User::factory()->create()->id,
        'updated_by' => User::factory()->create()->id,
    ], $overrides));
}

it('marks contracts past their end date as inactive without touching active or archived ones', function () {
    $expired = maintenanceContract(['end_date' => now()->subDay()->toDateString()]);
    $ongoing = maintenanceContract([
        'po_number' => 'PO/MAINT/002',
        'end_date' => now()->addMonth()->toDateString(),
    ]);
    $archived = maintenanceContract([
        'po_number' => 'PO/MAINT/003',
        'end_date' => now()->subDay()->toDateString(),
        'status' => Contract::STATUS_ARCHIVED,
        'archived_at' => now(),
    ]);

    $this->artisan('contracts:sync-status')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(Contract::STATUS_INACTIVE)
        ->and($ongoing->fresh()->status)->toBe(Contract::STATUS_ACTIVE)
        ->and($archived->fresh()->status)->toBe(Contract::STATUS_ARCHIVED);
});

it('schedules the recycle bin purge and contract status reconciliation daily', function () {
    require_once base_path('routes/console.php');

    $events = app(Schedule::class)->events();
    $commands = collect($events)->map(fn ($event) => (string) $event->command)->implode(' ');

    expect($commands)->toContain('recycle-bin:purge')
        ->toContain('contracts:sync-status');
});
