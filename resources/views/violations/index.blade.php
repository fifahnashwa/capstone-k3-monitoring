@extends('layouts.app')

@section('title', 'Pelanggaran')

@section('content')

<div class="flex items-center justify-between mb-5">

    <h1 class="text-lg font-semibold text-on-surface">Pelanggaran</h1>

</div>

{{-- FILTER BAR --}}

<div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4 mb-4 flex flex-wrap gap-3 items-end">

    <div>

        <label class="block text-xs text-outline mb-1">Status</label>

        <select id="f-status" class="text-sm border border-outline-variant rounded-lg px-3 py-1.5 bg-surface-container-lowest">

            <option value="">Semua</option>

            <option value="pending">Pending</option>

            <option value="validated">Validated</option>

            <option value="reported">Reported</option>

            <option value="rejected">Rejected</option>

        </select>

    </div>

    <div>

        <label class="block text-xs text-outline mb-1">Tipe</label>

        <select id="f-type" class="text-sm border border-outline-variant rounded-lg px-3 py-1.5 bg-surface-container-lowest">

            <option value="">Semua</option>

            <option value="apd">APD</option>

            <option value="discipline">Disiplin</option>

        </select>

    </div>

    <div>

        <label class="block text-xs text-outline mb-1">Level</label>

        <select id="f-level" class="text-sm border border-outline-variant rounded-lg px-3 py-1.5 bg-surface-container-lowest">

            <option value="">Semua</option>

            <option value="major">Major</option>

            <option value="minor">Minor</option>

        </select>

    </div>

    <div>

        <label class="block text-xs text-outline mb-1">Dari</label>

        <input type="date" id="f-from" class="text-sm border border-outline-variant rounded-lg px-3 py-1.5">

    </div>

    <div>

        <label class="block text-xs text-outline mb-1">Sampai</label>

        <input type="date" id="f-to" class="text-sm border border-outline-variant rounded-lg px-3 py-1.5">

    </div>

    <button onclick="loadViolations(1)" class="px-4 py-1.5 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">Cari</button>

    <button onclick="resetFilter()" class="px-4 py-1.5 text-sm border border-outline-variant rounded-lg text-on-surface-variant hover:bg-surface-container-low">Reset</button>

</div>

{{-- TABLE --}}

<div class="bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden">

    <div id="table-loading" class="py-12 text-center text-sm text-outline">Memuat data...</div>

    <table id="violations-table" class="hidden w-full text-sm">

        <thead class="bg-surface-container-low border-b border-outline-variant">

            <tr class="text-left text-xs text-outline uppercase tracking-wide">

                <th class="px-4 py-3 font-medium">Waktu</th>

                <th class="px-4 py-3 font-medium">Zona / Kamera</th>

                <th class="px-4 py-3 font-medium">Tipe</th>

                <th class="px-4 py-3 font-medium">Level</th>

                <th class="px-4 py-3 font-medium">Label</th>

                <th class="px-4 py-3 font-medium">Status</th>

                <th class="px-4 py-3 font-medium"></th>

            </tr>

        </thead>

        <tbody id="violations-body" class="divide-y divide-outline-variant/30"></tbody>

    </table>

    <div id="empty-state" class="hidden py-12 text-center text-sm text-outline">Tidak ada data.</div>

</div>

{{-- PAGINATION --}}

<div id="pagination" class="flex justify-center gap-2 mt-4"></div>

@endsection

@push('scripts')

<script>

let currentPage = 1;

const statusBadge = {

    pending:   'bg-[#ffefc8] text-[#7c5800]',

    validated: 'bg-primary-fixed text-on-primary-fixed-variant',

    reported:  'bg-[#ccf0da] text-[#15803d]',

    rejected:  'bg-surface-container text-on-surface-variant',

};

const levelBadge = {

    major: 'bg-error-container text-error',

    minor: 'bg-[#ffefc8] text-[#a16207]',

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

        <tr class="hover:bg-surface-container-low">

            <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap">${formatDate(v.detected_at)}</td>

            <td class="px-4 py-3">

                <span class="font-medium text-on-surface">${v.zone?.name ?? '—'}</span>

                <span class="block text-xs text-outline">${v.camera?.name ?? '—'}</span>

            </td>

            <td class="px-4 py-3 text-on-surface-variant">${v.violation_type === 'apd' ? 'APD' : 'Disiplin'}</td>

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

                <a href="/violations/${v.id}" class="text-xs text-primary hover:underline">Detail</a>

            </td>

        </tr>

    `).join('');

}

function labelCell(v) {

    const labels = v.apd_labels?.length ? v.apd_labels : (v.apd_label ? [v.apd_label] : []);

    if (labels.length) {

        return labels.map(l => `<span class="inline-block px-2 py-0.5 rounded-full text-xs bg-surface-container text-on-surface-variant">${l}</span>`).join(' ');

    }

    if (v.violation_type === 'discipline') {

        return '<span class="text-xs text-outline">—</span>';

    }

    // APD violation tapi tidak ada label = terdeteksi di luar jam shift

    return '<span class="text-xs text-outline">Di luar shift</span>';

}

function renderPagination(meta) {

    const total   = meta.last_page ?? 1;

    const current = meta.current_page ?? 1;

    if (total <= 1) { document.getElementById('pagination').innerHTML = ''; return; }

    let html = '';

    for (let i = 1; i <= total; i++) {

        html += `<button onclick="loadViolations(${i})"

            class="px-3 py-1 text-xs rounded-lg border ${i === current ? 'bg-primary text-white border-primary' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-low'}">${i}</button>`;

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