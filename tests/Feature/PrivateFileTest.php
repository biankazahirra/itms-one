<?php

use App\Services\PrivateFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    Storage::fake('local');
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
