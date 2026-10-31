@extends('layouts.app')

@section('title', 'Login Owner - ' . config('app.name'))

@section('body-class', 'portal-login')

@push('head')
<style>
    .portal-login { background: #f7f5ef; color: #252825; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    .portal-login .portal-shell { min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; position: relative; isolation: isolate; overflow: hidden; }
    .portal-shell::before { content: ''; position: absolute; inset: -6px; z-index: -1; background: url('{{ asset('assets/images/ChatGPT-Image-Oct-2_-2026_-09_13_14-PM-1.png') }}') center / cover; opacity: .17; filter: blur(4px); }
    .portal-login .portal-card { background: #fff; width: 100%; max-width: 440px; padding: 36px 32px; border: 1px solid #d3dbd0; border-radius: 18px; box-shadow: 0 16px 40px rgba(37,56,45,.08); }
    .portal-heading { text-align: center; margin-bottom: 22px; }
    .portal-mark { width: 52px; height: 52px; margin: 0 auto 12px; border-radius: 50%; background: #dde6da; color: #25382d; display: grid; place-items: center; }
    .portal-brand { font-size: 11px; font-weight: 700; letter-spacing: .08em; margin-bottom: 4px; }
    .portal-heading h1 { font-size: 24px; line-height: 1.2; font-weight: 700; margin: 0; }
    .portal-heading p { font-size: 13px; line-height: 1.4; color: #70756f; margin: 8px 0 0; }
    .portal-login .form-group { margin-bottom: 16px; }
    .portal-login .form-label { color: #252825; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
    .portal-login .form-control { height: 48px; border: 1px solid #d3dbd0; border-radius: 8px; padding: 0 14px; font-size: 14px; background: white; }
    .portal-login .password-field input { padding-right: 48px; }
    .portal-login .password-toggle { color: #70756f; }
    .portal-login .portal-submit { width: 100%; min-height: 50px; margin-top: 4px; border-radius: 8px; background: #25382d; color: white; font-size: 15px; font-weight: 600; }
    .portal-quick { border-top: 1px dashed #d3dbd0; margin-top: 20px; padding-top: 18px; text-align: center; }
    .portal-quick-title { font-size: 12px; color: #70756f; margin-bottom: 12px; }
    .portal-quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .portal-login .portal-quick-button { min-height: 40px; padding: 8px; background: #dde6da; border: 1px solid #d3dbd0; border-radius: 8px; color: #252825; font-size: 12px; font-weight: 600; cursor: pointer; }
    .portal-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; background: #25382d; }
    .portal-back { display: inline-block; margin-top: 24px; color: #70756f; text-decoration: none; font-size: 13px; font-weight: 600; }
    .portal-login button:focus-visible, .portal-login a:focus-visible { outline: 3px solid #496a50; outline-offset: 3px; }
    @media(max-width: 480px) { .portal-login .portal-card { padding: 28px 22px; } }
</style>
@endpush

@section('content')
    <main class="portal-shell">
        <div class="portal-card">
            <div class="portal-heading">
                <div class="portal-mark"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 1v3m4-3v3m4-3v3"/></svg></div>
                <div class="portal-brand">TAMAN CAFE · MAKASSAR</div>
                <h1>Portal Owner &amp; Kasir</h1>
                <p>Masuk untuk memantau pesanan real-time, KDS dapur &amp; laporan bisnis.</p>
            </div>

            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control"
                           placeholder="owner_makanan / owner_minuman / admin_kasir"
                           autocomplete="username" autocapitalize="none" spellcheck="false" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" id="togglePassword"
                                onclick="togglePasswordVisibility()" aria-label="Lihat password"
                                title="Lihat password"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                    </div>
                </div>

                <button type="submit" id="btnLogin" class="btn portal-submit">
                    Masuk ke Dashboard &nbsp; →
                </button>
            </form>

            <div class="portal-quick">
                <div class="portal-quick-title">ϟ Akses Cepat Demo (1-Click Login):</div>
                <div class="portal-quick-grid">
                    <button type="button" class="portal-quick-button" onclick="quickLogin('owner_makanan')">
                        <span class="portal-dot" style="background:#9b641c"></span>Owner Makanan
                    </button>
                    <button type="button" class="portal-quick-button" onclick="quickLogin('owner_minuman')">
                        <span class="portal-dot" style="background:#2f7a4d"></span>Owner Minuman
                    </button>
                    <button type="button" class="portal-quick-button" onclick="quickLogin('admin_kasir')">
                        <span class="portal-dot" style="background:#285c83"></span>Kasir &amp; Dapur
                    </button>
                </div>
                <div>
                    <a href="{{ route('menu') }}" class="portal-back">&larr; Kembali ke Menu Pelanggan</a>
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        const loginUrl = @js(route('login'));

        function quickLogin(role) {
            document.getElementById('username').value = role;
            document.getElementById('password').value = 'password123';
            document.getElementById('loginForm').dispatchEvent(new Event('submit'));
        }

        function togglePasswordVisibility() {
            const input = document.getElementById('password');
            const btn = document.getElementById('togglePassword');
            const revealed = input.type === 'text';

            input.type = revealed ? 'password' : 'text';
            btn.setAttribute('aria-pressed', String(!revealed));
            btn.setAttribute('aria-label', revealed ? 'Lihat password' : 'Sembunyikan password');
            btn.setAttribute('title', revealed ? 'Lihat password' : 'Sembunyikan password');
        }

        async function handleLogin(e) {
            e.preventDefault();
            const btn = document.getElementById('btnLogin');
            btn.disabled = true;
            btn.innerText = 'Memeriksa...';

            const formData = new FormData();
            formData.append('username', document.getElementById('username').value.trim());
            formData.append('password', document.getElementById('password').value.trim());

            try {
                const res = await fetch(loginUrl, { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    window.location.href = data.data.redirect;
                } else {
                    alert(data.message);
                    btn.disabled = false;
                    btn.innerText = 'Masuk ke Dashboard';
                }
            } catch (err) {
                alert('Gagal login: ' + err.message);
                btn.disabled = false;
                btn.innerText = 'Masuk ke Dashboard';
            }
        }
    </script>
@endpush
