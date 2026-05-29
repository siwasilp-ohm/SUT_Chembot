<?php
/**
 * Login Page — SUT ChemBot AI Chemical Search Assistant
 * Redesigned: DeepSeek-style hierarchical responses, animations, no external AI
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

if (Auth::getCurrentUser()) { header('Location: /v1/'); exit; }
$lang = I18n::getCurrentLang();

$demoSetting = Database::fetch("SELECT setting_value FROM system_settings WHERE setting_key = :k", [':k' => 'demo_accounts_enabled']);
$demoEnabled = $demoSetting && in_array($demoSetting['setting_value'], ['1', 'true']);

$allUsers = [];
if ($demoEnabled) {
    $allUsers = Database::fetchAll("
        SELECT u.id, u.username, u.full_name_th, u.first_name, u.last_name,
               r.name as role_name, r.display_name as role_display
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY r.level DESC, u.username ASC
    ");
}

$usersByRole = [];
foreach ($allUsers as $u) {
    $role = $u['role_name'] ?? 'user';
    $usersByRole[$role][] = $u;
}

$roleConfig = [
    'admin'       => ['icon' => 'fa-user-shield',  'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fecaca', 'th' => 'ผู้ดูแลระบบ',      'en' => 'Administrator'],
    'ceo'         => ['icon' => 'fa-crown',         'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a', 'th' => 'ผู้บริหาร',         'en' => 'CEO / Director'],
    'lab_manager' => ['icon' => 'fa-microscope',    'color' => '#7c3aed', 'bg' => '#f5f3ff', 'border' => '#ddd6fe', 'th' => 'ผู้จัดการห้องปฏิบัติการ', 'en' => 'Lab Manager'],
    'user'        => ['icon' => 'fa-user',          'color' => '#2563eb', 'bg' => '#eff6ff', 'border' => '#bfdbfe', 'th' => 'ผู้ใช้งาน',          'en' => 'Lab User'],
    'visitor'     => ['icon' => 'fa-eye',           'color' => '#6b7280', 'bg' => '#f9fafb', 'border' => '#e5e7eb', 'th' => 'ผู้เยี่ยมชม',        'en' => 'Visitor'],
];

$dbCount = Database::fetch("SELECT COUNT(*) as cnt FROM chemicals WHERE is_active = 1");
$totalChemicals = (int)($dbCount['cnt'] ?? 0);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $lang==='th'?'ผู้ช่วยค้นหาสารเคมี':'Chemical Search Assistant'; ?> — SUT ChemBot</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Sans+Thai:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════════
   CSS VARIABLES & RESET
═══════════════════════════════════════════════════ */
:root{
    --accent:#f97316; --accent-h:#ea580c; --accent-light:#fff7ed;
    --sb-bg:#151515; --sb-w:300px;
    --chat-bg:#f4f6f9;
    --border:#e5e7eb; --border-dark:rgba(255,255,255,.08);
    --c1:#1e293b; --c2:#475569; --c3:#94a3b8;
    --card:#ffffff;
    --radius:12px;
    --shadow:0 2px 12px rgba(0,0,0,.06);
    --shadow-lg:0 8px 32px rgba(0,0,0,.12);

    /* Semantic colors */
    --danger:#dc2626; --danger-bg:#fef2f2; --danger-border:#fecaca;
    --warn:#f59e0b;   --warn-bg:#fffbeb;   --warn-border:#fde68a;
    --ok:#10b981;     --ok-bg:#f0fdf4;     --ok-border:#86efac;
    --info:#3b82f6;   --info-bg:#eff6ff;   --info-border:#bfdbfe;
    --purple:#8b5cf6; --purple-bg:#f5f3ff; --purple-border:#ddd6fe;
}
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;background:var(--chat-bg);font-family:'Inter','Noto Sans Thai',sans-serif;font-size:14px;color:var(--c1)}

/* ═══════════════════════════════════════════════════
   LAYOUT
═══════════════════════════════════════════════════ */
.app{display:flex;min-height:100vh}

/* Sidebar */
.sb{
    width:var(--sb-w);position:fixed;top:0;left:0;bottom:0;
    background:var(--sb-bg);display:flex;flex-direction:column;
    padding:20px;z-index:100;overflow-y:auto;
    border-right:1px solid rgba(255,255,255,.06);
    transition:transform .3s;
}
.sb-logo{display:flex;align-items:center;gap:12px;padding-bottom:16px;border-bottom:1px solid var(--border-dark);margin-bottom:20px}
.sb-logo-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--accent),#fb923c);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex-shrink:0;box-shadow:0 4px 16px rgba(249,115,22,.35)}
.sb-logo-text h1{font-size:18px;font-weight:800;color:#fff;letter-spacing:-.5px}
.sb-logo-text p{font-size:10px;color:rgba(255,255,255,.45);margin-top:2px}

.sb-lang{display:flex;gap:4px;margin-bottom:16px}
.sb-lang a{flex:1;text-align:center;padding:5px;border-radius:6px;font-size:11px;font-weight:600;color:rgba(255,255,255,.45);text-decoration:none;transition:.12s}
.sb-lang a:hover{color:#fff;background:rgba(255,255,255,.08)}
.sb-lang a.active{color:#fff;background:var(--accent)}

/* Login Card */
.sb-login{margin-top:auto;padding-bottom:12px}
.login-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:18px}
.login-card h3{color:#fff;font-size:13px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px;opacity:.9}
.fg{margin-bottom:12px}
.fg label{display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.5);margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px}
.fg input{width:100%;padding:11px 14px;border:1px solid rgba(255,255,255,.12);border-radius:9px;font-size:13px;background:rgba(255,255,255,.06);color:#fff;transition:.15s;font-family:inherit}
.fg input:focus{outline:none;border-color:var(--accent);background:rgba(255,255,255,.1)}
.fg input::placeholder{color:rgba(255,255,255,.3)}
.login-opts{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;font-size:11px;color:rgba(255,255,255,.45)}
.login-opts label{display:flex;align-items:center;gap:5px;cursor:pointer}
.login-opts a{color:var(--accent);font-weight:500;text-decoration:none}
.login-btn{width:100%;padding:11px;background:var(--accent);color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;transition:.15s;display:flex;align-items:center;justify-content:center;gap:7px}
.login-btn:hover{background:var(--accent-h)}
.login-btn:disabled{opacity:.55;cursor:not-allowed}
.err-msg{padding:9px 12px;background:rgba(220,38,38,.2);border:1px solid rgba(220,38,38,.3);border-radius:8px;color:#fca5a5;font-size:12px;margin-top:10px;display:none;align-items:center;gap:6px}
.err-msg.show{display:flex}

/* Demo accounts */
.demo-box{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:10px;margin-bottom:12px}
.demo-title{font-size:10px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:flex;align-items:center;gap:5px}
.demo-role-group{position:relative;margin-bottom:5px}
.demo-role-btn{width:100%;display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:7px;border:1px solid rgba(255,255,255,.08);background:transparent;cursor:pointer;font-size:11px;color:rgba(255,255,255,.75);transition:.15s;font-family:inherit}
.demo-role-btn:hover{border-color:var(--accent);background:rgba(249,115,22,.08)}
.demo-role-icon{width:22px;height:22px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0}
.demo-role-info{flex:1;text-align:left}
.demo-role-name{font-weight:700;font-size:11px}
.demo-role-count{font-size:10px;color:rgba(255,255,255,.4)}
.demo-dd{display:none;position:absolute;left:0;right:0;top:100%;margin-top:4px;background:#222;border:1px solid rgba(255,255,255,.12);border-radius:9px;box-shadow:0 8px 24px rgba(0,0,0,.4);z-index:20;max-height:140px;overflow-y:auto;padding:5px}
.demo-dd.show{display:block}
.demo-user{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:6px;cursor:pointer;font-size:11px;color:rgba(255,255,255,.8)}
.demo-user:hover{background:rgba(255,255,255,.08)}
.demo-avatar{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff;flex-shrink:0}

/* Sidebar footer */
.sb-footer{border-top:1px solid var(--border-dark);padding-top:12px;margin-top:16px;font-size:10px;color:rgba(255,255,255,.3);text-align:center}
.sb-footer a{color:var(--accent);text-decoration:none}

/* Sidebar collapse */
.sb{transition:transform .3s,width .28s cubic-bezier(.4,0,.2,1)}
.sb.collapsed{width:0;padding:0;overflow:hidden;border-right:none}
.main{transition:margin-left .28s cubic-bezier(.4,0,.2,1)}
.main.sb-hidden{margin-left:0}
/* Sidebar collapse toggle button (inside header) */
.sb-toggle-btn{width:36px;height:36px;border-radius:8px;border:none;background:transparent;cursor:pointer;color:var(--c3);font-size:15px;transition:.15s;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-toggle-btn:hover{background:var(--border);color:var(--c1)}

/* Mobile toggle */
.mob-btn{display:none;width:38px;height:38px;border-radius:9px;background:var(--accent);border:none;color:#fff;font-size:16px;cursor:pointer;box-shadow:0 3px 10px rgba(249,115,22,.35);align-items:center;justify-content:center;flex-shrink:0;transition:.15s}
.mob-btn:hover{background:var(--accent-h)}
.mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:190}
.mob-overlay.show{display:block}

/* ═══════════════════════════════════════════════════
   MAIN CHAT AREA
═══════════════════════════════════════════════════ */
.main{flex:1;margin-left:var(--sb-w);display:flex;flex-direction:column;height:100vh;height:100dvh}

/* Chat Header */
.chat-hdr{
    height:60px;background:rgba(255,255,255,.97);backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);display:flex;align-items:center;
    padding:0 24px;gap:10px;position:sticky;top:0;z-index:50;
}
.chat-hdr-left{display:flex;align-items:center;gap:12px;flex:1}
.chat-hdr-left h2{font-size:16px;font-weight:700;color:var(--c1)}
.chat-hdr-left .ai-status{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--c3);margin-top:1px}
.ai-dot{width:6px;height:6px;border-radius:50%;background:var(--ok);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.hdr-actions{display:flex;gap:6px}
.hdr-btn{width:36px;height:36px;border-radius:8px;border:none;background:transparent;cursor:pointer;color:var(--c3);font-size:14px;transition:.15s;display:flex;align-items:center;justify-content:center}
.hdr-btn:hover{background:var(--border);color:var(--c1)}
.hdr-btn.active{background:var(--accent-light);color:var(--accent)}

/* Chat Messages */
.chat-msgs{flex:1;overflow-y:auto;padding:24px;scroll-behavior:smooth}
.chat-msgs::-webkit-scrollbar{width:5px}
.chat-msgs::-webkit-scrollbar-track{background:transparent}
.chat-msgs::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px}

/* Welcome screen */
.welcome{max-width:760px;margin:0 auto}

/* Quick chips */
.quick-section{margin-bottom:20px}
.quick-section h3{font-size:12px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip{padding:9px 16px;background:var(--card);border:1px solid var(--border);border-radius:24px;font-size:12px;color:var(--c2);cursor:pointer;transition:.15s;display:flex;align-items:center;gap:7px;box-shadow:var(--shadow);font-family:inherit;white-space:nowrap}
.chip:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-light);transform:translateY(-2px);box-shadow:0 4px 12px rgba(249,115,22,.12)}
.chip i{font-size:11px}

/* Capability cards */
.cap-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:8px}
.cap-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px;cursor:pointer;transition:.2s;box-shadow:var(--shadow)}
.cap-card:hover{border-color:var(--accent);transform:translateY(-3px);box-shadow:0 6px 20px rgba(249,115,22,.12)}
.cap-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:10px}
.cap-card h4{font-size:13px;font-weight:700;color:var(--c1);margin-bottom:4px}
.cap-card p{font-size:11px;color:var(--c3);line-height:1.4}

/* Messages */
.msgs-area{max-width:800px;margin:0 auto}
.msg{display:flex;gap:12px;margin-bottom:24px;animation:msgIn .3s ease}
@keyframes msgIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.msg.user{flex-direction:row-reverse}
.msg-av{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.msg.ai .msg-av{background:linear-gradient(135deg,var(--accent),#fb923c);color:#fff}
.msg.user .msg-av{background:#e2e8f0;color:var(--c2)}
.msg-body{max-width:calc(100% - 46px)}
.msg-name{font-size:11px;font-weight:600;color:var(--c3);margin-bottom:5px}
.msg.user .msg-name{text-align:right}
.msg-bubble{padding:14px 18px;border-radius:14px;line-height:1.65;font-size:13.5px}
.msg.ai .msg-bubble{background:var(--card);border:1px solid var(--border);color:var(--c1);box-shadow:var(--shadow)}
.msg.user .msg-bubble{background:linear-gradient(135deg,var(--accent),#fb923c);color:#fff;box-shadow:0 4px 14px rgba(249,115,22,.25)}

/* Loading */
.loading-dots{display:flex;gap:5px;padding:4px 0}
.loading-dots span{width:7px;height:7px;border-radius:50%;background:var(--accent);animation:ldot 1.4s ease-in-out infinite}
.loading-dots span:nth-child(1){animation-delay:-.32s}
.loading-dots span:nth-child(2){animation-delay:-.16s}
@keyframes ldot{0%,80%,100%{transform:scale(0)}40%{transform:scale(1)}}
.loading-label{font-size:12px;color:var(--c3);margin-top:4px}

/* ═══════════════════════════════════════════════════
   RESPONSE BLOCKS — DeepSeek style
═══════════════════════════════════════════════════ */

/* Section header */
.rs-head{display:flex;align-items:center;gap:8px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid var(--border)}
.rs-head-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.rs-head h3{font-size:15px;font-weight:700;color:var(--c1)}

/* Chemical hero card */
.chem-hero{display:flex;gap:18px;background:linear-gradient(135deg,#fef3c7 0%,#fff7ed 100%);border:1px solid var(--warn-border);border-radius:16px;padding:20px;margin-bottom:16px}
.chem-hero-img{flex-shrink:0;width:130px;height:130px;background:#fff;border-radius:12px;padding:8px;box-shadow:0 2px 10px rgba(0,0,0,.08);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.chem-hero-img img{max-width:100%;max-height:100%;object-fit:contain;transition:transform .4s}
.chem-hero-img:hover img{transform:scale(1.08)}
.chem-hero-info{flex:1;min-width:0}
.chem-hero-name{font-size:20px;font-weight:800;color:#92400e;margin-bottom:10px;line-height:1.2}
.chem-pills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.pill{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:12px;font-weight:600}
.pill.cas{background:#fef3c7;color:#b45309;border:1px solid #fde68a}
.pill.formula{background:#e0e7ff;color:#4338ca;border:1px solid #c7d2fe}
.pill.mw{background:#f3e8ff;color:#7c3aed;border:1px solid #ddd6fe}
.pill.state{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.pill.cat{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}
.pill.danger{background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger-border)}
.pill.warning{background:var(--warn-bg);color:#b45309;border:1px solid var(--warn-border)}
.pill.info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.chem-hero-desc{font-size:12px;color:#78350f;line-height:1.5;margin-top:4px}

/* chembot-response wrapper */
.chembot-response{display:flex;flex-direction:column;gap:0}
.section-body{padding:0 2px 12px}

/* Info table */
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:14px}
.info-cell{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px}
.info-cell-label{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.info-cell-value{font-size:14px;font-weight:700;color:var(--c1)}
.info-cell-value.mono{font-family:'JetBrains Mono',monospace;font-size:13px}

/* ─── Hazard Section ─── */
.hazard-block{background:var(--danger-bg);border:1px solid var(--danger-border);border-radius:14px;padding:16px;margin-bottom:16px}
.hazard-block-head{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:var(--danger);margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--danger-border)}
.signal-badge{padding:5px 18px;border-radius:8px;font-size:18px;font-weight:900;letter-spacing:.5px;display:inline-block;margin-bottom:12px}
.signal-badge.danger{background:var(--danger);color:#fff;box-shadow:0 3px 12px rgba(220,38,38,.3)}
.signal-badge.warning{background:var(--warn);color:#fff;box-shadow:0 3px 12px rgba(245,158,11,.3)}

/* ═══════════════════════════════════════════════════════
   GHS Pictograms — UN GHS International Standard
   Red border · White background · Black symbol
   ═══════════════════════════════════════════════════════ */
.ghs-section{margin-bottom:16px}
.ghs-section-title{
    font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;
    color:var(--c3);margin-bottom:12px;display:flex;align-items:center;gap:6px;
    padding-bottom:6px;border-bottom:1px solid var(--border);
}
.ghs-grid{display:flex;flex-wrap:wrap;gap:20px 12px;margin-bottom:4px;padding:4px 2px}
.ghs-item{
    display:flex;flex-direction:column;align-items:center;gap:7px;
    cursor:pointer;position:relative;
}

/* Official GHS SVG wrapper — uses Wikimedia Commons SVG files */
.ghs-diamond-wrap{
    width:88px;height:88px;
    transition:transform .3s cubic-bezier(.34,1.56,.64,1),
               filter .3s ease;
    filter:drop-shadow(0 4px 12px rgba(0,0,0,.22));
}
.ghs-diamond-wrap svg,
.ghs-diamond-wrap .ghs-official-svg{
    width:100%;height:100%;display:block;
}
.ghs-item:hover .ghs-diamond-wrap{
    transform:scale(1.18) rotate(4deg);
    filter:drop-shadow(0 8px 24px rgba(204,0,0,.55));
}

/* Code pill badge */
.ghs-code-pill{
    font-size:9px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;
    color:#fff;background:#cc0000;padding:2px 7px;border-radius:99px;
    line-height:1.5;white-space:nowrap;
    box-shadow:0 2px 8px rgba(204,0,0,.35);
    transition:transform .2s ease,box-shadow .2s ease;
}
.ghs-item:hover .ghs-code-pill{
    transform:translateY(-2px);
    box-shadow:0 5px 16px rgba(204,0,0,.5);
}

/* Hazard name label */
.ghs-name{
    font-size:10px;font-weight:600;color:var(--c2);
    text-align:center;max-width:80px;line-height:1.35;
}

/* Hazard & Precautionary statements */
.stmt-block{background:#fff;border-radius:10px;padding:12px;margin-bottom:10px}
.stmt-head{font-size:12px;font-weight:700;color:var(--danger);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.stmt-list{list-style:none;padding:0;display:flex;flex-direction:column;gap:5px}
.stmt-list li{font-size:12px;color:var(--c2);padding-left:14px;position:relative;line-height:1.5}
.stmt-list li::before{content:'•';position:absolute;left:2px;color:var(--danger)}

/* ─── Location Section — Pro Redesign ─── */
.loc-block{background:#fff;border:1px solid #d1fae5;border-radius:16px;overflow:hidden;margin-bottom:16px;box-shadow:0 2px 12px rgba(16,185,129,.08)}
.loc-block-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:linear-gradient(135deg,#ecfdf5,#f0fdf4);border-bottom:1px solid #a7f3d0;gap:10px;flex-wrap:wrap}
.loc-head-left{display:flex;align-items:center;gap:10px}
.loc-head-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:17px;flex-shrink:0;box-shadow:0 2px 8px rgba(16,185,129,.3)}
.loc-head-title{font-size:14px;font-weight:800;color:#064e3b;line-height:1.2}
.loc-head-sub{font-size:10.5px;color:#6b7280;margin-top:2px}
.loc-total-badge{display:flex;align-items:baseline;gap:3px;background:linear-gradient(135deg,#10b981,#059669);padding:7px 14px;border-radius:20px;box-shadow:0 2px 10px rgba(16,185,129,.3);flex-shrink:0}
.loc-qty-big{font-size:17px;font-weight:900;color:#fff;line-height:1}
.loc-unit{font-size:11px;color:rgba(255,255,255,.8);font-weight:600;margin-left:2px}
.loc-count-tag{font-size:10px;color:rgba(255,255,255,.7);margin-left:6px;font-weight:600}
.loc-tree{padding:12px;display:flex;flex-direction:column;gap:10px}
.loc-building{border:1.5px solid #d1fae5;border-radius:12px;overflow:hidden}
.loc-building-hdr{display:flex;align-items:stretch;cursor:pointer;user-select:none;transition:background .15s}
.loc-building-hdr:hover .loc-bld-text{background:#dcfce7}
.loc-bld-icon-wrap{width:46px;background:linear-gradient(180deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0}
.loc-bld-text{flex:1;padding:11px 12px;font-weight:700;color:#065f46;font-size:13px;background:linear-gradient(90deg,#f0fdf4,#fff);border-left:1px solid #d1fae5;transition:background .15s;display:flex;align-items:center;gap:8px}
.loc-bld-meta{display:flex;align-items:center;gap:6px;margin-left:auto;padding-right:4px}
.loc-n-badge{background:#10b981;color:#fff;border-radius:8px;padding:2px 8px;font-size:10px;font-weight:700}
.expand-icon{color:#10b981;transition:transform .25s;font-size:11px;flex-shrink:0}
.loc-building-hdr.open .expand-icon{transform:rotate(180deg)}
.loc-rooms{padding:10px;display:flex;flex-direction:column;gap:8px}
.loc-room{border:1px solid #a7f3d0;border-radius:10px;overflow:hidden}
.loc-room-hdr{display:flex;align-items:center;gap:8px;padding:8px 12px;font-weight:700;color:#065f46;font-size:12px;background:linear-gradient(90deg,#dcfce7,#f0fdf4);border-bottom:1px solid #a7f3d0}
.loc-room-hdr .loc-n-badge{background:#059669;margin-left:auto}
.loc-cabinet{background:#fff;padding:8px}
.loc-cabinet+.loc-cabinet{border-top:1px solid #f0fdf4}
.loc-cabinet-hdr{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#065f46;margin-bottom:7px;padding:5px 8px;background:linear-gradient(90deg,#f0fdf4,#fff);border-radius:7px;border:1px solid #d1fae5}
.loc-items{display:flex;flex-direction:column;gap:6px}
.loc-item{border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#fff;transition:box-shadow .15s,transform .15s}
.loc-item:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-1px)}
.loc-item-main{display:flex;align-items:stretch}
.loc-item-type-col{width:52px;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 4px;gap:3px;flex-shrink:0;border-right:1px solid #e2e8f0}
.loc-item-type-col .ctype-icon{font-size:17px;color:#2563eb}
.loc-item-type-col .ctype-lbl{font-size:8px;font-weight:700;color:#1d4ed8;text-transform:uppercase;text-align:center;letter-spacing:.3px;word-break:break-all;max-width:46px;line-height:1.2}
.loc-item-info{flex:1;padding:8px 10px;display:flex;flex-direction:column;justify-content:center;gap:4px;min-width:0}
.loc-item-qr{font-size:10px;font-family:"Courier New",monospace;color:#374151;font-weight:600;display:flex;align-items:center;gap:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.loc-item-qr .qr-icon{color:#94a3b8;font-size:9px;flex-shrink:0}
.loc-item-loc{font-size:10.5px;color:#6b7280;display:flex;align-items:center;gap:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.loc-item-loc i{color:#10b981;font-size:9px;flex-shrink:0}
.loc-item-right{display:flex;flex-direction:column;align-items:flex-end;justify-content:center;padding:8px 12px;gap:5px;flex-shrink:0;border-left:1px solid #f1f5f9;background:#fafafa;min-width:95px}
.loc-item-qty{font-size:15px;font-weight:900;color:#1e293b;line-height:1;white-space:nowrap}
.loc-exp-badge{font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:6px;white-space:nowrap;display:flex;align-items:center;gap:3px}
.loc-exp-badge.fresh{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.loc-exp-badge.warn{background:#fef3c7;color:#b45309;border:1px solid #fde68a}
.loc-exp-badge.danger{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.loc-exp-badge.nodate{background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0}
.loc-item-owner{display:flex;align-items:center;gap:10px;padding:6px 10px;border-top:1px solid #f1f5f9;background:#fafafa;font-size:10px;flex-wrap:wrap}
.loc-owner-name{font-weight:700;color:#374151;display:flex;align-items:center;gap:4px;white-space:nowrap}
.loc-owner-name i{color:#94a3b8;font-size:9px}
.loc-item-owner a{color:#0369a1;text-decoration:none;display:flex;align-items:center;gap:3px;white-space:nowrap}
.loc-item-owner a:hover{text-decoration:underline}
.loc-item-owner a i{font-size:9px;color:#94a3b8}
.loc-no-owner{color:#94a3b8;font-style:italic}
.ctype-flask .loc-item-type-col{background:linear-gradient(135deg,#f0fdf4,#dcfce7)}
.ctype-flask .ctype-icon{color:#16a34a}.ctype-flask .ctype-lbl{color:#166534}
.ctype-bag .loc-item-type-col{background:linear-gradient(135deg,#fdf4ff,#f3e8ff)}
.ctype-bag .ctype-icon{color:#9333ea}.ctype-bag .ctype-lbl{color:#7e22ce}
.ctype-drum .loc-item-type-col{background:linear-gradient(135deg,#fef3c7,#fde68a)}
.ctype-drum .ctype-icon{color:#b45309}.ctype-drum .ctype-lbl{color:#92400e}
.ctype-can .loc-item-type-col{background:linear-gradient(135deg,#fff7ed,#fed7aa)}
.ctype-can .ctype-icon{color:#ea580c}.ctype-can .ctype-lbl{color:#c2410c}
.ctype-box .loc-item-type-col{background:linear-gradient(135deg,#f8fafc,#f1f5f9)}
.ctype-box .ctype-icon{color:#475569}.ctype-box .ctype-lbl{color:#334155}
/* Action buttons */
.loc-item-actions{display:flex;align-items:center;gap:6px;padding:7px 10px 8px;border-top:1px solid #f1f5f9;background:linear-gradient(to right,#f8fafc,#f0f9ff);flex-wrap:wrap}
.loc-act-btn{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:7px;font-size:10.5px;font-weight:700;border:none;cursor:pointer;transition:all .15s;white-space:nowrap;text-decoration:none}
.loc-act-btn i{font-size:9px}
.loc-act-borrow{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 2px 6px rgba(16,185,129,.3)}
.loc-act-borrow:hover{background:linear-gradient(135deg,#059669,#047857);box-shadow:0 3px 10px rgba(16,185,129,.4);transform:translateY(-1px)}
.loc-act-edit{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;box-shadow:0 2px 6px rgba(59,130,246,.3)}
.loc-act-edit:hover{background:linear-gradient(135deg,#2563eb,#1d4ed8);box-shadow:0 3px 10px rgba(59,130,246,.4);transform:translateY(-1px)}
.loc-act-view3d{background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;box-shadow:0 2px 6px rgba(139,92,246,.3)}
.loc-act-view3d:hover{background:linear-gradient(135deg,#7c3aed,#6d28d9);box-shadow:0 3px 10px rgba(139,92,246,.4);transform:translateY(-1px)}
.loc-act-info{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}
.loc-act-info:hover{background:#e2e8f0;color:#1e293b;transform:translateY(-1px)}
.loc-set-exp-btn{cursor:pointer;background:linear-gradient(135deg,#f59e0b,#d97706)!important;color:#fff!important;border:none!important;padding:3px 9px!important;font-size:9.5px!important;gap:4px}
.loc-set-exp-btn:hover{background:linear-gradient(135deg,#d97706,#b45309)!important;transform:translateY(-1px)}
.loc-stock-status{display:flex;align-items:center;gap:6px;margin-left:auto;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:3px 9px;font-size:10px;max-width:220px;flex:1}
.loc-stock-status.loc-stock-ceo{background:#f0f9ff;border-color:#bae6fd}
.loc-stock-icon{font-size:11px;color:#10b981}
.loc-stock-bar{flex:1;height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;min-width:40px}
.loc-stock-fill{height:100%;background:linear-gradient(to right,#10b981,#059669);border-radius:2px;transition:width .4s ease}
.loc-stock-lbl{font-size:9.5px;font-weight:700;color:#047857;white-space:nowrap}
.loc-stock-ceo .loc-stock-lbl{color:#0369a1}
.loc-stock-ceo .loc-stock-fill{background:linear-gradient(to right,#38bdf8,#0ea5e9)}
@media(max-width:600px){
.loc-item-main{flex-direction:column}
.loc-item-type-col{width:100%;flex-direction:row;height:auto;padding:7px 10px;border-right:none;border-bottom:1px solid #e2e8f0;justify-content:flex-start;gap:8px}
.loc-item-type-col .ctype-icon{font-size:15px}
.loc-item-right{flex-direction:row;justify-content:space-between;align-items:center;min-width:auto;border-left:none;border-top:1px solid #f1f5f9;padding:7px 10px;background:#f8fafc;width:auto}
.loc-item-qty{font-size:14px}
.loc-bld-icon-wrap{width:40px;font-size:14px}
.loc-head-icon{width:34px;height:34px;font-size:15px}
.loc-total-badge{padding:5px 12px}
.loc-qty-big{font-size:15px}
.loc-tree{padding:8px;gap:8px}
.loc-rooms{padding:8px;gap:6px}
.loc-item-actions{padding:6px 8px;gap:4px}
.loc-act-btn{padding:4px 9px;font-size:10px}
.loc-stock-status{max-width:100%;margin-left:0}
}

/* No stock badge */
.no-stock{text-align:center;padding:20px;background:#fef3c7;border-radius:8px;color:#b45309;font-size:12px}

/* ── LOC MODAL ── */
.loc-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;display:flex;align-items:flex-end;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s;backdrop-filter:blur(4px)}
.loc-modal-overlay.open{opacity:1;pointer-events:all}
.lm-panel{background:#fff;width:100%;max-width:520px;max-height:92vh;border-radius:20px 20px 0 0;display:flex;flex-direction:column;box-shadow:0 -8px 40px rgba(0,0,0,.18);transform:translateY(100%);transition:transform .3s cubic-bezier(.22,1,.36,1)}
.loc-modal-overlay.open .lm-panel{transform:translateY(0)}
.lm-panel.lm-accent-green .lm-hdr{background:linear-gradient(135deg,#10b981,#059669)}
.lm-panel.lm-accent-blue .lm-hdr{background:linear-gradient(135deg,#3b82f6,#1d4ed8)}
.lm-panel.lm-accent-purple .lm-hdr{background:linear-gradient(135deg,#8b5cf6,#6d28d9)}
.lm-hdr{display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#1e293b,#334155);border-radius:20px 20px 0 0;flex-shrink:0}
.lm-title{flex:1;font-size:14px;font-weight:800;color:#fff;display:flex;align-items:center;gap:7px}
.lm-title i{font-size:13px;opacity:.85}
.lm-close-btn{background:rgba(255,255,255,.18);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:.15s}
.lm-close-btn:hover{background:rgba(255,255,255,.32)}
.lm-body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px;scroll-behavior:smooth}
.lm-loading{text-align:center;padding:40px;color:#94a3b8;font-size:13px;display:flex;align-items:center;justify-content:center;gap:8px}
.lm-error{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;color:#b91c1c;font-size:12.5px;display:flex;align-items:center;gap:8px;line-height:1.5}
.lm-chem-name{font-size:18px;font-weight:900;color:#1e293b;line-height:1.3}
.lm-3d-badge{display:inline-block;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;font-size:10px;font-weight:800;padding:1px 6px;border-radius:5px;margin-left:8px;vertical-align:middle}
.lm-pills{display:flex;flex-wrap:wrap;gap:5px}
.lm-pill{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700;white-space:nowrap}
.lm-pill.cas{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.lm-pill.fml{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.lm-pill.grade{background:#fef9c3;color:#92400e;border:1px solid #fde68a}
.lm-pill.type{background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe}
.lm-pill.avail{background:#f0fdf4;color:#047857;border:1px solid #6ee7b7}
.lm-section{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 13px}
.lm-section-hdr{font-size:10px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:7px;display:flex;align-items:center;gap:5px}
.lm-qty-bar{height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-bottom:6px}
.lm-qty-bar.lm-qty-preview{margin-top:6px;margin-bottom:0}
.lm-qty-fill{height:100%;border-radius:3px;transition:width .3s,background .3s}
.lm-qty-row{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
.lm-qty-big{font-size:20px;font-weight:900;color:#1e293b}
.lm-qty-big small{font-size:12px;font-weight:600;color:#64748b}
.lm-qty-sub{font-size:11px;color:#64748b}
.lm-badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:7px;font-size:11.5px;font-weight:700}
.lm-badge.lm-fresh{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.lm-badge.lm-warn{background:#fef3c7;color:#b45309;border:1px solid #fde68a}
.lm-badge.lm-danger{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.lm-badge.lm-nodate{background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0}
.lm-loc-text{font-size:12.5px;color:#374151;font-weight:600}
.lm-owner-row{font-size:12.5px;color:#374151;font-weight:700}
.lm-ghs-row{display:flex;flex-wrap:wrap;gap:5px}
.lm-ghsbadge{display:inline-flex;align-items:center;gap:4px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700}
.lm-mono{font-family:monospace;font-size:12px;color:#475569;background:#f8fafc;padding:5px 9px;border-radius:6px;word-break:break-all}
.lm-form{display:flex;flex-direction:column;gap:10px}
.lm-field{display:flex;flex-direction:column;gap:4px}
.lm-field label{font-size:11px;font-weight:800;color:#374151;display:flex;align-items:center;gap:5px}
.lm-unit{background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;font-size:10px}
.lm-input{border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 11px;font-size:13px;width:100%;box-sizing:border-box;outline:none;transition:.15s;background:#fff;color:#1e293b}
.lm-input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.lm-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%2364748b' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:30px}
.lm-textarea{resize:vertical;min-height:56px;font-family:inherit}
.lm-input-row{display:flex;gap:6px}
.lm-input-row .lm-input{flex:1}
.lm-mini-btn{background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;padding:8px 10px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;transition:.15s}
.lm-mini-btn:hover{background:#e2e8f0}
.lm-msg{padding:8px 12px;border-radius:8px;font-size:11.5px;font-weight:600;align-items:center;gap:6px}
.lm-msg-err{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.lm-msg-ok{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.lm-info-note{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:8px;padding:8px 12px;font-size:11px;display:flex;align-items:center;gap:7px}
.lm-actions{display:flex;gap:8px;flex-wrap:wrap;padding-top:4px;margin-top:auto}
.lm-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;font-size:12.5px;font-weight:800;border:none;cursor:pointer;transition:all .15s;flex:1;justify-content:center}
.lm-btn:disabled{opacity:.55;cursor:not-allowed;transform:none!important}
.lm-btn-borrow{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 3px 12px rgba(16,185,129,.3)}
.lm-btn-borrow:hover:not(:disabled){background:linear-gradient(135deg,#059669,#047857);box-shadow:0 4px 16px rgba(16,185,129,.4);transform:translateY(-1px)}
.lm-btn-edit{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;box-shadow:0 3px 12px rgba(59,130,246,.3)}
.lm-btn-edit:hover:not(:disabled){background:linear-gradient(135deg,#2563eb,#1d4ed8);transform:translateY(-1px)}
.lm-btn-3d{background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;box-shadow:0 3px 12px rgba(139,92,246,.3)}
.lm-btn-3d:hover:not(:disabled){background:linear-gradient(135deg,#7c3aed,#6d28d9);transform:translateY(-1px)}
.lm-btn-close{background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0}
.lm-btn-close:hover{background:#e2e8f0;color:#1e293b}
.lm-success{text-align:center;padding:16px 10px;display:flex;flex-direction:column;align-items:center;gap:8px}
.lm-success-icon{font-size:44px;line-height:1}
.lm-success-title{font-size:17px;font-weight:900;color:#1e293b}
.lm-success-detail{font-size:12.5px;color:#64748b}
.lm-3d-wrap{height:280px;border-radius:12px;overflow:hidden;background:linear-gradient(135deg,#1e1b4b,#312e81);position:relative}
.lm-3d-loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#a78bfa;font-size:12px;gap:6px}
.lm-3d-hint{text-align:center;font-size:10.5px;color:#94a3b8;display:flex;align-items:center;justify-content:center;gap:6px}
@media(min-width:600px){
.lm-panel{border-radius:16px;margin:auto;align-self:center;transform:translateY(30px) scale(.97)}
.loc-modal-overlay.open .lm-panel{transform:translateY(0) scale(1)}
}
.lm-guest-prompt{text-align:center;padding:12px 8px;display:flex;flex-direction:column;align-items:center;gap:10px}
.lm-guest-icon{width:64px;height:64px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;color:#fff;box-shadow:0 6px 20px rgba(59,130,246,.3)}
.lm-guest-title{font-size:18px;font-weight:900;color:#1e293b}
.lm-guest-desc{font-size:12.5px;color:#64748b;line-height:1.6;max-width:300px}
.lm-guest-features{display:flex;flex-direction:column;gap:7px;width:100%;max-width:260px}
.lm-guest-feat{display:flex;align-items:center;gap:9px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:9px;padding:8px 13px;font-size:12px;font-weight:700;color:#0369a1}
.lm-guest-feat i{font-size:14px;width:18px;text-align:center}

/* ─── SDS Section ─── */
.sds-block{background:var(--danger-bg);border:1px solid var(--danger-border);border-radius:14px;padding:16px;margin-bottom:16px}
.sds-block-head{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:var(--danger);margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--danger-border)}
.sds-img-wrap{background:#fff;border-radius:10px;padding:10px;margin-bottom:12px;text-align:center;position:relative}
.sds-img-wrap img{max-width:100%;max-height:360px;border-radius:6px;cursor:zoom-in;transition:.2s}
.sds-img-wrap img:hover{transform:scale(1.01)}
.sds-img-wrap img.fs{position:fixed;inset:0;width:100vw;height:100vh;max-width:100vw;max-height:100vh;object-fit:contain;z-index:9999;cursor:zoom-out;background:rgba(0,0,0,.92);border-radius:0;padding:20px;margin:0}
.sds-img-hint{font-size:11px;color:var(--c3);margin-top:6px}
.sds-btns{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.sds-btn{padding:9px 16px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:6px;transition:.15s;border:none;cursor:pointer;font-family:inherit}
.sds-btn:hover{transform:translateY(-1px)}
.sds-btn-b{background:var(--info);color:#fff}      .sds-btn-b:hover{background:#2563eb}
.sds-btn-r{background:var(--danger);color:#fff}    .sds-btn-r:hover{background:#b91c1c}
.sds-btn-c{background:#0891b2;color:#fff}           .sds-btn-c:hover{background:#0e7490}
.sds-btn-g{background:#6b7280;color:#fff}           .sds-btn-g:hover{background:#4b5563}

/* ─── Search results list ─── */
.result-list{display:flex;flex-direction:column;gap:14px}
.rc{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:.2s;box-shadow:var(--shadow)}
.rc:hover{box-shadow:0 6px 24px rgba(0,0,0,.1);border-color:#c7d2fe}
.rc-hdr{display:flex;gap:14px;padding:16px;background:linear-gradient(135deg,#f8fafc,#f1f5f9);border-bottom:1px solid var(--border);cursor:pointer;align-items:flex-start}
.rc-img{width:80px;height:80px;flex-shrink:0;background:#fff;border-radius:10px;padding:6px;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.rc-img img{max-width:68px;max-height:68px;object-fit:contain}
.rc-img .no-img{font-size:28px;color:#c7d2fe}
.rc-meta{flex:1;min-width:0}
.rc-name{font-size:16px;font-weight:800;color:var(--c1);margin-bottom:7px;line-height:1.2}
.rc-pills{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:6px}
.rc-desc{font-size:12px;color:var(--c3);line-height:1.4}
.rc-body{padding:14px 16px}
.rc-loc-summary{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.rc-loc-chip{background:var(--info-bg);color:#1e40af;padding:3px 10px;border-radius:10px;font-size:11px;display:flex;align-items:center;gap:4px;font-weight:500}
.rc-actions{display:flex;gap:8px;margin-top:12px}
.rc-action-btn{padding:7px 14px;border-radius:7px;font-size:12px;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.15s;font-family:inherit;text-decoration:none}
.rc-action-btn.primary{background:var(--accent);color:#fff}
.rc-action-btn.primary:hover{background:var(--accent-h)}
.rc-action-btn.outline{background:transparent;color:var(--c2);border:1px solid var(--border)}
.rc-action-btn.outline:hover{background:var(--border)}

/* No results */
.no-result-card{background:#fff;border-radius:20px;padding:36px;text-align:center;box-shadow:var(--shadow-lg)}
.no-result-icon{width:80px;height:80px;background:linear-gradient(135deg,var(--accent),#fb923c);border-radius:24px;display:inline-flex;align-items:center;justify-content:center;font-size:34px;color:#fff;margin-bottom:20px;box-shadow:0 8px 28px rgba(249,115,22,.3)}
.no-result-title{font-size:22px;font-weight:800;color:var(--c1);margin-bottom:6px}
.no-result-sub{font-size:13px;color:var(--c3);margin-bottom:24px;line-height:1.6}
.ext-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;max-width:480px;margin:0 auto 20px}
.ext-link{display:flex;align-items:center;gap:12px;padding:14px;border-radius:12px;text-decoration:none;transition:.2s;border:1px solid transparent}
.ext-link:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.ext-link-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;color:#fff;flex-shrink:0}
.ext-link-info strong{display:block;font-size:13px;color:var(--c1)}
.ext-link-info small{font-size:11px;color:var(--c3)}
.ext-link .arr{margin-left:auto;color:var(--c3);font-size:12px}

/* 3D grid */
.model-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;max-width:480px;margin:0 auto}
.model-link{display:flex;flex-direction:column;align-items:center;gap:8px;padding:16px;border-radius:12px;text-decoration:none;color:#fff;transition:.2s;font-size:12px;font-weight:700}
.model-link:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
.model-link i{font-size:24px;margin-bottom:2px}
.model-link small{opacity:.8;font-size:10px;font-weight:400}

/* ─── 3D Viewer (local3d-wrapper, glb-card, embed3d) ─── */
.local3d-wrapper{display:flex;flex-direction:column;gap:14px}

/* GLB card */
.glb-list{display:flex;flex-direction:column;gap:12px}
.glb-card{background:#0f0f1a;border:1px solid rgba(255,255,255,.08);border-radius:14px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.3)}
.glb-mv-slot{width:100%;height:320px;position:relative;background:#0f0f1a;display:flex;align-items:center;justify-content:center}
.glb-mv-slot model-viewer{width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#6C5CE7;--progress-bar-height:3px}
.glb-mv-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;position:absolute;inset:0}
.glb-mv-spinner{width:36px;height:36px;border:3px solid rgba(108,92,231,.2);border-top-color:#6C5CE7;border-radius:50%;animation:spin .7s linear infinite}
.glb-mv-loading-txt{font-size:11px;color:rgba(255,255,255,.4)}
.glb-footer{display:flex;align-items:center;gap:8px;padding:10px 14px;background:rgba(255,255,255,.03);border-top:1px solid rgba(255,255,255,.06);flex-wrap:wrap}
.glb-footer-label{font-size:12px;font-weight:600;color:rgba(255,255,255,.7);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.glb-badge{font-size:9px;font-weight:800;padding:2px 7px;border-radius:6px;background:rgba(108,92,231,.3);color:#a78bfa;letter-spacing:.5px;text-transform:uppercase;border:1px solid rgba(108,92,231,.3)}
.glb-ar-btn{font-size:10px;font-weight:700;padding:3px 10px;border-radius:7px;background:rgba(5,150,105,.2);color:#34d399;border:1px solid rgba(52,211,153,.3);text-decoration:none;transition:.15s}
.glb-ar-btn:hover{background:rgba(5,150,105,.35)}
.glb-fs-btn{padding:4px 10px;border-radius:8px;font-size:10px;font-weight:700;background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.3);cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:5px}
.glb-fs-btn:hover{background:rgba(99,102,241,.3);color:#a5b4fc}

/* Embed3D */
.embed3d-container{border:1px solid rgba(255,255,255,.08);border-radius:14px;overflow:hidden;background:#0f0f1a;box-shadow:0 8px 32px rgba(0,0,0,.3)}
.embed3d-header{display:flex;align-items:center;gap:8px;padding:9px 14px;background:rgba(255,255,255,.05)}
.embed3d-provider{font-size:11px;font-weight:700;color:rgba(255,255,255,.8)}
.embed3d-model-name{font-size:11px;color:rgba(255,255,255,.4);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.embed3d-fullscreen-btn{margin-left:auto;font-size:14px;color:rgba(255,255,255,.4);text-decoration:none;line-height:1;transition:.15s;padding:2px 6px;border-radius:5px}
.embed3d-fullscreen-btn:hover{color:#fff;background:rgba(255,255,255,.1)}
.embed3d-frame-wrap{position:relative;width:100%;background:#0f0f1a}

/* fallback3d */
.fallback3d-section{margin-bottom:12px}
.fallback3d-title{font-size:13px;font-weight:700;color:var(--c1);margin-bottom:4px}
.fallback3d-note{font-size:11px;color:var(--c3);margin-bottom:10px}

/* GLB Fullscreen Overlay */
.glb-ov{position:fixed;inset:0;background:rgba(8,8,20,.95);z-index:1000;display:none;flex-direction:column;backdrop-filter:blur(8px)}
.glb-ov.show{display:flex}
.glb-ov-hdr{display:flex;align-items:center;gap:12px;padding:14px 20px;background:rgba(255,255,255,.04);border-bottom:1px solid rgba(255,255,255,.07);flex-shrink:0}
.glb-ov-title{font-size:14px;font-weight:700;color:#fff;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.glb-ov-badge{font-size:9px;font-weight:800;padding:2px 8px;border-radius:6px;background:rgba(108,92,231,.35);color:#a78bfa;border:1px solid rgba(108,92,231,.4);letter-spacing:.5px}
.glb-ov-controls{display:flex;gap:8px;align-items:center}
.glb-ov-ctrl-btn{width:34px;height:34px;border-radius:9px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.07);color:rgba(255,255,255,.7);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:.15s}
.glb-ov-ctrl-btn:hover{background:rgba(255,255,255,.15);color:#fff}
.glb-ov-close{width:34px;height:34px;border-radius:9px;border:none;background:rgba(239,68,68,.15);color:#f87171;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;transition:.15s}
.glb-ov-close:hover{background:rgba(239,68,68,.35);color:#fff}
.glb-ov-body{flex:1;position:relative;overflow:hidden}
.glb-ov-body model-viewer{width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#6C5CE7;--progress-bar-height:3px}
.glb-ov-hint{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.6);color:rgba(255,255,255,.5);font-size:10px;padding:5px 12px;border-radius:20px;pointer-events:none;white-space:nowrap;backdrop-filter:blur(4px)}

/* DB stats footer in not-found */
.db-stats-bar{background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:12px 16px;text-align:center;font-size:12px;color:var(--c3);margin-top:16px}

/* ─── Physical props table ─── */
.phys-table{width:100%;border-collapse:collapse;margin-bottom:14px;font-size:12px}
.phys-table th{background:#f8fafc;padding:7px 10px;text-align:left;font-weight:700;color:var(--c2);border-bottom:2px solid var(--border)}
.phys-table td{padding:7px 10px;border-bottom:1px solid var(--border);color:var(--c1)}
.phys-table td:first-child{color:var(--c3);font-weight:500;width:40%}
.phys-table td:last-child{font-weight:600;font-family:'JetBrains Mono',monospace;font-size:11px}
.phys-table tr:hover td{background:#fafafa}

/* ─── Premium Thinking / Skeleton ─── */
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes skshimmer{0%{background-position:-400px 0}100%{background-position:400px 0}}
@keyframes fadeSlideIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes orbit1{0%{transform:translate(-50%,-50%) rotate(0deg) translateX(9px)}100%{transform:translate(-50%,-50%) rotate(360deg) translateX(9px)}}
@keyframes orbit2{0%{transform:translate(-50%,-50%) rotate(0deg) translateX(-9px)}100%{transform:translate(-50%,-50%) rotate(-360deg) translateX(-9px)}}
@keyframes tickFade{0%{opacity:0;transform:translateY(5px)}20%{opacity:1;transform:translateY(0)}80%{opacity:1;transform:translateY(0)}100%{opacity:0;transform:translateY(-5px)}}
@keyframes gradFlow{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

/* Thinking wrapper */
.thinking-block{
    background:#fff;
    border:1px solid var(--border);
    border-radius:16px;
    padding:18px 20px 14px;
    margin-bottom:4px;
    box-shadow:0 2px 16px rgba(0,0,0,.06);
    animation:fadeSlideIn .25s ease both;
    position:relative;
    overflow:hidden;
}
.thinking-block::before{
    content:'';
    position:absolute;top:0;left:0;right:0;height:3px;
    background:linear-gradient(270deg,var(--accent),#fb923c,#f59e0b,#34d399,var(--accent));
    background-size:300% 300%;
    animation:gradFlow 2s linear infinite;
}

/* Header row */
.thinking-header{
    display:flex;align-items:center;gap:10px;margin-bottom:14px;
}
.thinking-orbit{
    position:relative;width:24px;height:24px;flex-shrink:0;
}
.thinking-orbit-core{
    width:8px;height:8px;border-radius:50%;
    background:linear-gradient(135deg,var(--accent),#fb923c);
    position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
    box-shadow:0 0 6px rgba(249,115,22,.6);
}
.thinking-orbit-dot{
    width:5px;height:5px;border-radius:50%;background:var(--accent);
    position:absolute;top:50%;left:50%;
}
.thinking-orbit-dot:nth-child(2){animation:orbit1 1.0s linear infinite;opacity:.8}
.thinking-orbit-dot:nth-child(3){animation:orbit2 1.5s linear infinite;background:#fb923c;opacity:.6}
.thinking-title{font-size:12px;font-weight:700;color:var(--c1)}
.thinking-ticker-wrap{
    margin-left:auto;overflow:hidden;height:16px;
    min-width:130px;max-width:160px;text-align:right;
}
.thinking-ticker{
    font-size:10px;color:var(--c3);font-style:italic;
    display:inline-block;
    animation:tickFade 2.4s ease-in-out infinite;
}

/* Steps */
.thinking-steps{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.thinking-step{
    display:flex;align-items:center;gap:9px;
    font-size:11.5px;color:var(--c3);
    transition:color .25s ease,opacity .25s ease;
    opacity:.35;
}
.thinking-step.done{color:#16a34a;opacity:.9}
.thinking-step.active{color:var(--c1);opacity:1;font-weight:600}
.thinking-step.pending{opacity:.25}
.thinking-step .step-icon{
    width:18px;height:18px;border-radius:50%;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    font-size:8px;transition:background .25s,box-shadow .25s;
    background:var(--border);color:var(--c3);
}
.thinking-step.done .step-icon{background:#dcfce7;color:#16a34a}
.thinking-step.active .step-icon{
    background:linear-gradient(135deg,var(--accent),#fb923c);
    color:#fff;
    box-shadow:0 0 0 3px rgba(249,115,22,.2);
    animation:pulse .9s ease-in-out infinite;
}
.thinking-step .step-label{flex:1}
.thinking-step .step-time{
    font-size:9px;color:var(--c3);font-family:'JetBrains Mono',monospace;
    min-width:28px;text-align:right;opacity:0;transition:opacity .3s;
}
.thinking-step.done .step-time{opacity:1}

/* Skeleton lines */
.skel-section{margin-top:2px}
.skel-line{
    height:9px;border-radius:6px;margin-bottom:7px;
    background:linear-gradient(90deg,#f1f5f9 25%,#e8ecf0 50%,#f1f5f9 75%);
    background-size:400px 100%;
    animation:skshimmer 1.4s ease-in-out infinite;
}
.skel-line.short{width:38%}
.skel-line.med{width:65%}
.skel-line.long{width:88%}
.skel-line.full{width:100%}
.skel-line.title{height:12px;width:50%;margin-bottom:10px;border-radius:8px}
.skel-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.skel-row .skel-line{margin-bottom:0}

/* ─── Section collapse toggle ─── */
.section-toggle{cursor:pointer;user-select:none}
.section-toggle .toggle-icon{transition:transform .2s}
.section-toggle.collapsed .toggle-icon{transform:rotate(-90deg)}
.section-body.collapsed{display:none}

/* ─── Compatibility warning ─── */
.compat-block{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;margin-bottom:12px}
.compat-block-head{font-size:12px;font-weight:700;color:#c2410c;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.compat-list{display:flex;flex-wrap:wrap;gap:6px}
.compat-item{background:#fef3c7;color:#b45309;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600}

/* ─── Animated background chem formula ─── */
.hero-formula-bg{position:absolute;top:10px;right:20px;font-size:56px;font-weight:900;color:rgba(255,255,255,.04);font-family:'JetBrains Mono',monospace;pointer-events:none;user-select:none;letter-spacing:-2px}

/* ═══════════════════════════════════════════════════
   INPUT AREA
═══════════════════════════════════════════════════ */
.input-area{padding:14px 24px 20px;background:rgba(255,255,255,.97);border-top:1px solid var(--border)}
.input-wrap{max-width:800px;margin:0 auto;position:relative}
.input-row{display:flex;align-items:flex-end;gap:8px;background:var(--card);border:1.5px solid var(--border);border-radius:16px;padding:8px 8px 8px 16px;transition:.15s;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.input-row:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px rgba(249,115,22,.1)}
#chatInput{flex:1;border:none;outline:none;background:transparent;font-size:14px;resize:none;min-height:36px;max-height:180px;font-family:inherit;color:var(--c1);line-height:1.5;padding:4px 0}
#chatInput::placeholder{color:var(--c3)}
.send-btn{width:38px;height:38px;border-radius:11px;border:none;background:var(--accent);color:#fff;cursor:pointer;transition:.15s;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.send-btn:hover{background:var(--accent-h);transform:scale(1.05)}
.send-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}

/* ─── DeepSeek-style bottom toolbar under input-row ─── */
.input-toolbar{
    display:flex;align-items:center;gap:6px;
    padding:8px 2px 0;
    flex-wrap:wrap;
    min-height:38px;
}
.input-toolbar-left{
    display:flex;align-items:center;gap:6px;
    flex:1;flex-wrap:wrap;
}
/* Feature pill buttons (DeepSeek style: icon + label) */
.feat-pill{
    display:inline-flex;align-items:center;gap:5px;
    padding:5px 12px;
    border-radius:20px;
    border:1.5px solid var(--border);
    background:transparent;
    font-size:12px;font-weight:500;
    color:var(--c2);
    cursor:pointer;
    transition:all .15s;
    font-family:inherit;
    white-space:nowrap;
    line-height:1;
}
.feat-pill i{font-size:11px}
.feat-pill:hover{
    border-color:var(--accent);
    color:var(--accent);
    background:var(--accent-light);
}
.feat-pill.active{
    border-color:var(--accent);
    color:var(--accent);
    background:var(--accent-light);
}

/* Quick search chips row (shown in welcome mode, collapsed after first send) */
.input-chips-row{
    display:flex;align-items:center;gap:6px;
    padding:6px 2px 0;
    flex-wrap:wrap;
    transition:all .25s;
}
.input-chips-row.hidden{
    display:none;
}
.input-chip{
    padding:5px 12px;
    background:#f8fafc;
    border:1px solid var(--border);
    border-radius:20px;
    font-size:11.5px;
    color:var(--c2);
    cursor:pointer;
    transition:.15s;
    display:inline-flex;align-items:center;gap:5px;
    font-family:inherit;
    white-space:nowrap;
}
.input-chip i{font-size:10px;opacity:.7}
.input-chip:hover{
    border-color:var(--accent);
    color:var(--accent);
    background:var(--accent-light);
    transform:translateY(-1px);
    box-shadow:0 3px 8px rgba(249,115,22,.1);
}

/* ═══════════════════════════════════════════════════
   SEARCH HISTORY PANEL
═══════════════════════════════════════════════════ */
.history-panel{position:fixed;right:-340px;top:0;bottom:0;width:320px;background:var(--card);border-left:1px solid var(--border);z-index:210;transition:right .3s;box-shadow:-4px 0 24px rgba(0,0,0,.06);display:flex;flex-direction:column}
.history-panel.open{right:0}
.hp-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.hp-head h3{font-size:14px;font-weight:700;color:var(--c1)}
.hp-close{width:30px;height:30px;border:none;background:var(--border);border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--c2);font-size:13px}
.hp-body{flex:1;overflow-y:auto;padding:12px}
.hp-item{padding:10px 12px;border-radius:9px;cursor:pointer;transition:.15s;border:1px solid transparent;margin-bottom:6px;display:flex;align-items:flex-start;gap:8px}
.hp-item:hover{background:#f8fafc;border-color:var(--border)}
.hp-item-content{flex:1;min-width:0}
.hp-item-q{font-size:13px;font-weight:600;color:var(--c1);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hp-item-meta{font-size:11px;color:var(--c3);display:flex;gap:8px}
.hp-item-del{flex-shrink:0;width:22px;height:22px;border:none;background:transparent;border-radius:5px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--c3);font-size:11px;opacity:0;transition:.15s;margin-top:1px}
.hp-item:hover .hp-item-del{opacity:1}.hp-item-del:hover{background:#fee2e2;color:#dc2626}
.hp-clear-all{width:100%;padding:7px 12px;border:1.5px dashed var(--border);background:transparent;border-radius:8px;font-size:11px;font-weight:600;color:var(--c3);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s;margin-top:4px}
.hp-clear-all:hover{border-color:#dc2626;color:#dc2626;background:#fff5f5}
.hp-empty{text-align:center;padding:40px 20px;color:var(--c3)}
.hp-empty i{font-size:36px;opacity:.3;display:block;margin-bottom:10px}

/* ═══════════════════════════════════════════════════
   STORAGE BROWSER MODAL
═══════════════════════════════════════════════════ */
.sbm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:300;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.sbm-overlay.show{display:flex}
.sbm{width:min(860px,96vw);max-height:90vh;background:#fff;border-radius:20px;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.25);overflow:hidden;animation:sbmIn .25s cubic-bezier(.34,1.56,.64,1)}
@keyframes sbmIn{from{opacity:0;transform:scale(.93) translateY(16px)}to{opacity:1;transform:none}}
.sbm-hdr{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,#f0fdf4,#fff)}
.sbm-hdr-icon{width:40px;height:40px;border-radius:11px;background:var(--ok);color:#fff;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.sbm-hdr-title{flex:1}
.sbm-hdr-title h2{font-size:16px;font-weight:800;color:var(--c1)}
.sbm-hdr-title p{font-size:11px;color:var(--c3);margin-top:1px}
.sbm-close{width:34px;height:34px;border:none;background:var(--border);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--c2);font-size:14px;transition:.15s}
.sbm-close:hover{background:#e2e8f0;color:var(--c1)}

/* Breadcrumb */
.sbm-breadcrumb{padding:10px 22px;border-bottom:1px solid var(--border);background:#f8fafc;display:flex;align-items:center;gap:5px;flex-wrap:wrap;min-height:42px}
.sbm-crumb{font-size:12px;color:var(--c3);display:flex;align-items:center;gap:5px}
.sbm-crumb a{color:var(--info);font-weight:600;cursor:pointer;text-decoration:none}
.sbm-crumb a:hover{text-decoration:underline}
.sbm-crumb-sep{color:var(--c3);font-size:10px}
.sbm-crumb.active span{font-weight:700;color:var(--c1)}

/* Body */
.sbm-body{flex:1;overflow-y:auto;padding:18px 22px}
.sbm-body::-webkit-scrollbar{width:4px}
.sbm-body::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px}

/* Building grid */
.sbm-bld-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px}
.sbm-bld-card{background:var(--card);border:1.5px solid var(--border);border-radius:14px;padding:16px;cursor:pointer;transition:.2s;display:flex;flex-direction:column;gap:6px;position:relative;overflow:hidden}
.sbm-bld-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--ok),#34d399)}
.sbm-bld-card:hover{border-color:var(--ok);transform:translateY(-3px);box-shadow:0 8px 24px rgba(16,185,129,.12)}
.sbm-bld-code{font-size:22px;font-weight:900;color:var(--ok);font-family:'JetBrains Mono',monospace;line-height:1}
.sbm-bld-name{font-size:12px;font-weight:600;color:var(--c1);line-height:1.3}
.sbm-bld-meta{display:flex;gap:8px;margin-top:4px}
.sbm-bld-pill{background:var(--ok-bg);color:#166534;border:1px solid var(--ok-border);padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:3px}
.sbm-bld-pill.rooms{background:#eff6ff;color:#1e40af;border-color:#bfdbfe}
.sbm-bld-arr{position:absolute;bottom:12px;right:12px;color:var(--c3);font-size:11px;transition:.2s}
.sbm-bld-card:hover .sbm-bld-arr{color:var(--ok);transform:translateX(3px)}

/* Room list */
.sbm-room-list{display:flex;flex-direction:column;gap:8px}
.sbm-room-row{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:13px 16px;cursor:pointer;display:flex;align-items:center;gap:12px;transition:.2s}
.sbm-room-row:hover{border-color:var(--ok);background:var(--ok-bg);transform:translateX(4px)}
.sbm-room-icon{width:36px;height:36px;border-radius:9px;background:var(--ok-bg);color:var(--ok);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.sbm-room-info{flex:1;min-width:0}
.sbm-room-code{font-size:13px;font-weight:800;color:var(--c1);font-family:'JetBrains Mono',monospace}
.sbm-room-name{font-size:11px;color:var(--c3);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sbm-room-count{background:var(--ok);color:#fff;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;white-space:nowrap}
.sbm-room-arr{color:var(--c3);font-size:12px;transition:.2s}
.sbm-room-row:hover .sbm-room-arr{color:var(--ok);transform:translateX(3px)}

/* Container table */
.sbm-ctr-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px}
.sbm-ctr-title{font-size:13px;font-weight:700;color:var(--c1);display:flex;align-items:center;gap:8px}
.sbm-ctr-badge{background:var(--ok);color:#fff;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700}
.sbm-ctr-search{padding:7px 12px;border:1px solid var(--border);border-radius:8px;font-size:12px;outline:none;width:200px;font-family:inherit;transition:.15s}
.sbm-ctr-search:focus{border-color:var(--ok);box-shadow:0 0 0 3px rgba(16,185,129,.1)}
.sbm-tbl{width:100%;border-collapse:collapse;font-size:12px}
.sbm-tbl thead th{background:#f0fdf4;padding:8px 10px;text-align:left;font-weight:700;color:#166534;border-bottom:2px solid var(--ok-border);white-space:nowrap;position:sticky;top:0}
.sbm-tbl tbody tr{transition:.1s}
.sbm-tbl tbody tr:hover{background:#f0fdf4}
.sbm-tbl td{padding:8px 10px;border-bottom:1px solid #f0fdf4;vertical-align:middle}
.sbm-tbl td .chem-nm{font-weight:700;color:var(--c1)}
.sbm-tbl td .cas-nm{font-size:10px;color:var(--c3);font-family:'JetBrains Mono',monospace}
.sbm-tbl td .qty-val{font-weight:700;color:#166534}
.sbm-tbl td .qr-code{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--c2)}
.sbm-tbl td .type-pill{padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700;background:#e0f2fe;color:#0369a1}
.sbm-tbl .expiry-warn{color:var(--warn);font-weight:700}
.sbm-tbl .expiry-ok{color:var(--ok);font-size:10px}
.sbm-empty{text-align:center;padding:36px;color:var(--c3)}
.sbm-empty i{font-size:36px;opacity:.3;display:block;margin-bottom:10px}

/* Loading spinner */
.sbm-loading{text-align:center;padding:40px;color:var(--c3)}
.sbm-spinner{width:36px;height:36px;border:3px solid var(--border);border-top-color:var(--ok);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px}

@media(max-width:600px){
    .sbm-bld-grid{grid-template-columns:1fr 1fr}
    .sbm{max-height:95vh}
    .sbm-ctr-search{width:100%}
    .sbm-ctr-hdr{flex-direction:column;align-items:flex-start}
}

/* ═══════════════════════════════════════════════════
   MOBILE RESPONSIVE — ≤ 768px
═══════════════════════════════════════════════════ */
@media(max-width:768px){

    /* Show hamburger (mob), hide desktop collapse btn */
    .mob-btn{display:flex}
    .sb-toggle-btn{display:none}

    /* Sidebar: slide off-screen by default; override collapsed on mobile */
    .sb.collapsed{width:min(300px,85vw);padding:16px;overflow-y:auto;border-right:1px solid rgba(255,255,255,.06)}
    .main.sb-hidden{margin-left:0}
    .sb{
        transform:translateX(-100%);
        position:fixed;
        top:0;left:0;bottom:0;
        width:min(300px,85vw);
        z-index:200;
        box-shadow:4px 0 32px rgba(0,0,0,.4);
        padding:16px;
        overflow-y:auto;
    }
    .sb.show{transform:translateX(0)}

    /* Main: fill full width */
    .main{
        margin-left:0;
        width:100%;
        height:100dvh;
    }

    /* Header: give space for hamburger */
    .chat-hdr{
        padding:0 12px;
        height:54px;
        gap:8px;
    }
    .chat-hdr-left h2{font-size:14px}
    .chat-hdr-left .ai-status{font-size:10px}

    /* Chat messages area */
    .chat-msgs{padding:14px 12px}

    /* Welcome screen */
    .welcome{padding:0 2px}

    /* Capability cards: 2 per row */
    .cap-grid{
        grid-template-columns:repeat(2,1fr);
        gap:8px;
        margin-bottom:12px;
    }
    .cap-card{padding:12px}
    .cap-icon{width:32px;height:32px;font-size:14px;margin-bottom:7px}
    .cap-card h4{font-size:12px}
    .cap-card p{font-size:10px}

    /* Quick chips: wrap nicely */
    .chip{padding:7px 12px;font-size:11px}

    /* Messages */
    .msg{gap:8px;margin-bottom:16px}
    .msg-av{width:30px;height:30px;font-size:13px;border-radius:8px}
    .msg-body{max-width:calc(100% - 38px)}
    .msg-bubble{padding:11px 14px;font-size:13px;border-radius:12px}

    /* Input area */
    .input-area{
        padding:10px 12px;
        padding-bottom:max(10px, env(safe-area-inset-bottom));
    }
    .input-row{border-radius:14px;padding:6px 6px 6px 12px}
    #chatInput{font-size:14px;min-height:34px}
    .send-btn{width:36px;height:36px;border-radius:10px}

    /* ── Mobile: toolbar single scrollable row ── */
    .input-toolbar{
        min-height:auto;
        padding:6px 0 0;
        gap:0;
        flex-wrap:nowrap;
    }
    .input-toolbar-left{
        flex-wrap:nowrap;
        overflow-x:auto;
        scrollbar-width:none;
        gap:5px;
        padding-bottom:2px;
        /* fade right edge to hint more content */
        -webkit-mask-image:linear-gradient(to right,#000 78%,transparent 100%);
        mask-image:linear-gradient(to right,#000 78%,transparent 100%);
    }
    .input-toolbar-left::-webkit-scrollbar{display:none}
    .feat-pill{
        padding:5px 11px;
        font-size:11.5px;
        flex-shrink:0;
    }

    /* ── Mobile: chips single scrollable row ── */
    .input-chips-row{
        flex-wrap:nowrap;
        overflow-x:auto;
        scrollbar-width:none;
        padding:5px 0 0;
        /* fade right edge */
        -webkit-mask-image:linear-gradient(to right,#000 82%,transparent 100%);
        mask-image:linear-gradient(to right,#000 82%,transparent 100%);
    }
    .input-chips-row::-webkit-scrollbar{display:none}
    .input-chip{flex-shrink:0}

    /* History panel: full-width slide */
    .history-panel{
        width:100%;
        right:-100%;
    }
    .history-panel.open{right:0}

    /* Thinking block */
    .thinking-block{padding:14px 14px 10px;border-radius:12px}
    .thinking-ticker-wrap{min-width:100px;max-width:130px}

    /* Chemical hero card: stack vertically */
    .chem-hero{flex-direction:column;gap:12px;padding:14px}
    .chem-hero-img{width:100px;height:100px;align-self:center}
    .chem-hero-name{font-size:17px}

    /* Result cards */
    .rc-hdr{gap:10px;padding:12px}
    .rc-img{width:64px;height:64px}
    .rc-name{font-size:14px}

    /* Info grid: 2 columns */
    .info-grid{grid-template-columns:1fr 1fr;gap:8px}

    /* GHS grid: smaller diamonds */
    .ghs-diamond-wrap{width:68px;height:68px}
    .ghs-grid{gap:14px 8px}
    .ghs-name{font-size:9px;max-width:65px}

    /* Location table: horizontal scroll */
    .loc-tbl{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch}

    /* SDS buttons: wrap */
    .sds-btns{gap:6px}
    .sds-btn{padding:8px 12px;font-size:11px}

    /* No-result external links: 1 column */
    .ext-grid{grid-template-columns:1fr}

    /* 3D model grid: 2 col */
    .model-grid{grid-template-columns:repeat(2,1fr)}

    /* Storage browser modal */
    .sbm{border-radius:16px}
    .sbm-bld-grid{grid-template-columns:1fr 1fr}
    .sbm-hdr{padding:14px 16px}

    /* Login card inside sidebar — full width, comfortable */
    .login-card{padding:14px;border-radius:12px}
    .fg input{padding:10px 12px;font-size:14px}
    .login-btn{padding:12px;font-size:14px}

    /* Sidebar logo shrink */
    .sb-logo-icon{width:38px;height:38px;font-size:17px}
    .sb-logo-text h1{font-size:16px}
    .sb-logo-text p{font-size:9px}
}

/* Extra small: ≤ 380px */
@media(max-width:380px){
    .cap-grid{grid-template-columns:1fr 1fr;gap:6px}
    .cap-card{padding:10px}
    .cap-card h4{font-size:11px}
    .cap-card p{display:none}
    .chip{padding:6px 10px;font-size:10px}
    .chat-hdr-left h2{font-size:13px}
    .ghs-diamond-wrap{width:56px;height:56px}
    .ghs-grid{gap:10px 6px}

    /* ── XS: toolbar pills → icon only (ซ่อน label) ── */
    .feat-pill span.pill-label{display:none}
    .feat-pill{padding:6px 10px;border-radius:50%;width:32px;height:32px;justify-content:center}
    .feat-pill i{font-size:12px}

    /* chips a bit smaller */
    .input-chip{padding:5px 10px;font-size:11px}
}

/* ── Forgot Password Modal ──────────────────────────── */
.fp-ov{
  position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(5px);
  z-index:500;display:flex;align-items:center;justify-content:center;padding:16px;
  opacity:0;pointer-events:none;transition:opacity .2s;
}
.fp-ov.show{opacity:1;pointer-events:all}
.fp-box{
  background:#1e1e1e;border:1px solid rgba(255,255,255,.12);border-radius:18px;
  width:380px;max-width:94vw;
  box-shadow:0 24px 60px rgba(0,0,0,.5);
  transform:translateY(18px) scale(.97);transition:transform .22s,opacity .22s;
  overflow:hidden;
}
.fp-ov.show .fp-box{transform:none}
.fp-hdr{
  display:flex;align-items:center;gap:12px;padding:20px 20px 14px;
  border-bottom:1px solid rgba(255,255,255,.08);
}
.fp-hdr-ic{
  width:38px;height:38px;border-radius:10px;
  background:rgba(249,115,22,.18);color:var(--accent);
  display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;
}
.fp-hdr h4{font-size:14px;font-weight:700;color:#fff;margin:0}
.fp-hdr p{font-size:11px;color:rgba(255,255,255,.45);margin:3px 0 0}
.fp-close{
  margin-left:auto;width:28px;height:28px;border:none;
  background:rgba(255,255,255,.07);border-radius:7px;color:rgba(255,255,255,.5);
  cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;
  transition:background .15s;
}
.fp-close:hover{background:rgba(255,255,255,.13);color:#fff}
.fp-body{padding:18px 20px}
.fp-step{display:none}
.fp-step.active{display:block}
/* Step 1 — input */
.fp-inp{
  width:100%;padding:11px 14px;border:1px solid rgba(255,255,255,.12);border-radius:9px;
  font-size:13px;background:rgba(255,255,255,.06);color:#fff;
  transition:border-color .15s;font-family:inherit;
}
.fp-inp:focus{outline:none;border-color:var(--accent);background:rgba(255,255,255,.09)}
.fp-inp::placeholder{color:rgba(255,255,255,.3)}
.fp-btn{
  width:100%;padding:11px;background:var(--accent);color:#fff;border:none;
  border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;
  transition:background .15s;font-family:inherit;margin-top:12px;
  display:flex;align-items:center;justify-content:center;gap:7px;
}
.fp-btn:hover{background:var(--accent-h)}
.fp-btn:disabled{opacity:.55;pointer-events:none}
.fp-err{
  padding:8px 12px;background:rgba(220,38,38,.2);border:1px solid rgba(220,38,38,.3);
  border-radius:8px;color:#fca5a5;font-size:11.5px;margin-top:10px;
  display:none;align-items:center;gap:6px;
}
.fp-err.show{display:flex}
/* Step 2 — show link */
.fp-link-box{
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
  border-radius:10px;padding:12px 14px;word-break:break-all;
  font-family:'JetBrains Mono','Consolas',monospace;font-size:11px;
  color:rgba(255,255,255,.75);line-height:1.5;
}
.fp-copy-btn{
  display:flex;align-items:center;gap:6px;margin-top:10px;
  width:100%;padding:9px;background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);border-radius:9px;
  color:rgba(255,255,255,.8);font-size:12px;font-weight:600;
  cursor:pointer;font-family:inherit;transition:background .15s;justify-content:center;
}
.fp-copy-btn:hover{background:rgba(255,255,255,.14)}
.fp-open-btn{
  display:flex;align-items:center;gap:6px;margin-top:8px;
  width:100%;padding:9px;background:var(--accent);
  border:none;border-radius:9px;
  color:#fff;font-size:12px;font-weight:700;
  cursor:pointer;font-family:inherit;transition:background .15s;justify-content:center;
}
.fp-open-btn:hover{background:var(--accent-h)}
.fp-note{font-size:10.5px;color:rgba(255,255,255,.35);margin-top:10px;text-align:center;line-height:1.5}
</style>
</head>
<body>

<div class="mob-overlay" id="mobOverlay" onclick="toggleSidebar()"></div>

<div class="app">

<!-- ═══ SIDEBAR ═══ -->
<aside class="sb" id="sidebar">
    <div class="sb-logo">
        <div class="sb-logo-icon"><i class="fas fa-flask-vial"></i></div>
        <div class="sb-logo-text">
            <h1>SUT ChemBot</h1>
            <p>AI Chemical Search Assistant</p>
        </div>
    </div>

    <div class="sb-lang">
        <a href="?lang=th" class="<?php echo $lang==='th'?'active':''; ?>">🇹🇭 ภาษาไทย</a>
        <a href="?lang=en" class="<?php echo $lang==='en'?'active':''; ?>">🇬🇧 English</a>
    </div>

    <div class="sb-login">
        <div class="login-card">
            <h3><i class="fas fa-lock"></i> <?php echo $lang==='th'?'เข้าสู่ระบบจัดการ':'Sign In to Manage'; ?></h3>

            <?php if ($demoEnabled && !empty($allUsers)): ?>
            <div class="demo-box">
                <div class="demo-title"><i class="fas fa-vial"></i> <?php echo $lang==='th'?'บัญชีทดลองใช้':'Demo Accounts'; ?></div>
                <?php foreach ($roleConfig as $rKey => $rc):
                    $uList = $usersByRole[$rKey] ?? [];
                    if (empty($uList)) continue;
                ?>
                <div class="demo-role-group">
                    <button type="button" class="demo-role-btn" onclick="toggleDD(this)">
                        <div class="demo-role-icon" style="background:<?php echo $rc['bg']; ?>;color:<?php echo $rc['color']; ?>;border:1px solid <?php echo $rc['border']; ?>"><i class="fas <?php echo $rc['icon']; ?>"></i></div>
                        <div class="demo-role-info">
                            <div class="demo-role-name"><?php echo $lang==='th'?$rc['th']:$rc['en']; ?></div>
                            <div class="demo-role-count"><?php echo count($uList); ?> <?php echo $lang==='th'?'บัญชี':'accounts'; ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size:9px;color:rgba(255,255,255,.4);margin-left:auto"></i>
                    </button>
                    <div class="demo-dd">
                        <?php foreach ($uList as $du):
                            $initial = mb_substr($du['first_name'] ?: $du['username'], 0, 1, 'UTF-8');
                        ?>
                        <div class="demo-user" onclick="fillDemo('<?php echo htmlspecialchars($du['username']); ?>')">
                            <div class="demo-avatar" style="background:<?php echo $rc['color']; ?>"><?php echo $initial; ?></div>
                            <div><?php echo htmlspecialchars($du['full_name_th'] ?: $du['username']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form id="loginForm">
                <div class="fg">
                    <label><?php echo $lang==='th'?'ชื่อผู้ใช้':'Username'; ?></label>
                    <input type="text" id="username" placeholder="<?php echo $lang==='th'?'ชื่อผู้ใช้หรืออีเมล':'Username or email'; ?>" autocomplete="username">
                </div>
                <div class="fg">
                    <label><?php echo $lang==='th'?'รหัสผ่าน':'Password'; ?></label>
                    <input type="password" id="password" placeholder="••••••••" autocomplete="current-password">
                </div>
                <div class="login-opts">
                    <label><input type="checkbox" id="remember" style="width:auto"> <?php echo $lang==='th'?'จดจำฉัน':'Remember'; ?></label>
                    <a href="#" onclick="openForgotModal();return false;"><?php echo $lang==='th'?'ลืมรหัสผ่าน?':'Forgot?'; ?></a>
                </div>
                <button type="submit" id="loginBtn" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> <?php echo $lang==='th'?'เข้าสู่ระบบ':'Sign In'; ?>
                </button>
            </form>
            <div id="errMsg" class="err-msg"><i class="fas fa-exclamation-circle"></i><span id="errText"></span></div>
            <div style="text-align:center;margin-top:10px;font-size:11px;color:rgba(255,255,255,.4)">
                <?php echo $lang==='th'?'ยังไม่มีบัญชี?':'No account?'; ?>
                <a href="/v1/pages/register.php" style="color:var(--accent);font-weight:600"><?php echo $lang==='th'?'สมัครสมาชิก':'Register'; ?></a>
            </div>
        </div>
    </div>

    <div class="sb-footer">
        SUT ChemBot v2.0 &nbsp;|&nbsp; <a href="#"><?php echo date('Y'); ?></a>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">

    <!-- Header -->
    <div class="chat-hdr">
        <button class="mob-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <button class="sb-toggle-btn" id="sbToggleBtn" onclick="toggleSidebarCollapse()" title="<?php echo $lang==='th'?'ยุบ/แสดง Sidebar':'Toggle sidebar'; ?>"><i class="fas fa-bars"></i></button>
        <div class="chat-hdr-left">
            <div>
                <h2><?php echo $lang==='th'?'Chemical Search Assistant':'Chemical Search Assistant'; ?></h2>
                <div class="ai-status"><span class="ai-dot"></span> <?php echo $lang==='th'?'พร้อมใช้งาน — ฐานข้อมูลภายใน':'Ready — Local database'; ?></div>
            </div>
        </div>
        <div class="hdr-actions">
            <button class="hdr-btn" title="<?php echo $lang==='th'?'ประวัติการค้นหา':'Search history'; ?>" onclick="toggleHistory()" id="histBtn"><i class="fas fa-history"></i></button>
            <button class="hdr-btn" title="<?php echo $lang==='th'?'ล้างการสนทนา':'Clear chat'; ?>" onclick="clearChat()"><i class="fas fa-trash-alt"></i></button>
        </div>
    </div>

    <!-- Messages -->
    <div class="chat-msgs" id="chatMsgs">
        <div class="welcome" id="welcomeScreen">

            <!-- Welcome hero text -->
            <div style="text-align:center;padding:60px 20px 40px;">
                <div style="width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,var(--accent),#fb923c);display:inline-flex;align-items:center;justify-content:center;font-size:28px;color:#fff;margin-bottom:20px;box-shadow:0 8px 28px rgba(249,115,22,.28)">
                    <i class="fas fa-flask-vial"></i>
                </div>
                <h2 style="font-size:26px;font-weight:800;color:var(--c1);margin-bottom:8px"><?php echo $lang==='th'?'มีอะไรให้ฉันช่วยไหมคะ?':'How can I help you today?'; ?></h2>
                <p style="font-size:14px;color:var(--c3);max-width:440px;margin:0 auto;line-height:1.6"><?php echo $lang==='th'?'ค้นหาสารเคมี ดูข้อมูลความปลอดภัย ตรวจสอบที่จัดเก็บ และอื่นๆ อีกมากมาย':'Search chemicals, view safety data, check storage locations, and much more.'; ?></p>
            </div>

        </div>
        <div class="msgs-area" id="msgsArea" style="display:none"></div>
    </div>

    <!-- Input -->
    <div class="input-area">
        <div class="input-wrap">
            <div class="input-row">
                <textarea id="chatInput" placeholder="<?php echo $lang==='th'?'มีอะไรให้ฉันช่วยไหมคะ...':'How can I help you...'; ?>" rows="1" onkeydown="onKey(event)"></textarea>
                <button class="send-btn" id="sendBtn" onclick="doSend()"><i class="fas fa-paper-plane"></i></button>
            </div>

            <!-- ─── DeepSeek-style toolbar: feature pills ─── -->
            <div class="input-toolbar" id="inputToolbar">
                <div class="input-toolbar-left">
                    <button class="feat-pill" id="pillSearch" onclick="sendMsg('<?php echo $lang==='th'?'ค้นหาสาร เอทานอล':'search ethanol'; ?>')" title="<?php echo $lang==='th'?'ค้นหาสารเคมี':'Chemical Search'; ?>">
                        <i class="fas fa-search"></i><span class="pill-label"> <?php echo $lang==='th'?'ค้นหาสาร':'Search'; ?></span>
                    </button>
                    <button class="feat-pill" id="pillLocation" onclick="sendMsg('<?php echo $lang==='th'?'อะซิโตน อยู่ที่ไหน':'where is acetone'; ?>')" title="<?php echo $lang==='th'?'ตำแหน่งจัดเก็บ':'Storage Location'; ?>">
                        <i class="fas fa-map-marker-alt"></i><span class="pill-label"> <?php echo $lang==='th'?'ที่จัดเก็บ':'Location'; ?></span>
                    </button>
                    <button class="feat-pill" id="pillSDS" onclick="sendMsg('<?php echo $lang==='th'?'SDS กรดซัลฟิวริก':'SDS sulfuric acid'; ?>')" title="SDS / Safety">
                        <i class="fas fa-file-shield"></i><span class="pill-label"> SDS</span>
                    </button>
                    <button class="feat-pill" id="pillHazard" onclick="sendMsg('<?php echo $lang==='th'?'ความอันตราย H2SO4':'hazard H2SO4'; ?>')" title="GHS Hazard">
                        <i class="fas fa-exclamation-triangle"></i><span class="pill-label"> GHS</span>
                    </button>
                    <button class="feat-pill" id="pillStorage" onclick="openStorageBrowser()" title="<?php echo $lang==='th'?'สถานที่จัดเก็บ':'Storage Browser'; ?>" style="border-color:var(--ok-border);color:#166534">
                        <i class="fas fa-warehouse"></i><span class="pill-label"> <?php echo $lang==='th'?'คลังสาร':'Storage'; ?></span>
                    </button>
                    <button class="feat-pill" id="pillPeriodic" onclick="sendMsg('<?php echo $lang==='th'?'ตารางธาตุ':'periodic table'; ?>')" title="<?php echo $lang==='th'?'ตารางธาตุ':'Periodic Table'; ?>" style="border-color:#c7d2fe;color:#4338ca">
                        <i class="fas fa-table-cells"></i><span class="pill-label"> <?php echo $lang==='th'?'ตารางธาตุ':'Periodic'; ?></span>
                    </button>
                </div>
            </div>

            <!-- ─── Quick search chips (collapsed after first message) ─── -->
            <div class="input-chips-row" id="inputChipsRow">
                <button class="input-chip" onclick="sendMsg('<?php echo $lang==='th'?'เอทานอล':'ethanol'; ?>')"><i class="fas fa-flask"></i> Ethanol</button>
                <button class="input-chip" onclick="sendMsg('7664-93-9')"><i class="fas fa-hashtag"></i> 7664-93-9</button>
                <button class="input-chip" onclick="sendMsg('<?php echo $lang==='th'?'อะซิโตน อยู่ที่ไหน':'where is acetone'; ?>')"><i class="fas fa-map-marker-alt"></i> <?php echo $lang==='th'?'อะซิโตน ที่ไหน':'Acetone?'; ?></button>
                <button class="input-chip" onclick="sendMsg('H2SO4')"><i class="fas fa-atom"></i> H₂SO₄</button>
                <button class="input-chip" onclick="sendMsg('NaOH')"><i class="fas fa-atom"></i> NaOH</button>
                <button class="input-chip" onclick="sendMsg('<?php echo $lang==='th'?'SDS เมทานอล':'SDS methanol'; ?>')"><i class="fas fa-file-shield"></i> SDS Methanol</button>
                <button class="input-chip" onclick="sendMsg('<?php echo $lang==='th'?'ความอันตราย คลอรีน':'chlorine hazard'; ?>')"><i class="fas fa-biohazard"></i> <?php echo $lang==='th'?'อันตราย Cl₂':'Cl₂ hazard'; ?></button>
                <button class="input-chip" onclick="sendMsg('<?php echo $lang==='th'?'กรดไฮโดรคลอริก':'hydrochloric acid'; ?>')"><i class="fas fa-vial"></i> HCl</button>
            </div>
        </div>
    </div>

</main>
</div><!-- /.app -->

<!-- History Panel -->
<div class="history-panel" id="histPanel">
    <div class="hp-head">
        <h3><i class="fas fa-history"></i> <?php echo $lang==='th'?'ประวัติการค้นหา':'Search History'; ?></h3>
        <div style="display:flex;gap:6px;align-items:center">
            <button class="hp-close" onclick="clearHistory()" title="<?php echo $lang==='th'?'ล้างทั้งหมด':'Clear all'; ?>" style="width:auto;padding:0 10px;font-size:11px;font-weight:600;color:#dc2626;background:#fee2e2;border-radius:7px;display:flex;align-items:center;gap:4px"><i class="fas fa-trash-alt" style="font-size:10px"></i> <?php echo $lang==='th'?'ล้าง':'Clear'; ?></button>
            <button class="hp-close" onclick="toggleHistory()"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="hp-body" id="hpBody">
        <div class="hp-empty"><i class="fas fa-clock"></i><p><?php echo $lang==='th'?'ยังไม่มีประวัติการค้นหา':'No search history yet'; ?></p></div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════
// CONSTANTS & STATE
// ═══════════════════════════════════════════════
const LANG = '<?php echo $lang; ?>';
const L = (th, en) => LANG === 'th' ? th : en;

let searchHistory = JSON.parse(localStorage.getItem('chembot_history') || '[]');
let isProcessing  = false;

// ═══════════════════════════════════════════════
// STARTUP
// ═══════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    loadHeroStats();
    renderHistory();
    autoResizeTextarea();
});

function loadHeroStats() {
    fetch('/v1/api/ai_assistant.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get_stats', lang: LANG})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success && d.data) {
            const el = document.getElementById('heroContainers');
            if (el) el.textContent = d.data.containers?.toLocaleString() ?? '-';
        }
    })
    .catch(() => {});
}

// ═══════════════════════════════════════════════
// INPUT
// ═══════════════════════════════════════════════
function autoResizeTextarea() {
    const ta = document.getElementById('chatInput');
    ta.addEventListener('input', () => {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 180) + 'px';
    });
}

function onKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doSend(); }
}

function sendMsg(msg) {
    document.getElementById('chatInput').value = msg;
    doSend();
}

async function doSend() {
    const ta = document.getElementById('chatInput');
    const msg = ta.value.trim();
    if (!msg || isProcessing) return;

    isProcessing = true;
    document.getElementById('sendBtn').disabled = true;
    ta.value = '';
    ta.style.height = 'auto';

    // Hide quick-chips row after first send (DeepSeek style)
    const chipsRow = document.getElementById('inputChipsRow');
    if (chipsRow) chipsRow.classList.add('hidden');

    // Show chat area
    document.getElementById('welcomeScreen').style.display = 'none';
    document.getElementById('msgsArea').style.display = 'block';

    // User bubble
    appendMsg(msg, 'user');

    // Thinking block
    const thinkId = appendThinking(msg);

    // Random thinking delay: 3–12 seconds (looks like real reasoning)
    const thinkDelay = (3 + Math.random() * 9) * 1000;
    await new Promise(res => setTimeout(res, thinkDelay));

    try {
        const r = await fetch('/v1/api/ai_assistant.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'chat_local', message: msg, lang: LANG })
        });
        const d = await r.json();

        document.getElementById(thinkId)?.remove();

        if (d.success) {
            appendMsg(null, 'ai', d.html || escHtml(d.response || ''));
            saveHistory(msg, d.query_type);
        } else {
            appendMsg(d.error || L('เกิดข้อผิดพลาด', 'An error occurred'), 'ai');
        }
    } catch (err) {
        document.getElementById(thinkId)?.remove();
        appendMsg(L('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'Cannot connect to server'), 'ai');
    }

    isProcessing = false;
    document.getElementById('sendBtn').disabled = false;
    scrollBottom();
}

// ═══════════════════════════════════════════════
// MESSAGE RENDERING
// ═══════════════════════════════════════════════
function runScripts(el) {
    el.querySelectorAll('script').forEach(old => {
        const s = document.createElement('script');
        if (old.src) { s.src = old.src; } else { s.textContent = old.textContent; }
        document.head.appendChild(s);
        s.remove();
        old.remove();
    });
}

function appendMsg(text, role, html = null) {
    const area = document.getElementById('msgsArea');
    const div  = document.createElement('div');
    div.className = `msg ${role}`;
    const name = role === 'user' ? L('คุณ','You') : 'SUT ChemBot';
    div.innerHTML = `
        <div class="msg-av"><i class="fas fa-${role === 'user' ? 'user' : 'robot'}"></i></div>
        <div class="msg-body">
            <div class="msg-name">${name}</div>
            <div class="msg-bubble">${html ?? escHtml(text)}</div>
        </div>`;
    area.appendChild(div);
    if (html) runScripts(div);
    scrollBottom();
}

function appendThinking(query) {
    const id   = 'think-' + Date.now();
    const area = document.getElementById('msgsArea');
    const div  = document.createElement('div');
    div.id = id;
    div.className = 'msg ai';

    // ── Classify query ──
    const isCAS      = /\d{2,7}-\d{2}-\d/.test(query);
    const isSDS      = /sds|msds|ความปลอดภัย|safety/i.test(query);
    const isHazard   = /อันตราย|hazard|ghs|pictogram|signal/i.test(query);
    const isLocation = /อยู่|ที่ไหน|where|location|stored|เก็บ|ตำแหน่ง/i.test(query);
    const isExpiry   = /หมดอายุ|expir/i.test(query);
    const isStock    = /สต็อก|สต๊อก|stock|inventory|ทั้งหมด/i.test(query);
    const isBrowse   = /storage|คลัง|อาคาร|browse/i.test(query);

    // ── Steps [icon, label] ──
    const steps = isCAS
        ? [['hashtag',          L('ตรวจสอบ CAS Registry',     'Validating CAS registry')],
           ['database',         L('ค้นหาในฐานข้อมูล',         'Querying local database')],
           ['map-marker-alt',   L('โหลดตำแหน่งจัดเก็บ',       'Resolving storage locations')],
           ['shield-alt',       L('โหลดข้อมูล GHS / SDS',      'Loading GHS & SDS data')]]
        : isSDS
        ? [['microscope',       L('ระบุสาร',                  'Identifying substance')],
           ['file-alt',         L('ค้นหา SDS',                'Fetching SDS document')],
           ['link',             L('ตรวจสอบลิงก์',             'Verifying source links')]]
        : isHazard
        ? [['exclamation-triangle', L('ระบุสาร',              'Identifying chemical')],
           ['radiation-alt',    L('ตรวจสอบ GHS Hazard',       'Evaluating GHS hazards')],
           ['list-ul',          L('รวบรวม H/P Statements',    'Compiling H & P statements')]]
        : isLocation
        ? [['search',           L('ระบุสาร',                  'Identifying chemical')],
           ['map-marker-alt',   L('ค้นหาตำแหน่งทั้งหมด',     'Finding all locations')],
           ['layer-group',      L('จัดกลุ่มตามอาคาร',         'Grouping by building')]]
        : isExpiry
        ? [['calendar-alt',     L('ดึงข้อมูลวันหมดอายุ',      'Fetching expiry records')],
           ['sort-amount-up',   L('จัดเรียงตามวันที่',         'Sorting by date')],
           ['bell',             L('ตรวจสอบระดับแจ้งเตือน',    'Checking alert thresholds')]]
        : isStock
        ? [['warehouse',        L('สแกนคลังทั้งหมด',          'Scanning full inventory')],
           ['chart-bar',        L('รวบรวมสถิติ',              'Aggregating statistics')],
           ['filter',           L('กรองและจัดรูปแบบ',         'Filtering & formatting')]]
        : isBrowse
        ? [['building',         L('โหลดข้อมูลอาคาร',          'Loading buildings')],
           ['door-open',        L('โหลดห้อง',                 'Loading rooms')],
           ['vial',             L('โหลดรายการสาร',            'Loading container list')]]
        : [['brain',            L('วิเคราะห์คำค้นหา',         'Analyzing query')],
           ['database',         L('ค้นหาในฐานข้อมูล',         'Searching database')],
           ['magic',            L('จัดรูปแบบผลลัพธ์',         'Formatting results')]];

    // ── Ticker texts ──
    const tickers = isCAS
        ? ['CAS: ' + query.trim(), 'JOIN chemicals…', 'GROUP BY building…']
        : isLocation
        ? [L('ระบุชื่อสาร…','Identifying…'), L('ค้นตำแหน่ง…','Resolving…'), L('เรียงอาคาร…','Sorting…')]
        : isSDS
        ? [L('ระบุสาร…','Identifying…'), L('ค้นหา SDS…','Fetching SDS…'), L('ตรวจสอบ…','Verifying…')]
        : [L('วิเคราะห์…','Parsing…'), 'SELECT FROM chemicals…', L('จัดรูปแบบ…','Formatting…')];

    // ── Skeleton HTML ──
    const skelHtml = (isCAS || isLocation)
        ? `<div class="skel-section">
              <div class="skel-line title"></div>
              <div class="skel-row"><div class="skel-line"></div><div class="skel-line short"></div></div>
              <div class="skel-line full"></div>
              <div class="skel-line med"></div>
              <div class="skel-row"><div class="skel-line long"></div><div class="skel-line short"></div></div>
           </div>`
        : `<div class="skel-section">
              <div class="skel-line title"></div>
              <div class="skel-line long"></div>
              <div class="skel-line med"></div>
           </div>`;

    // ── Build HTML ──
    div.innerHTML = `
        <div class="msg-av"><i class="fas fa-atom"></i></div>
        <div class="msg-body">
            <div class="msg-name">SUT ChemBot</div>
            <div class="msg-bubble">
                <div class="thinking-block">
                    <div class="thinking-header">
                        <div class="thinking-orbit">
                            <div class="thinking-orbit-core"></div>
                            <div class="thinking-orbit-dot"></div>
                            <div class="thinking-orbit-dot"></div>
                        </div>
                        <div class="thinking-title">${L('กำลังประมวลผล…','Processing…')}</div>
                        <div class="thinking-ticker-wrap">
                            <span class="thinking-ticker" id="${id}-ticker">${tickers[0]}</span>
                        </div>
                    </div>
                    <div class="thinking-steps">
                        ${steps.map((s, i) =>
                            `<div class="thinking-step ${i === 0 ? 'active' : 'pending'}" id="${id}-s${i}">
                                <span class="step-icon"><i class="fas fa-${s[0]}"></i></span>
                                <span class="step-label">${s[1]}</span>
                                <span class="step-time" id="${id}-t${i}"></span>
                            </div>`
                        ).join('')}
                    </div>
                    ${skelHtml}
                </div>
            </div>
        </div>`;

    area.appendChild(div);
    scrollBottom();

    // ── Step animation ──
    let currentStep = 0;
    const startTimes = [Date.now()];

    // Random target time per step: 0.01–2.53s weighted toward 0.3–1.4s
    function randTarget() {
        const r = Math.random();
        // bias: 70% chance 0.28–1.45s, 20% chance 0.01–0.28s, 10% chance 1.45–2.53s
        if (r < 0.20) return 0.01 + Math.random() * 0.27;
        if (r < 0.90) return 0.28 + Math.random() * 1.17;
        return 1.45 + Math.random() * 1.08;
    }

    // Animated counter: rapidly counts up from ~0 to target, then snaps to final
    function animateCounter(elId, target, duration, onDone) {
        const el = document.getElementById(elId);
        if (!el) { if (onDone) onDone(); return; }
        const fps = 30;
        const interval = 1000 / fps;
        const steps = Math.max(1, Math.round(duration / interval));
        let frame = 0;
        // easeOutQuart — fast at start, slow near end
        const ease = t => 1 - Math.pow(1 - t, 4);
        const timer = setInterval(() => {
            frame++;
            const progress = frame / steps;
            const current = target * ease(progress);
            el.textContent = current.toFixed(2) + 's';
            if (frame >= steps) {
                clearInterval(timer);
                el.textContent = target.toFixed(2) + 's';
                if (onDone) onDone();
            }
        }, interval);
    }

    function markDone(i, target) {
        const el = document.getElementById(`${id}-s${i}`);
        if (!el) return;
        el.classList.remove('active', 'pending', 'done');
        el.classList.add('done');
        const tEl = document.getElementById(`${id}-t${i}`);
        if (tEl) tEl.textContent = target.toFixed(2) + 's';
    }

    function markActive(i) {
        const el = document.getElementById(`${id}-s${i}`);
        if (!el) return;
        el.classList.remove('active', 'pending', 'done');
        el.classList.add('active');
        startTimes[i] = Date.now();
    }

    function advanceToStep(i, target) {
        // animate counter on previous step then mark done
        if (i > 0) {
            const prevTarget = target; // already passed in as the prev step's target
            const tElId = `${id}-t${i - 1}`;
            const prevEl = document.getElementById(`${id}-s${i - 1}`);
            if (prevEl) {
                prevEl.classList.remove('active', 'pending', 'done');
                prevEl.classList.add('done');
            }
            animateCounter(tElId, prevTarget, 260, null);
        }
        markActive(i);
        // update ticker
        const tickerEl = document.getElementById(`${id}-ticker`);
        if (tickerEl) {
            const txt = tickers[Math.min(i, tickers.length - 1)];
            const clone = tickerEl.cloneNode(false);
            clone.textContent = txt;
            tickerEl.parentNode.replaceChild(clone, tickerEl);
            clone.id = `${id}-ticker`;
        }
    }

    // Build per-step random targets & cumulative delays
    const stepTargets = steps.map(() => randTarget());
    const STEP_MS = 380;
    let cumDelay = 0;
    for (let i = 1; i < steps.length; i++) {
        const delay = cumDelay + STEP_MS + stepTargets[i - 1] * 320;
        cumDelay = delay;
        const prevTarget = stepTargets[i - 1];
        setTimeout(() => advanceToStep(i, prevTarget), delay);
    }

    // Last step: live counting timer that keeps incrementing until block is removed
    const lastIdx = steps.length - 1;
    const lastDelay = cumDelay + STEP_MS + stepTargets[lastIdx - 1] * 320;
    setTimeout(() => {
        const tElId = `${id}-t${lastIdx}`;
        const tEl = document.getElementById(tElId);
        if (!tEl) return;
        tEl.style.opacity = '1';
        let val = 0.01 + Math.random() * 0.12;
        const liveTimer = setInterval(() => {
            if (!document.getElementById(id)) { clearInterval(liveTimer); return; }
            val += 0.01 + Math.random() * 0.04;
            if (val > 2.53) val = 0.01 + Math.random() * 0.1;
            tEl.textContent = val.toFixed(2) + 's';
        }, 80);
    }, lastDelay);

    return id;
}

// ═══════════════════════════════════════════════
// CHAT CONTROLS
// ═══════════════════════════════════════════════
function clearChat() {
    document.getElementById('msgsArea').innerHTML = '';
    document.getElementById('msgsArea').style.display = 'none';
    document.getElementById('welcomeScreen').style.display = 'block';
}

function scrollBottom() {
    const c = document.getElementById('chatMsgs');
    requestAnimationFrame(() => { c.scrollTop = c.scrollHeight; });
}

// ═══════════════════════════════════════════════
// HISTORY
// ═══════════════════════════════════════════════
function saveHistory(query, type) {
    searchHistory.unshift({ query, type, ts: Date.now() });
    if (searchHistory.length > 50) searchHistory.pop();
    localStorage.setItem('chembot_history', JSON.stringify(searchHistory));
    renderHistory();
}

function renderHistory() {
    const body = document.getElementById('hpBody');
    if (!searchHistory.length) {
        body.innerHTML = '<div class="hp-empty"><i class="fas fa-clock"></i><p>' + L('ยังไม่มีประวัติ','No history yet') + '</p></div>';
        return;
    }
    const typeIcon = {cas:'hashtag',formula:'atom',sds:'file-shield',location:'map-marker-alt',name:'search',hazard:'exclamation-triangle'};
    body.innerHTML = searchHistory.slice(0, 30).map((h, idx) => {
        const t = new Date(h.ts);
        const timeStr = t.toLocaleTimeString(LANG==='th'?'th':'en',{hour:'2-digit',minute:'2-digit'});
        const icon = typeIcon[h.type] || 'search';
        return `<div class="hp-item" onclick="sendMsg('${escAttr(h.query)}')">
            <div class="hp-item-content">
                <div class="hp-item-q"><i class="fas fa-${icon}" style="color:var(--accent);margin-right:6px"></i>${escHtml(h.query)}</div>
                <div class="hp-item-meta"><span>${timeStr}</span><span>${h.type || 'search'}</span></div>
            </div>
            <button class="hp-item-del" onclick="event.stopPropagation();deleteHistory(${idx})" title="${L('ลบ','Remove')}"><i class="fas fa-times"></i></button>
        </div>`;
    }).join('');
}

function deleteHistory(idx) {
    searchHistory.splice(idx, 1);
    localStorage.setItem('chembot_history', JSON.stringify(searchHistory));
    renderHistory();
}

function clearHistory() {
    if (!searchHistory.length) return;
    if (!confirm(L('ล้างประวัติการค้นหาทั้งหมด?', 'Clear all search history?'))) return;
    searchHistory = [];
    localStorage.setItem('chembot_history', JSON.stringify(searchHistory));
    renderHistory();
}

function toggleHistory() {
    document.getElementById('histPanel').classList.toggle('open');
    document.getElementById('histBtn').classList.toggle('active');
}

// ═══════════════════════════════════════════════
// SIDEBAR / DEMO
// ═══════════════════════════════════════════════
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('mobOverlay').classList.toggle('show');
}
function toggleSidebarCollapse() {
    const sb = document.getElementById('sidebar');
    const main = document.querySelector('.main');
    const btn = document.getElementById('sbToggleBtn');
    const collapsed = sb.classList.toggle('collapsed');
    main.classList.toggle('sb-hidden', collapsed);
    btn.querySelector('i').className = collapsed ? 'fas fa-chevron-right' : 'fas fa-bars';
    try { localStorage.setItem('sbCollapsed', collapsed ? '1' : '0'); } catch(e){}
}
(function(){
    try {
        if (localStorage.getItem('sbCollapsed') === '1') {
            const sb = document.getElementById('sidebar');
            const main = document.querySelector('.main');
            const btn = document.getElementById('sbToggleBtn');
            if (sb) sb.classList.add('collapsed');
            if (main) main.classList.add('sb-hidden');
            if (btn) btn.querySelector('i').className = 'fas fa-chevron-right';
        }
    } catch(e){}
})();

function toggleDD(btn) {
    const dd = btn.nextElementSibling;
    const isOpen = dd.classList.contains('show');
    document.querySelectorAll('.demo-dd.show').forEach(d => d.classList.remove('show'));
    if (!isOpen) dd.classList.add('show');
}

document.addEventListener('click', e => {
    if (!e.target.closest('.demo-role-group'))
        document.querySelectorAll('.demo-dd.show').forEach(d => d.classList.remove('show'));
});

function fillDemo(u) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = '123';
}

// ═══════════════════════════════════════════════
// LOGIN
// ═══════════════════════════════════════════════
document.getElementById('loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const err = document.getElementById('errMsg');
    const et  = document.getElementById('errText');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    err.classList.remove('show');
    try {
        const r = await fetch('/v1/api/auth.php?action=login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
                remember: document.getElementById('remember').checked
            })
        });
        const d = await r.json();
        if (d.success) { window.location.href = '/v1/'; }
        else { throw new Error(d.error || L('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง','Invalid credentials')); }
    } catch (er) {
        et.textContent = er.message;
        err.classList.add('show');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> ' + L('เข้าสู่ระบบ','Sign In');
    }
});

// ═══════════════════════════════════════════════
// RESPONSE INTERACTIONS
// ═══════════════════════════════════════════════
document.addEventListener('click', e => {
    // SDS image fullscreen
    if (e.target.classList.contains('sds-full-img')) {
        e.target.classList.toggle('fs');
    }
    // Location building toggle
    if (e.target.closest('.loc-building-hdr')) {
        const hdr   = e.target.closest('.loc-building-hdr');
        hdr.classList.toggle('open');
        const rooms = hdr.nextElementSibling;
        if (rooms) rooms.style.display = hdr.classList.contains('open') ? 'block' : 'none';
        return;
    }
    // Section toggle
    if (e.target.closest('.section-toggle')) {
        const el = e.target.closest('.section-toggle');
        el.classList.toggle('collapsed');
        const body = el.nextElementSibling;
        if (body && body.classList.contains('section-body')) body.classList.toggle('collapsed');
    }
});

// ═══════════════════════════════════════════════
// LOC-ITEM ACTION HANDLERS
// ═══════════════════════════════════════════════
const _locCache = {};
async function _locFetch(id) {
    if (_locCache[id]) return _locCache[id];
    const r = await fetch('/v1/api/containers.php?action=detail&id=' + id);
    if (r.status === 401) throw new Error('__AUTH__');
    const d = await r.json();
    if (!d.success) throw new Error(d.error || 'ไม่พบข้อมูล');
    _locCache[id] = d.data;
    return d.data;
}
function locGuestPrompt() {
    _locModalOpen('เข้าสู่ระบบเพื่อดูข้อมูล', 'lock', `
    <div class="lm-guest-prompt">
      <div class="lm-guest-icon"><i class="fas fa-lock"></i></div>
      <div class="lm-guest-title">กรุณาเข้าสู่ระบบ</div>
      <div class="lm-guest-desc">เพื่อดู 3D Model รายละเอียดภาชนะ และข้อมูลการจัดเก็บ กรุณาเข้าสู่ระบบก่อน</div>
      <div class="lm-guest-features">
        <div class="lm-guest-feat"><i class="fas fa-cube"></i> ดู 3D Model ภาชนะ</div>
        <div class="lm-guest-feat"><i class="fas fa-info-circle"></i> รายละเอียดปริมาณและตำแหน่ง</div>
        <div class="lm-guest-feat"><i class="fas fa-hand-holding"></i> เบิกใช้สารเคมี</div>
      </div>
    </div>
    <div class="lm-actions">
      <button class="lm-btn lm-btn-borrow" onclick="document.getElementById('loginFormSection')?.scrollIntoView({behavior:'smooth'});locModalClose()"><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</button>
      <button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ปิด</button>
    </div>`, 'lm-accent-blue');
}
function _locHandleError(e) {
    if (e.message === '__AUTH__') { locGuestPrompt(); return; }
    document.getElementById('locModalBody').innerHTML = '<div class="lm-error"><i class="fas fa-exclamation-triangle"></i> ' + escHtml(e.message) + '</div>';
}
function _locModalOpen(title, icon, bodyHtml, accentClass) {
    const ov = document.getElementById('locModal');
    if (!ov) return;
    ov.querySelector('.lm-title').innerHTML = '<i class="fas fa-' + icon + '"></i> ' + title;
    ov.querySelector('.lm-panel').className = 'lm-panel ' + (accentClass || '');
    document.getElementById('locModalBody').innerHTML = bodyHtml;
    ov.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function locModalClose() {
    const ov = document.getElementById('locModal');
    if (ov) { ov.classList.remove('open'); document.body.style.overflow = ''; }
}
function _hazardBadge(code) {
    const map = {'GHS01':'💥','GHS02':'🔥','GHS03':'🔥','GHS04':'⚗️','GHS05':'⚠️','GHS06':'☠️','GHS07':'❗','GHS08':'⚕️','GHS09':'🌿'};
    const k = Object.keys(map).find(k => (code||'').includes(k));
    return '<span class="lm-ghsbadge" title="' + escHtml(code) + '">' + (map[k]||'⚠️') + ' ' + escHtml(code) + '</span>';
}

async function locViewDetail(containerId) {
    _locModalOpen('กำลังโหลด…', 'circle-notch fa-spin', '<div class="lm-loading">กำลังโหลดข้อมูล…</div>', '');
    try {
        const c = await _locFetch(containerId);
        const pct = Math.round(parseFloat(c.remaining_percentage || 0));
        const pctColor = pct > 50 ? '#10b981' : pct > 20 ? '#f59e0b' : '#ef4444';
        const exp = c.expiry_date;
        let expBadge = '<span class="lm-badge lm-nodate">ไม่ระบุวันหมดอายุ</span>';
        if (exp) {
            const days = Math.round((new Date(exp) - new Date()) / 86400000);
            expBadge = days < 0 ? '<span class="lm-badge lm-danger">หมดอายุ ' + exp + ' (' + Math.abs(days) + ' วันที่แล้ว)</span>'
                : days < 90 ? '<span class="lm-badge lm-warn">หมดอายุ ' + exp + ' (อีก ' + days + ' วัน)</span>'
                : '<span class="lm-badge lm-fresh">หมดอายุ ' + exp + '</span>';
        }
        const hazards = (c.hazard_pictograms || []).map(_hazardBadge).join('');
        const html = `<div class="lm-chem-name">${escHtml(c.chemical_name||'-')}</div>
        <div class="lm-pills">
          ${c.cas_number?'<span class="lm-pill cas">CAS: '+escHtml(c.cas_number)+'</span>':''}
          ${c.molecular_formula?'<span class="lm-pill fml">'+escHtml(c.molecular_formula)+'</span>':''}
          ${c.grade?'<span class="lm-pill grade">'+escHtml(c.grade)+'</span>':''}
          ${c.container_type?'<span class="lm-pill type">'+escHtml(c.container_type)+'</span>':''}
        </div>
        <div class="lm-section"><div class="lm-section-hdr"><i class="fas fa-flask"></i> ปริมาณ</div>
          <div class="lm-qty-bar"><div class="lm-qty-fill" style="width:${pct}%;background:${pctColor}"></div></div>
          <div class="lm-qty-row"><span class="lm-qty-big">${escHtml(String(c.current_quantity))} <small>${escHtml(c.quantity_unit||'')}</small></span>
          <span class="lm-qty-sub">จาก ${escHtml(String(c.initial_quantity||'-'))} · เหลือ ${pct}%</span></div>
        </div>
        <div class="lm-section"><div class="lm-section-hdr"><i class="fas fa-calendar-alt"></i> วันหมดอายุ</div>${expBadge}</div>
        <div class="lm-section"><div class="lm-section-hdr"><i class="fas fa-map-marker-alt"></i> ตำแหน่งจัดเก็บ</div>
          <div class="lm-loc-text">${escHtml(c.location_text||c.building_name||'-')}</div></div>
        ${c.owner_name?'<div class="lm-section"><div class="lm-section-hdr"><i class="fas fa-user"></i> ผู้รับผิดชอบ</div><div class="lm-owner-row">'+escHtml(c.owner_name)+(c.lab_name?' · '+escHtml(c.lab_name):'')+'</div></div>':''}
        ${hazards?'<div class="lm-section"><div class="lm-section-hdr"><i class="fas fa-radiation"></i> GHS Hazards</div><div class="lm-ghs-row">'+hazards+'</div></div>':''}
        <div class="lm-actions">
          ${(c.ar_data&&c.ar_data.model_url)?'<button class="lm-btn lm-btn-3d" onclick="locView3D('+containerId+')"><i class="fas fa-cube"></i> ดู 3D Model</button>':''}
          <button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ปิด</button>
        </div>`;
        _locModalOpen('รายละเอียดภาชนะ', 'info-circle', html, 'lm-accent-blue');
    } catch(e) { _locHandleError(e); }
}

async function locBorrow(containerId) {
    _locModalOpen('กำลังโหลด…', 'circle-notch fa-spin', '<div class="lm-loading">กำลังโหลดข้อมูล…</div>', '');
    try {
        const c = await _locFetch(containerId);
        const maxQty = parseFloat(c.current_quantity || 0);
        const unit = escHtml(c.quantity_unit || '');
        const html = `<div class="lm-chem-name">${escHtml(c.chemical_name||'-')}</div>
        <div class="lm-pills"><span class="lm-pill avail">คงเหลือ: ${escHtml(String(maxQty))} ${unit}</span></div>
        <form id="borrowForm" class="lm-form">
          <div class="lm-field"><label>ปริมาณที่ต้องการเบิก <span class="lm-unit">${unit}</span></label>
            <div class="lm-input-row"><input type="number" id="bqty" min="0.001" max="${maxQty}" step="0.001" placeholder="0.000" required class="lm-input">
            <button type="button" class="lm-mini-btn" onclick="document.getElementById('bqty').value=${maxQty}">ทั้งหมด</button></div>
            <div class="lm-qty-bar lm-qty-preview"><div id="bqtyBar" class="lm-qty-fill" style="width:0%;background:#10b981"></div></div></div>
          <div class="lm-field"><label>วัตถุประสงค์</label><input type="text" id="bpurpose" placeholder="เช่น วิจัย LAB301" class="lm-input"></div>
          <div class="lm-field"><label>วันที่คาดว่าจะคืน</label><input type="date" id="bretdate" class="lm-input" min="${new Date().toISOString().slice(0,10)}"></div>
          <div id="borrowMsg" class="lm-msg" style="display:none"></div>
          <div class="lm-actions"><button type="submit" class="lm-btn lm-btn-borrow"><i class="fas fa-hand-holding"></i> ยืนยันเบิกใช้สาร</button>
          <button type="button" class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ยกเลิก</button></div>
        </form>`;
        _locModalOpen('เบิกใช้สาร', 'hand-holding', html, 'lm-accent-green');
        document.getElementById('bqty').addEventListener('input', function() {
            const v = parseFloat(this.value)||0, pct=Math.min(100,Math.round(v/maxQty*100));
            const bar = document.getElementById('bqtyBar');
            bar.style.width=pct+'%'; bar.style.background=pct>80?'#ef4444':pct>50?'#f59e0b':'#10b981';
        });
        document.getElementById('borrowForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const qty=parseFloat(document.getElementById('bqty').value), msg=document.getElementById('borrowMsg'), btn=this.querySelector('[type=submit]');
            if (!qty||qty<=0){msg.style.display='flex';msg.className='lm-msg lm-msg-err';msg.textContent='กรุณาระบุปริมาณ';return;}
            if (qty>maxQty){msg.style.display='flex';msg.className='lm-msg lm-msg-err';msg.textContent='ปริมาณเกินที่มีอยู่';return;}
            btn.disabled=true;btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> กำลังดำเนินการ…';
            try {
                const r=await fetch('/v1/api/borrow.php?action=borrow',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({source_type:'container',source_id:containerId,quantity:qty,purpose:document.getElementById('bpurpose').value,expected_return_date:document.getElementById('bretdate').value||null})});
                const d=await r.json();
                if(d.success){delete _locCache[containerId];const txn=d.data;
                    document.getElementById('locModalBody').innerHTML=`<div class="lm-success"><div class="lm-success-icon">✅</div><div class="lm-success-title">เบิกใช้สารสำเร็จ</div><div class="lm-success-detail">เลขที่: <strong>${escHtml(txn.txn_number||'-')}</strong></div><div class="lm-success-detail">ปริมาณ: <strong>${qty} ${unit}</strong></div><div class="lm-success-detail">สถานะ: <strong>${txn.status==='pending'?'⏳ รอการอนุมัติ':'✅ สำเร็จ'}</strong></div>${txn.status==='pending'?'<div class="lm-info-note"><i class="fas fa-info-circle"></i> รายการนี้ต้องรอการอนุมัติจากเจ้าของสาร</div>':''}</div><div class="lm-actions"><button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-check"></i> ปิด</button></div>`;
                } else {msg.style.display='flex';msg.className='lm-msg lm-msg-err';msg.textContent=d.error||'เกิดข้อผิดพลาด';btn.disabled=false;btn.innerHTML='<i class="fas fa-hand-holding"></i> ยืนยันเบิกใช้สาร';}
            } catch(err){msg.style.display='flex';msg.className='lm-msg lm-msg-err';msg.textContent='Network error';btn.disabled=false;btn.innerHTML='<i class="fas fa-hand-holding"></i> ยืนยันเบิกใช้สาร';}
        });
    } catch(e) { _locHandleError(e); }
}

async function locEditItem(containerId) {
    _locModalOpen('กำลังโหลด…', 'circle-notch fa-spin', '<div class="lm-loading">กำลังโหลดข้อมูล…</div>', '');
    try {
        const c = await _locFetch(containerId);
        const html = `<div class="lm-chem-name">${escHtml(c.chemical_name||'-')}</div>
        <form id="editForm" class="lm-form">
          <div class="lm-field"><label>ปริมาณปัจจุบัน <span class="lm-unit">${escHtml(c.quantity_unit||'')}</span></label>
            <input type="number" id="eqty" value="${escHtml(String(c.current_quantity||0))}" min="0" step="0.001" class="lm-input"></div>
          <div class="lm-field"><label>วันหมดอายุ</label><input type="date" id="eexp" value="${escHtml(c.expiry_date||'')}" class="lm-input"></div>
          <div class="lm-field"><label>สถานะคุณภาพ</label>
            <select id="eqstat" class="lm-input lm-select">
              <option value="good" ${c.quality_status==='good'?'selected':''}>✅ ดี</option>
              <option value="degraded" ${c.quality_status==='degraded'?'selected':''}>⚠️ เสื่อมสภาพ</option>
              <option value="contaminated" ${c.quality_status==='contaminated'?'selected':''}>☣️ ปนเปื้อน</option>
              <option value="expired" ${c.quality_status==='expired'?'selected':''}>❌ หมดอายุ</option>
            </select></div>
          <div class="lm-field"><label>หมายเหตุ</label><textarea id="enotes" rows="2" class="lm-input lm-textarea" placeholder="หมายเหตุ…">${escHtml(c.notes||'')}</textarea></div>
          <div id="editMsg" class="lm-msg" style="display:none"></div>
          <div class="lm-actions"><button type="submit" class="lm-btn lm-btn-edit"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
          <button type="button" class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ยกเลิก</button></div>
        </form>`;
        _locModalOpen('แก้ไขข้อมูลภาชนะ', 'pen', html, 'lm-accent-blue');
        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msg=document.getElementById('editMsg'),btn=this.querySelector('[type=submit]');
            btn.disabled=true;btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> กำลังบันทึก…';
            try {
                const r=await fetch('/v1/api/containers.php?id='+containerId,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({current_quantity:parseFloat(document.getElementById('eqty').value),expiry_date:document.getElementById('eexp').value||null,quality_status:document.getElementById('eqstat').value,notes:document.getElementById('enotes').value})});
                const d=await r.json();
                if(d.success){delete _locCache[containerId];
                    document.getElementById('locModalBody').innerHTML='<div class="lm-success"><div class="lm-success-icon">✅</div><div class="lm-success-title">บันทึกสำเร็จ</div></div><div class="lm-actions"><button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-check"></i> ปิด</button></div>';
                } else {msg.style.display='flex';msg.className='lm-msg lm-msg-err';msg.textContent=d.error||'เกิดข้อผิดพลาด';btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> บันทึกการแก้ไข';}
            } catch(err){msg.style.display='flex';msg.className='lm-msg lm-msg-err';msg.textContent='Network error';btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> บันทึกการแก้ไข';}
        });
    } catch(e) { _locHandleError(e); }
}

async function locView3D(containerId) {
    _locModalOpen('กำลังโหลด…', 'circle-notch fa-spin', '<div class="lm-loading">กำลังโหลดข้อมูล…</div>', '');
    try {
        const c = await _locFetch(containerId);
        const ar = c.ar_data || {}, modelUrl = ar.model_url;
        if (!modelUrl) {
            document.getElementById('locModalBody').innerHTML=`<div class="lm-chem-name">${escHtml(c.chemical_name||'-')}</div><div class="lm-error" style="margin-top:16px"><i class="fas fa-cube"></i> ไม่พบ 3D Model สำหรับภาชนะนี้<br><small>${escHtml(c.container_type||'')} · ${escHtml(c.container_material||'')}</small></div><div class="lm-actions"><button class="lm-btn lm-btn-close" onclick="locModalClose()">ปิด</button></div>`;
            return;
        }
        const html=`<div class="lm-chem-name">${escHtml(c.chemical_name||'-')}<span class="lm-3d-badge">3D</span></div>
        <div class="lm-3d-wrap"><model-viewer src="${escHtml(modelUrl)}" auto-rotate camera-controls shadow-intensity="1.2" camera-orbit="45deg 75deg 105%" environment-image="neutral" ar ar-modes="webxr scene-viewer quick-look" style="width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#8b5cf6;--progress-bar-height:3px"><div class="lm-3d-loading" slot="progress-bar"><i class="fas fa-circle-notch fa-spin"></i> โหลด 3D…</div></model-viewer></div>
        <div class="lm-3d-hint"><i class="fas fa-mouse-pointer"></i> ลากเพื่อหมุน · เลื่อนล้อเพื่อซูม</div>
        <div class="lm-actions"><button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ปิด</button></div>`;
        _locModalOpen('3D Model: '+(c.chemical_name||''), 'cube', html, 'lm-accent-purple');
    } catch(e) { _locHandleError(e); }
}

function locSetExpiry(containerId, btn) {
    const input = document.createElement('input');
    input.type = 'date'; input.min = new Date().toISOString().slice(0,10);
    input.style.cssText = 'position:absolute;opacity:0;pointer-events:none;width:0;height:0';
    document.body.appendChild(input);
    input.addEventListener('change', async function() {
        const val = input.value; document.body.removeChild(input);
        if (!val) return;
        const origHtml = btn.innerHTML; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
        try {
            const r = await fetch('/v1/api/containers.php?id=' + containerId, {method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({expiry_date:val})});
            const d = await r.json();
            if (d.success) {
                delete _locCache[containerId];
                btn.classList.remove('nodate','loc-set-exp-btn');
                const days = Math.round((new Date(val)-new Date())/86400000);
                btn.classList.add(days<0?'danger':days<90?'warn':'fresh');
                btn.innerHTML=val+(days<0?' ⚠':days<90?' ⚡':''); btn.onclick=null;
            } else { btn.innerHTML=origHtml; alert('Error: '+(d.error||'Failed')); }
        } catch(e) { btn.innerHTML=origHtml; alert('Network error'); }
    });
    input.click();
}

// ═══════════════════════════════════════════════
// UTILITIES
// ═══════════════════════════════════════════════
function escHtml(t) {
    const d = document.createElement('div');
    d.textContent = String(t ?? '');
    return d.innerHTML;
}
function escAttr(t) {
    return String(t ?? '').replace(/'/g,"&#39;").replace(/"/g,"&quot;");
}
</script>
<!-- ═══ STORAGE BROWSER MODAL ═══ -->
<div class="sbm-overlay" id="sbmOverlay" onclick="sbmBgClick(event)">
  <div class="sbm" id="sbm">
    <div class="sbm-hdr">
      <div class="sbm-hdr-icon"><i class="fas fa-warehouse"></i></div>
      <div class="sbm-hdr-title">
        <h2><?php echo $lang==='th'?'สถานที่จัดเก็บสารเคมี':'Chemical Storage Browser'; ?></h2>
        <p id="sbmSubtitle"><?php echo $lang==='th'?'เลือกอาคารเพื่อดูรายละเอียด':'Select a building to explore'; ?></p>
      </div>
      <button class="sbm-close" onclick="closeStorageBrowser()"><i class="fas fa-times"></i></button>
    </div>
    <div class="sbm-breadcrumb" id="sbmBreadcrumb">
      <div class="sbm-crumb active"><span><?php echo $lang==='th'?'อาคารทั้งหมด':'All Buildings'; ?></span></div>
    </div>
    <div class="sbm-body" id="sbmBody">
      <div class="sbm-loading"><div class="sbm-spinner"></div><?php echo $lang==='th'?'กำลังโหลด...':'Loading...'; ?></div>
    </div>
  </div>
</div>

<!-- ═══ GLB 3D FULLSCREEN OVERLAY ═══ -->
<div class="glb-ov" id="glbOv">
  <div class="glb-ov-hdr">
    <i class="fas fa-cube" style="color:#a78bfa;font-size:16px"></i>
    <span class="glb-ov-title" id="glbOvTitle">3D Model</span>
    <span class="glb-ov-badge">GLB · Interactive</span>
    <div class="glb-ov-controls">
      <button class="glb-ov-ctrl-btn" id="glbOvAutoRotateBtn" onclick="glbOvToggleAutoRotate()" title="Toggle auto-rotate"><i class="fas fa-sync-alt"></i></button>
      <button class="glb-ov-ctrl-btn" onclick="glbOvResetCamera()" title="Reset camera"><i class="fas fa-compress-arrows-alt"></i></button>
      <a class="glb-ov-ctrl-btn" id="glbOvDlBtn" href="#" download title="Download GLB"><i class="fas fa-download"></i></a>
    </div>
    <button class="glb-ov-close" onclick="closeGLBOverlay()" title="Close"><i class="fas fa-times"></i></button>
  </div>
  <div class="glb-ov-body" id="glbOvBody">
    <!-- model-viewer injected by JS -->
    <div class="glb-ov-hint"><?php echo $lang==='th'?'ลากเมาส์เพื่อหมุน • เลื่อนเพื่อ หมุนรอบ':'Drag to rotate • Scroll to zoom • Right-click to pan'; ?></div>
  </div>
</div>

<!-- ═══ LOC-ITEM MODAL ═══ -->
<div id="locModal" class="loc-modal-overlay" onclick="if(event.target===this)locModalClose()">
  <div class="lm-panel">
    <div class="lm-hdr">
      <span class="lm-title"></span>
      <button class="lm-close-btn" onclick="locModalClose()"><i class="fas fa-times"></i></button>
    </div>
    <div class="lm-body" id="locModalBody"></div>
  </div>
</div>

<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js"></script>
<script>
// ═══════════════════════════════════════════════
// 3D MODEL VIEWER
// ═══════════════════════════════════════════════
function attachModelViewers(root) {
    const slots = (root || document).querySelectorAll('.glb-mv-slot:not([data-attached])');
    slots.forEach(slot => {
        slot.setAttribute('data-attached', '1');
        const src   = slot.dataset.src;
        const usdz  = slot.dataset.usdz || '';
        if (!src) return;
        const mv = document.createElement('model-viewer');
        mv.setAttribute('src', src);
        if (usdz) mv.setAttribute('ios-src', usdz);
        mv.setAttribute('auto-rotate', '');
        mv.setAttribute('camera-controls', '');
        mv.setAttribute('camera-orbit', '45deg 75deg auto');
        mv.setAttribute('shadow-intensity', '1');
        mv.setAttribute('environment-image', 'neutral');
        mv.setAttribute('interaction-prompt', 'auto');
        mv.setAttribute('ar', '');
        mv.setAttribute('ar-modes', 'webxr scene-viewer quick-look');
        mv.style.cssText = 'width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#6C5CE7;--progress-bar-height:3px';
        // Remove loading placeholder when loaded
        mv.addEventListener('load', () => {
            const loading = slot.querySelector('.glb-mv-loading');
            if (loading) loading.style.display = 'none';
        });
        slot.appendChild(mv);
    });
}

// Observe DOM for new .glb-mv-slot elements (appended after chat responses)
const _glbObserver = new MutationObserver(muts => {
    muts.forEach(m => m.addedNodes.forEach(n => {
        if (n.nodeType !== 1) return;
        if (n.classList && n.classList.contains('glb-mv-slot')) attachModelViewers(n.parentElement);
        else if (n.querySelector && n.querySelector('.glb-mv-slot')) attachModelViewers(n);
    }));
});
document.addEventListener('DOMContentLoaded', () => {
    _glbObserver.observe(document.getElementById('msgsArea') || document.body, { childList: true, subtree: true });
    attachModelViewers();
});

// ── GLB Fullscreen Overlay ──────────────────────────────────
let _glbOvAutoRotate = true;

function openGLBOverlay(src, label) {
    const ov    = document.getElementById('glbOv');
    const body  = document.getElementById('glbOvBody');
    const title = document.getElementById('glbOvTitle');
    const dlBtn = document.getElementById('glbOvDlBtn');
    if (!ov) return;
    // Clear previous
    const old = body.querySelector('model-viewer');
    if (old) old.remove();
    // Build model-viewer
    const mv = document.createElement('model-viewer');
    mv.setAttribute('src', src);
    mv.setAttribute('auto-rotate', '');
    mv.setAttribute('camera-controls', '');
    mv.setAttribute('camera-orbit', '0deg 75deg 105%');
    mv.setAttribute('shadow-intensity', '1.2');
    mv.setAttribute('environment-image', 'neutral');
    mv.setAttribute('interaction-prompt', 'auto');
    mv.setAttribute('ar', '');
    mv.setAttribute('ar-modes', 'webxr scene-viewer quick-look');
    mv.style.cssText = 'width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#6C5CE7;--progress-bar-height:4px;display:block';
    body.insertBefore(mv, body.querySelector('.glb-ov-hint'));
    title.textContent = label || '3D Model';
    dlBtn.href = src;
    dlBtn.download = (label || 'model') + '.glb';
    _glbOvAutoRotate = true;
    ov.classList.add('show');
    document.body.style.overflow = 'hidden';
    // Close on Escape
    document.addEventListener('keydown', _glbOvKeyClose);
}

function closeGLBOverlay() {
    const ov   = document.getElementById('glbOv');
    const body = document.getElementById('glbOvBody');
    if (!ov) return;
    ov.classList.remove('show');
    document.body.style.overflow = '';
    document.removeEventListener('keydown', _glbOvKeyClose);
    const mv = body.querySelector('model-viewer');
    if (mv) setTimeout(() => mv.remove(), 300);
}

function _glbOvKeyClose(e) {
    if (e.key === 'Escape') closeGLBOverlay();
}

function glbOvToggleAutoRotate() {
    const mv  = document.querySelector('#glbOvBody model-viewer');
    const btn = document.getElementById('glbOvAutoRotateBtn');
    if (!mv) return;
    _glbOvAutoRotate = !_glbOvAutoRotate;
    if (_glbOvAutoRotate) { mv.setAttribute('auto-rotate',''); btn.style.color='#a78bfa'; }
    else { mv.removeAttribute('auto-rotate'); btn.style.color='rgba(255,255,255,.35)'; }
}

function glbOvResetCamera() {
    const mv = document.querySelector('#glbOvBody model-viewer');
    if (mv) mv.cameraOrbit = '0deg 75deg 105%';
}

// ═══════════════════════════════════════════════
// STORAGE BROWSER
// ═══════════════════════════════════════════════
const SBM_LANG = '<?php echo $lang; ?>';
const T = (th, en) => SBM_LANG === 'th' ? th : en;

let sbmState = { level: 'buildings', buildingId: null, buildingCode: null, buildingName: null, roomId: null, roomCode: null, allContainers: [] };

function openStorageBrowser() {
    document.getElementById('sbmOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
    sbmState = { level: 'buildings', buildingId: null, buildingCode: null, buildingName: null, roomId: null, roomCode: null, allContainers: [] };
    sbmLoadBuildings();
}
function closeStorageBrowser() {
    document.getElementById('sbmOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
function sbmBgClick(e) {
    if (e.target === document.getElementById('sbmOverlay')) closeStorageBrowser();
}

// ─── Buildings ───────────────────────────────────────────────
function sbmLoadBuildings() {
    sbmSetBreadcrumb([{label: T('อาคารทั้งหมด','All Buildings')}]);
    document.getElementById('sbmSubtitle').textContent = T('เลือกอาคารเพื่อดูรายละเอียด','Select a building to explore');
    sbmShowLoading();
    fetch('/v1/api/ai_assistant.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'get_buildings', lang: SBM_LANG})
    }).then(r=>r.json()).then(d=>{
        if (!d.success || !d.data?.length) { sbmShowEmpty(T('ไม่พบข้อมูลอาคาร','No buildings found')); return; }
        sbmRenderBuildings(d.data);
    }).catch(()=>sbmShowEmpty(T('เกิดข้อผิดพลาด','Error loading data')));
}

function sbmRenderBuildings(buildings) {
    const colorsMap = { 'F0':'#6b7280', 'F1':'#3b82f6','F4':'#8b5cf6','F5':'#f59e0b','F6':'#06b6d4','F7':'#10b981','F10':'#ec4899','F11':'#f97316','F12':'#dc2626' };
    let html = '<div class="sbm-bld-grid">';
    buildings.forEach(b => {
        const col = colorsMap[b.code] || '#10b981';
        html += `<div class="sbm-bld-card" onclick="sbmLoadRooms(${b.id},'${escAttr(b.code)}','${escAttr(b.name)}')"
            style="--bld-col:${col}">
            <div class="sbm-bld-code" style="color:${col}">${escHtml(b.code)}</div>
            <div class="sbm-bld-name">${escHtml(b.name)}</div>
            <div class="sbm-bld-meta">
                <span class="sbm-bld-pill"><i class="fas fa-vial" style="font-size:9px"></i>${Number(b.bottle_count).toLocaleString()} ${T('ขวด','bottles')}</span>
                <span class="sbm-bld-pill rooms"><i class="fas fa-door-open" style="font-size:9px"></i>${b.room_count} ${T('ห้อง','rooms')}</span>
            </div>
            <i class="fas fa-chevron-right sbm-bld-arr"></i>
        </div>`;
    });
    html += '</div>';
    document.getElementById('sbmBody').innerHTML = html;
}

// ─── Rooms ───────────────────────────────────────────────────
function sbmLoadRooms(buildingId, code, name) {
    sbmState.buildingId = buildingId;
    sbmState.buildingCode = code;
    sbmState.buildingName = name;
    sbmSetBreadcrumb([
        {label: T('อาคารทั้งหมด','All Buildings'), fn: 'sbmLoadBuildings()'},
        {label: code + ' — ' + name}
    ]);
    document.getElementById('sbmSubtitle').textContent = T('ห้องใน ','Rooms in ') + code;
    sbmShowLoading();
    fetch('/v1/api/ai_assistant.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'get_rooms', building_id: buildingId, lang: SBM_LANG})
    }).then(r=>r.json()).then(d=>{
        if (!d.success || !d.data?.length) { sbmShowEmpty(T('ไม่พบห้องที่มีสารเคมี','No rooms with chemicals')); return; }
        sbmRenderRooms(d.data);
    }).catch(()=>sbmShowEmpty(T('เกิดข้อผิดพลาด','Error loading data')));
}

function sbmRenderRooms(rooms) {
    let html = '<div class="sbm-room-list">';
    rooms.forEach(r => {
        const nm = r.name ? escHtml(r.name) : '';
        const floor = r.floor != null ? `<span style="font-size:10px;color:var(--c3);margin-left:6px">${T('ชั้น','Floor ')}${r.floor}</span>` : '';
        html += `<div class="sbm-room-row" onclick="sbmLoadContainers(${r.id},'${escAttr(r.room_number)}')">
            <div class="sbm-room-icon"><i class="fas fa-door-open"></i></div>
            <div class="sbm-room-info">
                <div class="sbm-room-code">${escHtml(r.room_number)}${floor}</div>
                ${nm ? `<div class="sbm-room-name">${nm}</div>` : ''}
            </div>
            <span class="sbm-room-count">${Number(r.bottle_count).toLocaleString()} ${T('ขวด','')}</span>
            <i class="fas fa-chevron-right sbm-room-arr"></i>
        </div>`;
    });
    html += '</div>';
    document.getElementById('sbmBody').innerHTML = html;
}

// ─── Containers ──────────────────────────────────────────────
function sbmLoadContainers(roomId, roomCode) {
    sbmState.roomId = roomId;
    sbmState.roomCode = roomCode;
    sbmSetBreadcrumb([
        {label: T('อาคารทั้งหมด','All Buildings'), fn: 'sbmLoadBuildings()'},
        {label: sbmState.buildingCode, fn: `sbmLoadRooms(${sbmState.buildingId},'${escAttr(sbmState.buildingCode)}','${escAttr(sbmState.buildingName)}')`},
        {label: roomCode}
    ]);
    document.getElementById('sbmSubtitle').textContent = T('รายการสารเคมีใน ','Chemicals in ') + roomCode;
    sbmShowLoading();
    fetch('/v1/api/ai_assistant.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'get_room_containers', room_id: roomId, lang: SBM_LANG})
    }).then(r=>r.json()).then(d=>{
        if (!d.success || !d.data?.length) { sbmShowEmpty(T('ไม่พบสารเคมีในห้องนี้','No chemicals in this room')); return; }
        sbmState.allContainers = d.data;
        sbmRenderContainers(d.data);
    }).catch(()=>sbmShowEmpty(T('เกิดข้อผิดพลาด','Error loading data')));
}

function sbmRenderContainers(rows) {
    const today = new Date();
    let html = `<div class="sbm-ctr-hdr">
        <div class="sbm-ctr-title">
            <i class="fas fa-vial" style="color:var(--ok)"></i>
            <span>${T('รายการขวดสาร','Container List')}</span>
            <span class="sbm-ctr-badge">${rows.length}${rows.length >= 200 ? '+' : ''}</span>
        </div>
        <input class="sbm-ctr-search" type="text" placeholder="${T('ค้นหาชื่อสาร / CAS / barcode...','Search name / CAS / barcode...')}" oninput="sbmFilterContainers(this.value)">
    </div>
    <div id="sbmTblWrap">`;
    html += sbmBuildTable(rows);
    html += '</div>';
    document.getElementById('sbmBody').innerHTML = html;
}

function sbmBuildTable(rows) {
    const today = new Date();
    let t = `<table class="sbm-tbl">
        <thead><tr>
            <th>#</th>
            <th>${T('ชื่อสารเคมี','Chemical')}</th>
            <th>${T('Barcode','Barcode')}</th>
            <th>${T('ปริมาณคงเหลือ','Qty')}</th>
            <th>${T('ประเภท','Type')}</th>
            <th>${T('เกรด','Grade')}</th>
            <th>${T('รับเข้า','Received')}</th>
            <th>${T('ผู้ดูแล / ติดต่อ','Custodian / Contact')}</th>
        </tr></thead><tbody>`;
    rows.forEach((r, i) => {
        const expCls = r.expiry_date ? (() => { const d = new Date(r.expiry_date); const diff = (d-today)/86400000; return diff < 0 ? 'expiry-warn' : diff < 90 ? 'expiry-warn' : 'expiry-ok'; })() : '';
        const recvd = r.received_date ? r.received_date.substring(0,4) : '—';
        const typeLabel = {'bottle':T('ขวด','Bottle'),'flask':T('ฟลาสก์','Flask'),'canister':T('กระป๋อง','Canister'),'cylinder':T('ถัง','Cylinder')}[r.container_type] || r.container_type || '—';
        const ownerName  = r.owner_name  ? escHtml(r.owner_name.trim())  : '';
        const ownerPhone = r.owner_phone ? escHtml(r.owner_phone.trim()) : '';
        const ownerCell  = ownerName || ownerPhone
            ? `<div style="font-size:11px;line-height:1.6">`
                + (ownerName  ? `<div style="font-weight:700;color:#1e293b"><i class="fas fa-user" style="color:#94a3b8;margin-right:3px"></i>${ownerName}</div>` : '')
                + (ownerPhone ? `<div style="color:#0369a1"><a href="tel:${ownerPhone}" style="color:inherit;text-decoration:none"><i class="fas fa-phone" style="color:#94a3b8;margin-right:3px"></i>${ownerPhone}</a></div>` : '')
                + `</div>`
            : '<span style="color:#94a3b8">—</span>';
        t += `<tr>
            <td style="color:var(--c3);width:28px">${i+1}</td>
            <td><div class="chem-nm">${escHtml(r.chem_name)}</div><div class="cas-nm">${escHtml(r.cas_number||'')}</div></td>
            <td><div class="qr-code">${escHtml(r.qr_code||'')}</div></td>
            <td><span class="qty-val">${r.current_quantity != null ? Number(r.current_quantity).toLocaleString() : '—'} ${escHtml(r.quantity_unit||'')}</span></td>
            <td><span class="type-pill">${typeLabel}</span></td>
            <td>${escHtml(r.grade||'—')}</td>
            <td class="${expCls}">${recvd}</td>
            <td>${ownerCell}</td>
        </tr>`;
    });
    t += '</tbody></table>';
    return t;
}

function sbmFilterContainers(q) {
    const ql = q.toLowerCase();
    const filtered = ql ? sbmState.allContainers.filter(r =>
        (r.chem_name||'').toLowerCase().includes(ql) ||
        (r.cas_number||'').toLowerCase().includes(ql) ||
        (r.qr_code||'').toLowerCase().includes(ql)
    ) : sbmState.allContainers;
    document.getElementById('sbmTblWrap').innerHTML = filtered.length ? sbmBuildTable(filtered) : `<div class="sbm-empty"><i class="fas fa-search"></i>${T('ไม่พบผลลัพธ์','No results found')}</div>`;
}

// ─── Helpers ─────────────────────────────────────────────────
function sbmShowLoading() {
    document.getElementById('sbmBody').innerHTML = `<div class="sbm-loading"><div class="sbm-spinner"></div>${T('กำลังโหลด...','Loading...')}</div>`;
}
function sbmShowEmpty(msg) {
    document.getElementById('sbmBody').innerHTML = `<div class="sbm-empty"><i class="fas fa-box-open"></i><p>${msg}</p></div>`;
}
function sbmSetBreadcrumb(crumbs) {
    const el = document.getElementById('sbmBreadcrumb');
    let html = '';
    crumbs.forEach((c, i) => {
        const isLast = i === crumbs.length - 1;
        if (i > 0) html += '<span class="sbm-crumb-sep"><i class="fas fa-chevron-right" style="font-size:9px"></i></span>';
        html += `<div class="sbm-crumb${isLast?' active':''}">` + (c.fn ? `<a onclick="${c.fn}">${escHtml(c.label)}</a>` : `<span>${escHtml(c.label)}</span>`) + '</div>';
    });
    el.innerHTML = html;
}
function escAttr(s) { return (s||'').replace(/'/g,"\\'")
    .replace(/"/g,'&quot;'); }
// Keyboard: Escape closes
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeStorageBrowser(); locModalClose(); closeForgotModal(); } });
</script>

<!-- ── Forgot Password Modal ── -->
<div class="fp-ov" id="fpOv" onclick="if(event.target===this)closeForgotModal()">
  <div class="fp-box">
    <div class="fp-hdr">
      <div class="fp-hdr-ic"><i class="fas fa-key"></i></div>
      <div>
        <h4><?= $lang==='th'?'ลืมรหัสผ่าน':'Forgot Password' ?></h4>
        <p><?= $lang==='th'?'กรอกชื่อผู้ใช้หรืออีเมล เพื่อรับลิงก์ reset':'Enter your username or email to get a reset link' ?></p>
      </div>
      <button class="fp-close" onclick="closeForgotModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="fp-body">
      <!-- Step 1: input -->
      <div class="fp-step active" id="fpStep1">
        <input class="fp-inp" type="text" id="fpIdentifier"
          placeholder="<?= $lang==='th'?'ชื่อผู้ใช้หรืออีเมล':'Username or email' ?>"
          autocomplete="off">
        <div class="fp-err" id="fpErr"><i class="fas fa-exclamation-circle"></i><span id="fpErrTxt"></span></div>
        <button class="fp-btn" id="fpSubmitBtn" onclick="fpSubmit()">
          <i class="fas fa-paper-plane"></i>
          <?= $lang==='th'?'สร้างลิงก์ Reset':'Generate Reset Link' ?>
        </button>
      </div>
      <!-- Step 2: show link -->
      <div class="fp-step" id="fpStep2">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <div style="width:32px;height:32px;border-radius:50%;background:rgba(16,185,129,.15);color:#10b981;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0"><i class="fas fa-check"></i></div>
          <div style="font-size:12px;color:rgba(255,255,255,.7)"><?= $lang==='th'?'สร้างลิงก์สำหรับ <strong id="fpName" style="color:#fff"></strong> แล้ว':'Reset link created for <strong id="fpName" style="color:#fff"></strong>' ?></div>
        </div>
        <div class="fp-link-box" id="fpLinkBox"></div>
        <button class="fp-copy-btn" id="fpCopyBtn" onclick="fpCopy()">
          <i class="fas fa-copy"></i>
          <?= $lang==='th'?'คัดลอกลิงก์':'Copy Link' ?>
        </button>
        <button class="fp-open-btn" id="fpOpenBtn" onclick="fpOpen()">
          <i class="fas fa-external-link-alt"></i>
          <?= $lang==='th'?'เปิดลิงก์เพื่อตั้งรหัสผ่านใหม่':'Open Reset Link' ?>
        </button>
        <p class="fp-note"><?= $lang==='th'?'ลิงก์นี้จะหมดอายุใน 1 ชั่วโมง':'This link expires in 1 hour' ?></p>
        <button class="fp-btn" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);margin-top:8px" onclick="fpReset()">
          <i class="fas fa-redo"></i> <?= $lang==='th'?'ขอใหม่อีกครั้ง':'Request again' ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const _fpLang = <?= $lang === 'th' ? 'true' : 'false' ?>;
let _fpResetUrl = '';

function openForgotModal() {
    fpReset();
    document.getElementById('fpOv').classList.add('show');
    setTimeout(() => document.getElementById('fpIdentifier').focus(), 80);
}
function closeForgotModal() {
    document.getElementById('fpOv').classList.remove('show');
}
function fpReset() {
    document.getElementById('fpStep1').classList.add('active');
    document.getElementById('fpStep2').classList.remove('active');
    document.getElementById('fpIdentifier').value = '';
    document.getElementById('fpErr').classList.remove('show');
    const btn = document.getElementById('fpSubmitBtn');
    btn.disabled = false;
    btn.innerHTML = `<i class="fas fa-paper-plane"></i> ${_fpLang?'สร้างลิงก์ Reset':'Generate Reset Link'}`;
}
async function fpSubmit() {
    const id  = document.getElementById('fpIdentifier').value.trim();
    const err = document.getElementById('fpErr');
    const btn = document.getElementById('fpSubmitBtn');
    err.classList.remove('show');
    if (!id) {
        document.getElementById('fpErrTxt').textContent = _fpLang ? 'กรุณากรอกข้อมูล' : 'Please enter username or email';
        err.classList.add('show');
        return;
    }
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${_fpLang?'กำลังดำเนินการ...':'Processing...'}`;
    try {
        const r = await fetch('/v1/api/auth.php?action=forgot_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identifier: id })
        }).then(r => r.json());
        if (!r.success) throw new Error(r.error || 'Error');
        _fpResetUrl = r.reset_url;
        document.getElementById('fpName').textContent = r.name || id;
        document.getElementById('fpLinkBox').textContent = r.reset_url;
        document.getElementById('fpStep1').classList.remove('active');
        document.getElementById('fpStep2').classList.add('active');
    } catch(e) {
        document.getElementById('fpErrTxt').textContent = e.message;
        err.classList.add('show');
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-paper-plane"></i> ${_fpLang?'สร้างลิงก์ Reset':'Generate Reset Link'}`;
    }
}
function fpCopy() {
    navigator.clipboard?.writeText(_fpResetUrl).then(() => {
        const btn = document.getElementById('fpCopyBtn');
        btn.innerHTML = `<i class="fas fa-check"></i> ${_fpLang?'คัดลอกแล้ว!':'Copied!'}`;
        setTimeout(() => { btn.innerHTML = `<i class="fas fa-copy"></i> ${_fpLang?'คัดลอกลิงก์':'Copy Link'}`; }, 2000);
    });
}
function fpOpen() { if (_fpResetUrl) window.open(_fpResetUrl, '_self'); }
document.getElementById('fpIdentifier')?.addEventListener('keydown', e => { if (e.key === 'Enter') fpSubmit(); });
</script>
</body>
</html>
