@extends('layouts.app')

@section('title', 'Dashboard ' . $scopeLabel . ' - ' . config('app.name'))

@section('content')
    @push('navbar-actions')
        <button id="btnToggleSound" class="btn btn-primary btn-sm">🔔 Audio</button>
    @endpush

    @include('owner.partials.navbar', [
        'current' => 'dashboard',
        'title' => 'Dashboard ' . $scopeLabel,
        'icon' => $user->isAdmin() ? '👨‍💼' : ($user->ownerType === 'makanan' ? '🍲' : '🍹'),
    ])

    <main class="container">
        <!-- Welcome Banner -->
        <div class="owner-welcome-card">
            <div>
                <h2 style="font-size:1.2rem; font-weight:800; color:#0f172a;">Halo, {{ $user->name }} 👋</h2>
                <p style="font-size:0.85rem; color:#64748b; margin-top:2px;">
                    Antrean pesanan dapur khusus <strong>{{ strtoupper($scopeBadge) }}</strong> (Auto-update real-time)
                </p>
            </div>
            <div class="polling-badge">
                <span class="pulse-dot"></span>
                <span>Live Refresh (5s)</span>
            </div>
        </div>

        <!-- KPI Summary Cards (2x2 on mobile, 4-col on desktop) -->
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

        <!-- Install + Push Notification Card -->
        <div class="card p-3 mb-4" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                <span style="font-size:1.4rem;">📲</span>
                <div style="min-width:0;">
                    <div style="font-weight:800; font-size:0.95rem; color:#0f172a;">Notifikasi &amp; Aplikasi</div>
                    <div id="pushStatusText" style="font-size:0.78rem; color:#64748b; margin-top:2px;">Memuat status…</div>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" id="btnInstallApp" class="btn btn-outline btn-sm" style="display:none;" onclick="installApp()">
                    ⬇️ Pasang Aplikasi
                </button>
                <button type="button" id="btnTestPush" class="btn btn-outline btn-sm" style="display:none;" onclick="sendTestPush()">
                    📨 Tes Notifikasi
                </button>
                <button type="button" id="btnTogglePush" class="btn btn-primary btn-sm" onclick="togglePush()">
                    🔔 Aktifkan Notifikasi
                </button>
            </div>
        </div>

        <!-- Section Header with Mobile Filter Tabs -->
        <div class="orders-section-header">
            <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a;">Antrean Pesanan Real-time</h3>

            <div class="order-filter-chips">
                <button type="button" class="filter-chip active" data-status="all" onclick="filterOrderStatus('all')">Semua</button>
                <button type="button" class="filter-chip" data-status="pending" onclick="filterOrderStatus('pending')">⏳ Menunggu</button>
                <button type="button" class="filter-chip" data-status="cooking" onclick="filterOrderStatus('cooking')">🔥 Dimasak</button>
            </div>
        </div>

        <!-- Order Cards Stream -->
        <div id="ordersContainer" class="orders-grid"></div>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset_versioned('assets/js/owner.js') }}"></script>
    <script>
        const pushConfigUrl = @js(route('owner.push.show'));
        let pushState = { enabled: false, public_key: null, subscribed: false };

        document.addEventListener('DOMContentLoaded', () => {
            initOwnerPolling(@js($ownerTypes), @js($startWorkingLabel));
            initPwaControls();
        });

        async function initPwaControls() {
            const statusEl = document.getElementById('pushStatusText');
            const toggleBtn = document.getElementById('btnTogglePush');
            const testBtn = document.getElementById('btnTestPush');
            const installBtn = document.getElementById('btnInstallApp');

            const unsupported = window.PWA.unsupportedReason();
            const standalone = window.PWA.isStandalone();

            if (installBtn) {
                if (standalone) {
                    installBtn.style.display = 'none';
                } else {
                    window.PWA.registerInstallPrompt(installBtn);
                }
            }

            if (unsupported) {
                statusEl.textContent = unsupported;
                toggleBtn.style.display = 'none';
                testBtn.style.display = 'none';

                return;
            }

            const res = await fetch(pushConfigUrl);
            const data = await res.json();

            if (data.success) {
                pushState = data.data;
            }

            renderPushStatus();
        }

        function renderPushStatus() {
            const statusEl = document.getElementById('pushStatusText');
            const toggleBtn = document.getElementById('btnTogglePush');
            const testBtn = document.getElementById('btnTestPush');

            if (!pushState.enabled) {
                statusEl.textContent = 'Notifikasi belum dikonfigurasi di server. Jalankan: php artisan push:vapid-keys';
                toggleBtn.style.display = 'none';
                testBtn.style.display = 'none';

                return;
            }

            if (pushState.subscribed) {
                const devices = (pushState.devices || []).length;
                statusEl.textContent = `Aktif — ${devices} perangkat terdaftar. Anda akan diberi tahu setiap ada pesanan baru.`;
                toggleBtn.textContent = '🔕 Matikan Notifikasi';
                toggleBtn.className = 'btn btn-outline btn-sm';
                testBtn.style.display = '';

                return;
            }

            statusEl.textContent = 'Belum aktif di perangkat ini. Aktifkan untuk menerima pemberitahuan pesanan baru.';
            toggleBtn.textContent = '🔔 Aktifkan Notifikasi';
            toggleBtn.className = 'btn btn-primary btn-sm';
            testBtn.style.display = 'none';
        }

        async function togglePush() {
            const statusEl = document.getElementById('pushStatusText');
            const result = pushState.subscribed
                ? await window.PWA.unsubscribePush()
                : await window.PWA.subscribePush(pushState.public_key);

            if (!result.success) {
                alert(result.message);
                return;
            }

            pushState.subscribed = !pushState.subscribed;

            const res = await fetch(pushConfigUrl);
            const data = await res.json();
            if (data.success) {
                pushState = data.data;
            }

            renderPushStatus();
            statusEl.style.color = '#059669';
        }

        async function installApp() {
            await window.PWA.promptInstall();
        }

        async function sendTestPush() {
            const data = await window.PWA.sendTestPush();
            alert(data.message || (data.success ? 'Notifikasi tes dikirim' : 'Gagal mengirim notifikasi tes'));
        }
    </script>
@endpush
