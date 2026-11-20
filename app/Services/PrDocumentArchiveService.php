<?php

namespace App\Services;

use App\Models\PrDocument;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrDocumentArchiveService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly PrivateFileService $files,
        private readonly RecycleBinService $recycleBin,
    ) {}

    /**
     * Store one user-uploaded document in private storage.
     *
     * @param  array<string, mixed>  $data
     */
    public function upload(PurchaseRequest $purchaseRequest, array $data, UploadedFile $file, User $actor): PrDocument
    {
        return DB::transaction(function () use ($purchaseRequest, $data, $file, $actor): PrDocument {
            $path = $this->files->put(
                "pr-documents/{$purchaseRequest->id}",
                $file,
                Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
            );

            $document = $purchaseRequest->prDocuments()->create([
                'category' => $data['category'],
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => (int) $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $actor->id,
                'uploaded_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->activity->record(
                'purchase_request',
                $purchaseRequest->id,
                'document_uploaded',
                "Menambahkan dokumen {$document->category} pada PR {$purchaseRequest->pr_number}.",
                null,
                ['original_filename' => $document->original_filename, 'category' => $document->category],
                $actor,
            );

            return $document;
        });
    }

    /**
     * Move a document into the shared recycle bin. The stored file stays
     * available until a permanent purge.
     */
    public function delete(PrDocument $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $this->recycleBin->register('pr_document', $document, $actor);
        });
    }
}
