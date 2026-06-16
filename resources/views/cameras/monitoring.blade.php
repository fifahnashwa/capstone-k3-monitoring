@extends('layouts.app')
@section('title', 'Monitoring Dashboard')
@section('full-width', true)

@push('head')
<style>
/* ── Base ────────────────────────────────────────────────────────── */
#monitoring-root { color: #191b23; }

.mon-card {
    background: #ffffff;
    border: 1px solid #c3c6d7;
    border-radius: .75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}

/* Stream panel header — primary blue */
.stream-header {
    background: #004ac6;
    border-radius: .75rem .75rem 0 0;
    padding: .75rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

/* Stream box */
.stream-wrap {
    position: relative;
    background: #ededf9;
    border-radius: .5rem;
    overflow: hidden;
    border: 1px solid #c3c6d7;
    display: flex; align-items: center; justify-content: center;
    width: 100%; height: 100%;
}
.stream-wrap img { width: 100%; height: 100%; object-fit: cover; }

/* Overlay nav arrows */
.stream-nav-btn {
    position: absolute;
    top: 50%; transform: translateY(-50%);
    width: 2.25rem; height: 2.25rem;
    border-radius: .5rem;
    background: rgba(255,255,255,.88);
    border: 1.5px solid rgba(0,74,198,.2);
    color: #004ac6;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    transition: background .12s, opacity .12s;
    z-index: 10;
}
.stream-nav-btn:hover:not(:disabled) { background: #ffffff; }
.stream-nav-btn:disabled { opacity: 0; pointer-events: none; }
#btn-prev { left: .75rem; }
#btn-next { right: .75rem; }

/* PTZ */
.ptz-overlay {
    position: absolute; bottom: 1rem; left: 50%; transform: translateX(-50%);
    display: grid; grid-template-columns: repeat(3, 2.5rem); gap: .25rem;
}
.ptz-btn {
    width: 2.5rem; height: 2.5rem; border-radius: .5rem;
    background: rgba(255,255,255,.82);
    border: 1px solid #d1d5db; color: #434655; font-size: 1rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(4px); transition: background .12s;
    box-shadow: 0 1px 3px rgba(0,0,0,.1);
}
.ptz-btn:hover { background: #ffffff; }
.ptz-center {
    background: rgba(0,74,198,.1) !important;
    border-color: #004ac6 !important;
    color: #004ac6 !important;
}

/* Activity log rows */
.act-row {
    display: grid;
    grid-template-columns: 54px minmax(0, 1fr) 78px;
    gap: .375rem;
    padding: .5rem .75rem;
    border-bottom: 1px solid #e7e7f3;
    font-size: .75rem;
    cursor: pointer;
    transition: background .12s;
    align-items: center;
}
.act-row:hover { background: #f3f3fe; }
.act-row.violation-high { background: #fef2f2; }
.act-time {
    color: #737686;
    font-variant-numeric: tabular-nums;
    font-family: 'JetBrains Mono', monospace;
    font-size: .7rem;
}

/* Stats */
.stat-card { text-align: center; padding: 1.25rem 1rem; }
.stat-val  { font-size: 2rem; font-weight: 700; line-height: 1; }
.stat-label {
    font-size: .68rem; text-transform: uppercase;
    letter-spacing: .08em; color: #737686; margin-top: .375rem;
}
.loc-LOW    { color: #15803d; }
.loc-MEDIUM { color: #a16207; }
.loc-HIGH   { color: #ba1a1a; }
</style>
@endpush

@section('content')
<div id="monitoring-root">

  {{-- ══ Page Header ══ --}}
  <div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-lg font-bold text-on-surface">Monitoring Dashboard</h1>
        <p class="text-xs text-outline mt-0.5">K3 Real-Time APD Detection</p>
    </div>
    <div class="flex items-center gap-3">
        <span id="clock" class="font-mono text-sm text-on-surface-variant"></span>
        <select id="cam-select" onchange="selectCamera(this.value)"
                class="bg-surface-container-lowest border border-outline-variant text-on-surface
                       rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            <option value="">— Pilih Kamera —</option>
        </select>
    </div>
  </div>

  {{-- ══ Stream + Log (same height) ══ --}}
  <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-4 lg:h-[500px]">

    {{-- ── Stream Panel (4/5) ── --}}
    <div class="lg:col-span-4 mon-card overflow-hidden flex flex-col">

        {{-- Blue header — judul kamera saja --}}
        <div class="stream-header">
            <div class="min-w-0">
                <div id="cam-name" class="font-semibold text-white text-sm leading-tight truncate">
                    Memuat kamera...
                </div>
                <div id="cam-zone" class="text-xs text-white/55 truncate"></div>
            </div>

            {{-- Screenshot: kotak putih, ikon biru --}}
            <button onclick="takeScreenshot()" title="Ambil Screenshot"
                    class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors flex-shrink-0"
                    style="background:#ffffff; border:1.5px solid rgba(255,255,255,.6);"
                    onmouseover="this.style.background='#f0f4ff'"
                    onmouseout="this.style.background='#ffffff'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                     viewBox="0 0 24 24" stroke="#004ac6" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07
                             4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012
                             2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>
        </div>

        {{-- Stream area --}}
        <div class="flex-1 p-3 min-h-0 flex flex-col gap-2">
            <div class="stream-wrap flex-1 min-h-0" id="stream-container">

                {{-- Placeholder --}}
                <div id="stream-placeholder" class="text-center text-outline px-4">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none"
                         stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0
                                 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2
                                 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm">Pilih kamera untuk memulai monitoring</p>
                </div>

                {{-- Stream --}}
                <img id="stream-img" class="hidden w-full h-full object-cover"
                     onerror="streamError(this)" alt="Live Stream">

                {{-- Overlay nav arrows --}}
                <button id="btn-prev" onclick="switchCamera(-1)"
                        class="stream-nav-btn" disabled>◀</button>
                <button id="btn-next" onclick="switchCamera(1)"
                        class="stream-nav-btn" disabled>▶</button>

                {{-- PTZ --}}
                <div id="ptz-overlay" class="ptz-overlay hidden">
                    <div></div>
                    <button class="ptz-btn" onclick="ptz('up')"    title="Atas (W)">▲</button>
                    <div></div>
                    <button class="ptz-btn" onclick="ptz('left')"  title="Kiri (A)">◄</button>
                    <button class="ptz-btn ptz-center" onclick="ptz('home')" title="Home">⌂</button>
                    <button class="ptz-btn" onclick="ptz('right')" title="Kanan (D)">►</button>
                    <div></div>
                    <button class="ptz-btn" onclick="ptz('down')"  title="Bawah (S)">▼</button>
                    <div></div>
                </div>

                {{-- CMD status --}}
                <div id="cmd-status"
                     class="hidden absolute top-2 right-2 text-xs font-mono px-2 py-1 rounded-md shadow-sm"
                     style="background:rgba(255,255,255,.9); color:#003ea8;
                            border:1px solid #b4c5ff;"></div>
            </div>

            {{-- PTZ preset bar --}}
            <div id="ptz-presets-bar" class="hidden flex-shrink-0 flex flex-wrap gap-1"></div>
        </div>
    </div>

    {{-- ── Activity Log (1/5) ── --}}
    <div class="mon-card flex flex-col overflow-hidden"
         style="border-left: 3px solid #004ac6;">

        <div class="flex items-center justify-between px-4 py-3 flex-shrink-0"
             style="background:#f3f3fe; border-bottom:1px solid #c3c6d7;
                    border-radius:.75rem .75rem 0 0;">
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                      style="background:#004ac6;"></span>
                <span class="text-sm font-semibold text-on-surface">Log Aktivitas</span>
            </div>
            <a href="{{ route('violations.index') }}"
               class="text-xs font-medium hover:underline"
               style="color:#004ac6;">Lihat Semua</a>
        </div>

        <div class="flex-1 overflow-y-auto min-h-0" id="activity-log">
            <div class="act-row" style="color:#737686; cursor:default;">
                <span>—</span><span>Memuat data...</span><span></span>
            </div>
        </div>
    </div>

  </div>

  {{-- ══ Stats Row ══ --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

    <div class="mon-card stat-card" style="border-top: 3px solid #004ac6;">
        <div id="stat-workers" class="stat-val" style="color:#004ac6;">—</div>
        <div class="stat-label">Active Worker</div>
    </div>

    <div class="mon-card stat-card">
        <div id="stat-location" class="stat-val loc-LOW">LOW</div>
        <div class="stat-label">Location Status</div>
    </div>

    <div class="mon-card stat-card">
        <div id="stat-violations" class="stat-val text-error">—</div>
        <div class="stat-label">Today's Violation</div>
    </div>

    <div class="mon-card stat-card">
        <div id="stat-compliance" class="stat-val" style="color:#15803d;">—%</div>
        <div class="stat-label">Compliance Rate</div>
    </div>

  </div>

</div>

{{-- ── Violation Detail Modal ── --}}
<div id="viol-modal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center"
     style="background:rgba(0,0,0,.45)">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl
                w-full max-w-md shadow-2xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4"
             style="background:#004ac6; border-radius:.75rem .75rem 0 0;">
            <span class="font-semibold text-white text-sm" id="viol-title">
                Detail Pelanggaran
            </span>
            <button onclick="document.getElementById('viol-modal').classList.add('hidden')"
                    class="text-white/60 hover:text-white text-xl leading-none transition-colors">
                &times;
            </button>
        </div>
        <div id="viol-content"
             class="px-5 py-4 text-sm text-on-surface-variant space-y-2"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let cameras       = [];
let currentCamIdx = -1;
let currentCamId  = null;
let pollTimer     = null;
let statsTimer    = null;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    startClock();
    await loadCameras();
    startPolling();
    bindKeyboard();
});

function startClock() {
    const tick = () => {
        document.getElementById('clock').textContent =
            new Date().toLocaleTimeString('id-ID');
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
                <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0
                             01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2
                             2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm text-outline">Tidak ada kamera aktif</p>
                <a href="/cameras"
                   class="mt-2 inline-block px-3 py-1 text-xs rounded-lg text-on-surface-variant
                          border border-outline-variant bg-surface-container-lowest
                          hover:bg-surface-container shadow-sm">
                    Tambah Kamera
                </a>
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
        cameras.map(c =>
            `<option value="${c.id}">${escHtml(c.name)}` +
            `${c.zone_name ? ` (${escHtml(c.zone_name)})` : ''}</option>`
        ).join('');
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
    const img = document.getElementById('stream-img');
    const ph  = document.getElementById('stream-placeholder');
    ph.classList.add('hidden');
    img.classList.remove('hidden');
    img.src = `http://localhost:8001/api/cameras/${camId}/stream`;
}

function streamError(img) {
    img.classList.add('hidden');
    const ph = document.getElementById('stream-placeholder');
    ph.classList.remove('hidden');
    ph.innerHTML = `
        <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none"
             stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0
                     015.636 5.636m12.728 12.728L5.636 5.636"/>
        </svg>
        <p class="text-sm text-error">Stream tidak tersedia</p>
        <p class="text-xs text-outline mt-1">Detection worker mungkin offline</p>
        <button onclick="loadStream(${currentCamId})"
                class="mt-2 px-3 py-1 text-xs rounded-lg text-on-surface-variant
                       border border-outline-variant bg-surface-container-lowest
                       hover:bg-surface-container shadow-sm">
            Coba Lagi
        </button>
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
                class="px-2 py-1 text-xs rounded-lg text-on-surface-variant
                       border border-outline-variant bg-surface-container-lowest
                       hover:bg-surface-container shadow-sm">
            📍 ${escHtml(p.name ?? `Preset ${i+1}`)}
        </button>
    `).join('');
}

function bindKeyboard() {
    document.addEventListener('keydown', e => {
        if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
        const map = { w:'up', s:'down', a:'left', d:'right', h:'home' };
        if (map[e.key]) { e.preventDefault(); ptz(map[e.key]); }
    });
}

// ─── Screenshot ───────────────────────────────────────────────────────────────
async function takeScreenshot() {
    if (!currentCamId) return;
    try {
        await api('POST', `/api/cameras/${currentCamId}/screenshot`);
        toast('Screenshot berhasil diambil.', 'success');
    } catch (e) {
        toast('Gagal mengambil screenshot.', 'error');
    }
}

// ─── Stats ────────────────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const params = currentCamId ? `?camera_id=${currentCamId}` : '';
        const res = await api('GET', `/api/dashboard/stats${params}`);
        document.getElementById('stat-workers').textContent    = res.active_workers ?? 0;
        document.getElementById('stat-violations').textContent = res.today_violations ?? 0;
        document.getElementById('stat-compliance').textContent =
            (res.compliance_rate ?? 100) + '%';
        const locEl = document.getElementById('stat-location');
        locEl.textContent = res.location_status ?? 'LOW';
        locEl.className   = `stat-val loc-${res.location_status ?? 'LOW'}`;
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
        el.innerHTML = `<div class="act-row" style="color:#737686;cursor:default">
            <span>—</span><span>Tidak ada pelanggaran terbaru</span><span></span>
        </div>`;
        return;
    }
    el.innerHTML = logs.map(log => `
        <div class="act-row ${log.level === 'major' ? 'violation-high' : ''}"
             onclick='showViolDetail(${JSON.stringify(log)})'>
            <span class="act-time">${escHtml(log.time)}</span>
            <span class="text-on-surface truncate" title="${escHtml(log.camera_name ?? '')}">
                ${escHtml(log.event)}
            </span>
            <span class="text-outline text-right truncate text-xs">
                ${escHtml(log.location)}
            </span>
        </div>
    `).join('');
}

function showViolDetail(log) {
    document.getElementById('viol-title').textContent = log.event;
    const imgHtml = log.image_path
        ? `<img src="/storage/${log.image_path}" class="w-full rounded-lg mt-1 mb-2"
               onerror="this.style.display='none'" alt="Bukti">`
        : '';
    const statusCls = log.status === 'pending'
        ? 'bg-[#ffefc8] text-[#7c5800] border border-[#7c5800]/20'
        : 'bg-[#ccf0da] text-[#15803d] border border-[#15803d]/20';
    document.getElementById('viol-content').innerHTML = `
        ${imgHtml}
        <div><span class="text-outline">Waktu:</span> ${escHtml(log.time)}</div>
        <div><span class="text-outline">Pelanggar:</span>
            <span class="font-medium text-on-surface">${escHtml(log.person_name || '—')}</span>
        </div>
        <div><span class="text-outline">Lokasi:</span> ${escHtml(log.location)}</div>
        <div><span class="text-outline">Kamera:</span> ${escHtml(log.camera_name ?? '—')}</div>
        <div><span class="text-outline">Status:</span>
            <span class="px-2 py-0.5 rounded-full text-xs ${statusCls}">
                ${escHtml(log.status)}
            </span>
        </div>
        ${log.level ? `
        <div><span class="text-outline">Level:</span>
            <span class="uppercase font-medium
                         ${log.level === 'major' ? 'text-error' : 'text-[#a16207]'}">
                ${log.level}
            </span>
        </div>` : ''}
    `;
    document.getElementById('viol-modal').classList.remove('hidden');
}

// ─── Polling ──────────────────────────────────────────────────────────────────
function startPolling() {
    pollTimer  = setInterval(loadActivityLog, 5000);
    statsTimer = setInterval(loadStats, 10000);
}

// ─── Utilities ────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush