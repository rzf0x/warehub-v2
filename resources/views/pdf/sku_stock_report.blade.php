<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan SKU Stock - {{ $seller->seller_name }}</title>
    <style>
        @page { margin: 16px 22px; }
        body { font-family: sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; margin: 0; }
        .header-table { width: 100%; border-bottom: 2px solid #059669; padding-bottom: 8px; margin-bottom: 12px; border-collapse: collapse; }
        .title { font-size: 16px; font-weight: bold; color: #065f46; text-transform: uppercase; margin: 0; }
        .subtitle { font-size: 9.5px; color: #64748b; margin: 3px 0 0 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; background-color: #d1fae5; color: #065f46; }

        .meta-table { width: 100%; margin-bottom: 12px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-collapse: collapse; }
        .meta-table td { padding: 6px 10px; font-size: 10px; border: 1px solid #e2e8f0; }

        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary-box { border: 1px solid #cbd5e1; padding: 8px 10px; background-color: #ffffff; text-align: center; }
        .summary-label { font-size: 8.5px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 3px; }
        .summary-val { font-size: 14px; font-weight: bold; color: #0f172a; }

        .product-header { background-color: #065f46; color: #ffffff; padding: 6px 10px; font-weight: bold; font-size: 10.5px; margin-top: 10px; }
        .product-sub { font-size: 9px; font-weight: normal; color: #d1fae5; }

        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 9.5px; }
        table.data-table th { background-color: #f1f5f9; color: #334155; font-weight: bold; text-transform: uppercase; font-size: 8.5px; text-align: left; }
        table.data-table tfoot td { background-color: #ecfdf5; font-weight: bold; border-top: 1.5px solid #10b981; color: #065f46; }

        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: bold; }
        .text-emerald { color: #059669; }
        .text-rose { color: #e11d48; }
        .text-amber { color: #d97706; }

        .grand-total-box { width: 100%; border-collapse: collapse; margin-top: 12px; border: 2px solid #059669; background-color: #ecfdf5; }
        .grand-total-box td { padding: 8px 12px; font-size: 11px; font-weight: bold; color: #065f46; }

        .footer-note { margin-top: 18px; text-align: center; font-size: 8.5px; color: #94a3b8; border-top: 1px dashed #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <h1 class="title">Laporan Daftar Stok SKU &amp; Ukuran (SKU Stock)</h1>
                <p class="subtitle">Warehub v2 — Rekapitulasi Stok Fisik Per-SKU, Size &amp; Total Kuantitas</p>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="badge">Print SKU Stock</span>
                <p class="subtitle" style="margin-top: 3px;">Dicetak: {{ date('d M Y H:i') }} WIB</p>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="width: 18%; font-weight: bold; color: #64748b;">Mitra / Seller</td>
            <td style="width: 32%; font-weight: bold; color: #0f172a;">{{ $seller->seller_name }}</td>
            <td style="width: 18%; font-weight: bold; color: #64748b;">Lokasi Gudang</td>
            <td style="width: 32%; font-weight: bold;">{{ $warehouse ? $warehouse->name : 'Semua Gudang (Akumulasi)' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; color: #64748b;">Brand</td>
            <td>{{ $seller->brand?->name ?? '-' }}</td>
            <td style="font-weight: bold; color: #64748b;">Kontak Mitra</td>
            <td>{{ $seller->phone ?? '-' }}</td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-box" style="width: 33.33%;">
                <div class="summary-label">Total Produk Terpilih</div>
                <div class="summary-val">{{ number_format($summary['total_products'] ?? 0) }} Produk</div>
            </td>
            <td class="summary-box" style="width: 33.33%;">
                <div class="summary-label">Total SKU Varian</div>
                <div class="summary-val">{{ number_format($summary['total_variants'] ?? 0) }} SKU</div>
            </td>
            <td class="summary-box" style="width: 33.33%; background-color: #ecfdf5;">
                <div class="summary-label text-emerald">Grand Total Stok Fisik</div>
                <div class="summary-val text-emerald">{{ number_format($summary['grand_total_qty'] ?? 0) }} Pcs</div>
            </td>
        </tr>
    </table>

    @forelse($products as $pIndex => $product)
        <div class="product-header">
            {{ $pIndex + 1 }}. {{ $product['product_name'] }}
            <span class="product-sub">
                (Master SKU: {{ $product['sku'] }} | Kategori: {{ $product['category_name'] }} | Total: {{ number_format($product['total_qty']) }} Pcs)
            </span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 7%;" class="text-center">No</th>
                    <th style="width: 35%;">Kode SKU Varian</th>
                    <th style="width: 20%;" class="text-center">Size / Ukuran</th>
                    <th style="width: 20%;" class="text-center">Warna</th>
                    <th style="width: 18%;" class="text-right">Total Qty Stok</th>
                </tr>
            </thead>
            <tbody>
                @foreach($product['variants'] as $vIndex => $variant)
                    <tr>
                        <td class="text-center">{{ $vIndex + 1 }}</td>
                        <td class="font-mono font-bold">{{ $variant['sku'] }}</td>
                        <td class="text-center font-bold">{{ $variant['size'] }}</td>
                        <td class="text-center">{{ $variant['color'] }}</td>
                        <td class="text-right font-bold {{ $variant['qty'] <= 0 ? 'text-rose' : ($variant['qty'] <= 5 ? 'text-amber' : 'text-emerald') }}">
                            {{ number_format($variant['qty']) }} Pcs
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">SUBTOTAL ({{ $product['product_name'] }} — {{ count($product['variants']) }} SKU):</td>
                    <td class="text-right">{{ number_format($product['total_qty']) }} Pcs</td>
                </tr>
            </tfoot>
        </table>
    @empty
        <div style="padding: 20px; text-align: center; border: 1px solid #cbd5e1; color: #64748b;">
            Tidak ada produk atau varian SKU yang dipilih untuk dicetak.
        </div>
    @endforelse

    @if(count($products) > 0)
        <table class="grand-total-box">
            <tr>
                <td>GRAND TOTAL KESELURUHAN ({{ $summary['total_products'] }} Produk / {{ $summary['total_variants'] }} Varian SKU)</td>
                <td class="text-right" style="font-size: 13px;">{{ number_format($summary['grand_total_qty']) }} Pcs</td>
            </tr>
        </table>
    @endif

    <div class="footer-note">
        Dokumen ini dicetak secara otomatis dari Sistem Warehub v2 pada {{ date('d/m/Y H:i:s') }} WIB.
    </div>
</body>
</html>
