let cart = {}; // { productId: { id, name, price, qty, image, category_name } }
let activeCategory = 'all';
let checkoutInProgress = false;

function initCustomerMenu() {
  renderCartBadge();
  watchConnection();
  applyReorderFromUrl();
  initAssistant();
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
    showToast(`${added} menu dimuat lagi ke keranjang`, 'repeat');
  }

  if (missing.length > 0) {
    setTimeout(() => {
      showToast(`${missing.length} menu sudah tidak tersedia, dilewati`, 'alert-triangle');
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
      document.body.classList.add('has-cart-bar');
      countEl.innerText = `${count} Menu Dipilih`;
      amountEl.innerText = formatRupiah(total);
      if (bubble) bubble.innerText = count;
    } else {
      bar.style.display = 'none';
      document.body.classList.remove('has-cart-bar');
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
        ${icon('shopping-cart', 'icon-empty-state')}
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
  el.innerHTML = message ? iconText(ok ? 'check-circle' : 'x-circle', message) : '';
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
    setPromoStatus(`${data.name}: hemat ${formatRupiah(data.discount)}`, true);
  } catch (err) {
    appliedPromo = null;
    btn.innerText = 'Pakai';
    setPromoStatus(err.message, false);
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
      setPromoStatus(`${data.name}: hemat ${formatRupiah(data.discount)}`, true);
    } catch (err) {
      const code = appliedPromo.code;
      removePromoCode();
      setPromoStatus(`Kupon ${code} dilepas: ${err.message}`, false);
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
      submitBtn.innerHTML = 'Kirim Pesanan Sekarang ' + icon('send');
      checkoutInProgress = false;
    }
  } catch (err) {
    alert('Terjadi kesalahan koneksi server: ' + err.message);
    submitBtn.disabled = false;
    submitBtn.innerHTML = 'Kirim Pesanan Sekarang ' + icon('send');
    checkoutInProgress = false;
  }
}

function formatRupiah(number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
}

function showToast(msg, iconName = 'sparkles') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `${icon(iconName)} <span>${msg}</span>`;
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
      : `<div class="card-product-img-placeholder ${meta.isFood ? 'ph-food' : 'ph-drink'}" style="width:100%; height:100%;"><span class="ph-icon">${icon(meta.isFood ? 'soup' : 'cup-soda')}</span></div>`;
    imgWrap.innerHTML = `
      ${imgHtml}
      <span class="brand-badge ${meta.isFood ? 'badge-food' : 'badge-drink'} img-badge">${escapeHtml(product.category || '')}</span>
      <button type="button" class="preview-close-btn" onclick="closeProductPreview()" aria-label="Tutup"></button>
    `;
  }

  document.getElementById('previewTitle').innerText = product.name;
  document.getElementById('previewRating').innerHTML = `<span class="pv-star">${icon('star')}</span> ${meta.rating} &middot; ${meta.reviews}+ ulasan${product.badge ? ' &middot; ' + escapeHtml(product.badge) : ''}`;
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
   Asisten Menu (chat) — rekomendasi sesuai selera pelanggan.
   Berjalan sepenuhnya di browser dari katalog yang sudah dimuat halaman,
   jadi tetap berfungsi offline dan tidak mengirim data ke layanan luar.
   ========================================================================== */

const PREF_KEYWORDS = {
  pedas: ['pedas', 'sambal', 'cabe', 'cabai', 'rawit', 'rujak'],
  gurih: ['gurih', 'crispy', 'krispi', 'rempah', 'truffle', 'fries', 'seafood', 'bakar', 'goreng', 'asin', 'buntut', 'keju'],
  manis: ['manis', 'madu', 'karamel', 'caramel', 'rujak'],
  hangat: ['sop', 'buntut', 'kuah', 'hangat', 'rempah', 'hot', 'mie', 'nasi'],
  segar: ['segar', 'mocktail', 'sparkler', 'lemon', 'mint', 'dingin', 'jus', 'soda', 'iced', 'cold', 'es kopi', 'es teh'],
  kopi: ['kopi', 'espresso', 'americano', 'macchiato', 'cold brew', 'coffee'],
  creamy: ['creamy', 'susu', 'latte', 'alpukat', 'matcha', 'cheese', 'keju'],
  buah: ['jus', 'buah', 'mangga', 'alpukat', 'berry', 'lemon']
};

const PREF_LABELS = {
  pedas: 'Pedas',
  gurih: 'Gurih / Crispy',
  manis: 'Manis',
  hangat: 'Kuah Hangat',
  segar: 'Dingin & Segar',
  kopi: 'Kopi',
  creamy: 'Creamy & Susu',
  buah: 'Jus Buah'
};

const PREF_ICONS = {
  pedas: 'flame', gurih: 'drumstick', manis: 'candy', hangat: 'soup',
  segar: 'snowflake', kopi: 'coffee', creamy: 'milk', buah: 'citrus'
};

const FOOD_PREFS = new Set(['pedas', 'gurih', 'manis', 'hangat']);
const DRINK_PREFS = new Set(['segar', 'kopi', 'creamy', 'buah']);

// Kata yang mungkin diketik pelanggan, dipetakan ke tag selera.
const PREF_SYNONYMS = {
  pedas: ['pedas', 'pedes', 'sambal', 'cabe', 'cabai', 'spicy'],
  gurih: ['gurih', 'crispy', 'krispi', 'renyah', 'goreng', 'asin', 'savory'],
  manis: ['manis', 'madu', 'sweet', 'legit'],
  hangat: ['hangat', 'kuah', 'berkuah', 'sop', 'sup', 'soto', 'panas'],
  segar: ['segar', 'dingin', 'es', 'fresh', 'mocktail', 'soda', 'seger'],
  kopi: ['kopi', 'coffee', 'espresso', 'americano', 'ngopi'],
  creamy: ['creamy', 'susu', 'milk', 'latte', 'matcha'],
  buah: ['jus', 'buah', 'juice', 'mangga', 'alpukat', 'berry']
};

const ASSISTANT_INTENTS = {
  food: ['makan', 'makanan', 'lapar', 'laper', 'food'],
  drink: ['minum', 'minuman', 'haus', 'drink'],
  bestseller: ['terlaris', 'laris', 'favorit', 'populer', 'best', 'andalan', 'terlaku'],
  cart: ['keranjang', 'checkout', 'bayar'],
  thanks: ['makasih', 'terima kasih', 'thanks', 'thank', 'thx', 'tengkyu'],
  greeting: ['halo', 'hai', 'hi', 'hello', 'pagi', 'siang', 'sore', 'malam', 'permisi']
};

// Kata umum yang tidak dipakai untuk mencari nama menu.
const ASSISTANT_STOPWORDS = new Set([
  'mau', 'ingin', 'pengen', 'pingin', 'yang', 'dong', 'deh', 'ada', 'apa', 'saja', 'aja', 'menu',
  'rekomendasi', 'rekomen', 'saran', 'tolong', 'carikan', 'cari', 'kak', 'mas', 'mbak', 'buat',
  'untuk', 'saya', 'aku', 'gue', 'yg', 'enak', 'paling', 'nya', 'sama', 'atau', 'dan', 'pakai', 'pake'
]);

let assistantProducts = {};
let assistantFlow = { scope: null, foodTag: null };
let assistantBusy = false;
let assistantStarted = false;

function getCatalogProducts() {
  return Array.isArray(window.__ALL_CATALOG_PRODUCTS__) ? window.__ALL_CATALOG_PRODUCTS__ : [];
}

function isFoodProduct(p) {
  return p.ownerType === 'makanan';
}

function scoreProducts(productList, tags) {
  const scored = [];
  productList.forEach(product => {
    let score = 0;
    const matchedTags = [];
    const name = (product.name || '').toLowerCase();
    const desc = (product.desc || '').toLowerCase();
    const cat = (product.category || '').toLowerCase();

    tags.forEach(tag => {
      let hit = false;
      (PREF_KEYWORDS[tag] || [tag]).forEach(kw => {
        if (name.includes(kw)) { score += 6; hit = true; }
        if (desc.includes(kw)) { score += 3; hit = true; }
        if (cat.includes(kw)) { score += 1.5; }
      });
      if (hit) matchedTags.push(tag);
    });

    // "kopi dingin" should rank a cold coffee above a coffee or a cold drink alone.
    score += Math.max(0, matchedTags.length - 1) * 10;

    if (score > 0) {
      const reason = matchedTags.map(t => PREF_LABELS[t]).join(' + ');
      scored.push({ product, score, reason: reason || (isFoodProduct(product) ? 'Menu Pilihan' : 'Minuman Pilihan') });
    }
  });
  return scored.sort((a, b) => b.score - a.score);
}

/**
 * @param {'food'|'drink'|'both'|null} scope
 * @param {string[]} tags
 * @returns {{items: {product: object, reason: string}[], matched: boolean}}
 */
function recommendProducts(scope, tags) {
  const available = getCatalogProducts().filter(p => (p.stock || 0) > 0);
  const foods = available.filter(isFoodProduct);
  const drinks = available.filter(p => !isFoodProduct(p));
  const foodTags = tags.filter(t => FOOD_PREFS.has(t));
  const drinkTags = tags.filter(t => DRINK_PREFS.has(t));
  let matched = true;

  const pick = (list, tagsFor, count, fallbackReason) => {
    const scored = tagsFor.length ? scoreProducts(list, tagsFor) : [];
    if (scored.length) return scored.slice(0, count);
    if (tagsFor.length) matched = false;
    return list.slice(0, count).map(product => ({ product, reason: fallbackReason }));
  };

  const wantFood = scope === 'food' || scope === 'both' || (!scope && foodTags.length > 0);
  const wantDrink = scope === 'drink' || scope === 'both' || (!scope && drinkTags.length > 0);

  let items;
  if (wantFood && wantDrink) {
    items = [...pick(foods, foodTags, 2, 'Menu Pilihan'), ...pick(drinks, drinkTags, 2, 'Minuman Pilihan')];
  } else if (wantFood) {
    items = pick(foods, foodTags, 4, 'Menu Pilihan');
  } else if (wantDrink) {
    items = pick(drinks, drinkTags, 4, 'Minuman Pilihan');
  } else {
    items = [...pick(foods, [], 2, 'Rekomendasi Spesial'), ...pick(drinks, [], 2, 'Rekomendasi Spesial')];
  }
  return { items, matched };
}

function bestsellerProducts() {
  const ids = Array.isArray(window.__BESTSELLER_IDS__) ? window.__BESTSELLER_IDS__ : [];
  const catalog = getCatalogProducts();
  const seen = new Set();
  return ids
    .map(id => catalog.find(p => p.id === id))
    .filter(p => p && !seen.has(p.id) && seen.add(p.id))
    .map(product => ({ product, reason: 'Terlaris' }));
}

function parseAssistantText(text) {
  const t = text.toLowerCase();
  const words = t.split(/[^a-z0-9]+/).filter(Boolean);
  // Short words must match a whole word ("es" must not match "espresso").
  const has = (term) => (term.includes(' ') || term.length > 4 ? t.includes(term) : words.includes(term));
  const any = (terms) => terms.some(has);

  const tags = Object.keys(PREF_SYNONYMS).filter(tag => any(PREF_SYNONYMS[tag]));
  const knownWords = new Set([...Object.values(PREF_SYNONYMS).flat(), ...Object.values(ASSISTANT_INTENTS).flat()]);
  const nameTerms = words.filter(w => w.length >= 3 && !ASSISTANT_STOPWORDS.has(w) && !knownWords.has(w));
  const nameMatches = nameTerms.length
    ? getCatalogProducts().filter(p => nameTerms.some(w => (p.name || '').toLowerCase().includes(w)))
    : [];

  return {
    tags,
    nameMatches,
    food: any(ASSISTANT_INTENTS.food),
    drink: any(ASSISTANT_INTENTS.drink),
    bestseller: any(ASSISTANT_INTENTS.bestseller),
    cart: any(ASSISTANT_INTENTS.cart),
    thanks: any(ASSISTANT_INTENTS.thanks),
    greeting: any(ASSISTANT_INTENTS.greeting)
  };
}

/* ---------- Panel ---------- */

function initAssistant() {
  try {
    if (sessionStorage.getItem('taman_assistant_teased')) return;
  } catch (e) { /* storage blocked: still show the hint once per page */ }

  // A small, dismissible hint next to the button — never a blocking popup.
  setTimeout(() => {
    const panel = document.getElementById('assistantPanel');
    const teaser = document.getElementById('assistantTeaser');
    if (!teaser || (panel && !panel.hidden)) return;
    teaser.hidden = false;
    setTimeout(() => { teaser.hidden = true; }, 9000);
    try { sessionStorage.setItem('taman_assistant_teased', '1'); } catch (e) {}
  }, 4000);
}

function dismissAssistantTeaser() {
  const teaser = document.getElementById('assistantTeaser');
  if (teaser) teaser.hidden = true;
}

function openAssistant() {
  const panel = document.getElementById('assistantPanel');
  const fab = document.getElementById('assistantFab');
  if (!panel) return;
  dismissAssistantTeaser();
  panel.hidden = false;
  document.body.classList.add('assistant-open');
  if (fab) fab.setAttribute('aria-expanded', 'true');
  if (!assistantStarted) {
    assistantStarted = true;
    assistantGreet();
  }
  if (window.matchMedia('(min-width: 601px)').matches) {
    document.getElementById('assistantInput')?.focus();
  }
}

function closeAssistant() {
  const panel = document.getElementById('assistantPanel');
  const fab = document.getElementById('assistantFab');
  if (panel) panel.hidden = true;
  document.body.classList.remove('assistant-open');
  if (fab) {
    fab.setAttribute('aria-expanded', 'false');
    fab.focus();
  }
}

function resetAssistant() {
  if (assistantBusy) return;
  const list = document.getElementById('assistantMessages');
  if (list) list.innerHTML = '';
  assistantFlow = { scope: null, foodTag: null };
  assistantGreet();
}

/* ---------- Messages ---------- */

function assistantScroll() {
  const list = document.getElementById('assistantMessages');
  if (list) list.scrollTop = list.scrollHeight;
}

function assistantAppend(html, from) {
  const list = document.getElementById('assistantMessages');
  if (!list) return null;
  const row = document.createElement('div');
  row.className = `assistant-msg assistant-msg-${from}`;
  row.innerHTML = from === 'bot'
    ? `<span class="assistant-msg-avatar">${icon('leaf')}</span><div class="assistant-bubble">${html}</div>`
    : `<div class="assistant-bubble">${html}</div>`;
  list.appendChild(row);
  assistantScroll();
  return row;
}

function assistantUserSays(text) {
  assistantAppend(escapeHtml(text), 'user');
}

/** Show a short typing indicator, then the bot's reply and its quick replies. */
function assistantBotSays(html, quickReplies = [], extraHtml = '') {
  assistantBusy = true;
  setAssistantQuickReplies([]);
  const typing = assistantAppend('<span class="assistant-typing" aria-label="Mengetik"><i></i><i></i><i></i></span>', 'bot');
  setTimeout(() => {
    if (typing) typing.remove();
    assistantAppend(html + extraHtml, 'bot');
    setAssistantQuickReplies(quickReplies);
    assistantBusy = false;
  }, 450);
}

function setAssistantQuickReplies(replies) {
  const box = document.getElementById('assistantQuick');
  if (!box) return;
  box.innerHTML = '';
  replies.forEach(reply => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'assistant-chip';
    btn.innerHTML = `${reply.icon ? icon(reply.icon) : ''}<span>${escapeHtml(reply.label)}</span>`;
    btn.addEventListener('click', () => {
      if (assistantBusy) return;
      assistantUserSays(reply.label);
      reply.action();
    });
    box.appendChild(btn);
  });
  box.hidden = replies.length === 0;
  assistantScroll();
}

/* ---------- Conversation ---------- */

function assistantStartReplies() {
  return [
    { label: 'Cari makanan', icon: 'soup', action: () => assistantAskTaste('food') },
    { label: 'Cari minuman', icon: 'cup-soda', action: () => assistantAskTaste('drink') },
    { label: 'Makanan + minuman', icon: 'utensils', action: () => assistantAskTaste('both') },
    { label: 'Menu terlaris', icon: 'star', action: assistantShowBestsellers }
  ];
}

function assistantAfterResultReplies() {
  const replies = [{ label: 'Cari lagi', icon: 'refresh', action: assistantAskAgain }];
  if (Object.keys(cart).length > 0) {
    replies.push({ label: 'Lihat keranjang', icon: 'shopping-cart', action: assistantOpenCart });
  }
  replies.push({ label: 'Menu terlaris', icon: 'star', action: assistantShowBestsellers });
  return replies;
}

function assistantGreet() {
  assistantBotSays(
    'Halo! Saya asisten menu Taman Cafe. Mau saya bantu carikan menu yang pas dengan selera Anda?'
      + '<span class="assistant-hint">Pilih di bawah, atau ketik saja, mis. <em>"yang pedas"</em> atau <em>"kopi dingin"</em>.</span>',
    assistantStartReplies()
  );
}

function assistantAskAgain() {
  assistantFlow = { scope: null, foodTag: null };
  assistantBotSays('Baik, mau cari apa lagi?', assistantStartReplies());
}

function tasteReplies(tags, onPick) {
  return [
    ...tags.map(tag => ({ label: PREF_LABELS[tag], icon: PREF_ICONS[tag], action: () => onPick(tag) })),
    { label: 'Apa saja', icon: 'sparkles', action: () => onPick(null) }
  ];
}

function assistantAskTaste(scope) {
  assistantFlow = { scope, foodTag: null };
  if (scope === 'drink') {
    assistantBotSays('Minuman seperti apa yang Anda mau?',
      tasteReplies([...DRINK_PREFS], tag => assistantShowResults('drink', tag ? [tag] : [])));
    return;
  }
  assistantBotSays('Selera makanan apa yang sedang Anda cari?', tasteReplies([...FOOD_PREFS], tag => {
    if (scope === 'both') {
      assistantFlow.foodTag = tag;
      assistantBotSays('Lalu minumannya mau yang seperti apa?', tasteReplies([...DRINK_PREFS], drinkTag => {
        assistantShowResults('both', [assistantFlow.foodTag, drinkTag].filter(Boolean));
      }));
      return;
    }
    assistantShowResults('food', tag ? [tag] : []);
  }));
}

function assistantProductCards(items) {
  return `<div class="assistant-products">${items.map(({ product: p, reason }) => {
    const isFood = isFoodProduct(p);
    assistantProducts[p.id] = {
      id: p.id, name: p.name, price: p.price, image: p.imageUrl || '', category: p.category,
      ownerType: p.ownerType, stock: p.stock, desc: p.desc
    };
    const img = p.imageUrl
      ? `<img src="${escapeHtml(p.imageUrl)}" alt="" loading="lazy">`
      : `<span class="assistant-product-ph ${isFood ? 'ph-food' : 'ph-drink'}">${icon(isFood ? 'soup' : 'cup-soda')}</span>`;
    return `
      <div class="assistant-product" role="button" tabindex="0"
           onclick="openAssistantProduct(event, ${p.id})" onkeydown="if (event.key === 'Enter') openAssistantProduct(event, ${p.id})">
        <div class="assistant-product-img">${img}</div>
        <div class="assistant-product-info">
          <div class="assistant-product-name">${escapeHtml(p.name)}</div>
          <div class="assistant-product-reason">${escapeHtml(reason)}</div>
          <div class="assistant-product-price">${formatRupiah(p.price)}</div>
        </div>
        <button type="button" class="assistant-add" onclick="assistantAddToCart(event, ${p.id})" aria-label="Tambah ${escapeHtml(p.name)} ke keranjang">${icon('plus')}</button>
      </div>`;
  }).join('')}</div>`;
}

function assistantShowResults(scope, tags) {
  const { items, matched } = recommendProducts(scope, tags);
  if (!items.length) {
    assistantBotSays('Maaf, menu untuk pilihan itu sedang habis. Mau coba yang lain?', assistantStartReplies());
    return;
  }
  const labels = tags.map(t => PREF_LABELS[t]).filter(Boolean);
  const intro = !matched
    ? 'Belum ada menu yang benar-benar cocok, tapi ini pilihan lain yang bisa dicoba:'
    : labels.length
      ? `Untuk selera <strong>${escapeHtml(labels.join(' + '))}</strong>, ini yang saya sarankan:`
      : 'Ini beberapa pilihan favorit kami:';
  assistantBotSays(intro, assistantAfterResultReplies(), assistantProductCards(items));
}

function assistantShowBestsellers() {
  const items = bestsellerProducts();
  if (!items.length) {
    assistantShowResults(null, []);
    return;
  }
  assistantBotSays('Ini menu yang paling sering dipesan pelanggan bulan ini:', assistantAfterResultReplies(), assistantProductCards(items));
}

function assistantOpenCart() {
  closeAssistant();
  openCartModal();
}

function submitAssistantText(event) {
  event.preventDefault();
  const input = document.getElementById('assistantInput');
  const text = (input?.value || '').trim();
  if (!text || assistantBusy) return;
  input.value = '';
  assistantUserSays(text);

  const intent = parseAssistantText(text);

  if (intent.thanks && !intent.tags.length && !intent.nameMatches.length) {
    assistantBotSays('Sama-sama! Selamat menikmati. Ada lagi yang bisa saya bantu?', assistantAfterResultReplies());
  } else if (intent.cart) {
    if (Object.keys(cart).length > 0) {
      assistantBotSays('Baik, saya bukakan keranjang Anda.', []);
      setTimeout(assistantOpenCart, 900);
    } else {
      assistantBotSays('Keranjang Anda masih kosong. Mau saya carikan menu dulu?', assistantStartReplies());
    }
  } else if (intent.nameMatches.length) {
    const items = intent.nameMatches.slice(0, 4).map(product => ({ product, reason: product.category || 'Menu' }));
    assistantBotSays('Ini menu yang sesuai dengan yang Anda cari:', assistantAfterResultReplies(), assistantProductCards(items));
  } else if (intent.tags.length || intent.food || intent.drink) {
    const scope = intent.food && intent.drink ? 'both' : intent.food ? 'food' : intent.drink ? 'drink' : null;
    if (!intent.tags.length) {
      assistantAskTaste(scope);
    } else {
      assistantShowResults(scope, intent.tags);
    }
  } else if (intent.bestseller) {
    assistantShowBestsellers();
  } else if (intent.greeting) {
    assistantBotSays('Halo juga! Mau cari makanan, minuman, atau keduanya?', assistantStartReplies());
  } else {
    assistantBotSays(
      'Maaf, saya belum menangkap maksudnya. Coba sebut selera Anda, misalnya <em>pedas</em>, <em>manis</em>, <em>kopi</em>, atau <em>jus buah</em>.',
      assistantStartReplies()
    );
  }
}

function openAssistantProduct(event, id) {
  if (event && event.target && event.target.closest('button')) return;
  const p = assistantProducts[id];
  if (p) openProductPreview(event, p);
}

function assistantAddToCart(event, id) {
  if (event) event.stopPropagation();
  const p = assistantProducts[id];
  if (!p) return;
  addToCart(p.id, p.name, p.price, p.image, p.category);
  const btn = event?.currentTarget;
  if (btn) {
    btn.innerHTML = icon('check');
    btn.classList.add('is-added');
    setTimeout(() => { btn.innerHTML = icon('plus'); btn.classList.remove('is-added'); }, 1400);
  }
}
