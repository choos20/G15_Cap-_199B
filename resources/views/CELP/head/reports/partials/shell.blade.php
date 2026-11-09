{{--
    Shared chrome for every report: filter bar, export buttons, and the print
    stylesheet. Print rules hide the app shell so Ctrl+P -> Save as PDF yields a
    clean document with a header and signature block.
--}}
<style>
    .rp-bar { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; padding:16px 18px; background:#fff;
              border-radius:14px; box-shadow:0 6px 18px rgba(11,42,42,0.06); margin-bottom:18px; }
    .rp-field { display:flex; flex-direction:column; gap:5px; }
    .rp-field label { font-size:0.74rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em; }
    .rp-field select, .rp-field input { border:1px solid #e2e8f0; border-radius:10px; padding:8px 11px; font:inherit;
                                        font-size:0.88rem; color:#0f172a; background:#fff; min-width:130px; }
    .rp-actions { margin-left:auto; display:flex; gap:8px; align-items:center; }
    .rp-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 15px; border-radius:10px; border:1px solid #e2e8f0;
              background:#fff; color:#334155; font:inherit; font-size:0.86rem; font-weight:700; cursor:pointer; text-decoration:none; }
    .rp-btn:hover { border-color:#cbd5e1; background:#f8fafc; }
    .rp-btn-primary { background:#0b6a4a; border-color:#0b6a4a; color:#fff; }
    .rp-btn-primary:hover { background:#0f8a61; border-color:#0f8a61; }

    .rp-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
    .rp-table th { text-align:left; padding:10px 12px; font-size:0.74rem; text-transform:uppercase; letter-spacing:0.04em;
                   color:#94a3b8; font-weight:700; border-bottom:1px solid #e1e0d9; white-space:nowrap; }
    .rp-table td { padding:11px 12px; border-bottom:1px solid #f1f5f9; color:#0f172a; vertical-align:top; }
    .rp-table tr:last-child td { border-bottom:none; }
    .rp-num { font-variant-numeric:tabular-nums; }
    .rp-muted { color:#94a3b8; }
    .rp-group-head { background:#f8fafc; font-weight:700; color:#0f172a; padding:10px 12px; border-bottom:1px solid #e2e8f0; }
    .rp-empty { padding:48px; text-align:center; color:#64748b; }
    .rp-sign { display:none; }
    .rp-print-head { display:none; }

    @media print {
        @page { margin:14mm; }
        .sidebar, .topbar, .rp-bar, .rp-noprint { display:none !important; }
        .main, .content, body { background:#fff !important; padding:0 !important; margin:0 !important; }
        .card { box-shadow:none !important; border:1px solid #cbd5e1; border-radius:0 !important; }
        .rp-print-head { display:block; margin-bottom:14px; }
        .rp-print-head h1 { margin:0; font-size:1.2rem; color:#000; }
        .rp-print-head .rp-sub { font-size:0.82rem; color:#333; margin-top:3px; }
        .rp-sign { display:block; margin-top:34px; page-break-inside:avoid; }
        .rp-table { font-size:0.76rem; }
        .rp-table th { color:#000; border-bottom:1px solid #000; }
        .rp-table td { border-bottom:1px solid #ddd; }
        tr, .rp-group { page-break-inside:avoid; }
        thead { display:table-header-group; }
        a { text-decoration:none; color:#000; }
    }
</style>

<div class="rp-print-head">
    <h1>Holy Name University &mdash; ProjectLink</h1>
    <div class="rp-sub">
        {{ $reportTitle }} &middot; A.Y. {{ $f['ay'] }}{{ $f['term'] ? ' · ' . \App\Support\AcademicPeriod::termLabel($f['term']) : '' }}
        &middot; Generated {{ now()->format('M j, Y g:i A') }}
    </div>
</div>
