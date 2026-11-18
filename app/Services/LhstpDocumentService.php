<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\Lhstp;
use App\Models\LhstpVersion;
use App\Models\User;
use App\Support\Format;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use RuntimeException;

class LhstpDocumentService
{
    /**
     * Usable text width of the supplied template in points.
     */
    private const TEXT_WIDTH = 475.3;

    public function __construct(
        private readonly PrivateFileService $files,
        private readonly ActivityLogService $activity,
    ) {}

    /**
     * Generate the version-specific LHSTP document and store its metadata.
     *
     * A version keeps one document per format; an existing one is returned
     * unchanged so older generated files are never overwritten.
     */
    public function generate(Lhstp $lhstp, LhstpVersion $version, User $actor, string $format): GeneratedDocument
    {
        $format = strtoupper($format) === GeneratedDocument::FORMAT_DOCX
            ? GeneratedDocument::FORMAT_DOCX
            : GeneratedDocument::FORMAT_PDF;

        $existing = GeneratedDocument::query()
            ->where('entity_type', GeneratedDocument::ENTITY_LHSTP)
            ->where('entity_id', $lhstp->id)
            ->where('version_id', $version->id)
            ->where('document_format', $format)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $version->loadMissing(['lhstp', 'termin']);

        $data = $this->viewData($lhstp, $version);

        $output = $format === GeneratedDocument::FORMAT_DOCX
            ? $this->renderDocx($data)
            : $this->renderPdf($data);

        $filename = $this->filename($lhstp, $version, $format);
        $path = "lhstp/{$lhstp->id}/versions/{$version->id}/{$filename}";

        $this->files->putContents($path, $output);

        $document = GeneratedDocument::create([
            'entity_type' => GeneratedDocument::ENTITY_LHSTP,
            'entity_id' => $lhstp->id,
            'version_id' => $version->id,
            'document_format' => $format,
            'document_kind' => 'final',
            'original_filename' => $filename,
            'storage_path' => $path,
            'mime_type' => $format === GeneratedDocument::FORMAT_DOCX
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/pdf',
            'file_size' => strlen($output),
            'checksum_sha256' => hash('sha256', $output),
            'generated_by' => $actor->id,
            'generated_at' => now(),
        ]);

        $this->activity->record(
            'lhstp',
            $lhstp->id,
            'document_generated',
            "Membuat {$format} {$lhstp->lhstp_number} {$version->label()}.",
            null,
            ['version_id' => $version->id, 'filename' => $filename],
            $actor,
        );

        return $document;
    }

    /**
     * Values shared by the PDF and DOCX renderers.
     *
     * @return array<string, mixed>
     */
    private function viewData(Lhstp $lhstp, LhstpVersion $version): array
    {
        $rate = rtrim(rtrim(Format::angka($version->ppn_rate, 4), '0'), ',');

        $referenceType = $version->reference_document_type === Lhstp::REFERENCE_OTHER
            ? ($version->reference_document_name ?: Lhstp::REFERENCE_OTHER)
            : (string) preg_replace('/^Surat\s+/i', '', $version->reference_document_type);

        return [
            'number' => $lhstp->lhstp_number,
            'workName' => $version->work_name_snapshot,
            'poNumber' => $version->po_number_snapshot,
            'contractDate' => Format::tanggal($version->contract_date_snapshot),
            'vendorName' => $version->vendor_name_snapshot,
            'referenceType' => $referenceType,
            'referenceNumber' => $version->reference_document_number,
            'referenceDate' => Format::tanggal($version->reference_document_date),
            'workPeriod' => $version->work_period,
            'opnameDate' => Format::tanggal($version->opname_date),
            'contractMonths' => $version->contractMonths(),
            'contractStart' => Format::tanggal($version->contract_start_snapshot),
            'contractEnd' => Format::tanggal($version->contract_end_snapshot),
            'realizationDays' => $version->realization_days,
            'realizationStart' => Format::tanggal($version->realization_start),
            'realizationEnd' => Format::tanggal($version->realization_end),
            'workConclusion' => $version->work_conclusion,
            'paymentTerms' => $version->payment_terms,
            'contractValue' => Format::angka($version->contract_value_snapshot, 2),
            'paymentAmount' => Format::angka($version->payment_amount, 2),
            'dpp' => Format::angka($version->dpp_lain, 2),
            'ppn' => Format::angka($version->ppn_amount, 2),
            'total' => Format::angka($version->total_payment, 2),
            'ppnRate' => $rate,
            'terbilang' => $version->terbilang,
            'costCenter' => $version->cost_center,
            'representativeName' => $version->representative_name_snapshot,
            'representativePosition' => $version->representative_position_snapshot,
            'signatureDate' => Format::tanggal($version->signature_date),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderPdf(array $data): string
    {
        $html = view('documents.lhstp-pdf', $data)->render();

        $options = new Options(['isRemoteEnabled' => false]);

        // Dompdf file:// URIs must live under Options::chroot; widen it so the
        // system Calibri files can be registered. Falls back to bundled fonts.
        if (is_dir('C:\\Windows\\Fonts')) {
            $options->setChroot(['C:\\Windows\\Fonts']);
        }

        $dompdf = new Dompdf($options);
        $this->registerCalibriFonts($dompdf);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 595.3, 841.9]);
        $dompdf->render();

        $output = $dompdf->output();

        if (! str_starts_with($output, '%PDF')) {
            throw new RuntimeException('PDF rendering produced no valid document.');
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderDocx(array $data): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'paperSize' => 'A4',
            'marginTop' => Converter::pointToTwip(28.95),
            'marginBottom' => Converter::pointToTwip(40),
            'marginLeft' => Converter::pointToTwip(72),
            'marginRight' => Converter::pointToTwip(48),
        ]);

        $logo = resource_path('assets/lhstp-logo.png');
        $logoOptions = [
            'width' => Converter::pointToPixel(502.5),
            'height' => Converter::pointToPixel(45.4),
        ];

        $section->addImage($logo, $logoOptions + ['indent' => Converter::pointToTwip(-18)]);

        $paragraph = fn (string $text, array $font = [], array $style = []) => $section->addText(
            $text,
            ['name' => 'Calibri', 'size' => 11] + $font,
            ['lineHeight' => 1.22, 'spaceAfter' => 0] + $style,
        );

        $section->addText('LAPORAN HASIL SERAH TERIMA PEKERJAAN', ['bold' => true, 'size' => 14], [
            'alignment' => 'center',
            'spaceBefore' => Converter::pointToTwip(6),
        ]);
        $section->addText('Nomor: '.$data['number'], ['size' => 12], ['alignment' => 'center']);

        $paragraph('Kepada Yth.', [], ['spaceBefore' => Converter::pointToTwip(14)]);
        $paragraph('Manager Accounting');
        $paragraph('PT Krakatau Sarana Properti', [], ['spaceAfter' => Converter::pointToTwip(13.5)]);

        $fields = $section->addTable();
        foreach ([
            'PEKERJAAN' => $data['workName'],
            'NO. PURCHASE ORDER' => $data['poNumber'],
            'TANGGAL KONTRAK' => $data['contractDate'],
            'KONTRAKTOR' => $data['vendorName'],
        ] as $label => $value) {
            $row = $fields->addRow();
            $this->tableText($row->addCell(Converter::pointToTwip(127.6)), $label);
            $this->tableText($row->addCell(Converter::pointToTwip(14.2)), ':');
            $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 141.8)), $value);
        }

        $paragraph(
            'Sesuai dengan surat '.$data['referenceType'].' dari '.$data['vendorName']
            .' Nomor: '.$data['referenceNumber'].' tanggal '.$data['referenceDate']
            .' Perihal Penyelesaian Pekerjaan periode '.$data['workPeriod'].' (terlampir),'
            .' pihak-pihak yang bertanda tangan di bawah ini melaporkan bahwa:',
            [],
            ['alignment' => 'both', 'spaceBefore' => Converter::pointToTwip(28.4)],
        );

        $paragraph(
            'Pada hari '.$data['opnameDate'].' telah dilaksanakan penelitian/opname pekerjaan bersama'
            .' di lapangan dengan hasil sebagai berikut:',
            [],
            ['alignment' => 'both', 'spaceBefore' => Converter::pointToTwip(2.8)],
        );

        $results = $section->addTable();
        $this->numberedRow($results, '1.', 'Hasil pekerjaan dapat diterima sebagai Penyelesaian pekerjaan periode '.$data['workPeriod'].'.');
        $this->numberedRow($results, '2.', 'Jangka waktu penyelesaian pekerjaan sesuai dengan Purchase Order Nomor : '
            .$data['poNumber'].' adalah '.$data['contractMonths'].' bulan, terhitung dari tanggal '
            .$data['contractStart'].' sampai dengan tanggal '.$data['contractEnd'].'.');
        $this->numberedRow($results, '3.', 'Realisasi waktu pekerjaan adalah '.$data['realizationDays']
            .' hari kalender terhitung tanggal '.$data['realizationStart'].' sampai dengan tanggal '.$data['realizationEnd'].'.');

        $paragraph(
            'Kesimpulan dari hasil penelitian/opname pekerjaan di lapangan sebagai berikut:',
            [],
            ['alignment' => 'both', 'spaceBefore' => Converter::pointToTwip(13.5)],
        );

        $conclusions = $section->addTable();
        $this->numberedRow($conclusions, '1.', $data['workConclusion']);
        $this->numberedRow($conclusions, '2.', $data['paymentTerms']);

        $paragraph(
            'Pembayaran yang berhak diterima '.$data['vendorName'].' adalah sebagai berikut:',
            ['size' => 10],
            ['spaceBefore' => Converter::pointToTwip(12.4)],
        );

        $payments = $section->addTable();
        $this->paymentRow($payments, 'Nilai Kontrak', $data['contractValue']);
        $this->paymentRow($payments, 'Pembayaran', $data['paymentAmount'], $data['workName']);
        $this->paymentRow($payments, 'DPP LAIN (Rp '.$data['paymentAmount'].' x 11/12)', $data['dpp']);
        $this->paymentRow($payments, 'PPN ( Rp '.$data['dpp'].' x '.$data['ppnRate'].'%)', $data['ppn']);
        $this->paymentRow($payments, 'Yang Harus Dibayarkan Termasuk PPN', $data['total']);

        $paragraph(
            'Terbilang: ( '.$data['terbilang'].' )',
            ['size' => 10],
            ['spaceBefore' => Converter::pointToTwip(11)],
        );
        $paragraph(
            '(Harga tersebut sudah termasuk PPN '.$data['ppnRate'].'%)',
            ['size' => 10],
            ['indent' => Converter::pointToTwip(42.6)],
        );

        $paragraph(
            'Dari tanggal diterbitkannya LHSTP ini, Pembayaran dibebankan kepada Cost Center Nomor : '.$data['costCenter'],
            ['size' => 11],
            ['spaceBefore' => Converter::pointToTwip(53.8)],
        );

        $section->addPageBreak();
        $section->addImage($logo, $logoOptions + ['indent' => Converter::pointToTwip(-18)]);

        $paragraph(
            'Berdasarkan perincian pekerjaan dan perhitungan pembayaran di atas, maka yang bertanda tangan di bawah ini:',
            [],
            ['alignment' => 'both', 'spaceBefore' => Converter::pointToTwip(18)],
        );

        $first = $section->addTable();
        $this->partyRows($first, '1', 'Tb. Benny Kurniawan',
            'IT & Management System Manager PT Krakatau Sarana Properti yang dalam hal ini bertindak'
            .' untuk dan atas nama PT Krakatau Sarana Properti yang selanjutnya disebut PIHAK PERTAMA');

        $second = $section->addTable();
        $this->partyRows($second, '2', $data['representativeName'],
            $data['representativePosition'].' '.$data['vendorName'].' yang dalam hal ini bertindak untuk'
            .' dan atas nama '.$data['vendorName'].' yang selanjutnya disebut PIHAK KEDUA', true);

        $paragraph(
            'Demikian Laporan Hasil Serah Terima Pekerjaan ini dibuat dengan sebenarnya untuk dapat'
            .' dipergunakan sebagaimana mestinya.',
            [],
            ['alignment' => 'both', 'spaceBefore' => Converter::pointToTwip(26.9)],
        );

        $paragraph('Cilegon, '.$data['signatureDate'], [], [
            'alignment' => 'right',
            'spaceBefore' => Converter::pointToTwip(40.3),
            'spaceAfter' => Converter::pointToTwip(13.4),
        ]);

        $signatures = $section->addTable();
        foreach ([
            ['PIHAK KEDUA', $data['vendorName']],
            ['PIHAK PERTAMA', 'PT Krakatau Sarana Properti'],
        ] as [$party, $company]) {
            $row = $signatures->addRow();
            $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH / 2)), $party, 'center');
            $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH / 2)), $company, 'center');
        }
        $signatures->addRow(Converter::pointToTwip(53.8));
        foreach ([
            [$data['representativeName'], 'Tb. Benny Kurniawan'],
            [$data['representativePosition'], 'IT & Management System Manager'],
        ] as [$secondName, $firstName]) {
            $row = $signatures->addRow();
            $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH / 2)), $secondName, 'center');
            $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH / 2)), $firstName, 'center');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'lhstp-docx-');

        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary file for the DOCX document.');
        }

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($temporary);
            $output = file_get_contents($temporary);
        } finally {
            @unlink($temporary);
        }

        if ($output === false || $output === '') {
            throw new RuntimeException('DOCX rendering produced no valid document.');
        }

        return $output;
    }

    private function tableText(Cell $cell, string $text, ?string $alignment = null): void
    {
        $cell->addText($text, ['name' => 'Calibri', 'size' => 11], [
            'lineHeight' => 1.22,
            'spaceAfter' => 0,
        ] + ($alignment === null ? [] : ['alignment' => $alignment]));
    }

    /**
     * @param  Table  $table
     */
    private function numberedRow($table, string $number, string $text): void
    {
        $row = $table->addRow();
        $this->tableText($row->addCell(Converter::pointToTwip(21.3)), $number);
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 21.3)), $text);
    }

    /**
     * @param  Table  $table
     */
    private function paymentRow($table, string $label, string $amount, ?string $subLabel = null): void
    {
        $row = $table->addRow();
        $cell = $row->addCell(Converter::pointToTwip(300));
        $this->tableText($cell, $label, null);
        $this->tableText($cell, $subLabel ?? '', null);
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 300)), '= Rp '.$amount, 'right');
    }

    /**
     * @param  Table  $table
     */
    private function partyRows($table, string $number, string $name, string $position, bool $spaced = false): void
    {
        $row = $table->addRow(Converter::pointToTwip($spaced ? 34 : 0));
        $this->tableText($row->addCell(Converter::pointToTwip(13.5)), $number);
        $this->tableText($row->addCell(Converter::pointToTwip(46.7)), 'Nama');
        $this->tableText($row->addCell(Converter::pointToTwip(13)), ':');
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 73.2)), $name);

        $row = $table->addRow();
        $this->tableText($row->addCell(Converter::pointToTwip(13.5)), '');
        $this->tableText($row->addCell(Converter::pointToTwip(46.7)), 'Jabatan');
        $this->tableText($row->addCell(Converter::pointToTwip(13)), ':');
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 73.2)), $position);
    }

    private function filename(Lhstp $lhstp, LhstpVersion $version, string $format): string
    {
        $base = strtoupper(str_replace('/', '-', $lhstp->lhstp_number));
        $base = (string) preg_replace('/^LHSTP-/', '', $base);

        return 'LHSTP-'.$base.'_'.$version->label().'.'.strtolower($format);
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
