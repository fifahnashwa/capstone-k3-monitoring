<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SafeGuard-CV') }} — @yield('title', 'Dashboard')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <script>
    tailwind.config = {
        theme: { extend: {
            colors: {
                "surface":                   "#faf8ff",
                "surface-dim":               "#d9d9e5",
                "surface-container-lowest":  "#ffffff",
                "surface-container-low":     "#f3f3fe",
                "surface-container":         "#ededf9",
                "surface-container-high":    "#e7e7f3",
                "surface-container-highest": "#e1e2ed",
                "on-surface":                "#191b23",
                "on-surface-variant":        "#434655",
                "inverse-surface":           "#2e3039",
                "inverse-on-surface":        "#f0f0fb",
                "outline":                   "#737686",
                "outline-variant":           "#c3c6d7",
                "primary":                   "#004ac6",
                "on-primary":                "#ffffff",
                "primary-container":         "#2563eb",
                "on-primary-container":      "#eeefff",
                "primary-fixed":             "#dbe1ff",
                "primary-fixed-dim":         "#b4c5ff",
                "on-primary-fixed":          "#00174b",
                "on-primary-fixed-variant":  "#003ea8",
                "secondary":                 "#505f76",
                "on-secondary":              "#ffffff",
                "secondary-container":       "#d0e1fb",
                "on-secondary-container":    "#54647a",
                "error":                     "#ba1a1a",
                "on-error":                  "#ffffff",
                "error-container":           "#ffdad6",
                "on-error-container":        "#93000a",
                "background":                "#faf8ff",
                "on-background":             "#191b23",
            },
            borderRadius: {
                "DEFAULT": "0.125rem",
                "lg":      "0.25rem",
                "xl":      "0.5rem",
                "2xl":     "0.75rem",
                "full":    "9999px",
            },
            fontFamily: {
                "sans":  ["Inter", "ui-sans-serif", "system-ui"],
                "mono":  ["JetBrains Mono", "ui-monospace"],
                "brand": ["Manrope", "ui-sans-serif"],
            },
        }}
    }
    </script>

    <style>
    /* ── Global resets ──────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: Inter, sans-serif; background: #faf8ff; color: #191b23; }

    /* ── Sidebar nav items ──────────────────────────────────── */
    .nav-link {
        display: flex; align-items: center; gap: 0.625rem;
        padding: 0.45rem 0.75rem; border-radius: 0.375rem;
        font-size: 0.875rem; color: #434655;
        text-decoration: none; transition: background 0.12s, color 0.12s;
        white-space: nowrap;
    }
    .nav-link:hover    { background: #f3f3fe; color: #191b23; }
    .nav-link.active   { background: #ededf9; color: #004ac6; font-weight: 600; }
    .nav-link svg      { flex-shrink: 0; width: 1.1rem; height: 1.1rem; }

    /* ── Toast ──────────────────────────────────────────────── */
    #toast-wrap {
        position: fixed; bottom: 1.5rem; right: 1.5rem;
        z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem;
    }
    .toast {
        display: flex; align-items: center; gap: 0.625rem;
        padding: 0.65rem 1rem; border-radius: 0.5rem;
        font-size: 0.8125rem; font-family: Inter, sans-serif;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        min-width: 220px; max-width: 340px;
        animation: toastIn 0.18s ease;
    }
    .toast.success { background: #ccf0da; color: #1a6b3a; }
    .toast.error   { background: #ffdad6; color: #93000a; }
    .toast.info    { background: #dbe1ff; color: #003ea8; }
    @keyframes toastIn  { from { opacity:0; transform:translateX(10px); } to { opacity:1; transform:none; } }
    @keyframes toastOut { to   { opacity:0; transform:translateX(8px); } }

    /* ── Scrollbar thin ─────────────────────────────────────── */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #c3c6d7; border-radius: 99px; }
    </style>

    @stack('head')
</head>

<body class="h-full flex overflow-hidden">

{{-- ══ SIDEBAR ════════════════════════════════════════════════════════════════ --}}
<aside class="w-[260px] flex-shrink-0 h-full flex flex-col overflow-y-auto"
       style="background:#ffffff; border-right:1px solid #c3c6d7;">

    {{-- Brand --}}
    <div class="px-5 py-4" style="border-bottom:1px solid #e7e7f3;">
        <div class="flex items-center gap-2.5">
            <div>
                <p class="font-brand font-bold leading-tight" style="font-size:14px;color:#004ac6;">SafeGuard-CV</p>
                <p class="leading-none" style="font-size:10px;color:#737686;letter-spacing:.05em;text-transform:uppercase;">K3 Monitoring</p>
            </div>
        </div>
    </div>

    {{-- User --}}
    @auth
    <div class="px-4 py-3" style="border-bottom:1px solid #e7e7f3;">
        <p class="font-semibold leading-tight truncate" style="font-size:13px;color:#191b23;">{{ Auth::user()->name }}</p>
        <p class="mt-0.5" style="font-size:10px;letter-spacing:.05em;text-transform:uppercase;
            color:{{ Auth::user()->role === 'admin' ? '#7c3aed' : (Auth::user()->role === 'hr' ? '#15803d' : '#004ac6') }};">
            {{ Auth::user()->role }}
        </p>
    </div>
    @endauth

    {{-- Navigation --}}
    @php $p = request()->path(); @endphp
    <nav class="flex-1 px-3 py-3 space-y-0.5">

        <p style="font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#737686;padding:.5rem .75rem .25rem;">Monitoring</p>

        {{-- Dashboard: semua role --}}
        <a href="{{ url('/dashboard') }}"
           class="nav-link {{ $p === 'dashboard' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6zM4 14a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1v-5z"/>
            </svg>
            Dashboard
        </a>

        {{-- Live Monitor: admin saja --}}
        @if(Auth::user()?->role === 'admin')
        <a href="{{ route('cameras.monitoring') }}"
           class="nav-link {{ $p === 'cameras/monitoring' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            Live Monitor
        </a>
        @endif

        {{-- Pelanggaran: admin & manager --}}
        @if(in_array(Auth::user()?->role, ['admin', 'manager']))
        <a href="{{ route('violations.index') }}"
           class="nav-link {{ str_starts_with($p,'violations') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            Pelanggaran
        </a>
        @endif

        {{-- Laporan: hr saja --}}
        @if(Auth::user()?->role === 'hr')
        <a href="{{ route('reports.index') }}"
           class="nav-link {{ str_starts_with($p,'reports') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Laporan
        </a>
        @endif

        {{-- Konfigurasi: admin saja --}}
        @if(Auth::user()?->role === 'admin')
        <p style="font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#737686;padding:.75rem .75rem .25rem;">Konfigurasi</p>

        <a href="{{ route('cameras.index') }}"
           class="nav-link {{ $p === 'cameras' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Kamera
        </a>

        <a href="{{ route('operasional.index') }}"
           class="nav-link {{ str_starts_with($p,'operasional') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Operasional
        </a>

        <a href="{{ route('users.index') }}"
           class="nav-link {{ str_starts_with($p,'users') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            Pengguna
        </a>

        <a href="{{ route('workers.faces') }}"
           class="nav-link {{ str_starts_with($p,'workers') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Model Wajah
        </a>

        <a href="{{ route('activity-logs.index') }}"
           class="nav-link {{ str_starts_with($p,'activity-logs') ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Log Aktivitas
        </a>
        @endif

    </nav>

    {{-- Logout --}}
    <div class="px-3 pb-4 pt-2" style="border-top:1px solid #e7e7f3;">
        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <button type="submit"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors"
                style="color:#ba1a1a;" onmouseover="this.style.background='#ffdad6'" onmouseout="this.style.background=''">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Keluar
            </button>
        </form>
    </div>
</aside>

{{-- ══ MAIN ════════════════════════════════════════════════════════════════════ --}}
<div class="flex-1 flex flex-col h-full min-w-0">

    {{-- Page content --}}
    <main class="flex-1 overflow-y-auto @if(View::hasSection('full-width')) p-4 @else p-5 @endif"
          style="background:#faf8ff;">
        @yield('content')
    </main>
</div>

{{-- Toast container --}}
<div id="toast-wrap"></div>

{{-- ══ GLOBAL SCRIPTS ══════════════════════════════════════════════════════════ --}}
<script>
// ── api() ─────────────────────────────────────────────────────────────────────
async function api(method, url, body = null) {
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
        },
        credentials: 'same-origin',
    };
    if (body) opts.body = JSON.stringify(body);

    const res  = await fetch(url, opts);
    if (res.status === 401) { window.location.href = '/login'; return; }
    const data = await res.json();
    if (!res.ok) throw new Error(data.message ?? `HTTP ${res.status}`);
    return data;
}

function getCookie(name) {
    const m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return m ? decodeURIComponent(m[2]) : '';
}

// ── toast() ───────────────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
    const icons = { success: '✓', error: '✕', info: 'i' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span style="font-weight:700;font-size:.9em;">${icons[type] ?? 'i'}</span><span>${msg}</span>`;
    document.getElementById('toast-wrap').appendChild(el);
    setTimeout(() => {
        el.style.animation = 'toastOut 0.25s ease forwards';
        setTimeout(() => el.remove(), 250);
    }, 3500);
}

// ── formatDate() ──────────────────────────────────────────────────────────────
function formatDate(str, withTime = true) {
    if (!str) return '—';
    const opts = { day: '2-digit', month: 'short', year: 'numeric' };
    if (withTime) { opts.hour = '2-digit'; opts.minute = '2-digit'; }
    return new Date(str).toLocaleString('id-ID', opts);
}
</script>
@stack('scripts')
</body>
</html>