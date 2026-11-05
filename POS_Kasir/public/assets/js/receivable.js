/**
 * Owner-side receivables: unpaid bills grouped by customer.
 *
 * The point of this screen is that one person with three unpaid orders is one
 * phone call, not three. So the API returns groups keyed by phone number and
 * this file renders the call / WhatsApp action at the group level, with the
 * individual bills underneath.
 */

let unpaidTimer = null;

function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(number);
}

function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[ch]));
}

function renderUnpaidSummary(summary) {
    const set = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.innerText = value;
    };

    set('unpaidSaleCount', summary.sale_count || 0);
    set('unpaidCustomerCount', summary.customer_count || 0);
    set('unpaidTotal', formatRupiah(summary.total_amount || 0));
    set('unpaidUncallable', summary.undialable_count || 0);

    const warning = document.getElementById('unpaidWarning');
    if (warning) {
        warning.style.display = (summary.undialable_count || 0) > 0 ? 'block' : 'none';
    }
}

function renderSaleRows(sales) {
    if (!sales.length) {
        return '';
    }

    return sales.map((sale) => {
        const items = sale.items.map((i) => `
            <li style="display:flex; justify-content:space-between; gap:8px; font-size:0.82rem; color:#475569;">
                <span>${escapeHtml(i.product_name)} &times; ${i.quantity}</span>
                <span style="font-weight:700; color:#0f172a;">${formatRupiah(i.subtotal)}</span>
            </li>`).join('');

        return `
            <div class="unpaid-sale">
                <div class="unpaid-sale-head">
                    <div>
                        <strong style="font-size:0.88rem; color:#0f172a;">${escapeHtml(sale.invoice_number)}</strong>
                        <span class="brand-badge ${sale.is_overdue ? 'badge-unpaid-late' : 'badge-unpaid'}"
                              style="margin-left:6px; font-size:0.65rem; padding:1px 7px;">
                            ${escapeHtml(sale.age_label)}
                        </span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <strong style="color:#dc2626;">${formatRupiah(sale.total_amount)}</strong>
                        <button type="button" class="btn btn-success btn-sm" style="display:inline-flex; align-items:center; gap:5px;"
                                onclick="markUnpaidPaid(${sale.sale_id}, '${escapeHtml(sale.invoice_number)}')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Lunasi
                        </button>
                    </div>
                </div>
                <ul style="list-style:none; padding:0; margin:8px 0 0;">${items}</ul>
            </div>`;
    }).join('');
}

function renderUnpaidGroups(groups) {
    const container = document.getElementById('unpaidList');
    if (!container) return;

    if (!groups.length) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1;">
                <div style="width:48px; height:48px; border-radius:50%; background:#d1fae5; display:flex; align-items:center; justify-content:center; margin:0 auto 12px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h3 style="color:#059669; font-size:1.1rem; font-weight:700;">Tidak ada tagihan tertunggak</h3>
                <p style="color:#94a3b8; font-size:0.85rem; margin-top:2px;">Semua pesanan sudah dibayar oleh pelanggan.</p>
            </div>`;
        return;
    }

    container.innerHTML = groups.map((group) => {
        const canCall = group.phone_usable;
        const name = escapeHtml(group.customer_name);
        const phone = canCall
            ? escapeHtml(group.customer_phone)
            : '<span style="color:#dc2626;">nomor tidak valid</span>';

        const actions = canCall ? `
            <a href="${escapeHtml(group.tel_link)}" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:5px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.44 2 2 0 0 1 3.59 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.83a16 16 0 0 0 6.26 6.26l1.13-.9a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Telepon
            </a>
            <a href="${escapeHtml(group.whatsapp_link)}" target="_blank" rel="noopener" class="btn btn-success btn-sm" style="display:inline-flex; align-items:center; gap:5px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.998 2C6.477 2 2 6.477 2 12c0 1.76.459 3.413 1.261 4.845L2 22l5.272-1.388A9.956 9.956 0 0 0 11.998 22c5.523 0 10-4.477 10-10S17.521 2 11.998 2z"/></svg>
                WhatsApp
            </a>
        ` : `
            <span class="badge-unpaid-late" style="font-size:0.7rem; padding:3px 8px;">Tidak bisa ditagih</span>
        `;

        const count = group.sale_count > 1
            ? `${group.sale_count} pesanan, tertua ${escapeHtml(group.oldest_label)}`
            : escapeHtml((group.sales[0] || {}).age_label || '');

        return `
            <div class="unpaid-group">
                <div class="unpaid-group-head">
                    <div style="min-width:0;">
                        <div style="font-weight:800; font-size:1rem; color:#0f172a; overflow-wrap:anywhere;">${name}</div>
                        <div style="font-size:0.82rem; color:#64748b; margin-top:2px; overflow-wrap:anywhere; display:flex; align-items:center; gap:5px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            ${phone}</div>
                        <div style="font-size:0.75rem; color:#94a3b8; margin-top:2px;">${count}</div>
                    </div>
                    <div style="text-align:right; flex-shrink:0;">
                        <div style="font-weight:800; font-size:1.05rem; color:#dc2626;">${formatRupiah(group.total_amount)}</div>
                        <div style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; justify-content:flex-end;">${actions}</div>
                    </div>
                </div>
                <div class="unpaid-sales">${renderSaleRows(group.sales)}</div>
            </div>`;
    }).join('');
}

async function loadUnpaid() {
    const container = document.getElementById('unpaidList');

    try {
        const res = await fetch('/api/owner/unpaid', { headers: { Accept: 'application/json' } });
        const result = await res.json();

        if (result.success) {
            renderUnpaidSummary(result.data.summary);
            renderUnpaidGroups(result.data.groups);
        } else if (container) {
            container.innerHTML = `
                <div style="text-align:center; padding:40px 20px; background:#fff; border-radius:16px; border:2px solid #fecaca;">
                    <h3 style="color:#dc2626; font-size:1rem; font-weight:700;">Gagal memuat tagihan</h3>
                    <p style="color:#94a3b8; font-size:0.82rem; margin-top:4px;">${escapeHtml(result.message || 'Coba muat ulang halaman.')}</p>
                </div>`;
        }
    } catch (e) {
        if (container) {
            container.innerHTML = `
                <div style="text-align:center; padding:40px 20px; background:#fff; border-radius:16px; border:2px solid #fecaca;">
                    <h3 style="color:#dc2626; font-size:1rem; font-weight:700;">Koneksi bermasalah</h3>
                    <p style="color:#94a3b8; font-size:0.82rem; margin-top:4px;">${escapeHtml(e.message)}</p>
                </div>`;
        }
    }
}

async function markUnpaidPaid(saleId, invoiceNumber) {
    if (!confirm(`Tandai pesanan ${invoiceNumber} sudah dibayar?`)) return;

    try {
        const res = await fetch(`/api/owner/unpaid/${saleId}/paid`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sale_id: saleId }),
        });
        const result = await res.json();

        if (result.success) {
            showUnpaidToast(result.message);
            loadUnpaid();
        } else {
            alert('Gagal: ' + (result.message || 'tidak diketahui'));
        }
    } catch (e) {
        alert('Gagal: ' + e.message);
    }
}

function showUnpaidToast(msg) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg> <span>${escapeHtml(msg)}</span>`;
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
    loadUnpaid();

    const btn = document.getElementById('btnRefreshUnpaid');
    if (btn) btn.addEventListener('click', loadUnpaid);

    // Refresh quietly every 30s so a bill settled at the counter disappears
    // without the owner having to reload.
    unpaidTimer = setInterval(() => {
        if (document.visibilityState === 'visible') loadUnpaid();
    }, 30000);
});
