<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>LHSTP {{ $number }}</title>
    <style>
        @page {
            size: 595.3pt 841.9pt;
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            font-family: Calibri, "dejavu sans", sans-serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
        }

        .page {
            position: relative;
            width: 595.3pt;
            height: 841.9pt;
        }

        .page + .page { page-break-before: always; }

        .letterhead {
            position: absolute;
            left: 54pt;
            top: 28.95pt;
            width: 502.5pt;
            height: 45.4pt;
        }

        .title {
            position: absolute;
            left: 0;
            top: 83pt;
            width: 595.3pt;
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
        }

        .nomor {
            position: absolute;
            left: 0;
            top: 100.8pt;
            width: 595.3pt;
            text-align: center;
            font-size: 12pt;
        }

        .rule-fields {
            position: absolute;
            left: 70.5pt;
            top: 254.82pt;
            width: 475.67pt;
            height: 0.5pt;
            background: #000;
        }

        .underline-title {
            text-decoration: underline;
        }

        .underline-signature {
            text-decoration: underline;
        }

        .content {
            position: absolute;
            left: 72pt;
            top: 132.6pt;
            width: 475.3pt;
        }

        p {
            margin: 0;
            line-height: 10.01pt;
            text-align: justify;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table td {
            padding: 0;
            vertical-align: top;
            line-height: 10.01pt;
        }

        .fields { margin-top: 13.5pt; }
        .fields td.label { width: 127.6pt; }
        .fields td.colon { width: 14.2pt; }

        .ref { margin-top: 28.4pt; }
        .opname { margin-top: 2.8pt; }

        table.items { margin-top: 0; }
        table.items td.no { width: 21.3pt; }
        table.items td.txt { text-align: justify; }

        .conclusion-title { margin-top: 13.5pt; }
        .conclusions { margin-top: 0; }

        .payment { margin-top: 12.4pt; font-size: 10pt; }
        .payment table td { line-height: 10.42pt; }
        .payment td.label { width: 300pt; }
        .payment td.amount { text-align: right; white-space: nowrap; }
        .payment .sub { padding-left: 18.2pt; }

        .terbilang { margin-top: 11pt; font-size: 10pt; line-height: 10.42pt; }
        .ppn-note { padding-left: 42.6pt; }

        .cost-center { margin-top: 53.8pt; }

        .page-2 { position: absolute; left: 72pt; top: 112.9pt; width: 475.3pt; }

        .parties { margin-top: 14.1pt; }
        .parties td.no { width: 13.5pt; }
        .parties td.label { width: 46.7pt; }
        .parties td.colon { width: 13pt; }

        .parties-gap { margin-top: 7.1pt; }

        .closing { margin-top: 26.9pt; }

        .signature-date {
            margin-top: 26.9pt;
            text-align: right;
            padding-right: 40pt;
        }

        table.signatures { margin-top: 13.4pt; }
        table.signatures td {
            width: 50%;
            text-align: center;
            line-height: 10.01pt;
        }

        .signature-space { height: 53.8pt; }
    </style>
</head>
<body>
<div class="page">
    <img class="letterhead" src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('assets/lhstp-logo.png'))) }}" alt="Logo">

    <div class="title"><span class="underline-title">LAPORAN HASIL SERAH TERIMA PEKERJAAN</span></div>
    <div class="nomor"><strong>Nomor: {{ $number }}</strong></div>

    <div class="content">
        <p><strong>Kepada Yth.<br>Manager Accounting<br>PT Krakatau Sarana Properti</strong></p>

        <table class="fields">
            <tr>
                <td class="label"><strong>PEKERJAAN</strong></td>
                <td class="colon"><strong>:</strong></td>
                <td><strong>{{ $workName }}</strong></td>
            </tr>
            <tr>
                <td class="label"><strong>NO. PURCHASE ORDER</strong></td>
                <td class="colon"><strong>:</strong></td>
                <td><strong>{{ $poNumber }}</strong></td>
            </tr>
            <tr>
                <td class="label"><strong>TANGGAL KONTRAK</strong></td>
                <td class="colon"><strong>:</strong></td>
                <td><strong>{{ $contractDate }}</strong></td>
            </tr>
            <tr>
                <td class="label"><strong>KONTRAKTOR</strong></td>
                <td class="colon"><strong>:</strong></td>
                <td><strong>{{ $vendorName }}</strong></td>
            </tr>
        </table>

        <p class="ref">
            Sesuai dengan surat {{ $referenceType }} dari <strong>{{ $vendorName }}</strong>
            <strong>Nomor:</strong> <strong>{{ $referenceNumber }}</strong> <strong>tanggal</strong>
            <strong>{{ $referenceDate }}</strong> <strong>Perihal Penyelesaian Pekerjaan periode</strong>
            <strong>{{ $workPeriod }}</strong> (terlampir), pihak-pihak yang bertanda tangan di bawah ini
            melaporkan bahwa:
        </p>

        <p class="opname">
            Pada hari {{ $opnameDate }} telah dilaksanakan penelitian/opname pekerjaan bersama
            di lapangan dengan hasil sebagai berikut:
        </p>

        <table class="items">
            <tr>
                <td class="no">1.</td>
                <td class="txt">Hasil pekerjaan dapat diterima sebagai Penyelesaian pekerjaan
                    <strong>periode {{ $workPeriod }}</strong>.</td>
            </tr>
            <tr>
                <td class="no">2.</td>
                <td class="txt">
                    Jangka waktu penyelesaian pekerjaan sesuai dengan
                    <strong>Purchase Order Nomor :</strong> <strong>{{ $poNumber }}</strong> adalah
                    {{ $contractMonths }} bulan, <strong>terhitung dari tanggal</strong>
                    <strong>{{ $contractStart }}</strong> <strong>sampai dengan tanggal</strong>
                    <strong>{{ $contractEnd }}</strong>.
                </td>
            </tr>
            <tr>
                <td class="no">3.</td>
                <td class="txt">
                    Realisasi waktu pekerjaan adalah <strong>{{ $realizationDays }}</strong> hari kalender
                    terhitung <strong>tanggal {{ $realizationStart }}</strong> sampai dengan tanggal
                    <strong>{{ $realizationEnd }}</strong>.
                </td>
            </tr>
        </table>

        <p class="conclusion-title">Kesimpulan dari hasil penelitian/opname pekerjaan di lapangan sebagai berikut:</p>

        <table class="items conclusions">
            <tr>
                <td class="no">1.</td>
                <td class="txt">{!! nl2br(e($workConclusion)) !!}</td>
            </tr>
            <tr>
                <td class="no">2.</td>
                <td class="txt">{!! nl2br(e($paymentTerms)) !!}</td>
            </tr>
        </table>

        <div class="payment">
            <p>Pembayaran yang berhak diterima <strong>{{ $vendorName }}</strong> adalah sebagai berikut:</p>

            <table>
                <tr>
                    <td class="label"><strong>Nilai Kontrak</strong></td>
                    <td class="amount"><strong>= Rp {{ $contractValue }}</strong></td>
                </tr>
                <tr>
                    <td class="label"><strong>Pembayaran</strong><br><span class="sub">{{ $workName }}</span></td>
                    <td class="amount">= Rp {{ $paymentAmount }}</td>
                </tr>
                <tr>
                    <td class="label">DPP LAIN (Rp {{ $paymentAmount }} x 11/12)</td>
                    <td class="amount">= Rp {{ $dpp }}</td>
                </tr>
                <tr>
                    <td class="label">PPN ( Rp {{ $dpp }} x {{ $ppnRate }}%)</td>
                    <td class="amount">= Rp {{ $ppn }}</td>
                </tr>
                <tr>
                    <td class="label"><strong>Yang Harus Dibayarkan Termasuk PPN</strong></td>
                    <td class="amount">= Rp <strong>{{ $total }}</strong></td>
                </tr>
            </table>

            <p class="terbilang">
                <em>Terbilang: ( {{ $terbilang }} )</em><br>
                <span class="ppn-note"><em>(Harga tersebut sudah termasuk PPN {{ $ppnRate }}%)</em></span>
            </p>
        </div>

        <p class="cost-center">
            Dari tanggal diterbitkannya LHSTP ini, Pembayaran dibebankan kepada Cost Center Nomor : {{ $costCenter }}
        </p>
    </div>
    <div class="rule-fields"></div>
</div>

<div class="page">
    <img class="letterhead" src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('assets/lhstp-logo.png'))) }}" alt="Logo">

    <div class="page-2">
        <p>Berdasarkan perincian pekerjaan dan perhitungan pembayaran di atas, maka yang bertanda tangan di bawah ini:</p>

        <table class="parties">
            <tr>
                <td class="no">1</td>
                <td class="label">Nama</td>
                <td class="colon">:</td>
                <td><strong>Tb. Benny Kurniawan</strong></td>
            </tr>
            <tr>
                <td class="no"></td>
                <td class="label">Jabatan</td>
                <td class="colon">:</td>
                <td>
                    IT &amp; Management System Manager <strong>PT Krakatau Sarana Properti</strong> yang dalam hal ini
                    bertindak untuk dan atas nama <strong>PT Krakatau Sarana Properti</strong> yang selanjutnya disebut
                    <strong>PIHAK PERTAMA</strong>
                </td>
            </tr>
        </table>

        <table class="parties parties-gap">
            <tr>
                <td class="no">2</td>
                <td class="label">Nama</td>
                <td class="colon">:</td>
                <td><strong>{{ $representativeName }}</strong></td>
            </tr>
            <tr>
                <td class="no"></td>
                <td class="label">Jabatan</td>
                <td class="colon">:</td>
                <td>
                    {{ $representativePosition }} <strong>{{ $vendorName }}</strong> yang dalam hal ini bertindak untuk
                    dan atas nama <strong>{{ $vendorName }}</strong> yang selanjutnya disebut
                    <strong>PIHAK KEDUA</strong>
                </td>
            </tr>
        </table>

        <p class="closing">
            Demikian Laporan Hasil Serah Terima Pekerjaan ini dibuat dengan sebenarnya untuk dapat
            dipergunakan sebagaimana mestinya.
        </p>

        <p class="signature-date">Cilegon, {{ $signatureDate }}</p>

        <table class="signatures">
            <tr>
                <td>PIHAK KEDUA</td>
                <td>PIHAK PERTAMA</td>
            </tr>
            <tr>
                <td>{{ $vendorName }}</td>
                <td>PT Krakatau Sarana Properti</td>
            </tr>
            <tr>
                <td class="signature-space"></td>
                <td class="signature-space"></td>
            </tr>
            <tr>
                <td><span class="underline-signature">{{ $representativeName }}</span></td>
                <td><span class="underline-signature">Tb. Benny Kurniawan</span></td>
            </tr>
            <tr>
                <td>{{ $representativePosition }}</td>
                <td>IT &amp; Management System Manager</td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
