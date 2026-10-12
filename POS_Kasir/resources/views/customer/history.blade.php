@extends('layouts.app')

@section('title', 'Riwayat Pesanan Saya - ' . config('app.name'))

@section('content')
    <header class="navbar">
        <div class="navbar-container">
            <div style="display:flex; align-items:center; gap:10px;">
                <a href="{{ route('menu') }}" class="brand-logo">
                    <span>🧾</span>
                    <span class="brand-title-text">Riwayat Pesanan</span>
                </a>
            </div>

            <div class="owner-nav-actions">
                <a href="{{ route('menu') }}" class="btn btn-primary btn-sm">☕ Pesan Lagi</a>
            </div>
        </div>
    </header>

    <main class="container" style="max-width:720px;">
        <div class="owner-welcome-card">
            <div>
                <h2 style="font-size:1.2rem; font-weight:800; color:#0f172a;">Cek pesanan Anda 🔎</h2>
                <p style="font-size:0.85rem; color:#64748b; margin-top:2px;">
                    Masukkan nomor WhatsApp yang Anda pakai saat memesan.
                </p>
            </div>
        </div>

        <div class="card" style="background:#fff; border-radius:16px; padding:18px; border:1px solid #e2e8f0; margin-bottom:18px;">
            <form id="historyForm" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                <div class="form-group" style="flex:1; min-width:200px; margin-bottom:0;">
                    <label class="form-label" for="historyPhone">Nomor WhatsApp</label>
                    <input type="tel" id="historyPhone" class="form-control"
                           placeholder="081234567890" inputmode="numeric" autocomplete="tel" maxlength="20">
                    <small class="form-hint">Data {{ $windowDays }} hari terakhir.</small>
                </div>
                <button type="submit" id="btnHistorySearch" class="btn btn-primary">Cari Riwayat</button>
            </form>
            <div id="historyMessage" style="margin-top:12px; display:none; font-size:0.85rem; border-radius:10px; padding:10px 12px;"></div>
        </div>

        <div id="historySummary"></div>
        <div id="historyList" class="unpaid-list" style="margin-top:14px;"></div>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset_versioned('assets/js/history.js') }}"></script>
@endpush
