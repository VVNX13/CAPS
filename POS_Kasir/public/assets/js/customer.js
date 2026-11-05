let cart = {}; // { productId: { id, name, price, qty, image, category_name } }
let activeCategory = 'all';
let checkoutInProgress = false;

function initCustomerMenu() {
  renderCartBadge();
  watchConnection();
  applyReorderFromUrl();
  initCustomerPreferences();
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

    const existing = cart[product.id];
    if (existing) {
      for (let i = 0; i < Math.max(1, parseInt(entry.quantity, 10) || 1); i++) existing.units.push({ chips: [], note: entry.notes || '' });
    } else cart[product.id] = {
      id: product.id,
      name: product.name,
      price: product.price,
      units: Array.from({ length: Math.max(1, parseInt(entry.quantity, 10) || 1) }, () => ({ chips: [], note: entry.notes || '' })),
      image: product.image,
      category: product.category,
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
  if (!cart[id]) cart[id] = { id, name, price: parseFloat(price), image, category, ownerType: '', units: [] };
  cart[id].units.push({ chips: [], note: '' });

  // Mobile haptic vibration if supported
  if (navigator.vibrate) {
    navigator.vibrate(40);
  }

  renderCartBadge();
  showToast(`"${name}" ditambahkan ke keranjang`);
}

function updateCartUnitNote(id, index, noteText) {
  if (cart[id]?.units[index]) cart[id].units[index].note = noteText;
}

function toggleCartUnitChip(id, unitIndex, chipIndex, button) {
  const unit = cart[id]?.units[unitIndex];
  if (!unit) return;
  const chip = getProductMeta(cart[id]).chips[chipIndex];
  const index = unit.chips.indexOf(chip);
  if (index >= 0) unit.chips.splice(index, 1);
  else unit.chips.push(chip);
  button.classList.toggle('active');
  renderCartBadge();
  document.getElementById('modalCartTotal').innerText = formatRupiah(getCartTotal());
}

function removeCartUnit(id, index) {
  if (!cart[id]) return;
  cart[id].units.splice(index, 1);
  if (!cart[id].units.length) delete cart[id];
  renderCartBadge();
  renderCartModalItems();
}

function updateCartQty(id, delta) {
  if (!cart[id]) return;
  if (delta > 0) cart[id].units.push({ chips: [], note: '' });
  else cart[id].units.pop();
  if (!cart[id].units.length) delete cart[id];
  renderCartBadge();
  renderCartModalItems();
}

function getCartTotal() {
  return Object.values(cart).reduce((sum, item) => sum + (item.price * item.units.length), 0);
}

function getCartCount() {
  return Object.values(cart).reduce((sum, item) => sum + item.units.length, 0);
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
    renderCartTotals();
    return;
  }

  let html = '';
  items.forEach(item => {
    const chips = getProductMeta(item).chips;
    html += `
      <div class="cart-item-card">
        <div class="cart-item-row">
          <div class="cart-item-info">
            <div class="cart-item-name">${escapeHtml(item.name)}</div>
            <div class="cart-item-unit-price">${formatRupiah(item.price)}</div>
          </div>
          <div class="cart-item-controls">
            <button type="button" class="btn-qty" aria-label="Kurangi jumlah" onclick="updateCartQty(${item.id}, -1)">&minus;</button>
            <span class="cart-item-qty">${item.units.length}</span>
            <button type="button" class="btn-qty" aria-label="Tambah jumlah" onclick="updateCartQty(${item.id}, 1)">&plus;</button>
            <span class="cart-item-subtotal">${formatRupiah(item.price * item.units.length)}</span>
          </div>
        </div>
        <div class="cart-unit-list">${item.units.map((unit, index) => `
          <section class="cart-unit">
            <div class="cart-unit-head"><strong>Pesanan ${index + 1}</strong><button type="button" class="cart-unit-remove" onclick="removeCartUnit(${item.id}, ${index})" aria-label="Hapus pesanan ${index + 1}">Hapus</button></div>
            <div class="cart-unit-chips">${chips.map((chip, chipIndex) => `<button type="button" class="pv-chip ${unit.chips.includes(chip) ? 'active' : ''}" onclick="toggleCartUnitChip(${item.id}, ${index}, ${chipIndex}, this)">${escapeHtml(chip)}</button>`).join('')}</div>
            <input type="text" class="cart-item-note-input" placeholder="Catatan khusus untuk pesanan ini" value="${escapeHtml(unit.note)}" oninput="updateCartUnitNote(${item.id}, ${index}, this.value)" maxlength="180">
          </section>`).join('')}</div>
      </div>
    `;
  });

  container.innerHTML = html;
  renderCartTotals();
  refreshAppliedPromo();
}

// ===== Kupon promo =====
// Potongan di sini hanya pratinjau; server menghitung ulang saat checkout.
let appliedPromo = null; // { code, label, discount, subtotal }
let promoCheckTimer = null;

function renderCartTotals() {
  const subtotal = getCartTotal();
  const discount = appliedPromo ? Math.min(appliedPromo.discount, subtotal) : 0;
  const rows = document.getElementById('cartDiscountRows');

  if (rows) rows.style.display = discount > 0 ? '' : 'none';
  if (discount > 0) {
    document.getElementById('modalCartSubtotal').innerText = formatRupiah(subtotal);
    document.getElementById('modalDiscountLabel').innerText = `Diskon ${appliedPromo.code} (${appliedPromo.label})`;
    document.getElementById('modalCartDiscount').innerText = '-' + formatRupiah(discount);
  }

  const totalEl = document.getElementById('modalCartTotal');
  if (totalEl) totalEl.innerText = formatRupiah(subtotal - discount);
}

function setPromoStatus(message, ok) {
  const el = document.getElementById('promoStatus');
  if (!el) return;
  el.textContent = message || '';
  el.className = 'promo-status' + (message ? (ok ? ' is-ok' : ' is-error') : '');
}

async function checkPromo(code, subtotal) {
  const res = await fetch('/api/promo/check', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ code, subtotal })
  });
  const result = await res.json();
  if (!result.success) {
    const firstError = result.errors ? Object.values(result.errors).flat()[0] : null;
    throw new Error(firstError || result.message);
  }
  return result.data;
}

async function applyPromoCode() {
  const input = document.getElementById('promoCodeInput');
  const btn = document.getElementById('btnApplyPromo');
  const code = (input?.value || '').trim().toUpperCase();

  // Tombol berubah jadi "Hapus" saat kupon sudah terpasang.
  if (appliedPromo && (!code || code === appliedPromo.code)) {
    removePromoCode();
    return;
  }
  if (!code) {
    setPromoStatus('Masukkan kode kupon terlebih dahulu', false);
    return;
  }

  btn.disabled = true;
  try {
    const subtotal = getCartTotal();
    const data = await checkPromo(code, subtotal);
    appliedPromo = { ...data, subtotal };
    input.value = data.code;
    btn.innerText = 'Hapus';
    setPromoStatus(`✅ ${data.name}: hemat ${formatRupiah(data.discount)}`, true);
  } catch (err) {
    appliedPromo = null;
    btn.innerText = 'Pakai';
    setPromoStatus('❌ ' + err.message, false);
  } finally {
    btn.disabled = false;
    renderCartTotals();
  }
}

function removePromoCode() {
  appliedPromo = null;
  const input = document.getElementById('promoCodeInput');
  if (input) input.value = '';
  const btn = document.getElementById('btnApplyPromo');
  if (btn) btn.innerText = 'Pakai';
  setPromoStatus('', true);
  renderCartTotals();
}

// Isi keranjang berubah: hitung ulang potongan (kupon persen / minimal belanja).
function refreshAppliedPromo() {
  if (!appliedPromo || appliedPromo.subtotal === getCartTotal()) return;
  clearTimeout(promoCheckTimer);
  promoCheckTimer = setTimeout(async () => {
    if (!appliedPromo) return;
    const subtotal = getCartTotal();
    try {
      const data = await checkPromo(appliedPromo.code, subtotal);
      appliedPromo = { ...data, subtotal };
      setPromoStatus(`✅ ${data.name}: hemat ${formatRupiah(data.discount)}`, true);
    } catch (err) {
      const code = appliedPromo.code;
      removePromoCode();
      setPromoStatus(`❌ Kupon ${code} dilepas: ${err.message}`, false);
    }
    renderCartTotals();
  }, 400);
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

  const items = Object.values(cart).flatMap(i => {
    const grouped = new Map();
    i.units.forEach(unit => {
      const notes = [...unit.chips, unit.note.trim()].filter(Boolean).join(', ');
      grouped.set(notes, (grouped.get(notes) || 0) + 1);
    });
    return [...grouped].map(([notes, quantity]) => ({ product_id: i.id, quantity, notes }));
  });
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
        promo_code: appliedPromo ? appliedPromo.code : null,
        items
      })
    });

    const result = await res.json();
    if (result.success) {
      cart = {};
      appliedPromo = null;
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

// ponytail: derive composition, rating, and taste tags client-side from existing product data; move to DB columns when owner needs per-item nutrition/recipe CRUD.
let activePreviewProduct = null;
let selectedPreviewChips = new Set();

function getProductMeta(p) {
  const n = (p.name || '').toLowerCase();
  const isFood = p.ownerType === 'makanan' || (p.category || '').toLowerCase().includes('makan');
  const rating = (4.7 + ((p.id || 1) % 3) * 0.1).toFixed(1);
  const reviews = 45 + ((p.id || 1) * 19) % 140;

  let composition = p.desc || (isFood ? 'Bahan segar pilihan, bumbu rempah dapur Taman Cafe, disajikan hangat.' : 'Racikan bahan minuman segar berkualitas dengan takaran pas.');
  if (n.includes('nasi goreng')) composition = 'Nasi pulen, bumbu rempah nusantara, telur mata sapi, ayam suwir, bawang goreng, kerupuk udang & acar.';
  else if (n.includes('ayam bakar')) composition = 'Ayam bagian pilihan, olesan madu karamel & bumbu rempah, sambal terasi segar, lalapan.';
  else if (n.includes('mie')) composition = 'Mie kuning kenyal, udang & cumi segar, bakso ikan, sawi hijau, kol, bawang putih.';
  else if (n.includes('sop') || n.includes('buntut')) composition = 'Potongan buntut sapi empuk, kaldu rempah bening (pala, cengkeh, kayu manis), wortel, kentang, seledri.';
  else if (n.includes('kentang') || n.includes('fries')) composition = 'Kentang potong crispy, white truffle oil, taburan parmesan/keju mayo & parsley.';
  else if (n.includes('tahu')) composition = 'Tahu sutra lembut berbalut tepung renyah, bawang putih cincang, cabai rawit, daun bawang.';
  else if (n.includes('cireng')) composition = 'Aci goreng renyah kenyal, bumbu rujak gula aren asli, asam jawa & cabai segar.';
  else if (n.includes('gula aren') || n.includes('kopi susu')) composition = 'Double shot espresso (Arabika & Robusta), fresh milk creamy, gula aren organik.';
  else if (n.includes('caramel') || n.includes('macchiato')) composition = 'Espresso shot, steamed fresh milk, sirup vanila, saus karamel di atas foam.';
  else if (n.includes('americano') || n.includes('v60') || n.includes('gayo')) composition = '100% ekstrak biji kopi pilihan seduh segar dengan air mineral murni (0% gula/susu).';
  else if (n.includes('matcha')) composition = 'Bubuk teh hijau Uji Matcha Jepang autentik, susu segar creamy, sedikit pemanis alami.';
  else if (n.includes('berry') || n.includes('sparkler')) composition = 'Puree buah beri campuran, air soda dingin, perasan lemon segar & daun mint.';
  else if (n.includes('alpukat')) composition = 'Daging buah alpukat mentega matang, es serut halus, susu & swirl cokelat.';
  else if (n.includes('mangga')) composition = 'Buah mangga harum manis segar diblender halus dengan es batu kristal.';

  const highlights = isFood
    ? (n.includes('kentang') || n.includes('tahu') || n.includes('cireng')
      ? ['Porsi sharing', 'Disajikan hangat', 'Teman ngopi']
      : ['Porsi mengenyangkan', 'Bumbu segar', 'Disajikan hangat'])
    : (n.includes('americano') || n.includes('v60')
      ? ['Rendah kalori', 'Kafein kuat', 'Tanpa gula']
      : ['Dingin menyegarkan', 'Manis pas', 'Favorit pelanggan']);

  const chips = isFood
    ? ['Tidak Pedas', 'Pedas Sedang', 'Extra Pedas', 'Pisah Sambal', 'Tanpa Bawang']
    : ['Normal Sugar', 'Less Sugar', 'No Sugar', 'Less Ice', 'Normal Ice'];

  return { rating, reviews, composition, highlights, chips, isFood };
}

function openProductPreview(event, product) {
  if (event && event.target && event.target.closest('button')) return;
  activePreviewProduct = product;
  selectedPreviewChips = new Set();

  const meta = getProductMeta(product);
  const imgWrap = document.getElementById('previewImgWrap');
  if (imgWrap) {
    const imgHtml = product.image
      ? `<img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" style="width:100%; height:100%; object-fit:cover;">`
      : `<div class="card-product-img-placeholder ${meta.isFood ? 'ph-food' : 'ph-drink'}" style="width:100%; height:100%;"><span class="ph-emoji">${meta.isFood ? '🍲' : '🥤'}</span></div>`;
    imgWrap.innerHTML = `
      ${imgHtml}
      <span class="brand-badge ${meta.isFood ? 'badge-food' : 'badge-drink'} img-badge">${escapeHtml(product.category || '')}</span>
      <button type="button" class="preview-close-btn" onclick="closeProductPreview()" aria-label="Tutup"></button>
    `;
  }

  document.getElementById('previewTitle').innerText = product.name;
  document.getElementById('previewRating').innerHTML = `<span class="pv-star">&#9733;</span> ${meta.rating} &middot; ${meta.reviews}+ ulasan${product.badge ? ' &middot; ' + escapeHtml(product.badge) : ''}`;
  document.getElementById('previewPrice').innerText = formatRupiah(product.price);
  document.getElementById('previewStock').innerText = `Stok ${product.stock}`;
  const descEl = document.getElementById('previewDesc');
  descEl.innerText = product.desc || '';
  descEl.style.display = product.desc && product.desc !== meta.composition ? 'block' : 'none';
  document.getElementById('previewComposition').innerText = meta.composition;

  document.getElementById('previewHighlights').innerHTML = meta.highlights
    .map(h => `<span class="pv-tag">${escapeHtml(h)}</span>`)
    .join('');

  document.getElementById('previewChips').innerHTML = meta.chips
    .map(c => `<button type="button" class="pv-chip" onclick="togglePreviewChip(this, '${escapeHtml(c)}')">${escapeHtml(c)}</button>`)
    .join('');

  const noteInput = document.getElementById('previewCustomNote');
  if (noteInput) noteInput.value = '';

  const addBtn = document.getElementById('previewAddBtn');
  if (addBtn) addBtn.disabled = (product.stock < 1);

  const modal = document.getElementById('productPreviewModal');
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function togglePreviewChip(btn, chipText) {
  if (selectedPreviewChips.has(chipText)) {
    selectedPreviewChips.delete(chipText);
    btn.classList.remove('active');
  } else {
    selectedPreviewChips.add(chipText);
    btn.classList.add('active');
  }
}

function closeProductPreview() {
  const modal = document.getElementById('productPreviewModal');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function addPreviewProductToCart() {
  if (!activePreviewProduct) return;
  const p = activePreviewProduct;
  addToCart(p.id, p.name, p.price, p.image, p.category);

  const customNote = (document.getElementById('previewCustomNote')?.value || '').trim();
  const unit = cart[p.id]?.units[cart[p.id].units.length - 1];
  if (unit) {
    unit.chips = [...selectedPreviewChips];
    unit.note = customNote;
  }
  closeProductPreview();
}

/* ==========================================================================
   Customer Preference Questionnaire & Recommendation Engine
   ========================================================================== */

const PREF_KEYWORDS = {
  pedas: ['pedas', 'sambal', 'cabe', 'cabai', 'rawit', 'rujak'],
  gurih: ['gurih', 'crispy', 'krispi', 'rempah', 'truffle', 'fries', 'seafood', 'bakar', 'goreng', 'asin', 'buntut', 'keju'],
  manis: ['manis', 'madu', 'karamel', 'caramel', 'rujak'],
  hangat: ['sop', 'buntut', 'kuah', 'hangat', 'rempah', 'hot', 'mie', 'nasi'],
  segar: ['segar', 'mocktail', 'sparkler', 'lemon', 'mint', 'dingin', 'jus', 'soda'],
  kopi: ['kopi', 'espresso', 'americano', 'macchiato', 'cold brew', 'coffee'],
  creamy: ['creamy', 'susu', 'latte', 'alpukat', 'matcha', 'cheese', 'keju'],
  buah: ['jus', 'buah', 'mangga', 'alpukat', 'berry', 'lemon']
};

const PREF_LABELS = {
  pedas: 'Pedas & Gurih 🌶️',
  gurih: 'Gurih / Crispy 🍗',
  manis: 'Manis & Legit 🍯',
  hangat: 'Kuah Rempah Hangat 🍲',
  segar: 'Dingin & Segar 🧊',
  kopi: 'Kopi Mantap ☕',
  creamy: 'Creamy & Susu 🥛',
  buah: 'Jus Buah Asli 🥭'
};

const FOOD_PREFS = new Set(['pedas', 'gurih', 'manis', 'hangat']);
const DRINK_PREFS = new Set(['segar', 'kopi', 'creamy', 'buah']);

window.__PREF_PRODUCTS_MAP__ = {};

function getCatalogProducts() {
  if (Array.isArray(window.__ALL_CATALOG_PRODUCTS__) && window.__ALL_CATALOG_PRODUCTS__.length > 0) {
    return window.__ALL_CATALOG_PRODUCTS__;
  }

  // Fallback: extract directly from DOM product cards
  const cards = document.querySelectorAll('#productGrid .card-product');
  const list = [];
  cards.forEach((card, idx) => {
    const titleEl = card.querySelector('.card-product-title');
    const price = parseFloat(card.dataset.price) || 0;
    const descEl = card.querySelector('.card-product-desc');
    const imgEl = card.querySelector('.card-product-img');
    const badgeEl = card.querySelector('.brand-badge');
    const isFood = badgeEl?.classList.contains('badge-food') || false;

    const onclickStr = card.getAttribute('onclick') || '';
    const matchId = onclickStr.match(/id:\s*(\d+)/);
    const id = matchId ? parseInt(matchId[1], 10) : (idx + 1);

    list.push({
      id: id,
      name: titleEl ? titleEl.innerText.trim() : (card.dataset.name || ''),
      price: price,
      category: badgeEl ? badgeEl.innerText.trim() : (isFood ? 'Makanan' : 'Minuman'),
      ownerType: isFood ? 'makanan' : 'minuman',
      stock: 20,
      desc: descEl ? descEl.innerText.trim() : '',
      image: '',
      imageUrl: imgEl ? imgEl.getAttribute('src') || '' : ''
    });
  });

  window.__ALL_CATALOG_PRODUCTS__ = list;
  return list;
}

function initCustomerPreferences() {
  const status = sessionStorage.getItem('taman_pref_status');
  if (!status) {
    // Show modal quickly but smoothly after initial page load
    setTimeout(() => {
      openPreferenceModal();
    }, 380);
  } else if (status === 'answered') {
    let tags = [];
    try {
      tags = JSON.parse(sessionStorage.getItem('taman_pref_tags') || '[]');
    } catch (e) {
      tags = [];
    }
    if (tags.length > 0) {
      applyPreferenceRecommendations(tags, false);
    } else {
      showPreferenceInviteBanner();
    }
  } else {
    // status is skipped
    showPreferenceInviteBanner();
  }
}

function openPreferenceModal() {
  const modal = document.getElementById('preferenceModal');
  if (!modal) return;

  // Restore active chips if saved
  let savedTags = [];
  try {
    savedTags = JSON.parse(sessionStorage.getItem('taman_pref_tags') || '[]');
  } catch (e) {}

  document.querySelectorAll('#preferenceModal .pref-chip').forEach(chip => {
    const pref = chip.dataset.pref;
    chip.classList.toggle('active', savedTags.includes(pref));
  });

  updatePrefSubmitBtnText();

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closePreferenceModal() {
  const modal = document.getElementById('preferenceModal');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function skipPreferences() {
  sessionStorage.setItem('taman_pref_status', 'skipped');
  closePreferenceModal();
  showPreferenceInviteBanner();
  const recSec = document.getElementById('preferenceRecSection');
  if (recSec) recSec.style.display = 'none';
}

function togglePrefChip(button) {
  button.classList.toggle('active');
  updatePrefSubmitBtnText();
}

function updatePrefSubmitBtnText() {
  const count = document.querySelectorAll('#preferenceModal .pref-chip.active').length;
  const btn = document.getElementById('btnSavePreferences');
  if (btn) {
    btn.innerText = count > 0 ? `Tampilkan Rekomendasi (${count})` : 'Tampilkan Rekomendasi';
  }
}

function submitPreferences() {
  const activeChips = Array.from(document.querySelectorAll('#preferenceModal .pref-chip.active'));
  const tags = activeChips.map(c => c.dataset.pref).filter(Boolean);

  if (tags.length === 0) {
    showToast('Silakan pilih minimal 1 preferensi, atau klik Lewati');
    return;
  }

  sessionStorage.setItem('taman_pref_status', 'answered');
  sessionStorage.setItem('taman_pref_tags', JSON.stringify(tags));
  closePreferenceModal();
  applyPreferenceRecommendations(tags, true);
}

function showPreferenceInviteBanner() {
  const banner = document.getElementById('prefInviteBanner');
  if (banner) banner.style.display = 'flex';
  const recSec = document.getElementById('preferenceRecSection');
  if (recSec) recSec.style.display = 'none';
}

function applyPreferenceRecommendations(tags, shouldScroll) {
  const banner = document.getElementById('prefInviteBanner');
  if (banner) banner.style.display = 'none';

  const section = document.getElementById('preferenceRecSection');
  const grid = document.getElementById('prefCardsGrid');
  const labelEl = document.getElementById('prefActiveTagsLabel');
  if (!section || !grid) return;

  const catalog = getCatalogProducts();
  if (!catalog.length) return;

  // Build active label text
  const labelParts = tags.map(t => PREF_LABELS[t] || t).filter(Boolean);
  if (labelEl) {
    labelEl.innerText = labelParts.length > 0
      ? `Disaring berdasarkan selera: ${labelParts.join(', ')}.`
      : 'Disaring khusus berdasarkan preferensi rasa yang Anda pilih.';
  }

  // Separate food preferences vs drink preferences
  const foodTags = tags.filter(t => FOOD_PREFS.has(t));
  const drinkTags = tags.filter(t => DRINK_PREFS.has(t));

  const scoreProductsList = (productList, relevantTags) => {
    const scoredList = [];
    productList.forEach(product => {
      let score = 0;
      const pName = (product.name || '').toLowerCase();
      const pDesc = (product.desc || '').toLowerCase();
      const pCat = (product.category || '').toLowerCase();
      let matchedReason = '';

      relevantTags.forEach(tag => {
        const kws = PREF_KEYWORDS[tag] || [tag];
        kws.forEach(kw => {
          if (pName.includes(kw)) {
            score += 6;
            if (!matchedReason) matchedReason = PREF_LABELS[tag] || tag;
          }
          if (pDesc.includes(kw)) {
            score += 3;
            if (!matchedReason) matchedReason = PREF_LABELS[tag] || tag;
          }
          if (pCat.includes(kw)) {
            score += 1.5;
          }
        });
      });

      if (score > 0) {
        scoredList.push({
          product,
          score,
          reason: matchedReason || (product.ownerType === 'makanan' ? 'Menu Pilihan' : 'Minuman Pilihan')
        });
      }
    });

    scoredList.sort((a, b) => b.score - a.score);
    return scoredList;
  };

  const available = catalog.filter(p => (p.stock || 0) > 0);
  const allFood = available.filter(p => p.ownerType === 'makanan');
  const allDrinks = available.filter(p => p.ownerType !== 'makanan');

  let topItems = [];

  // CASE 1: Only Food Preferences selected -> Strictly Food ONLY
  if (foodTags.length > 0 && drinkTags.length === 0) {
    const scoredFood = scoreProductsList(allFood, foodTags);
    topItems = scoredFood.length > 0
      ? scoredFood.slice(0, 4)
      : allFood.slice(0, 3).map(p => ({ product: p, score: 0, reason: 'Menu Pilihan' }));
  }
  // CASE 2: Only Drink Preferences selected -> Strictly Drinks ONLY
  else if (foodTags.length === 0 && drinkTags.length > 0) {
    const scoredDrinks = scoreProductsList(allDrinks, drinkTags);
    topItems = scoredDrinks.length > 0
      ? scoredDrinks.slice(0, 4)
      : allDrinks.slice(0, 3).map(p => ({ product: p, score: 0, reason: 'Minuman Pilihan' }));
  }
  // CASE 3: Both Food and Drink Preferences selected -> Balanced Top Food + Top Drinks
  else if (foodTags.length > 0 && drinkTags.length > 0) {
    const scoredFood = scoreProductsList(allFood, foodTags);
    const topFoods = scoredFood.length > 0
      ? scoredFood.slice(0, 2)
      : allFood.slice(0, 2).map(p => ({ product: p, score: 0, reason: 'Menu Pilihan' }));

    const scoredDrinks = scoreProductsList(allDrinks, drinkTags);
    const topDrinks = scoredDrinks.length > 0
      ? scoredDrinks.slice(0, 2)
      : allDrinks.slice(0, 2).map(p => ({ product: p, score: 0, reason: 'Minuman Pilihan' }));

    topItems = [...topFoods, ...topDrinks];
  } else {
    // Fallback if tags empty
    topItems = allFood.slice(0, 2).concat(allDrinks.slice(0, 2)).map(p => ({
      product: p,
      score: 0,
      reason: 'Rekomendasi Spesial'
    }));
  }

  // Clear map and populate fresh payload
  window.__PREF_PRODUCTS_MAP__ = {};

  grid.innerHTML = topItems.map(item => {
    const p = item.product;
    const isFood = p.ownerType === 'makanan';
    const badgeText = item.reason;

    window.__PREF_PRODUCTS_MAP__[p.id] = {
      id: p.id,
      name: p.name,
      price: p.price,
      image: p.imageUrl || '',
      category: p.category,
      ownerType: p.ownerType,
      stock: p.stock,
      desc: p.desc,
      badge: badgeText
    };

    const imgHtml = p.imageUrl
      ? `<img src="${escapeHtml(p.imageUrl)}" alt="${escapeHtml(p.name)}" loading="lazy">`
      : `<div class="card-product-img card-product-img-placeholder ${isFood ? 'ph-food' : 'ph-drink'}" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:2.2rem;"><span>${isFood ? '🍲' : '🥤'}</span></div>`;

    return `
      <div class="rec-card" style="cursor:pointer;" onclick="openPrefProductPreview(event, ${p.id})">
        <div class="rec-card-img-wrap">
          <span class="rec-card-badge">${escapeHtml(badgeText)}</span>
          <span class="rec-card-sales-pill">${escapeHtml(p.category || (isFood ? 'Makanan' : 'Minuman'))}</span>
          ${imgHtml}
        </div>
        <div class="rec-card-body">
          <div>
            <div class="rec-card-title">${escapeHtml(p.name)}</div>
            <div class="rec-card-desc">${escapeHtml(p.desc || '')}</div>
          </div>
          <div class="rec-card-footer">
            <div class="rec-card-price">${formatRupiah(p.price)}</div>
            <button type="button" class="rec-card-btn" onclick="addPrefProductToCart(event, ${p.id})">
              + Tambah
            </button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  // Animate grid smoothly on update
  grid.classList.remove('pref-fade-in');
  void grid.offsetWidth;
  grid.classList.add('pref-fade-in');

  section.style.display = 'block';

  if (shouldScroll) {
    setTimeout(() => {
      section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 120);
    showToast('🎯 Rekomendasi selera Anda telah diperbarui!');
  }
}

function openPrefProductPreview(event, id) {
  if (event && event.target && event.target.closest('button')) return;
  const p = window.__PREF_PRODUCTS_MAP__[id];
  if (p) openProductPreview(event, p);
}

function addPrefProductToCart(event, id) {
  if (event) event.stopPropagation();
  const p = window.__PREF_PRODUCTS_MAP__[id];
  if (p) {
    addToCart(p.id, p.name, p.price, p.image, p.category);
  }
}
