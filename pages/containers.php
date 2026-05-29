<?php
/**
 * Containers Page — Pro Edition
 * Chemical Bottle/Container Management
 * 
 * Features:
 *   Hero banner + Stats row
 *   Tabs: All / My
 *   Views: Table / Grid / Compact / Analytics
 *   Detail modal with 3D viewer + AR + history
 *   Add bottle form (full wizard)
 *   Role-based access control
 */
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang    = I18n::getCurrentLang();
$role    = $user['role_name'];
$uid     = (int)$user['id'];
$isAdmin = $role === 'admin';
$isCeo   = $role === 'ceo';
$isLab   = $role === 'lab_manager';
$canEdit = in_array($role, ['admin','lab_manager','user']);
$canSeeAll = $isAdmin || $isCeo;
$action  = $_GET['action'] ?? '';
$displayName = $user['full_name_th'] ?? trim(($user['first_name']??'').' '.($user['last_name']??''));
$userInitial = mb_substr(preg_replace('/^(นาย|นางสาว|นาง|ดร\.)\s*/u', '', $displayName), 0, 1, 'UTF-8');
Layout::head($lang==='th' ? 'จัดการขวดสารเคมี' : 'Chemical Containers');
?>
<style>
:root{--ctn-r:14px;--ctn-rs:10px;--ctn-sh:0 1px 6px rgba(0,0,0,.06);--ctn-shm:0 4px 20px rgba(0,0,0,.08)}

.ctn-src{display:inline-flex;align-items:center;gap:3px;font-size:8px;font-weight:700;padding:1px 6px;border-radius:4px;letter-spacing:.3px;text-transform:uppercase;vertical-align:middle}
.ctn-src-container{background:#dbeafe;color:#2563eb}
.ctn-src-stock{background:#fef3c7;color:#92400e}

/* ═══ Hero ═══ */
.ctn-hero{background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 50%,#3b82f6 100%);border-radius:var(--ctn-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.ctn-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.ctn-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.ctn-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px;position:relative}
.ctn-hero-info p{font-size:12px;opacity:.85;margin:0;position:relative}
.ctn-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0}
.ctn-hero-c{text-align:center;position:relative}
.ctn-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.ctn-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}

/* ═══ Stats ═══ */
.ctn-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.ctn-stat{background:#fff;border-radius:var(--ctn-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--ctn-sh);border:1px solid var(--border);transition:all .15s;cursor:pointer}
.ctn-stat:hover{transform:translateY(-2px);box-shadow:var(--ctn-shm)}
.ctn-stat.af{border-color:var(--accent);background:#f0fdf4}
.ctn-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.ctn-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.ctn-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ═══ Tabs ═══ */
.ctn-tabs{display:inline-flex;background:#f1f5f9;border-radius:var(--ctn-rs);padding:3px}
.ctn-tab{padding:8px 20px;font-size:12px;font-weight:600;color:var(--c3);border-radius:8px;cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:6px}
.ctn-tab:hover{color:var(--c1)}
.ctn-tab.active{background:#fff;color:#2563eb;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.ctn-tab .bg{font-size:9px;padding:2px 7px;border-radius:10px;font-weight:700;background:#e2e8f0;color:var(--c3)}
.ctn-tab.active .bg{background:#2563eb;color:#fff}

/* ═══ My Banner ═══ */
.ctn-my{background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:var(--ctn-r);padding:16px 20px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:16px}
.ctn-my-av{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px}
.ctn-my h3{font-size:14px;font-weight:700;margin:0}
.ctn-my p{font-size:11px;opacity:.8;margin:2px 0 0}

/* ═══ Toolbar ═══ */
.ctn-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}
.ctn-search{flex:1;min-width:220px;position:relative}
.ctn-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:var(--ctn-rs);font-size:13px;background:#fff;color:var(--c1);transition:border .15s}
.ctn-search input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.ctn-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:13px}
.ctn-btn{padding:8px 16px;border:none;border-radius:var(--ctn-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap}
.ctn-btn-p{background:#2563eb;color:#fff}.ctn-btn-p:hover{background:#1d4ed8}
.ctn-btn-o{background:#fff;color:#2563eb;border:1.5px solid #2563eb}.ctn-btn-o:hover{background:#2563eb;color:#fff}
.ctn-btn-g{background:transparent;color:var(--c3);border:1.5px solid var(--border)}.ctn-btn-g:hover{border-color:#2563eb;color:#2563eb}
.ctn-btn-d{background:#dc2626;color:#fff}.ctn-btn-d:hover{background:#b91c1c}
.ctn-btn-s{padding:5px 10px;font-size:11px}
.ctn-btn-teal{background:#0d9488;color:#fff}.ctn-btn-teal:hover{background:#0f766e}

/* ═══ View Switcher ═══ */
.ctn-vw{display:flex;border:1.5px solid var(--border);border-radius:var(--ctn-rs);overflow:hidden}
.ctn-vw button{padding:7px 11px;border:none;background:#fff;color:var(--c3);cursor:pointer;font-size:12px;transition:all .12s;display:flex;align-items:center;gap:4px}
.ctn-vw button+button{border-left:1px solid var(--border)}
.ctn-vw button.active{background:#2563eb;color:#fff}
.ctn-vw button:hover:not(.active){background:#f8fafc}

/* ═══ Filter Panel ═══ */
.ctn-fp{max-height:0;overflow:hidden;transition:max-height .25s ease,margin .25s,padding .25s;background:#fff;border:1.5px solid transparent;border-radius:var(--ctn-r);margin-bottom:0}
.ctn-fp.show{max-height:300px;border-color:var(--border);padding:16px;margin-bottom:14px;box-shadow:var(--ctn-sh)}
.ctn-fg2{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
.ctn-fl label{font-size:10px;font-weight:700;color:var(--c3);display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px}
.ctn-fl select{width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff;color:var(--c1)}
.ctn-fl select:focus{outline:none;border-color:#2563eb}

/* ═══ Table View ═══ */
.ctn-tw{overflow-x:auto;border-radius:var(--ctn-r);border:1px solid var(--border);background:#fff;box-shadow:var(--ctn-sh)}
.ctn-t{width:100%;border-collapse:collapse;font-size:12px}
.ctn-t th{background:#f8fafc;padding:10px 12px;text-align:left;font-weight:700;color:var(--c3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);white-space:nowrap;cursor:pointer;user-select:none;position:sticky;top:0;z-index:1}
.ctn-t th:hover{color:#2563eb}
.ctn-t td{padding:10px 12px;border-bottom:1px solid #f1f5f9;color:var(--c1);vertical-align:middle}
.ctn-t tbody tr{transition:background .1s;cursor:pointer}
.ctn-t tbody tr:hover{background:#eff6ff}
.ctn-t tbody tr.me{background:#eff6ff}

/* ═══ Grid View ═══ */
.ctn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:12px}
.ctn-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--ctn-r);overflow:hidden;transition:all .18s;cursor:pointer;position:relative}
.ctn-card:hover{border-color:#2563eb;box-shadow:var(--ctn-shm);transform:translateY(-2px)}
.ctn-card.me{border-left:3px solid #2563eb}

/* ── 3D Preview Area ── */
.ctn-card-3d{position:relative;width:100%;height:220px;background:linear-gradient(135deg,#0f0f1a 0%,#1a1a2e 50%,#16213e 100%);overflow:hidden}
.ctn-card-3d iframe{width:100%;height:100%;border:none;pointer-events:none;transition:opacity .3s}
.ctn-card-3d.interactive iframe{pointer-events:auto}
.ctn-card-3d-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;background:rgba(0,0,0,.02);transition:all .2s;cursor:pointer}
.ctn-card-3d.interactive .ctn-card-3d-overlay{display:none}
.ctn-card-3d-overlay:hover{background:rgba(0,0,0,.18)}
.ctn-card-3d-play{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.12);backdrop-filter:blur(8px);border:2px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;transition:all .2s;opacity:0}
.ctn-card-3d-overlay:hover .ctn-card-3d-play{opacity:1;transform:scale(1.08)}

/* Top-left badge row */
.ctn-card-3d-top{position:absolute;top:8px;left:8px;right:8px;display:flex;align-items:flex-start;justify-content:space-between;z-index:4;pointer-events:none}
.ctn-card-3d-top>*{pointer-events:auto}
.ctn-card-3d-badge{background:linear-gradient(135deg,#6C5CE7,#a855f7);color:#fff;font-size:8px;padding:3px 8px;border-radius:6px;font-weight:700;display:flex;align-items:center;gap:4px;letter-spacing:.3px;flex-shrink:0}
.ctn-card-3d-signal{font-size:8px;padding:3px 8px;border-radius:6px;font-weight:700;display:flex;align-items:center;gap:3px;flex-shrink:0}
.ctn-card-3d-signal.danger{background:rgba(220,38,38,.85);color:#fff}
.ctn-card-3d-signal.warning{background:rgba(245,158,11,.85);color:#fff}

/* Bottom info HUD */
.ctn-card-3d-hud{position:absolute;bottom:0;left:0;right:0;z-index:3;padding:28px 10px 8px;background:linear-gradient(to top,rgba(0,0,0,.75) 0%,rgba(0,0,0,.35) 60%,transparent 100%);display:flex;align-items:flex-end;gap:6px;pointer-events:none}
.ctn-card-3d-hud>*{pointer-events:auto}
.ctn-card-3d-info{flex:1;min-width:0}
.ctn-card-3d-chemname{font-size:11px;font-weight:700;color:#fff;line-height:1.25;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;text-shadow:0 1px 3px rgba(0,0,0,.5)}
.ctn-card-3d-meta{display:flex;flex-wrap:wrap;gap:4px;margin-top:3px}
.ctn-card-3d-chip{font-size:7.5px;padding:2px 6px;border-radius:4px;font-weight:600;background:rgba(255,255,255,.13);color:rgba(255,255,255,.85);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,.08);white-space:nowrap}
.ctn-card-3d-chip.cas{color:#a5b4fc;background:rgba(99,102,241,.2);border-color:rgba(99,102,241,.25)}
.ctn-card-3d-chip.formula{color:#86efac;background:rgba(34,197,94,.15);border-color:rgba(34,197,94,.2)}
.ctn-card-3d-chip.qty{color:#fde68a;background:rgba(245,158,11,.18);border-color:rgba(245,158,11,.2)}
.ctn-card-3d-chip.haz{color:#fca5a5;background:rgba(220,38,38,.18);border-color:rgba(220,38,38,.2)}

/* GHS mini icons in 3D area */
.ctn-card-3d-ghs{display:flex;gap:2px;margin-top:3px}
.ctn-card-3d-ghs span{width:16px;height:16px;border-radius:3px;display:inline-flex;align-items:center;justify-content:center;font-size:7px;background:rgba(220,38,38,.2);border:1px solid rgba(220,38,38,.25);color:#fca5a5}

/* Control buttons */
.ctn-card-3d-controls{display:flex;gap:4px;flex-shrink:0;align-items:flex-end}
.ctn-card-3d-ctrl{width:28px;height:28px;border-radius:7px;background:rgba(0,0,0,.45);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.1);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;cursor:pointer;transition:all .15s;opacity:0}
.ctn-card:hover .ctn-card-3d-ctrl{opacity:1}
.ctn-card-3d-ctrl:hover{background:rgba(108,92,231,.8);border-color:rgba(255,255,255,.3);transform:scale(1.1)}
.ctn-card-3d-ctrl.ar-btn{background:linear-gradient(135deg,rgba(13,148,136,.85),rgba(20,184,166,.85));border-color:rgba(20,184,166,.3);opacity:1}
.ctn-card-3d-ctrl.ar-btn:hover{background:linear-gradient(135deg,#0f766e,#0d9488);transform:scale(1.1);box-shadow:0 2px 10px rgba(13,148,136,.5)}

/* Quantity ring indicator */
.ctn-card-3d-ring{position:absolute;top:8px;right:8px;z-index:4}
.ctn-card-3d-ring svg{width:32px;height:32px;filter:drop-shadow(0 1px 3px rgba(0,0,0,.4))}
.ctn-card-3d-ring .ring-bg{fill:none;stroke:rgba(255,255,255,.12);stroke-width:3}
.ctn-card-3d-ring .ring-fg{fill:none;stroke-width:3;stroke-linecap:round;transition:stroke-dashoffset .6s ease}
.ctn-card-3d-ring .ring-txt{fill:#fff;font-size:7.5px;font-weight:800;text-anchor:middle;dominant-baseline:central;text-shadow:0 1px 2px rgba(0,0,0,.5)}

/* Placeholder for cards without 3D */
.ctn-card-3d-placeholder{position:relative;width:100%;height:80px;background:linear-gradient(135deg,#f8fafc,#f1f5f9);display:flex;align-items:center;justify-content:center;gap:8px;color:var(--c3);font-size:11px;font-weight:500}
.ctn-card-3d-placeholder i{font-size:24px;opacity:.2}

.ctn-card-hd{display:flex;align-items:flex-start;gap:10px;padding:14px 16px 0}
.ctn-card-ic{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.ctn-card-nm{font-size:13px;font-weight:700;color:var(--c1);line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.ctn-card-cd{font-size:10px;color:var(--c2);font-family:'Courier New',monospace;margin-top:2px;background:#f1f5f9;padding:1px 6px;border-radius:3px;display:inline-block;letter-spacing:0.3px}
.ctn-card-bd{padding:10px 16px 14px}
.ctn-card-tg{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px}
.ctn-card-tag{font-size:9px;padding:2px 7px;border-radius:6px;font-weight:600}
.ctn-card-bar{height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-bottom:6px}
.ctn-card-bf{height:100%;border-radius:3px;transition:width .3s}
.ctn-card-ft{display:flex;justify-content:space-between;font-size:10px;color:var(--c3)}
.ctn-card-row{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--c2);margin-top:4px}
.ctn-card-row i{width:14px;text-align:center;color:var(--c3);font-size:10px}
.ctn-3d-badge{position:absolute;top:10px;right:10px;background:linear-gradient(135deg,#6C5CE7,#a855f7);color:#fff;font-size:9px;padding:3px 8px;border-radius:6px;font-weight:700;display:flex;align-items:center;gap:4px}

/* ═══ Compact View ═══ */
.ctn-compact{display:flex;flex-direction:column;gap:4px}
.ctn-cr{display:flex;align-items:center;gap:10px;padding:8px 14px;background:#fff;border-radius:8px;border:1px solid var(--border);cursor:pointer;transition:all .1s;font-size:12px}
.ctn-cr:hover{background:#eff6ff;border-color:#2563eb}
.ctn-cr.me{border-left:3px solid #2563eb}
.ctn-cn{flex:1;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ctn-cc{color:var(--c2);font-size:10px;width:100px;flex-shrink:0;font-family:'Courier New',monospace;letter-spacing:0.3px}
.ctn-cb{width:50px;height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden;flex-shrink:0}
.ctn-cb div{height:100%;border-radius:2px}
.ctn-cp{font-weight:700;font-size:11px;width:35px;text-align:right;flex-shrink:0}
.ctn-co{font-size:10px;color:var(--c3);width:80px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ═══ Analytics View ═══ */
.ctn-an{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}
.ctn-ac{background:#fff;border-radius:var(--ctn-r);border:1px solid var(--border);padding:18px;box-shadow:var(--ctn-sh)}
.ctn-at{font-size:12px;font-weight:700;color:var(--c1);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.ctn-at i{color:#2563eb}
.ctn-bc{display:flex;flex-direction:column;gap:6px}
.ctn-br{display:flex;align-items:center;gap:8px;font-size:11px}
.ctn-bl{width:100px;text-align:right;color:var(--c3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0}
.ctn-bt{flex:1;height:18px;background:#f1f5f9;border-radius:4px;overflow:hidden}
.ctn-bfl{height:100%;border-radius:4px;display:flex;align-items:center;padding-left:6px;font-size:9px;font-weight:700;color:#fff;transition:width .4s}
.ctn-bv{font-weight:700;color:var(--c1);width:40px;flex-shrink:0}

/* ═══ Badges ═══ */
.ctn-badge{font-size:9px;padding:3px 8px;border-radius:6px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;display:inline-block}
.ctn-badge-active{background:#dcfce7;color:#15803d}
.ctn-badge-empty{background:#fee2e2;color:#dc2626}
.ctn-badge-expired{background:#fce7f3;color:#be185d}
.ctn-badge-quarantined{background:#fef3c7;color:#d97706}
.ctn-badge-disposed{background:#f1f5f9;color:#64748b}
.bar-ok{background:linear-gradient(90deg,#22c55e,#16a34a)}
.bar-mid{background:linear-gradient(90deg,#eab308,#f59e0b)}
.bar-low{background:linear-gradient(90deg,#ef4444,#dc2626)}

.type-icon{width:28px;height:28px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.type-bottle{background:#dbeafe;color:#2563eb}
.type-vial{background:#ede9fe;color:#7c3aed}
.type-flask{background:#d1fae5;color:#059669}
.type-canister{background:#fed7aa;color:#ea580c}
.type-cylinder{background:#fecdd3;color:#e11d48}
.type-ampoule{background:#e0e7ff;color:#4338ca}
.type-bag{background:#f5f5f4;color:#78716c}
.type-other{background:#f1f5f9;color:#64748b}

/* ═══ Pagination ═══ */
.ctn-pager{display:flex;align-items:center;justify-content:center;gap:3px;margin-top:14px;flex-wrap:wrap}
.ctn-pager button{width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--c1);cursor:pointer;font-size:11px;font-weight:600;transition:all .12s;display:flex;align-items:center;justify-content:center}
.ctn-pager button:hover:not(:disabled){border-color:#2563eb;color:#2563eb}
.ctn-pager button.active{background:#2563eb;color:#fff;border-color:#2563eb}
.ctn-pager button:disabled{opacity:.3;cursor:default}
.ctn-pager-info{font-size:11px;color:var(--c3);margin:0 8px}
.ctn-pager-size{display:flex;align-items:center;gap:6px;margin-left:12px;font-size:11px;color:var(--c3)}
.ctn-pager-size select{padding:4px 8px;border:1.5px solid var(--border);border-radius:7px;font-size:11px;font-weight:600;background:#fff;color:var(--c1);cursor:pointer;transition:border-color .15s}
.ctn-pager-size select:hover{border-color:#2563eb}
.ctn-pager-size select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}

/* ═══ Modal ═══ */
.ctn-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .2s}
.ctn-ov.show{opacity:1;visibility:visible}
.ctn-md{background:#fff;border-radius:18px;width:96%;max-width:820px;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.92) translateY(10px);transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
.ctn-ov.show .ctn-md{transform:scale(1) translateY(0)}
.ctn-mh{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:2;border-bottom:1px solid transparent}
.ctn-mh h3{font-size:16px;font-weight:700;color:var(--c1);display:flex;align-items:center;gap:8px}
.ctn-mx{width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:14px;color:var(--c3);display:flex;align-items:center;justify-content:center;transition:all .12s}
.ctn-mx:hover{background:#fee2e2;color:#dc2626}
.ctn-mb{padding:16px 24px 24px}
.ctn-dg{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ctn-dlb{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
.ctn-dvl{font-size:13px;color:var(--c1);font-weight:500}
.ctn-df{grid-column:1/-1}
.ctn-da{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}

/* ═══ 3D Viewer in Modal ═══ */
.ctn-3d-viewer{background:linear-gradient(135deg,#0c0c1d 0%,#1a1a3e 100%);border-radius:14px;overflow:hidden;height:280px;margin-bottom:16px;position:relative;border:1px solid rgba(108,92,231,.25)}
.ctn-3d-viewer iframe{width:100%;height:100%;border:none}
.ctn-3d-viewer .no-model{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#555;gap:8px}
.ctn-3d-viewer .no-model i{font-size:40px;opacity:.3;color:#6C5CE7}
.ctn-3d-viewer .no-model p{font-size:12px;color:#888}
.ctn-3d-actions{position:absolute;bottom:12px;left:12px;right:12px;display:flex;gap:6px;justify-content:flex-end}
.ctn-3d-actions button,.ctn-3d-actions a{padding:7px 14px;border:none;border-radius:8px;background:rgba(108,92,231,.85);color:#fff;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .18s;text-decoration:none;backdrop-filter:blur(4px)}
.ctn-3d-actions button:hover,.ctn-3d-actions a:hover{background:#6C5CE7;transform:translateY(-1px)}
.ctn-3d-actions .ar-btn{background:linear-gradient(135deg,#0d9488,#14b8a6);box-shadow:0 2px 10px rgba(13,148,136,.4)}
.ctn-3d-actions .ar-btn:hover{background:linear-gradient(135deg,#0f766e,#0d9488);transform:translateY(-1px);box-shadow:0 4px 14px rgba(13,148,136,.5)}
.ctn-3d-label{position:absolute;top:12px;left:12px;display:flex;gap:6px;align-items:center}
.ctn-3d-label span{background:rgba(0,0,0,.5);backdrop-filter:blur(4px);color:#fff;font-size:10px;padding:4px 10px;border-radius:6px;font-weight:600;display:flex;align-items:center;gap:4px}

/* ═══ GHS Pictograms ═══ */
.ghs-row{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.ghs-diamond{width:36px;height:36px;position:relative;cursor:pointer;transition:transform .15s}
.ghs-diamond:hover{transform:scale(1.15)}
.ghs-diamond-inner{position:absolute;inset:3px;transform:rotate(45deg);border:2px solid #dc2626;border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:13px}
.ghs-diamond-inner i{transform:rotate(-45deg)}
.ghs-compressed_gas .ghs-diamond-inner{background:#fff3cd;border-color:#d97706;color:#92400e}
.ghs-flammable .ghs-diamond-inner{background:#fee2e2;border-color:#dc2626;color:#dc2626}
.ghs-oxidizing .ghs-diamond-inner{background:#fef3c7;border-color:#d97706;color:#92400e}
.ghs-toxic .ghs-diamond-inner{background:#fee2e2;border-color:#dc2626;color:#991b1b}
.ghs-corrosive .ghs-diamond-inner{background:#f3e8ff;border-color:#7c3aed;color:#6d28d9}
.ghs-irritant .ghs-diamond-inner{background:#fef3c7;border-color:#f59e0b;color:#92400e}
.ghs-environmental .ghs-diamond-inner{background:#dcfce7;border-color:#16a34a;color:#15803d}
.ghs-health_hazard .ghs-diamond-inner{background:#fee2e2;border-color:#dc2626;color:#991b1b}
.ghs-explosive .ghs-diamond-inner{background:#fef3c7;border-color:#ea580c;color:#c2410c}
.ghs-tooltip{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1a1a2e;color:#fff;padding:4px 8px;border-radius:5px;font-size:9px;white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .15s;z-index:10}
.ghs-diamond:hover .ghs-tooltip{opacity:1}
.ghs-tooltip::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:4px solid transparent;border-top-color:#1a1a2e}

/* ═══ Signal Word ═══ */
.signal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;display:inline-flex;align-items:center;gap:4px;animation:signal-pulse 2s infinite}
.signal-warning{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;display:inline-flex;align-items:center;gap:4px}
@keyframes signal-pulse{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.3)}50%{box-shadow:0 0 0 6px rgba(220,38,38,0)}}

/* ═══ Chemical Info Card ═══ */
.ctn-chem-card{background:linear-gradient(135deg,#f8fafc,#f0f4ff);border:1.5px solid #e0e7ff;border-radius:14px;padding:18px;margin-bottom:16px}
.ctn-chem-header{display:flex;gap:14px;align-items:flex-start}
.ctn-chem-fluid{flex-shrink:0}
.ctn-chem-body{flex:1;min-width:0}
.ctn-chem-name{font-size:18px;font-weight:800;color:var(--c1);margin-bottom:2px;line-height:1.25}
.ctn-chem-sub{font-size:12px;color:var(--c3);margin-bottom:2px;display:flex;align-items:center;gap:6px}
.ctn-chem-sub b{color:var(--c1);font-weight:600}
.ctn-chem-tags{display:flex;gap:4px;flex-wrap:wrap;margin-top:8px}
.ctn-chem-props{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0}
.ctn-chem-prop{text-align:center;padding:6px 4px;background:#fff;border-radius:8px;border:1px solid #e2e8f0}
.ctn-chem-prop .prop-v{font-size:14px;font-weight:800;color:var(--c1)}
.ctn-chem-prop .prop-l{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.3px;margin-top:1px}

/* ═══ History Timeline ═══ */
.ctn-tl{position:relative;padding-left:20px}
.ctn-tl::before{content:'';position:absolute;left:6px;top:4px;bottom:4px;width:2px;background:#e2e8f0;border-radius:2px}
.ctn-tl-item{position:relative;margin-bottom:14px;padding-left:12px}
.ctn-tl-item::before{content:'';position:absolute;left:-18px;top:5px;width:10px;height:10px;border-radius:50%;border:2px solid #2563eb;background:#fff}
.ctn-tl-item.created::before{background:#22c55e;border-color:#22c55e}
.ctn-tl-item.used::before{background:#eab308;border-color:#eab308}
.ctn-tl-item.moved::before{background:#3b82f6;border-color:#3b82f6}
.ctn-tl-item.disposed::before{background:#ef4444;border-color:#ef4444}
.ctn-tl-act{font-size:12px;font-weight:600;color:var(--c1);text-transform:capitalize}
.ctn-tl-det{font-size:11px;color:var(--c3);margin-top:2px}
.ctn-tl-time{font-size:10px;color:var(--c3);margin-top:1px}

/* ═══ Fluid Level ═══ */
.ctn-fluid{width:44px;height:70px;border:2px solid #2563eb;border-radius:8px;position:relative;overflow:hidden;background:#f0f4ff;flex-shrink:0}
.ctn-fluid-fill{position:absolute;bottom:0;left:0;right:0;transition:height .5s;border-radius:0 0 5px 5px}
.ctn-fluid-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#1e3a5f;text-shadow:0 0 4px rgba(255,255,255,.8)}

/* ═══ Add Form ═══ */
.ctn-form-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--ctn-r);padding:20px;margin-bottom:16px}
.ctn-form-card h4{font-size:13px;font-weight:700;color:var(--c1);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.ctn-form-card h4 i{color:#2563eb;font-size:14px}
.ctn-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ctn-form-full{grid-column:1/-1}
.ctn-form-grid label{font-size:11px;font-weight:600;color:var(--c3);display:block;margin-bottom:3px}
.ctn-form-grid input,.ctn-form-grid select,.ctn-form-grid textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:#fff;color:var(--c1);box-sizing:border-box}
.ctn-form-grid input:focus,.ctn-form-grid select:focus,.ctn-form-grid textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}

/* ═══ Preview ═══ */
.ctn-preview{background:#f0f4ff;border:1.5px solid #bfdbfe;border-radius:var(--ctn-r);padding:20px;display:none;margin-bottom:16px}
.ctn-preview h4{font-size:13px;font-weight:700;color:#1e40af;margin-bottom:12px;display:flex;align-items:center;gap:6px}
.ctn-preview-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.ctn-preview-item .preview-label{font-size:10px;color:#6b7280;display:block}
.ctn-preview-item .preview-value{font-size:13px;font-weight:600;color:var(--c1)}

/* ═══ Empty ═══ */
.ctn-empty{text-align:center;padding:48px 24px;color:var(--c3)}
.ctn-empty i{font-size:48px;margin-bottom:12px;opacity:.25}
.ctn-empty p{font-size:14px}

/* ═══ Toast ═══ */
.ctn-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);background:#1a1a2e;color:#fff;padding:12px 24px;border-radius:var(--ctn-rs);font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;z-index:99999;opacity:0;transition:all .3s}
.ctn-toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.ctn-toast.ok{background:#0d6832}.ctn-toast.err{background:#c62828}

/* ═══ Responsive ═══ */
@media(max-width:768px){
    .ctn-hero{flex-direction:column;text-align:center;gap:12px;padding:20px}
    .ctn-hero-meta{margin-left:0}
    .ctn-stats{grid-template-columns:repeat(2,1fr)}
    .ctn-toolbar{flex-direction:column;align-items:stretch}
    .ctn-search{min-width:100%}
    .ctn-grid{grid-template-columns:1fr}
    .ctn-card-3d{height:180px}
    .ctn-card-3d-placeholder{height:60px}
    .ctn-card-3d-chemname{font-size:10px}
    .ctn-card-3d-chip{font-size:7px}
    .ctn-card-3d-ring svg{width:28px;height:28px}
    .ctn-dg,.ctn-form-grid,.ctn-preview-grid{grid-template-columns:1fr}
    .ctn-an{grid-template-columns:1fr}
    .ctn-tabs{overflow-x:auto}
    .ctn-cc,.ctn-co{display:none}
    .ctn-md{width:100%;max-width:100%;border-radius:14px 14px 0 0;max-height:95vh;margin-top:auto}
}
</style>
<body>
<?php Layout::sidebar('containers'); Layout::beginContent(); ?>

<?php if ($action === 'add'): ?>
<!-- ═══════════════════════════════════════════ -->
<!-- ADD BOTTLE FORM                           -->
<!-- ═══════════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
    <div>
        <h2 style="font-size:18px;font-weight:800;color:var(--c1);display:flex;align-items:center;gap:8px">
            <i class="fas fa-plus-circle" style="color:#2563eb"></i>
            <?php echo $lang==='th'?'เพิ่มขวดสารเคมี':'Add Chemical Bottle'; ?>
        </h2>
        <p style="font-size:12px;color:var(--c3);margin-top:2px"><?php echo $lang==='th'?'กรอกข้อมูลเพื่อเพิ่มขวดสารเคมีใหม่เข้าสู่ระบบ':'Fill in the details to add a new chemical bottle'; ?></p>
    </div>
    <a href="/v1/pages/containers.php" class="ctn-btn ctn-btn-g"><i class="fas fa-arrow-left"></i> <?php echo $lang==='th'?'กลับ':'Back'; ?></a>
</div>

<form id="addBottleForm" autocomplete="off">
<!-- Section 1: Chemical -->
<div class="ctn-form-card">
    <h4><i class="fas fa-flask"></i> <?php echo $lang==='th'?'ข้อมูลสารเคมี':'Chemical Information'; ?></h4>
    <div class="ctn-form-grid">
        <div class="ctn-form-full">
            <label><?php echo $lang==='th'?'ชื่อสารเคมี':'Chemical Name'; ?> <span style="color:#ef4444">*</span></label>
            <input type="text" id="chemName" list="chemSuggestions" required placeholder="<?php echo $lang==='th'?'เช่น Sodium Hydroxide':'e.g. Sodium Hydroxide'; ?>">
            <datalist id="chemSuggestions"></datalist>
        </div>
        <div>
            <label>CAS Number</label>
            <input type="text" id="casNumber" placeholder="e.g. 1310-73-2">
        </div>
        <div>
            <label><?php echo $lang==='th'?'เกรด':'Grade'; ?></label>
            <select id="gradeSelect">
                <option value="">— <?php echo $lang==='th'?'เลือก':'Select'; ?> —</option>
                <option value="ACS">ACS Grade</option>
                <option value="AR">AR (Analytical Reagent)</option>
                <option value="CP">CP (Chemically Pure)</option>
                <option value="HPLC">HPLC Grade</option>
                <option value="Molecular Biology">Molecular Biology</option>
                <option value="Technical">Technical Grade</option>
                <option value="Lab">Lab Grade</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div>
            <label><?php echo $lang==='th'?'ผู้ผลิต':'Manufacturer'; ?></label>
            <input type="text" id="manufacturer" list="mfrSuggestions" placeholder="<?php echo $lang==='th'?'เช่น Merck, Sigma-Aldrich':'e.g. Merck, Sigma-Aldrich'; ?>">
            <datalist id="mfrSuggestions"></datalist>
        </div>
        <div>
            <label><?php echo $lang==='th'?'สถานะทางกายภาพ':'Physical State'; ?></label>
            <select id="physicalState">
                <option value="liquid"><?php echo $lang==='th'?'ของเหลว':'Liquid'; ?></option>
                <option value="solid"><?php echo $lang==='th'?'ของแข็ง':'Solid'; ?></option>
                <option value="gas"><?php echo $lang==='th'?'ก๊าซ':'Gas'; ?></option>
                <option value="powder"><?php echo $lang==='th'?'ผง':'Powder'; ?></option>
                <option value="solution"><?php echo $lang==='th'?'สารละลาย':'Solution'; ?></option>
            </select>
        </div>
    </div>
</div>

<!-- Section 2: Bottle -->
<div class="ctn-form-card">
    <h4><i class="fas fa-box"></i> <?php echo $lang==='th'?'ข้อมูลบรรจุภัณฑ์':'Bottle / Container Info'; ?></h4>
    <div class="ctn-form-grid">
        <div>
            <label><?php echo $lang==='th'?'รหัสขวด':'Bottle Code'; ?></label>
            <input type="text" id="bottleCode" placeholder="<?php echo $lang==='th'?'เช่น F05202A6800001 (อัตโนมัติตามห้อง)':'e.g. F05202A6800001 (auto by room)'; ?>" style="font-family:'Courier New',monospace;font-size:13px;letter-spacing:0.5px">
            <small style="color:#64748b;font-size:10px;margin-top:2px;display:block"><?php echo $lang==='th'?'รูปแบบ SUT: รหัสห้อง(6) + หมวด(1) + ปีงบ(2) + ลำดับ(5) — เว้นว่างจะสร้างอัตโนมัติ':'SUT format: RoomCode(6)+Section(1)+FY(2)+Serial(5) — leave blank to auto-generate'; ?></small>
        </div>
        <div>
            <label><?php echo $lang==='th'?'ประเภท':'Container Type'; ?></label>
            <select id="containerType">
                <option value="bottle"><?php echo $lang==='th'?'ขวด (Bottle)':'Bottle'; ?></option>
                <option value="vial"><?php echo $lang==='th'?'ไวแอล (Vial)':'Vial'; ?></option>
                <option value="flask"><?php echo $lang==='th'?'ฟลาสก์ (Flask)':'Flask'; ?></option>
                <option value="canister"><?php echo $lang==='th'?'แกลลอน (Canister)':'Canister'; ?></option>
                <option value="cylinder"><?php echo $lang==='th'?'ถังแก๊ส (Cylinder)':'Cylinder'; ?></option>
                <option value="ampoule"><?php echo $lang==='th'?'แอมพูล (Ampoule)':'Ampoule'; ?></option>
                <option value="bag"><?php echo $lang==='th'?'ถุง (Bag)':'Bag'; ?></option>
                <option value="other"><?php echo $lang==='th'?'อื่นๆ':'Other'; ?></option>
            </select>
        </div>
        <div>
            <label><?php echo $lang==='th'?'วัสดุ':'Material'; ?></label>
            <select id="containerMaterial">
                <option value="glass"><?php echo $lang==='th'?'แก้ว':'Glass'; ?></option>
                <option value="plastic"><?php echo $lang==='th'?'พลาสติก':'Plastic'; ?></option>
                <option value="metal"><?php echo $lang==='th'?'โลหะ':'Metal'; ?></option>
                <option value="other"><?php echo $lang==='th'?'อื่นๆ':'Other'; ?></option>
            </select>
        </div>
        <div>
            <label><?php echo $lang==='th'?'ขนาดบรรจุ':'Pack Size'; ?> <span style="color:#ef4444">*</span></label>
            <input type="number" id="packSize" step="any" min="0" required placeholder="<?php echo $lang==='th'?'เช่น 500':'e.g. 500'; ?>">
        </div>
        <div>
            <label><?php echo $lang==='th'?'หน่วย':'Unit'; ?></label>
            <select id="unitSelect">
                <optgroup label="<?php echo $lang==='th'?'ปริมาตร':'Volume'; ?>"><option value="mL" selected>mL</option><option value="L">L</option><option value="µL">µL</option></optgroup>
                <optgroup label="<?php echo $lang==='th'?'มวล':'Mass'; ?>"><option value="g">g</option><option value="kg">kg</option><option value="mg">mg</option></optgroup>
                <optgroup label="<?php echo $lang==='th'?'อื่นๆ':'Other'; ?>"><option value="Units">Units</option><option value="กิโลกรัม">กิโลกรัม</option></optgroup>
            </select>
        </div>
        <div>
            <label><?php echo $lang==='th'?'ปริมาณคงเหลือ':'Remaining Qty'; ?></label>
            <input type="number" id="remainingQty" step="any" min="0" placeholder="<?php echo $lang==='th'?'เท่ากับขนาดบรรจุ':'Same as pack size'; ?>">
        </div>
        <div>
            <label><?php echo $lang==='th'?'วันหมดอายุ':'Expiry Date'; ?></label>
            <input type="date" id="expiryDate">
        </div>
    </div>
</div>

<!-- Section 3: Location -->
<div class="ctn-form-card">
    <h4><i class="fas fa-map-marker-alt"></i> <?php echo $lang==='th'?'ตำแหน่งจัดเก็บ':'Storage Location'; ?></h4>
    <div class="ctn-form-grid">
        <div>
            <label><?php echo $lang==='th'?'อาคาร':'Building'; ?></label>
            <select id="buildingSelect"><option value="">— <?php echo $lang==='th'?'เลือกอาคาร':'Select Building'; ?> —</option></select>
        </div>
        <div>
            <label><?php echo $lang==='th'?'ห้อง':'Room'; ?></label>
            <select id="roomSelect" disabled><option value="">— <?php echo $lang==='th'?'เลือกอาคารก่อน':'Select building first'; ?> —</option></select>
        </div>
        <div>
            <label><?php echo $lang==='th'?'ตู้จัดเก็บ':'Cabinet'; ?></label>
            <select id="cabinetSelect" disabled><option value="">—</option></select>
        </div>
    </div>
</div>

<!-- Section 4: Purchase -->
<div class="ctn-form-card">
    <h4><i class="fas fa-receipt"></i> <?php echo $lang==='th'?'ข้อมูลการจัดซื้อ':'Purchase Information'; ?></h4>
    <div class="ctn-form-grid">
        <div><label><?php echo $lang==='th'?'เลขที่ใบแจ้งหนี้':'Invoice No.'; ?></label><input type="text" id="invoiceNo"></div>
        <div><label><?php echo $lang==='th'?'ราคา (฿)':'Price (฿)'; ?></label><input type="number" id="price" step="0.01" min="0"></div>
        <div><label><?php echo $lang==='th'?'แหล่งเงิน':'Funding Source'; ?></label><select id="fundingSelect"><option value="">—</option></select></div>
        <div><label><?php echo $lang==='th'?'โครงการ':'Project'; ?></label><input type="text" id="projectName"></div>
        <div class="ctn-form-full"><label><?php echo $lang==='th'?'หมายเหตุ':'Notes'; ?></label><textarea id="notes" rows="2"></textarea></div>
    </div>
</div>

<!-- Preview -->
<div class="ctn-preview" id="formPreview">
    <h4><i class="fas fa-eye"></i> <?php echo $lang==='th'?'ตรวจสอบข้อมูล':'Preview'; ?></h4>
    <div class="ctn-preview-grid" id="previewGrid"></div>
</div>

<!-- Actions -->
<div style="display:flex;gap:10px;justify-content:flex-end;margin-bottom:32px">
    <button type="button" onclick="previewForm()" class="ctn-btn ctn-btn-o"><i class="fas fa-eye"></i> <?php echo $lang==='th'?'ตรวจสอบ':'Preview'; ?></button>
    <button type="submit" id="submitBtn" class="ctn-btn ctn-btn-p" style="padding:10px 28px"><i class="fas fa-save"></i> <?php echo $lang==='th'?'บันทึกเข้าคลัง':'Save to Inventory'; ?></button>
</div>
</form>

<?php else: ?>
<!-- ═══════════════════════════════════════════ -->
<!-- LIST VIEW                                 -->
<!-- ═══════════════════════════════════════════ -->

<!-- Hero -->
<div class="ctn-hero">
    <div class="ctn-hero-ic"><i class="fas fa-box-open"></i></div>
    <div class="ctn-hero-info">
        <h2><?php echo $lang==='th'?'จัดการขวดสารเคมี':'Chemical Containers'; ?></h2>
        <p><?php echo $lang==='th'
            ? ($canSeeAll?'ภาพรวมขวดบรรจุภัณฑ์สารเคมีทั้งหมดในระบบ':($isLab?'จัดการขวดสารของทีมคุณ':'จัดการขวดสารเคมีของคุณ'))
            : ($canSeeAll?'Manage all chemical containers':'Manage your containers'); ?></p>
    </div>
    <div class="ctn-hero-meta">
        <div class="ctn-hero-c"><div class="v" id="heroTotal">—</div><div class="lb"><?php echo $lang==='th'?'ทั้งหมด':'Total'; ?></div></div>
        <div class="ctn-hero-c"><div class="v" id="heroMy">—</div><div class="lb"><?php echo $lang==='th'?'ของฉัน':'Mine'; ?></div></div>
        <div class="ctn-hero-c"><div class="v" id="hero3D">—</div><div class="lb">3D</div></div>
    </div>
</div>

<!-- Stats -->
<div class="ctn-stats" id="statsRow"></div>

<!-- Tabs + Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px">
    <div class="ctn-tabs">
        <button class="ctn-tab active" onclick="switchTab('all',this)"><i class="fas fa-boxes-stacked"></i> <?php echo $lang==='th'?'ทั้งหมด':'All'; ?> <span class="bg" id="tabAllCnt">—</span></button>
        <button class="ctn-tab" onclick="switchTab('my',this)"><i class="fas fa-user"></i> <?php echo $lang==='th'?'ของฉัน':'My'; ?> <span class="bg" id="tabMyCnt">—</span></button>
    </div>
    <div style="display:flex;gap:6px;align-items:center">
        <div class="ctn-vw" id="viewSwitcher">
            <button class="active" onclick="switchView('table',this)" title="Table"><i class="fas fa-list"></i></button>
            <button onclick="switchView('grid',this)" title="Grid"><i class="fas fa-th-large"></i></button>
            <button onclick="switchView('compact',this)" title="Compact"><i class="fas fa-bars"></i></button>
            <button onclick="switchView('analytics',this)" title="Analytics"><i class="fas fa-chart-bar"></i></button>
        </div>
        <?php if ($canEdit): ?>
        <a href="/v1/pages/containers.php?action=add" class="ctn-btn ctn-btn-p"><i class="fas fa-plus"></i> <?php echo $lang==='th'?'เพิ่มขวด':'Add Bottle'; ?></a>
        <?php endif; ?>
    </div>
</div>

<!-- My Banner (hidden by default) -->
<div class="ctn-my" id="myBanner" style="display:none">
    <div class="ctn-my-av"><?php echo $userInitial; ?></div>
    <div>
        <h3><?php echo htmlspecialchars($displayName); ?></h3>
        <p id="myBannerSub"><?php echo $lang==='th'?'ขวดสารเคมีของคุณ':'Your chemical containers'; ?></p>
    </div>
    <div style="margin-left:auto;display:flex;gap:16px">
        <div style="text-align:center"><div style="font-size:20px;font-weight:900" id="myBannerTotal">—</div><div style="font-size:9px;opacity:.7"><?php echo $lang==='th'?'ทั้งหมด':'Total'; ?></div></div>
        <div style="text-align:center"><div style="font-size:20px;font-weight:900" id="myBannerActive">—</div><div style="font-size:9px;opacity:.7"><?php echo $lang==='th'?'ใช้งาน':'Active'; ?></div></div>
    </div>
</div>

<!-- Toolbar -->
<div class="ctn-toolbar">
    <div class="ctn-search"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="<?php echo $lang==='th'?'ค้นหาชื่อสาร, รหัสขวด, CAS, ผู้ผลิต...':'Search chemical, bottle code, CAS, manufacturer...'; ?>"></div>
    <button class="ctn-btn ctn-btn-g" onclick="toggleFilter()"><i class="fas fa-filter"></i> <?php echo $lang==='th'?'ตัวกรอง':'Filter'; ?></button>
    <select id="sortSelect" class="ctn-fl" style="padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff">
        <option value="newest"><?php echo $lang==='th'?'ใหม่สุด':'Newest'; ?></option>
        <option value="oldest"><?php echo $lang==='th'?'เก่าสุด':'Oldest'; ?></option>
        <option value="name_asc">A → Z</option>
        <option value="name_desc">Z → A</option>
        <option value="pct_asc"><?php echo $lang==='th'?'เหลือน้อย':'Low first'; ?></option>
        <option value="pct_desc"><?php echo $lang==='th'?'เหลือมาก':'Full first'; ?></option>
        <option value="bottle_code"><?php echo $lang==='th'?'รหัสขวด':'Bottle Code'; ?></option>
    </select>
</div>

<!-- Filter Panel -->
<div class="ctn-fp" id="filterPanel">
    <div class="ctn-fg2">
        <div class="ctn-fl"><label><?php echo $lang==='th'?'สถานะ':'Status'; ?></label>
            <select id="statusFilter"><option value=""><?php echo __('all'); ?></option><option value="active"><?php echo $lang==='th'?'ใช้งาน':'Active'; ?></option><option value="empty"><?php echo $lang==='th'?'หมด':'Empty'; ?></option><option value="expired"><?php echo $lang==='th'?'หมดอายุ':'Expired'; ?></option><option value="quarantined"><?php echo $lang==='th'?'กักกัน':'Quarantined'; ?></option></select>
        </div>
        <div class="ctn-fl"><label><?php echo $lang==='th'?'ประเภท':'Type'; ?></label>
            <select id="typeFilter"><option value=""><?php echo __('all'); ?></option><option value="bottle">Bottle</option><option value="vial">Vial</option><option value="flask">Flask</option><option value="canister">Canister</option><option value="cylinder">Cylinder</option><option value="ampoule">Ampoule</option></select>
        </div>
        <div class="ctn-fl"><label><?php echo $lang==='th'?'อาคาร':'Building'; ?></label>
            <select id="buildingFilter"><option value=""><?php echo __('all'); ?></option></select>
        </div>
        <div class="ctn-fl"><label><?php echo $lang==='th'?'แหล่งข้อมูล':'Source'; ?></label>
            <select id="sourceFilter"><option value=""><?php echo __('all'); ?></option><option value="container"><?php echo $lang==='th'?'ระบบใหม่ (Container)':'System (Container)'; ?></option><option value="stock"><?php echo $lang==='th'?'คลังเดิม (CSV)':'Legacy (CSV)'; ?></option></select>
        </div>
    </div>
</div>

<!-- Data Container -->
<div id="dataContainer"></div>

<!-- Pagination -->
<div class="ctn-pager" id="pager" style="display:none"></div>

<!-- Detail Modal -->
<div class="ctn-ov" id="detailOverlay" onclick="if(event.target===this)closeDetail()">
    <div class="ctn-md" id="detailModal"></div>
</div>

<!-- Toast -->
<div class="ctn-toast" id="toast"></div>

<?php endif; ?>

<?php Layout::endContent(); ?>
<script>
const LANG='<?php echo $lang; ?>';
const ACTION='<?php echo $action; ?>';
const IS_ADMIN=<?php echo $isAdmin?'true':'false'; ?>;
const CAN_EDIT=<?php echo $canEdit?'true':'false'; ?>;
const MY_UID=<?php echo $uid; ?>;

const typeIcons={bottle:'fa-wine-bottle',vial:'fa-vial',flask:'fa-flask',canister:'fa-gas-pump',cylinder:'fa-fire-extinguisher',ampoule:'fa-syringe',bag:'fa-bag-shopping',other:'fa-box'};
const typeLabels={bottle:'Bottle',vial:'Vial',flask:'Flask',canister:'Canister',cylinder:'Cylinder',ampoule:'Ampoule',bag:'Bag',other:'Other'};

function pctColor(p){return p>50?'bar-ok':p>20?'bar-mid':'bar-low'}
function pctCss(p){return p>50?'#16a34a':p>20?'#d97706':'#dc2626'}
function badgeHtml(s){
    const m={active:['ctn-badge-active','Active'],empty:['ctn-badge-empty','Empty'],expired:['ctn-badge-expired','Expired'],quarantined:['ctn-badge-quarantined','Quarantined'],disposed:['ctn-badge-disposed','Disposed'],low:['ctn-badge-quarantined','Low']};
    const [cls,lbl]=m[s]||m.active;
    return `<span class="ctn-badge ${cls}">${lbl}</span>`;
}
function srcBadge(s){return s==='stock'?'<span class="ctn-src ctn-src-stock">CSV</span>':'<span class="ctn-src ctn-src-container">SYS</span>'}
function toast(msg,ok=true){const t=document.getElementById('toast');t.textContent=msg;t.className='ctn-toast '+(ok?'ok':'err')+' show';setTimeout(()=>t.classList.remove('show'),3000)}

<?php if ($action === 'add'): ?>
// ═══════════════════════════════════════════
// ADD FORM LOGIC
// ═══════════════════════════════════════════
(async function(){
    const load=async u=>{try{const d=await apiFetch(u);return d.success?d.data:[]}catch(e){return[]}};
    // Buildings
    const bldgs=await load('/v1/api/locations.php?type=buildings');
    const bSel=document.getElementById('buildingSelect');
    bldgs.forEach(b=>{const o=document.createElement('option');o.value=b.id;o.textContent=b.shortname?b.name+' ('+b.shortname+')':b.name;bSel.appendChild(o)});
    // Funding
    const funding=await load('/v1/api/locations.php?type=funding');
    const fSel=document.getElementById('fundingSelect');
    funding.forEach(f=>{const o=document.createElement('option');o.value=f.id;o.textContent=f.name;fSel.appendChild(o)});
    // Manufacturers
    const mfrs=await load('/v1/api/locations.php?type=manufacturers');
    const mDl=document.getElementById('mfrSuggestions');
    mfrs.forEach(m=>{const o=document.createElement('option');o.value=m.name;mDl.appendChild(o)});
    // Chemicals
    try{const d=await apiFetch('/v1/api/chemicals.php?limit=200');if(d.success){const items=d.data?.data||d.data?.chemicals||d.data||[];const dl=document.getElementById('chemSuggestions');items.forEach(c=>{const o=document.createElement('option');o.value=c.name||c.chemical_name;if(c.cas_number)o.label=c.cas_number;dl.appendChild(o)})}}catch(e){}
})();

// Cascading
document.getElementById('buildingSelect').addEventListener('change',async function(){
    const rm=document.getElementById('roomSelect');rm.innerHTML='<option value="">— '+(LANG==='th'?'กำลังโหลด...':'Loading...')+' —</option>';rm.disabled=true;
    document.getElementById('cabinetSelect').innerHTML='<option value="">—</option>';document.getElementById('cabinetSelect').disabled=true;
    if(!this.value){rm.innerHTML='<option value="">— '+(LANG==='th'?'เลือกอาคารก่อน':'Select building')+' —</option>';return}
    try{const d=await apiFetch('/v1/api/locations.php?type=rooms&building_id='+this.value);if(d.success&&d.data.length){rm.innerHTML='<option value="">— '+(LANG==='th'?'เลือกห้อง':'Select Room')+' —</option>';d.data.forEach(r=>{const o=document.createElement('option');o.value=r.id;o.textContent=(r.code||r.room_number?((r.code||r.room_number)+' - '):'')+r.name;rm.appendChild(o)});rm.disabled=false}else{rm.innerHTML='<option value="">— '+(LANG==='th'?'ไม่มีห้อง':'No rooms')+' —</option>'}}catch(e){}
});
document.getElementById('roomSelect').addEventListener('change',async function(){
    const cb=document.getElementById('cabinetSelect');cb.innerHTML='<option value="">— '+(LANG==='th'?'กำลังโหลด...':'Loading...')+' —</option>';cb.disabled=true;
    if(!this.value)return;
    try{const d=await apiFetch('/v1/api/locations.php?type=cabinets&room_id='+this.value);if(d.success&&d.data.length){cb.innerHTML='<option value="">— '+(LANG==='th'?'เลือกตู้':'Select Cabinet')+' —</option>';d.data.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.name;cb.appendChild(o)});cb.disabled=false}else{cb.innerHTML='<option value="">— '+(LANG==='th'?'ไม่มีตู้':'No cabinets')+' —</option>'}}catch(e){}
});

function previewForm(){
    const name=document.getElementById('chemName').value;
    if(!name){alert(LANG==='th'?'กรุณากรอกชื่อสารเคมี':'Please enter chemical name');document.getElementById('chemName').focus();return}
    const pack=document.getElementById('packSize').value;
    if(!pack){alert(LANG==='th'?'กรุณากรอกขนาดบรรจุ':'Please enter pack size');document.getElementById('packSize').focus();return}
    const bldg=document.getElementById('buildingSelect'),room=document.getElementById('roomSelect');
    const items=[
        [LANG==='th'?'ชื่อสารเคมี':'Chemical',name],
        ['CAS',document.getElementById('casNumber').value||'-'],
        [LANG==='th'?'เกรด':'Grade',document.getElementById('gradeSelect').value||'-'],
        [LANG==='th'?'ผู้ผลิต':'Manufacturer',document.getElementById('manufacturer').value||'-'],
        [LANG==='th'?'ขนาดบรรจุ':'Pack Size',pack+' '+document.getElementById('unitSelect').value],
        [LANG==='th'?'ประเภท':'Type',document.getElementById('containerType').value],
        [LANG==='th'?'วัสดุ':'Material',document.getElementById('containerMaterial').value],
        [LANG==='th'?'อาคาร':'Building',bldg.options[bldg.selectedIndex]?.text||'-'],
        [LANG==='th'?'ห้อง':'Room',room.options[room.selectedIndex]?.text||'-'],
        [LANG==='th'?'ราคา':'Price',(document.getElementById('price').value||'0')+' ฿'],
    ];
    document.getElementById('previewGrid').innerHTML=items.map(([l,v])=>`<div class="ctn-preview-item"><span class="preview-label">${l}</span><span class="preview-value">${v}</span></div>`).join('');
    const pv=document.getElementById('formPreview');pv.style.display='block';pv.scrollIntoView({behavior:'smooth'});
}

document.getElementById('addBottleForm').addEventListener('submit',async function(e){
    e.preventDefault();
    const btn=document.getElementById('submitBtn');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> '+(LANG==='th'?'กำลังบันทึก...':'Saving...');
    const packSize=parseFloat(document.getElementById('packSize').value);
    const remaining=document.getElementById('remainingQty').value?parseFloat(document.getElementById('remainingQty').value):packSize;
    const body={
        chemical_name:document.getElementById('chemName').value.trim(),
        cas_number:document.getElementById('casNumber').value.trim(),
        grade:document.getElementById('gradeSelect').value,
        manufacturer:document.getElementById('manufacturer').value.trim(),
        physical_state:document.getElementById('physicalState').value,
        bottle_code:document.getElementById('bottleCode').value.trim(),
        container_type:document.getElementById('containerType').value,
        container_material:document.getElementById('containerMaterial').value,
        initial_quantity:packSize,
        current_quantity:remaining,
        quantity_unit:document.getElementById('unitSelect').value,
        building_id:document.getElementById('buildingSelect').value||null,
        room_id:document.getElementById('roomSelect').value||null,
        cabinet_id:document.getElementById('cabinetSelect').value||null,
        invoice_number:document.getElementById('invoiceNo').value.trim(),
        cost:document.getElementById('price').value?parseFloat(document.getElementById('price').value):null,
        funding_source_id:document.getElementById('fundingSelect').value||null,
        project_name:document.getElementById('projectName').value.trim(),
        notes:document.getElementById('notes').value.trim(),
        expiry_date:document.getElementById('expiryDate').value||null,
    };
    try{
        const d=await apiFetch('/v1/api/containers.php',{method:'POST',body:JSON.stringify(body)});
        if(d.success){
            const code=d.data?.bottle_code||d.data?.id||'';
            if(confirm((LANG==='th'?'✅ บันทึกสำเร็จ!\nรหัสขวด: '+code+'\n\nต้องการเพิ่มขวดอีกหรือไม่?':'✅ Saved! Bottle: '+code+'\n\nAdd another?'))){
                document.getElementById('addBottleForm').reset();document.getElementById('formPreview').style.display='none';document.getElementById('chemName').focus();
            }else{window.location.href='/v1/pages/containers.php'}
        }else{throw new Error(d.error||'Failed')}
    }catch(er){
        alert('❌ '+(LANG==='th'?'เกิดข้อผิดพลาด: ':'Error: ')+er.message);
        btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> '+(LANG==='th'?'บันทึกเข้าคลัง':'Save to Inventory');
    }
});

<?php else: ?>
// ═══════════════════════════════════════════
// LIST VIEW LOGIC
// ═══════════════════════════════════════════
let allData=[],curTab='all',curView='table',curPage=1,totalPages=1,totalItems=0;
let pageSize=20;
let stats=null;

// Load stats
async function loadStats(){
    try{
        const d=await apiFetch('/v1/api/containers.php?action=stats');
        if(d.success){
            stats=d.data;
            document.getElementById('heroTotal').textContent=stats.total;
            document.getElementById('heroMy').textContent=stats.my_total;
            document.getElementById('hero3D').textContent=stats.models_3d;
            document.getElementById('tabAllCnt').textContent=stats.total;
            document.getElementById('tabMyCnt').textContent=stats.my_total;
            // Source breakdown tooltip
            const sb=stats.source_breakdown||{};
            const hel=document.getElementById('heroTotal');
            if(hel)hel.title=`Container: ${sb.containers||0} | CSV Stock: ${sb.stock||0}`;
            renderStats();
        }
    }catch(e){console.error(e)}
}

function renderStats(){
    if(!stats)return;
    const items=[
        {icon:'fa-check-circle',color:'#16a34a',bg:'#dcfce7',v:stats.active,l:LANG==='th'?'ใช้งาน':'Active'},
        {icon:'fa-flask-vial',color:'#2563eb',bg:'#dbeafe',v:stats.chemicals,l:LANG==='th'?'สารเคมี':'Chemicals'},
        {icon:'fa-battery-quarter',color:'#d97706',bg:'#fef3c7',v:stats.low,l:LANG==='th'?'เหลือน้อย':'Low'},
        {icon:'fa-clock',color:'#dc2626',bg:'#fee2e2',v:stats.expiring_soon,l:LANG==='th'?'ใกล้หมดอายุ':'Expiring'},
        {icon:'fa-box-archive',color:'#64748b',bg:'#f1f5f9',v:stats.empty,l:LANG==='th'?'หมดแล้ว':'Empty'},
        {icon:'fa-cube',color:'#7c3aed',bg:'#ede9fe',v:stats.models_3d,l:'3D Models'},
    ];
    document.getElementById('statsRow').innerHTML=items.map(s=>
        `<div class="ctn-stat"><div class="ctn-si" style="background:${s.bg};color:${s.color}"><i class="fas ${s.icon}"></i></div><div><div class="ctn-sv">${s.v}</div><div class="ctn-sl">${s.l}</div></div></div>`
    ).join('');
}

// Load data
async function loadData(){
    try{
        const p=new URLSearchParams({page:curPage,limit:pageSize,tab:curTab,sort:document.getElementById('sortSelect').value});
        const s=document.getElementById('searchInput').value;if(s)p.set('search',s);
        const st=document.getElementById('statusFilter')?.value;if(st)p.set('status',st);
        const tp=document.getElementById('typeFilter')?.value;if(tp)p.set('type',tp);
        const bd=document.getElementById('buildingFilter')?.value;if(bd)p.set('building_id',bd);
        const src=document.getElementById('sourceFilter')?.value;if(src)p.set('source',src);

        const d=await apiFetch('/v1/api/containers.php?'+p);
        if(d.success){
            allData=d.data.data||[];
            totalItems=d.data.pagination?.total||0;
            totalPages=d.data.pagination?.pages||1;
            render();
        }
    }catch(e){console.error(e)}
}

function render(){
    if(curView==='analytics'){renderAnalytics();return}
    const el=document.getElementById('dataContainer');
    if(!allData.length){
        el.innerHTML=`<div class="ctn-empty"><i class="fas fa-box-open"></i><p>${LANG==='th'?'ไม่พบข้อมูลขวดสารเคมี':'No containers found'}</p>${CAN_EDIT?`<a href="/v1/pages/containers.php?action=add" class="ctn-btn ctn-btn-p" style="margin-top:12px"><i class="fas fa-plus"></i> ${LANG==='th'?'เพิ่มขวดสาร':'Add Bottle'}</a>`:''}</div>`;
        document.getElementById('pager').style.display='none';return;
    }
    if(curView==='table')renderTable();
    else if(curView==='grid')renderGrid();
    else if(curView==='compact')renderCompact();
    renderPager();
}

// ── Table View ──
function renderTable(){
    const el=document.getElementById('dataContainer');
    const ghsTinyIcons={compressed_gas:'fa-wind',flammable:'fa-fire-flame-curved',oxidizing:'fa-circle-radiation',toxic:'fa-skull-crossbones',corrosive:'fa-flask-vial',irritant:'fa-exclamation-triangle',environmental:'fa-leaf',health_hazard:'fa-heart-crack',explosive:'fa-explosion'};
    const ghsTinyColors={compressed_gas:'#d97706',flammable:'#dc2626',oxidizing:'#d97706',toxic:'#991b1b',corrosive:'#7c3aed',irritant:'#f59e0b',environmental:'#16a34a',health_hazard:'#dc2626',explosive:'#ea580c'};
    el.innerHTML=`<div class="ctn-tw"><table class="ctn-t"><thead><tr>
        <th>#</th>
        <th>${LANG==='th'?'สารเคมี':'Chemical'}</th>
        <th>${LANG==='th'?'รหัสขวด':'Code'}</th>
        <th>${LANG==='th'?'ปริมาณ':'Quantity'}</th>
        <th>%</th>
        <th>${LANG==='th'?'อันตราย':'Hazard'}</th>
        <th>${LANG==='th'?'สถานะ':'Status'}</th>
        <th>${LANG==='th'?'เจ้าของ':'Owner'}</th>
        <th>${LANG==='th'?'ตำแหน่ง':'Location'}</th>
        <th>3D/AR</th>
    </tr></thead><tbody>${allData.map((c,i)=>{
        const pct=parseFloat(c.remaining_percentage)||0;
        const me=c.is_mine?'me':'';
        const idx=(curPage-1)*20+i+1;
        const haz=(c.hazard_pictograms||[]);
        const hazTiny=haz.length?haz.slice(0,4).map(h=>`<i class="fas ${ghsTinyIcons[h]||'fa-exclamation'}" style="font-size:10px;color:${ghsTinyColors[h]||'#dc2626'}" title="${h}"></i>`).join(' ')+(haz.length>4?` <span style="font-size:9px;color:var(--c3)">+${haz.length-4}</span>`:''):`<span style="color:#ccc;font-size:10px">—</span>`;
        return `<tr class="${me}" onclick="openDetail(${c.id})">
            <td>${idx}</td>
            <td><div style="display:flex;align-items:center;gap:8px">
                <div class="type-icon type-${c.container_type||'other'}"><i class="fas ${typeIcons[c.container_type]||'fa-box'}"></i></div>
                <div><div style="font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(c.chemical_name||'-')}</div>
                ${c.cas_number?`<div style="font-size:10px;color:var(--c3)">${c.cas_number} ${srcBadge(c.source)}</div>`:srcBadge(c.source)}</div>
            </div></td>
            <td><code style="font-size:10px;background:#f1f5f9;padding:2px 6px;border-radius:4px;font-family:'Courier New',monospace;letter-spacing:0.3px">${esc(c.bottle_code||'')}</code></td>
            <td style="white-space:nowrap;font-size:11px">${c.current_quantity||0} / ${c.initial_quantity||0} ${esc(c.quantity_unit||'')}</td>
            <td><div style="display:flex;align-items:center;gap:6px">
                <div style="width:40px;height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden"><div class="${pctColor(pct)}" style="height:100%;width:${pct}%;border-radius:2px"></div></div>
                <span style="font-weight:700;font-size:11px;color:${pctCss(pct)}">${pct.toFixed(0)}%</span>
            </div></td>
            <td style="white-space:nowrap">${hazTiny}</td>
            <td>${badgeHtml(c.status||'active')}</td>
            <td style="font-size:11px">${c.is_mine?'<i class="fas fa-star" style="color:#d97706;font-size:10px"></i> ':''}<span>${esc(c.owner_name||'-')}</span></td>
            <td style="font-size:11px;color:var(--c2)">${esc(c.location_text||'-')}</td>
            <td>${c.has_3d?'<span class="ctn-3d-badge" style="position:static"><i class="fas fa-cube"></i> 3D</span>':'<span style="color:#ccc;font-size:10px">—</span>'}</td>
        </tr>`;
    }).join('')}</tbody></table></div>`;
}

// ── Grid View ── helpers
const ghsMini3D={compressed_gas:'fa-wind',flammable:'fa-fire-flame-curved',oxidizing:'fa-circle-radiation',toxic:'fa-skull-crossbones',corrosive:'fa-flask-vial',irritant:'fa-exclamation-triangle',environmental:'fa-leaf',health_hazard:'fa-heart-crack',explosive:'fa-explosion'};
function build3DPreview(c){
    if(!c.has_3d||!c.model_url) return `<div class="ctn-card-3d-placeholder"><i class="fas fa-cube" style="font-size:22px;opacity:.18"></i></div>`;
    const viewerUrl=c.model_type==='embed'?c.model_url:`/v1/pages/viewer3d.php?src=${encodeURIComponent(c.model_url)}&embed=1&transparent=0&title=${encodeURIComponent(c.chemical_name||'')}`;
    const pct=parseFloat(c.remaining_percentage)||0;
    const haz=(c.hazard_pictograms||[]);
    const circumf=Math.PI*2*12;
    const dashOff=circumf*(1-pct/100);
    const ringColor=pct>50?'#22c55e':pct>20?'#f59e0b':'#ef4444';

    // Signal word badge
    const sigCls=c.signal_word==='Danger'?'danger':c.signal_word==='Warning'?'warning':'';
    const sigHtml=sigCls?`<span class="ctn-card-3d-signal ${sigCls}"><i class="fas ${sigCls==='danger'?'fa-radiation':'fa-exclamation-triangle'}"></i> ${sigCls==='danger'?(LANG==='th'?'อันตราย':'DANGER'):(LANG==='th'?'ระวัง':'WARNING')}</span>`:'';

    // GHS mini icons
    const ghsRow=haz.length?`<div class="ctn-card-3d-ghs">${haz.slice(0,5).map(h=>`<span title="${h}"><i class="fas ${ghsMini3D[h]||'fa-exclamation'}"></i></span>`).join('')}${haz.length>5?`<span style="font-size:7px;color:rgba(255,255,255,.5)">+${haz.length-5}</span>`:''}</div>`:'';

    return `<div class="ctn-card-3d" data-viewer-url="${esc(viewerUrl)}">
        <iframe src="${esc(viewerUrl)}" loading="lazy" allow="autoplay" allowfullscreen style="width:100%;height:100%;border:none;border-radius:var(--ctn-r) var(--ctn-r) 0 0"></iframe>
        <div class="ctn-card-3d-overlay" onclick="toggle3DInteract(this.closest('.ctn-card-3d'),event)" title="คลิกเพื่อหมุนโมเดล">
            <div class="ctn-card-3d-play"><i class="fas fa-play" style="margin-left:2px"></i></div>
        </div>

        <div class="ctn-card-3d-top">
            <div style="display:flex;gap:4px;align-items:center">
                <span class="ctn-card-3d-badge"><i class="fas fa-cube"></i> 3D</span>
                ${sigHtml}
            </div>
        </div>

        <div class="ctn-card-3d-ring">
            <svg viewBox="0 0 32 32">
                <circle class="ring-bg" cx="16" cy="16" r="12"/>
                <circle class="ring-fg" cx="16" cy="16" r="12" stroke="${ringColor}" stroke-dasharray="${circumf.toFixed(1)}" stroke-dashoffset="${dashOff.toFixed(1)}" transform="rotate(-90 16 16)"/>
                <text class="ring-txt" x="16" y="16">${pct.toFixed(0)}%</text>
            </svg>
        </div>

        <div class="ctn-card-3d-hud">
            <div class="ctn-card-3d-info">
                <div class="ctn-card-3d-chemname">${esc(c.chemical_name||'-')}</div>
                <div class="ctn-card-3d-meta">
                    ${c.cas_number?`<span class="ctn-card-3d-chip cas">CAS ${esc(c.cas_number)}</span>`:''}
                    ${c.molecular_formula?`<span class="ctn-card-3d-chip formula">${esc(c.molecular_formula)}</span>`:''}
                    <span class="ctn-card-3d-chip qty">${c.current_quantity||0}/${c.initial_quantity||0} ${esc(c.quantity_unit||'')}</span>
                </div>
                ${ghsRow}
            </div>
            <div class="ctn-card-3d-controls">
                <button class="ctn-card-3d-ctrl ar-btn" onclick="openAR(${c.id},'${c.source||''}',event)" title="${LANG==='th'?'ดูใน AR':'View in AR'}"><i class="fas fa-vr-cardboard"></i></button>
                <button class="ctn-card-3d-ctrl" onclick="open3DFullscreen('${esc(viewerUrl)}','${esc(c.chemical_name||'')}',event)" title="${LANG==='th'?'เต็มหน้าจอ':'Fullscreen'}"><i class="fas fa-expand"></i></button>
                <button class="ctn-card-3d-ctrl" onclick="toggle3DInteract(this.closest('.ctn-card-3d'),event)" title="${LANG==='th'?'หมุนโมเดล':'Interact'}"><i class="fas fa-hand-pointer"></i></button>
            </div>
        </div>
    </div>`;
}
function toggle3DInteract(el,e){
    e.stopPropagation();
    el.classList.toggle('interactive');
    const ov=el.querySelector('.ctn-card-3d-overlay');
    if(el.classList.contains('interactive')){
        if(ov) ov.style.display='none';
    } else {
        if(ov) ov.style.display='';
    }
}
function open3DFullscreen(url,title,e){
    e.stopPropagation();
    window.open(url,'_blank','width=1000,height=700,toolbar=no,menubar=no');
}
function openAR(id,source,e){
    e.stopPropagation();
    const src=source||(id<0?'stock':'container');
    window.open(`/v1/ar/view_ar.php?id=${id}&source=${src}`,'_blank');
}
function renderGrid(){
    const el=document.getElementById('dataContainer');
    const ghsMiniIcons={compressed_gas:'fa-wind',flammable:'fa-fire-flame-curved',oxidizing:'fa-circle-radiation',toxic:'fa-skull-crossbones',corrosive:'fa-flask-vial',irritant:'fa-exclamation-triangle',environmental:'fa-leaf',health_hazard:'fa-heart-crack',explosive:'fa-explosion'};
    const ghsMiniColors={compressed_gas:'#d97706',flammable:'#dc2626',oxidizing:'#d97706',toxic:'#991b1b',corrosive:'#7c3aed',irritant:'#f59e0b',environmental:'#16a34a',health_hazard:'#dc2626',explosive:'#ea580c'};
    el.innerHTML=`<div class="ctn-grid">${allData.map(c=>{
        const pct=parseFloat(c.remaining_percentage)||0;
        const isExp=c.expiry_date&&new Date(c.expiry_date)<new Date();
        const haz=(c.hazard_pictograms||[]);
        const hazMini=haz.length?`<div style="display:flex;gap:3px;flex-wrap:wrap;margin-top:6px">${haz.slice(0,5).map(h=>`<span style="width:20px;height:20px;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;font-size:9px;background:#fef2f2;color:${ghsMiniColors[h]||'#dc2626'};border:1px solid ${ghsMiniColors[h]||'#fecaca'}30" title="${h}"><i class="fas ${ghsMiniIcons[h]||'fa-exclamation'}"></i></span>`).join('')}${haz.length>5?`<span style="font-size:9px;color:var(--c3)">+${haz.length-5}</span>`:''}</div>`:'';
        return `<div class="ctn-card${c.is_mine?' me':''}" onclick="openDetail(${c.id})">
            ${build3DPreview(c)}
            <div class="ctn-card-hd">
                <div class="ctn-card-ic type-${c.container_type||'other'}"><i class="fas ${typeIcons[c.container_type]||'fa-box'}"></i></div>
                <div style="min-width:0;flex:1">
                    <div class="ctn-card-nm">${esc(c.chemical_name||'-')}</div>
                    <div class="ctn-card-cd">${esc(c.bottle_code||'')} ${srcBadge(c.source)}</div>
                </div>
                ${badgeHtml(c.status||'active')}
            </div>
            <div class="ctn-card-bd">
                <div class="ctn-card-tg">
                    <span class="ctn-card-tag" style="background:#f0f4ff;color:#2563eb">${typeLabels[c.container_type]||c.container_type||'-'}</span>
                    ${c.grade?`<span class="ctn-card-tag" style="background:#f0fdf4;color:#15803d">${esc(c.grade)}</span>`:''}
                    ${c.manufacturer_name?`<span class="ctn-card-tag" style="background:#fef3c7;color:#d97706">${esc(c.manufacturer_name)}</span>`:''}
                </div>
                <div class="ctn-card-ft" style="margin-bottom:6px">
                    <span>${c.current_quantity||0} / ${c.initial_quantity||0} ${esc(c.quantity_unit||'')}</span>
                    <span style="font-weight:700;color:${pctCss(pct)}">${pct.toFixed(0)}%</span>
                </div>
                <div class="ctn-card-bar"><div class="ctn-card-bf ${pctColor(pct)}" style="width:${pct}%"></div></div>
                ${c.location_text&&c.location_text!=='-'?`<div class="ctn-card-row"><i class="fas fa-map-marker-alt"></i> ${esc(c.location_text)}</div>`:''}
                <div class="ctn-card-row"><i class="fas fa-user"></i> ${c.is_mine?'<i class="fas fa-star" style="color:#d97706;font-size:9px"></i> ':''} ${esc(c.owner_name||'-')}</div>
                ${c.expiry_date?`<div class="ctn-card-row" style="${isExp?'color:#dc2626;font-weight:600':''}"><i class="fas fa-calendar"></i> ${formatDate(c.expiry_date)}${isExp?' ⚠️':''}</div>`:''}
                ${hazMini}
            </div>
        </div>`;
    }).join('')}</div>`;
}

// ── Compact View ──
function renderCompact(){
    const el=document.getElementById('dataContainer');
    el.innerHTML=`<div class="ctn-compact">${allData.map(c=>{
        const pct=parseFloat(c.remaining_percentage)||0;
        return `<div class="ctn-cr${c.is_mine?' me':''}" onclick="openDetail(${c.id})">
            <div class="type-icon type-${c.container_type||'other'}" style="width:24px;height:24px;border-radius:6px;font-size:10px"><i class="fas ${typeIcons[c.container_type]||'fa-box'}"></i></div>
            <div class="ctn-cn">${esc(c.chemical_name||'-')}</div>
            <div class="ctn-cc">${esc(c.bottle_code||'')} ${srcBadge(c.source)}</div>
            ${badgeHtml(c.status||'active')}
            <div class="ctn-cb"><div class="${pctColor(pct)}" style="width:${pct}%"></div></div>
            <div class="ctn-cp" style="color:${pctCss(pct)}">${pct.toFixed(0)}%</div>
            <div class="ctn-co">${esc(c.owner_name||'-')}</div>
            ${c.has_3d?'<i class="fas fa-cube" style="color:#7c3aed;font-size:11px" title="3D Model"></i>':''}
        </div>`;
    }).join('')}</div>`;
}

// ── Analytics View ──
function renderAnalytics(){
    if(!stats){document.getElementById('dataContainer').innerHTML='';return}
    const el=document.getElementById('dataContainer');
    const maxType=Math.max(...(stats.types||[]).map(t=>t.cnt),1);
    const maxChem=Math.max(...(stats.top_chemicals||[]).map(t=>t.cnt),1);
    const maxOwn=Math.max(...(stats.top_owners||[]).map(t=>t.cnt),1);
    const total=stats.total||1;
    const colors=['#2563eb','#7c3aed','#059669','#ea580c','#e11d48','#0891b2','#d97706','#64748b'];

    el.innerHTML=`<div class="ctn-an">
        <!-- Types -->
        <div class="ctn-ac"><div class="ctn-at"><i class="fas fa-box-open"></i> ${LANG==='th'?'ประเภทบรรจุภัณฑ์':'Container Types'}</div>
        <div class="ctn-bc">${(stats.types||[]).map((t,i)=>`<div class="ctn-br">
            <div class="ctn-bl">${typeLabels[t.container_type]||t.container_type||'N/A'}</div>
            <div class="ctn-bt"><div class="ctn-bfl" style="width:${(t.cnt/maxType*100).toFixed(1)}%;background:${colors[i%8]}">${t.cnt}</div></div>
            <div class="ctn-bv">${(t.cnt/total*100).toFixed(0)}%</div>
        </div>`).join('')}</div></div>

        <!-- Status -->
        <div class="ctn-ac"><div class="ctn-at"><i class="fas fa-chart-pie"></i> ${LANG==='th'?'สถานะ':'Status Distribution'}</div>
        <div style="display:flex;flex-wrap:wrap;gap:14px;justify-content:center;padding:10px">
            ${(stats.statuses||[]).map(s=>{
                const sc={active:'#16a34a',empty:'#dc2626',expired:'#be185d',quarantined:'#d97706',disposed:'#64748b'};
                return `<div style="text-align:center"><div style="width:48px;height:48px;border-radius:50%;background:${sc[s.status]||'#999'};color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;margin:0 auto 4px">${s.cnt}</div><div style="font-size:10px;color:var(--c3);text-transform:capitalize">${s.status}</div></div>`;
            }).join('')}
        </div></div>

        <!-- Top Chemicals -->
        <div class="ctn-ac"><div class="ctn-at"><i class="fas fa-flask"></i> ${LANG==='th'?'สารเคมีที่มีขวดมากสุด':'Top Chemicals'}</div>
        <div class="ctn-bc">${(stats.top_chemicals||[]).map((t,i)=>`<div class="ctn-br">
            <div class="ctn-bl" title="${esc(t.chemical_name)}">${esc(t.chemical_name)}</div>
            <div class="ctn-bt"><div class="ctn-bfl" style="width:${(t.cnt/maxChem*100).toFixed(1)}%;background:${colors[i%8]}">${t.cnt}</div></div>
            <div class="ctn-bv">${t.cnt}</div>
        </div>`).join('')}</div></div>

        <!-- Top Owners -->
        <div class="ctn-ac"><div class="ctn-at"><i class="fas fa-users"></i> ${LANG==='th'?'เจ้าของขวดมากสุด':'Top Owners'}</div>
        <div class="ctn-bc">${(stats.top_owners||[]).map((t,i)=>`<div class="ctn-br">
            <div class="ctn-bl" title="${esc(t.owner_name)}">${esc(t.owner_name)}</div>
            <div class="ctn-bt"><div class="ctn-bfl" style="width:${(t.cnt/maxOwn*100).toFixed(1)}%;background:${colors[i%8]}">${t.cnt}</div></div>
            <div class="ctn-bv">${t.cnt}</div>
        </div>`).join('')}</div></div>
    </div>`;
    document.getElementById('pager').style.display='none';
}

// ── Pager ──
function renderPager(){
    const el=document.getElementById('pager');
    if(totalPages<=1&&totalItems<=10){el.style.display='none';return}
    el.style.display='flex';
    let html=`<button ${curPage<=1?'disabled':''} onclick="goPage(${curPage-1})"><i class="fas fa-chevron-left"></i></button>`;
    for(let i=1;i<=totalPages;i++){
        if(totalPages>7&&Math.abs(i-curPage)>2&&i!==1&&i!==totalPages){if(i===2||i===totalPages-1)html+='<span style="padding:0 4px;color:var(--c3)">…</span>';continue}
        html+=`<button class="${i===curPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
    }
    html+=`<span class="ctn-pager-info">${totalItems} ${LANG==='th'?'รายการ':'items'}</span>`;
    html+=`<button ${curPage>=totalPages?'disabled':''} onclick="goPage(${curPage+1})"><i class="fas fa-chevron-right"></i></button>`;
    html+=`<div class="ctn-pager-size"><span>${LANG==='th'?'แสดง':'Show'}</span><select onchange="changePageSize(+this.value)">${[10,20,50,100].map(n=>`<option value="${n}"${n===pageSize?' selected':''}>${n}</option>`).join('')}</select><span>${LANG==='th'?'รายการ/หน้า':'/page'}</span></div>`;
    el.innerHTML=html;
}
function goPage(p){curPage=p;loadData()}
function changePageSize(n){pageSize=n;curPage=1;loadData()}

// ── Tabs ──
function switchTab(tab,btn){
    curTab=tab;curPage=1;
    document.querySelectorAll('.ctn-tab').forEach(t=>t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('myBanner').style.display=tab==='my'?'flex':'none';
    if(tab==='my'&&stats){
        document.getElementById('myBannerTotal').textContent=stats.my_total;
        document.getElementById('myBannerActive').textContent=stats.my_active;
    }
    loadData();
}

// ── Views ──
function switchView(v,btn){
    curView=v;
    document.querySelectorAll('#viewSwitcher button').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    render();
}

// ── Filter ──
function toggleFilter(){document.getElementById('filterPanel').classList.toggle('show')}

// ── Detail Modal ──
async function openDetail(id){
    const ov=document.getElementById('detailOverlay');
    const md=document.getElementById('detailModal');
    md.innerHTML='<div style="padding:60px;text-align:center;color:var(--c3)"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></div>';
    ov.classList.add('show');
    try{
        const d=await apiFetch('/v1/api/containers.php?action=detail&id='+id);
        if(!d.success)throw new Error(d.error);
        const c=d.data;
        const pct=parseFloat(c.remaining_percentage)||0;
        const ar=c.ar_data||{};
        const isExp=c.expiry_date&&new Date(c.expiry_date)<new Date();
        const hazards=(c.hazard_pictograms||[]);
        const history=c.history||[];

        // GHS icon mapping
        const ghsIcons={
            compressed_gas:'fa-wind',flammable:'fa-fire-flame-curved',oxidizing:'fa-circle-radiation',
            toxic:'fa-skull-crossbones',corrosive:'fa-flask-vial',irritant:'fa-exclamation-triangle',
            environmental:'fa-leaf',health_hazard:'fa-heart-crack',explosive:'fa-explosion'
        };
        const ghsLabels={
            compressed_gas:LANG==='th'?'ก๊าซอัด':'Compressed Gas',
            flammable:LANG==='th'?'ไวไฟ':'Flammable',
            oxidizing:LANG==='th'?'วัตถุออกซิไดซ์':'Oxidizing',
            toxic:LANG==='th'?'พิษเฉียบพลัน':'Toxic',
            corrosive:LANG==='th'?'กัดกร่อน':'Corrosive',
            irritant:LANG==='th'?'ระคายเคือง':'Irritant',
            environmental:LANG==='th'?'อันตรายต่อสิ่งแวดล้อม':'Environmental Hazard',
            health_hazard:LANG==='th'?'อันตรายต่อสุขภาพ':'Health Hazard',
            explosive:LANG==='th'?'วัตถุระเบิด':'Explosive'
        };

        // Build GHS diamonds
        const ghsHtml=hazards.length?`<div class="ghs-row">${hazards.map(h=>
            `<div class="ghs-diamond ghs-${h}" title="${ghsLabels[h]||h}">
                <div class="ghs-diamond-inner"><i class="fas ${ghsIcons[h]||'fa-exclamation'}"></i></div>
                <div class="ghs-tooltip">${ghsLabels[h]||h}</div>
            </div>`
        ).join('')}</div>`:'';

        // Signal word badge
        const signalHtml=c.signal_word?
            (c.signal_word==='Danger'
                ?`<span class="signal-danger"><i class="fas fa-radiation"></i> ${LANG==='th'?'อันตราย':'DANGER'}</span>`
                :`<span class="signal-warning"><i class="fas fa-exclamation-triangle"></i> ${LANG==='th'?'ระวัง':'WARNING'}</span>`)
            :'';

        // GHS classifications
        const ghsClassHtml=(c.ghs_classifications||[]).length?
            `<div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:6px">${c.ghs_classifications.map(g=>
                `<span style="font-size:9px;padding:2px 7px;border-radius:5px;background:#fef2f2;color:#991b1b;font-weight:600;border:1px solid #fecaca">${esc(g)}</span>`
            ).join('')}</div>`:'';

        // 3D Viewer with AR button
        let viewer3d='';
        if(ar.has_model){
            const arBtn=`<a href="/v1/ar/view_ar.php?id=${c.id}&source=${c.source||''}" target="_blank" class="ar-btn" onclick="event.stopPropagation()"><i class="fas fa-vr-cardboard"></i> ${LANG==='th'?'ดู AR':'View AR'}</a>`;
            if(ar.model_type==='embed'){
                viewer3d=`<div class="ctn-3d-viewer">
                    <div class="ctn-3d-label"><span><i class="fas fa-cube"></i> 3D Preview</span>${signalHtml?'<span>'+signalHtml+'</span>':''}</div>
                    <iframe src="${ar.model_url}" allow="autoplay; fullscreen" allowfullscreen></iframe>
                    <div class="ctn-3d-actions">
                        <button onclick="window.open('${ar.model_url}','_blank')"><i class="fas fa-expand"></i> ${LANG==='th'?'เต็มจอ':'Fullscreen'}</button>
                        ${arBtn}
                    </div>
                </div>`;
            }else{
                viewer3d=`<div class="ctn-3d-viewer">
                    <div class="ctn-3d-label"><span><i class="fas fa-cube"></i> 3D Preview</span>${signalHtml?'<span>'+signalHtml+'</span>':''}</div>
                    <iframe src="/v1/pages/viewer3d.php?src=${encodeURIComponent(ar.model_url)}&embed=1&transparent=0&title=${encodeURIComponent(c.chemical_name||'')}" style="width:100%;height:100%;border:none"></iframe>
                    <div class="ctn-3d-actions">
                        <button onclick="window.open('/v1/pages/viewer3d.php?src=${encodeURIComponent(ar.model_url)}&title=${encodeURIComponent(c.chemical_name||'')}','_blank')"><i class="fas fa-expand"></i> ${LANG==='th'?'เต็มจอ':'Fullscreen'}</button>
                        ${arBtn}
                    </div>
                </div>`;
            }
        }else{
            viewer3d=`<div class="ctn-3d-viewer">
                <div class="ctn-3d-label"><span><i class="fas fa-cube"></i> 3D Preview</span></div>
                <div class="no-model">
                    <i class="fas fa-cube"></i>
                    <p>${LANG==='th'?'ยังไม่มีโมเดล 3D สำหรับบรรจุภัณฑ์นี้':'No 3D model available for this container type'}</p>
                    <a href="/v1/ar/view_ar.php?id=${c.id}&source=${c.source||""}" target="_blank" style="margin-top:4px;font-size:11px;color:#0d9488;text-decoration:none;display:flex;align-items:center;gap:4px"><i class="fas fa-vr-cardboard"></i> ${LANG==='th'?'ลองดูใน AR':'Try AR View'}</a>
                </div>
            </div>`;
        }

        // Fluid level
        const fluidColor=pct>50?'linear-gradient(to top,#3b82f6,#60a5fa)':pct>20?'linear-gradient(to top,#eab308,#fbbf24)':'linear-gradient(to top,#ef4444,#f87171)';

        // Chemical properties row
        const propsData=[];
        if(c.molecular_formula)propsData.push({v:c.molecular_formula,l:LANG==='th'?'สูตร':'Formula'});
        if(c.molecular_weight)propsData.push({v:parseFloat(c.molecular_weight).toFixed(2),l:'MW (g/mol)'});
        if(c.physical_state){
            const stateMap={solid:LANG==='th'?'ของแข็ง':'Solid',liquid:LANG==='th'?'ของเหลว':'Liquid',gas:LANG==='th'?'ก๊าซ':'Gas'};
            propsData.push({v:stateMap[c.physical_state]||c.physical_state,l:LANG==='th'?'สถานะ':'State'});
        }
        propsData.push({v:`${pct.toFixed(0)}%`,l:LANG==='th'?'คงเหลือ':'Remaining'});
        propsData.push({v:`${c.current_quantity||0} ${esc(c.quantity_unit||'')}`,l:LANG==='th'?'ปริมาณ':'Qty'});

        const propsHtml=propsData.length?`<div class="ctn-chem-props">${propsData.map(p=>
            `<div class="ctn-chem-prop"><div class="prop-v">${p.v}</div><div class="prop-l">${p.l}</div></div>`
        ).join('')}</div>`:'';

        md.innerHTML=`
        <div class="ctn-mh">
            <h3><i class="fas fa-box" style="color:#2563eb"></i> ${esc(c.chemical_name||'Container')} ${badgeHtml(c.status||'active')} ${srcBadge(c.source)}</h3>
            <button class="ctn-mx" onclick="closeDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="ctn-mb">
            ${viewer3d}

            <!-- Chemical Info Card -->
            <div class="ctn-chem-card">
                <div class="ctn-chem-header">
                    <div class="ctn-chem-fluid">
                        <div class="ctn-fluid" style="width:48px;height:76px">
                            <div class="ctn-fluid-fill" style="height:${pct}%;background:${fluidColor}"></div>
                            <div class="ctn-fluid-pct">${pct.toFixed(0)}%</div>
                        </div>
                    </div>
                    <div class="ctn-chem-body">
                        <div class="ctn-chem-name">${esc(c.chemical_name||'-')}</div>
                        ${c.cas_number?`<div class="ctn-chem-sub">CAS: <b>${c.cas_number}</b></div>`:''}
                        <div class="ctn-chem-tags">
                            <span class="ctn-card-tag" style="background:#f0f4ff;color:#2563eb"><i class="fas ${typeIcons[c.container_type]||'fa-box'}" style="font-size:9px"></i> ${typeLabels[c.container_type]||c.container_type||'-'}</span>
                            ${c.container_material?`<span class="ctn-card-tag" style="background:#f5f5f4;color:#78716c">${c.container_material}</span>`:''}
                            ${c.grade?`<span class="ctn-card-tag" style="background:#f0fdf4;color:#15803d">${esc(c.grade)}</span>`:''}
                            ${signalHtml}
                        </div>
                        ${ghsHtml}
                        ${ghsClassHtml}
                    </div>
                </div>
                ${propsHtml}
            </div>

            <!-- Detail Grid -->
            <div class="ctn-dg" style="margin-bottom:16px">
                <div><div class="ctn-dlb">${LANG==='th'?'รหัสขวด':'Bottle Code'}</div><div class="ctn-dvl"><code style="font-size:12px;background:#f1f5f9;padding:2px 8px;border-radius:4px;font-family:'Courier New',monospace;letter-spacing:0.5px">${esc(c.bottle_code||'-')}</code></div></div>
                <div><div class="ctn-dlb">QR Code</div><div class="ctn-dvl" style="font-family:monospace;font-size:11px">${esc(c.qr_code||'-')}</div></div>
                <div><div class="ctn-dlb">${LANG==='th'?'เจ้าของ':'Owner'}</div><div class="ctn-dvl"><i class="fas fa-user" style="color:#2563eb;font-size:11px;margin-right:4px"></i>${esc(c.owner_name||'-')}</div></div>
                <div><div class="ctn-dlb">${LANG==='th'?'ตำแหน่ง':'Location'}</div><div class="ctn-dvl"><i class="fas fa-map-marker-alt" style="color:#dc2626;font-size:11px;margin-right:4px"></i>${esc(c.location_text||'-')}</div></div>
                <div><div class="ctn-dlb">${LANG==='th'?'ห้อง/แลป':'Lab'}</div><div class="ctn-dvl">${esc(c.lab_name||'-')}</div></div>
                ${c.manufacturer_name?`<div><div class="ctn-dlb">${LANG==='th'?'ผู้ผลิต':'Manufacturer'}</div><div class="ctn-dvl">${esc(c.manufacturer_name)}</div></div>`:''}
                ${c.cost?`<div><div class="ctn-dlb">${LANG==='th'?'ราคา':'Cost'}</div><div class="ctn-dvl" style="color:#16a34a;font-weight:700">${parseFloat(c.cost).toLocaleString()} ฿</div></div>`:''}
                ${c.batch_number?`<div><div class="ctn-dlb">${LANG==='th'?'Batch No.':'Batch No.'}</div><div class="ctn-dvl" style="font-family:monospace">${esc(c.batch_number)}</div></div>`:''}
                ${c.lot_number?`<div><div class="ctn-dlb">${LANG==='th'?'Lot No.':'Lot No.'}</div><div class="ctn-dvl" style="font-family:monospace">${esc(c.lot_number)}</div></div>`:''}
                ${c.expiry_date?`<div><div class="ctn-dlb">${LANG==='th'?'วันหมดอายุ':'Expiry'}</div><div class="ctn-dvl" style="${isExp?'color:#dc2626;font-weight:700':''}"><i class="fas fa-calendar-alt" style="font-size:10px;margin-right:4px"></i>${formatDate(c.expiry_date)}${isExp?' <span style="font-size:10px">⚠️ '+( LANG==='th'?'หมดอายุแล้ว':'Expired')+'</span>':''}</div></div>`:''}
                ${c.received_date?`<div><div class="ctn-dlb">${LANG==='th'?'วันที่รับ':'Received'}</div><div class="ctn-dvl">${formatDate(c.received_date)}</div></div>`:''}
                ${c.invoice_number?`<div><div class="ctn-dlb">${LANG==='th'?'เลขที่ใบแจ้งหนี้':'Invoice'}</div><div class="ctn-dvl">${esc(c.invoice_number)}</div></div>`:''}
                ${c.notes?`<div class="ctn-df"><div class="ctn-dlb">${LANG==='th'?'หมายเหตุ':'Notes'}</div><div class="ctn-dvl">${esc(c.notes)}</div></div>`:''}
            </div>

            ${history.length?`
            <div style="padding-top:16px;border-top:1px solid var(--border)">
                <h4 style="font-size:12px;font-weight:700;color:var(--c1);margin-bottom:12px;display:flex;align-items:center;gap:6px"><i class="fas fa-history" style="color:#2563eb"></i> ${LANG==='th'?'ประวัติ':'History'} <span style="font-size:10px;color:var(--c3);font-weight:400">(${history.length})</span></h4>
                <div class="ctn-tl">${history.map(h=>`
                    <div class="ctn-tl-item ${h.action_type||''}">
                        <div class="ctn-tl-act">${h.action_type||'-'}</div>
                        <div class="ctn-tl-det">${esc(h.notes||'')} ${h.quantity_change?'(<span style=\"font-weight:700;color:'+(parseFloat(h.quantity_change)<0?'#dc2626':'#16a34a')+'\">'+(parseFloat(h.quantity_change)>0?'+':'')+h.quantity_change+'</span>)':''} — ${esc(h.user_name||'')}</div>
                        <div class="ctn-tl-time"><i class="fas fa-clock" style="font-size:9px;margin-right:3px"></i>${formatDate(h.created_at)}</div>
                    </div>
                `).join('')}</div>
            </div>`:''}

            <!-- Action Buttons -->
            <div class="ctn-da" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
                ${c.qr_code_image?`<a href="${c.qr_code_image}" target="_blank" class="ctn-btn ctn-btn-o ctn-btn-s"><i class="fas fa-qrcode"></i> QR</a>`:''}
                ${c.sds_url?`<a href="${c.sds_url}" target="_blank" class="ctn-btn ctn-btn-g ctn-btn-s"><i class="fas fa-file-pdf"></i> SDS</a>`:''}
                ${ar.has_model?`<button onclick="window.open('/v1/pages/viewer3d.php?src=${encodeURIComponent(ar.model_url||'')}&title=${encodeURIComponent(c.chemical_name||'')}','_blank')" class="ctn-btn ctn-btn-s" style="background:#6C5CE7;color:#fff"><i class="fas fa-cube"></i> ${LANG==='th'?'ดู 3D':'3D View'}</button>`:''}
                <a href="/v1/ar/view_ar.php?id=${c.id}&source=${c.source||""}" target="_blank" class="ctn-btn ctn-btn-s" style="background:linear-gradient(135deg,#0d9488,#14b8a6);color:#fff"><i class="fas fa-vr-cardboard"></i> ${LANG==='th'?'ดู AR':'AR View'}</a>
            </div>
        </div>`;
    }catch(e){
        md.innerHTML=`<div style="padding:40px;text-align:center;color:#dc2626"><i class="fas fa-exclamation-triangle" style="font-size:24px;margin-bottom:8px"></i><p>${e.message}</p><button class="ctn-btn ctn-btn-g" onclick="closeDetail()" style="margin-top:12px">Close</button></div>`;
    }
}
function closeDetail(){document.getElementById('detailOverlay').classList.remove('show')}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDetail()});

// ── Helpers ──
function esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML}
function formatDate(d){if(!d)return '—';try{return new Date(d).toLocaleDateString(LANG==='th'?'th-TH':'en-US',{day:'numeric',month:'short',year:'numeric'})}catch(e){return d}}

// ── Load building filter ──
async function loadBuildingFilter(){
    try{
        const d=await apiFetch('/v1/api/locations.php?type=buildings');
        if(d.success){
            const sel=document.getElementById('buildingFilter');
            d.data.forEach(b=>{const o=document.createElement('option');o.value=b.id;o.textContent=b.shortname||b.name;sel.appendChild(o)});
        }
    }catch(e){}
}

// ── Event Listeners ──
let sTimer;
document.getElementById('searchInput').addEventListener('input',()=>{clearTimeout(sTimer);sTimer=setTimeout(()=>{curPage=1;loadData()},300)});
document.getElementById('sortSelect').addEventListener('change',()=>{curPage=1;loadData()});
document.getElementById('statusFilter')?.addEventListener('change',()=>{curPage=1;loadData()});
document.getElementById('typeFilter')?.addEventListener('change',()=>{curPage=1;loadData()});
document.getElementById('buildingFilter')?.addEventListener('change',()=>{curPage=1;loadData()});
document.getElementById('sourceFilter')?.addEventListener('change',()=>{curPage=1;loadData()});

// ── Init ──
loadStats();
loadData();
loadBuildingFilter();

<?php endif; ?>
</script>
</body></html>
