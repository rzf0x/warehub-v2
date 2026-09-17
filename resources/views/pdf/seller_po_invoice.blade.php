<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $purchaseOrder->po_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin: 0; padding: 15px; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 12px; margin-bottom: 15px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .brand-title { font-size: 20px; font-weight: bold; color: #1e1b4b; text-transform: uppercase; margin: 0; }
        .po-number { font-size: 14px; font-weight: bold; color: #4f46e5; margin: 4px 0 0; }
        .info-card { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; margin-bottom: 15px; font-size: 10px; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; }
        table.data-table th { background-color: #f1f5f9; font-weight: bold; color: #1e293b; text-transform: uppercase; font-size: 9px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .footer { margin-top: 30px; text-align: right; font-size: 10px; color: #64748b; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge-urgent { background-color: #fee2e2; color: #991b1b; }
        .badge-normal { background-color: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <h1 class="brand-title">WAREHUB v2</h1>
                    <p style="margin: 2px 0; color: #64748b; font-size: 10px;">Portal Restock Seller & Produksi Konveksi</p>
                </td>
                <td class="text-right">
                    <p class="po-number">{{ $purchaseOrder->po_number }}</p>
                    <p style="margin: 2px 0; font-size: 10px; color: #64748b;">Tanggal: {{ $purchaseOrder->created_at ? $purchaseOrder->created_at->format('d F Y - H:i') : '-' }}</p>
                    <span class="badge {{ strtolower($purchaseOrder->priority) === 'urgent' || strtolower($purchaseOrder->priority) === 'high' ? 'badge-urgent' : 'badge-normal' }}">
                        Prioritas: {{ strtoupper($purchaseOrder->priority) }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-card">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">
                    <strong>Mitra Seller / Pemohon:</strong><br>
                    <span style="font-size: 12px; font-weight: bold; color: #0f172a;">{{ $purchaseOrder->seller->seller_name ?? 'Mitra Seller' }}</span><br>
                    Toko Tujuan: <strong>{{ $purchaseOrder->store->store_name ?? 'Umum / All Store' }}</strong>
                </td>
                <td style="width: 50%;" class="text-right">
                    <strong>Tipe Pengajuan:</strong> {{ $purchaseOrder->poType->name ?? 'Restock Reguler' }}<br>
                    <strong>Status Produksi:</strong> <span style="text-transform: uppercase; font-weight: bold; color: #4f46e5;">{{ $purchaseOrder->production_status }}</span>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">#</th>
                <th>Nama Produk & Varian</th>
                <th class="text-center">SKU Varian</th>
                <th class="text-center">QTY Item</th>
                <th class="text-right">Harga HPP (Modal)</th>
                <th class="text-right">Subtotal Estimasi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong style="color: #0f172a;">{{ $item->productVariant->product->product_name ?? 'Produk' }}</strong><br>
                        <span style="font-size: 9px; color: #64748b;">Ukuran: {{ $item->productVariant->size ?? '-' }} | Warna: {{ $item->productVariant->color ?? '-' }}</span>
                    </td>
                    <td class="text-center" style="font-family: monospace;">{{ $item->productVariant->sku ?? '-' }}</td>
                    <td class="text-center bold">{{ number_format($item->qty) }} Pcs</td>
                    <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="text-right bold" style="color: #4f46e5;">Rp {{ number_format($item->qty * $item->price, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="3" class="text-right">TOTAL PO RESTOCK:</td>
                <td class="text-center">{{ number_format($totalQty) }} Pcs</td>
                <td colspan="2" class="text-right" style="font-size: 13px; color: #059669;">
                    Rp {{ number_format($totalCost, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    @if ($purchaseOrder->notes)
        <div style="margin-top: 15px; font-size: 10px; background-color: #fffbebfb; border: 1px solid #fef3c7; border-radius: 6px; padding: 8px;">
            <strong>Catatan Seller:</strong> {{ $purchaseOrder->notes }}
        </div>
    @endif

    <div class="footer">
        <p>Dicetak otomatis oleh Sistem Warehub v2 Mobile Portal pada {{ date('d F Y - H:i') }} WIB</p>
    </div>
</body>
</html>
