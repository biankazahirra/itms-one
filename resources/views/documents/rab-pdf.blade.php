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
            font-size: 8.98pt;
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

        .title-main {
            position: absolute;
            left: 0;
            width: 792.13pt;
            top: 30.34pt;
            text-align: center;
            font-size: 11.98pt;
            font-weight: bold;
        }

        .title-work {
            position: absolute;
            left: 0;
            width: 792.13pt;
            top: 50.71pt;
            text-align: center;
            font-size: 11.98pt;
            font-weight: bold;
            color: #404040;
        }

        .table-wrap {
            margin: 107.7pt 43.5pt 0 33.27pt;
        }

        table.items {
            table-layout: fixed;
            width: 715.36pt;
            border-collapse: collapse;
            font-size: 8.98pt;
            line-height: 0.92;
        }

        table.items th,
        table.items td {
            border: 0.1pt solid #000;
            padding: 3.2pt 3pt;
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
            height: auto;
            padding: 2.64pt 4pt 6.26pt;
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
            height: auto;
            padding: 2.65pt 3pt;
        }

        td.total-label {
            text-align: center;
        }

        td.total-value {
            background-color: #0BC3FD;
            text-align: right;
            white-space: nowrap;
            padding-right: 3pt;
        }

        .sign-block {
            position: relative;
            margin: 14.45pt 43.5pt 0 33.27pt;
            height: 120pt;
            page-break-inside: avoid;
        }

        .date-line {
            position: absolute;
            right: 0;
            top: 15.95pt;
        }

        .sig-left {
            position: absolute;
            left: 400pt;
            top: 35.85pt;
            width: 110pt;
            text-align: center;
            line-height: 0.97;
        }

        .sig-right {
            position: absolute;
            left: 594.4pt;
            top: 35.85pt;
            width: 100pt;
            text-align: center;
            line-height: 0.97;
        }

        .signature-space { height: 46.45pt; }

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

    <img class="logo" src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('assets/rab-logo.png'))) }}" alt="Logo">

    <div class="title-main">Rencana Anggaran Biaya</div>
    <div class="title-work">{{ $version->work_name }}</div>

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

        <div class="sig-left">
            Disetujui,
            <div class="signature-space"></div>
            <span class="signatory-name">Tb. Benny Kurniawan</span><br>
            ITMS Manager
        </div>
        <div class="sig-right">
            Dibuat oleh,
            <div class="signature-space"></div>
            <span class="signatory-name">Lazuardi Arsy</span><br>
            IT Senior Engineer
        </div>
    </div>
</body>
</html>
