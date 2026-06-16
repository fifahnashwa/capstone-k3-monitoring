@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')

<div class="flex items-center justify-between mb-5">

    <h1 class="text-lg font-semibold text-on-surface">Activity Log</h1>

</div>

<div class="bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden">

    <div id="loading" class="py-12 text-center text-sm text-outline">Memuat...</div>

    <table id="log-table" class="hidden w-full text-sm">

        <thead class="bg-surface-container-low border-b border-outline-variant">

            <tr class="text-left text-xs text-outline uppercase tracking-wide">

                <th class="px-4 py-3 font-medium">Waktu</th>

                <th class="px-4 py-3 font-medium">Pengguna</th>

                <th class="px-4 py-3 font-medium">Aksi</th>

                <th class="px-4 py-3 font-medium">Deskripsi</th>

            </tr>

        </thead>

        <tbody id="log-body" class="divide-y divide-outline-variant/30"></tbody>

    </table>

    <div id="empty" class="hidden py-12 text-center text-sm text-outline">Belum ada log.</div>

</div>

<div id="pagination" class="flex justify-center gap-2 mt-4"></div>

@endsection

@push('scripts')

<script>

async function loadLogs(page = 1) {

    try {

        const data = await api('GET', `/api/activity-logs?page=${page}`);

        renderLogs(data.data ?? data);

        renderPagination(data.meta ?? {});

    } catch(e) { toast('Gagal memuat', 'error'); }

}

function renderLogs(logs) {

    document.getElementById('loading').classList.add('hidden');

    if (!logs.length) { document.getElementById('empty').classList.remove('hidden'); return; }

    document.getElementById('log-table').classList.remove('hidden');

    document.getElementById('log-body').innerHTML = logs.map(l => `

        <tr class="hover:bg-surface-container-low">

            <td class="px-4 py-3 text-outline text-xs whitespace-nowrap">${new Date(l.created_at).toLocaleString('id-ID')}</td>

            <td class="px-4 py-3 text-on-surface-variant">${l.user?.name ?? '—'}</td>

            <td class="px-4 py-3"><span class="px-2 py-0.5 bg-surface-container text-on-surface-variant rounded text-xs font-mono">${l.action ?? '—'}</span></td>

            <td class="px-4 py-3 text-on-surface-variant">${l.description ?? '—'}</td>

        </tr>

    `).join('');

}

function renderPagination(meta) {

    const total = meta.last_page ?? 1;

    if (total <= 1) return;

    let html = '';

    for (let i = 1; i <= total; i++) {

        html += `<button onclick="loadLogs(${i})" class="px-3 py-1 text-xs rounded-lg border ${i === (meta.current_page ?? 1) ? 'bg-primary text-white border-primary' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-low'}">${i}</button>`;

    }

    document.getElementById('pagination').innerHTML = html;

}

document.addEventListener('DOMContentLoaded', () => loadLogs(1));

</script>

@endpush