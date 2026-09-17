<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Audit Stock Opname #{{ $stockOpname->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #059669; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #047857; text-transform: uppercase; font-size: 18px; }
        .meta-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .meta-table td { padding: 4px; font-size: 11px; vertical-align: top; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        table.data-table th { background-color: #047857; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-rose { color: #dc2626; font-weight: bold; }
        .text-emerald { color: #059669; font-weight: bold; }
        .footer { margin-top: 30px; text-align: right; font-size: 9px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Audit Stock Opname</h2>
        <p>Warehub v2 Inventory Management System</p>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 15%;"><strong>ID Opname</strong></td>
            <td style="width: 35%;">: #{{ $stockOpname->id }}</td>
            <td style="width: 15%;"><strong>Gudang</strong></td>
            <td style="width: 35%;">: {{ $stockOpname->warehouse->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Tanggal Audit</strong></td>
            <td>: {{ $stockOpname->date?->format('d-m-Y') }}</td>
            <td><strong>Auditor / User</strong></td>
            <td>: {{ $stockOpname->user->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Catatan</strong></td>
            <td colspan="3">: {{ $stockOpname->note ?? '-' }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Produk & SKU Varian</th>
                <th style="width: 15%;">Stok Sistem</th>
                <th style="width: 15%;">Stok Fisik Audit</th>
                <th style="width: 15%;">Selisih (Diff)</th>
                <th style="width: 15%;">Status Audit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockOpname->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->productVariant->product->product_name ?? '-' }}</strong><br>
                    <small style="color: #047857;">SKU: {{ $item->productVariant->sku ?? '-' }}</small>
                </td>
                <td>{{ $item->system_qty }} pcs</td>
                <td><strong>{{ $item->physical_qty }} pcs</strong></td>
                <td>
                    @if($item->difference < 0)
                        <span class="text-rose">{{ $item->difference }} pcs</span>
                    @elseif($item->difference > 0)
                        <span class="text-emerald">+{{ $item->difference }} pcs</span>
                    @else
                        <span>0 pcs</span>
                    @endif
                </td>
                <td>
                    @if($item->difference == 0)
                        <span>SESUAI</span>
                    @elseif($item->difference < 0)
                        <span class="text-rose">KURANG</span>
                    @else
                        <span class="text-emerald">LEBIH</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak otomatis oleh system Warehub v2 pada {{ date('d-m-Y H:i') }}
    </div>
</body>
</html>
