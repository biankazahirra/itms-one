<?php

use App\Support\Legacy\LegacyRowMapper;
use App\Support\Legacy\SqlDumpReader;

function rowsFromFixture(): array
{
    return app(SqlDumpReader::class)->read(base_path('tests/Fixtures/legacy-pr-mapper.sql'));
}

it('consolidates department and vendor names', function () {
    $result = app(LegacyRowMapper::class)->map(rowsFromFixture());

    expect($result['departments'])->toBe(['Accounting', 'Human Capital', 'ITMS'])
        ->and($result['vendors'][0]['legacy_ids'])->toBe(['1', '2']);
});

it('skips contracts whose payment rules are missing', function () {
    $result = app(LegacyRowMapper::class)->map(rowsFromFixture());

    expect($result['contracts'])->toBe([])
        ->and($result['exceptions'])->toContain([
            'table' => 'kontrak', 'source_id' => '1', 'reason' => 'Duplicate PO; contract ID 2 kept',
        ]);
});

it('skips PR with no valid items', function () {
    $result = app(LegacyRowMapper::class)->map(rowsFromFixture());

    expect(array_column($result['purchase_requests'], 'source_id'))->toBe(['30', '31']);
});

it('skips zero dates', function () {
    $result = app(LegacyRowMapper::class)->map(rowsFromFixture());

    expect(array_column($result['lhstps'], 'source_id'))->not->toContain('3');
});
