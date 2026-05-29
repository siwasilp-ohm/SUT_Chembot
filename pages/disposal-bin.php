<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang      = I18n::getCurrentLang();
$userId    = $user['id'];
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin   = $roleLevel >= 5;
$isManager = $roleLevel >= 3;
$TH        = $lang === 'th';
if (!$isManager) { header('Location: /v1/pages/borrow.php'); exit; }

$buildings   = Database::fetchAll("SELECT DISTINCT building_name FROM disposal_bin WHERE building_name IS NOT NULL AND building_name !='' ORDER BY building_name");
$departments = Database::fetchAll("SELECT DISTINCT department FROM disposal_bin WHERE department IS NOT NULL AND department !='' ORDER BY department");
$disposers   = Database::fetchAll("SELECT DISTINCT db.disposed_by, u.first_name, u.last_name FROM disposal_bin db JOIN users u ON u.id=db.disposed_by ORDER BY u.first_name");

Layout::head($TH ? 'ถังจำหน่ายสารเคมี' : 'Chemical Disposal Bin');
?>
<body>
<?php Layout::sidebar('disposal'); Layout::beginContent(); ?>
<style>
:root{--db-r:14px;--db-rs:10px;--db-sh:0 1px 6px rgba(0,0,0,.06);--db-shm:0 4px 20px rgba(0,0,0,.09)}

/* ── Hero ── */
.db-hero{background:linear-gradient(135deg,#7f1d1d 0%,#dc2626 55%,#f87171 100%);border-radius:var(--db-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.db-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.db-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;position:relative}
.db-hero-info{position:relative}
.db-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.db-hero-info p{font-size:12px;opacity:.85;margin:0}
.db-hero-meta{margin-left:auto;display:flex;gap:24px;flex-shrink:0;position:relative}
.db-hero-c{text-align:center}
.db-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.db-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}

/* ── Stats Row ── */
.db-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.db-stat{background:#fff;border-radius:var(--db-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--db-sh);border:1px solid #e2e8f0;transition:all .15s;cursor:pointer}
.db-stat:hover{transform:translateY(-2px);box-shadow:var(--db-shm)}
.db-stat.active{border-color:#fca5a5;background:#fef2f2;box-shadow:0 0 0 3px rgba(220,38,38,.1)}
.db-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.db-sv{font-size:20px;font-weight:800;color:#0f172a;line-height:1}
.db-sl{font-size:10px;color:#94a3b8;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Tabs ── */
.db-tabs{display:inline-flex;background:#f1f5f9;border-radius:var(--db-rs);padding:3px;margin-bottom:18px}
.db-tab{padding:8px 20px;font-size:12px;font-weight:600;color:#64748b;border-radius:8px;cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:6px;white-space:nowrap}
.db-tab:hover{color:#0f172a}
.db-tab.on{background:#fff;color:#dc2626;box-shadow:0 1px 4px rgba(0,0,0,.08)}

/* ── Toolbar ── */
.db-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.db-search{flex:1;min-width:220px;position:relative}
.db-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid #e2e8f0;border-radius:var(--db-rs);font-size:13px;background:#fff;color:#0f172a;transition:border .15s;box-sizing:border-box;outline:none}
.db-search input:focus{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.08)}
.db-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:13px}
.db-btn{padding:8px 16px;border:none;border-radius:var(--db-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap}
.db-btn-g{background:#fff;color:#64748b;border:1.5px solid #e2e8f0}
.db-btn-g:hover{border-color:#dc2626;color:#dc2626}
.db-btn-g.on{background:#fef2f2;border-color:#fca5a5;color:#dc2626}

/* ── Filter Panel ── */
.db-fp{max-height:0;overflow:hidden;transition:max-height .25s ease,padding .25s ease;background:#fff;border:1.5px solid transparent;border-radius:var(--db-r);margin-bottom:0}
.db-fp.show{max-height:320px;border-color:#e2e8f0;padding:16px;margin-bottom:14px;box-shadow:var(--db-sh)}
.db-fg{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
.db-fl label{font-size:10px;font-weight:700;color:#94a3b8;display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px}
.db-fl select,.db-fl input[type=date]{width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:12px;background:#fff;color:#0f172a;outline:none;box-sizing:border-box;transition:.12s}
.db-fl select:focus,.db-fl input:focus{border-color:#dc2626}
.db-fa{display:flex;gap:8px;margin-top:10px;justify-content:flex-end}
.db-fa-btn{padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;transition:.12s}
.db-fa-btn:hover{border-color:#dc2626;color:#dc2626}

/* ── Report grid ── */
.db-rgrid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:700px){.db-rgrid{grid-template-columns:1fr}}
.db-rcard{background:#fff;border:1.5px solid #e2e8f0;border-radius:var(--db-r);overflow:hidden;box-shadow:var(--db-sh)}
.db-rcard.wide{grid-column:1/-1}
.db-rcard-hdr{display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid #f1f5f9;background:#fafbfc}
.db-rcard-hdr-ic{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.db-rcard-hdr h5{margin:0;font-size:13px;font-weight:700;color:#0f172a;flex:1}
.db-rcard-cnt{font-size:10px;color:#64748b;font-weight:600;background:#f1f5f9;padding:2px 8px;border-radius:6px}
.db-rcard-body{padding:12px 14px}

/* ── Bar chart ── */
.db-bar-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #f8fafc}
.db-bar-row:last-child{border-bottom:none}
.db-bar-lbl{flex:1.4;min-width:0}
.db-bar-name{font-size:12px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.db-bar-sub{font-size:10px;color:#94a3b8;margin-top:1px}
.db-bar-track{flex:2;height:16px;background:#f1f5f9;border-radius:8px;overflow:hidden}
.db-bar-fill{height:100%;border-radius:8px;display:flex;align-items:center;justify-content:flex-end;padding-right:5px;font-size:9px;color:#fff;font-weight:700;transition:width .4s ease}
.db-bar-n{font-size:12px;font-weight:700;color:#0f172a;min-width:28px;text-align:right}
.gd-red{background:linear-gradient(90deg,#ef4444,#dc2626)}
.gd-orange{background:linear-gradient(90deg,#f97316,#ea580c)}
.gd-blue{background:linear-gradient(90deg,#3b82f6,#2563eb)}
.gd-green{background:linear-gradient(90deg,#10b981,#059669)}
.gd-purple{background:linear-gradient(90deg,#a855f7,#7c3aed)}
.gd-teal{background:linear-gradient(90deg,#14b8a6,#0d9488)}
.gd-gray{background:linear-gradient(90deg,#94a3b8,#64748b)}

/* ── List items ── */
.db-list{display:flex;flex-direction:column;gap:8px}
.db-item{background:#fff;border:1.5px solid #e2e8f0;border-radius:var(--db-r);overflow:hidden;box-shadow:var(--db-sh);transition:.15s;position:relative;cursor:pointer}
.db-item:hover{border-color:#fca5a5;box-shadow:var(--db-shm)}
.db-item-accent{position:absolute;left:0;top:0;bottom:0;width:4px}
.db-item.s-pending .db-item-accent{background:linear-gradient(180deg,#f97316,#ea580c)}
.db-item.s-approved .db-item-accent{background:linear-gradient(180deg,#3b82f6,#2563eb)}
.db-item.s-completed .db-item-accent{background:linear-gradient(180deg,#10b981,#059669)}
.db-item.s-rejected .db-item-accent{background:linear-gradient(180deg,#a855f7,#7c3aed)}
.db-item-inner{padding:14px 16px 14px 20px}
.db-item-top{display:flex;align-items:flex-start;gap:12px}
.db-item-ico{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.db-item.s-pending .db-item-ico{background:#fff7ed;color:#f97316}
.db-item.s-approved .db-item-ico{background:#eff6ff;color:#2563eb}
.db-item.s-completed .db-item-ico{background:#ecfdf5;color:#059669}
.db-item.s-rejected .db-item-ico{background:#faf5ff;color:#a855f7}
.db-item-main{flex:1;min-width:0}
.db-item-name{font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}
.db-item-bc{font-size:10px;font-family:'Courier New',monospace;color:#94a3b8;display:flex;align-items:center;gap:4px}
.db-item-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0}
.db-chip{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:8px;font-size:9px;font-weight:700;white-space:nowrap;text-transform:uppercase;letter-spacing:.3px}
.db-chip.pending{background:#fff7ed;color:#c2410c}
.db-chip.approved{background:#eff6ff;color:#1d4ed8}
.db-chip.completed{background:#ecfdf5;color:#065f46}
.db-chip.rejected{background:#faf5ff;color:#6d28d9}
.db-item-qty{font-size:14px;font-weight:800;color:#0f172a;white-space:nowrap}
.db-item-meta{display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9}
.db-item-meta span{display:flex;align-items:center;gap:4px;font-size:11px;color:#64748b}
.db-item-meta i{font-size:10px;color:#94a3b8}
.db-item-actions{display:flex;gap:6px;margin-top:10px}
.db-act-btn{display:flex;align-items:center;gap:5px;padding:6px 14px;border-radius:8px;border:1.5px solid;font-size:11px;font-weight:700;cursor:pointer;transition:.12s}
.db-act-btn.confirm{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
.db-act-btn.confirm:hover{background:#059669;border-color:#059669;color:#fff}
.db-act-btn.restore{background:#faf5ff;border-color:#e9d5ff;color:#6d28d9}
.db-act-btn.restore:hover{background:#a855f7;border-color:#a855f7;color:#fff}

/* ── Pagination ── */
.db-pager{display:flex;align-items:center;justify-content:center;gap:5px;margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9}
.db-pbtn{width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:11px;cursor:pointer;transition:.12s}
.db-pbtn:hover{background:#fef2f2;border-color:#fca5a5;color:#dc2626}
.db-pbtn.on{background:#dc2626;color:#fff;border-color:#dc2626}
.db-pbtn:disabled{opacity:.3;cursor:default}
.db-pinfo{font-size:11px;color:#94a3b8;padding:0 6px}

/* ── Confirm Modal ── */
.db-conf-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(8px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity .2s}
.db-conf-ov.show{opacity:1;pointer-events:all}
.db-conf-box{background:#fff;border-radius:20px;width:100%;max-width:440px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.25);transform:translateY(14px) scale(.97);transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
.db-conf-ov.show .db-conf-box{transform:none}
.db-conf-hdr{padding:22px 22px 18px;color:#fff;position:relative}
.db-conf-hdr-ic{width:52px;height:52px;border-radius:16px;background:rgba(255,255,255,.2);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px}
.db-conf-hdr h3{font-size:17px;font-weight:800;margin:0 0 4px}
.db-conf-hdr p{font-size:12px;opacity:.85;margin:0}
.db-conf-close{position:absolute;top:14px;right:14px;width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.2);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;transition:.15s;z-index:2}
.db-conf-close:hover{background:rgba(255,255,255,.35)}
.db-conf-item{margin:0 18px 16px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;padding:14px 16px}
.db-conf-item-top{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.db-conf-item-ic{width:38px;height:38px;border-radius:10px;background:#fef2f2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.db-conf-item-name{font-size:14px;font-weight:700;color:#0f172a;margin-bottom:2px}
.db-conf-item-bc{font-size:10px;font-family:'Courier New',monospace;color:#94a3b8}
.db-conf-item-chips{display:flex;gap:8px;flex-wrap:wrap}
.db-conf-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:8px;font-size:11px;font-weight:600}
.db-conf-chip.qty{background:#eff6ff;color:#1d4ed8}
.db-conf-chip.person{background:#f0fdf4;color:#065f46}
.db-conf-chip.reason{background:#fff7ed;color:#c2410c}
.db-conf-warn{margin:0 18px 16px;display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;font-size:12px;font-weight:500;line-height:1.5}
.db-conf-warn.danger{background:#fef2f2;border:1.5px solid #fecaca;color:#991b1b}
.db-conf-warn.info{background:#eff6ff;border:1.5px solid #bfdbfe;color:#1e40af}
.db-conf-warn i{font-size:14px;margin-top:1px;flex-shrink:0}
.db-conf-ftr{display:flex;gap:8px;padding:0 18px 20px}
.db-conf-btn{flex:1;padding:11px;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;border:none;display:flex;align-items:center;justify-content:center;gap:7px;transition:.15s;font-family:inherit}
.db-conf-btn.cancel{background:#f1f5f9;color:#64748b}
.db-conf-btn.cancel:hover{background:#e2e8f0}
.db-conf-btn.ok-green{background:linear-gradient(135deg,#059669,#10b981);color:#fff}
.db-conf-btn.ok-green:hover{filter:brightness(1.08)}
.db-conf-btn.ok-purple{background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff}
.db-conf-btn.ok-purple:hover{filter:brightness(1.08)}
.db-conf-btn:disabled{opacity:.6;cursor:not-allowed;filter:none}

/* ── Detail Modal ── */
.db-det-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(8px);z-index:999;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity .2s}
.db-det-ov.show{opacity:1;pointer-events:all}
.db-det-box{background:#fff;border-radius:20px;width:100%;max-width:480px;max-height:90vh;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.25);transform:translateY(14px) scale(.97);transition:transform .25s cubic-bezier(.34,1.56,.64,1);display:flex;flex-direction:column}
.db-det-ov.show .db-det-box{transform:none}
.db-det-hdr{padding:22px 22px 18px;color:#fff;position:relative;flex-shrink:0}
.db-det-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:.15s;z-index:2}
.db-det-close:hover{background:rgba(255,255,255,.35)}
.db-det-hdr-top{display:flex;align-items:flex-start;gap:14px}
.db-det-hdr-ic{width:52px;height:52px;border-radius:16px;background:rgba(255,255,255,.2);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.db-det-hdr-info{flex:1;min-width:0}
.db-det-hdr-label{font-size:10px;font-weight:700;opacity:.75;text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px}
.db-det-hdr-name{font-size:17px;font-weight:800;line-height:1.25;margin-bottom:5px}
.db-det-hdr-bc{font-size:11px;font-family:'Courier New',monospace;background:rgba(0,0,0,.18);padding:2px 9px;border-radius:5px;display:inline-block}
.db-det-strip{display:grid;grid-template-columns:repeat(3,1fr);background:#f8fafc;border-top:1px solid #e2e8f0;flex-shrink:0}
.db-det-sc{padding:13px 10px;text-align:center;border-right:1px solid #e2e8f0}
.db-det-sc:last-child{border-right:none}
.db-det-sv{font-size:15px;font-weight:800;color:#0f172a;line-height:1;display:flex;align-items:center;justify-content:center;gap:4px;flex-wrap:wrap}
.db-det-sl{font-size:10px;color:#94a3b8;margin-top:4px;text-transform:uppercase;letter-spacing:.3px}
.db-det-body{padding:16px 18px 8px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;flex:1}
.db-det-card{display:flex;align-items:flex-start;gap:12px;padding:11px 14px;border:1.5px solid #f1f5f9;border-radius:12px;background:#fafbfc}
.db-det-card-ic{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.db-det-card-lbl{font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.db-det-card-val{font-size:13px;font-weight:600;color:#0f172a;line-height:1.4}
.db-det-ftr{padding:12px 18px 18px;display:flex;gap:8px;flex-shrink:0;border-top:1px solid #f1f5f9}
.db-det-act{flex:1;padding:10px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;border:1.5px solid;display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s;font-family:inherit}
.db-det-act.confirm{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
.db-det-act.confirm:hover{background:#059669;border-color:#059669;color:#fff}
.db-det-act.restore{background:#faf5ff;border-color:#e9d5ff;color:#6d28d9}
.db-det-act.restore:hover{background:#a855f7;border-color:#a855f7;color:#fff}

/* ── Common ── */
.db-empty{text-align:center;padding:40px 20px;color:#94a3b8}
.db-empty i{font-size:36px;display:block;margin-bottom:10px;opacity:.25}
.db-empty p{font-size:12px;margin:4px 0 0;font-weight:500}
.db-ld{text-align:center;padding:32px;color:#94a3b8}
.db-ld i{animation:dbspin .8s linear infinite;font-size:20px}
@keyframes dbspin{to{transform:rotate(360deg)}}

@media(max-width:640px){
  .db-stats{display:flex;overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:4px;scrollbar-width:none;gap:8px}
  .db-stats::-webkit-scrollbar{display:none}
  .db-stat{flex-shrink:0;min-width:130px}
  .db-hero-meta{display:none}
  .db-hero{padding:18px 20px}
  .db-hero-info h2{font-size:16px}
  .db-tabs{overflow-x:auto;width:100%}
}
</style>

<?php Layout::pageHeader(
    $TH ? 'ถังจำหน่ายสารเคมี' : 'Chemical Disposal Bin',
    'fas fa-trash-alt',
    $TH ? 'ติดตามและจัดการสถานะการจำหน่ายสารเคมี' : 'Monitor and manage chemical disposal status'
); ?>

<!-- HERO -->
<div class="db-hero">
  <div class="db-hero-ic"><i class="fas fa-trash-alt"></i></div>
  <div class="db-hero-info">
    <h2><?php echo $TH?'ถังจำหน่ายสารเคมี':'Chemical Disposal Bin'?></h2>
    <p><?php echo $TH?'ติดตามและจัดการสถานะการจำหน่ายสารเคมีทั้งหมด':'Monitor and manage all chemical disposal records'?></p>
  </div>
  <div class="db-hero-meta">
    <div class="db-hero-c"><div class="v" id="dsTot">–</div><div class="lb"><?php echo $TH?'ทั้งหมด':'Total'?></div></div>
    <div class="db-hero-c"><div class="v" id="dsPend">–</div><div class="lb"><?php echo $TH?'รอดำเนินการ':'Pending'?></div></div>
    <div class="db-hero-c"><div class="v" id="dsComp">–</div><div class="lb"><?php echo $TH?'สำเร็จ':'Done'?></div></div>
  </div>
</div>

<!-- STATS ROW -->
<div class="db-stats">
  <div class="db-stat" id="dbStat-all" onclick="statClick('')"><div class="db-si" style="background:#fef2f2;color:#ef4444"><i class="fas fa-inbox"></i></div><div><div class="db-sv" id="statTot">–</div><div class="db-sl"><?php echo $TH?'ทั้งหมด':'Total'?></div></div></div>
  <div class="db-stat" id="dbStat-pending" onclick="statClick('pending')"><div class="db-si" style="background:#fff7ed;color:#f97316"><i class="fas fa-clock"></i></div><div><div class="db-sv" id="statPend">–</div><div class="db-sl"><?php echo $TH?'รอดำเนินการ':'Pending'?></div></div></div>
  <div class="db-stat" id="dbStat-completed" onclick="statClick('completed')"><div class="db-si" style="background:#ecfdf5;color:#10b981"><i class="fas fa-check-circle"></i></div><div><div class="db-sv" id="statComp">–</div><div class="db-sl"><?php echo $TH?'จำหน่ายแล้ว':'Completed'?></div></div></div>
  <div class="db-stat" id="dbStat-rejected" onclick="statClick('rejected')"><div class="db-si" style="background:#faf5ff;color:#a855f7"><i class="fas fa-undo"></i></div><div><div class="db-sv" id="statCan">–</div><div class="db-sl"><?php echo $TH?'คืนกลับ':'Restored'?></div></div></div>
</div>

<!-- TOOLBAR -->
<div class="db-toolbar">
  <div class="db-search"><i class="fas fa-search"></i>
    <input type="text" id="fSearch" placeholder="<?php echo $TH?'ค้นหาชื่อสาร / Barcode...':'Search chemical, barcode...'?>" oninput="debounce()">
  </div>
  <button class="db-btn db-btn-g" id="dbFpBtn" onclick="toggleFp()"><i class="fas fa-sliders-h"></i> <?php echo $TH?'ตัวกรอง':'Filter'?></button>
</div>

<!-- FILTER PANEL -->
<div class="db-fp" id="dbFp">
  <div class="db-fg">
    <div class="db-fl">
      <label><?php echo $TH?'อาคาร':'Building'?></label>
      <select id="fBuilding" onchange="load()">
        <option value=""><?php echo $TH?'ทุกอาคาร':'All Buildings'?></option>
        <?php foreach($buildings as $b): ?><option><?php echo htmlspecialchars($b['building_name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="db-fl">
      <label><?php echo $TH?'หน่วยงาน':'Department'?></label>
      <select id="fDept" onchange="load()">
        <option value=""><?php echo $TH?'ทุกหน่วยงาน':'All Depts'?></option>
        <?php foreach($departments as $d): ?><option><?php echo htmlspecialchars($d['department'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="db-fl">
      <label><?php echo $TH?'บุคคล':'Person'?></label>
      <select id="fPerson" onchange="load()">
        <option value=""><?php echo $TH?'ทุกคน':'All People'?></option>
        <?php foreach($disposers as $dp): ?><option value="<?php echo $dp['disposed_by']?>"><?php echo htmlspecialchars($dp['first_name'].' '.$dp['last_name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="db-fl">
      <label><?php echo $TH?'สถานะ':'Status'?></label>
      <select id="fStatus" onchange="load()">
        <option value=""><?php echo $TH?'ทุกสถานะ':'All Status'?></option>
        <option value="pending"><?php echo $TH?'รอดำเนินการ':'Pending'?></option>
        <option value="approved"><?php echo $TH?'อนุมัติ':'Approved'?></option>
        <option value="completed"><?php echo $TH?'จำหน่ายแล้ว':'Completed'?></option>
        <option value="rejected"><?php echo $TH?'คืนกลับ':'Restored'?></option>
      </select>
    </div>
    <div class="db-fl">
      <label><?php echo $TH?'ตั้งแต่':'From'?></label>
      <input type="date" id="fFrom" onchange="load()">
    </div>
    <div class="db-fl">
      <label><?php echo $TH?'ถึง':'To'?></label>
      <input type="date" id="fTo" onchange="load()">
    </div>
  </div>
  <div class="db-fa">
    <button class="db-fa-btn" onclick="clearF()"><?php echo $TH?'ล้างตัวกรอง':'Clear'?></button>
  </div>
</div>

<!-- TABS -->
<div class="db-tabs">
  <button class="db-tab on" id="tab-report" onclick="switchTab('report')"><i class="fas fa-chart-bar"></i> <?php echo $TH?'สรุปรายงาน':'Report'?></button>
  <button class="db-tab" id="tab-list" onclick="switchTab('list')"><i class="fas fa-list"></i> <?php echo $TH?'รายการทั้งหมด':'All Items'?></button>
</div>

<!-- REPORT VIEW -->
<div id="vReport">
  <div class="db-rgrid">
    <div class="db-rcard">
      <div class="db-rcard-hdr"><div class="db-rcard-hdr-ic" style="background:#fef2f2;color:#ef4444"><i class="fas fa-building"></i></div><h5><?php echo $TH?'ตามหน่วยงาน':'By Department'?></h5></div>
      <div class="db-rcard-body" id="rDept"><div class="db-ld"><i class="fas fa-circle-notch"></i></div></div>
    </div>
    <div class="db-rcard">
      <div class="db-rcard-hdr"><div class="db-rcard-hdr-ic" style="background:#eff6ff;color:#2563eb"><i class="fas fa-user"></i></div><h5><?php echo $TH?'ตามบุคคล':'By Person'?></h5></div>
      <div class="db-rcard-body" id="rPerson"><div class="db-ld"><i class="fas fa-circle-notch"></i></div></div>
    </div>
    <div class="db-rcard">
      <div class="db-rcard-hdr"><div class="db-rcard-hdr-ic" style="background:#fff7ed;color:#f97316"><i class="fas fa-tag"></i></div><h5><?php echo $TH?'ตามเหตุผล':'By Reason'?></h5></div>
      <div class="db-rcard-body" id="rReason"><div class="db-ld"><i class="fas fa-circle-notch"></i></div></div>
    </div>
    <div class="db-rcard">
      <div class="db-rcard-hdr"><div class="db-rcard-hdr-ic" style="background:#ecfdf5;color:#059669"><i class="fas fa-map-marker-alt"></i></div><h5><?php echo $TH?'ตามอาคาร':'By Building'?></h5></div>
      <div class="db-rcard-body" id="rBuild"><div class="db-ld"><i class="fas fa-circle-notch"></i></div></div>
    </div>
    <div class="db-rcard wide">
      <div class="db-rcard-hdr"><div class="db-rcard-hdr-ic" style="background:#faf5ff;color:#7c3aed"><i class="fas fa-cogs"></i></div><h5><?php echo $TH?'ตามวิธีจำหน่าย':'By Method'?></h5></div>
      <div class="db-rcard-body" id="rMethod"><div class="db-ld"><i class="fas fa-circle-notch"></i></div></div>
    </div>
  </div>
  <div class="db-rcard" style="margin-bottom:16px">
    <div class="db-rcard-hdr"><div class="db-rcard-hdr-ic" style="background:#f1f5f9;color:#64748b"><i class="fas fa-history"></i></div><h5><?php echo $TH?'รายการล่าสุด':'Recent Items'?></h5><span class="db-rcard-cnt" id="rRecentCnt"></span></div>
    <div class="db-rcard-body" id="rRecent"><div class="db-ld"><i class="fas fa-circle-notch"></i></div></div>
  </div>
</div>

<!-- LIST VIEW -->
<div id="vList" style="display:none">
  <div id="lList"><div class="db-ld"><i class="fas fa-circle-notch"></i></div></div>
  <div id="lPager"></div>
</div>

<?php Layout::endContent(); ?>

<!-- DETAIL MODAL -->
<div class="db-det-ov" id="dbDetOv" onclick="if(event.target===this)closeDetail()">
  <div class="db-det-box">
    <div class="db-det-hdr" id="dbDetHdr">
      <button class="db-det-close" onclick="closeDetail()"><i class="fas fa-times"></i></button>
      <div class="db-det-hdr-top">
        <div class="db-det-hdr-ic" id="dbDetIc"></div>
        <div class="db-det-hdr-info">
          <div class="db-det-hdr-label" id="dbDetLabel"></div>
          <div class="db-det-hdr-name" id="dbDetName"></div>
          <div class="db-det-hdr-bc" id="dbDetBc"></div>
        </div>
      </div>
    </div>
    <div class="db-det-strip" id="dbDetStrip"></div>
    <div class="db-det-body" id="dbDetBody"></div>
    <div class="db-det-ftr" id="dbDetFtr" style="display:none"></div>
  </div>
</div>

<!-- CONFIRM MODAL -->
<div class="db-conf-ov" id="dbConfOv" onclick="if(event.target===this)closeConfirm()">
  <div class="db-conf-box">
    <div class="db-conf-hdr" id="dbConfHdr">
      <button class="db-conf-close" onclick="closeConfirm()"><i class="fas fa-times"></i></button>
      <div class="db-conf-hdr-ic" id="dbConfIc"></div>
      <h3 id="dbConfTitle"></h3>
      <p id="dbConfSub"></p>
    </div>
    <div class="db-conf-item" id="dbConfItem"></div>
    <div class="db-conf-warn" id="dbConfWarn"></div>
    <div class="db-conf-ftr">
      <button class="db-conf-btn cancel" onclick="closeConfirm()"><i class="fas fa-times"></i> <?php echo $TH?'ยกเลิก':'Cancel'?></button>
      <button class="db-conf-btn" id="dbConfOkBtn" onclick="_execConfirm()"></button>
    </div>
  </div>
</div>

<script>
const TH=<?php echo json_encode($TH);?>;
const IS_ADMIN=<?php echo json_encode($isAdmin);?>;
const RL={expired:TH?'หมดอายุ':'Expired',empty:TH?'หมด/ใช้จนหมด':'Empty',contaminated:TH?'ปนเปื้อน':'Contaminated',damaged:TH?'ชำรุด':'Damaged',obsolete:TH?'ไม่ใช้แล้ว':'Obsolete',other:TH?'อื่นๆ':'Other'};
const ML={waste_collection:TH?'ส่งเก็บของเสีย':'Waste Collection',neutralization:TH?'ทำให้เป็นกลาง':'Neutralization',incineration:TH?'เผาทำลาย':'Incineration',return_to_vendor:TH?'คืนผู้ขาย':'Return Vendor',other:TH?'อื่นๆ':'Other'};
const SL={pending:TH?'รอดำเนินการ':'Pending',approved:TH?'อนุมัติ':'Approved',completed:TH?'จำหน่ายแล้ว':'Completed',rejected:TH?'คืนกลับ':'Restored'};
const GDS=['gd-red','gd-orange','gd-blue','gd-green','gd-purple','gd-teal','gd-gray'];

let curTab='report',curPage=1,timer=null,_dbItems={},_confPending=null;

function g(id){return document.getElementById(id);}
function esc(s){const d=document.createElement('div');d.textContent=String(s??'');return d.innerHTML;}
function num(v){return Number(v||0).toLocaleString();}
function fmtDate(s){if(!s)return'';const d=new Date(s);return d.toLocaleDateString(TH?'th-TH':'en-GB',{day:'2-digit',month:'short',year:'2-digit'});}
function debounce(){clearTimeout(timer);timer=setTimeout(()=>{curPage=1;load();},300);}

function statClick(status){
  document.querySelectorAll('.db-stat').forEach(s=>s.classList.remove('active'));
  const key=status||'all';
  const el=g('dbStat-'+key);if(el)el.classList.add('active');
  const sel=g('fStatus');if(sel)sel.value=status;
  curPage=1;
  g('tab-report').classList.remove('on');
  g('tab-list').classList.add('on');
  g('vReport').style.display='none';
  g('vList').style.display='';
  curTab='list';loadList();
}

function toggleFp(){
  const fp=g('dbFp'),btn=g('dbFpBtn');
  fp.classList.toggle('show');
  btn.classList.toggle('on',fp.classList.contains('show'));
}
function clearF(){
  ['fBuilding','fDept','fPerson','fStatus'].forEach(id=>{const el=g(id);if(el)el.value='';});
  ['fFrom','fTo'].forEach(id=>{const el=g(id);if(el)el.value='';});
  load();
}

async function apiFetch(url,opts={}){
  const tk=document.cookie.split('; ').find(c=>c.startsWith('auth_token='))?.split('=')[1];
  const h={'Content-Type':'application/json'};if(tk)h['Authorization']='Bearer '+tk;
  const r=await fetch(url,{...opts,headers:{...h,...(opts.headers||{})}});return r.json();
}

function getF(){
  const f={};
  const s=g('fSearch').value.trim();if(s)f.search=s;
  const b=g('fBuilding').value;if(b)f.building=b;
  const d=g('fDept').value;if(d)f.department=d;
  const p=g('fPerson').value;if(p)f.disposed_by=p;
  const st=g('fStatus').value;if(st)f.status=st;
  const df=g('fFrom').value;if(df)f.date_from=df;
  const dt=g('fTo').value;if(dt)f.date_to=dt;
  return f;
}

function setStats(st){
  if(!st)return;
  const tot=num(st.total),pnd=num(st.pending),cmp=num(st.completed),can=num(st.cancelled||st.rejected||0);
  ['dsTot','statTot'].forEach(id=>{const el=g(id);if(el)el.textContent=tot;});
  ['dsPend','statPend'].forEach(id=>{const el=g(id);if(el)el.textContent=pnd;});
  ['dsComp','statComp'].forEach(id=>{const el=g(id);if(el)el.textContent=cmp;});
  const sc=g('statCan');if(sc)sc.textContent=can;
}

function switchTab(t){
  curTab=t;curPage=1;
  g('tab-report').classList.toggle('on',t==='report');
  g('tab-list').classList.toggle('on',t==='list');
  g('vReport').style.display=t==='report'?'':'none';
  g('vList').style.display=t==='list'?'':'none';
  document.querySelectorAll('.db-stat').forEach(s=>s.classList.remove('active'));
  load();
}

function load(){curTab==='report'?loadReport():loadList();}

document.addEventListener('DOMContentLoaded',load);

/* ── REPORT ── */
async function loadReport(){
  const params=new URLSearchParams({action:'disposal_report',...getF()});
  try{
    const d=await apiFetch('/v1/api/borrow.php?'+params);
    if(!d.success)throw new Error(d.error);
    const r=d.data;
    setStats(r.stats||{});
    renderBar('rDept',r.by_department||[],'dept_name','item_count');
    const pd=(r.by_person||[]).map(p=>({...p,display_name:[p.first_name,p.last_name].filter(Boolean).join(' ')||'-',sub:p.department||''}));
    renderBar('rPerson',pd,'display_name','item_count','sub');
    renderBar('rReason',(r.by_reason||[]).map(x=>({...x,rl:RL[x.reason]||x.reason})),'rl','item_count');
    renderBar('rBuild',r.by_building||[],'bld_name','item_count');
    renderBar('rMethod',(r.by_method||[]).map(x=>({...x,ml:ML[x.method]||x.method})),'ml','item_count');
    const rec=r.recent||[];
    g('rRecentCnt').textContent=rec.length?(rec.length+' '+(TH?'รายการ':'items')):'';
    renderRecentList('rRecent',rec);
  }catch(e){
    ['rDept','rPerson','rReason','rBuild','rMethod','rRecent'].forEach(id=>g(id).innerHTML=`<div class="ci-alert ci-alert-danger" style="margin:0">${esc(e.message)}</div>`);
  }
}

function renderBar(elId,data,lbl,cnt,sub){
  const el=g(elId);
  if(!data.length){el.innerHTML=`<div class="db-empty" style="padding:20px"><i class="fas fa-inbox"></i><p>${TH?'ไม่มีข้อมูล':'No data'}</p></div>`;return;}
  const max=Math.max(...data.map(d=>parseInt(d[cnt])||0),1);
  el.innerHTML=data.map((d,i)=>{
    const n=parseInt(d[cnt])||0,pct=Math.round(n/max*100);
    return `<div class="db-bar-row">
      <div class="db-bar-lbl">
        <div class="db-bar-name">${esc(d[lbl]||'–')}</div>
        ${sub&&d[sub]?`<div class="db-bar-sub">${esc(d[sub])}</div>`:''}
      </div>
      <div class="db-bar-track"><div class="db-bar-fill ${GDS[i%GDS.length]}" style="width:${pct}%">${pct>18?n:''}</div></div>
      <div class="db-bar-n">${n}</div>
    </div>`;
  }).join('');
}

function renderRecentList(elId,items){
  const el=g(elId);
  if(!items.length){el.innerHTML=`<div class="db-empty" style="padding:20px"><i class="fas fa-inbox"></i><p>${TH?'ไม่มีรายการล่าสุด':'No recent items'}</p></div>`;return;}
  el.innerHTML='<div class="db-list">'+items.map(b=>itemCard(b)).join('')+'</div>';
}

/* ── LIST ── */
async function loadList(){
  const list=g('lList'),pager=g('lPager');
  list.innerHTML='<div class="db-ld"><i class="fas fa-circle-notch"></i></div>';
  pager.innerHTML='';
  const params=new URLSearchParams({action:'disposal_bin',page:curPage,per_page:20,show_all:1,...getF()});
  try{
    const d=await apiFetch('/v1/api/borrow.php?'+params);
    if(!d.success)throw new Error(d.error);
    const items=d.data.items||d.data||[];
    const pg=d.data.pagination;
    setStats(d.data.stats);
    if(!items.length){list.innerHTML=`<div class="db-empty"><i class="fas fa-trash-alt"></i><p>${TH?'ไม่พบรายการ':'No items found'}</p></div>`;return;}
    list.innerHTML='<div class="db-list">'+items.map(b=>itemCard(b)).join('')+'</div>';
    if(pg&&pg.total_pages>1){
      let h='<div class="db-pager">';
      h+=`<button class="db-pbtn" onclick="curPage--;loadList()" ${curPage<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
      for(let i=Math.max(1,curPage-2);i<=Math.min(pg.total_pages,curPage+2);i++)
        h+=`<button class="db-pbtn ${i===curPage?'on':''}" onclick="curPage=${i};loadList()">${i}</button>`;
      h+=`<button class="db-pbtn" onclick="curPage++;loadList()" ${curPage>=pg.total_pages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
      h+=`<span class="db-pinfo">${curPage}/${pg.total_pages} · ${num(pg.total)} ${TH?'รายการ':'items'}</span></div>`;
      pager.innerHTML=h;
    }
  }catch(e){list.innerHTML=`<div class="ci-alert ci-alert-danger">${esc(e.message)}</div>`;}
}

/* ── CONFIRM POPUP ── */
function showConfirm(item,action){
  _confPending={id:item.id,action};
  const isComplete=action==='complete';
  const hdr=g('dbConfHdr');
  hdr.style.background=isComplete
    ?'linear-gradient(135deg,#065f46,#059669)'
    :'linear-gradient(135deg,#5b21b6,#7c3aed)';
  g('dbConfIc').innerHTML=isComplete
    ?'<i class="fas fa-check-circle"></i>'
    :'<i class="fas fa-undo"></i>';
  g('dbConfTitle').textContent=isComplete
    ?(TH?'ยืนยันการจำหน่าย':'Confirm Disposal')
    :(TH?'ยืนยันการคืนกลับ':'Confirm Restore');
  g('dbConfSub').textContent=isComplete
    ?(TH?'สารเคมีนี้จะถูกบันทึกว่าจำหน่ายสำเร็จแล้ว':'This chemical will be marked as disposed')
    :(TH?'สารเคมีนี้จะถูกคืนกลับเข้าสู่ระบบ':'This chemical will be restored to inventory');

  const by=[item.disposed_first,item.disposed_last].filter(Boolean).join(' ')||'–';
  const reason=RL[item.disposal_reason]||item.disposal_reason||'–';
  const qty=num(item.remaining_qty)+' '+(item.unit||'');
  g('dbConfItem').innerHTML=`
    <div class="db-conf-item-top">
      <div class="db-conf-item-ic"><i class="fas fa-flask"></i></div>
      <div style="flex:1;min-width:0">
        <div class="db-conf-item-name">${esc(item.chemical_name||'–')}</div>
        ${item.barcode?`<div class="db-conf-item-bc"><i class="fas fa-barcode" style="margin-right:3px"></i>${esc(item.barcode)}</div>`:''}
      </div>
    </div>
    <div class="db-conf-item-chips">
      <span class="db-conf-chip qty"><i class="fas fa-weight-hanging"></i> ${qty}</span>
      <span class="db-conf-chip person"><i class="fas fa-user"></i> ${esc(by)}</span>
      <span class="db-conf-chip reason"><i class="fas fa-tag"></i> ${esc(reason)}</span>
    </div>`;

  const warn=g('dbConfWarn');
  if(isComplete){
    warn.className='db-conf-warn danger';
    warn.innerHTML=`<i class="fas fa-exclamation-triangle"></i><span>${TH?'การดำเนินการนี้ไม่สามารถย้อนกลับได้ สารเคมีจะถูกบันทึกว่าจำหน่ายออกจากระบบแล้ว':'This action cannot be undone. The chemical will be permanently marked as disposed.'}</span>`;
  } else {
    warn.className='db-conf-warn info';
    warn.innerHTML=`<i class="fas fa-info-circle"></i><span>${TH?'สารเคมีจะถูกนำกลับเข้าคลังและสามารถใช้งานได้ตามปกติ':'The chemical will be returned to inventory and available for use.'}</span>`;
  }

  const okBtn=g('dbConfOkBtn');
  okBtn.className='db-conf-btn '+(isComplete?'ok-green':'ok-purple');
  okBtn.innerHTML=isComplete
    ?`<i class="fas fa-check"></i> ${TH?'ยืนยันจำหน่าย':'Confirm Disposal'}`
    :`<i class="fas fa-undo"></i> ${TH?'ยืนยันคืนกลับ':'Confirm Restore'}`;
  okBtn.disabled=false;

  g('dbConfOv').classList.add('show');
}

function closeConfirm(){
  g('dbConfOv').classList.remove('show');
  _confPending=null;
}

async function _execConfirm(){
  if(!_confPending)return;
  const {id,action}=_confPending;
  const btn=g('dbConfOkBtn');
  btn.disabled=true;
  btn.innerHTML=`<i class="fas fa-circle-notch" style="animation:dbspin .8s linear infinite"></i> ${TH?'กำลังดำเนินการ...':'Processing...'}`;
  const ep=action==='complete'?'disposal_complete':'disposal_cancel';
  try{
    const d=await apiFetch('/v1/api/borrow.php?action='+ep,{method:'POST',body:JSON.stringify({bin_id:id})});
    if(!d.success)throw new Error(d.error);
    closeConfirm();
    toast(action==='complete'?(TH?'จำหน่ายสำเร็จ':'Completed'):(TH?'คืนกลับแล้ว':'Restored'),'ok');
    load();
  }catch(e){
    btn.disabled=false;
    btn.innerHTML=action==='complete'
      ?`<i class="fas fa-check"></i> ${TH?'ยืนยันจำหน่าย':'Confirm'}`
      :`<i class="fas fa-undo"></i> ${TH?'ยืนยันคืนกลับ':'Confirm'}`;
    toast(e.message,'err');
  }
}

document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeDetail();closeConfirm();}});

/* ── DETAIL MODAL ── */
const ST_GRAD={pending:'linear-gradient(135deg,#c2410c,#f97316)',approved:'linear-gradient(135deg,#1d4ed8,#3b82f6)',completed:'linear-gradient(135deg,#065f46,#10b981)',rejected:'linear-gradient(135deg,#5b21b6,#a855f7)'};
const ST_IC={pending:'fa-clock',approved:'fa-thumbs-up',completed:'fa-check-circle',rejected:'fa-undo'};

function openDetail(id){
  const b=_dbItems[id];if(!b)return;
  const st=b.status||'pending';
  g('dbDetHdr').style.background=ST_GRAD[st]||ST_GRAD.pending;
  g('dbDetIc').innerHTML=`<i class="fas ${ST_IC[st]||'fa-flask'}"></i>`;
  g('dbDetLabel').textContent=SL[st]||st;
  g('dbDetName').textContent=b.chemical_name||'–';
  const bcEl=g('dbDetBc');
  bcEl.textContent=b.barcode||'';
  bcEl.style.display=b.barcode?'inline-block':'none';

  const qty=num(b.remaining_qty)+' '+(b.unit||'');
  g('dbDetStrip').innerHTML=`
    <div class="db-det-sc">
      <div class="db-det-sv">${qty}</div>
      <div class="db-det-sl">${TH?'ปริมาณ':'Quantity'}</div>
    </div>
    <div class="db-det-sc">
      <div class="db-det-sv"><span class="db-chip ${st}">${SL[st]||st}</span></div>
      <div class="db-det-sl">${TH?'สถานะ':'Status'}</div>
    </div>
    <div class="db-det-sc">
      <div class="db-det-sv" style="font-size:12px">${fmtDate(b.created_at)}</div>
      <div class="db-det-sl">${TH?'วันที่':'Date'}</div>
    </div>`;

  const by=[b.disposed_first,b.disposed_last].filter(Boolean).join(' ')||'–';
  const reason=RL[b.disposal_reason]||b.disposal_reason||'–';
  const method=b.disposal_method?ML[b.disposal_method]||b.disposal_method:'';
  const loc=[b.department,b.building_name].filter(Boolean).join(' · ');
  const cards=[];
  cards.push(`<div class="db-det-card">
    <div class="db-det-card-ic" style="background:#eff6ff;color:#2563eb"><i class="fas fa-user"></i></div>
    <div><div class="db-det-card-lbl">${TH?'ผู้จำหน่าย':'Disposed By'}</div><div class="db-det-card-val">${esc(by)}</div></div>
  </div>`);
  cards.push(`<div class="db-det-card">
    <div class="db-det-card-ic" style="background:#fff7ed;color:#f97316"><i class="fas fa-tag"></i></div>
    <div><div class="db-det-card-lbl">${TH?'เหตุผล':'Reason'}</div><div class="db-det-card-val">${esc(reason)}</div></div>
  </div>`);
  if(method)cards.push(`<div class="db-det-card">
    <div class="db-det-card-ic" style="background:#faf5ff;color:#7c3aed"><i class="fas fa-cogs"></i></div>
    <div><div class="db-det-card-lbl">${TH?'วิธีจำหน่าย':'Method'}</div><div class="db-det-card-val">${esc(method)}</div></div>
  </div>`);
  if(loc)cards.push(`<div class="db-det-card">
    <div class="db-det-card-ic" style="background:#f0fdf4;color:#059669"><i class="fas fa-building"></i></div>
    <div><div class="db-det-card-lbl">${TH?'หน่วยงาน / อาคาร':'Dept / Building'}</div><div class="db-det-card-val">${esc(loc)}</div></div>
  </div>`);
  if(b.notes)cards.push(`<div class="db-det-card">
    <div class="db-det-card-ic" style="background:#fef9c3;color:#a16207"><i class="fas fa-sticky-note"></i></div>
    <div><div class="db-det-card-lbl">${TH?'หมายเหตุ':'Notes'}</div><div class="db-det-card-val">${esc(b.notes)}</div></div>
  </div>`);
  g('dbDetBody').innerHTML=cards.join('');

  const ftr=g('dbDetFtr');
  if(IS_ADMIN&&(st==='pending'||st==='approved')){
    ftr.style.display='flex';
    ftr.innerHTML=`
      <button class="db-det-act confirm" onclick="closeDetail();showConfirm(_dbItems[${b.id}],'complete')">
        <i class="fas fa-check"></i> ${TH?'ยืนยันจำหน่าย':'Complete'}
      </button>
      <button class="db-det-act restore" onclick="closeDetail();showConfirm(_dbItems[${b.id}],'cancel')">
        <i class="fas fa-undo"></i> ${TH?'คืนกลับ':'Restore'}
      </button>`;
  } else {
    ftr.style.display='none';
  }
  g('dbDetOv').classList.add('show');
}

function closeDetail(){g('dbDetOv').classList.remove('show');}

/* ── CARD RENDERER ── */
function itemCard(b){
  _dbItems[b.id]=b;
  const name=esc(b.chemical_name||'–');
  const by=esc([b.disposed_first,b.disposed_last].filter(Boolean).join(' ')||'–');
  const reason=RL[b.disposal_reason]||b.disposal_reason||'–';
  const method=b.disposal_method?ML[b.disposal_method]||b.disposal_method:'';
  const st=b.status||'pending';
  const qty=num(b.remaining_qty)+' '+(b.unit||'');
  let acts='';
  if(IS_ADMIN&&(st==='pending'||st==='approved')){
    acts=`<div class="db-item-actions">
      <button class="db-act-btn confirm" onclick="event.stopPropagation();showConfirm(_dbItems[${b.id}],'complete')"><i class="fas fa-check"></i> ${TH?'ยืนยันจำหน่าย':'Complete'}</button>
      <button class="db-act-btn restore" onclick="event.stopPropagation();showConfirm(_dbItems[${b.id}],'cancel')"><i class="fas fa-undo"></i> ${TH?'คืนกลับ':'Restore'}</button>
    </div>`;
  }
  return `<div class="db-item s-${st}" onclick="openDetail(${b.id})">
    <div class="db-item-accent"></div>
    <div class="db-item-inner">
      <div class="db-item-top">
        <div class="db-item-ico"><i class="fas fa-flask"></i></div>
        <div class="db-item-main">
          <div class="db-item-name">${name}</div>
          ${b.barcode?`<div class="db-item-bc"><i class="fas fa-barcode"></i>${esc(b.barcode)}</div>`:''}
        </div>
        <div class="db-item-right">
          <span class="db-chip ${st}">${SL[st]||st}</span>
          <span class="db-item-qty">${qty}</span>
        </div>
      </div>
      <div class="db-item-meta">
        <span><i class="fas fa-user"></i> ${by}</span>
        <span><i class="fas fa-tag"></i> ${esc(reason)}</span>
        ${method?`<span><i class="fas fa-cogs"></i> ${esc(method)}</span>`:''}
        ${b.department?`<span><i class="fas fa-building"></i> ${esc(b.department)}</span>`:''}
        <span><i class="fas fa-calendar"></i> ${fmtDate(b.created_at)}</span>
      </div>
      ${acts}
    </div>
  </div>`;
}


function toast(msg,type='ok'){
  const t=document.createElement('div');
  t.style.cssText='position:fixed;top:68px;right:20px;z-index:9999;display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:12px;color:#fff;font-size:13px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.18);transition:opacity .3s';
  t.style.background=type==='ok'?'linear-gradient(135deg,#059669,#10b981)':'linear-gradient(135deg,#dc2626,#ef4444)';
  t.innerHTML=`<i class="fas fa-${type==='ok'?'check-circle':'times-circle'}"></i> ${esc(msg)}`;
  document.body.appendChild(t);
  setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.remove(),300);},3000);
}
</script>
</body></html>
