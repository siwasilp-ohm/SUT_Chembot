<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
Layout::head($lang==='th'?'โปรไฟล์':'Profile');
?>
<style>
/* ═══════════ PROFILE HERO ═══════════ */
.pf-hero{position:relative;background:linear-gradient(135deg,#1a8a5c 0%,#0d5c3a 50%,#1a1a2e 100%);border-radius:10px;overflow:hidden;margin-bottom:20px}
.pf-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
.pf-hero-inner{position:relative;padding:32px 28px 24px;display:flex;align-items:flex-end;gap:20px;z-index:1}
.pf-avatar-wrap{position:relative;flex-shrink:0}
.pf-avatar{width:100px;height:100px;border-radius:50%;border:4px solid rgba(255,255,255,.3);background:#2d2d2d;display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.pf-avatar img{width:100%;height:100%;object-fit:cover}
.pf-avatar-letter{font-size:38px;font-weight:700;color:#fff}
.pf-avatar-edit{position:absolute;bottom:2px;right:2px;width:30px;height:30px;border-radius:50%;background:var(--accent);border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;font-size:11px;transition:transform .15s;box-shadow:0 2px 8px rgba(0,0,0,.3)}
.pf-avatar-edit:hover{transform:scale(1.12)}
.pf-hero-info{flex:1;min-width:0;color:#fff;padding-bottom:4px}
.pf-name{font-size:22px;font-weight:700;line-height:1.2;text-shadow:0 1px 4px rgba(0,0,0,.2)}
.pf-role{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.15);backdrop-filter:blur(6px);padding:3px 12px;border-radius:20px;font-size:11px;font-weight:600;margin-top:6px;color:rgba(255,255,255,.9)}
.pf-role i{font-size:10px}
.pf-meta{display:flex;gap:20px;margin-top:8px;font-size:12px;color:rgba(255,255,255,.65);flex-wrap:wrap}
.pf-meta span i{margin-right:4px}

/* ═══════════ TABS ═══════════ */
.pf-tabs{display:flex;gap:2px;border-bottom:2px solid var(--border);margin-bottom:20px;overflow-x:auto}
.pf-tab{padding:10px 18px;font-size:13px;font-weight:600;color:var(--c3);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;white-space:nowrap;display:flex;align-items:center;gap:6px}
.pf-tab:hover{color:var(--c1)}
.pf-tab.active{color:var(--accent);border-bottom-color:var(--accent)}
.pf-tab i{font-size:12px}
.pf-panel{display:none}.pf-panel.active{display:block}

/* ═══════════ FORM SECTIONS ═══════════ */
.pf-section{margin-bottom:24px}
.pf-section-title{font-size:13px;font-weight:700;color:var(--accent-d);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.pf-section-title::after{content:'';flex:1;height:1px;background:var(--border)}
.pf-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.pf-row.single{grid-template-columns:1fr}
.pf-row.triple{grid-template-columns:1fr 1fr 1fr}

/* ═══════════ ORG TREE DISPLAY ═══════════ */
.org-tree{display:flex;flex-direction:column;gap:0}
.org-node{display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:13px;position:relative}
.org-node:not(:last-child)::after{content:'';position:absolute;left:18px;top:32px;bottom:-8px;width:2px;background:var(--accent-l)}
.org-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;border:2px solid var(--accent)}
.org-node:last-child .org-dot{background:var(--accent)}
.org-label-type{font-size:10px;color:var(--c3);font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.org-label-name{font-weight:600;color:var(--c1)}
.org-indent{padding-left:28px}
.org-indent-2{padding-left:56px}

/* ═══════════ PASSWORD STRENGTH ═══════════ */
.pw-strength{height:4px;border-radius:2px;background:#eee;margin-top:6px;overflow:hidden}
.pw-strength-bar{height:100%;width:0;border-radius:2px;transition:all .3s}
.pw-strength-bar.weak{width:33%;background:#d9534f}
.pw-strength-bar.medium{width:66%;background:#f0ad4e}
.pw-strength-bar.strong{width:100%;background:#5cb85c}
.pw-hint{font-size:11px;margin-top:3px}

/* ═══════════ TOAST ═══════════ */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);background:#1a1a2e;color:#fff;padding:12px 24px;border-radius:8px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;z-index:9999;opacity:0;transition:all .3s ease}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.toast.success{background:#0d6832}.toast.error{background:#c62828}

/* ═══════════ SAVE BAR ═══════════ */
.pf-save-bar{position:sticky;bottom:0;background:var(--bg);padding:14px 0;border-top:2px solid var(--accent-l);display:none;justify-content:flex-end;align-items:center;gap:10px;z-index:5}
.pf-save-bar.show{display:flex}

/* ═══════════ AVATAR MODAL ═══════════ */
.avatar-modal-body{text-align:center;padding:24px}
.avatar-preview-area{width:200px;height:200px;border-radius:50%;overflow:hidden;margin:16px auto;border:3px solid var(--border);background:#f5f5f5;display:flex;align-items:center;justify-content:center}
.avatar-preview-area img{width:100%;height:100%;object-fit:cover}
.avatar-drop-zone{border:2px dashed var(--border);border-radius:12px;padding:32px 20px;cursor:pointer;transition:all .2s;color:var(--c3)}
.avatar-drop-zone:hover,.avatar-drop-zone.dragover{border-color:var(--accent);background:var(--accent-l);color:var(--accent)}
.avatar-drop-zone i{font-size:36px;display:block;margin-bottom:8px}

/* ═══════════ DEFAULT AVATAR GALLERY ═══════════ */
.av-divider{display:flex;align-items:center;gap:10px;margin:20px 0 16px;font-size:12px;font-weight:600;color:var(--c3);text-transform:uppercase;letter-spacing:.5px}
.av-divider::before,.av-divider::after{content:'';flex:1;height:1px;background:var(--border)}
.av-gallery{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.av-gallery-item{position:relative;width:100%;aspect-ratio:1;border-radius:50%;overflow:hidden;border:3px solid var(--border);cursor:pointer;transition:all .2s;background:#f5f5f5}
.av-gallery-item:hover{border-color:var(--accent);transform:scale(1.08);box-shadow:0 4px 12px rgba(0,0,0,.15)}
.av-gallery-item.selected{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-l),0 4px 12px rgba(0,0,0,.12)}
.av-gallery-item.selected::after{content:'\f00c';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(26,138,92,.4);color:#fff;font-size:18px;border-radius:50%}
.av-gallery-item img{width:100%;height:100%;object-fit:cover}

/* ═══════════ MODAL HEADER HEIGHT (15% higher) ═══════════ */
.ci-modal-hdr { min-height: 56px; padding-top: 20px; padding-bottom: 20px; }

/* ═══════════ RESPONSIVE ═══════════ */
@media(max-width:768px){
    .pf-hero-inner{flex-direction:column;align-items:center;text-align:center;padding:24px 16px 20px}
    .pf-meta{justify-content:center}
    .pf-row{grid-template-columns:1fr}
    .pf-row.triple{grid-template-columns:1fr}
    .pf-tabs{gap:0}
    .pf-tab{padding:8px 12px;font-size:12px}
    .pf-avatar{width:80px;height:80px}
    .pf-avatar-letter{font-size:28px}
    .pf-name{font-size:18px}
}
</style>
<body>
<?php Layout::sidebar('profile'); Layout::beginContent(); ?>

<!-- ═══════════ HERO CARD ═══════════ -->
<div class="pf-hero" id="heroCard">
    <div class="pf-hero-inner">
        <div class="pf-avatar-wrap">
            <div class="pf-avatar" id="heroAvatar">
                <span class="pf-avatar-letter" id="heroLetter"></span>
            </div>
            <div class="pf-avatar-edit" onclick="openAvatarModal()" title="<?php echo $lang==='th'?'เปลี่ยนรูปโปรไฟล์':'Change avatar'; ?>">
                <i class="fas fa-camera"></i>
            </div>
        </div>
        <div class="pf-hero-info">
            <div class="pf-name" id="heroName">—</div>
            <div class="pf-role"><i class="fas fa-shield-halved"></i> <span id="heroRole">—</span></div>
            <div class="pf-meta">
                <span><i class="fas fa-envelope"></i> <span id="heroEmail">—</span></span>
                <span><i class="fas fa-calendar"></i> <span id="heroJoined">—</span></span>
                <span><i class="fas fa-clock"></i> <span id="heroLastLogin">—</span></span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ TABS ═══════════ -->
<div class="pf-tabs" id="pfTabs">
    <div class="pf-tab active" data-tab="info"><i class="fas fa-user"></i> <?php echo $lang==='th'?'ข้อมูลส่วนตัว':'Personal Info'; ?></div>
    <div class="pf-tab" data-tab="org"><i class="fas fa-sitemap"></i> <?php echo $lang==='th'?'สังกัด / หน่วยงาน':'Organization'; ?></div>
    <div class="pf-tab" data-tab="security"><i class="fas fa-lock"></i> <?php echo $lang==='th'?'ความปลอดภัย':'Security'; ?></div>
</div>

<!-- ═══════════ PANEL: PERSONAL INFO ═══════════ -->
<div class="pf-panel active" id="panel-info">
    <div class="ci-card">
        <div class="ci-card-body">
            <div class="pf-section">
                <div class="pf-section-title"><?php echo $lang==='th'?'ข้อมูลพื้นฐาน':'Basic Information'; ?></div>
                <div class="pf-row">
                    <div class="ci-fg">
                        <label class="ci-label"><?php echo $lang==='th'?'ชื่อจริง':'First Name'; ?></label>
                        <input type="text" id="pf_first_name" class="ci-input" data-field="first_name">
                    </div>
                    <div class="ci-fg">
                        <label class="ci-label"><?php echo $lang==='th'?'นามสกุล':'Last Name'; ?></label>
                        <input type="text" id="pf_last_name" class="ci-input" data-field="last_name">
                    </div>
                </div>
                <div class="pf-row">
                    <div class="ci-fg">
                        <label class="ci-label"><?php echo $lang==='th'?'ชื่อ-นามสกุล (ไทย)':'Full Name (Thai)'; ?></label>
                        <input type="text" id="pf_full_name_th" class="ci-input" data-field="full_name_th" placeholder="เช่น นายสมชาย ใจดี">
                    </div>
                    <div class="ci-fg">
                        <label class="ci-label">Username</label>
                        <input type="text" id="pf_username" class="ci-input" disabled style="background:#f5f5f5;color:var(--c3)">
                    </div>
                </div>
            </div>
            <div class="pf-section">
                <div class="pf-section-title"><?php echo $lang==='th'?'ข้อมูลติดต่อ':'Contact Information'; ?></div>
                <div class="pf-row">
                    <div class="ci-fg">
                        <label class="ci-label">Email</label>
                        <input type="email" id="pf_email" class="ci-input" data-field="email">
                    </div>
                    <div class="ci-fg">
                        <label class="ci-label"><?php echo $lang==='th'?'เบอร์โทรศัพท์':'Phone'; ?></label>
                        <input type="tel" id="pf_phone" class="ci-input" data-field="phone" placeholder="0xx-xxx-xxxx">
                    </div>
                </div>
            </div>
            <div class="pf-section">
                <div class="pf-section-title"><?php echo $lang==='th'?'ตำแหน่ง':'Position'; ?></div>
                <div class="pf-row">
                    <div class="ci-fg">
                        <label class="ci-label"><?php echo $lang==='th'?'ตำแหน่ง / หน้าที่':'Position / Title'; ?></label>
                        <input type="text" id="pf_position" class="ci-input" data-field="position" placeholder="<?php echo $lang==='th'?'เช่น นักวิทยาศาสตร์':'e.g. Scientist'; ?>">
                    </div>
                    <div class="ci-fg">
                        <label class="ci-label"><?php echo $lang==='th'?'แผนก (ข้อความ)':'Department (text)'; ?></label>
                        <input type="text" id="pf_department" class="ci-input" data-field="department">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="pf-save-bar" id="infoSaveBar">
        <button class="ci-btn ci-btn-secondary ci-btn-sm" onclick="loadProfile()"><i class="fas fa-undo"></i> <?php echo $lang==='th'?'ยกเลิก':'Discard'; ?></button>
        <button class="ci-btn ci-btn-primary ci-btn-sm" onclick="saveProfile()" id="saveInfoBtn"><i class="fas fa-save"></i> <?php echo $lang==='th'?'บันทึก':'Save'; ?></button>
    </div>
</div>

<!-- ═══════════ PANEL: ORGANIZATION ═══════════ -->
<div class="pf-panel" id="panel-org">
    <div class="ci-card">
        <div class="ci-card-head">
            <span><i class="fas fa-sitemap" style="color:var(--accent);margin-right:6px"></i> <?php echo $lang==='th'?'เลือกสังกัด / หน่วยงาน':'Select Organization Unit'; ?></span>
        </div>
        <div class="ci-card-body">
            <p style="font-size:12px;color:var(--c3);margin-bottom:16px">
                <i class="fas fa-info-circle" style="color:var(--info)"></i>
                <?php echo $lang==='th'?'เลือกศูนย์/สำนักวิชา → ฝ่าย/สาขาวิชา → งาน ตามลำดับ':'Select Center → Division → Section in order'; ?>
            </p>

            <!-- Level 1: ศูนย์/สำนักวิชา -->
            <div class="ci-fg">
                <label class="ci-label"><i class="fas fa-building" style="color:#1565c0;margin-right:4px"></i> <?php echo $lang==='th'?'ศูนย์ / สำนักวิชา':'Center / Faculty'; ?></label>
                <select id="org_level1" class="ci-select" onchange="loadOrgChildren(2, this.value)">
                    <option value="">— <?php echo $lang==='th'?'เลือก':'Select'; ?> —</option>
                </select>
            </div>

            <!-- Level 2: ฝ่าย/สาขาวิชา -->
            <div class="ci-fg" id="org_level2_wrap" style="display:none">
                <label class="ci-label"><i class="fas fa-project-diagram" style="color:#6a1b9a;margin-right:4px"></i> <?php echo $lang==='th'?'ฝ่าย / สาขาวิชา':'Division / Department'; ?></label>
                <select id="org_level2" class="ci-select" onchange="loadOrgChildren(3, this.value)">
                    <option value="">— <?php echo $lang==='th'?'เลือก':'Select'; ?> —</option>
                </select>
            </div>

            <!-- Level 3: งาน -->
            <div class="ci-fg" id="org_level3_wrap" style="display:none">
                <label class="ci-label"><i class="fas fa-flask" style="color:#e65100;margin-right:4px"></i> <?php echo $lang==='th'?'งาน':'Section'; ?></label>
                <select id="org_level3" class="ci-select" onchange="orgChanged()">
                    <option value="">— <?php echo $lang==='th'?'เลือก':'Select'; ?> —</option>
                </select>
            </div>

            <!-- Current org path display -->
            <div id="orgPathDisplay" style="margin-top:16px;display:none">
                <div class="pf-section-title"><?php echo $lang==='th'?'สังกัดปัจจุบัน':'Current Affiliation'; ?></div>
                <div class="org-tree" id="orgTree"></div>
            </div>
        </div>
    </div>
    <div class="pf-save-bar" id="orgSaveBar">
        <button class="ci-btn ci-btn-secondary ci-btn-sm" onclick="loadProfile()"><i class="fas fa-undo"></i> <?php echo $lang==='th'?'ยกเลิก':'Discard'; ?></button>
        <button class="ci-btn ci-btn-primary ci-btn-sm" onclick="saveOrg()" id="saveOrgBtn"><i class="fas fa-save"></i> <?php echo $lang==='th'?'บันทึก':'Save'; ?></button>
    </div>
</div>

<!-- ═══════════ PANEL: SECURITY ═══════════ -->
<div class="pf-panel" id="panel-security">
    <div class="ci-card">
        <div class="ci-card-head">
            <span><i class="fas fa-key" style="color:#c62828;margin-right:6px"></i> <?php echo $lang==='th'?'เปลี่ยนรหัสผ่าน':'Change Password'; ?></span>
        </div>
        <div class="ci-card-body">
            <div class="pf-row single" style="max-width:400px">
                <div class="ci-fg">
                    <label class="ci-label"><?php echo $lang==='th'?'รหัสผ่านปัจจุบัน':'Current Password'; ?></label>
                    <input type="password" id="pw_current" class="ci-input" autocomplete="current-password">
                </div>
            </div>
            <div class="pf-row single" style="max-width:400px">
                <div class="ci-fg">
                    <label class="ci-label"><?php echo $lang==='th'?'รหัสผ่านใหม่':'New Password'; ?></label>
                    <input type="password" id="pw_new" class="ci-input" oninput="checkPwStrength(this.value)" autocomplete="new-password">
                    <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
                    <div class="pw-hint" id="pwHint" style="color:var(--c3)"></div>
                </div>
            </div>
            <div class="pf-row single" style="max-width:400px">
                <div class="ci-fg">
                    <label class="ci-label"><?php echo $lang==='th'?'ยืนยันรหัสผ่านใหม่':'Confirm New Password'; ?></label>
                    <input type="password" id="pw_confirm" class="ci-input" autocomplete="new-password">
                </div>
            </div>
            <div style="margin-top:6px">
                <button class="ci-btn ci-btn-primary" onclick="changePassword()" id="changePwBtn">
                    <i class="fas fa-key"></i> <?php echo $lang==='th'?'เปลี่ยนรหัสผ่าน':'Change Password'; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ AVATAR MODAL ═══════════ -->
<div class="ci-modal-bg" id="avatarModal">
    <div class="ci-modal" style="max-width:480px">
        <div class="ci-modal-hdr">
            <h3><i class="fas fa-camera" style="color:var(--accent);margin-right:6px"></i> <?php echo $lang==='th'?'เปลี่ยนรูปโปรไฟล์':'Change Profile Picture'; ?></h3>
            <button class="ci-modal-close" onclick="closeAvatarModal()">&times;</button>
        </div>
        <div class="avatar-modal-body">
            <div class="avatar-preview-area" id="avatarPreview">
                <i class="fas fa-user" style="font-size:48px;color:#ccc"></i>
            </div>

            <!-- Default Avatar Gallery -->
            <div class="av-divider"><span><?php echo $lang==='th'?'เลือกอวาตาร์สำเร็จรูป':'Choose a Default Avatar'; ?></span></div>
            <div class="av-gallery" id="avGallery">
                <?php
                $defaultDir = __DIR__ . '/../assets/uploads/avatars/default/';
                $defaultUrl = '/v1/assets/uploads/avatars/default/';
                if (is_dir($defaultDir)) {
                    $files = array_filter(scandir($defaultDir), fn($f) => preg_match('/\.(png|jpg|jpeg|webp|gif)$/i', $f));
                    sort($files);
                    foreach ($files as $f):
                ?>
                <div class="av-gallery-item" onclick="selectDefaultAvatar(this,'<?php echo htmlspecialchars($f); ?>')" title="<?php echo pathinfo($f, PATHINFO_FILENAME); ?>">
                    <img src="<?php echo $defaultUrl . htmlspecialchars($f); ?>" alt="<?php echo pathinfo($f, PATHINFO_FILENAME); ?>" loading="lazy">
                </div>
                <?php endforeach; } ?>
            </div>
            <div style="margin-bottom:8px">
                <button class="ci-btn ci-btn-primary ci-btn-sm" onclick="saveDefaultAvatar()" id="saveDefaultBtn" disabled style="width:100%">
                    <i class="fas fa-check"></i> <?php echo $lang==='th'?'ใช้อวาตาร์นี้':'Use This Avatar'; ?>
                </button>
            </div>

            <!-- Upload Custom -->
            <div class="av-divider"><span><?php echo $lang==='th'?'หรืออัพโหลดรูปเอง':'Or Upload Your Own'; ?></span></div>
            <div class="avatar-drop-zone" id="dropZone" onclick="document.getElementById('avatarInput').click()">
                <i class="fas fa-cloud-arrow-up"></i>
                <div style="font-size:13px;font-weight:600"><?php echo $lang==='th'?'คลิกหรือลากไฟล์มาวาง':'Click or drag file here'; ?></div>
                <div style="font-size:11px;margin-top:4px">JPG, PNG, WebP — <?php echo $lang==='th'?'ไม่เกิน 5MB':'Max 5MB'; ?></div>
            </div>
            <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="previewAvatar(this)">
            <div style="margin-top:12px;display:flex;gap:8px;justify-content:center">
                <button class="ci-btn ci-btn-secondary" onclick="closeAvatarModal()"><?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></button>
                <button class="ci-btn ci-btn-primary" onclick="uploadAvatar()" id="uploadBtn" disabled>
                    <i class="fas fa-upload"></i> <?php echo $lang==='th'?'อัพโหลด':'Upload'; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<?php Layout::endContent(); ?>
<script>
const LANG='<?php echo $lang; ?>';
let profileData=null, orgDirty=false;

// ══════════ TABS ══════════
document.querySelectorAll('.pf-tab').forEach(tab=>{
    tab.addEventListener('click',()=>{
        document.querySelectorAll('.pf-tab').forEach(t=>t.classList.remove('active'));
        document.querySelectorAll('.pf-panel').forEach(p=>p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('panel-'+tab.dataset.tab).classList.add('active');
    });
});

// ══════════ LOAD PROFILE ══════════
async function loadProfile(){
    try{
        const d=await apiFetch('/v1/api/profile.php?action=profile');
        if(!d.success) throw new Error(d.error);
        profileData=d.data;
        populateProfile(d.data);
        hideSaveBars();
    }catch(e){showToast('❌ '+e.message,'error')}
}

function populateProfile(p){
    // Hero
    document.getElementById('heroName').textContent=p.full_name_th||((p.first_name||'')+' '+(p.last_name||''));
    document.getElementById('heroRole').textContent=p.role_display||p.role_name;
    document.getElementById('heroEmail').textContent=p.email||'—';
    document.getElementById('heroJoined').textContent=p.created_at?formatDate(p.created_at):'—';
    document.getElementById('heroLastLogin').textContent=p.last_login?formatDate(p.last_login):'—';

    // Avatar
    const av=document.getElementById('heroAvatar');
    const letter=document.getElementById('heroLetter');
    if(p.avatar_url){
        av.innerHTML=`<img src="${p.avatar_url}?t=${Date.now()}" alt="Avatar">`;
    }else{
        const l=p.full_name_th?p.full_name_th.charAt(0):(p.first_name||'U').charAt(0);
        av.innerHTML=`<span class="pf-avatar-letter">${l}</span>`;
    }

    // Form fields
    const fields=['first_name','last_name','full_name_th','email','phone','position','department'];
    fields.forEach(f=>{
        const el=document.getElementById('pf_'+f);
        if(el) el.value=p[f]||'';
    });
    document.getElementById('pf_username').value=p.username||'';

    // Org path
    if(p.org_path&&p.org_path.length>0){
        renderOrgPath(p.org_path);
        // Set cascading selects
        loadOrgLevel1(p.org_path);
    }else{
        document.getElementById('orgPathDisplay').style.display='none';
        loadOrgLevel1([]);
    }
}

function renderOrgPath(path){
    const container=document.getElementById('orgTree');
    const icons=['fa-building','fa-project-diagram','fa-flask'];
    const colors=['#1565c0','#6a1b9a','#e65100'];
    container.innerHTML=path.map((n,i)=>`
        <div class="org-node" style="padding-left:${i*28}px">
            <div class="org-dot" style="border-color:${colors[i]||'#999'}${i===path.length-1?';background:'+colors[i]:''}"></div>
            <div>
                <div class="org-label-type">${n.level_label||('Level '+n.level)}</div>
                <div class="org-label-name">${n.name}</div>
            </div>
        </div>
    `).join('');
    document.getElementById('orgPathDisplay').style.display='block';
}

// ══════════ ORG CASCADING SELECTS ══════════
async function loadOrgLevel1(currentPath){
    try{
        const d=await apiFetch('/v1/api/profile.php?action=departments&level=1');
        if(!d.success) return;
        const sel=document.getElementById('org_level1');
        sel.innerHTML='<option value="">— '+(LANG==='th'?'เลือก':'Select')+' —</option>';
        d.data.forEach(dept=>{
            sel.innerHTML+=`<option value="${dept.id}">${dept.name}</option>`;
        });
        // Pre-select if user has org_path
        if(currentPath.length>0){
            sel.value=currentPath[0].id;
            await loadOrgChildren(2, currentPath[0].id);
            if(currentPath.length>1){
                document.getElementById('org_level2').value=currentPath[1].id;
                await loadOrgChildren(3, currentPath[1].id);
                if(currentPath.length>2){
                    document.getElementById('org_level3').value=currentPath[2].id;
                }
            }
        }
    }catch(e){console.error(e)}
}

async function loadOrgChildren(level, parentId){
    const selId='org_level'+level;
    const wrapId=selId+'_wrap';
    const sel=document.getElementById(selId);
    const wrap=document.getElementById(wrapId);

    // Hide deeper levels
    for(let i=level;i<=3;i++){
        const w=document.getElementById('org_level'+i+'_wrap');
        if(w) w.style.display='none';
        const s=document.getElementById('org_level'+i);
        if(s) s.innerHTML='<option value="">— '+(LANG==='th'?'เลือก':'Select')+' —</option>';
    }

    if(!parentId){orgChanged();return}

    try{
        const d=await apiFetch(`/v1/api/profile.php?action=departments&level=${level}&parent_id=${parentId}`);
        if(!d.success||!d.data.length) return;
        d.data.forEach(dept=>{
            sel.innerHTML+=`<option value="${dept.id}">${dept.name}</option>`;
        });
        wrap.style.display='block';
    }catch(e){console.error(e)}
    orgChanged();
}

function orgChanged(){
    orgDirty=true;
    document.getElementById('orgSaveBar').classList.add('show');
}

// ══════════ SAVE PROFILE ══════════
function detectInfoChanges(){
    if(!profileData) return;
    document.getElementById('infoSaveBar').classList.add('show');
}

document.querySelectorAll('#panel-info [data-field]').forEach(el=>{
    el.addEventListener('input', detectInfoChanges);
});

async function saveProfile(){
    const btn=document.getElementById('saveInfoBtn');
    btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> '+(LANG==='th'?'กำลังบันทึก...':'Saving...');
    const payload={};
    document.querySelectorAll('#panel-info [data-field]').forEach(el=>{
        payload[el.dataset.field]=el.value;
    });
    try{
        const d=await apiFetch('/v1/api/profile.php?action=update_profile',{method:'POST',body:JSON.stringify(payload)});
        if(d.success){showToast(LANG==='th'?'✅ บันทึกข้อมูลส่วนตัวแล้ว':'✅ Profile saved','success');loadProfile()}
        else throw new Error(d.error);
    }catch(e){showToast('❌ '+e.message,'error')}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> '+(LANG==='th'?'บันทึก':'Save')}
}

async function saveOrg(){
    const btn=document.getElementById('saveOrgBtn');
    btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    // Find the deepest selected level
    let deptId=null;
    for(let i=3;i>=1;i--){
        const v=document.getElementById('org_level'+i)?.value;
        if(v){deptId=parseInt(v);break}
    }
    try{
        const d=await apiFetch('/v1/api/profile.php?action=update_profile',{method:'POST',body:JSON.stringify({department_id:deptId})});
        if(d.success){showToast(LANG==='th'?'✅ บันทึกสังกัดแล้ว':'✅ Organization saved','success');orgDirty=false;loadProfile()}
        else throw new Error(d.error);
    }catch(e){showToast('❌ '+e.message,'error')}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> '+(LANG==='th'?'บันทึก':'Save')}
}

// ══════════ AVATAR ══════════
let _selectedDefaultAvatar = null;

function openAvatarModal(){
    document.getElementById('avatarModal').classList.add('show');
    document.getElementById('avatarInput').value='';
    document.getElementById('avatarPreview').innerHTML='<i class="fas fa-user" style="font-size:48px;color:#ccc"></i>';
    document.getElementById('uploadBtn').disabled=true;
    document.getElementById('saveDefaultBtn').disabled=true;
    _selectedDefaultAvatar=null;
    document.querySelectorAll('.av-gallery-item').forEach(i=>i.classList.remove('selected'));

    // If current avatar is a default, pre-select it
    if(profileData && profileData.avatar_url && profileData.avatar_url.includes('/default/')){
        const fname = profileData.avatar_url.split('/').pop();
        document.querySelectorAll('.av-gallery-item').forEach(item=>{
            const img = item.querySelector('img');
            if(img && img.src.includes('/default/' + fname)){
                item.classList.add('selected');
                _selectedDefaultAvatar = fname;
                document.getElementById('avatarPreview').innerHTML=`<img src="${profileData.avatar_url}?t=${Date.now()}">`;
            }
        });
    }
}
function closeAvatarModal(){document.getElementById('avatarModal').classList.remove('show')}

function selectDefaultAvatar(el, filename){
    // Deselect all
    document.querySelectorAll('.av-gallery-item').forEach(i=>i.classList.remove('selected'));
    el.classList.add('selected');
    _selectedDefaultAvatar = filename;
    // Show in preview
    const url = '/v1/assets/uploads/avatars/default/' + filename;
    document.getElementById('avatarPreview').innerHTML=`<img src="${url}">`;
    document.getElementById('saveDefaultBtn').disabled=false;
    // Clear file input (deselect custom upload)
    document.getElementById('avatarInput').value='';
    document.getElementById('uploadBtn').disabled=true;
}

async function saveDefaultAvatar(){
    if(!_selectedDefaultAvatar) return;
    const btn=document.getElementById('saveDefaultBtn');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> '+(LANG==='th'?'กำลังบันทึก...':'Saving...');
    try{
        const d=await apiFetch('/v1/api/profile.php?action=set_default_avatar',{
            method:'POST',
            body:JSON.stringify({avatar:_selectedDefaultAvatar})
        });
        if(d.success){
            showToast(LANG==='th'?'✅ เปลี่ยนอวาตาร์สำเร็จ':'✅ Avatar changed','success');
            closeAvatarModal();
            loadProfile();
        } else throw new Error(d.error);
    }catch(e){showToast('❌ '+e.message,'error')}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> '+(LANG==='th'?'ใช้อวาตาร์นี้':'Use This Avatar')}
}

// Drag & drop
const dz=document.getElementById('dropZone');
dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('dragover')});
dz.addEventListener('dragleave',()=>dz.classList.remove('dragover'));
dz.addEventListener('drop',e=>{
    e.preventDefault();dz.classList.remove('dragover');
    const f=e.dataTransfer.files[0];
    if(f&&f.type.startsWith('image/')){
        document.getElementById('avatarInput').files=e.dataTransfer.files;
        previewAvatar(document.getElementById('avatarInput'));
    }
});

function previewAvatar(input){
    const file=input.files[0];
    if(!file) return;
    if(file.size>5*1024*1024){showToast(LANG==='th'?'❌ ไฟล์ใหญ่เกิน 5MB':'❌ File too large','error');return}
    // Deselect default gallery
    document.querySelectorAll('.av-gallery-item').forEach(i=>i.classList.remove('selected'));
    _selectedDefaultAvatar=null;
    document.getElementById('saveDefaultBtn').disabled=true;
    const reader=new FileReader();
    reader.onload=e=>{
        document.getElementById('avatarPreview').innerHTML=`<img src="${e.target.result}">`;
        document.getElementById('uploadBtn').disabled=false;
    };
    reader.readAsDataURL(file);
}

async function uploadAvatar(){
    const input=document.getElementById('avatarInput');
    if(!input.files[0]) return;
    const btn=document.getElementById('uploadBtn');
    btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> '+(LANG==='th'?'กำลังอัพโหลด...':'Uploading...');

    const fd=new FormData();
    fd.append('avatar',input.files[0]);

    try{
        const token=document.cookie.split('; ').find(c=>c.startsWith('auth_token='))?.split('=')[1];
        const r=await fetch('/v1/api/profile.php?action=upload_avatar',{
            method:'POST',
            headers:token?{'Authorization':'Bearer '+token}:{},
            body:fd
        });
        const d=await r.json();
        if(d.success){
            showToast(LANG==='th'?'✅ อัพโหลดรูปสำเร็จ':'✅ Avatar uploaded','success');
            closeAvatarModal();
            loadProfile();
        }else throw new Error(d.error);
    }catch(e){showToast('❌ '+e.message,'error')}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-upload"></i> '+(LANG==='th'?'อัพโหลด':'Upload')}
}

// ══════════ PASSWORD ══════════
function checkPwStrength(pw){
    const bar=document.getElementById('pwBar');
    const hint=document.getElementById('pwHint');
    if(!pw){bar.className='pw-strength-bar';bar.style.width='0';hint.textContent='';return}
    let score=0;
    if(pw.length>=6) score++;
    if(pw.length>=10) score++;
    if(/[A-Z]/.test(pw)&&/[a-z]/.test(pw)) score++;
    if(/\d/.test(pw)) score++;
    if(/[^A-Za-z0-9]/.test(pw)) score++;

    if(score<=2){bar.className='pw-strength-bar weak';hint.textContent=LANG==='th'?'อ่อน':'Weak';hint.style.color='#d9534f'}
    else if(score<=3){bar.className='pw-strength-bar medium';hint.textContent=LANG==='th'?'ปานกลาง':'Medium';hint.style.color='#f0ad4e'}
    else{bar.className='pw-strength-bar strong';hint.textContent=LANG==='th'?'แข็งแรง':'Strong';hint.style.color='#5cb85c'}
}

async function changePassword(){
    const btn=document.getElementById('changePwBtn');
    const cur=document.getElementById('pw_current').value;
    const nw=document.getElementById('pw_new').value;
    const cf=document.getElementById('pw_confirm').value;
    if(!cur||!nw||!cf){showToast(LANG==='th'?'❌ กรุณากรอกข้อมูลให้ครบ':'❌ Fill all fields','error');return}
    if(nw!==cf){showToast(LANG==='th'?'❌ รหัสผ่านไม่ตรงกัน':'❌ Passwords do not match','error');return}
    if(nw.length<6){showToast(LANG==='th'?'❌ รหัสผ่านต้องยาวอย่างน้อย 6 ตัว':'❌ Min 6 characters','error');return}

    btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    try{
        const d=await apiFetch('/v1/api/profile.php?action=change_password',{method:'POST',body:JSON.stringify({current_password:cur,new_password:nw,confirm_password:cf})});
        if(d.success){
            showToast(LANG==='th'?'✅ เปลี่ยนรหัสผ่านสำเร็จ':'✅ Password changed','success');
            document.getElementById('pw_current').value='';
            document.getElementById('pw_new').value='';
            document.getElementById('pw_confirm').value='';
            document.getElementById('pwBar').className='pw-strength-bar';
            document.getElementById('pwHint').textContent='';
        }else throw new Error(d.error);
    }catch(e){showToast('❌ '+e.message,'error')}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-key"></i> '+(LANG==='th'?'เปลี่ยนรหัสผ่าน':'Change Password')}
}

// ══════════ HELPERS ══════════
function hideSaveBars(){
    document.querySelectorAll('.pf-save-bar').forEach(b=>b.classList.remove('show'));
    orgDirty=false;
}

function showToast(msg,type){
    const t=document.getElementById('toast');
    t.textContent=msg;t.className='toast '+type+' show';
    setTimeout(()=>t.classList.remove('show'),3000);
}

// ══════════ INIT ══════════
loadProfile();
</script>
</body></html>
