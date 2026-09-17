<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Penghasilan & HPP Seller</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #1e1b4b; text-transform: uppercase; font-size: 18px; }
        .header p { margin: 4px 0 0; color: #64748b; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        th { background-color: #f1f5f9; font-weight: bold; color: #1e293b; text-transform: uppercase; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .footer { margin-top: 30px; text-align: right; font-size: 11px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Penghasilan & Margin HPP Seller</h2>
        <p>Sistem Manajemen Persediaan & Keuangan Warehub v2</p>
        <p>Periode: {{ $startDate ?? 'Semua' }} s/d {{ $endDate ?? 'Semua' }} | Tanggal Cetak: {{ date('d F Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">#</th>
                <th>Nama Seller / Mitra</th>
                <th class="text-center">QTY Terjual</th>
                <th class="text-right">Total HPP (Modal)</th>
                <th class="text-right">Total Omset (Jual)</th>
                <th class="text-right">Laba Kotor (Margin)</th>
                <th class="text-center">% Margin</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandQty = 0;
                $grandHpp = 0;
                $grandOmset = 0;
                $grandProfit = 0;
            @endphp
            @forelse ($reportData as $index => $row)
                @php
                    $grandQty += $row['total_qty_sold'];
                    $grandHpp += $row['total_hpp_sold'];
                    $grandOmset += $row['total_omset'];
                    $grandProfit += $row['gross_profit'];
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="bold">{{ $row['seller_name'] }}</td>
                    <td class="text-center">{{ number_format($row['total_qty_sold']) }} Pcs</td>
                    <td class="text-right">Rp {{ number_format($row['total_hpp_sold'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($row['total_omset'], 0, ',', '.') }}</td>
                    <td class="text-right bold" style="color: #059669;">Rp {{ number_format($row['gross_profit'], 0, ',', '.') }}</td>
                    <td class="text-center">{{ $row['margin_percent'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #94a3b8;">Belum ada data penghasilan seller pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="2" class="text-right">TOTAL KESELURUHAN:</td>
                <td class="text-center">{{ number_format($grandQty) }} Pcs</td>
                <td class="text-right">Rp {{ number_format($grandHpp, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($grandOmset, 0, ',', '.') }}</td>
                <td class="text-right" style="color: #059669;">Rp {{ number_format($grandProfit, 0, ',', '.') }}</td>
                <td class="text-center">{{ $grandOmset > 0 ? round(($grandProfit / $grandOmset) * 100, 2) : 0 }}%</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Dicetak secara otomatis oleh sistem Warehub v2 pada {{ date('H:i:s') }} WIB</p>
    </div>
</body>
</html>
