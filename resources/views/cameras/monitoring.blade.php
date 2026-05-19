@extends('layouts.app')
@section('title', 'Monitoring Dashboard')

@push('head')
<style>
/* ── Dark Dashboard Theme ──────────────────────────────────────────── */
#monitoring-root {
    background: #111827;
    color: #f9fafb;
    min-height: calc(100vh - 3rem);
    border-radius: 1rem;
    overflow: hidden;
}
.mon-card { background:#1f2937; border:1px solid #374151; border-radius:.75rem; }
.mon-header { background:#1f2937; border-bottom:1px solid #374151; }
.accent { color:#f5d76e; }
.accent-bg { background:#f5d76e; color:#111827; }

/* Live badge pulse */
@keyframes pulse-live { 0%,100%{opacity:1} 50%{opacity:.4} }
.live-badge { animation: pulse-live 1.5s infinite; display:inline-flex; align-items:center; gap:.3rem; }
.live-dot { width:.6rem; height:.6rem; background:#ef4444; border-radius:50%; }

/* Stats cards */
.stat-card { text-align:center; padding:1.25rem 1rem; }
.stat-val { font-size:2rem; font-weight:700; line-height:1; }
.stat-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.08em; color:#9ca3af; margin-top:.375rem; }

/* Location status colors */
.loc-LOW    { color:#4ade80; }
.loc-MEDIUM { color:#f5d76e; }
.loc-HIGH   { color:#f87171; }

/* Activity log */
.act-row { display:grid; grid-template-columns: 80px 1fr 130px; gap:.5rem; padding:.5rem .75rem;
           border-bottom:1px solid #374151; font-size:.8rem; cursor:pointer; transition:background .12s; }
.act-row:hover { background:#374151; }
.act-row.violation-high { background:rgba(239,68,68,.08); }
.act-time { color:#9ca3af; font-variant-numeric:tabular-nums; }

/* Stream panel */
.stream-wrap { position:relative; background:#0a0f1a; border-radius:.5rem; overflow:hidden;
               aspect-ratio:16/9; display:flex; align-items:center; justify-content:center; }
.stream-wrap img, .stream-wrap video { width:100%; height:100%; object-fit:cover; }
.stream-overlay { position:absolute; top:0;left:0;right:0;bottom:0; pointer-events:none; }

/* PTZ Overlay */
.ptz-overlay { position:absolute; bottom:1rem; left:50%; transform:translateX(-50%);
               display:grid; grid-template-columns: repeat(3, 2.5rem); gap:.25rem; }
.ptz-btn { width:2.5rem; height:2.5rem; border-radius:.5rem; background:rgba(0,0,0,.6);
           border:1px solid rgba(255,255,255,.2); color:#fff; font-size:1rem; cursor:pointer;
           display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px);
           transition:background .12s; }
.ptz-btn:hover { background:rgba(255,255,255,.15); }
.ptz-center { background:rgba(245,215,110,.2) !important; border-color:#f5d76e !important; }

/* Camera switcher */
.cam-switch-btn { width:2rem; height:2rem; border-radius:.375rem; background:rgba(0,0,0,.4);
                  border:1px solid rgba(255,255,255,.2); color:#fff; cursor:pointer;
                  display:flex; align-items:center; justify-content:center; font-size:.875rem; }
.cam-switch-btn:hover:not(:disabled) { background:rgba(255,255,255,.15); }
.cam-switch-btn:disabled { opacity:.3; cursor:not-allowed; }
</style>
@endpush

@section('content')
<div id="monitoring-root" class="p-4">

  {{-- ══ Header ══ --}}
  <div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold accent">Monitoring Dashboard</h1>
        <p class="text-xs text-gray-400 mt-0.5">K3 Real-Time APD Detection</p>
    </div>
    <div class="flex items-center gap-3 text-xs text-gray-400">
        <span id="clock" class="font-mono text-sm text-gray-300"></span>
        <select id="cam-select" onchange="selectCamera(this.value)"
                class="bg-gray-700 border border-gray-600 text-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none">
            <option value="">— Pilih Kamera —</option>
        </select>
    </div>
  </div>

  {{-- ══ Main Content Grid ══ --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

    {{-- ── Live Footage Panel ── --}}
    <div class="lg:col-span-2 mon-card p-4">
        {{-- Camera header --}}
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <button id="btn-prev" onclick="switchCamera(-1)" class="cam-switch-btn" disabled>&#8592;</button>
                <div>
                    <div id="cam-name" class="font-semibold text-white text-sm">Memuat kamera...</div>
                    <div id="cam-zone" class="text-xs text-gray-400"></div>
                </div>
                <button id="btn-next" onclick="switchCamera(1)" class="cam-switch-btn" disabled>&#8594;</button>
            </div>
            <div class="flex items-center gap-2">
                <span class="live-badge text-xs font-bold text-red-400 bg-red-900/30 px-2 py-0.5 rounded-full">
                    <span class="live-dot"></span> LIVE
                </span>
                <button onclick="takeScreenshot()" title="Screenshot Manual"
                        class="w-7 h-7 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 flex items-center justify-center text-base">
                    📸
                </button>
            </div>
        </div>

        {{-- Stream --}}
        <div class="stream-wrap" id="stream-container">
            <div id="stream-placeholder" class="text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm">Pilih kamera untuk memulai monitoring</p>
            </div>
            <img id="stream-img" class="hidden w-full h-full object-cover"
                 onerror="streamError(this)" alt="Live Stream">

            {{-- PTZ Overlay (hidden until ptz_enabled) --}}
            <div id="ptz-overlay" class="ptz-overlay hidden">
                <div></div>
                <button class="ptz-btn" onclick="ptz('up')" title="Atas (W)">▲</button>
                <div></div>
                <button class="ptz-btn" onclick="ptz('left')" title="Kiri (A)">◄</button>
                <button class="ptz-btn ptz-center" onclick="ptz('home')" title="Home">⌂</button>
                <button class="ptz-btn" onclick="ptz('right')" title="Kanan (D)">►</button>
                <div></div>
                <button class="ptz-btn" onclick="ptz('down')" title="Bawah (S)">▼</button>
                <div></div>
            </div>

            {{-- CMD Status Indicator --}}
            <div id="cmd-status" class="hidden absolute top-2 right-2 bg-black/60 text-cyan-400 text-xs font-mono px-2 py-1 rounded-md backdrop-blur"></div>
        </div>

        {{-- PTZ Presets --}}
        <div id="ptz-presets-bar" class="hidden mt-2 flex flex-wrap gap-1"></div>
    </div>

    {{-- ── Activity Log ── --}}
    <div class="mon-card flex flex-col" style="max-height:480px">
        <div class="mon-header px-4 py-3 flex items-center justify-between rounded-t-xl">
            <span class="text-sm font-semibold text-gray-200">Log Aktivitas</span>
            <a href="{{ route('violations.index') }}" class="text-xs accent hover:underline">Lihat Semua →</a>
        </div>
        <div class="flex-1 overflow-y-auto" id="activity-log">
            <div class="act-row" style="color:#6b7280; font-size:.75rem; cursor:default">
                <span>—</span><span>Memuat data...</span><span></span>
            </div>
        </div>
    </div>
  </div>

  {{-- ══ Stats Cards Row ══ --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="stats-row">
    <div class="mon-card stat-card">
        <div id="stat-workers" class="stat-val accent">—</div>
        <div class="stat-label">Active Worker</div>
    </div>
    <div class="mon-card stat-card">
        <div id="stat-location" class="stat-val loc-LOW">LOW</div>
        <div class="stat-label">Location Status</div>
    </div>
    <div class="mon-card stat-card">
        <div id="stat-violations" class="stat-val" style="color:#f87171">—</div>
        <div class="stat-label">Today's Violation</div>
    </div>
    <div class="mon-card stat-card">
        <div id="stat-compliance" class="stat-val" style="color:#4ade80">—%</div>
        <div class="stat-label">Compliance Rate</div>
    </div>
  </div>

</div>

{{-- ── Violation Detail Modal ── --}}
<div id="viol-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.6)">
    <div class="bg-gray-800 border border-gray-700 rounded-2xl w-full max-w-md shadow-2xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700">
            <span class="font-semibold text-white" id="viol-title">Detail Pelanggaran</span>
            <button onclick="document.getElementById('viol-modal').classList.add('hidden')"
                    class="text-gray-400 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div id="viol-content" class="px-5 py-4 text-sm text-gray-300 space-y-2"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let cameras        = [];
let currentCamIdx  = -1;
let currentCamId   = null;
let pollTimer      = null;
let statsTimer     = null;

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', async () => {
    startClock();
    await loadCameras();
    startPolling();
    bindKeyboard();
});

function startClock() {
    const tick = () => {
        document.getElementById('clock').textContent = new Date().toLocaleTimeString('id-ID');
    };
    tick();
    setInterval(tick, 1000);
}

// ─── Cameras ──────────────────────────────────────────────────────────────────

async function loadCameras() {
    try {
        const res = await api('GET', '/api/cameras?is_active=1&per_page=100');
        cameras = res?.data ?? [];
        buildCameraSelect();
        if (cameras.length > 0) {
            selectCamera(cameras[0].id);
        } else {
            document.getElementById('cam-name').textContent = 'Tidak ada kamera aktif';
            document.getElementById('stream-placeholder').innerHTML = `
                <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm text-gray-400">Tidak ada kamera aktif</p>
                <a href="/cameras" class="mt-2 inline-block px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 rounded-lg text-gray-200">Tambah Kamera</a>
            `;
        }
    } catch (e) {
        console.error('Failed to load cameras', e);
        document.getElementById('cam-name').textContent = 'Gagal memuat kamera';
    }
}

function buildCameraSelect() {
    const sel = document.getElementById('cam-select');
    sel.innerHTML = '<option value="">— Pilih Kamera —</option>' +
        cameras.map(c => `<option value="${c.id}">${escHtml(c.name)} (${escHtml(c.zone_name ?? '')})</option>`).join('');
}

function selectCamera(id) {
    if (!id) return;
    currentCamId  = parseInt(id);
    currentCamIdx = cameras.findIndex(c => c.id === currentCamId);
    document.getElementById('cam-select').value = id;

    const cam = cameras[currentCamIdx];
    if (cam) updateCameraHeader(cam);

    loadStream(currentCamId);
    loadStats();
    loadActivityLog();
}

function updateCameraHeader(cam) {
    document.getElementById('cam-name').textContent = cam.name;
    document.getElementById('cam-zone').textContent = cam.zone_name ?? '';

    // Fetch detailed info for PTZ
    api('GET', `/api/cameras/${cam.id}`).then(res => {
        const d = res.data;
        const ptzEnabled = d?.ptz_enabled ?? false;
        document.getElementById('ptz-overlay').classList.toggle('hidden', !ptzEnabled);
        buildPresetBar(d?.preset_positions ?? [], cam.id);
    }).catch(() => {});

    document.getElementById('btn-prev').disabled = currentCamIdx <= 0;
    document.getElementById('btn-next').disabled = currentCamIdx >= cameras.length - 1;
}

function switchCamera(dir) {
    const idx = currentCamIdx + dir;
    if (idx < 0 || idx >= cameras.length) return;
    selectCamera(cameras[idx].id);
}

// ─── Stream ───────────────────────────────────────────────────────────────────

function loadStream(camId) {
    const img  = document.getElementById('stream-img');
    const ph   = document.getElementById('stream-placeholder');
    ph.classList.add('hidden');
    img.classList.remove('hidden');
    // MJPEG stream via detection worker proxy
    img.src = `/api/cameras/${camId}/stream?t=${Date.now()}`;
}

function streamError(img) {
    img.classList.add('hidden');
    const ph = document.getElementById('stream-placeholder');
    ph.classList.remove('hidden');
    ph.innerHTML = `
        <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
        </svg>
        <p class="text-sm text-red-400">Stream tidak tersedia</p>
        <p class="text-xs text-gray-500 mt-1">Detection worker mungkin offline</p>
        <button onclick="loadStream(${currentCamId})" class="mt-2 px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 rounded-lg text-gray-200">Coba Lagi</button>
    `;
}

// ─── PTZ ─────────────────────────────────────────────────────────────────────

async function ptz(direction) {
    if (!currentCamId) return;
    showCmdStatus(`MOVING ${direction.toUpperCase()}`);
    try {
        await api('POST', `/api/cameras/${currentCamId}/ptz`, { direction });
        setTimeout(() => showCmdStatus('STOPPED'), 800);
    } catch (e) {
        showCmdStatus('PTZ ERR');
        setTimeout(() => hideCmdStatus(), 1500);
    }
}

async function gotoPreset(camId, idx) {
    showCmdStatus('PRESET...');
    try {
        await api('POST', `/api/cameras/${camId}/ptz/preset/${idx}`);
        setTimeout(() => showCmdStatus('STOPPED'), 1000);
    } catch (e) {
        showCmdStatus('ERR');
        setTimeout(() => hideCmdStatus(), 1500);
    }
}

function showCmdStatus(msg) {
    const el = document.getElementById('cmd-status');
    el.textContent = msg;
    el.classList.remove('hidden');
}

function hideCmdStatus() {
    document.getElementById('cmd-status').classList.add('hidden');
}

function buildPresetBar(presets, camId) {
    const bar = document.getElementById('ptz-presets-bar');
    if (!presets?.length) { bar.classList.add('hidden'); return; }
    bar.classList.remove('hidden');
    bar.innerHTML = presets.map((p, i) => `
        <button onclick="gotoPreset(${camId}, ${i})"
                class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 border border-gray-600 rounded-lg text-gray-200">
            📍 ${escHtml(p.name ?? `Preset ${i+1}`)}
        </button>
    `).join('');
}

function bindKeyboard() {
    document.addEventListener('keydown', e => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
        const map = { w: 'up', s: 'down', a: 'left', d: 'right', h: 'home' };
        if (map[e.key]) { e.preventDefault(); ptz(map[e.key]); }
    });
}

// ─── Screenshot ───────────────────────────────────────────────────────────────

async function takeScreenshot() {
    if (!currentCamId) return;
    try {
        const res = await api('POST', `/api/cameras/${currentCamId}/screenshot`);
        toast('Screenshot berhasil diambil.', 'success');
    } catch (e) {
        toast('Gagal mengambil screenshot.', 'error');
    }
}

// ─── Stats Cards ──────────────────────────────────────────────────────────────

async function loadStats() {
    try {
        const params = currentCamId ? `?camera_id=${currentCamId}` : '';
        const res = await api('GET', `/api/dashboard/stats${params}`);
        document.getElementById('stat-workers').textContent   = res.active_workers ?? 0;
        document.getElementById('stat-violations').textContent = res.today_violations ?? 0;
        document.getElementById('stat-compliance').textContent = (res.compliance_rate ?? 100) + '%';

        const locEl = document.getElementById('stat-location');
        locEl.textContent  = res.location_status ?? 'LOW';
        locEl.className    = `stat-val loc-${res.location_status ?? 'LOW'}`;
    } catch (e) {}
}

// ─── Activity Log ─────────────────────────────────────────────────────────────

async function loadActivityLog() {
    try {
        const params = currentCamId ? `?camera_id=${currentCamId}` : '';
        const res    = await api('GET', `/api/dashboard/activity-log${params}`);
        renderActivityLog(res.data ?? []);
    } catch (e) {}
}

function renderActivityLog(logs) {
    const el = document.getElementById('activity-log');
    if (!logs.length) {
        el.innerHTML = '<div class="act-row" style="color:#6b7280;cursor:default"><span>—</span><span>Tidak ada pelanggaran terbaru</span><span></span></div>';
        return;
    }
    el.innerHTML = logs.map(log => `
        <div class="act-row ${log.level === 'major' ? 'violation-high' : ''}" onclick='showViolDetail(${JSON.stringify(log)})'>
            <span class="act-time">${escHtml(log.time)}</span>
            <span class="text-gray-200" title="${escHtml(log.camera_name ?? '')}">${escHtml(log.event)}</span>
            <span class="text-gray-400 text-right truncate">${escHtml(log.location)}</span>
        </div>
    `).join('');
}

function showViolDetail(log) {
    document.getElementById('viol-title').textContent = log.event;
    const imgHtml = log.image_path
        ? `<img src="/storage/${log.image_path}" class="w-full rounded-lg mt-2 mb-1" onerror="this.style.display='none'" alt="Bukti">`
        : '';
    document.getElementById('viol-content').innerHTML = `
        ${imgHtml}
        <div><span class="text-gray-500">Waktu:</span> ${escHtml(log.time)}</div>
        <div><span class="text-gray-500">Lokasi:</span> ${escHtml(log.location)}</div>
        <div><span class="text-gray-500">Kamera:</span> ${escHtml(log.camera_name ?? '—')}</div>
        <div><span class="text-gray-500">Status:</span>
            <span class="px-2 py-0.5 rounded-full text-xs ${log.status === 'pending' ? 'bg-yellow-900 text-yellow-300' : 'bg-green-900 text-green-300'}">
                ${escHtml(log.status)}
            </span>
        </div>
        ${log.level ? `<div><span class="text-gray-500">Level:</span> <span class="uppercase font-medium ${log.level === 'major' ? 'text-red-400' : 'text-yellow-400'}">${log.level}</span></div>` : ''}
    `;
    document.getElementById('viol-modal').classList.remove('hidden');
}

// ─── Polling ──────────────────────────────────────────────────────────────────

function startPolling() {
    // Reload activity log & stats every 5 seconds
    pollTimer  = setInterval(loadActivityLog, 5000);
    statsTimer = setInterval(loadStats, 10000);
}

// ─── Utilities ────────────────────────────────────────────────────────────────

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush
