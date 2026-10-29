let lastSeenOrderId = 0;
let pollingInterval = null;
let soundEnabled = true;
let currentOrdersCache = [];
let activeOrderFilter = 'all';
let startWorkingLabel = 'Mulai Masak';
let dashboardLoadInFlight = false;

// Web Audio API Synthesizer (Bip/Chime Suara Bel Kasir)
function playBeepSound() {
  if (!soundEnabled) return;
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    const ctx = new AudioContext();

    // 2-tone melodic chime
    const now = ctx.currentTime;
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(587.33, now); // D5
    osc1.frequency.setValueAtTime(880.00, now + 0.15); // A5
    gain1.gain.setValueAtTime(0.3, now);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
    osc1.connect(gain1);
    gain1.connect(ctx.destination);

    osc1.start(now);
    osc1.stop(now + 0.6);
  } catch (e) {
    console.log('Audio notification error:', e);
  }
}

function initOwnerPolling(ownerTypes, workingLabel) {
  if (workingLabel) {
    startWorkingLabel = workingLabel;
  }

  loadDashboardData();

  // Tutup modal kalkulator kembalian dengan tombol Esc
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && cashModalState) closeCashModal();
  });

  // Polling setiap 5 detik
  pollingInterval = setInterval(() => {
    checkNewOrdersPolling(ownerTypes);
  }, 5000);

  // Setup sound switch button
  const soundBtn = document.getElementById('btnToggleSound');
  if (soundBtn) {
    soundBtn.addEventListener('click', () => {
      soundEnabled = !soundEnabled;
      soundBtn.innerHTML = soundEnabled ? '🔔 Audio: On' : '🔕 Audio: Mute';
      soundBtn.classList.toggle('btn-primary', soundEnabled);
      soundBtn.classList.toggle('btn-outline', !soundEnabled);
      if (soundEnabled) playBeepSound();
    });
  }
}

async function loadDashboardData() {
  // Cegah request tumpang tindih: polling 5 detik + klik tombol bisa memicu
  // load bersamaan, dan respons yang telat bisa menimpa data yang lebih baru.
  if (dashboardLoadInFlight) return;
  dashboardLoadInFlight = true;

  try {
    const res = await fetch('/api/owner/orders');
    const data = await res.json();
    if (data.success) {
      lastSeenOrderId = data.data.last_id || 0;
      currentOrdersCache = data.data.orders || [];
      renderKPIStats(data.data.stats);
      renderOrderCards(currentOrdersCache);
    }
  } catch (e) {
    console.error('Error fetching dashboard:', e);
  } finally {
    dashboardLoadInFlight = false;
  }
}

async function checkNewOrdersPolling(ownerTypes) {
  try {
    const res = await fetch(`/api/orders/check-new?type=${encodeURIComponent(ownerTypes)}&last_id=${lastSeenOrderId}`);
    const response = await res.json();

    // ApiResponse::success() membungkus payload di dalam property "data".
    const poll = response.data || {};

    if (poll.has_new_order && poll.count > 0) {
      lastSeenOrderId = poll.latest_id;
      
      // Bunyikan Audio Bip
      playBeepSound();

      // Notifikasi Toast
      showToast(`⚡ ${poll.count} Pesanan Baru Masuk!`);

      // Refresh Tampilan Dashboard
      await loadDashboardData();
    }
  } catch (err) {
    console.warn('Polling check failed:', err);
  }
}

function renderKPIStats(stats) {
  if (!stats) return;
  const pendingEl = document.getElementById('kpiPending');
  const cookingEl = document.getElementById('kpiCooking');
  const doneEl = document.getElementById('kpiDone');
  const revEl = document.getElementById('kpiRevenue');

  if (pendingEl) pendingEl.innerText = stats.pending_count || 0;
  if (cookingEl) cookingEl.innerText = stats.cooking_count || 0;
  if (doneEl) doneEl.innerText = stats.done_today || 0;
  if (revEl) revEl.innerText = formatRupiah(stats.revenue_today || 0);
}

function filterOrderStatus(status) {
  activeOrderFilter = status;
  document.querySelectorAll('.filter-chip').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.status === status);
  });
  renderOrderCards(currentOrdersCache);
}

// ===== Pencarian antrean (no. meja, invoice, nama, HP, menu) =====
let orderSearchQuery = '';

function searchOrders(value) {
  orderSearchQuery = String(value || '').trim().toLowerCase();
  renderOrderCards(currentOrdersCache);
}

function matchesOrderSearch(order) {
  if (!orderSearchQuery) return true;

  const haystack = [
    order.table_number ? `meja ${order.table_number}` : '',
    order.invoice_number,
    order.customer_name,
    order.customer_phone,
    order.product_name,
  ].join(' ').toLowerCase();

  // Semua kata harus cocok, contoh: "budi kopi"
  return orderSearchQuery.split(/\s+/).every(word => haystack.includes(word));
}

function renderOrderCards(orders) {
  const container = document.getElementById('ordersContainer');
  if (!container) return;

  const byStatus = (activeOrderFilter === 'all')
    ? (orders || [])
    : (orders || []).filter(o => o.item_status === activeOrderFilter);
  const filtered = byStatus.filter(matchesOrderSearch);

  const countEl = document.getElementById('orderSearchCount');
  if (countEl) countEl.textContent = orderSearchQuery ? `${filtered.length} hasil` : '';

  if (filtered.length === 0) {
    const title = orderSearchQuery
      ? `Tidak ada pesanan yang cocok dengan "${escapeHtml(orderSearchQuery)}"`
      : `Tidak ada antrean pesanan ${activeOrderFilter !== 'all' ? '(' + activeOrderFilter + ')' : 'aktif'}`;
    const hint = orderSearchQuery
      ? 'Coba kata kunci lain, atau hapus pencarian untuk melihat semua antrean.'
      : 'Pesanan pelanggan yang masuk akan otomatis muncul di sini.';

    container.innerHTML = `
      <div style="text-align:center; padding:40px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; grid-column:1/-1;">
        <div style="font-size:2.5rem; margin-bottom:8px;">${orderSearchQuery ? '🔍' : '🍽️'}</div>
        <h3 style="color:#64748b; font-size:1.1rem; font-weight:700;">${title}</h3>
        <p style="color:#94a3b8; font-size:0.85rem; margin-top:2px;">${hint}</p>
      </div>
    `;
    return;
  }

  let html = '';
  filtered.forEach(order => {
    const isDineIn = order.service_type === 'dine_in';
    const statusBadge = {
      'pending': '<span class="brand-badge" style="background:#fee2e2; color:#ef4444;">⏳ Menunggu</span>',
      'cooking': '<span class="brand-badge" style="background:#fef3c7; color:#d97706;">🔥 Dimasak</span>',
      'done': '<span class="brand-badge" style="background:#d1fae5; color:#059669;">✅ Selesai</span>'
    }[order.item_status];

    const paymentBadge = order.payment_status === 'paid' 
      ? '<span class="brand-badge" style="background:#d1fae5; color:#059669;">LUNAS (' + order.payment_method.toUpperCase() + ')</span>'
      : '<span class="brand-badge" style="background:#fee2e2; color:#dc2626;">BELUM BAYAR</span>';

    html += `
      <div class="order-card" id="card-item-${order.item_id}">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
          <div>
            <div style="font-weight:800; font-size:1.1rem; color:#1e293b;">
              ${isDineIn ? '🍽️ Makan di Tempat' : '🛍️ Takeaway / Bungkus'}
              ${order.table_number ? `<span class="brand-badge" style="margin-left:4px;">Meja ${escapeHtml(order.table_number)}</span>` : ''}
            </div>
            <div style="font-size:0.78rem; color:#64748b; margin-top:2px;">
              Inv: <strong>${escapeHtml(order.invoice_number)}</strong> &bull; ${escapeHtml(order.customer_name)}
            </div>
          </div>
          <div>${statusBadge}</div>
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px; background:#f8fafc; border-radius:10px; margin-bottom:12px;">
          <div>
            <div style="font-weight:700; font-size:1rem; color:#0f172a;">${escapeHtml(order.product_name)}</div>
            <div style="font-size:0.85rem; color:#64748b;">Jumlah: <strong style="color:#2563eb; font-size:1rem;">${order.quantity} porsi</strong></div>
            ${order.category_name ? `<span class="brand-badge ${order.owner_type === 'minuman' ? 'badge-drink' : 'badge-food'}" style="font-size:0.65rem; padding:1px 6px;">${escapeHtml(order.category_name)}</span>` : ''}
            ${order.item_notes ? `<div class="order-item-note"><span>📝</span> <span>${escapeHtml(order.item_notes)}</span></div>` : ''}
          </div>
          <div style="text-align:right;">
            <div style="font-weight:800; color:#0f172a; font-size:0.95rem;">${formatRupiah(order.subtotal)}</div>
            <div style="margin-top:2px;">${paymentBadge}</div>
          </div>
        </div>

        ${order.notes ? `<div style="font-size:0.8rem; background:#fffbeb; padding:6px 10px; border-radius:8px; margin-bottom:12px; color:#b45309;">📝 Catatan Pesanan: ${escapeHtml(order.notes)}</div>` : ''}

        <div style="display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">
          ${order.payment_status === 'unpaid' ? `
            <button class="btn btn-warning btn-sm" onclick="confirmPayment(${order.sale_id})">
              💵 Terima Bayar Kasir
            </button>
          ` : ''}
          ${order.item_status === 'pending' ? `
            <button class="btn btn-primary btn-sm" onclick="updateStatus(${order.item_id}, 'cooking')">
              ${order.owner_type === 'minuman' ? '🥤' : '👨‍🍳'} ${startWorkingLabel}
            </button>
          ` : ''}
          ${order.item_status === 'cooking' ? `
            <button class="btn btn-success btn-sm" onclick="updateStatus(${order.item_id}, 'done')">
              ✅ Tandai Selesai
            </button>
          ` : ''}
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

async function updateStatus(itemId, newStatus) {
  try {
    const res = await fetch('/api/owner/item/status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_id: itemId, status: newStatus })
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message);
      loadDashboardData();
    } else {
      alert(result.message);
    }
  } catch (e) {
    alert('Gagal update status: ' + e.message);
  }
}

// ===== Kalkulator Uang Kembalian (pembayaran tunai) =====
let cashModalState = null; // { saleId, total }

function confirmPayment(saleId) {
  const order = currentOrdersCache.find(o => o.sale_id === saleId);

  // Pesanan tunai: buka kalkulator kembalian. Selain itu cukup konfirmasi biasa.
  if (order && order.payment_method === 'cash' && document.getElementById('cashModal')) {
    openCashModal(order);
    return;
  }

  if (!confirm('Konfirmasi pelanggan sudah membayar tunai / QRIS di kasir?')) return;
  markSalePaid(saleId);
}

function openCashModal(order) {
  const total = Math.round(Number(order.total_amount) || 0);
  cashModalState = { saleId: order.sale_id, total };

  document.getElementById('cashModalInvoice').textContent =
    `Inv: ${order.invoice_number} • ${order.customer_name}`;
  document.getElementById('cashTotal').textContent = formatRupiah(total);

  const input = document.getElementById('cashReceived');
  input.value = '';

  renderCashQuickButtons(total);
  updateCashChange();

  document.getElementById('cashModal').classList.add('active');
  document.body.style.overflow = 'hidden';
  setTimeout(() => input.focus(), 50);
}

function closeCashModal() {
  document.getElementById('cashModal').classList.remove('active');
  document.body.style.overflow = '';
  cashModalState = null;
}

// Saran nominal cepat: uang pas + pembulatan ke pecahan uang kertas umum.
function renderCashQuickButtons(total) {
  const amounts = [total];
  [5000, 10000, 20000, 50000, 100000].forEach(step => {
    const rounded = Math.ceil(total / step) * step;
    if (rounded > total && !amounts.includes(rounded)) amounts.push(rounded);
  });

  document.getElementById('cashQuickButtons').innerHTML = amounts.slice(0, 5).map((amount, i) => `
    <button type="button" class="btn btn-outline btn-sm" onclick="setCashReceived(${amount})">
      ${i === 0 ? 'Uang Pas' : formatRupiah(amount)}
    </button>
  `).join('');
}

function parseCashValue(text) {
  return Number(String(text).replace(/\D/g, '')) || 0;
}

function onCashInput(input) {
  const value = parseCashValue(input.value);
  input.value = value ? value.toLocaleString('id-ID') : '';
  updateCashChange();
}

function setCashReceived(amount) {
  document.getElementById('cashReceived').value = amount.toLocaleString('id-ID');
  updateCashChange();
}

function updateCashChange() {
  if (!cashModalState) return;

  const received = parseCashValue(document.getElementById('cashReceived').value);
  const diff = received - cashModalState.total;
  const box = document.getElementById('cashChangeBox');
  const enough = received > 0 && diff >= 0;

  document.getElementById('cashChangeLabel').textContent = diff < 0 && received > 0 ? 'Uang Kurang' : 'Kembalian';
  document.getElementById('cashChange').textContent = formatRupiah(received > 0 ? Math.abs(diff) : 0);
  box.classList.toggle('is-short', received > 0 && diff < 0);
  box.classList.toggle('is-ok', enough);
  document.getElementById('btnCashSubmit').disabled = !enough;
}

async function submitCashPayment(e) {
  e.preventDefault();
  if (!cashModalState) return;

  const received = parseCashValue(document.getElementById('cashReceived').value);
  const change = received - cashModalState.total;
  if (change < 0) return;

  const btn = document.getElementById('btnCashSubmit');
  btn.disabled = true;

  const ok = await markSalePaid(cashModalState.saleId,
    `Lunas • Kembalian ${formatRupiah(change)}`, received);

  if (ok) {
    closeCashModal();
  } else {
    btn.disabled = false;
  }
}

async function markSalePaid(saleId, successMessage, cashReceived = null) {
  const payload = { sale_id: saleId, payment_status: 'paid' };
  if (cashReceived !== null) payload.cash_received = cashReceived;

  try {
    const res = await fetch('/api/owner/payment/status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (result.success) {
      showToast(successMessage || 'Status pembayaran berhasil diubah ke LUNAS');
      loadDashboardData();
      return true;
    }
    alert('Error: ' + result.message);
  } catch (e) {
    alert('Error: ' + e.message);
  }
  return false;
}


function formatRupiah(num) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
}

function showToast(msg) {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `<span>🔔</span> <span>${msg}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 3500);
}

function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}