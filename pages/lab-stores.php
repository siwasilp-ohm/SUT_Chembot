<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$userId = $user['id'];
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin = $roleLevel >= 5;
$isManager = $roleLevel >= 3;
$TH = $lang === 'th';

Layout::head($TH ? 'คลังสารเคมี — Lab Store' : 'Lab Store');
?>
<body>
<?php Layout::sidebar('lab-stores'); Layout::beginContent(); ?>
<?php Layout::pageHeader(
    $TH ? 'คลังสารเคมีแยกตามฝ่าย' : 'Lab Chemical Stores',
    'fas fa-store',
    $TH ? 'Dashboard & รายงานแยกตามฝ่าย/งาน พร้อม Drill-down ลึกถึงผู้ถือครองสารเคมี' : 'Dashboard & Reports by Division / Section — Drill-down to Chemical Holders'
); ?>

<style>
/* ============ CSS Variables ============ */
:root {
    --ls-primary: #6C5CE7;
    --ls-primary-light: #ede9fe;
    --ls-success: #059669;
    --ls-warning: #d97706;
    --ls-danger: #dc2626;
    --ls-radius: 16px;
    --ls-shadow: 0 4px 24px rgba(0,0,0,.06);
    --ls-r: 14px;
    --ls-rs: 10px;
    --ls-sh: 0 1px 6px rgba(0,0,0,.06);
    --ls-shm: 0 4px 20px rgba(0,0,0,.08);
}

/* ============ KPI Cards ============ */
.ls-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.ls-kpi{background:#fff;border-radius:var(--ls-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--ls-sh);border:1px solid var(--border);transition:all .15s;position:relative;overflow:hidden}
.ls-kpi:hover{transform:translateY(-2px);box-shadow:var(--ls-shm)}
.ls-kpi:active{transform:translateY(-1px)}
.ls-kpi::before{content:'';position:absolute;top:0;right:0;width:48px;height:48px;border-radius:0 10px 0 48px;opacity:.04;background:currentColor}
.ls-kpi-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.ls-kpi-text{flex:1;min-width:0}
.ls-kpi-value{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.ls-kpi-label{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ============ Breadcrumb ============ */
.ls-breadcrumb{display:flex;align-items:center;gap:6px;margin-bottom:18px;padding:10px 16px;background:#fff;border-radius:var(--ls-rs);box-shadow:var(--ls-sh);border:1px solid var(--border);flex-wrap:wrap}
.ls-bc-item{display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#888;cursor:pointer;padding:4px 10px;border-radius:8px;transition:all .15s;white-space:nowrap}
.ls-bc-item:hover{background:var(--ls-primary-light);color:var(--ls-primary)}
.ls-bc-item.active{background:var(--ls-primary);color:#fff;cursor:default}
.ls-bc-sep{font-size:8px;color:#ccc}

/* ============ Tab Bar ============ */
.ls-tabs{display:inline-flex;background:#f1f5f9;border-radius:var(--ls-rs);padding:3px;margin-bottom:20px}
.ls-tab{padding:8px 20px;font-size:12px;font-weight:600;color:var(--c3);border-radius:8px;cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;flex:none;white-space:nowrap}
.ls-tab:hover{color:var(--c1)}
.ls-tab.active{background:#fff;color:var(--ls-primary);box-shadow:0 1px 4px rgba(0,0,0,.08)}
.ls-tab i{margin-right:4px}

/* ============ Drill-Down Cards ============ */
.ls-drill-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-bottom:24px}
.ls-drill-card{background:#fff;border-radius:var(--ls-r);box-shadow:var(--ls-sh);border:1px solid var(--border);overflow:hidden;transition:all .2s;cursor:pointer;position:relative}
.ls-drill-card:hover{border-color:var(--ls-primary);box-shadow:var(--ls-shm);transform:translateY(-2px)}
.dc-header{padding:16px 18px;display:flex;align-items:center;gap:12px}
.dc-icon{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.dc-info{flex:1;min-width:0}
.dc-name{font-size:14px;font-weight:700;color:#1a1a2e;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.dc-sub{font-size:10px;color:#999;margin-top:3px}
.dc-arrow{font-size:12px;color:#ccc;transition:transform .15s}
.ls-drill-card:hover .dc-arrow{color:var(--ls-primary);transform:translateX(3px)}
.dc-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:#f3f4f6}
.dc-stat{background:#fff;padding:10px 12px;text-align:center}
.dc-stat-val{font-size:17px;font-weight:800;color:#1a1a2e}
.dc-stat-lbl{font-size:9px;color:#999;font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.dc-progress{height:4px;background:#f3f4f6;margin:0 16px 14px;border-radius:2px;overflow:hidden}
.dc-progress-bar{height:100%;border-radius:2px;transition:width .6s ease}

/* ============ Holder Cards ============ */
.ls-holder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;margin-bottom:24px}
.ls-holder-card{background:#fff;border-radius:var(--ls-r);box-shadow:var(--ls-sh);border:1px solid var(--border);overflow:hidden;transition:all .2s;cursor:pointer}
.ls-holder-card:hover{border-color:#2563eb;box-shadow:var(--ls-shm);transform:translateY(-2px)}
.ls-holder-header{padding:16px 18px;display:flex;align-items:center;gap:12px}
.ls-holder-avatar{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#fff;flex-shrink:0;text-transform:uppercase}
.ls-holder-info{flex:1;min-width:0}
.ls-holder-name{font-size:13px;font-weight:700;color:#1a1a2e;line-height:1.2}
.ls-holder-dept{font-size:10px;color:#888;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-holder-pills{display:flex;gap:6px;padding:0 16px 14px;flex-wrap:wrap}
.ls-holder-pill{font-size:10px;font-weight:700;padding:3px 10px;border-radius:8px;display:inline-flex;align-items:center;gap:4px}
.ls-holder-pill.bot{background:rgba(108,92,231,.08);color:#6C5CE7}
.ls-holder-pill.chem{background:rgba(37,99,235,.08);color:#2563eb}
.ls-holder-pill.qty{background:rgba(5,150,105,.08);color:#059669}

/* ============ Tables ============ */
table.ls-tbl{width:100%;border-collapse:collapse;font-size:12px}
table.ls-tbl th{padding:10px 14px;text-align:left;font-weight:700;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);white-space:nowrap;cursor:pointer;user-select:none;position:sticky;top:0;z-index:1;background:#fff}
table.ls-tbl th:hover{color:var(--ls-primary)}
table.ls-tbl th.sorted{color:var(--ls-primary)}
table.ls-tbl th .sort-icon{margin-left:4px;font-size:8px}
table.ls-tbl td{padding:10px 14px;border-bottom:1px solid #f9fafb;color:#333;vertical-align:middle}
table.ls-tbl tbody tr{transition:background .1s}
table.ls-tbl tbody tr:hover{background:#f5f3ff}

/* ============ Badges ============ */
.ls-badge{display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;padding:2px 8px;border-radius:6px}
.ls-badge.div{background:var(--ls-primary-light);color:var(--ls-primary)}
.ls-badge.sec{background:#dbeafe;color:#2563eb}
.ls-badge.status-active{background:#dcfce7;color:#059669}
.ls-badge.status-low{background:#fef3c7;color:#d97706}
.ls-badge.status-empty{background:#fee2e2;color:#dc2626}
.ls-badge.status-expired{background:#f3f4f6;color:#666}

/* ============ Bar Charts ============ */
.ls-bar-cell{position:relative;min-width:100px}
.ls-bar{height:6px;border-radius:3px;background:#f3f4f6;overflow:hidden}
.ls-bar-fill{height:100%;border-radius:3px;transition:width .4s ease}
.ls-bar-val{position:absolute;right:0;top:-2px;font-size:10px;font-weight:700;color:#333}
.ls-hbar{margin-bottom:8px}
.ls-hbar-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:3px}
.ls-hbar-name{font-size:11px;font-weight:600;color:#555;max-width:70%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-hbar-val{font-size:11px;font-weight:700;color:#1a1a2e}
.ls-hbar-track{height:8px;background:#f3f4f6;border-radius:4px;overflow:hidden}
.ls-hbar-fill{height:100%;border-radius:4px;transition:width .6s ease}

/* ============ Treemap ============ */
.ls-chart-card{background:#fff;border-radius:var(--ls-r);box-shadow:var(--ls-sh);border:1px solid var(--border);padding:20px}
.ls-chart-title{font-size:14px;font-weight:700;color:#1a1a2e;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.ls-chart-title i{color:var(--ls-primary);font-size:12px}
.ls-chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.ls-treemap{display:flex;flex-wrap:wrap;gap:3px;min-height:120px;border-radius:var(--ls-radius);overflow:hidden}
.ls-tm-item{border-radius:8px;padding:8px 10px;display:flex;flex-direction:column;justify-content:flex-end;min-height:60px;cursor:pointer;transition:all .2s;overflow:hidden;position:relative}
.ls-tm-item:hover{filter:brightness(1.1);transform:scale(1.02);z-index:2}
.ls-tm-name{font-size:9px;font-weight:700;color:rgba(255,255,255,.9);line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-tm-val{font-size:14px;font-weight:800;color:#fff}

/* ============ Empty / Loading ============ */
.ls-empty{text-align:center;padding:48px 20px;color:#ccc}
.ls-empty i{font-size:40px;display:block;margin-bottom:12px;opacity:.2}
.ls-empty p{font-size:13px;color:#999}
.ls-store-name{font-weight:700;color:#1a1a2e;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ============ Remaining % ============ */
.ls-remain-bar{display:flex;align-items:center;gap:6px}
.ls-remain-track{flex:1;height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden;min-width:40px}
.ls-remain-fill{height:100%;border-radius:3px;transition:width .3s}
.ls-remain-pct{font-size:10px;font-weight:700;min-width:30px;text-align:right}

/* ============ Table Wrap ============ */
.ls-table-wrap{background:#fff;border-radius:var(--ls-r);box-shadow:var(--ls-sh);border:1px solid var(--border);overflow:hidden}
.ls-table-header{padding:16px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #f3f4f6;flex-wrap:wrap}
.ls-table-title{font-size:15px;font-weight:700;color:#1a1a2e;flex:1;display:flex;align-items:center;gap:8px}
.ls-search{position:relative;min-width:220px}
.ls-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:12px}
.ls-search input{width:100%;padding:9px 14px 9px 36px;border:1.5px solid var(--border);border-radius:var(--ls-rs);font-size:13px;background:#fff;color:var(--c1);transition:border .15s;box-sizing:border-box}
.ls-search input:focus{outline:none;border-color:var(--ls-primary);box-shadow:0 0 0 3px rgba(108,92,231,.08)}
.ls-filter select{padding:8px 10px;border:1.5px solid var(--border);border-radius:var(--ls-rs);font-size:11px;font-weight:600;background:#fff;color:var(--c1);cursor:pointer}

/* ============ Buttons ============ */
.ls-import-btn{padding:8px 16px;border:none;border-radius:var(--ls-rs);font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:6px;background:var(--ls-primary);color:#fff}
.ls-import-btn:hover{filter:brightness(1.1);transform:translateY(-1px)}
.ls-export-btn{padding:6px 14px;border:1.5px solid var(--border);border-radius:var(--ls-rs);font-size:11px;font-weight:700;cursor:pointer;background:#fff;color:#555;display:inline-flex;align-items:center;gap:5px;transition:all .15s}
.ls-export-btn:hover{border-color:var(--ls-success);color:var(--ls-success)}
/* ── Unified Buttons ── */
.ls-btn{padding:8px 16px;border:none;border-radius:var(--ls-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap;text-decoration:none}
.ls-btn-p{background:var(--ls-primary);color:#fff}.ls-btn-p:hover{filter:brightness(1.08)}
.ls-btn-o{background:#fff;color:var(--ls-primary);border:1.5px solid var(--ls-primary)}.ls-btn-o:hover{background:var(--ls-primary);color:#fff}
.ls-btn-d{background:#dc2626;color:#fff}.ls-btn-d:hover{background:#b91c1c}
.ls-btn-g{background:transparent;color:var(--c3);border:1.5px solid var(--border)}.ls-btn-g:hover{border-color:var(--ls-primary);color:var(--ls-primary)}
.ls-btn-s{padding:5px 10px;font-size:11px}

/* ============ Borrow Alert ============ */
.ls-borrow-row{background:#fef2f2;border-radius:10px;padding:10px 14px;margin-bottom:8px;display:flex;align-items:center;gap:10px;font-size:12px}
.ls-borrow-row i{color:#dc2626;font-size:14px;flex-shrink:0}
.ls-borrow-row .br-name{font-weight:700;color:#1a1a2e;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-borrow-row .br-qty{font-weight:700;color:#dc2626;white-space:nowrap}
.ls-borrow-row .br-date{font-size:10px;color:#999;white-space:nowrap}

/* ============ Pagination ============ */
.ls-pagination{display:flex;align-items:center;justify-content:center;gap:6px;padding:14px}
.ls-pagination button{padding:6px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:11px;font-weight:600;background:#fff;cursor:pointer;transition:all .15s}
.ls-pagination button:hover:not(:disabled){border-color:var(--ls-primary);color:var(--ls-primary)}
.ls-pagination button:disabled{opacity:.4;cursor:not-allowed}
.ls-pagination .pg-info{font-size:11px;color:#888;font-weight:600}

/* ============ Section Title ============ */
.ls-section-title{font-size:15px;font-weight:700;color:#1a1a2e;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.ls-section-title i{font-size:14px}

/* ============ Responsive ============ */
@media(max-width:768px){
    /* KPI — horizontal scroll strip */
    .ls-kpi-grid{display:flex;overflow-x:auto;gap:8px;padding-bottom:6px;margin-bottom:14px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
    .ls-kpi-grid::-webkit-scrollbar{display:none}
    .ls-kpi{min-width:120px;flex-shrink:0;padding:10px 12px;gap:8px}
    .ls-kpi-icon{width:32px;height:32px;font-size:13px;border-radius:8px}
    .ls-kpi-value{font-size:16px}
    .ls-kpi-label{font-size:9px}
    /* other layout */
    .ls-drill-grid,.ls-holder-grid{grid-template-columns:1fr}
    .ls-chart-grid{grid-template-columns:1fr}
    .ls-table-header{flex-direction:column;align-items:stretch}
    .ls-search input{width:100%}
    .ls-tabs{overflow-x:auto;flex-wrap:nowrap;display:flex}
    .ls-tab{min-width:80px;padding:8px 14px}
    .ls-breadcrumb{overflow-x:auto;flex-wrap:nowrap}
}
@media(max-width:480px){
    .ls-kpi-grid{gap:6px}
    .ls-kpi{min-width:108px}
}

/* ============ Manage Tab ============ */
.ls-admin-bar{display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.ls-admin-bar .count-badge{font-size:10px;font-weight:700;color:var(--c3);margin-left:auto}
.ls-chk{width:16px;height:16px;accent-color:var(--ls-primary);cursor:pointer;margin:0}
table.ls-tbl td.td-actions{white-space:nowrap;text-align:center}
.ls-act-btn{padding:4px 8px;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:3px}
.ls-act-btn.edit{background:#dbeafe;color:#2563eb}
.ls-act-btn.edit:hover{background:#2563eb;color:#fff}
.ls-act-btn.del{background:#fee2e2;color:#dc2626}
.ls-act-btn.del:hover{background:#dc2626;color:#fff}

/* ============ Modal ============ */
.ls-modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45);z-index:9998;display:flex;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s}
.ls-modal-overlay.show{opacity:1;visibility:visible}
.ls-modal{background:#fff;border-radius:20px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.92) translateY(10px);transition:transform .2s}
.ls-modal-overlay.show .ls-modal{transform:scale(1) translateY(0)}
.ls-modal.wide{max-width:780px}
/* Chemical detail modal */
.csd-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-bottom:18px}
.csd-item{background:#f8fafc;border-radius:10px;padding:10px 14px}
.csd-item .csd-lbl{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px}
.csd-item .csd-val{font-size:13px;font-weight:700;color:#1e293b;word-break:break-all}
.csd-fluid{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border-radius:14px;padding:14px 18px;margin-bottom:18px}
.csd-fluid-bar{width:48px;height:80px;border-radius:8px;background:#e2e8f0;position:relative;overflow:hidden;flex-shrink:0;border:2px solid #cbd5e1}
.csd-fluid-fill{position:absolute;bottom:0;left:0;right:0;border-radius:0 0 6px 6px;transition:height .5s ease}
.csd-fluid-pct{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:11px;font-weight:800;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.4)}
.csd-fluid-info{flex:1}
.csd-fluid-name{font-size:15px;font-weight:800;color:#1e293b;margin-bottom:3px}
.csd-fluid-sub{font-size:11px;color:#64748b}
.csd-section{margin-bottom:18px}
.csd-section-title{font-size:12px;font-weight:800;color:#475569;margin-bottom:10px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.3px}
.csd-section-title i{color:var(--ls-primary);font-size:13px}
/* Timeline */
.csd-tl{position:relative;padding-left:24px}
.csd-tl::before{content:'';position:absolute;left:8px;top:4px;bottom:4px;width:2px;background:#e2e8f0;border-radius:1px}
.csd-tl-item{position:relative;margin-bottom:16px;padding:12px 16px;background:#f8fafc;border-radius:12px;border:1px solid #f1f5f9}
.csd-tl-item::before{content:'';position:absolute;left:-20px;top:16px;width:10px;height:10px;border-radius:50%;border:2px solid #6C5CE7;background:#fff;z-index:1}
.csd-tl-item.borrow::before{border-color:#d97706;background:#fef3c7}
.csd-tl-item.return::before{border-color:#059669;background:#dcfce7}
.csd-tl-item.transfer::before{border-color:#2563eb;background:#dbeafe}
.csd-tl-item.dispose::before{border-color:#dc2626;background:#fee2e2}
.csd-tl-item.adjust::before{border-color:#7c3aed;background:#ede9fe}
.csd-tl-item.receive::before{border-color:#0891b2;background:#cffafe}
.csd-tl-item.use::before{border-color:#ea580c;background:#ffedd5}
.csd-tl-head{display:flex;align-items:center;gap:8px;margin-bottom:4px}
.csd-tl-type{font-size:10px;font-weight:800;padding:2px 8px;border-radius:6px;text-transform:uppercase;letter-spacing:.5px}
.csd-tl-type.borrow{background:#fef3c7;color:#92400e}
.csd-tl-type.return{background:#dcfce7;color:#065f46}
.csd-tl-type.transfer{background:#dbeafe;color:#1e40af}
.csd-tl-type.dispose{background:#fee2e2;color:#991b1b}
.csd-tl-type.adjust{background:#ede9fe;color:#5b21b6}
.csd-tl-type.receive{background:#cffafe;color:#155e75}
.csd-tl-type.use{background:#ffedd5;color:#9a3412}
.csd-tl-time{font-size:10px;color:#94a3b8;margin-left:auto}
.csd-tl-body{font-size:12px;color:#475569;line-height:1.5}
.csd-tl-body strong{color:#1e293b}
.csd-tl-status{display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px}
.csd-tl-status.completed{background:#dcfce7;color:#059669}
.csd-tl-status.pending{background:#fef3c7;color:#d97706}
.csd-tl-status.rejected{background:#fee2e2;color:#dc2626}
.csd-tl-status.cancelled{background:#f3f4f6;color:#6b7280}
.csd-empty-tl{text-align:center;padding:24px;color:#94a3b8;font-size:12px}
.csd-ghs-row{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.csd-ghs-badge{font-size:9px;padding:2px 7px;border-radius:5px;background:#fef2f2;color:#991b1b;font-weight:600;border:1px solid #fecaca}
@media(max-width:600px){.csd-grid{grid-template-columns:1fr 1fr}.ls-modal.wide{max-width:100%}}
.ls-modal-hdr{padding:20px 24px 12px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #f3f4f6}
.ls-modal-hdr h3{font-size:16px;font-weight:700;color:#1a1a2e;flex:1;margin:0}
.ls-modal-close{width:32px;height:32px;border:none;border-radius:8px;background:#f3f4f6;cursor:pointer;font-size:14px;color:#888;display:flex;align-items:center;justify-content:center}
.ls-modal-close:hover{background:#fee2e2;color:#dc2626}
.ls-modal-body{padding:20px 24px}
.ls-fg{margin-bottom:14px}
.ls-fg label{display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px}
.ls-fg input,.ls-fg select,.ls-fg textarea{width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;background:#f9fafb;transition:all .15s;box-sizing:border-box}
.ls-fg input:focus,.ls-fg select:focus,.ls-fg textarea:focus{outline:none;border-color:var(--ls-primary);box-shadow:0 0 0 3px rgba(108,92,231,.08);background:#fff}
.ls-fg textarea{resize:vertical;min-height:60px}
.ls-fg .ls-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ls-modal-ft{padding:14px 24px 20px;display:flex;justify-content:flex-end;gap:8px}
.ls-btn-cancel{padding:9px 20px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:12px;font-weight:700;background:#fff;color:#666;cursor:pointer}
.ls-btn-save{padding:9px 24px;border:none;border-radius:10px;font-size:12px;font-weight:700;background:var(--ls-primary);color:#fff;cursor:pointer;transition:all .15s}
.ls-btn-save:hover{filter:brightness(1.1);transform:translateY(-1px)}
.ls-btn-danger{padding:9px 24px;border:none;border-radius:10px;font-size:12px;font-weight:700;background:#dc2626;color:#fff;cursor:pointer}

/* ============ Import Modal ============ */
.ls-dropzone{border:2px dashed #d4d4d8;border-radius:14px;padding:30px 20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa}
.ls-dropzone:hover,.ls-dropzone.drag-over{border-color:var(--ls-primary);background:var(--ls-primary-light)}
.ls-dropzone i{font-size:28px;color:#bbb;display:block;margin-bottom:8px}
.ls-dropzone p{font-size:12px;color:#888;margin:4px 0}
.ls-dropzone .file-name{font-size:12px;font-weight:700;color:var(--ls-primary);margin-top:8px}
.ls-radio-group{display:flex;gap:16px;margin-top:4px}
.ls-radio-group label{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:#555;cursor:pointer;text-transform:none}
.ls-radio-group input[type=radio]{accent-color:var(--ls-primary)}

/* ============ Animations ============ */
@keyframes lsFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.ls-fade{animation:lsFadeIn .35s ease both}
</style>

<!-- ===== BREADCRUMB ===== -->
<div class="ls-breadcrumb" id="breadcrumb">
    <span class="ls-bc-item active" onclick="goHome()"><i class="fas fa-home" style="font-size:14px"></i> <?= $TH?'ภาพรวม':'Overview' ?></span>
</div>

<!-- ===== KPI GRID ===== -->
<div class="ls-kpi-grid" id="kpiGrid"></div>

<!-- ===== TAB BAR (home only) ===== -->
<div class="ls-tabs" id="tabBar">
    <button class="ls-tab active" onclick="switchTab('overview')"><i class="fas fa-th-large"></i> <?= $TH?'ภาพรวม':'Overview' ?></button>
    <button class="ls-tab" onclick="switchTab('divisions')"><i class="fas fa-building"></i> <?= $TH?'แยกตามฝ่าย':'By Division' ?></button>
    <button class="ls-tab" onclick="switchTab('table')"><i class="fas fa-table"></i> <?= $TH?'ตารางทั้งหมด':'All Stores' ?></button>
    <button class="ls-tab" onclick="switchTab('report')"><i class="fas fa-chart-bar"></i> <?= $TH?'รายงาน':'Report' ?></button>
    <?php if ($isAdmin): ?><button class="ls-tab" onclick="switchTab('manage')"><i class="fas fa-cogs"></i> <?= $TH?'จัดการ':'Manage' ?></button><?php endif; ?>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div id="mainContent"></div>

<!-- ===== STORE MODAL ===== -->
<div class="ls-modal-overlay" id="storeModal">
<div class="ls-modal">
    <div class="ls-modal-hdr"><i class="fas fa-store" style="color:var(--ls-primary)"></i><h3 id="smTitle"><?= $TH?'เพิ่มคลัง':'Add Store' ?></h3><button class="ls-modal-close" onclick="closeModal('storeModal')">&times;</button></div>
    <div class="ls-modal-body">
        <input type="hidden" id="smId">
        <div class="ls-fg"><label><?= $TH?'ศูนย์ / สำนักวิชา':'Center' ?></label><input id="smCenter" list="dlCenter" placeholder="<?= $TH?'ศูนย์เครื่องมือฯ':'Center name' ?>"><datalist id="dlCenter"></datalist></div>
        <div class="ls-fg"><label><?= $TH?'ฝ่าย / สาขาวิชา':'Division' ?></label><input id="smDiv" list="dlDiv" placeholder="<?= $TH?'ฝ่าย...':'Division...' ?>"><datalist id="dlDiv"></datalist></div>
        <div class="ls-fg"><label><?= $TH?'งาน':'Section' ?></label><input id="smSec" list="dlSec" placeholder="<?= $TH?'งาน...':'Section...' ?>"><datalist id="dlSec"></datalist></div>
        <div class="ls-fg"><label><?= $TH?'ชื่อคลังสารเคมี':'Store Name' ?></label><input id="smStore" placeholder="<?= $TH?'ชื่อคลัง':'Store name' ?>"></div>
        <div id="smStatsRow" style="display:none;margin-bottom:14px">
            <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:8px;text-transform:uppercase;letter-spacing:.3px"><?= $TH?'สถิติจาก chemical_stock (อัตโนมัติ)':'Stats from chemical_stock (auto)' ?></label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                <div style="background:#f0f4ff;border-radius:10px;padding:10px 12px;text-align:center"><div style="font-size:18px;font-weight:800;color:#6C5CE7" id="smStatBottles">0</div><div style="font-size:9px;color:#888;font-weight:600;text-transform:uppercase"><?= $TH?'ขวด':'Bottles' ?></div></div>
                <div style="background:#eff6ff;border-radius:10px;padding:10px 12px;text-align:center"><div style="font-size:18px;font-weight:800;color:#2563eb" id="smStatChems">0</div><div style="font-size:9px;color:#888;font-weight:600;text-transform:uppercase"><?= $TH?'สารเคมี':'Chemicals' ?></div></div>
                <div style="background:#ecfdf5;border-radius:10px;padding:10px 12px;text-align:center"><div style="font-size:18px;font-weight:800;color:#059669" id="smStatWeight">0</div><div style="font-size:9px;color:#888;font-weight:600;text-transform:uppercase"><?= $TH?'น้ำหนัก (kg)':'Weight (kg)' ?></div></div>
            </div>
        </div>
        <div class="ls-fg"><label><?= $TH?'หมายเหตุ':'Notes' ?></label><textarea id="smNotes" rows="2"></textarea></div>
    </div>
    <div class="ls-modal-ft"><button class="ls-btn-cancel" onclick="closeModal('storeModal')"><?= $TH?'ยกเลิก':'Cancel' ?></button><button class="ls-btn-save" onclick="saveStoreForm()"><?= $TH?'บันทึก':'Save' ?></button></div>
</div></div>

<!-- ===== IMPORT MODAL ===== -->
<div class="ls-modal-overlay" id="importModal">
<div class="ls-modal">
    <div class="ls-modal-hdr"><i class="fas fa-file-csv" style="color:var(--ls-success)"></i><h3><?= $TH?'นำเข้า CSV':'Import CSV' ?></h3><button class="ls-modal-close" onclick="closeModal('importModal')">&times;</button></div>
    <div class="ls-modal-body">
        <div class="ls-fg">
            <label><?= $TH?'เลือกไฟล์ CSV':'Choose CSV File' ?></label>
            <div class="ls-dropzone" id="csvDropzone" onclick="document.getElementById('csvFileInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <p><?= $TH?'ลากไฟล์มาวาง หรือคลิกเพื่อเลือก':'Drag & drop or click to select' ?></p>
                <p style="font-size:10px;color:#bbb"><?= $TH?'รองรับ .csv (UTF-8)':'Supports .csv (UTF-8)' ?></p>
                <div class="file-name" id="csvFileName" style="display:none"></div>
            </div>
            <input type="file" id="csvFileInput" accept=".csv" style="display:none" onchange="onCsvSelected(this)">
        </div>
        <div class="ls-fg">
            <label><?= $TH?'โหมดนำเข้า':'Import Mode' ?></label>
            <div class="ls-radio-group">
                <label><input type="radio" name="importMode" value="append" checked> <?= $TH?'เพิ่ม/อัพเดต (ไม่ลบของเดิม)':'Append / Update' ?></label>
                <label><input type="radio" name="importMode" value="replace"> <?= $TH?'แทนที่ทั้งหมด':'Replace All' ?></label>
            </div>
        </div>
        <div class="ls-fg">
            <label><?= $TH?'ข้าม Header กี่แถว':'Skip Header Rows' ?></label>
            <input id="csvSkipRows" type="number" min="0" value="1" style="width:80px">
        </div>
        <div class="ls-chart-card" style="padding:12px;font-size:11px;color:#888">
            <strong><?= $TH?'รูปแบบ CSV:':'CSV Format:' ?></strong><br>
            <?= $TH?'ศูนย์/สำนักวิชา, ฝ่าย/สาขาวิชา, งาน, ชื่อคลัง, จำนวนขวด, จำนวนสาร, ปริมาณ(kg), หมายเหตุ':'Center, Division, Section, Store Name, Bottles, Chemicals, Weight(kg), Notes' ?>
        </div>
    </div>
    <div class="ls-modal-ft"><button class="ls-btn-cancel" onclick="closeModal('importModal')"><?= $TH?'ยกเลิก':'Cancel' ?></button><button class="ls-btn-save" id="btnDoImport" onclick="doImport()" disabled><?= $TH?'นำเข้า':'Import' ?></button></div>
</div></div>

<!-- ===== CHEMICAL STOCK DETAIL MODAL ===== -->
<div class="ls-modal-overlay" id="chemDetailModal" onclick="if(event.target===this)closeModal('chemDetailModal')">
<div class="ls-modal wide" id="chemDetailContent"></div>
</div>

<!-- ===== DELETE CONFIRM MODAL ===== -->
<div class="ls-modal-overlay" id="delConfirmModal" onclick="if(event.target===this)closeDelConfirm()">
<div class="ls-modal" style="max-width:480px;border-radius:20px;overflow:hidden">
    <!-- header -->
    <div style="background:linear-gradient(135deg,#dc2626,#b91c1c);padding:20px 22px;display:flex;align-items:center;gap:14px">
        <div style="width:46px;height:46px;border-radius:14px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0">
            <i class="fas fa-trash-alt"></i>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:16px;font-weight:800;color:#fff"><?= $TH?'ยืนยันการลบคลัง':'Delete Store' ?></div>
            <div style="font-size:11px;color:rgba(255,255,255,.7);margin-top:2px"><?= $TH?'การดำเนินการนี้ไม่สามารถย้อนกลับได้':'This action cannot be undone' ?></div>
        </div>
        <button onclick="closeDelConfirm()" style="background:rgba(255,255,255,.15);border:none;width:32px;height:32px;border-radius:8px;cursor:pointer;color:#fff;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .15s" onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">&times;</button>
    </div>
    <!-- body -->
    <div id="delConfirmBody" style="padding:20px 22px">
        <div style="text-align:center;padding:24px;color:#94a3b8"><i class="fas fa-spinner fa-spin fa-lg"></i></div>
    </div>
    <!-- footer -->
    <div style="padding:14px 22px 20px;display:flex;gap:10px;border-top:1px solid #f1f5f9">
        <button onclick="closeDelConfirm()" style="flex:1;padding:11px;border:1.5px solid #e2e8f0;border-radius:10px;background:#fff;color:#475569;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s" onmouseover="this.style.borderColor='#94a3b8'" onmouseout="this.style.borderColor='#e2e8f0'"><?= $TH?'ยกเลิก':'Cancel' ?></button>
        <button id="delConfirmBtn" onclick="execDeleteSingle()" style="flex:2;padding:11px;border:none;border-radius:10px;background:#dc2626;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:7px;transition:opacity .15s" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            <i class="fas fa-trash-alt"></i> <?= $TH?'ลบคลังนี้':'Delete Store' ?>
        </button>
    </div>
</div>
</div>

<?php Layout::endContent(); ?>
<script>
const TH = <?= json_encode($TH) ?>;
const API = '/v1/api/lab_stores.php';
const IS_ADMIN = <?= json_encode($isAdmin) ?>;
const IS_MANAGER = <?= json_encode($isManager) ?>;
const COLORS = ['#6C5CE7','#2563eb','#059669','#d97706','#dc2626','#0891b2','#7c3aed','#db2777','#ea580c','#4f46e5','#0d9488','#65a30d'];
const AVA_COLORS = ['#6C5CE7','#2563eb','#059669','#d97706','#dc2626','#0891b2','#7c3aed','#db2777','#ea580c','#4f46e5'];

let dashData = null, currentView = 'home', currentTab = 'overview', breadcrumbs = [];
let tblSort = 'total_weight_kg', tblSortDir = 'desc', tblPage = 1;
let hSort = 'chemical_name', hSortDir = 'asc', hPage = 1;
let searchTimer = null, drillState = {};

/* ── helpers ── */
function num(v){return Number(v||0).toLocaleString(undefined,{maximumFractionDigits:2})}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML}
function animN(id,target){target=parseInt(target)||0;const el=document.getElementById(id);if(!el)return;const dur=600,s=performance.now();const step=n=>{const p=Math.min((n-s)/dur,1);el.textContent=Math.round(target*(1-Math.pow(1-p,3))).toLocaleString();if(p<1)requestAnimationFrame(step)};requestAnimationFrame(step)}
function mkKpi(icon,bg,fg,id,label,delay=0){return `<div class="ls-kpi ls-fade" style="animation-delay:${delay}s"><div class="ls-kpi-icon" style="background:${bg};color:${fg}"><i class="fas ${icon}"></i></div><div class="ls-kpi-text"><div class="ls-kpi-value" id="${id}">-</div><div class="ls-kpi-label">${label}</div></div></div>`}

/* ── init ── */
document.addEventListener('DOMContentLoaded',()=>goHome());

/* ─────────────── BREADCRUMB ─────────────── */
function updateBC(){
    const bc=document.getElementById('breadcrumb');
    let h=`<span class="ls-bc-item ${breadcrumbs.length===0?'active':''}" onclick="goHome()"><i class="fas fa-home" style="font-size:14px"></i> ${TH?'ภาพรวม':'Overview'}</span>`;
    breadcrumbs.forEach((b,i)=>{
        h+=`<i class="fas fa-chevron-right ls-bc-sep"></i>`;
        h+=`<span class="ls-bc-item ${i===breadcrumbs.length-1?'active':''}" onclick="${b.onclick}"><i class="fas ${b.icon}" style="font-size:10px;margin-right:3px"></i>${esc(b.label)}</span>`;
    });
    bc.innerHTML=h;
}

/* ─────────────── HOME ─────────────── */
async function goHome(){
    currentView='home'; breadcrumbs=[]; updateBC();
    document.getElementById('tabBar').style.display='';
    document.getElementById('mainContent').innerHTML='<div style="text-align:center;padding:40px;color:#ccc"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    try{
        const d=await apiFetch(API+'?action=dashboard');
        if(!d.success)throw new Error(d.error);
        dashData=d.data;
        renderHomeKPIs(dashData.totals);
        document.getElementById('mainContent').innerHTML='<div id="tabOverview"></div><div id="tabDivisions" style="display:none"></div><div id="tabTable" style="display:none"></div><div id="tabReport" style="display:none"></div>'+(IS_ADMIN?'<div id="tabManage" style="display:none"></div>':'');
        renderOverview(); renderDivTab();
        if(IS_ADMIN)loadHierarchyCache();
        switchTab(currentTab);
    }catch(e){document.getElementById('mainContent').innerHTML=`<div class="ls-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`}
}

function renderHomeKPIs(t){
    if(!t)return;
    document.getElementById('kpiGrid').innerHTML=
        mkKpi('fa-store',    '#ede9fe','#6C5CE7','k1',TH?'คลังทั้งหมด':'Total Stores',0)+
        mkKpi('fa-building', '#dbeafe','#2563eb','k2',TH?'ฝ่าย':'Divisions',.05)+
        mkKpi('fa-wine-bottle','#dcfce7','#15803d','k3',TH?'ขวดสารเคมี':'Bottles',.1)+
        mkKpi('fa-weight-hanging','#fef3c7','#d97706','k4',TH?'ปริมาณรวม (kg)':'Weight (kg)',.15)+
        mkKpi('fa-user-tag', '#fee2e2','#dc2626','k5',TH?'ผู้ถือครอง':'Holders',.2);
    animN('k1',t.total_stores); animN('k2',t.total_divisions); animN('k3',t.total_bottles);
    document.getElementById('k4').textContent=num(t.total_weight_kg);
    animN('k5',t.total_holders||0);
}

let mngPage=1, mngSort='id', mngSortDir='desc', mngSearch='', mngDivF='', selectedIds=new Set(), hierarchyCache=null;

/* ── tabs ── */
function switchTab(tab){
    if(currentView!=='home')return;
    currentTab=tab;
    const tabNames=['overview','divisions','table','report']; if(IS_ADMIN)tabNames.push('manage');
    document.querySelectorAll('.ls-tab').forEach((b,i)=>b.classList.toggle('active',tabNames[i]===tab));
    ['tabOverview','tabDivisions','tabTable','tabReport','tabManage'].forEach(id=>{const e=document.getElementById(id);if(e)e.style.display='none'});
    const m={overview:'tabOverview',divisions:'tabDivisions',table:'tabTable',report:'tabReport',manage:'tabManage'};
    const el=document.getElementById(m[tab]);if(el)el.style.display='';
    if(tab==='table')loadStoreTable();
    if(tab==='report')renderReport();
    if(tab==='manage')loadManageTab();
}

/* ─────────────── OVERVIEW TAB ─────────────── */
function renderOverview(){
    const divs=dashData.divisionSummary||[], topW=dashData.topByWeight||[];
    const el=document.getElementById('tabOverview'); if(!el)return;
    const totalW=divs.reduce((s,d)=>s+parseFloat(d.total_weight_kg||0),0);

    // treemap
    let tm='';
    if(divs.length&&totalW>0){
        tm=divs.filter(d=>parseFloat(d.total_weight_kg)>0).map((d,i)=>{
            const pct=parseFloat(d.total_weight_kg)/totalW*100, c=COLORS[i%COLORS.length];
            return `<div class="ls-tm-item" style="flex-basis:${Math.max(pct,8)}%;background:${c}" title="${esc(d.division_name)}\n${num(d.total_weight_kg)} kg (${pct.toFixed(1)}%)" onclick="drillDiv('${esc(d.division_name)}')"><div class="ls-tm-val">${num(d.total_weight_kg)} <span style="font-size:9px;opacity:.7">kg</span></div><div class="ls-tm-name">${esc(d.division_name.replace(/ฝ่าย/,'').substring(0,30))}</div></div>`;
        }).join('');
    }

    // top weight
    const mxW=topW.length?Math.max(...topW.map(t=>parseFloat(t.total_weight_kg)||0)):1;
    const twH=topW.map((t,i)=>{const p=parseFloat(t.total_weight_kg)/mxW*100;return `<div class="ls-hbar"><div class="ls-hbar-header"><span class="ls-hbar-name" title="${esc(t.store_name)}">${esc(t.store_name)}</span><span class="ls-hbar-val">${num(t.total_weight_kg)} kg</span></div><div class="ls-hbar-track"><div class="ls-hbar-fill" style="width:${p}%;background:${COLORS[i%COLORS.length]}"></div></div></div>`}).join('');

    // top bottles
    const topB=[...topW].sort((a,b)=>(b.bottle_count||0)-(a.bottle_count||0)).slice(0,10);
    const mxB=topB.length?Math.max(...topB.map(t=>parseInt(t.bottle_count)||0)):1;
    const tbH=topB.filter(t=>parseInt(t.bottle_count)>0).map((t,i)=>{const p=(parseInt(t.bottle_count)||0)/mxB*100;return `<div class="ls-hbar"><div class="ls-hbar-header"><span class="ls-hbar-name" title="${esc(t.store_name)}">${esc(t.store_name)}</span><span class="ls-hbar-val">${parseInt(t.bottle_count).toLocaleString()}</span></div><div class="ls-hbar-track"><div class="ls-hbar-fill" style="width:${p}%;background:${COLORS[(i+3)%COLORS.length]}"></div></div></div>`}).join('');

    el.innerHTML=`
        <div class="ls-chart-card ls-fade" style="margin-bottom:20px"><div class="ls-chart-title"><i class="fas fa-th"></i> ${TH?'สัดส่วนตามฝ่าย — คลิกเพื่อ Drill-down':'Division Treemap — Click to drill-down'}</div><div class="ls-treemap">${tm||'<div class="ls-empty"><p>ไม่มีข้อมูล</p></div>'}</div></div>
        <div class="ls-chart-grid">
            <div class="ls-chart-card ls-fade"><div class="ls-chart-title"><i class="fas fa-flask"></i> ${TH?'Top 10 ปริมาณ (kg)':'Top 10 by Weight'}</div><div>${twH||'<div class="ls-empty"><p>-</p></div>'}</div></div>
            <div class="ls-chart-card ls-fade" style="animation-delay:.1s"><div class="ls-chart-title"><i class="fas fa-wine-bottle"></i> ${TH?'Top 10 จำนวนขวด':'Top 10 by Bottles'}</div><div>${tbH||'<div class="ls-empty"><p>-</p></div>'}</div></div>
        </div>`;
}

/* ─────────────── DIVISIONS TAB ─────────────── */
function renderDivTab(){
    const divs=dashData.divisionSummary||[];
    const el=document.getElementById('tabDivisions'); if(!el)return;
    const totalW=divs.reduce((s,d)=>s+parseFloat(d.total_weight_kg||0),0);
    el.innerHTML=`<div class="ls-drill-grid">${divs.map((d,i)=>{
        const c=COLORS[i%COLORS.length], pct=totalW>0?(parseFloat(d.total_weight_kg)/totalW*100):0;
        return `<div class="ls-drill-card ls-fade" style="animation-delay:${i*0.04}s" onclick="drillDiv('${esc(d.division_name)}')">
            <div class="dc-header"><div class="dc-icon" style="background:${c}15;color:${c}"><i class="fas fa-building"></i></div>
            <div class="dc-info"><div class="dc-name">${esc(d.division_name)}</div><div class="dc-sub">${d.section_count||0} ${TH?'งาน':'sec'} · ${d.store_count||0} ${TH?'คลัง':'stores'} · ${d.holder_count||0} ${TH?'ผู้ถือครอง':'holders'}</div></div>
            <i class="fas fa-chevron-right dc-arrow"></i></div>
            <div class="dc-stats"><div class="dc-stat"><div class="dc-stat-val" style="color:${c}">${parseInt(d.total_bottles||0).toLocaleString()}</div><div class="dc-stat-lbl">${TH?'ขวด':'Bottles'}</div></div>
            <div class="dc-stat"><div class="dc-stat-val" style="color:#2563eb">${parseInt(d.total_chemicals||0).toLocaleString()}</div><div class="dc-stat-lbl">${TH?'สารเคมี':'Chems'}</div></div>
            <div class="dc-stat"><div class="dc-stat-val" style="color:#059669">${num(d.total_weight_kg)}</div><div class="dc-stat-lbl">kg</div></div></div>
            <div class="dc-progress"><div class="dc-progress-bar" style="width:${pct}%;background:${c}"></div></div></div>`;
    }).join('')}</div>`;
}

/* ─────────────── TABLE TAB ─────────────── */
async function loadStoreTable(){
    let el=document.getElementById('tabTable'); if(!el)return;
    if(!el.querySelector('.ls-table-wrap')){
        const opts=(dashData?.divisionSummary||[]).map(d=>`<option value="${esc(d.division_name)}">${esc(d.division_name)}</option>`).join('');
        el.innerHTML=`<div class="ls-table-wrap"><div class="ls-table-header">
            <div class="ls-table-title"><i class="fas fa-store" style="color:var(--ls-primary)"></i> ${TH?'รายการคลังทั้งหมด':'All Lab Stores'}</div>
            <div class="ls-search"><i class="fas fa-search"></i><input id="tblSearch" placeholder="${TH?'ค้นหา...':'Search...'}" oninput="onTblSrch()"></div>
            <div class="ls-filter"><select id="tblDivF" onchange="tblPage=1;loadTblData()"><option value="">${TH?'ทุกฝ่าย':'All'}</option>${opts}</select></div>
            ${IS_MANAGER?`<button class="ls-btn ls-btn-p" onclick="importCSV()"><i class="fas fa-file-csv"></i> ${TH?'นำเข้า CSV':'Import'}</button>`:''}
        </div><div style="overflow-x:auto"><table class="ls-tbl" id="storeTable"><thead><tr>
            <th onclick="sortTbl('store_name')">${TH?'ชื่อคลัง':'Store'} <span class="sort-icon"></span></th>
            <th onclick="sortTbl('division_name')">${TH?'ฝ่าย':'Division'} <span class="sort-icon"></span></th>
            <th onclick="sortTbl('section_name')">${TH?'งาน':'Section'} <span class="sort-icon"></span></th>
            <th onclick="sortTbl('bottle_count')" style="text-align:right">${TH?'ขวด':'Bot'} <span class="sort-icon"></span></th>
            <th onclick="sortTbl('chemical_count')" style="text-align:right">${TH?'สารเคมี':'Chem'} <span class="sort-icon"></span></th>
            <th onclick="sortTbl('total_weight_kg')">${TH?'ปริมาณ (kg)':'Weight'} <span class="sort-icon"></span></th>
        </tr></thead><tbody id="tblBody"></tbody></table></div><div class="ls-pagination" id="tblPg"></div></div>`;
    }
    loadTblData();
}

async function loadTblData(){
    const body=document.getElementById('tblBody'); if(!body)return;
    body.innerHTML='<tr><td colspan="6" style="text-align:center;padding:30px;color:#ccc"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    const p=new URLSearchParams({action:'list',page:tblPage,per_page:30,sort:tblSort,sort_dir:tblSortDir});
    const s=document.getElementById('tblSearch')?.value.trim(), dv=document.getElementById('tblDivF')?.value;
    if(s)p.set('search',s); if(dv)p.set('division',dv);
    try{
        const d=await apiFetch(API+'?'+p); if(!d.success)throw new Error(d.error);
        const items=d.data.items||[], pg=d.data.pagination;
        const mxW=items.length?Math.max(...items.map(r=>parseFloat(r.total_weight_kg)||0)):1;
        body.innerHTML=items.length?items.map(r=>{
            const w=parseFloat(r.total_weight_kg)||0,pct=mxW>0?(w/mxW*100):0;
            const ci=(dashData?.divisionSummary||[]).findIndex(d=>d.division_name===r.division_name);
            const c=COLORS[(ci>=0?ci:0)%COLORS.length];
            return `<tr style="cursor:pointer" onclick="drillStore(${r.id})" title="${TH?'คลิกเพื่อดูรายละเอียด':'Click for details'}">
                <td><div class="ls-store-name">${esc(r.store_name)}</div></td>
                <td><span class="ls-badge div">${esc((r.division_name||'').replace(/ฝ่าย/,'').substring(0,25))}</span></td>
                <td><span class="ls-badge sec">${esc((r.section_name||'').substring(0,30))}</span></td>
                <td style="text-align:right;font-weight:700">${parseInt(r.bottle_count||0).toLocaleString()}</td>
                <td style="text-align:right;font-weight:700">${parseInt(r.chemical_count||0).toLocaleString()}</td>
                <td><div class="ls-bar-cell"><div class="ls-bar-val">${num(r.total_weight_kg)}</div><div class="ls-bar" style="margin-top:14px"><div class="ls-bar-fill" style="width:${pct}%;background:${c}"></div></div></div></td></tr>`;
        }).join(''):`<tr><td colspan="6"><div class="ls-empty"><i class="fas fa-store"></i><p>${TH?'ไม่พบข้อมูล':'No stores'}</p></div></td></tr>`;

        const pgEl=document.getElementById('tblPg');
        if(pg&&pg.total_pages>1)pgEl.innerHTML=`<button ${pg.page<=1?'disabled':''} onclick="tblPage=${pg.page-1};loadTblData()"><i class="fas fa-chevron-left"></i></button><span class="pg-info">${pg.page}/${pg.total_pages} (${pg.total})</span><button ${pg.page>=pg.total_pages?'disabled':''} onclick="tblPage=${pg.page+1};loadTblData()"><i class="fas fa-chevron-right"></i></button>`;
        else pgEl.innerHTML=pg?`<span class="pg-info">${pg.total} ${TH?'รายการ':'items'}</span>`:'';

        const ths=document.querySelectorAll('#storeTable th');
        ths.forEach(th=>{th.classList.remove('sorted');const si=th.querySelector('.sort-icon');if(si)si.textContent=''});
        const si=['store_name','division_name','section_name','bottle_count','chemical_count','total_weight_kg'].indexOf(tblSort);
        if(si>=0&&ths[si]){ths[si].classList.add('sorted');const ic=ths[si].querySelector('.sort-icon');if(ic)ic.textContent=tblSortDir==='asc'?'▲':'▼'}
    }catch(e){body.innerHTML=`<tr><td colspan="6" style="text-align:center;color:#dc2626">${esc(e.message)}</td></tr>`}
}

function sortTbl(c){tblSort===c?(tblSortDir=tblSortDir==='desc'?'asc':'desc'):(tblSort=c,tblSortDir='desc');tblPage=1;loadTblData()}
function onTblSrch(){clearTimeout(searchTimer);searchTimer=setTimeout(()=>{tblPage=1;loadTblData()},300)}

/* ─────────────── REPORT TAB ─────────────── */
function renderReport(){
    const divs=dashData?.divisionSummary||[];
    const el=document.getElementById('tabReport'); if(!el||!divs.length)return;
    const totalW=divs.reduce((s,d)=>s+parseFloat(d.total_weight_kg||0),0);
    const mxW=Math.max(...divs.map(d=>parseFloat(d.total_weight_kg||0)));
    const ch=divs.map((d,i)=>{const p=mxW>0?(parseFloat(d.total_weight_kg)/mxW*100):0;return `<div class="ls-hbar"><div class="ls-hbar-header"><span class="ls-hbar-name">${esc(d.division_name)}</span><span class="ls-hbar-val">${num(d.total_weight_kg)} kg</span></div><div class="ls-hbar-track"><div class="ls-hbar-fill" style="width:${p}%;background:${COLORS[i%COLORS.length]}"></div></div></div>`}).join('');
    const rows=divs.map((d,i)=>{const sh=totalW>0?(parseFloat(d.total_weight_kg)/totalW*100):0,c=COLORS[i%COLORS.length];
        return `<tr style="cursor:pointer" onclick="drillDiv('${esc(d.division_name)}')"><td><div style="display:flex;align-items:center;gap:8px"><div style="width:10px;height:10px;border-radius:3px;background:${c}"></div><span style="font-weight:700">${esc(d.division_name)}</span></div></td>
        <td style="text-align:right;font-weight:600">${d.store_count}</td><td style="text-align:right;font-weight:600">${parseInt(d.total_bottles||0).toLocaleString()}</td>
        <td style="text-align:right;font-weight:600">${parseInt(d.total_chemicals||0).toLocaleString()}</td><td style="text-align:right;font-weight:700">${num(d.total_weight_kg)}</td>
        <td><div class="ls-bar-cell"><div class="ls-bar-val">${sh.toFixed(1)}%</div><div class="ls-bar" style="margin-top:14px"><div class="ls-bar-fill" style="width:${sh}%;background:${c}"></div></div></div></td></tr>`}).join('');

    el.innerHTML=`
        <div class="ls-chart-card ls-fade" style="margin-bottom:20px"><div class="ls-chart-title"><i class="fas fa-chart-pie"></i> ${TH?'รายงานสรุป — คลิกเพื่อ Drill-down':'Summary — Click to drill-down'}</div><div>${ch}</div></div>
        <div class="ls-table-wrap"><div class="ls-table-header"><div class="ls-table-title"><i class="fas fa-file-alt" style="color:var(--ls-primary)"></i> ${TH?'สรุปตามฝ่าย':'By Division'}</div>
        <button class="ls-export-btn" onclick="exportRpt()"><i class="fas fa-download"></i> ${TH?'ส่งออก CSV':'Export CSV'}</button></div>
        <div style="overflow-x:auto"><table class="ls-tbl" id="reportTable"><thead><tr>
            <th>${TH?'ฝ่าย':'Division'}</th><th style="text-align:right">${TH?'คลัง':'Stores'}</th>
            <th style="text-align:right">${TH?'ขวด':'Bottles'}</th><th style="text-align:right">${TH?'สารเคมี':'Chemicals'}</th>
            <th style="text-align:right">${TH?'ปริมาณ (kg)':'Weight'}</th><th>${TH?'สัดส่วน':'Share'}</th>
        </tr></thead><tbody>${rows}</tbody></table></div></div>`;
}

/* ═══════════════════════════════════════════════
   DRILL-DOWN: DIVISION → SECTION → STORE → HOLDER
   ═══════════════════════════════════════════════ */

/* ── Division ── */
async function drillDiv(name){
    currentView='division'; breadcrumbs=[{label:name.replace(/ฝ่าย/,'').substring(0,35),icon:'fa-building',onclick:`drillDiv('${esc(name)}')`}];
    updateBC(); document.getElementById('tabBar').style.display='none';
    const mc=document.getElementById('mainContent');
    mc.innerHTML='<div style="text-align:center;padding:40px;color:#ccc"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    try{
        const d=await apiFetch(API+'?action=drill_division&division='+encodeURIComponent(name));
        if(!d.success)throw new Error(d.error);
        drillState=d.data;
        const{division_name,totals,sections,holders}=d.data;

        // KPIs
        document.getElementById('kpiGrid').innerHTML=
            mkKpi('fa-layer-group','#ede9fe','#6C5CE7','kD1',TH?'งาน':'Sections',0)+
            mkKpi('fa-store',    '#dbeafe','#2563eb','kD2',TH?'คลัง':'Stores',.05)+
            mkKpi('fa-wine-bottle','#dcfce7','#15803d','kD3',TH?'ขวด':'Bottles',.1)+
            mkKpi('fa-weight-hanging','#fef3c7','#d97706','kD4','kg',.15)+
            mkKpi('fa-user-tag', '#fee2e2','#dc2626','kD5',TH?'ผู้ถือครอง':'Holders',.2);
        animN('kD1',totals?.section_count); animN('kD2',totals?.store_count); animN('kD3',totals?.total_bottles);
        document.getElementById('kD4').textContent=num(totals?.total_weight_kg);
        animN('kD5',holders?.length||0);

        const totalW=sections.reduce((s,sec)=>s+parseFloat(sec.total_weight_kg||0),0);
        const secCards=sections.map((s,i)=>{
            const c=COLORS[i%COLORS.length], pct=totalW>0?(parseFloat(s.total_weight_kg)/totalW*100):0;
            return `<div class="ls-drill-card ls-fade" style="animation-delay:${i*0.04}s" onclick="drillSec('${esc(division_name)}','${esc(s.section_name)}')">
                <div class="dc-header"><div class="dc-icon" style="background:${c}15;color:${c}"><i class="fas fa-layer-group"></i></div>
                <div class="dc-info"><div class="dc-name">${esc(s.section_name)}</div><div class="dc-sub">${s.store_count||0} ${TH?'คลัง':'stores'} · ${s.holder_count||0} ${TH?'ผู้ถือครอง':'holders'}</div></div>
                <i class="fas fa-chevron-right dc-arrow"></i></div>
                <div class="dc-stats"><div class="dc-stat"><div class="dc-stat-val" style="color:${c}">${parseInt(s.total_bottles||0).toLocaleString()}</div><div class="dc-stat-lbl">${TH?'ขวด':'Bot'}</div></div>
                <div class="dc-stat"><div class="dc-stat-val" style="color:#2563eb">${parseInt(s.total_chemicals||0).toLocaleString()}</div><div class="dc-stat-lbl">${TH?'สารเคมี':'Chem'}</div></div>
                <div class="dc-stat"><div class="dc-stat-val" style="color:#059669">${num(s.total_weight_kg)}</div><div class="dc-stat-lbl">kg</div></div></div>
                <div class="dc-progress"><div class="dc-progress-bar" style="width:${pct}%;background:${c}"></div></div></div>`;
        }).join('');

        mc.innerHTML=`
            <div style="margin-bottom:24px"><div class="ls-section-title"><i class="fas fa-layer-group" style="color:var(--ls-primary)"></i>${TH?'งาน (Section) — คลิกเพื่อ Drill-down':'Sections — Click to drill-down'}</div>
            <div class="ls-drill-grid">${secCards||'<div class="ls-empty"><p>ไม่มีงานย่อย</p></div>'}</div></div>
            <div><div class="ls-section-title"><i class="fas fa-user-tag" style="color:#2563eb"></i>${TH?'ผู้ถือครองสารเคมี — คลิกเพื่อดูรายการสาร':'Chemical Holders — Click for chemical list'}</div>
            ${renderHolders(holders,division_name)}</div>`;
    }catch(e){mc.innerHTML=`<div class="ls-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`}
}

/* ── Section ── */
async function drillSec(divN,secN){
    currentView='section';
    breadcrumbs=[
        {label:divN.replace(/ฝ่าย/,'').substring(0,25),icon:'fa-building',onclick:`drillDiv('${esc(divN)}')`},
        {label:secN.substring(0,30),icon:'fa-layer-group',onclick:`drillSec('${esc(divN)}','${esc(secN)}')`}
    ];
    updateBC(); document.getElementById('tabBar').style.display='none';
    const mc=document.getElementById('mainContent');
    mc.innerHTML='<div style="text-align:center;padding:40px;color:#ccc"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    try{
        const d=await apiFetch(API+'?action=drill_section&division='+encodeURIComponent(divN)+'&section='+encodeURIComponent(secN));
        if(!d.success)throw new Error(d.error);
        drillState=d.data;
        const{division_name,section_name,totals,stores,holders}=d.data;

        document.getElementById('kpiGrid').innerHTML=
            mkKpi('fa-store',    '#ede9fe','#6C5CE7','kS1',TH?'คลัง':'Stores',0)+
            mkKpi('fa-wine-bottle','#dbeafe','#2563eb','kS2',TH?'ขวด':'Bottles',.05)+
            mkKpi('fa-flask',    '#dcfce7','#15803d','kS3',TH?'สารเคมี':'Chems',.1)+
            mkKpi('fa-weight-hanging','#fef3c7','#d97706','kS4','kg',.15)+
            mkKpi('fa-user-tag', '#fee2e2','#dc2626','kS5',TH?'ผู้ถือครอง':'Holders',.2);
        animN('kS1',totals?.store_count); animN('kS2',totals?.total_bottles); animN('kS3',totals?.total_chemicals);
        document.getElementById('kS4').textContent=num(totals?.total_weight_kg);
        animN('kS5',holders?.length||0);

        const totalW=stores.reduce((s,st)=>s+parseFloat(st.total_weight_kg||0),0);
        const stCards=stores.map((st,i)=>{
            const c=COLORS[i%COLORS.length], pct=totalW>0?(parseFloat(st.total_weight_kg)/totalW*100):0;
            return `<div class="ls-drill-card ls-fade" style="animation-delay:${i*0.04}s" onclick="drillStore(${st.id})">
                <div class="dc-header"><div class="dc-icon" style="background:${c}15;color:${c}"><i class="fas fa-store"></i></div>
                <div class="dc-info"><div class="dc-name">${esc(st.store_name)}</div><div class="dc-sub">${TH?'คลังสารเคมี':'Chemical Store'}</div></div>
                <i class="fas fa-chevron-right dc-arrow"></i></div>
                <div class="dc-stats"><div class="dc-stat"><div class="dc-stat-val" style="color:${c}">${parseInt(st.bottle_count||0).toLocaleString()}</div><div class="dc-stat-lbl">${TH?'ขวด':'Bot'}</div></div>
                <div class="dc-stat"><div class="dc-stat-val" style="color:#2563eb">${parseInt(st.chemical_count||0).toLocaleString()}</div><div class="dc-stat-lbl">${TH?'สารเคมี':'Chem'}</div></div>
                <div class="dc-stat"><div class="dc-stat-val" style="color:#059669">${num(st.total_weight_kg)}</div><div class="dc-stat-lbl">kg</div></div></div>
                <div class="dc-progress"><div class="dc-progress-bar" style="width:${pct}%;background:${c}"></div></div></div>`;
        }).join('');

        mc.innerHTML=`
            <div style="margin-bottom:24px"><div class="ls-section-title"><i class="fas fa-store" style="color:var(--ls-primary)"></i>${TH?'คลังในงานนี้':'Stores in this section'}</div>
            <div class="ls-drill-grid">${stCards||'<div class="ls-empty"><p>ไม่มีคลัง</p></div>'}</div></div>
            <div><div class="ls-section-title"><i class="fas fa-user-tag" style="color:#2563eb"></i>${TH?'ผู้ถือครองสารเคมี — คลิกเพื่อดูรายการสาร':'Chemical Holders — Click for chemical list'}</div>
            ${renderHolders(holders,divN)}</div>`;
    }catch(e){mc.innerHTML=`<div class="ls-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`}
}

/* ── Store ── */
async function drillStore(id){
    currentView='store'; document.getElementById('tabBar').style.display='none';
    const mc=document.getElementById('mainContent');
    mc.innerHTML='<div style="text-align:center;padding:40px;color:#ccc"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    try{
        const d=await apiFetch(API+'?action=drill_store&id='+id);
        if(!d.success)throw new Error(d.error);
        const{store,holders}=d.data;
        breadcrumbs=[
            {label:(store.division_name||'').replace(/ฝ่าย/,'').substring(0,25),icon:'fa-building',onclick:`drillDiv('${esc(store.division_name)}')`},
            {label:(store.section_name||'').substring(0,25),icon:'fa-layer-group',onclick:`drillSec('${esc(store.division_name)}','${esc(store.section_name)}')`},
            {label:(store.store_name||'').substring(0,25),icon:'fa-store',onclick:`drillStore(${store.id})`}
        ];
        updateBC();

        document.getElementById('kpiGrid').innerHTML=
            mkKpi('fa-wine-bottle','#ede9fe','#6C5CE7','kT1',TH?'ขวด':'Bottles',0)+
            mkKpi('fa-flask',    '#dbeafe','#2563eb','kT2',TH?'สารเคมี':'Chems',.05)+
            mkKpi('fa-weight-hanging','#dcfce7','#15803d','kT3','kg',.1)+
            mkKpi('fa-user-tag', '#fef3c7','#d97706','kT4',TH?'ผู้ถือครอง':'Holders',.15);
        animN('kT1',store.bottle_count); animN('kT2',store.chemical_count);
        document.getElementById('kT3').textContent=num(store.total_weight_kg);
        animN('kT4',holders?.length||0);

        mc.innerHTML=`
            <div class="ls-chart-card ls-fade" style="margin-bottom:20px"><div class="ls-chart-title"><i class="fas fa-info-circle"></i> ${TH?'รายละเอียดคลัง':'Store Details'}</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;font-size:12px">
                <div><span style="color:#888;font-weight:600">${TH?'ศูนย์:':'Center:'}</span> <strong>${esc(store.center_name||'-')}</strong></div>
                <div><span style="color:#888;font-weight:600">${TH?'ฝ่าย:':'Division:'}</span> <strong>${esc(store.division_name||'-')}</strong></div>
                <div><span style="color:#888;font-weight:600">${TH?'งาน:':'Section:'}</span> <strong>${esc(store.section_name||'-')}</strong></div>
                <div><span style="color:#888;font-weight:600">${TH?'คลัง:':'Store:'}</span> <strong>${esc(store.store_name||'-')}</strong></div>
            </div></div>
            ${store._no_holder_warning?`<div class="ls-chart-card ls-fade" style="margin-bottom:20px;border-left:4px solid #f59e0b;background:#fffbeb">
                <div style="display:flex;align-items:flex-start;gap:12px;padding:4px 0">
                    <i class="fas fa-exclamation-triangle" style="color:#f59e0b;font-size:18px;margin-top:2px;flex-shrink:0"></i>
                    <div>
                        <div style="font-weight:700;color:#92400e;margin-bottom:4px">${TH?'ไม่พบผู้ถือครองสารเคมี':'No Chemical Holders Found'}</div>
                        <div style="font-size:12px;color:#78350f;line-height:1.5">${TH?'คลังนี้มีข้อมูลปริมาณสารเคมีจากการนำเข้า CSV แต่ยังไม่มีผู้ใช้ (User) ที่ถูกกำหนดให้อยู่ในคลังนี้ กรุณาตรวจสอบการตั้งค่า <strong>store_id</strong> ของผู้ใช้ในระบบ หรือกำหนดค่า department/position ให้ตรงกับโครงสร้างคลัง':'This store has chemical quantity data from CSV import but no users are assigned to it. Please check user <strong>store_id</strong> settings, or configure matching department/position values.'}</div>
                        <div style="margin-top:8px;font-size:11px;color:#92400e"><i class="fas fa-lightbulb" style="margin-right:4px"></i>${TH?'วิธีแก้ไข: ไปที่เมนู "ผู้ใช้งาน" > แก้ไขผู้ใช้ > กำหนดค่า "คลัง" (store) ให้ตรงกับคลังนี้':'Fix: Go to "Users" > Edit User > Set "Store" to this store'}</div>
                    </div>
                </div>
            </div>`:''}
            <div><div class="ls-section-title"><i class="fas fa-user-tag" style="color:#2563eb"></i>${TH?'ผู้ถือครอง — คลิกเพื่อดูรายการสาร':'Holders — Click to see chemicals'}</div>
            ${renderHolders(holders,store.division_name)}</div>`;
    }catch(e){mc.innerHTML=`<div class="ls-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`}
}

/* ── Holder (deepest) ── */
async function drillHolder(uid,ctxDiv,ctxSec){
    currentView='holder'; document.getElementById('tabBar').style.display='none';
    hPage=1; hSort='chemical_name'; hSortDir='asc';
    const mc=document.getElementById('mainContent');
    mc.innerHTML='<div style="text-align:center;padding:40px;color:#ccc"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    try{
        const d=await apiFetch(API+'?action=drill_holder&user_id='+uid);
        if(!d.success)throw new Error(d.error);
        drillState.hData=d.data; drillState.hUid=uid; drillState.hDiv=ctxDiv; drillState.hSec=ctxSec;
        const u=d.data.user, fn=(u.first_name||'')+' '+(u.last_name||'');

        breadcrumbs=[];
        if(ctxDiv)breadcrumbs.push({label:ctxDiv.replace(/ฝ่าย/,'').substring(0,20),icon:'fa-building',onclick:`drillDiv('${esc(ctxDiv)}')`});
        if(ctxSec)breadcrumbs.push({label:ctxSec.substring(0,20),icon:'fa-layer-group',onclick:`drillSec('${esc(ctxDiv)}','${esc(ctxSec)}')`});
        breadcrumbs.push({label:fn.substring(0,20),icon:'fa-user',onclick:`drillHolder(${uid},'${esc(ctxDiv||'')}','${esc(ctxSec||'')}')`});
        updateBC();

        renderHolderView(d.data);
    }catch(e){mc.innerHTML=`<div class="ls-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`}
}

function renderHolderView(data){
    const{user,stats,chemicals,active_borrows,pagination}=data;
    const fn=(user.first_name||'')+' '+(user.last_name||'');
    const kpi=document.getElementById('kpiGrid');
    kpi.innerHTML=
        mkKpi('fa-wine-bottle','#ede9fe','#6C5CE7','kU1',TH?'ขวดทั้งหมด':'Bottles',0)+
        mkKpi('fa-flask',    '#dbeafe','#2563eb','kU2',TH?'ชนิดสารเคมี':'Unique Chems',.05)+
        mkKpi('fa-battery-half','#dcfce7','#15803d','kU3',TH?'% คงเหลือเฉลี่ย':'Avg % Left',.1)+
        mkKpi('fa-hand-holding','#fee2e2','#dc2626','kU4',TH?'กำลังยืม':'Borrows',.15);
    animN('kU1',stats?.total_bottles); animN('kU2',stats?.unique_chemicals);
    document.getElementById('kU3').textContent=(stats?.avg_remaining_pct||0)+'%';
    animN('kU4',active_borrows?.length||0);

    const mc=document.getElementById('mainContent');
    // Borrow alerts
    let bHtml='';
    if(active_borrows&&active_borrows.length){
        bHtml=`<div class="ls-chart-card ls-fade" style="margin-bottom:20px"><div class="ls-chart-title"><i class="fas fa-exclamation-circle" style="color:#dc2626"></i> ${TH?'สารที่กำลังยืม (ยังไม่คืน)':'Active Borrows'}</div>
            ${active_borrows.map(b=>`<div class="ls-borrow-row"><i class="fas fa-hand-holding"></i><span class="br-name" title="${esc(b.chemical_name||b.barcode)}">${esc(b.chemical_name||b.barcode)}</span><span class="br-qty">${num(b.quantity)} ${esc(b.unit||'')}</span><span class="br-date">${b.created_at?new Date(b.created_at).toLocaleDateString('th-TH'):'-'}</span></div>`).join('')}</div>`;
    }

    mc.innerHTML=`
        <div class="ls-chart-card ls-fade" style="margin-bottom:20px"><div class="ls-chart-title"><i class="fas fa-user-circle"></i> ${esc(fn)}</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;font-size:12px">
            <div><span style="color:#888;font-weight:600">${TH?'ฝ่าย:':'Dept:'}</span> <strong>${esc(user.department||'-')}</strong></div>
            <div><span style="color:#888;font-weight:600">${TH?'งาน:':'Position:'}</span> <strong>${esc(user.position||'-')}</strong></div>
            ${user.email?`<div><span style="color:#888;font-weight:600">Email:</span> <strong>${esc(user.email)}</strong></div>`:''}
            ${user.phone?`<div><span style="color:#888;font-weight:600">${TH?'โทร:':'Phone:'}</span> <strong>${esc(user.phone)}</strong></div>`:''}</div></div>
        ${bHtml}
        <div class="ls-table-wrap" id="hWrap"><div class="ls-table-header">
            <div class="ls-table-title"><i class="fas fa-flask" style="color:var(--ls-primary)"></i> ${TH?'สารเคมีที่ถือครอง':'Chemicals Held'} <span style="font-size:11px;color:#888">(${pagination.total})</span></div>
            <div class="ls-search"><i class="fas fa-search"></i><input id="hSearch" placeholder="${TH?'ค้นหาสาร / CAS / ขวด...':'Search...'}" oninput="onHSrch()"></div>
            <button class="ls-export-btn" onclick="exportHolder(${drillState.hUid})"><i class="fas fa-download"></i> CSV</button></div>
        <div style="overflow-x:auto"><table class="ls-tbl" id="hTable"><thead><tr>
            <th onclick="sortH('bottle_code')">${TH?'รหัสขวด':'Bottle'} <span class="sort-icon"></span></th>
            <th onclick="sortH('chemical_name')">${TH?'ชื่อสารเคมี':'Chemical'} <span class="sort-icon"></span></th>
            <th onclick="sortH('cas_no')">CAS <span class="sort-icon"></span></th>
            <th onclick="sortH('remaining_qty')" style="text-align:right">${TH?'คงเหลือ':'Remaining'} <span class="sort-icon"></span></th>
            <th onclick="sortH('unit')">${TH?'หน่วย':'Unit'} <span class="sort-icon"></span></th>
            <th>% ${TH?'เหลือ':'Left'}</th>
            <th onclick="sortH('status')">${TH?'สถานะ':'Status'} <span class="sort-icon"></span></th>
        </tr></thead><tbody id="hBody"></tbody></table></div>
        <div class="ls-pagination" id="hPg"></div></div>`;
    renderHChems(chemicals,pagination);
}

function renderHChems(chems,pg){
    const body=document.getElementById('hBody'); if(!body)return;
    if(!chems||!chems.length){body.innerHTML=`<tr><td colspan="7"><div class="ls-empty"><i class="fas fa-flask"></i><p>${TH?'ไม่พบสารเคมี':'No chemicals'}</p></div></td></tr>`;return;}
    body.innerHTML=chems.map(c=>{
        const pct=parseFloat(c.remaining_pct)||0, pc=pct>50?'#059669':pct>20?'#d97706':'#dc2626';
        const sc=c.status==='active'?'status-active':c.status==='low'?'status-low':c.status==='empty'?'status-empty':'status-expired';
        return `<tr style="cursor:pointer" onclick="openChemDetail(${c.id})" title="${TH?'คลิกเพื่อดูรายละเอียดและประวัติ':'Click for details & history'}">
            <td><code style="font-size:11px;background:#f3f4f6;padding:2px 6px;border-radius:4px">${esc(c.bottle_code||'-')}</code></td>
            <td style="font-weight:700;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(c.chemical_name)}">${esc(c.chemical_name)}</td>
            <td style="font-size:11px;color:#666">${esc(c.cas_no||'-')}</td>
            <td style="text-align:right;font-weight:700">${num(c.remaining_qty)}</td>
            <td style="font-size:11px">${esc(c.unit||'-')}</td>
            <td><div class="ls-remain-bar"><div class="ls-remain-track"><div class="ls-remain-fill" style="width:${pct}%;background:${pc}"></div></div><span class="ls-remain-pct" style="color:${pc}">${pct.toFixed(0)}%</span></div></td>
            <td><span class="ls-badge ${sc}">${esc(c.status||'active')}</span></td></tr>`;
    }).join('');

    // Sort indicator
    const ths=document.querySelectorAll('#hTable th');
    ths.forEach(th=>{th.classList.remove('sorted');const si=th.querySelector('.sort-icon');if(si)si.textContent=''});
    const cols=['bottle_code','chemical_name','cas_no','remaining_qty','unit','','status'];
    const si=cols.indexOf(hSort); if(si>=0&&ths[si]){ths[si].classList.add('sorted');const ic=ths[si].querySelector('.sort-icon');if(ic)ic.textContent=hSortDir==='asc'?'▲':'▼'}

    const pgEl=document.getElementById('hPg');
    if(pg&&pg.total_pages>1)pgEl.innerHTML=`<button ${pg.page<=1?'disabled':''} onclick="hPage=${pg.page-1};loadHData()"><i class="fas fa-chevron-left"></i></button><span class="pg-info">${pg.page}/${pg.total_pages} (${pg.total})</span><button ${pg.page>=pg.total_pages?'disabled':''} onclick="hPage=${pg.page+1};loadHData()"><i class="fas fa-chevron-right"></i></button>`;
    else pgEl.innerHTML=pg?`<span class="pg-info">${pg.total} ${TH?'รายการ':'items'}</span>`:'';
}

async function loadHData(){
    const uid=drillState.hUid; if(!uid)return;
    const body=document.getElementById('hBody');
    if(body)body.innerHTML='<tr><td colspan="7" style="text-align:center;padding:20px;color:#ccc"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    const p=new URLSearchParams({action:'drill_holder',user_id:uid,page:hPage,per_page:50,sort:hSort,sort_dir:hSortDir});
    const s=document.getElementById('hSearch')?.value.trim(); if(s)p.set('search',s);
    try{const d=await apiFetch(API+'?'+p);if(!d.success)throw new Error(d.error);renderHChems(d.data.chemicals,d.data.pagination)}
    catch(e){if(body)body.innerHTML=`<tr><td colspan="7" style="text-align:center;color:#dc2626">${esc(e.message)}</td></tr>`}
}

function sortH(c){hSort===c?(hSortDir=hSortDir==='desc'?'asc':'desc'):(hSort=c,hSortDir='asc');hPage=1;loadHData()}
function onHSrch(){clearTimeout(searchTimer);searchTimer=setTimeout(()=>{hPage=1;loadHData()},300)}

/* ─────────────── SHARED: HOLDER CARDS ─────────────── */
function renderHolders(holders,ctxDiv){
    if(!holders||!holders.length)return `<div class="ls-empty ls-fade" style="padding:40px 20px"><i class="fas fa-user-slash" style="font-size:2.5em;color:#d1d5db;margin-bottom:12px"></i><p style="font-weight:600;font-size:14px;margin-bottom:6px;color:#6b7280">${TH?'ไม่พบผู้ถือครองสารเคมี':'No chemical holders found'}</p><p style="font-size:12px;color:#9ca3af;max-width:360px;margin:0 auto;line-height:1.5">${TH?'ยังไม่มีผู้ใช้ที่ถูกกำหนดให้อยู่ในคลังนี้ หรือผู้ใช้ยังไม่มีสารเคมีในครอบครอง':'No users are assigned to this store, or assigned users have no chemicals.'}</p></div>`;
    return `<div class="ls-holder-grid">${holders.map((h,i)=>{
        const ini=(h.full_name||'?').substring(0,2),c=AVA_COLORS[i%AVA_COLORS.length],sec=h.section_name||'';
        return `<div class="ls-holder-card ls-fade" style="animation-delay:${i*0.03}s" onclick="drillHolder(${h.user_id},'${esc(ctxDiv||'')}','${esc(sec)}')">
            <div class="ls-holder-header"><div class="ls-holder-avatar" style="background:${c}">${esc(ini)}</div>
            <div class="ls-holder-info"><div class="ls-holder-name">${esc(h.full_name)}</div><div class="ls-holder-dept">${esc(sec)}</div></div>
            <i class="fas fa-chevron-right dc-arrow"></i></div>
            <div class="ls-holder-pills">
                <span class="ls-holder-pill bot"><i class="fas fa-wine-bottle"></i> ${parseInt(h.bottle_count||0).toLocaleString()} ${TH?'ขวด':'bot'}</span>
                <span class="ls-holder-pill chem"><i class="fas fa-flask"></i> ${parseInt(h.chemical_count||0).toLocaleString()} ${TH?'ชนิด':'types'}</span>
                <span class="ls-holder-pill qty"><i class="fas fa-weight-hanging"></i> ${num(h.total_qty)}</span>
            </div></div>`;
    }).join('')}</div>`;
}

/* ─────────────── IMPORT / EXPORT ─────────────── */
async function importCSV(){
    if(!confirm(TH?'นำเข้าข้อมูลจาก CSV จะลบข้อมูลเดิม ต้องการดำเนินการ?':'Import CSV will replace data. Continue?'))return;
    try{const d=await apiFetch(API+'?action=import_csv',{method:'POST',body:'{}'});if(!d.success)throw new Error(d.error);showToast((TH?'นำเข้าสำเร็จ ':'Imported ')+d.data.inserted+(TH?' รายการ':' records'),'success');goHome()}catch(e){alert(e.message)}
}

function exportRpt(){
    const divs=dashData?.divisionSummary||[]; if(!divs.length)return;
    let csv='ฝ่าย,คลัง,ขวด,สารเคมี,ปริมาณ(kg)\n';
    divs.forEach(d=>{csv+=`"${d.division_name}",${d.store_count},${d.total_bottles},${d.total_chemicals},${d.total_weight_kg}\n`});
    dlCSV(csv,'lab_stores_report.csv');
}

async function exportHolder(uid){
    try{
        const d=await apiFetch(API+'?action=drill_holder&user_id='+uid+'&per_page=9999');
        if(!d.success)throw new Error(d.error);
        const chems=d.data.chemicals||[],u=d.data.user;
        let csv=`ผู้ถือครอง: ${u.first_name} ${u.last_name} | ฝ่าย: ${u.department} | งาน: ${u.position}\n`;
        csv+='รหัสขวด,ชื่อสารเคมี,CAS No.,คงเหลือ,หน่วย,%เหลือ,สถานะ\n';
        chems.forEach(c=>{csv+=`"${c.bottle_code||''}","${c.chemical_name||''}","${c.cas_no||''}",${c.remaining_qty||0},"${c.unit||''}",${parseFloat(c.remaining_pct||0).toFixed(1)},${c.status||''}\n`});
        dlCSV(csv,`holder_${uid}_chemicals.csv`);
    }catch(e){alert(e.message)}
}

/* ─────────────── CHEMICAL STOCK DETAIL MODAL ─────────────── */
const TXN_LABELS={borrow:TH?'ยืม':'Borrow',return:TH?'คืน':'Return',transfer:TH?'โอน':'Transfer',dispose:TH?'ทำลาย':'Dispose',adjust:TH?'ปรับปริมาณ':'Adjust',receive:TH?'รับเข้า':'Receive',use:TH?'ใช้งาน':'Use'};
const TXN_ICONS={borrow:'fa-hand-holding',return:'fa-undo-alt',transfer:'fa-exchange-alt',dispose:'fa-trash-alt',adjust:'fa-sliders-h',receive:'fa-box-open',use:'fa-vial'};

async function openChemDetail(stockId){
    const ov=document.getElementById('chemDetailModal');
    const md=document.getElementById('chemDetailContent');
    md.innerHTML='<div style="padding:60px;text-align:center;color:#ccc"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></div>';
    ov.classList.add('show');
    try{
        const d=await apiFetch(API+'?action=stock_detail&id='+stockId);
        if(!d.success)throw new Error(d.error);
        renderChemDetail(md,d.data);
    }catch(e){
        md.innerHTML=`<div class="ls-modal-hdr"><i class="fas fa-exclamation-triangle" style="color:#dc2626"></i><h3>${TH?'เกิดข้อผิดพลาด':'Error'}</h3><button class="ls-modal-close" onclick="closeModal('chemDetailModal')">&times;</button></div><div class="ls-modal-body" style="text-align:center;color:#dc2626;padding:30px">${esc(e.message)}</div>`;
    }
}

function renderChemDetail(md,data){
    const s=data.stock, hist=data.history||[], borrows=data.borrows||[];
    const pct=parseFloat(s.remaining_pct)||0;
    const fluidColor=pct>50?'linear-gradient(to top,#059669,#10b981)':pct>20?'linear-gradient(to top,#d97706,#f59e0b)':'linear-gradient(to top,#dc2626,#f87171)';
    const sc=s.status==='active'?'status-active':s.status==='low'?'status-low':s.status==='empty'?'status-empty':'status-expired';

    // GHS pictograms
    const ghsList=(s.hazard_pictograms||[]);
    const ghsHtml=ghsList.length?`<div class="csd-ghs-row">${ghsList.map(g=>`<span class="csd-ghs-badge">${esc(g)}</span>`).join('')}</div>`:'';
    const ghsClass=(s.ghs_classifications||[]);
    const ghsClassHtml=ghsClass.length?`<div class="csd-ghs-row" style="margin-top:4px">${ghsClass.map(g=>`<span class="csd-ghs-badge" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe">${esc(g)}</span>`).join('')}</div>`:'';

    // Signal word
    const sigHtml=s.signal_word?(s.signal_word==='Danger'?`<span style="color:#dc2626;font-weight:800"><i class="fas fa-radiation"></i> ${TH?'อันตราย':'DANGER'}</span>`:`<span style="color:#d97706;font-weight:800"><i class="fas fa-exclamation-triangle"></i> ${TH?'ระวัง':'WARNING'}</span>`):'';

    // Detail fields
    const fields=[
        {l:TH?'รหัสขวด':'Bottle Code',v:`<code style="font-size:12px;background:#f1f5f9;padding:2px 8px;border-radius:4px;letter-spacing:.5px">${esc(s.bottle_code||'-')}</code>`},
        {l:'CAS No.',v:esc(s.cas_no||s.chem_cas||'-')},
        {l:TH?'เกรด':'Grade',v:esc(s.grade||'-')},
        {l:TH?'ขนาดบรรจุ':'Package Size',v:`${num(s.package_size)} ${esc(s.unit||'')}`},
        {l:TH?'คงเหลือ':'Remaining',v:`<strong style="color:${pct>50?'#059669':pct>20?'#d97706':'#dc2626'}">${num(s.remaining_qty)} ${esc(s.unit||'')}</strong>`},
        {l:TH?'สถานะ':'Status',v:`<span class="ls-badge ${sc}">${esc(s.status||'active')}</span>`},
    ];
    if(s.owner_full_name)fields.push({l:TH?'ผู้ถือครอง':'Holder',v:`<i class="fas fa-user" style="color:var(--ls-primary);font-size:10px;margin-right:3px"></i>${esc(s.owner_full_name)}`});
    if(s.owner_department)fields.push({l:TH?'ฝ่าย':'Department',v:esc(s.owner_department)});
    if(s.storage_location)fields.push({l:TH?'ที่จัดเก็บ':'Storage',v:esc(s.storage_location)});
    if(s.added_at)fields.push({l:TH?'วันที่เพิ่ม':'Added',v:fmtDT(s.added_at)});

    // Chemical properties (from chemicals table)
    let propsHtml='';
    const props=[];
    if(s.molecular_formula)props.push({l:TH?'สูตรโมเลกุล':'Formula',v:esc(s.molecular_formula)});
    if(s.molecular_weight)props.push({l:'MW (g/mol)',v:parseFloat(s.molecular_weight).toFixed(2)});
    if(s.physical_state){
        const stMap={solid:TH?'ของแข็ง':'Solid',liquid:TH?'ของเหลว':'Liquid',gas:TH?'ก๊าซ':'Gas',plasma:'Plasma'};
        props.push({l:TH?'สถานะ':'State',v:stMap[s.physical_state]||s.physical_state});
    }
    if(s.substance_type)props.push({l:TH?'ประเภท':'Type',v:esc(s.substance_type)});
    if(s.appearance)props.push({l:TH?'ลักษณะ':'Appearance',v:esc(s.appearance)});
    if(s.un_class)props.push({l:'UN Class',v:esc(s.un_class)});
    if(props.length){
        propsHtml=`<div class="csd-section"><div class="csd-section-title"><i class="fas fa-atom"></i>${TH?'คุณสมบัติทางเคมี':'Chemical Properties'}</div><div class="csd-grid">${props.map(p=>`<div class="csd-item"><div class="csd-lbl">${p.l}</div><div class="csd-val">${p.v}</div></div>`).join('')}</div></div>`;
    }

    // Safety info
    let safetyHtml='';
    const safeItems=[];
    if(s.storage_requirements)safeItems.push({l:TH?'ข้อกำหนดจัดเก็บ':'Storage Requirements',v:esc(s.storage_requirements)});
    if(s.handling_procedures)safeItems.push({l:TH?'วิธีจัดการ':'Handling',v:esc(s.handling_procedures)});
    if(safeItems.length||sigHtml||ghsHtml){
        safetyHtml=`<div class="csd-section"><div class="csd-section-title"><i class="fas fa-shield-alt"></i>${TH?'ข้อมูลความปลอดภัย':'Safety Info'}</div>
            ${sigHtml?`<div style="margin-bottom:8px">${sigHtml}</div>`:''}
            ${ghsHtml}${ghsClassHtml}
            ${s.ghs_hazard_text?`<div style="margin-top:6px;font-size:11px;color:#991b1b"><i class="fas fa-exclamation-circle" style="margin-right:4px"></i>${esc(s.ghs_hazard_text)}</div>`:''}
            ${safeItems.map(si=>`<div style="margin-top:8px"><div style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:2px">${si.l}</div><div style="font-size:12px;color:#334155;line-height:1.5">${si.v}</div></div>`).join('')}
            ${s.sds_url?`<a href="${esc(s.sds_url)}" target="_blank" style="display:inline-flex;align-items:center;gap:4px;margin-top:8px;font-size:11px;color:#dc2626;font-weight:700;text-decoration:none"><i class="fas fa-file-pdf"></i> ${TH?'ดู SDS':'View SDS'}</a>`:''}
        </div>`;
    }

    // Transaction history timeline
    const allTxn=[...hist];
    // Deduplicate by id
    const seen=new Set(); const uniqTxn=allTxn.filter(t=>{if(seen.has(t.id))return false;seen.add(t.id);return true});

    let tlHtml='';
    if(uniqTxn.length){
        tlHtml=`<div class="csd-section"><div class="csd-section-title"><i class="fas fa-history"></i>${TH?'ประวัติธุรกรรม':'Transaction History'} <span style="font-size:10px;color:#94a3b8;font-weight:400">(${uniqTxn.length})</span></div>
        <div class="csd-tl">${uniqTxn.map(t=>{
            const typ=t.txn_type||'adjust';
            const icon=TXN_ICONS[typ]||'fa-circle';
            const label=TXN_LABELS[typ]||typ;
            let detail='';
            if(typ==='borrow'){
                detail=`<strong>${esc(t.to_user_name||'-')}</strong> ${TH?'ยืม':'borrowed'} ${num(t.quantity)} ${esc(t.unit||'')}`;
                if(t.purpose)detail+=` — ${esc(t.purpose)}`;
                if(t.expected_return_date)detail+=` <span style="color:#94a3b8">(${TH?'กำหนดคืน':'due'}: ${fmtDT(t.expected_return_date)})</span>`;
            }else if(typ==='return'){
                detail=`<strong>${esc(t.to_user_name||t.from_user_name||'-')}</strong> ${TH?'คืน':'returned'} ${num(t.quantity)} ${esc(t.unit||'')}`;
                if(t.return_condition)detail+=` <span class="csd-ghs-badge" style="background:#f0fdf4;color:#065f46;border-color:#bbf7d0">${esc(t.return_condition)}</span>`;
            }else if(typ==='transfer'){
                detail=`${esc(t.from_user_name||'-')} → <strong>${esc(t.to_user_name||'-')}</strong> ${num(t.quantity)} ${esc(t.unit||'')}`;
                if(t.from_department||t.to_department)detail+=` <span style="color:#94a3b8">(${esc(t.from_department||'?')} → ${esc(t.to_department||'?')})</span>`;
            }else if(typ==='dispose'){
                detail=`<strong>${esc(t.from_user_name||'-')}</strong> ${TH?'ทำลาย':'disposed'} ${num(t.quantity)} ${esc(t.unit||'')}`;
                if(t.disposal_reason)detail+=` — ${esc(t.disposal_reason)}`;
                if(t.disposal_method)detail+=` (${esc(t.disposal_method)})`;
            }else if(typ==='adjust'){
                detail=`${TH?'ปรับปริมาณ':'Adjusted'} ${num(t.quantity)} ${esc(t.unit||'')}`;
                if(t.balance_after!=null)detail+=` → ${TH?'คงเหลือ':'balance'}: ${num(t.balance_after)}`;
                if(t.purpose)detail+=` — ${esc(t.purpose)}`;
            }else if(typ==='receive'){
                detail=`<strong>${esc(t.to_user_name||'-')}</strong> ${TH?'รับเข้า':'received'} ${num(t.quantity)} ${esc(t.unit||'')}`;
            }else if(typ==='use'){
                detail=`<strong>${esc(t.from_user_name||'-')}</strong> ${TH?'ใช้':'used'} ${num(t.quantity)} ${esc(t.unit||'')}`;
                if(t.purpose)detail+=` — ${esc(t.purpose)}`;
                if(t.project_name)detail+=` <span style="color:#94a3b8">[${esc(t.project_name)}]</span>`;
            }else{
                detail=`${num(t.quantity)} ${esc(t.unit||'')}`;if(t.purpose)detail+=` — ${esc(t.purpose)}`;
            }
            const statusCls=(t.txn_status||'completed');
            const statusLbl={completed:TH?'สำเร็จ':'Done',pending:TH?'รออนุมัติ':'Pending',approved:TH?'อนุมัติ':'Approved',rejected:TH?'ปฏิเสธ':'Rejected',cancelled:TH?'ยกเลิก':'Cancelled'};
            return `<div class="csd-tl-item ${typ}">
                <div class="csd-tl-head">
                    <span class="csd-tl-type ${typ}"><i class="fas ${icon}" style="margin-right:3px"></i>${label}</span>
                    <span class="csd-tl-status ${statusCls}">${statusLbl[statusCls]||statusCls}</span>
                    ${t.txn_number?`<code style="font-size:9px;color:#94a3b8;background:#f1f5f9;padding:1px 5px;border-radius:3px">${esc(t.txn_number)}</code>`:''}
                    <span class="csd-tl-time"><i class="fas fa-clock" style="margin-right:2px"></i>${fmtDT(t.created_at)}</span>
                </div>
                <div class="csd-tl-body">${detail}</div>
                ${t.approval_notes?`<div style="margin-top:4px;font-size:10px;color:#64748b"><i class="fas fa-comment" style="margin-right:3px"></i>${esc(t.approval_notes)}</div>`:''}
                ${t.approved_by_name?`<div style="margin-top:2px;font-size:10px;color:#94a3b8"><i class="fas fa-check-circle" style="color:#059669;margin-right:3px"></i>${TH?'อนุมัติโดย':'Approved by'}: ${esc(t.approved_by_name)} ${t.approved_at?fmtDT(t.approved_at):''}</div>`:''}
            </div>`;
        }).join('')}</div></div>`;
    }else{
        tlHtml=`<div class="csd-section"><div class="csd-section-title"><i class="fas fa-history"></i>${TH?'ประวัติธุรกรรม':'Transaction History'}</div>
        <div class="csd-empty-tl"><i class="fas fa-inbox" style="font-size:24px;margin-bottom:8px;display:block"></i>${TH?'ยังไม่มีประวัติธุรกรรมสำหรับสารนี้':'No transaction history for this chemical'}</div></div>`;
    }

    md.innerHTML=`
    <div class="ls-modal-hdr">
        <i class="fas fa-flask" style="color:var(--ls-primary)"></i>
        <h3>${TH?'รายละเอียดสารเคมี':'Chemical Detail'}</h3>
        <button class="ls-modal-close" onclick="closeModal('chemDetailModal')">&times;</button>
    </div>
    <div class="ls-modal-body">
        <div class="csd-fluid">
            <div class="csd-fluid-bar">
                <div class="csd-fluid-fill" style="height:${pct}%;background:${fluidColor}"></div>
                <div class="csd-fluid-pct">${pct.toFixed(0)}%</div>
            </div>
            <div class="csd-fluid-info">
                <div class="csd-fluid-name">${esc(s.chemical_name||'-')}</div>
                <div class="csd-fluid-sub">${esc(s.cas_no||s.chem_cas||'')}${s.grade?' · '+esc(s.grade):''}</div>
                <div style="margin-top:4px"><span class="ls-badge ${sc}" style="font-size:10px">${esc(s.status||'active')}</span></div>
            </div>
        </div>

        <div class="csd-section"><div class="csd-section-title"><i class="fas fa-info-circle"></i>${TH?'ข้อมูลทั่วไป':'General Info'}</div>
        <div class="csd-grid">${fields.map(f=>`<div class="csd-item"><div class="csd-lbl">${f.l}</div><div class="csd-val">${f.v}</div></div>`).join('')}</div></div>

        ${propsHtml}
        ${safetyHtml}
        ${tlHtml}
    </div>`;
}

function fmtDT(d){if(!d)return'-';try{const dt=new Date(d);if(isNaN(dt))return esc(d);return dt.toLocaleDateString('th-TH',{day:'numeric',month:'short',year:'numeric'})+' '+dt.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'})}catch{return esc(d)}}

document.addEventListener('keydown',e=>{if(e.key==='Escape'){const m=document.getElementById('chemDetailModal');if(m&&m.classList.contains('show')){closeModal('chemDetailModal');e.stopPropagation()}}});

function dlCSV(csv,name){
    const blob=new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8;'});
    const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name;
    document.body.appendChild(a);a.click();document.body.removeChild(a);
}

/* ═══════════════════════════════════════════════
   ADMIN MANAGE TAB — CRUD + Import/Export
   ═══════════════════════════════════════════════ */

async function loadHierarchyCache(){
    try{const d=await apiFetch(API+'?action=hierarchy');if(d.success)hierarchyCache=d.data}catch(e){}
}

function populateDatalist(){
    if(!hierarchyCache)return;
    let c='',dv='',sc='';
    hierarchyCache.centers.forEach(n=>{c+=`<option value="${esc(n)}">`;});
    hierarchyCache.divisions.forEach(r=>{dv+=`<option value="${esc(r.division_name)}">`;});
    hierarchyCache.sections.forEach(r=>{sc+=`<option value="${esc(r.section_name)}">`;});
    document.getElementById('dlCenter').innerHTML=c;
    document.getElementById('dlDiv').innerHTML=dv;
    document.getElementById('dlSec').innerHTML=sc;
}

/* ── Manage Tab ── */
async function loadManageTab(){
    let el=document.getElementById('tabManage'); if(!el)return;
    if(!el.querySelector('.ls-table-wrap')){
        const opts=(dashData?.divisionSummary||[]).map(d=>`<option value="${esc(d.division_name)}">${esc(d.division_name)}</option>`).join('');
        el.innerHTML=`
            <div class="ls-admin-bar">
                <button class="ls-btn ls-btn-p" onclick="openAddStore()"><i class="fas fa-plus"></i> ${TH?'เพิ่มคลัง':'Add Store'}</button>
                <button class="ls-btn ls-btn-p" style="background:#059669" onclick="openImportModal()"><i class="fas fa-file-csv"></i> ${TH?'นำเข้า CSV':'Import CSV'}</button>
                <button class="ls-btn ls-btn-g" onclick="exportAllCSV()"><i class="fas fa-download"></i> ${TH?'ส่งออก CSV':'Export CSV'}</button>
                <button class="ls-btn ls-btn-d" id="btnBulkDel" style="display:none" onclick="bulkDeleteSelected()"><i class="fas fa-trash"></i> ${TH?'ลบที่เลือก':'Delete Selected'} (<span id="selCount">0</span>)</button>
                <span class="count-badge" id="mngTotal"></span>
            </div>
            <div class="ls-table-wrap"><div class="ls-table-header">
                <div class="ls-table-title"><i class="fas fa-cogs" style="color:var(--ls-primary)"></i> ${TH?'จัดการโครงสร้างคลัง':'Store Management'}</div>
                <div class="ls-search"><i class="fas fa-search"></i><input id="mngSearch" placeholder="${TH?'ค้นหา...':'Search...'}" oninput="onMngSrch()"></div>
                <div class="ls-filter"><select id="mngDivF" onchange="mngPage=1;loadMngData()"><option value="">${TH?'ทุกฝ่าย':'All'}</option>${opts}</select></div>
            </div>
            <div style="overflow-x:auto"><table class="ls-tbl" id="mngTable"><thead><tr>
                <th style="width:30px"><input type="checkbox" class="ls-chk" id="chkAll" onchange="toggleAll(this)"></th>
                <th onclick="sortMng('id')" style="width:50px">ID <span class="sort-icon"></span></th>
                <th onclick="sortMng('center_name')">${TH?'ศูนย์':'Center'} <span class="sort-icon"></span></th>
                <th onclick="sortMng('division_name')">${TH?'ฝ่าย':'Division'} <span class="sort-icon"></span></th>
                <th onclick="sortMng('section_name')">${TH?'งาน':'Section'} <span class="sort-icon"></span></th>
                <th onclick="sortMng('store_name')">${TH?'ชื่อคลัง':'Store'} <span class="sort-icon"></span></th>
                <th onclick="sortMng('bottle_count')" style="text-align:right">${TH?'ขวด':'Bot'} <span class="sort-icon"></span></th>
                <th onclick="sortMng('total_weight_kg')" style="text-align:right">kg <span class="sort-icon"></span></th>
                <th style="width:100px;text-align:center">${TH?'จัดการ':'Actions'}</th>
            </tr></thead><tbody id="mngBody"></tbody></table></div>
            <div class="ls-pagination" id="mngPg"></div>
            </div>`;
    }
    loadMngData();
}

async function loadMngData(){
    const body=document.getElementById('mngBody'); if(!body)return;
    body.innerHTML='<tr><td colspan="9" style="text-align:center;padding:30px;color:#ccc"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    const p=new URLSearchParams({action:'list',page:mngPage,per_page:30,sort:mngSort,sort_dir:mngSortDir});
    const s=document.getElementById('mngSearch')?.value.trim(), dv=document.getElementById('mngDivF')?.value;
    if(s)p.set('search',s); if(dv)p.set('division',dv);
    try{
        const d=await apiFetch(API+'?'+p); if(!d.success)throw new Error(d.error);
        const items=d.data.items||[], pg=d.data.pagination;
        document.getElementById('mngTotal').textContent=`${pg.total} ${TH?'รายการ':'stores'}`;
        body.innerHTML=items.length?items.map(r=>{
            const checked=selectedIds.has(r.id)?'checked':'';
            return `<tr>
                <td><input type="checkbox" class="ls-chk" data-id="${r.id}" ${checked} onchange="toggleSel(${r.id},this.checked)"></td>
                <td style="font-size:11px;color:#999">${r.id}</td>
                <td style="font-size:11px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.center_name)}">${esc((r.center_name||'').substring(0,25))}</td>
                <td><span class="ls-badge div">${esc((r.division_name||'').replace(/ฝ่าย/,'').substring(0,20))}</span></td>
                <td><span class="ls-badge sec">${esc((r.section_name||'').substring(0,20))}</span></td>
                <td><strong style="font-size:12px">${esc(r.store_name)}</strong></td>
                <td style="text-align:right;font-weight:700">${parseInt(r.bottle_count||0).toLocaleString()}</td>
                <td style="text-align:right;font-weight:600">${num(r.total_weight_kg)}</td>
                <td class="td-actions">
                    <button class="ls-act-btn edit" onclick="openEditStore(${r.id})" title="${TH?'แก้ไข':'Edit'}"><i class="fas fa-pen"></i></button>
                    <button class="ls-act-btn del" onclick="deleteSingle(${r.id})" title="${TH?'ลบ':'Delete'}"><i class="fas fa-trash"></i></button>
                </td></tr>`;
        }).join(''):`<tr><td colspan="9"><div class="ls-empty"><i class="fas fa-store"></i><p>${TH?'ไม่พบข้อมูล':'No stores'}</p></div></td></tr>`;

        const pgEl=document.getElementById('mngPg');
        if(pg&&pg.total_pages>1)pgEl.innerHTML=`<button ${pg.page<=1?'disabled':''} onclick="mngPage=${pg.page-1};loadMngData()"><i class="fas fa-chevron-left"></i></button><span class="pg-info">${pg.page}/${pg.total_pages} (${pg.total})</span><button ${pg.page>=pg.total_pages?'disabled':''} onclick="mngPage=${pg.page+1};loadMngData()"><i class="fas fa-chevron-right"></i></button>`;
        else pgEl.innerHTML=pg?`<span class="pg-info">${pg.total} ${TH?'รายการ':'items'}</span>`:'';

        // sort indicators
        const ths=document.querySelectorAll('#mngTable th');
        ths.forEach(th=>{th.classList.remove('sorted');const si=th.querySelector('.sort-icon');if(si)si.textContent=''});
        const cols=['','id','center_name','division_name','section_name','store_name','bottle_count','total_weight_kg',''];
        const si=cols.indexOf(mngSort); if(si>=0&&ths[si]){ths[si].classList.add('sorted');const ic=ths[si].querySelector('.sort-icon');if(ic)ic.textContent=mngSortDir==='asc'?'▲':'▼'}

        // update checkAll state
        const chkAll=document.getElementById('chkAll');
        if(chkAll)chkAll.checked=items.length>0&&items.every(r=>selectedIds.has(r.id));
    }catch(e){body.innerHTML=`<tr><td colspan="9" style="text-align:center;color:#dc2626">${esc(e.message)}</td></tr>`}
}

function sortMng(c){mngSort===c?(mngSortDir=mngSortDir==='desc'?'asc':'desc'):(mngSort=c,mngSortDir='desc');mngPage=1;loadMngData()}
function onMngSrch(){clearTimeout(searchTimer);searchTimer=setTimeout(()=>{mngPage=1;loadMngData()},300)}

/* ── Selection ── */
function toggleSel(id,checked){
    if(checked)selectedIds.add(id); else selectedIds.delete(id);
    updateSelUI();
}
function toggleAll(el){
    document.querySelectorAll('#mngBody .ls-chk').forEach(c=>{
        const id=parseInt(c.dataset.id); c.checked=el.checked;
        if(el.checked)selectedIds.add(id); else selectedIds.delete(id);
    });
    updateSelUI();
}
function updateSelUI(){
    const cnt=selectedIds.size;
    document.getElementById('selCount').textContent=cnt;
    document.getElementById('btnBulkDel').style.display=cnt>0?'inline-flex':'none';
}

/* ── CRUD Modal ── */
function openAddStore(){
    document.getElementById('smId').value='';
    document.getElementById('smTitle').textContent=TH?'เพิ่มคลังใหม่':'Add New Store';
    ['smCenter','smDiv','smSec','smStore','smNotes'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('smStatsRow').style.display='none';
    populateDatalist();
    openModal('storeModal');
}

async function openEditStore(id){
    try{
        const d=await apiFetch(API+'?action=detail&id='+id);
        if(!d.success)throw new Error(d.error);
        const r=d.data;
        document.getElementById('smId').value=r.id;
        document.getElementById('smTitle').textContent=TH?'แก้ไขคลัง':'Edit Store';
        document.getElementById('smCenter').value=r.center_name||'';
        document.getElementById('smDiv').value=r.division_name||'';
        document.getElementById('smSec').value=r.section_name||'';
        document.getElementById('smStore').value=r.store_name||'';
        document.getElementById('smNotes').value=r.notes||'';
        // Show auto-computed stats
        document.getElementById('smStatBottles').textContent=Number(r.bottle_count||0).toLocaleString();
        document.getElementById('smStatChems').textContent=Number(r.chemical_count||0).toLocaleString();
        document.getElementById('smStatWeight').textContent=num(r.total_weight_kg||0);
        document.getElementById('smStatsRow').style.display='';
        populateDatalist();
        openModal('storeModal');
    }catch(e){showToast(e.message,'error')}
}

async function saveStoreForm(){
    const id=document.getElementById('smId').value;
    const payload={
        center_name:document.getElementById('smCenter').value.trim(),
        division_name:document.getElementById('smDiv').value.trim(),
        section_name:document.getElementById('smSec').value.trim(),
        store_name:document.getElementById('smStore').value.trim(),
        notes:document.getElementById('smNotes').value.trim()||null
    };
    if(id)payload.id=parseInt(id);
    if(!payload.center_name||!payload.division_name||!payload.section_name||!payload.store_name){
        showToast(TH?'กรุณากรอกข้อมูลให้ครบ':'Please fill all required fields','error'); return;
    }
    try{
        const d=await apiFetch(API+'?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        if(!d.success)throw new Error(d.error);
        showToast(TH?(id?'อัพเดตเรียบร้อย':'เพิ่มเรียบร้อย'):(id?'Updated':'Added'),'success');
        closeModal('storeModal');
        loadHierarchyCache();
        loadMngData();
        goHome(); // refresh dashboard too
    }catch(e){showToast(e.message,'error')}
}

/* ── Delete confirm modal ── */
let _delId = null;

async function deleteSingle(id) {
    _delId = id;
    const body = document.getElementById('delConfirmBody');
    const btn  = document.getElementById('delConfirmBtn');
    body.innerHTML = '<div style="text-align:center;padding:24px;color:#94a3b8"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
    btn.disabled = false;
    btn.innerHTML = `<i class="fas fa-trash-alt"></i> ${TH?'ลบคลังนี้':'Delete Store'}`;
    openModal('delConfirmModal');

    try {
        const d = await apiFetch(API + '?action=detail&id=' + id);
        if (!d.success) throw new Error(d.error);
        const s = d.data;
        const hasData = (parseInt(s.bottle_count)||0) > 0;
        const bottles = parseInt(s.bottle_count||0);
        const chems   = parseInt(s.chemical_count||0);
        const weight  = parseFloat(s.total_weight_kg||0);
        const holders = parseInt(s.holder_count||0);

        body.innerHTML = `
        <!-- breadcrumb path -->
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:11px;color:#94a3b8;margin-bottom:14px">
            ${s.center_name  ? `<span style="background:#f1f5f9;padding:3px 9px;border-radius:20px;font-weight:600;color:#475569">${esc(s.center_name)}</span><i class="fas fa-chevron-right" style="font-size:8px"></i>` : ''}
            ${s.division_name? `<span style="background:#ede9fe;padding:3px 9px;border-radius:20px;font-weight:600;color:#6C5CE7">${esc(s.division_name)}</span><i class="fas fa-chevron-right" style="font-size:8px"></i>` : ''}
            ${s.section_name ? `<span style="background:#fef3c7;padding:3px 9px;border-radius:20px;font-weight:600;color:#92400e">${esc(s.section_name)}</span><i class="fas fa-chevron-right" style="font-size:8px"></i>` : ''}
        </div>

        <!-- store identity card -->
        <div style="background:linear-gradient(135deg,#fef2f2,#fff5f5);border:1.5px solid #fecaca;border-radius:14px;padding:16px 18px;margin-bottom:14px;display:flex;align-items:center;gap:14px">
            <div style="width:52px;height:52px;border-radius:14px;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
                <i class="fas fa-store"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:17px;font-weight:800;color:#1e293b;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(s.store_name)}</div>
                <div style="font-size:11px;color:#94a3b8">${TH?'คลังสารเคมี ID':'Store ID'} #${s.id}</div>
            </div>
            ${s.notes ? `<div style="font-size:10px;color:#94a3b8;max-width:120px;text-align:right;line-height:1.4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(s.notes)}">${esc(s.notes)}</div>` : ''}
        </div>

        <!-- stats grid -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px">
            <div style="background:#f8fafc;border-radius:10px;padding:10px;text-align:center;border:1px solid ${bottles>0?'#fecaca':'#f1f5f9'}">
                <div style="font-size:20px;font-weight:800;color:${bottles>0?'#dc2626':'#1e293b'}">${bottles.toLocaleString()}</div>
                <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-top:2px">${TH?'ขวด':'Bottles'}</div>
            </div>
            <div style="background:#f8fafc;border-radius:10px;padding:10px;text-align:center">
                <div style="font-size:20px;font-weight:800;color:#6C5CE7">${chems.toLocaleString()}</div>
                <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-top:2px">${TH?'สารเคมี':'Chems'}</div>
            </div>
            <div style="background:#f8fafc;border-radius:10px;padding:10px;text-align:center">
                <div style="font-size:20px;font-weight:800;color:#059669">${weight>0?weight.toFixed(2):0}</div>
                <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-top:2px">kg</div>
            </div>
            <div style="background:#f8fafc;border-radius:10px;padding:10px;text-align:center">
                <div style="font-size:20px;font-weight:800;color:#2563eb">${holders}</div>
                <div style="font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-top:2px">${TH?'ผู้ถือ':'Holders'}</div>
            </div>
        </div>

        <!-- warning banner -->
        ${hasData ? `
        <div style="background:#fef9ec;border:1.5px solid #fde68a;border-radius:11px;padding:11px 14px;display:flex;gap:10px;align-items:flex-start;margin-bottom:4px">
            <i class="fas fa-exclamation-triangle" style="color:#d97706;margin-top:2px;font-size:14px;flex-shrink:0"></i>
            <div style="font-size:12px;line-height:1.6;color:#92400e">
                <strong>${TH?'คลังนี้ยังมีสารเคมีอยู่':'This store still contains chemicals'}</strong><br>
                ${TH?`พบ <strong>${bottles} ขวด</strong> น้ำหนักรวม <strong>${weight.toFixed(2)} kg</strong> — ข้อมูลสารเคมีในระบบจะ<strong>ไม่ถูกลบ</strong> แต่จะไม่มีการเชื่อมโยงกับคลังนี้อีกต่อไป`
                    :`Found <strong>${bottles} bottles</strong> (${weight.toFixed(2)} kg) — chemical records will <strong>not be deleted</strong>, but will lose association with this store.`}
            </div>
        </div>` : `
        <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:11px;padding:11px 14px;display:flex;gap:10px;align-items:center;margin-bottom:4px">
            <i class="fas fa-check-circle" style="color:#059669;font-size:14px;flex-shrink:0"></i>
            <div style="font-size:12px;color:#065f46">${TH?'คลังนี้ไม่มีสารเคมีอยู่ สามารถลบได้อย่างปลอดภัย':'This store is empty and can be safely deleted.'}</div>
        </div>`}`;
    } catch(e) {
        body.innerHTML = `<div style="padding:16px;color:#dc2626;font-size:13px"><i class="fas fa-exclamation-circle"></i> ${esc(e.message)}</div>`;
    }
}

function closeDelConfirm() {
    closeModal('delConfirmModal');
    _delId = null;
}

async function execDeleteSingle() {
    if (!_delId) return;
    const btn = document.getElementById('delConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        const d = await apiFetch(API+'?action=delete', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:_delId})});
        if (!d.success) throw new Error(d.error);
        closeDelConfirm();
        showToast(TH?'ลบคลังเรียบร้อยแล้ว':'Store deleted successfully', 'success');
        selectedIds.delete(_delId);
        updateSelUI();
        loadMngData();
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-trash-alt"></i> ${TH?'ลบคลังนี้':'Delete Store'}`;
        showToast(e.message, 'error');
    }
}

async function bulkDeleteSelected(){
    if(!selectedIds.size)return;
    if(!confirm(TH?`ลบ ${selectedIds.size} คลังที่เลือก?`:`Delete ${selectedIds.size} selected stores?`))return;
    try{
        const d=await apiFetch(API+'?action=bulk_delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids:[...selectedIds]})});
        if(!d.success)throw new Error(d.error);
        showToast((TH?'ลบเรียบร้อย ':'Deleted ')+d.data.deleted+(TH?' รายการ':' items'),'success');
        selectedIds.clear();
        updateSelUI();
        loadMngData();
    }catch(e){showToast(e.message,'error')}
}

/* ── Modal helpers ── */
function openModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}

/* ── Import ── */
let csvFile=null;
function openImportModal(){
    csvFile=null;
    document.getElementById('csvFileInput').value='';
    document.getElementById('csvFileName').style.display='none';
    document.getElementById('btnDoImport').disabled=true;
    document.querySelector('input[name=importMode][value=append]').checked=true;
    document.getElementById('csvSkipRows').value='1';
    openModal('importModal');
}

// Drag & drop
(function(){
    const dz=document.getElementById('csvDropzone');
    if(!dz)return;
    ['dragenter','dragover'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.add('drag-over')}));
    ['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.remove('drag-over')}));
    dz.addEventListener('drop',ev=>{
        const f=ev.dataTransfer?.files?.[0];
        if(f&&f.name.endsWith('.csv')){csvFile=f;showCsvName(f.name)}
        else showToast(TH?'กรุณาเลือกไฟล์ .csv':'Please select a .csv file','error');
    });
})();

function onCsvSelected(inp){
    const f=inp.files?.[0];
    if(f){csvFile=f;showCsvName(f.name)}
}
function showCsvName(n){
    const el=document.getElementById('csvFileName');
    el.textContent='📄 '+n; el.style.display='block';
    document.getElementById('btnDoImport').disabled=false;
}

async function doImport(){
    if(!csvFile){showToast(TH?'เลือกไฟล์ก่อน':'Select a file first','error');return;}
    const mode=document.querySelector('input[name=importMode]:checked')?.value||'append';
    const skip=document.getElementById('csvSkipRows')?.value||'1';
    if(mode==='replace'&&!confirm(TH?'โหมดแทนที่จะลบข้อมูลเดิมทั้งหมด ยืนยัน?':'Replace mode will deactivate all existing data. Confirm?'))return;

    const fd=new FormData();
    fd.append('csv_file',csvFile);
    fd.append('import_mode',mode);
    fd.append('skip_rows',skip);

    document.getElementById('btnDoImport').disabled=true;
    document.getElementById('btnDoImport').innerHTML='<i class="fas fa-spinner fa-spin"></i> ...';
    try{
        const authToken=document.cookie.split('; ').find(c=>c.startsWith('auth_token='))?.split('=')[1];
        const hdrs={};
        if(authToken)hdrs['Authorization']='Bearer '+authToken;
        const resp=await fetch(API+'?action=import_csv',{
            method:'POST',
            headers:hdrs,
            body:fd
        });
        const d=await resp.json();
        if(!d.success)throw new Error(d.error);
        const r=d.data;
        showToast(`${TH?'นำเข้าสำเร็จ':'Imported'}: +${r.inserted} ${TH?'ใหม่':'new'}, ↻${r.updated} ${TH?'อัพเดต':'updated'}, ⊘${r.skipped} ${TH?'ข้าม':'skipped'}`,'success');
        closeModal('importModal');
        loadHierarchyCache();
        goHome();
    }catch(e){showToast(e.message,'error')}
    finally{
        const btn=document.getElementById('btnDoImport');
        btn.disabled=false;btn.innerHTML=TH?'นำเข้า':'Import';
    }
}

/* ── Export All ── */
function exportAllCSV(){
    const dv=document.getElementById('mngDivF')?.value||'';
    window.open(API+'?action=export_csv'+(dv?'&division='+encodeURIComponent(dv):''),'_blank');
}
</script>
</body>
</html>
