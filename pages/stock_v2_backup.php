<?php
/**
 * Chemical Stock Management Page — Pro Edition (ขวดสารเคมีในคลัง)
 * 
 * Role-based views:
 *   admin       → Full CRUD, import/export, see ALL
 *   ceo         → Read-only, see ALL, export
 *   lab_manager → See own + team, manage own
 *   user        → See/manage own only
 *
 * Views: Table / Grid / Compact / Analytics
 */
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang    = I18n::getCurrentLang();
$role    = $user['role_name'];
$uid     = (int)$user['id'];
$isAdmin = $role === 'admin';
$isCeo   = $role === 'ceo';
$isLab   = $role === 'lab_manager';
$canEdit = in_array($role, ['admin','lab_manager','user']);
$canSeeAll = $isAdmin || $isCeo;
$userDisplayName = $user['full_name_th'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$userInitial = mb_substr(preg_replace('/^(นาย|นางสาว|นาง|ดร\.)\s*/u', '', $userDisplayName), 0, 1, 'UTF-8');
Layout::head($lang === 'th' ? 'คลังสารเคมี — ขวดสาร' : 'Chemical Stock');
?>
<style>
:root{--stk-r:14px;--stk-rs:10px;--stk-sh:0 1px 6px rgba(0,0,0,.06);--stk-shm:0 4px 20px rgba(0,0,0,.08);--sg:#16a34a;--sy:#d97706;--sr:#dc2626;--sb:#2563eb;--sp:#7c3aed;--st:#0d9488}

/* ── Hero Banner ── */
.stk-hero{background:linear-gradient(135deg,#065f46 0%,#0d9488 50%,#14b8a6 100%);border-radius:var(--stk-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.stk-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.stk-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.stk-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px;position:relative}
.stk-hero-info p{font-size:12px;opacity:.85;margin:0;position:relative}
.stk-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0}
.stk-hero-c{text-align:center;position:relative}
.stk-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.stk-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}

/* ── Stats Row ── */
.stk-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.stk-stat{background:#fff;border-radius:var(--stk-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--stk-sh);border:1px solid var(--border);transition:all .15s;cursor:pointer}
.stk-stat:hover{transform:translateY(-2px);box-shadow:var(--stk-shm)}
.stk-stat.af{border-color:var(--accent);background:#f0fdf4}
.stk-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.stk-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.stk-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Tabs ── */
.stk-tabs{display:inline-flex;background:#f1f5f9;border-radius:var(--stk-rs);padding:3px}
.stk-tab{padding:8px 20px;font-size:12px;font-weight:600;color:var(--c3);border-radius:8px;cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:6px}
.stk-tab:hover{color:var(--c1)}
.stk-tab.active{background:#fff;color:var(--accent);box-shadow:0 1px 4px rgba(0,0,0,.08)}
.stk-tab .bg{font-size:9px;padding:2px 7px;border-radius:10px;font-weight:700;background:#e2e8f0;color:var(--c3)}
.stk-tab.active .bg{background:var(--accent);color:#fff}

/* ── My Banner ── */
.stk-my{background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:var(--stk-r);padding:16px 20px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:16px}
.stk-my-av{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px}
.stk-my h3{font-size:14px;font-weight:700;margin:0}
.stk-my p{font-size:11px;opacity:.8;margin:2px 0 0}

/* ── Toolbar ── */
.stk-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}
.stk-search{flex:1;min-width:220px;position:relative}
.stk-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:var(--stk-rs);font-size:13px;background:#fff;color:var(--c1);transition:border .15s}
.stk-search input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,138,92,.1)}
.stk-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:13px}
.stk-btn{padding:8px 16px;border:none;border-radius:var(--stk-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap}
.stk-btn-p{background:var(--accent);color:#fff}.stk-btn-p:hover{filter:brightness(1.08)}
.stk-btn-o{background:#fff;color:var(--accent);border:1.5px solid var(--accent)}.stk-btn-o:hover{background:var(--accent);color:#fff}
.stk-btn-d{background:#dc2626;color:#fff}.stk-btn-d:hover{background:#b91c1c}
.stk-btn-g{background:transparent;color:var(--c3);border:1.5px solid var(--border)}.stk-btn-g:hover{border-color:var(--accent);color:var(--accent)}
.stk-btn-s{padding:5px 10px;font-size:11px}

/* ── View Switcher ── */
.stk-vw{display:flex;border:1.5px solid var(--border);border-radius:var(--stk-rs);overflow:hidden}
.stk-vw button{padding:7px 11px;border:none;background:#fff;color:var(--c3);cursor:pointer;font-size:12px;transition:all .12s;display:flex;align-items:center;gap:4px}
.stk-vw button+button{border-left:1px solid var(--border)}
.stk-vw button.active{background:var(--accent);color:#fff}
.stk-vw button:hover:not(.active){background:#f8fafc}

/* ── Filter Panel ── */
.stk-fp{max-height:0;overflow:hidden;transition:max-height .25s ease,margin .25s ease,padding .25s ease;background:#fff;border:1.5px solid transparent;border-radius:var(--stk-r);margin-bottom:0}
.stk-fp.show{max-height:300px;border-color:var(--border);padding:16px;margin-bottom:14px;box-shadow:var(--stk-sh)}
.stk-fg2{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
.stk-fl label{font-size:10px;font-weight:700;color:var(--c3);display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px}
.stk-fl select{width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff;color:var(--c1)}
.stk-fl select:focus{outline:none;border-color:var(--accent)}
.stk-fa{display:flex;gap:8px;margin-top:10px;justify-content:flex-end}

/* ── Table View ── */
.stk-tw{overflow-x:auto;border-radius:var(--stk-r);border:1px solid var(--border);background:#fff;box-shadow:var(--stk-sh)}
.stk-t{width:100%;border-collapse:collapse;font-size:12px}
.stk-t th{background:#f8fafc;padding:10px 12px;text-align:left;font-weight:700;color:var(--c3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);white-space:nowrap;cursor:pointer;user-select:none;transition:color .12s;position:sticky;top:0;z-index:1}
.stk-t th:hover{color:var(--accent)}
.stk-t th.s{color:var(--accent)}
.stk-t th .si{margin-left:3px;font-size:8px;opacity:.4}
.stk-t th.s .si{opacity:1}
.stk-t td{padding:10px 12px;border-bottom:1px solid #f1f5f9;color:var(--c1);vertical-align:middle}
.stk-t tbody tr{transition:background .1s;cursor:pointer}
.stk-t tbody tr:hover{background:#f0fdf4}
.stk-t tbody tr.me{background:#eff6ff}
.stk-t tbody tr.me:hover{background:#dbeafe}

/* ── Grid/Card View ── */
.stk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.stk-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--stk-r);padding:16px;transition:all .18s;cursor:pointer;position:relative;overflow:hidden}
.stk-card:hover{border-color:var(--accent);box-shadow:var(--stk-shm);transform:translateY(-2px)}
.stk-card.me{border-left:3px solid var(--sb)}
.stk-card-hd{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px}
.stk-card-ic{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.stk-card-nm{font-size:13px;font-weight:700;color:var(--c1);line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.stk-card-cd{font-size:10px;color:var(--c3);font-family:'Courier New',monospace;margin-top:2px}
.stk-card-tg{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px}
.stk-card-tag{font-size:9px;padding:2px 7px;border-radius:6px;font-weight:600}
.stk-card-bar{height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-bottom:6px}
.stk-card-bf{height:100%;border-radius:3px;transition:width .3s}
.stk-card-ft{display:flex;justify-content:space-between;align-items:center;font-size:10px;color:var(--c3)}
.stk-av{width:20px;height:20px;border-radius:50%;background:var(--accent);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:8px;font-weight:700;margin-right:4px;vertical-align:middle}

/* ── Compact View ── */
.stk-compact{display:flex;flex-direction:column;gap:4px}
.stk-cr{display:flex;align-items:center;gap:10px;padding:8px 14px;background:#fff;border-radius:8px;border:1px solid var(--border);cursor:pointer;transition:all .1s;font-size:12px}
.stk-cr:hover{background:#f0fdf4;border-color:var(--accent)}
.stk-cr.me{border-left:3px solid var(--sb)}
.stk-cn{flex:1;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.stk-cc{color:var(--c3);font-size:11px;width:90px;flex-shrink:0}
.stk-cb{width:50px;height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden;flex-shrink:0}
.stk-cb div{height:100%;border-radius:2px}
.stk-cp{font-weight:700;font-size:11px;width:35px;text-align:right;flex-shrink:0}
.stk-co{font-size:10px;color:var(--c3);width:80px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ── Analytics View ── */
.stk-an{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}
.stk-ac{background:#fff;border-radius:var(--stk-r);border:1px solid var(--border);padding:18px;box-shadow:var(--stk-sh)}
.stk-at{font-size:12px;font-weight:700;color:var(--c1);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.stk-at i{color:var(--accent)}
.stk-bc{display:flex;flex-direction:column;gap:6px}
.stk-br{display:flex;align-items:center;gap:8px;font-size:11px}
.stk-bl{width:100px;text-align:right;color:var(--c3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0}
.stk-bt{flex:1;height:18px;background:#f1f5f9;border-radius:4px;overflow:hidden;position:relative}
.stk-bf{height:100%;border-radius:4px;display:flex;align-items:center;padding-left:6px;font-size:9px;font-weight:700;color:#fff;transition:width .4s}
.stk-bv{font-weight:700;color:var(--c1);width:40px;flex-shrink:0}
.stk-dn{display:flex;align-items:center;gap:20px;justify-content:center}
.stk-dl2{display:flex;flex-direction:column;gap:6px}
.stk-di{display:flex;align-items:center;gap:6px;font-size:11px}
.stk-dd{width:10px;height:10px;border-radius:3px;flex-shrink:0}

/* ── Badges ── */
.stk-badge{font-size:9px;padding:3px 8px;border-radius:6px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;display:inline-block}
.stk-badge-active{background:#dcfce7;color:#15803d}
.stk-badge-low{background:#fef9c3;color:#a16207}
.stk-badge-empty{background:#fee2e2;color:#dc2626}
.stk-badge-expired{background:#fce7f3;color:#be185d}
.stk-badge-disposed{background:#f1f5f9;color:#64748b}
.bar-ok{background:linear-gradient(90deg,#22c55e,#16a34a)}
.bar-mid{background:linear-gradient(90deg,#eab308,#f59e0b)}
.bar-low{background:linear-gradient(90deg,#ef4444,#dc2626)}

/* ── Pagination ── */
.stk-pager{display:flex;align-items:center;justify-content:center;gap:3px;margin-top:14px;flex-wrap:wrap}
.stk-pager button{width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--c1);cursor:pointer;font-size:11px;font-weight:600;transition:all .12s;display:flex;align-items:center;justify-content:center}
.stk-pager button:hover:not(:disabled){border-color:var(--accent);color:var(--accent)}
.stk-pager button.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.stk-pager button:disabled{opacity:.3;cursor:default}
.stk-pager-info{font-size:11px;color:var(--c3);margin:0 8px}

/* ── Modal ── */
.stk-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .2s}
.stk-ov.show{opacity:1;visibility:visible}
.stk-md{background:#fff;border-radius:18px;width:94%;max-width:620px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.92) translateY(10px);transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
.stk-ov.show .stk-md{transform:scale(1) translateY(0)}
.stk-md-sm{max-width:420px}
.stk-mh{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between}
.stk-mh h3{font-size:16px;font-weight:700;color:var(--c1);display:flex;align-items:center;gap:8px}
.stk-mx{width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:14px;color:var(--c3);display:flex;align-items:center;justify-content:center;transition:all .12s}
.stk-mx:hover{background:#fee2e2;color:#dc2626}
.stk-mb{padding:16px 24px 24px}
.stk-dg{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.stk-dlb{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
.stk-dvl{font-size:13px;color:var(--c1);font-weight:500}
.stk-df{grid-column:1/-1}
.stk-da{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}

/* ── Form ── */
.stk-fg{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.stk-ff{grid-column:1/-1}
.stk-fg label{font-size:11px;font-weight:600;color:var(--c3);display:block;margin-bottom:3px}
.stk-fg input,.stk-fg select{width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:#fff;color:var(--c1);box-sizing:border-box}
.stk-fg input:focus,.stk-fg select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,138,92,.1)}

/* ── Import ── */
.stk-drop{border:2px dashed var(--border);border-radius:12px;padding:28px;text-align:center;transition:all .15s;cursor:pointer}
.stk-drop:hover,.stk-drop.drag{border-color:var(--accent);background:#f0fdf4}
.stk-drop i{font-size:28px;color:var(--accent);margin-bottom:6px}
.stk-drop p{font-size:12px;color:var(--c3)}

/* ── Use Modal ── */
.stk-uq{width:120px;text-align:center;font-size:22px;font-weight:800;padding:14px;border:2px solid var(--border);border-radius:var(--stk-rs)}
.stk-uq:focus{border-color:var(--accent);outline:none}

/* ── Toast ── */
.stk-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);background:#1a1a2e;color:#fff;padding:12px 24px;border-radius:var(--stk-rs);font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;z-index:99999;opacity:0;transition:all .3s}
.stk-toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.stk-toast.ok{background:#0d6832}.stk-toast.err{background:#c62828}

/* ── Empty ── */
.stk-empty{text-align:center;padding:48px 24px;color:var(--c3)}
.stk-empty i{font-size:48px;margin-bottom:12px;opacity:.25}
.stk-empty p{font-size:14px}

/* ── Responsive ── */
@media(max-width:768px){
    .stk-hero{flex-direction:column;text-align:center;gap:12px;padding:20px}
    .stk-hero-meta{margin-left:0}
    .stk-stats{grid-template-columns:repeat(2,1fr)}
    .stk-toolbar{flex-direction:column;align-items:stretch}
    .stk-search{min-width:100%}
    .stk-grid{grid-template-columns:1fr}
    .stk-dg,.stk-fg{grid-template-columns:1fr}
    .stk-an{grid-template-columns:1fr}
    .stk-tabs{overflow-x:auto}
    .stk-cc,.stk-co{display:none}
    .stk-dn{flex-direction:column}
}
</style>
<body>
<?php Layout::sidebar('stock'); Layout::beginContent(); ?>

<!-- ═══ Hero Banner ═══ -->
<div class="stk-hero">
    <div class="stk-hero-ic"><i class="fas fa-flask"></i></div>
    <div class="stk-hero-info">
        <h2><?php echo $lang==='th'?'คลังขวดสารเคมี':'Chemical Bottle Stock'; ?></h2>
        <p><?php echo $lang==='th'
            ? ($canSeeAll?'ภาพรวมข้อมูลขวดสารเคมีทั้งหมดในระบบ':($isLab?'จัดการสารเคมีของทีมคุณ':'จัดการขวดสารเคมีของคุณ'))
            : ($canSeeAll?'Overview of all chemical bottles in the system':($isLab?'Manage your team\'s chemicals':'Manage your chemical bottles'));
        ?></p>
    </div>
    <div class="stk-hero-meta">
        <div class="stk-hero-c"><div class="v" id="heroTotal">—</div><div class="lb"><?php echo $lang==='th'?'ขวดทั้งหมด':'Total'; ?></div></div>
        <div class="stk-hero-c"><div class="v" id="heroMy">—</div><div class="lb"><?php echo $lang==='th'?'ของฉัน':'My Stock'; ?></div></div>
    </div>
</div>

<!-- ═══ Stats Row ═══ -->
<div class="stk-stats" id="statsRow"></div>

<!-- ═══ Tabs + View ═══ -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px">
    <div class="stk-tabs" id="mainTabs">
        <button class="stk-tab active" data-tab="all" onclick="switchTab('all')">
            <i class="fas fa-globe"></i> <?php echo $lang==='th'?($canSeeAll?'ทั้งหมด':'ที่เข้าถึงได้'):($canSeeAll?'All':'Accessible'); ?>
            <span class="bg" id="badgeAll">0</span>
        </button>
        <button class="stk-tab" data-tab="my" onclick="switchTab('my')">
            <i class="fas fa-user"></i> <?php echo $lang==='th'?'ของฉัน':'My Stock'; ?>
            <span class="bg" id="badgeMy">0</span>
        </button>
    </div>
    <div class="stk-vw" id="viewSw">
        <button class="active" data-view="table" onclick="setView('table')" title="Table"><i class="fas fa-th-list"></i></button>
        <button data-view="grid" onclick="setView('grid')" title="Grid"><i class="fas fa-th-large"></i></button>
        <button data-view="compact" onclick="setView('compact')" title="Compact"><i class="fas fa-bars"></i></button>
        <button data-view="analytics" onclick="setView('analytics')" title="Analytics"><i class="fas fa-chart-pie"></i></button>
    </div>
</div>

<!-- ═══ My Banner ═══ -->
<div class="stk-my" id="myBanner" style="display:none">
    <div class="stk-my-av"><?php echo htmlspecialchars($userInitial); ?></div>
    <div style="flex:1">
        <h3><?php echo $lang==='th'?'สารเคมีของฉัน':'My Chemical Stock'; ?> — <?php echo htmlspecialchars($userDisplayName); ?></h3>
        <p><?php echo $lang==='th'?'แสดงเฉพาะขวดที่คุณเป็นผู้เพิ่มหรือรับผิดชอบ':'Showing bottles you added or are responsible for'; ?></p>
    </div>
    <div style="display:flex;gap:14px">
        <div class="stk-hero-c"><div class="v" id="myStatTotal">—</div><div class="lb"><?php echo $lang==='th'?'ขวด':'Bottles'; ?></div></div>
        <div class="stk-hero-c"><div class="v" id="myStatActive" style="color:#4ade80">—</div><div class="lb"><?php echo $lang==='th'?'ปกติ':'Active'; ?></div></div>
        <div class="stk-hero-c"><div class="v" id="myStatLow" style="color:#fbbf24">—</div><div class="lb"><?php echo $lang==='th'?'เหลือน้อย':'Low'; ?></div></div>
    </div>
</div>

<!-- ═══ Toolbar ═══ -->
<div class="stk-toolbar" id="toolbar">
    <div class="stk-search">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="<?php echo $lang==='th'?'ค้นหา: รหัสขวด, ชื่อสาร, CAS, ผู้เพิ่ม...':'Search: bottle code, chemical, CAS, owner...'; ?>">
    </div>
    <button class="stk-btn stk-btn-g" id="filterToggle" onclick="toggleFilter()">
        <i class="fas fa-sliders-h"></i> <?php echo $lang==='th'?'ตัวกรอง':'Filters'; ?>
    </button>
    <?php if ($canEdit): ?>
    <button class="stk-btn stk-btn-p" onclick="openAddModal()"><i class="fas fa-plus"></i> <?php echo $lang==='th'?'เพิ่มขวด':'Add Bottle'; ?></button>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <button class="stk-btn stk-btn-g" onclick="openImportModal()"><i class="fas fa-file-import"></i> Import</button>
    <button class="stk-btn stk-btn-g" onclick="relinkOwners()" title="<?php echo $lang==='th'?'ซ่อมแซมการเชื่อมโยงเจ้าของ':'Re-link owner mapping'; ?>"><i class="fas fa-link"></i> Relink</button>
    <?php endif; ?>
    <button class="stk-btn stk-btn-g" onclick="doExport()"><i class="fas fa-file-csv"></i> Export</button>
</div>

<!-- ═══ Filter Panel ═══ -->
<div class="stk-fp" id="filterPanel">
    <div class="stk-fg2">
        <div class="stk-fl">
            <label><?php echo $lang==='th'?'สถานะ':'Status'; ?></label>
            <select id="fStatus" onchange="loadData(1)">
                <option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option>
                <option value="active"><?php echo $lang==='th'?'ปกติ':'Active'; ?></option>
                <option value="low"><?php echo $lang==='th'?'เหลือน้อย':'Low'; ?></option>
                <option value="empty"><?php echo $lang==='th'?'หมด':'Empty'; ?></option>
                <option value="expired"><?php echo $lang==='th'?'หมดอายุ':'Expired'; ?></option>
            </select>
        </div>
        <div class="stk-fl">
            <label><?php echo $lang==='th'?'หน่วย':'Unit'; ?></label>
            <select id="fUnit" onchange="loadData(1)"><option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option></select>
        </div>
        <?php if ($canSeeAll || $isLab): ?>
        <div class="stk-fl">
            <label><?php echo $lang==='th'?'ผู้เพิ่มขวด':'Owner'; ?></label>
            <select id="fOwner" onchange="loadData(1)"><option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option></select>
        </div>
        <?php endif; ?>
        <div class="stk-fl">
            <label><?php echo $lang==='th'?'เรียงตาม':'Sort By'; ?></label>
            <select id="fSort" onchange="loadData(1)">
                <option value="added_at"><?php echo $lang==='th'?'เวลาเพิ่ม':'Date Added'; ?></option>
                <option value="chemical_name"><?php echo $lang==='th'?'ชื่อสาร':'Chemical Name'; ?></option>
                <option value="bottle_code"><?php echo $lang==='th'?'รหัสขวด':'Bottle Code'; ?></option>
                <option value="remaining_pct"><?php echo $lang==='th'?'% คงเหลือ':'% Remaining'; ?></option>
                <option value="package_size"><?php echo $lang==='th'?'ขนาดบรรจุ':'Package Size'; ?></option>
            </select>
        </div>
    </div>
    <div class="stk-fa">
        <button class="stk-btn stk-btn-g stk-btn-s" onclick="clearFilters()"><i class="fas fa-undo"></i> <?php echo $lang==='th'?'ล้าง':'Clear'; ?></button>
    </div>
</div>

<!-- ═══ Data Area ═══ -->
<div id="dataArea"></div>
<div class="stk-pager" id="pagerArea"></div>

<!-- ═══ Detail Modal ═══ -->
<div class="stk-ov" id="detailOv" onclick="if(event.target===this)closeDetail()">
    <div class="stk-md">
        <div class="stk-mh"><h3><i class="fas fa-flask" style="color:var(--accent)"></i> <span id="detailTitle"></span></h3><button class="stk-mx" onclick="closeDetail()"><i class="fas fa-times"></i></button></div>
        <div class="stk-mb" id="detailBody"></div>
    </div>
</div>

<!-- ═══ Add/Edit Modal ═══ -->
<div class="stk-ov" id="addOv" onclick="if(event.target===this)closeAdd()">
    <div class="stk-md">
        <div class="stk-mh"><h3><i class="fas fa-plus-circle" style="color:var(--accent)"></i> <span id="addTitle"><?php echo $lang==='th'?'เพิ่มขวดสารเคมี':'Add Chemical Bottle'; ?></span></h3><button class="stk-mx" onclick="closeAdd()"><i class="fas fa-times"></i></button></div>
        <div class="stk-mb">
            <form id="addForm" onsubmit="return submitAdd(event)">
                <input type="hidden" id="editId" value="">
                <div class="stk-fg">
                    <div><label><?php echo $lang==='th'?'รหัสขวด *':'Bottle Code *'; ?></label><input type="text" id="fBottleCode" required placeholder="e.g. F01121A5800001"></div>
                    <div><label>CAS / Catalogue No.</label><input type="text" id="fCasNo" placeholder="e.g. 64-17-5"></div>
                    <div class="stk-ff"><label><?php echo $lang==='th'?'ชื่อสารเคมี *':'Chemical Name *'; ?></label><input type="text" id="fChemName" required placeholder="e.g. Ethyl alcohol"></div>
                    <div class="stk-ff"><label><?php echo $lang==='th'?'เกรด':'Grade'; ?></label><input type="text" id="fGrade" placeholder="e.g. AR grade, RPE"></div>
                    <div><label><?php echo $lang==='th'?'ขนาดบรรจุ':'Package Size'; ?></label><input type="number" id="fPkgSize" step="0.01" min="0" placeholder="2500"></div>
                    <div><label><?php echo $lang==='th'?'ปริมาณคงเหลือ':'Remaining Qty'; ?></label><input type="number" id="fRemQty" step="0.01" min="0" placeholder="1500"></div>
                    <div><label><?php echo $lang==='th'?'หน่วย':'Unit'; ?></label>
                        <select id="fUnitSel"><option value="">—</option>
                            <option value="กรัม">กรัม</option><option value="มิลลิกรัม">มิลลิกรัม</option><option value="ไมโครกรัม">ไมโครกรัม</option><option value="กิโลกรัม">กิโลกรัม</option>
                            <option value="มิลลิลิตร">มิลลิลิตร</option><option value="ไมโครลิตร">ไมโครลิตร</option><option value="ลิตร">ลิตร</option>
                            <option value="Packs">Packs</option><option value="Units">Units</option><option value="Vials">Vials</option>
                        </select>
                    </div>
                    <div><label><?php echo $lang==='th'?'สถานที่จัดเก็บ':'Storage Location'; ?></label><input type="text" id="fStorage" placeholder="e.g. Center Store"></div>
                </div>
                <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end">
                    <button type="button" class="stk-btn stk-btn-g" onclick="closeAdd()"><?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></button>
                    <button type="submit" class="stk-btn stk-btn-p" id="addSubmitBtn"><i class="fas fa-save"></i> <?php echo $lang==='th'?'บันทึก':'Save'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ Use Modal ═══ -->
<div class="stk-ov" id="useOv" onclick="if(event.target===this)closeUse()">
    <div class="stk-md stk-md-sm">
        <div class="stk-mh"><h3><i class="fas fa-vial" style="color:var(--sy)"></i> <span id="useTitle"><?php echo $lang==='th'?'บันทึกการใช้งาน':'Record Usage'; ?></span></h3><button class="stk-mx" onclick="closeUse()"><i class="fas fa-times"></i></button></div>
        <div class="stk-mb" style="text-align:center">
            <p id="useChemName" style="font-weight:700;margin-bottom:4px"></p>
            <p id="useCurrentQty" style="font-size:12px;color:var(--c3);margin-bottom:16px"></p>
            <input type="hidden" id="useStockId">
            <input type="number" class="stk-uq" id="useAmount" step="0.01" min="0.01" placeholder="0.00">
            <p id="useUnitLabel" style="font-size:12px;color:var(--c3);margin-top:6px"></p>
            <div style="display:flex;gap:8px;margin-top:16px;justify-content:center">
                <button class="stk-btn stk-btn-g" onclick="closeUse()"><?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></button>
                <button class="stk-btn stk-btn-p" onclick="submitUse()"><i class="fas fa-check"></i> <?php echo $lang==='th'?'บันทึก':'Confirm'; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Import Modal ═══ -->
<div class="stk-ov" id="importOv" onclick="if(event.target===this)closeImport()">
    <div class="stk-md">
        <div class="stk-mh"><h3><i class="fas fa-file-import" style="color:var(--sy)"></i> Import CSV</h3><button class="stk-mx" onclick="closeImport()"><i class="fas fa-times"></i></button></div>
        <div class="stk-mb">
            <div class="stk-drop" id="dropZone" onclick="document.getElementById('importFile').click()">
                <input type="file" id="importFile" accept=".csv" style="display:none" onchange="handleImportFile(this)">
                <i class="fas fa-cloud-upload-alt"></i>
                <p><?php echo $lang==='th'?'คลิกหรือลากไฟล์ CSV มาวาง':'Click or drag a CSV file here'; ?></p>
                <p style="font-size:10px;color:var(--c3);margin-top:4px"><?php echo $lang==='th'?'รูปแบบ: รหัสขวด, ชื่อสาร, CAS, เกรด, ขนาดบรรจุ, ปริมาณ, หน่วย, ผู้เพิ่ม, เวลา':'Bottle Code, Name, CAS, Grade, Size, Qty, Unit, Owner, Date'; ?></p>
            </div>
            <div id="importResult" style="display:none;margin-top:14px"></div>
        </div>
    </div>
</div>

<div class="stk-toast" id="stkToast"></div>
<?php Layout::endContent(); ?>

<script>
/* ═════════════════════════════════════════
   CONFIG & STATE
   ═════════════════════════════════════════ */
const L='<?php echo $lang; ?>';
const ROLE='<?php echo $role; ?>';
const UID=<?php echo (int)$uid; ?>;
const IS_ADMIN=<?php echo $isAdmin?'true':'false'; ?>;
const CAN_EDIT=<?php echo $canEdit?'true':'false'; ?>;
const CAN_SEE_ALL=<?php echo $canSeeAll?'true':'false'; ?>;
const USER_NAME='<?php echo addslashes($userDisplayName); ?>';

let VIEW='table',TAB='all',PAGE=1,SORT='added_at',DIR='DESC';
let DATA=[],STATS=null,SF='';
const T=(th,en)=>L==='th'?th:en;

/* ═════════════════════════════════════════
   INIT
   ═════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded',()=>{
    loadStats();loadFilters();loadData(1);setupSearch();setupDrop();
});
function setupSearch(){let t;document.getElementById('searchInput').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>loadData(1),300)})}

/* ═════════════════════════════════════════
   STATS
   ═════════════════════════════════════════ */
async function loadStats(){
    try{
        const d=await apiFetch('/v1/api/stock.php?action=stats');
        if(!d.success)return;
        STATS=d.data;const s=d.data;
        document.getElementById('heroTotal').textContent=num(s.total);
        document.getElementById('heroMy').textContent=num(s.my_total||0);
        document.getElementById('badgeAll').textContent=num(s.total);
        document.getElementById('badgeMy').textContent=num(s.my_total||0);
        // My Banner mini stats
        const mst=document.getElementById('myStatTotal');if(mst)mst.textContent=num(s.my_total||0);
        const msa=document.getElementById('myStatActive');if(msa)msa.textContent=num(s.my_active||0);
        const msl=document.getElementById('myStatLow');if(msl)msl.textContent=num(s.my_low||0);
        const cards=[
            {k:'active',ic:'fas fa-check-circle',bg:'#dcfce7',fg:'#15803d',v:s.active,l:T('ปกติ','Active')},
            {k:'low',ic:'fas fa-exclamation-triangle',bg:'#fef9c3',fg:'#a16207',v:s.low,l:T('เหลือน้อย','Low')},
            {k:'empty',ic:'fas fa-times-circle',bg:'#fee2e2',fg:'#dc2626',v:s.empty||0,l:T('หมด','Empty')},
            {k:'',ic:'fas fa-atom',bg:'#dbeafe',fg:'#2563eb',v:s.unique_chemicals,l:T('ชนิดสาร','Chemicals')},
            {k:'',ic:'fas fa-users',bg:'#f3e8ff',fg:'#7c3aed',v:s.unique_owners,l:T('ผู้ดูแล','Owners')},
        ];
        document.getElementById('statsRow').innerHTML=cards.map(c=>`
            <div class="stk-stat${SF===c.k&&c.k?' af':''}" ${c.k?`onclick="quickFilter('${c.k}')"`:''}>
                <div class="stk-si" style="background:${c.bg};color:${c.fg}"><i class="${c.ic}"></i></div>
                <div><div class="stk-sv">${num(c.v)}</div><div class="stk-sl">${c.l}</div></div>
            </div>`).join('');
    }catch(e){console.error(e)}
}
function quickFilter(st){
    const sel=document.getElementById('fStatus');
    if(SF===st){SF='';sel.value=''}else{SF=st;sel.value=st}
    loadData(1);
    document.querySelectorAll('.stk-stat').forEach(el=>{
        const oc=el.getAttribute('onclick')||'';
        const m=oc.match(/quickFilter\('(\w+)'\)/);
        el.classList.toggle('af',!!m&&m[1]===SF);
    });
}

/* ═════════════════════════════════════════
   FILTERS
   ═════════════════════════════════════════ */
async function loadFilters(){
    try{
        const [u,o]=await Promise.all([apiFetch('/v1/api/stock.php?action=units'),apiFetch('/v1/api/stock.php?action=owners')]);
        if(u.success){const sel=document.getElementById('fUnit');u.data.forEach(x=>{const op=document.createElement('option');op.value=x.unit;op.textContent=x.unit;sel.appendChild(op)})}
        if(o.success&&document.getElementById('fOwner')){const sel=document.getElementById('fOwner');o.data.forEach(x=>{const op=document.createElement('option');op.value=x.owner_user_id;op.textContent=(x.owner_name||x.username)+' ('+x.cnt+')';sel.appendChild(op)})}
    }catch(e){}
}

/* ═════════════════════════════════════════
   LOAD DATA
   ═════════════════════════════════════════ */
async function loadData(page){
    PAGE=page||1;
    const search=document.getElementById('searchInput').value.trim();
    const status=document.getElementById('fStatus').value;
    const unit=document.getElementById('fUnit').value;
    const owner=document.getElementById('fOwner')?.value||'';
    const sort=document.getElementById('fSort')?.value||SORT;
    SORT=sort;
    let url='/v1/api/stock.php?action='+(TAB==='my'?'my':'list');
    url+='&page='+PAGE+'&limit=25&search='+encodeURIComponent(search);
    url+='&sort='+sort+'&dir='+DIR;
    if(status)url+='&status='+status;
    if(unit)url+='&unit='+encodeURIComponent(unit);
    if(owner&&TAB!=='my')url+='&owner_id='+owner;
    const area=document.getElementById('dataArea');
    area.innerHTML='<div style="text-align:center;padding:40px;color:var(--c3)"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    try{
        const d=await apiFetch(url);
        if(!d.success)throw new Error(d.error);
        DATA=d.data.items;
        renderView();
        renderPager(d.data.pagination);
    }catch(e){area.innerHTML='<div class="stk-empty"><i class="fas fa-exclamation-circle"></i><p>'+esc(e.message)+'</p></div>'}
}

/* ═════════════════════════════════════════
   RENDER DISPATCHER
   ═════════════════════════════════════════ */
function renderView(){
    const area=document.getElementById('dataArea');
    if(!DATA||!DATA.length){area.innerHTML=`<div class="stk-empty"><i class="fas fa-flask"></i><p>${T('ไม่พบข้อมูลขวดสารเคมี','No chemical bottles found')}</p></div>`;return}
    switch(VIEW){
        case 'table':renderTable(area);break;
        case 'grid':renderGrid(area);break;
        case 'compact':renderCompact(area);break;
        case 'analytics':renderAnalytics(area);break;
        default:renderTable(area);
    }
}

/* ═════════════════════════════════════════
   TABLE VIEW
   ═════════════════════════════════════════ */
function renderTable(area){
    const cols=[
        {k:'bottle_code',l:T('รหัสขวด','Code')},
        {k:'chemical_name',l:T('ชื่อสารเคมี','Chemical')},
        {k:'cas_no',l:'CAS No.'},
        {k:'grade',l:T('เกรด','Grade')},
        {k:'remaining_pct',l:'%'},
        {k:'remaining_qty',l:T('คงเหลือ','Remain')},
        {k:'unit',l:T('หน่วย','Unit')},
        {k:'owner_name',l:T('ผู้เพิ่ม','Owner')},
        {k:'status',l:T('สถานะ','Status')},
        {k:'_a',l:''}
    ];
    let h='<div class="stk-tw"><table class="stk-t"><thead><tr>';
    cols.forEach(c=>{
        if(c.k==='_a'){h+='<th style="width:50px"></th>';return}
        const s=SORT===c.k;
        h+=`<th class="${s?'s':''}" onclick="sortBy('${c.k}')">${c.l} <i class="fas fa-sort${s?(DIR==='ASC'?'-up':'-down'):''} si"></i></th>`;
    });
    h+='</tr></thead><tbody>';
    DATA.forEach(r=>{
        const p=pct(r),bc=barCls(p),mine=isMine(r);
        h+=`<tr class="${mine?'me':''}" onclick="openDetail(${r.id})">
            <td><code style="font-size:10px;background:#f1f5f9;padding:2px 6px;border-radius:4px">${esc(r.bottle_code)}</code></td>
            <td><div style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.chemical_name)}">${esc(r.chemical_name)}</div></td>
            <td style="font-size:11px;color:var(--c3)">${esc(r.cas_no||'—')}</td>
            <td style="font-size:11px;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.grade||'')}">${esc(shortGrade(r.grade)||'—')}</td>
            <td><div style="display:flex;align-items:center;gap:5px">
                <div style="width:36px;height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden"><div style="height:100%;width:${Math.min(p,100)}%;border-radius:2px" class="${bc}"></div></div>
                <span style="font-size:10px;font-weight:700;color:${pctColor(p)}">${Math.round(p)}%</span>
            </div></td>
            <td style="font-weight:600;font-size:11px">${numFmt(r.remaining_qty)}/${numFmt(r.package_size)}</td>
            <td style="font-size:11px">${esc(r.unit||'—')}</td>
            <td><div style="display:flex;align-items:center;gap:4px">
                <span class="stk-av">${esc(initial(r))}</span>
                <span style="font-size:10px;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(shortName(r.owner_name))}</span>
            </div></td>
            <td><span class="stk-badge stk-badge-${r.status}">${statusLbl(r.status)}</span></td>
            <td onclick="event.stopPropagation()">
                ${canManage(r)?`<button class="stk-btn stk-btn-g stk-btn-s" onclick="openUse(${r.id})" title="${T('บันทึกการใช้','Use')}"><i class="fas fa-vial"></i></button>`:''}
            </td>
        </tr>`;
    });
    h+='</tbody></table></div>';
    area.innerHTML=h;
}

/* ═════════════════════════════════════════
   GRID VIEW
   ═════════════════════════════════════════ */
function renderGrid(area){
    let h='<div class="stk-grid">';
    DATA.forEach(r=>{
        const p=pct(r),bc=barCls(p),mine=isMine(r);
        const ibg=p>50?'#dcfce7':p>15?'#fef9c3':'#fee2e2';
        const ifc=p>50?'#15803d':p>15?'#a16207':'#dc2626';
        h+=`<div class="stk-card${mine?' me':''}" onclick="openDetail(${r.id})">
            <div class="stk-card-hd">
                <div class="stk-card-ic" style="background:${ibg};color:${ifc}"><i class="fas fa-flask"></i></div>
                <div style="flex:1;min-width:0">
                    <div class="stk-card-nm">${esc(r.chemical_name)}</div>
                    <div class="stk-card-cd">${esc(r.bottle_code)}</div>
                </div>
                ${mine?'<i class="fas fa-user" style="font-size:10px;color:var(--sb)" title="'+T('ของฉัน','Mine')+'"></i>':''}
            </div>
            <div class="stk-card-tg">
                ${r.cas_no?`<span class="stk-card-tag" style="background:#f1f5f9;color:#475569">${esc(r.cas_no)}</span>`:''}
                <span class="stk-badge stk-badge-${r.status}">${statusLbl(r.status)}</span>
                ${r.grade?`<span class="stk-card-tag" style="background:#ede9fe;color:#6d28d9">${esc(shortGrade(r.grade))}</span>`:''}
            </div>
            <div class="stk-card-bar"><div class="stk-card-bf ${bc}" style="width:${Math.min(p,100)}%"></div></div>
            <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:6px">
                <span style="font-weight:700">${numFmt(r.remaining_qty)} / ${numFmt(r.package_size)} ${esc(r.unit||'')}</span>
                <span style="font-weight:700;color:${pctColor(p)}">${Math.round(p)}%</span>
            </div>
            <div class="stk-card-ft">
                <div><span class="stk-av">${esc(initial(r))}</span>${esc(shortName(r.owner_name))}</div>
                <span>${fmtDate(r.added_at)}</span>
            </div>
        </div>`;
    });
    h+='</div>';
    area.innerHTML=h;
}

/* ═════════════════════════════════════════
   COMPACT VIEW
   ═════════════════════════════════════════ */
function renderCompact(area){
    let h='<div class="stk-compact">';
    DATA.forEach(r=>{
        const p=pct(r),bc=barCls(p),mine=isMine(r);
        h+=`<div class="stk-cr${mine?' me':''}" onclick="openDetail(${r.id})">
            <span class="stk-badge stk-badge-${r.status}" style="flex-shrink:0">${statusLbl(r.status)}</span>
            <div class="stk-cn" title="${esc(r.chemical_name)}">${esc(r.chemical_name)}</div>
            <span class="stk-cc">${esc(r.cas_no||'')}</span>
            <div class="stk-cb"><div class="${bc}" style="width:${Math.min(p,100)}%"></div></div>
            <span class="stk-cp" style="color:${pctColor(p)}">${Math.round(p)}%</span>
            <span class="stk-co">${esc(shortName(r.owner_name))}</span>
            ${canManage(r)?`<button class="stk-btn stk-btn-g stk-btn-s" onclick="event.stopPropagation();openUse(${r.id})"><i class="fas fa-vial"></i></button>`:''}
        </div>`;
    });
    h+='</div>';
    area.innerHTML=h;
}

/* ═════════════════════════════════════════
   ANALYTICS VIEW
   ═════════════════════════════════════════ */
function renderAnalytics(area){
    if(!STATS){area.innerHTML='<div class="stk-empty"><p>Loading…</p></div>';return}
    const s=STATS;
    const sd=[{l:T('ปกติ','Active'),v:s.active,c:'#22c55e'},{l:T('เหลือน้อย','Low'),v:s.low,c:'#eab308'},{l:T('หมด','Empty'),v:s.empty||0,c:'#ef4444'}];
    const tot=sd.reduce((a,x)=>a+x.v,0)||1;
    let svg='',off=0;
    sd.forEach(d=>{const pc=(d.v/tot)*100;const ds=2*Math.PI*40;svg+=`<circle cx="50" cy="50" r="40" fill="none" stroke="${d.c}" stroke-width="14" stroke-dasharray="${ds*pc/100} ${ds*(1-pc/100)}" stroke-dashoffset="${-ds*off/100}" transform="rotate(-90 50 50)"/>`;off+=pc});
    const tc=s.top_chemicals||[];const mc=tc.length?tc[0].cnt:1;
    const ud=s.unit_distribution||[];const mu=ud.length?ud[0].cnt:1;
    const cl=['#22c55e','#3b82f6','#8b5cf6','#f59e0b','#ec4899'];
    let h=`<div class="stk-an">
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-chart-pie"></i> ${T('สถานะขวดสาร','Bottle Status')}</div>
            <div class="stk-dn"><svg width="120" height="120" viewBox="0 0 100 100">${svg}<text x="50" y="48" text-anchor="middle" font-size="18" font-weight="800" fill="var(--c1)">${num(s.total)}</text><text x="50" y="60" text-anchor="middle" font-size="7" fill="var(--c3)">${T('ขวดทั้งหมด','TOTAL')}</text></svg>
            <div class="stk-dl2">${sd.map(d=>`<div class="stk-di"><div class="stk-dd" style="background:${d.c}"></div><span>${d.l}: <b>${num(d.v)}</b> (${Math.round(d.v/tot*100)}%)</span></div>`).join('')}</div></div>
        </div>
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-vials"></i> ${T('สารเคมียอดนิยม','Top Chemicals')}</div>
            <div class="stk-bc">${tc.map((c,i)=>`<div class="stk-br"><span class="stk-bl" title="${esc(c.chemical_name)}">${esc(c.chemical_name.length>14?c.chemical_name.substring(0,12)+'…':c.chemical_name)}</span><div class="stk-bt"><div class="stk-bf" style="width:${(c.cnt/mc)*100}%;background:${cl[i%cl.length]}">${c.cnt}</div></div><span class="stk-bv">${T(c.cnt+' ขวด',c.cnt+' btl')}</span></div>`).join('')}${!tc.length?`<p style="text-align:center;color:var(--c3);font-size:12px">${T('ไม่มีข้อมูล','No data')}</p>`:''}</div>
        </div>
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-balance-scale"></i> ${T('การกระจายตามหน่วย','Unit Distribution')}</div>
            <div class="stk-bc">${ud.map((u,i)=>`<div class="stk-br"><span class="stk-bl">${esc(u.unit)}</span><div class="stk-bt"><div class="stk-bf" style="width:${(u.cnt/mu)*100}%;background:${cl[(i+2)%cl.length]}">${u.cnt}</div></div><span class="stk-bv">${num(u.cnt)}</span></div>`).join('')}</div>
        </div>
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-info-circle"></i> ${T('สรุปภาพรวม','Summary')}</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div style="text-align:center;padding:14px;background:#f0fdf4;border-radius:10px"><div style="font-size:24px;font-weight:800;color:#16a34a">${num(s.total)}</div><div style="font-size:10px;color:var(--c3);text-transform:uppercase">${T('ขวดทั้งหมด','Total')}</div></div>
                <div style="text-align:center;padding:14px;background:#eff6ff;border-radius:10px"><div style="font-size:24px;font-weight:800;color:#2563eb">${num(s.unique_chemicals)}</div><div style="font-size:10px;color:var(--c3);text-transform:uppercase">${T('ชนิดสาร','Chemicals')}</div></div>
                <div style="text-align:center;padding:14px;background:#fef9c3;border-radius:10px"><div style="font-size:24px;font-weight:800;color:#a16207">${num(s.low)}</div><div style="font-size:10px;color:var(--c3);text-transform:uppercase">${T('เหลือน้อย','Low Stock')}</div></div>
                <div style="text-align:center;padding:14px;background:#f3e8ff;border-radius:10px"><div style="font-size:24px;font-weight:800;color:#7c3aed">${num(s.unique_owners)}</div><div style="font-size:10px;color:var(--c3);text-transform:uppercase">${T('ผู้ดูแล','Owners')}</div></div>
            </div>
        </div>
    </div>`;
    // Also show table below analytics
    h+='<div style="margin-top:18px">';
    const tmp={innerHTML:''};renderTable(tmp);h+=tmp.innerHTML;
    h+='</div>';
    area.innerHTML=h;
}

/* ═════════════════════════════════════════
   PAGINATION
   ═════════════════════════════════════════ */
function renderPager(pg){
    if(!pg||pg.pages<=1){document.getElementById('pagerArea').innerHTML='';return}
    let h='';
    h+=`<button ${pg.page<=1?'disabled':''} onclick="loadData(${pg.page-1})"><i class="fas fa-chevron-left"></i></button>`;
    const mx=5;let st=Math.max(1,pg.page-Math.floor(mx/2)),en=Math.min(pg.pages,st+mx-1);
    if(en-st<mx-1)st=Math.max(1,en-mx+1);
    if(st>1){h+=`<button onclick="loadData(1)">1</button>`;if(st>2)h+=`<span class="stk-pager-info">…</span>`}
    for(let i=st;i<=en;i++)h+=`<button class="${i===pg.page?'active':''}" onclick="loadData(${i})">${i}</button>`;
    if(en<pg.pages){if(en<pg.pages-1)h+=`<span class="stk-pager-info">…</span>`;h+=`<button onclick="loadData(${pg.pages})">${pg.pages}</button>`}
    h+=`<button ${pg.page>=pg.pages?'disabled':''} onclick="loadData(${pg.page+1})"><i class="fas fa-chevron-right"></i></button>`;
    h+=`<span class="stk-pager-info">${T('หน้า '+pg.page+'/'+pg.pages+' • '+num(pg.total)+' รายการ','Page '+pg.page+'/'+pg.pages+' • '+num(pg.total)+' items')}</span>`;
    document.getElementById('pagerArea').innerHTML=h;
}

/* ═════════════════════════════════════════
   DETAIL MODAL
   ═════════════════════════════════════════ */
async function openDetail(id){
    const ov=document.getElementById('detailOv');ov.classList.add('show');
    document.getElementById('detailTitle').textContent=T('กำลังโหลด...','Loading...');
    document.getElementById('detailBody').innerHTML='<div style="text-align:center;padding:24px"><i class="fas fa-spinner fa-spin fa-2x" style="color:var(--c3)"></i></div>';
    try{
        const d=await apiFetch('/v1/api/stock.php?action=detail&id='+id);
        if(!d.success)throw new Error(d.error);
        const r=d.data;const p=pct(r),bc=barCls(p);
        document.getElementById('detailTitle').textContent=r.chemical_name;
        let h=`
        <div style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span style="font-size:12px;font-weight:600;color:var(--c3)">${T('ปริมาณคงเหลือ','Remaining')}</span>
                <span style="font-size:16px;font-weight:800;color:${pctColor(p)}">${Math.round(p)}%</span>
            </div>
            <div class="stk-card-bar" style="height:10px;border-radius:5px"><div class="stk-card-bf ${bc}" style="height:100%;width:${Math.min(p,100)}%"></div></div>
            <div style="text-align:center;font-size:14px;font-weight:700;margin-top:6px">${numFmt(r.remaining_qty)} / ${numFmt(r.package_size)} ${esc(r.unit||'')}</div>
        </div>
        <div class="stk-dg">
            <div><div class="stk-dlb">${T('รหัสขวด','Bottle Code')}</div><div class="stk-dvl"><code>${esc(r.bottle_code)}</code></div></div>
            <div><div class="stk-dlb">CAS No.</div><div class="stk-dvl">${esc(r.cas_no||'—')}</div></div>
            <div class="stk-df"><div class="stk-dlb">${T('เกรด','Grade')}</div><div class="stk-dvl">${esc(r.grade||'—')}</div></div>
            <div><div class="stk-dlb">${T('สถานะ','Status')}</div><div class="stk-dvl"><span class="stk-badge stk-badge-${r.status}">${statusLbl(r.status)}</span></div></div>
            <div><div class="stk-dlb">${T('สถานที่','Location')}</div><div class="stk-dvl">${esc(r.storage_location||'—')}</div></div>
            <div><div class="stk-dlb">${T('ผู้เพิ่มขวด','Added By')}</div><div class="stk-dvl">
                <div style="display:flex;align-items:center;gap:6px">
                    <span class="stk-av">${esc(initial(r))}</span>
                    <span>${esc(r.owner_name||'—')}</span>
                    ${r.owner_username?`<span style="font-size:10px;padding:1px 6px;border-radius:4px;background:#f0fdf4;color:#15803d;font-weight:600">@${esc(r.owner_username)}</span>`:'<span style="font-size:10px;padding:1px 6px;border-radius:4px;background:#fef3c7;color:#92400e;font-weight:600">⚠ unmapped</span>'}
                    ${parseInt(r.owner_user_id)===UID?`<span style="font-size:9px;padding:1px 6px;border-radius:4px;background:#dbeafe;color:#1d4ed8;font-weight:600">${T('ของฉัน','Mine')}</span>`:''}
                </div>
            </div></div>
            <div><div class="stk-dlb">${T('เวลาเพิ่ม','Date Added')}</div><div class="stk-dvl">${fmtDate(r.added_at)}</div></div>
            ${r.owner_department?`<div class="stk-df"><div class="stk-dlb">${T('ฝ่าย/แผนก','Department')}</div><div class="stk-dvl">${esc(r.owner_department)}</div></div>`:''}
        </div>`;
        if(r.linked_chem_name){
            h+=`<div style="margin-top:14px;padding:12px;background:#f0fdf4;border-radius:var(--stk-rs);border:1px solid #bbf7d0">
                <div style="font-size:11px;font-weight:700;color:#15803d;margin-bottom:6px"><i class="fas fa-link"></i> ${T('ข้อมูลเชื่อมโยง','Linked Chemical')}</div>
                <div style="font-size:12px;color:var(--c1)">${esc(r.linked_chem_name)}</div>
                ${r.molecular_formula?`<div style="font-size:11px;color:var(--c3);margin-top:2px">Formula: ${esc(r.molecular_formula)}</div>`:''}
                ${r.signal_word?`<div style="font-size:11px;margin-top:4px"><span class="stk-badge" style="background:${r.signal_word==='Danger'?'#fee2e2':'#fef9c3'};color:${r.signal_word==='Danger'?'#dc2626':'#a16207'}">${esc(r.signal_word)}</span></div>`:''}
            </div>`;
        }
        const cm=IS_ADMIN||parseInt(r.owner_user_id)===UID;
        if(CAN_EDIT&&cm){
            h+=`<div class="stk-da">
                <button class="stk-btn stk-btn-p" onclick="closeDetail();openUse(${r.id})"><i class="fas fa-vial"></i> ${T('บันทึกการใช้','Record Use')}</button>
                <button class="stk-btn stk-btn-g" onclick="closeDetail();openEditModal(${r.id})"><i class="fas fa-edit"></i> ${T('แก้ไข','Edit')}</button>
                ${IS_ADMIN?`<button class="stk-btn stk-btn-d" onclick="deleteStock(${r.id})"><i class="fas fa-trash"></i> ${T('ลบ','Delete')}</button>`:''}
            </div>`;
        }
        document.getElementById('detailBody').innerHTML=h;
    }catch(e){document.getElementById('detailBody').innerHTML='<div style="padding:24px;text-align:center;color:#dc2626">'+esc(e.message)+'</div>'}
}
function closeDetail(){document.getElementById('detailOv').classList.remove('show')}

/* ═════════════════════════════════════════
   ADD / EDIT
   ═════════════════════════════════════════ */
function openAddModal(){document.getElementById('editId').value='';document.getElementById('addTitle').textContent=T('เพิ่มขวดสารเคมี','Add Chemical Bottle');document.getElementById('addForm').reset();document.getElementById('addOv').classList.add('show')}
async function openEditModal(id){
    try{
        const d=await apiFetch('/v1/api/stock.php?action=detail&id='+id);if(!d.success)throw new Error(d.error);const r=d.data;
        document.getElementById('editId').value=r.id;
        document.getElementById('addTitle').textContent=T('แก้ไขขวดสารเคมี','Edit Chemical Bottle');
        document.getElementById('fBottleCode').value=r.bottle_code||'';
        document.getElementById('fCasNo').value=r.cas_no||'';
        document.getElementById('fChemName').value=r.chemical_name||'';
        document.getElementById('fGrade').value=r.grade||'';
        document.getElementById('fPkgSize').value=r.package_size||'';
        document.getElementById('fRemQty').value=r.remaining_qty||'';
        document.getElementById('fUnitSel').value=r.unit||'';
        document.getElementById('fStorage').value=r.storage_location||'';
        document.getElementById('addOv').classList.add('show');
    }catch(e){toast('❌ '+e.message,'err')}
}
function closeAdd(){document.getElementById('addOv').classList.remove('show')}
async function submitAdd(e){
    e.preventDefault();const eid=document.getElementById('editId').value;
    const pl={bottle_code:document.getElementById('fBottleCode').value,chemical_name:document.getElementById('fChemName').value,cas_no:document.getElementById('fCasNo').value,grade:document.getElementById('fGrade').value,package_size:parseFloat(document.getElementById('fPkgSize').value)||0,remaining_qty:parseFloat(document.getElementById('fRemQty').value)||0,unit:document.getElementById('fUnitSel').value,storage_location:document.getElementById('fStorage').value};
    const btn=document.getElementById('addSubmitBtn');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    try{
        const d=eid?await apiFetch('/v1/api/stock.php?id='+eid,{method:'PUT',body:JSON.stringify(pl)}):await apiFetch('/v1/api/stock.php?action=create',{method:'POST',body:JSON.stringify(pl)});
        if(!d.success)throw new Error(d.error);
        toast('✅ '+T('บันทึกสำเร็จ','Saved'),'ok');closeAdd();loadData(PAGE);loadStats();
    }catch(e){toast('❌ '+e.message,'err')}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> '+T('บันทึก','Save')}
}

/* ═════════════════════════════════════════
   USE (Record Usage)
   ═════════════════════════════════════════ */
function openUse(id){
    const r=DATA.find(x=>parseInt(x.id)===id);if(!r)return;
    document.getElementById('useStockId').value=id;
    document.getElementById('useChemName').textContent=r.chemical_name;
    document.getElementById('useCurrentQty').textContent=T('คงเหลือ: ','Remaining: ')+numFmt(r.remaining_qty)+' '+esc(r.unit||'');
    document.getElementById('useUnitLabel').textContent=r.unit||'';
    document.getElementById('useAmount').value='';
    document.getElementById('useOv').classList.add('show');
    setTimeout(()=>document.getElementById('useAmount').focus(),200);
}
function closeUse(){document.getElementById('useOv').classList.remove('show')}
async function submitUse(){
    const id=parseInt(document.getElementById('useStockId').value);
    const amt=parseFloat(document.getElementById('useAmount').value);
    if(!amt||amt<=0){toast(T('กรุณากรอกจำนวน','Enter amount'),'err');return}
    try{
        const d=await apiFetch('/v1/api/stock.php?action=use',{method:'POST',body:JSON.stringify({id,amount:amt})});
        if(!d.success)throw new Error(d.error);
        toast('✅ '+T('บันทึกการใช้งานเรียบร้อย','Usage recorded'),'ok');closeUse();loadData(PAGE);loadStats();
    }catch(e){toast('❌ '+e.message,'err')}
}

/* ═════════════════════════════════════════
   DELETE
   ═════════════════════════════════════════ */
async function deleteStock(id){
    if(!confirm(T('แน่ใจหรือไม่ว่าต้องการลบ?','Delete this record?')))return;
    try{const d=await apiFetch('/v1/api/stock.php?id='+id,{method:'DELETE'});if(!d.success)throw new Error(d.error);
    toast('✅ '+T('ลบสำเร็จ','Deleted'),'ok');closeDetail();loadData(PAGE);loadStats()}catch(e){toast('❌ '+e.message,'err')}
}

/* ═════════════════════════════════════════
   IMPORT
   ═════════════════════════════════════════ */
function openImportModal(){document.getElementById('importResult').style.display='none';document.getElementById('importFile').value='';document.getElementById('importOv').classList.add('show')}
function closeImport(){document.getElementById('importOv').classList.remove('show')}
function setupDrop(){
    const z=document.getElementById('dropZone');if(!z)return;
    ['dragenter','dragover'].forEach(e=>z.addEventListener(e,ev=>{ev.preventDefault();z.classList.add('drag')}));
    ['dragleave','drop'].forEach(e=>z.addEventListener(e,ev=>{ev.preventDefault();z.classList.remove('drag')}));
    z.addEventListener('drop',ev=>{if(ev.dataTransfer.files[0])doImport(ev.dataTransfer.files[0])});
}
function handleImportFile(input){if(input.files[0])doImport(input.files[0])}
async function doImport(file){
    const res=document.getElementById('importResult');res.style.display='block';
    res.innerHTML='<div style="text-align:center;padding:16px"><i class="fas fa-spinner fa-spin fa-2x" style="color:var(--accent)"></i><p style="margin-top:8px;font-size:12px;color:var(--c3)">'+T('กำลัง import...','Importing...')+'</p></div>';
    const fd=new FormData();fd.append('file',file);
    try{
        const resp=await fetch('/v1/api/stock.php?action=import',{method:'POST',body:fd,headers:{'Authorization':'Bearer '+getCookie('auth_token')}});
        const d=await resp.json();if(!d.success)throw new Error(d.error);const r=d.data;
        res.innerHTML=`<div style="padding:14px;background:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0">
            <div style="font-weight:700;color:#15803d;margin-bottom:6px"><i class="fas fa-check-circle"></i> ${T('Import สำเร็จ','Import Complete')}</div>
            <div style="font-size:13px">✅ ${T('นำเข้า','Imported')}: <b>${r.imported}</b></div>
            ${r.errors>0?`<div style="font-size:13px;color:#dc2626">❌ ${T('ผิดพลาด','Errors')}: ${r.errors}</div>`:''}
        </div>`;loadData(1);loadStats();
    }catch(e){res.innerHTML=`<div style="padding:14px;background:#fee2e2;border-radius:10px;color:#dc2626"><i class="fas fa-exclamation-circle"></i> ${esc(e.message)}</div>`}
}

/* ═════════════════════════════════════════
   EXPORT
   ═════════════════════════════════════════ */
function doExport(){const s=document.getElementById('searchInput').value.trim();const a=document.createElement('a');a.href='/v1/api/stock.php?action=export&search='+encodeURIComponent(s);a.target='_blank';document.body.appendChild(a);a.click();a.remove();toast(T('📥 กำลังดาวน์โหลด...','📥 Downloading...'),'ok')}

/* ═════════════════════════════════════════
   TABS / VIEWS / SORT / FILTER
   ═════════════════════════════════════════ */
function switchTab(tab){TAB=tab;document.querySelectorAll('.stk-tab').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));document.getElementById('myBanner').style.display=tab==='my'?'flex':'none';loadData(1)}
function setView(v){
    VIEW=v;document.querySelectorAll('#viewSw button').forEach(b=>b.classList.toggle('active',b.dataset.view===v));
    document.getElementById('toolbar').style.display=v==='analytics'?'none':'flex';
    document.getElementById('filterPanel').classList.remove('show');
    if(v==='analytics'){renderView();document.getElementById('pagerArea').innerHTML=''}else{renderView()}
}
function sortBy(key){if(SORT===key)DIR=DIR==='ASC'?'DESC':'ASC';else{SORT=key;DIR='ASC'}const sel=document.getElementById('fSort');if(sel)sel.value=key;loadData(PAGE)}
function toggleFilter(){const p=document.getElementById('filterPanel');p.classList.toggle('show');document.getElementById('filterToggle').classList.toggle('active',p.classList.contains('show'))}
function clearFilters(){document.getElementById('fStatus').value='';document.getElementById('fUnit').value='';if(document.getElementById('fOwner'))document.getElementById('fOwner').value='';document.getElementById('fSort').value='added_at';document.getElementById('searchInput').value='';SF='';document.querySelectorAll('.stk-stat').forEach(el=>el.classList.remove('af'));loadData(1)}

/* ═════════════════════════════════════════
   HELPERS
   ═════════════════════════════════════════ */
function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=String(s);return d.innerHTML}
function num(n){return(n||0).toLocaleString()}
function numFmt(n){const v=parseFloat(n);if(isNaN(v))return '—';return v%1===0?v.toLocaleString():v.toLocaleString(undefined,{minimumFractionDigits:0,maximumFractionDigits:2})}
function pct(r){return parseFloat(r.remaining_pct)||0}
function barCls(p){return p>50?'bar-ok':p>15?'bar-mid':'bar-low'}
function pctColor(p){return p>50?'#16a34a':p>15?'#a16207':'#dc2626'}
function isMine(r){return parseInt(r.owner_user_id)===UID}
function canManage(r){return CAN_EDIT&&(IS_ADMIN||isMine(r))}
function initial(r){
    const name=r.owner_name||r.owner_first||'?';
    // Strip Thai titles to get first char of actual name
    const clean=name.replace(/^(นาย|นางสาว|นาง|ดร\.)\ s*/,'');
    return clean.charAt(0)||'?';
}
function statusLbl(s){const m={active:T('ปกติ','Active'),low:T('เหลือน้อย','Low'),empty:T('หมด','Empty'),expired:T('หมดอายุ','Expired'),disposed:T('กำจัดแล้ว','Disposed')};return m[s]||s}
function shortName(n){return n?n.replace(/^(นาย|นางสาว|นาง|ดร\.)\ s*/,''):'—'}
function shortGrade(g){return g?(g.length>20?g.substring(0,18)+'…':g):''}
function fmtDate(d){if(!d)return '—';try{return new Date(d).toLocaleDateString(L==='th'?'th-TH':'en-US',{day:'numeric',month:'short',year:'numeric'})}catch(e){return d}}
function getCookie(n){const m=document.cookie.match(new RegExp('(^| )'+n+'=([^;]+)'));return m?m[2]:''}
function toast(msg,type){const t=document.getElementById('stkToast');t.textContent=msg;t.className='stk-toast '+(type||'')+' show';setTimeout(()=>t.classList.remove('show'),3000)}

/* ═══ Admin: Relink Owners ═══ */
async function relinkOwners(){
    if(!confirm(T('ซ่อมแซมการเชื่อมโยง owner_user_id กับตาราง users ใหม่ทั้งหมด?','Re-link all owner_user_id mappings to current users table?')))return;
    try{
        const d=await apiFetch('/v1/api/stock.php?action=relink');
        if(!d.success)throw new Error(d.error);
        const r=d.data;
        if(r.fixed>0){
            toast('✅ '+T('ซ่อมแซมสำเร็จ '+r.fixed+' รายการ','Fixed '+r.fixed+' records'),'ok');
            loadData(PAGE);loadStats();
        }else{
            toast('✅ '+T('ข้อมูลถูกต้องทั้งหมดแล้ว','All mappings are correct'),'ok');
        }
    }catch(e){toast('❌ '+e.message,'err')}
}
</script>
</body></html>
