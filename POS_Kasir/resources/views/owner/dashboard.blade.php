@extends('layouts.app')

@section('title', ($dashboardTitle ?? 'Dashboard ' . $scopeLabel) . ' - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current'    => 'dashboard',
        'title'      => $dashboardTitle ?? 'Dashboard ' . $scopeLabel,
        'scopeBadge' => $scopeBadge,
    ])

    <main class="container">

        {{-- ═══════════════════════════════════════════════
             OWNER — Executive Dashboard
        ═══════════════════════════════════════════════ --}}
        @if (!$user->isAdmin())
        <style>
            /* ── Executive Dashboard Styles ── */
            .exec-header {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 24px;
                padding-top: 4px;
            }
            .exec-header-title {
                font-size: 1.45rem;
                font-weight: 800;
                color: var(--text-main);
                letter-spacing: -0.02em;
                line-height: 1.2;
            }
            .exec-header-subtitle {
                font-size: 0.82rem;
                color: var(--text-muted);
                margin-top: 4px;
                font-weight: 500;
            }
            .exec-refresh-btn {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 7px 14px;
                border: 1px solid var(--border-color);
                border-radius: var(--radius-md);
                background: var(--card-bg);
                font-size: 0.8rem;
                font-weight: 600;
                color: var(--text-muted);
                cursor: pointer;
                transition: all .18s;
                white-space: nowrap;
            }
            .exec-refresh-btn:hover {
                border-color: var(--primary);
                color: var(--primary);
                background: var(--primary-light);
            }
            .exec-refresh-btn svg { flex-shrink: 0; }

            /* ── Metric Cards Row ── */
            .exec-metrics {
                display: grid;
                grid-template-columns: 2fr 1fr 1fr 1fr;
                gap: 12px;
                margin-bottom: 20px;
            }
            @media (max-width: 900px) {
                .exec-metrics { grid-template-columns: 1fr 1fr; }
                .exec-metric-main { grid-column: span 2; }
            }
            @media (max-width: 540px) {
                .exec-metrics { grid-template-columns: 1fr; }
                .exec-metric-main { grid-column: span 1; }
            }

            .exec-metric {
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: var(--radius-lg);
                padding: 18px 20px;
                box-shadow: var(--shadow-sm);
                position: relative;
                overflow: hidden;
            }
            .exec-metric-main {
                background: var(--primary);
                border-color: transparent;
                color: #fff;
            }
            .exec-metric-icon {
                width: 36px;
                height: 36px;
                border-radius: var(--radius-md);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 14px;
                flex-shrink: 0;
            }
            .exec-metric-icon.icon-green  { background: #e6f2e8; color: var(--success); }
            .exec-metric-icon.icon-amber  { background: #fff4df; color: var(--warning); }
            .exec-metric-icon.icon-red    { background: #fceceb; color: var(--danger); }
            .exec-metric-icon.icon-white  { background: rgba(255,255,255,0.15); color: #fff; }
            .exec-metric-label {
                font-size: 0.7rem;
                font-weight: 700;
                letter-spacing: .07em;
                text-transform: uppercase;
                color: var(--text-muted);
                margin-bottom: 6px;
            }
            .exec-metric-main .exec-metric-label { color: rgba(255,255,255,0.65); }
            .exec-metric-value {
                font-size: 1.55rem;
                font-weight: 900;
                color: var(--text-main);
                letter-spacing: -0.03em;
                line-height: 1;
                margin-bottom: 6px;
            }
            .exec-metric-main .exec-metric-value { color: #fff; font-size: 1.9rem; }
            .exec-metric-sub {
                font-size: 0.75rem;
                color: var(--text-muted);
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .exec-metric-main .exec-metric-sub { color: rgba(255,255,255,0.65); }
            .trend-up   { color: #4ade80; font-weight: 700; }
            .trend-down { color: #fca5a5; font-weight: 700; }
            .trend-neutral { color: rgba(255,255,255,0.5); }
            .trend-up-dark   { color: var(--success); font-weight: 700; }
            .trend-down-dark { color: var(--danger);  font-weight: 700; }

            /* ── Bottom Grid ── */
            .exec-bottom-grid {
                display: grid;
                grid-template-columns: 1fr 340px;
                gap: 16px;
                margin-bottom: 20px;
            }
            @media (max-width: 860px) {
                .exec-bottom-grid { grid-template-columns: 1fr; }
            }

            /* ── Section Card ── */
            .exec-section {
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-sm);
                overflow: hidden;
            }
            .exec-section-header {
                padding: 16px 20px;
                border-bottom: 1px solid var(--border-color);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            .exec-section-title {
                font-size: 0.9rem;
                font-weight: 700;
                color: var(--text-main);
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .exec-section-title svg { color: var(--text-muted); flex-shrink: 0; }
            .exec-section-body { padding: 20px; }

            /* ── Daily Stats Mini Grid ── */
            .exec-daily-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
                margin-bottom: 20px;
            }
            @media (max-width: 600px) {
                .exec-daily-grid { grid-template-columns: repeat(2, 1fr); }
            }
            .exec-daily-item {
                background: var(--bg-main);
                border-radius: var(--radius-md);
                padding: 12px;
                text-align: center;
            }
            .exec-daily-item-label {
                font-size: 0.65rem;
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: .06em;
                margin-bottom: 5px;
            }
            .exec-daily-item-value {
                font-size: 1.1rem;
                font-weight: 800;
                color: var(--text-main);
                letter-spacing: -0.02em;
            }

            /* ── Top Menu Table ── */
            .exec-top-menu-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid var(--border-color);
                gap: 10px;
            }
            .exec-top-menu-row:last-child { border-bottom: none; }
            .exec-menu-rank {
                width: 22px;
                height: 22px;
                border-radius: 50%;
                background: var(--bg-main);
                font-size: 0.72rem;
                font-weight: 800;
                color: var(--text-muted);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .exec-menu-rank.rank-1 { background: #fef3c7; color: #92400e; }
            .exec-menu-rank.rank-2 { background: #f3f4f6; color: #374151; }
            .exec-menu-rank.rank-3 { background: #fff7ed; color: #9a3412; }
            .exec-menu-name {
                flex: 1;
                font-size: 0.85rem;
                font-weight: 600;
                color: var(--text-main);
                min-width: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .exec-menu-qty {
                font-size: 0.8rem;
                font-weight: 700;
                color: var(--text-muted);
                white-space: nowrap;
            }
            .exec-menu-bar-wrap {
                width: 60px;
                height: 4px;
                background: var(--border-color);
                border-radius: 2px;
                overflow: hidden;
                flex-shrink: 0;
            }
            .exec-menu-bar { height: 100%; border-radius: 2px; background: var(--primary); }

            /* ── Right Panel ── */
            .exec-right-stack { display: flex; flex-direction: column; gap: 16px; }

            /* ── 2-col info blocks ── */
            .exec-info-row {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 0;
                border-bottom: 1px solid var(--border-color);
            }
            .exec-info-row:last-child { border-bottom: none; }
            .exec-info-icon {
                width: 34px;
                height: 34px;
                border-radius: var(--radius-md);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .exec-info-label {
                font-size: 0.72rem;
                color: var(--text-muted);
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .05em;
            }
            .exec-info-value {
                font-size: 1rem;
                font-weight: 800;
                color: var(--text-main);
                letter-spacing: -0.02em;
            }
            .exec-info-desc {
                font-size: 0.72rem;
                color: var(--text-muted);
                font-weight: 500;
                margin-top: 1px;
            }

            /* ── Stock list ── */
            .exec-stock-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 9px 0;
                border-bottom: 1px solid var(--border-color);
                gap: 8px;
            }
            .exec-stock-row:last-child { border-bottom: none; }
            .exec-stock-name {
                font-size: 0.83rem;
                font-weight: 600;
                color: var(--text-main);
                flex: 1;
                min-width: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .exec-stock-badge {
                font-size: 0.72rem;
                font-weight: 700;
                padding: 2px 8px;
                border-radius: 20px;
                white-space: nowrap;
            }
            .badge-empty   { background: #fceceb; color: var(--danger); }
            .badge-low     { background: #fff4df; color: var(--warning); }
            .badge-safe    { background: #e6f2e8; color: var(--success); }

            /* ── Divider ── */
            .exec-divider {
                height: 1px;
                background: var(--border-color);
                margin: 16px 0;
            }
        </style>

        {{-- ── PAGE HEADER ── --}}
        <div class="exec-header">
            <div>
                <div class="exec-header-title">Dashboard Eksekutif</div>
                <div class="exec-header-subtitle">
                    {{ $scopeLabel }} &mdash; <span id="execDate">Memuat…</span>
                </div>
            </div>
            <button class="exec-refresh-btn" onclick="loadDailyStats()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                </svg>
                Perbarui
                <span id="lastRefreshTime" style="font-size:0.72rem; opacity:.6; margin-left:2px;"></span>
            </button>
        </div>

        {{-- ── METRIC CARDS ── --}}
        <div class="exec-metrics">

            {{-- Omset Bersih (Dominant Card) --}}
            <div class="exec-metric exec-metric-main">
                <div class="exec-metric-icon icon-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div class="exec-metric-label">Omset Bersih Hari Ini</div>
                <div id="execRevenue" class="exec-metric-value">Rp —</div>
                <div class="exec-metric-sub">
                    <span id="execRevenueTrend">Memuat perbandingan…</span>
                </div>
            </div>

            {{-- Transaksi Lunas --}}
            <div class="exec-metric">
                <div class="exec-metric-icon icon-green">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
                <div class="exec-metric-label">Transaksi Lunas</div>
                <div id="execOrders" class="exec-metric-value">—</div>
                <div id="execOrdersSub" class="exec-metric-sub" style="color:var(--text-muted);">vs kemarin</div>
            </div>

            {{-- Rata-rata Transaksi --}}
            <div class="exec-metric">
                <div class="exec-metric-icon icon-amber">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                    </svg>
                </div>
                <div class="exec-metric-label">Rata-rata Transaksi</div>
                <div id="execAvgOrder" class="exec-metric-value">Rp —</div>
                <div class="exec-metric-sub" style="color:var(--text-muted);">per transaksi lunas</div>
            </div>

            {{-- Tingkat Pembatalan --}}
            <div class="exec-metric">
                <div class="exec-metric-icon icon-red">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                <div class="exec-metric-label">Tingkat Pembatalan</div>
                <div id="execCancelRate" class="exec-metric-value">—%</div>
                <div id="execCancelSub" class="exec-metric-sub" style="color:var(--text-muted);">hari ini</div>
            </div>

        </div>

        {{-- ── BOTTOM GRID ── --}}
        <div class="exec-bottom-grid">

            {{-- LEFT: Statistik Harian + Top Menu --}}
            <div class="exec-section">
                <div class="exec-section-header">
                    <div class="exec-section-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                        </svg>
                        Ringkasan Hari Ini
                    </div>
                    <span id="dailyDate" style="font-size:0.75rem; color:var(--text-muted); font-weight:500;"></span>
                </div>
                <div class="exec-section-body">

                    {{-- Mini stats --}}
                    <div class="exec-daily-grid">
                        <div class="exec-daily-item">
                            <div class="exec-daily-item-label">Transaksi</div>
                            <div id="dailyOrders" class="exec-daily-item-value">—</div>
                        </div>
                        <div class="exec-daily-item">
                            <div class="exec-daily-item-label">Porsi Terjual</div>
                            <div id="dailyItems" class="exec-daily-item-value">—</div>
                        </div>
                        <div class="exec-daily-item">
                            <div class="exec-daily-item-label">Diskon</div>
                            <div id="dailyDiscount" class="exec-daily-item-value" style="font-size:0.9rem;">—</div>
                        </div>
                        <div class="exec-daily-item">
                            <div class="exec-daily-item-label">Omset Bersih</div>
                            <div id="dailyRevenue" class="exec-daily-item-value" style="font-size:0.9rem;">—</div>
                        </div>
                    </div>

                    <div class="exec-divider"></div>

                    {{-- Top menu --}}
                    <div style="margin-bottom:12px;">
                        <div style="font-size:0.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="18 20 18 10"/><polyline points="12 20 12 4"/><polyline points="6 20 6 14"/>
                            </svg>
                            Menu Terlaris
                        </div>
                        <div id="dailyTopMenus">
                            <div style="text-align:center; padding:20px 0; color:var(--text-muted); font-size:0.82rem;">Memuat data…</div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- RIGHT PANEL --}}
            <div class="exec-right-stack">

                {{-- Jam Tersibuk + Stok Kritis --}}
                <div class="exec-section">
                    <div class="exec-section-header">
                        <div class="exec-section-title">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            Operasional
                        </div>
                    </div>
                    <div class="exec-section-body" style="padding-top:14px; padding-bottom:14px;">
                        <div class="exec-info-row">
                            <div class="exec-info-icon" style="background:var(--bg-main);">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div class="exec-info-label">Jam Tersibuk</div>
                                <div id="execPeakHour" class="exec-info-value">—</div>
                                <div id="execPeakDesc" class="exec-info-desc"></div>
                            </div>
                        </div>
                        <div class="exec-info-row">
                            <div class="exec-info-icon" style="background:#fff7ed;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div class="exec-info-label">Belum Lunas</div>
                                <div id="execUnpaidDetail" class="exec-info-value">—</div>
                                <div id="execUnpaidDesc" class="exec-info-desc"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Stok Kritis --}}
                <div class="exec-section" style="flex:1;">
                    <div class="exec-section-header">
                        <div class="exec-section-title">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            Stok Kritis
                        </div>
                        <a href="{{ route('owner.stock') }}" style="font-size:0.75rem; font-weight:600; color:var(--primary); text-decoration:none;">Lihat semua</a>
                    </div>
                    <div class="exec-section-body" style="padding-top:14px; padding-bottom:14px;">
                        <div id="execCriticalStock">
                            <div style="text-align:center; padding:16px 0; color:var(--text-muted); font-size:0.82rem;">Memuat…</div>
                        </div>
                    </div>
                </div>

            </div>{{-- end right stack --}}
        </div>{{-- end bottom grid --}}

        @else
        {{-- ═══════════════════════════════════════════════
             ADMIN — Queue Dashboard (unchanged)
        ═══════════════════════════════════════════════ --}}
            @push('navbar-actions')
                <button id="btnToggleSound" class="btn btn-outline btn-sm" style="display:flex;align-items:center;gap:6px;">
                    <svg id="soundIconOn" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                    <svg id="soundIconOff" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
                    Audio
                </button>
            @endpush

            <div class="owner-welcome-card">
                <div>
                    <div style="font-size:1.15rem; font-weight:800; color:#0f172a;">Halo, {{ $user->name }}</div>
                    <div style="font-size:0.82rem; color:#64748b; margin-top:3px; font-weight:500;">Antrean pesanan dapur — auto-update setiap 5 detik</div>
                </div>
                <div class="polling-badge">
                    <span class="pulse-dot"></span>
                    <span>Live Refresh (5s)</span>
                </div>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card border-danger">
                    <div class="kpi-label">ANTREAN MASUK</div>
                    <div id="kpiPending" class="kpi-val text-danger">0</div>
                </div>
                <div class="kpi-card border-warning">
                    <div class="kpi-label">SEDANG DIMASAK</div>
                    <div id="kpiCooking" class="kpi-val text-warning">0</div>
                </div>
                <div class="kpi-card border-success">
                    <div class="kpi-label">SELESAI HARI INI</div>
                    <div id="kpiDone" class="kpi-val text-success">0</div>
                </div>
                <div class="kpi-card border-primary">
                    <div class="kpi-label">OMSET LUNAS HARI INI</div>
                    <div id="kpiRevenue" class="kpi-val text-primary" style="font-size:1.3rem;">Rp 0</div>
                </div>
            </div>

            {{-- ── STATISTIK HARIAN OPERASIONAL ── --}}
            <div id="adminDailyStats" style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px;">
                <div class="kpi-card" style="border-left:3px solid var(--primary);">
                    <div class="kpi-label">TRANSAKSI LUNAS</div>
                    <div id="adTransaksi" class="kpi-val" style="font-size:1.3rem; color:var(--primary);">—</div>
                    <div id="adTransaksiSub" style="font-size:0.72rem; color:#64748b; margin-top:3px;"></div>
                </div>
                <div class="kpi-card" style="border-left:3px solid #9b641c;">
                    <div class="kpi-label">RATA-RATA TRANSAKSI</div>
                    <div id="adRataRata" class="kpi-val" style="font-size:1.1rem; color:#9b641c;">—</div>
                    <div style="font-size:0.72rem; color:#64748b; margin-top:3px;">per transaksi lunas</div>
                </div>
                <div class="kpi-card" style="border-left:3px solid #ad3e39;">
                    <div class="kpi-label">TINGKAT PEMBATALAN</div>
                    <div id="adCancel" class="kpi-val" style="font-size:1.3rem; color:#ad3e39;">—%</div>
                    <div id="adCancelSub" style="font-size:0.72rem; color:#64748b; margin-top:3px;"></div>
                </div>
                <div class="kpi-card" style="border-left:3px solid #64748b;">
                    <div class="kpi-label">JAM TERSIBUK</div>
                    <div id="adPeak" class="kpi-val" style="font-size:1rem; color:#0f172a;">—</div>
                    <div id="adPeakSub" style="font-size:0.72rem; color:#64748b; margin-top:3px;"></div>
                </div>
                <div class="kpi-card" style="border-left:3px solid #b45309;">
                    <div class="kpi-label">BELUM LUNAS</div>
                    <div id="adUnpaid" class="kpi-val" style="font-size:1.3rem; color:#b45309;">—</div>
                    <div id="adUnpaidSub" style="font-size:0.72rem; color:#64748b; margin-top:3px;"></div>
                </div>
                <div class="kpi-card" style="border-left:3px solid #15803d; grid-row:span 1;">
                    <div class="kpi-label">MENU TERLARIS HARI INI</div>
                    <div id="adTopMenu" style="font-size:0.82rem; color:#0f172a; margin-top:4px; font-weight:700;">—</div>
                    <div id="adTopMenuSub" style="font-size:0.72rem; color:#64748b; margin-top:2px;"></div>
                </div>
            </div>

            <div class="card p-3 mb-4" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                    <div style="width:34px;height:34px;border-radius:9px;background:#e8f0e9;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25382d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </div>
                    <div style="min-width:0;">
                        <div style="font-weight:700; font-size:0.9rem; color:#0f172a;">Notifikasi &amp; Aplikasi</div>
                        <div id="pushStatusText" style="font-size:0.78rem; color:#64748b; margin-top:2px;">Memuat status…</div>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" id="btnInstallApp" class="btn btn-outline btn-sm" style="display:none;" onclick="installApp()">Pasang Aplikasi</button>
                    <button type="button" id="btnTestPush" class="btn btn-outline btn-sm" style="display:none;" onclick="sendTestPush()">Tes Notifikasi</button>
                    <button type="button" id="btnTogglePush" class="btn btn-primary btn-sm" onclick="togglePush()">Aktifkan Notifikasi</button>
                </div>
            </div>

            <div class="orders-section-header">
                <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a;">Antrean Pesanan Real-time</h3>
                <div class="order-filter-chips">
                    <button type="button" class="filter-chip active" data-status="all" onclick="filterOrderStatus('all')">Semua</button>
                    <button type="button" class="filter-chip" data-status="pending" onclick="filterOrderStatus('pending')" style="display:flex;align-items:center;gap:5px;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Menunggu
                    </button>
                    <button type="button" class="filter-chip" data-status="cooking" onclick="filterOrderStatus('cooking')" style="display:flex;align-items:center;gap:5px;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 1 7 7c0 5-7 13-7 13S5 14 5 9a7 7 0 0 1 7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                        Dimasak
                    </button>
                </div>
            </div>

            <div class="order-search">
                <span class="order-search-icon" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="search" id="orderSearch" class="form-control" autocomplete="off"
                       placeholder="Cari no. meja, invoice, nama pembeli, atau menu…"
                       aria-label="Cari pesanan" oninput="searchOrders(this.value)">
                <span id="orderSearchCount" class="order-search-count"></span>
            </div>

            <div id="ordersContainer" class="orders-grid"></div>

            <!-- Modal Terima Bayar Tunai -->
            <div class="modal-overlay" id="cashModal" onclick="if (event.target === this) closeCashModal()">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="cashModalTitle">
                    <div class="modal-sheet-handle"></div>
                    <div class="modal-header">
                        <div>
                            <h3 id="cashModalTitle" style="font-size:1.15rem; font-weight:800; color:#0f172a;">Terima Pembayaran</h3>
                            <p id="cashModalInvoice" style="font-size:0.75rem; color:#64748b; margin-top:2px;"></p>
                        </div>
                        <button type="button" class="btn btn-outline modal-close-btn" onclick="closeCashModal()" aria-label="Tutup">&times;</button>
                    </div>
                    <form onsubmit="submitCashPayment(event)">
                        <div class="modal-body">
                            <div class="cash-total-row"><span>Total Tagihan</span><strong id="cashTotal">Rp 0</strong></div>
                            <div class="form-group">
                                <label class="form-label" for="cashReceived">Uang Diterima</label>
                                <div class="cash-input-wrap"><span>Rp</span><input type="text" id="cashReceived" class="form-control" inputmode="numeric" autocomplete="off" placeholder="0" oninput="onCashInput(this)"></div>
                            </div>
                            <div id="cashQuickButtons" class="cash-quick-buttons"></div>
                            <div id="cashChangeBox" class="cash-change-box"><span id="cashChangeLabel">Kembalian</span><strong id="cashChange">Rp 0</strong></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeCashModal()">Batal</button>
                            <button type="submit" id="btnCashSubmit" class="btn btn-success" disabled>Konfirmasi Lunas</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Diskon -->
            <div class="modal-overlay" id="discountModal" onclick="if (event.target === this) closeDiscountModal()">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="discountModalTitle">
                    <div class="modal-sheet-handle"></div>
                    <div class="modal-header">
                        <div>
                            <h3 id="discountModalTitle" style="font-size:1.15rem; font-weight:800; color:#0f172a;">Potongan Harga</h3>
                            <p id="discountModalInvoice" style="font-size:0.75rem; color:#64748b; margin-top:2px;"></p>
                        </div>
                        <button type="button" class="btn btn-outline modal-close-btn" onclick="closeDiscountModal()" aria-label="Tutup">&times;</button>
                    </div>
                    <form onsubmit="submitDiscount(event)">
                        <div class="modal-body">
                            <div class="cash-total-row"><span>Total Belanja</span><strong id="discountSubtotal">Rp 0</strong></div>
                            <div id="discountCurrent" class="discount-current" style="display:none;"></div>
                            <div class="toggle-service" style="margin-bottom:14px;">
                                <div class="toggle-btn active" id="btnDiscountManual" onclick="setDiscountMode('manual')">Nominal Manual</div>
                                <div class="toggle-btn" id="btnDiscountPromo" onclick="setDiscountMode('promo')">Kupon Promo</div>
                            </div>
                            <div id="discountManualFields">
                                <div class="form-group">
                                    <label class="form-label" for="discountAmount">Nominal Diskon</label>
                                    <div class="cash-input-wrap"><span>Rp</span><input type="text" id="discountAmount" class="form-control" inputmode="numeric" autocomplete="off" placeholder="0" oninput="onDiscountInput(this)"></div>
                                </div>
                                <div id="discountQuickButtons" class="cash-quick-buttons"></div>
                                <div class="form-group">
                                    <label class="form-label" for="discountNote">Keterangan <span class="form-hint" style="display:inline;">(opsional)</span></label>
                                    <input type="text" id="discountNote" class="form-control" maxlength="255" placeholder="Contoh: pelanggan tetap">
                                </div>
                            </div>
                            <div id="discountPromoFields" style="display:none;">
                                <div class="form-group">
                                    <label class="form-label" for="discountCode">Kode Kupon</label>
                                    <input type="text" id="discountCode" class="form-control" maxlength="30" autocomplete="off" placeholder="HEMAT10" style="text-transform:uppercase;" oninput="updateDiscountPreview()">
                                    <small class="form-hint">Potongan dihitung otomatis oleh sistem.</small>
                                </div>
                            </div>
                            <div id="discountPreviewBox" class="cash-change-box"><span>Total Setelah Diskon</span><strong id="discountPreview">Rp 0</strong></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="btnDiscountRemove" class="btn btn-outline-danger" style="display:none;" onclick="removeDiscount()">Hapus Diskon</button>
                            <button type="button" class="btn btn-outline" onclick="closeDiscountModal()">Batal</button>
                            <button type="submit" id="btnDiscountSubmit" class="btn btn-primary" disabled>Pasang Diskon</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Void -->
            <div class="modal-overlay" id="voidModal" onclick="if (event.target === this) closeVoidModal()">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="voidModalTitle">
                    <div class="modal-sheet-handle"></div>
                    <div class="modal-header">
                        <div>
                            <h3 id="voidModalTitle" style="font-size:1.15rem; font-weight:800; color:#b91c1c;">Batalkan Pesanan</h3>
                            <p id="voidModalInvoice" style="font-size:0.75rem; color:#64748b; margin-top:2px;"></p>
                        </div>
                        <button type="button" class="btn btn-outline modal-close-btn" onclick="closeVoidModal()" aria-label="Tutup">&times;</button>
                    </div>
                    <form onsubmit="submitVoidOrder(event)">
                        <div class="modal-body">
                            <div class="void-summary">
                                <div style="font-weight:700; margin-bottom:6px; color:#334155;">Seluruh isi invoice akan dibatalkan:</div>
                                <ul id="voidItemList" class="void-item-list"></ul>
                                <div class="cash-total-row" style="margin-top:8px;"><span>Total Tagihan</span><strong id="voidTotal">Rp 0</strong></div>
                            </div>
                            <div id="voidPaidWarning" class="void-paid-warning" style="display:none;">
                                <strong>Perhatian:</strong> Pesanan ini sudah lunas. Kembalikan uang pelanggan setelah pembatalan.
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="voidReason">Alasan Pembatalan</label>
                                <div class="void-reason-chips">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="setVoidReason(this.textContent)">Pelanggan membatalkan</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="setVoidReason(this.textContent)">Menu habis</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="setVoidReason(this.textContent)">Salah input pesanan</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="setVoidReason(this.textContent)">Pesanan ganda</button>
                                </div>
                                <textarea id="voidReason" class="form-control" rows="2" maxlength="255" placeholder="Tulis alasan pembatalan…" oninput="updateVoidSubmit()"></textarea>
                            </div>
                            <p style="font-size:0.78rem; color:#64748b;">Stok menu pada pesanan ini akan dikembalikan otomatis.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeVoidModal()">Kembali</button>
                            <button type="submit" id="btnVoidSubmit" class="btn btn-danger" disabled>Batalkan Pesanan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </main>
@endsection

@push('scripts')
@if ($user->isAdmin())
    <script src="{{ asset_versioned('assets/js/owner.js') }}"></script>
    <script>
        const pushConfigUrl = @js(route('owner.push.show'));
        const adminDailyUrl = @js(route('owner.reports.daily'));
        let pushState = { enabled: false, public_key: null, subscribed: false };

        document.addEventListener('DOMContentLoaded', () => {
            initOwnerPolling(@js($ownerTypes), @js($startWorkingLabel));
            initPwaControls();
            loadAdminDailyStats();
            setInterval(() => { if (document.visibilityState === 'visible') loadAdminDailyStats(); }, 60000);
        });

        const fmtRp = n => new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', minimumFractionDigits:0 }).format(n);
        const fmtShortRp = n => {
            if (n >= 1_000_000) return 'Rp ' + (n/1_000_000).toFixed(1).replace('.0','') + ' jt';
            if (n >= 1_000)    return 'Rp ' + Math.round(n/1_000) + ' rb';
            return fmtRp(n);
        };

        async function loadAdminDailyStats() {
            try {
                const res  = await fetch(adminDailyUrl);
                const data = await res.json();
                if (!data.success) return;
                const d = data.data;

                // Transaksi lunas
                document.getElementById('adTransaksi').textContent = d.total_orders;
                const diff = d.total_orders - d.yesterday_orders;
                document.getElementById('adTransaksiSub').textContent =
                    diff > 0 ? `+${diff} vs kemarin` : diff < 0 ? `${diff} vs kemarin` : 'Sama dengan kemarin';

                // Rata-rata
                document.getElementById('adRataRata').textContent = fmtShortRp(d.avg_order_value);

                // Pembatalan
                document.getElementById('adCancel').textContent = d.cancel_rate + '%';
                document.getElementById('adCancelSub').textContent = d.canceled_count + ' pesanan dibatalkan';

                // Jam tersibuk
                if (d.peak_hour !== null) {
                    const h = d.peak_hour;
                    document.getElementById('adPeak').textContent =
                        String(h).padStart(2,'0') + ':00 – ' + String(h+1).padStart(2,'0') + ':00';
                    document.getElementById('adPeakSub').textContent = d.peak_hour_count + ' pesanan masuk';
                } else {
                    document.getElementById('adPeak').textContent = 'Belum ada data';
                }

                // Belum lunas
                const unpaid = d.unpaid_today ?? 0;
                document.getElementById('adUnpaid').textContent = unpaid + ' transaksi';
                document.getElementById('adUnpaidSub').textContent = unpaid > 0 ? 'Perlu dikonfirmasi kasir' : 'Semua sudah lunas';

                // Top menu
                if (d.top_menus && d.top_menus.length) {
                    const top = d.top_menus[0];
                    document.getElementById('adTopMenu').textContent = top.product_name;
                    document.getElementById('adTopMenuSub').textContent = top.qty_sold + ' porsi terjual';
                } else {
                    document.getElementById('adTopMenu').textContent = 'Belum ada transaksi';
                }
            } catch (e) { console.error('admin daily stats:', e); }
        }

        async function initPwaControls() {
            const statusEl  = document.getElementById('pushStatusText');
            const toggleBtn = document.getElementById('btnTogglePush');
            const testBtn   = document.getElementById('btnTestPush');
            const installBtn= document.getElementById('btnInstallApp');
            const unsupported = window.PWA.unsupportedReason();
            const standalone  = window.PWA.isStandalone();
            if (installBtn) standalone ? installBtn.style.display = 'none' : window.PWA.registerInstallPrompt(installBtn);
            if (unsupported) { statusEl.textContent = unsupported; toggleBtn.style.display = 'none'; testBtn.style.display = 'none'; return; }
            const res = await fetch(pushConfigUrl);
            const data = await res.json();
            if (data.success) pushState = data.data;
            renderPushStatus();
        }
        function renderPushStatus() {
            const statusEl  = document.getElementById('pushStatusText');
            const toggleBtn = document.getElementById('btnTogglePush');
            const testBtn   = document.getElementById('btnTestPush');
            if (!pushState.enabled) { statusEl.textContent = 'Notifikasi belum dikonfigurasi.'; toggleBtn.style.display = 'none'; testBtn.style.display = 'none'; return; }
            if (pushState.subscribed) { statusEl.textContent = `Aktif — ${(pushState.devices||[]).length} perangkat.`; toggleBtn.textContent = '🔕 Matikan'; toggleBtn.className = 'btn btn-outline btn-sm'; testBtn.style.display = ''; return; }
            statusEl.textContent = 'Belum aktif.'; toggleBtn.textContent = '🔔 Aktifkan Notifikasi'; toggleBtn.className = 'btn btn-primary btn-sm'; testBtn.style.display = 'none';
        }
        async function togglePush() {
            const result = pushState.subscribed ? await window.PWA.unsubscribePush() : await window.PWA.subscribePush(pushState.public_key);
            if (!result.success) { alert(result.message); return; }
            pushState.subscribed = !pushState.subscribed;
            const res = await fetch(pushConfigUrl); const data = await res.json();
            if (data.success) pushState = data.data;
            renderPushStatus();
        }
        async function installApp()   { await window.PWA.promptInstall(); }
        async function sendTestPush() { const data = await window.PWA.sendTestPush(); alert(data.message || (data.success ? 'Dikirim' : 'Gagal')); }
    </script>
@else
    <script>
        const dailyStatsUrl = @js(route('owner.reports.daily'));
        const fmt = n => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n);
        const fmtShort = n => {
            if (n >= 1_000_000) return 'Rp ' + (n / 1_000_000).toFixed(1).replace('.0','') + ' jt';
            if (n >= 1_000)    return 'Rp ' + (n / 1_000).toFixed(0) + ' rb';
            return fmt(n);
        };

        document.addEventListener('DOMContentLoaded', () => loadDailyStats());

        async function loadDailyStats() {
            try {
                const res  = await fetch(dailyStatsUrl);
                const data = await res.json();
                if (!data.success) return;
                const d = data.data;

                // Header date
                document.getElementById('execDate').textContent =
                    new Date(d.date + 'T00:00:00').toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
                document.getElementById('dailyDate').textContent =
                    new Date(d.date + 'T00:00:00').toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' });

                // ── Metric cards ──
                document.getElementById('execRevenue').textContent = fmtShort(d.net_revenue);

                // Trend vs yesterday
                const trendEl = document.getElementById('execRevenueTrend');
                if (d.revenue_change_pct !== null) {
                    const up  = d.revenue_change_pct >= 0;
                    const pct = Math.abs(d.revenue_change_pct);
                    const arrow = up
                        ? `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>`
                        : `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>`;
                    trendEl.innerHTML = `<span class="${up ? 'trend-up' : 'trend-down'}" style="display:inline-flex;align-items:center;gap:3px;">${arrow} ${pct}%</span>&nbsp;vs kemarin`;
                } else {
                    trendEl.textContent = 'Data kemarin belum tersedia';
                }

                // Orders
                document.getElementById('execOrders').textContent = d.total_orders;
                const orderDiff = d.total_orders - d.yesterday_orders;
                document.getElementById('execOrdersSub').innerHTML = orderDiff !== 0
                    ? `<span class="${orderDiff > 0 ? 'trend-up-dark' : 'trend-down-dark'}">${orderDiff > 0 ? '+' : ''}${orderDiff}</span> vs kemarin`
                    : `Sama dengan kemarin`;

                // Avg order
                document.getElementById('execAvgOrder').textContent = fmtShort(d.avg_order_value);

                // Cancel rate
                const cancelEl = document.getElementById('execCancelRate');
                cancelEl.textContent = d.cancel_rate + '%';
                cancelEl.style.color = d.cancel_rate > 10 ? 'var(--danger)' : d.cancel_rate > 5 ? 'var(--warning)' : 'var(--success)';
                document.getElementById('execCancelSub').textContent = d.canceled_count + ' pesanan dibatalkan';

                // ── Daily stats mini ──
                document.getElementById('dailyOrders').textContent  = d.total_orders;
                document.getElementById('dailyItems').textContent   = d.total_items;
                document.getElementById('dailyDiscount').textContent= fmtShort(d.discount);
                document.getElementById('dailyRevenue').textContent = fmtShort(d.net_revenue);

                // ── Top menus ──
                const topEl  = document.getElementById('dailyTopMenus');
                const maxQty = d.top_menus.length ? Math.max(...d.top_menus.map(m => m.qty_sold)) : 1;
                if (!d.top_menus.length) {
                    topEl.innerHTML = '<div style="text-align:center;padding:20px 0;color:var(--text-muted);font-size:0.82rem;">Belum ada transaksi hari ini</div>';
                } else {
                    const rankClass = i => i === 0 ? 'rank-1' : i === 1 ? 'rank-2' : i === 2 ? 'rank-3' : '';
                    topEl.innerHTML = d.top_menus.map((m, i) => `
                        <div class="exec-top-menu-row">
                            <div class="exec-menu-rank ${rankClass(i)}">${i + 1}</div>
                            <div class="exec-menu-name">${m.product_name}</div>
                            <div class="exec-menu-bar-wrap">
                                <div class="exec-menu-bar" style="width:${Math.round((m.qty_sold / maxQty) * 100)}%"></div>
                            </div>
                            <div class="exec-menu-qty">${m.qty_sold} porsi</div>
                        </div>`).join('');
                }

                // ── Jam tersibuk ──
                if (d.peak_hour !== null) {
                    const h = d.peak_hour;
                    document.getElementById('execPeakHour').textContent = `${String(h).padStart(2,'0')}:00 – ${String(h+1).padStart(2,'0')}:00`;
                    document.getElementById('execPeakDesc').textContent = `${d.peak_hour_count} pesanan masuk`;
                } else {
                    document.getElementById('execPeakHour').textContent = 'Belum ada data';
                    document.getElementById('execPeakDesc').textContent = '';
                }

                // ── Belum lunas ──
                const unpaid = d.unpaid_today ?? 0;
                document.getElementById('execUnpaidDetail').textContent = unpaid + ' transaksi';
                document.getElementById('execUnpaidDesc').textContent   = unpaid > 0 ? 'Perlu ditagih ke kasir' : 'Semua transaksi lunas';

                // ── Stok kritis ──
                const stockEl = document.getElementById('execCriticalStock');
                if (!d.critical_stock.length) {
                    stockEl.innerHTML = `
                        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;">
                            <div style="width:30px;height:30px;border-radius:50%;background:#e6f2e8;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <span style="font-size:0.83rem;font-weight:600;color:var(--success);">Semua stok aman</span>
                        </div>`;
                } else {
                    stockEl.innerHTML = d.critical_stock.map(p => {
                        const isEmpty   = p.stock <= 0;
                        const badgeClass= isEmpty ? 'badge-empty' : 'badge-low';
                        const label     = isEmpty ? 'Habis' : `${p.stock} porsi`;
                        return `<div class="exec-stock-row">
                            <span class="exec-stock-name">${p.name}</span>
                            <span class="exec-stock-badge ${badgeClass}">${label}</span>
                        </div>`;
                    }).join('');
                }

                // Update time
                document.getElementById('lastRefreshTime').textContent =
                    new Date().toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });

            } catch (e) { console.error('exec dashboard error', e); }
        }
    </script>
@endif
@endpush
