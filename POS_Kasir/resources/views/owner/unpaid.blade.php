@extends('layouts.app')

@section('title', 'Tagihan Belum Bayar - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current' => 'unpaid',
        'title' => 'Tagihan Belum Bayar',
        'icon' => '💸',
    ])

    <main class="container">
        <div class="owner-welcome-card">
            <div>
                <h2 style="font-size:1.2rem; font-weight:800; color:#0f172a;">Pesanan unpaid yang perlu ditagih 💸</h2>
                <p style="font-size:0.85rem; color:#64748b; margin-top:2px;">
                    Dikelompokkan per nomor HP, jadi tinggal telpon atau WhatsApp sekali saja per pelanggan.
                </p>
            </div>
            <button type="button" id="btnRefreshUnpaid" class="btn btn-outline btn-sm">🔄 Muat Ulang</button>
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
            ⚠️ Ada tagihan dengan nomor HP tidak valid (mis. <code>000000</code>) sehingga tidak bisa ditelpon.
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
