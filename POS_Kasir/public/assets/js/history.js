/**
 * Customer order history, looked up by the phone number used at checkout.
 *
 * The main practical use is not nostalgia: it lets a customer who realises at
 * the counter that they never actually paid see the bill, and it makes
 * "order again" a two-tap job.
 */

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

function showHistoryMessage(text, kind) {
    const box = document.getElementById('historyMessage');
    if (!box) return;

    const styles = {
        error: { background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' },
        info: { background: '#eff6ff', color: '#1d4ed8', border: '1px solid #bfdbfe' },
    };
    const style = styles[kind] || styles.info;

    box.style.display = 'block';
    box.style.background = style.background;
    box.style.color = style.color;
    box.style.border = style.border;
    box.innerText = text;
}

function renderHistorySummary(summary) {
    const box = document.getElementById('historySummary');
    if (!box) return;

    if (!summary.order_count) {
        box.innerHTML = '';
        return;
    }

    const unpaid = summary.unpaid_count > 0;

    box.innerHTML = `
        <div class="kpi-grid" style="margin-bottom:0;">
            <div class="kpi-card">
                <div class="kpi-label">TOTAL PESANAN</div>
                <div class="kpi-val">${summary.order_count}</div>
            </div>
            <div class="kpi-card ${unpaid ? 'border-danger' : 'border-success'}">
                <div class="kpi-label">BELUM BAYAR</div>
                <div class="kpi-val ${unpaid ? 'text-danger' : 'text-success'}">${summary.unpaid_count}</div>
            </div>
            <div class="kpi-card border-primary">
                <div class="kpi-label">SUDAH BAYAR</div>
                <div class="kpi-val">${formatRupiah(summary.total_spent || 0)}</div>
            </div>
            ${unpaid ? `
            <div class="kpi-card border-danger">
                <div class="kpi-label">SISA TAGIHAN</div>
                <div class="kpi-val text-danger">${formatRupiah(summary.unpaid_amount || 0)}</div>
            </div>` : ''}
        </div>`;
}

function renderHistoryOrders(orders) {
    const container = document.getElementById('historyList');
    if (!container) return;

    if (!orders.length) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1;">
                <div style="font-size:2.5rem; margin-bottom:8px;">🔍</div>
                <h3 style="color:#64748b; font-size:1.05rem; font-weight:700;">Belum ada pesanan</h3>
                <p style="color:#94a3b8; font-size:0.85rem; margin-top:4px;">
                    Nomor ini tidak punya pesanan dalam 90 hari terakhir. Pastikan nomornya sama dengan saat memesan.
                </p>
                <a href="/" class="btn btn-primary btn-sm" style="margin-top:14px;">☕ Pesan Sekarang</a>
            </div>`;
        return;
    }

    container.innerHTML = orders.map((order) => {
        const paidBadge = order.is_paid
            ? '<span class="brand-badge" style="background:#d1fae5; color:#059669;">✅ LUNAS</span>'
            : '<span class="brand-badge badge-unpaid-late">⚠️ BELUM BAYAR</span>';

        const method = order.payment_method === 'cash' ? '💵 Tunai' : '📱 QRIS';
        const service = order.service_type === 'dine_in' ? '🍽️ Makan di Tempat' : '🛍️ Bungkus';

        const items = order.items.map((i) => `
            <li style="display:flex; justify-content:space-between; gap:8px; font-size:0.83rem; color:#475569;">
                <span>${escapeHtml(i.product_name)} &times; ${i.quantity}</span>
                <span style="font-weight:700; color:#0f172a;">${formatRupiah(i.subtotal)}</span>
            </li>`).join('');

        const reorderItems = encodeURIComponent(JSON.stringify(
            order.items.map((i) => ({ product_id: i.product_id, quantity: i.quantity }))
        ));

        return `
            <div class="history-order ${order.is_paid ? 'is-paid' : 'is-unpaid'}">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                    <div style="min-width:0;">
                        <div style="font-weight:800; font-size:1rem; color:#0f172a;">${escapeHtml(order.invoice_number)}</div>
                        <div style="font-size:0.78rem; color:#94a3b8; margin-top:2px;">${escapeHtml(order.date_label)} &bull; ${escapeHtml(order.age_label)}</div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:4px;">${service} &bull; ${method}</div>
                    </div>
                    <div style="text-align:right;">
                        ${paidBadge}
                        <div style="font-weight:800; font-size:1.05rem; color:#0f172a; margin-top:6px;">${formatRupiah(order.total_amount)}</div>
                    </div>
                </div>

                ${!order.is_paid ? `
                    <div style="background:#fef2f2; color:#b91c1c; padding:10px 12px; border-radius:10px; margin-top:12px; font-size:0.83rem;">
                        💸 Pesanan ini <strong>belum dibayar</strong>. Silakan bayar di kasir saat mengambil pesanan.
                    </div>` : ''}

                <ul class="history-items">${items}</ul>

                <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
                    <a href="${escapeHtml(order.receipt_url)}" class="btn btn-outline btn-sm">🧾 Lihat Struk</a>
                    <a href="/menu?reorder=${reorderItems}" class="btn btn-primary btn-sm">🔁 Pesan Lagi</a>
                </div>
            </div>`;
    }).join('');
}

async function loadHistory(phone) {
    const btn = document.getElementById('btnHistorySearch');
    const list = document.getElementById('historyList');

    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Mencari...';
    }

    if (list) {
        list.innerHTML = `
            <div style="text-align:center; padding:30px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1;">
                <h3 style="color:#64748b; font-size:0.95rem; font-weight:700;">Mencari pesanan...</h3>
            </div>`;
    }

    try {
        const res = await fetch(`/api/orders/history?phone=${encodeURIComponent(phone)}`, {
            headers: { Accept: 'application/json' },
        });
        const result = await res.json();

        if (!result.success) {
            const firstError = result.errors ? Object.values(result.errors).flat()[0] : null;
            showHistoryMessage(firstError || result.message || 'Nomor tidak valid.', 'error');
            renderHistorySummary({ order_count: 0, unpaid_count: 0, total_spent: 0, unpaid_amount: 0 });
            renderHistoryOrders([]);
            return;
        }

        const summary = result.data.summary;

        if (!summary.order_count) {
            showHistoryMessage('Tidak ada pesanan untuk nomor tersebut.', 'info');
        } else {
            const box = document.getElementById('historyMessage');
            if (box) box.style.display = 'none';
        }

        renderHistorySummary(summary);
        renderHistoryOrders(result.data.orders);
    } catch (e) {
        showHistoryMessage('Koneksi bermasalah: ' + e.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerText = 'Cari Riwayat';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('historyForm');
    const input = document.getElementById('historyPhone');

    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const phone = input ? input.value.trim() : '';

            if (!phone) {
                showHistoryMessage('Masukkan nomor WhatsApp Anda dulu.', 'error');
                return;
            }

            loadHistory(phone);
        });
    }

    // Deep link from "Pesan Lagi" on the menu page hands the number over.
    const params = new URLSearchParams(window.location.search);
    const preset = params.get('phone');

    if (preset && input) {
        input.value = preset;
        loadHistory(preset);
    }
});
