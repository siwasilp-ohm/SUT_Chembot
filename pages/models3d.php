<?php
/**
 * 3D Model Management Page — STANDALONE
 * Self-contained model management, no VRX dependency
 * Features: Edit modal, CAS Number grouping, grid/group view
 */
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin = $roleLevel >= 5;
$isManager = $roleLevel >= 3;
if (!$isManager) { header('Location: /v1/'); exit; }
Layout::head('จัดการโมเดล 3D');
?>
<body>
<?php Layout::sidebar('models3d'); Layout::beginContent(); ?>
<?php Layout::pageHeader('จัดการโมเดล 3D บรรจุภัณฑ์', 'fas fa-cube', 'อัปโหลดและจัดการโมเดล 3D สำหรับแสดงผลบรรจุภัณฑ์สารเคมี'); ?>

<style>
/* ═══ Stats ═══ */
.m3d-stat{display:flex;align-items:center;gap:14px;padding:16px}
.m3d-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px}
.m3d-stat-num{font-size:22px;font-weight:700;color:#333}
.m3d-stat-lbl{font-size:12px;color:#999}
/* ═══ Tabs ═══ */
.m3d-tabs{display:flex;gap:2px;border-bottom:2px solid #f0f0f0;margin-bottom:20px}
.m3d-tab{padding:10px 18px;font-size:13px;font-weight:600;color:#888;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;display:flex;align-items:center;gap:6px}
.m3d-tab:hover{color:#333}
.m3d-tab.active{color:#6C5CE7;border-bottom-color:#6C5CE7}
.m3d-tab .badge{background:#eee;color:#888;font-size:10px;padding:1px 6px;border-radius:8px;font-weight:700}
.m3d-tab.active .badge{background:#ede9fe;color:#6C5CE7}
/* ═══ View Toggle ═══ */
.m3d-view-toggle{display:flex;gap:2px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden}
.m3d-view-btn{padding:6px 12px;font-size:12px;cursor:pointer;background:#fff;border:none;color:#888;transition:all .15s}
.m3d-view-btn:hover{background:#f8f8ff;color:#333}
.m3d-view-btn.active{background:#6C5CE7;color:#fff}
/* ═══ Model Cards ═══ */
.m3d-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.m3d-card{border:1.5px solid #e5e7eb;border-radius:14px;overflow:hidden;transition:all .15s;background:#fff;position:relative}
.m3d-card:hover{border-color:#6C5CE7;box-shadow:0 6px 20px rgba(108,92,231,.1)}
.m3d-card.is-default{border-color:#6C5CE7;background:#faf5ff}
.m3d-card-preview{width:100%;height:180px;background:#0f0f1a;position:relative;overflow:hidden;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:center}
.m3d-card-preview iframe{width:100%;height:100%;border:none}
.m3d-card-preview .no-preview{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#555;gap:8px}
.m3d-card-preview .no-preview i{font-size:40px;opacity:.3}
.m3d-card-body{padding:14px 16px}
.m3d-card-title{font-size:14px;font-weight:600;color:#333;margin-bottom:6px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.m3d-card-title .tag{font-size:9px;padding:1px 6px;border-radius:6px;font-weight:700}
.m3d-card-title .tag-default{background:#ede9fe;color:#6C5CE7}
.m3d-card-title .tag-ar{background:#d1fae5;color:#059669}
.m3d-card-meta{font-size:12px;color:#888;line-height:1.8}
.m3d-card-meta i{width:14px;text-align:center;color:#aaa;font-size:10px}
.m3d-card-actions{display:flex;gap:6px;margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f0;flex-wrap:wrap}
/* ═══ CAS Group ═══ */
.cas-group{margin-bottom:24px;border:1.5px solid #e5e7eb;border-radius:14px;overflow:hidden;background:#fff}
.cas-group-header{display:flex;align-items:center;gap:12px;padding:14px 18px;background:linear-gradient(135deg,#f8f9fa 0%,#f0f0ff 100%);border-bottom:1px solid #e5e7eb;cursor:pointer;transition:background .15s;user-select:none}
.cas-group-header:hover{background:linear-gradient(135deg,#eee 0%,#e8e0ff 100%)}
.cas-group-header .cas-badge{font-size:13px;font-weight:700;color:#6C5CE7;background:#ede9fe;padding:4px 14px;border-radius:8px;font-family:'Courier New',monospace;letter-spacing:.5px}
.cas-group-header .cas-name{font-size:13px;font-weight:600;color:#333;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cas-group-header .cas-count{font-size:11px;color:#fff;background:#6C5CE7;padding:2px 10px;border-radius:10px;font-weight:600}
.cas-group-header .cas-chevron{color:#aaa;transition:transform .2s;font-size:12px}
.cas-group-header.collapsed .cas-chevron{transform:rotate(-90deg)}
.cas-group-body{padding:16px}
.cas-group-body.collapsed{display:none}
.cas-ungrouped{margin-top:24px}
.cas-ungrouped-header{display:flex;align-items:center;gap:8px;margin-bottom:14px;padding:10px 16px;background:#f8f9fa;border-radius:10px;border:1px solid #e5e7eb}
.cas-ungrouped-header i{color:#999}
.cas-ungrouped-header span{font-size:14px;font-weight:600;color:#888}
/* ═══ Upload Area ═══ */
.m3d-upload-drop{border:2px dashed #d4d4d4;border-radius:14px;padding:40px 30px;text-align:center;cursor:pointer;transition:all .3s;background:#fafafa}
.m3d-upload-drop:hover,.m3d-upload-drop.dragover{border-color:#6C5CE7;background:#faf5ff}
.m3d-upload-drop i.drop-icon{font-size:40px;color:#ccc;margin-bottom:12px;display:block;transition:color .3s}
.m3d-upload-drop:hover i.drop-icon{color:#6C5CE7}
.m3d-upload-drop h3{font-size:15px;font-weight:600;color:#333;margin-bottom:4px}
.m3d-upload-drop p{font-size:12px;color:#999}
.m3d-upload-drop .ext-badges{display:flex;gap:4px;justify-content:center;margin-top:10px;flex-wrap:wrap}
.m3d-upload-drop .ext-badges span{font-size:10px;padding:2px 8px;border-radius:10px;background:#ede9fe;color:#6C5CE7;font-weight:700;text-transform:uppercase}
/* ═══ Upload Progress ═══ */
.m3d-upload-progress{margin-top:16px;padding:16px;background:#f8f9fa;border-radius:12px;border:1px solid #e5e7eb}
.m3d-progress-bar{height:6px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin:8px 0}
.m3d-progress-fill{height:100%;background:linear-gradient(90deg,#6C5CE7,#00CEC9);border-radius:4px;transition:width .3s;width:0}
.m3d-progress-text{display:flex;justify-content:space-between;font-size:12px;color:#888}
/* ═══ Upload Form ═══ */
.m3d-upload-form{margin-top:16px;padding:20px;background:#fff;border:1.5px solid #e5e7eb;border-radius:14px}
.m3d-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:640px){.m3d-form-grid{grid-template-columns:1fr}}
.m3d-form-field{margin-bottom:14px}
.m3d-form-field label{display:block;font-size:12px;font-weight:600;color:#888;margin-bottom:4px}
.m3d-form-field .req{color:#ef4444}
.m3d-form-field input,.m3d-form-field select,.m3d-form-field textarea{width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;color:#333;background:#fff}
.m3d-form-field input:focus,.m3d-form-field select:focus,.m3d-form-field textarea:focus{outline:none;border-color:#6C5CE7;box-shadow:0 0 0 3px rgba(108,92,231,.1)}
/* ═══ Chemical Search Autocomplete ═══ */
.chem-search-wrap{position:relative}
.chem-search-results{position:absolute;top:100%;left:0;right:0;z-index:50;background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;max-height:220px;overflow-y:auto;box-shadow:0 8px 20px rgba(0,0,0,.1);display:none}
.chem-search-results.show{display:block}
.chem-search-item{padding:8px 12px;cursor:pointer;font-size:12px;display:flex;align-items:center;gap:8px;transition:background .1s;border-bottom:1px solid #f8f8f8}
.chem-search-item:last-child{border-bottom:none}
.chem-search-item:hover{background:#f8f5ff}
.chem-search-item .cas{font-family:'Courier New',monospace;font-weight:700;color:#6C5CE7;font-size:11px;min-width:85px}
.chem-search-item .name{color:#333;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chem-selected{display:flex;align-items:center;gap:8px;padding:6px 10px;background:#ede9fe;border-radius:8px;margin-top:6px;font-size:12px}
.chem-selected .cas{font-family:'Courier New',monospace;font-weight:700;color:#6C5CE7}
.chem-selected .name{color:#333;flex:1}
.chem-selected .clear{cursor:pointer;color:#999;font-size:14px;padding:0 4px;transition:color .15s}
.chem-selected .clear:hover{color:#dc2626}
/* ═══ Edit Modal ═══ */
.m3d-modal-overlay{position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s}
.m3d-modal-overlay.show{opacity:1;pointer-events:all}
.m3d-modal{width:90vw;max-width:740px;max-height:90vh;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);display:flex;flex-direction:column}
.m3d-modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #f0f0f0}
.m3d-modal-header h3{font-size:16px;font-weight:700;color:#333;display:flex;align-items:center;gap:8px}
.m3d-modal-header h3 i{color:#6C5CE7}
.m3d-modal-close{width:32px;height:32px;border-radius:8px;border:none;background:#f0f0f0;cursor:pointer;font-size:14px;color:#999;display:flex;align-items:center;justify-content:center;transition:all .15s}
.m3d-modal-close:hover{background:#fee2e2;color:#dc2626}
.m3d-modal-body{padding:20px;overflow-y:auto;flex:1}
.m3d-modal-footer{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #f0f0f0;background:#fafafa}
/* ═══ Request Cards ═══ */
.req-card{border:1.5px solid #e5e7eb;border-radius:12px;padding:16px;transition:all .15s}
.req-card:hover{border-color:#f59e0b}
.req-status{font-size:10px;padding:2px 8px;border-radius:8px;font-weight:700;display:inline-block}
.req-pending{background:#fef3c7;color:#d97706}
.req-approved{background:#dbeafe;color:#2563eb}
.req-in_progress{background:#ede9fe;color:#6C5CE7}
.req-completed{background:#d1fae5;color:#059669}
.req-rejected{background:#fee2e2;color:#dc2626}
.req-priority{font-size:10px;padding:2px 6px;border-radius:6px;font-weight:700}
.req-priority.urgent{background:#fee2e2;color:#dc2626}
.req-priority.high{background:#fef3c7;color:#d97706}
.req-priority.normal{background:#eee;color:#888}
.req-priority.low{background:#f0f9ff;color:#0ea5e9}
/* ═══ 3D Preview Modal ═══ */
.m3d-preview-modal{position:fixed;inset:0;z-index:1100;background:rgba(0,0,0,.85);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s}
.m3d-preview-modal.show{opacity:1;pointer-events:all}
.m3d-preview-container{width:90vw;height:85vh;max-width:1100px;border-radius:16px;overflow:hidden;border:1px solid rgba(108,92,231,.3);position:relative;background:#0f0f1a}
.m3d-preview-container iframe{width:100%;height:100%;border:none}
.m3d-preview-close{position:absolute;top:12px;right:12px;z-index:10;width:36px;height:36px;border-radius:50%;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.15);color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s}
.m3d-preview-close:hover{background:rgba(220,53,69,.7);transform:scale(1.1)}
.m3d-preview-title{position:absolute;bottom:12px;left:12px;z-index:10;padding:6px 14px;background:rgba(0,0,0,.7);border-radius:8px;color:#fff;font-size:13px;font-weight:600}
/* ═══ Empty State ═══ */
.m3d-empty{text-align:center;padding:48px 20px;color:#999}
.m3d-empty i{font-size:48px;opacity:.2;display:block;margin-bottom:14px}
.m3d-empty p{font-size:14px;margin-bottom:16px}
/* ═══ Upload Mode Toggle ═══ */
.m3d-upload-mode{display:flex;gap:2px;margin-bottom:20px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#f8f9fa}
.m3d-upload-mode button{flex:1;padding:10px 16px;font-size:13px;font-weight:600;border:none;background:transparent;color:#888;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:6px}
.m3d-upload-mode button:hover{color:#333;background:#f0f0ff}
.m3d-upload-mode button.active{background:#6C5CE7;color:#fff}
/* ═══ Embed Section ═══ */
.m3d-embed-step{margin-bottom:20px;padding:20px;background:#f8f9fa;border-radius:12px;border:1px solid #e5e7eb}
.m3d-embed-step-header{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.m3d-embed-step-num{width:28px;height:28px;border-radius:50%;background:#6C5CE7;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.m3d-embed-step-title{font-size:14px;font-weight:600;color:#333}
.m3d-embed-kiri-toggle{display:flex;align-items:center;gap:8px;margin-bottom:14px;padding:10px 14px;background:#faf5ff;border-radius:8px;border:1px solid #ede9fe}
.m3d-embed-kiri-toggle label{font-size:12px;font-weight:600;color:#6C5CE7;cursor:pointer;display:flex;align-items:center;gap:6px}
.m3d-embed-code-area{width:100%;min-height:80px;font-family:'Courier New',monospace;font-size:12px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;resize:vertical;background:#fff;color:#333}
.m3d-embed-code-area:focus{outline:none;border-color:#6C5CE7;box-shadow:0 0 0 3px rgba(108,92,231,.1)}
.m3d-embed-preview{width:100%;height:280px;border-radius:12px;overflow:hidden;background:#0f0f1a;border:1px solid #e5e7eb;position:relative}
.m3d-embed-preview iframe{width:100%;height:100%;border:none}
.m3d-embed-preview .empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#555;gap:8px}
.m3d-embed-preview .empty i{font-size:36px;opacity:.25}
.m3d-embed-preview .empty span{font-size:12px}
.m3d-provider-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;background:#ede9fe;color:#6C5CE7}
.m3d-provider-pill.kiri{background:#dbeafe;color:#2563eb}
.m3d-provider-pill.sketchfab{background:#fef3c7;color:#d97706}
.m3d-provider-pill.youtube{background:#fee2e2;color:#dc2626}

.m3d-config-tool h4 i{color:#d97706}
</style>

<!-- ═══════ Stats Row ═══════ -->
<div id="statsRow" class="ci-auto-grid" style="margin-bottom:20px">
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#ede9fe"><i class="fas fa-cube" style="color:#6C5CE7"></i></div><div><p class="m3d-stat-num" id="sTotal">—</p><p class="m3d-stat-lbl">โมเดลทั้งหมด</p></div></div>
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#dbeafe"><i class="fas fa-link" style="color:#3b82f6"></i></div><div><p class="m3d-stat-num" id="sLinked">—</p><p class="m3d-stat-lbl">เชื่อมโยงบรรจุภัณฑ์</p></div></div>
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#e0e7ff"><i class="fas fa-code" style="color:#6366f1"></i></div><div><p class="m3d-stat-num" id="sEmbed">—</p><p class="m3d-stat-lbl">Embed / iFrame</p></div></div>
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#d1fae5"><i class="fas fa-hdd" style="color:#059669"></i></div><div><p class="m3d-stat-num" id="sStorage">—</p><p class="m3d-stat-lbl">พื้นที่จัดเก็บ</p></div></div>
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#fef3c7"><i class="fas fa-clipboard-list" style="color:#d97706"></i></div><div><p class="m3d-stat-num" id="sPending">—</p><p class="m3d-stat-lbl">คำขอรอดำเนินการ</p></div></div>
</div>

<!-- ═══════ Tab Strip ═══════ -->
<div class="m3d-tabs">
    <div class="m3d-tab active" onclick="switchTab('models')"><i class="fas fa-cube"></i> โมเดลทั้งหมด <span class="badge" id="tabModelCount">0</span></div>
    <div class="m3d-tab" onclick="switchTab('upload')"><i class="fas fa-cloud-upload-alt"></i> อัปโหลด / นำเข้า</div>
    <div class="m3d-tab" onclick="switchTab('requests')"><i class="fas fa-clipboard-list"></i> คำขอโมเดล <span class="badge" id="tabReqCount">0</span></div>
</div>

<!-- ═══════ Tab: All Models ═══════ -->
<div class="m3d-tab-body" id="tabModels">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px;position:relative">
            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;font-size:12px"></i>
            <input type="text" id="modelSearch" placeholder="ค้นหาโมเดล, CAS No., ชื่อสาร..." style="width:100%;padding:8px 12px 8px 34px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px" oninput="debounceLoadModels()">
        </div>
        <select id="filterType" onchange="refreshView()" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
            <option value="">ทุกประเภท</option>
            <option value="bottle">ขวด</option>
            <option value="vial">ขวดเล็ก</option>
            <option value="flask">ขวดทดลอง</option>
            <option value="jar">โหล</option>
            <option value="can">กระป๋อง</option>
            <option value="drum">ถัง</option>
            <option value="bag">ถุง</option>
            <option value="box">กล่อง</option>
            <option value="ampoule">แอมพูล</option>
            <option value="cylinder">ถังแก๊ส</option>
        </select>
        <div class="m3d-view-toggle">
            <button class="m3d-view-btn active" id="viewGrid" onclick="setView('grid')" title="แสดงแบบตาราง"><i class="fas fa-th"></i></button>
            <button class="m3d-view-btn" id="viewCas" onclick="setView('cas')" title="จัดกลุ่มตาม CAS Number"><i class="fas fa-layer-group"></i></button>
        </div>
    </div>
    <div id="modelContainer"></div>
    <div id="modelPagination" style="display:flex;justify-content:center;gap:6px;margin-top:20px"></div>
</div>

<!-- ═══════ Tab: Upload / Import ═══════ -->
<div class="m3d-tab-body" id="tabUpload" style="display:none">
    <div class="ci-card ci-card-body" style="max-width:750px;margin:0 auto">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px">
            <i class="fas fa-cloud-upload-alt" style="color:#6C5CE7"></i> นำเข้าโมเดล 3D
        </h3>

        <!-- Upload Mode Toggle -->
        <div class="m3d-upload-mode">
            <button class="active" id="modeFile" onclick="setUploadMode('file')">
                <i class="fas fa-file-upload"></i> อัปโหลดไฟล์
            </button>
            <button id="modeEmbed" onclick="setUploadMode('embed')">
                <i class="fas fa-code"></i> Embed / iFrame
            </button>
        </div>

        <!-- ═══ Mode: File Upload ═══ -->
        <div id="uploadModeFile">
            <!-- Dropzone -->
            <div class="m3d-upload-drop" id="dropZone"
                 ondragover="event.preventDefault();this.classList.add('dragover')"
                 ondragleave="this.classList.remove('dragover')"
                 ondrop="handleDrop(event)"
                 onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-cloud-upload-alt drop-icon"></i>
                <h3>ลากไฟล์มาวางที่นี่</h3>
                <p>หรือคลิกเพื่อเลือกไฟล์ — สูงสุด 100 MB</p>
                <div class="ext-badges">
                    <span>GLB</span><span>GLTF</span><span>OBJ</span><span>FBX</span><span>STL</span>
                </div>
            </div>
            <input type="file" id="fileInput" accept=".glb,.gltf,.obj,.fbx,.stl" style="display:none" onchange="handleFileSelect(this.files[0])">

            <!-- Upload Progress -->
            <div class="m3d-upload-progress" id="uploadProgress" style="display:none">
                <div class="m3d-progress-text">
                    <span id="uploadStatus">กำลังอัปโหลด...</span>
                    <span id="uploadPercent">0%</span>
                </div>
                <div class="m3d-progress-bar"><div class="m3d-progress-fill" id="uploadFill"></div></div>
            </div>

            <!-- Model Detail Form (appears after upload) -->
            <div class="m3d-upload-form" id="uploadForm" style="display:none">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f0f0f0">
                    <div style="width:40px;height:40px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center"><i class="fas fa-cube" style="color:#6C5CE7"></i></div>
                    <div>
                        <div id="uploadFileName" style="font-size:14px;font-weight:600;color:#333"></div>
                        <div id="uploadFileInfo" style="font-size:11px;color:#999"></div>
                    </div>
                </div>

                <div class="m3d-form-field">
                    <label>ชื่อโมเดล <span class="req">*</span></label>
                    <input type="text" id="fLabel" placeholder="เช่น ขวดแก้ว 2.5L">
                </div>

                <!-- Chemical Autocomplete -->
                <div class="m3d-form-field">
                    <label>เชื่อมโยงสารเคมี (CAS Number)</label>
                    <div class="chem-search-wrap">
                        <input type="text" id="fChemSearch" placeholder="พิมพ์ CAS No. หรือชื่อสาร เพื่อค้นหา..." oninput="searchChemical(this.value,'upload')">
                        <div class="chem-search-results" id="fChemResults"></div>
                    </div>
                    <div id="fChemSelected" style="display:none"></div>
                    <input type="hidden" id="fChemicalId" value="">
                </div>

                <div class="m3d-form-grid">
                    <div class="m3d-form-field">
                        <label>ประเภทภาชนะ <span class="req">*</span></label>
                        <select id="fType">
                            <option value="bottle">ขวด (Bottle)</option>
                            <option value="vial">ขวดเล็ก (Vial)</option>
                            <option value="flask">ขวดทดลอง (Flask)</option>
                            <option value="jar">โหล (Jar)</option>
                            <option value="can">กระป๋อง (Can)</option>
                            <option value="drum">ถัง (Drum)</option>
                            <option value="bag">ถุง (Bag)</option>
                            <option value="box">กล่อง (Box)</option>
                            <option value="ampoule">แอมพูล (Ampoule)</option>
                            <option value="cylinder">ถังแก๊ส (Cylinder)</option>
                        </select>
                    </div>
                    <div class="m3d-form-field">
                        <label>วัสดุ</label>
                        <select id="fMaterial">
                            <option value="">— ไม่ระบุ —</option>
                            <option value="glass">แก้ว (Glass)</option>
                            <option value="plastic">พลาสติก (Plastic)</option>
                            <option value="metal">โลหะ (Metal)</option>
                            <option value="hdpe">HDPE</option>
                            <option value="pp">PP</option>
                            <option value="pet">PET</option>
                            <option value="amber_glass">แก้วสีชา (Amber Glass)</option>
                        </select>
                    </div>
                </div>

                <div class="m3d-form-grid">
                    <div class="m3d-form-field">
                        <label>ขนาดบรรจุ ต่ำสุด</label>
                        <input type="number" id="fCapMin" step="any" min="0" placeholder="เช่น 0">
                    </div>
                    <div class="m3d-form-field">
                        <label>ขนาดบรรจุ สูงสุด</label>
                        <input type="number" id="fCapMax" step="any" min="0" placeholder="เช่น 5000">
                    </div>
                </div>

                <div class="m3d-form-grid">
                    <div class="m3d-form-field">
                        <label>หน่วย</label>
                        <select id="fCapUnit">
                            <option value="">—</option>
                            <option value="mL" selected>mL</option>
                            <option value="L">L</option>
                            <option value="g">g</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>
                    <div class="m3d-form-field">
                        <label>ลำดับ</label>
                        <input type="number" id="fSort" value="0" min="0">
                    </div>
                </div>

                <div class="m3d-form-field">
                    <label>คำอธิบาย</label>
                    <textarea id="fDesc" rows="2" placeholder="รายละเอียดโมเดล (ไม่บังคับ)"></textarea>
                </div>

                <div style="display:flex;gap:16px;align-items:center;margin-bottom:14px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" id="fIsDefault" style="width:16px;height:16px;accent-color:#6C5CE7"> ตั้งเป็นค่าเริ่มต้น (Default)
                    </label>
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button onclick="resetUpload()" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
                    <button onclick="saveUploadedModel()" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff" id="saveModelBtn">
                        <i class="fas fa-save"></i> บันทึกโมเดล
                    </button>
                </div>
            </div>

            <!-- Inline 3D Preview -->
            <div id="uploadPreview" style="display:none;margin-top:16px;border-radius:14px;overflow:hidden;border:1.5px solid #e5e7eb;height:300px;background:#0f0f1a">
            </div>
        </div>

        <!-- ═══ Mode: Embed / iFrame ═══ -->
        <div id="uploadModeEmbed" style="display:none">
            <!-- Step 1: URL / Code Input -->
            <div class="m3d-embed-step">
                <div class="m3d-embed-step-header">
                    <div class="m3d-embed-step-num">1</div>
                    <div class="m3d-embed-step-title">วาง URL หรือ Iframe Code</div>
                </div>

                <div class="m3d-embed-kiri-toggle">
                    <label>
                        <input type="checkbox" id="embedKiriAuto" checked onchange="onKiriAutoConfigChange()" style="accent-color:#6C5CE7">
                        <i class="fas fa-magic"></i> Kiri Engine Auto-Config
                    </label>
                    <span style="font-size:10px;color:#999;margin-left:auto">ปรับ URL อัตโนมัติสำหรับ Kiri Engine</span>
                </div>

                <div class="m3d-form-field">
                    <label>Embed URL <span class="req">*</span></label>
                    <input type="text" id="embedSrc" placeholder="https://www.kiriengine.app/embed/..." oninput="onEmbedSrcInput()">
                    <div id="embedProviderBadge" style="margin-top:6px"></div>
                </div>

                <div class="m3d-form-field" style="margin-top:10px">
                    <label>Iframe Code <span style="font-size:10px;color:#999;font-weight:400">(วาง code ทั้ง &lt;iframe&gt; ระบบจะดึง src อัตโนมัติ)</span></label>
                    <textarea class="m3d-embed-code-area" id="embedCode" rows="4" placeholder='วาง <iframe src="..." ...></iframe> ที่นี่...' onpaste="onEmbedPasteCode()"></textarea>
                </div>
            </div>

            <!-- Step 2: Content Details -->
            <div class="m3d-embed-step">
                <div class="m3d-embed-step-header">
                    <div class="m3d-embed-step-num">2</div>
                    <div class="m3d-embed-step-title">รายละเอียดเนื้อหา</div>
                </div>

                <div class="m3d-form-field">
                    <label>ชื่อโมเดล <span class="req">*</span></label>
                    <input type="text" id="embedTitle" placeholder="เช่น Chemical Bottle 3D - Kiri Scan">
                </div>

                <div class="m3d-form-grid">
                    <div class="m3d-form-field">
                        <label>แหล่งที่มา (Provider)</label>
                        <select id="embedProvider">
                            <option value="">— ตรวจจับอัตโนมัติ —</option>
                            <option value="Kiri Engine">Kiri Engine</option>
                            <option value="Sketchfab">Sketchfab</option>
                            <option value="YouTube">YouTube</option>
                            <option value="Matterport">Matterport</option>
                            <option value="Google Maps">Google Maps</option>
                            <option value="Vimeo">Vimeo</option>
                            <option value="Polycam">Polycam</option>
                            <option value="Luma AI">Luma AI</option>
                            <option value="Other">อื่นๆ</option>
                        </select>
                    </div>
                    <div class="m3d-form-field">
                        <label>ประเภทภาชนะ <span class="req">*</span></label>
                        <select id="embedContainerType">
                            <option value="bottle">ขวด (Bottle)</option>
                            <option value="vial">ขวดเล็ก (Vial)</option>
                            <option value="flask">ขวดทดลอง (Flask)</option>
                            <option value="jar">โหล (Jar)</option>
                            <option value="can">กระป๋อง (Can)</option>
                            <option value="drum">ถัง (Drum)</option>
                            <option value="bag">ถุง (Bag)</option>
                            <option value="box">กล่อง (Box)</option>
                            <option value="ampoule">แอมพูล (Ampoule)</option>
                            <option value="cylinder">ถังแก๊ส (Cylinder)</option>
                        </select>
                    </div>
                </div>

                <!-- Chemical Autocomplete for embed -->
                <div class="m3d-form-field">
                    <label>เชื่อมโยงสารเคมี (CAS Number)</label>
                    <div class="chem-search-wrap">
                        <input type="text" id="embedChemSearch" placeholder="พิมพ์ CAS No. หรือชื่อสาร เพื่อค้นหา..." oninput="searchChemical(this.value,'embed')">
                        <div class="chem-search-results" id="embedChemResults"></div>
                    </div>
                    <div id="embedChemSelected" style="display:none"></div>
                    <input type="hidden" id="embedChemicalId" value="">
                </div>

                <div class="m3d-form-field">
                    <label>คำอธิบาย</label>
                    <textarea id="embedDesc" rows="2" placeholder="รายละเอียดเพิ่มเติม (ไม่บังคับ)"></textarea>
                </div>

                <div style="display:flex;gap:16px;align-items:center">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" id="embedIsDefault" style="width:16px;height:16px;accent-color:#6C5CE7"> ตั้งเป็นค่าเริ่มต้น
                    </label>
                </div>
            </div>

            <!-- Step 3: Preview & Save -->
            <div class="m3d-embed-step">
                <div class="m3d-embed-step-header">
                    <div class="m3d-embed-step-num">3</div>
                    <div class="m3d-embed-step-title">ดูตัวอย่าง &amp; บันทึก</div>
                </div>

                <div class="m3d-embed-preview" id="embedPreviewBox">
                    <div class="empty">
                        <i class="fas fa-play-circle"></i>
                        <span>กรอก URL ด้านบนเพื่อดูตัวอย่าง</span>
                    </div>
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                    <button onclick="resetEmbed()" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">
                        <i class="fas fa-redo"></i> เริ่มใหม่
                    </button>
                    <button onclick="saveEmbed()" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff" id="saveEmbedBtn">
                        <i class="fas fa-save"></i> บันทึก Embed
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════ Tab: Requests ═══════ -->
<div class="m3d-tab-body" id="tabRequests" style="display:none">
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <select id="reqStatusFilter" onchange="loadRequests()" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
            <option value="">ทุกสถานะ</option>
            <option value="pending">รอดำเนินการ</option>
            <option value="approved">อนุมัติแล้ว</option>
            <option value="in_progress">กำลังดำเนินการ</option>
            <option value="completed">เสร็จสิ้น</option>
            <option value="rejected">ปฏิเสธ</option>
        </select>
    </div>
    <div id="requestList"></div>
</div>



<!-- ═══════ Edit Modal ═══════ -->
<div class="m3d-modal-overlay" id="editModal" onclick="if(event.target===this)closeEditModal()">
    <div class="m3d-modal">
        <div class="m3d-modal-header">
            <h3><i class="fas fa-edit"></i> <span id="editModalTitle">แก้ไขโมเดล</span></h3>
            <button class="m3d-modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="m3d-modal-body">
            <input type="hidden" id="editId">

            <!-- 3D Preview inside edit -->
            <div id="editPreviewWrap" style="height:200px;border-radius:12px;overflow:hidden;margin-bottom:16px;background:#0f0f1a;border:1px solid #e5e7eb"></div>

            <div class="m3d-form-field">
                <label>ชื่อโมเดล <span class="req">*</span></label>
                <input type="text" id="eLabel" placeholder="เช่น ขวดแก้ว 2.5L">
            </div>

            <!-- Chemical Autocomplete in Edit -->
            <div class="m3d-form-field">
                <label>เชื่อมโยงสารเคมี (CAS Number)</label>
                <div class="chem-search-wrap">
                    <input type="text" id="eChemSearch" placeholder="พิมพ์ CAS No. หรือชื่อสาร เพื่อค้นหา..." oninput="searchChemical(this.value,'edit')">
                    <div class="chem-search-results" id="eChemResults"></div>
                </div>
                <div id="eChemSelected" style="display:none"></div>
                <input type="hidden" id="eChemicalId" value="">
            </div>

            <div class="m3d-form-grid">
                <div class="m3d-form-field">
                    <label>ประเภทภาชนะ <span class="req">*</span></label>
                    <select id="eType">
                        <option value="bottle">ขวด (Bottle)</option>
                        <option value="vial">ขวดเล็ก (Vial)</option>
                        <option value="flask">ขวดทดลอง (Flask)</option>
                        <option value="jar">โหล (Jar)</option>
                        <option value="can">กระป๋อง (Can)</option>
                        <option value="drum">ถัง (Drum)</option>
                        <option value="bag">ถุง (Bag)</option>
                        <option value="box">กล่อง (Box)</option>
                        <option value="ampoule">แอมพูล (Ampoule)</option>
                        <option value="cylinder">ถังแก๊ส (Cylinder)</option>
                    </select>
                </div>
                <div class="m3d-form-field">
                    <label>วัสดุ</label>
                    <select id="eMaterial">
                        <option value="">— ไม่ระบุ —</option>
                        <option value="glass">แก้ว (Glass)</option>
                        <option value="plastic">พลาสติก (Plastic)</option>
                        <option value="metal">โลหะ (Metal)</option>
                        <option value="hdpe">HDPE</option>
                        <option value="pp">PP</option>
                        <option value="pet">PET</option>
                        <option value="amber_glass">แก้วสีชา (Amber Glass)</option>
                    </select>
                </div>
            </div>

            <div class="m3d-form-grid">
                <div class="m3d-form-field">
                    <label>ขนาดบรรจุ ต่ำสุด</label>
                    <input type="number" id="eCapMin" step="any" min="0">
                </div>
                <div class="m3d-form-field">
                    <label>ขนาดบรรจุ สูงสุด</label>
                    <input type="number" id="eCapMax" step="any" min="0">
                </div>
            </div>

            <div class="m3d-form-grid">
                <div class="m3d-form-field">
                    <label>หน่วย</label>
                    <select id="eCapUnit">
                        <option value="">—</option>
                        <option value="mL">mL</option>
                        <option value="L">L</option>
                        <option value="g">g</option>
                        <option value="kg">kg</option>
                    </select>
                </div>
                <div class="m3d-form-field">
                    <label>ลำดับ</label>
                    <input type="number" id="eSort" value="0" min="0">
                </div>
            </div>

            <div class="m3d-form-field">
                <label>คำอธิบาย</label>
                <textarea id="eDesc" rows="2" placeholder="รายละเอียดโมเดล"></textarea>
            </div>

            <div style="display:flex;gap:16px;align-items:center">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="checkbox" id="eIsDefault" style="width:16px;height:16px;accent-color:#6C5CE7"> ตั้งเป็นค่าเริ่มต้น (Default)
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="checkbox" id="eArEnabled" style="width:16px;height:16px;accent-color:#059669"> รองรับ AR
                </label>
            </div>
        </div>
        <div class="m3d-modal-footer">
            <button onclick="closeEditModal()" class="ci-btn ci-btn-sm" style="border:1px solid #e0e0e0">ยกเลิก</button>
            <button onclick="saveEditModel()" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff" id="editSaveBtn">
                <i class="fas fa-save"></i> บันทึกการแก้ไข
            </button>
        </div>
    </div>
</div>

<!-- ═══════ 3D Preview Modal ═══════ -->
<div class="m3d-preview-modal" id="previewModal" onclick="if(event.target===this)closePreview()">
    <div class="m3d-preview-container">
        <button class="m3d-preview-close" onclick="closePreview()"><i class="fas fa-times"></i></button>
        <div class="m3d-preview-title" id="previewTitle"></div>
        <iframe id="previewFrame" src="" allowfullscreen></iframe>
    </div>
</div>

<script>
const API = '/v1/api/models3d.php';
let currentModels = [];
let uploadedFileData = null;
let debounceTimer = null;
let chemSearchTimer = null;
let currentView = 'grid';
let editingModel = null;
let iframeConfig = {};
let embedPreviewTimer = null;

const TYPES = {bottle:'ขวด',vial:'ขวดเล็ก',flask:'ขวดทดลอง',jar:'โหล',can:'กระป๋อง',drum:'ถัง',bag:'ถุง',box:'กล่อง',ampoule:'แอมพูล',cylinder:'ถังแก๊ส'};
const MATS = {glass:'แก้ว',plastic:'พลาสติก',metal:'โลหะ',hdpe:'HDPE',pp:'PP',pet:'PET',amber_glass:'แก้วสีชา'};

// ═══ Helpers ═══
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function fmtDate(d) { if(!d) return '—'; return new Date(d).toLocaleDateString('th-TH',{year:'numeric',month:'short',day:'numeric'}); }
function fmtSize(b) { b=parseInt(b)||0; if(b<1024) return b+' B'; if(b<1048576) return (b/1024).toFixed(1)+' KB'; return (b/1048576).toFixed(1)+' MB'; }

async function apiFetch(url, opts) {
    const token = localStorage.getItem('auth_token') || '';
    const headers = opts?.headers || {};
    if (token) headers['Authorization'] = 'Bearer ' + token;
    if (!(opts?.body instanceof FormData) && !headers['Content-Type']) {
        headers['Content-Type'] = 'application/json';
    }
    const r = await fetch(url, { ...opts, headers });
    return r.json();
}

function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;animation:cmFadeIn .2s;max-width:360px;box-shadow:0 4px 12px rgba(0,0,0,.15)';
    t.style.background = type==='error'?'#fee2e2':type==='warning'?'#fef3c7':'#d1fae5';
    t.style.color = type==='error'?'#dc2626':type==='warning'?'#d97706':'#059669';
    t.innerHTML = '<i class="fas fa-'+(type==='error'?'times-circle':type==='warning'?'exclamation-triangle':'check-circle')+'"></i> '+esc(msg);
    document.body.appendChild(t);
    setTimeout(()=>t.remove(), 3000);
}

// ═══ Tab Switching ═══
function switchTab(tab) {
    document.querySelectorAll('.m3d-tab-body').forEach(b=>b.style.display='none');
    document.querySelectorAll('.m3d-tab').forEach(t=>t.classList.remove('active'));
    const tabMap = {models:'tabModels', upload:'tabUpload', requests:'tabRequests'};
    const el = document.getElementById(tabMap[tab]);
    if(el) el.style.display = 'block';
    // Activate the right tab button
    const tabs = document.querySelectorAll('.m3d-tab');
    const tabOrder = ['models','upload','requests'];
    const idx = tabOrder.indexOf(tab);
    if(idx >= 0 && tabs[idx]) tabs[idx].classList.add('active');
    if(tab==='requests') loadRequests();
}

// ═══ View Toggle ═══
function setView(v) {
    currentView = v;
    document.getElementById('viewGrid').classList.toggle('active', v==='grid');
    document.getElementById('viewCas').classList.toggle('active', v==='cas');
    document.getElementById('modelPagination').style.display = v==='grid' ? 'flex' : 'none';
    refreshView();
}

function refreshView() {
    if(currentView === 'cas') loadModelsCas();
    else loadModels();
}

// ═══ Load Stats ═══
async function loadStats() {
    try {
        const d = await apiFetch(API+'?action=stats');
        if(!d.success) return;
        const s = d.data;
        document.getElementById('sTotal').textContent = s.total_models || 0;
        document.getElementById('sLinked').textContent = s.specific_links || 0;
        document.getElementById('sEmbed').textContent = s.embed_models || 0;
        document.getElementById('sStorage').textContent = s.total_storage_fmt || '0 B';
        document.getElementById('sPending').textContent = s.pending_requests || 0;
    } catch(e){}
}

// ═══ Load Models (Grid View) ═══
function debounceLoadModels() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(refreshView, 300);
}

async function loadModels(page=1) {
    const search = document.getElementById('modelSearch').value.trim();
    const type = document.getElementById('filterType').value;
    let url = API+'?action=list&page='+page+'&limit=20';
    if(search) url += '&search=' + encodeURIComponent(search);
    if(type) url += '&container_type=' + type;

    try {
        const d = await apiFetch(url);
        if(!d.success) throw new Error(d.error);
        currentModels = d.data;
        document.getElementById('tabModelCount').textContent = d.pagination.total;
        renderModelGrid(d.data, d.pagination);
    } catch(e) {
        document.getElementById('modelContainer').innerHTML = '<div class="m3d-empty"><i class="fas fa-exclamation-triangle"></i><p>'+esc(e.message)+'</p></div>';
    }
}

// ═══ Load Models (CAS Group View) ═══
async function loadModelsCas() {
    const search = document.getElementById('modelSearch').value.trim();
    const type = document.getElementById('filterType').value;
    let url = API+'?action=list&group_by=cas';
    if(search) url += '&search=' + encodeURIComponent(search);
    if(type) url += '&container_type=' + type;

    try {
        const d = await apiFetch(url);
        if(!d.success) throw new Error(d.error);
        currentModels = [];
        (d.data.grouped||[]).forEach(g => { currentModels = currentModels.concat(g.models); });
        currentModels = currentModels.concat(d.data.ungrouped||[]);
        document.getElementById('tabModelCount').textContent = d.total;
        renderCasGroupView(d.data);
    } catch(e) {
        document.getElementById('modelContainer').innerHTML = '<div class="m3d-empty"><i class="fas fa-exclamation-triangle"></i><p>'+esc(e.message)+'</p></div>';
    }
}

// ═══ Render: Model Card (shared) ═══
function renderModelCard(m) {
    const isEmbed = m.source_type === 'embed';
    const hasFile = m.file_url;
    const hasPreview = hasFile || isEmbed;

    let previewSrc = '';
    if (isEmbed && m.embed_url) {
        previewSrc = m.embed_url;
    } else if (hasFile) {
        previewSrc = '/v1/pages/viewer3d.php?src='+encodeURIComponent(m.file_url)+'&transparent=1&embed=1';
    }

    let html = '<div class="m3d-card '+(m.is_default?'is-default':'')+'">';

    // Preview area
    html += '<div class="m3d-card-preview" onclick="'+(hasPreview?'openPreview('+m.id+')':'void(0)')+'" style="cursor:'+(hasPreview?'pointer':'default')+'">';
    if(previewSrc) {
        html += '<iframe src="'+esc(previewSrc)+'" loading="lazy" style="pointer-events:none"></iframe>';
    } else {
        html += '<div class="no-preview"><i class="fas fa-cube"></i><span style="font-size:11px">ไม่มีไฟล์</span></div>';
    }
    html += '</div>';

    // Body
    html += '<div class="m3d-card-body">';
    html += '<div class="m3d-card-title">';
    html += esc(m.label);
    if(m.is_default) html += ' <span class="tag tag-default">Default</span>';
    if(m.ar_enabled) html += ' <span class="tag tag-ar">AR</span>';
    if(isEmbed) html += ' <span class="tag" style="background:#dbeafe;color:#2563eb">Embed</span>';
    html += '</div>';

    // Meta
    html += '<div class="m3d-card-meta">';
    html += '<div><i class="fas fa-box"></i> '+(TYPES[m.container_type]||m.container_type);
    if(m.container_material) html += ' • '+(MATS[m.container_material]||m.container_material);
    html += '</div>';
    if(m.capacity_range_min||m.capacity_range_max) {
        html += '<div><i class="fas fa-ruler"></i> '+(m.capacity_range_min||0)+' – '+(m.capacity_range_max||'∞')+' '+(m.capacity_unit||'')+'</div>';
    }
    if(m.cas_number) {
        html += '<div><i class="fas fa-hashtag"></i> <span style="font-family:monospace;font-weight:600;color:#6C5CE7">'+esc(m.cas_number)+'</span></div>';
    }
    if(m.chemical_name) {
        html += '<div><i class="fas fa-flask"></i> '+esc(m.chemical_name)+'</div>';
    }
    if(isEmbed && m.embed_provider) {
        html += '<div><i class="fas fa-globe"></i> <span class="m3d-provider-pill'+getProviderClass(m.embed_provider)+'">'+esc(m.embed_provider)+'</span></div>';
    }
    if(!isEmbed && m.original_name) {
        html += '<div><i class="fas fa-file"></i> '+esc(m.original_name);
        if(m.file_size) html += ' ('+fmtSize(m.file_size)+')';
        html += '</div>';
    }
    html += '<div><i class="fas fa-calendar"></i> '+fmtDate(m.created_at)+'</div>';
    html += '</div>';

    // Actions
    html += '<div class="m3d-card-actions">';
    html += '<button onclick="openEditModal('+m.id+')" class="ci-btn ci-btn-sm" style="font-size:11px;color:#f59e0b"><i class="fas fa-edit"></i> แก้ไข</button>';
    if(hasPreview) {
        html += '<button onclick="openPreview('+m.id+')" class="ci-btn ci-btn-sm" style="font-size:11px;color:#6C5CE7"><i class="fas fa-eye"></i> ดู</button>';
        if(!isEmbed && hasFile) {
            html += '<button onclick="openInViewer('+m.id+')" class="ci-btn ci-btn-sm" style="font-size:11px;color:#059669"><i class="fas fa-expand"></i> เต็มจอ</button>';
        }
        if(isEmbed && m.embed_url) {
            html += '<button onclick="window.open(\''+esc(m.embed_url)+'\',\'_blank\')" class="ci-btn ci-btn-sm" style="font-size:11px;color:#059669"><i class="fas fa-external-link-alt"></i> เปิด</button>';
        }
    }
    html += '<button onclick="deleteModel('+m.id+')" class="ci-btn ci-btn-sm" style="font-size:11px;color:#ef4444;margin-left:auto"><i class="fas fa-trash"></i></button>';
    html += '</div></div></div>';

    return html;
}

function getProviderClass(provider) {
    if (!provider) return '';
    var p = provider.toLowerCase();
    if (p.indexOf('kiri') >= 0) return ' kiri';
    if (p.indexOf('sketchfab') >= 0) return ' sketchfab';
    if (p.indexOf('youtube') >= 0) return ' youtube';
    return '';
}

function renderModelGrid(models, pagination) {
    const container = document.getElementById('modelContainer');
    if(!models.length) {
        container.innerHTML = '<div class="m3d-empty"><i class="fas fa-cube"></i><p>ยังไม่มีโมเดล 3D</p><button onclick="switchTab(\'upload\')" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff"><i class="fas fa-cloud-upload-alt"></i> อัปโหลดโมเดลแรก</button></div>';
        document.getElementById('modelPagination').innerHTML = '';
        return;
    }

    container.innerHTML = '<div class="m3d-grid">' + models.map(renderModelCard).join('') + '</div>';

    // Pagination
    const pg = document.getElementById('modelPagination');
    if(pagination.total_pages > 1) {
        let html = '';
        for(let i=1; i<=pagination.total_pages; i++) {
            html += '<button onclick="loadModels('+i+')" class="ci-btn ci-btn-sm" style="'+(i===pagination.page?'background:#6C5CE7;color:#fff':'border:1px solid #e5e7eb')+'">'+i+'</button>';
        }
        pg.innerHTML = html;
    } else {
        pg.innerHTML = '';
    }
}

// ═══ Render: CAS Number Group View ═══
function renderCasGroupView(data) {
    const container = document.getElementById('modelContainer');
    const grouped = data.grouped || [];
    const ungrouped = data.ungrouped || [];

    if(!grouped.length && !ungrouped.length) {
        container.innerHTML = '<div class="m3d-empty"><i class="fas fa-cube"></i><p>ยังไม่มีโมเดล 3D</p><button onclick="switchTab(\'upload\')" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff"><i class="fas fa-cloud-upload-alt"></i> อัปโหลดโมเดลแรก</button></div>';
        return;
    }

    let html = '';

    // Grouped by CAS
    grouped.forEach(function(g) {
        html += '<div class="cas-group">';
        html += '<div class="cas-group-header" onclick="toggleCasGroup(this)">';
        html += '<i class="fas fa-chevron-down cas-chevron"></i>';
        html += '<span class="cas-badge">'+esc(g.cas_number)+'</span>';
        html += '<span class="cas-name">'+esc(g.chemical_name || '')+'</span>';
        html += '<span class="cas-count">'+g.models.length+' โมเดล</span>';
        html += '</div>';
        html += '<div class="cas-group-body">';
        html += '<div class="m3d-grid">';
        html += g.models.map(renderModelCard).join('');
        html += '</div></div></div>';
    });

    // Ungrouped (no CAS)
    if(ungrouped.length) {
        html += '<div class="cas-ungrouped">';
        html += '<div class="cas-ungrouped-header"><i class="fas fa-inbox"></i><span>ไม่ได้ระบุ CAS Number ('+ungrouped.length+' โมเดล)</span></div>';
        html += '<div class="m3d-grid">';
        html += ungrouped.map(renderModelCard).join('');
        html += '</div></div>';
    }

    container.innerHTML = html;
}

function toggleCasGroup(headerEl) {
    headerEl.classList.toggle('collapsed');
    const body = headerEl.nextElementSibling;
    body.classList.toggle('collapsed');
}

// ═══ Preview Modal ═══
function openPreview(id) {
    const m = currentModels.find(x=>x.id==id);
    if(!m) return;
    const isEmbed = m.source_type === 'embed';
    document.getElementById('previewTitle').textContent = m.label || 'โมเดล 3D';

    if(isEmbed && m.embed_url) {
        document.getElementById('previewFrame').src = m.embed_url;
    } else if(m.file_url) {
        document.getElementById('previewFrame').src = '/v1/pages/viewer3d.php?src='+encodeURIComponent(m.file_url)+'&title='+encodeURIComponent(m.label||'');
    } else {
        return;
    }
    document.getElementById('previewModal').classList.add('show');
}

function closePreview() {
    document.getElementById('previewModal').classList.remove('show');
    document.getElementById('previewFrame').src = '';
}

function openInViewer(id) {
    const m = currentModels.find(x=>x.id==id);
    if(!m || !m.file_url) return;
    window.open('/v1/pages/viewer3d.php?src='+encodeURIComponent(m.file_url)+'&title='+encodeURIComponent(m.label||''), '_blank');
}

// ═══ Delete Model ═══
async function deleteModel(id) {
    const m = currentModels.find(x=>x.id==id);
    const name = m ? m.label : 'โมเดลนี้';
    if(!confirm('ลบโมเดล "'+name+'" ?\nโมเดลจะถูกปิดการใช้งาน')) return;
    try {
        const d = await apiFetch(API+'?action=delete&id='+id, {method:'DELETE'});
        if(d.success) { showToast('ลบโมเดลสำเร็จ'); refreshView(); loadStats(); }
        else throw new Error(d.error);
    } catch(e) { showToast(e.message, 'error'); }
}

// ═══ Chemical Autocomplete ═══
function searchChemical(q, ctx) {
    clearTimeout(chemSearchTimer);
    const prefixMap = {edit:'e', upload:'f', embed:'embed'};
    const prefix = prefixMap[ctx] || ctx;
    const resultsEl = document.getElementById(prefix+'ChemResults');
    if(q.trim().length < 1) { resultsEl.classList.remove('show'); return; }
    chemSearchTimer = setTimeout(async function() {
        try {
            const d = await apiFetch(API+'?action=chemicals_search&q='+encodeURIComponent(q));
            if(!d.success) return;
            if(!d.data.length) {
                resultsEl.innerHTML = '<div style="padding:10px;text-align:center;color:#999;font-size:12px">ไม่พบสารเคมี</div>';
            } else {
                resultsEl.innerHTML = d.data.map(function(c) {
                    return '<div class="chem-search-item" onclick="selectChemical('+c.id+',\''+escAttr(c.cas_number||'')+'\',\''+escAttr(c.name||'')+'\',\''+ctx+'\')">'
                        + '<span class="cas">'+(c.cas_number||'—')+'</span>'
                        + '<span class="name">'+esc(c.name)+'</span>'
                        + '</div>';
                }).join('');
            }
            resultsEl.classList.add('show');
        } catch(e){}
    }, 250);
}

function escAttr(s) {
    return (s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
}

function selectChemical(id, cas, name, ctx) {
    const prefixMap = {edit:'e', upload:'f', embed:'embed'};
    const prefix = prefixMap[ctx] || ctx;
    document.getElementById(prefix+'ChemicalId').value = id;
    document.getElementById(prefix+'ChemSearch').value = '';
    document.getElementById(prefix+'ChemResults').classList.remove('show');
    const sel = document.getElementById(prefix+'ChemSelected');
    sel.style.display = 'block';
    sel.innerHTML = '<div class="chem-selected"><span class="cas">'+esc(cas)+'</span><span class="name">'+esc(name)+'</span><span class="clear" onclick="clearChemical(\''+ctx+'\')">&times;</span></div>';
}

function clearChemical(ctx) {
    const prefixMap = {edit:'e', upload:'f', embed:'embed'};
    const prefix = prefixMap[ctx] || ctx;
    document.getElementById(prefix+'ChemicalId').value = '';
    document.getElementById(prefix+'ChemSelected').style.display = 'none';
    document.getElementById(prefix+'ChemSelected').innerHTML = '';
}

// Close autocomplete when clicking outside
document.addEventListener('click', function(e) {
    if(!e.target.closest('.chem-search-wrap')) {
        document.querySelectorAll('.chem-search-results').forEach(function(el){el.classList.remove('show');});
    }
});

// ═══ Edit Modal ═══
async function openEditModal(id) {
    try {
        const d = await apiFetch(API+'?action=detail&id='+id);
        if(!d.success) throw new Error(d.error);
        const m = d.data;
        editingModel = m;

        document.getElementById('editId').value = m.id;
        document.getElementById('editModalTitle').textContent = 'แก้ไขโมเดล: ' + (m.label||'');
        document.getElementById('eLabel').value = m.label || '';
        document.getElementById('eType').value = m.container_type || 'bottle';
        document.getElementById('eMaterial').value = m.container_material || '';
        document.getElementById('eCapMin').value = m.capacity_range_min || '';
        document.getElementById('eCapMax').value = m.capacity_range_max || '';
        document.getElementById('eCapUnit').value = m.capacity_unit || '';
        document.getElementById('eSort').value = m.sort_order || 0;
        document.getElementById('eDesc').value = m.description || '';
        document.getElementById('eIsDefault').checked = !!parseInt(m.is_default);
        document.getElementById('eArEnabled').checked = !!parseInt(m.ar_enabled);

        // Chemical link
        clearChemical('edit');
        if(m.chemical_id && m.cas_number) {
            selectChemical(m.chemical_id, m.cas_number, m.chemical_name||'', 'edit');
        }

        // 3D Preview
        const pw = document.getElementById('editPreviewWrap');
        const isEmbed = m.source_type === 'embed';
        if(isEmbed && m.embed_url) {
            pw.innerHTML = '<iframe src="'+esc(m.embed_url)+'" style="width:100%;height:100%;border:none" allowfullscreen></iframe>';
        } else if(m.file_url) {
            pw.innerHTML = '<iframe src="/v1/pages/viewer3d.php?src='+encodeURIComponent(m.file_url)+'&embed=1" style="width:100%;height:100%;border:none"></iframe>';
        } else {
            pw.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#555"><i class="fas fa-cube" style="font-size:40px;opacity:.2"></i></div>';
        }
        pw.style.display = 'block';

        document.getElementById('editModal').classList.add('show');
    } catch(e) {
        showToast(e.message, 'error');
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
    document.getElementById('editPreviewWrap').innerHTML = '';
    editingModel = null;
}

async function saveEditModel() {
    const id = parseInt(document.getElementById('editId').value);
    const label = document.getElementById('eLabel').value.trim();
    const type = document.getElementById('eType').value;
    if(!label) { showToast('กรุณาระบุชื่อโมเดล','warning'); return; }
    if(!id) { showToast('ไม่พบข้อมูลโมเดล','error'); return; }

    const btn = document.getElementById('editSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    try {
        const data = {
            id: id,
            label: label,
            container_type: type,
            container_material: document.getElementById('eMaterial').value || null,
            chemical_id: parseInt(document.getElementById('eChemicalId').value) || null,
            capacity_range_min: parseFloat(document.getElementById('eCapMin').value) || null,
            capacity_range_max: parseFloat(document.getElementById('eCapMax').value) || null,
            capacity_unit: document.getElementById('eCapUnit').value || null,
            description: document.getElementById('eDesc').value.trim() || null,
            is_default: document.getElementById('eIsDefault').checked ? 1 : 0,
            ar_enabled: document.getElementById('eArEnabled').checked ? 1 : 0,
            sort_order: parseInt(document.getElementById('eSort').value) || 0,
            // Preserve existing file/embed data
            file_path: editingModel?.file_path || null,
            file_url: editingModel?.file_url || null,
            original_name: editingModel?.original_name || null,
            mime_type: editingModel?.mime_type || null,
            extension: editingModel?.extension || null,
            file_size: editingModel?.file_size || 0,
            source_type: editingModel?.source_type || 'upload',
            embed_url: editingModel?.embed_url || null,
            embed_code: editingModel?.embed_code || null,
            embed_provider: editingModel?.embed_provider || null,
        };

        const d = await apiFetch(API+'?action=save', { method:'POST', body:JSON.stringify(data) });
        if(d.success) {
            showToast('บันทึกการแก้ไขสำเร็จ!');
            closeEditModal();
            refreshView();
            loadStats();
        } else throw new Error(d.error);
    } catch(e) {
        showToast(e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> บันทึกการแก้ไข';
    }
}

// ═══ File Upload ═══
function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').classList.remove('dragover');
    const files = e.dataTransfer.files;
    if(files.length) handleFileSelect(files[0]);
}

async function handleFileSelect(file) {
    if(!file) return;
    const ext = file.name.split('.').pop().toLowerCase();
    const allowed = ['glb','gltf','obj','fbx','stl'];
    if(!allowed.includes(ext)) {
        showToast('ไฟล์ประเภท .'+ext+' ไม่รองรับ ใช้: '+allowed.join(', '), 'error');
        return;
    }
    if(file.size > 100*1024*1024) {
        showToast('ไฟล์ใหญ่เกิน 100 MB', 'error');
        return;
    }

    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('uploadForm').style.display = 'none';
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('uploadStatus').textContent = 'กำลังอัปโหลด ' + file.name + '...';
    document.getElementById('uploadPercent').textContent = '0%';
    document.getElementById('uploadFill').style.width = '0%';

    var formData = new FormData();
    formData.append('model_file', file);

    try {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API+'?action=upload');
        var token = localStorage.getItem('auth_token') || '';
        if(token) xhr.setRequestHeader('Authorization', 'Bearer ' + token);

        xhr.upload.onprogress = function(e) {
            if(e.lengthComputable) {
                var pct = Math.round(e.loaded / e.total * 100);
                document.getElementById('uploadFill').style.width = pct + '%';
                document.getElementById('uploadPercent').textContent = pct + '%';
            }
        };

        xhr.onload = function() {
            var d = JSON.parse(xhr.responseText);
            if(d.success) {
                uploadedFileData = d.data;
                document.getElementById('uploadStatus').textContent = 'อัปโหลดสำเร็จ!';
                document.getElementById('uploadFill').style.width = '100%';
                document.getElementById('uploadFill').style.background = '#059669';

                setTimeout(function(){
                    document.getElementById('uploadProgress').style.display = 'none';
                    document.getElementById('uploadForm').style.display = 'block';
                    document.getElementById('uploadFileName').textContent = d.data.original_name;
                    document.getElementById('uploadFileInfo').textContent = d.data.file_size_fmt + ' \u2022 ' + d.data.extension.toUpperCase();
                    document.getElementById('fLabel').value = d.data.original_name.replace(/\.[^.]+$/, '');

                    if(['glb','gltf'].indexOf(d.data.extension) >= 0) {
                        var pv = document.getElementById('uploadPreview');
                        pv.style.display = 'block';
                        pv.innerHTML = '<iframe src="/v1/pages/viewer3d.php?src='+encodeURIComponent(d.data.file_url)+'&embed=1" style="width:100%;height:100%;border:none"></iframe>';
                    }
                }, 500);
            } else {
                showToast(d.error || 'Upload failed', 'error');
                document.getElementById('uploadProgress').style.display = 'none';
            }
        };

        xhr.onerror = function() {
            showToast('Network error', 'error');
            document.getElementById('uploadProgress').style.display = 'none';
        };

        xhr.send(formData);
    } catch(e) {
        showToast(e.message, 'error');
        document.getElementById('uploadProgress').style.display = 'none';
    }
}

async function saveUploadedModel() {
    if(!uploadedFileData) { showToast('กรุณาอัปโหลดไฟล์ก่อน','warning'); return; }
    var label = document.getElementById('fLabel').value.trim();
    var type = document.getElementById('fType').value;
    if(!label) { showToast('กรุณาระบุชื่อโมเดล','warning'); return; }

    var btn = document.getElementById('saveModelBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    try {
        var data = {
            label: label,
            container_type: type,
            container_material: document.getElementById('fMaterial').value || null,
            chemical_id: parseInt(document.getElementById('fChemicalId').value) || null,
            capacity_range_min: parseFloat(document.getElementById('fCapMin').value) || null,
            capacity_range_max: parseFloat(document.getElementById('fCapMax').value) || null,
            capacity_unit: document.getElementById('fCapUnit').value || null,
            description: document.getElementById('fDesc').value.trim() || null,
            is_default: document.getElementById('fIsDefault').checked ? 1 : 0,
            sort_order: parseInt(document.getElementById('fSort').value) || 0,
            file_path: uploadedFileData.file_path,
            file_url: uploadedFileData.file_url,
            original_name: uploadedFileData.original_name,
            mime_type: uploadedFileData.mime_type,
            extension: uploadedFileData.extension,
            file_size: uploadedFileData.file_size,
        };

        var d = await apiFetch(API+'?action=save', { method:'POST', body:JSON.stringify(data) });
        if(d.success) {
            showToast('บันทึกโมเดลสำเร็จ!');
            resetUpload();
            loadStats();
            switchTab('models');
            refreshView();
        } else throw new Error(d.error);
    } catch(e) {
        showToast(e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> บันทึกโมเดล';
    }
}

function resetUpload() {
    uploadedFileData = null;
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadForm').style.display = 'none';
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('fileInput').value = '';
    document.getElementById('fLabel').value = '';
    document.getElementById('fDesc').value = '';
    document.getElementById('fCapMin').value = '';
    document.getElementById('fCapMax').value = '';
    document.getElementById('fIsDefault').checked = false;
    clearChemical('upload');
}

// ═══ Load Requests ═══
async function loadRequests() {
    var status = document.getElementById('reqStatusFilter').value;
    var url = API+'?action=requests';
    if(status) url += '&status=' + status;

    try {
        var d = await apiFetch(url);
        if(!d.success) throw new Error(d.error);
        var list = d.data;
        document.getElementById('tabReqCount').textContent = list.length;
        renderRequests(list);
    } catch(e) {
        document.getElementById('requestList').innerHTML = '<div class="m3d-empty"><i class="fas fa-exclamation-triangle"></i><p>'+esc(e.message)+'</p></div>';
    }
}

function renderRequests(requests) {
    var container = document.getElementById('requestList');
    if(!requests.length) {
        container.innerHTML = '<div class="m3d-empty"><i class="fas fa-clipboard-check"></i><p>ไม่มีคำขอโมเดล</p></div>';
        return;
    }

    container.innerHTML = requests.map(function(r) {
        var html = '<div class="req-card" style="margin-bottom:10px">';
        html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">';
        html += '<div>';
        html += '<div style="font-size:14px;font-weight:600;color:#333;margin-bottom:4px">'+esc(r.title)+'</div>';
        html += '<span class="req-status req-'+r.status+'">'+r.status+'</span> ';
        html += '<span class="req-priority '+r.priority+'">'+r.priority+'</span>';
        html += '</div>';
        html += '<div style="text-align:right;font-size:11px;color:#999">';
        html += '<div>#'+r.id+'</div>';
        html += '<div>'+fmtDate(r.requested_at)+'</div>';
        html += '</div></div>';
        html += '<div style="font-size:12px;color:#888;line-height:1.8">';
        html += '<div><i class="fas fa-box" style="width:14px;text-align:center;color:#aaa;font-size:10px"></i> '+(TYPES[r.container_type]||r.container_type)+' '+(r.container_material?'• '+r.container_material:'')+'</div>';
        if(r.chemical_name) html += '<div><i class="fas fa-flask" style="width:14px;text-align:center;color:#aaa;font-size:10px"></i> '+esc(r.chemical_name)+'</div>';
        if(r.description) html += '<div><i class="fas fa-comment" style="width:14px;text-align:center;color:#aaa;font-size:10px"></i> '+esc(r.description)+'</div>';
        html += '<div><i class="fas fa-user" style="width:14px;text-align:center;color:#aaa;font-size:10px"></i> '+esc((r.requester_first||'')+' '+(r.requester_last||''))+'</div>';
        html += '</div>';
        if(r.status!=='completed'&&r.status!=='rejected') {
            html += '<div style="display:flex;gap:6px;margin-top:10px;padding-top:8px;border-top:1px solid #f0f0f0">';
            html += '<select onchange="updateReqStatus('+r.id+',this.value)" style="padding:4px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:11px">';
            html += '<option value="">เปลี่ยนสถานะ...</option>';
            html += '<option value="approved">อนุมัติ</option>';
            html += '<option value="in_progress">กำลังดำเนินการ</option>';
            html += '<option value="completed">เสร็จสิ้น</option>';
            html += '<option value="rejected">ปฏิเสธ</option>';
            html += '</select></div>';
        }
        html += '</div>';
        return html;
    }).join('');
}

async function updateReqStatus(id, status) {
    if(!status) return;
    try {
        var d = await apiFetch(API+'?action=request_update', {
            method:'POST',
            body:JSON.stringify({id:id, status:status})
        });
        if(d.success) { showToast('อัปเดตสถานะสำเร็จ'); loadRequests(); loadStats(); }
        else throw new Error(d.error);
    } catch(e) { showToast(e.message,'error'); }
}

// ═══ Upload Mode Toggle ═══
function setUploadMode(mode) {
    document.getElementById('modeFile').classList.toggle('active', mode==='file');
    document.getElementById('modeEmbed').classList.toggle('active', mode==='embed');
    document.getElementById('uploadModeFile').style.display = mode==='file' ? 'block' : 'none';
    document.getElementById('uploadModeEmbed').style.display = mode==='embed' ? 'block' : 'none';
    if(mode==='embed') loadIframeConfigSilent();
}

// ═══ Embed / iFrame Logic ═══
async function loadIframeConfigSilent() {
    try {
        const d = await apiFetch(API+'?action=iframe_config');
        if(d.success) iframeConfig = d.data;
    } catch(e){}
}

function detectProvider(url) {
    if(!url) return '';
    const map = [
        ['kiriengine.app', 'Kiri Engine'],
        ['sketchfab.com', 'Sketchfab'],
        ['youtube.com', 'YouTube'], ['youtu.be', 'YouTube'],
        ['matterport.com', 'Matterport'],
        ['google.com/maps', 'Google Maps'],
        ['vimeo.com', 'Vimeo'],
        ['poly.cam', 'Polycam'],
        ['lumalabs.ai', 'Luma AI'],
    ];
    for(var i=0; i<map.length; i++) {
        if(url.indexOf(map[i][0]) >= 0) return map[i][1];
    }
    try { return new URL(url).hostname; } catch(e) { return ''; }
}

function autoConfigUrl(url) {
    if(!url || !iframeConfig) return url;
    // Replace sharemodel → embed (Kiri Engine specific)
    url = url.replace(/\/sharemodel(\/|$)/gi, '/embed$1');
    var parts = url.split('?');
    var base = parts[0];
    var origQuery = parts[1] || '';

    // Parse original params
    var paramMap = {};
    if(origQuery) {
        origQuery.split('&').forEach(function(p) {
            var kv = p.split('=');
            if(kv[0]) paramMap[kv[0]] = kv[1] || '';
        });
    }

    // Override with config
    paramMap['bg_theme'] = iframeConfig.kiri_bg_theme || 'transparent';
    paramMap['auto_spin_model'] = iframeConfig.kiri_auto_spin || '1';
    if(iframeConfig.default_params) {
        iframeConfig.default_params.split('&').forEach(function(p) {
            var kv = p.split('=');
            if(kv[0] && !(kv[0] in paramMap)) paramMap[kv[0]] = kv[1] || '';
        });
    }

    var newParams = Object.keys(paramMap).map(function(k){ return k+'='+paramMap[k]; }).join('&');
    return base + '?' + newParams;
}

function autoConfigCode(code) {
    if(!code) return code;
    return code.replace(/src\s*=\s*["']([^"']+)["']/gi, function(match, url) {
        return 'src="' + autoConfigUrl(url) + '"';
    });
}

function onKiriAutoConfigChange() {
    var isAuto = document.getElementById('embedKiriAuto').checked;
    if(isAuto) {
        var src = document.getElementById('embedSrc').value.trim();
        if(src && src.indexOf('kiriengine') >= 0) {
            document.getElementById('embedSrc').value = autoConfigUrl(src);
        }
        var code = document.getElementById('embedCode').value.trim();
        if(code && code.indexOf('kiriengine') >= 0) {
            document.getElementById('embedCode').value = autoConfigCode(code);
        }
        renderEmbedPreview();
    }
}

function onEmbedSrcInput() {
    var url = document.getElementById('embedSrc').value.trim();
    var isAuto = document.getElementById('embedKiriAuto').checked;

    // Auto-config Kiri
    if(isAuto && url.indexOf('kiriengine') >= 0) {
        url = autoConfigUrl(url);
        document.getElementById('embedSrc').value = url;
    }

    var provider = detectProvider(url);

    // Show provider badge
    var badge = document.getElementById('embedProviderBadge');
    if(provider) {
        badge.innerHTML = '<span class="m3d-provider-pill'+getProviderClass(provider)+'"><i class="fas fa-globe" style="font-size:9px"></i> '+esc(provider)+'</span>';
        // Auto-select provider dropdown if not set
        var sel = document.getElementById('embedProvider');
        if(!sel.value) {
            for(var i=0; i<sel.options.length; i++) {
                if(sel.options[i].value === provider) { sel.value = provider; break; }
            }
        }
    } else {
        badge.innerHTML = '';
    }

    // Debounced preview
    clearTimeout(embedPreviewTimer);
    if(url) {
        embedPreviewTimer = setTimeout(function() { renderEmbedPreview(); }, 700);
    } else {
        document.getElementById('embedPreviewBox').innerHTML = '<div class="empty"><i class="fas fa-play-circle"></i><span>กรอก URL ด้านบนเพื่อดูตัวอย่าง</span></div>';
    }
}

function onEmbedPasteCode() {
    setTimeout(function() {
        var code = document.getElementById('embedCode').value.trim();
        if(!code) return;

        var isAuto = document.getElementById('embedKiriAuto').checked;
        if(isAuto && code.indexOf('kiriengine') >= 0) {
            code = autoConfigCode(code);
            document.getElementById('embedCode').value = code;
        }

        // Extract src from iframe
        var srcMatch = code.match(/src\s*=\s*["']([^"']+)["']/i);
        var titleMatch = code.match(/title\s*=\s*["']([^"']+)["']/i);

        if(srcMatch && !document.getElementById('embedSrc').value.trim()) {
            document.getElementById('embedSrc').value = srcMatch[1];
            onEmbedSrcInput();
        }
        if(titleMatch && !document.getElementById('embedTitle').value.trim()) {
            document.getElementById('embedTitle').value = titleMatch[1];
        }
    }, 60);
}

function renderEmbedPreview() {
    var el = document.getElementById('embedPreviewBox');
    var src = document.getElementById('embedSrc').value.trim();
    if(!src) {
        el.innerHTML = '<div class="empty"><i class="fas fa-play-circle"></i><span>กรอก URL ด้านบนเพื่อดูตัวอย่าง</span></div>';
        return;
    }

    var code = document.getElementById('embedCode').value.trim();
    if(code && code.match(/<iframe[\s\S]*<\/iframe>/i)) {
        // Use the pasted code, fix size
        var fixed = code.replace(/style\s*=\s*["'][^"']*["']/gi, '')
                        .replace(/<iframe/i, '<iframe style="width:100%;height:100%;border:none;"');
        el.innerHTML = fixed;
    } else {
        // Create iframe from URL
        el.innerHTML = '<iframe src="'+esc(src)+'" style="width:100%;height:100%;border:none;" allowfullscreen allow="autoplay; fullscreen"></iframe>';
    }
}

async function saveEmbed() {
    var src = document.getElementById('embedSrc').value.trim();
    var title = document.getElementById('embedTitle').value.trim();
    var containerType = document.getElementById('embedContainerType').value;

    if(!src) { showToast('กรุณากรอก Embed URL','warning'); return; }
    if(!title) { showToast('กรุณากรอกชื่อโมเดล','warning'); return; }

    var isAuto = document.getElementById('embedKiriAuto').checked;
    // Final auto-config before save
    if(isAuto && src.indexOf('kiriengine') >= 0) {
        src = autoConfigUrl(src);
    }

    var provider = document.getElementById('embedProvider').value || detectProvider(src) || 'Unknown';
    var code = document.getElementById('embedCode').value.trim();
    if(isAuto && code && code.indexOf('kiriengine') >= 0) {
        code = autoConfigCode(code);
    }
    // Generate code if not provided
    if(!code) {
        var attrs = (isAuto && src.indexOf('kiriengine') >= 0 && iframeConfig.default_attrs)
            ? iframeConfig.default_attrs + ' width="'+(iframeConfig.width||640)+'" height="'+(iframeConfig.height||480)+'"'
            : 'allowfullscreen style="width:100%;height:100%;border:none;"';
        code = '<iframe src="' + src + '" ' + attrs + '><\/iframe>';
    }

    var btn = document.getElementById('saveEmbedBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    try {
        var data = {
            label: title,
            container_type: containerType,
            chemical_id: parseInt(document.getElementById('embedChemicalId').value) || null,
            description: document.getElementById('embedDesc').value.trim() || null,
            is_default: document.getElementById('embedIsDefault').checked ? 1 : 0,
            source_type: 'embed',
            embed_url: src,
            embed_code: code,
            embed_provider: provider,
            file_url: src,
            file_size: 0,
        };

        var d = await apiFetch(API+'?action=save', { method:'POST', body:JSON.stringify(data) });
        if(d.success) {
            showToast('บันทึก Embed สำเร็จ!');
            resetEmbed();
            loadStats();
            switchTab('models');
            refreshView();
        } else throw new Error(d.error);
    } catch(e) {
        showToast(e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> บันทึก Embed';
    }
}

function resetEmbed() {
    clearTimeout(embedPreviewTimer);
    document.getElementById('embedSrc').value = '';
    document.getElementById('embedCode').value = '';
    document.getElementById('embedTitle').value = '';
    document.getElementById('embedProvider').value = '';
    document.getElementById('embedContainerType').value = 'bottle';
    document.getElementById('embedDesc').value = '';
    document.getElementById('embedIsDefault').checked = false;
    document.getElementById('embedProviderBadge').innerHTML = '';
    document.getElementById('embedPreviewBox').innerHTML = '<div class="empty"><i class="fas fa-play-circle"></i><span>กรอก URL ด้านบนเพื่อดูตัวอย่าง</span></div>';
    clearChemical('embed');
}

// ═══ Keyboard ═══
document.addEventListener('keydown', function(e) {
    if(e.key==='Escape') {
        if(document.getElementById('editModal').classList.contains('show')) closeEditModal();
        else closePreview();
    }
});

// ═══ Handle deep links from CAS Map page ═══
(function() {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    const chemId = params.get('chemId');
    const cas = params.get('cas');
    const name = params.get('name');
    const view = params.get('view');
    const search = params.get('search');

    if(tab === 'upload' && chemId) {
        setTimeout(function() {
            switchTab('upload');
            selectChemical(chemId, cas || '', name || '', 'upload');
            showToast('เลือกสาร ' + (cas||'') + ' แล้ว — เลือกวิธีนำเข้าโมเดล', 'success');
        }, 300);
    } else if(view === 'cas' && search) {
        setTimeout(function() {
            setView('cas');
            document.getElementById('modelSearch').value = search;
            refreshView();
        }, 300);
    }
})();

// ═══ Initialize ═══
loadStats();
loadModels();
loadIframeConfigSilent();
</script>

<?php Layout::endContent(); ?>
</body>
</html>