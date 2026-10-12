{{--
    Single owner navbar for every page in the owner area.

    Each page used to hand-roll its own header, so the links drifted apart and
    the hamburger (which nav.js injects into .navbar-container) only appeared on
    the pages that happened to use .owner-nav-actions. Routing every page through
    this partial makes the navigation identical for admin and owner alike, and
    keeps the admin-only links in exactly one place.

    Usage:
        @include('owner.partials.navbar', ['current' => 'dashboard', 'title' => '...', 'icon' => '🍲'])

    A page can add its own buttons with @push('navbar-actions') before the include.
--}}
@php
    $navUser = auth()->user();
    $navCurrent = $current ?? '';
    $navIsAdmin = $navUser?->isAdmin() ?? false;

    // Derived here rather than passed in, so a page that forgets to hand over
    // $scopeBadge still renders a complete header.
    $navScopeBadge = $scopeBadge ?? ($navUser ? $navUser->scopeBadge() : null);
@endphp

<header class="navbar">
    <div class="navbar-container">
        <div style="display:flex; align-items:center; gap:10px; min-width:0;">
            <a href="{{ route('owner.dashboard') }}" class="brand-logo">
                <span>{{ $icon ?? '🍽️' }}</span>
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

            <a href="{{ route('owner.dashboard') }}" class="btn btn-sm {{ $navCurrent === 'dashboard' ? 'btn-primary' : 'btn-outline' }}">🍲 Dashboard</a>
            <a href="{{ route('owner.products') }}" class="btn btn-sm {{ $navCurrent === 'products' ? 'btn-primary' : 'btn-outline' }}">📦 Menu</a>
            <a href="{{ route('owner.reports') }}" class="btn btn-sm {{ $navCurrent === 'reports' ? 'btn-primary' : 'btn-outline' }}">📊 Laporan</a>
            <a href="{{ route('owner.unpaid') }}" class="btn btn-sm {{ $navCurrent === 'unpaid' ? 'btn-primary' : 'btn-outline' }}">💸 Belum Bayar</a>

            {{-- Shop-wide configuration: admin only, hidden for food / drink owners. --}}
            @if ($navIsAdmin)
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
