@extends('layouts.app')

@section('title', 'Tagihan Belum Bayar - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current' => 'unpaid',
        'title'   => 'Tagihan Belum Bayar',
    ])

    <main class="container">

        {{-- ── Page Header ── --}}
        <div class="stk-page-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; padding-top:4px;">
            <div>
                <div style="font-size:1.3rem; font-weight:800; color:var(--text-main,#0f172a); letter-spacing:-0.02em;">Tagihan Belum Bayar</div>
                <div style="font-size:0.8rem; color:var(--text-muted,#64748b); margin-top:3px; font-weight:500;">Dikelompokkan per pelanggan — satu panggilan per orang</div>
            </div>
            <button type="button" id="btnRefreshUnpaid" class="btn btn-outline btn-sm" style="display:flex; align-items:center; gap:7px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Muat Ulang
            </button>
        </div>

        <div class="kpi-grid" style="margin-bottom:18px;">
            <div class="kpi-card border-danger">
                <div class="kpi-label">PESANAN MENGGANTUNG</div>
                <div id="unpaidSaleCount" class="kpi-val text-danger">0</div>
            </div>
            <div class="kpi-card border-warning">
                <div class="kpi-label">PELANGGAN DITAGIH</div>
                <div id="unpaidCustomerCount" class="kpi-val text-warning">0</div>
            </div>
            <div class="kpi-card border-success">
                <div class="kpi-label">TOTAL TAGIHAN</div>
                <div id="unpaidTotal" class="kpi-val text-success">Rp 0</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">NOMOR TIDAK VALID</div>
                <div id="unpaidUncallable" class="kpi-val">0</div>
            </div>
        </div>

        <div id="unpaidWarning" style="display:none; background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:12px 14px; border-radius:12px; margin-bottom:16px; font-size:0.85rem;">
            Ada tagihan dengan nomor HP tidak valid (mis. <code>000000</code>) sehingga tidak bisa ditelpon.
            Nomor tersebut berasal dari pesanan lama, sebelum validasi nomor WhatsApp dipasang.
        </div>

        <div id="unpaidList" class="unpaid-list">
            <div style="text-align:center; padding:40px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1;">
                <h3 style="color:#64748b; font-size:1.1rem; font-weight:700;">Memuat tagihan...</h3>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset_versioned('assets/js/receivable.js') }}"></script>
@endpush
