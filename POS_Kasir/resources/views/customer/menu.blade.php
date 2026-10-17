@extends('layouts.app')

@section('title', 'Menu Digital - ' . config('app.name'))
@section('body-class', 'mobile-app-body')

@section('content')
    <!-- Top Modern App Header -->
    <header class="app-header">
        <div class="app-header-inner">
            <a href="{{ route('menu') }}" class="brand-logo" style="text-decoration:none; display:flex; align-items:center; gap:8px;">
                <span class="brand-icon" style="font-size:1.5rem;">🌿</span>
                <div>
                    <span class="brand-text" style="font-weight:800; font-size:1.05rem; color:var(--text-main); display:block; line-height:1.15;">TAMAN CAFE</span>
                    <span style="font-size:0.68rem; color:var(--text-muted); font-weight:600; display:block;">Coffee · Food · Garden</span>
                </div>
            </a>
            <div style="display:flex; align-items:center; gap:8px;">
                <a href="{{ route('history') }}" class="btn btn-outline btn-sm">🧾 Riwayat</a>
                <button type="button" class="btn btn-primary btn-sm" onclick="openCartModal()" style="display:inline-flex; align-items:center; gap:6px;">
                    🛒 <span id="headerCartCount" style="background:#ffffff; color:var(--accent); font-size:0.75rem; font-weight:800; padding:1px 6px; border-radius:9999px;">0</span>
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
            ⚠️ Anda sedang offline. Menu tetap bisa dilihat, tetapi pesanan tidak bisa dikirim sampai koneksi kembali.
        </div>

        <!-- Rekomendasi Menu Pilihan (Top Sales 1 Bulan) -->
        <section class="customer-rec-section">
            <div class="rec-section-header">
                <div>
                    <div class="rec-header-badge">
                        <span>⭐ Rekomendasi Top Sales</span>
                    </div>
                    <h3 class="rec-header-title">Menu Paling Terlaris &amp; Favorit</h3>
                    <p class="rec-header-desc">Pilihan nomor satu yang paling banyak dipesan pelanggan Taman Cafe.</p>
                </div>
                <span class="rec-badge-highlight">Berdasarkan Penjualan</span>
            </div>

            <div class="rec-cards-grid">
                @php
                    $recItems = [
                        ['badge' => '⭐ #1 Minuman', 'sales' => '480x Terjual', 'name' => 'Kopi Susu Gula Aren', 'desc' => 'Espresso blend Arabika & Robusta lokal, susu creamy, dan gula aren organik murni.', 'price' => 24000, 'img' => 'ChatGPT-Image-Oct-2_-2026_-09_13_15-PM-2.png', 'cat' => 'minuman'],
                        ['badge' => '⭐ #1 Makanan', 'sales' => '310x Terjual', 'name' => 'Nasi Goreng Kampung', 'desc' => 'Nasi goreng bumbu rempah nusantara, telur mata sapi, ayam suwir & kerupuk udang.', 'price' => 38000, 'img' => 'ChatGPT-Image-Oct-2_-2026_-09_13_19-PM-5.png', 'cat' => 'makanan'],
                        ['badge' => '⭐ #1 Snack', 'sales' => '380x Terjual', 'name' => 'Truffle Parmesan Fries', 'desc' => 'Kentang goreng renyah bumbu white truffle oil, taburan keju parmesan melimpah & parsley.', 'price' => 30000, 'img' => 'ChatGPT-Image-Oct-2_-2026_-09_13_21-PM-7.png', 'cat' => 'makanan'],
                    ];
                @endphp
                @foreach ($recItems as $rec)
                    @php
                        $matchedProduct = $products->first(function($p) use ($rec) {
                            $pName = strtolower($p->name);
                            if ($rec['cat'] === 'minuman') {
                                return str_contains($pName, 'gula aren') || str_contains($pName, 'aren') || str_contains($pName, 'kopi');
                            }
                            if (str_contains($rec['name'], 'Nasi Goreng')) {
                                return str_contains($pName, 'nasi goreng');
                            }
                            return str_contains($pName, 'kentang') || str_contains($pName, 'truffle');
                        }) ?? $products->first();

                        $targetId = $matchedProduct ? $matchedProduct->id : 1;
                        $targetPrice = $matchedProduct ? (float) $matchedProduct->price : $rec['price'];
                        $targetName = $matchedProduct ? $matchedProduct->name : $rec['name'];
                        $targetCat = $matchedProduct && $matchedProduct->category ? $matchedProduct->category->name : ($rec['cat'] === 'minuman' ? 'Minuman' : 'Makanan');
                    @endphp
                    <div class="rec-card">
                        <div class="rec-card-img-wrap">
                            <span class="rec-card-badge">{{ $rec['badge'] }}</span>
                            <span class="rec-card-sales-pill">{{ $rec['sales'] }}</span>
                            <img src="{{ asset('assets/images/' . $rec['img']) }}" alt="{{ $rec['name'] }}" loading="lazy">
                        </div>
                        <div class="rec-card-body">
                            <div>
                                <div class="rec-card-title">{{ $targetName }}</div>
                                <div class="rec-card-desc">{{ $rec['desc'] }}</div>
                            </div>
                            <div class="rec-card-footer">
                                <div class="rec-card-price">{{ rupiah($targetPrice) }}</div>
                                <button type="button" class="rec-card-btn"
                                        onclick="addToCart({{ $targetId }}, @js($targetName), {{ $targetPrice }}, @js($rec['img']), @js($targetCat))">
                                    + Tambah
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Search Bar -->
        <div class="menu-search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="menuSearchInput" class="menu-search-input" placeholder="Cari makanan, snack, atau minuman..." oninput="filterMenuSearch()">
        </div>

        <!-- Sticky Horizontal Category Scroll -->
        <div class="category-scroll-container">
            <div class="category-scroll" id="categoryTabs">
                <button type="button" class="category-pill active" data-cat="all" onclick="selectCategory('all')">
                    ✨ Semua
                </button>
                @foreach ($categories as $category)
                    <button type="button" class="category-pill" data-cat="{{ $category->id }}" onclick="selectCategory('{{ $category->id }}')">
                        {{ $category->owner_type === 'makanan' ? '🍲' : '🍹' }} {{ $category->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Product Cards Grid -->
        <div class="grid-menu" id="productGrid">
            @foreach ($products as $product)
                @php
                    $isFood = $product->category->owner_type === 'makanan';
                    // Auto-assign high quality illustration if available
                    $pName = strtolower($product->name);
                    $assignedImg = null;
                    if (str_contains($pName, 'gula aren') || str_contains($pName, 'aren')) {
                        $assignedImg = 'ChatGPT-Image-Oct-2_-2026_-09_13_15-PM-2.png';
                    } elseif (str_contains($pName, 'v60') || str_contains($pName, 'gayo') || str_contains($pName, 'americano')) {
                        $assignedImg = 'ChatGPT-Image-Oct-2_-2026_-09_13_16-PM-3.png';
                    } elseif (str_contains($pName, 'matcha')) {
                        $assignedImg = 'ChatGPT-Image-Oct-2_-2026_-09_13_17-PM-4.png';
                    } elseif (str_contains($pName, 'nasi goreng')) {
                        $assignedImg = 'ChatGPT-Image-Oct-2_-2026_-09_13_19-PM-5.png';
                    } elseif (str_contains($pName, 'croissant') || str_contains($pName, 'sandwich') || str_contains($pName, 'ayam bakar')) {
                        $assignedImg = 'ChatGPT-Image-Oct-2_-2026_-09_13_20-PM-6.png';
                    } elseif (str_contains($pName, 'kentang') || str_contains($pName, 'truffle') || str_contains($pName, 'fries')) {
                        $assignedImg = 'ChatGPT-Image-Oct-2_-2026_-09_13_21-PM-7.png';
                    }

                    $imageUrl = $assignedImg ? asset('assets/images/' . $assignedImg) : \App\Services\ProductImage::url($product->image);
                @endphp
                <div class="card-product" data-category="{{ $product->category_id }}" data-name="{{ \Illuminate\Support\Str::lower($product->name) }}">
                    <div class="card-product-img-wrapper">
                        @if ($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="card-product-img" loading="lazy">
                        @else
                            <div class="card-product-img card-product-img-placeholder {{ $isFood ? 'ph-food' : 'ph-drink' }}"
                                 role="img" aria-label="{{ $product->name }}">
                                <span class="ph-emoji">{{ $isFood ? '🍲' : '🥤' }}</span>
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
                                    onclick="addToCart({{ $product->id }}, @js($product->name), {{ (float) $product->price }}, @js($imageUrl ?? ''), @js($product->category->name))">
                                + Tambah
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Empty State Search Result -->
        <div id="emptySearchState" style="display:none; text-align:center; padding: 40px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; margin-top:20px;">
            <div style="font-size:2.5rem; margin-bottom:8px;">🔍</div>
            <h3 style="font-weight:700; color:#475569;">Menu Tidak Ditemukan</h3>
            <p style="font-size:0.85rem; color:#94a3b8;">Coba cari dengan kata kunci lain.</p>
        </div>
    </main>

    <!-- Floating Mobile Bottom Cart Bar -->
    <div class="floating-cart-bar" id="floatingCartBar" style="display:none;" onclick="openCartModal()">
        <div class="floating-cart-left">
            <div class="floating-cart-badge-icon">
                🛒
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
                <button class="btn btn-outline modal-close-btn" onclick="closeCartModal()">&times;</button>
            </div>

            <form onsubmit="submitCheckout(event)">
                <div class="modal-body">
                    <!-- Cart Item List -->
                    <div id="cartItemsList" class="cart-items-scroll"></div>

                    <div class="cart-total-bar">
                        <span style="font-weight:700; color:#475569;">Total Belanja:</span>
                        <span id="modalCartTotal" style="color:var(--primary); font-size:1.25rem; font-weight:800;">Rp 0</span>
                    </div>

                    <!-- Pilihan Layanan -->
                    <h4 class="form-section-title">🍽️ Pilihan Layanan</h4>
                    <div class="toggle-service">
                        <div class="toggle-btn active" id="btnDineIn" onclick="setServiceType('dine_in')">
                            🍽️ Makan di Meja
                        </div>
                        <div class="toggle-btn" id="btnTakeaway" onclick="setServiceType('takeaway')">
                            🛍️ Bungkus (Takeaway)
                        </div>
                    </div>
                    <input type="hidden" id="service_type" value="dine_in">

                    <!-- Data Pelanggan -->
                    <h4 class="form-section-title">👤 Informasi Pemesan</h4>

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

                    <div class="form-group">
                        <label class="form-label">Catatan Tambahan (Opsional)</label>
                        <textarea id="order_notes" class="form-control" rows="2" placeholder="Contoh: Es sedikit, sambal dipisah..."></textarea>
                    </div>

                    <!-- Metode Pembayaran -->
                    <h4 class="form-section-title">💳 Metode Pembayaran</h4>
                    <div class="payment-options">
                        <label class="payment-card-option">
                            <input type="radio" name="payment_method" value="qris" checked>
                            <div class="payment-card-content">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div class="payment-card-title">📱 QRIS Terpusat (Dinamis)</div>
                                    <span class="brand-badge badge-qris">Instan &amp; Pas</span>
                                </div>
                                <div class="payment-card-subtitle">BCA, GoPay, OVO, Dana, ShopeePay, LinkAja, Mobile Banking</div>
                            </div>
                        </label>
                        <label class="payment-card-option">
                            <input type="radio" name="payment_method" value="cash">
                            <div class="payment-card-content">
                                <div class="payment-card-title">💵 Bayar Tunai di Kasir</div>
                                <div class="payment-card-subtitle">Pesanan langsung diproses, bayar saat pesanan disajikan / di kasir</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCartModal()">Batal</button>
                    <button type="submit" id="btnSubmitOrder" class="btn btn-primary" style="flex:1;">
                        Kirim Pesanan Sekarang 🚀
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Lets "Pesan Lagi" on the history page rebuild the cart from ids alone.
        window.__MENU_PRODUCTS__ = @json($reorderCatalog);
    </script>
    <script src="{{ asset_versioned('assets/js/customer.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initCustomerMenu);
    </script>
@endpush
