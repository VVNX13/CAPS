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
                        <button type="button" class="btn btn-success btn-sm"
                                onclick="markUnpaidPaid(${sale.sale_id}, '${escapeHtml(sale.invoice_number)}')">
                            ✅ Lunasi
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
                <div style="font-size:2.5rem; margin-bottom:8px;">✅</div>
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
            <a href="${escapeHtml(group.tel_link)}" class="btn btn-primary btn-sm">📞 Telepon</a>
            <a href="${escapeHtml(group.whatsapp_link)}" target="_blank" rel="noopener" class="btn btn-success btn-sm">💬 WhatsApp</a>
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
                        <div style="font-size:0.82rem; color:#64748b; margin-top:2px; overflow-wrap:anywhere;">📱 ${phone}</div>
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
    toast.innerHTML = `<span>💸</span> <span>${escapeHtml(msg)}</span>`;
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
