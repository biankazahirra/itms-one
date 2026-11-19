<?php

use Illuminate\Support\Facades\Schema;

it('indexes the LHSTP date used by the dashboard payment filter', function () {
    $columns = collect(Schema::getIndexes('lhstp_versions'))
        ->flatMap(fn (array $index) => $index['columns'] ?? [])
        ->all();

    expect($columns)->toContain('lhstp_date');
});
