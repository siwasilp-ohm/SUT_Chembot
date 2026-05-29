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
    </div>
</div>

<!-- ═══════ Stats Row ═══════ -->
<div class="loc-stats" id="statsRow">
    <div class="loc-stat" style="--lc:#4338ca;--lb:#eef2ff">
        <div class="loc-si" style="background:#eef2ff;color:#4338ca"><i class="fas fa-building"></i></div>
        <div><div class="loc-sv" id="statBuildings">—</div><div class="loc-sl">อาคาร</div></div>
    </div>
    <div class="loc-stat" style="--lc:#c2410c;--lb:#fff7ed">
        <div class="loc-si" style="background:#fff7ed;color:#c2410c"><i class="fas fa-layer-group"></i></div>
        <div><div class="loc-sv" id="statFloors">—</div><div class="loc-sl">ชั้น</div></div>
    </div>
    <div class="loc-stat" style="--lc:#0369a1;--lb:#e0f2fe">
        <div class="loc-si" style="background:#e0f2fe;color:#0369a1"><i class="fas fa-door-open"></i></div>
        <div><div class="loc-sv" id="statRooms">—</div><div class="loc-sl">ห้อง</div></div>
    </div>
    <div class="loc-stat" style="--lc:#7c3aed;--lb:#f3e8ff">
        <div class="loc-si" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-archive"></i></div>
        <div><div class="loc-sv" id="statCabinets">—</div><div class="loc-sl">ตู้เก็บ</div></div>
    </div>
    <div class="loc-stat" style="--lc:#16a34a;--lb:#dcfce7">
        <div class="loc-si" style="background:#dcfce7;color:#16a34a"><i class="fas fa-flask"></i></div>
        <div><div class="loc-sv" id="statContainers">—</div><div class="loc-sl">ภาชนะ</div></div>
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
.loc-stat{background:#fff;border-radius:var(--loc-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--loc-sh);border:1px solid var(--border);transition:all .15s}
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
.loc-tree-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
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
</style>

<?php Layout::endContent(); ?>

<script>
const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const IS_MANAGER = <?php echo $isManager ? 'true' : 'false'; ?>;
let currentView = localStorage.getItem('locView') || 'tree';
let navStack = [];
let buildingsData = [];

// ═══════ Init ═══════
async function init() {
    loadStats();
    loadBuildings();
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
            // floors stat (may not exist in API yet)
            const flEl = document.getElementById('statFloors');
            if (flEl) flEl.textContent = d.data.floors ?? '—';
        }
    } catch(e) { console.error(e); }
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

        if (currentView === 'tree') {
            el.innerHTML = `<div class="loc-panel">
                <div class="loc-panel-hd">
                    <div class="loc-panel-hd-title"><i class="fas fa-door-open"></i> ห้องในชั้นนี้</div>
                    <span style="font-size:11px;color:var(--c3)">${rooms.length} ห้อง</span>
                </div>
                <div class="loc-tree-list">
                    ${rooms.map(r => `
                        <div class="loc-tree-item" onclick="navigateTo('room',${r.id},'${esc(r.name)}')">
                            <div class="loc-tree-ic" style="background:#e0f2fe;color:#0369a1"><i class="fas fa-door-open"></i></div>
                            <div class="loc-tree-name">${esc(r.name)}</div>
                            ${r.code ? `<span class="loc-tree-badge">${esc(r.code)}</span>` : ''}
                            ${statusDot(r.status_text)}
                            ${r.cabinet_count > 0 ? `<span class="loc-tree-badge">${r.cabinet_count} ตู้</span>` : ''}
                            <i class="fas fa-chevron-right loc-tree-arr"></i>
                        </div>
                    `).join('')}
                </div>
            </div>`;
        } else if (currentView === 'grid') {
            el.innerHTML = `<div class="loc-grid">${rooms.map(r => `
                <div class="loc-card" onclick="navigateTo('room',${r.id},'${esc(r.name)}')">
                    <div class="loc-card-hd">
                        <div class="loc-card-ic" style="background:#e0f2fe;color:#0369a1"><i class="fas fa-door-open"></i></div>
                        <div style="flex:1;min-width:0">
                            <div class="loc-card-nm" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.name)}</div>
                            <div class="loc-card-sub">${esc(r.code||'')}${r.area_sqm?' · '+r.area_sqm+' ตร.ม.':''}</div>
                        </div>
                        ${statusBadge(r.status_text)}
                    </div>
                    <div class="loc-card-bd">
                        <div class="loc-card-stats">
                            <div class="loc-card-stat"><div class="v">${r.cabinet_count}</div><div class="l">ตู้เก็บ</div></div>
                            <div class="loc-card-stat"><div class="v">${r.capacity_persons||'—'}</div><div class="l">ความจุ(คน)</div></div>
                        </div>
                    </div>
                </div>`).join('')}</div>`;
        } else {
            el.innerHTML = `<div class="loc-panel"><div class="loc-tw"><table class="loc-t">
                <thead><tr><th>รหัส</th><th>ชื่อห้อง</th><th>สถานะ</th><th style="text-align:center">พื้นที่</th><th style="text-align:center">ความจุ</th><th style="text-align:center">ตู้</th></tr></thead>
                <tbody>${rooms.map(r => `
                    <tr onclick="navigateTo('room',${r.id},'${esc(r.name)}')">
                        <td><span style="font-size:10px;padding:2px 8px;border-radius:6px;background:#e0f2fe;color:#0369a1;font-weight:700">${esc(r.code||'—')}</span></td>
                        <td style="font-weight:600;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.name)}</td>
                        <td>${statusBadge(r.status_text)}</td>
                        <td style="text-align:center">${r.area_sqm ? r.area_sqm+' ตร.ม.' : '—'}</td>
                        <td style="text-align:center">${r.capacity_persons||'—'}</td>
                        <td style="text-align:center;font-weight:700">${r.cabinet_count}</td>
                    </tr>`).join('')}
                </tbody></table></div></div>`;
        }
    } catch(e) { el.innerHTML = emptyState('fas fa-exclamation-triangle', e.message); }
}

// ═══════ Render Cabinets ═══════
async function loadAndRenderCabinets(el, roomId) {
    el.innerHTML = loading();
    try {
        const d = await apiFetch(`/v1/api/locations.php?action=cabinets&room_id=${roomId}`);
        if (!d.success || !d.data.length) {
            el.innerHTML = emptyState('fas fa-archive', 'ยังไม่มีตู้เก็บในห้องนี้') +
                (IS_MANAGER ? `<div style="text-align:center;margin-top:12px"><button onclick="showAddModal('cabinet',{room_id:${roomId}})" class="loc-btn loc-btn-p"><i class="fas fa-plus"></i> เพิ่มตู้เก็บ</button></div>` : '');
            return;
        }
        const cabs = d.data;

        if (currentView === 'tree') {
            el.innerHTML = `<div class="loc-panel">
                <div class="loc-panel-hd">
                    <div class="loc-panel-hd-title"><i class="fas fa-archive"></i> ตู้เก็บ</div>
                    <span style="font-size:11px;color:var(--c3)">${cabs.length} ตู้</span>
                </div>
                <div class="loc-tree-list">
                    ${cabs.map(c => `
                        <div class="loc-tree-item" onclick="navigateTo('cabinet',${c.id},'${esc(c.name)}')">
                            <div class="loc-tree-ic" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-archive"></i></div>
                            <div class="loc-tree-name">${esc(c.name)}</div>
                            <span class="loc-tree-badge">${cabinetTypeLabel(c.type)}</span>
                            <span class="loc-tree-badge">${c.shelf_count} ชั้นวาง</span>
                            ${c.container_count > 0 ? `<span class="loc-tree-badge" style="background:#dcfce7;color:#15803d">${c.container_count} ภาชนะ</span>` : ''}
                            <i class="fas fa-chevron-right loc-tree-arr"></i>
                        </div>
                    `).join('')}
                </div>
                ${IS_MANAGER ? `<div class="loc-add-row"><button onclick="showAddModal('cabinet',{room_id:${roomId}})" class="loc-btn loc-btn-g" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> เพิ่มตู้เก็บ</button></div>` : ''}
            </div>`;
        } else if (currentView === 'grid') {
            el.innerHTML = `<div class="loc-grid">${cabs.map(c => `
                <div class="loc-card" onclick="navigateTo('cabinet',${c.id},'${esc(c.name)}')">
                    <div class="loc-card-hd">
                        <div class="loc-card-ic" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-archive"></i></div>
                        <div><div class="loc-card-nm">${esc(c.name)}</div><div class="loc-card-sub">${cabinetTypeLabel(c.type)}</div></div>
                    </div>
                    <div class="loc-card-bd">
                        <div class="loc-card-stats">
                            <div class="loc-card-stat"><div class="v">${c.shelf_count}</div><div class="l">ชั้นวาง</div></div>
                            <div class="loc-card-stat"><div class="v">${c.container_count}</div><div class="l">ภาชนะ</div></div>
                        </div>
                    </div>
                </div>`).join('')}</div>`;
        } else {
            el.innerHTML = `<div class="loc-panel"><div class="loc-tw"><table class="loc-t">
                <thead><tr><th>ชื่อตู้</th><th>ประเภท</th><th style="text-align:center">ชั้นวาง</th><th style="text-align:center">ภาชนะ</th></tr></thead>
                <tbody>${cabs.map(c => `
                    <tr onclick="navigateTo('cabinet',${c.id},'${esc(c.name)}')">
                        <td style="font-weight:600"><i class="fas fa-archive" style="color:#7c3aed;margin-right:7px"></i>${esc(c.name)}</td>
                        <td style="color:var(--c3)">${cabinetTypeLabel(c.type)}</td>
                        <td style="text-align:center">${c.shelf_count}</td>
                        <td style="text-align:center;font-weight:700">${c.container_count}</td>
                    </tr>`).join('')}
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
            el.innerHTML = emptyState('fas fa-layer-group', 'ยังไม่มีชั้นวาง') +
                (IS_MANAGER ? `<div style="text-align:center;margin-top:12px"><button onclick="showAddModal('shelf',{cabinet_id:${cabinetId}})" class="loc-btn loc-btn-p"><i class="fas fa-plus"></i> เพิ่มชั้นวาง</button></div>` : '');
            return;
        }
        const shelves = d.data;
        el.innerHTML = `<div class="loc-panel">
            <div class="loc-panel-hd">
                <div class="loc-panel-hd-title"><i class="fas fa-layer-group"></i> ชั้นวาง</div>
                <span style="font-size:11px;color:var(--c3)">${shelves.length} ชั้น</span>
            </div>
            <div class="loc-tree-list">
                ${shelves.map(s => `
                    <div class="loc-tree-item" onclick="navigateTo('shelf',${s.id},'${esc(s.name)}')">
                        <div class="loc-tree-ic" style="background:#ccfbf1;color:#0d9488"><i class="fas fa-layer-group"></i></div>
                        <div class="loc-tree-name">${esc(s.name)} <span style="color:var(--c3);font-size:11px;font-weight:400">(ระดับ ${s.level})</span></div>
                        <span class="loc-tree-badge">${s.slot_count} ช่อง</span>
                        ${s.container_count > 0 ? `<span class="loc-tree-badge" style="background:#dcfce7;color:#15803d">${s.container_count} ภาชนะ</span>` : ''}
                        <i class="fas fa-chevron-right loc-tree-arr"></i>
                    </div>
                `).join('')}
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
            el.innerHTML = emptyState('fas fa-th', 'ยังไม่มีช่องเก็บ') +
                (IS_MANAGER ? `<div style="text-align:center;margin-top:12px"><button onclick="showAddModal('slot',{shelf_id:${shelfId}})" class="loc-btn loc-btn-p"><i class="fas fa-plus"></i> เพิ่มช่อง</button></div>` : '');
            return;
        }
        const slots = d.data;
        const used = slots.filter(s => s.container_id).length;
        el.innerHTML = `<div class="loc-panel">
            <div class="loc-panel-hd">
                <div class="loc-panel-hd-title"><i class="fas fa-th"></i> ช่องเก็บ</div>
                <span style="font-size:11px;color:var(--c3)">${used}/${slots.length} ใช้งาน</span>
            </div>
            <div class="loc-slots">
                ${slots.map(s => `
                    <div class="loc-slot${s.container_id?' used':''}">
                        <div class="loc-slot-code">${esc(s.code||s.name)}</div>
                        <div class="loc-slot-ic">${s.container_id ? '<i class="fas fa-flask" style="color:#16a34a"></i>' : '<i class="fas fa-square" style="color:#e2e8f0"></i>'}</div>
                        ${s.container_id
                            ? `<div class="loc-slot-nm">${esc(s.chemical_name||'')}</div><div class="loc-slot-sub">${esc(s.container_number||'')}</div>`
                            : `<div class="loc-slot-empty">ว่าง</div>`}
                    </div>
                `).join('')}
            </div>
            ${IS_MANAGER ? `<div class="loc-add-row"><button onclick="showAddModal('slot',{shelf_id:${shelfId}})" class="loc-btn loc-btn-g" style="width:100%;justify-content:center"><i class="fas fa-plus"></i> เพิ่มช่อง</button></div>` : ''}
        </div>`;
    } catch(e) { el.innerHTML = emptyState('fas fa-exclamation-triangle', e.message); }
}

// ═══════ Search ═══════
function setupSearch() {
    let timer;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('searchResults').style.display = 'none'; return; }
        timer = setTimeout(() => doSearch(q), 300);
    });
}

async function doSearch(q) {
    try {
        const d = await apiFetch(`/v1/api/locations.php?action=search&q=${encodeURIComponent(q)}`);
        const sr = document.getElementById('searchResults');
        if (!d.success || !d.data.length) {
            sr.innerHTML = `<div class="loc-sr"><div class="loc-sr-hd">ผลการค้นหา</div><div style="padding:20px;text-align:center;color:var(--c3);font-size:13px">ไม่พบผลลัพธ์</div></div>`;
            sr.style.display = 'block';
            return;
        }
        const typeIcon = {building:'fa-building',room:'fa-door-open',cabinet:'fa-archive'};
        const typeBg   = {building:'#eef2ff',room:'#e0f2fe',cabinet:'#f3e8ff'};
        const typeClr  = {building:'#4338ca',room:'#0369a1',cabinet:'#7c3aed'};
        const typeLabel= {building:'อาคาร',room:'ห้อง',cabinet:'ตู้'};
        sr.style.display = 'block';
        sr.innerHTML = `<div class="loc-sr">
            <div class="loc-sr-hd">ผลการค้นหา (${d.data.length})</div>
            ${d.data.map(item => `
                <div class="loc-sr-item" onclick="searchNavigate('${item.type}',${item.id},'${esc(item.name)}',${JSON.stringify(item).replace(/'/g,"\\'")})">
                    <div class="loc-sr-ic" style="background:${typeBg[item.type]||'#f1f5f9'};color:${typeClr[item.type]||'#64748b'}"><i class="fas ${typeIcon[item.type]||'fa-map-marker-alt'}"></i></div>
                    <div style="flex:1;min-width:0">
                        <div class="loc-sr-nm">${esc(item.name)}</div>
                        <div class="loc-sr-sub">${esc(item.building_name||'')}${item.floor?' · ชั้น '+item.floor:''}${item.code?' · '+item.code:''}</div>
                    </div>
                    <span class="loc-sr-tag">${typeLabel[item.type]||item.type}</span>
                </div>
            `).join('')}
        </div>`;
    } catch(e) { console.error(e); }
}

function searchNavigate(type, id, name, item) {
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('searchInput').value = '';
    navStack = [];
    if (type === 'building') {
        navigateTo('building', id, name);
    } else if (type === 'room') {
        if (item.building_name) navigateTo('building', null, item.building_code || item.building_name);
        navigateTo('room', id, name);
    }
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

function cabinetTypeLabel(t) {
    const m = {storage:'ตู้เก็บ',fume_hood:'ตู้ดูดควัน',refrigerator:'ตู้เย็น',freezer:'ตู้แช่แข็ง',safety_cabinet:'ตู้นิรภัย',other:'อื่นๆ'};
    return m[t] || t || 'ตู้เก็บ';
}

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

init();
</script>
</body></html>
