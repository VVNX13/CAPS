@extends('layouts.app')

@section('title', 'Menu Digital - ' . config('app.name'))
@section('body-class', 'mobile-app-body')

@section('content')
    <!-- Top Modern App Header -->
    <header class="app-header">
        <div class="app-header-inner">
            <a href="{{ route('menu') }}" class="brand-logo" style="text-decoration:none; display:flex; align-items:center; gap:8px;">
                <span class="icon-chip icon-chip-green"><x-icon name="leaf" /></span>
                <div>
                    <span class="brand-text" style="font-weight:800; font-size:1.05rem; color:var(--text-main); display:block; line-height:1.15;">TAMAN CAFE</span>
                    <span style="font-size:0.68rem; color:var(--text-muted); font-weight:600; display:block;">Coffee · Food · Garden</span>
                </div>
            </a>
            <div style="display:flex; align-items:center; gap:8px;">
                <a href="{{ route('history') }}" class="btn btn-outline btn-sm"><x-icon name="receipt" /> Riwayat</a>
                <button type="button" class="btn btn-primary btn-sm" onclick="openCartModal()" style="display:inline-flex; align-items:center; gap:6px;">
                    <x-icon name="shopping-cart" /> <span id="headerCartCount" style="background:#ffffff; color:var(--accent); font-size:0.75rem; font-weight:800; padding:1px 6px; border-radius:9999px;">0</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Cafe Intro Banner -->
    <div class="container" style="padding-bottom:0;">
        <section class="cafe-intro">
            <div class="cafe-intro-copy">
                <h1>Taman Cafe</h1>
                <p>Ngopi, makan, dan santai di tengah suasana taman.</p>
                <small>Jl. Wijaya Kusuma No.54 · Banta-Bantaeng · Makassar</small>
            </div>
            <figure>
                <img src="{{ asset('assets/images/ChatGPT-Image-Oct-2_-2026_-09_13_14-PM-1.png') }}" alt="Suasana Taman Cafe" loading="lazy">
            </figure>
        </section>
    </div>

    <main class="container app-content" style="padding-top:0;">
        <!-- Offline Notice -->
        <div id="offlineBanner" style="display:none; background:#fef3c7; border:1px solid #fcd34d; color:#92400e; padding:12px 14px; border-radius:12px; margin-bottom:14px; font-size:0.85rem; font-weight:600;">
            <x-icon name="wifi-off" /> Anda sedang offline. Menu tetap bisa dilihat, tetapi pesanan tidak bisa dikirim sampai koneksi kembali.
        </div>

        @if ($recommendations->isNotEmpty())
        <!-- Rekomendasi berdasarkan transaksi lunas 30 hari terakhir -->
        <section class="customer-rec-section">
            <div class="rec-section-header">
                <div>
                    <div class="rec-header-badge">
                        <x-icon name="star" /><span>Rekomendasi Top Sales</span>
                    </div>
                    <h3 class="rec-header-title">Menu Paling Terlaris &amp; Favorit</h3>
                    <p class="rec-header-desc">Menu paling banyak terjual dari transaksi lunas selama 30 hari terakhir.</p>
                </div>
                <span class="rec-badge-highlight">Berdasarkan Penjualan</span>
            </div>

            <div class="rec-cards-grid">
                @foreach ($recommendations as $product)
                    @php
                        $targetId = $product->id;
                        $targetPrice = (float) $product->price;
                        $targetName = $product->name;
                        $targetCat = $product->category->name;
                        $targetStock = (int) $product->stock;
                        $targetImgUrl = \App\Services\ProductImage::url($product->image, $product->name) ?? '';
                        $badge = '#' . $loop->iteration . ' Terlaris';
                        $salesLabel = number_format($product->quantity_sold, 0, ',', '.') . ' porsi terjual';
                    @endphp
                    <div class="rec-card" style="cursor:pointer;"
                         onclick="openProductPreview(event, { id: {{ $targetId }}, name: @js($targetName), price: {{ $targetPrice }}, image: @js($targetImgUrl), category: @js($targetCat), ownerType: @js($product->category->owner_type), stock: {{ $targetStock }}, desc: @js((string) $product->description), badge: @js($badge . ' · ' . $salesLabel) })">
                        <div class="rec-card-img-wrap">
                            <span class="rec-card-badge"><x-icon name="star" /> {{ $badge }}</span>
                            <span class="rec-card-sales-pill">{{ $salesLabel }}</span>
                            @if ($targetImgUrl)
                                <img src="{{ $targetImgUrl }}" alt="{{ $targetName }}" loading="lazy">
                            @else
                                <x-icon :name="$product->category->owner_type === 'makanan' ? 'soup' : 'cup-soda'" />
                            @endif
                        </div>
                        <div class="rec-card-body">
                            <div>
                                <div class="rec-card-title">{{ $targetName }}</div>
                                <div class="rec-card-desc">{{ $product->description }}</div>
                            </div>
                            <div class="rec-card-footer">
                                <div class="rec-card-price">{{ rupiah($targetPrice) }}</div>
                                <button type="button" class="rec-card-btn"
                                        onclick="event.stopPropagation(); addToCart({{ $targetId }}, @js($targetName), {{ $targetPrice }}, @js($targetImgUrl), @js($targetCat))">
                                    + Tambah
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        <!-- Search & Sort Bar -->
        <div class="menu-search-wrapper">
            <div class="menu-search-box">
                <span class="search-icon"><x-icon name="search" /></span>
                <input type="text" id="menuSearchInput" class="menu-search-input" placeholder="Cari makanan, snack, atau minuman..." oninput="filterMenuSearch()">
            </div>
            <select id="menuSortSelect" class="menu-sort-select" onchange="applyFilters()">
                <option value="default">Urutkan Menu</option>
                <option value="price_asc">Harga: Termurah</option>
                <option value="price_desc">Harga: Termahal</option>
                <option value="name_asc">Nama: A - Z</option>
            </select>
        </div>

        <!-- Sticky Horizontal Category Scroll -->
        <div class="category-scroll-container">
            <div class="category-scroll" id="categoryTabs">
                <button type="button" class="category-pill active" data-cat="all" onclick="selectCategory('all')">
                    <x-icon name="sparkles" /> Semua
                </button>
                @foreach ($categories as $category)
                    <button type="button" class="category-pill" data-cat="{{ $category->id }}" onclick="selectCategory('{{ $category->id }}')">
                        <x-icon :name="$category->owner_type === 'makanan' ? 'soup' : 'cup-soda'" /> {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Product Cards Grid -->
        <div class="grid-menu" id="productGrid">
            @foreach ($products as $product)
                @php
                    $isFood = $product->category->owner_type === 'makanan';
                    $imageUrl = \App\Services\ProductImage::url($product->image, $product->name);
                @endphp
                <div class="card-product" style="cursor:pointer;" data-category="{{ $product->category_id }}" data-name="{{ \Illuminate\Support\Str::lower($product->name) }}" data-price="{{ (float) $product->price }}" data-order="{{ $loop->index }}"
                     onclick="openProductPreview(event, { id: {{ $product->id }}, name: @js($product->name), price: {{ (float) $product->price }}, image: @js($imageUrl ?? ''), category: @js($product->category->name), ownerType: @js($product->category->owner_type), stock: {{ (int) $product->stock }}, desc: @js((string) $product->description) })">
                    <div class="card-product-img-wrapper">
                        @if ($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="card-product-img" loading="lazy"
                                 onerror="this.style.opacity='0';">
                        @else
                            <div class="card-product-img card-product-img-placeholder {{ $isFood ? 'ph-food' : 'ph-drink' }}"
                                 role="img" aria-label="{{ $product->name }}">
                                <span class="ph-icon"><x-icon :name="$isFood ? 'soup' : 'cup-soda'" /></span>
                            </div>
                        @endif

                        <span class="brand-badge {{ $isFood ? 'badge-food' : 'badge-drink' }} img-badge">
                            {{ $product->category->name }}
                        </span>
                    </div>
                    <div class="card-product-body">
                        <div class="card-product-header">
                            <h3 class="card-product-title">{{ $product->name }}</h3>
                            <span class="card-product-stock">Stok: {{ $product->stock }}</span>
                        </div>
                        @if (filled($product->description))
                            <p class="card-product-desc">{{ $product->description }}</p>
                        @else
                            <div class="card-product-desc card-product-desc-empty" aria-hidden="true"></div>
                        @endif
                        <div class="card-product-footer">
                            <div class="product-price">{{ rupiah($product->price) }}</div>
                            <button class="btn btn-primary btn-add-cart"
                                    {{ $product->stock < 1 ? 'disabled' : '' }}
                                    onclick="event.stopPropagation(); addToCart({{ $product->id }}, @js($product->name), {{ (float) $product->price }}, @js($imageUrl ?? ''), @js($product->category->name))">
                                + Tambah
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Empty State Search Result -->
        <div id="emptySearchState" style="display:none; text-align:center; padding: 40px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; margin-top:20px;">
            <x-icon name="search" class="icon-empty-state" />
            <h3 style="font-weight:700; color:#475569;">Menu Tidak Ditemukan</h3>
            <p style="font-size:0.85rem; color:#94a3b8;">Coba cari dengan kata kunci lain.</p>
        </div>
    </main>

    <!-- Modal Detail / Preview Menu & Personalisasi -->
    <div class="modal-overlay" id="productPreviewModal" onclick="if(event.target.id==='productPreviewModal') closeProductPreview()">
        <div class="modal-box modal-bottom-sheet" onclick="event.stopPropagation()">
            <div class="modal-sheet-handle"></div>
            <div id="previewImgWrap" style="position:relative; width:100%; height:200px; background:#f1f5f9; overflow:hidden;"></div>
            <div class="modal-body pv-body">
                <div class="pv-head">
                    <div>
                        <h3 id="previewTitle" class="pv-title"></h3>
                        <div id="previewRating" class="pv-meta"></div>
                    </div>
                    <div class="pv-price-wrap">
                        <div id="previewPrice" class="pv-price"></div>
                        <div id="previewStock" class="pv-meta"></div>
                    </div>
                </div>

                <p id="previewDesc" class="pv-desc"></p>

                <div class="pv-section">
                    <div class="pv-label">Komposisi</div>
                    <p id="previewComposition" class="pv-text"></p>
                    <div id="previewHighlights" class="pv-highlights"></div>
                </div>

                <div class="pv-section">
                    <div class="pv-label">Sesuaikan Pesanan <span class="pv-optional">Opsional</span></div>
                    <div id="previewChips" class="pv-chips"></div>
                    <input type="text" id="previewCustomNote" class="cart-item-note-input" placeholder="Catatan khusus, mis. tanpa es batu" maxlength="150">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeProductPreview()">Tutup</button>
                <button type="button" id="previewAddBtn" class="btn btn-primary" style="flex:1;" onclick="addPreviewProductToCart()">+ Tambah ke Keranjang</button>
            </div>
        </div>
    </div>

    <!-- Floating Mobile Bottom Cart Bar -->
    <div class="floating-cart-bar" id="floatingCartBar" style="display:none;" onclick="openCartModal()">
        <div class="floating-cart-left">
            <div class="floating-cart-badge-icon">
                <x-icon name="shopping-cart" />
                <span class="cart-badge-bubble" id="cartBadgeCount">0</span>
            </div>
            <div class="floating-cart-info">
                <div id="cartTotalCount" class="floating-cart-title">0 Item Dipesan</div>
                <div id="cartTotalAmount" class="floating-cart-price">Rp 0</div>
            </div>
        </div>
        <div class="floating-cart-btn">
            Checkout &rarr;
        </div>
    </div>

    <!-- Modal Cart & Checkout Bottom Sheet -->
    <div class="modal-overlay" id="cartModal" onclick="handleModalOverlayClick(event)">
        <div class="modal-box modal-bottom-sheet" onclick="event.stopPropagation()">
            <div class="modal-sheet-handle"></div>

            <div class="modal-header">
                <div>
                    <h3 style="font-size:1.15rem; font-weight:800; color:#0f172a;">Keranjang Pesanan</h3>
                    <p style="font-size:0.75rem; color:#64748b; margin-top:2px;">Periksa item sebelum mengirim pesanan</p>
                </div>
                <button type="button" class="btn btn-outline modal-close-btn" onclick="closeCartModal()" aria-label="Tutup keranjang"></button>
            </div>

            <form onsubmit="submitCheckout(event)">
                <div class="modal-body">
                    <!-- Cart Item List -->
                    <div id="cartItemsList" class="cart-items-scroll"></div>

                    <!-- Kupon Promo -->
                    <div class="promo-apply">
                        <div class="promo-apply-row">
                            <input type="text" id="promoCodeInput" class="form-control" maxlength="30"
                                   placeholder="Punya kode kupon?" autocomplete="off" aria-label="Kode kupon"
                                   style="text-transform:uppercase;"
                                   onkeydown="if (event.key === 'Enter') { event.preventDefault(); applyPromoCode(); }">
                            <button type="button" id="btnApplyPromo" class="btn btn-outline btn-sm" onclick="applyPromoCode()">Pakai</button>
                        </div>
                        <div id="promoStatus" class="promo-status" aria-live="polite"></div>
                    </div>

                    <div id="cartDiscountRows" class="cart-discount-rows" style="display:none;">
                        <div><span>Subtotal</span><span id="modalCartSubtotal">Rp 0</span></div>
                        <div class="is-discount"><span id="modalDiscountLabel">Diskon</span><span id="modalCartDiscount">-Rp 0</span></div>
                    </div>

                    <div class="cart-total-bar">
                        <span style="font-weight:700; color:#475569;">Total Bayar:</span>
                        <span id="modalCartTotal" style="color:var(--primary); font-size:1.25rem; font-weight:800;">Rp 0</span>
                    </div>

                    <!-- Pilihan Layanan -->
                    <h4 class="form-section-title"><x-icon name="utensils" /> Pilihan Layanan</h4>
                    <div class="toggle-service">
                        <div class="toggle-btn active" id="btnDineIn" onclick="setServiceType('dine_in')">
                            <x-icon name="utensils" /> Makan di Meja
                        </div>
                        <div class="toggle-btn" id="btnTakeaway" onclick="setServiceType('takeaway')">
                            <x-icon name="shopping-bag" /> Bungkus (Takeaway)
                        </div>
                    </div>
                    <input type="hidden" id="service_type" value="dine_in">

                    <div class="form-group" id="tableNumberGroup">
                        <label class="form-label" for="table_number">Nomor Meja <span class="req-star">*</span></label>
                        <input type="text" id="table_number" class="form-control"
                               placeholder="Contoh: 5 atau A3"
                               maxlength="10" autocomplete="off" inputmode="text"
                               pattern="[A-Za-z0-9\- ]{1,10}" title="Nomor meja berupa huruf/angka, maksimal 10 karakter"
                               required>
                        <small class="form-hint">Lihat nomor yang tertempel di meja Anda.</small>
                    </div>

                    <!-- Data Pelanggan -->
                    <h4 class="form-section-title"><x-icon name="user" /> Informasi Pemesan</h4>

                    <div class="form-group">
                        <label class="form-label">Nama Anda <span class="req-star">*</span></label>
                        <input type="text" id="customer_name" class="form-control" placeholder="Masukkan nama panggilan / lengkap" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nomor WhatsApp <span class="req-star">*</span></label>
                        <input type="tel" id="customer_phone" class="form-control"
                               placeholder="08xxxxxxxxxx"
                               inputmode="numeric" autocomplete="tel" maxlength="20"
                               pattern="0[0-9]{8,13}" title="Masukkan nomor Indonesia, contoh 081234567890"
                               required>
                        <small class="form-hint">Dipakai untuk kirim struk &amp; kalau ada pesanan yang belum dibayar.</small>
                    </div>

                    <!-- Metode Pembayaran -->
                    <h4 class="form-section-title"><x-icon name="credit-card" /> Metode Pembayaran</h4>
                    <div class="payment-options">
                        <label class="payment-card-option">
                            <input type="radio" name="payment_method" value="qris" checked>
                            <div class="payment-card-content">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div class="payment-card-title with-icon"><x-icon name="qr-code" /> QRIS Terpusat (Dinamis)</div>
                                    <span class="brand-badge badge-qris">Instan &amp; Pas</span>
                                </div>
                                <div class="payment-card-subtitle">BCA, GoPay, OVO, Dana, ShopeePay, LinkAja, Mobile Banking</div>
                            </div>
                        </label>
                        <label class="payment-card-option">
                            <input type="radio" name="payment_method" value="cash">
                            <div class="payment-card-content">
                                <div class="payment-card-title with-icon"><x-icon name="banknote" /> Bayar Tunai di Kasir</div>
                                <div class="payment-card-subtitle">Pesanan langsung diproses, bayar saat pesanan disajikan / di kasir</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCartModal()">Batal</button>
                    <button type="submit" id="btnSubmitOrder" class="btn btn-primary" style="flex:1;">
                        Kirim Pesanan Sekarang <x-icon name="send" />
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Asisten Menu (chat) — dibuka oleh pelanggan sendiri, tidak muncul otomatis -->
    <div class="assistant-teaser" id="assistantTeaser" hidden>
        <button type="button" class="assistant-teaser-body" onclick="openAssistant()">
            Bingung mau pesan apa? Saya bantu carikan menu yang pas.
        </button>
        <button type="button" class="assistant-teaser-close" onclick="dismissAssistantTeaser()" aria-label="Tutup">
            <x-icon name="x" />
        </button>
    </div>

    <button type="button" class="assistant-fab" id="assistantFab" onclick="openAssistant()"
            aria-label="Buka asisten menu" aria-controls="assistantPanel" aria-expanded="false">
        <x-icon name="message-circle" />
        <span class="assistant-fab-label">Tanya Menu</span>
    </button>

    <div class="assistant-backdrop" onclick="closeAssistant()" aria-hidden="true"></div>
    <section class="assistant-panel" id="assistantPanel" role="dialog" aria-label="Asisten Menu Taman Cafe" hidden>
        <header class="assistant-header">
            <span class="assistant-avatar"><x-icon name="leaf" /></span>
            <div class="assistant-title">
                <strong>Asisten Taman Cafe</strong>
                <span><span class="assistant-online-dot"></span> Siap membantu memilih menu</span>
            </div>
            <button type="button" class="assistant-icon-btn" onclick="resetAssistant()" aria-label="Mulai ulang percakapan" title="Mulai ulang">
                <x-icon name="refresh" />
            </button>
            <button type="button" class="assistant-icon-btn" onclick="closeAssistant()" aria-label="Tutup asisten" title="Tutup">
                <x-icon name="x" />
            </button>
        </header>
        <div class="assistant-messages" id="assistantMessages" aria-live="polite"></div>
        <div class="assistant-quick" id="assistantQuick" hidden></div>
        <form class="assistant-input" onsubmit="submitAssistantText(event)">
            <input type="text" id="assistantInput" maxlength="120" autocomplete="off"
                   placeholder="Ketik selera Anda, mis. pedas atau kopi" aria-label="Pesan untuk asisten">
            <button type="submit" class="assistant-send" aria-label="Kirim"><x-icon name="send" /></button>
        </form>
    </section>
@endsection

@push('scripts')
    @php
        $catalogProductsJson = $products->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
            'category' => $p->category?->name ?? '',
            'ownerType' => $p->category?->owner_type ?? '',
            'stock' => (int) $p->stock,
            'desc' => (string) ($p->description ?? ''),
            'image' => $p->image ?? '',
            'imageUrl' => \App\Services\ProductImage::url($p->image, $p->name) ?? '',
        ])->values();
    @endphp
    <script>
        // Lets "Pesan Lagi" on the history page rebuild the cart from ids alone.
        window.__MENU_PRODUCTS__ = @json($reorderCatalog);
        window.__ALL_CATALOG_PRODUCTS__ = @json($catalogProductsJson);
        window.__BESTSELLER_IDS__ = @json($bestsellerIds);
    </script>
    <script src="{{ asset_versioned('assets/js/customer.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initCustomerMenu);
    </script>
@endpush
