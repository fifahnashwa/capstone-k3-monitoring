@extends('layouts.app')

@section('title', 'Laporan')

@section('content')

    <div class="flex items-center justify-between mb-5">

        <h1 class="text-lg font-semibold text-on-surface">Generate Laporan</h1>

    </div>

    <div class="grid grid-cols-3 gap-5">

        {{-- FORM --}}

        <div class="col-span-1">

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-5 space-y-4">

                <h2 class="text-sm font-medium text-on-surface">Parameter Laporan</h2>

                <div>

                    <label class="block text-xs text-outline mb-1">Dari tanggal</label>

                    <input type="date" id="r-from" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2">

                </div>

                <div>

                    <label class="block text-xs text-outline mb-1">Sampai tanggal</label>

                    <input type="date" id="r-to" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2">

                </div>

                <div>

                    <label class="block text-xs text-outline mb-1">Zona (opsional)</label>

                    <select id="r-zone" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest">

                        <option value="">Semua zona</option>

                    </select>

                </div>

                <div>

                    <label class="block text-xs text-outline mb-1">Shift (opsional)</label>

                    <select id="r-shift" class="w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-lowest">

                        <option value="">Semua shift</option>

                    </select>

                </div>

                <button onclick="generateReport()"

                    class="w-full py-2.5 text-sm bg-primary text-white rounded-lg hover:bg-primary/90 font-medium">

                    Generate Laporan

                </button>

            </div>

        </div>

        {{-- RESULT --}}

        <div class="col-span-2">

            <div id="result-placeholder"

                class="bg-surface-container-lowest rounded-xl border border-outline-variant p-10 text-center text-sm text-outline h-full flex items-center justify-center">

                Isi parameter di kiri, lalu klik Generate Laporan.

            </div>

            <div id="result-loading"

                class="hidden bg-surface-container-lowest rounded-xl border border-outline-variant p-10 text-center text-sm text-outline">

                Membuat laporan...

            </div>

            <div id="result-content" class="hidden bg-surface-container-lowest rounded-xl border border-outline-variant p-5 space-y-5">

                <div class="flex items-center justify-between">

                    <div class="flex items-center justify-between">

                        <h2 class="text-sm font-medium text-on-surface">Hasil Laporan</h2>

                        <div class="flex items-center gap-3">

                            <span id="r-period" class="text-xs text-outline"></span>

                            <a id="btn-download-pdf" href="#" target="_blank"

                                class="hidden items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-error hover:bg-red-700 rounded-lg transition-colors">

                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"

                                    stroke="currentColor" stroke-width="2">

                                    <path stroke-linecap="round" stroke-linejoin="round"

                                        d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h4a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />

                                </svg>

                                Download PDF

                            </a>

                        </div>

                    </div>

                </div>

                <div class="grid grid-cols-3 gap-3">

                    <div class="bg-surface-container-low rounded-xl p-4">

                        <p class="text-xs text-outline mb-1">Total pelanggaran</p>

                        <p class="text-2xl font-semibold text-on-surface" id="r-total">—</p>

                    </div>

                    <div class="bg-error-container rounded-xl p-4">

                        <p class="text-xs text-red-400 mb-1">Major</p>

                        <p class="text-2xl font-semibold text-error" id="r-major">—</p>

                    </div>

                    <div class="bg-[#ffefc8] rounded-xl p-4">

                        <p class="text-xs text-amber-500 mb-1">Minor</p>

                        <p class="text-2xl font-semibold text-[#a16207]" id="r-minor">—</p>

                    </div>

                </div>

                <div>

                    <h3 class="text-xs font-medium text-on-surface-variant mb-2 uppercase tracking-wide">Per zona</h3>

                    <div id="r-zones" class="space-y-2"></div>

                </div>

                <div>

                    <h3 class="text-xs font-medium text-on-surface-variant mb-2 uppercase tracking-wide">Per shift</h3>

                    <div id="r-shifts" class="space-y-2"></div>

                </div>

                <div>

                    <h3 class="text-xs font-medium text-on-surface-variant mb-2 uppercase tracking-wide">Jenis APD dilanggar</h3>

                    <div id="r-labels" class="flex flex-wrap gap-2"></div>

                </div>

                <div>

                    <h3 class="text-xs font-medium text-on-surface-variant mb-2 uppercase tracking-wide">Detail Pelanggaran</h3>

                    <div class="overflow-x-auto rounded-lg border border-outline-variant">

                        <table class="w-full text-xs">

                            <thead class="bg-surface-container-low text-on-surface-variant uppercase tracking-wide">

                                <tr>

                                    <th class="px-3 py-2 text-left">Tanggal</th>

                                    <th class="px-3 py-2 text-left">Waktu</th>

                                    <th class="px-3 py-2 text-left">Zona</th>

                                    <th class="px-3 py-2 text-left">Shift</th>

                                    <th class="px-3 py-2 text-left">Label APD</th>

                                    <th class="px-3 py-2 text-left">Level</th>

                                    <th class="px-3 py-2 text-left">Pelanggar</th>

                                    <th class="px-3 py-2 text-left">Status</th>

                                </tr>

                            </thead>

                            <tbody id="r-detail" class="divide-y divide-outline-variant/30 text-on-surface"></tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

@endsection

@push('scripts')

    <script>

        function fmtDate(d) {

            const y = d.getFullYear();

            const m = String(d.getMonth() + 1).padStart(2, '0');

            const day = String(d.getDate()).padStart(2, '0');

            return `${y}-${m}-${day}`;

        }

        async function loadOptions() {

            try {

                const [zones, shifts] = await Promise.all([

                    api('GET', '/api/zones'),

                    api('GET', '/api/shifts'),

                ]);

                const zoneEl = document.getElementById('r-zone');

                (zones.data ?? zones).forEach(z => {

                    zoneEl.innerHTML += `<option value="${z.id}">${z.name}</option>`;

                });

                const shiftEl = document.getElementById('r-shift');

                (shifts.data ?? shifts).forEach(s => {

                    shiftEl.innerHTML += `<option value="${s.id}">${s.name}</option>`;

                });

            } catch (e) { }

        }

        async function generateReport() {

            const from = document.getElementById('r-from').value;

            const to = document.getElementById('r-to').value;

            const zone = document.getElementById('r-zone').value;

            const shift = document.getElementById('r-shift').value;

            if (!from || !to) { toast('Tanggal harus diisi', 'error'); return; }

            document.getElementById('result-placeholder').classList.add('hidden');

            document.getElementById('result-content').classList.add('hidden');

            document.getElementById('result-loading').classList.remove('hidden');

            const body = { date_from: from, date_to: to };

            if (zone) body.zone_id = parseInt(zone);

            if (shift) body.shift_id = parseInt(shift);

            try {

                const data = await api('POST', '/api/reports', body);

                renderResult(data);

            } catch (e) {

                toast(e.message ?? 'Gagal generate laporan', 'error');

                document.getElementById('result-loading').classList.add('hidden');

                document.getElementById('result-placeholder').classList.remove('hidden');

            }

        }

        function renderResult(resp) {

            document.getElementById('result-loading').classList.add('hidden');

            document.getElementById('result-content').classList.remove('hidden');

            const s      = resp.summary ?? resp;

            const period = resp.period  ?? {};

            const rows   = resp.violations ?? [];

            // Period & PDF button

            const pdfFrom = period.from ?? document.getElementById('r-from').value;

            const pdfTo   = period.to   ?? document.getElementById('r-to').value;

            document.getElementById('r-period').textContent = `${pdfFrom} – ${pdfTo}`;

            const pdfBtn = document.getElementById('btn-download-pdf');

            pdfBtn.href = `/reports/pdf?date_from=${pdfFrom}&date_to=${pdfTo}`;

            pdfBtn.classList.remove('hidden');

            pdfBtn.classList.add('flex');

            // Summary cards

            document.getElementById('r-total').textContent = s.total_violations ?? 0;

            document.getElementById('r-major').textContent = s.by_level?.major ?? 0;

            document.getElementById('r-minor').textContent = s.by_level?.minor ?? 0;

            // Per zona

            document.getElementById('r-zones').innerHTML = (s.by_zone ?? []).map(z => `

                <div class="flex items-center gap-3">

                    <span class="text-sm text-on-surface-variant w-32 shrink-0">${z.zone ?? '—'}</span>

                    <div class="flex-1 bg-surface-container rounded-full h-2 overflow-hidden">

                        <div class="bg-primary-fixed h-2 rounded-full"

                            style="width:${s.total_violations ? Math.round(z.total / s.total_violations * 100) : 0}%"></div>

                    </div>

                    <span class="text-sm font-medium text-on-surface w-8 text-right">${z.total}</span>

                </div>`).join('') || '<p class="text-sm text-outline">Tidak ada data.</p>';

            // Per shift

            document.getElementById('r-shifts').innerHTML = (s.by_shift ?? []).map(sh => `

                <div class="flex items-center gap-3">

                    <span class="text-sm text-on-surface-variant w-32 shrink-0">${sh.shift}</span>

                    <div class="flex-1 bg-surface-container rounded-full h-2 overflow-hidden">

                        <div class="bg-[#ccf0da] h-2 rounded-full"

                            style="width:${s.total_violations ? Math.round(sh.total / s.total_violations * 100) : 0}%"></div>

                    </div>

                    <span class="text-sm font-medium text-on-surface w-8 text-right">${sh.total}</span>

                </div>`).join('') || '<p class="text-sm text-outline">Tidak ada data.</p>';

            // Label APD

            const byLabel = s.by_label ?? {};

            document.getElementById('r-labels').innerHTML = Object.entries(byLabel)

                .map(([k, v]) => `<span class="px-3 py-1 bg-surface-container text-on-surface text-sm rounded-full">${k}: <strong>${v}</strong></span>`)

                .join('') || '<p class="text-sm text-outline">Tidak ada data.</p>';

            // Detail tabel

            const statusBadge = {

                pending:   'bg-[#ffefc8] text-[#7c5800]',

                validated: 'bg-blue-100 text-on-primary-fixed-variant',

                reported:  'bg-[#ccf0da] text-[#15803d]',

                rejected:  'bg-surface-container text-on-surface-variant',

            };

            const levelBadge = { MAJOR: 'text-error font-semibold', MINOR: 'text-[#a16207]' };

            document.getElementById('r-detail').innerHTML = rows.length

                ? rows.map(v => `

                    <tr class="hover:bg-surface-container-low">

                        <td class="px-3 py-2">${v.tanggal}</td>

                        <td class="px-3 py-2">${v.waktu}</td>

                        <td class="px-3 py-2">${v.zona}</td>

                        <td class="px-3 py-2">${v.shift}</td>

                        <td class="px-3 py-2">${v.label}</td>

                        <td class="px-3 py-2 ${levelBadge[v.level] ?? ''}">${v.level}</td>

                        <td class="px-3 py-2">${v.nama_pelanggar}</td>

                        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs ${statusBadge[v.status] ?? ''}">${v.status}</span></td>

                    </tr>`).join('')

                : '<tr><td colspan="8" class="px-3 py-4 text-center text-outline">Tidak ada data pelanggaran.</td></tr>';

        }

        document.addEventListener('DOMContentLoaded', () => {

            const today = fmtDate(new Date());

            const monthAgo = fmtDate(new Date(Date.now() - 30 * 24 * 60 * 60 * 1000));

            document.getElementById('r-from').value = monthAgo;

            document.getElementById('r-to').value = today;

            loadOptions();

        });

    </script>

@endpush