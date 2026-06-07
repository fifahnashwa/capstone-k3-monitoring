@extends('layouts.app')

@section('title', 'Manajemen Model Wajah')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Manajemen Model Wajah Pekerja</h1>
            <p class="text-sm text-gray-500 mt-0.5">Training dan pengelolaan model pengenalan wajah untuk anti-double detection.</p>
        </div>
        <button onclick="openTrainModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah / Train Model
        </button>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                    <th class="px-5 py-3 text-left font-medium">Pekerja</th>
                    <th class="px-5 py-3 text-left font-medium">ID Karyawan</th>
                    <th class="px-5 py-3 text-left font-medium">Status</th>
                    <th class="px-5 py-3 text-left font-medium">Embedding</th>
                    <th class="px-5 py-3 text-left font-medium">Dilatih</th>
                    <th class="px-5 py-3 text-right font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody id="face-table-body" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-gray-400">
                        <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Memuat data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

{{-- Train Modal --}}
<div id="train-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Training Model Wajah</h2>
            <button onclick="closeTrainModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ID Karyawan <span class="text-red-500">*</span></label>
                <input id="emp-id" type="text" placeholder="cth: EMP001"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Karyawan <span class="text-red-500">*</span></label>
                <input id="emp-name" type="text" placeholder="Nama lengkap"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Foto Wajah <span class="text-red-500">*</span> <span class="text-gray-400 font-normal">(min 3, maks 50 foto · JPG/PNG · maks 10MB/foto)</span></label>
                <div id="drop-zone"
                    class="relative border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 cursor-pointer transition-colors"
                    onclick="document.getElementById('photo-input').click()"
                    ondragover="event.preventDefault(); this.classList.add('border-blue-400')"
                    ondragleave="this.classList.remove('border-blue-400')"
                    ondrop="handleDrop(event)">
                    <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm text-gray-500">Klik atau seret foto ke sini</p>
                    <input id="photo-input" type="file" accept="image/jpeg,image/png" multiple class="hidden" onchange="handleFiles(this.files)">
                </div>
                <div id="photo-preview" class="mt-3 flex flex-wrap gap-2 hidden"></div>
            </div>
            <div id="train-error" class="hidden text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg"></div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
            <button onclick="closeTrainModal()" class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
            <button id="train-btn" onclick="submitTrain()" class="px-5 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Mulai Training
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let selectedFiles = [];

// ── Load table ────────────────────────────────────────────────────────────────
async function loadModels() {
    try {
        const data = await api('GET', '/api/worker-face-models');
        renderTable(data.data ?? []);
    } catch (e) {
        renderTable([]);
    }
}

function renderTable(models) {
    const tbody = document.getElementById('face-table-body');
    if (!models.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-gray-400">
                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Belum ada model wajah yang dilatih.
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = models.map(m => {
        const statusBadge = {
            trained: '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>Terlatih</span>',
            pending: '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700"><span class="w-1.5 h-1.5 rounded-full bg-yellow-500 inline-block"></span>Pending</span>',
            failed:  '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700"><span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>Gagal</span>',
        }[m.status] ?? m.status;

        const trainedAt = m.trained_at
            ? new Date(m.trained_at).toLocaleString('id-ID', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})
            : '—';

        return `<tr class="hover:bg-gray-50">
            <td class="px-5 py-3 font-medium text-gray-900">${esc(m.employee_name)}</td>
            <td class="px-5 py-3 text-gray-500 font-mono text-xs">${esc(m.employee_id)}</td>
            <td class="px-5 py-3">${statusBadge}</td>
            <td class="px-5 py-3 text-gray-700">${m.embeddings_count > 0 ? m.embeddings_count + ' embedding' : '—'}</td>
            <td class="px-5 py-3 text-gray-500 text-xs">${trainedAt}</td>
            <td class="px-5 py-3 text-right">
                <button onclick="deleteModel(${m.id}, '${esc(m.employee_name)}')"
                    class="text-xs text-red-600 hover:text-red-800 px-2 py-1 rounded hover:bg-red-50">Hapus</button>
            </td>
        </tr>`;
    }).join('');
}

// ── Train modal ───────────────────────────────────────────────────────────────
function openTrainModal() {
    selectedFiles = [];
    document.getElementById('emp-id').value = '';
    document.getElementById('emp-name').value = '';
    document.getElementById('photo-preview').innerHTML = '';
    document.getElementById('photo-preview').classList.add('hidden');
    document.getElementById('train-error').classList.add('hidden');
    document.getElementById('train-btn').disabled = false;
    document.getElementById('train-btn').textContent = 'Mulai Training';
    document.getElementById('photo-input').value = '';
    document.getElementById('train-modal').classList.remove('hidden');
}

function closeTrainModal() {
    document.getElementById('train-modal').classList.add('hidden');
}

function handleFiles(files) {
    for (const f of files) {
        if (!selectedFiles.find(x => x.name === f.name && x.size === f.size)) {
            selectedFiles.push(f);
        }
    }
    renderPreview();
}

function handleDrop(event) {
    event.preventDefault();
    document.getElementById('drop-zone').classList.remove('border-blue-400');
    handleFiles(event.dataTransfer.files);
}

function renderPreview() {
    const container = document.getElementById('photo-preview');
    if (!selectedFiles.length) {
        container.classList.add('hidden');
        container.innerHTML = '';
        return;
    }
    container.classList.remove('hidden');
    container.innerHTML = selectedFiles.map((f, i) => {
        const mb = (f.size / 1024 / 1024).toFixed(1);
        const over = parseFloat(mb) > 10;
        return `
        <div class="relative group flex flex-col items-center gap-0.5">
            <div class="relative">
                <img src="${URL.createObjectURL(f)}" class="w-16 h-16 object-cover rounded-lg border ${over ? 'border-red-400' : 'border-gray-200'}">
                <button onclick="removeFile(${i})" class="absolute -top-1.5 -right-1.5 hidden group-hover:flex w-5 h-5 bg-red-500 text-white rounded-full items-center justify-center text-xs">×</button>
            </div>
            <span class="text-xs ${over ? 'text-red-500 font-medium' : 'text-gray-400'}">${mb}MB</span>
        </div>`;
    }).join('');
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderPreview();
}

async function submitTrain() {
    const empId   = document.getElementById('emp-id').value.trim();
    const empName = document.getElementById('emp-name').value.trim();
    const errEl   = document.getElementById('train-error');
    const btn     = document.getElementById('train-btn');

    errEl.classList.add('hidden');

    if (!empId)              return showTrainError('ID Karyawan wajib diisi.');
    if (!empName)            return showTrainError('Nama Karyawan wajib diisi.');
    if (selectedFiles.length < 3) return showTrainError(`Minimal 3 foto diperlukan. Saat ini: ${selectedFiles.length}`);
    if (selectedFiles.length > 50) return showTrainError('Maksimal 50 foto.');

    btn.disabled = true;
    btn.textContent = 'Training...';

    const form = new FormData();
    form.append('employee_id', empId);
    form.append('employee_name', empName);
    selectedFiles.forEach(f => form.append('photos[]', f));

    try {
        const res = await fetch('/api/worker-face-models/train', {
            method: 'POST',
            headers: {
                'Accept':       'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            credentials: 'same-origin',
            body: form,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw data;
        toast(data.message ?? 'Training berhasil!');
        closeTrainModal();
        loadModels();
    } catch (err) {
        const msg = err?.message || err?.errors ? formatErrors(err) : 'Training gagal. Coba lagi.';
        showTrainError(msg);
        btn.disabled = false;
        btn.textContent = 'Mulai Training';
    }
}

function showTrainError(msg) {
    const el = document.getElementById('train-error');
    el.textContent = msg;
    el.classList.remove('hidden');
}

function formatErrors(err) {
    if (err?.message) return err.message;
    if (err?.errors) return Object.values(err.errors).flat().join(' ');
    return 'Terjadi kesalahan.';
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function deleteModel(id, name) {
    if (!confirm(`Hapus model wajah "${name}"? Tindakan ini tidak dapat dibatalkan.`)) return;
    try {
        await api('DELETE', `/api/worker-face-models/${id}`);
        toast(`Model "${name}" berhasil dihapus.`);
        loadModels();
    } catch (e) {
        toast(e?.message ?? 'Gagal menghapus model.', 'error');
    }
}

// ── Utils ─────────────────────────────────────────────────────────────────────
function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', loadModels);
</script>
@endpush
