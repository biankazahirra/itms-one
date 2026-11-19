<?php

use App\Support\Legacy\SqlDumpReader;

it('parses quoted values with commas and escapes', function () {
    $rows = app(SqlDumpReader::class)->read(base_path('tests/Fixtures/legacy-pr-mini.sql'));

    expect($rows['purchase_requisition'][0]['perihal'])
        ->toBe('Lisensi Office 365, Autocad, & Power BI')
        ->and($rows['detail_anggaran'][0]['spesifikasi'])->toBe("Merk A\nBaris 2")
        ->and($rows['vendor'])->toHaveCount(2);
});
