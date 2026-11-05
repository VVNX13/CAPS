{{--
    Single owner navbar for every page in the owner area.

    Non-admin owners (makanan / minuman) see: Dashboard, Laporan, Stok, Keluar.
    Admin sees all links in one flat row using compact .btn-nav buttons.
--}}
@php
    $navUser    = auth()->user();
    $navCurrent = $current ?? '';
    $navIsAdmin = $navUser?->isAdmin() ?? false;
    $navScopeBadge = $scopeBadge ?? ($navUser ? $navUser->scopeBadge() : null);
@endphp

<header class="navbar">
    <div class="navbar-container">

        {{-- ── Brand: icon + scope badge only (no long title — saves horizontal space) ── --}}
        <a href="{{ route('owner.dashboard') }}" class="brand-logo" style="gap:8px; flex-shrink:0; text-decoration:none;">
            <span style="
                width:28px; height:28px; border-radius:7px; flex-shrink:0;
                display:flex; align-items:center; justify-content:center;
                background:{{ $navIsAdmin ? '#e8f0e9' : ($navUser?->ownerType === 'makanan' ? '#e8f0e9' : '#e8edf0') }};
            ">
                @if ($navIsAdmin)
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#25382d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                @elseif ($navUser?->ownerType === 'makanan')
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#25382d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6h3l-1 11h3l-1-11h1z"/>
                    </svg>
                @else
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1e3a4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 2h14l-2 7a5 5 0 0 1-10 0L5 2z"/><line x1="12" y1="14" x2="12" y2="20"/><line x1="8" y1="20" x2="16" y2="20"/>
                    </svg>
                @endif
            </span>

            @if ($navScopeBadge)
                <span class="brand-badge {{ $navIsAdmin ? 'badge-qris' : ($navUser->ownerType === 'makanan' ? 'badge-food' : 'badge-drink') }}"
                      style="font-size:0.65rem; letter-spacing:0.04em;">
                    {{ $navScopeBadge }}
                </span>
            @endif
        </a>

        {{-- ── Nav actions (all flat, compact) ── --}}
        <div class="owner-nav-actions">
            @stack('navbar-actions')

            {{-- Dashboard --}}
            <a href="{{ route('owner.dashboard') }}"
               class="btn btn-nav {{ $navCurrent === 'dashboard' ? 'btn-primary' : 'btn-outline' }}"
               title="Dashboard">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                Dashboard
            </a>

            {{-- Laporan --}}
            <a href="{{ route('owner.reports') }}"
               class="btn btn-nav {{ $navCurrent === 'reports' ? 'btn-primary' : 'btn-outline' }}"
               title="Laporan">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
                Laporan
            </a>

            {{-- Stok --}}
            <a href="{{ route('owner.stock') }}"
               class="btn btn-nav {{ $navCurrent === 'stock' ? 'btn-primary' : 'btn-outline' }}"
               title="Stok Produk">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73L13 2.27a2 2 0 0 0-2 0L4 6.27A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                Stok
            </a>

            @if ($navIsAdmin)
                {{-- Kelola Menu --}}
                <a href="{{ route('owner.products') }}"
                   class="btn btn-nav {{ $navCurrent === 'products' ? 'btn-primary' : 'btn-outline' }}"
                   title="Kelola Menu">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1" ry="1"/>
                        <line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/>
                    </svg>
                    Menu
                </a>

                {{-- Belum Bayar --}}
                <a href="{{ route('owner.unpaid') }}"
                   class="btn btn-nav {{ $navCurrent === 'unpaid' ? 'btn-primary' : 'btn-outline' }}"
                   title="Belum Bayar">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Tagihan
                </a>

                {{-- Kupon Promo --}}
                <a href="{{ route('owner.promos') }}"
                   class="btn btn-nav {{ $navCurrent === 'promos' ? 'btn-primary' : 'btn-outline' }}"
                   title="Kupon Promo">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                        <line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                    Promo
                </a>

                {{-- QRIS --}}
                <a href="{{ route('owner.qris') }}"
                   class="btn btn-nav {{ $navCurrent === 'qris' ? 'btn-primary' : 'btn-outline' }}"
                   title="QRIS Dinamis">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    QRIS
                </a>

                {{-- Cetak QR --}}
                <a href="{{ route('qr-center') }}" target="_blank"
                   class="btn btn-nav btn-outline"
                   title="Cetak QR Menu">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    QR
                </a>
            @endif

            {{-- Keluar --}}
            <form method="POST" action="{{ route('logout') }}" style="display:inline; flex-shrink:0;">
                @csrf
                <button type="submit" class="btn btn-nav btn-danger">Keluar</button>
            </form>
        </div>

    </div>
</header>
