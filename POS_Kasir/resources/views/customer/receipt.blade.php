@extends('layouts.app')

@section('title', 'Struk Pesanan - ' . $sale->invoice_number)
@section('body-class', 'bg-receipt')

@php
    $remainingSeconds = $qrisData['remaining_seconds'] ?? 0;
    $countdownInitial = $remainingSeconds > 0
        ? sprintf('%02d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60)
        : 'Kadaluarsa';
    $receiptUrl = route('receipt', $sale->invoice_number);
@endphp

@push('head')
    {{-- Kertas thermal: 58mm (lebar cetak ±48mm) atau 80mm (±72mm). Tanpa margin halaman. --}}
    <style>
        @media print {
            @page { margin: 0; }
        }
    </style>
@endpush

@section('content')
    <!-- Navbar -->
    <header class="navbar no-print">
        <div class="navbar-container">
            <a href="{{ route('menu') }}" class="brand-logo">
                <span>🍽️</span> {{ config('app.name') }}
            </a>
            <div class="nav-actions">
                <a href="{{ route('menu') }}" class="btn btn-outline btn-sm">&larr; Menu Utama</a>
            </div>
        </div>
    </header>

    <div class="container receipt-container">
        <!-- Dynamic QRIS Payment Card (Shown if QRIS and still Unpaid) -->
        @if ($sale->payment_method === 'qris' && $sale->payment_status === 'unpaid' && $qrisData)
            <div class="qris-pay-card no-print" id="qrisPaymentBox">
                <div class="qris-header">
                    <div class="qris-brand-logo">
                        <span style="font-size: 1.3rem; font-weight: 900; color: #dc2626; letter-spacing: 1px;">QRIS</span>
                        <span style="font-size: 0.75rem; color: #475569; font-weight: 600;">QR Code Standar Pembayaran Nasional</span>
                    </div>
                    <div class="qris-timer" id="qrisTimerBadge">⏱️ Sisa Waktu: <strong id="countdown" data-remaining-seconds="{{ $remainingSeconds }}">{{ $countdownInitial }}</strong></div>
                </div>

                <div class="qris-qr-wrapper">
                    <img id="qrisQrImg" src="{{ $qrisData['image_url'] }}" alt="QRIS Dinamis" class="qris-qr-image">
                    <div class="qris-scan-instruction">
                        Arahkan kamera e-Wallet / Mobile Banking Anda ke QR Code di atas
                    </div>
                </div>

                <div class="qris-amount-box">
                    <div style="font-size: 0.8rem; color: #64748b;">Total Nominal Tagihan (via QRIS-ify):</div>
                    <div class="qris-amount-val">{{ rupiah($qrisData['amount']) }}</div>
                    @if ((float) $qrisData['amount'] != (float) $sale->total_amount)
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">
                            Harga belanja {{ rupiah($sale->total_amount) }} + kode unik {{ rupiah((float) $qrisData['amount'] - (float) $sale->total_amount, false) }}
                        </div>
                    @endif
                    <div style="font-size: 0.75rem; color: #059669; font-weight: 700; margin-top: 2px;">
                        ✨ QRIS-ify: Nominal pas otomatis terisi saat di-scan
                    </div>
                </div>

                <!-- Supported e-Wallet Icons -->
                <div class="qris-wallets">
                    <span class="wallet-badge">BCA Mobile</span>
                    <span class="wallet-badge">GoPay</span>
                    <span class="wallet-badge">OVO</span>
                    <span class="wallet-badge">DANA</span>
                    <span class="wallet-badge">ShopeePay</span>
                    <span class="wallet-badge">LinkAja</span>
                    <span class="wallet-badge">Livin' Mandiri</span>
                </div>

                <!-- Status Check Indicator -->
                <div class="qris-status-bar" id="liveStatusText" style="display:flex; flex-direction:column; gap:8px; align-items:center;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="pulse-dot"></span>
                        <span>Menunggu verifikasi pembayaran kasir...</span>
                    </div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:normal; max-width:320px; line-height:1.4;">
                        Setelah Anda selesai membayar di aplikasi e-wallet, kasir akan memverifikasi mutasi dan status struk ini akan <strong>otomatis berubah menjadi LUNAS</strong>.
                    </div>
                    <div style="display:flex; gap:8px; margin-top:4px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="checkPaymentStatusNow()" style="font-size:0.75rem; padding:4px 10px;">
                            🔄 Periksa Ulang Status
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Digital Thermal Receipt Box -->
        <div class="receipt-box" id="thermalReceipt">
            <!-- Header Struk -->
            <div style="text-align:center;">
                <div style="font-size:2.2rem; margin-bottom:4px;">🏪</div>
                <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a;">{{ config('app.name') }}</h2>
                <p style="font-size:0.8rem; color:#64748b;">1 QR Code Self-Order &amp; POS</p>
                <div class="receipt-divider"></div>
            </div>

            <!-- Detail Transaksi -->
            <div style="font-size:0.85rem; line-height:1.7;">
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">No. Invoice:</span>
                    <strong style="color:#0f172a;">{{ $sale->invoice_number }}</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Waktu:</span>
                    <span>{{ $sale->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Pelanggan:</span>
                    <strong>{{ $sale->customer_name }}</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Layanan:</span>
                    <strong>{{ $sale->service_type === 'dine_in' ? 'Makan di Tempat' : 'Bungkus (Takeaway)' }}</strong>
                </div>
                @if ($sale->table_number)
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#64748b;">No. Meja:</span>
                        <strong>{{ $sale->table_number }}</strong>
                    </div>
                @endif
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Metode Bayar:</span>
                    <strong style="text-transform:uppercase;">{{ $sale->payment_method }}</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Status Bayar:</span>
                    <strong id="receiptPaymentStatus" style="color:{{ $sale->isPaid() ? '#059669' : '#dc2626' }};">
                        {{ $sale->isPaid() ? '✅ LUNAS' : '⏳ MENUNGGU PEMBAYARAN' }}
                    </strong>
                </div>
                @if ($sale->notes)
                    <div style="display:flex; justify-content:space-between; margin-top:4px;">
                        <span style="color:#64748b;">Catatan:</span>
                        <span style="font-style:italic;">{{ $sale->notes }}</span>
                    </div>
                @endif
            </div>

            <div class="receipt-divider"></div>

            <!-- List Menu yang Dipesan -->
            <div style="font-size:0.85rem;">
                <div style="font-weight:700; margin-bottom:8px; color:#334155;">Daftar Pesanan:</div>
                @foreach ($items as $item)
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <div>
                            <div style="font-weight:600; color:#0f172a;">{{ $item['product_name'] }}</div>
                            <div style="font-size:0.75rem; color:#64748b;">
                                {{ $item['quantity'] }} x {{ rupiah($item['unit_price']) }}
                                <span class="brand-badge {{ $item['owner_type'] === 'makanan' ? 'badge-food' : 'badge-drink' }}" style="font-size:0.65rem; padding:1px 6px;">
                                    {{ ucfirst($item['owner_type']) }}
                                </span>
                            </div>
                            @if (!empty($item['notes']))
                                <div style="font-size:0.725rem; color:#92400e; background:#fef3c7; padding:2px 6px; border-radius:4px; display:inline-block; margin-top:3px;">
                                    📝 {{ $item['notes'] }}
                                </div>
                            @endif
                        </div>
                        <div style="font-weight:700; color:#0f172a;">{{ rupiah($item['subtotal']) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="receipt-divider"></div>

            <!-- Total Amount -->
            <div style="display:flex; justify-content:space-between; align-items:center; font-size:1.15rem; font-weight:800;">
                <span>TOTAL AKHIR:</span>
                <span style="color:var(--primary);">{{ rupiah($sale->total_amount) }}</span>
            </div>

            @if ($sale->payment_method === 'cash' && $sale->cash_received !== null)
                <div style="font-size:0.85rem; line-height:1.7; margin-top:6px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#64748b;">Tunai Diterima:</span>
                        <span>{{ rupiah($sale->cash_received) }}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-weight:700;">
                        <span>Kembalian:</span>
                        <span>{{ rupiah($sale->cash_change) }}</span>
                    </div>
                </div>
            @endif

            <div class="receipt-divider"></div>

            <!-- QR Code Struk & Terima Kasih -->
            <div style="text-align:center; padding:8px 0;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode($receiptUrl) }}" alt="QR Invoice" style="border-radius:8px; margin-bottom:6px;">
                <p style="font-size:0.75rem; color:#64748b;">Scan untuk cek status pesanan kapan saja</p>
                <p style="font-size:0.85rem; font-weight:700; margin-top:8px; color:#1e293b;">Terima Kasih & Selamat Menikmati!</p>
            </div>

            <!-- Tombol Aksi Mobile Friendly -->
            <div class="receipt-actions no-print">
                <div class="print-controls">
                    <select id="paperSize" class="form-control" aria-label="Ukuran kertas struk" onchange="applyPaperSize(this.value)">
                        <option value="58">Kertas 58mm</option>
                        <option value="80">Kertas 80mm</option>
                    </select>
                    <button type="button" onclick="printThermalReceipt()" class="btn btn-primary">
                        🖨️ Cetak Struk
                    </button>
                </div>
                <a href="https://api.whatsapp.com/send?phone={{ preg_replace('/^0/', '62', $sale->customer_phone) }}&text={{ urlencode('Halo ' . $sale->customer_name . ', berikut adalah struk digital pesanan Anda (' . $sale->invoice_number . '): ' . $receiptUrl) }}" target="_blank" class="btn btn-success" style="width:100%;">
                    📲 Kirim Struk ke WhatsApp
                </a>
            </div>
        </div>

        <!-- Struk Thermal: hanya tampil saat dicetak -->
        <div class="thermal-print" aria-hidden="true">
            <div class="tp-center tp-title">{{ config('app.name') }}</div>
            <div class="tp-center">{{ config('app.address') }}</div>
            <div class="tp-line"></div>

            <div class="tp-row"><span>No</span><span>{{ $sale->invoice_number }}</span></div>
            <div class="tp-row"><span>Tanggal</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
            <div class="tp-row"><span>Pelanggan</span><span>{{ $sale->customer_name }}</span></div>
            <div class="tp-row">
                <span>Layanan</span>
                <span>{{ $sale->service_type === 'dine_in' ? 'Dine In' : 'Takeaway' }}@if ($sale->table_number) · Meja {{ $sale->table_number }}@endif</span>
            </div>
            <div class="tp-line"></div>

            @foreach ($items as $item)
                <div class="tp-item">
                    <div>{{ $item['product_name'] }}</div>
                    <div class="tp-row">
                        <span>{{ $item['quantity'] }} x {{ rupiah($item['unit_price'], false) }}</span>
                        <span>{{ rupiah($item['subtotal'], false) }}</span>
                    </div>
                    @if (!empty($item['notes']))
                        <div class="tp-note">* {{ $item['notes'] }}</div>
                    @endif
                </div>
            @endforeach
            <div class="tp-line"></div>

            <div class="tp-row tp-total"><span>TOTAL</span><span>{{ rupiah($sale->total_amount) }}</span></div>
            @if ($sale->payment_method === 'cash' && $sale->cash_received !== null)
                <div class="tp-row"><span>Tunai</span><span>{{ rupiah($sale->cash_received) }}</span></div>
                <div class="tp-row tp-bold"><span>Kembalian</span><span>{{ rupiah($sale->cash_change) }}</span></div>
            @endif
            <div class="tp-row">
                <span>Bayar</span>
                <span>{{ strtoupper($sale->payment_method) }} · <span id="tpPaymentStatus">{{ $sale->isPaid() ? 'LUNAS' : 'BELUM LUNAS' }}</span></span>
            </div>
            @if ($sale->notes)
                <div class="tp-note">Catatan: {{ $sale->notes }}</div>
            @endif
            <div class="tp-line"></div>

            <div class="tp-center tp-bold">Terima kasih &amp; selamat menikmati!</div>
            <div class="tp-center">Simpan struk ini sebagai bukti pembayaran</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const invoiceNumber = @js($sale->invoice_number);
        const statusUrl = @js(route('orders.status', $sale->invoice_number));
        let currentPaymentStatus = @js($sale->payment_status);

        // ===== Cetak struk thermal 58mm / 80mm =====
        function applyPaperSize(size) {
            const paper = size === '80' ? '80' : '58';
            document.documentElement.dataset.paper = paper;
            const select = document.getElementById('paperSize');
            if (select) select.value = paper;
            try { localStorage.setItem('receiptPaperSize', paper); } catch (e) {}
        }

        function printThermalReceipt() {
            applyPaperSize(document.getElementById('paperSize')?.value);
            window.print();
        }

        (function initThermalPrint() {
            let saved = '58';
            try { saved = localStorage.getItem('receiptPaperSize') || '58'; } catch (e) {}

            // ?print=1 dari dashboard kasir langsung membuka dialog cetak (?print=80 untuk paksa 80mm).
            const printParam = new URLSearchParams(window.location.search).get('print');
            applyPaperSize(printParam === '58' || printParam === '80' ? printParam : saved);

            if (printParam) {
                window.addEventListener('load', () => setTimeout(() => window.print(), 300));
            }
        })();

        if (currentPaymentStatus === 'unpaid') {
            const pollTimer = setInterval(async () => {
                try {
                    const res = await fetch(statusUrl);
                    const data = await res.json();
                    if (data.success && data.data.payment_status === 'paid') {
                        currentPaymentStatus = 'paid';
                        clearInterval(pollTimer);

                        const qrisBox = document.getElementById('qrisPaymentBox');
                        if (qrisBox) {
                            qrisBox.innerHTML = `
                                <div style="text-align:center; padding:20px; background:#ecfdf5; border-radius:16px; border:2px solid #10b981;">
                                    <div style="font-size:3rem; margin-bottom:6px;">🎉</div>
                                    <h3 style="color:#065f46; font-size:1.25rem; font-weight:800;">Pembayaran QRIS Berhasil!</h3>
                                    <p style="color:#047857; font-size:0.9rem; margin-top:4px;">Pesanan Anda telah diteruskan ke bagian dapur untuk segera diproses.</p>
                                </div>
                            `;
                        }
                        const statusEl = document.getElementById('receiptPaymentStatus');
                        if (statusEl) {
                            statusEl.innerText = '✅ LUNAS';
                            statusEl.style.color = '#059669';
                        }
                        const printStatusEl = document.getElementById('tpPaymentStatus');
                        if (printStatusEl) printStatusEl.innerText = 'LUNAS';
                    }
                } catch (error) {
                    console.error('Gagal memeriksa status pembayaran:', error);
                }
            }, 3500);

            async function checkPaymentStatusNow() {
                try {
                    const res = await fetch(statusUrl);
                    const data = await res.json();
                    if (data.success && data.data.payment_status === 'paid') {
                        currentPaymentStatus = 'paid';
                        location.reload();
                    } else {
                        alert('Status saat ini masih MENUNGGU VERIFIKASI. Kasir sedang memeriksa mutasi pembayaran Anda.');
                    }
                } catch (e) {
                    alert('Gagal memeriksa status: ' + e.message);
                }
            }

            // 15-minute Countdown Timer
            const timerEl = document.getElementById('countdown');
            if (timerEl) {
                const initialRemainingSeconds = Math.max(0, Number(timerEl.dataset.remainingSeconds) || 0);
                const expiresAt = Date.now() + (initialRemainingSeconds * 1000);
                let interval;

                const updateCountdown = () => {
                    const seconds = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));

                    if (seconds <= 0) {
                        if (interval) clearInterval(interval);
                        timerEl.innerText = 'Kadaluarsa';
                        return;
                    }

                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = seconds % 60;
                    timerEl.innerText = `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
                };

                updateCountdown();
                if (initialRemainingSeconds > 0) {
                    interval = setInterval(updateCountdown, 250);
                }
            }
        }
    </script>
@endpush
