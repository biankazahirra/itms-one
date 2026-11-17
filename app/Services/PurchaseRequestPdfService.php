<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestVersion;
use App\Models\User;
use Dompdf\Dompdf;
use RuntimeException;

class PurchaseRequestPdfService
{
    public function __construct(
        private readonly PrivateFileService $files,
        private readonly ActivityLogService $activity,
    ) {}

    /**
     * Generate the version-specific PR PDF and store its metadata.
     *
     * A version keeps one PDF; an existing document is returned unchanged.
     */
    public function generate(PurchaseRequest $purchaseRequest, PurchaseRequestVersion $version, User $actor): GeneratedDocument
    {
        $existing = GeneratedDocument::query()
            ->where('entity_type', GeneratedDocument::ENTITY_PURCHASE_REQUEST)
            ->where('entity_id', $purchaseRequest->id)
            ->where('version_id', $version->id)
            ->where('document_format', GeneratedDocument::FORMAT_PDF)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $version->loadMissing(['items', 'department']);

        $html = view('documents.pr-pdf', [
            'purchaseRequest' => $purchaseRequest,
            'version' => $version,
        ])->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();

        if (! str_starts_with($output, '%PDF')) {
            throw new RuntimeException('PDF rendering produced no valid document.');
        }

        $filename = $purchaseRequest->pr_number.'_'.$version->label().'.pdf';
        $path = "pr/{$purchaseRequest->id}/versions/{$version->id}/{$filename}";

        $this->files->putContents($path, $output);

        $document = GeneratedDocument::create([
            'entity_type' => GeneratedDocument::ENTITY_PURCHASE_REQUEST,
            'entity_id' => $purchaseRequest->id,
            'version_id' => $version->id,
            'document_format' => GeneratedDocument::FORMAT_PDF,
            'document_kind' => 'final',
            'original_filename' => $filename,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($output),
            'checksum_sha256' => hash('sha256', $output),
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);

        $this->activity->record(
            'purchase_request',
            $purchaseRequest->id,
            'document_generated',
            "Membuat PDF {$purchaseRequest->pr_number} {$version->label()}.",
            null,
            ['version_id' => $version->id, 'filename' => $filename],
            $actor,
        );

        return $document;
    }
}
