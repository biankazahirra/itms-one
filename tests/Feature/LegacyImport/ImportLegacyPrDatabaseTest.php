<?php

use App\Models\PurchaseRequest;
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
