<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — Sistem K3</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="w-full max-w-sm">

    {{-- CARD --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">

        {{-- LOGO / HEADER --}}
        <div class="mb-7 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-600 rounded-xl mb-4">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <h1 class="text-lg font-semibold text-gray-900">Sistem Monitoring K3</h1>
            <p class="text-sm text-gray-400 mt-1">Masuk untuk melanjutkan</p>
        </div>
        
        <div id="error-box" class="hidden mb-4 px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-sm text-red-600"></div>

        {{-- FORM --}}
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1.5" for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    placeholder="email@perusahaan.com"
                    class="w-full text-sm border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    autofocus
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1.5" for="password">Password</label>
                <div class="relative">
                    <input
                        type="password"
                        id="password"
                        placeholder="••••••••"
                        class="w-full text-sm border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-10"
                    />
                    <button type="button" onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        aria-label="Tampilkan/sembunyikan password">

                        {{-- Ikon mata terbuka: ditampilkan saat password tersembunyi --}}
                        <svg id="eye-open" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>

                        {{-- Ikon mata tertutup: ditampilkan saat password terlihat --}}
                        <svg id="eye-closed" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button
                onclick="doLogin()"
                id="btn-login"
                class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors">
                Masuk
            </button>
        </div>
    </div>

    <p class="text-center text-xs text-gray-400 mt-5">
        {{ config('app.name') }} &copy; {{ date('Y') }}
    </p>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const isHidden = input.type === 'password';

    input.type = isHidden ? 'text' : 'password';

    //Toggle ikon sesuai state
    document.getElementById('eye-open').classList.toggle('hidden', isHidden);
    document.getElementById('eye-closed').classList.toggle('hidden', !isHidden);
}

function showError(msg) {
    const box = document.getElementById('error-box');
    box.textContent = msg;
    box.classList.remove('hidden');
}

function hideError() {
    document.getElementById('error-box').classList.add('hidden');
}

function setLoading(loading) {
    const btn = document.getElementById('btn-login');
    btn.disabled = loading;
    btn.textContent = loading ? 'Memproses...' : 'Masuk';
    btn.classList.toggle('opacity-60', loading);
    btn.classList.toggle('cursor-not-allowed', loading);
}

async function doLogin() {
    hideError();

    const emailInput    = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const email         = emailInput.value.trim();
    const password      = passwordInput.value;

    if (!email || !password) {
        showError('Email dan password wajib diisi.');
        return;
    }

    // Validasi format email di client sebelum hit server
    if (!emailInput.checkValidity()) {
        showError('Format email tidak valid.');
        return;
    }

    setLoading(true);

    try {
        const res = await fetch('/login', {
            method: 'POST',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-XSRF-TOKEN':  getCookie('XSRF-TOKEN'),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ email, password }),
        });

        const data = await res.json();

        if (!res.ok) {
            showError(data.message ?? 'Email atau password salah.');
            return;
        }

        // Login berhasil → session sudah dibuat → redirect ke dashboard
        window.location.href = '/dashboard';

    } catch (err) {
        showError('Terjadi kesalahan jaringan. Coba lagi.');
    } finally {
        setLoading(false);
    }
}

function getCookie(name) {
    const m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return m ? decodeURIComponent(m[2]) : '';
}

document.getElementById('email').addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
});
document.getElementById('password').addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
});
</script>

</body>
</html>