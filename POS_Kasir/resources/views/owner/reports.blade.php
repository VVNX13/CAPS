@extends('layouts.app')

@section('title', 'Laporan Penjualan ' . $scopeLabel . ' - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current' => 'reports',
        'title' => 'Laporan ' . $scopeLabel,
        'icon' => $scopeLabel === 'Makanan' ? '🍲' : ($scopeLabel === 'Minuman' ? '🍹' : '👨‍💼'),
    ])

    <main class="container">
        <!-- Filter Card -->
        <div class="card p-3 mb-4">
            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:12px;">
                <div style="display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap; flex:1;">
                    <div style="flex:1; min-width:140px;">
                        <label class="form-label" for="startDate">Dari Tanggal</label>
                        <input type="date" id="startDate" class="form-control">
                    </div>
                    <div style="flex:1; min-width:140px;">
                        <label class="form-label" for="endDate">Sampai Tanggal</label>
                        <input type="date" id="endDate" class="form-control">
                    </div>
                    <button class="btn btn-primary" onclick="loadReport()" style="margin-top:auto;">
                        🔍 Filter
                    </button>
                </div>

                <!-- Export Action Buttons -->
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-success btn-sm" onclick="exportExcel()">
                        📊 Export Excel
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="exportPdf()">
                        📄 Export PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary KPIs -->
        <div class="kpi-grid mb-4">
            <div class="kpi-card border-primary">
                <div class="kpi-label">TOTAL TRANSAKSI</div>
                <div id="reportOrdersCount" class="kpi-val text-primary">0</div>
            </div>
            <div class="kpi-card border-warning">
                <div class="kpi-label">ITEM TERJUAL</div>
                <div id="reportItemsSold" class="kpi-val text-warning">0</div>
            </div>
            <div class="kpi-card border-success" style="grid-column: span 2;">
                <div class="kpi-label">TOTAL OMSET {{ strtoupper($scopeLabel) }} (LUNAS)</div>
                <div id="reportTotalRevenue" class="kpi-val text-success" style="font-size:1.45rem;">Rp 0</div>
            </div>
        </div>

        <!-- Table Report / Mobile Responsive Cards -->
        <div class="card p-3">
            <h4 style="font-weight:800; font-size:1.05rem; margin-bottom:12px; color:#0f172a;">Rincian Transaksi</h4>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Invoice</th>
                            <th>Waktu</th>
                            <th>Pelanggan</th>
                            <th>Item Menu</th>
                            @if ($isAdmin)
                                <th>Kategori</th>
                            @endif
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Harga</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="reportBody">
                        <tr><td colspan="{{ $isAdmin ? 9 : 8 }}" style="text-align:center; padding:30px;">Memuat data laporan...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        const reportDataUrl = @js(route('owner.reports.data'));
        const reportExcelUrl = @js(route('owner.reports.excel'));
        const reportPdfUrl = @js(route('owner.reports.pdf'));
        const receiptUrlPattern = @js(url('/receipt/') . '__INVOICE__');
        const showCategoryColumn = @js($isAdmin);
        const columnCount = showCategoryColumn ? 9 : 8;

        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date().toISOString().slice(0, 10);
            const firstDayOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);

            document.getElementById('startDate').value = firstDayOfMonth;
            document.getElementById('endDate').value = today;
            loadReport();
        });

        async function loadReport() {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;

            try {
                const res = await fetch(`${reportDataUrl}?start_date=${start}&end_date=${end}`);
                const data = await res.json();
                if (data.success) {
                    document.getElementById('reportOrdersCount').innerText = data.data.total_orders;
                    document.getElementById('reportItemsSold').innerText = data.data.total_items_sold + ' porsi';
                    document.getElementById('reportTotalRevenue').innerText = formatRupiah(data.data.total_revenue);
                    renderReportTable(data.data.items);
                }
            } catch (e) {
                console.error(e);
            }
        }

        function renderReportTable(items) {
            const tbody = document.getElementById('reportBody');
            if (items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${columnCount}" style="text-align:center; padding:30px;">Tidak ada transaksi pada periode ini.</td></tr>`;
                return;
            }

            let html = '';
            let no = 1;
            items.forEach(item => {
                const receiptHref = receiptUrlPattern.replace('__INVOICE__', item.invoice_number);
                const categoryCell = showCategoryColumn
                    ? `<td><span class="brand-badge ${item.owner_type === 'minuman' ? 'badge-drink' : 'badge-food'}" style="font-size:0.65rem; padding:1px 6px;">${item.category_name}</span></td>`
                    : '';
                html += `
                    <tr>
                        <td>${no++}</td>
                        <td style="font-weight:700;"><a href="${receiptHref}" target="_blank" style="color:var(--primary); text-decoration:underline;">${item.invoice_number}</a></td>
                        <td style="color:#64748b; font-size:0.8rem; white-space:nowrap;">${item.created_at}</td>
                        <td>${item.customer_name}</td>
                        <td style="font-weight:600;">${item.product_name}</td>
                        ${categoryCell}
                        <td style="text-align:center; font-weight:700;">${item.quantity}</td>
                        <td style="text-align:right;">${formatRupiah(item.unit_price)}</td>
                        <td style="text-align:right; font-weight:800; color:#0f172a;">${formatRupiah(item.subtotal)}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function exportExcel() {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            window.location.href = `${reportExcelUrl}?start_date=${start}&end_date=${end}`;
        }

        function exportPdf() {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            window.location.href = `${reportPdfUrl}?start_date=${start}&end_date=${end}`;
        }

        function formatRupiah(num) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
        }
    </script>
@endpush
