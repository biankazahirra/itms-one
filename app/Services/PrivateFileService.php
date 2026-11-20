<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateFileService
{
    /**
     * Disk rooted outside the public web root.
     */
    private const DISK = 'local';

    /**
     * Store an uploaded file under a private directory and return its path.
     */
    public function put(string $directory, UploadedFile $file, ?string $filename = null): string
    {
        $filename ??= Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs(trim($directory, '/'), $filename, self::DISK);
    }

    public function exists(string $path): bool
    {
        $safePath = $this->safePath($path);

        return $safePath !== null && Storage::disk(self::DISK)->exists($safePath);
    }

    /**
     * Write generated contents to a private path.
     */
    public function putContents(string $path, string $contents): void
    {
        $safePath = $this->safePath($path);

        if ($safePath === null) {
            throw new InvalidArgumentException("Unsafe private file path [{$path}].");
        }

        Storage::disk(self::DISK)->put($safePath, $contents);
    }

    public function delete(string $path): bool
    {
        $safePath = $this->safePath($path);

        return $safePath !== null && Storage::disk(self::DISK)->delete($safePath);
    }

    /**
     * Stream a private file to the current authorized request.
     */
    public function download(string $path, ?string $displayName = null): StreamedResponse
    {
        $safePath = $this->safePath($path);

        abort_if($safePath === null || ! Storage::disk(self::DISK)->exists($safePath), 404);

        return Storage::disk(self::DISK)->download($safePath, $displayName);
    }

    /**
     * Display a supported private file inline in the browser.
     */
    public function preview(string $path, ?string $displayName = null): StreamedResponse
    {
        $safePath = $this->safePath($path);

        abort_if($safePath === null || ! Storage::disk(self::DISK)->exists($safePath), 404);

        return Storage::disk(self::DISK)->response($safePath, $displayName, [], 'inline');
    }

    /**
     * Reject absolute paths and traversal segments.
     */
    private function safePath(string $path): ?string
    {
        $normalized = trim(str_replace('\\', '/', $path));

        if ($normalized === '' || str_starts_with($normalized, '/')) {
            return null;
        }

        if (in_array('..', explode('/', $normalized), true)) {
            return null;
        }

        return $normalized;
    }
}
