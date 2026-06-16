@extends('layouts.app')

@section('title', 'Manajemen Kamera')

@push('head')

<style>

    .tab-btn.active { background:#ededf9; color:#004ac6; border-color:#b4c5ff; }

    .tab-btn { border:1px solid transparent; border-radius:0.5rem; padding:0.375rem 0.875rem; font-size:0.8125rem; cursor:pointer; transition:all .15s; }

    .tab-btn:not(.active):hover { background:#f3f3fe; }

    .tab-content { display:none; }

    .tab-content.active { display:block; }

    .badge-online  { background:#ccf0da; color:#15803d; }

    .badge-offline { background:#ffdad6; color:#ba1a1a; }

    .badge-unknown { background:#ededf9; color:#737686; }

    .badge-active  { background:#ccf0da; color:#15803d; }

    .badge-inactive{ background:#ededf9; color:#737686; }

    .slider-wrap label { display:flex; justify-content:space-between; }

    input[type=range] { width:100%; accent-color:#004ac6; }

    .conn-result { border-radius:.5rem; padding:.75rem 1rem; font-size:.8125rem; margin-top:.5rem; }

    .conn-ok   { background:#ccf0da; border:1px solid #86efac; }

    .conn-fail { background:#ffdad6; border:1px solid #fca5a5; }

</style>

@endpush

@section('content')

<div class="flex items-center justify-between mb-5">

    <div>

        <h1 class="text-lg font-semibold text-on-surface">Manajemen Kamera</h1>

        <p class="text-xs text-outline mt-0.5">Kelola semua kamera pengawas APD</p>

    </div>

    <div class="flex gap-2">

        <a href="{{ route('cameras.monitoring') }}"

           class="px-4 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low flex items-center gap-1.5">

            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>

            </svg>

            Dashboard Monitor

        </a>

        @if(Auth::user()->role === 'admin')

        <button onclick="openModal()" class="px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90 flex items-center gap-1.5">

            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>

            </svg>

            Tambah Kamera

        </button>

        @endif

    </div>

</div>

{{-- FILTER BAR --}}

<div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-3 mb-4 flex flex-wrap gap-3 items-center">

    <input type="text" id="search" placeholder="Cari nama, IP, atau zona..."

           class="flex-1 min-w-48 text-sm border border-outline-variant rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/20"

           oninput="debounceLoad()">

    <select id="f-zone" onchange="loadData()"

            class="text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest">

        <option value="">Semua Zona</option>

    </select>

    <select id="f-status" onchange="loadData()"

            class="text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest">

        <option value="">Semua Status</option>

        <option value="1">Aktif</option>

        <option value="0">Nonaktif</option>

    </select>

    <select id="f-conn" onchange="loadData()"

            class="text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest">

        <option value="">Semua Koneksi</option>

        <option value="online">Online</option>

        <option value="offline">Offline</option>

        <option value="unknown">Unknown</option>

    </select>

</div>

{{-- TABLE --}}

<div class="bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden">

    <div id="loading" class="py-12 text-center text-sm text-outline">Memuat...</div>

    <table id="cam-table" class="hidden w-full text-sm">

        <thead class="bg-surface-container-low border-b border-outline-variant">

            <tr class="text-left text-xs text-outline uppercase tracking-wide">

                <th class="px-4 py-3 font-medium">Nama</th>

                <th class="px-4 py-3 font-medium">Zona</th>

                <th class="px-4 py-3 font-medium">DVR Channel</th>

                <th class="px-4 py-3 font-medium">IP Address</th>

                <th class="px-4 py-3 font-medium">Koneksi</th>

                <th class="px-4 py-3 font-medium">Status</th>

                <th class="px-4 py-3 font-medium text-right">Aksi</th>

            </tr>

        </thead>

        <tbody id="cam-body" class="divide-y divide-outline-variant/30"></tbody>

    </table>

    <div id="empty" class="hidden py-12 text-center text-sm text-outline">Belum ada kamera.</div>

</div>

{{-- PAGINATION --}}

<div id="pagination" class="hidden flex items-center justify-between mt-4 text-sm text-on-surface-variant"></div>

{{-- ═══════════════════════ MODAL ═══════════════════════ --}}

<div id="modal" class="hidden fixed inset-0 z-40 flex items-center justify-center overflow-y-auto py-6" style="background:rgba(0,0,0,0.4)">

  <div class="bg-surface-container-lowest rounded-2xl w-full max-w-2xl shadow-2xl mx-4" onclick="event.stopPropagation()">

    {{-- Modal Header --}}

    <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant">

        <h2 id="modal-title" class="text-base font-semibold text-on-surface">Tambah Kamera</h2>

        <button onclick="closeModal()" class="text-outline hover:text-on-surface-variant">

            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>

            </svg>

        </button>

    </div>

    {{-- Tabs --}}

    <div class="flex gap-1 px-6 pt-4">

        <button class="tab-btn active" onclick="switchTab('tab-info', this)">Informasi</button>

        <button class="tab-btn" onclick="switchTab('tab-network', this)">Jaringan</button>

        <button class="tab-btn" onclick="switchTab('tab-ai', this)">AI / Deteksi</button>

        <button class="tab-btn" onclick="switchTab('tab-ptz', this)">PTZ</button>

    </div>

    {{-- Modal Body --}}

    <div class="px-6 py-4 space-y-4 max-h-[65vh] overflow-y-auto">

      {{-- TAB 1: Informasi Dasar --}}

      <div id="tab-info" class="tab-content active space-y-3">

        <div class="grid grid-cols-2 gap-3">

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Nama Kamera <span class="text-error">*</span></label>

                <input type="text" id="f-name" maxlength="100"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none"

                       placeholder="Kamera Forklift 1">

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Zona <span class="text-error">*</span></label>

                <select id="f-zone-modal" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:outline-none"></select>

            </div>

        </div>

        <div class="grid grid-cols-2 gap-3">

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">DVR Channel</label>

                <input type="text" id="f-dvr" maxlength="10"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none"

                       placeholder="CH-01">

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Status</label>

                <select id="f-active" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:outline-none">

                    <option value="1">Aktif</option>

                    <option value="0">Nonaktif</option>

                </select>

            </div>

        </div>

        <div>

            <label class="block text-xs text-on-surface-variant mb-1">Deskripsi / Lokasi Detail</label>

            <textarea id="f-desc" rows="2"

                      class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none resize-none"

                      placeholder="Opsional — keterangan tambahan lokasi kamera"></textarea>

        </div>

      </div>

      {{-- TAB 2: Koneksi Jaringan --}}

      <div id="tab-network" class="tab-content space-y-3">

        <div class="grid grid-cols-3 gap-3">

            <div class="col-span-2">

                <label class="block text-xs text-on-surface-variant mb-1">IP Address <span class="text-error">*</span></label>

                <input type="text" id="f-ip"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none"

                       placeholder="10.134.28.100">

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Port ONVIF</label>

                <input type="number" id="f-port-onvif" value="2020" min="1" max="65535"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none">

            </div>

        </div>

        <div class="grid grid-cols-2 gap-3">

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Username Kamera <span class="text-error">*</span></label>

                <input type="text" id="f-username" autocomplete="off"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none"

                       placeholder="TC70BKelompok2A2">

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1" id="pass-label">Password <span class="text-error">*</span></label>

                <div class="flex gap-1">

                    <input type="password" id="f-password" autocomplete="new-password"

                           class="flex-1 text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none"

                           placeholder="••••••••••">

                    <button type="button" id="pass-toggle-btn" class="hidden px-2 py-1 text-xs border border-outline-variant rounded-lg text-primary hover:bg-primary-fixed whitespace-nowrap"

                            onclick="togglePasswordEdit()">Ubah</button>

                </div>

            </div>

        </div>

        <div class="grid grid-cols-3 gap-3">

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Port RTSP</label>

                <input type="number" id="f-port-rtsp" value="554" min="1" max="65535"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none">

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Path Stream</label>

                <select id="f-rtsp-path" onchange="checkCustomPath()"

                        class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:outline-none">

                    <option value="/stream1">/stream1 (main)</option>

                    <option value="/stream2" selected>/stream2 (sub)</option>

                    <option value="/live">/live</option>

                    <option value="custom">Custom...</option>

                </select>

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Transport</label>

                <select id="f-rtsp-transport" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:outline-none">

                    <option value="tcp">TCP</option>

                    <option value="udp">UDP</option>

                </select>

            </div>

        </div>

        <div id="custom-path-wrap" class="hidden">

            <label class="block text-xs text-on-surface-variant mb-1">Custom Path</label>

            <input type="text" id="f-rtsp-path-custom" placeholder="/custom/path"

                   class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none">

        </div>

        <div class="grid grid-cols-2 gap-3">

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Connection Timeout (detik)</label>

                <input type="number" id="f-timeout" value="10" min="1" max="60"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none">

            </div>

        </div>

        <button onclick="runConnectionTest()" id="btn-test-conn"

                class="w-full py-2 text-sm border-2 border-blue-200 text-on-primary-fixed-variant rounded-lg hover:bg-primary-fixed flex items-center justify-center gap-2">

            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>

            </svg>

            Test Koneksi

        </button>

        <div id="conn-result" class="hidden conn-result"></div>

      </div>

      {{-- TAB 3: Konfigurasi AI --}}

      <div id="tab-ai" class="tab-content space-y-3">

        {{-- Model path (read + manual override) --}}

        <div>

            <label class="block text-xs text-on-surface-variant mb-1">Path Model Aktif (.pt / .onnx)</label>

            <div class="flex gap-1.5">

                <input type="text" id="f-model-path"

                       class="flex-1 text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none"

                       placeholder="Pilih dari daftar di bawah atau ketik path manual">

                <button type="button" onclick="document.getElementById('f-model-path').value=''"

                        class="px-2 text-outline hover:text-error border border-outline-variant rounded-lg" title="Hapus">✕</button>

            </div>

        </div>

        {{-- Daftar model tersedia --}}

        <div class="border border-outline-variant rounded-xl overflow-hidden">

            <div class="flex items-center justify-between px-3 py-2 bg-surface-container-low border-b border-outline-variant">

                <span class="text-xs font-medium text-on-surface-variant">Model Tersedia di Worker</span>

                <button type="button" onclick="loadModelList()" class="text-xs text-primary hover:underline">↻ Refresh</button>

            </div>

            <div id="model-list" class="max-h-40 overflow-y-auto text-sm text-outline py-3 text-center">

                Klik Refresh untuk memuat daftar model.

            </div>

        </div>

        {{-- Upload model baru --}}

        <div class="border border-dashed border-blue-100 rounded-xl p-3 bg-primary-fixed/30">

            <div class="text-xs font-medium text-on-surface-variant mb-2">Upload Model Baru ke Worker</div>

            <div class="flex gap-2">

                <label class="flex-1 cursor-pointer">

                    <input type="file" id="model-file-input" accept=".pt,.onnx,.zip" class="hidden"

                           onchange="onModelFileChosen()">

                    <div class="flex items-center gap-2 px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-lowest text-sm text-on-surface-variant hover:bg-surface-container-low truncate">

                        <svg class="w-4 h-4 shrink-0 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"

                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>

                        </svg>

                        <span id="model-file-label" class="truncate">Pilih .pt, .onnx, atau .zip</span>

                    </div>

                </label>

                <button type="button" id="btn-upload-model" onclick="doUploadModel()"

                        class="px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed shrink-0"

                        disabled>Upload</button>

            </div>

            {{-- Progress bar --}}

            <div id="upload-progress" class="hidden mt-2 space-y-1">

                <div class="flex items-center gap-2">

                    <div class="flex-1 bg-surface-container-lowest rounded-full h-1.5 border border-outline-variant">

                        <div id="upload-bar" class="bg-primary-fixed h-1.5 rounded-full transition-all duration-150" style="width:0%"></div>

                    </div>

                    <span id="upload-pct" class="text-xs text-on-surface-variant w-8 text-right">0%</span>

                </div>

                <div id="upload-status" class="text-xs text-on-surface-variant"></div>

            </div>

            <p class="text-xs text-outline mt-1.5">Format: .pt (PyTorch), .onnx, atau .zip yang berisi file .pt</p>

        </div>

        {{-- Detection settings --}}

        <div class="grid grid-cols-2 gap-3">

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Detection Size</label>

                <input type="number" id="f-det-size" value="640" min="320" max="1280" step="32"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none">

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Proses Setiap N Frame</label>

                <input type="number" id="f-skip-frame" value="2" min="1" max="10"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none">

            </div>

        </div>

        <div class="slider-wrap">

            <label class="block text-xs text-on-surface-variant mb-1">

                <span>Confidence Threshold</span>

                <span id="conf-val" class="font-medium text-primary">0.40</span>

            </label>

            <input type="range" id="f-confidence" min="0.10" max="0.95" step="0.01" value="0.40"

                   oninput="document.getElementById('conf-val').textContent = parseFloat(this.value).toFixed(2)">

        </div>

        <div>

            <label class="block text-xs text-on-surface-variant mb-1">Class ID Mapping (JSON)</label>

            <textarea id="f-class-map" rows="5"

                      class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 font-mono text-xs focus:ring-2 focus:ring-primary/20 focus:outline-none resize-none">{

  "person_id": 5,

  "helmet_ids": [1],

  "vest_ids": [6],

  "boots_ids": [0],

  "no_helmet_ids": [3],

  "no_vest_ids": [4],

  "no_boots_ids": [2]

}</textarea>

            <p class="text-xs text-outline mt-1">Model 7-kelas: boots(0) helmet(1) no_boots(2) no_helmet(3) no_vest(4) person(5) vest(6)</p>

        </div>

        <div class="grid grid-cols-2 gap-3 items-center">

            <div>

                <label class="flex items-center gap-2 text-sm text-on-surface cursor-pointer">

                    <input type="checkbox" id="f-auto-ss" checked class="rounded">

                    Auto Screenshot Pelanggaran

                </label>

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Cooldown Screenshot (detik)</label>

                <input type="number" id="f-ss-cooldown" value="30" min="5" max="300"

                       class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none">

            </div>

        </div>

      </div>

      {{-- TAB 4: PTZ --}}

      <div id="tab-ptz" class="tab-content space-y-3">

        <div class="flex items-center gap-3 p-3 bg-surface-container-low rounded-lg">

            <label class="flex items-center gap-2 text-sm text-on-surface cursor-pointer">

                <input type="checkbox" id="f-ptz-enabled" onchange="togglePtzFields()"

                       class="rounded">

                Kamera ini mendukung PTZ (Pan-Tilt-Zoom)

            </label>

        </div>

        <div id="ptz-fields" class="hidden space-y-3">

            <div class="slider-wrap">

                <label class="block text-xs text-on-surface-variant mb-1">

                    <span>Kecepatan PTZ</span>

                    <span id="ptz-speed-val" class="font-medium text-primary">0.6</span>

                </label>

                <input type="range" id="f-ptz-speed" min="0.1" max="1.0" step="0.1" value="0.6"

                       oninput="document.getElementById('ptz-speed-val').textContent = parseFloat(this.value).toFixed(1)">

            </div>

            <div class="grid grid-cols-2 gap-3">

                <div>

                    <label class="block text-xs text-on-surface-variant mb-1">Durasi Gerakan (detik)</label>

                    <input type="number" id="f-ptz-duration" value="0.4" min="0.1" max="5.0" step="0.1"

                           class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:outline-none">

                </div>

            </div>

            <div>

                <label class="block text-xs text-on-surface-variant mb-1">Preset Positions (JSON)</label>

                <textarea id="f-ptz-presets" rows="4"

                          class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 font-mono text-xs focus:ring-2 focus:ring-primary/20 focus:outline-none resize-none"

                          placeholder='[{"name":"Pintu Masuk","pan":0.5,"tilt":0.2,"zoom":1.0}]'></textarea>

                <p class="text-xs text-outline mt-1">Format: array of {name, pan, tilt, zoom}</p>

            </div>

        </div>

      </div>

    </div>

    {{-- Modal Footer --}}

    <div class="flex items-center justify-between px-6 py-4 border-t border-outline-variant bg-surface-container-low rounded-b-2xl">

        <button onclick="closeModal()" class="px-4 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-lowest">

            Batal

        </button>

        <div class="flex gap-2">

            <button onclick="saveCamera(true)" id="btn-save-test"

                    class="px-4 py-2 text-sm border border-blue-200 text-on-primary-fixed-variant rounded-lg hover:bg-primary-fixed">

                Simpan & Test

            </button>

            <button onclick="saveCamera(false)" id="btn-save"

                    class="px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">

                Simpan

            </button>

        </div>

    </div>

  </div>

</div>

{{-- Hapus Konfirmasi --}}

<div id="del-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.4)">

    <div class="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-sm shadow-2xl mx-4">

        <h3 class="font-semibold text-on-surface mb-2">Hapus Kamera</h3>

        <p class="text-sm text-on-surface-variant mb-5">Kamera <strong id="del-name"></strong> akan dihapus. Aksi ini tidak bisa dibatalkan.</p>

        <div class="flex gap-2">

            <button onclick="confirmDelete()" class="flex-1 py-2 text-sm bg-error text-white rounded-lg hover:bg-red-700">Hapus</button>

            <button onclick="document.getElementById('del-modal').classList.add('hidden')"

                    class="flex-1 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">Batal</button>

        </div>

    </div>

</div>

@endsection

@push('scripts')

<script>

const IS_ADMIN = {{ Auth::user()->role === 'admin' ? 'true' : 'false' }};

let editingId   = null;

let deleteId    = null;

let zones       = [];

let currentPage = 1;

let debounceTimer = null;

// ─── Data Loading ─────────────────────────────────────────────────────────────

async function loadData(page = 1) {

    currentPage = page;

    const params = new URLSearchParams({

        per_page: 10,

        page,

        search:    document.getElementById('search').value.trim(),

        zone_id:   document.getElementById('f-zone').value,

        is_active: document.getElementById('f-status').value,

        connection_status: document.getElementById('f-conn').value,

    });

    // Remove empty params

    [...params.entries()].forEach(([k, v]) => { if (!v) params.delete(k); });

    try {

        const res = await api('GET', `/api/cameras?${params}`);

        renderCameras(res.data ?? []);

        renderPagination(res.meta);

    } catch (e) { toast('Gagal memuat data kamera.', 'error'); }

}

function debounceLoad() {

    clearTimeout(debounceTimer);

    debounceTimer = setTimeout(() => loadData(1), 350);

}

async function loadZones() {

    try {

        const res = await api('GET', '/api/zones');

        zones = res.data ?? res;

        const fZone = document.getElementById('f-zone');

        zones.forEach(z => {

            const opt = new Option(z.name, z.id);

            fZone.appendChild(opt);

        });

    } catch (e) {}

}

// ─── Render ───────────────────────────────────────────────────────────────────

function renderCameras(cameras) {

    document.getElementById('loading').classList.add('hidden');

    const tbody = document.getElementById('cam-body');

    if (!cameras.length) {

        document.getElementById('cam-table').classList.add('hidden');

        document.getElementById('empty').classList.remove('hidden');

        return;

    }

    document.getElementById('empty').classList.add('hidden');

    document.getElementById('cam-table').classList.remove('hidden');

    tbody.innerHTML = cameras.map(c => `

        <tr class="hover:bg-surface-container-low">

            <td class="px-4 py-3">

                <div class="font-medium text-on-surface">${escHtml(c.name)}</div>

            </td>

            <td class="px-4 py-3 text-on-surface-variant text-xs">${escHtml(c.zone_name ?? '—')}</td>

            <td class="px-4 py-3 text-outline text-xs font-mono">${escHtml(c.dvr_channel ?? '—')}</td>

            <td class="px-4 py-3 text-on-surface-variant text-xs font-mono">${escHtml(c.ip_address ?? '—')}</td>

            <td class="px-4 py-3">

                <span class="px-2 py-0.5 rounded-full text-xs font-medium badge-${c.connection_status}">

                    ${connLabel(c.connection_status)}

                </span>

            </td>

            <td class="px-4 py-3">

                <span class="px-2 py-0.5 rounded-full text-xs font-medium ${c.is_active ? 'badge-active' : 'badge-inactive'}">

                    ${c.is_active ? 'Aktif' : 'Nonaktif'}

                </span>

            </td>

            <td class="px-4 py-3 text-right">

                <div class="flex items-center justify-end gap-2">

                    ${IS_ADMIN ? `<button onclick='editCamera(${JSON.stringify(c)})' class="text-xs text-primary hover:underline">Edit</button>` : ''}

                    <a href="/cameras/${c.id}" class="text-xs text-on-surface-variant hover:underline">Detail</a>

                    ${IS_ADMIN ? `

                    <button onclick="testConn(${c.id})" class="text-xs text-indigo-500 hover:underline">Test</button>

                    <button onclick="openDelete(${c.id}, '${escHtml(c.name)}')" class="text-xs text-error hover:underline">Hapus</button>

                    ` : ''}

                </div>

            </td>

        </tr>

    `).join('');

}

function renderPagination(meta) {

    if (!meta || meta.last_page <= 1) {

        document.getElementById('pagination').classList.add('hidden');

        return;

    }

    const p = document.getElementById('pagination');

    p.classList.remove('hidden');

    p.innerHTML = `

        <span>Halaman ${meta.page} dari ${meta.last_page} &bull; Total ${meta.total} kamera</span>

        <div class="flex gap-1">

            ${meta.page > 1 ? `<button onclick="loadData(${meta.page - 1})" class="px-3 py-1 border border-outline-variant rounded-lg hover:bg-surface-container-low">← Prev</button>` : ''}

            ${meta.page < meta.last_page ? `<button onclick="loadData(${meta.page + 1})" class="px-3 py-1 border border-outline-variant rounded-lg hover:bg-surface-container-low">Next →</button>` : ''}

        </div>

    `;

}

function connLabel(s) {

    return { online: '● Online', offline: '● Offline', unknown: '● Unknown' }[s] ?? '— Unknown';

}

// ─── Modal ────────────────────────────────────────────────────────────────────

function openModal(camera = null) {

    editingId = camera?.id ?? null;

    document.getElementById('modal-title').textContent = camera ? 'Edit Kamera' : 'Tambah Kamera';

    switchTab('tab-info', document.querySelector('.tab-btn'));

    document.getElementById('conn-result').classList.add('hidden');

    // Tab 1

    document.getElementById('f-name').value   = camera?.name ?? '';

    document.getElementById('f-dvr').value    = camera?.dvr_channel ?? '';

    document.getElementById('f-active').value = camera?.is_active ? '1' : '0';

    document.getElementById('f-desc').value   = camera?.description ?? '';

    buildZoneOptions(camera?.zone_id);

    // Tab 2

    document.getElementById('f-ip').value            = camera?.ip_address ?? '';

    document.getElementById('f-port-onvif').value    = camera?.port_onvif ?? 2020;

    document.getElementById('f-port-rtsp').value     = camera?.port_rtsp ?? 554;

    document.getElementById('f-username').value      = camera?.username ?? '';

    document.getElementById('f-timeout').value       = camera?.connection_timeout ?? 10;

    const passInput  = document.getElementById('f-password');

    const passToggle = document.getElementById('pass-toggle-btn');

    const passLabel  = document.getElementById('pass-label');

    if (camera && camera.has_password) {

        passInput.value       = '';

        passInput.placeholder = '••••••••••';

        passInput.disabled    = true;

        passToggle.classList.remove('hidden');

        passLabel.innerHTML   = 'Password <span class="text-outline font-normal">(tersimpan)</span>';

    } else {

        passInput.value       = '';

        passInput.placeholder = '••••••••••';

        passInput.disabled    = false;

        passToggle.classList.add('hidden');

        passLabel.innerHTML   = 'Password <span class="text-error">*</span>';

    }

    const rtspPath = camera?.rtsp_path ?? '/stream2';

    const pathSel  = document.getElementById('f-rtsp-path');

    const knownPaths = ['/stream1', '/stream2', '/live'];

    if (knownPaths.includes(rtspPath)) {

        pathSel.value = rtspPath;

        document.getElementById('custom-path-wrap').classList.add('hidden');

    } else {

        pathSel.value = 'custom';

        document.getElementById('custom-path-wrap').classList.remove('hidden');

        document.getElementById('f-rtsp-path-custom').value = rtspPath;

    }

    document.getElementById('f-rtsp-transport').value = camera?.rtsp_transport ?? 'tcp';

    // Tab 3

    document.getElementById('f-model-path').value  = camera?.ai_model_path ?? '';

    document.getElementById('f-det-size').value     = camera?.detection_size ?? 640;

    document.getElementById('f-skip-frame').value   = camera?.process_every_n_frame ?? 2;

    const conf = camera?.confidence_threshold ?? 0.40;

    document.getElementById('f-confidence').value   = conf;

    document.getElementById('conf-val').textContent = parseFloat(conf).toFixed(2);

    document.getElementById('f-class-map').value    = JSON.stringify(camera?.class_mapping ?? {

        person_id: 5, helmet_ids: [1], vest_ids: [6], boots_ids: [0],

        no_helmet_ids: [3], no_vest_ids: [4], no_boots_ids: [2]

    }, null, 2);

    document.getElementById('f-auto-ss').checked    = camera?.auto_screenshot !== false;

    document.getElementById('f-ss-cooldown').value  = camera?.screenshot_cooldown ?? 30;

    // Tab 4

    const ptzEnabled = !!camera?.ptz_enabled;

    document.getElementById('f-ptz-enabled').checked = ptzEnabled;

    document.getElementById('ptz-fields').classList.toggle('hidden', !ptzEnabled);

    const spd = camera?.ptz_speed ?? 0.6;

    document.getElementById('f-ptz-speed').value      = spd;

    document.getElementById('ptz-speed-val').textContent = parseFloat(spd).toFixed(1);

    document.getElementById('f-ptz-duration').value   = camera?.ptz_movement_duration ?? 0.4;

    document.getElementById('f-ptz-presets').value    = camera?.preset_positions?.length

        ? JSON.stringify(camera.preset_positions, null, 2) : '';

    document.getElementById('modal').classList.remove('hidden');

}

async function editCamera(c) {

    // Fetch detailed data first

    try {

        const res = await api('GET', `/api/cameras/${c.id}`);

        openModal(res.data);

    } catch (e) {

        openModal(c);

    }

}

function closeModal() {

    document.getElementById('modal').classList.add('hidden');

    editingId = null;

}

function switchTab(tabId, btn) {

    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));

    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

    document.getElementById(tabId).classList.add('active');

    if (btn) btn.classList.add('active');

    if (tabId === 'tab-ai') loadModelList();

}

function buildZoneOptions(selectedId = null) {

    const sel = document.getElementById('f-zone-modal');

    sel.innerHTML = '<option value="">— Pilih Zona —</option>' +

        zones.map(z => `<option value="${z.id}" ${z.id == selectedId ? 'selected' : ''}>${escHtml(z.name)}</option>`).join('');

}

function togglePasswordEdit() {

    const inp = document.getElementById('f-password');

    inp.disabled = false;

    inp.placeholder = 'Masukkan password baru';

    inp.focus();

    document.getElementById('pass-toggle-btn').classList.add('hidden');

    document.getElementById('pass-label').innerHTML = 'Password Baru';

}

function checkCustomPath() {

    const val  = document.getElementById('f-rtsp-path').value;

    const wrap = document.getElementById('custom-path-wrap');

    wrap.classList.toggle('hidden', val !== 'custom');

}

function togglePtzFields() {

    const enabled = document.getElementById('f-ptz-enabled').checked;

    document.getElementById('ptz-fields').classList.toggle('hidden', !enabled);

}

function getRtspPath() {

    const sel = document.getElementById('f-rtsp-path').value;

    return sel === 'custom' ? document.getElementById('f-rtsp-path-custom').value.trim() : sel;

}

function buildPayload() {

    let classMap = null;

    try { classMap = JSON.parse(document.getElementById('f-class-map').value); } catch(e) {}

    let presets = null;

    try { presets = JSON.parse(document.getElementById('f-ptz-presets').value || '[]'); } catch(e) {}

    const payload = {

        name:                   document.getElementById('f-name').value.trim(),

        zone_id:                parseInt(document.getElementById('f-zone-modal').value) || null,

        dvr_channel:            document.getElementById('f-dvr').value.trim() || undefined,

        description:            document.getElementById('f-desc').value.trim() || null,

        is_active:              document.getElementById('f-active').value === '1',

        ip_address:             document.getElementById('f-ip').value.trim(),

        port_onvif:             parseInt(document.getElementById('f-port-onvif').value),

        port_rtsp:              parseInt(document.getElementById('f-port-rtsp').value),

        username:               document.getElementById('f-username').value.trim(),

        rtsp_path:              getRtspPath(),

        rtsp_transport:         document.getElementById('f-rtsp-transport').value,

        connection_timeout:     parseInt(document.getElementById('f-timeout').value),

        ai_model_path:          document.getElementById('f-model-path').value.trim() || null,

        detection_size:         parseInt(document.getElementById('f-det-size').value),

        confidence_threshold:   parseFloat(document.getElementById('f-confidence').value),

        process_every_n_frame:  parseInt(document.getElementById('f-skip-frame').value),

        class_mapping:          classMap ? JSON.stringify(classMap) : null,

        auto_screenshot:        document.getElementById('f-auto-ss').checked,

        screenshot_cooldown:    parseInt(document.getElementById('f-ss-cooldown').value),

        ptz_enabled:            document.getElementById('f-ptz-enabled').checked,

        ptz_speed:              parseFloat(document.getElementById('f-ptz-speed').value),

        ptz_movement_duration:  parseFloat(document.getElementById('f-ptz-duration').value),

        preset_positions:       presets ? JSON.stringify(presets) : null,

    };

    // Only include password if not disabled (i.e., user typed something or it's a new camera)

    const passEl = document.getElementById('f-password');

    if (!passEl.disabled && passEl.value) {

        payload.password = passEl.value;

    }

    return payload;

}

async function saveCamera(testAfter = false) {

    const payload = buildPayload();

    if (!payload.name) { toast('Nama kamera wajib diisi.', 'error'); return; }

    if (!payload.zone_id) { toast('Zona wajib dipilih.', 'error'); return; }

    if (!payload.ip_address) { toast('IP address wajib diisi.', 'error'); return; }

    const btn = document.getElementById('btn-save');

    btn.textContent = 'Menyimpan...';

    btn.disabled = true;

    try {

        if (editingId) {

            await api('PUT', `/api/cameras/${editingId}`, payload);

            toast('Kamera berhasil diperbarui.');

        } else {

            await api('POST', '/api/cameras', payload);

            toast('Kamera berhasil ditambahkan.');

        }

        if (!testAfter) closeModal();

        loadData(currentPage);

        if (testAfter && editingId) {

            await runConnectionTest();

        }

    } catch (e) {

        const msg = e.errors ? Object.values(e.errors).flat().join('\n') : (e.message ?? 'Gagal menyimpan.');

        toast(msg, 'error');

    } finally {

        btn.textContent = 'Simpan';

        btn.disabled    = false;

    }

}

// ─── Connection Test ──────────────────────────────────────────────────────────

async function runConnectionTest() {

    if (!editingId) {

        toast('Simpan kamera terlebih dahulu sebelum test koneksi.', 'error');

        return;

    }

    const btn = document.getElementById('btn-test-conn');

    btn.textContent = 'Testing...';

    btn.disabled    = true;

    document.getElementById('conn-result').classList.add('hidden');

    switchTab('tab-network', document.querySelectorAll('.tab-btn')[1]);

    try {

        const res = await api('POST', `/api/cameras/${editingId}/test-connection`);

        showConnResult(res.data);

    } catch (e) {

        showConnResult({ overall: false, onvif: { ok: false, error: e.message }, rtsp: { ok: false } });

    } finally {

        btn.textContent = '🔍 Test Koneksi';

        btn.disabled    = false;

    }

}

async function testConn(id) {

    toast('Testing koneksi...', 'success');

    try {

        const res = await api('POST', `/api/cameras/${id}/test-connection`);

        const d = res.data;

        toast(d.overall ? '✓ Koneksi berhasil' : '✗ Koneksi gagal', d.overall ? 'success' : 'error');

        loadData(currentPage);

    } catch (e) {

        toast('Test koneksi gagal.', 'error');

    }

}

function showConnResult(d) {

    const el = document.getElementById('conn-result');

    const ok = d?.overall;

    el.className = `conn-result ${ok ? 'conn-ok' : 'conn-fail'}`;

    let html = `<div class="font-medium mb-1">${ok ? 'Koneksi Berhasil' : 'Koneksi Gagal'}</div>`;

    if (d?.onvif) {

        html += `<div class="text-xs mt-1">ONVIF: ${d.onvif.ok ? 'Koneksi Berhasil' : 'Koneksi Gagal' + (d.onvif.error ?? 'Gagal')}</div>`;

        if (d.onvif.latency_ms) html += ` <span class="text-outline">(${d.onvif.latency_ms}ms)</span>`;

    }

    if (d?.rtsp) {

        html += `<div class="text-xs mt-0.5">RTSP: ${d.rtsp.ok ? 'Stream tersedia' : 'Stream tidak tersedia' + (d.rtsp.error ?? 'Gagal')}`;

        if (d.rtsp.resolution) html += ` <span class="text-outline">${d.rtsp.resolution}</span>`;

        if (d.rtsp.fps)        html += ` <span class="text-outline">${d.rtsp.fps}fps</span>`;

        html += '</div>';

    }

    if (d?.note) html += `<div class="text-xs text-yellow-600 mt-1">${d.note}</div>`;

    el.innerHTML = html;

    el.classList.remove('hidden');

}

// ─── Delete ───────────────────────────────────────────────────────────────────

function openDelete(id, name) {

    deleteId = id;

    document.getElementById('del-name').textContent = name;

    document.getElementById('del-modal').classList.remove('hidden');

}

async function confirmDelete() {

    if (!deleteId) return;

    try {

        await api('DELETE', `/api/cameras/${deleteId}`);

        toast('Kamera berhasil dihapus.');

        document.getElementById('del-modal').classList.add('hidden');

        loadData(currentPage);

    } catch (e) {

        toast(e.message ?? 'Gagal menghapus.', 'error');

    } finally { deleteId = null; }

}

// ─── Utilities ────────────────────────────────────────────────────────────────

function escHtml(str) {

    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

}

document.getElementById('modal').addEventListener('click', e => {

    if (e.target === e.currentTarget) closeModal();

});

// ─── Model Management ─────────────────────────────────────────────────────────

// Cache daftar model agar onclick bisa referensi by index (aman untuk path Windows)

let _modelCache = [];

async function loadModelList() {

    const list = document.getElementById('model-list');

    list.innerHTML = '<div class="py-3 text-xs text-outline">Memuat...</div>';

    try {

        const res = await api('GET', '/api/models');

        if (res.error) {

            list.innerHTML = `

                <div class="py-3 px-3 text-xs text-orange-600 flex items-start gap-2">

                    <span class="shrink-0">⚠</span>

                    <span>${escHtml(res.error)}</span>

                </div>`;

            return;

        }

        _modelCache = res.data ?? [];

        if (!_modelCache.length) {

            list.innerHTML = '<div class="py-3 px-3 text-xs text-outline">Belum ada model di folder <code>detection_worker/models/</code>. Upload file di bawah.</div>';

            return;

        }

        const currentPath = document.getElementById('f-model-path').value;

        list.innerHTML = _modelCache.map((m, i) => {

            const isSelected = currentPath === m.path;

            return `

            <div class="flex items-center justify-between px-3 py-2 hover:bg-primary-fixed cursor-pointer border-b border-surface-container-low last:border-0"

                 onclick="selectModelByIdx(${i})">

                <div class="min-w-0">

                    <div class="text-sm font-medium text-on-surface truncate">${escHtml(m.name)}</div>

                    <div class="text-xs text-outline">${m.size_mb} MB</div>

                </div>

                <span class="ml-3 shrink-0 text-xs px-2 py-0.5 rounded-full ${isSelected

                    ? 'bg-blue-100 text-on-primary-fixed-variant font-medium'

                    : 'text-primary hover:underline'}">

                    ${isSelected ? '✓ Aktif' : 'Pilih'}

                </span>

            </div>`;

        }).join('');

    } catch (e) {

        list.innerHTML = '<div class="py-3 text-xs text-red-400">Gagal memuat daftar model.</div>';

    }

}

function selectModelByIdx(i) {

    const m = _modelCache[i];

    if (m) selectModel(m.path, m.name);

}

function selectModel(path, name) {

    document.getElementById('f-model-path').value = path;

    loadModelList();  // refresh agar badge "Aktif" tampil

    toast(`Model "${name}" dipilih.`, 'success');

}

function onModelFileChosen() {

    const input = document.getElementById('model-file-input');

    const label = document.getElementById('model-file-label');

    const btn   = document.getElementById('btn-upload-model');

    if (input.files.length) {

        label.textContent = input.files[0].name;

        btn.disabled = false;

    } else {

        label.textContent = 'Pilih .pt, .onnx, atau .zip';

        btn.disabled = true;

    }

}

async function doUploadModel() {

    const input  = document.getElementById('model-file-input');

    if (!input.files.length) return;

    const file       = input.files[0];

    const btn        = document.getElementById('btn-upload-model');

    const progressEl = document.getElementById('upload-progress');

    const bar        = document.getElementById('upload-bar');

    const pct        = document.getElementById('upload-pct');

    const statusEl   = document.getElementById('upload-status');

    btn.disabled    = true;

    btn.textContent = 'Uploading...';

    progressEl.classList.remove('hidden');

    statusEl.textContent = `Mengunggah ${file.name}...`;

    const formData = new FormData();

    formData.append('model', file);

    try {

        const result = await new Promise((resolve, reject) => {

            const xhr = new XMLHttpRequest();

            xhr.open('POST', '/api/cameras/upload-model');

            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

            xhr.upload.onprogress = e => {

                if (e.lengthComputable) {

                    const p = Math.round(e.loaded / e.total * 100);

                    bar.style.width = p + '%';

                    pct.textContent = p + '%';

                }

            };

            xhr.onload = () => {

                try {

                    const data = JSON.parse(xhr.responseText);

                    xhr.status < 300 ? resolve(data) : reject(new Error(data.message ?? 'Upload gagal'));

                } catch { reject(new Error('Response tidak valid')); }

            };

            xhr.onerror = () => reject(new Error('Koneksi gagal'));

            xhr.send(formData);

        });

        toast(`Model "${result.model_name}" (${result.size_mb} MB) berhasil diupload.`, 'success');

        selectModel(result.model_path, result.model_name);

        input.value = '';

        document.getElementById('model-file-label').textContent = 'Pilih .pt, .onnx, atau .zip';

    } catch (e) {

        toast(e.message ?? 'Upload gagal.', 'error');

    } finally {

        btn.disabled    = false;

        btn.textContent = 'Upload';

        progressEl.classList.add('hidden');

        bar.style.width = '0%';

        pct.textContent = '0%';

        statusEl.textContent = '';

    }

}

document.addEventListener('DOMContentLoaded', () => {

    loadZones();

    loadData();

});

</script>

@endpush