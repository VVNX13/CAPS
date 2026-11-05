@extends('layouts.app')

@section('title', 'Kupon Promo - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current' => 'promos',
        'title'   => 'Kupon Promo',
    ])

    <main class="container">
        <!-- Page Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; padding-top:4px;">
            <div>
                <div style="font-size:1.3rem; font-weight:800; color:var(--text-main,#0f172a); letter-spacing:-0.02em;">Kupon Promo</div>
                <div style="font-size:0.8rem; color:var(--text-muted,#64748b); margin-top:3px; font-weight:500;">
                    Kupon dapat dimasukkan pelanggan saat checkout, atau dipasang kasir sebelum tagihan dibayar.
                </div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openPromoModal()" style="display:flex; align-items:center; gap:7px; padding:8px 16px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Kupon Baru
            </button>
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
                    <div style="text-align:center; padding:48px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; grid-column:1/-1;">
                        <div style="width:48px;height:48px;border-radius:12px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        </div>
                        <h3 style="color:#64748b; font-size:1rem; font-weight:700; margin-bottom:4px;">Belum ada kupon promo</h3>
                        <p style="color:#94a3b8; font-size:0.82rem;">Klik "Kupon Baru" untuk membuat kupon pertama.</p>
                    </div>`;
                return;
            }

            box.innerHTML = promos.map(p => {
                const rules = [
                    p.min_order > 0 ? `Min. belanja ${formatRupiah(p.min_order)}` : 'Tanpa minimal belanja',
                    p.max_discount ? `maks. ${formatRupiah(p.max_discount)}` : '',
                ].filter(Boolean).join(' · ');
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
                        <div class="promo-meta" style="display:flex;align-items:center;gap:5px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            ${escapeHtml(period)} &bull; ${escapeHtml(usage)}
                        </div>
                        ${p.status_note ? `<div class="promo-meta" style="color:#b45309;">${escapeHtml(p.status_note)}</div>` : ''}
                        <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openPromoModal(${p.id})" style="display:flex;align-items:center;gap:5px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Ubah
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="togglePromo(${p.id})">${p.is_active ? 'Nonaktifkan' : 'Aktifkan'}</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="deletePromo(${p.id})" style="display:flex;align-items:center;gap:5px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                Hapus
                            </button>
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
