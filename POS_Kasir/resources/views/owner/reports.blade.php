@extends('layouts.app')

@section('title', 'Laporan Penjualan ' . $scopeLabel . ' - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current' => 'reports',
        'title'   => 'Laporan ' . $scopeLabel,
        'icon'    => '',
    ])

    <style>
        /* ── Report Page Styles ── */
        .rpt-page { padding: 24px 0 40px; }

        .rpt-page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .rpt-page-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main, #0f172a);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .rpt-page-sub {
            font-size: 0.8rem;
            color: var(--text-muted, #64748b);
            margin-top: 3px;
            font-weight: 500;
        }

        /* Filter bar */
        .rpt-filter-bar {
            background: #fff;
            border: 1px solid #e8e4dc;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
            margin-bottom: 20px;
        }
        .rpt-filter-group {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex: 1;
            flex-wrap: wrap;
        }
        .rpt-filter-field { flex: 1; min-width: 130px; }
        .rpt-filter-field label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted, #64748b);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 5px;
        }
        .rpt-export-group { display: flex; gap: 8px; flex-wrap: wrap; }

        .rpt-btn-filter {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: var(--primary, #25382d);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .15s;
            white-space: nowrap;
        }
        .rpt-btn-filter:hover { opacity: .88; }

        .rpt-btn-export {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            border: 1.5px solid;
            transition: all .15s;
            white-space: nowrap;
            background: transparent;
        }
        .rpt-btn-export.excel {
            border-color: #15803d;
            color: #15803d;
        }
        .rpt-btn-export.excel:hover { background: #f0fdf4; }
        .rpt-btn-export.pdf {
            border-color: #b91c1c;
            color: #b91c1c;
        }
        .rpt-btn-export.pdf:hover { background: #fff5f5; }

        /* KPI strip */
        .rpt-kpi-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) { .rpt-kpi-strip { grid-template-columns: repeat(2, 1fr); } }

        .rpt-kpi {
            background: #fff;
            border: 1px solid #e8e4dc;
            border-radius: 12px;
            padding: 16px 18px;
        }
        .rpt-kpi.dominant {
            grid-column: span 2;
            background: var(--primary, #25382d);
            border-color: var(--primary, #25382d);
            color: #fff;
        }
        .rpt-kpi-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text-muted, #64748b);
            margin-bottom: 6px;
        }
        .rpt-kpi.dominant .rpt-kpi-label { color: rgba(255,255,255,.65); }
        .rpt-kpi-value {
            font-size: 1.55rem;
            font-weight: 900;
            color: var(--primary, #25382d);
            line-height: 1.1;
        }
        .rpt-kpi.dominant .rpt-kpi-value { color: #fff; }
        .rpt-kpi-note {
            font-size: 0.72rem;
            color: var(--text-muted, #64748b);
            margin-top: 4px;
        }
        .rpt-kpi.dominant .rpt-kpi-note { color: rgba(255,255,255,.55); }

        /* Section cards */
        .rpt-section {
            background: #fff;
            border: 1px solid #e8e4dc;
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .rpt-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px 14px;
            border-bottom: 1px solid #f0ede6;
        }
        .rpt-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text-main, #0f172a);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .rpt-section-title svg { color: var(--text-muted, #64748b); }
        .rpt-section-body { padding: 18px 20px; }

        /* Trend select */
        .rpt-select {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 600;
            background: #fff;
            color: var(--text-main, #0f172a);
            cursor: pointer;
        }

        /* Pareto table tweaks */
        .rpt-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
        .rpt-table th {
            padding: 7px 10px;
            text-align: left;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text-muted, #64748b);
            border-bottom: 1px solid #f0ede6;
        }
        .rpt-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #f7f5ef;
            vertical-align: middle;
            color: var(--text-main, #0f172a);
        }
        .rpt-table tbody tr:last-child td { border-bottom: none; }
        .rpt-table tbody tr:hover { background: #fafaf8; }

        .rpt-bar-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rpt-bar-track {
            flex: 1;
            height: 6px;
            background: #f0ede6;
            border-radius: 10px;
            overflow: hidden;
            min-width: 60px;
        }
        .rpt-bar-fill {
            height: 100%;
            background: var(--primary, #25382d);
            border-radius: 10px;
        }
        .rpt-bar-pct {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted, #64748b);
            white-space: nowrap;
            min-width: 36px;
            text-align: right;
        }

        .rpt-sub-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text-muted, #64748b);
            padding: 14px 0 8px;
        }

        /* Detail table */
        .rpt-detail-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .rpt-detail-table th {
            padding: 8px 12px;
            text-align: left;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text-muted, #64748b);
            border-bottom: 1.5px solid #e8e4dc;
            background: #fafaf8;
        }
        .rpt-detail-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0ede6;
            vertical-align: middle;
        }
        .rpt-detail-table tbody tr:last-child td { border-bottom: none; }
        .rpt-detail-table tbody tr:hover { background: #fafaf8; }
    </style>

    <main class="container rpt-page">

        {{-- Page Header --}}
        <div class="rpt-page-header">
            <div>
                <div class="rpt-page-title">Laporan Penjualan</div>
                <div class="rpt-page-sub">{{ $scopeLabel }} &mdash; Analisis transaksi berdasarkan periode</div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="rpt-filter-bar">
            <div class="rpt-filter-group">
                <div class="rpt-filter-field">
                    <label for="startDate">Dari Tanggal</label>
                    <input type="date" id="startDate" class="form-control">
                </div>
                <div class="rpt-filter-field">
                    <label for="endDate">Sampai Tanggal</label>
                    <input type="date" id="endDate" class="form-control">
                </div>
                <button class="rpt-btn-filter" onclick="loadReport()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Tampilkan
                </button>
            </div>
            <div class="rpt-export-group">
                <button class="rpt-btn-export excel" onclick="exportExcel()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Excel
                </button>
                <button class="rpt-btn-export pdf" onclick="exportPdf()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    PDF
                </button>
            </div>
        </div>

        {{-- KPI Strip --}}
        <div class="rpt-kpi-strip">
            <div class="rpt-kpi dominant">
                <div class="rpt-kpi-label">Total Omset {{ $scopeLabel }} (Lunas)</div>
                <div id="reportTotalRevenue" class="rpt-kpi-value">Rp 0</div>
                <div id="reportDiscountNote" class="rpt-kpi-note"></div>
            </div>
            <div class="rpt-kpi">
                <div class="rpt-kpi-label">Total Transaksi</div>
                <div id="reportOrdersCount" class="rpt-kpi-value" style="color:var(--primary,#25382d);">0</div>
                <div class="rpt-kpi-note">transaksi lunas</div>
            </div>
            <div class="rpt-kpi">
                <div class="rpt-kpi-label">Item Terjual</div>
                <div id="reportItemsSold" class="rpt-kpi-value" style="color:#9b641c;">0</div>
                <div class="rpt-kpi-note">total porsi</div>
            </div>
        </div>

        {{-- Pareto Analysis --}}
        <div class="rpt-section">
            <div class="rpt-section-header">
                <div class="rpt-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    Analisis Produk (Pareto)
                </div>
                <span style="font-size:0.72rem; font-weight:600; color:var(--text-muted,#64748b);">80% kontribusi pendapatan</span>
            </div>
            <div class="rpt-section-body">
                <div style="overflow-x:auto;">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Nama Menu</th>
                                <th style="text-align:right;">Omset</th>
                                <th style="text-align:center; width:60px;">Qty</th>
                                <th style="min-width:140px;">Kumulatif</th>
                            </tr>
                        </thead>
                        <tbody id="paretoTopBody">
                            <tr><td colspan="5" style="text-align:center;padding:24px;color:#94a3b8;font-size:0.82rem;">Memuat data…</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="rpt-sub-label">Produk dengan Penjualan Rendah</div>
                <div style="overflow-x:auto;">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Nama Menu</th>
                                <th style="text-align:right;">Omset</th>
                                <th style="text-align:center; width:60px;">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="paretoLowBody">
                            <tr><td colspan="4" style="text-align:center;padding:24px;color:#94a3b8;font-size:0.82rem;">Memuat data…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Trend Chart --}}
        <div class="rpt-section">
            <div class="rpt-section-header">
                <div class="rpt-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Tren Penjualan
                </div>
                <select id="trendPeriod" class="rpt-select" onchange="loadTrendData(document.getElementById('startDate').value, document.getElementById('endDate').value)">
                    <option value="daily">Harian</option>
                    <option value="weekly">Mingguan</option>
                </select>
            </div>
            <div class="rpt-section-body">
                <canvas id="salesTrendChart" style="height:280px;"></canvas>
            </div>
        </div>

        {{-- Detail Table --}}
        <div class="rpt-section">
            <div class="rpt-section-header">
                <div class="rpt-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    Rincian Transaksi
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="rpt-detail-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Invoice</th>
                            <th>Waktu</th>
                            <th>Pelanggan</th>
                            <th>Item Menu</th>
                            @if ($isAdmin)<th>Kategori</th>@endif
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Harga</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="reportBody">
                        <tr><td colspan="{{ $isAdmin ? 9 : 8 }}" style="text-align:center;padding:32px;color:#94a3b8;font-size:0.82rem;">Memuat data laporan…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        const reportDataUrl     = @js(route('owner.reports.data'));
        const reportParetoUrl   = @js(route('owner.reports.pareto'));
        const reportTrendUrl    = @js(route('owner.reports.trend'));
        const reportExcelUrl    = @js(route('owner.reports.excel'));
        const reportPdfUrl      = @js(route('owner.reports.pdf'));
        const receiptUrlPattern = @js(url('/receipt/') . '__INVOICE__');
        const showCategoryColumn = @js($isAdmin);
        const columnCount = showCategoryColumn ? 9 : 8;

        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date().toISOString().slice(0, 10);
            const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);
            document.getElementById('startDate').value = firstDay;
            document.getElementById('endDate').value   = today;
            loadReport();
        });

        async function loadReport() {
            const start = document.getElementById('startDate').value;
            const end   = document.getElementById('endDate').value;
            try {
                const res  = await fetch(`${reportDataUrl}?start_date=${start}&end_date=${end}`);
                const data = await res.json();
                if (data.success) {
                    document.getElementById('reportOrdersCount').textContent  = data.data.total_orders;
                    document.getElementById('reportItemsSold').textContent    = data.data.total_items_sold + ' porsi';
                    document.getElementById('reportTotalRevenue').textContent = formatRupiah(data.data.total_revenue);
                    document.getElementById('reportDiscountNote').textContent = data.data.total_discount > 0
                        ? `Sudah dipotong diskon ${formatRupiah(data.data.total_discount)}`
                        : '';
                    renderReportTable(data.data.items);
                }
            } catch (e) { console.error(e); }
            loadPareto(start, end);
            loadTrendData(start, end);
        }

        async function loadPareto(start, end) {
            try {
                const res  = await fetch(`${reportParetoUrl}?start_date=${start}&end_date=${end}`);
                const data = await res.json();
                if (!data.success) return;
                const { top_products, low_products } = data.data;

                const topBody = document.getElementById('paretoTopBody');
                topBody.innerHTML = top_products.length
                    ? top_products.map((p, i) => `
                        <tr>
                            <td style="color:#94a3b8;font-size:0.78rem;">${i + 1}</td>
                            <td style="font-weight:700;">${p.product}</td>
                            <td style="text-align:right;font-weight:600;">${formatRupiah(p.revenue)}</td>
                            <td style="text-align:center;">${p.qty}</td>
                            <td>
                                <div class="rpt-bar-wrap">
                                    <div class="rpt-bar-track"><div class="rpt-bar-fill" style="width:${p.cumulative_perc}%;"></div></div>
                                    <span class="rpt-bar-pct">${p.cumulative_perc}%</span>
                                </div>
                            </td>
                        </tr>`).join('')
                    : `<tr><td colspan="5" style="text-align:center;padding:24px;color:#94a3b8;font-size:0.82rem;">Tidak ada data</td></tr>`;

                const lowBody = document.getElementById('paretoLowBody');
                lowBody.innerHTML = low_products.length
                    ? low_products.map((p, i) => `
                        <tr>
                            <td style="color:#94a3b8;font-size:0.78rem;">${i + 1}</td>
                            <td style="font-weight:600;">${p.product}</td>
                            <td style="text-align:right;">${formatRupiah(p.revenue)}</td>
                            <td style="text-align:center;">${p.qty}</td>
                        </tr>`).join('')
                    : `<tr><td colspan="4" style="text-align:center;padding:24px;color:#94a3b8;font-size:0.82rem;">Tidak ada data</td></tr>`;
            } catch (e) { console.error(e); }
        }

        async function loadTrendData(start, end) {
            const period = document.getElementById('trendPeriod')?.value ?? 'daily';
            try {
                const res  = await fetch(`${reportTrendUrl}?start_date=${start}&end_date=${end}&period=${period}`);
                const data = await res.json();
                if (!data.success) return;
                const ctx = document.getElementById('salesTrendChart');
                if (!ctx) return;
                if (window._trendChart) window._trendChart.destroy();
                window._trendChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.data.labels,
                        datasets: [{
                            label: 'Omset (Rp)',
                            data: data.data.values,
                            backgroundColor: '#25382d22',
                            borderColor: '#25382d',
                            borderWidth: 2,
                            borderRadius: 7,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                grid: { color: '#f0ede6' },
                                ticks: { callback: v => 'Rp ' + Number(v).toLocaleString('id-ID'), font: { size: 11 } }
                            }
                        }
                    }
                });
            } catch (e) { console.error(e); }
        }

        function renderReportTable(items) {
            const tbody = document.getElementById('reportBody');
            if (!items.length) {
                tbody.innerHTML = `<tr><td colspan="${columnCount}" style="text-align:center;padding:32px;color:#94a3b8;font-size:0.82rem;">Tidak ada transaksi pada periode ini.</td></tr>`;
                return;
            }
            let html = '', no = 1;
            items.forEach(item => {
                const href = receiptUrlPattern.replace('__INVOICE__', item.invoice_number);
                const catCell = showCategoryColumn
                    ? `<td><span class="brand-badge ${item.owner_type === 'minuman' ? 'badge-drink' : 'badge-food'}" style="font-size:0.65rem;padding:1px 6px;">${item.category_name}</span></td>`
                    : '';
                html += `
                    <tr>
                        <td style="color:#94a3b8;font-size:0.78rem;">${no++}</td>
                        <td><a href="${href}" target="_blank" style="font-weight:700;color:var(--primary,#25382d);text-decoration:none;border-bottom:1px solid currentColor;">${item.invoice_number}</a></td>
                        <td style="color:#64748b;font-size:0.78rem;white-space:nowrap;">${item.created_at}</td>
                        <td>${item.customer_name}</td>
                        <td style="font-weight:600;">${item.product_name}</td>
                        ${catCell}
                        <td style="text-align:center;font-weight:700;">${item.quantity}</td>
                        <td style="text-align:right;">${formatRupiah(item.unit_price)}</td>
                        <td style="text-align:right;font-weight:800;">
                            ${formatRupiah(item.net_subtotal)}
                            ${item.discount > 0 ? `<div style="font-size:0.68rem;font-weight:600;color:#047857;">diskon -${formatRupiah(item.discount)}</div>` : ''}
                        </td>
                    </tr>`;
            });
            tbody.innerHTML = html;
        }

        function exportExcel() {
            const s = document.getElementById('startDate').value;
            const e = document.getElementById('endDate').value;
            window.location.href = `${reportExcelUrl}?start_date=${s}&end_date=${e}`;
        }
        function exportPdf() {
            const s = document.getElementById('startDate').value;
            const e = document.getElementById('endDate').value;
            window.location.href = `${reportPdfUrl}?start_date=${s}&end_date=${e}`;
        }
        function formatRupiah(num) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
        }
    </script>
@endpush
