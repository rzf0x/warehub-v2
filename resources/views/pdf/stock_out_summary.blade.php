<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ringkasan Pengeluaran Barang Per Seller</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #be123c; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #9f1239; text-transform: uppercase; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #9f1239; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Warehub v2 - Laporan Summary Goods Issue (Stock Out)</h2>
        <p>Dicetak Pada: {{ date('d-m-Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Ref / Resi</th>
                <th style="width: 15%;">Tanggal</th>
                <th style="width: 20%;">Toko Marketplace</th>
                <th style="width: 15%;">Gudang</th>
                <th style="width: 15%;">Total Qty</th>
                <th style="width: 15%;">Total Nilai Jual</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockOuts as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td><strong>{{ $item->resi_summary ?? 'OUT-'.$item->id }}</strong></td>
                <td>{{ $item->date?->format('d-m-Y') }}</td>
                <td>{{ $item->store->store_name ?? '-' }}</td>
                <td>{{ $item->warehouse->name ?? '-' }}</td>
                <td><strong>{{ $item->items->sum('qty') }} pcs</strong></td>
                <td>Rp {{ number_format($item->total_selling_price, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Warehub v2 Inventory &copy; {{ date('Y') }}
    </div>
</body>
</html>
