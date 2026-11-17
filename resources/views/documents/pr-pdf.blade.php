<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>PR {{ $purchaseRequest->pr_number }}</title>
    <style>
        @page { margin: 0; }

        * { box-sizing: border-box; }

        body {
            font-family: times-roman;
            font-size: 8pt;
            color: #000;
            margin: 0;
        }

        .page {
            padding: 30.3pt 102pt 0 75.74pt;
        }

        .title {
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            margin: 0;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24.2pt;
        }

        table.meta td {
            padding: 0;
            height: 11pt;
            vertical-align: top;
            font-size: 8pt;
        }

        table.meta td.label { width: 61.86pt; }
        table.meta td.colon { width: 33.9pt; }

        .salutation {
            margin-top: 18.2pt;
            line-height: 12pt;
        }

        table.categories {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2.2pt;
        }

        table.categories td {
            padding: 0;
            vertical-align: top;
            font-size: 8pt;
            line-height: 14.25pt;
        }

        table.categories td.left { width: 465pt; }

        table.categories td.right { padding-top: 4.5pt; }

        table.cat {
            border-collapse: collapse;
            margin-left: -3.75pt;
        }

        table.cat td {
            padding: 0;
            vertical-align: middle;
            font-size: 8pt;
            line-height: 14.25pt;
            white-space: nowrap;
        }

        table.cat td.box {
            width: 12pt;
            text-align: left;
        }

        .checkbox {
            display: inline-block;
            width: 8.25pt;
            height: 8.25pt;
            border: 0.5pt solid #000;
            font-size: 6.5pt;
            font-weight: bold;
            line-height: 7pt;
            text-align: center;
        }

        .budget-note {
            margin-top: 13.1pt;
        }

        table.items {
            table-layout: fixed;
            width: 640.5pt;
            border-collapse: collapse;
            margin-top: 9.7pt;
            margin-left: -2.25pt;
            font-size: 8pt;
        }

        table.items th,
        table.items td {
            border: 0.5pt solid #000;
            padding: 1pt 2pt;
            line-height: 9pt;
            vertical-align: top;
        }

        table.items th {
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            height: 10.8pt;
            padding: 1pt;
        }

        table.items td { height: 12.05pt; }

        col.no { width: 15.7pt; }
        col.name { width: 296.3pt; }
        col.qty { width: 19.5pt; }
        col.money { width: 92.2pt; }
        col.gl { width: 84.8pt; }
        col.cost { width: 79.5pt; }
        col.io { width: 52.5pt; }

        td.no { text-align: center; }
        td.name { padding-left: 0.5pt; }
        td.qty { text-align: center; }
        td.money { text-align: right; padding-right: 0.5pt; white-space: nowrap; }

        tr.total td { height: 10.8pt; }
        td.total-label { text-align: center; font-weight: bold; }
        td.total-value { text-align: right; font-weight: bold; padding-right: 0.5pt; white-space: nowrap; }

        table.footer {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8.2pt;
        }

        table.footer td {
            padding: 0;
            vertical-align: top;
            font-size: 8pt;
            line-height: 11.25pt;
        }

        td.notes { width: 530pt; }

        td.signature {
            width: 128pt;
            text-align: center;
            padding-left: 7pt;
        }

        .signature-space {
            height: 37.7pt;
        }
    </style>
</head>
<body>
    @php
        $money = function ($value) {
            $value = (string) $value;
            $decimals = bccomp($value, bcadd($value, '0', 0), 2) === 0 ? 0 : 2;

            return number_format((float) $value, $decimals, ',', '.');
        };

        $category = $version->category;
    @endphp

    <div class="page">
        <p class="title">Form Pengajuan Purchase Requisition</p>

        <table class="meta">
            <tr>
                <td class="label">Dari</td>
                <td class="colon">:</td>
                <td>Divisi ITMS</td>
            </tr>
            <tr>
                <td class="label">Kepada Yth</td>
                <td class="colon">:</td>
                <td>{{ $version->department?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Perihal</td>
                <td class="colon">:</td>
                <td>{{ $version->subject }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal</td>
                <td class="colon">:</td>
                <td>{{ \Carbon\Carbon::parse($version->pr_date)->format('d-M-y') }}</td>
            </tr>
        </table>

        <div class="salutation">
            <div>Dengan hormat,</div>
            <div>Dengan ini kami meminta dibuatkan Purchase Requisition untuk:</div>
        </div>

        <table class="categories">
            <tr>
                <td class="left">
                    <table class="cat">
                        <tr>
                            <td class="box"><span class="checkbox">{{ $category === 'Investment' ? 'X' : '' }}</span></td>
                            <td>Purchase Requisition Investment</td>
                        </tr>
                        <tr>
                            <td class="box"><span class="checkbox">{{ $category === 'Office Supplies' ? 'X' : '' }}</span></td>
                            <td>Purchase Requisition Office Supplies</td>
                        </tr>
                        <tr>
                            <td class="box"><span class="checkbox">{{ $category === 'Maintenance' ? 'X' : '' }}</span></td>
                            <td>Purchase Requisition Maintenance</td>
                        </tr>
                    </table>
                </td>
                <td class="right">
                    <table class="cat">
                        <tr>
                            <td class="box"><span class="checkbox">{{ $category === 'Services' ? 'X' : '' }}</span></td>
                            <td>Purchase Requisition Services</td>
                        </tr>
                        <tr>
                            <td class="box"><span class="checkbox">{{ $category === 'Fixed Asset' ? 'X' : '' }}</span></td>
                            <td>Purchase Requisition Fixed Asset</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="budget-note">Biaya pekerjaan dibebankan pada mata anggaran:</div>

        <table class="items">
            <colgroup>
                <col class="no">
                <col class="name">
                <col class="qty">
                <col class="money">
                <col class="gl">
                <col class="cost">
                <col class="io">
            </colgroup>
            <thead>
                <tr>
                    <th style="width: 2.45%"></th>
                    <th style="width: 46.26%">Nama Anggaran</th>
                    <th style="width: 3.04%">Qty</th>
                    <th style="width: 14.39%">Estimasi Harga</th>
                    <th style="width: 13.24%">GL Account*</th>
                    <th style="width: 12.41%">Cost Center*</th>
                    <th style="width: 8.2%">No IO**</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($version->items as $index => $item)
                    <tr>
                        <td class="no">{{ $index + 1 }}</td>
                        <td class="name">{{ $item->budget_name }}</td>
                        <td class="qty">{{ \App\Support\Money::quantity($item->quantity) }}</td>
                        <td class="money">{{ $money($item->estimated_total) }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforeach

                <tr class="total">
                    <td class="total-label" colspan="3">Total</td>
                    <td class="total-value">{{ $money($version->grand_total) }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <table class="footer">
            <tr>
                <td class="notes">
                    <div>Notes:</div>
                    <div>* Diverifikasi Accounting</div>
                    <div>** Diisi Accounting</div>
                </td>
                <td class="signature">
                    <div>ITMS Department</div>
                    <div class="signature-space"></div>
                    <div>Tb. Benny Kurniawan</div>
                    <div>Manager</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
