@extends('layouts.app')

@section('title', 'Login Owner - ' . config('app.name'))

@section('content')
    <div style="display:flex; align-items:center; justify-content:center; min-height:100vh; background:#0f172a;">
        <div style="background:#fff; width:100%; max-width:440px; padding:32px; border-radius:18px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.5); margin:20px;">
            <div style="text-align:center; margin-bottom:24px;">
                <div style="font-size:2.5rem; margin-bottom:8px;">🔐</div>
                <h2 style="font-size:1.4rem; font-weight:800; color:#0f172a;">Portal Owner &amp; Kasir</h2>
                <p style="font-size:0.85rem; color:#64748b;">Masuk untuk memantau pesanan real-time &amp; laporan</p>
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
                                title="Lihat password">👁️</button>
                    </div>
                </div>

                <button type="submit" id="btnLogin" class="btn btn-primary" style="width:100%; padding:12px; margin-top:8px;">
                    Masuk ke Dashboard
                </button>
            </form>

            <div style="margin-top:24px; padding-top:16px; border-top:1px solid #e2e8f0; text-align:center;">
                <div style="font-size:0.8rem; font-weight:700; color:#64748b; margin-bottom:10px;">⚡ Quick Login (Demo 1-Click):</div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-food" style="flex:1; font-size:0.75rem; padding:6px;" onclick="quickLogin('owner_makanan')">
                        🍲 Owner Makanan
                    </button>
                    <button type="button" class="btn btn-drink" style="flex:1; font-size:0.75rem; padding:6px;" onclick="quickLogin('owner_minuman')">
                        🍹 Owner Minuman
                    </button>
                </div>
                <div style="margin-top:16px;">
                    <a href="{{ route('menu') }}" style="font-size:0.85rem; color:#2563eb; text-decoration:none;">&larr; Kembali ke Menu Pelanggan</a>
                </div>
            </div>
        </div>
    </div>
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
            btn.textContent = revealed ? '👁️' : '🙈';
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
