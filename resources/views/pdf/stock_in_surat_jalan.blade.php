<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat Jalan Penerimaan Barang #{{ $stockIn->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #4338ca; text-transform: uppercase; font-size: 18px; }
        .meta-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .meta-table td { padding: 4px; font-size: 11px; vertical-align: top; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.data-table th { background-color: #4338ca; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .footer { margin-top: 30px; text-align: right; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Surat Jalan Penerimaan Barang (Stock In)</h2>
        <p>Warehub v2 Inventory Management System</p>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 15%;"><strong>No Invoice/SJ</strong></td>
            <td style="width: 35%;">: {{ $stockIn->invoice_number }}</td>
            <td style="width: 15%;"><strong>Gudang tujuan</strong></td>
            <td style="width: 35%;">: {{ $stockIn->warehouse->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Tanggal</strong></td>
            <td>: {{ $stockIn->date?->format('d-m-Y') }}</td>
            <td><strong>Penerima</strong></td>
            <td>: {{ $stockIn->user->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Catatan</strong></td>
            <td colspan="3">: {{ $stockIn->note ?? '-' }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 30%;">Produk & SKU</th>
                <th style="width: 15%;">Vendor Konveksi</th>
                <th style="width: 15%;">Ukuran / Warna</th>
                <th style="width: 15%;">HPP (Rp)</th>
                <th style="width: 20%;">Qty Masuk</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockIn->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->productVariant->product->product_name ?? '-' }}</strong><br>
                    <small style="color: #6366f1;">SKU: {{ $item->productVariant->sku ?? '-' }}</small>
                </td>
                <td>{{ $item->konveksi->name ?? '-' }}</td>
                <td>{{ $item->productVariant->size ?? '-' }} / {{ $item->productVariant->color ?? '-' }}</td>
                <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
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
