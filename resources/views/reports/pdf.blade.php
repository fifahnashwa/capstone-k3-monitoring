<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Laporan K3 {{ $dateFrom }} s/d {{ $dateTo }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 9px;
    color: #1a1a2e;
    background: #fff;
    padding: 20px 24px;
  }

  /* ── Header ── */
  .header {
    display: flex; /* DomPDF: gunakan table fallback jika flex tidak render */
    border-bottom: 3px solid #1e3a5f;
    padding-bottom: 12px;
    margin-bottom: 16px;
  }
  .header-table { width: 100%; border-collapse: collapse; }
  .header-table td { vertical-align: middle; }
  .header-left { width: 60%; }
  .header-right { width: 40%; text-align: right; }

  .company-name {
    font-size: 14px;
    font-weight: bold;
    color: #1e3a5f;
    letter-spacing: 0.5px;
  }
  .report-title {
    font-size: 11px;
    font-weight: bold;
    color: #c0392b;
    margin-top: 3px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .report-subtitle {
    font-size: 8px;
    color: #555;
    margin-top: 2px;
  }
  .meta-item { font-size: 8px; color: #555; line-height: 1.6; }
  .meta-label { font-weight: bold; color: #1e3a5f; }
  .badge-period {
    display: inline-block;
    background: #1e3a5f;
    color: #fff;
    padding: 3px 10px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: bold;
    margin-top: 4px;
  }

  /* ── Summary cards ── */
  .summary-section { margin-bottom: 14px; }
  .section-title {
    font-size: 8px;
    font-weight: bold;
    color: #1e3a5f;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-left: 3px solid #c0392b;
    padding-left: 6px;
    margin-bottom: 8px;
  }

  .cards-table { width: 100%; border-collapse: separate; border-spacing: 6px 0; }
  .card {
    border-radius: 4px;
    padding: 8px 10px;
    text-align: center;
  }
  .card-total   { background: #1e3a5f; color: #fff; }
  .card-apd     { background: #2980b9; color: #fff; }
  .card-disip   { background: #8e44ad; color: #fff; }
  .card-major   { background: #c0392b; color: #fff; }
  .card-minor   { background: #e67e22; color: #fff; }

  .card-number { font-size: 20px; font-weight: bold; line-height: 1; }
  .card-label  { font-size: 7px; opacity: 0.85; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }

  /* ── Mini summary tables ── */
  .mini-tables { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  .mini-tables td { vertical-align: top; padding-right: 12px; }
  .mini-table { width: 100%; border-collapse: collapse; }
  .mini-table th {
    background: #f0f4f8;
    color: #1e3a5f;
    font-size: 7.5px;
    font-weight: bold;
    padding: 4px 6px;
    text-align: left;
    border: 1px solid #dde3ea;
  }
  .mini-table td {
    font-size: 8px;
    padding: 3px 6px;
    border: 1px solid #dde3ea;
    color: #333;
  }
  .mini-table tr:nth-child(even) td { background: #f8fafc; }
  .num { text-align: right; font-weight: bold; color: #1e3a5f; }

  /* ── Main table ── */
  .main-table-title { margin-bottom: 8px; }
  table.violations {
    width: 100%;
    border-collapse: collapse;
    font-size: 7.5px;
  }
  table.violations thead tr {
    background: #1e3a5f;
    color: #fff;
  }
  table.violations thead th {
    padding: 5px 5px;
    text-align: left;
    font-weight: bold;
    font-size: 7px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
  }
  table.violations tbody tr:nth-child(even) { background: #f5f8fc; }
  table.violations tbody tr:hover           { background: #eaf0fb; }
  table.violations tbody td {
    padding: 4px 5px;
    border-bottom: 1px solid #e8ecf0;
    color: #333;
    vertical-align: top;
  }
  .td-no       { width: 22px; text-align: center; color: #888; }
  .td-date     { width: 55px; white-space: nowrap; }
  .td-zona     { width: 70px; }
  .td-cam      { width: 40px; }
  .td-shift    { width: 65px; }
  .td-jenis    { width: 40px; }
  .td-label    { width: 80px; }
  .td-nama     { width: 90px; }
  .td-catatan  { }

  .badge {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 2px;
    font-size: 6.5px;
    font-weight: bold;
    text-transform: uppercase;
  }
  .badge-apd     { background: #dbeafe; color: #1e40af; }
  .badge-disip   { background: #ede9fe; color: #5b21b6; }
  .badge-major   { background: #fee2e2; color: #991b1b; }
  .badge-minor   { background: #fef3c7; color: #92400e; }
  .badge-conf    { background: #f0fdf4; color: #166534; }

  /* ── Footer ── */
  .footer {
    margin-top: 16px;
    border-top: 1px solid #dde3ea;
    padding-top: 8px;
  }
  .footer-table { width: 100%; border-collapse: collapse; }
  .footer-left  { font-size: 7px; color: #888; }
  .footer-right { text-align: right; font-size: 7px; color: #888; }

  .signature-box {
    border: 1px solid #dde3ea;
    border-radius: 3px;
    padding: 6px 14px;
    display: inline-block;
    text-align: center;
    min-width: 120px;
  }
  .sig-label   { font-size: 7px; color: #555; }
  .sig-line    { border-top: 1px solid #333; margin: 20px 0 4px; }
  .sig-name    { font-size: 7.5px; font-weight: bold; color: #1e3a5f; }

  .no-data {
    text-align: center;
    padding: 20px;
    color: #888;
    font-style: italic;
  }

  /* DomPDF page break */
  .page-break { page-break-after: always; }
</style>
</head>
<body>

{{-- ═══════════════ HEADER ═══════════════ --}}
<table class="header-table" style="margin-bottom:16px; border-bottom:3px solid #1e3a5f; padding-bottom:12px;">
  <tr>
    <td class="header-left">
      <div class="company-name">⚙ SISTEM MONITORING K3</div>
      <div class="report-title">Laporan Pelanggaran K3 & Disiplin Kerja</div>
      <div class="report-subtitle">Sistem Computer Vision — Deteksi Otomatis APD & Disiplin</div>
      <div class="badge-period">Periode: {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</div>
    </td>
    <td class="header-right">
      <div class="meta-item"><span class="meta-label">Digenerate oleh</span>: {{ $generatedBy }}</div>
      <div class="meta-item"><span class="meta-label">Tanggal cetak</span>&nbsp;&nbsp;: {{ $generatedAt }}</div>
      <div class="meta-item"><span class="meta-label">Total pelanggaran</span>: <strong>{{ $summary['total'] }}</strong></div>
    </td>
  </tr>
</table>

{{-- ═══════════════ SUMMARY CARDS ═══════════════ --}}
<div class="section-title">Ringkasan</div>
<table class="cards-table" style="margin-bottom:14px;">
  <tr>
    <td><div class="card card-total"><div class="card-number">{{ $summary['total'] }}</div><div class="card-label">Total</div></div></td>
    <td><div class="card card-apd"><div class="card-number">{{ $summary['by_type']['APD'] }}</div><div class="card-label">APD</div></div></td>
    <td><div class="card card-disip"><div class="card-number">{{ $summary['by_type']['Disiplin'] }}</div><div class="card-label">Disiplin</div></div></td>
    <td><div class="card card-major"><div class="card-number">{{ $summary['by_level']['Major'] }}</div><div class="card-label">Major</div></div></td>
    <td><div class="card card-minor"><div class="card-number">{{ $summary['by_level']['Minor'] }}</div><div class="card-label">Minor</div></div></td>
  </tr>
</table>

{{-- ═══════════════ MINI TABLES ═══════════════ --}}
<table class="mini-tables" style="margin-bottom:14px;">
  <tr>
    {{-- By Label --}}
    <td style="width:33%">
      <table class="mini-table">
        <thead><tr><th>Jenis Pelanggaran APD</th><th class="num">Jumlah</th></tr></thead>
        <tbody>
          @foreach($summary['by_label'] as $label => $count)
          @if($count > 0)
          <tr><td>{{ $label }}</td><td class="num">{{ $count }}</td></tr>
          @endif
          @endforeach
        </tbody>
      </table>
    </td>
    {{-- By Zone --}}
    <td style="width:33%">
      <table class="mini-table">
        <thead><tr><th>Zona</th><th class="num">Jumlah</th></tr></thead>
        <tbody>
          @foreach($summary['by_zone'] as $zone => $count)
          <tr><td>{{ $zone }}</td><td class="num">{{ $count }}</td></tr>
          @endforeach
          @if(empty($summary['by_zone']))<tr><td colspan="2" style="color:#888;font-style:italic">Tidak ada data</td></tr>@endif
        </tbody>
      </table>
    </td>
    {{-- By Shift --}}
    <td style="width:33%; padding-right:0">
      <table class="mini-table">
        <thead><tr><th>Shift</th><th class="num">Jumlah</th></tr></thead>
        <tbody>
          @foreach($summary['by_shift'] as $shift => $count)
          <tr><td>{{ $shift }}</td><td class="num">{{ $count }}</td></tr>
          @endforeach
          @if(empty($summary['by_shift']))<tr><td colspan="2" style="color:#888;font-style:italic">Tidak ada data</td></tr>@endif
        </tbody>
      </table>
    </td>
  </tr>
</table>

{{-- ═══════════════ DETAIL TABLE ═══════════════ --}}
<div class="section-title main-table-title">Detail Pelanggaran</div>

@if($detail->isEmpty())
  <div class="no-data">Tidak ada data pelanggaran pada periode ini.</div>
@else
<table class="violations">
  <thead>
    <tr>
      <th class="td-no">#</th>
      <th class="td-date">Tanggal</th>
      <th class="td-date">Waktu</th>
      <th class="td-zona">Zona</th>
      <th class="td-cam">Kamera</th>
      <th class="td-shift">Shift</th>
      <th class="td-jenis">Jenis</th>
      <th class="td-label">Label</th>
      <th style="width:35px">Level</th>
      <th style="width:35px">Conf.</th>
      <th class="td-nama">Nama</th>
      <th class="td-catatan">Catatan</th>
    </tr>
  </thead>
  <tbody>
    @foreach($detail as $i => $v)
    <tr>
      <td class="td-no">{{ $i + 1 }}</td>
      <td class="td-date">{{ $v['tanggal'] }}</td>
      <td class="td-date">{{ $v['waktu'] }}</td>
      <td class="td-zona">{{ $v['zona'] }}</td>
      <td class="td-cam">{{ $v['kamera'] }}</td>
      <td class="td-shift">{{ $v['shift'] }}</td>
      <td class="td-jenis">
        <span class="badge {{ $v['jenis'] === 'APD' ? 'badge-apd' : 'badge-disip' }}">
          {{ $v['jenis'] }}
        </span>
      </td>
      <td class="td-label">{{ $v['label'] }}</td>
      <td>
        @if($v['level'] !== '-')
        <span class="badge {{ $v['level'] === 'MAJOR' ? 'badge-major' : 'badge-minor' }}">
          {{ $v['level'] }}
        </span>
        @else
        <span style="color:#aaa">—</span>
        @endif
      </td>
      <td><span class="badge badge-conf">{{ $v['confidence'] }}</span></td>
      <td class="td-nama">{{ $v['nama'] }}</td>
      <td class="td-catatan" style="color:#555">{{ $v['catatan'] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

{{-- ═══════════════ FOOTER ═══════════════ --}}
<table class="footer-table" style="margin-top:20px; border-top:1px solid #dde3ea; padding-top:10px;">
  <tr>
    <td class="footer-left" style="vertical-align:bottom">
      <div>Dokumen ini digenerate secara otomatis oleh Sistem Monitoring K3.</div>
      <div style="margin-top:2px">Dicetak: {{ $generatedAt }} · Oleh: {{ $generatedBy }}</div>
    </td>
    <td class="footer-right" style="vertical-align:top">
      <div class="signature-box">
        <div class="sig-label">Mengetahui, HR / Pimpinan</div>
        <div class="sig-line"></div>
        <div class="sig-name">( _________________________ )</div>
      </div>
    </td>
  </tr>
</table>

</body>
</html>