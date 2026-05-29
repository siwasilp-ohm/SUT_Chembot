<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$isAdmin = ($user['role_level'] ?? $user['level'] ?? 0) >= 5;
$isManager = ($user['role_level'] ?? $user['level'] ?? 0) >= 3;
$userId = $user['id'];
Layout::head('ข้อมูลสารเคมี Master');
?>
<body>
<?php Layout::sidebar('chemicals'); Layout::beginContent(); ?>
<?php Layout::pageHeader('ข้อมูลสารเคมี Master', 'fas fa-flask', 'จัดการข้อมูลสารเคมี · SDS · GHS · Safety Data'); ?>

<!-- ═══════ Stats Row ═══════ -->
<div class="cm-stats-row" id="statsRow">
    <div class="cm-stat-card" id="csStat-all" onclick="statClick('all')" style="--ic-bg:#eff6ff;--ic-c:#3b82f6;--ic-shadow:rgba(59,130,246,.15)">
        <div class="cm-stat-ic"><i class="fas fa-flask"></i></div>
        <div class="cm-stat-info"><div class="cm-stat-num" id="sTotalChem">—</div><div class="cm-stat-lbl">สารเคมีทั้งหมด</div></div>
        <div class="cm-stat-arr"><i class="fas fa-chevron-right"></i></div>
    </div>
    <div class="cm-stat-card" id="csStat-cas" onclick="statClick('cas')" style="--ic-bg:#fef3c7;--ic-c:#d97706;--ic-shadow:rgba(217,119,6,.15)">
        <div class="cm-stat-ic"><i class="fas fa-fingerprint"></i></div>
        <div class="cm-stat-info"><div class="cm-stat-num" id="sCas">—</div><div class="cm-stat-lbl">CAS Number</div></div>
        <div class="cm-stat-arr"><i class="fas fa-chevron-right"></i></div>
    </div>
    <div class="cm-stat-card" id="csStat-ghs" onclick="statClick('ghs')" style="--ic-bg:#fce4ec;--ic-c:#c62828;--ic-shadow:rgba(198,40,40,.15)">
        <div class="cm-stat-ic"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="cm-stat-info"><div class="cm-stat-num" id="sGhs">—</div><div class="cm-stat-lbl">GHS Records</div></div>
        <div class="cm-stat-arr"><i class="fas fa-chevron-right"></i></div>
    </div>
    <div class="cm-stat-card" id="csStat-sds" onclick="statClick('sds')" style="--ic-bg:#e8f5e9;--ic-c:#2e7d32;--ic-shadow:rgba(46,125,50,.15)">
        <div class="cm-stat-ic"><i class="fas fa-file-pdf"></i></div>
        <div class="cm-stat-info"><div class="cm-stat-num" id="sSds">—</div><div class="cm-stat-lbl">SDS Files</div></div>
        <div class="cm-stat-arr"><i class="fas fa-chevron-right"></i></div>
    </div>
</div>

<!-- ═══════ Mobile Sticky Search Bar ═══════ -->
<div class="cm-mob-bar" id="cmMobBar">
    <div class="cm-mob-search">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInputMob" placeholder="ค้นหาชื่อสาร, CAS...">
    </div>
    <div class="cm-vw-toggle" id="cmVwMob">
        <button class="active" data-view="grid" onclick="setView('grid')" title="การ์ด"><i class="fas fa-th-large"></i></button>
        <button data-view="table" onclick="setView('table')" title="ตาราง"><i class="fas fa-table"></i></button>
    </div>
</div>

<!-- ═══════ Toolbar (Desktop) ═══════ -->
<div class="cm-toolbar" id="cmToolbar">
    <div class="cm-toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="ค้นหาชื่อสาร, CAS, Catalogue...">
    </div>
    <select id="fState" class="cm-toolbar-select">
        <option value="">สถานะทั้งหมด</option>
        <option value="solid">Solid</option>
        <option value="liquid">Liquid</option>
        <option value="gas">Gas</option>
    </select>
    <select id="fType" class="cm-toolbar-select">
        <option value="">ชนิดทั้งหมด</option>
        <option value="HomogeneousSubstance">Homogeneous</option>
        <option value="HeterogenousSubstance">Heterogeneous</option>
    </select>
    <div class="cm-vw-toggle" id="cmVwDesk">
        <button class="active" id="btnGrid" data-view="grid" onclick="setView('grid')" title="การ์ด"><i class="fas fa-th-large"></i></button>
        <button id="btnTable" data-view="table" onclick="setView('table')" title="ตาราง"><i class="fas fa-table"></i></button>
    </div>
    <?php if($isManager): ?>
    <button onclick="showAddChemical()" class="cm-btn-add"><i class="fas fa-plus"></i> เพิ่มสาร</button>
    <?php endif; ?>
</div>

<!-- ═══════ Content ═══════ -->
<div id="mainContent"></div>

<!-- ═══════ Pagination ═══════ -->
<div class="cm-pagination" id="pagination" style="display:none">
    <span id="pgInfo" class="cm-pg-info"></span>
    <div class="cm-pg-btns">
        <button id="pgPrev" class="cm-pg-btn" onclick="changePage(-1)"><i class="fas fa-chevron-left"></i></button>
        <span id="pgNumbers" style="display:flex;gap:4px;flex-wrap:wrap"></span>
        <button id="pgNext" class="cm-pg-btn" onclick="changePage(1)"><i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<!-- ═══════ Detail Modal ═══════ -->
<div id="detailModal" class="ci-modal-bg">
<div class="ci-modal" style="max-width:860px">
    <div class="ci-modal-hdr">
        <h3 id="detailTitle"><i class="fas fa-flask"></i> รายละเอียดสาร</h3>
        <button class="ci-modal-close" onclick="closeDetail()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ci-modal-body" id="detailBody" style="padding:0"></div>
</div>
</div>

<!-- ═══════ GHS Editor Modal ═══════ -->
<div id="ghsModal" class="ci-modal-bg">
<div class="ci-modal" style="max-width:720px">
    <div class="ci-modal-hdr">
        <h3 id="ghsTitle"><i class="fas fa-exclamation-triangle" style="color:#ef4444"></i> GHS / Safety Data</h3>
        <button class="ci-modal-close" onclick="closeGhs()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ci-modal-body" id="ghsBody" style="max-height:80vh;overflow-y:auto"></div>
</div>
</div>

<!-- ═══════ Upload File Modal ═══════ -->
<div id="uploadModal" class="ci-modal-bg">
<div class="ci-modal" style="max-width:520px">
    <div class="ci-modal-hdr">
        <h3><i class="fas fa-cloud-upload-alt" style="color:#3b82f6"></i> อัปโหลดเอกสาร</h3>
        <button class="ci-modal-close" onclick="closeUpload()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ci-modal-body" id="uploadBody"></div>
</div>
</div>

<!-- ═══════ Edit File Modal ═══════ -->
<div id="editFileModal" class="ci-modal-bg">
<div class="ci-modal" style="max-width:520px">
    <div class="ci-modal-hdr">
        <h3><i class="fas fa-pen" style="color:#d97706"></i> แก้ไขข้อมูลเอกสาร</h3>
        <button class="ci-modal-close" onclick="closeEditFile()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ci-modal-body" id="editFileBody"></div>
</div>
</div>

<!-- ═══════ Add Chemical Modal ═══════ -->
<div id="addModal" class="ci-modal-bg">
<div class="ci-modal" style="max-width:560px">
    <div class="ci-modal-hdr">
        <h3><i class="fas fa-plus-circle" style="color:#3b82f6"></i> เพิ่มสารเคมีใหม่</h3>
        <button class="ci-modal-close" onclick="closeAdd()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ci-modal-body" id="addBody"></div>
</div>
</div>

<!-- ═══════ 3D & AR Viewer PRO Modal ═══════ -->
<div id="arViewerModal" class="ar-modal-bg">
<div class="ar-modal-container">
    <!-- Header -->
    <div class="ar-modal-header">
        <div class="ar-header-left">
            <button class="ar-hdr-btn" onclick="closeArViewer()" title="ปิด"><i class="fas fa-arrow-left"></i></button>
            <div class="ar-header-info">
                <span class="ar-header-label" id="arTitle">โมเดล 3D</span>
                <span class="ar-header-sub" id="arSubtitle"></span>
            </div>
        </div>
        <div class="ar-header-right">
            <span class="ar-provider-badge" id="arProviderBadge" style="display:none"></span>
            <button class="ar-hdr-btn" id="arBtnGyro" onclick="toggleGyro()" title="Gyroscope Control"><i class="fas fa-compass"></i></button>
            <button class="ar-hdr-btn" onclick="toggleArFullscreen()" title="เต็มจอ"><i class="fas fa-expand"></i></button>
        </div>
    </div>

    <!-- 3D Viewer Area -->
    <div class="ar-viewer-area" id="arViewerArea">
        <!-- Loading -->
        <div class="ar-loading" id="arLoading">
            <div class="ar-load-ring"><div></div><div></div><div></div></div>
            <p>กำลังโหลดโมเดล 3D...</p>
        </div>
        <!-- model-viewer or iframe will be injected here -->
        <div id="arModelContainer"></div>
    </div>

    <!-- Bottom Controls -->
    <div class="ar-controls">
        <!-- Chemical Info Card -->
        <div class="ar-chem-card" id="arChemCard" style="display:none">
            <div class="ar-chem-head">
                <div class="ar-chem-cas" id="arChemCas"></div>
                <div class="ar-chem-name" id="arChemName"></div>
            </div>
            <div class="ar-chem-tags" id="arChemTags"></div>
        </div>

        <!-- Action Buttons -->
        <div class="ar-action-bar">
            <button class="ar-action-btn ar-btn-ar" id="arBtnAR" onclick="launchAR()" style="display:none">
                <i class="fas fa-vr-cardboard"></i>
                <span>ดูใน AR</span>
                <small>ใช้กล้องอุปกรณ์</small>
            </button>
            <button class="ar-action-btn ar-btn-spatial" id="arBtnSpatial" onclick="launchSpatialAR()" style="display:none">
                <i class="fas fa-anchor"></i>
                <span>Spatial AR</span>
                <small>ยึดตำแหน่ง</small>
            </button>
            <button class="ar-action-btn" id="arBtnRotate" onclick="toggleArAutoRotate()">
                <i class="fas fa-sync-alt"></i>
                <span>หมุนอัตโนมัติ</span>
            </button>
            <button class="ar-action-btn" id="arBtnReset" onclick="resetArCamera()">
                <i class="fas fa-crosshairs"></i>
                <span>รีเซ็ตมุมมอง</span>
            </button>
            <button class="ar-action-btn" id="arBtnNewTab" onclick="openArNewTab()">
                <i class="fas fa-external-link-alt"></i>
                <span>แท็บใหม่</span>
            </button>
        </div>

        <!-- Model Stats -->
        <div class="ar-stats-bar" id="arStatsBar" style="display:none">
            <span id="arStatVert"></span>
            <span id="arStatTri"></span>
            <span id="arStatMat"></span>
        </div>

        <!-- Spatial Anchor Pill -->
        <div class="ar-anchor-pill" id="arAnchorPill">
            <i class="fas fa-anchor" style="font-size:10px"></i>
            <span id="arAnchorText">Spatial Anchor ยึดตำแหน่งแล้ว — เดินรอบวัตถุได้</span>
        </div>
    </div>

    <!-- AR Status Banner (inside modal, above viewer) -->
    <div class="ar-spatial-status" id="arSpatialStatus">
        <div class="ar-sp-dot" id="arSpDot"></div>
        <span id="arSpText"></span>
    </div>

    <!-- AR Placement Instructions -->
    <div class="ar-sp-instruct" id="arSpInstruct">
        <i class="fas fa-mobile-alt"></i>
        <p id="arSpInstructText">เลื่อนอุปกรณ์ช้าๆ เพื่อสแกนพื้นผิว</p>
    </div>
</div>
</div>

<!-- Google model-viewer for native AR support -->
<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js"></script>

<style>
/* ═══════════════════════════════════════════════════════
   Chemical Master — Modern Responsive UI
   ═══════════════════════════════════════════════════════ */

/* ── Stats Row ── */
.cm-stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.cm-stat-card{background:#fff;border:1.5px solid #e8ecf1;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:transform .15s,box-shadow .15s,border-color .15s,background .15s;overflow:hidden;position:relative;cursor:pointer}
.cm-stat-card::after{content:'';position:absolute;top:0;right:0;width:52px;height:52px;border-radius:0 14px 0 52px;background:var(--ic-c,#888);opacity:.04}
.cm-stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.09)}
.cm-stat-card.active{border-color:var(--ic-c);background:var(--ic-bg);box-shadow:0 0 0 4px var(--ic-shadow,rgba(0,0,0,.06)),0 4px 16px rgba(0,0,0,.08);transform:translateY(-1px)}
.cm-stat-card.active .cm-stat-arr{opacity:1;transform:translateX(0);color:var(--ic-c)}
.cm-stat-arr{display:flex;align-items:center;margin-left:auto;font-size:10px;color:#cbd5e1;opacity:0;transform:translateX(-4px);transition:opacity .15s,transform .15s,color .15s;flex-shrink:0}
.cm-stat-card:hover .cm-stat-arr{opacity:.6;transform:translateX(0)}
.cm-stat-ic{width:44px;height:44px;border-radius:12px;background:var(--ic-bg,#f3f4f6);color:var(--ic-c,#6b7280);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.cm-stat-info{min-width:0}
.cm-stat-num{font-size:22px;font-weight:800;color:#0f172a;line-height:1.1;font-variant-numeric:tabular-nums}
.cm-stat-lbl{font-size:11px;color:#94a3b8;margin-top:2px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* ── Toolbar (Desktop) ── */
.cm-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;background:#fff;border:1.5px solid #e8ecf1;border-radius:14px;padding:10px 14px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
.cm-toolbar-search{flex:1;min-width:220px;display:flex;align-items:center;gap:8px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:0 13px;transition:.2s}
.cm-toolbar-search:focus-within{border-color:var(--accent,#1a8a5c);background:#fff;box-shadow:0 0 0 3px rgba(26,138,92,.09)}
.cm-toolbar-search i{color:#b0bac6;font-size:13px;flex-shrink:0}
.cm-toolbar-search input{flex:1;border:none;background:transparent;font-size:13px;color:#334155;padding:9px 0;outline:none;font-family:inherit}
.cm-toolbar-search input::placeholder{color:#b0bac6}
.cm-toolbar-divider{width:1px;height:28px;background:#e8ecf1;flex-shrink:0}
.cm-toolbar-select{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:12px;font-weight:500;color:#475569;background:#fff;cursor:pointer;font-family:inherit;transition:.15s;outline:none}
.cm-toolbar-select:focus{border-color:var(--accent,#1a8a5c);box-shadow:0 0 0 2px rgba(26,138,92,.08)}
.cm-vw-toggle{display:flex;gap:2px;background:#f1f5f9;border-radius:9px;padding:3px;flex-shrink:0}
.cm-vw-toggle button{width:32px;height:32px;border:none;background:transparent;color:#94a3b8;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:.15s}
.cm-vw-toggle button.active{background:#fff;color:var(--accent,#1a8a5c);box-shadow:0 1px 4px rgba(0,0,0,.1)}
.cm-btn-add{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:var(--accent,#1a8a5c);color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit;transition:.15s;flex-shrink:0;box-shadow:0 2px 8px rgba(26,138,92,.2)}
.cm-btn-add:hover{background:var(--accent-h,#166b47);box-shadow:0 4px 16px rgba(26,138,92,.35);transform:translateY(-1px)}
.cm-btn-add:active{transform:none;box-shadow:0 1px 4px rgba(26,138,92,.2)}

/* ── Mobile Sticky Bar ── */
.cm-mob-bar{display:none;position:sticky;top:0;z-index:50;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border-bottom:1px solid #e8ecf1;padding:10px 14px;gap:8px;align-items:center;margin:0 -16px 14px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
.cm-mob-search{flex:1;display:flex;align-items:center;gap:8px;background:#f1f5f9;border-radius:10px;padding:0 12px}
.cm-mob-search i{color:#94a3b8;font-size:13px;flex-shrink:0}
.cm-mob-search input{flex:1;border:none;background:transparent;font-size:13px;color:#334155;padding:9px 0;outline:none;font-family:inherit}
.cm-mob-search input::placeholder{color:#b0bac6}

/* ── Pagination ── */
.cm-pagination{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:16px;padding:10px 14px;background:#fff;border:1px solid #e8ecf1;border-radius:12px}
.cm-pg-info{font-size:12px;color:#94a3b8;font-weight:500}
.cm-pg-btns{display:flex;align-items:center;gap:4px}
.cm-pg-btn{width:32px;height:32px;border:1.5px solid #e2e8f0;background:#fff;border-radius:8px;color:#64748b;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.15s}
.cm-pg-btn:hover:not(:disabled){border-color:var(--accent,#1a8a5c);color:var(--accent,#1a8a5c);background:#f0fdf4}
.cm-pg-btn:disabled{opacity:.35;cursor:not-allowed}

/* ── Legacy compat (removed in favor of cm-*) ── */
.cm-stat{display:flex;align-items:center;gap:14px;padding:16px!important}
.cm-stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.cm-view-toggle{display:flex;gap:2px;background:#f1f1f1;border-radius:8px;padding:3px}
.cm-view-toggle .ci-btn{background:transparent;border:none;color:#888;border-radius:6px;padding:6px 10px}
.cm-view-toggle .ci-btn.active{background:#fff;color:var(--accent);box-shadow:0 1px 3px rgba(0,0,0,.1)}

/* Grid Cards */
.cm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.cm-card{cursor:pointer;transition:transform .15s,box-shadow .15s;border-radius:14px;overflow:hidden;border:1px solid #e8ecf1!important;box-shadow:0 2px 8px rgba(0,0,0,.04)!important}
.cm-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.1)!important;border-color:#cbd5e1!important}
.cm-card-top{display:flex;gap:12px;align-items:flex-start}
.cm-card-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.cm-card-struct{width:44px;height:44px;border-radius:10px;border:1px solid #e8ecf0;background:#fafbfc;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.cm-card-struct img{width:40px;height:40px;object-fit:contain}
.cm-card-struct .cm-card-formula-inner{font-size:10px;font-weight:600;font-family:'Inter',sans-serif;color:#475569;line-height:1.15;text-align:center;padding:2px}
.cm-card-name{font-size:14px;font-weight:600;color:#333;line-height:1.3;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.cm-card-cas{font-size:12px;color:#888;font-family:monospace;margin-top:2px}
.cm-card-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:10px}
.cm-tag{font-size:10px;padding:2px 8px;border-radius:10px;font-weight:500;white-space:nowrap}
.cm-tag-solid{background:#ede9fe;color:#7c3aed}
.cm-tag-liquid{background:#dbeafe;color:#2563eb}
.cm-tag-gas{background:#fef3c7;color:#d97706}
.cm-tag-mfr{background:#f3f4f6;color:#6b7280}
.cm-tag-cat{background:#ecfdf5;color:#059669}
.cm-tag-ghs{background:#fef2f2;color:#dc2626}
.cm-tag-sds{background:#eff6ff;color:#3b82f6}
.cm-card-footer{display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:10px;border-top:1px solid #f0f0f0;font-size:12px;color:#aaa}
.cm-card-footer .cm-badges{display:flex;gap:6px}

/* Card inline 3D preview */
.cm-card-3d{position:relative;width:100%;height:120px;border-radius:10px;overflow:hidden;margin-top:10px;background:linear-gradient(135deg,#0f0f1a 0%,#1a1a3a 60%,#1e1b3a 100%);cursor:pointer;transition:all .25s}
.cm-card-3d:hover{box-shadow:0 4px 20px rgba(108,92,231,.2);transform:scale(1.01)}
.cm-card-3d .card3d-badge{position:absolute;top:6px;left:6px;display:flex;gap:4px;z-index:3;pointer-events:none}
.cm-card-3d .card3d-badge span{padding:2px 7px;border-radius:5px;font-size:8px;font-weight:700;letter-spacing:.3px;backdrop-filter:blur(6px)}
.cm-card-3d .c3d-tag-3d{background:rgba(108,92,231,.7);color:#e0d4ff}
.cm-card-3d .c3d-tag-ar{background:rgba(5,150,105,.7);color:#a7f3d0}
.cm-card-3d .c3d-tag-embed{background:rgba(59,130,246,.6);color:#bfdbfe}
.cm-card-3d .card3d-expand{position:absolute;bottom:6px;right:6px;width:28px;height:28px;border-radius:8px;background:rgba(255,255,255,.08);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.1);color:#ccc;display:flex;align-items:center;justify-content:center;font-size:11px;z-index:5;cursor:pointer;transition:all .15s;opacity:0}
.cm-card-3d:hover .card3d-expand{opacity:1}
.cm-card-3d .card3d-expand:hover{background:rgba(108,92,231,.4);color:#fff;border-color:rgba(108,92,231,.5)}
.cm-card-3d .card3d-loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:8px;color:#666;font-size:11px}
.cm-card-3d .card3d-loading i{animation:spin 1s linear infinite}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
/* Card packaging switcher */
.cm-card-3d .card3d-pkg-sw{position:absolute;bottom:5px;left:5px;display:flex;gap:3px;z-index:4;max-width:calc(100% - 46px);overflow-x:auto;scrollbar-width:none}
.cm-card-3d .card3d-pkg-sw::-webkit-scrollbar{display:none}
.cm-card-3d .card3d-pkg-sw .sw-pill{padding:2px 8px;border-radius:10px;font-size:8px;font-weight:600;background:rgba(255,255,255,.12);backdrop-filter:blur(6px);color:rgba(255,255,255,.65);border:1px solid rgba(255,255,255,.1);cursor:pointer;white-space:nowrap;transition:all .15s;line-height:1.3}
.cm-card-3d .card3d-pkg-sw .sw-pill:hover{background:rgba(255,255,255,.22);color:#fff}
.cm-card-3d .card3d-pkg-sw .sw-pill.active{background:rgba(108,92,231,.75);color:#fff;border-color:rgba(108,92,231,.5)}
.cm-card-3d .card3d-viewer-wrap{position:absolute;inset:0;transition:opacity .25s}
.cm-card-3d .card3d-viewer-wrap model-viewer{width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#6C5CE7;pointer-events:auto}
.cm-card-3d .card3d-viewer-wrap iframe{width:100%;height:100%;border:none;pointer-events:none}
.cm-badge-icon{display:flex;align-items:center;gap:3px}

/* Table */
.cm-table{width:100%;border-collapse:separate;border-spacing:0}
.cm-table th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:12px;font-weight:600;color:#64748b;border-bottom:2px solid #e2e8f0;position:sticky;top:0;z-index:1}
.cm-table td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#334155}
.cm-table tr{cursor:pointer;transition:background .1s}
.cm-table tr:hover td{background:#f8fafc}

/* ═══ Sortable Table Pro ═══ */
.cm-table-toolbar{display:flex;align-items:center;gap:10px;padding:11px 16px;background:linear-gradient(to right,#f8fafc,#f1f5f9 60%,#f8fafc);border-bottom:1.5px solid #e2e8f0}
.cm-ttb-l{flex:1;display:flex;align-items:center;gap:7px;min-width:0;flex-wrap:wrap}
.cm-ttb-r{display:flex;align-items:center;gap:7px;flex-shrink:0}
.cm-ttb-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;background:#fff;border:1.5px solid #e2e8f0;border-radius:9px;font-size:11px;font-weight:700;color:#475569;white-space:nowrap;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.cm-ttb-badge i{color:var(--accent,#1a8a5c);font-size:10px}
.cm-ttb-sep{width:1px;height:14px;background:#e2e8f0;flex-shrink:0}
.cm-ttb-count{font-size:12px;font-weight:600;color:#64748b;white-space:nowrap}
.cm-ttb-count strong{color:#0f172a}
.cm-ttb-sort{display:inline-flex;align-items:center;gap:5px;padding:3px 10px 3px 8px;background:#ecfdf5;border:1px solid #86efac;border-radius:20px;font-size:11px;font-weight:600;color:var(--accent,#1a8a5c);cursor:pointer;transition:all .15s;white-space:nowrap;flex-shrink:0}
.cm-ttb-sort:hover{background:#dcfce7;border-color:#4ade80}
.cm-ttb-sort .ts-x{opacity:.45;margin-left:1px;font-size:9px;transition:opacity .12s,color .12s}
.cm-ttb-sort:hover .ts-x{opacity:1;color:#dc2626}

/* Column dropdown */
.cm-col-dropdown{position:relative;display:inline-block}
.cm-col-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border:1.5px solid #e2e8f0;border-radius:9px;background:#fff;cursor:pointer;font-size:12px;color:#475569;font-weight:600;transition:all .15s;white-space:nowrap;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.cm-col-btn:hover{border-color:var(--accent);color:var(--accent);background:#f0fdf4;box-shadow:0 2px 8px rgba(26,138,92,.1)}
.cm-col-btn .col-count-badge{background:var(--accent-l,#dcfce7);color:var(--accent,#1a8a5c);padding:1px 7px;border-radius:6px;font-size:10px;font-weight:700;transition:background .15s}
.cm-col-btn:hover .col-count-badge{background:var(--accent,#1a8a5c);color:#fff}
.cm-col-panel{position:absolute;top:calc(100% + 8px);right:0;min-width:250px;max-height:380px;overflow-y:auto;background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;box-shadow:0 16px 48px rgba(0,0,0,.13);z-index:100;display:none;animation:cmDropIn .15s ease}
.cm-col-panel.show{display:block}
.cm-col-panel-hdr{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid #f1f5f9;background:#fafbfc;border-radius:13px 13px 0 0}
.cm-col-panel-hdr span{font-size:12px;font-weight:700;color:#334155;display:flex;align-items:center;gap:5px}
.cm-col-panel-hdr span i{color:var(--accent,#1a8a5c);font-size:11px}
.cm-col-panel-hdr button{font-size:11px;color:var(--accent);background:none;border:none;cursor:pointer;font-weight:600;padding:3px 8px;border-radius:6px;transition:.12s}
.cm-col-panel-hdr button:hover{background:var(--accent-l,#dcfce7);text-decoration:none}
.cm-col-item{display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;transition:background .1s;user-select:none;border-bottom:1px solid #f8fafc}
.cm-col-item:last-child{border-bottom:none}
.cm-col-item:hover{background:#f8fafc}
.cm-col-item input[type=checkbox]{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;flex-shrink:0}
.cm-col-item label{font-size:12px;color:#475569;cursor:pointer;flex:1;font-weight:500}
.cm-col-item .col-drag-handle{cursor:grab;color:#cbd5e1;font-size:10px;padding:3px;transition:color .1s;flex-shrink:0}
.cm-col-item .col-drag-handle:hover{color:#64748b}
.cm-col-item.dragging{opacity:.45;background:#eff6ff}
.cm-col-item.drag-over{border-top:2px solid var(--accent)}
@keyframes cmDropIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* Sortable Headers */
.cm-th-sort{cursor:pointer;user-select:none;transition:all .15s;position:relative;white-space:nowrap}
.cm-th-sort:hover{background:#eef2f7;color:#334155}
.cm-th-sort .sort-label{display:inline-flex;align-items:center;gap:5px}
.cm-th-sort .sort-icon{display:inline-flex;flex-direction:column;gap:0;line-height:1;margin-left:4px;font-size:8px;opacity:.35;transition:opacity .15s}
.cm-th-sort:hover .sort-icon{opacity:.6}
.cm-th-sort.sort-asc .sort-icon .si-up,
.cm-th-sort.sort-desc .sort-icon .si-down{color:var(--accent);opacity:1;font-weight:bold;font-size:10px}
.cm-th-sort.sort-asc .sort-icon,
.cm-th-sort.sort-desc .sort-icon{opacity:1}

/* Drag handle for table header */
.cm-th-sort .th-grip{cursor:grab;color:#cbd5e1;margin-right:4px;font-size:10px;opacity:0;transition:opacity .15s}
.cm-th-sort:hover .th-grip{opacity:.6}
.cm-th-sort.th-dragging{opacity:.4;background:#dbeafe!important}
.cm-th-sort.th-drag-over{box-shadow:inset -3px 0 0 var(--accent)}

/* Per-page selector */
.cm-perpage{display:flex;align-items:center;gap:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);flex-shrink:0}
.cm-perpage-lbl{font-size:11px;font-weight:500;color:#94a3b8;padding:0 9px;height:32px;display:flex;align-items:center;background:#f8fafc;border-right:1px solid #e2e8f0;white-space:nowrap;flex-shrink:0}
.cm-perpage select{height:32px;padding:0 26px 0 10px;border:none;font-size:12px;font-weight:600;color:#334155;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2394a3b8'/%3E%3C/svg%3E") no-repeat right 8px center;-webkit-appearance:none;appearance:none;cursor:pointer;outline:none;transition:color .15s}
.cm-perpage select:focus{color:var(--accent,#1a8a5c)}

/* ── Detail Tabs — Pro ── */
.cm-tabs-wrap{padding:14px 20px 0;background:#fff;border-bottom:1.5px solid #eef0f3;flex-shrink:0}
.cm-tabs{display:inline-flex;align-items:center;background:#f1f5f9;border-radius:13px;padding:3px;gap:2px}
.cm-tab{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border:none;background:transparent;border-radius:10px;font-size:12px;font-weight:600;color:#64748b;cursor:pointer;transition:background .18s,color .18s,box-shadow .18s;white-space:nowrap;font-family:inherit;margin:0;outline:none;flex-shrink:0}
.cm-tab:hover:not(.active){color:#334155;background:rgba(255,255,255,.65)}
.cm-tab.active{background:#fff;color:var(--accent,#1a8a5c);box-shadow:0 2px 10px rgba(0,0,0,.1),0 0 0 1px rgba(0,0,0,.04);font-weight:700}
.cm-tab i{font-size:11px;flex-shrink:0}
.cm-tab-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:#dde1e7;color:#64748b;border-radius:10px;font-size:9px;font-weight:700;transition:background .18s,color .18s;line-height:1;flex-shrink:0}
.cm-tab.active .cm-tab-badge{background:#d1fae5;color:var(--accent,#1a8a5c)}
.cm-tab-body{padding:20px;animation:cmFadeIn .22s ease}
@keyframes cmFadeIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}

/* Detail Info Grid */
.cm-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.cm-info-item{display:flex;flex-direction:column;gap:4px}
.cm-info-label{font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px}
.cm-info-value{font-size:14px;color:#334155;font-weight:500}

/* Molecule Structure Preview */
.cm-structure-preview{text-align:center;padding:16px 16px 8px;margin-bottom:12px;border-bottom:1px solid #f0f0f0}
.cm-structure-img-wrap{display:inline-block;background:#fafbfc;border:1px solid #e8ecf0;border-radius:12px;padding:12px;cursor:zoom-in;transition:all .2s}
.cm-structure-img-wrap:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);border-color:#cbd5e1;transform:scale(1.02)}
.cm-structure-img-wrap img{max-width:220px;max-height:220px;width:auto;height:auto;display:block}
.cm-structure-caption{font-size:11px;color:#94a3b8;margin-top:8px;font-weight:500;display:flex;align-items:center;justify-content:center;gap:4px}

/* Formula Visual Renderer */
.cm-formula-visual{text-align:center;padding:18px 16px 10px;margin-bottom:12px;border-bottom:1px solid #f0f0f0}
.cm-formula-display{display:inline-block;background:linear-gradient(135deg,#f8fafc,#f1f5f9);border:1px solid #e2e8f0;border-radius:14px;padding:20px 28px;position:relative}
.cm-formula-text{font-size:32px;font-family:'Inter',sans-serif;font-weight:300;letter-spacing:1px;color:#1e293b}
.cm-formula-text sub{font-size:20px;vertical-align:sub;font-weight:500;color:#475569}
.cm-formula-text .el-C{color:#374151}.cm-formula-text .el-H{color:#6b7280}.cm-formula-text .el-O{color:#dc2626}
.cm-formula-text .el-N{color:#2563eb}.cm-formula-text .el-S{color:#ca8a04}.cm-formula-text .el-Cl{color:#16a34a}
.cm-formula-text .el-F{color:#0891b2}.cm-formula-text .el-Br{color:#9f1239}.cm-formula-text .el-I{color:#7c3aed}
.cm-formula-text .el-P{color:#ea580c}.cm-formula-text .el-Na{color:#7c3aed}.cm-formula-text .el-K{color:#7c3aed}
.cm-formula-text .el-Fe{color:#b45309}.cm-formula-text .el-Cu{color:#b45309}.cm-formula-text .el-Zn{color:#6b7280}
.cm-formula-text .el-Si{color:#6366f1}.cm-formula-text .el-B{color:#e11d48}.cm-formula-text .el-Li{color:#dc2626}
.cm-formula-text .el-Mg{color:#059669}.cm-formula-text .el-Ca{color:#059669}.cm-formula-text .el-Al{color:#9ca3af}
.cm-formula-text .el-default{color:#475569}
.cm-atom-bar{display:flex;height:6px;border-radius:3px;overflow:hidden;margin-top:14px;gap:1px}
.cm-atom-seg{height:100%;min-width:4px;transition:flex .3s}
.cm-atom-legend{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:8px}
.cm-atom-legend span{font-size:10px;font-weight:600;display:flex;align-items:center;gap:3px;color:#64748b}
.cm-atom-legend .dot{width:7px;height:7px;border-radius:50%;display:inline-block}
.cm-formula-mw{font-size:12px;color:#94a3b8;margin-top:10px;font-weight:500}
.cm-formula-pubchem{margin-top:10px}
.cm-formula-pubchem img{max-width:200px;max-height:200px;border-radius:8px;border:1px solid #e8ecf0;background:#fff;padding:4px}

/* Card formula mini */
.cm-card-formula{width:44px;height:44px;border-radius:10px;border:1px solid #e8ecf0;background:linear-gradient(135deg,#f8fafc,#f1f5f9);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;font-family:'Inter',sans-serif;color:#475569;line-height:1.1;text-align:center;padding:2px}

/* GHS Pictograms */
.cm-ghs-grid{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0}
.cm-ghs-sym{width:64px;height:64px;border:2px solid #fecaca;border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;background:#fff;position:relative}
.cm-ghs-sym:hover{transform:scale(1.05);box-shadow:0 2px 8px rgba(220,38,38,.15)}
.cm-ghs-sym.active{border-color:#dc2626;background:#fef2f2;box-shadow:0 0 0 3px rgba(220,38,38,.15)}
.cm-ghs-sym img{width:40px;height:40px}
.cm-ghs-sym .ghs-label{font-size:9px;font-weight:600;color:#991b1b;margin-top:2px}
.cm-ghs-sym .ghs-check{position:absolute;top:-4px;right:-4px;width:16px;height:16px;border-radius:50%;background:#dc2626;color:#fff;font-size:8px;display:none;align-items:center;justify-content:center}
.cm-ghs-sym.active .ghs-check{display:flex}

/* SDS File Cards */
.cm-file-card{border:1.5px solid #e5e7eb;border-radius:12px;transition:all .15s;margin-bottom:10px;overflow:hidden}
.cm-file-card:hover{border-color:#3b82f6;box-shadow:0 4px 16px rgba(59,130,246,.08)}
.cm-file-card-main{display:flex;align-items:center;gap:12px;padding:12px 16px}
.cm-file-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.cm-file-info{flex:1;min-width:0}
.cm-file-name{font-size:13px;font-weight:600;color:#333;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cm-file-meta{font-size:11px;color:#94a3b8;display:flex;gap:8px;margin-top:2px;flex-wrap:wrap}
.cm-file-actions{display:flex;gap:6px;flex-shrink:0}
/* Expiry bar section */
.cm-file-expiry{padding:8px 16px 12px;border-top:1px solid #f1f5f9;background:#fafbfc}
.cm-file-expiry-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:5px}
.cm-file-expiry-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;display:flex;align-items:center;gap:5px}
.cm-file-expiry-dates{font-size:10px;color:#64748b;display:flex;gap:12px}
.cm-file-expiry-dates span{display:flex;align-items:center;gap:3px}
.cm-expiry-bar-wrap{height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;margin-bottom:4px}
.cm-expiry-bar{height:100%;border-radius:2px;transition:width .5s ease}
.cm-expiry-footer{display:flex;justify-content:space-between;align-items:center}
.cm-expiry-days{font-size:10px;font-weight:600}
/* Expiry status badges */
.cm-expiry-badge{font-size:9px;font-weight:700;padding:2px 8px;border-radius:10px;text-transform:uppercase;letter-spacing:.4px;display:inline-flex;align-items:center;gap:3px}
.cm-expiry-ok{background:#dcfce7;color:#15803d}
.cm-expiry-soon{background:#fef3c7;color:#92400e;animation:soonPulse 2s ease-in-out infinite}
.cm-expiry-expired{background:#fee2e2;color:#dc2626}
.cm-upload-ts{font-size:9px;color:#94a3b8;display:flex;align-items:center;gap:3px}
@keyframes soonPulse{0%,100%{opacity:1}50%{opacity:.65}}

/* Form fields for modals */
.cm-field{margin-bottom:16px}
.cm-field label{display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:5px}
.cm-field label .req{color:#ef4444}
.cm-field input,.cm-field select,.cm-field textarea{width:100%;padding:9px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#333;background:#fafbfc;transition:all .2s;outline:none;box-sizing:border-box}
.cm-field input:focus,.cm-field select:focus,.cm-field textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.1);background:#fff}
.cm-field textarea{min-height:70px;resize:vertical}
.cm-field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.cm-field .hint{font-size:11px;color:#aaa;margin-top:3px}

/* Signal Word */
.cm-signal-danger{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:4px 12px;border-radius:8px;font-weight:700;font-size:12px}
.cm-signal-warning{background:#fffbeb;color:#d97706;border:1px solid #fde68a;padding:4px 12px;border-radius:8px;font-weight:700;font-size:12px}
.cm-signal-none{background:#f3f4f6;color:#6b7280;padding:4px 12px;border-radius:8px;font-weight:500;font-size:12px}

/* Upload Drop Zone */
.cm-dropzone{border:2px dashed #d1d5db;border-radius:12px;padding:32px;text-align:center;transition:all .2s;cursor:pointer}
.cm-dropzone:hover,.cm-dropzone.dragover{border-color:#3b82f6;background:#eff6ff}
.cm-dropzone i{font-size:32px;color:#9ca3af;margin-bottom:8px}
.cm-dropzone p{font-size:13px;color:#6b7280}
.cm-dropzone .hint{font-size:11px;color:#aaa;margin-top:4px}

/* Packaging Cards */
.cm-pkg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-top:12px}
.cm-pkg-card{border:1.5px solid #e5e7eb;border-radius:12px;overflow:hidden;transition:all .15s;background:#fff;position:relative}
.cm-pkg-card:hover{border-color:#d97706;box-shadow:0 4px 16px rgba(217,119,6,.08)}
.cm-pkg-card.is-default{border-color:#d97706;background:#fffbeb}
.cm-pkg-card-img{width:100%;height:180px;background:linear-gradient(135deg,#0f0f1a 0%,#1a1a3a 100%);display:flex;align-items:center;justify-content:center;overflow:hidden;border-bottom:1px solid rgba(108,92,231,.08);position:relative}
.cm-pkg-card-img img{max-width:100%;max-height:180px;object-fit:contain}
.cm-pkg-card-img iframe{width:100%;height:100%;border:none;display:block}
.cm-pkg-card-img model-viewer{width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#6C5CE7}
.cm-pkg-card-img .pkg-no-img{color:#d1d5db;font-size:36px}
.cm-pkg-card-img .pkg-3d-badge{position:absolute;top:6px;left:6px;background:rgba(108,92,231,.85);backdrop-filter:blur(6px);color:#fff;font-size:9px;padding:3px 8px;border-radius:6px;font-weight:700;z-index:2;display:flex;align-items:center;gap:3px}
.cm-pkg-card-img .pkg-ar-badge{position:absolute;top:6px;right:6px;background:linear-gradient(135deg,rgba(5,150,105,.9),rgba(16,185,129,.85));backdrop-filter:blur(6px);color:#fff;font-size:9px;padding:3px 8px;border-radius:6px;font-weight:700;z-index:2;display:flex;align-items:center;gap:3px;animation:arPulse 2s ease-in-out infinite;box-shadow:0 2px 8px rgba(5,150,105,.3)}
@keyframes arPulse{0%,100%{opacity:1}50%{opacity:.7}}
.cm-pkg-card-img .pkg-3d-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;height:100%;cursor:pointer;transition:all .2s;gap:6px;color:#8b7bf7}
.cm-pkg-card-img .pkg-3d-placeholder:hover{background:rgba(108,92,231,.1)}
.cm-pkg-card-img .pkg-3d-placeholder i.fa-cube{font-size:32px;opacity:.6;transition:all .2s}
.cm-pkg-card-img .pkg-3d-placeholder:hover i.fa-cube{opacity:1;transform:scale(1.1)}
.cm-pkg-card-img .pkg-3d-placeholder span{font-size:11px;font-weight:600;opacity:.7}
.cm-pkg-card-img .pkg-3d-placeholder small{font-size:9px;opacity:.5;color:#aaa}
.cm-pkg-card-img .pkg-fallback{background:#f8fafc}
/* Pkg inline 3D viewer overlay controls */
.cm-pkg-card-img .pkg-3d-overlay{position:absolute;bottom:0;left:0;right:0;display:flex;align-items:center;justify-content:space-between;padding:6px 8px;background:linear-gradient(to top,rgba(0,0,0,.6) 0%,transparent 100%);z-index:3;opacity:0;transition:opacity .2s}
.cm-pkg-card-img:hover .pkg-3d-overlay{opacity:1}
.cm-pkg-card-img .pkg-3d-overlay .pkg-ov-label{font-size:9px;color:rgba(255,255,255,.7);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:60%}
.cm-pkg-card-img .pkg-3d-overlay .pkg-ov-btns{display:flex;gap:4px}
.cm-pkg-card-img .pkg-3d-overlay .pkg-ov-btn{width:26px;height:26px;border-radius:7px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);backdrop-filter:blur(6px);color:#ccc;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:10px;transition:all .15s}
.cm-pkg-card-img .pkg-3d-overlay .pkg-ov-btn:hover{background:rgba(108,92,231,.4);color:#fff;border-color:rgba(108,92,231,.5)}
.cm-pkg-card-img .pkg-3d-overlay .pkg-ov-btn.ov-ar{background:rgba(5,150,105,.25);border-color:rgba(5,150,105,.4);color:#6ee7b7}
.cm-pkg-card-img .pkg-3d-overlay .pkg-ov-btn.ov-ar:hover{background:rgba(5,150,105,.5);color:#a7f3d0}
.cm-pkg-card-img .pkg-3d-loading{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;z-index:2;background:linear-gradient(135deg,#0f0f1a 0%,#1a1a3a 100%);transition:opacity .4s}
.cm-pkg-card-img .pkg-3d-loading.loaded{opacity:0;pointer-events:none}
.cm-pkg-card-img .pkg-3d-loading i{font-size:20px;color:#6C5CE7;animation:spin 1s linear infinite}
.cm-pkg-card-img .pkg-3d-loading span{font-size:10px;color:#888}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
.cm-pkg-card-body{padding:12px 14px}
.cm-pkg-card-label{font-size:14px;font-weight:600;color:#333;margin-bottom:4px;display:flex;align-items:center;gap:6px}
.cm-pkg-card-label .pkg-default-badge{background:#fef3c7;color:#d97706;font-size:9px;padding:1px 6px;border-radius:8px;font-weight:700}
.cm-pkg-card-detail{font-size:12px;color:#888;line-height:1.6}
.cm-pkg-card-detail .pkg-row{display:flex;align-items:center;gap:4px}
.cm-pkg-card-detail .pkg-row i{width:14px;font-size:10px;color:#aaa;text-align:center}
.cm-pkg-card-actions{display:flex;gap:6px;margin-top:10px;padding-top:8px;border-top:1px solid #f0f0f0}
.cm-pkg-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.cm-pkg-request-btn{background:#fef3c7;color:#d97706;border:1px solid #fde68a;font-size:11px;padding:4px 10px;border-radius:6px;cursor:pointer;transition:all .15s}
.cm-pkg-request-btn:hover{background:#fde68a}

/* ═══ 3D Model Selector ═══ */
.pkg-model-sel{border:1.5px solid #e5e7eb;border-radius:10px;overflow:hidden;transition:border-color .2s}
.pkg-model-sel.has-selection{border-color:#16a34a}
.pkg-model-sel-hdr{display:flex;align-items:center;gap:8px;padding:10px 12px;background:#faf5ff;border-bottom:1px solid #ede9fe;cursor:pointer;transition:background .15s}
.pkg-model-sel.has-selection .pkg-model-sel-hdr{background:#f0fdf4;border-bottom-color:#bbf7d0}
.pkg-model-sel-hdr i.hdr-icon{color:#7c3aed;font-size:14px}
.pkg-model-sel.has-selection .pkg-model-sel-hdr i.hdr-icon{color:#16a34a}
.pkg-model-sel-hdr .sel-title{font-size:12px;font-weight:600;color:#5b21b6;flex:1}
.pkg-model-sel.has-selection .pkg-model-sel-hdr .sel-title{color:#15803d}
.pkg-model-sel-hdr .sel-clear{font-size:10px;color:#ef4444;cursor:pointer;padding:2px 8px;border-radius:4px;border:1px solid #fecaca;background:#fff;transition:all .12s}
.pkg-model-sel-hdr .sel-clear:hover{background:#fef2f2}
.pkg-model-sel-search{padding:8px 12px;border-bottom:1px solid #f3f4f6;display:flex;gap:6px;align-items:center}
.pkg-model-sel-search input{flex:1;border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:12px;color:#333}
.pkg-model-sel-search input:focus{outline:none;border-color:#7c3aed}
.pkg-model-sel-search select{border:1px solid #e5e7eb;border-radius:6px;padding:6px 8px;font-size:11px;color:#555}
.pkg-model-sel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;padding:10px 12px;max-height:320px;overflow-y:auto;background:#fafafa}
.pkg-model-sel-grid::-webkit-scrollbar{width:5px}
.pkg-model-sel-grid::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:10px}
/* Model item card */
.pkg-ms-item{border:2.5px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:all .18s;background:#fff;overflow:hidden;position:relative}
.pkg-ms-item:hover{border-color:#a78bfa;box-shadow:0 3px 12px rgba(124,58,237,.12);transform:translateY(-1px)}
.pkg-ms-item.selected{border-color:#16a34a!important;background:#f0fdf4;box-shadow:0 0 0 3px rgba(22,163,74,.18),0 3px 12px rgba(22,163,74,.1)}
/* Checkmark badge — green */
.pkg-ms-item .ms-check{position:absolute;top:5px;right:5px;width:22px;height:22px;border-radius:50%;background:#16a34a;color:#fff;display:none;align-items:center;justify-content:center;font-size:10px;z-index:5;box-shadow:0 2px 6px rgba(22,163,74,.3)}
.pkg-ms-item.selected .ms-check{display:flex}
/* Thumbnail area */
.pkg-ms-thumb{width:100%;height:90px;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.pkg-ms-thumb model-viewer,.pkg-ms-thumb iframe{width:100%;height:100%;border:none;pointer-events:none}
.pkg-ms-thumb model-viewer{--poster-color:transparent;--progress-bar-height:2px;--progress-bar-color:#7c3aed}
.pkg-ms-thumb .ms-icon{font-size:24px;color:#6366f1;opacity:.4}
/* Click overlay to guarantee clicks register */
.pkg-ms-thumb .ms-click-overlay{position:absolute;inset:0;z-index:3;cursor:pointer}
/* Source badge */
.pkg-ms-thumb .ms-src{position:absolute;top:3px;left:3px;font-size:7px;padding:1px 5px;border-radius:3px;background:rgba(124,58,237,.7);color:#fff;font-weight:700;text-transform:uppercase;z-index:4}
/* Preview button */
.pkg-ms-thumb .ms-preview-btn{position:absolute;bottom:4px;right:4px;width:24px;height:24px;border-radius:6px;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.15);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:10px;z-index:4;opacity:0;transition:opacity .15s,background .15s}
.pkg-ms-item:hover .ms-preview-btn{opacity:1}
.pkg-ms-thumb .ms-preview-btn:hover{background:rgba(124,58,237,.7)}
/* Info section */
.pkg-ms-info{padding:7px 8px}
.pkg-ms-info .ms-name{font-size:10px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pkg-ms-item.selected .ms-name{color:#15803d}
.pkg-ms-info .ms-meta{font-size:9px;color:#999;margin-top:2px;display:flex;gap:4px;align-items:center}
/* Empty / Loading states */
.pkg-model-sel-empty{text-align:center;padding:24px;color:#999;font-size:12px}
.pkg-model-sel-empty i{font-size:24px;opacity:.3;display:block;margin-bottom:6px}
.pkg-model-sel-loading{text-align:center;padding:24px;color:#7c3aed}
.pkg-model-sel-loading i{animation:spin 1s linear infinite}
/* Current model preview bar */
.pkg-model-current{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f0fdf4;border-top:1px solid #bbf7d0}
.pkg-model-current .mc-badge{width:28px;height:28px;border-radius:50%;background:#16a34a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.pkg-model-current .mc-preview{width:60px;height:60px;border-radius:8px;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;cursor:pointer;border:2px solid #16a34a}
.pkg-model-current .mc-preview model-viewer{width:100%;height:100%;--poster-color:transparent}
.pkg-model-current .mc-preview iframe{width:100%;height:100%;border:none}
.pkg-model-current .mc-preview i{color:#16a34a;font-size:20px;opacity:.5}
.pkg-model-current .mc-info{flex:1;min-width:0}
.pkg-model-current .mc-info .mc-name{font-size:12px;font-weight:600;color:#15803d}
.pkg-model-current .mc-info .mc-type{font-size:10px;color:#666;margin-top:1px}
/* 3D Preview Modal (lightweight) */
.pkg-preview-modal-bg{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:flex;align-items:center;justify-content:center;animation:cmFadeIn .2s ease}
.pkg-preview-modal{width:min(90vw,600px);height:min(80vh,500px);background:#1a1a2e;border-radius:16px;overflow:hidden;position:relative;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.pkg-preview-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:rgba(255,255,255,.05);border-bottom:1px solid rgba(255,255,255,.08)}
.pkg-preview-modal-hdr .pm-title{color:#fff;font-size:13px;font-weight:600}
.pkg-preview-modal-hdr .pm-meta{color:#aaa;font-size:11px}
.pkg-preview-modal-hdr .pm-close{width:30px;height:30px;border-radius:8px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:#ccc;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;transition:all .15s}
.pkg-preview-modal-hdr .pm-close:hover{background:rgba(239,68,68,.3);color:#fff}
.pkg-preview-modal-body{flex:1;display:flex;align-items:center;justify-content:center;overflow:hidden}
.pkg-preview-modal-body model-viewer{width:100%;height:100%}
.pkg-preview-modal-body iframe{width:100%;height:100%;border:none}
.pkg-preview-modal-footer{display:flex;gap:8px;justify-content:center;padding:12px 16px;background:rgba(255,255,255,.03);border-top:1px solid rgba(255,255,255,.06)}
.pkg-preview-modal-footer button{padding:8px 20px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600;transition:all .15s}
.pkg-preview-modal-footer .pm-select{background:#16a34a;color:#fff}
.pkg-preview-modal-footer .pm-select:hover{background:#15803d}
.pkg-preview-modal-footer .pm-cancel{background:rgba(255,255,255,.08);color:#ccc;border:1px solid rgba(255,255,255,.12)}
.pkg-preview-modal-footer .pm-cancel:hover{background:rgba(255,255,255,.15)}

/* ═══ Responsive ═══ */
@media(max-width:900px){
    .cm-stats-row{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
    /* Show mobile bar, hide desktop toolbar */
    .cm-mob-bar{display:flex}
    .cm-toolbar{display:none}
    /* Stats — horizontal scroll strip (same pattern as stk-stats) */
    .cm-stats-row{display:flex;overflow-x:auto;gap:8px;padding-bottom:6px;margin-bottom:14px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
    .cm-stats-row::-webkit-scrollbar{display:none}
    .cm-stat-card{min-width:140px;flex-shrink:0;padding:11px 12px;gap:9px;border-radius:12px}
    .cm-stat-num{font-size:18px}
    .cm-stat-lbl{font-size:10px}
    .cm-stat-ic{width:38px;height:38px;font-size:16px;border-radius:10px}
    /* Grid */
    .cm-grid{grid-template-columns:1fr}
    /* Info grid inside detail modal */
    .cm-info-grid{grid-template-columns:1fr}
    .cm-field-row{grid-template-columns:1fr}
    .cm-pkg-form-grid{grid-template-columns:1fr}
    /* Detail modal tabs — mobile underline strip */
    .cm-tabs-wrap{
        padding:0;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        scrollbar-width:none;
    }
    .cm-tabs-wrap::-webkit-scrollbar{display:none}
    .cm-tabs{
        display:flex;
        background:transparent;
        border-radius:0;
        padding:0 14px;
        gap:0;
        min-width:max-content;
    }
    .cm-tab{
        padding:11px 13px;
        border-radius:0;
        font-size:11px;
        gap:5px;
        border-bottom:2px solid transparent;
        margin-bottom:-1.5px;
        color:#64748b;
        background:transparent !important;
        box-shadow:none !important;
        font-weight:600;
    }
    .cm-tab:hover:not(.active){color:#334155}
    .cm-tab.active{
        color:var(--accent,#1a8a5c);
        border-bottom-color:var(--accent,#1a8a5c);
        font-weight:700;
    }
    .cm-tab-badge{min-width:16px;height:16px;font-size:8px;padding:0 4px}
    .cm-tab.active .cm-tab-badge{background:#d1fae5;color:var(--accent,#1a8a5c)}
    .cm-tab-body{padding:14px}
    .cm-ghs-sym{width:52px;height:52px}
    .cm-ghs-sym img{width:32px;height:32px}
    /* Table toolbar */
    .cm-table-toolbar{flex-direction:column;gap:9px;padding:10px 12px}
    .cm-ttb-l{width:100%}
    .cm-ttb-r{width:100%;justify-content:space-between}
    .cm-col-panel{right:auto;left:0;min-width:220px}
    /* Pagination */
    .cm-pagination{flex-direction:column;gap:8px;text-align:center}
    .cm-pg-btns{justify-content:center;flex-wrap:wrap}
}
@media(max-width:480px){
    .cm-stat-card{min-width:120px;padding:10px 11px}
    .cm-stat-num{font-size:16px}
    .cm-card-tags{display:none}
}

/* ═══════ 3D & AR Viewer PRO Modal ═══════ */
.ar-modal-bg{position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.95);display:none;align-items:stretch;justify-content:center;animation:arFadeIn .3s ease}
.ar-modal-bg.show{display:flex}
@keyframes arFadeIn{from{opacity:0}to{opacity:1}}
.ar-modal-container{display:flex;flex-direction:column;width:100%;height:100vh;max-height:100vh;overflow:hidden}

/* Header */
.ar-modal-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:rgba(15,15,26,.95);backdrop-filter:blur(20px);border-bottom:1px solid rgba(108,92,231,.15);flex-shrink:0;z-index:10}
.ar-header-left{display:flex;align-items:center;gap:12px}
.ar-header-right{display:flex;align-items:center;gap:6px}
.ar-hdr-btn{width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.05);color:#ccc;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;transition:all .15s}
.ar-hdr-btn:hover{background:rgba(108,92,231,.2);color:#fff;border-color:rgba(108,92,231,.3)}
.ar-hdr-btn.active{background:rgba(108,92,231,.25);color:#a78bfa;border-color:rgba(108,92,231,.4)}
.ar-header-info{display:flex;flex-direction:column;gap:1px}
.ar-header-label{font-size:14px;font-weight:700;color:#fff}
.ar-header-sub{font-size:10px;color:#888;font-family:monospace}
.ar-provider-badge{padding:3px 10px;border-radius:8px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.ar-provider-badge.prov-kiri{background:rgba(59,130,246,.2);color:#93c5fd}
.ar-provider-badge.prov-sketchfab{background:rgba(217,119,6,.2);color:#fcd34d}
.ar-provider-badge.prov-upload{background:rgba(5,150,105,.2);color:#6ee7b7}
.ar-provider-badge.prov-generic{background:rgba(255,255,255,.08);color:#aaa}

/* Viewer Area */
.ar-viewer-area{flex:1 1 0%;position:relative;overflow:hidden;background:radial-gradient(ellipse at center,#1a1a3a 0%,#0f0f1a 70%);min-height:0}
#arModelContainer{position:absolute;inset:0;z-index:1}
#arModelContainer model-viewer{width:100%;height:100%;display:block;--poster-color:transparent;--progress-bar-color:#6C5CE7;--progress-bar-height:3px}
#arModelContainer iframe{width:100%;height:100%;border:none;display:block}
.ar-loading{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;z-index:5;background:radial-gradient(ellipse at center,#1a1a3a 0%,#0f0f1a 70%);transition:opacity .5s}
.ar-loading.hidden{opacity:0;pointer-events:none}
.ar-loading p{font-size:13px;color:#888;font-weight:500}
.ar-load-ring{display:inline-block;position:relative;width:48px;height:48px}
.ar-load-ring div{box-sizing:border-box;display:block;position:absolute;width:40px;height:40px;margin:4px;border:3px solid transparent;border-radius:50%;animation:arRing 1.2s cubic-bezier(.5,0,.5,1) infinite;border-top-color:#6C5CE7}
.ar-load-ring div:nth-child(1){animation-delay:-.45s;border-top-color:#a78bfa}
.ar-load-ring div:nth-child(2){animation-delay:-.3s;border-top-color:#6C5CE7}
.ar-load-ring div:nth-child(3){animation-delay:-.15s;border-top-color:#4c3fad}
@keyframes arRing{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}

/* Bottom Controls */
.ar-controls{flex-shrink:0;background:rgba(15,15,26,.95);backdrop-filter:blur(20px);border-top:1px solid rgba(108,92,231,.12);padding:12px 16px 16px;display:flex;flex-direction:column;gap:10px}

/* Chemical Info Card */
.ar-chem-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.ar-chem-head{min-width:0;flex:1}
.ar-chem-cas{font-size:15px;font-weight:800;color:#a78bfa;font-family:monospace;letter-spacing:.5px}
.ar-chem-name{font-size:11px;color:#aaa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.ar-chem-tags{display:flex;gap:4px;flex-shrink:0;flex-wrap:wrap}
.ar-chem-tags .ar-tag{padding:3px 8px;border-radius:6px;font-size:9px;font-weight:700}
.ar-chem-tags .ar-tag-danger{background:rgba(220,38,38,.15);color:#fca5a5}
.ar-chem-tags .ar-tag-warning{background:rgba(217,119,6,.15);color:#fcd34d}
.ar-chem-tags .ar-tag-ghs{background:rgba(220,38,38,.12);color:#f87171}

/* Action Buttons */
.ar-action-bar{display:flex;gap:6px}
.ar-action-btn{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:10px 4px;border-radius:12px;border:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,.03);color:#ccc;cursor:pointer;transition:all .2s;text-align:center}
.ar-action-btn:hover{background:rgba(108,92,231,.12);color:#fff;border-color:rgba(108,92,231,.2)}
.ar-action-btn:active{transform:scale(.96)}
.ar-action-btn.active{background:rgba(108,92,231,.2);color:#a78bfa;border-color:rgba(108,92,231,.3)}
.ar-action-btn i{font-size:16px}
.ar-action-btn span{font-size:10px;font-weight:600}
.ar-action-btn small{font-size:8px;color:#888;display:none}
.ar-btn-ar{background:linear-gradient(135deg,rgba(5,150,105,.2),rgba(16,185,129,.1));border-color:rgba(5,150,105,.3);color:#6ee7b7}
.ar-btn-ar:hover{background:linear-gradient(135deg,rgba(5,150,105,.3),rgba(16,185,129,.2));color:#a7f3d0}
.ar-btn-ar small{display:block;color:#6ee7b7}
.ar-btn-ar i{font-size:20px}

/* Stats Bar */
.ar-stats-bar{display:flex;gap:14px;justify-content:center;padding:6px 0 0}
.ar-stats-bar span{font-size:10px;color:#666;display:flex;align-items:center;gap:4px}
.ar-stats-bar span i{font-size:8px;color:#555}

/* Responsive */
@media(max-width:768px){
    .ar-modal-header{padding:8px 12px}
    .ar-controls{padding:10px 12px 14px}
    .ar-action-btn{padding:8px 2px}
    .ar-action-btn i{font-size:14px}
}
@media(min-width:1201px){
    .ar-modal-container{max-width:1200px;margin:0 auto;height:100vh}
}

/* ═══ Spatial AR Button ═══ */
.ar-btn-spatial{background:linear-gradient(135deg,rgba(108,92,231,.2),rgba(167,139,250,.1));border-color:rgba(108,92,231,.3)!important;color:#a78bfa}
.ar-btn-spatial:hover{background:linear-gradient(135deg,rgba(108,92,231,.3),rgba(167,139,250,.2));color:#c4b5fd}
.ar-btn-spatial small{display:block;color:#a78bfa}
.ar-btn-spatial i{font-size:18px}

/* ═══ AR Spatial Status Banner ═══ */
.ar-spatial-status{position:absolute;top:56px;left:50%;transform:translateX(-50%);z-index:20;display:none;align-items:center;gap:8px;padding:8px 18px;border-radius:12px;background:rgba(0,0,0,.75);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.08);font-size:12px;font-weight:600;white-space:nowrap;animation:arSpSlide .4s ease}
.ar-spatial-status.show{display:flex}
@keyframes arSpSlide{from{opacity:0;transform:translateX(-50%) translateY(-12px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
.ar-sp-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.ar-sp-dot.scan{background:#fbbf24;animation:arDotPulse 1s ease-in-out infinite}
.ar-sp-dot.placed{background:#22c55e}
.ar-sp-dot.anchored{background:#a78bfa;animation:arDotPulse 1.5s ease-in-out infinite}
@keyframes arDotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.6)}}

/* ═══ AR Placement Instructions ═══ */
.ar-sp-instruct{position:absolute;bottom:180px;left:50%;transform:translateX(-50%);z-index:20;display:none;flex-direction:column;align-items:center;gap:6px;padding:14px 22px;border-radius:14px;background:rgba(0,0,0,.7);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.06);animation:arInstructBounce 2s ease-in-out infinite;pointer-events:none}
.ar-sp-instruct.show{display:flex}
@keyframes arInstructBounce{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(-6px)}}
.ar-sp-instruct i{font-size:24px;color:#a78bfa}
.ar-sp-instruct p{font-size:11px;color:#ccc;text-align:center;max-width:180px;line-height:1.4}

/* ═══ Anchor Pill ═══ */
.ar-anchor-pill{display:none;align-items:center;gap:6px;justify-content:center;padding:6px 14px;border-radius:10px;background:rgba(108,92,231,.1);border:1px solid rgba(108,92,231,.15);font-size:11px;font-weight:600;color:#a78bfa;animation:arAnchorGlow 2s ease-in-out infinite}
.ar-anchor-pill.show{display:flex}
@keyframes arAnchorGlow{0%,100%{border-color:rgba(108,92,231,.15)}50%{border-color:rgba(108,92,231,.4)}}
</style>

<?php Layout::endContent(); ?>

<script>
const IS_ADMIN = <?= $isAdmin?'true':'false' ?>;
const IS_MANAGER = <?= $isManager?'true':'false' ?>;
const USER_ID = <?= $userId ?>;

// GHS pictogram data — using PubChem official SVGs (reliable, never 404)
const GHS_PICTOGRAMS = {
    'GHS01':{name:'Exploding Bomb',desc:'ระเบิดได้',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS01.svg'},
    'GHS02':{name:'Flame',desc:'ไวไฟ',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS02.svg'},
    'GHS03':{name:'Flame over Circle',desc:'ออกซิไดเซอร์',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS03.svg'},
    'GHS04':{name:'Gas Cylinder',desc:'ก๊าซอัด',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS04.svg'},
    'GHS05':{name:'Corrosion',desc:'กัดกร่อน',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS05.svg'},
    'GHS06':{name:'Skull & Crossbones',desc:'พิษเฉียบพลัน',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS06.svg'},
    'GHS07':{name:'Exclamation Mark',desc:'อันตราย',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS07.svg'},
    'GHS08':{name:'Health Hazard',desc:'อันตรายต่อสุขภาพ',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS08.svg'},
    'GHS09':{name:'Environment',desc:'อันตรายต่อสิ่งแวดล้อม',img:'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS09.svg'}
};

let chemicals = [], page = 1, perPage = parseInt(localStorage.getItem('chemPerPage'))||20, totalItems = 0, totalPages = 0;
let currentView = localStorage.getItem('chemView') || 'grid';
let currentDetail = null;
let sortField = localStorage.getItem('chemSortField') || 'name';
let sortDir = localStorage.getItem('chemSortDir') || 'ASC';
let activeStatFilter = null;

// Column definitions — order matters, user can reorder
const DEFAULT_COLUMNS = [
    {key:'idx',      label:'#',         sortable:false, align:'center', width:'50px', visible:true},
    {key:'name',     label:'ชื่อสาร',    sortable:true,  sortKey:'name', visible:true},
    {key:'cas',      label:'CAS No.',   sortable:true,  sortKey:'cas', visible:true},
    {key:'manufacturer',label:'ผู้ผลิต', sortable:true,  sortKey:'manufacturer', visible:true},
    {key:'catalogue',label:'Catalogue', sortable:false, visible:true},
    {key:'state',    label:'สถานะ',     sortable:true,  sortKey:'state', visible:true},
    {key:'type',     label:'ชนิด',      sortable:false, visible:true},
    {key:'category', label:'ประเภท',    sortable:false, visible:false},
    {key:'mw',       label:'MW (g/mol)',sortable:false, visible:false},
    {key:'formula',  label:'สูตร',      sortable:false, visible:false},
    {key:'sds',      label:'SDS',       sortable:false, align:'center', visible:true},
    {key:'ghs',      label:'GHS',       sortable:false, align:'center', visible:true},
    {key:'containers',label:'คลัง',     sortable:false, align:'center', visible:false},
    {key:'date',     label:'วันที่เพิ่ม', sortable:true,  sortKey:'date', visible:false},
];
let tableColumns = JSON.parse(localStorage.getItem('chemColumns') || 'null') || DEFAULT_COLUMNS.map(c=>({...c}));

// ═══════ Init ═══════
async function init() {
    loadStats();
    loadChemicals();
    setView(currentView, true);
    setupSearch();
}

// ═══════ Stats ═══════
async function loadStats() {
    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=stats');
        if (d.success) {
            document.getElementById('sTotalChem').textContent = num(d.data.total_chemicals);
            document.getElementById('sCas').textContent = num(d.data.unique_cas);
            document.getElementById('sGhs').textContent = num(d.data.ghs_records);
            document.getElementById('sSds').textContent = num(d.data.sds_files);
        }
    } catch(e) { console.error(e); }
}

function statClick(type) {
    const same = activeStatFilter === type || (type === 'all' && !activeStatFilter);
    document.querySelectorAll('.cm-stat-card').forEach(c => c.classList.remove('active'));
    if (same) {
        activeStatFilter = null;
    } else {
        activeStatFilter = type === 'all' ? null : type;
        const el = document.getElementById('csStat-' + type);
        if (el) el.classList.add('active');
    }
    page = 1;
    loadChemicals();
    document.getElementById('mainContent').scrollIntoView({behavior:'smooth', block:'start'});
}

// ═══════ Load & Render ═══════
async function loadChemicals() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="ci-loading" style="padding:40px"><div class="ci-spinner"></div></div>';
    try {
        const params = new URLSearchParams({action:'list', page, limit: perPage, sort: sortField, dir: sortDir});
        const s = document.getElementById('searchInput').value;
        const st = document.getElementById('fState').value;
        const tp = document.getElementById('fType').value;
        if (s) params.set('search', s);
        if (st) params.set('state', st);
        if (tp) params.set('substance_type', tp);
        if (activeStatFilter === 'cas') params.set('has_cas', '1');
        if (activeStatFilter === 'ghs') params.set('has_ghs', '1');
        if (activeStatFilter === 'sds') params.set('has_sds', '1');

        const d = await apiFetch('/v1/api/chemicals.php?' + params);
        if (d.success) {
            chemicals = d.data.chemicals || [];
            totalItems = d.data.total || 0;
            totalPages = d.data.total_pages || 0;
            render();
            renderPagination();
        }
    } catch(e) { mc.innerHTML = emptyState('fa-exclamation-triangle', e.message); }
}

function render() {
    const mc = document.getElementById('mainContent');
    if (!chemicals.length) { mc.innerHTML = emptyState('fa-flask', 'ไม่พบข้อมูลสารเคมี'); return; }

    if (currentView === 'grid') {
        mc.innerHTML = `<div class="cm-grid">${chemicals.map(c => renderCard(c)).join('')}</div>`;
        loadCardModels();
    } else {
        const visibleCols = tableColumns.filter(c => c.visible);
        mc.innerHTML = `<div class="ci-card" style="overflow:hidden">
            ${renderTableToolbar()}
            <div style="overflow-x:auto"><table class="cm-table" id="chemTable">
            <thead><tr id="chemTableHead">
                ${visibleCols.map(col => renderTh(col)).join('')}
            </tr></thead>
            <tbody>${chemicals.map((c,i) => renderRow(c, (page-1)*perPage+i+1)).join('')}</tbody>
        </table></div></div>`;
        initHeaderDragDrop();
    }
}

function renderTableToolbar() {
    const visCols = tableColumns.filter(c => c.visible).length;
    const hasSortOverride = sortField !== 'name' || sortDir !== 'ASC';
    const sortLabel = tableColumns.find(c => c.sortKey === sortField)?.label || sortField;
    const sortChip = hasSortOverride ? `
        <span class="cm-ttb-sep"></span>
        <button class="cm-ttb-sort"
            onclick="sortField='name';sortDir='ASC';localStorage.setItem('chemSortField','name');localStorage.setItem('chemSortDir','ASC');page=1;loadChemicals();"
            title="คลิกเพื่อรีเซ็ต Sort">
            <i class="fas fa-sort-amount-${sortDir==='ASC'?'up':'down'}-alt"></i>
            ${sortLabel} ${sortDir==='ASC'?'↑':'↓'}
            <span class="ts-x"><i class="fas fa-times"></i></span>
        </button>` : '';

    return `<div class="cm-table-toolbar">
        <div class="cm-ttb-l">
            <span class="cm-ttb-badge"><i class="fas fa-table"></i> Table View</span>
            <span class="cm-ttb-sep"></span>
            <span class="cm-ttb-count"><strong>${num(totalItems)}</strong> รายการ</span>
            ${sortChip}
        </div>
        <div class="cm-ttb-r">
            <div class="cm-perpage">
                <span class="cm-perpage-lbl">แสดง</span>
                <select onchange="changePerPage(this.value)">
                    ${[10,20,50,100].map(n => `<option value="${n}" ${n==perPage?'selected':''}>${n} / หน้า</option>`).join('')}
                </select>
            </div>
            <div class="cm-col-dropdown">
                <button class="cm-col-btn" onclick="toggleColPanel(event)">
                    <i class="fas fa-sliders-h" style="font-size:11px"></i>
                    คอลัมน์
                    <span class="col-count-badge">${visCols}/${tableColumns.length}</span>
                    <i class="fas fa-chevron-down" style="font-size:9px;opacity:.5"></i>
                </button>
                <div class="cm-col-panel" id="colPanel">
                    <div class="cm-col-panel-hdr">
                        <span><i class="fas fa-th-list"></i> จัดการคอลัมน์</span>
                        <button onclick="resetColumns()"><i class="fas fa-undo" style="font-size:9px;margin-right:3px"></i>รีเซ็ต</button>
                    </div>
                    <div id="colPanelList">
                        ${tableColumns.map((col, i) => `
                        <div class="cm-col-item" draggable="true" data-col-idx="${i}"
                             ondragstart="colDragStart(event,${i})" ondragover="colDragOver(event)" ondragenter="colDragEnter(event,this)" ondragleave="colDragLeave(event,this)" ondrop="colDrop(event,${i})" ondragend="colDragEnd(event)">
                            <span class="col-drag-handle"><i class="fas fa-grip-vertical"></i></span>
                            <input type="checkbox" id="colChk_${col.key}" ${col.visible?'checked':''}
                                   onchange="toggleColumn('${col.key}',this.checked)">
                            <label for="colChk_${col.key}">${col.label}</label>
                        </div>`).join('')}
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

function renderTh(col) {
    if (!col.sortable) {
        return `<th class="cm-th-sort" data-col="${col.key}" style="${col.width?'width:'+col.width:''}${col.align?';text-align:'+col.align:''}">
            <span class="sort-label"><span class="th-grip"><i class="fas fa-grip-vertical"></i></span>${col.label}</span>
        </th>`;
    }
    const isActive = sortField === col.sortKey;
    const cls = isActive ? (sortDir==='ASC' ? 'sort-asc' : 'sort-desc') : '';
    return `<th class="cm-th-sort ${cls}" data-col="${col.key}" data-sort="${col.sortKey}"
            onclick="doSort('${col.sortKey}')"
            style="${col.width?'width:'+col.width:''}${col.align?';text-align:'+col.align:''}">
        <span class="sort-label">
            <span class="th-grip"><i class="fas fa-grip-vertical"></i></span>
            ${col.label}
            <span class="sort-icon"><i class="fas fa-caret-up si-up"></i><i class="fas fa-caret-down si-down"></i></span>
        </span>
    </th>`;
}

function renderCard(c) {
    const stateIcon = {solid:'fa-cube',liquid:'fa-tint',gas:'fa-wind'}[c.physical_state]||'fa-atom';
    const stateColor = {solid:'#7c3aed',liquid:'#2563eb',gas:'#d97706'}[c.physical_state]||'#6b7280';
    const stateBg = {solid:'#ede9fe',liquid:'#dbeafe',gas:'#fef3c7'}[c.physical_state]||'#f3f4f6';
    const stateTag = {solid:'cm-tag-solid',liquid:'cm-tag-liquid',gas:'cm-tag-gas'}[c.physical_state]||'';
    const pics = parseJson(c.hazard_pictograms);

    return `<div class="ci-card cm-card" onclick="showDetail(${c.id})">
        <div class="ci-card-body">
            <div class="cm-card-top">
                ${c.molecular_formula && c.molecular_formula !== 'N/A' ? `<div class="cm-card-struct"><div class="cm-card-formula-inner">${miniFormula(c.molecular_formula)}</div></div>` 
                             : `<div class="cm-card-icon" style="background:${stateBg};color:${stateColor}"><i class="fas ${stateIcon}"></i></div>`}
                <div style="flex:1;min-width:0">
                    <div class="cm-card-name">${esc(c.name)}</div>
                    ${c.cas_number ? `<div class="cm-card-cas">CAS ${esc(c.cas_number)}</div>` : '<div class="cm-card-cas" style="color:#ccc">No CAS</div>'}
                </div>
                ${c.verified ? '<i class="fas fa-check-circle" style="color:#22c55e;font-size:14px" title="Verified"></i>' : ''}
            </div>
            <div id="card3d_${c.id}" class="cm-card-3d" style="display:none" onclick="event.stopPropagation()"></div>
            <div class="cm-card-tags">
                <span class="cm-tag ${stateTag}">${c.physical_state||'—'}</span>
                ${c.manufacturer_name ? `<span class="cm-tag cm-tag-mfr"><i class="fas fa-industry" style="margin-right:3px;font-size:9px"></i>${esc(c.manufacturer_name)}</span>` : ''}
                ${c.substance_category ? `<span class="cm-tag cm-tag-cat">${esc(truncate(c.substance_category,25))}</span>` : ''}
                ${pics.length ? `<span class="cm-tag cm-tag-ghs"><i class="fas fa-exclamation-triangle" style="margin-right:3px;font-size:9px"></i>GHS</span>` : ''}
                ${c.sds_count > 0 ? `<span class="cm-tag cm-tag-sds"><i class="fas fa-file-pdf" style="margin-right:3px;font-size:9px"></i>${c.sds_count}</span>` : ''}
            </div>
            <div class="cm-card-footer">
                <div class="cm-badges">
                    <span class="cm-badge-icon"><i class="fas fa-box"></i> ${c.container_count||0}</span>
                    ${c.catalogue_number ? `<span class="cm-badge-icon"><i class="fas fa-barcode"></i> ${esc(c.catalogue_number)}</span>` : ''}
                </div>
                <i class="fas fa-chevron-right" style="font-size:10px"></i>
            </div>
        </div>
    </div>`;
}

function renderRow(c, idx) {
    const pics = parseJson(c.hazard_pictograms);
    const visibleCols = tableColumns.filter(col => col.visible);
    const cellMap = {
        idx: `<td style="color:#aaa;text-align:center">${idx}</td>`,
        name: `<td><div style="font-weight:600;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(c.name)}</div>
            ${c.substance_category ? `<div style="font-size:11px;color:#aaa;margin-top:1px">${esc(truncate(c.substance_category,40))}</div>` : ''}</td>`,
        cas: `<td style="font-family:monospace;font-size:12px">${c.cas_number||'<span style="color:#ddd">—</span>'}</td>`,
        manufacturer: `<td style="font-size:12px;color:#666">${esc(c.manufacturer_name||'—')}</td>`,
        catalogue: `<td style="font-size:12px;color:#666">${esc(c.catalogue_number||'—')}</td>`,
        state: `<td>${statePill(c.physical_state)}</td>`,
        type: `<td style="font-size:11px;color:#888">${c.substance_type==='HomogeneousSubstance'?'Homo':'Hetero'}</td>`,
        category: `<td style="font-size:12px;color:#666">${esc(c.substance_category||'—')}</td>`,
        mw: `<td style="font-size:12px;color:#666;font-family:monospace">${c.molecular_weight||'—'}</td>`,
        formula: `<td style="font-size:12px;color:#666;font-family:monospace">${esc(c.molecular_formula||'—')}</td>`,
        sds: `<td style="text-align:center">${c.sds_count>0?`<span class="cm-tag cm-tag-sds">${c.sds_count}</span>`:'<span style="color:#ddd">—</span>'}</td>`,
        ghs: `<td style="text-align:center">${c.has_ghs?'<i class="fas fa-check-circle" style="color:#22c55e"></i>':'<span style="color:#ddd">—</span>'}</td>`,
        containers: `<td style="text-align:center">${c.container_count>0?`<span style="font-size:12px;font-weight:600;color:#7c3aed">${c.container_count}</span>`:'<span style="color:#ddd">—</span>'}</td>`,
        date: `<td style="font-size:12px;color:#888">${formatDate(c.created_at)}</td>`,
    };
    return `<tr onclick="showDetail(${c.id})">${visibleCols.map(col => cellMap[col.key]||'<td>—</td>').join('')}</tr>`;
}

// ═══════ Detail View ═══════
async function showDetail(id) {
    document.getElementById('detailBody').innerHTML = '<div class="ci-loading" style="padding:40px"><div class="ci-spinner"></div></div>';
    document.getElementById('detailModal').classList.add('show');
    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=detail&id=' + id);
        if (!d.success) throw new Error(d.error);
        currentDetail = d.data;
        renderDetail(d.data);
    } catch(e) { document.getElementById('detailBody').innerHTML = `<div style="padding:20px;color:#ef4444">${e.message}</div>`; }
}

function renderDetail(c) {
    document.getElementById('detailTitle').innerHTML = `<i class="fas fa-flask" style="color:var(--accent)"></i> ${esc(c.name)}`;
    const pics = c.ghs?.ghs_pictograms || parseJson(c.hazard_pictograms) || [];
    const signal = c.ghs?.signal_word || c.signal_word || 'None';

    let html = `
    <!-- Tabs -->
    <div class="cm-tabs-wrap">
      <div class="cm-tabs">
        <button class="cm-tab active" onclick="switchTab(this,'tabInfo')"><i class="fas fa-info-circle"></i> ข้อมูลทั่วไป</button>
        <button class="cm-tab" onclick="switchTab(this,'tabGhs')"><i class="fas fa-shield-alt"></i> GHS / Safety</button>
        <button class="cm-tab" onclick="switchTab(this,'tabSds')"><i class="fas fa-file-pdf"></i> SDS & Files <span class="cm-tab-badge">${(c.sds_files||[]).length}</span></button>
        <button class="cm-tab" onclick="switchTab(this,'tabPackaging')"><i class="fas fa-box-open"></i> บรรจุภัณฑ์ <span class="cm-tab-badge">${(c.packaging||[]).length}</span></button>
        <button class="cm-tab" onclick="switchTab(this,'tabStock')"><i class="fas fa-boxes"></i> คลัง <span class="cm-tab-badge">${(c.containers||[]).length}</span></button>
      </div>
    </div>

    <!-- Tab: Info -->
    <div class="cm-tab-body" id="tabInfo">
        ${c.molecular_formula && c.molecular_formula !== 'N/A' ? renderFormulaVisual(c) : (c.image_url ? `
        <div class="cm-structure-preview">
            <div class="cm-structure-img-wrap">
                <img src="${esc(c.image_url)}" alt="Molecular Structure" 
                     onerror="this.parentElement.parentElement.style.display='none'"
                     onclick="window.open(this.src.replace('300x300','900x900'),'_blank')">
            </div>
        </div>` : '')}
        <div class="cm-info-grid">
            ${infoItem('ชื่อสาร', c.name)}
            ${infoItem('CAS Number', c.cas_number || '—', 'monospace')}
            ${infoItem('IUPAC Name', c.iupac_name || '—')}
            ${infoItem('สูตรโมเลกุล', c.molecular_formula || '—', 'monospace')}
            ${infoItem('น้ำหนักโมเลกุล', c.molecular_weight ? c.molecular_weight + ' g/mol' : '—')}
            ${infoItem('สถานะ', statePill(c.physical_state))}
            ${infoItem('ชนิดสาร', c.substance_type || '—')}
            ${infoItem('ประเภทสาร', c.substance_category || '—')}
            ${infoItem('ผู้ผลิต', c.manufacturer_name || '—')}
            ${infoItem('Catalogue No.', c.catalogue_number || '—', 'monospace')}
            ${infoItem('Category', c.category_name || '—')}
            ${infoItem('Verified', c.verified ? '<i class="fas fa-check-circle" style="color:#22c55e"></i> Yes' : '<i class="fas fa-times-circle" style="color:#ccc"></i> No')}
        </div>
        ${c.description ? `<div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0"><div class="cm-info-label">คำอธิบาย</div><p style="font-size:13px;color:#555;margin-top:4px">${esc(c.description)}</p></div>` : ''}
        ${IS_MANAGER ? `<div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0;display:flex;gap:8px;flex-wrap:wrap">
            <button onclick="editChemical(${c.id})" class="ci-btn ci-btn-sm ci-btn-primary"><i class="fas fa-edit"></i> แก้ไขข้อมูล</button>
            <button onclick="showGhsEditor(${c.id})" class="ci-btn ci-btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca"><i class="fas fa-exclamation-triangle"></i> GHS Data</button>
            <button onclick="showUpload(${c.id})" class="ci-btn ci-btn-sm" style="background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe"><i class="fas fa-upload"></i> Upload File</button>
        </div>` : `<div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0">
            <button onclick="showUpload(${c.id})" class="ci-btn ci-btn-sm" style="background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe"><i class="fas fa-upload"></i> Upload File</button>
        </div>`}
    </div>

    <!-- Tab: GHS -->
    <div class="cm-tab-body" id="tabGhs" style="display:none">
        ${renderGhsTab(c)}
    </div>

    <!-- Tab: SDS -->
    <div class="cm-tab-body" id="tabSds" style="display:none">
        ${renderSdsTab(c)}
    </div>

    <!-- Tab: Packaging -->
    <div class="cm-tab-body" id="tabPackaging" style="display:none">
        ${renderPackagingTab(c)}
    </div>

    <!-- Tab: Stock -->
    <div class="cm-tab-body" id="tabStock" style="display:none">
        ${renderStockTab(c)}
    </div>`;

    document.getElementById('detailBody').innerHTML = html;

    // Load 3D models for packaging cards after DOM is updated
    const pkgs = c.packaging || [];
    if (pkgs.length) {
        setTimeout(() => loadPkg3dModels(pkgs.map(p => p.id)), 100);
    }
}

function renderGhsTab(c) {
    const ghs = c.ghs;
    const pics = ghs?.ghs_pictograms || [];
    const signal = ghs?.signal_word || 'None';

    if (!ghs) {
        return `<div style="text-align:center;padding:24px;color:#999">
            <i class="fas fa-shield-alt" style="font-size:36px;opacity:.3;display:block;margin-bottom:12px"></i>
            <p>ยังไม่มีข้อมูล GHS / Safety</p>
            ${IS_MANAGER ? `<button onclick="showGhsEditor(${c.id})" class="ci-btn ci-btn-primary ci-btn-sm" style="margin-top:12px"><i class="fas fa-plus"></i> เพิ่มข้อมูล GHS</button>` : ''}
        </div>`;
    }

    return `
        <!-- Signal Word -->
        <div style="margin-bottom:16px;display:flex;align-items:center;gap:12px">
            <span class="cm-info-label">SIGNAL WORD</span>
            <span class="${signal==='Danger'?'cm-signal-danger':signal==='Warning'?'cm-signal-warning':'cm-signal-none'}">${signal}</span>
        </div>

        <!-- Pictograms -->
        ${pics.length ? `<div style="margin-bottom:20px">
            <div class="cm-info-label" style="margin-bottom:8px">GHS PICTOGRAMS</div>
            <div class="cm-ghs-grid">
                ${pics.map(p => GHS_PICTOGRAMS[p] ? `<div class="cm-ghs-sym active" title="${GHS_PICTOGRAMS[p].name} — ${GHS_PICTOGRAMS[p].desc}">
                    <img src="${GHS_PICTOGRAMS[p].img}" alt="${p}">
                    <div class="ghs-label">${p}</div>
                </div>` : '').join('')}
            </div>
        </div>` : ''}

        <!-- Hazard & Precautionary -->
        <div class="cm-info-grid" style="margin-bottom:16px">
            ${ghs.h_statements_text ? `<div class="cm-info-item"><div class="cm-info-label">HAZARD STATEMENTS (H)</div><div style="font-size:13px;color:#dc2626">${esc(ghs.h_statements_text)}</div></div>` : ''}
            ${ghs.p_statements_text ? `<div class="cm-info-item"><div class="cm-info-label">PRECAUTIONARY STATEMENTS (P)</div><div style="font-size:13px;color:#2563eb">${esc(ghs.p_statements_text)}</div></div>` : ''}
        </div>

        <!-- Safety Summary -->
        ${ghs.safety_summary ? `<div style="background:#fefce8;border:1px solid #fef08a;border-radius:10px;padding:14px;margin-bottom:16px">
            <div class="cm-info-label" style="margin-bottom:6px"><i class="fas fa-shield-alt" style="color:#ca8a04"></i> SAFETY SUMMARY</div>
            <p style="font-size:13px;color:#713f12">${esc(ghs.safety_summary)}</p>
        </div>` : ''}

        <!-- First Aid -->
        ${(ghs.first_aid_inhalation||ghs.first_aid_skin||ghs.first_aid_eye||ghs.first_aid_ingestion) ? `
        <div style="margin-bottom:16px">
            <div class="cm-info-label" style="margin-bottom:8px"><i class="fas fa-first-aid" style="color:#ef4444"></i> FIRST AID MEASURES</div>
            <div class="cm-info-grid">
                ${ghs.first_aid_inhalation ? infoItem('🌬️ Inhalation', ghs.first_aid_inhalation) : ''}
                ${ghs.first_aid_skin ? infoItem('🖐️ Skin', ghs.first_aid_skin) : ''}
                ${ghs.first_aid_eye ? infoItem('👁️ Eye', ghs.first_aid_eye) : ''}
                ${ghs.first_aid_ingestion ? infoItem('🍽️ Ingestion', ghs.first_aid_ingestion) : ''}
            </div>
        </div>` : ''}

        <!-- Storage & Handling -->
        <div class="cm-info-grid">
            ${ghs.handling_precautions ? infoItem('⚠️ การจัดการ/ข้อควรระวัง', ghs.handling_precautions) : ''}
            ${ghs.storage_instructions ? infoItem('📦 การเก็บรักษา', ghs.storage_instructions) : ''}
            ${ghs.disposal_instructions ? infoItem('🗑️ การกำจัด', ghs.disposal_instructions) : ''}
            ${ghs.exposure_limits ? infoItem('📊 Exposure Limits', ghs.exposure_limits) : ''}
        </div>

        ${IS_MANAGER ? `<div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0">
            <button onclick="showGhsEditor(${c.id})" class="ci-btn ci-btn-sm ci-btn-primary"><i class="fas fa-edit"></i> แก้ไข GHS Data</button>
        </div>` : ''}`;
}

function renderSdsTab(c) {
    const files = c.sds_files || [];
    const now = new Date(); now.setHours(0,0,0,0);

    // Cache files for edit access
    window._sdsFilesCache = window._sdsFilesCache || {};
    files.forEach(f => { window._sdsFilesCache[f.id] = f; });

    // Count expiry statuses for summary
    let expiredCount=0, soonCount=0;
    files.forEach(f => {
        if (!f.expiry_date) return;
        const exp = new Date(f.expiry_date); exp.setHours(0,0,0,0);
        const days = Math.round((exp - now) / 86400000);
        if (days < 0) expiredCount++;
        else if (days <= 90) soonCount++;
    });

    let summaryBadges = '';
    if (expiredCount) summaryBadges += `<span class="cm-expiry-badge cm-expiry-expired"><i class="fas fa-times-circle"></i> หมดอายุ ${expiredCount} ไฟล์</span>`;
    if (soonCount) summaryBadges += `<span class="cm-expiry-badge cm-expiry-soon" style="margin-left:4px"><i class="fas fa-exclamation-triangle"></i> ใกล้หมด ${soonCount} ไฟล์</span>`;

    let html = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:${summaryBadges?'8px':'16px'}">
        <div style="display:flex;align-items:center;gap:8px">
            <div class="cm-info-label" style="margin:0">เอกสาร SDS &amp; FILES (${files.length})</div>
            ${summaryBadges}
        </div>
        <button onclick="showUpload(${c.id})" class="ci-btn ci-btn-sm" style="background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe">
            <i class="fas fa-plus"></i> เพิ่มเอกสาร
        </button>
    </div>`;

    if (summaryBadges) {
        html += `<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;margin-bottom:14px;font-size:11px;color:#92400e;display:flex;align-items:center;gap:6px">
            <i class="fas fa-shield-alt"></i>
            <span>เอกสาร SDS มีระยะเวลามีผล — ตรวจสอบและอัปเดตเอกสารที่หมดอายุเพื่อความปลอดภัยของห้องปฏิบัติการ</span>
        </div>`;
    }

    if (!files.length) {
        html += `<div style="text-align:center;padding:24px;color:#999">
            <i class="fas fa-folder-open" style="font-size:36px;opacity:.3;display:block;margin-bottom:12px"></i>
            <p>ยังไม่มีเอกสาร/ไฟล์</p>
            <p style="font-size:11px;margin-top:6px;color:#bbb">กดปุ่ม "เพิ่มเอกสาร" เพื่อแนบ SDS · Datasheet · Certificate</p>
        </div>`;
    } else {
        files.forEach(f => {
            const typeIcon = {sds:'fa-file-pdf',datasheet:'fa-file-alt',msds:'fa-file-medical',certificate:'fa-certificate',other:'fa-file'}[f.file_type]||'fa-file';
            const typeColor = {sds:'#ef4444',datasheet:'#3b82f6',msds:'#8b5cf6',certificate:'#22c55e',other:'#6b7280'}[f.file_type]||'#6b7280';
            const canDelete = IS_MANAGER || f.uploaded_by == USER_ID;

            // ─── Expiry calculation ───
            let expiryHtml = '';
            if (f.issue_date || f.expiry_date) {
                const vFrom  = f.issue_date  ? new Date(f.issue_date)  : null;
                const vUntil = f.expiry_date ? new Date(f.expiry_date) : null;
                if (vFrom) vFrom.setHours(0,0,0,0);
                if (vUntil) vUntil.setHours(0,0,0,0);

                let daysLeft = vUntil ? Math.round((vUntil - now) / 86400000) : null;
                let totalDays = (vFrom && vUntil) ? Math.round((vUntil - vFrom) / 86400000) : 1825;
                let elapsed = (vFrom && vUntil) ? Math.round((now - vFrom) / 86400000) : 0;
                let pct = Math.min(100, Math.max(0, Math.round((elapsed / Math.max(totalDays,1)) * 100)));

                let badgeCls, badgeIcon, badgeText, barColor, daysText;
                if (daysLeft === null) {
                    badgeCls='cm-expiry-ok'; badgeIcon='fa-shield-alt'; badgeText='มีผลบังคับ'; barColor='#22c55e'; daysText='';
                } else if (daysLeft < 0) {
                    badgeCls='cm-expiry-expired'; badgeIcon='fa-times-circle'; badgeText='หมดอายุแล้ว';
                    barColor='#ef4444'; daysText=`<span style="color:#dc2626">หมดอายุ ${Math.abs(daysLeft)} วันที่แล้ว</span>`;
                } else if (daysLeft <= 90) {
                    badgeCls='cm-expiry-soon'; badgeIcon='fa-exclamation-triangle'; badgeText=`เหลือ ${daysLeft} วัน`;
                    barColor='#f59e0b'; daysText=`<span style="color:#d97706"><i class="fas fa-clock" style="font-size:9px"></i> เหลือ ${daysLeft} วัน</span>`;
                } else {
                    badgeCls='cm-expiry-ok'; badgeIcon='fa-check-circle'; badgeText=`เหลือ ${daysLeft} วัน`;
                    barColor='#22c55e'; daysText=`<span style="color:#15803d"><i class="fas fa-check-circle" style="font-size:9px"></i> เหลือ ${daysLeft} วัน</span>`;
                }

                const fmtDate = d => d ? d.toLocaleDateString('th-TH',{day:'2-digit',month:'short',year:'2-digit'}) : '—';

                expiryHtml = `<div class="cm-file-expiry">
                    <div class="cm-file-expiry-row">
                        <div class="cm-file-expiry-label"><i class="fas fa-calendar-check" style="color:${barColor}"></i> อายุเอกสาร</div>
                        <div style="display:flex;align-items:center;gap:6px">
                            <span class="cm-expiry-badge ${badgeCls}"><i class="fas ${badgeIcon}"></i> ${badgeText}</span>
                        </div>
                    </div>
                    <div class="cm-expiry-bar-wrap"><div class="cm-expiry-bar" style="width:${pct}%;background:${barColor}"></div></div>
                    <div class="cm-expiry-footer">
                        <div class="cm-file-expiry-dates">
                            <span><i class="fas fa-play-circle" style="color:#94a3b8"></i> ${fmtDate(vFrom)}</span>
                            <span><i class="fas fa-flag-checkered" style="color:#94a3b8"></i> ${fmtDate(vUntil)}</span>
                        </div>
                        <div class="cm-expiry-days">${daysText}</div>
                    </div>
                </div>`;
            }

            // ─── Upload timestamp ───
            const uploadDt = f.created_at ? new Date(f.created_at) : null;
            const uploadTs = uploadDt ? `<span class="cm-upload-ts"><i class="fas fa-upload"></i> ${uploadDt.toLocaleDateString('th-TH',{day:'2-digit',month:'short',year:'2-digit'})} ${uploadDt.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'})}</span>` : '';

            html += `<div class="cm-file-card">
                <div class="cm-file-card-main">
                    <div class="cm-file-icon" style="background:${typeColor}15;color:${typeColor}"><i class="fas ${typeIcon}"></i></div>
                    <div class="cm-file-info">
                        <div class="cm-file-name">${esc(f.title)}</div>
                        <div class="cm-file-meta">
                            <span style="background:${typeColor}15;color:${typeColor};padding:1px 6px;border-radius:4px;font-weight:600">${f.file_type.toUpperCase()}</span>
                            ${f.language ? `<span style="background:#f1f5f9;padding:1px 6px;border-radius:4px">${f.language.toUpperCase()}</span>` : ''}
                            ${f.file_size ? `<span><i class="fas fa-hdd" style="font-size:9px"></i> ${formatBytes(f.file_size)}</span>` : '<span><i class="fas fa-link" style="font-size:9px"></i> URL</span>'}
                            <span><i class="fas fa-user" style="font-size:9px"></i> ${esc((f.first_name+' '+f.last_name).trim()||'—')}</span>
                            ${uploadTs}
                        </div>
                    </div>
                    <div class="cm-file-actions">
                        ${f.file_path ? `<a href="${f.file_path}" target="_blank" class="ci-btn ci-btn-sm" style="padding:6px 10px;background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe" title="ดาวน์โหลด"><i class="fas fa-download"></i></a>` : ''}
                        ${f.file_url ? `<a href="${f.file_url}" target="_blank" class="ci-btn ci-btn-sm" style="padding:6px 10px;background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe" title="เปิดลิงก์"><i class="fas fa-external-link-alt"></i></a>` : ''}
                        ${canDelete ? `<button onclick="event.stopPropagation();showEditFile(${f.id},${c.id})" class="ci-btn ci-btn-sm" style="padding:6px 10px;color:#d97706;border:1px solid #fde68a;background:#fffbeb" title="แก้ไข"><i class="fas fa-pen"></i></button>` : ''}
                        ${canDelete ? `<button onclick="event.stopPropagation();deleteFile(${f.id},${c.id})" class="ci-btn ci-btn-sm" style="padding:6px 10px;color:#ef4444;border:1px solid #fecaca" title="ลบ"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </div>
                ${expiryHtml}
            </div>`;
        });
    }
    return html;
}

function renderStockTab(c) {
    const containers = c.containers || [];
    if (!containers.length) {
        return `<div style="text-align:center;padding:24px;color:#999">
            <i class="fas fa-box-open" style="font-size:36px;opacity:.3;display:block;margin-bottom:12px"></i>
            <p>ไม่มี Container ที่ active</p>
        </div>`;
    }
    return `<div style="overflow-x:auto"><table class="cm-table">
        <thead><tr><th>Container</th><th>จำนวน</th><th>Lab</th><th>เจ้าของ</th><th>หมดอายุ</th></tr></thead>
        <tbody>${containers.map(cn => `<tr>
            <td style="font-family:monospace;font-size:12px">${esc(cn.container_number||'—')}</td>
            <td>${cn.current_quantity||0} ${esc(cn.unit||'')}</td>
            <td>${esc(cn.lab_name||'—')}</td>
            <td>${esc((cn.first_name||'')+' '+(cn.last_name||''))}</td>
            <td>${cn.expiry_date ? formatDate(cn.expiry_date) : '—'}</td>
        </tr>`).join('')}</tbody>
    </table></div>`;
}

// ═══════ Packaging Tab ═══════
const CONTAINER_TYPES = {
    bottle:'ขวด', vial:'ไวอัล', flask:'ขวดรูปชมพู่', canister:'กระป๋อง',
    cylinder:'ถังแก๊ส', ampoule:'แอมพูล', bag:'ถุง', gallon:'แกลลอน', drum:'ถัง', other:'อื่นๆ'
};
const CONTAINER_MATERIALS = {
    glass:'แก้ว', plastic:'พลาสติก', metal:'โลหะ', hdpe:'HDPE', amber_glass:'แก้วสีชา', other:'อื่นๆ'
};
const CONTAINER_TYPE_ICONS = {
    bottle:'fa-wine-bottle', vial:'fa-vial', flask:'fa-flask', canister:'fa-oil-can',
    cylinder:'fa-database', ampoule:'fa-syringe', bag:'fa-shopping-bag', gallon:'fa-jug-detergent',
    drum:'fa-drum', other:'fa-box'
};

function renderPackagingTab(c) {
    const pkgs = c.packaging || [];
    let html = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <div>
            <div class="cm-info-label" style="margin:0">บรรจุภัณฑ์สำหรับสารนี้ (${pkgs.length})</div>
            <div style="font-size:11px;color:#aaa;margin-top:2px">กำหนดขนาดบรรจุภัณฑ์เพื่อใช้ตอนเพิ่มขวดสาร · รองรับโมเดล 3D</div>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            ${IS_MANAGER ? `<button onclick="showPackagingForm(${c.id})" class="ci-btn ci-btn-sm" style="background:#fffbeb;color:#d97706;border:1px solid #fde68a">
                <i class="fas fa-plus"></i> เพิ่มบรรจุภัณฑ์
            </button>` : ''}
            <button onclick="showModelRequestForm(${c.id})" class="cm-pkg-request-btn">
                <i class="fas fa-cube"></i> ขอโมเดล 3D
            </button>
        </div>
    </div>`;

    // Packaging form placeholder
    html += `<div id="pkgFormArea"></div>`;
    // Model request form placeholder
    html += `<div id="modelRequestArea"></div>`;

    if (!pkgs.length) {
        html += `<div style="text-align:center;padding:24px;color:#999">
            <i class="fas fa-box-open" style="font-size:36px;opacity:.3;display:block;margin-bottom:12px"></i>
            <p>ยังไม่มีบรรจุภัณฑ์กำหนดไว้</p>
            ${IS_MANAGER ? `<p style="font-size:12px;margin-top:6px">กดปุ่ม "เพิ่มบรรจุภัณฑ์" เพื่อเริ่มกำหนดขนาดต่างๆ</p>` : ''}
        </div>`;
    } else {
        html += `<div class="cm-pkg-grid">`;
        pkgs.forEach(p => {
            const typeLabel = CONTAINER_TYPES[p.container_type] || p.container_type;
            const matLabel = CONTAINER_MATERIALS[p.container_material] || p.container_material;
            const icon = CONTAINER_TYPE_ICONS[p.container_type] || 'fa-box';
            html += `<div class="cm-pkg-card ${p.is_default?'is-default':''}" id="pkgCard_${p.id}">
                <div class="cm-pkg-card-img" id="pkgPreview_${p.id}">
                    ${p.image_url
                        ? `<img src="${esc(p.image_url)}" alt="${esc(p.label)}" onerror="this.style.display='none';this.nextElementSibling.style.display='block'" class="pkg-fallback">
                           <i class="fas ${icon} pkg-no-img" style="display:none"></i>`
                        : `<i class="fas ${icon} pkg-no-img"></i>`}
                </div>
                <div class="cm-pkg-card-body">
                    <div class="cm-pkg-card-label">
                        ${esc(p.label)}
                        ${p.is_default ? '<span class="pkg-default-badge">ค่าเริ่มต้น</span>' : ''}
                    </div>
                    <div class="cm-pkg-card-detail">
                        <div class="pkg-row"><i class="fas ${icon}"></i> ${typeLabel} · ${matLabel}</div>
                        <div class="pkg-row"><i class="fas fa-ruler-combined"></i> ${p.capacity} ${p.capacity_unit}</div>
                        ${p.supplier_name ? `<div class="pkg-row"><i class="fas fa-industry"></i> ${esc(p.supplier_name)}</div>` : ''}
                        ${p.catalogue_number ? `<div class="pkg-row"><i class="fas fa-barcode"></i> ${esc(p.catalogue_number)}</div>` : ''}
                        ${p.unit_price ? `<div class="pkg-row"><i class="fas fa-tag"></i> ${Number(p.unit_price).toLocaleString()} ${esc(p.currency||'THB')}</div>` : ''}
                        ${p.description ? `<div class="pkg-row" style="margin-top:4px;color:#666"><i class="fas fa-info-circle"></i> ${esc(truncate(p.description,60))}</div>` : ''}
                    </div>
                    ${IS_MANAGER ? `<div class="cm-pkg-card-actions">
                        <button onclick="event.stopPropagation();showPackagingForm(${c.id},${p.id})" class="ci-btn ci-btn-sm" style="flex:1;font-size:11px"><i class="fas fa-edit"></i> แก้ไข</button>
                        <button onclick="event.stopPropagation();open3dPreview(${p.id})" class="ci-btn ci-btn-sm" style="font-size:11px;color:#6C5CE7" title="ดูโมเดล 3D เต็มจอ"><i class="fas fa-expand"></i></button>
                        <button onclick="event.stopPropagation();deletePackaging(${p.id},${c.id})" class="ci-btn ci-btn-sm" style="font-size:11px;color:#ef4444"><i class="fas fa-trash"></i></button>
                    </div>` : `<div class="cm-pkg-card-actions">
                        <button onclick="event.stopPropagation();open3dPreview(${p.id})" class="ci-btn ci-btn-sm" style="flex:1;font-size:11px;color:#6C5CE7"><i class="fas fa-cube"></i> ดูโมเดล 3D</button>
                    </div>`}
                </div>
            </div>`;
        });
        html += `</div>`;
    }
    return html;
}

function showPackagingForm(chemId, editId) {
    // If editing, find the existing data
    const pkg = editId && currentDetail?.packaging ? currentDetail.packaging.find(p => p.id == editId) : null;
    const isEdit = !!pkg;

    const formHtml = `
    <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:16px;margin-bottom:16px;animation:cmFadeIn .2s ease">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <div style="font-size:13px;font-weight:600;color:#92400e"><i class="fas fa-${isEdit?'edit':'plus-circle'}"></i> ${isEdit?'แก้ไข':'เพิ่ม'}บรรจุภัณฑ์</div>
            <button onclick="document.getElementById('pkgFormArea').innerHTML=''" style="background:none;border:none;cursor:pointer;color:#aaa;font-size:14px"><i class="fas fa-times"></i></button>
        </div>
        <form id="pkgForm" onsubmit="submitPackaging(event,${chemId},${editId||0})">
            <div class="cm-field">
                <label>ชื่อบรรจุภัณฑ์ <span class="req">*</span></label>
                <input type="text" name="label" required placeholder="เช่น ขวดแก้ว 2.5 L" value="${isEdit ? esc(pkg.label) : ''}">
                <div class="hint">ชื่อที่แสดงตอนเลือกบรรจุภัณฑ์</div>
            </div>
            <div class="cm-pkg-form-grid">
                <div class="cm-field">
                    <label>ประเภทภาชนะ <span class="req">*</span></label>
                    <select name="container_type" onchange="syncModelTypeFilter(this.value)">
                        ${Object.entries(CONTAINER_TYPES).map(([k,v]) => `<option value="${k}" ${isEdit && pkg.container_type===k?'selected':''}>${v}</option>`).join('')}
                    </select>
                </div>
                <div class="cm-field">
                    <label>วัสดุ</label>
                    <select name="container_material">
                        ${Object.entries(CONTAINER_MATERIALS).map(([k,v]) => `<option value="${k}" ${isEdit && pkg.container_material===k?'selected':''}>${v}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="cm-pkg-form-grid">
                <div class="cm-field">
                    <label>ปริมาตร/ขนาด <span class="req">*</span></label>
                    <input type="number" name="capacity" step="any" min="0.01" required placeholder="เช่น 2.5" value="${isEdit ? pkg.capacity : ''}">
                </div>
                <div class="cm-field">
                    <label>หน่วย</label>
                    <select name="capacity_unit">
                        ${['mL','L','g','kg','mg','oz','lb','gal'].map(u => `<option value="${u}" ${isEdit && pkg.capacity_unit===u?'selected':''} ${!isEdit && u==='mL'?'selected':''}>${u}</option>`).join('')}
                    </select>
                </div>
            </div>
            <!-- ═══ 3D Model Selector ═══ -->
            <div class="cm-field">
                <label><i class="fas fa-cube" style="color:#7c3aed;margin-right:4px"></i> โมเดล 3D บรรจุภัณฑ์</label>
                <input type="hidden" name="model_3d_id" id="pkgModel3dId" value="${isEdit && pkg.model_3d_id ? pkg.model_3d_id : ''}">
                <div class="pkg-model-sel ${isEdit && pkg.model_3d_id ? 'has-selection' : ''}" id="pkgModelSel">
                    <div class="pkg-model-sel-hdr" onclick="toggleModelSelector()">
                        <i class="fas fa-cube hdr-icon"></i>
                        <span class="sel-title" id="pkgModelSelTitle">${isEdit && pkg.model_3d_id ? '<i class="fas fa-check-circle" style="color:#16a34a;margin-right:4px"></i>โมเดลถูกเลือกแล้ว' : 'เลือกโมเดล 3D (ไม่บังคับ)'}</span>
                        <span id="pkgModelSelClear" class="sel-clear" style="display:${isEdit && pkg.model_3d_id ? 'inline':'none'}" onclick="event.stopPropagation();clearModelSelection()"><i class="fas fa-times"></i> ยกเลิก</span>
                        <i class="fas fa-chevron-down" style="color:#aaa;font-size:10px;transition:transform .2s" id="pkgModelSelArrow"></i>
                    </div>
                    <div id="pkgModelSelBody" style="display:none">
                        <div class="pkg-model-sel-search">
                            <input type="text" id="pkgModelSearch" placeholder="ค้นหาชื่อโมเดล..." oninput="debounceModelSearch()">
                            <select id="pkgModelTypeFilter" onchange="loadModelOptions()">
                                <option value="">ทุกประเภท</option>
                                ${Object.entries(CONTAINER_TYPES).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}
                            </select>
                        </div>
                        <div id="pkgModelGrid" class="pkg-model-sel-grid">
                            <div class="pkg-model-sel-loading"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>
                        </div>
                    </div>
                    <div id="pkgModelCurrentPreview" style="display:none"></div>
                </div>
                <div class="hint">เลือกโมเดล 3D จากคลัง · หากยังไม่มีโมเดลที่ต้องการ <a href="/v1/pages/models3d.php" target="_blank" style="color:#6C5CE7">จัดการโมเดล 3D</a></div>
            </div>
            <div class="cm-field">
                <label><i class="fas fa-image" style="color:#3b82f6;margin-right:4px"></i> ลิงก์รูปภาพ (Fallback)</label>
                <input type="url" name="image_url" placeholder="https://example.com/image.jpg" value="${isEdit ? esc(pkg.image_url||'') : ''}">
                <div class="hint">URL รูปภาพสำรอง — ใช้เมื่อไม่มีโมเดล 3D</div>
            </div>
            <div class="cm-pkg-form-grid">
                <div class="cm-field">
                    <label>ผู้จำหน่าย / Supplier</label>
                    <input type="text" name="supplier_name" placeholder="เช่น Merck, Sigma-Aldrich" value="${isEdit ? esc(pkg.supplier_name||'') : ''}">
                </div>
                <div class="cm-field">
                    <label>Catalogue No.</label>
                    <input type="text" name="catalogue_number" placeholder="เช่น 100317" value="${isEdit ? esc(pkg.catalogue_number||'') : ''}">
                </div>
            </div>
            <div class="cm-pkg-form-grid">
                <div class="cm-field">
                    <label>ราคาต่อหน่วย</label>
                    <input type="number" name="unit_price" step="0.01" min="0" placeholder="เช่น 1500" value="${isEdit ? pkg.unit_price||'' : ''}">
                </div>
                <div class="cm-field">
                    <label>สกุลเงิน</label>
                    <select name="currency">
                        <option value="THB" ${isEdit && pkg.currency==='THB'?'selected':''}>THB (฿)</option>
                        <option value="USD" ${isEdit && pkg.currency==='USD'?'selected':''}>USD ($)</option>
                        <option value="EUR" ${isEdit && pkg.currency==='EUR'?'selected':''}>EUR (€)</option>
                    </select>
                </div>
            </div>
            <div class="cm-field">
                <label>คำอธิบาย</label>
                <textarea name="description" rows="2" placeholder="รายละเอียดเพิ่มเติม เช่น ฝาเกลียวเกลียว, สีชา">${isEdit ? esc(pkg.description||'') : ''}</textarea>
            </div>
            <div class="cm-field" style="margin-bottom:0">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="is_default" value="1" ${isEdit && pkg.is_default ? 'checked' : ''} style="width:16px;height:16px;accent-color:#d97706">
                    <span style="font-size:12px;color:#92400e">ตั้งเป็นบรรจุภัณฑ์เริ่มต้น</span>
                </label>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;padding-top:12px;border-top:1px solid #fde68a">
                <button type="button" onclick="document.getElementById('pkgFormArea').innerHTML=''" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
                <button type="submit" class="ci-btn ci-btn-sm" style="background:#d97706;color:#fff" id="pkgSubmitBtn">
                    <i class="fas fa-${isEdit?'save':'plus'}"></i> ${isEdit?'บันทึก':'เพิ่ม'}
                </button>
            </div>
        </form>
    </div>`;

    document.getElementById('pkgFormArea').innerHTML = formHtml;
    // Scroll to form
    document.getElementById('pkgFormArea').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setTimeout(() => document.querySelector('#pkgForm input[name="label"]').focus(), 100);

    // Auto-set filter from form's container_type & load gallery
    const initType = document.querySelector('#pkgForm select[name="container_type"]')?.value || '';
    if (initType) {
        const filt = document.getElementById('pkgModelTypeFilter');
        if (filt) filt.value = initType;
    }
    // If editing and has model_3d_id, load current model preview
    if (isEdit && pkg.model_3d_id) {
        loadCurrentModelPreview(pkg.model_3d_id);
    }
}

/* ═══ 3D Model Selector Functions ═══ */
let _modelSearchTimer = null;
let _modelCache = [];
let _previewModelId = null;

function syncModelTypeFilter(type) {
    const filt = document.getElementById('pkgModelTypeFilter');
    if (filt) { filt.value = type; loadModelOptions(); }
}

function toggleModelSelector() {
    const body = document.getElementById('pkgModelSelBody');
    const arrow = document.getElementById('pkgModelSelArrow');
    if (!body) return;
    const isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : 'block';
    if (arrow) arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
    if (!isOpen) loadModelOptions();
}

function debounceModelSearch() {
    clearTimeout(_modelSearchTimer);
    _modelSearchTimer = setTimeout(() => loadModelOptions(), 350);
}

async function loadModelOptions() {
    const grid = document.getElementById('pkgModelGrid');
    if (!grid) return;
    grid.innerHTML = '<div class="pkg-model-sel-loading"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>';

    const search = document.getElementById('pkgModelSearch')?.value?.trim() || '';
    const typeFilter = document.getElementById('pkgModelTypeFilter')?.value || '';
    let url = '/v1/api/models3d.php?action=list&per_page=50&is_active=1';
    if (search)     url += '&search=' + encodeURIComponent(search);
    if (typeFilter) url += '&container_type=' + encodeURIComponent(typeFilter);

    try {
        const d = await apiFetch(url);
        const models = d.data || d.models || [];
        _modelCache = models;
        const selectedId = document.getElementById('pkgModel3dId')?.value;
        if (!models.length) {
            grid.innerHTML = `<div class="pkg-model-sel-empty" style="grid-column:1/-1">
                <i class="fas fa-cube"></i>
                ไม่พบโมเดล 3D${typeFilter ? ' ประเภท ' + (CONTAINER_TYPES[typeFilter]||typeFilter) : ''}${search ? ' "' + search + '"' : ''}
                <br><small style="color:#aaa">ลองเปลี่ยนตัวกรอง หรือ<a href="/v1/pages/models3d.php" target="_blank" style="color:#7c3aed"> เพิ่มโมเดลใหม่</a></small>
            </div>`;
            return;
        }

        grid.innerHTML = models.map(m => {
            const isSelected = selectedId && String(m.id) === String(selectedId);
            const typeLabel = CONTAINER_TYPES[m.container_type] || m.container_type || '';
            const matLabel  = CONTAINER_MATERIALS[m.container_material] || m.container_material || '';
            const srcBadge  = m.source_type === 'embed' ? 'EMBED' : 'GLB';
            let thumbContent;
            if (m.thumbnail_path) {
                thumbContent = `<img src="/v1/${m.thumbnail_path}" style="width:100%;height:100%;object-fit:cover;pointer-events:none" onerror="this.style.display='none';this.parentElement.querySelector('.ms-icon').style.display='flex'"><i class="ms-icon fas fa-cube" style="display:none"></i>`;
            } else if (m.source_type === 'embed' && m.embed_url) {
                thumbContent = `<iframe src="${esc(m.embed_url)}" loading="lazy" sandbox="allow-scripts allow-same-origin"></iframe>`;
            } else if (m.file_url || m.file_path) {
                const src = m.file_url || '/v1/' + m.file_path;
                thumbContent = `<model-viewer src="${esc(src)}" auto-rotate camera-controls interaction-prompt="none"></model-viewer>`;
            } else {
                thumbContent = `<i class="ms-icon fas fa-cube"></i>`;
            }
            return `<div class="pkg-ms-item ${isSelected?'selected':''}" data-model-id="${m.id}" title="${esc(m.label||m.original_name||'')}">
                <div class="ms-check"><i class="fas fa-check"></i></div>
                <div class="pkg-ms-thumb">
                    ${thumbContent}
                    <div class="ms-click-overlay" onclick="selectModel(${m.id})"></div>
                    <span class="ms-src">${srcBadge}</span>
                    <div class="ms-preview-btn" onclick="event.stopPropagation();previewModel(${m.id})" title="ดูตัวอย่าง"><i class="fas fa-expand"></i></div>
                </div>
                <div class="pkg-ms-info" onclick="selectModel(${m.id})">
                    <div class="ms-name">${esc(m.label || m.original_name || 'โมเดล #' + m.id)}</div>
                    <div class="ms-meta">${typeLabel}${matLabel ? ' · ' + matLabel : ''}</div>
                </div>
            </div>`;
        }).join('');
    } catch (err) {
        grid.innerHTML = `<div class="pkg-model-sel-empty" style="grid-column:1/-1"><i class="fas fa-exclamation-triangle"></i> โหลดโมเดลไม่สำเร็จ<br><small>${esc(err.message||'')}</small></div>`;
    }
}

function selectModel(modelId) {
    const model = _modelCache.find(m => m.id == modelId);
    if (!model) return;

    // 1. Update hidden input
    const hiddenInput = document.getElementById('pkgModel3dId');
    if (hiddenInput) hiddenInput.value = modelId;

    // 2. Update header text + green state
    const title = document.getElementById('pkgModelSelTitle');
    if (title) title.innerHTML = '<i class="fas fa-check-circle" style="color:#16a34a;margin-right:4px"></i>' + esc(model.label || model.original_name || 'โมเดล #' + modelId);
    const clearBtn = document.getElementById('pkgModelSelClear');
    if (clearBtn) clearBtn.style.display = 'inline';

    // 3. Update .has-selection on container
    const selBox = document.getElementById('pkgModelSel');
    if (selBox) selBox.classList.add('has-selection');

    // 4. Update selection in grid — remove from all, add to correct one
    document.querySelectorAll('.pkg-ms-item').forEach(el => el.classList.remove('selected'));
    const target = document.querySelector(`.pkg-ms-item[data-model-id="${modelId}"]`);
    if (target) target.classList.add('selected');

    // 5. Show current preview bar
    showModelPreviewBar(model);

    // 6. Close gallery after short delay
    setTimeout(() => {
        const body = document.getElementById('pkgModelSelBody');
        const arrow = document.getElementById('pkgModelSelArrow');
        if (body) body.style.display = 'none';
        if (arrow) arrow.style.transform = '';
    }, 350);
}

function clearModelSelection() {
    const hiddenInput = document.getElementById('pkgModel3dId');
    if (hiddenInput) hiddenInput.value = '';

    const title = document.getElementById('pkgModelSelTitle');
    if (title) title.textContent = 'เลือกโมเดล 3D (ไม่บังคับ)';

    const clearBtn = document.getElementById('pkgModelSelClear');
    if (clearBtn) clearBtn.style.display = 'none';

    const selBox = document.getElementById('pkgModelSel');
    if (selBox) selBox.classList.remove('has-selection');

    document.querySelectorAll('.pkg-ms-item').forEach(el => el.classList.remove('selected'));

    const preview = document.getElementById('pkgModelCurrentPreview');
    if (preview) { preview.style.display = 'none'; preview.innerHTML = ''; }
}

function showModelPreviewBar(model) {
    const preview = document.getElementById('pkgModelCurrentPreview');
    if (!preview) return;
    const typeLabel = CONTAINER_TYPES[model.container_type] || model.container_type || '';
    const matLabel  = CONTAINER_MATERIALS[model.container_material] || model.container_material || '';
    let viewerHtml;
    if (model.source_type === 'embed' && model.embed_url) {
        viewerHtml = `<iframe src="${esc(model.embed_url)}" style="width:100%;height:100%;border:none;pointer-events:none"></iframe>`;
    } else if (model.file_url || model.file_path) {
        const src = model.file_url || '/v1/' + model.file_path;
        viewerHtml = `<model-viewer src="${esc(src)}" auto-rotate camera-controls interaction-prompt="none" style="width:100%;height:100%"></model-viewer>`;
    } else {
        viewerHtml = `<i class="fas fa-cube"></i>`;
    }
    preview.innerHTML = `<div class="pkg-model-current">
        <div class="mc-badge"><i class="fas fa-check"></i></div>
        <div class="mc-preview" onclick="previewModel(${model.id})" title="คลิกเพื่อดูตัวอย่างเต็ม">${viewerHtml}</div>
        <div class="mc-info">
            <div class="mc-name">${esc(model.label || model.original_name || 'โมเดล #' + model.id)}</div>
            <div class="mc-type">${typeLabel}${matLabel ? ' · ' + matLabel : ''} · ${model.source_type === 'embed' ? 'Embed' : 'GLB'}</div>
        </div>
    </div>`;
    preview.style.display = 'block';
}

/* ═══ Preview Modal ═══ */
function previewModel(modelId) {
    const model = _modelCache.find(m => m.id == modelId);
    if (!model) return;
    _previewModelId = modelId;
    const typeLabel = CONTAINER_TYPES[model.container_type] || model.container_type || '';
    const matLabel  = CONTAINER_MATERIALS[model.container_material] || model.container_material || '';
    let viewerHtml;
    if (model.source_type === 'embed' && model.embed_url) {
        viewerHtml = `<iframe src="${esc(model.embed_url)}" style="width:100%;height:100%;border:none"></iframe>`;
    } else if (model.file_url || model.file_path) {
        const src = model.file_url || '/v1/' + model.file_path;
        viewerHtml = `<model-viewer src="${esc(src)}" auto-rotate camera-controls camera-orbit="45deg 75deg auto" shadow-intensity="1" environment-image="neutral" interaction-prompt="auto" style="width:100%;height:100%;--poster-color:transparent"></model-viewer>`;
    } else {
        viewerHtml = `<div style="color:#aaa;text-align:center"><i class="fas fa-cube" style="font-size:48px;opacity:.3;display:block;margin-bottom:10px"></i>ไม่มีไฟล์โมเดล</div>`;
    }
    const selectedId = document.getElementById('pkgModel3dId')?.value;
    const isAlreadySelected = selectedId && String(selectedId) === String(modelId);

    const modal = document.createElement('div');
    modal.className = 'pkg-preview-modal-bg';
    modal.id = 'pkgPreviewModal';
    modal.innerHTML = `<div class="pkg-preview-modal">
        <div class="pkg-preview-modal-hdr">
            <div>
                <div class="pm-title"><i class="fas fa-cube" style="margin-right:6px;color:#7c3aed"></i>${esc(model.label || model.original_name || 'โมเดล #' + model.id)}</div>
                <div class="pm-meta">${typeLabel}${matLabel ? ' · ' + matLabel : ''} · ${model.source_type === 'embed' ? 'Embed' : 'GLB'}${model.file_size ? ' · ' + formatFileSize(model.file_size) : ''}</div>
            </div>
            <div class="pm-close" onclick="closePreviewModal()"><i class="fas fa-times"></i></div>
        </div>
        <div class="pkg-preview-modal-body">${viewerHtml}</div>
        <div class="pkg-preview-modal-footer">
            <button class="pm-cancel" onclick="closePreviewModal()"><i class="fas fa-arrow-left" style="margin-right:4px"></i> กลับ</button>
            <button class="pm-select" onclick="selectFromPreview(${modelId})">
                <i class="fas fa-${isAlreadySelected ? 'check-circle' : 'check'}" style="margin-right:4px"></i>
                ${isAlreadySelected ? 'เลือกอยู่แล้ว' : 'เลือกโมเดลนี้'}
            </button>
        </div>
    </div>`;
    document.body.appendChild(modal);
    // Close on backdrop click
    modal.addEventListener('click', (e) => { if (e.target === modal) closePreviewModal(); });
    // Close on ESC
    document.addEventListener('keydown', _previewEscHandler);
}

function _previewEscHandler(e) {
    if (e.key === 'Escape') closePreviewModal();
}

function closePreviewModal() {
    const modal = document.getElementById('pkgPreviewModal');
    if (modal) modal.remove();
    document.removeEventListener('keydown', _previewEscHandler);
    _previewModelId = null;
}

function selectFromPreview(modelId) {
    selectModel(modelId);
    closePreviewModal();
}

function formatFileSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1048576).toFixed(1) + ' MB';
}

async function loadCurrentModelPreview(modelId) {
    try {
        const d = await apiFetch('/v1/api/models3d.php?action=detail&id=' + modelId);
        const model = d.data || d.model || d;
        if (model && model.id) {
            _modelCache = [model];
            // Set green state on header
            const selBox = document.getElementById('pkgModelSel');
            if (selBox) selBox.classList.add('has-selection');
            const title = document.getElementById('pkgModelSelTitle');
            if (title) title.innerHTML = '<i class="fas fa-check-circle" style="color:#16a34a;margin-right:4px"></i>' + esc(model.label || model.original_name || 'โมเดล #' + model.id);
            const clearBtn = document.getElementById('pkgModelSelClear');
            if (clearBtn) clearBtn.style.display = 'inline';
            showModelPreviewBar(model);
        }
    } catch (e) { /* silently fail */ }
}

async function submitPackaging(e, chemId, editId) {
    e.preventDefault();
    const btn = document.getElementById('pkgSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    const form = document.getElementById('pkgForm');
    const data = Object.fromEntries(new FormData(form));
    data.chemical_id = chemId;
    data.is_default = data.is_default ? 1 : 0;
    // Include model_3d_id (can be empty string = null)
    const m3dId = document.getElementById('pkgModel3dId')?.value;
    data.model_3d_id = m3dId ? parseInt(m3dId) : null;
    if (editId) data.id = editId;

    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=packaging_save', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        if (d.success) {
            showToast(editId ? 'อัปเดตบรรจุภัณฑ์สำเร็จ' : 'เพิ่มบรรจุภัณฑ์สำเร็จ', 'success');
            // Refresh detail
            showDetail(chemId);
            // After refresh, switch to packaging tab
            setTimeout(() => {
                const tabs = document.querySelectorAll('#detailBody .cm-tab');
                tabs.forEach(t => t.classList.remove('active'));
                const pkgTab = [...tabs].find(t => t.textContent.includes('บรรจุภัณฑ์'));
                if (pkgTab) pkgTab.classList.add('active');
                document.querySelectorAll('#detailBody .cm-tab-body').forEach(b => b.style.display = 'none');
                const tabPkg = document.getElementById('tabPackaging');
                if (tabPkg) tabPkg.style.display = 'block';
            }, 300);
        } else {
            throw new Error(d.error);
        }
    } catch(err) {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-${editId?'save':'plus'}"></i> ${editId?'บันทึก':'เพิ่ม'}`;
        showToast(err.message, 'error');
    }
}

async function deletePackaging(pkgId, chemId) {
    if (!confirm('ต้องการลบบรรจุภัณฑ์นี้?')) return;
    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=packaging&id=' + pkgId, { method: 'DELETE' });
        if (d.success) {
            showToast('ลบบรรจุภัณฑ์สำเร็จ', 'success');
            showDetail(chemId);
            setTimeout(() => {
                const tabs = document.querySelectorAll('#detailBody .cm-tab');
                tabs.forEach(t => t.classList.remove('active'));
                const pkgTab = [...tabs].find(t => t.textContent.includes('บรรจุภัณฑ์'));
                if (pkgTab) pkgTab.classList.add('active');
                document.querySelectorAll('#detailBody .cm-tab-body').forEach(b => b.style.display = 'none');
                const tabPkg = document.getElementById('tabPackaging');
                if (tabPkg) tabPkg.style.display = 'block';
            }, 300);
        } else throw new Error(d.error);
    } catch(e) { showToast(e.message, 'error'); }
}

// ═══════ 3D Model Integration for Packaging (Standalone) ═══════
// Cache for loaded 3D models per packaging
const pkg3dModelCache = {};

async function loadPkg3dModels(pkgIds) {
    for (const pkgId of pkgIds) {
        try {
            const d = await apiFetch('/v1/api/models3d.php?action=for_packaging&id=' + pkgId);
            if (d.success && d.data) {
                pkg3dModelCache[pkgId] = d.data;
                renderPkg3dPreview(pkgId, d.data);
            }
        } catch (e) {
            // Silently fail — keep fallback image/icon
        }
    }
}

function renderPkg3dPreview(pkgId, model) {
    const container = document.getElementById('pkgPreview_' + pkgId);
    if (!container || !model) return;
    const hasFile = !!(model.file_url || model.embed_url);
    if (!hasFile) return;

    // Detect capabilities
    const isGlb = model.file_url && /\.(glb|gltf)$/i.test(model.file_url);
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    const canAR = isGlb && isMobile;
    const isEmbed = model.source_type === 'embed';
    const modelLabel = esc((model.label || '3D Model').substring(0, 30));

    // Build overlay buttons
    let overlayBtns = `<button class="pkg-ov-btn" onclick="event.stopPropagation();open3dPreview(${pkgId})" title="ดูเต็มจอ"><i class="fas fa-expand"></i></button>`;
    if (canAR) {
        overlayBtns = `<button class="pkg-ov-btn ov-ar" onclick="event.stopPropagation();openPkgAR(${pkgId})" title="ดูใน AR"><i class="fas fa-vr-cardboard"></i></button>` + overlayBtns;
    }

    if (isGlb) {
        // Live inline <model-viewer> — interactive 3D right in the card
        container.innerHTML = `
            <span class="pkg-3d-badge"><i class="fas fa-cube"></i> 3D</span>
            ${canAR ? '<span class="pkg-ar-badge"><i class="fas fa-vr-cardboard"></i> AR</span>' : ''}
            <div class="pkg-3d-loading" id="pkgLoad_${pkgId}"><i class="fas fa-spinner"></i><span>กำลังโหลด 3D...</span></div>
            <model-viewer
                src="${model.file_url}"
                alt="${modelLabel}"
                auto-rotate
                camera-controls
                touch-action="pan-y"
                interaction-prompt="none"
                shadow-intensity="0.7"
                shadow-softness="0.5"
                exposure="1.05"
                environment-image="neutral"
                tone-mapping="commerce"
                style="width:100%;height:100%"
                onload="document.getElementById('pkgLoad_${pkgId}')?.classList.add('loaded')"
            ></model-viewer>
            <div class="pkg-3d-overlay">
                <span class="pkg-ov-label">${modelLabel}</span>
                <div class="pkg-ov-btns">${overlayBtns}</div>
            </div>
        `;
        // Handle load event via JS (onload attribute may not fire for custom elements)
        const mv = container.querySelector('model-viewer');
        if (mv) {
            mv.addEventListener('load', () => {
                const ld = document.getElementById('pkgLoad_' + pkgId);
                if (ld) ld.classList.add('loaded');
            });
        }
        container.style.background = canAR
            ? 'linear-gradient(135deg, #0a1628 0%, #0f2a1a 50%, #0f0f1a 100%)'
            : 'linear-gradient(135deg, #0f0f1a 0%, #1a1a3a 100%)';

    } else if (isEmbed) {
        // Embed iframe preview (Kiri Engine, Sketchfab, etc.)
        let embedUrl = model.embed_url;
        if (embedUrl.includes('sketchfab')) {
            embedUrl += (embedUrl.includes('?') ? '&' : '?') + 'autostart=1&ui_stop=0&ui_inspector=0&ui_watermark=0&ui_controls=0';
        }
        const prov = embedUrl.includes('kiri') ? 'Kiri' : (embedUrl.includes('sketchfab') ? 'Sketchfab' : 'Embed');
        container.innerHTML = `
            <span class="pkg-3d-badge"><i class="fas fa-cube"></i> ${prov}</span>
            <iframe src="${embedUrl}" allow="autoplay; fullscreen; xr-spatial-tracking" loading="lazy"></iframe>
            <div class="pkg-3d-overlay">
                <span class="pkg-ov-label">${modelLabel}</span>
                <div class="pkg-ov-btns">${overlayBtns}</div>
            </div>
        `;
        container.style.background = 'linear-gradient(135deg, #0f0f1a 0%, #1a1a3a 100%)';

    } else if (model.file_url) {
        // Non-GLB file — static placeholder with preview
        container.innerHTML = `
            <span class="pkg-3d-badge"><i class="fas fa-cube"></i> 3D</span>
            <div class="pkg-3d-placeholder" onclick="event.stopPropagation();open3dPreview(${pkgId})" title="คลิกเพื่อดูโมเดล 3D">
                <i class="fas fa-cube"></i>
                <span>${modelLabel}</span>
                <small><i class="fas fa-expand"></i> คลิกเพื่อดู</small>
            </div>
        `;
        container.style.background = 'linear-gradient(135deg, #0f0f1a 0%, #1a1a3a 100%)';
    }
}

// Open AR directly from packaging card
function openPkgAR(pkgId) {
    const model = pkg3dModelCache[pkgId];
    if (!model) return;
    arCurrentModel = model;
    arCurrentPkgId = pkgId;
    openArViewer(model);
    // Auto-launch AR after modal opens
    setTimeout(() => { launchAR(); }, 800);
}

// ═══════ Card 3D Preview (inline model-viewer in grid cards) ═══════
const card3dModelCache = {};    // chemId → [models...]
const card3dActiveIdx = {};     // chemId → active index

async function loadCardModels() {
    if (!chemicals.length) return;
    const ids = chemicals.map(c => c.id).join(',');
    try {
        const d = await apiFetch('/v1/api/models3d.php?action=models_for_cards&chemical_ids=' + ids);
        if (d.success && d.data) {
            for (const [cid, models] of Object.entries(d.data)) {
                const chemId = parseInt(cid);
                // API now returns array of models per chemical
                const arr = Array.isArray(models) ? models : [models];
                if (!arr.length) continue;
                card3dModelCache[chemId] = arr;
                card3dActiveIdx[chemId] = 0;
                renderCard3dPreview(chemId);
            }
        }
    } catch (e) { /* silent — 3D preview is optional enhancement */ }
}

function renderCard3dPreview(chemId) {
    const container = document.getElementById('card3d_' + chemId);
    const models = card3dModelCache[chemId];
    if (!container || !models || !models.length) return;

    const idx = card3dActiveIdx[chemId] || 0;
    const model = models[idx];
    if (!model) return;

    const isGlb = model.file_url && /\.(glb|gltf)$/i.test(model.file_url);
    const isEmbed = model.source_type === 'embed' && model.embed_url;
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    const canAR = isGlb && isMobile;

    if (!isGlb && !isEmbed && !model.file_url) {
        container.style.display = 'none';
        return;
    }

    // Build top-left badges
    let badges = '<span class="c3d-tag-3d"><i class="fas fa-cube" style="font-size:7px;margin-right:2px"></i>3D</span>';
    if (canAR) badges += '<span class="c3d-tag-ar"><i class="fas fa-vr-cardboard" style="font-size:7px;margin-right:2px"></i>AR</span>';
    if (isEmbed) {
        const prov = (model.embed_url||'').includes('kiri') ? 'Kiri' : (model.embed_url||'').includes('sketchfab') ? 'Sketchfab' : 'Embed';
        badges += `<span class="c3d-tag-embed">${prov}</span>`;
    }

    // Build packaging switcher pills (only if >1 model)
    let switcherHtml = '';
    if (models.length > 1) {
        switcherHtml = '<div class="card3d-pkg-sw">' + models.map((m, i) => {
            const lbl = m.pkg_label || m.label || ('โมเดล ' + (i+1));
            const short = lbl.length > 18 ? lbl.substring(0,16) + '…' : lbl;
            return `<span class="sw-pill ${i===idx?'active':''}" onclick="event.stopPropagation();switchCardModel(${chemId},${i})" title="${esc(lbl)}">${esc(short)}</span>`;
        }).join('') + '</div>';
    }

    // Build viewer content
    let viewerHtml = '';
    if (isGlb) {
        viewerHtml = `<div class="card3d-viewer-wrap">
            <model-viewer src="${model.file_url}" alt="${esc(model.label||'3D')}"
                auto-rotate camera-controls touch-action="pan-y" interaction-prompt="none"
                shadow-intensity="0.6" exposure="1" environment-image="neutral"></model-viewer>
        </div>`;
    } else if (isEmbed) {
        let embedUrl = model.embed_url;
        if (embedUrl.includes('sketchfab')) {
            embedUrl += (embedUrl.includes('?') ? '&' : '?') + 'autostart=1&ui_stop=0&ui_inspector=0&ui_watermark=0&ui_controls=0';
        }
        viewerHtml = `<div class="card3d-viewer-wrap">
            <iframe src="${embedUrl}" allow="autoplay; fullscreen; xr-spatial-tracking" loading="lazy"></iframe>
        </div>`;
    } else if (model.file_url) {
        viewerHtml = `<div class="card3d-viewer-wrap">
            <div class="card3d-loading"><i class="fas fa-cube" style="font-size:24px;color:#6C5CE7;animation:none"></i></div>
        </div>`;
    }

    container.style.display = '';
    container.innerHTML = `
        <div class="card3d-badge">${badges}</div>
        ${viewerHtml}
        ${switcherHtml}
        <div class="card3d-expand" onclick="event.stopPropagation();openCard3dFull(${chemId})" title="ดูแบบเต็ม / AR"><i class="fas fa-expand"></i></div>
    `;
}

function switchCardModel(chemId, idx) {
    const models = card3dModelCache[chemId];
    if (!models || idx < 0 || idx >= models.length) return;
    card3dActiveIdx[chemId] = idx;
    renderCard3dPreview(chemId);
}

function openCard3dFull(chemId) {
    const models = card3dModelCache[chemId];
    const idx = card3dActiveIdx[chemId] || 0;
    const model = models?.[idx];
    if (!model) return;
    const chem = chemicals.find(c => c.id === chemId || c.id === String(chemId));
    if (chem) currentDetail = chem;
    arCurrentModel = model;
    arCurrentPkgId = model.pkg_id || null;
    openArViewer(model);
}

// ═══════ 3D & AR Viewer PRO ═══════
let arCurrentModel = null;
let arCurrentPkgId = null;
let arAutoRotate = true;
let arGyroEnabled = false;
let arIsFullscreen = false;
let arViewerType = null; // 'model-viewer' | 'iframe'

function open3dPreview(pkgId) {
    const model = pkg3dModelCache[pkgId];
    if (!model) {
        showToast('ยังไม่มีโมเดล 3D สำหรับบรรจุภัณฑ์นี้ — กดปุ่ม "ขอโมเดล 3D" เพื่อส่งคำขอ', 'warning');
        return;
    }
    arCurrentModel = model;
    arCurrentPkgId = pkgId;
    openArViewer(model);
}

function openArViewer(model) {
    const modal = document.getElementById('arViewerModal');
    const container = document.getElementById('arModelContainer');
    const loading = document.getElementById('arLoading');

    // Reset loading state completely
    loading.innerHTML = '<div class="ar-load-ring"><div></div><div></div><div></div></div><p>กำลังโหลดโมเดล 3D...</p>';
    loading.classList.remove('hidden');
    loading.style.opacity = '';
    loading.style.pointerEvents = '';

    // Clean previous content
    container.innerHTML = '';
    document.getElementById('arBtnAR').style.display = 'none';
    document.getElementById('arBtnSpatial').style.display = 'none';
    document.getElementById('arStatsBar').style.display = 'none';
    // Reset spatial AR state
    _clearSpatialArUI();

    // Set header info
    document.getElementById('arTitle').textContent = model.label || 'โมเดล 3D';
    const subtitle = model.container_type
        ? (CONTAINER_TYPES[model.container_type] || model.container_type) + (model.capacity ? ' ' + model.capacity + (model.capacity_unit || '') : '')
        : (model.file_url || model.embed_url || '').split('/').pop()?.substring(0, 30) || '';
    document.getElementById('arSubtitle').textContent = subtitle;

    // Provider badge
    const badge = document.getElementById('arProviderBadge');
    const url = model.embed_url || model.file_url || '';
    if (url.includes('kiriengine') || url.includes('kiri.app')) {
        badge.textContent = 'Kiri Engine'; badge.className = 'ar-provider-badge prov-kiri'; badge.style.display = '';
    } else if (url.includes('sketchfab')) {
        badge.textContent = 'Sketchfab'; badge.className = 'ar-provider-badge prov-sketchfab'; badge.style.display = '';
    } else if (model.source_type === 'upload') {
        badge.textContent = 'Upload'; badge.className = 'ar-provider-badge prov-upload'; badge.style.display = '';
    } else {
        badge.style.display = 'none';
    }

    // Chemical info card
    if (currentDetail) {
        document.getElementById('arChemCas').textContent = currentDetail.cas_number || '—';
        document.getElementById('arChemName').textContent = currentDetail.name || '—';
        let tags = '';
        const signal = currentDetail.ghs?.signal_word || currentDetail.signal_word;
        if (signal === 'Danger') tags += '<span class="ar-tag ar-tag-danger">⚠ DANGER</span>';
        else if (signal === 'Warning') tags += '<span class="ar-tag ar-tag-warning">⚠ WARNING</span>';
        const pics = currentDetail.ghs?.ghs_pictograms || [];
        if (pics.length) tags += '<span class="ar-tag ar-tag-ghs"><i class="fas fa-radiation" style="font-size:8px"></i> GHS×' + pics.length + '</span>';
        document.getElementById('arChemTags').innerHTML = tags;
        document.getElementById('arChemCard').style.display = '';
    } else {
        document.getElementById('arChemCard').style.display = 'none';
    }

    // Reset rotation state
    arAutoRotate = true;
    document.getElementById('arBtnRotate').classList.add('active');

    // Show modal immediately so viewer area gets dimensions
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    // Decide viewer type & inject after a frame so layout is established
    const isGlbFile = model.file_url && /\.(glb|gltf)$/i.test(model.file_url);
    const isEmbed = model.source_type === 'embed' && model.embed_url;

    requestAnimationFrame(() => {
        if (isGlbFile) {
            arViewerType = 'model-viewer';
            _injectModelViewer(model, container, loading);
        } else if (isEmbed) {
            arViewerType = 'iframe';
            _injectIframe(model.embed_url, container, loading);
        } else if (model.file_url) {
            arViewerType = 'iframe';
            const iframeSrc = '/v1/pages/viewer3d.php?src=' + encodeURIComponent(model.file_url) + '&embed=1&title=' + encodeURIComponent(model.label || '');
            _injectIframe(iframeSrc, container, loading);
        } else {
            loading.innerHTML = '<i class="fas fa-cube" style="font-size:48px;color:#555;margin-bottom:12px"></i><p>ไม่มีไฟล์โมเดล</p>';
        }
    });
}

function _hideArLoading() {
    const ld = document.getElementById('arLoading');
    if (ld) { ld.classList.add('hidden'); }
}

function _injectModelViewer(model, container, loading) {
    // Wait for model-viewer custom element to be defined
    const create = () => {
        const mv = document.createElement('model-viewer');
        mv.id = 'arModelViewer';
        mv.setAttribute('src', model.file_url);
        mv.setAttribute('alt', model.label || '3D Model');
        mv.setAttribute('auto-rotate', '');
        mv.setAttribute('camera-controls', '');
        mv.setAttribute('touch-action', 'pan-y');
        mv.setAttribute('interaction-prompt', 'auto');
        mv.setAttribute('shadow-intensity', '1');
        mv.setAttribute('shadow-softness', '0.8');
        mv.setAttribute('exposure', '1.1');
        mv.setAttribute('environment-image', 'neutral');
        mv.setAttribute('tone-mapping', 'commerce');
        mv.setAttribute('interpolation-decay', '100');
        // AR attributes
        mv.setAttribute('ar', '');
        mv.setAttribute('ar-modes', 'webxr scene-viewer quick-look');
        mv.setAttribute('ar-scale', 'auto');
        mv.setAttribute('ar-placement', 'floor');
        mv.setAttribute('xr-environment', '');
        // Custom AR button slot (hidden — we use our own)
        mv.innerHTML = '<button slot="ar-button" style="display:none"></button>';

        let loaded = false;
        const onLoaded = () => {
            if (loaded) return;
            loaded = true;
            _hideArLoading();
            updateArStats(mv);
            // Show AR button if supported
            try {
                if (mv.canActivateAR) {
                    document.getElementById('arBtnAR').style.display = '';
                    document.getElementById('arBtnSpatial').style.display = '';
                }
            } catch(e) {}
        };
        mv.addEventListener('load', onLoaded);
        mv.addEventListener('model-visibility', (e) => { if (e.detail?.visible) onLoaded(); });
        // Listen for AR session status (for spatial anchoring UI)
        mv.addEventListener('ar-status', (e) => { _onArSessionStatus(e.detail); });
        mv.addEventListener('ar-tracking', (e) => { _onArTracking(e.detail); });
        mv.addEventListener('error', (e) => {
            console.error('model-viewer error:', e);
            loading.innerHTML = '<i class="fas fa-exclamation-triangle" style="font-size:32px;color:#e17055;margin-bottom:8px"></i><p style="color:#e17055">โหลดโมเดลไม่สำเร็จ</p><p style="font-size:11px;color:#888;margin-top:4px">' + esc(model.file_url || '') + '</p>';
            loading.classList.remove('hidden');
        });
        // Fallback: hide loading after 8s even if load event doesn't fire
        setTimeout(() => { if (!loaded) { onLoaded(); } }, 8000);

        container.appendChild(mv);
    };

    // Ensure custom element is defined before creating
    if (customElements.get('model-viewer')) {
        create();
    } else {
        customElements.whenDefined('model-viewer').then(create).catch(() => {
            // model-viewer not loaded — fallback to iframe with viewer3d.php
            console.warn('model-viewer custom element not available, falling back to iframe');
            arViewerType = 'iframe';
            const src = '/v1/pages/viewer3d.php?src=' + encodeURIComponent(model.file_url) + '&embed=1&title=' + encodeURIComponent(model.label || '');
            _injectIframe(src, container, loading);
        });
        // Also set a timeout in case the CDN is completely unreachable
        setTimeout(() => {
            if (!customElements.get('model-viewer') && container.children.length === 0) {
                arViewerType = 'iframe';
                const src = '/v1/pages/viewer3d.php?src=' + encodeURIComponent(model.file_url) + '&embed=1&title=' + encodeURIComponent(model.label || '');
                _injectIframe(src, container, loading);
            }
        }, 5000);
    }
}

function _injectIframe(src, container, loading) {
    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.allowFullscreen = true;
    iframe.allow = 'autoplay; fullscreen; xr-spatial-tracking';
    iframe.onload = () => _hideArLoading();
    iframe.onerror = () => _hideArLoading();
    // Fallback timeout
    setTimeout(() => _hideArLoading(), 6000);
    container.appendChild(iframe);
}

function closeArViewer() {
    const modal = document.getElementById('arViewerModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';

    // Destroy model-viewer to free GPU resources
    const mv = document.getElementById('arModelViewer');
    if (mv) {
        mv.setAttribute('src', ''); // release GL context
        setTimeout(() => mv.remove(), 50);
    }
    document.getElementById('arModelContainer').innerHTML = '';
    document.getElementById('arBtnAR').style.display = 'none';
    document.getElementById('arBtnSpatial').style.display = 'none';
    document.getElementById('arStatsBar').style.display = 'none';
    _clearSpatialArUI();

    // Reset loading state for next open
    const ld = document.getElementById('arLoading');
    ld.innerHTML = '<div class="ar-load-ring"><div></div><div></div><div></div></div><p>กำลังโหลดโมเดล 3D...</p>';
    ld.classList.remove('hidden');

    // Reset gyroscope
    if (arGyroEnabled) {
        window.removeEventListener('deviceorientation', handleGyro);
        arGyroEnabled = false;
        document.getElementById('arBtnGyro').classList.remove('active');
    }

    arCurrentModel = null;
    arViewerType = null;
    if (arIsFullscreen && document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    }
    arIsFullscreen = false;
}

function launchAR() {
    const mv = document.getElementById('arModelViewer');
    if (mv && mv.canActivateAR) {
        _setArSpatialStatus('scan', 'กำลังเปิด AR — สแกนพื้นผิว...');
        _showArInstruction('เลื่อนอุปกรณ์ช้าๆ เพื่อสแกนพื้นผิว\nแตะเพื่อวางวัตถุ');
        mv.activateAR();
    } else {
        // Fallback: open model in scene-viewer (Android) or Quick Look (iOS)
        const model = arCurrentModel;
        if (model && model.file_url) {
            _launchNativeAR(model);
        } else {
            showToast('AR ไม่รองรับบนอุปกรณ์นี้ หรือโมเดลไม่ใช่ไฟล์ GLB/GLTF', 'warning');
        }
    }
}

// ═══════ Launch Spatial AR (dedicated page with full anchoring) ═══════
function launchSpatialAR() {
    const model = arCurrentModel;
    if (!model || !model.file_url) {
        showToast('ไม่พบไฟล์โมเดลสำหรับ Spatial AR', 'warning');
        return;
    }
    const params = new URLSearchParams({
        src: model.file_url,
        title: model.label || 'โมเดล 3D'
    });
    if (currentDetail) {
        params.set('chem_name', currentDetail.name || '');
        params.set('cas', currentDetail.cas_number || '');
        params.set('signal', currentDetail.ghs?.signal_word || currentDetail.signal_word || '');
    }
    window.open('/v1/ar/ar_spatial.php?' + params.toString(), '_blank');
}

// ═══════ Native AR Fallback (Scene Viewer / Quick Look) ═══════
function _launchNativeAR(model) {
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const fullUrl = window.location.origin + model.file_url;
    if (isIOS) {
        const a = document.createElement('a');
        a.rel = 'ar';
        a.href = model.file_url;
        const img = document.createElement('img');
        a.appendChild(img);
        document.body.appendChild(a);
        a.click();
        setTimeout(() => a.remove(), 100);
    } else {
        // Android Scene Viewer with anchoring enabled
        const params = new URLSearchParams({
            file: fullUrl,
            mode: 'ar_preferred',
            title: model.label || '',
            resizable: 'true',
            enable_vertical_placement: 'true'
        });
        const intentUrl = 'https://arvr.google.com/scene-viewer/1.0?' + params.toString();
        const a = document.createElement('a');
        a.href = 'intent://arvr.google.com/scene-viewer/1.0?' + params.toString()
            + '#Intent;scheme=https;package=com.google.android.googlequicksearchbox;action=android.intent.action.VIEW;S.browser_fallback_url='
            + encodeURIComponent(intentUrl) + ';end;';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => a.remove(), 100);
    }
}

// ═══════ AR Session Status Handler (model-viewer events) ═══════
let _arSpatialAnchored = false;

function _onArSessionStatus(status) {
    console.log('[AR] status:', status);
    switch (status) {
        case 'session-started':
            _arSpatialAnchored = false;
            _setArSpatialStatus('scan', 'สแกนพื้นผิว — เลื่อนกล้องช้าๆ');
            _showArInstruction('เลื่อนอุปกรณ์ช้าๆ เพื่อให้ระบบจดจำพื้นผิว');
            break;

        case 'object-placed':
            _setArSpatialStatus('placed', '✅ วางวัตถุแล้ว — สร้าง Anchor...');
            _hideArInstruction();
            // Auto-create spatial anchor after placement
            setTimeout(() => {
                _arSpatialAnchored = true;
                _setArSpatialStatus('anchored', '🔒 Spatial Anchor ยึดตำแหน่ง — เดินรอบได้');
                document.getElementById('arAnchorPill').classList.add('show');
                showToast('🔒 Spatial Anchor สร้างสำเร็จ — เดินรอบวัตถุได้!', 'success');
            }, 600);
            break;

        case 'failed':
            _clearSpatialArUI();
            showToast('AR session จบ', 'info');
            break;

        case 'not-presenting':
            _clearSpatialArUI();
            break;
    }
}

function _onArTracking(state) {
    console.log('[AR] tracking:', state);
    if (state === 'tracking' && !_arSpatialAnchored) {
        _setArSpatialStatus('scan', 'ตรวจจับพื้นผิวแล้ว — แตะเพื่อวางวัตถุ');
        _hideArInstruction();
    } else if (state === 'not-tracking') {
        _setArSpatialStatus('scan', 'กำลังสแกนพื้นผิว...');
        _showArInstruction('เลื่อนอุปกรณ์ช้าๆ เพื่อให้ระบบจดจำพื้นผิว');
    }
}

// ═══════ Spatial AR UI Helpers ═══════
function _setArSpatialStatus(type, text) {
    const el = document.getElementById('arSpatialStatus');
    const dot = document.getElementById('arSpDot');
    const txt = document.getElementById('arSpText');
    if (!type || !el) { if (el) el.classList.remove('show'); return; }
    dot.className = 'ar-sp-dot ' + type;
    txt.textContent = text;
    el.classList.add('show');
}

function _showArInstruction(text) {
    const el = document.getElementById('arSpInstruct');
    if (!el) return;
    document.getElementById('arSpInstructText').textContent = text;
    el.classList.add('show');
}

function _hideArInstruction() {
    const el = document.getElementById('arSpInstruct');
    if (el) el.classList.remove('show');
}

function _clearSpatialArUI() {
    _arSpatialAnchored = false;
    const st = document.getElementById('arSpatialStatus');
    if (st) st.classList.remove('show');
    const inst = document.getElementById('arSpInstruct');
    if (inst) inst.classList.remove('show');
    const pill = document.getElementById('arAnchorPill');
    if (pill) pill.classList.remove('show');
}

function toggleArAutoRotate() {
    arAutoRotate = !arAutoRotate;
    const btn = document.getElementById('arBtnRotate');
    btn.classList.toggle('active', arAutoRotate);
    const mv = document.getElementById('arModelViewer');
    if (mv) {
        if (arAutoRotate) mv.setAttribute('auto-rotate', '');
        else mv.removeAttribute('auto-rotate');
    }
}

function resetArCamera() {
    const mv = document.getElementById('arModelViewer');
    if (mv) {
        mv.cameraOrbit = 'auto auto auto';
        mv.cameraTarget = 'auto auto auto';
        mv.fieldOfView = 'auto';
        // model-viewer uses jumpCameraToGoal for instant reset
        if (typeof mv.jumpCameraToGoal === 'function') mv.jumpCameraToGoal();
    }
    showToast('รีเซ็ตมุมมอง', 'success');
}

function toggleGyro() {
    const btn = document.getElementById('arBtnGyro');
    arGyroEnabled = !arGyroEnabled;
    btn.classList.toggle('active', arGyroEnabled);
    // Request device orientation permission (iOS 13+)
    if (arGyroEnabled && typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
        DeviceOrientationEvent.requestPermission().then(state => {
            if (state !== 'granted') {
                showToast('ไม่ได้รับอนุญาตใช้ Gyroscope', 'warning');
                arGyroEnabled = false;
                btn.classList.remove('active');
            }
        }).catch(() => {});
    }
    if (arGyroEnabled) {
        window.addEventListener('deviceorientation', handleGyro);
        showToast('เปิดโหมด Gyroscope — เอียงอุปกรณ์เพื่อหมุนโมเดล', 'success');
    } else {
        window.removeEventListener('deviceorientation', handleGyro);
        showToast('ปิด Gyroscope', 'success');
    }
}

function handleGyro(e) {
    if (!arGyroEnabled) return;
    const mv = document.getElementById('arModelViewer');
    if (!mv) return;
    const beta = (e.beta || 0); // front-back tilt
    const gamma = (e.gamma || 0); // left-right tilt
    const theta = 90 + gamma * 2; // map to 0-180 horizontal
    const phi = 90 - beta; // map to vertical
    mv.cameraOrbit = theta + 'deg ' + Math.max(0, Math.min(180, phi)) + 'deg auto';
}

function toggleArFullscreen() {
    const area = document.getElementById('arViewerModal');
    if (!document.fullscreenElement) {
        area.requestFullscreen().then(() => { arIsFullscreen = true; }).catch(() => {});
    } else {
        document.exitFullscreen().then(() => { arIsFullscreen = false; }).catch(() => {});
    }
}

function openArNewTab() {
    const model = arCurrentModel;
    if (!model) return;
    if (model.embed_url) {
        window.open(model.embed_url, '_blank');
    } else if (model.file_url) {
        window.open('/v1/pages/viewer3d.php?src=' + encodeURIComponent(model.file_url) + '&title=' + encodeURIComponent(model.label || '3D Model'), '_blank');
    }
}

function updateArStats(mv) {
    try {
        const model = mv.model;
        if (!model) return;
        // model-viewer exposes model stats
        const statsBar = document.getElementById('arStatsBar');
        // Use InternalsAPI if available
        let info = '';
        if (mv.modelIsVisible) {
            statsBar.style.display = '';
            // Basic info from the model
            document.getElementById('arStatVert').innerHTML = '<i class="fas fa-draw-polygon"></i> ' + (mv.getAttribute('alt') || 'Model loaded');
            document.getElementById('arStatTri').innerHTML = '<i class="fas fa-cube"></i> ' + (arCurrentModel?.container_type ? (CONTAINER_TYPES[arCurrentModel.container_type] || arCurrentModel.container_type) : 'GLB');
            document.getElementById('arStatMat').innerHTML = '<i class="fas fa-palette"></i> ' + (arCurrentModel?.container_material ? (CONTAINER_MATERIALS[arCurrentModel.container_material] || arCurrentModel.container_material) : 'Standard');
        }
    } catch (e) { /* stats are optional */ }
}

// ═══════ Model Request Form (from Packaging tab) ═══════
function showModelRequestForm(chemId) {
    const c = currentDetail;
    const area = document.getElementById('modelRequestArea');
    if (!area) return;
    
    area.innerHTML = `
    <div style="background:#fef3c7;border:1.5px solid #fde68a;border-radius:12px;padding:16px;margin-bottom:16px;animation:cmFadeIn .2s ease">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <div style="font-size:13px;font-weight:600;color:#92400e"><i class="fas fa-cube"></i> ขอโมเดล 3D สำหรับบรรจุภัณฑ์</div>
            <button onclick="document.getElementById('modelRequestArea').innerHTML=''" style="background:none;border:none;cursor:pointer;color:#aaa;font-size:14px"><i class="fas fa-times"></i></button>
        </div>
        <form id="modelReqForm" onsubmit="submitModelRequest(event,${chemId})">
            <div class="cm-field">
                <label>หัวข้อคำขอ <span class="req">*</span></label>
                <input type="text" name="title" required placeholder="เช่น ต้องการโมเดลขวดแก้ว 2.5L สำหรับ ${esc(c?.name || '')}" 
                       value="ขอโมเดล 3D: ${esc(c?.name || '')}">
            </div>
            <div class="cm-pkg-form-grid">
                <div class="cm-field">
                    <label>ประเภทภาชนะ <span class="req">*</span></label>
                    <select name="container_type">
                        ${Object.entries(CONTAINER_TYPES).map(([k,v]) => `<option value="${k}">${v}</option>`).join('')}
                    </select>
                </div>
                <div class="cm-field">
                    <label>วัสดุ</label>
                    <select name="container_material">
                        <option value="">—</option>
                        ${Object.entries(CONTAINER_MATERIALS).map(([k,v]) => `<option value="${k}">${v}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="cm-pkg-form-grid">
                <div class="cm-field">
                    <label>ขนาดบรรจุ</label>
                    <input type="number" name="capacity" step="any" min="0" placeholder="เช่น 2.5">
                </div>
                <div class="cm-field">
                    <label>หน่วย</label>
                    <select name="capacity_unit">
                        <option value="">—</option>
                        ${['mL','L','g','kg','mg'].map(u => `<option value="${u}">${u}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="cm-field">
                <label>คำอธิบาย / รายละเอียดที่ต้องการ</label>
                <textarea name="description" rows="2" placeholder="รายละเอียดเพิ่มเติม เช่น สี ลักษณะฝา ฉลาก"></textarea>
            </div>
            <div class="cm-field">
                <label><i class="fas fa-image" style="color:#3b82f6;margin-right:4px"></i> ลิงก์ภาพอ้างอิง (ถ้ามี)</label>
                <input type="url" name="reference_image_url" placeholder="https://example.com/reference.jpg">
                <div class="hint">ภาพตัวอย่างเพื่อให้ผู้สร้างโมเดลใช้อ้างอิง</div>
            </div>
            <div class="cm-field">
                <label>ความเร่งด่วน</label>
                <select name="priority">
                    <option value="normal">ปกติ</option>
                    <option value="low">ต่ำ</option>
                    <option value="high">สูง</option>
                    <option value="urgent">เร่งด่วน</option>
                </select>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;padding-top:12px;border-top:1px solid #fde68a">
                <button type="button" onclick="document.getElementById('modelRequestArea').innerHTML=''" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
                <button type="submit" class="ci-btn ci-btn-sm" style="background:#d97706;color:#fff" id="reqSubmitBtn">
                    <i class="fas fa-paper-plane"></i> ส่งคำขอ
                </button>
            </div>
        </form>
    </div>`;

    area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function submitModelRequest(e, chemId) {
    e.preventDefault();
    const btn = document.getElementById('reqSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังส่ง...';

    const form = document.getElementById('modelReqForm');
    const data = Object.fromEntries(new FormData(form));
    data.chemical_id = chemId;

    try {
        const d = await apiFetch('/v1/api/models3d.php?action=request', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        if (d.success) {
            showToast('ส่งคำขอโมเดล 3D สำเร็จ! ทีมจะดำเนินการ', 'success');
            document.getElementById('modelRequestArea').innerHTML = '';
        } else throw new Error(d.error);
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> ส่งคำขอ';
        showToast(err.message, 'error');
    }
}

// ═══════ GHS Editor ═══════
async function showGhsEditor(chemId) {
    closeDetail();
    document.getElementById('ghsTitle').innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#ef4444"></i> แก้ไข GHS / Safety Data';
    document.getElementById('ghsBody').innerHTML = '<div class="ci-loading" style="padding:40px"><div class="ci-spinner"></div></div>';
    document.getElementById('ghsModal').classList.add('show');

    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=ghs&chemical_id=' + chemId);
        const ghs = d.data || {};
        const pics = ghs.ghs_pictograms || [];

        let html = `<form id="ghsForm" onsubmit="saveGhs(event,${chemId})">
        <!-- GHS Pictograms -->
        <div style="margin-bottom:20px">
            <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:8px">GHS Pictograms <span style="color:#aaa;font-weight:400">— คลิกเพื่อเลือก</span></label>
            <div class="cm-ghs-grid" id="ghsPicGrid">
                ${Object.entries(GHS_PICTOGRAMS).map(([k,v]) => `
                    <div class="cm-ghs-sym${pics.includes(k)?' active':''}" data-code="${k}" onclick="toggleGhsPic(this)">
                        <div class="ghs-check"><i class="fas fa-check"></i></div>
                        <img src="${v.img}" alt="${k}" title="${v.name}: ${v.desc}">
                        <div class="ghs-label">${k}</div>
                    </div>`).join('')}
            </div>
        </div>

        <!-- Signal Word -->
        <div class="cm-field">
            <label>Signal Word <span class="req">*</span></label>
            <select name="signal_word">
                <option value="None" ${ghs.signal_word==='None'?'selected':''}>None</option>
                <option value="Warning" ${ghs.signal_word==='Warning'?'selected':''}>⚠️ Warning</option>
                <option value="Danger" ${ghs.signal_word==='Danger'?'selected':''}>🔴 Danger</option>
            </select>
        </div>

        <!-- Hazard / Precautionary -->
        <div class="cm-field-row">
            <div class="cm-field">
                <label>H-Statements (Hazard)</label>
                <textarea name="h_statements_text" placeholder="เช่น H302: Harmful if swallowed">${esc(ghs.h_statements_text||'')}</textarea>
            </div>
            <div class="cm-field">
                <label>P-Statements (Precautionary)</label>
                <textarea name="p_statements_text" placeholder="เช่น P264: Wash hands thoroughly">${esc(ghs.p_statements_text||'')}</textarea>
            </div>
        </div>

        <!-- Safety Summary -->
        <div class="cm-field">
            <label>Safety Summary / ข้อปฏิบัติ</label>
            <textarea name="safety_summary" rows="3" placeholder="สรุปข้อควรปฏิบัติด้านความปลอดภัย...">${esc(ghs.safety_summary||'')}</textarea>
        </div>

        <!-- Handling / Storage / Disposal -->
        <div class="cm-field">
            <label>ข้อควรระวังในการจัดการ (Handling Precautions)</label>
            <textarea name="handling_precautions" placeholder="วิธีจัดการและข้อควรระวัง...">${esc(ghs.handling_precautions||'')}</textarea>
        </div>
        <div class="cm-field-row">
            <div class="cm-field">
                <label>วิธีเก็บรักษา (Storage)</label>
                <textarea name="storage_instructions" placeholder="อุณหภูมิ, แสง, ความชื้น...">${esc(ghs.storage_instructions||'')}</textarea>
            </div>
            <div class="cm-field">
                <label>การกำจัด (Disposal)</label>
                <textarea name="disposal_instructions" placeholder="วิธีกำจัดที่ถูกต้อง...">${esc(ghs.disposal_instructions||'')}</textarea>
            </div>
        </div>

        <!-- First Aid -->
        <div style="margin-bottom:16px;padding-top:12px;border-top:1px solid #f0f0f0">
            <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:10px"><i class="fas fa-first-aid" style="color:#ef4444"></i> First Aid Measures</label>
            <div class="cm-field-row">
                <div class="cm-field"><label>🌬️ Inhalation</label><textarea name="first_aid_inhalation" rows="2">${esc(ghs.first_aid_inhalation||'')}</textarea></div>
                <div class="cm-field"><label>🖐️ Skin Contact</label><textarea name="first_aid_skin" rows="2">${esc(ghs.first_aid_skin||'')}</textarea></div>
            </div>
            <div class="cm-field-row">
                <div class="cm-field"><label>👁️ Eye Contact</label><textarea name="first_aid_eye" rows="2">${esc(ghs.first_aid_eye||'')}</textarea></div>
                <div class="cm-field"><label>🍽️ Ingestion</label><textarea name="first_aid_ingestion" rows="2">${esc(ghs.first_aid_ingestion||'')}</textarea></div>
            </div>
        </div>

        <!-- Transport / Other -->
        <div style="padding-top:12px;border-top:1px solid #f0f0f0">
            <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:10px"><i class="fas fa-truck" style="color:#6366f1"></i> Transport / Toxicology</label>
            <div class="cm-field-row">
                <div class="cm-field"><label>UN Number</label><input type="text" name="un_number" value="${esc(ghs.un_number||'')}" placeholder="e.g. UN1230"></div>
                <div class="cm-field"><label>Packing Group</label><input type="text" name="packing_group" value="${esc(ghs.packing_group||'')}" placeholder="I / II / III"></div>
            </div>
            <div class="cm-field-row">
                <div class="cm-field"><label>LD50</label><input type="text" name="ld50" value="${esc(ghs.ld50||'')}" placeholder="e.g. 100 mg/kg (oral, rat)"></div>
                <div class="cm-field"><label>LC50</label><input type="text" name="lc50" value="${esc(ghs.lc50||'')}" placeholder="e.g. 3000 ppm (inhalation, rat)"></div>
            </div>
            <div class="cm-field"><label>Exposure Limits</label><textarea name="exposure_limits" rows="2">${esc(ghs.exposure_limits||'')}</textarea></div>
            <div class="cm-field"><label>Source / แหล่งข้อมูล</label><input type="text" name="source" value="${esc(ghs.source||'')}" placeholder="เช่น PubChem, Merck SDS"></div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid #f0f0f0">
            <button type="button" onclick="closeGhs()" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
            <button type="submit" class="ci-btn ci-btn-primary ci-btn-sm" id="ghsSubmitBtn"><i class="fas fa-save"></i> บันทึก GHS</button>
        </div>
        </form>`;

        document.getElementById('ghsBody').innerHTML = html;
    } catch(e) { document.getElementById('ghsBody').innerHTML = `<div style="padding:20px;color:red">${e.message}</div>`; }
}

function toggleGhsPic(el) {
    el.classList.toggle('active');
}

async function saveGhs(e, chemId) {
    e.preventDefault();
    const btn = document.getElementById('ghsSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    const form = document.getElementById('ghsForm');
    const fd = Object.fromEntries(new FormData(form));

    // Collect selected pictograms
    fd.ghs_pictograms = [...document.querySelectorAll('#ghsPicGrid .cm-ghs-sym.active')].map(el => el.dataset.code);
    fd.chemical_id = chemId;

    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=ghs_save', {method:'POST', body:JSON.stringify(fd)});
        if (d.success) {
            closeGhs();
            loadStats();
            // Show success toast
            showToast('บันทึกข้อมูล GHS สำเร็จ', 'success');
        } else {
            throw new Error(d.error);
        }
    } catch(err) {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> บันทึก GHS';
        showToast(err.message, 'error');
    }
}

// ═══════ Upload File ═══════
function showUpload(chemId) {
    closeDetail();
    // Default dates: today → +5 years
    const today = new Date().toISOString().split('T')[0];
    const plus5 = new Date(new Date().setFullYear(new Date().getFullYear()+5)).toISOString().split('T')[0];

    document.getElementById('uploadBody').innerHTML = `
        <form id="uploadForm" onsubmit="submitUpload(event,${chemId})" enctype="multipart/form-data">
            <!-- Type & Language -->
            <div class="cm-field-row">
                <div class="cm-field">
                    <label>ประเภทเอกสาร</label>
                    <select name="file_type">
                        <option value="sds">SDS (Safety Data Sheet)</option>
                        <option value="datasheet">Datasheet</option>
                        <option value="msds">MSDS</option>
                        <option value="certificate">Certificate</option>
                        <option value="other">อื่นๆ</option>
                    </select>
                </div>
                <div class="cm-field">
                    <label>ภาษา</label>
                    <select name="language">
                        <option value="en">English</option>
                        <option value="th">ไทย</option>
                    </select>
                </div>
            </div>
            <div class="cm-field">
                <label>ชื่อเอกสาร</label>
                <input type="text" name="title" placeholder="เช่น Safety Data Sheet - English">
            </div>
            <div class="cm-field">
                <label>คำอธิบาย</label>
                <textarea name="description" rows="2" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
            </div>

            <!-- ═══ Validity Dates ═══ -->
            <div style="background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1.5px solid #bfdbfe;border-radius:10px;padding:14px;margin-bottom:16px">
                <div style="font-size:12px;font-weight:700;color:#1d4ed8;margin-bottom:10px;display:flex;align-items:center;gap:6px">
                    <i class="fas fa-shield-alt"></i> ระยะเวลามีผลของเอกสาร
                    <span style="font-size:10px;font-weight:400;color:#6b7280">(SDS มาตรฐาน ISO 11014 อายุ 5 ปี)</span>
                </div>
                <div class="cm-field-row" style="margin-bottom:0">
                    <div class="cm-field" style="margin-bottom:0">
                        <label style="color:#374151"><i class="fas fa-play-circle" style="color:#22c55e;margin-right:3px"></i> วันที่เริ่มต้น (Valid From)</label>
                        <input type="date" name="valid_from" id="validFromInput" value="${today}"
                               onchange="autoFillValidUntil(this)">
                        <div class="hint">วันที่เอกสารเริ่มมีผลบังคับใช้</div>
                    </div>
                    <div class="cm-field" style="margin-bottom:0">
                        <label style="color:#374151"><i class="fas fa-flag-checkered" style="color:#ef4444;margin-right:3px"></i> วันหมดอายุ (Valid Until)</label>
                        <input type="date" name="valid_until" id="validUntilInput" value="${plus5}">
                        <div class="hint">หากไม่กรอก ระบบจะตั้งค่า 5 ปีโดยอัตโนมัติ</div>
                    </div>
                </div>
                <div style="margin-top:8px;padding:6px 10px;background:rgba(59,130,246,.06);border-radius:6px;font-size:10px;color:#6b7280;display:flex;align-items:center;gap:5px">
                    <i class="fas fa-clock" style="color:#3b82f6"></i>
                    <span id="validityPreview">ระยะเวลา: <strong>5 ปี 0 เดือน</strong> นับจากวันนี้</span>
                </div>
            </div>

            <!-- Tab: Upload or URL -->
            <div style="margin-bottom:16px">
              <div class="cm-tabs">
                <button type="button" class="cm-tab active" id="tabFileBtn" onclick="switchUploadTab('file')">
                    <i class="fas fa-upload"></i> อัปโหลดไฟล์
                </button>
                <button type="button" class="cm-tab" id="tabUrlBtn" onclick="switchUploadTab('url')">
                    <i class="fas fa-link"></i> ลิงก์ URL
                </button>
              </div>
            </div>

            <div id="uploadFileSection">
                <div class="cm-dropzone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt" style="display:block"></i>
                    <p>คลิกหรือลากไฟล์มาวางที่นี่</p>
                    <p class="hint">PDF, DOC, XLS, Image — สูงสุด 20 MB</p>
                    <input type="file" id="fileInput" name="file" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.csv,.txt" onchange="updateDropzone(this)">
                </div>
                <div id="filePreview" style="display:none;margin-top:8px;padding:10px;background:#f8fafc;border-radius:8px;font-size:13px"></div>
            </div>
            <div id="uploadUrlSection" style="display:none">
                <div class="cm-field">
                    <label>URL ลิงก์เอกสาร</label>
                    <input type="url" name="file_url" placeholder="https://example.com/sds.pdf">
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid #f0f0f0">
                <button type="button" onclick="closeUpload()" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
                <button type="submit" class="ci-btn ci-btn-primary ci-btn-sm" id="uploadSubmitBtn"><i class="fas fa-cloud-upload-alt"></i> อัปโหลด</button>
            </div>
        </form>`;

    // Setup drag & drop
    const dz = document.getElementById('dropZone');
    ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
    dz.addEventListener('drop', e => { document.getElementById('fileInput').files = e.dataTransfer.files; updateDropzone(document.getElementById('fileInput')); });

    // Initialize validity preview
    updateValidityPreview();
    document.getElementById('validUntilInput').addEventListener('change', updateValidityPreview);
    document.getElementById('validFromInput').addEventListener('change', updateValidityPreview);

    document.getElementById('uploadModal').classList.add('show');
}

function autoFillValidUntil(fromInput) {
    // Auto-set valid_until = valid_from + 5 years if user hasn't manually changed it
    const from = new Date(fromInput.value);
    if (!from || isNaN(from)) return;
    const until = new Date(from);
    until.setFullYear(until.getFullYear() + 5);
    const untilInput = document.getElementById('validUntilInput');
    if (untilInput) untilInput.value = until.toISOString().split('T')[0];
    updateValidityPreview();
}

function updateValidityPreview() {
    const fromInput = document.getElementById('validFromInput');
    const untilInput = document.getElementById('validUntilInput');
    const preview = document.getElementById('validityPreview');
    if (!fromInput || !untilInput || !preview) return;
    const from = new Date(fromInput.value);
    const until = new Date(untilInput.value);
    if (!from || !until || isNaN(from) || isNaN(until)) return;
    const diffMs = until - from;
    if (diffMs <= 0) {
        preview.innerHTML = '<span style="color:#dc2626"><i class="fas fa-exclamation-circle"></i> วันหมดอายุต้องมากกว่าวันเริ่มต้น</span>';
        return;
    }
    const diffDays = Math.round(diffMs / 86400000);
    const years = Math.floor(diffDays / 365);
    const months = Math.floor((diffDays % 365) / 30);
    const now = new Date(); now.setHours(0,0,0,0);
    const daysLeft = Math.round((until - now) / 86400000);
    let color = daysLeft < 0 ? '#dc2626' : daysLeft <= 90 ? '#d97706' : '#15803d';
    preview.innerHTML = `ระยะเวลา: <strong>${years > 0 ? years + ' ปี ' : ''}${months > 0 ? months + ' เดือน' : ''} (${diffDays} วัน)</strong> · <span style="color:${color};font-weight:600">${daysLeft < 0 ? 'หมดอายุแล้ว' : 'เหลือ ' + daysLeft + ' วัน'}</span>`;
}

function switchUploadTab(tab) {
    document.getElementById('tabFileBtn').classList.toggle('active', tab==='file');
    document.getElementById('tabUrlBtn').classList.toggle('active', tab==='url');
    document.getElementById('uploadFileSection').style.display = tab==='file'?'block':'none';
    document.getElementById('uploadUrlSection').style.display = tab==='url'?'block':'none';
}

function updateDropzone(input) {
    const preview = document.getElementById('filePreview');
    if (input.files.length) {
        const f = input.files[0];
        preview.style.display = 'flex';
        preview.style.alignItems = 'center';
        preview.style.gap = '10px';
        preview.innerHTML = `<i class="fas fa-file" style="color:#3b82f6;font-size:18px"></i>
            <div style="flex:1"><strong>${esc(f.name)}</strong> <span style="color:#aaa">(${formatBytes(f.size)})</span></div>
            <button type="button" onclick="document.getElementById('fileInput').value='';document.getElementById('filePreview').style.display='none'" style="background:none;border:none;color:#ef4444;cursor:pointer"><i class="fas fa-times"></i></button>`;
        // Auto-fill title if empty
        const titleInput = document.querySelector('#uploadForm input[name="title"]');
        if (!titleInput.value) titleInput.value = f.name;
    } else {
        preview.style.display = 'none';
    }
}

async function submitUpload(e, chemId) {
    e.preventDefault();
    const btn = document.getElementById('uploadSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังอัปโหลด...';

    const form = document.getElementById('uploadForm');
    const urlInput = form.querySelector('input[name="file_url"]');
    const fileInput = document.getElementById('fileInput');

    // Collect validity dates
    const validFrom  = form.querySelector('input[name="valid_from"]')?.value  || '';
    const validUntil = form.querySelector('input[name="valid_until"]')?.value || '';

    try {
        if (urlInput && urlInput.value) {
            // URL mode
            const body = {
                chemical_id: chemId,
                file_url: urlInput.value,
                file_type: form.querySelector('select[name="file_type"]').value,
                title: form.querySelector('input[name="title"]').value || 'External Link',
                description: form.querySelector('textarea[name="description"]').value,
                language: form.querySelector('select[name="language"]').value,
                valid_from: validFrom,
                valid_until: validUntil,
            };
            const d = await apiFetch('/v1/api/chemicals.php?action=add_file_url', {method:'POST', body:JSON.stringify(body)});
            if (!d.success) throw new Error(d.error);
        } else if (fileInput && fileInput.files.length) {
            // File upload mode
            const fd = new FormData(form);
            fd.append('chemical_id', chemId);
            // Ensure date fields are included even from named inputs
            if (validFrom)  fd.set('valid_from',  validFrom);
            if (validUntil) fd.set('valid_until', validUntil);
            const token = document.cookie.split('; ').find(c=>c.startsWith('auth_token='))?.split('=')[1];
            const resp = await fetch('/v1/api/chemicals.php?action=upload_file', {
                method: 'POST',
                headers: token ? {'Authorization':'Bearer '+token} : {},
                body: fd
            });
            const d = await resp.json();
            if (!d.success) throw new Error(d.error);
        } else {
            throw new Error('กรุณาเลือกไฟล์หรือใส่ URL');
        }

        closeUpload();
        loadStats();
        showToast('อัปโหลดเอกสารสำเร็จ', 'success');
    } catch(err) {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> อัปโหลด';
        showToast(err.message, 'error');
    }
}

async function deleteFile(fileId, chemId) {
    if (!confirm('ต้องการลบเอกสารนี้?')) return;
    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=file&id='+fileId, {method:'DELETE'});
        if (d.success) {
            showToast('ลบเอกสารสำเร็จ', 'success');
            showDetail(chemId); // Refresh
        } else throw new Error(d.error);
    } catch(e) { showToast(e.message, 'error'); }
}

// ═══════ Edit File ═══════
function showEditFile(fileId, chemId) {
    const f = (window._sdsFilesCache || {})[fileId];
    if (!f) { showToast('ไม่พบข้อมูลไฟล์', 'error'); return; }

    const issueVal  = f.issue_date  ? f.issue_date.split('T')[0]  : new Date().toISOString().split('T')[0];
    const expiryVal = f.expiry_date ? f.expiry_date.split('T')[0] :
        new Date(new Date().setFullYear(new Date().getFullYear()+5)).toISOString().split('T')[0];

    const typeOpts  = ['sds','datasheet','msds','certificate','other'];
    const langOpts  = [{v:'en',l:'English'},{v:'th',l:'ไทย'}];

    document.getElementById('editFileBody').innerHTML = `
    <form id="editFileForm" onsubmit="submitEditFile(event,${fileId},${chemId})">
        <div class="cm-field-row">
            <div class="cm-field">
                <label>ประเภทเอกสาร</label>
                <select name="file_type">
                    ${typeOpts.map(t=>`<option value="${t}" ${f.file_type===t?'selected':''}>${t.toUpperCase()}</option>`).join('')}
                </select>
            </div>
            <div class="cm-field">
                <label>ภาษา</label>
                <select name="language">
                    ${langOpts.map(o=>`<option value="${o.v}" ${f.language===o.v?'selected':''}>${o.l}</option>`).join('')}
                </select>
            </div>
        </div>
        <div class="cm-field">
            <label>ชื่อเอกสาร <span class="req">*</span></label>
            <input type="text" name="title" required value="${esc(f.title||'')}">
        </div>
        <div class="cm-field">
            <label>คำอธิบาย</label>
            <textarea name="description" rows="2">${esc(f.description||'')}</textarea>
        </div>

        <!-- Validity Dates -->
        <div style="background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1.5px solid #bfdbfe;border-radius:10px;padding:14px;margin-bottom:16px">
            <div style="font-size:12px;font-weight:700;color:#1d4ed8;margin-bottom:10px;display:flex;align-items:center;gap:6px">
                <i class="fas fa-shield-alt"></i> ระยะเวลามีผลของเอกสาร
                <span style="font-size:10px;font-weight:400;color:#6b7280">(SDS มาตรฐาน ISO 11014 อายุ 5 ปี)</span>
            </div>
            <div class="cm-field-row" style="margin-bottom:0">
                <div class="cm-field" style="margin-bottom:0">
                    <label style="color:#374151"><i class="fas fa-play-circle" style="color:#22c55e;margin-right:3px"></i> วันที่เริ่มต้น</label>
                    <input type="date" name="valid_from" id="editValidFrom" value="${issueVal}" onchange="autoFillEditValidUntil(this)">
                </div>
                <div class="cm-field" style="margin-bottom:0">
                    <label style="color:#374151"><i class="fas fa-flag-checkered" style="color:#ef4444;margin-right:3px"></i> วันหมดอายุ</label>
                    <input type="date" name="valid_until" id="editValidUntil" value="${expiryVal}">
                </div>
            </div>
            <div style="margin-top:8px;padding:6px 10px;background:rgba(59,130,246,.06);border-radius:6px;font-size:10px;color:#6b7280;display:flex;align-items:center;gap:5px">
                <i class="fas fa-clock" style="color:#3b82f6"></i>
                <span id="editValidityPreview"></span>
            </div>
        </div>

        ${f.file_url !== null ? `
        <div class="cm-field">
            <label><i class="fas fa-link" style="color:#3b82f6;margin-right:4px"></i> URL ลิงก์เอกสาร</label>
            <input type="url" name="file_url" value="${esc(f.file_url||'')}" placeholder="https://example.com/sds.pdf">
        </div>` : ''}

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0">
            <button type="button" onclick="closeEditFile()" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
            <button type="submit" class="ci-btn ci-btn-primary ci-btn-sm" id="editFileSubmitBtn">
                <i class="fas fa-save"></i> บันทึก
            </button>
        </div>
    </form>`;

    updateEditValidityPreview();
    document.getElementById('editValidUntil').addEventListener('change', updateEditValidityPreview);
    document.getElementById('editValidFrom').addEventListener('change', updateEditValidityPreview);
    document.getElementById('editFileModal').classList.add('show');
}

function closeEditFile() {
    document.getElementById('editFileModal').classList.remove('show');
}

function autoFillEditValidUntil(fromInput) {
    const from = new Date(fromInput.value);
    if (!from || isNaN(from)) return;
    const until = new Date(from);
    until.setFullYear(until.getFullYear() + 5);
    const untilInput = document.getElementById('editValidUntil');
    if (untilInput) untilInput.value = until.toISOString().split('T')[0];
    updateEditValidityPreview();
}

function updateEditValidityPreview() {
    const fromInput  = document.getElementById('editValidFrom');
    const untilInput = document.getElementById('editValidUntil');
    const preview    = document.getElementById('editValidityPreview');
    if (!fromInput || !untilInput || !preview) return;
    const from  = new Date(fromInput.value);
    const until = new Date(untilInput.value);
    if (isNaN(from) || isNaN(until)) return;
    const diffMs = until - from;
    if (diffMs <= 0) {
        preview.innerHTML = '<span style="color:#dc2626"><i class="fas fa-exclamation-circle"></i> วันหมดอายุต้องมากกว่าวันเริ่มต้น</span>';
        return;
    }
    const diffDays = Math.round(diffMs / 86400000);
    const years  = Math.floor(diffDays / 365);
    const months = Math.floor((diffDays % 365) / 30);
    const now      = new Date(); now.setHours(0,0,0,0);
    const daysLeft = Math.round((until - now) / 86400000);
    const color    = daysLeft < 0 ? '#dc2626' : daysLeft <= 90 ? '#d97706' : '#15803d';
    preview.innerHTML = `ระยะเวลา: <strong>${years > 0 ? years+' ปี ' : ''}${months > 0 ? months+' เดือน' : ''} (${diffDays} วัน)</strong> · <span style="color:${color};font-weight:600">${daysLeft < 0 ? 'หมดอายุแล้ว' : 'เหลือ '+daysLeft+' วัน'}</span>`;
}

async function submitEditFile(e, fileId, chemId) {
    e.preventDefault();
    const btn = document.getElementById('editFileSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    const form = document.getElementById('editFileForm');
    const fd   = Object.fromEntries(new FormData(form));

    // Build PUT payload — map form names → DB column names
    const payload = {
        id:          fileId,
        title:       fd.title,
        description: fd.description || null,
        file_type:   fd.file_type,
        language:    fd.language,
        issue_date:  fd.valid_from  || null,
        expiry_date: fd.valid_until || null,
    };
    if (fd.file_url !== undefined) payload.file_url = fd.file_url || null;

    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=file&id=' + fileId, {
            method: 'PUT',
            body: JSON.stringify(payload)
        });
        if (d.success) {
            closeEditFile();
            showToast('แก้ไขข้อมูลเอกสารสำเร็จ', 'success');
            showDetail(chemId);
        } else throw new Error(d.error);
    } catch(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> บันทึก';
        showToast(err.message, 'error');
    }
}

// ═══════ Add Chemical ═══════
function showAddChemical() {
    document.getElementById('addBody').innerHTML = `
    <form id="addChemForm" onsubmit="submitAddChem(event)">
        <div class="cm-field"><label>ชื่อสาร <span class="req">*</span></label><input type="text" name="name" required placeholder="ชื่อสารเคมี"></div>
        <div class="cm-field-row">
            <div class="cm-field"><label>CAS Number</label><input type="text" name="cas_number" placeholder="เช่น 7647-01-0"></div>
            <div class="cm-field"><label>Catalogue No.</label><input type="text" name="catalogue_number" placeholder="เช่น 100317"></div>
        </div>
        <div class="cm-field-row">
            <div class="cm-field"><label>สถานะ</label>
                <select name="physical_state"><option value="solid">Solid</option><option value="liquid">Liquid</option><option value="gas">Gas</option></select>
            </div>
            <div class="cm-field"><label>ชนิดสาร</label>
                <select name="substance_type"><option value="HomogeneousSubstance">Homogeneous</option><option value="HeterogenousSubstance">Heterogeneous</option></select>
            </div>
        </div>
        <div class="cm-field"><label>ประเภทสาร</label><input type="text" name="substance_category" placeholder="เช่น Reagent, Buffer solution"></div>
        <div class="cm-field-row">
            <div class="cm-field"><label>สูตรโมเลกุล</label><input type="text" name="molecular_formula" placeholder="เช่น H2SO4"></div>
            <div class="cm-field"><label>น้ำหนักโมเลกุล</label><input type="number" name="molecular_weight" step="any" placeholder="g/mol"></div>
        </div>
        <div class="cm-field"><label>คำอธิบาย</label><textarea name="description" rows="2" placeholder="รายละเอียดเพิ่มเติม"></textarea></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0">
            <button type="button" onclick="closeAdd()" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
            <button type="submit" class="ci-btn ci-btn-primary ci-btn-sm" id="addChemBtn"><i class="fas fa-plus"></i> เพิ่มสาร</button>
        </div>
    </form>`;
    document.getElementById('addModal').classList.add('show');
    setTimeout(() => document.querySelector('#addChemForm input[name="name"]').focus(), 100);
}

async function submitAddChem(e) {
    e.preventDefault();
    const btn = document.getElementById('addChemBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    const data = Object.fromEntries(new FormData(document.getElementById('addChemForm')));
    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=create', {method:'POST', body:JSON.stringify(data)});
        if (d.success) { closeAdd(); loadChemicals(); loadStats(); showToast('เพิ่มสารเคมีสำเร็จ','success'); }
        else throw new Error(d.error);
    } catch(err) { btn.disabled=false; btn.innerHTML='<i class="fas fa-plus"></i> เพิ่มสาร'; showToast(err.message,'error'); }
}

// ═══════ Edit Chemical ═══════
function editChemical(id) {
    const c = currentDetail;
    if (!c) return;
    closeDetail();
    document.getElementById('addBody').innerHTML = `
    <form id="editChemForm" onsubmit="submitEditChem(event,${id})">
        <div class="cm-field"><label>ชื่อสาร <span class="req">*</span></label><input type="text" name="name" required value="${esc(c.name)}"></div>
        <div class="cm-field-row">
            <div class="cm-field"><label>CAS Number</label><input type="text" name="cas_number" value="${esc(c.cas_number||'')}"></div>
            <div class="cm-field"><label>Catalogue No.</label><input type="text" name="catalogue_number" value="${esc(c.catalogue_number||'')}"></div>
        </div>
        <div class="cm-field-row">
            <div class="cm-field"><label>สถานะ</label>
                <select name="physical_state"><option value="solid" ${c.physical_state==='solid'?'selected':''}>Solid</option><option value="liquid" ${c.physical_state==='liquid'?'selected':''}>Liquid</option><option value="gas" ${c.physical_state==='gas'?'selected':''}>Gas</option></select>
            </div>
            <div class="cm-field"><label>ชนิดสาร</label>
                <select name="substance_type"><option value="HomogeneousSubstance" ${c.substance_type==='HomogeneousSubstance'?'selected':''}>Homogeneous</option><option value="HeterogenousSubstance" ${c.substance_type==='HeterogenousSubstance'?'selected':''}>Heterogeneous</option></select>
            </div>
        </div>
        <div class="cm-field"><label>ประเภทสาร</label><input type="text" name="substance_category" value="${esc(c.substance_category||'')}"></div>
        <div class="cm-field"><label>IUPAC Name</label><input type="text" name="iupac_name" value="${esc(c.iupac_name||'')}"></div>
        <div class="cm-field-row">
            <div class="cm-field"><label>สูตรโมเลกุล</label><input type="text" name="molecular_formula" value="${esc(c.molecular_formula||'')}"></div>
            <div class="cm-field"><label>น้ำหนักโมเลกุล</label><input type="number" name="molecular_weight" step="any" value="${c.molecular_weight||''}"></div>
        </div>
        <div class="cm-field"><label>คำอธิบาย</label><textarea name="description" rows="2">${esc(c.description||'')}</textarea></div>
        <div class="cm-field-row">
            <div class="cm-field"><label>วิธีจัดเก็บ</label><textarea name="storage_requirements" rows="2">${esc(c.storage_requirements||'')}</textarea></div>
            <div class="cm-field"><label>วิธีจัดการ</label><textarea name="handling_procedures" rows="2">${esc(c.handling_procedures||'')}</textarea></div>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0">
            <button type="button" onclick="closeAdd()" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
            <button type="submit" class="ci-btn ci-btn-primary ci-btn-sm" id="editChemBtn"><i class="fas fa-save"></i> บันทึก</button>
        </div>
    </form>`;
    document.querySelector('#addModal .ci-modal-hdr h3').innerHTML = '<i class="fas fa-edit" style="color:var(--accent)"></i> แก้ไขสารเคมี';
    document.getElementById('addModal').classList.add('show');
}

async function submitEditChem(e, id) {
    e.preventDefault();
    const btn = document.getElementById('editChemBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    const data = Object.fromEntries(new FormData(document.getElementById('editChemForm')));
    data.id = id;
    try {
        const d = await apiFetch('/v1/api/chemicals.php?action=update', {method:'POST', body:JSON.stringify(data)});
        if (d.success) { closeAdd(); loadChemicals(); showToast('อัปเดตสำเร็จ','success'); }
        else throw new Error(d.error);
    } catch(err) { btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> บันทึก'; showToast(err.message,'error'); }
}

// ═══════ Sorting ═══════
function doSort(field) {
    if (thDragHappened) { thDragHappened = false; return; }
    if (sortField === field) {
        sortDir = sortDir === 'ASC' ? 'DESC' : 'ASC';
    } else {
        sortField = field;
        sortDir = 'ASC';
    }
    localStorage.setItem('chemSortField', sortField);
    localStorage.setItem('chemSortDir', sortDir);
    page = 1;
    loadChemicals();
}

// ═══════ Column Visibility ═══════
function toggleColPanel(e) {
    e.stopPropagation();
    const panel = document.getElementById('colPanel');
    panel.classList.toggle('show');
    if (panel.classList.contains('show')) {
        // Close on outside click
        setTimeout(() => {
            document.addEventListener('click', closeColPanelOutside);
        }, 10);
    }
}

function closeColPanelOutside(e) {
    const panel = document.getElementById('colPanel');
    if (panel && !panel.contains(e.target) && !e.target.closest('.cm-col-btn')) {
        panel.classList.remove('show');
        document.removeEventListener('click', closeColPanelOutside);
    }
}

function toggleColumn(key, visible) {
    const col = tableColumns.find(c => c.key === key);
    if (col) {
        col.visible = visible;
        saveColumns();
        render();
    }
}

function resetColumns() {
    tableColumns = DEFAULT_COLUMNS.map(c => ({...c}));
    saveColumns();
    render();
}

function saveColumns() {
    localStorage.setItem('chemColumns', JSON.stringify(tableColumns));
}

function changePerPage(val) {
    perPage = parseInt(val);
    localStorage.setItem('chemPerPage', perPage);
    page = 1;
    loadChemicals();
}

// ═══════ Column Drag & Drop (Panel) ═══════
let colDragIdx = null;

function colDragStart(e, idx) {
    colDragIdx = idx;
    e.dataTransfer.effectAllowed = 'move';
    e.target.classList.add('dragging');
}

function colDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function colDragEnter(e, el) {
    e.preventDefault();
    el.classList.add('drag-over');
}

function colDragLeave(e, el) {
    el.classList.remove('drag-over');
}

function colDrop(e, dropIdx) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    if (colDragIdx === null || colDragIdx === dropIdx) return;
    const [moved] = tableColumns.splice(colDragIdx, 1);
    tableColumns.splice(dropIdx, 0, moved);
    colDragIdx = null;
    saveColumns();
    render();
}

function colDragEnd(e) {
    e.target.classList.remove('dragging');
    document.querySelectorAll('.cm-col-item').forEach(el => el.classList.remove('drag-over'));
    colDragIdx = null;
}

// ═══════ Header Drag & Drop (Table TH reorder) ═══════
let thDragHappened = false;

function initHeaderDragDrop() {
    const ths = document.querySelectorAll('#chemTableHead th');
    let dragThIdx = null;

    ths.forEach((th, i) => {
        th.setAttribute('draggable', 'true');

        th.addEventListener('dragstart', e => {
            dragThIdx = i;
            thDragHappened = false;
            e.dataTransfer.effectAllowed = 'move';
            th.classList.add('th-dragging');
        });

        th.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        th.addEventListener('dragenter', e => {
            e.preventDefault();
            th.classList.add('th-drag-over');
        });

        th.addEventListener('dragleave', () => {
            th.classList.remove('th-drag-over');
        });

        th.addEventListener('drop', e => {
            e.preventDefault();
            th.classList.remove('th-drag-over');
            if (dragThIdx === null || dragThIdx === i) return;
            thDragHappened = true;

            // Map visible index to full array index
            const visibleKeys = tableColumns.filter(c => c.visible).map(c => c.key);
            const fromKey = visibleKeys[dragThIdx];
            const toKey = visibleKeys[i];
            const fromFullIdx = tableColumns.findIndex(c => c.key === fromKey);
            const toFullIdx = tableColumns.findIndex(c => c.key === toKey);

            const [moved] = tableColumns.splice(fromFullIdx, 1);
            tableColumns.splice(toFullIdx, 0, moved);

            dragThIdx = null;
            saveColumns();
            render();
        });

        th.addEventListener('dragend', () => {
            th.classList.remove('th-dragging');
            ths.forEach(t => t.classList.remove('th-drag-over'));
            dragThIdx = null;
        });
    });
}

// ═══════ View & Pagination ═══════
function setView(v, noReload) {
    currentView = v; localStorage.setItem('chemView', v);
    // Update all view toggle buttons (desktop + mobile)
    document.querySelectorAll('.cm-view-toggle button, .cm-vw-toggle button, .cm-view-toggle .ci-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.view===v || b.id===(v==='grid'?'btnGrid':'btnTable'));
    });
    if (!noReload) render();
}

function changePage(delta) {
    page = Math.max(1, Math.min(totalPages, page + delta));
    loadChemicals();
}

function goPage(p) { page = p; loadChemicals(); }

function renderPagination() {
    const el = document.getElementById('pagination');
    if (totalPages <= 1) { el.style.display = 'none'; return; }
    el.style.display = 'flex';
    document.getElementById('pgInfo').textContent = `แสดง ${(page-1)*perPage+1}-${Math.min(page*perPage,totalItems)} จาก ${num(totalItems)} รายการ`;
    document.getElementById('pgPrev').disabled = page <= 1;
    document.getElementById('pgNext').disabled = page >= totalPages;

    let nums = '';
    const range = 2;
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= page-range && i <= page+range)) {
            nums += `<button class="cm-pg-btn${i===page?' active':''}" onclick="goPage(${i})" style="min-width:32px;${i===page?'background:var(--accent,#1a8a5c);color:#fff;border-color:var(--accent,#1a8a5c)':''}">${i}</button>`;
        } else if (i === page-range-1 || i === page+range+1) {
            nums += '<span style="padding:4px;color:#aaa">...</span>';
        }
    }
    document.getElementById('pgNumbers').innerHTML = nums;
}

function clearStatFilter() {
    activeStatFilter = null;
    document.querySelectorAll('.cm-stat-card').forEach(c => c.classList.remove('active'));
}

// ═══════ Search ═══════
function setupSearch() {
    let timer;
    const reload = () => { clearTimeout(timer); timer = setTimeout(() => { clearStatFilter(); page=1; loadChemicals(); }, 300); };
    document.getElementById('searchInput').addEventListener('input', reload);
    // Sync mobile search ↔ desktop search
    const mobInp = document.getElementById('searchInputMob');
    if (mobInp) mobInp.addEventListener('input', () => {
        document.getElementById('searchInput').value = mobInp.value;
        reload();
    });
    document.getElementById('searchInput').addEventListener('input', () => {
        if (mobInp) mobInp.value = document.getElementById('searchInput').value;
    });
    document.getElementById('fState').addEventListener('change', () => { clearStatFilter(); page=1; loadChemicals(); });
    document.getElementById('fType').addEventListener('change', () => { clearStatFilter(); page=1; loadChemicals(); });
}

// ═══════ Tab Switching ═══════
function switchTab(el, tabId) {
    el.closest('.cm-tabs').querySelectorAll('.cm-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    el.closest('.ci-modal-body').querySelectorAll('.cm-tab-body').forEach(b => b.style.display='none');
    document.getElementById(tabId).style.display = 'block';
}

// ═══════ Modal Closers ═══════
function closeDetail() { document.getElementById('detailModal').classList.remove('show'); currentDetail = null; }
function closeGhs() { document.getElementById('ghsModal').classList.remove('show'); }
function closeUpload() { document.getElementById('uploadModal').classList.remove('show'); }
function closeAdd() {
    document.getElementById('addModal').classList.remove('show');
    document.querySelector('#addModal .ci-modal-hdr h3').innerHTML = '<i class="fas fa-plus-circle" style="color:#3b82f6"></i> เพิ่มสารเคมีใหม่';
}

// Backdrop close
['detailModal','ghsModal','uploadModal','addModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', e => { if(e.target===e.currentTarget) { if(id==='detailModal')closeDetail(); else if(id==='ghsModal')closeGhs(); else if(id==='uploadModal')closeUpload(); else closeAdd(); }});
});

// ═══════ Helpers ═══════
function esc(s) { if(!s) return ''; const d=document.createElement('div'); d.textContent=String(s); return d.innerHTML; }
function num(n) { return Number(n||0).toLocaleString(); }
function truncate(s,n) { return s && s.length > n ? s.substr(0,n)+'…' : s; }
function parseJson(s) { try { return typeof s==='string' ? JSON.parse(s) : (s||[]); } catch(e) { return []; } }
function formatBytes(b) { if(!b) return ''; const u=['B','KB','MB','GB']; let i=0; while(b>=1024&&i<3){b/=1024;i++} return b.toFixed(i?1:0)+' '+u[i]; }

function formatDate(d) { if(!d) return '—'; try { const dt=new Date(d); return dt.toLocaleDateString('th-TH',{day:'2-digit',month:'short',year:'numeric'}); } catch(e) { return d; } }

function statePill(s) {
    const m = {solid:['#7c3aed','#ede9fe','Solid'],liquid:['#2563eb','#dbeafe','Liquid'],gas:['#d97706','#fef3c7','Gas']};
    const [c,bg,l] = m[s] || ['#6b7280','#f3f4f6',s||'—'];
    return `<span style="background:${bg};color:${c};padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600">${l}</span>`;
}

function infoItem(label, value, fontClass) {
    return `<div class="cm-info-item"><div class="cm-info-label">${label}</div><div class="cm-info-value" ${fontClass?`style="font-family:${fontClass}"`:''}>${value||'—'}</div></div>`;
}

function emptyState(icon, text) {
    return `<div class="ci-card ci-card-body" style="text-align:center;padding:40px;color:#999"><i class="fas ${icon}" style="font-size:36px;margin-bottom:12px;display:block;opacity:.3"></i><p>${text}</p></div>`;
}

function showToast(msg, type) {
    const existing = document.querySelector('.cm-toast');
    if (existing) existing.remove();
    const t = document.createElement('div');
    t.className = 'cm-toast';
    const bg = type==='success'?'#059669':type==='error'?'#dc2626':'#3b82f6';
    const icon = type==='success'?'fa-check-circle':type==='error'?'fa-exclamation-circle':'fa-info-circle';
    t.style.cssText = `position:fixed;bottom:24px;right:24px;background:${bg};color:#fff;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:500;z-index:9999;display:flex;align-items:center;gap:10px;box-shadow:0 4px 16px rgba(0,0,0,.15);animation:cmFadeIn .2s ease`;
    t.innerHTML = `<i class="fas ${icon}"></i> ${esc(msg)}`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}

// ═══════ Molecule Formula Visual Renderer ═══════
const ELEMENT_COLORS = {C:'#374151',H:'#9ca3af',O:'#dc2626',N:'#2563eb',S:'#ca8a04',Cl:'#16a34a',F:'#0891b2',Br:'#9f1239',I:'#7c3aed',P:'#ea580c',Na:'#7c3aed',K:'#7c3aed',Fe:'#b45309',Cu:'#b45309',Zn:'#6b7280',Si:'#6366f1',B:'#e11d48',Li:'#dc2626',Mg:'#059669',Ca:'#059669',Al:'#9ca3af',Mn:'#b45309',Cr:'#16a34a',Co:'#2563eb',Ni:'#6b7280',Ti:'#6366f1',Ag:'#9ca3af',Au:'#ca8a04',Hg:'#6b7280',Pb:'#64748b',Sn:'#6b7280',As:'#7c3aed',Se:'#ca8a04',Ba:'#059669',Sr:'#059669',La:'#6366f1',Ce:'#6366f1'};
const ELEMENT_NAMES = {C:'Carbon',H:'Hydrogen',O:'Oxygen',N:'Nitrogen',S:'Sulfur',Cl:'Chlorine',F:'Fluorine',Br:'Bromine',I:'Iodine',P:'Phosphorus',Na:'Sodium',K:'Potassium',Fe:'Iron',Cu:'Copper',Zn:'Zinc',Si:'Silicon',B:'Boron',Li:'Lithium',Mg:'Magnesium',Ca:'Calcium',Al:'Aluminium'};

function parseFormula(f) {
    const parts = [];
    // Match: uppercase letter + optional lowercase letters + optional digits
    const re = /([A-Z][a-z]?)(\d*)/g;
    let m;
    while ((m = re.exec(f)) !== null) {
        if (m[1]) parts.push({ el: m[1], n: parseInt(m[2]||'1') });
    }
    return parts;
}

function renderFormulaVisual(c) {
    const f = c.molecular_formula;
    const parts = parseFormula(f);
    if (!parts.length) return '';

    // Formatted formula with colored elements and subscripts
    let formulaHtml = parts.map(p => {
        const cls = ELEMENT_COLORS[p.el] ? 'el-'+p.el : 'el-default';
        return `<span class="${cls}">${p.el}</span>${p.n > 1 ? '<sub>'+p.n+'</sub>' : ''}`;
    }).join('');

    // Atom composition bar
    const totalAtoms = parts.reduce((s,p) => s + p.n, 0);
    let barHtml = parts.map(p => {
        const pct = (p.n / totalAtoms * 100);
        const col = ELEMENT_COLORS[p.el] || '#94a3b8';
        return `<div class="cm-atom-seg" style="flex:${p.n};background:${col}" title="${p.el}: ${p.n} atom${p.n>1?'s':''} (${pct.toFixed(0)}%)"></div>`;
    }).join('');

    // Legend
    let legendHtml = parts.map(p => {
        const col = ELEMENT_COLORS[p.el] || '#94a3b8';
        const name = ELEMENT_NAMES[p.el] || p.el;
        return `<span><span class="dot" style="background:${col}"></span>${p.el}${p.n>1?'×'+p.n:''}</span>`;
    }).join('');

    // MW
    const mwHtml = c.molecular_weight ? `<div class="cm-formula-mw">MW: ${c.molecular_weight} g/mol</div>` : '';

    // Optional PubChem image (try to load, hide on error)
    let pubchemHtml = '';
    if (c.image_url) {
        pubchemHtml = `<div class="cm-formula-pubchem"><img src="${esc(c.image_url)}" alt="" onerror="this.parentElement.style.display='none'" onclick="window.open(this.src.replace('300x300','900x900'),'_blank')" style="cursor:zoom-in" title="คลิกเพื่อดูภาพขนาดใหญ่"></div>`;
    }

    // 3D link
    const link3d = c.model_3d_url ? ` &nbsp;|&nbsp; <a href="${esc(c.model_3d_url)}" target="_blank" style="color:var(--accent)"><i class="fas fa-cube"></i> 3D Model</a>` : '';

    return `<div class="cm-formula-visual">
        <div class="cm-formula-display">
            <div class="cm-formula-text">${formulaHtml}</div>
            <div class="cm-atom-bar">${barHtml}</div>
            <div class="cm-atom-legend">${legendHtml}</div>
            ${mwHtml}
        </div>
        ${pubchemHtml}
        <div class="cm-structure-caption" style="margin-top:8px">
            <i class="fas fa-atom"></i> สูตรโมเลกุล${link3d}
        </div>
    </div>`;
}

function miniFormula(f) {
    const parts = parseFormula(f);
    if (!parts.length) return esc(f.substring(0,6));
    // Show compact formula with subscripts, max ~8 chars visual
    let html = '';
    for (const p of parts) {
        const col = ELEMENT_COLORS[p.el] || '#475569';
        html += `<span style="color:${col}">${p.el}</span>`;
        if (p.n > 1) html += `<sub style="font-size:8px">${p.n}</sub>`;
    }
    return html;
}

// ═══ AR Viewer keyboard & click-outside ═══
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('arViewerModal').classList.contains('show')) {
        closeArViewer();
        e.stopPropagation();
    }
});
document.getElementById('arViewerModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeArViewer();
});

init();
</script>
</body></html>
