@extends('layouts.app')
@section('title', 'Pelanggaran')
@section('content')

<div class="flex items-center justify-between mb-5">
    <h1 class="text-lg font-semibold text-gray-900">Pelanggaran</h1>
</div>

{{-- FILTER BAR --}}
<div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs text-gray-400 mb-1">Status</label>
        <select id="f-status" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white">
            <option value="">Semua</option>
            <option value="pending">Pending</option>
            <option value="validated">Validated</option>
            <option value="reported">Reported</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-400 mb-1">Tipe</label>
        <select id="f-type" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white">
            <option value="">Semua</option>
            <option value="apd">APD</option>
            <option value="discipline">Disiplin</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-400 mb-1">Level</label>
        <select id="f-level" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white">
            <option value="">Semua</option>
            <option value="major">Major</option>
            <option value="minor">Minor</option>
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-400 mb-1">Dari</label>
        <input type="date" id="f-from" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
    </div>
    <div>
        <label class="block text-xs text-gray-400 mb-1">Sampai</label>
        <input type="date" id="f-to" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
    </div>
    <button onclick="loadViolations(1)" class="px-4 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Cari</button>
    <button onclick="resetFilter()" class="px-4 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">Reset</button>
</div>

{{-- TABLE --}}
<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div id="table-loading" class="py-12 text-center text-sm text-gray-400">Memuat data...</div>
    <table id="violations-table" class="hidden w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs text-gray-400 uppercase tracking-wide">
                <th class="px-4 py-3 font-medium">Waktu</th>
                <th class="px-4 py-3 font-medium">Zona / Kamera</th>
                <th class="px-4 py-3 font-medium">Tipe</th>
                <th class="px-4 py-3 font-medium">Level</th>
                <th class="px-4 py-3 font-medium">Label</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
        </thead>
        <tbody id="violations-body" class="divide-y divide-gray-50"></tbody>
    </table>
    <div id="empty-state" class="hidden py-12 text-center text-sm text-gray-400">Tidak ada data.</div>
</div>

{{-- PAGINATION --}}
<div id="pagination" class="flex justify-center gap-2 mt-4"></div>

@endsection

@push('scripts')
<script>
let currentPage = 1;
const statusBadge = {
    pending:   'bg-yellow-50 text-yellow-700',
    validated: 'bg-blue-50 text-blue-700',
    reported:  'bg-green-50 text-green-700',
    rejected:  'bg-gray-100 text-gray-500',
};
const levelBadge = {
    major: 'bg-red-50 text-red-600',
    minor: 'bg-amber-50 text-amber-600',
};

async function loadViolations(page = 1) {
    currentPage = page;
    document.getElementById('table-loading').classList.remove('hidden');
    document.getElementById('violations-table').classList.add('hidden');
    document.getElementById('empty-state').classList.add('hidden');

    const params = new URLSearchParams({ page });
    const status = document.getElementById('f-status').value;
    const type   = document.getElementById('f-type').value;
    const level  = document.getElementById('f-level').value;
    const from   = document.getElementById('f-from').value;
    const to     = document.getElementById('f-to').value;
    if (status) params.append('status', status);
    if (type)   params.append('violation_type', type);
    if (level)  params.append('level', level);
    if (from)   params.append('date_from', from);
    if (to)     params.append('date_to', to);

    try {
        const data = await api('GET', `/api/violations?${params}`);
        renderTable(data);
        renderPagination(data.meta ?? data);
    } catch (e) {
        toast('Gagal memuat data', 'error');
        document.getElementById('table-loading').classList.add('hidden');
    }
}

function renderTable(data) {
    const items = data.data ?? [];
    document.getElementById('table-loading').classList.add('hidden');
    if (!items.length) {
        document.getElementById('empty-state').classList.remove('hidden');
        return;
    }
    document.getElementById('violations-table').classList.remove('hidden');
    document.getElementById('violations-body').innerHTML = items.map(v => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">${formatDate(v.detected_at)}</td>
            <td class="px-4 py-3">
                <span class="font-medium text-gray-800">${v.zone?.name ?? '—'}</span>
                <span class="block text-xs text-gray-400">${v.camera?.name ?? '—'}</span>
            </td>
            <td class="px-4 py-3 text-gray-600">${v.violation_type === 'apd' ? 'APD' : 'Disiplin'}</td>
            <td class="px-4 py-3">
                ${v.level
                    ? `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${levelBadge[v.level] ?? ''}">${v.level}</span>`
                    : '—'}
            </td>
            <td class="px-4 py-3">
                ${labelCell(v)}
            </td>
            <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium ${statusBadge[v.status] ?? ''}">${v.status}</span>
            </td>
            <td class="px-4 py-3 text-right">
                <a href="/violations/${v.id}" class="text-xs text-blue-600 hover:underline">Detail →</a>
            </td>
        </tr>
    `).join('');
}

function labelCell(v) {
    if (v.apd_label) {
        return `<span class="inline-block px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">${v.apd_label}</span>`;
    }
    if (v.violation_type === 'discipline') {
        return '<span class="text-xs text-gray-400">—</span>';
    }
    // APD violation tapi tidak ada label = terdeteksi di luar jam shift
    return '<span class="text-xs text-gray-400">Di luar shift</span>';
}

function renderPagination(meta) {
    const total   = meta.last_page ?? 1;
    const current = meta.current_page ?? 1;
    if (total <= 1) { document.getElementById('pagination').innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= total; i++) {
        html += `<button onclick="loadViolations(${i})"
            class="px-3 py-1 text-xs rounded-lg border ${i === current ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-200 text-gray-500 hover:bg-gray-50'}">${i}</button>`;
    }
    document.getElementById('pagination').innerHTML = html;
}

function resetFilter() {
    ['f-status','f-type','f-level','f-from','f-to'].forEach(id => {
        document.getElementById(id).value = '';
    });
    loadViolations(1);
}

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

document.addEventListener('DOMContentLoaded', () => loadViolations(1));
</script>
@endpush