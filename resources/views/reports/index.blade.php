@extends('layouts.app')
@section('title', 'Laporan')
@section('content')

    <div class="flex items-center justify-between mb-5">
        <h1 class="text-lg font-semibold text-gray-900">Generate Laporan</h1>
    </div>

    <div class="grid grid-cols-3 gap-5">
        {{-- FORM --}}
        <div class="col-span-1">
            <div class="bg-white rounded-xl border border-gray-100 p-5 space-y-4">
                <h2 class="text-sm font-medium text-gray-700">Parameter Laporan</h2>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Dari tanggal</label>
                    <input type="date" id="r-from" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Sampai tanggal</label>
                    <input type="date" id="r-to" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Zona (opsional)</label>
                    <select id="r-zone" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white">
                        <option value="">Semua zona</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Shift (opsional)</label>
                    <select id="r-shift" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white">
                        <option value="">Semua shift</option>
                    </select>
                </div>
                <button onclick="generateReport()"
                    class="w-full py-2.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    Generate Laporan
                </button>
            </div>
        </div>

        {{-- RESULT --}}
        <div class="col-span-2">
            <div id="result-placeholder"
                class="bg-white rounded-xl border border-gray-100 p-10 text-center text-sm text-gray-400 h-full flex items-center justify-center">
                Isi parameter di kiri, lalu klik Generate Laporan.
            </div>
            <div id="result-loading"
                class="hidden bg-white rounded-xl border border-gray-100 p-10 text-center text-sm text-gray-400">
                Membuat laporan...
            </div>
            <div id="result-content" class="hidden bg-white rounded-xl border border-gray-100 p-5 space-y-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-medium text-gray-700">Hasil Laporan</h2>
                        <div class="flex items-center gap-3">
                            <span id="r-period" class="text-xs text-gray-400"></span>
                            <a id="btn-download-pdf" href="#" target="_blank"
                                class="hidden items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
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
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-xs text-gray-400 mb-1">Total pelanggaran</p>
                        <p class="text-2xl font-semibold text-gray-900" id="r-total">—</p>
                    </div>
                    <div class="bg-red-50 rounded-xl p-4">
                        <p class="text-xs text-red-400 mb-1">Major</p>
                        <p class="text-2xl font-semibold text-red-600" id="r-major">—</p>
                    </div>
                    <div class="bg-amber-50 rounded-xl p-4">
                        <p class="text-xs text-amber-500 mb-1">Minor</p>
                        <p class="text-2xl font-semibold text-amber-600" id="r-minor">—</p>
                    </div>
                </div>
                <div>
                    <h3 class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Per zona</h3>
                    <div id="r-zones" class="space-y-2"></div>
                </div>
                <div>
                    <h3 class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Per shift</h3>
                    <div id="r-shifts" class="space-y-2"></div>
                </div>
                <div>
                    <h3 class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Jenis APD dilanggar</h3>
                    <div id="r-labels" class="flex flex-wrap gap-2"></div>
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
                renderResult(data.summary ?? data.data ?? data);
            } catch (e) {
                toast(e.message ?? 'Gagal generate laporan', 'error');
                document.getElementById('result-loading').classList.add('hidden');
                document.getElementById('result-placeholder').classList.remove('hidden');
            }
        }

        function renderResult(d) {
            document.getElementById('result-loading').classList.add('hidden');
            document.getElementById('result-content').classList.remove('hidden');

            document.getElementById('r-period').textContent = `${d.period?.from ?? ''} – ${d.period?.to ?? ''}`;
            const pdfBtn = document.getElementById('btn-download-pdf');
            const pdfFrom = d.period?.from ?? document.getElementById('r-from').value;
            const pdfTo = d.period?.to ?? document.getElementById('r-to').value;
            pdfBtn.href = `/reports/pdf?date_from=${pdfFrom}&date_to=${pdfTo}`;
            pdfBtn.classList.remove('hidden');
            pdfBtn.classList.add('flex');
            document.getElementById('r-total').textContent = d.total_violations ?? 0;
            document.getElementById('r-major').textContent = d.by_level?.major ?? 0;
            document.getElementById('r-minor').textContent = d.by_level?.minor ?? 0;

            document.getElementById('r-zones').innerHTML = (d.by_zone ?? []).map(z => `
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-600 w-32 shrink-0">${z.zone_name ?? z.zone ?? '—'}</span>
            <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                <div class="bg-blue-500 h-2 rounded-full"
                    style="width:${d.total_violations ? Math.round(z.total / d.total_violations * 100) : 0}%"></div>
            </div>
            <span class="text-sm font-medium text-gray-700 w-8 text-right">${z.total}</span>
        </div>
        `).join('') || '<p class="text-sm text-gray-400">Tidak ada data.</p>';

            document.getElementById('r-shifts').innerHTML = (d.by_shift ?? []).map(s => `
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-600 w-32 shrink-0">${s.shift}</span>
            <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                <div class="bg-green-500 h-2 rounded-full"
                    style="width:${d.total_violations ? Math.round(s.total / d.total_violations * 100) : 0}%"></div>
            </div>
            <span class="text-sm font-medium text-gray-700 w-8 text-right">${s.total}</span>
        </div>
        `).join('') || '<p class="text-sm text-gray-400">Tidak ada data.</p>';

            const byLabel = d.by_label ?? {};
            document.getElementById('r-labels').innerHTML = Object.entries(byLabel)
                .map(([k, v]) => `<span class="px-3 py-1 bg-gray-100 text-gray-700 text-sm rounded-full">${k}:
            <strong>${v}</strong></span>`)
                .join('') || '<p class="text-sm text-gray-400">Tidak ada data.</p>';
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