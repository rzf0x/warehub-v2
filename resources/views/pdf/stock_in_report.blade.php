<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Penerimaan Barang (Stock In)</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #4338ca; text-transform: uppercase; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #4338ca; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Warehub v2 - Laporan Penerimaan Barang (Stock In)</h2>
        <p>Total Transaksi: {{ count($stockIns) }} | Dicetak: {{ date('d-m-Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">No Invoice / SJ</th>
                <th style="width: 15%;">Tanggal</th>
                <th style="width: 20%;">Gudang</th>
                <th style="width: 15%;">Petugas</th>
                <th style="width: 15%;">Total Qty</th>
                <th style="width: 15%;">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockIns as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td><strong>{{ $item->invoice_number }}</strong></td>
                <td>{{ $item->date?->format('d-m-Y') }}</td>
                <td>{{ $item->warehouse->name ?? '-' }}</td>
                <td>{{ $item->user->name ?? '-' }}</td>
                <td><strong>{{ $item->items->sum('qty') }} pcs</strong></td>
                <td>{{ $item->note ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Warehub v2 Inventory &copy; {{ date('Y') }}
    </div>
</body>
</html>
