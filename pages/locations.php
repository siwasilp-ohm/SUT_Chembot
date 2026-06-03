<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$isAdmin = ($user['role_level'] ?? $user['level'] ?? 0) >= 5;
$isManager = ($user['role_level'] ?? $user['level'] ?? 0) >= 3;
Layout::head('จัดการสถานที่จัดเก็บ');
?>
<body>
<?php Layout::sidebar('locations'); Layout::beginContent(); ?>

<!-- ═══════ Hero Banner ═══════ -->
<div class="loc-hero">
    <div class="loc-hero-ic"><i class="fas fa-map-marker-alt"></i></div>
    <div class="loc-hero-info">
        <h2>สถานที่จัดเก็บ</h2>
        <p>อาคาร &rarr; ชั้น &rarr; ห้อง &rarr; ตู้ &rarr; ชั้นวาง &rarr; ช่อง</p>
    </div>
    <div class="loc-hero-meta">
        <div class="loc-hero-c"><div class="v" id="heroBuildings">—</div><div class="lb">อาคาร</div></div>
        <div class="loc-hero-c"><div class="v" id="heroRooms">—</div><div class="lb">ห้อง</div></div>
        <div class="loc-hero-c"><div class="v" id="heroContainers">—</div><div class="lb">ภาชนะ</div></div>
        <div class="loc-hero-c" id="heroMyRoomsWrap" style="display:none"><div class="v" id="heroMyRooms" style="color:#a5b4fc">—</div><div class="lb" style="color:rgba(255,255,255,.55)">ห้องของฉัน</div></div>
    </div>
</div>

<!-- ═══════ Stats Row ═══════ -->
<div class="loc-stats" id="statsRow">
    <div class="loc-stat" onclick="showStatDetail('buildings')" style="--lc:#4338ca;--lb:#eef2ff">
        <div class="loc-si" style="background:#eef2ff;color:#4338ca"><i class="fas fa-building"></i></div>
        <div><div class="loc-sv" id="statBuildings">—</div><div class="loc-sl">อาคาร</div></div>
    </div>
    <div class="loc-stat" onclick="showStatDetail('rooms')" style="--lc:#0369a1;--lb:#e0f2fe">
        <div class="loc-si" style="background:#e0f2fe;color:#0369a1"><i class="fas fa-door-open"></i></div>
        <div><div class="loc-sv" id="statRooms">—</div><div class="loc-sl">ห้อง</div></div>
    </div>
    <div class="loc-stat" onclick="showStatDetail('cabinets')" style="--lc:#7c3aed;--lb:#f3e8ff">
        <div class="loc-si" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-archive"></i></div>
        <div><div class="loc-sv" id="statCabinets">—</div><div class="loc-sl">ตู้เก็บ</div></div>
    </div>
    <div class="loc-stat" onclick="showStatDetail('containers')" style="--lc:#16a34a;--lb:#dcfce7">
        <div class="loc-si" style="background:#dcfce7;color:#16a34a"><i class="fas fa-flask"></i></div>
        <div><div class="loc-sv" id="statContainers">—</div><div class="loc-sl">ภาชนะ</div></div>
    </div>
    <div class="loc-stat" id="statMyRoomsCard" onclick="showStatDetail('myrooms')" style="display:none;--lc:#6366f1;--lb:#eef2ff">
        <div class="loc-si" style="background:#eef2ff;color:#6366f1"><i class="fas fa-star"></i></div>
        <div><div class="loc-sv" id="statMyRooms" style="color:#6366f1">—</div><div class="loc-sl">ห้องของฉัน</div></div>
    </div>
</div>

<!-- ═══════ Toolbar ═══════ -->
<div class="loc-toolbar">
    <div class="loc-search">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="ค้นหาอาคาร, ห้อง, รหัส...">
    </div>
    <div class="loc-vw">
        <button onclick="setView('tree')" id="btnTree" title="มุมมองต้นไม้"><i class="fas fa-sitemap"></i></button>
        <button onclick="setView('grid')" id="btnGrid" title="มุมมองการ์ด"><i class="fas fa-th-large"></i></button>
        <button onclick="setView('table')" id="btnTable" title="มุมมองตาราง"><i class="fas fa-table"></i></button>
    </div>
    <?php if($isManager): ?>
    <button onclick="showAddModal()" class="loc-btn loc-btn-p"><i class="fas fa-plus"></i> เพิ่ม</button>
    <?php endif; ?>
</div>

<!-- ═══════ Breadcrumb ═══════ -->
<div id="breadcrumbBar" style="display:none;margin-bottom:14px"></div>

<!-- ═══════ Search Results ═══════ -->
<div id="searchResults" style="display:none;margin-bottom:14px"></div>

<!-- ═══════ Main Content Area ═══════ -->
<div id="mainContent"></div>

<!-- ═══════ Add/Edit Modal ═══════ -->
<div id="addModal" class="ci-modal-bg">
    <div class="ci-modal" style="max-width:540px">
        <div class="ci-modal-hdr">
            <h3 id="modalTitle"><i class="fas fa-plus-circle" style="margin-right:8px;opacity:.7"></i>เพิ่มรายการ</h3>
            <button class="ci-modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="ci-modal-body" id="modalBody" style="padding:0"></div>
    </div>
</div>

<!-- ═══════ Stat Detail Sheet ═══════ -->
<div id="statDetailOv" class="loc-sds-ov" onclick="if(event.target===this)closeStatDetail()">
    <div class="loc-sds">
        <div class="loc-sds-drag"></div>
        <div class="loc-sds-hdr" id="sdsHdr"></div>
        <div id="sdsSearchWrap" class="loc-sds-search" style="display:none">
            <i class="fas fa-search"></i>
            <input type="text" id="sdsSearchInp" placeholder="ค้นหาห้อง..." oninput="sdsFilterRooms(this.value)">
        </div>
        <div class="loc-sds-body" id="sdsBody"></div>
    </div>
</div>

<style>
:root{--loc-r:14px;--loc-rs:10px;--loc-sh:0 1px 6px rgba(0,0,0,.06);--loc-shm:0 4px 20px rgba(0,0,0,.09)}

/* ── Hero ── */
.loc-hero{background:linear-gradient(135deg,#1e1b4b 0%,#3730a3 55%,#6366f1 100%);border-radius:var(--loc-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.loc-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.loc-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;position:relative}
.loc-hero-info{position:relative}
.loc-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.loc-hero-info p{font-size:12px;opacity:.85;margin:0}
.loc-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0}
.loc-hero-c{text-align:center;position:relative}
.loc-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.loc-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}

/* ── Stats Row ── */
.loc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.loc-stat{background:#fff;border-radius:var(--loc-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--loc-sh);border:1px solid var(--border);transition:all .15s;cursor:pointer;user-select:none}
.loc-stat:hover{transform:translateY(-2px);box-shadow:var(--loc-shm)}
.loc-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.loc-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.loc-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Toolbar ── */
.loc-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}
.loc-search{flex:1;min-width:220px;position:relative}
.loc-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:var(--loc-rs);font-size:13px;background:#fff;color:var(--c1);transition:border .15s;box-sizing:border-box}
.loc-search input:focus{outline:none;border-color:#4338ca;box-shadow:0 0 0 3px rgba(67,56,202,.1)}
.loc-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:13px}
.loc-vw{display:flex;border:1.5px solid var(--border);border-radius:var(--loc-rs);overflow:hidden}
.loc-vw button{padding:7px 11px;border:none;background:#fff;color:var(--c3);cursor:pointer;font-size:12px;transition:all .12s;display:flex;align-items:center;gap:4px}
.loc-vw button+button{border-left:1px solid var(--border)}
.loc-vw button.active{background:#4338ca;color:#fff}
.loc-vw button:hover:not(.active){background:#f8fafc}
.loc-btn{padding:8px 16px;border:none;border-radius:var(--loc-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap}
.loc-btn-p{background:#4338ca;color:#fff}.loc-btn-p:hover{filter:brightness(1.08)}
.loc-btn-o{background:#fff;color:#4338ca;border:1.5px solid #4338ca}.loc-btn-o:hover{background:#4338ca;color:#fff}
.loc-btn-g{background:transparent;color:var(--c3);border:1.5px solid var(--border)}.loc-btn-g:hover{border-color:#4338ca;color:#4338ca}

/* ── Breadcrumb ── */
.loc-bc{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:13px;background:#fff;border:1px solid var(--border);border-radius:var(--loc-rs);padding:10px 16px;box-shadow:var(--loc-sh)}
.loc-bc a{color:#4338ca;text-decoration:none;display:flex;align-items:center;gap:4px}
.loc-bc a:hover{text-decoration:underline}
.loc-bc .sep{color:#cbd5e1;font-size:10px}
.loc-bc .cur{color:var(--c1);font-weight:600}

/* ── Panel (wraps tree/grid/table) ── */
.loc-panel{background:#fff;border:1.5px solid var(--border);border-radius:var(--loc-r);overflow:hidden;box-shadow:var(--loc-sh)}
.loc-panel-hd{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border);background:#f8fafc}
.loc-panel-hd-title{font-size:12px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px}
.loc-panel-hd-title i{color:#4338ca}

/* ── Tree View ── */
.loc-tree-list{padding:8px}
.loc-tree-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;cursor:pointer;transition:background .15s,border-color .15s;margin-bottom:2px;border:1px solid transparent}
.loc-tree-item:hover{background:#f5f7ff;border-color:#e0e7ff}
.loc-tree-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;position:relative}
.loc-tree-arr{color:#cbd5e1;font-size:10px;flex-shrink:0;transition:transform .2s}
.loc-tree-item:hover .loc-tree-arr{color:#4338ca;transform:translateX(2px)}
.loc-tree-name{font-size:13px;font-weight:500;color:var(--c1);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.loc-tree-badge{font-size:10px;padding:2px 8px;border-radius:8px;background:#f1f5f9;color:var(--c3);white-space:nowrap;font-weight:500}
.loc-tree-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.loc-tree-dot.ok{background:#22c55e}
.loc-tree-dot.maint{background:#f59e0b}
.loc-tree-dot.closed{background:#ef4444}

/* ── Grid View ── */
.loc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;padding:14px}
.loc-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--loc-r);overflow:hidden;cursor:pointer;transition:all .18s}
.loc-card:hover{border-color:#4338ca;box-shadow:var(--loc-shm);transform:translateY(-2px)}
.loc-card-hd{display:flex;align-items:flex-start;gap:10px;padding:14px 14px 0}
.loc-card-ic{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.loc-card-nm{font-size:13px;font-weight:700;color:var(--c1);line-height:1.3}
.loc-card-sub{font-size:10px;color:var(--c3);margin-top:2px}
.loc-card-bd{padding:10px 14px 14px}
.loc-card-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
.loc-card-stat{background:#f8fafc;border-radius:8px;padding:7px;text-align:center}
.loc-card-stat .v{font-size:18px;font-weight:800;color:#4338ca}
.loc-card-stat .l{font-size:9px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Table View ── */
.loc-tw{overflow-x:auto}
.loc-t{width:100%;border-collapse:collapse;font-size:12px}
.loc-t th{background:#f8fafc;padding:10px 12px;text-align:left;font-weight:700;color:var(--c3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);white-space:nowrap}
.loc-t td{padding:10px 12px;border-bottom:1px solid #f1f5f9;color:var(--c1);vertical-align:middle}
.loc-t tbody tr{transition:background .1s;cursor:pointer}
.loc-t tbody tr:hover td{background:#f5f7ff}

/* ── Status Badge ── */
.loc-status{display:inline-flex;align-items:center;gap:4px;font-size:10px;padding:3px 9px;border-radius:10px;font-weight:600}
.loc-status.ok{background:#dcfce7;color:#15803d}
.loc-status.maint{background:#fef9c3;color:#a16207}
.loc-status.closed{background:#fee2e2;color:#dc2626}

/* ── Slot Grid ── */
.loc-slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;padding:14px}
.loc-slot{border:2px solid #e2e8f0;border-radius:10px;padding:14px;text-align:center;background:#f8fafc;transition:all .15s}
.loc-slot.used{border-color:#22c55e;background:#f0fdf4}
.loc-slot-code{font-size:10px;color:var(--c3);margin-bottom:4px;font-weight:600;text-transform:uppercase}
.loc-slot-ic{font-size:22px;margin-bottom:6px}
.loc-slot-nm{font-size:11px;font-weight:600;color:var(--c1)}
.loc-slot-sub{font-size:10px;color:var(--c3)}
.loc-slot-empty{font-size:11px;color:#cbd5e1}

/* ── Search Results ── */
.loc-sr{background:#fff;border:1.5px solid var(--border);border-radius:var(--loc-r);overflow:hidden;box-shadow:var(--loc-sh)}
.loc-sr-hd{padding:10px 16px;border-bottom:1px solid var(--border);background:#f8fafc;font-size:11px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px}
.loc-sr-item{display:flex;align-items:center;gap:10px;padding:10px 16px;cursor:pointer;transition:background .12s;border-bottom:1px solid #f1f5f9}
.loc-sr-item:last-child{border-bottom:none}
.loc-sr-item:hover{background:#f5f7ff}
.loc-sr-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.loc-sr-nm{font-size:13px;font-weight:600;color:var(--c1)}
.loc-sr-sub{font-size:11px;color:var(--c3);margin-top:1px}
.loc-sr-tag{font-size:10px;padding:2px 8px;border-radius:8px;background:#eef2ff;color:#4338ca;font-weight:600;white-space:nowrap;flex-shrink:0}

/* ── Loading/Empty ── */
.loc-ld{display:flex;align-items:center;justify-content:center;padding:50px;color:var(--c3)}
.loc-empty{display:flex;flex-direction:column;align-items:center;padding:50px 24px;text-align:center;color:var(--c3)}
.loc-empty i{font-size:36px;opacity:.25;margin-bottom:12px;display:block}
.loc-empty p{font-size:13px}

/* ── Add-row button ── */
.loc-add-row{padding:10px 14px;border-top:1px dashed var(--border)}

/* ══════════════════════════════════
   Modal styles (unchanged / pro)
══════════════════════════════════ */
.modal-wizard{animation:modalSlideIn .25s ease}
@keyframes modalSlideIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.modal-type-picker{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;padding:24px}
.modal-type-card{display:flex;flex-direction:column;align-items:center;gap:10px;padding:20px 12px;border:2px solid #eee;border-radius:14px;cursor:pointer;transition:all .2s;background:#fafbfc}
.modal-type-card:hover{border-color:#4338ca;background:#eef2ff;transform:translateY(-2px);box-shadow:0 4px 12px rgba(67,56,202,.12)}
.modal-type-card.selected{border-color:#4338ca;background:#eef2ff;box-shadow:0 0 0 3px rgba(67,56,202,.15)}
.modal-type-card .type-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px}
.modal-type-card .type-label{font-size:13px;font-weight:600;color:#333}
.modal-type-card .type-desc{font-size:11px;color:#999;text-align:center;line-height:1.4}
.modal-type-card.disabled{opacity:.4;pointer-events:none}
.modal-form-wrap{padding:24px;animation:modalSlideIn .2s ease}
.modal-form-header{display:flex;align-items:center;gap:14px;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid #f0f0f0}
.modal-form-header .form-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.modal-form-header .form-title{font-size:16px;font-weight:700;color:#333}
.modal-form-header .form-desc{font-size:12px;color:#999;margin-top:2px}
.modal-field{margin-bottom:18px;position:relative}
.modal-field label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;letter-spacing:.3px}
.modal-field label .req{color:#ef4444;margin-left:2px}
.modal-field input,.modal-field select,.modal-field textarea{width:100%;padding:10px 14px;border:1.5px solid #e0e3e8;border-radius:10px;font-size:14px;color:#333;background:#fafbfc;transition:all .2s;outline:none;box-sizing:border-box}
.modal-field input:focus,.modal-field select:focus,.modal-field textarea:focus{border-color:#4338ca;background:#fff;box-shadow:0 0 0 3px rgba(67,56,202,.1)}
.modal-field input::placeholder{color:#bbb}
.modal-field .field-hint{font-size:11px;color:#aaa;margin-top:4px}
.modal-field .field-icon{position:absolute;right:14px;top:34px;color:#ccc;font-size:13px;pointer-events:none}
.modal-field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.modal-select-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.modal-select-opt{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1.5px solid #e0e3e8;border-radius:10px;cursor:pointer;transition:all .15s;font-size:13px;background:#fafbfc}
.modal-select-opt:hover{border-color:#4338ca;background:#eef2ff}
.modal-select-opt.active{border-color:#4338ca;background:#eef2ff;box-shadow:0 0 0 3px rgba(67,56,202,.1)}
.modal-select-opt .opt-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.modal-select-opt .opt-label{font-weight:500;color:#333}
.modal-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:16px 24px;border-top:1px solid #f0f0f0;background:#fafbfc;border-radius:0 0 6px 6px}
.modal-footer .back-link{display:flex;align-items:center;gap:6px;font-size:13px;color:#888;cursor:pointer;transition:color .15s;background:none;border:none;padding:0}
.modal-footer .back-link:hover{color:#4338ca}
.modal-footer .btn-submit{display:flex;align-items:center;gap:8px;padding:10px 28px;border-radius:10px;font-size:14px;font-weight:600;border:none;cursor:pointer;transition:all .2s}
.modal-footer .btn-submit.primary{background:#4338ca;color:#fff;box-shadow:0 2px 8px rgba(67,56,202,.25)}
.modal-footer .btn-submit.primary:hover{background:#3730a3;transform:translateY(-1px);box-shadow:0 4px 12px rgba(67,56,202,.3)}
.modal-footer .btn-submit:disabled{opacity:.5;cursor:not-allowed;transform:none!important}
.modal-footer .btn-cancel{background:none;border:1.5px solid #e0e3e8;color:#666;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s}
.modal-footer .btn-cancel:hover{border-color:#ccc;background:#f5f5f5}
.modal-success{display:flex;flex-direction:column;align-items:center;padding:40px 24px;animation:modalSlideIn .3s ease}
.modal-success .success-icon{width:72px;height:72px;border-radius:50%;background:#ede9fe;display:flex;align-items:center;justify-content:center;font-size:32px;color:#4338ca;margin-bottom:16px;animation:successPop .4s ease}
@keyframes successPop{0%{transform:scale(0)}50%{transform:scale(1.15)}100%{transform:scale(1)}}
.modal-success h3{font-size:18px;font-weight:700;color:#333;margin-bottom:6px}
.modal-success p{font-size:13px;color:#999}

/* ── Responsive ── */
@media(max-width:768px){
    .loc-hero{padding:16px 18px;gap:12px}
    .loc-hero-ic{width:44px;height:44px;font-size:18px}
    .loc-hero-info h2{font-size:16px}
    .loc-hero-meta{gap:14px}
    .loc-hero-c .v{font-size:20px}
    .loc-stats{grid-template-columns:repeat(3,1fr)}
    .loc-grid{grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}
    .modal-type-picker{grid-template-columns:1fr 1fr;padding:16px}
    .modal-field-row{grid-template-columns:1fr}
    .modal-select-grid{grid-template-columns:1fr}
}
@media(max-width:480px){
    .loc-hero-meta{display:none}
    .loc-stats{grid-template-columns:repeat(2,1fr)}
    .loc-grid{grid-template-columns:1fr}
    .loc-tree-badge{display:none}
    .modal-type-picker{grid-template-columns:1fr 1fr;gap:8px}
    .modal-type-card{padding:14px 8px}
    .modal-type-card .type-desc{display:none}
    .modal-form-wrap{padding:16px}
}

/* ── Room Manage Banner (cabinet level) ── */
.loc-room-mgmt{background:linear-gradient(135deg,#f0f4ff,#e8edff);border:1.5px solid #c7d2fe;border-radius:var(--loc-rs);padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.loc-room-mgmt-ic{width:36px;height:36px;border-radius:9px;background:#4338ca;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.loc-room-mgmt-body{flex:1;min-width:0}
.loc-room-mgmt-title{font-size:12px;font-weight:700;color:#3730a3}
.loc-room-mgmt-sub{font-size:10px;color:#6366f1;margin-top:1px;display:flex;gap:8px;flex-wrap:wrap}
.loc-room-mgmt-stat{display:inline-flex;align-items:center;gap:3px}

/* ── Enhanced Slot Cards ── */
.loc-slot{border:2px solid #e2e8f0;border-radius:12px;padding:0;text-align:left;background:#fff;transition:all .18s;overflow:hidden;display:flex;flex-direction:column}
.loc-slot.used{border-color:#22c55e}
.loc-slot.expiring{border-color:#f59e0b}
.loc-slot.expired{border-color:#ef4444}
.loc-slot-hdr{display:flex;align-items:center;justify-content:space-between;padding:8px 10px 0;gap:4px}
.loc-slot-code{font-size:9px;color:var(--c3);font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.loc-slot-exp-tag{font-size:8px;padding:1px 6px;border-radius:5px;font-weight:700;white-space:nowrap}
.loc-slot-exp-tag.warn{background:#fef9c3;color:#a16207}
.loc-slot-exp-tag.danger{background:#fee2e2;color:#dc2626}
.loc-slot-body{padding:8px 10px 10px;flex:1}
.loc-slot-ic{font-size:20px;margin-bottom:5px;display:block;line-height:1}
.loc-slot-nm{font-size:11px;font-weight:700;color:var(--c1);line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.loc-slot-bc{font-size:9px;color:var(--c3);margin-top:3px;font-family:monospace;letter-spacing:.2px}
.loc-slot-qty-bar{height:3px;background:#e2e8f0;border-radius:2px;margin:6px 0 4px;overflow:hidden}
.loc-slot-qty-fill{height:100%;border-radius:2px;background:#22c55e;transition:width .3s}
.loc-slot-qty{font-size:9px;color:#4338ca;font-weight:600}
.loc-slot-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:18px 10px;text-align:center;min-height:80px}
.loc-slot-empty-ic{font-size:20px;color:#e2e8f0;margin-bottom:5px}
.loc-slot-empty-lbl{font-size:10px;color:#cbd5e1;font-weight:500}

/* ── Shelf Summary Strip ── */
.loc-shelf-strip{display:flex;align-items:center;gap:6px;padding:6px 10px;background:#f8fafc;border-top:1px dashed #e2e8f0;font-size:10px;color:var(--c3);flex-wrap:wrap}

/* ── Myroom Sync Enrichment ── */
.loc-ctr-badge{display:inline-flex;align-items:center;gap:3px;font-size:10px;padding:2px 7px;border-radius:7px;font-weight:600;white-space:nowrap}
.loc-ctr-badge i{font-size:8px;flex-shrink:0}
.loc-ctr-badge.total{background:#e0f2fe;color:#0369a1}
.loc-ctr-badge.warn{background:#fef9c3;color:#a16207}
.loc-ctr-badge.danger{background:#fee2e2;color:#dc2626}
.loc-my-room-tag{display:inline-flex;align-items:center;gap:4px;font-size:10px;padding:3px 9px;border-radius:8px;background:linear-gradient(135deg,#4338ca,#818cf8);color:#fff;font-weight:700;white-space:nowrap;flex-shrink:0}
.loc-goto-btn{display:inline-flex;align-items:center;gap:5px;font-size:10px;padding:5px 11px;border-radius:8px;background:#4338ca;color:#fff;border:none;cursor:pointer;font-weight:700;font-family:inherit;transition:all .15s;white-space:nowrap;text-decoration:none;flex-shrink:0}
.loc-goto-btn:hover{background:#3730a3;box-shadow:0 2px 8px rgba(67,56,202,.3)}
.loc-goto-btn-light{display:inline-flex;align-items:center;gap:5px;font-size:11px;padding:7px 14px;border-radius:9px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);color:#fff;cursor:pointer;font-weight:700;font-family:inherit;transition:all .15s;white-space:nowrap;text-decoration:none;flex-shrink:0}
.loc-goto-btn-light:hover{background:rgba(255,255,255,.28);border-color:rgba(255,255,255,.5)}

/* Tree room enrichment */
.loc-tree-enrich{display:flex;align-items:center;gap:5px;flex-shrink:0;flex-wrap:nowrap}
.loc-tree-item-mine{background:#fafbff;border-color:#e0e7ff!important}
.loc-tree-item-mine:hover{background:#f0f4ff!important}

/* Card mine highlight */
.loc-card-mine{border-color:#c7d2fe!important}
.loc-card-mine:hover{border-color:#4338ca!important}
.loc-card-ft{padding:8px 14px 10px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}

/* Manager avatars */
.loc-mgr-row{display:flex;align-items:center}
.loc-mgr-av{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#4338ca,#818cf8);color:#fff;font-size:9px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.12);text-transform:uppercase;overflow:hidden}
.loc-mgr-av img{width:100%;height:100%;object-fit:cover}
.loc-mgr-av+.loc-mgr-av{margin-left:-6px}

/* My Rooms band */
.loc-myrooms-band{background:linear-gradient(135deg,#3730a3 0%,#4338ca 50%,#6366f1 100%);border-radius:var(--loc-rs);padding:14px 18px;margin-bottom:14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;color:#fff;box-shadow:0 4px 16px rgba(67,56,202,.28)}
.loc-myrooms-label{flex-shrink:0}
.loc-myrooms-label .lb{font-size:9px;opacity:.65;text-transform:uppercase;letter-spacing:.6px;margin-bottom:2px}
.loc-myrooms-label .ct{font-size:22px;font-weight:900;line-height:1}
.loc-myrooms-chips{display:flex;gap:7px;flex-wrap:wrap;flex:1;min-width:0}
.loc-myrooms-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.22);border-radius:9px;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap;font-family:inherit;color:#fff}
.loc-myrooms-chip:hover{background:rgba(255,255,255,.27);border-color:rgba(255,255,255,.45)}
.loc-myrooms-chip .dot{width:7px;height:7px;border-radius:50%;background:#4ade80;flex-shrink:0}
.loc-myrooms-chip .dot.warn{background:#fbbf24}
.loc-myrooms-chip .dot.danger{background:#f87171}

@media(max-width:640px){
    .loc-tree-enrich{display:none}
    .loc-myrooms-band{padding:12px 14px;gap:10px}
    .loc-myrooms-chips{gap:5px}
}
@media(max-width:480px){
    .loc-card-ft{gap:6px}
    .loc-myrooms-label{display:none}
}

/* ── Stat Detail Sheet ── */
.loc-sds-ov{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:300;opacity:0;pointer-events:none;transition:opacity .22s;backdrop-filter:blur(3px)}
.loc-sds-ov.open{opacity:1;pointer-events:all}
.loc-sds{position:fixed;bottom:0;left:0;right:0;background:#fff;border-radius:20px 20px 0 0;z-index:301;transform:translateY(100%);transition:transform .32s cubic-bezier(.32,1,.36,1);max-height:82vh;display:flex;flex-direction:column;box-shadow:0 -8px 40px rgba(0,0,0,.14)}
.loc-sds-ov.open .loc-sds{transform:translateY(0)}
.loc-sds-drag{width:40px;height:4px;border-radius:2px;background:#e2e8f0;margin:12px auto 4px;flex-shrink:0}
.loc-sds-hdr{padding:12px 18px 14px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px;flex-shrink:0}
.loc-sds-hdr-ic{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.loc-sds-hdr-title{font-size:15px;font-weight:800;color:var(--c1)}
.loc-sds-hdr-cnt{font-size:10px;color:var(--c3);margin-top:2px}
.loc-sds-search{padding:10px 16px;border-bottom:1px solid #f1f5f9;position:relative;flex-shrink:0}
.loc-sds-search input{width:100%;padding:8px 12px 8px 34px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:#f8fafc;outline:none;box-sizing:border-box;color:var(--c1);font-family:inherit}
.loc-sds-search input:focus{border-color:#4338ca;background:#fff;box-shadow:0 0 0 3px rgba(67,56,202,.1)}
.loc-sds-search i{position:absolute;left:27px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:12px;pointer-events:none}
.loc-sds-body{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch}
.loc-sds-section{padding:8px 18px 4px;font-size:9px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.7px;background:#f8fafc;border-bottom:1px solid #f1f5f9}
.loc-sds-row{display:flex;align-items:center;gap:10px;padding:11px 18px;cursor:pointer;transition:background .12s;border-bottom:1px solid #f9fafb}
.loc-sds-row:last-child{border-bottom:none}
.loc-sds-row:hover{background:#f5f7ff}
.loc-sds-row:active{background:#eef2ff}
.loc-sds-row-ic{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.loc-sds-row-nm{font-size:13px;font-weight:600;color:var(--c1);line-height:1.3}
.loc-sds-row-sub{font-size:10px;color:var(--c3);margin-top:1px}
.loc-sds-row-right{text-align:right;flex-shrink:0;min-width:50px}
.loc-sds-row-val{font-size:17px;font-weight:900;line-height:1;color:#4338ca}
.loc-sds-row-vl{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.3px;margin-top:1px}
.loc-sds-stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:14px 18px;border-bottom:1px solid #f1f5f9}
.loc-sds-stat-card{background:#f8fafc;border-radius:12px;padding:12px 8px;text-align:center}
.loc-sds-stat-card .sv{font-size:22px;font-weight:900;line-height:1;color:#4338ca}
.loc-sds-stat-card .sl{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.3px;margin-top:4px}
.loc-sds-empty{display:flex;flex-direction:column;align-items:center;padding:48px 24px;color:var(--c3)}
.loc-sds-empty i{font-size:36px;opacity:.18;margin-bottom:12px;display:block}
.loc-sds-empty p{font-size:13px;margin:0}
.loc-sds-close{background:none;border:none;cursor:pointer;color:var(--c3);font-size:15px;padding:6px;border-radius:8px;transition:all .12s;line-height:1;flex-shrink:0}
.loc-sds-close:hover{color:var(--c1);background:#f1f5f9}
.loc-sds-footer{padding:14px 18px;border-top:1px solid #f1f5f9;text-align:center;flex-shrink:0;padding-bottom:max(14px,env(safe-area-inset-bottom))}
</style>

<?php Layout::endContent(); ?>

<script>
const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const IS_MANAGER = <?php echo $isManager ? 'true' : 'false'; ?>;
let currentView = localStorage.getItem('locView') || 'tree';
let navStack = [];
let buildingsData = [];
let mrAllRoomsMap = {};
let mrMyRoomsMap  = {};

// ═══════ Init ═══════
async function init() {
    loadStats();
    loadBuildings();
    loadMyroomData();
    setView(currentView, true);
    setupSearch();
}

// ═══════ Stats ═══════
async function loadStats() {
    try {
        const d = await apiFetch('/v1/api/locations.php?action=stats');
        if (d.success) {
            document.getElementById('statBuildings').textContent = d.data.buildings;
            document.getElementById('statRooms').textContent = d.data.rooms;
            document.getElementById('statCabinets').textContent = d.data.cabinets;
            document.getElementById('statContainers').textContent = d.data.containers;
            document.getElementById('heroBuildings').textContent = d.data.buildings;
            document.getElementById('heroRooms').textContent = d.data.rooms;
            document.getElementById('heroContainers').textContent = d.data.containers;
        }
    } catch(e) { console.error(e); }
}

// ═══════ Myroom Data Sync ═══════
async function loadMyroomData() {
    try {
        const [allRes, myRes] = await Promise.all([
            apiFetch('/v1/api/myroom.php?action=all_rooms'),
            apiFetch('/v1/api/myroom.php?action=my_rooms')
        ]);
        if (allRes.success) {
            mrAllRoomsMap = {};
            (allRes.data || []).forEach(r => { mrAllRoomsMap[r.id] = r; });
        }
        if (myRes.success) {
            mrMyRoomsMap = {};
            (myRes.data || []).forEach(r => { mrMyRoomsMap[r.room_id] = r; });
            const cnt = Object.keys(mrMyRoomsMap).length;
            if (cnt) {
                const hw = document.getElementById('heroMyRoomsWrap');
                const hv = document.getElementById('heroMyRooms');
                const sc = document.getElementById('statMyRoomsCard');
                const sv = document.getElementById('statMyRooms');
                if (hw) { hw.style.display = ''; if (hv) hv.textContent = cnt; }
                if (sc) { sc.style.display = ''; if (sv) sv.textContent = cnt; }
            }
        }
        // Re-render rooms if already at floor level so enrichment shows
        const level = navStack.length > 0 ? navStack[navStack.length - 1] : null;
        if (level && level.type === 'floor') renderCurrentLevel();
    } catch(e) { console.error('myroom sync:', e); }
}

// ═══════ View Toggle ═══════
function setView(v, noReload) {
    currentView = v;
    localStorage.setItem('locView', v);
    document.querySelectorAll('.loc-vw button').forEach(b => b.classList.remove('active'));
    const btn = document.getElementById('btn' + v.charAt(0).toUpperCase() + v.slice(1));
    if (btn) btn.classList.add('active');
    if (!noReload) renderCurrentLevel();
}

// ═══════ Navigation ═══════
function navigateTo(type, id, name, extra) {
    navStack.push({type, id, name, ...extra});
    renderCurrentLevel();
    updateBreadcrumb();
}

function navigateBack(toIndex) {
    navStack = navStack.slice(0, toIndex + 1);
    renderCurrentLevel();
    updateBreadcrumb();
}

function navigateHome() {
    navStack = [];
    renderCurrentLevel();
    updateBreadcrumb();
}

function updateBreadcrumb() {
    const bar = document.getElementById('breadcrumbBar');
    if (navStack.length === 0) { bar.style.display = 'none'; return; }
    bar.style.display = 'block';
    let html = '<div class="loc-bc"><a href="javascript:navigateHome()"><i class="fas fa-home"></i> อาคารทั้งหมด</a>';
    navStack.forEach((item, i) => {
        html += '<span class="sep"><i class="fas fa-chevron-right"></i></span>';
        if (i < navStack.length - 1) {
            html += `<a href="javascript:navigateBack(${i})">${esc(item.name)}</a>`;
        } else {
            html += `<span class="cur">${esc(item.name)}</span>`;
        }
    });
    html += '</div>';
    bar.innerHTML = html;
}

// ═══════ Load Buildings ═══════
async function loadBuildings() {
    try {
        const d = await apiFetch('/v1/api/locations.php?action=buildings');
        if (d.success) {
            buildingsData = d.data;
            renderCurrentLevel();
        }
    } catch(e) { console.error(e); }
}

// ═══════ Render Current Level ═══════
function renderCurrentLevel() {
    const mc = document.getElementById('mainContent');
    const level = navStack.length > 0 ? navStack[navStack.length - 1] : null;
    if (!level) {
        renderBuildings(mc);
    } else if (level.type === 'building') {
        loadAndRenderFloors(mc, level.id);
    } else if (level.type === 'floor') {
        loadAndRenderRooms(mc, level.buildingId, level.floor);
    } else if (level.type === 'room') {
        loadAndRenderCabinets(mc, level.id);
    } else if (level.type === 'cabinet') {
        loadAndRenderShelves(mc, level.id);
    } else if (level.type === 'shelf') {
        loadAndRenderSlots(mc, level.id);
    }
}

// ═══════ Render Buildings ═══════
function renderBuildings(el) {
    const data = buildingsData;
    if (!data.length) { el.innerHTML = emptyState('fas fa-building', 'ยังไม่มีข้อมูลอาคาร'); return; }

    if (currentView === 'tree') {
        el.innerHTML = `<div class="loc-panel">
            <div class="loc-panel-hd">
                <div class="loc-panel-hd-title"><i class="fas fa-building"></i> อาคารทั้งหมด</div>
                <span style="font-size:11px;color:var(--c3)">${data.length} อาคาร</span>
            </div>
            <div class="loc-tree-list">
                ${data.map(b => `
                    <div class="loc-tree-item" onclick="navigateTo('building',${b.id},'${esc(b.shortname||b.name)}')">
                        <div class="loc-tree-ic" style="background:#eef2ff;color:#4338ca"><i class="fas fa-building"></i></div>
                        <div class="loc-tree-name">${esc(b.name)}</div>
                        ${b.shortname ? `<span class="loc-tree-badge">${esc(b.shortname)}</span>` : ''}
                        <span class="loc-tree-badge">${b.floor_count} ชั้น</span>
                        <span class="loc-tree-badge">${b.room_count} ห้อง</span>
                        <i class="fas fa-chevron-right loc-tree-arr"></i>
                    </div>
                `).join('')}
            </div>
        </div>`;
    } else if (currentView === 'grid') {
        el.innerHTML = `<div class="loc-grid">${data.map(b => `
            <div class="loc-card" onclick="navigateTo('building',${b.id},'${esc(b.shortname||b.name)}')">
                <div class="loc-card-hd">
                    <div class="loc-card-ic" style="background:#eef2ff;color:#4338ca"><i class="fas fa-building"></i></div>
                    <div style="flex:1;min-width:0">
                        <div class="loc-card-nm">${esc(b.name)}</div>
                        <div class="loc-card-sub">${esc(b.shortname||'')}${b.name_en ? ' · '+esc(b.name_en) : ''}</div>
                    </div>
                </div>
                <div class="loc-card-bd">
                    <div class="loc-card-stats">
                        <div class="loc-card-stat"><div class="v">${b.floor_count}</div><div class="l">ชั้น</div></div>
                        <div class="loc-card-stat"><div class="v">${b.room_count}</div><div class="l">ห้อง</div></div>
                    </div>
                </div>
            </div>`).join('')}</div>`;
    } else {
        el.innerHTML = `<div class="loc-panel"><div class="loc-tw"><table class="loc-t">
            <thead><tr><th>รหัส</th><th>ชื่ออาคาร</th><th>ชื่อภาษาอังกฤษ</th><th style="text-align:center">ชั้น</th><th style="text-align:center">ห้อง</th><th style="text-align:center">ตู้</th></tr></thead>
            <tbody>${data.map(b => `
                <tr onclick="navigateTo('building',${b.id},'${esc(b.shortname||b.name)}')">
                    <td><span style="font-size:10px;padding:2px 8px;border-radius:6px;background:#eef2ff;color:#4338ca;font-weight:700">${esc(b.shortname||b.code||'—')}</span></td>
                    <td style="font-weight:600">${esc(b.name)}</td>
                    <td style="color:var(--c3)">${esc(b.name_en||'—')}</td>
                    <td style="text-align:center">${b.floor_count}</td>
                    <td style="text-align:center;font-weight:700">${b.room_count}</td>
                    <td style="text-align:center">${b.cabinet_count}</td>
                </tr>`).join('')}
            </tbody></table></div></div>`;
    }
}

// ═══════ Render Floors ═══════
async function loadAndRenderFloors(el, buildingId) {
    el.innerHTML = loading();
    try {
        const d = await apiFetch(`/v1/api/locations.php?action=floors&building_id=${buildingId}`);
        if (!d.success || !d.data.length) { el.innerHTML = emptyState('fas fa-layer-group','ไม่พบข้อมูลชั้น'); return; }
        const floors = d.data;
        const bid = buildingId;

        if (currentView === 'tree') {
            el.innerHTML = `<div class="loc-panel">
                <div class="loc-panel-hd">
                    <div class="loc-panel-hd-title"><i class="fas fa-layer-group"></i> ชั้นในอาคาร</div>
                    <span style="font-size:11px;color:var(--c3)">${floors.length} ชั้น</span>
                </div>
                <div class="loc-tree-list">
                    ${floors.map(f => `
                        <div class="loc-tree-item" onclick="navigateTo('floor',${f.floor},'ชั้น ${f.floor}',{buildingId:${bid},floor:${f.floor}})">
                            <div class="loc-tree-ic" style="background:#fff7ed;color:#c2410c"><i class="fas fa-layer-group"></i></div>
                            <div class="loc-tree-name">ชั้นที่ ${f.floor}</div>
                            <span class="loc-tree-badge">${f.room_count} ห้อง</span>
                            <span class="loc-tree-badge" style="background:#dcfce7;color:#15803d">${f.active_rooms} พร้อม</span>
                            ${f.maintenance_rooms > 0 ? `<span class="loc-tree-badge" style="background:#fef9c3;color:#a16207">${f.maintenance_rooms} ปรับปรุง</span>` : ''}
                            <i class="fas fa-chevron-right loc-tree-arr"></i>
                        </div>
                    `).join('')}
                </div>
            </div>`;
        } else if (currentView === 'grid') {
            el.innerHTML = `<div class="loc-grid">${floors.map(f => `
                <div class="loc-card" onclick="navigateTo('floor',${f.floor},'ชั้น ${f.floor}',{buildingId:${bid},floor:${f.floor}})">
                    <div class="loc-card-hd">
                        <div class="loc-card-ic" style="background:#fff7ed;color:#c2410c"><i class="fas fa-layer-group"></i></div>
                        <div><div class="loc-card-nm">ชั้นที่ ${f.floor}</div><div class="loc-card-sub">${f.room_count} ห้อง</div></div>
                    </div>
                    <div class="loc-card-bd">
                        <div class="loc-card-stats">
                            <div class="loc-card-stat"><div class="v" style="color:#15803d">${f.active_rooms}</div><div class="l">พร้อมใช้</div></div>
                            <div class="loc-card-stat"><div class="v" style="color:#a16207">${f.maintenance_rooms||0}</div><div class="l">ปรับปรุง</div></div>
                        </div>
                    </div>
                </div>`).join('')}</div>`;
        } else {
            el.innerHTML = `<div class="loc-panel"><div class="loc-tw"><table class="loc-t">
                <thead><tr><th>ชั้น</th><th style="text-align:center">ห้องทั้งหมด</th><th style="text-align:center">พร้อมใช้</th><th style="text-align:center">ปรับปรุง</th><th style="text-align:center">ตู้</th></tr></thead>
                <tbody>${floors.map(f => `
                    <tr onclick="navigateTo('floor',${f.floor},'ชั้น ${f.floor}',{buildingId:${bid},floor:${f.floor}})">
                        <td style="font-weight:700"><i class="fas fa-layer-group" style="color:#c2410c;margin-right:6px"></i>ชั้นที่ ${f.floor}</td>
                        <td style="text-align:center;font-weight:700">${f.room_count}</td>
                        <td style="text-align:center"><span class="loc-status ok">${f.active_rooms}</span></td>
                        <td style="text-align:center">${f.maintenance_rooms > 0 ? `<span class="loc-status maint">${f.maintenance_rooms}</span>` : '—'}</td>
                        <td style="text-align:center">${f.cabinet_count}</td>
                    </tr>`).join('')}
                </tbody></table></div></div>`;
        }
    } catch(e) { el.innerHTML = emptyState('fas fa-exclamation-triangle', e.message); }
}

// ═══════ Render Rooms ═══════
async function loadAndRenderRooms(el, buildingId, floor) {
    el.innerHTML = loading();
    try {
        const d = await apiFetch(`/v1/api/locations.php?action=rooms&building_id=${buildingId}&floor=${floor}`);
        if (!d.success || !d.data.length) { el.innerHTML = emptyState('fas fa-door-open','ไม่พบห้องในชั้นนี้'); return; }
        const rooms = d.data;
        const myInView = rooms.filter(r => mrAllRoomsMap[r.id]?.access_status === 'has_access');
        const band = myRoomsBand(myInView);

        if (currentView === 'tree') {
            el.innerHTML = band + `<div class="loc-panel">
                <div class="loc-panel-hd">
                    <div class="loc-panel-hd-title"><i class="fas fa-door-open"></i> ห้องในชั้นนี้</div>
                    <span style="font-size:11px;color:var(--c3)">${rooms.length} ห้อง</span>
                </div>
                <div class="loc-tree-list">
                    ${rooms.map(r => {
                        const mr = mrAllRoomsMap[r.id];
                        const my = mrMyRoomsMap[r.id];
                        const isMine = mr?.access_status === 'has_access';
                        return `<div class="loc-tree-item${isMine ? ' loc-tree-item-mine' : ''}" onclick="navigateTo('room',${r.id},'${esc(r.name)}')">
                            <div class="loc-tree-ic" style="background:${isMine?'#eef2ff':'#e0f2fe'};color:${isMine?'#4338ca':'#0369a1'}"><i class="fas fa-door-open"></i></div>
                            <div class="loc-tree-name">${esc(r.name)}</div>
                            ${r.code ? `<span class="loc-tree-badge">${esc(r.code)}</span>` : ''}
                            ${statusDot(r.status_text)}
                            ${r.cabinet_count > 0 ? `<span class="loc-tree-badge">${r.cabinet_count} ตู้</span>` : ''}
                            ${enrichTreeRoom(mr, my, isMine)}
                            <i class="fas fa-chevron-right loc-tree-arr"></i>
                        </div>`;
                    }).join('')}
                </div>
            </div>`;
        } else if (currentView === 'grid') {
            el.innerHTML = band + `<div class="loc-grid">${rooms.map(r => {
                const mr = mrAllRoomsMap[r.id];
                const my = mrMyRoomsMap[r.id];
                const isMine = mr?.access_status === 'has_access';
                return `<div class="loc-card${isMine ? ' loc-card-mine' : ''}" onclick="navigateTo('room',${r.id},'${esc(r.name)}')">
                    <div class="loc-card-hd">
                        <div class="loc-card-ic" style="background:${isMine?'#eef2ff':'#e0f2fe'};color:${isMine?'#4338ca':'#0369a1'}"><i class="fas fa-door-open"></i></div>
                        <div style="flex:1;min-width:0">
                            <div class="loc-card-nm" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.name)}</div>
                            <div class="loc-card-sub">${esc(r.code||'')}${r.area_sqm?' · '+r.area_sqm+' ตร.ม.':''}</div>
                        </div>
                        ${isMine ? '<span class="loc-my-room-tag"><i class="fas fa-star" style="font-size:8px"></i>ของฉัน</span>' : statusBadge(r.status_text)}
                    </div>
                    <div class="loc-card-bd">
                        ${enrichCardStats(mr, my, r)}
                    </div>
                    ${enrichCardFooter(mr, my, isMine)}
                </div>`;
            }).join('')}</div>`;
        } else {
            el.innerHTML = band + `<div class="loc-panel"><div class="loc-tw"><table class="loc-t">
                <thead><tr><th>รหัส</th><th>ชื่อห้อง</th><th>สถานะ</th><th style="text-align:center">ตู้</th><th style="text-align:center">ภาชนะ</th><th>ผู้ดูแล</th><th></th></tr></thead>
                <tbody>${rooms.map(r => {
                    const mr = mrAllRoomsMap[r.id];
                    const my = mrMyRoomsMap[r.id];
                    const isMine = mr?.access_status === 'has_access';
                    return `<tr onclick="navigateTo('room',${r.id},'${esc(r.name)}')" style="${isMine?'background:#fafbff':''}">
                        <td><span style="font-size:10px;padding:2px 8px;border-radius:6px;background:${isMine?'#eef2ff':'#e0f2fe'};color:${isMine?'#4338ca':'#0369a1'};font-weight:700">${esc(r.code||'—')}</span></td>
                        <td style="font-weight:600;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${isMine?'<i class="fas fa-star" style="color:#6366f1;font-size:9px;margin-right:5px"></i>':''}${esc(r.name)}</td>
                        <td>${statusBadge(r.status_text)}</td>
                        <td style="text-align:center">${r.cabinet_count}</td>
                        <td style="text-align:center">${enrichTableCtr(mr, my)}</td>
                        <td style="max-width:160px">${enrichTableMgrs(mr)}</td>
                        <td style="text-align:center;white-space:nowrap">${isMine ? `<a href="/v1/pages/myroom.php" class="loc-goto-btn" onclick="event.stopPropagation()"><i class="fas fa-arrow-right" style="font-size:9px"></i>จัดการ</a>` : ''}</td>
                    </tr>`;
                }).join('')}
                </tbody></table></div></div>`;
        }
    } catch(e) { el.innerHTML = emptyState('fas fa-exclamation-triangle', e.message); }
}

// ═══════ Render Cabinets ═══════
async function loadAndRenderCabinets(el, roomId) {
    el.innerHTML = loading();
    try {
        const d = await apiFetch(`/v1/api/locations.php?action=cabinets&room_id=${roomId}`);
        const mr = mrAllRoomsMap[roomId];
        const my = mrMyRoomsMap[roomId];
        const isMine = mr?.access_status === 'has_access';
        const band = isMine ? roomManageBand(mr, my) : '';

        if (!d.success || !d.data.length) {
            el.innerHTML = band + emptyState('fas fa-archive', 'ยังไม่มีตู้เก็บในห้องนี้') +
                (IS_MANAGER ? `<div style="text-align:center;margin-top:12px"><button onclick="showAddModal('cabinet',{room_id:${roomId}})" class="loc-btn loc-btn-p"><i class="fas fa-plus"></i> เพิ่มตู้เก็บ</button></div>` : '');
            return;
        }
        const cabs = d.data;

        if (currentView === 'tree') {
            el.innerHTML = band + `<div class="loc-panel">
                <div class="loc-panel-hd">
                    <div class="loc-panel-hd-title"><i class="fas fa-archive"></i> ตู้เก็บในห้องนี้</div>
                    <span style="font-size:11px;color:var(--c3)">${cabs.length} ตู้</span>
                </div>
                <div class="loc-tree-list">
                    ${cabs.map(c => {
                        const hasDanger = c.expired_count > 0;
                        const hasWarn = !hasDanger && c.expiring_count > 0;
                        return `<div class="loc-tree-item" onclick="navigateTo('cabinet',${c.id},'${esc(c.name)}')">
                            <div class="loc-tree-ic" style="background:${cabinetTypeBg(c.type)};color:${cabinetTypeColor(c.type)}">${cabinetTypeIconEl(c.type)}</div>
                            <div class="loc-tree-name">${esc(c.name)}</div>
                            ${c.code ? `<span class="loc-tree-badge">${esc(c.code)}</span>` : ''}
                            <span class="loc-tree-badge">${cabinetTypeLabel(c.type)}</span>
                            <span class="loc-tree-badge">${c.shelf_count} ชั้นวาง</span>
                            ${c.container_count > 0 ? `<span class="loc-tree-badge" style="background:#dcfce7;color:#15803d"><i class="fas fa-flask" style="font-size:8px"></i> ${c.container_count}</span>` : ''}
                            ${hasDanger ? `<span class="loc-ctr-badge danger"><i class="fas fa-exclamation-triangle"></i>${c.expired_count} หมดอายุ</span>` : ''}
                            ${hasWarn ? `<span class="loc-ctr-badge warn"><i class="fas fa-clock"></i>${c.expiring_count} ใกล้หมด</span>` : ''}
                            <i class="fas fa-chevron-right loc-tree-arr"></i>
                        </div>`;
                    }).join('')}
                </div>
                ${IS_MANAGER ? `<div class="loc-add-row"><button onclick="showAddModal('cabinet',{room_id:${roomId}})" class="loc-btn loc-btn-g" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> เพิ่มตู้เก็บ</button></div>` : ''}
            </div>`;
        } else if (currentView === 'grid') {
            el.innerHTML = band + `<div class="loc-grid">${cabs.map(c => {
                const hasDanger = c.expired_count > 0;
                const hasWarn = !hasDanger && c.expiring_count > 0;
                const ctrColor = hasDanger ? '#dc2626' : hasWarn ? '#a16207' : '#15803d';
                return `<div class="loc-card" onclick="navigateTo('cabinet',${c.id},'${esc(c.name)}')">
                    <div class="loc-card-hd">
                        <div class="loc-card-ic" style="background:${cabinetTypeBg(c.type)};color:${cabinetTypeColor(c.type)}">${cabinetTypeIconEl(c.type)}</div>
                        <div style="flex:1;min-width:0">
                            <div class="loc-card-nm">${esc(c.name)}</div>
                            <div class="loc-card-sub">${cabinetTypeLabel(c.type)}${c.code?' · '+esc(c.code):''}</div>
                        </div>
                    </div>
                    <div class="loc-card-bd">
                        <div class="loc-card-stats" style="grid-template-columns:1fr 1fr 1fr">
                            <div class="loc-card-stat"><div class="v">${c.shelf_count}</div><div class="l">ชั้นวาง</div></div>
                            <div class="loc-card-stat"><div class="v" style="color:${ctrColor}">${c.container_count}</div><div class="l">ภาชนะ</div></div>
                            <div class="loc-card-stat"><div class="v" style="color:${hasDanger?'#dc2626':hasWarn?'#a16207':'#64748b'}">${hasDanger?c.expired_count:hasWarn?c.expiring_count:0}</div><div class="l">${hasDanger?'หมดอายุ':hasWarn?'ใกล้หมด':'ปกติ'}</div></div>
                        </div>
                    </div>
                </div>`;
            }).join('')}</div>`;
        } else {
            el.innerHTML = band + `<div class="loc-panel"><div class="loc-tw"><table class="loc-t">
                <thead><tr><th>ตู้เก็บ</th><th>ประเภท</th><th style="text-align:center">ชั้นวาง</th><th style="text-align:center">ภาชนะ</th><th style="text-align:center">สถานะ</th></tr></thead>
                <tbody>${cabs.map(c => {
                    const hasDanger = c.expired_count > 0;
                    const hasWarn = !hasDanger && c.expiring_count > 0;
                    return `<tr onclick="navigateTo('cabinet',${c.id},'${esc(c.name)}')">
                        <td style="font-weight:600"><span style="display:inline-flex;align-items:center;gap:7px"><span style="width:26px;height:26px;border-radius:7px;background:${cabinetTypeBg(c.type)};color:${cabinetTypeColor(c.type)};display:inline-flex;align-items:center;justify-content:center;font-size:11px">${cabinetTypeIconEl(c.type)}</span>${esc(c.name)}</span></td>
                        <td style="color:var(--c3)">${cabinetTypeLabel(c.type)}</td>
                        <td style="text-align:center">${c.shelf_count}</td>
                        <td style="text-align:center;font-weight:700;color:${c.container_count>0?'#15803d':'var(--c3)'}">${c.container_count}</td>
                        <td style="text-align:center">${hasDanger?`<span class="loc-ctr-badge danger">${c.expired_count} หมดอายุ</span>`:hasWarn?`<span class="loc-ctr-badge warn">${c.expiring_count} ใกล้หมด</span>`:c.container_count>0?'<span class="loc-ctr-badge total">ปกติ</span>':'—'}</td>
                    </tr>`;
                }).join('')}
                </tbody></table></div></div>`;
        }
    } catch(e) { el.innerHTML = emptyState('fas fa-exclamation-triangle', e.message); }
}

// ═══════ Render Shelves ═══════
async function loadAndRenderShelves(el, cabinetId) {
    el.innerHTML = loading();
    try {
        const d = await apiFetch(`/v1/api/locations.php?action=shelves&cabinet_id=${cabinetId}`);
        if (!d.success || !d.data.length) {
            el.innerHTML = roomManageBandFromStack() + emptyState('fas fa-layer-group', 'ยังไม่มีชั้นวาง') +
                (IS_MANAGER ? `<div style="text-align:center;margin-top:12px"><button onclick="showAddModal('shelf',{cabinet_id:${cabinetId}})" class="loc-btn loc-btn-p"><i class="fas fa-plus"></i> เพิ่มชั้นวาง</button></div>` : '');
            return;
        }
        const shelves = d.data;
        const cabinetType = shelves[0]?.cabinet_type || 'storage';
        const totalCtrs = shelves.reduce((s, sh) => s + (+sh.container_count||0), 0);
        const totalExp = shelves.reduce((s, sh) => s + (+sh.expired_count||0), 0);
        const totalWarn = shelves.reduce((s, sh) => s + (+sh.expiring_count||0), 0);

        el.innerHTML = roomManageBandFromStack() + `<div class="loc-panel">
            <div class="loc-panel-hd">
                <div class="loc-panel-hd-title" style="gap:8px">
                    <span style="width:26px;height:26px;border-radius:7px;background:${cabinetTypeBg(cabinetType)};color:${cabinetTypeColor(cabinetType)};display:inline-flex;align-items:center;justify-content:center;font-size:11px">${cabinetTypeIconEl(cabinetType)}</span>
                    ชั้นวาง
                </div>
                <div style="display:flex;gap:6px;align-items:center">
                    ${totalCtrs > 0 ? `<span class="loc-ctr-badge total"><i class="fas fa-flask"></i>${totalCtrs} ภาชนะ</span>` : ''}
                    ${totalExp > 0 ? `<span class="loc-ctr-badge danger"><i class="fas fa-exclamation-triangle"></i>${totalExp}</span>` : ''}
                    ${totalWarn > 0 ? `<span class="loc-ctr-badge warn"><i class="fas fa-clock"></i>${totalWarn}</span>` : ''}
                    <span style="font-size:11px;color:var(--c3)">${shelves.length} ชั้น</span>
                </div>
            </div>
            <div class="loc-tree-list">
                ${shelves.map(s => {
                    const hasDanger = s.expired_count > 0;
                    const hasWarn = !hasDanger && s.expiring_count > 0;
                    const fillPct = s.slot_count > 0 ? Math.round((s.container_count / s.slot_count) * 100) : 0;
                    return `<div class="loc-tree-item" onclick="navigateTo('shelf',${s.id},'${esc(s.name)}')">
                        <div class="loc-tree-ic" style="background:#ccfbf1;color:#0d9488">
                            <i class="fas fa-layer-group"></i>
                            <span style="position:absolute;bottom:-1px;right:-1px;font-size:7px;background:#0d9488;color:#fff;border-radius:3px;padding:0 2px;font-weight:700">${s.level}</span>
                        </div>
                        <div class="loc-tree-name">${esc(s.name)}</div>
                        <span class="loc-tree-badge">${s.container_count}/${s.slot_count} ช่อง</span>
                        ${hasDanger ? `<span class="loc-ctr-badge danger"><i class="fas fa-exclamation-triangle"></i>${s.expired_count}</span>` : ''}
                        ${hasWarn ? `<span class="loc-ctr-badge warn"><i class="fas fa-clock"></i>${s.expiring_count}</span>` : ''}
                        ${s.container_count > 0 && !hasDanger && !hasWarn ? `<span class="loc-ctr-badge total"><i class="fas fa-flask"></i>${s.container_count}</span>` : ''}
                        <i class="fas fa-chevron-right loc-tree-arr"></i>
                    </div>`;
                }).join('')}
            </div>
            ${IS_MANAGER ? `<div class="loc-add-row"><button onclick="showAddModal('shelf',{cabinet_id:${cabinetId}})" class="loc-btn loc-btn-g" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> เพิ่มชั้นวาง</button></div>` : ''}
        </div>`;
    } catch(e) { el.innerHTML = emptyState('fas fa-exclamation-triangle', e.message); }
}

// ═══════ Render Slots ═══════
async function loadAndRenderSlots(el, shelfId) {
    el.innerHTML = loading();
    try {
        const d = await apiFetch(`/v1/api/locations.php?action=slots&shelf_id=${shelfId}`);
        if (!d.success || !d.data.length) {
            el.innerHTML = roomManageBandFromStack() + emptyState('fas fa-th', 'ยังไม่มีช่องเก็บ') +
                (IS_MANAGER ? `<div style="text-align:center;margin-top:12px"><button onclick="showAddModal('slot',{shelf_id:${shelfId}})" class="loc-btn loc-btn-p"><i class="fas fa-plus"></i> เพิ่มช่อง</button></div>` : '');
            return;
        }
        const slots = d.data;
        const used = slots.filter(s => s.container_id).length;
        const expired = slots.filter(s => s.container_id && slotExpiryState(s) === 'expired').length;
        const expiring = slots.filter(s => s.container_id && slotExpiryState(s) === 'expiring').length;

        el.innerHTML = roomManageBandFromStack() + `<div class="loc-panel">
            <div class="loc-panel-hd">
                <div class="loc-panel-hd-title"><i class="fas fa-th-large"></i> ช่องเก็บสาร</div>
                <div style="display:flex;gap:6px;align-items:center">
                    <span style="font-size:11px;color:var(--c3)">${used}/${slots.length} ช่อง</span>
                    ${expired > 0 ? `<span class="loc-ctr-badge danger"><i class="fas fa-exclamation-triangle"></i>${expired} หมดอายุ</span>` : ''}
                    ${expiring > 0 ? `<span class="loc-ctr-badge warn"><i class="fas fa-clock"></i>${expiring} ใกล้หมด</span>` : ''}
                </div>
            </div>
            <div class="loc-slots" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
                ${slots.map(s => renderSlotCard(s)).join('')}
            </div>
            ${IS_MANAGER ? `<div class="loc-add-row"><button onclick="showAddModal('slot',{shelf_id:${shelfId}})" class="loc-btn loc-btn-g" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> เพิ่มช่อง</button></div>` : ''}
        </div>`;
    } catch(e) { el.innerHTML = emptyState('fas fa-exclamation-triangle', e.message); }
}

function slotExpiryState(s) {
    if (!s.expiry_date) return 'ok';
    const exp = new Date(s.expiry_date);
    const now = new Date();
    if (exp < now) return 'expired';
    const diff = (exp - now) / (1000 * 86400);
    if (diff <= 60) return 'expiring';
    return 'ok';
}

function renderSlotCard(s) {
    if (!s.container_id) {
        return `<div class="loc-slot">
            <div class="loc-slot-hdr">
                <span class="loc-slot-code">${esc(s.code || s.name)}</span>
            </div>
            <div class="loc-slot-empty">
                <div class="loc-slot-empty-ic"><i class="fas fa-box-open"></i></div>
                <div class="loc-slot-empty-lbl">ว่าง</div>
            </div>
        </div>`;
    }

    const expState = slotExpiryState(s);
    const expTag = expState === 'expired'
        ? `<span class="loc-slot-exp-tag danger">หมดอายุ</span>`
        : expState === 'expiring'
            ? `<span class="loc-slot-exp-tag warn">ใกล้หมด</span>`
            : '';

    const stateClass = expState === 'expired' ? ' expired' : expState === 'expiring' ? ' expiring' : ' used';
    const physState = s.physical_state;
    const stateIc = physState === 'liquid' ? '🧪' : physState === 'solid' ? '🧂' : physState === 'gas' ? '💨' : '⚗️';

    const cur = parseFloat(s.current_quantity) || 0;
    const ini = parseFloat(s.initial_quantity) || cur;
    const fillPct = ini > 0 ? Math.min(100, Math.round((cur / ini) * 100)) : 100;
    const fillColor = fillPct > 50 ? '#22c55e' : fillPct > 20 ? '#f59e0b' : '#ef4444';

    const expiryFmt = s.expiry_date ? new Date(s.expiry_date).toLocaleDateString('th-TH', {year:'2-digit',month:'short',day:'numeric'}) : '';

    return `<div class="loc-slot${stateClass}">
        <div class="loc-slot-hdr">
            <span class="loc-slot-code">${esc(s.code || s.name)}</span>
            ${expTag}
        </div>
        <div class="loc-slot-body">
            <span class="loc-slot-ic">${stateIc}</span>
            <div class="loc-slot-nm" title="${esc(s.chemical_name||'')}">${esc(s.chemical_name || '—')}</div>
            ${s.bottle_code ? `<div class="loc-slot-bc">${esc(s.bottle_code)}</div>` : ''}
            ${ini > 0 ? `
                <div class="loc-slot-qty-bar"><div class="loc-slot-qty-fill" style="width:${fillPct}%;background:${fillColor}"></div></div>
                <div class="loc-slot-qty">${cur} / ${ini} ${esc(s.quantity_unit||'')}</div>
            ` : ''}
            ${expiryFmt && expState !== 'ok' ? `<div style="font-size:9px;color:${expState==='expired'?'#dc2626':'#a16207'};margin-top:3px"><i class="fas fa-calendar-times" style="font-size:8px"></i> ${expiryFmt}</div>` : ''}
        </div>
    </div>`;
}

// ═══════ Search ═══════
function setupSearch() {
    let timer;
    const inp = document.getElementById('searchInput');
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('searchResults').style.display = 'none'; _srItems = []; return; }
        timer = setTimeout(() => doSearch(q), 300);
    });
    inp.addEventListener('keydown', e => {
        if (e.key === 'Escape') { inp.value = ''; document.getElementById('searchResults').style.display = 'none'; _srItems = []; }
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('#searchResults') && !e.target.closest('.loc-search')) {
            document.getElementById('searchResults').style.display = 'none';
        }
    });
}

let _srItems = [];

async function doSearch(q) {
    try {
        const d = await apiFetch(`/v1/api/locations.php?action=search&q=${encodeURIComponent(q)}`);
        const sr = document.getElementById('searchResults');
        if (!d.success || !d.data.length) {
            _srItems = [];
            sr.innerHTML = `<div class="loc-sr"><div class="loc-sr-hd">ผลการค้นหา</div><div style="padding:20px;text-align:center;color:var(--c3);font-size:13px">ไม่พบผลลัพธ์</div></div>`;
            sr.style.display = 'block';
            return;
        }
        _srItems = d.data;
        const typeIcon  = {building:'fa-building', room:'fa-door-open', cabinet:'fa-archive'};
        const typeBg    = {building:'#eef2ff',     room:'#e0f2fe',      cabinet:'#f3e8ff'};
        const typeClr   = {building:'#4338ca',     room:'#0369a1',      cabinet:'#7c3aed'};
        const typeLabel = {building:'อาคาร',        room:'ห้อง',          cabinet:'ตู้เก็บ'};

        sr.style.display = 'block';
        sr.innerHTML = `<div class="loc-sr">
            <div class="loc-sr-hd">ผลการค้นหา (${d.data.length} รายการ)</div>
            ${d.data.map((item, i) => {
                const path = srPath(item);
                const mr = mrAllRoomsMap[item.id];
                const isMine = item.type === 'room' && mr?.access_status === 'has_access';
                return `<div class="loc-sr-item" onclick="searchNavigate(${i})">
                    <div class="loc-sr-ic" style="background:${isMine?'#eef2ff':typeBg[item.type]||'#f1f5f9'};color:${isMine?'#4338ca':typeClr[item.type]||'#64748b'}">
                        <i class="fas ${typeIcon[item.type]||'fa-map-marker-alt'}"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div class="loc-sr-nm" style="display:flex;align-items:center;gap:5px">
                            ${esc(item.name)}
                            ${item.code ? `<span style="font-size:9px;padding:1px 5px;border-radius:5px;background:${typeBg[item.type]||'#f1f5f9'};color:${typeClr[item.type]||'#64748b'};font-weight:700">${esc(item.code)}</span>` : ''}
                            ${isMine ? '<span class="loc-my-room-tag" style="font-size:9px;padding:1px 7px"><i class="fas fa-star" style="font-size:7px"></i>ของฉัน</span>' : ''}
                        </div>
                        <div class="loc-sr-sub">${path}</div>
                    </div>
                    <span class="loc-sr-tag">${typeLabel[item.type]||item.type}</span>
                </div>`;
            }).join('')}
        </div>`;
    } catch(e) { console.error(e); }
}

function srPath(item) {
    const parts = [];
    if (item.building_name || item.building_code) parts.push(esc(item.building_code || item.building_name));
    if (item.floor) parts.push('ชั้น ' + item.floor);
    if (item.room_name) parts.push(esc(item.room_name));
    return parts.join(' <i class="fas fa-chevron-right" style="font-size:7px;opacity:.5"></i> ');
}

function searchNavigate(index) {
    const item = _srItems[index];
    if (!item) return;
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('searchInput').value = '';
    navStack = [];

    if (item.type === 'building') {
        navStack.push({type:'building', id:+item.id, name:item.name});
    } else if (item.type === 'room') {
        if (item.building_id) navStack.push({type:'building', id:+item.building_id, name:item.building_name||item.building_code||'อาคาร'});
        if (item.floor)       navStack.push({type:'floor', buildingId:+item.building_id, floor:+item.floor, name:'ชั้น '+item.floor});
        navStack.push({type:'room', id:+item.id, name:item.name});
    } else if (item.type === 'cabinet') {
        if (item.building_id) navStack.push({type:'building', id:+item.building_id, name:item.building_name||item.building_code||'อาคาร'});
        if (item.floor)       navStack.push({type:'floor', buildingId:+item.building_id, floor:+item.floor, name:'ชั้น '+item.floor});
        if (item.room_id)     navStack.push({type:'room', id:+item.room_id, name:item.room_name||'ห้อง'});
        navStack.push({type:'cabinet', id:+item.id, name:item.name});
    }

    renderCurrentLevel();
    updateBreadcrumb();
}

// ═══════ Add Modal ═══════
const LOC_TYPES = {
    building:{label:'อาคาร',icon:'fa-building',color:'#4338ca',bg:'#eef2ff',desc:'เพิ่มอาคารใหม่ในระบบ'},
    room:    {label:'ห้อง',icon:'fa-door-open',color:'#0369a1',bg:'#e0f2fe',desc:'เพิ่มห้องปฏิบัติการ/ห้องเก็บ'},
    cabinet: {label:'ตู้เก็บ',icon:'fa-archive',color:'#7c3aed',bg:'#f3e8ff',desc:'เพิ่มตู้/ตู้ดูดควัน/ตู้เย็น'},
    shelf:   {label:'ชั้นวาง',icon:'fa-layer-group',color:'#0d9488',bg:'#ccfbf1',desc:'เพิ่มชั้นวางในตู้เก็บ'},
    slot:    {label:'ช่องเก็บ',icon:'fa-th',color:'#c2410c',bg:'#fff7ed',desc:'เพิ่มช่องสำหรับวางภาชนะ'}
};
let modalExtra = {};
let modalSelectedType = null;

function showAddModal(type, extra) {
    modalExtra = extra || {};
    modalSelectedType = type || null;
    const level = navStack.length > 0 ? navStack[navStack.length - 1] : null;
    if (level && !Object.keys(modalExtra).length) {
        if (level.type === 'building') modalExtra.building_id = level.id;
        if (level.type === 'floor') { modalExtra.building_id = level.buildingId; modalExtra.floor = level.floor; }
        if (level.type === 'room') modalExtra.room_id = level.id;
        if (level.type === 'cabinet') modalExtra.cabinet_id = level.id;
        if (level.type === 'shelf') modalExtra.shelf_id = level.id;
    }
    if (modalSelectedType) { showAddForm(modalSelectedType); } else { showTypePicker(level); }
    document.getElementById('addModal').classList.add('show');
}

function showTypePicker(level) {
    const suggested = !level ? 'building' : level.type==='building'?'room':level.type==='floor'?'room':level.type==='room'?'cabinet':level.type==='cabinet'?'shelf':'slot';
    const available = getAvailableTypes(level);
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle" style="margin-right:8px;opacity:.7"></i>เพิ่มรายการใหม่';
    let html = `<div class="modal-wizard"><div style="padding:20px 24px 0;font-size:13px;color:#888"><i class="fas fa-info-circle" style="margin-right:4px"></i> เลือกประเภทที่ต้องการเพิ่ม</div><div class="modal-type-picker">`;
    for (const [key, cfg] of Object.entries(LOC_TYPES)) {
        const enabled = available.includes(key);
        const isSuggested = key === suggested;
        html += `<div class="modal-type-card${!enabled?' disabled':''}${isSuggested?' selected':''}" onclick="${enabled?"showAddForm('"+key+"')":''}">
            <div class="type-icon" style="background:${cfg.bg};color:${cfg.color}"><i class="fas ${cfg.icon}"></i></div>
            <div class="type-label">${cfg.label}</div>
            <div class="type-desc">${cfg.desc}</div>
            ${isSuggested?'<div style="font-size:10px;color:#4338ca;font-weight:600;margin-top:2px"><i class="fas fa-star" style="font-size:8px"></i> แนะนำ</div>':''}
        </div>`;
    }
    html += `</div><div class="modal-footer"><div></div><button class="btn-cancel" onclick="closeModal()">ยกเลิก</button></div></div>`;
    document.getElementById('modalBody').innerHTML = html;
}

function getAvailableTypes(level) {
    if (!level) return IS_ADMIN ? ['building','room','cabinet','shelf','slot'] : ['room','cabinet','shelf','slot'];
    if (level.type === 'building' || level.type === 'floor') return ['room'];
    if (level.type === 'room') return ['cabinet'];
    if (level.type === 'cabinet') return ['shelf'];
    if (level.type === 'shelf') return ['slot'];
    return [];
}

function showAddForm(type) {
    modalSelectedType = type;
    const cfg = LOC_TYPES[type];
    document.getElementById('modalTitle').innerHTML = `<i class="fas ${cfg.icon}" style="margin-right:8px;color:${cfg.color}"></i>เพิ่ม${cfg.label}`;
    let fieldsHtml = '';
    if (type === 'building') {
        fieldsHtml = `
            ${mField('name','ชื่ออาคาร (ภาษาไทย)','text',true,'','เช่น อาคารวิชาการ 1','fa-building')}
            ${mField('name_en','ชื่อภาษาอังกฤษ','text',false,'','e.g. Academic Building 1','fa-font')}
            <div class="modal-field-row">
                ${mField('shortname','ชื่อย่อ','text',false,'','เช่น F1','fa-tag')}
                ${mField('code','รหัสอาคาร','text',false,'','เช่น B001','fa-barcode')}
            </div>`;
    } else if (type === 'room') {
        fieldsHtml = `
            ${mField('name','ชื่อห้อง (ภาษาไทย)','text',true,'','เช่น ห้องปฏิบัติการเคมี 1','fa-door-open')}
            ${mField('name_en','ชื่อภาษาอังกฤษ','text',false,'','e.g. Chemistry Lab 1','fa-font')}
            <div class="modal-field-row">
                ${mField('code','รหัสห้อง','text',false,'','เช่น F01101','fa-barcode')}
                ${mField('floor','ชั้นที่','number',false,modalExtra.floor||1,'','fa-layer-group')}
            </div>
            <div class="modal-field-row">
                ${mField('area_sqm','พื้นที่ (ตร.ม.)','number',false,'','','fa-ruler-combined')}
                ${mField('capacity_persons','ความจุ (คน)','number',false,'','','fa-users')}
            </div>`;
    } else if (type === 'cabinet') {
        fieldsHtml = `
            ${mField('name','ชื่อตู้','text',true,'','เช่น ตู้เก็บสารเคมี A1','fa-archive')}
            ${mField('code','รหัสตู้','text',false,'','เช่น CAB-001','fa-barcode')}
            <div class="modal-field">
                <label>ประเภทตู้ <span class="req">*</span></label>
                <div class="modal-select-grid" id="cabinetTypeGrid">
                    ${cabinetTypeOption('storage','fa-box','ตู้เก็บทั่วไป','#7c3aed','#f3e8ff',true)}
                    ${cabinetTypeOption('fume_hood','fa-wind','ตู้ดูดควัน','#0d9488','#ccfbf1')}
                    ${cabinetTypeOption('refrigerator','fa-temperature-low','ตู้เย็น','#0369a1','#e0f2fe')}
                    ${cabinetTypeOption('freezer','fa-snowflake','ตู้แช่แข็ง','#1d4ed8','#dbeafe')}
                    ${cabinetTypeOption('safety_cabinet','fa-shield-alt','ตู้นิรภัย','#dc2626','#fee2e2')}
                    ${cabinetTypeOption('other','fa-ellipsis-h','อื่นๆ','#64748b','#f1f5f9')}
                </div>
                <input type="hidden" name="cabinet_type" id="cabinetTypeVal" value="storage">
            </div>
            ${mField('dimensions','ขนาด กxยxส (ซม.)','text',false,'','เช่น 60x45x180','fa-ruler')}`;
    } else if (type === 'shelf') {
        fieldsHtml = `
            ${mField('name','ชื่อชั้นวาง','text',true,'','เช่น ชั้นที่ 1','fa-layer-group')}
            <div class="modal-field-row">
                ${mField('level','ระดับ (ล่าง→บน)','number',false,1,'ลำดับจากล่างขึ้นบน','fa-sort-amount-up')}
                ${mField('capacity','ความจุ (ช่อง)','number',false,'','จำนวนช่องสูงสุด','fa-th')}
            </div>
            ${mField('max_weight','น้ำหนักสูงสุด (กก.)','number',false,'','น้ำหนักที่รับได้','fa-weight-hanging')}`;
    } else if (type === 'slot') {
        fieldsHtml = `
            ${mField('name','ชื่อช่อง','text',true,'','เช่น ช่อง A1','fa-th')}
            <div class="modal-field-row">
                ${mField('code','รหัสช่อง','text',false,'','เช่น S001','fa-barcode')}
                ${mField('position','ลำดับตำแหน่ง','number',false,1,'จากซ้ายไปขวา','fa-arrows-alt-h')}
            </div>`;
    }

    const html = `<div class="modal-wizard">
        <form id="addForm" onsubmit="submitAdd(event)">
            <div class="modal-form-wrap">
                <div class="modal-form-header">
                    <div class="form-icon" style="background:${cfg.bg};color:${cfg.color}"><i class="fas ${cfg.icon}"></i></div>
                    <div><div class="form-title">เพิ่ม${cfg.label}ใหม่</div><div class="form-desc">${cfg.desc} — กรอกข้อมูลด้านล่าง</div></div>
                </div>
                ${fieldsHtml}
            </div>
            <div class="modal-footer">
                <button type="button" class="back-link" onclick="showTypePicker(navStack.length?navStack[navStack.length-1]:null)">
                    <i class="fas fa-arrow-left"></i> เลือกประเภทอื่น
                </button>
                <div style="display:flex;gap:10px">
                    <button type="button" class="btn-cancel" onclick="closeModal()">ยกเลิก</button>
                    <button type="submit" class="btn-submit primary" id="btnSubmitAdd"><i class="fas fa-check"></i> บันทึก</button>
                </div>
            </div>
        </form>
    </div>`;
    document.getElementById('modalBody').innerHTML = html;
    setTimeout(() => { const f = document.querySelector('#addForm input[type="text"]'); if(f) f.focus(); }, 100);
}

function mField(name, label, type, required, value, hint, icon) {
    type = type || 'text';
    return `<div class="modal-field">
        <label>${label}${required ? '<span class="req"> *</span>' : ''}</label>
        <input type="${type}" name="${name}" ${required?'required':''} ${value!==undefined&&value!==''?'value="'+value+'"':''} ${type==='number'?'step="any"':''} placeholder="${hint||''}">
        ${icon ? '<i class="fas '+icon+' field-icon"></i>' : ''}
    </div>`;
}

function cabinetTypeOption(val, icon, label, color, bg, active) {
    return `<div class="modal-select-opt${active?' active':''}" data-val="${val}" onclick="selectCabinetType(this,'${val}')">
        <div class="opt-icon" style="background:${bg};color:${color}"><i class="fas ${icon}"></i></div>
        <span class="opt-label">${label}</span>
    </div>`;
}

function selectCabinetType(el, val) {
    document.querySelectorAll('#cabinetTypeGrid .modal-select-opt').forEach(o => o.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('cabinetTypeVal').value = val;
}

async function submitAdd(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitAdd');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
    const form = document.getElementById('addForm');
    const data = Object.fromEntries(new FormData(form));
    Object.assign(data, modalExtra, {type: modalSelectedType});
    try {
        const d = await apiFetch('/v1/api/locations.php?action=create', {method:'POST', body:JSON.stringify(data)});
        if (d.success) {
            showAddSuccess(data.name || data.code || '');
            renderCurrentLevel();
            loadStats();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> บันทึก';
            showFieldError(d.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    } catch(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> บันทึก';
        showFieldError(err.message);
    }
}

function showAddSuccess(name) {
    const cfg = LOC_TYPES[modalSelectedType];
    document.getElementById('modalBody').innerHTML = `
        <div class="modal-success">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h3>เพิ่ม${cfg.label}สำเร็จ!</h3>
            <p>${esc(name)} ถูกเพิ่มเข้าสู่ระบบเรียบร้อยแล้ว</p>
            <div style="margin-top:24px;display:flex;gap:10px">
                <button onclick="closeModal()" class="btn-cancel" style="border:1.5px solid #e0e3e8;border-radius:10px;padding:10px 20px;font-size:13px;cursor:pointer;background:none">ปิด</button>
                <button onclick="showAddModal()" class="btn-submit primary" style="border:none;border-radius:10px;padding:10px 24px;font-size:13px;font-weight:600;cursor:pointer;background:#4338ca;color:#fff">
                    <i class="fas fa-plus"></i> เพิ่มอีก
                </button>
            </div>
        </div>`;
    setTimeout(() => { if (document.getElementById('addModal').classList.contains('show')) closeModal(); }, 4000);
}

function showFieldError(msg) {
    document.querySelectorAll('.modal-error-msg').forEach(e => e.remove());
    const wrap = document.querySelector('.modal-form-wrap');
    if (wrap) {
        const errDiv = document.createElement('div');
        errDiv.className = 'modal-error-msg';
        errDiv.style.cssText = 'background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-top:4px;display:flex;align-items:center;gap:10px;font-size:13px;color:#991b1b;animation:modalSlideIn .2s ease';
        errDiv.innerHTML = `<i class="fas fa-exclamation-circle" style="color:#ef4444;font-size:16px;flex-shrink:0"></i><span>${esc(msg)}</span>`;
        wrap.appendChild(errDiv);
        setTimeout(() => errDiv.remove(), 5000);
    }
}

function closeModal() {
    document.getElementById('addModal').classList.remove('show');
    modalSelectedType = null;
    modalExtra = {};
}
document.getElementById('addModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

// ═══════ Helpers ═══════
function statusBadge(s) {
    if (!s || s === 'พร้อมใช้งาน') return '<span class="loc-status ok"><i class="fas fa-circle" style="font-size:6px"></i> พร้อม</span>';
    if (s === 'ปิดปรับปรุง') return '<span class="loc-status maint"><i class="fas fa-circle" style="font-size:6px"></i> ปรับปรุง</span>';
    if (s === 'ไม่เปิดให้บริการ') return '<span class="loc-status closed"><i class="fas fa-circle" style="font-size:6px"></i> ปิด</span>';
    return `<span class="loc-status">${esc(s)}</span>`;
}

function statusDot(s) {
    if (!s || s === 'พร้อมใช้งาน') return '<div class="loc-tree-dot ok" title="พร้อมใช้งาน"></div>';
    if (s === 'ปิดปรับปรุง') return '<div class="loc-tree-dot maint" title="ปิดปรับปรุง"></div>';
    if (s === 'ไม่เปิดให้บริการ') return '<div class="loc-tree-dot closed" title="ไม่เปิดให้บริการ"></div>';
    return '';
}

function cabinetTypeLabel(t) { return (CAB_TYPE[t]||CAB_TYPE.other).label; }

function loading() {
    return '<div class="loc-ld"><div class="ci-spinner"></div></div>';
}

function emptyState(icon, text) {
    return `<div class="loc-empty"><i class="${icon}"></i><p>${text}</p></div>`;
}

function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

// ═══════ Cabinet Type Helpers ═══════
const CAB_TYPE = {
    storage:        {label:'ตู้เก็บ',     icon:'fa-archive',        bg:'#f3e8ff',color:'#7c3aed'},
    fume_hood:      {label:'ตู้ดูดควัน',   icon:'fa-wind',           bg:'#ccfbf1',color:'#0d9488'},
    refrigerator:   {label:'ตู้เย็น',      icon:'fa-temperature-low',bg:'#e0f2fe',color:'#0369a1'},
    freezer:        {label:'ตู้แช่แข็ง',   icon:'fa-snowflake',      bg:'#dbeafe',color:'#1d4ed8'},
    safety_cabinet: {label:'ตู้นิรภัย',   icon:'fa-shield-alt',     bg:'#fee2e2',color:'#dc2626'},
    other:          {label:'อื่นๆ',         icon:'fa-ellipsis-h',    bg:'#f1f5f9',color:'#64748b'},
};
function cabinetTypeBg(t)    { return (CAB_TYPE[t]||CAB_TYPE.other).bg; }
function cabinetTypeColor(t) { return (CAB_TYPE[t]||CAB_TYPE.other).color; }
function cabinetTypeIconEl(t){ const c = CAB_TYPE[t]||CAB_TYPE.other; return `<i class="fas ${c.icon}"></i>`; }

// ═══════ Room Management Band Helpers ═══════
function roomManageBand(mr, my) {
    if (!mr || mr.access_status !== 'has_access') return '';
    const stats = [];
    if (my) {
        if (my.total > 0) stats.push(`<span class="loc-room-mgmt-stat"><i class="fas fa-flask" style="color:#4338ca;font-size:9px"></i> ${my.total} ภาชนะ</span>`);
        if (my.expired > 0) stats.push(`<span class="loc-room-mgmt-stat" style="color:#dc2626"><i class="fas fa-exclamation-triangle" style="font-size:9px"></i> ${my.expired} หมดอายุ</span>`);
        else if (my.expiring_soon > 0) stats.push(`<span class="loc-room-mgmt-stat" style="color:#a16207"><i class="fas fa-clock" style="font-size:9px"></i> ${my.expiring_soon} ใกล้หมดอายุ</span>`);
        if (my.unplaced > 0) stats.push(`<span class="loc-room-mgmt-stat" style="color:#6366f1"><i class="fas fa-inbox" style="font-size:9px"></i> ${my.unplaced} ยังไม่จัดวาง</span>`);
    }
    return `<div class="loc-room-mgmt">
        <div class="loc-room-mgmt-ic"><i class="fas fa-star"></i></div>
        <div class="loc-room-mgmt-body">
            <div class="loc-room-mgmt-title">ห้องที่คุณดูแล</div>
            ${stats.length ? `<div class="loc-room-mgmt-sub">${stats.join('')}</div>` : ''}
        </div>
        <a href="/v1/pages/myroom.php" class="loc-goto-btn" onclick="event.stopPropagation()">
            <i class="fas fa-arrow-right" style="font-size:9px"></i>จัดการ My Room
        </a>
    </div>`;
}

function roomManageBandFromStack() {
    const roomEntry = navStack.find(n => n.type === 'room');
    if (!roomEntry) return '';
    return roomManageBand(mrAllRoomsMap[roomEntry.id], mrMyRoomsMap[roomEntry.id]);
}

// ═══════ Myroom Enrichment Helpers ═══════

function myRoomsBand(myRooms) {
    if (!myRooms.length) return '';
    return `<div class="loc-myrooms-band">
        <div class="loc-myrooms-label">
            <div class="lb">ห้องที่ดูแล</div>
            <div class="ct">${myRooms.length}</div>
        </div>
        <div class="loc-myrooms-chips">
            ${myRooms.map(r => {
                const my = mrMyRoomsMap[r.id];
                const hasDanger = my && my.expired > 0;
                const hasWarn = my && !hasDanger && my.expiring_soon > 0;
                const ctrLabel = my ? ` · ${my.total}` : '';
                return `<button class="loc-myrooms-chip" onclick="event.stopPropagation();window.location='/v1/pages/myroom.php'">
                    <div class="dot${hasDanger ? ' danger' : hasWarn ? ' warn' : ''}"></div>
                    ${esc(r.code || r.name)}${ctrLabel}
                </button>`;
            }).join('')}
        </div>
        <a href="/v1/pages/myroom.php" class="loc-goto-btn-light" onclick="event.stopPropagation()">
            <i class="fas fa-external-link-alt" style="font-size:9px"></i>จัดการ My Room
        </a>
    </div>`;
}

function enrichTreeRoom(mr, my, isMine) {
    if (!mr) return '';
    const parts = [];
    if (my) {
        if (my.expired > 0) parts.push(`<span class="loc-ctr-badge danger"><i class="fas fa-exclamation-triangle"></i>${my.expired} หมดอายุ</span>`);
        else if (my.expiring_soon > 0) parts.push(`<span class="loc-ctr-badge warn"><i class="fas fa-clock"></i>${my.expiring_soon} ใกล้หมด</span>`);
        if (my.total > 0) parts.push(`<span class="loc-ctr-badge total"><i class="fas fa-flask"></i>${my.total}</span>`);
    } else if (mr.total > 0) {
        parts.push(`<span class="loc-ctr-badge total"><i class="fas fa-flask"></i>${mr.total}</span>`);
    }
    if (isMine) {
        parts.push(`<a href="/v1/pages/myroom.php" class="loc-goto-btn" style="padding:3px 9px;font-size:9px" onclick="event.stopPropagation()"><i class="fas fa-cog" style="font-size:8px"></i>จัดการ</a>`);
    } else {
        const mgrs = (mr.managers || []).slice(0, 3);
        if (mgrs.length) parts.push(renderMgrAvatars(mgrs));
    }
    return `<div class="loc-tree-enrich">${parts.join('')}</div>`;
}

function enrichCardStats(mr, my, r) {
    if (my) {
        const warnV = my.expired > 0 ? my.expired : my.expiring_soon;
        const warnL = my.expired > 0 ? 'หมดอายุ' : my.expiring_soon > 0 ? 'ใกล้หมด' : 'ปกติ';
        const warnC = my.expired > 0 ? '#dc2626' : my.expiring_soon > 0 ? '#a16207' : '#64748b';
        return `<div class="loc-card-stats" style="grid-template-columns:1fr 1fr 1fr">
            <div class="loc-card-stat"><div class="v" style="color:#15803d">${my.total}</div><div class="l">ภาชนะ</div></div>
            <div class="loc-card-stat"><div class="v" style="color:${warnC}">${warnV}</div><div class="l">${warnL}</div></div>
            <div class="loc-card-stat"><div class="v">${r.cabinet_count}</div><div class="l">ตู้เก็บ</div></div>
        </div>`;
    }
    if (mr && mr.total > 0) {
        return `<div class="loc-card-stats">
            <div class="loc-card-stat"><div class="v">${r.cabinet_count}</div><div class="l">ตู้เก็บ</div></div>
            <div class="loc-card-stat"><div class="v" style="color:#0369a1">${mr.total}</div><div class="l">ภาชนะ</div></div>
        </div>`;
    }
    return `<div class="loc-card-stats">
        <div class="loc-card-stat"><div class="v">${r.cabinet_count}</div><div class="l">ตู้เก็บ</div></div>
        <div class="loc-card-stat"><div class="v">${r.capacity_persons||'—'}</div><div class="l">ความจุ(คน)</div></div>
    </div>`;
}

function enrichCardFooter(mr, my, isMine) {
    if (!mr) return '';
    let left = '', right = '';
    if (isMine) {
        const badges = [];
        if (my && my.unplaced > 0) badges.push(`<span class="loc-ctr-badge warn" title="ยังไม่ได้จัดวาง"><i class="fas fa-inbox"></i>${my.unplaced} ยังไม่จัด</span>`);
        left = `<div style="display:flex;gap:4px;align-items:center">${badges.join('')}</div>`;
        right = `<a href="/v1/pages/myroom.php" class="loc-goto-btn" onclick="event.stopPropagation()"><i class="fas fa-arrow-right" style="font-size:9px"></i>จัดการ</a>`;
    } else {
        const mgrs = mr.managers || [];
        left = mgrs.length
            ? `<div style="display:flex;align-items:center;gap:6px">${renderMgrAvatars(mgrs)}${mgrs.length === 1 ? `<span style="font-size:10px;color:var(--c2);max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(mgrs[0].first_name+' '+mgrs[0].last_name)}</span>` : ''}</div>`
            : `<span style="font-size:10px;color:var(--c3)">ยังไม่มีผู้ดูแล</span>`;
        right = '';
    }
    return `<div class="loc-card-ft">${left}${right}</div>`;
}

function enrichTableCtr(mr, my) {
    if (my) {
        let s = `<strong style="color:#15803d">${my.total}</strong>`;
        if (my.expired > 0) s += ` <span class="loc-ctr-badge danger" style="padding:1px 5px">${my.expired}</span>`;
        else if (my.expiring_soon > 0) s += ` <span class="loc-ctr-badge warn" style="padding:1px 5px">${my.expiring_soon}</span>`;
        return s;
    }
    if (mr && mr.total > 0) return `<span style="color:#0369a1;font-weight:700">${mr.total}</span>`;
    return '—';
}

function enrichTableMgrs(mr) {
    if (!mr || !mr.managers || !mr.managers.length) return '<span style="color:var(--c3);font-size:11px">—</span>';
    const primary = mr.managers.find(m => m.is_primary == 1) || mr.managers[0];
    return `<div style="display:flex;align-items:center;gap:6px">
        ${renderMgrAvatars(mr.managers)}
        <span style="font-size:11px;color:var(--c1);max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(primary.first_name+' '+primary.last_name)}</span>
    </div>`;
}

function renderMgrAvatars(managers) {
    const shown = managers.slice(0, 3);
    const extra = managers.length - shown.length;
    return `<div class="loc-mgr-row">${shown.map(m =>
        `<div class="loc-mgr-av" title="${esc(m.first_name+' '+m.last_name)}">${m.avatar_url ? `<img src="${esc(m.avatar_url)}" alt="">` : esc((m.first_name||'?')[0])}</div>`
    ).join('')}${extra > 0 ? `<div class="loc-mgr-av" style="background:#94a3b8;font-size:8px;width:22px;height:22px">+${extra}</div>` : ''}</div>`;
}

// ═══════ Stat Detail Sheet ═══════
let _sdsType = null;

function showStatDetail(type) {
    _sdsType = type;
    const ov = document.getElementById('statDetailOv');
    ov.classList.add('open');
    const inp = document.getElementById('sdsSearchInp');
    if (inp) inp.value = '';
    renderStatDetail(type);
}

function closeStatDetail() {
    document.getElementById('statDetailOv').classList.remove('open');
    _sdsType = null;
}

function renderStatDetail(type) {
    if (type === 'buildings')  renderSdsBuildings();
    else if (type === 'rooms')      renderSdsRooms('');
    else if (type === 'cabinets')   renderSdsCabinets();
    else if (type === 'containers') renderSdsContainers();
    else if (type === 'myrooms')    renderSdsMyRooms();
}

function sdsHdrHtml(icBg, icColor, icon, title, cnt) {
    return `<div class="loc-sds-hdr-ic" style="background:${icBg};color:${icColor}"><i class="fas ${icon}"></i></div>
        <div style="flex:1;min-width:0">
            <div class="loc-sds-hdr-title">${title}</div>
            <div class="loc-sds-hdr-cnt">${cnt}</div>
        </div>
        <button class="loc-sds-close" onclick="closeStatDetail()"><i class="fas fa-times"></i></button>`;
}

function renderSdsBuildings() {
    document.getElementById('sdsHdr').innerHTML = sdsHdrHtml('#eef2ff','#4338ca','fa-building','อาคารทั้งหมด',buildingsData.length+' อาคาร');
    document.getElementById('sdsSearchWrap').style.display = 'none';
    const body = document.getElementById('sdsBody');
    if (!buildingsData.length) { body.innerHTML = `<div class="loc-sds-empty"><i class="fas fa-building"></i><p>ยังไม่มีข้อมูลอาคาร</p></div>`; return; }
    body.innerHTML = buildingsData.map(b => `
        <div class="loc-sds-row" onclick="closeStatDetail();navigateHome();navigateTo('building',${b.id},'${esc(b.shortname||b.name)}')">
            <div class="loc-sds-row-ic" style="background:#eef2ff;color:#4338ca"><i class="fas fa-building"></i></div>
            <div style="flex:1;min-width:0">
                <div class="loc-sds-row-nm">${esc(b.name)}${b.shortname?`<span style="font-size:10px;padding:1px 6px;border-radius:5px;background:#eef2ff;color:#4338ca;font-weight:700;margin-left:6px">${esc(b.shortname)}</span>`:''}</div>
                <div class="loc-sds-row-sub">${b.floor_count} ชั้น · ${b.room_count} ห้อง</div>
            </div>
            <div class="loc-sds-row-right">
                <div class="loc-sds-row-val">${b.cabinet_count}</div>
                <div class="loc-sds-row-vl">ตู้เก็บ</div>
            </div>
        </div>`).join('');
}

function sdsFilterRooms(q) {
    renderSdsRooms(q.trim());
}

function renderSdsRooms(q) {
    const allRooms = Object.values(mrAllRoomsMap);
    const myRoomIds = new Set(Object.keys(mrMyRoomsMap).map(Number));
    const ql = q ? q.toLowerCase() : '';
    const filtered = ql
        ? allRooms.filter(r => (r.name||'').toLowerCase().includes(ql) || (r.code||'').toLowerCase().includes(ql) || (r.bld_short||'').toLowerCase().includes(ql))
        : allRooms;

    const myRooms = filtered.filter(r => myRoomIds.has(+r.id));
    const otherRooms = filtered.filter(r => !myRoomIds.has(+r.id));

    document.getElementById('sdsHdr').innerHTML = sdsHdrHtml('#e0f2fe','#0369a1','fa-door-open','ห้องทั้งหมด',allRooms.length+' ห้อง');
    document.getElementById('sdsSearchWrap').style.display = '';
    const body = document.getElementById('sdsBody');

    if (!filtered.length) {
        body.innerHTML = `<div class="loc-sds-empty"><i class="fas fa-search"></i><p>ไม่พบห้องที่ค้นหา</p></div>`;
        return;
    }

    let html = '';
    if (myRooms.length) {
        html += `<div class="loc-sds-section"><i class="fas fa-star" style="color:#6366f1;margin-right:4px"></i>ห้องของฉัน (${myRooms.length})</div>`;
        html += myRooms.map(r => sdsRoomRow(r, true)).join('');
    }
    if (otherRooms.length) {
        if (myRooms.length) html += `<div class="loc-sds-section">ห้องอื่นๆ (${otherRooms.length})</div>`;
        html += otherRooms.map(r => sdsRoomRow(r, false)).join('');
    }
    body.innerHTML = html;
}

function sdsRoomRow(r, isMine) {
    const my = mrMyRoomsMap[r.id];
    const hasDanger = my && +my.expired > 0;
    const hasWarn = my && !hasDanger && +my.expiring_soon > 0;
    const ctrCount = my ? +my.total : +r.total||0;
    const bld = r.bld_short || r.bld_code || '';
    const sub = [bld ? 'อาคาร '+bld : '', r.floor ? 'ชั้น '+r.floor : ''].filter(Boolean).join(' · ') + (r.code ? (bld||r.floor ? ' · '+esc(r.code) : esc(r.code)) : '');
    return `<div class="loc-sds-row" onclick="closeStatDetail();sdsGoRoom(${r.id},'${esc(r.name)}','${esc(r.bld_code||'')}',${+r.floor||0})">
        <div class="loc-sds-row-ic" style="background:${isMine?'#eef2ff':'#e0f2fe'};color:${isMine?'#4338ca':'#0369a1'}"><i class="fas fa-door-open"></i></div>
        <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap">
                <span class="loc-sds-row-nm">${esc(r.name)}</span>
                ${isMine?'<span class="loc-my-room-tag" style="font-size:9px;padding:1px 7px;flex-shrink:0"><i class="fas fa-star" style="font-size:7px"></i>ของฉัน</span>':''}
            </div>
            <div class="loc-sds-row-sub">${sub}</div>
        </div>
        <div class="loc-sds-row-right">
            ${hasDanger?`<div class="loc-sds-row-val" style="color:#dc2626">${my.expired}</div><div class="loc-sds-row-vl" style="color:#dc2626">หมดอายุ</div>`:
              hasWarn?`<div class="loc-sds-row-val" style="color:#a16207">${my.expiring_soon}</div><div class="loc-sds-row-vl" style="color:#a16207">ใกล้หมด</div>`:
              ctrCount>0?`<div class="loc-sds-row-val">${ctrCount}</div><div class="loc-sds-row-vl">ภาชนะ</div>`:''}
        </div>
    </div>`;
}

function renderSdsCabinets() {
    const total = buildingsData.reduce((s,b)=>s+(+b.cabinet_count||0),0);
    document.getElementById('sdsHdr').innerHTML = sdsHdrHtml('#f3e8ff','#7c3aed','fa-archive','ตู้เก็บทั้งหมด',total+' ตู้เก็บ');
    document.getElementById('sdsSearchWrap').style.display = 'none';
    const body = document.getElementById('sdsBody');
    if (!buildingsData.length) { body.innerHTML = `<div class="loc-sds-empty"><i class="fas fa-archive"></i><p>ยังไม่มีข้อมูลตู้เก็บ</p></div>`; return; }
    const rooms = buildingsData.reduce((s,b)=>s+(+b.room_count||0),0);
    body.innerHTML = `
        <div class="loc-sds-stat-row">
            <div class="loc-sds-stat-card"><div class="sv" style="color:#7c3aed">${total}</div><div class="sl">ตู้เก็บ</div></div>
            <div class="loc-sds-stat-card"><div class="sv" style="color:#0369a1">${rooms}</div><div class="sl">ห้อง</div></div>
            <div class="loc-sds-stat-card"><div class="sv" style="color:#4338ca">${buildingsData.length}</div><div class="sl">อาคาร</div></div>
        </div>
        <div class="loc-sds-section">แยกตามอาคาร</div>
        ${buildingsData.map(b=>`
            <div class="loc-sds-row" onclick="closeStatDetail();navigateHome();navigateTo('building',${b.id},'${esc(b.shortname||b.name)}')">
                <div class="loc-sds-row-ic" style="background:#eef2ff;color:#4338ca"><i class="fas fa-building"></i></div>
                <div style="flex:1;min-width:0">
                    <div class="loc-sds-row-nm">${esc(b.name)}</div>
                    <div class="loc-sds-row-sub">${b.room_count} ห้อง · ${b.floor_count} ชั้น</div>
                </div>
                <div class="loc-sds-row-right">
                    <div class="loc-sds-row-val" style="color:#7c3aed">${b.cabinet_count}</div>
                    <div class="loc-sds-row-vl">ตู้เก็บ</div>
                </div>
            </div>`).join('')}`;
}

function renderSdsContainers() {
    const myRooms = Object.values(mrMyRoomsMap);
    const totalExp  = myRooms.reduce((s,r)=>s+(+r.expired||0),0);
    const totalWarn = myRooms.reduce((s,r)=>s+(+r.expiring_soon||0),0);
    const globalEl  = document.getElementById('statContainers');
    const globalTotal = globalEl ? (+globalEl.textContent||0) : 0;

    document.getElementById('sdsHdr').innerHTML = sdsHdrHtml('#dcfce7','#16a34a','fa-flask','ภาชนะสารเคมี',globalTotal+' รายการ');
    document.getElementById('sdsSearchWrap').style.display = 'none';
    const body = document.getElementById('sdsBody');

    let html = `
        <div class="loc-sds-stat-row">
            <div class="loc-sds-stat-card"><div class="sv" style="color:#15803d">${globalTotal}</div><div class="sl">ทั้งหมด</div></div>
            <div class="loc-sds-stat-card"><div class="sv" style="color:#dc2626">${totalExp}</div><div class="sl">หมดอายุ</div></div>
            <div class="loc-sds-stat-card"><div class="sv" style="color:#a16207">${totalWarn}</div><div class="sl">ใกล้หมด</div></div>
        </div>`;

    if (myRooms.length) {
        html += `<div class="loc-sds-section"><i class="fas fa-star" style="color:#6366f1;margin-right:4px"></i>ห้องที่ฉันดูแล</div>`;
        html += myRooms.map(my => {
            const rd = mrAllRoomsMap[my.room_id]||{};
            const hasDanger = +my.expired>0;
            const hasWarn = !hasDanger && +my.expiring_soon>0;
            return `<div class="loc-sds-row" onclick="closeStatDetail();sdsGoRoomFromMyRoom(${my.room_id})">
                <div class="loc-sds-row-ic" style="background:${hasDanger?'#fee2e2':hasWarn?'#fef9c3':'#dcfce7'};color:${hasDanger?'#dc2626':hasWarn?'#a16207':'#15803d'}"><i class="fas fa-flask"></i></div>
                <div style="flex:1;min-width:0">
                    <div class="loc-sds-row-nm">${esc(rd.name||'ห้อง #'+my.room_id)}</div>
                    <div class="loc-sds-row-sub">${+my.organized} จัดวาง · ${+my.unplaced||0} ยังไม่จัด</div>
                </div>
                <div class="loc-sds-row-right">
                    ${hasDanger?`<div class="loc-sds-row-val" style="color:#dc2626">${my.expired}</div><div class="loc-sds-row-vl" style="color:#dc2626">หมดอายุ</div>`:
                      hasWarn?`<div class="loc-sds-row-val" style="color:#a16207">${my.expiring_soon}</div><div class="loc-sds-row-vl" style="color:#a16207">ใกล้หมด</div>`:
                      `<div class="loc-sds-row-val" style="color:#15803d">${my.total}</div><div class="loc-sds-row-vl">ภาชนะ</div>`}
                </div>
            </div>`;
        }).join('');
        html += `<div class="loc-sds-footer"><a href="/v1/pages/myroom.php" class="loc-btn loc-btn-p" style="display:inline-flex"><i class="fas fa-arrow-right"></i>จัดการ My Room</a></div>`;
    } else {
        html += `<div class="loc-sds-empty" style="padding:32px 24px"><i class="fas fa-info-circle"></i><p style="font-size:12px">เข้าถึงสถิติภาชนะรายห้องได้<br>เมื่อคุณเป็นผู้ดูแลห้อง</p></div>`;
    }
    body.innerHTML = html;
}

function renderSdsMyRooms() {
    const myRooms = Object.values(mrMyRoomsMap);
    const totalMine = myRooms.reduce((s,r)=>s+(+r.total||0),0);
    const totalExp  = myRooms.reduce((s,r)=>s+(+r.expired||0),0);
    const totalWarn = myRooms.reduce((s,r)=>s+(+r.expiring_soon||0),0);

    document.getElementById('sdsHdr').innerHTML = sdsHdrHtml('#eef2ff','#6366f1','fa-star','ห้องของฉัน',myRooms.length+' ห้อง · '+totalMine+' ภาชนะ');
    document.getElementById('sdsSearchWrap').style.display = 'none';
    const body = document.getElementById('sdsBody');

    if (!myRooms.length) {
        body.innerHTML = `<div class="loc-sds-empty"><i class="fas fa-star"></i><p>ยังไม่มีห้องที่คุณดูแล</p></div>`;
        return;
    }

    let html = `
        <div class="loc-sds-stat-row">
            <div class="loc-sds-stat-card"><div class="sv" style="color:#6366f1">${totalMine}</div><div class="sl">ภาชนะ</div></div>
            <div class="loc-sds-stat-card"><div class="sv" style="color:#dc2626">${totalExp}</div><div class="sl">หมดอายุ</div></div>
            <div class="loc-sds-stat-card"><div class="sv" style="color:#a16207">${totalWarn}</div><div class="sl">ใกล้หมด</div></div>
        </div>`;

    html += myRooms.map(my => {
        const rd = mrAllRoomsMap[my.room_id]||{};
        const hasDanger = +my.expired>0;
        const hasWarn = !hasDanger && +my.expiring_soon>0;
        return `<div class="loc-sds-row" onclick="closeStatDetail();sdsGoRoomFromMyRoom(${my.room_id})">
            <div class="loc-sds-row-ic" style="background:${hasDanger?'#fee2e2':hasWarn?'#fef9c3':'#eef2ff'};color:${hasDanger?'#dc2626':hasWarn?'#a16207':'#4338ca'}"><i class="fas fa-door-open"></i></div>
            <div style="flex:1;min-width:0">
                <div class="loc-sds-row-nm">${esc(rd.name||'ห้อง #'+my.room_id)}</div>
                <div class="loc-sds-row-sub">${+my.total} ภาชนะ · ${+my.organized} จัดวาง · ${+my.unplaced||0} ยังไม่จัด</div>
            </div>
            <div class="loc-sds-row-right">
                ${hasDanger?`<div class="loc-sds-row-val" style="color:#dc2626">${my.expired}</div><div class="loc-sds-row-vl" style="color:#dc2626">หมดอายุ</div>`:
                  hasWarn?`<div class="loc-sds-row-val" style="color:#a16207">${my.expiring_soon}</div><div class="loc-sds-row-vl" style="color:#a16207">ใกล้หมด</div>`:
                  `<div class="loc-sds-row-val" style="color:#6366f1">${my.total}</div><div class="loc-sds-row-vl">ภาชนะ</div>`}
            </div>
        </div>`;
    }).join('');

    html += `<div class="loc-sds-footer"><a href="/v1/pages/myroom.php" class="loc-btn loc-btn-p" style="display:inline-flex"><i class="fas fa-arrow-right"></i>ไปที่ My Room</a></div>`;
    body.innerHTML = html;
}

function sdsGoRoom(roomId, roomName, bldCode, floor) {
    navStack = [];
    if (bldCode) {
        const bld = buildingsData.find(b => b.code === bldCode || b.shortname === bldCode);
        if (bld) {
            navStack.push({type:'building', id:+bld.id, name:bld.shortname||bld.name});
            if (floor) navStack.push({type:'floor', buildingId:+bld.id, floor:+floor, name:'ชั้น '+floor});
        }
    }
    navStack.push({type:'room', id:+roomId, name:roomName});
    renderCurrentLevel();
    updateBreadcrumb();
}

function sdsGoRoomFromMyRoom(roomId) {
    const rd = mrAllRoomsMap[roomId]||{};
    sdsGoRoom(roomId, rd.name||'ห้อง #'+roomId, rd.bld_code||'', +rd.floor||0);
}

init();
</script>
</body></html>
