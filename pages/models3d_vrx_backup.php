<?php
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
<?php Layout::pageHeader('จัดการโมเดล 3D บรรจุภัณฑ์', 'fas fa-cube', 'เชื่อมโยงโมเดล 3D จาก VRX Studio กับบรรจุภัณฑ์สารเคมี', 
    '<a href="/vrx/pages/gallery.php" target="_blank" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff"><i class="fas fa-external-link-alt"></i> VRX Studio</a>'); ?>

<style>
/* ── Stats ── */
.m3d-stat{display:flex;align-items:center;gap:14px;padding:16px}
.m3d-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px}
.m3d-stat-num{font-size:22px;font-weight:700;color:#333}
.m3d-stat-lbl{font-size:12px;color:#999}
/* ── Tab Strip ── */
.m3d-tabs{display:flex;gap:2px;border-bottom:2px solid #f0f0f0;margin-bottom:20px}
.m3d-tab{padding:10px 18px;font-size:13px;font-weight:600;color:#888;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;display:flex;align-items:center;gap:6px}
.m3d-tab:hover{color:#333}
.m3d-tab.active{color:#6C5CE7;border-bottom-color:#6C5CE7}
.m3d-tab .badge{background:#eee;color:#888;font-size:10px;padding:1px 6px;border-radius:8px;font-weight:700}
.m3d-tab.active .badge{background:#ede9fe;color:#6C5CE7}
/* ── Model Cards ── */
.m3d-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.m3d-card{border:1.5px solid #e5e7eb;border-radius:14px;overflow:hidden;transition:all .15s;background:#fff;position:relative}
.m3d-card:hover{border-color:#6C5CE7;box-shadow:0 6px 20px rgba(108,92,231,.1)}
.m3d-card.is-default{border-color:#6C5CE7;background:#faf5ff}
.m3d-card-preview{width:100%;height:180px;background:#f8f9fa;position:relative;overflow:hidden;border-bottom:1px solid #f0f0f0}
.m3d-card-preview iframe{width:100%;height:100%;border:none}
.m3d-card-preview .no-preview{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#ccc}
.m3d-card-preview .no-preview i{font-size:40px;margin-bottom:8px}
.m3d-card-body{padding:14px 16px}
.m3d-card-title{font-size:14px;font-weight:600;color:#333;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.m3d-card-title .tag{font-size:9px;padding:1px 6px;border-radius:6px;font-weight:700}
.m3d-card-title .tag-default{background:#ede9fe;color:#6C5CE7}
.m3d-card-title .tag-ar{background:#d1fae5;color:#059669}
.m3d-card-meta{font-size:12px;color:#888;line-height:1.8}
.m3d-card-meta i{width:14px;text-align:center;color:#aaa;font-size:10px}
.m3d-card-actions{display:flex;gap:6px;margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f0}
/* ── VRX Browser ── */
.vrx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.vrx-file{border:1.5px solid #e5e7eb;border-radius:12px;overflow:hidden;cursor:pointer;transition:all .15s;background:#fff}
.vrx-file:hover{border-color:#6C5CE7;box-shadow:0 4px 14px rgba(108,92,231,.1)}
.vrx-file.selected{border-color:#6C5CE7;background:#faf5ff;box-shadow:0 0 0 2px rgba(108,92,231,.2)}
.vrx-file.linked{opacity:.6;pointer-events:none}
.vrx-file-preview{width:100%;height:140px;background:#0f0f1a;display:flex;align-items:center;justify-content:center;overflow:hidden}
.vrx-file-preview iframe{width:100%;height:100%;border:none}
.vrx-file-preview .fallback{color:#555;font-size:36px}
.vrx-file-body{padding:10px 12px}
.vrx-file-name{font-size:13px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vrx-file-info{font-size:11px;color:#999;margin-top:2px}
.vrx-file-linked{position:absolute;top:8px;right:8px;background:#6C5CE7;color:#fff;font-size:9px;padding:2px 6px;border-radius:6px}
/* ── Request Cards ── */
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
/* ── Link Form ── */
.m3d-link-form{background:#faf5ff;border:1.5px solid #ddd6fe;border-radius:14px;padding:20px;margin-bottom:20px;animation:cmFadeIn .2s}
.m3d-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@keyframes cmFadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
</style>

<!-- ═══════ Stats Row ═══════ -->
<div id="statsRow" class="ci-auto-grid" style="margin-bottom:20px">
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#ede9fe"><i class="fas fa-cube" style="color:#6C5CE7"></i></div><div><p class="m3d-stat-num" id="sTotal">—</p><p class="m3d-stat-lbl">โมเดลทั้งหมด</p></div></div>
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#dbeafe"><i class="fas fa-link" style="color:#3b82f6"></i></div><div><p class="m3d-stat-num" id="sLinked">—</p><p class="m3d-stat-lbl">เชื่อมโยงแล้ว</p></div></div>
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#d1fae5"><i class="fas fa-database" style="color:#059669"></i></div><div><p class="m3d-stat-num" id="sVrx">—</p><p class="m3d-stat-lbl">VRX 3D Files</p></div></div>
    <div class="ci-card ci-card-body m3d-stat"><div class="m3d-stat-icon" style="background:#fef3c7"><i class="fas fa-clipboard-list" style="color:#d97706"></i></div><div><p class="m3d-stat-num" id="sPending">—</p><p class="m3d-stat-lbl">คำขอรอดำเนินการ</p></div></div>
</div>

<!-- ═══════ Tab Strip ═══════ -->
<div class="m3d-tabs">
    <div class="m3d-tab active" onclick="switchM3dTab('linked')"><i class="fas fa-link"></i> โมเดลที่เชื่อมโยง <span class="badge" id="tabLinkedCount">0</span></div>
    <div class="m3d-tab" onclick="switchM3dTab('vrx')"><i class="fas fa-folder-open"></i> เลือกจาก VRX Studio</div>
    <div class="m3d-tab" onclick="switchM3dTab('upload')"><i class="fas fa-cloud-upload-alt"></i> อัปโหลดโมเดล</div>
    <div class="m3d-tab" onclick="switchM3dTab('requests')"><i class="fas fa-clipboard-list"></i> คำขอโมเดล <span class="badge" id="tabReqCount">0</span></div>
</div>

<!-- ═══════ Tab: Linked Models ═══════ -->
<div class="m3d-tab-body" id="tabLinked">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px;position:relative">
            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;font-size:12px"></i>
            <input type="text" id="modelSearch" placeholder="ค้นหาโมเดล..." class="ci-input" style="padding-left:34px;width:100%" oninput="loadModels()">
        </div>
        <select id="modelTypeFilter" class="ci-select" style="width:140px" onchange="loadModels()">
            <option value="">ภาชนะทุกประเภท</option>
            <option value="bottle">Bottle / ขวด</option>
            <option value="vial">Vial / ขวดเล็ก</option>
            <option value="flask">Flask / ขวดวิทย์</option>
            <option value="canister">Canister / แกลลอน</option>
            <option value="cylinder">Cylinder / ถังแก๊ส</option>
            <option value="ampoule">Ampoule / หลอดแก้ว</option>
            <option value="drum">Drum / ถัง</option>
            <option value="bag">Bag / ถุง</option>
            <option value="gallon">Gallon / แกลลอน</option>
            <option value="other">Other / อื่นๆ</option>
        </select>
        <button onclick="showLinkForm()" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff"><i class="fas fa-plus"></i> เพิ่มการเชื่อมโยง</button>
    </div>
    <div id="linkFormArea"></div>
    <div id="modelGrid" class="m3d-grid"></div>
    <div id="modelEmpty" style="display:none;text-align:center;padding:40px;color:#999">
        <i class="fas fa-cube" style="font-size:48px;opacity:.3;display:block;margin-bottom:14px"></i>
        <p>ยังไม่มีโมเดล 3D ที่เชื่อมโยง</p>
        <p style="font-size:12px;margin-top:6px">กดปุ่ม "เพิ่มการเชื่อมโยง" หรือเลือกจาก VRX Studio</p>
    </div>
</div>

<!-- ═══════ Tab: VRX Browser ═══════ -->
<div class="m3d-tab-body" id="tabVrx" style="display:none">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px;position:relative">
            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;font-size:12px"></i>
            <input type="text" id="vrxSearch" placeholder="ค้นหาไฟล์ใน VRX Studio..." class="ci-input" style="padding-left:34px;width:100%">
        </div>
        <button onclick="loadVrxFiles()" class="ci-btn ci-btn-sm ci-btn-primary"><i class="fas fa-search"></i> ค้นหา</button>
        <a href="/vrx/pages/upload.php" target="_blank" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff"><i class="fas fa-upload"></i> Upload ใน VRX</a>
    </div>
    <div id="vrxGrid" class="vrx-grid"></div>
    <div id="vrxEmpty" style="display:none;text-align:center;padding:40px;color:#999">
        <i class="fas fa-database" style="font-size:48px;opacity:.3;display:block;margin-bottom:14px"></i>
        <p>ไม่พบไฟล์ 3D ใน VRX Studio</p>
    </div>
    <div id="vrxPagination" style="display:none;margin-top:16px;text-align:center">
        <button id="vrxPrevBtn" class="ci-btn ci-btn-sm" onclick="loadVrxFiles(vrxPage-1)"><i class="fas fa-chevron-left"></i></button>
        <span id="vrxPageInfo" style="margin:0 12px;font-size:13px;color:#888"></span>
        <button id="vrxNextBtn" class="ci-btn ci-btn-sm" onclick="loadVrxFiles(vrxPage+1)"><i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<!-- ═══════ Tab: Upload ═══════ -->
<div class="m3d-tab-body" id="tabUpload" style="display:none">
    <div class="ci-card ci-card-body" style="max-width:600px">
        <h3 style="font-size:15px;font-weight:600;margin-bottom:16px"><i class="fas fa-cloud-upload-alt" style="color:#6C5CE7;margin-right:6px"></i> อัปโหลดโมเดล 3D ใหม่ไปยัง VRX Studio</h3>
        <form id="uploadForm" enctype="multipart/form-data" onsubmit="handleUpload(event)">
            <div class="cm-field">
                <label>ชื่อโมเดล <span class="req">*</span></label>
                <input type="text" name="name" required placeholder="เช่น Glass Bottle 2.5L">
            </div>
            <div class="cm-field">
                <label>คำอธิบาย</label>
                <textarea name="description" rows="2" placeholder="รายละเอียดโมเดล..."></textarea>
            </div>
            <div class="cm-field">
                <label>ไฟล์ 3D <span class="req">*</span> <span style="font-size:11px;color:#999">(GLB, GLTF, OBJ, FBX, STL — สูงสุด 100MB)</span></label>
                <div id="dropZone" style="border:2px dashed #ddd;border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:all .15s;background:#fafafa" 
                     onclick="document.getElementById('fileInput').click()"
                     ondragover="event.preventDefault();this.style.borderColor='#6C5CE7';this.style.background='#faf5ff'"
                     ondragleave="this.style.borderColor='#ddd';this.style.background='#fafafa'"
                     ondrop="handleDrop(event)">
                    <i class="fas fa-cube" style="font-size:36px;color:#ccc;display:block;margin-bottom:10px"></i>
                    <p style="color:#888;font-size:13px" id="dropLabel">ลากไฟล์มาวาง หรือกดเพื่อเลือกไฟล์</p>
                    <input type="file" id="fileInput" name="model_file" accept=".glb,.gltf,.obj,.fbx,.stl" style="display:none" onchange="onFileSelected(this)">
                </div>
            </div>
            <div id="uploadPreview" style="display:none;margin-bottom:16px;border-radius:12px;overflow:hidden;height:200px;background:#0f0f1a"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="submit" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff" id="uploadBtn">
                    <i class="fas fa-upload"></i> อัปโหลดไปยัง VRX Studio
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════ Tab: Requests ═══════ -->
<div class="m3d-tab-body" id="tabRequests" style="display:none">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
        <select id="reqStatusFilter" class="ci-select" style="width:160px" onchange="loadRequests()">
            <option value="">สถานะทั้งหมด</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="rejected">Rejected</option>
        </select>
        <div style="flex:1"></div>
    </div>
    <div id="requestsList" style="display:grid;gap:12px"></div>
    <div id="reqEmpty" style="display:none;text-align:center;padding:40px;color:#999">
        <i class="fas fa-clipboard-check" style="font-size:48px;opacity:.3;display:block;margin-bottom:14px"></i>
        <p>ไม่มีคำขอโมเดล</p>
    </div>
</div>

<!-- ═══════ VRX Link Modal ═══════ -->
<div id="vrxLinkModal" class="ci-modal-bg">
<div class="ci-modal" style="max-width:640px">
    <div class="ci-modal-hdr">
        <h3><i class="fas fa-link" style="color:#6C5CE7"></i> เชื่อมโยงไฟล์ VRX กับบรรจุภัณฑ์</h3>
        <button class="ci-modal-close" onclick="closeVrxLink()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ci-modal-body" id="vrxLinkBody" style="max-height:80vh;overflow-y:auto"></div>
</div>
</div>

<!-- ═══════ Request Detail Modal ═══════ -->
<div id="reqDetailModal" class="ci-modal-bg">
<div class="ci-modal" style="max-width:560px">
    <div class="ci-modal-hdr">
        <h3><i class="fas fa-clipboard-list" style="color:#d97706"></i> รายละเอียดคำขอ</h3>
        <button class="ci-modal-close" onclick="closeReqDetail()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ci-modal-body" id="reqDetailBody" style="max-height:80vh;overflow-y:auto"></div>
</div>
</div>

<script>
const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const IS_MANAGER = <?php echo $isManager ? 'true' : 'false'; ?>;

const CONTAINER_TYPES = {bottle:'ขวด',vial:'ขวดเล็ก',flask:'ขวดวิทย์',canister:'แกลลอน',cylinder:'ถังแก๊ส',ampoule:'หลอดแก้ว',bag:'ถุง',gallon:'แกลลอน',drum:'ถัง',other:'อื่นๆ'};
const CONTAINER_MATERIALS = {glass:'แก้ว',plastic:'พลาสติก',metal:'โลหะ',hdpe:'HDPE',amber_glass:'แก้วสีชา',other:'อื่นๆ'};
const CONTAINER_TYPE_ICONS = {bottle:'fa-wine-bottle',vial:'fa-vial',flask:'fa-flask',canister:'fa-gas-pump',cylinder:'fa-database',ampoule:'fa-syringe',bag:'fa-bag-shopping',gallon:'fa-oil-can',drum:'fa-drum',other:'fa-box'};

let vrxPage = 1;
let selectedVrxFile = null;

// ── apiFetch ──
async function apiFetch(url, options = {}) {
    const t = document.cookie.split('; ').find(c => c.startsWith('auth_token='))?.split('=')[1];
    const h = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    if (t) h['Authorization'] = 'Bearer ' + t;
    // Don't set Content-Type for FormData
    if (options.body instanceof FormData) delete h['Content-Type'];
    const r = await fetch(url, { ...options, headers: h });
    if (!r.ok && r.status === 401) { window.location.href = '/v1/'; throw new Error('Unauthorized'); }
    return r.json();
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;top:24px;right:24px;z-index:10000;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:500;color:#fff;animation:cmFadeIn .2s;box-shadow:0 4px 20px rgba(0,0,0,.2);max-width:400px`;
    t.style.background = type === 'success' ? '#059669' : type === 'error' ? '#dc2626' : '#d97706';
    t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':type==='error'?'exclamation-circle':'info-circle'}" style="margin-right:6px"></i>${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function esc(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// ═══════════════════════════════════════════════════════
// Tab Switching
// ═══════════════════════════════════════════════════════
function switchM3dTab(tab) {
    document.querySelectorAll('.m3d-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.m3d-tab-body').forEach(b => b.style.display = 'none');
    
    const tabMap = { linked: 'tabLinked', vrx: 'tabVrx', upload: 'tabUpload', requests: 'tabRequests' };
    const idx = Object.keys(tabMap).indexOf(tab);
    document.querySelectorAll('.m3d-tab')[idx]?.classList.add('active');
    document.getElementById(tabMap[tab]).style.display = 'block';

    if (tab === 'vrx') loadVrxFiles();
    if (tab === 'requests') loadRequests();
}

// ═══════════════════════════════════════════════════════
// Stats
// ═══════════════════════════════════════════════════════
async function loadStats() {
    try {
        const d = await apiFetch('/v1/api/models3d.php?action=stats');
        if (d.success) {
            document.getElementById('sTotal').textContent = d.data.total_models ?? 0;
            document.getElementById('sLinked').textContent = d.data.specific_links ?? 0;
            document.getElementById('sVrx').textContent = d.data.vrx_3d_models ?? 0;
            document.getElementById('sPending').textContent = d.data.pending_requests ?? 0;
            document.getElementById('tabLinkedCount').textContent = d.data.total_models ?? 0;
            document.getElementById('tabReqCount').textContent = d.data.pending_requests ?? 0;
        }
    } catch (e) { console.error(e); }
}

// ═══════════════════════════════════════════════════════
// Linked Models
// ═══════════════════════════════════════════════════════
async function loadModels() {
    const search = document.getElementById('modelSearch').value;
    const type = document.getElementById('modelTypeFilter').value;
    let url = '/v1/api/models3d.php?action=list';
    if (search) url += '&search=' + encodeURIComponent(search);
    if (type) url += '&container_type=' + type;

    try {
        const d = await apiFetch(url);
        const grid = document.getElementById('modelGrid');
        const empty = document.getElementById('modelEmpty');

        if (!d.success || !d.data.length) {
            grid.innerHTML = '';
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';

        grid.innerHTML = d.data.map(m => {
            const icon = CONTAINER_TYPE_ICONS[m.container_type] || 'fa-box';
            const typeLabel = CONTAINER_TYPES[m.container_type] || m.container_type;
            const matLabel = CONTAINER_MATERIALS[m.container_material] || m.container_material || '—';
            const viewerUrl = m.vrx_file_url
                ? `/vrx/pages/viewer.php?src=${encodeURIComponent(m.vrx_file_url)}&transparent=1`
                : '';

            return `<div class="m3d-card ${m.is_default ? 'is-default' : ''}">
                <div class="m3d-card-preview">
                    ${viewerUrl
                        ? `<iframe src="${viewerUrl}" loading="lazy" title="3D Preview"></iframe>`
                        : `<div class="no-preview"><i class="fas fa-cube"></i><span style="font-size:12px">ไม่มีไฟล์ 3D</span></div>`}
                </div>
                <div class="m3d-card-body">
                    <div class="m3d-card-title">
                        ${esc(m.label)}
                        ${m.is_default ? '<span class="tag tag-default">ค่าเริ่มต้น</span>' : ''}
                        ${m.ar_enabled ? '<span class="tag tag-ar">AR</span>' : ''}
                    </div>
                    <div class="m3d-card-meta">
                        <div><i class="fas ${icon}"></i> ${typeLabel} · ${matLabel}</div>
                        ${m.capacity_range_min || m.capacity_range_max ? `<div><i class="fas fa-ruler"></i> ${m.capacity_range_min || '—'} — ${m.capacity_range_max || '—'} ${m.capacity_unit || ''}</div>` : ''}
                        ${m.packaging_label ? `<div><i class="fas fa-box-open"></i> ${esc(m.packaging_label)}</div>` : '<div><i class="fas fa-globe"></i> โมเดลทั่วไป (Generic)</div>'}
                        ${m.chemical_name ? `<div><i class="fas fa-flask"></i> ${esc(m.chemical_name)}</div>` : ''}
                        <div><i class="fas fa-clock"></i> ${new Date(m.created_at).toLocaleDateString('th-TH')}</div>
                    </div>
                    <div class="m3d-card-actions">
                        <button onclick="editModel(${m.id})" class="ci-btn ci-btn-sm" style="flex:1;font-size:11px"><i class="fas fa-edit"></i> แก้ไข</button>
                        <a href="/vrx/pages/viewer.php?src=${encodeURIComponent(m.vrx_file_url || '')}" target="_blank" class="ci-btn ci-btn-sm" style="font-size:11px" title="เปิดใน VRX"><i class="fas fa-expand"></i></a>
                        <button onclick="deleteModel(${m.id})" class="ci-btn ci-btn-sm" style="font-size:11px;color:#ef4444" title="ลบ"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>`;
        }).join('');
    } catch (e) {
        console.error(e);
        showToast('โหลดข้อมูลผิดพลาด', 'error');
    }
}

// ═══════════════════════════════════════════════════════
// Link Form
// ═══════════════════════════════════════════════════════
function showLinkForm(editId) {
    selectedVrxFile = null;
    const area = document.getElementById('linkFormArea');
    area.innerHTML = `
    <div class="m3d-link-form">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <div style="font-size:14px;font-weight:600;color:#5b21b6"><i class="fas fa-link"></i> เชื่อมโยงโมเดล 3D กับประเภทบรรจุภัณฑ์</div>
            <button onclick="document.getElementById('linkFormArea').innerHTML=''" style="background:none;border:none;cursor:pointer;color:#aaa"><i class="fas fa-times"></i></button>
        </div>
        <form id="linkModelForm" onsubmit="submitModelLink(event)">
            <div class="cm-field">
                <label>ชื่อโมเดล <span class="req">*</span></label>
                <input type="text" name="label" required placeholder="เช่น ขวดแก้ว 2.5L">
            </div>
            <div class="m3d-form-grid">
                <div class="cm-field">
                    <label>ประเภทภาชนะ <span class="req">*</span></label>
                    <select name="container_type" required>
                        ${Object.entries(CONTAINER_TYPES).map(([k,v]) => `<option value="${k}">${v}</option>`).join('')}
                    </select>
                </div>
                <div class="cm-field">
                    <label>วัสดุ</label>
                    <select name="container_material">
                        <option value="">— ทุกวัสดุ —</option>
                        ${Object.entries(CONTAINER_MATERIALS).map(([k,v]) => `<option value="${k}">${v}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="m3d-form-grid">
                <div class="cm-field">
                    <label>ขนาดต่ำสุด (capacity min)</label>
                    <input type="number" name="capacity_range_min" step="any" min="0" placeholder="เช่น 0.5">
                </div>
                <div class="cm-field">
                    <label>ขนาดสูงสุด (capacity max)</label>
                    <input type="number" name="capacity_range_max" step="any" min="0" placeholder="เช่น 5">
                </div>
            </div>
            <div class="cm-field">
                <label>หน่วย</label>
                <select name="capacity_unit">
                    <option value="">—</option>
                    ${['mL','L','g','kg','mg','oz','lb','gal'].map(u => `<option value="${u}">${u}</option>`).join('')}
                </select>
            </div>

            <!-- VRX File Picker -->
            <div class="cm-field">
                <label><i class="fas fa-cube" style="color:#6C5CE7;margin-right:4px"></i> เลือกไฟล์ 3D จาก VRX Studio <span class="req">*</span></label>
                <div style="display:flex;gap:8px">
                    <input type="text" id="vrxFileSearchInline" placeholder="ค้นหาไฟล์ 3D..." class="ci-input" style="flex:1">
                    <button type="button" onclick="searchVrxInline()" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff"><i class="fas fa-search"></i></button>
                </div>
                <div id="vrxInlineResults" style="margin-top:8px;max-height:200px;overflow-y:auto"></div>
                <input type="hidden" name="vrx_file_id" id="selectedVrxFileId">
                <input type="hidden" name="vrx_file_uuid" id="selectedVrxFileUuid">
                <input type="hidden" name="vrx_file_url" id="selectedVrxFileUrl">
                <div id="selectedVrxPreview" style="display:none;margin-top:8px;padding:8px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:12px">
                    <i class="fas fa-check-circle" style="color:#22c55e;margin-right:4px"></i> <span id="selectedVrxName"></span>
                </div>
            </div>

            <div class="cm-field">
                <label>คำอธิบาย</label>
                <textarea name="description" rows="2" placeholder="รายละเอียดเพิ่มเติม..."></textarea>
            </div>
            <div class="m3d-form-grid">
                <div class="cm-field" style="margin-bottom:0">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="is_default" value="1" style="width:16px;height:16px;accent-color:#6C5CE7">
                        <span style="font-size:12px;color:#5b21b6">ตั้งเป็นโมเดลเริ่มต้น</span>
                    </label>
                </div>
                <div class="cm-field" style="margin-bottom:0">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="ar_enabled" value="1" style="width:16px;height:16px;accent-color:#059669">
                        <span style="font-size:12px;color:#065f46">เปิดใช้ AR</span>
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;padding-top:12px;border-top:1px solid #ddd6fe">
                <button type="button" onclick="document.getElementById('linkFormArea').innerHTML=''" class="ci-btn ci-btn-sm">ยกเลิก</button>
                <button type="submit" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff" id="linkSubmitBtn">
                    <i class="fas fa-link"></i> สร้างการเชื่อมโยง
                </button>
            </div>
        </form>
    </div>`;
    area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function searchVrxInline() {
    const q = document.getElementById('vrxFileSearchInline').value;
    const res = document.getElementById('vrxInlineResults');
    res.innerHTML = '<div style="text-align:center;padding:12px;color:#999"><i class="fas fa-spinner fa-spin"></i> กำลังค้นหา...</div>';

    try {
        const d = await apiFetch('/v1/api/models3d.php?action=vrx_search&q=' + encodeURIComponent(q));
        if (!d.success || !d.data.length) {
            res.innerHTML = '<div style="text-align:center;padding:12px;color:#999">ไม่พบไฟล์</div>';
            return;
        }
        res.innerHTML = d.data.map(f => `
            <div style="display:flex;align-items:center;gap:10px;padding:8px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:4px;cursor:pointer;transition:all .1s;${f.is_linked ? 'opacity:.5' : ''}"
                 onclick="${f.is_linked ? '' : `selectVrxFile(${f.id},'${esc(f.uuid)}','${esc(f.file_url || '')}','${esc(f.name)}')`}"
                 onmouseover="this.style.borderColor='#6C5CE7'" onmouseout="this.style.borderColor='#e5e7eb'">
                <div style="width:50px;height:50px;background:#0f0f1a;border-radius:6px;overflow:hidden;flex-shrink:0">
                    ${f.file_url ? `<iframe src="/vrx/pages/viewer.php?src=${encodeURIComponent(f.file_url)}&transparent=1" style="width:50px;height:50px;border:none;pointer-events:none"></iframe>` : '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#555"><i class="fas fa-cube"></i></div>'}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:12px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(f.name)}</div>
                    <div style="font-size:10px;color:#999">${f.extension?.toUpperCase() || '—'} · ${formatFileSize(f.file_size)}</div>
                </div>
                ${f.is_linked ? '<span style="font-size:9px;background:#ede9fe;color:#6C5CE7;padding:2px 6px;border-radius:4px">เชื่อมแล้ว</span>' : '<i class="fas fa-check-circle" style="color:#ccc;font-size:14px"></i>'}
            </div>
        `).join('');
    } catch (e) {
        res.innerHTML = '<div style="color:#dc2626;padding:8px;font-size:12px">Error: ' + e.message + '</div>';
    }
}

function selectVrxFile(id, uuid, url, name) {
    document.getElementById('selectedVrxFileId').value = id;
    document.getElementById('selectedVrxFileUuid').value = uuid;
    document.getElementById('selectedVrxFileUrl').value = url;
    document.getElementById('selectedVrxName').textContent = name;
    document.getElementById('selectedVrxPreview').style.display = 'block';
    document.getElementById('vrxInlineResults').innerHTML = '';
    selectedVrxFile = { id, uuid, url, name };
}

async function submitModelLink(e) {
    e.preventDefault();
    const btn = document.getElementById('linkSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    const form = document.getElementById('linkModelForm');
    const data = Object.fromEntries(new FormData(form));
    data.is_default = data.is_default ? 1 : 0;
    data.ar_enabled = data.ar_enabled ? 1 : 0;

    if (!data.vrx_file_id) {
        showToast('กรุณาเลือกไฟล์ 3D จาก VRX Studio', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-link"></i> สร้างการเชื่อมโยง';
        return;
    }

    try {
        const d = await apiFetch('/v1/api/models3d.php?action=save', {
            method: 'POST', body: JSON.stringify(data)
        });
        if (d.success) {
            showToast('สร้างการเชื่อมโยงสำเร็จ');
            document.getElementById('linkFormArea').innerHTML = '';
            loadModels();
            loadStats();
        } else throw new Error(d.error);
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-link"></i> สร้างการเชื่อมโยง';
        showToast(err.message, 'error');
    }
}

async function deleteModel(id) {
    if (!confirm('ต้องการลบการเชื่อมโยงนี้?')) return;
    try {
        const d = await apiFetch('/v1/api/models3d.php?action=delete&id=' + id, { method: 'DELETE' });
        if (d.success) { showToast('ลบสำเร็จ'); loadModels(); loadStats(); }
        else throw new Error(d.error);
    } catch (e) { showToast(e.message, 'error'); }
}

function editModel(id) {
    // For now, open in link form pattern — could be enhanced
    showToast('ฟีเจอร์แก้ไข — กำลังพัฒนา', 'warning');
}

// ═══════════════════════════════════════════════════════
// VRX File Browser
// ═══════════════════════════════════════════════════════
async function loadVrxFiles(page) {
    vrxPage = page || 1;
    const search = document.getElementById('vrxSearch').value;
    let url = `/v1/api/models3d.php?action=vrx_files&page=${vrxPage}`;
    if (search) url += '&search=' + encodeURIComponent(search);

    const grid = document.getElementById('vrxGrid');
    grid.innerHTML = '<div style="text-align:center;padding:40px;color:#999"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    try {
        const d = await apiFetch(url);
        const empty = document.getElementById('vrxEmpty');
        const pag = document.getElementById('vrxPagination');

        if (!d.success || !d.data.length) {
            grid.innerHTML = '';
            empty.style.display = 'block';
            pag.style.display = 'none';
            return;
        }
        empty.style.display = 'none';

        grid.innerHTML = d.data.map(f => {
            const viewerUrl = f.file_url
                ? `/vrx/pages/viewer.php?src=${encodeURIComponent(f.file_url)}&transparent=1`
                : '';
            return `<div class="vrx-file ${f.is_linked ? 'linked' : ''}" style="position:relative" onclick="${f.is_linked ? '' : `openVrxLinkDialog(${f.id},'${esc(f.uuid)}','${esc(f.file_url || '')}','${esc(f.name)}')`}">
                ${f.is_linked ? '<div class="vrx-file-linked"><i class="fas fa-link"></i> เชื่อมแล้ว</div>' : ''}
                <div class="vrx-file-preview">
                    ${viewerUrl
                        ? `<iframe src="${viewerUrl}" loading="lazy" style="pointer-events:none"></iframe>`
                        : `<div class="fallback"><i class="fas fa-cube"></i></div>`}
                </div>
                <div class="vrx-file-body">
                    <div class="vrx-file-name" title="${esc(f.name)}">${esc(f.name)}</div>
                    <div class="vrx-file-info">${f.extension?.toUpperCase() || '—'} · ${formatFileSize(f.file_size)} · ${f.uploader || '—'}</div>
                </div>
            </div>`;
        }).join('');

        // Pagination
        if (d.pagination.total_pages > 1) {
            pag.style.display = 'block';
            document.getElementById('vrxPageInfo').textContent = `หน้า ${d.pagination.page} / ${d.pagination.total_pages}`;
            document.getElementById('vrxPrevBtn').disabled = d.pagination.page <= 1;
            document.getElementById('vrxNextBtn').disabled = d.pagination.page >= d.pagination.total_pages;
        } else {
            pag.style.display = 'none';
        }
    } catch (e) {
        grid.innerHTML = `<div style="color:#dc2626;padding:20px">Error: ${e.message}</div>`;
    }
}

function openVrxLinkDialog(fileId, uuid, url, name) {
    const modal = document.getElementById('vrxLinkModal');
    const body = document.getElementById('vrxLinkBody');

    body.innerHTML = `
    <div style="text-align:center;margin-bottom:16px">
        <div style="height:200px;background:#0f0f1a;border-radius:12px;overflow:hidden;margin-bottom:12px">
            ${url ? `<iframe src="/vrx/pages/viewer.php?src=${encodeURIComponent(url)}" style="width:100%;height:100%;border:none"></iframe>` : ''}
        </div>
        <h4 style="font-size:14px;font-weight:600">${esc(name)}</h4>
    </div>
    <form onsubmit="submitQuickLink(event,${fileId},'${uuid}','${esc(url)}')">
        <div class="cm-field">
            <label>ชื่อที่แสดง <span class="req">*</span></label>
            <input type="text" name="label" required placeholder="เช่น ขวดแก้ว 2.5L" value="${esc(name)}">
        </div>
        <div class="m3d-form-grid">
            <div class="cm-field">
                <label>ประเภทภาชนะ <span class="req">*</span></label>
                <select name="container_type" required>
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
        <div class="cm-field" style="margin-bottom:0">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_default" value="1" style="width:16px;height:16px;accent-color:#6C5CE7">
                <span style="font-size:12px;color:#5b21b6">ตั้งเป็นโมเดลเริ่มต้น</span>
            </label>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid #f0f0f0">
            <button type="button" onclick="closeVrxLink()" class="ci-btn ci-btn-sm">ยกเลิก</button>
            <button type="submit" class="ci-btn ci-btn-sm" style="background:#6C5CE7;color:#fff" id="quickLinkBtn">
                <i class="fas fa-link"></i> เชื่อมโยง
            </button>
        </div>
    </form>`;

    modal.style.display = 'flex';
}

async function submitQuickLink(e, fileId, uuid, url) {
    e.preventDefault();
    const btn = document.getElementById('quickLinkBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const form = e.target;
    const data = Object.fromEntries(new FormData(form));
    data.vrx_file_id = fileId;
    data.vrx_file_uuid = uuid;
    data.vrx_file_url = url;
    data.is_default = data.is_default ? 1 : 0;

    try {
        const d = await apiFetch('/v1/api/models3d.php?action=save', {
            method: 'POST', body: JSON.stringify(data)
        });
        if (d.success) {
            showToast('เชื่อมโยงสำเร็จ');
            closeVrxLink();
            loadVrxFiles(vrxPage);
            loadModels();
            loadStats();
        } else throw new Error(d.error);
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-link"></i> เชื่อมโยง';
        showToast(err.message, 'error');
    }
}

function closeVrxLink() { document.getElementById('vrxLinkModal').style.display = 'none'; }

// ═══════════════════════════════════════════════════════
// Upload
// ═══════════════════════════════════════════════════════
function handleDrop(e) {
    e.preventDefault();
    e.currentTarget.style.borderColor = '#ddd';
    e.currentTarget.style.background = '#fafafa';
    if (e.dataTransfer.files.length) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        onFileSelected(document.getElementById('fileInput'));
    }
}

function onFileSelected(input) {
    if (!input.files.length) return;
    const f = input.files[0];
    document.getElementById('dropLabel').innerHTML = `<i class="fas fa-check-circle" style="color:#22c55e"></i> ${esc(f.name)} (${formatFileSize(f.size)})`;
    
    // Show 3D preview for GLB/GLTF
    const ext = f.name.split('.').pop().toLowerCase();
    if (['glb', 'gltf'].includes(ext)) {
        const url = URL.createObjectURL(f);
        const preview = document.getElementById('uploadPreview');
        // Can't use VRX viewer for blob URLs, but show a placeholder
        preview.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6C5CE7">
            <div style="text-align:center"><i class="fas fa-cube fa-3x" style="margin-bottom:8px"></i><br><span style="font-size:12px">${esc(f.name)}</span></div>
        </div>`;
        preview.style.display = 'block';
    }
}

async function handleUpload(e) {
    e.preventDefault();
    const btn = document.getElementById('uploadBtn');
    const fileInput = document.getElementById('fileInput');
    if (!fileInput.files.length) { showToast('กรุณาเลือกไฟล์', 'error'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังอัปโหลด...';

    const formData = new FormData(document.getElementById('uploadForm'));
    
    try {
        const t = document.cookie.split('; ').find(c => c.startsWith('auth_token='))?.split('=')[1];
        const headers = {};
        if (t) headers['Authorization'] = 'Bearer ' + t;

        const r = await fetch('/v1/api/models3d.php?action=upload_to_vrx', {
            method: 'POST',
            headers,
            body: formData
        });
        const d = await r.json();
        if (d.success) {
            showToast('อัปโหลดสำเร็จ! ไฟล์พร้อมใช้งานใน VRX Studio');
            document.getElementById('uploadForm').reset();
            document.getElementById('dropLabel').innerHTML = 'ลากไฟล์มาวาง หรือกดเพื่อเลือกไฟล์';
            document.getElementById('uploadPreview').style.display = 'none';
            loadStats();
        } else throw new Error(d.error);
    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload"></i> อัปโหลดไปยัง VRX Studio';
    }
}

// ═══════════════════════════════════════════════════════
// Requests
// ═══════════════════════════════════════════════════════
async function loadRequests() {
    const status = document.getElementById('reqStatusFilter').value;
    let url = '/v1/api/models3d.php?action=requests';
    if (status) url += '&status=' + status;

    try {
        const d = await apiFetch(url);
        const list = document.getElementById('requestsList');
        const empty = document.getElementById('reqEmpty');

        if (!d.success || !d.data.length) {
            list.innerHTML = '';
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';

        const STATUS_LABELS = {pending:'รอดำเนินการ',approved:'อนุมัติแล้ว',in_progress:'กำลังดำเนินการ',completed:'เสร็จสิ้น',rejected:'ปฏิเสธ'};

        list.innerHTML = d.data.map(r => {
            const typeLabel = CONTAINER_TYPES[r.container_type] || r.container_type;
            return `<div class="req-card">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
                    <div>
                        <div style="font-size:14px;font-weight:600;color:#333">${esc(r.title)}</div>
                        <div style="font-size:12px;color:#999;margin-top:2px">${typeLabel} · ${r.requester_first || ''} ${r.requester_last || ''} · ${new Date(r.requested_at).toLocaleDateString('th-TH')}</div>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center">
                        <span class="req-priority ${r.priority}">${r.priority}</span>
                        <span class="req-status req-${r.status}">${STATUS_LABELS[r.status] || r.status}</span>
                    </div>
                </div>
                ${r.description ? `<p style="font-size:12px;color:#666;margin-bottom:8px">${esc(r.description)}</p>` : ''}
                ${r.chemical_name ? `<div style="font-size:11px;color:#888"><i class="fas fa-flask" style="width:14px;text-align:center"></i> ${esc(r.chemical_name)} ${r.cas_number ? '(' + esc(r.cas_number) + ')' : ''}</div>` : ''}
                ${IS_MANAGER ? `<div style="display:flex;gap:6px;margin-top:10px;padding-top:8px;border-top:1px solid #f0f0f0">
                    ${r.status === 'pending' ? `
                        <button onclick="updateRequestStatus(${r.id},'approved')" class="ci-btn ci-btn-sm" style="font-size:11px;background:#dbeafe;color:#2563eb"><i class="fas fa-check"></i> อนุมัติ</button>
                        <button onclick="updateRequestStatus(${r.id},'rejected')" class="ci-btn ci-btn-sm" style="font-size:11px;color:#dc2626"><i class="fas fa-times"></i> ปฏิเสธ</button>
                    ` : ''}
                    ${r.status === 'approved' ? `
                        <button onclick="updateRequestStatus(${r.id},'in_progress')" class="ci-btn ci-btn-sm" style="font-size:11px;background:#ede9fe;color:#6C5CE7"><i class="fas fa-play"></i> เริ่มดำเนินการ</button>
                    ` : ''}
                    ${r.status === 'in_progress' ? `
                        <button onclick="updateRequestStatus(${r.id},'completed')" class="ci-btn ci-btn-sm" style="font-size:11px;background:#d1fae5;color:#059669"><i class="fas fa-check-double"></i> เสร็จสิ้น</button>
                    ` : ''}
                </div>` : ''}
            </div>`;
        }).join('');
    } catch (e) {
        console.error(e);
        showToast('โหลดข้อมูลผิดพลาด', 'error');
    }
}

async function updateRequestStatus(id, status) {
    try {
        const d = await apiFetch('/v1/api/models3d.php?action=request_update', {
            method: 'POST', body: JSON.stringify({ id, status })
        });
        if (d.success) { showToast('อัปเดตสถานะสำเร็จ'); loadRequests(); loadStats(); }
        else throw new Error(d.error);
    } catch (e) { showToast(e.message, 'error'); }
}

function closeReqDetail() { document.getElementById('reqDetailModal').style.display = 'none'; }

// ═══════════════════════════════════════════════════════
// Utilities
// ═══════════════════════════════════════════════════════
function formatFileSize(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1024/1024).toFixed(1) + ' MB';
}

// ═══════════════════════════════════════════════════════
// Init
// ═══════════════════════════════════════════════════════
loadStats();
loadModels();
</script>

<?php Layout::endContent(); ?>
</body>
</html>
