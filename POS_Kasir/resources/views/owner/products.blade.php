@extends('layouts.app')

@section('title', 'Kelola Menu ' . $scopeLabel . ' - ' . config('app.name'))

@section('content')
    @push('navbar-actions')
        <button class="btn btn-primary btn-sm" onclick="openProductModal()">+ Tambah Menu</button>
    @endpush

    @include('owner.partials.navbar', [
        'current' => 'products',
        'title' => 'Menu ' . $scopeLabel,
        'icon' => $scopeLabel === 'Makanan' ? '🍲' : ($scopeLabel === 'Minuman' ? '🍹' : '👨‍💼'),
    ])

    <main class="container">
        <!-- Quick Action / Search Header -->
        <div class="card p-3 mb-4" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="font-weight:800; font-size:1.1rem; color:#0f172a;">
                Daftar Menu &amp; Stok ({{ $scopeLabel }})
            </div>
            <div style="display:flex; gap:8px; flex:1; max-width:360px;">
                <input type="text" id="ownerProductSearch" class="form-control" placeholder="🔍 Cari menu..." oninput="filterOwnerProducts()">
            </div>
        </div>

        <!-- Products Container: Responsive on Mobile & Desktop -->
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
                             style="width:96px; height:96px; border-radius:12px; border:1px solid #e2e8f0; background:#f1f5f9; overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.9rem;">
                            <span id="photoPlaceholderEmoji">🍲</span>
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
            toast.innerHTML = `<span>📦</span> <span>${escapeHtml(message)}</span>`;
            container.appendChild(toast);

            setTimeout(() => toast.remove(), 3500);
        }

        // Same fallback the customer menu draws, so the owner sees exactly what
        // the customer will see instead of an unrelated stock photo.
        function productFallback(product) {
            const food = !product || product.owner_type !== 'minuman';
            const emoji = food ? '🍲' : '🥤';
            const bg = food ? 'linear-gradient(135deg,#fff7ed,#fed7aa)' : 'linear-gradient(135deg,#ecfeff,#a5f3fc)';

            return `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;background:${bg};">${emoji}</div>`;
        }

        function productThumb(product) {
            if (product && product.image_url) {
                return `<img src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.name)}" class="owner-prod-thumb">`;
            }

            return `<div class="owner-prod-thumb" style="overflow:hidden;">${productFallback(product)}</div>`;
        }

        function updatePhotoPreview(src, note) {
            const box = document.getElementById('photoPreviewBox');
            const noteEl = document.getElementById('photoCurrentNote');
            if (!box) return;

            if (src) {
                box.innerHTML = `<img src="${escapeHtml(src)}" style="width:100%;height:100%;object-fit:cover;" alt="preview">`;
            } else {
                const food = !currentProduct || currentProduct.owner_type !== 'minuman';
                box.innerHTML = `<span>${food ? '🍲' : '🥤'}</span>`;
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
            renderProducts();
        }

        function renderProducts() {
            const container = document.getElementById('productGridList');
            const search = (document.getElementById('ownerProductSearch')?.value || '').toLowerCase().trim();

            const filtered = productsList.filter(p => !search || p.name.toLowerCase().includes(search) || p.category_name.toLowerCase().includes(search));

            if (filtered.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding:40px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; grid-column:1/-1;">Belum ada menu yang cocok.</div>';
                return;
            }

            let html = '';
            filtered.forEach(p => {
                html += `
                    <div class="card owner-prod-card">
                        <div style="display:flex; gap:12px; align-items:center;">
                            ${productThumb(p)}
                            <div style="flex:1; min-width:0;">
                                <span class="brand-badge ${p.owner_type === 'minuman' ? 'badge-drink' : 'badge-food'}" style="font-size:0.7rem; padding:2px 6px;">
                                    ${p.category_name}
                                </span>
                                <h4 style="font-weight:700; font-size:1rem; margin-top:2px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.name}</h4>
                                <div style="font-weight:800; color:var(--primary); font-size:1.05rem;">${formatRupiah(p.price)}</div>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding-top:12px; border-top:1px solid #f1f5f9;">
                            <div style="font-size:0.85rem; color:#64748b;">
                                Stok: <strong style="color:#0f172a;">${p.stock}</strong>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <button class="btn ${p.is_active == 1 ? 'btn-success' : 'btn-outline'} btn-sm" onclick="toggleActive(${p.id}, ${p.is_active == 1 ? 0 : 1})">
                                    ${p.is_active == 1 ? '✅ Aktif' : 'Off'}
                                </button>
                                <button class="btn btn-primary btn-sm" onclick="editProduct(${p.id})">
                                    ✏️ Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteProduct(${p.id})"
                                        title="${p.sold_count > 0 ? 'Sudah pernah terjual, tidak bisa dihapus' : 'Hapus menu ini'}">
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </div>
                `;
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
