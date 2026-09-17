<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Katalog Produk Warehub v2</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #6366f1; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #4338ca; text-transform: uppercase; font-size: 18px; }
        .header p { margin: 2px 0 0 0; color: #666; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
        th { background-color: #4338ca; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; background-color: #e0e7ff; color: #3730a3; }
        .text-right { text-align: right; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Warehub v2 - Laporan Katalog Produk</h2>
        <p>Dicetak Pada: {{ date('d-m-Y H:i') }} | Total Produk: {{ count($products) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Nama Produk & SKU</th>
                <th style="width: 15%;">Seller</th>
                <th style="width: 12%;">Brand</th>
                <th style="width: 12%;">Kategori</th>
                <th style="width: 10%;">Tipe</th>
                <th style="width: 21%;">Varian (SKU - Size/Warna - HPP - Jual)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $index => $product)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $product->product_name }}</strong><br>
                    <small style="color: #666;">SKU: {{ $product->sku }}</small>
                </td>
                <td>{{ $product->seller->name ?? '-' }}</td>
                <td>{{ $product->brand->name ?? '-' }}</td>
                <td>{{ $product->category->name ?? '-' }}</td>
                <td><span class="badge">{{ strtoupper($product->product_type ?? 'single') }}</span></td>
                <td>
                    @foreach($product->variants as $variant)
                        <div>&bull; {{ $variant->sku }} ({{ $variant->size }}/{{ $variant->color }}) - HPP: Rp {{ number_format($variant->price, 0, ',', '.') }} | Jual: Rp {{ number_format($variant->selling_price, 0, ',', '.') }}</div>
                    @endforeach
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Warehub v2 Inventory System &copy; {{ date('Y') }}
    </div>
</body>
</html>
