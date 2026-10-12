@extends('layouts.app')

@section('title', 'Cetak QR Stand Meja & Kasir - ' . config('app.name'))

@push('head')
    <style>
        @media print {
            .no-print, .navbar { display: none !important; }
            body { background: #fff !important; padding: 0 !important; }
            .print-card { box-shadow: none !important; border: 2px solid #000 !important; margin: 0 auto 30px !important; page-break-after: always; }
        }
    </style>
@endpush

@section('content')
    <div class="no-print">
        @include('owner.partials.navbar', [
            'current' => 'qr-center',
            'title' => 'Cetak QR Meja',
            'icon' => '🖨️',
        ])

        <div style="max-width:540px; margin:0 auto 16px; display:flex; justify-content:flex-end; align-items:center; flex-wrap:wrap; gap:10px;">
            <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Cetak Semua QR Stand</button>
        </div>
    </div>

    <!-- Container Printable Cards -->
    <div style="max-width: 500px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; background:#f1f5f9; padding:16px;">

        <!-- CARD 1: QR Stand Meja (Self-Order Hub) -->
        <div class="print-card" style="background:#fff; padding:32px 24px; border-radius:24px; border:2px dashed #2563eb; text-align:center; box-shadow:var(--shadow-md);">
            <div style="font-size:2.6rem; margin-bottom:6px;">🍽️</div>
            <h1 style="font-size:1.5rem; font-weight:900; color:#1e3a8a; margin-bottom:4px;">{{ config('app.name') }}</h1>
            <p style="font-size:0.85rem; color:#64748b; margin-bottom:16px;">Scan 1 QR Code untuk Pesan &amp; Bayar dari Meja</p>

            <!-- QR Menu Image -->
            <div style="background:#f8fafc; padding:16px; border-radius:18px; border:1px solid #e2e8f0; display:inline-block; margin-bottom:14px;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($menuUrl) }}" alt="QR Code Menu" style="width:200px; height:200px; display:block; border-radius:8px;">
            </div>

            <div style="background:#eff6ff; padding:10px 14px; border-radius:12px; margin-bottom:14px; font-size:0.85rem; color:#1e40af; font-weight:700;">
                🍲 Makanan &bull; 🍹 Minuman &bull; 📱 QRIS &amp; Tunai
            </div>

            <div style="font-size:0.8rem; color:#64748b; line-height:1.6; text-align:center;">
                1. Buka Kamera HP Anda<br>
                2. Scan QR Code di atas<br>
                3. Pilih Menu &amp; Kirim Pesanan
            </div>
        </div>

        <!-- CARD 2: QRIS Merchant Kasir Display -->
        <div class="print-card" style="background:#fff; padding:32px 24px; border-radius:24px; border:2px dashed #dc2626; text-align:center; box-shadow:var(--shadow-md);">
            <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-bottom:8px;">
                <span style="font-size:1.6rem; font-weight:900; color:#dc2626; letter-spacing:1px;">QRIS</span>
                <span style="font-size:0.8rem; font-weight:700; color:#475569;">Pembayaran Terpusat</span>
            </div>

            <h2 style="font-size:1.25rem; font-weight:800; color:#0f172a; margin-bottom:2px;">
                {{ $qrisDetails['merchant_name'] ?: config('app.name') }}
            </h2>
            <p style="font-size:0.8rem; color:#64748b; margin-bottom:16px;">
                {{ $qrisDetails['merchant_city'] ?: 'Indonesia' }} &bull; NMID: Standar Nasional
            </p>

            <!-- QRIS Static Code -->
            <div style="background:#f8fafc; padding:16px; border-radius:18px; border:1px solid #e2e8f0; display:inline-block; margin-bottom:14px;">
                <img src="{{ $qrisImg }}" alt="QRIS Merchant" style="width:200px; height:200px; display:block; border-radius:8px;">
            </div>

            <div style="font-size:0.75rem; color:#475569; font-weight:600; margin-bottom:10px;">
                Menerima Pembayaran Seluruh Bank &amp; e-Wallet:
            </div>
            <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:6px; font-size:0.75rem;">
                <span class="brand-badge badge-qris">BCA</span>
                <span class="brand-badge badge-qris">GoPay</span>
                <span class="brand-badge badge-qris">OVO</span>
                <span class="brand-badge badge-qris">DANA</span>
                <span class="brand-badge badge-qris">ShopeePay</span>
                <span class="brand-badge badge-qris">LinkAja</span>
                <span class="brand-badge badge-qris">Mandiri</span>
            </div>
        </div>

    </div>
@endsection
