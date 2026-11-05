@extends('layouts.app')

@section('title', 'Stok Produk ' . $scopeLabel . ' - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current' => 'stock',
        'title'   => 'Stok ' . $scopeLabel,
        'icon'    => '',
    ])

    <style>
        /* ── Stock Page Styles ── */
        .stk-page { padding: 24px 0 40px; }

        .stk-page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .stk-page-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main, #0f172a);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .stk-page-sub {
            font-size: 0.8rem;
            color: var(--text-muted, #64748b);
            margin-top: 3px;
            font-weight: 500;
        }

        /* Toolbar */
        .stk-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .stk-search-wrap {
            position: relative;
            flex: 1;
            min-width: 180px;
            max-width: 260px;
        }
        .stk-search-wrap svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }
        .stk-search-input {
            width: 100%;
            padding: 8px 12px 8px 32px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.82rem;
            background: #fff;
            color: var(--text-main, #0f172a);
            outline: none;
            transition: border-color .15s;
        }
        .stk-search-input:focus { border-color: var(--primary, #25382d); }

        .stk-refresh-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted, #64748b);
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
        }
        .stk-refresh-btn:hover { border-color: var(--primary, #25382d); color: var(--primary, #25382d); }

        /* KPI strip */
        .stk-kpi-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) { .stk-kpi-strip { grid-template-columns: repeat(2, 1fr); } }

        .stk-kpi {
            background: #fff;
            border: 1px solid #e8e4dc;
            border-radius: 12px;
            padding: 16px 18px;
        }
        .stk-kpi-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--text-muted, #64748b);
            margin-bottom: 6px;
        }
        .stk-kpi-value {
            font-size: 1.55rem;
            font-weight: 900;
            line-height: 1.1;
        }
        .stk-kpi-note { font-size: 0.72rem; color: var(--text-muted, #64748b); margin-top: 4px; }

        /* Filter chips */
        .stk-chips {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .stk-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 13px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            color: var(--text-muted, #64748b);
            cursor: pointer;
            transition: all .15s;
        }
        .stk-chip:hover { border-color: var(--primary, #25382d); color: var(--primary, #25382d); }
        .stk-chip.active {
            background: var(--primary, #25382d);
            border-color: var(--primary, #25382d);
            color: #fff;
        }
        .stk-chip-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: currentColor;
            opacity: .7;
        }

        /* Table */
        .stk-section {
            background: #fff;
            border: 1px solid #e8e4dc;
            border-radius: 12px;
            overflow: hidden;
        }
        .stk-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
        .stk-table th {
            padding: 10px 14px;
            text-align: left;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text-muted, #64748b);
            border-bottom: 1.5px solid #e8e4dc;
            background: #fafaf8;
        }
        .stk-table td {
            padding: 11px 14px;
            border-bottom: 1px solid #f0ede6;
            vertical-align: middle;
            color: var(--text-main, #0f172a);
        }
        .stk-table tbody tr:last-child td { border-bottom: none; }
        .stk-table tbody tr:hover { background: #fafaf8; }

        /* Stock level pill */
        .stk-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .stk-pill-dot { width: 6px; height: 6px; border-radius: 50%; }
        .stk-pill.ok   { background: #f0fdf4; color: #15803d; }
        .stk-pill.low  { background: #fffbeb; color: #b45309; }
        .stk-pill.out  { background: #fff5f5; color: #b91c1c; }
        .stk-pill.ok  .stk-pill-dot  { background: #15803d; }
        .stk-pill.low .stk-pill-dot  { background: #b45309; }
        .stk-pill.out .stk-pill-dot  { background: #b91c1c; }

        /* Active badge */
        .stk-active-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
        }
    </style>

    <main class="container stk-page">

        {{-- Page Header --}}
        <div class="stk-page-header">
            <div>
                <div class="stk-page-title">Stok Produk</div>
                <div class="stk-page-sub">
                    {{ $scopeLabel }} &mdash;
                    @if ($isAdmin)
                        Kasir dapat menambah stok produk.
                    @else
                        Tampilan hanya-lihat. Hubungi admin untuk perubahan stok.
                    @endif
                </div>
            </div>
            <div class="stk-toolbar">
                <div class="stk-search-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" id="stockSearch" class="stk-search-input" placeholder="Cari produk…" oninput="filterStock(this.value)">
                </div>
                <button class="stk-refresh-btn" onclick="loadStock()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    Perbarui
                </button>
            </div>
        </div>

        {{-- KPI Strip --}}
        <div class="stk-kpi-strip">
            <div class="stk-kpi">
                <div class="stk-kpi-label">Total Produk</div>
                <div id="stockTotalProducts" class="stk-kpi-value" style="color:var(--primary,#25382d);">0</div>
                <div class="stk-kpi-note">item terdaftar</div>
            </div>
            <div class="stk-kpi">
                <div class="stk-kpi-label">Stok Tersedia</div>
                <div id="stockTotalInStock" class="stk-kpi-value" style="color:#15803d;">0</div>
                <div class="stk-kpi-note">stok &gt; 5</div>
            </div>
            <div class="stk-kpi">
                <div class="stk-kpi-label">Stok Menipis</div>
                <div id="stockTotalLow" class="stk-kpi-value" style="color:#b45309;">0</div>
                <div class="stk-kpi-note">≤ 5 porsi tersisa</div>
            </div>
            <div class="stk-kpi">
                <div class="stk-kpi-label">Stok Habis</div>
                <div id="stockTotalEmpty" class="stk-kpi-value" style="color:#b91c1c;">0</div>
                <div class="stk-kpi-note">perlu restock</div>
            </div>
        </div>

        {{-- Filter Chips --}}
        <div class="stk-chips">
            <button class="stk-chip active" data-filter="all" onclick="setFilter('all', this)">Semua</button>
            <button class="stk-chip" data-filter="available" onclick="setFilter('available', this)">
                <span class="stk-chip-dot" style="background:#15803d;"></span> Tersedia
            </button>
            <button class="stk-chip" data-filter="low" onclick="setFilter('low', this)">
                <span class="stk-chip-dot" style="background:#b45309;"></span> Menipis (≤5)
            </button>
            <button class="stk-chip" data-filter="empty" onclick="setFilter('empty', this)">
                <span class="stk-chip-dot" style="background:#b91c1c;"></span> Habis
            </button>
        </div>

        {{-- Products Table --}}
        <div class="stk-section">
            <table class="stk-table">
                <thead>
                    <tr>
                        <th style="width:44px;">No</th>
                        <th>Nama Produk</th>
                        <th style="text-align:right;">Harga</th>
                        <th style="text-align:center; width:90px;">Stok</th>
                        <th style="text-align:center;">Kondisi</th>
                        <th style="text-align:center;">Status</th>
                        @if ($isAdmin)
                            <th style="text-align:center; width:100px;">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="stockBody">
                    <tr><td colspan="{{ $isAdmin ? 7 : 6 }}" style="text-align:center;padding:36px;color:#94a3b8;font-size:0.82rem;">Memuat data stok…</td></tr>
                </tbody>
            </table>
        </div>

    </main>

    {{-- Modal Tambah Stok (Admin only) --}}
    @if ($isAdmin)
    <div id="addStockModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:28px 24px; width:100%; max-width:360px; margin:16px; box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div style="font-size:1rem; font-weight:800; color:#0f172a;">Tambah Stok</div>
                <button onclick="closeAddStockModal()" style="background:none;border:none;cursor:pointer;font-size:1.3rem;color:#94a3b8;">&times;</button>
            </div>
            <div id="addStockProductName" style="font-weight:700; color:#0f172a; margin-bottom:4px;"></div>
            <div id="addStockCurrent" style="font-size:0.8rem; color:#64748b; margin-bottom:16px;"></div>
            <label style="display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:6px;">Jumlah Ditambahkan</label>
            <input type="number" id="addStockQty" min="1" max="9999" value="10"
                   style="width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:1rem; font-weight:700; color:#0f172a; outline:none; margin-bottom:16px;"
                   onfocus="this.style.borderColor='#25382d'" onblur="this.style.borderColor='#e2e8f0'">
            <div style="display:flex; gap:8px;">
                <button onclick="closeAddStockModal()" style="flex:1; padding:10px; border:1.5px solid #e2e8f0; border-radius:8px; background:#fff; font-weight:600; cursor:pointer;">Batal</button>
                <button onclick="submitAddStock()" id="btnAddStockSubmit" style="flex:2; padding:10px; background:#25382d; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;">Simpan</button>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
<script>
    const stockListUrl  = @js(route('owner.products.stock'));
    const addStockBase  = @js(url('/api/owner/products'));
    const isAdmin       = @js($isAdmin);
    const colCount      = isAdmin ? 7 : 6;
    let allProducts     = [];
    let currentFilter   = 'all';
    let currentSearch   = '';
    let addStockTarget  = null;

    document.addEventListener('DOMContentLoaded', () => loadStock());

    async function loadStock() {
        try {
            const res  = await fetch(stockListUrl);
            const data = await res.json();
            if (!data.success) return;
            allProducts = data.data.products;
            updateSummary();
            renderTable();
        } catch (e) { console.error(e); }
    }

    function updateSummary() {
        const inStock = allProducts.filter(p => p.stock > 5);
        const low     = allProducts.filter(p => p.stock > 0 && p.stock <= 5);
        const empty   = allProducts.filter(p => p.stock <= 0);
        document.getElementById('stockTotalProducts').textContent = allProducts.length;
        document.getElementById('stockTotalInStock').textContent  = inStock.length;
        document.getElementById('stockTotalEmpty').textContent    = empty.length;
        document.getElementById('stockTotalLow').textContent      = low.length;
    }

    function setFilter(filter, btn) {
        currentFilter = filter;
        document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderTable();
    }

    function filterStock(val) {
        currentSearch = val.toLowerCase();
        renderTable();
    }

    function renderTable() {
        const fmt = n => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n);

        let products = allProducts.filter(p => {
            if (currentSearch && !p.name.toLowerCase().includes(currentSearch)) return false;
            if (currentFilter === 'available') return p.stock > 5;
            if (currentFilter === 'low')       return p.stock > 0 && p.stock <= 5;
            if (currentFilter === 'empty')     return p.stock <= 0;
            return true;
        });

        const tbody = document.getElementById('stockBody');
        if (!products.length) {
            tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center;padding:36px;color:#94a3b8;font-size:0.82rem;">Tidak ada produk ditemukan.</td></tr>`;
            return;
        }

        let html = '';
        products.forEach((p, i) => {
            let pillClass, pillLabel;
            if (p.stock <= 0) {
                pillClass = 'out'; pillLabel = 'Habis';
            } else if (p.stock <= 5) {
                pillClass = 'low'; pillLabel = 'Menipis';
            } else {
                pillClass = 'ok'; pillLabel = 'Tersedia';
            }

            const activeBadge = p.is_active
                ? `<span class="stk-active-badge" style="background:#f0fdf4;color:#166534;">Aktif</span>`
                : `<span class="stk-active-badge" style="background:#fff5f5;color:#991b1b;">Nonaktif</span>`;

            const rowBg = p.stock <= 0 ? 'background:#fffafa;' : p.stock <= 5 ? 'background:#fffdf5;' : '';

            const actionCell = isAdmin ? `
                <td style="text-align:center;">
                    <button onclick="openAddStockModal(${p.id})" style="
                        padding:4px 12px; border:1.5px solid #25382d; border-radius:7px;
                        background:#fff; color:#25382d; font-size:0.76rem; font-weight:700;
                        cursor:pointer; transition:all .15s;
                    " onmouseover="this.style.background='#25382d';this.style.color='#fff'"
                       onmouseout="this.style.background='#fff';this.style.color='#25382d'">
                        + Stok
                    </button>
                </td>` : '';

            html += `
                <tr style="${rowBg}">
                    <td style="text-align:center;color:#94a3b8;font-size:0.78rem;">${i + 1}</td>
                    <td style="font-weight:700;">${p.name}</td>
                    <td style="text-align:right;font-weight:600;">${fmt(p.price)}</td>
                    <td style="text-align:center;">
                        <span id="stockVal_${p.id}" style="font-size:1.25rem;font-weight:900;color:${p.stock <= 0 ? '#b91c1c' : p.stock <= 5 ? '#b45309' : '#15803d'};">${p.stock}</span>
                        <div style="font-size:0.65rem;color:#94a3b8;margin-top:1px;">porsi</div>
                    </td>
                    <td style="text-align:center;">
                        <span class="stk-pill ${pillClass}">
                            <span class="stk-pill-dot"></span>${pillLabel}
                        </span>
                    </td>
                    <td style="text-align:center;">${activeBadge}</td>
                    ${actionCell}
                </tr>`;
        });
        tbody.innerHTML = html;
    }

    // ── Add Stock Modal ──
    function openAddStockModal(productId) {
        const p = allProducts.find(x => x.id === productId);
        if (!p) return;
        addStockTarget = p;
        document.getElementById('addStockProductName').textContent = p.name;
        document.getElementById('addStockCurrent').textContent = 'Stok saat ini: ' + p.stock + ' porsi';
        document.getElementById('addStockQty').value = 10;
        const modal = document.getElementById('addStockModal');
        modal.style.display = 'flex';
        setTimeout(() => document.getElementById('addStockQty').focus(), 100);
    }

    function closeAddStockModal() {
        document.getElementById('addStockModal').style.display = 'none';
        addStockTarget = null;
    }

    async function submitAddStock() {
        if (!addStockTarget) return;
        const qty = parseInt(document.getElementById('addStockQty').value);
        if (!qty || qty < 1) { alert('Masukkan jumlah yang valid (minimal 1)'); return; }

        const btn = document.getElementById('btnAddStockSubmit');
        btn.disabled = true;
        btn.textContent = 'Menyimpan…';

        try {
            const res = await fetch(`${addStockBase}/${addStockTarget.id}/addstock`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ qty }),
            });
            const data = await res.json();
            if (data.success) {
                // Update local data
                const p = allProducts.find(x => x.id === addStockTarget.id);
                if (p) p.stock = data.data.stock;
                closeAddStockModal();
                updateSummary();
                renderTable();
            } else {
                alert('Gagal: ' + (data.message || 'tidak diketahui'));
            }
        } catch (e) {
            alert('Gagal: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan';
        }
    }

    // Close modal on backdrop click
    document.addEventListener('click', e => {
        const modal = document.getElementById('addStockModal');
        if (modal && e.target === modal) closeAddStockModal();
    });
</script>
@endpush
