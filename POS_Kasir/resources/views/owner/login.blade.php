@extends('layouts.app')

@section('title', 'Login Owner - ' . config('app.name'))

@section('body-class', 'auth-body')

@section('content')
    <main class="auth-shell">
        <aside class="auth-brand" aria-hidden="true"
               style="--auth-photo: url('{{ asset_versioned('assets/images/taman-cafe.jpg') }}')">
            <div class="auth-brand-logo">{{ config('app.name') }}</div>

            <div class="auth-brand-copy">
                <h1>Kelola pesanan &amp; laporan dalam satu tempat.</h1>
                <p>Pantau pesanan masuk secara real-time, cek pembayaran QRIS, dan lihat laporan penjualan harian.</p>
            </div>

            <ul class="auth-brand-points">
                <li>Notifikasi pesanan real-time</li>
                <li>Pembayaran QRIS &amp; tunai</li>
                <li>Laporan penjualan otomatis</li>
            </ul>
        </aside>

        <section class="auth-panel">
            <div class="auth-card">
                <header class="auth-header">
                    <h2>Portal Owner &amp; Kasir</h2>
                    <p>Masuk untuk memantau pesanan real-time &amp; laporan.</p>
                </header>

                <div id="loginError" class="auth-error" role="alert" @unless($errors->has('login')) hidden @endunless>{{ $errors->first('login') }}</div>

                <form id="loginForm" method="POST" action="{{ route('login') }}" onsubmit="handleLogin(event)" novalidate>
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control auth-input"
                               value="{{ old('username') }}" placeholder="Masukkan username"
                               autocomplete="username" autocapitalize="none" spellcheck="false" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" class="form-control auth-input"
                                   placeholder="Masukkan password" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" id="togglePassword"
                                    onclick="togglePasswordVisibility()" aria-label="Lihat password"
                                    title="Lihat password"><span class="eye-icon" aria-hidden="true"></span></button>
                        </div>
                    </div>

                    <button type="submit" id="btnLogin" class="btn btn-primary auth-submit">
                        <span class="auth-spinner" aria-hidden="true"></span>
                        <span id="btnLoginText">Masuk ke Dashboard</span>
                    </button>
                </form>

                <div class="auth-divider"><span>Demo cepat</span></div>

                <div class="auth-quick">
                    <button type="button" class="auth-quick-btn" onclick="quickLogin('owner_makanan')">
                        <span class="auth-dot auth-dot-food"></span> Owner Makanan
                    </button>
                    <button type="button" class="auth-quick-btn" onclick="quickLogin('owner_minuman')">
                        <span class="auth-dot auth-dot-drink"></span> Owner Minuman
                    </button>
                </div>

                <a href="{{ route('menu') }}" class="auth-back">&larr; Kembali ke Menu Pelanggan</a>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        function quickLogin(role) {
            document.getElementById('username').value = role;
            document.getElementById('password').value = 'password123';
            document.getElementById('loginForm').requestSubmit();
        }

        function togglePasswordVisibility() {
            const input = document.getElementById('password');
            const btn = document.getElementById('togglePassword');
            const revealed = input.type === 'text';
            const label = revealed ? 'Lihat password' : 'Sembunyikan password';

            input.type = revealed ? 'password' : 'text';
            btn.classList.toggle('is-revealed', !revealed);
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
        }

        function showLoginError(message) {
            const box = document.getElementById('loginError');
            box.textContent = message;
            box.hidden = !message;
        }

        // The form posts natively (no fetch), so login works in every browser.
        // This only checks empty fields and shows the loading state.
        function handleLogin(e) {
            const btn = document.getElementById('btnLogin');
            const username = document.getElementById('username');
            const password = document.getElementById('password');

            if (btn.disabled) {
                e.preventDefault();
                return;
            }

            username.value = username.value.trim();
            password.value = password.value.trim();

            if (!username.value || !password.value) {
                e.preventDefault();
                showLoginError('Username dan password wajib diisi.');
                return;
            }

            showLoginError('');
            btn.disabled = true;
            btn.classList.add('is-loading');
            document.getElementById('btnLoginText').textContent = 'Memeriksa...';
        }

        // Re-enable the button when the page is restored with the Back button.
        window.addEventListener('pageshow', function () {
            const btn = document.getElementById('btnLogin');
            btn.disabled = false;
            btn.classList.remove('is-loading');
            document.getElementById('btnLoginText').textContent = 'Masuk ke Dashboard';
        });
    </script>
@endpush
