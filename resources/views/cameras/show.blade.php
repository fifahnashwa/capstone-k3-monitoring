@extends('layouts.app')

@section('title', 'Detail Kamera')

@section('content')

<div class="mb-5 flex items-center gap-3">

    <a href="{{ route('cameras.index') }}" class="text-outline hover:text-on-surface-variant">

        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">

            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>

        </svg>

    </a>

    <div>

        <h1 id="page-title" class="text-lg font-semibold text-on-surface">Detail Kamera</h1>

        <p class="text-xs text-outline mt-0.5" id="page-sub">Memuat...</p>

    </div>

    <div class="ml-auto flex gap-2">

        @if(Auth::user()->role === 'admin')

        <button onclick="editCamera()" class="px-3 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">

            Edit Konfigurasi

        </button>

        <button onclick="restartDetection()" class="px-3 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">

            Restart Deteksi

        </button>

        @endif

    </div>

</div>

<div id="loading-state" class="py-12 text-center text-sm text-outline">Memuat data kamera...</div>

<div id="content-root" class="hidden space-y-4">

  {{-- ══ Row 1: Info + Live Preview ══ --}}

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- Info Panel --}}

    <div class="lg:col-span-2 bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden">

        <div class="px-5 py-3 border-b border-outline-variant flex items-center justify-between">

            <h2 class="text-sm font-semibold text-on-surface">Informasi Kamera</h2>

            <div id="conn-badge" class="px-2 py-0.5 rounded-full text-xs font-medium bg-surface-container text-on-surface-variant">Unknown</div>

        </div>

        <div class="p-5">

            <div class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">

                <div><span class="text-outline text-xs block">Nama</span><span id="d-name" class="font-medium text-on-surface"></span></div>

                <div><span class="text-outline text-xs block">Zona</span><span id="d-zone" class="text-on-surface"></span></div>

                <div><span class="text-outline text-xs block">DVR Channel</span><span id="d-dvr" class="font-mono text-on-surface-variant"></span></div>

                <div><span class="text-outline text-xs block">Status</span><span id="d-status" class="font-medium"></span></div>

                <div><span class="text-outline text-xs block">IP Address</span><span id="d-ip" class="font-mono text-on-surface-variant"></span></div>

                <div><span class="text-outline text-xs block">Port ONVIF / RTSP</span><span id="d-ports" class="font-mono text-on-surface-variant"></span></div>

                <div><span class="text-outline text-xs block">Username</span><span id="d-user" class="font-mono text-on-surface-variant"></span></div>

                <div><span class="text-outline text-xs block">RTSP Path</span><span id="d-rtsp" class="font-mono text-on-surface-variant"></span></div>

                <div><span class="text-outline text-xs block">Model AI</span><span id="d-model" class="text-on-surface-variant text-xs break-all"></span></div>

                <div><span class="text-outline text-xs block">Confidence / Skip Frame</span><span id="d-conf" class="font-mono text-on-surface-variant"></span></div>

                <div><span class="text-outline text-xs block">PTZ</span><span id="d-ptz" class="text-on-surface-variant"></span></div>

                <div><span class="text-outline text-xs block">Auto Screenshot</span><span id="d-ss" class="text-on-surface-variant"></span></div>

            </div>

            <div id="d-desc-wrap" class="hidden mt-3 pt-3 border-t border-surface-container-low">

                <span class="text-outline text-xs block mb-1">Deskripsi</span>

                <p id="d-desc" class="text-sm text-on-surface-variant"></p>

            </div>

        </div>

    </div>

    {{-- Live Preview + Connection Health --}}

    <div class="space-y-4">

        {{-- Mini Stream Preview --}}

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden">

            <div class="px-4 py-3 border-b border-outline-variant flex items-center justify-between">

                <span class="text-sm font-semibold text-on-surface">Live Preview</span>

                <a id="link-monitoring" href="{{ route('cameras.monitoring') }}" class="text-xs text-primary hover:underline">Full Screen →</a>

            </div>

            <div class="relative bg-inverse-surface aspect-video flex items-center justify-center">

                <img id="preview-stream" class="hidden w-full h-full object-cover"

                     onerror="this.classList.add('hidden'); document.getElementById('preview-off').classList.remove('hidden')"

                     alt="Preview">

                <div id="preview-off" class="text-center text-on-surface-variant text-xs p-4">

                    <p>Stream tidak tersedia</p>

                    <button onclick="retryStream()" class="mt-2 text-blue-400 hover:underline">Coba lagi</button>

                </div>

            </div>

        </div>

        {{-- Connection Health --}}

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

            <h3 class="text-sm font-semibold text-on-surface mb-3">Connection Health</h3>

            <div class="space-y-2 text-xs">

                <div class="flex justify-between"><span class="text-outline">ONVIF Status</span><span id="h-onvif" class="font-medium">—</span></div>

                <div class="flex justify-between"><span class="text-outline">RTSP Status</span><span id="h-rtsp" class="font-medium">—</span></div>

                <div class="flex justify-between"><span class="text-outline">Last Check</span><span id="h-lastcheck" class="text-on-surface-variant">—</span></div>

                <div class="flex justify-between"><span class="text-outline">Terakhir Deteksi</span><span id="h-lastviolation" class="text-on-surface-variant">—</span></div>

            </div>

            @if(Auth::user()->role === 'admin')

            <button onclick="runTestConn()" class="mt-3 w-full py-1.5 text-xs border border-outline-variant rounded-lg text-on-surface-variant hover:bg-surface-container-low">

                Test Koneksi Sekarang

            </button>

            @endif

        </div>

    </div>

  </div>

  {{-- ══ Row 2: Stats + Quick Actions ══ --}}

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4 text-center">

        <div id="s-today" class="text-2xl font-bold text-error">—</div>

        <div class="text-xs text-outline mt-1 uppercase tracking-wide">Pelanggaran Hari Ini</div>

    </div>

    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4 text-center">

        <div id="s-week" class="text-2xl font-bold text-orange-500">—</div>

        <div class="text-xs text-outline mt-1 uppercase tracking-wide">Pelanggaran Minggu Ini</div>

    </div>

    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4 text-center">

        <div id="s-month" class="text-2xl font-bold text-on-surface">—</div>

        <div class="text-xs text-outline mt-1 uppercase tracking-wide">Pelanggaran Bulan Ini</div>

    </div>

    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4 text-center">

        <div id="s-compliance" class="text-2xl font-bold text-green-500">—%</div>

        <div class="text-xs text-outline mt-1 uppercase tracking-wide">Compliance Rate</div>

    </div>

  </div>

  {{-- ══ Row 3: Recent Violations ══ --}}

  <div class="bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden">

    <div class="px-5 py-3 border-b border-outline-variant flex items-center justify-between">

        <h2 class="text-sm font-semibold text-on-surface">Pelanggaran Terbaru</h2>

        <a id="violations-link" href="{{ route('violations.index') }}" class="text-xs text-primary hover:underline">Lihat Semua →</a>

    </div>

    <div id="violations-table" class="divide-y divide-outline-variant/30 text-sm text-on-surface">

        <div class="px-5 py-4 text-sm text-outline">Memuat...</div>

    </div>

  </div>

</div>

<script>

const CAMERA_ID = {{ $cameraId }};

const IS_ADMIN  = {{ Auth::user()->role === 'admin' ? 'true' : 'false' }};

let cameraData  = null;

document.addEventListener('DOMContentLoaded', async () => {

    await loadCamera();

    loadStats();

    loadViolations();

});

async function loadCamera() {

    try {

        const res  = await api('GET', `/api/cameras/${CAMERA_ID}`);

        cameraData = res.data;

        renderCamera(cameraData);

        document.getElementById('loading-state').classList.add('hidden');

        document.getElementById('content-root').classList.remove('hidden');

    } catch (e) {

        document.getElementById('loading-state').textContent = 'Kamera tidak ditemukan.';

    }

}

function renderCamera(d) {

    document.getElementById('page-title').textContent = d.name;

    document.getElementById('page-sub').textContent   = `Zona: ${d.zone_name ?? '—'} · ${d.ip_address ?? '—'}`;

    document.getElementById('d-name').textContent     = d.name;

    document.getElementById('d-zone').textContent     = d.zone_name ?? '—';

    document.getElementById('d-dvr').textContent      = d.dvr_channel ?? '—';

    document.getElementById('d-ip').textContent       = d.ip_address ?? '—';

    document.getElementById('d-ports').textContent    = `${d.port_onvif ?? 2020} / ${d.port_rtsp ?? 554}`;

    document.getElementById('d-user').textContent     = d.username ?? '—';

    document.getElementById('d-rtsp').textContent     = (d.rtsp_path ?? '/stream2') + ` [${(d.rtsp_transport ?? 'tcp').toUpperCase()}]`;

    document.getElementById('d-model').textContent    = d.ai_model_path ?? 'Tidak dikonfigurasi';

    document.getElementById('d-conf').textContent     = `${d.confidence_threshold ?? 0.40} / setiap ${d.process_every_n_frame ?? 1} frame`;

    document.getElementById('d-ptz').textContent      = d.ptz_enabled ? `✓ Aktif (speed: ${d.ptz_speed})` : 'Tidak aktif';

    document.getElementById('d-ss').textContent       = d.auto_screenshot ? `✓ Aktif (cooldown: ${d.screenshot_cooldown}s)` : 'Nonaktif';

    const statusEl = document.getElementById('d-status');

    statusEl.textContent  = d.is_active ? 'Aktif' : 'Nonaktif';

    statusEl.className    = d.is_active ? 'font-medium text-[#15803d]' : 'font-medium text-outline';

    const connEl = document.getElementById('conn-badge');

    const connMap = { online:'bg-[#ccf0da] text-[#15803d]', offline:'bg-error-container text-error', unknown:'bg-surface-container text-on-surface-variant' };

    connEl.className  = `px-2 py-0.5 rounded-full text-xs font-medium ${connMap[d.connection_status] ?? connMap.unknown}`;

    connEl.textContent = { online:'● Online', offline:'● Offline', unknown:'● Unknown' }[d.connection_status] ?? '—';

    document.getElementById('h-lastcheck').textContent = d.last_connection_check ?? '—';

    if (d.description) {

        document.getElementById('d-desc-wrap').classList.remove('hidden');

        document.getElementById('d-desc').textContent = d.description;

    }

    // Load stream preview

    const preview = document.getElementById('preview-stream');

    preview.src = `/api/cameras/${CAMERA_ID}/stream?t=${Date.now()}`;

    preview.classList.remove('hidden');

    document.getElementById('preview-off').classList.add('hidden');

}

async function loadStats() {

    try {

        const res = await api('GET', `/api/dashboard/stats?camera_id=${CAMERA_ID}`);

        document.getElementById('s-today').textContent       = res.today_violations ?? '—';

        document.getElementById('s-compliance').textContent  = (res.compliance_rate ?? 100) + '%';

    } catch (e) {}

    // Week & month counts from violations API

    try {

        const weekRes  = await api('GET', `/api/violations?camera_id=${CAMERA_ID}&date_from=${weekAgo()}&per_page=1`);

        const monthRes = await api('GET', `/api/violations?camera_id=${CAMERA_ID}&date_from=${monthAgo()}&per_page=1`);

        document.getElementById('s-week').textContent  = weekRes.meta?.total ?? '—';

        document.getElementById('s-month').textContent = monthRes.meta?.total ?? '—';

    } catch (e) {}

}

async function loadViolations() {

    try {

        const res  = await api('GET', `/api/violations?camera_id=${CAMERA_ID}&per_page=5`);

        const rows = res.data ?? [];

        const el   = document.getElementById('violations-table');

        if (!rows.length) { el.innerHTML = '<div class="px-5 py-4 text-sm text-outline">Belum ada pelanggaran.</div>'; return; }

        el.innerHTML = rows.map(v => `

            <div class="px-5 py-3 flex items-center justify-between hover:bg-surface-container-low">

                <div>

                    <div class="font-medium text-on-surface">${escHtml(violLabel(v))}</div>

                    <div class="text-xs text-outline">${escHtml(v.detected_at ?? '')}</div>

                </div>

                <span class="px-2 py-0.5 rounded-full text-xs font-medium ${statusColor(v.status)}">

                    ${escHtml(v.status)}

                </span>

            </div>

        `).join('');

    } catch (e) {}

}

async function runTestConn() {

    toast('Testing koneksi...', 'success');

    try {

        const res = await api('POST', `/api/cameras/${CAMERA_ID}/test-connection`);

        const ok  = res.data?.overall;

        toast(ok ? '✓ Koneksi berhasil' : '✗ Koneksi gagal', ok ? 'success' : 'error');

        await loadCamera();

    } catch (e) {

        toast('Test gagal.', 'error');

    }

}

async function restartDetection() {

    if (!confirm('Restart detection worker untuk kamera ini?')) return;

    toast('Mengirim perintah restart...', 'success');

    try {

        await api('POST', `/api/cameras/${CAMERA_ID}/test-connection`);

        toast('Perintah restart dikirim.', 'success');

    } catch (e) {

        toast('Gagal mengirim perintah.', 'error');

    }

}

function editCamera() {

    window.location.href = `/cameras?edit=${CAMERA_ID}`;

}

function retryStream() {

    const preview = document.getElementById('preview-stream');

    preview.src   = `/api/cameras/${CAMERA_ID}/stream?t=${Date.now()}`;

    preview.classList.remove('hidden');

    document.getElementById('preview-off').classList.add('hidden');

}

function violLabel(v) {

    if (v.violation_type === 'discipline') return 'Aktivitas di Luar Shift';

    const map = { no_helmet:'Tidak Pakai Helm', no_vest:'Tidak Pakai Rompi', no_boots:'Tidak Pakai Sepatu Safety' };

    return map[v.apd_label] ?? v.apd_label ?? 'Pelanggaran';

}

function statusColor(s) {

    const m = { pending:'bg-[#ffefc8] text-[#7c5800]', validated:'bg-[#ccf0da] text-[#15803d]',

                rejected:'bg-surface-container text-on-surface-variant', reported:'bg-primary-fixed text-on-primary-fixed-variant' };

    return m[s] ?? 'bg-surface-container text-on-surface-variant';

}

function weekAgo()  { const d=new Date(); d.setDate(d.getDate()-7);  return d.toISOString().slice(0,10); }

function monthAgo() { const d=new Date(); d.setMonth(d.getMonth()-1); return d.toISOString().slice(0,10); }

function escHtml(str) {

    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

}

</script>

@endsection