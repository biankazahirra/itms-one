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

    <div class="title">LAPORAN HASIL SERAH TERIMA PEKERJAAN</div>
    <div class="nomor">Nomor: {{ $number }}</div>

    <div class="content">
        <p>Kepada Yth.<br>Manager Accounting<br>PT Krakatau Sarana Properti</p>

        <table class="fields">
            <tr>
                <td class="label">PEKERJAAN</td>
                <td class="colon">:</td>
                <td>{{ $workName }}</td>
            </tr>
            <tr>
                <td class="label">NO. PURCHASE ORDER</td>
                <td class="colon">:</td>
                <td>{{ $poNumber }}</td>
            </tr>
            <tr>
                <td class="label">TANGGAL KONTRAK</td>
                <td class="colon">:</td>
                <td>{{ $contractDate }}</td>
            </tr>
            <tr>
                <td class="label">KONTRAKTOR</td>
                <td class="colon">:</td>
                <td>{{ $vendorName }}</td>
            </tr>
        </table>

        <p class="ref">
            Sesuai dengan surat {{ $referenceType }} dari {{ $vendorName }}
            Nomor: {{ $referenceNumber }} tanggal {{ $referenceDate }}
            Perihal Penyelesaian Pekerjaan periode {{ $workPeriod }} (terlampir),
            pihak-pihak yang bertanda tangan di bawah ini melaporkan bahwa:
        </p>

        <p class="opname">
            Pada hari {{ $opnameDate }} telah dilaksanakan penelitian/opname pekerjaan bersama
            di lapangan dengan hasil sebagai berikut:
        </p>

        <table class="items">
            <tr>
                <td class="no">1.</td>
                <td class="txt">Hasil pekerjaan dapat diterima sebagai Penyelesaian pekerjaan periode {{ $workPeriod }}.</td>
            </tr>
            <tr>
                <td class="no">2.</td>
                <td class="txt">
                    Jangka waktu penyelesaian pekerjaan sesuai dengan Purchase Order Nomor :
                    {{ $poNumber }} adalah {{ $contractMonths }} bulan, terhitung dari tanggal
                    {{ $contractStart }} sampai dengan tanggal {{ $contractEnd }}.
                </td>
            </tr>
            <tr>
                <td class="no">3.</td>
                <td class="txt">
                    Realisasi waktu pekerjaan adalah {{ $realizationDays }} hari kalender terhitung
                    tanggal {{ $realizationStart }} sampai dengan tanggal {{ $realizationEnd }}.
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
            <p>Pembayaran yang berhak diterima {{ $vendorName }} adalah sebagai berikut:</p>

            <table>
                <tr>
                    <td class="label">Nilai Kontrak</td>
                    <td class="amount">= Rp {{ $contractValue }}</td>
                </tr>
                <tr>
                    <td class="label">Pembayaran<br><span class="sub">{{ $workName }}</span></td>
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
                    <td class="label">Yang Harus Dibayarkan Termasuk PPN</td>
                    <td class="amount">= Rp {{ $total }}</td>
                </tr>
            </table>

            <p class="terbilang">
                Terbilang: ( {{ $terbilang }} )<br>
                <span class="ppn-note">(Harga tersebut sudah termasuk PPN {{ $ppnRate }}%)</span>
            </p>
        </div>

        <p class="cost-center">
            Dari tanggal diterbitkannya LHSTP ini, Pembayaran dibebankan kepada Cost Center Nomor : {{ $costCenter }}
        </p>
    </div>
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
                <td>Tb. Benny Kurniawan</td>
            </tr>
            <tr>
                <td class="no"></td>
                <td class="label">Jabatan</td>
                <td class="colon">:</td>
                <td>
                    IT &amp; Management System Manager PT Krakatau Sarana Properti yang dalam hal ini
                    bertindak untuk dan atas nama PT Krakatau Sarana Properti yang selanjutnya disebut
                    PIHAK PERTAMA
                </td>
            </tr>
        </table>

        <table class="parties parties-gap">
            <tr>
                <td class="no">2</td>
                <td class="label">Nama</td>
                <td class="colon">:</td>
                <td>{{ $representativeName }}</td>
            </tr>
            <tr>
                <td class="no"></td>
                <td class="label">Jabatan</td>
                <td class="colon">:</td>
                <td>
                    {{ $representativePosition }} {{ $vendorName }} yang dalam hal ini bertindak untuk
                    dan atas nama {{ $vendorName }} yang selanjutnya disebut PIHAK KEDUA
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
                <td>{{ $representativeName }}</td>
                <td>Tb. Benny Kurniawan</td>
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



