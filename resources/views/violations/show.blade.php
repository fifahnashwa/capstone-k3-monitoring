@extends('layouts.app')

@section('title', 'Detail Pelanggaran')

@section('content')

<div class="flex items-center gap-3 mb-5">

    <a href="{{ route('violations.index') }}" class="text-sm text-outline hover:text-on-surface-variant">← Kembali</a>

    <h1 class="text-lg font-semibold text-on-surface">Detail Pelanggaran</h1>

</div>

<div id="loading" class="py-20 text-center text-sm text-outline">Memuat...</div>

<div id="content" class="hidden grid grid-cols-3 gap-4">

    {{-- LEFT: image + info --}}

    <div class="col-span-2 space-y-4">

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden">

            <div id="img-wrap" class="bg-surface-container flex items-center justify-center min-h-48">

                <span class="text-xs text-outline">Tidak ada gambar</span>

            </div>

        </div>

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-5 space-y-4">

            <h2 class="text-sm font-medium text-on-surface">Informasi Deteksi</h2>

            <div class="grid grid-cols-2 gap-4 text-sm">

                <div><p class="text-xs text-outline mb-0.5">Waktu deteksi</p><p id="d-time" class="font-medium">—</p></div>

                <div><p class="text-xs text-outline mb-0.5">Kamera</p><p id="d-camera" class="font-medium">—</p></div>

                <div><p class="text-xs text-outline mb-0.5">Zona</p><p id="d-zone" class="font-medium">—</p></div>

                <div><p class="text-xs text-outline mb-0.5">Shift</p><p id="d-shift" class="font-medium">—</p></div>

                <div><p class="text-xs text-outline mb-0.5">Tipe pelanggaran</p><p id="d-type" class="font-medium">—</p></div>

                <div><p class="text-xs text-outline mb-0.5">Confidence</p><p id="d-confidence" class="font-medium">—</p></div>

            </div>

            <div>

                <p class="text-xs text-outline mb-1.5">Label APD</p>

                <div id="d-labels" class="flex flex-wrap gap-2"></div>

            </div>

        </div>

    </div>

    {{-- RIGHT: status + action --}}

    <div class="space-y-4">

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-5">

            <h2 class="text-sm font-medium text-on-surface mb-3">Status</h2>

            <div id="d-status-badge" class="mb-4"></div>

            <div id="level-wrap" class="mb-4 hidden">

                <p class="text-xs text-outline mb-1">Level</p>

                <div id="d-level-badge"></div>

            </div>

            {{-- Tampilkan nama pelanggar kalau sudah diisi --}}

            <div id="person-wrap" class="hidden mb-4 p-3 bg-surface-container-low rounded-lg">

                <p class="text-xs text-outline mb-0.5">Nama pelanggar</p>

                <p id="d-person" class="text-sm font-medium text-on-surface">—</p>

            </div>

            {{-- Tampilkan catatan kalau sudah diisi --}}

            <div id="notes-wrap" class="hidden mb-4 p-3 bg-surface-container-low rounded-lg">

                <p class="text-xs text-outline mb-0.5">Catatan validasi</p>

                <p id="d-notes" class="text-sm text-on-surface-variant">—</p>

            </div>

            <div id="action-wrap" class="space-y-2">

                <button id="btn-validate" onclick="openActionModal(true)"

                    class="w-full py-2 text-sm bg-[#15803d] text-white rounded-lg hover:bg-green-700">

                    Validasi

                </button>

                <button id="btn-reject" onclick="openActionModal(false)"

                    class="w-full py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">

                    Tolak

                </button>

            </div>

            <button id="btn-delete" onclick="confirmDelete()"

                class="w-full mt-2 py-2 text-sm border border-error/30 text-error rounded-lg hover:bg-error-container">

                Hapus

            </button>

        </div>

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-5">

            <h2 class="text-sm font-medium text-on-surface mb-3">Riwayat</h2>

            <div id="d-history" class="space-y-2 text-sm text-on-surface-variant">—</div>

        </div>

    </div>

</div>

<div id="action-modal" class="hidden fixed inset-0 z-40 flex items-center justify-center" style="background:rgba(0,0,0,0.35)">

    <div class="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-md shadow-xl">

        <h2 id="modal-title" class="text-base font-semibold text-on-surface mb-1">Validasi Pelanggaran</h2>

        <p id="modal-desc" class="text-sm text-outline mb-5">Isi informasi sebelum mengkonfirmasi.</p>

        <div class="space-y-4">

            <div>

                <label class="block text-xs font-medium text-on-surface-variant mb-1.5">

                    Nama pelanggar

                    <span id="person-required" class="text-red-400 ml-0.5">*</span>

                    <span id="person-optional" class="hidden text-outline">(opsional)</span>

                </label>

                <input type="text" id="m-person"

                    class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary"

                    placeholder="Nama lengkap pelanggar">

            </div>

            <div>

                <label class="block text-xs font-medium text-on-surface-variant mb-1.5">

                    Catatan

                    <span class="text-outline">(opsional)</span>

                </label>

                <textarea id="m-notes" rows="3"

                    class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary resize-none"

                    placeholder="Catatan tambahan..."></textarea>

            </div>

        </div>

        <div class="flex gap-2 mt-5">

            <button id="modal-confirm" class="flex-1 py-2 text-sm rounded-lg font-medium">Konfirmasi</button>

            <button onclick="closeActionModal()" class="flex-1 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">Batal</button>

        </div>

    </div>

</div>

@endsection

@push('scripts')

<script>

const violationId = {{ request()->route('violation') }};

let pendingIsValid = null;

const statusBadge = {

    pending:   'bg-[#ffefc8] text-[#7c5800] border border-[#7c5800]/20',

    validated: 'bg-primary-fixed text-on-primary-fixed-variant border border-blue-200',

    reported:  'bg-[#ccf0da] text-[#15803d] border border-[#15803d]/20',

    rejected:  'bg-surface-container text-on-surface-variant border border-outline-variant',

};

const levelBadge = {

    major: 'bg-error-container text-error border border-red-100',

    minor: 'bg-[#ffefc8] text-[#a16207] border border-amber-100',

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

    const apdLabels = v.apd_labels?.length ? v.apd_labels : (v.apd_label ? [v.apd_label] : []);

    document.getElementById('d-labels').innerHTML = apdLabels.length

        ? apdLabels.map(l => `<span class="px-2 py-0.5 bg-surface-container text-on-surface text-xs rounded-full">${l}</span>`).join('')

        : '<span class="text-xs text-outline">—</span>';

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

        history.join('') || '<span class="text-xs text-outline">Belum ada riwayat.</span>';

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

        confirmBtn.className = 'flex-1 py-2 text-sm rounded-lg font-medium bg-[#15803d] text-white hover:bg-green-700';

        confirmBtn.textContent = 'Validasi';

    } else {

        confirmBtn.className = 'flex-1 py-2 text-sm rounded-lg font-medium bg-error-container text-error border border-error/30 hover:bg-red-100';

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

async function confirmDelete() {

    if (!confirm('Hapus pelanggaran ini? Tindakan tidak dapat dibatalkan.')) return;

    const btn = document.getElementById('btn-delete');

    btn.disabled = true;

    btn.textContent = 'Menghapus...';

    try {

        await api('DELETE', `/api/violations/${violationId}`);

        toast('Pelanggaran berhasil dihapus.');

        setTimeout(() => { window.location.href = '/violations'; }, 1000);

    } catch(e) {

        toast(e.message ?? 'Gagal menghapus.', 'error');

        btn.disabled = false;

        btn.textContent = 'Hapus';

    }

}

document.getElementById('action-modal').addEventListener('click', function(e) {

    if (e.target === this) closeActionModal();

});

document.addEventListener('DOMContentLoaded', loadDetail);

</script>

@endpush