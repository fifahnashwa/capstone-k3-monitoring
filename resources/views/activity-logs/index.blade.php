@extends('layouts.app')
@section('title', 'Activity Log')
@section('content')

<div class="flex items-center justify-between mb-5">
    <h1 class="text-lg font-semibold text-gray-900">Activity Log</h1>
</div>

<div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div id="loading" class="py-12 text-center text-sm text-gray-400">Memuat...</div>
    <table id="log-table" class="hidden w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr class="text-left text-xs text-gray-400 uppercase tracking-wide">
                <th class="px-4 py-3 font-medium">Waktu</th>
                <th class="px-4 py-3 font-medium">Pengguna</th>
                <th class="px-4 py-3 font-medium">Aksi</th>
                <th class="px-4 py-3 font-medium">Deskripsi</th>
            </tr>
        </thead>
        <tbody id="log-body" class="divide-y divide-gray-50"></tbody>
    </table>
    <div id="empty" class="hidden py-12 text-center text-sm text-gray-400">Belum ada log.</div>
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
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">${new Date(l.created_at).toLocaleString('id-ID')}</td>
            <td class="px-4 py-3 text-gray-600">${l.user?.name ?? '—'}</td>
            <td class="px-4 py-3"><span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-mono">${l.action ?? '—'}</span></td>
            <td class="px-4 py-3 text-gray-500">${l.description ?? '—'}</td>
        </tr>
    `).join('');
}

function renderPagination(meta) {
    const total = meta.last_page ?? 1;
    if (total <= 1) return;
    let html = '';
    for (let i = 1; i <= total; i++) {
        html += `<button onclick="loadLogs(${i})" class="px-3 py-1 text-xs rounded-lg border ${i === (meta.current_page ?? 1) ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-200 text-gray-500 hover:bg-gray-50'}">${i}</button>`;
    }
    document.getElementById('pagination').innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => loadLogs(1));
</script>
@endpush