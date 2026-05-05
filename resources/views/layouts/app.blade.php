<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem K3') — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
</head>
<body class="bg-gray-100 min-h-screen font-sans text-gray-800">

<div class="flex h-screen overflow-hidden">

    {{-- SIDEBAR --}}
    <aside class="w-56 bg-white border-r border-gray-200 flex flex-col shrink-0">
        <div class="px-5 py-4 border-b border-gray-100">
            <span class="font-semibold text-sm text-gray-900">Sistem K3</span>
            <span class="block text-xs text-gray-400 mt-0.5">{{ Auth::user()->name }}</span>
            <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full
                {{ Auth::user()->role === 'admin' ? 'bg-purple-50 text-purple-600' :
                   (Auth::user()->role === 'manager' ? 'bg-blue-50 text-blue-600' : 'bg-green-50 text-green-600') }}">
                {{ ucfirst(Auth::user()->role) }}
            </span>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-0.5 text-sm overflow-y-auto">
            @php
                $nav = [
                    ['route' => 'dashboard',        'label' => 'Dashboard',    'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'roles' => ['admin','manager','hr']],
                    ['route' => 'violations.index', 'label' => 'Pelanggaran', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'roles' => ['admin','manager','hr']],
                    ['route' => 'reports.index',    'label' => 'Laporan',      'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'roles' => ['hr']],
                ];

                $adminNav = [
                    ['route' => 'users.index',         'label' => 'Pengguna',     'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                    ['route' => 'zones.index',          'label' => 'Zona & APD',  'icon' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7'],
                    ['route' => 'cameras.index',        'label' => 'Kamera',      'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
                    ['route' => 'shifts.index',         'label' => 'Shift',       'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['route' => 'activity-logs.index',  'label' => 'Activity Log', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                ];
            @endphp

            @foreach($nav as $item)
                @if(in_array(Auth::user()->role, $item['roles']))
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs($item['route']) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
                @endif
            @endforeach

            @if(Auth::user()->role === 'admin')
            <div class="pt-3 pb-1">
                <p class="px-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Admin</p>
            </div>
            @foreach($adminNav as $item)
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs($item['route']) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                </svg>
                {{ $item['label'] }}
            </a>
            @endforeach
            @endif
        </nav>

        <div class="px-3 py-4 border-t border-gray-100">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2.5 w-full px-3 py-2 rounded-lg text-sm text-gray-500 hover:bg-gray-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    {{-- MAIN --}}
    <main class="flex-1 overflow-y-auto">
        <div class="max-w-6xl mx-auto px-6 py-6">
            @yield('content')
        </div>
    </main>

</div>

{{-- TOAST --}}
<div id="toast" class="hidden fixed bottom-5 right-5 z-50 px-4 py-3 rounded-xl text-sm shadow-lg border text-white transition-all"></div>

<script>

function getCookie(name) {
    const m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return m ? decodeURIComponent(m[2]) : '';
}

async function api(method, url, body = null) {
    const headers = {
        'Accept':       'application/json',
        'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
    };

    if (body) headers['Content-Type'] = 'application/json';

    const opts = { method, headers, credentials: 'same-origin' };
    if (body) opts.body = JSON.stringify(body);

    const res  = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));

    if (res.status === 401) {
        window.location.href = '/login';
        return;
    }

    if (!res.ok) throw data;
    return data;
}

function toast(msg, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `fixed bottom-5 right-5 z-50 px-4 py-3 rounded-xl text-sm shadow-lg border text-white ${type === 'error' ? 'bg-red-600 border-red-700' : 'bg-gray-900 border-gray-800'}`;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}

</script>

@stack('scripts')

</body>
</html>