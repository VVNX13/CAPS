@extends('layouts.app')

@section('title', 'Menu Digital - ' . config('app.name'))
@section('body-class', 'mobile-app-body')

@section('content')
    <!-- Top Modern App Header -->
    <header class="app-header">
        <div class="app-header-inner">
            <a href="{{ route('menu') }}" class="brand-logo">
                <span class="brand-icon">🍽️</span>
                <span class="brand-text">{{ config('app.name') }}</span>
            </a>
            <a href="{{ route('history') }}" class="btn btn-outline btn-sm">🧾 Riwayat</a>
        </div>
    </header>

    <!-- Mobile Hero Banner -->
    <div class="app-hero-banner">
        <div class="hero-content">
            <span class="hero-chip">✨ 1 QR Code Self-Order</span>
            <h1 class="hero-title">Pesan Menu Favorit Anda</h1>
            <p class="hero-subtitle">Pilih santapan & minuman langsung dari meja Anda</p>
        </div>
    </div>

    <main class="container app-content">
        <!-- Offline Notice -->
        <div id="offlineBanner" style="display:none; background:#fef3c7; border:1px solid #fcd34d; color:#92400e; padding:12px 14px; border-radius:12px; margin-bottom:14px; font-size:0.85rem; font-weight:600;">
            ⚠️ Anda sedang offline. Menu tetap bisa dilihat, tetapi pesanan tidak bisa dikirim sampai koneksi kembali.
        </div>

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
                <div class="card-product" data-category="{{ $product->category_id }}" data-name="{{ \Illuminate\Support\Str::lower($product->name) }}">
                    <div class="card-product-img-wrapper">
                        @php
                            $imageUrl = \App\Services\ProductImage::url($product->image);
                            $isFood = $product->category->owner_type === 'makanan';
                        @endphp

                        @if ($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="card-product-img" loading="lazy">
                        @else
                            {{-- Local placeholder: no network, so the menu still looks
                                 right offline and no stock photo is mistaken for a
                                 real dish. --}}
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
                            {{-- Same flex spacer, no invented copy. Used to fall back
                                 to "Menu lezat pilihan segar khas restoran." on every
                                 card, which said nothing and read as filler. --}}
                            <div class="card-product-desc card-product-desc-empty" aria-hidden="true"></div>
                        @endif
                        <div class="card-product-footer">
                            <div class="product-price">{{ rupiah($product->price) }}</div>
                            <button class="btn btn-primary btn-add-cart"
                                    onclick="addToCart({{ $product->id }}, @js($product->name), {{ (float) $product->price }}, @js($product->image ?? ''), @js($product->category->name))">
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
