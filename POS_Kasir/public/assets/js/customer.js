let cart = {}; // { productId: { id, name, price, qty, image, category_name } }
let activeCategory = 'all';
let checkoutInProgress = false;

function initCustomerMenu() {
  renderCartBadge();
  watchConnection();
  applyReorderFromUrl();
}

/**
 * "Pesan Lagi" on the history page links back here with ?reorder=[{product_id,
 * quantity}, ...]. The product details come from the map the view injected,
 * so this only needs ids.
 */
function applyReorderFromUrl() {
  const raw = new URLSearchParams(window.location.search).get('reorder');
  if (!raw) return;

  let items;
  try {
    items = JSON.parse(raw);
  } catch (e) {
    return;
  }

  if (!Array.isArray(items) || items.length === 0) return;

  const catalog = window.__MENU_PRODUCTS__ || {};
  let added = 0;
  const missing = [];

  items.forEach((entry) => {
    const product = catalog[String(entry.product_id)];

    // A product can be gone or deactivated since the old order was placed.
    if (!product) {
      missing.push(entry.product_id);
      return;
    }

    cart[product.id] = {
      id: product.id,
      name: product.name,
      price: product.price,
      qty: Math.max(1, parseInt(entry.quantity, 10) || 1),
      image: product.image,
      category: product.category,
      notes: entry.notes || '',
    };
    added += 1;
  });

  if (added > 0) {
    renderCartBadge();
    openCartModal();
    showToast(`🔁 ${added} menu dimuat lagi ke keranjang`);
  }

  if (missing.length > 0) {
    setTimeout(() => {
      showToast(`⚠️ ${missing.length} menu sudah tidak tersedia, dilewati`);
    }, 600);
  }

  // Keep the URL clean so a refresh does not duplicate the cart.
  window.history.replaceState({}, '', window.location.pathname);
}

// Offline banner: the menu still renders from the service worker cache, but
// ordering is blocked until the connection is back.
function watchConnection() {
  const banner = document.getElementById('offlineBanner');
  const submitBtn = document.getElementById('btnSubmitOrder');

  const apply = () => {
    const online = navigator.onLine;

    if (banner) {
      banner.style.display = online ? 'none' : 'block';
    }

    if (submitBtn) {
      submitBtn.disabled = !online;
    }
  };

  apply();
  window.addEventListener('online', apply);
  window.addEventListener('offline', apply);
}

function selectCategory(catId) {
  activeCategory = catId;
  document.querySelectorAll('.category-pill').forEach(el => {
    el.classList.toggle('active', el.dataset.cat === String(catId));
  });

  applyFilters();
}

function filterMenuSearch() {
  applyFilters();
}

function applyFilters() {
  const searchTerm = (document.getElementById('menuSearchInput')?.value || '').toLowerCase().trim();
  const sortMode = document.getElementById('menuSortSelect')?.value || 'default';
  const grid = document.getElementById('productGrid');
  if (!grid) return;

  const cards = Array.from(grid.querySelectorAll('.card-product'));
  let visibleCount = 0;

  cards.forEach(card => {
    const cardCat = card.dataset.category;
    const cardName = (card.dataset.name || '').toLowerCase();

    const matchesCategory = (activeCategory === 'all' || cardCat === String(activeCategory));
    const matchesSearch = (!searchTerm || cardName.includes(searchTerm));

    if (matchesCategory && matchesSearch) {
      card.style.display = 'flex';
      visibleCount++;
    } else {
      card.style.display = 'none';
    }
  });

  cards.sort((a, b) => {
    if (sortMode === 'price_asc') {
      return (parseFloat(a.dataset.price) || 0) - (parseFloat(b.dataset.price) || 0);
    }
    if (sortMode === 'price_desc') {
      return (parseFloat(b.dataset.price) || 0) - (parseFloat(a.dataset.price) || 0);
    }
    if (sortMode === 'name_asc') {
      return (a.dataset.name || '').localeCompare(b.dataset.name || '');
    }
    return (parseInt(a.dataset.order, 10) || 0) - (parseInt(b.dataset.order, 10) || 0);
  });

  cards.forEach(card => grid.appendChild(card));

  const emptyState = document.getElementById('emptySearchState');
  if (emptyState) {
    emptyState.style.display = (visibleCount === 0) ? 'block' : 'none';
  }
}

function addToCart(id, name, price, image, category) {
  if (checkoutInProgress) return;
  if (cart[id]) {
    cart[id].qty += 1;
  } else {
    cart[id] = { id, name, price: parseFloat(price), qty: 1, image, category, notes: '' };
  }

  // Mobile haptic vibration if supported
  if (navigator.vibrate) {
    navigator.vibrate(40);
  }

  renderCartBadge();
  showToast(`"${name}" ditambahkan ke keranjang`);
}

function updateCartItemNote(id, noteText) {
  if (cart[id]) {
    cart[id].notes = noteText;
  }
}

function updateCartQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty += delta;
  if (cart[id].qty <= 0) {
    delete cart[id];
  }
  renderCartBadge();
  renderCartModalItems();
}

function getCartTotal() {
  return Object.values(cart).reduce((sum, item) => sum + (item.price * item.qty), 0);
}

function getCartCount() {
  return Object.values(cart).reduce((sum, item) => sum + item.qty, 0);
}

function renderCartBadge() {
  const count = getCartCount();
  const total = getCartTotal();
  const bar = document.getElementById('floatingCartBar');
  const countEl = document.getElementById('cartTotalCount');
  const amountEl = document.getElementById('cartTotalAmount');
  const bubble = document.getElementById('cartBadgeCount');

  const headerBadge = document.getElementById('headerCartCount');
  if (headerBadge) {
    headerBadge.innerText = count;
  }

  if (bar && countEl && amountEl) {
    if (count > 0) {
      bar.style.display = 'flex';
      countEl.innerText = `${count} Menu Dipilih`;
      amountEl.innerText = formatRupiah(total);
      if (bubble) bubble.innerText = count;
    } else {
      bar.style.display = 'none';
    }
  }
}

function openCartModal() {
  renderCartModalItems();
  const modal = document.getElementById('cartModal');
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeCartModal() {
  const modal = document.getElementById('cartModal');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function handleModalOverlayClick(e) {
  if (e.target.id === 'cartModal') {
    closeCartModal();
  }
}

function renderCartModalItems() {
  const container = document.getElementById('cartItemsList');
  const totalEl = document.getElementById('modalCartTotal');
  if (!container || !totalEl) return;

  const items = Object.values(cart);
  if (items.length === 0) {
    container.innerHTML = `
      <div style="text-align:center; padding:30px 10px; color:#64748b;">
        <div style="font-size:2rem; margin-bottom:6px;">🛒</div>
        Keranjang pesanan masih kosong.
      </div>`;
    totalEl.innerText = 'Rp 0';
    return;
  }

  let html = '';
  items.forEach(item => {
    html += `
      <div class="cart-item-card">
        <div class="cart-item-row">
          <div class="cart-item-info">
            <div class="cart-item-name">${escapeHtml(item.name)}</div>
            <div class="cart-item-unit-price">${formatRupiah(item.price)}</div>
          </div>
          <div class="cart-item-controls">
            <button type="button" class="btn-qty" onclick="updateCartQty(${item.id}, -1)">&minus;</button>
            <span class="cart-item-qty">${item.qty}</span>
            <button type="button" class="btn-qty" onclick="updateCartQty(${item.id}, 1)">&plus;</button>
            <span class="cart-item-subtotal">${formatRupiah(item.price * item.qty)}</span>
          </div>
        </div>
        <div class="cart-item-note-wrap">
          <input type="text" class="cart-item-note-input"
                 placeholder="📝 Catatan item (misal: less sugar, pedas, dll)..."
                 value="${escapeHtml(item.notes || '')}"
                 oninput="updateCartItemNote(${item.id}, this.value)"
                 maxlength="200">
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
  totalEl.innerText = formatRupiah(getCartTotal());
}

function setServiceType(type) {
  document.getElementById('service_type').value = type;
  document.getElementById('btnDineIn').classList.toggle('active', type === 'dine_in');
  document.getElementById('btnTakeaway').classList.toggle('active', type === 'takeaway');

  // Nomor meja hanya dibutuhkan untuk makan di tempat.
  const group = document.getElementById('tableNumberGroup');
  const input = document.getElementById('table_number');
  if (group && input) {
    group.style.display = type === 'dine_in' ? '' : 'none';
    input.required = type === 'dine_in';
  }
}

// Isi nomor meja otomatis dari link/QR meja, contoh: /?meja=5
function prefillTableNumber() {
  const input = document.getElementById('table_number');
  if (!input) return;

  const params = new URLSearchParams(window.location.search);
  const raw = (params.get('meja') || params.get('table') || '').trim();
  if (/^[A-Za-z0-9\- ]{1,10}$/.test(raw)) {
    input.value = raw.toUpperCase();
  }
}
document.addEventListener('DOMContentLoaded', prefillTableNumber);

async function submitCheckout(e) {
  e.preventDefault();
  if (checkoutInProgress) return;

  // Payments and stock reservation need the server, so never queue an order offline.
  if (!navigator.onLine) {
    alert('Anda sedang offline.\n\nPesanan butuh koneksi internet untuk terkirim ke dapur dan untuk pembayaran QRIS. Silakan tunggu jaringan kembali.');
    return;
  }

  const items = Object.values(cart).map(i => ({
    product_id: i.id,
    quantity: i.qty,
    notes: (i.notes || '').trim()
  }));
  if (items.length === 0) {
    alert('Silakan pilih menu terlebih dahulu!');
    return;
  }

  const customer_name = document.getElementById('customer_name').value.trim();
  const customer_phone = document.getElementById('customer_phone').value.trim();
  const service_type = document.getElementById('service_type').value;
  const table_number = service_type === 'dine_in'
    ? (document.getElementById('table_number')?.value.trim().toUpperCase() || '')
    : '';
  const payment_method = document.querySelector('input[name="payment_method"]:checked')?.value || 'qris';
  const notes = document.getElementById('order_notes')?.value.trim() || '';

  const submitBtn = document.getElementById('btnSubmitOrder');
  if (!submitBtn) return;
  checkoutInProgress = true;
  submitBtn.disabled = true;
  submitBtn.innerText = 'Mengirim Pesanan...';

  try {
    const res = await fetch('/api/orders/checkout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_name,
        customer_phone,
        service_type,
        table_number,
        payment_method,
        notes,
        items
      })
    });

    const result = await res.json();
    if (result.success) {
      cart = {};
      renderCartBadge();
      window.location.href = result.data.receipt_url;
    } else {
      // Laravel mengirim 422 dengan pesan generik + rincian per field.
      // Tampilkan rincian yang sebenarnya agar pelanggan tahu apa yang salah.
      const firstError = result.errors
        ? Object.values(result.errors).flat()[0]
        : null;
      alert('Gagal: ' + (firstError || result.message));
      submitBtn.disabled = false;
      submitBtn.innerText = 'Kirim Pesanan Sekarang 🚀';
      checkoutInProgress = false;
    }
  } catch (err) {
    alert('Terjadi kesalahan koneksi server: ' + err.message);
    submitBtn.disabled = false;
    submitBtn.innerText = 'Kirim Pesanan Sekarang 🚀';
    checkoutInProgress = false;
  }
}

function formatRupiah(number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
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
  toast.innerHTML = `<span>✨</span> <span>${msg}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 3000);
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
