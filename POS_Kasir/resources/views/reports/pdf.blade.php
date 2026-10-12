<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan {{ $scopeLabel }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0 0 5px 0; color: #1e293b; font-size: 18px; }
        .header p { margin: 0; color: #64748b; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #2563eb; color: #ffffff; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .summary-box { margin-top: 20px; float: right; width: 280px; }
        .summary-table { border: none; }
        .summary-table td { border: none; padding: 4px; }
        .total-highlight { font-size: 13px; font-weight: bold; color: #1e3a8a; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN PENJUALAN HARIAN / BULANAN</h2>
        <p>Kategori: <strong>{{ $scopeLabel }}</strong> | Periode: {{ $startDate }} s/d {{ $endDate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" width="5%">No</th>
                <th width="18%">Invoice</th>
                <th width="15%">Tanggal</th>
                <th width="18%">Pelanggan</th>
                <th>Menu Item</th>
                <th class="text-center" width="7%">Qty</th>
                <th class="text-right" width="15%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $row->invoice_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $row->customer_name }}</td>
                    <td>{{ $row->product_name }}<br><span style="font-size:9px; color:#64748b;">{{ $row->category_name }}</span></td>
                    <td class="text-center">{{ $row->quantity }}</td>
                    <td class="text-right">{{ rupiah($row->subtotal) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">Tidak ada transaksi lunas pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-box">
        <table class="summary-table">
            <tr>
                <td>Total Item Terjual</td>
                <td class="text-right"><strong>{{ $totalItems }} item</strong></td>
            </tr>
            <tr class="total-highlight">
                <td>TOTAL OMSET</td>
                <td class="text-right">{{ rupiah($totalRevenue) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
