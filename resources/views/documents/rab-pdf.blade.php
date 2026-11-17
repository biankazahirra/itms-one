<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>RAB {{ $budget->rab_number }}</title>
    <style>
        @page { margin: 0; }

        * { box-sizing: border-box; }

        body {
            font-family: Calibri, "dejavu sans", sans-serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
        }

        .logo {
            position: absolute;
            left: 33.3pt;
            top: 24pt;
            width: 134.5pt;
            height: 37.4pt;
        }

        .title {
            position: absolute;
            left: 0;
            right: 0;
            top: 36.5pt;
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.45;
        }

        .table-wrap {
            margin: 108pt 43.5pt 0 33.3pt;
        }

        table.items {
            table-layout: fixed;
            width: 715.4pt;
            border-collapse: collapse;
            font-size: 11pt;
        }

        table.items th,
        table.items td {
            border: 0.5pt solid #000;
            padding: 2pt 4pt;
            vertical-align: middle;
        }

        table.items thead th {
            font-weight: bold;
            text-align: center;
            height: 18.8pt;
        }

        col.no { width: 4.07%; }
        col.name { width: 24.67%; }
        col.description { width: 28.32%; }
        col.qty { width: 3.75%; }
        col.unit { width: 8.26%; }
        col.price { width: 15.15%; }
        col.amount { width: 15.78%; }

        td.no { text-align: center; }
        td.qty { text-align: center; }
        td.unit { text-align: center; }
        td.price { text-align: right; white-space: nowrap; }
        td.amount { text-align: right; white-space: nowrap; }

        tr.total td {
            font-weight: bold;
            height: 18.8pt;
        }

        td.total-label { text-align: right; }
        td.total-value { text-align: right; white-space: nowrap; }

        .sign-block {
            margin: 22pt 43.5pt 0 33.3pt;
        }

        .date-line {
            text-align: right;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14pt;
            page-break-inside: avoid;
        }

        .signatures td {
            vertical-align: top;
            font-size: 11pt;
        }

        .signatures td.left { width: 60%; padding-left: 21pt; }
        .signatures td.right { width: 40%; }

        .signature-space { height: 58pt; }
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
        <div>{{ $version->work_name }}</div>
    </div>

    <div class="table-wrap">
        <table class="items">
            <colgroup>
                <col class="no">
                <col class="name">
                <col class="description">
                <col class="qty">
                <col class="unit">
                <col class="price">
                <col class="amount">
            </colgroup>
            <thead>
                <tr>
                    <th></th>
                    <th>Nama Anggaran</th>
                    <th>Deskripsi</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
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
                <td class="left">
                    Disetujui,
                    <div class="signature-space"></div>
                    Tb. Benny Kurniawan<br>
                    ITMS Manager
                </td>
                <td class="right">
                    Dibuat oleh,
                    <div class="signature-space"></div>
                    Lazuardi Arsy<br>
                    IT Senior Engineer
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
