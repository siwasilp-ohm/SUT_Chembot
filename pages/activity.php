<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$userId    = $user['id'];
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin   = $roleLevel >= 5;
$isManager = $roleLevel >= 3;
$TH        = $lang === 'th';
Layout::head($TH ? 'ประวัติธุรกรรม' : 'Transaction History');
?>
<body>
<?php Layout::sidebar('activity'); Layout::beginContent(); ?>
<style>
:root{--act-r:14px;--act-rs:10px;--act-sh:0 1px 6px rgba(0,0,0,.06);--act-shm:0 4px 20px rgba(0,0,0,.08)}

/* ── Hero ── */
.act-hero{background:linear-gradient(135deg,#1e1b4b 0%,#4338ca 55%,#6366f1 100%);border-radius:var(--act-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.act-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.act-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;position:relative}
.act-hero-info{position:relative}
.act-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.act-hero-info p{font-size:12px;opacity:.85;margin:0}
.act-hero-meta{margin-left:auto;display:flex;gap:24px;flex-shrink:0;position:relative}
.act-hero-c{text-align:center}
.act-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.act-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}

/* ── Stats Row ── */
.act-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.act-stat{background:#fff;border-radius:var(--act-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--act-sh);border:1px solid #e2e8f0;transition:all .15s;cursor:pointer}
.act-stat:hover{transform:translateY(-2px);box-shadow:var(--act-shm)}
.act-stat.active{border-color:#a5b4fc;background:#ede9fe;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.act-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.act-sv{font-size:20px;font-weight:800;color:#0f172a;line-height:1}
.act-sl{font-size:10px;color:#94a3b8;margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Tabs ── */
.act-tabs{display:inline-flex;background:#f1f5f9;border-radius:var(--act-rs);padding:3px;margin-bottom:18px}
.act-tab{padding:8px 20px;font-size:12px;font-weight:600;color:#64748b;border-radius:8px;cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:6px;white-space:nowrap}
.act-tab:hover{color:#0f172a}
.act-tab.active{background:#fff;color:#4338ca;box-shadow:0 1px 4px rgba(0,0,0,.08)}

/* ── Panel ── */
.act-panel{background:#fff;border:1.5px solid #e2e8f0;border-radius:var(--act-r);padding:20px;box-shadow:var(--act-sh);animation:actFd .2s ease}
@keyframes actFd{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}

/* ── Breadcrumb ── */
.act-bc{display:flex;align-items:center;gap:6px;margin-bottom:16px;font-size:12px;flex-wrap:wrap}
.act-bc a{color:#4338ca;font-weight:600;cursor:pointer;text-decoration:none}
.act-bc a:hover{text-decoration:underline}
.act-bc-sep{color:#cbd5e1;font-size:10px}
.act-bc-cur{color:#0f172a;font-weight:700}

/* ── Type Grid ── */
.act-tgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:10px}
.act-tcard{border:1.5px solid #e2e8f0;border-radius:var(--act-r);padding:18px 16px;cursor:pointer;transition:all .18s;background:#fff;position:relative;overflow:hidden;box-shadow:var(--act-sh)}
.act-tcard:hover{border-color:#c7d2fe;box-shadow:var(--act-shm);transform:translateY(-2px)}
.act-tcard-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px}
.act-tcard-val{font-size:28px;font-weight:900;color:#0f172a;line-height:1}
.act-tcard-lbl{font-size:12px;color:#64748b;font-weight:600;margin-top:3px}
.act-tcard-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:10px}
.act-stag{font-size:10px;padding:2px 8px;border-radius:8px;font-weight:700;cursor:pointer;transition:.12s}
.act-stag:hover{filter:brightness(.92);transform:scale(1.05)}
.act-stag.completed{background:#dcfce7;color:#166534}
.act-stag.pending{background:#fef9c3;color:#854d0e}
.act-stag.rejected{background:#fee2e2;color:#991b1b}
.act-stag.approved{background:#dbeafe;color:#1e40af}
.act-stag.cancelled{background:#f1f5f9;color:#64748b}
.act-tcard::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px}
.act-tcard.tc-borrow::after{background:linear-gradient(90deg,#ea580c,#fb923c)}
.act-tcard.tc-use::after{background:linear-gradient(90deg,#7c3aed,#a78bfa)}
.act-tcard.tc-return::after{background:linear-gradient(90deg,#059669,#34d399)}
.act-tcard.tc-transfer::after{background:linear-gradient(90deg,#2563eb,#60a5fa)}
.act-tcard.tc-dispose::after{background:linear-gradient(90deg,#dc2626,#f87171)}

/* ── Filter Bar ── */
.act-fbar{display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;padding:12px 14px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0}
.act-fbar-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.act-fbar h4{margin:0;font-size:14px;font-weight:700;color:#0f172a}
.act-fbar-count{font-size:11px;color:#94a3b8;font-weight:500}
.act-fbtns{display:flex;gap:5px;margin-left:auto;flex-wrap:wrap}
.act-fbtn{padding:5px 12px;border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;font-size:11px;font-weight:600;color:#64748b;cursor:pointer;transition:.12s}
.act-fbtn:hover,.act-fbtn.on{background:#ede9fe;border-color:#a5b4fc;color:#4338ca}

/* ── TXN List ── */
.act-list{display:flex;flex-direction:column;gap:5px}
.act-row{display:flex;align-items:center;gap:12px;padding:11px 14px;border:1.5px solid #f1f5f9;border-radius:11px;background:#fff;transition:.15s;box-shadow:var(--act-sh);cursor:pointer}
.act-row:hover{background:#f8fafc;border-color:#e2e8f0;transform:translateX(2px)}
.act-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.act-dot.borrow{background:#ea580c}.act-dot.use{background:#7c3aed}
.act-dot.return{background:#059669}.act-dot.transfer{background:#2563eb}.act-dot.dispose{background:#dc2626}
.act-rinfo{flex:1;min-width:0}
.act-rname{font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.act-rsub{font-size:10px;color:#94a3b8;margin-top:2px;display:flex;gap:8px;flex-wrap:wrap}
.act-rsub span{display:flex;align-items:center;gap:3px}
.act-rbadge{font-size:9px;padding:2px 8px;border-radius:7px;font-weight:700;white-space:nowrap;flex-shrink:0;letter-spacing:.2px;text-transform:uppercase}
.act-rbadge.completed{background:#dcfce7;color:#166534}
.act-rbadge.pending{background:#fef9c3;color:#854d0e}
.act-rbadge.rejected{background:#fee2e2;color:#991b1b}
.act-rbadge.approved{background:#dbeafe;color:#1e40af}
.act-rbadge.cancelled{background:#f1f5f9;color:#64748b}
.act-rqty{font-size:12px;font-weight:700;color:#0f172a;white-space:nowrap;flex-shrink:0}
.act-rdate{font-size:10px;color:#94a3b8;white-space:nowrap;flex-shrink:0}

/* ── Type chip ── */
.act-type-chip{display:inline-flex;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;margin-right:4px}
.act-type-chip.borrow{background:#fff7ed;color:#ea580c}
.act-type-chip.use{background:#faf5ff;color:#7c3aed}
.act-type-chip.return{background:#f0fdf4;color:#059669}
.act-type-chip.transfer{background:#eff6ff;color:#2563eb}
.act-type-chip.dispose{background:#fef2f2;color:#dc2626}

/* ── Pager ── */
.act-pager{display:flex;align-items:center;justify-content:center;gap:5px;margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9}
.act-pbtn{width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:11px;cursor:pointer;transition:.12s}
.act-pbtn:hover{background:#ede9fe;border-color:#a5b4fc;color:#4338ca}
.act-pbtn.on{background:#4338ca;color:#fff;border-color:#4338ca}
.act-pbtn:disabled{opacity:.3;cursor:default}
.act-pinfo{font-size:11px;color:#94a3b8;padding:0 6px}

/* ── Chemical List ── */
.act-clist{display:flex;flex-direction:column;gap:5px}
.act-crow{display:flex;align-items:center;gap:12px;padding:13px 16px;border:1.5px solid #e2e8f0;border-radius:12px;cursor:pointer;background:#fff;transition:.15s;box-shadow:var(--act-sh)}
.act-crow:hover{background:#f8fafc;border-color:#c7d2fe;transform:translateX(2px)}
.act-crank{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.r1{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff}
.r2{background:linear-gradient(135deg,#9ca3af,#6b7280);color:#fff}
.r3{background:linear-gradient(135deg,#d97706,#b45309);color:#fff}
.rn{background:#f1f5f9;color:#64748b}
.act-cname{font-size:13px;font-weight:700;color:#0f172a;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.act-ccas{font-size:10px;color:#94a3b8;font-family:'Courier New',monospace;margin-top:2px}
.act-cval{font-size:12px;font-weight:700;color:#4338ca;white-space:nowrap;background:#ede9fe;padding:3px 10px;border-radius:8px}

/* ── Timeline ── */
.act-tlgroup{margin-bottom:10px;border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:var(--act-sh)}
.act-tlhdr{display:flex;align-items:center;gap:8px;padding:12px 16px;background:#f8fafc;cursor:pointer;transition:.12s}
.act-tlhdr:hover{background:#f1f5f9}
.act-tlhdr-bc{font-size:12px;font-weight:700;font-family:'Courier New',monospace;color:#0f172a;flex:1}
.act-tlhdr-cnt{font-size:10px;color:#64748b;background:#e2e8f0;padding:2px 8px;border-radius:6px;font-weight:600}
.act-tlhdr-chev{font-size:9px;color:#94a3b8;transition:transform .2s}
.act-tlhdr-chev.open{transform:rotate(90deg)}
.act-tlbody{padding:14px 16px 16px;border-top:1px solid #f1f5f9}
.act-tlbody.hide{display:none}
.act-tl{display:flex;gap:12px;padding-bottom:14px;position:relative}
.act-tl:last-child{padding-bottom:0}
.act-tl-node{display:flex;flex-direction:column;align-items:center;width:20px;flex-shrink:0}
.act-tl-dot{width:10px;height:10px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 2px currentColor;flex-shrink:0;z-index:1}
.act-tl-dot.borrow{color:#ea580c;background:#fff7ed}
.act-tl-dot.use{color:#7c3aed;background:#faf5ff}
.act-tl-dot.return{color:#059669;background:#ecfdf5}
.act-tl-dot.transfer{color:#2563eb;background:#eff6ff}
.act-tl-dot.dispose{color:#dc2626;background:#fef2f2}
.act-tl-line{width:2px;flex:1;background:#e2e8f0;margin-top:3px}
.act-tl:last-child .act-tl-line{display:none}
.act-tl-body{flex:1;min-width:0;background:#f8fafc;border-radius:10px;padding:10px 12px}
.act-tl-title{font-size:12px;font-weight:600;color:#0f172a;display:flex;align-items:center;flex-wrap:wrap;gap:4px}
.act-tl-meta{font-size:10px;color:#94a3b8;margin-top:5px;display:flex;gap:8px;flex-wrap:wrap}
.act-tl-meta span{display:flex;align-items:center;gap:3px}

/* ── Chem header (lifecycle) ── */
.act-chem-hdr{display:flex;align-items:center;gap:14px;margin-bottom:20px;padding:16px 18px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);border-radius:14px;border:1.5px solid #c4b5fd}
.act-chem-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.act-chem-nm{font-size:16px;font-weight:800;color:#1e1b4b;margin-bottom:4px}
.act-chem-meta{font-size:11px;color:#6d28d9;display:flex;gap:12px;flex-wrap:wrap}
.act-chem-meta span{display:flex;align-items:center;gap:4px}

/* ── Chart ── */
.act-chart-tb{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.act-chmode{display:flex;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;background:#f8fafc}
.act-chmbtn{padding:6px 14px;border:none;background:transparent;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;transition:.12s;border-right:1px solid #e2e8f0}
.act-chmbtn:last-child{border-right:none}
.act-chmbtn.on{background:#4338ca;color:#fff}
.act-chfil{margin-left:auto;display:flex;gap:6px;flex-wrap:wrap}
.act-chsel{padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:11px;background:#fff;color:#0f172a;cursor:pointer;outline:none;transition:.12s}
.act-chsel:focus{border-color:#a5b4fc}
.act-leg{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;padding:10px 14px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0}
.act-legitem{display:flex;align-items:center;gap:5px;font-size:11px;color:#475569;font-weight:600;cursor:pointer;padding:3px 8px;border-radius:6px;border:1.5px solid transparent;transition:.12s}
.act-legitem:hover{background:#fff;border-color:#e2e8f0}
.act-legitem.off{opacity:.3;text-decoration:line-through}
.act-legdot{width:10px;height:10px;border-radius:3px}
.act-chartwrap{position:relative;height:240px;border:1.5px solid #e2e8f0;border-radius:12px;background:#fafbfc;overflow:visible;margin-bottom:12px}
.act-chartsvg{width:100%;height:100%;overflow:visible}
.act-chartsvg .gridl{stroke:#f1f5f9;stroke-width:1}
.act-chartsvg .gridtxt{fill:#94a3b8;font-size:9px}
.act-chartbar{cursor:pointer;transition:filter .15s}
.act-chartbar:hover{filter:brightness(1.12)}
.act-charttip{position:absolute;pointer-events:none;background:#0f172a;color:#fff;font-size:10px;padding:8px 12px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.2);opacity:0;transition:opacity .15s;z-index:20;min-width:130px;line-height:1.7}
.act-charttip.show{opacity:1}

/* ── Drill header ── */
.act-drill-hdr{display:flex;align-items:center;gap:8px;margin:16px 0 10px;padding:12px 14px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0}
.act-drill-title{font-size:13px;font-weight:700;color:#0f172a;flex:1}

/* ── Detail Modal ── */
.act-mdl-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(8px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity .2s}
.act-mdl-ov.show{opacity:1;pointer-events:all}
.act-mdl{background:#fff;border-radius:20px;width:100%;max-width:480px;max-height:90vh;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.25);transform:translateY(16px) scale(.97);transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s;display:flex;flex-direction:column}
.act-mdl-ov.show .act-mdl{transform:none}
.act-mdl-hdr{padding:22px 22px 0;color:#fff;position:relative;flex-shrink:0}
.act-mdl-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:.15s;z-index:10}
.act-mdl-close:hover{background:rgba(255,255,255,.35);transform:scale(1.1)}
.act-mdl-hdr-top{display:flex;align-items:flex-start;gap:14px;padding-bottom:18px}
.act-mdl-hdr-ic{width:54px;height:54px;border-radius:16px;background:rgba(255,255,255,.2);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.act-mdl-hdr-info{flex:1;min-width:0}
.act-mdl-hdr-type{font-size:10px;font-weight:700;opacity:.75;text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px}
.act-mdl-hdr-name{font-size:17px;font-weight:800;line-height:1.25;margin-bottom:5px}
.act-mdl-hdr-bc{font-size:11px;font-family:'Courier New',monospace;background:rgba(0,0,0,.18);padding:2px 9px;border-radius:5px;display:inline-block}
.act-mdl-strip{display:grid;grid-template-columns:repeat(3,1fr);background:#f8fafc;border-top:1px solid #e2e8f0;flex-shrink:0}
.act-mdl-sc{padding:14px 10px;text-align:center;border-right:1px solid #e2e8f0}
.act-mdl-sc:last-child{border-right:none}
.act-mdl-sv{font-size:17px;font-weight:800;color:#0f172a;line-height:1;display:flex;align-items:center;justify-content:center;gap:4px;flex-wrap:wrap}
.act-mdl-sl{font-size:10px;color:#94a3b8;margin-top:4px;text-transform:uppercase;letter-spacing:.3px}
.act-mdl-body{padding:16px 18px 22px;overflow-y:auto;display:flex;flex-direction:column;gap:8px}
.act-mdl-card{display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1.5px solid #f1f5f9;border-radius:12px;background:#fafbfc}
.act-mdl-card-ic{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.act-mdl-card-lbl{font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.act-mdl-card-val{font-size:13px;font-weight:600;color:#0f172a;line-height:1.4}

/* ── Common ── */
.act-empty{text-align:center;padding:40px 20px;color:#94a3b8}
.act-empty i{font-size:36px;display:block;margin-bottom:10px;opacity:.25}
.act-empty p{font-size:12px;margin:0;font-weight:500}
.act-ld{text-align:center;padding:32px;color:#94a3b8}
.act-ld i{animation:actSpin .8s linear infinite;font-size:20px}
@keyframes actSpin{to{transform:rotate(360deg)}}

@media(max-width:640px){
  .act-stats{display:flex;overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:4px;scrollbar-width:none;gap:8px}
  .act-stats::-webkit-scrollbar{display:none}
  .act-stat{flex-shrink:0;min-width:130px}
  .act-tgrid{grid-template-columns:repeat(2,1fr)}
  .act-panel{padding:14px}
  .act-rsub{display:none}
  .act-hero-meta{display:none}
  .act-hero{padding:18px 20px}
  .act-hero-info h2{font-size:16px}
  .act-tabs{overflow-x:auto;width:100%;-webkit-overflow-scrolling:touch}
}
</style>

<?php Layout::pageHeader(
    $TH ? 'ประวัติธุรกรรม' : 'Transaction History',
    'fas fa-history',
    $TH ? 'ดูความเคลื่อนไหวธุรกรรมสารเคมีแยกตามประเภทและสารเคมี' : 'View chemical transaction history by type and chemical'
); ?>

<div class="ap">

<!-- HERO BANNER -->
<div class="act-hero">
  <div class="act-hero-ic"><i class="fas fa-history"></i></div>
  <div class="act-hero-info">
    <h2><?php echo $TH?'ประวัติธุรกรรม':'Transaction History'?></h2>
    <p><?php echo $TH?'ติดตามความเคลื่อนไหวสารเคมีทุกประเภท':'Track all chemical transaction movements'?></p>
  </div>
  <div class="act-hero-meta">
    <div class="act-hero-c"><div class="v" id="heroTotal">–</div><div class="lb"><?php echo $TH?'ทั้งหมด':'Total'?></div></div>
    <div class="act-hero-c"><div class="v">5</div><div class="lb"><?php echo $TH?'ประเภท':'Types'?></div></div>
  </div>
</div>

<!-- STATS ROW -->
<div class="act-stats" id="actStats">
  <div class="act-stat" id="actStatAll" onclick="statClick(null)"><div class="act-si" style="background:#eff6ff;color:#2563eb"><i class="fas fa-chart-bar"></i></div><div><div class="act-sv" id="apTotal">–</div><div class="act-sl"><?php echo $TH?'ทั้งหมด':'Total'?></div></div></div>
</div>

<!-- TABS -->
<div class="act-tabs">
  <button class="act-tab active" id="apTab-type" onclick="switchView('type')"><i class="fas fa-layer-group"></i> <?php echo $TH?'ตามประเภท':'By Type'?></button>
  <button class="act-tab" id="apTab-chemical" onclick="switchView('chemical')"><i class="fas fa-flask"></i> <?php echo $TH?'สารเคมี':'Chemical'?></button>
  <button class="act-tab" id="apTab-chart" onclick="switchView('chart')"><i class="fas fa-chart-area"></i> <?php echo $TH?'กราฟ':'Chart'?></button>
</div>

<!-- PANEL -->
<div class="act-panel" id="apPanel"><div class="act-ld"><i class="fas fa-circle-notch"></i></div></div>
</div>

<?php Layout::endContent(); ?>
<!-- DETAIL MODAL -->
<div class="act-mdl-ov" id="actMdlOv" onclick="if(event.target===this)closeActMdl()">
  <div class="act-mdl">
    <div class="act-mdl-hdr" id="actMdlHdr">
      <button class="act-mdl-close" onclick="closeActMdl()"><i class="fas fa-times"></i></button>
      <div class="act-mdl-hdr-top">
        <div class="act-mdl-hdr-ic" id="actMdlIc"></div>
        <div class="act-mdl-hdr-info">
          <div class="act-mdl-hdr-type" id="actMdlType"></div>
          <div class="act-mdl-hdr-name" id="actMdlName"></div>
          <div class="act-mdl-hdr-bc" id="actMdlBc"></div>
        </div>
      </div>
    </div>
    <div class="act-mdl-strip" id="actMdlStrip"></div>
    <div class="act-mdl-body" id="actMdlBody"></div>
  </div>
</div>
<script>
const TH=<?php echo json_encode($TH);?>;
let D=null,view='type';

const TM={
  borrow:{icon:'fa-hand-holding-medical',lbl:TH?'ยืม':'Borrow',color:'#ea580c',bg:'#fff7ed',cls:'borrow'},
  use:{icon:'fa-eye-dropper',lbl:TH?'เบิกใช้':'Use',color:'#7c3aed',bg:'#faf5ff',cls:'use'},
  return:{icon:'fa-undo',lbl:TH?'คืน':'Return',color:'#059669',bg:'#ecfdf5',cls:'return'},
  transfer:{icon:'fa-people-arrows',lbl:TH?'โอน':'Transfer',color:'#2563eb',bg:'#eff6ff',cls:'transfer'},
  dispose:{icon:'fa-trash-alt',lbl:TH?'จำหน่าย':'Dispose',color:'#dc2626',bg:'#fef2f2',cls:'dispose'}
};
const SL={completed:TH?'สำเร็จ':'Done',pending:TH?'รอ':'Pending',rejected:TH?'ปฏิเสธ':'Rejected',approved:TH?'อนุมัติ':'Approved',cancelled:TH?'ยกเลิก':'Cancelled'};
const CC={borrow:'#fb923c',use:'#a78bfa',transfer:'#60a5fa',return:'#34d399',dispose:'#f87171'};
const TXN_GRAD={borrow:'linear-gradient(135deg,#c2410c,#ea580c)',use:'linear-gradient(135deg,#6d28d9,#7c3aed)',return:'linear-gradient(135deg,#047857,#059669)',transfer:'linear-gradient(135deg,#1d4ed8,#2563eb)',dispose:'linear-gradient(135deg,#b91c1c,#dc2626)'};

let _txnRows=[];

function openTxnDetail(idx){
  const r=_txnRows[idx];if(!r)return;
  const m=TM[r.txn_type]||TM.borrow;
  const hdr=g('actMdlHdr');
  hdr.style.background=TXN_GRAD[r.txn_type]||TXN_GRAD.borrow;
  g('actMdlIc').innerHTML=`<i class="fas ${m.icon}"></i>`;
  g('actMdlType').textContent=m.lbl;
  g('actMdlName').textContent=r.chemical_name||'';
  const bcEl=g('actMdlBc');
  bcEl.textContent=r.barcode||'';
  bcEl.style.display=r.barcode?'inline-block':'none';
  // strip — qty / status / date
  g('actMdlStrip').innerHTML=`
    <div class="act-mdl-sc">
      <div class="act-mdl-sv">${num(r.quantity)}<span style="font-size:10px;font-weight:600;color:#64748b">${esc(r.unit||'')}</span></div>
      <div class="act-mdl-sl">${TH?'ปริมาณ':'Quantity'}</div>
    </div>
    <div class="act-mdl-sc">
      <div class="act-mdl-sv"><span class="act-rbadge ${r.status}">${SL[r.status]||r.status}</span></div>
      <div class="act-mdl-sl">${TH?'สถานะ':'Status'}</div>
    </div>
    <div class="act-mdl-sc">
      <div class="act-mdl-sv" style="font-size:12px">${fmtDate(r.created_at)}</div>
      <div class="act-mdl-sl">${TH?'วันที่':'Date'}</div>
    </div>`;
  // body cards
  const cards=[];
  if(r.chemical_name){
    cards.push(`<div class="act-mdl-card">
      <div class="act-mdl-card-ic" style="background:${m.bg};color:${m.color}"><i class="fas fa-flask"></i></div>
      <div><div class="act-mdl-card-lbl">${TH?'สารเคมี':'Chemical'}</div><div class="act-mdl-card-val">${esc(r.chemical_name)}</div></div>
    </div>`);
  }
  if(r.barcode){
    cards.push(`<div class="act-mdl-card">
      <div class="act-mdl-card-ic" style="background:#f1f5f9;color:#64748b"><i class="fas fa-barcode"></i></div>
      <div><div class="act-mdl-card-lbl">Barcode</div><div class="act-mdl-card-val" style="font-family:'Courier New',monospace;font-size:12px;letter-spacing:.5px">${esc(r.barcode)}</div></div>
    </div>`);
  }
  if(r.from_name||r.to_name){
    const ft=r.from_name&&r.to_name?`${esc(r.from_name)} <i class="fas fa-arrow-right" style="font-size:9px;color:#94a3b8"></i> ${esc(r.to_name)}`:esc(r.from_name||r.to_name);
    cards.push(`<div class="act-mdl-card">
      <div class="act-mdl-card-ic" style="background:#eff6ff;color:#2563eb"><i class="fas fa-user"></i></div>
      <div><div class="act-mdl-card-lbl">${TH?'ผู้เกี่ยวข้อง':'Personnel'}</div><div class="act-mdl-card-val">${ft}</div></div>
    </div>`);
  }
  if(r.location||r.from_location||r.to_location){
    const loc=esc(r.location||r.from_location||r.to_location);
    cards.push(`<div class="act-mdl-card">
      <div class="act-mdl-card-ic" style="background:#f0fdf4;color:#059669"><i class="fas fa-map-marker-alt"></i></div>
      <div><div class="act-mdl-card-lbl">${TH?'สถานที่':'Location'}</div><div class="act-mdl-card-val">${loc}</div></div>
    </div>`);
  }
  if(r.notes){
    cards.push(`<div class="act-mdl-card">
      <div class="act-mdl-card-ic" style="background:#fef9c3;color:#a16207"><i class="fas fa-sticky-note"></i></div>
      <div><div class="act-mdl-card-lbl">${TH?'หมายเหตุ':'Notes'}</div><div class="act-mdl-card-val">${esc(r.notes)}</div></div>
    </div>`);
  }
  if(!cards.length)cards.push(`<div style="text-align:center;color:#94a3b8;font-size:12px;padding:12px 0;font-weight:500">${TH?'ไม่มีข้อมูลเพิ่มเติม':'No additional details'}</div>`);
  g('actMdlBody').innerHTML=cards.join('');
  g('actMdlOv').classList.add('show');
}

function closeActMdl(){g('actMdlOv').classList.remove('show');}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeActMdl();});

function g(id){return document.getElementById(id);}
function esc(s){const d=document.createElement('div');d.textContent=String(s??'');return d.innerHTML;}
function num(v){return Number(v||0).toLocaleString();}
function fmtDate(s){if(!s)return'';const d=new Date(s);return d.toLocaleDateString(TH?'th-TH':'en-GB',{day:'2-digit',month:'short',year:'2-digit'});}

async function apiFetch(url){
  const tk=document.cookie.split('; ').find(c=>c.startsWith('auth_token='))?.split('=')[1];
  const h={'Content-Type':'application/json'};if(tk)h['Authorization']='Bearer '+tk;
  const r=await fetch(url,{headers:h});return r.json();
}

document.addEventListener('DOMContentLoaded',async()=>{
  const p=g('apPanel');
  try{
    const d=await apiFetch('/v1/api/borrow.php?action=activity_summary');
    if(!d.success)throw new Error(d.error);
    D=d.data;
    buildSum();renderView();
  }catch(e){p.innerHTML=`<div class="ci-alert ci-alert-danger">${esc(e.message)}</div>`;}
});

function buildSum(){
  const bt=D.by_type||{};
  let tot=0;Object.values(bt).forEach(v=>tot+=v.total||0);
  if(g('apTotal'))g('apTotal').textContent=num(tot);
  if(g('heroTotal'))g('heroTotal').textContent=num(tot);
  const el=g('actStats');
  while(el.children.length>1)el.removeChild(el.lastChild);
  el.querySelector('.act-sv').textContent=num(tot);
  ['borrow','use','return','transfer','dispose'].forEach(t=>{
    const m=TM[t],n=bt[t]?.total||0;
    const c=document.createElement('div');c.className='act-stat';c.dataset.type=t;
    c.innerHTML=`<div class="act-si" style="background:${m.bg};color:${m.color}"><i class="fas ${m.icon}"></i></div><div><div class="act-sv">${num(n)}</div><div class="act-sl">${m.lbl}</div></div>`;
    c.onclick=()=>statClick(t);
    el.appendChild(c);
  });
}

function statClick(type){
  // highlight active stat card
  document.querySelectorAll('.act-stat').forEach(s=>s.classList.remove('active'));
  if(type){
    document.querySelector(`.act-stat[data-type="${type}"]`)?.classList.add('active');
  } else {
    g('actStatAll')?.classList.add('active');
  }
  // switch to type tab + open detail
  view='type';
  document.querySelectorAll('.act-tab').forEach(b=>b.classList.remove('active'));
  g('apTab-type').classList.add('active');
  if(type) openType(type);
  else renderTypeGrid();
}

function switchView(v){
  view=v;
  document.querySelectorAll('.act-tab').forEach(b=>b.classList.remove('active'));
  g('apTab-'+v).classList.add('active');
  // clear stat highlight when switching tab manually
  document.querySelectorAll('.act-stat').forEach(s=>s.classList.remove('active'));
  renderView();
}

function renderView(){
  if(!D)return;
  switch(view){
    case'type':renderTypeGrid();break;
    case'chemical':renderChemList();break;
    case'chart':renderChart();break;
  }
}

/* ── TYPE GRID ── */
function renderTypeGrid(){
  const bt=D.by_type||{};
  let h='<div class="act-tgrid">';
  ['borrow','use','return','transfer','dispose'].forEach(t=>{
    const m=TM[t],info=bt[t],tot=info?.total||0;
    const stags=Object.entries(info?.statuses||{}).map(([s,c])=>
      `<span class="act-stag ${s}" onclick="event.stopPropagation();openType('${t}','${s}')">${SL[s]||s} ${c}</span>`
    ).join('');
    h+=`<div class="act-tcard tc-${t}" onclick="openType('${t}')">
      <div class="act-tcard-ic" style="background:${m.bg};color:${m.color}"><i class="fas ${m.icon}"></i></div>
      <div class="act-tcard-val">${num(tot)}</div>
      <div class="act-tcard-lbl">${m.lbl}</div>
      ${stags?`<div class="act-tcard-tags">${stags}</div>`:''}
    </div>`;
  });
  h+='</div>';
  g('apPanel').innerHTML=h;
}

let _tt='',_ts='',_tp=1;
async function openType(type,status){_tt=type;_ts=status||'';_tp=1;await loadTypeList();}
async function loadTypeList(p){
  if(p)_tp=p;
  const el=g('apPanel'),m=TM[_tt]||TM.borrow;
  el.innerHTML=`<div class="act-bc">
    <a onclick="renderTypeGrid()"><i class="fas fa-layer-group"></i> ${TH?'ตามประเภท':'By Type'}</a>
    <span class="act-bc-sep"><i class="fas fa-chevron-right"></i></span>
    <span class="act-bc-cur">${m.lbl}${_ts?' · '+(SL[_ts]||_ts):''}</span>
  </div><div class="act-ld"><i class="fas fa-circle-notch"></i></div>`;
  try{
    let url=`/v1/api/borrow.php?action=activity_type_detail&txn_type=${_tt}&page=${_tp}`;
    if(_ts)url+=`&status=${_ts}`;
    const d=await apiFetch(url);if(!d.success)throw new Error(d.error);
    const {items=[],total=0,pages=1,page=1}=d.data;
    let h=`<div class="act-bc">
      <a onclick="renderTypeGrid()"><i class="fas fa-layer-group"></i> ${TH?'ตามประเภท':'By Type'}</a>
      <span class="act-bc-sep"><i class="fas fa-chevron-right"></i></span>
      <span class="act-bc-cur">${m.lbl}</span>
    </div>
    <div class="act-fbar">
      <div class="act-fbar-ico" style="background:${m.bg};color:${m.color}"><i class="fas ${m.icon}"></i></div>
      <div><h4>${m.lbl}</h4><div class="act-fbar-count">${num(total)} ${TH?'รายการ':'items'}</div></div>
      <div class="act-fbtns">
        <button class="act-fbtn ${!_ts?'on':''}" onclick="openType('${_tt}')">${TH?'ทั้งหมด':'All'}</button>
        <button class="act-fbtn ${_ts==='completed'?'on':''}" onclick="openType('${_tt}','completed')">${TH?'สำเร็จ':'Done'}</button>
        <button class="act-fbtn ${_ts==='pending'?'on':''}" onclick="openType('${_tt}','pending')">${TH?'รอ':'Pending'}</button>
      </div>
    </div>`;
    if(!items.length){h+=`<div class="act-empty"><i class="fas fa-inbox"></i><p>${TH?'ไม่มีรายการ':'No items'}</p></div>`;}
    else{
      _txnRows=items;
      h+='<div class="act-list">';
      items.forEach((r,i)=>{
        const ft=r.from_name&&r.to_name?`${esc(r.from_name)} → ${esc(r.to_name)}`:esc(r.from_name||r.to_name||'');
        h+=`<div class="act-row" onclick="openTxnDetail(${i})">
          <div class="act-dot ${r.txn_type}"></div>
          <div class="act-rinfo">
            <div class="act-rname">${esc(r.chemical_name)}</div>
            <div class="act-rsub">
              ${r.barcode?`<span><i class="fas fa-barcode"></i>${esc(r.barcode)}</span>`:''}
              ${ft?`<span><i class="fas fa-user"></i>${ft}</span>`:''}
              ${r.notes?`<span><i class="fas fa-sticky-note"></i>${esc(r.notes).slice(0,40)}</span>`:''}
            </div>
          </div>
          <span class="act-rbadge ${r.status}">${SL[r.status]||r.status}</span>
          <span class="act-rqty">${num(r.quantity)} ${esc(r.unit||'')}</span>
          <span class="act-rdate">${fmtDate(r.created_at)}</span>
          <i class="fas fa-chevron-right" style="font-size:9px;color:#cbd5e1;flex-shrink:0"></i>
        </div>`;
      });
      h+='</div>';
      if(pages>1){
        h+='<div class="act-pager">';
        h+=`<button class="act-pbtn" onclick="loadTypeList(${page-1})" ${page<=1?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
        for(let i=Math.max(1,page-2);i<=Math.min(pages,page+2);i++)
          h+=`<button class="act-pbtn ${i===page?'on':''}" onclick="loadTypeList(${i})">${i}</button>`;
        h+=`<button class="act-pbtn" onclick="loadTypeList(${page+1})" ${page>=pages?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
        h+=`<span class="act-pinfo">${page}/${pages}</span></div>`;
      }
    }
    el.innerHTML=h;
  }catch(e){el.innerHTML+=`<div class="ci-alert ci-alert-danger">${esc(e.message)}</div>`;}
}

/* ── CHEMICAL LIST ── */
function renderChemList(){
  const list=D.by_chemical||[];
  if(!list.length){g('apPanel').innerHTML=`<div class="act-empty"><i class="fas fa-flask"></i><p>${TH?'ยังไม่มีธุรกรรม':'No data'}</p></div>`;return;}
  let h='<div class="act-clist">';
  list.forEach((r,i)=>{
    const rk=i<3?`r${i+1}`:'rn';
    h+=`<div class="act-crow" onclick="openChem(${r.chemical_id},'${esc(r.chemical_name).replace(/'/g,"\\'")}')">
      <div class="act-crank ${rk}">${i+1}</div>
      <div style="flex:1;min-width:0">
        <div class="act-cname">${esc(r.chemical_name)}</div>
        ${r.cas_number?`<div class="act-ccas">CAS: ${r.cas_number}</div>`:''}
      </div>
      <div class="act-cval">${num(r.txn_count)} ${TH?'ธุรกรรม':'txns'}</div>
      <i class="fas fa-chevron-right" style="font-size:10px;color:#cbd5e1"></i>
    </div>`;
  });
  h+='</div>';
  g('apPanel').innerHTML=h;
}

async function openChem(id,name){
  const el=g('apPanel');
  el.innerHTML=`<div class="act-bc">
    <a onclick="renderChemList()"><i class="fas fa-flask"></i> ${TH?'สารเคมี':'Chemical'}</a>
    <span class="act-bc-sep"><i class="fas fa-chevron-right"></i></span>
    <span class="act-bc-cur">${esc(name)}</span>
  </div><div class="act-ld"><i class="fas fa-circle-notch"></i></div>`;
  try{
    const d=await apiFetch(`/v1/api/borrow.php?action=activity_chem_lifecycle&chemical_id=${id}`);
    if(!d.success)throw new Error(d.error);
    const {chemical={},by_barcode={},total_txns=0}=d.data;
    const bcs=Object.keys(by_barcode);
    let h=`<div class="act-bc">
      <a onclick="renderChemList()"><i class="fas fa-flask"></i> ${TH?'สารเคมี':'Chemical'}</a>
      <span class="act-bc-sep"><i class="fas fa-chevron-right"></i></span>
      <span class="act-bc-cur">${esc(name)}</span>
    </div>
    <div class="act-chem-hdr">
      <div class="act-chem-ic"><i class="fas fa-flask"></i></div>
      <div>
        <div class="act-chem-nm">${esc(chemical.name||name)}</div>
        <div class="act-chem-meta">
          ${chemical.cas_number?`<span><i class="fas fa-fingerprint"></i> CAS: ${chemical.cas_number}</span>`:''}
          <span><i class="fas fa-exchange-alt"></i> ${num(total_txns)} ${TH?'ธุรกรรม':'txns'}</span>
          <span><i class="fas fa-vial"></i> ${bcs.length} ${TH?'ขวด':'bottles'}</span>
        </div>
      </div>
    </div>`;
    if(!bcs.length){h+=`<div class="act-empty"><i class="fas fa-history"></i><p>${TH?'ยังไม่มีประวัติ':'No history'}</p></div>`;}
    else{
      bcs.forEach((bc,bi)=>{
        const txns=by_barcode[bc],open=bi<2;
        const iid=`tlb${bi}`;
        h+=`<div class="act-tlgroup">
          <div class="act-tlhdr" onclick="var b=g('${iid}'),v=b.classList.toggle('hide');this.querySelector('.act-tlhdr-chev').classList.toggle('open',!v)">
            <i class="fas fa-barcode" style="color:#94a3b8;font-size:11px"></i>
            <span class="act-tlhdr-bc">${bc==='no-barcode'?(TH?'ไม่มี Barcode':'No Barcode'):bc}</span>
            <span class="act-tlhdr-cnt">${txns.length} ${TH?'รายการ':'items'}</span>
            <i class="fas fa-chevron-right act-tlhdr-chev ${open?'open':''}"></i>
          </div>
          <div class="act-tlbody ${open?'':'hide'}" id="${iid}">`;
        txns.forEach(t=>{
          const m=TM[t.txn_type]||TM.borrow;
          const ft=t.from_name&&t.to_name?`${esc(t.from_name)} → ${esc(t.to_name)}`:esc(t.from_name||t.to_name||'');
          h+=`<div class="act-tl">
            <div class="act-tl-node"><div class="act-tl-dot ${t.txn_type}"></div><div class="act-tl-line"></div></div>
            <div class="act-tl-body">
              <div class="act-tl-title"><span class="act-type-chip ${t.txn_type}">${m.lbl}</span>${num(t.quantity)} ${esc(t.unit||'')} <span class="act-rbadge ${t.status}">${SL[t.status]||t.status}</span></div>
              <div class="act-tl-meta">
                <span><i class="fas fa-clock"></i> ${fmtDate(t.created_at)}</span>
                ${ft?`<span><i class="fas fa-user"></i> ${ft}</span>`:''}
                ${t.notes?`<span><i class="fas fa-sticky-note"></i> ${esc(t.notes).slice(0,50)}</span>`:''}
              </div>
            </div>
          </div>`;
        });
        h+='</div></div>';
      });
    }
    el.innerHTML=h;
  }catch(e){el.innerHTML+=`<div class="ci-alert ci-alert-danger">${esc(e.message)}</div>`;}
}

/* ── CHART ── */
let _cm='month',_cid=0,_cy='',_cmo='',_chid={};

async function renderChart(){
  const el=g('apPanel');
  el.innerHTML='<div class="act-ld"><i class="fas fa-circle-notch"></i></div>';
  try{
    let url=`/v1/api/borrow.php?action=activity_chart&mode=${_cm}`;
    if(_cid)url+=`&chemical_id=${_cid}`;
    if(_cy)url+=`&year=${_cy}`;
    if(_cmo)url+=`&month=${_cmo}`;
    const d=await apiFetch(url);if(!d.success)throw new Error(d.error);
    buildChart(el,d.data);
  }catch(e){el.innerHTML=`<div class="ci-alert ci-alert-danger">${esc(e.message)}</div>`;}
}

function buildChart(el,data){
  window._lcd=data;
  const {chart=[],chemicals=[],years=[]}=data;
  const types=['borrow','use','transfer','return','dispose'];
  const periods={};
  chart.forEach(r=>{
    if(!periods[r.period])periods[r.period]={period:r.period,borrow:0,use:0,transfer:0,return:0,dispose:0};
    periods[r.period][r.txn_type]=(periods[r.period][r.txn_type]||0)+parseInt(r.cnt);
  });
  const pa=Object.values(periods).sort((a,b)=>a.period.localeCompare(b.period));
  const vis=types.filter(t=>!_chid[t]);
  const maxV=Math.max(...pa.map(p=>vis.reduce((s,t)=>s+(p[t]||0),0)),1);
  const mN=TH?['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.']:['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

  let h=`<div class="act-chart-tb">
    <div class="act-chmode">
      ${[['day',TH?'วัน':'Day'],['month',TH?'เดือน':'Month'],['year',TH?'ปี':'Year']].map(([k,l])=>`<button class="act-chmbtn ${_cm===k?'on':''}" onclick="_cm='${k}';renderChart()">${l}</button>`).join('')}
    </div>
    <div class="act-chfil">
      <select class="act-chsel" onchange="_cid=parseInt(this.value)||0;renderChart()">
        <option value="">${TH?'— ทุกสาร —':'— All —'}</option>
        ${chemicals.map(c=>`<option value="${c.id}" ${c.id==_cid?'selected':''}>${esc(c.name)}</option>`).join('')}
      </select>
      ${years.length&&_cm!=='year'?`<select class="act-chsel" onchange="_cy=this.value;renderChart()"><option value="">${TH?'— ทุกปี —':'— All —'}</option>${years.map(y=>`<option value="${y}" ${y==_cy?'selected':''}>${y}</option>`).join('')}</select>`:''}
      ${_cm==='day'?`<select class="act-chsel" onchange="_cmo=this.value;renderChart()"><option value="">${TH?'— ทุกเดือน —':'— All —'}</option>${Array.from({length:12},(_,i)=>{const v=`${_cy||new Date().getFullYear()}-${String(i+1).padStart(2,'0')}`;return`<option value="${v}" ${v===_cmo?'selected':''}>${mN[i+1]}</option>`;}).join('')}</select>`:''}
    </div>
  </div>`;

  h+=`<div class="act-leg">${types.map(t=>`<div class="act-legitem ${_chid[t]?'off':''}" onclick="_chid['${t}']=!_chid['${t}'];renderChart()"><div class="act-legdot" style="background:${CC[t]}"></div>${TM[t].lbl}</div>`).join('')}</div>`;

  if(!pa.length){h+=`<div class="act-empty"><i class="fas fa-chart-bar"></i><p>${TH?'ไม่มีข้อมูล':'No data'}</p></div>`;}
  else{
    const sw=800,sh=220,pd={t:12,r:16,b:32,l:40};
    const cw=sw-pd.l-pd.r,ch=sh-pd.t-pd.b;
    const bw=Math.min(38,Math.max(8,cw/pa.length-4));
    const gap=(cw-bw*pa.length)/(pa.length+1);
    h+=`<div class="act-chartwrap" id="apCW"><div class="act-charttip" id="apCT"></div>
      <svg class="act-chartsvg" viewBox="0 0 ${sw} ${sh}" preserveAspectRatio="xMidYMid meet">
      <g>`;
    for(let i=0;i<=4;i++){
      const gy=pd.t+(ch/4)*i,v=Math.round(maxV*(1-i/4));
      h+=`<line x1="${pd.l}" y1="${gy}" x2="${sw-pd.r}" y2="${gy}" class="gridl"/><text x="${pd.l-4}" y="${gy+3}" text-anchor="end" class="gridtxt">${num(v)}</text>`;
    }
    h+='</g><g>';
    pa.forEach((p,pi)=>{
      const bx=pd.l+gap+pi*(bw+gap);let sy=pd.t+ch;
      let lbl=p.period;
      if(_cm==='month'){const pts=p.period.split('-');lbl=mN[parseInt(pts[1])]||pts[1];}
      else if(_cm==='day'){lbl=p.period.split('-').pop().replace(/^0/,'');}
      h+=`<text x="${bx+bw/2}" y="${sh-pd.b+14}" text-anchor="middle" class="gridtxt">${lbl}</text>`;
      vis.forEach(t=>{
        const v=p[t]||0;if(!v)return;
        const bh=(v/maxV)*ch;sy-=bh;
        h+=`<rect class="act-chartbar" x="${bx}" y="${sy}" width="${bw}" height="${bh}" fill="${CC[t]}" rx="2" data-p="${p.period}" data-t="${t}" data-v="${v}" onmouseenter="showTip(this,event)" onmouseleave="hideTip()" onclick="drillP('${p.period}')"/>`;
      });
    });
    h+='</g></svg></div>';
  }
  h+='<div id="apDrill"></div>';
  el.innerHTML=h;
}

function showTip(bar,e){
  const tip=g('apCT'),wrap=g('apCW');if(!tip||!wrap)return;
  const p=bar.dataset.p;
  const rows=(window._lcd?.chart||[]).filter(r=>r.period===p).map(r=>`<div style="display:flex;align-items:center;gap:6px"><span style="width:8px;height:8px;border-radius:2px;background:${CC[r.txn_type]};flex-shrink:0"></span>${TM[r.txn_type]?.lbl||r.txn_type}<span style="margin-left:auto;font-weight:700">${num(r.cnt)}</span></div>`).join('');
  tip.innerHTML=`<div style="font-weight:700;border-bottom:1px solid rgba(255,255,255,.15);padding-bottom:4px;margin-bottom:4px">${p}</div>${rows}`;
  tip.classList.add('show');
  const wr=wrap.getBoundingClientRect(),tw=tip.offsetWidth||140;
  let lf=e.clientX-wr.left-tw/2,tp=e.clientY-wr.top-tip.offsetHeight-12;
  lf=Math.max(4,Math.min(lf,wr.width-tw-4));if(tp<0)tp=e.clientY-wr.top+12;
  tip.style.left=lf+'px';tip.style.top=tp+'px';
}
function hideTip(){const t=g('apCT');if(t)t.classList.remove('show');}

async function drillP(period){
  const area=g('apDrill');if(!area)return;
  area.innerHTML='<div class="act-ld"><i class="fas fa-circle-notch"></i></div>';
  try{
    let url=`/v1/api/borrow.php?action=activity_chart&mode=${_cm}&drill=${period}`;
    if(_cid)url+=`&chemical_id=${_cid}`;
    const d=await apiFetch(url);if(!d.success)throw new Error(d.error);
    const items=d.data.drill||[];
    let h=`<div class="act-drill-hdr">
      <div class="act-drill-title"><i class="fas fa-calendar-day" style="color:#4338ca"></i> ${period} <span style="font-size:11px;font-weight:500;color:#94a3b8;margin-left:4px">${items.length} ${TH?'รายการ':'items'}</span></div>
      <button onclick="g('apDrill').innerHTML=''" style="border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;width:28px;height:28px;cursor:pointer;color:#64748b;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-times" style="font-size:10px"></i></button>
    </div>`;
    if(!items.length){h+=`<div class="act-empty"><i class="fas fa-inbox"></i><p>${TH?'ไม่มีรายการ':'No items'}</p></div>`;}
    else{
      _txnRows=items;
      h+='<div class="act-list">';
      items.forEach((r,i)=>{
        const m=TM[r.txn_type]||TM.borrow;
        const ft=r.from_name&&r.to_name?`${esc(r.from_name)} → ${esc(r.to_name)}`:esc(r.from_name||r.to_name||'');
        h+=`<div class="act-row" onclick="openTxnDetail(${i})"><div class="act-dot ${r.txn_type}"></div>
          <div class="act-rinfo"><div class="act-rname"><span class="act-type-chip ${r.txn_type}">${m.lbl}</span> ${esc(r.chemical_name)}</div>
          <div class="act-rsub">${ft?`<span><i class="fas fa-user"></i>${ft}</span>`:''}</div></div>
          <span class="act-rbadge ${r.status}">${SL[r.status]||r.status}</span>
          <span class="act-rqty">${num(r.quantity)} ${esc(r.unit||'')}</span>
          <span class="act-rdate">${fmtDate(r.created_at)}</span>
          <i class="fas fa-chevron-right" style="font-size:9px;color:#cbd5e1;flex-shrink:0"></i>
        </div>`;
      });
      h+='</div>';
    }
    area.innerHTML=h;
    area.scrollIntoView({behavior:'smooth',block:'nearest'});
  }catch(e){area.innerHTML=`<div class="ci-alert ci-alert-danger">${esc(e.message)}</div>`;}
}
</script>
</body></html>
