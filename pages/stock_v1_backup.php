<?php
/**
 * Chemical Stock Management Page (ขวดสารเคมีในคลัง)
 * 
 * Role-based views:
 *   admin       → Full CRUD, import/export, see ALL
 *   ceo         → Read-only, see ALL, export
 *   lab_manager → See own + team, manage own
 *   user        → See/manage own only
 */
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang  = I18n::getCurrentLang();
$role  = $user['role_name'];
$uid   = $user['id'];
$isAdmin = $role === 'admin';
$isCeo   = $role === 'ceo';
$isLab   = $role === 'lab_manager';
$canEdit = in_array($role, ['admin', 'lab_manager', 'user']);
Layout::head($lang === 'th' ? 'คลังสารเคมี — ขวดสาร' : 'Chemical Stock');
?>
<style>
/* ═══ Stats Cards ═══ */
.stk-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
.stk-stat{background:#fff;border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 6px rgba(0,0,0,.06);border:1px solid var(--border);transition:transform .15s}
.stk-stat:hover{transform:translateY(-2px)}
.stk-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.stk-stat-val{font-size:22px;font-weight:800;color:var(--c1);line-height:1.1}
.stk-stat-lbl{font-size:11px;color:var(--c3);margin-top:2px}

/* ═══ Toolbar ═══ */
.stk-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:16px}
.stk-search{flex:1;min-width:200px;position:relative}
.stk-search input{width:100%;padding:9px 12px 9px 36px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;background:#fff;color:var(--c1);transition:border .15s}
.stk-search input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,138,92,.1)}
.stk-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:13px}
.stk-filter{padding:8px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:12px;background:#fff;cursor:pointer;display:flex;align-items:center;gap:6px;color:var(--c1);font-family:inherit;transition:all .12s}
.stk-filter:hover{border-color:var(--accent);color:var(--accent)}
.stk-filter.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.stk-btn{padding:8px 16px;border:none;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s}
.stk-btn-primary{background:var(--accent);color:#fff}.stk-btn-primary:hover{filter:brightness(1.08)}
.stk-btn-outline{background:#fff;color:var(--accent);border:1.5px solid var(--accent)}.stk-btn-outline:hover{background:var(--accent);color:#fff}
.stk-btn-danger{background:#dc2626;color:#fff}.stk-btn-danger:hover{background:#b91c1c}
.stk-btn-secondary{background:#f1f5f9;color:#475569;border:1px solid var(--border)}.stk-btn-secondary:hover{background:#e2e8f0}

/* ═══ View Toggle ═══ */
.stk-view-toggle{display:flex;border:1.5px solid var(--border);border-radius:10px;overflow:hidden}
.stk-view-toggle button{padding:7px 12px;border:none;background:#fff;color:var(--c3);cursor:pointer;font-size:13px;transition:all .12s}
.stk-view-toggle button.active{background:var(--accent);color:#fff}

/* ═══ Filter Panel ═══ */
.stk-filter-panel{display:none;background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
.stk-filter-panel.show{display:block}
.stk-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.stk-filter-field label{font-size:11px;font-weight:600;color:var(--c3);display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
.stk-filter-field select{width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff;color:var(--c1)}
.stk-filter-field select:focus{outline:none;border-color:var(--accent)}
.stk-filter-actions{display:flex;gap:8px;margin-top:12px;justify-content:flex-end}

/* ═══ Table View ═══ */
.stk-table-wrap{overflow-x:auto;border-radius:12px;border:1px solid var(--border);background:#fff}
.stk-table{width:100%;border-collapse:collapse;font-size:12px}
.stk-table th{background:#f8fafc;padding:10px 12px;text-align:left;font-weight:700;color:var(--c3);font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);white-space:nowrap;cursor:pointer;user-select:none;transition:color .12s}
.stk-table th:hover{color:var(--accent)}
.stk-table th.sorted{color:var(--accent)}
.stk-table th .sort-icon{margin-left:4px;font-size:9px;opacity:.5}
.stk-table th.sorted .sort-icon{opacity:1}
.stk-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;color:var(--c1);vertical-align:middle}
.stk-table tr:hover{background:#f0fdf4}
.stk-table tr.selected{background:#ecfdf5}

/* ═══ Grid/Card View ═══ */
.stk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
.stk-card{background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:16px;transition:all .15s;cursor:pointer;position:relative;overflow:hidden}
.stk-card:hover{border-color:var(--accent);box-shadow:0 4px 20px rgba(26,138,92,.1);transform:translateY(-2px)}
.stk-card-head{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px}
.stk-card-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.stk-card-title{font-size:13px;font-weight:700;color:var(--c1);line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.stk-card-code{font-size:10px;color:var(--c3);font-family:'Courier New',monospace;margin-top:2px}
.stk-card-meta{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.stk-card-tag{font-size:10px;padding:3px 8px;border-radius:6px;font-weight:600}
.stk-card-bar{height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-bottom:8px}
.stk-card-bar-fill{height:100%;border-radius:3px;transition:width .3s}
.stk-card-footer{display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--c3)}
.stk-card-owner{display:flex;align-items:center;gap:6px}
.stk-card-owner-avatar{width:22px;height:22px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700}

/* ═══ Status Badges ═══ */
.stk-badge{font-size:10px;padding:3px 8px;border-radius:6px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.stk-badge-active{background:#dcfce7;color:#15803d}
.stk-badge-low{background:#fef9c3;color:#a16207}
.stk-badge-empty{background:#fee2e2;color:#dc2626}
.stk-badge-expired{background:#fce7f3;color:#be185d}
.stk-badge-disposed{background:#f1f5f9;color:#64748b}

/* ═══ Progress Bar Colors ═══ */
.bar-full{background:linear-gradient(90deg,#22c55e,#16a34a)}
.bar-mid{background:linear-gradient(90deg,#eab308,#f59e0b)}
.bar-low{background:linear-gradient(90deg,#ef4444,#dc2626)}

/* ═══ Pagination ═══ */
.stk-pager{display:flex;align-items:center;justify-content:center;gap:4px;margin-top:16px}
.stk-pager button{width:34px;height:34px;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--c1);cursor:pointer;font-size:12px;font-weight:600;transition:all .12s;display:flex;align-items:center;justify-content:center}
.stk-pager button:hover{border-color:var(--accent);color:var(--accent)}
.stk-pager button.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.stk-pager button:disabled{opacity:.3;cursor:default}
.stk-pager-info{font-size:11px;color:var(--c3);margin:0 8px}

/* ═══ Detail Modal ═══ */
.stk-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .2s}
.stk-modal-overlay.show{opacity:1;visibility:visible}
.stk-modal{background:#fff;border-radius:18px;width:94%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.92);transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
.stk-modal-overlay.show .stk-modal{transform:scale(1)}
.stk-modal-head{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between}
.stk-modal-head h3{font-size:16px;font-weight:700;color:var(--c1);display:flex;align-items:center;gap:8px}
.stk-modal-close{width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:14px;color:var(--c3);display:flex;align-items:center;justify-content:center;transition:all .12s}
.stk-modal-close:hover{background:#fee2e2;color:#dc2626}
.stk-modal-body{padding:16px 24px 24px}
.stk-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.stk-detail-field{margin-bottom:2px}
.stk-detail-label{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.stk-detail-value{font-size:13px;color:var(--c1);font-weight:500}
.stk-detail-full{grid-column:1/-1}
.stk-detail-bar{margin:8px 0}
.stk-detail-actions{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}

/* ═══ Import Modal ═══ */
.stk-import-zone{border:2px dashed var(--border);border-radius:12px;padding:32px;text-align:center;transition:all .15s;cursor:pointer}
.stk-import-zone:hover,.stk-import-zone.dragover{border-color:var(--accent);background:#f0fdf4}
.stk-import-zone i{font-size:32px;color:var(--accent);margin-bottom:8px}
.stk-import-zone p{font-size:13px;color:var(--c3)}

/* ═══ Add Modal Form ═══ */
.stk-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.stk-form-full{grid-column:1/-1}
.stk-form-field label{font-size:11px;font-weight:600;color:var(--c3);display:block;margin-bottom:4px}
.stk-form-field input,.stk-form-field select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:#fff;color:var(--c1)}
.stk-form-field input:focus,.stk-form-field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,138,92,.1)}

/* ═══ My Stock Section ═══ */
.stk-my-banner{background:linear-gradient(135deg,#0f766e,#14b8a6);border-radius:14px;padding:18px 22px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:20px}
.stk-my-banner-icon{width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stk-my-banner h3{font-size:15px;font-weight:700;margin:0 0 2px}
.stk-my-banner p{font-size:12px;opacity:.85;margin:0}

/* ═══ Tab Bar ═══ */
.stk-tabs{display:flex;gap:2px;background:#f1f5f9;border-radius:10px;padding:3px;margin-bottom:16px}
.stk-tab{flex:1;padding:8px 4px;text-align:center;font-size:12px;font-weight:600;color:var(--c3);border-radius:8px;cursor:pointer;transition:all .12s;border:none;background:none;font-family:inherit}
.stk-tab:hover{color:var(--c1)}
.stk-tab.active{background:#fff;color:var(--accent);box-shadow:0 1px 3px rgba(0,0,0,.08)}

/* ═══ Toast ═══ */
.stk-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);background:#1a1a2e;color:#fff;padding:12px 24px;border-radius:8px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;z-index:99999;opacity:0;transition:all .3s}
.stk-toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.stk-toast.success{background:#0d6832}.stk-toast.error{background:#c62828}

/* ═══ Empty State ═══ */
.stk-empty{text-align:center;padding:48px 24px;color:var(--c3)}
.stk-empty i{font-size:48px;margin-bottom:12px;opacity:.3}
.stk-empty p{font-size:14px}

/* ═══ Use Modal ═══ */
.stk-use-input{width:120px;text-align:center;font-size:18px;font-weight:700;padding:12px;border:2px solid var(--border);border-radius:10px}
.stk-use-input:focus{border-color:var(--accent);outline:none}

@media(max-width:768px){
    .stk-stats{grid-template-columns:repeat(2,1fr)}
    .stk-toolbar{flex-direction:column;align-items:stretch}
    .stk-search{min-width:100%}
    .stk-grid{grid-template-columns:1fr}
    .stk-detail-grid,.stk-form-grid{grid-template-columns:1fr}
    .stk-tabs{overflow-x:auto;flex-wrap:nowrap}
    .stk-tab{white-space:nowrap;flex:0 0 auto;padding:8px 16px}
}
</style>
<body>
<?php Layout::sidebar('stock'); Layout::beginContent(); ?>
<?php Layout::pageHeader(
    $lang==='th'?'คลังขวดสารเคมี':'Chemical Bottle Stock',
    'fas fa-flask',
    $lang==='th'?'จัดการขวดสารเคมีที่มีอยู่ในคลัง — '.($isAdmin?'ข้อมูลทั้งหมด':($isCeo?'ภาพรวมทั้งหมด':($isLab?'ข้อมูลทีมของคุณ':'ข้อมูลของคุณ')))
               :'Manage chemical bottle inventory — '.($isAdmin?'All Data':($isCeo?'Overview':($isLab?'Your Team':'Your Stock')))
); ?>

<!-- ═══ Stats ═══ -->
<div class="stk-stats" id="statsArea"></div>

<!-- ═══ Tabs ═══ -->
<?php if ($canEdit || $isLab): ?>
<div class="stk-tabs" id="mainTabs">
    <button class="stk-tab active" onclick="switchTab('all')" id="tabAll">
        <i class="fas fa-globe"></i> <?php echo $lang==='th'?($isAdmin||$isCeo?'ทั้งหมด':'ที่เข้าถึงได้'):($isAdmin||$isCeo?'All':'Accessible'); ?>
    </button>
    <button class="stk-tab" onclick="switchTab('my')" id="tabMy">
        <i class="fas fa-user"></i> <?php echo $lang==='th'?'ของฉัน':'My Stock'; ?>
    </button>
</div>
<?php endif; ?>

<!-- ═══ My Stock Banner ═══ -->
<div class="stk-my-banner" id="myBanner" style="display:none">
    <div class="stk-my-banner-icon"><i class="fas fa-flask"></i></div>
    <div>
        <h3 id="myBannerTitle"><?php echo $lang==='th'?'สารเคมีของฉัน':'My Chemical Stock'; ?></h3>
        <p id="myBannerDesc"></p>
    </div>
</div>

<!-- ═══ Toolbar ═══ -->
<div class="stk-toolbar">
    <div class="stk-search">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="<?php echo $lang==='th'?'ค้นหา: รหัสขวด, ชื่อสาร, CAS, ผู้เพิ่ม...':'Search: bottle code, chemical, CAS, owner...'; ?>">
    </div>
    <button class="stk-filter" id="filterToggle" onclick="toggleFilter()">
        <i class="fas fa-filter"></i> <?php echo $lang==='th'?'ตัวกรอง':'Filters'; ?>
    </button>
    <div class="stk-view-toggle">
        <button class="active" id="viewTable" onclick="setView('table')"><i class="fas fa-th-list"></i></button>
        <button id="viewGrid" onclick="setView('grid')"><i class="fas fa-th-large"></i></button>
    </div>
    <?php if ($canEdit): ?>
    <button class="stk-btn stk-btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> <?php echo $lang==='th'?'เพิ่มขวด':'Add Bottle'; ?></button>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <button class="stk-btn stk-btn-outline" onclick="openImportModal()"><i class="fas fa-file-import"></i> Import</button>
    <?php endif; ?>
    <button class="stk-btn stk-btn-secondary" onclick="doExport()"><i class="fas fa-file-export"></i> Export</button>
</div>

<!-- ═══ Filter Panel ═══ -->
<div class="stk-filter-panel" id="filterPanel">
    <div class="stk-filter-grid">
        <div class="stk-filter-field">
            <label><?php echo $lang==='th'?'สถานะ':'Status'; ?></label>
            <select id="fStatus">
                <option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option>
                <option value="active"><?php echo $lang==='th'?'ปกติ':'Active'; ?></option>
                <option value="low"><?php echo $lang==='th'?'เหลือน้อย':'Low'; ?></option>
                <option value="empty"><?php echo $lang==='th'?'หมด':'Empty'; ?></option>
            </select>
        </div>
        <div class="stk-filter-field">
            <label><?php echo $lang==='th'?'หน่วย':'Unit'; ?></label>
            <select id="fUnit"><option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option></select>
        </div>
        <?php if ($isAdmin || $isCeo || $isLab): ?>
        <div class="stk-filter-field">
            <label><?php echo $lang==='th'?'ผู้เพิ่มขวด':'Owner'; ?></label>
            <select id="fOwner"><option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option></select>
        </div>
        <?php endif; ?>
        <div class="stk-filter-field">
            <label><?php echo $lang==='th'?'เรียงตาม':'Sort By'; ?></label>
            <select id="fSort">
                <option value="added_at"><?php echo $lang==='th'?'เวลาเพิ่ม':'Date Added'; ?></option>
                <option value="chemical_name"><?php echo $lang==='th'?'ชื่อสาร':'Chemical Name'; ?></option>
                <option value="bottle_code"><?php echo $lang==='th'?'รหัสขวด':'Bottle Code'; ?></option>
                <option value="remaining_pct"><?php echo $lang==='th'?'% คงเหลือ':'% Remaining'; ?></option>
                <option value="package_size"><?php echo $lang==='th'?'ขนาดบรรจุ':'Package Size'; ?></option>
            </select>
        </div>
    </div>
    <div class="stk-filter-actions">
        <button class="stk-btn stk-btn-secondary" onclick="clearFilters()"><i class="fas fa-undo"></i> <?php echo $lang==='th'?'ล้าง':'Clear'; ?></button>
        <button class="stk-btn stk-btn-primary" onclick="loadData(1)"><i class="fas fa-search"></i> <?php echo $lang==='th'?'กรอง':'Apply'; ?></button>
    </div>
</div>

<!-- ═══ Data Area ═══ -->
<div id="dataArea"></div>

<!-- ═══ Pagination ═══ -->
<div class="stk-pager" id="pagerArea"></div>

<!-- ═══ Detail Modal ═══ -->
<div class="stk-modal-overlay" id="detailOverlay" onclick="if(event.target===this)closeDetail()">
    <div class="stk-modal">
        <div class="stk-modal-head">
            <h3><i class="fas fa-flask" style="color:var(--accent)"></i> <span id="detailTitle"></span></h3>
            <button class="stk-modal-close" onclick="closeDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="stk-modal-body" id="detailBody"></div>
    </div>
</div>

<!-- ═══ Add/Edit Modal ═══ -->
<div class="stk-modal-overlay" id="addOverlay" onclick="if(event.target===this)closeAdd()">
    <div class="stk-modal">
        <div class="stk-modal-head">
            <h3><i class="fas fa-plus-circle" style="color:var(--accent)"></i> <span id="addTitle"><?php echo $lang==='th'?'เพิ่มขวดสารเคมี':'Add Chemical Bottle'; ?></span></h3>
            <button class="stk-modal-close" onclick="closeAdd()"><i class="fas fa-times"></i></button>
        </div>
        <div class="stk-modal-body">
            <form id="addForm" onsubmit="return submitAdd(event)">
                <input type="hidden" id="editId" value="">
                <div class="stk-form-grid">
                    <div class="stk-form-field">
                        <label><?php echo $lang==='th'?'รหัสขวด *':'Bottle Code *'; ?></label>
                        <input type="text" id="fBottleCode" required placeholder="e.g. F01121A5800001">
                    </div>
                    <div class="stk-form-field">
                        <label>CAS / Catalogue No.</label>
                        <input type="text" id="fCasNo" placeholder="e.g. 64-17-5">
                    </div>
                    <div class="stk-form-field stk-form-full">
                        <label><?php echo $lang==='th'?'ชื่อสารเคมี *':'Chemical Name *'; ?></label>
                        <input type="text" id="fChemName" required placeholder="e.g. Ethyl alcohol">
                    </div>
                    <div class="stk-form-field stk-form-full">
                        <label><?php echo $lang==='th'?'เกรด':'Grade'; ?></label>
                        <input type="text" id="fGrade" placeholder="e.g. AR grade, RPE">
                    </div>
                    <div class="stk-form-field">
                        <label><?php echo $lang==='th'?'ขนาดบรรจุ':'Package Size'; ?></label>
                        <input type="number" id="fPkgSize" step="0.01" min="0" placeholder="e.g. 2500">
                    </div>
                    <div class="stk-form-field">
                        <label><?php echo $lang==='th'?'ปริมาณคงเหลือ':'Remaining Qty'; ?></label>
                        <input type="number" id="fRemQty" step="0.01" min="0" placeholder="e.g. 1500">
                    </div>
                    <div class="stk-form-field">
                        <label><?php echo $lang==='th'?'หน่วย':'Unit'; ?></label>
                        <select id="fUnitSel">
                            <option value="">—</option>
                            <option value="กรัม">กรัม</option>
                            <option value="มิลลิกรัม">มิลลิกรัม</option>
                            <option value="ไมโครกรัม">ไมโครกรัม</option>
                            <option value="กิโลกรัม">กิโลกรัม</option>
                            <option value="มิลลิลิตร">มิลลิลิตร</option>
                            <option value="ไมโครลิตร">ไมโครลิตร</option>
                            <option value="ลิตร">ลิตร</option>
                            <option value="ลูกบาศก์เมตร">ลูกบาศก์เมตร</option>
                            <option value="Packs">Packs</option>
                            <option value="Units">Units</option>
                            <option value="Vials">Vials</option>
                        </select>
                    </div>
                    <div class="stk-form-field">
                        <label><?php echo $lang==='th'?'สถานที่จัดเก็บ':'Storage Location'; ?></label>
                        <input type="text" id="fStorage" placeholder="e.g. Center Store">
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end">
                    <button type="button" class="stk-btn stk-btn-secondary" onclick="closeAdd()"><?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></button>
                    <button type="submit" class="stk-btn stk-btn-primary" id="addSubmitBtn"><i class="fas fa-save"></i> <?php echo $lang==='th'?'บันทึก':'Save'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ Import Modal ═══ -->
<div class="stk-modal-overlay" id="importOverlay" onclick="if(event.target===this)closeImport()">
    <div class="stk-modal">
        <div class="stk-modal-head">
            <h3><i class="fas fa-file-import" style="color:#d97706"></i> Import CSV</h3>
            <button class="stk-modal-close" onclick="closeImport()"><i class="fas fa-times"></i></button>
        </div>
        <div class="stk-modal-body">
            <div class="stk-import-zone" id="importZone" onclick="document.getElementById('importFile').click()">
                <input type="file" id="importFile" accept=".csv" style="display:none" onchange="handleImportFile(this)">
                <i class="fas fa-cloud-upload-alt"></i>
                <p><?php echo $lang==='th'?'คลิกหรือลากไฟล์ CSV มาวาง':'Click or drag CSV file here'; ?></p>
                <p style="font-size:11px;color:var(--c3);margin-top:4px"><?php echo $lang==='th'?'รูปแบบ: รหัสขวด, ชื่อสารเคมี, CAS, เกรด, ขนาดบรรจุ, ปริมาณคงเหลือ, หน่วย, ผู้เพิ่ม, เวลาเพิ่ม':'Format: Bottle Code, Chemical Name, CAS, Grade, Pkg Size, Remaining, Unit, Owner, Date'; ?></p>
            </div>
            <div id="importResult" style="display:none;margin-top:16px"></div>
        </div>
    </div>
</div>

<!-- ═══ Use Modal ═══ -->
<div class="stk-modal-overlay" id="useOverlay" onclick="if(event.target===this)closeUse()">
    <div class="stk-modal" style="max-width:400px">
        <div class="stk-modal-head">
            <h3><i class="fas fa-vial" style="color:#d97706"></i> <span id="useTitle"><?php echo $lang==='th'?'บันทึกการใช้งาน':'Record Usage'; ?></span></h3>
            <button class="stk-modal-close" onclick="closeUse()"><i class="fas fa-times"></i></button>
        </div>
        <div class="stk-modal-body" style="text-align:center">
            <p id="useChemName" style="font-weight:700;margin-bottom:4px"></p>
            <p id="useCurrentQty" style="font-size:12px;color:var(--c3);margin-bottom:16px"></p>
            <input type="hidden" id="useStockId">
            <input type="number" class="stk-use-input" id="useAmount" step="0.01" min="0.01" placeholder="0.00">
            <p id="useUnitLabel" style="font-size:12px;color:var(--c3);margin-top:4px"></p>
            <div style="display:flex;gap:8px;margin-top:16px;justify-content:center">
                <button class="stk-btn stk-btn-secondary" onclick="closeUse()"><?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></button>
                <button class="stk-btn stk-btn-primary" onclick="submitUse()"><i class="fas fa-check"></i> <?php echo $lang==='th'?'บันทึก':'Confirm'; ?></button>
            </div>
        </div>
    </div>
</div>

<div class="stk-toast" id="stkToast"></div>
<?php Layout::endContent(); ?>

<script>
const LANG='<?php echo $lang; ?>';
const ROLE='<?php echo $role; ?>';
const UID=<?php echo (int)$uid; ?>;
const IS_ADMIN=<?php echo $isAdmin?'true':'false'; ?>;
const CAN_EDIT=<?php echo $canEdit?'true':'false'; ?>;

let currentView='table';
let currentTab='all';
let currentPage=1;
let currentSort='added_at';
let currentDir='DESC';
let allData=[];

// ══════════════════════════════════════
// INIT
// ══════════════════════════════════════
document.addEventListener('DOMContentLoaded',()=>{
    loadStats();
    loadFilters();
    loadData(1);
    setupSearch();
    setupDragDrop();
});

function setupSearch(){
    let t;
    document.getElementById('searchInput').addEventListener('input',()=>{
        clearTimeout(t);
        t=setTimeout(()=>loadData(1),350);
    });
}

// ══════════════════════════════════════
// STATS
// ══════════════════════════════════════
async function loadStats(){
    try{
        const d=await apiFetch('/v1/api/stock.php?action=stats');
        if(!d.success)return;
        const s=d.data;
        document.getElementById('statsArea').innerHTML=`
            <div class="stk-stat">
                <div class="stk-stat-icon" style="background:#dcfce7;color:#15803d"><i class="fas fa-flask"></i></div>
                <div><div class="stk-stat-val">${num(s.total)}</div><div class="stk-stat-lbl">${LANG==='th'?'ขวดทั้งหมด':'Total Bottles'}</div></div>
            </div>
            <div class="stk-stat">
                <div class="stk-stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-atom"></i></div>
                <div><div class="stk-stat-val">${num(s.unique_chemicals)}</div><div class="stk-stat-lbl">${LANG==='th'?'ชนิดสาร':'Unique Chemicals'}</div></div>
            </div>
            <div class="stk-stat">
                <div class="stk-stat-icon" style="background:#fef9c3;color:#a16207"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="stk-stat-val">${num(s.low)}</div><div class="stk-stat-lbl">${LANG==='th'?'เหลือน้อย':'Low Stock'}</div></div>
            </div>
            <div class="stk-stat">
                <div class="stk-stat-icon" style="background:#fce7f3;color:#be185d"><i class="fas fa-users"></i></div>
                <div><div class="stk-stat-val">${num(s.unique_owners)}</div><div class="stk-stat-lbl">${LANG==='th'?'ผู้ดูแล':'Owners'}</div></div>
            </div>
        `;
    }catch(e){console.error(e)}
}

// ══════════════════════════════════════
// LOAD FILTERS
// ══════════════════════════════════════
async function loadFilters(){
    try{
        const [units,owners]=await Promise.all([
            apiFetch('/v1/api/stock.php?action=units'),
            apiFetch('/v1/api/stock.php?action=owners')
        ]);
        if(units.success){
            const sel=document.getElementById('fUnit');
            units.data.forEach(u=>{
                const o=document.createElement('option');
                o.value=u.unit;o.textContent=u.unit;
                sel.appendChild(o);
            });
        }
        if(owners.success&&document.getElementById('fOwner')){
            const sel=document.getElementById('fOwner');
            owners.data.forEach(o=>{
                const op=document.createElement('option');
                op.value=o.owner_user_id;
                op.textContent=(o.owner_name||o.username)+' ('+o.cnt+')';
                sel.appendChild(op);
            });
        }
    }catch(e){}
}

// ══════════════════════════════════════
// LOAD DATA
// ══════════════════════════════════════
async function loadData(page){
    currentPage=page||1;
    const search=document.getElementById('searchInput').value.trim();
    const status=document.getElementById('fStatus').value;
    const unit=document.getElementById('fUnit').value;
    const owner=document.getElementById('fOwner')?.value||'';
    const sort=document.getElementById('fSort')?.value||currentSort;

    let url='/v1/api/stock.php?action='+(currentTab==='my'?'my':'list');
    url+='&page='+currentPage+'&limit=25';
    url+='&search='+encodeURIComponent(search);
    url+='&sort='+sort+'&dir='+currentDir;
    if(status) url+='&status='+status;
    if(unit) url+='&unit='+encodeURIComponent(unit);
    if(owner&&currentTab!=='my') url+='&owner_id='+owner;

    const area=document.getElementById('dataArea');
    area.innerHTML='<div style="text-align:center;padding:32px;color:var(--c3)"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    try{
        const d=await apiFetch(url);
        if(!d.success)throw new Error(d.error);
        allData=d.data.items;
        renderData(allData);
        renderPager(d.data.pagination);
    }catch(e){
        area.innerHTML='<div class="stk-empty"><i class="fas fa-exclamation-circle"></i><p>'+esc(e.message)+'</p></div>';
    }
}

// ══════════════════════════════════════
// RENDER TABLE
// ══════════════════════════════════════
function renderData(items){
    const area=document.getElementById('dataArea');
    if(!items||!items.length){
        area.innerHTML='<div class="stk-empty"><i class="fas fa-flask"></i><p>'+(LANG==='th'?'ไม่พบข้อมูล':'No data found')+'</p></div>';
        return;
    }
    if(currentView==='table') renderTable(items,area);
    else renderGrid(items,area);
}

function renderTable(items,area){
    const cols=[
        {key:'bottle_code',label:LANG==='th'?'รหัสขวด':'Code'},
        {key:'chemical_name',label:LANG==='th'?'ชื่อสารเคมี':'Chemical Name'},
        {key:'cas_no',label:'CAS No.'},
        {key:'grade',label:LANG==='th'?'เกรด':'Grade'},
        {key:'remaining_pct',label:'%'},
        {key:'remaining_qty',label:LANG==='th'?'คงเหลือ':'Remaining'},
        {key:'unit',label:LANG==='th'?'หน่วย':'Unit'},
        {key:'owner_name',label:LANG==='th'?'ผู้เพิ่ม':'Owner'},
        {key:'status',label:LANG==='th'?'สถานะ':'Status'},
        {key:'_actions',label:''}
    ];

    let html='<div class="stk-table-wrap"><table class="stk-table"><thead><tr>';
    cols.forEach(c=>{
        if(c.key==='_actions'){html+='<th></th>';return;}
        const sorted=currentSort===c.key;
        html+=`<th class="${sorted?'sorted':''}" onclick="sortBy('${c.key}')">${c.label} <i class="fas fa-sort${sorted?(currentDir==='ASC'?'-up':'-down'):''} sort-icon"></i></th>`;
    });
    html+='</tr></thead><tbody>';

    items.forEach(r=>{
        const pct=parseFloat(r.remaining_pct)||0;
        const barClass=pct>50?'bar-full':pct>15?'bar-mid':'bar-low';
        const ownerInitial=(r.owner_first||r.owner_name||'?').charAt(0);

        html+=`<tr onclick="openDetail(${r.id})" style="cursor:pointer">
            <td><code style="font-size:11px;background:#f1f5f9;padding:2px 6px;border-radius:4px">${esc(r.bottle_code)}</code></td>
            <td style="max-width:200px"><div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.chemical_name)}">${esc(r.chemical_name)}</div></td>
            <td style="font-size:11px;color:var(--c3)">${esc(r.cas_no||'—')}</td>
            <td style="font-size:11px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.grade||'')}">${esc(shortGrade(r.grade)||'—')}</td>
            <td>
                <div style="display:flex;align-items:center;gap:6px">
                    <div style="width:40px;height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden"><div style="height:100%;width:${Math.min(pct,100)}%;border-radius:3px" class="${barClass}"></div></div>
                    <span style="font-size:10px;font-weight:700;color:${pct>50?'#16a34a':pct>15?'#a16207':'#dc2626'}">${Math.round(pct)}%</span>
                </div>
            </td>
            <td style="font-weight:600">${numFmt(r.remaining_qty)}/${numFmt(r.package_size)}</td>
            <td style="font-size:11px">${esc(r.unit||'—')}</td>
            <td>
                <div style="display:flex;align-items:center;gap:5px">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700">${esc(ownerInitial)}</div>
                    <span style="font-size:11px;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(r.owner_name||'')}">${esc(shortName(r.owner_name))}</span>
                </div>
            </td>
            <td><span class="stk-badge stk-badge-${r.status}">${statusLabel(r.status)}</span></td>
            <td onclick="event.stopPropagation()">
                ${CAN_EDIT&&(IS_ADMIN||parseInt(r.owner_user_id)===UID)?
                    `<button class="stk-btn stk-btn-secondary" style="padding:4px 8px;font-size:10px" onclick="openUse(${r.id})" title="${LANG==='th'?'บันทึกการใช้':'Record Use'}"><i class="fas fa-vial"></i></button>`:''}
            </td>
        </tr>`;
    });

    html+='</tbody></table></div>';
    area.innerHTML=html;
}

function renderGrid(items,area){
    let html='<div class="stk-grid">';
    items.forEach(r=>{
        const pct=parseFloat(r.remaining_pct)||0;
        const barClass=pct>50?'bar-full':pct>15?'bar-mid':'bar-low';
        const ownerInitial=(r.owner_first||r.owner_name||'?').charAt(0);
        const iconBg=pct>50?'#dcfce7':pct>15?'#fef9c3':'#fee2e2';
        const iconColor=pct>50?'#15803d':pct>15?'#a16207':'#dc2626';

        html+=`<div class="stk-card" onclick="openDetail(${r.id})">
            <div class="stk-card-head">
                <div class="stk-card-icon" style="background:${iconBg};color:${iconColor}"><i class="fas fa-flask"></i></div>
                <div style="flex:1;min-width:0">
                    <div class="stk-card-title">${esc(r.chemical_name)}</div>
                    <div class="stk-card-code">${esc(r.bottle_code)}</div>
                </div>
            </div>
            <div class="stk-card-meta">
                ${r.cas_no?`<span class="stk-card-tag" style="background:#f1f5f9;color:#475569">${esc(r.cas_no)}</span>`:''}
                <span class="stk-badge stk-badge-${r.status}">${statusLabel(r.status)}</span>
                ${r.grade?`<span class="stk-card-tag" style="background:#ede9fe;color:#6d28d9">${esc(shortGrade(r.grade))}</span>`:''}
            </div>
            <div class="stk-card-bar"><div class="stk-card-bar-fill ${barClass}" style="width:${Math.min(pct,100)}%"></div></div>
            <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:8px">
                <span style="font-weight:700">${numFmt(r.remaining_qty)} / ${numFmt(r.package_size)} ${esc(r.unit||'')}</span>
                <span style="font-weight:700;color:${pct>50?'#16a34a':pct>15?'#a16207':'#dc2626'}">${Math.round(pct)}%</span>
            </div>
            <div class="stk-card-footer">
                <div class="stk-card-owner">
                    <div class="stk-card-owner-avatar">${esc(ownerInitial)}</div>
                    <span>${esc(shortName(r.owner_name))}</span>
                </div>
                <span>${formatDate(r.added_at)}</span>
            </div>
        </div>`;
    });
    html+='</div>';
    area.innerHTML=html;
}

// ══════════════════════════════════════
// PAGINATION
// ══════════════════════════════════════
function renderPager(pg){
    if(!pg||pg.pages<=1){document.getElementById('pagerArea').innerHTML='';return;}
    let html='';
    html+=`<button ${pg.page<=1?'disabled':''} onclick="loadData(${pg.page-1})"><i class="fas fa-chevron-left"></i></button>`;
    
    const maxShow=5;
    let start=Math.max(1,pg.page-Math.floor(maxShow/2));
    let end=Math.min(pg.pages,start+maxShow-1);
    if(end-start<maxShow-1) start=Math.max(1,end-maxShow+1);
    
    if(start>1){html+=`<button onclick="loadData(1)">1</button>`;if(start>2)html+=`<span class="stk-pager-info">...</span>`}
    for(let i=start;i<=end;i++){
        html+=`<button class="${i===pg.page?'active':''}" onclick="loadData(${i})">${i}</button>`;
    }
    if(end<pg.pages){if(end<pg.pages-1)html+=`<span class="stk-pager-info">...</span>`;html+=`<button onclick="loadData(${pg.pages})">${pg.pages}</button>`}
    
    html+=`<button ${pg.page>=pg.pages?'disabled':''} onclick="loadData(${pg.page+1})"><i class="fas fa-chevron-right"></i></button>`;
    html+=`<span class="stk-pager-info">${num(pg.total)} ${LANG==='th'?'รายการ':'items'}</span>`;
    
    document.getElementById('pagerArea').innerHTML=html;
}

// ══════════════════════════════════════
// DETAIL MODAL
// ══════════════════════════════════════
async function openDetail(id){
    document.getElementById('detailOverlay').classList.add('show');
    document.getElementById('detailTitle').textContent=LANG==='th'?'กำลังโหลด...':'Loading...';
    document.getElementById('detailBody').innerHTML='<div style="text-align:center;padding:24px"><i class="fas fa-spinner fa-spin fa-2x" style="color:var(--c3)"></i></div>';

    try{
        const d=await apiFetch('/v1/api/stock.php?action=detail&id='+id);
        if(!d.success)throw new Error(d.error);
        const r=d.data;
        const pct=parseFloat(r.remaining_pct)||0;
        const barClass=pct>50?'bar-full':pct>15?'bar-mid':'bar-low';

        document.getElementById('detailTitle').textContent=r.chemical_name;
        
        let html=`
        <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span style="font-size:12px;font-weight:600;color:var(--c3)">${LANG==='th'?'ปริมาณคงเหลือ':'Remaining'}</span>
                <span style="font-size:14px;font-weight:800;color:${pct>50?'#16a34a':pct>15?'#a16207':'#dc2626'}">${Math.round(pct)}%</span>
            </div>
            <div class="stk-card-bar" style="height:10px;border-radius:5px"><div class="stk-card-bar-fill ${barClass}" style="height:100%;width:${Math.min(pct,100)}%"></div></div>
            <div style="text-align:center;font-size:13px;font-weight:700;margin-top:6px">${numFmt(r.remaining_qty)} / ${numFmt(r.package_size)} ${esc(r.unit||'')}</div>
        </div>
        <div class="stk-detail-grid">
            <div class="stk-detail-field"><div class="stk-detail-label">${LANG==='th'?'รหัสขวด':'Bottle Code'}</div><div class="stk-detail-value"><code>${esc(r.bottle_code)}</code></div></div>
            <div class="stk-detail-field"><div class="stk-detail-label">CAS No.</div><div class="stk-detail-value">${esc(r.cas_no||'—')}</div></div>
            <div class="stk-detail-field stk-detail-full"><div class="stk-detail-label">${LANG==='th'?'เกรด':'Grade'}</div><div class="stk-detail-value">${esc(r.grade||'—')}</div></div>
            <div class="stk-detail-field"><div class="stk-detail-label">${LANG==='th'?'สถานะ':'Status'}</div><div class="stk-detail-value"><span class="stk-badge stk-badge-${r.status}">${statusLabel(r.status)}</span></div></div>
            <div class="stk-detail-field"><div class="stk-detail-label">${LANG==='th'?'สถานที่':'Location'}</div><div class="stk-detail-value">${esc(r.storage_location||'—')}</div></div>
            <div class="stk-detail-field"><div class="stk-detail-label">${LANG==='th'?'ผู้เพิ่มขวด':'Added By'}</div><div class="stk-detail-value">${esc(r.owner_name||'—')}</div></div>
            <div class="stk-detail-field"><div class="stk-detail-label">${LANG==='th'?'เวลาเพิ่ม':'Date Added'}</div><div class="stk-detail-value">${formatDate(r.added_at)}</div></div>
            ${r.owner_department?`<div class="stk-detail-field stk-detail-full"><div class="stk-detail-label">${LANG==='th'?'ฝ่าย/แผนก':'Department'}</div><div class="stk-detail-value">${esc(r.owner_department)}</div></div>`:''}
        </div>`;

        // Linked chemical info
        if(r.linked_chem_name){
            html+=`<div style="margin-top:14px;padding:12px;background:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0">
                <div style="font-size:11px;font-weight:700;color:#15803d;margin-bottom:6px"><i class="fas fa-link"></i> ${LANG==='th'?'ข้อมูลเชื่อมโยง':'Linked Chemical Data'}</div>
                <div style="font-size:12px;color:var(--c1)">${esc(r.linked_chem_name)}</div>
                ${r.molecular_formula?`<div style="font-size:11px;color:var(--c3);margin-top:2px">Formula: ${esc(r.molecular_formula)}</div>`:''}
                ${r.signal_word?`<div style="font-size:11px;margin-top:2px"><span class="stk-badge" style="background:${r.signal_word==='Danger'?'#fee2e2':'#fef9c3'};color:${r.signal_word==='Danger'?'#dc2626':'#a16207'}">${esc(r.signal_word)}</span></div>`:''}
            </div>`;
        }

        // Actions
        const canManage=IS_ADMIN||parseInt(r.owner_user_id)===UID;
        if(CAN_EDIT&&canManage){
            html+=`<div class="stk-detail-actions">
                <button class="stk-btn stk-btn-primary" onclick="closeDetail();openUse(${r.id})"><i class="fas fa-vial"></i> ${LANG==='th'?'บันทึกการใช้':'Record Use'}</button>
                <button class="stk-btn stk-btn-outline" onclick="closeDetail();openEditModal(${r.id})"><i class="fas fa-edit"></i> ${LANG==='th'?'แก้ไข':'Edit'}</button>
                ${IS_ADMIN?`<button class="stk-btn stk-btn-danger" onclick="deleteStock(${r.id})"><i class="fas fa-trash"></i> ${LANG==='th'?'ลบ':'Delete'}</button>`:''}
            </div>`;
        }

        document.getElementById('detailBody').innerHTML=html;
    }catch(e){
        document.getElementById('detailBody').innerHTML='<div style="padding:24px;text-align:center;color:#dc2626">'+esc(e.message)+'</div>';
    }
}

function closeDetail(){document.getElementById('detailOverlay').classList.remove('show')}

// ══════════════════════════════════════
// ADD / EDIT
// ══════════════════════════════════════
function openAddModal(){
    document.getElementById('editId').value='';
    document.getElementById('addTitle').textContent=LANG==='th'?'เพิ่มขวดสารเคมี':'Add Chemical Bottle';
    document.getElementById('addForm').reset();
    document.getElementById('addOverlay').classList.add('show');
}

async function openEditModal(id){
    try{
        const d=await apiFetch('/v1/api/stock.php?action=detail&id='+id);
        if(!d.success)throw new Error(d.error);
        const r=d.data;
        document.getElementById('editId').value=r.id;
        document.getElementById('addTitle').textContent=LANG==='th'?'แก้ไขขวดสารเคมี':'Edit Chemical Bottle';
        document.getElementById('fBottleCode').value=r.bottle_code||'';
        document.getElementById('fCasNo').value=r.cas_no||'';
        document.getElementById('fChemName').value=r.chemical_name||'';
        document.getElementById('fGrade').value=r.grade||'';
        document.getElementById('fPkgSize').value=r.package_size||'';
        document.getElementById('fRemQty').value=r.remaining_qty||'';
        document.getElementById('fUnitSel').value=r.unit||'';
        document.getElementById('fStorage').value=r.storage_location||'';
        document.getElementById('addOverlay').classList.add('show');
    }catch(e){toast('❌ '+e.message,'error')}
}

function closeAdd(){document.getElementById('addOverlay').classList.remove('show')}

async function submitAdd(e){
    e.preventDefault();
    const editId=document.getElementById('editId').value;
    const payload={
        bottle_code:document.getElementById('fBottleCode').value,
        chemical_name:document.getElementById('fChemName').value,
        cas_no:document.getElementById('fCasNo').value,
        grade:document.getElementById('fGrade').value,
        package_size:parseFloat(document.getElementById('fPkgSize').value)||0,
        remaining_qty:parseFloat(document.getElementById('fRemQty').value)||0,
        unit:document.getElementById('fUnitSel').value,
        storage_location:document.getElementById('fStorage').value
    };

    const btn=document.getElementById('addSubmitBtn');
    btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';

    try{
        let d;
        if(editId){
            d=await apiFetch('/v1/api/stock.php?id='+editId,{method:'PUT',body:JSON.stringify(payload)});
        }else{
            d=await apiFetch('/v1/api/stock.php?action=create',{method:'POST',body:JSON.stringify(payload)});
        }
        if(!d.success)throw new Error(d.error);
        toast('✅ '+(LANG==='th'?'บันทึกสำเร็จ':'Saved'),'success');
        closeAdd();
        loadData(currentPage);
        loadStats();
    }catch(e){toast('❌ '+e.message,'error')}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> '+(LANG==='th'?'บันทึก':'Save')}
}

// ══════════════════════════════════════
// USE (Record Usage)
// ══════════════════════════════════════
function openUse(id){
    const r=allData.find(x=>parseInt(x.id)===id);
    if(!r) return;
    document.getElementById('useStockId').value=id;
    document.getElementById('useChemName').textContent=r.chemical_name;
    document.getElementById('useCurrentQty').textContent=(LANG==='th'?'คงเหลือ: ':'Remaining: ')+numFmt(r.remaining_qty)+' '+esc(r.unit||'');
    document.getElementById('useUnitLabel').textContent=r.unit||'';
    document.getElementById('useAmount').value='';
    document.getElementById('useOverlay').classList.add('show');
    setTimeout(()=>document.getElementById('useAmount').focus(),200);
}
function closeUse(){document.getElementById('useOverlay').classList.remove('show')}

async function submitUse(){
    const id=parseInt(document.getElementById('useStockId').value);
    const amount=parseFloat(document.getElementById('useAmount').value);
    if(!amount||amount<=0){toast(LANG==='th'?'กรุณากรอกจำนวน':'Enter amount','error');return}

    try{
        const d=await apiFetch('/v1/api/stock.php?action=use',{method:'POST',body:JSON.stringify({id,amount})});
        if(!d.success)throw new Error(d.error);
        toast('✅ '+(LANG==='th'?'บันทึกการใช้งานเรียบร้อย':'Usage recorded'),'success');
        closeUse();
        loadData(currentPage);
        loadStats();
    }catch(e){toast('❌ '+e.message,'error')}
}

// ══════════════════════════════════════
// DELETE
// ══════════════════════════════════════
async function deleteStock(id){
    if(!confirm(LANG==='th'?'แน่ใจหรือไม่ว่าต้องการลบ?':'Are you sure you want to delete?'))return;
    try{
        const d=await apiFetch('/v1/api/stock.php?id='+id,{method:'DELETE'});
        if(!d.success)throw new Error(d.error);
        toast('✅ '+(LANG==='th'?'ลบสำเร็จ':'Deleted'),'success');
        closeDetail();
        loadData(currentPage);
        loadStats();
    }catch(e){toast('❌ '+e.message,'error')}
}

// ══════════════════════════════════════
// IMPORT
// ══════════════════════════════════════
function openImportModal(){
    document.getElementById('importResult').style.display='none';
    document.getElementById('importFile').value='';
    document.getElementById('importOverlay').classList.add('show');
}
function closeImport(){document.getElementById('importOverlay').classList.remove('show')}

function setupDragDrop(){
    const zone=document.getElementById('importZone');
    if(!zone)return;
    ['dragenter','dragover'].forEach(e=>zone.addEventListener(e,ev=>{ev.preventDefault();zone.classList.add('dragover')}));
    ['dragleave','drop'].forEach(e=>zone.addEventListener(e,ev=>{ev.preventDefault();zone.classList.remove('dragover')}));
    zone.addEventListener('drop',ev=>{
        const f=ev.dataTransfer.files[0];
        if(f) doImport(f);
    });
}

function handleImportFile(input){if(input.files[0])doImport(input.files[0])}

async function doImport(file){
    const result=document.getElementById('importResult');
    result.style.display='block';
    result.innerHTML='<div style="text-align:center;padding:16px"><i class="fas fa-spinner fa-spin fa-2x" style="color:var(--accent)"></i><p style="margin-top:8px;font-size:12px;color:var(--c3)">'+(LANG==='th'?'กำลัง import...':'Importing...')+'</p></div>';

    const fd=new FormData();
    fd.append('file',file);

    try{
        const resp=await fetch('/v1/api/stock.php?action=import',{
            method:'POST',
            body:fd,
            headers:{'Authorization':'Bearer '+getCookie('auth_token')}
        });
        const d=await resp.json();
        if(!d.success)throw new Error(d.error);

        const r=d.data;
        result.innerHTML=`<div style="padding:16px;background:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0">
            <div style="font-weight:700;color:#15803d;margin-bottom:8px"><i class="fas fa-check-circle"></i> ${LANG==='th'?'Import สำเร็จ':'Import Complete'}</div>
            <div style="font-size:13px">✅ ${LANG==='th'?'นำเข้า':'Imported'}: <b>${r.imported}</b></div>
            ${r.errors>0?`<div style="font-size:13px;color:#dc2626">❌ ${LANG==='th'?'ผิดพลาด':'Errors'}: ${r.errors}</div>`:''}
            ${r.error_details&&r.error_details.length?`<div style="margin-top:8px;font-size:11px;color:var(--c3)">${r.error_details.join('<br>')}</div>`:''}
        </div>`;
        loadData(1);
        loadStats();
    }catch(e){
        result.innerHTML=`<div style="padding:16px;background:#fee2e2;border-radius:10px;color:#dc2626"><i class="fas fa-exclamation-circle"></i> ${esc(e.message)}</div>`;
    }
}

// ══════════════════════════════════════
// EXPORT
// ══════════════════════════════════════
function doExport(){
    const search=document.getElementById('searchInput').value.trim();
    let url='/v1/api/stock.php?action=export&search='+encodeURIComponent(search);
    const token=getCookie('auth_token');
    // Open in new tab with auth
    const a=document.createElement('a');
    a.href=url;a.target='_blank';document.body.appendChild(a);a.click();a.remove();
    toast(LANG==='th'?'📥 กำลังดาวน์โหลด...':'📥 Downloading...','success');
}

// ══════════════════════════════════════
// TABS, VIEW, SORT, FILTER
// ══════════════════════════════════════
function switchTab(tab){
    currentTab=tab;
    document.getElementById('tabAll')?.classList.toggle('active',tab==='all');
    document.getElementById('tabMy')?.classList.toggle('active',tab==='my');
    document.getElementById('myBanner').style.display=tab==='my'?'flex':'none';
    if(tab==='my'){
        document.getElementById('myBannerDesc').textContent=LANG==='th'?'แสดงเฉพาะสารเคมีที่คุณเป็นผู้เพิ่ม':'Showing only chemicals you added';
    }
    loadData(1);
}

function setView(v){
    currentView=v;
    document.getElementById('viewTable').classList.toggle('active',v==='table');
    document.getElementById('viewGrid').classList.toggle('active',v==='grid');
    renderData(allData);
}

function sortBy(key){
    if(currentSort===key) currentDir=currentDir==='ASC'?'DESC':'ASC';
    else{currentSort=key;currentDir='ASC'}
    document.getElementById('fSort').value=key;
    loadData(currentPage);
}

function toggleFilter(){
    const p=document.getElementById('filterPanel');
    p.classList.toggle('show');
    document.getElementById('filterToggle').classList.toggle('active',p.classList.contains('show'));
}

function clearFilters(){
    document.getElementById('fStatus').value='';
    document.getElementById('fUnit').value='';
    if(document.getElementById('fOwner'))document.getElementById('fOwner').value='';
    document.getElementById('fSort').value='added_at';
    document.getElementById('searchInput').value='';
    loadData(1);
}

// ══════════════════════════════════════
// HELPERS
// ══════════════════════════════════════
function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=String(s);return d.innerHTML}
function num(n){return(n||0).toLocaleString()}
function numFmt(n){const v=parseFloat(n);if(isNaN(v))return '—';return v%1===0?v.toLocaleString():v.toLocaleString(undefined,{minimumFractionDigits:0,maximumFractionDigits:2})}
function statusLabel(s){
    const map={active:LANG==='th'?'ปกติ':'Active',low:LANG==='th'?'เหลือน้อย':'Low',empty:LANG==='th'?'หมด':'Empty',expired:LANG==='th'?'หมดอายุ':'Expired',disposed:LANG==='th'?'กำจัดแล้ว':'Disposed'};
    return map[s]||s;
}
function shortName(name){
    if(!name)return '—';
    return name.replace(/^(นาย|นางสาว|นาง|ดร\.)\s*/,'');
}
function shortGrade(g){
    if(!g)return '';
    return g.length>25?g.substring(0,22)+'...':g;
}
function formatDate(d){
    if(!d)return '—';
    try{const dt=new Date(d);return dt.toLocaleDateString(LANG==='th'?'th-TH':'en-US',{day:'numeric',month:'short',year:'numeric'})}
    catch(e){return d}
}
function getCookie(n){const m=document.cookie.match(new RegExp('(^| )'+n+'=([^;]+)'));return m?m[2]:''}
function toast(msg,type){
    const t=document.getElementById('stkToast');
    t.textContent=msg;t.className='stk-toast '+type+' show';
    setTimeout(()=>t.classList.remove('show'),3000);
}
</script>
</body></html>
