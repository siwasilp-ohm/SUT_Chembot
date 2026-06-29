<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
if (!in_array($user['role_name'], ['admin', 'lab_manager'])) { header('Location: /v1/'); exit; }
$isAdmin = $user['role_name'] === 'admin';
$lang = I18n::getCurrentLang();
$TH = $lang === 'th';
Layout::head($TH ? 'จัดการผู้ใช้' : 'User Management');
?>
<body>
<?php Layout::sidebar('users'); Layout::beginContent(); ?>

<style>
:root{--usr-r:14px;--usr-rs:10px;--usr-sh:0 1px 6px rgba(0,0,0,.06);--usr-shm:0 4px 20px rgba(0,0,0,.09)}

/* ── Hero ── */
.usr-hero{background:linear-gradient(135deg,#1e1b4b 0%,#312e81 55%,#6366f1 100%);border-radius:var(--usr-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.usr-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.usr-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;position:relative}
.usr-hero-info{position:relative}
.usr-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.usr-hero-info p{font-size:12px;opacity:.85;margin:0}
.usr-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0;position:relative}
.usr-hero-c{text-align:center}
.usr-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.usr-hero-c .lb{font-size:10px;opacity:.7;margin-top:2px;text-transform:uppercase;letter-spacing:.5px}
.usr-hero-sep{width:1px;background:rgba(255,255,255,.2);flex-shrink:0}

/* ── Stats Row ── */
.usr-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.usr-stat{background:#fff;border-radius:var(--usr-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--usr-sh);border:1.5px solid var(--border);transition:all .15s;cursor:pointer}
.usr-stat:hover{transform:translateY(-2px);box-shadow:var(--usr-shm)}
.usr-stat.af{border-color:#6366f1;background:#f5f3ff}
.usr-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.usr-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.usr-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}

/* ── Toolbar ── */
.usr-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.usr-search{flex:1;min-width:200px;position:relative}
.usr-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:var(--usr-rs);font-size:13px;background:#fff;color:var(--c1);transition:border .15s;font-family:inherit}
.usr-search input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.usr-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--c3);font-size:13px}
.usr-btn{padding:8px 16px;border:none;border-radius:var(--usr-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap;text-decoration:none}
.usr-btn-p{background:#6366f1;color:#fff}.usr-btn-p:hover{background:#4f46e5}
.usr-btn-o{background:#fff;color:#6366f1;border:1.5px solid #6366f1}.usr-btn-o:hover{background:#6366f1;color:#fff}
.usr-btn-g{background:#fff;color:var(--c3);border:1.5px solid var(--border)}.usr-btn-g:hover{border-color:#6366f1;color:#6366f1}
.usr-btn-d{background:#dc2626;color:#fff}.usr-btn-d:hover{background:#b91c1c}

/* ── View switcher ── */
.usr-vw{display:flex;border:1.5px solid var(--border);border-radius:var(--usr-rs);overflow:hidden}
.usr-vw button{padding:7px 11px;border:none;background:#fff;color:var(--c3);cursor:pointer;font-size:12px;transition:all .12s;display:flex;align-items:center;gap:4px}
.usr-vw button+button{border-left:1px solid var(--border)}
.usr-vw button.active{background:#6366f1;color:#fff}
.usr-vw button:hover:not(.active){background:#f8fafc}

/* ── Role Tabs ── */
.usr-tabs-wrap{background:#fff;border:1.5px solid var(--border);border-radius:var(--usr-r);padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;box-shadow:var(--usr-sh)}
.usr-tabs{display:inline-flex;background:#f1f5f9;border-radius:8px;padding:3px;gap:2px;overflow-x:auto;flex:1;min-width:0}
.usr-tab{padding:7px 14px;font-size:11px;font-weight:600;color:var(--c3);border-radius:6px;cursor:pointer;border:none;background:none;font-family:inherit;transition:all .15s;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.usr-tab:hover{color:var(--c1)}
.usr-tab.active{background:#fff;color:#4338ca;box-shadow:0 1px 4px rgba(0,0,0,.08);font-weight:700}
.usr-tab .bg{font-size:9px;padding:1px 6px;border-radius:8px;font-weight:700;background:#e2e8f0;color:var(--c3)}
.usr-tab.active .bg{background:#4338ca;color:#fff}

/* ── Table ── */
.usr-tw{overflow-x:auto;border-radius:var(--usr-r);border:1.5px solid var(--border);background:#fff;box-shadow:var(--usr-sh)}
.usr-t{width:100%;border-collapse:collapse;font-size:12px}
.usr-t th{background:#f8fafc;padding:10px 14px;text-align:left;font-weight:700;color:var(--c3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);white-space:nowrap;position:sticky;top:0;z-index:1}
.usr-t td{padding:11px 14px;border-bottom:1px solid #f1f5f9;color:var(--c1);vertical-align:middle}
.usr-t tbody tr{transition:background .1s;cursor:pointer}
.usr-t tbody tr:hover{background:#fafbff}
.usr-t tbody tr:last-child td{border-bottom:none}
.usr-t tbody tr.inactive-row{opacity:.55}

/* ── User Avatar ── */
.usr-av{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff;flex-shrink:0;position:relative}
.usr-av img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.usr-av-sm{width:28px;height:28px;font-size:10px}
.usr-av-online::after{content:'';position:absolute;bottom:1px;right:1px;width:8px;height:8px;border-radius:50%;background:#22c55e;border:2px solid #fff}

/* ── Role Badges ── */
.usr-role{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.usr-role-admin{background:#fee2e2;color:#b91c1c}
.usr-role-ceo{background:#dbeafe;color:#1d4ed8}
.usr-role-lab_manager{background:#fef3c7;color:#b45309}
.usr-role-user{background:#dcfce7;color:#15803d}
.usr-role-visitor{background:#f1f5f9;color:#64748b}
.usr-status-on{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:10px;font-weight:700;background:#dcfce7;color:#15803d}
.usr-status-off{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:10px;font-weight:700;background:#f1f5f9;color:#64748b}


/* ── Action buttons ── */
.usr-actions{display:flex;gap:3px;justify-content:center}
.usr-act{padding:5px 8px;border:none;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:3px;transition:all .12s}
.usr-act-view{background:#ede9fe;color:#5b21b6}.usr-act-view:hover{background:#ddd6fe}
.usr-act-lab{background:#e0f2fe;color:#0369a1}.usr-act-lab:hover{background:#bae6fd}
.usr-act-edit{background:#dbeafe;color:#1d4ed8}.usr-act-edit:hover{background:#bfdbfe}
.usr-act-tog{background:#fef3c7;color:#b45309}.usr-act-tog:hover{background:#fde68a}
.usr-act-del{background:#fee2e2;color:#dc2626}.usr-act-del:hover{background:#fecaca}

/* ── Grid / Card View ── */
.usr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.usr-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--usr-r);overflow:hidden;transition:all .18s;cursor:pointer;position:relative}
.usr-card:hover{border-color:#a5b4fc;box-shadow:var(--usr-shm);transform:translateY(-2px)}
.usr-card.inactive-card{opacity:.6}
.usr-card-stripe{position:absolute;top:0;left:0;right:0;height:3px}
.usr-card-hd{display:flex;align-items:flex-start;gap:12px;padding:16px 16px 0}
.usr-card-av{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:#fff;flex-shrink:0}
.usr-card-info{flex:1;min-width:0;padding-top:2px}
.usr-card-name{font-size:13px;font-weight:700;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.usr-card-at{font-size:10px;color:var(--c3);font-family:'Courier New',monospace;margin-top:1px}
.usr-card-bd{padding:10px 16px 14px}
.usr-card-meta{display:flex;flex-direction:column;gap:5px;margin-bottom:10px}
.usr-card-row{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--c2)}
.usr-card-row i{width:14px;text-align:center;font-size:10px;color:var(--c3);flex-shrink:0}
.usr-card-ft{display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid #f1f5f9;gap:6px}
.usr-card-acts{display:flex;gap:4px}

/* ── Empty / Loading ── */
.usr-empty{padding:48px 24px;text-align:center;color:var(--c3)}
.usr-empty i{font-size:40px;margin-bottom:12px;opacity:.3;display:block}
.usr-empty p{font-size:13px}
.usr-ld{padding:48px;text-align:center;color:var(--c3);font-size:13px;display:flex;align-items:center;justify-content:center;gap:8px}
.usr-ld i{animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Modals (upgraded) ── */
.usr-ov{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);z-index:9999;display:none;align-items:center;justify-content:center}
.usr-ov.show{display:flex}
.usr-md{background:#fff;border-radius:18px;width:96%;max-width:820px;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.22);animation:usrMdIn .22s cubic-bezier(.34,1.56,.64,1)}
@keyframes usrMdIn{from{transform:scale(.92) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
.usr-md-sm{max-width:460px}
.usr-mh{padding:18px 22px 0;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:2;border-bottom:1px solid transparent;padding-bottom:14px;margin-bottom:0;border-bottom:1px solid #f1f5f9}
.usr-mh h3{font-size:15px;font-weight:700;color:var(--c1);display:flex;align-items:center;gap:8px;margin:0}
.usr-mx{width:32px;height:32px;border-radius:8px;border:none;background:#f1f5f9;cursor:pointer;font-size:14px;color:var(--c3);display:flex;align-items:center;justify-content:center;transition:all .12s}
.usr-mx:hover{background:#fee2e2;color:#dc2626}
.usr-mb{padding:16px 22px 22px}

/* ── Detail panel ── */
.usr-det-av{width:72px;height:72px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff;margin:0 auto 10px}
.usr-det-name{text-align:center;font-size:17px;font-weight:800;color:var(--c1);margin-bottom:4px}
.usr-det-sub{text-align:center;color:var(--c3);font-size:11px;margin-bottom:12px}
.usr-det-badges{display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin-bottom:16px}
.usr-det-rows{border-top:1px solid var(--border);padding-top:12px;display:flex;flex-direction:column;gap:2px}
.usr-det-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;font-size:12px}
.usr-det-row:last-child{border-bottom:none}
.usr-det-lbl{color:var(--c3);display:flex;align-items:center;gap:6px;font-weight:500}
.usr-det-val{font-weight:600;color:var(--c1);text-align:right;max-width:60%;word-break:break-word}
.usr-det-acts{display:flex;gap:8px;justify-content:center;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);flex-wrap:wrap}
/* ── Room list inside detail modal ── */
.ud-rooms{border-top:1.5px solid #f1f5f9;margin-top:14px;padding-top:12px}
.ud-rooms-hdr{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px}
.ud-rooms-hdr i{font-size:12px}
.ud-rooms-cnt{margin-left:auto;font-size:10px;font-weight:600;color:#6366f1;background:#e0e7ff;padding:1px 8px;border-radius:8px;text-transform:none;letter-spacing:0}
.ud-room-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:9px;background:#f8fafc;border:1.5px solid #e8edf5;margin-bottom:6px;transition:border-color .12s}
.ud-room-item:last-child{margin-bottom:0}
.ud-room-item.primary{background:#f0f4ff;border-color:#c7d2fe}
.ud-room-ic{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.ud-room-body{flex:1;min-width:0}
.ud-room-top{display:flex;align-items:center;gap:5px;margin-bottom:2px;flex-wrap:wrap}
.ud-room-code{font-size:13px;font-weight:800;line-height:1}
.ud-room-floor{font-size:9px;font-weight:600;color:#64748b;background:#e2e8f0;padding:1px 5px;border-radius:5px}
.ud-room-pri{font-size:9px;font-weight:700;color:#6366f1;background:#e0e7ff;padding:1px 7px;border-radius:8px;display:flex;align-items:center;gap:3px}
.ud-room-name{font-size:11px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ud-room-meta{display:flex;align-items:center;gap:8px;margin-top:3px;flex-wrap:wrap}
.ud-room-tag{font-size:10px;color:#94a3b8;display:flex;align-items:center;gap:3px}

/* ── Import/Export dropdown ── */
.usr-ie-wrap{position:relative;display:inline-block}
.usr-ie-dd{position:absolute;top:calc(100% + 6px);right:0;width:300px;background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.16),0 0 0 1px rgba(0,0,0,.04);z-index:100;display:none;overflow:hidden;animation:ieSlideDown .2s ease}
.usr-ie-dd.show{display:block}
@keyframes ieSlideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.usr-ie-head{padding:12px 16px;font-size:13px;font-weight:700;color:var(--c1);display:flex;align-items:center;gap:8px;border-bottom:1px solid #f0f0f0;background:#f9fafb}
.usr-ie-item{display:flex;align-items:center;gap:12px;padding:10px 16px;width:100%;border:none;background:transparent;cursor:pointer;transition:all .15s;text-align:left;font-size:13px}
.usr-ie-item:hover{background:#f0f4ff}
.usr-ie-item i{width:20px;text-align:center;font-size:14px;flex-shrink:0}
.usr-ie-title{font-weight:600;color:var(--c1);font-size:13px}
.usr-ie-desc{font-size:10px;color:var(--c3);margin-top:1px}
.usr-ie-div{height:1px;background:#f0f0f0;margin:2px 0}

/* ── Toast ── */
.usr-toast{position:fixed;top:64px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.usr-toast-item{background:#1a1a2e;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,.18);animation:toastIn .3s ease;pointer-events:all;min-width:240px}
.usr-toast-item.ok{background:#065f46}.usr-toast-item.err{background:#991b1b}
@keyframes toastIn{from{transform:translateX(60px);opacity:0}to{transform:translateX(0);opacity:1}}

/* ── Room Access Manager ── */
.rm-user-hdr{display:flex;align-items:center;gap:12px;padding:12px 14px;background:linear-gradient(135deg,#f5f3ff,#eef2ff);border:1.5px solid #e0e7ff;border-radius:12px;margin-bottom:14px}
.rm-user-av{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden}
.rm-user-name{font-size:13px;font-weight:700;color:var(--c1)}
.rm-user-at{font-size:11px;color:var(--c3);margin-top:1px}
.rm-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:999px;background:#ede9fe;color:#5b21b6;font-size:11px;font-weight:700;white-space:nowrap;margin-left:auto}
.rm-toolbar{display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
.rm-search{position:relative;flex:1;min-width:180px}
.rm-search i{position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;color:#9ca3af}
.rm-search input{width:100%;padding:8px 10px 8px 32px;border:1.5px solid var(--border);border-radius:9px;font-size:12px;background:#fff;color:var(--c1);font-family:inherit;box-sizing:border-box}
.rm-search input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.rm-bld-sel{padding:7px 28px 7px 10px;border:1.5px solid var(--border);border-radius:9px;font-size:12px;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%239ca3af'/%3E%3C/svg%3E") no-repeat right 10px center;-webkit-appearance:none;appearance:none;color:var(--c1);cursor:pointer;min-width:200px;max-width:260px}
.rm-bld-sel:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.rm-list{max-height:360px;overflow-y:auto;border:1.5px solid var(--border);border-radius:12px;background:#fff}
.rm-bld-hdr{padding:7px 14px;background:linear-gradient(90deg,#eef2ff,#f8faff);border-bottom:1px solid #e0e7ff;font-size:10px;font-weight:800;color:#4338ca;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px;position:sticky;top:0;z-index:1}
.rm-bld-hdr i{font-size:9px}
.rm-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #f5f7fa;transition:background .12s;cursor:pointer}
.rm-item:last-child{border-bottom:none}
.rm-item:hover{background:#fafbff}
.rm-item.sel{background:#f5f3ff}
.rm-item input[type=checkbox]{accent-color:#6366f1;width:15px;height:15px;flex-shrink:0;cursor:pointer}
.rm-item-code{font-family:'Courier New',monospace;font-size:11px;font-weight:700;color:#4338ca;background:#eef2ff;padding:2px 7px;border-radius:5px;flex-shrink:0;white-space:nowrap}
.rm-item-info{flex:1;min-width:0}
.rm-item-name{font-size:12px;font-weight:600;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rm-item-meta{font-size:10px;color:var(--c3);margin-top:1px;display:flex;gap:8px;flex-wrap:wrap}
.rm-item-cnt{font-size:11px;font-weight:700;color:#059669;background:#dcfce7;padding:2px 8px;border-radius:6px;flex-shrink:0;white-space:nowrap}
.rm-item-cnt.zero{color:var(--c3);background:#f1f5f9}
.rm-primary-lbl{display:inline-flex;align-items:center;gap:5px;font-size:10px;color:#6b7280;background:#f8fafc;padding:3px 8px;border-radius:999px;border:1px solid #e5e7eb;cursor:pointer;white-space:nowrap;flex-shrink:0}
.rm-primary-lbl input{accent-color:#059669;margin:0}
.rm-primary-lbl.active{background:#ecfdf5;border-color:#86efac;color:#065f46;font-weight:700}
.rm-empty{padding:24px;text-align:center;color:var(--c3);font-size:12px}
.rm-empty i{font-size:24px;display:block;margin-bottom:8px;opacity:.3}
.rm-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--border);border:1.5px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:12px}
.rm-sum-c{background:#fff;padding:10px 12px;text-align:center}
.rm-sum-v{font-size:20px;font-weight:900;color:var(--c1);line-height:1}
.rm-sum-l{font-size:9px;color:var(--c3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
/* User pill in table/card */
.rm-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:700;border:1px solid #c7d2fe;cursor:pointer;transition:all .15s;white-space:nowrap}
.rm-pill:hover{background:#e0e7ff;border-color:#a5b4fc;transform:translateY(-1px);box-shadow:0 2px 8px rgba(99,102,241,.18)}
.rm-pill-zero{background:#f1f5f9!important;color:#94a3b8!important;border-color:#e2e8f0!important;cursor:default!important;pointer-events:none}
.rm-pill-zero:hover{transform:none!important;box-shadow:none!important}
.rm-primary-tag{display:inline-flex;align-items:center;gap:3px;margin-top:3px;font-size:10px;color:#64748b;font-family:'Courier New',monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px}
.rm-primary-tag .rm-bld{color:#94a3b8;font-size:9px}
/* Room popover */
.rmp-box{background:#fff;border:1.5px solid #e0e7ff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.14),0 2px 8px rgba(99,102,241,.10);min-width:270px;max-width:340px;overflow:hidden;animation:rmpIn .15s ease}
@keyframes rmpIn{from{opacity:0;transform:translateY(6px) scale(.97)}to{opacity:1;transform:none}}
.rmp-hdr{display:flex;align-items:center;gap:8px;padding:12px 14px 10px;border-bottom:1px solid #f0f0f0;background:linear-gradient(135deg,#f5f3ff,#eef2ff)}
.rmp-hdr-ic{width:28px;height:28px;border-radius:8px;background:#6366f1;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;flex-shrink:0;overflow:hidden}
.rmp-hdr-name{font-size:12px;font-weight:700;color:#1e293b;line-height:1.2}
.rmp-hdr-sub{font-size:10px;color:#94a3b8;margin-top:1px}
.rmp-close{margin-left:auto;width:22px;height:22px;border:none;background:none;cursor:pointer;color:#94a3b8;font-size:13px;border-radius:6px;display:flex;align-items:center;justify-content:center;padding:0}
.rmp-close:hover{background:#f1f5f9;color:#475569}
.rmp-body{padding:8px 6px;max-height:260px;overflow-y:auto}
.rmp-row{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:8px;margin-bottom:2px;transition:background .1s}
.rmp-row:hover{background:#f8fafc}
.rmp-row-ic{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;font-family:'Courier New',monospace;font-weight:800}
.rmp-row-ic.primary{background:#6366f1;color:#fff}
.rmp-row-ic.secondary{background:#eef2ff;color:#4338ca}
.rmp-row-info{flex:1;min-width:0}
.rmp-row-code{font-family:'Courier New',monospace;font-size:11px;font-weight:700;color:#1e293b}
.rmp-row-meta{font-size:10px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
.rmp-row-cnt{font-size:10px;font-weight:700;color:#059669;background:#dcfce7;padding:2px 6px;border-radius:5px;flex-shrink:0}
.rmp-star{font-size:9px;color:#f59e0b;margin-left:3px}
.rmp-ft{display:flex;align-items:center;gap:6px;padding:8px 10px 10px;border-top:1px solid #f0f0f0}
.rmp-ft-btn{flex:1;padding:6px 10px;border:1.5px solid #6366f1;border-radius:8px;background:#fff;color:#6366f1;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:all .12s}
.rmp-ft-btn:hover{background:#6366f1;color:#fff}
.rmp-loading{padding:28px;text-align:center;color:#94a3b8;font-size:12px}
.rmp-loading i{display:block;font-size:22px;margin-bottom:8px;opacity:.4}

/* ── Org Section (in modal) ── */
.org-section{margin-top:4px;padding:16px;background:linear-gradient(135deg,#f8fafc 0%,#f0f4ff 100%);border:1.5px solid #e2e8f0;border-radius:12px;position:relative;overflow:hidden}
.org-section::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#6366f1,#e65100,#1a8a5c,#2563eb);border-radius:12px 12px 0 0}
.org-section-hdr{display:flex;align-items:center;gap:8px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid rgba(0,0,0,.06);font-size:13px;font-weight:700;color:var(--c1)}
.org-section-hdr i{color:#6366f1;font-size:14px}
.org-cascade:disabled{background:#f1f5f9;color:#94a3b8;cursor:not-allowed}
.org-breadcrumb{display:flex;align-items:center;gap:6px;margin-top:10px;padding:8px 12px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;font-size:11px;color:var(--c2)}
.org-breadcrumb i{color:#6366f1;font-size:12px;flex-shrink:0}

/* ── Add/Edit Modal Sections ── */
.um-sec{margin-bottom:12px;background:#f8fafc;border:1.5px solid #e8edf5;border-radius:12px;padding:14px 16px}
.um-sec:last-of-type{margin-bottom:0}
.um-sec-hd{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;color:var(--c1);margin-bottom:12px}
.um-sec-hd i{width:22px;height:22px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:10px;flex-shrink:0}
.um-sec-opt{font-size:10px;font-weight:400;color:#94a3b8;margin-left:auto}
/* Room picker trigger */
.um-room-trigger{width:100%;min-height:44px;border:1.5px dashed #c7d2fe;border-radius:10px;background:#fff;display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;font-family:inherit;text-align:left;transition:all .15s}
.um-room-trigger:hover{border-color:#6366f1;background:#f5f3ff}
.um-room-trigger.has-room{border-style:solid;border-color:#6366f1;background:#f5f3ff}
.um-rt-ic{width:28px;height:28px;border-radius:8px;background:#e0e7ff;color:#6366f1;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;transition:all .15s}
.um-room-trigger.has-room .um-rt-ic{background:#6366f1;color:#fff}
.um-rt-ph{font-size:12.5px;color:#94a3b8}
.um-rt-name{font-size:12.5px;font-weight:700;color:#4338ca}
.um-rt-bld{font-size:10px;color:#6366f1;margin-top:1px}
/* Room picker overlay */
.um-sl-ov{position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);z-index:10010;display:none;align-items:center;justify-content:center;padding:16px}
.um-sl-ov.open{display:flex}
.um-sl-modal{background:#fff;border-radius:16px;width:100%;max-width:540px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.25);animation:usrMdIn .18s ease}
.um-sl-hdr{display:flex;align-items:center;gap:12px;padding:15px 18px;border-bottom:1.5px solid #f0f0f0;flex-shrink:0;background:linear-gradient(135deg,#f5f3ff,#eef2ff)}
.um-sl-hdr-ic{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;flex-shrink:0}
.um-sl-hdr-txt{flex:1}
.um-sl-hdr-title{font-size:14px;font-weight:700;color:#1e293b}
.um-sl-hdr-sub{font-size:10px;color:#94a3b8;margin-top:1px}
.um-sl-close{width:30px;height:30px;border:none;background:#e0e7ff;border-radius:8px;cursor:pointer;color:#6366f1;font-size:13px;display:flex;align-items:center;justify-content:center;transition:background .1s;flex-shrink:0}
.um-sl-close:hover{background:#c7d2fe}
.um-sl-nav{display:flex;align-items:center;gap:8px;padding:8px 14px;border-bottom:1px solid #f0f0f0;background:#f8f9ff;flex-shrink:0}
.um-sl-back{background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:#6366f1;padding:4px 8px;border-radius:6px;font-family:inherit;transition:background .1s}
.um-sl-back:hover{background:#ede9fe}
.um-sl-nav-cur{font-size:12px;font-weight:600;color:#374151;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.um-sl-srch-wrap{padding:10px 14px;border-bottom:1px solid #f0f0f0;flex-shrink:0}
.um-sl-srch{width:100%;padding:8px 12px 8px 34px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:12.5px;font-family:inherit;color:#1e293b;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 10px center}
.um-sl-srch:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.12)}
.um-sl-body{flex:1;overflow-y:auto;min-height:0}
.um-sl-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:14px}
.um-sl-bld{display:flex;flex-direction:column;padding:13px 13px 32px;border:1.5px solid #e8ecf0;border-radius:12px;cursor:pointer;background:#fff;border-left-width:4px;transition:box-shadow .15s,transform .1s,background .1s;position:relative;min-height:100px}
.um-sl-bld:hover{box-shadow:0 4px 16px rgba(0,0,0,.10);background:#fafbff;transform:translateY(-1px)}
.um-sl-bld-num{font-size:24px;font-weight:900;line-height:1;letter-spacing:-1px;margin-bottom:6px}
.um-sl-bld-name{font-size:11px;font-weight:700;color:#1e293b;line-height:1.4;flex:1;margin-bottom:4px}
.um-sl-bld-badge{position:absolute;bottom:10px;left:13px;font-size:9px;font-weight:700;color:#64748b;background:#f1f5f9;padding:2px 7px;border-radius:8px;display:flex;align-items:center;gap:4px}
.um-sl-bld-arrow{position:absolute;bottom:12px;right:11px;font-size:10px;color:#d1d5db;transition:right .12s,color .12s}
.um-sl-bld:hover .um-sl-bld-arrow{right:9px;color:#94a3b8}
.um-sl-floor-hdr{font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;padding:8px 18px 5px;background:#fff;border-bottom:1px solid #f0f0f0;position:sticky;top:0;z-index:1}
.um-sl-room{display:flex;align-items:center;gap:11px;padding:11px 18px;cursor:pointer;transition:background .12s;border-bottom:1px solid #f1f5f9}
.um-sl-room:hover{background:#f8f9ff}
.um-sl-room.sel{background:#f0f0ff}
.um-sl-room-chk{width:20px;height:20px;border-radius:50%;border:2px solid #d1d5db;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .12s}
.um-sl-room.sel .um-sl-room-chk{border-color:#6366f1;background:#6366f1}
.um-sl-room-chk i{font-size:9px;color:#fff;opacity:0;transition:opacity .1s}
.um-sl-room.sel .um-sl-room-chk i{opacity:1}
.um-sl-room-ic{width:40px;height:40px;border-radius:10px;background:#ede9fe;color:#6366f1;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:all .12s}
.um-sl-room.sel .um-sl-room-ic{background:#c7d2fe;color:#4338ca}
.um-sl-room-body{flex:1;min-width:0}
.um-sl-room-top{display:flex;align-items:center;gap:6px;margin-bottom:3px}
.um-sl-room-code{font-size:13px;font-weight:800;color:#4338ca;letter-spacing:.3px}
.um-sl-room.sel .um-sl-room-code{color:#3730a3}
.um-sl-room-floor{font-size:9px;color:#64748b;font-weight:600;padding:1px 6px;background:#f1f5f9;border-radius:8px}
.um-sl-room-name{font-size:11px;color:#94a3b8}
.um-sl-room.sel .um-sl-room-name{color:#374151}
.um-sl-footer{border-top:2px solid #e0e7ff;background:#f5f3ff;flex-shrink:0;display:none;padding:10px 16px 12px;align-items:center;gap:10px}
.um-sl-footer.visible{display:flex}
.um-sl-footer-room{font-size:12px;font-weight:700;color:#4338ca}
.um-sl-footer-bld{font-size:10px;color:#94a3b8;margin-top:1px}
.um-sl-confirm{padding:9px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px;flex-shrink:0;transition:background .12s}
.um-sl-confirm:hover{background:#4f46e5}
.um-sl-empty{text-align:center;padding:36px 16px;font-size:12px;color:#94a3b8}
.um-sl-empty i{display:block;font-size:28px;margin-bottom:8px;opacity:.3}

/* ── Import Modal ── */
.imp-info-box{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:16px;font-size:12px;color:#1e40af;line-height:1.5}
.imp-info-box i{font-size:16px;margin-top:2px;flex-shrink:0}
.imp-info-box code{background:rgba(37,99,235,.08);padding:1px 5px;border-radius:4px;font-size:10px}
.imp-upload-zone{border:2.5px dashed #d4d4d4;border-radius:14px;padding:40px 20px;text-align:center;cursor:pointer;transition:all .3s;background:#fafafa}
.imp-upload-zone:hover,.imp-upload-zone.dragover{border-color:#6366f1;background:#f0f0ff}
.imp-upload-zone i{font-size:40px;color:#ddd;margin-bottom:10px;display:block;transition:all .3s}
.imp-upload-zone:hover i{color:#6366f1;transform:translateY(-4px)}
.imp-upload-zone h4{font-size:14px;font-weight:600;color:#333;margin-bottom:4px}
.imp-upload-zone p{font-size:11px;color:#999}
.imp-file-info{display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f0fdf4;border:1px solid #86efac;border-radius:10px;margin-top:12px}
.imp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.imp-stat{padding:12px;background:#f9fafb;border-radius:10px;text-align:center;border:1px solid #f0f0f0}
.imp-stat-val{font-size:22px;font-weight:800;color:var(--c1)}
.imp-stat-lbl{font-size:10px;color:var(--c3);text-transform:uppercase;font-weight:600;margin-top:2px}
.imp-stat.new .imp-stat-val{color:#059669}
.imp-stat.update .imp-stat-val{color:#2563eb}
.imp-stat.error .imp-stat-val{color:#dc2626}
.imp-badge-new{background:#dcfce7;color:#059669;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700}
.imp-badge-update{background:#dbeafe;color:#2563eb;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700}
.imp-badge-error{background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700}
.imp-badge-skip{background:#f3f4f6;color:#888;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700}
.ci-table-sm th,.ci-table-sm td{padding:6px 10px;font-size:12px}
.imp-result-card{padding:24px;text-align:center;border-radius:14px;margin-bottom:12px}
.imp-result-card i{font-size:48px;margin-bottom:12px;display:block}
.imp-result-card h3{font-size:18px;font-weight:700;margin-bottom:4px}
.imp-result-detail{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:16px}
.imp-result-item{padding:10px;background:#f9fafb;border-radius:8px}
.imp-result-item .val{font-size:20px;font-weight:800}
.imp-result-item .lbl{font-size:10px;color:var(--c3);margin-top:2px}

@media(max-width:700px){
    .usr-hero-meta{display:none}
    .usr-grid{grid-template-columns:1fr}
    .usr-t th:nth-child(5),.usr-t td:nth-child(5),
    .usr-t th:nth-child(8),.usr-t td:nth-child(8){display:none}
}
@media(max-width:480px){
    .usr-t th:nth-child(4),.usr-t td:nth-child(4),
    .usr-t th:nth-child(6),.usr-t td:nth-child(6){display:none}
    .imp-stats{grid-template-columns:repeat(2,1fr)}
}

/* ── Import Wizard ── */
.uiw-ov{position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(6px);z-index:9100;display:flex;align-items:center;justify-content:center;padding:16px;animation:uiwFd .2s ease}
@keyframes uiwFd{from{opacity:0}to{opacity:1}}
.uiw-box{background:#fff;border-radius:20px;width:100%;max-width:920px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.22);overflow:hidden;animation:uiwUp .25s cubic-bezier(.22,1,.36,1)}
@keyframes uiwUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
.uiw-hd{background:linear-gradient(135deg,#1e1b4b 0%,#312e81 55%,#6366f1 100%);color:#fff;padding:17px 22px;display:flex;align-items:center;gap:13px;flex-shrink:0}
.uiw-hd-ic{width:42px;height:42px;background:rgba(255,255,255,.18);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.uiw-hd h3{font-size:15px;font-weight:800;margin:0 0 2px}
.uiw-hd p{font-size:11px;opacity:.8;margin:0}
.uiw-hd-x{margin-left:auto;width:32px;height:32px;background:rgba(255,255,255,.15);border:none;border-radius:8px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:background .15s;flex-shrink:0}
.uiw-hd-x:hover{background:rgba(255,255,255,.3)}
.uiw-steps{display:flex;padding:11px 22px;border-bottom:1.5px solid var(--border);background:#fafbff;flex-shrink:0;overflow-x:auto;gap:0}
.uiw-step{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:#94a3b8;white-space:nowrap}
.uiw-step.done{color:#15803d}.uiw-step.active{color:#312e81}
.uiw-sn{width:23px;height:23px;border-radius:50%;border:2px solid currentColor;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0}
.uiw-step.done .uiw-sn{background:#15803d;border-color:#15803d;color:#fff}
.uiw-step.active .uiw-sn{background:#312e81;border-color:#312e81;color:#fff}
.uiw-sa{margin:0 10px;color:#d1d5db;font-size:9px;flex-shrink:0}
.uiw-bd{flex:1;overflow-y:auto;padding:18px 22px;min-height:0}
.uiw-ft{padding:12px 22px;border-top:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:8px;background:#fafbff;flex-shrink:0}
.uiw-drop{border:2.5px dashed #c7d2fe;border-radius:14px;padding:34px 20px;text-align:center;cursor:pointer;transition:all .15s;background:#fafbff}
.uiw-drop:hover,.uiw-drop.ov{border-color:#6366f1;background:#f0f0ff}
.uiw-drop-ic{font-size:36px;color:#6366f1;margin-bottom:10px}
.uiw-drop h4{font-size:15px;font-weight:700;color:var(--c1);margin:0 0 5px}
.uiw-drop p{font-size:12px;color:var(--c3);margin:0}
.uiw-fcard{background:#f0f0ff;border:1.5px solid #c7d2fe;border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:13px;margin-top:12px}
.uiw-fic{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0}
.uiw-map-grid{display:grid;grid-template-columns:1fr 26px 1fr;gap:7px 5px;align-items:center;margin-bottom:6px}
.uiw-map-src{background:#f8fafc;border:1.5px solid var(--border);border-radius:8px;padding:7px 11px;font-size:12px;font-weight:600;color:var(--c1);min-height:34px;display:flex;align-items:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.uiw-map-arr{color:#6366f1;font-size:12px;text-align:center}
.uiw-map-sel{padding:7px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;font-family:inherit;color:var(--c1);background:#fff;cursor:pointer;width:100%}
.uiw-map-sel:focus{outline:none;border-color:#6366f1}
.uiw-sample-box{background:#f0f4ff;border:1.5px solid #e0e7ff;border-radius:10px;padding:10px 13px;margin-top:12px}
.uiw-sample-title{font-size:10px;font-weight:700;color:#312e81;text-transform:uppercase;letter-spacing:.3px;margin-bottom:8px}
.uiw-sum{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:13px}
.uiw-sum-c{background:#f8fafc;border:1.5px solid var(--border);border-radius:10px;padding:10px;text-align:center}
.uiw-sum-v{font-size:22px;font-weight:900;line-height:1}
.uiw-sum-l{font-size:9px;color:var(--c3);margin-top:3px;text-transform:uppercase;letter-spacing:.3px}
.uiw-ftabs{display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap}
.uiw-ftab{padding:4px 12px;border-radius:100px;font-size:11px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:#fff;color:var(--c3);transition:all .12s}
.uiw-ftab.on{background:#6366f1;color:#fff;border-color:#6366f1}
.uiw-tbl-w{max-height:320px;overflow-y:auto;border:1.5px solid var(--border);border-radius:10px}
.uiw-tbl{width:100%;border-collapse:collapse;font-size:12px}
.uiw-tbl th{padding:7px 10px;font-size:10px;text-transform:uppercase;letter-spacing:.3px;color:var(--c3);font-weight:700;border-bottom:2px solid var(--border);white-space:nowrap;text-align:left;position:sticky;top:0;background:#f8fafc;z-index:1}
.uiw-tbl td{padding:6px 10px;border-bottom:1px solid var(--border);vertical-align:middle}
.uiw-tbl tr:last-child td{border-bottom:none}
.uiw-tbl tr:hover td{background:#fafbff}
.uiw-badge{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:100px;white-space:nowrap}
.uiw-new{background:#dcfce7;color:#15803d}.uiw-dup{background:#fef9c3;color:#854d0e}.uiw-err{background:#fee2e2;color:#b91c1c}
.uiw-res-hd{text-align:center;padding:14px 0 6px}
.uiw-res-ic{width:68px;height:68px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 12px}
.uiw-prog{background:#e2e8f0;border-radius:100px;height:8px;overflow:hidden;margin:10px 0}
.uiw-prog-bar{height:100%;border-radius:100px;background:linear-gradient(90deg,#6366f1,#818cf8);transition:width .5s ease}
.uiw-res-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
.uiw-res-c{background:#f8fafc;border:1.5px solid var(--border);border-radius:10px;padding:12px;text-align:center}
.uiw-res-v{font-size:24px;font-weight:900;line-height:1}
.uiw-res-l{font-size:10px;color:var(--c3);margin-top:3px;text-transform:uppercase;letter-spacing:.3px}

/* ── Bulk Selection ── */
.usr-cb{width:16px;height:16px;accent-color:#6366f1;cursor:pointer;display:block}
.usr-t thead th.cb-col,.usr-t tbody td.cb-col{width:38px;padding-left:10px;padding-right:4px;text-align:center}
.usr-t tbody tr.sel-row{background:#eeeeff!important}
.usr-t tbody tr.sel-row:hover{background:#e6e5ff!important}
.usr-card.sel-card{border-color:#6366f1!important;box-shadow:0 0 0 2px #6366f1!important;background:#f5f3ff!important}
.card-cb-ov{position:absolute;top:10px;right:10px;z-index:5;display:flex;align-items:center;justify-content:center}
.card-cb-ov input{width:16px;height:16px;accent-color:#6366f1;cursor:pointer}

/* ── Floating Bulk Bar ── */
.bulk-bar{position:fixed;bottom:-90px;left:50%;transform:translateX(-50%);background:#1e1b4b;color:#fff;border-radius:16px;padding:10px 16px;display:flex;align-items:center;gap:8px;box-shadow:0 8px 40px rgba(0,0,0,.3),0 0 0 1.5px rgba(99,102,241,.5);z-index:8900;transition:bottom .3s cubic-bezier(.34,1.56,.64,1);width:min(700px,92vw);flex-wrap:wrap}
.bulk-bar.show{bottom:24px}
.bulk-cnt{background:rgba(255,255,255,.12);border-radius:8px;padding:5px 12px;font-size:12px;font-weight:600;white-space:nowrap;display:flex;align-items:center;gap:6px;flex-shrink:0}
.bulk-cnt .n{font-size:18px;font-weight:900;color:#a5b4fc;line-height:1}
.bulk-sep{width:1px;height:26px;background:rgba(255,255,255,.18);flex-shrink:0}
.bulk-acts{display:flex;gap:5px;flex-wrap:wrap;align-items:center;flex:1}
.blk{padding:7px 12px;border:none;border-radius:9px;font-size:11.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;font-family:inherit;transition:all .12s;white-space:nowrap}
.blk-role{background:rgba(165,180,252,.18);color:#c7d2fe}.blk-role:hover{background:#6366f1;color:#fff}
.blk-on{background:rgba(134,239,172,.15);color:#86efac}.blk-on:hover{background:#16a34a;color:#fff}
.blk-off{background:rgba(252,165,165,.12);color:#fca5a5}.blk-off:hover{background:#b91c1c;color:#fff}
.blk-room{background:rgba(147,197,253,.15);color:#93c5fd}.blk-room:hover{background:#1d4ed8;color:#fff}
.blk-del{background:rgba(252,165,165,.14);color:#fca5a5}.blk-del:hover{background:#7f1d1d;color:#fff}
.blk-clr{background:none;border:1.5px solid rgba(255,255,255,.22);color:rgba(255,255,255,.65);padding:6px 10px}.blk-clr:hover{border-color:rgba(255,255,255,.6);color:#fff}

/* ── Bulk Room Banner ── */
.rm-bulk-banner{background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1.5px solid #fde68a;border-radius:12px;padding:10px 14px;margin-bottom:12px}
.rm-bulk-hd{font-size:11px;font-weight:700;color:#92400e;display:flex;align-items:center;gap:6px;margin-bottom:8px}
.rm-bulk-avatars{display:flex;flex-wrap:wrap;gap:6px}
.rm-bulk-chip{display:flex;align-items:center;gap:5px;padding:3px 9px 3px 4px;background:#fff;border:1.5px solid #fde68a;border-radius:20px;font-size:11px;font-weight:600;color:#78350f;white-space:nowrap}
.rm-bulk-chip.primary-user{border-color:#6366f1;background:#f0f0ff;color:#4338ca}
.rm-bulk-av-mini{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden}
.rm-bulk-ft{font-size:10px;color:#a16207;margin-top:8px;display:flex;align-items:flex-start;gap:4px;line-height:1.5}

/* ── Bulk Confirm Modal ── */
.bkc-ov{position:fixed;inset:0;background:rgba(15,10,40,.55);backdrop-filter:blur(5px);z-index:9600;display:none;align-items:center;justify-content:center;padding:16px}
.bkc-ov.show{display:flex}
.bkc-box{background:#fff;border-radius:22px;width:96%;max-width:430px;box-shadow:0 28px 70px rgba(0,0,0,.26);animation:usrMdIn .24s cubic-bezier(.34,1.56,.64,1);overflow:hidden}
.bkc-hd{padding:26px 24px 0;text-align:center}
.bkc-ic{width:66px;height:66px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px;transition:transform .2s}
.bkc-ov.show .bkc-ic{animation:bkcPop .35s cubic-bezier(.34,1.56,.64,1) .1s both}
@keyframes bkcPop{from{transform:scale(0)}to{transform:scale(1)}}
.bkc-title{font-size:18px;font-weight:800;color:var(--c1);margin:0 0 5px}
.bkc-desc{font-size:12px;color:var(--c3);margin:0 0 16px;line-height:1.6}
.bkc-users{max-height:230px;overflow-y:auto;border-top:1.5px solid var(--border);border-bottom:1.5px solid var(--border)}
.bkc-user{display:flex;align-items:center;gap:10px;padding:10px 24px;border-bottom:1px solid #f5f7fa}
.bkc-user:last-child{border-bottom:none}
.bkc-user:hover{background:#fafbff}
.bkc-av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;flex-shrink:0}
.bkc-uname{font-size:12.5px;font-weight:700;color:var(--c1)}
.bkc-usub{font-size:10px;color:var(--c3);margin-top:2px;display:flex;align-items:center;gap:5px}
.bkc-type-wrap{padding:16px 24px 0}
.bkc-type-lbl{font-size:11px;color:var(--c3);margin-bottom:7px;font-weight:500}
.bkc-type-inp{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:10px;font-size:13px;font-family:'Courier New',monospace;font-weight:700;color:var(--c1);letter-spacing:2px;text-transform:uppercase;transition:border .15s;box-sizing:border-box;background:#fafafa}
.bkc-type-inp:focus{outline:none;border-color:#6366f1;background:#fff}
.bkc-type-inp.match{border-color:#dc2626!important;background:#fff8f8!important;color:#dc2626}
.bkc-ft{padding:18px 24px 22px;display:flex;gap:8px;justify-content:flex-end}

/* ── Bulk Role Picker ── */
.brp-ov{position:fixed;inset:0;background:rgba(0,0,0,.48);backdrop-filter:blur(3px);z-index:9400;display:none;align-items:center;justify-content:center}
.brp-ov.show{display:flex}
.brp-box{background:#fff;border-radius:18px;width:96%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.22);animation:usrMdIn .22s ease;overflow:hidden}
.brp-hd{padding:16px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px;background:linear-gradient(135deg,#f5f3ff,#eef2ff)}
.brp-hd h3{font-size:14px;font-weight:700;color:var(--c1);margin:0;flex:1}
.brp-hd-ic{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;flex-shrink:0}
.brp-bd{padding:14px 20px 6px}
.brp-sub{font-size:11px;color:var(--c3);margin-bottom:12px;padding:8px 12px;background:#f8fafc;border-radius:8px;display:flex;align-items:center;gap:6px}
.brp-sub i{color:#6366f1}
.brp-grid{display:flex;flex-direction:column;gap:6px;margin-bottom:8px}
.brp-role{width:100%;padding:11px 14px;border:2px solid #e8edf5;border-radius:12px;background:#fff;cursor:pointer;display:flex;align-items:center;gap:12px;font-family:inherit;transition:all .15s;text-align:left}
.brp-role:hover{border-color:#a5b4fc;background:#f5f3ff}
.brp-role.picked{border-color:#6366f1;background:#ede9fe;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.brp-ric{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.brp-rname{font-size:13px;font-weight:700;color:var(--c1)}
.brp-rsub{font-size:10px;color:var(--c3);margin-top:1px}
.brp-ft{padding:12px 20px 18px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid #f0f0f0}
.brp-users{max-height:180px;overflow-y:auto;border:1.5px solid var(--border);border-radius:10px;margin-bottom:12px}
.brp-user{display:flex;align-items:center;gap:10px;padding:9px 12px;border-bottom:1px solid #f5f7fa;transition:background .1s}
.brp-user:last-child{border-bottom:none}
.brp-user:hover{background:#fafbff}
.brp-u-av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden}
.brp-u-name{font-size:12px;font-weight:700;color:var(--c1)}
.brp-u-sub{font-size:10px;color:var(--c3);margin-top:2px;display:flex;align-items:center;gap:4px}
.brp-sec-lbl{font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;padding-top:4px;display:flex;align-items:center;gap:5px}
.brp-sel-wrap{position:relative;margin-bottom:4px}
.brp-sel{width:100%;padding:11px 40px 11px 14px;border:2px solid #e2e8f0;border-radius:12px;font-size:13px;font-weight:600;color:var(--c1);background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='7'%3E%3Cpath d='M0 0l6 7 6-7z' fill='%236366f1'/%3E%3C/svg%3E") no-repeat right 14px center;-webkit-appearance:none;appearance:none;cursor:pointer;font-family:inherit;transition:border .15s,box-shadow .15s}
.brp-sel:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.brp-sel:hover{border-color:#a5b4fc}
</style>

<!-- ── Hero ── -->
<div class="usr-hero">
    <div class="usr-hero-ic"><i class="fas fa-users-cog"></i></div>
    <div class="usr-hero-info">
        <h2><?php echo $TH ? 'จัดการผู้ใช้งาน' : 'User Management'; ?></h2>
        <p><?php echo $TH ? 'ควบคุมสิทธิ์ผู้ใช้ บทบาท และการเข้าถึงห้องปฏิบัติการ' : 'Manage user roles, access permissions, and lab assignments'; ?></p>
    </div>
    <div class="usr-hero-meta">
        <div class="usr-hero-c"><div class="v" id="hTotal">—</div><div class="lb"><?php echo $TH ? 'ผู้ใช้ทั้งหมด' : 'Total Users'; ?></div></div>
        <div class="usr-hero-sep"></div>
        <div class="usr-hero-c"><div class="v" id="hActive">—</div><div class="lb"><?php echo $TH ? 'ใช้งานอยู่' : 'Active'; ?></div></div>
        <div class="usr-hero-sep"></div>
        <div class="usr-hero-c"><div class="v" id="hMulti">—</div><div class="lb"><?php echo $TH ? 'หลายห้อง' : 'Multi-Lab'; ?></div></div>
    </div>
</div>

<!-- ── Stat Cards ── -->
<div class="usr-stats" id="usrStatRow">
    <div class="usr-stat" onclick="filterByRole('all')">
        <div class="usr-si" style="background:#ede9fe;color:#6d28d9"><i class="fas fa-users"></i></div>
        <div><div class="usr-sv" id="sTotalUsers">—</div><div class="usr-sl"><?php echo $TH ? 'ผู้ใช้ทั้งหมด' : 'Total Users'; ?></div></div>
    </div>
    <div class="usr-stat" onclick="filterByRole('inactive')" style="display:none" id="statInactive">
        <div class="usr-si" style="background:#f1f5f9;color:#64748b"><i class="fas fa-ban"></i></div>
        <div><div class="usr-sv" id="sInactive">—</div><div class="usr-sl"><?php echo $TH ? 'ไม่ใช้งาน' : 'Inactive'; ?></div></div>
    </div>
    <div class="usr-stat" onclick="filterByRole('admin')">
        <div class="usr-si" style="background:#fee2e2;color:#b91c1c"><i class="fas fa-crown"></i></div>
        <div><div class="usr-sv" id="sAdmins">—</div><div class="usr-sl">Admin</div></div>
    </div>
    <div class="usr-stat" onclick="filterByRole('lab_manager')">
        <div class="usr-si" style="background:#fef3c7;color:#b45309"><i class="fas fa-user-shield"></i></div>
        <div><div class="usr-sv" id="sManagers">—</div><div class="usr-sl">Lab Manager</div></div>
    </div>
    <div class="usr-stat" onclick="filterByRole('user')">
        <div class="usr-si" style="background:#dcfce7;color:#15803d"><i class="fas fa-user"></i></div>
        <div><div class="usr-sv" id="sUsers">—</div><div class="usr-sl"><?php echo $TH ? 'ผู้ใช้งาน' : 'Users'; ?></div></div>
    </div>
    <div class="usr-stat" onclick="filterByRole('multi_lab')">
        <div class="usr-si" style="background:#e0e7ff;color:#4338ca"><i class="fas fa-project-diagram"></i></div>
        <div><div class="usr-sv" id="sMulti">—</div><div class="usr-sl"><?php echo $TH ? 'หลายห้อง' : 'Multi-Lab'; ?></div></div>
    </div>
</div>

<!-- ── Toolbar ── -->
<div class="usr-toolbar">
    <div class="usr-search">
        <i class="fas fa-search"></i>
        <input type="text" id="userSearch" placeholder="<?php echo $TH ? 'ค้นหาชื่อ, username, อีเมล, ห้อง...' : 'Search name, username, email...'; ?>" oninput="applyFilters()">
    </div>
    <select id="labSort" class="ci-select" style="width:180px;font-size:12px" onchange="applyFilters()">
        <option value="default"><?php echo $TH ? 'เรียงค่าเริ่มต้น' : 'Default sorting'; ?></option>
        <option value="lab_desc"><?php echo $TH ? 'ห้องมาก → น้อย' : 'Labs high → low'; ?></option>
        <option value="lab_asc"><?php echo $TH ? 'ห้องน้อย → มาก' : 'Labs low → high'; ?></option>
    </select>
    <div class="usr-vw" id="viewSwitcher">
        <button class="active" onclick="setView('table')" title="Table"><i class="fas fa-list"></i></button>
        <button onclick="setView('grid')" title="Grid"><i class="fas fa-th-large"></i></button>
    </div>
    <?php if ($isAdmin): ?>
    <div class="usr-ie-wrap">
        <button class="usr-btn usr-btn-g" onclick="toggleImportExport()"><i class="fas fa-exchange-alt"></i> Import/Export</button>
        <div class="usr-ie-dd" id="ieDropdown">
            <div class="usr-ie-head"><i class="fas fa-exchange-alt" style="color:#6366f1"></i> <?php echo $TH ? 'จัดการข้อมูลผู้ใช้' : 'User Data'; ?></div>
            <button class="usr-ie-item" onclick="exportUsersXLSX()"><i class="fas fa-file-excel" style="color:#16a34a"></i><div><div class="usr-ie-title">Export XLSX</div><div class="usr-ie-desc"><?php echo $TH ? 'ดาวน์โหลดเป็น Excel (.xlsx)' : 'Download as Excel (.xlsx)'; ?></div></div></button>
            <button class="usr-ie-item" onclick="exportUsersCSV()"><i class="fas fa-download" style="color:#059669"></i><div><div class="usr-ie-title">Export CSV</div><div class="usr-ie-desc"><?php echo $TH ? 'ดาวน์โหลดข้อมูลทั้งหมด' : 'Download all user data'; ?></div></div></button>
            <button class="usr-ie-item" onclick="exportUsersJSON()"><i class="fas fa-code" style="color:#6366f1"></i><div><div class="usr-ie-title">Export JSON</div><div class="usr-ie-desc"><?php echo $TH ? 'สำรองข้อมูลแบบ JSON' : 'Backup as JSON'; ?></div></div></button>
            <div class="usr-ie-div"></div>
            <button class="usr-ie-item" onclick="downloadTemplate()"><i class="fas fa-file-alt" style="color:#d97706"></i><div><div class="usr-ie-title"><?php echo $TH ? 'ดาวน์โหลด Template CSV' : 'Download CSV Template'; ?></div><div class="usr-ie-desc"><?php echo $TH ? 'แบบฟอร์มสำหรับ import' : 'CSV template for import'; ?></div></div></button>
            <button class="usr-ie-item" onclick="openImportModal()"><i class="fas fa-file-import" style="color:#2563eb"></i><div><div class="usr-ie-title"><?php echo $TH ? 'Import XLSX / CSV' : 'Import XLSX / CSV'; ?></div><div class="usr-ie-desc"><?php echo $TH ? 'นำเข้าผู้ใช้จาก Excel หรือ CSV' : 'Import users from Excel or CSV'; ?></div></div></button>
        </div>
    </div>
    <button class="usr-btn usr-btn-p" onclick="openAddModal()"><i class="fas fa-user-plus"></i> <?php echo $TH ? 'เพิ่มผู้ใช้' : 'Add User'; ?></button>
    <?php endif; ?>
</div>

<!-- ── Role Filter Tabs ── -->
<div class="usr-tabs-wrap">
    <div class="usr-tabs" id="roleFilter">
        <button class="usr-tab active" data-role="all"><?php echo $TH ? 'ทั้งหมด' : 'All'; ?> <span class="bg" id="cAll">0</span></button>
        <button class="usr-tab" data-role="admin"><i class="fas fa-crown" style="color:#b91c1c"></i> Admin</button>
        <button class="usr-tab" data-role="lab_manager"><i class="fas fa-user-shield" style="color:#b45309"></i> Manager</button>
        <button class="usr-tab" data-role="user"><i class="fas fa-user" style="color:#15803d"></i> User</button>
        <button class="usr-tab" data-role="multi_lab"><i class="fas fa-project-diagram" style="color:#4338ca"></i> <?php echo $TH ? 'หลายห้อง' : 'Multi-Lab'; ?> <span class="bg" id="cMulti">0</span></button>
        <button class="usr-tab" data-role="inactive"><i class="fas fa-ban" style="color:var(--c3)"></i> <?php echo $TH ? 'ไม่ใช้งาน' : 'Inactive'; ?></button>
    </div>
    <div id="usrCount" style="font-size:11px;color:var(--c3);white-space:nowrap;flex-shrink:0"></div>
</div>

<!-- ── Content Area ── -->
<div id="usrContent">
    <div class="usr-ld"><i class="fas fa-circle-notch"></i> <?php echo $TH ? 'กำลังโหลด...' : 'Loading...'; ?></div>
</div>

<!-- ════ MODALS ════ -->

<!-- Add/Edit User -->
<div class="usr-ov" id="userModal">
    <div class="usr-md" style="max-width:660px">
        <div class="usr-mh">
            <h3 id="modalTitle"><i class="fas fa-user-plus" style="color:#6366f1"></i> <?php echo $TH ? 'เพิ่มผู้ใช้' : 'Add User'; ?></h3>
            <button class="usr-mx" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="usr-mb">
            <form id="userForm" onsubmit="saveUser(event)">
                <input type="hidden" id="fUserId" value="">

                <!-- ข้อมูลส่วนตัว -->
                <div class="um-sec">
                    <div class="um-sec-hd">
                        <i class="fas fa-user" style="background:#ede9fe;color:#6366f1"></i>
                        <?php echo $TH ? 'ข้อมูลส่วนตัว' : 'Personal Info'; ?>
                    </div>
                    <div class="ci-g2">
                        <div class="ci-fg"><label class="ci-label"><?php echo $TH?'ชื่อ':'First Name'?> <span style="color:#dc2626">*</span></label><input type="text" id="fFirstName" class="ci-input" required placeholder="<?php echo $TH?'ชื่อจริง':'First name'?>"></div>
                        <div class="ci-fg"><label class="ci-label"><?php echo $TH?'นามสกุล':'Last Name'?> <span style="color:#dc2626">*</span></label><input type="text" id="fLastName" class="ci-input" required placeholder="<?php echo $TH?'นามสกุล':'Last name'?>"></div>
                    </div>
                    <div class="ci-g2">
                        <div class="ci-fg"><label class="ci-label">Username <span style="color:#dc2626">*</span></label><input type="text" id="fUsername" class="ci-input" required autocomplete="off"></div>
                        <div class="ci-fg"><label class="ci-label">Email <span style="color:#dc2626">*</span></label><input type="email" id="fEmail" class="ci-input" required placeholder="email@example.com"></div>
                    </div>
                    <div class="ci-g2" style="margin-bottom:0">
                        <div class="ci-fg" style="margin-bottom:0"><label class="ci-label"><?php echo $TH?'เบอร์โทร':'Phone'?></label><input type="text" id="fPhone" class="ci-input" placeholder="0X-XXXX-XXXX"></div>
                        <div class="ci-fg" style="margin-bottom:0"><label class="ci-label"><?php echo $TH?'รหัสผ่าน':'Password'?> <span id="pwHint" style="font-weight:400;color:var(--c3);font-size:10px"></span></label><input type="password" id="fPassword" class="ci-input" minlength="6" placeholder="<?php echo $TH?'อย่างน้อย 6 ตัวอักษร':'Min 6 characters'?>" autocomplete="new-password"></div>
                    </div>
                </div>

                <!-- บทบาทและสิทธิ์ -->
                <div class="um-sec">
                    <div class="um-sec-hd">
                        <i class="fas fa-shield-alt" style="background:#e0e7ff;color:#6366f1"></i>
                        <?php echo $TH?'บทบาทและสิทธิ์':'Role & Permissions'?>
                    </div>
                    <div class="ci-fg" style="margin-bottom:0">
                        <label class="ci-label"><?php echo $TH?'บทบาท':'Role'?> <span style="color:#dc2626">*</span></label>
                        <select id="fRole" class="ci-select" required>
                            <option value="">-- <?php echo $TH?'เลือกบทบาท':'Select Role'?> --</option>
                        </select>
                    </div>
                </div>

                <!-- สังกัดองค์กร -->
                <div class="org-section">
                    <div class="org-section-hdr"><i class="fas fa-sitemap"></i> <?php echo $TH?'สังกัดองค์กร':'Organization'?></div>
                    <div class="ci-g2" style="margin-bottom:0">
                        <div class="ci-fg"><label class="ci-label"><i class="fas fa-building" style="margin-right:4px;font-size:10px;color:#6366f1"></i><?php echo $TH?'ศูนย์':'Center'?></label><select id="fCenter" class="ci-select org-cascade" data-level="center" onchange="onOrgChange('center')"><option value="">-- <?php echo $TH?'เลือกศูนย์':'Select Center'?> --</option></select></div>
                        <div class="ci-fg"><label class="ci-label"><i class="fas fa-sitemap" style="margin-right:4px;font-size:10px;color:#e65100"></i><?php echo $TH?'ฝ่าย':'Division'?></label><select id="fDivision" class="ci-select org-cascade" data-level="division" onchange="onOrgChange('division')" disabled><option value="">-- <?php echo $TH?'เลือกฝ่าย':'Select Division'?> --</option></select></div>
                    </div>
                    <div class="ci-g2" style="margin-bottom:0">
                        <div class="ci-fg" style="margin-bottom:0"><label class="ci-label"><i class="fas fa-layer-group" style="margin-right:4px;font-size:10px;color:#1a8a5c"></i><?php echo $TH?'งาน':'Section'?></label><select id="fSection" class="ci-select org-cascade" data-level="section" onchange="onOrgChange('section')" disabled><option value="">-- <?php echo $TH?'เลือกงาน':'Select Section'?> --</option></select></div>
                        <div class="ci-fg" style="margin-bottom:0"><label class="ci-label"><i class="fas fa-warehouse" style="margin-right:4px;font-size:10px;color:#2563eb"></i><?php echo $TH?'คลัง':'Store'?></label><select id="fStore" class="ci-select org-cascade" data-level="store" onchange="onOrgChange('store')" disabled><option value="">-- <?php echo $TH?'เลือกคลัง':'Select Store'?> --</option></select></div>
                    </div>
                    <div class="org-breadcrumb" id="orgBreadcrumb" style="display:none"><i class="fas fa-map-marker-alt"></i><span id="orgBreadcrumbText"></span></div>
                </div>

                <!-- ห้องปฏิบัติการหลัก (Add only) -->
                <div class="um-sec" id="umRoomSec">
                    <div class="um-sec-hd">
                        <i class="fas fa-door-open" style="background:#dbeafe;color:#2563eb"></i>
                        <?php echo $TH?'ห้องปฏิบัติการหลัก':'Primary Lab Room'?>
                        <span class="um-sec-opt"><?php echo $TH?'ไม่บังคับ — จัดการเพิ่มได้ภายหลัง':'optional — manageable later'?></span>
                    </div>
                    <button type="button" class="um-room-trigger" id="umRoomTrigger" onclick="umSlOpen()">
                        <div class="um-rt-ic"><i class="fas fa-flask"></i></div>
                        <div class="um-rt-txt" id="umRoomTrigTxt"><span class="um-rt-ph"><?php echo $TH?'เลือกห้องปฏิบัติการ...':'Select laboratory...'?></span></div>
                        <i class="fas fa-chevron-right" style="font-size:11px;color:#94a3b8;flex-shrink:0"></i>
                    </button>
                    <input type="hidden" id="fRoomId" value="">
                </div>

                <!-- Actions -->
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                    <button type="button" class="usr-btn usr-btn-o" style="border-color:#e2e8f0;color:#64748b" onclick="closeModal()"><i class="fas fa-times"></i> <?php echo $TH?'ยกเลิก':'Cancel'?></button>
                    <button type="submit" class="usr-btn usr-btn-p" id="saveBtn"><i class="fas fa-save"></i> <?php echo $TH?'บันทึก':'Save'?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Room Picker Modal (for Add User) -->
<div class="um-sl-ov" id="umSlOv">
    <div class="um-sl-modal">
        <div class="um-sl-hdr">
            <div class="um-sl-hdr-ic"><i class="fas fa-flask-vial"></i></div>
            <div class="um-sl-hdr-txt">
                <div class="um-sl-hdr-title"><?php echo $TH?'เลือกห้องปฏิบัติการ':'Select Laboratory'?></div>
                <div class="um-sl-hdr-sub" id="umSlHdrSub"><?php echo $TH?'เลือกอาคารเพื่อดูรายการห้อง':'Select a building to browse rooms'?></div>
            </div>
            <button type="button" class="um-sl-close" onclick="umSlClose()"><i class="fas fa-times"></i></button>
        </div>
        <div class="um-sl-nav" id="umSlNav" style="display:none">
            <button type="button" class="um-sl-back" onclick="umSlGoGrid()"><i class="fas fa-arrow-left"></i> <?php echo $TH?'อาคารทั้งหมด':'All Buildings'?></button>
            <span style="color:#94a3b8;font-size:12px;flex-shrink:0">/</span>
            <span class="um-sl-nav-cur" id="umSlNavCur"></span>
        </div>
        <div class="um-sl-srch-wrap">
            <input type="text" class="um-sl-srch" id="umSlSearch" placeholder="<?php echo $TH?'ค้นหาชื่ออาคาร หรือรหัสห้อง...':'Search building or room code...'?>" autocomplete="off">
        </div>
        <div class="um-sl-body" id="umSlBody"><div class="um-sl-empty"><i class="fas fa-spinner fa-spin"></i></div></div>
        <div class="um-sl-footer" id="umSlFooter">
            <div style="flex:1;min-width:0">
                <div class="um-sl-footer-room" id="umSlFooterRoom">–</div>
                <div class="um-sl-footer-bld" id="umSlFooterBld"></div>
            </div>
            <button type="button" class="um-sl-confirm" onclick="umSlConfirm()"><i class="fas fa-check-circle"></i> <?php echo $TH?'ยืนยัน':'Confirm'?></button>
        </div>
    </div>
</div>

<!-- User Detail -->
<div class="usr-ov" id="detailModal">
    <div class="usr-md usr-md-sm">
        <div class="usr-mh">
            <h3><i class="fas fa-id-card" style="color:#6366f1"></i> <?php echo $TH ? 'ข้อมูลผู้ใช้' : 'User Details'; ?></h3>
            <button class="usr-mx" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="usr-mb" id="detailContent"></div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Room Access Modal -->
<div class="usr-ov" id="roomModal">
    <div class="usr-md" style="max-width:800px">
        <div class="usr-mh">
            <h3><i class="fas fa-map-marker-alt" style="color:#6366f1"></i> <?php echo $TH ? 'ห้องที่ดูแล' : 'Managed Rooms'; ?></h3>
            <button class="usr-mx" onclick="closeRoomModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="usr-mb">
            <!-- Bulk mode banner (shown only when multiple users selected) -->
            <div id="rmBulkBanner" class="rm-bulk-banner" style="display:none">
                <div class="rm-bulk-hd">
                    <i class="fas fa-users"></i>
                    <span><?php echo $TH?'กำหนดห้องแบบกลุ่ม':'Bulk room assignment'?> — <strong id="rmBulkCount">0</strong> <?php echo $TH?'ผู้ใช้ที่เลือก':'selected users'?></span>
                </div>
                <div class="rm-bulk-avatars" id="rmBulkAvatars"></div>
                <div class="rm-bulk-ft"><i class="fas fa-info-circle" style="color:#d97706;flex-shrink:0;margin-top:1px"></i> <span id="rmBulkHint"></span></div>
            </div>
            <!-- User header -->
            <div class="rm-user-hdr">
                <div class="rm-user-av" id="rmUserAv" style="background:#6366f1">—</div>
                <div>
                    <div class="rm-user-name" id="rmUserName">—</div>
                    <div class="rm-user-at" id="rmUserAt">—</div>
                </div>
                <div class="rm-chip" id="rmChip"><i class="fas fa-door-open"></i> 0 ห้อง</div>
            </div>
            <!-- Toolbar -->
            <div class="rm-toolbar">
                <div class="rm-search"><i class="fas fa-search"></i><input type="text" id="rmSearch" placeholder="<?php echo $TH ? 'ค้นหารหัสห้อง หรือชื่อห้อง...' : 'Search room code or name...'; ?>" oninput="renderRoomList()"></div>
                <select class="rm-bld-sel" id="rmBldFilter" onchange="renderRoomList()">
                    <option value=""><?php echo $TH ? 'ทุกอาคาร' : 'All buildings'; ?></option>
                </select>
            </div>
            <!-- Room list -->
            <div class="rm-list" id="rmList"><div class="usr-ld"><i class="fas fa-circle-notch"></i></div></div>
            <!-- Footer -->
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;padding-top:12px;border-top:1px solid var(--border);align-items:center">
                <span style="flex:1;font-size:11px;color:var(--c3)"><i class="fas fa-info-circle" style="color:#6366f1;margin-right:4px"></i><?php echo $TH ? 'ติ๊กเลือกห้อง · กำหนด Primary 1 ห้อง' : 'Check rooms · set one as primary'; ?></span>
                <button class="usr-btn usr-btn-g" onclick="closeRoomModal()"><?php echo $TH ? 'ยกเลิก' : 'Cancel'; ?></button>
                <button class="usr-btn usr-btn-p" id="rmSaveBtn" onclick="saveRoomAccess()"><i class="fas fa-save"></i> <?php echo $TH ? 'บันทึก' : 'Save'; ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Import Wizard -->
<div class="uiw-ov" id="importWizard" style="display:none">
    <div class="uiw-box">
        <div class="uiw-hd">
            <div class="uiw-hd-ic"><i class="fas fa-file-import"></i></div>
            <div>
                <h3><?php echo $TH?'นำเข้าข้อมูลผู้ใช้':'Import Users'; ?></h3>
                <p id="uiwSubtitle"><?php echo $TH?'รองรับไฟล์ .xlsx และ .csv':'Supports .xlsx and .csv files'; ?></p>
            </div>
            <button class="uiw-hd-x" onclick="uiwClose()"><i class="fas fa-times"></i></button>
        </div>
        <div class="uiw-steps">
            <div class="uiw-step active" id="uiwS1"><div class="uiw-sn">1</div><?php echo $TH?'อัปโหลด':'Upload'; ?></div>
            <div class="uiw-sa"><i class="fas fa-chevron-right"></i></div>
            <div class="uiw-step" id="uiwS2"><div class="uiw-sn">2</div><?php echo $TH?'กำหนด Column':'Map Columns'; ?></div>
            <div class="uiw-sa"><i class="fas fa-chevron-right"></i></div>
            <div class="uiw-step" id="uiwS3"><div class="uiw-sn">3</div><?php echo $TH?'ตรวจสอบ':'Preview'; ?></div>
            <div class="uiw-sa"><i class="fas fa-chevron-right"></i></div>
            <div class="uiw-step" id="uiwS4"><div class="uiw-sn">4</div><?php echo $TH?'ผลลัพธ์':'Results'; ?></div>
        </div>
        <div class="uiw-bd">
            <!-- Step 1: Upload -->
            <div id="uiwStep1">
                <div class="uiw-drop" id="uiwDrop" onclick="document.getElementById('uiwFileInput').click()">
                    <div class="uiw-drop-ic"><i class="fas fa-cloud-upload-alt"></i></div>
                    <h4><?php echo $TH?'ลากไฟล์มาวาง หรือคลิกเพื่อเลือกไฟล์':'Drag & drop or click to browse'; ?></h4>
                    <p><?php echo $TH?'รองรับ .xlsx (Excel) และ .csv · สูงสุด 10MB':'Supports .xlsx and .csv · Max 10MB'; ?></p>
                </div>
                <input type="file" id="uiwFileInput" accept=".xlsx,.csv,.txt" style="display:none" onchange="uiwOnFile(this)">
                <div id="uiwFileCard" style="display:none" class="uiw-fcard">
                    <div class="uiw-fic" id="uiwFileIc" style="background:#16a34a"><i class="fas fa-file-excel"></i></div>
                    <div style="flex:1">
                        <div style="font-size:14px;font-weight:700" id="uiwFileName"></div>
                        <div style="font-size:11px;color:var(--c3);margin-top:3px" id="uiwFileMeta"></div>
                    </div>
                    <button class="usr-btn usr-btn-d" style="padding:4px 10px;font-size:11px" onclick="uiwClearFile()"><i class="fas fa-times"></i></button>
                </div>
                <div style="margin-top:14px;padding:11px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;font-size:12px;color:#92400e">
                    <i class="fas fa-lightbulb" style="margin-right:6px;color:#d97706"></i>
                    <?php echo $TH?'<strong>Username และ Password</strong> จะถูกกำหนดจาก <strong>รหัสพนักงาน</strong> โดยอัตโนมัติ':'<strong>Username & Password</strong> will be set from <strong>Employee ID</strong> automatically'; ?>
                </div>
            </div>
            <!-- Step 2: Column Mapping -->
            <div id="uiwStep2" style="display:none">
                <p style="font-size:12px;color:var(--c3);margin:0 0 13px"><?php echo $TH?'ระบบตรวจพบ Column ต่อไปนี้ กรุณาตรวจสอบการจับคู่ให้ถูกต้อง':'Columns detected from file. Verify the field mapping below.'; ?></p>
                <div style="display:grid;grid-template-columns:1fr 26px 1fr;gap:0 5px;margin-bottom:7px;padding:0 2px">
                    <div style="font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.3px"><?php echo $TH?'Column ในไฟล์':'File Column'; ?></div>
                    <div></div>
                    <div style="font-size:10px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.3px"><?php echo $TH?'ฟิลด์ระบบ':'System Field'; ?></div>
                </div>
                <div id="uiwMapRows"></div>
                <div class="uiw-sample-box">
                    <div class="uiw-sample-title"><i class="fas fa-eye" style="margin-right:4px"></i><?php echo $TH?'ตัวอย่างข้อมูล 3 แถวแรก':'Preview: first 3 rows'; ?></div>
                    <div id="uiwSampleContent" style="overflow-x:auto;font-size:11px"></div>
                </div>
            </div>
            <!-- Step 3: Preview & Validate -->
            <div id="uiwStep3" style="display:none">
                <div class="uiw-sum" id="uiwSum"></div>
                <div class="uiw-ftabs" id="uiwFtabs"></div>
                <div class="uiw-tbl-w"><table class="uiw-tbl"><thead><tr>
                    <th>#</th>
                    <th><?php echo $TH?'รหัส/Username':'ID / Username'; ?></th>
                    <th><?php echo $TH?'ชื่อ-นามสกุล':'Full Name'; ?></th>
                    <th><?php echo $TH?'ตำแหน่ง':'Position'; ?></th>
                    <th><?php echo $TH?'ฝ่าย/งาน':'Dept / Section'; ?></th>
                    <th>Email</th>
                    <th><?php echo $TH?'สถานะ':'Status'; ?></th>
                </tr></thead><tbody id="uiwPreviewBody"></tbody></table></div>
                <div style="margin-top:10px;font-size:12px">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="checkbox" id="uiwUpdateExisting" style="accent-color:#6366f1">
                        <span><?php echo $TH?'อัปเดตผู้ใช้ที่มี username ซ้ำ':'Update existing users with matching username'; ?></span>
                    </label>
                </div>
            </div>
            <!-- Step 4: Results -->
            <div id="uiwStep4" style="display:none">
                <div class="uiw-res-hd" id="uiwResHd"></div>
                <div class="uiw-prog"><div class="uiw-prog-bar" id="uiwProgBar" style="width:0%"></div></div>
                <div class="uiw-res-grid" id="uiwResGrid"></div>
                <div id="uiwErrList"></div>
            </div>
        </div>
        <div class="uiw-ft">
            <div><button class="usr-btn usr-btn-g" id="uiwBtnBack" onclick="uiwBack()" style="display:none"><i class="fas fa-arrow-left"></i> <?php echo $TH?'กลับ':'Back'; ?></button></div>
            <div style="display:flex;gap:8px">
                <button class="usr-btn usr-btn-g" id="uiwBtnCancel" onclick="uiwClose()"><?php echo $TH?'ยกเลิก':'Cancel'; ?></button>
                <button class="usr-btn usr-btn-p" id="uiwBtnNext" onclick="uiwNext()" disabled><i class="fas fa-arrow-right"></i> <?php echo $TH?'ถัดไป':'Next'; ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast container -->
<!-- Room popover -->
<div id="rmPopover" style="display:none;position:fixed;z-index:9800">
    <div id="rmPopoverBox"></div>
</div>

<div class="usr-toast" id="usrToast"></div>

<?php Layout::endContent(); ?>

<script>
const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const currentUserId = <?php echo (int)$user['id']; ?>;
const TH = <?php echo $TH ? 'true' : 'false'; ?>;

let allUsers = [], allRoles = [], allStores = [];
let currentFilter = 'all', currentView = 'table';
let roomState = { userId:null, allRooms:[], selected:[], primary:null, buildings:[] };
let selectedUsers = new Set();
let _bulkRoomUserIds = null;

/* ─── Bulk Selection ─── */
function toggleSelect(userId, cb) {
    userId = parseInt(userId);
    if (cb.checked) selectedUsers.add(userId);
    else selectedUsers.delete(userId);
    const tr = cb.closest('tr');
    if (tr) tr.classList.toggle('sel-row', cb.checked);
    const card = cb.closest('.usr-card');
    if (card) card.classList.toggle('sel-card', cb.checked);
    updateBulkBar();
}

function toggleSelectAll(cb) {
    const vis = [...document.querySelectorAll('.usr-cb[id^="cb_"]')].map(c => parseInt(c.id.replace('cb_','')));
    vis.forEach(id => {
        if (cb.checked) selectedUsers.add(id); else selectedUsers.delete(id);
        const c = document.getElementById('cb_' + id);
        if (c) {
            c.checked = cb.checked;
            const tr = c.closest('tr'); if (tr) tr.classList.toggle('sel-row', cb.checked);
            const card = c.closest('.usr-card'); if (card) card.classList.toggle('sel-card', cb.checked);
        }
    });
    updateBulkBar();
}

function updateBulkBar() {
    const n = selectedUsers.size;
    const bar = document.getElementById('bulkBar');
    if (!bar) return;
    bar.classList.toggle('show', n > 0);
    const cntEl = document.getElementById('bulkCnt');
    if (cntEl) cntEl.textContent = n;
    const cbAll = document.getElementById('cbSelectAll');
    if (cbAll) {
        const vis = [...document.querySelectorAll('.usr-cb[id^="cb_"]')].map(c => parseInt(c.id.replace('cb_','')));
        const allSel = vis.length > 0 && vis.every(id => selectedUsers.has(id));
        cbAll.checked = allSel;
        cbAll.indeterminate = !allSel && vis.some(id => selectedUsers.has(id));
    }
}

function clearSelection() {
    selectedUsers.clear();
    document.querySelectorAll('.usr-cb[id^="cb_"]').forEach(c => {
        c.checked = false;
        const tr = c.closest('tr'); if (tr) tr.classList.remove('sel-row');
        const card = c.closest('.usr-card'); if (card) card.classList.remove('sel-card');
    });
    const cbAll = document.getElementById('cbSelectAll');
    if (cbAll) { cbAll.checked = false; cbAll.indeterminate = false; }
    updateBulkBar();
}

/* ─── Bulk Confirm Modal ─── */
let _bkcCallback = null;

function showBulkConfirm({ icon, iconBg, iconColor, title, desc, users, confirmLabel, confirmClass, requireType }) {
    document.getElementById('bkcIcon').className = `fas ${icon}`;
    const ic = document.getElementById('bkcIc');
    ic.style.background = iconBg; ic.style.color = iconColor;
    document.getElementById('bkcTitle').textContent = title;
    document.getElementById('bkcDesc').textContent = desc;
    document.getElementById('bkcUserList').innerHTML = users.map(u => {
        const ini = ((u.first_name||'')[0]||'') + ((u.last_name||'')[0]||'');
        const bg  = roleAvBg[u.role_name] || '#6366f1';
        const nm  = `${u.first_name||''} ${u.last_name||''}`.trim();
        return `<div class="bkc-user">
            <div class="bkc-av" style="background:${bg}">${escHtml(ini.toUpperCase()||'?')}</div>
            <div>
                <div class="bkc-uname">${escHtml(nm)}</div>
                <div class="bkc-usub">@${escHtml(u.username||'')} <span class="usr-role ${roleColors[u.role_name]||'usr-role-visitor'}" style="font-size:9px;padding:1px 6px"><i class="fas ${roleIcons[u.role_name]||'fa-user'}"></i> ${escHtml(u.role_display||u.role_name||'')}</span></div>
            </div>
        </div>`;
    }).join('');
    const typeWrap = document.getElementById('bkcTypeWrap');
    const typeInp  = document.getElementById('bkcTypeInp');
    if (requireType) {
        typeWrap.style.display = '';
        typeInp.value = '';
        typeInp.classList.remove('match');
        document.getElementById('bkcTypeLbl').textContent = TH ? 'พิมพ์ DELETE เพื่อยืนยันการลบถาวร' : 'Type DELETE to confirm permanent deletion';
    } else {
        typeWrap.style.display = 'none';
    }
    const confirmBtn = document.getElementById('bkcConfirmBtn');
    confirmBtn.textContent = confirmLabel;
    confirmBtn.className = `usr-btn ${confirmClass}`;
    confirmBtn.style.minWidth = '100px';
    confirmBtn.disabled = !!requireType;
    document.getElementById('bkcOv').classList.add('show');
    if (requireType) setTimeout(() => typeInp.focus(), 180);
}

function onBkcType(inp) {
    const ok = inp.value.trim().toUpperCase() === 'DELETE';
    inp.classList.toggle('match', ok);
    document.getElementById('bkcConfirmBtn').disabled = !ok;
}

function closeBulkConfirm() {
    document.getElementById('bkcOv').classList.remove('show');
    _bkcCallback = null;
}

function execBulkConfirm() {
    const cb = _bkcCallback;
    closeBulkConfirm();
    if (cb) cb();
}

async function bulkToggle(makeActive) {
    if (!isAdmin) return;
    const ids = [...selectedUsers].filter(id => id !== currentUserId);
    if (!ids.length) { showToast(TH?'ไม่มีผู้ใช้ที่เลือกได้':'No eligible users selected','err'); return; }
    const label = makeActive ? (TH?'เปิดใช้งาน':'Activate') : (TH?'ปิดใช้งาน':'Deactivate');
    const users = ids.map(id => allUsers.find(x => parseInt(x.id) === id)).filter(Boolean);
    _bkcCallback = async () => {
        let ok = 0, fail = 0;
        await Promise.all(ids.map(async id => {
            try {
                const res = await apiFetch('/v1/api/auth.php?action=users_update', { method:'POST', body:JSON.stringify({ user_id: id, is_active: makeActive ? 1 : 0 }) });
                if (res.success) ok++; else fail++;
            } catch { fail++; }
        }));
        showToast(TH ? `${label}สำเร็จ ${ok} รายการ${fail?' · ผิดพลาด '+fail:''}` : `${label}d ${ok}${fail?' · '+fail+' failed':''}`, fail && !ok ? 'err' : 'ok');
        clearSelection(); loadData();
    };
    showBulkConfirm({
        icon: makeActive ? 'fa-check-circle' : 'fa-ban',
        iconBg: makeActive ? '#dcfce7' : '#fee2e2',
        iconColor: makeActive ? '#15803d' : '#b91c1c',
        title: label,
        desc: TH ? `${users.length} ผู้ใช้ต่อไปนี้จะถูก${label}` : `${users.length} user(s) below will be ${makeActive ? 'activated' : 'deactivated'}`,
        users,
        confirmLabel: label,
        confirmClass: makeActive ? 'usr-btn-p' : 'usr-btn-d',
        requireType: false,
    });
}

async function bulkDelete() {
    if (!isAdmin) return;
    const ids = [...selectedUsers].filter(id => id !== currentUserId);
    if (!ids.length) { showToast(TH?'ไม่มีผู้ใช้ที่เลือกได้':'No eligible users selected','err'); return; }
    const inactive = ids.filter(id => { const u = allUsers.find(x => parseInt(x.id) === id); return u && !parseInt(u.is_active); });
    if (!inactive.length) { showToast(TH?'เฉพาะผู้ใช้ที่ปิดใช้งานเท่านั้นที่ลบได้':'Only inactive users can be deleted','err'); return; }
    const users = inactive.map(id => allUsers.find(x => parseInt(x.id) === id)).filter(Boolean);
    _bkcCallback = async () => {
        let ok = 0, fail = 0;
        for (const id of inactive) {
            try { const res = await apiFetch('/v1/api/auth.php?action=users_delete', { method:'POST', body:JSON.stringify({ user_id: id }) }); if (res.success) ok++; else fail++; }
            catch { fail++; }
        }
        showToast(TH ? `ลบสำเร็จ ${ok} รายการ${fail?' · ผิดพลาด '+fail:''}` : `Deleted ${ok}${fail?' · '+fail+' failed':''}`, fail && !ok ? 'err' : 'ok');
        clearSelection(); loadData();
    };
    showBulkConfirm({
        icon: 'fa-trash',
        iconBg: '#fee2e2',
        iconColor: '#b91c1c',
        title: TH ? 'ลบผู้ใช้ถาวร' : 'Delete Users',
        desc: TH ? `${users.length} ผู้ใช้ต่อไปนี้จะถูกลบออกจากระบบอย่างถาวร` : `${users.length} user(s) below will be permanently deleted`,
        users,
        confirmLabel: TH ? 'ลบถาวร' : 'Delete',
        confirmClass: 'usr-btn-d',
        requireType: true,
    });
}

function openBulkRolePicker() {
    if (!isAdmin || !selectedUsers.size) return;
    const ids = [...selectedUsers];
    const n   = ids.length;
    // Subtitle
    const sub = document.getElementById('brpSub');
    if (sub) sub.innerHTML = `<i class="fas fa-users"></i> ${TH ? `เปลี่ยนสิทธิ์ผู้ใช้ ${n} รายการที่เลือก` : `Change role for ${n} selected user(s)`}`;
    // User list
    const uList = document.getElementById('brpUserList');
    if (uList) uList.innerHTML = ids.map(id => {
        const u = allUsers.find(x => parseInt(x.id) === id);
        if (!u) return '';
        const ini = ((u.first_name||'')[0]||'') + ((u.last_name||'')[0]||'');
        const bg  = roleAvBg[u.role_name] || '#6366f1';
        const nm  = `${u.first_name||''} ${u.last_name||''}`.trim();
        return `<div class="brp-user">
            <div class="brp-u-av" style="background:${bg}">${escHtml(ini.toUpperCase()||'?')}</div>
            <div style="flex:1;min-width:0">
                <div class="brp-u-name">${escHtml(nm)}</div>
                <div class="brp-u-sub">@${escHtml(u.username||'')} <span class="usr-role ${roleColors[u.role_name]||'usr-role-visitor'}" style="font-size:9px;padding:1px 6px"><i class="fas ${roleIcons[u.role_name]||'fa-user'}"></i> ${escHtml(u.role_display||u.role_name||'')}</span></div>
            </div>
        </div>`;
    }).join('');
    // Role dropdown
    const sel = document.getElementById('brpRoleSelect');
    if (sel) {
        sel.innerHTML = `<option value="">${TH ? '— เลือกสิทธิ์ —' : '— Select role —'}</option>` +
            allRoles.map(r => `<option value="${r.id}" data-name="${escHtml(r.display_name)}">${escHtml(r.display_name)}</option>`).join('');
        sel.value = '';
    }
    document.getElementById('bulkRolePicker').classList.add('show');
    if (sel) setTimeout(() => sel.focus(), 120);
}

function closeBulkRolePicker() { document.getElementById('bulkRolePicker').classList.remove('show'); }

function pickBulkRole(btn) {
    document.querySelectorAll('.brp-role').forEach(b => b.classList.remove('picked'));
    btn.classList.add('picked');
}

async function applyBulkRole() {
    const sel = document.getElementById('brpRoleSelect');
    if (!sel || !sel.value) { showToast(TH?'กรุณาเลือกสิทธิ์':'Please select a role','err'); return; }
    const roleId   = parseInt(sel.value);
    const roleName = sel.options[sel.selectedIndex]?.dataset?.name || sel.options[sel.selectedIndex]?.text || '';
    const ids = [...selectedUsers].filter(id => id !== currentUserId);
    if (!ids.length) { closeBulkRolePicker(); return; }
    closeBulkRolePicker();
    let ok = 0, fail = 0;
    await Promise.all(ids.map(async id => {
        try { const res = await apiFetch('/v1/api/auth.php?action=users_update', { method:'POST', body:JSON.stringify({ user_id: id, role_id: roleId }) }); if (res.success) ok++; else fail++; }
        catch { fail++; }
    }));
    showToast(TH ? `เปลี่ยนสิทธิ์เป็น "${roleName}" สำเร็จ ${ok} รายการ${fail?' · ผิดพลาด '+fail:''}` : `Changed to "${roleName}": ${ok} done${fail?' · '+fail+' failed':''}`, fail && !ok ? 'err' : 'ok');
    clearSelection(); loadData();
}

function bulkManageRooms() {
    const ids = [...selectedUsers];
    if (!ids.length) return;
    _bulkRoomUserIds = ids;
    openRoomModal(ids[0]);
}

/* ─── Role/Avatar config ─── */
const roleColors = { admin:'usr-role-admin', ceo:'usr-role-ceo', lab_manager:'usr-role-lab_manager', user:'usr-role-user', visitor:'usr-role-visitor' };
const roleIcons  = { admin:'fa-crown', ceo:'fa-briefcase', lab_manager:'fa-user-shield', user:'fa-user', visitor:'fa-eye' };
const roleAvBg   = { admin:'#b91c1c', ceo:'#1d4ed8', lab_manager:'#b45309', user:'#15803d', visitor:'#64748b' };
const roleLabel  = { admin:'Admin', ceo:'CEO', lab_manager:'Lab Manager', user:'User', visitor:'Visitor' };

function escHtml(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ─── Stats ─── */
function updateStats(users) {
    const active   = users.filter(u => parseInt(u.is_active));
    const inactive = users.filter(u => !parseInt(u.is_active));
    const multi    = active.filter(u => parseInt(u.room_count || 0) > 1);
    const admins   = users.filter(u => u.role_name === 'admin');
    const managers = users.filter(u => u.role_name === 'lab_manager');
    const regularUsers = users.filter(u => u.role_name === 'user');

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('hTotal',     users.length);
    set('hActive',    active.length);
    set('hMulti',     multi.length);
    set('sTotalUsers',users.length);
    set('sAdmins',    admins.length);
    set('sManagers',  managers.length);
    set('sUsers',     regularUsers.length);
    set('sMulti',     multi.length);
    set('cAll',       users.length);
    set('cMulti',     multi.length);

    const si = document.getElementById('statInactive');
    if (si) {
        si.style.display = inactive.length > 0 ? '' : 'none';
        set('sInactive', inactive.length);
    }

    // Highlight active stat card
    document.querySelectorAll('.usr-stat').forEach(c => c.classList.remove('af'));
}

function filterByRole(role) {
    currentFilter = role;
    document.querySelectorAll('#roleFilter .usr-tab').forEach(t => t.classList.toggle('active', t.dataset.role === role));
    applyFilters();
}

/* ─── Filters ─── */
function applyFilters() {
    const q = (document.getElementById('userSearch').value || '').toLowerCase();
    const sortMode = (document.getElementById('labSort')?.value || 'default');
    let filtered = allUsers;

    if (currentFilter === 'inactive') {
        filtered = filtered.filter(u => !parseInt(u.is_active));
    } else if (currentFilter === 'multi_lab') {
        filtered = filtered.filter(u => parseInt(u.is_active) && parseInt(u.room_count || 0) > 1);
    } else if (currentFilter !== 'all') {
        filtered = filtered.filter(u => u.role_name === currentFilter && parseInt(u.is_active));
    }

    if (q) filtered = filtered.filter(u =>
        ((u.first_name||'') + ' ' + (u.last_name||'') + ' ' + (u.username||'') + ' ' + (u.email||'') + ' ' + (u.department||'') + ' ' + (u.primary_room_code||'')).toLowerCase().includes(q)
    );

    if (sortMode === 'lab_desc') filtered = [...filtered].sort((a,b) => (parseInt(b.room_count||0) - parseInt(a.room_count||0)) || ((a.first_name||'') + ' ' + (a.last_name||'')).localeCompare((b.first_name||'') + ' ' + (b.last_name||'')));
    else if (sortMode === 'lab_asc') filtered = [...filtered].sort((a,b) => (parseInt(a.room_count||0) - parseInt(b.room_count||0)) || ((a.first_name||'') + ' ' + (a.last_name||'')).localeCompare((b.first_name||'') + ' ' + (b.last_name||'')));

    const cnt = document.getElementById('usrCount');
    if (cnt) cnt.textContent = TH ? `แสดง ${filtered.length} รายการ` : `Showing ${filtered.length}`;

    if (currentView === 'table') renderUsersTable(filtered);
    else renderUsersGrid(filtered);
}

/* ─── Avatar helper ─── */
function usrAvatar(u, size = 38, radius = '50%') {
    const initials = ((u.first_name||'')[0]||'') + ((u.last_name||'')[0]||'');
    const bg = roleAvBg[u.role_name] || '#64748b';
    const fontSize = Math.round(size * 0.34);
    if (u.avatar_url) {
        return `<div class="usr-av" style="width:${size}px;height:${size}px;border-radius:${radius};background:${bg};flex-shrink:0;overflow:hidden">` +
               `<img src="${escHtml(u.avatar_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:${radius}" onerror="this.parentElement.innerHTML='<span style=\\'font-size:${fontSize}px;font-weight:700;color:#fff\\'>${initials}</span>';this.parentElement.style.display='flex';this.parentElement.style.alignItems='center';this.parentElement.style.justifyContent='center'">` +
               `</div>`;
    }
    return `<div class="usr-av" style="width:${size}px;height:${size}px;border-radius:${radius};background:${bg};font-size:${fontSize}px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;flex-shrink:0">${initials}</div>`;
}

/* ─── View toggle ─── */
function setView(v) {
    currentView = v;
    document.querySelectorAll('#viewSwitcher button').forEach((b,i) => b.classList.toggle('active', (i===0&&v==='table')||(i===1&&v==='grid')));
    applyFilters();
}

/* ─── Table View ─── */
function renderUsersTable(users) {
    const wrap = document.getElementById('usrContent');
    if (!users.length) {
        wrap.innerHTML = `<div class="usr-empty"><i class="fas fa-users"></i><p>${TH ? 'ไม่พบผู้ใช้' : 'No users found'}</p></div>`;
        return;
    }
    const allVis = users.map(u => parseInt(u.id));
    const allSel = allVis.length > 0 && allVis.every(id => selectedUsers.has(id));
    const someSel = !allSel && allVis.some(id => selectedUsers.has(id));
    wrap.innerHTML = `<div class="usr-tw"><table class="usr-t">
        <thead><tr>
            <th class="cb-col"><input type="checkbox" class="usr-cb" id="cbSelectAll" ${allSel ? 'checked' : ''} onclick="toggleSelectAll(this)" title="${TH?'เลือกทั้งหมด':'Select all'}"></th>
            <th>${TH ? 'ชื่อ-สกุล' : 'Name'}</th>
            <th>${TH ? 'บทบาท' : 'Role'}</th>
            <th>${TH ? 'ฝ่าย' : 'Division'}</th>
            <th>${TH ? 'งาน' : 'Section'}</th>
            <th>${TH ? 'ห้องที่ดูแล' : 'Labs'}</th>
            <th>${TH ? 'สถานะ' : 'Status'}</th>
            <th>${TH ? 'เข้าสู่ระบบล่าสุด' : 'Last Login'}</th>
            <th style="text-align:center;width:90px">${TH ? 'จัดการ' : 'Actions'}</th>
        </tr></thead>
        <tbody>${users.map(u => {
            const active   = parseInt(u.is_active);
            const isSelf   = parseInt(u.id) === currentUserId;
            const roomCount = parseInt(u.room_count || 0);
            const isSel    = selectedUsers.has(parseInt(u.id));
            return `<tr class="${!active ? 'inactive-row' : ''}${isSel ? ' sel-row' : ''}" onclick="showDetail(${u.id})">
                <td class="cb-col" onclick="event.stopPropagation()"><input type="checkbox" class="usr-cb" id="cb_${u.id}" ${isSel ? 'checked' : ''} onchange="toggleSelect(${u.id},this)"></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        ${usrAvatar(u, 38, '50%')}
                        <div>
                            <div style="font-weight:700;font-size:13px">${escHtml(u.first_name||'')} ${escHtml(u.last_name||'')}${isSelf ? ' <span style="font-size:9px;padding:1px 6px;background:#e0f2fe;color:#0369a1;border-radius:4px;font-weight:700">You</span>' : ''}</div>
                            <div style="font-size:10px;color:var(--c3)">@${escHtml(u.username)} · ${escHtml(u.email)}</div>
                        </div>
                    </div>
                </td>
                <td><span class="usr-role ${roleColors[u.role_name]||'usr-role-visitor'}"><i class="fas ${roleIcons[u.role_name]||'fa-user'}"></i> ${escHtml(u.role_display||u.role_name)}</span></td>
                <td style="font-size:12px;color:var(--c2)">${escHtml(u.department)||'<span style="color:#cbd5e1">—</span>'}</td>
                <td style="font-size:12px;color:var(--c2)">${escHtml(u.position)||'<span style="color:#cbd5e1">—</span>'}</td>
                <td>
                    ${roomCount > 0
                        ? `<span class="rm-pill" onclick="event.stopPropagation();showRoomPopover(event,${u.id})" title="${TH?'คลิกเพื่อดูรายการห้อง':'Click to view rooms'}"><i class="fas fa-door-open"></i> ${roomCount} ${TH?'ห้อง':'rooms'}</span>`
                        : `<span class="rm-pill rm-pill-zero"><i class="fas fa-door-open"></i> ${TH?'ไม่มีห้อง':'No rooms'}</span>`}
                    ${u.primary_room_code ? `<span class="rm-primary-tag" title="${escHtml(u.primary_bld_code||'')} · ${escHtml(u.primary_room_name||'')}"><span class="rm-bld">${escHtml(u.primary_bld_code||'')}</span>${escHtml(u.primary_room_code)}</span>` : ''}
                </td>
                <td>${active
                    ? '<span class="usr-status-on"><i class="fas fa-circle" style="font-size:7px"></i> Active</span>'
                    : '<span class="usr-status-off"><i class="fas fa-ban" style="font-size:9px"></i> Inactive</span>'}</td>
                <td style="font-size:11px;color:var(--c3)">${u.last_login ? formatDate(u.last_login) : '<span style="color:#cbd5e1">—</span>'}</td>
                <td onclick="event.stopPropagation()">
                    <div class="usr-actions">
                        <button class="usr-act usr-act-view" onclick="showDetail(${u.id})" title="${TH?'ดูรายละเอียด':'View'}"><i class="fas fa-eye"></i></button>
                        ${isAdmin ? `<button class="usr-act usr-act-lab" onclick="openRoomModal(${u.id})" title="${TH?'จัดการห้อง':'Rooms'}"><i class="fas fa-map-marker-alt"></i></button>` : ''}
                        ${isAdmin || !isSelf ? `<button class="usr-act usr-act-edit" onclick="openEditModal(${u.id})" title="${TH?'แก้ไข':'Edit'}"><i class="fas fa-pen"></i></button>` : ''}
                        ${isAdmin && !isSelf ? `<button class="usr-act usr-act-tog" onclick="toggleUser(${u.id}, ${active})" title="${active?(TH?'ปิดใช้งาน':'Deactivate'):(TH?'เปิดใช้งาน':'Activate')}"><i class="fas fa-${active?'ban':'check'}"></i></button>` : ''}
                        ${isAdmin && !isSelf && !active ? `<button class="usr-act usr-act-del" onclick="deleteUser(${u.id})" title="${TH?'ลบถาวร':'Delete'}"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('')}</tbody>
    </table></div>`;
    const cbAll = document.getElementById('cbSelectAll');
    if (cbAll) cbAll.indeterminate = someSel;
}

/* ─── Grid View ─── */
function renderUsersGrid(users) {
    const wrap = document.getElementById('usrContent');
    if (!users.length) {
        wrap.innerHTML = `<div class="usr-empty"><i class="fas fa-users"></i><p>${TH ? 'ไม่พบผู้ใช้' : 'No users found'}</p></div>`;
        return;
    }
    wrap.innerHTML = `<div class="usr-grid">${users.map(u => {
        const active   = parseInt(u.is_active);
        const isSelf   = parseInt(u.id) === currentUserId;
        const roomCount = parseInt(u.room_count || 0);
        const isSel    = selectedUsers.has(parseInt(u.id));
        const stripeColors = { admin:'#dc2626', ceo:'#2563eb', lab_manager:'#d97706', user:'#16a34a', visitor:'#64748b' };
        const stripe = stripeColors[u.role_name] || '#6366f1';
        return `<div class="usr-card ${!active ? 'inactive-card' : ''}${isSel ? ' sel-card' : ''}" onclick="showDetail(${u.id})">
            <div class="usr-card-stripe" style="background:${stripe}"></div>
            <div class="card-cb-ov" onclick="event.stopPropagation()"><input type="checkbox" class="usr-cb" id="cb_${u.id}" ${isSel ? 'checked' : ''} onchange="toggleSelect(${u.id},this)"></div>
            <div class="usr-card-hd" style="margin-top:6px">
                ${usrAvatar(u, 48, '14px')}
                <div class="usr-card-info">
                    <div class="usr-card-name">${escHtml(u.first_name||'')} ${escHtml(u.last_name||'')}${isSelf ? ' <span style="font-size:9px;padding:1px 5px;background:#e0f2fe;color:#0369a1;border-radius:4px;font-weight:700">You</span>' : ''}</div>
                    <div class="usr-card-at">@${escHtml(u.username)}</div>
                    <div style="margin-top:5px"><span class="usr-role ${roleColors[u.role_name]||'usr-role-visitor'}" style="font-size:9px"><i class="fas ${roleIcons[u.role_name]||'fa-user'}"></i> ${escHtml(u.role_display||roleLabel[u.role_name]||u.role_name)}</span></div>
                </div>
            </div>
            <div class="usr-card-bd">
                <div class="usr-card-meta">
                    <div class="usr-card-row"><i class="fas fa-envelope"></i> ${escHtml(u.email)||'—'}</div>
                    ${u.department ? `<div class="usr-card-row"><i class="fas fa-sitemap"></i> ${escHtml(u.department)}</div>` : ''}
                    ${u.position   ? `<div class="usr-card-row"><i class="fas fa-layer-group"></i> ${escHtml(u.position)}</div>` : ''}
                    ${u.primary_room_code ? `<div class="usr-card-row"><i class="fas fa-map-marker-alt" style="color:#4338ca"></i> <span style="color:#4338ca;font-weight:600">${escHtml(u.primary_room_code)}</span>${u.primary_bld_code ? ` <span style="color:#94a3b8;font-size:10px">${escHtml(u.primary_bld_code)}</span>` : ''}</div>` : ''}
                </div>
                <div class="usr-card-ft">
                    <div style="display:flex;align-items:center;gap:6px">
                        ${roomCount > 0
                            ? `<span class="rm-pill" style="font-size:10px" onclick="event.stopPropagation();showRoomPopover(event,${u.id})" title="${TH?'คลิกเพื่อดูรายการห้อง':'Click to view rooms'}"><i class="fas fa-door-open"></i> ${roomCount} ${TH?'ห้อง':'rooms'}</span>`
                            : `<span class="rm-pill rm-pill-zero" style="font-size:10px"><i class="fas fa-door-open"></i> ${TH?'ไม่มีห้อง':'No rooms'}</span>`}
                        ${active
                            ? '<span class="usr-status-on" style="font-size:9px;padding:2px 7px"><i class="fas fa-circle" style="font-size:6px"></i> Active</span>'
                            : '<span class="usr-status-off" style="font-size:9px;padding:2px 7px"><i class="fas fa-ban" style="font-size:8px"></i> Off</span>'}
                    </div>
                    <div class="usr-card-acts" onclick="event.stopPropagation()">
                        ${isAdmin ? `<button class="usr-act usr-act-lab" onclick="openRoomModal(${u.id})" title="${TH?'จัดการห้อง':'Rooms'}"><i class="fas fa-map-marker-alt"></i></button>` : ''}
                        ${isAdmin || !isSelf ? `<button class="usr-act usr-act-edit" onclick="openEditModal(${u.id})" title="${TH?'แก้ไข':'Edit'}"><i class="fas fa-pen"></i></button>` : ''}
                        ${isAdmin && !isSelf ? `<button class="usr-act usr-act-tog" onclick="toggleUser(${u.id},${active})" title="${TH?'สลับสถานะ':'Toggle'}"><i class="fas fa-${active?'ban':'check'}"></i></button>` : ''}
                    </div>
                </div>
            </div>
        </div>`;
    }).join('')}</div>`;
}

/* ─── Detail modal ─── */
async function showDetail(userId) {
    const u = allUsers.find(x => parseInt(x.id) === userId);
    if (!u) return;
    const active    = parseInt(u.is_active);
    const roomCount = parseInt(u.room_count || 0);
    const isSelf    = parseInt(u.id) === currentUserId;
    const primaryRoom = u.primary_room_code ? `${u.primary_room_code}${u.primary_bld_code ? ' · '+u.primary_bld_code : ''}` : '—';

    const roomsPlaceholder = roomCount > 0
        ? `<div style="text-align:center;padding:14px;color:#94a3b8;font-size:12px"><i class="fas fa-spinner fa-spin"></i> ${TH?'กำลังโหลด...':'Loading...'}</div>`
        : `<div style="text-align:center;padding:12px;color:#cbd5e1;font-size:12px"><i class="fas fa-door-open" style="display:block;font-size:24px;margin-bottom:6px;opacity:.25"></i>${TH?'ไม่มีห้องที่ดูแล':'No managed rooms'}</div>`;

    document.getElementById('detailContent').innerHTML = `
        <div style="display:flex;justify-content:center;margin-bottom:10px">${usrAvatar(u, 72, '20px')}</div>
        <div class="usr-det-name">${escHtml(u.first_name||'')} ${escHtml(u.last_name||'')}</div>
        <div class="usr-det-sub">@${escHtml(u.username)} · ${escHtml(u.email)}</div>
        <div class="usr-det-badges">
            <span class="usr-role ${roleColors[u.role_name]||'usr-role-visitor'}"><i class="fas ${roleIcons[u.role_name]||'fa-user'}"></i> ${escHtml(u.role_display||u.role_name)}</span>
            ${active ? '<span class="usr-status-on"><i class="fas fa-circle" style="font-size:7px"></i> Active</span>' : '<span class="usr-status-off"><i class="fas fa-ban"></i> Inactive</span>'}
        </div>
        <div class="usr-det-rows">
            ${[
                ['fa-at','color:#6366f1', TH?'Username':'Username', '@'+u.username],
                ['fa-envelope','color:#6366f1', TH?'อีเมล':'Email', u.email||'—'],
                ['fa-phone','color:#16a34a', TH?'เบอร์โทร':'Phone', u.phone||'—'],
                ['fa-building','color:#6366f1', TH?'ศูนย์':'Center', u.center_name||(allStores.find(s=>s.division_name===(u.department||u.division_name))||{}).center_name||'—'],
                ['fa-sitemap','color:#e65100', TH?'ฝ่าย':'Division', u.department||u.division_name||'—'],
                ['fa-layer-group','color:#1a8a5c', TH?'งาน':'Section', u.position||u.section_name||'—'],
                ['fa-warehouse','color:#2563eb', TH?'คลัง':'Store', u.store_name||'—'],
                ['fa-clock','color:var(--c3)', TH?'เข้าสู่ระบบล่าสุด':'Last Login', u.last_login ? formatDate(u.last_login) : '—'],
                ['fa-calendar','color:var(--c3)', TH?'สร้างเมื่อ':'Created', formatDate(u.created_at)],
            ].map(([icon, icStyle, label, val]) => `<div class="usr-det-row">
                <span class="usr-det-lbl"><i class="fas ${icon}" style="${icStyle};width:16px;text-align:center"></i> ${label}</span>
                <span class="usr-det-val">${escHtml(String(val))}</span>
            </div>`).join('')}
        </div>
        <div class="ud-rooms">
            <div class="ud-rooms-hdr">
                <i class="fas fa-door-open"></i>${TH?'ห้องที่ดูแล':'Managed Rooms'}
                <span class="ud-rooms-cnt">${roomCount} ${TH?'ห้อง':'rooms'}</span>
            </div>
            <div id="detailRoomList">${roomsPlaceholder}</div>
        </div>
        <div class="usr-det-acts">
            ${isAdmin ? `<button class="usr-btn usr-btn-g" style="font-size:11px;padding:6px 12px" onclick="closeDetailModal();openRoomModal(${u.id})"><i class="fas fa-map-marker-alt"></i> ${TH?'จัดการห้อง':'Manage Rooms'}</button>` : ''}
            ${isAdmin || !isSelf ? `<button class="usr-btn usr-btn-o" style="font-size:11px;padding:6px 12px;border-color:#6366f1;color:#6366f1" onclick="closeDetailModal();openEditModal(${u.id})"><i class="fas fa-pen"></i> ${TH?'แก้ไข':'Edit'}</button>` : ''}
            ${isAdmin && !isSelf ? `<button class="usr-btn ${active?'usr-btn-d':'usr-btn-p'}" style="font-size:11px;padding:6px 12px" onclick="closeDetailModal();toggleUser(${u.id},${active})"><i class="fas fa-${active?'ban':'check'}"></i> ${active?(TH?'ปิดใช้งาน':'Deactivate'):(TH?'เปิดใช้งาน':'Activate')}</button>` : ''}
            ${isAdmin && !isSelf && !active ? `<button class="usr-btn usr-btn-d" style="font-size:11px;padding:6px 12px" onclick="closeDetailModal();deleteUser(${u.id})"><i class="fas fa-trash"></i> ${TH?'ลบถาวร':'Delete'}</button>` : ''}
        </div>`;
    document.getElementById('detailModal').classList.add('show');

    if (roomCount > 0) {
        try {
            if (!_rmpCache[userId]) {
                const res = await apiFetch('/v1/api/auth.php?action=user_room_access&user_id=' + userId);
                if (!res.success) throw new Error(res.error || 'Load failed');
                _rmpCache[userId] = res.data.assigned_rooms || [];
            }
            const rooms = _rmpCache[userId];
            const el = document.getElementById('detailRoomList');
            if (!el) return;
            if (!rooms.length) {
                el.innerHTML = `<div style="text-align:center;padding:12px;color:#cbd5e1;font-size:12px">${TH?'ไม่มีห้องที่ดูแล':'No managed rooms'}</div>`;
                return;
            }
            el.innerHTML = rooms.map(r => {
                const isPri = parseInt(r.is_primary) === 1;
                const col   = umBldColor(r.building_code);
                return `<div class="ud-room-item${isPri?' primary':''}">
                    <div class="ud-room-ic" style="background:${col}18;color:${col}">
                        <i class="fas fa-door-${isPri?'open':'closed'}"></i>
                    </div>
                    <div class="ud-room-body">
                        <div class="ud-room-top">
                            <span class="ud-room-code" style="color:${col}">${escHtml(r.room_code||'–')}</span>
                            ${r.floor!=null?`<span class="ud-room-floor">${TH?'ชั้น':'F'}${escHtml(String(r.floor))}</span>`:''}
                            ${isPri?`<span class="ud-room-pri"><i class="fas fa-star" style="font-size:7px"></i>${TH?'ห้องหลัก':'Primary'}</span>`:''}
                        </div>
                        ${r.room_name?`<div class="ud-room-name">${escHtml(r.room_name)}</div>`:''}
                        <div class="ud-room-meta">
                            <span class="ud-room-tag"><i class="fas fa-building"></i>${escHtml(r.building_short||r.building_code||'')}</span>
                            <span class="ud-room-tag"><i class="fas fa-flask"></i>${r.container_count||0} ${TH?'สาร':'items'}</span>
                            ${r.room_type?`<span class="ud-room-tag"><i class="fas fa-tag"></i>${escHtml(r.room_type)}</span>`:''}
                        </div>
                    </div>
                </div>`;
            }).join('');
        } catch(e) {
            const el = document.getElementById('detailRoomList');
            if (el) el.innerHTML = `<div style="text-align:center;padding:10px;color:#ef4444;font-size:11px"><i class="fas fa-exclamation-circle"></i> ${TH?'โหลดไม่สำเร็จ':'Load failed'}</div>`;
        }
    }
}
function closeDetailModal() { document.getElementById('detailModal').classList.remove('show'); }

/* ─── Load data ─── */
async function loadData() {
    document.getElementById('usrContent').innerHTML = `<div class="usr-ld"><i class="fas fa-circle-notch"></i> ${TH?'กำลังโหลด...':'Loading...'}</div>`;
    try {
        const [usersRes, rolesRes, orgRes] = await Promise.all([
            apiFetch('/v1/api/auth.php?action=users'),
            apiFetch('/v1/api/auth.php?action=roles'),
            apiFetch('/v1/api/auth.php?action=org_hierarchy')
        ]);
        if (usersRes.success) { allUsers = usersRes.data; updateStats(allUsers); applyFilters(); }
        if (rolesRes.success) {
            allRoles = rolesRes.data;
            const sel = document.getElementById('fRole');
            sel.innerHTML = `<option value="">-- ${TH?'เลือกบทบาท':'Select Role'} --</option>` +
                allRoles.map(r => `<option value="${r.id}">${escHtml(r.display_name)}</option>`).join('');
        }
        if (orgRes.success) { allStores = orgRes.data; populateOrgCenters(); }
    } catch(e) {
        document.getElementById('usrContent').innerHTML = `<div class="usr-empty"><i class="fas fa-exclamation-triangle"></i><p>${escHtml(e.message||'Load failed')}</p></div>`;
    }
}

/* ─── Tab click ─── */
document.querySelectorAll('#roleFilter .usr-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('#roleFilter .usr-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentFilter = tab.dataset.role;
        applyFilters();
    });
});

/* ─── Org cascade ─── */
function populateOrgCenters() {
    const centers = [...new Set(allStores.map(s => s.center_name))].sort();
    const sel = document.getElementById('fCenter');
    sel.innerHTML = `<option value="">-- ${TH?'เลือกศูนย์':'Select Center'} --</option>` +
        centers.map(c => `<option value="${escHtml(c)}">${escHtml(c)}</option>`).join('');
}

function onOrgChange(level) {
    const center = document.getElementById('fCenter').value;
    const division = document.getElementById('fDivision').value;
    const section = document.getElementById('fSection').value;

    if (level === 'center') {
        const divSel = document.getElementById('fDivision');
        const secSel = document.getElementById('fSection');
        const stoSel = document.getElementById('fStore');
        if (center) {
            const divs = [...new Set(allStores.filter(s => s.center_name === center).map(s => s.division_name))].sort();
            divSel.innerHTML = `<option value="">-- ${TH?'เลือกฝ่าย':'Select Division'} --</option>` + divs.map(d => `<option value="${escHtml(d)}">${escHtml(d)}</option>`).join('');
            divSel.disabled = false;
        } else { divSel.innerHTML = `<option value="">-- ${TH?'เลือกฝ่าย':'Select Division'} --</option>`; divSel.disabled = true; }
        secSel.innerHTML = `<option value="">-- ${TH?'เลือกงาน':'Select Section'} --</option>`; secSel.disabled = true;
        stoSel.innerHTML = `<option value="">-- ${TH?'เลือกคลัง':'Select Store'} --</option>`; stoSel.disabled = true;
    }
    if (level === 'division') {
        const secSel = document.getElementById('fSection');
        const stoSel = document.getElementById('fStore');
        if (division) {
            const sects = [...new Set(allStores.filter(s => s.center_name === center && s.division_name === division).map(s => s.section_name))].sort();
            secSel.innerHTML = `<option value="">-- ${TH?'เลือกงาน':'Select Section'} --</option>` + sects.map(s => `<option value="${escHtml(s)}">${escHtml(s)}</option>`).join('');
            secSel.disabled = false;
        } else { secSel.innerHTML = `<option value="">-- ${TH?'เลือกงาน':'Select Section'} --</option>`; secSel.disabled = true; }
        document.getElementById('fStore').innerHTML = `<option value="">-- ${TH?'เลือกคลัง':'Select Store'} --</option>`; document.getElementById('fStore').disabled = true;
    }
    if (level === 'section') {
        const stoSel = document.getElementById('fStore');
        if (section) {
            const stores = allStores.filter(s => s.center_name === center && s.division_name === division && s.section_name === section);
            stoSel.innerHTML = `<option value="">-- ${TH?'เลือกคลัง':'Select Store'} --</option>` + stores.map(s => `<option value="${s.id}">${escHtml(s.store_name)}</option>`).join('');
            stoSel.disabled = false;
        } else { stoSel.innerHTML = `<option value="">-- ${TH?'เลือกคลัง':'Select Store'} --</option>`; stoSel.disabled = true; }
    }
    updateOrgBreadcrumb();
}

function updateOrgBreadcrumb() {
    const center = document.getElementById('fCenter').value;
    const division = document.getElementById('fDivision').value;
    const section = document.getElementById('fSection').value;
    const storeId = document.getElementById('fStore').value;
    const bc = document.getElementById('orgBreadcrumb');
    const parts = [center, division, section].filter(Boolean);
    if (storeId) { const st = allStores.find(s => String(s.id) === String(storeId)); if (st) parts.push(st.store_name); }
    if (parts.length > 0) { bc.style.display = 'flex'; document.getElementById('orgBreadcrumbText').textContent = parts.join(' › '); }
    else bc.style.display = 'none';
}

function _ensureOpt(sel, val) {
    if (val && !Array.from(sel.options).some(o => o.value === val)) {
        sel.disabled = false;
        sel.add(new Option(val, val));
    }
}
function setOrgFromUser(u) {
    let center = u.center_name || '';
    const division = u.department || u.division_name || '';
    const section  = u.position  || u.section_name  || '';
    const storeId  = u.store_id  || '';
    if (!center && division) { const m = allStores.find(s => s.division_name === division); if (m) center = m.center_name; }
    if (center) {
        const cSel = document.getElementById('fCenter');
        _ensureOpt(cSel, center);
        cSel.value = center;
        onOrgChange('center');
    }
    if (division) {
        const dSel = document.getElementById('fDivision');
        _ensureOpt(dSel, division);
        dSel.value = division;
        onOrgChange('division');
    }
    if (section) {
        const sSel = document.getElementById('fSection');
        _ensureOpt(sSel, section);
        sSel.value = section;
        onOrgChange('section');
    }
    if (storeId) { document.getElementById('fStore').value = storeId; }
    updateOrgBreadcrumb();
}

function resetOrgFields() {
    document.getElementById('fCenter').value = '';
    ['fDivision','fSection','fStore'].forEach(id => {
        const el = document.getElementById(id);
        el.innerHTML = `<option value="">—</option>`;
        el.disabled = true;
    });
    document.getElementById('orgBreadcrumb').style.display = 'none';
}

/* ─── Room Picker (Add User) ─── */
let umSlBuildings = [], umSlRoomsCache = {}, umSlSelected = null, umSlActiveBld = null, umSlView = 'grid';
const UM_BLD_COLORS = {'F0':'#6b7280','F1':'#3b82f6','F2':'#16a34a','F3':'#2563eb','F4':'#9333ea','F5':'#ea580c','F6':'#0284c7','F7':'#10b981','F9':'#0d9488','F10':'#ec4899','F11':'#f97316','F12':'#dc2626','F14':'#65a30d','F16':'#d97706','Farm':'#92400e'};
function umBldColor(code){ return UM_BLD_COLORS[code]||'#374151'; }

function umSlSetTrigger() {
    const trig = document.getElementById('umRoomTrigger');
    const txt  = document.getElementById('umRoomTrigTxt');
    if (!umSlSelected) {
        txt.innerHTML = `<span class="um-rt-ph">${TH?'เลือกห้องปฏิบัติการ...':'Select laboratory...'}</span>`;
        trig.classList.remove('has-room');
        document.getElementById('fRoomId').value = '';
    } else {
        const col = umBldColor(umSlSelected.building_code);
        txt.innerHTML = `<div class="um-rt-name" style="color:${col}">${escHtml(umSlSelected.room_number||umSlSelected.name||'–')}</div><div class="um-rt-bld" style="color:${col}88"><i class="fas fa-building" style="font-size:9px;margin-right:3px"></i>${escHtml(umSlSelected.building_name||'')}</div>`;
        trig.classList.add('has-room');
        document.getElementById('fRoomId').value = umSlSelected.id;
    }
}

function umSlRenderGrid() {
    umSlView = 'grid';
    document.getElementById('umSlNav').style.display = 'none';
    document.getElementById('umSlHdrSub').textContent = TH?'เลือกอาคารเพื่อดูรายการห้อง':'Select a building to browse rooms';
    const q = document.getElementById('umSlSearch').value.trim().toLowerCase();
    const list = q ? umSlBuildings.filter(b=>(b.code||'').toLowerCase().includes(q)||(b.name||'').toLowerCase().includes(q)) : umSlBuildings;
    if (!list.length) { document.getElementById('umSlBody').innerHTML=`<div class="um-sl-empty"><i class="fas fa-search"></i>${TH?'ไม่พบอาคาร':'No buildings found'}</div>`; return; }
    let html = '<div class="um-sl-grid">';
    list.forEach(b => {
        const col = umBldColor(b.code);
        html += `<div class="um-sl-bld" data-bid="${b.id}" style="border-left-color:${col}">
            <div class="um-sl-bld-num" style="color:${col}">${escHtml(b.code)}</div>
            <div class="um-sl-bld-name">${escHtml(b.name)}</div>
            <div class="um-sl-bld-badge"><i class="fas fa-door-closed"></i>${b.room_count} ${TH?'ห้อง':'rooms'}</div>
            <i class="fas fa-chevron-right um-sl-bld-arrow"></i>
        </div>`;
    });
    html += '</div>';
    document.getElementById('umSlBody').innerHTML = html;
    document.getElementById('umSlBody').querySelectorAll('[data-bid]').forEach(card => {
        card.addEventListener('click', () => {
            umSlActiveBld = umSlBuildings.find(b=>b.id==card.getAttribute('data-bid'));
            umSlLoadRooms(umSlActiveBld.id);
        });
    });
}

async function umSlLoadRooms(bldId) {
    if (umSlRoomsCache[bldId]) { umSlRenderRooms(umSlRoomsCache[bldId]); return; }
    document.getElementById('umSlBody').innerHTML = `<div class="um-sl-empty"><i class="fas fa-spinner fa-spin"></i> ${TH?'กำลังโหลด...':'Loading...'}</div>`;
    try {
        const res = await fetch(`/v1/api/auth.php?action=public_rooms&building_id=${bldId}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        umSlRoomsCache[bldId] = (data.data||[]).map(r=>({...r, id:parseInt(r.id,10)}));
        umSlRenderRooms(umSlRoomsCache[bldId]);
    } catch(e) {
        document.getElementById('umSlBody').innerHTML = `<div class="um-sl-empty"><i class="fas fa-exclamation-circle"></i> ${TH?'โหลดไม่สำเร็จ':'Load failed'}</div>`;
    }
}

function umSlRenderRooms(rooms) {
    umSlView = 'rooms';
    const col = umBldColor(umSlActiveBld.code);
    document.getElementById('umSlNavCur').textContent = umSlActiveBld.name;
    document.getElementById('umSlNav').style.display = 'flex';
    document.getElementById('umSlHdrSub').textContent = umSlActiveBld.name;
    const q = document.getElementById('umSlSearch').value.trim().toLowerCase();
    const list = q ? rooms.filter(r=>(r.room_number||'').toLowerCase().includes(q)||(r.name||'').toLowerCase().includes(q)) : rooms;
    const floors={}, floorOrd=[];
    list.forEach(r=>{ const f=r.floor!=null?String(r.floor):''; if(!floors[f]){floors[f]=[];floorOrd.push(f);} floors[f].push(r); });
    let html='';
    floorOrd.forEach(f=>{
        if(f) html+=`<div class="um-sl-floor-hdr">${TH?'ชั้น':'Floor'} ${escHtml(f)}</div>`;
        floors[f].forEach(r=>{
            const isSel = umSlSelected && umSlSelected.id===r.id;
            html+=`<div class="um-sl-room${isSel?' sel':''}" data-rid="${r.id}">
                <div class="um-sl-room-chk"><i class="fas fa-check"></i></div>
                <div class="um-sl-room-ic" style="background:${col}1a;color:${col}"><i class="fas fa-door-closed"></i></div>
                <div class="um-sl-room-body">
                    <div class="um-sl-room-top">
                        <span class="um-sl-room-code" style="color:${col}">${escHtml(r.room_number||'–')}</span>
                        ${r.floor!=null?`<span class="um-sl-room-floor">${TH?'ชั้น':'F'}${r.floor}</span>`:''}
                    </div>
                    ${r.name?`<div class="um-sl-room-name">${escHtml(r.name)}</div>`:''}
                </div>
            </div>`;
        });
    });
    if(!html) html=`<div class="um-sl-empty"><i class="fas fa-door-open"></i>${TH?'ไม่พบห้อง':'No rooms found'}</div>`;
    document.getElementById('umSlBody').innerHTML=html;
    document.getElementById('umSlBody').querySelectorAll('.um-sl-room').forEach(row=>{
        row.addEventListener('click',()=>{
            const rid=parseInt(row.getAttribute('data-rid'),10);
            const room=rooms.find(r=>r.id===rid); if(!room) return;
            umSlSelected = (umSlSelected&&umSlSelected.id===rid) ? null : {
                id:rid, room_number:room.room_number||'', name:room.name||'', floor:room.floor,
                building_id:parseInt(umSlActiveBld.id), building_code:umSlActiveBld.code, building_name:umSlActiveBld.name
            };
            umSlRenderRooms(rooms);
            umSlUpdateFooter();
        });
    });
    umSlUpdateFooter();
}

function umSlUpdateFooter() {
    const footer=document.getElementById('umSlFooter');
    if(!umSlSelected){footer.classList.remove('visible');return;}
    footer.classList.add('visible');
    document.getElementById('umSlFooterRoom').textContent=umSlSelected.room_number||umSlSelected.name||'–';
    document.getElementById('umSlFooterBld').textContent=umSlSelected.building_name||'';
}

function umSlConfirm() {
    if(!umSlSelected) return;
    umSlSetTrigger();
    umSlClose();
}

async function umSlOpen() {
    document.getElementById('umSlOv').classList.add('open');
    document.getElementById('umSlSearch').value='';
    if(!umSlBuildings.length) {
        document.getElementById('umSlBody').innerHTML=`<div class="um-sl-empty"><i class="fas fa-spinner fa-spin"></i></div>`;
        try {
            const res=await fetch('/v1/api/auth.php?action=public_buildings');
            const data=await res.json();
            if(data.success) umSlBuildings=data.data||[];
        } catch(e){ document.getElementById('umSlBody').innerHTML=`<div class="um-sl-empty"><i class="fas fa-exclamation-circle"></i> ${TH?'โหลดไม่สำเร็จ':'Load failed'}</div>`; return; }
    }
    if(umSlSelected&&umSlActiveBld&&umSlRoomsCache[umSlActiveBld.id]){ umSlRenderRooms(umSlRoomsCache[umSlActiveBld.id]); }
    else { umSlRenderGrid(); }
    setTimeout(()=>document.getElementById('umSlSearch').focus(),80);
}

function umSlClose() {
    document.getElementById('umSlOv').classList.remove('open');
    document.getElementById('umSlFooter').classList.remove('visible');
}

function umSlGoGrid() {
    document.getElementById('umSlSearch').value='';
    umSlRenderGrid();
}

document.addEventListener('DOMContentLoaded',()=>{
    document.getElementById('umSlOv').addEventListener('click',e=>{ if(e.target===e.currentTarget) umSlClose(); });
    document.getElementById('umSlSearch').addEventListener('input',()=>{
        if(umSlView==='rooms'&&umSlActiveBld&&umSlRoomsCache[umSlActiveBld.id]) umSlRenderRooms(umSlRoomsCache[umSlActiveBld.id]);
        else umSlRenderGrid();
    });
    document.addEventListener('keydown',e=>{ if(e.key==='Escape'&&document.getElementById('umSlOv').classList.contains('open')) umSlClose(); });
});

/* ─── Add / Edit ─── */
function openAddModal() {
    document.getElementById('fUserId').value = '';
    document.getElementById('userForm').reset();
    document.getElementById('modalTitle').innerHTML = `<i class="fas fa-user-plus" style="color:#6366f1"></i> ${TH?'เพิ่มผู้ใช้':'Add User'}`;
    document.getElementById('fUsername').disabled = false;
    document.getElementById('fPassword').required = true;
    document.getElementById('pwHint').textContent = TH?'(จำเป็น)':'(required)';
    resetOrgFields();
    umSlSelected = null; umSlActiveBld = null;
    umSlSetTrigger();
    document.getElementById('umRoomSec').style.display = '';
    document.getElementById('userModal').classList.add('show');
}

function openEditModal(userId) {
    const u = allUsers.find(x => parseInt(x.id) === userId);
    if (!u) return;
    document.getElementById('fUserId').value = u.id;
    document.getElementById('fFirstName').value = u.first_name || '';
    document.getElementById('fLastName').value  = u.last_name  || '';
    document.getElementById('fUsername').value  = u.username   || '';
    document.getElementById('fUsername').disabled = true;
    document.getElementById('fEmail').value    = u.email   || '';
    document.getElementById('fPhone').value    = u.phone   || '';
    document.getElementById('fRole').value     = u.role_id || '';
    document.getElementById('fPassword').value    = '';
    document.getElementById('fPassword').required = false;
    document.getElementById('pwHint').textContent = TH?'(เว้นว่างถ้าไม่เปลี่ยน)':'(leave blank to keep)';
    document.getElementById('modalTitle').innerHTML = `<i class="fas fa-user-edit" style="color:#6366f1"></i> ${TH?'แก้ไขผู้ใช้':'Edit User'}`;
    document.getElementById('umRoomSec').style.display = 'none';
    resetOrgFields();
    if (allStores.length > 0) setTimeout(() => setOrgFromUser(u), 50);
    document.getElementById('userModal').classList.add('show');
}

function closeModal() {
    document.getElementById('userModal').classList.remove('show');
    umSlClose();
}

async function saveUser(e) {
    e.preventDefault();
    const userId = document.getElementById('fUserId').value;
    const isEdit = !!userId;
    const data = {
        first_name: document.getElementById('fFirstName').value.trim(),
        last_name:  document.getElementById('fLastName').value.trim(),
        email:      document.getElementById('fEmail').value.trim(),
        phone:      document.getElementById('fPhone').value.trim(),
        role_id:    document.getElementById('fRole').value,
        department: document.getElementById('fDivision').value.trim(),
        position:   document.getElementById('fSection').value.trim(),
        store_id:   document.getElementById('fStore').value || ''
    };
    const pw = document.getElementById('fPassword').value;
    if (pw) data.password = pw;
    const btn = document.getElementById('saveBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        let res;
        if (isEdit) {
            data.user_id = parseInt(userId);
            res = await apiFetch('/v1/api/auth.php?action=users_update', { method:'POST', body:JSON.stringify(data) });
        } else {
            data.username = document.getElementById('fUsername').value.trim();
            data.password = pw;
            res = await apiFetch('/v1/api/auth.php?action=users', { method:'POST', body:JSON.stringify(data) });
            if (res.success && umSlSelected && res.data && res.data.user_id) {
                try {
                    await apiFetch('/v1/api/auth.php?action=user_room_access_update', {
                        method:'POST',
                        body: JSON.stringify({ user_id: res.data.user_id, room_ids: [umSlSelected.id], primary_room_id: umSlSelected.id })
                    });
                } catch(_){}
            }
        }
        if (res.success) {
            closeModal();
            showToast(isEdit ? (TH?'อัปเดตสำเร็จ':'Updated') : (TH?'เพิ่มผู้ใช้สำเร็จ':'User added'), 'ok');
            loadData();
        } else showToast(res.error || 'Error', 'err');
    } catch(e) { showToast(e.message || 'Error', 'err'); }
    finally { btn.disabled = false; btn.innerHTML = `<i class="fas fa-save"></i> ${TH?'บันทึก':'Save'}`; }
}

async function toggleUser(userId, currentlyActive) {
    const msg = currentlyActive ? (TH?'ต้องการปิดใช้งานผู้ใช้นี้?':'Deactivate this user?') : (TH?'ต้องการเปิดใช้งานผู้ใช้นี้?':'Activate this user?');
    if (!confirm(msg)) return;
    try {
        const res = await apiFetch('/v1/api/auth.php?action=users_toggle', { method:'POST', body:JSON.stringify({ user_id: userId }) });
        if (res.success) { showToast(TH?'อัปเดตสำเร็จ':'Updated', 'ok'); loadData(); }
        else showToast(res.error || 'Error', 'err');
    } catch(e) { showToast(e.message || 'Error', 'err'); }
}

async function deleteUser(userId) {
    const u = allUsers.find(x => parseInt(x.id) === userId);
    if (!u) return;
    const name = ((u.first_name||'') + ' ' + (u.last_name||'')).trim();
    const answer = prompt(`⚠️ ลบผู้ใช้ "${name}" (@${u.username}) อย่างถาวร?\n\nพิมพ์ DELETE เพื่อยืนยัน`);
    if (answer !== 'DELETE') { if (answer !== null) showToast(TH?'ยกเลิก: พิมพ์ DELETE เพื่อยืนยัน':'Cancelled: type DELETE to confirm', 'err'); return; }
    try {
        const res = await apiFetch('/v1/api/auth.php?action=users_delete', { method:'POST', body:JSON.stringify({ user_id: userId }) });
        if (res.success) { showToast(res.message || (TH?'ลบผู้ใช้สำเร็จ':'User deleted'), 'ok'); loadData(); }
        else showToast(res.error || 'Error', 'err');
    } catch(e) { showToast(e.message || 'Error', 'err'); }
}


/* ─── Room Popover ─── */
let _rmpCache = {};   // cache by userId to avoid repeated fetches

async function showRoomPopover(e, userId) {
    const pop = document.getElementById('rmPopover');
    const box = document.getElementById('rmPopoverBox');
    const u   = allUsers.find(x => parseInt(x.id) === userId);

    // Position near click, keep inside viewport
    const MARGIN = 8;
    pop.style.display = 'block';
    box.className = 'rmp-box';
    box.innerHTML = `<div class="rmp-loading"><i class="fas fa-circle-notch fa-spin"></i>${TH?'กำลังโหลด...':'Loading...'}</div>`;

    const vw = window.innerWidth, vh = window.innerHeight;
    const bw = 340, bh = 380;   // estimated box size
    let left = e.clientX + 8;
    let top  = e.clientY + 8;
    if (left + bw > vw - MARGIN) left = e.clientX - bw - 8;
    if (top  + bh > vh - MARGIN) top  = e.clientY - bh - 8;
    pop.style.left = Math.max(MARGIN, left) + 'px';
    pop.style.top  = Math.max(MARGIN, top)  + 'px';

    // Fetch (use cache)
    try {
        if (!_rmpCache[userId]) {
            const res = await apiFetch('/v1/api/auth.php?action=user_room_access&user_id=' + userId);
            if (!res.success) throw new Error(res.error || 'Load failed');
            _rmpCache[userId] = res.data.assigned_rooms || [];
        }
        const rooms = _rmpCache[userId];
        const name  = u ? `${u.first_name||''} ${u.last_name||''}`.trim() : `#${userId}`;
        const initials = u ? ((u.first_name||'')[0]||'') + ((u.last_name||'')[0]||'') : '?';
        const bgColor  = u ? (roleAvBg[u.role_name] || '#6366f1') : '#6366f1';
        const avHtml   = (u && u.avatar_url)
            ? `<img src="${escHtml(u.avatar_url)}" alt="" style="width:100%;height:100%;object-fit:cover" onerror="var p=this.parentNode;p.style.background='${bgColor}';p.innerHTML='${escHtml(initials.toUpperCase())}'">`
            : escHtml(initials.toUpperCase());

        box.innerHTML = `
            <div class="rmp-hdr">
                <div class="rmp-hdr-ic" style="background:${(u && u.avatar_url) ? 'transparent' : bgColor}">${avHtml}</div>
                <div>
                    <div class="rmp-hdr-name">${escHtml(name)}</div>
                    <div class="rmp-hdr-sub">${rooms.length} ${TH?'ห้องที่ดูแล':'managed rooms'}</div>
                </div>
                <button class="rmp-close" onclick="closeRoomPopover()"><i class="fas fa-times"></i></button>
            </div>
            <div class="rmp-body">
                ${rooms.length ? rooms.map(r => {
                    const isPri = parseInt(r.is_primary) === 1;
                    const bldLabel = [r.building_code, r.building_short].filter(Boolean).join(' · ');
                    const floorLabel = r.floor ? `${TH?'ชั้น':'Fl.'} ${r.floor}` : '';
                    const metaParts = [bldLabel, floorLabel].filter(Boolean);
                    const cnt = parseInt(r.container_count || 0);
                    return `<div class="rmp-row">
                        <div class="rmp-row-ic ${isPri?'primary':'secondary'}">${escHtml((r.room_code||'').slice(-2)||'—')}</div>
                        <div class="rmp-row-info">
                            <div class="rmp-row-code">${escHtml(r.room_code||'—')}${isPri ? '<i class="fas fa-star rmp-star"></i>' : ''}</div>
                            <div class="rmp-row-meta">${escHtml(r.room_name||'')}${metaParts.length ? ' · '+escHtml(metaParts.join(' · ')) : ''}</div>
                        </div>
                        ${cnt > 0 ? `<div class="rmp-row-cnt"><i class="fas fa-flask" style="font-size:9px;margin-right:2px;opacity:.7"></i>${cnt}</div>` : ''}
                    </div>`;
                }).join('') : `<div class="rmp-loading"><i class="fas fa-inbox"></i>${TH?'ยังไม่มีห้องที่ดูแล':'No rooms assigned'}</div>`}
            </div>
            ${isAdmin ? `<div class="rmp-ft">
                <button class="rmp-ft-btn" onclick="closeRoomPopover();openRoomModal(${userId})">
                    <i class="fas fa-edit"></i> ${TH?'จัดการห้อง':'Manage Rooms'}
                </button>
            </div>` : ''}`;

        // Reposition now that we know real height
        const realH = box.offsetHeight;
        if (top + realH > vh - MARGIN) top = e.clientY - realH - 8;
        pop.style.top = Math.max(MARGIN, top) + 'px';

    } catch(err) {
        box.innerHTML = `<div class="rmp-loading" style="color:#dc2626"><i class="fas fa-exclamation-circle"></i>${escHtml(err.message)}</div>`;
    }
}

function closeRoomPopover() {
    const pop = document.getElementById('rmPopover');
    if (pop) pop.style.display = 'none';
}

// Close popover on outside click
document.addEventListener('click', e => {
    const pop = document.getElementById('rmPopover');
    if (pop && pop.style.display !== 'none' && !pop.contains(e.target)) closeRoomPopover();
});
// Invalidate cache after saving so next open re-fetches
/* ─── Room Access Modal ─── */
async function openRoomModal(userId) {
    if (!isAdmin) return;
    const modal = document.getElementById('roomModal');
    if (!modal) return;
    resetRoomState();
    roomState.userId = parseInt(userId);
    const u = allUsers.find(x => parseInt(x.id) === parseInt(userId));

    // User header
    const initials = u ? ((u.first_name||'')[0]||'') + ((u.last_name||'')[0]||'') : '?';
    const bgColor = u ? (roleAvBg[u.role_name] || '#6366f1') : '#6366f1';
    const av = document.getElementById('rmUserAv');
    if (u && u.avatar_url) {
        av.style.background = 'transparent';
        av.innerHTML = `<img src="${escHtml(u.avatar_url)}" alt="" style="width:100%;height:100%;object-fit:cover" onerror="this.parentNode.style.background='${bgColor}';this.parentNode.innerHTML='${escHtml(initials.toUpperCase())}'">`;
    } else {
        av.style.background = bgColor;
        av.textContent = initials.toUpperCase() || '?';
    }
    document.getElementById('rmUserName').textContent = u ? `${u.first_name||''} ${u.last_name||''}`.trim() : `#${userId}`;
    document.getElementById('rmUserAt').textContent = u ? `@${u.username||''}  ·  ${u.role_display||u.role_name||''}` : '';

    // Bulk banner — show selected users when assigning rooms in bulk
    const _bulkBanner  = document.getElementById('rmBulkBanner');
    const _bulkAvatars = document.getElementById('rmBulkAvatars');
    const _bulkCount   = document.getElementById('rmBulkCount');
    const _bulkHint    = document.getElementById('rmBulkHint');
    const _bulkIds = _bulkRoomUserIds && _bulkRoomUserIds.length > 1 ? _bulkRoomUserIds : null;
    if (_bulkBanner) {
        _bulkBanner.style.display = _bulkIds ? '' : 'none';
        if (_bulkIds) {
            if (_bulkCount) _bulkCount.textContent = _bulkIds.length;
            const uName = u ? `${u.first_name||''} ${u.last_name||''}`.trim() : `#${userId}`;
            if (_bulkHint) _bulkHint.innerHTML = TH
                ? `โหลดห้องจาก <strong>${escHtml(uName)}</strong> เป็นต้นแบบ · หลังบันทึกระบบจะถามว่าจะใช้ห้องเดียวกันกับผู้ใช้ที่เหลือ`
                : `Rooms loaded from <strong>${escHtml(uName)}</strong> as baseline · after saving you'll be asked to apply to the remaining users`;
            if (_bulkAvatars) _bulkAvatars.innerHTML = _bulkIds.map(id => {
                const pu = allUsers.find(x => parseInt(x.id) === parseInt(id));
                if (!pu) return '';
                const ini = ((pu.first_name||'')[0]||'') + ((pu.last_name||'')[0]||'');
                const bg  = roleAvBg[pu.role_name] || '#6366f1';
                const nm  = `${pu.first_name||''} ${pu.last_name||''}`.trim();
                const isBase = parseInt(id) === parseInt(userId);
                return `<div class="rm-bulk-chip${isBase ? ' primary-user' : ''}">
                    <div class="rm-bulk-av-mini" style="background:${bg}">${escHtml(ini.toUpperCase()||'?')}</div>
                    <span>${escHtml(nm)}</span>${isBase ? ' <i class="fas fa-star" style="font-size:8px;margin-left:2px" title="${TH?\'โหลดห้องจากผู้ใช้นี้\':\'Baseline user\'}"></i>' : ''}
                </div>`;
            }).join('');
        }
    }

    document.getElementById('rmSearch').value = '';
    document.getElementById('rmBldFilter').innerHTML = `<option value="">${TH?'ทุกอาคาร':'All buildings'}</option>`;
    document.getElementById('rmList').innerHTML = `<div class="usr-ld"><i class="fas fa-circle-notch fa-spin"></i></div>`;
    updateRoomChip();
    modal.classList.add('show');

    try {
        const res = await apiFetch('/v1/api/auth.php?action=user_room_access&user_id=' + encodeURIComponent(userId));
        if (!res.success) throw new Error(res.error || 'Load failed');
        roomState.allRooms = (res.data?.all_rooms || []).map(r => ({
            id: parseInt(r.id), code: r.code||'', name: r.name||'',
            bld_id: parseInt(r.building_id||0),
            bld_code: r.building_code||'',
            bld_short: r.building_short||'',
            bld_name: r.building_name||'',
            floor: r.floor||'', container_count: parseInt(r.container_count||0)
        }));
        const assigned = res.data?.assigned_rooms || [];
        roomState.selected = assigned.map(a => parseInt(a.room_id)).filter(Boolean);
        const p = assigned.find(a => parseInt(a.is_primary) === 1);
        roomState.primary = p ? parseInt(p.room_id) : (roomState.selected[0] || null);

        // Build building filter — count rooms per building for display
        const bldMap = {};
        roomState.allRooms.forEach(r => {
            if (!r.bld_id) return;
            if (!bldMap[r.bld_id]) bldMap[r.bld_id] = { code: r.bld_code, short: r.bld_short, name: r.bld_name, count: 0 };
            bldMap[r.bld_id].count++;
        });
        roomState.buildings = Object.entries(bldMap)
            .map(([id, b]) => ({ id: parseInt(id), ...b }))
            .sort((a, b) => a.code.localeCompare(b.code));
        const sel = document.getElementById('rmBldFilter');
        sel.innerHTML = `<option value="">🏢 ${TH?'ทุกอาคาร':'All buildings'} (${roomState.allRooms.length})</option>`;
        roomState.buildings.forEach(b => {
            const o = document.createElement('option');
            o.value = b.id;
            o.textContent = `${b.code}${b.short && b.short !== b.code ? ' · ' + b.short : ''} — ${b.count} ${TH?'ห้อง':'rooms'}`;
            sel.appendChild(o);
        });
        updateRoomChip();
        renderRoomList();
    } catch(e) {
        document.getElementById('rmList').innerHTML = `<div style="padding:32px;text-align:center;color:#dc2626"><i class="fas fa-exclamation-circle" style="font-size:24px;margin-bottom:8px;display:block"></i>${escHtml(e.message)}</div>`;
    }
}

function closeRoomModal() { document.getElementById('roomModal')?.classList.remove('show'); resetRoomState(); _bulkRoomUserIds = null; }
function resetRoomState() { roomState = { userId:null, allRooms:[], selected:[], primary:null, buildings:[] }; }

function updateRoomChip() {
    const chip = document.getElementById('rmChip');
    if (!chip) return;
    const n = roomState.selected.length;
    chip.innerHTML = `<i class="fas fa-door-open"></i> ${n} ${TH?'ห้อง':'rooms'}`;
    chip.style.background = n > 0 ? '#6366f1' : '#94a3b8';
}

function renderRoomList() {
    const q = (document.getElementById('rmSearch')?.value || '').toLowerCase();
    const bldFilter = parseInt(document.getElementById('rmBldFilter')?.value || 0);
    const list = document.getElementById('rmList');
    if (!list) return;

    let rooms = roomState.allRooms;
    if (bldFilter) rooms = rooms.filter(r => r.bld_id === bldFilter);
    if (q) rooms = rooms.filter(r => (r.code + ' ' + r.name + ' ' + r.bld_code + ' ' + r.bld_short + ' ' + r.bld_name).toLowerCase().includes(q));

    if (!rooms.length) {
        list.innerHTML = `<div style="padding:40px;text-align:center;color:#94a3b8"><i class="fas fa-search" style="font-size:22px;margin-bottom:10px;display:block"></i>${TH?'ไม่พบห้อง':'No rooms found'}</div>`;
        return;
    }

    // Group by building
    const groups = {};
    rooms.forEach(r => {
        const key = r.bld_id || 0;
        if (!groups[key]) {
            const label = r.bld_code
                ? (r.bld_short && r.bld_short !== r.bld_code ? `${r.bld_code} · ${r.bld_short} — ${r.bld_name}` : `${r.bld_code} — ${r.bld_name}`)
                : (r.bld_name || 'Unknown');
            groups[key] = { label, rooms: [] };
        }
        groups[key].rooms.push(r);
    });

    list.innerHTML = Object.values(groups).map(g => `
        <div class="rm-bld-hdr"><i class="fas fa-building" style="margin-right:6px;opacity:.7"></i>${escHtml(g.label)}</div>
        ${g.rooms.map(r => {
            const isSel = roomState.selected.includes(r.id);
            const isPri = parseInt(roomState.primary) === r.id;
            return `<div class="rm-item ${isSel ? 'sel' : ''}" onclick="toggleRoomItem(${r.id})">
                <input type="checkbox" class="rm-cb" ${isSel ? 'checked' : ''} onclick="event.stopPropagation();toggleRoomItem(${r.id})">
                <div class="rm-item-info">
                    <div style="display:flex;align-items:center;gap:6px">
                        <span class="rm-item-code">${escHtml(r.code)}</span>
                        ${isPri ? `<span class="rm-primary-lbl active"><i class="fas fa-star" style="font-size:8px"></i> Primary</span>` : (isSel ? `<span class="rm-primary-lbl" onclick="event.stopPropagation();setRoomPrimary(${r.id})" title="${TH?'ตั้งเป็นหลัก':'Set as primary'}">${TH?'ตั้งเป็นหลัก':'Set primary'}</span>` : '')}
                    </div>
                    <div class="rm-item-name">${escHtml(r.name)}</div>
                    <div class="rm-item-meta"><i class="fas fa-building" style="opacity:.5;font-size:9px"></i> ${escHtml(r.bld_code||r.bld_name||'—')} <span style="opacity:.4">·</span> ${TH?'ชั้น':'Fl.'} ${escHtml(r.floor||'—')}</div>
                </div>
                <div class="rm-item-cnt">${r.container_count > 0 ? `<i class="fas fa-flask" style="margin-right:3px;opacity:.7"></i>${r.container_count}` : '<span style="color:#cbd5e1">—</span>'}</div>
            </div>`;
        }).join('')}
    `).join('');
}

function toggleRoomItem(roomId) {
    roomId = parseInt(roomId);
    if (roomState.selected.includes(roomId)) {
        roomState.selected = roomState.selected.filter(id => id !== roomId);
        if (roomState.primary === roomId) roomState.primary = roomState.selected[0] || null;
    } else {
        roomState.selected.push(roomId);
        if (!roomState.primary) roomState.primary = roomId;
    }
    updateRoomChip();
    renderRoomList();
}

function setRoomPrimary(roomId) {
    roomId = parseInt(roomId);
    if (roomState.selected.includes(roomId)) { roomState.primary = roomId; renderRoomList(); }
}

async function saveRoomAccess() {
    if (!isAdmin || !roomState.userId) return;
    if (!roomState.selected.length) { showToast(TH?'กรุณาเลือกอย่างน้อย 1 ห้อง':'Select at least 1 room', 'err'); return; }
    if (!roomState.primary || !roomState.selected.includes(roomState.primary)) roomState.primary = roomState.selected[0];
    const btn = document.getElementById('rmSaveBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        const res = await apiFetch('/v1/api/auth.php?action=user_room_access_update', {
            method: 'POST',
            body: JSON.stringify({ user_id: roomState.userId, room_ids: roomState.selected, primary_room_id: roomState.primary })
        });
        if (!res.success) throw new Error(res.error || 'Save failed');
        const savedRoomIds = [...roomState.selected];
        const savedPrimary = roomState.primary;
        const otherIds = (_bulkRoomUserIds || []).filter(id => id !== roomState.userId);
        delete _rmpCache[roomState.userId];
        closeRoomModal();
        if (otherIds.length) {
            const doAll = confirm(TH ? `ใช้ห้องเดียวกันกับอีก ${otherIds.length} ผู้ใช้ที่เลือก?` : `Apply same rooms to ${otherIds.length} other selected user(s)?`);
            if (doAll) {
                await Promise.all(otherIds.map(id =>
                    apiFetch('/v1/api/auth.php?action=user_room_access_update', { method:'POST', body:JSON.stringify({ user_id: id, room_ids: savedRoomIds, primary_room_id: savedPrimary }) }).catch(()=>{})
                ));
                otherIds.forEach(id => delete _rmpCache[id]);
                showToast(TH ? `กำหนดห้องเรียบร้อย ${otherIds.length+1} ผู้ใช้` : `Rooms assigned to ${otherIds.length+1} users`, 'ok');
            } else {
                showToast(TH?'บันทึกการกำหนดห้องเรียบร้อย':'Room access saved', 'ok');
            }
            clearSelection();
        } else {
            showToast(TH?'บันทึกการกำหนดห้องเรียบร้อย':'Room access saved', 'ok');
        }
        _bulkRoomUserIds = null;
        loadData();
    } catch(e) { showToast(e.message || 'Save failed', 'err'); }
    finally { btn.disabled = false; btn.innerHTML = `<i class="fas fa-save"></i> ${TH?'บันทึก':'Save'}`; }
}

/* ─── Toast ─── */
function showToast(msg, type = 'ok') {
    const container = document.getElementById('usrToast');
    const item = document.createElement('div');
    item.className = `usr-toast-item ${type}`;
    item.innerHTML = `<i class="fas fa-${type==='ok'?'check-circle':'exclamation-circle'}"></i> ${escHtml(msg)}`;
    container.appendChild(item);
    setTimeout(() => { item.style.opacity = '0'; item.style.transform = 'translateX(60px)'; item.style.transition = 'all .3s'; setTimeout(() => item.remove(), 300); }, 3000);
}

/* ─── Close on backdrop ─── */
['userModal','detailModal','roomModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', e => { if (e.target === e.currentTarget) e.target.classList.remove('show'); });
});
document.getElementById('importWizard')?.addEventListener('click', e => { if (e.target === e.currentTarget) uiwClose(); });

/* ─── Import / Export ─── */
function toggleImportExport() { document.getElementById('ieDropdown').classList.toggle('show'); }
document.addEventListener('click', e => { if (!e.target.closest('.usr-ie-wrap')) document.getElementById('ieDropdown')?.classList.remove('show'); });
function exportUsersCSV()  { document.getElementById('ieDropdown').classList.remove('show'); window.location.href = '/v1/api/user_import.php?action=export&format=csv'; }
function exportUsersJSON() { document.getElementById('ieDropdown').classList.remove('show'); window.location.href = '/v1/api/user_import.php?action=export&format=json'; }
function downloadTemplate(){ document.getElementById('ieDropdown').classList.remove('show'); window.location.href = '/v1/api/user_import.php?action=export_template'; }
function exportUsersXLSX() {
    document.getElementById('ieDropdown').classList.remove('show');
    if (!allUsers.length) { showToast(TH?'ไม่มีข้อมูลผู้ใช้':'No user data', 'err'); return; }
    const rows = allUsers.map(u => ({
        'ID': u.id,
        'ชื่อ-นามสกุล': u.full_name_th || ((u.first_name||'') + ' ' + (u.last_name||'')).trim(),
        'Username': u.username, 'Email': u.email, 'Phone': u.phone||'',
        'Role': u.role_name||'', 'Department': u.department||'', 'Position': u.position||'',
        'Lab': u.primary_room_name||'', 'Active': parseInt(u.is_active)?'Yes':'No',
    }));
    const ws = XLSX.utils.json_to_sheet(rows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Users');
    XLSX.writeFile(wb, 'users_export_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

/* ─── Import Wizard ─── */
const UIW = { file:null, headers:[], rawRows:[], mapping:{}, parsedUsers:[], step:1, pf:'all' };
const UIW_FIELDS = [
    {k:'employee_id', l: TH?'รหัสพนักงาน (Username)':'Employee ID (Username)', req:true},
    {k:'fullname',    l: TH?'ชื่อ-นามสกุล':'Full Name',                        req:true},
    {k:'position',   l: TH?'ตำแหน่ง':'Position',                               req:false},
    {k:'section',    l: TH?'งาน (Section)':'Section / Unit',                    req:false},
    {k:'department', l: TH?'ฝ่าย (Department)':'Department / Division',         req:false},
    {k:'email',      l: TH?'อีเมล':'Email',                                     req:false},
    {k:'phone',      l: TH?'เบอร์โทร':'Phone',                                  req:false},
    {k:'ignore',     l: TH?'— ไม่ใช้ —':'— Ignore —',                          req:false},
];
const UIW_HINTS = {
    employee_id:['รหัสพนัก','รหัส','emp','employee','staffid','staff_id'],
    fullname:   ['ชื่อ','name','fullname','full_name','นามสกุล'],
    position:   ['ตำแหน่ง','position','positionname'],
    section:    ['งาน','section','unit'],
    department: ['ฝ่าย','department','division','dept'],
    email:      ['email','อีเมล','mail','emailname'],
    phone:      ['tel','phone','โทร','เบอร์','mobile'],
};

function openImportModal() {
    document.getElementById('ieDropdown').classList.remove('show');
    Object.assign(UIW,{file:null,headers:[],rawRows:[],mapping:{},parsedUsers:[],step:1,pf:'all'});
    document.getElementById('uiwFileInput').value='';
    document.getElementById('uiwFileCard').style.display='none';
    document.getElementById('uiwDrop').style.display='';
    _uiwGo(1);
    document.getElementById('importWizard').style.display='flex';
}
function uiwClose(){ document.getElementById('importWizard').style.display='none'; }

function _uiwGo(n){
    UIW.step=n;
    [1,2,3,4].forEach(i=>{
        document.getElementById('uiwStep'+i).style.display=i===n?'':'none';
        document.getElementById('uiwS'+i).className='uiw-step'+(i<n?' done':i===n?' active':'');
    });
    const bb=document.getElementById('uiwBtnBack'); bb.style.display=n>1&&n<4?'':'none';
    const nb=document.getElementById('uiwBtnNext');
    const bc=document.getElementById('uiwBtnCancel');
    if(n===4){ nb.innerHTML=`<i class="fas fa-check"></i> ${TH?'เสร็จสิ้น':'Done'}`; nb.disabled=false; bc.style.display='none'; }
    else if(n===3){ const cnt=UIW.parsedUsers.filter(r=>r.st==='new').length; nb.innerHTML=`<i class="fas fa-file-import"></i> ${TH?`นำเข้า ${cnt} รายการ`:`Import ${cnt} users`}`; nb.disabled=cnt===0; bc.style.display=''; }
    else { nb.innerHTML=`<i class="fas fa-arrow-right"></i> ${TH?'ถัดไป':'Next'}`; nb.disabled=n===1&&!UIW.file; bc.style.display=''; }
    const sub={1:TH?'รองรับไฟล์ .xlsx และ .csv':'Supports .xlsx and .csv',2:TH?'ตรวจสอบการจับคู่ column':'Verify column mapping',3:TH?'ตรวจสอบข้อมูลก่อนนำเข้า':'Review data before import',4:TH?'ผลการนำเข้าข้อมูล':'Import results'};
    document.getElementById('uiwSubtitle').textContent=sub[n]||'';
}

async function uiwNext(){
    if(UIW.step===1){ _uiwRenderMapping(); _uiwGo(2); }
    else if(UIW.step===2){
        const empCol=UIW.mapping.employee_id;
        if(empCol===undefined){ showToast(TH?'กรุณากำหนด column สำหรับ "รหัสพนักงาน"':'Please map the Employee ID column','err'); return; }
        _uiwBuild(); _uiwRenderPreview(); _uiwGo(3);
    }
    else if(UIW.step===3){ await _uiwImport(); }
    else if(UIW.step===4){ uiwClose(); loadData(); }
}
function uiwBack(){ if(UIW.step===2)_uiwGo(1); else if(UIW.step===3)_uiwGo(2); }

/* file read */
function uiwOnFile(inp){
    if(!inp.files.length) return;
    const f=inp.files[0], ext=f.name.split('.').pop().toLowerCase();
    if(!['xlsx','csv','txt'].includes(ext)){ showToast(TH?'รองรับเฉพาะ .xlsx และ .csv':'Only .xlsx and .csv supported','err'); return; }
    if(f.size>10*1024*1024){ showToast(TH?'ไฟล์ใหญ่เกิน 10MB':'File exceeds 10MB','err'); return; }
    UIW.file=f;
    document.getElementById('uiwFileName').textContent=f.name;
    const isX=ext==='xlsx';
    document.getElementById('uiwFileIc').innerHTML=isX?'<i class="fas fa-file-excel"></i>':'<i class="fas fa-file-csv"></i>';
    document.getElementById('uiwFileIc').style.background=isX?'#16a34a':'#2563eb';
    const reader=new FileReader();
    reader.onload=ev=>{
        try{
            const wb=XLSX.read(ev.target.result,{type:'array'});
            const ws=wb.Sheets[wb.SheetNames[0]];
            const data=XLSX.utils.sheet_to_json(ws,{header:1,defval:''});
            const ne=data.filter(r=>r.some(c=>String(c).trim()));
            if(ne.length<2){ showToast(TH?'ไฟล์ไม่มีข้อมูล':'No data in file','err'); return; }
            UIW.headers=ne[0].map(h=>String(h).trim());
            UIW.rawRows=ne.slice(1).filter(r=>r.some(c=>String(c).trim()));
            _uiwAutoMap();
            document.getElementById('uiwFileMeta').textContent=_uiwSz(f.size)+' · '+ext.toUpperCase()+' · '+UIW.rawRows.length+(TH?' แถว':' rows');
            document.getElementById('uiwFileCard').style.display='flex';
            document.getElementById('uiwDrop').style.display='none';
            document.getElementById('uiwBtnNext').disabled=false;
        }catch(e){ showToast((TH?'อ่านไฟล์ไม่ได้: ':'Cannot read: ')+e.message,'err'); }
    };
    reader.readAsArrayBuffer(f);
}
function uiwClearFile(){ UIW.file=null; document.getElementById('uiwFileInput').value=''; document.getElementById('uiwFileCard').style.display='none'; document.getElementById('uiwDrop').style.display=''; document.getElementById('uiwBtnNext').disabled=true; }
function _uiwSz(b){ return b<1024?b+' B':b<1048576?(b/1024).toFixed(1)+' KB':(b/1048576).toFixed(1)+' MB'; }

function _uiwAutoMap(){
    UIW.mapping={};
    UIW.headers.forEach((h,i)=>{
        const lh=h.toLowerCase();
        for(const[f,hints]of Object.entries(UIW_HINTS)){
            if(UIW.mapping[f]!==undefined) continue;
            if(hints.some(hint=>lh.includes(hint.toLowerCase()))){ UIW.mapping[f]=i; break; }
        }
    });
}

function _uiwRenderMapping(){
    document.getElementById('uiwMapRows').innerHTML=UIW.headers.map((h,i)=>{
        const mapped=Object.entries(UIW.mapping).find(([,idx])=>idx===i)?.[0]||'ignore';
        return `<div class="uiw-map-grid">
            <div class="uiw-map-src" title="${escHtml(h)}">${escHtml(h)}</div>
            <div class="uiw-map-arr"><i class="fas fa-long-arrow-alt-right"></i></div>
            <select class="uiw-map-sel" data-col="${i}" onchange="_uiwMapChg(this)">
                ${UIW_FIELDS.map(f=>`<option value="${f.k}"${mapped===f.k?' selected':''}>${f.l}</option>`).join('')}
            </select>
        </div>`;
    }).join('');
    const sr=UIW.rawRows.slice(0,3);
    document.getElementById('uiwSampleContent').innerHTML=
        '<table style="border-collapse:collapse;width:100%"><thead><tr>'+
        UIW.headers.map(h=>`<th style="padding:3px 8px;background:#e0e7ff;color:#312e81;font-weight:700;white-space:nowrap;border:1px solid #c7d2fe;font-size:11px">${escHtml(h)}</th>`).join('')+
        '</tr></thead><tbody>'+sr.map(r=>'<tr>'+UIW.headers.map((_,i)=>`<td style="padding:3px 8px;border:1px solid #e2e8f0;white-space:nowrap">${escHtml(String(r[i]??''))}</td>`).join('')+'</tr>').join('')+
        '</tbody></table>';
}
function _uiwMapChg(sel){
    const ci=parseInt(sel.dataset.col), nf=sel.value;
    Object.keys(UIW.mapping).forEach(k=>{ if(UIW.mapping[k]===ci) delete UIW.mapping[k]; });
    if(nf!=='ignore'){
        if(UIW.mapping[nf]!==undefined){ const p=document.querySelector(`.uiw-map-sel[data-col="${UIW.mapping[nf]}"]`); if(p)p.value='ignore'; }
        UIW.mapping[nf]=ci;
    }
}

function _uiwBuild(){
    const exist=new Set(allUsers.map(u=>(u.username||'').toLowerCase()));
    UIW.parsedUsers=UIW.rawRows.map((row,i)=>{
        const g=f=>{ const idx=UIW.mapping[f]; return idx!==undefined?String(row[idx]??'').trim():''; };
        const empId=g('employee_id'), fn=g('fullname');
        let email=g('email'); if(!email&&empId) email=empId+'@sut.ac.th';
        const errs=[]; let st='new';
        if(!empId){ errs.push(TH?'ไม่มีรหัสพนักงาน':'Missing employee ID'); st='err'; }
        if(!fn) errs.push(TH?'ไม่มีชื่อ':'Missing name');
        if(empId&&exist.has(empId.toLowerCase())) st=st==='err'?'err':'dup';
        let fName='',lName='',fnTh=fn;
        if(fn){ const c=fn.replace(/^(นาย|นางสาว|นาง|ดร\.|Dr\.|Mr\.|Mrs\.|Ms\.)\s*/u,''); const p=c.split(/\s+/); fName=p[0]||empId; lName=p.slice(1).join(' '); }
        else fName=empId;
        return {row:i+1,username:empId,fullname:fn,fnTh,fName,lName,pos:g('position'),sec:g('section'),dept:g('department'),email,phone:g('phone'),st,errs};
    });
}

function _uiwRenderPreview(){
    const u=UIW.parsedUsers, cnt={all:u.length,new:0,dup:0,err:0};
    u.forEach(r=>{ cnt[r.st]=(cnt[r.st]||0)+1; });
    document.getElementById('uiwSum').innerHTML=[
        ['#6366f1',cnt.all,TH?'ทั้งหมด':'Total'],
        ['#15803d',cnt.new,TH?'เพิ่มใหม่':'New'],
        ['#d97706',cnt.dup,TH?'ซ้ำ (username)':'Duplicate'],
        ['#dc2626',cnt.err,TH?'ข้อผิดพลาด':'Error'],
    ].map(([c,v,l])=>`<div class="uiw-sum-c"><div class="uiw-sum-v" style="color:${c}">${v}</div><div class="uiw-sum-l">${l}</div></div>`).join('');
    UIW.pf='all'; _uiwTabs(cnt); _uiwTable('all');
    const ic=u.filter(r=>r.st==='new').length;
    document.getElementById('uiwBtnNext').innerHTML=`<i class="fas fa-file-import"></i> ${TH?`นำเข้า ${ic} รายการ`:`Import ${ic} users`}`;
    document.getElementById('uiwBtnNext').disabled=ic===0;
}
function _uiwTabs(cnt){
    document.getElementById('uiwFtabs').innerHTML=[
        ['all',TH?'ทั้งหมด':'All',cnt.all],
        ['new',TH?'เพิ่มใหม่':'New',cnt.new],
        ['dup',TH?'ซ้ำ':'Duplicate',cnt.dup],
        ['err',TH?'ผิดพลาด':'Error',cnt.err],
    ].map(([k,l,c])=>`<button class="uiw-ftab${UIW.pf===k?' on':''}" onclick="UIW.pf='${k}';_uiwTabRefresh()">${l} <strong>(${c})</strong></button>`).join('');
}
function _uiwTabRefresh(){
    const cnt={all:UIW.parsedUsers.length,new:0,dup:0,err:0};
    UIW.parsedUsers.forEach(r=>{ cnt[r.st]=(cnt[r.st]||0)+1; });
    _uiwTabs(cnt); _uiwTable(UIW.pf);
}
function _uiwTable(f){
    const rows=f==='all'?UIW.parsedUsers:UIW.parsedUsers.filter(r=>r.st===f);
    const bd={new:`<span class="uiw-badge uiw-new"><i class="fas fa-plus-circle"></i> ${TH?'ใหม่':'New'}</span>`,dup:`<span class="uiw-badge uiw-dup"><i class="fas fa-exclamation-circle"></i> ${TH?'ซ้ำ':'Dup'}</span>`,err:`<span class="uiw-badge uiw-err"><i class="fas fa-times-circle"></i> ${TH?'ผิดพลาด':'Error'}</span>`};
    document.getElementById('uiwPreviewBody').innerHTML=rows.map(r=>`<tr>
        <td style="color:var(--c3)">${r.row}</td>
        <td><code style="font-size:11px;background:#f1f5f9;padding:2px 5px;border-radius:4px">${escHtml(r.username)||'<span style="color:#dc2626">—</span>'}</code></td>
        <td style="font-size:12px;font-weight:500">${escHtml(r.fullname)||'<span style="color:var(--c3)">—</span>'}</td>
        <td style="font-size:11px;color:var(--c3)">${escHtml(r.pos||r.sec)}</td>
        <td style="font-size:11px;color:var(--c3)">${escHtml(r.dept)}</td>
        <td style="font-size:11px">${escHtml(r.email)}</td>
        <td>${bd[r.st]||''}${r.errs.length?`<div style="font-size:10px;color:#dc2626;margin-top:2px">${r.errs.join(', ')}</div>`:''}</td>
    </tr>`).join('')||`<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--c3)">${TH?'ไม่มีข้อมูล':'No data'}</td></tr>`;
}

async function _uiwImport(){
    const upd=document.getElementById('uiwUpdateExisting').checked;
    const todo=UIW.parsedUsers.filter(r=>r.st==='new'||(upd&&r.st==='dup'));
    if(!todo.length){ showToast(TH?'ไม่มีรายการที่นำเข้าได้':'Nothing to import','err'); return; }
    document.getElementById('uiwBtnNext').disabled=true;
    document.getElementById('uiwBtnBack').style.display='none';
    _uiwGo(4);
    document.getElementById('uiwResHd').innerHTML=`<div class="uiw-res-ic" style="background:#e0e7ff;color:#6366f1"><i class="fas fa-spinner fa-spin"></i></div><h4 style="margin:0 0 4px;font-weight:700">${TH?'กำลังนำเข้า...':'Importing...'}</h4><p style="font-size:12px;color:var(--c3);margin:0">${TH?`กำลังประมวลผล ${todo.length} รายการ`:`Processing ${todo.length} records`}</p>`;
    document.getElementById('uiwProgBar').style.width='20%';
    document.getElementById('uiwResGrid').innerHTML=''; document.getElementById('uiwErrList').innerHTML='';
    try{
        const resp=await fetch('/v1/api/user_import.php?action=import_json',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({update_existing:upd,rows:todo.map(u=>({username:u.username,fullname:u.fnTh,first_name:u.fName,last_name:u.lName,position:u.pos,section:u.sec,department:u.dept,email:u.email,phone:u.phone}))})});
        const data=await resp.json();
        if(!data.success) throw new Error(data.error||'Import failed');
        document.getElementById('uiwProgBar').style.width='100%';
        const s=data.stats, he=s.errors>0;
        document.getElementById('uiwResHd').innerHTML=`<div class="uiw-res-ic" style="background:${he?'#fef9c3':'#dcfce7'};color:${he?'#854d0e':'#15803d'}"><i class="fas fa-${he?'exclamation-circle':'check-circle'}"></i></div><h4 style="font-size:17px;font-weight:800;margin:0 0 4px">${he?(TH?'นำเข้าเสร็จสิ้น (มีข้อผิดพลาด)':'Done with errors'):(TH?'นำเข้าสำเร็จ!':'Import Successful!')}</h4><p style="font-size:12px;color:var(--c3);margin:0">${TH?`ประมวลผล ${s.total} รายการ`:`Processed ${s.total} records`}</p>`;
        document.getElementById('uiwResGrid').innerHTML=[['#16a34a',s.created,TH?'เพิ่มใหม่':'Created'],['#d97706',s.skipped,TH?'ข้าม':'Skipped'],['#dc2626',s.errors,TH?'ผิดพลาด':'Errors']].map(([c,v,l])=>`<div class="uiw-res-c"><div class="uiw-res-v" style="color:${c}">${v}</div><div class="uiw-res-l">${l}</div></div>`).join('');
        const errs=(data.results||[]).filter(r=>r.status==='error');
        if(errs.length) document.getElementById('uiwErrList').innerHTML=`<div style="margin-top:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;font-size:12px;color:#991b1b"><strong>${TH?'รายการที่ผิดพลาด:':'Errors:'}</strong><ul style="margin:6px 0 0 16px">${errs.map(e=>`<li>${TH?'แถว':'Row'} ${e.row} (${escHtml(e.username)}): ${escHtml(e.reason)}</li>`).join('')}</ul></div>`;
        document.getElementById('uiwBtnNext').disabled=false;
        document.getElementById('uiwBtnNext').innerHTML=`<i class="fas fa-check"></i> ${TH?'เสร็จสิ้น':'Done'}`;
        if(!he) showToast(TH?`นำเข้าสำเร็จ ${s.created} รายการ`:`Imported ${s.created} users`,'ok');
    }catch(e){
        document.getElementById('uiwResHd').innerHTML=`<div class="uiw-res-ic" style="background:#fee2e2;color:#b91c1c"><i class="fas fa-times-circle"></i></div><h4 style="margin:0 0 4px;font-weight:700">${TH?'เกิดข้อผิดพลาด':'Error'}</h4><p style="font-size:12px;color:#b91c1c;margin:0">${escHtml(e.message)}</p>`;
        document.getElementById('uiwProgBar').style.width='100%'; document.getElementById('uiwProgBar').style.background='#dc2626';
        document.getElementById('uiwBtnNext').disabled=false; document.getElementById('uiwBtnNext').innerHTML=`<i class="fas fa-times"></i> ${TH?'ปิด':'Close'}`;
    }
}

/* drag & drop */
const _dd=document.getElementById('uiwDrop');
if(_dd){
    _dd.addEventListener('dragover',e=>{e.preventDefault();_dd.classList.add('ov');});
    _dd.addEventListener('dragleave',()=>_dd.classList.remove('ov'));
    _dd.addEventListener('drop',e=>{e.preventDefault();_dd.classList.remove('ov');if(e.dataTransfer.files.length){document.getElementById('uiwFileInput').files=e.dataTransfer.files;uiwOnFile(document.getElementById('uiwFileInput'));}});
}

loadData();
</script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>

<!-- ════ BULK ACTION BAR ════ -->
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-cnt"><span class="n" id="bulkCnt">0</span>&nbsp;<?php echo $TH?'รายการที่เลือก':'selected'?></div>
    <div class="bulk-sep"></div>
    <div class="bulk-acts">
        <?php if ($isAdmin): ?>
        <button class="blk blk-role" onclick="openBulkRolePicker()"><i class="fas fa-shield-alt"></i> <?php echo $TH?'เปลี่ยนสิทธิ์':'Role'?></button>
        <button class="blk blk-on" onclick="bulkToggle(true)"><i class="fas fa-check"></i> <?php echo $TH?'เปิดใช้งาน':'Activate'?></button>
        <button class="blk blk-off" onclick="bulkToggle(false)"><i class="fas fa-ban"></i> <?php echo $TH?'ปิดใช้งาน':'Deactivate'?></button>
        <button class="blk blk-room" onclick="bulkManageRooms()"><i class="fas fa-map-marker-alt"></i> <?php echo $TH?'จัดการห้อง':'Rooms'?></button>
        <button class="blk blk-del" onclick="bulkDelete()"><i class="fas fa-trash"></i> <?php echo $TH?'ลบ':'Delete'?></button>
        <?php endif; ?>
    </div>
    <button class="blk blk-clr" onclick="clearSelection()" title="<?php echo $TH?'ยกเลิกการเลือก':'Clear selection'?>"><i class="fas fa-times"></i></button>
</div>

<!-- ════ BULK CONFIRM MODAL ════ -->
<div class="bkc-ov" id="bkcOv">
    <div class="bkc-box">
        <div class="bkc-hd">
            <div class="bkc-ic" id="bkcIc"><i id="bkcIcon" class="fas fa-check-circle"></i></div>
            <div class="bkc-title" id="bkcTitle"></div>
            <div class="bkc-desc" id="bkcDesc"></div>
        </div>
        <div class="bkc-users" id="bkcUserList"></div>
        <div class="bkc-type-wrap" id="bkcTypeWrap" style="display:none">
            <div class="bkc-type-lbl" id="bkcTypeLbl"><?php echo $TH?'พิมพ์ DELETE เพื่อยืนยัน':'Type DELETE to confirm'?></div>
            <input type="text" class="bkc-type-inp" id="bkcTypeInp" placeholder="DELETE" oninput="onBkcType(this)" autocomplete="off" spellcheck="false">
        </div>
        <div class="bkc-ft">
            <button class="usr-btn usr-btn-g" onclick="closeBulkConfirm()"><?php echo $TH?'ยกเลิก':'Cancel'?></button>
            <button class="usr-btn usr-btn-p" id="bkcConfirmBtn" onclick="execBulkConfirm()"><?php echo $TH?'ยืนยัน':'Confirm'?></button>
        </div>
    </div>
</div>

<!-- ════ BULK ROLE PICKER ════ -->
<div class="brp-ov" id="bulkRolePicker">
    <div class="brp-box">
        <div class="brp-hd">
            <div class="brp-hd-ic"><i class="fas fa-shield-alt"></i></div>
            <h3><?php echo $TH?'เปลี่ยนสิทธิ์ผู้ใช้':'Change User Role'?></h3>
            <button class="usr-mx" onclick="closeBulkRolePicker()"><i class="fas fa-times"></i></button>
        </div>
        <div class="brp-bd">
            <div class="brp-sub" id="brpSub"><i class="fas fa-users"></i> <?php echo $TH?'ผู้ใช้ที่เลือก':'Selected users'?></div>
            <div class="brp-users" id="brpUserList"></div>
            <div class="brp-sec-lbl"><i class="fas fa-angle-right" style="font-size:9px;color:#6366f1"></i> <?php echo $TH?'เลือกสิทธิ์ใหม่':'Select new role'?></div>
            <div class="brp-sel-wrap">
                <select id="brpRoleSelect" class="brp-sel">
                    <option value=""><?php echo $TH?'— เลือกสิทธิ์ —':'— Select role —'?></option>
                </select>
            </div>
        </div>
        <div class="brp-ft">
            <button class="usr-btn usr-btn-g" onclick="closeBulkRolePicker()"><?php echo $TH?'ยกเลิก':'Cancel'?></button>
            <button class="usr-btn usr-btn-p" onclick="applyBulkRole()"><i class="fas fa-check"></i> <?php echo $TH?'ยืนยัน':'Apply'?></button>
        </div>
    </div>
</div>

</body></html>
