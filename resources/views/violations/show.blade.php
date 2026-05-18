@extends('layouts.app')
@section('title', 'Detail Pelanggaran')
@section('content')

<div class="flex items-center gap-3 mb-5">
    <a href="{{ route('violations.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← Kembali</a>
    <h1 class="text-lg font-semibold text-gray-900">Detail Pelanggaran</h1>
</div>

<div id="loading" class="py-20 text-center text-sm text-gray-400">Memuat...</div>
<div id="content" class="hidden grid grid-cols-3 gap-4">

    {{-- LEFT: image + info --}}
    <div class="col-span-2 space-y-4">
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div id="img-wrap" class="bg-gray-100 flex items-center justify-center min-h-48">
                <span class="text-xs text-gray-400">Tidak ada gambar</span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-5 space-y-4">
            <h2 class="text-sm font-medium text-gray-700">Informasi Deteksi</h2>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><p class="text-xs text-gray-400 mb-0.5">Waktu deteksi</p><p id="d-time" class="font-medium">—</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Kamera</p><p id="d-camera" class="font-medium">—</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Zona</p><p id="d-zone" class="font-medium">—</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Shift</p><p id="d-shift" class="font-medium">—</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Tipe pelanggaran</p><p id="d-type" class="font-medium">—</p></div>
                <div><p class="text-xs text-gray-400 mb-0.5">Confidence</p><p id="d-confidence" class="font-medium">—</p></div>
            </div>
            <div>
                <p class="text-xs text-gray-400 mb-1.5">Label APD</p>
                <div id="d-labels" class="flex flex-wrap gap-2"></div>
            </div>
        </div>
    </div>

    {{-- RIGHT: status + action --}}
    <div class="space-y-4">
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h2 class="text-sm font-medium text-gray-700 mb-3">Status</h2>
            <div id="d-status-badge" class="mb-4"></div>
            <div id="level-wrap" class="mb-4 hidden">
                <p class="text-xs text-gray-400 mb-1">Level</p>
                <div id="d-level-badge"></div>
            </div>

            {{-- Tampilkan nama pelanggar kalau sudah diisi --}}
            <div id="person-wrap" class="hidden mb-4 p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-400 mb-0.5">Nama pelanggar</p>
                <p id="d-person" class="text-sm font-medium text-gray-800">—</p>
            </div>

            {{-- Tampilkan catatan kalau sudah diisi --}}
            <div id="notes-wrap" class="hidden mb-4 p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-400 mb-0.5">Catatan validasi</p>
                <p id="d-notes" class="text-sm text-gray-600">—</p>
            </div>

            <div id="action-wrap" class="space-y-2">
                <button id="btn-validate" onclick="openActionModal(true)"
                    class="w-full py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Validasi
                </button>
                <button id="btn-reject" onclick="openActionModal(false)"
                    class="w-full py-2 text-sm border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50">
                    Tolak
                </button>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h2 class="text-sm font-medium text-gray-700 mb-3">Riwayat</h2>
            <div id="d-history" class="space-y-2 text-sm text-gray-500">—</div>
        </div>
    </div>
</div>

<div id="action-modal" class="hidden fixed inset-0 z-40 flex items-center justify-center" style="background:rgba(0,0,0,0.35)">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h2 id="modal-title" class="text-base font-semibold text-gray-900 mb-1">Validasi Pelanggaran</h2>
        <p id="modal-desc" class="text-sm text-gray-400 mb-5">Isi informasi sebelum mengkonfirmasi.</p>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1.5">
                    Nama pelanggar
                    <span id="person-required" class="text-red-400 ml-0.5">*</span>
                    <span id="person-optional" class="hidden text-gray-400">(opsional)</span>
                </label>
                <input type="text" id="m-person"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Nama lengkap pelanggar">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1.5">
                    Catatan
                    <span class="text-gray-400">(opsional)</span>
                </label>
                <textarea id="m-notes" rows="3"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                    placeholder="Catatan tambahan..."></textarea>
            </div>
        </div>

        <div class="flex gap-2 mt-5">
            <button id="modal-confirm" class="flex-1 py-2 text-sm rounded-lg font-medium">Konfirmasi</button>
            <button onclick="closeActionModal()" class="flex-1 py-2 text-sm border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50">Batal</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const violationId = {{ request()->route('violation') }};
let pendingIsValid = null;

const statusBadge = {
    pending:   'bg-yellow-50 text-yellow-700 border border-yellow-200',
    validated: 'bg-blue-50 text-blue-700 border border-blue-200',
    reported:  'bg-green-50 text-green-700 border border-green-200',
    rejected:  'bg-gray-100 text-gray-500 border border-gray-200',
};
const levelBadge = {
    major: 'bg-red-50 text-red-600 border border-red-100',
    minor: 'bg-amber-50 text-amber-600 border border-amber-100',
};

async function loadDetail() {
    try {
        const data = await api('GET', `/api/violations/${violationId}`);
        renderDetail(data.data ?? data);
    } catch(e) {
        document.getElementById('loading').textContent = 'Gagal memuat data.';
    }
}

function renderDetail(v) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('content').classList.remove('hidden');

    if (v.image_path) {
        document.getElementById('img-wrap').innerHTML =
            `<img src="/storage/${v.image_path}" class="w-full object-contain max-h-96">`;
    }

    document.getElementById('d-time').textContent       = v.detected_at ? new Date(v.detected_at).toLocaleString('id-ID') : '—';
    document.getElementById('d-camera').textContent     = v.camera?.name ?? '—';
    document.getElementById('d-zone').textContent       = v.zone?.name ?? '—';
    document.getElementById('d-shift').textContent      = v.shift?.name ?? 'Di luar shift';
    document.getElementById('d-type').textContent       = v.violation_type === 'apd' ? 'APD' : 'Disiplin';
    document.getElementById('d-confidence').textContent = v.confidence ? (v.confidence * 100).toFixed(1) + '%' : '—';

    document.getElementById('d-labels').innerHTML = v.apd_label
        ? `<span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-full">${v.apd_label}</span>`
        : '<span class="text-xs text-gray-400">—</span>';

    document.getElementById('d-status-badge').innerHTML =
        `<span class="px-3 py-1 rounded-full text-sm font-medium ${statusBadge[v.status] ?? ''}">${v.status}</span>`;

    if (v.level) {
        document.getElementById('level-wrap').classList.remove('hidden');
        document.getElementById('d-level-badge').innerHTML =
            `<span class="px-3 py-1 rounded-full text-sm font-medium ${levelBadge[v.level] ?? ''}">${v.level}</span>`;
    }

    // Tampilkan nama pelanggar kalau sudah terisi
    if (v.person_name) {
        document.getElementById('person-wrap').classList.remove('hidden');
        document.getElementById('d-person').textContent = v.person_name;
    }

    // Tampilkan catatan validasi kalau sudah terisi
    if (v.validation_notes) {
        document.getElementById('notes-wrap').classList.remove('hidden');
        document.getElementById('d-notes').textContent = v.validation_notes;
    }

    // Sembunyikan action buttons kalau bukan pending
    if (v.status !== 'pending') {
        document.getElementById('action-wrap').classList.add('hidden');
    }

    // Riwayat
    const history = [];
    if (v.created_at) {
        history.push(`
            <div class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-300 mt-1.5 shrink-0"></span>
                <span><strong>Terdeteksi</strong> · ${new Date(v.created_at).toLocaleString('id-ID')}</span>
            </div>`);
    }
    if (v.validated_at) {
        const label = v.status === 'rejected' ? 'Ditolak' : 'Divalidasi';
        const byText = v.validated_by_user?.name ? ` oleh ${v.validated_by_user.name}` : '';
        history.push(`
            <div class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 mt-1.5 shrink-0"></span>
                <span><strong>${label}</strong>${byText} · ${new Date(v.validated_at).toLocaleString('id-ID')}</span>
            </div>`);
    }
    document.getElementById('d-history').innerHTML =
        history.join('') || '<span class="text-xs text-gray-400">Belum ada riwayat.</span>';
}

function openActionModal(isValid) {
    pendingIsValid = isValid;

    document.getElementById('modal-title').textContent =
        isValid ? 'Validasi Pelanggaran' : 'Tolak Pelanggaran';
    document.getElementById('modal-desc').textContent =
        isValid
            ? 'Isi nama pelanggar sebelum memvalidasi.'
            : 'Pelanggaran ini akan ditandai sebagai false positive.';

    // Untuk validasi: person_name wajib. Untuk tolak: opsional.
    document.getElementById('person-required').classList.toggle('hidden', !isValid);
    document.getElementById('person-optional').classList.toggle('hidden', isValid);

    // Reset form
    document.getElementById('m-person').value = '';
    document.getElementById('m-notes').value  = '';

    // Style tombol konfirmasi sesuai aksi
    const confirmBtn = document.getElementById('modal-confirm');
    confirmBtn.onclick = submitAction;
    if (isValid) {
        confirmBtn.className = 'flex-1 py-2 text-sm rounded-lg font-medium bg-green-600 text-white hover:bg-green-700';
        confirmBtn.textContent = 'Validasi';
    } else {
        confirmBtn.className = 'flex-1 py-2 text-sm rounded-lg font-medium bg-red-50 text-red-600 border border-red-200 hover:bg-red-100';
        confirmBtn.textContent = 'Tolak';
    }

    document.getElementById('action-modal').classList.remove('hidden');
    setTimeout(() => document.getElementById('m-person').focus(), 50);
}

function closeActionModal() {
    document.getElementById('action-modal').classList.add('hidden');
    pendingIsValid = null;
}

async function submitAction() {
    const personName = document.getElementById('m-person').value.trim();
    const notes      = document.getElementById('m-notes').value.trim();

    // person_name wajib saat validasi
    if (pendingIsValid && !personName) {
        document.getElementById('m-person').focus();
        document.getElementById('m-person').classList.add('border-red-400');
        setTimeout(() => document.getElementById('m-person').classList.remove('border-red-400'), 1500);
        return;
    }

    const body = { is_valid: pendingIsValid };
    if (personName) body.person_name      = personName;
    if (notes)      body.validation_notes = notes;

    const confirmBtn = document.getElementById('modal-confirm');
    confirmBtn.disabled     = true;
    confirmBtn.textContent  = 'Menyimpan...';

    try {
        await api('PUT', `/api/violations/${violationId}/validate`, body);
        const wasValid = pendingIsValid;
        closeActionModal();
        toast(wasValid ? 'Berhasil divalidasi.' : 'Berhasil ditolak.'); 
        loadDetail();
    } catch(e) {
        toast(e.message ?? 'Gagal', 'error');
        confirmBtn.disabled = false;
        confirmBtn.textContent = pendingIsValid ? 'Validasi' : 'Tolak';
    }
}

document.getElementById('action-modal').addEventListener('click', function(e) {
    if (e.target === this) closeActionModal();
});

document.addEventListener('DOMContentLoaded', loadDetail);
</script>
@endpush