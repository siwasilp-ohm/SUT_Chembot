<?php
/**
 * My Room — Chemical Management for Assigned Rooms (Pro Edition)
 */
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$TH   = ($lang === 'th');
$uid  = (int)$user['id'];
$role = $user['role_name'];
$canEdit = in_array($role, ['admin', 'lab_manager', 'user']);
Layout::head($TH ? 'ห้องของฉัน' : 'My Rooms');
?>
<style>
/* ── Override accent to indigo ── */
:root{
  --accent:#6366f1;--sg:#16a34a;--sy:#d97706;--sr:#dc2626;--sb:#2563eb;--sp:#7c3aed;
  --stk-r:14px;--stk-rs:10px;--stk-sh:0 1px 6px rgba(0,0,0,.06);--stk-shm:0 4px 20px rgba(0,0,0,.08);
  --mr:#6366f1;--mr2:#8b5cf6;--mrd:#4338ca;--mrbg:#eef2ff;--mrbrd:#c7d2fe;
}

/* ── Hero ── */
.mr-hero{background:linear-gradient(135deg,#1e1b4b 0%,#3730a3 55%,#6366f1 100%);border-radius:var(--stk-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.mr-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.mr-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.15);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;position:relative}
.mr-hero-info{position:relative;flex:1}
.mr-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.mr-hero-info p{font-size:12px;opacity:.85;margin:0}
.mr-hero-meta{margin-left:auto;display:flex;gap:24px;flex-shrink:0;position:relative}
.mr-hero-c{text-align:center}
.mr-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.mr-hero-c .lb{font-size:10px;opacity:.7;margin-top:3px;text-transform:uppercase;letter-spacing:.5px}

/* ── Room Tabs ── */
.mr-room-tabs{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;margin-bottom:16px;scrollbar-width:thin}
.mr-room-tabs::-webkit-scrollbar{height:4px}
.mr-room-tabs::-webkit-scrollbar-thumb{background:var(--mrbrd);border-radius:2px}
.mr-room-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:50px;border:1.5px solid var(--mrbrd);background:#fff;color:var(--mrd);font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .15s;font-family:inherit}
.mr-room-tab:hover{border-color:var(--mr);background:var(--mrbg)}
.mr-room-tab.active{background:var(--mr);color:#fff;border-color:var(--mr)}
.mr-rt-code{font-weight:800;font-size:11px}
.mr-rt-name{opacity:.85}
.mr-rt-cnt{background:rgba(0,0,0,.15);padding:1px 7px;border-radius:10px;font-size:10px;font-weight:700}
.mr-room-tab.active .mr-rt-cnt{background:rgba(255,255,255,.25)}

/* ── Stats Bar ── */
@keyframes mr-stat-in{0%{opacity:0;transform:translateY(14px) scale(.95)}62%{transform:translateY(-3px) scale(1.015)}100%{opacity:1;transform:translateY(0) scale(1)}}
@keyframes mr-stat-ic-pop{0%{opacity:0;transform:scale(.5) rotate(-10deg)}65%{transform:scale(1.2) rotate(3deg)}100%{opacity:1;transform:scale(1) rotate(0deg)}}
@keyframes mr-sk-shimmer{0%{background-position:-600px 0}100%{background-position:600px 0}}
.mr-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px}
.mr-stat{position:relative;overflow:hidden;background:#fff;border-radius:var(--stk-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--stk-sh);border:1px solid var(--border,#e0e0e0);transition:border-color .15s,box-shadow .15s,transform .15s,background .15s;cursor:pointer;user-select:none}
.mr-stat::before{content:'';position:absolute;top:0;right:0;width:52px;height:52px;border-radius:0 var(--stk-rs) 0 52px;opacity:.06;background:currentColor;transition:opacity .15s}
.mr-stat:hover{transform:translateY(-2px);box-shadow:var(--stk-shm)}
.mr-stat:hover::before{opacity:.1}
.mr-stat:active{transform:translateY(-1px)}
.mr-stat.active{border-color:var(--mr-stat-col,var(--mr));box-shadow:0 0 0 3px var(--mr-stat-glow,rgba(99,102,241,.18)),var(--stk-shm);transform:translateY(-2px)}
.mr-stat-ic{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.mr-stat-v{font-size:20px;font-weight:800;color:var(--c1,#333);line-height:1;font-variant-numeric:tabular-nums}
.mr-stat-l{font-size:10px;color:var(--c3,#999);margin-top:3px;text-transform:uppercase;letter-spacing:.4px}
/* entrance animation */
.mr-stat.entering{animation:mr-stat-in .44s cubic-bezier(.34,1.25,.64,1) both}
.mr-stat.entering .mr-stat-ic{animation:mr-stat-ic-pop .4s cubic-bezier(.34,1.4,.64,1) both}
/* mrList pop on filter change */
@keyframes mr-list-pop{0%{opacity:0;transform:translateY(14px) scale(.95)}62%{transform:translateY(-3px) scale(1.015)}100%{opacity:1;transform:translateY(0) scale(1)}}
.mr-list-anim{animation:mr-list-pop .44s cubic-bezier(.34,1.25,.64,1) both}
/* skeleton */
.mr-stat-sk{pointer-events:none;cursor:default}
.mr-sk{border-radius:6px;background:linear-gradient(90deg,#f1f5f9 25%,#e4eaf4 50%,#f1f5f9 75%);background-size:800px 100%;animation:mr-sk-shimmer 1.5s ease-in-out infinite}
.mr-sk-ic{width:38px;height:38px;border-radius:10px;flex-shrink:0}
.mr-sk-num{height:20px;width:38px;margin-bottom:7px}
.mr-sk-lbl{height:9px;width:62px}
/* ── Skeleton List ── */
.mr-sk-card{background:#fff;border:1.5px solid #f1f5f9;border-radius:var(--stk-r);overflow:hidden;pointer-events:none}
.mr-sk-card-hd{padding:14px 14px 6px;display:flex;align-items:flex-start;gap:10px}
.mr-sk-card-bd{padding:4px 14px 14px;display:flex;flex-direction:column;gap:7px}
.mr-sk-card-nm{flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;padding-top:2px}
.mr-sk-trow td{padding:9px 14px;border-bottom:1px solid #f0f4f8;vertical-align:middle}
.mr-sk-trow td>div{display:flex;flex-direction:column;gap:5px}
.mr-sk-grp-row .mr-grp-hdr{cursor:default;pointer-events:none}

/* ── Toolbar ── */
.mr-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}
.stk-search{flex:1;min-width:180px;position:relative}
.stk-search input{width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border,#e0e0e0);border-radius:var(--stk-rs);font-size:13px;background:#fff;color:var(--c1,#333);font-family:inherit;transition:border .15s}
.stk-search input:focus{outline:none;border-color:var(--mr);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.stk-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--c3,#999);font-size:13px}

/* ── View Switcher ── */
.stk-vw{display:flex;border:1.5px solid var(--border,#e0e0e0);border-radius:var(--stk-rs);overflow:hidden;flex-shrink:0}
.stk-vw button{padding:7px 11px;border:none;background:#fff;color:var(--c3,#999);cursor:pointer;font-size:12px;transition:all .12s;display:flex;align-items:center;gap:4px;font-family:inherit;white-space:nowrap}
.stk-vw button+button{border-left:1px solid var(--border,#e0e0e0)}
.stk-vw button.active{background:var(--accent);color:#fff}
.stk-vw button:hover:not(.active){background:#f8fafc}

/* ── Action Buttons ── */
.stk-btn{padding:8px 14px;border:none;border-radius:var(--stk-rs);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .12s;white-space:nowrap}
.stk-btn-g{background:transparent;color:var(--c3,#999);border:1.5px solid var(--border,#e0e0e0)}.stk-btn-g:hover{border-color:var(--mr);color:var(--mr)}
.stk-btn-g.active{background:var(--mrbg);border-color:var(--mr);color:var(--mr)}

/* ── Main Layout ── */
.mr-layout{display:flex;gap:16px;align-items:flex-start;position:relative}
.mr-content{flex:1;min-width:0}

/* ── Loading ── */
.mr-ld{text-align:center;padding:32px;color:var(--c3,#999);font-size:13px}

/* ── Type Icons ── */
.type-icon{width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.type-bottle{background:#dbeafe;color:#2563eb}.type-vial{background:#ede9fe;color:#7c3aed}
.type-flask{background:#d1fae5;color:#059669}.type-canister{background:#fed7aa;color:#ea580c}
.type-cylinder{background:#fecdd3;color:#e11d48}.type-ampoule{background:#e0e7ff;color:#4338ca}
.type-bag{background:#f5f5f4;color:#78716c}.type-other{background:#f1f5f9;color:#64748b}

/* ── Badges ── */
.stk-badge{font-size:9px;padding:3px 8px;border-radius:6px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;display:inline-block;white-space:nowrap}
.badge-ok{background:#dcfce7;color:#15803d}
.badge-exp{background:#fce7f3;color:#be185d}
.badge-warn{background:#fef9c3;color:#a16207}
.badge-grey{background:#f1f5f9;color:#64748b}

/* ── Location Badge ── */
.mr-loc{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;font-size:10px;font-weight:600;white-space:nowrap}
.mr-loc.placed{background:var(--mrbg);color:var(--mrd)}
.mr-loc.unplaced{background:#fef3c7;color:#92400e}

/* ── Owner Avatar ── */
.mr-av{width:20px;height:20px;border-radius:50%;background:var(--mr);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:8px;font-weight:700;flex-shrink:0}

/* ─────────────── TABLE VIEW ─────────────── */
.mr-tw{overflow-x:auto;border-radius:var(--stk-r);border:1px solid var(--border,#e0e0e0);background:#fff;box-shadow:var(--stk-sh)}
.mr-t{width:100%;border-collapse:collapse;font-size:12px}
.mr-t th{background:#f8fafc;padding:10px 12px;text-align:left;font-weight:700;color:var(--c3,#999);font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border,#e0e0e0);white-space:nowrap;user-select:none;position:sticky;top:0;z-index:1}
.mr-t td{padding:9px 12px;border-bottom:1px solid #f1f5f9;color:var(--c1,#333);vertical-align:middle}
.mr-t tbody tr:hover{background:#fafbff}
.mr-t tbody tr.sel{background:#eef2ff}
.mr-t .col-cb{width:36px}
.mr-t .col-ic{width:44px}
.mr-t .col-act{width:96px;white-space:nowrap}
.mr-t-code{font-family:'Courier New',monospace;font-size:11px;font-weight:700;background:#f1f5f9;padding:2px 6px;border-radius:4px;display:inline-block}
.mr-t-chem{font-weight:600;font-size:12px}
.mr-t-formula{font-size:10px;color:var(--c3,#999);margin-top:1px}
.mr-t-owner{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--c2,#666)}
.mr-t-act{padding:4px 6px;border:1px solid var(--border,#e0e0e0);border-radius:6px;background:#f8fafc;color:var(--c3,#999);cursor:pointer;font-size:11px;transition:all .1s;display:inline-flex;align-items:center}
.mr-t-act:hover{border-color:var(--mr);color:var(--mr);background:var(--mrbg)}
.mr-t-act.borrow:hover{border-color:#059669;color:#059669;background:#dcfce7}

/* ── Custom Checkbox ── */
.mr-cb{
  width:16px;height:16px;
  border:2px solid #cbd5e1;border-radius:4px;
  cursor:pointer;
  -webkit-appearance:none;appearance:none;
  background-color:#fff;background-image:none;
  background-repeat:no-repeat;background-position:center;background-size:10px 10px;
  transition:background-color .12s,border-color .12s;
  flex-shrink:0;display:inline-block;vertical-align:middle;
}
.mr-cb:checked,
.mr-cb.is-checked{
  background-color:var(--mr) !important;
  border-color:var(--mr) !important;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2 6l3 3 5-5' stroke='%23ffffff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E%3C/svg%3E") !important;
}
.mr-cb:hover:not(:checked):not(.is-checked){border-color:var(--mr)}

/* ─────────────── CARD VIEW ─────────────── */
@keyframes mr-card-in{0%{opacity:0;transform:translateY(16px) scale(.95)}62%{transform:translateY(-3px) scale(1.012)}100%{opacity:1;transform:translateY(0) scale(1)}}
@keyframes mr-row-in{from{opacity:0;transform:translateX(-12px)}to{opacity:1;transform:translateX(0)}}
@keyframes mr-grp-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.mr-card-anim{animation:mr-card-in .38s cubic-bezier(.34,1.18,.64,1) both}
.mr-row-anim{animation:mr-row-in .2s ease both}
.mr-grp-anim{animation:mr-grp-in .26s ease both}
.mr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
.mr-card{background:#fff;border:1.5px solid var(--border,#e0e0e0);border-radius:var(--stk-r);overflow:hidden;transition:all .18s;position:relative}
.mr-card:hover{border-color:var(--mr);box-shadow:var(--stk-shm);transform:translateY(-2px)}
.mr-card.sel{border-color:var(--mr);box-shadow:0 0 0 2px rgba(99,102,241,.2);background:#fafbff}
.mr-card-cb{position:absolute;top:10px;left:10px;z-index:2}
.mr-card-hd{padding:14px 14px 0;display:flex;align-items:flex-start;gap:10px}
.mr-card-nm{flex:1;min-width:0}
.mr-card-code{font-size:11px;font-weight:800;font-family:'Courier New',monospace;background:#f1f5f9;padding:2px 6px;border-radius:4px;display:inline-block;margin-bottom:3px}
.mr-card-chem{font-size:13px;font-weight:700;color:var(--c1,#333);line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mr-card-formula{font-size:10px;color:var(--c3,#999);margin-top:1px}
.mr-card-bd{padding:10px 14px 14px;display:flex;flex-direction:column;gap:6px}
.mr-card-row{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--c2,#666)}
.mr-card-row i{width:14px;text-align:center;color:var(--c3,#999);font-size:10px}
.mr-card-act{display:flex;gap:6px;margin-top:4px}
.mr-act-btn{flex:1;padding:6px;border-radius:7px;border:1.5px solid var(--border,#e0e0e0);background:#f8fafc;color:var(--c2,#666);cursor:pointer;font-size:11px;transition:all .12s;display:flex;align-items:center;justify-content:center;gap:4px;font-family:inherit}
.mr-act-btn:hover{border-color:var(--mr);color:var(--mr);background:var(--mrbg)}
.mr-act-btn.borrow:hover{border-color:#059669;color:#059669;background:#dcfce7}

/* ─────────────── GROUPED VIEW ─────────────── */
.mr-groups{display:flex;flex-direction:column;gap:10px}
.mr-grp{background:#fff;border:1.5px solid var(--border,#e0e0e0);border-radius:var(--stk-r);overflow:hidden;box-shadow:var(--stk-sh);transition:border-color .15s}
.mr-grp.open{border-color:var(--mrbrd)}
.mr-grp-hdr{display:flex;align-items:center;gap:10px;padding:13px 16px;cursor:pointer;transition:background .12s;user-select:none}
.mr-grp-hdr:hover{background:#fafbff}
.mr-grp-toggle{width:22px;height:22px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--c3,#999);flex-shrink:0;transition:all .15s}
.mr-grp.open .mr-grp-toggle{background:var(--mrbg);color:var(--mr);transform:rotate(90deg)}
.mr-grp-ic{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.mr-grp-info{flex:1;min-width:0}
.mr-grp-name{font-size:13px;font-weight:700;color:var(--c1,#333);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mr-grp-sub{font-size:10px;color:var(--c3,#999);margin-top:1px;display:flex;gap:8px;flex-wrap:wrap}
.mr-grp-meta{display:flex;align-items:center;gap:8px;flex-shrink:0}
.mr-grp-cnt{background:var(--mrbg);color:var(--mrd);padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700}
.mr-grp-qty{font-size:11px;color:var(--c3,#999);font-weight:600}
.mr-grp-body{border-top:1px solid #f1f5f9;max-height:0;overflow:hidden;transition:max-height .3s ease}
.mr-grp.open .mr-grp-body{max-height:2000px}
.mr-grp-row{display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid #f8fafc;font-size:12px;transition:background .1s}
.mr-grp-row:last-child{border-bottom:none}
.mr-grp-row:hover{background:#fafbff}
.mr-grp-row.sel{background:#eef2ff}
.mr-grp-code{font-family:'Courier New',monospace;font-size:11px;font-weight:700;background:#f1f5f9;padding:2px 6px;border-radius:4px;flex-shrink:0;white-space:nowrap}
.mr-grp-loc{flex:1;min-width:0;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.mr-grp-owner{font-size:10px;color:var(--c3,#999);display:flex;align-items:center;gap:4px;white-space:nowrap}
.mr-grp-actions{display:flex;gap:4px;flex-shrink:0}

/* ── Empty ── */
.mr-empty{text-align:center;padding:60px 20px;color:var(--c3,#999)}
.mr-empty i{font-size:40px;margin-bottom:12px;display:block;opacity:.4}
.mr-empty p{font-size:14px}

/* ─────────────── BATCH BAR ─────────────── */
@keyframes batchIn{from{opacity:0;transform:translateX(-50%) translateY(16px) scale(.96)}to{opacity:1;transform:translateX(-50%) translateY(0) scale(1)}}
.stk-batch{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:1000;display:flex;align-items:center;gap:0;background:rgba(10,15,30,.92);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:6px 8px;box-shadow:0 8px 40px rgba(0,0,0,.5),0 2px 12px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.06);max-width:92vw;animation:batchIn .25s cubic-bezier(.34,1.56,.64,1)}
.bb-count{display:flex;align-items:center;gap:8px;padding:2px 12px 2px 6px;border-right:1px solid rgba(255,255,255,.1);margin-right:4px}
.bb-num{background:var(--accent);color:#fff;font-size:13px;font-weight:800;min-width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 6px;line-height:1}
.bb-lbl{font-size:11px;color:rgba(255,255,255,.55);font-weight:600;white-space:nowrap}
.bb-grp{display:flex;align-items:center;gap:3px;padding:0 6px}
.bb-grp+.bb-grp{border-left:1px solid rgba(255,255,255,.08)}
.bab{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:10px;font-size:11.5px;font-weight:700;border:none;cursor:pointer;transition:.15s;white-space:nowrap;font-family:inherit;letter-spacing:.2px}
.bab i{font-size:12px}
.bab-move{background:rgba(99,102,241,.2);color:#a5b4fc}.bab-move:hover{background:rgba(99,102,241,.38);color:#c7d2fe}
.bab-txn{background:rgba(139,92,246,.2);color:#c4b5fd}.bab-txn:hover{background:rgba(139,92,246,.38);color:#ddd6fe}
.bab-rpt{background:rgba(16,185,129,.2);color:#6ee7b7}.bab-rpt:hover{background:rgba(16,185,129,.38);color:#a7f3d0}
.bab-cancel{background:none;color:rgba(255,255,255,.35);width:32px;height:32px;padding:0;border-radius:8px;justify-content:center;font-size:14px}
.bab-cancel:hover{background:rgba(239,68,68,.2);color:#f87171}
@media(max-width:600px){.bab-lbl{display:none}.bab{padding:7px 9px}.bb-lbl{display:none}.bb-count{padding:2px 8px 2px 4px}}

/* ─────────────── MODALS ─────────────── */
.mr-modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center;padding:20px}
.mr-modal-ov.show{display:flex}
.mr-modal{background:#fff;border-radius:var(--stk-r);width:100%;max-width:480px;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.35);animation:mrIn .18s ease-out}
.mr-modal.wide{max-width:620px}
@keyframes mrIn{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:none}}
.mr-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border,#e0e0e0);position:sticky;top:0;background:#fff;z-index:2}
.mr-modal-hdr h3{font-size:15px;font-weight:700;margin:0;color:var(--mrd);display:flex;align-items:center;gap:8px}
.mr-modal-hdr h3 i{color:var(--mr)}
.mr-modal-x{background:none;border:none;font-size:20px;color:#94a3b8;cursor:pointer;padding:2px;line-height:1}
.mr-modal-x:hover{color:#ef4444}
.mr-modal-body{padding:20px;display:flex;flex-direction:column;gap:14px}
.mr-modal-footer{padding:14px 20px;border-top:1px solid var(--border,#e0e0e0);display:flex;gap:8px;justify-content:flex-end}
.mr-fg label{display:block;font-size:11px;font-weight:700;color:var(--c2,#666);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px}
.mr-fg input,.mr-fg select,.mr-fg textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border,#e0e0e0);border-radius:8px;font-size:13px;font-family:inherit;color:var(--c1,#333);background:#fff;transition:border .15s;box-sizing:border-box}
.mr-fg input:focus,.mr-fg select:focus,.mr-fg textarea:focus{outline:none;border-color:var(--mr);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.mr-fg textarea{resize:vertical;min-height:80px}
.mr-btn{padding:9px 20px;border-radius:9px;border:none;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .12s;display:inline-flex;align-items:center;gap:6px}
.mr-btn-p{background:var(--mr);color:#fff}.mr-btn-p:hover{background:var(--mrd)}
.mr-btn-p:disabled{opacity:.6;cursor:not-allowed}
.mr-btn-g{background:#f1f5f9;color:var(--c2,#666)}.mr-btn-g:hover{background:#e2e8f0}
.mr-mini-card{background:var(--mrbg);border:1px solid var(--mrbrd);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--mrd)}
.mr-bpl-item{display:flex;align-items:center;gap:8px;padding:6px 10px;background:#f8fafc;border-radius:8px;border:1px solid var(--border,#e0e0e0);font-size:11px;margin-bottom:4px}
.mr-bpl-code{font-family:'Courier New',monospace;font-weight:700;flex-shrink:0}
.mr-bpl-chem{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--c2,#666)}

/* ── Toast ── */
.mr-toast-c{position:fixed;bottom:80px;right:24px;z-index:10000;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
.mr-toast{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.2);animation:toastIn .25s ease-out;transition:opacity .3s,transform .3s;max-width:340px}
.mr-toast.ok{background:#059669;color:#fff}.mr-toast.err{background:#dc2626;color:#fff}
@keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}

/* ════════════════ STORAGE TREE PANEL ════════════════ */

/* Tree: sticky scrollable panel */
.mr-tree{
  width:288px;flex-shrink:0;
  position:sticky;top:16px;
  max-height:calc(100vh - 110px);
  overflow-y:auto;
  scrollbar-width:thin;scrollbar-color:var(--mrbrd) transparent;
  background:#fff;
  border:1.5px solid var(--mrbrd);
  border-radius:12px;
  box-shadow:0 2px 16px rgba(99,102,241,.07),0 1px 4px rgba(0,0,0,.04);
  display:flex;flex-direction:column;
}
.mr-tree::-webkit-scrollbar{width:3px}
.mr-tree::-webkit-scrollbar-thumb{background:var(--mrbrd);border-radius:2px}
.mr-tree.collapsed{display:none}

/* Tree header sticky inside panel */
.mr-tree-hdr{
  position:sticky;top:0;z-index:5;
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 12px;flex-shrink:0;
  background:linear-gradient(135deg,var(--mrbg) 0%,#f5f3ff 100%);
  border-bottom:1px solid var(--mrbrd);
  font-size:12px;font-weight:700;color:var(--mrd);gap:6px;
}
.mr-tree-hdr-acts{display:flex;gap:4px;flex-shrink:0}

/* Unplaced zone */
.mr-tree-unplaced{
  display:flex;align-items:center;gap:8px;flex-shrink:0;
  padding:8px 12px;
  background:#fffbeb;border-bottom:1px solid #fde68a;
  font-size:12px;font-weight:600;color:#92400e;cursor:pointer;transition:all .15s;
}
.mr-tree-unplaced:hover{background:#fef3c7}
.mr-tree-unplaced.active{background:#fef3c7;box-shadow:inset 3px 0 0 #f59e0b}
.mr-tree-unplaced.drop-over{background:#fef3c7;box-shadow:inset 0 0 0 2px #f59e0b}
.mr-tree-cnt{margin-left:auto;background:#fff;color:var(--c2,#666);padding:1px 6px;border-radius:7px;font-size:10px;font-weight:700;border:1px solid #e2e8f0}
.mr-tree-cnt.amber{background:#fef3c7;color:#92400e;border-color:#fde68a}

/* Empty state */
.mr-tree-empty{text-align:center;padding:28px 16px;color:var(--c3,#999);font-size:12px}
.mr-tree-empty i{font-size:26px;margin-bottom:8px;display:block;opacity:.3}

/* ── CABINET ── */
.mr-cab-wrap{border-bottom:1px solid #f1f5f9}
.mr-cab-wrap:last-child{border-bottom:none}

.mr-cab-hdr{
  display:flex;align-items:center;gap:7px;
  padding:8px 10px 8px 12px;
  cursor:pointer;transition:background .1s;user-select:none;
  border-left:3px solid var(--cab-col,var(--mr));
}
.mr-cab-hdr:hover{background:#fafbff}
.mr-cab-hdr.active{background:var(--mrbg)}
.mr-cab-hdr.drop-over{background:#ede9fe}
.mr-cab-hdr[data-type="storage"]        {--cab-col:#6366f1}
.mr-cab-hdr[data-type="refrigerator"]   {--cab-col:#0ea5e9}
.mr-cab-hdr[data-type="freezer"]        {--cab-col:#38bdf8}
.mr-cab-hdr[data-type="fume_hood"]      {--cab-col:#f59e0b}
.mr-cab-hdr[data-type="safety_cabinet"] {--cab-col:#dc2626}
.mr-cab-hdr[data-type="other"]          {--cab-col:#94a3b8}
.mr-cab-ic{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.mr-cab-meta{flex:1;min-width:0}
.mr-cab-name{font-size:12px;font-weight:700;color:var(--c1,#333);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.3}
.mr-cab-sub{font-size:10px;color:var(--c3,#999);margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mr-cnt-pill{font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:var(--mrbg);color:var(--mrd);border:1px solid var(--mrbrd);flex-shrink:0;white-space:nowrap;min-width:22px;text-align:center}

/* Action buttons (reveal on hover) */
.mr-node-acts{display:flex;gap:2px;flex-shrink:0;opacity:0;transition:opacity .12s}
.mr-cab-hdr:hover .mr-node-acts,.mr-cab-hdr.active .mr-node-acts,
.mr-shelf-hdr:hover .mr-node-acts,.mr-shelf-hdr.active .mr-node-acts,
.mr-slot-row:hover .mr-node-acts,.mr-slot-row.active .mr-node-acts{opacity:1}
.mr-nibtn{width:20px;height:20px;border:none;border-radius:4px;background:#f1f5f9;color:#94a3b8;cursor:pointer;font-size:9px;display:flex;align-items:center;justify-content:center;transition:all .12s;padding:0}
.mr-nibtn:hover{background:var(--mrbg);color:var(--mr)}
.mr-nibtn.red:hover{background:#fee2e2;color:#dc2626}
.mr-nibtn.grn:hover{background:#dcfce7;color:#16a34a}

/* Toggle chevron */
.mr-toggle{width:16px;height:16px;border:none;background:none;color:var(--c3,#999);cursor:pointer;font-size:8px;display:flex;align-items:center;justify-content:center;border-radius:3px;transition:all .15s;flex-shrink:0;padding:0}
.mr-toggle:hover{background:var(--mrbrd);color:var(--mr)}
.mr-toggle.open{color:var(--mr)}
.mr-toggle .fa-chevron-right{transition:transform .15s}
.mr-toggle.open .fa-chevron-right{transform:rotate(90deg)}

/* Inline rename */
.mr-rename-inp{flex:1;min-width:0;padding:2px 6px;border:1.5px solid var(--mr);border-radius:5px;font-size:12px;font-weight:600;font-family:inherit;color:var(--c1,#333);background:#fff;outline:none;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.mr-node-name{flex:1;min-width:0;font-size:12px;font-weight:600;color:var(--c1,#333);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mr-node-sub{font-size:10px;color:var(--c3,#999);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ── SHELVES ── */
.mr-shelves{padding:3px 0 5px;position:relative}
.mr-shelves::before{content:'';position:absolute;left:22px;top:6px;bottom:14px;width:1px;background:#e9ecf1;pointer-events:none}

.mr-shelf-wrap{margin:1px 6px 1px 12px;position:relative}
.mr-shelf-wrap::before{content:'';position:absolute;left:-9px;top:13px;width:9px;height:1px;background:#e9ecf1;pointer-events:none}

.mr-shelf-hdr{
  display:flex;align-items:center;gap:5px;
  padding:5px 7px;border-radius:7px;
  cursor:pointer;transition:background .1s;user-select:none;
}
.mr-shelf-hdr:hover{background:#f8fafc}
.mr-shelf-hdr.active{background:var(--mrbg);box-shadow:inset 2px 0 0 var(--mr)}
.mr-shelf-hdr.drop-over{background:#ede9fe;box-shadow:inset 0 0 0 1.5px var(--mr)}
.mr-shelf-name{flex:1;min-width:0;font-size:11px;font-weight:600;color:var(--c2,#666);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Capacity bar */
.mr-cap-wrap{display:flex;align-items:center;gap:4px;flex-shrink:0}
.mr-cap-track{width:24px;height:3px;background:#e2e8f0;border-radius:2px;overflow:hidden}
.mr-cap-fill{height:100%;background:var(--mr);border-radius:2px;transition:width .3s}
.mr-cap-fill.cap-full{background:#16a34a}
.mr-cap-txt{font-size:9px;font-weight:700;color:var(--c3,#999);white-space:nowrap}

/* ── SLOTS ── */
.mr-slot-list{display:flex;flex-direction:column;gap:1px;padding:2px 4px 5px 18px;position:relative}
.mr-slot-list::before{content:'';position:absolute;left:10px;top:4px;bottom:14px;width:1px;background:#f0f1f5;pointer-events:none}

.mr-slot-item{position:relative}
.mr-slot-item::before{content:'';position:absolute;left:-7px;top:12px;width:7px;height:1px;background:#f0f1f5;pointer-events:none}

.mr-slot-row{
  display:flex;align-items:center;gap:5px;
  padding:4px 6px;border-radius:6px;font-size:11px;
  cursor:pointer;transition:all .1s;border:1px solid transparent;
}
.mr-slot-row:hover{background:var(--mrbg);border-color:var(--mrbrd)}
.mr-slot-row.active{background:var(--mrbg);border-color:var(--mrbrd)}
.mr-slot-row.drop-over{background:#ede9fe;border-color:var(--mr);box-shadow:0 0 0 1.5px rgba(99,102,241,.2)}
.mr-slot-code{font-family:'Courier New',monospace;font-size:10px;font-weight:800;color:var(--mrd);background:var(--mrbg);padding:1px 5px;border-radius:4px;border:1px solid var(--mrbrd);flex-shrink:0;min-width:18px;text-align:center}
.mr-slot-name-lbl{flex:1;min-width:0;color:var(--c3,#999);font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mr-slot-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;background:#e2e8f0;transition:background .15s}
.mr-slot-dot.filled{background:var(--mr)}
.mr-cnt-pill-sm{font-size:9px;font-weight:700;color:var(--c3,#999);flex-shrink:0;white-space:nowrap}

/* ── CHIPS (containers inside slot) ── */
.mr-chip-list{padding:2px 6px 4px 10px;display:flex;flex-direction:column;gap:2px}
.mr-chip{
  display:flex;align-items:center;gap:6px;
  padding:5px 8px;
  background:#fafbff;border:1px solid var(--mrbrd);border-radius:7px;
  font-size:11px;cursor:pointer;transition:all .12s;
}
.mr-chip:hover{border-color:var(--mr);background:#eef2ff;box-shadow:0 1px 4px rgba(99,102,241,.1)}
.mr-chip[draggable="true"]{cursor:grab}.mr-chip[draggable="true"]:active{cursor:grabbing}
.mr-chip.dragging{opacity:.4}
.mr-chip-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.mr-chip-dot.ok{background:#16a34a}.mr-chip-dot.warn{background:#f59e0b}
.mr-chip-dot.exp{background:#dc2626}.mr-chip-dot.none{background:#cbd5e1}
.mr-chip-code{font-family:'Courier New',monospace;font-weight:700;font-size:10px;color:var(--mr);flex-shrink:0}
.mr-chip-nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--c2,#666)}
.mr-chip-qty{font-size:10px;color:var(--c3,#999);flex-shrink:0;white-space:nowrap}

/* ── Drag global ── */
body.mr-dragging .mr-content{opacity:.85}

/* ══ MOBILE TREE DRAWER ══ */
.mr-tree-ov{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
  z-index:7940;opacity:0;pointer-events:none;transition:opacity .25s;
}
.mr-tree-ov.show{opacity:1;pointer-events:auto}
.mr-tree-fab{
  display:none;position:fixed;bottom:88px;right:16px;z-index:7900;
  padding:10px 16px;border-radius:50px;border:none;
  background:var(--mr);color:#fff;font-size:12px;font-weight:700;font-family:inherit;
  box-shadow:0 4px 20px rgba(99,102,241,.4);cursor:pointer;
  align-items:center;gap:7px;transition:all .15s;
}
.mr-tree-fab:hover{filter:brightness(1.1);transform:translateY(-1px)}

/* ── Responsive ── */
@media(max-width:960px){
  .mr-tree{width:260px}
}
@media(max-width:640px){
  .mr-layout{gap:0}
  .mr-tree{
    position:fixed!important;right:-320px;top:0!important;
    height:100vh;max-height:100vh;
    width:300px!important;max-width:88vw;
    z-index:7950;border-radius:16px 0 0 16px;border-right:none;
    transition:right .28s cubic-bezier(.4,0,.2,1);
    box-shadow:-4px 0 32px rgba(0,0,0,.2);
  }
  .mr-tree.mobile-open{right:0!important}
  .mr-tree.collapsed{right:-320px!important;display:flex!important}
  .mr-tree-ov,.mr-tree-fab{display:flex}
  .mr-stats{display:flex;overflow-x:auto;gap:8px;padding-bottom:6px;margin-bottom:14px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
  .mr-stats::-webkit-scrollbar{display:none}
  .mr-stat{min-width:120px;flex-shrink:0;padding:10px 12px;gap:8px}
  .mr-stat-ic{width:32px;height:32px;font-size:13px;border-radius:8px}
  .mr-stat-v{font-size:16px}
  .mr-stat-l{font-size:9px}
  .mr-hero{flex-wrap:wrap;gap:14px}.mr-hero-meta{gap:16px}
  .mr-grid{grid-template-columns:1fr}
}

/* ── Room Admin panel ── */
.rm-admin-row{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:9px;background:#f8fafc;border:1px solid var(--border,#e0e0e0);margin-bottom:6px;transition:background .1s}
.rm-admin-row:hover{background:var(--mrbg)}
.rm-admin-info{flex:1;min-width:0}
.rm-admin-name{font-size:13px;font-weight:600;color:var(--c1,#333)}
.rm-admin-role{font-size:10px;color:var(--c3,#999);margin-top:1px}
.rm-admin-badge{font-size:9px;font-weight:700;padding:2px 7px;border-radius:5px;text-transform:uppercase;letter-spacing:.3px}
.rm-admin-badge.primary{background:#eef2ff;color:var(--mrd)}
.rm-admin-badge.co{background:#f1f5f9;color:#64748b}
.rm-admin-rm{width:26px;height:26px;border:none;border-radius:6px;background:none;color:#cbd5e1;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;transition:all .12s;flex-shrink:0}
.rm-admin-rm:hover{background:#fee2e2;color:#dc2626}
.rm-search-wrap{position:relative;margin-bottom:10px}
.rm-search-wrap input{width:100%;padding:9px 12px 9px 36px;border:1.5px solid var(--border,#e0e0e0);border-radius:8px;font-size:13px;font-family:inherit;background:#fff;transition:border .15s;box-sizing:border-box}
.rm-search-wrap input:focus{outline:none;border-color:var(--mr);box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.rm-search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--c3,#999);font-size:13px}
.rm-results{max-height:180px;overflow-y:auto;border:1px solid var(--border,#e0e0e0);border-radius:8px;background:#fff}
.rm-result-row{display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;transition:background .1s;font-size:12px}
.rm-result-row:hover{background:var(--mrbg)}
.rm-result-row+.rm-result-row{border-top:1px solid #f1f5f9}
.rm-empty-results{padding:12px;text-align:center;color:var(--c3,#999);font-size:12px}

/* ── Draggable cards/rows ── */
.mr-card[draggable="true"]{cursor:grab}.mr-card[draggable="true"]:active{cursor:grabbing}
.mr-card.dragging-src{opacity:.4;border-style:dashed}
[data-drag-row]{cursor:grab}[data-drag-row]:active{cursor:grabbing}

/* ─────────────── DETAIL DRAWER ─────────────── */
.mr-drawer-ov{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:8000;opacity:0;pointer-events:none;transition:opacity .25s}
.mr-drawer-ov.open{opacity:1;pointer-events:all}
.mr-drawer{position:fixed;top:0;right:-440px;width:420px;max-width:100vw;height:100vh;background:#fff;z-index:8001;box-shadow:-6px 0 32px rgba(0,0,0,.18);transition:right .28s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow:hidden}
.mr-drawer.open{right:0}
.mr-drawer-hdr{display:flex;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--border,#e0e0e0);background:#fff;flex-shrink:0}
.mr-drawer-hdr h3{font-size:14px;font-weight:700;margin:0;flex:1;color:var(--mrd);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mr-drawer-x{width:30px;height:30px;border:none;background:none;cursor:pointer;color:#94a3b8;font-size:20px;display:flex;align-items:center;justify-content:center;border-radius:6px;flex-shrink:0;line-height:1}
.mr-drawer-x:hover{background:#fee2e2;color:#ef4444}
.mr-drawer-body{flex:1;overflow-y:auto;scrollbar-width:thin;scrollbar-color:var(--mrbrd) transparent}
.mr-drawer-body::-webkit-scrollbar{width:4px}.mr-drawer-body::-webkit-scrollbar-thumb{background:var(--mrbrd);border-radius:2px}
.mr-drawer-hero{padding:18px;display:flex;gap:12px;align-items:flex-start;background:linear-gradient(135deg,var(--mrbg) 0%,#f5f3ff 100%);border-bottom:1px solid var(--mrbrd)}
.mr-drawer-sect{padding:13px 18px;border-bottom:1px solid #f1f5f9}
.mr-drawer-sect:last-child{border-bottom:none}
.mr-drawer-sl{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--c3,#999);margin-bottom:8px}
.mr-drawer-kv{display:flex;align-items:flex-start;gap:8px;padding:3px 0;font-size:12px}
.mr-drawer-k{color:var(--c3,#999);flex:0 0 108px;font-size:11px;padding-top:1px}
.mr-drawer-v{color:var(--c1,#333);flex:1;min-width:0;font-weight:600;word-break:break-word}
.mr-drawer-footer{padding:12px 16px;border-top:1px solid var(--border,#e0e0e0);display:flex;gap:7px;flex-wrap:wrap;flex-shrink:0;background:#fafbff}
.mr-drawer-act{flex:1;min-width:80px;padding:9px 8px;border-radius:9px;border:1.5px solid var(--border,#e0e0e0);background:#fff;color:var(--c2,#666);cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .12s}
.mr-drawer-act:hover{border-color:var(--mr);color:var(--mr);background:var(--mrbg)}
.mr-drawer-act.borrow:hover{border-color:#059669;color:#059669;background:#dcfce7}
/* make rows feel clickable */
.mr-t tbody tr[data-cid]{cursor:pointer}
.mr-grp-row[data-cid]{cursor:pointer}

/* ─────────────── TRANSACTION MODAL ─────────────── */
.mr-txn-tiles{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px}
.mr-txn-tile{padding:14px 12px;border-radius:12px;border:2px solid var(--border,#e0e0e0);background:#fff;cursor:pointer;text-align:left;transition:all .18s;font-family:inherit;width:100%}
.mr-txn-tile:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.1)}
.tile-ic{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:9px}
.tile-nm{font-size:13px;font-weight:700;color:var(--c1,#333);margin-bottom:3px}
.tile-ds{font-size:10px;color:var(--c3,#999);line-height:1.45}
.mr-txn-tile.t-withdraw:hover{border-color:#ef4444;background:#fff5f5}
.mr-txn-tile.t-withdraw .tile-ic{background:#fee2e2;color:#dc2626}
.mr-txn-tile.t-restock:hover{border-color:#16a34a;background:#f0fdf4}
.mr-txn-tile.t-restock .tile-ic{background:#dcfce7;color:#16a34a}
.mr-txn-tile.t-dispose{grid-column:1/-1}
.mr-txn-tile.t-dispose:hover{border-color:#d97706;background:#fffbeb}
.mr-txn-tile.t-dispose .tile-ic{background:#fef3c7;color:#d97706}
.mr-txn-tile.t-dispose .tile-nm{display:flex;align-items:center;gap:6px}
.mr-txn-tile.t-borrow:hover{border-color:var(--mr);background:var(--mrbg)}
.mr-txn-tile.t-borrow .tile-ic{background:var(--mrbg);color:var(--mr)}
.mr-txn-form{border-top:1px solid #f1f5f9;padding-top:14px}
.mr-txn-form-hdr{display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:13px;font-weight:700;color:var(--c1,#333)}
.mr-txn-warn{display:flex;align-items:flex-start;gap:8px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;font-size:12px;color:#92400e;margin-bottom:12px}
.mr-txn-qty-row{display:flex;gap:8px;margin-bottom:0}
.mr-txn-qty-row input:first-child{flex:1}
.mr-txn-qty-row input:last-child{width:72px}
.mr-owner-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;flex-shrink:0}
.mr-owner-badge.is-owner{background:#eef2ff;color:var(--mrd)}
.mr-owner-badge.is-borrower{background:#f1f5f9;color:#64748b}
/* txn button variants (table, card, drawer) */
.mr-t-act.txn{color:var(--mr)}.mr-t-act.txn:hover{border-color:var(--mr);color:#fff;background:var(--mr)}
.mr-act-btn.txn{color:var(--mr);font-weight:700;border-color:var(--mrbrd);background:var(--mrbg)}
.mr-act-btn.txn:hover{border-color:var(--mrd);background:var(--mr);color:#fff}
.mr-drawer-act.txn{color:var(--mr);border-color:var(--mrbrd);background:var(--mrbg);font-weight:700}
.mr-drawer-act.txn:hover{border-color:var(--mrd);background:var(--mr);color:#fff}
.mr-drawer-act.ar3d{color:#7c3aed;border-color:#ddd6fe;background:#f5f3ff;font-weight:700;text-decoration:none}
.mr-drawer-act.ar3d:hover{border-color:#7c3aed;background:#7c3aed;color:#fff}
/* ── Batch Txn Sheet (btx-md) ── */
.btx-ov{position:fixed;inset:0;z-index:9500;background:rgba(0,0,0,.55);display:flex;align-items:flex-end;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s}
.btx-ov.show{opacity:1;pointer-events:auto}
.btx-md{background:#fff;border-radius:22px 22px 0 0;width:100%;max-width:560px;max-height:88vh;display:flex;flex-direction:column;transform:translateY(100%);transition:transform .28s cubic-bezier(.34,1.1,.64,1);overflow:hidden}
.btx-ov.show .btx-md{transform:translateY(0)}
.btx-handle{display:flex;justify-content:center;padding:10px 0 4px;flex-shrink:0}
.btx-handle-bar{width:36px;height:4px;border-radius:2px;background:#e2e8f0}
.btx-hdr{padding:8px 20px 14px;flex-shrink:0;border-bottom:1px solid #f1f5f9}
.btx-hdr-top{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.btx-hdr-ic{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.btx-hdr-info{flex:1;min-width:0}
.btx-hdr-title{font-size:15px;font-weight:800;color:#0f172a;line-height:1.2}
.btx-hdr-sub{font-size:11px;color:#64748b;margin-top:2px}
.btx-hdr-close{width:28px;height:28px;border-radius:8px;border:none;background:#f1f5f9;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px}
.btx-hdr-close:hover{background:#fee2e2;color:#dc2626}
.btx-tabs{display:flex;gap:6px}
.btx-tab{flex:1;padding:8px;border-radius:10px;border:1.5px solid transparent;cursor:pointer;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s;font-family:inherit;background:#f8fafc;color:#64748b}
.btx-tab.act{border-color:var(--mr);color:var(--mr);background:var(--mrbg)}
.btx-body{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:14px}
.btx-items-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.btx-items-hdr h5{margin:0;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px}
.btx-items-scroll{max-height:200px;overflow-y:auto;display:flex;flex-direction:column;gap:5px;border:1.5px solid #f1f5f9;border-radius:12px;padding:7px}
.btx-item{display:flex;align-items:center;gap:10px;padding:7px 8px;background:#f8fafc;border-radius:9px;transition:.12s;border:1px solid transparent}
.btx-item.btx-ok{background:#f0fdf4;border-color:#bbf7d0}
.btx-item.btx-err{background:#fff5f5;border-color:#fca5a5}
.btx-item-ic{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;background:var(--mrbg);color:var(--mr);flex-shrink:0}
.btx-item-info{flex:1;min-width:0}
.btx-item-name{font-size:12px;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.btx-item-sub{font-size:10px;color:#94a3b8;margin-top:1px}
.btx-item-status{font-size:14px;flex-shrink:0;width:20px;text-align:center}
.btx-field{display:flex;flex-direction:column;gap:5px}
.btx-field label{font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px}
.btx-field input,.btx-field textarea,.btx-field select{border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13px;color:#0f172a;background:#fafafa;outline:none;transition:.15s;font-family:inherit;resize:vertical}
.btx-field input:focus,.btx-field textarea:focus{border-color:var(--mr);background:#fff}
.btx-field textarea{min-height:60px}
.btx-prog{background:#f1f5f9;border-radius:6px;height:6px;overflow:hidden;margin-top:4px}
.btx-prog-fill{height:100%;background:linear-gradient(90deg,var(--mr),#818cf8);border-radius:6px;transition:width .3s ease}
.btx-footer{padding:12px 20px;border-top:1px solid #f1f5f9;display:flex;gap:8px;flex-shrink:0;background:#fff}
.btx-btn-cancel{flex:0 0 auto;padding:10px 18px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-weight:700;background:#fff;color:#64748b;cursor:pointer;font-family:inherit}
.btx-btn-cancel:hover{background:#f8fafc}
.btx-btn-submit{flex:1;padding:10px 18px;border:none;border-radius:10px;font-size:13px;font-weight:700;color:#fff;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:7px;transition:.15s;background:var(--mr)}
.btx-btn-submit:hover{filter:brightness(1.08)}
.btx-btn-submit:disabled{opacity:.5;cursor:not-allowed;filter:none}
.btx-result-summary{padding:12px 14px;border-radius:12px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:8px}
.btx-result-ok{background:#f0fdf4;color:#065f46}
.btx-result-err{background:#fff5f5;color:#dc2626}
.btx-item-qty{display:flex;align-items:center;gap:4px;flex-shrink:0}
.btx-item-qty input{width:64px;text-align:center;border:1.5px solid #e2e8f0;border-radius:7px;padding:4px 6px;font-size:12px;font-weight:700;color:#0f172a;outline:none;background:#fff;font-family:inherit}
.btx-item-qty input:focus{border-color:var(--mr);background:#fefeff}
.btx-item-qty input.qty-warn{border-color:#dc2626!important;background:#fff5f5!important;color:#dc2626!important}
.btx-unit{font-size:10px;color:#94a3b8;font-weight:600;min-width:20px}
/* ── Batch Confirm Popup (btc) ── */
@keyframes mrBtcIn{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.mr-btc-ov{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.55);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .2s}
.mr-btc-ov.show{opacity:1;pointer-events:auto}
.mr-btc-box{background:#fff;border-radius:22px;width:100%;max-width:440px;max-height:86vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.18),0 0 0 1px rgba(0,0,0,.06);animation:mrBtcIn .25s cubic-bezier(.34,1.1,.64,1)}
.mr-btc-hdr{padding:20px 20px 16px;text-align:center;flex-shrink:0;border-bottom:1px solid #f1f5f9}
.mr-btc-hdr-ic{width:52px;height:52px;border-radius:15px;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 10px;box-shadow:0 8px 20px rgba(0,0,0,.15)}
.mr-btc-hdr h3{margin:0;font-size:16px;font-weight:800;color:#0f172a}
.mr-btc-hdr p{margin:4px 0 0;font-size:12px;color:#64748b}
.mr-btc-body{flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:12px}
.mr-btc-sec{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.mr-btc-purpose{padding:8px 11px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;color:#374151;line-height:1.5}
.mr-btc-items{display:flex;flex-direction:column;gap:5px;max-height:220px;overflow-y:auto}
.mr-btc-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:10px;border:1px solid transparent}
.mr-btc-item-ic{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.mr-btc-item-name{flex:1;min-width:0;font-size:12px;font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mr-btc-item-sub{font-size:10px;color:#64748b;font-weight:400;margin-top:1px}
.mr-btc-item-qty{font-size:12px;font-weight:700;white-space:nowrap;flex-shrink:0}
.mr-btc-footer{padding:12px 18px 18px;flex-shrink:0;display:flex;flex-direction:column;gap:8px;border-top:1px solid #f1f5f9}
.mr-btc-btn-confirm{width:100%;padding:13px;border:none;border-radius:12px;font-size:14px;font-weight:800;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.15s;font-family:inherit}
.mr-btc-btn-confirm:hover{filter:brightness(1.08)}
.mr-btc-btn-edit{width:100%;padding:11px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:13px;font-weight:700;color:#64748b;background:#fff;cursor:pointer;font-family:inherit;transition:.15s}
.mr-btc-btn-edit:hover{background:#f8fafc;border-color:#cbd5e1}
/* ── Danger Confirm Popup ── */
@keyframes mrDelIn{from{opacity:0;transform:scale(.92) translateY(12px)}to{opacity:1;transform:scale(1) translateY(0)}}
.mr-del-ov{position:fixed;inset:0;z-index:11000;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;pointer-events:none;transition:opacity .2s}
.mr-del-ov.show{opacity:1;pointer-events:auto}
.mr-del-box{background:#fff;border-radius:24px;width:100%;max-width:380px;box-shadow:0 28px 80px rgba(0,0,0,.22),0 0 0 1px rgba(0,0,0,.05);animation:mrDelIn .22s cubic-bezier(.34,1.15,.64,1);overflow:hidden}
.mr-del-top{padding:28px 24px 20px;text-align:center}
.mr-del-ic-wrap{width:68px;height:68px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;position:relative}
.mr-del-ic-wrap::before{content:'';position:absolute;inset:-6px;border-radius:50%;opacity:.15}
.mr-del-ic-wrap.danger{background:linear-gradient(135deg,#dc2626,#ef4444);box-shadow:0 8px 24px rgba(220,38,38,.35)}
.mr-del-ic-wrap.danger::before{background:#dc2626}
.mr-del-ic{font-size:24px;color:#fff}
.mr-del-title{font-size:18px;font-weight:800;color:#0f172a;margin-bottom:6px}
.mr-del-sub{font-size:13px;color:#64748b;line-height:1.55}
.mr-del-target{display:inline-flex;align-items:center;gap:6px;margin:12px auto 0;padding:7px 14px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-weight:700;color:#0f172a}
.mr-del-target i{color:#94a3b8;font-size:12px}
.mr-del-chips{display:flex;justify-content:center;flex-wrap:wrap;gap:6px;padding:0 24px 16px}
.mr-del-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700}
.mr-del-chip.warn{background:#fee2e2;color:#991b1b}
.mr-del-chip.info{background:#f1f5f9;color:#475569}
.mr-del-warn{margin:0 24px 16px;padding:10px 14px;background:#fff7ed;border:1.5px solid #fed7aa;border-radius:12px;font-size:12px;color:#9a3412;display:flex;align-items:flex-start;gap:8px;line-height:1.5}
.mr-del-warn>i{margin-top:2px;flex-shrink:0;color:#f97316;font-size:14px}
.mr-del-dest{display:inline-flex;align-items:center;gap:5px;margin-top:6px;padding:4px 10px;background:#ffedd5;border:1px solid #fed7aa;border-radius:8px;font-size:11px;font-weight:700;color:#c2410c}
.mr-del-cont-badge{background:#dc2626;color:#fff;font-size:10px;font-weight:800;padding:1px 6px;border-radius:20px;margin-left:4px}
.mr-del-footer{display:flex;flex-direction:column;gap:8px;padding:0 20px 20px}
.mr-del-btn-del{width:100%;padding:13px;border:none;border-radius:13px;font-size:14px;font-weight:800;color:#fff;background:linear-gradient(135deg,#dc2626,#ef4444);cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,.3);transition:.15s}
.mr-del-btn-del:hover{filter:brightness(1.07);box-shadow:0 6px 20px rgba(220,38,38,.4)}
.mr-del-btn-del:active{transform:scale(.98)}
.mr-del-btn-cancel{width:100%;padding:11px;border:1.5px solid #e2e8f0;border-radius:13px;font-size:13px;font-weight:700;color:#64748b;background:#fff;cursor:pointer;font-family:inherit;transition:.15s}
.mr-del-btn-cancel:hover{background:#f8fafc;border-color:#cbd5e1}

/* ── Report Scope Picker ── */
.rpt-scope-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.rpt-scope-tile{border:1.5px solid var(--border,#e0e0e0);background:#fff;border-radius:12px;padding:12px 10px 10px;cursor:pointer;text-align:center;transition:all .15s;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:4px;position:relative;min-width:0}
.rpt-scope-tile:hover{border-color:var(--mr);background:var(--mrbg)}
.rpt-scope-tile.rpt-active{border-width:2px}
.rpt-scope-ic{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:3px;flex-shrink:0}
.rpt-scope-lbl{font-size:12px;font-weight:700;color:var(--c1,#333);white-space:nowrap}
.rpt-scope-sub{font-size:9.5px;color:var(--c3,#999);line-height:1.35;max-width:82px}
.rpt-scope-chk{position:absolute;top:6px;right:7px;font-size:11px}
.rpt-preview-box{background:var(--mrbg);border:1px solid var(--mrbrd);border-radius:10px;padding:10px 14px;font-size:12px;color:var(--mrd)}
.rpt-preview-cnt{display:flex;align-items:center;gap:8px;font-weight:700;margin-bottom:4px}
.rpt-preview-path{font-size:10px;color:var(--c2,#666);display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.rpt-fields-row{display:flex;gap:10px;flex-wrap:wrap}
.rpt-field-lbl{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:var(--c2,#666);cursor:pointer;padding:8px 14px;border:1.5px solid var(--border,#e0e0e0);border-radius:50px;background:#fff;transition:all .12s;user-select:none;line-height:1}
.rpt-field-lbl:hover{border-color:var(--mr);color:var(--mr)}
.rpt-field-lbl input[type="checkbox"]{accent-color:var(--mr);width:14px;height:14px;cursor:pointer;flex-shrink:0}
.rpt-field-lbl:has(input:checked){border-color:var(--mr);background:var(--mrbg);color:var(--mrd)}
@media(max-width:540px){.rpt-scope-grid{grid-template-columns:repeat(2,1fr)}}

/* Modal */
.sr-overlay{position:fixed;inset:0;z-index:3000;background:rgba(2,10,30,.6);backdrop-filter:blur(4px);display:flex;align-items:flex-start;justify-content:center;padding:24px;overflow-y:auto;opacity:0;visibility:hidden;transition:opacity .28s,visibility .28s}
.sr-overlay.show{opacity:1;visibility:visible}
.sr-modal{background:#fff;border-radius:22px;width:100%;max-width:1020px;box-shadow:0 40px 120px rgba(0,0,0,.28),0 0 0 1px rgba(0,0,0,.04);margin:auto;overflow:hidden;transform:translateY(28px) scale(.97);transition:transform .35s cubic-bezier(.34,1.15,.64,1)}
.sr-overlay.show .sr-modal{transform:translateY(0) scale(1)}
.sr-hdr{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 55%,#1e40af 100%);color:#fff;padding:22px 26px;display:flex;align-items:flex-start;gap:16px;position:relative}
.sr-hdr-ic{width:50px;height:50px;border-radius:15px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:inset 0 1px 1px rgba(255,255,255,.2)}
.sr-hdr-info{flex:1;min-width:0}
.sr-hdr-title{font-size:17px;font-weight:800;line-height:1.2;margin-bottom:3px}
.sr-hdr-sub{font-size:11px;opacity:.75;display:flex;gap:10px;flex-wrap:wrap}
.sr-hdr-sub span{display:flex;align-items:center;gap:4px}
.sr-hdr-right{display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0}
.sr-hdr-actions{display:flex;gap:8px;align-items:center}
.sr-hdr-btn{padding:7px 14px;border-radius:9px;border:1.5px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#fff;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px;transition:all .15s}
.sr-hdr-btn:hover{background:rgba(255,255,255,.22);border-color:rgba(255,255,255,.45);transform:translateY(-1px)}
.sr-close{width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.1);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s;flex-shrink:0}
.sr-close:hover{background:rgba(255,255,255,.25);transform:scale(1.1)}
.sr-tabs{display:flex;border-bottom:2px solid #f1f5f9;background:#fafbfc;padding:0 26px}
.sr-tab{padding:13px 18px;border:none;background:none;font-size:13px;font-weight:600;color:#94a3b8;cursor:pointer;font-family:inherit;border-bottom:2.5px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:7px;transition:all .15s;white-space:nowrap}
.sr-tab:hover{color:#475569}
.sr-tab.active{color:#1e40af;border-bottom-color:#1e40af}
.sr-tab-badge{font-size:10px;padding:1px 7px;border-radius:8px;background:#e0e7ff;color:#3730a3;font-weight:700;transition:all .15s}
.sr-tab.active .sr-tab-badge{background:#1e40af;color:#fff}
.sr-body{padding:20px 26px 30px}
.sr-loading{text-align:center;padding:56px;color:#94a3b8}
.sr-loading i{animation:fa-spin 1s linear infinite}
.sr-panel{display:none}.sr-panel.active{display:block}
/* Table */
.sr-tbl-wrap{overflow-x:auto;border-radius:14px;border:1.5px solid #e8ecf5;margin-bottom:24px;box-shadow:0 2px 12px rgba(0,0,0,.04)}
.sr-tbl{width:100%;border-collapse:collapse;font-size:12.5px}
.sr-tbl thead{position:sticky;top:0;z-index:1}
.sr-tbl th{padding:10px 14px;background:linear-gradient(0deg,#f1f5fb,#f8fafc);text-align:center;font-size:10.5px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;border-bottom:2px solid #e2e8f0}
.sr-tbl th:first-child{text-align:left}
.sr-tbl td{padding:9px 14px;border-bottom:1px solid #f0f4f8;color:#334155;vertical-align:middle}
.sr-tbl td:not(:first-child){text-align:right;font-variant-numeric:tabular-nums;font-family:'Courier New',monospace;font-size:12px;color:#475569}
.sr-tbl tbody tr{transition:background .1s}
.sr-tbl tbody tr:hover{background:#f4f8ff}
.sr-tbl tbody tr.has-data{background:#fafbff}
.sr-tbl tbody tr.has-data:hover{background:#eef3ff}
.sr-tbl td:not(:first-child).nonzero{color:#1e40af;font-weight:700}
.sr-tbl tfoot tr{background:linear-gradient(0deg,#dde6ff,#eef2ff)}
.sr-tbl tfoot td{padding:11px 14px;font-weight:800;font-size:12px;color:#1e40af;border-top:2px solid #c7d2fe}
.sr-tbl tfoot td:not(:first-child){text-align:right;font-family:'Courier New',monospace}
.sr-tbl-section{font-size:13px;font-weight:800;color:#1e3a5f;padding:14px 0 10px;display:flex;align-items:center;gap:8px}
.sr-tbl-section i{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px}
/* Chart layout */
.sr-chart-row{display:flex;gap:18px;align-items:flex-start;margin-top:4px}
.sr-chart-box{flex:0 0 auto;width:100%;max-width:520px;min-width:0}
.sr-chart-legend{flex:1;min-width:160px;padding-top:6px}
.sr-chart-title{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #f1f5f9}
.sr-legend-list{display:flex;flex-direction:column;gap:1px}
.sr-legend-row{display:flex;align-items:center;gap:7px;padding:6px 4px;border-radius:7px;font-size:11.5px;color:#475569;transition:background .12s;cursor:default}
.sr-legend-row:hover{background:#f4f8ff}
.sr-legend-dot{width:11px;height:11px;border-radius:4px;flex-shrink:0}
.sr-legend-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:#334155}
.sr-legend-bar{height:4px;background:#f1f5f9;border-radius:4px;width:60px;flex-shrink:0;overflow:hidden}
.sr-legend-bar-fill{height:100%;border-radius:4px;min-width:2px}
.sr-legend-pct{font-size:11px;font-weight:700;color:#1e40af;white-space:nowrap;min-width:34px;text-align:right}
.sr-legend-kg{font-size:10px;color:#94a3b8;white-space:nowrap;min-width:54px;text-align:right}
.sr-empty{text-align:center;padding:36px;color:#94a3b8;font-size:13px}
.sr-empty i{font-size:40px;opacity:.2;display:block;margin-bottom:12px}
.sr-note{font-size:10.5px;color:#94a3b8;margin-top:8px;line-height:1.6;padding:8px 12px;background:#fafbfc;border-radius:8px;border:1px solid #f1f5f9}
/* Animations */
@keyframes sr-modal-in{from{opacity:0;transform:translateY(28px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes sr-slice-pop{0%{opacity:0;transform:scale(.5)}65%{transform:scale(1.04)}100%{opacity:1;transform:scale(1)}}
@keyframes sr-row-slide{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:translateX(0)}}
@keyframes sr-bar-grow{from{width:0!important}}
@keyframes sr-tbl-row-in{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
@keyframes sr-fade-in{from{opacity:0}to{opacity:1}}
.sr-anim-slice{animation:sr-slice-pop .55s cubic-bezier(.34,1.4,.64,1) both}
.sr-anim-row{animation:sr-row-slide .32s ease both}
.sr-anim-bar{animation:sr-bar-grow .75s cubic-bezier(.4,0,.2,1) both}
.sr-anim-trow{animation:sr-tbl-row-in .28s ease both}
.sr-slice-path{transition:filter .15s,opacity .15s}
.sr-slice-path:hover{filter:brightness(1.1) drop-shadow(0 2px 6px rgba(0,0,0,.2));cursor:pointer}
/* Chem Popup (slice click) */
@keyframes sr-popup-in{from{opacity:0;transform:translateY(18px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.sr-chem-popup{position:fixed;bottom:28px;right:28px;z-index:4000;width:320px;background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.22),0 0 0 1px rgba(0,0,0,.06);overflow:hidden;display:none}
.sr-chem-popup.show{display:block;animation:sr-popup-in .3s cubic-bezier(.34,1.1,.64,1) both}
.sr-cp-hdr{padding:13px 16px;display:flex;align-items:center;gap:9px}
.sr-cp-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}
.sr-cp-title{flex:1;font-size:12.5px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#fff}
.sr-cp-close{width:24px;height:24px;border-radius:7px;background:rgba(255,255,255,.18);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;transition:background .12s}
.sr-cp-close:hover{background:rgba(255,255,255,.35)}
.sr-cp-stats{padding:7px 16px;background:#f8fafc;border-bottom:1px solid #f1f5f9;display:flex;gap:18px;font-size:10.5px;color:#64748b}
.sr-cp-stat{display:flex;align-items:center;gap:5px;font-weight:600}
.sr-cp-list{max-height:268px;overflow-y:auto}
.sr-cp-item{padding:8px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #f8fafc;animation:sr-tbl-row-in .22s ease both}
.sr-cp-item:last-child{border-bottom:none}
.sr-cp-item:hover{background:#f4f8ff}
.sr-cp-ic{width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.sr-cp-name{flex:1;min-width:0}
.sr-cp-chem-name{font-size:12px;font-weight:700;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sr-cp-cas{font-size:10px;color:#94a3b8;margin-top:1px}
.sr-cp-right{display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0}
.sr-cp-qty{font-size:11px;font-weight:700;color:#1e40af;white-space:nowrap}
.sr-cp-hcodes{display:flex;gap:2px;flex-wrap:wrap;justify-content:flex-end;max-width:90px}
.sr-cp-hpill{font-size:9px;padding:1px 5px;border-radius:4px;background:#e0e7ff;color:#3730a3;font-weight:700;white-space:nowrap}
.sr-cp-footer{padding:10px 16px;border-top:1px solid #f1f5f9}
.sr-cp-more-btn{width:100%;padding:7px 14px;border-radius:9px;border:1.5px solid #e2e8f0;background:#f8fafc;color:#475569;font-size:11.5px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .15s}
.sr-cp-more-btn:hover{border-color:#1e40af;color:#1e40af;background:#eff6ff}
.sr-cp-loading{padding:24px;text-align:center;color:#94a3b8;font-size:12px}
@media(max-width:700px){.sr-chart-row{flex-direction:column}.sr-chart-box{max-width:100%;width:100%}.sr-legend-bar{display:none}.sr-chem-popup{bottom:16px;right:16px;left:16px;width:auto}}
</style>
<?php Layout::sidebar('myroom'); Layout::beginContent(); ?>

<!-- Hero -->
<div class="mr-hero">
  <div class="mr-hero-ic"><i class="fas fa-door-open"></i></div>
  <div class="mr-hero-info">
    <h2><?= $TH ? 'ห้องของฉัน' : 'My Rooms' ?></h2>
    <p><?= $TH ? 'จัดการสารเคมีในห้องที่คุณรับผิดชอบ' : 'Manage chemicals in rooms you are responsible for' ?></p>
  </div>
  <div class="mr-hero-meta" id="heroMeta"></div>
</div>

<!-- Room Tabs -->
<div class="mr-room-tabs" id="roomTabs"></div>

<!-- Room Panel -->
<div id="roomPanel" style="display:none">
  <div class="mr-stats" id="roomStats"></div>

  <!-- Toolbar -->
  <div class="mr-toolbar">
    <div class="stk-search">
      <i class="fas fa-search"></i>
      <input id="mrSearch" type="text" placeholder="<?= $TH ? 'ค้นหาสาร, รหัสขวด, CAS, สูตร...' : 'Search chemical, code, CAS, formula...' ?>">
    </div>
    <div style="display:flex;gap:6px;margin-left:auto;align-items:center;flex-shrink:0">
      <div class="stk-vw" id="viewSwitcher">
        <button onclick="setView('table')" title="<?= $TH ? 'ตาราง' : 'Table' ?>"><i class="fas fa-list"></i></button>
        <button class="active" onclick="setView('card')" title="<?= $TH ? 'การ์ด' : 'Cards' ?>"><i class="fas fa-th-large"></i></button>
        <button onclick="setView('grouped')" title="<?= $TH ? 'จัดกลุ่มตามสาร' : 'Grouped by Chemical' ?>"><i class="fas fa-layer-group"></i></button>
      </div>
      <button class="stk-btn stk-btn-g" id="btnTreeToggle" onclick="toggleTree()">
        <i class="fas fa-sitemap"></i> <span id="treeToggleLbl"><?= $TH ? 'ซ่อนต้นไม้' : 'Hide Tree' ?></span>
      </button>
      <button class="stk-btn stk-btn-g" onclick="openMrRpt()" title="<?= $TH ? 'รายงานตำแหน่งสารเคมี' : 'Location Report' ?>">
        <i class="fas fa-clipboard-list"></i> <?= $TH ? 'รายงาน' : 'Report' ?>
      </button>
      <button class="stk-btn stk-btn-g" id="btnSafetyRpt" onclick="openSafetyReport()" title="<?= $TH ? 'รายงานความปลอดภัยสารเคมี' : 'Chemical Safety Report' ?>">
        <i class="fas fa-shield-alt"></i> <?= $TH ? 'ความปลอดภัย' : 'Safety' ?>
      </button>
      <button class="stk-btn stk-btn-g" onclick="openManageAdmins()" title="<?= $TH ? 'จัดการผู้ดูแลห้อง' : 'Manage Room Admins' ?>">
        <i class="fas fa-users-cog"></i>
      </button>
    </div>
  </div>

  <!-- Content Layout -->
  <div class="mr-layout">
    <div class="mr-tree" id="storageTree"></div>
    <div class="mr-content">
      <div id="mrList"></div>
    </div>
  </div>
</div>

<!-- ══════════════════ Safety Report Modal ══════════════════ -->
<div class="sr-overlay" id="srOverlay" onclick="if(event.target===this)closeSafetyReport()">
  <div class="sr-modal">
    <div class="sr-hdr">
      <div class="sr-hdr-ic"><i class="fas fa-shield-alt"></i></div>
      <div class="sr-hdr-info">
        <div class="sr-hdr-title" id="srTitle"><?= $TH?'รายงานความปลอดภัยสารเคมี':'Chemical Safety Report' ?></div>
        <div class="sr-hdr-sub" id="srSub"></div>
      </div>
      <div class="sr-hdr-right">
        <button class="sr-close" onclick="closeSafetyReport()"><i class="fas fa-times"></i></button>
        <div class="sr-hdr-actions">
          <button class="sr-hdr-btn" onclick="srPrint()"><i class="fas fa-print"></i> <?= $TH?'พิมพ์':'Print' ?></button>
        </div>
      </div>
    </div>
    <div class="sr-tabs">
      <button class="sr-tab active" id="srTab-health" onclick="srSwitchTab('health')">
        <i class="fas fa-heartbeat" style="color:#dc2626"></i> <?= $TH?'ความอันตรายต่อสุขภาพ':'Health Hazard' ?>
        <span class="sr-tab-badge" id="srBadge-health">0</span>
      </button>
      <button class="sr-tab" id="srTab-physical" onclick="srSwitchTab('physical')">
        <i class="fas fa-bolt" style="color:#d97706"></i> <?= $TH?'ความอันตรายทางกายภาพ':'Physical Hazard' ?>
        <span class="sr-tab-badge" id="srBadge-physical">0</span>
      </button>
    </div>
    <div class="sr-body" id="srBody">
      <div class="sr-loading"><i class="fas fa-spinner fa-spin" style="font-size:24px;margin-bottom:10px;display:block"></i><?= $TH?'กำลังโหลด...':'Loading...' ?></div>
    </div>
  </div>
  <!-- Chem Popup (slice click) -->
  <div class="sr-chem-popup" id="srChemPopup" onclick="event.stopPropagation()">
    <div class="sr-cp-hdr" id="srCpHdr">
      <div class="sr-cp-dot" id="srCpDot"></div>
      <div class="sr-cp-title" id="srCpTitle"></div>
      <button class="sr-cp-close" onclick="srCloseChemPopup()"><i class="fas fa-times"></i></button>
    </div>
    <div class="sr-cp-stats" id="srCpStats"></div>
    <div class="sr-cp-list" id="srCpList"></div>
    <div class="sr-cp-footer" id="srCpFooter" style="display:none">
      <button class="sr-cp-more-btn" id="srCpMoreBtn"><i class="fas fa-list"></i> <span id="srCpMoreLbl"></span></button>
    </div>
  </div>
</div>

<!-- Mobile tree overlay + FAB (outside roomPanel) -->
<div id="mrTreeOv" class="mr-tree-ov" onclick="closeMobileTree()"></div>
<button id="treeFab" class="mr-tree-fab" onclick="openMobileTree()">
  <i class="fas fa-sitemap"></i> <span><?= $TH ? 'ผังห้อง' : 'Storage Map' ?></span>
</button>

<!-- No Rooms -->
<div id="noRooms" style="display:none">
  <div class="mr-empty">
    <i class="fas fa-door-closed"></i>
    <p style="font-size:16px;font-weight:700;margin-bottom:8px"><?= $TH ? 'ไม่มีห้องที่รับผิดชอบ' : 'No rooms assigned' ?></p>
    <p style="font-size:13px"><?= $TH ? 'กรุณาติดต่อผู้ดูแลระบบเพื่อกำหนดห้องให้คุณ' : 'Please contact an administrator to assign rooms to your account.' ?></p>
  </div>
</div>

<!-- Container Detail Drawer -->
<div class="mr-drawer-ov" id="mrDrawerOv" onclick="closeDrawer()"></div>
<div class="mr-drawer" id="mrDrawer" role="dialog" aria-modal="true">
  <div class="mr-drawer-hdr">
    <div id="drawerTypeIc" style="width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0"></div>
    <h3 id="drawerTitle">Detail</h3>
    <button class="mr-drawer-x" onclick="closeDrawer()" title="Close">&times;</button>
  </div>
  <div class="mr-drawer-body" id="drawerBody"></div>
  <div class="mr-drawer-footer" id="drawerFooter"></div>
</div>

<!-- Batch Bar -->
<div class="stk-batch" id="batchBar" style="display:none">
  <div class="bb-count">
    <div class="bb-num" id="bbCount">0</div>
    <div class="bb-lbl"><?= $TH ? 'รายการ' : 'selected' ?></div>
  </div>
  <div class="bb-grp">
    <button class="bab bab-move" onclick="openBatchPlace()">
      <i class="fas fa-map-pin"></i> <span class="bab-lbl"><?= $TH ? 'ย้ายตำแหน่ง' : 'Assign Location' ?></span>
    </button>
  </div>
  <div class="bb-grp">
    <button class="bab bab-txn" onclick="openBatchTxn()">
      <i class="fas fa-exchange-alt"></i> <span class="bab-lbl"><?= $TH ? 'ธุรกรรม' : 'Transaction' ?></span>
    </button>
  </div>
  <div class="bb-grp">
    <button class="bab bab-rpt" onclick="openBatchRpt()">
      <i class="fas fa-file-alt"></i> <span class="bab-lbl"><?= $TH ? 'รายงาน' : 'Report' ?></span>
    </button>
  </div>
  <div class="bb-grp">
    <button class="bab bab-cancel" onclick="clearSelection()" title="<?= $TH ? 'ยกเลิก' : 'Cancel' ?>"><i class="fas fa-times"></i></button>
  </div>
</div>

<!-- Toast -->
<div id="mrToast" class="mr-toast-c"></div>

<?php if ($canEdit): ?>
<!-- Modal: Add Cabinet -->
<div class="mr-modal-ov" id="modalAddCabinet">
  <div class="mr-modal">
    <div class="mr-modal-hdr"><h3><i class="fas fa-archive"></i> <?= $TH ? 'เพิ่มตู้จัดเก็บ' : 'Add Cabinet' ?></h3><button class="mr-modal-x" onclick="closeModal('modalAddCabinet')">&times;</button></div>
    <div class="mr-modal-body">
      <input type="hidden" id="addCabRoomId">
      <div class="mr-fg"><label><?= $TH ? 'ชื่อตู้ *' : 'Cabinet Name *' ?></label><input type="text" id="addCabName" placeholder="<?= $TH ? 'เช่น ตู้เก็บสารไวไฟ A' : 'e.g. Flammable Cabinet A' ?>"></div>
      <div class="mr-fg"><label>Code</label><input type="text" id="addCabCode" placeholder="e.g. CAB-A1"></div>
      <div class="mr-fg"><label><?= $TH ? 'ประเภทตู้' : 'Type' ?></label>
        <select id="addCabType">
          <option value="storage"><?= $TH ? 'ตู้เก็บทั่วไป' : 'General Storage' ?></option>
          <option value="fume_hood"><?= $TH ? 'ตู้ดูดควัน' : 'Fume Hood' ?></option>
          <option value="refrigerator"><?= $TH ? 'ตู้เย็น' : 'Refrigerator' ?></option>
          <option value="freezer"><?= $TH ? 'ช่องแช่แข็ง' : 'Freezer' ?></option>
          <option value="safety_cabinet"><?= $TH ? 'ตู้นิรภัย' : 'Safety Cabinet' ?></option>
          <option value="other"><?= $TH ? 'อื่นๆ' : 'Other' ?></option>
        </select>
      </div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalAddCabinet')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="btnAddCab" onclick="submitAddCabinet()"><i class="fas fa-plus"></i> <?= $TH ? 'เพิ่มตู้' : 'Add' ?></button>
    </div>
  </div>
</div>

<!-- Modal: Add Shelf -->
<div class="mr-modal-ov" id="modalAddShelf">
  <div class="mr-modal">
    <div class="mr-modal-hdr"><h3><i class="fas fa-layer-group"></i> <?= $TH ? 'เพิ่มชั้นวาง' : 'Add Shelf' ?></h3><button class="mr-modal-x" onclick="closeModal('modalAddShelf')">&times;</button></div>
    <div class="mr-modal-body">
      <input type="hidden" id="addShelfCabId">
      <div class="mr-fg"><label><?= $TH ? 'ชื่อชั้น *' : 'Shelf Name *' ?></label><input type="text" id="addShelfName" placeholder="<?= $TH ? 'เช่น ชั้นที่ 1' : 'e.g. Shelf 1' ?>"></div>
      <div class="mr-fg"><label><?= $TH ? 'ระดับชั้น' : 'Level' ?></label><input type="number" id="addShelfLevel" min="0" placeholder="1"></div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalAddShelf')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="btnAddShelf" onclick="submitAddShelf()"><i class="fas fa-plus"></i> <?= $TH ? 'เพิ่มชั้น' : 'Add' ?></button>
    </div>
  </div>
</div>

<!-- Modal: Add Slot -->
<div class="mr-modal-ov" id="modalAddSlot">
  <div class="mr-modal">
    <div class="mr-modal-hdr"><h3><i class="fas fa-th"></i> <?= $TH ? 'เพิ่มช่องวาง' : 'Add Slot' ?></h3><button class="mr-modal-x" onclick="closeModal('modalAddSlot')">&times;</button></div>
    <div class="mr-modal-body">
      <input type="hidden" id="addSlotShelfId">
      <div class="mr-fg"><label><?= $TH ? 'ชื่อช่อง *' : 'Slot Name *' ?></label><input type="text" id="addSlotName" placeholder="e.g. A1"></div>
      <div class="mr-fg"><label><?= $TH ? 'ตำแหน่ง' : 'Position' ?></label><input type="number" id="addSlotPos" min="0" placeholder="1"></div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalAddSlot')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="btnAddSlot" onclick="submitAddSlot()"><i class="fas fa-plus"></i> <?= $TH ? 'เพิ่มช่อง' : 'Add' ?></button>
    </div>
  </div>
</div>

<!-- Modal: Note/Nickname -->
<div class="mr-modal-ov" id="modalNote">
  <div class="mr-modal">
    <div class="mr-modal-hdr"><h3><i class="fas fa-sticky-note"></i> <?= $TH ? 'Nickname & Note' : 'Nickname & Note' ?></h3><button class="mr-modal-x" onclick="closeModal('modalNote')">&times;</button></div>
    <div class="mr-modal-body">
      <div id="noteContInfo"></div>
      <input type="hidden" id="noteContId">
      <div class="mr-fg"><label><?= $TH ? 'ชื่อเล่น' : 'Nickname' ?></label><input type="text" id="noteNickname" maxlength="100" placeholder="<?= $TH ? 'เช่น สารล้างมือ' : 'e.g. Sample 5' ?>"></div>
      <div class="mr-fg"><label><?= $TH ? 'บันทึกประจำห้อง' : 'Room Note' ?></label><textarea id="noteText" placeholder="<?= $TH ? 'บันทึกเพิ่มเติม...' : 'Additional notes...' ?>"></textarea></div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalNote')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="btnNote" onclick="submitNote()"><i class="fas fa-save"></i> <?= $TH ? 'บันทึก' : 'Save' ?></button>
    </div>
  </div>
</div>

<!-- Modal: Place Container -->
<div class="mr-modal-ov" id="modalPlace">
  <div class="mr-modal">
    <div class="mr-modal-hdr"><h3><i class="fas fa-map-pin"></i> <?= $TH ? 'กำหนดตำแหน่งจัดเก็บ' : 'Assign Storage Location' ?></h3><button class="mr-modal-x" onclick="closeModal('modalPlace')">&times;</button></div>
    <div class="mr-modal-body">
      <div id="placeContInfo"></div>
      <input type="hidden" id="placeContId">
      <div class="mr-fg"><label><?= $TH ? 'ตู้จัดเก็บ' : 'Cabinet' ?></label><select id="placeCabinet"><option value=""><?= $TH ? '— ไม่จัดเก็บ (Unplaced) —' : '— Unplaced —' ?></option></select></div>
      <div class="mr-fg"><label><?= $TH ? 'ชั้นวาง' : 'Shelf' ?></label><select id="placeShelf"><option value=""><?= $TH ? '— เลือกชั้น —' : '— Select shelf —' ?></option></select></div>
      <div class="mr-fg"><label><?= $TH ? 'ช่องวาง' : 'Slot' ?></label><select id="placeSlot"><option value=""><?= $TH ? '— เลือกช่อง —' : '— Select slot —' ?></option></select></div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalPlace')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="btnPlace" onclick="submitPlace()"><i class="fas fa-map-marker-alt"></i> <?= $TH ? 'บันทึกตำแหน่ง' : 'Save Location' ?></button>
    </div>
  </div>
</div>

<!-- Modal: Batch Place -->
<div class="mr-modal-ov" id="modalBatchPlace">
  <div class="mr-modal wide">
    <div class="mr-modal-hdr"><h3><i class="fas fa-map-pin"></i> <?= $TH ? 'ย้ายตำแหน่งพร้อมกัน' : 'Batch Assign Location' ?></h3><button class="mr-modal-x" onclick="closeModal('modalBatchPlace')">&times;</button></div>
    <div class="mr-modal-body">
      <div id="batchPlaceList" style="max-height:180px;overflow-y:auto"></div>
      <div style="background:#eef2ff;border:1px solid var(--mrbrd);border-radius:10px;padding:14px;margin-top:4px">
        <div style="font-size:10px;font-weight:700;color:var(--mrd);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px"><?= $TH ? 'กำหนดตำแหน่งใหม่' : 'New Location' ?></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="mr-fg"><label><?= $TH ? 'ตู้' : 'Cabinet' ?></label><select id="bpCabinet"><option value=""><?= $TH ? '— ไม่จัดเก็บ —' : '— Unplaced —' ?></option></select></div>
          <div class="mr-fg"><label><?= $TH ? 'ชั้น' : 'Shelf' ?></label><select id="bpShelf"><option value=""><?= $TH ? '— เลือกชั้น —' : '— Select —' ?></option></select></div>
          <div class="mr-fg"><label><?= $TH ? 'ช่อง' : 'Slot' ?></label><select id="bpSlot"><option value=""><?= $TH ? '— เลือกช่อง —' : '— Select —' ?></option></select></div>
        </div>
      </div>
      <div id="bpProgress" style="display:none;font-size:12px;color:var(--c3,#999);text-align:center;padding:8px"><i class="fas fa-circle-notch fa-spin"></i> <?= $TH ? 'กำลังบันทึก...' : 'Saving...' ?></div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalBatchPlace')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="btnBatchPlace" onclick="submitBatchPlace()"><i class="fas fa-map-marker-alt"></i> <?= $TH ? 'บันทึกทั้งหมด' : 'Save All' ?></button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal: Borrow -->
<div class="mr-modal-ov" id="modalBorrow">
  <div class="mr-modal">
    <div class="mr-modal-hdr"><h3><i class="fas fa-hand-holding"></i> <?= $TH ? 'ขอยืมสาร' : 'Borrow Request' ?></h3><button class="mr-modal-x" onclick="closeModal('modalBorrow')">&times;</button></div>
    <div class="mr-modal-body">
      <div id="borContInfo"></div>
      <input type="hidden" id="borContId">
      <div class="mr-fg"><label><?= $TH ? 'ปริมาณที่ต้องการ' : 'Quantity' ?></label><input type="number" id="borQty" min="0" step="any" placeholder="0"></div>
      <div class="mr-fg"><label><?= $TH ? 'หน่วย' : 'Unit' ?></label>
        <select id="borUnit">
          <option value="mL">mL</option><option value="L">L</option><option value="g">g</option>
          <option value="kg">kg</option><option value="mg">mg</option><option value="unit">unit</option>
          <option value="bottle">bottle</option><option value="piece">piece</option>
        </select>
      </div>
      <div class="mr-fg"><label><?= $TH ? 'วัตถุประสงค์ *' : 'Purpose *' ?></label><textarea id="borPurpose" placeholder="<?= $TH ? 'อธิบายวัตถุประสงค์การใช้...' : 'Describe the purpose...' ?>"></textarea></div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalBorrow')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="btnBorrow" onclick="submitBorrow()"><i class="fas fa-paper-plane"></i> <?= $TH ? 'ส่งคำขอ' : 'Send Request' ?></button>
    </div>
  </div>
</div>

<!-- Modal: Transaction -->
<div class="mr-modal-ov" id="modalTxn">
  <div class="mr-modal">
    <div class="mr-modal-hdr">
      <h3><i class="fas fa-exchange-alt"></i> <?= $TH ? 'ธุรกรรม' : 'Transaction' ?></h3>
      <button class="mr-modal-x" onclick="closeModal('modalTxn')">&times;</button>
    </div>
    <div class="mr-modal-body" id="txnModalBody"></div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" id="txnBtnBack" style="display:none" onclick="txnGoBack()">
        <i class="fas fa-arrow-left"></i> <?= $TH ? 'กลับ' : 'Back' ?>
      </button>
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalTxn')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="txnBtnSubmit" style="display:none" onclick="submitTxn()">
        <i class="fas fa-check"></i> <?= $TH ? 'ยืนยัน' : 'Confirm' ?>
      </button>
    </div>
  </div>
</div>

<!-- Modal: Report Options -->
<div class="mr-modal-ov" id="modalRpt">
  <div class="mr-modal wide">
    <div class="mr-modal-hdr">
      <h3><i class="fas fa-clipboard-list"></i> <?= $TH ? 'ตัวเลือกรายงาน' : 'Report Options' ?></h3>
      <button class="mr-modal-x" onclick="closeModal('modalRpt')">&times;</button>
    </div>
    <div class="mr-modal-body">
      <div class="mr-fg">
        <label><?= $TH ? 'ขอบเขตรายงาน' : 'Report Scope' ?></label>
        <div class="rpt-scope-grid" id="rptScopeGrid"></div>
      </div>
      <div id="rptCascade" style="display:flex;flex-direction:column;gap:12px"></div>
      <div class="mr-fg">
        <label><?= $TH ? 'คอลัมน์เพิ่มเติม' : 'Extra Columns' ?></label>
        <div class="rpt-fields-row">
          <label class="rpt-field-lbl">
            <input type="checkbox" id="rptFldNickname">
            <i class="fas fa-tag" style="font-size:10px"></i>
            <?= $TH ? 'ชื่อเล่น' : 'Nickname' ?>
          </label>
          <label class="rpt-field-lbl">
            <input type="checkbox" id="rptFldNote">
            <i class="fas fa-sticky-note" style="font-size:10px"></i>
            <?= $TH ? 'บันทึกประจำห้อง (Note)' : 'Room Note' ?>
          </label>
        </div>
      </div>
      <div id="rptPreview"></div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalRpt')"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="mr-btn mr-btn-p" id="btnRpt" onclick="submitRpt()">
        <i class="fas fa-file-pdf"></i> <?= $TH ? 'สร้างรายงาน' : 'Generate' ?>
      </button>
    </div>
  </div>
</div>

<!-- Batch Transaction Sheet (btx-md) -->
<div class="btx-ov" id="mrBtxOv" onclick="if(event.target===this)closeMrBatch()">
  <div class="btx-md">
    <div class="btx-handle"><div class="btx-handle-bar"></div></div>
    <div class="btx-hdr">
      <div class="btx-hdr-top">
        <div class="btx-hdr-ic" id="mrBtxIc"><i class="fas fa-exchange-alt"></i></div>
        <div class="btx-hdr-info">
          <div class="btx-hdr-title" id="mrBtxTitle"><?= $TH ? 'ธุรกรรมหลายรายการ' : 'Batch Transaction' ?></div>
          <div class="btx-hdr-sub" id="mrBtxSub"></div>
        </div>
        <button class="btx-hdr-close" onclick="closeMrBatch()"><i class="fas fa-times"></i></button>
      </div>
      <div class="btx-tabs" id="mrBtxTabs"></div>
    </div>
    <div class="btx-body" id="mrBtxBody"></div>
    <div class="btx-footer">
      <button class="btx-btn-cancel" onclick="closeMrBatch()"><?= $TH ? 'ยกเลิก' : 'Cancel' ?></button>
      <button class="btx-btn-submit" id="mrBtxSubmit" onclick="submitMrBatch()">
        <i class="fas fa-check"></i>
        <span id="mrBtxSubmitLbl"><?= $TH ? 'ยืนยันดำเนินการ' : 'Confirm' ?></span>
      </button>
    </div>
  </div>
</div>

<!-- Danger Confirm Popup -->
<div class="mr-del-ov" id="mrDelOv" onclick="if(event.target===this)_mrDelResolve(false)">
  <div class="mr-del-box" id="mrDelBox"></div>
</div>

<!-- Batch Confirm Popup -->
<div class="mr-btc-ov" id="mrBtcOv" onclick="if(event.target===this){if(_dndPending)_dndCancel();else mrBtcEdit()}">
  <div class="mr-btc-box" id="mrBtcBox"></div>
</div>

<!-- Modal: Manage Room Admins -->
<div class="mr-modal-ov" id="modalAdmins">
  <div class="mr-modal wide">
    <div class="mr-modal-hdr">
      <h3><i class="fas fa-users-cog"></i> <?= $TH ? 'ผู้ดูแลห้อง' : 'Room Admins' ?></h3>
      <button class="mr-modal-x" onclick="closeModal('modalAdmins')">&times;</button>
    </div>
    <div class="mr-modal-body">
      <div id="adminsList"></div>
      <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border,#e0e0e0)">
        <div style="font-size:11px;font-weight:700;color:var(--c2,#666);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px"><?= $TH ? 'เพิ่มผู้ดูแลร่วม' : 'Add Co-Admin' ?></div>
        <div class="rm-search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="adminSearch" placeholder="<?= $TH ? 'ค้นหาชื่อหรืออีเมล...' : 'Search by name or email...' ?>" oninput="searchUsersToAdd()">
        </div>
        <div id="adminSearchResults"></div>
      </div>
    </div>
    <div class="mr-modal-footer">
      <button class="mr-btn mr-btn-g" onclick="closeModal('modalAdmins')"><?= $TH ? 'ปิด' : 'Close' ?></button>
    </div>
  </div>
</div>

<script>
const API = '/v1/api/myroom.php';
const TH  = <?= $TH ? 'true' : 'false' ?>;
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const CURRENT_UID = <?= $uid ?>;

const S = {
  rooms:[], activeRoomId:null, cabinets:[], containers:[],
  filter:'all', treeFilter:null, search:'',
  view:'card',
  selected: new Set(),
  lastIdx: -1,
  treeVisible: true,
  expanded: { cabinets: new Set(), shelves: new Set(), slots: new Set() },
  renaming: null,
  draggingId: null,
  draggingIds: new Set(),
  roomAdmins: [],
};

const TYPE_IC  = {bottle:'fa-flask',vial:'fa-vial',flask:'fa-flask',canister:'fa-drum',cylinder:'fa-circle',ampoule:'fa-syringe',bag:'fa-shopping-bag',other:'fa-box'};
const TYPE_COL = {bottle:'#2563eb',vial:'#7c3aed',flask:'#059669',canister:'#ea580c',cylinder:'#e11d48',ampoule:'#4338ca',bag:'#78716c',other:'#64748b'};
const CAB_IC   = {storage:'fa-archive',fume_hood:'fa-wind',refrigerator:'fa-temperature-low',freezer:'fa-snowflake',safety_cabinet:'fa-shield-alt',other:'fa-box'};
const CAB_COL  = {storage:'#6366f1',fume_hood:'#0d9488',refrigerator:'#2563eb',freezer:'#7c3aed',safety_cabinet:'#dc2626',other:'#64748b'};

async function apiFetch(url, opts={}) {
  const tok = localStorage.getItem('auth_token');
  const h = {'Content-Type':'application/json'};
  if (tok) h['Authorization'] = 'Bearer ' + tok;
  return (await fetch(url, {headers:h,...opts})).json();
}

/* ── Init ── */
async function init() {
  const res = await apiFetch(API + '?action=my_rooms');
  if (!res.success || !res.data?.length) {
    document.getElementById('noRooms').style.display = '';
    document.getElementById('heroMeta').innerHTML = heroMeta(0,0,0,0);
    return;
  }
  S.rooms = res.data;
  renderHeroMeta(); renderRoomTabs(); selectRoom(S.rooms[0].room_id);
}

function heroMeta(total,rooms,unplaced,expiring) {
  return [[total,TH?'สารทั้งหมด':'Total','#fff'],[rooms,TH?'ห้องที่ดูแล':'Rooms','#a5b4fc'],
          [unplaced,TH?'ยังไม่จัดเก็บ':'Unplaced','#fbbf24'],[expiring,TH?'ใกล้หมดอายุ':'Expiring','#f87171']]
    .map(([v,l,c])=>`<div class="mr-hero-c"><div class="v" style="color:${c}">${v}</div><div class="lb">${l}</div></div>`).join('');
}
function renderHeroMeta() {
  document.getElementById('heroMeta').innerHTML = heroMeta(
    S.rooms.reduce((s,r)=>s+parseInt(r.total||0),0), S.rooms.length,
    S.rooms.reduce((s,r)=>s+parseInt(r.unplaced||0),0),
    S.rooms.reduce((s,r)=>s+parseInt(r.expiring_soon||0),0)
  );
}
function renderRoomTabs() {
  document.getElementById('roomTabs').innerHTML = S.rooms.map(r=>
    `<button class="mr-room-tab${parseInt(r.room_id)===S.activeRoomId?' active':''}" onclick="selectRoom(${r.room_id})">
      ${parseInt(r.is_primary)?'<i class="fas fa-star" style="font-size:9px;color:#fbbf24;margin-right:2px"></i>':''}
      <span class="mr-rt-code">${escH(r.code)}</span>
      <span class="mr-rt-name">${escH(r.name)}</span>
      <span class="mr-rt-cnt">${r.total}</span>
    </button>`
  ).join('');
}

async function selectRoom(roomId) {
  S.activeRoomId = parseInt(roomId); S.treeFilter = null; S.filter = 'all'; S.search = '';
  S.selected.clear(); S.lastIdx = -1; updateBatchBar();
  document.getElementById('mrSearch').value = '';
  renderRoomTabs();
  document.getElementById('roomPanel').style.display = '';
  document.getElementById('noRooms').style.display   = 'none';
  showStatSkeleton();
  document.getElementById('storageTree').innerHTML = '<div class="mr-ld"><i class="fas fa-circle-notch fa-spin"></i></div>';
  showListSkeleton();
  const res = await apiFetch(API + '?action=room_data&room_id=' + roomId);
  if (!res.success) { showToast(res.error||'Error','err'); return; }
  S.cabinets   = res.data.cabinets   || [];
  S.containers = res.data.containers || [];
  renderRoomStats(true); renderStorageTree(); renderContainers(true);
}

/* ── Stats ── */
function showStatSkeleton() {
  document.getElementById('roomStats').innerHTML = Array(5).fill(0).map(()=>`
    <div class="mr-stat mr-stat-sk" style="background:#fafbfc;border-color:#f1f5f9;cursor:default">
      <div class="mr-sk mr-sk-ic"></div>
      <div style="flex:1">
        <div class="mr-sk mr-sk-num"></div>
        <div class="mr-sk mr-sk-lbl"></div>
      </div>
    </div>`).join('');
}

function showListSkeleton() {
  const el = document.getElementById('mrList');
  // Helper: one shimmer block
  const s = (w, h, r=4, d=0) =>
    `<div class="mr-sk" style="width:${w};height:${h}px;border-radius:${r}px;animation-delay:${d}ms"></div>`;

  if (S.view === 'table') {
    const rows = Array(10).fill(0).map((_, i) => {
      const d = i * 22;
      return `<tr class="mr-sk-trow">
        <td>${s('14px',14,3,d)}</td>
        <td>${s('28px',28,8,d+15)}</td>
        <td>${s('72px',13,4,d+25)}</td>
        <td><div>${s('58%',13,4,d+35)}${s('36%',10,4,d+55)}</div></td>
        <td>${s('52px',12,4,d+20)}</td>
        <td>${s('46px',12,4,d+38)}</td>
        <td>${s('60px',12,4,d+45)}</td>
        <td>${s('80px',20,10,d+52)}</td>
        <td>${s('54px',12,4,d+60)}</td>
        <td>${s('50px',20,6,d+65)}</td>
        <td>${s('60px',26,8,d+12)}</td>
      </tr>`;
    }).join('');
    el.innerHTML = `<div class="mr-tw"><table class="mr-t"><thead><tr>
      <th class="col-cb"></th><th></th><th>${TH?'รหัสขวด':'Code'}</th><th>${TH?'สารเคมี':'Chemical'}</th>
      <th>${TH?'เจ้าของ':'Owner'}</th><th>${TH?'คงเหลือ':'Qty'}</th>
      <th>${TH?'รับเข้า':'Received'}</th><th>${TH?'ตำแหน่ง':'Location'}</th>
      <th>${TH?'หมดอายุ':'Expiry'}</th><th>${TH?'สถานะ':'Status'}</th><th>${TH?'จัดการ':'Actions'}</th>
    </tr></thead><tbody>${rows}</tbody></table></div>`;

  } else if (S.view === 'grouped') {
    const groups = Array(6).fill(0).map((_, i) => {
      const d = i * 55;
      return `<div class="mr-grp mr-sk-grp-row">
        <div class="mr-grp-hdr">
          <div class="mr-grp-toggle"><i class="fas fa-chevron-right" style="opacity:.18"></i></div>
          ${s('38px',38,10,d)}
          <div class="mr-grp-info">
            ${s('52%',13,4,d+40)}
            <div style="display:flex;gap:6px;margin-top:5px">${s('76px',10,4,d+65)}${s('54px',10,4,d+80)}</div>
          </div>
          <div style="display:flex;gap:6px;align-items:center;flex-shrink:0">
            ${s('54px',20,8,d+50)}${s('40px',20,8,d+62)}
          </div>
        </div>
      </div>`;
    }).join('');
    el.innerHTML = `<div class="mr-groups">${groups}</div>`;

  } else {
    // Card view (default)
    const card = (i) => {
      const d = i * 52;
      return `<div class="mr-sk-card">
        <div class="mr-sk-card-hd">
          ${s('38px',38,10,d)}
          <div class="mr-sk-card-nm">
            ${s('78px',17,4,d+40)}
            ${s('62%',13,4,d+65)}
            ${s('44%',10,4,d+85)}
          </div>
          ${s('54px',21,10,d+30)}
        </div>
        <div class="mr-sk-card-bd">
          ${s('68%',11,4,d+72)}
          ${s('55%',11,4,d+90)}
          ${s('76%',11,4,d+108)}
          ${s('100%',28,8,d+50)}
        </div>
      </div>`;
    };
    el.innerHTML = '<div class="mr-grid">' + Array(8).fill(0).map((_, i) => card(i)).join('') + '</div>';
  }
}

function animateStatCounters() {
  document.querySelectorAll('#roomStats .mr-stat-v[data-val]').forEach((el, i) => {
    const target = parseInt(el.dataset.val) || 0;
    const cardDelay = i * 65;
    if (!target) { setTimeout(() => { el.textContent = '0'; }, cardDelay); return; }
    setTimeout(() => {
      const dur = 580, start = performance.now();
      function tick(now) {
        const t = Math.min((now - start) / dur, 1);
        const ease = 1 - Math.pow(1 - t, 3);
        el.textContent = Math.round(target * ease);
        if (t < 1) requestAnimationFrame(tick);
        else el.textContent = target;
      }
      requestAnimationFrame(tick);
    }, cardDelay + 100);
  });
}

function renderRoomStats(animate = false) {
  const r = S.rooms.find(r=>parseInt(r.room_id)===S.activeRoomId)||{};
  const [total,org,unp,exp,expired] = ['total','organized','unplaced','expiring_soon','expired'].map(k=>parseInt(r[k]||0));
  const defs = [
    ['all',      'fa-box',         '#6366f1','#eef2ff','rgba(99,102,241,.2)',  total,  TH?'ทั้งหมด':'Total'],
    ['organized','fa-layer-group', '#059669','#dcfce7','rgba(5,150,105,.2)',   org,    TH?'จัดเก็บแล้ว':'Organized'],
    ['unplaced', 'fa-inbox',       '#d97706','#fef3c7','rgba(217,119,6,.2)',   unp,    TH?'ยังไม่จัดเก็บ':'Unplaced'],
    ['expiring', 'fa-clock',       '#dc2626','#fee2e2','rgba(220,38,38,.2)',   exp,    TH?'ใกล้หมดอายุ':'Expiring'],
    ['expired',  'fa-ban',         '#94a3b8','#f1f5f9','rgba(148,163,184,.2)', expired,TH?'หมดอายุ':'Expired'],
  ];
  document.getElementById('roomStats').innerHTML = defs.map(([f,ic,col,bg,glow,v,l], idx)=>{
    const isActive = S.filter === f;
    const delay = animate ? idx * 65 : 0;
    const enterCls = animate ? ' entering' : '';
    const numHtml = animate
      ? `<div class="mr-stat-v" data-val="${v}">0</div>`
      : `<div class="mr-stat-v">${v}</div>`;
    return `<div class="mr-stat${enterCls}${isActive?' active':''}" data-stat-filter="${f}"
         data-col="${col}" data-bg="${bg}" data-glow="${glow}"
         style="animation-delay:${delay}ms;${isActive?`--mr-stat-col:${col};--mr-stat-glow:${glow};background:${bg}`:''}"
         onclick="setStatFilter('${f}')">
      <div class="mr-stat-ic" style="background:${bg};color:${col};animation-delay:${delay+80}ms"><i class="fas ${ic}"></i></div>
      <div>${numHtml}<div class="mr-stat-l">${l}</div></div>
    </div>`;
  }).join('');
  if (animate) requestAnimationFrame(() => animateStatCounters());
}

function setStatFilter(f) {
  S.filter = f; S.treeFilter = null;
  document.querySelectorAll('#roomStats .mr-stat').forEach(el => {
    const isActive = el.dataset.statFilter === f;
    const col  = el.dataset.col  || 'var(--mr)';
    const bg   = el.dataset.bg   || '';
    const glow = el.dataset.glow || 'rgba(99,102,241,.18)';
    el.classList.toggle('active', isActive);
    if (isActive) {
      el.style.setProperty('--mr-stat-col', col);
      el.style.setProperty('--mr-stat-glow', glow);
      el.style.background = bg;
    } else {
      el.style.removeProperty('--mr-stat-col');
      el.style.removeProperty('--mr-stat-glow');
      el.style.background = '';
    }
  });
  renderStorageTree(); renderContainers(true);
}

/* ── Storage Tree (Directory Tree Edition) ── */
function renderStorageTree() {
  const tf=S.treeFilter, uc=S.containers.filter(c=>!c.cabinet_id).length;
  const unplacedActive = tf&&tf.type==='unplaced';

  let h = `<div class="mr-tree-hdr">
    <span><i class="fas fa-warehouse" style="color:var(--mr);margin-right:6px"></i>${TH?'พื้นที่จัดเก็บ':'Storage'}</span>
    <div class="mr-tree-hdr-acts">
      ${CAN_EDIT?`<button class="mr-nibtn grn" onclick="openAddCabinet()" title="${TH?'เพิ่มตู้':'Add Cabinet'}"><i class="fas fa-plus"></i></button>`:''}
    </div>
  </div>
  <div class="mr-tree-unplaced${unplacedActive?' active':''}"
       onclick="setTreeFilter('unplaced',0)"
       ondragover="treeDragOver(event,'unplaced',0)"
       ondrop="treeDragDrop(event,'unplaced',0)"
       ondragleave="treeDragLeave(event)">
    <i class="fas fa-inbox"></i><span>${TH?'ยังไม่จัดเก็บ':'Unplaced'}</span>
    <span class="mr-tree-cnt amber">${uc}</span>
  </div>`;

  if (!S.cabinets.length) {
    h += `<div class="mr-tree-empty"><i class="fas fa-box-open"></i><p>${TH?'ยังไม่มีตู้จัดเก็บ<br><small>กดปุ่ม + ด้านบนเพื่อเพิ่ม':'No cabinets yet<br><small>Click + above to add'}</small></p></div>`;
  } else {
    h += S.cabinets.map(cab=>renderCabNode(cab)).join('');
  }
  document.getElementById('storageTree').innerHTML = h;

  // focus rename input if active
  if (S.renaming) {
    const inp = document.getElementById('mr-rename-inp');
    if (inp) { inp.focus(); inp.select(); }
  }
}

function renderCabNode(cab) {
  const id=parseInt(cab.id), open=S.expanded.cabinets.has(id);
  const tf=S.treeFilter, active=tf&&tf.type==='cabinet'&&tf.id===id;
  const ic=CAB_IC[cab.type]||'fa-box', col=CAB_COL[cab.type]||'#6366f1';
  const isRen=S.renaming?.type==='cabinet'&&S.renaming.id===id;
  const shelfCnt=(cab.shelves||[]).length;

  const nameHtml=isRen
    ?`<input id="mr-rename-inp" class="mr-rename-inp" value="${escH(S.renaming.cur)}"
        onclick="event.stopPropagation()"
        onkeydown="renameKeydown(event,'cabinet',${id})"
        onblur="submitRename('cabinet',${id})">`
    :`<div class="mr-cab-name">${escH(cab.name||cab.code)}</div>
      <div class="mr-cab-sub">${cab.code?escH(cab.code)+' · ':''} ${shelfCnt} ${TH?'ชั้น':'shelf'}</div>`;

  const children=open
    ?`<div class="mr-shelves">${shelfCnt
        ?(cab.shelves||[]).map(s=>renderShelfNode(s,id)).join('')
        :`<div style="padding:6px 12px 8px 28px;font-size:10px;color:var(--c3,#999);font-style:italic">${TH?'ยังไม่มีชั้น':'No shelves yet'}</div>`
      }</div>`
    :'';

  return `<div class="mr-cab-wrap">
    <div class="mr-cab-hdr${active?' active':''}" data-type="${escH(cab.type||'storage')}"
         onclick="treeNodeClick('cabinet',${id})"
         ondragover="treeDragOver(event,'cabinet',${id})"
         ondrop="treeDragDrop(event,'cabinet',${id})"
         ondragleave="treeDragLeave(event)">
      <button class="mr-toggle${open?' open':''}" onclick="event.stopPropagation();treeToggle('cabinets',${id})">
        <i class="fas fa-chevron-right"></i>
      </button>
      <div class="mr-cab-ic" style="background:${col}18;color:${col}">
        <i class="fas ${ic}"></i>
      </div>
      <div class="mr-cab-meta">${nameHtml}</div>
      <span class="mr-cnt-pill">${cab.container_count||0}</span>
      <div class="mr-node-acts">
        ${CAN_EDIT?`<button class="mr-nibtn" onclick="event.stopPropagation();startRename('cabinet',${id},'${escH(cab.name||cab.code||'')}')" title="Rename"><i class="fas fa-pen"></i></button>
        <button class="mr-nibtn grn" onclick="event.stopPropagation();openAddShelf(${id})" title="${TH?'เพิ่มชั้น':'Add Shelf'}"><i class="fas fa-plus"></i></button>
        <button class="mr-nibtn red" onclick="event.stopPropagation();deleteCabinet(${id})" title="Delete"><i class="fas fa-trash"></i></button>`:''}
      </div>
    </div>
    ${children}
  </div>`;
}

function renderShelfNode(s, cabId) {
  const id=parseInt(s.id), open=S.expanded.shelves.has(id);
  const tf=S.treeFilter, active=tf&&tf.type==='shelf'&&tf.id===id;
  const isRen=S.renaming?.type==='shelf'&&S.renaming.id===id;
  const slots=(s.slots||[]);
  const totalSlots=slots.length;
  const usedSlots=slots.filter(sl=>(sl.container_count||0)>0).length;
  const pct=totalSlots>0?Math.round(usedSlots/totalSlots*100):0;
  const fillCls=pct>=100?'cap-full':pct===0?'cap-empty':'';

  const nameHtml=isRen
    ?`<input id="mr-rename-inp" class="mr-rename-inp" value="${escH(S.renaming.cur)}"
        onclick="event.stopPropagation()"
        onkeydown="renameKeydown(event,'shelf',${id})"
        onblur="submitRename('shelf',${id})">`
    :`<span class="mr-shelf-name">${escH(s.name||s.code||'Shelf')}</span>`;

  const capBar=totalSlots>0
    ?`<div class="mr-cap-wrap">
        <div class="mr-cap-track"><div class="mr-cap-fill ${fillCls}" style="width:${pct}%"></div></div>
        <span class="mr-cap-txt">${usedSlots}/${totalSlots}</span>
      </div>`
    :`<span class="mr-cnt-pill-sm">${s.container_count||0}</span>`;

  const children=open
    ?`<div class="mr-slot-list">${slots.length
        ?slots.map(sl=>renderSlotNode(sl,id,cabId)).join('')
        :`<div style="padding:3px 6px 5px 6px;font-size:10px;color:var(--c3,#999);font-style:italic">${TH?'ยังไม่มีช่อง':'No slots yet'}</div>`
      }</div>`
    :'';

  return `<div class="mr-shelf-wrap">
    <div class="mr-shelf-hdr${active?' active':''}"
         onclick="event.stopPropagation();treeNodeClick('shelf',${id})"
         ondragover="treeDragOver(event,'shelf',${id})"
         ondrop="treeDragDrop(event,'shelf',${id})"
         ondragleave="treeDragLeave(event)">
      <button class="mr-toggle${open?' open':''}" onclick="event.stopPropagation();treeToggle('shelves',${id})">
        <i class="fas fa-chevron-right"></i>
      </button>
      <i class="fas fa-bars" style="font-size:9px;color:#94a3b8;flex-shrink:0"></i>
      ${nameHtml}
      ${capBar}
      <div class="mr-node-acts">
        ${CAN_EDIT?`<button class="mr-nibtn" onclick="event.stopPropagation();startRename('shelf',${id},'${escH(s.name||s.code||'')}')" title="Rename"><i class="fas fa-pen"></i></button>
        <button class="mr-nibtn grn" onclick="event.stopPropagation();openAddSlot(${id})" title="${TH?'เพิ่มช่อง':'Add Slot'}"><i class="fas fa-plus"></i></button>
        <button class="mr-nibtn red" onclick="event.stopPropagation();deleteShelf(${id})" title="Delete"><i class="fas fa-trash"></i></button>`:''}
      </div>
    </div>
    ${children}
  </div>`;
}

function renderSlotNode(sl, shelfId, cabId) {
  const id=parseInt(sl.id), open=S.expanded.slots.has(id);
  const tf=S.treeFilter, active=tf&&tf.type==='slot'&&tf.id===id;
  const isRen=S.renaming?.type==='slot'&&S.renaming.id===id;
  const filled=(sl.container_count||0)>0;

  const innerHtml=isRen
    ?`<input id="mr-rename-inp" class="mr-rename-inp" value="${escH(S.renaming.cur)}"
        onclick="event.stopPropagation()"
        onkeydown="renameKeydown(event,'slot',${id})"
        onblur="submitRename('slot',${id})">`
    :`<span class="mr-slot-code">${escH(sl.code||sl.name||'S')}</span>
      ${sl.name&&sl.code?`<span class="mr-slot-name-lbl">${escH(sl.name)}</span>`:''}`;

  const chips=open?buildChipList(id):'';

  return `<div class="mr-slot-item">
    <div class="mr-slot-row${active?' active':''}"
         onclick="event.stopPropagation();treeNodeClick('slot',${id})"
         ondragover="treeDragOver(event,'slot',${id})"
         ondrop="treeDragDrop(event,'slot',${id})"
         ondragleave="treeDragLeave(event)">
      <button class="mr-toggle${open?' open':''}" onclick="event.stopPropagation();treeToggle('slots',${id})" style="width:14px;height:14px;font-size:7px">
        <i class="fas fa-chevron-right"></i>
      </button>
      <span class="mr-slot-dot${filled?' filled':''}"></span>
      ${innerHtml}
      ${filled?`<span class="mr-cnt-pill-sm">${sl.container_count}</span>`:''}
      <div class="mr-node-acts">
        ${CAN_EDIT?`<button class="mr-nibtn" onclick="event.stopPropagation();startRename('slot',${id},'${escH(sl.code||sl.name||'')}')" title="Rename"><i class="fas fa-pen"></i></button>
        <button class="mr-nibtn red" onclick="event.stopPropagation();deleteSlot(${id})" title="Delete"><i class="fas fa-trash"></i></button>`:''}
      </div>
    </div>
    ${chips}
  </div>`;
}

function buildChipList(slotId) {
  const items=S.containers.filter(c=>parseInt(c.slot_id)===slotId);
  if(!items.length) return `<div class="mr-chip-list"><div style="font-size:10px;color:var(--c3,#999);font-style:italic;padding:3px 0">${TH?'ว่าง':'Empty'}</div></div>`;
  return `<div class="mr-chip-list">`+items.map(c=>{
    const qty=parseFloat(c.current_quantity||0);
    const st=chipStatus(c);
    return `<div class="mr-chip${S.draggingIds.has(parseInt(c.id))?' dragging':''}"
      draggable="true"
      ondragstart="containerDragStart(event,${c.id})"
      ondragend="containerDragEnd(event)"
      onclick="if(!S.draggingId)openContainerDetail(${c.id})">
      <span class="mr-chip-dot ${st}"></span>
      <span class="mr-chip-code">${escH(c.bottle_code||'—')}</span>
      <span class="mr-chip-nm">${escH(c.chem_name||'—')}</span>
      ${qty>0?`<span class="mr-chip-qty">${qty}${c.quantity_unit?' '+c.quantity_unit:''}</span>`:''}
    </div>`;
  }).join('')+'</div>';
}

function chipStatus(c) {
  if(!c.expiry_date) return 'none';
  const d=new Date(c.expiry_date), now=new Date();
  if(d<now) return 'exp';
  if(d<=new Date(now.getTime()+60*864e5)) return 'warn';
  return 'ok';
}

function treeNodeClick(type, id) {
  if (S.renaming) return;
  if (S.treeFilter?.type===type && S.treeFilter.id===parseInt(id)) S.treeFilter=null;
  else { S.treeFilter={type,id:parseInt(id)}; S.filter=(type==='unplaced')?'unplaced':'all'; }
  renderStorageTree(); renderContainers(true);
}
function setTreeFilter(type, id) { treeNodeClick(type, id); }

function toggleTree() {
  if(window.innerWidth<=640){ openMobileTree(); return; }
  S.treeVisible=!S.treeVisible;
  document.getElementById('storageTree').classList.toggle('collapsed',!S.treeVisible);
  document.getElementById('treeToggleLbl').textContent=S.treeVisible?(TH?'ซ่อนต้นไม้':'Hide Tree'):(TH?'แสดงต้นไม้':'Show Tree');
  document.getElementById('btnTreeToggle').classList.toggle('active',!S.treeVisible);
}
function openMobileTree(){
  const t=document.getElementById('storageTree');
  t.classList.add('mobile-open'); t.classList.remove('collapsed');
  document.getElementById('mrTreeOv').classList.add('show');
  document.body.style.overflow='hidden';
}
function closeMobileTree(){
  document.getElementById('storageTree').classList.remove('mobile-open');
  document.getElementById('mrTreeOv').classList.remove('show');
  document.body.style.overflow='';
}

/* ── Tree: expand/collapse ── */
function treeToggle(setKey, id) {
  const set=S.expanded[setKey];
  set.has(id)?set.delete(id):set.add(id);
  S.renaming=null;
  renderStorageTree();
}

/* ── Tree: inline rename ── */
function startRename(type, id, cur) {
  S.renaming={type,id:parseInt(id),cur};
  // auto-expand parent so node is visible
  if(type==='cabinet') S.expanded.cabinets.add(parseInt(id));
  if(type==='shelf')   S.expanded.shelves.add(parseInt(id));
  if(type==='slot')    S.expanded.slots.add(parseInt(id));
  renderStorageTree();
}
function cancelRename() { S.renaming=null; renderStorageTree(); }
function renameKeydown(e, type, id) {
  if(e.key==='Enter')  { e.preventDefault(); submitRename(type,id); }
  if(e.key==='Escape') { e.preventDefault(); cancelRename(); }
}
async function submitRename(type, id) {
  const inp=document.getElementById('mr-rename-inp');
  if(!inp) { S.renaming=null; return; }
  const name=inp.value.trim();
  S.renaming=null;
  if(!name) { renderStorageTree(); return; }
  const actionMap={cabinet:'rename_cabinet',shelf:'rename_shelf',slot:'rename_slot'};
  const r=await apiFetch(API+'?action='+actionMap[type],{method:'POST',body:JSON.stringify({id,name})});
  if(!r.success) { showToast(r.error||'Error','err'); renderStorageTree(); return; }
  showToast(TH?'เปลี่ยนชื่อเรียบร้อย':'Renamed','ok');
  await selectRoom(S.activeRoomId);
}

/* ── Drag & Drop ── */
let _dndPending = null;

function containerDragStart(e, id) {
  e.dataTransfer.setData('text/plain', String(id));
  e.dataTransfer.effectAllowed = 'move';
  S.draggingId = id;
  // If dragged item is inside the active selection, bring all selected along
  S.draggingIds = (S.selected.size > 1 && S.selected.has(id))
    ? new Set([...S.selected])
    : new Set([id]);
  document.body.classList.add('mr-dragging');
  // Mark every selected row/card visually without a full re-render
  S.draggingIds.forEach(cid => {
    document.querySelectorAll(`[data-cid="${cid}"]`).forEach(el => el.classList.add('dragging-src'));
  });
  setTimeout(() => renderStorageTree(), 0);
}

function containerDragEnd(e) {
  S.draggingId = null;
  S.draggingIds = new Set();
  document.body.classList.remove('mr-dragging');
  document.querySelectorAll('.drop-over').forEach(el => el.classList.remove('drop-over'));
  document.querySelectorAll('.dragging-src').forEach(el => el.classList.remove('dragging-src'));
  renderStorageTree();
}

function treeDragOver(e, type, id) {
  if (!S.draggingId && !e.dataTransfer.types.includes('text/plain')) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  document.querySelectorAll('.drop-over').forEach(el => el.classList.remove('drop-over'));
  e.currentTarget.classList.add('drop-over');
}

function treeDragLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) e.currentTarget.classList.remove('drop-over');
}

function treeDragDrop(e, type, id) {
  e.preventDefault();
  e.currentTarget.classList.remove('drop-over');
  document.querySelectorAll('.drop-over').forEach(el => el.classList.remove('drop-over'));
  const contId = parseInt(e.dataTransfer.getData('text/plain') || S.draggingId || 0);
  if (!contId) return;

  // Resolve target IDs
  let cabinet_id = null, shelf_id = null, slot_id = null;
  if (type === 'slot') {
    slot_id = parseInt(id);
    const sl = findSlot(slot_id);
    if (sl) { shelf_id = sl.shelf_id; cabinet_id = sl.cabinet_id; }
  } else if (type === 'shelf') {
    shelf_id = parseInt(id);
    const sh = findShelf(shelf_id);
    if (sh) cabinet_id = sh.cabinet_id;
  } else if (type === 'cabinet') {
    cabinet_id = parseInt(id);
  }

  // Collect IDs to move
  const idsToMove = [...(S.draggingIds.size ? S.draggingIds : new Set([contId]))];

  // Show confirm popup instead of immediately calling API
  _dndShowConfirm(idsToMove, { type, id: parseInt(id), cabinet_id, shelf_id, slot_id });
}

/* ── Location label helpers ── */
function _dndTargetLabel(target) {
  if (!target.cabinet_id) return TH ? 'ยังไม่จัดเก็บ' : 'Unplaced';
  const parts = [];
  const cab = S.cabinets.find(c => parseInt(c.id) === target.cabinet_id);
  if (cab) parts.push(cab.name || cab.code || '');
  if (target.shelf_id) {
    for (const c of S.cabinets) {
      const sh = (c.shelves||[]).find(s => parseInt(s.id) === target.shelf_id);
      if (sh) { parts.push(sh.name || sh.code || (TH?`ชั้น ${sh.level||''}`:`Shelf ${sh.level||''}`)); break; }
    }
  }
  if (target.slot_id) {
    for (const c of S.cabinets) for (const sh of (c.shelves||[])) {
      const sl = (sh.slots||[]).find(s => parseInt(s.id) === target.slot_id);
      if (sl) { parts.push(sl.code || sl.name || (TH?`ช่อง ${sl.position||''}`:`Slot ${sl.position||''}`)); break; }
    }
  }
  return parts.filter(Boolean).join(' › ');
}

function _contLocLabel(c) {
  if (!c.cabinet_id) return TH ? 'ยังไม่จัดเก็บ' : 'Unplaced';
  return [c.cabinet_code||c.cabinet_name, c.shelf_code||c.shelf_name, c.slot_code||c.slot_name]
    .filter(Boolean).join(' › ');
}

/* ── DnD confirm popup ── */
function _dndShowConfirm(ids, target) {
  const containers = ids.map(id => S.containers.find(c => parseInt(c.id) === id)).filter(Boolean);
  if (!containers.length) return;
  _dndPending = { ids, target };

  const toLbl   = _dndTargetLabel(target);
  const toIcon  = !target.cabinet_id ? 'fa-inbox' : target.slot_id ? 'fa-th' : target.shelf_id ? 'fa-layer-group' : 'fa-archive';
  const toColor = !target.cabinet_id ? '#f59e0b' : '#6366f1';

  const itemsHtml = containers.map(c => {
    const fromLbl = _contLocLabel(c);
    const name = escH((c.chem_name || c.bottle_code || '—').substring(0, 34));
    const code = c.bottle_code && c.chem_name
      ? `<span style="font-family:monospace;font-size:9.5px;color:#94a3b8;font-weight:400"> · ${escH(c.bottle_code)}</span>` : '';
    return `<div class="mr-btc-item" style="background:#f8fafc;border-color:#e2e8f0">
      <div class="mr-btc-item-ic" style="background:#eef2ff;color:#6366f1"><i class="fas fa-flask"></i></div>
      <div style="flex:1;min-width:0">
        <div class="mr-btc-item-name">${name}${code}</div>
        <div class="mr-btc-item-sub" style="display:flex;align-items:center;gap:5px;margin-top:2px">
          <span style="color:#94a3b8;font-size:9.5px">${escH(fromLbl)}</span>
          <i class="fas fa-arrow-right" style="font-size:8px;color:#c7d2fe"></i>
          <span style="color:#4f46e5;font-weight:700;font-size:9.5px">${escH(toLbl)}</span>
        </div>
      </div>
    </div>`;
  }).join('');

  document.getElementById('mrBtcBox').innerHTML = `
    <div class="mr-btc-hdr">
      <div class="mr-btc-hdr-ic" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <i class="fas fa-map-pin"></i>
      </div>
      <h3>${TH ? 'ย้ายตำแหน่งจัดเก็บ' : 'Move to Location'}</h3>
      <p>${containers.length} ${TH ? 'รายการ' : 'item(s)'}</p>
    </div>
    <div class="mr-btc-body">
      <div>
        <div class="mr-btc-sec">${TH ? 'ตำแหน่งปลายทาง' : 'Destination'}</div>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#eef2ff;border-radius:12px;border:1.5px solid #c7d2fe">
          <div style="width:34px;height:34px;border-radius:9px;background:${toColor};color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0">
            <i class="fas ${toIcon}"></i>
          </div>
          <span style="font-size:13px;font-weight:800;color:#3730a3">${escH(toLbl)}</span>
        </div>
      </div>
      <div>
        <div class="mr-btc-sec">${TH ? 'รายการที่จะย้าย' : 'Items to move'} (${containers.length})</div>
        <div class="mr-btc-items">${itemsHtml}</div>
      </div>
    </div>
    <div class="mr-btc-footer">
      <button class="mr-btc-btn-confirm" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)" onclick="_dndConfirm()">
        <i class="fas fa-map-pin"></i> ${TH ? 'ยืนยัน ย้ายตำแหน่ง' : 'Confirm Move'}
      </button>
      <button class="mr-btc-btn-edit" onclick="_dndCancel()">
        <i class="fas fa-times"></i> ${TH ? 'ยกเลิก' : 'Cancel'}
      </button>
    </div>`;

  document.getElementById('mrBtcOv').classList.add('show');
}

function _dndCancel() {
  document.getElementById('mrBtcOv').classList.remove('show');
  _dndPending = null;
  S.draggingId = null;
  S.draggingIds = new Set();
  document.body.classList.remove('mr-dragging');
  document.querySelectorAll('.dragging-src').forEach(el => el.classList.remove('dragging-src'));
  renderStorageTree();
}

async function _dndConfirm() {
  if (!_dndPending) return;
  const { ids, target } = _dndPending;
  _dndPending = null;
  document.getElementById('mrBtcOv').classList.remove('show');

  let ok = 0, fail = 0;
  for (const cid of ids) {
    const r = await apiFetch(API + '?action=place_container', {
      method: 'POST',
      body: JSON.stringify({
        container_id: cid,
        room_id: S.activeRoomId,
        cabinet_id: target.cabinet_id,
        shelf_id:   target.shelf_id,
        slot_id:    target.slot_id,
      })
    });
    r.success ? ok++ : fail++;
  }

  S.draggingId  = null;
  S.draggingIds = new Set();
  document.body.classList.remove('mr-dragging');

  const msg = ok
    ? `${TH?'ย้ายสำเร็จ':'Moved'} ${ok} ${TH?'รายการ':'item(s)'}${fail?` (${fail} ${TH?'ล้มเหลว':'failed'})`:''}`
    : (TH ? 'ย้ายล้มเหลว' : 'Move failed');
  showToast(msg, ok ? 'ok' : 'err');

  if (ok > 0) {
    clearSelection();
    await selectRoom(S.activeRoomId);
  }
}
function findShelf(shelfId) {
  for(const cab of S.cabinets) for(const sh of(cab.shelves||[]))
    if(parseInt(sh.id)===shelfId) return {...sh,cabinet_id:parseInt(cab.id)};
  return null;
}
function findSlot(slotId) {
  for(const cab of S.cabinets) for(const sh of(cab.shelves||[]))
    for(const sl of(sh.slots||[]))
      if(parseInt(sl.id)===slotId) return {...sl,shelf_id:parseInt(sh.id),cabinet_id:parseInt(cab.id)};
  return null;
}

/* ── View ── */
function setView(v) {
  S.view=v; S.selected.clear(); S.lastIdx=-1; updateBatchBar();
  document.querySelectorAll('#viewSwitcher button').forEach((b,i)=>b.classList.toggle('active',['table','card','grouped'][i]===v));
  renderContainers(true);
}

/* ── Filtering ── */
function getFiltered() {
  let list = [...S.containers];
  const q = S.search.toLowerCase(), tf = S.treeFilter;
  const now=new Date(), in60=new Date(now.getTime()+60*24*60*60*1000);
  if (tf) {
    if      (tf.type==='unplaced') list=list.filter(c=>!c.cabinet_id);
    else if (tf.type==='cabinet')  list=list.filter(c=>parseInt(c.cabinet_id)===tf.id);
    else if (tf.type==='shelf')    list=list.filter(c=>parseInt(c.shelf_id)===tf.id);
    else if (tf.type==='slot')     list=list.filter(c=>parseInt(c.slot_id)===tf.id);
  }
  if      (S.filter==='organized') list=list.filter(c=>c.cabinet_id);
  else if (S.filter==='unplaced')  list=list.filter(c=>!c.cabinet_id);
  else if (S.filter==='expiring')  list=list.filter(c=>{ if(!c.expiry_date)return false; const e=new Date(c.expiry_date); return e>=now&&e<=in60; });
  else if (S.filter==='expired')   list=list.filter(c=>{ if(!c.expiry_date)return false; return new Date(c.expiry_date)<now; });
  if (q) list=list.filter(c=>(c.bottle_code||'').toLowerCase().includes(q)||(c.chem_name||'').toLowerCase().includes(q)||
    (c.nickname||'').toLowerCase().includes(q)||(c.cas_number||'').toLowerCase().includes(q)||(c.molecular_formula||'').toLowerCase().includes(q));
  return list;
}

/* ── Render helpers ── */
function locBadge(c) {
  if (!c.cabinet_id) return `<span class="mr-loc unplaced"><i class="fas fa-inbox"></i>${TH?'ยังไม่จัดเก็บ':'Unplaced'}</span>`;
  const pts=[c.cabinet_code||c.cabinet_name,c.shelf_code||c.shelf_name,c.slot_code||c.slot_name].filter(Boolean);
  return `<span class="mr-loc placed"><i class="fas fa-map-marker-alt"></i>${pts.map(p=>`<span>${escH(p)}</span>`).join(' › ')}</span>`;
}
function expBadge(c) {
  if (!c.expiry_date) return '';
  const e=new Date(c.expiry_date), now=new Date(), in60=new Date(now.getTime()+60*24*60*60*1000);
  if (e<now)  return `<span class="stk-badge badge-exp">${TH?'หมดอายุ':'Expired'}</span>`;
  if (e<=in60){ const d=Math.round((e-now)/86400000); return `<span class="stk-badge badge-warn">${d}${TH?'วัน':'d'}</span>`; }
  return '';
}
function typeIc(t) {
  const ic=TYPE_IC[t]||'fa-box', col=TYPE_COL[t]||'#64748b';
  return `<div class="type-icon" style="background:${col}18;color:${col}"><i class="fas ${ic}"></i></div>`;
}
function ownerDisp(c) {
  const n=c.owner_name||[c.owner_fn||'',c.owner_ln||''].join(' ').trim()||'—';
  const init=n.replace(/^(นาย|นางสาว|นาง|ดร\.)\s*/u,'').charAt(0)||'?';
  const avHtml=(size=20,fs=8)=>avatarHtml({first_name:c.owner_fn||'',last_name:c.owner_ln||'',email:'',avatar_url:c.owner_avatar_url||null},size,fs);
  return {n, init, avHtml};
}
function fmtDate(d){ return d?String(d).slice(0,10):'—'; }
function rowActs(c) {
  return (CAN_EDIT?`<button class="mr-t-act" onclick="openPlaceModal(${c.id})" title="${TH?'ตำแหน่ง':'Loc'}"><i class="fas fa-map-pin"></i></button>
    <button class="mr-t-act" onclick="openNoteModal(${c.id})" title="Note"><i class="fas fa-sticky-note"></i></button>`:'') +
    `<button class="mr-t-act txn" onclick="openTxnModal(${c.id})" title="${TH?'ธุรกรรม':'Transaction'}"><i class="fas fa-exchange-alt"></i></button>`;
}

/* ── Render dispatcher ── */
function renderContainers(anim=false) {
  const list=getFiltered(), el=document.getElementById('mrList');
  if (anim) { el.classList.remove('mr-list-anim'); void el.offsetWidth; el.classList.add('mr-list-anim'); }
  if (!list.length) {
    el.innerHTML=`<div class="mr-empty"><i class="fas fa-search"></i><p>${TH?'ไม่พบสารเคมี':'No chemicals found'}</p></div>`; return;
  }
  if      (S.view==='table')   renderTable(list,el,anim);
  else if (S.view==='grouped') renderGrouped(list,el,anim);
  else                         renderCards(list,el,anim);
}

/* ─────────── TABLE ─────────── */
function renderTable(list,el,anim=false) {
  const allSel = list.length>0 && list.every(c=>S.selected.has(c.id));
  const rows = list.map((c,idx)=>{
    const sel=S.selected.has(c.id), qty=parseFloat(c.current_quantity||0);
    const {n,init,avHtml}=ownerDisp(c);
    const rowAnimCls=anim?' mr-row-anim':'';
    const rowAnimStyle=anim?` style="animation-delay:${Math.min(idx,24)*16}ms"`:``;    
    return `<tr class="${sel?'sel':''}${rowAnimCls}"${rowAnimStyle} data-drag-row data-cid="${c.id}" draggable="true"
      ondragstart="containerDragStart(event,${c.id})" ondragend="containerDragEnd(event)">
      <td class="col-cb"><input type="checkbox" class="mr-cb${sel?' is-checked':''}" ${sel?'checked':''} onclick="toggleSel(${c.id},event,${idx})"></td>
      <td class="col-ic">${typeIc(c.container_type)}</td>
      <td><span class="mr-t-code">${escH(c.bottle_code||'—')}</span></td>
      <td>
        <div class="mr-t-chem">${escH(c.chem_name||'—')}${c.nickname?` <span style="color:var(--mr);font-size:10px">[${escH(c.nickname)}]</span>`:''}</div>
        <div class="mr-t-formula">${escH(c.molecular_formula||'')}${c.cas_number?` · CAS ${escH(c.cas_number)}`:''}</div>
      </td>
      <td><div class="mr-t-owner">${avHtml(20,8)}<span>${escH(n)}</span></div></td>
      <td style="white-space:nowrap">${qty>0?qty+' '+(c.quantity_unit||''):'—'}</td>
      <td style="color:var(--c3,#999);white-space:nowrap">${fmtDate(c.received_date)}</td>
      <td>${locBadge(c)}</td>
      <td style="color:var(--c3,#999);white-space:nowrap;font-size:11px">${fmtDate(c.expiry_date)}</td>
      <td>${expBadge(c)}</td>
      <td class="col-act">${rowActs(c)}</td>
    </tr>`;
  }).join('');
  el.innerHTML=`<div class="mr-tw"><table class="mr-t"><thead><tr>
    <th class="col-cb"><input type="checkbox" class="mr-cb${allSel?' is-checked':''}" ${allSel?'checked':''} onclick="toggleSelAll(event)"></th>
    <th></th><th>${TH?'รหัสขวด':'Code'}</th><th>${TH?'สารเคมี':'Chemical'}</th>
    <th>${TH?'เจ้าของ':'Owner'}</th><th>${TH?'คงเหลือ':'Qty'}</th>
    <th>${TH?'รับเข้า':'Received'}</th><th>${TH?'ตำแหน่ง':'Location'}</th>
    <th>${TH?'หมดอายุ':'Expiry'}</th><th>${TH?'สถานะ':'Status'}</th><th>${TH?'จัดการ':'Actions'}</th>
  </tr></thead><tbody>${rows}</tbody></table></div>`;
}

/* ─────────── CARDS ─────────── */
function renderCards(list,el,anim=false) {
  el.innerHTML='<div class="mr-grid">'+list.map((c,idx)=>{
    const sel=S.selected.has(c.id), qty=parseFloat(c.current_quantity||0);
    const ic=TYPE_IC[c.container_type]||'fa-box', col=TYPE_COL[c.container_type]||'#64748b';
    const {n,init,avHtml}=ownerDisp(c);
    const animCls = anim ? ' mr-card-anim' : '';
    const animDelay = anim ? ` style="animation-delay:${Math.min(idx,16)*38}ms"` : '';
    return `<div class="mr-card${sel?' sel':''}${animCls}"${animDelay} data-cid="${c.id}" draggable="true"
      ondragstart="containerDragStart(event,${c.id})" ondragend="containerDragEnd(event)">
      <div class="mr-card-cb"><input type="checkbox" class="mr-cb${sel?' is-checked':''}" ${sel?'checked':''} onclick="toggleSel(${c.id},event,${idx})"></div>
      <div class="mr-card-hd" style="padding-left:34px">
        <div class="type-icon" style="background:${col}18;color:${col};width:38px;height:38px;border-radius:10px"><i class="fas ${ic}"></i></div>
        <div class="mr-card-nm">
          <span class="mr-card-code">${escH(c.bottle_code||'—')}</span>
          <div class="mr-card-chem">${escH(c.chem_name||'—')}</div>
          <div class="mr-card-formula">${escH(c.molecular_formula||'')}${c.cas_number?` · ${escH(c.cas_number)}`:''}</div>
        </div>
        <div style="flex-shrink:0">${expBadge(c)}</div>
      </div>
      <div class="mr-card-bd">
        ${c.nickname?`<div style="color:var(--mr);font-size:11px;font-weight:600;display:flex;align-items:center;gap:4px"><i class="fas fa-tag" style="font-size:9px"></i>${escH(c.nickname)}</div>`:''}
        ${c.room_note?`<div style="font-size:11px;color:var(--c3,#999);font-style:italic;line-height:1.4">${escH(c.room_note)}</div>`:''}
        <div class="mr-card-row"><i class="fas fa-user"></i>${avHtml(16,7)}<span>${escH(n)}</span></div>
        <div class="mr-card-row"><i class="fas fa-flask"></i><span style="font-weight:600">${qty>0?qty+' '+(c.quantity_unit||''):(TH?'ไม่ระบุ':'N/A')}</span></div>
        ${c.received_date?`<div class="mr-card-row"><i class="fas fa-calendar-check"></i><span>${fmtDate(c.received_date)}</span></div>`:''}
        <div class="mr-card-row"><i style="opacity:0"></i>${locBadge(c)}</div>
        <div class="mr-card-act">
          ${CAN_EDIT?`<button class="mr-act-btn" onclick="openPlaceModal(${c.id})"><i class="fas fa-map-pin"></i></button>`:''}
          ${CAN_EDIT?`<button class="mr-act-btn" onclick="openNoteModal(${c.id})"><i class="fas fa-sticky-note"></i></button>`:''}
          <button class="mr-act-btn txn" onclick="openTxnModal(${c.id})"><i class="fas fa-exchange-alt"></i> ${TH?'ธุรกรรม':'Txn'}</button>
        </div>
      </div>
    </div>`;
  }).join('')+'</div>';
}

/* ─────────── GROUPED ─────────── */
function renderGrouped(list,el,anim=false) {
  // save which groups are currently open before wiping DOM
  const openIds=new Set([...el.querySelectorAll('.mr-grp.open')].map(n=>n.id));

  const groups=new Map();
  list.forEach(c=>{
    const k=c.chemical_id?'i'+c.chemical_id:'n'+(c.chem_name||'?');
    if(!groups.has(k)) groups.set(k,{name:c.chem_name||'—',formula:c.molecular_formula||'',cas:c.cas_number||'',signal:c.signal_word||'',items:[]});
    groups.get(k).items.push(c);
  });
  let html='<div class="mr-groups">', gi=0;
  groups.forEach(grp=>{
    const qty=grp.items.reduce((s,c)=>s+parseFloat(c.current_quantity||0),0);
    const unit=grp.items[0]?.quantity_unit||'';
    const ic=TYPE_IC[grp.items[0]?.container_type]||'fa-flask', col=TYPE_COL[grp.items[0]?.container_type]||'#64748b';
    const sCol={Danger:'#dc2626',Warning:'#f59e0b'}[grp.signal]||'#94a3b8';
    const gid='mrg'+gi;
    const rows=grp.items.map((c,ridx)=>{
      const sel=S.selected.has(c.id), q=parseFloat(c.current_quantity||0);
      const {n,init,avHtml}=ownerDisp(c);
      const overallIdx=getFiltered().findIndex(x=>x.id===c.id);
      return `<div class="mr-grp-row${sel?' sel':''}" data-cid="${c.id}">
        <input type="checkbox" class="mr-cb${sel?' is-checked':''}" style="flex-shrink:0" ${sel?'checked':''} onclick="toggleSel(${c.id},event,${overallIdx})">
        <span class="mr-grp-code">${escH(c.bottle_code||'—')}</span>
        <div class="mr-grp-loc">
          ${c.nickname?`<span style="color:var(--mr);font-weight:600;font-size:11px">${escH(c.nickname)}</span>`:''}
          ${locBadge(c)}
        </div>
        <div class="mr-grp-owner">${avHtml(16,7)}${escH(n)}</div>
        <span style="font-size:11px;color:var(--c2,#666);white-space:nowrap">${q>0?q+' '+unit:'—'}</span>
        ${expBadge(c)}
        <div class="mr-grp-actions">${rowActs(c)}</div>
      </div>`;
    }).join('');
    const grpDelay=anim?Math.min(gi,11)*48:0;
    const grpAnimCls=anim?' mr-grp-anim':'';
    html+=`<div class="mr-grp${openIds.has(gid)?' open':''}${grpAnimCls}" style="animation-delay:${grpDelay}ms" id="${gid}">
      <div class="mr-grp-hdr" onclick="toggleGrp('${gid}')">
        <div class="mr-grp-toggle"><i class="fas fa-chevron-right"></i></div>
        <div class="mr-grp-ic" style="background:${col}18;color:${col}"><i class="fas ${ic}"></i></div>
        <div class="mr-grp-info">
          <div class="mr-grp-name">${escH(grp.name)}</div>
          <div class="mr-grp-sub">
            ${grp.formula?`<span>${escH(grp.formula)}</span>`:''}
            ${grp.cas?`<span>CAS ${escH(grp.cas)}</span>`:''}
            ${grp.signal&&grp.signal!=='No signal word'?`<span style="color:${sCol};font-weight:700">${escH(grp.signal)}</span>`:''}
          </div>
        </div>
        <div class="mr-grp-meta">
          ${qty>0?`<span class="mr-grp-qty">${qty.toFixed(2)} ${unit}</span>`:''}
          <span class="mr-grp-cnt">${grp.items.length} ${TH?'ขวด':'btl'}</span>
        </div>
      </div>
      <div class="mr-grp-body">${rows}</div>
    </div>`;
    gi++;
  });
  el.innerHTML=html+'</div>';
}
function toggleGrp(id){ document.getElementById(id)?.classList.toggle('open'); }

/* ── Selection ── */
function toggleSel(id,e,rowIdx) {
  e.preventDefault();
  e.stopPropagation();

  if (e.shiftKey && S.lastIdx>=0) {
    // shift-click: range → need full re-render for bulk update
    const list=getFiltered(), from=Math.min(S.lastIdx,rowIdx), to=Math.max(S.lastIdx,rowIdx);
    for(let i=from;i<=to;i++) if(list[i]) S.selected.add(list[i].id);
    S.lastIdx=rowIdx; updateBatchBar(); renderContainers();
    return;
  }

  // single click → direct DOM update (no full re-render → groups stay open, cards stay put)
  const nowSel = S.selected.has(id) ? (S.selected.delete(id), false) : (S.selected.add(id), true);
  S.lastIdx=rowIdx;
  updateBatchBar();

  // 1. update the clicked checkbox
  const cb = e.target;
  cb.classList.toggle('is-checked', nowSel);
  cb.checked = nowSel;

  // 2. highlight the parent row / card
  cb.closest('.mr-card')?.classList.toggle('sel', nowSel);
  cb.closest('tr')?.classList.toggle('sel', nowSel);
  cb.closest('.mr-grp-row')?.classList.toggle('sel', nowSel);

  // 3. sync the table select-all header checkbox
  const filtered=getFiltered();
  const allSel=filtered.length>0&&filtered.every(c=>S.selected.has(c.id));
  const allCb=document.querySelector('th.col-cb .mr-cb');
  if(allCb){ allCb.classList.toggle('is-checked',allSel); allCb.checked=allSel; }
}

function toggleSelAll(e) {
  e.preventDefault();
  const list=getFiltered();
  const allSel=list.length>0&&list.every(c=>S.selected.has(c.id));
  list.forEach(c=>allSel?S.selected.delete(c.id):S.selected.add(c.id));
  updateBatchBar(); renderContainers();
}
function clearSelection(){ S.selected.clear(); S.lastIdx=-1; updateBatchBar(); renderContainers(); }
function updateBatchBar(){
  const n=S.selected.size;
  document.getElementById('batchBar').style.display=n?'':'none';
  document.getElementById('bbCount').textContent=n;
}

/* ── Batch Place ── */
function openBatchPlace(){
  if(!S.selected.size) return;
  const list=S.containers.filter(c=>S.selected.has(c.id));
  document.getElementById('batchPlaceList').innerHTML=list.map(c=>
    `<div class="mr-bpl-item"><i class="fas fa-flask" style="color:var(--c3,#999)"></i>
     <span class="mr-bpl-code">${escH(c.bottle_code||'—')}</span>
     <span class="mr-bpl-chem">${escH(c.chem_name||'')}</span></div>`
  ).join('');
  const cs=document.getElementById('bpCabinet');
  cs.innerHTML=`<option value="">${TH?'— ไม่จัดเก็บ (Unplaced) —':'— Unplaced —'}</option>`+
    S.cabinets.map(c=>`<option value="${c.id}">${escH(c.code||'')} ${escH(c.name)}</option>`).join('');
  document.getElementById('bpShelf').innerHTML=`<option value="">${TH?'— เลือกชั้น —':'— Select shelf —'}</option>`;
  document.getElementById('bpSlot').innerHTML=`<option value="">${TH?'— เลือกช่อง —':'— Select slot —'}</option>`;
  document.getElementById('bpProgress').style.display='none';
  openModal('modalBatchPlace');
}
document.getElementById('bpCabinet')?.addEventListener('change',function(){
  const cabId=parseInt(this.value||0), ss=document.getElementById('bpShelf');
  ss.innerHTML=`<option value="">${TH?'— เลือกชั้น —':'— Select shelf —'}</option>`;
  if(cabId){ const c=S.cabinets.find(x=>parseInt(x.id)===cabId); c?.shelves?.forEach(s=>{ss.innerHTML+=`<option value="${s.id}">${escH(s.code||'')} ${escH(s.name)}</option>`;});}
  document.getElementById('bpSlot').innerHTML=`<option value="">${TH?'— เลือกช่อง —':'— Select slot —'}</option>`;
});
document.getElementById('bpShelf')?.addEventListener('change',function(){
  const shId=parseInt(this.value||0), cabId=parseInt(document.getElementById('bpCabinet').value||0);
  const ss=document.getElementById('bpSlot');
  ss.innerHTML=`<option value="">${TH?'— เลือกช่อง —':'— Select slot —'}</option>`;
  if(shId&&cabId){ const c=S.cabinets.find(x=>parseInt(x.id)===cabId), sh=c?.shelves?.find(s=>parseInt(s.id)===shId); sh?.slots?.forEach(sl=>{ss.innerHTML+=`<option value="${sl.id}">${escH(sl.code||'')} ${escH(sl.name)}</option>`;});}
});
async function submitBatchPlace(){
  const cabinet_id=parseInt(document.getElementById('bpCabinet').value||0)||null;
  const shelf_id=parseInt(document.getElementById('bpShelf').value||0)||null;
  const slot_id=parseInt(document.getElementById('bpSlot').value||0)||null;
  const ids=[...S.selected]; if(!ids.length) return;
  const btn=document.getElementById('btnBatchPlace'); btn.disabled=true;
  document.getElementById('bpProgress').style.display='';
  let ok=0,fail=0;
  for(const cid of ids){
    const r=await apiFetch(API+'?action=place_container',{method:'POST',body:JSON.stringify({container_id:cid,room_id:S.activeRoomId,cabinet_id,shelf_id,slot_id})});
    r.success?ok++:fail++;
  }
  btn.disabled=false; closeModal('modalBatchPlace'); clearSelection();
  showToast(`${TH?'บันทึกแล้ว':'Saved'} ${ok} ${TH?'รายการ':'items'}${fail?` (${fail} ${TH?'ล้มเหลว':'failed'})`:''}`,fail?'err':'ok');
  await selectRoom(S.activeRoomId);
}

/* ── Filter/Search events ── */
document.getElementById('mrSearch').addEventListener('input',e=>{ S.search=e.target.value; renderContainers(); });

/* ── Cabinet CRUD ── */
function openAddCabinet(){ document.getElementById('addCabRoomId').value=S.activeRoomId; document.getElementById('addCabName').value=''; document.getElementById('addCabCode').value=''; document.getElementById('addCabType').value='storage'; openModal('modalAddCabinet'); }
async function submitAddCabinet(){
  const name=document.getElementById('addCabName').value.trim(); if(!name){showToast(TH?'กรุณากรอกชื่อตู้':'Enter cabinet name','err');return;}
  const btn=document.getElementById('btnAddCab'); btn.disabled=true;
  const r=await apiFetch(API+'?action=add_cabinet',{method:'POST',body:JSON.stringify({room_id:S.activeRoomId,name,code:document.getElementById('addCabCode').value.trim(),type:document.getElementById('addCabType').value})});
  btn.disabled=false; if(!r.success){showToast(r.error||'Error','err');return;}
  showToast(TH?'เพิ่มตู้เรียบร้อย':'Cabinet added','ok'); closeModal('modalAddCabinet'); await selectRoom(S.activeRoomId);
}
/* ── Danger Confirm Popup ── */
let _mrDelResolve = null;
function _confirmDel({ title, target, targetIc='fa-box', warn, chips=[], btnLabel }) {
  return new Promise(resolve => {
    _mrDelResolve = (v) => {
      document.getElementById('mrDelOv').classList.remove('show');
      _mrDelResolve = null;
      resolve(v);
    };
    const chipsHtml = chips.length
      ? `<div class="mr-del-chips">${chips.map(c=>`<span class="mr-del-chip info"><i class="fas fa-info-circle"></i>${c.label}</span>`).join('')}</div>`
      : '';
    const warnHtml = warn
      ? `<div class="mr-del-warn">
           <i class="fas fa-exclamation-triangle"></i>
           <div>
             <div style="font-weight:700;margin-bottom:4px;font-size:12px;color:#9a3412">${warn.text}</div>
             <div class="mr-del-dest">
               <i class="fas fa-arrow-right" style="font-size:10px;color:#f97316"></i>
               <i class="fas ${warn.destIc}" style="font-size:11px;color:#ea580c"></i>
               <span>${warn.dest}</span>
               <span class="mr-del-cont-badge">${warn.cont} ${TH?'รายการ':'item(s)'}</span>
             </div>
           </div>
         </div>`
      : '';
    document.getElementById('mrDelBox').innerHTML = `
      <div class="mr-del-top">
        <div class="mr-del-ic-wrap danger"><i class="mr-del-ic fas fa-trash-alt"></i></div>
        <div class="mr-del-title">${title}</div>
        <div class="mr-del-sub">${TH?'รายการที่จะถูกลบ':'Item to delete'}</div>
        <div class="mr-del-target"><i class="fas ${targetIc}"></i>${target}</div>
      </div>
      ${chipsHtml}
      ${warnHtml}
      <div class="mr-del-footer">
        <button class="mr-del-btn-del" onclick="_mrDelResolve(true)">
          <i class="fas fa-trash-alt"></i> ${btnLabel}
        </button>
        <button class="mr-del-btn-cancel" onclick="_mrDelResolve(false)">
          ${TH?'ยกเลิก':'Cancel'}
        </button>
      </div>`;
    document.getElementById('mrDelOv').classList.add('show');
  });
}

async function deleteCabinet(id) {
  const cab = S.cabinets.find(x=>parseInt(x.id)===id); if(!cab) return;
  const shelfCnt = cab.shelves?.length || 0;
  const slotCnt  = (cab.shelves||[]).reduce((s,sh)=>s+(sh.slots?.length||0),0);
  const contCnt  = parseInt(cab.container_count||0);
  const chips = [
    shelfCnt > 0 ? {label:`${shelfCnt} ${TH?'ชั้น':'shelf'}`, warn:false} : null,
    slotCnt  > 0 ? {label:`${slotCnt} ${TH?'ช่อง':'slot'}`,  warn:false} : null,
  ].filter(Boolean);
  const ok = await _confirmDel({
    title:    TH ? 'ลบตู้เก็บสาร' : 'Delete Cabinet',
    target:   escH(cab.name || cab.code || ''),
    targetIc: 'fa-cabinet-filing',
    warn:     contCnt > 0
      ? {text: TH ? `ขวดสาร ${contCnt} รายการจะถูกย้ายไปยัง "ยังไม่จัดเก็บ" โดยอัตโนมัติ`
                  : `${contCnt} container(s) will be moved to "Unplaced" automatically.`,
         cont: contCnt, dest: TH?'ยังไม่จัดเก็บ':'Unplaced', destIc:'fa-inbox'}
      : null,
    chips,
    btnLabel: TH ? 'ลบตู้เลย' : 'Delete Cabinet',
  });
  if (!ok) return;
  const r = await apiFetch(API+'?action=delete_cabinet',{method:'POST',body:JSON.stringify({id})});
  if (!r.success) { showToast(r.error||'Error','err'); return; }
  const movedMsg = r.data?.moved > 0 ? ` · ${TH?'ย้าย':'moved'} ${r.data.moved} ${TH?'รายการ':'item(s)'}` : '';
  showToast((TH?'ลบตู้เรียบร้อย':'Cabinet deleted')+movedMsg, 'ok'); await selectRoom(S.activeRoomId);
}

/* ── Shelf CRUD ── */
function openAddShelf(cid){ document.getElementById('addShelfCabId').value=cid; document.getElementById('addShelfName').value=''; document.getElementById('addShelfLevel').value=''; openModal('modalAddShelf'); }
async function submitAddShelf(){
  const name=document.getElementById('addShelfName').value.trim(); if(!name){showToast(TH?'กรุณากรอกชื่อชั้น':'Enter shelf name','err');return;}
  const btn=document.getElementById('btnAddShelf'); btn.disabled=true;
  const r=await apiFetch(API+'?action=add_shelf',{method:'POST',body:JSON.stringify({cabinet_id:parseInt(document.getElementById('addShelfCabId').value),name,level:parseInt(document.getElementById('addShelfLevel').value||0)})});
  btn.disabled=false; if(!r.success){showToast(r.error||'Error','err');return;}
  showToast(TH?'เพิ่มชั้นเรียบร้อย':'Shelf added','ok'); closeModal('modalAddShelf'); await selectRoom(S.activeRoomId);
}
async function deleteShelf(id) {
  let shelf = null;
  for (const cab of S.cabinets) { shelf = (cab.shelves||[]).find(s=>parseInt(s.id)===id); if(shelf) break; }
  if (!shelf) return;
  let shelfCab = null;
  for (const cab of S.cabinets) { if((cab.shelves||[]).find(s=>parseInt(s.id)===id)){shelfCab=cab;break;} }
  const slotCnt = shelf.slots?.length || 0;
  const contCnt = parseInt(shelf.container_count || 0);
  const destName = shelfCab ? escH(shelfCab.name||shelfCab.code||'') : (TH?'ยังไม่จัดเก็บ':'Unplaced');
  const chips = [
    slotCnt > 0 ? {label:`${slotCnt} ${TH?'ช่อง':'slot'}`, warn:false} : null,
  ].filter(Boolean);
  const ok = await _confirmDel({
    title:    TH ? 'ลบชั้นวางสาร' : 'Delete Shelf',
    target:   escH(shelf.name || shelf.code || ''),
    targetIc: 'fa-layer-group',
    warn:     contCnt > 0
      ? {text: TH ? `ขวดสาร ${contCnt} รายการจะถูกย้ายไปยังตู้ "${destName}" (ระดับตู้) โดยอัตโนมัติ`
                  : `${contCnt} container(s) will be moved up to cabinet "${destName}" automatically.`,
         cont: contCnt, dest: destName, destIc:'fa-cabinet-filing'}
      : null,
    chips,
    btnLabel: TH ? 'ลบชั้นเลย' : 'Delete Shelf',
  });
  if (!ok) return;
  const r = await apiFetch(API+'?action=delete_shelf',{method:'POST',body:JSON.stringify({id})});
  if (!r.success) { showToast(r.error||'Error','err'); return; }
  const movedMsg = r.data?.moved > 0 ? ` · ${TH?'ย้าย':'moved'} ${r.data.moved} ${TH?'รายการ':'item(s)'}` : '';
  showToast((TH?'ลบชั้นเรียบร้อย':'Shelf deleted')+movedMsg, 'ok'); await selectRoom(S.activeRoomId);
}

/* ── Slot CRUD ── */
function openAddSlot(sid){ document.getElementById('addSlotShelfId').value=sid; document.getElementById('addSlotName').value=''; document.getElementById('addSlotPos').value=''; openModal('modalAddSlot'); }
async function submitAddSlot(){
  const name=document.getElementById('addSlotName').value.trim(); if(!name){showToast(TH?'กรุณากรอกชื่อช่อง':'Enter slot name','err');return;}
  const btn=document.getElementById('btnAddSlot'); btn.disabled=true;
  const r=await apiFetch(API+'?action=add_slot',{method:'POST',body:JSON.stringify({shelf_id:parseInt(document.getElementById('addSlotShelfId').value),name,position:parseInt(document.getElementById('addSlotPos').value||0)})});
  btn.disabled=false; if(!r.success){showToast(r.error||'Error','err');return;}
  showToast(TH?'เพิ่มช่องเรียบร้อย':'Slot added','ok'); closeModal('modalAddSlot'); await selectRoom(S.activeRoomId);
}
async function deleteSlot(id) {
  let slot = null, slotShelf = null;
  for (const cab of S.cabinets) for (const sh of (cab.shelves||[])) {
    const found = (sh.slots||[]).find(sl=>parseInt(sl.id)===id);
    if (found) { slot=found; slotShelf=sh; break; }
  }
  if (!slot) return;
  const contCnt = parseInt(slot.container_count || 0);
  const destName = slotShelf ? escH(slotShelf.name||slotShelf.code||'') : '';
  const ok = await _confirmDel({
    title:    TH ? 'ลบช่องเก็บสาร' : 'Delete Slot',
    target:   escH(slot.name || slot.code || ''),
    targetIc: 'fa-box',
    warn:     contCnt > 0
      ? {text: TH ? `ขวดสาร ${contCnt} รายการจะถูกย้ายไปยังชั้น "${destName}" (ระดับชั้น) โดยอัตโนมัติ`
                  : `${contCnt} container(s) will be moved up to shelf "${destName}" automatically.`,
         cont: contCnt, dest: destName, destIc:'fa-layer-group'}
      : null,
    chips:    [],
    btnLabel: TH ? 'ลบช่องเลย' : 'Delete Slot',
  });
  if (!ok) return;
  const r = await apiFetch(API+'?action=delete_slot',{method:'POST',body:JSON.stringify({id})});
  if (!r.success) { showToast(r.error||'Error','err'); return; }
  const movedMsg = r.data?.moved > 0 ? ` · ${TH?'ย้าย':'moved'} ${r.data.moved} ${TH?'รายการ':'item(s)'}` : '';
  showToast((TH?'ลบช่องเรียบร้อย':'Slot deleted')+movedMsg, 'ok'); await selectRoom(S.activeRoomId);
}

/* ── Place Modal ── */
function openPlaceModal(id){
  const c=S.containers.find(x=>parseInt(x.id)===id); if(!c) return;
  document.getElementById('placeContInfo').innerHTML=`<div class="mr-mini-card"><b>${escH(c.bottle_code)}</b> — ${escH(c.chem_name||'')}</div>`;
  document.getElementById('placeContId').value=id;
  const cs=document.getElementById('placeCabinet');
  cs.innerHTML=`<option value="">${TH?'— ไม่จัดเก็บ (Unplaced) —':'— Unplaced —'}</option>`+
    S.cabinets.map(cab=>`<option value="${cab.id}"${parseInt(c.cabinet_id)===parseInt(cab.id)?' selected':''}>${escH(cab.code||'')} ${escH(cab.name)}</option>`).join('');
  cs.dispatchEvent(new Event('change')); openModal('modalPlace');
}
document.getElementById('placeCabinet')?.addEventListener('change',function(){
  const cabId=parseInt(this.value||0), shSel=document.getElementById('placeShelf');
  const c=S.containers.find(x=>parseInt(x.id)===parseInt(document.getElementById('placeContId').value||0));
  shSel.innerHTML=`<option value="">${TH?'— เลือกชั้น —':'— Select shelf —'}</option>`;
  if(cabId){ const cab=S.cabinets.find(x=>parseInt(x.id)===cabId); cab?.shelves?.forEach(s=>{const sel=c&&parseInt(c.cabinet_id)===cabId&&parseInt(c.shelf_id)===parseInt(s.id)?'selected':''; shSel.innerHTML+=`<option value="${s.id}" ${sel}>${escH(s.code||'')} ${escH(s.name)}</option>`;});}
  shSel.dispatchEvent(new Event('change'));
});
document.getElementById('placeShelf')?.addEventListener('change',function(){
  const shId=parseInt(this.value||0), cabId=parseInt(document.getElementById('placeCabinet').value||0);
  const c=S.containers.find(x=>parseInt(x.id)===parseInt(document.getElementById('placeContId').value||0));
  const slSel=document.getElementById('placeSlot');
  slSel.innerHTML=`<option value="">${TH?'— เลือกช่อง —':'— Select slot —'}</option>`;
  if(shId&&cabId){ const cab=S.cabinets.find(x=>parseInt(x.id)===cabId), sh=cab?.shelves?.find(s=>parseInt(s.id)===shId); sh?.slots?.forEach(sl=>{const sel=c&&parseInt(c.slot_id)===parseInt(sl.id)?'selected':''; slSel.innerHTML+=`<option value="${sl.id}" ${sel}>${escH(sl.code||'')} ${escH(sl.name)}</option>`;});}
});
async function submitPlace(){
  const id=parseInt(document.getElementById('placeContId').value);
  const cabinet_id=parseInt(document.getElementById('placeCabinet').value||0)||null;
  const shelf_id=parseInt(document.getElementById('placeShelf').value||0)||null;
  const slot_id=parseInt(document.getElementById('placeSlot').value||0)||null;
  const btn=document.getElementById('btnPlace'); btn.disabled=true;
  const r=await apiFetch(API+'?action=place_container',{method:'POST',body:JSON.stringify({container_id:id,room_id:S.activeRoomId,cabinet_id,shelf_id,slot_id})});
  btn.disabled=false; if(!r.success){showToast(r.error||'Error','err');return;}
  showToast(TH?'บันทึกตำแหน่งเรียบร้อย':'Location saved','ok'); closeModal('modalPlace'); await selectRoom(S.activeRoomId);
}

/* ── Note Modal ── */
function openNoteModal(id){
  const c=S.containers.find(x=>parseInt(x.id)===id); if(!c) return;
  document.getElementById('noteContInfo').innerHTML=`<div class="mr-mini-card"><b>${escH(c.bottle_code)}</b> — ${escH(c.chem_name||'')}</div>`;
  document.getElementById('noteContId').value=id;
  document.getElementById('noteNickname').value=c.nickname||'';
  document.getElementById('noteText').value=c.room_note||'';
  openModal('modalNote');
}
async function submitNote(){
  const id=parseInt(document.getElementById('noteContId').value);
  const nickname=document.getElementById('noteNickname').value.trim(), notes=document.getElementById('noteText').value.trim();
  const btn=document.getElementById('btnNote'); btn.disabled=true;
  const r=await apiFetch(API+'?action=save_note',{method:'POST',body:JSON.stringify({room_id:S.activeRoomId,container_id:id,nickname,notes})});
  btn.disabled=false; if(!r.success){showToast(r.error||'Error','err');return;}
  showToast(TH?'บันทึก Note เรียบร้อย':'Note saved','ok'); closeModal('modalNote');
  const c=S.containers.find(x=>parseInt(x.id)===id); if(c){c.nickname=nickname;c.room_note=notes;}
  renderContainers();
}

/* ── Borrow Modal ── */
function openBorrowModal(id){
  const c=S.containers.find(x=>parseInt(x.id)===id); if(!c) return;
  document.getElementById('borContInfo').innerHTML=`<div class="mr-mini-card"><b>${escH(c.bottle_code)}</b> — ${escH(c.chem_name||'')}
    <span style="float:right;color:#94a3b8;font-size:10px">${c.current_quantity||''}${c.quantity_unit?' '+c.quantity_unit:''}</span></div>`;
  document.getElementById('borContId').value=id; document.getElementById('borQty').value='';
  document.getElementById('borUnit').value=c.quantity_unit||'mL'; document.getElementById('borPurpose').value='';
  openModal('modalBorrow');
}
async function submitBorrow(){
  const id=parseInt(document.getElementById('borContId').value);
  const quantity=parseFloat(document.getElementById('borQty').value||0);
  const unit=document.getElementById('borUnit').value, purpose=document.getElementById('borPurpose').value.trim();
  if(!purpose){showToast(TH?'กรุณาระบุวัตถุประสงค์':'Enter purpose','err');return;}
  const btn=document.getElementById('btnBorrow'); btn.disabled=true;
  const r=await apiFetch(API+'?action=borrow_request',{method:'POST',body:JSON.stringify({container_id:id,quantity,unit,purpose})});
  btn.disabled=false; if(!r.success){showToast(r.error||'Error','err');return;}
  showToast(TH?'ส่งคำขอยืมเรียบร้อย':'Borrow request sent','ok'); closeModal('modalBorrow');
}

/* ── Helpers ── */
function openModal(id)  { document.getElementById(id)?.classList.add('show'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('show'); }
function escH(s){ if(!s)return''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function showToast(msg,type='ok'){
  const t=document.createElement('div'); t.className='mr-toast '+type;
  t.innerHTML=`<i class="fas fa-${type==='ok'?'check-circle':'exclamation-circle'}"></i> ${escH(msg)}`;
  document.getElementById('mrToast').appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translateY(-10px)'; setTimeout(()=>t.remove(),350); },3000);
}
document.addEventListener('keydown',e=>{
  if(e.key==='Escape') {
    if(S.renaming){cancelRename();return;}
    if(document.getElementById('mrDrawer')?.classList.contains('open')){closeDrawer();return;}
    ['modalAddCabinet','modalAddShelf','modalAddSlot','modalNote','modalPlace','modalBatchPlace','modalBorrow','modalAdmins','modalTxn','modalRpt'].forEach(closeModal);
  }
});

/* ── Avatar helper ── */
function avatarHtml(u, size=36, fontSize=13) {
  const name=[u.first_name||'',u.last_name||''].join(' ').trim()||u.email||'?';
  const init=name.replace(/^(นาย|นางสาว|นาง|ดร\.)\s*/u,'').charAt(0).toUpperCase()||'?';
  const style=`width:${size}px;height:${size}px;border-radius:50%;flex-shrink:0;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:${fontSize}px`;
  if(u.avatar_url)
    return `<div style="${style};background:var(--mrbg)"><img src="${escH(u.avatar_url)}" style="width:100%;height:100%;object-fit:cover" alt="${escH(init)}"></div>`;
  return `<div style="${style};background:var(--mr);color:#fff">${escH(init)}</div>`;
}

/* ── Room Admin Management ── */
async function openManageAdmins() {
  if(!S.activeRoomId){showToast(TH?'เลือกห้องก่อน':'Select a room first','err');return;}
  document.getElementById('adminsList').innerHTML=`<div style="text-align:center;padding:16px;color:var(--c3,#999)"><i class="fas fa-circle-notch fa-spin"></i></div>`;
  document.getElementById('adminSearch').value='';
  document.getElementById('adminSearchResults').innerHTML='';
  openModal('modalAdmins');
  const r=await apiFetch(API+'?action=get_room_admins&room_id='+S.activeRoomId);
  if(!r.success){showToast(r.error||'Error','err');return;}
  S.roomAdmins=r.data||[];
  renderAdminsList();
}
function renderAdminsList() {
  const el=document.getElementById('adminsList');
  if(!S.roomAdmins.length){
    el.innerHTML=`<div class="rm-empty-results">${TH?'ยังไม่มีผู้ดูแล':'No admins assigned'}</div>`;return;
  }
  el.innerHTML=S.roomAdmins.map(u=>{
    const name=[u.first_name||'',u.last_name||''].join(' ').trim()||u.email||'User';
    const isPrimary=parseInt(u.is_primary)===1;
    const canRemove=!isPrimary&&parseInt(u.id)!==CURRENT_UID;
    return `<div class="rm-admin-row">
      ${avatarHtml(u,36,13)}
      <div class="rm-admin-info">
        <div class="rm-admin-name">${escH(name)}</div>
        <div class="rm-admin-role">${escH(u.email||'')}</div>
      </div>
      <span class="rm-admin-badge ${isPrimary?'primary':'co'}">${isPrimary?(TH?'ผู้ดูแลหลัก':'Primary'):(TH?'ผู้ดูแลร่วม':'Co-Admin')}</span>
      ${canRemove?`<button class="rm-admin-rm" onclick="removeRoomAdmin(${u.id})" title="${TH?'ลบออก':'Remove'}"><i class="fas fa-times"></i></button>`:''}
    </div>`;
  }).join('');
}
let _adminSearchTm=null;
function searchUsersToAdd() {
  clearTimeout(_adminSearchTm);
  _adminSearchTm=setTimeout(async()=>{
    const q=document.getElementById('adminSearch').value.trim();
    const res=document.getElementById('adminSearchResults');
    if(q.length<2){res.innerHTML='';return;}
    res.innerHTML=`<div class="rm-empty-results"><i class="fas fa-circle-notch fa-spin"></i></div>`;
    const r=await apiFetch(API+'?action=search_users&q='+encodeURIComponent(q));
    if(!r.success){res.innerHTML='';return;}
    const existing=new Set(S.roomAdmins.map(a=>parseInt(a.id)));
    const list=(r.data||[]).filter(u=>!existing.has(parseInt(u.id)));
    if(!list.length){res.innerHTML=`<div class="rm-empty-results">${TH?'ไม่พบผู้ใช้ที่ยังไม่ได้เป็นผู้ดูแล':'No eligible users found'}</div>`;return;}
    res.innerHTML=`<div class="rm-results">`+list.map(u=>{
      const name=[u.first_name||'',u.last_name||''].join(' ').trim()||u.email||'User';
      return `<div class="rm-result-row" onclick="addRoomAdmin(${u.id},'${escH(name)}')">
        ${avatarHtml(u,28,10)}
        <div><div style="font-weight:600">${escH(name)}</div><div style="font-size:10px;color:var(--c3,#999)">${escH(u.email||'')}</div></div>
        <div style="margin-left:auto"><i class="fas fa-plus" style="color:var(--mr)"></i></div>
      </div>`;
    }).join('')+'</div>';
  },300);
}
async function addRoomAdmin(userId, name) {
  const r=await apiFetch(API+'?action=add_room_admin',{method:'POST',body:JSON.stringify({room_id:S.activeRoomId,user_id:userId})});
  if(!r.success){showToast(r.error||'Error','err');return;}
  showToast((TH?'เพิ่ม ':'Added ')+name+' '+(TH?'เป็นผู้ดูแลเรียบร้อย':'as room admin'),'ok');
  document.getElementById('adminSearch').value='';
  document.getElementById('adminSearchResults').innerHTML='';
  const r2=await apiFetch(API+'?action=get_room_admins&room_id='+S.activeRoomId);
  if(r2.success) { S.roomAdmins=r2.data||[]; renderAdminsList(); }
}
async function removeRoomAdmin(userId) {
  if(!confirm(TH?'ลบผู้ดูแลร่วมคนนี้ออก?':'Remove this co-admin?')) return;
  const r=await apiFetch(API+'?action=remove_room_admin',{method:'POST',body:JSON.stringify({room_id:S.activeRoomId,user_id:userId})});
  if(!r.success){showToast(r.error||'Error','err');return;}
  showToast(TH?'ลบเรียบร้อย':'Removed','ok');
  S.roomAdmins=S.roomAdmins.filter(u=>parseInt(u.id)!==parseInt(userId));
  renderAdminsList();
}

/* ── Container Detail Drawer ── */
function openContainerDetail(id) {
  const c = S.containers.find(x => parseInt(x.id) === parseInt(id));
  if (!c) return;
  const ic = TYPE_IC[c.container_type] || 'fa-box';
  const col = TYPE_COL[c.container_type] || '#64748b';
  const qty = parseFloat(c.current_quantity || 0);
  const {n} = ownerDisp(c);

  const iconEl = document.getElementById('drawerTypeIc');
  iconEl.style.cssText = `width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;background:${col}20;color:${col}`;
  iconEl.innerHTML = `<i class="fas ${ic}"></i>`;
  document.getElementById('drawerTitle').textContent = c.chem_name || c.bottle_code || '—';

  // hazard pictograms
  let hazardHtml = '';
  if (c.hazard_pictograms) {
    try {
      const pics = typeof c.hazard_pictograms === 'string' ? JSON.parse(c.hazard_pictograms) : c.hazard_pictograms;
      if (Array.isArray(pics) && pics.length) {
        const sCol = {Danger:'#dc2626',Warning:'#f59e0b'}[c.signal_word] || '#94a3b8';
        hazardHtml = `<div class="mr-drawer-sect">
          <div class="mr-drawer-sl">${TH?'สัญลักษณ์อันตราย':'Hazard Pictograms'}</div>
          <div style="display:flex;gap:5px;flex-wrap:wrap">
            ${pics.map(p=>`<span style="font-size:10px;padding:3px 8px;border-radius:5px;background:#fef2f2;color:#dc2626;font-weight:600;border:1px solid #fecaca">${escH(p)}</span>`).join('')}
          </div>
          ${c.signal_word&&c.signal_word!=='No signal word'?`<div style="margin-top:6px;font-size:11px;font-weight:700;color:${sCol}">${escH(c.signal_word)}</div>`:''}
        </div>`;
      }
    } catch(err) {}
  }

  // location path
  let locPath = '';
  if (c.cabinet_id) {
    locPath = [c.cabinet_name||c.cabinet_code, c.shelf_name||c.shelf_code, c.slot_name||c.slot_code].filter(Boolean).join(' › ');
  }

  document.getElementById('drawerBody').innerHTML = `
    <div class="mr-drawer-hero">
      <div style="flex:1;min-width:0">
        <div style="font-size:11px;font-weight:800;font-family:'Courier New',monospace;background:#fff;border:1px solid var(--mrbrd);color:var(--mrd);padding:3px 10px;border-radius:6px;display:inline-block;margin-bottom:6px">${escH(c.bottle_code||'—')}</div>
        <div style="font-size:15px;font-weight:800;color:var(--c1,#333);line-height:1.3">${escH(c.chem_name||'—')}</div>
        ${c.molecular_formula?`<div style="font-size:12px;color:var(--c3,#999);margin-top:2px">${escH(c.molecular_formula)}</div>`:''}
        ${c.nickname?`<div style="font-size:11px;color:var(--mr);font-weight:600;margin-top:5px;display:flex;align-items:center;gap:4px"><i class="fas fa-tag" style="font-size:9px"></i>${escH(c.nickname)}</div>`:''}
      </div>
      <div style="flex-shrink:0">${expBadge(c)}</div>
    </div>

    <div class="mr-drawer-sect">
      <div class="mr-drawer-sl">${TH?'ข้อมูลสาร':'Chemical Info'}</div>
      ${c.cas_number?`<div class="mr-drawer-kv"><span class="mr-drawer-k">CAS No.</span><span class="mr-drawer-v">${escH(c.cas_number)}</span></div>`:''}
      ${c.molecular_formula?`<div class="mr-drawer-kv"><span class="mr-drawer-k">${TH?'สูตรโมเลกุล':'Formula'}</span><span class="mr-drawer-v">${escH(c.molecular_formula)}</span></div>`:''}
      ${c.physical_state?`<div class="mr-drawer-kv"><span class="mr-drawer-k">${TH?'สถานะ':'State'}</span><span class="mr-drawer-v">${escH(c.physical_state)}</span></div>`:''}
    </div>

    <div class="mr-drawer-sect">
      <div class="mr-drawer-sl">${TH?'รายละเอียดภาชนะ':'Container Details'}</div>
      <div class="mr-drawer-kv"><span class="mr-drawer-k">${TH?'ประเภท':'Type'}</span><span class="mr-drawer-v">${escH(c.container_type||'—')}</span></div>
      <div style="padding:8px 0 4px">${qtyBar(c)}</div>
      <div class="mr-drawer-kv"><span class="mr-drawer-k">${TH?'วันรับเข้า':'Received'}</span><span class="mr-drawer-v">${fmtDate(c.received_date)}</span></div>
      <div class="mr-drawer-kv"><span class="mr-drawer-k">${TH?'วันหมดอายุ':'Expiry'}</span><span class="mr-drawer-v">${fmtDate(c.expiry_date)}</span></div>
    </div>

    <div class="mr-drawer-sect">
      <div class="mr-drawer-sl">${TH?'เจ้าของ':'Owner'}</div>
      <div style="display:flex;align-items:center;gap:10px;padding:4px 0">
        ${avatarHtml({first_name:c.owner_fn||'',last_name:c.owner_ln||'',email:'',avatar_url:c.owner_avatar_url||null},36,14)}
        <div>
          <div style="font-size:13px;font-weight:700;color:var(--c1,#333);line-height:1.2">${escH(n)}</div>
          ${parseInt(c.owner_id)===CURRENT_UID?`<div style="font-size:10px;color:var(--mr);font-weight:700;margin-top:2px"><i class="fas fa-crown" style="font-size:8px"></i> ${TH?'คุณเป็นเจ้าของ':'You own this'}</div>`:''}
        </div>
      </div>
    </div>

    <div class="mr-drawer-sect">
      <div class="mr-drawer-sl">${TH?'ตำแหน่งจัดเก็บ':'Storage Location'}</div>
      <div style="padding:4px 0">${locBadge(c)}</div>
      ${locPath?`<div style="margin-top:5px;font-size:11px;color:var(--c3,#999);line-height:1.5">${escH(locPath)}</div>`:''}
    </div>

    ${(c.room_note||c.container_notes)?`<div class="mr-drawer-sect">
      <div class="mr-drawer-sl">${TH?'บันทึก':'Notes'}</div>
      ${c.room_note?`<div style="font-size:12px;color:var(--c2,#666);line-height:1.5;background:var(--mrbg);padding:8px 10px;border-radius:7px;border:1px solid var(--mrbrd)">${escH(c.room_note)}</div>`:''}
      ${c.container_notes?`<div style="font-size:12px;color:var(--c2,#666);line-height:1.5;margin-top:6px">${escH(c.container_notes)}</div>`:''}
    </div>`:''}

    ${hazardHtml}
  `;

  document.getElementById('drawerFooter').innerHTML = `
    ${CAN_EDIT?`<button class="mr-drawer-act" onclick="closeDrawer();openPlaceModal(${c.id})"><i class="fas fa-map-pin"></i> ${TH?'ตำแหน่ง':'Location'}</button>`:''}
    ${CAN_EDIT?`<button class="mr-drawer-act" onclick="closeDrawer();openNoteModal(${c.id})"><i class="fas fa-sticky-note"></i> Note</button>`:''}
    <button class="mr-drawer-act txn" onclick="closeDrawer();openTxnModal(${c.id})"><i class="fas fa-exchange-alt"></i> ${TH?'ธุรกรรม':'Transaction'}</button>
    ${c.has_3d?`<a class="mr-drawer-act ar3d" href="/v1/ar/view_ar.php?id=${c.id}" target="_blank"><i class="fas fa-cube"></i> ดู 3D</a>`:''}
  `;

  document.getElementById('mrDrawerOv').classList.add('open');
  document.getElementById('mrDrawer').classList.add('open');
}

function closeDrawer() {
  document.getElementById('mrDrawerOv').classList.remove('open');
  document.getElementById('mrDrawer').classList.remove('open');
}

/* ── Quantity progress bar ── */
function qtyBar(c) {
  const cur  = parseFloat(c.current_quantity  || 0);
  const init = parseFloat(c.initial_quantity  || 0);
  const unit = c.quantity_unit || '';
  const pct  = init > 0 ? Math.min(100, cur / init * 100) : null;
  const pctInt = pct !== null ? Math.round(pct) : null;
  const col = pct === null ? '#94a3b8'
            : pct > 50    ? '#16a34a'
            : pct > 20    ? '#f59e0b'
            : '#ef4444';
  const barW = pctInt ?? (cur > 0 ? 100 : 0);
  return `<div>
    <div style="display:flex;align-items:center;gap:8px">
      <div style="flex:1;height:7px;background:#e2e8f0;border-radius:4px;overflow:hidden">
        <div style="height:100%;width:${barW}%;background:${col};border-radius:4px;transition:width .4s"></div>
      </div>
      ${pctInt !== null ? `<span style="font-size:11px;font-weight:800;color:${col};min-width:32px;text-align:right">${pctInt}%</span>` : ''}
    </div>
    <div style="font-size:10px;color:var(--c3,#999);margin-top:3px">${cur.toFixed(3)}${init > 0 ? '/'+init.toFixed(3) : ''} ${unit}</div>
  </div>`;
}

/* ── Transaction Modal ── */
let _txnCid = null, _txnType = null;

function openTxnModal(id) {
  _txnCid = parseInt(id); _txnType = null;
  const c = S.containers.find(x => parseInt(x.id) === _txnCid);
  if (!c) return;
  txnShowTiles(c);
  openModal('modalTxn');
}

function txnGoBack() { txnShowTiles(); }

function _txnMiniCard(c) {
  const ic=TYPE_IC[c.container_type]||'fa-box', col=TYPE_COL[c.container_type]||'#64748b';
  const isOwner=parseInt(c.owner_id)===CURRENT_UID;
  const badge=isOwner
    ?`<span class="mr-owner-badge is-owner"><i class="fas fa-crown" style="font-size:8px"></i> ${TH?'เจ้าของสาร':'Owner'}</span>`
    :`<span class="mr-owner-badge is-borrower"><i class="fas fa-user" style="font-size:9px"></i> ${TH?'ผู้ขอยืม':'Requester'}</span>`;
  return `<div class="mr-mini-card" style="margin-bottom:16px;padding:12px 14px">
    <div style="display:flex;align-items:flex-start;gap:10px">
      <div style="width:40px;height:40px;border-radius:10px;background:${col}20;color:${col};display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;margin-top:2px"><i class="fas ${ic}"></i></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;margin-bottom:2px">
          <div style="font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">${escH(c.chem_name||c.bottle_code||'—')}</div>
          ${badge}
        </div>
        <div style="font-size:10px;color:var(--c3,#999);margin-bottom:8px">${escH(c.bottle_code||'')}</div>
        ${qtyBar(c)}
      </div>
    </div>
  </div>`;
}

function txnShowTiles(c) {
  if (!c) c = S.containers.find(x => parseInt(x.id) === _txnCid);
  if (!c) return;
  const isOwner = parseInt(c.owner_id) === CURRENT_UID;
  document.getElementById('txnBtnBack').style.display = 'none';
  document.getElementById('txnBtnSubmit').style.display = 'none';

  let tiles = isOwner ? `<div class="mr-txn-tiles">
    <button class="mr-txn-tile t-withdraw" onclick="txnSelectType('withdraw')">
      <div class="tile-ic"><i class="fas fa-minus-circle"></i></div>
      <div class="tile-nm">${TH?'เบิกใช้':'Withdraw'}</div>
      <div class="tile-ds">${TH?'บันทึกการนำออกใช้/ลดปริมาณ':'Record usage / deduct quantity'}</div>
    </button>
    <button class="mr-txn-tile t-restock" onclick="txnSelectType('restock')">
      <div class="tile-ic"><i class="fas fa-plus-circle"></i></div>
      <div class="tile-nm">${TH?'เติมสาร':'Restock'}</div>
      <div class="tile-ds">${TH?'เพิ่มปริมาณสารเข้าสต็อก':'Add quantity to stock'}</div>
    </button>
    <button class="mr-txn-tile t-dispose" onclick="txnSelectType('dispose')">
      <div class="tile-ic"><i class="fas fa-trash-alt"></i></div>
      <div class="tile-nm"><span>${TH?'จำหน่าย':'Dispose'}</span><span style="font-size:10px;font-weight:400;color:#d97706;margin-left:4px">${TH?'(นำออกจากระบบ)':'(remove from system)'}</span></div>
      <div class="tile-ds">${TH?'บันทึกการทิ้ง/จำหน่ายออกจากระบบ':'Record disposal — cannot be undone'}</div>
    </button>
  </div>` : `<div class="mr-txn-tiles" style="grid-template-columns:1fr">
    <button class="mr-txn-tile t-borrow" onclick="txnSelectType('borrow')">
      <div class="tile-ic"><i class="fas fa-hand-holding-heart"></i></div>
      <div class="tile-nm">${TH?'ขอยืม':'Request to Borrow'}</div>
      <div class="tile-ds">${TH?'ส่งคำขอยืมไปยังเจ้าของสาร — ต้องรอการอนุมัติ':'Submit borrow request to the owner — awaits approval'}</div>
    </button>
  </div>`;

  document.getElementById('txnModalBody').innerHTML = _txnMiniCard(c) + tiles;
}

function txnSelectType(type) {
  _txnType = type;
  const c = S.containers.find(x => parseInt(x.id) === _txnCid); if (!c) return;
  const qty = parseFloat(c.current_quantity || 0), unit = escH(c.quantity_unit || '');

  const meta = {
    withdraw: {col:'#dc2626',bg:'#fee2e2',ic:'fa-minus-circle',lbl:TH?'เบิกใช้':'Withdraw',       btnLbl:TH?'บันทึกการเบิก':'Record Withdrawal'},
    restock:  {col:'#16a34a',bg:'#dcfce7',ic:'fa-plus-circle', lbl:TH?'เติมสาร':'Restock',        btnLbl:TH?'บันทึกการเติม':'Record Restock'},
    dispose:  {col:'#d97706',bg:'#fef3c7',ic:'fa-trash-alt',   lbl:TH?'จำหน่าย':'Dispose',       btnLbl:TH?'ยืนยันการจำหน่าย':'Confirm Disposal'},
    borrow:   {col:'#6366f1',bg:'#eef2ff',ic:'fa-hand-holding-heart',lbl:TH?'ขอยืม':'Borrow Request',btnLbl:TH?'ส่งคำขอ':'Send Request'},
  };
  const m = meta[type];
  const hdr = `<div class="mr-txn-form-hdr">
    <div style="width:30px;height:30px;border-radius:8px;background:${m.bg};color:${m.col};display:flex;align-items:center;justify-content:center;font-size:14px"><i class="fas ${m.ic}"></i></div>
    ${escH(m.lbl)}
  </div>`;
  const qtyRow = `<div class="mr-fg"><label>${TH?'ปริมาณ *':'Amount *'}</label>
    <div class="mr-txn-qty-row">
      <input type="number" id="txnQty" min="0.001" step="any" placeholder="0.00"${type!=='restock'&&qty>0?' max="'+qty+'"':''}>
      <input type="text"   id="txnUnit" value="${unit}" placeholder="${unit||'mL'}">
    </div></div>`;

  let form = '';
  if (type === 'withdraw') {
    form = qtyRow +
      `<div class="mr-fg"><label>${TH?'วัตถุประสงค์ *':'Purpose *'}</label><textarea id="txnPurpose" rows="2" placeholder="${TH?'อธิบายการนำไปใช้...':'Describe usage...'}"></textarea></div>`;
  } else if (type === 'restock') {
    form = qtyRow +
      `<div class="mr-fg"><label>${TH?'บันทึก (ไม่บังคับ)':'Note (optional)'}</label><input type="text" id="txnPurpose" placeholder="${TH?'แหล่งที่มา, Lot No...':'Source, Lot No...'}"></div>`;
  } else if (type === 'dispose') {
    form = `<div class="mr-txn-warn"><i class="fas fa-exclamation-triangle" style="margin-top:1px;flex-shrink:0"></i>
      <span>${TH?'ภาชนะนี้จะถูกนำออกจากระบบ ไม่สามารถยกเลิกได้':'This container will be permanently removed from the system.'}</span></div>
      <div class="mr-fg"><label>${TH?'เหตุผล / วิธีจำหน่าย *':'Reason / Disposal method *'}</label>
      <textarea id="txnPurpose" rows="2" placeholder="${TH?'เช่น หมดอายุ, เสื่อมสภาพ, ทิ้งตามขั้นตอน...':'e.g. Expired, degraded, proper waste disposal...'}"></textarea></div>`;
  } else if (type === 'borrow') {
    form = qtyRow +
      `<div class="mr-fg"><label>${TH?'วัตถุประสงค์ *':'Purpose *'}</label><textarea id="txnPurpose" rows="2" placeholder="${TH?'อธิบายวัตถุประสงค์การใช้...':'Describe the purpose...'}"></textarea></div>`;
  }

  document.getElementById('txnModalBody').innerHTML = _txnMiniCard(c) +
    `<div class="mr-txn-form">${hdr}${form}</div>`;

  const btn = document.getElementById('txnBtnSubmit');
  btn.style.display = ''; btn.textContent = m.btnLbl;
  document.getElementById('txnBtnBack').style.display = '';
  setTimeout(() => document.getElementById('txnModalBody').querySelector('textarea, input[type="number"]')?.focus(), 60);
}

/* ── Batch Transaction Sheet ── */
let _mrBtxType = null;

function openBatchTxn() {
  if (!S.selected.size) return;
  _mrBtxType = null;
  const ids = [...S.selected];
  const ownedIds = ids.filter(id => { const c=S.containers.find(x=>parseInt(x.id)===id); return c&&parseInt(c.owner_id)===CURRENT_UID; });
  const otherCnt = ids.length - ownedIds.length;

  const tabs = [];
  if (ownedIds.length > 0) {
    tabs.push({type:'withdraw', ic:'fa-minus-circle',      lbl:TH?'เบิกใช้':'Withdraw',  col:'#dc2626', bg:'#fee2e2'});
    tabs.push({type:'restock',  ic:'fa-plus-circle',       lbl:TH?'เติมสาร':'Restock',   col:'#16a34a', bg:'#dcfce7'});
  }
  if (otherCnt > 0) {
    tabs.push({type:'borrow',   ic:'fa-hand-holding-heart',lbl:TH?'ขอยืม':'Borrow',     col:'#6366f1', bg:'#eef2ff'});
  }

  document.getElementById('mrBtxIc').innerHTML = '<i class="fas fa-layer-group"></i>';
  document.getElementById('mrBtxIc').style.cssText = 'width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;background:var(--mrbg);color:var(--mr)';
  document.getElementById('mrBtxTitle').textContent = TH ? 'ธุรกรรมหลายรายการ' : 'Batch Transaction';
  document.getElementById('mrBtxSub').textContent   = `${ids.length} ${TH ? 'รายการที่เลือก' : 'items selected'}`;
  document.getElementById('mrBtxTabs').innerHTML    = tabs.map(t =>
    `<button class="btx-tab" id="mrBtxTab_${t.type}" onclick="mrBtxSelectTab('${t.type}')">
       <i class="fas ${t.ic}"></i> ${t.lbl}
     </button>`).join('');
  document.getElementById('mrBtxBody').innerHTML = '';

  document.getElementById('mrBtxOv').classList.add('show');
  mrBtxSelectTab(tabs[0].type);
}

function mrBtxSelectTab(type) {
  _mrBtxType = type;
  const ids = [...S.selected];
  const isOwnerAction = type === 'withdraw' || type === 'restock';
  const affected = ids
    .map(id => S.containers.find(x => parseInt(x.id) === id))
    .filter(c => c && (isOwnerAction ? parseInt(c.owner_id)===CURRENT_UID : parseInt(c.owner_id)!==CURRENT_UID));

  document.querySelectorAll('#mrBtxTabs .btx-tab').forEach(el => el.classList.remove('act'));
  document.getElementById('mrBtxTab_' + type)?.classList.add('act');

  const m = {
    withdraw:{col:'#dc2626',bg:'#fee2e2',ic:'fa-minus-circle',      needPurpose:true,  btnLbl:TH?`บันทึกการเบิก (${affected.length})`:`Withdraw (${affected.length})`},
    restock: {col:'#16a34a',bg:'#dcfce7',ic:'fa-plus-circle',       needPurpose:false, btnLbl:TH?`บันทึกการเติม (${affected.length})`:`Restock (${affected.length})`},
    borrow:  {col:'#6366f1',bg:'#eef2ff',ic:'fa-hand-holding-heart',needPurpose:true,  btnLbl:TH?`ส่งคำขอ (${affected.length})`:`Request (${affected.length})`},
  }[type];

  const icEl = document.getElementById('mrBtxIc');
  icEl.innerHTML = `<i class="fas ${m.ic}"></i>`;
  icEl.style.background = m.bg; icEl.style.color = m.col;

  const itemRows = affected.map(c => {
    const cur  = parseFloat(c.current_quantity || 0);
    const unit = c.quantity_unit || '';
    const maxAttr = type === 'withdraw' ? `max="${cur}"` : '';
    const curColor = cur > 0 ? '#0f172a' : '#dc2626';
    return `
    <div class="btx-item" id="mrBtxRow_${c.id}">
      <div class="btx-item-ic" style="background:${m.bg};color:${m.col}"><i class="fas fa-flask"></i></div>
      <div class="btx-item-info">
        <div class="btx-item-name">${escH(c.chem_name || c.bottle_code || '–')}</div>
        <div class="btx-item-sub">${escH(c.bottle_code||'')} · ${TH?'คงเหลือ':'rem.'} <b style="color:${curColor}">${cur.toFixed(3)} ${escH(unit)}</b></div>
      </div>
      <div class="btx-item-qty">
        <input type="number" id="mrBtxQty_${c.id}" min="0.001" step="any" ${maxAttr}
          placeholder="0" oninput="mrBtxQtyCheck(this,${cur},'${type}')">
        <span class="btx-unit">${escH(unit)}</span>
      </div>
      <div class="btx-item-status" id="mrBtxSt_${c.id}"></div>
    </div>`;
  }).join('');

  document.getElementById('mrBtxBody').innerHTML = `
    <div>
      <div class="btx-items-hdr">
        <h5>${TH?'รายการที่จะดำเนินการ':'Items to process'} (${affected.length})</h5>
      </div>
      <div class="btx-items-scroll">${itemRows || `<div style="text-align:center;padding:12px;color:#94a3b8;font-size:12px">${TH?'ไม่มีรายการที่เหมาะสม':'No applicable items'}</div>`}</div>
    </div>
    <div class="btx-field">
      <label>${m.needPurpose?(TH?'วัตถุประสงค์':'Purpose')+' *':(TH?'บันทึก (ไม่บังคับ)':'Note (optional)')}</label>
      <textarea id="mrBtxPurpose" rows="2" placeholder="${TH?'อธิบายวัตถุประสงค์...':'Describe purpose...'}"></textarea>
    </div>
    <div id="mrBtxProgress" style="display:none">
      <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;margin-bottom:4px">
        <span id="mrBtxProgLbl">${TH?'กำลังดำเนินการ...':'Processing...'}</span>
        <span id="mrBtxProgNum">0/${affected.length}</span>
      </div>
      <div class="btx-prog"><div class="btx-prog-fill" id="mrBtxProgFill" style="width:0"></div></div>
    </div>
    <div id="mrBtxResultBox" style="display:none"></div>`;

  const submitBtn = document.getElementById('mrBtxSubmit');
  submitBtn.disabled = affected.length === 0;
  submitBtn.onclick = submitMrBatch;
  document.getElementById('mrBtxSubmitLbl').textContent = m.btnLbl;
  setTimeout(() => document.getElementById('mrBtxBody').querySelector('input[type="number"]')?.focus(), 60);
}

function closeMrBatch() {
  document.getElementById('mrBtxOv').classList.remove('show');
}

function mrBtxQtyCheck(el, max, type) {
  const v = parseFloat(el.value || 0);
  el.classList.toggle('qty-warn', v > 0 && type === 'withdraw' && max > 0 && v > max);
}

/* pending state for confirm popup */
let _mrBtxPending = null;

function submitMrBatch() {
  const type = _mrBtxType;
  const purpose = (document.getElementById('mrBtxPurpose')?.value || '').trim();
  const needPurpose = type !== 'restock';
  if (needPurpose && !purpose) { showToast(TH?'กรุณาระบุวัตถุประสงค์':'Enter purpose','err'); return; }

  const ids = [...S.selected];
  const isOwnerAction = type === 'withdraw' || type === 'restock';
  const affected = ids
    .map(id => S.containers.find(x => parseInt(x.id) === id))
    .filter(c => c && (isOwnerAction ? parseInt(c.owner_id)===CURRENT_UID : parseInt(c.owner_id)!==CURRENT_UID));

  // Validate per-item qty
  let hasEmptyQty = false;
  const qtys = {};
  for (const c of affected) {
    const qtyEl = document.getElementById('mrBtxQty_' + c.id);
    const v = parseFloat(qtyEl?.value || 0);
    if (!v || v <= 0) { qtyEl?.classList.add('qty-warn'); hasEmptyQty = true; }
    else { qtyEl?.classList.remove('qty-warn'); qtys[c.id] = v; }
  }
  if (hasEmptyQty) { showToast(TH?'กรุณาระบุปริมาณให้ครบทุกรายการ':'Enter amount for all items','err'); return; }

  _mrBtxPending = { type, affected, qtys, purpose };
  _showMrBtcPreview();
}

function _showMrBtcPreview() {
  const { type, affected, qtys, purpose } = _mrBtxPending;
  const cfg = {
    withdraw: { grad:'linear-gradient(135deg,#b91c1c,#ef4444)', ic:'fa-minus-circle',       titleTH:'ยืนยันการเบิกใช้',   titleEN:'Confirm Withdrawal',  btnTH:'เบิกใช้เลย',     btnEN:'Confirm Withdraw', bgItem:'#fff5f5', bdItem:'#fecaca', colItem:'#dc2626' },
    restock:  { grad:'linear-gradient(135deg,#15803d,#22c55e)', ic:'fa-plus-circle',        titleTH:'ยืนยันการเติมสาร',   titleEN:'Confirm Restock',     btnTH:'เติมสารเลย',     btnEN:'Confirm Restock',  bgItem:'#f0fdf4', bdItem:'#bbf7d0', colItem:'#16a34a' },
    borrow:   { grad:'linear-gradient(135deg,#4338ca,#6366f1)', ic:'fa-hand-holding-heart', titleTH:'ยืนยันการขอยืม',    titleEN:'Confirm Borrow',      btnTH:'ส่งคำขอเลย',     btnEN:'Send Requests',    bgItem:'#eef2ff', bdItem:'#c7d2fe', colItem:'#4f46e5' },
  }[type];

  const itemsHtml = affected.map(c => {
    const qty = qtys[c.id];
    const unit = c.quantity_unit || '';
    return `<div class="mr-btc-item" style="background:${cfg.bgItem};border-color:${cfg.bdItem}">
      <div class="mr-btc-item-ic" style="background:rgba(0,0,0,.06);color:${cfg.colItem}"><i class="fas fa-flask"></i></div>
      <div style="flex:1;min-width:0">
        <div class="mr-btc-item-name">${escH(c.chem_name || c.bottle_code || '–')}</div>
        <div class="mr-btc-item-sub">${escH(c.bottle_code||'')}</div>
      </div>
      <div class="mr-btc-item-qty" style="color:${cfg.colItem}">${qty.toLocaleString(undefined,{maximumFractionDigits:3})} ${escH(unit)}</div>
    </div>`;
  }).join('');

  const purposeHtml = purpose
    ? `<div>
        <div class="mr-btc-sec"><i class="fas fa-align-left"></i> ${TH?'วัตถุประสงค์':'Purpose'}</div>
        <div class="mr-btc-purpose">${escH(purpose)}</div>
      </div>` : '';

  document.getElementById('mrBtcBox').innerHTML = `
    <div class="mr-btc-hdr">
      <div class="mr-btc-hdr-ic" style="background:${cfg.grad}"><i class="fas ${cfg.ic}"></i></div>
      <h3>${TH ? cfg.titleTH : cfg.titleEN}</h3>
      <p>${affected.length} ${TH?'รายการ':'item(s)'}</p>
    </div>
    <div class="mr-btc-body">
      <div>
        <div class="mr-btc-sec"><i class="fas fa-flask"></i> ${TH?'รายการที่จะดำเนินการ':'Items'}</div>
        <div class="mr-btc-items">${itemsHtml}</div>
      </div>
      ${purposeHtml}
    </div>
    <div class="mr-btc-footer">
      <button class="mr-btc-btn-confirm" style="background:${cfg.grad}" onclick="mrBtcConfirm()">
        <i class="fas ${cfg.ic}"></i> ${TH ? cfg.btnTH : cfg.btnEN}
      </button>
      <button class="mr-btc-btn-edit" onclick="mrBtcEdit()">
        <i class="fas fa-arrow-left"></i> ${TH?'แก้ไข':'Edit'}
      </button>
    </div>`;

  document.getElementById('mrBtcOv').classList.add('show');
}

function mrBtcEdit() {
  document.getElementById('mrBtcOv').classList.remove('show');
  if (_dndPending) _dndCancel();
}

async function mrBtcConfirm() {
  document.getElementById('mrBtcOv').classList.remove('show');
  const { type, affected, qtys, purpose } = _mrBtxPending;

  const submitBtn = document.getElementById('mrBtxSubmit');
  submitBtn.disabled = true;
  document.getElementById('mrBtxProgress').style.display = 'block';
  document.getElementById('mrBtxResultBox').style.display = 'none';

  let ok = 0, fail = 0;
  for (let i = 0; i < affected.length; i++) {
    const c = affected[i];
    const qty = qtys[c.id];
    document.getElementById('mrBtxProgNum').textContent  = `${i}/${affected.length}`;
    document.getElementById('mrBtxProgFill').style.width = `${(i/affected.length)*100}%`;
    document.getElementById('mrBtxProgLbl').textContent  = `${TH?'กำลังดำเนินการ':'Processing'} ${c.chem_name||c.bottle_code||''}...`;
    const row  = document.getElementById('mrBtxRow_' + c.id);
    const stEl = document.getElementById('mrBtxSt_' + c.id);
    try {
      const body = type === 'borrow'
        ? {container_id:c.id, quantity:qty, unit:c.quantity_unit, purpose}
        : {container_id:c.id, amount:qty,   unit:c.quantity_unit, purpose};
      const r = await apiFetch(API+'?action='+(type==='borrow'?'borrow_request':type), {method:'POST',body:JSON.stringify(body)});
      if (!r?.success) throw new Error(r?.error||'Failed');
      ok++;
      row?.classList.add('btx-ok');
      if (stEl) stEl.innerHTML = '<span style="color:#16a34a;font-size:15px">✓</span>';
    } catch(e) {
      fail++;
      row?.classList.add('btx-err');
      if (stEl) stEl.innerHTML = `<span style="color:#dc2626;font-size:15px" title="${escH(e.message)}">✗</span>`;
    }
  }

  document.getElementById('mrBtxProgFill').style.width = '100%';
  document.getElementById('mrBtxProgNum').textContent  = `${affected.length}/${affected.length}`;
  document.getElementById('mrBtxProgLbl').textContent  = TH ? 'เสร็จสิ้น' : 'Done';

  const rb = document.getElementById('mrBtxResultBox');
  rb.style.display = 'block';
  const lines = [];
  if (ok   > 0) lines.push(`<span style="color:#16a34a"><i class="fas fa-check-circle"></i> ${TH?`สำเร็จ ${ok} รายการ`:`${ok} succeeded`}</span>`);
  if (fail > 0) lines.push(`<span style="color:#dc2626"><i class="fas fa-times-circle"></i> ${TH?`ล้มเหลว ${fail} รายการ`:`${fail} failed`}</span>`);
  rb.innerHTML = `<div class="btx-result-summary ${fail?'btx-result-err':'btx-result-ok'}" style="flex-direction:column;align-items:flex-start;gap:4px;font-size:12px">${lines.join('')}</div>`;

  submitBtn.disabled = false;
  document.getElementById('mrBtxSubmitLbl').textContent = TH ? 'ปิด' : 'Close';
  submitBtn.onclick = () => { closeMrBatch(); if (ok>0) { clearSelection(); selectRoom(S.activeRoomId); } };
  _mrBtxPending = null;
}

async function submitTxn() {
  const c = S.containers.find(x => parseInt(x.id) === _txnCid); if (!c || !_txnType) return;
  const btn = document.getElementById('txnBtnSubmit');
  const qty     = parseFloat(document.getElementById('txnQty')?.value || 0);
  const unit    = (document.getElementById('txnUnit')?.value || c.quantity_unit || '').trim();
  const purpose = (document.getElementById('txnPurpose')?.value || '').trim();

  // validation
  if (_txnType !== 'restock' && !purpose) { showToast(TH?'กรุณาระบุวัตถุประสงค์/เหตุผล':'Enter purpose / reason','err'); return; }
  if (_txnType !== 'dispose' && (!qty || qty <= 0)) { showToast(TH?'กรุณาระบุปริมาณ':'Enter amount','err'); return; }
  if (_txnType === 'withdraw' && qty > parseFloat(c.current_quantity || 0)) {
    showToast(TH?'ปริมาณที่เบิกเกินกว่าที่มีอยู่':'Amount exceeds available quantity','err'); return;
  }

  btn.disabled = true;
  const body = _txnType === 'dispose'
    ? {container_id: c.id, reason: purpose}
    : _txnType === 'borrow'
      ? {container_id: c.id, quantity: qty, unit, purpose}
      : {container_id: c.id, amount: qty, unit, purpose};
  const action = _txnType === 'borrow' ? 'borrow_request' : _txnType;
  const r = await apiFetch(API + '?action=' + action, {method:'POST', body:JSON.stringify(body)});
  btn.disabled = false;
  if (!r?.success) { showToast(r?.error || 'Error', 'err'); return; }

  const msgs = {
    withdraw: TH?'บันทึกการเบิกเรียบร้อย':'Withdrawal recorded',
    restock:  TH?'บันทึกการเติมเรียบร้อย':'Restock recorded',
    dispose:  TH?'บันทึกการจำหน่ายเรียบร้อย':'Disposal recorded',
    borrow:   TH?'ส่งคำขอยืมเรียบร้อย':'Borrow request sent',
  };
  showToast(msgs[_txnType], 'ok');
  closeModal('modalTxn');
  if (_txnType !== 'borrow') await selectRoom(S.activeRoomId);
}

/* ── Delegated click → detail drawer ── */
document.getElementById('mrList').addEventListener('click', function(e) {
  if (e.target.closest('.mr-cb, button, input, select, a, .mr-card-act, .mr-grp-actions, .col-act, .mr-t-act, .mr-act-btn')) return;
  const card = e.target.closest('.mr-card[data-cid]');
  if (card) { openContainerDetail(parseInt(card.dataset.cid)); return; }
  const row = e.target.closest('tr[data-cid]');
  if (row) { openContainerDetail(parseInt(row.dataset.cid)); return; }
  const grpRow = e.target.closest('.mr-grp-row[data-cid]');
  if (grpRow) { openContainerDetail(parseInt(grpRow.dataset.cid)); return; }
});

/* ══════════════════════════════════════════════════════
   STORAGE LOCATION REPORT  —  opens in new window as PDF
   ══════════════════════════════════════════════════════ */
/* ── Report scope state ── */
let _rptScope = { type:'all', cabinet_id:null, shelf_id:null, slot_id:null };

const _RPT_SCOPES = [
  { key:'all',     ic:'fa-warehouse',   col:'#6366f1', bg:'#eef2ff', lbl_th:'ทั้งหมด',   lbl_en:'All',        sub_th:'สารทุกรายการในห้อง', sub_en:'All chemicals' },
  { key:'cabinet', ic:'fa-archive',     col:'#0d9488', bg:'#f0fdfa', lbl_th:'ตามตู้',     lbl_en:'By Cabinet', sub_th:'เลือกตู้จัดเก็บ',    sub_en:'Select cabinet' },
  { key:'shelf',   ic:'fa-layer-group', col:'#7c3aed', bg:'#f5f3ff', lbl_th:'ตามชั้น',    lbl_en:'By Shelf',   sub_th:'เลือกชั้นวาง',       sub_en:'Select shelf' },
  { key:'slot',    ic:'fa-th',          col:'#ea580c', bg:'#fff7ed', lbl_th:'ตามช่อง',    lbl_en:'By Slot',    sub_th:'เลือกช่องวาง',      sub_en:'Select slot' },
];

function openMrRpt() {
  if (!S.activeRoomId) { showToast(TH?'กรุณาเลือกห้องก่อน':'Select a room first','err'); return; }
  _rptScope = { type:'all', cabinet_id:null, shelf_id:null, slot_id:null };
  _rptRender();
  openModal('modalRpt');
}

function _rptRender() {
  // Scope tiles
  const tilesHtml = _RPT_SCOPES.map(s => {
    const active = _rptScope.type === s.key;
    return `<button class="rpt-scope-tile${active?' rpt-active':''}"
      style="${active?`border-color:${s.col};background:${s.bg}`:''}"
      onclick="rptSetScope('${s.key}')">
      <div class="rpt-scope-ic" style="background:${s.bg};color:${s.col}"><i class="fas ${s.ic}"></i></div>
      <div class="rpt-scope-lbl">${TH?s.lbl_th:s.lbl_en}</div>
      <div class="rpt-scope-sub">${TH?s.sub_th:s.sub_en}</div>
      ${active?`<div class="rpt-scope-chk"><i class="fas fa-check-circle" style="color:${s.col}"></i></div>`:''}
    </button>`;
  }).join('');
  document.getElementById('rptScopeGrid').innerHTML = tilesHtml;

  // Cascade selects
  const needCab = _rptScope.type==='cabinet'||_rptScope.type==='shelf'||_rptScope.type==='slot';
  const needShf = (_rptScope.type==='shelf'||_rptScope.type==='slot') && _rptScope.cabinet_id;
  const needSlt = _rptScope.type==='slot' && _rptScope.shelf_id;
  let cascHtml = '';

  if (needCab) {
    const opts = S.cabinets.map(c=>`<option value="${c.id}"${_rptScope.cabinet_id===parseInt(c.id)?' selected':''}>${escH(c.code?c.code+' — ':'')}${escH(c.name)}</option>`).join('');
    cascHtml += `<div class="mr-fg"><label>${TH?'ตู้จัดเก็บ':'Cabinet'}</label>
      <select id="rptCabSel" onchange="rptCabChange()">
        <option value="">${TH?'— ไม่ระบุ (= ทุกตู้ในห้อง) —':'— Any cabinet —'}</option>${opts}
      </select></div>`;
  }
  if (needShf) {
    const cab = S.cabinets.find(c=>parseInt(c.id)===_rptScope.cabinet_id);
    const opts = (cab?.shelves||[]).map(s=>`<option value="${s.id}"${_rptScope.shelf_id===parseInt(s.id)?' selected':''}>${escH(s.name||s.code||'Shelf')}</option>`).join('');
    cascHtml += `<div class="mr-fg"><label>${TH?'ชั้นวาง':'Shelf'}</label>
      <select id="rptShelfSel" onchange="rptShelfChange()">
        <option value="">${TH?'— ไม่ระบุ (= ทั้งตู้) —':'— Whole cabinet —'}</option>${opts}
      </select></div>`;
  }
  if (needSlt) {
    const cab = S.cabinets.find(c=>parseInt(c.id)===_rptScope.cabinet_id);
    const sh  = (cab?.shelves||[]).find(s=>parseInt(s.id)===_rptScope.shelf_id);
    const opts = (sh?.slots||[]).map(s=>`<option value="${s.id}"${_rptScope.slot_id===parseInt(s.id)?' selected':''}>${escH(s.code||s.name||'Slot')}</option>`).join('');
    cascHtml += `<div class="mr-fg"><label>${TH?'ช่องวาง':'Slot'}</label>
      <select id="rptSlotSel" onchange="rptSlotChange()">
        <option value="">${TH?'— ไม่ระบุ (= ทั้งชั้น) —':'— Whole shelf —'}</option>${opts}
      </select></div>`;
  }
  document.getElementById('rptCascade').innerHTML = cascHtml;

  // Preview
  const cs = _getRptContainers();
  const path = _rptScopePath();
  document.getElementById('rptPreview').innerHTML = `<div class="rpt-preview-box">
    <div class="rpt-preview-cnt">
      <i class="fas fa-flask" style="font-size:11px"></i>
      <b>${cs.length}</b>&nbsp;${TH?'รายการสารเคมีในขอบเขตที่เลือก':'chemicals in selected scope'}
    </div>
    <div class="rpt-preview-path"><i class="fas fa-filter" style="font-size:9px;color:var(--mr)"></i>${path}</div>
  </div>`;
}

function _rptScopePath() {
  const { type, cabinet_id, shelf_id, slot_id } = _rptScope;
  if (type === 'all') return TH?'<b>ทั้งหมด</b> — ทุกสารในห้อง':'<b>All</b> — every chemical in room';
  const parts = [];
  if (cabinet_id) { const cab=S.cabinets.find(c=>parseInt(c.id)===cabinet_id); if(cab) parts.push(`<span style="background:var(--mrbg);padding:1px 7px;border-radius:5px">${escH(cab.name||cab.code||'')}</span>`); }
  if (shelf_id)   { for(const cab of S.cabinets){ const sh=(cab.shelves||[]).find(s=>parseInt(s.id)===shelf_id); if(sh){parts.push(`<span style="background:#f5f3ff;padding:1px 7px;border-radius:5px">${escH(sh.name||sh.code||'')}</span>`);break;} } }
  if (slot_id)    { for(const cab of S.cabinets) for(const sh of(cab.shelves||[])){ const sl=(sh.slots||[]).find(s=>parseInt(s.id)===slot_id); if(sl){parts.push(`<span style="background:#fff7ed;padding:1px 7px;border-radius:5px">${escH(sl.code||sl.name||'')}</span>`);break;} } }
  return parts.length ? parts.join(' <span style="color:#c7d2fe;font-size:11px">›</span> ') : (TH?'(ไม่ได้ระบุ = ครอบคลุมทั้งหมด)':'(none selected = all)');
}

function rptSetScope(type) {
  _rptScope = { type, cabinet_id:null, shelf_id:null, slot_id:null };
  _rptRender();
}
function rptCabChange() {
  _rptScope.cabinet_id = parseInt(document.getElementById('rptCabSel')?.value||0)||null;
  _rptScope.shelf_id = null; _rptScope.slot_id = null;
  _rptRender();
}
function rptShelfChange() {
  _rptScope.shelf_id = parseInt(document.getElementById('rptShelfSel')?.value||0)||null;
  _rptScope.slot_id = null;
  _rptRender();
}
function rptSlotChange() {
  _rptScope.slot_id = parseInt(document.getElementById('rptSlotSel')?.value||0)||null;
  _rptRender();
}

function _getRptContainers() {
  const { type, cabinet_id, shelf_id, slot_id } = _rptScope;
  let cs = [...S.containers];
  if (type === 'all') return cs;
  if (slot_id)    return cs.filter(c=>parseInt(c.slot_id)===slot_id);
  if (shelf_id)   return cs.filter(c=>parseInt(c.shelf_id)===shelf_id);
  if (cabinet_id) return cs.filter(c=>parseInt(c.cabinet_id)===cabinet_id);
  return cs;
}
function _getRptCabinets() {
  const { cabinet_id, type } = _rptScope;
  if (type === 'all' || !cabinet_id) return S.cabinets;
  return S.cabinets.filter(c=>parseInt(c.id)===cabinet_id);
}

function submitRpt() {
  const fields = {
    nickname:  !!(document.getElementById('rptFldNickname')?.checked),
    room_note: !!(document.getElementById('rptFldNote')?.checked),
  };
  const cs   = _getRptContainers();
  const cabs = _getRptCabinets();
  const html = _rptFullHtml(cs, cabs, fields);
  const win  = window.open('', '_blank');
  if (!win) { showToast(TH?'กรุณาอนุญาต Popup เพื่อเปิดรายงาน':'Allow popups to open the report','err'); return; }
  win.document.write(html);
  win.document.close();
  closeModal('modalRpt');
}

/* ── helpers ── */
function _rptSt(c) {
  if (!c.expiry_date) return 'none';
  const d = new Date(c.expiry_date), now = new Date();
  if (d < now) return 'exp';
  if (d <= new Date(now.getTime() + 60 * 864e5)) return 'warn';
  return 'ok';
}
function _rptStHtml(st) {
  const m = {ok:[TH?'ปกติ':'Normal','#166534','#dcfce7'],warn:[TH?'ใกล้หมดอายุ':'Expiring','#9a3412','#fff7ed'],exp:[TH?'หมดอายุแล้ว':'Expired','#991b1b','#fee2e2'],none:[TH?'ไม่ระบุ':'—','#64748b','#f1f5f9']};
  const [lbl,col,bg] = m[st]||m.none;
  return `<span style="display:inline-flex;align-items:center;gap:4px;background:${bg};color:${col};border-radius:5px;padding:2px 8px;font-size:10px;font-weight:700;white-space:nowrap"><span style="width:5px;height:5px;border-radius:50%;background:${col};flex-shrink:0"></span>${lbl}</span>`;
}
function _rptShelfSlotHtml(c) {
  if (!c.shelf_name && !c.shelf_code) return '<span style="color:#cbd5e1">—</span>';
  const sh = escH(c.shelf_name || c.shelf_code);
  const sl = c.slot_code || c.slot_name;
  if (!sl) return `<span style="color:#475569;font-size:10.5px">${sh}</span>`;
  return `<span style="color:#475569;font-size:10.5px">${sh}</span><span style="color:#cbd5e1;font-size:9px;margin:0 3px">›</span><span style="background:#eef2ff;color:#4338ca;border-radius:4px;padding:1px 6px;font-size:10.5px;font-weight:700">${escH(sl)}</span>`;
}
function _rptExpHtml(c, st) {
  if (!c.expiry_date) return '<span style="color:#cbd5e1">—</span>';
  const colMap = {ok:'#475569',warn:'#d97706',exp:'#dc2626',none:'#475569'};
  return `<span style="color:${colMap[st]||'#475569'};font-size:10.5px">${new Date(c.expiry_date).toLocaleDateString('th-TH',{day:'numeric',month:'short',year:'2-digit'})}</span>`;
}
function _rptQtyHtml(c) {
  if (c.current_quantity == null) return '<span style="color:#cbd5e1">—</span>';
  const fmt  = n => parseFloat(n).toLocaleString(undefined,{maximumFractionDigits:3});
  const unit = escH(c.quantity_unit||'');
  const cur  = escH(fmt(c.current_quantity));
  if (c.initial_quantity != null && parseFloat(c.initial_quantity) > 0) {
    const ini  = escH(fmt(c.initial_quantity));
    const pct  = Math.min(100, Math.max(0, parseFloat(c.current_quantity) / parseFloat(c.initial_quantity) * 100));
    const pctC = pct > 50 ? '#16a34a' : pct > 20 ? '#d97706' : '#dc2626';
    return `<div style="font-weight:700;font-size:11.5px;white-space:nowrap;line-height:1.4">${cur} <span style="color:#94a3b8;font-weight:500;font-size:10px">/ ${ini} ${unit}</span></div>`
         + `<div style="font-size:9px;color:${pctC};font-weight:700;margin-top:1px">${Math.round(pct)}%</div>`;
  }
  return `<div style="font-weight:700;font-size:11.5px;white-space:nowrap">${cur} ${unit}</div>`;
}

/* ── one table row (organized) ── */
function _rptRow(c, i, fields) {
  const st = _rptSt(c);
  return `<tr>
    <td style="text-align:center;color:#cbd5e1;font-size:10px;font-weight:700;width:28px">${i+1}</td>
    <td>
      <div style="font-weight:700;color:#1e293b;font-size:12px">${escH((c.chem_name||c.bottle_code||'—').substring(0,40))}</div>
      ${c.molecular_formula?`<div style="font-size:9.5px;color:#94a3b8;font-family:monospace;margin-top:1px">${escH(c.molecular_formula)}</div>`:''}
      ${fields?.nickname&&c.nickname?`<div style="font-size:9.5px;color:#6366f1;font-weight:600;margin-top:2px;display:flex;align-items:center;gap:3px"><i class="fas fa-tag" style="font-size:8px;opacity:.8"></i>${escH(c.nickname)}</div>`:''}
      ${fields?.room_note&&c.room_note?`<div style="font-size:9px;color:#64748b;font-style:italic;margin-top:2px;line-height:1.4;max-width:220px">${escH(c.room_note.substring(0,100))}</div>`:''}
    </td>
    <td style="font-family:monospace;font-size:10px;color:#64748b;white-space:nowrap">${escH(c.cas_number||'—')}</td>
    <td style="font-family:monospace;font-size:10.5px;color:#64748b;white-space:nowrap">${escH(c.bottle_code||'—')}</td>
    <td>${_rptShelfSlotHtml(c)}</td>
    <td style="text-align:right">${_rptQtyHtml(c)}</td>
    <td>${_rptExpHtml(c,st)}</td>
    <td>${_rptStHtml(st)}</td>
  </tr>`;
}

/* ── one table row (unplaced) ── */
function _rptRowUnp(c, i, fields) {
  const st = _rptSt(c);
  return `<tr style="background:#fffdf4">
    <td style="text-align:center;color:#cbd5e1;font-size:10px;font-weight:700;width:28px">${i+1}</td>
    <td>
      <div style="font-weight:700;color:#1e293b;font-size:12px">${escH((c.chem_name||c.bottle_code||'—').substring(0,40))}</div>
      ${c.molecular_formula?`<div style="font-size:9.5px;color:#94a3b8;font-family:monospace;margin-top:1px">${escH(c.molecular_formula)}</div>`:''}
      ${fields?.nickname&&c.nickname?`<div style="font-size:9.5px;color:#6366f1;font-weight:600;margin-top:2px;display:flex;align-items:center;gap:3px"><i class="fas fa-tag" style="font-size:8px;opacity:.8"></i>${escH(c.nickname)}</div>`:''}
      ${fields?.room_note&&c.room_note?`<div style="font-size:9px;color:#64748b;font-style:italic;margin-top:2px;line-height:1.4;max-width:220px">${escH(c.room_note.substring(0,100))}</div>`:''}
    </td>
    <td style="font-family:monospace;font-size:10px;color:#64748b;white-space:nowrap">${escH(c.cas_number||'—')}</td>
    <td style="font-family:monospace;font-size:10.5px;color:#64748b;white-space:nowrap">${escH(c.bottle_code||'—')}</td>
    <td style="text-align:right">${_rptQtyHtml(c)}</td>
    <td>${_rptExpHtml(c,st)}</td>
    <td>${_rptStHtml(st)}</td>
  </tr>`;
}

const _rptTblTh = `<thead><tr style="background:#f8fafc">
  <th style="width:28px">#</th>
  <th>${TH?'ชื่อสาร':'Chemical Name'}</th>
  <th>CAS Number</th>
  <th>${TH?'รหัสขวด':'Bottle Code'}</th>
  <th>${TH?'ชั้น / ช่อง':'Shelf / Slot'}</th>
  <th style="text-align:right">${TH?'คงเหลือ / บรรจุ':'Remaining / Initial'}</th>
  <th>${TH?'วันหมดอายุ':'Expiry'}</th>
  <th>${TH?'สถานะ':'Status'}</th>
</tr></thead>`;

const _rptTblThUnp = `<thead><tr style="background:#fffbeb">
  <th style="width:28px">#</th>
  <th>${TH?'ชื่อสาร':'Chemical Name'}</th>
  <th>CAS Number</th>
  <th>${TH?'รหัสขวด':'Bottle Code'}</th>
  <th style="text-align:right">${TH?'คงเหลือ / บรรจุ':'Remaining / Initial'}</th>
  <th>${TH?'วันหมดอายุ':'Expiry'}</th>
  <th>${TH?'สถานะ':'Status'}</th>
</tr></thead>`;

/* ── per-cabinet section ── */
function _rptCabSec(cab, allConts, idx, fields) {
  const cabId   = parseInt(cab.id);
  const ic      = CAB_IC[cab.type]  || 'fa-box';
  const col     = CAB_COL[cab.type] || '#6366f1';
  const conts   = allConts.filter(c => parseInt(c.cabinet_id) === cabId);
  const types   = {storage:TH?'ตู้ทั่วไป':'Storage',fume_hood:TH?'ตู้ดูดควัน':'Fume Hood',refrigerator:TH?'ตู้เย็น':'Refrigerator',freezer:TH?'ช่องแช่แข็ง':'Freezer',safety_cabinet:TH?'ตู้นิรภัย':'Safety Cabinet',other:TH?'อื่นๆ':'Other'};
  const typeLbl = types[cab.type] || '';
  const sep     = idx > 0 ? `<tr><td colspan="8" style="padding:0;height:14px;border:none;background:#f8fafc"></td></tr>` : '';

  const cabHdr = `<tr>
    <td colspan="8" style="padding:0;border:none">
      <div style="display:flex;align-items:center;gap:9px;padding:9px 12px;background:${col}0d;border-left:3px solid ${col};border-radius:0;margin-bottom:0">
        <div style="width:24px;height:24px;border-radius:7px;background:${col}22;color:${col};display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0"><i class="fas ${ic}"></i></div>
        <span style="font-size:12px;font-weight:800;color:#1e293b;flex:1">${escH(cab.name||cab.code)}${cab.code&&cab.name?` <span style="font-size:10px;font-weight:500;color:#94a3b8">· ${escH(cab.code)}</span>`:''}</span>
        ${typeLbl?`<span style="font-size:10px;color:#94a3b8">${typeLbl}</span>`:''}
        <span style="background:${col}18;color:${col};border-radius:5px;padding:2px 9px;font-size:10px;font-weight:700">${conts.length} ${TH?'รายการ':'items'}</span>
      </div>
    </td>
  </tr>`;

  if (!conts.length) {
    return sep + cabHdr + `<tr><td colspan="8" style="text-align:center;padding:12px;color:#cbd5e1;font-size:11px;font-style:italic">${TH?'ไม่มีสารในตู้นี้':'No chemicals'}</td></tr>`;
  }

  conts.sort((a,b)=>{
    const s=(a.shelf_name||'').localeCompare(b.shelf_name||'');if(s)return s;
    const l=(a.slot_code||a.slot_name||'').localeCompare(b.slot_code||b.slot_name||'');if(l)return l;
    return (a.bottle_code||'').localeCompare(b.bottle_code||'');
  });
  return sep + cabHdr + conts.map((c,i)=>_rptRow(c,i,fields)).join('');
}

/* ── full paper HTML content ── */
function _buildRptPaper(cs, cabs, fields) {
  const room    = S.rooms.find(r=>parseInt(r.room_id)===S.activeRoomId)||{};
  const today   = new Date().toLocaleDateString('th-TH',{day:'numeric',month:'long',year:'numeric'});
  const gen     = new Date().toLocaleString('th-TH',{day:'numeric',month:'short',year:'2-digit',hour:'2-digit',minute:'2-digit'});
  const exp60   = cs.filter(c=>_rptSt(c)==='warn').length;
  const expired = cs.filter(c=>_rptSt(c)==='exp').length;
  const unp     = cs.filter(c=>!c.cabinet_id);
  const org     = cs.length - unp.length;
  const roomLbl = [room.code, room.name].filter(Boolean).join(' — ');
  const bldLbl  = room.bld_short || room.bld_name || '';

  // scope badge for cover
  let scopeLbl = '';
  if (_rptScope.type !== 'all') {
    const parts = [];
    if (_rptScope.cabinet_id) { const cab=S.cabinets.find(c=>parseInt(c.id)===_rptScope.cabinet_id); if(cab) parts.push(escH(cab.name||cab.code||'')); }
    if (_rptScope.shelf_id)   { for(const cab of S.cabinets){ const sh=(cab.shelves||[]).find(s=>parseInt(s.id)===_rptScope.shelf_id); if(sh){parts.push(escH(sh.name||sh.code||''));break;} } }
    if (_rptScope.slot_id)    { for(const cab of S.cabinets) for(const sh of(cab.shelves||[])){ const sl=(sh.slots||[]).find(s=>parseInt(s.id)===_rptScope.slot_id); if(sl){parts.push(escH(sl.code||sl.name||''));break;} } }
    const typeN = {cabinet:TH?'ตามตู้':'Cabinet',shelf:TH?'ตามชั้น':'Shelf',slot:TH?'ตามช่อง':'Slot'};
    scopeLbl = parts.length ? parts.join(' › ') : (typeN[_rptScope.type]||'');
  }

  /* cover */
  const cover = `<div class="rpt-cover">
    <div class="rpt-cover-inner">
      <div class="rpt-cover-row">
        <div class="rpt-cover-ic"><i class="fas fa-flask"></i></div>
        <div>
          <div class="rpt-doc-label">Chemical Inventory Report</div>
          <div class="rpt-cover-title">${TH?'รายงานตำแหน่งจัดเก็บสารเคมี':'Chemical Storage Location Report'}</div>
          <div class="rpt-cover-sub">
            ${bldLbl?`<i class="fas fa-building"></i> ${escH(bldLbl)} &nbsp;›&nbsp; `:''}
            <i class="fas fa-door-open"></i> ${escH(roomLbl)}
            &nbsp;&nbsp;·&nbsp;&nbsp;
            <i class="fas fa-calendar-alt"></i> ${today}
          </div>
          ${scopeLbl?`<div style="margin-top:8px;display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:20px;padding:4px 12px;font-size:10.5px"><i class="fas fa-filter" style="font-size:9px;opacity:.7"></i> ${scopeLbl}</div>`:''}
        </div>
      </div>
      <hr class="rpt-divider">
      <div class="rpt-cover-stats">
        <div class="rpt-cs"><div class="rpt-cs-v">${cs.length}</div><div class="rpt-cs-l">${TH?'สารทั้งหมด':'Total'}</div></div>
        <div class="rpt-cs"><div class="rpt-cs-v ok">${org}</div><div class="rpt-cs-l">${TH?'จัดเก็บแล้ว':'Organized'}</div></div>
        <div class="rpt-cs"><div class="rpt-cs-v ${unp.length?'warn':''}">${unp.length}</div><div class="rpt-cs-l">${TH?'ยังไม่จัดเก็บ':'Unplaced'}</div></div>
        <div class="rpt-cs"><div class="rpt-cs-v ${exp60?'warn':''}">${exp60}</div><div class="rpt-cs-l">${TH?'ใกล้หมดอายุ':'Expiring'}</div></div>
        <div class="rpt-cs"><div class="rpt-cs-v ${expired?'danger':''}">${expired}</div><div class="rpt-cs-l">${TH?'หมดอายุแล้ว':'Expired'}</div></div>
      </div>
    </div>
  </div>`;

  /* organized table */
  const orgRows = cabs.map((cab,i)=>_rptCabSec(cab,cs,i,fields)).filter(Boolean).join('');
  const orgSection = org > 0 ? `<div class="rpt-sec">
    <div class="rpt-sec-hdr">
      <div class="rpt-sec-hdr-ic" style="background:#eef2ff;color:#4f46e5"><i class="fas fa-map-marked-alt"></i></div>
      <div class="rpt-sec-hdr-title">${TH?'รายการสารเคมีจำแนกตามตำแหน่งจัดเก็บ':'Chemicals by Storage Location'}</div>
      <span class="rpt-sec-hdr-cnt">${org} ${TH?'รายการ':'items'}</span>
    </div>
    <table class="rpt-tbl">${_rptTblTh}<tbody>${orgRows}</tbody></table>
  </div>` : '';

  /* unplaced table */
  const unpSection = unp.length ? `<div class="rpt-sec">
    <div class="rpt-sec-hdr">
      <div class="rpt-sec-hdr-ic" style="background:#fff3cd;color:#d97706"><i class="fas fa-inbox"></i></div>
      <div class="rpt-sec-hdr-title">${TH?'สารที่ยังไม่ได้จัดเก็บ':'Unplaced Chemicals'}</div>
      <span class="rpt-sec-hdr-cnt" style="background:#fff3cd;color:#d97706">${unp.length} ${TH?'รายการ':'items'}</span>
    </div>
    <table class="rpt-tbl">${_rptTblThUnp}<tbody>${unp.map((c,i)=>_rptRowUnp(c,i,fields)).join('')}</tbody></table>
  </div>` : '';

  /* footer */
  const footer = `<div class="rpt-footer">
    <div class="rpt-footer-title"><i class="fas fa-pen-alt"></i> ${TH?'ลงนามรับรอง':'Authorized Signatures'}</div>
    <div class="rpt-footer-sigs">
      <div class="rpt-sig">
        <div class="rpt-sig-role">${TH?'ผู้จัดทำรายงาน':'Prepared by'}</div>
        <div class="rpt-sig-line"></div>
        <div class="rpt-sig-field">${TH?'ชื่อ-นามสกุล':'Name'}: <span class="rpt-sig-blank"></span></div>
        <div class="rpt-sig-field">${TH?'วันที่':'Date'}: <span class="rpt-sig-blank"></span></div>
      </div>
      <div class="rpt-sig">
        <div class="rpt-sig-role">${TH?'ผู้ตรวจสอบ / อนุมัติ':'Verified & Approved by'}</div>
        <div class="rpt-sig-line"></div>
        <div class="rpt-sig-field">${TH?'ชื่อ-นามสกุล':'Name'}: <span class="rpt-sig-blank"></span></div>
        <div class="rpt-sig-field">${TH?'วันที่':'Date'}: <span class="rpt-sig-blank"></span></div>
      </div>
    </div>
    <div class="rpt-generated">${TH?'สร้างโดยระบบจัดการสารเคมี':'Generated by Chemical Management System'} &nbsp;·&nbsp; ${gen}</div>
  </div>`;

  return cover + `<div class="rpt-body">${orgSection}${unpSection}${footer}</div>`;
}

/* ── standalone CSS for new window ── */
function _rptCss() {
  return `*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;background:#e2e8f0;padding:24px 20px;color:#1e293b;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rpt-toolbar{max-width:860px;margin:0 auto 16px;display:flex;align-items:center;gap:10px}
.rpt-toolbar h1{font-size:14px;font-weight:700;color:#475569;flex:1}
.rpt-tbtn{padding:9px 20px;border-radius:9px;border:none;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;font-family:inherit;transition:.13s}
.rpt-tbtn-pdf{background:#4f46e5;color:#fff;box-shadow:0 3px 10px rgba(79,70,229,.3)}.rpt-tbtn-pdf:hover{background:#4338ca}
.rpt-tbtn-close{background:#fff;color:#64748b;border:1.5px solid #e2e8f0}.rpt-tbtn-close:hover{border-color:#94a3b8}
.rpt-paper{background:#fff;width:100%;max-width:860px;margin:0 auto;border-radius:4px;box-shadow:0 4px 40px rgba(0,0,0,.22);overflow:hidden}
/* cover */
.rpt-cover{background:linear-gradient(135deg,#1e1b4b 0%,#312e81 42%,#4f46e5 78%,#6366f1 100%);color:#fff;padding:40px;position:relative;overflow:hidden;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rpt-cover::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(45deg,rgba(255,255,255,.02) 0,rgba(255,255,255,.02) 1px,transparent 1px,transparent 8px)}
.rpt-cover-inner{position:relative}
.rpt-cover-row{display:flex;align-items:flex-start;gap:18px;margin-bottom:22px}
.rpt-cover-ic{width:58px;height:58px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;border:2px solid rgba(255,255,255,.2)}
.rpt-doc-label{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;opacity:.55;margin-bottom:7px}
.rpt-cover-title{font-size:24px;font-weight:900;line-height:1.25;margin-bottom:6px}
.rpt-cover-sub{font-size:12.5px;opacity:.78;display:flex;align-items:center;flex-wrap:wrap;gap:4px}
.rpt-cover-sub i{opacity:.7;font-size:10px}
.rpt-divider{border:none;border-top:1px solid rgba(255,255,255,.2);margin:18px 0}
.rpt-cover-stats{display:flex;gap:0}
.rpt-cs{flex:1;text-align:center;border-right:1px solid rgba(255,255,255,.15);padding:4px 8px}
.rpt-cs:last-child{border-right:none}
.rpt-cs-v{font-size:28px;font-weight:900;line-height:1;margin-bottom:5px}
.rpt-cs-l{font-size:9.5px;opacity:.6;text-transform:uppercase;letter-spacing:.5px}
.rpt-cs-v.ok{color:#86efac}.rpt-cs-v.warn{color:#fbbf24}.rpt-cs-v.danger{color:#f87171}
/* body */
.rpt-body{}
.rpt-sec{padding:26px 40px;border-bottom:1px solid #f1f5f9}
.rpt-sec:last-child{border-bottom:none}
.rpt-sec-hdr{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #f1f5f9}
.rpt-sec-hdr-ic{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.rpt-sec-hdr-title{font-size:14px;font-weight:800;color:#1e293b;flex:1}
.rpt-sec-hdr-cnt{font-size:11px;font-weight:700;color:#64748b;background:#f1f5f9;border-radius:6px;padding:3px 10px}
/* table */
.rpt-tbl{width:100%;border-collapse:collapse;font-size:11.5px}
.rpt-tbl th{padding:8px 10px;text-align:left;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:2px solid #e2e8f0;white-space:nowrap;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rpt-tbl td{padding:7px 10px;border-bottom:1px solid #f8fafc;vertical-align:middle}
.rpt-tbl tr:last-child td{border-bottom:none}
.rpt-tbl tbody tr:hover{background:#fafbff}
/* footer */
.rpt-footer{padding:26px 40px 32px;background:#f8fafc;border-top:2px solid #e2e8f0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.rpt-footer-title{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:20px;display:flex;align-items:center;gap:7px}
.rpt-footer-sigs{display:grid;grid-template-columns:1fr 1fr;gap:48px;margin-bottom:20px}
.rpt-sig-role{font-size:10.5px;font-weight:700;color:#64748b;margin-bottom:28px}
.rpt-sig-line{border-top:1.5px solid #94a3b8;margin-bottom:6px}
.rpt-sig-field{font-size:10px;color:#94a3b8;margin-top:4px}
.rpt-sig-blank{display:inline-block;border-bottom:1px solid #cbd5e1;min-width:120px;margin-left:4px;vertical-align:bottom}
.rpt-generated{font-size:9.5px;color:#94a3b8;text-align:center;padding-top:16px;border-top:1px solid #e2e8f0}
/* print */
@page{size:A4 portrait;margin:12mm 14mm}
@media print{
  body{background:none!important;padding:0!important}
  .rpt-toolbar{display:none!important}
  .rpt-paper{box-shadow:none!important;border-radius:0!important;max-width:none!important}
  .rpt-cover{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  .rpt-footer{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  .rpt-tbl th{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  .rpt-sec{break-inside:avoid;page-break-inside:avoid}
  .rpt-tbl tr{break-inside:avoid;page-break-inside:avoid}
}`;
}

/* ══════════════════════════════════════════════
   BATCH SELECTION DETAILED REPORT
   ══════════════════════════════════════════════ */
function openBatchRpt() {
  if (!S.selected.size) return;
  const cs = S.containers.filter(c => S.selected.has(parseInt(c.id)));
  if (!cs.length) return;
  const html = _selRptFullHtml(cs);
  const win = window.open('', '_blank');
  if (!win) { showToast(TH?'กรุณาอนุญาต Popup เพื่อเปิดรายงาน':'Allow popups to open the report','err'); return; }
  win.document.write(html);
  win.document.close();
}

function _selRptDetailBlock(c, i) {
  const st = _rptSt(c);
  const stHtml = _rptStHtml(st);
  const isUnp = !c.cabinet_id;

  const locParts = [];
  if (c.cabinet_name||c.cabinet_code) locParts.push(escH(c.cabinet_name||c.cabinet_code));
  if (c.shelf_name||c.shelf_code)     locParts.push(escH(c.shelf_name||c.shelf_code));
  if (c.slot_code||c.slot_name)       locParts.push(escH(c.slot_code||c.slot_name));
  const locPath = locParts.length ? locParts.join(' › ') : (TH?'ยังไม่จัดเก็บ':'Unplaced');

  const pct = (c.initial_quantity != null && parseFloat(c.initial_quantity) > 0)
    ? Math.min(100, Math.max(0, parseFloat(c.current_quantity||0) / parseFloat(c.initial_quantity) * 100))
    : Math.min(100, Math.max(0, parseFloat(c.remaining_percentage||0)));
  const qtyColor = pct>50?'#22c55e':pct>20?'#f59e0b':'#ef4444';

  let ghsHtml = '';
  try {
    const codes = JSON.parse(c.hazard_pictograms||'[]');
    if (codes.length) {
      const names = {GHS01:'Explosive',GHS02:'Flammable',GHS03:'Oxidizing',GHS04:'Compressed Gas',GHS05:'Corrosive',GHS06:'Acute Toxic',GHS07:'Irritant',GHS08:'Health Hazard',GHS09:'Environmental'};
      ghsHtml = codes.map(g=>`<span style="display:inline-flex;align-items:center;gap:3px;background:#fff3cd;color:#92400e;border-radius:4px;padding:1px 7px;font-size:9px;font-weight:700">${escH(g)}${names[g]?` · ${names[g]}`:''}</span>`).join(' ');
    }
  } catch(e){}

  const expiryColor = st==='exp'?'#dc2626':st==='warn'?'#d97706':'#475569';
  const expiryWeight = (st==='exp'||st==='warn')?700:400;

  return `<div class="dbl-card${isUnp?' dbl-unp':''}">
  <div class="dbl-hdr">
    <div class="dbl-num">${i+1}</div>
    <div class="dbl-hdr-main">
      <div class="dbl-name">${escH((c.chem_name||c.chemical_name||c.bottle_code||'—').substring(0,60))}</div>
      ${c.nickname?`<div class="dbl-nickname"><i class="fas fa-tag" style="font-size:8px;opacity:.8"></i> ${escH(c.nickname)}</div>`:''}
      ${c.molecular_formula?`<div class="dbl-formula">${escH(c.molecular_formula)}</div>`:''}
    </div>
    <div class="dbl-hdr-right">
      ${stHtml}
      <div class="dbl-loc${isUnp?' dbl-loc-unp':''}">${isUnp?'<i class="fas fa-inbox" style="font-size:9px"></i>':'<i class="fas fa-map-marker-alt" style="font-size:9px"></i>'} ${locPath}</div>
    </div>
  </div>
  <div class="dbl-body">
    <div class="dbl-col">
      <div class="dbl-sl">${TH?'ข้อมูลสารเคมี':'Chemical Info'}</div>
      ${c.cas_number?`<div class="dbl-kv"><span class="dbl-k">CAS</span><span class="dbl-v dbl-mono">${escH(c.cas_number)}</span></div>`:''}
      ${c.bottle_code?`<div class="dbl-kv"><span class="dbl-k">${TH?'รหัสขวด':'Bottle Code'}</span><span class="dbl-v dbl-mono">${escH(c.bottle_code)}</span></div>`:''}
      ${c.container_type?`<div class="dbl-kv"><span class="dbl-k">${TH?'ประเภท':'Type'}</span><span class="dbl-v">${escH(c.container_type)}</span></div>`:''}
      ${c.grade?`<div class="dbl-kv"><span class="dbl-k">Grade</span><span class="dbl-v">${escH(c.grade)}</span></div>`:''}
      ${c.physical_state?`<div class="dbl-kv"><span class="dbl-k">${TH?'สถานะสาร':'State'}</span><span class="dbl-v">${escH(c.physical_state)}</span></div>`:''}
      ${c.signal_word?`<div class="dbl-kv"><span class="dbl-k">Signal Word</span><span class="dbl-v">${escH(c.signal_word)}</span></div>`:''}
    </div>
    <div class="dbl-col">
      <div class="dbl-sl">${TH?'ปริมาณและวันที่':'Quantity & Dates'}</div>
      <div class="dbl-qty-wrap">
        <div style="margin-bottom:5px">${_rptQtyHtml(c)}</div>
        <div style="background:#e2e8f0;border-radius:3px;height:5px;overflow:hidden">
          <div style="background:${qtyColor};width:${pct}%;height:100%;border-radius:3px;-webkit-print-color-adjust:exact;print-color-adjust:exact"></div>
        </div>
      </div>
      ${c.received_date?`<div class="dbl-kv"><span class="dbl-k">${TH?'วันรับ':'Received'}</span><span class="dbl-v">${new Date(c.received_date).toLocaleDateString('th-TH',{day:'numeric',month:'short',year:'2-digit'})}</span></div>`:''}
      ${c.expiry_date?`<div class="dbl-kv"><span class="dbl-k">${TH?'วันหมดอายุ':'Expiry'}</span><span class="dbl-v" style="color:${expiryColor};font-weight:${expiryWeight}">${new Date(c.expiry_date).toLocaleDateString('th-TH',{day:'numeric',month:'short',year:'2-digit'})}</span></div>`:''}
      ${c.invoice_number?`<div class="dbl-kv"><span class="dbl-k">Invoice</span><span class="dbl-v dbl-mono">${escH(c.invoice_number)}</span></div>`:''}
    </div>
    <div class="dbl-col">
      <div class="dbl-sl">${TH?'ข้อมูลเพิ่มเติม':'Additional Info'}</div>
      ${c.owner_name?`<div class="dbl-kv"><span class="dbl-k">${TH?'ผู้รับผิดชอบ':'Owner'}</span><span class="dbl-v">${escH(c.owner_name)}</span></div>`:''}
      ${c.manufacturer_name?`<div class="dbl-kv"><span class="dbl-k">${TH?'ผู้ผลิต':'Manufacturer'}</span><span class="dbl-v">${escH(c.manufacturer_name)}</span></div>`:''}
      ${c.lab_name?`<div class="dbl-kv"><span class="dbl-k">Lab</span><span class="dbl-v">${escH(c.lab_name)}</span></div>`:''}
      ${ghsHtml?`<div class="dbl-kv" style="align-items:flex-start"><span class="dbl-k">GHS</span><span class="dbl-v" style="display:flex;flex-wrap:wrap;gap:3px">${ghsHtml}</span></div>`:''}
    </div>
  </div>
  ${(c.container_notes||c.room_note)?`<div class="dbl-foot">
    ${c.container_notes?`<div class="dbl-note"><i class="fas fa-sticky-note" style="font-size:9px;opacity:.6;flex-shrink:0;margin-top:2px"></i><span><strong>${TH?'หมายเหตุ':'Notes'}:</strong> ${escH(c.container_notes.substring(0,200))}</span></div>`:''}
    ${c.room_note?`<div class="dbl-note dbl-note-room"><i class="fas fa-door-open" style="font-size:9px;opacity:.6;flex-shrink:0;margin-top:2px"></i><span><strong>${TH?'บันทึกประจำห้อง':'Room Note'}:</strong> ${escH(c.room_note.substring(0,200))}</span></div>`:''}
  </div>`:''}
</div>`;
}

function _selRptCss() {
  return `
.dbl-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:16px;page-break-inside:avoid;break-inside:avoid}
.dbl-card.dbl-unp{border-left:4px solid #f59e0b}
.dbl-hdr{display:flex;align-items:flex-start;gap:12px;padding:14px 16px 12px;background:#f8fafc;border-bottom:1px solid #f1f5f9}
.dbl-num{min-width:24px;height:24px;background:#6366f1;color:#fff;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;margin-top:2px}
.dbl-hdr-main{flex:1;min-width:0}
.dbl-name{font-size:14px;font-weight:800;color:#1e293b;line-height:1.3;word-break:break-word}
.dbl-nickname{font-size:10.5px;color:#6366f1;font-weight:600;margin-top:3px;display:flex;align-items:center;gap:4px}
.dbl-formula{font-size:10px;color:#94a3b8;font-family:monospace;margin-top:2px}
.dbl-hdr-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0}
.dbl-loc{font-size:9.5px;font-weight:600;color:#475569;background:#eef2ff;border-radius:5px;padding:2px 8px;white-space:nowrap;max-width:200px;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:4px}
.dbl-loc-unp{background:#fffbeb;color:#d97706}
.dbl-body{display:grid;grid-template-columns:1fr 1fr 1fr}
.dbl-col{padding:12px 16px;border-right:1px solid #f1f5f9}
.dbl-col:last-child{border-right:none}
.dbl-sl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid #f1f5f9}
.dbl-kv{display:flex;gap:6px;margin-bottom:5px;align-items:baseline;font-size:10.5px}
.dbl-k{color:#94a3b8;font-weight:600;min-width:72px;flex-shrink:0;font-size:9.5px}
.dbl-v{color:#1e293b;font-weight:500;word-break:break-word;font-size:10.5px}
.dbl-mono{font-family:monospace;font-size:10px}
.dbl-qty-wrap{background:#f8fafc;border-radius:6px;padding:8px 10px;margin-bottom:8px;border:1px solid #f1f5f9}
.dbl-foot{padding:10px 16px;background:#fafbff;border-top:1px solid #f1f5f9;display:flex;flex-direction:column;gap:6px}
.dbl-note{font-size:10px;color:#64748b;display:flex;align-items:flex-start;gap:6px;line-height:1.55}
.dbl-note-room{color:#7c3aed}
@media print{
  .dbl-card{page-break-inside:avoid;break-inside:avoid}
  .dbl-hdr,.dbl-body,.dbl-col,.dbl-foot,.dbl-qty-wrap{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}`;
}

function _buildSelRptPaper(cs) {
  const room    = S.rooms.find(r=>parseInt(r.room_id)===S.activeRoomId)||{};
  const today   = new Date().toLocaleDateString('th-TH',{day:'numeric',month:'long',year:'numeric'});
  const gen     = new Date().toLocaleString('th-TH',{day:'numeric',month:'short',year:'2-digit',hour:'2-digit',minute:'2-digit'});
  const roomLbl = [room.code, room.name].filter(Boolean).join(' — ');
  const bldLbl  = room.bld_short||room.bld_name||'';
  const exp60   = cs.filter(c=>_rptSt(c)==='warn').length;
  const expired = cs.filter(c=>_rptSt(c)==='exp').length;

  const cover = `<div class="rpt-cover">
  <div class="rpt-cover-inner">
    <div class="rpt-cover-row">
      <div class="rpt-cover-ic"><i class="fas fa-clipboard-check"></i></div>
      <div>
        <div class="rpt-doc-label">Detailed Chemical Report · Selected Items</div>
        <div class="rpt-cover-title">${TH?'รายงานละเอียดสารที่เลือก':'Detailed Report – Selected Items'}</div>
        <div class="rpt-cover-sub">
          ${bldLbl?`<i class="fas fa-building"></i> ${escH(bldLbl)} &nbsp;›&nbsp; `:''}
          <i class="fas fa-door-open"></i> ${escH(roomLbl)}
          &nbsp;&nbsp;·&nbsp;&nbsp;
          <i class="fas fa-calendar-alt"></i> ${today}
        </div>
      </div>
    </div>
    <hr class="rpt-divider">
    <div class="rpt-cover-stats">
      <div class="rpt-cs"><div class="rpt-cs-v">${cs.length}</div><div class="rpt-cs-l">${TH?'รายการที่เลือก':'Selected'}</div></div>
      <div class="rpt-cs"><div class="rpt-cs-v ${exp60?'warn':''}">${exp60}</div><div class="rpt-cs-l">${TH?'ใกล้หมดอายุ':'Expiring'}</div></div>
      <div class="rpt-cs"><div class="rpt-cs-v ${expired?'danger':''}">${expired}</div><div class="rpt-cs-l">${TH?'หมดอายุแล้ว':'Expired'}</div></div>
    </div>
  </div>
</div>`;

  const blocks = cs.map((c,i)=>_selRptDetailBlock(c,i)).join('');

  const footer = `<div class="rpt-footer">
  <div class="rpt-footer-title"><i class="fas fa-pen-alt"></i> ${TH?'ลงนามรับรอง':'Authorized Signatures'}</div>
  <div class="rpt-footer-sigs">
    <div class="rpt-sig"><div class="rpt-sig-role">${TH?'ผู้จัดทำรายงาน':'Prepared by'}</div><div class="rpt-sig-line"></div><div class="rpt-sig-field">${TH?'ชื่อ-นามสกุล':'Name'}: <span class="rpt-sig-blank"></span></div><div class="rpt-sig-field">${TH?'วันที่':'Date'}: <span class="rpt-sig-blank"></span></div></div>
    <div class="rpt-sig"><div class="rpt-sig-role">${TH?'ผู้ตรวจสอบ / อนุมัติ':'Verified & Approved by'}</div><div class="rpt-sig-line"></div><div class="rpt-sig-field">${TH?'ชื่อ-นามสกุล':'Name'}: <span class="rpt-sig-blank"></span></div><div class="rpt-sig-field">${TH?'วันที่':'Date'}: <span class="rpt-sig-blank"></span></div></div>
  </div>
  <div class="rpt-generated">${TH?'สร้างโดยระบบจัดการสารเคมี':'Generated by Chemical Management System'} &nbsp;·&nbsp; ${gen}</div>
</div>`;

  return cover + `<div class="rpt-body" style="padding:24px 40px">${blocks}${footer}</div>`;
}

function _selRptFullHtml(cs) {
  const room  = S.rooms.find(r=>parseInt(r.room_id)===S.activeRoomId)||{};
  const title = escH([room.code, room.name].filter(Boolean).join(' — '));
  return `<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>${TH?'รายงานละเอียด':'Detailed Report'} — ${title}</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>${_rptCss()}${_selRptCss()}</style>
</head>
<body>
  <div class="rpt-toolbar">
    <h1><i class="fas fa-clipboard-check" style="color:#10b981;margin-right:7px"></i>${TH?'รายงานละเอียดสารที่เลือก':'Detailed Report – Selected Items'} <span style="font-size:11px;font-weight:600;color:#94a3b8;margin-left:6px">(${cs.length} ${TH?'รายการ':'items'})</span></h1>
    <button class="rpt-tbtn rpt-tbtn-pdf" onclick="window.print()"><i class="fas fa-print"></i> ${TH?'พิมพ์ / บันทึก PDF':'Print / Save PDF'}</button>
    <button class="rpt-tbtn rpt-tbtn-close" onclick="window.close()"><i class="fas fa-times"></i> ${TH?'ปิด':'Close'}</button>
  </div>
  <div class="rpt-paper">${_buildSelRptPaper(cs)}</div>
</body>
</html>`;
}

/* ── assemble complete standalone HTML ── */
function _rptFullHtml(cs, cabs, fields) {
  const room  = S.rooms.find(r=>parseInt(r.room_id)===S.activeRoomId)||{};
  const title = escH([room.code, room.name].filter(Boolean).join(' — '));
  const paper = _buildRptPaper(cs, cabs, fields);
  return `<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>${TH?'รายงานตำแหน่งสารเคมี':'Chemical Report'} — ${title}</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>${_rptCss()}</style>
</head>
<body>
  <div class="rpt-toolbar print-hide">
    <h1><i class="fas fa-clipboard-list" style="color:#6366f1;margin-right:6px"></i>${TH?'รายงานตำแหน่งสารเคมี':'Chemical Location Report'} &nbsp;·&nbsp; ${title}</h1>
    <button class="rpt-tbtn rpt-tbtn-pdf" onclick="window.print()"><i class="fas fa-file-pdf"></i> Export PDF</button>
    <button class="rpt-tbtn rpt-tbtn-close" onclick="window.close()"><i class="fas fa-times"></i> ${TH?'ปิด':'Close'}</button>
  </div>
  <div class="rpt-paper">${paper}</div>
</body>
</html>`;
}

init();

/* ════════════════════════════════════════════════════════════
   SAFETY REPORT
════════════════════════════════════════════════════════════ */
const SR_HEALTH_LABELS = {
  acute_toxicity:  'Acute toxicity (ความเป็นพิษเฉียบพลัน)',
  aspiration:      'Aspiration hazardous (อันตรายต่อระบบทางเดินหายใจส่วนล่าง)',
  carcinogenicity: 'Carcinogenicity (ความสามารถในการก่อมะเร็ง)',
  germ_cell:       'Germ cell mutagenicity (การกลายพันธุ์ของเซลล์สืบพันธุ์)',
  reproductive:    'Reproductive toxicity (ความเป็นพิษต่อระบบสืบพันธุ์)',
  sensitization:   'Respiratory or skin sensitization (การทำให้ไวต่อการกระตุ้นอากาศแพ้ทางเดินหายใจหรือผิวหนัง)',
  eye_damage:      'Serious eye damage/eye irritation (การทำลายดวงตาอย่างรุนแรง/การระคายเคืองต่อดวงตา)',
  skin_corrosion:  'Skin corrosion/irritation (การกัดกร่อน/ระคายเคืองผิวหนัง)',
  stot_repeated:   'Specific target organ toxicity – Repeated exposure (ความเป็นพิษต่อระบบอวัยวะเป้าหมาย การได้รับสัมผัสซ้ำ)',
  stot_single:     'Specific target organ toxicity – Single exposure (ความเป็นพิษต่อระบบอวัยวะเป้าหมาย การได้รับสัมผัสครั้งเดียว)',
};
const SR_PHYS_LABELS = {
  corrosive_metals:   'Corrosive to metals (สารที่กัดกร่อนโลหะ)',
  explosives:         'Explosives (วัตถุระเบิด)',
  flammable_gas:      'Flammable (แก็สไวไฟ)',
  flammable_aerosols: 'Flammable aerosols (สารละอองลอยไวไฟ)',
  flammable_liquids:  'Flammable liquids (ของเหลวไวไฟ)',
  flammable_solids:   'Flammable solids (ของแข็งไวไฟ)',
  gas_pressure:       'Gas under pressure (แก็สภายใต้ความดัน)',
  organic_peroxides:  'Organic peroxides (สารเปอร์ออกไซด์อินทรีย์)',
  oxidizing_gases:    'Oxidizing gases (แก็สออกซิไดซ์)',
  oxidizing_liquids:  'Oxidizing liquids (ของเหลวออกซิไดซ์)',
  oxidizing_solids:   'Oxidizing solids (ของแข็งออกซิไดซ์)',
  pyrophoric_liquids: 'Pyrophoric liquids (ของเหลวที่ลุกติดไฟได้เองในอากาศ)',
  pyrophoric_solids:  'Pyrophoric solids (ของแข็งที่ลุกติดไฟได้เองในอากาศ)',
  self_heating:       'Self-heating substances and mixtures (สารเคมีที่เกิดความร้อนได้เอง)',
  self_reactive:      'Self-reactive substances and mixtures (สารเคมีที่ทำปฏิกิริยาได้เอง)',
  water_reactive:     'Substances and mixtures, which in contact with water, emit flammable gases (สารเคมีที่สัมผัสน้ำแล้วให้แก็สไวไฟ)',
};
const SR_COLORS = [
  '#2563eb','#ea580c','#16a34a','#d97706','#7c3aed','#dc2626',
  '#0891b2','#65a30d','#9333ea','#e11d48','#6366f1','#f59e0b',
  '#10b981','#ef4444','#8b5cf6','#f97316','#0d9488','#db2777',
];
const SR_HEALTH_SHORT = {
  acute_toxicity:'Acute Tox.', aspiration:'Aspiration', carcinogenicity:'Carc.',
  germ_cell:'Muta.', reproductive:'Repr.', sensitization:'Sensitiz.',
  eye_damage:'Eye Dam.', skin_corrosion:'Skin Corr.',
  stot_repeated:'STOT-RE', stot_single:'STOT-SE',
};
const SR_PHYS_SHORT = {
  corrosive_metals:'Metal Corr.', explosives:'Explos.', flammable_gas:'Flam. Gas',
  flammable_aerosols:'Flam. Aerosol', flammable_liquids:'Flam. Liq.',
  flammable_solids:'Flam. Sol.', gas_pressure:'Gas Pres.',
  organic_peroxides:'Org. Perox.', oxidizing_gases:'Ox. Gas',
  oxidizing_liquids:'Ox. Liq.', oxidizing_solids:'Ox. Sol.',
  pyrophoric_liquids:'Pyrop. Liq.', pyrophoric_solids:'Pyrop. Sol.',
  self_heating:'Self-Heat.', self_reactive:'Self-React.',
  water_reactive:'Water React.',
};

let srData = null, srActiveTab = 'health';

async function openSafetyReport() {
  const roomId = S.activeRoomId;
  if (!roomId) { showToast(TH?'กรุณาเลือกห้องก่อน':'Please select a room first','warn'); return; }
  const room = S.rooms.find(r => parseInt(r.room_id) === roomId) || {};
  const overlay = document.getElementById('srOverlay');
  document.getElementById('srTitle').textContent = TH ? 'รายงานความปลอดภัยสารเคมี' : 'Chemical Safety Report';
  document.getElementById('srSub').innerHTML = `<span><i class="fas fa-door-open"></i> ${escH(room.code||'')} ${escH(room.name||'')}</span><span><i class="fas fa-building"></i> ${escH(room.bld_name||'')}</span><span><i class="fas fa-calendar-alt"></i> ${new Date().toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'})}</span>`;
  document.getElementById('srBody').innerHTML = `<div class="sr-loading"><i class="fas fa-spinner fa-spin" style="font-size:24px;margin-bottom:10px;display:block"></i>${TH?'กำลังโหลด...':'Loading...'}</div>`;
  srActiveTab = 'health';
  document.getElementById('srTab-health').classList.add('active');
  document.getElementById('srTab-physical').classList.remove('active');
  overlay.classList.add('show');

  try {
    const res = await apiFetch(`/v1/api/myroom.php?action=safety_report&room_id=${roomId}`);
    if (!res.success) throw new Error(res.error || 'Failed');
    srData = res.data;
    srRender();
  } catch(e) {
    document.getElementById('srBody').innerHTML = `<div class="sr-empty"><i class="fas fa-exclamation-triangle"></i>${e.message}</div>`;
  }
}

function closeSafetyReport() {
  document.getElementById('srOverlay').classList.remove('show');
  srCloseChemPopup();
}

function srSwitchTab(tab) {
  srActiveTab = tab;
  document.querySelectorAll('.sr-tab').forEach(t => t.classList.remove('active'));
  document.getElementById('srTab-' + tab).classList.add('active');
  document.querySelectorAll('.sr-panel').forEach(p => p.classList.remove('active'));
  const panel = document.getElementById('srPanel-' + tab);
  if (panel) panel.classList.add('active');
}

function srFmt(v) { return v <= 0 ? '0.00' : v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }

function srBuildRows(dataObj, labels, shortLabels) {
  const keys = Object.keys(labels);
  let rows = [], totSolid = 0, totLiq = 0, totGas = 0;
  for (const key of keys) {
    const d = dataObj[key] || {solid:0,liquid:0,gas:0};
    const s = d.solid||0, l = d.liquid||0, g = d.gas||0, t = s+l+g;
    totSolid += s; totLiq += l; totGas += g;
    const shortLabel = shortLabels?.[key] || labels[key].split(' (')[0].slice(0, 16);
    rows.push({key, label: labels[key], shortLabel, solid:s, liquid:l, gas:g, total:t});
  }
  return {rows, totSolid, totLiq, totGas, totTotal: totSolid+totLiq+totGas};
}

function srBuildTable(rows, totSolid, totLiq, totGas, totTotal) {
  const colSolid = TH ? 'ของแข็ง (kg)' : 'Solid (kg)';
  const colLiq   = TH ? 'ของเหลว (kg)' : 'Liquid (kg)';
  const colGas   = TH ? 'ก๊าซ (kg)' : 'Gas (kg)';
  const colTotal = TH ? 'รวม (kg)' : 'Total (kg)';
  const rowsHtml = rows.map((r, i) => {
    const hasData = r.total > 0;
    const c = (v) => v > 0 ? `<td class="nonzero">${srFmt(v)}</td>` : `<td>${srFmt(v)}</td>`;
    return `<tr class="sr-anim-trow${hasData?' has-data':''}" style="animation-delay:${i*22}ms"><td style="font-size:12px">- ${escH(r.label)}</td>${c(r.solid)}${c(r.liquid)}${c(r.gas)}${c(r.total)}</tr>`;
  }).join('');
  return `
    <div class="sr-tbl-wrap">
    <table class="sr-tbl">
      <thead><tr>
        <th style="width:42%">${TH?'ประเภทความอันตราย':'Hazard Type'}</th>
        <th style="width:14.5%">${colSolid}</th><th style="width:14.5%">${colLiq}</th>
        <th style="width:14.5%">${colGas}</th><th style="width:14.5%">${colTotal}</th>
      </tr></thead>
      <tbody>${rowsHtml}</tbody>
      <tfoot><tr>
        <td>${TH?'ปริมาณรวมทั้งหมด (kg)*:':'Total quantity (kg)*:'}</td>
        <td>${srFmt(totSolid)}</td><td>${srFmt(totLiq)}</td><td>${srFmt(totGas)}</td><td>${srFmt(totTotal)}</td>
      </tr></tfoot>
    </table>
    </div>
    <p class="sr-note">* ${TH?'ปริมาณแสดงในหน่วย kg โดยประมาณ โดยสมมติความหนาแน่น 1 kg/L สำหรับของเหลว และ 1 g/mL สำหรับหน่วยปริมาตร':'Quantities are approximate kg values assuming density of 1 kg/L for liquids and 1 g/mL for volume units.'}</p>`;
}

function srBuildChart(rows, tab) {
  const nonZero = rows.filter(r => r.total > 0);
  const total = nonZero.reduce((s,r) => s + r.total, 0);
  if (!total || !nonZero.length) return `<div class="sr-empty"><i class="fas fa-chart-pie"></i>${TH?'ไม่มีข้อมูล GHS สำหรับห้องนี้':'No GHS data available'}</div>`;

  const THRESHOLD = 0.015;
  let main = nonZero.filter(r => r.total/total >= THRESHOLD).map((r,i) => ({...r, color: SR_COLORS[i % SR_COLORS.length]}));
  const othTotal = nonZero.filter(r => r.total/total < THRESHOLD).reduce((s,r) => s+r.total, 0);
  if (othTotal > 0) main.push({key:'others', label:TH?'อื่นๆ':'Others', shortLabel:TH?'อื่นๆ':'Others', total:othTotal, color:'#94a3b8'});

  // ── SVG dimensions ─────────────────────────────────────
  // cx biased left to give right-side labels more room
  const W=560, H=320, cx=200, cy=160, OR=110, IR=66;
  const ARC_DOT_R = OR + 6;   // dot attachment radius
  const LABEL_R   = OR + 22;  // initial connector radius

  // ── Slices ─────────────────────────────────────────────
  let angle = -Math.PI / 2;
  const slices = main.map((r, i) => {
    const sa = angle, sw = (r.total/total) * Math.PI * 2;
    angle += sw;
    return {...r, sa, ea: angle, sw, mid: sa + sw/2, idx: i};
  });

  // ── Draw paths with staggered pop animation ────────────
  let paths = '';
  for (const s of slices) {
    const delay = s.idx * 55;
    // Full-circle arc degenerates (start=end point) → use two circles instead
    const clickable = s.key !== 'others';
    const oc = clickable ? ` onclick="srSliceClick('${s.key}','${tab}','${s.color}')"` : '';
    if (s.sw >= Math.PI * 2 - 0.001) {
      paths += `<circle class="sr-slice-path sr-anim-slice" cx="${cx}" cy="${cy}" r="${OR}"
        fill="${s.color}" stroke="#fff" stroke-width="2.5"${oc}
        style="transform-origin:${cx}px ${cy}px;animation-delay:${delay}ms${clickable?';cursor:pointer':''}">
        <title>${escH(s.label)}: 100%</title></circle>`;
    } else {
      const x1=cx+OR*Math.cos(s.sa), y1=cy+OR*Math.sin(s.sa);
      const x2=cx+OR*Math.cos(s.ea), y2=cy+OR*Math.sin(s.ea);
      const xi1=cx+IR*Math.cos(s.sa),yi1=cy+IR*Math.sin(s.sa);
      const xi2=cx+IR*Math.cos(s.ea),yi2=cy+IR*Math.sin(s.ea);
      const lg = s.sw > Math.PI ? 1 : 0;
      paths += `<path class="sr-slice-path sr-anim-slice" data-key="${s.key}"
        d="M${xi1.toFixed(1)} ${yi1.toFixed(1)} L${x1.toFixed(1)} ${y1.toFixed(1)} A${OR} ${OR} 0 ${lg} 1 ${x2.toFixed(1)} ${y2.toFixed(1)} L${xi2.toFixed(1)} ${yi2.toFixed(1)} A${IR} ${IR} 0 ${lg} 0 ${xi1.toFixed(1)} ${yi1.toFixed(1)}Z"
        fill="${s.color}" stroke="#fff" stroke-width="2.5"${oc}
        style="transform-origin:${cx}px ${cy}px;animation-delay:${delay}ms">
        <title>${escH(s.label)}: ${((s.total/total)*100).toFixed(1)}%</title>
      </path>`;
    }
  }

  // ── Label placement with collision avoidance ───────────
  const RIGHT = slices.filter(s => Math.cos(s.mid) >= 0).sort((a,b) => Math.sin(a.mid)-Math.sin(b.mid));
  const LEFT  = slices.filter(s => Math.cos(s.mid) < 0) .sort((a,b) => Math.sin(a.mid)-Math.sin(b.mid));

  function stackY(group) {
    const MIN_GAP = 14;
    const pts = group.map(s => ({ ...s, y: cy + LABEL_R * Math.sin(s.mid) }));
    for (let pass = 0; pass < 10; pass++) {
      for (let i = 1; i < pts.length; i++) {
        const diff = pts[i].y - pts[i-1].y;
        if (diff < MIN_GAP) {
          const push = (MIN_GAP - diff) / 2;
          pts[i-1].y -= push;
          pts[i].y   += push;
        }
      }
    }
    pts.forEach(p => { p.y = Math.max(10, Math.min(H-10, p.y)); });
    return pts;
  }

  const rightPts = stackY(RIGHT);
  const leftPts  = stackY(LEFT);

  // Anchor x for label text
  const R_ANCHOR = W - 6;
  const L_ANCHOR = 6;

  let labels = '';

  // ── Special case: single 100% slice — label inside hole ─
  if (main.length === 1) {
    const s = main[0];
    const lbl = s.shortLabel || s.label.split(' (')[0].slice(0, 18);
    labels = `<g style="opacity:0;animation:sr-fade-in .4s ease 500ms forwards">
      <text x="${cx}" y="${cy + 46}" text-anchor="middle" font-size="10" font-weight="700" fill="${s.color}" font-family="Sarabun,sans-serif">${escH(lbl)}</text>
      <text x="${cx}" y="${cy + 60}" text-anchor="middle" font-size="9" fill="#94a3b8" font-family="Sarabun,sans-serif">100%</text>
    </g>`;
  } else {

  const ELBOW_EXTRA = 28;

  for (const p of rightPts) {
    const pct = ((p.total/total)*100).toFixed(1);
    const lbl = p.shortLabel || p.label.split(' (')[0].slice(0,14);
    const dotX = (cx + ARC_DOT_R * Math.cos(p.mid)).toFixed(1);
    const dotY = (cy + ARC_DOT_R * Math.sin(p.mid)).toFixed(1);
    const elbX = Math.min(cx + LABEL_R * Math.cos(p.mid) + ELBOW_EXTRA, W - 130).toFixed(1);
    const lineX = (R_ANCHOR - 5).toFixed(1);
    const delay = (p.idx * 55 + 300);
    labels += `<g style="opacity:0;animation:sr-fade-in .3s ease ${delay}ms forwards">
      <circle cx="${dotX}" cy="${dotY}" r="3" fill="${p.color}"/>
      <polyline points="${dotX},${dotY} ${elbX},${p.y.toFixed(1)} ${lineX},${p.y.toFixed(1)}" fill="none" stroke="${p.color}" stroke-width="1.1" opacity=".6"/>
      <text x="${R_ANCHOR}" y="${(p.y+3.5).toFixed(1)}" text-anchor="end" font-size="8.5" font-weight="600" fill="#334155" font-family="Sarabun,sans-serif">${escH(lbl)} ${pct}%</text>
    </g>`;
  }
  for (const p of leftPts) {
    const pct = ((p.total/total)*100).toFixed(1);
    const lbl = p.shortLabel || p.label.split(' (')[0].slice(0,14);
    const dotX = (cx + ARC_DOT_R * Math.cos(p.mid)).toFixed(1);
    const dotY = (cy + ARC_DOT_R * Math.sin(p.mid)).toFixed(1);
    const elbX = Math.max(cx + LABEL_R * Math.cos(p.mid) - ELBOW_EXTRA, 130).toFixed(1);
    const lineX = (L_ANCHOR + 5).toFixed(1);
    const delay = (p.idx * 55 + 300);
    labels += `<g style="opacity:0;animation:sr-fade-in .3s ease ${delay}ms forwards">
      <circle cx="${dotX}" cy="${dotY}" r="3" fill="${p.color}"/>
      <polyline points="${dotX},${dotY} ${elbX},${p.y.toFixed(1)} ${lineX},${p.y.toFixed(1)}" fill="none" stroke="${p.color}" stroke-width="1.1" opacity=".6"/>
      <text x="${L_ANCHOR}" y="${(p.y+3.5).toFixed(1)}" text-anchor="start" font-size="8.5" font-weight="600" fill="#334155" font-family="Sarabun,sans-serif">${escH(lbl)} ${pct}%</text>
    </g>`;
  }

  } // end else (multi-slice)

  // ── Center label ───────────────────────────────────────
  const ctVal  = total >= 1000 ? (total/1000).toFixed(1) : total.toFixed(1);
  const ctUnit = total >= 1000 ? 'tonnes' : 'kg';

  const svgStr = `<svg viewBox="0 0 ${W} ${H}" style="width:100%;height:auto;overflow:visible">
    <defs>
      <filter id="sr-inner-shadow" x="-10%" y="-10%" width="120%" height="120%">
        <feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#0f172a" flood-opacity=".12"/>
      </filter>
    </defs>
    <g>${paths}</g>
    <circle cx="${cx}" cy="${cy}" r="${IR-1}" fill="white" filter="url(#sr-inner-shadow)"/>
    <text x="${cx}" y="${cy-14}" text-anchor="middle" font-size="8.5" fill="#94a3b8" letter-spacing="1" font-family="Sarabun,sans-serif" font-weight="700">TOTAL</text>
    <text x="${cx}" y="${cy+9}" text-anchor="middle" font-size="22" font-weight="800" fill="#0f172a" font-family="Sarabun,sans-serif" class="sr-center-val">${escH(ctVal)}</text>
    <text x="${cx}" y="${cy+24}" text-anchor="middle" font-size="9.5" fill="#64748b" font-family="Sarabun,sans-serif">${ctUnit}</text>
    ${labels}
  </svg>`;

  // ── Animated legend ────────────────────────────────────
  const legendHtml = main.map((r, i) => {
    const pct = ((r.total/total)*100).toFixed(1);
    const delay = i * 45 + 200;
    return `<div class="sr-legend-row sr-anim-row" style="animation-delay:${delay}ms" title="${escH(r.label)}">
      <div class="sr-legend-dot" style="background:${r.color}"></div>
      <div class="sr-legend-name">${escH(r.shortLabel||r.label.split(' (')[0])}</div>
      <div class="sr-legend-bar"><div class="sr-legend-bar-fill sr-anim-bar" style="background:${r.color};width:${pct}%;animation-delay:${delay+300}ms"></div></div>
      <div class="sr-legend-pct">${pct}%</div>
      <div class="sr-legend-kg sr-counter" data-val="${r.total.toFixed(3)}">${srFmt(r.total)}</div>
    </div>`;
  }).join('');

  return `<div class="sr-chart-row">
    <div class="sr-chart-box">${svgStr}</div>
    <div class="sr-chart-legend">
      <div class="sr-chart-title"><i class="fas fa-circle-notch" style="margin-right:5px;opacity:.5"></i>${TH?'สัดส่วนตามประเภท':'By hazard type'}</div>
      <div class="sr-legend-list">${legendHtml}</div>
    </div>
  </div>`;
}

function srRender() {
  if (!srData) return;
  const {health_hazard, physical_hazard} = srData;

  const h = srBuildRows(health_hazard, SR_HEALTH_LABELS, SR_HEALTH_SHORT);
  const p = srBuildRows(physical_hazard, SR_PHYS_LABELS, SR_PHYS_SHORT);

  const hNonZero = h.rows.filter(r=>r.total>0).length;
  const pNonZero = p.rows.filter(r=>r.total>0).length;
  document.getElementById('srBadge-health').textContent   = hNonZero;
  document.getElementById('srBadge-physical').textContent = pNonZero;

  const healthHtml = `
    <div class="sr-tbl-section"><i class="fas fa-heartbeat" style="background:#fef2f2;color:#dc2626"></i>${TH?'Health Hazard — ความเป็นอันตรายทางสุขภาพ':'Health Hazard'}</div>
    ${srBuildTable(h.rows, h.totSolid, h.totLiq, h.totGas, h.totTotal)}
    ${srBuildChart(h.rows, 'health')}`;

  const physHtml = `
    <div class="sr-tbl-section"><i class="fas fa-bolt" style="background:#fffbeb;color:#d97706"></i>${TH?'Physical Hazard — ความเป็นอันตรายทางกายภาพ':'Physical Hazard'}</div>
    ${srBuildTable(p.rows, p.totSolid, p.totLiq, p.totGas, p.totTotal)}
    ${srBuildChart(p.rows, 'physical')}`;

  document.getElementById('srBody').innerHTML = `
    <div class="sr-panel active" id="srPanel-health">${healthHtml}</div>
    <div class="sr-panel" id="srPanel-physical">${physHtml}</div>`;

  // Animate counter numbers (kg values in legend)
  requestAnimationFrame(() => srAnimateCounters());
}

function srAnimateCounters() {
  const els = document.querySelectorAll('#srBody .sr-counter[data-val]');
  els.forEach(el => {
    const target = parseFloat(el.dataset.val);
    if (!target) return;
    const dur = 900, start = performance.now();
    function tick(now) {
      const t = Math.min((now - start) / dur, 1);
      const ease = 1 - Math.pow(1 - t, 3); // ease-out cubic
      el.textContent = srFmt(target * ease);
      if (t < 1) requestAnimationFrame(tick);
      else el.textContent = srFmt(target);
    }
    requestAnimationFrame(tick);
  });
}

// ── Chem Popup (slice click) ──────────────────────────────────────
let srCpCurKey = null, srCpCurTab = null, srCpCurColor = null, srCpTotalCount = 0;

const SR_STATE_IC = {
  solid:   {icon:'fa-cube',   bg:'#e0e7ff', fg:'#3730a3'},
  liquid:  {icon:'fa-tint',   bg:'#dbeafe', fg:'#1d4ed8'},
  gas:     {icon:'fa-wind',   bg:'#dcfce7', fg:'#15803d'},
};

function srCloseChemPopup() {
  const el = document.getElementById('srChemPopup');
  if (el) el.classList.remove('show');
  srCpCurKey = srCpCurTab = srCpCurColor = null;
}

async function srSliceClick(key, tab, color) {
  if (key === 'others') return;
  const roomId = S.activeRoomId;
  if (!roomId) return;
  srCpCurKey = key; srCpCurTab = tab; srCpCurColor = color;

  const popup  = document.getElementById('srChemPopup');
  const hdr    = document.getElementById('srCpHdr');
  const dot    = document.getElementById('srCpDot');
  const title  = document.getElementById('srCpTitle');
  const stats  = document.getElementById('srCpStats');
  const list   = document.getElementById('srCpList');
  const footer = document.getElementById('srCpFooter');

  // Derive label from known label maps
  const labelMap = tab === 'health' ? SR_HEALTH_LABELS : SR_PHYS_LABELS;
  const shortMap = tab === 'health' ? SR_HEALTH_SHORT  : SR_PHYS_SHORT;
  const label = (shortMap[key] || (labelMap[key]||key).split('(')[0]).trim();

  hdr.style.background = color;
  dot.style.background = 'rgba(255,255,255,0.35)';
  title.textContent = label;
  stats.innerHTML = '';
  list.innerHTML = `<div class="sr-cp-loading"><i class="fas fa-spinner fa-spin"></i></div>`;
  footer.style.display = 'none';
  popup.classList.remove('show');
  void popup.offsetWidth; // force reflow to restart animation
  popup.classList.add('show');

  try {
    const res = await apiFetch(`/v1/api/myroom.php?action=safety_chemicals&room_id=${roomId}&category=${encodeURIComponent(key)}&tab=${tab}&limit=5`);
    if (!res.success) throw new Error(res.error || 'fail');
    srShowChemPopup(res.data, label, color);
  } catch(e) {
    list.innerHTML = `<div class="sr-cp-loading" style="color:#ef4444"><i class="fas fa-exclamation-triangle"></i> ${TH?'โหลดไม่สำเร็จ':'Load failed'}</div>`;
  }
}

function srShowChemPopup(data, label, color) {
  const {chemicals, total_count, total_kg} = data;
  srCpTotalCount = total_count;

  document.getElementById('srCpStats').innerHTML =
    `<div class="sr-cp-stat"><i class="fas fa-flask" style="color:${color}"></i>${total_count} ${TH?'สาร':'chemicals'}</div>` +
    `<div class="sr-cp-stat"><i class="fas fa-weight" style="color:${color}"></i>${srFmt(total_kg)} kg ${TH?'รวม':'total'}</div>`;

  const listEl = document.getElementById('srCpList');
  if (!chemicals.length) {
    listEl.innerHTML = `<div class="sr-cp-loading" style="opacity:.6">${TH?'ไม่พบข้อมูลสาร':'No chemicals found'}</div>`;
    document.getElementById('srCpFooter').style.display = 'none';
    return;
  }

  listEl.innerHTML = chemicals.map((c, i) => {
    const ic = SR_STATE_IC[c.state] || SR_STATE_IC.solid;
    const hpills = (c.h_codes||[]).slice(0,3).map(h => `<span class="sr-cp-hpill">${escH(h)}</span>`).join('');
    const delay = i * 45;
    return `<div class="sr-cp-item" style="animation-delay:${delay}ms">
      <div class="sr-cp-ic" style="background:${ic.bg};color:${ic.fg}"><i class="fas ${ic.icon}"></i></div>
      <div class="sr-cp-name">
        <div class="sr-cp-chem-name" title="${escH(c.name)}">${escH(c.name)}</div>
        ${c.cas ? `<div class="sr-cp-cas">CAS ${escH(c.cas)}</div>` : '<div class="sr-cp-cas">—</div>'}
      </div>
      <div class="sr-cp-right">
        <div class="sr-cp-qty">${srFmt(c.qty_kg)} kg</div>
        <div class="sr-cp-hcodes">${hpills}</div>
      </div>
    </div>`;
  }).join('');

  const footer  = document.getElementById('srCpFooter');
  const moreBtn = document.getElementById('srCpMoreBtn');
  const moreLbl = document.getElementById('srCpMoreLbl');
  if (total_count > chemicals.length) {
    moreLbl.textContent = TH ? `ดูทั้งหมด ${total_count} รายการ` : `View all ${total_count} items`;
    footer.style.display = 'block';
    moreBtn.onclick = srLoadMoreChems;
  } else {
    footer.style.display = 'none';
  }
}

async function srLoadMoreChems() {
  if (!srCpCurKey || !srCpCurTab) return;
  const roomId = S.activeRoomId;
  if (!roomId) return;
  const btn = document.getElementById('srCpMoreBtn');
  btn.disabled = true;
  btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
  try {
    const res = await apiFetch(`/v1/api/myroom.php?action=safety_chemicals&room_id=${roomId}&category=${encodeURIComponent(srCpCurKey)}&tab=${srCpCurTab}&limit=50`);
    if (!res.success) throw new Error(res.error || 'fail');
    srShowChemPopup(res.data, srCpCurKey, srCpCurColor);
  } catch(e) {
    btn.disabled = false;
    btn.innerHTML = `<i class="fas fa-list"></i> <span id="srCpMoreLbl">${TH?'โหลดเพิ่ม...':'Load more...'}</span>`;
  }
}

function srPrint() {
  if (!srData) return;
  const room = S.rooms.find(r => parseInt(r.room_id) === S.activeRoomId) || {};
  const title = [room.code, room.name].filter(Boolean).join(' — ');
  const date  = new Date().toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'});

  const h = srBuildRows(srData.health_hazard, SR_HEALTH_LABELS);
  const p = srBuildRows(srData.physical_hazard, SR_PHYS_LABELS);

  const content = `<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">
    <title>Safety Report — ${escH(title)}</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
      *{box-sizing:border-box;margin:0;padding:0}body{font-family:'Sarabun',sans-serif;font-size:12px;color:#1e293b;padding:32px 40px}
      h1{font-size:18px;font-weight:800;color:#1e3a5f;margin-bottom:4px}
      .sub{font-size:11px;color:#64748b;margin-bottom:24px}
      .sec{font-size:13px;font-weight:800;color:#1e3a5f;margin:20px 0 8px;padding-bottom:4px;border-bottom:2px solid #e2e8f0}
      table{width:100%;border-collapse:collapse;font-size:11.5px;margin-bottom:6px}
      th{background:#f1f5fb;padding:7px 10px;text-align:center;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.4px;border:1px solid #e2e8f0}
      th:first-child{text-align:left}
      td{padding:7px 10px;border:1px solid #e2e8f0;color:#334155}
      td:not(:first-child){text-align:right;font-variant-numeric:tabular-nums}
      tr.has-data{background:#fafbff}
      td.nz{color:#1e40af;font-weight:700}
      tfoot td{background:#eef2ff;font-weight:800;color:#1e40af}
      .note{font-size:9.5px;color:#94a3b8;margin-top:4px}
      @media print{body{padding:20px}@page{margin:15mm}}
    </style></head><body>
    <h1>รายงานความปลอดภัยสารเคมี — ${escH(title)}</h1>
    <div class="sub">อาคาร: ${escH(room.bld_name||'')} &nbsp;·&nbsp; วันที่: ${date}</div>
    <div class="sec">Health Hazard (ความเป็นอันตรายทางสุขภาพ)</div>
    ${srPrintTable(h.rows, h.totSolid, h.totLiq, h.totGas, h.totTotal)}
    <div class="sec">Physical Hazard (ความเป็นอันตรายทางกายภาพ)</div>
    ${srPrintTable(p.rows, p.totSolid, p.totLiq, p.totGas, p.totTotal)}
    <p class="note">* ปริมาณแสดงในหน่วย kg โดยประมาณ สมมติความหนาแน่น 1 kg/L สำหรับของเหลว | สร้างโดยระบบจัดการสารเคมี</p>
    <script>window.onload=()=>window.print()<\/script>
    </body></html>`;
  const win = window.open('','_blank');
  if (!win) { showToast(TH?'กรุณาอนุญาต Popup':'Allow popups','warn'); return; }
  win.document.write(content); win.document.close();
}

function srPrintTable(rows, totSolid, totLiq, totGas, totTotal) {
  const c = v => v>0 ? `<td class="nz">${srFmt(v)}</td>` : `<td>${srFmt(v)}</td>`;
  const r = rows.map(row => `<tr class="${row.total>0?'has-data':''}"><td>- ${escH(row.label)}</td>${c(row.solid)}${c(row.liquid)}${c(row.gas)}${c(row.total)}</tr>`).join('');
  return `<table><thead><tr><th style="width:44%">ประเภทความอันตราย</th><th>ของแข็ง (kg)</th><th>ของเหลว (kg)</th><th>ก๊าซ (kg)</th><th>รวม (kg)</th></tr></thead>
    <tbody>${r}</tbody>
    <tfoot><tr><td>ปริมาณรวมทั้งหมด (kg)*:</td><td>${srFmt(totSolid)}</td><td>${srFmt(totLiq)}</td><td>${srFmt(totGas)}</td><td>${srFmt(totTotal)}</td></tr></tfoot></table>`;
}
</script>

<?php Layout::endContent(); ?>
