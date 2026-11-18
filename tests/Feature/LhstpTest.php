<?php

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\GeneratedDocument;
use App\Models\Lhstp;
use App\Models\LhstpVersionTermin;
use App\Models\RecycleBinItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\LhstpService;
use App\Support\Terbilang;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->vendor = Vendor::create([
        'company_name' => 'PT Mitra Teknologi',
        'company_address' => 'Jl. Industri No. 1, Cilegon',
        'is_active' => true,
    ]);

    $this->contract = contractFor();
});

function contractFor(array $overrides = []): Contract
{
    return Contract::create(array_replace([
        'po_number' => 'PO/ITMS/2026/101',
        'contract_date' => '2026-11-01',
        'vendor_id' => test()->vendor->id,
        'work_name' => 'Pengadaan Jaringan Kantor',
        'representative_name' => 'Budi Santoso',
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

function lhstpPayload(array $overrides = []): array
{
    return array_replace([
        'contract_id' => test()->contract->id,
        'lhstp_number' => 'LHSTP/ITMS/2026/001',
        'lhstp_date' => '2026-11-17',
        'work_period' => 'November 2026',
        'realization_start' => '2026-11-01',
        'realization_end' => '2026-11-05',
        'reference_document_type' => Lhstp::REFERENCE_BERITA_ACARA,
        'reference_document_number' => 'BA/ITMS/2026/001',
        'reference_document_date' => '2026-11-06',
        'payment_amount' => '12000000',
        'ppn_rate' => '12',
        'work_conclusion' => 'Hasil pekerjaan dapat diterima sebagai Penyelesaian pekerjaan periode November 2026.',
        'payment_terms' => 'Berdasarkan ketentuan Purchase Order, pembayaran dilakukan per bulan sebesar Rp 12.000.000.',
        'cost_center' => Lhstp::DEFAULT_COST_CENTER,
        'confirm_exceed' => '1',
    ], $overrides);
}

it('redirects guests to the login page', function () {
    $this->get(route('lhstp.index'))->assertRedirect(route('login'));
});

it('creates a draft LHSTP with contract snapshots and PPN excluded calculation', function () {
    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload())
        ->assertRedirect();

    $lhstp = Lhstp::firstOrFail();
    $version = $lhstp->currentVersion;

    expect($lhstp->status)->toBe(Lhstp::STATUS_DRAFT)
        ->and($version->version_number)->toBe(1)
        ->and($version->po_number_snapshot)->toBe('PO/ITMS/2026/101')
        ->and($version->vendor_name_snapshot)->toBe('PT Mitra Teknologi')
        ->and($version->representative_name_snapshot)->toBe('Budi Santoso')
        ->and($version->payment_method_snapshot)->toBe(Contract::PAYMENT_MONTHLY)
        ->and($version->ppn_status_snapshot)->toBe(Contract::PPN_EXCLUDED)
        ->and($version->realization_days)->toBe(5)
        ->and($version->dpp_lain)->toBe('11000000.00')
        ->and($version->ppn_amount)->toBe('1320000.00')
        ->and($version->total_payment)->toBe('13320000.00')
        ->and($version->ppn_rate)->toBe('12.0000')
        ->and($version->terbilang)->toBe('Tiga Belas Juta Tiga Ratus Dua Puluh Ribu Rupiah');

    expect(ActivityLog::where('entity_type', 'lhstp')->where('entity_id', $lhstp->id)->count())->toBe(1);
});

it('extracts PPN from an amount that already includes it', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload([
        'contract_id' => contractFor([
            'po_number' => 'PO/ITMS/2026/102',
            'ppn_status' => Contract::PPN_INCLUDED,
        ])->id,
        'payment_amount' => '13320000',
    ]));

    $version = Lhstp::firstOrFail()->currentVersion;

    expect($version->dpp_lain)->toBe('11000000.00')
        ->and($version->ppn_amount)->toBe('1320000.00')
        ->and($version->total_payment)->toBe('13320000.00');
});

it('rounds only the total payment to whole rupiah and keeps intermediates unrounded', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload([
        'contract_id' => contractFor([
            'po_number' => 'PO/ITMS/2026/103',
            'ppn_status' => Contract::PPN_INCLUDED,
        ])->id,
        'payment_amount' => '13123356.89',
        'ppn_rate' => '11',
    ]));

    $version = Lhstp::firstOrFail()->currentVersion;

    expect($version->total_payment)->toBe('13123357.00');
});

it('applies an edited PPN rate to the calculation and snapshot', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload([
        'contract_id' => contractFor([
            'po_number' => 'PO/ITMS/2026/104',
            'ppn_status' => Contract::PPN_INCLUDED,
        ])->id,
        'payment_amount' => '13320000',
        'ppn_rate' => '11',
    ]));

    $version = Lhstp::firstOrFail()->currentVersion;

    expect($version->ppn_rate)->toBe('11.0000')
        ->and($version->dpp_lain)->toBe('11091597.27')
        ->and($version->ppn_amount)->toBe('1220075.70')
        ->and($version->total_payment)->toBe('13320000.00');
});

it('validates required LHSTP inputs and the realization date order', function () {
    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload([
            'payment_amount' => '0',
            'realization_end' => '2026-10-31',
            'reference_document_number' => '',
            'reference_document_date' => '',
            'ppn_rate' => '101',
        ]))
        ->assertSessionHasErrors([
            'payment_amount',
            'realization_end',
            'reference_document_number',
            'reference_document_date',
            'ppn_rate',
        ]);
});

it('requires the other document name for another document type', function () {
    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload([
            'reference_document_type' => Lhstp::REFERENCE_OTHER,
            'reference_document_name' => '',
        ]))
        ->assertSessionHasErrors('reference_document_name');
});

it('rejects a duplicate LHSTP number but allows the same number on a revision', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload());
    $lhstp = Lhstp::firstOrFail();

    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload([
            'contract_id' => contractFor(['po_number' => 'PO/ITMS/2026/105'])->id,
        ]))
        ->assertSessionHasErrors('lhstp_number');

    expect(Lhstp::count())->toBe(1);

    $this->actingAs($this->user)->post(route('lhstp.finalize', $lhstp), ['generate_document' => '0']);
    $this->actingAs($this->user)->post(route('lhstp.revise', $lhstp))->assertRedirect();

    expect($lhstp->fresh()->versions()->count())->toBe(2)
        ->and($lhstp->lhstp_number)->toBe('LHSTP/ITMS/2026/001');
});

it('blocks an archived contract and allows an inactive contract', function () {
    $archived = contractFor([
        'po_number' => 'PO/ITMS/2026/106',
        'status' => Contract::STATUS_ARCHIVED,
        'archived_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload(['contract_id' => $archived->id]))
        ->assertSessionHasErrors('contract_id');

    $this->actingAs($this->user->fresh())
        ->post(route('lhstp.store'), lhstpPayload([
            'contract_id' => $archived->id,
            'lhstp_number' => 'LHSTP/ITMS/2026/002',
        ]))
        ->assertSessionHasErrors('contract_id');

    $inactive = contractFor([
        'po_number' => 'PO/ITMS/2026/107',
        'status' => Contract::STATUS_INACTIVE,
        'end_date' => '2026-10-31',
    ]);

    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload([
            'contract_id' => $inactive->id,
            'lhstp_number' => 'LHSTP/ITMS/2026/003',
        ]))
        ->assertSessionHasNoErrors();
});

it('stores the selected termin and blocks a termin consumed by another final LHSTP', function () {
    $contract = contractFor([
        'po_number' => 'PO/ITMS/2026/108',
        'payment_method' => Contract::PAYMENT_TERMIN,
        'monthly_payment_amount' => null,
    ]);

    $termin = $contract->termins()->create([
        'termin_name' => 'Termin 1',
        'percentage' => '50.0000',
        'nominal_amount' => '60000000.00',
        'sort_order' => 1,
    ]);

    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload([
        'contract_id' => $contract->id,
        'payment_method' => null,
        'contract_termin_id' => $termin->id,
        'payment_amount' => '60000000',
    ]))->assertSessionHasNoErrors();

    $first = Lhstp::firstOrFail();
    $this->actingAs($this->user)->post(route('lhstp.finalize', $first), ['generate_document' => '0']);

    expect(LhstpVersionTermin::where('contract_termin_id', $termin->id)->count())->toBe(1);

    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload([
            'contract_id' => $contract->id,
            'lhstp_number' => 'LHSTP/ITMS/2026/004',
            'contract_termin_id' => $termin->id,
            'payment_amount' => '60000000',
        ]))
        ->assertSessionHasErrors('contract_termin_id');
});

it('lets a revision reuse its own termin', function () {
    $contract = contractFor([
        'po_number' => 'PO/ITMS/2026/109',
        'payment_method' => Contract::PAYMENT_TERMIN,
        'monthly_payment_amount' => null,
    ]);

    $termin = $contract->termins()->create([
        'termin_name' => 'Termin 1',
        'percentage' => '100.0000',
        'nominal_amount' => '120000000.00',
        'sort_order' => 1,
    ]);

    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload([
        'contract_id' => $contract->id,
        'contract_termin_id' => $termin->id,
        'payment_amount' => '120000000',
    ]))->assertSessionHasNoErrors();

    $lhstp = Lhstp::firstOrFail();
    $this->actingAs($this->user)->post(route('lhstp.finalize', $lhstp), ['generate_document' => '0']);
    $this->actingAs($this->user)->post(route('lhstp.revise', $lhstp))->assertRedirect();

    expect($lhstp->fresh()->currentVersion->termin->contract_termin_id)->toBe($termin->id);
});

it('finalizes, keeps snapshots immutable after contract edits, and archives', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload());
    $lhstp = Lhstp::firstOrFail();

    $this->actingAs($this->user)->post(route('lhstp.finalize', $lhstp), ['generate_document' => '0'])->assertRedirect();

    $lhstp->refresh();
    expect($lhstp->status)->toBe(Lhstp::STATUS_FINAL)
        ->and($lhstp->finalized_at)->not->toBeNull()
        ->and($lhstp->currentVersion->finalized_at)->not->toBeNull();

    $this->contract->forceFill(['work_name' => 'Nama Pekerjaan Baru', 'contract_value' => '999000000'])->save();

    $version = $lhstp->fresh()->currentVersion;
    expect($version->work_name_snapshot)->toBe('Pengadaan Jaringan Kantor')
        ->and($version->contract_value_snapshot)->toBe('120000000.00');

    $this->actingAs($this->user)->post(route('lhstp.archive', $lhstp))->assertRedirect();
    expect($lhstp->fresh()->status)->toBe(Lhstp::STATUS_ARCHIVED);
});

it('moves a draft LHSTP to the recycle bin and blocks deleting a finalized one', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload(['lhstp_number' => 'LHSTP/ITMS/2026/010']));
    $draft = Lhstp::firstOrFail();

    $this->actingAs($this->user)->delete(route('lhstp.destroy', $draft))->assertRedirect();
    expect($draft->fresh()->trashed())->toBeTrue()
        ->and(RecycleBinItem::where('entity_type', 'lhstp')->where('entity_id', $draft->id)->count())->toBe(1);

    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload(['lhstp_number' => 'LHSTP/ITMS/2026/011']));
    $final = Lhstp::where('lhstp_number', 'LHSTP/ITMS/2026/011')->firstOrFail();
    $this->actingAs($this->user)->post(route('lhstp.finalize', $final), ['generate_document' => '0']);

    $this->actingAs($this->user)->delete(route('lhstp.destroy', $final))->assertSessionHasErrors('status');
    expect($final->fresh()->trashed())->toBeFalse();
});

it('warns about duplicate references, overlapping periods, and dates outside the contract without blocking', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload(['lhstp_number' => 'LHSTP/ITMS/2026/020']));

    $second = $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload([
        'lhstp_number' => 'LHSTP/ITMS/2026/021',
        'realization_start' => '2026-11-04',
        'realization_end' => '2026-11-10',
    ]));

    $second->assertSessionHasNoErrors()->assertSessionHas('warning');

    $warnings = (string) session('warning');
    expect($warnings)->toContain('Nomor dokumen referensi')
        ->and($warnings)->toContain('periode pekerjaan');

    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload([
            'lhstp_number' => 'LHSTP/ITMS/2026/022',
            'realization_start' => '2025-12-01',
            'realization_end' => '2025-12-05',
        ]))
        ->assertSessionHasNoErrors();

    expect((string) session('warning'))->toContain('di luar periode kontrak');
});

it('requires confirmation when LHSTP payments exceed the contract value', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload([
        'lhstp_number' => 'LHSTP/ITMS/2026/030',
        'payment_amount' => '120000000',
    ]));

    $this->actingAs($this->user)
        ->post(route('lhstp.store'), lhstpPayload([
            'lhstp_number' => 'LHSTP/ITMS/2026/031',
            'payment_amount' => '120000000',
            'confirm_exceed' => '',
        ]))
        ->assertSessionHasErrors('confirm_exceed');

    expect(Lhstp::count())->toBe(1);
});

it('renders LHSTP list, detail, create, and edit pages', function () {
    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload());
    $lhstp = Lhstp::firstOrFail();

    $this->actingAs($this->user)->get(route('lhstp.index'))->assertOk()->assertSee('LHSTP/ITMS/2026/001');
    $this->actingAs($this->user)->get(route('lhstp.show', $lhstp))->assertOk()->assertSee('Budi Santoso');
    $this->actingAs($this->user)->get(route('lhstp.create'))->assertOk();
    $this->actingAs($this->user)->get(route('lhstp.edit', $lhstp))->assertOk();
});

it('generates version specific PDF and DOCX documents that can be downloaded again later', function () {
    Storage::fake('local');

    $this->actingAs($this->user)->post(route('lhstp.store'), lhstpPayload());
    $lhstp = Lhstp::firstOrFail();
    $this->actingAs($this->user)->post(route('lhstp.finalize', $lhstp), ['generate_document' => '0']);

    $this->actingAs($this->user)
        ->post(route('lhstp.documents.store', $lhstp), ['document_format' => 'PDF'])
        ->assertRedirect();
    $this->actingAs($this->user)
        ->post(route('lhstp.documents.store', $lhstp), ['document_format' => 'DOCX'])
        ->assertRedirect();

    $version = $lhstp->fresh()->currentVersion;
    $pdf = GeneratedDocument::where('version_id', $version->id)->where('document_format', 'PDF')->firstOrFail();
    $docx = GeneratedDocument::where('version_id', $version->id)->where('document_format', 'DOCX')->firstOrFail();

    Storage::disk('local')->assertExists($pdf->storage_path);
    Storage::disk('local')->assertExists($docx->storage_path);

    expect(Storage::disk('local')->get($pdf->storage_path))->toStartWith('%PDF')
        ->and($docx->checksum_sha256)->toHaveLength(64)
        ->and($docx->file_size)->toBeGreaterThan(1000);

    $xml = (function () use ($docx) {
        $zip = new ZipArchive;
        $path = Storage::disk('local')->path($docx->storage_path);
        $zip->open($path);

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $entries[$name] = $zip->getFromIndex($i);
        }

        $zip->close();

        return $entries;
    })();

    expect($xml)->toHaveKey('word/document.xml')
        ->and($xml['word/document.xml'])->toContain('LAPORAN HASIL SERAH TERIMA PEKERJAAN');

    $this->actingAs($this->user)
        ->get(route('documents.download', $pdf))
        ->assertOk();

    $this->actingAs($this->user)
        ->post(route('lhstp.documents.store', $lhstp), ['document_format' => 'PDF'])
        ->assertRedirect();

    expect(GeneratedDocument::where('version_id', $version->id)->count())->toBe(2);
});

it('calculates realization days inclusively', function () {
    expect(app(LhstpService::class)->realizationDays('2026-11-01', '2026-11-05'))->toBe(5)
        ->and(app(LhstpService::class)->realizationDays('2026-11-01', '2026-11-01'))->toBe(1);
});

it('formats rupiah amounts as Indonesian terbilang', function () {
    expect(Terbilang::rupiah('13320000.00'))->toBe('Tiga Belas Juta Tiga Ratus Dua Puluh Ribu Rupiah')
        ->and(Terbilang::rupiah('1000.00'))->toBe('Seribu Rupiah')
        ->and(Terbilang::rupiah('0.00'))->toBe('Nol Rupiah');
});
