<?php

use App\Models\Department;
use App\Models\GeneratedDocument;
use App\Models\PrDocument;
use App\Models\PurchaseRequest;
use App\Models\RecycleBinItem;
use App\Models\User;
use App\Services\PrDocumentArchiveService;
use App\Services\PrivateFileService;
use App\Services\PurchaseRequestService;
use App\Services\RecycleBinService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();

    Department::create(['name' => 'Accounting Department']);
    Department::create(['name' => 'ITMS']);

    $this->purchaseRequest = app(PurchaseRequestService::class)->create([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Laptop Tim IT',
        'pr_date' => '2026-11-17',
        'category' => 'Fixed Asset',
        'items' => [
            ['budget_name' => 'Laptop 14 inci', 'quantity' => '2', 'unit_price' => '8500000'],
        ],
    ], $this->user);
});

function uploadDocument(PurchaseRequest $purchaseRequest, array $overrides = [], ?UploadedFile $file = null)
{
    return test()->actingAs(test()->user)->post(
        route('purchase-requests.pr-documents.store', $purchaseRequest),
        array_replace([
            'category' => PrDocument::CATEGORIES[0],
            'notes' => 'Formulir pengajuan',
            'file' => $file ?? UploadedFile::fake()->createWithContent('formulir.pdf', "%PDF-1.4\n%%EOF"),
        ], $overrides),
    );
}

it('stores an uploaded PR document privately with metadata', function () {
    uploadDocument($this->purchaseRequest)->assertRedirect();

    $document = PrDocument::firstOrFail();

    expect($document->purchase_request_id)->toBe($this->purchaseRequest->id)
        ->and($document->category)->toBe('PR Submission Form')
        ->and($document->original_filename)->toBe('formulir.pdf')
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->notes)->toBe('Formulir pengajuan')
        ->and($document->storage_path)->toStartWith("pr-documents/{$this->purchaseRequest->id}/")
        ->and($document->checksum_sha256)->toHaveLength(64);

    Storage::disk('local')->assertExists($document->storage_path);

    $this->assertDatabaseHas('activity_logs', [
        'entity_type' => 'purchase_request',
        'entity_id' => $this->purchaseRequest->id,
        'action' => 'document_uploaded',
    ]);
});

it('rejects an unsupported document category', function () {
    uploadDocument($this->purchaseRequest, ['category' => 'Kategori Karangan'])->assertSessionHasErrors('category');

    expect(PrDocument::count())->toBe(0);
});

it('rejects unsupported formats and oversized files', function () {
    uploadDocument($this->purchaseRequest, [], UploadedFile::fake()->create('dokumen.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->assertSessionHasErrors('file');

    uploadDocument($this->purchaseRequest, [], UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf'))
        ->assertSessionHasErrors('file');

    expect(PrDocument::count())->toBe(0);
});

it('requires authentication for archive actions', function () {
    $this->post(route('purchase-requests.pr-documents.store', $this->purchaseRequest), [])
        ->assertRedirect(route('login'));
});

it('previews PDF, JPG and PNG inline and downloads the file', function () {
    uploadDocument($this->purchaseRequest)->assertRedirect();
    $document = PrDocument::firstOrFail();

    $this->actingAs($this->user)
        ->get(route('purchase-requests.pr-documents.preview', [$this->purchaseRequest, $document]))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=formulir.pdf');

    $download = $this->actingAs($this->user)
        ->get(route('purchase-requests.pr-documents.download', [$this->purchaseRequest, $document]))
        ->assertOk();

    expect($download->streamedContent())->toContain('%PDF');
});

it('rejects access to a document through another PR', function () {
    uploadDocument($this->purchaseRequest)->assertRedirect();
    $document = PrDocument::firstOrFail();

    $other = app(PurchaseRequestService::class)->create([
        'recipient_department_id' => Department::where('name', 'Accounting Department')->value('id'),
        'subject' => 'Pengadaan Meja',
        'pr_date' => '2026-11-17',
        'category' => 'Office Supplies',
        'items' => [
            ['budget_name' => 'Meja kerja', 'quantity' => '1', 'unit_price' => '1500000'],
        ],
    ], $this->user);

    $this->actingAs($this->user)
        ->get(route('purchase-requests.pr-documents.preview', [$other, $document]))
        ->assertNotFound();
});

it('moves a document to the recycle bin and restores the file', function () {
    uploadDocument($this->purchaseRequest)->assertRedirect();
    $document = PrDocument::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('purchase-requests.pr-documents.destroy', [$this->purchaseRequest, $document]))
        ->assertRedirect();

    expect(PrDocument::withTrashed()->find($document->id)->trashed())->toBeTrue();

    $item = RecycleBinItem::where('entity_type', 'pr_document')
        ->where('entity_id', $document->id)
        ->firstOrFail();

    app(RecycleBinService::class)->restore($item);

    expect(PrDocument::find($document->id))->not->toBeNull();
    Storage::disk('local')->assertExists($document->storage_path);
});

it('removes metadata and the physical file on permanent purge', function () {
    uploadDocument($this->purchaseRequest)->assertRedirect();
    $document = PrDocument::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('purchase-requests.pr-documents.destroy', [$this->purchaseRequest, $document]));

    $item = RecycleBinItem::where('entity_type', 'pr_document')
        ->where('entity_id', $document->id)
        ->firstOrFail();

    expect(app(RecycleBinService::class)->purgeItem($item))->toBeNull()
        ->and(PrDocument::withTrashed()->find($document->id))->toBeNull();

    Storage::disk('local')->assertMissing($document->storage_path);
});

it('never modifies finalized PR versions or generated documents', function () {
    app(PurchaseRequestService::class)->finalize($this->purchaseRequest, $this->user);

    $version = $this->purchaseRequest->fresh()->currentVersion;
    $path = "documents/{$this->purchaseRequest->id}/versi-1.pdf";
    Storage::disk('local')->put($path, 'pdf historis');

    $generated = GeneratedDocument::create([
        'entity_type' => GeneratedDocument::ENTITY_PURCHASE_REQUEST,
        'entity_id' => $this->purchaseRequest->id,
        'version_id' => $version->id,
        'document_format' => GeneratedDocument::FORMAT_PDF,
        'document_kind' => 'final',
        'original_filename' => 'PR-final.pdf',
        'storage_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 12,
        'checksum_sha256' => hash('sha256', 'pdf historis'),
        'generated_by' => $this->user->id,
        'generated_at' => now(),
    ]);

    uploadDocument($this->purchaseRequest)->assertRedirect();
    $document = PrDocument::firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('purchase-requests.pr-documents.destroy', [$this->purchaseRequest, $document]));

    expect($version->fresh()->finalized_at)->not->toBeNull()
        ->and(GeneratedDocument::find($generated->id))->not->toBeNull();

    Storage::disk('local')->assertExists($path);
});

it('shows the archive on the PR detail page with preview for allowed formats', function () {
    uploadDocument($this->purchaseRequest)->assertRedirect();
    $document = PrDocument::firstOrFail();

    $this->actingAs($this->user)
        ->get(route('purchase-requests.show', $this->purchaseRequest))
        ->assertOk()
        ->assertSee($document->original_filename)
        ->assertSee('Pratinjau')
        ->assertSee('Arsip Dokumen PR');
});

it('blocks PR purge while uploaded documents still exist', function () {
    uploadDocument($this->purchaseRequest)->assertRedirect();
    $document = PrDocument::firstOrFail();

    app(PurchaseRequestService::class)->deleteDraft($this->purchaseRequest, $this->user);
    $prItem = RecycleBinItem::where('entity_type', 'purchase_request')
        ->where('entity_id', $this->purchaseRequest->id)
        ->firstOrFail();

    expect(app(RecycleBinService::class)->purgeItem($prItem))->not->toBeNull()
        ->and(PurchaseRequest::withTrashed()->find($this->purchaseRequest->id))->not->toBeNull();

    app(PrDocumentArchiveService::class)->delete($document, $this->user);
    $documentItem = RecycleBinItem::where('entity_type', 'pr_document')
        ->where('entity_id', $document->id)
        ->firstOrFail();

    expect(app(RecycleBinService::class)->purgeItem($documentItem))->toBeNull()
        ->and(app(RecycleBinService::class)->purgeItem($prItem))->toBeNull()
        ->and(PurchaseRequest::withTrashed()->find($this->purchaseRequest->id))->toBeNull();
});

it('keeps the private file service blocking path traversal', function () {
    $files = app(PrivateFileService::class);

    expect($files->exists('../.env'))->toBeFalse();
});
