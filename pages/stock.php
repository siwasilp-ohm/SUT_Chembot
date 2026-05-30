<?php
/**
 * Chemical Stock Management Page — Pro Edition (ขวดสารเคมีในคลัง)
 * 
 * Now powered by Containers API — same data source as containers.php
 * 
 * Role-based views:
 *   admin       → Full CRUD, import/export, see ALL
 *   ceo         → Read-only, see ALL, export
 *   lab_manager → See own + team, manage own
 *   user        → See/manage own only
 *
 * Views: Table / Grid / Compact / Analytics
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
$canEdit = in_array($role, ['admin','lab_manager']);          // add / delete bottles
$canAct  = in_array($role, ['admin','lab_manager','user']);   // use / borrow / transfer (own items)
$canViewOnly = ($role === 'user'); // still flagged for contextual read-only notice
$canSeeAll = $isAdmin || $isCeo;
$userDisplayName = $user['full_name_th'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$userInitial = mb_substr(preg_replace('/^(นาย|นางสาว|นาง|ดร\.)\s*/u', '', $userDisplayName), 0, 1, 'UTF-8');
Layout::head($lang === 'th' ? 'คลังสารเคมี — ขวดสาร' : 'Chemical Stock');
?>
<style>
:root{--stk-r:14px;--stk-rs:10px;--stk-sh:0 1px 6px rgba(0,0,0,.06);--stk-shm:0 4px 20px rgba(0,0,0,.08);--sg:#16a34a;--sy:#d97706;--sr:#dc2626;--sb:#2563eb;--sp:#7c3aed;--st:#0d9488}

/* ── Hero Banner ── */
.stk-src{display:inline-flex;align-items:center;gap:3px;font-size:8px;font-weight:700;padding:1px 6px;border-radius:4px;letter-spacing:.3px;text-transform:uppercase;vertical-align:middle}
.stk-src-container{background:#dbeafe;color:#2563eb}
.stk-src-stock{background:#fef3c7;color:#92400e}

.stk-hero{background:linear-gradient(135deg,#065f46 0%,#0d9488 50%,#14b8a6 100%);border-radius:var(--stk-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.stk-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.stk-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.stk-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px;position:relative}
.stk-hero-info p{font-size:12px;opacity:.85;margin:0;position:relative}
.stk-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0}
.stk-hero-c{text-align:center;position:relative}
.stk-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.stk-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}

/* ── Stats Row ── */
.stk-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.stk-stat{background:#fff;border-radius:var(--stk-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--stk-sh);border:1px solid var(--border);transition:all .15s;cursor:pointer}
.stk-stat:hover{transform:translateY(-2px);box-shadow:var(--stk-shm)}
.stk-stat.af{border-color:var(--accent);background:#f0fdf4}
.stk-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.stk-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.stk-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Tabs ── */
.stk-tabs{display:inline-flex;background:#f1f5f9;border-radius:var(--stk-rs);padding:3px}
.stk-tab{padding:8px 20px;font-size:12px;font-weight:600;color:var(--c3);border-radius:8px;cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;display:flex;align-items:center;gap:6px}
.stk-tab:hover{color:var(--c1)}
.stk-tab.active{background:#fff;color:var(--accent);box-shadow:0 1px 4px rgba(0,0,0,.08)}
.stk-tab .bg{font-size:9px;padding:2px 7px;border-radius:10px;font-weight:700;background:#e2e8f0;color:var(--c3)}
.stk-tab.active .bg{background:var(--accent);color:#fff}

/* ── My Banner ── */
.stk-my{background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:var(--stk-r);padding:16px 20px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:16px}
.stk-my-av{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;overflow:hidden;flex-shrink:0}
.stk-my-av img{width:100%;height:100%;object-fit:cover}
.stk-my h3{font-size:14px;font-weight:700;margin:0}
.stk-my p{font-size:11px;opacity:.8;margin:2px 0 0}

/* ── Toolbar ── */
.stk-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}
.stk-search{flex:1;min-width:220px;position:relative}
.stk-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:var(--stk-rs);font-size:13px;background:#fff;color:var(--c1);transition:border .15s}
.stk-search input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,138,92,.1)}
.stk-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:13px}
.stk-btn{padding:8px 16px;border:none;border-radius:var(--stk-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap;text-decoration:none}
.stk-btn-p{background:var(--accent);color:#fff}.stk-btn-p:hover{filter:brightness(1.08)}
.stk-btn-o{background:#fff;color:var(--accent);border:1.5px solid var(--accent)}.stk-btn-o:hover{background:var(--accent);color:#fff}
.stk-btn-d{background:#dc2626;color:#fff}.stk-btn-d:hover{background:#b91c1c}
.stk-btn-g{background:transparent;color:var(--c3);border:1.5px solid var(--border)}.stk-btn-g:hover{border-color:var(--accent);color:var(--accent)}
.stk-btn-s{padding:5px 10px;font-size:11px}

/* ── View Switcher ── */
.stk-vw{display:flex;border:1.5px solid var(--border);border-radius:var(--stk-rs);overflow:hidden}
.stk-vw button{padding:7px 11px;border:none;background:#fff;color:var(--c3);cursor:pointer;font-size:12px;transition:all .12s;display:flex;align-items:center;gap:4px}
.stk-vw button+button{border-left:1px solid var(--border)}
.stk-vw button.active{background:var(--accent);color:#fff}
.stk-vw button:hover:not(.active){background:#f8fafc}

/* ── Filter Panel ── */
.stk-fp{max-height:0;overflow:hidden;transition:max-height .25s ease,margin .25s ease,padding .25s ease;background:#fff;border:1.5px solid transparent;border-radius:var(--stk-r);margin-bottom:0}
.stk-fp.show{max-height:300px;border-color:var(--border);padding:16px;margin-bottom:14px;box-shadow:var(--stk-sh)}
.stk-fg2{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
.stk-fl label{font-size:10px;font-weight:700;color:var(--c3);display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px}
.stk-fl select{width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff;color:var(--c1)}
.stk-fl select:focus{outline:none;border-color:var(--accent)}
.stk-fa{display:flex;gap:8px;margin-top:10px;justify-content:flex-end}

/* ── Table View ── */
.stk-tw{overflow-x:auto;border-radius:var(--stk-r);border:1px solid var(--border);background:#fff;box-shadow:var(--stk-sh)}
.stk-t{width:100%;border-collapse:collapse;font-size:12px}
.stk-t th{background:#f8fafc;padding:10px 12px;text-align:left;font-weight:700;color:var(--c3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);white-space:nowrap;cursor:pointer;user-select:none;transition:color .12s;position:sticky;top:0;z-index:1}
.stk-t th:hover{color:var(--accent)}
.stk-t td{padding:10px 12px;border-bottom:1px solid #f1f5f9;color:var(--c1);vertical-align:middle}
.stk-t tbody tr{transition:background .1s;cursor:pointer}

/* ── Grid/Card View ── */
.stk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:12px}
.stk-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--stk-r);overflow:hidden;transition:all .18s;cursor:pointer;position:relative}
.stk-card:hover{border-color:var(--accent);box-shadow:var(--stk-shm);transform:translateY(-2px)}
.stk-card.me{border-left:3px solid var(--sb)}
.stk-card.pending-transfer{background:#fffbeb;border-color:#fde68a!important}
.stk-card.pending-transfer:hover{border-color:#f59e0b!important}
.stk-cr.pending-transfer{background:#fffbeb!important;border-left:3px solid #f59e0b!important}
.stk-pt-badge{display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;white-space:nowrap}
.stk-card.pending-borrow{background:#eff6ff;border-color:#bfdbfe!important}
.stk-card.pending-borrow:hover{border-color:#93c5fd!important}
.stk-cr.pending-borrow{background:#eff6ff!important;border-left:3px solid #2563eb!important}
tr.pending-borrow td{background:#eff6ff!important}
tr.pending-borrow{border-left:3px solid #2563eb}
.stk-pb-badge{display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;padding:2px 7px;border-radius:4px;background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;white-space:nowrap}
.stk-card-hd{display:flex;align-items:flex-start;gap:10px;padding:16px 16px 0}
.stk-card-ic{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.stk-card-nm{font-size:13px;font-weight:700;color:var(--c1);line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.stk-card-cd{font-size:10px;color:var(--c2);font-family:'Courier New',monospace;margin-top:2px;background:#f1f5f9;padding:1px 6px;border-radius:3px;display:inline-block;letter-spacing:0.3px}
.stk-card-bd{padding:10px 16px 16px}
.stk-card-tg{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px}
.stk-card-tag{font-size:9px;padding:2px 7px;border-radius:6px;font-weight:600}
.stk-card-bar{height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-bottom:6px}
.stk-card-bf{height:100%;border-radius:3px;transition:width .3s}
.stk-card-ft{display:flex;justify-content:space-between;align-items:center;font-size:10px;color:var(--c3)}
.stk-card-row{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--c2);margin-top:4px}
.stk-card-row i{width:14px;text-align:center;color:var(--c3);font-size:10px}
.stk-av{width:20px;height:20px;border-radius:50%;background:var(--accent);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:8px;font-weight:700;margin-right:4px;vertical-align:middle}
.stk-3d-badge{position:absolute;top:10px;right:10px;background:linear-gradient(135deg,#6C5CE7,#a855f7);color:#fff;font-size:9px;padding:3px 8px;border-radius:6px;font-weight:700;display:flex;align-items:center;gap:4px}

/* ── Compact View ── */
.stk-compact{display:flex;flex-direction:column;gap:4px}
.stk-cr{display:flex;align-items:center;gap:10px;padding:8px 14px;background:#fff;border-radius:8px;border:1px solid var(--border);cursor:pointer;transition:all .1s;font-size:12px}
.stk-cr:hover{background:#f0fdf4;border-color:var(--accent)}
.stk-cr.me{border-left:3px solid var(--sb)}
.stk-cn{flex:1;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.stk-cc{color:var(--c2);font-size:10px;width:100px;flex-shrink:0;font-family:'Courier New',monospace;letter-spacing:0.3px}
.stk-cb{width:50px;height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden;flex-shrink:0}
.stk-cb div{height:100%;border-radius:2px}
.stk-cp{font-weight:700;font-size:11px;width:35px;text-align:right;flex-shrink:0}
.stk-co{font-size:10px;color:var(--c3);width:90px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.stk-cl{font-size:10px;color:var(--c3);width:120px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.stk-owner-inline{display:none;font-size:9.5px;color:var(--c3);margin-top:3px;align-items:center;gap:5px;flex-wrap:wrap}
@media(max-width:1024px){.stk-owner-inline{display:flex}}

/* ── Analytics View ── */
.stk-an{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}
.stk-ac{background:#fff;border-radius:var(--stk-r);border:1px solid var(--border);padding:18px;box-shadow:var(--stk-sh)}
.stk-at{font-size:12px;font-weight:700;color:var(--c1);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.stk-at i{color:var(--accent)}
.stk-bc{display:flex;flex-direction:column;gap:6px}
.stk-br{display:flex;align-items:center;gap:8px;font-size:11px}
.stk-bl{width:100px;text-align:right;color:var(--c3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex-shrink:0}
.stk-bt{flex:1;height:18px;background:#f1f5f9;border-radius:4px;overflow:hidden;position:relative}
.stk-bf{height:100%;border-radius:4px;display:flex;align-items:center;padding-left:6px;font-size:9px;font-weight:700;color:#fff;transition:width .4s}
.stk-bv{font-weight:700;color:var(--c1);width:40px;flex-shrink:0}
.stk-dn{display:flex;align-items:center;gap:20px;justify-content:center}
.stk-dl2{display:flex;flex-direction:column;gap:6px}
.stk-di{display:flex;align-items:center;gap:6px;font-size:11px}
.stk-dd{width:10px;height:10px;border-radius:3px;flex-shrink:0}

/* ── Badges ── */
.stk-badge{font-size:9px;padding:3px 8px;border-radius:6px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;display:inline-block}
.stk-badge-active{background:#dcfce7;color:#15803d}
.stk-badge-low{background:#fef9c3;color:#a16207}
.stk-badge-empty{background:#fee2e2;color:#dc2626}
.stk-badge-expired{background:#fce7f3;color:#be185d}
.stk-badge-quarantined{background:#fef3c7;color:#d97706}
.stk-badge-disposed{background:#f1f5f9;color:#64748b}
.bar-ok{background:linear-gradient(90deg,#22c55e,#16a34a)}
.bar-mid{background:linear-gradient(90deg,#eab308,#f59e0b)}
.bar-low{background:linear-gradient(90deg,#ef4444,#dc2626)}

/* ── Type Icons ── */
.type-icon{width:28px;height:28px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.type-bottle{background:#dbeafe;color:#2563eb}
.type-vial{background:#ede9fe;color:#7c3aed}
.type-flask{background:#d1fae5;color:#059669}
.type-canister{background:#fed7aa;color:#ea580c}
.type-cylinder{background:#fecdd3;color:#e11d48}
.type-ampoule{background:#e0e7ff;color:#4338ca}
.type-bag{background:#f5f5f4;color:#78716c}
.type-other{background:#f1f5f9;color:#64748b}

/* ── GHS Pictograms ── */
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

/* ── Signal Word ── */
.signal-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;display:inline-flex;align-items:center;gap:4px;animation:signal-pulse 2s infinite}
.signal-warning{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;display:inline-flex;align-items:center;gap:4px}
@keyframes signal-pulse{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.3)}50%{box-shadow:0 0 0 6px rgba(220,38,38,0)}}

/* ── Fluid Level ── */
.stk-fluid{width:44px;height:70px;border:2px solid var(--accent);border-radius:8px;position:relative;overflow:hidden;background:#f0fdf4;flex-shrink:0}
.stk-fluid-fill{position:absolute;bottom:0;left:0;right:0;transition:height .5s;border-radius:0 0 5px 5px}
.stk-fluid-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#065f46;text-shadow:0 0 4px rgba(255,255,255,.8)}

/* ── Chemical Info Card ── */
.stk-chem-card{background:linear-gradient(135deg,#f8faf8,#f0fdf4);border:1.5px solid #bbf7d0;border-radius:14px;padding:18px;margin-bottom:16px}
.stk-chem-header{display:flex;gap:14px;align-items:flex-start}
.stk-chem-body{flex:1;min-width:0}
.stk-chem-name{font-size:18px;font-weight:800;color:var(--c1);margin-bottom:2px;line-height:1.25}
.stk-chem-sub{font-size:12px;color:var(--c3);margin-bottom:2px;display:flex;align-items:center;gap:6px}
.stk-chem-sub b{color:var(--c1);font-weight:600}
.stk-chem-tags{display:flex;gap:4px;flex-wrap:wrap;margin-top:8px}
.stk-chem-props{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0}
.stk-chem-prop{text-align:center;padding:6px 4px;background:#fff;border-radius:8px;border:1px solid #e2e8f0}
.stk-chem-prop .prop-v{font-size:14px;font-weight:800;color:var(--c1)}
.stk-chem-prop .prop-l{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.3px;margin-top:1px}

/* ── 3D Viewer ── */
.stk-3d-viewer{background:linear-gradient(135deg,#0c0c1d 0%,#1a1a3e 100%);border-radius:14px;overflow:hidden;height:280px;margin-bottom:16px;position:relative;border:1px solid rgba(108,92,231,.25)}
.stk-3d-viewer iframe{width:100%;height:100%;border:none}
.stk-3d-viewer .no-model{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#555;gap:8px}
.stk-3d-viewer .no-model i{font-size:40px;opacity:.3;color:#6C5CE7}
.stk-3d-viewer .no-model p{font-size:12px;color:#888}
.stk-3d-actions{position:absolute;bottom:12px;left:12px;right:12px;display:flex;gap:6px;justify-content:flex-end}
.stk-3d-actions button,.stk-3d-actions a{padding:7px 14px;border:none;border-radius:8px;background:rgba(108,92,231,.85);color:#fff;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .18s;text-decoration:none;backdrop-filter:blur(4px)}
.stk-3d-actions button:hover,.stk-3d-actions a:hover{background:#6C5CE7;transform:translateY(-1px)}
.stk-3d-actions .ar-btn{background:linear-gradient(135deg,#0d9488,#14b8a6);box-shadow:0 2px 10px rgba(13,148,136,.4)}
.stk-3d-actions .ar-btn:hover{background:linear-gradient(135deg,#0f766e,#0d9488)}
.stk-3d-label{position:absolute;top:12px;left:12px;display:flex;gap:6px;align-items:center}
.stk-3d-label span{background:rgba(0,0,0,.5);backdrop-filter:blur(4px);color:#fff;font-size:10px;padding:4px 10px;border-radius:6px;font-weight:600;display:flex;align-items:center;gap:4px}

/* ── History Timeline ── */
.stk-tl{position:relative;padding-left:20px}
.stk-tl::before{content:'';position:absolute;left:6px;top:4px;bottom:4px;width:2px;background:#e2e8f0;border-radius:2px}
.stk-tl-item{position:relative;margin-bottom:14px;padding-left:12px}
.stk-tl-item::before{content:'';position:absolute;left:-18px;top:5px;width:10px;height:10px;border-radius:50%;border:2px solid var(--accent);background:#fff}
.stk-tl-item.created::before{background:#22c55e;border-color:#22c55e}
.stk-tl-item.used::before{background:#eab308;border-color:#eab308}
.stk-tl-item.moved::before{background:#3b82f6;border-color:#3b82f6}
.stk-tl-item.disposed::before{background:#ef4444;border-color:#ef4444}
.stk-tl-act{font-size:12px;font-weight:600;color:var(--c1);text-transform:capitalize}
.stk-tl-det{font-size:11px;color:var(--c3);margin-top:2px}
.stk-tl-time{font-size:10px;color:var(--c3);margin-top:1px}

/* ── Pagination ── */
.stk-pager{display:flex;align-items:center;justify-content:center;gap:3px;margin-top:14px;flex-wrap:wrap}
.stk-pager button{width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--c1);cursor:pointer;font-size:11px;font-weight:600;transition:all .12s;display:flex;align-items:center;justify-content:center}
.stk-pager button:hover:not(:disabled){border-color:var(--accent);color:var(--accent)}
.stk-pager button.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.stk-pager button:disabled{opacity:.3;cursor:default}
.stk-pager-info{font-size:11px;color:var(--c3);margin:0 8px}

/* ── Modal ── */
.stk-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .2s}
.stk-ov.show{opacity:1;visibility:visible}
.stk-md{background:#fff;border-radius:18px;width:96%;max-width:820px;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.92) translateY(10px);transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
.stk-ov.show .stk-md{transform:scale(1) translateY(0)}
.stk-md-sm{max-width:420px}
.stk-mh{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:2;border-bottom:1px solid transparent}
.stk-mh h3{font-size:16px;font-weight:700;color:var(--c1);display:flex;align-items:center;gap:8px}
.stk-mx{width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:14px;color:var(--c3);display:flex;align-items:center;justify-content:center;transition:all .12s}
.stk-mx:hover{background:#fee2e2;color:#dc2626}
.stk-mb{padding:16px 24px 24px}
.stk-dg{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.stk-dlb{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
.stk-dvl{font-size:13px;color:var(--c1);font-weight:500}
.stk-df{grid-column:1/-1}
.stk-da{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}

/* ── Toast ── */
.stk-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);background:#1a1a2e;color:#fff;padding:12px 24px;border-radius:var(--stk-rs);font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;z-index:99999;opacity:0;transition:all .3s}
.stk-toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.stk-toast.ok{background:#0d6832}.stk-toast.err{background:#c62828}

/* ── Label on-screen preview ── */
.stk-label{width:440px;background:#fff;border:2px solid #1e293b;border-radius:8px;overflow:hidden;position:relative;font-family:'Segoe UI',Arial,sans-serif;box-shadow:0 4px 20px rgba(0,0,0,.12)}
.stk-label-header{background:linear-gradient(135deg,#1e293b,#334155);display:flex;align-items:center;padding:8px 12px;gap:8px}
.stk-label-logo{width:26px;height:26px;background:linear-gradient(135deg,#10b981,#059669);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;flex-shrink:0}
.stk-label-hinfo{flex:1;overflow:hidden}
.stk-label-htitle{font-size:8px;font-weight:800;color:#fff;white-space:nowrap;letter-spacing:.3px}
.stk-label-hloc{font-size:7px;color:rgba(255,255,255,.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.stk-label-ar{background:linear-gradient(135deg,#6d28d9,#8b5cf6);color:#fff;font-size:7px;font-weight:800;padding:2px 6px;border-radius:4px;flex-shrink:0;letter-spacing:.5px}
.stk-label-hazrow{display:flex;align-items:center;gap:6px;padding:5px 12px;background:linear-gradient(to right,#fef2f2,#fff5f5);border-bottom:1px solid #fecaca;min-height:24px;flex-wrap:wrap}
.stk-label-signal{font-size:7.5px;font-weight:900;padding:2px 6px;border-radius:3px;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.stk-label-signal.danger{background:#dc2626;color:#fff}
.stk-label-signal.warning{background:#f59e0b;color:#000}
.stk-label-no-hazard{font-size:7px;color:#94a3b8;font-style:italic}
.stk-label-ghsstrip{display:flex;gap:4px;flex-wrap:wrap}
.stk-label-ghs{width:20px;height:20px;position:relative;flex-shrink:0}
.stk-label-ghs-inner{position:absolute;inset:1px;transform:rotate(45deg);border:1.5px solid #dc2626;border-radius:2px;display:flex;align-items:center;justify-content:center}
.stk-label-ghs-inner i{transform:rotate(-45deg);font-size:7px}
.stk-label-body{padding:8px 12px 6px}
.stk-label-chem{font-size:15px;font-weight:900;color:#0f172a;line-height:1.2;margin-bottom:2px}
.stk-label-formula{font-size:8.5px;color:#475569;margin-bottom:6px;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.stk-label-formula .lf-pill{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:1px 5px;font-family:monospace;font-size:8px;color:#334155;font-weight:700}
.stk-label-formula .lf-sep{color:#cbd5e1}
.stk-label-props{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:5px}
.stk-label-prop{background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:1px 6px;font-size:7.5px;color:#64748b}
.stk-label-prop b{color:#1e293b;font-weight:700}
.stk-label-qtyrow{display:flex;align-items:center;gap:7px;margin-bottom:4px}
.stk-label-qty{font-size:9px;font-weight:800;color:#1e293b;white-space:nowrap}
.stk-label-pbar{flex:1;height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden}
.stk-label-pfill{height:100%;border-radius:3px;transition:width .3s}
.stk-label-pct{font-size:9px;font-weight:900;min-width:30px;text-align:right}
.stk-label-metarow{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px}
.stk-label-exp{font-size:8px;font-weight:700;padding:1px 6px;border-radius:4px}
.stk-label-exp.fresh{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.stk-label-exp.warn{background:#fef3c7;color:#b45309;border:1px solid #fde68a}
.stk-label-exp.danger{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.stk-label-exp.nodate{background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0}
.stk-label-owner{font-size:7.5px;color:#475569;display:flex;align-items:center;gap:3px}
.stk-label-owner i{font-size:6px;color:#94a3b8}
.stk-label-batch{font-size:7px;color:#94a3b8;margin-bottom:2px;font-family:monospace}
.stk-label-codes{display:flex;align-items:stretch;gap:0;border-top:1.5px solid #e2e8f0;background:#fafafa}
.stk-label-qr{width:72px;height:72px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-right:1px solid #e2e8f0;padding:4px;background:#fff}
.stk-label-qr canvas,.stk-label-qr img,.stk-label-qr svg{max-width:100%;max-height:100%}
.stk-label-barcode{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4px 8px;overflow:hidden}
.stk-label-barcode svg{max-width:100%;height:36px!important}
.stk-label-barcode-text{font-family:'Courier New',monospace;font-size:7px;letter-spacing:.8px;color:#334155;margin-top:1px;font-weight:700}
.stk-label-footer{display:flex;justify-content:space-between;align-items:center;padding:3px 10px;background:#f8fafc;border-top:1px dashed #cbd5e1;font-size:6.5px;color:#94a3b8}
/* ── Print Settings Modal ── */
.ps-ov{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(6px);z-index:10000;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all .25s}
.ps-ov.show{opacity:1;visibility:visible}
.ps-md{background:#fff;border-radius:20px;width:96%;max-width:940px;max-height:94vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.25);transform:translateY(20px) scale(.96);transition:transform .3s cubic-bezier(.22,1,.36,1)}
.ps-ov.show .ps-md{transform:translateY(0) scale(1)}
.ps-hdr{background:linear-gradient(135deg,#1e293b,#334155);padding:18px 24px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.ps-hdr-icon{width:38px;height:38px;background:linear-gradient(135deg,#10b981,#059669);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0}
.ps-hdr h3{flex:1;font-size:15px;font-weight:800;color:#fff;margin:0}
.ps-hdr p{font-size:11px;color:rgba(255,255,255,.6);margin:2px 0 0}
.ps-close{width:32px;height:32px;border-radius:8px;border:none;background:rgba(255,255,255,.12);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;transition:.15s;flex-shrink:0}
.ps-close:hover{background:rgba(255,255,255,.24)}
.ps-body{display:flex;flex:1;overflow:hidden;min-height:0}
.ps-left{width:320px;flex-shrink:0;border-right:1px solid #e2e8f0;overflow-y:auto;padding:20px}
.ps-right{flex:1;background:#f8fafc;display:flex;flex-direction:column;overflow:hidden}
.ps-section{margin-bottom:20px}
.ps-section-hdr{font-size:10px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:6px}
.ps-row{display:flex;flex-direction:column;gap:4px;margin-bottom:12px}
.ps-row label{font-size:11px;font-weight:700;color:#374151}
.ps-row input[type=number],.ps-row select{border:1.5px solid #e2e8f0;border-radius:8px;padding:7px 11px;font-size:12.5px;color:#1e293b;background:#fff;width:100%;outline:none;transition:.15s;box-sizing:border-box}
.ps-row input[type=number]:focus,.ps-row select:focus{border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.12)}
.ps-dim-row{display:grid;grid-template-columns:1fr auto 1fr;gap:6px;align-items:center}
.ps-dim-sep{font-size:14px;font-weight:800;color:#94a3b8;text-align:center}
.ps-preset-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.ps-preset{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:9px;padding:9px 10px;cursor:pointer;transition:.15s;text-align:center}
.ps-preset:hover{border-color:#10b981;background:#f0fdf4}
.ps-preset.active{border-color:#10b981;background:#f0fdf4;box-shadow:0 0 0 2px rgba(16,185,129,.2)}
.ps-preset-name{font-size:11px;font-weight:800;color:#1e293b}
.ps-preset-dim{font-size:9.5px;color:#64748b;margin-top:1px}
.ps-preset-tag{font-size:8.5px;padding:1px 5px;border-radius:4px;font-weight:700;display:inline-block;margin-top:3px}
.ps-tag-thermal{background:#fef3c7;color:#b45309}
.ps-tag-a4{background:#eff6ff;color:#1d4ed8}
.ps-tag-custom{background:#f5f3ff;color:#6d28d9}
.ps-printer-row{display:flex;flex-direction:column;gap:7px}
.ps-printer{display:flex;align-items:center;gap:10px;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 13px;cursor:pointer;transition:.15s}
.ps-printer:hover{border-color:#10b981;background:#f0fdf4}
.ps-printer.active{border-color:#10b981;background:#f0fdf4}
.ps-printer-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.ps-printer-icon.thermal{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.ps-printer-icon.inkjet{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff}
.ps-printer-name{font-size:12px;font-weight:800;color:#1e293b}
.ps-printer-sub{font-size:10px;color:#64748b}
.ps-printer-dot{width:8px;height:8px;border-radius:50%;background:#e2e8f0;margin-left:auto;flex-shrink:0}
.ps-printer.active .ps-printer-dot{background:#10b981}
.ps-preview-area{flex:1;padding:20px;overflow:auto;display:flex;flex-direction:column;align-items:center;gap:14px}
.ps-ruler-wrap{display:flex;align-items:flex-start;gap:0}
.ps-ruler-top{height:16px;background:#fff;border:1px solid #cbd5e1;border-bottom:none;display:flex;align-items:flex-end;overflow:hidden;font-size:7px;color:#94a3b8;position:relative}
.ps-ruler-left{width:16px;background:#fff;border:1px solid #cbd5e1;border-right:none;display:flex;flex-direction:column;overflow:hidden;font-size:7px;color:#94a3b8;position:relative}
.ps-ruler-label{position:absolute;font-size:7px;color:#64748b;font-weight:600;line-height:1;white-space:nowrap}
.ps-label-canvas{border:2px dashed #cbd5e1;background:#fff;position:relative;box-shadow:0 4px 20px rgba(0,0,0,.08);overflow:hidden;flex-shrink:0}
.ps-preview-hdr{text-align:center;font-size:10.5px;font-weight:700;color:#64748b;margin-bottom:4px}
.ps-preview-size-badge{background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-size:10px;font-weight:800;padding:3px 10px;border-radius:20px;display:inline-block}
.ps-footer{padding:14px 20px;border-top:1px solid #e2e8f0;display:flex;gap:8px;align-items:center;flex-shrink:0;background:#fff}
.ps-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;font-size:12.5px;font-weight:700;border:none;cursor:pointer;transition:.15s}
.ps-btn-primary{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 3px 12px rgba(16,185,129,.3)}
.ps-btn-primary:hover{box-shadow:0 4px 16px rgba(16,185,129,.4);transform:translateY(-1px)}
.ps-btn-test{background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;box-shadow:0 3px 12px rgba(139,92,246,.25)}
.ps-btn-test:hover{transform:translateY(-1px)}
.ps-btn-sec{background:#f1f5f9;color:#475569;border:1.5px solid #e2e8f0}
.ps-btn-sec:hover{background:#e2e8f0}
.ps-cols-row{display:flex;gap:6px}
.ps-col-opt{flex:1;text-align:center;border:1.5px solid #e2e8f0;border-radius:8px;padding:7px 4px;cursor:pointer;transition:.15s;font-size:11px;font-weight:700;color:#64748b}
.ps-col-opt:hover{border-color:#10b981;color:#059669}
.ps-col-opt.active{border-color:#10b981;background:#f0fdf4;color:#059669}
.ps-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0}
.ps-toggle{position:relative;width:36px;height:20px;flex-shrink:0}
.ps-toggle input{opacity:0;width:0;height:0;position:absolute}
.ps-toggle-slider{position:absolute;inset:0;background:#e2e8f0;border-radius:10px;cursor:pointer;transition:.2s}
.ps-toggle input:checked+.ps-toggle-slider{background:#10b981}
.ps-toggle-slider:before{content:'';position:absolute;width:14px;height:14px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.ps-toggle input:checked+.ps-toggle-slider:before{transform:translateX(16px)}
.ps-label-mini{font-size:11.5px;color:#374151;font-weight:600}
.ps-badge-save{background:#f0fdf4;color:#059669;border:1px solid #bbf7d0;font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;margin-left:auto}
/* ── Barcode Edit Modal ── */
.stk-bc-form{display:flex;flex-direction:column;gap:14px;margin-top:4px}
.stk-bc-field label{display:block;font-size:11px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.stk-bc-field input{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:var(--stk-rs);font-size:13px;font-family:'Courier New',monospace;letter-spacing:.5px;color:var(--c1);transition:border .15s;box-sizing:border-box}
.stk-bc-field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,138,92,.1)}
.stk-bc-old{font-size:11px;color:var(--c3);margin-top:4px;font-family:'Courier New',monospace}
.stk-bc-warn{background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e;display:flex;align-items:flex-start;gap:8px}
.stk-bc-actions{display:flex;gap:8px;justify-content:flex-end;padding-top:4px}

/* ── Empty ── */
.stk-empty{text-align:center;padding:48px 24px;color:var(--c3)}
.stk-empty i{font-size:48px;margin-bottom:12px;opacity:.25}
.stk-empty p{font-size:14px}

/* ── QR code canvas fallback ── */
.stk-label-qr canvas,.stk-label-qr img,.stk-label-qr svg{max-width:100%;max-height:100%}
.stk-label-barcode svg{max-width:100%;height:36px!important}

/* ── QR Modal Display ── */
.stk-qr-display{text-align:center;padding:20px}
.stk-qr-display .qr-big{width:200px;height:200px;margin:0 auto 12px;border:3px solid var(--border);border-radius:12px;padding:8px;background:#fff;display:flex;align-items:center;justify-content:center}
.stk-qr-display .qr-big img,.stk-qr-display .qr-big canvas{max-width:100%;max-height:100%}
.stk-qr-display .qr-val{font-family:'Courier New',monospace;font-size:13px;font-weight:700;color:var(--c1);background:#f1f5f9;padding:6px 14px;border-radius:8px;display:inline-block;letter-spacing:.5px;margin-bottom:8px}
.stk-qr-display .qr-hint{font-size:11px;color:var(--c3);display:flex;align-items:center;gap:6px;justify-content:center}

/* ── Report Export Dropdown ── */
.stk-export-dd{position:relative;display:inline-block}
.stk-export-menu{position:absolute;top:calc(100% + 4px);right:0;background:#fff;border:1.5px solid var(--border);border-radius:var(--stk-rs);box-shadow:var(--stk-shm);min-width:200px;z-index:100;display:none;overflow:hidden}
.stk-export-menu.show{display:block;animation:ddSlide .15s ease-out}
@keyframes ddSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.stk-export-item{display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:12px;color:var(--c1);cursor:pointer;transition:background .1s;border:none;background:none;width:100%;text-align:left;font-family:inherit}
.stk-export-item:hover{background:#f0fdf4}
.stk-export-item i{width:16px;text-align:center;font-size:13px}
.stk-export-item .ext{font-size:9px;padding:1px 5px;border-radius:3px;font-weight:700;margin-left:auto}
.stk-export-sep{height:1px;background:var(--border);margin:2px 0}

/* ══ Batch Actions Bar — Pro Floating Toolbar ══ */
@keyframes batchIn{from{opacity:0;transform:translateX(-50%) translateY(16px) scale(.96)}to{opacity:1;transform:translateX(-50%) translateY(0) scale(1)}}
.stk-batch{
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:1000;
    display:flex;align-items:center;gap:0;
    background:rgba(10,15,30,.92);
    backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,.1);
    border-radius:18px;padding:6px 8px;
    box-shadow:0 8px 40px rgba(0,0,0,.5),0 2px 12px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.06);
    max-width:92vw;animation:batchIn .25s cubic-bezier(.34,1.56,.64,1);
}
/* Count badge section */
.bb-count{display:flex;align-items:center;gap:8px;padding:2px 12px 2px 6px;border-right:1px solid rgba(255,255,255,.1);margin-right:4px}
.bb-num{background:var(--accent);color:#fff;font-size:13px;font-weight:800;min-width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 6px;line-height:1}
.bb-lbl{font-size:11px;color:rgba(255,255,255,.55);font-weight:600;white-space:nowrap}
/* Button groups */
.bb-grp{display:flex;align-items:center;gap:3px;padding:0 6px}
.bb-grp+.bb-grp{border-left:1px solid rgba(255,255,255,.08)}
/* Base button */
.bab{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:10px;font-size:11.5px;font-weight:700;border:none;cursor:pointer;transition:.15s;white-space:nowrap;font-family:inherit;letter-spacing:.2px}
.bab i{font-size:12px}
/* Transaction buttons */
.bab-use{background:rgba(22,163,74,.18);color:#4ade80}
.bab-use:hover{background:rgba(22,163,74,.35);color:#86efac}
.bab-borrow{background:rgba(59,130,246,.18);color:#93c5fd}
.bab-borrow:hover{background:rgba(59,130,246,.35);color:#bfdbfe}
.bab-transfer{background:rgba(139,92,246,.18);color:#c4b5fd}
.bab-transfer:hover{background:rgba(139,92,246,.35);color:#ddd6fe}
/* Print/utility buttons */
.bab-print{background:rgba(255,255,255,.07);color:rgba(255,255,255,.65)}
.bab-print:hover{background:rgba(255,255,255,.15);color:#fff}
/* Cancel */
.bab-cancel{background:none;color:rgba(255,255,255,.35);width:32px;height:32px;padding:0;border-radius:8px;justify-content:center;font-size:14px}
.bab-cancel:hover{background:rgba(239,68,68,.2);color:#f87171}
/* Mobile: hide labels, show icons only */
@media(max-width:600px){
    .bab-lbl{display:none}
    .bab{padding:7px 9px}
    .bb-lbl{display:none}
    .bb-count{padding:2px 8px 2px 4px}
}

/* ══ Batch Transaction Modal ══ */
.btx-ov{position:fixed;inset:0;z-index:1100;background:rgba(0,0,0,.55);display:flex;align-items:flex-end;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s}
.btx-ov.show{opacity:1;pointer-events:auto}
.btx-md{background:#fff;border-radius:22px 22px 0 0;width:100%;max-width:560px;max-height:88vh;display:flex;flex-direction:column;transform:translateY(100%);transition:transform .28s cubic-bezier(.34,1.1,.64,1);overflow:hidden}
.btx-ov.show .btx-md{transform:translateY(0)}
/* Handle */
.btx-handle{display:flex;justify-content:center;padding:10px 0 4px;flex-shrink:0}
.btx-handle-bar{width:36px;height:4px;border-radius:2px;background:#e2e8f0}
/* Header */
.btx-hdr{padding:8px 20px 14px;flex-shrink:0;border-bottom:1px solid #f1f5f9}
.btx-hdr-top{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.btx-hdr-ic{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.btx-hdr-info{flex:1;min-width:0}
.btx-hdr-title{font-size:15px;font-weight:800;color:#0f172a;line-height:1.2}
.btx-hdr-sub{font-size:11px;color:#64748b;margin-top:2px}
.btx-hdr-close{width:28px;height:28px;border-radius:8px;border:none;background:#f1f5f9;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px}
.btx-hdr-close:hover{background:#fee2e2;color:#dc2626}
/* Type tabs */
.btx-tabs{display:flex;gap:6px}
.btx-tab{flex:1;padding:8px;border-radius:10px;border:none;cursor:pointer;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s;font-family:inherit;background:#f8fafc;color:#64748b;border:1.5px solid transparent}
.btx-tab.act{border-color:var(--accent);color:var(--accent);background:rgba(13,148,136,.06)}
/* Body */
.btx-body{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:14px}
/* Item list */
.btx-items-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.btx-items-hdr h5{margin:0;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px}
.btx-item{display:flex;align-items:center;gap:10px;padding:7px 8px;background:#f8fafc;border-radius:9px;transition:.12s;border:1px solid transparent}
.btx-item.btx-ok{background:#f0fdf4;border-color:#bbf7d0}
.btx-item.btx-err{background:#fff5f5;border-color:#fca5a5}
.btx-item-ic{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;background:rgba(13,148,136,.1);color:var(--accent);flex-shrink:0}
.btx-item-info{flex:1;min-width:0}
.btx-item-name{font-size:12px;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.btx-item-sub{font-size:10px;color:#94a3b8;margin-top:1px}
.btx-item-qty{display:flex;align-items:center;gap:4px;flex-shrink:0}
.btx-item-qty input{width:60px;text-align:center;border:1.5px solid #e2e8f0;border-radius:7px;padding:4px 4px;font-size:12px;font-weight:700;color:#0f172a;outline:none;background:#fff}
.btx-item-qty input:focus{border-color:var(--accent)}
.btx-item-qty .btx-unit{font-size:10px;color:#94a3b8;font-weight:600;min-width:24px}
.btx-item-status{font-size:14px;flex-shrink:0;width:18px;text-align:center}
/* Fields */
.btx-field{display:flex;flex-direction:column;gap:5px}
.btx-field label{font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px}
.btx-field input,.btx-field textarea,.btx-field select{border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13px;color:#0f172a;background:#fafafa;outline:none;transition:.15s;font-family:inherit;resize:vertical}
.btx-field input:focus,.btx-field textarea:focus{border-color:var(--accent);background:#fff}
.btx-field textarea{min-height:60px}
/* User search */
.btx-user-wrap{position:relative}
.btx-user-dd{position:absolute;top:calc(100% + 2px);left:0;right:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;z-index:20;max-height:220px;overflow-y:auto;display:none;box-shadow:0 8px 24px rgba(0,0,0,.1)}
.btx-user-opt{padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px;transition:.12s}
.btx-user-opt:hover{background:#f0fdf4}
.btx-user-opt:last-child{border-bottom:none}
/* Avatar — photo or initial fallback */
.btx-user-av{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#34d399);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;overflow:hidden;border:1.5px solid rgba(255,255,255,.6)}
.btx-user-av img{width:100%;height:100%;object-fit:cover;display:block}
/* Selected card */
.btx-selected-user{display:flex;align-items:center;gap:10px;padding:10px 12px;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1.5px solid #6ee7b7;border-radius:12px;margin-top:6px}
.btx-selected-user-name{font-size:13px;font-weight:800;color:#065f46;line-height:1.2}
.btx-clear-user{border:none;background:rgba(239,68,68,.08);color:#dc2626;cursor:pointer;font-size:12px;padding:5px 7px;border-radius:7px;margin-left:auto;flex-shrink:0;transition:.15s}
.btx-clear-user:hover{background:rgba(239,68,68,.18)}
/* Progress bar */
.btx-prog{background:#f1f5f9;border-radius:6px;height:6px;overflow:hidden;margin-top:4px}
.btx-prog-fill{height:100%;background:linear-gradient(90deg,var(--accent),#34d399);border-radius:6px;transition:width .3s ease}
/* Footer */
.btx-footer{padding:12px 20px;border-top:1px solid #f1f5f9;display:flex;gap:8px;flex-shrink:0;background:#fff}
.btx-btn-cancel{flex:0 0 auto;padding:10px 18px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-weight:700;background:#fff;color:#64748b;cursor:pointer;font-family:inherit}
.btx-btn-cancel:hover{background:#f8fafc}
.btx-btn-submit{flex:1;padding:10px 18px;border:none;border-radius:10px;font-size:13px;font-weight:700;color:#fff;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:7px;transition:.15s}
.btx-btn-submit:hover{filter:brightness(1.08)}
.btx-btn-submit:disabled{opacity:.5;cursor:not-allowed;filter:none}
.btx-result-summary{padding:12px 14px;border-radius:12px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:8px}
.btx-result-ok{background:#f0fdf4;color:#065f46}
.btx-result-err{background:#fff5f5;color:#dc2626}
@media(max-width:480px){.btx-footer{padding:10px 14px}}

/* ══ Dispose Modal (dsp) ══ */
@keyframes dspIn{from{opacity:0;transform:translateY(30px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.dsp-ov{position:fixed;inset:0;z-index:10100;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);display:flex;align-items:flex-end;justify-content:center;padding:0;opacity:0;pointer-events:none;transition:opacity .22s}
.dsp-ov.show{opacity:1;pointer-events:auto}
.dsp-md{background:#fff;border-radius:24px 24px 0 0;width:100%;max-width:560px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 -8px 40px rgba(220,38,38,.18),0 -1px 0 #fecaca;animation:dspIn .28s cubic-bezier(.34,1.1,.64,1)}
@media(min-width:600px){.dsp-ov{align-items:center;padding:16px}.dsp-md{border-radius:22px;max-height:88vh}}
/* Header */
.dsp-hdr{padding:18px 20px 0;flex-shrink:0}
.dsp-hdr-top{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.dsp-hdr-ic{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,#dc2626,#f87171);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;box-shadow:0 6px 16px rgba(220,38,38,.35)}
.dsp-hdr-title{font-size:16px;font-weight:800;color:#0f172a}
.dsp-hdr-sub{font-size:11px;color:#64748b;margin-top:1px}
.dsp-hdr-close{margin-left:auto;width:32px;height:32px;border-radius:9px;border:none;background:#f1f5f9;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;transition:.15s}
.dsp-hdr-close:hover{background:#fee2e2;color:#dc2626}
/* Body */
.dsp-body{flex:1;overflow-y:auto;padding:14px 20px 8px;display:flex;flex-direction:column;gap:12px}
/* Items list */
.dsp-items{display:flex;flex-direction:column;gap:5px;max-height:220px;overflow-y:auto}
.dsp-item{display:flex;align-items:center;gap:10px;padding:9px 11px;background:#fff5f5;border:1px solid #fecaca;border-radius:11px}
.dsp-item-ic{width:30px;height:30px;border-radius:8px;background:rgba(220,38,38,.1);color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.dsp-item-name{flex:1;min-width:0;font-size:12px;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dsp-item-qty{font-size:11px;color:#64748b;white-space:nowrap;flex-shrink:0}
.dsp-item-del{width:22px;height:22px;border:none;background:none;color:#f87171;cursor:pointer;font-size:11px;border-radius:5px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:.15s}
.dsp-item-del:hover{background:#fee2e2;color:#dc2626}
.dsp-item-info{flex:1;min-width:0}
.dsp-item-meta{font-size:10px;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:1px}
.dsp-item-rm{width:24px;height:24px;border:none;background:none;color:#f87171;cursor:pointer;font-size:12px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:.15s;padding:0}
.dsp-item-rm:hover{background:#fee2e2;color:#dc2626}
/* Fields */
.dsp-field{display:flex;flex-direction:column;gap:5px}
.dsp-field label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
.dsp-select,.dsp-textarea{width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13px;font-family:inherit;color:#0f172a;background:#fff;transition:.15s;box-sizing:border-box}
.dsp-select:focus,.dsp-textarea:focus{outline:none;border-color:#f87171;box-shadow:0 0 0 3px rgba(248,113,113,.15)}
.dsp-textarea{resize:vertical;min-height:70px;line-height:1.5}
/* Warning banner */
.dsp-warn{display:flex;align-items:flex-start;gap:9px;padding:10px 13px;background:#fff5f5;border:1.5px solid #fecaca;border-radius:11px;font-size:12px;color:#991b1b;line-height:1.5}
.dsp-warn i{color:#ef4444;margin-top:1px;flex-shrink:0}
/* Progress */
.dsp-prog-wrap{display:none}
.dsp-prog{background:#fee2e2;border-radius:6px;height:5px;overflow:hidden;margin-top:6px}
.dsp-prog-fill{height:100%;background:linear-gradient(90deg,#dc2626,#f87171);border-radius:6px;transition:width .3s ease;width:0}
/* Footer */
.dsp-footer{padding:12px 20px 20px;flex-shrink:0;display:flex;flex-direction:column;gap:8px;border-top:1px solid #fef2f2}
.dsp-btn-confirm{width:100%;padding:13px;border:none;border-radius:12px;font-size:14px;font-weight:800;color:#fff;background:linear-gradient(135deg,#dc2626,#ef4444);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.15s;font-family:inherit;box-shadow:0 4px 14px rgba(220,38,38,.35)}
.dsp-btn-confirm:hover:not(:disabled){filter:brightness(1.08);box-shadow:0 6px 20px rgba(220,38,38,.45)}
.dsp-btn-confirm:disabled{opacity:.55;cursor:not-allowed;filter:none;box-shadow:none}
.dsp-btn-cancel{width:100%;padding:11px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:13px;font-weight:700;color:#64748b;background:#fff;cursor:pointer;font-family:inherit;transition:.15s}
.dsp-btn-cancel:hover{background:#f8fafc}
/* Batch bar dispose button */
.bab-dispose{background:rgba(220,38,38,.08);color:#dc2626;border:1px solid rgba(220,38,38,.2)}
.bab-dispose:hover{background:rgba(220,38,38,.16);border-color:rgba(220,38,38,.4)}

/* ══ Dispose Confirm Popup ══ */
@keyframes dspCfIn{from{opacity:0;transform:scale(.95) translateY(12px)}to{opacity:1;transform:scale(1) translateY(0)}}
.dspCf-ov{position:fixed;inset:0;z-index:10200;background:rgba(0,0,0,.6);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .2s}
.dspCf-ov.show{opacity:1;pointer-events:auto}
.dspCf-box{background:#fff;border-radius:20px;width:100%;max-width:420px;max-height:85vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.22),0 0 0 1px rgba(220,38,38,.08);animation:dspCfIn .24s cubic-bezier(.34,1.1,.64,1)}
.dspCf-hdr{padding:20px 20px 0;text-align:center;flex-shrink:0}
.dspCf-ic{width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,#dc2626,#f87171);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 12px;box-shadow:0 8px 20px rgba(220,38,38,.35)}
.dspCf-title{font-size:16px;font-weight:800;color:#0f172a;margin-bottom:4px}
.dspCf-sub{font-size:12px;color:#64748b}
.dspCf-body{flex:1;overflow-y:auto;padding:14px 20px}
.dspCf-list{display:flex;flex-direction:column;gap:5px;margin-bottom:12px}
.dspCf-item{display:flex;align-items:center;gap:10px;padding:8px 11px;background:#fff5f5;border:1px solid #fecaca;border-radius:10px}
.dspCf-item-dot{width:7px;height:7px;border-radius:50%;background:#ef4444;flex-shrink:0}
.dspCf-item-name{flex:1;font-size:12px;font-weight:600;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dspCf-item-qty{font-size:11px;color:#dc2626;font-weight:700;white-space:nowrap;flex-shrink:0}
.dspCf-meta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.dspCf-chip{font-size:11px;padding:3px 10px;border-radius:8px;font-weight:600;background:#f1f5f9;color:#475569;display:flex;align-items:center;gap:4px}
.dspCf-warn{display:flex;align-items:flex-start;gap:9px;padding:10px 13px;background:#fef2f2;border:1.5px solid #fecaca;border-radius:11px;font-size:12px;color:#991b1b;line-height:1.5;font-weight:500}
.dspCf-warn i{color:#ef4444;flex-shrink:0;margin-top:1px}
.dspCf-footer{padding:12px 20px 20px;flex-shrink:0;display:flex;flex-direction:column;gap:8px;border-top:1px solid #fef2f2}
.dspCf-btn-ok{width:100%;padding:13px;border:none;border-radius:12px;font-size:14px;font-weight:800;color:#fff;background:linear-gradient(135deg,#dc2626,#ef4444);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;box-shadow:0 4px 14px rgba(220,38,38,.35);transition:.15s}
.dspCf-btn-ok:hover{filter:brightness(1.08);box-shadow:0 6px 20px rgba(220,38,38,.45)}
.dspCf-btn-cancel{width:100%;padding:11px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:13px;font-weight:700;color:#64748b;background:#fff;cursor:pointer;font-family:inherit;transition:.15s}
.dspCf-btn-cancel:hover{background:#f8fafc}
@media(max-width:440px){.dspCf-ov{align-items:flex-end;padding:0}.dspCf-box{border-radius:20px 20px 0 0;max-height:92vh}}

/* ══ Pre-Transfer Preview Popup (btc) ══ */
@keyframes btcIn{from{opacity:0;transform:translateY(24px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.btc-ov{position:fixed;inset:0;z-index:1300;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .2s}
.btc-ov.show{opacity:1;pointer-events:auto}
.btc-box{background:#fff;border-radius:22px;width:100%;max-width:460px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.2),0 0 0 1px rgba(0,0,0,.06);animation:btcIn .26s cubic-bezier(.34,1.1,.64,1)}
.btc-hdr{padding:20px 20px 0;text-align:center;flex-shrink:0}
.btc-hdr-ic{width:52px;height:52px;border-radius:15px;background:linear-gradient(135deg,#6d28d9,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 10px;box-shadow:0 8px 20px rgba(109,40,217,.3)}
.btc-hdr h3{margin:0;font-size:16px;font-weight:800;color:#0f172a}
.btc-hdr p{margin:4px 0 0;font-size:12px;color:#64748b}
.btc-body{flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:11px}
.btc-sec{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.btc-recipient{display:flex;align-items:center;gap:11px;padding:10px 13px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:13px}
.btc-rec-av{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#6d28d9,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0;overflow:hidden;box-shadow:0 3px 10px rgba(109,40,217,.25)}
.btc-rec-av img{width:100%;height:100%;object-fit:cover}
.btc-rec-name{font-size:13px;font-weight:800;color:#0f172a}
.btc-rec-sub{font-size:10.5px;color:#64748b;margin-top:2px;display:flex;gap:8px;flex-wrap:wrap}
.btc-purpose{padding:8px 11px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;color:#374151;line-height:1.5}
.btc-items{display:flex;flex-direction:column;gap:5px;max-height:220px;overflow-y:auto}
.btc-item{display:flex;align-items:center;gap:9px;padding:8px 10px;background:#faf5ff;border:1px solid #ede9fe;border-radius:10px}
.btc-item-ic{width:28px;height:28px;border-radius:8px;background:rgba(139,92,246,.12);color:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.btc-item-name{flex:1;min-width:0;font-size:12px;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.btc-item-qty{font-size:11px;color:#6d28d9;font-weight:600;white-space:nowrap;flex-shrink:0}
.btc-footer{padding:12px 18px 18px;flex-shrink:0;display:flex;flex-direction:column;gap:8px;border-top:1px solid #f1f5f9}
.btc-btn-confirm{width:100%;padding:13px;border:none;border-radius:12px;font-size:14px;font-weight:800;color:#fff;background:linear-gradient(135deg,#6d28d9,#8b5cf6);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.15s;font-family:inherit;box-shadow:0 4px 14px rgba(109,40,217,.3)}
.btc-btn-confirm:hover{filter:brightness(1.08);box-shadow:0 6px 20px rgba(109,40,217,.4)}
.btc-btn-cancel{width:100%;padding:11px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:13px;font-weight:700;color:#64748b;background:#fff;cursor:pointer;font-family:inherit;transition:.15s}
.btc-btn-cancel:hover{background:#f8fafc;border-color:#cbd5e1}
.btc-warn{display:flex;align-items:center;gap:7px;padding:8px 11px;background:#fffbeb;border:1px solid #fde68a;border-radius:9px;font-size:11px;color:#92400e;font-weight:600}
@media(max-width:400px){.btc-box{border-radius:18px 18px 0 0;max-height:93vh}.btc-ov{align-items:flex-end;padding:0}}

/* ══ Transfer Confirm Popup ══ */
@keyframes tfcIn{from{opacity:0;transform:translateY(28px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.tfc-ov{position:fixed;inset:0;z-index:1200;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .22s}
.tfc-ov.show{opacity:1;pointer-events:auto}
.tfc-box{background:#fff;border-radius:22px;width:100%;max-width:440px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.22),0 0 0 1px rgba(0,0,0,.06);animation:tfcIn .28s cubic-bezier(.34,1.2,.64,1)}
/* Header */
.tfc-hdr{padding:22px 22px 0;text-align:center;flex-shrink:0}
.tfc-hdr-ic{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 12px;box-shadow:0 8px 20px rgba(59,130,246,.35)}
.tfc-hdr h3{margin:0;font-size:17px;font-weight:800;color:#0f172a}
.tfc-hdr p{margin:4px 0 0;font-size:12px;color:#64748b}
/* Body */
.tfc-body{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:12px}
/* Recipient card */
.tfc-recipient{display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px}
.tfc-rec-av{width:46px;height:46px;border-radius:13px;background:linear-gradient(135deg,#6d28d9,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;flex-shrink:0;overflow:hidden;box-shadow:0 4px 12px rgba(109,40,217,.3)}
.tfc-rec-av img{width:100%;height:100%;object-fit:cover}
.tfc-rec-info{flex:1;min-width:0}
.tfc-rec-name{font-size:14px;font-weight:800;color:#0f172a}
.tfc-rec-sub{font-size:11px;color:#64748b;margin-top:2px;display:flex;gap:8px;flex-wrap:wrap}
.tfc-arrow{font-size:18px;color:#cbd5e1;flex-shrink:0}
/* Items section */
.tfc-sec-label{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.tfc-items{display:flex;flex-direction:column;gap:5px;max-height:200px;overflow-y:auto}
.tfc-item{display:flex;align-items:center;gap:10px;padding:8px 10px;background:#f8fafc;border-radius:10px;border:1px solid #f1f5f9;transition:.15s}
.tfc-item.tfc-ok{background:#f0fdf4;border-color:#bbf7d0}
.tfc-item.tfc-fail{background:#fff5f5;border-color:#fca5a5}
.tfc-item-ic{width:30px;height:30px;border-radius:8px;background:rgba(139,92,246,.1);color:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.tfc-item-name{flex:1;min-width:0;font-size:12px;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tfc-item-qty{font-size:11px;color:#64748b;white-space:nowrap;flex-shrink:0}
.tfc-item-st{width:20px;text-align:center;font-size:14px;flex-shrink:0}
/* Progress */
.tfc-prog-wrap{display:none}
.tfc-prog{background:#f1f5f9;border-radius:6px;height:5px;overflow:hidden;margin-top:6px}
.tfc-prog-fill{height:100%;background:linear-gradient(90deg,#1d4ed8,#60a5fa);border-radius:6px;transition:width .3s ease;width:0}
/* Summary row */
.tfc-summary{display:flex;gap:10px;flex-wrap:wrap}
.tfc-sum-chip{display:flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;font-size:11px;font-weight:700}
.tfc-sum-ok{background:#f0fdf4;color:#16a34a}
.tfc-sum-fail{background:#fff5f5;color:#dc2626}
.tfc-sum-skip{background:#fffbeb;color:#d97706}
/* Footer */
.tfc-footer{padding:14px 20px 20px;flex-shrink:0;display:flex;flex-direction:column;gap:8px;border-top:1px solid #f1f5f9}
.tfc-btn-confirm{width:100%;padding:13px;border:none;border-radius:12px;font-size:14px;font-weight:800;color:#fff;background:linear-gradient(135deg,#1d4ed8,#3b82f6);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.15s;font-family:inherit;box-shadow:0 4px 14px rgba(59,130,246,.35)}
.tfc-btn-confirm:hover{filter:brightness(1.08);box-shadow:0 6px 20px rgba(59,130,246,.45)}
.tfc-btn-confirm:disabled{opacity:.55;cursor:not-allowed;filter:none;box-shadow:none}
.tfc-btn-row{display:flex;gap:8px}
.tfc-btn-reject{flex:1;padding:11px;border:1.5px solid #fca5a5;border-radius:12px;font-size:13px;font-weight:700;color:#dc2626;background:#fff;cursor:pointer;font-family:inherit;transition:.15s}
.tfc-btn-reject:hover{background:#fff5f5}
.tfc-btn-close{flex:1;padding:11px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:13px;font-weight:700;color:#64748b;background:#fff;cursor:pointer;font-family:inherit;transition:.15s}
.tfc-btn-close:hover{background:#f8fafc}
@media(max-width:400px){.tfc-box{border-radius:18px 18px 0 0;max-height:95vh}.tfc-ov{align-items:flex-end;padding:0}}
/* Item badges */
.btx-badges{display:flex;flex-wrap:wrap;gap:3px;margin-top:3px}
.btx-badge{display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;padding:2px 5px;border-radius:4px;line-height:1.3}
.btx-badge-mine{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.btx-badge-oos{background:#fee2e2;color:#dc2626;border:1px solid #fca5a5}
.btx-badge-blocked{background:#ede9fe;color:#6d28d9;border:1px solid #c4b5fd}
/* Item states */
.btx-item.btx-oos{background:#fff8f8;border:1px solid #fecaca}
.btx-item.btx-blocked{background:#fefce8;border:1px solid #fde68a}
/* Qty warning */
.btx-qty-warn{border-color:#dc2626!important;background:#fff5f5!important;color:#dc2626!important;animation:qtyShake .2s ease}
@keyframes qtyShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-3px)}75%{transform:translateX(3px)}}
/* Remove button */
.btx-remove{width:24px;height:24px;border-radius:7px;border:none;background:rgba(239,68,68,.07);color:#ef4444;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;transition:.15s;opacity:.55;padding:0}
.btx-remove:hover{background:rgba(239,68,68,.18);opacity:1;transform:scale(1.1)}
/* Whole-bottle toggle */
.btx-whole-toggle{display:inline-flex;align-items:center;gap:5px;padding:3px 7px 3px 4px;border-radius:20px;border:1.5px solid #e2e8f0;background:#f8fafc;cursor:pointer;font-size:10px;font-weight:700;color:#64748b;transition:.15s;flex-shrink:0;white-space:nowrap;user-select:none}
.btx-whole-toggle:hover{border-color:#a5b4fc;color:#4f46e5;background:#eef2ff}
.btx-whole-toggle.on{border-color:#8b5cf6;background:rgba(139,92,246,.1);color:#6d28d9}
.btx-whole-toggle .btx-wt-dot{width:10px;height:10px;border-radius:50%;background:#e2e8f0;transition:.2s;flex-shrink:0}
.btx-whole-toggle.on .btx-wt-dot{background:#7c3aed}
.btx-whole-all{font-size:10px;font-weight:700;color:#7c3aed;background:none;border:none;cursor:pointer;padding:0;display:flex;align-items:center;gap:4px;transition:opacity .15s}
.btx-whole-all:hover{opacity:.75}
.btx-item.btx-whole{background:linear-gradient(135deg,#faf5ff,#f5f3ff);border-color:#c4b5fd}
.btx-item.btx-whole .btx-item-qty input{background:#ede9fe;border-color:#a78bfa;color:#5b21b6;font-weight:800}
/* Items scroll taller */
.btx-items-scroll{max-height:240px;overflow-y:auto;display:flex;flex-direction:column;gap:5px;border:1.5px solid #f1f5f9;border-radius:12px;padding:7px}
/* Mobile optimise */
@media(max-width:480px){
    .btx-item-ic{display:none}
    .btx-item{padding:6px 6px}
    .btx-item-name{font-size:11.5px}
    .btx-item-qty input{width:52px;font-size:12px}
    .btx-md{max-height:92vh}
}

/* ── Selection ── */
.stk-selected{outline:2px solid var(--accent)!important;outline-offset:-2px;background:linear-gradient(135deg,rgba(13,148,136,.03),rgba(20,184,166,.06))!important}
.stk-card .stk-chk,.stk-cr .stk-chk{accent-color:var(--accent)}
tr .stk-chk{accent-color:var(--accent)}

/* ── QR Display (modal) ── */
.stk-qr-display{text-align:center;padding:20px}
.stk-qr-display .qr-big{width:180px;height:180px;margin:0 auto 12px;background:#fff;border-radius:12px;border:2px solid var(--border);padding:8px;display:flex;align-items:center;justify-content:center}
.stk-qr-display .qr-val{font-family:'Courier New',monospace;font-size:12px;color:var(--c2);letter-spacing:1px;margin-bottom:8px;font-weight:600}
.stk-qr-display .qr-hint{font-size:11px;color:var(--c3);line-height:1.5;padding:8px 16px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;display:inline-block}

/* ── Transaction Modal ── */
.txn-header{display:flex;align-items:center;gap:12px;padding:20px 22px 16px;border-bottom:1px solid var(--border)}
.txn-header-ic{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.txn-header h3{font-size:15px;font-weight:800;margin:0;color:var(--c1)}
.txn-header p{font-size:11px;color:var(--c3);margin:2px 0 0}
.txn-close{margin-left:auto;background:none;border:none;color:var(--c3);font-size:18px;cursor:pointer;padding:4px;border-radius:6px;line-height:1;transition:color .15s}
.txn-close:hover{color:var(--c1)}
.txn-tabs{display:flex;border-bottom:1px solid var(--border);background:#f8fafc;flex-shrink:0}
.txn-tab{flex:1;padding:10px 8px;font-size:11px;font-weight:700;color:var(--c3);border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;transition:all .15s;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:5px}
.txn-tab:hover{color:var(--c1);background:#f1f5f9}
.txn-tab.active{color:var(--accent);border-bottom-color:var(--accent);background:#fff}
.txn-body{padding:18px 22px 22px;overflow-y:auto;max-height:calc(90vh - 170px)}
.txn-chem-info{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:14px;margin-bottom:18px;position:relative;overflow:hidden}
.txn-chem-info::before{content:'';position:absolute;right:-20px;top:-20px;width:80px;height:80px;border-radius:50%;background:rgba(22,163,74,.08);pointer-events:none}
.txn-chem-ic{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;box-shadow:0 3px 10px rgba(22,163,74,.3)}
.txn-chem-body{flex:1;min-width:0}
.txn-chem-name{font-size:13px;font-weight:800;color:#14532d;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.txn-chem-code{font-size:10px;color:#15803d;opacity:.8;margin:2px 0 6px;font-family:monospace;letter-spacing:.3px}
.txn-chem-stats{display:flex;align-items:center;gap:10px}
.txn-chem-qty{display:flex;flex-direction:column;gap:0}
.txn-chem-qty-val{font-size:18px;font-weight:900;color:#15803d;line-height:1}
.txn-chem-qty-lbl{font-size:9px;color:#16a34a;text-transform:uppercase;letter-spacing:.4px;margin-top:1px;opacity:.8}
.txn-chem-divider{width:1px;height:28px;background:rgba(22,163,74,.25);flex-shrink:0}
.txn-chem-meta{display:flex;flex-direction:column;gap:3px}
.txn-chem-meta span{font-size:10px;color:#166534;display:flex;align-items:center;gap:4px;opacity:.85}
.txn-chem-meta i{font-size:9px;color:#16a34a}
.txn-field{margin-bottom:14px}
.txn-field label{display:block;font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.txn-field input,.txn-field select,.txn-field textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;color:var(--c1);background:#fff;transition:border .15s;box-sizing:border-box}
.txn-field input:focus,.txn-field select:focus,.txn-field textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(13,148,136,.1)}
.txn-field textarea{resize:vertical;min-height:72px}
.txn-qty-row{display:flex;align-items:center;gap:10px}
.txn-qty-row input{flex:1}
.txn-qty-max{font-size:11px;color:var(--c3);white-space:nowrap;flex-shrink:0;background:#f1f5f9;padding:5px 10px;border-radius:7px;border:1px solid var(--border);font-weight:600}
.txn-qty-barwrap{margin-top:8px}
.txn-qty-bartrack{height:5px;background:#e2e8f0;border-radius:99px;overflow:hidden}
.txn-qty-barfill{height:100%;width:0%;border-radius:99px;background:#16a34a;transition:width .3s cubic-bezier(.4,0,.2,1),background .3s}
.txn-qty-barlbl{display:flex;justify-content:space-between;align-items:center;margin-top:5px}
.txn-qty-barpct{font-size:11px;font-weight:800;color:#94a3b8;transition:color .3s}
.txn-qty-baramt{font-size:10px;color:var(--c3)}
/* ── Borrow confirm overlay ── */
.txn-confirm-card{background:#fff;border-radius:18px;box-shadow:0 12px 48px rgba(0,0,0,.2);max-width:420px;width:100%;transform:scale(.9) translateY(16px);transition:transform .25s cubic-bezier(.34,1.4,.64,1);overflow:hidden}
.stk-ov.show .txn-confirm-card{transform:scale(1) translateY(0)}
.txn-confirm-hdr{padding:18px 20px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.txn-confirm-hdr-ic{width:40px;height:40px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:16px;color:#2563eb;flex-shrink:0}
.txn-confirm-hdr-title{font-size:14px;font-weight:800;color:var(--c1)}
.txn-confirm-hdr-sub{font-size:11px;color:var(--c3);margin-top:1px}
.txn-confirm-body{padding:16px 20px}
.txn-confirm-chem{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #bfdbfe;border-radius:10px;padding:12px 14px;margin-bottom:14px}
.txn-confirm-chem-name{font-size:13px;font-weight:800;color:#1e3a8a}
.txn-confirm-chem-code{font-size:10px;color:#2563eb;font-weight:700;margin-top:2px}
.txn-confirm-rows{display:flex;flex-direction:column;gap:7px;margin-bottom:14px}
.txn-confirm-row{display:flex;align-items:flex-start;gap:10px;padding:9px 12px;background:#f8fafc;border-radius:9px;border:1px solid var(--border)}
.txn-confirm-row-ic{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;margin-top:1px}
.txn-confirm-row-label{font-size:10px;color:var(--c3);font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.txn-confirm-row-val{font-size:13px;font-weight:700;color:var(--c1);margin-top:2px;word-break:break-word}
.txn-confirm-actions{display:flex;gap:10px;padding:0 20px 20px}
.txn-confirm-back{flex:1;padding:11px;border:1.5px solid var(--border);border-radius:10px;background:#fff;font-size:13px;font-weight:700;color:var(--c2);cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .12s}
.txn-confirm-back:hover{border-color:#64748b;color:var(--c1)}
.txn-confirm-go{flex:2;padding:11px;border:none;border-radius:10px;background:linear-gradient(135deg,#2563eb,#1d4ed8);font-size:13px;font-weight:800;color:#fff;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .12s;box-shadow:0 2px 10px rgba(37,99,235,.3)}
.txn-confirm-go:hover{filter:brightness(1.08);box-shadow:0 4px 16px rgba(37,99,235,.4)}
.txn-confirm-go:active{transform:scale(.97)}
.txn-confirm-go:disabled{opacity:.65;cursor:not-allowed;filter:none}
.txn-user-wrap{position:relative}
.txn-user-dropdown{position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid var(--accent);border-top:none;border-radius:0 0 10px 10px;max-height:200px;overflow-y:auto;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.12)}
.txn-user-opt{padding:10px 14px;cursor:pointer;transition:background .1s;display:flex;align-items:center;gap:10px}
.txn-user-opt:hover{background:#f0fdf4}
.txn-user-av{width:30px;height:30px;border-radius:8px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
.txn-user-name{font-size:12px;font-weight:600;color:var(--c1)}
.txn-user-dep{font-size:10px;color:var(--c3)}
.txn-user-selected{background:#f0fdf4;border:1.5px solid var(--accent);border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:10px;margin-top:6px}
.txn-user-selected-name{font-size:12px;font-weight:700;color:#14532d}
.txn-user-selected-dep{font-size:10px;color:var(--accent)}
.txn-user-clear{margin-left:auto;background:none;border:none;color:var(--c3);cursor:pointer;font-size:14px;padding:2px;border-radius:4px}
.txn-user-clear:hover{color:#dc2626}
.txn-warn{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;font-size:11px;color:#92400e;display:flex;align-items:flex-start;gap:8px;margin-bottom:14px;line-height:1.5}
.txn-warn i{margin-top:1px;flex-shrink:0;color:#d97706}
.txn-info{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 12px;font-size:11px;color:#1e40af;display:flex;align-items:flex-start;gap:8px;margin-bottom:14px;line-height:1.5}
.txn-info i{margin-top:1px;flex-shrink:0}
.txn-submit-row{display:flex;gap:8px;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)}
.txn-submit-row button{flex:1;padding:11px 16px;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .15s}
.txn-btn-cancel{background:#f1f5f9;color:var(--c2)}
.txn-btn-cancel:hover{background:#e2e8f0}
.txn-btn-use{background:linear-gradient(135deg,#16a34a,#22c55e);color:#fff}
.txn-btn-use:hover{filter:brightness(1.08)}
.txn-btn-borrow{background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff}
.txn-btn-borrow:hover{filter:brightness(1.08)}
.txn-btn-transfer{background:linear-gradient(135deg,#7c3aed,#8b5cf6);color:#fff}
.txn-btn-transfer:hover{filter:brightness(1.08)}
.txn-btn-submit:disabled,.txn-btn-use:disabled,.txn-btn-borrow:disabled,.txn-btn-transfer:disabled{opacity:.5;cursor:not-allowed;filter:none}
.txn-result{border-radius:12px;padding:24px 20px;text-align:center;margin-top:4px}
.txn-result.ok{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0}
.txn-result.err{background:#fef2f2;border:1px solid #fecaca}
.txn-result .txn-result-ic{font-size:40px;margin-bottom:10px;display:block}
.txn-result .txn-result-title{font-size:15px;font-weight:800;color:#14532d;margin-bottom:6px}
.txn-result .txn-result-msg{font-size:12px;color:#166534;line-height:1.6;opacity:.85}
.txn-result-auto{height:3px;background:#dcfce7;border-radius:2px;margin:14px 0 2px;overflow:hidden}
.txn-auto-bar{height:100%;width:0;background:linear-gradient(90deg,#16a34a,#22c55e);border-radius:2px}
.txn-searching{text-align:center;padding:16px;color:var(--c3);font-size:12px}

/* ── Txn: owner chip & full-bottle btn ── */
.txn-owner-chip{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;margin-top:6px;letter-spacing:.2px}
.txn-owner-chip.other{background:#fef9ec;border:1px solid #fde68a;color:#92400e}
.txn-owner-chip.mine{background:#dcfce7;border:1px solid #bbf7d0;color:#065f46}
.txn-full-btn{padding:6px 11px;border:1.5px solid #2563eb;border-radius:7px;background:#eff6ff;color:#2563eb;font-size:10px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;font-family:inherit;transition:all .12s;display:inline-flex;align-items:center;gap:4px}
.txn-full-btn:hover{background:#2563eb;color:#fff}

/* ── Txn: borrow flow card (static — borrower is always current user) ── */
.txn-borrow-flow{display:flex;align-items:center;gap:10px;background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:12px 14px}
.txn-bf-side{display:flex;align-items:center;gap:8px;flex:1;min-width:0}
.txn-bf-av{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden}
.txn-bf-name{font-size:12px;font-weight:700;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.txn-bf-role{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;margin-top:1px}
.txn-bf-arrow{display:flex;flex-direction:column;align-items:center;gap:2px;flex-shrink:0;padding:0 4px}
.txn-bf-arrow i{font-size:15px;color:#2563eb}
.txn-bf-arrow span{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#93c5fd}

/* ── Txn: user picker (transfer mode) ── */
.txn-upick{border:1.5px solid var(--border);border-radius:10px;overflow:hidden}
.txn-upick-flt{display:flex;align-items:center;gap:8px;padding:7px 11px;background:#f8fafc;border-bottom:1px solid var(--border)}
.txn-upick-flt i{color:var(--c3);font-size:11px;flex-shrink:0}
.txn-upick-flt input{flex:1;border:none;background:transparent;font-size:12px;color:var(--c1);outline:none;padding:0;font-family:inherit}
.txn-ulist{max-height:192px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:var(--border) transparent}
.txn-urow{display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;transition:background .1s;border-bottom:1px solid #f8fafc}
.txn-urow:last-child{border-bottom:none}
.txn-urow:hover{background:#f5f3ff}
.txn-urow.sel{background:#f5f3ff;border-left:3px solid #7c3aed;padding-left:9px}
.txn-uav{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.txn-uname{font-size:12px;font-weight:700;color:var(--c1)}
.txn-udep{font-size:10px;color:var(--c3)}
.txn-ume{font-size:9px;font-weight:700;background:#dbeafe;color:#2563eb;padding:1px 6px;border-radius:10px;margin-left:5px;vertical-align:middle}
.txn-ucheck{margin-left:auto;color:#7c3aed;font-size:13px;opacity:0;transition:opacity .1s}
.txn-urow.sel .txn-ucheck{opacity:1}
.txn-unone{text-align:center;padding:22px 16px;color:var(--c3);font-size:12px}

/* ── Mobile Sticky Toolbar ── */
.stk-mob-bar{display:none}
/* ซ่อนเมื่อ sidebar กำลังเปิด */
.stk-mob-bar.sb-hidden{display:none!important}

/* ── Filter button default: hidden (desktop uses toolbar's filterToggle) ── */
.stk-tabs-filter{display:none}

/* ── Pro Hero Enhancements ── */
.stk-hero{background:linear-gradient(135deg,#064e3b 0%,#065f46 35%,#0d9488 70%,#0f766e 100%);box-shadow:0 4px 24px rgba(6,95,70,.25)}
.stk-hero::after{content:'';position:absolute;top:-30%;right:-5%;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.08) 0%,transparent 70%);pointer-events:none}
.stk-hero-ic{box-shadow:0 4px 16px rgba(0,0,0,.15)}

/* ── Improved Stat Cards ── */
.stk-stat{position:relative;overflow:hidden}
.stk-stat::before{content:'';position:absolute;top:0;right:0;width:48px;height:48px;border-radius:0 10px 0 48px;opacity:.04;background:currentColor}
.stk-stat:active{transform:translateY(-1px)}

/* ── Improved Grid Cards ── */
.stk-card{box-shadow:var(--stk-sh)}
.stk-card:hover{box-shadow:0 8px 32px rgba(0,0,0,.12)}
.stk-card-actions{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,rgba(255,255,255,.98) 80%,transparent);padding:10px 16px 14px;display:flex;gap:6px;transform:translateY(100%);transition:transform .2s cubic-bezier(.34,1.56,.64,1);border-top:1px solid rgba(0,0,0,.04)}
.stk-card:hover .stk-card-actions{transform:translateY(0)}
.stk-card-action-btn{flex:1;padding:6px;border:1.5px solid var(--border);border-radius:7px;background:#fff;color:var(--c2);font-size:11px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px;font-family:inherit;transition:all .12s}
.stk-card-action-btn:hover{border-color:var(--accent);color:var(--accent);background:#f0fdf4}

/* ── Table pro ── */
.stk-tw{border-radius:var(--stk-r);box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.04)}
.stk-t th{font-size:9.5px}
.stk-t td{font-size:12.5px}
.stk-t tbody tr:last-child td{border-bottom:none}
.stk-t tbody tr:hover{background:#f0fdf4!important}
.stk-t tbody tr.me{background:#eff6ff}
.stk-t tbody tr.me:hover{background:#dbeafe!important}

/* ── Compact row pro ── */
.stk-cr{transition:all .12s}
.stk-cr:hover{box-shadow:0 2px 10px rgba(13,148,136,.1);transform:translateX(2px)}

/* ── Bottom sheet modal (mobile-only, activated via JS) ── */
.stk-md.bottom-sheet{border-radius:22px 22px 0 0;max-height:92vh;margin-top:auto;width:100%;max-width:100%}
.stk-md.bottom-sheet .bs-handle{width:44px;height:5px;background:#e2e8f0;border-radius:3px;margin:10px auto 0;cursor:grab}

/* ── Detail Modal Layout ── */
.dm-handle{width:44px;height:5px;background:#dde1e7;border-radius:3px;margin:12px auto 0;display:none;cursor:grab;flex-shrink:0}
.dm-hdr{padding:16px 20px 14px;border-bottom:1px solid var(--border);position:sticky;top:0;background:#fff;z-index:5;display:flex;align-items:flex-start;gap:12px}
.dm-hdr-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.dm-hdr-info{flex:1;min-width:0}
.dm-hdr-name{font-size:16px;font-weight:800;color:var(--c1);line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.dm-hdr-sub{font-size:11px;color:var(--c3);margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.dm-close{width:34px;height:34px;border-radius:9px;border:none;background:#f1f5f9;cursor:pointer;color:var(--c3);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;transition:all .15s}
.dm-close:hover{background:#fee2e2;color:#dc2626}
.dm-layout{display:flex;gap:0;min-height:0}
.dm-main{flex:1;min-width:0;padding:0;overflow-y:auto}
.dm-sidebar{width:280px;flex-shrink:0;border-left:1px solid var(--border);background:#fafbfc;display:flex;flex-direction:column;overflow-y:auto}
.dm-section{padding:18px 20px;border-bottom:1px solid #f1f5f9}
.dm-section:last-child{border-bottom:none}
.dm-section-title{font-size:10px;font-weight:800;color:var(--c3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px;display:flex;align-items:center;gap:6px}
.dm-section-title i{color:var(--accent);font-size:11px}
.dm-fluid-row{display:flex;align-items:center;gap:16px;margin-bottom:4px}
.dm-pct-big{font-size:36px;font-weight:900;line-height:1;letter-spacing:-1px}
.dm-pct-label{font-size:11px;color:var(--c3);margin-top:2px}
.dm-qty-bar{flex:1;height:10px;border-radius:5px;background:#e2e8f0;overflow:hidden}
.dm-qty-fill{height:100%;border-radius:5px;transition:width .6s cubic-bezier(.34,1.56,.64,1)}
.dm-qty-text{font-size:12px;color:var(--c2);margin-top:4px}
.dm-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.dm-info-item{display:flex;flex-direction:column;gap:2px}
.dm-info-item.full{grid-column:1/-1}
.dm-info-label{font-size:9.5px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.4px}
.dm-info-value{font-size:12.5px;color:var(--c1);font-weight:500;line-height:1.35}
.dm-info-value code{font-family:'Courier New',monospace;font-size:11px;background:#f1f5f9;padding:1px 6px;border-radius:4px;letter-spacing:.3px}
.dm-txn-group{display:flex;flex-direction:column;gap:8px;padding:16px 16px 8px}
.dm-txn-btn{display:flex;align-items:center;gap:10px;padding:12px 14px;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;width:100%;text-align:left}
.dm-txn-btn:hover{filter:brightness(1.05);transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,0,0,.15)}
.dm-txn-btn-ic{width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.dm-txn-btn-info{flex:1}
.dm-txn-btn-title{font-size:12px;font-weight:800;line-height:1}
.dm-txn-btn-sub{font-size:10px;opacity:.8;margin-top:1px;font-weight:400}
.dm-quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:12px 16px 16px}
.dm-quick-btn{display:flex;align-items:center;justify-content:center;gap:5px;padding:9px 8px;border:1.5px solid var(--border);border-radius:9px;background:#fff;color:var(--c2);font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .12s;text-decoration:none;white-space:nowrap}
.dm-quick-btn:hover{border-color:var(--accent);color:var(--accent);background:#f0fdf4}
.dm-tl-item{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #f8fafc}
.dm-tl-item:last-child{border-bottom:none;padding-bottom:0}
.dm-tl-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:4px}
.dm-tl-act{font-size:11px;font-weight:700;color:var(--c1);text-transform:capitalize}
.dm-tl-det{font-size:10.5px;color:var(--c3);margin-top:1px;line-height:1.4}
.dm-tl-time{font-size:9.5px;color:var(--c3);margin-top:2px}
/* Mobile detail overrides */
@media(max-width:768px){
    .dm-handle{display:block}
    .dm-layout{flex-direction:column}
    .dm-sidebar{width:100%;border-left:none;border-top:1px solid var(--border);background:#fff}
    .dm-hdr{padding:12px 16px 10px}
    .dm-hdr-ic{width:38px;height:38px;font-size:15px;border-radius:10px}
    .dm-hdr-name{font-size:14px}
    .dm-section{padding:14px 16px}
    .dm-txn-group{padding:12px 16px 6px;flex-direction:row;flex-wrap:wrap}
    .dm-txn-btn{flex:1;min-width:calc(50% - 4px);padding:10px 10px}
    .dm-txn-btn-sub{display:none}
    .dm-quick-grid{padding:10px 16px 14px}
    .dm-pct-big{font-size:28px}
    .dm-info-grid{grid-template-columns:1fr 1fr}
}


/* ── Mobile floating action bar ── */
.stk-mob-bar{position:sticky;top:0;z-index:200;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid rgba(0,0,0,.06);padding:10px 12px;display:none;gap:8px;align-items:center;margin-left:-16px;margin-right:-16px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.stk-mob-bar .stk-search{flex:1;min-width:0}
.stk-mob-bar .stk-search input{padding:8px 12px 8px 34px;font-size:13px;border-radius:10px}
.stk-mob-bar .stk-vw{border-color:var(--border)}
.stk-mob-bar .stk-vw button{padding:8px 10px}

/* ── Print Styles ── */
@media print{
    body *{visibility:hidden}
    .stk-print-area,.stk-print-area *{visibility:visible}
    .stk-print-area{position:absolute;left:0;top:0;width:100%}
    .stk-label{border:2px solid #000!important;break-inside:avoid;margin-bottom:12px}
    .stk-print-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
}

/* ══════════════════════════════════
   RESPONSIVE
══════════════════════════════════ */

/* ── Tablet ── */
@media(max-width:1024px){
    .stk-grid{grid-template-columns:repeat(auto-fill,minmax(260px,1fr))}
    .stk-an{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
    .stk-hero-meta{gap:14px}
    .stk-hero-c .v{font-size:22px}
    .stk-t .hide-tablet{display:none}
}

/* ── Mobile ── */
@media(max-width:768px){
    /* Hero — compact horizontal strip */
    .stk-hero{flex-direction:row;flex-wrap:wrap;padding:14px 16px;gap:10px;border-radius:12px;margin-bottom:14px}
    .stk-hero-ic{width:44px;height:44px;font-size:18px;border-radius:12px;flex-shrink:0}
    .stk-hero-info h2{font-size:15px}
    .stk-hero-info p{font-size:10px}
    .stk-hero-meta{margin-left:auto;gap:10px}
    .stk-hero-c .v{font-size:18px}
    .stk-hero-c .lb{font-size:9px}
    .stk-hero::after{display:none}

    /* Stats — horizontal scroll strip */
    .stk-stats{display:flex;overflow-x:auto;gap:8px;padding-bottom:6px;margin-bottom:14px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
    .stk-stats::-webkit-scrollbar{display:none}
    .stk-stat{min-width:120px;flex-shrink:0;padding:10px 12px;gap:8px}
    .stk-si{width:32px;height:32px;font-size:13px;border-radius:8px}
    .stk-sv{font-size:16px}
    .stk-sl{font-size:9px}

    /* Sticky mobile bar — always visible */
    .stk-mob-bar{display:flex}

    /* Hide desktop toolbar when sticky bar is shown */
    #toolbar{display:none}

    /* Tabs row — single row on mobile */
    .stk-tabs-row{flex-wrap:nowrap;gap:6px;align-items:center}
    .stk-tabs{flex:1;overflow-x:auto;scrollbar-width:none;min-width:0}
    .stk-tabs::-webkit-scrollbar{display:none}
    .stk-tab{padding:8px 14px;font-size:11px;white-space:nowrap}
    /* Hide desktop-only controls from tabs row */
    .stk-tabs-print,.stk-tabs-add,#viewSw{display:none!important}
    /* Show filter button in the tabs row */
    .stk-tabs-filter{display:inline-flex!important}

    /* My banner */
    .stk-my{flex-wrap:wrap;gap:8px;padding:12px 14px}
    .stk-my-av{width:36px;height:36px;border-radius:10px;font-size:15px}
    .stk-my h3{font-size:12px}
    .stk-my p{font-size:10px}
    .stk-my>div:last-child{margin-left:auto}

    /* Grid — 2 col for 480+ */
    .stk-grid{grid-template-columns:repeat(2,1fr);gap:8px}
    .stk-card-hd{padding:12px 12px 0}
    .stk-card-ic{width:32px;height:32px;font-size:13px;border-radius:8px}
    .stk-card-nm{font-size:11.5px}
    .stk-card-bd{padding:8px 12px 12px}
    .stk-card-actions{display:none}

    /* Compact view */
    .stk-cc,.stk-co,.stk-cl{display:none}
    .stk-cn{font-size:12px}

    /* Table — horizontal scroll on mobile */
    .stk-tw{border-radius:10px;overflow-x:auto;-webkit-overflow-scrolling:touch}
    .stk-t{min-width:500px}
    .stk-t .hide-tablet{display:none}

    /* Analytics */
    .stk-an{grid-template-columns:1fr}
    .stk-dn{flex-direction:column}

    /* Detail modal — bottom sheet */
    #detailOv{align-items:flex-end}
    #detailOv .stk-md{border-radius:22px 22px 0 0;max-height:92vh;margin:0;width:100%;max-width:100%;transform:translateY(20px) scale(1)}
    #detailOv.show .stk-md{transform:translateY(0) scale(1)}
    #detailOv .stk-md::before{content:'';display:block;width:44px;height:5px;background:#e2e8f0;border-radius:3px;margin:10px auto 0}

    /* Txn modal — bottom sheet */
    #txnOv{align-items:flex-end}
    #txnOv .stk-md{border-radius:22px 22px 0 0;max-height:88vh;margin:0;width:100%;max-width:100%;transform:translateY(20px) scale(1);overflow:visible}
    #txnOv.show .stk-md{transform:translateY(0) scale(1)}

    /* QR/Barcode modals */
    #qrOv .stk-md,#bcOv .stk-md{border-radius:20px 20px 0 0;width:100%;max-width:100%;margin-top:auto}
    #qrOv,#bcOv{align-items:flex-end}

    /* Pager */
    .stk-pager-info{display:none}
    .stk-pager button{width:28px;height:28px;font-size:10px}

    /* Batch bar — sit above the global mob-nav (56px) */
    .stk-batch{bottom:calc(56px + 10px);left:10px;right:10px;width:auto;max-width:100%;transform:none}

    /* Filter panel */
    .stk-fp.show{max-height:none}
    .stk-fg2{grid-template-columns:1fr 1fr}
}

/* ── Small phone ── */
@media(max-width:480px){
    .stk-hero-meta{display:none}
    .stk-hero{padding:12px 14px}
    .stk-grid{grid-template-columns:1fr}
    .stk-stats{gap:6px}
    .stk-stat{min-width:108px}
    .stk-tabs-row>:last-child .stk-vw{display:none}
    .stk-fg2{grid-template-columns:1fr}
    .stk-dg{grid-template-columns:1fr}
    .stk-my>div:last-child{display:none}
}
</style>
<body>
<?php Layout::sidebar('stock'); Layout::beginContent(); ?>

<!-- ═══ Hero Banner ═══ -->
<div class="stk-hero">
    <div class="stk-hero-ic"><i class="fas fa-flask"></i></div>
    <div class="stk-hero-info">
        <h2><?php echo $lang==='th'?'คลังขวดสารเคมี':'Chemical Bottle Stock'; ?></h2>
        <p><?php echo $lang==='th'
            ? ($canSeeAll?'ภาพรวมข้อมูลขวดสารเคมีทั้งหมดในระบบ':($isLab?'จัดการสารเคมีของทีมคุณ':($canViewOnly?'ดูรายการขวดสารเคมีในระบบ (อ่านได้อย่างเดียว)':'จัดการขวดสารเคมีของคุณ')))
            : ($canSeeAll?'Overview of all chemical bottles in the system':($isLab?'Manage your team\'s chemicals':($canViewOnly?'Browse chemical bottles — read-only until role is upgraded':'Manage your chemical bottles')));
        ?></p>
    </div>
    <div class="stk-hero-meta">
        <div class="stk-hero-c"><div class="v" id="heroTotal">—</div><div class="lb"><?php echo $lang==='th'?'ขวดทั้งหมด':'Total'; ?></div></div>
        <div class="stk-hero-c"><div class="v" id="heroMy">—</div><div class="lb"><?php echo $lang==='th'?'ของฉัน':'My Stock'; ?></div></div>
        <div class="stk-hero-c"><div class="v" id="hero3D">—</div><div class="lb">3D</div></div>
    </div>
</div>

<!-- ═══ Stats Row ═══ -->
<div class="stk-stats" id="statsRow"></div>

<!-- ═══ Mobile Sticky Bar ═══ -->
<div class="stk-mob-bar" id="mobBar">
    <div class="stk-search" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInputMob" placeholder="<?php echo $lang==='th'?'ค้นหาสารเคมี...':'Search chemicals...'; ?>" oninput="document.getElementById('searchInput').value=this.value;loadData()">
    </div>
    <div class="stk-vw" id="viewSwMob" style="flex-shrink:0">
        <button class="active" data-view="table" onclick="setView('table')" title="Table"><i class="fas fa-th-list"></i></button>
        <button data-view="grid" onclick="setView('grid')" title="Grid"><i class="fas fa-th-large"></i></button>
        <button data-view="compact" onclick="setView('compact')" title="Compact"><i class="fas fa-bars"></i></button>
    </div>
</div>

<!-- ═══ Tabs + View ═══ -->
<div class="stk-tabs-row" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px">
    <div class="stk-tabs" id="mainTabs">
        <button class="stk-tab <?php echo !$canViewOnly?'active':''; ?>" data-tab="my" onclick="switchTab('my')">
            <i class="fas fa-user"></i> <?php echo $lang==='th'?'ของฉัน':'My Stock'; ?>
            <span class="bg" id="badgeMy">0</span>
        </button>
        <button class="stk-tab <?php echo $canViewOnly?'active':''; ?>" data-tab="all" onclick="switchTab('all')">
            <i class="fas fa-globe"></i> <?php echo $lang==='th'?($canSeeAll?'ทั้งหมด':'ที่เข้าถึงได้'):($canSeeAll?'All':'Accessible'); ?>
            <span class="bg" id="badgeAll">0</span>
        </button>
    </div>
    <div class="stk-tabs-actions" style="display:flex;gap:6px;align-items:center">
        <div class="stk-vw" id="viewSw">
            <button class="active" data-view="table" onclick="setView('table')" title="Table"><i class="fas fa-th-list"></i></button>
            <button data-view="grid" onclick="setView('grid')" title="Grid"><i class="fas fa-th-large"></i></button>
            <button data-view="compact" onclick="setView('compact')" title="Compact"><i class="fas fa-bars"></i></button>
            <button data-view="analytics" onclick="setView('analytics')" title="Analytics"><i class="fas fa-chart-pie"></i></button>
        </div>
        <button class="stk-btn stk-btn-g stk-tabs-print" onclick="openPrintSettings()" title="<?php echo $lang==='th'?'ตั้งค่าการพิมพ์ฉลาก':'Label Print Settings'; ?>" style="border-color:#8b5cf6;color:#6d28d9;gap:5px">
            <i class="fas fa-print" style="font-size:11px"></i>
            <i class="fas fa-sliders-h" style="font-size:10px"></i>
        </button>
        <?php if ($canEdit): ?>
        <a href="/v1/pages/containers.php?action=add" class="stk-btn stk-btn-p stk-tabs-add"><i class="fas fa-plus"></i> <?php echo $lang==='th'?'เพิ่มขวด':'Add Bottle'; ?></a>
        <?php endif; ?>
        <!-- Filter button: shown on mobile only, in this row -->
        <button class="stk-btn stk-btn-g stk-tabs-filter" id="filterToggle" onclick="toggleFilter()" title="<?php echo $lang==='th'?'ตัวกรอง':'Filters'; ?>">
            <i class="fas fa-sliders-h"></i>
        </button>
    </div>
</div>

<!-- ═══ My Banner ═══ -->
<div class="stk-my" id="myBanner" style="display:none">
    <div class="stk-my-av"><?php if(!empty($user['avatar_url'])): ?><img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="<?php echo htmlspecialchars($userDisplayName); ?>" onerror="this.parentNode.textContent='<?php echo htmlspecialchars($userInitial); ?>'">
    <?php else: echo htmlspecialchars($userInitial); endif; ?></div>
    <div style="flex:1">
        <h3><?php echo $lang==='th'?'สารเคมีของฉัน':'My Chemical Stock'; ?> — <?php echo htmlspecialchars($userDisplayName); ?></h3>
        <p><?php echo $lang==='th'?'แสดงเฉพาะขวดที่คุณเป็นผู้เพิ่มหรือรับผิดชอบ':'Showing bottles you added or are responsible for'; ?></p>
    </div>
    <div style="display:flex;gap:14px">
        <div class="stk-hero-c"><div class="v" id="myStatTotal">—</div><div class="lb"><?php echo $lang==='th'?'ขวด':'Bottles'; ?></div></div>
        <div class="stk-hero-c"><div class="v" id="myStatActive" style="color:#4ade80">—</div><div class="lb"><?php echo $lang==='th'?'ปกติ':'Active'; ?></div></div>
    </div>
</div>

<!-- ═══ Toolbar ═══ -->
<div class="stk-toolbar" id="toolbar">
    <div class="stk-search">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="<?php echo $lang==='th'?'ค้นหา: รหัสขวด, ชื่อสาร, CAS, ผู้เพิ่ม, ผู้ผลิต...':'Search: bottle code, chemical, CAS, owner, manufacturer...'; ?>">
    </div>
    <button class="stk-btn stk-btn-g stk-filter-trigger" onclick="toggleFilter()">
        <i class="fas fa-sliders-h"></i> <?php echo $lang==='th'?'ตัวกรอง':'Filters'; ?>
    </button>
    <select id="sortSelect" style="padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;background:#fff">
        <option value="newest"><?php echo $lang==='th'?'ใหม่สุด':'Newest'; ?></option>
        <option value="oldest"><?php echo $lang==='th'?'เก่าสุด':'Oldest'; ?></option>
        <option value="name_asc">A → Z</option>
        <option value="name_desc">Z → A</option>
        <option value="pct_asc"><?php echo $lang==='th'?'เหลือน้อย':'Low first'; ?></option>
        <option value="pct_desc"><?php echo $lang==='th'?'เหลือมาก':'Full first'; ?></option>
        <option value="bottle_code"><?php echo $lang==='th'?'รหัสขวด':'Bottle Code'; ?></option>
    </select>
    <div class="stk-export-dd">
        <button class="stk-btn stk-btn-g" onclick="toggleExportMenu(event)"><i class="fas fa-file-export"></i> <?php echo $lang==='th'?'ส่งออก / รายงาน':'Export / Report'; ?> <i class="fas fa-chevron-down" style="font-size:9px;margin-left:2px"></i></button>
        <div class="stk-export-menu" id="exportMenu">
            <button class="stk-export-item" onclick="doExport('csv')">
                <i class="fas fa-file-csv" style="color:#16a34a"></i> <?php echo $lang==='th'?'ส่งออก CSV':'Export CSV'; ?>
                <span class="ext" style="background:#dcfce7;color:#16a34a">.csv</span>
            </button>
            <button class="stk-export-item" onclick="doExport('pdf_report')">
                <i class="fas fa-file-pdf" style="color:#dc2626"></i> <?php echo $lang==='th'?'รายงาน PDF (สรุป)':'Summary Report PDF'; ?>
                <span class="ext" style="background:#fee2e2;color:#dc2626">.pdf</span>
            </button>
            <div class="stk-export-sep"></div>
            <button class="stk-export-item" onclick="doPrintLabels('selected')">
                <i class="fas fa-print" style="color:#7c3aed"></i> <?php echo $lang==='th'?'พิมพ์ฉลากขวด (ที่เลือก)':'Print Labels (Selected)'; ?>
            </button>
            <button class="stk-export-item" onclick="doPrintLabels('all')">
                <i class="fas fa-tags" style="color:#0d9488"></i> <?php echo $lang==='th'?'พิมพ์ฉลากทั้งหมด (หน้านี้)':'Print All Labels (This Page)'; ?>
            </button>
            <div class="stk-export-sep"></div>
            <button class="stk-export-item" onclick="doPrintLabels('qr_sheet')">
                <i class="fas fa-qrcode" style="color:#2563eb"></i> <?php echo $lang==='th'?'แผ่น QR Code (A4)':'QR Code Sheet (A4)'; ?>
            </button>
        </div>
    </div>
</div>

<!-- ═══ Filter Panel ═══ -->
<div class="stk-fp" id="filterPanel">
    <div class="stk-fg2">
        <div class="stk-fl">
            <label><?php echo $lang==='th'?'สถานะ':'Status'; ?></label>
            <select id="fStatus">
                <option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option>
                <option value="active"><?php echo $lang==='th'?'ปกติ':'Active'; ?></option>
                <option value="empty"><?php echo $lang==='th'?'หมด':'Empty'; ?></option>
                <option value="expired"><?php echo $lang==='th'?'หมดอายุ':'Expired'; ?></option>
                <option value="quarantined"><?php echo $lang==='th'?'กักกัน':'Quarantined'; ?></option>
            </select>
        </div>
        <div class="stk-fl">
            <label><?php echo $lang==='th'?'ประเภท':'Type'; ?></label>
            <select id="fType">
                <option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option>
                <option value="bottle">Bottle</option>
                <option value="vial">Vial</option>
                <option value="flask">Flask</option>
                <option value="canister">Canister</option>
                <option value="cylinder">Cylinder</option>
                <option value="ampoule">Ampoule</option>
            </select>
        </div>
        <div class="stk-fl">
            <label><?php echo $lang==='th'?'อาคาร':'Building'; ?></label>
            <select id="fBuilding">
                <option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option>
            </select>
        </div>
        <div class="stk-fl">
            <label><?php echo $lang==='th'?'แหล่งข้อมูล':'Source'; ?></label>
            <select id="fSource">
                <option value=""><?php echo $lang==='th'?'ทั้งหมด':'All'; ?></option>
                <option value="container"><?php echo $lang==='th'?'ระบบใหม่ (Container)':'System (Container)'; ?></option>
                <option value="stock"><?php echo $lang==='th'?'คลังเดิม (CSV)':'Legacy (CSV)'; ?></option>
            </select>
        </div>
    </div>
    <div class="stk-fg2" style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.06)">
        <label class="stk-fl-toggle" style="display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;font-size:12px;color:var(--c2)">
            <input type="checkbox" id="fShowDisposed" onchange="loadData(1)"
                style="width:15px;height:15px;accent-color:#ef4444;cursor:pointer;flex-shrink:0">
            <span><i class="fas fa-trash-alt" style="color:#ef4444;margin-right:4px;font-size:11px"></i><?php echo $lang==='th'?'แสดงสารที่กำจัดแล้ว':'Show disposed items'; ?></span>
        </label>
    </div>
    <div class="stk-fa">
        <button class="stk-btn stk-btn-g stk-btn-s" onclick="clearFilters()"><i class="fas fa-undo"></i> <?php echo $lang==='th'?'ล้าง':'Clear'; ?></button>
    </div>
</div>

<!-- ═══ Data Area ═══ -->
<div id="dataArea"></div>
<div class="stk-pager" id="pagerArea"></div>

<!-- ═══ Batch Actions Bar ═══ -->
<div class="stk-batch" id="batchBar" style="display:none">
    <!-- Count badge -->
    <div class="bb-count">
        <div class="bb-num" id="selCount">0</div>
        <span class="bb-lbl"><?php echo $lang==='th'?'รายการที่เลือก':'selected'; ?></span>
    </div>
    <!-- Transaction group: admin/lab_manager can act on all; user can act on own items -->
    <?php if($canAct): ?>
    <div class="bb-grp">
        <button id="bbBtnUse" class="bab bab-use" onclick="openBatchTxn('use')" title="<?php echo $lang==='th'?'เบิกใช้':'Use'; ?>">
            <i class="fas fa-flask"></i><span class="bab-lbl"><?php echo $lang==='th'?'เบิกใช้':'Use'; ?></span>
        </button>
        <button id="bbBtnBorrow" class="bab bab-borrow" onclick="openBatchTxn('borrow')" title="<?php echo $lang==='th'?'ยืม':'Borrow'; ?>">
            <i class="fas fa-hand-holding"></i><span class="bab-lbl"><?php echo $lang==='th'?'ยืม':'Borrow'; ?></span>
        </button>
        <button id="bbBtnTransfer" class="bab bab-transfer" onclick="openBatchTxn('transfer')" title="<?php echo $lang==='th'?'โอน':'Transfer'; ?>">
            <i class="fas fa-share-nodes"></i><span class="bab-lbl"><?php echo $lang==='th'?'โอน':'Transfer'; ?></span>
        </button>
        <?php if($isAdmin||$isLab): ?>
        <button class="bab bab-dispose" onclick="openDispose()" title="<?php echo $lang==='th'?'จำหน่ายออก':'Dispose'; ?>">
            <i class="fas fa-trash-alt"></i><span class="bab-lbl"><?php echo $lang==='th'?'กำจัด':'Dispose'; ?></span>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <!-- Print group -->
    <div class="bb-grp">
        <button class="bab bab-print" onclick="doPrintLabels('selected')" title="<?php echo $lang==='th'?'พิมพ์ฉลาก':'Print Labels'; ?>">
            <i class="fas fa-print"></i><span class="bab-lbl"><?php echo $lang==='th'?'ฉลาก':'Labels'; ?></span>
        </button>
        <button class="bab bab-print" onclick="doPrintLabels('qr_selected')" title="QR Sheet">
            <i class="fas fa-qrcode"></i><span class="bab-lbl">QR</span>
        </button>
    </div>
    <!-- Cancel -->
    <div class="bb-grp" style="padding-left:2px">
        <button class="bab bab-cancel" onclick="clearSelection()" title="<?php echo $lang==='th'?'ยกเลิกการเลือก':'Clear selection'; ?>">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<!-- ═══ Batch Transaction Modal ═══ -->
<div class="btx-ov" id="btxOv" onclick="if(event.target===this)closeBatchTxn()">
    <div class="btx-md">
        <div class="btx-handle"><div class="btx-handle-bar"></div></div>
        <!-- Header -->
        <div class="btx-hdr">
            <div class="btx-hdr-top">
                <div class="btx-hdr-ic" id="btxHdrIc"><i class="fas fa-flask"></i></div>
                <div class="btx-hdr-info">
                    <div class="btx-hdr-title" id="btxHdrTitle"><?php echo $lang==='th'?'เบิกใช้หลายรายการ':'Batch Use'; ?></div>
                    <div class="btx-hdr-sub" id="btxHdrSub"></div>
                </div>
                <button class="btx-hdr-close" onclick="closeBatchTxn()"><i class="fas fa-times"></i></button>
            </div>
            <!-- Type tabs -->
            <div class="btx-tabs" id="btxTabs">
                <button class="btx-tab" id="btxTabUse" onclick="switchBatchTab('use')">
                    <i class="fas fa-flask"></i> <?php echo $lang==='th'?'เบิกใช้':'Use'; ?>
                </button>
                <button class="btx-tab" id="btxTabBorrow" onclick="switchBatchTab('borrow')">
                    <i class="fas fa-hand-holding"></i> <?php echo $lang==='th'?'ยืม':'Borrow'; ?>
                </button>
                <button class="btx-tab" id="btxTabTransfer" onclick="switchBatchTab('transfer')">
                    <i class="fas fa-share-nodes"></i> <?php echo $lang==='th'?'โอน':'Transfer'; ?>
                </button>
            </div>
        </div>
        <!-- Scrollable body -->
        <div class="btx-body" id="btxBody"></div>
        <!-- Footer -->
        <div class="btx-footer">
            <button class="btx-btn-cancel" onclick="closeBatchTxn()">
                <?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?>
            </button>
            <button class="btx-btn-submit" id="btxSubmitBtn" onclick="submitBatchTxn()">
                <i class="fas fa-check"></i>
                <span id="btxSubmitLbl"><?php echo $lang==='th'?'ยืนยันดำเนินการ':'Confirm'; ?></span>
            </button>
        </div>
    </div>
</div>

<!-- ═══ Dispose Modal ═══ -->
<div class="dsp-ov" id="dspOv" onclick="if(event.target===this)closeDispose()">
<div class="dsp-md">
    <div class="dsp-hdr">
        <div class="dsp-hdr-top">
            <div class="dsp-hdr-ic"><i class="fas fa-trash-alt"></i></div>
            <div>
                <div class="dsp-hdr-title"><?php echo $lang==='th'?'จำหน่ายสารเคมีออกจากระบบ':'Dispose / Write-off Chemicals'; ?></div>
                <div class="dsp-hdr-sub" id="dspHdrSub"></div>
            </div>
            <button class="dsp-hdr-close" onclick="closeDispose()"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="dsp-body" id="dspBody"></div>
    <div class="dsp-footer">
        <button class="dsp-btn-confirm" id="dspSubmitBtn" onclick="submitDispose()">
            <i class="fas fa-trash-alt"></i>
            <span id="dspSubmitLbl"><?php echo $lang==='th'?'จำหน่าย':'Confirm Dispose'; ?></span>
        </button>
        <button class="dsp-btn-cancel" onclick="closeDispose()"><?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></button>
    </div>
</div>
</div>

<!-- ═══ Dispose Confirm Popup ═══ -->
<div class="dspCf-ov" id="dspCfOv" onclick="if(event.target===this)_closeDspCf()">
    <div class="dspCf-box" id="dspCfBox"></div>
</div>

<!-- ═══ Pre-Transfer Preview Popup ═══ -->
<div class="btc-ov" id="btcOv">
    <div class="btc-box" id="btcBox"><!-- filled by JS --></div>
</div>

<!-- ═══ Transfer Confirm Popup ═══ -->
<div class="tfc-ov" id="tfcOv">
    <div class="tfc-box" id="tfcBox">
        <!-- filled by JS -->
    </div>
</div>

<!-- ═══ Detail Modal ═══ -->
<div class="stk-ov" id="detailOv" onclick="if(event.target===this)closeDetail()">
    <div class="stk-md" id="detailModal"></div>
</div>

<!-- ═══ Print Settings Modal ═══ -->
<div class="ps-ov" id="psOv" onclick="if(event.target===this)closePrintSettings()">
  <div class="ps-md">
    <div class="ps-hdr">
      <div class="ps-hdr-icon"><i class="fas fa-print"></i></div>
      <div style="flex:1">
        <h3><?php echo $lang==='th'?'ตั้งค่าการพิมพ์ฉลาก':'Label Print Settings'; ?></h3>
        <p><?php echo $lang==='th'?'กำหนดขนาด กระดาษ เครื่องพิมพ์ และดูตัวอย่างฉลาก':'Configure paper size, printer type, and preview your label layout'; ?></p>
      </div>
      <span class="ps-badge-save" id="psAutoSaveBadge" style="display:none"><i class="fas fa-check"></i> <?php echo $lang==='th'?'บันทึกแล้ว':'Saved'; ?></span>
      <button class="ps-close" onclick="closePrintSettings()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ps-body">
      <!-- Left: Controls -->
      <div class="ps-left" id="psLeft">

        <!-- Printer type -->
        <div class="ps-section">
          <div class="ps-section-hdr"><i class="fas fa-print"></i> <?php echo $lang==='th'?'เครื่องพิมพ์':'Printer Type'; ?></div>
          <div class="ps-printer-row">
            <div class="ps-printer active" data-ptype="thermal" onclick="psPrinterSelect(this)">
              <div class="ps-printer-icon thermal"><i class="fas fa-receipt"></i></div>
              <div><div class="ps-printer-name">Thermal Printer</div><div class="ps-printer-sub">Aiyin AE230, Zebra, DYMO, Brother</div></div>
              <div class="ps-printer-dot"></div>
            </div>
            <div class="ps-printer" data-ptype="inkjet" onclick="psPrinterSelect(this)">
              <div class="ps-printer-icon inkjet"><i class="fas fa-file-alt"></i></div>
              <div><div class="ps-printer-name">Inkjet / Laser</div><div class="ps-printer-sub">A4 sheet, multiple labels per page</div></div>
              <div class="ps-printer-dot"></div>
            </div>
          </div>
        </div>

        <!-- Paper/Label size presets -->
        <div class="ps-section">
          <div class="ps-section-hdr"><i class="fas fa-ruler-combined"></i> <?php echo $lang==='th'?'ขนาดฉลาก (preset)':'Label Size (Presets)'; ?></div>
          <div class="ps-preset-grid" id="psPresets">
            <div class="ps-preset active" data-w="60" data-h="40" onclick="psPresetSelect(this)">
              <div class="ps-preset-name">AE230 S</div>
              <div class="ps-preset-dim">60 × 40 mm</div>
              <span class="ps-preset-tag ps-tag-thermal">Thermal</span>
            </div>
            <div class="ps-preset" data-w="80" data-h="50" onclick="psPresetSelect(this)">
              <div class="ps-preset-name">AE230 M</div>
              <div class="ps-preset-dim">80 × 50 mm</div>
              <span class="ps-preset-tag ps-tag-thermal">Thermal</span>
            </div>
            <div class="ps-preset" data-w="100" data-h="70" onclick="psPresetSelect(this)">
              <div class="ps-preset-name">AE230 L</div>
              <div class="ps-preset-dim">100 × 70 mm</div>
              <span class="ps-preset-tag ps-tag-thermal">Thermal</span>
            </div>
            <div class="ps-preset" data-w="50" data-h="25" onclick="psPresetSelect(this)">
              <div class="ps-preset-name">Mini</div>
              <div class="ps-preset-dim">50 × 25 mm</div>
              <span class="ps-preset-tag ps-tag-thermal">Thermal</span>
            </div>
            <div class="ps-preset" data-w="105" data-h="74" onclick="psPresetSelect(this)">
              <div class="ps-preset-name">A7</div>
              <div class="ps-preset-dim">105 × 74 mm</div>
              <span class="ps-preset-tag ps-tag-a4">Sheet</span>
            </div>
            <div class="ps-preset" data-w="210" data-h="297" onclick="psPresetSelect(this)">
              <div class="ps-preset-name">A4</div>
              <div class="ps-preset-dim">210 × 297 mm</div>
              <span class="ps-preset-tag ps-tag-a4">Sheet</span>
            </div>
          </div>
        </div>

        <!-- Custom size -->
        <div class="ps-section">
          <div class="ps-section-hdr"><i class="fas fa-arrows-alt-h"></i> <?php echo $lang==='th'?'ขนาดกำหนดเอง (mm)':'Custom Size (mm)'; ?></div>
          <div class="ps-row">
            <label><?php echo $lang==='th'?'กว้าง × สูง (มิลลิเมตร)':'Width × Height (mm)'; ?></label>
            <div class="ps-dim-row">
              <input type="number" id="psW" min="20" max="300" value="60" step="1" oninput="psOnDimChange()">
              <span class="ps-dim-sep">×</span>
              <input type="number" id="psH" min="15" max="400" value="40" step="1" oninput="psOnDimChange()">
            </div>
          </div>
        </div>

        <!-- Columns (for sheet printing) -->
        <div class="ps-section" id="psColsSection">
          <div class="ps-section-hdr"><i class="fas fa-columns"></i> <?php echo $lang==='th'?'คอลัมน์ต่อหน้า':'Columns per Page'; ?></div>
          <div class="ps-cols-row" id="psColsRow">
            <div class="ps-col-opt active" data-cols="1" onclick="psColSelect(this)">1</div>
            <div class="ps-col-opt" data-cols="2" onclick="psColSelect(this)">2</div>
            <div class="ps-col-opt" data-cols="3" onclick="psColSelect(this)">3</div>
            <div class="ps-col-opt" data-cols="4" onclick="psColSelect(this)">4</div>
          </div>
        </div>

        <!-- Options -->
        <div class="ps-section">
          <div class="ps-section-hdr"><i class="fas fa-sliders-h"></i> <?php echo $lang==='th'?'ตัวเลือก':'Options'; ?></div>
          <div class="ps-toggle-row">
            <span class="ps-label-mini"><i class="fas fa-qrcode" style="color:#6d28d9;margin-right:4px"></i> <?php echo $lang==='th'?'แสดง QR Code':'Show QR Code'; ?></span>
            <label class="ps-toggle"><input type="checkbox" id="psOptQR" checked onchange="psUpdatePreview()"><span class="ps-toggle-slider"></span></label>
          </div>
          <div class="ps-toggle-row">
            <span class="ps-label-mini"><i class="fas fa-barcode" style="color:#374151;margin-right:4px"></i> <?php echo $lang==='th'?'แสดง Barcode':'Show Barcode'; ?></span>
            <label class="ps-toggle"><input type="checkbox" id="psOptBar" checked onchange="psUpdatePreview()"><span class="ps-toggle-slider"></span></label>
          </div>
          <div class="ps-toggle-row">
            <span class="ps-label-mini"><i class="fas fa-radiation" style="color:#dc2626;margin-right:4px"></i> <?php echo $lang==='th'?'แสดงสัญลักษณ์ GHS':'Show GHS Symbols'; ?></span>
            <label class="ps-toggle"><input type="checkbox" id="psOptGHS" checked onchange="psUpdatePreview()"><span class="ps-toggle-slider"></span></label>
          </div>
          <div class="ps-toggle-row">
            <span class="ps-label-mini"><i class="fas fa-map-marker-alt" style="color:#10b981;margin-right:4px"></i> <?php echo $lang==='th'?'แสดงตำแหน่งจัดเก็บ':'Show Storage Location'; ?></span>
            <label class="ps-toggle"><input type="checkbox" id="psOptLoc" checked onchange="psUpdatePreview()"><span class="ps-toggle-slider"></span></label>
          </div>
        </div>

      </div><!-- /ps-left -->

      <!-- Right: Preview + Ruler -->
      <div class="ps-right">
        <div style="padding:16px 20px 10px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#fff;flex-shrink:0">
          <div>
            <div style="font-size:12px;font-weight:800;color:#1e293b"><i class="fas fa-eye" style="color:#8b5cf6;margin-right:5px"></i><?php echo $lang==='th'?'ตัวอย่างฉลาก':'Label Preview'; ?></div>
            <div style="font-size:10.5px;color:#94a3b8;margin-top:1px"><?php echo $lang==='th'?'แสดงขนาดจริงสัมพัทธ์':'Proportional real-size preview'; ?></div>
          </div>
          <span class="ps-preview-size-badge" id="psSizeBadge">60 × 40 mm</span>
        </div>
        <div class="ps-preview-area" id="psPreviewArea">
          <!-- Ruler + label injected by JS -->
        </div>
      </div>
    </div><!-- /ps-body -->

    <div class="ps-footer">
      <button class="ps-btn ps-btn-test" onclick="psTestPrint()"><i class="fas fa-vial"></i> <?php echo $lang==='th'?'ทดสอบการพิมพ์':'Test Print'; ?></button>
      <button class="ps-btn ps-btn-sec" onclick="psResetDefaults()"><i class="fas fa-undo"></i> <?php echo $lang==='th'?'ค่าเริ่มต้น':'Reset'; ?></button>
      <div style="flex:1"></div>
      <button class="ps-btn ps-btn-sec" onclick="closePrintSettings()"><i class="fas fa-times"></i> <?php echo $lang==='th'?'ปิด':'Close'; ?></button>
      <button class="ps-btn ps-btn-primary" onclick="psSaveSettings()"><i class="fas fa-save"></i> <?php echo $lang==='th'?'บันทึกการตั้งค่า':'Save Settings'; ?></button>
    </div>
  </div>
</div>

<!-- ═══ Label Preview Modal ═══ -->
<div class="stk-ov" id="labelOv" onclick="if(event.target===this)closeLabelModal()">
    <div class="stk-md" style="max-width:900px" id="labelModal">
        <div class="stk-mh">
            <h3><i class="fas fa-tag" style="color:var(--accent)"></i> <?php echo $lang==='th'?'ฉลากขวดสารเคมี':'Chemical Bottle Labels'; ?></h3>
            <button class="stk-mx" onclick="closeLabelModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="stk-mb" id="labelContent">
            <div style="text-align:center;padding:40px;color:var(--c3)"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
        </div>
    </div>
</div>

<!-- ═══ QR Code Enlarge Modal ═══ -->
<div class="stk-ov" id="qrOv" onclick="if(event.target===this)closeQrModal()">
    <div class="stk-md stk-md-sm" id="qrModal">
        <div class="stk-mh">
            <h3><i class="fas fa-qrcode" style="color:var(--accent)"></i> QR Code</h3>
            <button class="stk-mx" onclick="closeQrModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="stk-mb" id="qrContent"></div>
    </div>
</div>

<!-- ═══ Barcode Edit Modal ═══ -->
<div class="stk-ov" id="bcOv" onclick="if(event.target===this)closeBarcodeEdit()">
    <div class="stk-md stk-md-sm" id="bcModal" style="max-width:480px">
        <div class="stk-mh">
            <h3><i class="fas fa-barcode" style="color:#f59e0b"></i> <?php echo $lang==='th'?'แก้ไข Barcode':'Edit Barcode'; ?></h3>
            <button class="stk-mx" onclick="closeBarcodeEdit()"><i class="fas fa-times"></i></button>
        </div>
        <div class="stk-mb">
            <div class="stk-bc-warn">
                <i class="fas fa-exclamation-triangle" style="margin-top:1px;flex-shrink:0"></i>
                <div><?php echo $lang==='th'?'การแก้ไข Barcode / QR Code จะส่งผลต่อ QR Scanner และป้ายสติ๊กเกอร์ที่พิมพ์ไปแล้ว กรุณาตรวจสอบให้แน่ใจก่อนบันทึก':'Changing the Barcode / QR Code affects scanner lookups and any printed labels. Please verify before saving.'; ?></div>
            </div>
            <div class="stk-bc-form" id="bcForm">
                <div class="stk-bc-field">
                    <label><i class="fas fa-barcode"></i> Bottle Code (Barcode)</label>
                    <input type="text" id="bcInputBottle" placeholder="e.g. F02212A6000028" maxlength="32" autocomplete="off" spellcheck="false">
                    <div class="stk-bc-old" id="bcOldBottle"></div>
                </div>
                <div class="stk-bc-field">
                    <label><i class="fas fa-qrcode"></i> QR Code</label>
                    <input type="text" id="bcInputQR" placeholder="e.g. CHEM-001" maxlength="64" autocomplete="off" spellcheck="false">
                    <div class="stk-bc-old" id="bcOldQR"></div>
                </div>
                <div class="stk-bc-actions">
                    <button class="stk-btn stk-btn-g" onclick="closeBarcodeEdit()"><?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></button>
                    <button class="stk-btn stk-btn-p" id="bcSaveBtn" onclick="saveBarcodeEdit()"><i class="fas fa-save"></i> <?php echo $lang==='th'?'บันทึก':'Save'; ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Transaction Modal (เบิก/ยืม/โอน) ═══ -->
<div class="stk-ov" id="txnOv" onclick="if(event.target===this)closeTxnModal()">
    <div class="stk-md" id="txnModal" style="max-width:500px;padding:0;overflow:visible">
        <div class="txn-header" id="txnHeader">
            <div class="txn-header-ic" id="txnHeaderIc"><i class="fas fa-exchange-alt"></i></div>
            <div>
                <h3 id="txnHeaderTitle">Transaction</h3>
                <p id="txnHeaderSub"></p>
            </div>
            <button class="txn-close" onclick="closeTxnModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="txn-tabs" id="txnTabs" style="display:none">
            <button class="txn-tab active" id="ttUse" onclick="switchTxnTab('use')"><i class="fas fa-flask"></i> เบิกใช้</button>
            <button class="txn-tab" id="ttBorrow" onclick="switchTxnTab('borrow')"><i class="fas fa-hand-holding"></i> ยืม</button>
            <button class="txn-tab" id="ttTransfer" onclick="switchTxnTab('transfer')"><i class="fas fa-share-nodes"></i> โอน</button>
        </div>
        <div class="txn-body" id="txnBody">
            <div style="text-align:center;padding:40px;color:var(--c3)"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></div>
        </div>
    </div>
</div>

<!-- ═══ Borrow Confirmation overlay ═══ -->
<div class="stk-ov" id="txnConfirmOv" style="z-index:10002">
    <div class="txn-confirm-card" id="txnConfirmBox"></div>
</div>

<!-- ═══ Hidden print area ═══ -->
<div class="stk-print-area" id="printArea" style="display:none"></div>

<!-- ═══ Toast ═══ -->
<div class="stk-toast" id="stkToast"></div>
<?php Layout::endContent(); ?>

<script>
/* ═════════════════════════════════════════
   CONFIG & STATE
   ═════════════════════════════════════════ */
const L='<?php echo $lang; ?>';
const ROLE='<?php echo $role; ?>';
const UID=<?php echo (int)$uid; ?>;
const IS_ADMIN=<?php echo $isAdmin?'true':'false'; ?>;
const IS_LAB=<?php echo $isLab?'true':'false'; ?>;
const CAN_EDIT=<?php echo $canEdit?'true':'false'; ?>;
const CAN_ACT=<?php echo $canAct?'true':'false'; ?>;
const CAN_SEE_ALL=<?php echo $canSeeAll?'true':'false'; ?>;
const CAN_VIEW_ONLY=<?php echo $canViewOnly?'true':'false'; ?>;
const USER_NAME='<?php echo addslashes($userDisplayName); ?>';
// Pre-cache current user so batch borrow can auto-fill without an extra API call
const _CURRENT_USER_CACHE={id:UID,first_name:'<?php echo addslashes($user['first_name']??''); ?>',last_name:'<?php echo addslashes($user['last_name']??''); ?>',username:'<?php echo addslashes($user['username']??''); ?>',department:'<?php echo addslashes($user['department']??''); ?>',avatar_url:'<?php echo addslashes($user['avatar_url']??''); ?>'};

let VIEW='table',TAB=CAN_VIEW_ONLY?'all':'my',PAGE=1;
let DATA=[],STATS=null,SELECTED=new Set(),_txnItem=null;
const T=(th,en)=>L==='th'?th:en;

const typeIcons={bottle:'fa-wine-bottle',vial:'fa-vial',flask:'fa-flask',canister:'fa-gas-pump',cylinder:'fa-fire-extinguisher',ampoule:'fa-syringe',bag:'fa-bag-shopping',other:'fa-box'};
const typeLabels={bottle:'Bottle',vial:'Vial',flask:'Flask',canister:'Canister',cylinder:'Cylinder',ampoule:'Ampoule',bag:'Bag',other:'Other'};

const ghsTinyIcons={compressed_gas:'fa-wind',flammable:'fa-fire-flame-curved',oxidizing:'fa-circle-radiation',toxic:'fa-skull-crossbones',corrosive:'fa-flask-vial',irritant:'fa-exclamation-triangle',environmental:'fa-leaf',health_hazard:'fa-heart-crack',explosive:'fa-explosion'};
const ghsTinyColors={compressed_gas:'#d97706',flammable:'#dc2626',oxidizing:'#d97706',toxic:'#991b1b',corrosive:'#7c3aed',irritant:'#f59e0b',environmental:'#16a34a',health_hazard:'#dc2626',explosive:'#ea580c'};
const ghsLabelsMap={compressed_gas:T('ก๊าซอัด','Compressed Gas'),flammable:T('ไวไฟ','Flammable'),oxidizing:T('วัตถุออกซิไดซ์','Oxidizing'),toxic:T('พิษเฉียบพลัน','Toxic'),corrosive:T('กัดกร่อน','Corrosive'),irritant:T('ระคายเคือง','Irritant'),environmental:T('อันตรายต่อสิ่งแวดล้อม','Environmental Hazard'),health_hazard:T('อันตรายต่อสุขภาพ','Health Hazard'),explosive:T('วัตถุระเบิด','Explosive')};

/* ═════════════════════════════════════════
   INIT
   ═════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded',()=>{
    loadStats();switchTab(CAN_VIEW_ONLY?'all':'my');setupSearch();loadBuildingFilter();
    initMobileUI();
});

/* ═════════════════════════════════════════
   MOBILE UI INIT
   ═════════════════════════════════════════ */
function initMobileUI(){
    // Swipe-to-close for bottom sheet modals
    ['detailOv','txnOv','qrOv','bcOv'].forEach(id=>{
        const ov=document.getElementById(id);
        if(!ov) return;
        let startY=0,isDragging=false;
        ov.addEventListener('touchstart',e=>{
            const md=ov.querySelector('.stk-md');
            if(!md) return;
            const touch=e.touches[0];
            const rect=md.getBoundingClientRect();
            // Only start drag from top 60px of modal (handle zone)
            if(touch.clientY-rect.top>60) return;
            startY=touch.clientY;
            isDragging=true;
            md.style.transition='none';
        },{passive:true});
        ov.addEventListener('touchmove',e=>{
            if(!isDragging) return;
            const md=ov.querySelector('.stk-md');
            if(!md) return;
            const dy=e.touches[0].clientY-startY;
            if(dy>0) md.style.transform=`translateY(${dy}px)`;
        },{passive:true});
        ov.addEventListener('touchend',e=>{
            if(!isDragging) return;
            isDragging=false;
            const md=ov.querySelector('.stk-md');
            if(!md) return;
            md.style.transition='';
            const dy=e.changedTouches[0].clientY-startY;
            if(dy>80){
                // Dismiss
                ov.classList.remove('show');
                setTimeout(()=>{md.style.transform='';},300);
            } else {
                md.style.transform='';
            }
        },{passive:true});
    });

    // Hide mob-bar when sidebar opens (sidebar has z-index:100, mob-bar z-index:200)
    const mobBar=document.getElementById('mobBar');
    const sidebarEl=document.getElementById('sidebar');
    const overlayEl=document.getElementById('overlay');
    if(mobBar&&sidebarEl){
        // Watch sidebar class changes via MutationObserver
        new MutationObserver(()=>{
            const isOpen=sidebarEl.classList.contains('open');
            mobBar.classList.toggle('sb-hidden',isOpen);
        }).observe(sidebarEl,{attributes:true,attributeFilter:['class']});
        // Also hide when overlay is clicked (sidebar closes)
        if(overlayEl){
            overlayEl.addEventListener('click',()=>{
                mobBar.classList.remove('sb-hidden');
            });
        }
    }

    // Resize handler — sync view switcher active state on resize
    let _resizeTimer;
    window.addEventListener('resize',()=>{
        clearTimeout(_resizeTimer);
        _resizeTimer=setTimeout(()=>{
            const isMobile=window.innerWidth<=768;
            // Sync both view switchers
            document.querySelectorAll('#viewSw button,#viewSwMob button').forEach(b=>{
                b.classList.toggle('active',b.dataset.view===VIEW);
            });
            // Hide analytics toolbar on desktop only
            if(!isMobile){
                const tb=document.getElementById('toolbar');
                if(tb) tb.style.display=VIEW==='analytics'?'none':'flex';
            }
        },150);
    });
}
function setupSearch(){
    let t;
    function onSearch(){clearTimeout(t);t=setTimeout(()=>loadData(1),300);}
    document.getElementById('searchInput').addEventListener('input',onSearch);
    // Sync desktop search ↔ mobile search
    document.getElementById('searchInput').addEventListener('input',function(){
        const mob=document.getElementById('searchInputMob');
        if(mob&&document.activeElement!==mob) mob.value=this.value;
    });
    const mobInp=document.getElementById('searchInputMob');
    if(mobInp) mobInp.addEventListener('input',function(){
        document.getElementById('searchInput').value=this.value;
        onSearch();
    });
}

/* ═════════════════════════════════════════
   STATS
   ═════════════════════════════════════════ */
async function loadStats(){
    try{
        const d=await apiFetch('/v1/api/containers.php?action=stats');
        if(!d.success)return;
        STATS=d.data;const s=d.data;
        document.getElementById('heroTotal').textContent=num(s.total);
        document.getElementById('heroMy').textContent=num(s.my_total||0);
        document.getElementById('hero3D').textContent=num(s.models_3d||0);
        document.getElementById('badgeAll').textContent=num(s.total);
        document.getElementById('badgeMy').textContent=num(s.my_total||0);
        // Source breakdown tooltip
        const sb=s.source_breakdown||{};
        const heroEl=document.getElementById('heroTotal');
        if(heroEl)heroEl.title=`Container: ${num(sb.containers||0)} | CSV Stock: ${num(sb.stock||0)}`;
        const mst=document.getElementById('myStatTotal');if(mst)mst.textContent=num(s.my_total||0);
        const msa=document.getElementById('myStatActive');if(msa)msa.textContent=num(s.my_active||0);
        const cards=[
            {icon:'fa-check-circle',bg:'#dcfce7',fg:'#15803d',v:s.active,l:T('ปกติ','Active'),k:'active'},
            {icon:'fa-flask-vial',bg:'#dbeafe',fg:'#2563eb',v:s.chemicals,l:T('สารเคมี','Chemicals'),k:''},
            {icon:'fa-battery-quarter',bg:'#fef3c7',fg:'#d97706',v:s.low,l:T('เหลือน้อย','Low'),k:''},
            {icon:'fa-clock',bg:'#fee2e2',fg:'#dc2626',v:s.expiring_soon,l:T('ใกล้หมดอายุ','Expiring'),k:''},
            {icon:'fa-box-archive',bg:'#f1f5f9',fg:'#64748b',v:s.empty,l:T('หมดแล้ว','Empty'),k:'empty'},
            {icon:'fa-cube',bg:'#ede9fe',fg:'#7c3aed',v:s.models_3d,l:'3D Models',k:''},
        ];
        document.getElementById('statsRow').innerHTML=cards.map(c=>`
            <div class="stk-stat" ${c.k?`onclick="quickFilter('${c.k}')"`:''}> 
                <div class="stk-si" style="background:${c.bg};color:${c.fg}"><i class="fas ${c.icon}"></i></div>
                <div><div class="stk-sv">${num(c.v)}</div><div class="stk-sl">${c.l}</div></div>
            </div>`).join('');
    }catch(e){console.error(e)}
}
function quickFilter(st){
    const sel=document.getElementById('fStatus');
    if(sel.value===st){sel.value=''}else{sel.value=st}
    loadData(1);
}

/* ═════════════════════════════════════════
   LOAD DATA
   ═════════════════════════════════════════ */
async function loadData(page){
    PAGE=page||1;
    const p=new URLSearchParams({page:PAGE,limit:25,tab:TAB,sort:document.getElementById('sortSelect').value});
    const search=document.getElementById('searchInput').value.trim();
    if(search)p.set('search',search);
    const status=document.getElementById('fStatus').value;if(status)p.set('status',status);
    const showDisposed=document.getElementById('fShowDisposed')?.checked;
    if(!showDisposed&&!status) p.set('exclude_status','disposed');
    const type=document.getElementById('fType')?.value;if(type)p.set('type',type);
    const building=document.getElementById('fBuilding')?.value;if(building)p.set('building_id',building);
    const source=document.getElementById('fSource')?.value;if(source)p.set('source',source);

    const area=document.getElementById('dataArea');
    area.innerHTML='<div style="text-align:center;padding:40px;color:var(--c3)"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    try{
        const d=await apiFetch('/v1/api/containers.php?'+p);
        if(!d.success)throw new Error(d.error);
        DATA=d.data.data||[];
        DATA.forEach(r=>{r.id=+r.id;});   // normalize: PDO EMULATE_PREPARES returns strings
        renderView();
        renderPager(d.data.pagination);
    }catch(e){area.innerHTML='<div class="stk-empty"><i class="fas fa-exclamation-circle"></i><p>'+esc(e.message)+'</p></div>'}
}

/* ═════════════════════════════════════════
   RENDER DISPATCHER
   ═════════════════════════════════════════ */
function renderView(){
    const area=document.getElementById('dataArea');
    if(!DATA||!DATA.length){
        area.innerHTML=`<div class="stk-empty"><i class="fas fa-flask"></i><p>${T('ไม่พบข้อมูลขวดสารเคมี','No chemical bottles found')}</p>${CAN_EDIT?`<a href="/v1/pages/containers.php?action=add" class="stk-btn stk-btn-p" style="margin-top:12px"><i class="fas fa-plus"></i> ${T('เพิ่มขวดสาร','Add Bottle')}</a>`:''}</div>`;
        document.getElementById('pagerArea').innerHTML='';return;
    }
    switch(VIEW){
        case 'table':renderTable(area);break;
        case 'grid':renderGrid(area);break;
        case 'compact':renderCompact(area);break;
        case 'analytics':renderAnalytics(area);break;
        default:renderTable(area);
    }
}

/* ═════════════════════════════════════════
   TABLE VIEW
   ═════════════════════════════════════════ */
function renderTable(area){
    let h=`<div class="stk-tw"><table class="stk-t"><thead><tr>
        <th style="width:32px;padding:10px 8px"><input type="checkbox" id="chkAll" onclick="toggleSelectAll(event)" style="cursor:pointer;accent-color:var(--accent)"></th>
        <th style="width:32px;color:var(--c3)">#</th>
        <th>${T('สารเคมี','Chemical')}</th>
        <th class="hide-tablet">${T('รหัส','Code')}</th>
        <th>${T('คงเหลือ','Stock')}</th>
        <th class="hide-tablet">${T('อันตราย','Hazard')}</th>
        <th>${T('สถานะ','Status')}</th>
        <th class="hide-tablet">${T('เจ้าของ','Owner')}</th>
        <th class="hide-tablet">${T('ตำแหน่ง','Location')}</th>
        <th style="text-align:center;width:80px"></th>
    </tr></thead><tbody>`;

    DATA.forEach((r,i)=>{
        const p=parseFloat(r.remaining_percentage)||0;
        const mine=r.is_mine;
        const idx=(PAGE-1)*25+i+1;
        const haz=(r.hazard_pictograms||[]);
        const isExp=r.expiry_date&&new Date(r.expiry_date)<new Date();
        const isLow=p>0&&p<=20;
        const hazTiny=haz.length
            ?haz.slice(0,4).map(hp=>`<span title="${hp}" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:5px;background:${ghsTinyColors[hp]||'#dc2626'}18;border:1px solid ${ghsTinyColors[hp]||'#dc2626'}30"><i class="fas ${ghsTinyIcons[hp]||'fa-exclamation'}" style="font-size:9px;color:${ghsTinyColors[hp]||'#dc2626'}"></i></span>`).join('')
              +(haz.length>4?`<span style="font-size:9px;color:var(--c3);margin-left:2px">+${haz.length-4}</span>`:'')
            :`<span style="color:#dde1e7;font-size:10px">—</span>`;

        const hasPendingTx=!!r.pending_transfer_id;
        const hasPendingBorrow=!!r.pending_borrow_by_me;
        const rowStyle=hasPendingBorrow?'background:#eff6ff':hasPendingTx?'background:#fffbeb':isExp?'background:#fff5f5':isLow&&mine?'background:#fffbeb':'';
        const warningDot=isExp?`<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#dc2626;margin-right:4px;flex-shrink:0" title="${T('หมดอายุ','Expired')}"></span>`:
                         isLow?`<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#f59e0b;margin-right:4px;flex-shrink:0" title="${T('ปริมาณน้อย','Low stock')}"></span>`:'';

        h+=`<tr class="${mine?'me':''}${hasPendingTx?' pending-transfer':''}${hasPendingBorrow?' pending-borrow':''}" style="${rowStyle}" onclick="openDetail(${r.id})">
            <td style="padding:9px 8px" onclick="event.stopPropagation()">
                <input type="checkbox" class="stk-chk" data-id="${r.id}" ${SELECTED.has(+r.id)?'checked':''} onchange="toggleSelect(${r.id},event)" style="cursor:pointer;accent-color:var(--accent)">
            </td>
            <td style="color:var(--c3);font-size:11px;padding:9px 8px">${idx}</td>
            <td>
                <div style="display:flex;align-items:center;gap:8px;min-width:0">
                    ${warningDot}
                    <div class="type-icon type-${r.container_type||'other'}" style="flex-shrink:0"><i class="fas ${typeIcons[r.container_type]||'fa-box'}"></i></div>
                    <div style="min-width:0">
                        <div style="font-weight:700;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px">${esc(r.chemical_name||'-')}</div>
                        <div style="font-size:9.5px;color:var(--c3);margin-top:1px;display:flex;align-items:center;gap:4px">
                            ${r.cas_number?`<span>${esc(r.cas_number)}</span>`:''}
                            ${srcBadge(r.source)}
                            ${r.has_3d?`<span style="background:linear-gradient(135deg,#6C5CE7,#a855f7);color:#fff;font-size:8px;padding:1px 5px;border-radius:4px;font-weight:700">3D</span>`:''}
                        </div>
                        <div class="stk-owner-inline">
                            ${mine?`<i class="fas fa-star" style="color:#d97706;font-size:8px"></i>`:''}
                            <span><i class="fas fa-user" style="font-size:8px;opacity:.6"></i> ${esc(r.owner_name||'-')}</span>
                            ${r.location_text&&r.location_text!=='-'?`<span style="opacity:.4">·</span><span><i class="fas fa-map-marker-alt" style="font-size:8px;opacity:.6"></i> ${esc(r.location_text)}</span>`:''}
                            ${hasPendingTx?`<span class="stk-pt-badge"><i class="fas fa-clock"></i> รอรับโอน</span>`:''}
                            ${hasPendingBorrow?`<span class="stk-pb-badge"><i class="fas fa-hand-holding"></i> รอการอนุมัติยืม</span>`:''}
                        </div>
                    </div>
                </div>
            </td>
            <td class="hide-tablet">
                <code style="font-size:10px;background:#f1f5f9;padding:2px 7px;border-radius:4px;font-family:'Courier New',monospace;letter-spacing:0.3px;white-space:nowrap">${esc(r.bottle_code||'—')}</code>
            </td>
            <td>
                <div style="display:flex;align-items:center;gap:7px">
                    <div style="width:44px;height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden;flex-shrink:0">
                        <div class="${barCls(p)}" style="height:100%;width:${Math.min(p,100)}%;border-radius:3px"></div>
                    </div>
                    <span style="font-weight:800;font-size:11.5px;color:${pctColor(p)};flex-shrink:0">${p.toFixed(0)}%</span>
                </div>
                <div style="font-size:9.5px;color:var(--c3);margin-top:2px;white-space:nowrap">${r.current_quantity||0}/${r.initial_quantity||0} ${esc(r.quantity_unit||'')}</div>
            </td>
            <td class="hide-tablet" style="white-space:nowrap">${hazTiny}</td>
            <td>${badgeHtml(r.status||'active')}</td>
            <td class="hide-tablet" style="font-size:11px">
                <div style="display:flex;align-items:center;gap:5px">
                    ${mine?'<i class="fas fa-star" style="color:#d97706;font-size:9px"></i>':''}
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px">${esc(r.owner_name||'-')}</span>
                </div>
            </td>
            <td class="hide-tablet" style="font-size:11px;color:var(--c2);max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.location_text||'-')}</td>
            <td onclick="event.stopPropagation()" style="padding:8px">
                <div style="display:flex;gap:3px;justify-content:center">
                    <button class="stk-btn stk-btn-g" onclick="showQRModal(${r.id})" title="QR Code" style="padding:5px 7px;font-size:11px"><i class="fas fa-qrcode"></i></button>
                    <button class="stk-btn stk-btn-g" onclick="doPrintSingleLabel(${r.id})" title="${T('พิมพ์ฉลาก','Print Label')}" style="padding:5px 7px;font-size:11px"><i class="fas fa-tag"></i></button>
                    ${r.has_3d?`<a href="/v1/ar/view_ar.php?id=${r.id}" target="_blank" class="stk-btn" style="padding:5px 7px;font-size:11px;background:linear-gradient(135deg,#0d9488,#14b8a6);color:#fff;text-decoration:none" title="AR"><i class="fas fa-vr-cardboard"></i></a>`:''}
                </div>
            </td>
        </tr>`;
    });
    h+='</tbody></table></div>';
    area.innerHTML=h;
}

/* ═════════════════════════════════════════
   GRID VIEW
   ═════════════════════════════════════════ */
function renderGrid(area){
    let h='<div class="stk-grid">';
    DATA.forEach(r=>{
        const p=parseFloat(r.remaining_percentage)||0;
        const mine=r.is_mine;
        const isExp=r.expiry_date&&new Date(r.expiry_date)<new Date();
        const haz=(r.hazard_pictograms||[]);
        const hazMini=haz.length?`<div style="display:flex;gap:3px;flex-wrap:wrap;margin-top:6px">${haz.slice(0,5).map(hp=>`<span style="width:20px;height:20px;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;font-size:9px;background:#fef2f2;color:${ghsTinyColors[hp]||'#dc2626'};border:1px solid ${ghsTinyColors[hp]||'#fecaca'}30" title="${hp}"><i class="fas ${ghsTinyIcons[hp]||'fa-exclamation'}"></i></span>`).join('')}${haz.length>5?`<span style="font-size:9px;color:var(--c3)">+${haz.length-5}</span>`:''}</div>`:'';
        const pColor=pctColor(p);
        const pborrow=!!r.pending_borrow_by_me;
        h+=`<div class="stk-card${mine?' me':''}${SELECTED.has(+r.id)?' stk-selected':''}${!!r.pending_transfer_id?' pending-transfer':''}${pborrow?' pending-borrow':''}" onclick="openDetail(${r.id})" style="padding-bottom:${CAN_EDIT?'0':'0'}">
            ${pborrow?`<div style="position:absolute;top:8px;right:8px;z-index:3"><span class="stk-pb-badge"><i class="fas fa-hand-holding"></i> รอการอนุมัติยืม</span></div>`:''}
            ${r.has_3d?`<div class="stk-3d-badge"><i class="fas fa-cube"></i> 3D</div>`:''}
            <div onclick="event.stopPropagation()" style="position:absolute;top:9px;left:9px;z-index:3">
                <input type="checkbox" class="stk-chk" data-id="${r.id}" ${SELECTED.has(+r.id)?'checked':''} onchange="toggleSelect(${r.id},event)" style="cursor:pointer;width:15px;height:15px;accent-color:var(--accent)">
            </div>
            <div class="stk-card-hd">
                <div class="stk-card-ic type-${r.container_type||'other'}"><i class="fas ${typeIcons[r.container_type]||'fa-box'}"></i></div>
                <div style="min-width:0;flex:1">
                    <div class="stk-card-nm">${esc(r.chemical_name||'-')}</div>
                    <div class="stk-card-cd">${esc(r.bottle_code||'')} ${srcBadge(r.source)}</div>
                </div>
                ${badgeHtml(r.status||'active')}
            </div>
            <div class="stk-card-bd">
                <div class="stk-card-tg">
                    <span class="stk-card-tag" style="background:#f0fdf4;color:#059669">${typeLabels[r.container_type]||r.container_type||'-'}</span>
                    ${r.grade?`<span class="stk-card-tag" style="background:#ede9fe;color:#6d28d9">${esc(r.grade)}</span>`:''}
                    ${r.manufacturer_name?`<span class="stk-card-tag" style="background:#fef3c7;color:#d97706;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.manufacturer_name)}</span>`:''}
                </div>
                <div class="stk-card-ft" style="margin-bottom:5px">
                    <span style="font-size:11px">${r.current_quantity||0} / ${r.initial_quantity||0} ${esc(r.quantity_unit||'')}</span>
                    <span style="font-weight:800;color:${pColor};font-size:12px">${p.toFixed(0)}%</span>
                </div>
                <div class="stk-card-bar" style="height:6px;margin-bottom:8px">
                    <div class="stk-card-bf ${barCls(p)}" style="width:${Math.min(p,100)}%"></div>
                </div>
                ${r.location_text&&r.location_text!=='-'?`<div class="stk-card-row"><i class="fas fa-map-marker-alt"></i> ${esc(r.location_text)}</div>`:''}
                <div class="stk-card-row"><i class="fas fa-user"></i> ${r.is_mine?'<i class="fas fa-star" style="color:#d97706;font-size:9px"></i> ':''} ${esc(r.owner_name||'-')}</div>
                ${r.expiry_date?`<div class="stk-card-row" style="${isExp?'color:#dc2626;font-weight:600':''}"><i class="fas fa-calendar-alt"></i> ${fmtDate(r.expiry_date)}${isExp?' <span style="font-size:9px">⚠️ หมดอายุ</span>':''}</div>`:''}
                ${hazMini}
            </div>
            <div class="stk-card-actions" onclick="event.stopPropagation()">
                <button class="stk-card-action-btn" onclick="showQRModal(${r.id})" title="QR"><i class="fas fa-qrcode"></i> QR</button>
                <button class="stk-card-action-btn" onclick="doPrintSingleLabel(${r.id})" title="${T('ฉลาก','Label')}"><i class="fas fa-tag"></i> ${T('ฉลาก','Label')}</button>
                ${(CAN_EDIT||(CAN_ACT&&r.is_mine))?`<button class="stk-card-action-btn" onclick="openTxnModal('use');_txnItem=DATA.find(x=>x.id==${r.id})||_txnItem" style="color:#16a34a;border-color:#bbf7d0"><i class="fas fa-flask"></i> ${T('เบิก','Use')}</button>`:''}
                ${r.has_3d?`<a href="/v1/ar/view_ar.php?id=${r.id}" target="_blank" class="stk-card-action-btn" style="color:#0d9488;border-color:#99f6e4;text-decoration:none"><i class="fas fa-vr-cardboard"></i> AR</a>`:''}
            </div>
        </div>`;
    });
    h+='</div>';
    area.innerHTML=h;
}

/* ═════════════════════════════════════════
   COMPACT VIEW
   ═════════════════════════════════════════ */
function renderCompact(area){
    let h='<div class="stk-compact">';
    DATA.forEach(r=>{
        const p=parseFloat(r.remaining_percentage)||0;
        const mine=r.is_mine;
        const pborrow=!!r.pending_borrow_by_me;
        h+=`<div class="stk-cr${mine?' me':''}${SELECTED.has(+r.id)?' stk-selected':''}${!!r.pending_transfer_id?' pending-transfer':''}${pborrow?' pending-borrow':''}" onclick="openDetail(${r.id})">
            <div onclick="event.stopPropagation()" style="display:flex;align-items:center"><input type="checkbox" class="stk-chk" data-id="${r.id}" ${SELECTED.has(+r.id)?'checked':''} onchange="toggleSelect(${r.id},event)" style="cursor:pointer;width:14px;height:14px;margin-right:6px"></div>
            <div class="type-icon type-${r.container_type||'other'}" style="width:24px;height:24px;border-radius:6px;font-size:10px"><i class="fas ${typeIcons[r.container_type]||'fa-box'}"></i></div>
            <div class="stk-cn" title="${esc(r.chemical_name)}">${esc(r.chemical_name||'-')}</div>
            <span class="stk-cc">${esc(r.bottle_code||'')} ${srcBadge(r.source)}</span>
            ${badgeHtml(r.status||'active')}
            <div class="stk-cb"><div class="${barCls(p)}" style="width:${Math.min(p,100)}%"></div></div>
            <span class="stk-cp" style="color:${pctColor(p)}">${p.toFixed(0)}%</span>
            <span class="stk-co">${mine?'<i class="fas fa-star" style="color:#d97706;font-size:8px;margin-right:2px"></i>':''}${esc(r.owner_name||'-')}</span>
            ${r.location_text&&r.location_text!=='-'?`<span class="stk-cl"><i class="fas fa-map-marker-alt" style="font-size:8px;opacity:.6"></i> ${esc(r.location_text)}</span>`:'<span class="stk-cl" style="color:#dde1e7">—</span>'}
            ${pborrow?`<span class="stk-pb-badge"><i class="fas fa-hand-holding"></i> รอการอนุมัติยืม</span>`:''}
            <div onclick="event.stopPropagation()" style="display:flex;gap:2px;margin-left:4px">
                <button class="stk-btn stk-btn-s stk-btn-g" onclick="doPrintSingleLabel(${r.id})" title="${T('ฉลาก','Label')}" style="padding:2px 5px;font-size:9px"><i class="fas fa-tag"></i></button>
                ${r.has_3d?`<a href="/v1/ar/view_ar.php?id=${r.id}" target="_blank" style="padding:2px 5px;font-size:9px;color:#0d9488;text-decoration:none" title="AR"><i class="fas fa-vr-cardboard"></i></a>`:''}
            </div>
        </div>`;
    });
    h+='</div>';
    area.innerHTML=h;
}

/* ═════════════════════════════════════════
   ANALYTICS VIEW
   ═════════════════════════════════════════ */
function renderAnalytics(area){
    if(!STATS){area.innerHTML='<div class="stk-empty"><p>Loading…</p></div>';return}
    const s=STATS;
    const total=s.total||1;
    const colors=['#22c55e','#3b82f6','#8b5cf6','#f59e0b','#ec4899','#0891b2','#ea580c','#64748b'];

    // Donut for statuses
    const sd=(s.statuses||[]);
    let svg='',off=0;
    const sc2={active:'#22c55e',empty:'#ef4444',expired:'#ec4899',quarantined:'#f59e0b',disposed:'#94a3b8'};
    sd.forEach(d=>{const pc=(d.cnt/total)*100;const ds=2*Math.PI*40;svg+=`<circle cx="50" cy="50" r="40" fill="none" stroke="${sc2[d.status]||'#999'}" stroke-width="14" stroke-dasharray="${ds*pc/100} ${ds*(1-pc/100)}" stroke-dashoffset="${-ds*off/100}" transform="rotate(-90 50 50)"/>`;off+=pc});

    const maxType=Math.max(...(s.types||[]).map(t=>t.cnt),1);
    const maxChem=Math.max(...(s.top_chemicals||[]).map(t=>t.cnt),1);
    const maxOwn=Math.max(...(s.top_owners||[]).map(t=>t.cnt),1);

    let h=`<div class="stk-an">
        <!-- Status Donut -->
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-chart-pie"></i> ${T('สถานะขวดสาร','Bottle Status')}</div>
            <div class="stk-dn">
                <svg width="120" height="120" viewBox="0 0 100 100">${svg}<text x="50" y="48" text-anchor="middle" font-size="18" font-weight="800" fill="var(--c1)">${num(s.total)}</text><text x="50" y="60" text-anchor="middle" font-size="7" fill="var(--c3)">${T('ขวดทั้งหมด','TOTAL')}</text></svg>
                <div class="stk-dl2">${sd.map(d=>`<div class="stk-di"><div class="stk-dd" style="background:${sc2[d.status]||'#999'}"></div><span style="text-transform:capitalize">${d.status}: <b>${num(d.cnt)}</b> (${Math.round(d.cnt/total*100)}%)</span></div>`).join('')}</div>
            </div>
        </div>

        <!-- Container Types -->
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-box-open"></i> ${T('ประเภทบรรจุภัณฑ์','Container Types')}</div>
            <div class="stk-bc">${(s.types||[]).map((t,i)=>`<div class="stk-br">
                <span class="stk-bl">${typeLabels[t.container_type]||t.container_type||'N/A'}</span>
                <div class="stk-bt"><div class="stk-bf" style="width:${(t.cnt/maxType*100).toFixed(1)}%;background:${colors[i%8]}">${t.cnt}</div></div>
                <span class="stk-bv">${(t.cnt/total*100).toFixed(0)}%</span>
            </div>`).join('')}</div>
        </div>

        <!-- Top Chemicals -->
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-flask"></i> ${T('สารเคมีที่มีขวดมากสุด','Top Chemicals')}</div>
            <div class="stk-bc">${(s.top_chemicals||[]).map((c,i)=>`<div class="stk-br">
                <span class="stk-bl" title="${esc(c.chemical_name)}">${esc(c.chemical_name.length>14?c.chemical_name.substring(0,12)+'…':c.chemical_name)}</span>
                <div class="stk-bt"><div class="stk-bf" style="width:${(c.cnt/maxChem*100).toFixed(1)}%;background:${colors[i%8]}">${c.cnt}</div></div>
                <span class="stk-bv">${num(c.cnt)}</span>
            </div>`).join('')}${!(s.top_chemicals||[]).length?`<p style="text-align:center;color:var(--c3);font-size:12px">${T('ไม่มีข้อมูล','No data')}</p>`:''}</div>
        </div>

        <!-- Top Owners -->
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-users"></i> ${T('เจ้าของขวดมากสุด','Top Owners')}</div>
            <div class="stk-bc">${(s.top_owners||[]).map((t,i)=>`<div class="stk-br">
                <span class="stk-bl" title="${esc(t.owner_name)}">${esc(t.owner_name)}</span>
                <div class="stk-bt"><div class="stk-bf" style="width:${(t.cnt/maxOwn*100).toFixed(1)}%;background:${colors[i%8]}">${t.cnt}</div></div>
                <span class="stk-bv">${num(t.cnt)}</span>
            </div>`).join('')}</div>
        </div>

        <!-- Summary -->
        <div class="stk-ac"><div class="stk-at"><i class="fas fa-info-circle"></i> ${T('สรุปภาพรวม','Summary')}</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div style="text-align:center;padding:14px;background:#f0fdf4;border-radius:10px"><div style="font-size:24px;font-weight:800;color:#16a34a">${num(s.total)}</div><div style="font-size:10px;color:var(--c3);text-transform:uppercase">${T('ขวดทั้งหมด','Total')}</div></div>
                <div style="text-align:center;padding:14px;background:#eff6ff;border-radius:10px"><div style="font-size:24px;font-weight:800;color:#2563eb">${num(s.chemicals)}</div><div style="font-size:10px;color:var(--c3);text-transform:uppercase">${T('ชนิดสาร','Chemicals')}</div></div>
                <div style="text-align:center;padding:14px;background:#fef9c3;border-radius:10px"><div style="font-size:24px;font-weight:800;color:#a16207">${num(s.low)}</div><div style="font-size:10px;color:var(--c3);text-transform:uppercase">${T('เหลือน้อย','Low Stock')}</div></div>
                <div style="text-align:center;padding:14px;background:#ede9fe;border-radius:10px"><div style="font-size:24px;font-weight:800;color:#7c3aed">${num(s.models_3d||0)}</div><div style="font-size:10px;color:var(--c3);text-transform:uppercase">3D Models</div></div>
            </div>
        </div>
    </div>`;
    area.innerHTML=h;
    document.getElementById('pagerArea').innerHTML='';
}

/* ═════════════════════════════════════════
   PAGINATION
   ═════════════════════════════════════════ */
function renderPager(pg){
    if(!pg||pg.pages<=1){document.getElementById('pagerArea').innerHTML='';return}
    let h='';
    h+=`<button ${pg.page<=1?'disabled':''} onclick="loadData(${pg.page-1})"><i class="fas fa-chevron-left"></i></button>`;
    const mx=5;let st=Math.max(1,pg.page-Math.floor(mx/2)),en=Math.min(pg.pages,st+mx-1);
    if(en-st<mx-1)st=Math.max(1,en-mx+1);
    if(st>1){h+=`<button onclick="loadData(1)">1</button>`;if(st>2)h+=`<span class="stk-pager-info">…</span>`}
    for(let i=st;i<=en;i++)h+=`<button class="${i===pg.page?'active':''}" onclick="loadData(${i})">${i}</button>`;
    if(en<pg.pages){if(en<pg.pages-1)h+=`<span class="stk-pager-info">…</span>`;h+=`<button onclick="loadData(${pg.pages})">${pg.pages}</button>`}
    h+=`<button ${pg.page>=pg.pages?'disabled':''} onclick="loadData(${pg.page+1})"><i class="fas fa-chevron-right"></i></button>`;
    h+=`<span class="stk-pager-info">${T('หน้า '+pg.page+'/'+pg.pages+' • '+num(pg.total)+' รายการ','Page '+pg.page+'/'+pg.pages+' • '+num(pg.total)+' items')}</span>`;
    document.getElementById('pagerArea').innerHTML=h;
}

/* ═════════════════════════════════════════
   DETAIL MODAL
   ═════════════════════════════════════════ */
async function openDetail(id){
    const ov=document.getElementById('detailOv');
    const md=document.getElementById('detailModal');
    md.innerHTML='<div style="padding:60px;text-align:center;color:var(--c3)"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i></div>';
    ov.classList.add('show');
    try{
        const d=await apiFetch('/v1/api/containers.php?action=detail&id='+id);
        if(!d.success)throw new Error(d.error);
        const c=d.data;
        _txnItem=c; // store for transaction modal
        const pct=parseFloat(c.remaining_percentage)||0;
        const ar=c.ar_data||{};
        const isExp=c.expiry_date&&new Date(c.expiry_date)<new Date();
        const hazards=(c.hazard_pictograms||[]);
        const history=c.history||[];
        const isMine=c.is_mine||IS_ADMIN;
        const canOwnerOp=isMine||IS_LAB;// use/transfer needs ownership or manager
        const hasPT=!!c.pending_transfer_id;
        const isPTInitiator=hasPT&&c.pending_transfer_by===UID;

        // GHS diamonds
        const ghsHtml=hazards.length?`<div class="ghs-row">${hazards.map(hp=>
            `<div class="ghs-diamond ghs-${hp}" title="${ghsLabelsMap[hp]||hp}">
                <div class="ghs-diamond-inner"><i class="fas ${ghsTinyIcons[hp]||'fa-exclamation'}"></i></div>
                <div class="ghs-tooltip">${ghsLabelsMap[hp]||hp}</div>
            </div>`
        ).join('')}</div>`:'';

        // Signal word
        const signalHtml=c.signal_word?
            (c.signal_word==='Danger'
                ?`<span class="signal-danger"><i class="fas fa-radiation"></i> ${T('อันตราย','DANGER')}</span>`
                :`<span class="signal-warning"><i class="fas fa-exclamation-triangle"></i> ${T('ระวัง','WARNING')}</span>`)
            :'';

        // GHS classifications
        const ghsClassHtml=(c.ghs_classifications||[]).length?
            `<div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:6px">${c.ghs_classifications.map(g=>
                `<span style="font-size:9px;padding:2px 7px;border-radius:5px;background:#fef2f2;color:#991b1b;font-weight:600;border:1px solid #fecaca">${esc(g)}</span>`
            ).join('')}</div>`:'';

        // 3D Viewer
        let viewer3d='';
        if(ar.has_model){
            const arBtn=`<a href="/v1/ar/view_ar.php?id=${c.id}" target="_blank" class="ar-btn" onclick="event.stopPropagation()"><i class="fas fa-vr-cardboard"></i> ${T('ดู AR','View AR')}</a>`;
            if(ar.model_type==='embed'){
                viewer3d=`<div class="stk-3d-viewer">
                    <div class="stk-3d-label"><span><i class="fas fa-cube"></i> 3D Preview</span>${signalHtml?'<span>'+signalHtml+'</span>':''}</div>
                    <iframe src="${ar.model_url}" allow="autoplay; fullscreen" allowfullscreen></iframe>
                    <div class="stk-3d-actions"><button onclick="window.open('${ar.model_url}','_blank')"><i class="fas fa-expand"></i> ${T('เต็มจอ','Fullscreen')}</button>${arBtn}</div>
                </div>`;
            }else{
                viewer3d=`<div class="stk-3d-viewer">
                    <div class="stk-3d-label"><span><i class="fas fa-cube"></i> 3D Preview</span>${signalHtml?'<span>'+signalHtml+'</span>':''}</div>
                    <iframe src="/v1/pages/viewer3d.php?src=${encodeURIComponent(ar.model_url)}&embed=1&transparent=0&title=${encodeURIComponent(c.chemical_name||'')}" style="width:100%;height:100%;border:none"></iframe>
                    <div class="stk-3d-actions"><button onclick="window.open('/v1/pages/viewer3d.php?src=${encodeURIComponent(ar.model_url)}&title=${encodeURIComponent(c.chemical_name||'')}','_blank')"><i class="fas fa-expand"></i> ${T('เต็มจอ','Fullscreen')}</button>${arBtn}</div>
                </div>`;
            }
        }else{
            viewer3d=`<div class="stk-3d-viewer">
                <div class="stk-3d-label"><span><i class="fas fa-cube"></i> 3D Preview</span></div>
                <div class="no-model">
                    <i class="fas fa-cube"></i>
                    <p>${T('ยังไม่มีโมเดล 3D สำหรับบรรจุภัณฑ์นี้','No 3D model available for this container type')}</p>
                    <a href="/v1/ar/view_ar.php?id=${c.id}" target="_blank" style="margin-top:4px;font-size:11px;color:#0d9488;text-decoration:none;display:flex;align-items:center;gap:4px"><i class="fas fa-vr-cardboard"></i> ${T('ลองดูใน AR','Try AR View')}</a>
                </div>
            </div>`;
        }


        // Fluid level fill color
        const fluidFill = pct>50?'linear-gradient(to top,#0d9488,#14b8a6)':pct>20?'linear-gradient(to top,#eab308,#fbbf24)':'linear-gradient(to top,#ef4444,#f87171)';
        const pctTextColor = pct>50?'#0d9488':pct>20?'#d97706':'#dc2626';

        // Type icon background colors
        const typeIconBg={bottle:'#dbeafe',vial:'#ede9fe',flask:'#d1fae5',canister:'#fed7aa',cylinder:'#fecdd3',ampoule:'#e0e7ff',bag:'#f5f5f4',other:'#f1f5f9'};
        const typeIconFg={bottle:'#2563eb',vial:'#7c3aed',flask:'#059669',canister:'#ea580c',cylinder:'#e11d48',ampoule:'#4338ca',bag:'#78716c',other:'#64748b'};
        const ctBg=typeIconBg[c.container_type]||'#f1f5f9';
        const ctFg=typeIconFg[c.container_type]||'#64748b';

        // Timeline dot colors
        const tlDot={created:'#22c55e',used:'#eab308',moved:'#3b82f6',disposed:'#ef4444',borrowed:'#8b5cf6',returned:'#0d9488'};

        md.innerHTML=`
        <div class="dm-handle"></div>
        <div class="dm-hdr">
            <div class="dm-hdr-ic" style="background:${ctBg};color:${ctFg}">
                <i class="fas ${typeIcons[c.container_type]||'fa-box'}"></i>
            </div>
            <div class="dm-hdr-info">
                <div class="dm-hdr-name">${esc(c.chemical_name||'—')}</div>
                <div class="dm-hdr-sub">
                    ${c.bottle_code?`<code style="font-family:'Courier New',monospace;font-size:10px;background:#f1f5f9;padding:1px 6px;border-radius:4px;color:var(--c2)">${esc(c.bottle_code)}</code>`:''}
                    ${badgeHtml(c.status||'active')}
                    ${srcBadge(c.source)}
                    ${isMine?`<span style="background:#fef9c3;color:#a16207;font-size:9px;font-weight:700;padding:1px 7px;border-radius:5px"><i class="fas fa-star" style="font-size:8px"></i> ${T('ของฉัน','Mine')}</span>`:''}
                </div>
            </div>
            <button class="dm-close" onclick="closeDetail()"><i class="fas fa-times"></i></button>
        </div>

        <div class="dm-layout" style="max-height:calc(92vh - 76px);overflow:hidden">

            <!-- ── MAIN (left / top) ── -->
            <div class="dm-main">

                <!-- Fluid level -->
                <div class="dm-section">
                    <div class="dm-section-title"><i class="fas fa-tint"></i> ${T('ปริมาณคงเหลือ','Remaining Volume')}</div>
                    <div class="dm-fluid-row">
                        <div>
                            <div class="dm-pct-big" style="color:${pctTextColor}">${pct.toFixed(0)}<span style="font-size:18px;font-weight:600">%</span></div>
                            <div class="dm-pct-label">${T('คงเหลือ','remaining')}</div>
                        </div>
                        <div style="flex:1">
                            <div class="dm-qty-bar">
                                <div class="dm-qty-fill ${barCls(pct)}" style="width:${Math.min(pct,100)}%;background:${fluidFill}"></div>
                            </div>
                            <div class="dm-qty-text">${c.current_quantity||0} / ${c.initial_quantity||0} <b>${esc(c.quantity_unit||'')}</b></div>
                        </div>
                    </div>
                    ${ghsHtml||signalHtml?`<div style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">${signalHtml}${ghsHtml}</div>`:''}
                    ${ghsClassHtml}
                </div>

                <!-- 3D Viewer -->
                ${ar.has_model?`<div class="dm-section" style="padding:0;border-bottom:1px solid #f1f5f9">${viewer3d}</div>`:''}

                <!-- Chemical info grid -->
                <div class="dm-section">
                    <div class="dm-section-title"><i class="fas fa-info-circle"></i> ${T('ข้อมูลสาร','Chemical Info')}</div>
                    <div class="dm-info-grid">
                        ${c.cas_number?`<div class="dm-info-item"><div class="dm-info-label">CAS Number</div><div class="dm-info-value"><code>${esc(c.cas_number)}</code></div></div>`:''}
                        ${c.molecular_formula?`<div class="dm-info-item"><div class="dm-info-label">${T('สูตรโมเลกุล','Formula')}</div><div class="dm-info-value"><code>${esc(c.molecular_formula)}</code></div></div>`:''}
                        ${c.molecular_weight?`<div class="dm-info-item"><div class="dm-info-label">MW (g/mol)</div><div class="dm-info-value">${parseFloat(c.molecular_weight).toFixed(2)}</div></div>`:''}
                        ${c.physical_state?`<div class="dm-info-item"><div class="dm-info-label">${T('สถานะ','State')}</div><div class="dm-info-value">${({solid:T('ของแข็ง','Solid'),liquid:T('ของเหลว','Liquid'),gas:T('ก๊าซ','Gas'),powder:T('ผง','Powder'),solution:T('สารละลาย','Solution')})[c.physical_state]||c.physical_state}</div></div>`:''}
                        ${c.grade?`<div class="dm-info-item"><div class="dm-info-label">Grade</div><div class="dm-info-value"><span style="background:#ede9fe;color:#6d28d9;padding:1px 8px;border-radius:5px;font-size:11px;font-weight:700">${esc(c.grade)}</span></div></div>`:''}
                        ${c.container_material?`<div class="dm-info-item"><div class="dm-info-label">${T('วัสดุ','Material')}</div><div class="dm-info-value">${esc(c.container_material)}</div></div>`:''}
                        ${c.manufacturer_name?`<div class="dm-info-item full"><div class="dm-info-label">${T('ผู้ผลิต','Manufacturer')}</div><div class="dm-info-value">${esc(c.manufacturer_name)}</div></div>`:''}
                    </div>
                </div>

                <!-- Location & ownership -->
                <div class="dm-section">
                    <div class="dm-section-title"><i class="fas fa-map-marker-alt"></i> ${T('ตำแหน่งและเจ้าของ','Location & Owner')}</div>
                    <div class="dm-info-grid">
                        <div class="dm-info-item"><div class="dm-info-label">${T('เจ้าของ','Owner')}</div><div class="dm-info-value"><i class="fas fa-user" style="color:var(--accent);font-size:10px;margin-right:4px"></i>${esc(c.owner_name||'-')}</div></div>
                        <div class="dm-info-item"><div class="dm-info-label">${T('ห้องแลป','Lab')}</div><div class="dm-info-value">${esc(c.lab_name||'-')}</div></div>
                        <div class="dm-info-item full"><div class="dm-info-label">${T('ตำแหน่ง','Location')}</div><div class="dm-info-value"><i class="fas fa-map-marker-alt" style="color:#dc2626;font-size:10px;margin-right:4px"></i>${esc(c.location_text||'-')}</div></div>
                        <div class="dm-info-item"><div class="dm-info-label">${T('รหัสขวด','Bottle Code')}</div><div class="dm-info-value"><code>${esc(c.bottle_code||'-')}</code></div></div>
                        <div class="dm-info-item"><div class="dm-info-label">QR Code</div><div class="dm-info-value" style="font-size:11px;font-family:monospace">${esc(c.qr_code||'-')}</div></div>
                    </div>
                </div>

                <!-- Dates & batch -->
                ${(c.expiry_date||c.received_date||c.batch_number||c.lot_number||c.invoice_number||c.cost)?`
                <div class="dm-section">
                    <div class="dm-section-title"><i class="fas fa-calendar-alt"></i> ${T('วันที่และข้อมูลการจัดซื้อ','Dates & Procurement')}</div>
                    <div class="dm-info-grid">
                        ${c.expiry_date?`<div class="dm-info-item"><div class="dm-info-label">${T('วันหมดอายุ','Expiry')}</div><div class="dm-info-value" style="${isExp?'color:#dc2626;font-weight:700':''}">${fmtDate(c.expiry_date)}${isExp?` <span style="font-size:10px">⚠️</span>`:''}</div></div>`:''}
                        ${c.received_date?`<div class="dm-info-item"><div class="dm-info-label">${T('วันที่รับ','Received')}</div><div class="dm-info-value">${fmtDate(c.received_date)}</div></div>`:''}
                        ${c.batch_number?`<div class="dm-info-item"><div class="dm-info-label">Batch No.</div><div class="dm-info-value"><code>${esc(c.batch_number)}</code></div></div>`:''}
                        ${c.lot_number?`<div class="dm-info-item"><div class="dm-info-label">Lot No.</div><div class="dm-info-value"><code>${esc(c.lot_number)}</code></div></div>`:''}
                        ${c.invoice_number?`<div class="dm-info-item full"><div class="dm-info-label">${T('เลขที่ใบแจ้งหนี้','Invoice No.')}</div><div class="dm-info-value">${esc(c.invoice_number)}</div></div>`:''}
                        ${c.cost?`<div class="dm-info-item"><div class="dm-info-label">${T('ราคา','Cost')}</div><div class="dm-info-value" style="color:#16a34a;font-weight:700">${parseFloat(c.cost).toLocaleString()} ฿</div></div>`:''}
                    </div>
                </div>`:''}

                ${c.notes?`<div class="dm-section"><div class="dm-section-title"><i class="fas fa-sticky-note"></i> ${T('หมายเหตุ','Notes')}</div><div style="font-size:12.5px;color:var(--c2);line-height:1.6">${esc(c.notes)}</div></div>`:''}

                <!-- History -->
                ${history.length?`
                <div class="dm-section">
                    <div class="dm-section-title"><i class="fas fa-history"></i> ${T('ประวัติการเคลื่อนไหว','Activity History')} <span style="font-size:9px;color:var(--c3);font-weight:400;margin-left:4px">(${history.length})</span></div>
                    <div>${history.slice(0,8).map(hi=>`
                        <div class="dm-tl-item">
                            <div class="dm-tl-dot" style="background:${tlDot[hi.action_type]||'#94a3b8'}"></div>
                            <div style="flex:1;min-width:0">
                                <div class="dm-tl-act">${hi.action_type||'-'}${hi.quantity_change?` <span style="font-weight:700;color:${parseFloat(hi.quantity_change)<0?'#dc2626':'#16a34a'};font-size:10px">${parseFloat(hi.quantity_change)>0?'+':''}${hi.quantity_change}</span>`:''}</div>
                                <div class="dm-tl-det">${esc(hi.notes||'')}${hi.user_name?` — ${esc(hi.user_name)}`:''}</div>
                                <div class="dm-tl-time"><i class="fas fa-clock" style="font-size:8px;margin-right:2px"></i>${fmtDate(hi.created_at)}</div>
                            </div>
                        </div>`).join('')}
                    </div>
                </div>`:''}

            </div><!-- /dm-main -->

            <!-- ── SIDEBAR (right / bottom) ── -->
            <div class="dm-sidebar">

                ${(CAN_VIEW_ONLY&&!canOwnerOp)?`
                <div style="margin:14px 16px 0;padding:10px 13px;background:#fefce8;border:1.5px solid #fde68a;border-radius:10px;display:flex;align-items:flex-start;gap:9px">
                    <i class="fas fa-eye" style="color:#d97706;margin-top:1px;font-size:13px;flex-shrink:0"></i>
                    <div style="font-size:11px;color:#92400e;line-height:1.5">
                        <strong>${T('ดูข้อมูลเท่านั้น','View only')}</strong><br>
                        ${T('สารนี้ไม่ใช่ของคุณ ต้องได้รับสิทธิ์เพิ่มเพื่อดำเนินการ','This item is not yours. A higher role is required to act on it.')}
                    </div>
                </div>`:''}

                ${hasPT?`
                <div style="margin:14px 16px 0;padding:10px 13px;background:#fef3c7;border:1.5px solid #fde68a;border-radius:10px;display:flex;align-items:flex-start;gap:9px">
                    <i class="fas fa-clock" style="color:#d97706;margin-top:1px;font-size:13px;flex-shrink:0"></i>
                    <div style="font-size:11px;color:#92400e;line-height:1.5;flex:1">
                        <strong>รอการรับโอน</strong><br>
                        ขวดสารนี้กำลังอยู่ในระหว่างการโอนกรรมสิทธิ์ &mdash; ไม่สามารถเบิกใช้หรือโอนซ้ำได้
                    </div>
                    ${isPTInitiator?`<button onclick="cancelPendingTransfer(${c.pending_transfer_id})" style="flex-shrink:0;padding:5px 10px;background:#dc2626;color:#fff;border:none;border-radius:6px;font-size:10px;font-weight:700;cursor:pointer;font-family:inherit">ยกเลิกการโอน</button>`:''}
                </div>`:''}

                ${CAN_ACT?`
                <div class="dm-section-title" style="padding:16px 16px 0;font-size:9.5px"><i class="fas fa-exchange-alt"></i> ${T('รายการเคลื่อนไหว','Transactions')}</div>
                <div class="dm-txn-group">
                    ${canOwnerOp&&!hasPT?`
                    <button class="dm-txn-btn" style="background:linear-gradient(135deg,#15803d,#22c55e);color:#fff" onclick="openTxnModal('use')">
                        <div class="dm-txn-btn-ic"><i class="fas fa-flask"></i></div>
                        <div class="dm-txn-btn-info"><div class="dm-txn-btn-title">${T('เบิกใช้','Use / Consume')}</div><div class="dm-txn-btn-sub">${T('หักปริมาณออก ไม่ต้องคืน','Deduct quantity, no return')}</div></div>
                    </button>`:''}
                    ${!isMine?`
                    <button class="dm-txn-btn" style="background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff" onclick="openTxnModal('borrow')">
                        <div class="dm-txn-btn-ic"><i class="fas fa-hand-holding"></i></div>
                        <div class="dm-txn-btn-info"><div class="dm-txn-btn-title">${T('ยืม','Borrow')}</div><div class="dm-txn-btn-sub">${T('ยืมพร้อมกำหนดคืน','Borrow with return date')}</div></div>
                    </button>`:''}
                    ${isMine&&!hasPT?`
                    <button class="dm-txn-btn" style="background:linear-gradient(135deg,#6d28d9,#8b5cf6);color:#fff" onclick="openTxnModal('transfer')">
                        <div class="dm-txn-btn-ic"><i class="fas fa-share-nodes"></i></div>
                        <div class="dm-txn-btn-info"><div class="dm-txn-btn-title">${T('โอนกรรมสิทธิ์','Transfer')}</div><div class="dm-txn-btn-sub">${T('โอนความเป็นเจ้าของ','Change ownership')}</div></div>
                    </button>`:''}
                    ${(IS_ADMIN||IS_LAB)?`
                    <button class="dm-txn-btn" style="background:linear-gradient(135deg,#991b1b,#dc2626);color:#fff" onclick="openDisposeFor(${c.id})">
                        <div class="dm-txn-btn-ic"><i class="fas fa-trash-alt"></i></div>
                        <div class="dm-txn-btn-info"><div class="dm-txn-btn-title">${T('จำหน่าย','Dispose')}</div><div class="dm-txn-btn-sub">${T('ตัดออกจากระบบ','Remove from system')}</div></div>
                    </button>`:''}
                </div>`:''}

                <div class="dm-section-title" style="padding:${CAN_ACT?'8':'16'}px 16px 0;font-size:9.5px"><i class="fas fa-tools"></i> ${T('เครื่องมือ','Tools')}</div>
                <div class="dm-quick-grid">
                    <button class="dm-quick-btn" onclick="showQRModal(${c.id})" title="QR Code">
                        <i class="fas fa-qrcode" style="color:var(--accent)"></i> QR Code
                    </button>
                    <button class="dm-quick-btn" onclick="doPrintSingleLabel(${c.id})" title="${T('พิมพ์ฉลาก','Print Label')}">
                        <i class="fas fa-tag" style="color:#7c3aed"></i> ${T('ฉลาก','Label')}
                    </button>
                    <a href="/v1/ar/view_ar.php?id=${c.id}" target="_blank" class="dm-quick-btn" title="AR View">
                        <i class="fas fa-vr-cardboard" style="color:#0d9488"></i> AR View
                    </a>
                    ${ar.has_model?`<button class="dm-quick-btn" onclick="window.open('/v1/pages/viewer3d.php?src=${encodeURIComponent(ar.model_url||'')}&title=${encodeURIComponent(c.chemical_name||'')}','_blank')" title="3D View">
                        <i class="fas fa-cube" style="color:#6C5CE7"></i> 3D View
                    </button>`:''}
                    ${c.sds_url?`<a href="${c.sds_url}" target="_blank" class="dm-quick-btn" title="SDS">
                        <i class="fas fa-file-pdf" style="color:#dc2626"></i> SDS
                    </a>`:''}
                    ${IS_ADMIN?`<button class="dm-quick-btn" onclick="openBarcodeEdit(${c.id},'${esc(c.bottle_code||'')}','${esc(c.qr_code||'')}')" title="${T('แก้ไข Barcode','Edit Barcode')}">
                        <i class="fas fa-barcode" style="color:#d97706"></i> Barcode
                    </button>`:''}
                </div>

                <div style="padding:0 16px 16px;margin-top:auto">
                    <button onclick="closeDetail()" style="width:100%;padding:10px;border:1.5px solid var(--border);border-radius:9px;background:#fff;color:var(--c3);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .15s" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--c3)'">
                        <i class="fas fa-times"></i> ${T('ปิด','Close')}
                    </button>
                </div>

            </div><!-- /dm-sidebar -->
        </div>`;
    }catch(e){
        md.innerHTML=`<div style="padding:40px;text-align:center;color:#dc2626"><i class="fas fa-exclamation-triangle" style="font-size:24px;margin-bottom:8px"></i><p>${e.message}</p><button class="stk-btn stk-btn-g" onclick="closeDetail()" style="margin-top:12px">Close</button></div>`;
    }
}
function closeDetail(){document.getElementById('detailOv').classList.remove('show')}
async function cancelPendingTransfer(txnId){
    if(!confirm('ยืนยันยกเลิกการโอนได้')) return;
    try{
        const d=await apiFetch('/v1/api/borrow.php?action=reject',{method:'POST',body:JSON.stringify({txn_id:txnId})});
        if(!d.success) throw new Error(d.error||'Error');
        toast('ยกเลิกการโอนแล้ว','ok');
        closeDetail();
        fetchData();
    }catch(e){ toast(e.message,'err'); }
}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeDetail();closeBarcodeEdit();closePrintSettings();closeLabelModal();closeQrModal();closeTxnModal();closeBatchTxn();}});

/* ═════════════════════════════════════════
   BARCODE EDIT (admin only)
   ═════════════════════════════════════════ */
let _bcEditId=null;
function openBarcodeEdit(id, bottleCode, qrCode){
    _bcEditId=id;
    document.getElementById('bcInputBottle').value=bottleCode||'';
    document.getElementById('bcInputQR').value=qrCode||'';
    document.getElementById('bcOldBottle').textContent=(bottleCode?T('ค่าเดิม: ','Current: ')+bottleCode:'');
    document.getElementById('bcOldQR').textContent=(qrCode?T('ค่าเดิม: ','Current: ')+qrCode:'');
    document.getElementById('bcSaveBtn').disabled=false;
    document.getElementById('bcSaveBtn').innerHTML='<i class="fas fa-save"></i> '+T('บันทึก','Save');
    document.getElementById('bcOv').classList.add('show');
    setTimeout(()=>document.getElementById('bcInputBottle').focus(),200);
}
function closeBarcodeEdit(){
    document.getElementById('bcOv').classList.remove('show');
    _bcEditId=null;
}
async function saveBarcodeEdit(){
    if(!_bcEditId)return;
    const bottle=document.getElementById('bcInputBottle').value.trim();
    const qr=document.getElementById('bcInputQR').value.trim();
    if(!bottle&&!qr){toast(T('กรุณากรอกอย่างน้อย 1 ช่อง','Please fill at least one field'),'err');return;}
    const btn=document.getElementById('bcSaveBtn');
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> '+T('กำลังบันทึก...','Saving...');
    try{
        const payload={};
        if(bottle) payload.bottle_code=bottle;
        if(qr) payload.qr_code=qr;
        const res=await fetch('/v1/api/containers.php?id='+_bcEditId,{
            method:'PUT',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(payload)
        });
        const d=await res.json();
        if(!d.success)throw new Error(d.error||'Update failed');
        toast(T('อัปเดต Barcode สำเร็จ','Barcode updated successfully'),'ok');
        closeBarcodeEdit();
        // Reload detail modal
        const detOv=document.getElementById('detailOv');
        if(detOv.classList.contains('show')) openDetail(_bcEditId);
        // Reload list
        loadData(PAGE);
    }catch(e){
        toast(e.message,'err');
        btn.disabled=false;
        btn.innerHTML='<i class="fas fa-save"></i> '+T('บันทึก','Save');
    }
}

/* ═════════════════════════════════════════
   TABS / VIEWS / FILTER
   ═════════════════════════════════════════ */
function switchTab(tab){
    TAB=tab;
    document.querySelectorAll('.stk-tab').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));
    document.getElementById('myBanner').style.display=tab==='my'?'flex':'none';
    if(tab==='my'&&STATS){
        document.getElementById('myStatTotal').textContent=num(STATS.my_total||0);
        document.getElementById('myStatActive').textContent=num(STATS.my_active||0);
    }
    // sync mobile search
    const ms=document.getElementById('searchInputMob');
    if(ms) ms.value='';
    document.getElementById('searchInput').value='';
    loadData(1);
}
function setView(v){
    VIEW=v;
    // sync both desktop and mobile view switchers
    document.querySelectorAll('#viewSw button,#viewSwMob button').forEach(b=>b.classList.toggle('active',b.dataset.view===v));
    const isMobile=window.innerWidth<=768;
    if(!isMobile) document.getElementById('toolbar').style.display=v==='analytics'?'none':'flex';
    document.getElementById('filterPanel').classList.remove('show');
    renderView();
    if(v==='analytics')document.getElementById('pagerArea').innerHTML='';
}
function toggleFilter(){
    const p=document.getElementById('filterPanel');
    p.classList.toggle('show');
    const isOpen=p.classList.contains('show');
    document.querySelectorAll('#filterToggle,.stk-filter-trigger').forEach(el=>el.classList.toggle('active',isOpen));
}
function clearFilters(){document.getElementById('fStatus').value='';document.getElementById('fType').value='';document.getElementById('fBuilding').value='';document.getElementById('fSource').value='';document.getElementById('sortSelect').value='newest';document.getElementById('searchInput').value='';const fd=document.getElementById('fShowDisposed');if(fd)fd.checked=false;loadData(1)}

/* ═════════════════════════════════════════
   BUILDING FILTER
   ═════════════════════════════════════════ */
async function loadBuildingFilter(){
    try{
        const d=await apiFetch('/v1/api/locations.php?type=buildings');
        if(d.success){
            const sel=document.getElementById('fBuilding');
            d.data.forEach(b=>{const o=document.createElement('option');o.value=b.id;o.textContent=b.shortname||b.name;sel.appendChild(o)});
        }
    }catch(e){}
}

/* ═════════════════════════════════════════
   EVENT LISTENERS
   ═════════════════════════════════════════ */
document.getElementById('sortSelect').addEventListener('change',()=>loadData(1));
document.getElementById('fStatus').addEventListener('change',()=>loadData(1));
document.getElementById('fType').addEventListener('change',()=>loadData(1));
document.getElementById('fBuilding').addEventListener('change',()=>loadData(1));
document.getElementById('fSource').addEventListener('change',()=>loadData(1));

/* ═════════════════════════════════════════
   HELPERS
   ═════════════════════════════════════════ */
function esc(s){if(!s)return '';const d=document.createElement('div');d.textContent=String(s);return d.innerHTML}
function num(n){return(n||0).toLocaleString()}
function barCls(p){return p>50?'bar-ok':p>15?'bar-mid':'bar-low'}
function pctColor(p){return p>50?'#16a34a':p>15?'#a16207':'#dc2626'}
function badgeHtml(s){
    const m={active:['stk-badge-active',T('ปกติ','Active')],empty:['stk-badge-empty',T('หมด','Empty')],expired:['stk-badge-expired',T('หมดอายุ','Expired')],quarantined:['stk-badge-quarantined',T('กักกัน','Quarantined')],disposed:['stk-badge-disposed',T('กำจัดแล้ว','Disposed')],low:['stk-badge-low',T('เหลือน้อย','Low')]};
    const [cls,lbl]=m[s]||m.active;
    return `<span class="stk-badge ${cls}">${lbl}</span>`;
}
function fmtDate(d){if(!d)return '—';try{return new Date(d).toLocaleDateString(L==='th'?'th-TH':'en-US',{day:'numeric',month:'short',year:'numeric'})}catch(e){return d}}
function srcBadge(s){return s==='stock'?'<span class="stk-src stk-src-stock">CSV</span>':'<span class="stk-src stk-src-container">SYS</span>'}
function toast(msg,type){const t=document.getElementById('stkToast');t.textContent=msg;t.className='stk-toast '+(type||'')+' show';setTimeout(()=>t.classList.remove('show'),3000)}

/* ═════════════════════════════════════════
   SELECTION / BATCH
   ═════════════════════════════════════════ */
function toggleSelect(id,e){
    e.stopPropagation();
    id=+id;
    if(SELECTED.has(id))SELECTED.delete(id);else SELECTED.add(id);
    updateSelectionUI();
}
function toggleSelectAll(e){
    e.stopPropagation();
    if(SELECTED.size===DATA.length){SELECTED.clear()}else{DATA.forEach(r=>SELECTED.add(+r.id))}
    updateSelectionUI();renderView();
}
function clearSelection(){SELECTED.clear();updateSelectionUI();renderView()}

/* ═════════════════════════════════════════
   BATCH TRANSACTION MODAL
   ═════════════════════════════════════════ */
let _btxType='use', _btxUser=null, _btxRunning=false;
const _btxWholeSet=new Set(); // item ids marked as "whole bottle"

const BTX_CFG={
    use:     {icon:'fa-flask',       bg:'rgba(22,163,74,.15)',   color:'#16a34a', btnBg:'linear-gradient(135deg,#15803d,#22c55e)',  title:{th:'เบิกใช้หลายรายการ',en:'Batch Use'}},
    borrow:  {icon:'fa-hand-holding',bg:'rgba(59,130,246,.15)',  color:'#2563eb', btnBg:'linear-gradient(135deg,#1d4ed8,#3b82f6)',  title:{th:'ยืมหลายรายการ',en:'Batch Borrow'}},
    transfer:{icon:'fa-share-nodes', bg:'rgba(139,92,246,.15)',  color:'#7c3aed', btnBg:'linear-gradient(135deg,#6d28d9,#8b5cf6)', title:{th:'โอนหลายรายการ',en:'Batch Transfer'}},
};

function openBatchTxn(type){
    if(!SELECTED.size){toast(T('กรุณาเลือกรายการก่อน','Please select items first'),'err');return;}
    _btxType=type;
    _btxUser=null;
    _btxRunning=false;
    _btxWholeSet.clear();
    // Reset submit button to original state (in case previous run changed it)
    const btn=document.getElementById('btxSubmitBtn');
    const lbl=document.getElementById('btxSubmitLbl');
    if(btn){ btn.disabled=false; btn.style.display=''; btn.onclick=submitBatchTxn; }
    if(lbl) lbl.textContent=T('ยืนยันดำเนินการ','Confirm');
    // Clear result box
    const rb=document.getElementById('btxResultBox');
    if(rb){ rb.style.display='none'; rb.innerHTML=''; }
    const prog=document.getElementById('btxProgress');
    if(prog) prog.style.display='none';
    _renderBatchBody();
    _updateBatchTabs();
    _updateBatchHeader();
    document.getElementById('btxOv').classList.add('show');
}

function closeBatchTxn(){
    if(_btxRunning) return;
    document.getElementById('btxOv').classList.remove('show');
}

function switchBatchTab(type){
    if(_btxRunning) return;
    _btxType=type;
    _btxUser=null;
    _updateBatchTabs();
    _updateBatchHeader();
    _renderBatchBody();
}

function _updateBatchTabs(){
    ['use','borrow','transfer'].forEach(t=>{
        const btn=document.getElementById('btxTab'+t.charAt(0).toUpperCase()+t.slice(1));
        if(btn) btn.classList.toggle('act',t===_btxType);
    });
}

function _updateBatchHeader(){
    const cfg=BTX_CFG[_btxType];
    const ic=document.getElementById('btxHdrIc');
    ic.style.background=cfg.bg; ic.style.color=cfg.color;
    ic.innerHTML=`<i class="fas ${cfg.icon}"></i>`;
    document.getElementById('btxHdrTitle').textContent=L==='th'?cfg.title.th:cfg.title.en;

    // Count actionable vs total
    const items=DATA.filter(r=>SELECTED.has(+r.id));
    const type=_btxType;
    const actionable=items.filter(r=>{
        const rem=parseFloat(r.current_quantity)||0;
        if(rem<=0) return false;
        if(type==='borrow'&&r.is_mine) return false;
        if(type==='transfer'&&!r.is_mine&&!(IS_ADMIN||IS_LAB)) return false;
        return true;
    });
    const skipped=items.length-actionable.length;
    let subTxt=T(`${items.length} รายการที่เลือก`,`${items.length} items selected`);
    if(skipped>0) subTxt+=` · <span style="color:#f59e0b">${T(`${skipped} ข้ามได้`,`${skipped} skipped`)}</span>`;
    document.getElementById('btxHdrSub').innerHTML=subTxt;

    const btn=document.getElementById('btxSubmitBtn');
    if(btn) btn.style.background=cfg.btnBg;
    const lbl=document.getElementById('btxSubmitLbl');
    if(lbl) lbl.textContent=L==='th'?cfg.title.th:cfg.title.en;
}

function _renderBatchBody(){
    const items=DATA.filter(r=>SELECTED.has(+r.id));
    const type=_btxType;
    const cfg=BTX_CFG[type];

    // Item list — with ownership / OOS checks
    let itemRows=items.map(r=>{
        const rem=parseFloat(r.current_quantity)||0;
        const unit=esc(r.quantity_unit||'');
        const isMine=!!r.is_mine;
        const isPrivileged=IS_ADMIN||IS_LAB;
        const isOos=rem<=0;
        // ยืมของตัวเอง → ไม่อนุญาต (เบิก/โอนได้)
        const borrowBlocked=type==='borrow'&&isMine;
        // เบิก/โอนของคนอื่น → อนุญาตเฉพาะ admin/lab เท่านั้น
        const ownerBlocked=(type==='use'||type==='transfer')&&!isMine&&!isPrivileged;
        const disabled=isOos||borrowBlocked||ownerBlocked;

        // Badges
        let badgeHtml='';
        if(isMine) badgeHtml+=`<span class="btx-badge btx-badge-mine"><i class="fas fa-star"></i> ${T('ของฉัน','Mine')}</span>`;
        if(isOos)  badgeHtml+=`<span class="btx-badge btx-badge-oos"><i class="fas fa-times-circle"></i> ${T('หมด Stock','Out of stock')}</span>`;
        else if(borrowBlocked) badgeHtml+=`<span class="btx-badge btx-badge-blocked"><i class="fas fa-ban"></i> ${T('เบิกได้อย่างเดียว','Use only')}</span>`;
        else if(ownerBlocked) badgeHtml+=`<span class="btx-badge btx-badge-blocked"><i class="fas fa-hand-holding"></i> ${T('ไม่ใช่เจ้าของ ยืมได้อย่างเดียว','Not owner — borrow only')}</span>`;

        // Transfer is ALWAYS whole-bottle — auto-mark and lock
        const forceWhole=(type==='transfer'&&!disabled);
        if(forceWhole) _btxWholeSet.add(+r.id); else if(type==='transfer') _btxWholeSet.delete(+r.id);
        const isWhole=forceWhole||_btxWholeSet.has(+r.id);
        const rowCls='btx-item'+(isOos?' btx-oos':(borrowBlocked||ownerBlocked)?' btx-blocked':isWhole?' btx-whole':'');
        const remColor=rem>0?'#0f172a':'#dc2626';
        const remText=rem>0?rem.toLocaleString():T('หมด','0');
        const qtyVal=isWhole?rem:(!disabled&&rem>0?Math.min(1,rem):'');

        // Transfer: show locked "ทั้งขวด" badge instead of toggle; Use/Borrow: normal toggle
        const wholeLabel=type==='use'?T('เบิกทั้งขวด','Use whole'):T('ยืมทั้งขวด','Borrow whole');
        const wholeToggle=type==='transfer'
            ?(!disabled?`<span class="btx-whole-toggle on" style="cursor:default;opacity:.75" title="${T('โอนทั้งขวดเสมอ','Always full bottle')}"><span class="btx-wt-dot"></span>${T('ทั้งขวด','Whole')}</span>`:'')
            :(!isOos&&!borrowBlocked&&!ownerBlocked
                ?`<button class="btx-whole-toggle${isWhole?' on':''}" id="btxWt${r.id}" onclick="btxToggleWhole(${r.id},${rem})" title="${wholeLabel}"><span class="btx-wt-dot"></span>${T('ทั้งขวด','Whole')}</button>`
                :'');

        return `<div class="${rowCls}" id="btxRow${r.id}" data-id="${r.id}">
            <div class="btx-item-ic" style="background:${cfg.bg};color:${cfg.color}"><i class="fas ${typeIcons[r.container_type||'other']||'fa-box'}"></i></div>
            <div class="btx-item-info">
                <div class="btx-item-name">${esc(r.chemical_name||'-')}</div>
                <div class="btx-item-sub">${esc(r.bottle_code||'')} · ${T('คงเหลือ','rem.')} <b style="color:${remColor}">${remText} ${unit}</b></div>
                ${badgeHtml?`<div class="btx-badges">${badgeHtml}</div>`:''}
            </div>
            ${wholeToggle}
            <div class="btx-item-qty" style="${(disabled||isWhole)?'opacity:.35;pointer-events:none':''}">
                <input type="number" min="0.001" max="${rem}" step="any"
                    value="${qtyVal}"
                    id="btxQty${r.id}" placeholder="${isOos?T('หมด','OOS'):'0'}"
                    oninput="btxQtyCheck(this,${rem})"
                    ${(disabled||isWhole)?'disabled':''}>
                <span class="btx-unit">${unit}</span>
            </div>
            <div class="btx-item-status" id="btxSt${r.id}"></div>
            <button class="btx-remove" onclick="btxRemoveItem(${r.id})" title="${T('ลบออกจากรายการ','Remove from list')}"><i class="fas fa-times"></i></button>
        </div>`;
    }).join('');

    const purposeField=`<div class="btx-field">
        <label><i class="fas fa-align-left"></i> ${T('วัตถุประสงค์','Purpose')} <span style="color:#dc2626">*</span></label>
        <textarea id="btxPurpose" rows="2" placeholder="${T('ระบุวัตถุประสงค์...','Describe purpose...')}"></textarea>
    </div>`;

    const userField=type==='borrow'
        ?`<div class="btx-field">
            <label><i class="fas fa-user-check"></i> ${T('ผู้ยืม','Borrower')}</label>
            <div id="btxUserSel" style="display:none"></div>
        </div>`
        :`<div class="btx-field">
            <label><i class="fas fa-user-check"></i> ${T('ผู้รับการโอน','Transfer Recipient')} <span style="color:#dc2626">*</span></label>
            <div class="btx-user-wrap">
                <input type="text" id="btxUserSearch" autocomplete="off"
                    placeholder="${T('ค้นหาชื่อหรือ username...','Search name or username...')}">
                <div class="btx-user-dd" id="btxUserDd"></div>
            </div>
            <div id="btxUserSel" style="display:none"></div>
        </div>`;

    const dateField=`<div class="btx-field">
        <label><i class="fas fa-calendar-alt"></i> ${T('กำหนดคืน','Return By')} <span style="color:#dc2626">*</span></label>
        <input type="date" id="btxRetDate" min="${new Date().toISOString().split('T')[0]}"
            value="${new Date(Date.now()+7*864e5).toISOString().split('T')[0]}">
    </div>`;

    // Count actionable for header hint
    const _priv=IS_ADMIN||IS_LAB;
    const actionableCount=items.filter(r=>{
        const rem=parseFloat(r.current_quantity)||0;
        if(rem<=0) return false;
        if(type==='borrow'&&r.is_mine) return false;
        if(type==='transfer'&&!r.is_mine&&!_priv) return false;
        return true;
    }).length;
    const skippedCount=items.length-actionableCount;
    const hdrRight=skippedCount>0
        ?`<span style="font-size:10px;color:#f59e0b;font-weight:700"><i class="fas fa-exclamation-triangle"></i> ${T(`ข้าม ${skippedCount} รายการ`,`${skippedCount} skipped`)}</span>`
        :type==='transfer'
            ?`<span style="font-size:10px;color:#7c3aed;font-weight:700"><i class="fas fa-bottle-droplet" style="font-size:9px"></i> ${T('โอนย้ายกรรมสิทธิทั้งขวดให้กับเจ้าของใหม่','Transfer full ownership to new owner')}</span>`
            :`<button class="btx-whole-all" onclick="btxToggleAllWhole()"><i class="fas fa-bottle-droplet" style="font-size:9px"></i> ${T('ทั้งขวดทุกรายการ','All whole')}</button>`;

    let html=`
    <div>
        <div class="btx-items-hdr">
            <h5><i class="fas fa-list-ul"></i> ${T('รายการสารเคมี','Items')} (${actionableCount}/${items.length})</h5>
            ${hdrRight}
        </div>
        <div class="btx-items-scroll">${itemRows}</div>
    </div>
    ${purposeField}
    ${(type==='borrow'||type==='transfer')?userField:''}
    ${type==='borrow'?dateField:''}
    <div id="btxProgress" style="display:none">
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;margin-bottom:4px">
            <span id="btxProgLbl">${T('กำลังดำเนินการ...','Processing...')}</span>
            <span id="btxProgNum">0/${items.length}</span>
        </div>
        <div class="btx-prog"><div class="btx-prog-fill" id="btxProgFill" style="width:0"></div></div>
    </div>
    <div id="btxResultBox" style="display:none"></div>`;

    document.getElementById('btxBody').innerHTML=html;
    _setupBtxUserSearch();
    if(type==='borrow'){
        _btxUCache[UID]=_CURRENT_USER_CACHE;
        _btxSelectUser(UID);
    }
}

// Safe cache — avoids apostrophe-in-name breaking onclick attribute strings
const _btxUCache={};

function _setupBtxUserSearch(){
    const inp=document.getElementById('btxUserSearch');
    if(!inp) return;
    let timer;
    inp.addEventListener('input',function(){
        clearTimeout(timer);
        const q=this.value.trim();
        const dd=document.getElementById('btxUserDd');
        if(q.length<2){dd.style.display='none';return;}
        timer=setTimeout(async()=>{
            try{
                const d=await apiFetch(`/v1/api/borrow.php?action=search_users&q=${encodeURIComponent(q)}`);
                const users=(d.success&&d.data)||[];
                if(!users.length){
                    dd.innerHTML=`<div style="padding:10px 12px;font-size:12px;color:#94a3b8">${T('ไม่พบผู้ใช้','No users found')}</div>`;
                    dd.style.display='block';return;
                }
                // Cache users by id so onclick only needs the safe integer id
                users.forEach(u=>{ _btxUCache[u.id]=u; });
                dd.innerHTML=users.map(u=>{
                    const fullName=esc((u.first_name||'')+' '+(u.last_name||'')).trim()||esc(u.username);
                    const initials=((u.first_name||'').charAt(0)+(u.last_name||'').charAt(0)).toUpperCase()||'?';
                    const avHtml=u.avatar_url
                        ?`<img src="${esc(u.avatar_url)}" alt="${fullName}" onerror="this.parentNode.textContent='${initials}'">`
                        :initials;
                    return `<div class="btx-user-opt" onclick="_btxSelectUser(${u.id})">
                        <div class="btx-user-av">${avHtml}</div>
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:700;font-size:13px;color:#0f172a;line-height:1.3">${fullName}</div>
                            <div style="font-size:10.5px;color:#64748b;display:flex;gap:8px;margin-top:1px;flex-wrap:wrap">
                                ${u.username?`<span style="color:#0d9488;font-weight:600">@${esc(u.username)}</span>`:''}
                                ${u.department?`<span><i class="fas fa-building" style="font-size:9px;opacity:.7"></i> ${esc(u.department)}</span>`:''}
                            </div>
                        </div>
                    </div>`;
                }).join('');
                dd.style.display='block';
            }catch(e){}
        },280);
    });
    document.addEventListener('click',function btxOutside(e){
        const dd=document.getElementById('btxUserDd');
        if(dd&&!dd.contains(e.target)&&e.target!==inp) dd.style.display='none';
        if(!document.getElementById('btxOv')?.classList.contains('show')) document.removeEventListener('click',btxOutside);
    });
}

function _btxSelectUser(id){
    const u=_btxUCache[id];
    if(!u) return;
    const fullName=((u.first_name||'')+' '+(u.last_name||'')).trim()||u.username;
    _btxUser={id:u.id, name:fullName, username:u.username, dep:u.department||'', avatar_url:u.avatar_url||''};
    const inp=document.getElementById('btxUserSearch');
    const dd=document.getElementById('btxUserDd');
    if(inp) inp.value='';
    if(dd) dd.style.display='none';
    const sel=document.getElementById('btxUserSel');
    if(sel){
        const initials=((u.first_name||'').charAt(0)+(u.last_name||'').charAt(0)).toUpperCase()||'?';
        const avHtml=_btxUser.avatar_url
            ?`<img src="${esc(_btxUser.avatar_url)}" alt="${esc(fullName)}" onerror="this.parentNode.textContent='${initials}'" style="width:100%;height:100%;object-fit:cover;border-radius:10px">`
            :initials;
        sel.style.display='flex';
        sel.className='btx-selected-user';
        sel.innerHTML=`
            <div class="btx-user-av" style="width:42px;height:42px;font-size:15px;border-radius:11px;flex-shrink:0">${avHtml}</div>
            <div style="flex:1;min-width:0">
                <div class="btx-selected-user-name">${esc(fullName)}</div>
                <div style="font-size:10.5px;color:#047857;display:flex;gap:8px;flex-wrap:wrap;margin-top:2px;font-weight:600">
                    <span>@${esc(u.username)}</span>
                    ${_btxUser.dep?`<span style="color:#0f766e;font-weight:500"><i class="fas fa-building" style="font-size:9px;opacity:.7"></i> ${esc(_btxUser.dep)}</span>`:''}
                </div>
            </div>
            ${_btxType!=='borrow'?`<button class="btx-clear-user" onclick="_btxClearUser()" title="${T('เปลี่ยนผู้รับ','Change recipient')}"><i class="fas fa-times"></i> ${T('เปลี่ยน','Change')}</button>`:''}`;

    }
}

function _btxClearUser(){
    _btxUser=null;
    const sel=document.getElementById('btxUserSel');
    if(sel) sel.style.display='none';
    const inp=document.getElementById('btxUserSearch');
    if(inp){inp.value='';inp.focus();}
}

/* Real-time qty validation — replays shake animation on each over-max keystroke */
function btxQtyCheck(inp,max){
    const v=parseFloat(inp.value)||0;
    const over=max>0&&v>max;
    if(over){
        // Remove and re-add class so CSS animation restarts on each keystroke
        inp.classList.remove('btx-qty-warn');
        void inp.offsetWidth; // force reflow
        inp.classList.add('btx-qty-warn');
        inp.title=T(`เกินปริมาณคงเหลือ (${max})`,`Exceeds remaining (${max})`);
    } else {
        inp.classList.remove('btx-qty-warn');
        inp.title='';
    }
}

/* Whole-bottle toggle per item */
function btxToggleWhole(id,rem){
    id=+id;
    const isNowWhole=!_btxWholeSet.has(id);
    if(isNowWhole) _btxWholeSet.add(id); else _btxWholeSet.delete(id);

    // Update toggle button appearance
    const tb=document.getElementById('btxWt'+id);
    if(tb) tb.classList.toggle('on',isNowWhole);

    // Update row class
    const row=document.getElementById('btxRow'+id);
    if(row) row.classList.toggle('btx-whole',isNowWhole);

    // Update qty input
    const inp=document.getElementById('btxQty'+id);
    if(inp){
        if(isNowWhole){
            inp.value=rem; inp.disabled=true;
            inp.parentElement.style.opacity='.35';
            inp.parentElement.style.pointerEvents='none';
            inp.classList.remove('btx-qty-warn'); inp.title='';
        } else {
            inp.value=Math.min(1,rem); inp.disabled=false;
            inp.parentElement.style.opacity='';
            inp.parentElement.style.pointerEvents='';
        }
    }
}

/* Bulk whole-bottle toggle — all actionable transfer items */
function btxToggleAllWhole(){
    const items=DATA.filter(r=>SELECTED.has(+r.id));
    const priv=IS_ADMIN||IS_LAB;
    const actionable=items.filter(r=>{
        const rem=parseFloat(r.current_quantity)||0;
        if(rem<=0) return false;
        if(_btxType==='borrow'&&r.is_mine) return false;
        if((_btxType==='use'||_btxType==='transfer')&&!r.is_mine&&!priv) return false;
        return true;
    });
    // If all are already whole → un-whole them all; otherwise whole them all
    const allWhole=actionable.every(r=>_btxWholeSet.has(+r.id));
    actionable.forEach(r=>{
        const rem=parseFloat(r.current_quantity)||0;
        if(rem<=0) return;
        if(allWhole) _btxWholeSet.delete(+r.id); else _btxWholeSet.add(+r.id);
        const tb=document.getElementById('btxWt'+r.id);
        if(tb) tb.classList.toggle('on',!allWhole);
        const row=document.getElementById('btxRow'+r.id);
        if(row) row.classList.toggle('btx-whole',!allWhole);
        const inp=document.getElementById('btxQty'+r.id);
        if(inp){
            if(!allWhole){
                inp.value=rem; inp.disabled=true;
                inp.parentElement.style.cssText='opacity:.35;pointer-events:none';
                inp.classList.remove('btx-qty-warn');
            } else {
                inp.value=Math.min(1,rem); inp.disabled=false;
                inp.parentElement.style.cssText='';
            }
        }
    });
}

/* Remove item from batch selection */
function btxRemoveItem(id){
    if(_btxRunning) return;
    SELECTED.delete(+id);
    updateSelectionUI();
    const remaining=DATA.filter(r=>SELECTED.has(+r.id));
    if(remaining.length===0){closeBatchTxn();return;}
    _updateBatchHeader();
    _renderBatchBody();
}

async function submitBatchTxn(){
    if(_btxRunning) return;
    const type=_btxType;
    const allItems=DATA.filter(r=>SELECTED.has(+r.id));
    const purpose=(document.getElementById('btxPurpose')?.value||'').trim();
    const retDate=document.getElementById('btxRetDate')?.value||'';

    // Validate shared fields
    if(!purpose){toast(T('กรุณาระบุวัตถุประสงค์','Please enter purpose'),'err');document.getElementById('btxPurpose')?.focus();return;}
    if((type==='borrow'||type==='transfer')&&!_btxUser){toast(T('กรุณาเลือกผู้รับ/ผู้ยืม','Please select recipient'),'err');return;}
    if(type==='borrow'&&!retDate){toast(T('กรุณาระบุกำหนดคืน','Please enter return date'),'err');return;}

    // Split items: actionable vs skipped
    const isPrivileged=IS_ADMIN||IS_LAB;
    const items=allItems.filter(r=>{
        const rem=parseFloat(r.current_quantity)||0;
        if(rem<=0) return false;                                              // OOS — skip
        if(type==='borrow'&&r.is_mine) return false;                         // own item borrow — skip
        if(type==='transfer'&&!r.is_mine&&!isPrivileged) return false;       // not owner, no privilege — skip
        return true;
    });
    const skipped=allItems.length-items.length;

    if(items.length===0){
        toast(T('ไม่มีรายการที่สามารถดำเนินการได้','No actionable items'),'err');
        return;
    }

    // Validate per-item qty — collect qty snapshot at the same time
    const itemsWithQty=[];
    for(const r of items){
        const rem=parseFloat(r.current_quantity)||0;
        const inp=document.getElementById('btxQty'+r.id);
        const qty=parseFloat(inp?.value)||0;
        if(qty<=0){
            inp?.focus();
            toast(T(`กรุณาระบุจำนวนสำหรับ ${r.chemical_name||''}`,`Enter qty for ${r.chemical_name||''}`),'err');
            return;
        }
        if(qty>rem){
            inp?.focus();
            inp?.classList.add('btx-qty-warn');
            toast(T(`${r.chemical_name||''}: จำนวนเกินกว่าที่คงเหลือ (${rem})`,`${r.chemical_name||''}: qty exceeds remaining (${rem})`),'err');
            return;
        }
        itemsWithQty.push({r, qty});
    }

    // Show preview popup for all types before executing
    _showBtcPreview(type, itemsWithQty, purpose, retDate, skipped, allItems, items);
}

/* ─── Pre-Transfer Preview Popup ─── */
function _openBtcPopup(){ document.getElementById('btcOv').classList.add('show'); }
function _closeBtcPopup(){ document.getElementById('btcOv').classList.remove('show'); }

function _showBtcPreview(type, itemsWithQty, purpose, retDate, skipped, allItems, items){
    const cfg=BTX_CFG[type];
    const u=_btxUser||{};

    // Per-type config
    const titles={
        use:      {th:'ยืนยันการเบิกใช้',      en:'Confirm Use'},
        borrow:   {th:'ยืนยันการยืม',           en:'Confirm Borrow'},
        transfer: {th:'ยืนยันการโอนสาร',        en:'Confirm Transfer'},
    };
    const confirmBtns={
        use:      {icon:'fa-flask',        th:'เบิกใช้เลย',      en:'Confirm Use'},
        borrow:   {icon:'fa-hand-holding', th:'ยืนยันการยืม',    en:'Confirm Borrow'},
        transfer: {icon:'fa-check-double', th:'ยืนยันโอนสาร',    en:'Confirm Transfer'},
    };
    const confirmGradients={
        use:      'linear-gradient(135deg,#15803d,#22c55e)',
        borrow:   'linear-gradient(135deg,#1d4ed8,#3b82f6)',
        transfer: 'linear-gradient(135deg,#6d28d9,#8b5cf6)',
    };

    // Recipient card (borrow / transfer)
    let recipientHtml='';
    if(u.name){
        const initials=((u.name||'?').charAt(0)).toUpperCase();
        const avHtml=u.avatar_url
            ?`<img src="${esc(u.avatar_url)}" alt="${esc(u.name||'')}" onerror="this.parentNode.textContent='${initials}'">`
            :initials;
        const recLabel=type==='transfer'?T('ผู้รับการโอน','Recipient'):T('ผู้ยืม','Borrower');
        recipientHtml=`<div>
            <div class="btc-sec"><i class="fas fa-user"></i> ${recLabel}</div>
            <div class="btc-recipient">
                <div class="btc-rec-av">${avHtml}</div>
                <div style="flex:1;min-width:0">
                    <div class="btc-rec-name">${esc(u.name)}</div>
                    <div class="btc-rec-sub">
                        ${u.username?`<span><i class="fas fa-at" style="font-size:9px"></i> ${esc(u.username)}</span>`:''}
                        ${u.dep?`<span><i class="fas fa-building" style="font-size:9px"></i> ${esc(u.dep)}</span>`:''}
                    </div>
                </div>
            </div>
        </div>`;
    }

    // Return date row (borrow only)
    const retHtml=retDate?`<div>
        <div class="btc-sec"><i class="fas fa-calendar-alt"></i> ${T('กำหนดคืน','Return by')}</div>
        <div class="btc-purpose" style="display:flex;align-items:center;gap:6px">
            <i class="fas fa-clock" style="color:#2563eb;font-size:11px"></i>
            ${new Date(retDate).toLocaleDateString(L==='th'?'th-TH':'en-GB',{day:'numeric',month:'long',year:'numeric'})}
        </div>
    </div>`:'';

    // Item rows
    const itemsHtml=itemsWithQty.map(({r,qty})=>{
        const unit=esc(r.quantity_unit||'');
        const isWhole=_btxWholeSet.has(+r.id);
        const isMine=!!r.is_mine;
        const wholeBadge=isWhole?`<span style="font-size:9px;background:#ede9fe;color:#6d28d9;padding:1px 5px;border-radius:4px;font-weight:700;margin-left:4px">${T('ทั้งขวด','Whole')}</span>`:'';
        const mineBadge=(type!=='use'&&isMine)?`<span style="font-size:9px;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:4px;font-weight:700;margin-left:4px">${T('ของฉัน','Mine')}</span>`:'';
        const icon=isWhole?'fa-bottle-droplet':(type==='use'?'fa-flask':type==='borrow'?'fa-hand-holding':'fa-share-nodes');
        const ownerLine=(type==='borrow'&&r.owner_name&&r.owner_name!=='-')
            ?`<div style="font-size:9.5px;color:#7c3aed;font-weight:600;margin-top:2px;display:flex;align-items:center;gap:3px"><i class="fas fa-user" style="font-size:8px;opacity:.65"></i> ${esc(r.owner_name)}${r.location_text&&r.location_text!=='-'?`<span style="opacity:.45;margin:0 1px">·</span><i class="fas fa-map-marker-alt" style="font-size:8px;opacity:.65"></i> ${esc(r.location_text)}`:''}</div>`
            :'';
        return `<div class="btc-item" style="background:${cfg.bg};border-color:${cfg.bg}">
            <div class="btc-item-ic" style="background:rgba(0,0,0,.06);color:${cfg.color}"><i class="fas ${icon}"></i></div>
            <div class="btc-item-name" title="${esc(r.chemical_name||'')}">${esc(r.chemical_name||'-')}
                <div style="font-size:10px;color:#64748b;font-weight:400;margin-top:1px">${esc(r.bottle_code||r.barcode||'')}</div>
                ${ownerLine}
            </div>
            <div class="btc-item-qty" style="color:${cfg.color}">${qty.toLocaleString()} ${unit}${wholeBadge}${mineBadge}</div>
        </div>`;
    }).join('');

    // Summary totals
    const totalQtyByUnit={};
    itemsWithQty.forEach(({r,qty})=>{
        const u2=r.quantity_unit||'';
        totalQtyByUnit[u2]=(totalQtyByUnit[u2]||0)+qty;
    });
    const totalStr=Object.entries(totalQtyByUnit).map(([u2,q])=>`${q.toLocaleString()} ${u2}`).join(', ');

    const warnHtml=skipped>0
        ?`<div class="btc-warn"><i class="fas fa-exclamation-triangle"></i> ${T(`จะข้าม ${skipped} รายการ (หมด / ไม่มีสิทธิ์)`,`${skipped} item(s) skipped (OOS / no permission)`)}</div>`
        :'';

    // Store state for confirm
    window._btcPendingData={type, itemsWithQty, purpose, retDate, skipped, allItems, items};

    document.getElementById('btcBox').innerHTML=`
        <div class="btc-hdr">
            <div class="btc-hdr-ic" style="background:${confirmGradients[type]}">
                <i class="fas ${cfg.icon}"></i>
            </div>
            <h3>${T(titles[type].th, titles[type].en)}</h3>
            <p>${T(`${itemsWithQty.length} รายการ · รวม ${totalStr}`,`${itemsWithQty.length} item(s) · total ${totalStr}`)}</p>
        </div>
        <div class="btc-body">
            ${recipientHtml}
            <div>
                <div class="btc-sec"><i class="fas fa-list-ul"></i> ${T('รายการสารเคมี','Items')} (${itemsWithQty.length})</div>
                <div class="btc-items">${itemsHtml}</div>
            </div>
            <div>
                <div class="btc-sec"><i class="fas fa-align-left"></i> ${T('วัตถุประสงค์','Purpose')}</div>
                <div class="btc-purpose">${esc(purpose)}</div>
            </div>
            ${retHtml}
            ${warnHtml}
        </div>
        <div class="btc-footer">
            <button class="btc-btn-confirm" style="background:${confirmGradients[type]}" onclick="_btcConfirm()">
                <i class="fas ${confirmBtns[type].icon}"></i>
                ${T(confirmBtns[type].th, confirmBtns[type].en)}
            </button>
            <button class="btc-btn-cancel" onclick="_closeBtcPopup()">
                <i class="fas fa-arrow-left"></i> ${T('ยกเลิก / แก้ไข','Cancel / Edit')}
            </button>
        </div>`;
    _openBtcPopup();
}

async function _btcConfirm(){
    const d=window._btcPendingData;
    if(!d) return;
    // Close both popups immediately — before API calls
    _closeBtcPopup();
    document.getElementById('btxOv').classList.remove('show');
    await _execBatchTxn(d.type, d.itemsWithQty, d.allItems, d.items, d.purpose, d.retDate, d.skipped);
}

/* ─── Core execution (shared by all types) ─── */
async function _execBatchTxn(type, itemsWithQty, allItems, items, purpose, retDate, skipped){
    _btxRunning=true;
    // modal may already be closed (confirmed from preview popup) — check once
    const modalOpen=document.getElementById('btxOv').classList.contains('show');
    const btn=document.getElementById('btxSubmitBtn');
    if(modalOpen){
        btn.disabled=true;
        document.getElementById('btxProgress').style.display='block';
        document.getElementById('btxResultBox').style.display='none';
        allItems.filter(r=>!items.includes(r)).forEach(r=>{
            const stEl=document.getElementById('btxSt'+r.id);
            if(stEl) stEl.innerHTML='<span style="color:#f59e0b;font-size:11px" title="'+T('ข้าม','Skipped')+'">—</span>';
        });
    }

    let ok=0, fail=0;
    const pendingTxns=[];
    const errors=[];

    for(let i=0;i<itemsWithQty.length;i++){
        const {r,qty}=itemsWithQty[i];

        if(modalOpen){
            const row=document.getElementById('btxRow'+r.id);
            const stEl=document.getElementById('btxSt'+r.id);
            document.getElementById('btxProgNum').textContent=`${i}/${itemsWithQty.length}`;
            document.getElementById('btxProgFill').style.width=`${(i/itemsWithQty.length)*100}%`;
            document.getElementById('btxProgLbl').textContent=T(`กำลังดำเนินการ ${r.chemical_name||''}...`,`Processing ${r.chemical_name||''}...`);
            try{
                const d=await _execSingleTxn(type,r,qty,purpose,retDate);
                if(d.success){
                    ok++;
                    const txnStatus=d.data?.status||'completed';
                    if(type==='transfer'&&txnStatus==='pending'){
                        pendingTxns.push({txn_id:d.data.id,txn_number:d.data.txn_number||'',chemical_name:r.chemical_name||'-',qty,unit:r.quantity_unit||'',whole_bottle:!!d.data.whole_bottle});
                        if(row) row.classList.add('btx-ok');
                        if(stEl) stEl.innerHTML=`<span style="color:#f59e0b;font-size:13px" title="${T('รอยืนยัน','Pending')}">⏳</span>`;
                    } else {
                        if(row) row.classList.add('btx-ok');
                        if(stEl) stEl.innerHTML='<span style="color:#16a34a;font-size:15px">✓</span>';
                    }
                } else throw new Error(d.error||'Failed');
            }catch(e){
                fail++;
                console.error('[BatchTxn]',r.id,e.message);
                if(row) row.classList.add('btx-err');
                if(stEl) stEl.innerHTML=`<span style="color:#dc2626;font-size:15px" title="${esc(e.message)}">✗</span>`;
                errors.push(e.message);
            }
        } else {
            // Silent path — modal closed, just run API calls
            try{
                const d=await _execSingleTxn(type,r,qty,purpose,retDate);
                if(d.success){
                    ok++;
                    if(type==='transfer'&&(d.data?.status||'completed')==='pending')
                        pendingTxns.push({txn_id:d.data.id,txn_number:d.data.txn_number||'',chemical_name:r.chemical_name||'-',qty,unit:r.quantity_unit||'',whole_bottle:!!d.data.whole_bottle});
                } else throw new Error(d.error||'Failed');
            }catch(e){
                fail++;
                console.error('[BatchTxn]',r.id,e.message);
                errors.push(e.message);
            }
        }
    }

    _btxRunning=false;

    if(pendingTxns.length>0){
        _showTransferConfirmPanel(pendingTxns, ok, fail, skipped, btn);
        return;
    }

    if(modalOpen){
        document.getElementById('btxProgFill').style.width='100%';
        document.getElementById('btxProgNum').textContent=`${itemsWithQty.length}/${itemsWithQty.length}`;
        document.getElementById('btxProgLbl').textContent=T('เสร็จสิ้น','Done');
        _finishBatchTxn(ok, fail, skipped, btn);
    } else {
        // Modals closed — show toast result and refresh
        if(fail===0&&ok>0){
            const typeLabel={use:T('เบิกใช้','used'),borrow:T('ยืม','borrowed'),transfer:T('โอน','transferred')};
            toast(T(`${typeLabel[type]}สำเร็จ ${ok} รายการ`,`${ok} item(s) ${typeLabel[type]} successfully`));
        } else if(fail>0){
            toast(errors[0]||T(`ล้มเหลว ${fail} รายการ`,`${fail} failed`),'err');
        }
        if(ok>0){ clearSelection(); loadData(); }
    }
}

function _execSingleTxn(type,r,qty,purpose,retDate){
    const srcType=r.source||(r.id>0?'container':'stock');
    const srcId=Math.abs(+r.id);
    const payload={source_type:srcType,source_id:srcId,quantity:qty,purpose};
    if(type==='borrow'){payload.to_user_id=_btxUser.id;payload.expected_return_date=retDate;}
    else if(type==='transfer'){payload.to_user_id=_btxUser.id;payload.whole_bottle=true;} // always whole bottle
    return apiFetch('/v1/api/borrow.php?action='+type,{method:'POST',body:JSON.stringify(payload)});
}

function _finishBatchTxn(ok, fail, skipped, btn){
    const rb=document.getElementById('btxResultBox');
    rb.style.display='block';
    let lines=[];
    if(ok>0)     lines.push(`<span style="color:#16a34a"><i class="fas fa-check-circle"></i> ${T(`สำเร็จ ${ok} รายการ`,`${ok} succeeded`)}</span>`);
    if(fail>0)   lines.push(`<span style="color:#dc2626"><i class="fas fa-times-circle"></i> ${T(`ล้มเหลว ${fail} รายการ`,`${fail} failed`)}</span>`);
    if(skipped>0)lines.push(`<span style="color:#f59e0b"><i class="fas fa-minus-circle"></i> ${T(`ข้าม ${skipped} รายการ`,`${skipped} skipped`)}</span>`);
    rb.innerHTML=`<div class="btx-result-summary ${fail?'btx-result-err':'btx-result-ok'}" style="flex-direction:column;align-items:flex-start;gap:4px;font-size:12px">${lines.join('')}</div>`;
    // Update label only — do NOT replace btn.innerHTML (would destroy #btxSubmitLbl span)
    const lbl=document.getElementById('btxSubmitLbl');
    if(lbl) lbl.textContent=T('ปิด','Close');
    if(btn){ btn.disabled=false; btn.onclick=()=>{ closeBatchTxn(); if(ok>0){clearSelection();loadData();} }; }
}

/* ── Transfer Confirm Popup ── */
function _openTfcPopup(){ document.getElementById('tfcOv').classList.add('show'); }
function _closeTfcPopup(){ document.getElementById('tfcOv').classList.remove('show'); }

function _showTransferConfirmPanel(pendingTxns, ok, fail, skipped, btn){
    // Close batch modal cleanly (bypass _btxRunning guard)
    document.getElementById('btxOv').classList.remove('show');
    _finishBatchTxn(ok-pendingTxns.length, fail, skipped, btn);

    const u=_btxUser||{};
    const initials=((u.name||'?').charAt(0)).toUpperCase();
    const avHtml=u.avatar_url
        ?`<img src="${esc(u.avatar_url)}" alt="${esc(u.name||'')}" onerror="this.parentNode.textContent='${initials}'">`
        :initials;

    const itemsHtml=pendingTxns.map(p=>`
        <div class="tfc-item" id="tfcItem${p.txn_id}">
            <div class="tfc-item-ic"><i class="fas ${p.whole_bottle?'fa-bottle-droplet':'fa-flask'}"></i></div>
            <div class="tfc-item-name" title="${esc(p.chemical_name)}">${esc(p.chemical_name)}</div>
            <div class="tfc-item-qty">${p.qty.toLocaleString()} ${esc(p.unit)}${p.whole_bottle?` <span style="font-size:9px;background:#ede9fe;color:#6d28d9;padding:1px 5px;border-radius:4px;font-weight:700">${T('ทั้งขวด','Whole')}</span>`:''}</div>
            <div class="tfc-item-st" id="tfcSt${p.txn_id}">⏳</div>
        </div>`).join('');

    const txnIdsJson=JSON.stringify(pendingTxns.map(p=>p.txn_id));

    const summaryHtml=(ok-pendingTxns.length>0||fail>0||skipped>0)?`<div class="tfc-summary">
        ${ok-pendingTxns.length>0?`<div class="tfc-sum-chip tfc-sum-ok"><i class="fas fa-check-circle"></i> ${T(`${ok-pendingTxns.length} สำเร็จแล้ว`,`${ok-pendingTxns.length} done`)}</div>`:''}
        ${fail>0?`<div class="tfc-sum-chip tfc-sum-fail"><i class="fas fa-times-circle"></i> ${T(`${fail} ล้มเหลว`,`${fail} failed`)}</div>`:''}
        ${skipped>0?`<div class="tfc-sum-chip tfc-sum-skip"><i class="fas fa-minus-circle"></i> ${T(`${skipped} ข้าม`,`${skipped} skipped`)}</div>`:''}
    </div>`:'';

    document.getElementById('tfcBox').innerHTML=`
        <div class="tfc-hdr">
            <div class="tfc-hdr-ic" style="background:linear-gradient(135deg,#0369a1,#38bdf8)"><i class="fas fa-paper-plane"></i></div>
            <h3>${T('ส่งคำขอโอนแล้ว','Transfer Request Sent')}</h3>
            <p>${T(`${pendingTxns.length} รายการ — รอผู้รับยืนยันรับโอนกรรมสิทธิ์`,`${pendingTxns.length} item(s) — awaiting recipient confirmation`)}</p>
        </div>
        <div class="tfc-body">
            <div>
                <div class="tfc-sec-label"><i class="fas fa-user-check"></i> ${T('ผู้รับโอน (รอยืนยัน)','Recipient (pending)')}</div>
                <div class="tfc-recipient" style="background:#f0f9ff;border-color:#bae6fd">
                    <div class="tfc-rec-av" style="background:linear-gradient(135deg,#0369a1,#38bdf8)">${avHtml}</div>
                    <div class="tfc-rec-info">
                        <div class="tfc-rec-name">${esc(u.name||'-')}</div>
                        <div class="tfc-rec-sub">
                            <span><i class="fas fa-at" style="font-size:9px"></i> ${esc(u.username||'')}</span>
                            ${u.dep?`<span><i class="fas fa-building" style="font-size:9px"></i> ${esc(u.dep)}</span>`:''}
                        </div>
                    </div>
                    <span style="font-size:9px;background:#fef3c7;color:#92400e;padding:2px 7px;border-radius:5px;font-weight:700;white-space:nowrap"><i class="fas fa-clock" style="font-size:8px"></i> ${T('รอยืนยัน','Pending')}</span>
                </div>
            </div>
            <div>
                <div class="tfc-sec-label"><i class="fas fa-list-ul"></i> ${T('รายการที่ส่งคำขอ','Requested items')} (${pendingTxns.length})</div>
                <div class="tfc-items">${itemsHtml}</div>
            </div>
            ${summaryHtml}
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:10px 12px;font-size:11px;color:#0369a1;display:flex;align-items:flex-start;gap:8px;margin-top:4px">
                <i class="fas fa-bell" style="margin-top:1px;flex-shrink:0"></i>
                <span>${T('ผู้รับได้รับการแจ้งเตือนแล้ว เมื่อยืนยันรับโอน กรรมสิทธิ์จะย้ายไปทันที','Recipient has been notified. Ownership transfers upon their confirmation.')}</span>
            </div>
        </div>
        <div class="tfc-footer">
            <button class="tfc-btn-confirm" onclick="_closeTfcPopup();loadData()" style="background:linear-gradient(135deg,#0369a1,#38bdf8)">
                <i class="fas fa-check"></i> ${T('รับทราบ','Got it')}
            </button>
        </div>`;

    _openTfcPopup();
    _btxRunning=false;
}

async function _tfcApproveAll(txnIds){
    const approveBtn=document.getElementById('tfcApproveBtn');
    const rejectBtn=document.getElementById('tfcRejectBtn');
    if(approveBtn){ approveBtn.disabled=true; approveBtn.innerHTML=`<i class="fas fa-spinner fa-spin"></i> ${T('กำลังยืนยัน...','Confirming...')}`; }
    if(rejectBtn) rejectBtn.disabled=true;
    document.getElementById('tfcProgWrap').style.display='block';

    let approved=0, failed=0;
    for(let i=0;i<txnIds.length;i++){
        const txnId=txnIds[i];
        const itemEl=document.getElementById('tfcItem'+txnId);
        const stEl=document.getElementById('tfcSt'+txnId);
        document.getElementById('tfcProgNum').textContent=`${i}/${txnIds.length}`;
        document.getElementById('tfcProgFill').style.width=`${(i/txnIds.length)*100}%`;
        try{
            const d=await apiFetch('/v1/api/borrow.php?action=approve',{method:'POST',body:JSON.stringify({txn_id:txnId})});
            if(d.success){
                approved++;
                if(itemEl) itemEl.classList.add('tfc-ok');
                if(stEl) stEl.innerHTML='<span style="color:#16a34a">✓</span>';
            } else throw new Error(d.error||'Failed');
        }catch(e){
            failed++;
            if(itemEl) itemEl.classList.add('tfc-fail');
            if(stEl) stEl.innerHTML=`<span style="color:#dc2626" title="${esc(e.message)}">✗</span>`;
        }
    }
    document.getElementById('tfcProgFill').style.width='100%';
    document.getElementById('tfcProgNum').textContent=`${txnIds.length}/${txnIds.length}`;
    document.getElementById('tfcProgLbl').textContent=T('เสร็จสิ้น','Done');

    // Replace footer with result + close button
    const footer=document.querySelector('.tfc-footer');
    if(footer) footer.innerHTML=`
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;padding:4px 0">
            ${approved>0?`<div class="tfc-sum-chip tfc-sum-ok" style="font-size:12px"><i class="fas fa-check-circle"></i> ${T(`ยืนยันแล้ว ${approved} รายการ`,`${approved} confirmed`)}</div>`:''}
            ${failed>0?`<div class="tfc-sum-chip tfc-sum-fail" style="font-size:12px"><i class="fas fa-times-circle"></i> ${T(`ล้มเหลว ${failed} รายการ`,`${failed} failed`)}</div>`:''}
        </div>
        <button class="tfc-btn-confirm" style="background:linear-gradient(135deg,#15803d,#22c55e)" onclick="_closeTfcPopup();clearSelection();loadData()">
            <i class="fas fa-check"></i> ${T('เสร็จสิ้น','Done')}
        </button>`;
}

async function _tfcRejectAll(txnIds){
    if(!confirm(T('ปฏิเสธการโอนสารทั้งหมดใช่หรือไม่?','Reject all pending transfers?'))) return;
    const approveBtn=document.getElementById('tfcApproveBtn');
    const rejectBtn=document.getElementById('tfcRejectBtn');
    if(approveBtn) approveBtn.disabled=true;
    if(rejectBtn){ rejectBtn.disabled=true; rejectBtn.innerHTML=`<i class="fas fa-spinner fa-spin"></i>`; }
    document.getElementById('tfcProgWrap').style.display='block';
    document.getElementById('tfcProgLbl').textContent=T('กำลังปฏิเสธ...','Rejecting...');

    let rejected=0;
    for(let i=0;i<txnIds.length;i++){
        const txnId=txnIds[i];
        const itemEl=document.getElementById('tfcItem'+txnId);
        const stEl=document.getElementById('tfcSt'+txnId);
        document.getElementById('tfcProgFill').style.width=`${((i+1)/txnIds.length)*100}%`;
        try{
            const d=await apiFetch('/v1/api/borrow.php?action=reject',{method:'POST',body:JSON.stringify({txn_id:txnId})});
            if(d.success){ rejected++; if(itemEl) itemEl.classList.add('tfc-fail'); if(stEl) stEl.innerHTML='<span style="color:#dc2626">✕</span>'; }
        }catch(e){ if(stEl) stEl.innerHTML='<span style="color:#dc2626">✗</span>'; }
    }
    const footer=document.querySelector('.tfc-footer');
    if(footer) footer.innerHTML=`
        <div class="tfc-sum-chip tfc-sum-fail" style="width:100%;justify-content:center;font-size:12px;padding:8px">
            <i class="fas fa-times-circle"></i> ${T(`ปฏิเสธ ${rejected} รายการแล้ว`,`${rejected} transfer(s) rejected`)}
        </div>
        <button class="tfc-btn-close" style="width:100%" onclick="_closeTfcPopup();loadData()">
            ${T('ปิด','Close')}
        </button>`;
}

/* ═════════════════════════════════════════
   SINGLE LABEL PRINT
   ═════════════════════════════════════════ */
async function doPrintSingleLabel(id){
    try{
        const d=await apiFetch('/v1/api/containers.php?action=detail&id='+id);
        if(!d.success)throw new Error(d.error);
        const r=d.data;
        r.has_3d=!!(r.ar_data&&r.ar_data.has_model);
        r.hazard_pictograms=r.hazard_pictograms||[];
        
        const labelOv=document.getElementById('labelOv');
        const labelContent=document.getElementById('labelContent');
        labelOv.classList.add('show');
        
        let h=`<div style="display:flex;gap:8px;margin-bottom:16px;align-items:center">
            <span style="font-size:13px;font-weight:700;color:var(--c1)"><i class="fas fa-tag" style="color:var(--accent)"></i> ${T('ฉลากสำหรับ','Label for')} ${esc(r.chemical_name||'')}</span>
            <button class="stk-btn stk-btn-p" onclick="printLabels()"><i class="fas fa-print"></i> ${T('พิมพ์','Print')}</button>
            <button class="stk-btn stk-btn-g" onclick="closeLabelModal()"><i class="fas fa-times"></i> ${T('ปิด','Close')}</button>
        </div>`;
        h+='<div id="labelGrid" style="max-width:440px;margin:0 auto">';
        h+=generateLabelHtml(r);
        h+='</div>';
        labelContent.innerHTML=h;
        
        await loadExternalLibs();
        setTimeout(()=>{
            renderQRCode('qrLabel_'+r.id, window.location.origin+'/v1/ar/view_ar.php?id='+r.id, 70);
            renderBarcode('barcode_'+r.id, r.bottle_code||('ID'+r.id));
        },200);
    }catch(e){
        toast(T('❌ ไม่สามารถสร้างฉลากได้','❌ Cannot generate label'),'err');
    }
}

function updateSelectionUI(){
    const bar=document.getElementById('batchBar');
    const cnt=document.getElementById('selCount');
    if(SELECTED.size>0){bar.style.display='flex';cnt.textContent=SELECTED.size}else{bar.style.display='none'}
    // Show Use/Transfer only when at least one selected item is owned by current user
    const anyMine=[...SELECTED].some(id=>{const r=DATA.find(x=>+x.id===id);return r&&(r.is_mine||IS_ADMIN);});
    const allMine=[...SELECTED].every(id=>{const r=DATA.find(x=>+x.id===id);return r&&(r.is_mine||IS_ADMIN);});
    const bbUse=document.getElementById('bbBtnUse');
    const bbBorrow=document.getElementById('bbBtnBorrow');
    const bbTransfer=document.getElementById('bbBtnTransfer');
    if(bbUse)bbUse.style.display=anyMine?'':'none';
    if(bbBorrow)bbBorrow.style.display=allMine?'none':'';
    if(bbTransfer)bbTransfer.style.display=anyMine?'':'none';
    // Update checkboxes
    document.querySelectorAll('.stk-chk').forEach(el=>{el.checked=SELECTED.has(+el.dataset.id)});
    const allCb=document.getElementById('chkAll');
    if(allCb)allCb.checked=SELECTED.size===DATA.length&&DATA.length>0;
}

/* ═════════════════════════════════════════
   DISPOSE (จำหน่าย) MODAL
   ═════════════════════════════════════════ */
let _dspItems=[];   // [{r, qty}] items queued for disposal

function openDisposeFor(id){
    // Open dispose modal pre-loaded with a single item (from detail modal)
    const r=DATA.find(x=>+x.id===id);
    if(!r){toast(T('ไม่พบข้อมูลสาร','Item not found'),'err');return;}
    _dspItems=[{r}];
    _renderDisposeBody();
    const sub=document.getElementById('dspHdrSub');
    if(sub) sub.textContent=T('รายการที่เลือก 1 รายการ','1 item selected');
    document.getElementById('dspOv').classList.add('show');
}

function openDispose(){
    if(SELECTED.size===0){toast(T('กรุณาเลือกสารเคมีก่อน','Please select items first'),'err');return;}
    _dspItems=[];
    SELECTED.forEach(id=>{
        const r=DATA.find(x=>+x.id===id);
        if(r) _dspItems.push({r});
    });
    if(_dspItems.length===0){toast(T('ไม่พบข้อมูลสาร','No item data found'),'err');return;}
    _renderDisposeBody();
    const sub=document.getElementById('dspHdrSub');
    if(sub) sub.textContent=T(`รายการที่เลือก ${_dspItems.length} รายการ`,`${_dspItems.length} item(s) selected`);
    document.getElementById('dspOv').classList.add('show');
}

function closeDispose(){
    document.getElementById('dspOv').classList.remove('show');
}

function _renderDisposeBody(){
    const el=document.getElementById('dspBody');
    if(!el) return;

    const reasonOpts=[
        {v:'expired',    th:'หมดอายุ',           en:'Expired'},
        {v:'damaged',    th:'แตก / เสียหาย',      en:'Broken / Damaged'},
        {v:'lost',       th:'สูญหาย',             en:'Lost / Missing'},
        {v:'discrepancy',th:'คลาดเคลื่อน / ขาด',  en:'Discrepancy'},
        {v:'other',      th:'อื่นๆ',              en:'Other'},
    ];
    const methodOpts=[
        {v:'standard',   th:'ทิ้งตามระเบียบ',      en:'Standard disposal'},
        {v:'incinerate', th:'เผาทำลาย',            en:'Incinerate'},
        {v:'handover',   th:'ส่งหน่วยกำจัด',       en:'Hand to disposal unit'},
        {v:'other',      th:'อื่นๆ',              en:'Other'},
    ];

    let itemsHtml=_dspItems.map((it,idx)=>{
        const qty=+(it.r.current_quantity??it.r.remaining_qty??it.r.quantity??0);
        const unit=esc(it.r.quantity_unit||it.r.unit||'');
        return `<div class="dsp-item" id="dspItem${idx}">
            <div class="dsp-item-info">
                <div class="dsp-item-name">${esc(it.r.chemical_name||it.r.name||'')}</div>
                <div class="dsp-item-meta">${it.r.bottle_code?esc(it.r.bottle_code)+' · ':''}${it.r.location_name?esc(it.r.location_name):''}</div>
            </div>
            <div class="dsp-item-qty" style="font-size:12px;font-weight:700;color:#dc2626;white-space:nowrap;flex-shrink:0">
                ${qty} <span style="font-weight:400;color:#9ca3af">${unit}</span>
            </div>
            <button class="dsp-item-rm" onclick="_dspRemoveItem(${idx})" title="Remove">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
    }).join('');

    const reasonSelOpts=reasonOpts.map(o=>`<option value="${o.v}">${L==='th'?o.th:o.en}</option>`).join('');
    const methodSelOpts=methodOpts.map(o=>`<option value="${o.v}">${L==='th'?o.th:o.en}</option>`).join('');

    el.innerHTML=`
        <div class="dsp-items" id="dspItemList">${itemsHtml}</div>
        <div class="dsp-warn">
            <i class="fas fa-exclamation-triangle"></i>
            ${T('การจำหน่ายออกจะลดปริมาณสารและบันทึกลง disposal log ไม่สามารถย้อนกลับได้',
               'Disposal will reduce stock and log to disposal record. This cannot be undone.')}
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px">
                    ${T('เหตุผล','Reason')} <span style="color:#dc2626">*</span>
                </label>
                <select class="dsp-select" id="dspReason">
                    <option value="">${T('-- เลือกเหตุผล --','-- Select reason --')}</option>
                    ${reasonSelOpts}
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px">
                    ${T('วิธีกำจัด','Method')}
                </label>
                <select class="dsp-select" id="dspMethod">
                    <option value="standard">${T('ทิ้งตามระเบียบ','Standard disposal')}</option>
                    ${methodSelOpts.replace('<option value="standard">'+T('ทิ้งตามระเบียบ','Standard disposal')+'</option>','')}
                </select>
            </div>
        </div>
        <div style="margin-top:10px">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px">
                ${T('หมายเหตุ','Notes')} <span style="font-weight:400;color:#9ca3af">${T('(ไม่บังคับ)','(optional)')}</span>
            </label>
            <textarea class="dsp-textarea" id="dspNotes" rows="2"
                placeholder="${T('เพิ่มรายละเอียดเพิ่มเติม...','Additional details...')}"></textarea>
        </div>`;
}

function _dspRemoveItem(idx){
    _dspItems.splice(idx,1);
    if(_dspItems.length===0){closeDispose();return;}
    _renderDisposeBody();
}

// Pending dispose state
let _dspPending=null;

function submitDispose(){
    const reasonEl=document.getElementById('dspReason');
    const methodEl=document.getElementById('dspMethod');
    const notesEl=document.getElementById('dspNotes');

    const reason=reasonEl?reasonEl.value:'';
    const method=methodEl?methodEl.value:'standard';
    const notes=notesEl?notesEl.value.trim():'';

    if(!reason){toast(T('กรุณาเลือกเหตุผล','Please select a reason'),'err');if(reasonEl)reasonEl.focus();return;}
    if(_dspItems.length===0){toast(T('ไม่มีรายการจำหน่าย','No items to dispose'),'err');return;}

    // Save state then show confirm popup
    _dspPending={items:[..._dspItems],reason,method,notes};
    _showDspConfirm();
}

function _showDspConfirm(){
    const d=_dspPending;
    if(!d) return;

    const reasonLabels={
        expired:T('หมดอายุ','Expired'),
        damaged:T('แตก / เสียหาย','Broken / Damaged'),
        lost:T('สูญหาย','Lost / Missing'),
        discrepancy:T('คลาดเคลื่อน / ขาด','Discrepancy'),
        other:T('อื่นๆ','Other'),
    };
    const methodLabels={
        standard:T('ทิ้งตามระเบียบ','Standard disposal'),
        incinerate:T('เผาทำลาย','Incinerate'),
        handover:T('ส่งหน่วยกำจัด','Hand to disposal unit'),
        other:T('อื่นๆ','Other'),
    };

    const itemsHtml=d.items.map(it=>{
        const r=it.r;
        const qty=+(r.current_quantity??r.remaining_qty??r.quantity??0);
        const unit=esc(r.quantity_unit||r.unit||'');
        return `<div class="dspCf-item">
            <div class="dspCf-item-dot"></div>
            <div class="dspCf-item-name" title="${esc(r.chemical_name||r.name||'')}">${esc(r.chemical_name||r.name||'—')}</div>
            <div class="dspCf-item-qty">${qty} ${unit}</div>
        </div>`;
    }).join('');

    document.getElementById('dspCfBox').innerHTML=`
        <div class="dspCf-hdr">
            <div class="dspCf-ic"><i class="fas fa-trash-alt"></i></div>
            <div class="dspCf-title">${T('ยืนยันการจำหน่ายสาร','Confirm Disposal')}</div>
            <div class="dspCf-sub">${T(`${d.items.length} รายการ จะถูกนำออกจากระบบ`,`${d.items.length} item(s) will be removed from the system`)}</div>
        </div>
        <div class="dspCf-body">
            <div class="dspCf-list">${itemsHtml}</div>
            <div class="dspCf-meta">
                <div class="dspCf-chip"><i class="fas fa-tag" style="color:#dc2626"></i>${reasonLabels[d.reason]||d.reason}</div>
                <div class="dspCf-chip"><i class="fas fa-recycle" style="color:#64748b"></i>${methodLabels[d.method]||d.method}</div>
            </div>
            <div class="dspCf-warn">
                <i class="fas fa-exclamation-triangle"></i>
                ${T('การดำเนินการนี้ไม่สามารถย้อนกลับได้ สารจะถูกตัดออกจากระบบและบันทึกลง disposal log',
                   'This action cannot be undone. Items will be removed from the system and logged.')}
            </div>
        </div>
        <div class="dspCf-footer">
            <button class="dspCf-btn-ok" onclick="_execDispose()">
                <i class="fas fa-trash-alt"></i> ${T('จำหน่ายออก — ยืนยัน','Dispose — Confirm')}
            </button>
            <button class="dspCf-btn-cancel" onclick="_closeDspCf()">${T('ยกเลิก','Cancel')}</button>
        </div>`;

    document.getElementById('dspCfOv').classList.add('show');
}

function _closeDspCf(){
    document.getElementById('dspCfOv').classList.remove('show');
}

async function _execDispose(){
    const d=_dspPending;
    if(!d) return;

    _closeDspCf();
    closeDispose();

    let done=0, failed=0;
    const errs=[];

    for(const it of d.items){
        const r=it.r;
        const sourceType=(r.source==='stock'||+r.id<0)?'stock':'container';
        const sourceId=sourceType==='stock'?Math.abs(+r.id):+r.id;
        try{
            const body={source_type:sourceType,source_id:sourceId,disposal_reason:d.reason,disposal_method:d.method};
            const res=await apiFetch('/v1/api/borrow.php?action=dispose',{method:'POST',body:JSON.stringify(body)});
            if(res&&res.success) done++;
            else throw new Error(res&&res.error?res.error:'Failed');
        }catch(e){
            failed++;
            errs.push(esc(r.chemical_name||r.name||'?')+': '+(e.message||'error'));
        }
    }

    _dspPending=null;

    if(done>0&&failed===0){
        toast(T(`✅ จำหน่ายออกสำเร็จ ${done} รายการ`,`✅ Disposed ${done} item(s) successfully`),'ok');
    }else if(done>0&&failed>0){
        toast(T(`⚠️ สำเร็จ ${done} / ล้มเหลว ${failed} รายการ`,`⚠️ Done ${done}, failed ${failed}`),'warn');
    }else{
        toast(T(`❌ จำหน่ายล้มเหลว: ${errs[0]||''}`,`❌ Disposal failed: ${errs[0]||''}`),'err');
    }

    if(done>0){clearSelection();loadData();}
}

/* ═════════════════════════════════════════
   EXPORT DROPDOWN
   ═════════════════════════════════════════ */
function toggleExportMenu(e){
    e.stopPropagation();
    const m=document.getElementById('exportMenu');m.classList.toggle('show');
    const close=()=>{m.classList.remove('show');document.removeEventListener('click',close)};
    if(m.classList.contains('show'))setTimeout(()=>document.addEventListener('click',close),10);
}

/* ═════════════════════════════════════════
   EXPORT FUNCTIONS
   ═════════════════════════════════════════ */
function doExport(format){
    document.getElementById('exportMenu').classList.remove('show');
    if(format==='csv'){
        const s=document.getElementById('searchInput').value.trim();
        const a=document.createElement('a');
        a.href='/v1/api/containers.php?action=export&search='+encodeURIComponent(s);
        a.target='_blank';document.body.appendChild(a);a.click();a.remove();
        toast(T('📥 กำลังดาวน์โหลด CSV...','📥 Downloading CSV...'),'ok');
    }else if(format==='pdf_report'){
        generatePDFReport();
    }
}

function generatePDFReport(){
    const items=DATA;
    if(!items.length){toast(T('ไม่มีข้อมูลสำหรับรายงาน','No data for report'),'err');return}
    
    const now=new Date().toLocaleString(L==='th'?'th-TH':'en-US');
    const title=T('รายงานสรุปคลังสารเคมี','Chemical Stock Summary Report');
    
    let html=`<html><head><meta charset="utf-8"><title>${title}</title>
    <style>
    *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Sarabun',sans-serif;padding:20px;font-size:11px;color:#333}
    .rpt-hdr{text-align:center;border-bottom:3px solid #065f46;padding-bottom:12px;margin-bottom:16px}
    .rpt-hdr h1{font-size:18px;color:#065f46;font-weight:800}.rpt-hdr p{font-size:10px;color:#666;margin-top:4px}
    .rpt-stats{display:flex;gap:12px;justify-content:center;margin-bottom:16px}
    .rpt-stat{text-align:center;padding:8px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px}
    .rpt-stat .v{font-size:20px;font-weight:800;color:#065f46}.rpt-stat .l{font-size:9px;color:#666;text-transform:uppercase}
    table{width:100%;border-collapse:collapse;font-size:10px;margin-top:10px}
    th{background:#065f46;color:#fff;padding:6px 8px;text-align:left;font-weight:700;font-size:9px;text-transform:uppercase;letter-spacing:.3px}
    td{padding:5px 8px;border-bottom:1px solid #e2e8f0}tr:nth-child(even){background:#f8fafc}
    .badge{padding:2px 6px;border-radius:4px;font-size:8px;font-weight:700;text-transform:uppercase}
    .b-active{background:#dcfce7;color:#15803d}.b-empty{background:#fee2e2;color:#dc2626}.b-expired{background:#fce7f3;color:#be185d}
    .bar{width:50px;height:5px;background:#e2e8f0;border-radius:3px;display:inline-block;vertical-align:middle;overflow:hidden}
    .bar div{height:100%;border-radius:3px}
    .hz{font-size:8px;color:#dc2626}.footer{text-align:center;margin-top:20px;font-size:8px;color:#999;border-top:1px solid #e2e8f0;padding-top:8px}
    @media print{@page{size:A4 landscape;margin:10mm}}
    </style></head><body>
    <div class="rpt-hdr">
        <h1>🧪 ${title}</h1>
        <p>${T('วันที่พิมพ์','Printed')}: ${now} | ${T('จำนวน','Count')}: ${items.length} ${T('รายการ','items')} | ${T('ผู้จัดทำ','By')}: ${USER_NAME}</p>
    </div>`;
    
    if(STATS){
        html+=`<div class="rpt-stats">
            <div class="rpt-stat"><div class="v">${num(STATS.total)}</div><div class="l">${T('ทั้งหมด','Total')}</div></div>
            <div class="rpt-stat"><div class="v">${num(STATS.active)}</div><div class="l">${T('ปกติ','Active')}</div></div>
            <div class="rpt-stat"><div class="v">${num(STATS.low)}</div><div class="l">${T('เหลือน้อย','Low')}</div></div>
            <div class="rpt-stat"><div class="v">${num(STATS.expiring_soon)}</div><div class="l">${T('ใกล้หมดอายุ','Expiring')}</div></div>
        </div>`;
    }
    
    html+=`<table><thead><tr>
        <th>#</th><th>${T('รหัสขวด','Code')}</th><th>${T('สารเคมี','Chemical')}</th><th>CAS</th>
        <th>${T('ประเภท','Type')}</th><th>${T('ปริมาณ','Qty')}</th><th>%</th>
        <th>${T('สถานะ','Status')}</th><th>${T('อันตราย','Hazard')}</th>
        <th>${T('เจ้าของ','Owner')}</th><th>${T('ตำแหน่ง','Location')}</th>
    </tr></thead><tbody>`;
    
    items.forEach((r,i)=>{
        const p=parseFloat(r.remaining_percentage)||0;
        const bc=p>50?'#22c55e':p>15?'#eab308':'#ef4444';
        const haz=(r.hazard_pictograms||[]).join(', ');
        const sc={active:'b-active',empty:'b-empty',expired:'b-expired'}[r.status]||'b-active';
        html+=`<tr>
            <td>${i+1}</td>
            <td style="font-family:monospace;font-size:9px">${esc(r.bottle_code||'')}</td>
            <td style="font-weight:600">${esc(r.chemical_name||'-')}</td>
            <td>${esc(r.cas_number||'')}</td>
            <td>${r.container_type||'-'}</td>
            <td>${r.current_quantity||0}/${r.initial_quantity||0} ${esc(r.quantity_unit||'')}</td>
            <td><div class="bar"><div style="width:${p}%;background:${bc}"></div></div> ${p.toFixed(0)}%</td>
            <td><span class="badge ${sc}">${r.status||'active'}</span></td>
            <td class="hz">${haz||'—'}</td>
            <td>${esc(r.owner_name||'-')}</td>
            <td>${esc(r.location_text||'-')}</td>
        </tr>`;
    });
    
    html+=`</tbody></table>
    <div class="footer">SUT chemBot — ${T('ระบบจัดการคลังสารเคมี','Chemical Inventory Management System')} | ${now}</div>
    </body></html>`;
    
    const w=window.open('','_blank','width=1100,height=800');
    w.document.write(html);w.document.close();
    setTimeout(()=>w.print(),500);
    toast(T('📊 กำลังสร้างรายงาน...','📊 Generating report...'),'ok');
}

/* ═════════════════════════════════════════
   LABEL GENERATION
   ═════════════════════════════════════════ */
function generateLabelHtml(r){
    const cfg=getPrintSettings();
    const p=parseFloat(r.remaining_percentage)||0;
    const haz=(r.hazard_pictograms||[]);
    const now=new Date().toLocaleDateString(L==='th'?'th-TH':'en-US',{day:'numeric',month:'short',year:'2-digit'});
    const pColor=pctColor(p);

    // ── GHS diamonds ──
    const ghsHtml=haz.length && cfg.showGHS
        ? '<div class="stk-label-ghsstrip">'+haz.slice(0,7).map(hp=>
            `<div class="stk-label-ghs" title="${hp}"><div class="stk-label-ghs-inner" style="border-color:${ghsTinyColors[hp]||'#dc2626'};color:${ghsTinyColors[hp]||'#dc2626'}"><i class="fas ${ghsTinyIcons[hp]||'fa-exclamation'}"></i></div></div>`
          ).join('')+'</div>'
        : '';

    // ── Signal word ──
    let sigClass='', sigText='';
    if(r.signal_word==='Danger'||r.signal_word==='danger'){sigClass='danger';sigText='⚠ DANGER';}
    else if(r.signal_word){sigClass='warning';sigText='⚡ WARNING';}
    const signalHtml=sigText?`<div class="stk-label-signal ${sigClass}">${sigText}</div>`:'';
    const hazRowHtml=signalHtml||ghsHtml
        ? `<div class="stk-label-hazrow">${signalHtml}${ghsHtml}</div>`
        : '';

    // ── Formula + CAS + MW ──
    const fmlParts=[];
    if(r.molecular_formula) fmlParts.push(`<span class="lf-pill">${esc(r.molecular_formula)}</span>`);
    if(r.cas_number)         fmlParts.push(`<span class="lf-sep">·</span><span>CAS: <b>${esc(r.cas_number)}</b></span>`);
    if(r.molecular_weight)   fmlParts.push(`<span class="lf-sep">·</span><span>MW: <b>${esc(String(r.molecular_weight))}</b> g/mol</span>`);
    const formulaHtml=fmlParts.length?`<div class="stk-label-formula">${fmlParts.join('')}</div>`:'';

    // ── Props ──
    const props=[];
    if(r.container_type) props.push(`<span class="stk-label-prop"><b>${T('ภาชนะ','Type')}</b> ${esc(r.container_type)}</span>`);
    if(r.grade)          props.push(`<span class="stk-label-prop"><b>${T('เกรด','Grade')}</b> ${esc(r.grade)}</span>`);
    if(r.physical_state) props.push(`<span class="stk-label-prop"><b>${T('สถานะ','State')}</b> ${esc(r.physical_state)}</span>`);
    if(r.container_size) props.push(`<span class="stk-label-prop"><b>${T('ขนาด','Size')}</b> ${esc(String(r.container_size))} ${esc(r.quantity_unit||'')}</span>`);
    const propsHtml=props.length?`<div class="stk-label-props">${props.join('')}</div>`:'';

    // ── Quantity bar ──
    const qtyText=`${r.current_quantity||0} / ${r.initial_quantity||0} ${esc(r.quantity_unit||'')}`;
    const qtyRowHtml=`<div class="stk-label-qtyrow">
        <span class="stk-label-qty">${qtyText}</span>
        <div class="stk-label-pbar"><div class="stk-label-pfill" style="width:${p}%;background:${pColor}"></div></div>
        <span class="stk-label-pct" style="color:${pColor}">${p.toFixed(0)}%</span>
    </div>`;

    // ── Expiry ──
    let expClass='nodate', expText=T('ไม่ระบุวันหมดอายุ','No expiry');
    if(r.expiry_date){
        const days=Math.round((new Date(r.expiry_date)-new Date())/86400000);
        expClass=days<0?'danger':days<90?'warn':'fresh';
        expText=(days<0?'⚠ ':'')+(L==='th'?'หมดอายุ':'Exp')+': '+fmtDate(r.expiry_date)+(days>=0&&days<90?` (${days}d)`:'');
    }
    const metaHtml=`<div class="stk-label-metarow">
        <span class="stk-label-exp ${expClass}">${expText}</span>
        ${r.owner_name?`<span class="stk-label-owner"><i class="fas fa-user"></i>${esc(r.owner_name)}</span>`:''}
    </div>`;

    // ── Batch/Lot ──
    const batchParts=[];
    if(r.batch_number) batchParts.push('Batch: '+esc(r.batch_number));
    if(r.lot_number)   batchParts.push('Lot: '+esc(r.lot_number));
    if(r.lab_name)     batchParts.push(esc(r.lab_name));
    const batchHtml=batchParts.length?`<div class="stk-label-batch">${batchParts.join(' · ')}</div>`:'';

    // ── Location ──
    const locText=cfg.showLoc?(r.location_text||r.building_name||''):r.lab_name||'';

    // ── Codes ──
    const showCodes=cfg.showQR||cfg.showBar;
    const codesHtml=showCodes?`<div class="stk-label-codes">
        ${cfg.showQR?`<div class="stk-label-qr" id="qrLabel_${r.id}"></div>`:''}
        ${cfg.showBar?`<div class="stk-label-barcode">
            <svg id="barcode_${r.id}"></svg>
            <div class="stk-label-barcode-text">${esc(r.bottle_code||r.qr_code||'ID'+r.id)}</div>
        </div>`:''}
    </div>`:'';

    return `<div class="stk-label" data-id="${r.id}">
        <div class="stk-label-header">
            <div class="stk-label-logo"><i class="fas fa-flask"></i></div>
            <div class="stk-label-hinfo">
                <div class="stk-label-htitle">SUT chemBot · ${T('คลังสารเคมี','Chemical Stock')}</div>
                ${locText?`<div class="stk-label-hloc"><i class="fas fa-map-marker-alt" style="font-size:5px;margin-right:2px"></i>${esc(locText)}</div>`:''}
            </div>
            ${r.has_3d?'<div class="stk-label-ar"><i class="fas fa-cube"></i> AR·3D</div>':''}
        </div>
        ${hazRowHtml}
        <div class="stk-label-body">
            <div class="stk-label-chem">${esc(r.chemical_name||'-')}</div>
            ${formulaHtml}
            ${propsHtml}
            ${qtyRowHtml}
            ${metaHtml}
            ${batchHtml}
        </div>
        ${codesHtml}
        <div class="stk-label-footer">
            <span><i class="fas fa-calendar-alt" style="margin-right:2px;opacity:.5"></i>${now}</span>
            <span style="font-family:monospace">ID:${r.id}</span>
            <span><i class="fas fa-qrcode" style="margin-right:2px;opacity:.5"></i>${T('แสกน QR → AR/3D','Scan QR → AR/3D')}</span>
        </div>
    </div>`;
}

async function doPrintLabels(mode){
    document.getElementById('exportMenu').classList.remove('show');
    let items=[];
    
    if(mode==='selected'||mode==='qr_selected'){
        if(SELECTED.size===0){
            // Fallback: if nothing selected, use all visible data
            if(DATA.length){items=[...DATA];}
            else{toast(T('กรุณาเลือกรายการก่อน','Please select items first'),'err');return}
        }else{
            items=DATA.filter(r=>SELECTED.has(+r.id));
            // Safety fallback if filter returned empty (type mismatch edge case)
            if(!items.length) items=[...DATA];
        }
    }else if(mode==='all'||mode==='qr_sheet'){
        items=[...DATA];
    }

    if(!items.length){
        toast(T('ไม่มีข้อมูลในหน้านี้','No items on this page'),'err');
        return;
    }
    
    if(mode==='qr_sheet'||mode==='qr_selected'){
        await generateQRSheet(items);return;
    }
    
    // Full label mode — fetch detail for each item
    const labelOv=document.getElementById('labelOv');
    const labelContent=document.getElementById('labelContent');
    labelOv.classList.add('show');
    labelContent.innerHTML='<div style="text-align:center;padding:40px;color:var(--c3)"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin-top:8px">'+T('กำลังสร้างฉลาก...','Generating labels...')+'</p></div>';
    
    try{
        // Fetch full details for each item to get signal_word etc
        const detailedItems=[];
        for(const item of items){
            try{
                const d=await apiFetch('/v1/api/containers.php?action=detail&id='+item.id);
                if(d.success){
                    const det=d.data;
                    det.has_3d=!!(det.ar_data&&det.ar_data.has_model);
                    det.hazard_pictograms=det.hazard_pictograms||[];
                    det.location_text=det.location_text||item.location_text;
                    det.is_mine=item.is_mine;
                    detailedItems.push(det);
                }else{
                    detailedItems.push(item);
                }
            }catch(e){detailedItems.push(item)}
        }
        
        const cfg=getPrintSettings();
        let h=`<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;background:#f8fafc;border-radius:10px;padding:10px 14px;border:1px solid #e2e8f0">
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:800;color:#1e293b"><i class="fas fa-tag" style="color:var(--accent);margin-right:5px"></i>${detailedItems.length} ${T('ฉลาก','Labels')}</div>
                <div style="font-size:10.5px;color:#64748b;margin-top:1px"><i class="fas fa-ruler-combined" style="margin-right:3px"></i>${cfg.labelW}×${cfg.labelH}mm · ${cfg.printerType==='thermal'?T('Thermal','Thermal'):T('Sheet','Sheet')} · ${cfg.cols} col${cfg.cols>1?'s':''}</div>
            </div>
            <button class="stk-btn stk-btn-g" onclick="openPrintSettings()" style="font-size:11px;padding:6px 10px"><i class="fas fa-sliders-h"></i> ${T('ตั้งค่า','Settings')}</button>
            <button class="stk-btn stk-btn-p" onclick="printLabels()"><i class="fas fa-print"></i> ${T('พิมพ์ทั้งหมด','Print All')}</button>
            <button class="stk-btn stk-btn-g" onclick="closeLabelModal()"><i class="fas fa-times"></i></button>
        </div>`;
        h+='<div id="labelGrid" style="display:flex;flex-wrap:wrap;gap:16px;justify-content:flex-start">';
        detailedItems.forEach(r=>{h+=generateLabelHtml(r)});
        h+='</div>';
        labelContent.innerHTML=h;
        
        // Render QR codes and barcodes after DOM update
        await loadExternalLibs();
        setTimeout(()=>{
            detailedItems.forEach(r=>{
                renderQRCode('qrLabel_'+r.id, window.location.origin+'/v1/ar/view_ar.php?id='+r.id, 70);
                renderBarcode('barcode_'+r.id, r.bottle_code||('ID'+r.id));
            });
        },200);
    }catch(e){
        labelContent.innerHTML=`<div class="stk-empty"><i class="fas fa-exclamation-triangle"></i><p>${e.message}</p></div>`;
    }
}

async function generateQRSheet(items){
    const labelOv=document.getElementById('labelOv');
    const labelContent=document.getElementById('labelContent');
    labelOv.classList.add('show');
    labelContent.innerHTML='<div style="text-align:center;padding:40px;color:var(--c3)"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    
    await loadExternalLibs();
    
    let h=`<div style="display:flex;gap:8px;margin-bottom:16px;align-items:center">
        <span style="font-size:13px;font-weight:700"><i class="fas fa-qrcode" style="color:var(--accent)"></i> ${items.length} QR Codes</span>
        <button class="stk-btn stk-btn-p" onclick="printLabels()"><i class="fas fa-print"></i> ${T('พิมพ์','Print')}</button>
        <button class="stk-btn stk-btn-g" onclick="closeLabelModal()"><i class="fas fa-times"></i> ${T('ปิด','Close')}</button>
    </div>`;
    h+='<div id="labelGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">';
    items.forEach(r=>{
        h+=`<div style="background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:12px;text-align:center">
            <div id="qrSheet_${r.id}" style="width:100px;height:100px;margin:0 auto 6px"></div>
            <div style="font-size:9px;font-weight:700;color:var(--c1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:2px">${esc(r.chemical_name||'-')}</div>
            <div style="font-family:'Courier New',monospace;font-size:8px;color:var(--c3);letter-spacing:0.3px">${esc(r.bottle_code||'')}</div>
            <div style="margin-top:4px"><svg id="qrBar_${r.id}"></svg></div>
        </div>`;
    });
    h+='</div>';
    labelContent.innerHTML=h;
    
    setTimeout(()=>{
        items.forEach(r=>{
            renderQRCode('qrSheet_'+r.id, window.location.origin+'/v1/ar/view_ar.php?id='+r.id, 90);
            renderBarcode('qrBar_'+r.id, r.bottle_code||('ID'+r.id), {height:25,fontSize:0,width:1});
        });
    },200);
}

function printLabels(){
    const grid=document.getElementById('labelGrid');
    if(!grid)return;
    const cfg=getPrintSettings();
    const isThermal=cfg.printerType==='thermal';
    const cols=isThermal?1:cfg.cols;
    const pageW=isThermal?cfg.labelW:210, pageH=isThermal?cfg.labelH:297;
    const pageMargin=isThermal?'0':'8mm';
    const colGap=isThermal?0:4;
    const w=window.open('','_blank','width=900,height=700');
    const codeH=Math.round(cfg.labelH*0.28);
    w.document.write(`<html><head><meta charset="utf-8"><title>${T('ฉลากสารเคมี','Chemical Labels')}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{padding:${isThermal?'0':'6mm'};font-family:Arial,Helvetica,sans-serif;background:#fff;color:#000}
    .grid{display:grid;grid-template-columns:repeat(${cols},${cfg.labelW}mm);gap:${colGap}mm}
    /* ── Label container ── */
    .stk-label{width:${cfg.labelW}mm;height:${cfg.labelH}mm;border:0.4mm solid #1e293b;background:#fff;break-inside:avoid;page-break-inside:avoid;overflow:hidden;box-sizing:border-box;display:flex;flex-direction:column;font-family:Arial,Helvetica,sans-serif}
    /* ── Header ── */
    .stk-label-header{background:linear-gradient(135deg,#1e293b,#334155);display:flex;align-items:center;padding:0.7mm 1.5mm;gap:1mm;flex-shrink:0}
    .stk-label-logo{width:4mm;height:4mm;background:linear-gradient(135deg,#10b981,#059669);border-radius:0.8mm;display:flex;align-items:center;justify-content:center;color:#fff;font-size:2mm;flex-shrink:0}
    .stk-label-hinfo{flex:1;overflow:hidden}
    .stk-label-htitle{font-size:1.8mm;font-weight:800;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .stk-label-hloc{font-size:1.4mm;color:rgba(255,255,255,.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:0.2mm;${cfg.showLoc?'':'display:none'}}
    .stk-label-ar{background:#6d28d9;color:#fff;font-size:1.2mm;font-weight:800;padding:0.2mm 0.8mm;border-radius:0.5mm;flex-shrink:0}
    /* ── Hazard row ── */
    .stk-label-hazrow{display:flex;align-items:center;gap:1mm;padding:0.6mm 1.5mm;background:#fef2f2;border-bottom:0.2mm solid #fecaca;flex-shrink:0;flex-wrap:wrap;min-height:4mm}
    .stk-label-signal{font-size:1.7mm;font-weight:900;padding:0.2mm 0.9mm;border-radius:0.4mm;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}
    .stk-label-signal.danger{background:#dc2626;color:#fff}
    .stk-label-signal.warning{background:#f59e0b;color:#000}
    .stk-label-ghsstrip{display:${cfg.showGHS?'flex':'none'};gap:0.7mm;flex-wrap:wrap;align-items:center}
    .stk-label-ghs{width:3.5mm;height:3.5mm;position:relative;flex-shrink:0}
    .stk-label-ghs-inner{position:absolute;inset:0.3mm;transform:rotate(45deg);border:0.35mm solid #dc2626;border-radius:0.3mm;display:flex;align-items:center;justify-content:center}
    .stk-label-ghs-inner i{transform:rotate(-45deg);font-size:1.2mm}
    /* ── Body ── */
    .stk-label-body{padding:1mm 1.5mm 0.5mm;flex:1;overflow:hidden;display:flex;flex-direction:column}
    .stk-label-chem{font-size:3.8mm;font-weight:900;color:#0f172a;line-height:1.15;margin-bottom:0.4mm;overflow:hidden}
    .stk-label-formula{font-size:1.5mm;color:#475569;margin-bottom:0.7mm;display:flex;flex-wrap:wrap;gap:0.7mm;align-items:center}
    .stk-label-formula .lf-pill{background:#f1f5f9;border:0.2mm solid #e2e8f0;border-radius:0.5mm;padding:0.2mm 0.7mm;font-family:'Courier New',monospace;font-size:1.4mm;color:#334155;font-weight:700}
    .stk-label-formula .lf-sep{color:#cbd5e1}
    .stk-label-props{display:flex;flex-wrap:wrap;gap:0.5mm;margin-bottom:0.6mm}
    .stk-label-prop{background:#f8fafc;border:0.2mm solid #e2e8f0;border-radius:0.4mm;padding:0.15mm 0.7mm;font-size:1.4mm;color:#64748b}
    .stk-label-prop b{color:#1e293b;font-weight:700}
    .stk-label-qtyrow{display:flex;align-items:center;gap:1mm;margin-bottom:0.5mm}
    .stk-label-qty{font-size:1.7mm;font-weight:800;color:#1e293b;white-space:nowrap}
    .stk-label-pbar{flex:1;height:1.2mm;background:#e2e8f0;border-radius:0.6mm;overflow:hidden}
    .stk-label-pfill{height:100%;border-radius:0.6mm}
    .stk-label-pct{font-size:1.7mm;font-weight:900;min-width:4.5mm;text-align:right}
    .stk-label-metarow{display:flex;align-items:center;gap:1.5mm;flex-wrap:wrap;margin-bottom:0.4mm}
    .stk-label-exp{font-size:1.4mm;font-weight:700;padding:0.2mm 0.9mm;border-radius:0.4mm}
    .stk-label-exp.fresh{background:#f0fdf4;color:#15803d;border:0.2mm solid #bbf7d0}
    .stk-label-exp.warn{background:#fef3c7;color:#b45309;border:0.2mm solid #fde68a}
    .stk-label-exp.danger{background:#fef2f2;color:#dc2626;border:0.2mm solid #fecaca}
    .stk-label-exp.nodate{background:#f1f5f9;color:#94a3b8;border:0.2mm solid #e2e8f0}
    .stk-label-owner{font-size:1.4mm;color:#475569}
    .stk-label-batch{font-size:1.3mm;color:#94a3b8;font-family:'Courier New',monospace;margin-top:auto;padding-top:0.3mm}
    /* ── Codes ── */
    .stk-label-codes{display:${cfg.showQR||cfg.showBar?'flex':'none'};align-items:stretch;border-top:0.3mm solid #e2e8f0;background:#fafafa;flex-shrink:0;height:${codeH}mm;overflow:hidden}
    .stk-label-qr{width:${codeH}mm;height:${codeH}mm;flex-shrink:0;display:${cfg.showQR?'flex':'none'};align-items:center;justify-content:center;border-right:0.3mm solid #e2e8f0;padding:0.5mm;background:#fff}
    .stk-label-qr canvas,.stk-label-qr img,.stk-label-qr svg{max-width:100%;max-height:100%}
    .stk-label-barcode{flex:1;display:${cfg.showBar?'flex':'none'};flex-direction:column;align-items:center;justify-content:center;padding:0.5mm 1mm;overflow:hidden}
    .stk-label-barcode svg{max-width:100%;height:${Math.round(cfg.labelH*0.16)}mm!important}
    .stk-label-barcode-text{font-size:1.3mm;letter-spacing:.3px;color:#334155;font-family:'Courier New',monospace;font-weight:700;margin-top:0.3mm}
    /* ── Footer ── */
    .stk-label-footer{display:flex;justify-content:space-between;align-items:center;padding:0.5mm 1.5mm;background:#f8fafc;border-top:0.3mm dashed #cbd5e1;font-size:1.3mm;color:#94a3b8;flex-shrink:0}
    @page{size:${pageW}mm ${pageH}mm;margin:${pageMargin}}
    @media print{body{padding:0}.stk-label{border-color:#000}}
    </style></head><body><div class="grid">`+grid.innerHTML+`</div></body></html>`);
    w.document.close();
    setTimeout(()=>w.print(),800);
}

function closeLabelModal(){document.getElementById('labelOv').classList.remove('show')}
function closeQrModal(){document.getElementById('qrOv').classList.remove('show')}

/* ═════════════════════════════════════════
   PRINT SETTINGS
   ═════════════════════════════════════════ */
const PS_KEY = 'sut_print_settings_v2';
const PS_DEFAULTS = {
    printerType: 'thermal',
    labelW: 60, labelH: 40,
    cols: 1,
    showQR: true, showBar: true, showGHS: true, showLoc: true
};

let PS = {...PS_DEFAULTS};

function psLoad(){
    try{ const s=localStorage.getItem(PS_KEY); if(s) PS={...PS_DEFAULTS,...JSON.parse(s)}; }catch(e){}
}
psLoad();

function psSave(){
    localStorage.setItem(PS_KEY, JSON.stringify(PS));
    const badge=document.getElementById('psAutoSaveBadge');
    if(badge){badge.style.display='';clearTimeout(badge._t);badge._t=setTimeout(()=>badge.style.display='none',2000);}
}

function openPrintSettings(){
    psLoad();
    const ov=document.getElementById('psOv');
    ov.classList.add('show');
    psApplyToUI();
    psUpdatePreview();
}
function closePrintSettings(){ document.getElementById('psOv').classList.remove('show'); }

function psApplyToUI(){
    // Printer
    document.querySelectorAll('#psLeft .ps-printer').forEach(el=>{
        el.classList.toggle('active', el.dataset.ptype===PS.printerType);
    });
    // Dims
    document.getElementById('psW').value = PS.labelW;
    document.getElementById('psH').value = PS.labelH;
    // Presets
    document.querySelectorAll('#psPresets .ps-preset').forEach(el=>{
        el.classList.toggle('active', parseInt(el.dataset.w)===PS.labelW && parseInt(el.dataset.h)===PS.labelH);
    });
    // Cols
    document.querySelectorAll('#psColsRow .ps-col-opt').forEach(el=>{
        el.classList.toggle('active', parseInt(el.dataset.cols)===PS.cols);
    });
    // Toggles
    document.getElementById('psOptQR').checked  = PS.showQR;
    document.getElementById('psOptBar').checked = PS.showBar;
    document.getElementById('psOptGHS').checked = PS.showGHS;
    document.getElementById('psOptLoc').checked = PS.showLoc;
    // Cols section visibility
    document.getElementById('psColsSection').style.display = PS.printerType==='thermal' ? 'none' : '';
}

function psPrinterSelect(el){
    document.querySelectorAll('#psLeft .ps-printer').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    PS.printerType = el.dataset.ptype;
    // Set default preset for type
    if(PS.printerType==='thermal'){ PS.labelW=60; PS.labelH=40; PS.cols=1; }
    else { PS.labelW=105; PS.labelH=74; PS.cols=2; }
    psApplyToUI(); psUpdatePreview();
}

function psPresetSelect(el){
    document.querySelectorAll('#psPresets .ps-preset').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    PS.labelW = parseInt(el.dataset.w);
    PS.labelH = parseInt(el.dataset.h);
    document.getElementById('psW').value = PS.labelW;
    document.getElementById('psH').value = PS.labelH;
    // auto-cols for A4
    if(PS.labelW>=200){ PS.cols=2; psApplyToUI(); }
    document.getElementById('psSizeBadge').textContent = PS.labelW+' × '+PS.labelH+' mm';
    psUpdatePreview();
}

function psOnDimChange(){
    const w=parseInt(document.getElementById('psW').value)||60;
    const h=parseInt(document.getElementById('psH').value)||40;
    PS.labelW=w; PS.labelH=h;
    // Clear preset selection
    document.querySelectorAll('#psPresets .ps-preset').forEach(p=>{
        p.classList.toggle('active', parseInt(p.dataset.w)===w && parseInt(p.dataset.h)===h);
    });
    document.getElementById('psSizeBadge').textContent = w+' × '+h+' mm';
    psUpdatePreview();
}

function psColSelect(el){
    document.querySelectorAll('#psColsRow .ps-col-opt').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    PS.cols=parseInt(el.dataset.cols);
    psUpdatePreview();
}

function psReadToggles(){
    PS.showQR  = document.getElementById('psOptQR').checked;
    PS.showBar = document.getElementById('psOptBar').checked;
    PS.showGHS = document.getElementById('psOptGHS').checked;
    PS.showLoc = document.getElementById('psOptLoc').checked;
}

/* ─── Live preview builder ─── */
const PX_PER_MM = 3.2; // screen px per mm for preview (96dpi ≈ 3.78, use 3.2 for fit)

function psUpdatePreview(){
    psReadToggles();
    const w=PS.labelW, h=PS.labelH;
    const pxW=Math.round(w*PX_PER_MM), pxH=Math.round(h*PX_PER_MM);
    document.getElementById('psSizeBadge').textContent = w+' × '+h+' mm';

    const area=document.getElementById('psPreviewArea');
    const rulerStep = w<=60 ? 10 : w<=120 ? 20 : 50;
    const rulerStepH = h<=60 ? 10 : h<=120 ? 20 : 50;

    // Build ruler HTML
    let rulerTopTicks=''; let rulerLeftTicks='';
    for(let mm=0; mm<=w; mm+=rulerStep){
        const x=Math.round(mm*PX_PER_MM);
        rulerTopTicks+=`<div class="ps-ruler-label" style="left:${x+16}px;bottom:1px">${mm}</div>
        <div style="position:absolute;left:${x+16}px;bottom:0;width:1px;height:${mm%rulerStep===0?8:4}px;background:#94a3b8"></div>`;
    }
    for(let mm=0; mm<=h; mm+=rulerStepH){
        const y=Math.round(mm*PX_PER_MM);
        rulerLeftTicks+=`<div class="ps-ruler-label" style="top:${y+16}px;right:1px;transform:none;writing-mode:horizontal-tb">${mm}</div>
        <div style="position:absolute;top:${y+16}px;right:0;height:1px;width:${mm%rulerStepH===0?8:4}px;background:#94a3b8"></div>`;
    }

    // Build mini label HTML (scaled)
    const fs = Math.max(6, Math.round(pxW/12));
    const qrSz = PS.showQR ? Math.max(24, Math.round(pxH*0.28)) : 0;
    const barH = PS.showBar ? Math.max(14, Math.round(pxH*0.18)) : 0;
    const showCodes = PS.showQR || PS.showBar;
    const codesH = showCodes ? (Math.max(qrSz, barH) + Math.round(pxH*0.05)) : 0;
    const ghsH = PS.showGHS ? Math.round(pxH*0.12) : 0;
    const headerH = Math.round(pxH*0.2);
    const infoH = pxH - headerH - codesH - ghsH - Math.round(pxH*0.06);

    area.innerHTML=`
    <div style="font-size:10.5px;font-weight:700;color:#64748b;margin-bottom:8px;text-align:center">
        <i class="fas fa-ruler" style="color:#8b5cf6;margin-right:4px"></i>
        ${w} × ${h} mm · ${PS.printerType==='thermal'?'Thermal':'Sheet'} · ${PS.cols} col${PS.cols>1?'s':''}
    </div>
    <div style="position:relative">
        <!-- Top ruler -->
        <div style="position:relative;height:16px;width:${pxW+16}px;background:#f8fafc;border:1px solid #cbd5e1;border-bottom:none;margin-left:16px;overflow:visible;box-sizing:border-box">
            ${rulerTopTicks}
            <div style="position:absolute;left:0;top:0;font-size:7px;color:#94a3b8;padding:1px 2px">mm</div>
        </div>
        <div style="display:flex">
            <!-- Left ruler -->
            <div style="position:relative;width:16px;height:${pxH}px;background:#f8fafc;border:1px solid #cbd5e1;border-right:none;overflow:visible;flex-shrink:0">
                ${rulerLeftTicks}
            </div>
            <!-- Label canvas -->
            <div style="width:${pxW}px;height:${pxH}px;border:2px solid #334155;background:#fff;position:relative;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.12);flex-shrink:0">
                <!-- Header bar -->
                <div style="background:#1e293b;height:${headerH}px;display:flex;align-items:center;padding:0 ${Math.round(pxW*0.04)}px;gap:${Math.round(pxW*0.025)}px">
                    <div style="width:${Math.round(headerH*0.7)}px;height:${Math.round(headerH*0.7)}px;background:#10b981;border-radius:${Math.round(headerH*0.15)}px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="fas fa-flask" style="color:#fff;font-size:${Math.round(headerH*0.35)}px"></i>
                    </div>
                    <div style="flex:1;overflow:hidden">
                        <div style="font-size:${Math.max(5,Math.round(fs*0.7))}px;font-weight:800;color:#fff;white-space:nowrap;overflow:hidden">SUT ChemBot</div>
                        ${PS.showLoc?`<div style="font-size:${Math.max(4,Math.round(fs*0.55))}px;color:rgba(255,255,255,.6);white-space:nowrap;overflow:hidden">LAB-301 › Cabinet A</div>`:''}
                    </div>
                </div>
                <!-- Chemical name -->
                <div style="padding:${Math.round(pxH*0.03)}px ${Math.round(pxW*0.04)}px ${Math.round(pxH*0.01)}px">
                    <div style="font-size:${Math.max(7,Math.round(fs*1.1))}px;font-weight:900;color:#1e293b;line-height:1.2;overflow:hidden;max-height:${Math.round(infoH*0.45)}px">Sodium Chloride</div>
                    <div style="font-size:${Math.max(5,Math.round(fs*0.65))}px;color:#64748b;margin-top:${Math.round(pxH*0.01)}px">CAS: 7647-14-5 · NaCl</div>
                    ${PS.showGHS?`<div style="display:flex;gap:${Math.round(pxW*0.015)}px;margin-top:${Math.round(pxH*0.02)}px">
                        ${['#dc2626','#f59e0b','#2563eb'].map(c=>`<div style="width:${Math.round(ghsH*0.8)}px;height:${Math.round(ghsH*0.8)}px;border:${Math.max(1,Math.round(ghsH*0.06))}px solid ${c};border-radius:2px;transform:rotate(45deg);display:flex;align-items:center;justify-content:center"><i class="fas fa-exclamation" style="font-size:${Math.max(3,Math.round(ghsH*0.28))}px;color:${c};transform:rotate(-45deg)"></i></div>`).join('')}
                    </div>`:''}
                    <div style="font-size:${Math.max(5,Math.round(fs*0.6))}px;color:#64748b;margin-top:${Math.round(pxH*0.01)}px">
                        <span>Qty: <b style="color:#1e293b">500 / 500 mL</b></span>
                    </div>
                </div>
                <!-- Codes section -->
                ${showCodes?`<div style="position:absolute;bottom:0;left:0;right:0;height:${codesH}px;border-top:1px solid #e2e8f0;display:flex;align-items:center;gap:${Math.round(pxW*0.02)}px;padding:${Math.round(pxH*0.02)}px ${Math.round(pxW*0.03)}px;background:#fafafa">
                    ${PS.showQR?`<div style="width:${qrSz}px;height:${qrSz}px;background:#1e293b;display:flex;align-items:center;justify-content:center;flex-shrink:0;border-radius:2px">
                        <i class="fas fa-qrcode" style="color:#fff;font-size:${Math.round(qrSz*0.55)}px"></i>
                    </div>`:''}
                    ${PS.showBar?`<div style="flex:1;overflow:hidden;text-align:center">
                        <div style="background:repeating-linear-gradient(90deg,#1e293b 0,#1e293b 2px,transparent 2px,transparent 4px),repeating-linear-gradient(90deg,#1e293b 0,#1e293b 1px,transparent 1px,transparent 3px);height:${Math.round(barH*0.6)}px;width:100%"></div>
                        <div style="font-family:monospace;font-size:${Math.max(4,Math.round(fs*0.5))}px;margin-top:2px;color:#374151;letter-spacing:.3px;overflow:hidden;white-space:nowrap">F02212A6000028</div>
                    </div>`:''}
                </div>`:''}
                <!-- AR badge -->
                ${w>=60?`<div style="position:absolute;top:${headerH+2}px;right:${Math.round(pxW*0.025)}px;background:#6d28d9;color:#fff;font-size:${Math.max(4,Math.round(fs*0.45))}px;padding:1px 3px;border-radius:2px;font-weight:800">AR</div>`:''}
            </div>
        </div>
    </div>
    ${PS.printerType==='inkjet' && PS.cols>1 ? `
    <div style="margin-top:12px;padding:8px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:10.5px;color:#1d4ed8;display:flex;align-items:center;gap:7px">
        <i class="fas fa-th"></i> ${PS.cols} columns · ${Math.floor(297/h)} rows per A4 page = <strong>${PS.cols*Math.floor(297/h)}</strong> labels/page
    </div>`:''
    }`;
}

function psSaveSettings(){
    psReadToggles();
    psSave();
    closePrintSettings();
    toast(T('✅ บันทึกการตั้งค่าแล้ว','✅ Print settings saved'),'ok');
}

function psResetDefaults(){
    PS={...PS_DEFAULTS};
    psApplyToUI();
    psUpdatePreview();
}

function psTestPrint(){
    psReadToggles();
    const w=window.open('','_blank','width=700,height=500');
    const mmToPx = 3.7795; // 96dpi
    const pw=Math.round(PS.labelW*mmToPx), ph=Math.round(PS.labelH*mmToPx);
    const fs=Math.max(7,Math.round(pw/13));
    const qrSz=PS.showQR?Math.max(30,Math.round(ph*0.28)):0;
    const barH=PS.showBar?Math.max(18,Math.round(ph*0.18)):0;
    const showCodes=PS.showQR||PS.showBar;
    const codesH=showCodes?(Math.max(qrSz,barH)+6):0;
    const ghsH=PS.showGHS?Math.round(ph*0.11):0;
    const hdrH=Math.round(ph*0.2);
    const sampleHazards=['GHS01','GHS02','GHS06'];
    const ghsColors={'GHS01':'#dc2626','GHS02':'#f59e0b','GHS06':'#7c3aed'};
    const ghsIcons={'GHS01':'fa-bomb','GHS02':'fa-fire','GHS06':'fa-skull-crossbones'};

    w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
    <title>Test Print — ${PS.labelW}×${PS.labelH}mm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:Arial,sans-serif;background:#f1f5f9;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;gap:16px;padding:20px}
    .lbl{width:${pw}px;height:${ph}px;border:2px solid #1e293b;background:#fff;position:relative;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.15)}
    .lbl-hdr{background:#1e293b;height:${hdrH}px;display:flex;align-items:center;padding:0 ${Math.round(pw*0.04)}px;gap:${Math.round(pw*0.025)}px}
    .lbl-logo{width:${Math.round(hdrH*0.65)}px;height:${Math.round(hdrH*0.65)}px;background:#10b981;border-radius:${Math.round(hdrH*0.15)}px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .lbl-logo i{color:#fff;font-size:${Math.round(hdrH*0.35)}px}
    .lbl-htxt{flex:1;overflow:hidden}
    .lbl-title{font-size:${Math.max(5,Math.round(fs*0.7))}px;font-weight:800;color:#fff;white-space:nowrap}
    .lbl-sub{font-size:${Math.max(4,Math.round(fs*0.55))}px;color:rgba(255,255,255,.6);white-space:nowrap}
    .lbl-body{padding:${Math.round(ph*0.03)}px ${Math.round(pw*0.04)}px}
    .lbl-name{font-size:${Math.max(7,Math.round(fs*1.1))}px;font-weight:900;color:#1e293b;line-height:1.2}
    .lbl-cas{font-size:${Math.max(5,Math.round(fs*0.62))}px;color:#64748b;margin-top:1px}
    .lbl-ghs{display:flex;gap:${Math.round(pw*0.012)}px;margin-top:${Math.round(ph*0.02)}px}
    .ghs-sq{width:${Math.round(ghsH*0.75)}px;height:${Math.round(ghsH*0.75)}px;border-radius:2px;transform:rotate(45deg);display:flex;align-items:center;justify-content:center;border:${Math.max(1,Math.round(ghsH*0.06))}px solid}
    .ghs-sq i{font-size:${Math.max(3,Math.round(ghsH*0.28))}px;transform:rotate(-45deg)}
    .lbl-info{font-size:${Math.max(5,Math.round(fs*0.6))}px;color:#475569;margin-top:${Math.round(ph*0.01)}px}
    .lbl-signal{font-size:${Math.max(5,Math.round(fs*0.62))}px;font-weight:900;padding:1px ${Math.round(pw*0.015)}px;background:#dc2626;color:#fff;border-radius:2px;display:inline-block;margin-bottom:2px}
    .lbl-codes{position:absolute;bottom:0;left:0;right:0;height:${codesH}px;border-top:1.5px solid #e2e8f0;display:flex;align-items:center;gap:${Math.round(pw*0.025)}px;padding:${Math.round(ph*0.02)}px ${Math.round(pw*0.03)}px;background:#fafafa}
    .lbl-qr{width:${qrSz}px;height:${qrSz}px;background:#000;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .lbl-qr i{color:#fff;font-size:${Math.round(qrSz*0.55)}px}
    .lbl-bc{flex:1;text-align:center;overflow:hidden}
    .lbl-bc svg{max-width:100%;height:${Math.round(barH*0.65)}px!important}
    .lbl-bc-txt{font-family:'Courier New',monospace;font-size:${Math.max(4,Math.round(fs*0.48))}px;letter-spacing:.5px;margin-top:1px}
    .lbl-ar{position:absolute;top:${hdrH+2}px;right:${Math.round(pw*0.025)}px;background:#6d28d9;color:#fff;font-size:${Math.max(4,Math.round(fs*0.44))}px;padding:1px 3px;border-radius:2px;font-weight:800}
    .lbl-footer{position:absolute;bottom:${showCodes?codesH:0}px;left:0;right:0;border-top:1px dashed #cbd5e1;padding:1px ${Math.round(pw*0.03)}px;font-size:${Math.max(4,Math.round(fs*0.45))}px;color:#94a3b8;display:flex;justify-content:space-between;background:#fff}
    .info-box{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;font-size:12px;color:#374151;text-align:center;max-width:400px}
    .info-box h2{font-size:15px;font-weight:800;color:#1e293b;margin-bottom:6px}
    .info-box .dim{font-size:20px;font-weight:900;color:#10b981;margin:4px 0}
    .print-btn{background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;padding:11px 28px;border-radius:9px;font-size:13px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:8px;margin-top:8px}
    @media print{body{background:#fff;padding:${PS.printerType==='thermal'?'0':'5mm'}}
        .info-box,.print-btn{display:none}
        .lbl{box-shadow:none;border:1.5px solid #000}
        @page{size:${PS.labelW}mm ${PS.labelH}mm;margin:0}}
    </style></head><body>
    <div class="info-box">
        <h2><i class="fas fa-vial" style="color:#8b5cf6"></i> Test Print — ${T('ทดสอบการพิมพ์','Test Label')}</h2>
        <div class="dim">${PS.labelW} × ${PS.labelH} mm</div>
        <div style="font-size:11px;color:#64748b">${PS.printerType==='thermal'?'🖨 Thermal Printer':'🖨 Inkjet / Laser'} · ${PS.cols} col${PS.cols>1?'s':''}</div>
        <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> ${T('พิมพ์ทันที','Print Now')}</button>
    </div>
    <div class="lbl">
        <div class="lbl-ar"><i class="fas fa-cube"></i> AR</div>
        <div class="lbl-hdr">
            <div class="lbl-logo"><i class="fas fa-flask"></i></div>
            <div class="lbl-htxt">
                <div class="lbl-title">SUT ChemBot — Chemical Stock</div>
                ${PS.showLoc?'<div class="lbl-sub">LAB-301 › Shelf A › Slot 3</div>':''}
            </div>
        </div>
        <div class="lbl-body">
            <div class="lbl-signal">⚠ DANGER</div>
            <div class="lbl-name">Sodium Hydroxide (NaOH)</div>
            <div class="lbl-cas">CAS: 1310-73-2 · Na OH · MW: 40.00 g/mol</div>
            ${PS.showGHS?`<div class="lbl-ghs">
                ${sampleHazards.map(g=>`<div class="ghs-sq" style="border-color:${ghsColors[g]};color:${ghsColors[g]}"><i class="fas ${ghsIcons[g]}"></i></div>`).join('')}
            </div>`:''}
            <div class="lbl-info">Qty: <b>250 / 500 mL</b> · Rem: <b style="color:#f59e0b">50%</b> · Exp: <b>2026-12-31</b></div>
        </div>
        ${showCodes?`<div class="lbl-codes">
            ${PS.showQR?'<div class="lbl-qr"><i class="fas fa-qrcode"></i></div>':''}
            ${PS.showBar?`<div class="lbl-bc">
                <svg id="testBarcode"></svg>
                <div class="lbl-bc-txt">F02212A6000028</div>
            </div>`:''}
        </div>`:''}
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js" onload="try{JsBarcode('#testBarcode','F02212A6000028',{format:'CODE128',height:${Math.round(barH*0.5)},fontSize:0,width:1,margin:0,displayValue:false})}catch(e){}"><\/script>
    </body></html>`);
    w.document.close();
}

// Expose settings getter for use in generateLabelHtml / printLabels
function getPrintSettings(){ psLoad(); return PS; }

/* ═════════════════════════════════════════
   QR CODE & BARCODE RENDERING
   ═════════════════════════════════════════ */
let libsLoaded=false;
function loadExternalLibs(){
    if(libsLoaded)return Promise.resolve();
    return new Promise((res)=>{
        let loaded=0;const total=2;
        const done=()=>{loaded++;if(loaded>=total){libsLoaded=true;res()}};
        
        // JsBarcode
        if(typeof JsBarcode!=='undefined'){done()}else{
            const s1=document.createElement('script');
            s1.src='https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';
            s1.onload=done;s1.onerror=done;document.head.appendChild(s1);
        }
        // QRCode.js
        if(typeof QRCode!=='undefined'){done()}else{
            const s2=document.createElement('script');
            s2.src='https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
            s2.onload=done;s2.onerror=done;document.head.appendChild(s2);
        }
    });
}

function renderQRCode(elId,data,size){
    const el=document.getElementById(elId);if(!el)return;
    el.innerHTML='';
    try{
        if(typeof QRCode!=='undefined'){
            new QRCode(el,{text:data,width:size||80,height:size||80,colorDark:'#000000',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});
        }else{
            el.innerHTML=`<div style="width:${size||80}px;height:${size||80}px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:20px;color:var(--c3)"><i class="fas fa-qrcode"></i></div>`;
        }
    }catch(e){
        el.innerHTML='<i class="fas fa-qrcode" style="font-size:24px;color:#ccc"></i>';
    }
}

function renderBarcode(elId,data,opts){
    const el=document.getElementById(elId);if(!el)return;
    try{
        if(typeof JsBarcode!=='undefined'){
            JsBarcode('#'+elId,data,Object.assign({format:'CODE128',height:opts?.height||40,displayValue:false,margin:0,width:opts?.width||1.5,lineColor:'#333'},opts||{}));
        }
    }catch(e){
        el.outerHTML='<div style="font-family:monospace;font-size:8px;color:#666;text-align:center">'+esc(data)+'</div>';
    }
}

/* ═════════════════════════════════════════
   SHOW QR CODE MODAL (from detail)
   ═════════════════════════════════════════ */
async function showQRModal(idOrObj){
    // Accept either a numeric id (safe, no injection) or legacy object
    const container = (typeof idOrObj === 'number' || typeof idOrObj === 'string')
        ? (DATA.find(x=>x.id===+idOrObj) || {id:+idOrObj})
        : idOrObj;

    const ov=document.getElementById('qrOv');
    const content=document.getElementById('qrContent');
    ov.classList.add('show');

    const arUrl=window.location.origin+'/v1/ar/view_ar.php?id='+container.id;
    const qrVal=container.qr_code||('CHEM-'+container.id);
    const bottleCode=container.bottle_code||('ID'+container.id);
    const chemName=container.chemical_name||'';

    content.innerHTML=`
    <div class="stk-qr-display">
        <div class="qr-big" id="qrModalBig"></div>
        <div class="qr-val">${esc(qrVal)}</div>
        <div style="margin:8px 0"><svg id="qrModalBarcode"></svg></div>
        <div style="font-family:'Courier New',monospace;font-size:11px;color:var(--c2);margin-bottom:12px;letter-spacing:0.5px">${esc(bottleCode)}</div>
        <div class="qr-hint"><i class="fas fa-mobile-alt" style="color:var(--accent)"></i> ${T('สแกน QR Code เพื่อเปิดดู AR Model ของขวดสารเคมีนี้','Scan this QR Code to view the AR Model of this chemical bottle')}</div>
        <div style="margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
            <a href="${arUrl}" target="_blank" class="stk-btn stk-btn-s" style="background:linear-gradient(135deg,#0d9488,#14b8a6);color:#fff"><i class="fas fa-vr-cardboard"></i> ${T('เปิด AR View','Open AR View')}</a>
            <button class="stk-btn stk-btn-s stk-btn-o" onclick="printSingleQR(${container.id})"><i class="fas fa-print"></i> ${T('พิมพ์ QR','Print QR')}</button>
            <button class="stk-btn stk-btn-s stk-btn-g" onclick="downloadQR(${container.id})"><i class="fas fa-download"></i> ${T('ดาวน์โหลด','Download')}</button>
        </div>
    </div>`;

    await loadExternalLibs();
    setTimeout(()=>{
        renderQRCode('qrModalBig',arUrl,180);
        renderBarcode('qrModalBarcode',bottleCode,{height:50,width:2});
    },200);
}

function printSingleQR(id){
    const c = DATA.find(x=>x.id===+id) || {id:+id, bottle_code:'ID'+id, chemical_name:''};
    const code = c.bottle_code || ('ID'+id);
    const name = c.chemical_name || '';

    const qrEl=document.getElementById('qrModalBig');
    const canvas=qrEl?.querySelector('canvas');
    const img=canvas?canvas.toDataURL():'';
    const barcodeEl=document.getElementById('qrModalBarcode');
    const barcodeHtml=barcodeEl?barcodeEl.outerHTML:'';

    const w=window.open('','_blank','width=400,height=500');
    w.document.write(`<html><head><meta charset="utf-8"><title>QR - ${esc(code)}</title>
    <style>body{text-align:center;padding:20px;font-family:'Courier New',monospace}
    img{width:200px;height:200px;margin:10px auto;display:block}
    h3{font-size:14px;margin:4px 0}p{font-size:10px;color:#666}
    svg{max-width:250px;height:50px!important;margin:8px auto;display:block}
    .code{font-size:12px;letter-spacing:1px;font-weight:700;margin:4px 0}
    .hint{font-size:9px;color:#999;margin-top:10px;border-top:1px dashed #ddd;padding-top:8px}
    @page{size:60mm 80mm;margin:5mm}
    </style></head><body>
    <h3>🧪 ${esc(name)}</h3>
    ${img?'<img src="'+img+'">':'<p>QR Code</p>'}
    ${barcodeHtml}
    <div class="code">${esc(code)}</div>
    <p>${T('สแกน QR เพื่อดู AR Model','Scan QR for AR Model')}</p>
    <div class="hint">SUT chemBot | ID: ${id}</div>
    </body></html>`);
    w.document.close();
    setTimeout(()=>w.print(),500);
}

function downloadQR(id){
    const canvas=document.querySelector('#qrModalBig canvas');
    if(!canvas){toast(T('ไม่พบ QR Code','QR Code not found'),'err');return}
    const a=document.createElement('a');
    a.href=canvas.toDataURL('image/png');
    a.download='qrcode_'+id+'.png';
    a.click();
    toast(T('📥 ดาวน์โหลด QR Code แล้ว','📥 QR Code downloaded'),'ok');
}

/* ═════════════════════════════════════════
   TRANSACTION MODAL (เบิก / ยืม / โอน)
   ═════════════════════════════════════════ */
let _txnType='use',_txnUser=null;

const TXN_CFG={
    use:{
        label:{th:'เบิกใช้',en:'Use'},
        icon:'fa-flask',color:'#16a34a',bg:'#f0fdf4',
        tabId:'ttUse',btnCls:'txn-btn-use'
    },
    borrow:{
        label:{th:'ยืม',en:'Borrow'},
        icon:'fa-hand-holding',color:'#2563eb',bg:'#eff6ff',
        tabId:'ttBorrow',btnCls:'txn-btn-borrow'
    },
    transfer:{
        label:{th:'โอนกรรมสิทธิ์',en:'Transfer'},
        icon:'fa-share-nodes',color:'#7c3aed',bg:'#f5f3ff',
        tabId:'ttTransfer',btnCls:'txn-btn-transfer'
    }
};

function openTxnModal(type){
    if(!_txnItem){return;}
    _txnType=type;
    _txnUser=null;

    const cfg=TXN_CFG[type];
    const c=_txnItem;
    const maxQty=parseFloat(c.current_quantity??c.remaining_quantity??c.remaining_qty??c.quantity)??0;
    const unit=c.unit||c.quantity_unit||'';

    // Header
    const hdrIc=document.getElementById('txnHeaderIc');
    hdrIc.style.background=cfg.bg;
    hdrIc.style.color=cfg.color;
    hdrIc.innerHTML=`<i class="fas ${cfg.icon}"></i>`;
    document.getElementById('txnHeaderTitle').textContent=L==='th'?cfg.label.th:cfg.label.en;
    document.getElementById('txnHeaderSub').textContent=c.chemical_name||(c.bottle_code||'');

    // Show tabs only for admin/lab (they can do all 3), else hide
    const tabs=document.getElementById('txnTabs');
    const isMine=c.is_mine||IS_ADMIN;
    const canOwnerOp=isMine||IS_LAB;
    tabs.style.display=(IS_ADMIN||IS_LAB)?'flex':'none';
    // Activate correct tab
    ['use','borrow','transfer'].forEach(t=>{
        const btn=document.getElementById(TXN_CFG[t].tabId);
        if(btn){
            btn.classList.toggle('active',t===type);
            // hide use/transfer tabs if no permission
            if((t==='use'||t==='transfer')&&!canOwnerOp) btn.style.display='none';
            else btn.style.display='';
        }
    });

    document.getElementById('txnBody').innerHTML=buildTxnForm(type,c,maxQty,unit);
    if(type==='borrow') _txnUser={id:UID,name:USER_NAME,dep:_CURRENT_USER_CACHE.department||'',username:_CURRENT_USER_CACHE.username||''};
    else if(type==='transfer') loadTxnUserPicker();
    document.getElementById('txnOv').classList.add('show');
}

function buildTxnForm(type,c,maxQty,unit){
    const initQty=parseFloat(c.initial_quantity||c.package_size||0);
    const pct=initQty>0?Math.min(100,Math.round(maxQty/initQty*100)):null;
    const pctColor=pct===null?'#16a34a':pct>50?'#16a34a':pct>20?'#d97706':'#dc2626';

    // Type-aware chem-info card colours
    const ciStyle={
        use:    {bg:'linear-gradient(135deg,#f0fdf4,#dcfce7)',border:'#bbf7d0',ic:'linear-gradient(135deg,#16a34a,#15803d)',nm:'#14532d',qv:'#15803d',mt:'#166534'},
        borrow: {bg:'linear-gradient(135deg,#eff6ff,#dbeafe)',border:'#bfdbfe',ic:'linear-gradient(135deg,#2563eb,#1d4ed8)',nm:'#1e3a8a',qv:'#1d4ed8',mt:'#1e40af'},
        transfer:{bg:'linear-gradient(135deg,#f5f3ff,#ede9fe)',border:'#ddd6fe',ic:'linear-gradient(135deg,#7c3aed,#6d28d9)',nm:'#3b0764',qv:'#6d28d9',mt:'#5b21b6'},
    }[type]||{bg:'linear-gradient(135deg,#f0fdf4,#dcfce7)',border:'#bbf7d0',ic:'linear-gradient(135deg,#16a34a,#15803d)',nm:'#14532d',qv:'#15803d',mt:'#166534'};

    // Owner chip — shown below bottle code
    let ownerChip='';
    if(type==='borrow'||type==='transfer'){
        if(c.is_mine) ownerChip=`<div class="txn-owner-chip mine"><i class="fas fa-star"></i>${T('ของฉัน','Mine')}</div>`;
        else if(c.owner_name) ownerChip=`<div class="txn-owner-chip other"><i class="fas fa-user"></i>${T('ของ','Owner')}: ${esc(c.owner_name)}</div>`;
    }

    const chemInfo=`<div class="txn-chem-info" style="background:${ciStyle.bg};border-color:${ciStyle.border}">
        <div class="txn-chem-ic" style="background:${ciStyle.ic}"><i class="fas fa-flask"></i></div>
        <div class="txn-chem-body">
            <div class="txn-chem-name" style="color:${ciStyle.nm}">${esc(c.chemical_name||'-')}</div>
            ${c.bottle_code?`<div class="txn-chem-code" style="color:${ciStyle.qv}"># ${esc(c.bottle_code)}</div>`:''}
            ${ownerChip}
            <div class="txn-chem-stats" style="margin-top:6px">
                <div class="txn-chem-qty">
                    <div class="txn-chem-qty-val" style="color:${pctColor}">${maxQty.toLocaleString()} <span style="font-size:12px;font-weight:700">${esc(unit)}</span></div>
                    <div class="txn-chem-qty-lbl" style="color:${ciStyle.qv}">${T('คงเหลือ','remaining')}</div>
                </div>
                ${pct!==null?`
                <div class="txn-chem-divider" style="background:${ciStyle.border}"></div>
                <div class="txn-chem-meta">
                    <span style="color:${ciStyle.mt}"><i class="fas fa-chart-bar"></i> ${pct}% ${T('ของปริมาณเริ่มต้น','of initial')}</span>
                    ${c.location_name?`<span style="color:${ciStyle.mt}"><i class="fas fa-map-marker-alt"></i> ${esc(c.location_name)}</span>`:''}
                </div>`:''}
            </div>
        </div>
    </div>`;

    // Qty row — borrow gets a "ยืมทั้งขวด" button + live percentage bar
    const qtyRow=`<div class="txn-field">
        <label>${T('จำนวน','Quantity')} <span style="color:#dc2626">*</span></label>
        <div class="txn-qty-row">
            <input type="number" id="txnQty" min="0.001" max="${maxQty}" step="any" placeholder="0" style="text-align:right" oninput="updateTxnQtyBar(this.value,${maxQty})">
            ${type==='borrow'?`<button class="txn-full-btn" type="button" onclick="document.getElementById('txnQty').value=${maxQty};updateTxnQtyBar(${maxQty},${maxQty});this.blur()" title="${T('ยืมทั้งขวด','Borrow full bottle')}"><i class="fas fa-wine-bottle"></i> ${T('ทั้งขวด','Full')}</button>`:''}
            <div class="txn-qty-max" title="${T('ปริมาณคงเหลือ','Remaining')}">${T('สูงสุด','Max')} ${maxQty.toLocaleString()} ${esc(unit)}</div>
        </div>
        <div class="txn-qty-barwrap">
            <div class="txn-qty-bartrack"><div class="txn-qty-barfill" id="txnQtyBar"></div></div>
            <div class="txn-qty-barlbl">
                <span class="txn-qty-barpct" id="txnQtyPct">0%</span>
                <span class="txn-qty-baramt" id="txnQtyAmt">${T('ระบุจำนวนที่ต้องการ','Enter amount')}</span>
            </div>
        </div>
    </div>`;

    const purposeRow=`<div class="txn-field">
        <label>${T('วัตถุประสงค์','Purpose')} <span style="color:#dc2626">*</span></label>
        <textarea id="txnPurpose" rows="2" placeholder="${T('ระบุวัตถุประสงค์การใช้...','Describe purpose...')}"></textarea>
    </div>`;

    // User picker — pre-loaded scrollable list + client-side filter
    const userPicker=`<div class="txn-field" id="txnUserField">
        <label>${T('ผู้รับ / ผู้ยืม','Recipient / Borrower')} <span style="color:#dc2626">*</span></label>
        <div class="txn-upick" id="txnUpick">
            <div class="txn-upick-flt">
                <i class="fas fa-filter"></i>
                <input id="txnUpickQ" type="text" autocomplete="off"
                    placeholder="${T('กรอกชื่อเพื่อกรอง...','Filter by name...')}"
                    oninput="filterTxnUsers(this.value)">
            </div>
            <div class="txn-ulist" id="txnUlist">
                <div class="txn-unone"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>`;

    const returnDate=`<div class="txn-field">
        <label>${T('กำหนดคืน','Expected Return Date')} <span style="color:#dc2626">*</span></label>
        <input type="date" id="txnReturnDate" min="${new Date().toISOString().split('T')[0]}">
    </div>`;

    let html=chemInfo;

    if(type==='use'){
        html+=`<div class="txn-info"><i class="fas fa-info-circle"></i><span>${T('เบิกใช้ : ปริมาณสารจะถูกหักจากขวดทันที ไม่ต้องคืน','Use: Quantity will be deducted immediately from this container. No return required.')}</span></div>`;
        html+=qtyRow+purposeRow;
    } else if(type==='borrow'){
        const isMine=c.is_mine||IS_ADMIN;
        if(!isMine) html+=`<div class="txn-warn"><i class="fas fa-clock"></i><span>${T('<b>หมายเหตุ:</b> การยืมจะต้องรอการอนุมัติจากเจ้าของ/ผู้จัดการ','<b>Note:</b> Borrow request will need approval from the owner/manager.')}</span></div>`;
        const myInitial=(USER_NAME||'?').charAt(0).toUpperCase();
        const ownerInitial=(c.owner_name||'?').charAt(0).toUpperCase();
        const borrowerFlow=`<div class="txn-field">
            <label>${T('ข้อมูลการยืม','Borrow Info')}</label>
            <div class="txn-borrow-flow">
                <div class="txn-bf-side">
                    ${txnBfAv(_CURRENT_USER_CACHE.avatar_url,myInitial,'#2563eb')}
                    <div>
                        <div class="txn-bf-name">${esc(USER_NAME)}</div>
                        <div class="txn-bf-role" style="color:#2563eb">${T('ผู้ยืม','Borrower')}</div>
                    </div>
                </div>
                <div class="txn-bf-arrow"><i class="fas fa-hand-holding"></i><span>${T('ยืมจาก','from')}</span></div>
                <div class="txn-bf-side">
                    ${txnBfAv(c.owner_avatar_url,ownerInitial,'#6C5CE7')}
                    <div>
                        <div class="txn-bf-name">${esc(c.owner_name||T('ไม่ทราบ','Unknown'))}</div>
                        <div class="txn-bf-role" style="color:#6C5CE7">${T('เจ้าของ','Owner')}</div>
                    </div>
                </div>
            </div>
        </div>`;
        html+=qtyRow+borrowerFlow+returnDate+purposeRow;
    } else if(type==='transfer'){
        html+=`<div class="txn-info"><i class="fas fa-info-circle"></i><span>${T('โอนกรรมสิทธิ์ : เจ้าของขวดจะเปลี่ยนเป็นผู้รับโอน สามารถโอนบางส่วนหรือทั้งหมด','Transfer ownership: Container owner will change to recipient. Partial or full transfer.')}</span></div>`;
        html+=qtyRow+userPicker+purposeRow;
    }

    html+=`<div class="txn-submit-row">
        <button class="txn-btn-cancel" onclick="closeTxnModal()"><i class="fas fa-times"></i> ${T('ยกเลิก','Cancel')}</button>
        <button class="${TXN_CFG[type].btnCls}" id="txnSubmitBtn" onclick="submitTxn('${type}')"><i class="fas ${TXN_CFG[type].icon}"></i> ${L==='th'?TXN_CFG[type].label.th:TXN_CFG[type].label.en}</button>
    </div>`;

    return html;
}

/* ── Borrow flow avatar helper ── */
function txnBfAv(url, initial, bg){
    const av=url?`<img src="${esc(url)}" alt="" style="width:100%;height:100%;object-fit:cover" onerror="var p=this.parentNode;p.style.background='${bg}';p.innerHTML='${initial}'">`:'';
    return `<div class="txn-bf-av" style="background:${url?'transparent':bg}">${av||initial}</div>`;
}

/* ── Qty percentage bar ── */
function updateTxnQtyBar(val,max){
    const qty=Math.max(0,parseFloat(val)||0);
    const pct=max>0?Math.min(100,qty/max*100):0;
    const bar=document.getElementById('txnQtyBar');
    const pctEl=document.getElementById('txnQtyPct');
    const amtEl=document.getElementById('txnQtyAmt');
    if(!bar) return;
    const color=pct>80?'#dc2626':pct>50?'#d97706':'#16a34a';
    bar.style.width=pct+'%';
    bar.style.background=color;
    if(pctEl){pctEl.textContent=Math.round(pct)+'%';pctEl.style.color=qty>0?color:'#94a3b8';}
    if(amtEl) amtEl.textContent=qty>0
        ?`${qty.toLocaleString()} / ${max.toLocaleString()}`
        :(L==='th'?'ระบุจำนวนที่ต้องการ':'Enter amount');
}

/* ── User picker — pre-loaded list with client-side filter ── */
const _UV=['#6C5CE7','#2563eb','#059669','#d97706','#dc2626','#0891b2','#7c3aed','#db2777','#ea580c','#0d9488'];
let _txnAllUsers=[];

async function loadTxnUserPicker(){
    const list=document.getElementById('txnUlist');
    if(!list) return;
    try{
        const res=await apiFetch('/v1/api/borrow.php?action=list_users');
        _txnAllUsers=(res.success?res.data:res)||[];
        renderTxnUserList(_txnAllUsers);
        // Auto-select self for borrow
        if(_txnType==='borrow'){
            const me=_txnAllUsers.find(u=>u.id===UID);
            const nm=me?(me.display_name||me.first_name+' '+me.last_name):USER_NAME;
            const dep=me?me.department:'';
            const un=me?me.username:'';
            selectTxnUser(UID,nm,dep,un);
        }
    }catch(e){
        list.innerHTML=`<div class="txn-unone" style="color:#dc2626"><i class="fas fa-exclamation-circle"></i> ${T('โหลดรายชื่อไม่สำเร็จ','Failed to load users')}</div>`;
    }
}

function renderTxnUserList(users){
    const list=document.getElementById('txnUlist');
    if(!list) return;
    if(!users.length){
        list.innerHTML=`<div class="txn-unone">${T('ไม่พบผู้ใช้','No users found')}</div>`;
        return;
    }
    list.innerHTML=users.map((u,i)=>{
        const name=u.display_name||(u.first_name+' '+u.last_name).trim();
        const isMe=u.id===UID;
        const isSel=_txnUser&&_txnUser.id===u.id;
        const bg=_UV[i%_UV.length];
        return `<div class="txn-urow${isSel?' sel':''}" id="txnUrow${u.id}"
            onclick="selectTxnUser(${u.id},'${esc(name)}','${esc(u.department||'')}','${esc(u.username||'')}')">
            <div class="txn-uav" style="background:${bg}">${(name||'?').charAt(0).toUpperCase()}</div>
            <div style="flex:1;min-width:0">
                <div class="txn-uname">${esc(name)}${isMe?`<span class="txn-ume">${T('ฉัน','Me')}</span>`:''}</div>
                <div class="txn-udep">${esc(u.department||u.username||'-')}</div>
            </div>
            <i class="fas fa-check-circle txn-ucheck"></i>
        </div>`;
    }).join('');
    // Scroll selected row into view
    if(_txnUser){const r=document.getElementById('txnUrow'+_txnUser.id);if(r)r.scrollIntoView({block:'nearest'});}
}

function filterTxnUsers(q){
    const lq=(q||'').toLowerCase().trim();
    const filtered=lq?_txnAllUsers.filter(u=>{
        const n=(u.display_name||u.first_name+' '+u.last_name).toLowerCase();
        return n.includes(lq)||(u.department||'').toLowerCase().includes(lq)||(u.username||'').toLowerCase().includes(lq);
    }):_txnAllUsers;
    renderTxnUserList(filtered);
}

function selectTxnUser(id,name,dep,username){
    _txnUser={id,name,dep,username};
    // Highlight row in picker
    document.querySelectorAll('.txn-urow').forEach(r=>r.classList.remove('sel'));
    const row=document.getElementById('txnUrow'+id);
    if(row){row.classList.add('sel');row.scrollIntoView({block:'nearest'});}
}

function clearTxnUser(){
    _txnUser=null;
    document.querySelectorAll('.txn-urow').forEach(r=>r.classList.remove('sel'));
    const flt=document.getElementById('txnUpickQ');
    if(flt){flt.value='';filterTxnUsers('');}
}

function switchTxnTab(type){
    if(!_txnItem) return;
    _txnType=type;
    _txnUser=null;
    const c=_txnItem;
    const maxQty=parseFloat(c.current_quantity??c.remaining_quantity??c.remaining_qty??c.quantity)??0;
    const unit=c.unit||c.quantity_unit||'';

    const cfg=TXN_CFG[type];
    const hdrIc=document.getElementById('txnHeaderIc');
    hdrIc.style.background=cfg.bg;
    hdrIc.style.color=cfg.color;
    hdrIc.innerHTML=`<i class="fas ${cfg.icon}"></i>`;
    document.getElementById('txnHeaderTitle').textContent=L==='th'?cfg.label.th:cfg.label.en;
    document.getElementById('txnHeaderSub').textContent=c.chemical_name||(c.bottle_code||'');

    ['use','borrow','transfer'].forEach(t=>{
        const btn=document.getElementById(TXN_CFG[t].tabId);
        if(btn) btn.classList.toggle('active',t===type);
    });

    document.getElementById('txnBody').innerHTML=buildTxnForm(type,c,maxQty,unit);
    if(type==='borrow') _txnUser={id:UID,name:USER_NAME,dep:_CURRENT_USER_CACHE.department||'',username:_CURRENT_USER_CACHE.username||''};
    else if(type==='transfer') loadTxnUserPicker();
}

async function submitTxn(type){
    const c=_txnItem;
    if(!c){toast(T('ไม่พบข้อมูล','No item selected'),'err');return;}

    const qtyEl=document.getElementById('txnQty');
    const purposeEl=document.getElementById('txnPurpose');
    const returnEl=document.getElementById('txnReturnDate');
    const submitBtn=document.getElementById('txnSubmitBtn');

    const qty=qtyEl?parseFloat(qtyEl.value):0;
    const purpose=(purposeEl?purposeEl.value:'').trim();
    const maxQty=parseFloat(c.current_quantity??c.remaining_quantity??c.remaining_qty??c.quantity)??0;

    // Validation
    if(!qty||qty<=0){toast(T('กรุณาระบุจำนวน','Please enter quantity'),'err');if(qtyEl)qtyEl.focus();return;}
    if(qty>maxQty){toast(T('จำนวนเกินปริมาณคงเหลือ','Quantity exceeds remaining'),'err');if(qtyEl)qtyEl.focus();return;}
    if(!purpose){toast(T('กรุณาระบุวัตถุประสงค์','Please enter purpose'),'err');if(purposeEl)purposeEl.focus();return;}
    if((type==='borrow'||type==='transfer')&&!_txnUser){toast(T('กรุณาเลือกผู้รับ/ผู้ยืม','Please select recipient'),'err');return;}
    if(type==='borrow'&&returnEl&&!returnEl.value){toast(T('กรุณาระบุกำหนดคืน','Please enter return date'),'err');if(returnEl)returnEl.focus();return;}

    // For borrow: show confirmation review instead of submitting directly
    if(type==='borrow'){
        showBorrowConfirm(qty,purpose,returnEl?returnEl.value:'');
        return;
    }

    // Determine source
    const srcType=c.source_type||c.source||'container';
    const srcId=Math.abs(parseInt(c.id)||0);

    if(submitBtn){submitBtn.disabled=true;submitBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> '+T('กำลังดำเนินการ...','Processing...');}

    try{
        let payload={source_type:srcType,source_id:srcId,quantity:qty,purpose};
        let action=type;

        if(type==='transfer'){
            payload.to_user_id=_txnUser.id;
        }

        const res=await fetch('/v1/api/borrow.php?action='+action,{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify(payload)
        }).then(r=>r.json());

        if(res.success){
            const needApproval=res.data&&res.data.status==='pending';
            const cfg=TXN_CFG[type];

            // ── Optimistic UI: deduct qty from DATA immediately ──
            const dataItem=DATA.find(x=>+x.id===+c.id);
            if(dataItem&&type==='use'){
                const prev=parseFloat(dataItem.current_quantity??dataItem.remaining_qty??0);
                dataItem.current_quantity=Math.max(0,prev-qty);
                dataItem.remaining_qty=dataItem.current_quantity;
                renderView(); // re-render list with updated qty
            }

            // ── Background refresh (stats + list) ──
            loadStats();
            loadData(PAGE);

            // ── Also refresh detail panel if open ──
            const detailOv=document.getElementById('detailOv');
            if(detailOv&&detailOv.classList.contains('show')) openDetail(c.id);

            // ── Show success screen, then auto-close ──
            const body=document.getElementById('txnBody');
            if(body){
                body.innerHTML=`<div class="txn-result ok">
                    <div class="txn-result-ic">${needApproval?'⏳':'✅'}</div>
                    <div class="txn-result-title">${needApproval?T('รอการอนุมัติ','Pending Approval'):T('ดำเนินการสำเร็จ','Completed')}</div>
                    <div class="txn-result-msg">${needApproval
                        ?T('คำขอถูกส่งเพื่อรออนุมัติจากผู้จัดการ/เจ้าของ','Request submitted — awaiting approval')
                        :T('บันทึกรายการสำเร็จ ข้อมูลถูกอัปเดตแล้ว','Transaction saved — data updated')
                    }</div>
                    ${res.data&&res.data.id?`<div style="font-size:10px;color:var(--c3);margin-top:4px">Ref #${res.data.id}</div>`:''}
                    <div class="txn-result-auto" id="txnAutoClose">
                        <div class="txn-auto-bar" id="txnAutoBar"></div>
                    </div>
                    <button class="stk-btn stk-btn-g stk-btn-s" style="margin-top:14px" onclick="closeTxnModal()"><i class="fas fa-times"></i> ${T('ปิด','Close')}</button>
                </div>`;
                // Animate progress bar then auto-close
                requestAnimationFrame(()=>{
                    const bar=document.getElementById('txnAutoBar');
                    if(bar){bar.style.transition='width 2s linear';bar.style.width='100%';}
                });
                _txnAutoCloseTimer=setTimeout(()=>closeTxnModal(),2000);
            }
        } else {
            throw new Error(res.error||res.message||T('เกิดข้อผิดพลาด','Unknown error'));
        }
    } catch(err){
        if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='<i class="fas '+TXN_CFG[type].icon+'"></i> '+T('ลองใหม่','Retry');}
        toast(err.message,'err');
    }
}

let _txnAutoCloseTimer=null;
function closeTxnModal(){
    if(_txnAutoCloseTimer){clearTimeout(_txnAutoCloseTimer);_txnAutoCloseTimer=null;}
    document.getElementById('txnConfirmOv').classList.remove('show');
    document.getElementById('txnOv').classList.remove('show');
    _txnUser=null;
}

/* ── Borrow confirmation flow ── */
let _pendingBorrowPayload=null;

function showBorrowConfirm(qty,purpose,returnDate){
    const c=_txnItem;
    const unit=c.unit||c.quantity_unit||'';
    const maxQty=parseFloat(c.current_quantity??c.remaining_quantity??c.remaining_qty??c.quantity)??0;
    const isMine=c.is_mine||IS_ADMIN;
    const pct=maxQty>0?Math.min(100,Math.round(qty/maxQty*100)):0;
    const pctColor=pct>80?'#dc2626':pct>50?'#d97706':'#16a34a';
    const retFmt=returnDate?new Date(returnDate).toLocaleDateString(L==='th'?'th-TH':'en-GB',{day:'numeric',month:'long',year:'numeric'}):'-';
    const myInitial=(USER_NAME||'?').charAt(0).toUpperCase();
    const ownerInitial=(c.owner_name||'?').charAt(0).toUpperCase();

    _pendingBorrowPayload={
        source_type:c.source_type||c.source||'container',
        source_id:Math.abs(parseInt(c.id)||0),
        quantity:qty,purpose,
        to_user_id:_txnUser.id,
        expected_return_date:returnDate
    };

    document.getElementById('txnConfirmBox').innerHTML=`
        <div class="txn-confirm-hdr">
            <div class="txn-confirm-hdr-ic"><i class="fas fa-clipboard-check"></i></div>
            <div>
                <div class="txn-confirm-hdr-title">${T('ทบทวนรายการ','Review Request')}</div>
                <div class="txn-confirm-hdr-sub">${T('ตรวจสอบข้อมูลให้ถูกต้องก่อนยืนยัน','Please review carefully before confirming')}</div>
            </div>
        </div>
        <div class="txn-confirm-body">
            <div class="txn-confirm-chem">
                <div class="txn-confirm-chem-name">${esc(c.chemical_name||'-')}</div>
                ${c.bottle_code?`<div class="txn-confirm-chem-code"># ${esc(c.bottle_code)}</div>`:''}
            </div>
            <div class="txn-borrow-flow" style="margin-bottom:14px">
                <div class="txn-bf-side">
                    ${txnBfAv(_CURRENT_USER_CACHE.avatar_url,myInitial,'#2563eb')}
                    <div><div class="txn-bf-name">${esc(USER_NAME)}</div><div class="txn-bf-role" style="color:#2563eb">${T('ผู้ยืม','Borrower')}</div></div>
                </div>
                <div class="txn-bf-arrow"><i class="fas fa-hand-holding"></i><span>${T('ยืมจาก','from')}</span></div>
                <div class="txn-bf-side">
                    ${txnBfAv(c.owner_avatar_url,ownerInitial,'#6C5CE7')}
                    <div><div class="txn-bf-name">${esc(c.owner_name||T('ไม่ทราบ','Unknown'))}</div><div class="txn-bf-role" style="color:#6C5CE7">${T('เจ้าของ','Owner')}</div></div>
                </div>
            </div>
            <div class="txn-confirm-rows">
                <div class="txn-confirm-row">
                    <div class="txn-confirm-row-ic" style="background:#eff6ff;color:#2563eb"><i class="fas fa-weight-hanging"></i></div>
                    <div><div class="txn-confirm-row-label">${T('จำนวนที่ยืม','Quantity')}</div>
                    <div class="txn-confirm-row-val" style="color:${pctColor}">${qty.toLocaleString()} ${esc(unit)} <span style="font-size:11px">(${pct}%)</span></div></div>
                </div>
                <div class="txn-confirm-row">
                    <div class="txn-confirm-row-ic" style="background:#fef3c7;color:#d97706"><i class="fas fa-calendar-alt"></i></div>
                    <div><div class="txn-confirm-row-label">${T('กำหนดคืน','Return By')}</div>
                    <div class="txn-confirm-row-val">${retFmt}</div></div>
                </div>
                <div class="txn-confirm-row">
                    <div class="txn-confirm-row-ic" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-file-alt"></i></div>
                    <div style="flex:1;min-width:0"><div class="txn-confirm-row-label">${T('วัตถุประสงค์','Purpose')}</div>
                    <div class="txn-confirm-row-val" style="white-space:pre-line">${esc(purpose)}</div></div>
                </div>
                ${!isMine?`<div class="txn-warn" style="margin:0"><i class="fas fa-clock"></i><span>${T('<b>หมายเหตุ:</b> คำขอจะต้องรอการอนุมัติจากเจ้าของ/ผู้จัดการ','<b>Note:</b> This request will require approval from the owner/manager.')}</span></div>`:''}
            </div>
        </div>
        <div class="txn-confirm-actions">
            <button class="txn-confirm-back" onclick="closeBorrowConfirm()"><i class="fas fa-pen"></i> ${T('แก้ไข','Edit')}</button>
            <button class="txn-confirm-go" id="txnConfirmGoBtn" onclick="doConfirmedBorrow()"><i class="fas fa-hand-holding"></i> ${T('ยืนยันการยืม','Confirm Borrow')}</button>
        </div>`;
    document.getElementById('txnConfirmOv').classList.add('show');
}

function closeBorrowConfirm(){
    document.getElementById('txnConfirmOv').classList.remove('show');
}

async function doConfirmedBorrow(){
    const goBtn=document.getElementById('txnConfirmGoBtn');
    if(goBtn){goBtn.disabled=true;goBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> '+T('กำลังดำเนินการ...','Processing...');}
    try{
        const res=await fetch('/v1/api/borrow.php?action=borrow',{
            method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify(_pendingBorrowPayload)
        }).then(r=>r.json());

        closeBorrowConfirm();

        if(res.success){
            const needApproval=res.data&&res.data.status==='pending';
            const c=_txnItem;
            loadStats();loadData(PAGE);
            const detailOv=document.getElementById('detailOv');
            if(detailOv&&detailOv.classList.contains('show')) openDetail(c.id);

            const body=document.getElementById('txnBody');
            if(body){
                body.innerHTML=`<div class="txn-result ok">
                    <div class="txn-result-ic">${needApproval?'⏳':'✅'}</div>
                    <div class="txn-result-title">${needApproval?T('รอการอนุมัติ','Pending Approval'):T('ดำเนินการสำเร็จ','Completed')}</div>
                    <div class="txn-result-msg">${needApproval
                        ?T('คำขอถูกส่งเพื่อรออนุมัติจากผู้จัดการ/เจ้าของ','Request submitted — awaiting approval')
                        :T('บันทึกรายการสำเร็จ ข้อมูลถูกอัปเดตแล้ว','Transaction saved — data updated')
                    }</div>
                    ${res.data&&res.data.id?`<div style="font-size:10px;color:var(--c3);margin-top:4px">Ref #${res.data.id}</div>`:''}
                    <div class="txn-result-auto" id="txnAutoClose"><div class="txn-auto-bar" id="txnAutoBar"></div></div>
                    <button class="stk-btn stk-btn-g stk-btn-s" style="margin-top:14px" onclick="closeTxnModal()"><i class="fas fa-times"></i> ${T('ปิด','Close')}</button>
                </div>`;
                requestAnimationFrame(()=>{const bar=document.getElementById('txnAutoBar');if(bar){bar.style.transition='width 2s linear';bar.style.width='100%';}});
                _txnAutoCloseTimer=setTimeout(()=>closeTxnModal(),2000);
            }
        } else {
            throw new Error(res.error||res.message||T('เกิดข้อผิดพลาด','Unknown error'));
        }
    }catch(err){
        if(goBtn){goBtn.disabled=false;goBtn.innerHTML='<i class="fas fa-hand-holding"></i> '+T('ยืนยันการยืม','Confirm Borrow');}
        closeBorrowConfirm();
        toast(err.message,'err');
    }
}
</script>
</body></html>