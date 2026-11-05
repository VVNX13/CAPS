@extends('layouts.app')

@section('title', 'Dapur — Kitchen Display · ' . config('app.name'))

@section('content')
<style>
/* ══════════════════════════════════════════════════════════
   Dapur / Kitchen Display System (KDS) — Theme: Warm Elegant
   Matches Owner & Kasir theme (--bg-main: #f7f5ef, --primary: #25382d)
══════════════════════════════════════════════════════════ */

/* ── Tab Bar ── */
.kds-tab-nav {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 12px;
}
.kds-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 700;
    border: 1px solid var(--border-color);
    background: #fff;
    color: var(--text-muted);
    cursor: pointer;
    transition: all .18s;
}
.kds-tab-btn:hover {
    color: var(--text-main);
    border-color: #94a3b8;
}
.kds-tab-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}
.kds-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 800;
    background: #f1f5f9;
    color: #475569;
}
.kds-tab-btn.active .kds-tab-count {
    background: rgba(255,255,255,.2);
    color: #fff;
}

/* ── 2-Column Section Layout for Active Orders ── */
.kds-sections-wrap {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
}
@media (max-width: 992px) {
    .kds-sections-wrap {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

/* Section Container */
.kds-section {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* Section Header */
.kds-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #fff;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}
.kds-section-title-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}
.kds-section-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.kds-section-icon.pending {
    background: #fee2e2;
    color: #dc2626;
}
.kds-section-icon.cooking {
    background: #fef3c7;
    color: #d97706;
}
.kds-section-title {
    font-size: 0.98rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
.kds-section-subtitle {
    font-size: 0.74rem;
    color: #64748b;
    margin-top: 2px;
    font-weight: 500;
}
.kds-section-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 800;
}
.kds-section-badge.pending {
    background: #fee2e2;
    color: #b91c1c;
}
.kds-section-badge.cooking {
    background: #fef3c7;
    color: #b45309;
}

/* ── Ticket Grid ── */
.kds-tickets-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* ── Ticket Card ── */
.kds-card {
    background: #fff;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: transform .18s, box-shadow .18s;
}
.kds-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -3px rgb(0 0 0 / 0.08);
}
.kds-card.card-pending {
    border-left: 4px solid #ef4444;
}
.kds-card.card-cooking {
    border-left: 4px solid #f59e0b;
    border-color: #fed7aa;
}

/* Ticket Header */
.kds-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 14px 16px 12px;
    border-bottom: 1px solid #f1f5f9;
    gap: 10px;
}
.kds-card-inv {
    font-size: 0.75rem;
    font-weight: 800;
    color: #64748b;
    letter-spacing: 0.03em;
}
.kds-card-cust {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    margin-top: 2px;
}
.kds-card-tags {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 5px;
    flex-wrap: wrap;
}
.kds-tag-table {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 6px;
    background: #e6f4ea;
    color: #1e7e34;
    font-size: 0.72rem;
    font-weight: 800;
}
.kds-tag-takeaway {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 6px;
    background: #e8edf0;
    color: #1e3a4a;
    font-size: 0.72rem;
    font-weight: 800;
}
.kds-tag-age {
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 600;
}
.kds-tag-age.late {
    color: #ef4444;
    font-weight: 800;
}

/* Items List inside Ticket */
.kds-card-items {
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.kds-card-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 8px 10px;
    background: #f8fafc;
    border-radius: var(--radius-md);
    gap: 10px;
}
.kds-item-main {
    flex: 1;
    min-width: 0;
}
.kds-item-name {
    font-size: 0.92rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}
.kds-item-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 3px;
    flex-wrap: wrap;
}
.kds-item-notes {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.76rem;
    color: #9b641c;
    background: #fff4df;
    padding: 2px 7px;
    border-radius: 4px;
    margin-top: 4px;
    font-weight: 600;
}
.kds-item-qty {
    flex-shrink: 0;
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--primary);
    background: #e2e8f0;
    padding: 3px 9px;
    border-radius: 6px;
}

/* Order Notes Callout */
.kds-order-note {
    margin: 0 16px 12px;
    padding: 9px 12px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: var(--radius-md);
    font-size: 0.78rem;
    color: #92400e;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    line-height: 1.4;
}

/* Ticket Action Bar */
.kds-card-action {
    padding: 10px 16px 14px;
    border-top: 1px solid #f1f5f9;
}
.kds-card-action .btn {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 16px;
    font-size: 0.88rem;
    font-weight: 800;
}

/* Empty State Card */
.kds-empty-box {
    text-align: center;
    padding: 36px 18px;
    background: #fff;
    border-radius: var(--radius-lg);
    border: 1px dashed var(--border-color);
}
.kds-empty-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
}
.kds-empty-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 2px;
}
.kds-empty-sub {
    font-size: 0.78rem;
    color: #94a3b8;
}

/* ── History View ── */
.kds-history-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr));
    gap: 14px;
}
.kds-history-card {
    background: #fff;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    padding: 14px 16px;
}
.kds-history-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 8px;
}
.kds-history-inv {
    font-size: 0.74rem;
    font-weight: 800;
    color: #64748b;
}
.kds-history-cust {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    margin-top: 2px;
}
.kds-history-time {
    font-size: 0.72rem;
    color: #10b981;
    font-weight: 700;
    background: #ecfdf5;
    padding: 2px 7px;
    border-radius: 6px;
}
.kds-history-items-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.kds-history-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.82rem;
    color: #475569;
}
</style>

{{-- ── Topbar (Same style as owner/kasir) ── --}}
<header class="navbar">
    <div class="navbar-container">
        {{-- Brand --}}
        <a href="{{ route('dapur.dashboard') }}" class="brand-logo" style="gap:8px; flex-shrink:0; text-decoration:none;">
            <span style="
                width:28px; height:28px; border-radius:7px; flex-shrink:0;
                display:flex; align-items:center; justify-content:center;
                background:#dde6da; color:#25382d;
            ">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#25382d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/>
                    <path d="M21 15V2a5 5 0 0 0-5 5v6h3l-1 11h3l-1-11h1z"/>
                </svg>
            </span>
            <span style="font-weight:800; font-size:0.95rem; color:#252825; letter-spacing:-0.01em;">Dapur</span>
            <span class="brand-badge badge-drink" style="font-size:0.65rem; letter-spacing:0.04em;">
                DAPUR
            </span>
        </a>

        {{-- Actions --}}
        <div class="owner-nav-actions">
            {{-- Audio Chime Toggle --}}
            <button type="button" id="btnToggleSound" class="btn btn-nav btn-primary" title="Suara Notifikasi Aktif">
                <svg id="soundIconOn" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                </svg>
                <svg id="soundIconOff" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>
                </svg>
                Audio
            </button>

            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}" style="display:inline; flex-shrink:0;">
                @csrf
                <button type="submit" class="btn btn-nav btn-danger">Keluar</button>
            </form>
        </div>
    </div>
</header>

<main class="container">
    {{-- ── Welcome Card ── --}}
    <div class="owner-welcome-card">
        <div>
            <div style="font-size:1.15rem; font-weight:800; color:#0f172a;">Halo, {{ $user->name }}</div>
            <div style="font-size:0.82rem; color:#64748b; margin-top:3px; font-weight:500;">
                Monitor antrean pesanan dapur — auto-update real-time setiap 5 detik
            </div>
        </div>
        <div class="polling-badge">
            <span class="pulse-dot"></span>
            <span>Live Refresh (5s)</span>
        </div>
    </div>

    {{-- ── KPI Grid ── --}}
    <div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 20px;">
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
    </div>

    {{-- ── Tab Navigation ── --}}
    <div class="kds-tab-nav">
        <button type="button" class="kds-tab-btn active" id="tabActiveOrders" onclick="switchTab('active')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            Antrean Dapur
            <span class="kds-tab-count" id="countActiveTab">0</span>
        </button>
        <button type="button" class="kds-tab-btn" id="tabHistoryOrders" onclick="switchTab('history')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            Riwayat Selesai Hari Ini
            <span class="kds-tab-count" id="countHistoryTab">0</span>
        </button>
    </div>

    {{-- ── PANEL 1: ANTREAN AKTIF (DIVIDED INTO 2 SECTIONS) ── --}}
    <div id="panelActiveOrders">
        <div class="kds-sections-wrap">

            {{-- ── SECTION A: ANTREAN MASUK (MENUNGGU) ── --}}
            <div class="kds-section">
                <div class="kds-section-head">
                    <div class="kds-section-title-wrap">
                        <div class="kds-section-icon pending">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div>
                            <div class="kds-section-title">Antrean Masuk</div>
                            <div class="kds-section-subtitle">Pesanan baru yang menunggu dimasak</div>
                        </div>
                    </div>
                    <span class="kds-section-badge pending" id="badgePendingCount">0 pesanan</span>
                </div>

                <div id="gridPending" class="kds-tickets-list">
                    <div class="kds-empty-box">
                        <div class="kds-empty-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div class="kds-empty-title">Tidak ada antrean menunggu</div>
                        <div class="kds-empty-sub">Pesanan baru dari pelanggan akan muncul di sini.</div>
                    </div>
                </div>
            </div>

            {{-- ── SECTION B: SEDANG DIMASAK (DALAM PROSES) ── --}}
            <div class="kds-section">
                <div class="kds-section-head">
                    <div class="kds-section-title-wrap">
                        <div class="kds-section-icon cooking">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="kds-section-title">Sedang Dimasak</div>
                            <div class="kds-section-subtitle">Pesanan dalam proses olah dapur</div>
                        </div>
                    </div>
                    <span class="kds-section-badge cooking" id="badgeCookingCount">0 diproses</span>
                </div>

                <div id="gridCooking" class="kds-tickets-list">
                    <div class="kds-empty-box">
                        <div class="kds-empty-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                        </div>
                        <div class="kds-empty-title">Belum ada yang dimasak</div>
                        <div class="kds-empty-sub">Klik "Mulai Masak" pada antrean masuk untuk memulai.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ── PANEL 2: RIWAYAT SELESAI HARI INI ── --}}
    <div id="panelHistoryOrders" style="display:none;">
        <div id="gridHistory" class="kds-history-grid">
            <div class="kds-empty-box" style="grid-column: 1 / -1;">
                <div class="kds-empty-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="kds-empty-title">Memuat riwayat...</div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
const ordersUrl   = @js(route('dapur.orders'));
const historyUrl  = @js(route('dapur.history'));
const startSaleUrl= @js(route('dapur.sale-start'));
const completeUrl = @js(route('dapur.sale-complete'));

let currentTab    = 'active';
let lastItemId    = 0;
let pollingTimer  = null;
let historyLoaded = false;
let soundEnabled  = true;

/* ── Web Audio Synthesizer (2-tone melodic chime) ── */
function playBeepSound() {
    if (!soundEnabled) return;
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const now = ctx.currentTime;

        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, now);        // D5
        osc.frequency.setValueAtTime(880.00, now + 0.15); // A5

        gain.gain.setValueAtTime(0.35, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.6);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now);
        osc.stop(now + 0.6);
    } catch (e) {
        console.log('Audio notification error:', e);
    }
}

/* ── Helpers ── */
function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function ageLabel(createdAt) {
    const diff = Math.floor((Date.now() - new Date(createdAt)) / 60000);
    if (diff < 1)  return 'Baru saja';
    if (diff < 60) return `${diff} menit lalu`;
    return `${Math.floor(diff / 60)} jam lalu`;
}

function isLate(createdAt) {
    return (Date.now() - new Date(createdAt)) / 60000 > 15;
}

function timeStr(ts) {
    return new Date(ts).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

/* ── Tab Switcher ── */
function switchTab(tab) {
    currentTab = tab;
    document.getElementById('panelActiveOrders').style.display  = tab === 'active'  ? '' : 'none';
    document.getElementById('panelHistoryOrders').style.display = tab === 'history' ? '' : 'none';
    document.getElementById('tabActiveOrders').classList.toggle('active', tab === 'active');
    document.getElementById('tabHistoryOrders').classList.toggle('active', tab === 'history');

    if (tab === 'history' && !historyLoaded) {
        loadHistory();
    }
}

/* ── Reusable SVG Icons ── */
const iconFlame = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>`;
const iconCheck = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
const iconTable = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="4" rx="1"/><path d="M5 7v13M19 7v13M8 20h8"/></svg>`;
const iconBag   = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>`;
const iconNote  = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`;

/* ── Render Ticket Card HTML ── */
function renderTicketCard(t, isCooking) {
    const isDineIn = t.service_type === 'dine_in';
    const late = isLate(t.created_at);
    const age = ageLabel(t.created_at);

    const tableBadge = isDineIn && t.table_number
        ? `<span class="kds-tag-table">${iconTable} Meja ${escapeHtml(t.table_number)}</span>`
        : `<span class="kds-tag-takeaway">${iconBag} Takeaway</span>`;

    const orderNoteHtml = t.order_notes
        ? `<div class="kds-order-note">${iconNote} <span>${escapeHtml(t.order_notes)}</span></div>`
        : '';

    const itemsHtml = t.items.map(i => {
        const isDrink = i.owner_type === 'minuman';
        const notesHtml = i.item_notes
            ? `<div class="kds-item-notes">${iconNote} ${escapeHtml(i.item_notes)}</div>`
            : '';
        return `
            <div class="kds-card-item">
                <div class="kds-item-main">
                    <div class="kds-item-name">${escapeHtml(i.product_name)}</div>
                    <div class="kds-item-meta">
                        <span class="brand-badge ${isDrink ? 'badge-drink' : 'badge-food'}" style="font-size:0.62rem; padding:1px 6px;">
                            ${escapeHtml(i.category_name)}
                        </span>
                    </div>
                    ${notesHtml}
                </div>
                <div class="kds-item-qty">×${i.quantity}</div>
            </div>`;
    }).join('');

    const actionBtn = isCooking
        ? `<button type="button" class="btn btn-success" onclick="completeSale(${t.sale_id})">
               ${iconCheck} Tandai Selesai Masak
           </button>`
        : `<button type="button" class="btn btn-primary" onclick="startCookingSale(${t.sale_id})">
               ${iconFlame} Mulai Masak
           </button>`;

    return `
        <div class="kds-card ${isCooking ? 'card-cooking' : 'card-pending'}" id="ticket-${t.sale_id}">
            <div class="kds-card-head">
                <div>
                    <div class="kds-card-inv">${escapeHtml(t.invoice_number)}</div>
                    <div class="kds-card-cust">${escapeHtml(t.customer_name)}</div>
                    <div class="kds-card-tags">
                        ${tableBadge}
                        <span class="kds-tag-age ${late ? 'late' : ''}">· ${age}</span>
                    </div>
                </div>
                <div>
                    <span class="brand-badge" style="${isCooking ? 'background:#fef3c7; color:#b45309;' : 'background:#fee2e2; color:#b91c1c;'}">
                        ${isCooking ? 'Sedang Dimasak' : 'Menunggu'}
                    </span>
                </div>
            </div>

            <div class="kds-card-items">
                ${itemsHtml}
            </div>

            ${orderNoteHtml}

            <div class="kds-card-action">
                ${actionBtn}
            </div>
        </div>
    `;
}

/* ── Render Active Orders into 2 Sections ── */
function renderActiveOrders(tickets) {
    const pendingTickets = tickets.filter(t => t.ticket_status === 'pending');
    const cookingTickets = tickets.filter(t => t.ticket_status === 'cooking');

    // Update section badges & tab counts
    document.getElementById('badgePendingCount').textContent = `${pendingTickets.length} pesanan`;
    document.getElementById('badgeCookingCount').textContent = `${cookingTickets.length} diproses`;
    document.getElementById('countActiveTab').textContent = tickets.length;

    // Render Section A (Pending)
    const pendingContainer = document.getElementById('gridPending');
    if (!pendingTickets.length) {
        pendingContainer.innerHTML = `
            <div class="kds-empty-box">
                <div class="kds-empty-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="kds-empty-title">Tidak ada antrean menunggu</div>
                <div class="kds-empty-sub">Pesanan baru dari pelanggan akan muncul di sini.</div>
            </div>`;
    } else {
        pendingContainer.innerHTML = pendingTickets.map(t => renderTicketCard(t, false)).join('');
    }

    // Render Section B (Cooking)
    const cookingContainer = document.getElementById('gridCooking');
    if (!cookingTickets.length) {
        cookingContainer.innerHTML = `
            <div class="kds-empty-box">
                <div class="kds-empty-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                </div>
                <div class="kds-empty-title">Belum ada yang dimasak</div>
                <div class="kds-empty-sub">Klik "Mulai Masak" pada antrean masuk untuk memulai.</div>
            </div>`;
    } else {
        cookingContainer.innerHTML = cookingTickets.map(t => renderTicketCard(t, true)).join('');
    }
}

/* ── Render History List ── */
function renderHistory(history) {
    const list = document.getElementById('gridHistory');
    document.getElementById('countHistoryTab').textContent = history.length;

    if (!history.length) {
        list.innerHTML = `
            <div class="kds-empty-box" style="grid-column: 1 / -1;">
                <div class="kds-empty-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="kds-empty-title">Belum ada riwayat hari ini</div>
                <div class="kds-empty-sub">Pesanan yang selesai dimasak akan tercatat di sini.</div>
            </div>`;
        return;
    }

    list.innerHTML = history.map(t => {
        const tableInfo = t.service_type === 'dine_in' && t.table_number
            ? `· Meja ${escapeHtml(t.table_number)}`
            : '· Takeaway';

        const itemsHtml = t.items.map(i => `
            <div class="kds-history-row">
                <span><strong>${escapeHtml(i.product_name)}</strong>${i.item_notes ? ` <span style="font-size:0.75rem; color:#b45309;">(${escapeHtml(i.item_notes)})</span>` : ''}</span>
                <span style="font-weight:700;">×${i.quantity}</span>
            </div>`).join('');

        return `
            <div class="kds-history-card">
                <div class="kds-history-head">
                    <div>
                        <div class="kds-history-inv">${escapeHtml(t.invoice_number)} ${tableInfo}</div>
                        <div class="kds-history-cust">${escapeHtml(t.customer_name)}</div>
                    </div>
                    <div class="kds-history-time">${timeStr(t.completed_at)}</div>
                </div>
                <div class="kds-history-items-list">
                    ${itemsHtml}
                </div>
            </div>
        `;
    }).join('');
}

/* ── API Calls ── */
async function loadOrders() {
    try {
        const res  = await fetch(ordersUrl, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (!data.success) return;

        const { tickets, pending, cooking, done_today, last_item_id } = data.data;

        document.getElementById('kpiPending').textContent = pending;
        document.getElementById('kpiCooking').textContent = cooking;
        document.getElementById('kpiDone').textContent    = done_today;

        const newItems = last_item_id > lastItemId;
        if (newItems && lastItemId > 0) {
            playBeepSound();
            document.title = '🔔 Pesanan Baru Masuk! — Dapur';
            setTimeout(() => document.title = 'Dapur — Kitchen Display · {{ config("app.name") }}', 3500);
        }
        lastItemId = last_item_id;

        renderActiveOrders(tickets);

        if (currentTab === 'history' && historyLoaded) {
            loadHistory();
        }
    } catch (e) {
        console.error('KDS poll error:', e);
    }
}

async function loadHistory() {
    try {
        const res  = await fetch(historyUrl, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (data.success) {
            renderHistory(data.data.history);
            historyLoaded = true;
        }
    } catch (e) {
        console.error('History load error:', e);
    }
}

async function startCookingSale(saleId) {
    try {
        const res = await fetch(startSaleUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sale_id: saleId }),
        });
        await res.json();
        await loadOrders();
    } catch (e) {
        console.error('Start cooking error:', e);
    }
}

async function completeSale(saleId) {
    try {
        const res = await fetch(completeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sale_id: saleId }),
        });
        await res.json();
        historyLoaded = false;
        await loadOrders();
        if (currentTab === 'history') {
            loadHistory();
        }
    } catch (e) {
        console.error('Complete sale error:', e);
    }
}

/* ── Audio Button Toggle Handler ── */
function setupSoundToggle() {
    const btn = document.getElementById('btnToggleSound');
    const icoOn = document.getElementById('soundIconOn');
    const icoOff = document.getElementById('soundIconOff');

    if (!btn) return;

    btn.addEventListener('click', () => {
        soundEnabled = !soundEnabled;
        icoOn.style.display  = soundEnabled ? '' : 'none';
        icoOff.style.display = soundEnabled ? 'none' : '';
        btn.classList.toggle('btn-primary', soundEnabled);
        btn.classList.toggle('btn-outline', !soundEnabled);
        btn.title = soundEnabled ? 'Suara Notifikasi Aktif' : 'Suara Notifikasi Dimatikan';
        if (soundEnabled) playBeepSound();
    });
}

/* ── Boot ── */
document.addEventListener('DOMContentLoaded', () => {
    setupSoundToggle();
    loadOrders();
    pollingTimer = setInterval(() => {
        if (document.visibilityState === 'visible') loadOrders();
    }, 5000);
});
</script>
@endpush
