<?php

use App\Models\Contract;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Storage;

function fixturePath(): string
{
    return base_path('tests/Fixtures/legacy-pr-mapper.sql');
}

it('dry run writes no database rows', function () {
    Storage::fake('local');

    $this->artisan('data:import-legacy-pr-db', ['--file' => fixturePath()])
        ->assertSuccessful();

    expect(PurchaseRequest::count())->toBe(0)
        ->and(Storage::disk('local')->files('imports'))->not->toBeEmpty();
});

it('lists skipped rows with reasons in the report', function () {
    Storage::fake('local');

    $this->artisan('data:import-legacy-pr-db', ['--file' => fixturePath()])->assertSuccessful();
    $csv = Storage::disk('local')->get(Storage::disk('local')->files('imports')[0]);

    expect($csv)->toContain('kontrak,1,skipped')
        ->toContain('Duplicate PO; contract ID 2 kept');
});

it('applies mapped rows through application services', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->artisan('data:import-legacy-pr-db', [
            '--file' => fixturePath(), '--apply' => true,
            '--user' => $user->id, '--backup-confirmed' => true,
        ])
        ->assertSuccessful();

    expect(Vendor::where('company_name', 'PT Kosera Karya Prima')->count())->toBe(1)
        ->and(Department::where('name', 'Accounting')->exists())->toBeTrue()
        ->and(PurchaseRequest::where('pr_number', 'PR-000001')->exists())->toBeTrue()
        ->and(Contract::count())->toBe(0)
        ->and(PurchaseRequest::first()->status)->toBe(PurchaseRequest::STATUS_DRAFT);
});

it('refuses to apply without backup confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->artisan('data:import-legacy-pr-db', [
            '--file' => fixturePath(), '--apply' => true, '--user' => $user->id,
        ])
        ->assertFailed();

    expect(PurchaseRequest::count())->toBe(0);
});

it('is idempotent', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    foreach (range(1, 2) as $run) {
        $this->actingAs($user)
            ->artisan('data:import-legacy-pr-db', [
                '--file' => fixturePath(), '--apply' => true,
                '--user' => $user->id, '--backup-confirmed' => true,
            ])
            ->assertSuccessful();
    }

    expect(PurchaseRequest::count())->toBe(2)
        ->and(Contract::count())->toBe(0);
});
