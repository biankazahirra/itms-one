<?php

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('redirects guests away from master data pages', function () {
    $this->get(route('master.vendors.index'))->assertRedirect(route('login'));
    $this->get(route('master.units.index'))->assertRedirect(route('login'));
    $this->get(route('master.departments.index'))->assertRedirect(route('login'));
});

it('creates a department and records activity', function () {
    $this->actingAs($this->user)
        ->post(route('master.departments.store'), ['name' => 'Finance'])
        ->assertRedirect(route('master.departments.index'))
        ->assertSessionHas('status');

    $department = Department::firstWhere('name', 'Finance');

    expect($department)->not->toBeNull()
        ->and($department->is_active)->toBeTrue();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->user->id,
        'entity_type' => 'department',
        'entity_id' => $department->id,
        'action' => 'created',
    ]);
});

it('rejects duplicate department names after normalization', function () {
    Department::create(['name' => 'Accounting Department']);

    $this->actingAs($this->user)
        ->from(route('master.departments.create'))
        ->post(route('master.departments.store'), ['name' => '  accounting   DEPARTMENT '])
        ->assertRedirect(route('master.departments.create'))
        ->assertSessionHasErrors('name');

    expect(Department::count())->toBe(1);
});

it('allows a department to keep its own name when edited', function () {
    $department = Department::create(['name' => 'ITMS']);

    $this->actingAs($this->user)
        ->put(route('master.departments.update', $department), [
            'name' => 'itms',
            'is_active' => '1',
        ])
        ->assertRedirect(route('master.departments.index'))
        ->assertSessionHasNoErrors();

    expect($department->fresh()->name)->toBe('itms');
});

it('creates a unit and rejects normalized duplicates', function () {
    $this->actingAs($this->user)
        ->post(route('master.units.store'), ['unit_name' => 'Paket'])
        ->assertRedirect(route('master.units.index'));

    $this->actingAs($this->user)
        ->from(route('master.units.create'))
        ->post(route('master.units.store'), ['unit_name' => 'PAKET '])
        ->assertSessionHasErrors('unit_name');

    expect(Unit::count())->toBe(1);
});

it('deactivates master data instead of deleting it', function () {
    $unit = Unit::create(['unit_name' => 'Orang']);

    $this->actingAs($this->user)
        ->put(route('master.units.update', $unit), [
            'unit_name' => 'Orang',
            'is_active' => '0',
        ])
        ->assertRedirect(route('master.units.index'));

    expect($unit->fresh()->is_active)->toBeFalse()
        ->and(Unit::count())->toBe(1);

    $this->assertDatabaseHas('activity_logs', [
        'entity_type' => 'unit',
        'entity_id' => $unit->id,
        'action' => 'deactivated',
    ]);
});

it('creates a vendor with optional fields', function () {
    $this->actingAs($this->user)
        ->post(route('master.vendors.store'), [
            'company_name' => 'PT Sumber Rejeki',
            'company_address' => 'Jl. Merdeka No. 1, Jakarta',
            'npwp' => '01.234.567.8-901.000',
            'phone' => '021-5551234',
            'email' => 'kontak@sumberrejeki.co.id',
        ])
        ->assertRedirect(route('master.vendors.index'));

    $vendor = Vendor::firstWhere('company_name', 'PT Sumber Rejeki');

    expect($vendor)->not->toBeNull()
        ->and($vendor->is_active)->toBeTrue()
        ->and($vendor->npwp)->toBe('01.234.567.8-901.000');
});

it('requires vendor company name and address', function () {
    $this->actingAs($this->user)
        ->from(route('master.vendors.create'))
        ->post(route('master.vendors.store'), [
            'company_name' => '',
            'company_address' => '',
        ])
        ->assertSessionHasErrors(['company_name', 'company_address']);

    expect(Vendor::count())->toBe(0);
});

it('warns on a duplicate vendor name and saves only after confirmation', function () {
    Vendor::create([
        'company_name' => 'PT Maju Bersama',
        'company_address' => 'Jl. Sudirman 10',
    ]);

    $payload = [
        'company_name' => 'pt  maju bersama',
        'company_address' => 'Jl. Sudirman 10',
    ];

    $this->actingAs($this->user)
        ->from(route('master.vendors.create'))
        ->post(route('master.vendors.store'), $payload)
        ->assertRedirect(route('master.vendors.create'))
        ->assertSessionHas('duplicate_warning');

    expect(Vendor::count())->toBe(1);

    $this->actingAs($this->user)
        ->post(route('master.vendors.store'), [...$payload, 'confirm_duplicate' => '1'])
        ->assertRedirect(route('master.vendors.index'));

    expect(Vendor::count())->toBe(2);
});

it('does not warn when an edited vendor keeps its own name', function () {
    $vendor = Vendor::create([
        'company_name' => 'PT Karya Utama',
        'company_address' => 'Jl. Thamrin 5',
    ]);

    $this->actingAs($this->user)
        ->put(route('master.vendors.update', $vendor), [
            'company_name' => 'PT Karya Utama',
            'company_address' => 'Jl. Thamrin 5',
            'is_active' => '1',
        ])
        ->assertRedirect(route('master.vendors.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('duplicate_warning');

    expect($vendor->fresh()->company_name)->toBe('PT Karya Utama');
});

it('filters vendor list by search and status', function () {
    Vendor::create(['company_name' => 'PT Alpha', 'company_address' => 'A', 'is_active' => true]);
    Vendor::create(['company_name' => 'PT Beta', 'company_address' => 'B', 'is_active' => false]);

    $this->actingAs($this->user)
        ->get(route('master.vendors.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('PT Beta')
        ->assertDontSee('PT Alpha');

    $this->actingAs($this->user)
        ->get(route('master.vendors.index', ['q' => 'Alpha']))
        ->assertOk()
        ->assertSee('PT Alpha')
        ->assertDontSee('PT Beta');
});

it('writes an activity entry for vendor creation with actor and snapshot', function () {
    $this->actingAs($this->user)
        ->post(route('master.vendors.store'), [
            'company_name' => 'PT Log Uji',
            'company_address' => 'Jl. Uji 1',
        ]);

    $log = ActivityLog::where('entity_type', 'vendor')->firstOrFail();

    expect($log->user_id)->toBe($this->user->id)
        ->and($log->action)->toBe('created')
        ->and($log->after_values['company_name'])->toBe('PT Log Uji')
        ->and($log->created_at)->not->toBeNull();
});
