@extends('layouts.app')

@section('title', 'Dashboard')

@push('head')

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>

@endpush

@section('content')

    {{-- HEADER --}}

    <div class="flex items-center justify-between mb-5">

        <div>

            <h1 class="text-lg font-semibold text-on-surface">Dashboard KPI</h1>

            <p class="text-sm text-outline mt-0.5" id="period-label">Memuat data...</p>

        </div>

        <div class="flex items-center gap-2">

            <div id="today-badge" class="hidden mr-1 flex items-center gap-1.5 px-3 py-1.5 rounded-full"
                style="background:#004ac6;">

                <span class="text-xs text-outline/60 text-white">Hari ini</span>

                <span class="text-xs font-semibold text-white" id="today-count">—</span>

            </div>

            <button onclick="fetchDashboard(7)" data-period="7"
                class="period-btn px-3 py-1.5 text-xs rounded-full border border-outline-variant text-on-surface-variant hover:bg-surface-container-low">

                7 hari

            </button>

            <button onclick="fetchDashboard(30)" data-period="30"
                class="period-btn px-3 py-1.5 text-xs rounded-full border border-primary/40 bg-primary-fixed text-on-primary-fixed-variant font-medium">

                30 hari

            </button>

            <button onclick="fetchDashboard(90)" data-period="90"
                class="period-btn px-3 py-1.5 text-xs rounded-full border border-outline-variant text-on-surface-variant hover:bg-surface-container-low">

                3 bulan

            </button>

        </div>

    </div>

    {{-- LOADING --}}

    <div id="loading" class="py-20 text-center text-sm text-outline">Memuat data...</div>

    {{-- ERROR --}}

    <div id="error-state" class="hidden py-20 text-center">

        <p class="text-sm text-error" id="error-msg">Gagal memuat data.</p>

        <button onclick="fetchDashboard(activePeriod)" class="mt-2 text-xs text-primary hover:underline">Coba lagi</button>

    </div>

    {{-- CONTENT --}}

    <div id="content" class="hidden space-y-4">

        {{-- SUMMARY CARDS --}}

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

                <p class="text-xs text-outline mb-1.5">Total pelanggaran</p>

                <p class="text-3xl font-semibold text-on-surface" id="c-total">—</p>

                <p class="text-xs mt-1.5 h-4" id="d-total"></p>

            </div>

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

                <p class="text-xs text-outline mb-1.5">Major</p>

                <p class="text-3xl font-semibold text-error" id="c-major">—</p>

                <p class="text-xs mt-1" id="d-major">no_helmet / no_boots</p>

            </div>

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

                <p class="text-xs text-outline mb-1.5">Minor</p>

                <p class="text-3xl font-semibold text-[#a16207]" id="c-minor">—</p>

                <p class="text-xs mt-1" id="d-minor">no_vest</p>

            </div>

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

                <p class="text-xs text-outline mb-1.5">APD</p>

                <p class="text-3xl font-semibold text-on-surface" id="c-apd">—</p>

            </div>

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

                <p class="text-xs text-outline mb-1.5">Disiplin</p>

                <p class="text-3xl font-semibold text-on-surface" id="c-disiplin">—</p>

            </div>

        </div>

        {{-- CHARTS ROW 1: trend + label doughnut --}}

        <div class="grid grid-cols-3 gap-4">

            <div class="col-span-2 bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

                <p class="text-sm font-medium text-on-surface mb-3">Tren pelanggaran harian</p>

                <canvas id="trendChart" height="120"></canvas>

            </div>

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4 flex flex-col">

                <p class="text-sm font-medium text-on-surface mb-2">Jenis APD dilanggar</p>

                <div class="flex-1 flex items-center justify-center min-h-0">

                    <canvas id="labelChart"></canvas>

                </div>

            </div>

        </div>

        {{-- CHARTS ROW 2: zone + shift, equal height --}}

        <div class="grid grid-cols-2 gap-4">

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

                <div class="flex items-center justify-between mb-3">

                    <p class="text-sm font-medium text-on-surface">Per zona</p>

                    <div class="flex items-center gap-3 text-xs text-outline">

                        <span class="flex items-center gap-1"><span
                                class="w-2 h-2 rounded-sm bg-red-400 inline-block"></span>Tinggi</span>

                        <span class="flex items-center gap-1"><span
                                class="w-2 h-2 rounded-sm bg-amber-400 inline-block"></span>Sedang</span>

                        <span class="flex items-center gap-1"><span
                                class="w-2 h-2 rounded-sm bg-[#34d399] inline-block"></span>Rendah</span>

                    </div>

                </div>

                <canvas id="zoneChart" height="140"></canvas>

            </div>

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant p-4">

                <p class="text-sm font-medium text-on-surface mb-3">Per shift</p>

                <canvas id="shiftChart" height="140"></canvas>

            </div>

        </div>

    </div>

@endsection

@push('scripts')

    <script>

        let charts = {};

        let activePeriod = 30;

        const TICK = { font: { size: 10 }, color: '#737686' };

        const GRID = { color: 'rgba(0,0,0,0.05)' };

        const PALETTE = ['#004ac6', '#6366f1', '#06b6d4', '#a78bfa', '#10b981', '#f59e0b', '#ec4899', '#38bdf8'];

        const RISK_COLOR = { high: '#f87171', medium: '#fbbf24', low: '#34d399' };

        function scaleXY(horizontal = false) {

            return horizontal

                ? { x: { ticks: TICK, grid: GRID }, y: { ticks: TICK, grid: { display: false } } }

                : { x: { ticks: TICK, grid: { display: false } }, y: { ticks: TICK, grid: GRID, beginAtZero: true } };

        }

        function makeChart(key, type, data, options) {

            if (charts[key]) charts[key].destroy();

            charts[key] = new Chart(document.getElementById(key + 'Chart'), { type, data, options });

        }

        // Format tanggal local (bukan UTC) untuk menghindari timezone bug

        function fmtDate(d) {

            const y = d.getFullYear();

            const m = String(d.getMonth() + 1).padStart(2, '0');

            const day = String(d.getDate()).padStart(2, '0');

            return `${y}-${m}-${day}`;

        }

        function getDateRange(days) {

            const to = new Date();

            const from = new Date(to.getTime() - (days - 1) * 86400000);

            return { date_from: fmtDate(from), date_to: fmtDate(to) };

        }

        // Periode sebelumnya — durasi sama, digeser ke belakang

        function getPrevDateRange(days) {

            const to = new Date();

            const prevTo = new Date(to.getTime() - days * 86400000);

            const prevFrom = new Date(prevTo.getTime() - (days - 1) * 86400000);

            return { date_from: fmtDate(prevFrom), date_to: fmtDate(prevTo) };

        }

        // Level risk zona relatif terhadap zona dengan violations terbanyak

        function zoneRiskLevel(total, maxTotal) {

            if (maxTotal === 0) return 'low';

            const ratio = total / maxTotal;

            if (ratio >= 0.6) return 'high';

            if (ratio >= 0.3) return 'medium';

            return 'low';

        }

        // Delta HTML — merah kalau naik (lebih banyak pelanggaran = buruk), hijau kalau turun

        function deltaHtml(current, prev) {

            if (prev === null || prev === undefined) return '';

            if (prev === 0) {

                return current > 0

                    ? '<span class="text-error font-medium">baru</span>'

                    : '<span class="text-outline">—</span>';

            }

            const pct = Math.round(((current - prev) / prev) * 100);

            if (pct === 0) return '<span class="text-outline">→ sama</span>';

            const up = pct > 0;

            const cls = up ? 'text-error' : 'text-[#15803d]';

            return `<span class="${cls} font-medium">${up ? '↑' : '↓'} ${Math.abs(pct)}%</span><span class="text-outline"> vs periode sebelumnya</span>`;

        }

        async function fetchDashboard(days) {

            activePeriod = days;

            document.querySelectorAll('.period-btn').forEach(btn => {

                const active = btn.dataset.period == days;

                btn.className = active

                    ? 'period-btn px-3 py-1.5 text-xs rounded-full border border-primary/40 bg-primary-fixed text-on-primary-fixed-variant font-medium'

                    : 'period-btn px-3 py-1.5 text-xs rounded-full border border-outline-variant text-on-surface-variant hover:bg-surface-container-low';

            });

            document.getElementById('loading').classList.remove('hidden');

            document.getElementById('content').classList.add('hidden');

            document.getElementById('error-state').classList.add('hidden');

            document.getElementById('today-badge').classList.add('hidden');

            const { date_from, date_to } = getDateRange(days);

            const { date_from: prev_from, date_to: prev_to } = getPrevDateRange(days);

            const todayStr = fmtDate(new Date());

            try {

                // Tiga request paralel: periode aktif, periode sebelumnya, hari ini

                const [current, prev, today] = await Promise.all([

                    api('GET', `/api/dashboard/summary?date_from=${date_from}&date_to=${date_to}`),

                    api('GET', `/api/dashboard/summary?date_from=${prev_from}&date_to=${prev_to}`),

                    api('GET', `/api/dashboard/summary?date_from=${todayStr}&date_to=${todayStr}`),

                ]);

                // Guard: api() return undefined saat 401 redirect berlangsung

                if (current) render(current, prev, today);

            } catch (err) {

                document.getElementById('loading').classList.add('hidden');

                document.getElementById('error-state').classList.remove('hidden');

                document.getElementById('error-msg').textContent = 'Gagal memuat data: ' + (err.message ?? JSON.stringify(err));

            }

        }

        function render(d, prev, today) {

            document.getElementById('loading').classList.add('hidden');

            document.getElementById('content').classList.remove('hidden');

            document.getElementById('period-label').textContent = `${d.period?.from} – ${d.period?.to}`;

            const total = d.total_violations ?? 0;

            const major = d.by_level?.major ?? 0;

            const minor = d.by_level?.minor ?? 0;

            document.getElementById('c-total').textContent = total;

            document.getElementById('c-major').textContent = major;

            document.getElementById('c-minor').textContent = minor;

            document.getElementById('c-apd').textContent = d.by_type?.apd ?? 0;

            document.getElementById('c-disiplin').textContent = d.by_type?.discipline ?? 0;

            // Delta

            if (prev) {

                document.getElementById('d-total').innerHTML = deltaHtml(total, prev.total_violations);

                document.getElementById('d-major').innerHTML = deltaHtml(major, prev.by_level?.major ?? 0);

                document.getElementById('d-minor').innerHTML = deltaHtml(minor, prev.by_level?.minor ?? 0);

            }

            // Today badge

            if (today) {

                document.getElementById('today-count').textContent = (today.total_violations ?? 0) + ' pelanggaran';

                document.getElementById('today-badge').classList.remove('hidden');

            }

            // Trend chart

            const trend = d.daily_trend ?? [];

            makeChart('trend', 'line', {

                labels: trend.map(t => {

                    const [, mm, dd] = t.date.split('-');

                    return `${parseInt(dd)}/${parseInt(mm)}`;

                }),

                datasets: [{

                    data: trend.map(t => t.total),

                    borderColor: '#004ac6',

                    backgroundColor: 'rgba(59,130,246,0.07)',

                    tension: 0.35,

                    fill: true,

                    pointRadius: 2,

                    borderWidth: 1.5,

                }]

            }, {

                responsive: true,

                plugins: { legend: { display: false } },

                scales: {

                    x: { ticks: { ...TICK, maxTicksLimit: 10 }, grid: GRID },

                    y: { ticks: TICK, grid: GRID, beginAtZero: true }

                }

            });

            // Shift chart

            const shifts = d.by_shift ?? [];

            makeChart('shift', 'bar', {

                labels: shifts.map(s => s.shift),

                datasets: [{

                    data: shifts.map(s => s.total),

                    backgroundColor: shifts.map((_, i) => PALETTE[i % PALETTE.length]),

                    borderRadius: 4,

                    borderWidth: 0,

                }]

            }, {

                indexAxis: 'y',

                responsive: true,

                plugins: { legend: { display: false } },

                scales: scaleXY(true),

            });

            // Zone chart

            // Warna berdasarkan risk level relatif antar zona

            const zones = d.by_zone ?? [];

            const maxZone = Math.max(...zones.map(z => z.total), 1);

            makeChart('zone', 'bar', {

                labels: zones.map(z => z.zone_name),

                datasets: [{

                    data: zones.map(z => z.total),

                    backgroundColor: zones.map(z => RISK_COLOR[zoneRiskLevel(z.total, maxZone)]),

                    borderRadius: 4,

                    borderWidth: 0,

                }]

            }, {

                responsive: true,

                plugins: { legend: { display: false } },

                scales: scaleXY(false),

            });

            // Label doughnut

            const byLabel = d.by_label ?? {};

            const labelKeys = Object.keys(byLabel);

            makeChart('label', 'doughnut', {

                labels: labelKeys,

                datasets: [{

                    data: labelKeys.map(k => byLabel[k]),

                    backgroundColor: [
                        '#be185d', // rose
                        '#7c3aed', // violet
                        '#0891b2', // teal
                        '#d97706', // amber
                        '#059669', // emerald
                        '#dc2626', // red
                        '#0369a1', // dark blue
                        '#9d174d', // burgundy
                        '#0f766e', // dark teal
                        '#b45309', // coklat amber
                    ],

                    borderWidth: 0,

                    hoverOffset: 4,

                }]

            }, {

                responsive: true,

                maintainAspectRatio: false,

                cutout: '62%',

                plugins: {

                    legend: {

                        position: 'bottom',

                        labels: { font: { size: 10 }, boxWidth: 10, padding: 8, color: '#737686' },

                    }

                }

            });

        }

        document.addEventListener('DOMContentLoaded', () => fetchDashboard(30));

    </script>

@endpush