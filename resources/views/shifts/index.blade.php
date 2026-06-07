@extends('layouts.app')
@section('title', 'Shift')
@section('content')

<div class="flex items-center justify-between mb-5">
    <h1 class="text-lg font-semibold text-gray-900">Shift Kerja</h1>
    <button onclick="openModal()" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">+ Tambah shift</button>
</div>

<div class="grid grid-cols-2 gap-4" id="shifts-grid"></div>
<div id="loading" class="py-12 text-center text-sm text-gray-400">Memuat...</div>

{{-- MODAL --}}
<div id="modal" class="hidden fixed inset-0 z-40 flex items-center justify-center" style="background:rgba(0,0,0,0.35)">
    <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl">
        <h2 id="modal-title" class="text-base font-semibold text-gray-900 mb-4">Tambah shift</h2>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Nama shift</label>
                <input type="text" id="f-name" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2" placeholder="Shift 1">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Jam mulai</label>
                    <input type="time" id="f-start" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Jam selesai</label>
                    <input type="time" id="f-end" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                </div>
            </div>
        </div>
        <div class="flex gap-2 mt-5">
            <button onclick="saveShift()" class="flex-1 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Simpan</button>
            <button onclick="closeModal()" class="flex-1 py-2 text-sm border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50">Batal</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let editingId = null;

async function loadShifts() {
    try {
        const data = await api('GET', '/api/shifts');
        renderShifts(data.data ?? data);
    } catch(e) { toast('Gagal memuat', 'error'); }
}

function renderShifts(shifts) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('shifts-grid').innerHTML = shifts.map(s => `
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-medium text-gray-900">${s.name}</h2>
                    <p class="text-2xl font-semibold text-blue-600 mt-2">${s.start_time} <span class="text-gray-300 font-normal text-lg">–</span> ${s.end_time}</p>
                </div>
                <div class="flex gap-2 mt-1">
                    <button onclick='editShift(${JSON.stringify(s)})' class="text-xs text-blue-600 hover:underline">Edit</button>
                    <button onclick="deleteShift(${s.id}, '${s.name}')" class="text-xs text-red-500 hover:underline">Hapus</button>
                </div>
            </div>
        </div>
    `).join('') || '<p class="text-sm text-gray-400 col-span-2 text-center py-10">Belum ada shift.</p>';
}

function openModal(shift = null) {
    editingId = shift?.id ?? null;
    document.getElementById('modal-title').textContent = shift ? 'Edit shift' : 'Tambah shift';
    document.getElementById('f-name').value  = shift?.name ?? '';
    document.getElementById('f-start').value = shift?.start_time?.substring(0, 5) ?? '';
    document.getElementById('f-end').value   = shift?.end_time?.substring(0, 5) ?? '';
    document.getElementById('modal').classList.remove('hidden');
}

function editShift(s) { openModal(s); }
function closeModal() { document.getElementById('modal').classList.add('hidden'); }

async function saveShift() {
    const start = document.getElementById('f-start').value;
    const end   = document.getElementById('f-end').value;
    const body  = {
        name:       document.getElementById('f-name').value.trim(),
        start_time: start ? start + ':00' : '',
        end_time:   end   ? end   + ':00' : '',
    };
    if (!body.name || !body.start_time || !body.end_time) { toast('Semua field wajib diisi', 'error'); return; }
    try {
        if (editingId) { await api('PUT', `/api/shifts/${editingId}`, body); toast('Shift diperbarui.'); }
        else           { await api('POST', '/api/shifts', body);              toast('Shift ditambahkan.'); }
        closeModal(); loadShifts();
    } catch(e) { toast(e.message ?? 'Gagal', 'error'); }
}

async function deleteShift(id, name) {
    if (!confirm(`Hapus shift "${name}"?`)) return;
    try { await api('DELETE', `/api/shifts/${id}`); toast('Shift dihapus.'); loadShifts(); }
    catch(e) { toast(e.message ?? 'Gagal', 'error'); }
}

document.addEventListener('DOMContentLoaded', loadShifts);
</script>
@endpush