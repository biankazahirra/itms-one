<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\Lhstp;
use App\Models\LhstpVersion;
use App\Models\User;
use App\Support\Format;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\Element\AbstractContainer;
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
        private readonly LhstpPaymentCalculator $calculator,
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
            'opnameDate' => Format::tanggalHari($version->opname_date),
            'contractMonths' => $version->contractMonths(),
            'contractStart' => Format::tanggal($version->contract_start_snapshot),
            'contractEnd' => Format::tanggal($version->contract_end_snapshot),
            'realizationDays' => $version->realization_days,
            'realizationStart' => Format::tanggal($version->realization_start),
            'realizationEnd' => Format::tanggal($version->realization_end),
            'workConclusion' => $version->work_conclusion,
            'paymentTerms' => $version->payment_terms,
            'contractValue' => Format::angka($version->contract_value_snapshot, 2),
            'paymentAmount' => Format::angka($this->calculator->paymentBeforePpn(
                (string) $version->payment_amount,
                (string) $version->ppn_rate,
                (string) $version->ppn_status_snapshot,
            ), 2),
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

        $section->addText('LAPORAN HASIL SERAH TERIMA PEKERJAAN', [
            'bold' => true,
            'size' => 14,
            'underline' => 'single',
        ], [
            'alignment' => 'center',
            'spaceBefore' => Converter::pointToTwip(6),
        ]);
        $this->richText($section, [
            ['text' => 'Nomor: '.$data['number'], 'bold' => true],
        ], ['size' => 12], ['alignment' => 'center']);

        $this->richText($section, [['text' => 'Kepada Yth.', 'bold' => true]], ['size' => 11], ['spaceBefore' => Converter::pointToTwip(14)]);
        $this->richText($section, [['text' => 'Manager Accounting', 'bold' => true]], ['size' => 11]);
        $this->richText($section, [['text' => 'PT Krakatau Sarana Properti', 'bold' => true]], ['size' => 11], ['spaceAfter' => Converter::pointToTwip(13.5)]);

        $fields = $section->addTable();
        foreach ([
            'PEKERJAAN' => $data['workName'],
            'NO. PURCHASE ORDER' => $data['poNumber'],
            'TANGGAL KONTRAK' => $data['contractDate'],
            'KONTRAKTOR' => $data['vendorName'],
        ] as $label => $value) {
            $row = $fields->addRow();
            $this->tableText($row->addCell(Converter::pointToTwip(127.6)), $label, null, true);
            $this->tableText($row->addCell(Converter::pointToTwip(14.2)), ':', null, true);
            $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 141.8)), $value, null, true);
        }
        $paragraph('', [], [
            'borderBottomSize' => 4,
            'borderBottomColor' => '000000',
            'borderBottomStyle' => 'single',
        ]);

        $this->richText($section, [
            ['text' => 'Sesuai dengan surat '.$data['referenceType'].' dari '],
            ['text' => $data['vendorName'], 'bold' => true],
            ['text' => ' Nomor: '],
            ['text' => $data['referenceNumber'], 'bold' => true],
            ['text' => ' tanggal '],
            ['text' => $data['referenceDate'], 'bold' => true],
            ['text' => ' Perihal Penyelesaian Pekerjaan periode '],
            ['text' => $data['workPeriod'], 'bold' => true],
            ['text' => ' (terlampir), pihak-pihak yang bertanda tangan di bawah ini melaporkan bahwa:'],
        ], ['size' => 11], ['alignment' => 'both', 'spaceBefore' => Converter::pointToTwip(28.4)]);

        $paragraph(
            'Pada hari '.$data['opnameDate'].' telah dilaksanakan penelitian/opname pekerjaan bersama'
            .' di lapangan dengan hasil sebagai berikut:',
            [],
            ['alignment' => 'both', 'spaceBefore' => Converter::pointToTwip(2.8)],
        );

        $results = $section->addTable();
        $this->numberedRow($results, '1.', [
            ['text' => 'Hasil pekerjaan dapat diterima sebagai Penyelesaian pekerjaan '],
            ['text' => 'periode '.$data['workPeriod'], 'bold' => true],
            ['text' => '.'],
        ]);
        $this->numberedRow($results, '2.', [
            ['text' => 'Jangka waktu penyelesaian pekerjaan sesuai dengan '],
            ['text' => 'Purchase Order Nomor :', 'bold' => true],
            ['text' => ' '],
            ['text' => $data['poNumber'], 'bold' => true],
            ['text' => ' adalah '.$data['contractMonths'].' bulan, '],
            ['text' => 'terhitung dari tanggal', 'bold' => true],
            ['text' => ' '],
            ['text' => $data['contractStart'], 'bold' => true],
            ['text' => ' '],
            ['text' => 'sampai dengan tanggal', 'bold' => true],
            ['text' => ' '],
            ['text' => $data['contractEnd'], 'bold' => true],
            ['text' => '.'],
        ]);
        $this->numberedRow($results, '3.', [
            ['text' => 'Realisasi waktu pekerjaan adalah '],
            ['text' => $data['realizationDays'], 'bold' => true],
            ['text' => ' hari kalender terhitung '],
            ['text' => 'tanggal '.$data['realizationStart'], 'bold' => true],
            ['text' => ' sampai dengan tanggal '],
            ['text' => $data['realizationEnd'], 'bold' => true],
            ['text' => '.'],
        ]);

        $paragraph(
            'Kesimpulan dari hasil penelitian/opname pekerjaan di lapangan sebagai berikut:',
            [],
            ['alignment' => 'both', 'spaceBefore' => Converter::pointToTwip(13.5)],
        );

        $conclusions = $section->addTable();
        $this->numberedRow($conclusions, '1.', $data['workConclusion']);
        $this->numberedRow($conclusions, '2.', $data['paymentTerms']);

        $this->richText($section, [
            ['text' => 'Pembayaran yang berhak diterima '],
            ['text' => $data['vendorName'], 'bold' => true],
            ['text' => ' adalah sebagai berikut:'],
        ], ['size' => 10], ['spaceBefore' => Converter::pointToTwip(12.4)]);

        $payments = $section->addTable();
        $this->paymentRow($payments, 'Nilai Kontrak', $data['contractValue'], null, true);
        $this->paymentRow($payments, 'Pembayaran', $data['paymentAmount'], $data['workName']);
        $this->paymentRow($payments, 'DPP LAIN (Rp '.$data['paymentAmount'].' x 11/12)', $data['dpp']);
        $this->paymentRow($payments, 'PPN (Rp '.$data['dpp'].' x '.$data['ppnRate'].'%)', $data['ppn']);
        $this->paymentRow($payments, 'Yang Harus Dibayarkan Termasuk PPN', $data['total'], null, true);

        $this->richText($section, [
            ['text' => 'Terbilang: ('.$data['terbilang'].')', 'italic' => true],
        ], ['size' => 10], ['spaceBefore' => Converter::pointToTwip(11)]);
        $this->richText($section, [
            ['text' => '(Harga tersebut sudah termasuk PPN '.$data['ppnRate'].'%)', 'italic' => true],
        ], ['size' => 10], ['indent' => Converter::pointToTwip(42.6)]);

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
        $this->partyRows($first, '1', 'Tb. Benny Kurniawan', [
            ['text' => 'IT & Management System Manager '],
            ['text' => 'PT Krakatau Sarana Properti', 'bold' => true],
            ['text' => ' yang dalam hal ini bertindak untuk dan atas nama '],
            ['text' => 'PT Krakatau Sarana Properti', 'bold' => true],
            ['text' => ' yang selanjutnya disebut '],
            ['text' => 'PIHAK PERTAMA', 'bold' => true],
        ]);

        $second = $section->addTable();
        $this->partyRows($second, '2', $data['representativeName'], [
            ['text' => $data['representativePosition'].' '],
            ['text' => $data['vendorName'], 'bold' => true],
            ['text' => ' yang dalam hal ini bertindak untuk dan atas nama '],
            ['text' => $data['vendorName'], 'bold' => true],
            ['text' => ' yang selanjutnya disebut '],
            ['text' => 'PIHAK KEDUA', 'bold' => true],
        ], true);

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
        $row = $signatures->addRow();
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH / 2)), $data['representativeName'], 'center', false, [], true);
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH / 2)), 'Tb. Benny Kurniawan', 'center', false, [], true);

        $row = $signatures->addRow();
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH / 2)), $data['representativePosition'], 'center');
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH / 2)), 'IT & Management System Manager', 'center');

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

    private function tableText(
        Cell $cell,
        string $text,
        ?string $alignment = null,
        bool $bold = false,
        array $style = [],
        bool $underline = false,
    ): void {
        $font = ['name' => 'Calibri', 'size' => 11]
            + ($bold ? ['bold' => true] : [])
            + ($underline ? ['underline' => 'single'] : []);

        $cell->addText($text, $font, [
            'lineHeight' => 1.22,
            'spaceAfter' => 0,
        ] + ($alignment === null ? [] : ['alignment' => $alignment]) + $style);
    }

    /**
     * Add a paragraph made of styled segments, matching the template's
     * bold and italic runs.
     *
     * @param  AbstractContainer  $container
     * @param  array<int, array{text: string, bold?: bool, italic?: bool}>  $segments
     */
    private function richText($container, array $segments, array $font, array $style = []): void
    {
        $run = $container->addTextRun(['lineHeight' => 1.22, 'spaceAfter' => 0] + $style);

        foreach ($segments as $segment) {
            $run->addText($segment['text'], ['name' => 'Calibri'] + $font + array_filter([
                'bold' => $segment['bold'] ?? false,
                'italic' => $segment['italic'] ?? false,
            ]));
        }
    }

    /**
     * @param  Table  $table
     * @param  string|array<int, array{text: string, bold?: bool, italic?: bool}>  $text
     */
    private function numberedRow($table, string $number, string|array $text): void
    {
        $row = $table->addRow();
        $this->tableText($row->addCell(Converter::pointToTwip(21.3)), $number);
        $cell = $row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 21.3));

        if (is_array($text)) {
            $this->richText($cell, $text, ['size' => 11]);
        } else {
            $this->tableText($cell, $text);
        }
    }

    /**
     * @param  Table  $table
     */
    private function paymentRow($table, string $label, string $amount, ?string $subLabel = null, bool $bold = false): void
    {
        $row = $table->addRow();
        $cell = $row->addCell(Converter::pointToTwip(300));
        $this->tableText($cell, $label, null, $bold);
        $this->tableText($cell, $subLabel ?? '', null);
        $this->tableText($row->addCell(Converter::pointToTwip(12)), '=', null, $bold);
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 312)), 'Rp '.$amount, 'right', $bold);
    }

    /**
     * @param  Table  $table
     * @param  string|array<int, array{text: string, bold?: bool, italic?: bool}>  $position
     */
    private function partyRows($table, string $number, string $name, string|array $position, bool $spaced = false): void
    {
        $row = $table->addRow(Converter::pointToTwip($spaced ? 34 : 0));
        $this->tableText($row->addCell(Converter::pointToTwip(13.5)), $number);
        $this->tableText($row->addCell(Converter::pointToTwip(46.7)), 'Nama');
        $this->tableText($row->addCell(Converter::pointToTwip(13)), ':');
        $this->tableText($row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 73.2)), $name, null, true);

        $row = $table->addRow();
        $this->tableText($row->addCell(Converter::pointToTwip(13.5)), '');
        $this->tableText($row->addCell(Converter::pointToTwip(46.7)), 'Jabatan');
        $this->tableText($row->addCell(Converter::pointToTwip(13)), ':');
        $cell = $row->addCell(Converter::pointToTwip(self::TEXT_WIDTH - 73.2));

        if (is_array($position)) {
            $this->richText($cell, $position, ['size' => 11]);
        } else {
            $this->tableText($cell, $position);
        }
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
        $italic = str_replace('\\', '/', 'C:\\Windows\\Fonts\\calibrii.ttf');

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

        if (is_file($italic)) {
            $metrics->registerFont(
                ['family' => 'Calibri', 'style' => 'italic', 'weight' => 'normal'],
                'file://'.$italic,
            );
        }
    }
}
