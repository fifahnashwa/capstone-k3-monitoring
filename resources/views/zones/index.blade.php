@extends('layouts.app')

@section('title', 'Zona & APD')

@section('content')

<div class="flex items-center justify-between mb-5">

    <h1 class="text-lg font-semibold text-on-surface">Zona & Aturan APD</h1>

    <button onclick="openZoneModal()" class="px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">+ Tambah zona</button>

</div>

<div id="zones-list" class="space-y-4"></div>

<div id="loading" class="py-12 text-center text-sm text-outline">Memuat...</div>

{{-- ZONE MODAL --}}

<div id="zone-modal" class="hidden fixed inset-0 z-40 flex items-center justify-center" style="background:rgba(0,0,0,0.35)">

    <div class="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-md shadow-xl">

        <h2 id="zone-modal-title" class="text-base font-semibold text-on-surface mb-4">Tambah zona</h2>

        <div class="space-y-3">

            <div>

                <label class="block text-xs text-outline mb-1">Nama zona</label>

                <input type="text" id="z-name" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2">

            </div>

            <div>

                <label class="block text-xs text-outline mb-1">Deskripsi</label>

                <textarea id="z-desc" rows="2" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2"></textarea>

            </div>

        </div>

        <div class="flex gap-2 mt-5">

            <button onclick="saveZone()" class="flex-1 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">Simpan</button>

            <button onclick="closeZoneModal()" class="flex-1 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">Batal</button>

        </div>

    </div>

</div>

{{-- RULE MODAL --}}

<div id="rule-modal" class="hidden fixed inset-0 z-40 flex items-center justify-center" style="background:rgba(0,0,0,0.35)">

    <div class="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-md shadow-xl">

        <h2 class="text-base font-semibold text-on-surface mb-4">Tambah aturan APD</h2>

        <input type="hidden" id="r-zone-id">

        <div class="space-y-3">

            <div>

                <label class="block text-xs text-outline mb-1">Label APD</label>

                <select id="r-label" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest">

                    <option value="no_helmet">no_helmet</option>

                    <option value="no_vest">no_vest</option>

                    <option value="no_boots">no_boots</option>

                </select>

            </div>

        </div>

        <div class="flex gap-2 mt-5">

            <button onclick="saveRule()" class="flex-1 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">Tambah</button>

            <button onclick="closeRuleModal()" class="flex-1 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">Batal</button>

        </div>

    </div>

</div>

@endsection

@push('scripts')

<script>

let editingZoneId = null;

async function loadZones() {

    try {

        const data = await api('GET', '/api/zones');

        renderZones(data.data ?? data);

    } catch(e) { toast('Gagal memuat data', 'error'); }

}

function renderZones(zones) {

    document.getElementById('loading').classList.add('hidden');

    if (!zones.length) {

        document.getElementById('zones-list').innerHTML = '<p class="text-sm text-outline text-center py-10">Belum ada zona.</p>';

        return;

    }

    document.getElementById('zones-list').innerHTML = zones.map(z => `

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-5">

            <div class="flex items-start justify-between mb-3">

                <div>

                    <h2 class="text-base font-medium text-on-surface">${z.name}</h2>

                    <p class="text-xs text-outline mt-0.5">${z.description ?? '—'}</p>

                </div>

                <div class="flex gap-2">

                    <button onclick='openZoneModal(${JSON.stringify(z)})' class="text-xs text-primary hover:underline">Edit</button>

                    <button onclick="deleteZone(${z.id}, '${z.name}')" class="text-xs text-error hover:underline">Hapus</button>

                </div>

            </div>

            <div class="flex flex-wrap gap-2 mb-3">

                ${(z.rules ?? []).map(r => `

                    <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs border

                        ${r.level === 'major' ? 'border-error/30 bg-error-container text-red-700' : 'border-amber-200 bg-[#ffefc8] text-amber-700'}">

                        ${r.apd_label}

                        <span class="opacity-60">${r.level}</span>

                        <button onclick="deleteRule(${z.id}, ${r.id})" class="opacity-50 hover:opacity-100 ml-0.5">×</button>

                    </span>

                `).join('') || '<span class="text-xs text-outline">Belum ada aturan APD.</span>'}

            </div>

            <button onclick="openRuleModal(${z.id})" class="text-xs text-primary hover:underline">+ Tambah aturan APD</button>

        </div>

    `).join('');

}

function openZoneModal(zone = null) {

    editingZoneId = zone?.id ?? null;

    document.getElementById('zone-modal-title').textContent = zone ? 'Edit zona' : 'Tambah zona';

    document.getElementById('z-name').value = zone?.name ?? '';

    document.getElementById('z-desc').value = zone?.description ?? '';

    document.getElementById('zone-modal').classList.remove('hidden');

}

function closeZoneModal() { document.getElementById('zone-modal').classList.add('hidden'); }

async function saveZone() {

    const body = { name: document.getElementById('z-name').value.trim(), description: document.getElementById('z-desc').value.trim() };

    if (!body.name) { toast('Nama zona wajib diisi', 'error'); return; }

    try {

        if (editingZoneId) { await api('PUT', `/api/zones/${editingZoneId}`, body); toast('Zona diperbarui.'); }

        else               { await api('POST', '/api/zones', body);               toast('Zona ditambahkan.'); }

        closeZoneModal(); loadZones();

    } catch(e) { toast(e.message ?? 'Gagal', 'error'); }

}

async function deleteZone(id, name) {

    if (!confirm(`Hapus zona "${name}"?`)) return;

    try { await api('DELETE', `/api/zones/${id}`); toast('Zona dihapus.'); loadZones(); }

    catch(e) { toast(e.message ?? 'Gagal', 'error'); }

}

function openRuleModal(zoneId) {

    document.getElementById('r-zone-id').value = zoneId;

    document.getElementById('rule-modal').classList.remove('hidden');

}

function closeRuleModal() { document.getElementById('rule-modal').classList.add('hidden'); }

async function saveRule() {

    const zoneId = document.getElementById('r-zone-id').value;

    const body   = { apd_label: document.getElementById('r-label').value };

    try { await api('POST', `/api/zones/${zoneId}/rules`, body); toast('Aturan APD ditambahkan.'); closeRuleModal(); loadZones(); }

    catch(e) { toast(e.message ?? 'Gagal', 'error'); }

}

async function deleteRule(zoneId, ruleId) {

    if (!confirm('Hapus aturan APD ini?')) return;

    try { await api('DELETE', `/api/zones/${zoneId}/rules/${ruleId}`); toast('Aturan dihapus.'); loadZones(); }

    catch(e) { toast(e.message ?? 'Gagal', 'error'); }

}

document.addEventListener('DOMContentLoaded', loadZones);

</script>

@endpush