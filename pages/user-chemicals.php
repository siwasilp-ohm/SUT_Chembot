<?php
/**
 * ข้อมูลสารรายบุคคล — User Chemical Inventory
 */
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
if (!in_array($user['role_name'], ['admin', 'ceo', 'lab_manager'])) { header('Location: /v1/'); exit; }
$lang = I18n::getCurrentLang();
$TH   = $lang === 'th';
Layout::head($TH ? 'ข้อมูลสารรายบุคคล' : 'User Chemical Inventory');
?>
<body>
<?php Layout::sidebar('user-chemicals'); Layout::beginContent(); ?>

<style>
:root{--uc-r:14px;--uc-rs:10px;--uc-sh:0 1px 6px rgba(0,0,0,.06);--uc-shm:0 4px 20px rgba(0,0,0,.09)}

/* ── Hero ── */
.uc-hero{background:linear-gradient(135deg,#1e1b4b 0%,#4c1d95 45%,#7c3aed 100%);border-radius:var(--uc-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.uc-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.uc-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;position:relative}
.uc-hero-info{position:relative}
.uc-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.uc-hero-info p{font-size:12px;opacity:.85;margin:0}
.uc-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0;position:relative}
.uc-hero-c{text-align:center}
.uc-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.uc-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}
.uc-hero-sep{width:1px;background:rgba(255,255,255,.2)}

/* ── Stats ── */
.uc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.uc-stat{background:#fff;border-radius:var(--uc-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--uc-sh);border:1.5px solid var(--border);transition:all .15s}
.uc-stat:hover{transform:translateY(-2px);box-shadow:var(--uc-shm)}
.uc-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.uc-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.uc-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Toolbar ── */
.uc-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.uc-search{flex:1;min-width:200px;position:relative}
.uc-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:var(--uc-rs);font-size:13px;background:#fff;color:var(--c1);transition:border .15s;font-family:inherit}
.uc-search input:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.1)}
.uc-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:13px}
.uc-sel{padding:8px 30px 8px 10px;border:1.5px solid var(--border);border-radius:var(--uc-rs);font-size:12px;background:#fff;color:var(--c1);cursor:pointer;font-family:inherit;min-width:140px;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2394a3b8'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.uc-sel:focus{outline:none;border-color:#7c3aed}
.uc-btn{padding:8px 16px;border:none;border-radius:var(--uc-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap}
.uc-btn-p{background:#7c3aed;color:#fff}.uc-btn-p:hover{background:#6d28d9}
.uc-btn-g{background:#fff;color:var(--c3);border:1.5px solid var(--border)}.uc-btn-g:hover{border-color:#7c3aed;color:#7c3aed}
.uc-vw{display:flex;border:1.5px solid var(--border);border-radius:var(--uc-rs);overflow:hidden}
.uc-vw button{padding:7px 11px;border:none;background:#fff;color:var(--c3);cursor:pointer;font-size:12px;transition:all .12s}
.uc-vw button+button{border-left:1px solid var(--border)}
.uc-vw button.on{background:#7c3aed;color:#fff}
.uc-vw button:hover:not(.on){background:#f8fafc}

/* ── List view card ── */
.uc-list{display:flex;flex-direction:column;gap:8px}
.uc-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--uc-r);overflow:hidden;transition:border-color .15s,box-shadow .15s;box-shadow:var(--uc-sh)}
.uc-card:hover{border-color:#c4b5fd;box-shadow:var(--uc-shm)}
.uc-card.open{border-color:#7c3aed;box-shadow:0 4px 20px rgba(124,58,237,.12)}
.uc-row{display:flex;align-items:center;gap:14px;padding:14px 18px;cursor:pointer;user-select:none;transition:background .12s}
.uc-row:hover{background:#faf8ff}
.uc-card.open .uc-row{background:#faf8ff}

/* ── Avatar ── */
.uc-av{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#fff;flex-shrink:0;overflow:hidden}
.uc-av img{width:100%;height:100%;object-fit:cover;border-radius:12px}

/* ── User info ── */
.uc-name{font-weight:700;font-size:14px;color:var(--c1);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.uc-meta{font-size:11px;color:var(--c3);margin-top:3px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.uc-meta i{font-size:9px;margin-right:2px}

/* ── Role badge ── */
.uc-rb{font-size:9px;font-weight:700;padding:2px 7px;border-radius:6px;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.uc-rb-admin{background:#fee2e2;color:#b91c1c}
.uc-rb-ceo{background:#dbeafe;color:#1d4ed8}
.uc-rb-lab_manager{background:#fef3c7;color:#b45309}
.uc-rb-user{background:#ede9fe;color:#5b21b6}
.uc-rb-visitor{background:#f1f5f9;color:#64748b}

/* ── Stat pills ── */
.uc-pills{display:flex;gap:5px;flex-shrink:0;align-items:center;flex-wrap:wrap}
.uc-pill{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:7px;font-size:11px;font-weight:700;white-space:nowrap}
.uc-pill-blue{background:#eff6ff;color:#2563eb}
.uc-pill-amber{background:#fef3c7;color:#b45309}
.uc-pill-red{background:#fef2f2;color:#dc2626}
.uc-pill-green{background:#f0fdf4;color:#15803d}
.uc-pill-gray{background:#f8fafc;color:#64748b}
.uc-pill i{font-size:9px}

/* ── Arrow ── */
.uc-arrow{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--c3);transition:all .2s;font-size:11px;flex-shrink:0;background:#f1f5f9}
.uc-card.open .uc-arrow{transform:rotate(180deg);color:#7c3aed;background:#ede9fe}

/* ── Expand panel ── */
.uc-panel{max-height:0;overflow:hidden;transition:max-height .3s ease;background:#fafbff;border-top:1px solid transparent}
.uc-card.open .uc-panel{max-height:3000px;border-top-color:#e8e4ff}
.uc-panel-inner{padding:0}
.uc-panel-ld{padding:30px;text-align:center;color:var(--c3);display:flex;align-items:center;justify-content:center;gap:8px;font-size:13px}
.uc-panel-ld i{animation:ucSpin 1s linear infinite}
@keyframes ucSpin{to{transform:rotate(360deg)}}

/* ── Chemical table ── */
.uc-tbl-wrap{overflow-x:auto}
.uc-tbl{width:100%;border-collapse:collapse;font-size:12px}
.uc-tbl thead{background:#f5f3ff;position:sticky;top:0;z-index:1}
.uc-tbl th{padding:10px 14px;text-align:left;font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#5b21b6;border-bottom:2px solid #e8e4ff;white-space:nowrap}
.uc-tbl td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;color:var(--c1)}
.uc-tbl tr:last-child td{border-bottom:none}
.uc-tbl tbody tr:hover td{background:#f5f3ff}
.uc-code{font-family:'Courier New',monospace;font-size:10px;font-weight:600;background:#f1f5f9;padding:2px 6px;border-radius:4px;color:#475569;letter-spacing:.5px}
.uc-pct-bar{display:flex;align-items:center;gap:6px}
.uc-pct-track{flex:1;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;min-width:50px}
.uc-pct-fill{height:100%;border-radius:3px;transition:width .3s}
.uc-pct-val{font-size:11px;font-weight:700;min-width:34px;text-align:right}

/* ── View all footer ── */
.uc-more{display:flex;justify-content:center;padding:10px;border-top:1px solid #e8e4ff}
.uc-more-btn{font-size:12px;color:#7c3aed;cursor:pointer;border:none;background:none;font-weight:700;display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;transition:background .12s}
.uc-more-btn:hover{background:#ede9fe}

/* ── Empty states ── */
.uc-empty{text-align:center;padding:60px 20px;color:var(--c3)}
.uc-empty i{font-size:48px;opacity:.25;margin-bottom:12px;display:block}
.uc-empty p{font-size:13px}
.uc-no-chem{padding:24px;text-align:center;color:var(--c3);font-size:12px}
.uc-no-chem i{font-size:24px;display:block;margin-bottom:6px;opacity:.3}
.uc-ld{padding:60px;text-align:center;color:var(--c3);font-size:13px;display:flex;align-items:center;justify-content:center;gap:8px}
.uc-ld i{animation:ucSpin 1s linear infinite}

/* ── Grid view ── */
.uc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px}
.uc-gcrd{background:#fff;border:1.5px solid var(--border);border-radius:var(--uc-r);overflow:hidden;transition:all .18s;cursor:pointer;position:relative}
.uc-gcrd:hover{border-color:#c4b5fd;box-shadow:var(--uc-shm);transform:translateY(-2px)}
.uc-gcrd-stripe{position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#7c3aed,#a78bfa)}
.uc-gcrd-hd{display:flex;align-items:flex-start;gap:12px;padding:18px 16px 0;margin-top:6px}
.uc-gcrd-info{flex:1;min-width:0}
.uc-gcrd-name{font-size:13px;font-weight:700;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.uc-gcrd-at{font-size:10px;color:var(--c3);margin-top:1px}
.uc-gcrd-bd{padding:10px 16px 14px}
.uc-gcrd-meta{display:flex;flex-direction:column;gap:4px;margin-bottom:10px}
.uc-gcrd-row{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--c2)}
.uc-gcrd-row i{width:14px;text-align:center;font-size:10px;color:var(--c3)}
.uc-gcrd-ft{display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid #f1f5f9;gap:6px}
.uc-gcrd-open-btn{font-size:10px;font-weight:700;padding:4px 10px;border:none;border-radius:6px;background:#ede9fe;color:#5b21b6;cursor:pointer;transition:all .12s}
.uc-gcrd-open-btn:hover{background:#7c3aed;color:#fff}

/* ── Modal ── */
.uc-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);z-index:9999;display:none;align-items:center;justify-content:center;padding:16px}
.uc-ov.show{display:flex}
.uc-md{background:#fff;border-radius:18px;width:100%;max-width:780px;max-height:92vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.22);animation:ucMdIn .22s cubic-bezier(.34,1.56,.64,1)}
@keyframes ucMdIn{from{transform:scale(.92) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
.uc-mh{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;background:linear-gradient(135deg,#1e1b4b,#4c1d95);color:#fff;flex-shrink:0}
.uc-mh-left{display:flex;align-items:center;gap:12px}
.uc-mh-av{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#fff;background:rgba(255,255,255,.2);overflow:hidden;flex-shrink:0}
.uc-mh-av img{width:100%;height:100%;object-fit:cover}
.uc-mh-name{font-weight:700;font-size:15px}
.uc-mh-sub{font-size:11px;opacity:.75;margin-top:1px}
.uc-mx{width:32px;height:32px;border-radius:8px;border:none;background:rgba(255,255,255,.15);cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all .12s}
.uc-mx:hover{background:rgba(255,255,255,.3)}
.uc-mb{overflow-y:auto;flex:1}
.uc-mfooter{padding:10px 16px;border-top:1px solid #e8e4ff;display:flex;gap:16px;justify-content:flex-end;font-size:12px;color:var(--c2);background:#fafbff;flex-shrink:0}

/* ── Toast ── */
.uc-toast-wrap{position:fixed;top:64px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:6px}
.uc-toast{background:#1a1a2e;color:#fff;padding:11px 18px;border-radius:10px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,.18);animation:ucToastIn .3s ease;min-width:220px}
@keyframes ucToastIn{from{transform:translateX(60px);opacity:0}to{transform:translateX(0);opacity:1}}

/* ── Responsive ── */
@media(max-width:700px){
    .uc-hero-meta{display:none}
    .uc-grid{grid-template-columns:1fr}
    .uc-row{flex-wrap:wrap;gap:10px}
    .uc-pills{width:100%;justify-content:flex-start}
    .uc-tbl th:nth-child(4),.uc-tbl td:nth-child(4){display:none}
}
@media(max-width:480px){
    .uc-tbl th:nth-child(5),.uc-tbl td:nth-child(5){display:none}
}
@media print{
    .uc-ov,.ci-sidebar,.uc-toolbar,.uc-btn{display:none!important}
    .ci-main{margin-left:0!important;padding:10px!important}
    .uc-card{break-inside:avoid}
    .uc-panel{max-height:none!important;overflow:visible!important}
}
</style>

<!-- ── Hero ── -->
<div class="uc-hero">
    <div class="uc-hero-ic"><i class="fas fa-user-tag"></i></div>
    <div class="uc-hero-info">
        <h2><?php echo $TH ? 'ข้อมูลสารรายบุคคล' : 'User Chemical Inventory'; ?></h2>
        <p><?php echo $TH ? 'สรุปภาพรวมสารเคมีในครอบครองของแต่ละบุคคล' : 'Overview of chemical holdings per individual user'; ?></p>
    </div>
    <div class="uc-hero-meta">
        <div class="uc-hero-c"><div class="v" id="hUsers">—</div><div class="lb"><?php echo $TH ? 'ผู้ใช้' : 'Users'; ?></div></div>
        <div class="uc-hero-sep"></div>
        <div class="uc-hero-c"><div class="v" id="hContainers">—</div><div class="lb"><?php echo $TH ? 'ภาชนะ' : 'Containers'; ?></div></div>
        <div class="uc-hero-sep"></div>
        <div class="uc-hero-c"><div class="v" id="hAlerts">—</div><div class="lb"><?php echo $TH ? 'แจ้งเตือน' : 'Alerts'; ?></div></div>
    </div>
</div>

<!-- ── Stats ── -->
<div class="uc-stats">
    <div class="uc-stat">
        <div class="uc-si" style="background:#ede9fe;color:#6d28d9"><i class="fas fa-users"></i></div>
        <div><div class="uc-sv" id="sTotalUsers">—</div><div class="uc-sl"><?php echo $TH ? 'ผู้ใช้ทั้งหมด' : 'Total Users'; ?></div></div>
    </div>
    <div class="uc-stat">
        <div class="uc-si" style="background:#dcfce7;color:#15803d"><i class="fas fa-user-check"></i></div>
        <div><div class="uc-sv" id="sWithChem">—</div><div class="uc-sl"><?php echo $TH ? 'มีสารเคมี' : 'With Chemicals'; ?></div></div>
    </div>
    <div class="uc-stat">
        <div class="uc-si" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-box"></i></div>
        <div><div class="uc-sv" id="sTotalCont">—</div><div class="uc-sl"><?php echo $TH ? 'ภาชนะทั้งหมด' : 'Total Containers'; ?></div></div>
    </div>
    <div class="uc-stat">
        <div class="uc-si" style="background:#fef3c7;color:#b45309"><i class="fas fa-battery-quarter"></i></div>
        <div><div class="uc-sv" id="sLowStock">—</div><div class="uc-sl"><?php echo $TH ? 'สต็อกต่ำ' : 'Low Stock'; ?></div></div>
    </div>
    <div class="uc-stat">
        <div class="uc-si" style="background:#fef2f2;color:#dc2626"><i class="fas fa-clock"></i></div>
        <div><div class="uc-sv" id="sExpiring">—</div><div class="uc-sl"><?php echo $TH ? 'ใกล้หมดอายุ' : 'Expiring'; ?></div></div>
    </div>
</div>

<!-- ── Toolbar ── -->
<div class="uc-toolbar">
    <div class="uc-search">
        <i class="fas fa-search"></i>
        <input type="text" id="fSearch" placeholder="<?php echo $TH ? 'ค้นหาชื่อ, ฝ่าย, คลัง...' : 'Search name, division, store...'; ?>" oninput="debounceLoad()">
    </div>
    <select id="fStore" class="uc-sel" onchange="loadData()">
        <option value=""><?php echo $TH ? '— ทุกคลัง —' : '— All Stores —'; ?></option>
    </select>
    <select id="fDivision" class="uc-sel" onchange="loadData()">
        <option value=""><?php echo $TH ? '— ทุกฝ่าย —' : '— All Divisions —'; ?></option>
    </select>
    <select id="fSort" class="uc-sel" onchange="loadData()">
        <option value="containers_desc"><?php echo $TH ? 'สารเคมีมาก → น้อย' : 'Most Chemicals'; ?></option>
        <option value="containers_asc"><?php echo $TH ? 'สารเคมีน้อย → มาก' : 'Fewest Chemicals'; ?></option>
        <option value="name_asc"><?php echo $TH ? 'ชื่อ ก-ฮ' : 'Name A-Z'; ?></option>
        <option value="name_desc"><?php echo $TH ? 'ชื่อ ฮ-ก' : 'Name Z-A'; ?></option>
        <option value="store"><?php echo $TH ? 'ตามคลัง' : 'By Store'; ?></option>
    </select>
    <div class="uc-vw" id="viewSw">
        <button class="on" onclick="setView('list')" title="List"><i class="fas fa-list"></i></button>
        <button onclick="setView('grid')" title="Grid"><i class="fas fa-th-large"></i></button>
    </div>
    <button class="uc-btn uc-btn-g" onclick="window.print()"><i class="fas fa-print"></i> <?php echo $TH ? 'พิมพ์' : 'Print'; ?></button>
</div>

<!-- ── Content ── -->
<div id="ucWrap">
    <div class="uc-ld"><i class="fas fa-circle-notch"></i> <?php echo $TH ? 'กำลังโหลด...' : 'Loading...'; ?></div>
</div>

<!-- ── Full-detail Modal ── -->
<div class="uc-ov" id="ucModal">
    <div class="uc-md">
        <div class="uc-mh">
            <div class="uc-mh-left">
                <div class="uc-mh-av" id="mdAvatar"><i class="fas fa-user"></i></div>
                <div>
                    <div class="uc-mh-name" id="mdName">—</div>
                    <div class="uc-mh-sub" id="mdSub">—</div>
                </div>
            </div>
            <button class="uc-mx" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="uc-mb" id="mdBody">
            <div class="uc-ld"><i class="fas fa-circle-notch"></i></div>
        </div>
        <div class="uc-mfooter" id="mdFooter"></div>
    </div>
</div>

<div class="uc-toast-wrap" id="ucToast"></div>

<?php Layout::endContent(); ?>

<script>
const TH = <?php echo $TH ? 'true' : 'false'; ?>;
let allUsers = [], loadedDetails = {}, debounceTimer = null, filtersPopulated = false, currentView = 'list';

/* ── Avatar helper ── */
function ucAvatar(u, size = 44, radius = '12px') {
    const initials = ((u.first_name||'')[0]||'') + ((u.last_name||'')[0]||'');
    const bgs = { admin:'#b91c1c', ceo:'#1d4ed8', lab_manager:'#b45309', user:'#5b21b6', visitor:'#64748b' };
    const bg = bgs[u.role_name] || '#5b21b6';
    const fs = Math.round(size * 0.35);
    if (u.avatar_url) {
        return `<div class="uc-av" style="width:${size}px;height:${size}px;border-radius:${radius};background:${bg};overflow:hidden;flex-shrink:0">` +
               `<img src="${esc(u.avatar_url)}" alt="" style="width:100%;height:100%;object-fit:cover" onerror="this.parentElement.innerHTML='<span style=\\'font-size:${fs}px;font-weight:700;color:#fff\\'>${initials}</span>';this.parentElement.style.display='flex';this.parentElement.style.alignItems='center';this.parentElement.style.justifyContent='center'">` +
               `</div>`;
    }
    return `<div class="uc-av" style="width:${size}px;height:${size}px;border-radius:${radius};background:${bg};font-size:${fs}px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;flex-shrink:0">${initials}</div>`;
}

function esc(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ── Debounce ── */
function debounceLoad() { clearTimeout(debounceTimer); debounceTimer = setTimeout(loadData, 300); }

/* ── View toggle ── */
function setView(v) {
    currentView = v;
    document.querySelectorAll('#viewSw button').forEach((b,i) => b.classList.toggle('on', (i===0&&v==='list')||(i===1&&v==='grid')));
    if (allUsers.length) {
        if (v === 'list') renderList(allUsers);
        else renderGrid(allUsers);
    }
}

/* ── Load data ── */
async function loadData() {
    const search   = document.getElementById('fSearch').value.trim();
    const store    = document.getElementById('fStore').value;
    const division = document.getElementById('fDivision').value;
    const sort     = document.getElementById('fSort').value;

    let url = `/v1/api/user_chemicals.php?action=list&sort=${encodeURIComponent(sort)}`;
    if (search)   url += `&search=${encodeURIComponent(search)}`;
    if (store)    url += `&store=${encodeURIComponent(store)}`;
    if (division) url += `&division=${encodeURIComponent(division)}`;

    document.getElementById('ucWrap').innerHTML = `<div class="uc-ld"><i class="fas fa-circle-notch"></i> ${TH?'กำลังโหลด...':'Loading...'}</div>`;

    try {
        const res = await apiFetch(url);
        if (!res.success) throw new Error(res.message);
        const d = res.data;
        allUsers = d.users || [];

        const s = d.stats || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el && v !== undefined) el.textContent = v; };
        set('sTotalUsers', s.total_users);
        set('sWithChem',   s.users_with_chemicals);
        set('sTotalCont',  s.total_containers);
        set('sLowStock',   s.total_low_stock);
        set('sExpiring',   s.total_expiring);
        set('hUsers',      s.total_users);
        set('hContainers', s.total_containers);
        set('hAlerts',     (parseInt(s.total_low_stock||0) + parseInt(s.total_expiring||0)));

        populateFilters(d.stores || [], d.divisions || []);

        if (currentView === 'grid') renderGrid(allUsers);
        else renderList(allUsers);
    } catch(e) {
        document.getElementById('ucWrap').innerHTML = `<div class="uc-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`;
    }
}

function populateFilters(stores, divisions) {
    if (filtersPopulated) return;
    filtersPopulated = true;
    const storeSel = document.getElementById('fStore');
    stores.forEach(s => { const o = document.createElement('option'); o.value = s.store_name; o.textContent = s.store_name; storeSel.appendChild(o); });
    const divSel = document.getElementById('fDivision');
    divisions.forEach(d => { const o = document.createElement('option'); o.value = d.division_name; o.textContent = d.division_name; divSel.appendChild(o); });
}

/* ── Role badge ── */
function roleBadge(roleName, display) {
    return `<span class="uc-rb uc-rb-${esc(roleName||'user')}">${esc(display||roleName||'user')}</span>`;
}

/* ── Stat pills ── */
function pillRow(cnt, low, exp, avgR) {
    let h = `<div class="uc-pill ${cnt > 0 ? 'uc-pill-blue' : 'uc-pill-gray'}"><i class="fas fa-box"></i> ${cnt}</div>`;
    if (low > 0) h += `<div class="uc-pill uc-pill-amber"><i class="fas fa-battery-quarter"></i> ${low}</div>`;
    if (exp > 0) h += `<div class="uc-pill uc-pill-red"><i class="fas fa-clock"></i> ${exp}</div>`;
    if (cnt > 0 && avgR > 0) {
        const cl = avgR <= 20 ? 'uc-pill-red' : avgR <= 50 ? 'uc-pill-amber' : 'uc-pill-green';
        h += `<div class="uc-pill ${cl}"><i class="fas fa-tachometer-alt"></i> ${avgR.toFixed(0)}%</div>`;
    }
    return h;
}

/* ─────────────────────────────────────────
   LIST VIEW
───────────────────────────────────────── */
function renderList(users) {
    if (!users.length) {
        document.getElementById('ucWrap').innerHTML = `<div class="uc-empty"><i class="fas fa-users"></i><p>${TH?'ไม่พบผู้ใช้':'No users found'}</p></div>`;
        return;
    }
    document.getElementById('ucWrap').innerHTML = `<div class="uc-list">${users.map(u => {
        const name  = `${esc(u.first_name||'')} ${esc(u.last_name||'')}`.trim();
        const cnt   = parseInt(u.active_containers) || 0;
        const low   = parseInt(u.low_stock_count)   || 0;
        const exp   = parseInt(u.expiring_count)    || 0;
        const avgR  = parseFloat(u.avg_remaining)   || 0;
        const stArr = u.store_names || [];
        const stTxt = stArr.length <= 2 ? stArr.join(', ') : stArr.slice(0,2).join(', ') + ` +${stArr.length-2}`;

        return `<div class="uc-card" id="uc-card-${u.id}">
            <div class="uc-row" onclick="toggleUser(${u.id})">
                ${ucAvatar(u, 44, '12px')}
                <div style="flex:1;min-width:0">
                    <div class="uc-name">${name} ${roleBadge(u.role_name, u.role_display)}</div>
                    <div class="uc-meta">
                        ${u.division_name ? `<span><i class="fas fa-sitemap"></i>${esc(u.division_name)}</span>` : ''}
                        ${stTxt ? `<span><i class="fas fa-warehouse"></i>${esc(stTxt)}</span>` : ''}
                        ${u.primary_lab_name ? `<span><i class="fas fa-flask" style="color:#7c3aed"></i>${esc(u.primary_lab_name)}</span>` : ''}
                    </div>
                </div>
                <div class="uc-pills">${pillRow(cnt, low, exp, avgR)}</div>
                <div class="uc-arrow"><i class="fas fa-chevron-down"></i></div>
            </div>
            <div class="uc-panel" id="uc-panel-${u.id}">
                <div class="uc-panel-inner" id="uc-inner-${u.id}">
                    <div class="uc-panel-ld"><i class="fas fa-circle-notch"></i> ${TH?'กำลังโหลด...':'Loading...'}</div>
                </div>
            </div>
        </div>`;
    }).join('')}</div>`;
}

async function toggleUser(userId) {
    const card = document.getElementById('uc-card-' + userId);
    const isOpen = card.classList.contains('open');
    document.querySelectorAll('.uc-card.open').forEach(c => c.classList.remove('open'));
    if (isOpen) return;
    card.classList.add('open');
    if (!loadedDetails[userId]) {
        document.getElementById('uc-inner-' + userId).innerHTML =
            `<div class="uc-panel-ld"><i class="fas fa-circle-notch"></i> ${TH?'กำลังโหลด...':'Loading...'}</div>`;
        try {
            const res = await apiFetch(`/v1/api/user_chemicals.php?action=detail&user_id=${userId}`);
            if (!res.success) throw new Error(res.message);
            loadedDetails[userId] = res.data;
            renderDetail(userId, res.data);
        } catch(e) {
            document.getElementById('uc-inner-' + userId).innerHTML =
                `<div class="uc-no-chem"><i class="fas fa-exclamation-circle"></i>${esc(e.message)}</div>`;
        }
    }
}

function renderDetail(userId, data) {
    const inner = document.getElementById('uc-inner-' + userId);
    const containers = data.containers || [];
    if (!containers.length) {
        inner.innerHTML = `<div class="uc-no-chem"><i class="fas fa-box-open"></i>${TH?'ไม่มีสารเคมีในครอบครอง':'No chemicals owned'}</div>`;
        return;
    }
    const showInline = containers.slice(0, 10);
    const hasMore    = containers.length > 10;

    let html = `<div class="uc-tbl-wrap"><table class="uc-tbl"><thead><tr>
        <th style="text-align:center;width:32px">#</th>
        <th>${TH?'ชื่อสารเคมี':'Chemical Name'}</th>
        <th>Barcode</th>
        <th>CAS</th>
        <th>${TH?'ตำแหน่ง':'Location'}</th>
        <th style="text-align:right">${TH?'ปริมาณ':'Qty'}</th>
        <th style="min-width:100px">${TH?'คงเหลือ':'Remaining'}</th>
        <th>${TH?'หมดอายุ':'Expiry'}</th>
    </tr></thead><tbody>`;

    showInline.forEach((c, i) => { html += buildRow(c, i + 1); });
    html += '</tbody></table></div>';

    if (hasMore) {
        html += `<div class="uc-more"><button class="uc-more-btn" onclick="openModal(${userId})">
            <i class="fas fa-external-link-alt"></i> ${TH?'ดูทั้งหมด':'View All'} (${containers.length} ${TH?'รายการ':'items'})
        </button></div>`;
    }
    inner.innerHTML = html;
}

/* ─────────────────────────────────────────
   GRID VIEW
───────────────────────────────────────── */
function renderGrid(users) {
    if (!users.length) {
        document.getElementById('ucWrap').innerHTML = `<div class="uc-empty"><i class="fas fa-users"></i><p>${TH?'ไม่พบผู้ใช้':'No users found'}</p></div>`;
        return;
    }
    document.getElementById('ucWrap').innerHTML = `<div class="uc-grid">${users.map(u => {
        const name  = `${esc(u.first_name||'')} ${esc(u.last_name||'')}`.trim();
        const cnt   = parseInt(u.active_containers) || 0;
        const low   = parseInt(u.low_stock_count)   || 0;
        const exp   = parseInt(u.expiring_count)    || 0;
        const avgR  = parseFloat(u.avg_remaining)   || 0;
        const stArr = u.store_names || [];
        const stTxt = stArr.length <= 2 ? stArr.join(', ') : stArr.slice(0,2).join(', ') + ` +${stArr.length-2}`;

        return `<div class="uc-gcrd" onclick="openModal(${u.id}, true)">
            <div class="uc-gcrd-stripe"></div>
            <div class="uc-gcrd-hd">
                ${ucAvatar(u, 48, '13px')}
                <div class="uc-gcrd-info">
                    <div class="uc-gcrd-name">${name}</div>
                    <div class="uc-gcrd-at">${roleBadge(u.role_name, u.role_display)}</div>
                </div>
            </div>
            <div class="uc-gcrd-bd">
                <div class="uc-gcrd-meta">
                    ${u.division_name   ? `<div class="uc-gcrd-row"><i class="fas fa-sitemap"></i>${esc(u.division_name)}</div>` : ''}
                    ${stTxt            ? `<div class="uc-gcrd-row"><i class="fas fa-warehouse"></i>${esc(stTxt)}</div>` : ''}
                    ${u.primary_lab_name ? `<div class="uc-gcrd-row"><i class="fas fa-flask" style="color:#7c3aed"></i><span style="color:#5b21b6;font-weight:600">${esc(u.primary_lab_name)}</span></div>` : ''}
                </div>
                <div class="uc-gcrd-ft">
                    <div class="uc-pills" style="gap:4px">${pillRow(cnt, low, exp, avgR)}</div>
                    <button class="uc-gcrd-open-btn" onclick="event.stopPropagation();openModal(${u.id}, true)">
                        <i class="fas fa-eye"></i> ${TH?'ดู':'View'}
                    </button>
                </div>
            </div>
        </div>`;
    }).join('')}</div>`;
}

/* ─────────────────────────────────────────
   Container table row helper
───────────────────────────────────────── */
function buildRow(c, num) {
    const pct   = parseFloat(c.remaining_percentage) || 0;
    const pctCl = pct <= 5 ? '#ef4444' : pct <= 20 ? '#f59e0b' : '#22c55e';
    const qty   = parseFloat(c.current_quantity) || 0;
    const unit  = c.quantity_unit || '';
    const bar   = c.bottle_code || c.qr_code || '—';
    const shortBar = bar.length > 18 ? bar.substring(0,18) + '…' : bar;
    const loc   = esc(c.location_path || '—');
    const expiry = c.expiry_date ? formatDate(c.expiry_date) : '—';
    const expired = c.expiry_date && new Date(c.expiry_date) < new Date();

    return `<tr>
        <td style="text-align:center;color:var(--c3);font-size:11px">${num}</td>
        <td>
            <strong style="font-size:12px">${esc(c.chemical_name||'—')}</strong>
            ${c.molecular_formula ? `<div style="font-size:10px;color:var(--c3)">${esc(c.molecular_formula)}</div>` : ''}
        </td>
        <td><span class="uc-code">${esc(shortBar)}</span></td>
        <td style="font-size:11px;color:var(--c3)">${esc(c.cas_number||'—')}</td>
        <td style="font-size:11px;color:var(--c2)">${loc}</td>
        <td style="text-align:right;white-space:nowrap;font-weight:600">${qty} <span style="color:var(--c3);font-weight:400">${esc(unit)}</span></td>
        <td>
            <div class="uc-pct-bar">
                <div class="uc-pct-track"><div class="uc-pct-fill" style="width:${Math.min(pct,100)}%;background:${pctCl}"></div></div>
                <div class="uc-pct-val" style="color:${pctCl}">${pct.toFixed(0)}%</div>
            </div>
        </td>
        <td style="font-size:11px;white-space:nowrap;${expired?'color:#dc2626;font-weight:600':''}">${expiry}${expired?' <i class="fas fa-exclamation-circle" style="font-size:9px;color:#dc2626"></i>':''}</td>
    </tr>`;
}

/* ─────────────────────────────────────────
   Modal
───────────────────────────────────────── */
async function openModal(userId, forceLoad = false) {
    // Find user from cached list
    const u = allUsers.find(x => parseInt(x.id) === userId);
    if (!u) return;

    // Set header
    const name = `${u.first_name||''} ${u.last_name||''}`.trim();
    const mdAv = document.getElementById('mdAvatar');
    if (u.avatar_url) {
        mdAv.innerHTML = `<img src="${esc(u.avatar_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px">`;
    } else {
        const bgs = { admin:'rgba(255,255,255,.3)', ceo:'rgba(255,255,255,.3)', lab_manager:'rgba(255,255,255,.3)', user:'rgba(255,255,255,.25)', visitor:'rgba(255,255,255,.2)' };
        const initials = ((u.first_name||'')[0]||'') + ((u.last_name||'')[0]||'');
        mdAv.innerHTML = `<span style="font-size:17px;font-weight:700">${initials}</span>`;
        mdAv.style.background = bgs[u.role_name] || 'rgba(255,255,255,.25)';
    }
    document.getElementById('mdName').textContent = name;
    const subParts = [];
    if (u.role_display) subParts.push(u.role_display);
    if (u.division_name) subParts.push(u.division_name);
    if ((u.store_names||[]).length) subParts.push(u.store_names.slice(0,2).join(', '));
    document.getElementById('mdSub').textContent = subParts.join(' · ');

    document.getElementById('mdBody').innerHTML = `<div class="uc-ld"><i class="fas fa-circle-notch"></i></div>`;
    document.getElementById('mdFooter').innerHTML = '';
    document.getElementById('ucModal').classList.add('show');
    document.body.style.overflow = 'hidden';

    // Load if not cached
    if (forceLoad || !loadedDetails[userId]) {
        try {
            const res = await apiFetch(`/v1/api/user_chemicals.php?action=detail&user_id=${userId}`);
            if (!res.success) throw new Error(res.message);
            loadedDetails[userId] = res.data;
        } catch(e) {
            document.getElementById('mdBody').innerHTML = `<div class="uc-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`;
            return;
        }
    }

    const data = loadedDetails[userId];
    const containers = data.containers || [];

    if (!containers.length) {
        document.getElementById('mdBody').innerHTML = `<div class="uc-empty"><i class="fas fa-box-open"></i><p>${TH?'ไม่มีสารเคมีในครอบครอง':'No chemicals owned'}</p></div>`;
        return;
    }

    let html = `<div class="uc-tbl-wrap"><table class="uc-tbl"><thead><tr>
        <th style="text-align:center;width:32px">#</th>
        <th>${TH?'ชื่อสารเคมี':'Chemical Name'}</th>
        <th>Barcode</th>
        <th>CAS</th>
        <th>${TH?'ตำแหน่ง':'Location'}</th>
        <th style="text-align:right">${TH?'ปริมาณ':'Qty'}</th>
        <th style="min-width:100px">${TH?'คงเหลือ':'Remaining'}</th>
        <th>${TH?'หมดอายุ':'Expiry'}</th>
    </tr></thead><tbody>`;
    containers.forEach((c, i) => { html += buildRow(c, i + 1); });
    html += '</tbody></table></div>';
    document.getElementById('mdBody').innerHTML = html;

    const avgPct = containers.length
        ? containers.reduce((s, c) => s + (parseFloat(c.remaining_percentage) || 0), 0) / containers.length : 0;
    document.getElementById('mdFooter').innerHTML = `
        <span><strong>${containers.length}</strong> ${TH?'ภาชนะ':'containers'}</span>
        <span>${TH?'เฉลี่ยคงเหลือ':'Avg remaining'}: <strong>${avgPct.toFixed(1)}%</strong></span>`;

    // Also render inline if list view
    if (currentView === 'list') renderDetail(userId, data);
}

function closeModal() {
    document.getElementById('ucModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('ucModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

loadData();
</script>
</body></html>
