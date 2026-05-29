<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }

$lang = I18n::getCurrentLang();
$th   = $lang === 'th';
$role = $user['role_name'] ?? 'user';

$displayName = trim((string)(!empty($user['full_name_th'])
    ? $user['full_name_th']
    : (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));
if ($displayName === '') $displayName = $user['username'] ?? ($th ? 'ผู้ใช้งาน' : 'User');

$isAdmin   = in_array($role, ['admin', 'ceo'], true);
$isManager = $role === 'lab_manager';

Layout::head($th ? 'Chemical Search Assistant' : 'Chemical Search Assistant');
?>
<style>
/* ── Lock page scroll: chat handles its own scroll internally ── */
html,body{overflow:hidden;height:100%}

/* ── Override ci-main: full-height flex column ── */
.ci-main{
    padding:0 !important;
    min-height:0 !important;          /* reset layout.php's min-height */
    height:calc(100dvh - var(--hdr-h)) !important;
    display:flex !important;
    flex-direction:column !important;
    overflow:hidden !important;
    background:#f0f4f8;
}

/* ── Mobile ≤768px: subtract mob-nav (fixed 56px + 8px buffer = 64px) ── */
@media(max-width:768px){
    .ci-main{
        height:calc(100dvh - var(--hdr-h) - 56px) !important;
        min-height:0 !important;
        margin-bottom:0 !important;
        /* push content up so it sits flush above mob-nav */
        padding-bottom:0 !important;
    }
    /* Extra padding inside input-area to not touch mob-nav edge */
    .input-area{
        padding-bottom:max(12px, env(safe-area-inset-bottom)) !important;
    }
}

/* ── CSS Variables ── */
:root{
    --bg:#f0f4f8;--card:#fff;--border:#e2e8f0;
    --c1:#0f172a;--c2:#475569;--c3:#94a3b8;
    --accent:#f97316;--accent-h:#ea6100;--accent-light:#fff7ed;
    --shadow:0 1px 4px rgba(0,0,0,.06);--shadow-lg:0 8px 32px rgba(0,0,0,.12);
    --info:#3b82f6;--info-bg:#eff6ff;
    --danger:#dc2626;--danger-bg:#fef2f2;--danger-border:#fecaca;
    --ok:#16a34a;--ok-bg:#f0fdf4;--ok-border:#bbf7d0;
    --warn:#f59e0b;--warn-bg:#fffbeb;--warn-border:#fde68a;
}

/* ── Scrollable chat area ── */
.chat-scroll{
    flex:1;
    min-height:0; /* critical: prevents flex child from overflowing */
    overflow-y:auto;overflow-x:hidden;
    scroll-behavior:smooth;
}
.chat-scroll::-webkit-scrollbar{width:5px}
.chat-scroll::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px}

/* ── Welcome screen: vertically + horizontally centered ── */
#welcomeScreen{
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:100%;
    padding:20px 16px;
    box-sizing:border-box;
}
.welcome{
    max-width:540px;width:100%;
    display:flex;flex-direction:column;align-items:center;
    text-align:center;
    padding:0;
}
.welcome-logo{
    width:64px;height:64px;border-radius:18px;
    background:linear-gradient(135deg,var(--accent),#fb923c);
    color:#fff;font-size:26px;
    display:flex;align-items:center;justify-content:center;
    margin-bottom:14px;
    box-shadow:0 8px 28px rgba(249,115,22,.30);
    animation:logoPop .5s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes logoPop{from{opacity:0;transform:scale(.5) translateY(20px)}to{opacity:1;transform:none}}
.welcome-title{font-size:24px;font-weight:800;color:var(--c1);margin-bottom:12px;line-height:1.2}
/* Role badge */
.role-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:5px 14px;border-radius:999px;font-size:11px;font-weight:700;
    border:1px solid transparent;margin-bottom:0;
}
.role-badge.admin{background:#fff7ed;color:#c2410c;border-color:#fdba74}
.role-badge.manager{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe}
.role-badge.user{background:#ecfdf5;color:#047857;border-color:#a7f3d0}

/* ── Messages ── */
.msgs-area{max-width:800px;margin:0 auto;padding:20px 20px 24px}
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

/* Loading dots */
.loading-dots{display:flex;gap:5px;padding:4px 0}
.loading-dots span{width:7px;height:7px;border-radius:50%;background:var(--accent);animation:ldot 1.4s ease-in-out infinite}
.loading-dots span:nth-child(1){animation-delay:-.32s}
.loading-dots span:nth-child(2){animation-delay:-.16s}
@keyframes ldot{0%,80%,100%{transform:scale(0)}40%{transform:scale(1)}}

/* ═══ RESPONSE BLOCKS ═══ */
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

.chembot-response{display:flex;flex-direction:column;gap:0}
.section-body{padding:0 2px 12px}

/* Info grid */
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:14px}
.info-cell{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px}
.info-cell-label{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.info-cell-value{font-size:14px;font-weight:700;color:var(--c1)}
.info-cell-value.mono{font-family:'JetBrains Mono',monospace;font-size:13px}

/* Hazard */
.hazard-block{background:var(--danger-bg);border:1px solid var(--danger-border);border-radius:14px;padding:16px;margin-bottom:16px}
.hazard-block-head{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:var(--danger);margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--danger-border)}
.signal-badge{padding:5px 18px;border-radius:8px;font-size:18px;font-weight:900;letter-spacing:.5px;display:inline-block;margin-bottom:12px}
.signal-badge.danger{background:var(--danger);color:#fff;box-shadow:0 3px 12px rgba(220,38,38,.3)}
.signal-badge.warning{background:var(--warn);color:#fff;box-shadow:0 3px 12px rgba(245,158,11,.3)}

/* GHS Pictograms */
.ghs-section{margin-bottom:16px}
.ghs-section-title{font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--c3);margin-bottom:12px;display:flex;align-items:center;gap:6px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.ghs-grid{display:flex;flex-wrap:wrap;gap:20px 12px;margin-bottom:4px;padding:4px 2px}
.ghs-item{display:flex;flex-direction:column;align-items:center;gap:7px;cursor:pointer;position:relative}
.ghs-diamond-wrap{width:88px;height:88px;transition:transform .3s cubic-bezier(.34,1.56,.64,1),filter .3s ease;filter:drop-shadow(0 4px 12px rgba(0,0,0,.22))}
.ghs-diamond-wrap svg,.ghs-diamond-wrap .ghs-official-svg{width:100%;height:100%;display:block}
.ghs-item:hover .ghs-diamond-wrap{transform:scale(1.18) rotate(4deg);filter:drop-shadow(0 8px 24px rgba(204,0,0,.55))}
.ghs-code-pill{font-size:9px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:#fff;background:#cc0000;padding:2px 7px;border-radius:99px;line-height:1.5;white-space:nowrap;box-shadow:0 2px 8px rgba(204,0,0,.35);transition:transform .2s ease,box-shadow .2s ease}
.ghs-item:hover .ghs-code-pill{transform:translateY(-2px);box-shadow:0 5px 16px rgba(204,0,0,.5)}
.ghs-name{font-size:10px;font-weight:600;color:var(--c2);text-align:center;max-width:80px;line-height:1.35}

/* H/P Statements */
.stmt-block{background:#fff;border-radius:10px;padding:12px;margin-bottom:10px}
.stmt-head{font-size:12px;font-weight:700;color:var(--danger);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.stmt-list{list-style:none;padding:0;display:flex;flex-direction:column;gap:5px}
.stmt-list li{font-size:12px;color:var(--c2);padding-left:14px;position:relative;line-height:1.5}
.stmt-list li::before{content:'•';position:absolute;left:2px;color:var(--danger)}

/* Location — Pro Redesign */
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
/* Tree */
.loc-tree{padding:12px;display:flex;flex-direction:column;gap:10px}
/* Building */
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
/* Room */
.loc-room{border:1px solid #a7f3d0;border-radius:10px;overflow:hidden}
.loc-room-hdr{display:flex;align-items:center;gap:8px;padding:8px 12px;font-weight:700;color:#065f46;font-size:12px;background:linear-gradient(90deg,#dcfce7,#f0fdf4);border-bottom:1px solid #a7f3d0}
.loc-room-hdr .loc-n-badge{background:#059669;margin-left:auto}
/* Cabinet */
.loc-cabinet{background:#fff;padding:8px}
.loc-cabinet+.loc-cabinet{border-top:1px solid #f0fdf4}
.loc-cabinet-hdr{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#065f46;margin-bottom:7px;padding:5px 8px;background:linear-gradient(90deg,#f0fdf4,#fff);border-radius:7px;border:1px solid #d1fae5}
/* Items */
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
/* Container type color variants */
.ctype-flask .loc-item-type-col{background:linear-gradient(135deg,#f0fdf4,#dcfce7)}
.ctype-flask .ctype-icon{color:#16a34a}
.ctype-flask .ctype-lbl{color:#166534}
.ctype-bag .loc-item-type-col{background:linear-gradient(135deg,#fdf4ff,#f3e8ff)}
.ctype-bag .ctype-icon{color:#9333ea}
.ctype-bag .ctype-lbl{color:#7e22ce}
.ctype-drum .loc-item-type-col{background:linear-gradient(135deg,#fef3c7,#fde68a)}
.ctype-drum .ctype-icon{color:#b45309}
.ctype-drum .ctype-lbl{color:#92400e}
.ctype-can .loc-item-type-col{background:linear-gradient(135deg,#fff7ed,#fed7aa)}
.ctype-can .ctype-icon{color:#ea580c}
.ctype-can .ctype-lbl{color:#c2410c}
.ctype-box .loc-item-type-col{background:linear-gradient(135deg,#f8fafc,#f1f5f9)}
.ctype-box .ctype-icon{color:#475569}
.ctype-box .ctype-lbl{color:#334155}
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
/* Mobile */
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
.loc-item-actions{padding:6px 8px;gap:4px}
.loc-act-btn{padding:4px 9px;font-size:10px}
.loc-stock-status{max-width:100%;margin-left:0}
}
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

/* SDS */
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
.sds-btn-b{background:var(--info);color:#fff}.sds-btn-b:hover{background:#2563eb}
.sds-btn-r{background:var(--danger);color:#fff}.sds-btn-r:hover{background:#b91c1c}
.sds-btn-c{background:#0891b2;color:#fff}.sds-btn-c:hover{background:#0e7490}
.sds-btn-g{background:#6b7280;color:#fff}.sds-btn-g:hover{background:#4b5563}

/* Search results */
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
.rc-action-btn.primary{background:var(--accent);color:#fff}.rc-action-btn.primary:hover{background:var(--accent-h)}
.rc-action-btn.outline{background:transparent;color:var(--c2);border:1px solid var(--border)}.rc-action-btn.outline:hover{background:var(--border)}

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

/* 3D */
.model-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;max-width:480px;margin:0 auto}
.model-link{display:flex;flex-direction:column;align-items:center;gap:8px;padding:16px;border-radius:12px;text-decoration:none;color:#fff;transition:.2s;font-size:12px;font-weight:700}
.model-link:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
.model-link i{font-size:24px;margin-bottom:2px}
.model-link small{opacity:.8;font-size:10px;font-weight:400}
.local3d-wrapper{display:flex;flex-direction:column;gap:14px}
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
.embed3d-container{border:1px solid rgba(255,255,255,.08);border-radius:14px;overflow:hidden;background:#0f0f1a;box-shadow:0 8px 32px rgba(0,0,0,.3)}
.embed3d-header{display:flex;align-items:center;gap:8px;padding:9px 14px;background:rgba(255,255,255,.05)}
.embed3d-provider{font-size:11px;font-weight:700;color:rgba(255,255,255,.8)}
.embed3d-model-name{font-size:11px;color:rgba(255,255,255,.4);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.embed3d-fullscreen-btn{margin-left:auto;font-size:14px;color:rgba(255,255,255,.4);text-decoration:none;line-height:1;transition:.15s;padding:2px 6px;border-radius:5px}
.embed3d-fullscreen-btn:hover{color:#fff;background:rgba(255,255,255,.1)}
.embed3d-frame-wrap{position:relative;width:100%;background:#0f0f1a}
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

/* Compatibility */
.compat-block{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;margin-bottom:12px}
.compat-block-head{font-size:12px;font-weight:700;color:#c2410c;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.compat-list{display:flex;flex-wrap:wrap;gap:6px}
.compat-item{background:#fef3c7;color:#b45309;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600}

/* Physical props */
.phys-table{width:100%;border-collapse:collapse;margin-bottom:14px;font-size:12px}
.phys-table th{background:#f8fafc;padding:7px 10px;text-align:left;font-weight:700;color:var(--c2);border-bottom:2px solid var(--border)}
.phys-table td{padding:7px 10px;border-bottom:1px solid var(--border);color:var(--c1)}
.phys-table td:first-child{color:var(--c3);font-weight:500;width:40%}
.phys-table td:last-child{font-weight:600;font-family:'JetBrains Mono',monospace;font-size:11px}
.phys-table tr:hover td{background:#fafafa}

/* DB stats */
.db-stats-bar{background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:12px 16px;text-align:center;font-size:12px;color:var(--c3);margin-top:16px}

/* Section toggle */
.section-toggle{cursor:pointer;user-select:none}
.section-toggle .toggle-icon{transition:transform .2s}
.section-toggle.collapsed .toggle-icon{transform:rotate(-90deg)}
.section-body.collapsed{display:none}

/* ═══ ANIMATIONS ═══ */
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes skshimmer{0%{background-position:-400px 0}100%{background-position:400px 0}}
@keyframes fadeSlideIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes orbit1{0%{transform:translate(-50%,-50%) rotate(0deg) translateX(9px)}100%{transform:translate(-50%,-50%) rotate(360deg) translateX(9px)}}
@keyframes orbit2{0%{transform:translate(-50%,-50%) rotate(0deg) translateX(-9px)}100%{transform:translate(-50%,-50%) rotate(-360deg) translateX(-9px)}}
@keyframes tickFade{0%{opacity:0;transform:translateY(5px)}20%{opacity:1;transform:translateY(0)}80%{opacity:1;transform:translateY(0)}100%{opacity:0;transform:translateY(-5px)}}
@keyframes gradFlow{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 3px rgba(249,115,22,.2)}50%{box-shadow:0 0 0 6px rgba(249,115,22,.1)}}

/* Thinking block */
.thinking-block{background:#fff;border:1px solid var(--border);border-radius:16px;padding:18px 20px 14px;margin-bottom:4px;box-shadow:0 2px 16px rgba(0,0,0,.06);animation:fadeSlideIn .25s ease both;position:relative;overflow:hidden}
.thinking-block::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(270deg,var(--accent),#fb923c,#f59e0b,#34d399,var(--accent));background-size:300% 300%;animation:gradFlow 2s linear infinite}
.thinking-header{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.thinking-orbit{position:relative;width:24px;height:24px;flex-shrink:0}
.thinking-orbit-core{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#fb923c);position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);box-shadow:0 0 6px rgba(249,115,22,.6)}
.thinking-orbit-dot{width:5px;height:5px;border-radius:50%;background:var(--accent);position:absolute;top:50%;left:50%}
.thinking-orbit-dot:nth-child(2){animation:orbit1 1.0s linear infinite;opacity:.8}
.thinking-orbit-dot:nth-child(3){animation:orbit2 1.5s linear infinite;background:#fb923c;opacity:.6}
.thinking-title{font-size:12px;font-weight:700;color:var(--c1)}
.thinking-ticker-wrap{margin-left:auto;overflow:hidden;height:16px;min-width:130px;max-width:160px;text-align:right}
.thinking-ticker{font-size:10px;color:var(--c3);font-style:italic;display:inline-block;animation:tickFade 2.4s ease-in-out infinite}
.thinking-steps{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.thinking-step{display:flex;align-items:center;gap:9px;font-size:11.5px;color:var(--c3);transition:color .25s ease,opacity .25s ease;opacity:.35}
.thinking-step.done{color:#16a34a;opacity:.9}
.thinking-step.active{color:var(--c1);opacity:1;font-weight:600}
.thinking-step.pending{opacity:.25}
.thinking-step .step-icon{width:18px;height:18px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:8px;transition:background .25s,box-shadow .25s;background:var(--border);color:var(--c3)}
.thinking-step.done .step-icon{background:#dcfce7;color:#16a34a}
.thinking-step.active .step-icon{background:linear-gradient(135deg,var(--accent),#fb923c);color:#fff;box-shadow:0 0 0 3px rgba(249,115,22,.2);animation:pulse .9s ease-in-out infinite}
.thinking-step .step-label{flex:1}
.thinking-step .step-time{font-size:9px;color:var(--c3);font-family:'JetBrains Mono',monospace;min-width:28px;text-align:right;opacity:0;transition:opacity .3s}
.thinking-step.done .step-time{opacity:1}

/* Skeleton */
.skel-section{margin-top:2px}
.skel-line{height:9px;border-radius:6px;margin-bottom:7px;background:linear-gradient(90deg,#f1f5f9 25%,#e8ecf0 50%,#f1f5f9 75%);background-size:400px 100%;animation:skshimmer 1.4s ease-in-out infinite}
.skel-line.short{width:38%}.skel-line.med{width:65%}.skel-line.long{width:88%}.skel-line.full{width:100%}
.skel-line.title{height:12px;width:50%;margin-bottom:10px;border-radius:8px}
.skel-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.skel-row .skel-line{margin-bottom:0}

/* ═══ INPUT AREA ═══ */
.input-area{
    padding:10px 20px 14px;
    background:#fff;
    border-top:1px solid var(--border);
    flex-shrink:0;
    box-shadow:0 -2px 12px rgba(0,0,0,.04);
}
.input-wrap{max-width:800px;margin:0 auto;position:relative}
.input-row{display:flex;align-items:flex-end;gap:8px;background:var(--card);border:1.5px solid var(--border);border-radius:16px;padding:8px 8px 8px 16px;transition:.15s;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.input-row:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px rgba(249,115,22,.1)}
#chatInput{flex:1;border:none;outline:none;background:transparent;font-size:14px;resize:none;min-height:36px;max-height:180px;font-family:inherit;color:var(--c1);line-height:1.5;padding:4px 0}
#chatInput::placeholder{color:var(--c3)}
.send-btn{width:38px;height:38px;border-radius:11px;border:none;background:var(--accent);color:#fff;cursor:pointer;transition:.15s;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.send-btn:hover{background:var(--accent-h);transform:scale(1.05)}
.send-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}

/* Toolbar */
.input-toolbar{display:flex;align-items:center;gap:6px;padding:8px 2px 0;flex-wrap:wrap;min-height:38px}
.input-toolbar-left{display:flex;align-items:center;gap:6px;flex:1;flex-wrap:wrap}
.feat-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;border:1.5px solid var(--border);background:transparent;font-size:12px;font-weight:500;color:var(--c2);cursor:pointer;transition:all .15s;font-family:inherit;white-space:nowrap;line-height:1}
.feat-pill i{font-size:11px}
.feat-pill:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-light)}
.feat-pill.active{border-color:var(--accent);color:var(--accent);background:var(--accent-light)}

/* Chips */
.input-chips-row{display:flex;align-items:center;gap:6px;padding:6px 2px 0;flex-wrap:wrap;transition:all .25s}
.input-chips-row.hidden{display:none}
.input-chip{padding:5px 12px;background:#f8fafc;border:1px solid var(--border);border-radius:20px;font-size:11.5px;color:var(--c2);cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:5px;font-family:inherit;white-space:nowrap}
.input-chip i{font-size:10px;opacity:.7}
.input-chip:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-light);transform:translateY(-1px);box-shadow:0 3px 8px rgba(249,115,22,.1)}

/* ═══ HISTORY PANEL ═══ */
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

/* ═══ STORAGE BROWSER ═══ */
.sbm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:300;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.sbm-overlay.show{display:flex}
.sbm{width:min(860px,96vw);max-height:90vh;background:#fff;border-radius:20px;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.25);overflow:hidden;animation:sbmIn .25s cubic-bezier(.34,1.56,.64,1)}
@keyframes sbmIn{from{opacity:0;transform:scale(.93) translateY(16px)}to{opacity:1;transform:none}}
.sbm-hdr{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,#f0fdf4,#fff)}
.sbm-hdr-icon{width:40px;height:40px;border-radius:11px;background:var(--ok);color:#fff;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.sbm-hdr-title{flex:1}.sbm-hdr-title h2{font-size:16px;font-weight:800;color:var(--c1)}.sbm-hdr-title p{font-size:11px;color:var(--c3);margin-top:1px}
.sbm-close{width:34px;height:34px;border:none;background:var(--border);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--c2);font-size:14px;transition:.15s}.sbm-close:hover{background:#e2e8f0;color:var(--c1)}
.sbm-breadcrumb{padding:10px 22px;border-bottom:1px solid var(--border);background:#f8fafc;display:flex;align-items:center;gap:5px;flex-wrap:wrap;min-height:42px}
.sbm-crumb{font-size:12px;color:var(--c3);display:flex;align-items:center;gap:5px}
.sbm-crumb a{color:var(--accent);cursor:pointer;text-decoration:none}.sbm-crumb a:hover{text-decoration:underline}
.sbm-crumb.active{font-weight:700;color:var(--c1)}.sbm-crumb-sep{color:var(--c3)}
.sbm-body{flex:1;overflow-y:auto;padding:18px 22px}
.sbm-body::-webkit-scrollbar{width:4px}.sbm-body::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px}
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
.sbm-room-list{display:flex;flex-direction:column;gap:8px}
.sbm-room-row{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:13px 16px;cursor:pointer;display:flex;align-items:center;gap:12px;transition:.2s}
.sbm-room-row:hover{border-color:var(--ok);background:var(--ok-bg);transform:translateX(4px)}
.sbm-room-icon{width:36px;height:36px;border-radius:9px;background:var(--ok-bg);color:var(--ok);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.sbm-room-info{flex:1;min-width:0}.sbm-room-code{font-size:13px;font-weight:800;color:var(--c1);font-family:'JetBrains Mono',monospace}
.sbm-room-name{font-size:11px;color:var(--c3);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sbm-room-count{background:var(--ok);color:#fff;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;white-space:nowrap}
.sbm-room-arr{color:var(--c3);font-size:12px;transition:.2s}.sbm-room-row:hover .sbm-room-arr{color:var(--ok);transform:translateX(3px)}
.sbm-ctr-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px}
.sbm-ctr-title{font-size:13px;font-weight:700;color:var(--c1);display:flex;align-items:center;gap:8px}
.sbm-ctr-badge{background:var(--ok);color:#fff;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700}
.sbm-ctr-search{padding:7px 12px;border:1px solid var(--border);border-radius:8px;font-size:12px;outline:none;width:200px;font-family:inherit;transition:.15s}
.sbm-ctr-search:focus{border-color:var(--ok);box-shadow:0 0 0 3px rgba(16,185,129,.1)}
.sbm-tbl{width:100%;border-collapse:collapse;font-size:12px}
.sbm-tbl thead th{background:#f0fdf4;padding:8px 10px;text-align:left;font-weight:700;color:#166534;border-bottom:2px solid var(--ok-border);white-space:nowrap;position:sticky;top:0}
.sbm-tbl tbody tr{transition:.1s}.sbm-tbl tbody tr:hover{background:#f0fdf4}
.sbm-tbl td{padding:8px 10px;border-bottom:1px solid #f0fdf4;vertical-align:middle}
.sbm-tbl td .chem-nm{font-weight:700;color:var(--c1)}.sbm-tbl td .cas-nm{font-size:10px;color:var(--c3);font-family:'JetBrains Mono',monospace}
.sbm-tbl td .qty-val{font-weight:700;color:#166534}.sbm-tbl td .qr-code{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--c2)}
.sbm-tbl td .type-pill{padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700;background:#e0f2fe;color:#0369a1}
.sbm-tbl .expiry-warn{color:var(--warn);font-weight:700}.sbm-tbl .expiry-ok{color:var(--ok);font-size:10px}
.sbm-empty{text-align:center;padding:36px;color:var(--c3)}
.sbm-empty i{font-size:40px;display:block;margin-bottom:10px;opacity:.3}
.sbm-empty p{font-size:13px}
.sbm-loading{text-align:center;padding:40px;color:var(--c3)}
.sbm-spinner{width:36px;height:36px;border:3px solid var(--border);border-top-color:var(--ok);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px}

/* ═══ MOBILE ≤768px ═══ */
@media(max-width:768px){
    .welcome-logo{width:52px;height:52px;font-size:21px;margin-bottom:10px}
    .welcome-title{font-size:19px}
    .msgs-area{padding:14px 12px 8px}
    .msg{gap:8px;margin-bottom:16px}
    .msg-av{width:30px;height:30px;font-size:13px;border-radius:8px}
    .msg-body{max-width:calc(100% - 38px)}
    .msg-bubble{padding:11px 14px;font-size:13px;border-radius:12px}
    .input-area{padding:8px 12px max(12px,env(safe-area-inset-bottom))}
    .input-row{border-radius:14px;padding:6px 6px 6px 12px}
    #chatInput{font-size:14px;min-height:34px}
    .send-btn{width:36px;height:36px;border-radius:10px}
    /* toolbar: single scrollable row */
    .input-toolbar{min-height:auto;padding:6px 0 0;gap:0;flex-wrap:nowrap}
    .input-toolbar-left{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;gap:5px;padding-bottom:2px;-webkit-mask-image:linear-gradient(to right,#000 78%,transparent 100%);mask-image:linear-gradient(to right,#000 78%,transparent 100%)}
    .input-toolbar-left::-webkit-scrollbar{display:none}
    .feat-pill{padding:5px 11px;font-size:11.5px;flex-shrink:0}
    /* chips: single scrollable row */
    .input-chips-row{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;padding:5px 0 0;-webkit-mask-image:linear-gradient(to right,#000 82%,transparent 100%);mask-image:linear-gradient(to right,#000 82%,transparent 100%)}
    .input-chips-row::-webkit-scrollbar{display:none}
    .input-chip{flex-shrink:0}
    /* history */
    .history-panel{width:100%;right:-100%}
    .history-panel.open{right:0}
    /* thinking */
    .thinking-block{padding:14px 14px 10px;border-radius:12px}
    .thinking-ticker-wrap{min-width:100px;max-width:130px}
    /* chem hero */
    .chem-hero{flex-direction:column;gap:12px;padding:14px}
    .chem-hero-img{width:100px;height:100px;align-self:center}
    .chem-hero-name{font-size:17px}
    /* result cards */
    .rc-hdr{gap:10px;padding:12px}.rc-img{width:64px;height:64px}.rc-name{font-size:14px}
    .info-grid{grid-template-columns:1fr 1fr;gap:8px}
    .ghs-diamond-wrap{width:68px;height:68px}.ghs-grid{gap:14px 8px}.ghs-name{font-size:9px;max-width:65px}
    .loc-tree{padding:8px;gap:8px}
    .loc-rooms{padding:8px;gap:6px}
    .sds-btns{gap:6px}.sds-btn{padding:8px 12px;font-size:11px}
    .ext-grid{grid-template-columns:1fr}
    .model-grid{grid-template-columns:repeat(2,1fr)}
    .sbm{border-radius:16px}.sbm-bld-grid{grid-template-columns:1fr 1fr}.sbm-hdr{padding:14px 16px}
}
@media(max-width:380px){
    .ghs-diamond-wrap{width:56px;height:56px}.ghs-grid{gap:10px 6px}
    /* XS: toolbar pills → icon only */
    .feat-pill span.pill-label{display:none}
    .feat-pill{padding:6px 10px;border-radius:50%;width:32px;height:32px;justify-content:center}
    .feat-pill i{font-size:12px}
    .input-chip{padding:5px 10px;font-size:11px}
}
</style>

<?php Layout::sidebar('dashboard'); Layout::beginContent(); ?>

<!-- ═══ SCROLLABLE CHAT AREA ═══ -->
<div class="chat-scroll" id="chatScroll">

    <!-- Welcome Screen -->
    <div id="welcomeScreen">
        <div class="welcome">
            <div class="welcome-logo"><i class="fas fa-flask-vial"></i></div>
            <h2 class="welcome-title"><?php echo $th ? 'มีอะไรให้ฉันช่วยไหมคะ?' : 'How can I help you today?'; ?></h2>
            <div class="role-badge <?php echo $isAdmin ? 'admin' : ($isManager ? 'manager' : 'user'); ?>">
                <i class="fas <?php echo $isAdmin ? 'fa-crown' : ($isManager ? 'fa-microscope' : 'fa-user'); ?>"></i>
                <?php
                    $roleLabel = $th
                        ? ($isAdmin ? 'ผู้ดูแลระบบ' : ($isManager ? 'ผู้จัดการห้องปฏิบัติการ' : 'ผู้ใช้งาน'))
                        : ($isAdmin ? 'Admin / Executive' : ($isManager ? 'Lab Manager' : 'User'));
                    echo htmlspecialchars($roleLabel);
                ?>
                &nbsp;·&nbsp; <?php echo htmlspecialchars($displayName); ?>
            </div>
        </div>
    </div>

    <!-- Messages area -->
    <div class="msgs-area" id="msgsArea" style="display:none"></div>

</div><!-- /.chat-scroll -->

<!-- ═══ INPUT AREA ═══ -->
<div class="input-area">
    <div class="input-wrap">
        <div class="input-row">
            <textarea id="chatInput" placeholder="<?php echo $th ? 'พิมพ์ชื่อสาร, CAS, สูตร หรือคำถาม...' : 'Type chemical name, CAS, formula, or your question...'; ?>" rows="1" onkeydown="onKey(event)"></textarea>
            <button class="send-btn" id="sendBtn" onclick="doSend()"><i class="fas fa-paper-plane"></i></button>
        </div>

        <!-- Toolbar: feature pills -->
        <div class="input-toolbar" id="inputToolbar">
            <div class="input-toolbar-left">
                <button class="feat-pill" onclick="sendMsg('<?php echo $th ? 'ค้นหาสาร เอทานอล' : 'search ethanol'; ?>')" title="<?php echo $th ? 'ค้นหาสารเคมี' : 'Chemical Search'; ?>">
                    <i class="fas fa-search"></i><span class="pill-label"> <?php echo $th ? 'ค้นหาสาร' : 'Search'; ?></span>
                </button>
                <button class="feat-pill" onclick="sendMsg('<?php echo $th ? 'อะซิโตน อยู่ที่ไหน' : 'where is acetone'; ?>')" title="<?php echo $th ? 'ตำแหน่งจัดเก็บ' : 'Storage Location'; ?>">
                    <i class="fas fa-map-marker-alt"></i><span class="pill-label"> <?php echo $th ? 'ที่จัดเก็บ' : 'Location'; ?></span>
                </button>
                <button class="feat-pill" onclick="sendMsg('<?php echo $th ? 'SDS กรดซัลฟิวริก' : 'SDS sulfuric acid'; ?>')" title="SDS / Safety">
                    <i class="fas fa-file-shield"></i><span class="pill-label"> SDS</span>
                </button>
                <button class="feat-pill" onclick="sendMsg('<?php echo $th ? 'ความอันตราย H2SO4' : 'hazard H2SO4'; ?>')" title="GHS Hazard">
                    <i class="fas fa-exclamation-triangle"></i><span class="pill-label"> GHS</span>
                </button>
                <button class="feat-pill" onclick="openStorageBrowser()" title="<?php echo $th ? 'สถานที่จัดเก็บ' : 'Storage Browser'; ?>" style="border-color:var(--ok-border);color:#166534">
                    <i class="fas fa-warehouse"></i><span class="pill-label"> <?php echo $th ? 'คลังสาร' : 'Storage'; ?></span>
                </button>
                <button class="feat-pill" onclick="sendMsg('<?php echo $th ? 'ตารางธาตุ' : 'periodic table'; ?>')" title="<?php echo $th ? 'ตารางธาตุ' : 'Periodic Table'; ?>" style="border-color:#c7d2fe;color:#4338ca">
                    <i class="fas fa-table-cells"></i><span class="pill-label"> <?php echo $th ? 'ตารางธาตุ' : 'Periodic'; ?></span>
                </button>
            </div>
        </div>

        <!-- Quick chips (hidden after first send) -->
        <div class="input-chips-row" id="inputChipsRow">
            <button class="input-chip" onclick="sendMsg('<?php echo $th ? 'เอทานอล' : 'ethanol'; ?>')"><i class="fas fa-flask"></i> Ethanol</button>
            <button class="input-chip" onclick="sendMsg('7664-93-9')"><i class="fas fa-hashtag"></i> 7664-93-9</button>
            <button class="input-chip" onclick="sendMsg('<?php echo $th ? 'อะซิโตน อยู่ที่ไหน' : 'where is acetone'; ?>')"><i class="fas fa-map-marker-alt"></i> <?php echo $th ? 'อะซิโตน ที่ไหน' : 'Acetone?'; ?></button>
            <button class="input-chip" onclick="sendMsg('H2SO4')"><i class="fas fa-atom"></i> H₂SO₄</button>
            <button class="input-chip" onclick="sendMsg('NaOH')"><i class="fas fa-atom"></i> NaOH</button>
            <button class="input-chip" onclick="sendMsg('<?php echo $th ? 'SDS เมทานอล' : 'SDS methanol'; ?>')"><i class="fas fa-file-shield"></i> SDS Methanol</button>
            <?php if ($isManager || $isAdmin): ?>
            <button class="input-chip" onclick="sendMsg('<?php echo $th ? 'สารใกล้หมดอายุ' : 'expiring chemicals'; ?>')"><i class="fas fa-calendar-times"></i> <?php echo $th ? 'ใกล้หมดอายุ' : 'Expiry'; ?></button>
            <button class="input-chip" onclick="sendMsg('low stock chemicals')"><i class="fas fa-boxes-stacked"></i> <?php echo $th ? 'สต็อกต่ำ' : 'Low stock'; ?></button>
            <?php else: ?>
            <button class="input-chip" onclick="sendMsg('<?php echo $th ? 'ความอันตราย คลอรีน' : 'chlorine hazard'; ?>')"><i class="fas fa-biohazard"></i> <?php echo $th ? 'อันตราย Cl₂' : 'Cl₂ hazard'; ?></button>
            <button class="input-chip" onclick="sendMsg('<?php echo $th ? 'กรดไฮโดรคลอริก' : 'hydrochloric acid'; ?>')"><i class="fas fa-vial"></i> HCl</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══ HISTORY PANEL ═══ -->
<div class="history-panel" id="histPanel">
    <div class="hp-head">
        <h3><i class="fas fa-history"></i> <?php echo $th ? 'ประวัติการค้นหา' : 'Search History'; ?></h3>
        <div style="display:flex;gap:6px;align-items:center">
            <button class="hp-close" onclick="clearHistory()" style="width:auto;padding:0 10px;font-size:11px;font-weight:600;color:#dc2626;background:#fee2e2;border-radius:7px;display:flex;align-items:center;gap:4px">
                <i class="fas fa-trash-alt" style="font-size:10px"></i> <?php echo $th ? 'ล้าง' : 'Clear'; ?>
            </button>
            <button class="hp-close" onclick="toggleHistory()"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="hp-body" id="hpBody">
        <div class="hp-empty"><i class="fas fa-clock"></i><p><?php echo $th ? 'ยังไม่มีประวัติการค้นหา' : 'No search history yet'; ?></p></div>
    </div>
</div>

<!-- ═══ STORAGE BROWSER MODAL ═══ -->
<div class="sbm-overlay" id="sbmOverlay" onclick="sbmBgClick(event)">
  <div class="sbm" id="sbm">
    <div class="sbm-hdr">
      <div class="sbm-hdr-icon"><i class="fas fa-warehouse"></i></div>
      <div class="sbm-hdr-title">
        <h2><?php echo $th ? 'สถานที่จัดเก็บสารเคมี' : 'Chemical Storage Browser'; ?></h2>
        <p id="sbmSubtitle"><?php echo $th ? 'เลือกอาคารเพื่อดูรายละเอียด' : 'Select a building to explore'; ?></p>
      </div>
      <button class="sbm-close" onclick="closeStorageBrowser()"><i class="fas fa-times"></i></button>
    </div>
    <div class="sbm-breadcrumb" id="sbmBreadcrumb">
      <div class="sbm-crumb active"><span><?php echo $th ? 'อาคารทั้งหมด' : 'All Buildings'; ?></span></div>
    </div>
    <div class="sbm-body" id="sbmBody">
      <div class="sbm-loading"><div class="sbm-spinner"></div><?php echo $th ? 'กำลังโหลด...' : 'Loading...'; ?></div>
    </div>
  </div>
</div>

<!-- ═══ GLB FULLSCREEN OVERLAY ═══ -->
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
    <button class="glb-ov-close" onclick="closeGLBOverlay()"><i class="fas fa-times"></i></button>
  </div>
  <div class="glb-ov-body" id="glbOvBody">
    <div class="glb-ov-hint"><?php echo $th ? 'ลากเพื่อหมุน • เลื่อนเพื่อซูม' : 'Drag to rotate • Scroll to zoom'; ?></div>
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

<script>
// ═══════════════════════════════════════════════
// CONSTANTS & STATE
// ═══════════════════════════════════════════════
const LANG = '<?php echo $lang; ?>';
const ROLE = <?php echo json_encode($role); ?>;
const IS_ADMIN   = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const IS_MANAGER = <?php echo $isManager ? 'true' : 'false'; ?>;
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
    // 3D observer
    _glbObserver.observe(document.getElementById('msgsArea') || document.body, { childList: true, subtree: true });
    attachModelViewers();
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
            if (el) el.textContent = d.data.containers?.toLocaleString() ?? '—';
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
    const ta  = document.getElementById('chatInput');
    const msg = ta.value.trim();
    if (!msg || isProcessing) return;

    isProcessing = true;
    document.getElementById('sendBtn').disabled = true;
    ta.value = '';
    ta.style.height = 'auto';

    // Hide chips after first send
    const chipsRow = document.getElementById('inputChipsRow');
    if (chipsRow) chipsRow.classList.add('hidden');

    // Switch to chat view
    document.getElementById('welcomeScreen').style.display = 'none';
    document.getElementById('msgsArea').style.display = 'block';

    appendMsg(msg, 'user');
    const thinkId = appendThinking(msg);

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
// Re-execute <script> tags that were injected via innerHTML (browser blocks them by default)
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
    const name = role === 'user' ? L('คุณ', 'You') : 'SUT ChemBot';
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

    const isCAS      = /\d{2,7}-\d{2}-\d/.test(query);
    const isSDS      = /sds|msds|ความปลอดภัย|safety/i.test(query);
    const isHazard   = /อันตราย|hazard|ghs|pictogram|signal/i.test(query);
    const isLocation = /อยู่|ที่ไหน|where|location|stored|เก็บ|ตำแหน่ง/i.test(query);
    const isExpiry   = /หมดอายุ|expir/i.test(query);
    const isStock    = /สต็อก|สต๊อก|stock|inventory|ทั้งหมด/i.test(query);
    const isBrowse   = /storage|คลัง|อาคาร|browse/i.test(query);

    const steps = isCAS
        ? [['hashtag', L('ตรวจสอบ CAS Registry','Validating CAS registry')],['database',L('ค้นหาในฐานข้อมูล','Querying local database')],['map-marker-alt',L('โหลดตำแหน่งจัดเก็บ','Resolving storage locations')],['shield-alt',L('โหลดข้อมูล GHS / SDS','Loading GHS & SDS data')]]
        : isSDS
        ? [['microscope',L('ระบุสาร','Identifying substance')],['file-alt',L('ค้นหา SDS','Fetching SDS document')],['link',L('ตรวจสอบลิงก์','Verifying source links')]]
        : isHazard
        ? [['exclamation-triangle',L('ระบุสาร','Identifying chemical')],['radiation-alt',L('ตรวจสอบ GHS Hazard','Evaluating GHS hazards')],['list-ul',L('รวบรวม H/P Statements','Compiling H & P statements')]]
        : isLocation
        ? [['search',L('ระบุสาร','Identifying chemical')],['map-marker-alt',L('ค้นหาตำแหน่งทั้งหมด','Finding all locations')],['layer-group',L('จัดกลุ่มตามอาคาร','Grouping by building')]]
        : isExpiry
        ? [['calendar-alt',L('ดึงข้อมูลวันหมดอายุ','Fetching expiry records')],['sort-amount-up',L('จัดเรียงตามวันที่','Sorting by date')],['bell',L('ตรวจสอบระดับแจ้งเตือน','Checking alert thresholds')]]
        : isStock
        ? [['warehouse',L('สแกนคลังทั้งหมด','Scanning full inventory')],['chart-bar',L('รวบรวมสถิติ','Aggregating statistics')],['filter',L('กรองและจัดรูปแบบ','Filtering & formatting')]]
        : isBrowse
        ? [['building',L('โหลดข้อมูลอาคาร','Loading buildings')],['door-open',L('โหลดห้อง','Loading rooms')],['vial',L('โหลดรายการสาร','Loading container list')]]
        : [['brain',L('วิเคราะห์คำค้นหา','Analyzing query')],['database',L('ค้นหาในฐานข้อมูล','Searching database')],['magic',L('จัดรูปแบบผลลัพธ์','Formatting results')]];

    const tickers = isCAS
        ? ['CAS: '+query.trim(),'JOIN chemicals…','GROUP BY building…']
        : isLocation
        ? [L('ระบุชื่อสาร…','Identifying…'),L('ค้นตำแหน่ง…','Resolving…'),L('เรียงอาคาร…','Sorting…')]
        : isSDS
        ? [L('ระบุสาร…','Identifying…'),L('ค้นหา SDS…','Fetching SDS…'),L('ตรวจสอบ…','Verifying…')]
        : [L('วิเคราะห์…','Parsing…'),'SELECT FROM chemicals…',L('จัดรูปแบบ…','Formatting…')];

    const skelHtml = (isCAS || isLocation)
        ? `<div class="skel-section"><div class="skel-line title"></div><div class="skel-row"><div class="skel-line"></div><div class="skel-line short"></div></div><div class="skel-line full"></div><div class="skel-line med"></div><div class="skel-row"><div class="skel-line long"></div><div class="skel-line short"></div></div></div>`
        : `<div class="skel-section"><div class="skel-line title"></div><div class="skel-line long"></div><div class="skel-line med"></div></div>`;

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
                        ${steps.map((s,i) => `<div class="thinking-step ${i===0?'active':'pending'}" id="${id}-s${i}">
                            <span class="step-icon"><i class="fas fa-${s[0]}"></i></span>
                            <span class="step-label">${s[1]}</span>
                            <span class="step-time" id="${id}-t${i}"></span>
                        </div>`).join('')}
                    </div>
                    ${skelHtml}
                </div>
            </div>
        </div>`;

    area.appendChild(div);
    scrollBottom();

    // Step animation
    const stepTargets = steps.map(() => {
        const r = Math.random();
        if (r < 0.20) return 0.01 + Math.random() * 0.27;
        if (r < 0.90) return 0.28 + Math.random() * 1.17;
        return 1.45 + Math.random() * 1.08;
    });

    function advanceToStep(i, prevTarget) {
        if (i > 0) {
            const prevEl = document.getElementById(`${id}-s${i-1}`);
            if (prevEl) { prevEl.className = 'thinking-step done'; }
            const tEl = document.getElementById(`${id}-t${i-1}`);
            if (tEl) { animateCounter(`${id}-t${i-1}`, prevTarget, 260); }
        }
        const el = document.getElementById(`${id}-s${i}`);
        if (el) el.className = 'thinking-step active';
        const tickerEl = document.getElementById(`${id}-ticker`);
        if (tickerEl) {
            const clone = tickerEl.cloneNode(false);
            clone.textContent = tickers[Math.min(i, tickers.length-1)];
            clone.id = `${id}-ticker`;
            tickerEl.parentNode.replaceChild(clone, tickerEl);
        }
    }
    function animateCounter(elId, target, duration) {
        const el = document.getElementById(elId);
        if (!el) return;
        const fps = 30, interval = 1000/fps, stps = Math.max(1, Math.round(duration/interval));
        let frame = 0;
        const ease = t => 1 - Math.pow(1-t, 4);
        const timer = setInterval(() => {
            frame++;
            el.textContent = (target * ease(frame/stps)).toFixed(2) + 's';
            if (frame >= stps) { clearInterval(timer); el.textContent = target.toFixed(2) + 's'; }
        }, interval);
    }

    const STEP_MS = 380;
    let cumDelay = 0;
    for (let i = 1; i < steps.length; i++) {
        const delay = cumDelay + STEP_MS + stepTargets[i-1] * 320;
        cumDelay = delay;
        const prevTarget = stepTargets[i-1];
        setTimeout(() => advanceToStep(i, prevTarget), delay);
    }
    // Live counter on last step
    const lastIdx = steps.length - 1;
    const lastDelay = cumDelay + STEP_MS + stepTargets[lastIdx > 0 ? lastIdx-1 : 0] * 320;
    setTimeout(() => {
        const tEl = document.getElementById(`${id}-t${lastIdx}`);
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
    document.getElementById('inputChipsRow')?.classList.remove('hidden');
}

function scrollBottom() {
    const c = document.getElementById('chatScroll');
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
    body.innerHTML = searchHistory.slice(0,30).map((h,idx) => {
        const t = new Date(h.ts);
        const timeStr = t.toLocaleTimeString(LANG==='th'?'th':'en',{hour:'2-digit',minute:'2-digit'});
        const icon = typeIcon[h.type] || 'search';
        return `<div class="hp-item" onclick="sendMsg('${escAttr(h.query)}')">
            <div class="hp-item-content">
                <div class="hp-item-q"><i class="fas fa-${icon}" style="color:var(--accent);margin-right:6px"></i>${escHtml(h.query)}</div>
                <div class="hp-item-meta"><span>${timeStr}</span><span>${h.type||'search'}</span></div>
            </div>
            <button class="hp-item-del" onclick="event.stopPropagation();deleteHistory(${idx})"><i class="fas fa-times"></i></button>
        </div>`;
    }).join('') + (searchHistory.length > 1 ? `<button class="hp-clear-all" onclick="clearHistory()"><i class="fas fa-trash-alt"></i> ${L('ล้างทั้งหมด','Clear all')}</button>` : '');
}

function deleteHistory(idx) {
    searchHistory.splice(idx, 1);
    localStorage.setItem('chembot_history', JSON.stringify(searchHistory));
    renderHistory();
}

function clearHistory() {
    if (!searchHistory.length) return;
    if (!confirm(L('ล้างประวัติการค้นหาทั้งหมด?','Clear all search history?'))) return;
    searchHistory = [];
    localStorage.setItem('chembot_history', JSON.stringify(searchHistory));
    renderHistory();
}

function toggleHistory() {
    document.getElementById('histPanel').classList.toggle('open');
}

// ═══════════════════════════════════════════════
// RESPONSE INTERACTIONS
// ═══════════════════════════════════════════════
document.addEventListener('click', e => {
    if (e.target.classList.contains('sds-full-img')) {
        e.target.classList.toggle('fs');
    }
    if (e.target.closest('.loc-building-hdr')) {
        const hdr = e.target.closest('.loc-building-hdr');
        hdr.classList.toggle('open');
        const rooms = hdr.nextElementSibling;
        if (rooms) rooms.style.display = hdr.classList.contains('open') ? 'block' : 'none';
        return;
    }
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
      <a href="/v1/pages/login.php" class="lm-btn lm-btn-borrow"><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</a>
      <button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ปิด</button>
    </div>`, 'lm-accent-blue');
}
function _locHandleError(e) {
    if (e.message === '__AUTH__') { locGuestPrompt(); return; }
    _locHandleError(e);
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
    const map = {
        'GHS01':'💥','GHS02':'🔥','GHS03':'🔥','GHS04':'⚗️','GHS05':'⚠️',
        'GHS06':'☠️','GHS07':'❗','GHS08':'⚕️','GHS09':'🌿'
    };
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
            expBadge = days < 0
                ? '<span class="lm-badge lm-danger">หมดอายุ ' + exp + ' (' + Math.abs(days) + ' วันที่แล้ว)</span>'
                : days < 90
                    ? '<span class="lm-badge lm-warn">หมดอายุ ' + exp + ' (อีก ' + days + ' วัน)</span>'
                    : '<span class="lm-badge lm-fresh">หมดอายุ ' + exp + '</span>';
        }
        const hazards = (c.hazard_pictograms || []).map(_hazardBadge).join('');
        const html = `
        <div class="lm-chem-name">${escHtml(c.chemical_name || '-')}</div>
        <div class="lm-pills">
          ${c.cas_number ? '<span class="lm-pill cas">CAS: '+escHtml(c.cas_number)+'</span>' : ''}
          ${c.molecular_formula ? '<span class="lm-pill fml">'+escHtml(c.molecular_formula)+'</span>' : ''}
          ${c.grade ? '<span class="lm-pill grade">'+escHtml(c.grade)+'</span>' : ''}
          ${c.container_type ? '<span class="lm-pill type">'+escHtml(c.container_type)+'</span>' : ''}
        </div>
        <div class="lm-section">
          <div class="lm-section-hdr"><i class="fas fa-flask"></i> ปริมาณ</div>
          <div class="lm-qty-bar">
            <div class="lm-qty-fill" style="width:${pct}%;background:${pctColor}"></div>
          </div>
          <div class="lm-qty-row">
            <span class="lm-qty-big">${escHtml(String(c.current_quantity))} <small>${escHtml(c.quantity_unit||'')}</small></span>
            <span class="lm-qty-sub">จาก ${escHtml(String(c.initial_quantity||'-'))} ${escHtml(c.quantity_unit||'')} · เหลือ ${pct}%</span>
          </div>
        </div>
        <div class="lm-section">
          <div class="lm-section-hdr"><i class="fas fa-calendar-alt"></i> วันหมดอายุ</div>
          ${expBadge}
        </div>
        <div class="lm-section">
          <div class="lm-section-hdr"><i class="fas fa-map-marker-alt"></i> ตำแหน่งจัดเก็บ</div>
          <div class="lm-loc-text">${escHtml(c.location_text || c.building_name || '-')}</div>
        </div>
        ${c.owner_name ? `<div class="lm-section">
          <div class="lm-section-hdr"><i class="fas fa-user"></i> ผู้รับผิดชอบ</div>
          <div class="lm-owner-row">${escHtml(c.owner_name)} ${c.lab_name?'· '+escHtml(c.lab_name):''}</div>
        </div>` : ''}
        ${hazards ? `<div class="lm-section"><div class="lm-section-hdr"><i class="fas fa-radiation"></i> GHS Hazards</div><div class="lm-ghs-row">${hazards}</div></div>` : ''}
        ${c.qr_code ? `<div class="lm-section"><div class="lm-section-hdr"><i class="fas fa-qrcode"></i> QR / Barcode</div><div class="lm-mono">${escHtml(c.qr_code)}${c.bottle_code?' · '+escHtml(c.bottle_code):''}</div></div>` : ''}
        <div class="lm-actions">
          ${c.ar_data?.model_url ? '<button class="lm-btn lm-btn-3d" onclick="locView3D('+containerId+')"><i class="fas fa-cube"></i> ดู 3D Model</button>' : ''}
          <button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ปิด</button>
        </div>`;
        _locModalOpen('รายละเอียดภาชนะ', 'info-circle', html, 'lm-accent-blue');
    } catch(e) {
        _locHandleError(e);
    }
}

async function locBorrow(containerId) {
    _locModalOpen('กำลังโหลด…', 'circle-notch fa-spin', '<div class="lm-loading">กำลังโหลดข้อมูล…</div>', '');
    try {
        const c = await _locFetch(containerId);
        const maxQty = parseFloat(c.current_quantity || 0);
        const unit = escHtml(c.quantity_unit || '');
        const html = `
        <div class="lm-chem-name">${escHtml(c.chemical_name || '-')}</div>
        <div class="lm-pills">
          ${c.cas_number ? '<span class="lm-pill cas">CAS: '+escHtml(c.cas_number)+'</span>' : ''}
          <span class="lm-pill avail">คงเหลือ: ${escHtml(String(maxQty))} ${unit}</span>
        </div>
        <form id="borrowForm" class="lm-form">
          <div class="lm-field">
            <label>ปริมาณที่ต้องการเบิก <span class="lm-unit">${unit}</span></label>
            <div class="lm-input-row">
              <input type="number" id="bqty" min="0.001" max="${maxQty}" step="0.001" placeholder="0.000" required class="lm-input">
              <button type="button" class="lm-mini-btn" onclick="document.getElementById('bqty').value=${maxQty}">ทั้งหมด</button>
            </div>
            <div class="lm-qty-bar lm-qty-preview"><div id="bqtyBar" class="lm-qty-fill" style="width:0%;background:#10b981"></div></div>
          </div>
          <div class="lm-field">
            <label>วัตถุประสงค์ / โครงการ</label>
            <input type="text" id="bpurpose" placeholder="เช่น วิจัย LAB301 ปีการศึกษา 2568" class="lm-input">
          </div>
          <div class="lm-field">
            <label>วันที่คาดว่าจะคืน</label>
            <input type="date" id="bretdate" class="lm-input" min="${new Date().toISOString().slice(0,10)}">
          </div>
          <div id="borrowMsg" class="lm-msg" style="display:none"></div>
          <div class="lm-actions">
            <button type="submit" class="lm-btn lm-btn-borrow"><i class="fas fa-hand-holding"></i> ยืนยันเบิกใช้สาร</button>
            <button type="button" class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ยกเลิก</button>
          </div>
        </form>`;
        _locModalOpen('เบิกใช้สาร', 'hand-holding', html, 'lm-accent-green');
        document.getElementById('bqty').addEventListener('input', function() {
            const v = parseFloat(this.value) || 0;
            const pct = Math.min(100, Math.round(v / maxQty * 100));
            document.getElementById('bqtyBar').style.width = pct + '%';
            document.getElementById('bqtyBar').style.background = pct > 80 ? '#ef4444' : pct > 50 ? '#f59e0b' : '#10b981';
        });
        document.getElementById('borrowForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const qty = parseFloat(document.getElementById('bqty').value);
            const purpose = document.getElementById('bpurpose').value;
            const retDate = document.getElementById('bretdate').value;
            const msg = document.getElementById('borrowMsg');
            const btn = this.querySelector('[type=submit]');
            if (!qty || qty <= 0) { msg.style.display='flex'; msg.className='lm-msg lm-msg-err'; msg.textContent='กรุณาระบุปริมาณ'; return; }
            if (qty > maxQty) { msg.style.display='flex'; msg.className='lm-msg lm-msg-err'; msg.textContent='ปริมาณเกินที่มีอยู่'; return; }
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> กำลังดำเนินการ…';
            try {
                const r = await fetch('/v1/api/borrow.php?action=borrow', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ source_type:'container', source_id:containerId, quantity:qty, purpose:purpose, expected_return_date:retDate||null })
                });
                const d = await r.json();
                if (d.success) {
                    delete _locCache[containerId];
                    const txn = d.data;
                    const statusTh = txn.status === 'pending' ? '⏳ รอการอนุมัติ' : '✅ สำเร็จ';
                    document.getElementById('locModalBody').innerHTML = `
                    <div class="lm-success">
                      <div class="lm-success-icon">✅</div>
                      <div class="lm-success-title">เบิกใช้สารสำเร็จ</div>
                      <div class="lm-success-detail">เลขที่: <strong>${escHtml(txn.txn_number||'-')}</strong></div>
                      <div class="lm-success-detail">ปริมาณ: <strong>${qty} ${unit}</strong></div>
                      <div class="lm-success-detail">สถานะ: <strong>${statusTh}</strong></div>
                      ${txn.status==='pending'?'<div class="lm-info-note"><i class="fas fa-info-circle"></i> รายการนี้ต้องรอการอนุมัติจากเจ้าของสาร</div>':''}
                    </div>
                    <div class="lm-actions"><button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-check"></i> ปิด</button></div>`;
                } else {
                    msg.style.display='flex'; msg.className='lm-msg lm-msg-err'; msg.textContent = d.error||'เกิดข้อผิดพลาด';
                    btn.disabled=false; btn.innerHTML='<i class="fas fa-hand-holding"></i> ยืนยันเบิกใช้สาร';
                }
            } catch(err) {
                msg.style.display='flex'; msg.className='lm-msg lm-msg-err'; msg.textContent='Network error: '+err.message;
                btn.disabled=false; btn.innerHTML='<i class="fas fa-hand-holding"></i> ยืนยันเบิกใช้สาร';
            }
        });
    } catch(e) {
        _locHandleError(e);
    }
}

async function locEditItem(containerId) {
    _locModalOpen('กำลังโหลด…', 'circle-notch fa-spin', '<div class="lm-loading">กำลังโหลดข้อมูล…</div>', '');
    try {
        const c = await _locFetch(containerId);
        const html = `
        <div class="lm-chem-name">${escHtml(c.chemical_name || '-')}</div>
        <form id="editForm" class="lm-form">
          <div class="lm-field">
            <label>ปริมาณปัจจุบัน <span class="lm-unit">${escHtml(c.quantity_unit||'')}</span></label>
            <input type="number" id="eqty" value="${escHtml(String(c.current_quantity||0))}" min="0" step="0.001" class="lm-input">
          </div>
          <div class="lm-field">
            <label>วันหมดอายุ</label>
            <input type="date" id="eexp" value="${escHtml(c.expiry_date||'')}" class="lm-input">
          </div>
          <div class="lm-field">
            <label>สถานะคุณภาพ</label>
            <select id="eqstat" class="lm-input lm-select">
              <option value="good" ${c.quality_status==='good'?'selected':''}>✅ ดี (Good)</option>
              <option value="degraded" ${c.quality_status==='degraded'?'selected':''}>⚠️ เสื่อมสภาพ (Degraded)</option>
              <option value="contaminated" ${c.quality_status==='contaminated'?'selected':''}>☣️ ปนเปื้อน (Contaminated)</option>
              <option value="expired" ${c.quality_status==='expired'?'selected':''}>❌ หมดอายุ (Expired)</option>
            </select>
          </div>
          <div class="lm-field">
            <label>หมายเหตุ</label>
            <textarea id="enotes" rows="2" class="lm-input lm-textarea" placeholder="หมายเหตุเพิ่มเติม…">${escHtml(c.notes||'')}</textarea>
          </div>
          <div id="editMsg" class="lm-msg" style="display:none"></div>
          <div class="lm-actions">
            <button type="submit" class="lm-btn lm-btn-edit"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
            <button type="button" class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ยกเลิก</button>
          </div>
        </form>`;
        _locModalOpen('แก้ไขข้อมูลภาชนะ', 'pen', html, 'lm-accent-blue');
        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const msg = document.getElementById('editMsg');
            const btn = this.querySelector('[type=submit]');
            btn.disabled=true; btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> กำลังบันทึก…';
            try {
                const payload = {
                    current_quantity: parseFloat(document.getElementById('eqty').value),
                    expiry_date: document.getElementById('eexp').value || null,
                    quality_status: document.getElementById('eqstat').value,
                    notes: document.getElementById('enotes').value
                };
                const r = await fetch('/v1/api/containers.php?id=' + containerId, {
                    method: 'PUT', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify(payload)
                });
                const d = await r.json();
                if (d.success) {
                    delete _locCache[containerId];
                    document.getElementById('locModalBody').innerHTML = `
                    <div class="lm-success">
                      <div class="lm-success-icon">✅</div>
                      <div class="lm-success-title">บันทึกสำเร็จ</div>
                      <div class="lm-success-detail">อัปเดตข้อมูลภาชนะเรียบร้อยแล้ว</div>
                    </div>
                    <div class="lm-actions"><button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-check"></i> ปิด</button></div>`;
                } else {
                    msg.style.display='flex'; msg.className='lm-msg lm-msg-err'; msg.textContent=d.error||'เกิดข้อผิดพลาด';
                    btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> บันทึกการแก้ไข';
                }
            } catch(err) {
                msg.style.display='flex'; msg.className='lm-msg lm-msg-err'; msg.textContent='Network error';
                btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> บันทึกการแก้ไข';
            }
        });
    } catch(e) {
        _locHandleError(e);
    }
}

async function locView3D(containerId) {
    _locModalOpen('กำลังโหลด…', 'circle-notch fa-spin', '<div class="lm-loading">กำลังโหลดข้อมูล…</div>', '');
    try {
        const c = await _locFetch(containerId);
        const ar = c.ar_data || {};
        const modelUrl = ar.model_url;
        if (!modelUrl) {
            document.getElementById('locModalBody').innerHTML = `
            <div class="lm-chem-name">${escHtml(c.chemical_name||'-')}</div>
            <div class="lm-error" style="margin-top:16px"><i class="fas fa-cube"></i> ไม่พบ 3D Model สำหรับภาชนะนี้<br><small style="opacity:.7">${escHtml(c.container_type||'')} · ${escHtml(c.container_material||'')}</small></div>
            <div class="lm-actions"><button class="lm-btn lm-btn-close" onclick="locModalClose()">ปิด</button></div>`;
            return;
        }
        const html = `
        <div class="lm-chem-name">${escHtml(c.chemical_name||'-')}<span class="lm-3d-badge">3D</span></div>
        <div class="lm-3d-wrap">
          <model-viewer src="${escHtml(modelUrl)}"
            auto-rotate camera-controls shadow-intensity="1.2"
            camera-orbit="45deg 75deg 105%" environment-image="neutral"
            ar ar-modes="webxr scene-viewer quick-look"
            style="width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#8b5cf6;--progress-bar-height:3px">
            <div class="lm-3d-loading" slot="progress-bar"><i class="fas fa-circle-notch fa-spin"></i> โหลด 3D…</div>
          </model-viewer>
        </div>
        <div class="lm-3d-hint"><i class="fas fa-mouse-pointer"></i> ลากเพื่อหมุน · เลื่อนล้อเพื่อซูม
          ${ar.model_type === 'glb' ? '&nbsp;·&nbsp;<i class="fas fa-mobile-alt"></i> รองรับ AR' : ''}
        </div>
        <div class="lm-actions"><button class="lm-btn lm-btn-close" onclick="locModalClose()"><i class="fas fa-times"></i> ปิด</button></div>`;
        _locModalOpen('3D Model: ' + (c.chemical_name||''), 'cube', html, 'lm-accent-purple');
        if (!document.querySelector('script[src*="model-viewer"]')) {
            const s = document.createElement('script'); s.type='module';
            s.src='https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js';
            document.head.appendChild(s);
        }
    } catch(e) {
        _locHandleError(e);
    }
}

function locSetExpiry(containerId, btn) {
    const input = document.createElement('input');
    input.type = 'date'; input.min = new Date().toISOString().slice(0,10);
    input.style.cssText = 'position:absolute;opacity:0;pointer-events:none;width:0;height:0';
    document.body.appendChild(input);
    input.addEventListener('change', async function() {
        const val = input.value;
        document.body.removeChild(input);
        if (!val) return;
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
        try {
            const r = await fetch('/v1/api/containers.php?id=' + containerId, {
                method: 'PUT', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({expiry_date: val})
            });
            const d = await r.json();
            if (d.success) {
                delete _locCache[containerId];
                btn.classList.remove('nodate', 'loc-set-exp-btn');
                const days = Math.round((new Date(val) - new Date()) / 86400000);
                btn.classList.add(days < 0 ? 'danger' : days < 90 ? 'warn' : 'fresh');
                btn.innerHTML = val + (days < 0 ? ' ⚠' : days < 90 ? ' ⚡' : '');
                btn.onclick = null;
            } else {
                btn.innerHTML = origHtml;
                alert('Error: ' + (d.error || 'Failed'));
            }
        } catch(e) { btn.innerHTML = origHtml; alert('Network error'); }
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

// ═══════════════════════════════════════════════
// 3D MODEL VIEWER
// ═══════════════════════════════════════════════
function attachModelViewers(root) {
    const slots = (root || document).querySelectorAll('.glb-mv-slot:not([data-attached])');
    slots.forEach(slot => {
        slot.setAttribute('data-attached', '1');
        const src = slot.dataset.src;
        if (!src) return;
        const mv = document.createElement('model-viewer');
        mv.setAttribute('src', src);
        if (slot.dataset.usdz) mv.setAttribute('ios-src', slot.dataset.usdz);
        mv.setAttribute('auto-rotate','');mv.setAttribute('camera-controls','');
        mv.setAttribute('camera-orbit','45deg 75deg auto');mv.setAttribute('shadow-intensity','1');
        mv.setAttribute('environment-image','neutral');mv.setAttribute('interaction-prompt','auto');
        mv.setAttribute('ar','');mv.setAttribute('ar-modes','webxr scene-viewer quick-look');
        mv.style.cssText='width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#6C5CE7;--progress-bar-height:3px';
        mv.addEventListener('load', () => { const l = slot.querySelector('.glb-mv-loading'); if(l) l.style.display='none'; });
        slot.appendChild(mv);
    });
}
const _glbObserver = new MutationObserver(muts => {
    muts.forEach(m => m.addedNodes.forEach(n => {
        if (n.nodeType !== 1) return;
        if (n.classList?.contains('glb-mv-slot')) attachModelViewers(n.parentElement);
        else if (n.querySelector?.('.glb-mv-slot')) attachModelViewers(n);
    }));
});

let _glbOvAutoRotate = true;
function openGLBOverlay(src, label) {
    const ov = document.getElementById('glbOv');
    const body = document.getElementById('glbOvBody');
    if (!ov) return;
    const old = body.querySelector('model-viewer');
    if (old) old.remove();
    const mv = document.createElement('model-viewer');
    mv.setAttribute('src', src);mv.setAttribute('auto-rotate','');mv.setAttribute('camera-controls','');
    mv.setAttribute('camera-orbit','0deg 75deg 105%');mv.setAttribute('shadow-intensity','1.2');
    mv.setAttribute('environment-image','neutral');mv.setAttribute('ar','');
    mv.setAttribute('ar-modes','webxr scene-viewer quick-look');
    mv.style.cssText='width:100%;height:100%;--poster-color:transparent;--progress-bar-color:#6C5CE7;--progress-bar-height:3px';
    body.insertBefore(mv, body.firstChild);
    document.getElementById('glbOvTitle').textContent = label || '3D Model';
    document.getElementById('glbOvDlBtn').href = src;
    _glbOvAutoRotate = true;
    document.getElementById('glbOvAutoRotateBtn').querySelector('i').className = 'fas fa-sync-alt';
    ov.classList.add('show');
}
function closeGLBOverlay() { document.getElementById('glbOv').classList.remove('show'); }
function glbOvToggleAutoRotate() {
    _glbOvAutoRotate = !_glbOvAutoRotate;
    const mv = document.getElementById('glbOvBody').querySelector('model-viewer');
    if (mv) { if (_glbOvAutoRotate) mv.setAttribute('auto-rotate',''); else mv.removeAttribute('auto-rotate'); }
    document.getElementById('glbOvAutoRotateBtn').querySelector('i').className = _glbOvAutoRotate ? 'fas fa-sync-alt' : 'fas fa-pause';
}
function glbOvResetCamera() {
    const mv = document.getElementById('glbOvBody').querySelector('model-viewer');
    if (mv) { mv.setAttribute('camera-orbit','0deg 75deg 105%'); mv.resetTurntableRotation?.(); }
}

// ═══════════════════════════════════════════════
// STORAGE BROWSER
// ═══════════════════════════════════════════════
const SBM_LANG = '<?php echo $lang; ?>';
const T = (th, en) => SBM_LANG === 'th' ? th : en;
let sbmState = { level:'buildings', buildingId:null, buildingCode:null, buildingName:null, roomId:null, roomCode:null, allContainers:[] };

function openStorageBrowser() {
    document.getElementById('sbmOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
    sbmState = { level:'buildings', buildingId:null, buildingCode:null, buildingName:null, roomId:null, roomCode:null, allContainers:[] };
    sbmLoadBuildings();
}
function closeStorageBrowser() { document.getElementById('sbmOverlay').classList.remove('show'); document.body.style.overflow = ''; }
function sbmBgClick(e) { if (e.target === document.getElementById('sbmOverlay')) closeStorageBrowser(); }

function sbmLoadBuildings() {
    sbmSetBreadcrumb([{label: T('อาคารทั้งหมด','All Buildings')}]);
    document.getElementById('sbmSubtitle').textContent = T('เลือกอาคารเพื่อดูรายละเอียด','Select a building to explore');
    sbmShowLoading();
    fetch('/v1/api/ai_assistant.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_buildings',lang:SBM_LANG})})
    .then(r=>r.json()).then(d=>{
        if (!d.success || !d.data?.length) { sbmShowEmpty(T('ไม่พบข้อมูลอาคาร','No buildings found')); return; }
        sbmRenderBuildings(d.data);
    }).catch(()=>sbmShowEmpty(T('เกิดข้อผิดพลาด','Error loading data')));
}
function sbmRenderBuildings(buildings) {
    const cols = {'F0':'#6b7280','F1':'#3b82f6','F4':'#8b5cf6','F5':'#f59e0b','F6':'#06b6d4','F7':'#10b981','F10':'#ec4899','F11':'#f97316','F12':'#dc2626'};
    let html = '<div class="sbm-bld-grid">';
    buildings.forEach(b => {
        const col = cols[b.code] || '#10b981';
        html += `<div class="sbm-bld-card" onclick="sbmLoadRooms(${b.id},'${escAttr(b.code)}','${escAttr(b.name)}')" style="--bld-col:${col}">
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
function sbmLoadRooms(buildingId, code, name) {
    sbmState.buildingId=buildingId; sbmState.buildingCode=code; sbmState.buildingName=name;
    sbmSetBreadcrumb([{label:T('อาคารทั้งหมด','All Buildings'),fn:'sbmLoadBuildings()'},{label:code+' — '+name}]);
    document.getElementById('sbmSubtitle').textContent = T('ห้องใน ','Rooms in ')+code;
    sbmShowLoading();
    fetch('/v1/api/ai_assistant.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_rooms',building_id:buildingId,lang:SBM_LANG})})
    .then(r=>r.json()).then(d=>{
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
            <div class="sbm-room-info"><div class="sbm-room-code">${escHtml(r.room_number)}${floor}</div>${nm?`<div class="sbm-room-name">${nm}</div>`:''}</div>
            <span class="sbm-room-count">${Number(r.bottle_count).toLocaleString()} ${T('ขวด','')}</span>
            <i class="fas fa-chevron-right sbm-room-arr"></i>
        </div>`;
    });
    html += '</div>';
    document.getElementById('sbmBody').innerHTML = html;
}
function sbmLoadContainers(roomId, roomCode) {
    sbmState.roomId=roomId; sbmState.roomCode=roomCode;
    sbmSetBreadcrumb([
        {label:T('อาคารทั้งหมด','All Buildings'),fn:'sbmLoadBuildings()'},
        {label:sbmState.buildingCode,fn:`sbmLoadRooms(${sbmState.buildingId},'${escAttr(sbmState.buildingCode)}','${escAttr(sbmState.buildingName)}')`},
        {label:roomCode}
    ]);
    document.getElementById('sbmSubtitle').textContent = T('รายการสารเคมีใน ','Chemicals in ')+roomCode;
    sbmShowLoading();
    fetch('/v1/api/ai_assistant.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'get_room_containers',room_id:roomId,lang:SBM_LANG})})
    .then(r=>r.json()).then(d=>{
        if (!d.success || !d.data?.length) { sbmShowEmpty(T('ไม่พบสารเคมีในห้องนี้','No chemicals in this room')); return; }
        sbmState.allContainers=d.data; sbmRenderContainers(d.data);
    }).catch(()=>sbmShowEmpty(T('เกิดข้อผิดพลาด','Error loading data')));
}
function sbmRenderContainers(rows) {
    let html = `<div class="sbm-ctr-hdr">
        <div class="sbm-ctr-title"><i class="fas fa-vial" style="color:var(--ok)"></i><span>${T('รายการขวดสาร','Container List')}</span><span class="sbm-ctr-badge">${rows.length}${rows.length>=200?'+':''}</span></div>
        <input class="sbm-ctr-search" type="text" placeholder="${T('ค้นหาชื่อสาร / CAS / barcode...','Search name / CAS / barcode...')}" oninput="sbmFilterContainers(this.value)">
    </div><div id="sbmTblWrap">${sbmBuildTable(rows)}</div>`;
    document.getElementById('sbmBody').innerHTML = html;
}
function sbmBuildTable(rows) {
    const today = new Date();
    let t = `<table class="sbm-tbl"><thead><tr>
        <th>#</th><th>${T('ชื่อสารเคมี','Chemical')}</th><th>${T('Barcode','Barcode')}</th>
        <th>${T('ปริมาณคงเหลือ','Qty')}</th><th>${T('ประเภท','Type')}</th><th>${T('เกรด','Grade')}</th>
        <th>${T('รับเข้า','Received')}</th><th>${T('ผู้ดูแล','Custodian')}</th>
    </tr></thead><tbody>`;
    rows.forEach((r,i) => {
        const expCls = r.expiry_date ? (() => { const d=new Date(r.expiry_date); return (d-today)/86400000<90?'expiry-warn':'expiry-ok'; })() : '';
        const recvd = r.received_date ? r.received_date.substring(0,4) : '—';
        const typeLabel = {'bottle':T('ขวด','Bottle'),'flask':T('ฟลาสก์','Flask'),'canister':T('กระป๋อง','Canister'),'cylinder':T('ถัง','Cylinder')}[r.container_type] || r.container_type || '—';
        const ownerName = r.owner_name ? escHtml(r.owner_name.trim()) : '';
        const ownerPhone = r.owner_phone ? escHtml(r.owner_phone.trim()) : '';
        const ownerCell = ownerName||ownerPhone
            ? `<div style="font-size:11px;line-height:1.6">`+(ownerName?`<div style="font-weight:700;color:#1e293b">${ownerName}</div>`:'')+(ownerPhone?`<div style="color:#0369a1"><a href="tel:${ownerPhone}" style="color:inherit">${ownerPhone}</a></div>`:'')+`</div>`
            : '<span style="color:#94a3b8">—</span>';
        t += `<tr><td style="color:var(--c3);width:28px">${i+1}</td>
            <td><div class="chem-nm">${escHtml(r.chem_name)}</div><div class="cas-nm">${escHtml(r.cas_number||'')}</div></td>
            <td><div class="qr-code">${escHtml(r.qr_code||'')}</div></td>
            <td><span class="qty-val">${r.current_quantity!=null?Number(r.current_quantity).toLocaleString():'—'} ${escHtml(r.quantity_unit||'')}</span></td>
            <td><span class="type-pill">${typeLabel}</span></td>
            <td>${escHtml(r.grade||'—')}</td>
            <td class="${expCls}">${recvd}</td>
            <td>${ownerCell}</td></tr>`;
    });
    t += '</tbody></table>';
    return t;
}
function sbmFilterContainers(q) {
    const ql = q.toLowerCase();
    const filtered = ql ? sbmState.allContainers.filter(r =>
        (r.chem_name||'').toLowerCase().includes(ql)||(r.cas_number||'').toLowerCase().includes(ql)||(r.qr_code||'').toLowerCase().includes(ql)
    ) : sbmState.allContainers;
    document.getElementById('sbmTblWrap').innerHTML = filtered.length ? sbmBuildTable(filtered) : `<div class="sbm-empty"><i class="fas fa-search"></i><p>${T('ไม่พบผลลัพธ์','No results found')}</p></div>`;
}
function sbmShowLoading() { document.getElementById('sbmBody').innerHTML=`<div class="sbm-loading"><div class="sbm-spinner"></div>${T('กำลังโหลด...','Loading...')}</div>`; }
function sbmShowEmpty(msg) { document.getElementById('sbmBody').innerHTML=`<div class="sbm-empty"><i class="fas fa-box-open"></i><p>${msg}</p></div>`; }
function sbmSetBreadcrumb(crumbs) {
    let html = '';
    crumbs.forEach((c,i) => {
        const isLast = i===crumbs.length-1;
        if (i>0) html+='<span class="sbm-crumb-sep"><i class="fas fa-chevron-right" style="font-size:9px"></i></span>';
        html+=`<div class="sbm-crumb${isLast?' active':''}">`+(c.fn?`<a onclick="${c.fn}">${escHtml(c.label)}</a>`:`<span>${escHtml(c.label)}</span>`)+`</div>`;
    });
    document.getElementById('sbmBreadcrumb').innerHTML = html;
}
document.addEventListener('keydown', e => { if (e.key==='Escape') { closeStorageBrowser(); closeGLBOverlay(); locModalClose(); } });
</script>
<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.4.0/model-viewer.min.js"></script>

<?php Layout::endContent(); ?>
