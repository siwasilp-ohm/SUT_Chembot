<?php
/**
 * Chemical Warehouses — จัดการคลังสารเคมี
 * Admin / CEO / Lab Manager: Overview, List, Map views with detail modal
 */
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$role = $user['role_name'] ?? 'user';
if (!in_array($role, ['admin', 'ceo', 'lab_manager'])) { header('Location: /v1/'); exit; }
$isAdmin = $role === 'admin';
$isCeo   = $role === 'ceo';
$lang    = I18n::getCurrentLang();
Layout::head($lang === 'th' ? 'คลังสารเคมี' : 'Chemical Warehouses');
Layout::sidebar('warehouses');
Layout::beginContent();
?>
<style>
:root{--wh:#7c3aed;--wh-l:#f3e8ff;--wh-d:#6d28d9;--wh-r:14px;--wh-rs:10px;--wh-sh:0 1px 6px rgba(0,0,0,.06);--wh-shm:0 4px 20px rgba(0,0,0,.1)}

/* ── Hero ── */
.wh-hero{background:linear-gradient(135deg,#1e0a3c 0%,#4c1d95 52%,#7c3aed 100%);border-radius:var(--wh-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.wh-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.wh-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;position:relative}
.wh-hero-info{position:relative}
.wh-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.wh-hero-info p{font-size:12px;opacity:.8;margin:0}
.wh-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0;position:relative}
.wh-hero-c{text-align:center}
.wh-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.wh-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}
.wh-hero-sep{width:1px;background:rgba(255,255,255,.2)}

/* ── Stats ── */
.wh-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.wh-stat{background:#fff;border-radius:var(--wh-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--wh-sh);border:1px solid var(--border);transition:all .15s;cursor:pointer}
.wh-stat:hover{transform:translateY(-2px);box-shadow:var(--wh-shm);border-color:var(--wh)}
.wh-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.wh-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.wh-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Tabs ── */
.wh-tabs{display:inline-flex;background:#f1f5f9;border-radius:var(--wh-rs);padding:3px}
.wh-tab{padding:8px 20px;font-size:12px;font-weight:600;color:var(--c3);border-radius:8px;cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:6px}
.wh-tab:hover{color:var(--c1)}
.wh-tab.active{background:#fff;color:var(--wh);box-shadow:0 1px 4px rgba(0,0,0,.08)}

/* ── Toolbar ── */
.wh-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}
.wh-search{flex:1;min-width:200px;position:relative}
.wh-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:var(--wh-rs);font-size:13px;background:#fff;color:var(--c1);transition:border .15s}
.wh-search input:focus{outline:none;border-color:var(--wh);box-shadow:0 0 0 3px rgba(124,58,237,.1)}
.wh-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:13px}
.wh-sel{padding:8px 10px;border:1.5px solid var(--border);border-radius:var(--wh-rs);font-size:12px;background:#fff;color:var(--c1);cursor:pointer}
.wh-sel:focus{outline:none;border-color:var(--wh)}
.wh-btn{padding:8px 16px;border:none;border-radius:var(--wh-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap}
.wh-btn-p{background:var(--wh);color:#fff}.wh-btn-p:hover{background:var(--wh-d)}
.wh-btn-g{background:#fff;color:var(--c3);border:1.5px solid var(--border)}.wh-btn-g:hover{border-color:var(--wh);color:var(--wh)}

/* ── Filter panel ── */
.wh-fp{max-height:0;overflow:hidden;transition:max-height .25s ease,margin .25s ease;background:#fff;border:1.5px solid transparent;border-radius:var(--wh-r);margin-bottom:0}
.wh-fp.show{max-height:200px;border-color:var(--border);padding:14px 16px;margin-bottom:14px;box-shadow:var(--wh-sh)}
.wh-fg{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
.wh-fl label{font-size:10px;font-weight:700;color:var(--c3);display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px}
.wh-fl select{width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff;color:var(--c1)}
.wh-fl select:focus{outline:none;border-color:var(--wh)}

/* ── Section label ── */
.wh-sec-label{font-size:11px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin:0 0 10px;display:flex;align-items:center;gap:6px}
.wh-sec-label i{color:var(--wh)}

/* ── Grand stats (overview) ── */
.wh-grand-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px}
.wh-grand-card{display:flex;align-items:center;gap:14px;padding:18px 16px;border-radius:var(--wh-r);background:#fff;border:1.5px solid var(--border);box-shadow:var(--wh-sh);transition:all .18s}
.wh-grand-card:hover{transform:translateY(-2px);box-shadow:var(--wh-shm)}
.wh-gc-icon{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.wh-gc-val{font-size:24px;font-weight:900;color:var(--c1);line-height:1}
.wh-gc-lbl{font-size:11px;color:var(--c3);font-weight:500;margin-top:3px}

/* ── Two-col grid ── */
.wh-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}

/* ── Content card ── */
.wh-card-panel{background:#fff;border-radius:var(--wh-r);border:1.5px solid var(--border);box-shadow:var(--wh-sh);overflow:hidden}
.wh-card-hdr{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.wh-card-hdr h3{font-size:13px;font-weight:700;color:var(--c1);margin:0;flex:1}
.wh-card-body{padding:0}

/* ── Division bar ── */
.wh-div-item{display:flex;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid #f5f7fa;transition:background .12s;cursor:pointer}
.wh-div-item:last-child{border-bottom:none}
.wh-div-item:hover{background:#faf5ff}
.wh-div-bar-wrap{flex:1;min-width:0}
.wh-div-name{font-size:12px;font-weight:600;color:var(--c1);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wh-div-bar{height:7px;background:#f1f5f9;border-radius:4px;overflow:hidden}
.wh-div-bar-fill{height:100%;border-radius:4px;transition:width .6s ease}
.wh-div-stats{display:flex;gap:14px;flex-shrink:0}
.wh-div-stat{text-align:right}
.wh-div-stat-val{font-size:13px;font-weight:800;color:var(--c1)}
.wh-div-stat-lbl{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.3px}

/* ── Top list ── */
.wh-top-item{display:flex;align-items:center;gap:10px;padding:9px 18px;border-bottom:1px solid #f5f7fa;transition:background .12s;cursor:pointer}
.wh-top-item:last-child{border-bottom:none}
.wh-top-item:hover{background:#faf5ff}
.wh-top-rank{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.wh-top-rank.gold{background:#fef3c7;color:#92400e}
.wh-top-rank.silver{background:#f1f5f9;color:#475569}
.wh-top-rank.bronze{background:#fed7aa;color:#9a3412}
.wh-top-rank.other{background:#f9fafb;color:var(--c3)}
.wh-top-info{flex:1;min-width:0}
.wh-top-name{font-size:12px;font-weight:600;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wh-top-sub{font-size:10px;color:var(--c3);margin-top:1px}
.wh-top-val{font-size:14px;font-weight:900;color:var(--wh);text-align:right}
.wh-top-unit{font-size:9px;color:var(--c3);text-align:right}

/* ── Building overview grid ── */
.wh-bld-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;padding:14px}
.wh-bld-card{padding:14px;background:var(--bg);border-radius:10px;border:1.5px solid var(--border);transition:all .18s;cursor:pointer}
.wh-bld-card:hover{border-color:var(--wh);background:var(--wh-l);box-shadow:0 2px 12px rgba(124,58,237,.1)}
.wh-bld-name{font-size:13px;font-weight:700;color:var(--c1);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.wh-bld-name i{color:var(--wh);font-size:12px}
.wh-bld-stats{display:grid;grid-template-columns:1fr 1fr;gap:5px}
.wh-bld-stat{font-size:11px;color:var(--c3)}
.wh-bld-stat b{color:var(--c1);font-weight:700}

/* ── List grid ── */
.wh-list-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px}
.wh-list-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--wh-r);padding:16px;transition:all .18s;cursor:pointer;position:relative;overflow:hidden}
.wh-list-card:hover{border-color:var(--wh);box-shadow:var(--wh-shm);transform:translateY(-2px)}
.wh-list-card-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}
.wh-list-card-name{font-size:14px;font-weight:700;color:var(--c1);flex:1;margin-right:8px;line-height:1.3}
.wh-list-card-badge{font-size:9px;font-weight:700;padding:3px 8px;border-radius:6px;white-space:nowrap;flex-shrink:0}
.badge-has{background:#dcfce7;color:#15803d}
.badge-none{background:#f1f5f9;color:var(--c3)}
.wh-list-card-path{font-size:11px;color:var(--c3);margin-bottom:10px;line-height:1.5;display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.wh-list-card-path i{font-size:8px;color:var(--border)}
.wh-list-card-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;padding-top:10px;border-top:1px solid #f1f5f9}
.wh-list-stat{text-align:center}
.wh-list-stat-val{font-size:16px;font-weight:800;color:var(--c1)}
.wh-list-stat-lbl{font-size:9px;color:var(--c3);text-transform:uppercase;font-weight:600;letter-spacing:.3px}
.wh-list-stat-val.zero{color:#d4d4d8}
.wh-list-bar{position:absolute;bottom:0;left:0;right:0;height:3px;background:#f1f5f9}
.wh-list-bar-fill{height:100%;transition:width .5s}

/* ── Map view ── */
.wh-bmap-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--wh-r);padding:20px;margin-bottom:14px;box-shadow:var(--wh-sh)}
.wh-bmap-hdr{display:flex;align-items:center;gap:12px;margin-bottom:14px;cursor:pointer}
.wh-bmap-icon{width:44px;height:44px;border-radius:12px;background:var(--wh-l);color:var(--wh);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.wh-bmap-title{font-size:15px;font-weight:700;color:var(--c1)}
.wh-bmap-sub{font-size:11px;color:var(--c3);margin-top:2px}
.wh-bmap-summ{display:flex;gap:16px;margin-left:auto;flex-shrink:0}
.wh-bmap-summ-item{text-align:center}
.wh-bmap-summ-item .v{font-size:18px;font-weight:900;color:var(--c1)}
.wh-bmap-summ-item .l{font-size:9px;color:var(--c3);text-transform:uppercase}
.wh-bmap-wh-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px}
.wh-bmap-wh{padding:10px 14px;background:var(--bg);border-radius:9px;border:1.5px solid var(--border);font-size:12px;cursor:pointer;transition:all .15s;display:flex;justify-content:space-between;align-items:center;gap:8px}
.wh-bmap-wh:hover{background:var(--wh-l);border-color:var(--wh)}
.wh-bmap-wh-name{font-weight:600;color:var(--c1);flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wh-bmap-wh-kg{font-weight:800;color:var(--wh);font-size:12px;white-space:nowrap;flex-shrink:0}

/* ── Empty / Spinner ── */
.wh-empty{text-align:center;padding:48px 20px;color:var(--c3)}
.wh-empty i{font-size:32px;opacity:.3;display:block;margin-bottom:10px}
.wh-empty p{font-size:13px;margin:0}
.wh-spin{text-align:center;padding:40px;color:var(--c3)}
.wh-spin i{font-size:22px;opacity:.4;display:block;margin-bottom:10px}

/* ── Detail Modal ── */
.wh-modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:1000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
.wh-modal-bg.show{opacity:1;pointer-events:auto}
.wh-modal{background:#fff;border-radius:18px;width:980px;max-width:96vw;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 24px 64px rgba(0,0,0,.25);transform:translateY(20px) scale(.97);transition:transform .22s}
.wh-modal-bg.show .wh-modal{transform:none}
.wh-modal-hdr{display:flex;align-items:center;gap:12px;padding:18px 22px;border-bottom:1px solid var(--border);flex-shrink:0}
.wh-modal-hdr-ic{width:40px;height:40px;border-radius:11px;background:var(--wh-l);color:var(--wh);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.wh-modal-title{font-size:15px;font-weight:800;color:var(--c1);flex:1}
.wh-modal-close{width:32px;height:32px;border-radius:9px;border:none;background:var(--bg);color:var(--c2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;font-size:14px}
.wh-modal-close:hover{background:#e2e8f0;color:var(--c1)}
.wh-modal-body{overflow-y:auto;flex:1;padding:20px 22px}

/* ── Detail hero ── */
.whd-hero{background:linear-gradient(135deg,#1e0a3c 0%,#4c1d95 50%,#7c3aed 100%);border-radius:var(--wh-r);padding:20px 24px;color:#fff;margin-bottom:16px;position:relative;overflow:hidden}
.whd-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.whd-hero-inner{position:relative}
.whd-hero h2{font-size:17px;font-weight:800;margin:0 0 3px}
.whd-hero-path{font-size:11px;opacity:.7;margin-bottom:14px;line-height:1.5}
.whd-hero-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.whd-hero-stat{text-align:center;background:rgba(255,255,255,.12);border-radius:10px;padding:10px 6px;backdrop-filter:blur(4px)}
.whd-hero-stat .v{font-size:20px;font-weight:900;line-height:1.1}
.whd-hero-stat .l{font-size:10px;opacity:.7;margin-top:3px;text-transform:uppercase;letter-spacing:.4px}

/* ── Detail tabs ── */
.whd-tabs{display:flex;gap:3px;margin-bottom:16px;background:#f1f5f9;border-radius:10px;padding:3px;overflow-x:auto}
.whd-tab{flex:1;padding:8px 12px;border-radius:8px;border:none;background:none;cursor:pointer;font-size:12px;font-weight:600;color:var(--c3);transition:.2s;white-space:nowrap;display:flex;align-items:center;gap:5px;justify-content:center;font-family:inherit}
.whd-tab.active{background:#fff;color:var(--c1);box-shadow:0 1px 3px rgba(0,0,0,.1)}
.whd-tab i{font-size:11px}
.whd-tab .cnt{background:#e2e8f0;color:var(--c3);border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700}
.whd-tab.active .cnt{background:var(--wh);color:#fff}

/* ── Detail filter bar ── */
.whd-filter-bar{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center}
.whd-search-wrap{position:relative;flex:1;min-width:180px}
.whd-search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:12px}
.whd-search-wrap input{width:100%;padding:7px 10px 7px 30px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff;outline:none;transition:.2s}
.whd-search-wrap input:focus{border-color:var(--wh);box-shadow:0 0 0 3px rgba(124,58,237,.1)}
.whd-filter-bar select{font-size:12px;padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;background:#fff;outline:none;transition:.2s}
.whd-filter-bar select:focus{border-color:var(--wh)}

/* ── Detail table ── */
.whd-tbl{width:100%;border-collapse:collapse;font-size:12px}
.whd-tbl thead{position:sticky;top:0;z-index:2}
.whd-tbl th{background:#f8fafc;padding:9px 11px;text-align:left;font-weight:700;color:var(--c3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border)}
.whd-tbl td{padding:9px 11px;border-bottom:1px solid #f1f5f9;vertical-align:middle;color:var(--c1)}
.whd-tbl tbody tr{transition:background .1s;cursor:pointer}
.whd-tbl tbody tr:hover td{background:#faf5ff}
.whd-tbl tbody tr:last-child td{border-bottom:none}
.whd-barcode{font-family:'Courier New',monospace;font-size:11px;color:#6366f1;font-weight:600;background:#eef2ff;padding:2px 7px;border-radius:5px;display:inline-block}
.whd-cas{font-family:monospace;font-size:11px;color:var(--c3)}
.whd-chem-name{font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.whd-status{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase}
.whd-status-dot{width:6px;height:6px;border-radius:50%}
.whd-status.active{background:#dcfce7;color:#15803d}.whd-status.active .whd-status-dot{background:#22c55e}
.whd-status.low{background:#fef3c7;color:#b45309}.whd-status.low .whd-status-dot{background:#f59e0b}
.whd-status.empty{background:#fee2e2;color:#dc2626}.whd-status.empty .whd-status-dot{background:#ef4444}
.whd-status.expired{background:#fce7f3;color:#be185d}.whd-status.expired .whd-status-dot{background:#ec4899}
.whd-status.disposed{background:#f1f5f9;color:var(--c3)}.whd-status.disposed .whd-status-dot{background:var(--c3)}
.whd-pct-bar{width:48px;height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;display:inline-block;vertical-align:middle;margin-left:4px}
.whd-pct-fill{height:100%;border-radius:3px}
.whd-owner-av{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--wh),#a78bfa);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:8px;font-weight:800;flex-shrink:0}

/* ── Detail info grid ── */
.whd-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.whd-info-item{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg);border-radius:9px;font-size:12px}
.whd-info-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.whd-info-item .lbl{color:var(--c3);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.whd-info-item .val{color:var(--c1);font-weight:700;font-size:13px}

/* ── Mini stat cards ── */
.whd-mini-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;margin-bottom:14px}
.whd-mini-card{text-align:center;padding:10px 8px;border-radius:9px;background:var(--bg);border:1.5px solid var(--border)}
.whd-mini-card .v{font-size:18px;font-weight:900;color:var(--c1)}
.whd-mini-card .l{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.3px;margin-top:2px}

/* ── Top chemicals list ── */
.whd-top-item{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #f5f7fa;font-size:12px}
.whd-top-item:last-child{border-bottom:none}
.whd-top-rank{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;flex-shrink:0}
.whd-top-rank.r1{background:#fef3c7;color:#b45309}
.whd-top-rank.r2{background:#e2e8f0;color:#475569}
.whd-top-rank.r3{background:#fed7aa;color:#c2410c}
.whd-top-rank.rn{background:#f1f5f9;color:var(--c3)}
.whd-top-name{flex:1;font-weight:600;color:var(--c1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.whd-top-val{font-weight:700;color:var(--wh);white-space:nowrap}

/* ── Owner grid ── */
.whd-owner-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-bottom:14px}
.whd-owner-card{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg);border-radius:10px;border:1.5px solid var(--border);transition:.15s;cursor:pointer}
.whd-owner-card:hover{border-color:var(--wh);background:var(--wh-l)}
.whd-owner-card .av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--wh),#a78bfa);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0}
.whd-owner-card .name{font-weight:700;font-size:12px;color:var(--c1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.whd-owner-card .sub{font-size:10px;color:var(--c3)}

/* ── Pagination ── */
.wh-page{display:flex;justify-content:space-between;align-items:center;padding:10px 0;font-size:12px;color:var(--c3)}
.wh-page button{padding:5px 12px;border-radius:7px;border:1.5px solid var(--border);background:#fff;cursor:pointer;font-size:11px;font-weight:600;color:var(--c2);transition:.15s}
.wh-page button:hover:not(:disabled){border-color:var(--wh);color:var(--wh)}
.wh-page button:disabled{opacity:.4;cursor:default}

/* ── Siblings ── */
.whd-siblings{margin-top:14px;padding-top:14px;border-top:1px solid #f0f0f0}
.whd-siblings-hdr{font-size:11px;font-weight:700;color:var(--c3);margin-bottom:8px;display:flex;align-items:center;gap:5px}
.whd-sib-item{display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:1px solid #f9f9f9;cursor:pointer;transition:.12s}
.whd-sib-item:hover{color:var(--wh)}

/* ── Responsive ── */
@media(max-width:1024px){.wh-grand-grid{grid-template-columns:repeat(3,1fr)}.wh-grid-2{grid-template-columns:1fr}}
@media(max-width:768px){.wh-grand-grid{grid-template-columns:repeat(2,1fr)}.wh-list-grid{grid-template-columns:1fr}.wh-hero-meta{display:none}.wh-bmap-summ{flex-direction:column;gap:4px;margin-left:0}.whd-info-grid{grid-template-columns:1fr}.whd-hero-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.wh-grand-grid{grid-template-columns:1fr 1fr}.wh-grand-grid .wh-grand-card:last-child{grid-column:1/-1}}
</style>

<!-- ── Hero ── -->
<div class="wh-hero">
    <div class="wh-hero-ic"><i class="fas fa-warehouse"></i></div>
    <div class="wh-hero-info">
        <h2><?= $lang === 'th' ? 'คลังสารเคมี' : 'Chemical Warehouses' ?></h2>
        <p><?= $lang === 'th' ? 'ภาพรวมคลังสารเคมีทั้งหมดในองค์กร — จัดการ ติดตาม และวิเคราะห์' : 'Organization-wide warehouse overview — manage, track & analyze' ?></p>
    </div>
    <div class="wh-hero-meta">
        <div class="wh-hero-c"><div class="v" id="hmTotal">—</div><div class="lb">คลัง</div></div>
        <div class="wh-hero-sep"></div>
        <div class="wh-hero-c"><div class="v" id="hmBottles">—</div><div class="lb">ขวด</div></div>
        <div class="wh-hero-sep"></div>
        <div class="wh-hero-c"><div class="v" id="hmKg">—</div><div class="lb">kg</div></div>
    </div>
</div>

<!-- ── Stats ── -->
<div class="wh-stats">
    <div class="wh-stat" onclick="switchView('list')">
        <div class="wh-si" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-warehouse"></i></div>
        <div><div class="wh-sv" id="stTotal">—</div><div class="wh-sl">คลังทั้งหมด</div></div>
    </div>
    <div class="wh-stat" onclick="switchView('list');document.getElementById('whStockFilter').value='1';loadWarehouses()">
        <div class="wh-si" style="background:#dcfce7;color:#15803d"><i class="fas fa-box-open"></i></div>
        <div><div class="wh-sv" id="stActive">—</div><div class="wh-sl">มีสารเคมี</div></div>
    </div>
    <div class="wh-stat">
        <div class="wh-si" style="background:#dbeafe;color:#2563eb"><i class="fas fa-wine-bottle"></i></div>
        <div><div class="wh-sv" id="stBottles">—</div><div class="wh-sl">ขวดสารเคมี</div></div>
    </div>
    <div class="wh-stat">
        <div class="wh-si" style="background:#fef3c7;color:#d97706"><i class="fas fa-flask"></i></div>
        <div><div class="wh-sv" id="stChemicals">—</div><div class="wh-sl">ชนิดสารเคมี</div></div>
    </div>
    <div class="wh-stat">
        <div class="wh-si" style="background:#fce7f3;color:#be185d"><i class="fas fa-weight-hanging"></i></div>
        <div><div class="wh-sv" id="stWeight">—</div><div class="wh-sl">ปริมาณ (kg)</div></div>
    </div>
</div>

<!-- ── Tabs + Toolbar ── -->
<div class="wh-toolbar">
    <div class="wh-tabs">
        <button class="wh-tab active" data-view="overview" onclick="switchView('overview')">
            <i class="fas fa-chart-pie"></i> ภาพรวม
        </button>
        <button class="wh-tab" data-view="list" onclick="switchView('list')">
            <i class="fas fa-th-large"></i> รายการคลัง
        </button>
        <button class="wh-tab" data-view="map" onclick="switchView('map')">
            <i class="fas fa-building"></i> ตามอาคาร
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════
     VIEW: OVERVIEW
══════════════════════════════════════════ -->
<div id="viewOverview">
    <!-- Grand stats -->
    <div class="wh-grand-grid">
        <div class="wh-grand-card">
            <div class="wh-gc-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-warehouse"></i></div>
            <div><div class="wh-gc-val" id="gTotal">—</div><div class="wh-gc-lbl">คลังสารเคมีทั้งหมด</div></div>
        </div>
        <div class="wh-grand-card">
            <div class="wh-gc-icon" style="background:#dcfce7;color:#15803d"><i class="fas fa-box-open"></i></div>
            <div><div class="wh-gc-val" id="gActive">—</div><div class="wh-gc-lbl">คลังที่มีสารเคมี</div></div>
        </div>
        <div class="wh-grand-card">
            <div class="wh-gc-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-wine-bottle"></i></div>
            <div><div class="wh-gc-val" id="gBottles">—</div><div class="wh-gc-lbl">ขวดสารเคมี</div></div>
        </div>
        <div class="wh-grand-card">
            <div class="wh-gc-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-flask"></i></div>
            <div><div class="wh-gc-val" id="gChemicals">—</div><div class="wh-gc-lbl">ชนิดสารเคมี</div></div>
        </div>
        <div class="wh-grand-card">
            <div class="wh-gc-icon" style="background:#fce7f3;color:#be185d"><i class="fas fa-weight-hanging"></i></div>
            <div><div class="wh-gc-val" id="gWeight">—</div><div class="wh-gc-lbl">ปริมาณรวม (kg)</div></div>
        </div>
    </div>

    <!-- Division + Top list -->
    <div class="wh-grid-2">
        <div class="wh-card-panel">
            <div class="wh-card-hdr">
                <i class="fas fa-sitemap" style="color:var(--wh)"></i>
                <h3>สัดส่วนตามฝ่าย</h3>
            </div>
            <div class="wh-card-body" id="divisionChart">
                <div class="wh-spin"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
        <div class="wh-card-panel">
            <div class="wh-card-hdr">
                <i class="fas fa-trophy" style="color:#d97706"></i>
                <h3>Top 10 คลัง (ปริมาณ kg)</h3>
            </div>
            <div class="wh-card-body" id="topWeightList">
                <div class="wh-spin"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>

    <!-- Building overview -->
    <div class="wh-card-panel">
        <div class="wh-card-hdr">
            <i class="fas fa-building" style="color:#6c5ce7"></i>
            <h3>สารเคมีตามอาคาร</h3>
        </div>
        <div class="wh-card-body" id="buildingGrid">
            <div class="wh-spin"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     VIEW: LIST
══════════════════════════════════════════ -->
<div id="viewList" style="display:none">
    <!-- Filter toolbar -->
    <div class="wh-toolbar">
        <div class="wh-search">
            <i class="fas fa-search"></i>
            <input type="text" id="whSearch" placeholder="ค้นหาคลังสารเคมี..." oninput="debounceSearch()">
        </div>
        <select id="whDivFilter" class="wh-sel" onchange="loadWarehouses()">
            <option value="">ทุกฝ่าย</option>
        </select>
        <select id="whStockFilter" class="wh-sel" onchange="loadWarehouses()">
            <option value="">สถานะทั้งหมด</option>
            <option value="1">มีสารเคมี</option>
            <option value="0">ว่างเปล่า</option>
        </select>
        <select id="whSortBy" class="wh-sel" onchange="loadWarehouses()">
            <option value="weight">เรียงตามน้ำหนัก (kg)</option>
            <option value="bottles">เรียงตามจำนวนขวด</option>
            <option value="chemicals">เรียงตามชนิดสาร</option>
            <option value="name">เรียงตามชื่อ A-Z</option>
            <option value="division">เรียงตามฝ่าย</option>
        </select>
        <button class="wh-btn wh-btn-g" onclick="document.getElementById('whSearch').value='';loadWarehouses()">
            <i class="fas fa-rotate-left"></i> รีเซ็ต
        </button>
    </div>
    <div id="whListGrid" class="wh-list-grid">
        <div class="wh-spin" style="grid-column:1/-1"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     VIEW: MAP
══════════════════════════════════════════ -->
<div id="viewMap" style="display:none">
    <div id="buildingMapDetail">
        <div class="wh-spin"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     DETAIL MODAL
══════════════════════════════════════════ -->
<div class="wh-modal-bg" id="whDetailModal">
    <div class="wh-modal">
        <div class="wh-modal-hdr">
            <div class="wh-modal-hdr-ic"><i class="fas fa-warehouse"></i></div>
            <div class="wh-modal-title" id="whDetailTitle">รายละเอียดคลัง</div>
            <button class="wh-modal-close" onclick="closeWhDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="wh-modal-body" id="whDetailContent"></div>
    </div>
</div>

<script>
let overviewData  = null;
let allWarehouses = [];
let divisionsList = [];
let searchTimer   = null;

/* ═══════════════════════════════════
   VIEW SWITCHING
═══════════════════════════════════ */
function switchView(v) {
    document.querySelectorAll('.wh-tab').forEach(b => b.classList.toggle('active', b.dataset.view === v));
    document.getElementById('viewOverview').style.display = v === 'overview' ? '' : 'none';
    document.getElementById('viewList').style.display     = v === 'list'     ? '' : 'none';
    document.getElementById('viewMap').style.display      = v === 'map'      ? '' : 'none';
    if (v === 'list' && !allWarehouses.length) loadWarehouses();
    if (v === 'map'  && !document.getElementById('buildingMapDetail').dataset.loaded) loadBuildingMap();
}

/* ═══════════════════════════════════
   OVERVIEW
═══════════════════════════════════ */
async function loadOverview() {
    try {
        const res = await apiFetch('/v1/api/warehouses.php?action=overview');
        if (!res.success) throw new Error(res.error);
        overviewData = res.data;
        const t = overviewData.totals;

        // Hero
        document.getElementById('hmTotal').textContent   = (parseInt(t.total_warehouses)||0).toLocaleString('th-TH');
        document.getElementById('hmBottles').textContent = (parseInt(t.total_bottles)||0).toLocaleString('th-TH');
        document.getElementById('hmKg').textContent      = parseFloat(t.total_weight_kg||0).toLocaleString('th-TH',{maximumFractionDigits:0});

        // Stats row
        anim('stTotal',    t.total_warehouses);
        anim('stActive',   t.active_warehouses);
        anim('stBottles',  t.total_bottles);
        anim('stChemicals',t.total_chemicals);
        document.getElementById('stWeight').textContent = parseFloat(t.total_weight_kg||0).toLocaleString('th-TH',{maximumFractionDigits:1});

        // Grand stats (overview section)
        anim('gTotal',    t.total_warehouses);
        anim('gActive',   t.active_warehouses);
        anim('gBottles',  t.total_bottles);
        anim('gChemicals',t.total_chemicals);
        document.getElementById('gWeight').textContent = parseFloat(t.total_weight_kg||0).toLocaleString('th-TH',{minimumFractionDigits:2});

        renderDivisionChart(overviewData.by_division);
        renderTopList(overviewData.top_by_weight);
        renderBuildingOverview(overviewData.by_building);
    } catch (e) { console.error('Overview error:', e); }
}

function anim(id, target) {
    const el = document.getElementById(id);
    target = parseInt(target) || 0;
    if (!el) return;
    if (target === 0) { el.textContent = '0'; return; }
    let cur = 0;
    const step = Math.max(1, Math.ceil(target / 40));
    const iv = setInterval(() => {
        cur += step;
        if (cur >= target) { cur = target; clearInterval(iv); }
        el.textContent = cur.toLocaleString('th-TH');
    }, 25);
}

const divColors = ['#7c3aed','#2563eb','#059669','#d97706','#dc2626','#0891b2','#be185d','#65a30d','#9333ea','#ea580c'];

function renderDivisionChart(divs) {
    if (!divs || !divs.length) { document.getElementById('divisionChart').innerHTML = '<div class="wh-empty"><i class="fas fa-sitemap"></i><p>ไม่มีข้อมูลฝ่าย</p></div>'; return; }
    const maxW = Math.max(...divs.map(d => parseFloat(d.weight_kg)||0), 1);
    document.getElementById('divisionChart').innerHTML = divs.map((d, i) => {
        const pct   = parseFloat(d.weight_kg) / maxW * 100;
        const color = divColors[i % divColors.length];
        const name  = (d.division_name || '').replace(/^ฝ่าย/, '');
        return `<div class="wh-div-item" onclick="filterByDivision(${d.division_id})">
            <div style="width:10px;height:10px;border-radius:3px;background:${color};flex-shrink:0"></div>
            <div class="wh-div-bar-wrap">
                <div class="wh-div-name" title="${d.division_name}">${esc(name)}</div>
                <div class="wh-div-bar"><div class="wh-div-bar-fill" style="width:${pct}%;background:${color}"></div></div>
            </div>
            <div class="wh-div-stats">
                <div class="wh-div-stat"><div class="wh-div-stat-val">${parseInt(d.warehouse_count)}</div><div class="wh-div-stat-lbl">คลัง</div></div>
                <div class="wh-div-stat"><div class="wh-div-stat-val">${parseInt(d.bottles).toLocaleString()}</div><div class="wh-div-stat-lbl">ขวด</div></div>
                <div class="wh-div-stat"><div class="wh-div-stat-val">${parseFloat(d.weight_kg).toLocaleString('th-TH',{maximumFractionDigits:0})}</div><div class="wh-div-stat-lbl">kg</div></div>
            </div>
        </div>`;
    }).join('');
}

function renderTopList(items) {
    if (!items || !items.length) { document.getElementById('topWeightList').innerHTML = '<div class="wh-empty"><i class="fas fa-trophy"></i><p>ไม่มีข้อมูล</p></div>'; return; }
    document.getElementById('topWeightList').innerHTML = items.map((w, i) => {
        const rankCls = i === 0 ? 'gold' : i === 1 ? 'silver' : i === 2 ? 'bronze' : 'other';
        return `<div class="wh-top-item" onclick="showWhDetail(${w.id})">
            <div class="wh-top-rank ${rankCls}">${i + 1}</div>
            <div class="wh-top-info">
                <div class="wh-top-name" title="${esc(w.name)}">${esc(w.name)}</div>
                <div class="wh-top-sub">${esc(w.unit_name || w.division_name || '')}${w.building ? ' · ' + w.building : ''}</div>
            </div>
            <div>
                <div class="wh-top-val">${parseFloat(w.total_weight_kg).toLocaleString('th-TH',{minimumFractionDigits:2})}</div>
                <div class="wh-top-unit">kg · ${parseInt(w.total_bottles)} ขวด</div>
            </div>
        </div>`;
    }).join('');
}

function renderBuildingOverview(buildings) {
    const el = document.getElementById('buildingGrid');
    if (!buildings || !buildings.length) { el.innerHTML = '<div class="wh-empty"><i class="fas fa-building"></i><p>ไม่มีข้อมูลอาคาร</p></div>'; return; }
    el.innerHTML = `<div class="wh-bld-grid">${buildings.map(b => `
        <div class="wh-bld-card" onclick="filterByBuilding(${b.building_id})">
            <div class="wh-bld-name"><i class="fas fa-building"></i>${esc(b.shortname || b.building_name)}</div>
            <div class="wh-bld-stats">
                <div class="wh-bld-stat"><b>${parseInt(b.warehouse_count)}</b> คลัง</div>
                <div class="wh-bld-stat"><b>${parseInt(b.bottles).toLocaleString()}</b> ขวด</div>
                <div class="wh-bld-stat"><b>${parseInt(b.chemicals)}</b> ชนิด</div>
                <div class="wh-bld-stat"><b>${parseFloat(b.weight_kg).toLocaleString('th-TH',{maximumFractionDigits:0})}</b> kg</div>
            </div>
        </div>`).join('')}</div>`;
}

/* ═══════════════════════════════════
   LIST VIEW
═══════════════════════════════════ */
async function loadWarehouses() {
    const div    = document.getElementById('whDivFilter').value;
    const stock  = document.getElementById('whStockFilter').value;
    const sort   = document.getElementById('whSortBy').value;
    const search = document.getElementById('whSearch').value.trim();
    const params = new URLSearchParams({ action:'list', sort });
    if (div)    params.set('division', div);
    if (stock)  params.set('has_stock', stock);
    if (search) params.set('search', search);
    document.getElementById('whListGrid').innerHTML = '<div class="wh-spin" style="grid-column:1/-1"><i class="fas fa-spinner fa-spin"></i></div>';
    try {
        const res = await apiFetch('/v1/api/warehouses.php?' + params);
        if (!res.success) throw new Error(res.error);
        allWarehouses = res.data;
        renderWarehouseGrid(allWarehouses);
    } catch (e) {
        document.getElementById('whListGrid').innerHTML = `<div class="wh-empty" style="grid-column:1/-1"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`;
    }
}

function renderWarehouseGrid(items) {
    const el = document.getElementById('whListGrid');
    if (!items.length) {
        el.innerHTML = '<div class="wh-empty" style="grid-column:1/-1"><i class="fas fa-warehouse"></i><p>ไม่พบข้อมูลคลังสารเคมี</p></div>';
        return;
    }
    const maxW = Math.max(...items.map(w => parseFloat(w.total_weight_kg)||0), 1);
    el.innerHTML = items.map(w => {
        const hasStock = parseInt(w.total_bottles) > 0;
        const pct      = parseFloat(w.total_weight_kg) / maxW * 100;
        const barColor = pct > 70 ? '#ef4444' : pct > 40 ? '#f59e0b' : '#22c55e';
        const divName  = (w.division_name || w.div_name || '').replace(/^ฝ่าย/, '');
        return `<div class="wh-list-card" onclick="showWhDetail(${w.id})">
            <div class="wh-list-card-top">
                <div class="wh-list-card-name">${esc(w.name)}</div>
                <span class="wh-list-card-badge ${hasStock ? 'badge-has' : 'badge-none'}">${hasStock ? 'มีสารเคมี' : 'ว่างเปล่า'}</span>
            </div>
            <div class="wh-list-card-path">
                ${divName ? `<span>${esc(divName)}</span>` : ''}
                ${w.unit_name || w.dept_name ? `<i class="fas fa-chevron-right"></i><span>${esc(w.unit_name || w.dept_name)}</span>` : ''}
                ${w.building_short ? `<i class="fas fa-building" style="margin-left:2px"></i><span>${esc(w.building_short)}</span>` : ''}
            </div>
            <div class="wh-list-card-stats">
                <div class="wh-list-stat">
                    <div class="wh-list-stat-val ${!hasStock?'zero':''}">${parseInt(w.total_bottles).toLocaleString()}</div>
                    <div class="wh-list-stat-lbl">ขวด</div>
                </div>
                <div class="wh-list-stat">
                    <div class="wh-list-stat-val ${!hasStock?'zero':''}">${parseInt(w.total_chemicals).toLocaleString()}</div>
                    <div class="wh-list-stat-lbl">ชนิด</div>
                </div>
                <div class="wh-list-stat">
                    <div class="wh-list-stat-val">${parseFloat(w.total_weight_kg).toLocaleString('th-TH',{minimumFractionDigits:2})}</div>
                    <div class="wh-list-stat-lbl">kg</div>
                </div>
            </div>
            <div class="wh-list-bar"><div class="wh-list-bar-fill" style="width:${pct}%;background:${barColor}"></div></div>
        </div>`;
    }).join('');
}

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadWarehouses, 300);
}

/* ═══════════════════════════════════
   MAP VIEW
═══════════════════════════════════ */
async function loadBuildingMap() {
    const el = document.getElementById('buildingMapDetail');
    el.dataset.loaded = '1';
    try {
        const [ovRes, whRes] = await Promise.all([
            apiFetch('/v1/api/warehouses.php?action=overview'),
            apiFetch('/v1/api/warehouses.php?action=list&sort=weight')
        ]);
        const buildings  = ovRes.data.by_building || [];
        const warehouses = whRes.data || [];
        const byBld = {};
        warehouses.forEach(w => { const bid = w.building_id || 'none'; if (!byBld[bid]) byBld[bid] = []; byBld[bid].push(w); });

        let html = '';
        buildings.forEach(b => {
            const whs = byBld[b.building_id] || [];
            html += `<div class="wh-bmap-card">
                <div class="wh-bmap-hdr" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'':'none'">
                    <div class="wh-bmap-icon"><i class="fas fa-building"></i></div>
                    <div>
                        <div class="wh-bmap-title">${esc(b.building_name)}</div>
                        <div class="wh-bmap-sub">${esc(b.shortname||'')} · ${b.warehouse_count} คลัง</div>
                    </div>
                    <div class="wh-bmap-summ">
                        <div class="wh-bmap-summ-item"><div class="v">${parseInt(b.bottles).toLocaleString()}</div><div class="l">ขวด</div></div>
                        <div class="wh-bmap-summ-item"><div class="v">${parseFloat(b.weight_kg).toLocaleString('th-TH',{maximumFractionDigits:0})}</div><div class="l">kg</div></div>
                    </div>
                </div>
                <div class="wh-bmap-wh-list">
                    ${whs.map(w => `<div class="wh-bmap-wh" onclick="showWhDetail(${w.id})">
                        <span class="wh-bmap-wh-name">${esc(w.name)}</span>
                        <span class="wh-bmap-wh-kg">${parseFloat(w.total_weight_kg).toLocaleString('th-TH',{minimumFractionDigits:2})} kg</span>
                    </div>`).join('')}
                </div>
            </div>`;
        });

        const noBld = byBld['none'] || byBld[''] || [];
        if (noBld.length) {
            html += `<div class="wh-bmap-card">
                <div class="wh-bmap-hdr">
                    <div class="wh-bmap-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-map-marker-slash"></i></div>
                    <div><div class="wh-bmap-title">ไม่ระบุอาคาร</div><div class="wh-bmap-sub">${noBld.length} คลัง</div></div>
                </div>
                <div class="wh-bmap-wh-list">
                    ${noBld.map(w => `<div class="wh-bmap-wh" onclick="showWhDetail(${w.id})">
                        <span class="wh-bmap-wh-name">${esc(w.name)}</span>
                        <span class="wh-bmap-wh-kg">${parseFloat(w.total_weight_kg).toLocaleString('th-TH',{minimumFractionDigits:2})} kg</span>
                    </div>`).join('')}
                </div>
            </div>`;
        }
        el.innerHTML = html || '<div class="wh-empty"><i class="fas fa-building"></i><p>ไม่มีข้อมูล</p></div>';
    } catch (e) {
        el.innerHTML = `<div class="wh-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`;
    }
}

/* ═══════════════════════════════════
   DETAIL MODAL
═══════════════════════════════════ */
let whDetailState = { id:0, page:1, search:'', status:'', tab:'info', data:null, ownerId:0, ownerName:'' };

async function showWhDetail(id) {
    whDetailState = { id, page:1, search:'', status:'', tab:'info', data:null, ownerId:0, ownerName:'' };
    document.getElementById('whDetailContent').innerHTML = '<div class="wh-spin" style="padding:48px"><i class="fas fa-spinner fa-spin"></i></div>';
    document.getElementById('whDetailModal').classList.add('show');
    try {
        const res = await apiFetch('/v1/api/warehouses.php?action=detail&id=' + id);
        if (!res.success) throw new Error(res.error);
        const w = res.data;
        whDetailState.data = w;
        document.getElementById('whDetailTitle').textContent = w.name;

        let html = buildHeroSection(w);
        html += buildTabsSection();
        html += '<div id="whd-tab-content"></div>';

        if (res.siblings && res.siblings.length) {
            html += `<div class="whd-siblings">
                <div class="whd-siblings-hdr"><i class="fas fa-th-list"></i> คลังในฝ่ายเดียวกัน (${res.siblings.length})</div>
                ${res.siblings.map(s => `<div class="whd-sib-item" onclick="showWhDetail(${s.id})">
                    <span>${esc(s.name)}</span>
                    <span style="font-weight:700;color:var(--wh)">${parseFloat(s.total_weight_kg).toLocaleString('th-TH',{minimumFractionDigits:2})} kg</span>
                </div>`).join('')}
            </div>`;
        }
        document.getElementById('whDetailContent').innerHTML = html;
        loadInfoTab();
    } catch (e) {
        document.getElementById('whDetailContent').innerHTML = `<div class="wh-empty"><i class="fas fa-exclamation-triangle" style="color:#ef4444"></i><p>${esc(e.message)}</p></div>`;
    }
}

function buildHeroSection(w) {
    return `<div class="whd-hero"><div class="whd-hero-inner">
        <h2><i class="fas fa-warehouse" style="margin-right:8px;opacity:.8"></i>${esc(w.name)}</h2>
        <div class="whd-hero-path">
            ${esc(w.ctr_name || w.center_name || '')}
            ${w.div_name || w.division_name ? ' › ' + esc(w.div_name || w.division_name) : ''}
            ${w.dept_name || w.unit_name    ? ' › ' + esc(w.dept_name || w.unit_name)    : ''}
            ${w.building_name_full ? ' &nbsp;·&nbsp; <i class="fas fa-building"></i> ' + esc(w.building_name_full) + (w.building_short ? ' (' + esc(w.building_short) + ')' : '') : ''}
        </div>
        <div class="whd-hero-stats">
            <div class="whd-hero-stat"><div class="v">${parseInt(w.total_bottles).toLocaleString()}</div><div class="l">ขวดสารเคมี</div></div>
            <div class="whd-hero-stat"><div class="v">${parseInt(w.total_chemicals).toLocaleString()}</div><div class="l">ชนิดสารเคมี</div></div>
            <div class="whd-hero-stat"><div class="v">${parseFloat(w.total_weight_kg).toLocaleString('th-TH',{maximumFractionDigits:1})}</div><div class="l">kg</div></div>
            <div class="whd-hero-stat"><div class="v" style="font-size:13px">${w.status==='active'?'✅ ใช้งาน':w.status||'—'}</div><div class="l">สถานะ</div></div>
        </div>
    </div></div>`;
}

function buildTabsSection() {
    return `<div class="whd-tabs">
        <button class="whd-tab active" data-tab="info"      onclick="switchDetailTab('info',this)"><i class="fas fa-info-circle"></i> ข้อมูลคลัง</button>
        <button class="whd-tab"        data-tab="overview"  onclick="switchDetailTab('overview',this)"><i class="fas fa-chart-pie"></i> ภาพรวม</button>
        <button class="whd-tab"        data-tab="chemicals" onclick="switchDetailTab('chemicals',this)"><i class="fas fa-flask"></i> สารเคมี</button>
        <button class="whd-tab"        data-tab="owners"    onclick="switchDetailTab('owners',this)"><i class="fas fa-users"></i> ผู้ครอบครอง</button>
    </div>`;
}

function switchDetailTab(tab, btn) {
    whDetailState.tab = tab;
    document.querySelectorAll('.whd-tab').forEach(t => t.classList.remove('active'));
    if (btn) btn.classList.add('active');
    switch(tab) {
        case 'chemicals': loadChemicalsTab(); break;
        case 'overview':  loadOverviewTab();  break;
        case 'owners':    loadOwnersTab();    break;
        case 'info':      loadInfoTab();      break;
    }
}

/* ── Chemicals Tab ── */
async function loadChemicalsTab() {
    const el = document.getElementById('whd-tab-content');
    el.innerHTML = '<div class="wh-spin"><i class="fas fa-spinner fa-spin"></i></div>';
    try {
        const params = new URLSearchParams({ action:'store_chemicals', id:whDetailState.id, page:whDetailState.page, search:whDetailState.search, status:whDetailState.status });
        if (whDetailState.ownerId) params.set('owner_id', whDetailState.ownerId);
        const res = await apiFetch('/v1/api/warehouses.php?' + params);
        if (!res.success) throw new Error(res.error);

        let html = '';
        if (whDetailState.ownerId && whDetailState.ownerName) {
            html += `<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;padding:9px 14px;background:var(--wh-l);border:1.5px solid #c4b5fd;border-radius:10px">
                <div class="whd-owner-av">${getInitials(whDetailState.ownerName)}</div>
                <div style="flex:1"><div style="font-size:12px;font-weight:700;color:var(--c1)">${esc(whDetailState.ownerName)}</div><div style="font-size:10px;color:var(--c3)">แสดงเฉพาะสารเคมีของผู้ครอบครองนี้</div></div>
                <button onclick="whdClearOwner()" style="border:none;background:#fee2e2;color:#dc2626;width:26px;height:26px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px"><i class="fas fa-times"></i></button>
            </div>`;
        }
        html += `<div class="whd-filter-bar">
            <div class="whd-search-wrap"><i class="fas fa-search"></i>
                <input type="text" placeholder="ค้นหาชื่อสาร, Barcode, CAS..." value="${esc(whDetailState.search)}" oninput="whdSearchDebounce(this.value)">
            </div>
            <select onchange="whdFilterStatus(this.value)">
                <option value="">ทุกสถานะ</option>
                <option value="active"   ${whDetailState.status==='active'  ?'selected':''}>Active</option>
                <option value="low"      ${whDetailState.status==='low'     ?'selected':''}>Low</option>
                <option value="empty"    ${whDetailState.status==='empty'   ?'selected':''}>Empty</option>
                <option value="expired"  ${whDetailState.status==='expired' ?'selected':''}>Expired</option>
                <option value="disposed" ${whDetailState.status==='disposed'?'selected':''}>Disposed</option>
            </select>
            <span style="font-size:11px;color:var(--c3)">${res.summary.total_items.toLocaleString()} รายการ</span>
        </div>`;

        if (res.status_breakdown && res.status_breakdown.length > 1) {
            html += '<div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap">';
            res.status_breakdown.forEach(s => {
                html += `<span class="whd-status ${s.status}" style="cursor:pointer" onclick="whdFilterStatus('${s.status}')"><span class="whd-status-dot"></span> ${s.status} (${s.cnt})</span>`;
            });
            html += '</div>';
        }

        if (!res.data.length) {
            html += `<div class="wh-empty"><i class="fas fa-flask"></i><p>${whDetailState.search||whDetailState.status?'ไม่พบรายการที่ตรงกับเงื่อนไข':'ไม่พบสารเคมีในคลังนี้'}</p></div>`;
        } else {
            html += `<div style="overflow-x:auto;border-radius:10px;border:1.5px solid var(--border)"><table class="whd-tbl">
                <thead><tr><th style="width:36px">#</th><th>Barcode</th><th>ชื่อสารเคมี</th><th>CAS No.</th><th>คงเหลือ</th><th>%</th><th>สถานะ</th><th>ผู้ครอบครอง</th></tr></thead>
                <tbody>`;
            const startIdx = (res.pagination.page - 1) * res.pagination.limit;
            res.data.forEach((c, i) => {
                const pct      = parseFloat(c.remaining_pct || 0);
                const pctColor = pct > 50 ? '#22c55e' : pct > 20 ? '#f59e0b' : '#ef4444';
                const initials = getInitials(c.owner_name || c.owner_first || '?');
                html += `<tr onclick="">
                    <td style="color:var(--c3);font-size:11px">${startIdx + i + 1}</td>
                    <td><span class="whd-barcode">${esc(c.bottle_code)}</span></td>
                    <td><div class="whd-chem-name" title="${esc(c.chemical_name)}">${esc(c.chemical_name)}</div>${c.grade?`<div style="font-size:10px;color:var(--c3)">${esc(c.grade)}</div>`:''}</td>
                    <td><span class="whd-cas">${esc(c.cas_no||'-')}</span></td>
                    <td style="font-weight:700">${c.remaining_qty?parseFloat(c.remaining_qty).toLocaleString('th-TH',{maximumFractionDigits:2}):'0'} <span style="font-size:10px;color:var(--c3);font-weight:400">${esc(c.unit||'')}</span></td>
                    <td><span style="font-size:11px;font-weight:700;color:${pctColor}">${pct.toFixed(0)}%</span><div class="whd-pct-bar"><div class="whd-pct-fill" style="width:${pct}%;background:${pctColor}"></div></div></td>
                    <td><span class="whd-status ${c.status}"><span class="whd-status-dot"></span>${c.status}</span></td>
                    <td><div style="display:flex;align-items:center;gap:5px"><div class="whd-owner-av">${initials}</div><span style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100px">${esc(c.owner_name||((c.owner_first||'')+' '+(c.owner_last||'')))}</span></div></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            if (res.pagination.pages > 1) {
                html += `<div class="wh-page">
                    <span>หน้า ${res.pagination.page} / ${res.pagination.pages} &nbsp;(${res.pagination.total.toLocaleString()} รายการ)</span>
                    <div style="display:flex;gap:5px">
                        <button ${res.pagination.page<=1?'disabled':''} onclick="whdChangePage(${res.pagination.page-1})"><i class="fas fa-chevron-left"></i> ก่อนหน้า</button>
                        <button ${res.pagination.page>=res.pagination.pages?'disabled':''} onclick="whdChangePage(${res.pagination.page+1})">ถัดไป <i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>`;
            }
        }
        el.innerHTML = html;
    } catch (e) {
        el.innerHTML = `<div class="wh-empty"><i class="fas fa-exclamation-triangle" style="color:#ef4444"></i><p>${esc(e.message)}</p></div>`;
    }
}

let whdSearchTimer = null;
function whdSearchDebounce(val) { whDetailState.search=val; whDetailState.page=1; clearTimeout(whdSearchTimer); whdSearchTimer=setTimeout(loadChemicalsTab,350); }
function whdFilterStatus(val)   { whDetailState.status=whDetailState.status===val?'':val; whDetailState.page=1; loadChemicalsTab(); }
function whdChangePage(p)       { whDetailState.page=p; loadChemicalsTab(); }
function whdFilterByOwner(userId, name) { whDetailState.ownerId=userId; whDetailState.ownerName=name; whDetailState.page=1; whDetailState.search=''; whDetailState.status=''; switchDetailTab('chemicals',document.querySelector('.whd-tab[data-tab="chemicals"]')); }
function whdClearOwner()        { whDetailState.ownerId=0; whDetailState.ownerName=''; whDetailState.page=1; loadChemicalsTab(); }

/* ── Overview Tab ── */
async function loadOverviewTab() {
    const el = document.getElementById('whd-tab-content');
    el.innerHTML = '<div class="wh-spin"><i class="fas fa-spinner fa-spin"></i></div>';
    try {
        const res = await apiFetch('/v1/api/warehouses.php?action=store_chemicals&id=' + whDetailState.id);
        if (!res.success) throw new Error(res.error);
        if (!res.summary.total_items) { el.innerHTML = '<div class="wh-empty"><i class="fas fa-chart-area"></i><p>ไม่มีข้อมูลสารเคมีในคลังนี้</p></div>'; return; }

        const statusColors  = {active:'#22c55e',low:'#f59e0b',empty:'#ef4444',expired:'#ec4899',disposed:'#94a3b8'};
        const statusLabels  = {active:'ใช้ได้',low:'เหลือน้อย',empty:'หมด',expired:'หมดอายุ',disposed:'ทำลาย'};

        let html = '<div class="wh-sec-label"><i class="fas fa-chart-bar"></i> สถานะสารเคมี</div>';
        html += '<div class="whd-mini-grid">';
        (res.status_breakdown||[]).forEach(s => {
            html += `<div class="whd-mini-card" style="border-left:3px solid ${statusColors[s.status]||'#ccc'}">
                <div class="v" style="color:${statusColors[s.status]||'var(--c1)'}">${parseInt(s.cnt).toLocaleString()}</div>
                <div class="l">${statusLabels[s.status]||s.status}</div>
            </div>`;
        });
        html += '</div>';

        if (res.top_chemicals && res.top_chemicals.length) {
            html += '<div class="wh-sec-label" style="margin-top:14px"><i class="fas fa-trophy" style="color:#d97706"></i> สารเคมียอดนิยม (Top 10)</div>';
            html += '<div style="background:var(--bg);border-radius:10px;padding:4px 10px">';
            res.top_chemicals.forEach((c, i) => {
                const rc = i===0?'r1':i===1?'r2':i===2?'r3':'rn';
                html += `<div class="whd-top-item"><div class="whd-top-rank ${rc}">${i+1}</div><div class="whd-top-name" title="${esc(c.chemical_name)}">${esc(c.chemical_name)}</div><div class="whd-top-val">${parseInt(c.bottle_count)} ขวด</div></div>`;
            });
            html += '</div>';
        }

        html += '<div class="wh-sec-label" style="margin-top:14px"><i class="fas fa-database"></i> สรุปข้อมูล</div>';
        html += `<div class="whd-info-grid">
            <div class="whd-info-item"><div class="whd-info-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-wine-bottle"></i></div><div><div class="lbl">จำนวนขวดทั้งหมด</div><div class="val">${res.summary.total_items.toLocaleString()}</div></div></div>
            <div class="whd-info-item"><div class="whd-info-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-atom"></i></div><div><div class="lbl">ชนิดสารเคมี</div><div class="val">${res.summary.unique_chemicals.toLocaleString()}</div></div></div>
            <div class="whd-info-item"><div class="whd-info-icon" style="background:#dcfce7;color:#15803d"><i class="fas fa-weight-hanging"></i></div><div><div class="lbl">ปริมาณรวม</div><div class="val">${res.summary.total_weight.toLocaleString('th-TH',{maximumFractionDigits:2})}</div></div></div>
            <div class="whd-info-item"><div class="whd-info-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-user-friends"></i></div><div><div class="lbl">ผู้ครอบครอง</div><div class="val">${res.summary.holder_count}</div></div></div>
        </div>`;
        el.innerHTML = html;
    } catch (e) {
        el.innerHTML = `<div class="wh-empty"><i class="fas fa-exclamation-triangle" style="color:#ef4444"></i><p>${esc(e.message)}</p></div>`;
    }
}

/* ── Owners Tab ── */
async function loadOwnersTab() {
    const el = document.getElementById('whd-tab-content');
    el.innerHTML = '<div class="wh-spin"><i class="fas fa-spinner fa-spin"></i></div>';
    try {
        const res = await apiFetch('/v1/api/warehouses.php?action=store_chemicals&id=' + whDetailState.id);
        if (!res.success) throw new Error(res.error);
        if (!res.owners || !res.owners.length) { el.innerHTML = '<div class="wh-empty"><i class="fas fa-users"></i><p>ไม่พบผู้ครอบครองสารเคมีในคลังนี้</p></div>'; return; }

        let html = `<div class="wh-sec-label"><i class="fas fa-users"></i> ผู้ครอบครองสารเคมี (${res.owners.length} คน)</div>`;
        html += '<div class="whd-owner-grid">';
        res.owners.forEach(o => {
            const name = o.owner_name || ((o.first_name||'')+' '+(o.last_name||'')).trim() || '?';
            html += `<div class="whd-owner-card" onclick="whdFilterByOwner(${o.owner_user_id},'${esc(name).replace(/'/g,"\\'")}')">
                <div class="av">${getInitials(name)}</div>
                <div style="flex:1;min-width:0">
                    <div class="name">${esc(name)}</div>
                    <div class="sub">${parseInt(o.bottle_count).toLocaleString()} ขวด · ${parseFloat(o.total_qty||0).toLocaleString('th-TH',{maximumFractionDigits:1})} หน่วย</div>
                </div>
                <i class="fas fa-chevron-right" style="color:var(--border);font-size:10px"></i>
            </div>`;
        });
        html += '</div>';
        el.innerHTML = html;
    } catch (e) {
        el.innerHTML = `<div class="wh-empty"><i class="fas fa-exclamation-triangle" style="color:#ef4444"></i><p>${esc(e.message)}</p></div>`;
    }
}

/* ── Info Tab ── */
function loadInfoTab() {
    const w = whDetailState.data;
    if (!w) return;
    let html = '<div class="wh-sec-label"><i class="fas fa-info-circle"></i> ข้อมูลคลังสารเคมี</div>';
    html += `<div class="whd-info-grid">
        <div class="whd-info-item"><div class="whd-info-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-sitemap"></i></div><div><div class="lbl">ศูนย์</div><div class="val">${esc(w.ctr_name||w.center_name||'—')}</div></div></div>
        <div class="whd-info-item"><div class="whd-info-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-layer-group"></i></div><div><div class="lbl">ฝ่าย</div><div class="val">${esc(w.div_name||w.division_name||'—')}</div></div></div>
        <div class="whd-info-item"><div class="whd-info-icon" style="background:#dcfce7;color:#15803d"><i class="fas fa-users-cog"></i></div><div><div class="lbl">งาน / หน่วย</div><div class="val">${esc(w.dept_name||w.unit_name||'—')}</div></div></div>
        <div class="whd-info-item"><div class="whd-info-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-building"></i></div><div><div class="lbl">อาคาร</div><div class="val">${esc(w.building_name_full||'—')}${w.building_short?' ('+esc(w.building_short)+')':''}</div></div></div>
        ${w.floor?`<div class="whd-info-item"><div class="whd-info-icon" style="background:#e0e7ff;color:#4f46e5"><i class="fas fa-stairs"></i></div><div><div class="lbl">ชั้น</div><div class="val">${esc(w.floor)}</div></div></div>`:''}
        ${w.zone?`<div class="whd-info-item"><div class="whd-info-icon" style="background:#fce7f3;color:#be185d"><i class="fas fa-map-marker-alt"></i></div><div><div class="lbl">โซน</div><div class="val">${esc(w.zone)}</div></div></div>`:''}
        <div class="whd-info-item"><div class="whd-info-icon" style="background:#ecfdf5;color:#059669"><i class="fas fa-signal"></i></div><div><div class="lbl">สถานะ</div><div class="val">${w.status==='active'?'✅ ใช้งาน':w.status||'—'}</div></div></div>
        ${w.mgr_name?`<div class="whd-info-item"><div class="whd-info-icon" style="background:#fff7ed;color:#c2410c"><i class="fas fa-user-tie"></i></div><div><div class="lbl">ผู้รับผิดชอบ</div><div class="val">${esc(w.mgr_name)}</div></div></div>`:''}
    </div>`;
    if (w.description) {
        html += `<div style="margin-top:6px;padding:12px 14px;background:var(--bg);border-radius:9px;font-size:12px;color:var(--c2);line-height:1.6">${esc(w.description)}</div>`;
    }
    document.getElementById('whd-tab-content').innerHTML = html;
}

/* ═══════════════════════════════════
   HELPERS
═══════════════════════════════════ */
function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.substring(0,2).toUpperCase();
}

function closeWhDetail() { document.getElementById('whDetailModal').classList.remove('show'); }
document.getElementById('whDetailModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeWhDetail(); });

function filterByDivision(divId) { switchView('list'); document.getElementById('whDivFilter').value = divId; loadWarehouses(); }
function filterByBuilding(bldId) { switchView('map'); }

async function loadDivisions() {
    try {
        const res = await apiFetch('/v1/api/warehouses.php?action=divisions');
        if (!res.success) return;
        divisionsList = res.data;
        const sel = document.getElementById('whDivFilter');
        sel.innerHTML = '<option value="">ทุกฝ่าย</option>' +
            divisionsList.map(d => `<option value="${d.id}">${esc(d.name.replace(/^ฝ่าย/, ''))} (${d.warehouse_count})</option>`).join('');
    } catch(e) { console.error(e); }
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function apiFetch(url, opts = {}) {
    const tk = localStorage.getItem('auth_token') || '';
    const h  = { 'Content-Type': 'application/json' };
    if (tk) h['Authorization'] = 'Bearer ' + tk;
    return fetch(url, { ...opts, headers: { ...h, ...(opts.headers || {}) } }).then(r => r.json());
}

loadOverview();
loadDivisions();
</script>

<?php Layout::endContent(); ?>
