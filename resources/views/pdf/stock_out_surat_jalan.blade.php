<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Jalan Pengeluaran Barang #{{ $stockOut->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #e11d48; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #be123c; text-transform: uppercase; font-size: 18px; }
        .meta-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .meta-table td { padding: 4px; font-size: 11px; vertical-align: top; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.data-table th { background-color: #be123c; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .footer { margin-top: 30px; text-align: right; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Surat Jalan Pengeluaran Barang (Stock Out)</h2>
        <p>Warehub v2 Inventory Management System</p>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 15%;"><strong>Ref / Resi</strong></td>
            <td style="width: 35%;">: {{ $stockOut->resi_summary ?? 'OUT-'.$stockOut->id }}</td>
            <td style="width: 15%;"><strong>Gudang Asal</strong></td>
            <td style="width: 35%;">: {{ $stockOut->warehouse->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Toko / Store</strong></td>
            <td>: {{ $stockOut->store->store_name ?? '-' }}</td>
            <td><strong>Petugas</strong></td>
            <td>: {{ $stockOut->user->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Tanggal</strong></td>
            <td>: {{ $stockOut->date?->format('d-m-Y') }}</td>
            <td><strong>Total Jual</strong></td>
            <td>: Rp {{ number_format($stockOut->total_selling_price, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Produk & SKU Varian</th>
                <th style="width: 15%;">Size / Warna</th>
                <th style="width: 15%;">HPP (Rp)</th>
                <th style="width: 15%;">Harga Jual (Rp)</th>
                <th style="width: 15%;">Qty Keluar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockOut->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->productVariant->product->product_name ?? '-' }}</strong><br>
                    <small style="color: #be123c;">SKU: {{ $item->productVariant->sku ?? '-' }}</small>
                </td>
                <td>{{ $item->productVariant->size ?? '-' }} / {{ $item->productVariant->color ?? '-' }}</td>
                <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item->selling_price, 0, ',', '.') }}</td>
                <td><strong>{{ $item->qty }} pcs</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak otomatis oleh system Warehub v2 pada {{ date('d-m-Y H:i') }}
    </div>
</body>
</html>
