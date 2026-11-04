{{--
    Single owner navbar for every page in the owner area.

    Non-admin owners (makanan / minuman) see only: Dashboard, Laporan, Stok, Keluar.
    Admin sees all links including product management, promo, QRIS, etc.

    Usage:
        @include('owner.partials.navbar', ['current' => 'dashboard', 'title' => '...', 'icon' => '🍲'])

    A page can add its own buttons with @push('navbar-actions') before the include.
--}}
@php
    $navUser = auth()->user();
    $navCurrent = $current ?? '';
    $navIsAdmin = $navUser?->isAdmin() ?? false;

    $navScopeBadge = $scopeBadge ?? ($navUser ? $navUser->scopeBadge() : null);
@endphp

<header class="navbar">
    <div class="navbar-container">
        <div style="display:flex; align-items:center; gap:10px; min-width:0;">
            <a href="{{ route('owner.dashboard') }}" class="brand-logo" style="gap:10px;">
                {{-- Brand icon: SVG per role, no emoji --}}
                <span style="
                    width:30px; height:30px; border-radius:8px; flex-shrink:0;
                    display:flex; align-items:center; justify-content:center;
                    background:{{ $navIsAdmin ? '#e8f0e9' : ($navUser?->ownerType === 'makanan' ? '#e8f0e9' : '#e8edf0') }};
                ">
                    @if ($navIsAdmin)
                        {{-- Admin: store/building icon --}}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="{{ $navIsAdmin ? '#25382d' : '#1e3a4a' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    @elseif ($navUser?->ownerType === 'makanan')
                        {{-- Makanan: utensils (fork & knife) --}}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25382d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6h3l-1 11h3l-1-11h1z"/>
                        </svg>
                    @else
                        {{-- Minuman: cup/goblet --}}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1e3a4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 2h14l-2 7a5 5 0 0 1-10 0L5 2z"/><line x1="12" y1="14" x2="12" y2="20"/><line x1="8" y1="20" x2="16" y2="20"/>
                        </svg>
                    @endif
                </span>
                <span class="brand-title-text">{{ $title ?? config('app.name') }}</span>
            </a>

            @if ($navScopeBadge)
                <span class="brand-badge {{ $navIsAdmin ? 'badge-qris' : ($navUser->ownerType === 'makanan' ? 'badge-food' : 'badge-drink') }}">
                    {{ $navScopeBadge }}
                </span>
            @endif
        </div>

        <div class="owner-nav-actions">
            @stack('navbar-actions')

            <a href="{{ route('owner.dashboard') }}" class="btn btn-sm {{ $navCurrent === 'dashboard' ? 'btn-primary' : 'btn-outline' }}">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <a href="{{ route('owner.reports') }}" class="btn btn-sm {{ $navCurrent === 'reports' ? 'btn-primary' : 'btn-outline' }}">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Laporan
            </a>
            <a href="{{ route('owner.stock') }}" class="btn btn-sm {{ $navCurrent === 'stock' ? 'btn-primary' : 'btn-outline' }}">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><path d="M21 16V8a2 2 0 0 0-1-1.73L13 2.27a2 2 0 0 0-2 0L4 6.27A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                Stok Produk
            </a>

            {{-- Admin-only links --}}
            @if ($navIsAdmin)
                <a href="{{ route('owner.products') }}" class="btn btn-sm {{ $navCurrent === 'products' ? 'btn-primary' : 'btn-outline' }}">🗂️ Kelola Menu</a>
                <a href="{{ route('owner.unpaid') }}" class="btn btn-sm {{ $navCurrent === 'unpaid' ? 'btn-primary' : 'btn-outline' }}">💸 Belum Bayar</a>
                <a href="{{ route('owner.promos') }}" class="btn btn-sm {{ $navCurrent === 'promos' ? 'btn-primary' : 'btn-outline' }}">🏷️ Kupon Promo</a>
                <a href="{{ route('owner.qris') }}" class="btn btn-sm {{ $navCurrent === 'qris' ? 'btn-primary' : 'btn-outline' }}">📱 QRIS Dinamis</a>
                <a href="{{ route('qr-center') }}" target="_blank" class="btn btn-sm btn-outline">🖨️ Cetak QR</a>
            @endif

            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm">Keluar</button>
            </form>
        </div>
    </div>
</header>
