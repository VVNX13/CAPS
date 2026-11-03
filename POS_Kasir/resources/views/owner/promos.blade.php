@extends('layouts.app')

@section('title', 'Kupon Promo - ' . config('app.name'))

@section('content')
    @push('navbar-actions')
        <button class="btn btn-primary btn-sm" onclick="openPromoModal()">+ Kupon Baru</button>
    @endpush

    @include('owner.partials.navbar', [
        'current' => 'promos',
        'title' => 'Kupon Promo',
        'icon' => '🏷️',
    ])

    <main class="container">
        <div class="owner-welcome-card">
            <div>
                <h2 style="font-size:1.2rem; font-weight:800; color:#0f172a;">Kupon Promo 🏷️</h2>
                <p style="font-size:0.85rem; color:#64748b; margin-top:2px;">
                    Kupon bisa dimasukkan pelanggan saat checkout, atau dipasang kasir di dashboard sebelum tagihan dibayar.
                </p>
            </div>
        </div>

        <div id="promoList" class="promo-grid"></div>
    </main>

    <!-- Modal Tambah/Ubah Kupon -->
    <div class="modal-overlay" id="promoModal" onclick="if (event.target === this) closePromoModal()">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="promoModalTitle">
            <div class="modal-sheet-handle"></div>
            <div class="modal-header">
                <h3 id="promoModalTitle" style="font-weight:800; font-size:1.15rem; color:#0f172a;">Kupon Baru</h3>
                <button type="button" class="btn btn-outline modal-close-btn" onclick="closePromoModal()" aria-label="Tutup">&times;</button>
            </div>
            <form onsubmit="savePromo(event)">
                <div class="modal-body">
                    <input type="hidden" id="promo_id">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label" for="promo_code">Kode Kupon</label>
                            <input type="text" id="promo_code" class="form-control" maxlength="30" required
                                   placeholder="HEMAT10" style="text-transform:uppercase;" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="promo_type">Jenis Potongan</label>
                            <select id="promo_type" class="form-control" onchange="syncPromoType()">
                                <option value="percent">Persen (%)</option>
                                <option value="fixed">Nominal (Rp)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="promo_name">Nama Promo</label>
                        <input type="text" id="promo_name" class="form-control" maxlength="100" required placeholder="Diskon akhir pekan">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label" for="promo_value" id="promoValueLabel">Potongan (%)</label>
                            <input type="number" id="promo_value" class="form-control" min="1" step="any" required>
                        </div>
                        <div class="form-group" id="maxDiscountGroup">
                            <label class="form-label" for="promo_max">Maks. Potongan (Rp)</label>
                            <input type="number" id="promo_max" class="form-control" min="1" placeholder="Tanpa batas">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label" for="promo_min">Min. Belanja (Rp)</label>
                            <input type="number" id="promo_min" class="form-control" min="0" placeholder="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="promo_limit">Kuota Pemakaian</label>
                            <input type="number" id="promo_limit" class="form-control" min="1" placeholder="Tanpa batas">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label" for="promo_start">Mulai</label>
                            <input type="date" id="promo_start" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="promo_end">Berakhir</label>
                            <input type="date" id="promo_end" class="form-control">
                        </div>
                    </div>

                    <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; color:#334155; cursor:pointer;">
                        <input type="checkbox" id="promo_active" style="width:auto;" checked> Kupon aktif
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closePromoModal()">Batal</button>
                    <button type="submit" id="btnPromoSave" class="btn btn-primary" style="flex:1;">Simpan Kupon</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const promoListUrl = @js(route('owner.promos.list'));
        const promoSaveUrl = @js(route('owner.promos.save'));
        const promoBaseUrl = @js(url('/api/owner/promos'));
        let promos = [];

        function escapeHtml(value) {
            return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, ch => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[ch]));
        }

        function formatRupiah(num) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
        }

        function formatDate(ymd) {
            if (!ymd) return '';
            const [y, m, d] = ymd.split('-');
            return `${d}/${m}/${y}`;
        }

        async function loadPromos() {
            const res = await fetch(promoListUrl);
            const data = await res.json();
            if (data.success) {
                promos = data.data.promos;
                renderPromos();
            }
        }

        function renderPromos() {
            const box = document.getElementById('promoList');

            if (promos.length === 0) {
                box.innerHTML = `
                    <div style="text-align:center; padding:40px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; grid-column:1/-1;">
                        <div style="font-size:2.5rem; margin-bottom:8px;">🏷️</div>
                        <h3 style="color:#64748b; font-size:1.1rem; font-weight:700;">Belum ada kupon promo</h3>
                        <p style="color:#94a3b8; font-size:0.85rem; margin-top:2px;">Klik "+ Kupon Baru" untuk membuat kupon pertama.</p>
                    </div>`;
                return;
            }

            box.innerHTML = promos.map(p => {
                const rules = [
                    p.min_order > 0 ? `Min. belanja ${formatRupiah(p.min_order)}` : 'Tanpa minimal belanja',
                    p.max_discount ? `maks. ${formatRupiah(p.max_discount)}` : '',
                ].filter(Boolean).join(' • ');
                const period = p.starts_at || p.ends_at
                    ? `${p.starts_at ? formatDate(p.starts_at) : '…'} – ${p.ends_at ? formatDate(p.ends_at) : '…'}`
                    : 'Berlaku kapan saja';
                const usage = p.usage_limit ? `${p.used_count} / ${p.usage_limit} terpakai` : `${p.used_count} kali terpakai`;
                const badge = p.status_note
                    ? `<span class="brand-badge" style="background:#f1f5f9; color:#64748b;" title="${escapeHtml(p.status_note)}">Tidak berlaku</span>`
                    : '<span class="brand-badge" style="background:#d1fae5; color:#059669;">Aktif</span>';

                return `
                    <div class="promo-card ${p.status_note ? 'is-off' : ''}">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                            <div style="min-width:0;">
                                <div class="promo-code">${escapeHtml(p.code)}</div>
                                <div style="font-size:0.85rem; color:#475569; margin-top:2px;">${escapeHtml(p.name)}</div>
                            </div>
                            ${badge}
                        </div>
                        <div class="promo-value">Potongan ${escapeHtml(p.label)}</div>
                        <div class="promo-meta">${escapeHtml(rules)}</div>
                        <div class="promo-meta">📅 ${escapeHtml(period)} &bull; ${escapeHtml(usage)}</div>
                        ${p.status_note ? `<div class="promo-meta" style="color:#b45309;">${escapeHtml(p.status_note)}</div>` : ''}
                        <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openPromoModal(${p.id})">✏️ Ubah</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="togglePromo(${p.id})">${p.is_active ? '⏸️ Nonaktifkan' : '▶️ Aktifkan'}</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="deletePromo(${p.id})">🗑️ Hapus</button>
                        </div>
                    </div>`;
            }).join('');
        }

        function syncPromoType() {
            const percent = document.getElementById('promo_type').value === 'percent';
            document.getElementById('promoValueLabel').textContent = percent ? 'Potongan (%)' : 'Potongan (Rp)';
            document.getElementById('promo_value').max = percent ? 100 : '';
            document.getElementById('maxDiscountGroup').style.visibility = percent ? '' : 'hidden';
        }

        function openPromoModal(id = null) {
            const p = promos.find(x => x.id === id) || null;
            document.getElementById('promoModalTitle').textContent = p ? `Ubah Kupon ${p.code}` : 'Kupon Baru';
            document.getElementById('promo_id').value = p ? p.id : '';
            document.getElementById('promo_code').value = p ? p.code : '';
            document.getElementById('promo_name').value = p ? p.name : '';
            document.getElementById('promo_type').value = p ? p.type : 'percent';
            document.getElementById('promo_value').value = p ? p.value : '';
            document.getElementById('promo_max').value = p && p.max_discount ? p.max_discount : '';
            document.getElementById('promo_min').value = p && p.min_order ? p.min_order : '';
            document.getElementById('promo_limit').value = p && p.usage_limit ? p.usage_limit : '';
            document.getElementById('promo_start').value = p ? (p.starts_at || '') : '';
            document.getElementById('promo_end').value = p ? (p.ends_at || '') : '';
            document.getElementById('promo_active').checked = p ? p.is_active : true;
            syncPromoType();
            document.getElementById('promoModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePromoModal() {
            document.getElementById('promoModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        async function savePromo(e) {
            e.preventDefault();
            const val = id => document.getElementById(id).value.trim();
            const payload = {
                id: val('promo_id') || null,
                code: val('promo_code').toUpperCase(),
                name: val('promo_name'),
                type: val('promo_type'),
                value: val('promo_value'),
                max_discount: val('promo_max') || null,
                min_order: val('promo_min') || null,
                usage_limit: val('promo_limit') || null,
                starts_at: val('promo_start') || null,
                ends_at: val('promo_end') || null,
                is_active: document.getElementById('promo_active').checked,
            };

            const btn = document.getElementById('btnPromoSave');
            btn.disabled = true;
            try {
                const res = await fetch(promoSaveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!data.success) {
                    const first = data.errors ? Object.values(data.errors).flat()[0] : null;
                    alert(first || data.message);
                    return;
                }
                closePromoModal();
                loadPromos();
            } catch (err) {
                alert('Gagal menyimpan kupon: ' + err.message);
            } finally {
                btn.disabled = false;
            }
        }

        async function togglePromo(id) {
            const res = await fetch(`${promoBaseUrl}/${id}/toggle`, { method: 'POST', headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success) alert(data.message);
            loadPromos();
        }

        async function deletePromo(id) {
            const p = promos.find(x => x.id === id);
            if (!p || !confirm(`Hapus kupon ${p.code}? Transaksi yang sudah memakai kupon ini tidak berubah.`)) return;
            const res = await fetch(`${promoBaseUrl}/${id}`, { method: 'DELETE', headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success) alert(data.message);
            loadPromos();
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closePromoModal(); });
        document.addEventListener('DOMContentLoaded', loadPromos);
    </script>
@endpush
