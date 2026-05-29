<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$userId = $user['id'];
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin = $roleLevel >= 4 || in_array($user['role_name'] ?? '', ['admin', 'ceo']);

Layout::head($lang==='th'?'ศูนย์การแจ้งเตือน':'Notification Center');
?>
<style>
/* ===== PREFIX: al- ===== */

/* Hero */
.al-hero{background:linear-gradient(135deg,#1c0a00 0%,#7c2d12 45%,#b45309 100%);border-radius:14px;padding:28px 32px;margin-bottom:22px;color:#fff;position:relative;overflow:hidden;display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.al-hero::after{content:'';position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:rgba(255,255,255,.04);border-radius:50%;pointer-events:none}
.al-hero::before{content:'';position:absolute;bottom:-6px;right:-6px;width:52px;height:52px;border-radius:0 10px 0 52px;opacity:.06;background:currentColor}
.al-hero-left{flex:1;min-width:0}
.al-hero-icon{width:46px;height:46px;border-radius:13px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:21px;margin-bottom:12px}
.al-hero-title{font-size:22px;font-weight:700;margin:0 0 4px;line-height:1.2}
.al-hero-sub{font-size:13px;opacity:.75;margin:0}
.al-hero-meta{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap}
.al-hero-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600}
.al-hero-badge i{font-size:10px;opacity:.8}

/* Stat Cards */
.al-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px}
.al-stat{background:#fff;border-radius:10px;padding:16px 18px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 4px rgba(0,0,0,.06);border:1px solid #eee;transition:transform .15s,box-shadow .15s;position:relative;overflow:hidden}
.al-stat::before{content:'';position:absolute;bottom:-6px;right:-6px;width:52px;height:52px;border-radius:0 8px 0 52px;opacity:.05}
.al-stat:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.1)}
.al-stat:active{transform:translateY(0)}
.al-stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;transition:transform .2s}
.al-stat:hover .al-stat-icon{transform:scale(1.08)}
.al-stat.total::before{background:#1565c0}.al-stat-icon.total{background:#e3f2fd;color:#1565c0}
.al-stat.unread::before{background:#e65100}.al-stat-icon.unread{background:#fff3e0;color:#e65100}
.al-stat.critical::before{background:#c62828}.al-stat-icon.critical{background:#ffebee;color:#c62828}
.al-stat.action::before{background:#7b1fa2}.al-stat-icon.action{background:#f3e5f5;color:#7b1fa2}
.al-stat-val{font-size:22px;font-weight:700;color:var(--c1);line-height:1.1}
.al-stat-lbl{font-size:11px;color:var(--c3);font-weight:500;margin-top:1px}

/* Toolbar */
.al-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:16px;background:#fff;padding:12px 16px;border-radius:10px;border:1px solid #eee;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.al-toolbar-left{display:flex;flex-wrap:wrap;gap:8px;align-items:center;flex:1}
.al-toolbar-right{display:flex;gap:6px;align-items:center}
.al-search{display:flex;align-items:center;gap:6px;background:#f5f5f5;border:1px solid #e0e0e0;border-radius:6px;padding:0 10px;min-width:200px;transition:border-color .15s,background .15s}
.al-search:focus-within{border-color:var(--accent);background:#fff}
.al-search i{color:var(--c3);font-size:12px}
.al-search input{border:none;background:none;font-size:12px;padding:7px 0;outline:none;flex:1;font-family:inherit;min-width:80px}
.al-filter{font-size:12px;padding:7px 10px;border:1px solid #e0e0e0;border-radius:6px;background:#f9f9f9;font-family:inherit;cursor:pointer;color:var(--c1);transition:border-color .15s}
.al-filter:focus{border-color:var(--accent);outline:none}
.al-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;font-size:12px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;transition:all .15s}
.al-btn:active{transform:scale(.97)}
.al-btn-primary{background:var(--accent);color:#fff}.al-btn-primary:hover{background:var(--accent-h)}
.al-btn-outline{background:none;border:1px solid #ddd;color:var(--c2)}.al-btn-outline:hover{background:#f5f5f5;border-color:#bbb}
.al-btn-danger{background:none;border:1px solid #ffcdd2;color:#c62828}.al-btn-danger:hover{background:#ffebee}
.al-btn:disabled{opacity:.4;cursor:default;pointer-events:none}

/* Select Bar */
.al-select-bar{display:none;align-items:center;gap:10px;padding:8px 16px;background:#e8f5ef;border-radius:8px;margin-bottom:10px;font-size:12px;color:var(--accent-d)}
.al-select-bar.show{display:flex}
.al-select-bar strong{font-weight:700}

/* Table */
.al-table-wrap{background:#fff;border-radius:10px;border:1px solid #eee;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.al-table{width:100%;border-collapse:collapse}
.al-table th{padding:10px 14px;font-size:11px;font-weight:600;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;text-align:left;border-bottom:2px solid #eee;background:#fafafa;cursor:pointer;white-space:nowrap;user-select:none;transition:color .12s}
.al-table th:hover{color:var(--accent)}
.al-table th.sorted{color:var(--accent)}
.al-table th .sort-arrow{font-size:9px;margin-left:4px;opacity:.4;transition:opacity .12s}
.al-table th.sorted .sort-arrow{opacity:1}
.al-table td{padding:10px 14px;font-size:12px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
.al-table tr:hover{background:#f8faf9}
.al-table tr.unread{background:#f0f9f5}
.al-table tr.unread td:first-child{border-left:3px solid var(--accent)}

/* Cells */
.al-user-cell{display:flex;align-items:center;gap:8px;min-width:120px}
.al-user-avatar{width:28px;height:28px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;overflow:hidden}
.al-user-avatar img{width:100%;height:100%;object-fit:cover}
.al-user-name{font-size:12px;font-weight:500;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px}
.al-type-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;white-space:nowrap}
.al-type-badge.expiry{background:#fff3e0;color:#e65100}
.al-type-badge.low_stock{background:#fff8e1;color:#f57f17}
.al-type-badge.overdue_borrow{background:#fce4ec;color:#c62828}
.al-type-badge.borrow_request{background:#e8f5e9;color:#2e7d32}
.al-type-badge.safety_violation{background:#ffebee;color:#b71c1c}
.al-type-badge.temperature_alert{background:#e3f2fd;color:#0d47a1}
.al-type-badge.compliance{background:#f3e5f5;color:#6a1b9a}
.al-type-badge.custom{background:#f5f5f5;color:#616161}
.al-sev-dot{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600}
.al-sev-dot::before{content:'';width:8px;height:8px;border-radius:50%;flex-shrink:0}
.al-sev-dot.critical{color:#c62828}.al-sev-dot.critical::before{background:#c62828}
.al-sev-dot.warning{color:#e65100}.al-sev-dot.warning::before{background:#f57c00}
.al-sev-dot.info{color:#1565c0}.al-sev-dot.info::before{background:#42a5f5}
.al-msg-cell{max-width:280px}
.al-msg-title{font-weight:600;color:var(--c1);margin-bottom:1px;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
.al-msg-text{color:var(--c2);font-size:11px;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
.al-status-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:600}
.al-status-badge.read{background:#e8f5e9;color:#2e7d32}
.al-status-badge.unread{background:#fff3e0;color:#e65100}
.al-time{font-size:11px;color:var(--c3);white-space:nowrap}
.al-actions{display:flex;gap:4px}
.al-act-btn{width:28px;height:28px;border:none;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .12s;background:none;color:var(--c3)}
.al-act-btn:hover{background:#f0f0f0;color:var(--c1)}
.al-act-btn.danger:hover{background:#ffebee;color:#c62828}

/* Pagination */
.al-pager{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#fff;border-radius:0 0 10px 10px;border-top:1px solid #eee}
.al-pager-info{font-size:12px;color:var(--c3)}
.al-pager-btns{display:flex;gap:4px}
.al-pager-btn{width:32px;height:32px;border:1px solid #e0e0e0;border-radius:6px;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;color:var(--c2);transition:all .12s;font-family:inherit}
.al-pager-btn:hover:not(:disabled){background:var(--accent-l);border-color:var(--accent);color:var(--accent)}
.al-pager-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.al-pager-btn:disabled{opacity:.3;cursor:default}

/* Empty / Loading */
.al-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;color:var(--c3)}
.al-empty i{font-size:48px;margin-bottom:14px;opacity:.2}
.al-empty p{font-size:14px;margin:0}
.al-loading{display:flex;align-items:center;justify-content:center;padding:60px}
.al-chk{width:16px;height:16px;accent-color:var(--accent);cursor:pointer}

/* Detail Modal */
.al-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px}
.al-modal-overlay.show{display:flex}
.al-modal{background:#fff;border-radius:12px;width:100%;max-width:560px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.al-modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #eee}
.al-modal-header h3{font-size:15px;font-weight:700;color:var(--c1);margin:0;display:flex;align-items:center;gap:8px}
.al-modal-close{width:30px;height:30px;border:none;background:none;cursor:pointer;border-radius:6px;font-size:16px;color:var(--c3);display:flex;align-items:center;justify-content:center;transition:all .12s}
.al-modal-close:hover{background:#f0f0f0;color:var(--c1)}
.al-modal-body{padding:20px}
.al-detail-row{display:flex;gap:12px;margin-bottom:14px}
.al-detail-label{font-size:11px;font-weight:600;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;width:100px;flex-shrink:0;padding-top:2px}
.al-detail-value{font-size:13px;color:var(--c1);flex:1}
.al-modal-footer{padding:12px 20px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:8px}

/* Custom Confirm Modal */
.al-confirm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:2000;align-items:center;justify-content:center;padding:20px}
.al-confirm-overlay.show{display:flex}
.al-confirm-box{background:#fff;border-radius:16px;max-width:400px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.28);overflow:hidden;animation:alCfIn .2s cubic-bezier(.2,0,.2,1.4)}
@keyframes alCfIn{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}
.al-confirm-icon{display:flex;align-items:center;justify-content:center;padding:28px 28px 0}
.al-confirm-ic{width:58px;height:58px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px}
.al-confirm-ic.danger{background:#ffebee;color:#c62828}
.al-confirm-ic.warn{background:#fff3e0;color:#e65100}
.al-confirm-body{padding:16px 28px 24px;text-align:center}
.al-confirm-title{font-size:16px;font-weight:700;color:var(--c1);margin:0 0 8px}
.al-confirm-msg{font-size:13px;color:var(--c2);margin:0;line-height:1.6}
.al-confirm-btns{display:flex;gap:10px;padding:0 20px 20px}
.al-confirm-cancel{flex:1;padding:10px;border:1px solid #e0e0e0;background:#f9f9f9;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;color:var(--c2);transition:all .12s}
.al-confirm-cancel:hover{background:#f0f0f0}
.al-confirm-ok{flex:1;padding:10px;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;color:#fff;transition:all .12s}
.al-confirm-ok.danger{background:#c62828}.al-confirm-ok.danger:hover{background:#b71c1c}
.al-confirm-ok.warn{background:#e65100}.al-confirm-ok.warn:hover{background:#d84315}

/* Toast */
.al-toast-wrap{position:fixed;bottom:24px;right:24px;z-index:3000;display:flex;flex-direction:column;gap:8px;max-width:340px;pointer-events:none}
.al-toast{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,.14);font-size:13px;font-weight:500;pointer-events:all;animation:alToastIn .3s cubic-bezier(.2,0,0,1.4);background:#fff;border-left:4px solid #ccc;color:var(--c1)}
.al-toast.al-ok{border-left-color:var(--ok);background:#f6fffa}
.al-toast.al-err{border-left-color:var(--danger);background:#fff6f6}
.al-toast.al-warn{border-left-color:var(--warn);background:#fffbf0}
.al-toast i.al-ti{font-size:16px;flex-shrink:0}
.al-toast.al-ok i.al-ti{color:var(--ok)}
.al-toast.al-err i.al-ti{color:var(--danger)}
.al-toast.al-warn i.al-ti{color:var(--warn)}
.al-toast span{flex:1;min-width:0}
.al-toast-x{background:none;border:none;color:#bbb;cursor:pointer;font-size:14px;line-height:1;padding:0 0 0 4px;flex-shrink:0}
.al-toast-x:hover{color:var(--c2)}
@keyframes alToastIn{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes alToastOut{to{transform:translateX(110%);opacity:0;max-height:0;padding-top:0;padding-bottom:0;margin:0;border-width:0}}

/* Mobile Cards */
.al-card-list{display:none}
.al-card{background:#fff;border-radius:10px;border:1px solid #eee;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;transition:box-shadow .15s;position:relative}
.al-card:active{box-shadow:0 2px 8px rgba(0,0,0,.12)}
.al-card.unread{border-left:3px solid var(--accent)}
.al-card-main{display:flex;gap:10px;padding:14px 14px 10px;cursor:pointer;align-items:flex-start;-webkit-tap-highlight-color:transparent}
.al-card-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px}
.al-card-icon.expiry{background:#fff3e0;color:#e65100}
.al-card-icon.low_stock{background:#fff8e1;color:#f57f17}
.al-card-icon.overdue_borrow{background:#fce4ec;color:#c62828}
.al-card-icon.borrow_request{background:#e8f5e9;color:#2e7d32}
.al-card-icon.safety_violation{background:#ffebee;color:#b71c1c}
.al-card-icon.temperature_alert{background:#e3f2fd;color:#0d47a1}
.al-card-icon.compliance{background:#f3e5f5;color:#6a1b9a}
.al-card-icon.custom{background:#f5f5f5;color:#616161}
.al-card-body{flex:1;min-width:0}
.al-card-top{display:flex;align-items:center;gap:6px;margin-bottom:3px;flex-wrap:wrap}
.al-card-title{font-size:13px;font-weight:600;color:var(--c1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;min-width:0}
.al-card-sev{font-size:9px;font-weight:700;padding:2px 6px;border-radius:3px;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}
.al-card-sev.critical{background:#ffebee;color:#c62828}
.al-card-sev.warning{background:#fff3e0;color:#e65100}
.al-card-sev.info{background:#e3f2fd;color:#1565c0}
.al-card-msg{font-size:11.5px;color:var(--c2);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.5;margin-bottom:6px}
.al-card-meta{display:flex;align-items:center;gap:8px;font-size:10px;color:var(--c3)}
.al-card-meta i{font-size:9px}
.al-card-dot{width:7px;height:7px;border-radius:50%;background:var(--accent);flex-shrink:0}
.al-card-chevron{color:#ccc;font-size:14px;flex-shrink:0;padding:8px 0}
.al-card-actions{display:flex;border-top:1px solid #f0f0f0;background:#fafafa}
.al-card-act{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:10px 8px;font-size:11px;font-weight:600;color:var(--c2);cursor:pointer;border:none;background:none;font-family:inherit;transition:background .12s;-webkit-tap-highlight-color:transparent;text-decoration:none}
.al-card-act:not(:last-child){border-right:1px solid #eee}
.al-card-act:active{background:#e8f5ef}
.al-card-act i{font-size:12px}
.al-card-act.primary{color:var(--accent)}
.al-card-act.danger{color:#c62828}
.al-card-act.danger:active{background:#ffebee}

/* Responsive */
@media(max-width:1024px){
    .al-stats{grid-template-columns:repeat(2,1fr)}
    .al-toolbar{flex-direction:column;align-items:stretch}
    .al-toolbar-left,.al-toolbar-right{width:100%}
    .al-table th.hide-md,.al-table td.hide-md{display:none}
}
@media(max-width:768px){
    .al-hero{padding:20px}
    .al-hero-title{font-size:18px}
    .al-table-wrap{display:none}
    .al-card-list{display:block}
    .al-stats{grid-template-columns:repeat(2,1fr)}
    .al-search{min-width:100%}
    .al-modal{max-width:95vw;max-height:92vh}
    .al-toolbar-right{flex-wrap:wrap}
    .al-toolbar-right .al-btn{flex:1;justify-content:center;font-size:11px;padding:8px 6px}
    .al-filter{font-size:11px;padding:8px 8px;flex:1;min-width:0}
    .al-pager{flex-direction:column;gap:8px;align-items:center}
    .al-toast-wrap{left:12px;right:12px;bottom:70px;max-width:none}
    .al-confirm-box{max-width:95vw}
}
@media(max-width:480px){
    .al-stats{grid-template-columns:1fr 1fr}
    .al-stat{padding:12px}
    .al-stat-val{font-size:18px}
    .al-card-act{font-size:10px;padding:9px 4px}
}
</style>
<?php Layout::sidebar('alerts'); Layout::beginContent(); ?>

<!-- Hero -->
<div class="al-hero">
    <div class="al-hero-left">
        <div class="al-hero-icon"><i class="fas fa-bell"></i></div>
        <h1 class="al-hero-title"><?php echo $lang==='th'?'ศูนย์การแจ้งเตือน':'Notification Center'; ?></h1>
        <p class="al-hero-sub"><?php echo $lang==='th'?'ตรวจสอบและจัดการการแจ้งเตือนทั้งหมดในระบบ':'Monitor and manage all system notifications'; ?></p>
        <div class="al-hero-meta">
            <span class="al-hero-badge"><i class="fas fa-bell"></i> <?php echo $lang==='th'?'ระบบแจ้งเตือน':'Alert System'; ?></span>
            <?php if ($isAdmin): ?>
            <span class="al-hero-badge"><i class="fas fa-shield-alt"></i> <?php echo $lang==='th'?'มุมมองผู้ดูแล':'Admin View'; ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stat Cards (admin only) -->
<?php if ($isAdmin): ?>
<div class="al-stats" id="alertStats">
    <div class="al-stat total">
        <div class="al-stat-icon total"><i class="fas fa-bell"></i></div>
        <div><div class="al-stat-val" id="statTotal">-</div><div class="al-stat-lbl"><?php echo $lang==='th'?'ทั้งหมด':'Total'; ?></div></div>
    </div>
    <div class="al-stat unread">
        <div class="al-stat-icon unread"><i class="fas fa-envelope"></i></div>
        <div><div class="al-stat-val" id="statUnread">-</div><div class="al-stat-lbl"><?php echo $lang==='th'?'ยังไม่อ่าน':'Unread'; ?></div></div>
    </div>
    <div class="al-stat critical">
        <div class="al-stat-icon critical"><i class="fas fa-exclamation-triangle"></i></div>
        <div><div class="al-stat-val" id="statCritical">-</div><div class="al-stat-lbl"><?php echo $lang==='th'?'วิกฤต':'Critical'; ?></div></div>
    </div>
    <div class="al-stat action">
        <div class="al-stat-icon action"><i class="fas fa-hand-pointer"></i></div>
        <div><div class="al-stat-val" id="statAction">-</div><div class="al-stat-lbl"><?php echo $lang==='th'?'ต้องดำเนินการ':'Action Required'; ?></div></div>
    </div>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="al-toolbar">
    <div class="al-toolbar-left">
        <div class="al-search">
            <i class="fas fa-search"></i>
            <input type="text" id="alSearch" placeholder="<?php echo $lang==='th'?'ค้นหาการแจ้งเตือน...':'Search notifications...'; ?>">
        </div>
        <select class="al-filter" id="alFilterType">
            <option value=""><?php echo $lang==='th'?'ทุกประเภท':'All Types'; ?></option>
            <option value="expiry"><?php echo $lang==='th'?'หมดอายุ':'Expiry'; ?></option>
            <option value="low_stock"><?php echo $lang==='th'?'สต็อกต่ำ':'Low Stock'; ?></option>
            <option value="overdue_borrow"><?php echo $lang==='th'?'ยืมเกินกำหนด':'Overdue'; ?></option>
            <option value="borrow_request"><?php echo $lang==='th'?'คำขอยืม':'Borrow Request'; ?></option>
            <option value="safety_violation"><?php echo $lang==='th'?'ความปลอดภัย':'Safety'; ?></option>
            <option value="temperature_alert"><?php echo $lang==='th'?'อุณหภูมิ':'Temperature'; ?></option>
            <option value="compliance"><?php echo $lang==='th'?'การปฏิบัติตาม':'Compliance'; ?></option>
            <option value="custom"><?php echo $lang==='th'?'ทั่วไป':'Custom'; ?></option>
        </select>
        <select class="al-filter" id="alFilterSev">
            <option value=""><?php echo $lang==='th'?'ทุกระดับ':'All Severity'; ?></option>
            <option value="critical"><?php echo $lang==='th'?'วิกฤต':'Critical'; ?></option>
            <option value="warning"><?php echo $lang==='th'?'เตือน':'Warning'; ?></option>
            <option value="info"><?php echo $lang==='th'?'ข้อมูล':'Info'; ?></option>
        </select>
        <select class="al-filter" id="alFilterRead">
            <option value=""><?php echo $lang==='th'?'ทุกสถานะ':'All Status'; ?></option>
            <option value="unread"><?php echo $lang==='th'?'ยังไม่อ่าน':'Unread'; ?></option>
            <option value="read"><?php echo $lang==='th'?'อ่านแล้ว':'Read'; ?></option>
        </select>
    </div>
    <div class="al-toolbar-right">
        <?php if ($isAdmin): ?>
        <button class="al-btn al-btn-outline" onclick="markSelectedRead()" id="btnBulkRead" disabled>
            <i class="fas fa-check-double"></i> <span><?php echo $lang==='th'?'อ่านแล้ว':'Mark Read'; ?></span>
        </button>
        <button class="al-btn al-btn-outline" onclick="dismissSelected()" id="btnBulkDismiss" disabled>
            <i class="fas fa-eye-slash"></i> <span><?php echo $lang==='th'?'ซ่อน':'Dismiss'; ?></span>
        </button>
        <button class="al-btn al-btn-danger" onclick="deleteSelected()" id="btnBulkDelete" disabled>
            <i class="fas fa-trash"></i> <span><?php echo $lang==='th'?'ลบ':'Delete'; ?></span>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Select Bar -->
<div class="al-select-bar" id="selectBar">
    <input type="checkbox" class="al-chk" id="chkSelectAll" onchange="toggleSelectAll(this.checked)">
    <span id="selectInfo"></span>
</div>

<!-- Table -->
<div class="al-table-wrap">
    <table class="al-table" id="alertsTable">
        <thead>
            <tr>
                <?php if ($isAdmin): ?><th style="width:36px"><input type="checkbox" class="al-chk" id="chkHead" onchange="toggleSelectAll(this.checked)"></th><?php endif; ?>
                <th data-sort="severity" class="hide-sm" style="width:90px"><?php echo $lang==='th'?'ระดับ':'Severity'; ?> <i class="fas fa-sort sort-arrow"></i></th>
                <th data-sort="alert_type" style="width:120px"><?php echo $lang==='th'?'ประเภท':'Type'; ?> <i class="fas fa-sort sort-arrow"></i></th>
                <th><?php echo $lang==='th'?'ข้อความ':'Message'; ?></th>
                <?php if ($isAdmin): ?><th class="hide-md" style="width:140px"><?php echo $lang==='th'?'ผู้ใช้':'User'; ?></th><?php endif; ?>
                <th data-sort="is_read" class="hide-sm" style="width:80px"><?php echo $lang==='th'?'สถานะ':'Status'; ?></th>
                <th data-sort="created_at" style="width:100px"><?php echo $lang==='th'?'เวลา':'Time'; ?> <i class="fas fa-sort-down sort-arrow"></i></th>
                <th style="width:80px"></th>
            </tr>
        </thead>
        <tbody id="alertsBody">
            <tr><td colspan="8" class="al-loading"><div class="ci-spinner"></div></td></tr>
        </tbody>
    </table>
</div>

<!-- Mobile Cards -->
<div class="al-card-list" id="alertsCards"></div>

<!-- Pagination -->
<div class="al-pager" id="alertsPager" style="display:none">
    <div class="al-pager-info" id="pagerInfo"></div>
    <div class="al-pager-btns" id="pagerBtns"></div>
</div>

<!-- Detail Modal -->
<div class="al-modal-overlay" id="detailModal">
    <div class="al-modal">
        <div class="al-modal-header">
            <h3><i class="fas fa-bell"></i> <span><?php echo $lang==='th'?'รายละเอียดการแจ้งเตือน':'Alert Detail'; ?></span></h3>
            <button class="al-modal-close" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="al-modal-body" id="modalBody"></div>
        <div class="al-modal-footer" id="modalFooter"></div>
    </div>
</div>

<!-- Confirm Modal -->
<div class="al-confirm-overlay" id="alConfirmOverlay">
    <div class="al-confirm-box">
        <div class="al-confirm-icon"><div class="al-confirm-ic" id="alConfirmIc"><i class="fas fa-exclamation-triangle"></i></div></div>
        <div class="al-confirm-body">
            <div class="al-confirm-title" id="alConfirmTitle"></div>
            <div class="al-confirm-msg" id="alConfirmMsg"></div>
        </div>
        <div class="al-confirm-btns">
            <button class="al-confirm-cancel" id="alConfirmCancel"><?php echo $lang==='th'?'ยกเลิก':'Cancel'; ?></button>
            <button class="al-confirm-ok danger" id="alConfirmOk"><?php echo $lang==='th'?'ยืนยัน':'Confirm'; ?></button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="al-toast-wrap" id="alToastWrap"></div>

<script>
(()=>{
const lang='<?php echo $lang; ?>';
const isAdmin=<?php echo $isAdmin?'true':'false'; ?>;
const scope=isAdmin?'all':'own';
let currentPage=1, currentSort='created_at', currentOrder='desc';
let allAlerts=[], selectedIds=new Set();
let debounceTimer=null;

/* ===== apiFetch (local — before Layout::endContent defines global) ===== */
async function apiFetch(url,options={}){
    const t=document.cookie.split('; ').find(c=>c.startsWith('auth_token='))?.split('=')[1];
    const h={'Content-Type':'application/json',...(options.headers||{})};
    if(t) h['Authorization']='Bearer '+t;
    const r=await fetch(url,{...options,headers:h});
    if(!r.ok&&r.status===401){window.location.href='/v1/';throw new Error('Unauthorized');}
    return r.json();
}

/* ===== Toast ===== */
function alToast(msg,type='ok',dur=3500){
    const wrap=document.getElementById('alToastWrap');
    const ic={ok:'fa-check-circle',err:'fa-times-circle',warn:'fa-exclamation-circle'}[type]||'fa-info-circle';
    const t=document.createElement('div');
    t.className=`al-toast al-${type==='err'?'err':type==='warn'?'warn':'ok'}`;
    t.innerHTML=`<i class="fas ${ic} al-ti"></i><span>${msg}</span><button class="al-toast-x" onclick="this.parentNode.remove()"><i class="fas fa-times"></i></button>`;
    wrap.appendChild(t);
    setTimeout(()=>{
        if(t.parentNode){t.style.animation='alToastOut .3s ease forwards';setTimeout(()=>t.remove(),300);}
    },dur);
}

/* ===== Confirm Modal ===== */
let _cfResolve=null;
function alModal(title,msg,type='danger'){
    return new Promise(resolve=>{
        _cfResolve=resolve;
        const overlay=document.getElementById('alConfirmOverlay');
        const ic=document.getElementById('alConfirmIc');
        const okBtn=document.getElementById('alConfirmOk');
        ic.className=`al-confirm-ic ${type}`;
        ic.innerHTML=type==='danger'?'<i class="fas fa-trash-alt"></i>':'<i class="fas fa-exclamation-triangle"></i>';
        document.getElementById('alConfirmTitle').textContent=title;
        document.getElementById('alConfirmMsg').textContent=msg;
        okBtn.className=`al-confirm-ok ${type}`;
        okBtn.textContent=lang==='th'?'ยืนยัน':'Confirm';
        document.getElementById('alConfirmCancel').textContent=lang==='th'?'ยกเลิก':'Cancel';
        okBtn.onclick=()=>{overlay.classList.remove('show');resolve(true);};
        document.getElementById('alConfirmCancel').onclick=()=>{overlay.classList.remove('show');resolve(false);};
        overlay.classList.add('show');
    });
}
document.getElementById('alConfirmOverlay').addEventListener('click',e=>{
    if(e.target.id==='alConfirmOverlay'&&_cfResolve){document.getElementById('alConfirmOverlay').classList.remove('show');_cfResolve(false);_cfResolve=null;}
});

/* ===== Data Maps ===== */
const typeIcons={
    expiry:'fa-clock',low_stock:'fa-battery-quarter',overdue_borrow:'fa-hourglass-end',
    borrow_request:'fa-handshake',safety_violation:'fa-shield-alt',compliance:'fa-clipboard-check',
    temperature_alert:'fa-thermometer-three-quarters',custom:'fa-bell'
};
const typeLabels={
    expiry:lang==='th'?'หมดอายุ':'Expiry',
    low_stock:lang==='th'?'สต็อกต่ำ':'Low Stock',
    overdue_borrow:lang==='th'?'เกินกำหนด':'Overdue',
    borrow_request:lang==='th'?'คำขอยืม':'Borrow Req',
    safety_violation:lang==='th'?'ความปลอดภัย':'Safety',
    temperature_alert:lang==='th'?'อุณหภูมิ':'Temperature',
    compliance:lang==='th'?'ปฏิบัติตาม':'Compliance',
    custom:lang==='th'?'ทั่วไป':'Custom'
};
const sevLabels={critical:lang==='th'?'วิกฤต':'Critical',warning:lang==='th'?'เตือน':'Warning',info:lang==='th'?'ข้อมูล':'Info'};

function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML;}

function timeAgo(dt){
    const diff=Math.floor((Date.now()-new Date(dt).getTime())/1000);
    if(diff<60) return lang==='th'?'เมื่อสักครู่':'Just now';
    if(diff<3600) return Math.floor(diff/60)+(lang==='th'?' นาที':'m ago');
    if(diff<86400) return Math.floor(diff/3600)+(lang==='th'?' ชม.':'h ago');
    if(diff<604800) return Math.floor(diff/86400)+(lang==='th'?' วัน':'d ago');
    return new Date(dt).toLocaleDateString(lang==='th'?'th-TH':'en-US',{month:'short',day:'numeric',year:'numeric'});
}

function fullDate(dt){
    if(!dt) return '-';
    return new Date(dt).toLocaleDateString(lang==='th'?'th-TH':'en-US',{year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'});
}

function getAlertLink(n){
    const cid=n.container_id,chid=n.chemical_id,bid=n.borrow_request_id,lid=n.lab_id;
    switch(n.alert_type){
        case 'borrow_request':case 'overdue_borrow':return '/v1/pages/borrow.php'+(bid?'?highlight_request='+bid:'');
        case 'expiry':case 'safety_violation':return cid?'/v1/pages/containers.php?highlight='+cid:(chid?'/v1/pages/stock.php?chemical_id='+chid:'/v1/pages/containers.php');
        case 'low_stock':return chid?'/v1/pages/stock.php?chemical_id='+chid:'/v1/pages/stock.php';
        case 'temperature_alert':return lid?'/v1/pages/locations.php?lab_id='+lid:'/v1/pages/locations.php';
        case 'compliance':return '/v1/pages/reports.php';
        default:return '#';
    }
}

function userName(n){
    if(n.user_full_name_th) return n.user_full_name_th;
    if(n.user_first_name) return n.user_first_name+' '+(n.user_last_name||'');
    return n.user_username||'User #'+n.user_id;
}
function userAvatar(n){
    const letter=(n.user_full_name_th||n.user_first_name||'U').charAt(0);
    if(n.user_avatar) return `<div class="al-user-avatar"><img src="${esc(n.user_avatar)}" alt=""></div>`;
    return `<div class="al-user-avatar">${esc(letter)}</div>`;
}

/* ===== Load Stats ===== */
async function loadStats(){
    if(!isAdmin) return;
    try{
        const d=await apiFetch('/v1/api/alerts.php?stats=1');
        if(d.success){
            const s=d.data;
            document.getElementById('statTotal').textContent=s.total.toLocaleString();
            document.getElementById('statUnread').textContent=s.unread.toLocaleString();
            document.getElementById('statCritical').textContent=s.critical.toLocaleString();
            document.getElementById('statAction').textContent=s.action_required.toLocaleString();
        }
    }catch(e){console.error('Stats',e);}
}

/* ===== Load Alerts ===== */
async function loadAlerts(page=1){
    currentPage=page;
    const body=document.getElementById('alertsBody');
    body.innerHTML='<tr><td colspan="8" style="text-align:center;padding:40px"><div class="ci-spinner"></div></td></tr>';
    const params=new URLSearchParams({scope,page,per_page:20,sort:currentSort,order:currentOrder,dismissed:'false'});
    const search=document.getElementById('alSearch').value.trim();
    const type=document.getElementById('alFilterType').value;
    const sev=document.getElementById('alFilterSev').value;
    const readSt=document.getElementById('alFilterRead').value;
    if(search) params.set('search',search);
    if(type) params.set('type',type);
    if(sev) params.set('severity',sev);
    if(readSt) params.set('read_status',readSt);
    try{
        const d=await apiFetch('/v1/api/alerts.php?'+params);
        if(!d.success) throw new Error(d.error||'Load failed');
        allAlerts=d.data?.data||[];
        renderTable(allAlerts);
        renderPager(d.data?.pagination);
        selectedIds.clear();
        updateBulkButtons();
    }catch(e){
        body.innerHTML=`<tr><td colspan="8"><div class="al-empty"><i class="fas fa-exclamation-circle"></i><p>${esc(e.message)}</p></div></td></tr>`;
    }
}

function renderTable(items){
    const body=document.getElementById('alertsBody');
    if(!items.length){
        body.innerHTML=`<tr><td colspan="8"><div class="al-empty"><i class="fas fa-bell-slash"></i><p>${lang==='th'?'ไม่พบการแจ้งเตือน':'No notifications found'}</p></div></td></tr>`;
        renderCards([]);return;
    }
    body.innerHTML=items.map(n=>{
        const isUnread=!n.is_read||n.is_read==='0'||n.is_read===0;
        const link=getAlertLink(n);
        const ic=typeIcons[n.alert_type]||'fa-bell';
        return `<tr class="${isUnread?'unread':''}">
            ${isAdmin?`<td><input type="checkbox" class="al-chk row-chk" value="${n.id}" onchange="onRowCheck()" ${selectedIds.has(n.id)?'checked':''}></td>`:''}
            <td class="hide-sm"><span class="al-sev-dot ${n.severity}">${sevLabels[n.severity]||n.severity}</span></td>
            <td><span class="al-type-badge ${n.alert_type}"><i class="fas ${ic}" style="font-size:10px"></i> ${typeLabels[n.alert_type]||n.alert_type}</span></td>
            <td class="al-msg-cell"><div class="al-msg-title">${esc(n.title||n.alert_type)}</div><div class="al-msg-text">${esc(n.message||'')}</div></td>
            ${isAdmin?`<td class="hide-md"><div class="al-user-cell">${userAvatar(n)}<span class="al-user-name" title="${esc(userName(n))}">${esc(userName(n))}</span></div></td>`:''}
            <td class="hide-sm"><span class="al-status-badge ${isUnread?'unread':'read'}">${isUnread?(lang==='th'?'ยังไม่อ่าน':'Unread'):(lang==='th'?'อ่านแล้ว':'Read')}</span></td>
            <td><span class="al-time" title="${fullDate(n.created_at)}">${timeAgo(n.created_at)}</span></td>
            <td><div class="al-actions">
                <button class="al-act-btn" title="${lang==='th'?'ดูรายละเอียด':'View'}" onclick="showDetail(${n.id})"><i class="fas fa-eye"></i></button>
                ${link!=='#'?`<a class="al-act-btn" title="${lang==='th'?'ไปยังหน้าที่เกี่ยวข้อง':'Go to page'}" href="${link}"><i class="fas fa-external-link-alt"></i></a>`:''}
                ${isAdmin?`<button class="al-act-btn danger" title="${lang==='th'?'ลบ':'Delete'}" onclick="deleteSingle(${n.id})"><i class="fas fa-trash-alt"></i></button>`:''}
            </div></td>
        </tr>`;
    }).join('');
    renderCards(items);
}

function renderCards(items){
    const c=document.getElementById('alertsCards');
    if(!c) return;
    if(!items.length){
        c.innerHTML=`<div class="al-empty" style="background:#fff;border-radius:10px;border:1px solid #eee"><i class="fas fa-bell-slash"></i><p>${lang==='th'?'ไม่พบการแจ้งเตือน':'No notifications found'}</p></div>`;
        return;
    }
    c.innerHTML=items.map(n=>{
        const isUnread=!n.is_read||n.is_read==='0'||n.is_read===0;
        const link=getAlertLink(n);
        const ic=typeIcons[n.alert_type]||'fa-bell';
        return `<div class="al-card ${isUnread?'unread':''}">
            <div class="al-card-main" onclick="showDetail(${n.id})">
                <div class="al-card-icon ${n.alert_type}"><i class="fas ${ic}"></i></div>
                <div class="al-card-body">
                    <div class="al-card-top">
                        <span class="al-card-title">${esc(n.title||typeLabels[n.alert_type]||n.alert_type)}</span>
                        <span class="al-card-sev ${n.severity}">${sevLabels[n.severity]||n.severity}</span>
                    </div>
                    <div class="al-card-msg">${esc(n.message||'')}</div>
                    <div class="al-card-meta">
                        ${isUnread?'<span class="al-card-dot"></span>':''}
                        <span><i class="fas fa-clock"></i> ${timeAgo(n.created_at)}</span>
                        <span><span class="al-type-badge ${n.alert_type}" style="padding:1px 5px;font-size:9px"><i class="fas ${ic}" style="font-size:8px"></i> ${typeLabels[n.alert_type]||n.alert_type}</span></span>
                        ${isAdmin&&n.user_first_name?`<span><i class="fas fa-user"></i> ${esc((n.user_full_name_th||n.user_first_name||'').split(' ')[0])}</span>`:''}
                    </div>
                </div>
                <div class="al-card-chevron"><i class="fas fa-chevron-right"></i></div>
            </div>
            <div class="al-card-actions">
                <button class="al-card-act primary" onclick="showDetail(${n.id})"><i class="fas fa-eye"></i> ${lang==='th'?'รายละเอียด':'Detail'}</button>
                ${isUnread?`<button class="al-card-act" onclick="quickMarkRead(${n.id},this)"><i class="fas fa-check"></i> ${lang==='th'?'อ่านแล้ว':'Mark Read'}</button>`:''}
                ${link!=='#'?`<a class="al-card-act" href="${link}"><i class="fas fa-external-link-alt"></i> ${lang==='th'?'ดูเพิ่ม':'View'}</a>`:''}
                ${isAdmin?`<button class="al-card-act danger" onclick="deleteSingle(${n.id})"><i class="fas fa-trash-alt"></i> ${lang==='th'?'ลบ':'Delete'}</button>`:''}
            </div>
        </div>`;
    }).join('');
}

function renderPager(pg){
    const pager=document.getElementById('alertsPager');
    if(!pg||pg.total<=0){pager.style.display='none';return;}
    pager.style.display='flex';
    const totalPages=pg.total_pages||Math.ceil(pg.total/pg.per_page);
    const from=(pg.page-1)*pg.per_page+1, to=Math.min(pg.page*pg.per_page,pg.total);
    document.getElementById('pagerInfo').textContent=`${from}-${to} / ${pg.total} ${lang==='th'?'รายการ':'items'}`;
    let btns=`<button class="al-pager-btn" ${pg.page<=1?'disabled':''} onclick="loadAlerts(${pg.page-1})"><i class="fas fa-chevron-left"></i></button>`;
    const maxShow=5;
    let start=Math.max(1,pg.page-Math.floor(maxShow/2));
    let end=Math.min(totalPages,start+maxShow-1);
    if(end-start<maxShow-1) start=Math.max(1,end-maxShow+1);
    for(let i=start;i<=end;i++) btns+=`<button class="al-pager-btn ${i===pg.page?'active':''}" onclick="loadAlerts(${i})">${i}</button>`;
    btns+=`<button class="al-pager-btn" ${pg.page>=totalPages?'disabled':''} onclick="loadAlerts(${pg.page+1})"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagerBtns').innerHTML=btns;
}

/* Sort */
document.querySelectorAll('.al-table th[data-sort]').forEach(th=>{
    th.addEventListener('click',()=>{
        const field=th.dataset.sort;
        if(currentSort===field){currentOrder=currentOrder==='asc'?'desc':'asc';}else{currentSort=field;currentOrder='desc';}
        document.querySelectorAll('.al-table th').forEach(h=>h.classList.remove('sorted'));
        th.classList.add('sorted');
        const arrow=th.querySelector('.sort-arrow');
        if(arrow) arrow.className='fas sort-arrow '+(currentOrder==='asc'?'fa-sort-up':'fa-sort-down');
        loadAlerts(1);
    });
});

/* Filters */
['alFilterType','alFilterSev','alFilterRead'].forEach(id=>{
    document.getElementById(id)?.addEventListener('change',()=>loadAlerts(1));
});
document.getElementById('alSearch')?.addEventListener('input',()=>{
    clearTimeout(debounceTimer);
    debounceTimer=setTimeout(()=>loadAlerts(1),400);
});

/* Checkbox */
window.onRowCheck=function(){
    selectedIds.clear();
    document.querySelectorAll('.row-chk:checked').forEach(c=>selectedIds.add(parseInt(c.value)));
    updateBulkButtons();
};
window.toggleSelectAll=function(checked){
    document.querySelectorAll('.row-chk').forEach(c=>{c.checked=checked;});
    selectedIds.clear();
    if(checked) allAlerts.forEach(n=>selectedIds.add(n.id));
    const hc=document.getElementById('chkHead');
    if(hc) hc.checked=checked;
    updateBulkButtons();
};
function updateBulkButtons(){
    const count=selectedIds.size;
    const bar=document.getElementById('selectBar');
    if(count>0){
        bar?.classList.add('show');
        document.getElementById('selectInfo').innerHTML=`<strong>${count}</strong> ${lang==='th'?'รายการที่เลือก':'selected'}`;
    }else{
        bar?.classList.remove('show');
    }
    ['btnBulkRead','btnBulkDismiss','btnBulkDelete'].forEach(id=>{
        const btn=document.getElementById(id);
        if(btn) btn.disabled=count===0;
    });
}

/* ===== Bulk Actions ===== */
window.markSelectedRead=async function(){
    if(!selectedIds.size) return;
    try{
        await apiFetch('/v1/api/alerts.php',{method:'POST',body:JSON.stringify({action:'mark_read',alert_ids:Array.from(selectedIds)})});
        alToast(lang==='th'?'อ่านแล้วทั้งหมด':'Marked as read','ok');
        loadAlerts(currentPage);loadStats();
    }catch(e){alToast(e.message,'err');}
};
window.dismissSelected=async function(){
    if(!selectedIds.size) return;
    const ok=await alModal(
        lang==='th'?'ซ่อนการแจ้งเตือน':'Dismiss Notifications',
        lang==='th'?`ซ่อน ${selectedIds.size} รายการที่เลือก?`:`Dismiss ${selectedIds.size} selected notifications?`,
        'warn'
    );
    if(!ok) return;
    try{
        await apiFetch('/v1/api/alerts.php',{method:'POST',body:JSON.stringify({action:'dismiss',alert_ids:Array.from(selectedIds)})});
        alToast(lang==='th'?'ซ่อนแล้ว':'Dismissed','ok');
        loadAlerts(currentPage);loadStats();
    }catch(e){alToast(e.message,'err');}
};
window.deleteSelected=async function(){
    if(!selectedIds.size) return;
    const ok=await alModal(
        lang==='th'?'ลบการแจ้งเตือน':'Delete Notifications',
        lang==='th'?`ลบ ${selectedIds.size} รายการ? ไม่สามารถกู้คืนได้`:`Delete ${selectedIds.size} items? This cannot be undone.`,
        'danger'
    );
    if(!ok) return;
    try{
        await apiFetch('/v1/api/alerts.php',{method:'POST',body:JSON.stringify({action:'bulk_delete',alert_ids:Array.from(selectedIds)})});
        alToast(lang==='th'?'ลบแล้ว':'Deleted','ok');
        loadAlerts(currentPage);loadStats();
    }catch(e){alToast(e.message,'err');}
};
window.deleteSingle=async function(id){
    const ok=await alModal(
        lang==='th'?'ลบการแจ้งเตือน':'Delete Notification',
        lang==='th'?'ลบการแจ้งเตือนนี้? ไม่สามารถกู้คืนได้':'Delete this notification? This cannot be undone.',
        'danger'
    );
    if(!ok) return;
    try{
        await apiFetch('/v1/api/alerts.php?id='+id,{method:'DELETE'});
        alToast(lang==='th'?'ลบแล้ว':'Deleted','ok');
        loadAlerts(currentPage);loadStats();
    }catch(e){alToast(e.message,'err');}
};

/* ===== Detail Modal ===== */
window.showDetail=function(id){
    const n=allAlerts.find(a=>a.id==id);
    if(!n) return;
    const isUnread=!n.is_read||n.is_read==='0'||n.is_read===0;
    const link=getAlertLink(n);
    document.getElementById('modalBody').innerHTML=`
        <div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'ประเภท':'Type'}</div>
            <div class="al-detail-value"><span class="al-type-badge ${n.alert_type}"><i class="fas ${typeIcons[n.alert_type]||'fa-bell'}" style="font-size:10px"></i> ${typeLabels[n.alert_type]||n.alert_type}</span></div></div>
        <div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'ระดับ':'Severity'}</div>
            <div class="al-detail-value"><span class="al-sev-dot ${n.severity}">${sevLabels[n.severity]||n.severity}</span></div></div>
        <div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'หัวข้อ':'Title'}</div>
            <div class="al-detail-value" style="font-weight:600">${esc(n.title||'')}</div></div>
        <div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'ข้อความ':'Message'}</div>
            <div class="al-detail-value">${esc(n.message||'-')}</div></div>
        ${isAdmin?`<div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'ผู้ใช้':'User'}</div>
            <div class="al-detail-value"><div class="al-user-cell">${userAvatar(n)}<span>${esc(userName(n))}</span></div></div></div>`:''}
        ${n.chemical_name?`<div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'สารเคมี':'Chemical'}</div>
            <div class="al-detail-value">${esc(n.chemical_name)}</div></div>`:''}
        <div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'สถานะ':'Status'}</div>
            <div class="al-detail-value"><span class="al-status-badge ${isUnread?'unread':'read'}">${isUnread?(lang==='th'?'ยังไม่อ่าน':'Unread'):(lang==='th'?'อ่านแล้ว':'Read')}</span></div></div>
        <div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'สร้างเมื่อ':'Created'}</div>
            <div class="al-detail-value">${fullDate(n.created_at)}</div></div>
        ${n.read_at?`<div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'อ่านเมื่อ':'Read at'}</div>
            <div class="al-detail-value">${fullDate(n.read_at)}</div></div>`:''}
        ${n.action_required?`<div class="al-detail-row"><div class="al-detail-label">${lang==='th'?'ดำเนินการ':'Action'}</div>
            <div class="al-detail-value">${n.action_taken?'<span class="al-status-badge read">'+esc(n.action_taken)+'</span>':'<span class="al-status-badge unread">'+(lang==='th'?'รอดำเนินการ':'Pending')+'</span>'}</div></div>`:''}
    `;
    let footer='';
    if(link!=='#') footer+=`<a href="${link}" class="al-btn al-btn-primary"><i class="fas fa-external-link-alt"></i> ${lang==='th'?'ไปยังหน้าที่เกี่ยวข้อง':'Go to Page'}</a>`;
    if(isUnread) footer+=`<button class="al-btn al-btn-outline" onclick="markReadFromModal(${n.id})"><i class="fas fa-check"></i> ${lang==='th'?'อ่านแล้ว':'Mark Read'}</button>`;
    footer+=`<button class="al-btn al-btn-outline" onclick="closeDetailModal()"><i class="fas fa-times"></i> ${lang==='th'?'ปิด':'Close'}</button>`;
    document.getElementById('modalFooter').innerHTML=footer;
    document.getElementById('detailModal').classList.add('show');
    if(isUnread){
        apiFetch('/v1/api/alerts.php',{method:'POST',body:JSON.stringify({action:'mark_read',alert_ids:[n.id]})})
            .then(()=>{const item=allAlerts.find(a=>a.id==n.id);if(item)item.is_read=1;loadStats();})
            .catch(()=>{});
    }
};
window.markReadFromModal=async function(id){
    try{
        await apiFetch('/v1/api/alerts.php',{method:'POST',body:JSON.stringify({action:'mark_read',alert_ids:[id]})});
        closeDetailModal();
        alToast(lang==='th'?'อ่านแล้ว':'Marked as read','ok');
        loadAlerts(currentPage);loadStats();
    }catch(e){alToast(e.message,'err');}
};
window.closeDetailModal=function(){document.getElementById('detailModal').classList.remove('show');};
document.getElementById('detailModal').addEventListener('click',e=>{if(e.target.id==='detailModal') closeDetailModal();});
document.addEventListener('keydown',e=>{
    if(e.key==='Escape'){
        closeDetailModal();
        if(document.getElementById('alConfirmOverlay').classList.contains('show')){
            document.getElementById('alConfirmOverlay').classList.remove('show');
            if(_cfResolve){_cfResolve(false);_cfResolve=null;}
        }
    }
});

/* Quick mark read (mobile card) */
window.quickMarkRead=async function(id,btn){
    try{
        if(btn){btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';btn.disabled=true;}
        await apiFetch('/v1/api/alerts.php',{method:'POST',body:JSON.stringify({action:'mark_read',alert_ids:[id]})});
        alToast(lang==='th'?'อ่านแล้ว':'Marked as read','ok');
        loadAlerts(currentPage);loadStats();
    }catch(e){alToast(e.message,'err');}
};

window.loadAlerts=loadAlerts;
window.loadStats=loadStats;
loadAlerts(1);
loadStats();
})();
</script>

<?php Layout::endContent(); Layout::footer(); ?>
