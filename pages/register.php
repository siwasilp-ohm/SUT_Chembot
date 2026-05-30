<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if ($user) { header('Location: /v1/'); exit; }
$lang = I18n::getCurrentLang();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('register_title'); ?> - <?php echo __('app_name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root{--accent:#1a8a5c;--accent-h:#15704b;--sb-bg:#2d2d2d}
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter','Noto Sans Thai',sans-serif}
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f5f5f5;padding:20px}
    .reg-card{width:100%;max-width:640px;background:#fff;border-radius:8px;border:1px solid #e0e0e0;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
    .reg-hdr{text-align:center;margin-bottom:24px}
    .reg-logo{width:52px;height:52px;background:var(--accent);border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;margin:0 auto 12px}
    .reg-hdr h2{font-size:22px;font-weight:700;color:#333}
    .reg-hdr p{font-size:13px;color:#999}
    .fg{margin-bottom:14px}
    .fg label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px}
    .fg input,.fg select{width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:5px;font-size:13px;font-family:inherit;transition:border .15s,box-shadow .15s}
    .fg input:focus,.fg select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(26,138,92,.15)}
    /* ── Lab selector trigger ── */
    .sl-trigger{
        width:100%;min-height:42px;border:1.5px solid #ddd;border-radius:8px;
        background:#fff;display:flex;align-items:center;gap:8px;
        padding:7px 10px 7px 12px;cursor:pointer;font-family:inherit;text-align:left;
        transition:border-color .15s,box-shadow .15s;
    }
    .sl-trigger:hover{border-color:#aaa}
    .sl-trigger.has-val{border-color:var(--accent);box-shadow:0 0 0 2px rgba(26,138,92,.12)}
    .sl-trig-ic{width:26px;height:26px;border-radius:6px;background:#e8f5ef;color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
    .sl-trig-txt{flex:1;min-width:0;display:flex;align-items:center;gap:7px;overflow:hidden}
    .sl-trig-name{font-size:12.5px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;min-width:0}
    .sl-trig-code{font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;background:#d1fae5;color:#065f46;white-space:nowrap;flex-shrink:0;letter-spacing:.3px}
    .sl-trig-ph{font-size:12.5px;color:#aaa;flex:1}
    .sl-trig-caret{font-size:11px;color:#94a3b8;flex-shrink:0}
    /* ── Modal overlay ── */
    .sl-ov{
        position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:900;
        display:none;align-items:center;justify-content:center;padding:16px;
        backdrop-filter:blur(2px);
    }
    .sl-ov.open{display:flex}
    .sl-modal{
        background:#fff;border-radius:16px;width:100%;max-width:560px;
        box-shadow:0 24px 64px rgba(0,0,0,.22);display:flex;flex-direction:column;
        max-height:90vh;overflow:hidden;
    }
    /* header */
    .sl-hdr{
        display:flex;align-items:center;gap:12px;padding:16px 18px 14px;
        border-bottom:1px solid #f0f4f2;flex-shrink:0;
    }
    .sl-hdr-ic{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#22c55e);
        display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0}
    .sl-hdr-txt{flex:1;min-width:0}
    .sl-hdr-title{font-size:15px;font-weight:700;color:#1e293b}
    .sl-hdr-sub{font-size:11px;color:#94a3b8;margin-top:1px}
    .sl-close{width:30px;height:30px;border:none;background:#f1f5f9;border-radius:8px;
        cursor:pointer;color:#64748b;font-size:13px;display:flex;align-items:center;justify-content:center;transition:background .1s;flex-shrink:0}
    .sl-close:hover{background:#e2e8f0;color:#1e293b}
    /* nav / back bar */
    .sl-nav{
        display:flex;align-items:center;gap:8px;padding:8px 14px;
        border-bottom:1px solid #f0f4f2;background:#f8faf9;flex-shrink:0;
    }
    .sl-back{background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:6px;
        font-size:12px;font-weight:600;color:var(--accent);padding:4px 8px;border-radius:6px;font-family:inherit;transition:background .1s}
    .sl-back:hover{background:#e8f5ef}
    .sl-nav-div{font-size:12px;color:#94a3b8}
    .sl-nav-cur{font-size:12px;font-weight:600;color:#374151;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    /* search */
    .sl-search-wrap{padding:10px 14px;border-bottom:1px solid #f0f4f2;flex-shrink:0}
    .sl-search{
        width:100%;padding:8px 12px 8px 34px;border:1.5px solid #e2e8f0;border-radius:8px;
        font-size:12.5px;font-family:inherit;color:#1e293b;
        background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 10px center;
    }
    .sl-search:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(26,138,92,.12)}
    /* body area */
    .sl-body{flex:1;overflow-y:auto;min-height:0}
    /* Level 1 grid — 2-column division cards */
    .sl-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:14px}
    /* Division card — sbm vertical card style */
    .sl-div-card{
        display:flex;flex-direction:column;padding:14px 14px 34px;
        border:1.5px solid #e8ecf0;border-radius:14px;cursor:pointer;background:#fff;
        border-left-width:4px;
        transition:box-shadow .15s,background .1s,transform .1s;
        position:relative;min-height:110px;
    }
    .sl-div-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.10);background:#fafffe;transform:translateY(-1px)}
    .sl-div-card.has-sel{background:#f0fdf4}
    .sl-div-ic{
        width:36px;height:36px;border-radius:10px;display:flex;align-items:center;
        justify-content:center;font-size:15px;margin-bottom:9px;flex-shrink:0;
    }
    .sl-bld-num{font-size:28px;font-weight:900;line-height:1;letter-spacing:-1px;margin-bottom:6px}
    .sl-div-title{font-size:11px;font-weight:700;color:#1e293b;line-height:1.45;flex:1;margin-bottom:6px}
    .sl-div-foot{
        position:absolute;bottom:10px;left:14px;right:28px;
        display:flex;align-items:center;gap:5px;flex-wrap:wrap;
    }
    .sl-div-badge{
        display:inline-flex;align-items:center;gap:4px;
        font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;
        background:#f1f5f9;color:#64748b;
    }
    .sl-div-badge i{font-size:8px}
    .sl-div-badge-sel{background:#fef3c7;color:#92400e}
    .sl-div-arrow{
        position:absolute;bottom:12px;right:12px;
        font-size:10px;color:#d1d5db;transition:color .12s,right .12s;
    }
    .sl-div-card:hover .sl-div-arrow{color:#94a3b8;right:10px}
    /* trigger count badge / room summary */
    .sl-trig-cnt{font-size:10px;font-weight:700;padding:2px 8px;border-radius:12px;background:var(--accent);color:#fff;flex-shrink:0}
    .sl-trig-rooms{font-size:11px;color:#374151;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    /* room list (view 2) — sbm style */
    .sl-unit-hdr{
        font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;
        letter-spacing:.6px;padding:9px 18px 6px;background:#fff;
        border-bottom:1px solid #f0f4f2;position:sticky;top:0;z-index:1;
    }
    .sl-room{
        display:flex;align-items:center;gap:12px;padding:12px 18px;
        cursor:pointer;transition:background .12s;border-bottom:1px solid #f1f5f9;
    }
    .sl-room:hover{background:#f8fffe}
    .sl-room.checked{background:#f0fdf4}
    /* square checkbox */
    .sl-room-chk{
        width:20px;height:20px;border-radius:5px;border:2px solid #d1d5db;flex-shrink:0;
        display:flex;align-items:center;justify-content:center;transition:all .12s;
    }
    .sl-room.checked .sl-room-chk{border-color:var(--accent);background:var(--accent)}
    .sl-room-chk i{font-size:9px;color:#fff;opacity:0;transition:opacity .1s}
    .sl-room.checked .sl-room-chk i{opacity:1}
    /* room icon (sbm-style rounded square) */
    .sl-room-icon{
        width:44px;height:44px;border-radius:12px;background:#e8f5ef;color:var(--accent);
        display:flex;align-items:center;justify-content:center;font-size:17px;
        flex-shrink:0;transition:background .12s,color .12s;
    }
    .sl-room.checked .sl-room-icon{background:#bbf7d0;color:#065f46}
    /* room body */
    .sl-room-body{flex:1;min-width:0}
    .sl-room-top{display:flex;align-items:center;gap:8px;margin-bottom:4px}
    .sl-room-code{font-size:14px;font-weight:800;color:var(--accent);letter-spacing:.3px;line-height:1}
    .sl-room.checked .sl-room-code{color:#065f46}
    .sl-room-nocode{font-size:13px;font-weight:700;color:#374151}
    .sl-room-floor{font-size:9.5px;color:#64748b;font-weight:600;padding:2px 7px;background:#f1f5f9;border-radius:10px;flex-shrink:0}
    .sl-room-name{font-size:12px;color:#94a3b8;line-height:1.35}
    .sl-room.checked .sl-room-name{color:#374151}
    /* empty state */
    .sl-empty{text-align:center;padding:36px 16px;font-size:12px;color:#94a3b8}
    .sl-empty i{display:block;font-size:30px;margin-bottom:8px;opacity:.3}
    /* confirm footer */
    .sl-footer{
        border-top:2px solid #d1fae5;background:#f0fdf4;flex-shrink:0;
        display:none;padding:10px 16px 12px;align-items:center;gap:10px;
    }
    .sl-footer.visible{display:flex}
    .sl-footer-info{flex:1;min-width:0}
    .sl-footer-room{font-size:12px;font-weight:700;color:#065f46;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .sl-footer-bld{font-size:10px;color:#94a3b8;margin-top:1px}
    .sl-confirm{
        padding:9px 20px;background:var(--accent);color:#fff;border:none;border-radius:8px;
        font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
        display:flex;align-items:center;gap:7px;flex-shrink:0;transition:background .12s;
    }
    .sl-confirm:hover{background:var(--accent-h)}
    .fg input::placeholder{color:#bbb;font-style:italic}
    .fg-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:480px){.fg-row{grid-template-columns:1fr;gap:8px}.reg-card{padding:20px 16px}}
    .reg-btn{width:100%;padding:11px;background:var(--accent);color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:6px}
    .reg-btn:hover{background:var(--accent-h)}
    .reg-btn:disabled{opacity:.6}
    .chk{display:flex;align-items:start;gap:8px;margin-bottom:16px;font-size:12px;color:#777}
    .chk a{color:var(--accent);font-weight:500}
    .msg{padding:10px 14px;border-radius:6px;font-size:13px;margin-top:12px;display:none;align-items:center;gap:6px}
    .msg.show{display:flex}
    .msg-ok{background:#e8f5ef;border:1px solid #c8e6d8;color:#2e7d32}
    .msg-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
    .reg-foot{text-align:center;font-size:13px;color:#999;margin-top:16px}
    .reg-foot a{color:var(--accent);font-weight:500}
    .lang-sw{position:fixed;top:16px;right:16px;display:flex;gap:4px}
    .lang-sw a{padding:4px 10px;border-radius:4px;font-size:11px;font-weight:500;color:#999;text-decoration:none;transition:all .12s}
    .lang-sw a:hover{color:#333;background:#eee}
    .lang-sw a.active{color:#fff;background:var(--accent)}
    /* ── Org section ── */
    .org-sec{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin-bottom:14px}
    .org-sec-hdr{font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;gap:7px}
    .org-sec-hdr i{color:#6366f1;font-size:12px}
    .org-bc{display:none;align-items:center;gap:6px;font-size:11px;color:#64748b;background:#fff;border:1px solid #e2e8f0;border-radius:7px;padding:7px 10px;margin-top:10px}
    .org-bc i{color:var(--accent);font-size:10px}
    .fg select:disabled{background:#f1f5f9;color:#94a3b8;cursor:not-allowed}
    </style>
</head>
<body>
    <div class="lang-sw">
        <a href="?lang=th" class="<?php echo $lang==='th'?'active':''; ?>">🇹🇭 TH</a>
        <a href="?lang=en" class="<?php echo $lang==='en'?'active':''; ?>">🇬🇧 EN</a>
    </div>
    <div class="reg-card">
        <div class="reg-hdr">
            <div class="reg-logo"><i class="fas fa-flask-vial"></i></div>
            <h2><?php echo __('register_title'); ?></h2>
            <p><?php echo __('register_subtitle'); ?></p>
        </div>
        <form id="registerForm">
            <div class="fg-row">
                <div class="fg"><label><?php echo __('register_first_name'); ?></label><input type="text" name="first_name" required></div>
                <div class="fg"><label><?php echo __('register_last_name'); ?></label><input type="text" name="last_name" required></div>
            </div>
            <div class="fg"><label><?php echo __('register_email'); ?></label><input type="email" name="email" required></div>
            <div class="fg"><label><?php echo __('register_username'); ?></label><input type="text" name="username" required></div>
            <div class="fg-row">
                <div class="fg"><label><?php echo __('register_password'); ?></label><input type="password" name="password" required></div>
                <div class="fg"><label><?php echo __('register_password_confirm'); ?></label><input type="password" name="password_confirm" required></div>
            </div>
            <div class="fg"><label><?php echo __('register_phone'); ?></label><input type="tel" name="phone"></div>
            <div class="fg">
                <label><?php echo __('register_lab'); ?></label>
                <button type="button" class="sl-trigger" id="slTrigger" onclick="slOpen()">
                    <div class="sl-trig-ic"><i class="fas fa-flask"></i></div>
                    <div class="sl-trig-txt" id="slTriggerTxt">
                        <span class="sl-trig-ph"><?= $lang==='th'?'เลือกห้องปฏิบัติการ...':'Select laboratory...' ?></span>
                    </div>
                    <i class="fas fa-chevron-right sl-trig-caret"></i>
                </button>
                <input type="hidden" name="room_id" id="roomIdInput" value="">
            </div>
            <!-- ── สังกัดองค์กร ── -->
            <div class="org-sec">
                <div class="org-sec-hdr"><i class="fas fa-sitemap"></i><?= $lang==='th'?'สังกัดองค์กร':'Organization' ?></div>
                <div class="fg-row">
                    <div class="fg" style="margin-bottom:10px">
                        <label><i class="fas fa-building" style="margin-right:4px;font-size:10px;color:#6366f1"></i><?= $lang==='th'?'ศูนย์':'Center' ?></label>
                        <select id="rCenter" class="fg" style="padding:9px 12px;border:1px solid #ddd;border-radius:5px;font-size:13px;font-family:inherit;width:100%" onchange="orgChange('center')">
                            <option value="">-- <?= $lang==='th'?'เลือกศูนย์':'Select Center' ?> --</option>
                        </select>
                    </div>
                    <div class="fg" style="margin-bottom:10px">
                        <label><i class="fas fa-sitemap" style="margin-right:4px;font-size:10px;color:#e65100"></i><?= $lang==='th'?'ฝ่าย':'Division' ?></label>
                        <select id="rDivision" name="department" style="padding:9px 12px;border:1px solid #ddd;border-radius:5px;font-size:13px;font-family:inherit;width:100%" onchange="orgChange('division')" disabled>
                            <option value="">-- <?= $lang==='th'?'เลือกฝ่าย':'Select Division' ?> --</option>
                        </select>
                    </div>
                </div>
                <div class="fg-row">
                    <div class="fg" style="margin-bottom:0">
                        <label><i class="fas fa-layer-group" style="margin-right:4px;font-size:10px;color:#1a8a5c"></i><?= $lang==='th'?'งาน':'Section' ?></label>
                        <select id="rSection" name="position" style="padding:9px 12px;border:1px solid #ddd;border-radius:5px;font-size:13px;font-family:inherit;width:100%" onchange="orgChange('section')" disabled>
                            <option value="">-- <?= $lang==='th'?'เลือกงาน':'Select Section' ?> --</option>
                        </select>
                    </div>
                    <div class="fg" style="margin-bottom:0">
                        <label><i class="fas fa-warehouse" style="margin-right:4px;font-size:10px;color:#2563eb"></i><?= $lang==='th'?'คลัง':'Store' ?></label>
                        <select id="rStore" name="store_id" style="padding:9px 12px;border:1px solid #ddd;border-radius:5px;font-size:13px;font-family:inherit;width:100%" disabled>
                            <option value="">-- <?= $lang==='th'?'เลือกคลัง':'Select Store' ?> --</option>
                        </select>
                    </div>
                </div>
                <div class="org-bc" id="orgBc"><i class="fas fa-map-marker-alt"></i><span id="orgBcTxt"></span></div>
            </div>
            <div class="chk">
                <input type="checkbox" name="terms" required style="margin-top:2px">
                <span><?php echo __('register_terms'); ?> <a href="#"><?php echo __('register_terms_link'); ?></a> <?php echo $lang==='th'?'และ':'and'; ?> <a href="#"><?php echo __('register_privacy_link'); ?></a></span>
            </div>
            <button type="submit" id="submitBtn" class="reg-btn">
                <span id="submitText"><?php echo __('register_title'); ?></span>
                <i id="submitSpinner" class="fas fa-spinner fa-spin" style="display:none"></i>
            </button>
        </form>
        <div id="successMsg" class="msg msg-ok"><i class="fas fa-check-circle"></i> <?php echo __('register_success'); ?></div>
        <div id="errorMsg" class="msg msg-err"><i class="fas fa-exclamation-circle"></i> <span id="errorText"></span></div>
        <div class="reg-foot"><?php echo __('register_has_account'); ?> <a href="/v1/pages/login.php"><?php echo __('login'); ?></a></div>
    </div>

<!-- Lab selector modal -->
<div class="sl-ov" id="slOv">
    <div class="sl-modal" id="slModal">
        <!-- header -->
        <div class="sl-hdr">
            <div class="sl-hdr-ic"><i class="fas fa-flask-vial"></i></div>
            <div class="sl-hdr-txt">
                <div class="sl-hdr-title"><?= $lang==='th'?'เลือกห้องปฏิบัติการ':'Select Laboratory' ?></div>
                <div class="sl-hdr-sub" id="slHdrSub"><?= $lang==='th'?'เลือกอาคารเพื่อดูรายการห้อง':'Select a building to browse rooms' ?></div>
            </div>
            <button type="button" class="sl-close" onclick="slClose()"><i class="fas fa-times"></i></button>
        </div>
        <!-- back nav (hidden when on grid view) -->
        <div class="sl-nav" id="slNav" style="display:none">
            <button type="button" class="sl-back" onclick="slGoGrid()">
                <i class="fas fa-arrow-left"></i>
                <span><?= $lang==='th'?'อาคารทั้งหมด':'All Buildings' ?></span>
            </button>
            <span class="sl-nav-div">/</span>
            <span class="sl-nav-cur" id="slNavCur"></span>
        </div>
        <!-- search -->
        <div class="sl-search-wrap">
            <input type="text" class="sl-search" id="slSearch"
                placeholder="<?= $lang==='th'?'ค้นหาชื่ออาคาร หรือรหัสห้อง...':'Search building name or room number...' ?>"
                autocomplete="off">
        </div>
        <!-- body: grid / room list / flat search -->
        <div class="sl-body" id="slBody">
            <div class="sl-empty"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <!-- confirm footer — shown when a room is pending -->
        <div class="sl-footer" id="slFooter">
            <div class="sl-footer-info">
                <div class="sl-footer-room" id="slFooterRoom">–</div>
                <div class="sl-footer-bld" id="slFooterBld"></div>
            </div>
            <button type="button" class="sl-confirm" id="slConfirmBtn" onclick="slConfirm()">
                <i class="fas fa-check-circle"></i>
                <?= $lang==='th'?'ยืนยัน':'Confirm' ?>
            </button>
        </div>
    </div>
</div>
<script>
const TXT = {
    required:      '<?php echo $lang==="th" ? "กรุณาเลือกห้องปฏิบัติการ" : "Please select a laboratory"; ?>',
    noData:        '<?php echo $lang==="th" ? "ไม่พบห้องปฏิบัติการ" : "No laboratories found"; ?>',
    loadingError:  '<?php echo $lang==="th" ? "ไม่สามารถโหลดรายการห้องปฏิบัติการได้" : "Unable to load laboratories"; ?>',
    pwMismatch:    '<?php echo $lang==="th" ? "รหัสผ่านไม่ตรงกัน" : "Passwords do not match"; ?>',
    serverError:   '<?php echo $lang==="th" ? "ข้อผิดพลาดของเซิร์ฟเวอร์" : "Server error"; ?>'
};
// keep alias for any legacy references
const LAB_TEXT = TXT;

// ── Lab selector state (buildings + rooms model) ────
let slBuildings   = [];    // [{id, code, shortname, name, room_count}]
let slRoomsCache  = {};    // {building_id: [{id, room_number, name, floor, room_type}]}
let slSelected    = [];    // confirmed rooms: [{id, room_number, name, floor, building_id, building_code, building_name}]
let slActiveBld   = null;  // building currently browsed: {id, code, name}
let slView        = 'grid';

const SL_BLD_COLORS = {
    'F0':'#6b7280','F1':'#3b82f6','F2':'#16a34a','F3':'#2563eb',
    'F4':'#9333ea','F5':'#ea580c','F6':'#0284c7','F6/1':'#0369a1',
    'F7':'#10b981','F9':'#0d9488','F10':'#ec4899','F11':'#f97316',
    'F12':'#dc2626','F14':'#65a30d','F16':'#d97706',
    'Farm':'#92400e','สัตว์ทดลอง':'#7c3aed','คลังสารเคมี':'#b45309',
};
function slBldColor(code){ return SL_BLD_COLORS[code] || '#374151'; }

function escapeHtml(str) {
    return String(str||'').replace(/[&<>'"]/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];
    });
}

// ── Trigger display ─────────────────────────────────
function slSetTrigger() {
    var txt = document.getElementById('slTriggerTxt');
    var btn = document.getElementById('slTrigger');
    if (!slSelected.length) {
        txt.innerHTML = '<span class="sl-trig-ph"><?= $lang==='th'?'เลือกห้องปฏิบัติการ...':'Select laboratory...' ?></span>';
        btn.classList.remove('has-val');
    } else {
        var col   = slBldColor(slSelected[0].building_code);
        var codes = slSelected.slice(0, 3).map(function(r){ return r.room_number || r.name || '–'; }).join(', ');
        txt.innerHTML =
            '<span class="sl-trig-cnt" style="background:'+col+'">'+slSelected.length+'</span>'+
            '<span class="sl-trig-rooms">'+escapeHtml(codes)+(slSelected.length > 3 ? ' …' : '')+'</span>';
        btn.classList.add('has-val');
    }
}

// ── View 1: building grid ───────────────────────────
function slRenderGrid() {
    slView = 'grid';
    document.getElementById('slNav').style.display = 'none';
    var q = document.getElementById('slSearch').value.trim().toLowerCase();
    var list = q
        ? slBuildings.filter(function(b){
            return (b.code||'').toLowerCase().includes(q) ||
                   (b.name||'').toLowerCase().includes(q);
          })
        : slBuildings;
    var html = '<div class="sl-grid">';
    list.forEach(function(b) {
        var col    = slBldColor(b.code);
        var bldSel = slSelected.filter(function(s){ return s.building_id === parseInt(b.id); }).length;
        var isSel  = bldSel > 0;
        html +=
            '<div class="sl-div-card'+(isSel?' has-sel':'')+'" data-bid="'+b.id+'"'+
                ' style="border-left-color:'+col+'">'+
                '<div class="sl-bld-num" style="color:'+col+'">'+escapeHtml(b.code)+'</div>'+
                '<div class="sl-div-title">'+escapeHtml(b.name)+'</div>'+
                '<div class="sl-div-foot">'+
                    '<span class="sl-div-badge"><i class="fas fa-door-closed"></i>'+b.room_count+' ห้อง</span>'+
                    (isSel?'<span class="sl-div-badge sl-div-badge-sel"><i class="fas fa-check"></i>'+bldSel+' เลือก</span>':'')+
                '</div>'+
                '<i class="fas fa-chevron-right sl-div-arrow"></i>'+
            '</div>';
    });
    if (!list.length) html += '<div class="sl-empty" style="grid-column:1/-1"><i class="fas fa-search"></i>'+TXT.noData+'</div>';
    html += '</div>';
    document.getElementById('slBody').innerHTML = html;
    document.getElementById('slBody').querySelectorAll('[data-bid]').forEach(function(card){
        card.addEventListener('click', function(){
            slActiveBld = slBuildings.find(function(b){ return b.id == card.getAttribute('data-bid'); });
            slLoadRooms(slActiveBld.id);
        });
    });
}

// ── pending (in-modal, not confirmed yet) ──────────
let slPending = new Map(); // id → room object (multi-select)

function slUpdateFooter() {
    var footer   = document.getElementById('slFooter');
    var source   = slPending.size ? slPending : (slSelected.length ? new Map(slSelected.map(function(s){return [s.id,s];})) : null);
    if (!source) { footer.classList.remove('visible'); return; }
    footer.classList.add('visible');
    var arr   = Array.from(source.values());
    var codes = arr.slice(0, 4).map(function(r){ return r.room_number || r.name || '–'; }).join(' · ');
    document.getElementById('slFooterRoom').textContent = arr.length + ' ห้องที่เลือก';
    document.getElementById('slFooterBld').textContent  = codes + (arr.length > 4 ? ' …' : '');
}

// ── Load rooms lazily (cached) ──────────────────────
async function slLoadRooms(buildingId) {
    if (slRoomsCache[buildingId]) { slRenderRooms(slRoomsCache[buildingId]); return; }
    document.getElementById('slBody').innerHTML =
        '<div class="sl-empty"><i class="fas fa-spinner fa-spin"></i> <?= $lang==='th'?'กำลังโหลด...':'Loading...' ?></div>';
    try {
        var res  = await fetch('/v1/api/auth.php?action=public_rooms&building_id=' + buildingId);
        var data = await res.json();
        if (!data.success) throw new Error(data.error);
        slRoomsCache[buildingId] = (data.data || []).map(function(r){
            r.id = parseInt(r.id, 10);  // ensure integer
            return r;
        });
        slRenderRooms(slRoomsCache[buildingId]);
    } catch(e) {
        document.getElementById('slBody').innerHTML =
            '<div class="sl-empty"><i class="fas fa-exclamation-circle"></i> <?= $lang==='th'?'โหลดข้อมูลไม่สำเร็จ':'Failed to load' ?></div>';
    }
}

// ── View 2: room list ───────────────────────────────
function slRenderRooms(rooms) {
    slView = 'rooms';
    var col = slBldColor(slActiveBld.code);
    document.getElementById('slNavCur').textContent = slActiveBld.name;
    document.getElementById('slNav').style.display = 'flex';
    var q = document.getElementById('slSearch').value.trim().toLowerCase();
    var list = q
        ? rooms.filter(function(r){
            return (r.room_number||'').toLowerCase().includes(q) ||
                   (r.name||'').toLowerCase().includes(q);
          })
        : rooms;
    // group by floor
    var floors = {}, floorOrd = [];
    list.forEach(function(r){
        var f = r.floor != null ? String(r.floor) : '';
        if (!floors[f]){floors[f]=[];floorOrd.push(f);}
        floors[f].push(r);
    });
    var html = '';
    floorOrd.forEach(function(f){
        if (f) html += '<div class="sl-unit-hdr">ชั้น '+escapeHtml(f)+'</div>';
        floors[f].forEach(function(r){
            var chk = slPending.has(r.id) || (!slPending.size && slSelected.some(function(s){ return s.id === r.id; }));
            html += '<div class="sl-room'+(chk?' checked':'')+'" data-rid="'+r.id+'">'+
                '<div class="sl-room-chk"><i class="fas fa-check"></i></div>'+
                '<div class="sl-room-icon" style="background:'+col+'1a;color:'+col+'"><i class="fas fa-door-closed"></i></div>'+
                '<div class="sl-room-body">'+
                    '<div class="sl-room-top">'+
                        '<span class="sl-room-code" style="color:'+col+'">'+escapeHtml(r.room_number||'–')+'</span>'+
                        (r.floor!=null?'<span class="sl-room-floor">ชั้น '+r.floor+'</span>':'')+
                    '</div>'+
                    (r.name?'<div class="sl-room-name">'+escapeHtml(r.name)+'</div>':'')+
                '</div>'+
            '</div>';
        });
    });
    if (!html) html = '<div class="sl-empty"><i class="fas fa-door-open"></i>'+TXT.noData+'</div>';
    document.getElementById('slBody').innerHTML = html;
    document.getElementById('slBody').querySelectorAll('.sl-room').forEach(function(row){
        row.addEventListener('click', function(){
            var rid  = parseInt(row.getAttribute('data-rid'), 10);
            var room = rooms.find(function(r){ return r.id === rid; });
            if (!room) return;
            // toggle selection
            if (slPending.has(rid)) {
                slPending.delete(rid);
            } else {
                slPending.set(rid, {
                    id:            rid,
                    room_number:   room.room_number || '',
                    name:          room.name || '',
                    floor:         room.floor,
                    building_id:   parseInt(slActiveBld.id),
                    building_code: slActiveBld.code,
                    building_name: slActiveBld.name,
                });
            }
            slRenderRooms(rooms);  // re-render to show/hide checkmark
            slUpdateFooter();
        });
    });
    slUpdateFooter();
}

// ── Confirm selection ───────────────────────────────
function slConfirm() {
    if (!slPending.size) return;
    slSelected = Array.from(slPending.values());
    slPending  = new Map();
    document.getElementById('roomIdInput').value = slSelected[0].id;
    slSetTrigger();
    slClose();
}

// ── Open / Close ────────────────────────────────────
function slOpen() {
    slPending = new Map(slSelected.map(function(s){ return [s.id, s]; }));
    document.getElementById('slOv').classList.add('open');
    document.body.style.overflow = 'hidden';
    document.getElementById('slSearch').value = '';
    if (slSelected.length) {
        // re-enter on the building of the first confirmed room
        slActiveBld = slBuildings.find(function(b){ return parseInt(b.id) === slSelected[0].building_id; }) || null;
        if (slActiveBld && slRoomsCache[slActiveBld.id]) {
            slRenderRooms(slRoomsCache[slActiveBld.id]);
            return;
        }
    }
    slRenderGrid();
    setTimeout(function(){ document.getElementById('slSearch').focus(); }, 80);
}

function slClose() {
    slPending = new Map();
    document.getElementById('slOv').classList.remove('open');
    document.body.style.overflow = '';
    document.getElementById('slFooter').classList.remove('visible');
}

function slGoGrid() {
    slPending = new Map();
    document.getElementById('slSearch').value = '';
    document.getElementById('slFooter').classList.remove('visible');
    slRenderGrid();
}

document.addEventListener('DOMContentLoaded', function(){
    document.getElementById('slOv').addEventListener('click', function(e){
        if (e.target === this) slClose();
    });
    document.getElementById('slSearch').addEventListener('input', function(){
        if (slView === 'rooms' && slActiveBld && slRoomsCache[slActiveBld.id]) {
            slRenderRooms(slRoomsCache[slActiveBld.id]);
        } else {
            slView = 'grid';
            document.getElementById('slNav').style.display = 'none';
            slRenderGrid();
        }
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') slClose();
    });
});

async function buildingsLoad() {
    try {
        var res  = await fetch('/v1/api/auth.php?action=public_buildings');
        var data = await res.json();
        if (!data.success) throw new Error(data.error || TXT.loadingError);
        slBuildings = (data.data || []).map(function(b){
            b.id = parseInt(b.id, 10);
            b.room_count = parseInt(b.room_count, 10);
            return b;
        });
    } catch(err) {}
}

buildingsLoad();

/* ── Org cascade ─────────────────────────────────── */
let orgStores = [];

function escOrg(s){ return String(s||'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c])); }

async function orgLoad() {
    try {
        const res  = await fetch('/v1/api/auth.php?action=public_org_hierarchy');
        const data = await res.json();
        if (!data.success) return;
        orgStores = data.data || [];
        const centers = [...new Set(orgStores.map(s => s.center_name).filter(Boolean))].sort();
        const sel = document.getElementById('rCenter');
        sel.innerHTML = `<option value="">-- <?= $lang==='th'?'เลือกศูนย์':'Select Center' ?> --</option>` +
            centers.map(c => `<option value="${escOrg(c)}">${escOrg(c)}</option>`).join('');
    } catch(e) {}
}

function orgChange(level) {
    const center   = document.getElementById('rCenter').value;
    const division = document.getElementById('rDivision').value;
    const section  = document.getElementById('rSection').value;
    const TH_      = <?= $lang==='th'?'true':'false' ?>;

    if (level === 'center') {
        const divSel = document.getElementById('rDivision');
        const secSel = document.getElementById('rSection');
        const stoSel = document.getElementById('rStore');
        if (center) {
            const divs = [...new Set(orgStores.filter(s => s.center_name === center).map(s => s.division_name).filter(Boolean))].sort();
            divSel.innerHTML = `<option value="">-- ${TH_?'เลือกฝ่าย':'Select Division'} --</option>` +
                divs.map(d => `<option value="${escOrg(d)}">${escOrg(d)}</option>`).join('');
            divSel.disabled = false;
        } else {
            divSel.innerHTML = `<option value="">-- ${TH_?'เลือกฝ่าย':'Select Division'} --</option>`;
            divSel.disabled = true;
        }
        secSel.innerHTML = `<option value="">-- ${TH_?'เลือกงาน':'Select Section'} --</option>`; secSel.disabled = true;
        stoSel.innerHTML = `<option value="">-- ${TH_?'เลือกคลัง':'Select Store'} --</option>`; stoSel.disabled = true;
    }
    if (level === 'division') {
        const secSel = document.getElementById('rSection');
        const stoSel = document.getElementById('rStore');
        if (division) {
            const sects = [...new Set(orgStores.filter(s => s.center_name === center && s.division_name === division).map(s => s.section_name).filter(Boolean))].sort();
            secSel.innerHTML = `<option value="">-- ${TH_?'เลือกงาน':'Select Section'} --</option>` +
                sects.map(s => `<option value="${escOrg(s)}">${escOrg(s)}</option>`).join('');
            secSel.disabled = false;
        } else {
            secSel.innerHTML = `<option value="">-- ${TH_?'เลือกงาน':'Select Section'} --</option>`; secSel.disabled = true;
        }
        stoSel.innerHTML = `<option value="">-- ${TH_?'เลือกคลัง':'Select Store'} --</option>`; stoSel.disabled = true;
    }
    if (level === 'section') {
        const stoSel = document.getElementById('rStore');
        if (section) {
            const stores = orgStores.filter(s => s.center_name === center && s.division_name === division && s.section_name === section);
            stoSel.innerHTML = `<option value="">-- ${TH_?'เลือกคลัง':'Select Store'} --</option>` +
                stores.map(s => `<option value="${escOrg(s.id)}">${escOrg(s.store_name)}</option>`).join('');
            stoSel.disabled = false;
        } else {
            stoSel.innerHTML = `<option value="">-- ${TH_?'เลือกคลัง':'Select Store'} --</option>`; stoSel.disabled = true;
        }
    }
    orgUpdateBc();
}

function orgUpdateBc() {
    const center   = document.getElementById('rCenter').value;
    const division = document.getElementById('rDivision').value;
    const section  = document.getElementById('rSection').value;
    const storeId  = document.getElementById('rStore').value;
    const bc = document.getElementById('orgBc');
    const parts = [center, division, section].filter(Boolean);
    if (storeId) {
        const st = orgStores.find(s => String(s.id) === String(storeId));
        if (st) parts.push(st.store_name);
    }
    if (parts.length) {
        bc.style.display = 'flex';
        document.getElementById('orgBcTxt').textContent = parts.join(' › ');
    } else {
        bc.style.display = 'none';
    }
}

orgLoad();

document.getElementById('registerForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    if (form.password.value !== form.password_confirm.value) {
        showError(TXT.pwMismatch);
        return;
    }
    if (!slSelected.length) {
        showError(TXT.required);
        return;
    }
    document.getElementById('roomIdInput').value = slSelected[0].id;

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    document.getElementById('submitText').style.display = 'none';
    document.getElementById('submitSpinner').style.display = 'inline-block';
    document.getElementById('errorMsg').classList.remove('show');

    try {
        const fd = new FormData(form);
        const body = Object.fromEntries(fd.entries());
        delete body.password_confirm;
        delete body.terms;

        const response = await fetch('/v1/api/auth.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await response.json();
        if (data.success) {
            form.style.display = 'none';
            document.getElementById('successMsg').classList.add('show');
        } else {
            showError(data.error || 'Registration failed');
        }
    } catch (error) {
        showError(TXT.serverError);
    } finally {
        btn.disabled = false;
        document.getElementById('submitText').style.display = '';
        document.getElementById('submitSpinner').style.display = 'none';
    }
});

function showError(msg) {
    document.getElementById('errorText').textContent = msg;
    document.getElementById('errorMsg').classList.add('show');
}
</script>
</body>
</html>
