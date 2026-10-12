@extends('layouts.app')

@section('title', 'Pengaturan QRIS Dinamis - ' . config('app.name'))

@section('content')
    @include('owner.partials.navbar', [
        'current' => 'qris',
        'title' => 'QRIS Dinamis',
        'icon' => '📱',
    ])

    <main class="container">
        <!-- Info Banner -->
        <div class="info-card">
            <div style="font-size: 2rem; flex-shrink: 0;">💳</div>
            <div>
                <h2 style="font-size: 1.15rem; font-weight: 800; color: #1e3a8a; margin-bottom: 4px;">QRIS-ify Gateway (Verified Auto-Paid) + Fallback QR Dinamis Lokal</h2>
                <p style="font-size: 0.85rem; color: #475569; line-height: 1.5;">
                    Sistem ini menggunakan <strong>QRIS-ify API</strong> untuk membuat QRIS Dinamis dengan nominal pas otomatis. Ketika pelanggan membayar, status pesanan <strong>berubah LUNAS secara otomatis</strong> via Webhook. Jika QRIS-ify tidak tersedia, sistem akan fallback ke generator QRIS lokal.
                </p>
            </div>
        </div>

        <!-- QRIS-ify API Settings -->
        <div class="card p-4" style="margin-bottom: 24px; border: 2px solid #10b981; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.1);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.5rem;">⚡</span>
                    <div>
                        <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin:0;">QRIS-ify API Gateway (Auto-Verified)</h3>
                        <p style="font-size: 0.8rem; color: #64748b; margin: 2px 0 0;">Bayar QRIS → LUNAS otomatis. Tanpa klik kasir.</p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span id="qrisifyStatusBadge" class="brand-badge" style="background:#d1fae5; color:#059669;">⚡ Active</span>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.85rem; font-weight:600;">
                        <input type="checkbox" id="qrisifyToggle" onchange="toggleQrisify(this)" style="width:18px; height:18px;">
                        Aktifkan QRIS-ify
                    </label>
                </div>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
                <div>
                    <label class="form-label" for="qrisifyApiKeyInput" style="font-size:0.8rem;">API Key (dari dashboard QRIS-ify):</label>
                    <div style="display:flex; gap:6px;">
                        <input type="password" id="qrisifyApiKeyInput" class="form-control" placeholder="qris_live_..." style="font-family:monospace; font-size:0.8rem;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="toggleApiKeyVisibility()">👁️</button>
                    </div>
                </div>
                <div style="display:flex; align-items:flex-end; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-success" onclick="saveQrisifySettings()" style="flex:1;">
                        💾 Simpan API Key
                    </button>
                    <a href="https://qrisify.adihub.my.id" target="_blank" class="btn btn-outline btn-sm">
                        🔗 Dashboard QRIS-ify
                    </a>
                </div>
            </div>
            <div id="qrisifySettingsResult" style="margin-top:12px; display:none; font-size:0.82rem;"></div>
        </div>

        <div class="qris-grid">
            <!-- Left: Settings Form -->
            <div class="card p-4">
                <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 16px; color: #0f172a;">⚙️ Payload QRIS Statis Toko Anda</h3>

                <!-- QRIS Physical Image Preview (from uploaded merchant file) -->
                <div id="qrisPhysicalPreview" style="text-align:center; margin-bottom:16px; padding:14px; background:#fefce8; border:1px dashed #f59e0b; border-radius:12px;">
                    <div style="font-size:0.75rem; font-weight:700; color:#92400e; margin-bottom:8px;">📸 QRIS Fisik Merchant (Auto-decoded dari foto Anda)</div>
                    <img src="{{ asset_versioned('assets/images/merchant_qris.jpg') }}" alt="QRIS Fisik Merchant" style="max-width:180px; border-radius:10px; border:2px solid #f59e0b; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size:0.72rem; color:#78350f; margin-top:6px;">✅ String QRIS berhasil dibaca otomatis dari foto ini</div>
                </div>

                <form onsubmit="saveQrisSettings(event)">
                    <div class="form-group">
                        <label class="form-label" for="staticQrisInput">Raw String QRIS Statis (EMVCo Payload):</label>
                        <textarea id="staticQrisInput" class="form-control" rows="4" placeholder="00020101021126600014ID.LINKAJA.WWW..." required style="font-family: monospace; font-size: 0.78rem; word-break: break-all;"></textarea>
                        <span style="font-size: 0.75rem; color: #64748b; margin-top: 4px; display: block;">
                            💡 String di atas didapat otomatis dari foto QRIS fisik toko Anda. Anda bisa memperbaruinya kapan saja jika QRIS berubah.
                        </span>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap;">
                        <button type="submit" id="btnSaveQris" class="btn btn-primary" style="flex: 1;">
                            💾 Simpan &amp; Aktifkan QRIS
                        </button>
                        <button type="button" class="btn btn-outline" onclick="resetDefaultQris()">
                            🔄 Reset ke QRIS Toko
                        </button>
                    </div>
                </form>

                <!-- Parsed Info Box -->
                <div id="parsedInfoBox" style="margin-top: 16px; padding: 14px; background: #f0fdf4; border-radius: 12px; border: 1px solid #86efac; display: none;">
                    <div style="display:flex; align-items:center; gap:6px; font-weight: 800; font-size: 0.85rem; color: #166534; margin-bottom: 10px;">
                        <span>✅</span> QRIS Terverifikasi &amp; Aktif
                    </div>
                    <div style="font-size: 0.85rem; line-height: 1.9; color:#15803d;">
                        <div>🏪 Merchant: <strong id="parsedMerchantName" style="color: #166534;">-</strong></div>
                        <div>📍 Kota: <strong id="parsedCity">-</strong></div>
                        <div>📮 Kode Pos: <strong id="parsedPostal">-</strong></div>
                    </div>
                </div>
            </div>

            <!-- Right: Dynamic Simulator & Tester -->
            <div class="card p-4">
                <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 16px; color: #0f172a;">🧪 Simulator &amp; Uji Dynamic QRIS</h3>
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 14px;">
                    Uji coba generate QRIS dengan nominal tertentu dan scan dengan aplikasi e-Wallet (BCA, GoPay, OVO, Dana, ShopeePay) untuk memastikan nominal otomatis terkunci.
                </p>

                <div style="display: flex; gap: 10px; margin-bottom: 16px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label class="form-label" for="testAmount">Nominal Uji Coba (Rp):</label>
                        <input type="number" id="testAmount" class="form-control" value="25000" min="1000" step="500">
                    </div>
                    <button type="button" class="btn btn-success" onclick="testGenerateQris()">
                        ⚡ Generate Dynamic QR
                    </button>
                </div>

                <!-- Dynamic QR Output Display -->
                <div id="dynamicQrResult" style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1;">
                    <div id="qrLoadingText" style="color: #64748b; font-size: 0.9rem;">Klik tombol di atas untuk melihat preview QR Dinamis</div>
                    <div id="qrOutputContainer" style="display: none;">
                        <div class="qris-badge-official">
                            <span style="font-weight: 800; color: #dc2626;">QRIS</span>
                            <span style="font-size: 0.75rem; color: #475569;">Pembayaran Digital Terverifikasi</span>
                        </div>
                        <img id="testQrImage" src="" alt="Dynamic QRIS" style="width: 200px; height: 200px; border-radius: 12px; margin: 12px auto; display: block; background: #fff; padding: 8px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm);">

                        <div style="font-size: 1.25rem; font-weight: 800; color: #2563eb;" id="testFormattedAmount">Rp 25.000</div>
                        <div style="font-size: 0.75rem; color: #059669; font-weight: 700; margin-top: 2px;">✅ Tag 54 &amp; CRC16 Valid</div>

                        <div style="margin-top: 14px; text-align: left; background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.75rem; font-family: monospace; word-break: break-all;" id="testDynamicString"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Universal Webhook & Auto Confirmation -->
        <div class="card p-4" style="margin-top: 28px; border: 2px solid #3b82f6; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.1);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:1.5rem;">🔌</span>
                        <h3 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin:0;">Integrasi API Webhook Otomatis (Auto-Lunas)</h3>
                    </div>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 4px;">
                        Gunakan endpoint ini untuk memverifikasi pembayaran secara otomatis tanpa kasir klik manual. Cocok untuk bot forwarder notifikasi HP atau Payment Gateway.
                    </p>
                </div>
                <span class="brand-badge badge-food" style="background:#eff6ff; color:#2563eb; font-size:0.75rem; border:1px solid #bfdbfe;">
                    ⚡ Universal Webhook Active
                </span>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
                <!-- Left: Webhook Credentials -->
                <div style="background:#f8fafc; padding:18px; border-radius:14px; border:1px solid #e2e8f0;">
                    <h4 style="font-size:0.95rem; font-weight:700; color:#334155; margin-bottom:14px;">🔑 Kredensial Webhook</h4>

                    <div class="form-group mb-3">
                        <label class="form-label" for="webhookUrlInput" style="font-size:0.8rem;">Endpoint URL Webhook (POST):</label>
                        <div style="display:flex; gap:6px;">
                            <input type="text" id="webhookUrlInput" class="form-control" readonly style="font-family:monospace; font-size:0.8rem; background:#fff;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="copyToClipboard('webhookUrlInput', 'URL Webhook disalin!')">📋 Salin</button>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" style="font-size:0.8rem;">Header Verifikasi:</label>
                        <input type="text" class="form-control" readonly value="X-Webhook-Secret" style="font-family:monospace; font-size:0.8rem; background:#fff;">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="webhookSecretInput" style="font-size:0.8rem;">Webhook Secret Key:</label>
                        <div style="display:flex; gap:6px;">
                            <input type="password" id="webhookSecretInput" class="form-control" style="font-family:monospace; font-size:0.8rem; background:#fff;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="toggleSecretVisibility()">👁️</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="copyToClipboard('webhookSecretInput', 'Secret disalin!')">📋 Salin</button>
                        </div>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="saveCustomSecret()" style="font-size:0.78rem;">
                            💾 Simpan Secret
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="regenerateSecretKey()" style="font-size:0.78rem;">
                            🔄 Buat Secret Acak Baru
                        </button>
                    </div>
                </div>

                <!-- Right: Simulator / Direct Tester -->
                <div style="background:#f0fdf4; padding:18px; border-radius:14px; border:1px solid #bbf7d0;">
                    <h4 style="font-size:0.95rem; font-weight:700; color:#166534; margin-bottom:14px;">🧪 Simulator / Tester Webhook</h4>
                    <p style="font-size:0.8rem; color:#15803d; margin-bottom:12px;">
                        Uji coba kirim sinyal webhook langsung untuk melunasi salah satu pesanan secara otomatis:
                    </p>

                    <div class="form-group mb-3">
                        <label class="form-label" for="testWebhookInvoice" style="font-size:0.8rem;">Nomor Invoice Transaksi:</label>
                        <input type="text" id="testWebhookInvoice" class="form-control" placeholder="Contoh: INV-20260925-..." style="background:#fff;">
                    </div>

                    <button type="button" class="btn btn-success" style="width:100%; margin-top:6px;" onclick="runWebhookTest()">
                        🚀 Kirim Sinyal Webhook (Tandai Lunas)
                    </button>

                    <div id="webhookTestResult" style="margin-top:14px; display:none; padding:12px; border-radius:8px; font-family:monospace; font-size:0.75rem; word-break:break-all;"></div>
                </div>
            </div>

            <!-- Bottom: Quick Android / Gateway Guide -->
            <div style="margin-top:20px; padding:16px; background:#fff; border-radius:12px; border:1px dashed #cbd5e1;">
                <div style="font-weight:700; font-size:0.85rem; color:#334155; margin-bottom:6px;">📱 Panduan Menghubungkan ke HP Kasir (Otomatis 100%):</div>
                <div style="font-size:0.8rem; color:#475569; line-height:1.6;">
                    1. Pasang aplikasi pembaca notifikasi di HP tempat akun GoPay / BCA Merchant terpasang (misal: <em>MacroDroid</em> atau <em>Tasker</em>).<br>
                    2. Buat pemicu (trigger): Saat ada notifikasi dari GoPay / BCA dengan kata kunci <em>"Pembayaran diterima"</em>.<br>
                    3. Buat aksi (action): Kirim HTTP POST ke <strong><span class="url-span" id="guideWhUrl">/api/webhook/payment</span></strong> dengan Header <code>X-Webhook-Secret</code> dan body JSON <code>{"message": "[notification_text]"}</code>.<br>
                    4. Server POS kasir akan otomatis memverifikasi dan mengubah status pesanan yang sesuai menjadi <strong>LUNAS</strong> dalam sekejap!
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        const DEFAULT_SAMPLE = @js(\App\Support\QrisHelper::DEFAULT_STATIC_QRIS);
        const qrisSettingsUrl = @js(route('owner.qris.get'));
        const qrisSettingsSaveUrl = @js(route('owner.qris.save'));
        const qrisTestUrl = @js(route('owner.qris.test'));
        const qrisifySettingsUrl = @js(route('owner.qrisify.get'));
        const qrisifySettingsSaveUrl = @js(route('owner.qrisify.save'));
        const webhookConfigUrl = @js(route('owner.webhook.config'));
        const webhookSecretUpdateUrl = @js(route('owner.webhook.update'));
        const webhookSecretRegenerateUrl = @js(route('owner.webhook.regenerate'));
        const webhookPaymentUrl = @js(route('webhooks.payment'));

        async function loadQrisSettings() {
            try {
                const res = await fetch(qrisSettingsUrl);
                const data = await res.json();
                if (data.success) {
                    document.getElementById('staticQrisInput').value = data.data.static_payload;
                    displayParsedInfo(data.data.details);
                    // auto run test
                    testGenerateQris();
                }
            } catch (e) {
                console.error(e);
            }
        }

        function displayParsedInfo(details) {
            if (!details) return;
            const box = document.getElementById('parsedInfoBox');
            document.getElementById('parsedMerchantName').innerText = details.merchant_name || 'Nusantara Merchant';
            document.getElementById('parsedCity').innerText = details.merchant_city || 'Indonesia';
            document.getElementById('parsedPostal').innerText = details.postal_code || '-';
            box.style.display = 'block';
        }

        async function saveQrisSettings(e) {
            e.preventDefault();
            const payload = document.getElementById('staticQrisInput').value.trim();
            const btn = document.getElementById('btnSaveQris');
            btn.disabled = true;
            btn.innerText = 'Menyimpan...';

            try {
                const res = await fetch(qrisSettingsSaveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ static_payload: payload })
                });
                const data = await res.json();
                if (data.success) {
                    alert('✅ ' + data.message);
                    displayParsedInfo(data.data.details);
                    testGenerateQris();
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (err) {
                alert('Gagal simpan: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerText = '💾 Simpan & Aktifkan QRIS';
            }
        }

        async function testGenerateQris() {
            const amount = document.getElementById('testAmount').value;
            const payload = document.getElementById('staticQrisInput').value.trim();

            try {
                const res = await fetch(qrisTestUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ amount, static_payload: payload })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('qrLoadingText').style.display = 'none';
                    const cont = document.getElementById('qrOutputContainer');
                    cont.style.display = 'block';
                    document.getElementById('testQrImage').src = data.data.qr_image_url;
                    document.getElementById('testFormattedAmount').innerText = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
                    document.getElementById('testDynamicString').innerText = 'Payload Dinamis: ' + data.data.dynamic_payload;
                }
            } catch (err) {
                console.error(err);
            }
        }

        function resetDefaultQris() {
            if (confirm('Kembalikan ke string QRIS contoh default?')) {
                document.getElementById('staticQrisInput').value = DEFAULT_SAMPLE;
                testGenerateQris();
            }
        }

        // ================= WEBHOOK INTEGRATION JS =================
        async function loadWebhookConfig() {
            try {
                const res = await fetch(webhookConfigUrl);
                const data = await res.json();
                if (data.success) {
                    document.getElementById('webhookUrlInput').value = data.data.webhook_url;
                    document.getElementById('webhookSecretInput').value = data.data.webhook_secret;
                    const guideEl = document.getElementById('guideWhUrl');
                    if (guideEl) guideEl.innerText = data.data.webhook_url;
                }
            } catch (err) {
                console.error('Failed to load webhook config:', err);
            }
        }

        // ================= QRIS-ify SETTINGS JS =================
        async function loadQrisifySettings() {
            try {
                const res = await fetch(qrisifySettingsUrl);
                const data = await res.json();
                if (data.success) {
                    document.getElementById('qrisifyApiKeyInput').value = data.data.api_key;
                    document.getElementById('qrisifyToggle').checked = data.data.enabled;
                    updateQrisifyBadge(data.data.enabled);
                }
            } catch (e) {
                console.error('Failed to load QRIS-ify config:', e);
            }
        }

        function updateQrisifyBadge(enabled) {
            const badge = document.getElementById('qrisifyStatusBadge');
            if (!badge) return;
            if (enabled) {
                badge.style.background = '#d1fae5';
                badge.style.color = '#059669';
                badge.innerText = '⚡ Active';
            } else {
                badge.style.background = '#fee2e2';
                badge.style.color = '#dc2626';
                badge.innerText = '⏸ Disabled';
            }
        }

        async function saveQrisifySettings() {
            const apiKey = document.getElementById('qrisifyApiKeyInput').value.trim();
            const enabled = document.getElementById('qrisifyToggle').checked;
            const resultBox = document.getElementById('qrisifySettingsResult');

            if (!apiKey) {
                alert('Masukkan API Key QRIS-ify terlebih dahulu');
                return;
            }

            resultBox.style.display = 'block';
            resultBox.style.background = '#eff6ff';
            resultBox.style.border = '1px solid #bfdbfe';
            resultBox.style.color = '#1e40af';
            resultBox.style.padding = '10px 12px';
            resultBox.style.borderRadius = '8px';
            resultBox.innerText = 'Menyimpan API Key...';

            try {
                const res = await fetch(qrisifySettingsSaveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ api_key: apiKey, enabled })
                });
                const data = await res.json();
                if (data.success) {
                    resultBox.style.background = '#ecfdf5';
                    resultBox.style.border = '1px solid #10b981';
                    resultBox.style.color = '#065f46';
                    resultBox.innerText = '✅ ' + data.message;
                    updateQrisifyBadge(enabled);
                } else {
                    resultBox.style.background = '#fef2f2';
                    resultBox.style.border = '1px solid #f87171';
                    resultBox.style.color = '#991b1b';
                    resultBox.innerText = '❌ Gagal menyimpan: ' + data.message;
                }
            } catch (e) {
                resultBox.innerText = 'Error: ' + e.message;
            }
        }

        async function toggleQrisify(checkbox) {
            const enabled = checkbox.checked;
            updateQrisifyBadge(enabled);
            try {
                await fetch(qrisifySettingsSaveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enabled })
                });
            } catch (e) {
                console.error(e);
            }
        }

        function toggleApiKeyVisibility() {
            const input = document.getElementById('qrisifyApiKeyInput');
            input.type = (input.type === 'password') ? 'text' : 'password';
        }

        function toggleSecretVisibility() {
            const input = document.getElementById('webhookSecretInput');
            input.type = (input.type === 'password') ? 'text' : 'password';
        }

        function copyToClipboard(elementId, successMsg) {
            const input = document.getElementById(elementId);
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(() => {
                alert('📋 ' + (successMsg || 'Disalin ke clipboard!'));
            }).catch(() => {
                document.execCommand('copy');
                alert('📋 ' + (successMsg || 'Disalin ke clipboard!'));
            });
        }

        async function saveCustomSecret() {
            const secret = document.getElementById('webhookSecretInput').value.trim();
            if (secret.length < 8) {
                alert('Secret minimal 8 karakter');
                return;
            }

            try {
                const res = await fetch(webhookSecretUpdateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ secret })
                });
                const data = await res.json();
                if (data.success) {
                    alert('✅ ' + data.message);
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }

        async function regenerateSecretKey() {
            if (!confirm('Apakah Anda yakin ingin membuat Webhook Secret acak baru? Anda harus memperbarui konfigurasi di bot / aplikasi forwarder Anda.')) return;

            try {
                const res = await fetch(webhookSecretRegenerateUrl, { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('webhookSecretInput').value = data.data.webhook_secret;
                    alert('✅ Webhook Secret baru berhasil dibuat!');
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }

        async function runWebhookTest() {
            const invoice = document.getElementById('testWebhookInvoice').value.trim();
            const secret = document.getElementById('webhookSecretInput').value.trim();
            const resultBox = document.getElementById('webhookTestResult');

            if (!invoice) {
                alert('Masukkan nomor invoice terlebih dahulu (misal: INV-20260925-...)');
                return;
            }

            resultBox.style.display = 'block';
            resultBox.style.background = '#eff6ff';
            resultBox.style.border = '1px solid #bfdbfe';
            resultBox.style.color = '#1e40af';
            resultBox.innerText = 'Mengirim sinyal webhook POST ke ' + webhookPaymentUrl + '...';

            try {
                const res = await fetch(webhookPaymentUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Webhook-Secret': secret
                    },
                    body: JSON.stringify({
                        invoice_number: invoice,
                        status: 'paid'
                    })
                });

                const data = await res.json();
                if (data.success) {
                    resultBox.style.background = '#ecfdf5';
                    resultBox.style.border = '1px solid #10b981';
                    resultBox.style.color = '#065f46';
                    resultBox.innerHTML = `<strong>✅ SUKSES (HTTP 200)</strong><br>${data.message}<br><br>Data Response:<br>${JSON.stringify(data.data, null, 2)}`;
                } else {
                    resultBox.style.background = '#fef2f2';
                    resultBox.style.border = '1px solid #f87171';
                    resultBox.style.color = '#991b1b';
                    resultBox.innerHTML = `<strong>❌ GAGAL (${res.status})</strong><br>${data.message}`;
                }
            } catch (err) {
                resultBox.style.background = '#fef2f2';
                resultBox.style.border = '1px solid #f87171';
                resultBox.style.color = '#991b1b';
                resultBox.innerText = 'Gagal mengirim request: ' + err.message;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadQrisSettings();
            loadWebhookConfig();
            loadQrisifySettings();
        });
    </script>
@endpush
