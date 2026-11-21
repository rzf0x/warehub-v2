<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Summary Pengeluaran Barang (Stock Out)</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 8px; }
        .header { text-align: center; margin-bottom: 14px; border-bottom: 2px solid #be123c; padding-bottom: 8px; }
        .header h2 { margin: 0; color: #9f1239; text-transform: uppercase; font-size: 16px; }
        .header p { margin: 3px 0 0; color: #64748b; font-size: 10px; }
        .info-bar { width: 100%; margin-bottom: 10px; font-size: 10px; color: #334155; }
        table.data-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; word-wrap: break-word; }
        table.data-table th { background-color: #9f1239; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 8.5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .text-rose { color: #be123c; font-weight: bold; }
        .text-cyan { color: #0891b2; font-weight: bold; }
        .text-emerald { color: #059669; font-weight: bold; }
        .text-violet { color: #7c3aed; font-weight: bold; }
        .subtext { font-size: 8.5px; color: #64748b; }
        .footer { margin-top: 16px; text-align: right; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Warehub v2 - Laporan Summary Pengeluaran Barang (Stock Out)</h2>
        <p>Rekapitulasi Mutasi Penjualan, HPP Modal, Bruto &amp; Estimasi Profit</p>
    </div>

    <div class="info-bar">
        <span>
            <strong>Periode:</strong> {{ $filterInfo['period'] ?? 'Semua Periode' }} &nbsp;|&nbsp;
            <strong>Gudang:</strong> {{ $filterInfo['warehouse'] ?? 'Semua Gudang' }} &nbsp;|&nbsp;
            <strong>Toko:</strong> {{ $filterInfo['store'] ?? 'Semua Toko' }} &nbsp;|&nbsp;
            <strong>Total Transaksi:</strong> {{ number_format($summary['total_transactions'] ?? 0, 0, ',', '.') }}
        </span>
        <span style="float: right;">
            <strong>Dicetak Pada:</strong> {{ date('d-m-Y H:i') }} WIB
        </span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 3.5%;">No</th>
                <th style="width: 8%;">No. Faktur</th>
                <th class="text-center" style="width: 8%;">Tanggal</th>
                <th style="width: 11%;">Periode</th>
                <th style="width: 15.5%;">Seller / Toko</th>
                <th style="width: 11%;">Gudang / Admin</th>
                <th class="text-center" style="width: 7%;">QTY Keluar</th>
                <th class="text-right" style="width: 9%;">HPP (Modal)</th>
                <th class="text-right" style="width: 9%;">Bruto</th>
                <th class="text-right" style="width: 9%;">Penghasilan Bersih</th>
                <th class="text-right" style="width: 9%;">Est. Untung</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                @php
                    $rowHpp = (float) ($row->row_hpp ?? 0);
                    $rowBruto = (float) ($row->row_bruto ?? 0);
                    $rowBersih = (float) ($row->row_bersih ?? 0);
                    $rowUntung = $rowBersih - $rowHpp;
                    $formattedDate = $row->date ? date('d-m-Y', strtotime((string) $row->date)) : '-';
                    $fakturCode = 'OUT-' . str_pad((string) $row->id, 5, '0', STR_PAD_LEFT);
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="bold">{{ $fakturCode }}</td>
                    <td class="text-center">{{ $formattedDate }}</td>
                    <td>{{ $row->period_name ?? '-' }}</td>
                    <td>
                        <span class="bold">{{ $row->seller_name ?? ($row->store_name ?? '-') }}</span>
                        @if(!empty($row->store_name) && !empty($row->seller_name))
                            <br><span class="subtext">{{ $row->store_name }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $row->warehouse_name ?? '-' }}
                        @if(!empty($row->admin_name))
                            <br><span class="subtext">{{ $row->admin_name }}</span>
                        @endif
                    </td>
                    <td class="text-center text-rose">-{{ number_format((int) $row->total_qty, 0, ',', '.') }} pcs</td>
                    <td class="text-right">Rp {{ number_format($rowHpp, 0, ',', '.') }}</td>
                    <td class="text-right text-cyan">Rp {{ number_format($rowBruto, 0, ',', '.') }}</td>
                    <td class="text-right text-emerald">Rp {{ number_format($rowBersih, 0, ',', '.') }}</td>
                    <td class="text-right text-violet">Rp {{ number_format($rowUntung, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center" style="padding: 18px; color: #94a3b8;">
                        Tidak ada data pengeluaran barang (Stock Out) untuk filter yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($rows) > 0)
        <tfoot>
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="6" class="text-right">TOTAL KESELURUHAN:</td>
                <td class="text-center text-rose">-{{ number_format($summary['total_qty'] ?? 0, 0, ',', '.') }} pcs</td>
                <td class="text-right">Rp {{ number_format($summary['total_hpp'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right text-cyan">Rp {{ number_format($summary['total_bruto'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right text-emerald">Rp {{ number_format($summary['total_bersih'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right text-violet">Rp {{ number_format($summary['total_untung'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">
        Warehub v2 Inventory &copy; {{ date('Y') }}
    </div>
</body>
</html>
