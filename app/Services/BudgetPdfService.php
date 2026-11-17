<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\GeneratedDocument;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class BudgetPdfService
{
    public function __construct(
        private readonly PrivateFileService $files,
        private readonly ActivityLogService $activity,
    ) {}

    /**
     * Generate the version-specific RAB PDF and store its metadata.
     *
     * A version keeps one PDF; an existing document is returned unchanged.
     */
    public function generate(Budget $budget, BudgetVersion $version, User $actor): GeneratedDocument
    {
        $existing = GeneratedDocument::query()
            ->where('entity_type', GeneratedDocument::ENTITY_BUDGET)
            ->where('entity_id', $budget->id)
            ->where('version_id', $version->id)
            ->where('document_format', GeneratedDocument::FORMAT_PDF)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $version->loadMissing(['items.unit', 'budget']);

        $html = view('documents.rab-pdf', [
            'budget' => $budget,
            'version' => $version,
        ])->render();

        $options = new Options(['isRemoteEnabled' => false]);

        // Dompdf file:// URIs must live under Options::chroot; widen it so the
        // system Calibri files can be registered. Falls back to bundled fonts.
        if (is_dir('C:\\Windows\\Fonts')) {
            $options->setChroot(['C:\\Windows\\Fonts']);
        }

        $dompdf = new Dompdf($options);
        $this->registerCalibriFonts($dompdf);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 792.13, 612.08]);
        $dompdf->render();

        $output = $dompdf->output();

        if (! str_starts_with($output, '%PDF')) {
            throw new RuntimeException('PDF rendering produced no valid document.');
        }

        $filename = $budget->rab_number.'_'.$version->label().'.pdf';
        $path = "rab/{$budget->id}/versions/{$version->id}/{$filename}";

        $this->files->putContents($path, $output);

        $document = GeneratedDocument::create([
            'entity_type' => GeneratedDocument::ENTITY_BUDGET,
            'entity_id' => $budget->id,
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
            'budget',
            $budget->id,
            'document_generated',
            "Membuat PDF {$budget->rab_number} {$version->label()}.",
            null,
            ['version_id' => $version->id, 'filename' => $filename],
            $actor,
        );

        return $document;
    }

    /**
     * Register the system Calibri font files with Dompdf.
     *
     * Falls back silently to the built-in DejaVu Sans family when the
     * Windows font files are not present.
     */
    private function registerCalibriFonts(Dompdf $dompdf): void
    {
        $regular = str_replace('\\', '/', 'C:\\Windows\\Fonts\\calibri.ttf');
        $bold = str_replace('\\', '/', 'C:\\Windows\\Fonts\\calibrib.ttf');

        $metrics = $dompdf->getFontMetrics();

        if (is_file($regular)) {
            $metrics->registerFont(
                ['family' => 'Calibri', 'style' => 'normal', 'weight' => 'normal'],
                'file://'.$regular,
            );
        }

        if (is_file($bold)) {
            $metrics->registerFont(
                ['family' => 'Calibri', 'style' => 'normal', 'weight' => 'bold'],
                'file://'.$bold,
            );
        }
    }
}
