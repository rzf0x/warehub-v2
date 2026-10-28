<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekening Koran Modal - {{ $seller->seller_name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; margin: 15px; }
        .header-table { width: 100%; border-bottom: 2px solid #6366f1; padding-bottom: 10px; margin-bottom: 15px; }
        .title { font-size: 18px; font-weight: bold; color: #312e81; text-transform: uppercase; margin: 0; }
        .subtitle { font-size: 10px; color: #64748b; margin: 2px 0 0 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-warning { background-color: #fef9c3; color: #854d0e; }
        .badge-primary { background-color: #e0e7ff; color: #3730a3; }
        
        .profile-table { width: 100%; margin-bottom: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        .profile-table td { padding: 6px 10px; font-size: 10.5px; }
        
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary-box { border: 1px solid #cbd5e1; padding: 8px 10px; background-color: #ffffff; text-align: center; }
        .summary-label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 3px; }
        .summary-val { font-size: 13px; font-weight: bold; color: #0f172a; }
        
        .section-title { font-size: 12px; font-weight: bold; color: #1e293b; margin: 15px 0 6px; border-left: 3px solid #6366f1; padding-left: 6px; }
        
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.data-table th, table.data-table td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; font-size: 10px; }
        table.data-table th { background-color: #f1f5f9; color: #334155; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        .text-sky { color: #0284c7; }
        .text-emerald { color: #059669; }
        .text-purple { color: #7c3aed; }
        .text-rose { color: #e11d48; }
        .font-bold { font-weight: bold; }
        
        .signatures { width: 100%; margin-top: 30px; page-break-inside: avoid; }
        .sign-box { text-align: center; width: 45%; }
        .sign-line { margin-top: 55px; border-top: 1px solid #94a3b8; width: 75%; margin-left: auto; margin-right: auto; }
        .footer-note { margin-top: 25px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px dashed #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                <h1 class="title">Rekening Koran Modal Mitra</h1>
                <p class="subtitle">Warehub v2 - Sistem Manajemen Gudang, Inventori & Keuangan Mitra</p>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="badge badge-primary">Laporan Resmi</span>
                <p class="subtitle" style="margin-top: 4px;">Cetak: {{ date('d M Y H:i') }} WIB</p>
            </td>
        </tr>
    </table>

    <!-- Profil Seller -->
    <table class="profile-table">
        <tr>
            <td style="width: 18%; font-weight: bold; color: #64748b;">Nama Mitra / Seller</td>
            <td style="width: 32%; font-weight: bold; color: #0f172a;">{{ $seller->seller_name }}</td>
            <td style="width: 18%; font-weight: bold; color: #64748b;">Periode Akuntansi</td>
            <td style="width: 32%;">{{ $selectedPeriod ? $selectedPeriod->name : 'Semua Periode' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; color: #64748b;">Brand / Merek</td>
            <td>{{ $seller->brand?->name ?? '-' }}</td>
            <td style="font-weight: bold; color: #64748b;">No. Telepon / WA</td>
            <td>{{ $seller->phone ?? '-' }}</td>
        </tr>
    </table>

    <!-- Ikhtisar Finansial -->
    <table class="summary-table">
        <tr>
            <td class="summary-box" style="width: 25%;">
                <div class="summary-label">Total Stock In (HPP)</div>
                <div class="summary-val text-sky">Rp {{ number_format($stats['stock_in_hpp'], 0, ',', '.') }}</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">{{ number_format($stats['stock_in_qty']) }} Pcs Barang Masuk</div>
            </td>
            <td class="summary-box" style="width: 25%;">
                <div class="summary-label">Total Stock Out (HPP)</div>
                <div class="summary-val text-emerald">Rp {{ number_format($stats['stock_out_hpp'], 0, ',', '.') }}</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">{{ number_format($stats['stock_out_qty']) }} Pcs Terjual</div>
            </td>
            <td class="summary-box" style="width: 25%;">
                <div class="summary-label">Penyesuaian Saldo</div>
                <div class="summary-val text-purple">Rp {{ number_format($stats['total_adjustment'], 0, ',', '.') }}</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 2px;">Adjustments & Kompensasi</div>
            </td>
            <td class="summary-box" style="width: 25%; background-color: #f5f3ff; border: 2px solid #818cf8;">
                <div class="summary-label" style="color: #4338ca;">Sisa Net Saldo Modal</div>
                <div class="summary-val" style="color: #4338ca; font-size: 14px;">Rp {{ number_format($stats['net_debt'], 0, ',', '.') }}</div>
                <div style="font-size: 9px; color: #6366f1; margin-top: 2px;">Likuidasi: {{ $stats['liquidation_rate'] }}%</div>
            </td>
        </tr>
    </table>

    <!-- Adjustments jika ada -->
    @if(count($adjustments) > 0)
        <div class="section-title">Riwayat Penyesuaian Saldo (Adjustments)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 90px;">Tanggal</th>
                    <th style="width: 110px;">Periode</th>
                    <th>Catatan / Keterangan</th>
                    <th class="text-right" style="width: 120px;">Nominal (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($adjustments as $adj)
                    <tr>
                        <td>{{ date('d M Y', strtotime($adj->adjustment_date)) }}</td>
                        <td>{{ $adj->period?->name ?? '-' }}</td>
                        <td>{{ $adj->note ?? '-' }}</td>
                        <td class="text-right font-bold {{ $adj->amount >= 0 ? 'text-purple' : 'text-rose' }}">
                            {{ $adj->amount >= 0 ? '+' : '' }} Rp {{ number_format($adj->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Barang Masuk (Stock In) -->
    <div class="section-title">Barang Masuk Gudang (Stock In - Menambah Tanggungan Modal)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 80px;">Tanggal</th>
                <th style="width: 100px;">No. Invoice</th>
                <th>Produk & Varian</th>
                <th style="width: 90px;">SKU</th>
                <th class="text-center" style="width: 50px;">Qty</th>
                <th class="text-right" style="width: 80px;">HPP Satuan</th>
                <th class="text-right" style="width: 95px;">Subtotal HPP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stockInItems as $it)
                <tr>
                    <td>{{ $it->stockIn?->date ? date('d M Y', strtotime($it->stockIn->date)) : '-' }}</td>
                    <td class="font-mono text-sky font-bold">{{ $it->stockIn?->invoice_number ?? '-' }}</td>
                    <td>{{ $it->productVariant?->product?->product_name ?? 'Produk' }} ({{ $it->productVariant?->size ?? '-' }}/{{ $it->productVariant?->color ?? '-' }})</td>
                    <td class="font-mono" style="font-size: 8.5px;">{{ $it->productVariant?->sku ?? '-' }}</td>
                    <td class="text-center font-bold">{{ number_format($it->qty) }}</td>
                    <td class="text-right">Rp {{ number_format($it->price, 0, ',', '.') }}</td>
                    <td class="text-right font-bold text-sky">Rp {{ number_format($it->qty * $it->price, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="color: #94a3b8; padding: 12px;">Tidak ada transaksi barang masuk pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Barang Keluar (Stock Out) -->
    <div class="section-title">Barang Keluar / Terjual (Stock Out - Memotong Tanggungan Modal)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 80px;">Tanggal</th>
                <th style="width: 110px;">Toko / Marketplace</th>
                <th>Produk & Varian</th>
                <th style="width: 90px;">SKU</th>
                <th class="text-center" style="width: 50px;">Qty</th>
                <th class="text-right" style="width: 80px;">HPP Satuan</th>
                <th class="text-right" style="width: 95px;">Subtotal HPP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stockOutItems as $it)
                <tr>
                    <td>{{ $it->stockOut?->date ? date('d M Y', strtotime($it->stockOut->date)) : '-' }}</td>
                    <td>{{ $it->stockOut?->store?->store_name ?? 'Penjualan Umum' }}</td>
                    <td>{{ $it->productVariant?->product?->product_name ?? 'Produk' }} ({{ $it->productVariant?->size ?? '-' }}/{{ $it->productVariant?->color ?? '-' }})</td>
                    <td class="font-mono" style="font-size: 8.5px;">{{ $it->productVariant?->sku ?? '-' }}</td>
                    <td class="text-center font-bold">{{ number_format($it->qty) }}</td>
                    <td class="text-right">Rp {{ number_format($it->price, 0, ',', '.') }}</td>
                    <td class="text-right font-bold text-emerald">Rp {{ number_format($it->qty * $it->price, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="color: #94a3b8; padding: 12px;">Tidak ada transaksi barang keluar pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Tanda Tangan -->
    <table class="signatures">
        <tr>
            <td class="sign-box">
                <p style="margin: 0; color: #64748b;">Disiapkan oleh,</p>
                <p style="font-weight: bold; margin: 2px 0 0 0;">Admin Finance Warehub</p>
                <div class="sign-line"></div>
                <p style="margin: 3px 0 0 0; color: #94a3b8;">Tanggal: {{ date('d M Y') }}</p>
            </td>
            <td style="width: 10%;"></td>
            <td class="sign-box">
                <p style="margin: 0; color: #64748b;">Diterima & Disetujui oleh,</p>
                <p style="font-weight: bold; margin: 2px 0 0 0;">Mitra / {{ $seller->seller_name }}</p>
                <div class="sign-line"></div>
                <p style="margin: 3px 0 0 0; color: #94a3b8;">Tanggal: ___________________</p>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen ini diterbitkan secara otomatis oleh Sistem Warehub v2 dan sah sebagai bukti mutasi modal mitra.
    </div>

</body>
</html>
