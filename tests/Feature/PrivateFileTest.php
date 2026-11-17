<?php

use App\Models\User;
use App\Services\PrivateFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('requires authentication before downloading private files', function () {
    Storage::disk('local')->put('documents/rahasia.pdf', 'isi dokumen');

    $this->get(route('files.show', 'documents/rahasia.pdf'))
        ->assertRedirect(route('login'));
});

it('streams an existing private file to an authenticated user', function () {
    Storage::disk('local')->put('documents/rahasia.pdf', 'isi dokumen');

    $response = $this->actingAs($this->user)
        ->get(route('files.show', 'documents/rahasia.pdf'));

    $response->assertOk();
    expect($response->streamedContent())->toBe('isi dokumen');
});

it('returns not found for missing private files', function () {
    $this->actingAs($this->user)
        ->get(route('files.show', 'documents/tidak-ada.pdf'))
        ->assertNotFound();
});

it('stores uploads outside the public web root', function () {
    $file = UploadedFile::fake()->create('kontrak.pdf', 12, 'application/pdf');

    $path = app(PrivateFileService::class)->put('contracts', $file);

    Storage::disk('local')->assertExists($path);
    expect($path)->toStartWith('contracts/');
});

it('rejects path traversal in the private file service', function () {
    $files = app(PrivateFileService::class);

    expect($files->exists('../.env'))->toBeFalse()
        ->and(fn () => $files->download('../.env'))->toThrow(NotFoundHttpException::class);
});
