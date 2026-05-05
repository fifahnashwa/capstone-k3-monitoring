@extends('layouts.app')
@section('title', 'Kamera')
@section('content')

<div class="flex items-center justify-between mb-5">
    <h1 class="text-lg font-semibold text-gray-900">Kamera</h1>
    <button onclick="openModal()" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">+ Tambah kamera</button>
</div>

<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div id="loading" class="py-12 text-center text-sm text-gray-400">Memuat...</div>
    <table id="cam-table" class="hidden w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs text-gray-400 uppercase tracking-wide">
                <th class="px-4 py-3 font-medium">Nama</th>
                <th class="px-4 py-3 font-medium">Zona</th>
                <th class="px-4 py-3 font-medium">DVR Channel</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody id="cam-body" class="divide-y divide-gray-50"></tbody>
    </table>
    <div id="empty" class="hidden py-12 text-center text-sm text-gray-400">Belum ada kamera.</div>
</div>

{{-- MODAL --}}
<div id="modal" class="hidden fixed inset-0 z-40 flex items-center justify-center" style="background:rgba(0,0,0,0.35)">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h2 id="modal-title" class="text-base font-semibold text-gray-900 mb-4">Tambah kamera</h2>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Nama kamera</label>
                <input type="text" id="f-name" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Zona</label>
                <select id="f-zone" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white"></select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">DVR Channel</label>
                <input type="text" id="f-dvr" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2" placeholder="CH-01">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Status</label>
                <select id="f-active" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white">
                    <option value="1">Aktif</option>
                    <option value="0">Tidak Aktif</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-5">
            <button onclick="saveCamera()" class="flex-1 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Simpan</button>
            <button onclick="closeModal()" class="flex-1 py-2 text-sm border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50">Batal</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let editingId = null;
let zones     = [];

async function loadData() {
    try {
        const [cams, zoneData] = await Promise.all([
            api('GET', '/api/cameras'),
            api('GET', '/api/zones'),
        ]);
        zones = zoneData.data ?? zoneData;
        renderCameras(cams.data ?? cams);
    } catch(e) { toast('Gagal memuat', 'error'); }
}

function renderCameras(cameras) {
    document.getElementById('loading').classList.add('hidden');
    if (!cameras.length) {
        document.getElementById('empty').classList.remove('hidden');
        return;
    }
    document.getElementById('cam-table').classList.remove('hidden');

    document.getElementById('cam-body').innerHTML = cameras.map(c => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-800">${escHtml(c.name)}</td>
            <td class="px-4 py-3 text-gray-500">${c.zone?.name ? escHtml(c.zone.name) : '—'}</td>
            <td class="px-4 py-3 text-gray-400 text-xs font-mono">${c.dvr_channel ? escHtml(c.dvr_channel) : '—'}</td>
            <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium ${c.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}">
                    ${c.is_active ? 'Aktif' : 'Tidak Aktif'}
                </span>
            </td>
            <td class="px-4 py-3 text-right">
                <button onclick='editCamera(${JSON.stringify(c)})' class="text-xs text-blue-600 hover:underline mr-3">Edit</button>
                <button onclick="deleteCamera(${c.id})" class="text-xs text-red-500 hover:underline">Hapus</button>
            </td>
        </tr>
    `).join('');
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function buildZoneOptions(selectedId = null) {
    return zones.map(z =>
        `<option value="${z.id}" ${z.id == selectedId ? 'selected' : ''}>${escHtml(z.name)}</option>`
    ).join('');
}

function openModal(camera = null) {
    editingId = camera?.id ?? null;
    document.getElementById('modal-title').textContent = camera ? 'Edit kamera' : 'Tambah kamera';
    document.getElementById('f-name').value   = camera?.name ?? '';
    document.getElementById('f-dvr').value    = camera?.dvr_channel ?? '';
    document.getElementById('f-active').value = camera?.is_active ? '1' : '0';
    document.getElementById('f-zone').innerHTML = buildZoneOptions(camera?.zone_id ?? camera?.zone?.id);
    document.getElementById('modal').classList.remove('hidden');
}

function editCamera(c) { openModal(c); }
function closeModal()  { document.getElementById('modal').classList.add('hidden'); }

async function saveCamera() {
    const body = {
        name:        document.getElementById('f-name').value.trim(),
        zone_id:     parseInt(document.getElementById('f-zone').value),
        dvr_channel: document.getElementById('f-dvr').value.trim(),
        is_active:   document.getElementById('f-active').value === '1',
    };
    if (!body.name)        { toast('Nama wajib diisi', 'error'); return; }
    if (!body.dvr_channel) { toast('DVR Channel wajib diisi', 'error'); return; }
    try {
        if (editingId) { await api('PUT',  `/api/cameras/${editingId}`, body); toast('Kamera diperbarui.'); }
        else           { await api('POST', '/api/cameras', body);               toast('Kamera ditambahkan.'); }
        closeModal(); loadData();
    } catch(e) { toast(e.message ?? 'Gagal', 'error'); }
}

async function deleteCamera(id) {
    if (!confirm('Hapus kamera ini?')) return;
    try { await api('DELETE', `/api/cameras/${id}`); toast('Kamera dihapus.'); loadData(); }
    catch(e) { toast(e.message ?? 'Gagal', 'error'); }
}

document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('DOMContentLoaded', loadData);
</script>
@endpush