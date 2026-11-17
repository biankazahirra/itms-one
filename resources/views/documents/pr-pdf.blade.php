<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>PR {{ $purchaseRequest->pr_number }}</title>
    <style>
        @page { margin: 22mm 18mm; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #0f172a;
            margin: 0;
        }

        h1 {
            font-size: 14pt;
            letter-spacing: 0.04em;
            margin: 0 0 2mm;
            text-align: center;
        }

        .company {
            font-size: 9pt;
            text-align: center;
            color: #334155;
        }

        .document-number {
            margin-top: 1mm;
            text-align: center;
            font-size: 9pt;
        }

        .divider {
            border-top: 1pt solid #0f172a;
            margin: 5mm 0 4mm;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
        }

        table.meta th,
        table.meta td {
            padding: 1.2mm 0;
            vertical-align: top;
            text-align: left;
            font-weight: normal;
        }

        table.meta th {
            width: 26mm;
            color: #475569;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items th,
        table.items td {
            border: 0.6pt solid #94a3b8;
            padding: 1.8mm 2mm;
            font-size: 9pt;
        }

        table.items th {
            background: #e2e8f0;
            text-align: center;
        }

        table.items td.no { width: 8mm; text-align: center; }
        table.items td.qty { width: 16mm; text-align: right; }
        table.items td.money { width: 32mm; text-align: right; }
        table.items td.account { width: 22mm; text-align: center; }

        table.total {
            width: 100%;
            border-collapse: collapse;
            margin-top: -0.6pt;
        }

        table.total th,
        table.total td {
            border: 0.6pt solid #94a3b8;
            padding: 2mm;
            font-size: 9pt;
        }

        table.total th {
            text-align: right;
            background: #f1f5f9;
        }

        table.total td {
            width: 32mm;
            text-align: right;
            font-weight: bold;
        }

        .note {
            margin-top: 3mm;
            font-size: 8pt;
            color: #475569;
        }

        table.signatures {
            width: 100%;
            margin-top: 14mm;
            border-collapse: collapse;
        }

        table.signatures td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 9pt;
        }

        .signature-space {
            height: 20mm;
        }

        .signature-name {
            border-top: 0.6pt solid #0f172a;
            padding-top: 1.5mm;
            margin: 0 12mm;
        }
    </style>
</head>
<body>
    <div class="company">IT &amp; Management System Department</div>
    <h1>FORM PURCHASE REQUISITION</h1>
    <div class="document-number">No. {{ $purchaseRequest->pr_number }}</div>

    <div class="divider"></div>

    <table class="meta">
        <tr>
            <th>Dari</th>
            <td>Divisi ITMS / IT &amp; Management System Department</td>
        </tr>
        <tr>
            <th>Kepada</th>
            <td>{{ $version->department?->name ?? '—' }}</td>
        </tr>
        <tr>
            <th>Perihal</th>
            <td>{{ $version->subject }}</td>
        </tr>
        <tr>
            <th>Tanggal</th>
            <td>{{ \App\Support\Format::tanggal($version->pr_date) }}</td>
        </tr>
        <tr>
            <th>Kategori</th>
            <td>{{ $version->category ?: '—' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Anggaran</th>
                <th>Qty</th>
                <th>Estimasi Harga</th>
                <th>GL Account*</th>
                <th>Cost Center*</th>
                <th>No IO**</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($version->items as $index => $item)
                <tr>
                    <td class="no">{{ $index + 1 }}</td>
                    <td>{{ $item->budget_name }}</td>
                    <td class="qty">{{ \App\Support\Money::quantity($item->quantity) }}</td>
                    <td class="money">{{ \App\Support\Format::rupiah($item->estimated_total, 2) }}</td>
                    <td class="account"></td>
                    <td class="account"></td>
                    <td class="account"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="total">
        <tr>
            <th>Grand Total</th>
            <td>{{ \App\Support\Format::rupiah($version->grand_total, 2) }}</td>
        </tr>
    </table>

    <p class="note">
        * GL Account dan Cost Center diisi sesuai ketentuan akuntansi.<br>
        ** No IO diisi bila diperlukan.
    </p>

    <table class="signatures">
        <tr>
            <td>
                Dibuat oleh,
                <div class="signature-space"></div>
                <div class="signature-name">Divisi ITMS</div>
            </td>
            <td>
                Disetujui oleh,
                <div class="signature-space"></div>
                <div class="signature-name">{{ $version->department?->name ?? 'Departemen Tujuan' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
