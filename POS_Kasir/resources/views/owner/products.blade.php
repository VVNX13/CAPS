@extends('layouts.app')

@section('title', 'Kelola Menu ' . $scopeLabel . ' - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current' => 'products',
        'title'   => 'Kelola Menu',
    ])

    <main class="container">
        <!-- Page Header + Toolbar -->
        <div class="stk-page-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; padding-top:4px;">
            <div>
                <div style="font-size:1.3rem; font-weight:800; color:var(--text-main,#0f172a); letter-spacing:-0.02em;">Kelola Menu</div>
                <div style="font-size:0.8rem; color:var(--text-muted,#64748b); margin-top:3px; font-weight:500;">{{ $scopeLabel }} — atur produk, stok harian &amp; visibilitas menu</div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openProductModal()" style="display:flex; align-items:center; gap:7px; padding:8px 16px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Tambah Menu
            </button>
        </div>

        <!-- Toolbar: Search + Filter -->
        <div class="card p-3 mb-4" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="font-weight:700; font-size:0.9rem; color:var(--text-muted,#64748b); letter-spacing:0.02em; text-transform:uppercase; font-size:0.72rem;">
                Daftar Menu &amp; Stok — {{ $scopeLabel }}
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <div style="position:relative;">
                    <svg style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="ownerProductSearch" class="form-control" placeholder="Cari nama menu..." oninput="filterOwnerProducts()" style="padding-left:34px; max-width:220px;">
                </div>
                <select id="stockFilter" class="form-select" style="max-width:140px;" onchange="filterOwnerProducts()">
                    <option value="all">Semua Stok</option>
                    <option value="low">Stok Kritis (≤5)</option>
                </select>
            </div>
        </div>

        <!-- Products Container -->
        <div id="productGridList" class="owner-products-grid"></div>
    </main>

    <!-- Modal Form Add/Edit Product -->
    <div class="modal-overlay" id="productModal" onclick="if(event.target.id==='productModal') closeProductModal()">
        <div class="modal-box modal-bottom-sheet" onclick="event.stopPropagation()">
            <div class="modal-sheet-handle"></div>
            <div class="modal-header">
                <h3 id="modalProductTitle" style="font-weight:800; font-size:1.15rem; color:#0f172a;">Tambah Menu Baru</h3>
                <button class="btn btn-outline modal-close-btn" onclick="closeProductModal()">&times;</button>
            </div>
            <form onsubmit="saveProduct(event)">
                <div class="modal-body">
                    <input type="hidden" id="prod_id">

                    <div class="form-group">
                        <label class="form-label" for="prod_category">Kategori</label>
                        <select id="prod_category" class="form-control" required></select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prod_name">Nama Menu</label>
                        <input type="text" id="prod_name" class="form-control" placeholder="Contoh: Nasi Goreng Spesial" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prod_desc">Deskripsi Menu <span class="form-hint" style="display:inline;">(opsional)</span></label>
                        <textarea id="prod_desc" class="form-control" rows="2" placeholder="Contoh: Nasi ayam geprek, sambal melingkup"></textarea>
                        <small class="form-hint">Kalau dikosongkan, tidak ada teks yang muncul di kartu menu.</small>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label" for="prod_price">Harga (Rp)</label>
                            <input type="number" id="prod_price" class="form-control" min="0" placeholder="25000" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="prod_stock">Stok Harian</label>
                            <input type="number" id="prod_stock" class="form-control" min="0" value="50" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prod_photo">Foto Menu</label>
                        <input type="file" id="prod_photo" class="form-control"
                               accept="image/jpeg,image/png,image/webp" onchange="previewPhoto(this)">
                        <small class="form-hint">JPG/PNG/WebP, maksimal 4 MB. Foto otomatis dikecilkan jadi 900px. Kalau kosong, menu menampilkan ikon kategori.</small>
                    </div>

                    <div style="display:flex; gap:14px; align-items:flex-start; margin-bottom:14px;">
                        <div id="photoPreviewBox"
                             style="width:96px; height:96px; border-radius:12px; border:1px solid #e2e8f0; background:#f1f5f9; overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div id="photoCurrentNote" style="font-size:0.75rem; color:#94a3b8; margin-bottom:6px;"></div>
                            <label style="display:flex; align-items:center; gap:6px; font-size:0.8rem; color:#64748b; cursor:pointer;">
                                <input type="checkbox" id="prod_remove_photo" style="width:auto;" onchange="syncPhotoPreview()"> Hapus foto yang ada
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prod_image">Atau pakai URL Foto (opsional)</label>
                        <input type="url" id="prod_image" class="form-control" placeholder="https://..." oninput="syncPhotoPreview()">
                        <small class="form-hint">Kalau upload file di atas, URL ini diabaikan.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status Menu</label>
                        <div style="display:flex; gap:16px; margin-top:4px;">
                            <label style="display:flex; align-items:center; gap:7px; cursor:pointer; font-size:0.9rem;">
                                <input type="radio" name="prod_is_active" id="prod_active_yes" value="1" checked
                                       style="width:auto; accent-color:#2f7a4d;">
                                <span style="color:#2f7a4d; font-weight:600;">Aktif</span>
                                <span style="color:#64748b; font-size:0.8rem;">(tampil ke pelanggan)</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:7px; cursor:pointer; font-size:0.9rem;">
                                <input type="radio" name="prod_is_active" id="prod_active_no" value="0"
                                       style="width:auto; accent-color:#ad3e39;">
                                <span style="color:#ad3e39; font-weight:600;">Nonaktif</span>
                                <span style="color:#64748b; font-size:0.8rem;">(disembunyikan)</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeProductModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" style="flex:1;">Simpan Menu</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const productsUrl = @js(route('owner.products.index'));
        const productsSaveUrl = @js(route('owner.products.save'));
        const productsToggleUrl = @js(route('owner.products.toggle'));
        const productsDeleteUrl = @js(url('/api/owner/products'));

        let categoriesList = [];
        let productsList = [];
        let currentProduct = null;

        function escapeHtml(value) {
            return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, ch => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[ch]));
        }

        function showToast(message) {
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.className = 'toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg> <span>${escapeHtml(message)}</span>`;
            container.appendChild(toast);

            setTimeout(() => toast.remove(), 3500);
        }

        // Same fallback the customer menu draws, so the owner sees exactly what
        // the customer will see instead of an unrelated stock photo.
        function productFallback(product) {
            const food = !product || product.owner_type !== 'minuman';
            const bg   = food ? 'linear-gradient(135deg,#fff7ed,#fed7aa)' : 'linear-gradient(135deg,#ecfeff,#a5f3fc)';
            const icon = food
                ? `<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#c2410c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l1.5 10h15L21 2"/><path d="M5 12v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"/><path d="M9 7h6"/></svg>`
                : `<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 0 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V8z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>`;
            return `<div class="prod-cover-fallback" style="background:${bg};">${icon}</div>`;
        }

        function productCover(product) {
            if (product && product.image_url) {
                return `<img src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.name)}"
                             style="width:100%;height:100%;object-fit:cover;"
                             onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                        <div style="display:none;">${productFallback(product)}</div>`;
            }
            return productFallback(product);
        }

        function updatePhotoPreview(src, note) {
            const box = document.getElementById('photoPreviewBox');
            const noteEl = document.getElementById('photoCurrentNote');
            if (!box) return;

            if (src) {
                box.innerHTML = `<img src="${escapeHtml(src)}" style="width:100%;height:100%;object-fit:cover;" alt="preview">`;
            } else {
                const food = !currentProduct || currentProduct.owner_type !== 'minuman';
                const icon = food
                    ? `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#c2410c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2l1.5 10h15L21 2"/><path d="M5 12v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"/><path d="M9 7h6"/></svg>`
                    : `<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 0 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V8z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>`;
                box.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f8fafc;">${icon}</div>`;
            }

            if (noteEl) noteEl.textContent = note || '';
        }

        function previewPhoto(input) {
            const file = input.files && input.files[0];
            if (!file) return;

            // Guarded client side as well: a 12 MB phone photo should not travel
            // over cafe wifi only to be rejected by the server.
            if (file.size > 4 * 1024 * 1024) {
                alert('Ukuran foto ' + (file.size / 1048576).toFixed(1) + ' MB, maksimal 4 MB.');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = e => {
                updatePhotoPreview(e.target.result, 'Foto baru akan dipakai: ' + file.name);
                document.getElementById('prod_remove_photo').checked = false;
            };
            reader.readAsDataURL(file);
        }

        function syncPhotoPreview() {
            const url = document.getElementById('prod_image').value.trim();
            const remove = document.getElementById('prod_remove_photo').checked;

            if (remove) {
                updatePhotoPreview(null, 'Foto akan dihapus');

                return;
            }

            if (url) {
                updatePhotoPreview(url, 'Mengakai URL foto');
            } else if (currentProduct && currentProduct.image_url && !currentProduct.image_external) {
                updatePhotoPreview(currentProduct.image_url, 'Foto saat ini (file), tidak berubah');
            } else {
                updatePhotoPreview(null, 'Tanpa foto, menu memakai ikon kategori');
            }
        }

        async function loadProducts() {
            try {
                const res = await fetch(productsUrl);
                const data = await res.json();
                if (data.success) {
                    categoriesList = data.data.categories;
                    productsList = data.data.products;
                    renderProducts();
                }
            } catch (e) {
                console.error(e);
            }
        }

        function filterOwnerProducts() {
            const filter = document.getElementById('stockFilter')?.value || 'all';
            renderProducts(filter);
        }

        function renderProducts(filter = 'all') {
            const container = document.getElementById('productGridList');
            const search = (document.getElementById('ownerProductSearch')?.value || '').toLowerCase().trim();

            const filtered = productsList.filter(p => !search || p.name.toLowerCase().includes(search) || p.category_name.toLowerCase().includes(search));

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding:48px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; grid-column:1/-1;">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 10px; display:block;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <div style="font-size:0.9rem; font-weight:600; color:#94a3b8;">Belum ada menu yang cocok.</div>
                    </div>`;
                return;
            }

            /* SVG icon helpers */
            const icoCheck = `<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
            const icoOff   = `<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
            const icoEdit  = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`;
            const icoDel   = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>`;
            const icoBox   = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>`;

            let html = '';
            filtered.forEach(p => {
                const isActive   = p.is_active == 1;
                const isFood     = p.owner_type !== 'minuman';
                const stockLow   = p.stock <= 5;
                const canDelete  = p.sold_count == 0;

                html += `
                <div class="card owner-prod-card">

                    <!-- Status ribbon -->
                    <div class="prod-status-ribbon ${isActive ? 'active' : 'inactive'}">
                        ${isActive ? icoCheck : icoOff}
                        ${isActive ? 'Aktif' : 'Nonaktif'}
                    </div>

                    <!-- Cover -->
                    <div class="prod-cover">
                        ${productCover(p)}
                    </div>

                    <!-- Body -->
                    <div class="prod-body">
                        <div class="prod-category-badge ${isFood ? 'food' : 'drink'}">
                            ${p.category_name}
                        </div>
                        <div class="prod-name">${escapeHtml(p.name)}</div>
                        <div class="prod-price">${formatRupiah(p.price)}</div>
                    </div>

                    <!-- Stock row -->
                    <div class="prod-stock-row">
                        ${icoBox}
                        <span>Stok:</span>
                        <span class="prod-stock-val ${stockLow ? 'low' : ''}">${p.stock}</span>
                        ${stockLow ? `<span style="font-size:0.68rem; color:#ad3e39; font-weight:700; margin-left:2px;">— Kritis</span>` : ''}
                    </div>

                    <!-- Action bar -->
                    <div class="prod-actions">
                        <button class="prod-action-btn ${isActive ? 'toggle-active' : 'toggle-inactive'}"
                                onclick="toggleActive(${p.id}, ${isActive ? 0 : 1})">
                            ${isActive ? icoCheck : icoOff}
                            ${isActive ? 'Aktif' : 'Nonaktif'}
                        </button>
                        <button class="prod-action-btn edit-btn" onclick="editProduct(${p.id})">
                            ${icoEdit} Edit
                        </button>
                        <button class="prod-action-btn delete-btn"
                                onclick="deleteProduct(${p.id})"
                                title="${canDelete ? 'Hapus menu ini' : 'Sudah pernah terjual, tidak bisa dihapus'}"
                                ${canDelete ? '' : 'style="opacity:.35; cursor:not-allowed;"'}>
                            ${icoDel}
                        </button>
                    </div>
                </div>`;
            });
            container.innerHTML = html;
        }

        function openProductModal() {
            document.getElementById('modalProductTitle').innerText = 'Tambah Menu Baru';
            document.getElementById('prod_id').value = '';
            document.getElementById('prod_name').value = '';
            document.getElementById('prod_desc').value = '';
            document.getElementById('prod_price').value = '';
            document.getElementById('prod_stock').value = '50';
            document.getElementById('prod_image').value = '';
            document.getElementById('prod_photo').value = '';
            document.getElementById('prod_remove_photo').checked = false;
            currentProduct = null;

            // Default: Aktif saat buat baru
            document.getElementById('prod_active_yes').checked = true;

            const catSelect = document.getElementById('prod_category');
            catSelect.innerHTML = categoriesList.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

            updatePhotoPreview(null, 'Tanpa foto, menu memakai ikon kategori');
            document.getElementById('productModal').classList.add('active');
        }

        function editProduct(id) {
            const p = productsList.find(item => item.id == id);
            if (!p) return;

            document.getElementById('modalProductTitle').innerText = 'Edit Menu: ' + p.name;
            document.getElementById('prod_id').value = p.id;
            document.getElementById('prod_name').value = p.name;
            document.getElementById('prod_desc').value = p.description || '';
            document.getElementById('prod_price').value = p.price;
            document.getElementById('prod_stock').value = p.stock;
            document.getElementById('prod_image').value = p.image_external ? (p.image || '') : '';
            document.getElementById('prod_photo').value = '';
            document.getElementById('prod_remove_photo').checked = false;
            currentProduct = p;

            // Set status aktif/nonaktif sesuai data produk
            const activeRadio = document.querySelector(`input[name="prod_is_active"][value="${p.is_active ? '1' : '0'}"]`);
            if (activeRadio) activeRadio.checked = true;

            const catSelect = document.getElementById('prod_category');
            catSelect.innerHTML = categoriesList.map(c => `<option value="${c.id}" ${c.id == p.category_id ? 'selected' : ''}>${c.name}</option>`).join('');

            syncPhotoPreview();
            document.getElementById('productModal').classList.add('active');
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.remove('active');
        }

        async function saveProduct(e) {
            e.preventDefault();

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalLabel = submitBtn.innerText;
            submitBtn.disabled = true;
            submitBtn.innerText = 'Mengunggah...';

            // FormData, not JSON: a JSON body cannot carry a file, and forcing
            // Content-Type: application/json here would strip the multipart
            // boundary the browser needs.
            const form = new FormData();
            form.append('id', document.getElementById('prod_id').value);
            form.append('category_id', document.getElementById('prod_category').value);
            form.append('name', document.getElementById('prod_name').value.trim());
            form.append('description', document.getElementById('prod_desc').value.trim());
            form.append('price', document.getElementById('prod_price').value);
            form.append('stock', document.getElementById('prod_stock').value);
            form.append('image', document.getElementById('prod_image').value.trim());
            form.append('remove_photo', document.getElementById('prod_remove_photo').checked ? '1' : '0');
            form.append('is_active', document.querySelector('input[name="prod_is_active"]:checked')?.value ?? '1');

            const fileInput = document.getElementById('prod_photo');
            if (fileInput.files && fileInput.files[0]) {
                form.append('photo', fileInput.files[0]);
            }

            try {
                const res = await fetch(productsSaveUrl, { method: 'POST', body: form });
                const data = await res.json();

                if (data.success) {
                    closeProductModal();
                    await loadProducts();
                    showToast(data.message || 'Menu tersimpan');
                } else {
                    // Laravel returns 422 with a per-field detail; the generic
                    // message it sends is useless for the person filling the form.
                    const first = data.errors ? Object.values(data.errors).flat()[0] : null;
                    alert('Gagal: ' + (first || data.message || 'tidak diketahui'));
                }
            } catch (err) {
                alert('Gagal: ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = originalLabel;
            }
        }

        async function toggleActive(id, newStatus) {
            await fetch(productsToggleUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, is_active: newStatus })
            });
            loadProducts();
        }

        async function deleteProduct(id) {
            const p = productsList.find(item => item.id == id);
            if (!p) return;

            if (p.sold_count > 0) {
                alert(
                    `Menu "${p.name}" sudah pernah terjual (${p.sold_count} item) sehingga tidak bisa dihapus\n` +
                    'karena akan merusak riwayat laporan.\n\nNonaktifkan saja agar tidak muncul di menu pelanggan.'
                );
                return;
            }

            if (!confirm(`Hapus menu "${p.name}"?\n\nTindakan ini permanen dan tidak bisa dibatalkan.`)) {
                return;
            }

            try {
                const res = await fetch(productsDeleteUrl + '/' + id, { method: 'DELETE' });
                const data = await res.json();

                if (data.success) {
                    loadProducts();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Gagal hapus menu: ' + e.message);
            }
        }

        function formatRupiah(num) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
        }

        document.addEventListener('DOMContentLoaded', loadProducts);
    </script>
@endpush
