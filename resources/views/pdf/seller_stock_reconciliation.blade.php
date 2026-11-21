<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekonsiliasi Hutang & Stok Per-Varian - {{ $seller->seller_name }}</title>
    <style>
        @page { margin: 14px 18px; }
        body { font-family: sans-serif; font-size: 9.5px; color: #1e293b; line-height: 1.35; margin: 0; }
        .header-table { width: 100%; border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 10px; border-collapse: collapse; }
        .title { font-size: 15px; font-weight: bold; color: #312e81; text-transform: uppercase; margin: 0; }
        .subtitle { font-size: 9px; color: #64748b; margin: 2px 0 0 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; background-color: #e0e7ff; color: #3730a3; }

        .profile-table { width: 100%; margin-bottom: 10px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-collapse: collapse; }
        .profile-table td { padding: 5px 8px; font-size: 9.5px; border: 1px solid #e2e8f0; }

        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .summary-box { border: 1px solid #cbd5e1; padding: 6px 8px; background-color: #ffffff; text-align: center; vertical-align: top; }
        .summary-label { font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 3px; }
        .summary-val { font-size: 11.5px; font-weight: bold; color: #0f172a; }
        .summary-sub { font-size: 8px; color: #64748b; margin-top: 2px; }

        .section-title { font-size: 10.5px; font-weight: bold; color: #1e293b; margin: 12px 0 5px; border-left: 3px solid #4f46e5; padding-left: 6px; text-transform: uppercase; }
        .group-header { background-color: #312e81; color: #ffffff; padding: 5px 8px; font-weight: bold; font-size: 9.5px; margin-top: 10px; }

        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 8.5px; }
        table.data-table th { background-color: #f1f5f9; color: #334155; font-weight: bold; text-transform: uppercase; font-size: 8px; text-align: left; }
        table.data-table tfoot td { background-color: #eef2ff; font-weight: bold; border-top: 1.5px solid #818cf8; }

        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: bold; }
        .text-sky { color: #0284c7; }
        .text-emerald { color: #059669; }
        .text-rose { color: #e11d48; }
        .text-indigo { color: #4338ca; }
        .text-amber { color: #b45309; }

        .signatures { width: 100%; margin-top: 20px; page-break-inside: avoid; border-collapse: collapse; }
        .sign-box { text-align: center; width: 45%; }
        .sign-line { margin-top: 42px; border-top: 1px solid #94a3b8; width: 70%; margin-left: auto; margin-right: auto; }
        .footer-note { margin-top: 15px; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px dashed #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $summary = $report['summary'] ?? [];
        $groups = $report['groups'] ?? [];
    @endphp

    <table class="header-table">
        <tr>
            <td>
                <h1 class="title">Laporan Rekonsiliasi Hutang & Stok Per-Varian Mitra</h1>
                <p class="subtitle">Warehub v2 - Rincian Hutang Modal Per-Varian (Grouped by Prefix SKU) & Perbandingan Stok Fisik</p>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="badge">Pra-Opname Snapshot</span>
                <p class="subtitle" style="margin-top: 3px;">Dicetak: {{ date('d M Y H:i') }} WIB</p>
            </td>
        </tr>
    </table>

    <table class="profile-table">
        <tr>
            <td style="width: 15%; font-weight: bold; color: #64748b;">Nama Mitra / Seller</td>
            <td style="width: 35%; font-weight: bold; color: #0f172a;">{{ $seller->seller_name }}</td>
            <td style="width: 15%; font-weight: bold; color: #64748b;">Gudang Target</td>
            <td style="width: 35%;">{{ $warehouse ? $warehouse->name : 'Semua Gudang' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; color: #64748b;">Periode Pembukuan</td>
            <td>{{ $period ? $period->name : 'Semua Periode (Akumulasi)' }}</td>
            <td style="font-weight: bold; color: #64748b;">Filter Prefix / Cari</td>
            <td>{{ $prefixFilter ? strtoupper($prefixFilter) : 'Semua Prefix' }}{{ $search ? ' | Pencarian: ' . $search : '' }}</td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-box" style="width: 20%;">
                <div class="summary-label">Modal Masuk (Stock In)</div>
                <div class="summary-val text-sky">Rp {{ number_format($summary['in_hpp'] ?? 0, 0, ',', '.') }}</div>
                <div class="summary-sub">{{ number_format($summary['in_qty'] ?? 0) }} pcs</div>
            </td>
            <td class="summary-box" style="width: 20%;">
                <div class="summary-label">Modal Keluar (Out + Bundle)</div>
                <div class="summary-val text-emerald">Rp {{ number_format($summary['out_hpp'] ?? 0, 0, ',', '.') }}</div>
                <div class="summary-sub">{{ number_format($summary['out_qty'] ?? 0) }} pcs ({{ number_format($summary['bundle_out_qty'] ?? 0) }} via bundle)</div>
            </td>
            <td class="summary-box" style="width: 20%; background-color: #fff1f2;">
                <div class="summary-label text-rose">Sisa Hutang Varian (In - Out)</div>
                <div class="summary-val text-rose">Rp {{ number_format($summary['sisa_debt_hpp'] ?? 0, 0, ',', '.') }}</div>
                <div class="summary-sub">{{ number_format($summary['sisa_debt_qty'] ?? 0) }} pcs tertahan</div>
            </td>
            <td class="summary-box" style="width: 20%; background-color: #f5f3ff;">
                <div class="summary-label text-indigo">Adjustment & Net Hutang</div>
                <div class="summary-val text-indigo">Rp {{ number_format($summary['net_debt_with_adjustment'] ?? 0, 0, ',', '.') }}</div>
                <div class="summary-sub">Adj: Rp {{ number_format($summary['total_adjustment'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td class="summary-box" style="width: 20%;">
                <div class="summary-label">Stok Fisik Gudang Terpilih</div>
                <div class="summary-val">{{ number_format($summary['warehouse_stock_qty'] ?? 0) }} pcs</div>
                <div class="summary-sub">Nilai HPP: Rp {{ number_format($summary['stock_hpp_value'] ?? 0, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Ringkasan Rekap Per-Prefix SKU</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Prefix SKU</th>
                <th class="text-center">Jumlah Varian</th>
                <th class="text-center">Masuk (Qty)</th>
                <th class="text-right">Masuk (HPP)</th>
                <th class="text-center">Keluar (Qty)</th>
                <th class="text-right">Keluar (HPP)</th>
                <th class="text-center">Sisa Hutang (Qty)</th>
                <th class="text-right">Sisa Hutang (HPP)</th>
                <th class="text-center">Stok Fisik Gudang</th>
                <th class="text-center">Selisih (Fisik - Hutang)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $g)
                <tr>
                    <td class="font-mono font-bold text-indigo">{{ $g['prefix'] }}</td>
                    <td class="text-center">{{ $g['variant_count'] }} SKU</td>
                    <td class="text-center">{{ number_format($g['in_qty']) }}</td>
                    <td class="text-right text-sky">Rp {{ number_format($g['in_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ number_format($g['out_qty']) }}</td>
                    <td class="text-right text-emerald">Rp {{ number_format($g['out_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center font-bold">{{ number_format($g['sisa_debt_qty']) }}</td>
                    <td class="text-right font-bold {{ $g['sisa_debt_hpp'] > 0 ? 'text-rose' : 'text-emerald' }}">Rp {{ number_format($g['sisa_debt_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center font-bold">{{ number_format($g['warehouse_stock_qty']) }}</td>
                    <td class="text-center font-bold {{ $g['qty_gap'] < 0 ? 'text-rose' : ($g['qty_gap'] > 0 ? 'text-amber' : 'text-emerald') }}">
                        {{ $g['qty_gap'] > 0 ? '+' : '' }}{{ number_format($g['qty_gap']) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="color: #94a3b8; padding: 10px;">Tidak ada data varian.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($groups) > 0)
            <tfoot>
                <tr>
                    <td>GRAND TOTAL</td>
                    <td class="text-center">{{ $summary['total_variants'] }} SKU</td>
                    <td class="text-center">{{ number_format($summary['in_qty']) }}</td>
                    <td class="text-right text-sky">Rp {{ number_format($summary['in_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ number_format($summary['out_qty']) }}</td>
                    <td class="text-right text-emerald">Rp {{ number_format($summary['out_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ number_format($summary['sisa_debt_qty']) }}</td>
                    <td class="text-right text-rose">Rp {{ number_format($summary['sisa_debt_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ number_format($summary['warehouse_stock_qty']) }}</td>
                    <td class="text-center">{{ ($summary['qty_gap'] > 0 ? '+' : '') . number_format($summary['qty_gap']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="section-title">Detail Rincian Per-Varian</div>
    @foreach($groups as $g)
        <div class="group-header">
            SERI SKU: {{ $g['prefix'] }} ({{ $g['variant_count'] }} Varian) — Sisa Hutang: Rp {{ number_format($g['sisa_debt_hpp'], 0, ',', '.') }} | Stok Fisik: {{ number_format($g['warehouse_stock_qty']) }} pcs
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 24px;" class="text-center">No</th>
                    <th>SKU</th>
                    <th>Produk & Varian</th>
                    <th>Konveksi</th>
                    <th class="text-right">HPP</th>
                    <th class="text-center">In</th>
                    <th class="text-right">In (Rp)</th>
                    <th class="text-center">Out (Bundle)</th>
                    <th class="text-right">Out (Rp)</th>
                    <th class="text-center">Hutang Qty</th>
                    <th class="text-right">Sisa Hutang (Rp)</th>
                    <th class="text-center">Stok Fisik</th>
                    <th class="text-center">Gap</th>
                </tr>
            </thead>
            <tbody>
                @foreach($g['variants'] as $idx => $v)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="font-mono font-bold text-indigo">{{ $v['sku'] }}</td>
                        <td>
                            <span class="font-bold">{{ $v['product_name'] }}</span>
                            <span style="color: #64748b;">({{ $v['size'] }} / {{ $v['color'] }})</span>
                        </td>
                        <td>{{ $v['konveksi_name'] }}</td>
                        <td class="text-right">Rp {{ number_format($v['hpp_price'], 0, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($v['in_qty']) }}</td>
                        <td class="text-right text-sky">Rp {{ number_format($v['in_hpp'], 0, ',', '.') }}</td>
                        <td class="text-center">
                            {{ number_format($v['out_qty']) }}
                            @if($v['bundle_out_qty'] > 0)
                                <span style="color: #6366f1;">({{ $v['bundle_out_qty'] }}B)</span>
                            @endif
                        </td>
                        <td class="text-right text-emerald">Rp {{ number_format($v['out_hpp'], 0, ',', '.') }}</td>
                        <td class="text-center font-bold">{{ number_format($v['sisa_debt_qty']) }}</td>
                        <td class="text-right font-bold {{ $v['sisa_debt_hpp'] > 0 ? 'text-rose' : 'text-emerald' }}">Rp {{ number_format($v['sisa_debt_hpp'], 0, ',', '.') }}</td>
                        <td class="text-center font-bold">{{ number_format($v['warehouse_stock_qty']) }}</td>
                        <td class="text-center {{ $v['qty_gap'] < 0 ? 'text-rose' : ($v['qty_gap'] > 0 ? 'text-amber' : 'text-emerald') }}">
                            {{ $v['qty_gap'] > 0 ? '+' : '' }}{{ number_format($v['qty_gap']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">SUBTOTAL {{ $g['prefix'] }}:</td>
                    <td class="text-center">{{ number_format($g['in_qty']) }}</td>
                    <td class="text-right text-sky">Rp {{ number_format($g['in_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ number_format($g['out_qty']) }}</td>
                    <td class="text-right text-emerald">Rp {{ number_format($g['out_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ number_format($g['sisa_debt_qty']) }}</td>
                    <td class="text-right text-rose">Rp {{ number_format($g['sisa_debt_hpp'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ number_format($g['warehouse_stock_qty']) }}</td>
                    <td class="text-center">{{ ($g['qty_gap'] > 0 ? '+' : '') . number_format($g['qty_gap']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endforeach

    <table class="signatures">
        <tr>
            <td class="sign-box">
                <p style="margin: 0; color: #64748b;">Disiapkan oleh,</p>
                <p style="font-weight: bold; margin: 2px 0 0 0;">Admin Gudang / Finance Warehub</p>
                <div class="sign-line"></div>
                <p style="margin: 3px 0 0 0; color: #94a3b8;">Tanggal: {{ date('d M Y') }}</p>
            </td>
            <td style="width: 10%;"></td>
            <td class="sign-box">
                <p style="margin: 0; color: #64748b;">Diperiksa & Disetujui oleh,</p>
                <p style="font-weight: bold; margin: 2px 0 0 0;">Mitra / {{ $seller->seller_name }}</p>
                <div class="sign-line"></div>
                <p style="margin: 3px 0 0 0; color: #94a3b8;">Tanggal: ___________________</p>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen ini diterbitkan secara otomatis oleh Sistem Warehub v2 sebagai berita acara rekonsiliasi hutang modal dan stok fisik pra-opname.
    </div>
</body>
</html>
