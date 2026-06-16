<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login | SafeGuard-CV</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    "surface": "#faf8ff", "surface-bright": "#faf8ff",
                    "surface-container-lowest": "#ffffff", "surface-container": "#ededf9",
                    "on-surface": "#191b23", "on-surface-variant": "#434655",
                    "outline": "#737686", "outline-variant": "#c3c6d7",
                    "primary": "#004ac6", "on-primary": "#ffffff",
                    "error": "#ba1a1a", "error-container": "#ffdad6",
                },
                borderRadius: { "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "2xl": "0.75rem", "full": "9999px" },
                fontFamily: { "manrope": ["Manrope","sans-serif"], "inter": ["Inter","sans-serif"] },
                fontSize: {
                    "headline-lg": ["28px", { lineHeight: "36px", fontWeight: "700" }],
                    "headline-md": ["20px", { lineHeight: "28px", fontWeight: "600" }],
                    "body-md": ["14px", { lineHeight: "20px" }],
                    "body-sm": ["12px", { lineHeight: "18px" }],
                    "label-caps": ["11px", { lineHeight: "16px", letterSpacing: "0.05em", fontWeight: "600" }],
                },
            }}
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            line-height: 1; vertical-align: middle;
        }
        .login-mesh {
            background-color: #faf8ff;
            background-image:
                radial-gradient(at 0% 0%, rgba(0,74,198,0.06) 0px, transparent 60%),
                radial-gradient(at 100% 100%, rgba(80,95,118,0.05) 0px, transparent 60%);
        }
    </style>
</head>
<body class="h-full font-inter text-on-surface login-mesh flex flex-col overflow-hidden">

<!-- Background blobs -->
<div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
    <div class="absolute -top-[25%] -left-[10%] w-[50%] h-[50%] bg-primary/[0.04] rounded-full blur-[120px]"></div>
    <div class="absolute -bottom-[20%] -right-[10%] w-[45%] h-[45%] bg-secondary/[0.04] rounded-full blur-[100px]"></div>
</div>

<main class="flex-grow flex items-center justify-center px-6">
    <div class="w-full max-w-[440px]">

        <!-- Logo -->
        <div class="flex flex-col items-center mb-8">
            <h1 class="font-manrope font-bold text-headline-lg text-primary tracking-tight">SafeGuard-CV</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Computer Vision Safety Monitoring System</p>
        </div>

        <!-- Card -->
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant shadow-sm relative overflow-hidden">
            <!-- Top accent -->
            <div class="absolute top-0 left-0 w-full h-[3px] bg-primary"></div>

            <div class="p-8">
                <div class="mb-6">
                    <h2 class="font-manrope font-semibold text-headline-md text-on-surface">Sign In</h2>
                    <p class="text-body-sm text-on-surface-variant mt-0.5">Akses dashboard pemantauan keselamatan produksi.</p>
                </div>

                <!-- Error box -->
                <div id="error-box" class="hidden mb-5 flex items-start gap-2.5 px-4 py-3 bg-error-container border border-error/20 rounded-xl text-body-sm text-on-error-container">
                    <span class="material-symbols-outlined text-[18px] flex-shrink-0 mt-0.5" style="font-variation-settings:'FILL' 1">error</span>
                    <span id="error-msg"></span>
                </div>

                <div class="space-y-5">
                    <!-- Email -->
                    <div class="space-y-1.5">
                        <label for="email" class="text-label-caps text-on-surface-variant uppercase tracking-widest">Email</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">alternate_email</span>
                            <input type="email" id="email" placeholder="nama@perusahaan.com"
                                class="w-full pl-10 pr-3 py-3 bg-surface border border-outline-variant rounded-xl text-body-md text-on-surface placeholder:text-outline/60
                                       focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <div class="flex justify-between items-center">
                            <label for="password" class="text-label-caps text-on-surface-variant uppercase tracking-widest">Password</label>
                        </div>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">lock</span>
                            <input type="password" id="password" placeholder="••••••••"
                                class="w-full pl-10 pr-10 py-3 bg-surface border border-outline-variant rounded-xl text-body-md text-on-surface placeholder:text-outline/60
                                       focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all">
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface transition-colors">
                                <span class="material-symbols-outlined text-[18px]" id="eye-icon">visibility</span>
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button onclick="doLogin()" id="btn-login"
                        class="w-full flex justify-center items-center gap-2 py-3 px-4 bg-primary text-on-primary font-semibold text-body-md rounded-xl
                               hover:bg-primary/90 active:scale-[0.98] transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        <span>Masuk</span>
                        <span class="material-symbols-outlined text-[18px]">login</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="flex-shrink-0 py-4 px-6 border-t border-outline-variant/20 bg-surface-container-lowest/60 backdrop-blur-sm flex flex-col sm:flex-row justify-between items-center gap-3">
    <p class="text-label-caps text-outline uppercase tracking-widest">Sistem K3 Monitoring Kelompok 2 © {{ date('Y') }}</p>
</footer>

<script>
function togglePassword() {
    const inp = document.getElementById('password');
    const icon = document.getElementById('eye-icon');
    if (inp.type === 'password') { inp.type = 'text'; icon.textContent = 'visibility_off'; }
    else { inp.type = 'password'; icon.textContent = 'visibility'; }
}

function showError(msg) {
    const box = document.getElementById('error-box');
    document.getElementById('error-msg').textContent = msg;
    box.classList.remove('hidden');
}
function hideError() { document.getElementById('error-box').classList.add('hidden'); }

function setLoading(on) {
    const btn = document.getElementById('btn-login');
    btn.disabled = on;
    btn.innerHTML = on
        ? '<svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span>Memproses...</span>'
        : '<span>Masuk</span><span class="material-symbols-outlined text-[18px]">login</span>';
}

async function doLogin() {
    hideError();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    if (!email || !password) { showError('Email dan password wajib diisi.'); return; }
    if (!document.getElementById('email').checkValidity()) { showError('Format email tidak valid.'); return; }

    setLoading(true);
    try {
        const res = await fetch('/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ email, password }),
        });
        const data = await res.json();
        if (!res.ok) { showError(data.message ?? 'Email atau password salah.'); return; }
        window.location.href = '/dashboard';
    } catch (err) {
        showError('Terjadi kesalahan jaringan. Silakan coba lagi.');
    } finally {
        setLoading(false);
    }
}

function getCookie(name) {
    const m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return m ? decodeURIComponent(m[2]) : '';
}

['email','password'].forEach(id => {
    document.getElementById(id).addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
});
</script>
</body>
</html>
