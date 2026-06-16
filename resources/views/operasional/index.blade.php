@extends('layouts.app')

@section('title', 'Operasional')

@section('content')

{{-- Sticky Header: Page Title + Nav --}}
<div id="page-sticky-header">

    {{-- HEADER --}}
    <div class="flex items-center justify-between mb-5">

        <div>
            <h1 class="text-lg font-semibold text-on-surface">
                Operasional
            </h1>

            <p class="text-sm text-outline mt-0.5">
                Kelola zona pemantauan, aturan APD, dan jadwal shift kerja.
            </p>
        </div>

    </div>

    {{-- NAV --}}
    <div class="flex items-center border-b border-outline-variant">

        <button onclick="jumpTo('section-zona')" id="nav-zona"
            class="section-nav-btn flex items-center gap-2 px-1 pb-3 mr-6 text-sm font-medium border-b-2 -mb-px transition-all
                   border-primary text-primary">
            Zona & Aturan APD
        </button>

        <button onclick="jumpTo('section-shift')" id="nav-shift"
            class="section-nav-btn flex items-center gap-2 px-1 pb-3 text-sm font-medium border-b-2 -mb-px transition-all
                   border-transparent text-on-surface-variant hover:text-on-surface">
            Shift Kerja
        </button>

    </div>

</div>

{{-- ══ ZONA & APD ══════════════════════════════════════════════════════════════ --}}
<section id="section-zona" class="mb-10 scroll-mt-6">

    <div class="flex items-center justify-between mb-5 mt-5">
        <div class="flex items-center gap-2.5">
            <svg class="w-5 h-5 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <h2 class="font-brand font-semibold text-on-surface" style="font-size:20px;">Zona & Aturan APD</h2>
        </div>
        <button onclick="openZoneModal()"
            class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah zona
        </button>
    </div>

    <div id="zones-loading" class="py-10 text-center text-sm text-outline">Memuat...</div>
    <div id="zones-list" class="space-y-3"></div>

</section>

{{-- ══ SHIFT KERJA ══════════════════════════════════════════════════════════════ --}}
<section id="section-shift" class="scroll-mt-6">

    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-2.5">
            <svg class="w-5 h-5 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h2 class="font-brand font-semibold text-on-surface" style="font-size:20px;">Shift Kerja</h2>
        </div>
        <button onclick="openShiftModal()"
            class="flex items-center gap-1.5 px-4 py-2 text-sm font-medium bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah shift
        </button>
    </div>

    <div id="shifts-loading" class="py-10 text-center text-sm text-outline">Memuat...</div>
    <div id="shifts-grid" class="space-y-3"></div>

</section>


{{-- ══ MODAL: ZONA ══════════════════════════════════════════════════════════ --}}
<div id="zone-modal" class="hidden fixed inset-0 z-40 flex items-center justify-center"
     style="background:rgba(0,0,0,0.35)">
    <div class="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h2 id="zone-modal-title" class="text-base font-semibold text-on-surface mb-4">Tambah zona</h2>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-outline mb-1">Nama zona</label>
                <input type="text" id="z-name"
                    class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs text-outline mb-1">Deskripsi</label>
                <textarea id="z-desc" rows="2"
                    class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2"></textarea>
            </div>
        </div>
        <div class="flex gap-2 mt-5">
            <button onclick="saveZone()"
                class="flex-1 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">Simpan</button>
            <button onclick="closeZoneModal()"
                class="flex-1 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">Batal</button>
        </div>
    </div>
</div>

{{-- ══ MODAL: ATURAN APD ════════════════════════════════════════════════════ --}}
<div id="rule-modal" class="hidden fixed inset-0 z-40 flex items-center justify-center"
     style="background:rgba(0,0,0,0.35)">
    <div class="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h2 class="text-base font-semibold text-on-surface mb-4">Tambah aturan APD</h2>
        <input type="hidden" id="r-zone-id">
        <div>
            <label class="block text-xs text-outline mb-1">Label APD</label>
            <select id="r-label"
                class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest">
                <option value="no_helmet">no_helmet</option>
                <option value="no_vest">no_vest</option>
                <option value="no_boots">no_boots</option>
            </select>
        </div>
        <div class="flex gap-2 mt-5">
            <button onclick="saveRule()"
                class="flex-1 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">Tambah</button>
            <button onclick="closeRuleModal()"
                class="flex-1 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">Batal</button>
        </div>
    </div>
</div>

{{-- ══ MODAL: SHIFT ══════════════════════════════════════════════════════════ --}}
<div id="shift-modal" class="hidden fixed inset-0 z-40 flex items-center justify-center"
     style="background:rgba(0,0,0,0.35)">
    <div class="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-sm shadow-xl">
        <h2 id="shift-modal-title" class="text-base font-semibold text-on-surface mb-4">Tambah shift</h2>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-outline mb-1">Nama shift</label>
                <input type="text" id="f-name"
                    class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2"
                    placeholder="Shift 1">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-outline mb-1">Jam mulai</label>
                    <input type="time" id="f-start"
                        class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs text-outline mb-1">Jam selesai</label>
                    <input type="time" id="f-end"
                        class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2">
                </div>
            </div>
        </div>
        <div class="flex gap-2 mt-5">
            <button onclick="saveShift()"
                class="flex-1 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90">Simpan</button>
            <button onclick="closeShiftModal()"
                class="flex-1 py-2 text-sm border border-outline-variant text-on-surface-variant rounded-lg hover:bg-surface-container-low">Batal</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>

// ══════════════════════════════════════════════════════
// ZONA & APD
// ══════════════════════════════════════════════════════

let editingZoneId = null;

async function loadZones() {
    try {
        const data = await api('GET', '/api/zones');
        renderZones(data.data ?? data);
    } catch(e) { toast('Gagal memuat zona', 'error'); }
}

function renderZones(zones) {
    document.getElementById('zones-loading').classList.add('hidden');
    if (!zones.length) {
        document.getElementById('zones-list').innerHTML =
            '<p class="text-sm text-outline text-center py-10">Belum ada zona.</p>';
        return;
    }
    document.getElementById('zones-list').innerHTML = zones.map(z => `
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="text-base font-medium text-on-surface">${z.name}</h3>
                    <p class="text-xs text-outline mt-0.5">${z.description ?? '—'}</p>
                </div>
                <div class="flex gap-3">
                    <button onclick='openZoneModal(${JSON.stringify(z)})'
                        class="flex items-center gap-1 text-xs text-primary hover:underline">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </button>
                    <button onclick="deleteZone(${z.id}, '${z.name}')"
                        class="flex items-center gap-1 text-xs text-error hover:underline">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Hapus
                    </button>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 mb-3">
                ${(z.rules ?? []).map(r => `
                    <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs border
                        ${r.level === 'major'
                            ? 'border-error/30 bg-error-container text-red-700'
                            : 'border-amber-200 bg-[#ffefc8] text-amber-700'}">
                        ${r.apd_label}
                        <span class="opacity-60">${r.level}</span>
                        <button onclick="deleteRule(${z.id}, ${r.id})"
                            class="opacity-50 hover:opacity-100 ml-0.5">×</button>
                    </span>
                `).join('') || '<span class="text-xs text-outline">Belum ada aturan APD.</span>'}
            </div>
            <button onclick="openRuleModal(${z.id})"
                class="flex items-center gap-1 text-xs text-primary hover:underline">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah aturan APD
            </button>
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
    const body = {
        name:        document.getElementById('z-name').value.trim(),
        description: document.getElementById('z-desc').value.trim(),
    };
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
    try {
        await api('POST', `/api/zones/${zoneId}/rules`, body);
        toast('Aturan APD ditambahkan.'); closeRuleModal(); loadZones();
    } catch(e) { toast(e.message ?? 'Gagal', 'error'); }
}

async function deleteRule(zoneId, ruleId) {
    if (!confirm('Hapus aturan APD ini?')) return;
    try { await api('DELETE', `/api/zones/${zoneId}/rules/${ruleId}`); toast('Aturan dihapus.'); loadZones(); }
    catch(e) { toast(e.message ?? 'Gagal', 'error'); }
}

// ══════════════════════════════════════════════════════
// SHIFT KERJA
// ══════════════════════════════════════════════════════

let editingShiftId = null;

async function loadShifts() {
    try {
        const data = await api('GET', '/api/shifts');
        renderShifts(data.data ?? data);
    } catch(e) { toast('Gagal memuat shift', 'error'); }
}

function renderShifts(shifts) {
    document.getElementById('shifts-loading').classList.add('hidden');
    if (!shifts.length) {
        document.getElementById('shifts-grid').innerHTML =
            '<p class="text-sm text-outline text-center py-10">Belum ada shift.</p>';
        return;
    }
    document.getElementById('shifts-grid').innerHTML = shifts.map(s => `
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-5">
            <div class="flex items-start justify-between mb-3">
                <h3 class="text-sm font-semibold text-on-surface">${s.name}</h3>
                <div class="flex gap-3">
                    <button onclick='openShiftModal(${JSON.stringify(s)})'
                        class="text-xs text-primary hover:underline">Edit</button>
                    <button onclick="deleteShift(${s.id}, '${s.name}')"
                        class="text-xs text-error hover:underline">Hapus</button>
                </div>
            </div>
            <p class="font-mono text-2xl font-semibold text-primary">
                ${s.start_time}
                <span class="text-outline/60 font-normal mx-1">–</span>
                ${s.end_time}
            </p>
        </div>
    `).join('');
}

function openShiftModal(shift = null) {
    editingShiftId = shift?.id ?? null;
    document.getElementById('shift-modal-title').textContent = shift ? 'Edit shift' : 'Tambah shift';
    document.getElementById('f-name').value  = shift?.name ?? '';
    document.getElementById('f-start').value = shift?.start_time?.substring(0, 5) ?? '';
    document.getElementById('f-end').value   = shift?.end_time?.substring(0, 5) ?? '';
    document.getElementById('shift-modal').classList.remove('hidden');
}
function closeShiftModal() { document.getElementById('shift-modal').classList.add('hidden'); }

async function saveShift() {
    const start = document.getElementById('f-start').value;
    const end   = document.getElementById('f-end').value;
    const body  = {
        name:       document.getElementById('f-name').value.trim(),
        start_time: start ? start + ':00' : '',
        end_time:   end   ? end   + ':00' : '',
    };
    if (!body.name || !body.start_time || !body.end_time) {
        toast('Semua field wajib diisi', 'error'); return;
    }
    try {
        if (editingShiftId) { await api('PUT', `/api/shifts/${editingShiftId}`, body); toast('Shift diperbarui.'); }
        else                { await api('POST', '/api/shifts', body);                  toast('Shift ditambahkan.'); }
        closeShiftModal(); loadShifts();
    } catch(e) { toast(e.message ?? 'Gagal', 'error'); }
}

async function deleteShift(id, name) {
    if (!confirm(`Hapus shift "${name}"?`)) return;
    try { await api('DELETE', `/api/shifts/${id}`); toast('Shift dihapus.'); loadShifts(); }
    catch(e) { toast(e.message ?? 'Gagal', 'error'); }
}

// ══════════════════════════════════════════════════════
// SECTION NAV
// ══════════════════════════════════════════════════════

let isManualScrolling = false;

function jumpTo(id) {
    const el   = document.getElementById(id);
    const main = document.querySelector('main');
    if (!el || !main) return;

    isManualScrolling = true;
    setActiveNav(id);

    const offset = el.getBoundingClientRect().top - main.getBoundingClientRect().top + main.scrollTop;
    main.scrollTo({ top: offset, behavior: 'smooth' });

    setTimeout(() => { isManualScrolling = false; }, 600);
}

function setActiveNav(id) {
    document.querySelectorAll('.section-nav-btn').forEach(btn => {
        const isActive = btn.id === 'nav-' + id.replace('section-', '');
        btn.classList.toggle('border-primary', isActive);
        btn.classList.toggle('text-primary',   isActive);
        btn.classList.toggle('border-transparent',      !isActive);
        btn.classList.toggle('text-on-surface-variant', !isActive);
    });
}

// ══════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    loadZones();
    loadShifts();

    const main     = document.querySelector('main');
    const header   = document.getElementById('page-sticky-header');
    const sections = ['section-zona', 'section-shift'];

    // ── JS-based sticky
    if (header && main) {
        const headerH   = header.offsetHeight;
        const mainLeft  = main.getBoundingClientRect().left;

        // spacer untuk mencegah konten naik turun saat header jadi fixed
        const spacer = document.createElement('div');
        spacer.id = 'sticky-spacer';
        spacer.style.height = headerH + 'px';
        header.after(spacer);

        // Set fixed style
Object.assign(header.style, {
    position:      'fixed',
    top:           '0',
    left:          mainLeft + 'px',
    right:         '0',
    zIndex:        '50',
    background:    '#faf8ff',
    paddingTop:    '1.25rem',
    paddingLeft:   '1.25rem',
    paddingRight:  '1.25rem',
});

        // Update left kalau window di-resize
        window.addEventListener('resize', () => {
            header.style.left = main.getBoundingClientRect().left + 'px';
            header.style.height = 'auto';
            spacer.style.height = header.offsetHeight + 'px';
        });
    }

    // ── Tab highlight saat scroll ──
    function updateNav() {
        if (isManualScrolling) return;
        const mainRect = main.getBoundingClientRect();
        let active = sections[0];
        for (const id of sections) {
            const el = document.getElementById(id);
            if (!el) continue;
            const top = el.getBoundingClientRect().top - mainRect.top;
            if (top <= 80) active = id;
        }
        setActiveNav(active);
    }

    if (main) {
        main.addEventListener('scroll', updateNav, { passive: true });
    }
});

</script>
@endpush