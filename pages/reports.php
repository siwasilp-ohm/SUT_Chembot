<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
if (!in_array($user['role_name'], ['admin','lab_manager','ceo'])) { header('Location: /v1/'); exit; }
$lang = I18n::getCurrentLang();
$TH   = $lang === 'th';
Layout::head(__('reports_title'), [], [
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2/dist/chartjs-plugin-datalabels.min.js',
]);
?>
<body>
<?php Layout::sidebar('reports'); Layout::beginContent(); ?>
<style>
:root{--rp-r:14px;--rp-rs:10px;--rp-sh:0 1px 6px rgba(0,0,0,.06);--rp-shm:0 4px 20px rgba(0,0,0,.09)}

/* ── Hero ── */
.rp-hero{background:linear-gradient(135deg,#1e1b4b 0%,#312e81 55%,#6366f1 100%);border-radius:var(--rp-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.rp-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.rp-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;position:relative}
.rp-hero-info{position:relative}
.rp-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.rp-hero-info p{font-size:12px;opacity:.85;margin:0}
.rp-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0}
.rp-hero-c{text-align:center;position:relative}
.rp-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.rp-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}

/* ── Stats Row ── */
.rp-sum{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.rp-sc{background:#fff;border-radius:var(--rp-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--rp-sh);border:1px solid var(--border);transition:all .15s;cursor:pointer;position:relative}
.rp-sc:hover{transform:translateY(-2px);box-shadow:var(--rp-shm);border-color:#6366f1}
.rp-sc-arrow{font-size:9px;color:#cbd5e1;margin-left:auto;flex-shrink:0;transition:color .15s}
.rp-sc:hover .rp-sc-arrow{color:#6366f1}
.rp-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.rp-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.rp-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Stat Detail Modal ── */
.rp-sd-overlay{display:none;position:fixed;inset:0;z-index:9500;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);justify-content:center;align-items:center;padding:16px}
.rp-sd-overlay.show{display:flex;animation:rpFd .18s ease}
.rp-sd-modal{background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:86vh;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.22);display:flex;flex-direction:column}
.rp-sd-hdr{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1.5px solid var(--border);flex-shrink:0;background:#fafbff}
.rp-sd-hdr-ic{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.rp-sd-title{font-size:15px;font-weight:800;color:var(--c1)}
.rp-sd-sub{font-size:11px;color:var(--c3);margin-top:1px}
.rp-sd-close{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:#fff;color:var(--c3);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;margin-left:auto;transition:.12s;flex-shrink:0}
.rp-sd-close:hover{background:#fef2f2;border-color:#fecaca;color:#dc2626}
.rp-sd-body{flex:1;overflow-y:auto}
.rp-sd-foot{padding:12px 20px;border-top:1.5px solid var(--border);display:flex;justify-content:flex-end;flex-shrink:0;background:#f8fafc}
.rp-sd-done{padding:9px 22px;background:#4338ca;color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:.12s}
.rp-sd-done:hover{background:#3730a3}
/* Modal inner parts */
.rp-sd-kpi{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--border);border:1.5px solid var(--border);border-radius:12px;overflow:hidden;margin:16px}
.rp-sd-kpi-c{background:#fff;padding:14px 12px;text-align:center}
.rp-sd-kpi-v{font-size:22px;font-weight:900;line-height:1}
.rp-sd-kpi-l{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.4px;margin-top:3px}
.rp-sd-list{display:flex;flex-direction:column;gap:0}
.rp-sd-row{display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid #f5f7fa;transition:background .12s}
.rp-sd-row:last-child{border-bottom:none}
.rp-sd-row:hover{background:#fafbff}
.rp-sd-rank{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0;background:#f1f5f9;color:var(--c3)}
.rp-sd-rank.r1{background:#fef3c7;color:#92400e}
.rp-sd-rank.r2{background:#f1f5f9;color:#475569}
.rp-sd-rank.r3{background:#fed7aa;color:#9a3412}
.rp-sd-info{flex:1;min-width:0}
.rp-sd-name{font-size:12px;font-weight:700;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rp-sd-meta{font-size:10px;color:var(--c3);margin-top:1px}
.rp-sd-bar-wrap{flex:1;min-width:60px;max-width:100px}
.rp-sd-bar{height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden}
.rp-sd-bar-fill{height:100%;border-radius:3px;transition:width .5s ease}
.rp-sd-val{text-align:right;flex-shrink:0;min-width:44px}
.rp-sd-val-n{font-size:13px;font-weight:800;color:var(--c1)}
.rp-sd-val-u{font-size:9px;color:var(--c3)}
.rp-sd-section{padding:10px 16px 4px;font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);background:#fafbff;display:flex;align-items:center;gap:6px}
.rp-sd-alert-item{display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid #f5f7fa;cursor:pointer;transition:background .12s}
.rp-sd-alert-item:hover{background:#fafbff}
.rp-sd-alert-item:last-child{border-bottom:none}
.rp-sd-alert-ic{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.rp-sd-alert-main{flex:1;min-width:0}
.rp-sd-alert-name{font-size:12px;font-weight:700;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rp-sd-alert-sub{font-size:10px;color:var(--c3);margin-top:1px}
.rp-sd-badge{font-size:9px;font-weight:700;padding:3px 8px;border-radius:7px;flex-shrink:0;white-space:nowrap}
.badge-danger{background:#fef2f2;color:#dc2626}
.badge-warning{background:#fff7ed;color:#c2410c}
.badge-info{background:#ede9fe;color:#4338ca}
.badge-ok{background:#dcfce7;color:#15803d}
.rp-sd-empty{text-align:center;padding:32px 16px;color:var(--c3)}
.rp-sd-empty i{font-size:28px;display:block;margin-bottom:8px;opacity:.25}
.rp-sd-empty p{font-size:12px;margin:0}

/* ── Tabs ── */
.rp-tabs{display:flex;align-items:center;gap:0;margin-bottom:18px;overflow-x:auto;padding-bottom:2px}
.rp-tab-wrap{display:inline-flex;background:#f1f5f9;border-radius:var(--rp-rs);padding:3px;gap:2px;min-width:max-content}
.rp-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;background:transparent;border-radius:8px;font-size:12px;font-weight:600;color:var(--c3);cursor:pointer;font-family:inherit;transition:all .15s;white-space:nowrap}
.rp-tab:hover{color:var(--c1)}
.rp-tab.on{background:#fff;color:#4338ca;box-shadow:0 1px 4px rgba(0,0,0,.08);font-weight:700}
.rp-tab i{font-size:11px}
.rp-tab .bg{font-size:9px;padding:2px 7px;border-radius:10px;font-weight:700;background:#e2e8f0;color:var(--c3)}
.rp-tab.on .bg{background:#4338ca;color:#fff}

/* ── Panels ── */
.rp-panel{display:none;animation:rpFd .2s ease}
.rp-panel.on{display:block}
@keyframes rpFd{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}

/* ── Grid ── */
.rp-g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:700px){.rp-g2{grid-template-columns:1fr}}

/* ── Cards ── */
.rp-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--rp-r);overflow:hidden;margin-bottom:14px;box-shadow:var(--rp-sh)}
.rp-card-hdr{display:flex;align-items:center;gap:8px;padding:12px 16px;background:#f8fafc;border-bottom:1.5px solid var(--border)}
.rp-card-hdr i{font-size:13px;color:#6366f1}
.rp-card-hdr h5{margin:0;font-size:12px;font-weight:700;color:var(--c1);flex:1;text-transform:uppercase;letter-spacing:.4px}
.rp-card-hdr .rp-cnt{font-size:10px;font-weight:700;padding:2px 9px;border-radius:8px;letter-spacing:.3px}
.rp-card-body{padding:14px 16px}
.rp-card-body.np{padding:0}

/* ── Chart ── */
.rp-chart{position:relative}
.rp-chart-tall{height:340px}
.rp-chart-std{height:280px}

/* ── Chart Toolbar ── */
.rp-chart-tb{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--border);background:#fafbff;flex-wrap:wrap;gap:8px}
.rp-chart-title{font-size:13px;font-weight:700;color:var(--c1)}
.rp-chart-sub{font-size:11px;color:var(--c3);margin-top:1px}

/* ── Compliance ── */
.rp-comp{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.rp-comp-card{text-align:center;padding:16px 10px;border-radius:12px;border:1.5px solid transparent}
.rp-comp-card.pass{background:#f0fdf4;border-color:#bbf7d0}
.rp-comp-card.warn{background:#fefce8;border-color:#fde047}
.rp-comp-card.fail{background:#fef2f2;border-color:#fecaca}
.rp-comp-v{font-size:28px;font-weight:900;line-height:1}
.rp-comp-card.pass .rp-comp-v{color:#059669}
.rp-comp-card.warn .rp-comp-v{color:#d97706}
.rp-comp-card.fail .rp-comp-v{color:#dc2626}
.rp-comp-l{font-size:11px;font-weight:600;margin-top:4px}
.rp-comp-card.pass .rp-comp-l{color:#059669}.rp-comp-card.warn .rp-comp-l{color:#d97706}.rp-comp-card.fail .rp-comp-l{color:#dc2626}

/* ── Lab List ── */
.rp-lab-list{display:flex;flex-direction:column;gap:5px;padding:12px}
.rp-lab-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1.5px solid var(--border);border-radius:11px;background:#fff;transition:all .15s;position:relative}
.rp-lab-row::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:#6366f1;border-radius:3px 0 0 3px}
.rp-lab-row:hover{background:#f5f5ff;border-color:#c7d2fe}
.rp-lab-ico{width:36px;height:36px;border-radius:10px;background:#ede9fe;color:#6d28d9;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.rp-lab-name{font-size:13px;font-weight:700;color:var(--c1);flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rp-lab-meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:3px}
.rp-lab-meta span{font-size:10px;color:var(--c3);display:flex;align-items:center;gap:3px}
.rp-lab-meta i{font-size:9px;color:var(--c3)}
.rp-track{flex:1.2;max-width:130px}
.rp-bar-wrap{height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden}
.rp-bar-fill{height:100%;border-radius:3px;transition:width .4s ease}
.rp-bar-fill.g{background:linear-gradient(90deg,#10b981,#059669)}
.rp-bar-fill.o{background:linear-gradient(90deg,#f97316,#ea580c)}
.rp-bar-fill.r{background:linear-gradient(90deg,#ef4444,#dc2626)}
.rp-pct{font-size:11px;font-weight:700;color:var(--c1);white-space:nowrap;text-align:right;min-width:36px;margin-top:3px}
.rp-overdue{font-size:10px;font-weight:700;padding:2px 8px;border-radius:7px;background:#fef2f2;color:#dc2626;white-space:nowrap;display:inline-flex;align-items:center;gap:4px}

/* ── Item List ── */
.rp-ilist{display:flex;flex-direction:column;gap:4px;padding:10px 12px 14px}
.rp-irow{display:flex;align-items:center;gap:12px;padding:10px 12px 10px 16px;border:1.5px solid var(--border);border-radius:11px;background:#fff;cursor:pointer;transition:all .15s;position:relative;overflow:hidden}
.rp-irow:hover{border-color:#6366f1;background:#f5f5ff}
.rp-irow::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px}
.rp-irow.danger::before{background:#ef4444}.rp-irow.warning::before{background:#f97316}.rp-irow.info::before{background:#6366f1}
.rp-irow-ico{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.rp-irow.danger .rp-irow-ico{background:#fef2f2;color:#ef4444}
.rp-irow.warning .rp-irow-ico{background:#fff7ed;color:#f97316}
.rp-irow.info .rp-irow-ico{background:#ede9fe;color:#6d28d9}
.rp-irow-main{flex:1;min-width:0}
.rp-irow-name{font-size:13px;font-weight:700;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rp-irow-sub{font-size:10px;color:var(--c3);margin-top:2px;display:flex;gap:8px;flex-wrap:wrap}
.rp-irow-sub span{display:flex;align-items:center;gap:3px}
.rp-ibadge{font-size:9px;font-weight:700;padding:3px 9px;border-radius:7px;white-space:nowrap;flex-shrink:0;letter-spacing:.3px;text-transform:uppercase}
.rp-ibadge.danger{background:#fef2f2;color:#dc2626}
.rp-ibadge.warning{background:#fff7ed;color:#c2410c}
.rp-ibadge.info{background:#ede9fe;color:#4338ca}

/* ── Yearly Chart filter ── */
.rp-fy-filter{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.rp-fy-filter label{font-size:11px;font-weight:600;color:var(--c3)}
.rp-fy-filter select{padding:5px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff;color:var(--c1);cursor:pointer}
.rp-fy-filter select:focus{outline:none;border-color:#6366f1}

/* ── Room Report ── */
.rp-room-filter{display:flex;flex-wrap:wrap;gap:10px;padding:14px 16px;background:#f8fafc;border-bottom:1.5px solid var(--border);align-items:flex-end}
.rp-room-fl{display:flex;flex-direction:column;gap:4px;flex:1;min-width:160px}
.rp-room-fl label{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px}
.rp-room-fl select,.rp-room-fl input{padding:8px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:#fff;color:var(--c1);width:100%;box-sizing:border-box}
.rp-room-fl select:focus,.rp-room-fl input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.rp-btn{padding:9px 18px;border:none;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap}
.rp-btn-p{background:#4338ca;color:#fff}.rp-btn-p:hover{background:#3730a3}
.rp-btn-o{background:#fff;color:#4338ca;border:1.5px solid #4338ca}.rp-btn-o:hover{background:#eef2ff}
.rp-btn-g{background:#fff;color:var(--c3);border:1.5px solid var(--border)}.rp-btn-g:hover{border-color:#4338ca;color:#4338ca}
.rp-btn-pr{background:#059669;color:#fff}.rp-btn-pr:hover{background:#047857}

/* ── Report Table ── */
.rp-report-hdr{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.rp-report-title{font-size:14px;font-weight:700;color:var(--c1)}
.rp-report-sub{font-size:11px;color:var(--c3);margin-top:2px}
.rp-tw{overflow-x:auto}
.rp-t{width:100%;border-collapse:collapse;font-size:11px}
.rp-t th{background:#f8fafc;padding:9px 10px;text-align:left;font-weight:700;color:var(--c3);font-size:9.5px;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid var(--border);white-space:nowrap;position:sticky;top:0;z-index:1}
.rp-t td{padding:8px 10px;border-bottom:1px solid #f1f5f9;color:var(--c1);vertical-align:middle}
.rp-t tbody tr:hover td{background:#f5f5ff}
.rp-t tbody tr:nth-child(even) td{background:#fafbff}
.rp-t tbody tr:nth-child(even):hover td{background:#f0f0ff}
.rp-t td.rp-code{font-family:'Courier New',monospace;font-size:10px;color:#334155;font-weight:600;background:#f1f5f9;border-radius:4px;padding:3px 6px;white-space:nowrap}
.rp-tfoot{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f8fafc;border-top:1.5px solid var(--border);font-size:11px;color:var(--c3);flex-wrap:wrap;gap:6px}

/* ── GHS Mini Diamonds in Table ── */
.rp-ghs-row{display:flex;gap:3px;flex-wrap:wrap;align-items:center}
.rp-ghs-d{width:22px;height:22px;position:relative;flex-shrink:0;cursor:pointer}
.rp-ghs-d-inner{position:absolute;inset:2px;transform:rotate(45deg);border-radius:2px;border:1.5px solid #dc2626;background:#fff;display:flex;align-items:center;justify-content:center;font-size:9px}
.rp-ghs-d-inner i{transform:rotate(-45deg);color:#dc2626}
.rp-ghs-flammable .rp-ghs-d-inner{background:#fee2e2;border-color:#dc2626}
.rp-ghs-toxic .rp-ghs-d-inner{background:#fee2e2;border-color:#991b1b;color:#991b1b}
.rp-ghs-corrosive .rp-ghs-d-inner{background:#f3e8ff;border-color:#7c3aed}
.rp-ghs-oxidizing .rp-ghs-d-inner{background:#fef3c7;border-color:#d97706}
.rp-ghs-health_hazard .rp-ghs-d-inner,.rp-ghs-harmful .rp-ghs-d-inner{background:#fee2e2;border-color:#dc2626}
.rp-ghs-irritant .rp-ghs-d-inner{background:#fef9c3;border-color:#ca8a04}
.rp-ghs-environmental .rp-ghs-d-inner{background:#dcfce7;border-color:#16a34a}
.rp-ghs-explosive .rp-ghs-d-inner{background:#fef3c7;border-color:#ea580c}
.rp-ghs-compressed_gas .rp-ghs-d-inner{background:#dbeafe;border-color:#2563eb}

/* ── Signal Word ── */
.rp-signal-d{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;padding:2px 7px;border-radius:5px;font-size:9px;font-weight:800;text-transform:uppercase}
.rp-signal-w{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:2px 7px;border-radius:5px;font-size:9px;font-weight:800;text-transform:uppercase}

/* ── Hazard text ── */
.rp-hazard-text{font-size:10px;color:#374151;line-height:1.5;max-width:280px}

/* ── Modals ── */
.rp-mo{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);justify-content:center;align-items:center;padding:16px}
.rp-mo.show{display:flex;animation:rpFd .15s ease}
.rp-modal{background:#fff;border-radius:var(--rp-r);width:100%;max-width:520px;max-height:90vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);display:flex;flex-direction:column}
.rp-mhdr{display:flex;align-items:center;gap:10px;padding:14px 18px;background:#f8fafc;border-bottom:1.5px solid var(--border);flex-shrink:0}
.rp-mhdr i{color:#6366f1}
.rp-mhdr h4{margin:0;font-size:14px;font-weight:700;flex:1;color:var(--c1)}
.rp-mclose{width:28px;height:28px;border-radius:8px;border:1.5px solid var(--border);background:#fff;color:var(--c3);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.12s}
.rp-mclose:hover{background:#fef2f2;border-color:#fecaca;color:#dc2626}
.rp-mbody{flex:1;overflow-y:auto;padding:16px 18px}
.rp-mhero{display:flex;align-items:center;gap:14px;padding:14px;border-radius:12px;margin-bottom:14px;border:1.5px solid transparent}
.rp-mhero.danger{background:#fef2f2;border-color:#fecaca}
.rp-mhero.warning{background:#fff7ed;border-color:#fed7aa}
.rp-mhero.info{background:#ede9fe;border-color:#ddd6fe}
.rp-mhero-ico{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.rp-mhero.danger .rp-mhero-ico{background:#fee2e2;color:#dc2626}
.rp-mhero.warning .rp-mhero-ico{background:#fef3c7;color:#d97706}
.rp-mhero.info .rp-mhero-ico{background:#ede9fe;color:#6d28d9}
.rp-mhero-txt h5{margin:0 0 2px;font-size:14px;font-weight:700}
.rp-mhero.danger .rp-mhero-txt h5{color:#dc2626}
.rp-mhero.warning .rp-mhero-txt h5{color:#c2410c}
.rp-mhero.info .rp-mhero-txt h5{color:#4338ca}
.rp-mhero-txt p{margin:0;font-size:11px;color:var(--c3)}
.rp-mgrid{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1.5px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:12px}
.rp-mditem{padding:12px 14px;border-bottom:1px solid #f8fafc}
.rp-mditem:nth-child(odd){border-right:1px solid #f8fafc}
.rp-mditem.full{grid-column:1/-1;border-right:none}
.rp-mdlbl{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.rp-mdval{font-size:13px;font-weight:600;color:var(--c1);word-break:break-all}
.rp-mbc{display:inline-flex;align-items:center;gap:7px;background:#f8fafc;border:1.5px dashed var(--border);border-radius:8px;padding:6px 12px;font-family:'Courier New',monospace;font-size:12px;font-weight:600;color:#334155}
.rp-gauge{width:76px;height:76px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 6px;flex-shrink:0}
.rp-gauge-inner{width:56px;height:56px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800}

/* ── Empty / Loading ── */
.rp-empty{text-align:center;padding:36px 20px;color:var(--c3)}
.rp-empty i{font-size:32px;display:block;margin-bottom:10px;opacity:.25}
.rp-empty p{font-size:12px;margin:4px 0 0}
.rp-ld{display:flex;align-items:center;justify-content:center;gap:8px;padding:28px;color:var(--c3);font-size:13px}
.rp-ld i{animation:rpspin .8s linear infinite}
@keyframes rpspin{to{transform:rotate(360deg)}}

/* ── Print ── */
@media print {
    body > *:not(#printArea){display:none!important}
    #printArea{display:block!important;position:static}
    .no-print{display:none!important}
    .rp-t{font-size:9px}
    .rp-t th{font-size:8px}
    .rp-ghs-d{width:18px;height:18px}
    .rp-ghs-d-inner{font-size:7px}
    @page{size:A4 landscape;margin:12mm}
}
#printArea{display:none}

/* ── Room View Toggle Bar ── */
.rrv-bar{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f8fafc;border-bottom:1.5px solid var(--border);flex-wrap:wrap;gap:10px}
.rrv-toggle{display:inline-flex;background:#f1f5f9;border-radius:9px;padding:3px;gap:2px}
.rrv-btn{padding:7px 15px;border:none;background:transparent;border-radius:7px;font-size:12px;font-weight:600;color:var(--c3);cursor:pointer;font-family:inherit;transition:all .12s;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.rrv-btn:hover{color:var(--c1)}
.rrv-btn.on{background:#fff;color:#4338ca;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.rrv-btn i{font-size:11px}

/* ── Overview Stats Row (all-rooms) ── */
.rrv-overview{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:8px;padding:12px 16px;border-bottom:1px solid var(--border);background:#fff}
.rrv-oc{background:#f8fafc;border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;text-align:center}
.rrv-oc .v{font-size:20px;font-weight:900;line-height:1}
.rrv-oc .lb{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.4px;margin-top:3px}

/* ── Room Cards (by-room view) ── */
#rrViewContent{padding:12px 14px 4px}
.rrc{background:#fff;border:1.5px solid var(--border);border-radius:var(--rp-r);overflow:hidden;margin-bottom:10px;box-shadow:var(--rp-sh);transition:box-shadow .15s}
.rrc:hover{box-shadow:var(--rp-shm)}
.rrc-hdr{display:flex;align-items:center;gap:8px;padding:11px 14px;background:linear-gradient(90deg,#f5f3ff,#fafbff);border-bottom:1.5px solid #e0e7ff;cursor:pointer;user-select:none;flex-wrap:wrap;gap:8px}
.rrc-hdr:hover{background:linear-gradient(90deg,#ede9fe,#f5f3ff)}
.rrc-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.rrc-name{font-size:13px;font-weight:700;color:#3730a3}
.rrc-code{font-family:'Courier New',monospace;font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;flex-shrink:0}
.rrc-bldg{font-size:10px;color:var(--c3);display:flex;align-items:center;gap:3px}
.rrc-bldg i{font-size:9px}
.rrc-exp-badge{font-size:9px;font-weight:700;padding:2px 7px;border-radius:7px;background:#fef2f2;color:#dc2626;display:inline-flex;align-items:center;gap:3px;flex-shrink:0}
.rrc-cost{font-size:11px;font-weight:700;color:#059669;flex-shrink:0}
.rrc-cnt{font-size:10px;font-weight:700;padding:3px 10px;border-radius:10px;color:#fff;white-space:nowrap;flex-shrink:0}
.rrc-arr{font-size:11px;color:var(--c3);transition:transform .2s;flex-shrink:0}
.rrc.collapsed .rrc-arr{transform:rotate(-90deg)}
.rrc-body{overflow:hidden}
.rrc.collapsed .rrc-body{display:none}
.rrc-foot{display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:8px 14px;background:#f8fafc;border-top:1px solid var(--border);font-size:10px;color:var(--c3)}
.rrc-foot strong{color:var(--c1)}

/* ── Combined view group-header row ── */
.rp-t tr.rrc-ghr td{background:linear-gradient(90deg,#eef2ff,#f8fafc)!important;padding:7px 12px!important;border-top:2px solid #c7d2fe!important}
.rrc-ghr-inner{display:flex;align-items:center;gap:8px}
.rrc-gh-code{font-family:'Courier New',monospace;font-size:10px;color:#6366f1;background:#e0e7ff;padding:1px 6px;border-radius:4px}
.rrc-gh-bldg{font-size:10px;color:var(--c3)}
.rrc-gh-cnt{font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:#4338ca;color:#fff;margin-left:auto}

/* ── Responsive ── */
@media(max-width:768px){
    .rp-hero{padding:16px 18px;gap:12px}
    .rp-hero-ic{width:44px;height:44px;font-size:18px}
    .rp-hero-info h2{font-size:16px}
    .rp-hero-meta{gap:14px}
    .rp-hero-c .v{font-size:20px}
}
@media(max-width:600px){
    .rp-hero-meta{display:none}
    .rp-sum{grid-template-columns:repeat(2,1fr)}
    .rp-comp{grid-template-columns:1fr 1fr}
    .rp-mgrid{grid-template-columns:1fr}
    .rp-mditem:nth-child(odd){border-right:none}
    .rp-chart-tall{height:260px}
    .rp-chart-std{height:200px}
}
</style>

<!-- ═══════ Hero ═══════ -->
<div class="rp-hero">
    <div class="rp-hero-ic"><i class="fas fa-chart-bar"></i></div>
    <div class="rp-hero-info">
        <h2><?php echo __('reports_title') ?></h2>
        <p><?php echo $TH ? 'ภาพรวมคลังสารเคมี รายงานการใช้งาน สต็อกต่ำ และใกล้หมดอายุ' : 'Chemical inventory overview, usage reports, low stock and expiring alerts' ?></p>
    </div>
    <div class="rp-hero-meta">
        <div class="rp-hero-c"><div class="v" id="heroChemicals">—</div><div class="lb"><?php echo $TH?'สารเคมี':'Chemicals'?></div></div>
        <div class="rp-hero-c"><div class="v" id="heroContainers">—</div><div class="lb"><?php echo $TH?'ภาชนะ':'Containers'?></div></div>
        <div class="rp-hero-c"><div class="v" id="heroAlert">—</div><div class="lb"><?php echo $TH?'แจ้งเตือน':'Alerts'?></div></div>
    </div>
</div>

<!-- ═══════ Stats ═══════ -->
<div class="rp-sum">
    <div class="rp-sc" onclick="showStatDetail('chemicals')">
        <div class="rp-si" style="background:#ede9fe;color:#6d28d9"><i class="fas fa-flask"></i></div>
        <div style="flex:1;min-width:0"><div class="rp-sv" id="sChemicals">—</div><div class="rp-sl"><?php echo __('stat_total_chemicals')?></div></div>
        <i class="fas fa-chevron-right rp-sc-arrow"></i>
    </div>
    <div class="rp-sc" onclick="showStatDetail('containers')">
        <div class="rp-si" style="background:#dcfce7;color:#15803d"><i class="fas fa-box"></i></div>
        <div style="flex:1;min-width:0"><div class="rp-sv" id="sContainers">—</div><div class="rp-sl"><?php echo __('stat_active_containers')?></div></div>
        <i class="fas fa-chevron-right rp-sc-arrow"></i>
    </div>
    <div class="rp-sc" onclick="showStatDetail('expired')">
        <div class="rp-si" style="background:#fee2e2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
        <div style="flex:1;min-width:0"><div class="rp-sv" id="sExpired">—</div><div class="rp-sl"><?php echo __('stat_expired_containers')?></div></div>
        <i class="fas fa-chevron-right rp-sc-arrow"></i>
    </div>
    <div class="rp-sc" onclick="showStatDetail('expiring')">
        <div class="rp-si" style="background:#fef3c7;color:#d97706"><i class="fas fa-clock"></i></div>
        <div style="flex:1;min-width:0"><div class="rp-sv" id="sExpiring">—</div><div class="rp-sl"><?php echo __('stat_expiring_soon')?></div></div>
        <i class="fas fa-chevron-right rp-sc-arrow"></i>
    </div>
    <div class="rp-sc" onclick="showStatDetail('users')">
        <div class="rp-si" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-users"></i></div>
        <div style="flex:1;min-width:0"><div class="rp-sv" id="sUsers">—</div><div class="rp-sl"><?php echo __('stat_total_users')?></div></div>
        <i class="fas fa-chevron-right rp-sc-arrow"></i>
    </div>
    <div class="rp-sc" onclick="showStatDetail('labs')">
        <div class="rp-si" style="background:#dbeafe;color:#2563eb"><i class="fas fa-building"></i></div>
        <div style="flex:1;min-width:0"><div class="rp-sv" id="sLabs">—</div><div class="rp-sl"><?php echo __('stat_total_labs')?></div></div>
        <i class="fas fa-chevron-right rp-sc-arrow"></i>
    </div>
</div>

<!-- ═══════ Tabs ═══════ -->
<div class="rp-tabs">
    <div class="rp-tab-wrap">
        <button class="rp-tab on"  data-tab="overview"><i class="fas fa-chart-pie"></i> <?php echo __('reports_tab_overview')?></button>
        <button class="rp-tab"     data-tab="labs"><i class="fas fa-building"></i> <?php echo __('reports_tab_labs')?></button>
        <button class="rp-tab"     data-tab="expiring"><i class="fas fa-clock"></i> <?php echo __('reports_tab_expiring')?> <span class="bg" id="tabExpCount" style="display:none"></span></button>
        <button class="rp-tab"     data-tab="lowstock"><i class="fas fa-exclamation-triangle"></i> <?php echo __('reports_tab_low_stock')?> <span class="bg" id="tabLsCount" style="display:none"></span></button>
        <button class="rp-tab"     data-tab="movement"><i class="fas fa-exchange-alt"></i> ความเคลื่อนไหวสาร</button>
        <button class="rp-tab"     data-tab="cost"><i class="fas fa-coins"></i> ค่าใช้จ่าย</button>
        <button class="rp-tab"     data-tab="roomreport"><i class="fas fa-print"></i> รายงานขวดสาร</button>
    </div>
</div>

<!-- ═══════ Overview ═══════ -->
<div class="rp-panel on" id="panel-overview">
    <div class="rp-g2">
        <div class="rp-card"><div class="rp-card-hdr"><i class="fas fa-building"></i><h5><?php echo __('reports_stock_by_lab')?></h5></div><div class="rp-card-body"><div class="rp-chart rp-chart-std"><canvas id="labChart"></canvas></div></div></div>
        <div class="rp-card"><div class="rp-card-hdr"><i class="fas fa-exchange-alt"></i><h5><?php echo __('reports_borrow_activity')?></h5></div><div class="rp-card-body"><div class="rp-chart rp-chart-std"><canvas id="borrowChart"></canvas></div></div></div>
    </div>
    <div class="rp-g2">
        <div class="rp-card"><div class="rp-card-hdr"><i class="fas fa-shield-alt"></i><h5><?php echo __('reports_compliance')?></h5></div><div class="rp-card-body" id="complianceContent"><div class="rp-ld"><i class="fas fa-circle-notch"></i></div></div></div>
        <div class="rp-card"><div class="rp-card-hdr"><i class="fas fa-chart-area"></i><h5><?php echo __('reports_usage_trend')?></h5></div><div class="rp-card-body"><div class="rp-chart rp-chart-std"><canvas id="trendChart"></canvas></div></div></div>
    </div>
</div>

<!-- ═══════ Labs ═══════ -->
<div class="rp-panel" id="panel-labs">
    <div class="rp-card">
        <div class="rp-card-hdr"><i class="fas fa-building"></i><h5><?php echo __('reports_lab_performance')?></h5><span class="rp-cnt" id="labCount" style="background:#ede9fe;color:#6d28d9"></span></div>
        <div class="rp-card-body np" id="labTableWrap"><div class="rp-ld"><i class="fas fa-circle-notch"></i></div></div>
    </div>
</div>

<!-- ═══════ Expiring ═══════ -->
<div class="rp-panel" id="panel-expiring">
    <div class="rp-card">
        <div class="rp-card-hdr"><i class="fas fa-clock" style="color:#d97706"></i><h5><?php echo __('reports_expiring_items')?></h5><span class="rp-cnt" id="expiringCount" style="background:#fef3c7;color:#a16207"></span></div>
        <div class="rp-card-body np" id="expiringTableWrap"><div class="rp-ld"><i class="fas fa-circle-notch"></i></div></div>
    </div>
</div>

<!-- ═══════ Low Stock ═══════ -->
<div class="rp-panel" id="panel-lowstock">
    <div class="rp-card">
        <div class="rp-card-hdr"><i class="fas fa-exclamation-triangle" style="color:#dc2626"></i><h5><?php echo __('reports_low_stock_items')?></h5><span class="rp-cnt" id="lowStockCount" style="background:#fee2e2;color:#dc2626"></span></div>
        <div class="rp-card-body np" id="lowStockTableWrap"><div class="rp-ld"><i class="fas fa-circle-notch"></i></div></div>
    </div>
</div>

<!-- ═══════ Movement Chart ═══════ -->
<div class="rp-panel" id="panel-movement">
    <div class="rp-card">
        <div class="rp-chart-tb">
            <div>
                <div class="rp-chart-title"><i class="fas fa-exchange-alt" style="color:#6366f1;margin-right:6px"></i>ความเคลื่อนไหวสารเคมี แยกตามปีงบประมาณ</div>
                <div class="rp-chart-sub">นำเข้ารวม · Liq. N2 · สารเคมีนำเข้า · สารเคมีคงเหลือ (หน่วย: kg)</div>
            </div>
            <div class="rp-fy-filter">
                <label>แสดง:</label>
                <select id="movYears" onchange="renderMovement()">
                    <option value="0">ทุกปี</option>
                    <option value="5" selected>5 ปีล่าสุด</option>
                    <option value="3">3 ปีล่าสุด</option>
                </select>
            </div>
        </div>
        <div class="rp-card-body">
            <div class="rp-chart rp-chart-tall"><canvas id="movChart"></canvas></div>
        </div>
        <div class="rp-tfoot" id="movSummary"></div>
    </div>
</div>

<!-- ═══════ Cost Chart ═══════ -->
<div class="rp-panel" id="panel-cost">

    <!-- Stat cards (filled by JS) -->
    <div id="costStatRow" class="rp-sum" style="margin-bottom:14px"></div>

    <!-- Unified chart card with 3-view toggle -->
    <div class="rp-card">
        <div class="rp-chart-tb" style="flex-wrap:wrap;gap:10px;align-items:flex-start">
            <div style="display:flex;flex-direction:column;gap:5px;flex:1;min-width:0">
                <div class="rrv-toggle">
                    <button class="rrv-btn on" data-cview="yearly"   onclick="setCostView('yearly')"><i class="fas fa-chart-bar"></i> แนวโน้มตามปี</button>
                    <button class="rrv-btn"    data-cview="chemical" onclick="setCostView('chemical')"><i class="fas fa-flask"></i> แยกตามสาร</button>
                    <button class="rrv-btn"    data-cview="room"     onclick="setCostView('room')"><i class="fas fa-building"></i> แยกตามห้อง</button>
                </div>
                <div id="costSubLabel" class="rp-chart-sub" style="padding-left:3px"></div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;align-items:center">
                <div class="rp-fy-filter" id="costYearFilter">
                    <label>แสดง:</label>
                    <select id="costYears" onchange="renderCostView()">
                        <option value="0">ทุกปี</option>
                        <option value="5" selected>5 ปีล่าสุด</option>
                        <option value="3">3 ปีล่าสุด</option>
                    </select>
                </div>
                <div class="rp-fy-filter" id="costTopFilter" style="display:none">
                    <label>Top:</label>
                    <select id="costTopN" onchange="renderCostView()">
                        <option value="10">10 รายการ</option>
                        <option value="15" selected>15 รายการ</option>
                        <option value="20">20 รายการ</option>
                        <option value="30">30 รายการ</option>
                    </select>
                </div>
            </div>
        </div>
        <div id="costChartWrap" class="rp-card-body">
            <div class="rp-ld"><i class="fas fa-circle-notch"></i> กำลังโหลด...</div>
        </div>
        <div class="rp-tfoot" id="costSummary"></div>
    </div>

</div>

<!-- ═══════ Room Report ═══════ -->
<div class="rp-panel" id="panel-roomreport">
    <div class="rp-card">
        <div class="rp-room-filter">
            <div class="rp-room-fl" style="flex:2;min-width:220px">
                <label><i class="fas fa-door-open"></i> ห้องปฏิบัติการ</label>
                <select id="rrRoom">
                    <option value="">— กำลังโหลด... —</option>
                    <option value="0">ทุกห้อง (รวมทั้งหมด)</option>
                </select>
            </div>
            <div class="rp-room-fl" style="flex:0 0 110px">
                <label><i class="fas fa-calendar-alt"></i> ภาคการศึกษา</label>
                <select id="rrSemester">
                    <option value="0">ทั้งหมด</option>
                    <option value="1">ภาค 1</option>
                    <option value="2" selected>ภาค 2</option>
                </select>
            </div>
            <div class="rp-room-fl" style="flex:0 0 110px">
                <label><i class="fas fa-calendar"></i> ปีการศึกษา (พ.ศ.)</label>
                <input type="number" id="rrYear" value="<?php echo date('Y')+543 ?>" min="2560" max="2580" placeholder="2568">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;flex-shrink:0;padding-bottom:0">
                <button class="rp-btn rp-btn-p" onclick="loadRoomReport()"><i class="fas fa-search"></i> สร้างรายงาน</button>
                <button class="rp-btn rp-btn-pr" onclick="printReport()" id="btnPrint" style="display:none"><i class="fas fa-print"></i> พิมพ์</button>
            </div>
        </div>

        <div id="rrResult">
            <div class="rp-empty"><i class="fas fa-file-alt"></i><p>เลือกห้องและกด "สร้างรายงาน"</p></div>
        </div>
    </div>
</div>

<!-- ═══════ Modals ═══════ -->
<div class="rp-mo" id="moExp" onclick="closeMo('moExp',event)">
    <div class="rp-modal" onclick="event.stopPropagation()">
        <div class="rp-mhdr"><i class="fas fa-clock"></i><h4 id="moExpTitle"><?php echo __('details')?></h4><button class="rp-mclose" onclick="closeMo('moExp')">&times;</button></div>
        <div class="rp-mbody" id="moExpBody"></div>
    </div>
</div>
<div class="rp-mo" id="moLs" onclick="closeMo('moLs',event)">
    <div class="rp-modal" onclick="event.stopPropagation()">
        <div class="rp-mhdr"><i class="fas fa-exclamation-triangle" style="color:#dc2626"></i><h4 id="moLsTitle"><?php echo __('details')?></h4><button class="rp-mclose" onclick="closeMo('moLs')">&times;</button></div>
        <div class="rp-mbody" id="moLsBody"></div>
    </div>
</div>

<!-- Print area (hidden, filled when printing) -->
<div id="printArea"></div>

<!-- ═══════ Stat Detail Modal ═══════ -->
<div class="rp-sd-overlay" id="rpStatModal" onclick="if(event.target===this)closeStatModal()">
    <div class="rp-sd-modal">
        <div class="rp-sd-hdr">
            <div class="rp-sd-hdr-ic" id="rsmHdrIc"></div>
            <div style="flex:1;min-width:0">
                <div class="rp-sd-title" id="rsmTitle"></div>
                <div class="rp-sd-sub" id="rsmSub"></div>
            </div>
            <button class="rp-sd-close" onclick="closeStatModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="rp-sd-body" id="rsmBody"></div>
        <div class="rp-sd-foot">
            <button class="rp-sd-done" onclick="closeStatModal()"><i class="fas fa-check" style="margin-right:5px"></i>ปิด</button>
        </div>
    </div>
</div>

<?php Layout::endContent(); ?>
<script>
const TH = <?php echo json_encode($TH); ?>;
const GC = 'rgba(0,0,0,.05)', TC = '#64748b';

Chart.register(ChartDataLabels);
Chart.defaults.color = TC;
Chart.defaults.borderColor = GC;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.pointStyle = 'circle';

let expItems = [], lsItems = [];
let movData = [], costData = [];
let dashData = {};
let movChartInst = null, costChartInst = null;

// ── Tabs ──
document.querySelectorAll('.rp-tab').forEach(t => {
    t.addEventListener('click', () => {
        document.querySelectorAll('.rp-tab').forEach(x => x.classList.remove('on'));
        document.querySelectorAll('.rp-panel').forEach(x => x.classList.remove('on'));
        t.classList.add('on');
        document.getElementById('panel-' + t.dataset.tab).classList.add('on');
        if (t.dataset.tab === 'movement' && movData.length === 0) loadMovement();
        if (t.dataset.tab === 'cost' && costData.length === 0) loadCost();
        if (t.dataset.tab === 'roomreport' && !g('rrRoom').dataset.loaded) loadRoomsList();
    });
});

function g(id) { return document.getElementById(id); }
function esc(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
function num(v) { return Number(v || 0).toLocaleString(); }
function numK(v) { v = Number(v || 0); return v >= 1000 ? (v / 1000).toFixed(1) + 'K' : v.toLocaleString(); }
function fmtDate(s) { if (!s) return '–'; const d = new Date(s); return d.toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: '2-digit' }); }
function fmtNum2(v) { return Number(v || 0).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }

async function apiFetch(url, opts) {
    const tk = document.cookie.split('; ').find(c => c.startsWith('auth_token='))?.split('=')[1];
    const h = { 'Content-Type': 'application/json' };
    if (tk) h['Authorization'] = 'Bearer ' + tk;
    const r = await fetch(url, { headers: h, ...opts });
    return r.json();
}

/* ════════════════════════════════════════════
   STAT DETAIL MODAL
════════════════════════════════════════════ */
const SD_CFG = {
    chemicals: { icon:'fas fa-flask',        bg:'#ede9fe', color:'#6d28d9', title:'สารเคมีทั้งหมด' },
    containers:{ icon:'fas fa-box',          bg:'#dcfce7', color:'#15803d', title:'ภาชนะที่ใช้งาน' },
    expired:   { icon:'fas fa-times-circle', bg:'#fee2e2', color:'#dc2626', title:'ภาชนะหมดอายุ' },
    expiring:  { icon:'fas fa-clock',        bg:'#fef3c7', color:'#d97706', title:'ใกล้หมดอายุ (30 วัน)' },
    users:     { icon:'fas fa-users',        bg:'#ede9fe', color:'#7c3aed', title:'ผู้ใช้งาน' },
    labs:      { icon:'fas fa-building',     bg:'#dbeafe', color:'#2563eb', title:'ห้องปฏิบัติการ' },
};

function showStatDetail(type) {
    const cfg = SD_CFG[type]; if (!cfg) return;
    const s = dashData.summary || dashData.stats || dashData;

    const subs = {
        chemicals:  () => `${num(s.total_chemicals  || 0)} ชนิดสารเคมีในระบบ`,
        containers: () => `${num(s.active_containers || 0)} ภาชนะที่กำลังใช้งาน`,
        expired:    () => { const n=(dashData.expiring_soon||[]).filter(i=>parseInt(i.days_until_expiry)<0).length; return `${num(n)} ภาชนะที่หมดอายุแล้ว`; },
        expiring:   () => `${num(expItems.length)} รายการที่ต้องติดตาม`,
        users:      () => `${num(s.total_users || 0)} ผู้ใช้งานทั้งหมดในระบบ`,
        labs:       () => `${num(s.total_labs  || 0)} ห้องปฏิบัติการ`,
    };
    const renders = { chemicals: renderSDChemicals, containers: renderSDContainers, expired: renderSDExpired, expiring: renderSDExpiring, users: renderSDUsers, labs: renderSDLabs };

    g('rsmHdrIc').style.cssText = `background:${cfg.bg};color:${cfg.color}`;
    g('rsmHdrIc').innerHTML = `<i class="${cfg.icon}"></i>`;
    g('rsmTitle').textContent = cfg.title;
    g('rsmSub').textContent = (subs[type] || (() => ''))();
    g('rsmBody').innerHTML = (renders[type] || (() => ''))();

    const modal = g('rpStatModal');
    modal.style.display = 'flex';
    requestAnimationFrame(() => modal.classList.add('show'));
    document.body.style.overflow = 'hidden';
}

function closeStatModal() {
    const modal = g('rpStatModal');
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 200);
    document.body.style.overflow = '';
}

/* ── KPI row builder ── */
function sdKpi(items) {
    return `<div class="rp-sd-kpi">${items.map(k=>`<div class="rp-sd-kpi-c"><div class="rp-sd-kpi-v" style="color:${k.color||'var(--c1)'}">${k.val}</div><div class="rp-sd-kpi-l">${k.lbl}</div></div>`).join('')}</div>`;
}

/* ── 1. Chemicals ── */
function renderSDChemicals() {
    const labs = dashData.lab_performance || [];
    const s = dashData.summary || dashData.stats || dashData;
    const total = parseInt(s.total_chemicals || 0);
    const totalC = parseInt(s.active_containers || 0);
    const totalU = labs.reduce((a,l) => a + (parseInt(l.user_count)||0), 0);
    if (!labs.length) return `${sdKpi([{val:num(total),lbl:'ชนิดสารเคมี',color:'#6d28d9'},{val:num(totalC),lbl:'ภาชนะรวม',color:'#15803d'},{val:num(totalU),lbl:'ผู้ใช้รวม',color:'#7c3aed'}])}<div class="rp-sd-empty"><i class="fas fa-flask"></i><p>ยังไม่มีข้อมูลรายละเอียด</p></div>`;
    const maxC = Math.max(...labs.map(l => parseInt(l.container_count)||0), 1);
    const sorted = [...labs].sort((a,b) => (parseInt(b.container_count)||0) - (parseInt(a.container_count)||0));
    const rows = sorted.map((l, i) => {
        const c = parseInt(l.container_count) || 0;
        const pct = Math.round(c / maxC * 100);
        const rankCls = i === 0 ? 'r1' : i === 1 ? 'r2' : i === 2 ? 'r3' : '';
        return `<div class="rp-sd-row">
            <div class="rp-sd-rank ${rankCls}">${i+1}</div>
            <div class="rp-sd-info"><div class="rp-sd-name">${esc(l.name||'–')}</div><div class="rp-sd-meta"><i class="fas fa-users" style="margin-right:3px"></i>${l.user_count||0} คน · <i class="fas fa-exchange-alt" style="margin:0 3px"></i>${l.borrow_requests||0} ยืม</div></div>
            <div class="rp-sd-bar-wrap"><div class="rp-sd-bar"><div class="rp-sd-bar-fill" style="width:${pct}%;background:#6d28d9"></div></div></div>
            <div class="rp-sd-val"><div class="rp-sd-val-n">${num(c)}</div><div class="rp-sd-val-u">ขวด</div></div>
        </div>`;
    }).join('');
    return sdKpi([{val:num(total),lbl:'ชนิดสาร',color:'#6d28d9'},{val:num(totalC),lbl:'ภาชนะรวม',color:'#15803d'},{val:num(labs.length),lbl:'ห้องปฏิบัติการ',color:'#2563eb'}]) +
        `<div class="rp-sd-section"><i class="fas fa-sort-amount-down" style="color:#6d28d9"></i>ห้องปฏิบัติการ — เรียงตามจำนวนขวด</div><div class="rp-sd-list">${rows}</div>`;
}

/* ── 2. Containers ── */
function renderSDContainers() {
    const labs = dashData.lab_performance || [];
    const s = dashData.summary || dashData.stats || dashData;
    const active = parseInt(s.active_containers || 0);
    const expired = parseInt(s.expired_containers || 0);
    const expiringSoon = expItems.length;
    if (!labs.length) return sdKpi([{val:num(active),lbl:'ใช้งาน',color:'#15803d'},{val:num(expired),lbl:'หมดอายุ',color:'#dc2626'},{val:num(expiringSoon),lbl:'ใกล้หมดอายุ',color:'#d97706'}]);
    const maxC = Math.max(...labs.map(l => parseInt(l.container_count)||0), 1);
    const sorted = [...labs].sort((a,b) => (parseInt(b.container_count)||0) - (parseInt(a.container_count)||0));
    const rows = sorted.map((l, i) => {
        const c = parseInt(l.container_count) || 0;
        const pct = Math.round(c / maxC * 100);
        const barColor = pct > 60 ? '#10b981' : pct > 25 ? '#f97316' : '#ef4444';
        const rankCls = i === 0 ? 'r1' : i === 1 ? 'r2' : i === 2 ? 'r3' : '';
        return `<div class="rp-sd-row">
            <div class="rp-sd-rank ${rankCls}">${i+1}</div>
            <div class="rp-sd-info"><div class="rp-sd-name">${esc(l.name||'–')}</div><div class="rp-sd-meta">${pct}% ของห้องสูงสุด</div></div>
            <div class="rp-sd-bar-wrap"><div class="rp-sd-bar"><div class="rp-sd-bar-fill" style="width:${pct}%;background:${barColor}"></div></div></div>
            <div class="rp-sd-val"><div class="rp-sd-val-n">${num(c)}</div><div class="rp-sd-val-u">ขวด</div></div>
        </div>`;
    }).join('');
    return sdKpi([{val:num(active),lbl:'ใช้งาน',color:'#15803d'},{val:num(expired),lbl:'หมดอายุ',color:'#dc2626'},{val:num(expiringSoon),lbl:'ใกล้หมดอายุ',color:'#d97706'}]) +
        `<div class="rp-sd-section"><i class="fas fa-sort-amount-down" style="color:#15803d"></i>การกระจายขวดสารเคมีตามห้อง</div><div class="rp-sd-list">${rows}</div>`;
}

/* ── 3. Expired ── */
function renderSDExpired() {
    const expired = (dashData.expiring_soon || []).filter(i => parseInt(i.days_until_expiry) < 0);
    if (!expired.length) return `<div class="rp-sd-empty"><i class="fas fa-check-circle" style="color:#10b981;opacity:1"></i><p>ไม่มีภาชนะหมดอายุ</p></div>`;
    const sorted = [...expired].sort((a,b) => (parseInt(a.days_until_expiry)||0) - (parseInt(b.days_until_expiry)||0));
    const grouped = {};
    sorted.forEach(item => { const lab = item.lab_name || '(ไม่ระบุ)'; if (!grouped[lab]) grouped[lab] = []; grouped[lab].push(item); });
    const totalLabs = Object.keys(grouped).length;
    let html = sdKpi([{val:num(sorted.length),lbl:'ขวดหมดอายุ',color:'#dc2626'},{val:num(totalLabs),lbl:'ห้องที่มีปัญหา',color:'#f97316'},{val:num(Math.max(...sorted.map(i=>Math.abs(parseInt(i.days_until_expiry)||0)))),lbl:'วันที่นานสุด',color:'#64748b'}]);
    Object.entries(grouped).forEach(([lab, items]) => {
        html += `<div class="rp-sd-section"><i class="fas fa-building" style="color:#dc2626"></i>${esc(lab)} (${items.length})</div>`;
        items.forEach(item => {
            const d = Math.abs(parseInt(item.days_until_expiry)||0);
            const qty = parseFloat(item.current_quantity)||0, unit = item.quantity_unit||'mL';
            const qStr = (qty>=1000&&unit==='mL') ? (qty/1000).toFixed(1)+' L' : qty.toFixed(0)+' '+unit;
            const owner = [item.owner_first,item.owner_last].filter(Boolean).join(' ')||'–';
            html += `<div class="rp-sd-alert-item">
                <div class="rp-sd-alert-ic" style="background:#fee2e2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
                <div class="rp-sd-alert-main">
                    <div class="rp-sd-alert-name">${esc(item.name)}</div>
                    <div class="rp-sd-alert-sub"><i class="fas fa-user" style="margin-right:3px"></i>${esc(owner)} · ${qStr}</div>
                </div>
                <span class="rp-sd-badge badge-danger">${d} วันแล้ว</span>
            </div>`;
        });
    });
    return html;
}

/* ── 4. Expiring soon ── */
function renderSDExpiring() {
    if (!expItems.length) return `<div class="rp-sd-empty"><i class="fas fa-check-circle" style="color:#10b981;opacity:1"></i><p>ไม่มีรายการใกล้หมดอายุ</p></div>`;
    const critical = expItems.filter(i => { const d=parseInt(i.days_until_expiry); return d>=0&&d<=7; });
    const warn     = expItems.filter(i => { const d=parseInt(i.days_until_expiry); return d>7&&d<=30; });
    const ok       = expItems.filter(i => { const d=parseInt(i.days_until_expiry); return d>30; });
    let html = sdKpi([
        {val:num(critical.length), lbl:'วิกฤต ≤7วัน',  color:'#dc2626'},
        {val:num(warn.length),     lbl:'เตือน ≤30วัน', color:'#d97706'},
        {val:num(ok.length),       lbl:'>30 วัน',       color:'#15803d'},
    ]);
    const buildGroup = (items, label, cls, bg, color, ico) => {
        if (!items.length) return '';
        const rows = items.map(item => {
            const d = parseInt(item.days_until_expiry)||0;
            const qty = parseFloat(item.current_quantity)||0, unit = item.quantity_unit||'mL';
            const qStr = (qty>=1000&&unit==='mL') ? (qty/1000).toFixed(1)+' L' : qty.toFixed(0)+' '+unit;
            const owner = [item.owner_first,item.owner_last].filter(Boolean).join(' ')||'–';
            return `<div class="rp-sd-alert-item">
                <div class="rp-sd-alert-ic" style="background:${bg};color:${color}"><i class="fas ${ico}"></i></div>
                <div class="rp-sd-alert-main">
                    <div class="rp-sd-alert-name">${esc(item.name)}</div>
                    <div class="rp-sd-alert-sub"><i class="fas fa-user" style="margin-right:3px"></i>${esc(owner)} · ${esc(item.lab_name||'–')} · ${qStr}</div>
                </div>
                <span class="rp-sd-badge ${cls}">${d}d</span>
            </div>`;
        }).join('');
        return `<div class="rp-sd-section" style="color:${color}"><i class="fas ${ico}" style="color:${color}"></i>${label} (${items.length})</div>${rows}`;
    };
    html += buildGroup(critical, 'วิกฤต — เหลือน้อยกว่า 7 วัน', 'badge-danger',  '#fee2e2', '#dc2626', 'fa-exclamation-triangle');
    html += buildGroup(warn,     'เตือน — เหลือน้อยกว่า 30 วัน', 'badge-warning', '#fff7ed', '#f97316', 'fa-clock');
    html += buildGroup(ok,       'ยังมีเวลาพอสมควร',              'badge-info',   '#ede9fe', '#6d28d9', 'fa-hourglass-half');
    return html;
}

/* ── 5. Users ── */
function renderSDUsers() {
    const labs = dashData.lab_performance || [];
    const s = dashData.summary || dashData.stats || dashData;
    const total = parseInt(s.total_users || 0);
    const labCount = parseInt(s.total_labs || 0);
    const totalBorrow = labs.reduce((a,l) => a + (parseInt(l.borrow_requests)||0), 0);
    if (!labs.length) return sdKpi([{val:num(total),lbl:'ผู้ใช้รวม',color:'#7c3aed'},{val:num(labCount),lbl:'ห้อง',color:'#2563eb'},{val:num(totalBorrow),lbl:'การยืมรวม',color:'#059669'}]);
    const maxU = Math.max(...labs.map(l => parseInt(l.user_count)||0), 1);
    const sorted = [...labs].sort((a,b) => (parseInt(b.user_count)||0) - (parseInt(a.user_count)||0));
    const rows = sorted.map((l, i) => {
        const u = parseInt(l.user_count)||0;
        const pct = Math.round(u / maxU * 100);
        const ov = parseInt(l.overdue_borrows)||0;
        const rankCls = i===0?'r1':i===1?'r2':i===2?'r3':'';
        return `<div class="rp-sd-row">
            <div class="rp-sd-rank ${rankCls}">${i+1}</div>
            <div class="rp-sd-info"><div class="rp-sd-name">${esc(l.name||'–')}</div><div class="rp-sd-meta"><i class="fas fa-box" style="margin-right:3px"></i>${l.container_count||0} ขวด${ov>0?` · <span style="color:#dc2626;font-weight:700">${ov} เกินกำหนด</span>`:''}</div></div>
            <div class="rp-sd-bar-wrap"><div class="rp-sd-bar"><div class="rp-sd-bar-fill" style="width:${pct}%;background:#7c3aed"></div></div></div>
            <div class="rp-sd-val"><div class="rp-sd-val-n">${num(u)}</div><div class="rp-sd-val-u">คน</div></div>
        </div>`;
    }).join('');
    return sdKpi([{val:num(total),lbl:'ผู้ใช้รวม',color:'#7c3aed'},{val:num(labs.length),lbl:'ห้องปฏิบัติการ',color:'#2563eb'},{val:num(totalBorrow),lbl:'การยืมรวม',color:'#059669'}]) +
        `<div class="rp-sd-section"><i class="fas fa-sort-amount-down" style="color:#7c3aed"></i>จำนวนสมาชิกแต่ละห้อง</div><div class="rp-sd-list">${rows}</div>`;
}

/* ── 6. Labs ── */
function renderSDLabs() {
    const labs = dashData.lab_performance || [];
    const s = dashData.summary || dashData.stats || dashData;
    const total = parseInt(s.total_labs || 0);
    const totalC = parseInt(s.active_containers || 0);
    const totalOv = labs.reduce((a,l) => a + (parseInt(l.overdue_borrows)||0), 0);
    const comp = dashData.compliance_status || {};
    if (!labs.length) return sdKpi([{val:num(total),lbl:'ห้องทั้งหมด',color:'#2563eb'},{val:num(totalC),lbl:'ขวดรวม',color:'#15803d'},{val:num(totalOv),lbl:'ยืมเกินกำหนด',color:'#dc2626'}]);
    const sorted = [...labs].sort((a,b) => (parseInt(b.container_count)||0) - (parseInt(a.container_count)||0));
    const rows = sorted.map((l, i) => {
        const c = parseInt(l.container_count)||0;
        const u = parseInt(l.user_count)||0;
        const b = parseInt(l.borrow_requests)||0;
        const ov = parseInt(l.overdue_borrows)||0;
        const rankCls = i===0?'r1':i===1?'r2':i===2?'r3':'';
        return `<div class="rp-sd-row">
            <div class="rp-sd-rank ${rankCls}">${i+1}</div>
            <div class="rp-sd-info">
                <div class="rp-sd-name">${esc(l.name||'–')}</div>
                <div class="rp-sd-meta"><i class="fas fa-box" style="margin-right:3px"></i>${num(c)} ขวด · <i class="fas fa-users" style="margin:0 3px"></i>${num(u)} คน · <i class="fas fa-exchange-alt" style="margin:0 3px"></i>${num(b)} ยืม</div>
            </div>
            ${ov>0 ? `<span class="rp-sd-badge badge-danger"><i class="fas fa-exclamation-triangle" style="margin-right:3px"></i>${ov} เกิน</span>` : `<span class="rp-sd-badge badge-ok"><i class="fas fa-check" style="margin-right:3px"></i>ปกติ</span>`}
        </div>`;
    }).join('');
    const compHtml = (comp.passed||comp.warnings||comp.failed) ? sdKpi([
        {val:num(comp.passed||0),  lbl:'ผ่าน',      color:'#059669'},
        {val:num(comp.warnings||0),lbl:'คำเตือน',   color:'#d97706'},
        {val:num(comp.failed||0),  lbl:'ไม่ผ่าน',   color:'#dc2626'},
    ]) : '';
    return sdKpi([{val:num(total),lbl:'ห้องทั้งหมด',color:'#2563eb'},{val:num(totalC),lbl:'ขวดรวม',color:'#15803d'},{val:num(totalOv),lbl:'ยืมเกินกำหนด',color:'#dc2626'}]) +
        (compHtml ? `<div class="rp-sd-section"><i class="fas fa-shield-alt" style="color:#2563eb"></i>สถานะ Compliance</div>${compHtml}` : '') +
        `<div class="rp-sd-section"><i class="fas fa-sort-amount-down" style="color:#2563eb"></i>รายละเอียดแต่ละห้อง</div><div class="rp-sd-list">${rows}</div>`;
}

// ── Load Main Dashboard ──
async function loadReport() {
    try {
        const d = await apiFetch('/v1/api/dashboard.php');
        if (!d.success || !d.data) throw new Error(d.error || 'Failed');
        const data = d.data, s = data.summary || data.stats || data;
        dashData = data;
        const map = { sChemicals: s.total_chemicals, sContainers: s.active_containers, sExpired: s.expired_containers || 0, sUsers: s.total_users, sLabs: s.total_labs };
        Object.entries(map).forEach(([id, val]) => { if (g(id) && val != null) g(id).textContent = num(val); });
        const expCount = (data.expiring_soon || []).length;
        g('sExpiring').textContent = num(expCount);
        if (g('heroChemicals')) g('heroChemicals').textContent = num(s.total_chemicals);
        if (g('heroContainers')) g('heroContainers').textContent = num(s.active_containers);
        if (g('heroAlert')) g('heroAlert').textContent = num(expCount + (data.low_stock || []).length);
        buildCharts(data);
        buildCompliance(data.compliance_status || {});
        buildLabs(data.lab_performance || []);
        buildExpiring(data.expiring_soon || []);
        buildLowStock(data.low_stock || []);
    } catch (e) { console.error(e); }
}

/* ── Overview Charts ── */
function buildCharts(d) {
    const labs = d.lab_performance || [];
    const ll = labs.map(l => (l.name || '').replace('ห้องปฏิบัติการ', '').trim().slice(0, 16));
    const dlOff = { display: false };

    new Chart(g('labChart'), { type: 'bar', data: { labels: ll, datasets: [
        { label: 'ภาชนะ', data: labs.map(l => parseInt(l.container_count) || 0), backgroundColor: 'rgba(99,102,241,.75)', borderRadius: 5, borderSkipped: false, barPercentage: .6 },
        { label: 'สมาชิก', data: labs.map(l => parseInt(l.user_count) || 0), backgroundColor: 'rgba(16,185,129,.65)', borderRadius: 5, borderSkipped: false, barPercentage: .6 },
    ] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top', labels: { padding: 10, font: { size: 11 } } }, datalabels: dlOff }, scales: { y: { beginAtZero: true, grid: { color: GC } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } } } });

    new Chart(g('borrowChart'), { type: 'bar', data: { labels: ll, datasets: [
        { label: 'รายการยืม', data: labs.map(l => parseInt(l.borrow_requests) || 0), backgroundColor: 'rgba(139,92,246,.75)', borderRadius: 5, borderSkipped: false, barPercentage: .5 },
        { label: 'เกินกำหนด', data: labs.map(l => parseInt(l.overdue_borrows) || 0), backgroundColor: 'rgba(239,68,68,.75)', borderRadius: 5, borderSkipped: false, barPercentage: .5 },
    ] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top', labels: { padding: 10, font: { size: 11 } } }, datalabels: dlOff }, scales: { x: { beginAtZero: true, grid: { color: GC } }, y: { grid: { display: false }, ticks: { font: { size: 10 } } } } } });

    const tr = d.usage_trend || [];
    new Chart(g('trendChart'), { type: 'line', data: { labels: tr.map(t => t.month) || ['ไม่มีข้อมูล'], datasets: [{ label: 'ธุรกรรม', data: tr.map(t => parseInt(t.transactions) || 0) || [0], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.08)', tension: .4, fill: true, pointRadius: 4, pointBackgroundColor: '#6366f1' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, datalabels: dlOff }, scales: { y: { beginAtZero: true, grid: { color: GC } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } } } });
}

/* ── Compliance ── */
function buildCompliance(c) {
    const p = parseInt(c.passed) || 0, w = parseInt(c.warnings) || 0, f = parseInt(c.failed) || 0;
    g('complianceContent').innerHTML = `<div class="rp-comp">
        <div class="rp-comp-card pass"><div class="rp-comp-v">${p}</div><div class="rp-comp-l"><?php echo __('reports_comp_passed')?></div></div>
        <div class="rp-comp-card warn"><div class="rp-comp-v">${w}</div><div class="rp-comp-l"><?php echo __('reports_comp_warnings')?></div></div>
        <div class="rp-comp-card fail"><div class="rp-comp-v">${f}</div><div class="rp-comp-l"><?php echo __('reports_comp_failed')?></div></div>
    </div>${!p && !w && !f ? `<div class="rp-empty" style="padding:16px 20px"><i class="fas fa-check-circle" style="color:#10b981;font-size:22px;opacity:1"></i><p><?php echo __('reports_no_compliance_issues')?></p></div>` : ''}`;
}

/* ── Labs ── */
function buildLabs(labs) {
    g('labCount').textContent = labs.length;
    if (!labs.length) { g('labTableWrap').innerHTML = `<div class="rp-empty"><i class="fas fa-building"></i><p><?php echo __('no_data')?></p></div>`; return; }
    const maxC = Math.max(...labs.map(l => parseInt(l.container_count) || 0), 1);
    let h = '<div class="rp-lab-list">';
    labs.forEach(lab => {
        const cnt = parseInt(lab.container_count) || 0, ov = parseInt(lab.overdue_borrows) || 0;
        const pct = Math.round(cnt / maxC * 100), cls = pct > 60 ? 'g' : pct > 25 ? 'o' : 'r';
        h += `<div class="rp-lab-row">
            <div class="rp-lab-ico"><i class="fas fa-building"></i></div>
            <div style="flex:1;min-width:0">
                <div class="rp-lab-name">${esc(lab.name)}</div>
                <div class="rp-lab-meta">
                    <span><i class="fas fa-box"></i> ${cnt} ภาชนะ</span>
                    <span><i class="fas fa-users"></i> ${lab.user_count || 0} คน</span>
                    <span><i class="fas fa-exchange-alt"></i> ${lab.borrow_requests || 0} ยืม</span>
                </div>
            </div>
            ${ov > 0 ? `<span class="rp-overdue"><i class="fas fa-exclamation-triangle"></i> ${ov} เกินกำหนด</span>` : ''}
            <div class="rp-track">
                <div class="rp-bar-wrap"><div class="rp-bar-fill ${cls}" style="width:${pct}%"></div></div>
                <div class="rp-pct">${pct}%</div>
            </div>
        </div>`;
    });
    g('labTableWrap').innerHTML = h + '</div>';
}

/* ── Expiring ── */
function buildExpiring(items) {
    expItems = items;
    const cnt = items.length;
    g('expiringCount').textContent = cnt;
    const tc = g('tabExpCount'); if (tc) { tc.textContent = cnt; tc.style.display = cnt ? '' : 'none'; }
    if (!items.length) { g('expiringTableWrap').innerHTML = `<div class="rp-empty"><i class="fas fa-check-circle" style="color:#10b981;opacity:1"></i><p><?php echo __('reports_no_expiring')?></p></div>`; return; }
    let h = '<div class="rp-ilist">';
    items.forEach((item, idx) => {
        const days = parseInt(item.days_until_expiry) || 0;
        const cls = days < 0 || days <= 7 ? 'danger' : days <= 30 ? 'warning' : 'info';
        const ico = days < 0 ? 'fa-exclamation-circle' : days <= 7 ? 'fa-exclamation-triangle' : 'fa-clock';
        const lbl = days < 0 ? `หมดอายุแล้ว (${Math.abs(days)}d)` : days <= 7 ? `วิกฤต ${days}d` : days <= 30 ? `เตือน ${days}d` : `${days}d เหลือ`;
        const qty = parseFloat(item.current_quantity) || 0, unit = item.quantity_unit || 'mL';
        const qtyStr = (qty >= 1000 && unit === 'mL') ? (qty / 1000).toFixed(1) + ' L' : qty.toFixed(0) + ' ' + unit;
        const owner = [item.owner_first, item.owner_last].filter(Boolean).join(' ') || '–';
        h += `<div class="rp-irow ${cls}" onclick="showExpDetail(${idx})">
            <div class="rp-irow-ico"><i class="fas ${ico}"></i></div>
            <div class="rp-irow-main"><div class="rp-irow-name">${esc(item.name)}</div>
                <div class="rp-irow-sub"><span><i class="fas fa-user"></i>${esc(owner)}</span><span><i class="fas fa-map-marker-alt"></i>${esc(item.lab_name || '–')}</span>${item.bottle_code ? `<span><i class="fas fa-barcode"></i>${esc(item.bottle_code)}</span>` : ''}</div></div>
            <div style="text-align:right;flex-shrink:0"><span class="rp-ibadge ${cls}">${lbl}</span><div style="font-size:11px;font-weight:700;color:var(--c1);margin-top:4px">${qtyStr}</div></div>
        </div>`;
    });
    g('expiringTableWrap').innerHTML = h + '</div>';
}

/* ── Low Stock ── */
function buildLowStock(items) {
    lsItems = items;
    const cnt = items.length;
    g('lowStockCount').textContent = cnt;
    const tc = g('tabLsCount'); if (tc) { tc.textContent = cnt; tc.style.display = cnt ? '' : 'none'; }
    if (!items.length) { g('lowStockTableWrap').innerHTML = `<div class="rp-empty"><i class="fas fa-check-circle" style="color:#10b981;opacity:1"></i><p><?php echo __('reports_no_low_stock')?></p></div>`; return; }
    let h = '<div class="rp-ilist">';
    items.forEach((item, idx) => {
        const pct = parseFloat(item.remaining_percentage) || 0;
        const cls = pct <= 5 ? 'danger' : 'warning';
        const qtyStr = (parseFloat(item.current_quantity) || 0) + ' ' + (item.quantity_unit || 'mL');
        const owner = [item.first_name, item.last_name].filter(Boolean).join(' ') || '–';
        const barFill = pct <= 5 ? 'r' : pct <= 15 ? 'o' : 'g';
        h += `<div class="rp-irow ${cls}" onclick="showLsDetail(${idx})">
            <div class="rp-irow-ico"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="rp-irow-main"><div class="rp-irow-name">${esc(item.name)}</div>
                <div class="rp-irow-sub"><span><i class="fas fa-user"></i>${esc(owner)}</span><span><i class="fas fa-map-marker-alt"></i>${esc(item.lab_name || '–')}</span>${item.cas_number ? `<span>CAS: ${esc(item.cas_number)}</span>` : ''}</div></div>
            <div style="min-width:100px;flex-shrink:0;text-align:right">
                <div class="rp-bar-wrap" style="width:90px;margin-left:auto;margin-bottom:4px"><div class="rp-bar-fill ${barFill}" style="width:${pct}%"></div></div>
                <div style="font-size:12px;font-weight:800;color:var(--c1)">${pct.toFixed(1)}%</div>
                <div style="font-size:10px;color:var(--c3)">${qtyStr}</div>
            </div>
        </div>`;
    });
    g('lowStockTableWrap').innerHTML = h + '</div>';
}

/* ════════════════════════════════════════════
   MOVEMENT CHART (a.jpg)
════════════════════════════════════════════ */
async function loadMovement() {
    g('movChart').parentElement.innerHTML = '<div class="rp-ld"><i class="fas fa-circle-notch"></i> กำลังโหลดข้อมูล...</div>';
    try {
        const d = await apiFetch('/v1/api/reports_ext.php?action=yearly_movement');
        if (!d.success) throw new Error(d.error);
        movData = d.data || [];
        // Re-insert canvas
        g('panel-movement').querySelector('.rp-card-body').innerHTML = '<div class="rp-chart rp-chart-tall"><canvas id="movChart"></canvas></div>';
        renderMovement();
    } catch (e) {
        g('panel-movement').querySelector('.rp-card-body').innerHTML = `<div class="rp-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`;
    }
}

function renderMovement() {
    if (!movData.length) return;
    let filtered = [...movData];
    const n = parseInt(g('movYears')?.value || 0);
    if (n > 0) filtered = filtered.slice(-n);

    if (movChartInst) movChartInst.destroy();

    const labels = filtered.map(r => 'ปี ' + r.fiscal_year);
    const total   = filtered.map(r => parseFloat(r.total_imported_kg) || 0);
    const liqN2   = filtered.map(r => parseFloat(r.liq_n2_kg) || 0);
    const chemIn  = filtered.map(r => parseFloat(r.chem_imported_kg) || 0);
    const remain  = filtered.map(r => parseFloat(r.chem_remaining_kg) || 0);

    const dlPlugin = {
        display: true,
        formatter: v => v > 0 ? fmtNum2(v) : '',
        anchor: 'end', align: 'end',
        font: { size: 9, weight: '700' },
        color: ctx => ctx.dataset.backgroundColor,
        rotation: -30,
        clamp: true,
    };

    movChartInst = new Chart(g('movChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'นำเข้ารวม/kg',      data: total,  backgroundColor: 'rgba(59,130,246,.8)',  borderRadius: 4, borderSkipped: false, barPercentage: .6, categoryPercentage: .85 },
                { label: 'Liq. N2/kg',          data: liqN2,  backgroundColor: 'rgba(239,68,68,.8)',   borderRadius: 4, borderSkipped: false, barPercentage: .6, categoryPercentage: .85 },
                { label: 'สารเคมีนำเข้า/kg',   data: chemIn, backgroundColor: 'rgba(234,179,8,.8)',   borderRadius: 4, borderSkipped: false, barPercentage: .6, categoryPercentage: .85 },
                { label: 'สารเคมีคงเหลือ/kg',  data: remain, backgroundColor: 'rgba(34,197,94,.8)',   borderRadius: 4, borderSkipped: false, barPercentage: .6, categoryPercentage: .85 },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { padding: 14, font: { size: 11 }, usePointStyle: true, pointStyle: 'rectRounded' } },
                datalabels: dlPlugin,
                tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${fmtNum2(ctx.raw)} kg` } },
            },
            scales: {
                y: { beginAtZero: true, grid: { color: GC }, ticks: { callback: v => fmtNum2(v) } },
                x: { grid: { display: false }, title: { display: true, text: 'ปีงบประมาณ', font: { size: 11 }, color: TC } },
            },
            layout: { padding: { top: 20 } },
        }
    });

    // Summary row
    const totalSum = total.reduce((a, b) => a + b, 0);
    const remSum = remain.reduce((a, b) => a + b, 0);
    if (g('movSummary')) {
        g('movSummary').innerHTML = `
            <span>รวมนำเข้าทั้งหมด: <strong>${fmtNum2(totalSum)} kg</strong></span>
            <span>รวมคงเหลือ: <strong>${fmtNum2(remSum)} kg</strong></span>
            <span style="color:${totalSum > 0 ? '#059669' : 'var(--c3)'}">อัตราใช้งาน: <strong>${totalSum > 0 ? ((1 - remSum / totalSum) * 100).toFixed(1) + '%' : '—'}</strong></span>
        `;
    }
}

/* ════════════════════════════════════════════
   COST CHART
════════════════════════════════════════════ */
let costRoomData = [], costChemData = [], costView = 'yearly';

function costSetCanvas(h = 340) {
    if (costChartInst) { costChartInst.destroy(); costChartInst = null; }
    g('costChartWrap').innerHTML = `<div class="rp-chart" style="height:${h}px"><canvas id="costChart"></canvas></div>`;
}

async function loadCost() {
    g('costChartWrap').innerHTML = '<div class="rp-ld"><i class="fas fa-circle-notch"></i> กำลังโหลด...</div>';
    try {
        const [d1, d2, d3] = await Promise.all([
            apiFetch('/v1/api/reports_ext.php?action=yearly_cost'),
            apiFetch('/v1/api/reports_ext.php?action=cost_by_room'),
            apiFetch('/v1/api/reports_ext.php?action=cost_by_chemical'),
        ]);
        if (!d1.success) throw new Error(d1.error);
        costData     = d1.data || [];
        costRoomData = d2.success ? (d2.data || []) : [];
        costChemData = d3.success ? (d3.data || []) : [];
        renderCostStats();
        setCostView('yearly');
    } catch (e) {
        g('costChartWrap').innerHTML = `<div class="rp-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`;
    }
}

function setCostView(view) {
    costView = view;
    document.querySelectorAll('[data-cview]').forEach(b => b.classList.toggle('on', b.dataset.cview === view));
    const isYearly = view === 'yearly';
    if (g('costYearFilter')) g('costYearFilter').style.display = isYearly ? '' : 'none';
    if (g('costTopFilter'))  g('costTopFilter').style.display  = isYearly ? 'none' : '';
    renderCostView();
}

function renderCostView() {
    if      (costView === 'yearly')   renderCost();
    else if (costView === 'chemical') renderCostByChemical();
    else if (costView === 'room')     renderCostByRoom();
}

function renderCostStats() {
    const allTotal  = costData.reduce((s,r) => s + (parseFloat(r.total_cost)||0), 0);
    const allLiq    = costData.reduce((s,r) => s + (parseFloat(r.liq_n2_cost)||0), 0);
    const allChem   = costData.reduce((s,r) => s + (parseFloat(r.chem_cost)||0), 0);
    const allCnt    = costData.reduce((s,r) => s + (parseInt(r.container_count)||0), 0);
    const allPriced = costData.reduce((s,r) => s + (parseInt(r.priced_count)||0), 0);
    const maxRow    = costData.reduce((mx,r) => (parseFloat(r.total_cost)||0)>(parseFloat(mx.total_cost)||0)?r:mx, {total_cost:0,fiscal_year:'—'});
    const pctPriced = allCnt > 0 ? Math.round(allPriced/allCnt*100) : 0;
    const hasPrice  = allTotal > 0;
    const uniqueChems = costChemData.length;

    const el = g('costStatRow'); if (!el) return;
    el.innerHTML = `
        <div class="rp-sc"><div class="rp-si" style="background:#dbeafe;color:#2563eb"><i class="fas fa-coins"></i></div>
            <div><div class="rp-sv">${hasPrice?'฿'+fmtNum2(allTotal):'—'}</div><div class="rp-sl">ค่าใช้จ่ายรวมทั้งหมด</div></div></div>
        <div class="rp-sc"><div class="rp-si" style="background:#fee2e2;color:#dc2626"><i class="fas fa-fire-alt"></i></div>
            <div><div class="rp-sv">${allLiq>0?'฿'+fmtNum2(allLiq):'—'}</div><div class="rp-sl">ไนโตรเจนเหลวรวม</div></div></div>
        <div class="rp-sc"><div class="rp-si" style="background:#fef3c7;color:#d97706"><i class="fas fa-flask"></i></div>
            <div><div class="rp-sv">${allChem>0?'฿'+fmtNum2(allChem):'—'}</div><div class="rp-sl">สารเคมีรวม</div></div></div>
        <div class="rp-sc"><div class="rp-si" style="background:#dcfce7;color:#15803d"><i class="fas fa-vials"></i></div>
            <div><div class="rp-sv">${num(uniqueChems)}</div><div class="rp-sl">ชนิดสารเคมี</div></div></div>
        <div class="rp-sc"><div class="rp-si" style="background:#ede9fe;color:#6d28d9"><i class="fas fa-trophy"></i></div>
            <div><div class="rp-sv">${hasPrice&&maxRow.fiscal_year!=='—'?maxRow.fiscal_year:'—'}</div><div class="rp-sl">ปีค่าใช้จ่ายสูงสุด</div></div></div>
        <div class="rp-sc"><div class="rp-si" style="background:${pctPriced>50?'#dcfce7':'#fef3c7'};color:${pctPriced>50?'#15803d':'#d97706'}"><i class="fas fa-tag"></i></div>
            <div><div class="rp-sv">${allCnt>0?pctPriced+'%':'—'}</div><div class="rp-sl">ขวดระบุราคา (${allPriced}/${allCnt})</div></div></div>
    `;
}

function renderCost() {
    if (!costData.length) {
        g('costChartWrap').innerHTML = '<div class="rp-empty"><i class="fas fa-chart-bar"></i><p>ไม่มีข้อมูล</p></div>';
        if (g('costSubLabel')) g('costSubLabel').textContent = '';
        return;
    }
    costSetCanvas(340);

    let filtered = [...costData];
    const n = parseInt(g('costYears')?.value || 0);
    if (n > 0) filtered = filtered.slice(-n);

    const labels   = filtered.map(r => 'ปี ' + r.fiscal_year);
    const total    = filtered.map(r => parseFloat(r.total_cost)||0);
    const liqN2    = filtered.map(r => parseFloat(r.liq_n2_cost)||0);
    const chem     = filtered.map(r => parseFloat(r.chem_cost)||0);
    const cnts     = filtered.map(r => parseInt(r.container_count)||0);
    const totalSum = total.reduce((a,b)=>a+b,0);
    const liqSum   = liqN2.reduce((a,b)=>a+b,0);
    const chemSum  = chem.reduce((a,b)=>a+b,0);
    const noPrices = totalSum === 0;

    const dlBaht = {
        display: true, formatter: v => v > 0 ? '฿'+fmtNum2(v) : '',
        anchor:'end', align:'end', font:{ size:9, weight:'700' },
        color: ctx => ctx.dataset.backgroundColor, rotation:-30, clamp:true,
    };
    const dlCnt = {
        display: true, formatter: v => v > 0 ? num(v)+' ขวด' : '',
        anchor:'end', align:'end', font:{ size:9, weight:'700' },
        color:'rgba(99,102,241,.9)', rotation:-30, clamp:true,
    };

    if (noPrices) {
        if (g('costSubLabel')) g('costSubLabel').textContent = 'จำนวนภาชนะแยกตามปีงบประมาณ (ยังไม่มีข้อมูลราคา)';
        costChartInst = new Chart(g('costChart'), {
            type:'bar',
            data:{ labels, datasets:[{
                label:'จำนวนภาชนะ', data:cnts,
                backgroundColor:'rgba(99,102,241,.7)',
                borderRadius:5, borderSkipped:false, barPercentage:.55, categoryPercentage:.8,
            }]},
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{
                    legend:{ position:'top', labels:{ padding:14, font:{size:11}, usePointStyle:true, pointStyle:'rectRounded' }},
                    datalabels: dlCnt,
                    tooltip:{ callbacks:{ label: ctx => ` จำนวน: ${num(ctx.raw)} ขวด` }},
                },
                scales:{
                    y:{ beginAtZero:true, grid:{color:GC}, ticks:{ callback: v => num(v)+' ขวด' }},
                    x:{ grid:{display:false}, title:{display:true, text:'ปีงบประมาณ', font:{size:11}, color:TC}},
                },
                layout:{ padding:{top:20}},
            }
        });
        if (g('costSummary')) g('costSummary').innerHTML = `
            <span style="color:#d97706"><i class="fas fa-exclamation-circle" style="margin-right:5px"></i>ยังไม่มีข้อมูลราคาสารเคมี</span>
            <span style="color:var(--c3);font-size:10px">กรุณาเพิ่มข้อมูลราคาในหน้าจัดการขวดสาร — แสดงจำนวนภาชนะแทน</span>
        `;
        return;
    }

    if (g('costSubLabel')) g('costSubLabel').textContent =
        'ค่าใช้จ่ายรวม' + (liqSum>0?' · ไนโตรเจนเหลว':'') + (chemSum>0&&liqSum>0?' · สารเคมี':'') + ' (หน่วย: บาท)';

    const datasets = [
        { label:'ค่าใช้จ่ายรวม (฿)', data:total, backgroundColor:'rgba(59,130,246,.8)', borderRadius:4, borderSkipped:false, barPercentage:.6, categoryPercentage:.85 },
    ];
    if (liqSum > 0) datasets.push(
        { label:'ไนโตรเจนเหลว (฿)', data:liqN2, backgroundColor:'rgba(239,68,68,.8)', borderRadius:4, borderSkipped:false, barPercentage:.6, categoryPercentage:.85 }
    );
    if (chemSum > 0 && liqSum > 0) datasets.push(
        { label:'สารเคมีอื่นๆ (฿)', data:chem, backgroundColor:'rgba(234,179,8,.8)', borderRadius:4, borderSkipped:false, barPercentage:.6, categoryPercentage:.85 }
    );

    costChartInst = new Chart(g('costChart'), {
        type:'bar', data:{ labels, datasets },
        options:{
            responsive:true, maintainAspectRatio:false,
            plugins:{
                legend:{ position:'top', labels:{padding:14, font:{size:11}, usePointStyle:true, pointStyle:'rectRounded'}},
                datalabels: dlBaht,
                tooltip:{ callbacks:{ label: ctx => ` ${ctx.dataset.label}: ฿${fmtNum2(ctx.raw)}` }},
            },
            scales:{
                y:{ beginAtZero:true, grid:{color:GC}, ticks:{callback: v => '฿'+fmtNum2(v)}},
                x:{ grid:{display:false}, title:{display:true, text:'ปีงบประมาณ', font:{size:11}, color:TC}},
            },
            layout:{ padding:{top:20}},
        }
    });

    if (g('costSummary')) g('costSummary').innerHTML = `
        <span>รวมทั้งหมด: <strong>฿${fmtNum2(totalSum)}</strong></span>
        ${liqSum>0 ? `<span>Liq. N2: <strong>฿${fmtNum2(liqSum)}</strong></span>` : ''}
        ${chemSum>0&&liqSum>0 ? `<span>สารเคมีอื่นๆ: <strong>฿${fmtNum2(chemSum)}</strong></span>` : ''}
        <span style="color:var(--c3);font-size:10px">${filtered.length} ปีงบประมาณ</span>
    `;
}

const COST_PALETTE = [
    'rgba(99,102,241,.8)','rgba(59,130,246,.8)','rgba(16,185,129,.8)',
    'rgba(245,158,11,.8)','rgba(239,68,68,.8)','rgba(168,85,247,.8)',
    'rgba(20,184,166,.8)','rgba(249,115,22,.8)','rgba(236,72,153,.8)',
    'rgba(34,197,94,.8)', 'rgba(234,179,8,.8)', 'rgba(14,165,233,.8)',
    'rgba(139,92,246,.8)','rgba(248,113,113,.8)','rgba(52,211,153,.8)',
];

function costHorizChart(rows, opts = {}) {
    const topN     = parseInt(g('costTopN')?.value || 15);
    const hasCost  = rows.some(r => parseFloat(r.total_cost) > 0);
    const sortKey  = hasCost ? 'total_cost' : 'container_count';
    const sorted   = [...rows]
        .sort((a,b) => (parseFloat(b[sortKey])||0) - (parseFloat(a[sortKey])||0))
        .slice(0, topN);

    if (!sorted.length) {
        g('costChartWrap').innerHTML = '<div class="rp-empty"><i class="fas fa-chart-bar"></i><p>ไม่มีข้อมูล</p></div>';
        if (g('costSubLabel')) g('costSubLabel').textContent = '';
        return;
    }

    const h = Math.max(sorted.length * 42 + 80, 280);
    costSetCanvas(h);

    const labelField = opts.labelField || 'chemical_name';
    const labels     = sorted.map(r => (r[labelField] || '(ไม่ระบุ)').length > 30
        ? (r[labelField]).substring(0, 28) + '…'
        : (r[labelField] || '(ไม่ระบุ)'));
    const values     = sorted.map(r => hasCost ? (parseFloat(r.total_cost)||0) : (parseInt(r.container_count)||0));
    const colors     = sorted.map((_, i) => COST_PALETTE[i % COST_PALETTE.length]);

    if (g('costSubLabel')) g('costSubLabel').textContent = opts.subLabel ||
        (hasCost ? 'ค่าใช้จ่ายรวม (หน่วย: บาท) — Top ' + sorted.length : 'จำนวนภาชนะ (ยังไม่มีข้อมูลราคา) — Top ' + sorted.length);

    costChartInst = new Chart(g('costChart'), {
        type:'bar',
        data:{ labels, datasets:[{
            label: hasCost ? 'ค่าใช้จ่าย (฿)' : 'จำนวนภาชนะ',
            data: values,
            backgroundColor: colors,
            borderRadius: 4, borderSkipped: false,
            barPercentage:.7, categoryPercentage:.85,
        }]},
        options:{
            indexAxis:'y',
            responsive:true, maintainAspectRatio:false,
            plugins:{
                legend:{ display:false },
                datalabels:{
                    display:true,
                    formatter: v => hasCost ? '฿'+fmtNum2(v) : num(v)+' ขวด',
                    anchor:'end', align:'end',
                    font:{ size:9, weight:'700' },
                    color: (ctx) => colors[ctx.dataIndex] || '#374151',
                    clamp:true,
                },
                tooltip:{ callbacks:{
                    label: ctx => {
                        const r = sorted[ctx.dataIndex];
                        const lines = [];
                        if (hasCost) {
                            lines.push(` ค่าใช้จ่าย: ฿${fmtNum2(parseFloat(r.total_cost)||0)}`);
                            if (r.avg_cost > 0) lines.push(` เฉลี่ย: ฿${fmtNum2(parseFloat(r.avg_cost))}`);
                        }
                        lines.push(` ภาชนะ: ${num(parseInt(r.container_count)||0)} ขวด`);
                        if (opts.extraTooltip) lines.push(...opts.extraTooltip(r));
                        return lines;
                    }
                }},
            },
            scales:{
                x:{ beginAtZero:true, grid:{color:GC},
                    ticks:{ callback: v => hasCost ? '฿'+fmtNum2(v) : num(v) }},
                y:{ grid:{display:false}, ticks:{ font:{size:11} }},
            },
            layout:{ padding:{ right: hasCost ? 80 : 60 }},
        }
    });

    const sumVal = values.reduce((a,b)=>a+b,0);
    if (g('costSummary')) g('costSummary').innerHTML = hasCost
        ? `<span>รวม ${sorted.length} รายการ: <strong>฿${fmtNum2(sumVal)}</strong></span>
           <span style="color:var(--c3);font-size:10px">แสดง Top ${sorted.length} จาก ${rows.length} รายการทั้งหมด</span>`
        : `<span style="color:#d97706"><i class="fas fa-exclamation-circle" style="margin-right:4px"></i>ยังไม่มีข้อมูลราคา — แสดงจำนวนภาชนะแทน</span>
           <span style="color:var(--c3);font-size:10px">แสดง Top ${sorted.length} จาก ${rows.length} รายการ</span>`;
}

function renderCostByChemical() {
    costHorizChart(costChemData, {
        labelField: 'chemical_name',
        subLabel: 'ค่าใช้จ่ายแยกตามชนิดสารเคมี (Top N)',
        extraTooltip: r => r.cas_number ? [` CAS: ${r.cas_number}`] : [],
    });
}

function renderCostByRoom() {
    costHorizChart(costRoomData, {
        labelField: 'name',
        subLabel: 'ค่าใช้จ่ายแยกตามห้องปฏิบัติการ (Top N)',
        extraTooltip: r => {
            const lines = [];
            if (r.building_short) lines.push(` อาคาร: ${r.building_short}`);
            const ppct = (parseInt(r.container_count)||0) > 0
                ? Math.round((parseInt(r.priced_count)||0) / (parseInt(r.container_count)||0) * 100) : 0;
            lines.push(` ระบุราคา: ${ppct}%`);
            return lines;
        },
    });
}

/* ════════════════════════════════════════════
   ROOM REPORT
════════════════════════════════════════════ */
async function loadRoomsList() {
    const sel = g('rrRoom');
    sel.innerHTML = '<option value="">— กำลังโหลด... —</option>';
    try {
        const d = await apiFetch('/v1/api/reports_ext.php?action=rooms_list');
        if (!d.success) return;
        sel.dataset.loaded = '1';
        const totalContainers = d.data.reduce((s, r) => s + (parseInt(r.container_count) || 0), 0);
        sel.innerHTML = `<option value="">— เลือกห้อง —</option>
            <option value="0">ทุกห้อง (รวมทั้งหมด · ${totalContainers} ขวด)</option>`;
        // Group by building
        const byBuilding = {};
        d.data.forEach(rm => {
            const bk = rm.building_name || '(ไม่ระบุอาคาร)';
            if (!byBuilding[bk]) byBuilding[bk] = [];
            byBuilding[bk].push(rm);
        });
        Object.entries(byBuilding).forEach(([bldg, rooms]) => {
            const og = document.createElement('optgroup');
            og.label = bldg;
            rooms.forEach(rm => {
                const opt = document.createElement('option');
                opt.value = rm.id;
                const parts = [rm.code || '', rm.name].filter(Boolean).join(' · ');
                opt.textContent = parts + (rm.container_count > 0 ? ` (${rm.container_count} ขวด)` : '');
                og.appendChild(opt);
            });
            sel.appendChild(og);
        });
    } catch (e) {
        sel.innerHTML = '<option value="">— โหลดไม่สำเร็จ —</option>';
        console.error(e);
    }
}

async function loadRoomReport() {
    const roomId   = g('rrRoom').value;
    const semester = g('rrSemester').value;
    const year     = g('rrYear').value;
    if (roomId === '') { alert('กรุณาเลือกห้องปฏิบัติการ'); return; }

    g('rrResult').innerHTML = '<div class="rp-ld"><i class="fas fa-circle-notch"></i> กำลังสร้างรายงาน...</div>';
    g('btnPrint').style.display = 'none';

    try {
        const url = `/v1/api/reports_ext.php?action=room_report&room_id=${roomId}&semester=${semester}&year=${year}`;
        const d = await apiFetch(url);
        if (!d.success) throw new Error(d.error);
        renderRoomReport(d.data || [], d.room, semester, year, d.all_rooms || false);
    } catch (e) {
        g('rrResult').innerHTML = `<div class="rp-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`;
    }
}

// ── Shared state ──
let rrGroups = [], rrAllItems = [], rrMeta = {}, rrViewMode = 'byroom';

const RR_GHS = {
    flammable:      { icon:'fa-fire',              color:'#dc2626' },
    toxic:          { icon:'fa-skull-crossbones',  color:'#991b1b' },
    corrosive:      { icon:'fa-flask',             color:'#7c3aed' },
    oxidizing:      { icon:'fa-circle-notch',      color:'#d97706' },
    health_hazard:  { icon:'fa-exclamation',       color:'#dc2626' },
    harmful:        { icon:'fa-exclamation-triangle',color:'#dc2626'},
    irritant:       { icon:'fa-exclamation-circle',color:'#ca8a04' },
    environmental:  { icon:'fa-leaf',              color:'#16a34a' },
    explosive:      { icon:'fa-bolt',              color:'#ea580c' },
    compressed_gas: { icon:'fa-wind',              color:'#2563eb' },
};

function rrGHS(pictograms) {
    if (!pictograms || !pictograms.length) return '<span style="color:#cbd5e1;font-size:10px">—</span>';
    return '<div class="rp-ghs-row">' + pictograms.map(p => {
        const c = RR_GHS[p] || { icon:'fa-exclamation', color:'#64748b' };
        return `<div class="rp-ghs-d rp-ghs-${p}" title="${p}"><div class="rp-ghs-d-inner" style="color:${c.color}"><i class="fas ${c.icon}"></i></div></div>`;
    }).join('') + '</div>';
}

const RR_THEAD = `<thead><tr>
    <th style="text-align:center;width:28px">ลำดับ</th>
    <th>รหัสขวด</th><th>ชื่อสารเคมี</th><th>CAS No.</th><th>เกรด</th>
    <th style="text-align:right">บรรจุ</th><th style="text-align:right">คงเหลือ</th>
    <th>หน่วย</th><th>วันที่รับ</th><th>ผู้เพิ่ม</th>
    <th>ห้อง</th><th>รหัสห้อง</th><th>ผู้รับผิดชอบ</th>
    <th style="text-align:right">ราคา</th>
    <th>GHS</th><th>ความเป็นอันตราย</th><th>หมดอายุ</th><th>หมายเหตุ</th>
</tr></thead>`;

function rrBuildRows(items, startNum = 1) {
    return items.map((item, i) => {
        const pct = parseFloat(item.initial_quantity) > 0
            ? Math.round(parseFloat(item.current_quantity) / parseFloat(item.initial_quantity) * 100) : null;
        const expired = item.expiry_date && new Date(item.expiry_date) < new Date();
        return `<tr>
            <td style="text-align:center;color:var(--c3)">${startNum + i}</td>
            <td><span class="rp-code">${esc(item.bottle_code||'—')}</span></td>
            <td style="font-weight:600;min-width:130px">${esc(item.chemical_name||'—')}</td>
            <td style="font-family:'Courier New',monospace;font-size:10px">${esc(item.cas_number||'—')}</td>
            <td style="text-align:center">${esc(item.grade||'—')}</td>
            <td style="text-align:right">${fmtNum2(item.initial_quantity)}</td>
            <td style="text-align:right;font-weight:700${pct!==null&&pct<=20?';color:#dc2626':''}">${fmtNum2(item.current_quantity)}</td>
            <td style="text-align:center">${esc(item.quantity_unit||'—')}</td>
            <td style="white-space:nowrap;font-size:10px">${fmtDate(item.received_date||item.created_at)}</td>
            <td style="font-size:10px">${esc(item.added_by||'—')}</td>
            <td style="font-size:10px">${esc(item.room_name||'—')}</td>
            <td style="font-size:10px">${esc(item.room_code||'—')}</td>
            <td style="font-size:10px">${esc(item.responsible_person||'—')}</td>
            <td style="text-align:right">${item.price?'฿'+fmtNum2(item.price):'—'}</td>
            <td>${rrGHS(item.hazard_pictograms)}</td>
            <td style="min-width:160px;font-size:10px;line-height:1.4">${esc(item.hazard_statements||'')}</td>
            <td style="${expired?'color:#dc2626;font-weight:700;':''}white-space:nowrap;font-size:10px">${fmtDate(item.expiry_date)}</td>
            <td style="font-size:10px;color:var(--c3)">${esc(item.notes||'')}</td>
        </tr>`;
    }).join('');
}

function rrGroupByRoom(items) {
    const map = {};
    items.forEach(item => {
        const key = (item.room_name||'(ไม่ระบุ)') + '||' + (item.room_code||'');
        if (!map[key]) map[key] = {
            room_name: item.room_name||'(ไม่ระบุ)',
            room_code: item.room_code||'',
            building_short: item.building_short||'',
            responsible_person: item.responsible_person||'',
            items: []
        };
        map[key].items.push(item);
    });
    return Object.values(map);
}

// ── Render: แยกตามห้อง ──
function rrBuildByRoom() {
    const palette = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#84cc16','#f97316','#ec4899'];
    return rrGroups.map((grp, gi) => {
        const color = palette[gi % palette.length];
        const roomCost = grp.items.reduce((s,i) => s + (parseFloat(i.price)||0), 0);
        const expCnt = grp.items.filter(i => i.expiry_date && new Date(i.expiry_date) < new Date()).length;
        return `<div class="rrc" id="rrc-${gi}">
            <div class="rrc-hdr" onclick="rrToggle(${gi})">
                <span class="rrc-dot" style="background:${color}"></span>
                <span class="rrc-name">${esc(grp.room_name)}</span>
                ${grp.room_code ? `<span class="rrc-code" style="background:${color}18;color:${color}">${esc(grp.room_code)}</span>` : ''}
                ${grp.building_short ? `<span class="rrc-bldg"><i class="fas fa-building"></i>${esc(grp.building_short)}</span>` : ''}
                <div style="display:flex;align-items:center;gap:7px;margin-left:auto;flex-wrap:wrap">
                    ${expCnt > 0 ? `<span class="rrc-exp-badge"><i class="fas fa-exclamation-triangle"></i>${expCnt} หมดอายุ</span>` : ''}
                    ${roomCost > 0 ? `<span class="rrc-cost">฿${fmtNum2(roomCost)}</span>` : ''}
                    <span class="rrc-cnt" style="background:${color}">${grp.items.length} ขวด</span>
                    <i class="fas fa-chevron-down rrc-arr"></i>
                </div>
            </div>
            <div class="rrc-body">
                <div class="rp-tw"><table class="rp-t">${RR_THEAD}<tbody>${rrBuildRows(grp.items,1)}</tbody></table></div>
                <div class="rrc-foot">
                    <span>รวม <strong>${grp.items.length}</strong> รายการ</span>
                    ${roomCost > 0 ? `<span>มูลค่า <strong style="color:#059669">฿${fmtNum2(roomCost)}</strong></span>` : ''}
                    ${grp.responsible_person ? `<span><i class="fas fa-user" style="margin-right:3px"></i>${esc(grp.responsible_person)}</span>` : ''}
                    <span style="margin-left:auto;opacity:.6">${new Date().toLocaleString('th-TH')}</span>
                </div>
            </div>
        </div>`;
    }).join('');
}

// ── Render: รวมทั้งหมด ──
function rrBuildCombined() {
    let rows = '', num = 0;
    rrGroups.forEach(grp => {
        const roomCost = grp.items.reduce((s,i) => s+(parseFloat(i.price)||0), 0);
        rows += `<tr class="rrc-ghr"><td colspan="18"><div class="rrc-ghr-inner">
            <span style="width:8px;height:8px;border-radius:50%;background:#6366f1;flex-shrink:0;display:inline-block"></span>
            <strong style="color:#3730a3">${esc(grp.room_name)}</strong>
            ${grp.room_code ? `<span class="rrc-gh-code">${esc(grp.room_code)}</span>` : ''}
            ${grp.building_short ? `<span class="rrc-gh-bldg"><i class="fas fa-building"></i> ${esc(grp.building_short)}</span>` : ''}
            <span class="rrc-gh-cnt">${grp.items.length} รายการ</span>
            ${roomCost > 0 ? `<span style="font-size:10px;color:#059669;font-weight:700;margin-left:6px">฿${fmtNum2(roomCost)}</span>` : ''}
        </div></td></tr>`;
        rows += rrBuildRows(grp.items, num + 1);
        num += grp.items.length;
    });
    const totalCost = rrAllItems.reduce((s,i) => s+(parseFloat(i.price)||0), 0);
    return `<div class="rp-tw"><table class="rp-t">${RR_THEAD}<tbody>${rows}</tbody></table></div>
    <div class="rp-tfoot">
        <span>รวมทั้งหมด <strong>${rrAllItems.length}</strong> รายการ ใน <strong>${rrGroups.length}</strong> ห้อง</span>
        ${totalCost > 0 ? `<span>มูลค่ารวม: <strong style="color:#059669">฿${fmtNum2(totalCost)}</strong></span>` : ''}
        <span style="color:var(--c3);font-size:10px">${new Date().toLocaleString('th-TH')}</span>
    </div>`;
}

function rrToggle(gi) {
    const el = g('rrc-' + gi);
    if (el) el.classList.toggle('collapsed');
}

function setRRView(mode) {
    rrViewMode = mode;
    document.querySelectorAll('.rrv-btn').forEach(b => b.classList.toggle('on', b.dataset.view === mode));
    const vc = g('rrViewContent');
    if (vc) vc.innerHTML = mode === 'combined' ? rrBuildCombined() : rrBuildByRoom();
    rrBuildPrintArea();
}

function rrBuildPrintArea() {
    const { roomLabel, yearLabel, allRooms } = rrMeta;
    const title = `รายงานขวดสารเคมี ${roomLabel}${yearLabel ? ' ' + yearLabel : ''}`;
    let body = '';
    if (!allRooms || rrViewMode === 'combined') {
        body = rrGroups.map((grp, gi) => {
            const roomCost = grp.items.reduce((s,i) => s+(parseFloat(i.price)||0), 0);
            return (allRooms ? `<div style="background:#eef2ff;padding:4px 8px;margin-bottom:2px;font-weight:700;font-size:9pt;border-left:3px solid #6366f1">
                ${esc(grp.room_name)}${grp.room_code?' ('+esc(grp.room_code)+')':''}${grp.building_short?' · '+esc(grp.building_short):''} — ${grp.items.length} รายการ${roomCost > 0 ? ' · ฿'+fmtNum2(roomCost) : ''}
            </div>` : '') +
            `<table style="width:100%;border-collapse:collapse;font-size:7.5pt;margin-bottom:${allRooms?'5mm':'0'}">${RR_THEAD}<tbody>${rrBuildRows(grp.items, 1)}</tbody></table>`;
        }).join('');
    } else {
        body = rrGroups.map(grp => {
            const roomCost = grp.items.reduce((s,i) => s+(parseFloat(i.price)||0), 0);
            return `<div style="margin-bottom:7mm;page-break-inside:avoid">
                <div style="background:#eef2ff;padding:5px 10px;font-weight:700;font-size:9.5pt;border-left:4px solid #6366f1;margin-bottom:2px">
                    ${esc(grp.room_name)}${grp.room_code?' ('+esc(grp.room_code)+')':''}${grp.building_short?' · '+esc(grp.building_short):''}
                    <span style="font-weight:400;font-size:8.5pt"> — ${grp.items.length} รายการ${roomCost>0?' · ฿'+fmtNum2(roomCost):''}</span>
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:7.5pt">${RR_THEAD}<tbody>${rrBuildRows(grp.items,1)}</tbody></table>
            </div>`;
        }).join('');
    }
    g('printArea').innerHTML = `<div style="font-family:Sarabun,sans-serif">
        <div style="text-align:center;margin-bottom:8px">
            <div style="font-size:13pt;font-weight:700">${title}</div>
            <div style="font-size:9pt;color:#555">วันที่พิมพ์: ${new Date().toLocaleString('th-TH')}</div>
        </div>${body}</div>`;
}

function renderRoomReport(items, room, semester, year, allRooms) {
    g('btnPrint').style.display = '';
    const semLabel = semester==1 ? 'ภาคการศึกษาที่ 1' : semester==2 ? 'ภาคการศึกษาที่ 2' : 'ทั้งหมด';
    const yearLabel = year ? `ประจำ${semLabel}/${year}` : '';
    const roomLabel = allRooms ? 'รวมทุกห้องปฏิบัติการ'
        : room ? `${room.name}${room.code?' ('+room.code+')':''}${room.building_name?' · '+room.building_name:''}` : 'ห้องที่เลือก';

    rrAllItems = items;
    rrGroups = rrGroupByRoom(items);
    rrMeta = { allRooms, roomLabel, yearLabel };

    if (!allRooms) {
        // ── Single room ──
        rrViewMode = 'single';
        const totalCost = items.reduce((s,i) => s+(parseFloat(i.price)||0), 0);
        g('rrResult').innerHTML = items.length === 0
            ? `<div class="rp-empty"><i class="fas fa-inbox"></i><p>ไม่พบข้อมูลขวดสารเคมีในห้องนี้</p></div>`
            : `<div class="rp-report-hdr">
                <div>
                    <div class="rp-report-title">รายงานขวดสารเคมี ${esc(roomLabel)}</div>
                    <div class="rp-report-sub">${yearLabel?yearLabel+' · ':''}รวม ${items.length} รายการ${totalCost>0?' · มูลค่า ฿'+fmtNum2(totalCost):''}</div>
                </div>
                <span style="font-size:11px;color:var(--c3)"><i class="fas fa-print" style="margin-right:4px"></i>A4 แนวนอน</span>
               </div>
               <div class="rp-tw"><table class="rp-t">${RR_THEAD}<tbody>${rrBuildRows(items,1)}</tbody></table></div>
               <div class="rp-tfoot">
                   <span>รวม <strong>${items.length}</strong> รายการ</span>
                   ${totalCost>0?`<span>มูลค่ารวม: <strong style="color:#059669">฿${fmtNum2(totalCost)}</strong></span>`:''}
                   <span style="color:var(--c3);font-size:10px">${new Date().toLocaleString('th-TH')}</span>
               </div>`;
        rrBuildPrintArea();
        return;
    }

    // ── All rooms ──
    rrViewMode = 'byroom';
    const totalCost = items.reduce((s,i) => s+(parseFloat(i.price)||0), 0);
    const expCnt = items.filter(i => i.expiry_date && new Date(i.expiry_date) < new Date()).length;

    g('rrResult').innerHTML = `
        <div class="rrv-bar">
            <div>
                <div class="rp-report-title">รายงานขวดสารเคมี ${esc(roomLabel)}</div>
                <div class="rp-report-sub">${yearLabel?yearLabel+' · ':''}${rrGroups.length} ห้อง · ${items.length} รายการ</div>
            </div>
            <div class="rrv-toggle">
                <button class="rrv-btn on" data-view="byroom" onclick="setRRView('byroom')"><i class="fas fa-layer-group"></i> แยกตามห้อง</button>
                <button class="rrv-btn" data-view="combined" onclick="setRRView('combined')"><i class="fas fa-table"></i> รวมทั้งหมด</button>
            </div>
        </div>
        <div class="rrv-overview">
            <div class="rrv-oc"><div class="v" style="color:#6366f1">${rrGroups.length}</div><div class="lb">ห้องทั้งหมด</div></div>
            <div class="rrv-oc"><div class="v" style="color:#0ea5e9">${items.length}</div><div class="lb">ขวดทั้งหมด</div></div>
            ${totalCost > 0 ? `<div class="rrv-oc"><div class="v" style="color:#059669;font-size:16px">฿${fmtNum2(totalCost)}</div><div class="lb">มูลค่ารวม</div></div>` : ''}
            ${expCnt > 0 ? `<div class="rrv-oc"><div class="v" style="color:#dc2626">${expCnt}</div><div class="lb">หมดอายุ</div></div>` : ''}
        </div>
        <div id="rrViewContent"></div>
    `;
    setRRView('byroom');
}

function printReport() {
    const pa = g('printArea');
    if (!pa || !pa.innerHTML.trim()) return;
    const win = window.open('', '_blank', 'width=1200,height=800');
    win.document.write(`<!DOCTYPE html><html><head>
        <meta charset="utf-8">
        <title>รายงานขวดสารเคมี</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            *{box-sizing:border-box}
            body{font-family:Sarabun,'Noto Sans Thai',sans-serif;font-size:9pt;color:#111;margin:0;padding:6mm}
            table{width:100%;border-collapse:collapse;font-size:7.5pt}
            th{background:#f1f5f9;padding:5px 6px;text-align:left;font-weight:700;border:1px solid #e2e8f0;font-size:7pt;white-space:nowrap}
            td{padding:4px 6px;border:1px solid #e8ecf0;vertical-align:middle}
            tr:nth-child(even) td{background:#fafbff}
            .rp-code{font-family:'Courier New',monospace;font-size:8pt;font-weight:600}
            .rp-ghs-row{display:flex;gap:2px;flex-wrap:wrap}
            .rp-ghs-d{width:16px;height:16px;position:relative;flex-shrink:0}
            .rp-ghs-d-inner{position:absolute;inset:2px;transform:rotate(45deg);border-radius:2px;border:1px solid #dc2626;background:#fff;display:flex;align-items:center;justify-content:center;font-size:6px}
            .rp-ghs-d-inner i{transform:rotate(-45deg);color:#dc2626}
            div[style*="eef2ff"]{-webkit-print-color-adjust:exact;print-color-adjust:exact}
            @page{size:A4 landscape;margin:8mm}
        </style>
    </head><body>${pa.innerHTML}</body></html>`);
    win.document.close();
    setTimeout(() => { win.print(); }, 600);
}

/* ── Modal Helpers ── */
function diItem(lbl, val) { return `<div class="rp-mditem"><div class="rp-mdlbl">${lbl}</div><div class="rp-mdval">${val}</div></div>`; }

function showExpDetail(idx) {
    const item = expItems[idx]; if (!item) return;
    const days = parseInt(item.days_until_expiry) || 0;
    const cls = days < 0 || days <= 7 ? 'danger' : days <= 30 ? 'warning' : 'info';
    const hTitle = days < 0 ? `หมดอายุแล้ว (${Math.abs(days)} วันที่แล้ว)` : days <= 7 ? `วิกฤต — ${days} วันเหลือ` : `${days} วันเหลือ`;
    const ico = days < 0 ? 'fa-exclamation-circle' : days <= 7 ? 'fa-exclamation-triangle' : 'fa-clock';
    const qty = parseFloat(item.current_quantity) || 0, unit = item.quantity_unit || 'mL';
    const qtyStr = (qty >= 1000 && unit === 'mL') ? (qty / 1000).toFixed(1) + ' L' : qty.toFixed(0) + ' ' + unit;
    const pct = parseFloat(item.remaining_percentage) || 0;
    const owner = [item.owner_first, item.owner_last].filter(Boolean).join(' ') || '–';
    g('moExpTitle').textContent = item.name;
    g('moExpBody').innerHTML = `
        <div class="rp-mhero ${cls}"><div class="rp-mhero-ico"><i class="fas ${ico}"></i></div><div class="rp-mhero-txt"><h5>${hTitle}</h5><p>วันหมดอายุ: ${fmtDate(item.expiry_date)}</p></div></div>
        ${item.bottle_code ? `<div style="text-align:center;margin-bottom:14px"><div class="rp-mbc"><i class="fas fa-barcode"></i> ${esc(item.bottle_code)}</div></div>` : ''}
        <div class="rp-mgrid">
            ${diItem('ชื่อสาร', esc(item.name))}
            ${diItem('CAS', item.cas_number || '–')}
            ${diItem('เจ้าของ', esc(owner))}
            ${diItem('ตำแหน่ง', esc(item.lab_name || '–'))}
            ${diItem('ปริมาณ', qtyStr)}
            ${diItem('คงเหลือ', `<div class="rp-bar-wrap" style="margin-bottom:4px"><div class="rp-bar-fill ${pct<=15?'r':pct<=40?'o':'g'}" style="width:${pct}%"></div></div>${pct.toFixed(1)}%`)}
            ${diItem('วันหมดอายุ', fmtDate(item.expiry_date))}
            ${diItem('ประเภท', esc(item.container_type || '–'))}
        </div>
        <div style="text-align:center;font-size:10px;color:var(--c3)">Container ID: #${item.container_id || '–'}</div>`;
    g('moExp').classList.add('show'); document.body.style.overflow = 'hidden';
}

function showLsDetail(idx) {
    const item = lsItems[idx]; if (!item) return;
    const pct = parseFloat(item.remaining_percentage) || 0;
    const cls = pct <= 5 ? 'danger' : 'warning', ico = pct <= 5 ? 'fa-times-circle' : 'fa-exclamation-triangle';
    const hTitle = (pct <= 5 ? 'วิกฤต' : 'สต็อกต่ำ') + ` — ${pct.toFixed(1)}%`;
    const qty = parseFloat(item.current_quantity) || 0, initQ = parseFloat(item.initial_quantity) || 0, unit = item.quantity_unit || 'mL';
    const owner = [item.first_name, item.last_name].filter(Boolean).join(' ') || '–';
    const pctColor = pct <= 5 ? '#ef4444' : pct <= 15 ? '#f59e0b' : '#22c55e';
    g('moLsTitle').textContent = item.name;
    g('moLsBody').innerHTML = `
        <div class="rp-mhero ${cls}"><div class="rp-mhero-ico"><i class="fas ${ico}"></i></div><div class="rp-mhero-txt"><h5>${hTitle}</h5><p>${pct <= 5 ? 'ปริมาณเหลือน้อยมาก ควรสั่งซื้อโดยด่วน' : 'ปริมาณน้อย ควรวางแผนสั่งซื้อ'}</p></div></div>
        <div style="display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:14px;flex-wrap:wrap">
            <div style="text-align:center">
                <div class="rp-gauge" style="background:conic-gradient(${pctColor} 0% ${pct}%,#f1f5f9 ${pct}% 100%)">
                    <div class="rp-gauge-inner" style="color:${pctColor}">${pct.toFixed(0)}%</div>
                </div>
                <div style="font-size:10px;color:var(--c3)">คงเหลือ</div>
            </div>
        </div>
        <div class="rp-mgrid">
            ${diItem('ชื่อสาร', esc(item.name))}
            ${diItem('CAS', item.cas_number || '–')}
            ${diItem('เจ้าของ', esc(owner))}
            ${diItem('ตำแหน่ง', esc(item.lab_name || '–'))}
            ${diItem('คงเหลือ', qty + ' ' + unit)}
            ${diItem('เริ่มต้น', initQ > 0 ? initQ + ' ' + unit : '–')}
            ${diItem('ประเภท', esc(item.container_type || '–'))}
            ${diItem('วันหมดอายุ', item.expiry_date ? fmtDate(item.expiry_date) : '–')}
        </div>`;
    g('moLs').classList.add('show'); document.body.style.overflow = 'hidden';
}

function closeMo(id, e) {
    const mo = g(id);
    if (e && e.target && e.target !== mo) return;
    mo.classList.remove('show'); document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeMo('moExp'); closeMo('moLs'); } });

loadReport();
</script>
</body></html>
