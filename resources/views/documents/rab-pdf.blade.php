<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>RAB {{ $budget->rab_number }}</title>
    <style>
        @page {
            size: 792.13pt 612.08pt;
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            font-family: Calibri, "dejavu sans", sans-serif;
            font-size: 10.98pt;
            color: #000;
            margin: 0;
        }

        .logo {
            position: absolute;
            left: 33.27pt;
            top: 28.9pt;
            width: 134.54pt;
            height: 37.43pt;
        }

        .title {
            position: absolute;
            left: 0;
            width: 792.13pt;
            top: 32.5pt;
            text-align: center;
            font-size: 13.98pt;
            font-weight: bold;
            line-height: 1.45;
        }

        .title .work-name {
            color: #404040;
        }

        .table-wrap {
            margin: 107.7pt 43.5pt 0 33.27pt;
        }

        table.items {
            table-layout: fixed;
            width: 715.36pt;
            border-collapse: collapse;
            font-size: 10.98pt;
            line-height: 1.16;
        }

        table.items th,
        table.items td {
            border: 0.1pt solid #000;
            padding: 2pt 3pt;
            vertical-align: middle;
        }

        table.items tr {
            page-break-inside: avoid;
        }

        table.items thead th {
            background-color: #0BC3FD;
            color: #000;
            font-weight: bold;
            text-align: center;
            height: 18.4pt;
            padding: 2pt 4pt;
        }

        th.no { width: 4.09%; }
        th.name { width: 24.67%; }
        th.description { width: 28.34%; }
        th.qty { width: 3.77%; }
        th.unit { width: 8.28%; }
        th.price { width: 15.16%; }
        th.amount { width: 15.69%; }

        td.no { text-align: center; }
        td.qty { text-align: center; }
        td.unit { text-align: center; }
        td.price { text-align: right; white-space: nowrap; padding-right: 3pt; }
        td.amount { text-align: right; white-space: nowrap; padding-right: 3pt; }

        tr.total td {
            font-weight: bold;
            height: 16.8pt;
            padding: 1pt 3pt;
        }

        td.total-label {
            text-align: center;
        }

        td.total-value {
            background-color: #0BC3FD;
            text-align: center;
            white-space: nowrap;
        }

        .sign-block {
            margin: 18pt 43.5pt 0 33.27pt;
            page-break-inside: avoid;
        }

        .date-line {
            text-align: right;
            margin-bottom: 8pt;
        }

        table.signatures {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        table.signatures td {
            vertical-align: top;
            font-size: 10.98pt;
            line-height: 1.16;
        }

        table.signatures td.spacer { width: 56.5%; }
        table.signatures td.left { width: 22.5%; }
        table.signatures td.right { width: 21%; }

        .signature-space { height: 60pt; }

        .signatory-name {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    @php
        $money = function ($value, bool $blankWhenZero = false) {
            $value = (string) $value;
            $decimals = bccomp($value, bcadd($value, '0', 0), 2) === 0 ? 0 : 2;

            if ($blankWhenZero && bccomp($value, '0', 2) === 0) {
                return '';
            }

            return number_format((float) $value, $decimals, ',', '.');
        };
    @endphp

    <img class="logo" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(resource_path('assets/rab-logo.jpg'))) }}" alt="Logo">

    <div class="title">
        <div>Rencana Anggaran Biaya</div>
        <div class="work-name">{{ $version->work_name }}</div>
    </div>

    <div class="table-wrap">
        <table class="items">
            <thead>
                <tr>
                    <th class="no">No</th>
                    <th class="name">Nama Anggaran</th>
                    <th class="description">Deskripsi</th>
                    <th class="qty">Qty</th>
                    <th class="unit">Unit</th>
                    <th class="price">Harga</th>
                    <th class="amount">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($version->items as $index => $item)
                    <tr>
                        <td class="no">{{ $index + 1 }}</td>
                        <td>{{ $item->budget_name }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="qty">{{ \App\Support\Money::quantity($item->quantity) }}</td>
                        <td class="unit">{{ $item->unit_name_snapshot }}</td>
                        <td class="price">{{ $money($item->unit_price) }}</td>
                        <td class="amount">{{ $money($item->line_total, true) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="total-label" colspan="6">Total</td>
                    <td class="total-value">{{ $money($version->grand_total) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="sign-block">
        <div class="date-line">Cilegon, {{ \Carbon\Carbon::parse($version->rab_date)->locale('id')->translatedFormat('j F Y') }}</div>

        <table class="signatures">
            <tr>
                <td class="spacer"></td>
                <td class="left">
                    Disetujui,
                    <div class="signature-space"></div>
                    <span class="signatory-name">Tb. Benny Kurniawan</span><br>
                    ITMS Manager
                </td>
                <td class="right">
                    Dibuat oleh,
                    <div class="signature-space"></div>
                    <span class="signatory-name">Lazuardi Arsy</span><br>
                    IT Senior Engineer
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
