<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Keuntungan Per-Periode</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin: 0; padding: 10px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #10b981; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #065f46; text-transform: uppercase; font-size: 18px; }
        .header p { margin: 4px 0 0; color: #64748b; font-size: 11px; }
        .info-bar { margin-bottom: 15px; font-size: 11px; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; }
        th { background-color: #f1f5f9; font-weight: bold; color: #1e293b; text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .text-success { color: #059669; font-weight: bold; }
        .text-danger { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 25px; text-align: right; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Keuntungan Per-Periode</h2>
        <p>Sistem Manajemen Persediaan & Keuangan Warehub v2 - AA Gym Konveksi</p>
    </div>

    <div class="info-bar">
        <span><strong>Filter Periode:</strong> {{ $selectedPeriodName ?? 'Semua Periode' }}</span>
        <span style="float: right;"><strong>Tanggal Cetak:</strong> {{ date('d F Y - H:i') }} WIB</span>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">#</th>
                <th>Periode Pembukuan</th>
                <th class="text-center">Rentang Tanggal</th>
                <th class="text-center">QTY Keluar</th>
                <th class="text-right">HPP (Modal)</th>
                <th class="text-right">Bruto</th>
                <th class="text-right">Penghasilan Bersih</th>
                <th class="text-right">Est. Untung</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportData as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="bold">{{ $row['period_name'] }}</td>
                    <td class="text-center" style="font-size: 10px; color: #475569;">{{ $row['start_date'] }} - {{ $row['end_date'] }}</td>
                    <td class="text-center bold">{{ number_format($row['qty_keluar']) }} Pcs</td>
                    <td class="text-right">Rp {{ number_format($row['hpp_modal'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($row['bruto'], 0, ',', '.') }}</td>
                    <td class="text-right bold" style="color: #4f46e5;">Rp {{ number_format($row['penghasilan_bersih'], 0, ',', '.') }}</td>
                    <td class="text-right {{ $row['est_untung'] >= 0 ? 'text-success' : 'text-danger' }}">
                        Rp {{ number_format($row['est_untung'], 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: #94a3b8;">Belum ada data laporan keuntungan.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="3" class="text-right">TOTAL KESELURUHAN:</td>
                <td class="text-center">{{ number_format($summary['total_qty_keluar']) }} Pcs</td>
                <td class="text-right">Rp {{ number_format($summary['total_hpp_modal'], 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($summary['total_bruto'], 0, ',', '.') }}</td>
                <td class="text-right" style="color: #4f46e5;">Rp {{ number_format($summary['total_penghasilan_bersih'], 0, ',', '.') }}</td>
                <td class="text-right {{ $summary['total_est_untung'] >= 0 ? 'text-success' : 'text-danger' }}">
                    Rp {{ number_format($summary['total_est_untung'], 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Dicetak secara otomatis oleh sistem Warehub v2</p>
    </div>
</body>
</html>
