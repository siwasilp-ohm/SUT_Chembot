<?php
require_once __DIR__ . '/../includes/layout.php';

$user = Auth::getCurrentUser();
if (!$user)                            { header('Location: /v1/pages/login.php'); exit; }
if ($user['role_name'] !== 'admin')    { header('Location: /v1/');               exit; }

$lang = I18n::getCurrentLang();
$TH   = ($lang === 'th');

Layout::head($TH ? 'ตั้งค่าระบบ' : 'System Settings');
?>
<style>
:root{
  --st:#6d28d9;--st-l:#ede9fe;--st-d:#5b21b6;
  --st-r:14px;--st-rs:10px;
  --st-sh:0 1px 4px rgba(0,0,0,.06);--st-shm:0 6px 24px rgba(0,0,0,.1);
}

/* ── Hero ─────────────────────────────────────────────────────────────── */
.st-hero{
  background:linear-gradient(135deg,#1e0a3c 0%,#4c1d95 55%,#6d28d9 100%);
  border-radius:var(--st-r);padding:24px 28px;color:#fff;
  display:flex;align-items:center;gap:20px;margin-bottom:20px;
  position:relative;overflow:hidden;
}
.st-hero::before{
  content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
}
.st-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.15);
  backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;
  font-size:22px;flex-shrink:0;position:relative;}
.st-hero-info{position:relative;flex:1;min-width:0}
.st-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.st-hero-info p{font-size:12px;opacity:.72;margin:0 0 10px}
.st-hero-pills{display:flex;gap:7px;flex-wrap:wrap}
.st-hero-pill{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;
  padding:4px 10px;border-radius:20px;background:rgba(255,255,255,.14);
  backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.18);}

/* ── Layout ───────────────────────────────────────────────────────────── */
.st-layout{display:grid;grid-template-columns:224px 1fr;gap:16px;align-items:start}

/* ── Left Nav ─────────────────────────────────────────────────────────── */
.st-nav{background:#fff;border-radius:var(--st-r);box-shadow:var(--st-sh);border:1.5px solid var(--border);overflow:hidden;position:sticky;top:16px}
.st-nav-hdr{padding:11px 14px;border-bottom:1px solid var(--border);font-size:10.5px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px}
.st-nav-list{padding:6px}
.st-nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;cursor:pointer;transition:all .15s;border:1.5px solid transparent;border-left-width:3px;user-select:none}
.st-nav-item:hover{background:var(--bg);border-left-color:var(--border)}
.st-nav-item.active{background:var(--st-l);border-color:var(--st);border-left-color:var(--st)}
.st-nav-item:active{transform:scale(.98)}
.st-nav-ic{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;transition:background .15s,color .15s}
.st-nav-label{font-size:12.5px;font-weight:600;color:var(--c1);flex:1}
.st-nav-item.active .st-nav-label{color:var(--st)}
.st-nav-dirty{width:7px;height:7px;border-radius:50%;background:#f59e0b;flex-shrink:0;display:none}
.st-nav-item.dirty .st-nav-dirty{display:block}
.st-nav-count{font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px;flex-shrink:0}
.st-nav-sep{height:1px;background:var(--border);margin:4px 8px}

/* ── Section Panels ───────────────────────────────────────────────────── */
.st-panel{display:none;flex-direction:column;gap:16px}
.st-panel.active{display:flex}

/* ── Card ─────────────────────────────────────────────────────────────── */
.st-card{background:#fff;border-radius:var(--st-r);box-shadow:var(--st-sh);border:1.5px solid var(--border);overflow:hidden;transition:border-color .2s,box-shadow .2s}
.st-card.dirty{border-color:#fcd34d;box-shadow:0 0 0 3px rgba(252,211,77,.15),var(--st-sh)}
.st-card-hdr{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;background:linear-gradient(to bottom,#fafafa,#f5f7fa)}
.st-card-hdr-ic{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.st-card-hdr-title{font-size:13px;font-weight:700;color:var(--c1);flex:1}
.st-card-hdr-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:#fef3c7;color:#d97706;border:1px solid #fcd34d;display:none;align-items:center;gap:4px}
.st-card.dirty .st-card-hdr-badge{display:flex}
.st-card-body{padding:0 16px}

/* ── Setting Row ──────────────────────────────────────────────────────── */
.st-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:13px 0;border-bottom:1px solid var(--border)}
.st-row:last-child{border-bottom:none}
.st-row-info{flex:1;min-width:0}
.st-row-label{font-size:13px;font-weight:600;color:var(--c1);display:flex;align-items:center;gap:6px}
.st-row-desc{font-size:11px;color:var(--c3);margin-top:3px;line-height:1.5}
.st-row-ctrl{flex-shrink:0;display:flex;align-items:center;gap:8px}
.st-row-unit{font-size:11.5px;color:var(--c3);white-space:nowrap}

/* ── Toggle Switch ────────────────────────────────────────────────────── */
.st-tog{position:relative;width:44px;height:24px;flex-shrink:0}
.st-tog input{opacity:0;width:0;height:0;position:absolute}
.st-track{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:24px;transition:background .2s}
.st-track:hover{background:#94a3b8}
.st-thumb{position:absolute;left:3px;top:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.18)}
.st-tog input:checked~.st-track{background:var(--st)}
.st-tog input:checked~.st-track:hover{background:var(--st-d)}
.st-tog input:checked~.st-thumb{transform:translateX(20px)}
.st-tog input:disabled~.st-track{opacity:.45;cursor:not-allowed}

/* ── Number input ─────────────────────────────────────────────────────── */
.st-num{width:82px;text-align:center;font-weight:700;padding:6px 8px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;color:var(--c1);transition:border-color .15s}
.st-num:focus{outline:none;border-color:var(--st);box-shadow:0 0 0 3px rgba(109,40,217,.1)}
.st-num:disabled{opacity:.45;cursor:not-allowed;background:#f8fafc}

/* ── Text / Select inputs ─────────────────────────────────────────────── */
.st-input{padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;color:var(--c1);transition:border-color .15s;background:#fff}
.st-input:focus{outline:none;border-color:var(--st);box-shadow:0 0 0 3px rgba(109,40,217,.1)}
.st-select{padding:7px 28px 7px 10px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;color:var(--c1);background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%2394a3b8' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E") no-repeat right 10px center;-webkit-appearance:none;cursor:pointer;transition:border-color .15s}
.st-select:focus{outline:none;border-color:var(--st)}
.st-textarea{width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-family:'Consolas','Monaco',monospace;font-size:12px;color:var(--c1);resize:vertical;transition:border-color .15s;background:#fff}
.st-textarea:focus{outline:none;border-color:var(--st);box-shadow:0 0 0 3px rgba(109,40,217,.1)}

/* Sub-section (lockout children) */
.st-sub{padding:4px 0 4px 16px;border-left:3px solid var(--st-l);margin-left:4px;transition:opacity .2s}
.st-sub.off{opacity:.38;pointer-events:none}

/* ── Section Footer / Save ────────────────────────────────────────────── */
.st-foot{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:linear-gradient(to top,#fafafa,#f5f7fa);border-top:1px solid var(--border)}
.st-foot-hint{font-size:11px;color:#d97706;display:flex;align-items:center;gap:5px;opacity:0;transition:opacity .2s;font-weight:600}
.st-foot-hint.show{opacity:1}
.st-save-btn{padding:8px 18px;border:none;border-radius:9px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all .15s;font-family:inherit;background:var(--st);color:#fff}
.st-save-btn:hover{background:var(--st-d);box-shadow:0 3px 10px rgba(109,40,217,.25)}
.st-save-btn:disabled{opacity:.5;cursor:not-allowed;box-shadow:none}

/* ── Iframe Config ────────────────────────────────────────────────────── */
.st-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.st-field label{font-size:11.5px;font-weight:700;color:var(--c2);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px}
.st-field .hint{font-size:10px;color:var(--c3);margin-top:4px;line-height:1.5}
.st-parse-box{background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:16px;margin-top:4px}
.st-parse-box h4{font-size:13px;font-weight:700;color:#92400e;margin:0 0 4px;display:flex;align-items:center;gap:7px}
.st-parse-hint{font-size:11px;color:#92400e;margin-bottom:12px;line-height:1.55}
.st-parse-out{background:#f0fdf4;border:1.5px solid #86efac;border-radius:8px}

/* ── Locked Users ─────────────────────────────────────────────────────── */
.st-lock-card{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border)}
.st-lock-card:last-child{border-bottom:none}
.st-lock-av{width:38px;height:38px;border-radius:10px;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;flex-shrink:0}
.st-lock-info{flex:1;min-width:0}
.st-lock-name{font-size:13px;font-weight:700;color:var(--c1)}
.st-lock-sub{font-size:11px;color:var(--c3);margin-top:2px;line-height:1.45}
.st-lock-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;background:#fee2e2;color:#dc2626;margin-top:4px}
.st-unlock-btn{padding:7px 14px;border:1.5px solid #fca5a5;background:#fff;color:#dc2626;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:5px;flex-shrink:0}
.st-unlock-btn:hover{background:#fef2f2;border-color:#f87171}
.st-unlock-btn:disabled{opacity:.5;cursor:not-allowed}

/* ── Danger Zone ──────────────────────────────────────────────────────── */
.st-danger-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border-bottom:1px solid #fecaca}
.st-danger-row:last-child{border-bottom:none}
.st-danger-title{font-size:13px;font-weight:700;color:#dc2626}
.st-danger-desc{font-size:11px;color:#ef4444;margin-top:2px;line-height:1.45}
.st-danger-btn{padding:7px 16px;border:1.5px solid #fca5a5;background:#fff;color:#dc2626;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;white-space:nowrap;display:flex;align-items:center;gap:5px;flex-shrink:0}
.st-danger-btn:hover{background:#fef2f2;border-color:#f87171}

/* ── Toast ────────────────────────────────────────────────────────────── */
.st-toast{position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;
  padding:11px 18px;border-radius:10px;font-size:13px;font-weight:500;
  box-shadow:0 4px 20px rgba(0,0,0,.2);z-index:9999;
  display:flex;align-items:center;gap:8px;
  transform:translateY(80px);opacity:0;transition:transform .25s,opacity .25s;max-width:320px}
.st-toast.show{transform:none;opacity:1}
.st-toast.ok {background:#064e3b}
.st-toast.err{background:#7f1d1d}
.st-toast.warn{background:#78350f}

/* ── Confirm Modal ────────────────────────────────────────────────────── */
.st-ov{position:fixed;inset:0;background:rgba(15,23,42,.52);backdrop-filter:blur(4px);z-index:10000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
.st-ov.show{opacity:1;pointer-events:auto}
.st-modal{background:#fff;border-radius:18px;width:460px;max-width:92vw;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 24px 64px rgba(0,0,0,.25);transform:translateY(16px) scale(.97);transition:transform .22s}
.st-ov.show .st-modal{transform:none}
.st-modal-hdr{display:flex;align-items:center;gap:14px;padding:20px 22px 14px;border-bottom:1px solid var(--border);flex-shrink:0}
.st-modal-hdr-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.st-modal-title{font-size:15px;font-weight:800;color:var(--c1)}
.st-modal-close{margin-left:auto;width:30px;height:30px;border-radius:8px;border:none;background:var(--bg);color:var(--c2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.st-modal-close:hover{background:#e2e8f0;color:var(--c1)}
.st-modal-body{padding:16px 22px;overflow-y:auto;flex:1;font-size:13px;color:var(--c2);line-height:1.6}
.st-changes{margin-top:10px;border:1.5px solid var(--border);border-radius:10px;overflow:hidden}
.st-chg-item{display:flex;align-items:center;gap:8px;padding:8px 14px;border-bottom:1px solid var(--border);font-size:12px}
.st-chg-item:last-child{border-bottom:none}
.st-chg-item .chk-ic{width:20px;height:20px;border-radius:5px;background:#f1f5f9;color:var(--c3);display:flex;align-items:center;justify-content:center;font-size:9px;flex-shrink:0}
.st-chg-label{font-weight:700;color:var(--c1);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.st-chg-old{color:#dc2626;text-decoration:line-through;font-size:11px;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.st-chg-new{color:#16a34a;font-weight:700;font-size:11px;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.st-modal-foot{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 22px 18px;border-top:1px solid var(--border);flex-shrink:0}
.st-btn-cancel{background:none;border:1.5px solid var(--border);color:var(--c2);padding:8px 18px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s}
.st-btn-cancel:hover{border-color:#94a3b8;color:var(--c1)}
.st-btn-ok{border:none;padding:9px 22px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:7px;transition:all .15s}
.st-btn-ok.purple{background:var(--st);color:#fff}
.st-btn-ok.purple:hover{background:var(--st-d)}
.st-btn-ok.danger{background:#dc2626;color:#fff}
.st-btn-ok.danger:hover{background:#b91c1c}
.st-btn-ok:disabled{opacity:.55;cursor:not-allowed}

/* ── Empty ────────────────────────────────────────────────────────────── */
.st-empty{text-align:center;padding:32px 20px;color:var(--c3)}
.st-empty i{font-size:28px;opacity:.35;display:block;margin-bottom:10px}
.st-empty p{margin:0;font-size:13px}

/* ── Responsive ───────────────────────────────────────────────────────── */
@media(max-width:900px){
  .st-layout{grid-template-columns:1fr}
  .st-nav{position:static}
  .st-nav-list{display:flex;flex-wrap:wrap;gap:4px;padding:8px}
  .st-nav-item{flex-shrink:0}
  .st-grid2{grid-template-columns:1fr}
}
@media(max-width:600px){
  .st-row{flex-direction:column;align-items:flex-start;gap:8px}
  .st-row-ctrl{align-self:flex-end}
  .st-hero{padding:18px 20px}
  .st-hero-pills{display:none}
  .st-input{width:100%!important}
}
</style>

<?php Layout::sidebar('settings'); Layout::beginContent(); ?>

<!-- Toast -->
<div class="st-toast" id="stToast"></div>

<!-- Confirm Modal -->
<div class="st-ov" id="stModal" onclick="if(event.target===this)stCloseModal()">
    <div class="st-modal">
        <div class="st-modal-hdr">
            <div class="st-modal-hdr-ic" id="stModalIc"></div>
            <div class="st-modal-title" id="stModalTitle"></div>
            <button class="st-modal-close" onclick="stCloseModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="st-modal-body" id="stModalBody"></div>
        <div class="st-modal-foot">
            <button class="st-btn-cancel" onclick="stCloseModal()"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
            <button class="st-btn-ok purple" id="stModalOk"><?= $TH ? 'ยืนยัน' : 'Confirm' ?></button>
        </div>
    </div>
</div>

<!-- ── Hero ──────────────────────────────────────────────────────────── -->
<div class="st-hero">
    <div class="st-hero-ic"><i class="fas fa-sliders-h"></i></div>
    <div class="st-hero-info">
        <h2><?= $TH ? 'ตั้งค่าระบบ' : 'System Settings' ?></h2>
        <p><?= $TH ? 'กำหนดค่าความปลอดภัย ทั่วไป และการกำหนดค่าระบบสำหรับผู้ดูแล' : 'Configure security, general, and system preferences for administrators' ?></p>
        <div class="st-hero-pills">
            <span class="st-hero-pill"><i class="fas fa-shield-alt" style="font-size:10px"></i> <?= $TH ? 'เฉพาะ Admin' : 'Admin Only' ?></span>
            <span class="st-hero-pill"><i class="fas fa-database" style="font-size:10px"></i> <?= $TH ? 'บันทึกลง DB ทันที' : 'Persisted to DB' ?></span>
            <span class="st-hero-pill"><i class="fas fa-history" style="font-size:10px"></i> <?= $TH ? 'ติดตามการเปลี่ยนแปลง' : 'Change Tracking' ?></span>
        </div>
    </div>
</div>

<!-- ── Main Layout ────────────────────────────────────────────────────── -->
<div class="st-layout">

    <!-- Left Nav -->
    <div class="st-nav">
        <div class="st-nav-hdr"><i class="fas fa-list"></i><?= $TH ? 'หมวดหมู่' : 'Sections' ?></div>
        <div class="st-nav-list">
            <div class="st-nav-item active" data-panel="security" onclick="switchPanel('security')">
                <div class="st-nav-ic" style="background:#fee2e2;color:#dc2626"><i class="fas fa-shield-alt"></i></div>
                <span class="st-nav-label"><?= $TH ? 'ความปลอดภัย' : 'Security' ?></span>
                <span class="st-nav-dirty"></span>
            </div>
            <div class="st-nav-item" data-panel="general" onclick="switchPanel('general')">
                <div class="st-nav-ic" style="background:#dbeafe;color:#2563eb"><i class="fas fa-cog"></i></div>
                <span class="st-nav-label"><?= $TH ? 'ทั่วไป' : 'General' ?></span>
                <span class="st-nav-dirty"></span>
            </div>
            <div class="st-nav-item" data-panel="iframe" onclick="switchPanel('iframe')">
                <div class="st-nav-ic" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-cube"></i></div>
                <span class="st-nav-label">3D / Iframe</span>
                <span class="st-nav-dirty"></span>
            </div>
            <div class="st-nav-sep"></div>
            <div class="st-nav-item" data-panel="locked" onclick="switchPanel('locked')">
                <div class="st-nav-ic" style="background:#fef3c7;color:#d97706"><i class="fas fa-user-lock"></i></div>
                <span class="st-nav-label"><?= $TH ? 'บัญชีถูกล็อค' : 'Locked Accounts' ?></span>
                <span class="st-nav-count" id="stLockCount" style="display:none;background:#fee2e2;color:#dc2626"></span>
            </div>
            <div class="st-nav-item" data-panel="danger" onclick="switchPanel('danger')">
                <div class="st-nav-ic" style="background:#fee2e2;color:#dc2626"><i class="fas fa-exclamation-triangle"></i></div>
                <span class="st-nav-label"><?= $TH ? 'อันตราย' : 'Danger Zone' ?></span>
            </div>
        </div>
    </div>

    <!-- Right Content -->
    <div id="stPanelContainer">

        <!-- ── SECURITY ──────────────────────────────────────────── -->
        <div class="st-panel active" id="panel-security">
            <div class="st-card" id="card-security">
                <div class="st-card-hdr">
                    <div class="st-card-hdr-ic" style="background:#fee2e2;color:#dc2626"><i class="fas fa-shield-alt"></i></div>
                    <span class="st-card-hdr-title"><?= $TH ? 'ความปลอดภัย' : 'Security' ?></span>
                    <span class="st-card-hdr-badge" id="badge-security">
                        <i class="fas fa-circle" style="font-size:6px"></i> <?= $TH ? 'มีการเปลี่ยนแปลง' : 'Unsaved' ?>
                    </span>
                </div>
                <div class="st-card-body">
                    <!-- Auto-Lock -->
                    <div class="st-row">
                        <div class="st-row-info">
                            <div class="st-row-label"><i class="fas fa-lock" style="color:#dc2626"></i><?= $TH ? 'การล็อคบัญชีอัตโนมัติ' : 'Account Auto-Lock' ?></div>
                            <div class="st-row-desc"><?= $TH ? 'ล็อคบัญชีเมื่อกรอกรหัสผ่านผิดเกินจำนวนครั้งที่กำหนด' : 'Lock account after too many failed login attempts' ?></div>
                        </div>
                        <div class="st-row-ctrl">
                            <label class="st-tog">
                                <input type="checkbox" id="account_lockout_enabled"
                                    data-key="account_lockout_enabled" data-sec="security"
                                    data-label="<?= $TH ? 'ล็อคบัญชีอัตโนมัติ' : 'Auto-Lock' ?>">
                                <span class="st-track"></span><span class="st-thumb"></span>
                            </label>
                        </div>
                    </div>
                    <!-- Lockout sub-settings -->
                    <div class="st-sub off" id="stLockoutSub">
                        <div class="st-row">
                            <div class="st-row-info">
                                <div class="st-row-label"><?= $TH ? 'จำนวนครั้งสูงสุดที่กรอกผิดได้' : 'Max Failed Attempts' ?></div>
                                <div class="st-row-desc"><?= $TH ? 'ล็อคบัญชีเมื่อกรอกรหัสผ่านผิดเกินจำนวนนี้' : 'Lock account after this many wrong passwords' ?></div>
                            </div>
                            <div class="st-row-ctrl">
                                <input type="number" id="account_lockout_max_attempts"
                                    data-key="account_lockout_max_attempts" data-sec="security"
                                    data-label="<?= $TH ? 'จำนวนครั้งสูงสุด' : 'Max Attempts' ?>"
                                    class="st-num" min="1" max="20" value="5">
                                <span class="st-row-unit"><?= $TH ? 'ครั้ง' : 'times' ?></span>
                            </div>
                        </div>
                        <div class="st-row">
                            <div class="st-row-info">
                                <div class="st-row-label"><?= $TH ? 'ระยะเวลาล็อค' : 'Lock Duration' ?></div>
                                <div class="st-row-desc"><?= $TH ? 'ระยะเวลาที่ล็อคบัญชีหลังจากกรอกผิดเกิน' : 'How long the account stays locked after too many attempts' ?></div>
                            </div>
                            <div class="st-row-ctrl">
                                <input type="number" id="account_lockout_duration"
                                    data-key="account_lockout_duration" data-sec="security"
                                    data-label="<?= $TH ? 'ระยะเวลาล็อค' : 'Lock Duration' ?>"
                                    class="st-num" min="1" max="1440" value="30">
                                <span class="st-row-unit"><?= $TH ? 'นาที' : 'min' ?></span>
                            </div>
                        </div>
                    </div>
                    <!-- Self-Registration -->
                    <div class="st-row">
                        <div class="st-row-info">
                            <div class="st-row-label"><i class="fas fa-user-plus" style="color:#2563eb"></i><?= $TH ? 'เปิดให้ลงทะเบียนเอง' : 'Allow Self-Registration' ?></div>
                            <div class="st-row-desc"><?= $TH ? 'ผู้ใช้สามารถสมัครบัญชีเองผ่านหน้า Register' : 'Users can register accounts via the registration page' ?></div>
                        </div>
                        <div class="st-row-ctrl">
                            <label class="st-tog">
                                <input type="checkbox" id="allow_registration"
                                    data-key="allow_registration" data-sec="security"
                                    data-label="<?= $TH ? 'ลงทะเบียนเอง' : 'Self-Register' ?>">
                                <span class="st-track"></span><span class="st-thumb"></span>
                            </label>
                        </div>
                    </div>
                    <!-- Demo Accounts -->
                    <div class="st-row">
                        <div class="st-row-info">
                            <div class="st-row-label"><i class="fas fa-vial" style="color:#0d9488"></i><?= $TH ? 'บัญชีทดลองใช้งาน' : 'Demo Accounts' ?></div>
                            <div class="st-row-desc"><?= $TH ? 'แสดงรายชื่อบัญชีทดลองใช้งานในหน้า Login เพื่อให้เลือกเข้าสู่ระบบได้ง่าย' : 'Show demo account selector on the login page for easy access' ?></div>
                        </div>
                        <div class="st-row-ctrl">
                            <span id="stDemoLabel" style="font-size:11px;font-weight:700;margin-right:4px"></span>
                            <label class="st-tog">
                                <input type="checkbox" id="demo_accounts_enabled"
                                    data-key="demo_accounts_enabled" data-sec="security"
                                    data-label="<?= $TH ? 'บัญชีทดลอง' : 'Demo Accounts' ?>">
                                <span class="st-track"></span><span class="st-thumb"></span>
                            </label>
                        </div>
                    </div>
                    <!-- Session Timeout -->
                    <div class="st-row">
                        <div class="st-row-info">
                            <div class="st-row-label"><i class="fas fa-clock" style="color:#7c3aed"></i>Session Timeout</div>
                            <div class="st-row-desc"><?= $TH ? 'ระยะเวลา session หมดอายุ (หลังจากไม่ได้ใช้งาน)' : 'Session expiry after inactivity' ?></div>
                        </div>
                        <div class="st-row-ctrl">
                            <input type="number" id="session_timeout"
                                data-key="session_timeout" data-sec="security"
                                data-label="Session Timeout"
                                class="st-num" min="5" max="43200" value="1440">
                            <span class="st-row-unit"><?= $TH ? 'นาที' : 'min' ?></span>
                        </div>
                    </div>
                </div>
                <div class="st-foot">
                    <div class="st-foot-hint" id="foot-hint-security"><i class="fas fa-circle" style="font-size:7px"></i><?= $TH ? 'มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก' : 'Unsaved changes' ?></div>
                    <button class="st-save-btn" id="save-btn-security" onclick="confirmSave('security')">
                        <i class="fas fa-floppy-disk"></i><?= $TH ? 'บันทึกความปลอดภัย' : 'Save Security' ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── GENERAL ───────────────────────────────────────────── -->
        <div class="st-panel" id="panel-general">
            <div class="st-card" id="card-general">
                <div class="st-card-hdr">
                    <div class="st-card-hdr-ic" style="background:#dbeafe;color:#2563eb"><i class="fas fa-cog"></i></div>
                    <span class="st-card-hdr-title"><?= $TH ? 'ทั่วไป' : 'General' ?></span>
                    <span class="st-card-hdr-badge" id="badge-general">
                        <i class="fas fa-circle" style="font-size:6px"></i> <?= $TH ? 'มีการเปลี่ยนแปลง' : 'Unsaved' ?>
                    </span>
                </div>
                <div class="st-card-body">
                    <div class="st-row">
                        <div class="st-row-info">
                            <div class="st-row-label"><?= $TH ? 'ชื่อระบบ (ไทย)' : 'System Name (Thai)' ?></div>
                            <div class="st-row-desc"><?= $TH ? 'แสดงในหัวเว็บและ Sidebar' : 'Shown in page title and sidebar' ?></div>
                        </div>
                        <div class="st-row-ctrl">
                            <input type="text" id="app_name_th"
                                data-key="app_name_th" data-sec="general"
                                data-label="<?= $TH ? 'ชื่อระบบ (TH)' : 'Name TH' ?>"
                                class="st-input" style="width:220px">
                        </div>
                    </div>
                    <div class="st-row">
                        <div class="st-row-info">
                            <div class="st-row-label"><?= $TH ? 'ชื่อระบบ (EN)' : 'System Name (EN)' ?></div>
                            <div class="st-row-desc"><?= $TH ? 'แสดงเมื่อเปลี่ยนภาษาเป็นอังกฤษ' : 'Shown when language is set to English' ?></div>
                        </div>
                        <div class="st-row-ctrl">
                            <input type="text" id="app_name_en"
                                data-key="app_name_en" data-sec="general"
                                data-label="<?= $TH ? 'ชื่อระบบ (EN)' : 'Name EN' ?>"
                                class="st-input" style="width:220px">
                        </div>
                    </div>
                    <div class="st-row">
                        <div class="st-row-info">
                            <div class="st-row-label"><?= $TH ? 'ชื่อหน่วยงาน / องค์กร' : 'Organization Name' ?></div>
                            <div class="st-row-desc"><?= $TH ? 'แสดงในรายงาน หัวจดหมาย และหน้า Login' : 'Shown in reports, letterheads, and login page' ?></div>
                        </div>
                        <div class="st-row-ctrl">
                            <input type="text" id="org_name"
                                data-key="org_name" data-sec="general"
                                data-label="<?= $TH ? 'หน่วยงาน' : 'Organization' ?>"
                                class="st-input" style="width:300px">
                        </div>
                    </div>
                </div>
                <div class="st-foot">
                    <div class="st-foot-hint" id="foot-hint-general"><i class="fas fa-circle" style="font-size:7px"></i><?= $TH ? 'มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก' : 'Unsaved changes' ?></div>
                    <button class="st-save-btn" id="save-btn-general" onclick="confirmSave('general')">
                        <i class="fas fa-floppy-disk"></i><?= $TH ? 'บันทึกทั่วไป' : 'Save General' ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── IFRAME / 3D ────────────────────────────────────────── -->
        <div class="st-panel" id="panel-iframe">
            <div class="st-card" id="card-iframe">
                <div class="st-card-hdr">
                    <div class="st-card-hdr-ic" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-cube"></i></div>
                    <span class="st-card-hdr-title">Iframe / 3D Embed Config</span>
                    <span class="st-card-hdr-badge" id="badge-iframe">
                        <i class="fas fa-circle" style="font-size:6px"></i> <?= $TH ? 'มีการเปลี่ยนแปลง' : 'Unsaved' ?>
                    </span>
                </div>
                <div class="st-card-body" style="padding-bottom:16px">
                    <div style="font-size:11px;color:var(--c3);padding:10px 0 14px;border-bottom:1px solid var(--border)"><?= $TH ? 'กำหนดค่าพารามิเตอร์สำหรับ iframe จาก Kiri Engine และ embed อื่น ๆ' : 'Configure iframe parameters for Kiri Engine and other embeds' ?></div>

                    <div style="padding:14px 0;border-bottom:1px solid var(--border)">
                        <div style="font-size:12px;font-weight:700;color:var(--c1);margin-bottom:10px;display:flex;align-items:center;gap:6px"><i class="fas fa-cog" style="color:#7c3aed"></i> Kiri Engine Parameters</div>
                        <div class="st-grid2">
                            <div class="st-field">
                                <label>Background Theme</label>
                                <select id="iframe_kiri_bg_theme" data-key="iframe_kiri_bg_theme" data-sec="iframe" data-label="BG Theme" class="st-select">
                                    <option value="transparent">transparent</option>
                                    <option value="dark">dark</option>
                                    <option value="light">light</option>
                                    <option value="gradient">gradient</option>
                                </select>
                            </div>
                            <div class="st-field">
                                <label>Auto Spin Model</label>
                                <select id="iframe_kiri_auto_spin" data-key="iframe_kiri_auto_spin" data-sec="iframe" data-label="Auto Spin" class="st-select">
                                    <option value="1"><?= $TH ? 'เปิด' : 'On' ?></option>
                                    <option value="0"><?= $TH ? 'ปิด' : 'Off' ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="padding:14px 0;border-bottom:1px solid var(--border)">
                        <div style="font-size:12px;font-weight:700;color:var(--c1);margin-bottom:10px;display:flex;align-items:center;gap:6px"><i class="fas fa-code" style="color:#7c3aed"></i> <?= $TH ? 'พารามิเตอร์และขนาด' : 'Parameters & Dimensions' ?></div>
                        <div class="st-field" style="margin-bottom:12px">
                            <label><?= $TH ? 'พารามิเตอร์เพิ่มเติม' : 'Additional Parameters' ?> (key=value, &amp;)</label>
                            <input type="text" id="iframe_default_params" data-key="iframe_default_params" data-sec="iframe" data-label="Extra Params"
                                class="st-input" style="width:100%;font-family:monospace;font-size:12px"
                                placeholder="bg_theme=transparent&amp;auto_spin_model=1">
                            <div class="hint" style="font-size:10px;color:var(--c3);margin-top:4px"><?= $TH ? 'เช่น' : 'e.g.' ?> userId=1665127&amp;bg_theme=transparent</div>
                        </div>
                        <div class="st-field" style="margin-bottom:12px">
                            <label><?= $TH ? 'Iframe Attributes เริ่มต้น' : 'Default Iframe Attributes' ?></label>
                            <textarea id="iframe_default_attrs" data-key="iframe_default_attrs" data-sec="iframe" data-label="Attributes" rows="2"
                                class="st-textarea" placeholder='frameborder="0" allowfullscreen ...'></textarea>
                        </div>
                        <div class="st-grid2">
                            <div class="st-field">
                                <label>Width (px)</label>
                                <input type="number" id="iframe_width" data-key="iframe_width" data-sec="iframe" data-label="Width" class="st-num" style="width:100%" min="100" max="1920">
                            </div>
                            <div class="st-field">
                                <label>Height (px)</label>
                                <input type="number" id="iframe_height" data-key="iframe_height" data-sec="iframe" data-label="Height" class="st-num" style="width:100%" min="100" max="1080">
                            </div>
                        </div>
                    </div>

                    <!-- Parse & Transform Tool -->
                    <div class="st-parse-box" style="margin-top:14px">
                        <h4><i class="fas fa-wand-magic-sparkles" style="color:#d97706"></i><?= $TH ? 'ตัดแต่ง Iframe Code' : 'Parse & Transform Iframe Code' ?></h4>
                        <div class="st-parse-hint"><?= $TH ? 'วาง iframe code ดิบจาก Kiri Engine — ระบบจะตัด parameter เก่าออก แล้วต่อด้วยพารามิเตอร์ที่ตั้งไว้ด้านบน' : 'Paste raw iframe code from Kiri Engine — old parameters are stripped and replaced with settings above' ?></div>
                        <div class="st-field" style="margin-bottom:10px">
                            <label><?= $TH ? 'วาง Iframe Code ดิบ' : 'Paste Raw Iframe Code' ?></label>
                            <textarea id="cfgRawIframe" rows="4" class="st-textarea" placeholder="<?= $TH ? 'วาง <iframe> code ที่นี่...' : 'Paste <iframe> code here...' ?>"></textarea>
                        </div>
                        <button onclick="parseIframe()" style="padding:8px 16px;background:#d97706;color:#fff;border:none;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:10px;font-family:inherit">
                            <i class="fas fa-wand-magic-sparkles"></i><?= $TH ? 'ตัดแต่ง & สร้างใหม่' : 'Parse & Transform' ?>
                        </button>
                        <div id="cfgParsedResult" style="display:none">
                            <div class="st-field" style="margin-bottom:8px">
                                <label><?= $TH ? 'ผลลัพธ์' : 'Result' ?></label>
                                <textarea id="cfgParsedOutput" rows="3" readonly class="st-textarea st-parse-out"></textarea>
                            </div>
                            <button onclick="copyParsed()" style="padding:6px 14px;border:1.5px solid #86efac;background:#fff;color:#16a34a;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;font-family:inherit">
                                <i class="fas fa-copy"></i><?= $TH ? 'คัดลอก' : 'Copy' ?>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="st-foot">
                    <div class="st-foot-hint" id="foot-hint-iframe"><i class="fas fa-circle" style="font-size:7px"></i><?= $TH ? 'มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก' : 'Unsaved changes' ?></div>
                    <button class="st-save-btn" id="save-btn-iframe" onclick="confirmSave('iframe')">
                        <i class="fas fa-floppy-disk"></i><?= $TH ? 'บันทึก Iframe Config' : 'Save Iframe Config' ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── LOCKED ACCOUNTS ───────────────────────────────────── -->
        <div class="st-panel" id="panel-locked">
            <div class="st-card">
                <div class="st-card-hdr">
                    <div class="st-card-hdr-ic" style="background:#fef3c7;color:#d97706"><i class="fas fa-user-lock"></i></div>
                    <span class="st-card-hdr-title"><?= $TH ? 'บัญชีที่ถูกล็อค' : 'Locked Accounts' ?></span>
                    <span id="stLockCountBadge" style="display:none;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:#fee2e2;color:#dc2626"></span>
                    <button onclick="loadLockedUsers()" style="margin-left:auto;padding:6px 12px;border:1.5px solid var(--border);background:#fff;color:var(--c2);border-radius:8px;font-size:11.5px;cursor:pointer;display:flex;align-items:center;gap:5px;font-family:inherit;transition:all .15s" onmouseover="this.style.borderColor='#94a3b8'" onmouseout="this.style.borderColor='var(--border)'">
                        <i class="fas fa-sync-alt"></i><?= $TH ? 'รีเฟรช' : 'Refresh' ?>
                    </button>
                </div>
                <div id="stLockedArea">
                    <div class="st-empty"><i class="fas fa-spinner fa-spin"></i><p><?= $TH ? 'กำลังโหลด...' : 'Loading...' ?></p></div>
                </div>
            </div>
        </div>

        <!-- ── DANGER ZONE ───────────────────────────────────────── -->
        <div class="st-panel" id="panel-danger">
            <div class="st-card" style="border-color:#fca5a5">
                <div class="st-card-hdr" style="background:linear-gradient(to bottom,#fef2f2,#fee2e2)">
                    <div class="st-card-hdr-ic" style="background:#fee2e2;color:#dc2626"><i class="fas fa-exclamation-triangle"></i></div>
                    <span class="st-card-hdr-title" style="color:#dc2626"><?= $TH ? 'Danger Zone — ดำเนินการด้วยความระมัดระวัง' : 'Danger Zone — Proceed with caution' ?></span>
                </div>
                <div>
                    <div class="st-danger-row">
                        <div>
                            <div class="st-danger-title"><?= $TH ? 'ล้างล็อกเก่า (>7 วัน)' : 'Clear Old Logs (>7 days)' ?></div>
                            <div class="st-danger-desc"><?= $TH ? 'ลบไฟล์บันทึกระบบที่เก่ากว่า 7 วัน ไม่สามารถกู้คืนได้' : 'Delete system log files older than 7 days. This cannot be undone.' ?></div>
                        </div>
                        <button class="st-danger-btn" onclick="dangerClearLogs()"><i class="fas fa-trash"></i><?= $TH ? 'ล้างล็อก' : 'Clear Logs' ?></button>
                    </div>
                    <div class="st-danger-row">
                        <div>
                            <div class="st-danger-title"><?= $TH ? 'ล้างการล็อกบัญชีทั้งหมด' : 'Unlock All Accounts' ?></div>
                            <div class="st-danger-desc"><?= $TH ? 'ปลดล็อคบัญชีผู้ใช้ทุกบัญชีที่ถูกล็อคอยู่ในขณะนี้' : 'Force-unlock every currently locked user account immediately.' ?></div>
                        </div>
                        <button class="st-danger-btn" onclick="dangerUnlockAll()"><i class="fas fa-unlock"></i><?= $TH ? 'ปลดล็อคทั้งหมด' : 'Unlock All' ?></button>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /#stPanelContainer -->
</div><!-- /.st-layout -->

<script>
const TH = <?= $TH ? 'true' : 'false' ?>;

/* ─── Section config ─────────────────────────────────────────────────── */
const SECS = {
    security: { label: TH?'ความปลอดภัย':'Security',       saveLabel: TH?'บันทึกความปลอดภัย':'Save Security' },
    general:  { label: TH?'ทั่วไป':'General',              saveLabel: TH?'บันทึกทั่วไป':'Save General'      },
    iframe:   { label: 'Iframe / 3D Config',               saveLabel: TH?'บันทึก Iframe Config':'Save Iframe Config' },
};
/* Maps data-key "3d_iframe" from API → our section "iframe" */
const KEY_TO_SEC = { '3d_iframe': 'iframe' };

let origVals  = {};
let secDirty  = { security: false, general: false, iframe: false };
let modalCb   = null;

/* ─── Panel switching ────────────────────────────────────────────────── */
function switchPanel(id) {
    document.querySelectorAll('.st-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.st-nav-item').forEach(n => n.classList.remove('active'));
    const panel = document.getElementById('panel-' + id);
    const nav   = document.querySelector(`.st-nav-item[data-panel="${id}"]`);
    if (panel) panel.classList.add('active');
    if (nav)   nav.classList.add('active');
    if (id === 'locked') loadLockedUsers();
}

/* ─── Toast ──────────────────────────────────────────────────────────── */
function stToast(msg, type = 'ok') {
    const t  = document.getElementById('stToast');
    const ic = { ok: 'fa-check-circle', err: 'fa-times-circle', warn: 'fa-exclamation-circle' }[type] || 'fa-info-circle';
    t.className = 'st-toast ' + type;
    t.innerHTML = `<i class="fas ${ic}"></i> ${msg}`;
    t.classList.add('show');
    clearTimeout(t._tmr);
    t._tmr = setTimeout(() => t.classList.remove('show'), 3400);
}

/* ─── Modal ──────────────────────────────────────────────────────────── */
function stModal(opt) {
    const ic   = document.getElementById('stModalIc');
    const cmap = { warn: { bg: '#fef3c7', color: '#d97706', icon: 'fa-exclamation-triangle' },
                   danger: { bg: '#fee2e2', color: '#dc2626', icon: 'fa-exclamation-triangle' },
                   info:   { bg: '#dbeafe', color: '#2563eb', icon: 'fa-info-circle' } };
    const c = cmap[opt.type || 'warn'];
    ic.style.cssText = `background:${c.bg};color:${c.color}`;
    ic.innerHTML = `<i class="fas ${c.icon}"></i>`;
    document.getElementById('stModalTitle').textContent = opt.title || '';
    document.getElementById('stModalBody').innerHTML    = opt.body  || '';
    const ok = document.getElementById('stModalOk');
    ok.textContent = opt.okText || (TH ? 'ยืนยัน' : 'Confirm');
    ok.className   = 'st-btn-ok ' + (opt.okClass || 'purple');
    modalCb = opt.onOk || null;
    ok.onclick = () => { stCloseModal(); if (modalCb) modalCb(); };
    document.getElementById('stModal').classList.add('show');
}
function stCloseModal() {
    document.getElementById('stModal').classList.remove('show');
    modalCb = null;
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') stCloseModal(); });

/* ─── Load settings ──────────────────────────────────────────────────── */
async function loadSettings() {
    try {
        const d = await apiFetch('/v1/api/settings.php');
        if (!d.success) return;
        const flat = {};
        Object.values(d.data).forEach(cat => {
            if (Array.isArray(cat)) cat.forEach(s => { flat[s.key] = s.value; });
        });
        Object.entries(flat).forEach(([k, v]) => {
            const el = document.getElementById(k);
            if (!el) return;
            if (el.type === 'checkbox') el.checked = !!v;
            else el.value = v ?? '';
        });
        origVals = { ...flat };
        Object.keys(secDirty).forEach(s => { secDirty[s] = false; refreshDirty(s); });
        syncLockoutSub();
        syncDemoLabel();
    } catch(e) { console.error('loadSettings', e); }
}

/* ─── Dirty tracking ─────────────────────────────────────────────────── */
function refreshDirty(sec) {
    const isDirty   = secDirty[sec];
    const card      = document.getElementById('card-' + sec);
    const badge     = document.getElementById('badge-' + sec);
    const footHint  = document.getElementById('foot-hint-' + sec);
    const navItem   = document.querySelector(`.st-nav-item[data-panel="${sec}"]`);
    if (card)     card.classList.toggle('dirty', isDirty);
    if (badge)    badge.style.display = isDirty ? 'flex' : 'none';
    if (footHint) footHint.classList.toggle('show', isDirty);
    if (navItem)  navItem.classList.toggle('dirty', isDirty);
}

document.querySelectorAll('[data-key][data-sec]').forEach(el => {
    const ev = el.type === 'checkbox' ? 'change' : (el.tagName === 'SELECT' ? 'change' : 'input');
    el.addEventListener(ev, () => {
        secDirty[el.dataset.sec] = true;
        refreshDirty(el.dataset.sec);
        if (el.id === 'account_lockout_enabled') syncLockoutSub();
        if (el.id === 'demo_accounts_enabled')   syncDemoLabel();
    });
});

function syncLockoutSub() {
    const on  = document.getElementById('account_lockout_enabled').checked;
    const sub = document.getElementById('stLockoutSub');
    sub.classList.toggle('off', !on);
    sub.querySelectorAll('input[type="number"]').forEach(i => i.disabled = !on);
}
function syncDemoLabel() {
    const el  = document.getElementById('demo_accounts_enabled');
    const lbl = document.getElementById('stDemoLabel');
    if (!el || !lbl) return;
    lbl.textContent = el.checked ? (TH ? 'เปิด' : 'On') : (TH ? 'ปิด' : 'Off');
    lbl.style.color = el.checked ? '#059669' : '#94a3b8';
}

/* ─── Change detection ───────────────────────────────────────────────── */
function getChanges(sec) {
    const list = [];
    document.querySelectorAll(`[data-key][data-sec="${sec}"]`).forEach(el => {
        const key = el.dataset.key, label = el.dataset.label || key;
        let nv, ov = origVals[key];
        if (el.type === 'checkbox') {
            nv = el.checked;
            if (typeof ov === 'undefined') ov = false;
            if (nv !== !!ov) list.push({ label, o: !!ov ? (TH?'เปิด':'ON') : (TH?'ปิด':'OFF'), n: nv ? (TH?'เปิด':'ON') : (TH?'ปิด':'OFF') });
        } else {
            nv = el.type === 'number' ? (parseInt(el.value) || 0) : el.value;
            const os = ov != null ? String(ov) : '', ns = String(nv);
            if (os !== ns) list.push({ label, o: os || '—', n: ns || '—' });
        }
    });
    return list;
}

function changesHtml(changes) {
    if (!changes.length)
        return `<div style="text-align:center;color:#94a3b8;padding:12px;font-size:12px"><i class="fas fa-check-circle" style="margin-right:4px;color:#22c55e"></i>${TH?'ไม่มีการเปลี่ยนแปลง':'No changes detected'}</div>`;
    return `<div class="st-changes">${changes.map(c => `
        <div class="st-chg-item">
            <div class="chk-ic"><i class="fas fa-pen"></i></div>
            <span class="st-chg-label" title="${esc(c.label)}">${esc(c.label)}</span>
            <span class="st-chg-old" title="${esc(c.o)}">${esc(c.o)}</span>
            <i class="fas fa-arrow-right" style="color:#cbd5e1;font-size:10px;flex-shrink:0"></i>
            <span class="st-chg-new" title="${esc(c.n)}">${esc(c.n)}</span>
        </div>`).join('')}</div>`;
}

/* ─── Save flow ──────────────────────────────────────────────────────── */
function confirmSave(sec) {
    const changes = getChanges(sec);
    const sName   = SECS[sec]?.label || sec;
    stModal({
        type:    changes.length ? 'warn' : 'info',
        title:   `${TH ? 'ยืนยันบันทึก — ' : 'Confirm Save — '}${sName}`,
        body:    `<div style="margin-bottom:8px">${TH ? `ต้องการบันทึกการตั้งค่า <strong>${sName}</strong> หรือไม่?` : `Save <strong>${sName}</strong> settings?`}</div>${changesHtml(changes)}`,
        okText:  TH ? 'บันทึก' : 'Save',
        okClass: 'purple',
        onOk:    () => doSave(sec),
    });
}

async function doSave(sec) {
    const btn = document.getElementById('save-btn-' + sec);
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${TH?'กำลังบันทึก...':'Saving...'}`; }

    const payload = {};
    /* Map sec "iframe" → API category "3d_iframe" keys via actual data-key attributes */
    document.querySelectorAll(`[data-key][data-sec="${sec}"]`).forEach(el => {
        const k = el.dataset.key;
        if (el.type === 'checkbox') payload[k] = el.checked;
        else if (el.type === 'number') payload[k] = parseInt(el.value) || 0;
        else payload[k] = el.value;
    });

    try {
        const d = await apiFetch('/v1/api/settings.php', { method: 'POST', body: JSON.stringify(payload) });
        if (!d.success) throw new Error(d.error || 'Save failed');
        stToast(`${TH ? 'บันทึก ' : ''}${SECS[sec]?.label || sec}${TH ? ' เรียบร้อย' : ' saved'}`, 'ok');
        Object.entries(payload).forEach(([k, v]) => { origVals[k] = v; });
        secDirty[sec] = false;
        refreshDirty(sec);
    } catch(e) {
        stToast(e.message, 'err');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}

/* ─── Locked users ───────────────────────────────────────────────────── */
async function loadLockedUsers() {
    const area = document.getElementById('stLockedArea');
    area.innerHTML = `<div class="st-empty"><i class="fas fa-spinner fa-spin"></i><p>${TH?'กำลังโหลด...':'Loading...'}</p></div>`;
    try {
        const d = await apiFetch('/v1/api/auth.php?action=locked_users');
        if (!d.success) throw new Error(d.error);
        const users = d.data || [];

        /* Update nav count badge */
        const cnt  = document.getElementById('stLockCount');
        const cntB = document.getElementById('stLockCountBadge');
        [cnt, cntB].forEach(el => {
            if (!el) return;
            el.textContent = users.length || '';
            el.style.display = users.length ? 'inline-flex' : 'none';
        });

        if (!users.length) {
            area.innerHTML = `<div class="st-empty"><i class="fas fa-check-circle" style="color:#22c55e"></i><p>${TH?'ไม่มีบัญชีที่ถูกล็อค':'No locked accounts'}</p></div>`;
            return;
        }
        area.innerHTML = users.map(u => {
            const name    = esc(u.full_name_th || ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.username);
            const initials = (u.first_name?.[0] || u.username?.[0] || '?').toUpperCase();
            return `<div class="st-lock-card">
                <div class="st-lock-av">${initials}</div>
                <div class="st-lock-info">
                    <div class="st-lock-name">${esc(u.username)} <span style="font-weight:400;color:var(--c3);font-size:12px">— ${name}</span></div>
                    <div class="st-lock-sub">
                        <span>${TH?'ลองผิด':'Attempts'}: <strong>${u.login_attempts}</strong></span>
                        &nbsp;·&nbsp;
                        <span>${TH?'ล็อคถึง':'Until'}: <strong>${esc(u.locked_until || '—')}</strong></span>
                    </div>
                    <span class="st-lock-badge"><i class="fas fa-lock" style="font-size:8px"></i>${TH?'ถูกล็อค':'Locked'}</span>
                </div>
                <button class="st-unlock-btn" onclick="confirmUnlock(${u.id},'${esc(u.username)}',this)">
                    <i class="fas fa-unlock"></i>${TH?'ปลดล็อค':'Unlock'}
                </button>
            </div>`;
        }).join('');
    } catch(e) {
        area.innerHTML = `<div class="st-empty"><i class="fas fa-exclamation-triangle" style="color:#dc2626"></i><p>${esc(e.message)}</p></div>`;
    }
}

function confirmUnlock(id, uname, btn) {
    stModal({
        type:    'danger',
        title:   TH ? 'ยืนยันปลดล็อค' : 'Confirm Unlock',
        body:    TH
            ? `ต้องการปลดล็อคบัญชี <strong>${uname}</strong>?<br><span style="font-size:11px;color:#94a3b8">ผู้ใช้จะสามารถเข้าสู่ระบบได้อีกครั้ง</span>`
            : `Unlock account <strong>${uname}</strong>?<br><span style="font-size:11px;color:#94a3b8">The user will be able to log in again.</span>`,
        okText:  TH ? 'ปลดล็อค' : 'Unlock',
        okClass: 'danger',
        onOk:    () => doUnlock(id, btn),
    });
}

async function doUnlock(id, btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
    try {
        const d = await apiFetch('/v1/api/auth.php?action=unlock_user', { method: 'POST', body: JSON.stringify({ user_id: id }) });
        if (!d.success) throw new Error(d.error);
        stToast(TH ? 'ปลดล็อคสำเร็จ' : 'Account unlocked', 'ok');
        loadLockedUsers();
    } catch(e) {
        stToast(e.message, 'err');
        if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fas fa-unlock"></i>${TH?'ปลดล็อค':'Unlock'}`; }
    }
}

/* ─── Danger Zone ────────────────────────────────────────────────────── */
function dangerClearLogs() {
    stModal({
        type:    'danger',
        title:   TH ? 'ล้างล็อกเก่า' : 'Clear Old Logs',
        body:    TH
            ? 'ต้องการลบไฟล์บันทึกระบบที่เก่ากว่า 7 วันหรือไม่?<br><span style="font-size:11px;color:#dc2626">การดำเนินการนี้ไม่สามารถกู้คืนได้</span>'
            : 'Delete log files older than 7 days?<br><span style="font-size:11px;color:#dc2626">This action cannot be undone.</span>',
        okText:  TH ? 'ล้างล็อก' : 'Clear Logs',
        okClass: 'danger',
        onOk: async () => {
            try {
                const d = await apiFetch('/v1/pages/system-monitor.php?action=clear_logs', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                stToast(d.message || (TH ? 'ล้างสำเร็จ' : 'Cleared'), 'ok');
            } catch(e) { stToast(e.message, 'err'); }
        },
    });
}

function dangerUnlockAll() {
    stModal({
        type:    'danger',
        title:   TH ? 'ปลดล็อคทุกบัญชี' : 'Unlock All Accounts',
        body:    TH
            ? 'ต้องการปลดล็อคบัญชีผู้ใช้ทุกบัญชีที่ถูกล็อคอยู่หรือไม่?'
            : 'Force-unlock every currently locked account?',
        okText:  TH ? 'ปลดล็อคทั้งหมด' : 'Unlock All',
        okClass: 'danger',
        onOk: async () => {
            try {
                const d = await apiFetch('/v1/api/auth.php?action=unlock_all', { method: 'POST' });
                if (!d.success) throw new Error(d.error || 'Failed');
                stToast(TH ? 'ปลดล็อคทุกบัญชีแล้ว' : 'All accounts unlocked', 'ok');
                loadLockedUsers();
            } catch(e) { stToast(e.message, 'err'); }
        },
    });
}

/* ─── Iframe Parse & Transform ───────────────────────────────────────── */
function parseIframe() {
    const raw = document.getElementById('cfgRawIframe').value.trim();
    if (!raw) { stToast(TH ? 'กรุณาวาง iframe code' : 'Please paste iframe code', 'err'); return; }
    const tags = raw.match(/<iframe[\s\S]*?<\/iframe>/gi);
    if (!tags?.length) { stToast(TH ? 'ไม่พบ <iframe> tag' : 'No <iframe> tag found', 'err'); return; }
    const results = [];
    tags.forEach(tag => {
        const srcM = tag.match(/src\s*=\s*["']([^"']+)["']/i);
        if (!srcM) return;
        let base = srcM[1].split('?')[0].replace(/\/sharemodel(\/|$)/gi, '/embed$1');
        const params  = {};
        const bgEl    = document.getElementById('iframe_kiri_bg_theme');
        const spinEl  = document.getElementById('iframe_kiri_auto_spin');
        params['bg_theme']         = bgEl?.value  || 'transparent';
        params['auto_spin_model']  = spinEl?.value || '1';
        const extra = document.getElementById('iframe_default_params')?.value.trim() || '';
        if (extra) extra.split('&').forEach(p => { const [k, v] = p.split('='); if (k) params[k] = v || ''; });
        const origQ = srcM[1].split('?')[1] || '';
        if (origQ) origQ.split('&').forEach(p => { const [k, v] = p.split('='); if (k === 'userId' && v) params['userId'] = v; });
        const newUrl = base + '?' + Object.entries(params).map(([k, v]) => `${k}=${v}`).join('&');
        const attrs  = (document.getElementById('iframe_default_attrs')?.value.trim()) || 'allowfullscreen';
        const w = document.getElementById('iframe_width')?.value  || '640';
        const h = document.getElementById('iframe_height')?.value || '480';
        results.push(`<iframe src="${newUrl}" width="${w}" height="${h}" ${attrs}></iframe>`);
    });
    if (results.length) {
        document.getElementById('cfgParsedOutput').value = results.join('\n\n');
        document.getElementById('cfgParsedResult').style.display = 'block';
        stToast(`${TH ? 'ตัดแต่ง ' : 'Transformed '}${results.length} iframe${results.length > 1 ? 's' : ''}`, 'ok');
    } else {
        stToast(TH ? 'ไม่สามารถแปลง iframe ได้' : 'Could not transform iframe', 'err');
    }
}

function copyParsed() {
    const val = document.getElementById('cfgParsedOutput').value;
    navigator.clipboard?.writeText(val).then(() => stToast(TH ? 'คัดลอกแล้ว' : 'Copied', 'ok'));
}

/* ─── Helpers ────────────────────────────────────────────────────────── */
function esc(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }

/* ─── Init ───────────────────────────────────────────────────────────── */
loadSettings();
</script>

<?php Layout::endContent(); Layout::footer(); ?>
