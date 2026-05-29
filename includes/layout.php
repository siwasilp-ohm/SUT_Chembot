<?php
/**
 * Shared Layout Components - SUT chemBot
 * Professional dark sidebar with teal/green accent theme
 * Inspired by https://app.cheminventory.net/
 */

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

class Layout {
    
    public static function head(string $title = '', array $extraCss = [], array $extraJs = []): void {
        $lang = I18n::getCurrentLang();
        $appName = __('app_name');
        $pageTitle = $title ? "{$title} - {$appName}" : $appName;
        ?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    :root {
        --sb-bg:#2d2d2d; --sb-hover:#3a3a3a; --sb-active-bg:#1a8a5c; --sb-text:#ccc; --sb-w:220px; --hdr-h:59px;
        --accent:#1a8a5c; --accent-h:#15704b; --accent-l:#e8f5ef; --accent-d:#0d5c3a;
        --bg:#f5f5f5; --card:#fff; --border:#e0e0e0; --c1:#333; --c2:#666; --c3:#999;
        --input-bg:#fff; --input-bd:#ccc; --danger:#d9534f; --warn:#f0ad4e; --info:#5bc0de; --ok:#5cb85c;
    }
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter','Noto Sans Thai',-apple-system,sans-serif}
    html{overflow-x:hidden}
    body{background:var(--bg);color:var(--c1);font-size:14px;line-height:1.5;overflow-x:hidden;-webkit-text-size-adjust:100%}
    ::-webkit-scrollbar{width:7px;height:7px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:#ccc;border-radius:4px}::-webkit-scrollbar-thumb:hover{background:#aaa}
    a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}

    /* ======== SIDEBAR ======== */
    .sb{position:fixed;top:0;left:0;width:var(--sb-w);height:100vh;background:var(--sb-bg);color:var(--sb-text);z-index:100;display:flex;flex-direction:column;transition:width .25s ease,transform .3s;overflow-y:auto;overflow-x:hidden}
    .sb-logo{display:flex;align-items:center;gap:10px;padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.08);min-height:59px}
    .sb-logo-icon{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#f97316,#fb923c);display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;flex-shrink:0;box-shadow:0 2px 8px rgba(249,115,22,.35);cursor:pointer;transition:transform .2s,box-shadow .2s;user-select:none}
    .sb-logo-icon:hover{transform:scale(1.08);box-shadow:0 4px 14px rgba(249,115,22,.5)}
    .sb-logo-text{font-weight:700;font-size:14px;color:#fff;line-height:1.2;transition:opacity .2s,width .2s;white-space:nowrap;overflow:hidden}
    .sb-logo-sub{font-size:9px;color:#9e9e9e;font-weight:400;white-space:nowrap}
    .sb-nav{flex:1;padding:6px 0}
    .sb-item{display:flex;align-items:center;gap:9px;padding:9px 14px;color:var(--sb-text);text-decoration:none;font-size:13px;font-weight:500;cursor:pointer;transition:all .12s;border-left:3px solid transparent;white-space:nowrap;overflow:hidden}
    .sb-item:hover{background:var(--sb-hover);color:#fff;text-decoration:none}
    .sb-item.active{background:var(--sb-active-bg);color:#fff;border-left-color:#4acea0}
    .sb-item i.icon{width:18px;text-align:center;font-size:13px;flex-shrink:0}
    .sb-item .arrow{margin-left:auto;font-size:9px;transition:transform .2s;flex-shrink:0}
    .sb-item.expanded .arrow{transform:rotate(180deg)}
    .sb-divider{height:1px;background:rgba(255,255,255,.06);margin:4px 0}
    .sb-sub{display:none;background:rgba(0,0,0,.12)}.sb-sub.show{display:block}
    .sb-sub .sb-item{padding-left:42px;font-size:12px;font-weight:400}
    .sb-user{padding:10px 14px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:9px;flex-shrink:0}
    .sb-avatar{width:30px;height:30px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;flex-shrink:0}
    .sb-uname{font-size:11px;font-weight:600;color:#ddd;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .sb-urole{font-size:9px;color:#888}
    .sb-footer{padding:6px 14px;border-top:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:3px;flex-shrink:0}
    .sb-footer a,.sb-footer button{font-size:10px;color:#888;text-decoration:none;padding:3px 7px;border-radius:3px;border:none;background:none;cursor:pointer;transition:all .12s}
    .sb-footer a:hover,.sb-footer button:hover{color:#fff;background:rgba(255,255,255,.08)}
    .sb-footer .active{color:#fff;background:var(--accent)}

    /* ======== SIDEBAR COLLAPSED ======== */
    .sb.collapsed{width:54px}
    .sb.collapsed .sb-logo-text-wrap{opacity:0;width:0;overflow:hidden;pointer-events:none}
    .sb.collapsed .sb-item-label{opacity:0;width:0;overflow:hidden;display:inline-block;transition:opacity .2s,width .2s}
    .sb.collapsed .sb-item{justify-content:center;padding:9px 0;gap:0;border-left-color:transparent!important}
    .sb.collapsed .sb-item.active{border-left-color:#4acea0!important}
    .sb.collapsed .sb-item i.icon{width:auto;margin:0}
    .sb.collapsed .sb-item .arrow{display:none}
    .sb.collapsed .sb-sub{display:none!important}
    .sb.collapsed .sb-divider{margin:4px 8px}
    .sb.collapsed .sb-footer{justify-content:center;padding:6px 0}
    .sb.collapsed .sb-footer a,.sb.collapsed .sb-footer button{padding:3px 5px}
    .sb-item-label{transition:opacity .2s,width .2s;white-space:nowrap}
    /* tooltip on collapsed */
    .sb.collapsed .sb-item{position:relative}
    .sb.collapsed .sb-item:hover::after{content:attr(data-label);position:absolute;left:58px;top:50%;transform:translateY(-50%);background:#1a1a1a;color:#fff;font-size:12px;padding:5px 10px;border-radius:5px;white-space:nowrap;z-index:999;box-shadow:0 4px 12px rgba(0,0,0,.3);pointer-events:none}
    .sb.collapsed .sb-item:hover::before{content:'';position:absolute;left:52px;top:50%;transform:translateY(-50%);border:5px solid transparent;border-right-color:#1a1a1a;z-index:999}

    /* ======== HEADER ======== */
    .hdr{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--hdr-h);background:var(--sb-bg);color:#ccc;display:flex;align-items:center;justify-content:flex-end;padding:0 12px;z-index:90;gap:2px;transition:left .25s ease}
    body.sb-collapsed .hdr{left:54px}
    body.sb-collapsed .ci-main{margin-left:54px}
    .hdr-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;padding:5px 12px;color:#ccc;text-decoration:none;border-radius:5px;transition:all .12s;border:none;background:none;cursor:pointer;position:relative}
    .hdr-btn:hover{background:rgba(255,255,255,.08);color:#fff;text-decoration:none}
    .hdr-btn i{font-size:16px}.hdr-btn span{font-size:9px;font-weight:500}
    .hdr-badge{position:absolute;top:1px;right:6px;background:var(--danger);color:#fff;font-size:8px;font-weight:700;width:15px;height:15px;border-radius:50%;display:flex;align-items:center;justify-content:center}

    /* ======== NOTIFICATION BELL ======== */
    .hdr-notif{position:relative}
    .hdr-notif-btn{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border:none;background:none;cursor:pointer;border-radius:8px;transition:all .15s;color:#ccc;position:relative}
    .hdr-notif-btn:hover{background:rgba(255,255,255,.1);color:#fff}
    .hdr-notif-btn i{font-size:18px;transition:transform .2s}
    .hdr-notif-btn:hover i{transform:scale(1.1)}
    .hdr-notif-btn.has-unread i{animation:bellSwing 2s ease-in-out infinite}
    @keyframes bellSwing{0%,40%,100%{transform:rotate(0)}5%{transform:rotate(12deg)}10%{transform:rotate(-10deg)}15%{transform:rotate(8deg)}20%{transform:rotate(-6deg)}25%{transform:rotate(3deg)}30%{transform:rotate(0)}}
    .hdr-notif-badge{position:absolute;top:2px;right:2px;background:var(--danger);color:#fff;font-size:9px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid var(--sb-bg);line-height:1;opacity:0;transform:scale(0);transition:all .3s cubic-bezier(.68,-.55,.27,1.55)}
    .hdr-notif-badge.show{opacity:1;transform:scale(1)}
    .hdr-notif-badge.pulse{animation:badgePulse 1.5s ease-in-out infinite}
    @keyframes badgePulse{0%,100%{box-shadow:0 0 0 0 rgba(217,83,79,.5)}50%{box-shadow:0 0 0 6px rgba(217,83,79,0)}}

    .hdr-notif-panel{position:absolute;top:calc(100% + 8px);right:-60px;width:360px;background:#fff;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.2),0 2px 10px rgba(0,0,0,.08);opacity:0;visibility:hidden;transform:translateY(-10px);transition:all .25s cubic-bezier(.4,0,.2,1);z-index:200;overflow:hidden;max-height:500px;display:flex;flex-direction:column}
    .hdr-notif-panel.show{opacity:1;visibility:visible;transform:translateY(0)}
    .hdr-notif-head{padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #eee;flex-shrink:0}
    .hdr-notif-head h4{font-size:14px;font-weight:700;color:var(--c1);margin:0;display:flex;align-items:center;gap:8px}
    .hdr-notif-head-count{background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;min-width:18px;text-align:center}
    .hdr-notif-mark-all{font-size:11px;color:var(--accent);cursor:pointer;border:none;background:none;font-weight:600;padding:4px 8px;border-radius:4px;transition:all .12s;font-family:inherit}
    .hdr-notif-mark-all:hover{background:var(--accent-l)}
    .hdr-notif-mark-all:disabled{color:var(--c3);cursor:default;opacity:.5}
    .hdr-notif-mark-all:disabled:hover{background:none}

    .hdr-notif-list{overflow-y:auto;flex:1;max-height:380px}
    .hdr-notif-item{display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid #f5f5f5;cursor:pointer;transition:background .12s;position:relative;text-decoration:none;color:inherit}
    .hdr-notif-item:hover{background:#f8faf9;text-decoration:none;color:inherit}
    .hdr-notif-item.unread{background:#f0f9f5}
    .hdr-notif-item.unread::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--accent);border-radius:0 2px 2px 0}
    .hdr-notif-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
    .hdr-notif-icon.critical{background:#ffebee;color:#c62828}
    .hdr-notif-icon.warning{background:#fff8e1;color:#e65100}
    .hdr-notif-icon.info{background:#e3f2fd;color:#1565c0}
    .hdr-notif-icon.success{background:#e8f5e9;color:#2e7d32}
    .hdr-notif-body{flex:1;min-width:0}
    .hdr-notif-title{font-size:12px;font-weight:600;color:var(--c1);margin-bottom:2px;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
    .hdr-notif-msg{font-size:11px;color:var(--c2);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4}
    .hdr-notif-meta{display:flex;align-items:center;gap:8px;margin-top:4px}
    .hdr-notif-time{font-size:10px;color:var(--c3)}
    .hdr-notif-type-tag{font-size:9px;font-weight:600;padding:1px 5px;border-radius:3px;text-transform:uppercase;letter-spacing:.3px}
    .hdr-notif-type-tag.critical{background:#ffcdd2;color:#b71c1c}
    .hdr-notif-type-tag.warning{background:#ffe0b2;color:#bf360c}
    .hdr-notif-type-tag.info{background:#bbdefb;color:#0d47a1}

    .hdr-notif-actions{position:absolute;top:8px;right:8px;display:flex;gap:2px;opacity:0;transition:opacity .15s}
    .hdr-notif-item:hover .hdr-notif-actions{opacity:1}
    .hdr-notif-act{width:24px;height:24px;border:none;border-radius:5px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;transition:all .12s;background:rgba(0,0,0,.04);color:var(--c3);padding:0;line-height:1}
    .hdr-notif-act:hover{background:rgba(0,0,0,.1);color:var(--c1)}
    .hdr-notif-act.dismiss:hover{background:#ffebee;color:#c62828}
    .hdr-notif-act.snooze:hover{background:#e8f5e9;color:#2e7d32}
    .hdr-notif-act[title]{position:relative}
    .hdr-notif-action-tag{display:inline-flex;align-items:center;padding:1px 6px;border-radius:3px;font-size:9px;font-weight:700;background:#fff3e0;color:#e65100;margin-left:6px;vertical-align:middle;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}
    .hdr-notif-item.is-action{background:#fffbf4}
    .hdr-notif-item.is-action.unread{background:#fff8ed}
    .hdr-notif-item.is-action.unread::before{background:#e65100}

    .hdr-notif-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;color:var(--c3)}
    .hdr-notif-empty i{font-size:32px;margin-bottom:10px;opacity:.3}
    .hdr-notif-empty p{font-size:12px;margin:0}

    .hdr-notif-footer{padding:10px 16px;border-top:1px solid #eee;text-align:center;flex-shrink:0}
    .hdr-notif-footer a{font-size:12px;color:var(--accent);font-weight:600;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:4px;padding:4px;border-radius:4px;transition:background .12s}
    .hdr-notif-footer a:hover{background:var(--accent-l);text-decoration:none}

    /* ======== PROFILE DROPDOWN ======== */
    .hdr-profile{position:relative;margin-left:4px}
    .hdr-profile-btn{display:flex;align-items:center;gap:8px;padding:4px 8px;border:none;background:none;cursor:pointer;border-radius:6px;transition:all .15s;color:#ccc}
    .hdr-profile-btn:hover{background:rgba(255,255,255,.1)}
    .hdr-profile-avatar{width:32px;height:32px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0;overflow:hidden;border:2px solid rgba(255,255,255,.15);transition:border-color .15s}
    .hdr-profile-btn:hover .hdr-profile-avatar{border-color:var(--accent)}
    .hdr-profile-avatar img{width:100%;height:100%;object-fit:cover}
    .hdr-profile-info{text-align:left;display:flex;flex-direction:column;line-height:1.2}
    .hdr-profile-name{font-size:12px;font-weight:600;color:#eee;white-space:nowrap;max-width:120px;overflow:hidden;text-overflow:ellipsis}
    .hdr-profile-role{font-size:9px;color:#999;font-weight:400}
    .hdr-profile-caret{font-size:9px;color:#888;transition:transform .2s}
    .hdr-profile-btn.open .hdr-profile-caret{transform:rotate(180deg)}
    .hdr-dropdown{position:absolute;top:calc(100% + 6px);right:0;width:240px;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.18),0 2px 8px rgba(0,0,0,.08);opacity:0;visibility:hidden;transform:translateY(-8px);transition:all .2s ease;z-index:200;overflow:hidden}
    .hdr-dropdown.show{opacity:1;visibility:visible;transform:translateY(0)}
    .hdr-dd-header{padding:14px 16px;background:#f8f9fa;border-bottom:1px solid #eee;display:flex;align-items:center;gap:10px}
    .hdr-dd-avatar{width:38px;height:38px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:15px;flex-shrink:0;overflow:hidden}
    .hdr-dd-avatar img{width:100%;height:100%;object-fit:cover}
    .hdr-dd-name{font-size:13px;font-weight:600;color:var(--c1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .hdr-dd-role{font-size:11px;color:var(--c3)}
    .hdr-dd-body{padding:6px 0}
    .hdr-dd-item{display:flex;align-items:center;gap:10px;padding:9px 16px;font-size:13px;color:var(--c1);text-decoration:none;cursor:pointer;transition:background .12s;border:none;background:none;width:100%;font-family:inherit}
    .hdr-dd-item:hover{background:#f0f7f4;color:var(--accent);text-decoration:none}
    .hdr-dd-item i{width:18px;text-align:center;font-size:14px;color:var(--c3)}
    .hdr-dd-item:hover i{color:var(--accent)}
    .hdr-dd-sep{height:1px;background:#eee;margin:4px 0}
    .hdr-dd-lang{display:flex;align-items:center;gap:0;padding:4px 16px 8px}
    .hdr-dd-lang-label{font-size:11px;color:var(--c3);margin-right:auto}
    .hdr-dd-lang-toggle{display:flex;background:#f0f0f0;border-radius:6px;overflow:hidden;border:1px solid #e0e0e0}
    .hdr-dd-lang-btn{padding:4px 12px;font-size:11px;font-weight:600;border:none;background:none;cursor:pointer;color:var(--c2);transition:all .15s;font-family:inherit}
    .hdr-dd-lang-btn.active{background:var(--accent);color:#fff}
    .hdr-dd-lang-btn:hover:not(.active){background:#e5e5e5}
    .hdr-dd-logout{color:var(--danger)!important}
    .hdr-dd-logout i{color:var(--danger)!important}
    .hdr-dd-logout:hover{background:#fef2f2!important}

    /* ======== MOBILE TOGGLE & OVERLAY ======== */
    .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:95}

    /* ======== MOBILE BOTTOM NAV ======== */
    .mob-nav{display:none;position:fixed;bottom:0;left:0;right:0;height:56px;background:var(--sb-bg);z-index:100;border-top:1px solid rgba(255,255,255,.08);padding:0 4px}
    .mob-nav-inner{display:flex;align-items:stretch;justify-content:space-around;height:100%;max-width:480px;margin:0 auto}
    .mob-nav-item{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:#888;text-decoration:none;font-size:9px;font-weight:500;padding:4px 2px;flex:1;transition:color .15s;position:relative;min-width:0}
    .mob-nav-item i{font-size:18px;line-height:1}
    .mob-nav-item span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;text-align:center}
    .mob-nav-item:hover,.mob-nav-item.active{color:#fff;text-decoration:none}
    .mob-nav-item.active::after{content:'';position:absolute;top:0;left:25%;right:25%;height:2px;background:var(--accent);border-radius:0 0 2px 2px}
    .mob-nav-badge{position:absolute;top:2px;right:calc(50% - 16px);background:var(--danger);color:#fff;font-size:7px;font-weight:700;width:14px;height:14px;border-radius:50%;display:flex;align-items:center;justify-content:center}

    /* ======== MAIN CONTENT ======== */
    .ci-main{margin-left:var(--sb-w);margin-top:var(--hdr-h);min-height:calc(100vh - var(--hdr-h));padding:20px 24px;overflow-x:hidden;max-width:100%}

    /* ======== CARD ======== */
    .ci-card{background:var(--card);border:1px solid var(--border);border-radius:6px;margin-bottom:16px;overflow:hidden;max-width:100%}
    .ci-card-head{padding:12px 16px;font-weight:600;font-size:14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:8px}
    .ci-card-body{padding:16px;overflow:hidden;word-break:break-word}

    /* ======== FORM ======== */
    .ci-fg{margin-bottom:12px}
    .ci-label{display:block;font-size:13px;font-weight:600;color:var(--c1);margin-bottom:3px}
    .ci-hint{font-size:11px;color:var(--c3);margin-top:2px;font-style:italic}
    .ci-input,.ci-select{width:100%;padding:7px 10px;font-size:13px;border:1px solid var(--input-bd);border-radius:4px;background:var(--input-bg);color:var(--c1);transition:border .15s,box-shadow .15s;font-family:inherit}
    .ci-input:focus,.ci-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(26,138,92,.15)}
    .ci-input::placeholder{color:#bbb;font-style:italic}

    /* ======== BUTTON ======== */
    .ci-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;font-size:13px;font-weight:600;border-radius:4px;border:1px solid transparent;cursor:pointer;transition:all .12s;text-decoration:none;font-family:inherit;white-space:nowrap}
    .ci-btn:hover{text-decoration:none}
    .ci-btn-primary{background:var(--accent);color:#fff;border-color:var(--accent-d)}.ci-btn-primary:hover{background:var(--accent-h)}
    .ci-btn-secondary{background:#f0f0f0;color:#555;border-color:#ccc}.ci-btn-secondary:hover{background:#e5e5e5}
    .ci-btn-danger{background:var(--danger);color:#fff;border-color:#c9302c}.ci-btn-danger:hover{background:#c9302c}
    .ci-btn-outline{background:transparent;color:var(--accent);border-color:var(--accent)}.ci-btn-outline:hover{background:var(--accent);color:#fff}
    .ci-btn-sm{padding:3px 8px;font-size:11px}
    .ci-btn-block{display:flex;width:100%;justify-content:center}

    /* ======== TABLE ======== */
    .ci-table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:4px;max-width:100%}
    .ci-table{width:100%;border-collapse:collapse;font-size:13px}
    .ci-table th{background:#f7f7f7;color:var(--c2);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.3px;padding:9px 12px;text-align:left;border-bottom:2px solid var(--border);white-space:nowrap}
    .ci-table td{padding:9px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
    .ci-table tbody tr:hover{background:#fafafa}
    .ci-table tbody tr{cursor:pointer;transition:background .1s}

    /* ======== BADGE ======== */
    .ci-badge{display:inline-flex;align-items:center;padding:2px 8px;font-size:11px;font-weight:600;border-radius:3px;white-space:nowrap}
    .ci-badge-success{background:#dff0d8;color:#3c763d}.ci-badge-warning{background:#fcf8e3;color:#8a6d3b}
    .ci-badge-danger{background:#f2dede;color:#a94442}.ci-badge-info{background:#d9edf7;color:#31708f}
    .ci-badge-default{background:#f0f0f0;color:#777}.ci-badge-primary{background:var(--accent-l);color:var(--accent-d)}

    /* ======== STATS ======== */
    .ci-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}.ci-stats>*{min-width:0}
    .ci-stat{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:16px;display:flex;align-items:center;gap:14px;min-width:0;overflow:hidden}
    .ci-stat-icon{width:44px;height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
    .ci-stat-icon.green{background:#e8f5ef;color:var(--accent)}.ci-stat-icon.blue{background:#e3f2fd;color:#1976d2}
    .ci-stat-icon.orange{background:#fff3e0;color:#e65100}.ci-stat-icon.red{background:#ffebee;color:#c62828}
    .ci-stat-icon.purple{background:#f3e5f5;color:#7b1fa2}.ci-stat-icon.teal{background:#e0f2f1;color:#00695c}
    .ci-stat-val{font-size:24px;font-weight:700;color:var(--c1);line-height:1.1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .ci-stat-lbl{font-size:12px;color:var(--c3);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

    /* ======== ALERTS ======== */
    .ci-alert{padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:10px;display:flex;align-items:flex-start;gap:8px}
    .ci-alert-info{background:#d9edf7;color:#31708f;border-left:4px solid #31708f}
    .ci-alert-success{background:#dff0d8;color:#3c763d;border-left:4px solid #3c763d}
    .ci-alert-warning{background:#fcf8e3;color:#8a6d3b;border-left:4px solid #8a6d3b}
    .ci-alert-danger{background:#f2dede;color:#a94442;border-left:4px solid #a94442}

    /* ======== MODAL ======== */
    .ci-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px}
    .ci-modal-bg.show{display:flex}
    .ci-modal{background:#fff;border-radius:6px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.3)}
    .ci-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid var(--border)}
    .ci-modal-hdr h3{font-size:15px;font-weight:600;margin:0}
    .ci-modal-close{background:none;border:none;font-size:18px;color:#999;cursor:pointer;padding:4px}.ci-modal-close:hover{color:#333}
    .ci-modal-body{padding:18px}

    /* ======== PAGE HEADER ======== */
    .ci-pg-hdr{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;flex-wrap:wrap;max-width:100%}
    .ci-pg-title{font-size:17px;font-weight:600;color:var(--c1);display:flex;align-items:center;gap:8px;overflow:hidden;text-overflow:ellipsis}
    .ci-pg-title i{color:var(--accent)}
    .ci-pg-sub{font-size:12px;color:var(--c3);margin-top:2px}
    .ci-pg-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}

    /* ======== GRID CLASSES ======== */
    .ci-g2{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}.ci-g2>*{min-width:0}
    .ci-g3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.ci-g3>*{min-width:0}
    .ci-g4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.ci-g4>*{min-width:0}
    .ci-dash-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start}.ci-dash-grid>*{min-width:0;overflow:hidden}
    .ci-quick-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.ci-quick-actions>*{min-width:0}
    .ci-auto-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(280px,100%),1fr));gap:16px}.ci-auto-grid>*{min-width:0}

    /* ======== FILTER BAR ======== */
    .ci-filter-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .ci-filter-bar .ci-input,.ci-filter-bar .ci-select{min-width:0}

    /* ======== HELPERS ======== */
    .ci-hide-mobile{display:initial}
    .ci-show-mobile{display:none!important}

    /* ======== MISC ======== */
    .ci-empty{text-align:center;padding:40px 20px;color:var(--c3)}.ci-empty i{font-size:36px;margin-bottom:10px;opacity:.4}
    .ci-loading{display:flex;align-items:center;justify-content:center;padding:40px;color:var(--c3)}
    .ci-spinner{display:inline-block;width:22px;height:22px;border:3px solid #e0e0e0;border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}
    @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
    .ci-fade{animation:fadeIn .2s ease-out}
    .ci-progress{height:6px;background:#eee;border-radius:3px;overflow:hidden}
    .ci-progress-bar{height:100%;border-radius:3px;transition:width .3s}
    .ci-progress-green{background:var(--ok)}.ci-progress-orange{background:var(--warn)}.ci-progress-red{background:var(--danger)}
    .ci-list-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border-bottom:1px solid #f0f0f0;transition:background .1s;min-width:0;overflow:hidden}.ci-list-item>*{min-width:0}.ci-list-item:hover{background:#fafafa}.ci-list-item:last-child{border-bottom:none}
    .ci-tabs{display:flex;border-bottom:2px solid var(--border);margin-bottom:16px}
    .ci-tab{padding:7px 14px;font-size:13px;font-weight:500;color:var(--c3);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .12s;text-decoration:none;background:none;border-top:none;border-left:none;border-right:none}
    .ci-tab:hover{color:var(--c1)}.ci-tab.active{color:var(--accent);border-bottom-color:var(--accent)}
    .ci-tag{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;font-size:10px;background:#f0f0f0;border-radius:3px;color:#666}
    .text-danger{color:var(--danger)}.text-success{color:var(--ok)}.text-warning{color:var(--warn)}.text-muted{color:var(--c3)}
    .ci-pagination{display:flex;align-items:center;justify-content:space-between;padding:12px 0;font-size:13px;color:var(--c2)}
    .ci-pagination-btns{display:flex;gap:4px}

    /* ================================================================
       RESPONSIVE — MUST be LAST so they override base styles above
       ================================================================ */

    /* TABLET ≤1024 */
    @media(max-width:1024px){
        :root{--sb-w:200px}
        .ci-main{padding:18px 16px}
        .ci-stats{grid-template-columns:repeat(2,1fr)}
        .ci-quick-actions{grid-template-columns:repeat(2,1fr)}
        .ci-dash-grid{grid-template-columns:1fr}
    }

    /* MOBILE ≤768 */
    @media(max-width:768px){
        /* Sidebar hidden, toggle visible */
        .sb{transform:translateX(-100%)}.sb.open{transform:translateX(0)}
        .overlay.show{display:block}
        .mob-nav{display:block}

        /* Header full-width */
        .hdr{left:0}
        .hdr-btn span{display:none}
        .hdr-btn i{font-size:18px}
        .hdr-btn{padding:6px 10px}
        .hdr-profile-info{display:none}
        .hdr-profile-caret{display:none}
        .hdr-dropdown{width:220px;right:-8px}
        .hdr-notif-panel{position:fixed;top:var(--hdr-h);left:8px;right:8px;width:auto;max-height:calc(100vh - var(--hdr-h) - 70px);border-radius:0 0 12px 12px;box-shadow:0 12px 40px rgba(0,0,0,.25)}
        .hdr-notif-list{max-height:calc(100vh - var(--hdr-h) - 180px)}
        .hdr-notif-item{padding:10px 12px;padding-right:60px}
        .hdr-notif-actions{opacity:1}
        .hdr-notif-act{width:28px;height:28px;font-size:13px;background:rgba(0,0,0,.06)}
        .hdr-notif-icon{width:30px;height:30px;font-size:13px}
        .hdr-notif-title{font-size:12px}
        .hdr-notif-msg{font-size:11px;-webkit-line-clamp:1}

        /* Main content */
        .ci-main{margin-left:0;margin-bottom:64px;padding:14px 12px}

        /* Page Header stacks */
        .ci-pg-hdr{flex-direction:column;align-items:stretch;gap:8px}
        .ci-pg-title{font-size:15px}
        .ci-pg-actions{display:flex;gap:6px}
        .ci-pg-actions .ci-btn{flex:1;justify-content:center;font-size:12px;padding:7px 8px}

        /* Stats 2-per-row */
        .ci-stats{grid-template-columns:repeat(2,1fr);gap:10px}
        .ci-stat{padding:10px;gap:10px}
        .ci-stat-val{font-size:18px}
        .ci-stat-icon{width:36px;height:36px;font-size:15px}
        .ci-stat-lbl{font-size:11px}

        /* Dashboard grid → 1 col */
        .ci-dash-grid{grid-template-columns:1fr;gap:12px}

        /* Quick actions 2×2 */
        .ci-quick-actions{grid-template-columns:repeat(2,1fr);gap:8px}

        /* Auto-grid → 1 col */
        .ci-auto-grid{grid-template-columns:1fr;gap:12px}

        /* Built-in grids → 1 col */
        .ci-g2,.ci-g3,.ci-g4{grid-template-columns:1fr;gap:12px}

        /* Cards compact */
        .ci-card{margin-bottom:10px}
        .ci-card-head{padding:10px 12px;font-size:13px}
        .ci-card-body{padding:12px}

        /* Modals bottom-sheet */
        .ci-modal-bg{padding:0;align-items:flex-end}
        .ci-modal{max-width:100%;margin:0;border-radius:16px 16px 0 0;max-height:90vh}
        .ci-modal-body{padding:14px}

        /* Tabs scroll */
        .ci-tabs{overflow-x:auto;-webkit-overflow-scrolling:touch;gap:0;flex-wrap:nowrap}
        .ci-tab{white-space:nowrap;padding:7px 10px;font-size:12px;flex-shrink:0}

        /* Table compact */
        .ci-table th,.ci-table td{padding:7px 8px;font-size:12px}

        /* Pagination stacks */
        .ci-pagination{flex-direction:column;gap:8px;text-align:center}

        /* Buttons */
        .ci-btn{padding:6px 10px;font-size:12px}

        /* Touch-friendly inputs */
        .ci-input,.ci-select{font-size:14px;padding:9px 10px}

        /* Filter bar stacks */
        .ci-filter-bar{flex-direction:column;gap:8px}
        .ci-filter-bar>*{width:100%;min-width:0}

        /* Helpers */
        .ci-hide-mobile{display:none!important}
        .ci-show-mobile{display:initial!important}
    }

    /* SMALL MOBILE ≤480 */
    @media(max-width:480px){
        .hdr-notif-panel{left:4px;right:4px;max-height:calc(100vh - var(--hdr-h) - 64px)}
        .hdr-notif-head{padding:10px 12px}
        .hdr-notif-head h4{font-size:13px}
        .hdr-notif-footer{padding:8px 12px}
        .ci-main{padding:10px 8px;margin-bottom:64px}
        .ci-stats{grid-template-columns:1fr 1fr;gap:8px}
        .ci-stat{padding:8px;gap:8px}
        .ci-stat-val{font-size:16px}
        .ci-stat-icon{width:32px;height:32px;font-size:13px}
        .ci-stat-lbl{font-size:10px}
        .ci-pg-title{font-size:14px}
        .ci-pg-sub{font-size:11px}
        .ci-card-head{padding:8px 10px;font-size:12px}
        .ci-card-body{padding:10px}
    }
    </style>
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
    <?php foreach ($extraJs as $js): ?>
    <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
</head>
        <?php
    }
    
    // ── Page-permission helpers ───────────────────────────────────────────
    private static ?array $_ppCache  = null;
    private static int    $_ppRoleId = 0;

    private static function loadPagePerms(int $roleId): array {
        if ($roleId <= 0) return [];
        if (self::$_ppCache !== null && self::$_ppRoleId === $roleId) return self::$_ppCache;
        self::$_ppRoleId = $roleId;
        self::$_ppCache  = [];    // default: empty = unrestricted
        try {
            // First ensure the table exists (idempotent, no FK)
            Database::query("CREATE TABLE IF NOT EXISTS page_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_id INT NOT NULL,
                page_key VARCHAR(100) NOT NULL,
                is_allowed TINYINT(1) NOT NULL DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_rp (role_id, page_key),
                INDEX idx_rid (role_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $rows = Database::fetchAll(
                "SELECT page_key FROM page_permissions WHERE role_id = ? AND is_allowed = 1",
                [$roleId]
            );
            // Non-empty result = custom restrictions are configured for this role
            self::$_ppCache = array_column($rows, 'page_key');
        } catch (\Throwable $e) {
            // Any DB failure → treat as "no restriction" (show all)
            self::$_ppCache = [];
        }
        return self::$_ppCache;
    }

    // true = page is visible for this user.
    // empty $allowed → no custom config for this role → show all.
    // non-empty $allowed → only listed keys are visible.
    private static function canSeePage(array $allowed, string $key): bool {
        return empty($allowed) || in_array($key, $allowed, true);
    }

    public static function sidebar(string $activePage = 'dashboard'): void {
        $user = Auth::getCurrentUser();
        $role = $user['role_name'] ?? 'user';
        $lang = I18n::getCurrentLang();
        $displayName = !empty($user['full_name_th'])
            ? htmlspecialchars($user['full_name_th'])
            : htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $roleDisplay  = htmlspecialchars($user['role_display'] ?? $role);
        $avatarLetter = !empty($user['full_name_th'])
            ? mb_substr($user['full_name_th'], 0, 1)
            : mb_substr($user['first_name'] ?? 'U', 0, 1);
        $avatarUrl = $user['avatar_url'] ?? '';
        $isAdmin   = in_array($role, ['admin', 'lab_manager', 'ceo']);

        // Admin always sees everything; other roles respect page_permissions table.
        $roleId  = (int)($user['role_id'] ?? 0);
        $allowed = ($role === 'admin') ? [] : self::loadPagePerms($roleId);
        $see     = function(string $k) use ($allowed): bool {
            return self::canSeePage($allowed, $k);
        };

        // Pre-compute which admin-section items are visible
        // (so we can hide the entire section header if nothing is accessible)
        $adminItems = ['alerts','users','user-chemicals','models3d','warehouses',
                       'containers','cas-map','settings','system-monitor','ai-assistant'];
        $anyAdminVisible = $isAdmin && (
            $role === 'admin' ||
            array_reduce($adminItems, function(bool $c, string $k) use ($see): bool {
                return $c || $see($k);
            }, false)
        );
        ?>
    <div class="overlay" id="overlay"></div>
    
    <aside class="sb" id="sidebar">
        <div class="sb-logo">
            <div class="sb-logo-icon" onclick="toggleSidebarCollapse()" title="ยุบ/แสดงเมนู"><i class="fas fa-flask-vial"></i></div>
            <div class="sb-logo-text-wrap"><div class="sb-logo-text"><span style="color:#f97316">SUT</span> chemBot</div><div class="sb-logo-sub">Chemical Management</div></div>
        </div>
        <nav class="sb-nav">
            <!-- 1. คลังสารเคมี -->
            <?php if ($see('stock')): ?>
            <a href="/v1/pages/stock.php" class="sb-item <?php echo $activePage==='stock'?'active':''; ?>" data-label="<?php echo $lang==='th'?'คลังสารเคมี':'Chemical Stock'; ?>">
                <i class="fas fa-flask icon"></i> <span class="sb-item-label"><?php echo $lang==='th'?'คลังสารเคมี':'Chemical Stock'; ?></span>
            </a>
            <?php endif; ?>
            <!-- 1b. ห้องของฉัน -->
            <?php if ($see('myroom')): ?>
            <a href="/v1/pages/myroom.php" class="sb-item <?php echo $activePage==='myroom'?'active':''; ?>" data-label="<?php echo $lang==='th'?'ห้องของฉัน':'My Rooms'; ?>">
                <i class="fas fa-door-open icon"></i> <span class="sb-item-label"><?php echo $lang==='th'?'ห้องของฉัน':'My Rooms'; ?></span>
            </a>
            <?php endif; ?>
            <!-- 2. ธุรกรรม -->
            <?php
            $showBorrow   = $see('borrow');
            $showActivity = $see('activity');
            $showDisposal = $see('disposal') && $isAdmin;
            if ($showBorrow || $showActivity || $showDisposal):
            ?>
            <div class="sb-item <?php echo in_array($activePage,['borrow','activity','disposal'])?'active expanded':''; ?>" onclick="toggleSub(this)" role="button" data-label="<?php echo $lang==='th'?'ธุรกรรม':'Transactions'; ?>">
                <i class="fas fa-exchange-alt icon"></i> <span class="sb-item-label"><?php echo $lang==='th'?'ธุรกรรม':'Transactions'; ?></span>
                <i class="fas fa-chevron-down arrow"></i>
            </div>
            <div class="sb-sub <?php echo in_array($activePage,['borrow','activity','disposal'])?'show':''; ?>">
                <?php if ($showBorrow): ?>
                <a href="/v1/pages/borrow.php" class="sb-item <?php echo $activePage==='borrow'?'active':''; ?>">
                    <i class="fas fa-list" style="font-size:10px;margin-right:4px"></i> <?php echo $lang==='th'?'รายการธุรกรรม':'Transactions'; ?>
                </a>
                <?php endif; if ($showActivity): ?>
                <a href="/v1/pages/activity.php" class="sb-item <?php echo $activePage==='activity'?'active':''; ?>">
                    <i class="fas fa-chart-bar" style="font-size:10px;margin-right:4px;color:#2563eb"></i> <?php echo $lang==='th'?'ธุรกรรมทั้งหมด':'All Activity'; ?>
                </a>
                <?php endif; if ($showDisposal): ?>
                <a href="/v1/pages/disposal-bin.php" class="sb-item <?php echo $activePage==='disposal'?'active':''; ?>">
                    <i class="fas fa-trash-alt" style="font-size:10px;margin-right:4px;color:#c62828"></i> <?php echo $lang==='th'?'ถังจำหน่าย':'Disposal Bin'; ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <!-- 3. ChemBot -->
            <?php if ($see('dashboard')): ?>
            <a href="/v1/" class="sb-item <?php echo $activePage==='dashboard'?'active':''; ?>" data-label="ChemBot">
                <i class="fas fa-atom icon"></i> <span class="sb-item-label">ChemBot</span>
            </a>
            <?php endif; ?>
            <!-- 4. ข้อมูลสารเคมี -->
            <?php if ($see('chemicals')): ?>
            <a href="/v1/pages/chemicals.php" class="sb-item <?php echo $activePage==='chemicals'?'active':''; ?>" data-label="<?php echo $lang==='th'?'ข้อมูลสารเคมี':'Chemicals'; ?>">
                <i class="fas fa-search icon"></i> <span class="sb-item-label"><?php echo __('nav_chemicals'); ?></span>
            </a>
            <?php endif; ?>
            <!-- 5. ตำแหน่ง -->
            <?php if ($see('locations')): ?>
            <a href="/v1/pages/locations.php" class="sb-item <?php echo $activePage==='locations'?'active':''; ?>" data-label="<?php echo $lang==='th'?'ตำแหน่ง':'Locations'; ?>">
                <i class="fas fa-sitemap icon"></i> <span class="sb-item-label"><?php echo __('nav_locations'); ?></span>
            </a>
            <?php endif; ?>
            <!-- 6. ฝ่ายปฏิบัติการ -->
            <?php if ($see('lab-stores')): ?>
            <a href="/v1/pages/lab-stores.php" class="sb-item <?php echo $activePage==='lab-stores'?'active':''; ?>" data-label="<?php echo $lang==='th'?'ฝ่ายปฏิบัติการ':'Lab Stores'; ?>">
                <i class="fas fa-store icon"></i> <span class="sb-item-label"><?php echo $lang==='th'?'ฝ่ายปฏิบัติการ':'Lab Stores'; ?></span>
            </a>
            <?php endif; ?>
            <!-- 7. แสกน QR -->
            <?php if ($see('qr-scanner')): ?>
            <a href="/v1/pages/qr-scanner.php" class="sb-item <?php echo $activePage==='qr-scanner'?'active':''; ?>" data-label="<?php echo $lang==='th'?'สแกน QR':'QR Scanner'; ?>">
                <i class="fas fa-qrcode icon"></i> <span class="sb-item-label"><?php echo __('nav_qr_scanner'); ?></span>
            </a>
            <?php endif; ?>
            <!-- 8. รายงาน -->
            <?php if ($see('reports')): ?>
            <a href="/v1/pages/reports.php" class="sb-item <?php echo $activePage==='reports'?'active':''; ?>" data-label="<?php echo $lang==='th'?'รายงาน':'Reports'; ?>">
                <i class="fas fa-chart-bar icon"></i> <span class="sb-item-label"><?php echo __('nav_reports'); ?></span>
            </a>
            <?php endif; ?>
            <!-- การจัดการ (admin / manager / ceo — only if at least one item is visible) -->
            <?php if ($anyAdminVisible): ?>
            <div class="sb-divider"></div>
            <div class="sb-item <?php echo in_array($activePage,['users','user-chemicals','settings','models3d','cas-map','warehouses','containers','alerts','ai-assistant','page-access'])?'active expanded':''; ?>" onclick="toggleSub(this)" role="button" data-label="<?php echo $lang==='th'?'การจัดการ':'Administration'; ?>">
                <i class="fas fa-cogs icon"></i> <span class="sb-item-label"><?php echo $lang==='th'?'การจัดการ':'Administration'; ?></span>
                <i class="fas fa-chevron-down arrow"></i>
            </div>
            <div class="sb-sub <?php echo in_array($activePage,['users','user-chemicals','settings','models3d','cas-map','warehouses','containers','alerts','system-monitor','ai-assistant','page-access'])?'show':''; ?>">
                <?php if ($see('alerts')): ?>
                <a href="/v1/pages/alerts.php" class="sb-item <?php echo $activePage==='alerts'?'active':''; ?>">
                    <i class="fas fa-bell" style="margin-right:6px;font-size:11px;color:#8b949e"></i><?php echo $lang==='th'?'ศูนย์แจ้งเตือน':'Alert Center'; ?>
                </a>
                <?php endif; if ($see('users')): ?>
                <a href="/v1/pages/users.php" class="sb-item <?php echo $activePage==='users'?'active':''; ?>">
                    <i class="fas fa-users" style="margin-right:6px;font-size:11px;color:#8b949e"></i><?php echo $lang==='th'?'จัดการผู้ใช้':'Manage Users'; ?>
                </a>
                <?php endif; if ($see('user-chemicals')): ?>
                <a href="/v1/pages/user-chemicals.php" class="sb-item <?php echo $activePage==='user-chemicals'?'active':''; ?>">
                    <i class="fas fa-user-shield" style="margin-right:6px;font-size:11px;color:#8b949e"></i><?php echo $lang==='th'?'สารเคมีรายบุคคล':'User Chemicals'; ?>
                </a>
                <?php endif; if ($see('models3d')): ?>
                <a href="/v1/pages/models3d.php" class="sb-item <?php echo $activePage==='models3d'?'active':''; ?>">
                    <i class="fas fa-cube" style="margin-right:6px;font-size:11px;color:#8b949e"></i><?php echo $lang==='th'?'โมเดล 3D':'3D Models'; ?>
                </a>
                <?php endif; if ($see('warehouses')): ?>
                <a href="/v1/pages/warehouses.php" class="sb-item <?php echo $activePage==='warehouses'?'active':''; ?>">
                    <i class="fas fa-warehouse" style="margin-right:6px;font-size:11px;color:#8b949e"></i><?php echo $lang==='th'?'คลังสารเคมี':'Chemical Warehouses'; ?>
                </a>
                <?php endif; if ($see('containers')): ?>
                <a href="/v1/pages/containers.php" class="sb-item <?php echo $activePage==='containers'?'active':''; ?>">
                    <i class="fas fa-box-open" style="margin-right:6px;font-size:11px;color:#8b949e"></i><?php echo $lang==='th'?'ภาชนะบรรจุ':'Containers'; ?>
                </a>
                <?php endif; if ($see('cas-map')): ?>
                <a href="/v1/pages/cas-map.php" class="sb-item <?php echo $activePage==='cas-map'?'active':''; ?>">
                    <i class="fas fa-atom" style="margin-right:6px;font-size:11px;color:#8b949e"></i>CAS Map
                </a>
                <?php endif; if ($see('settings')): ?>
                <a href="/v1/pages/settings.php" class="sb-item <?php echo $activePage==='settings'?'active':''; ?>">
                    <i class="fas fa-gear" style="margin-right:6px;font-size:11px;color:#8b949e"></i><?php echo $lang==='th'?'ตั้งค่าระบบ':'System Settings'; ?>
                </a>
                <?php endif; if ($see('system-monitor')): ?>
                <a href="/v1/pages/system-monitor.php" class="sb-item <?php echo $activePage==='system-monitor'?'active':''; ?>">
                    <i class="fas fa-display" style="margin-right:6px;font-size:11px;color:#8b949e"></i><?php echo $lang==='th'?'ตรวจสอบระบบ':'System Monitor'; ?>
                </a>
                <?php endif; ?>
                <?php if ($role === 'admin'): ?>
                <a href="/v1/pages/page-access.php" class="sb-item <?php echo $activePage==='page-access'?'active':''; ?>">
                    <i class="fas fa-shield-alt" style="margin-right:6px;font-size:11px;color:#e11d48"></i><?php echo $lang==='th'?'สิทธิ์หน้า (ACL)':'Page Access (ACL)'; ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </nav>
    </aside>
    
    <header class="hdr">

        <!-- Notification Bell -->
        <div class="hdr-notif">
            <button class="hdr-notif-btn" id="notifToggle" type="button" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="hdr-notif-badge" id="notifBadge"></span>
            </button>
            <div class="hdr-notif-panel" id="notifPanel">
                <div class="hdr-notif-head">
                    <h4><?php echo $lang==='th'?'การแจ้งเตือน':'Notifications'; ?> <span class="hdr-notif-head-count" id="notifHeadCount" style="display:none"></span></h4>
                    <button class="hdr-notif-mark-all" id="notifMarkAll" disabled><?php echo $lang==='th'?'อ่านทั้งหมด':'Mark all read'; ?></button>
                </div>
                <div class="hdr-notif-list" id="notifList">
                    <div class="hdr-notif-empty"><i class="fas fa-bell-slash"></i><p><?php echo $lang==='th'?'ไม่มีการแจ้งเตือน':'No notifications'; ?></p></div>
                </div>
                <div class="hdr-notif-footer">
                    <a href="/v1/pages/alerts.php"><?php echo $lang==='th'?'ดูทั้งหมด':'View All'; ?> <i class="fas fa-arrow-right" style="font-size:10px"></i></a>
                </div>
            </div>
        </div>

        <!-- Profile Dropdown -->
        <div class="hdr-profile">
            <button class="hdr-profile-btn" id="profileToggle" type="button" aria-label="Profile menu">
                <?php if ($avatarUrl): ?>
                <div class="hdr-profile-avatar"><img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt=""></div>
                <?php else: ?>
                <div class="hdr-profile-avatar"><?php echo $avatarLetter; ?></div>
                <?php endif; ?>
                <div class="hdr-profile-info">
                    <span class="hdr-profile-name"><?php echo $displayName; ?></span>
                    <span class="hdr-profile-role"><?php echo $roleDisplay; ?></span>
                </div>
                <i class="fas fa-chevron-down hdr-profile-caret"></i>
            </button>
            <div class="hdr-dropdown" id="profileDropdown">
                <div class="hdr-dd-header">
                    <?php if ($avatarUrl): ?>
                    <div class="hdr-dd-avatar"><img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt=""></div>
                    <?php else: ?>
                    <div class="hdr-dd-avatar"><?php echo $avatarLetter; ?></div>
                    <?php endif; ?>
                    <div style="min-width:0;flex:1">
                        <div class="hdr-dd-name"><?php echo $displayName; ?></div>
                        <div class="hdr-dd-role"><?php echo $roleDisplay; ?></div>
                    </div>
                </div>
                <div class="hdr-dd-body">
                    <a href="/v1/pages/borrow.php" class="hdr-dd-item">
                        <i class="fas fa-exchange-alt"></i>
                        <span><?php echo $lang==='th'?'ธุรกรรมของฉัน':'My Transactions'; ?></span>
                    </a>
                    <a href="/v1/pages/profile.php?tab=account" class="hdr-dd-item">
                        <i class="fas fa-shield-alt"></i>
                        <span><?php echo $lang==='th'?'จัดการบัญชี':'Account Settings'; ?></span>
                    </a>
                    <?php if ($isAdmin): ?>
                    <a href="/v1/pages/settings.php" class="hdr-dd-item">
                        <i class="fas fa-cog"></i>
                        <span><?php echo $lang==='th'?'ตั้งค่าระบบ':'System Settings'; ?></span>
                    </a>
                    <?php endif; ?>
                    <div class="hdr-dd-sep"></div>
                    <div class="hdr-dd-lang">
                        <span class="hdr-dd-lang-label"><?php echo $lang==='th'?'ภาษา':'Language'; ?></span>
                        <div class="hdr-dd-lang-toggle">
                            <button class="hdr-dd-lang-btn <?php echo $lang==='th'?'active':''; ?>" onclick="location.href='?lang=th'">🇹🇭 TH</button>
                            <button class="hdr-dd-lang-btn <?php echo $lang==='en'?'active':''; ?>" onclick="location.href='?lang=en'">🇬🇧 EN</button>
                        </div>
                    </div>
                    <div class="hdr-dd-sep"></div>
                    <a href="#" class="hdr-dd-item hdr-dd-logout" onclick="event.preventDefault();fetch('/v1/api/auth.php?action=logout').then(()=>location.href='/v1/')">
                        <i class="fas fa-right-from-bracket"></i>
                        <span><?php echo $lang==='th'?'ออกจากระบบ':'Sign Out'; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Bottom Navigation -->
    <nav class="mob-nav" id="mobNav">
        <div class="mob-nav-inner">
            <a href="/v1/" class="mob-nav-item <?php echo $activePage==='dashboard'?'active':''; ?>">
                <i class="fas fa-atom"></i><span>ChemBot</span>
            </a>
            <a href="/v1/pages/stock.php" class="mob-nav-item <?php echo in_array($activePage,['containers','stock'])?'active':''; ?>">
                <i class="fas fa-flask"></i><span><?php echo $lang==='th'?'คลัง':'Stock'; ?></span>
                <span class="mob-nav-badge" id="mobAlertBadge" style="display:none"></span>
            </a>
            <a href="/v1/pages/qr-scanner.php" class="mob-nav-item <?php echo $activePage==='qr-scanner'?'active':''; ?>">
                <i class="fas fa-qrcode"></i><span><?php echo $lang==='th'?'สแกน':'Scan'; ?></span>
            </a>
            <a href="/v1/pages/chemicals.php" class="mob-nav-item <?php echo $activePage==='chemicals'?'active':''; ?>">
                <i class="fas fa-search"></i><span><?php echo $lang==='th'?'สารเคมี':'Chemicals'; ?></span>
            </a>
            <a href="#" onclick="event.preventDefault();document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('show')" class="mob-nav-item">
                <i class="fas fa-bars"></i><span><?php echo $lang==='th'?'เมนู':'Menu'; ?></span>
            </a>
        </div>
    </nav>
        <?php
    }
    
    public static function beginContent(): void { ?>
    <main class="ci-main ci-fade">
        <?php
    }
    
    public static function endContent(): void {
        $layoutUser  = Auth::getCurrentUser();
        $layoutAdmin = $layoutUser && (
            in_array($layoutUser['role_name'] ?? '', ['admin', 'lab_manager', 'ceo']) ||
            (int)($layoutUser['role_level'] ?? 0) >= 4
        );
        ?>
    </main>
    <script>
    const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('overlay');
    overlay?.addEventListener('click',()=>{sidebar.classList.remove('open');overlay.classList.remove('show')});
    /* Sidebar Collapse Toggle */
    function toggleSidebarCollapse(){
        const collapsed=sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sb-collapsed',collapsed);
        localStorage.setItem('sbCollapsed',collapsed?'1':'0');
    }
    /* Restore collapse state on load */
    (()=>{
        if(localStorage.getItem('sbCollapsed')==='1'){
            sidebar.classList.add('collapsed');
            document.body.classList.add('sb-collapsed');
        }
    })();
    /* Profile dropdown */
    (()=>{const btn=document.getElementById('profileToggle'),dd=document.getElementById('profileDropdown');if(!btn||!dd)return;btn.addEventListener('click',e=>{e.stopPropagation();document.getElementById('notifPanel')?.classList.remove('show');const open=dd.classList.toggle('show');btn.classList.toggle('open',open)});document.addEventListener('click',e=>{if(!btn.contains(e.target)&&!dd.contains(e.target)){dd.classList.remove('show');btn.classList.remove('open')}});document.addEventListener('keydown',e=>{if(e.key==='Escape'){dd.classList.remove('show');btn.classList.remove('open')}})})();
    /* Notification Bell */
    (()=>{
        const lang='<?php echo I18n::getCurrentLang(); ?>';
        const bellIsAdmin=<?php echo $layoutAdmin ? 'true' : 'false'; ?>;
        const nBtn=document.getElementById('notifToggle'),nPanel=document.getElementById('notifPanel'),nBadge=document.getElementById('notifBadge'),nList=document.getElementById('notifList'),nCount=document.getElementById('notifHeadCount'),nMarkAll=document.getElementById('notifMarkAll');
        if(!nBtn||!nPanel)return;
        let notifData=[];

        const typeIcon={
            'expiry':'fa-clock','low_stock':'fa-battery-quarter','overdue_borrow':'fa-hourglass-end',
            'borrow_request':'fa-handshake','safety_violation':'fa-shield-alt','compliance':'fa-file-check',
            'temperature_alert':'fa-thermometer-three-quarters','custom':'fa-bell'
        };
        const sevClass={'critical':'critical','warning':'warning','info':'info'};
        const sevLabel={'critical':lang==='th'?'วิกฤต':'Critical','warning':lang==='th'?'เตือน':'Warning','info':lang==='th'?'ข้อมูล':'Info'};

        /* Build context-specific link from alert data */
        function getNotifLink(n){
            const t=n.alert_type;
            const cid=n.container_id, chid=n.chemical_id, bid=n.borrow_request_id, lid=n.lab_id;
            const isAction=n.action_required&&n.action_required!='0';
            switch(t){
                case 'borrow_request':
                    if(isAction) return '/v1/pages/borrow.php?tab=pending'+(bid?'&highlight='+bid:'');
                    return '/v1/pages/borrow.php'+(bid?'?highlight='+bid:'');
                case 'overdue_borrow':
                    return '/v1/pages/borrow.php?tab=overdue'+(bid?'&highlight='+bid:'');
                case 'expiry':
                case 'safety_violation':
                    if(cid) return '/v1/pages/containers.php?highlight='+cid;
                    if(chid) return '/v1/pages/stock.php?chemical_id='+chid;
                    return '/v1/pages/containers.php';
                case 'low_stock':
                    if(chid) return '/v1/pages/stock.php?chemical_id='+chid;
                    return '/v1/pages/stock.php';
                case 'temperature_alert':
                    if(lid) return '/v1/pages/locations.php?lab_id='+lid;
                    return '/v1/pages/locations.php';
                case 'compliance':
                    return '/v1/pages/reports.php';
                default:
                    return '/v1/pages/alerts.php';
            }
        }

        function timeAgo(dt){
            const diff=Math.floor((Date.now()-new Date(dt).getTime())/1000);
            if(diff<60) return lang==='th'?'เมื่อสักครู่':'Just now';
            if(diff<3600) return Math.floor(diff/60)+(lang==='th'?' นาทีที่แล้ว':'m ago');
            if(diff<86400) return Math.floor(diff/3600)+(lang==='th'?' ชั่วโมงที่แล้ว':'h ago');
            if(diff<604800) return Math.floor(diff/86400)+(lang==='th'?' วันที่แล้ว':'d ago');
            return new Date(dt).toLocaleDateString(lang==='th'?'th-TH':'en-US',{month:'short',day:'numeric'});
        }
        function absTime(dt){
            return new Date(dt).toLocaleString(lang==='th'?'th-TH':'en-US',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
        }

        function renderNotifs(items){
            if(!items.length){
                nList.innerHTML='<div class="hdr-notif-empty"><i class="fas fa-bell-slash"></i><p>'+(lang==='th'?'ไม่มีการแจ้งเตือน':'No notifications')+'</p></div>';
                return;
            }
            nList.innerHTML=items.map(n=>{
                const ic=typeIcon[n.alert_type]||'fa-bell';
                const unread=(!n.is_read||n.is_read==='0'||n.is_read===0)?'unread':'';
                const isAction=unread&&n.action_required&&n.action_required!='0';
                // borrow_request pending → warning (orange); borrow_request done → success (green); others → by severity
                const sc=n.alert_type==='borrow_request'
                    ?(isAction?'warning':'success')
                    :(sevClass[n.severity]||'info');
                const sl=n.alert_type==='borrow_request'
                    ?(isAction?(lang==='th'?'รออนุมัติ':'Pending'):(lang==='th'?'แจ้งเตือน':'Notification'))
                    :(sevLabel[n.severity]||n.severity);
                const link=getNotifLink(n);
                const actionBadge=isAction
                    ?`<span class="hdr-notif-action-tag"><i class="fas fa-bolt" style="font-size:8px;margin-right:3px"></i>${lang==='th'?'ต้องดำเนินการ':'Action Required'}</span>`
                    :'';
                return `<a href="${link}" class="hdr-notif-item ${unread}${isAction?' is-action':''}" data-id="${n.id}" onclick="notifClick(event,${n.id})">
                    <div class="hdr-notif-icon ${sc}"><i class="fas ${ic}"></i></div>
                    <div class="hdr-notif-body">
                        <div class="hdr-notif-title">${escH(n.title||n.alert_type)}${actionBadge}</div>
                        <div class="hdr-notif-msg">${escH(n.message||'')}</div>
                        <div class="hdr-notif-meta">
                            <span class="hdr-notif-time" title="${absTime(n.created_at)}"><i class="far fa-clock" style="margin-right:2px"></i>${timeAgo(n.created_at)}</span>
                            <span class="hdr-notif-type-tag ${sc}">${sl}</span>
                        </div>
                    </div>
                    <div class="hdr-notif-actions">
                        ${unread?`<button class="hdr-notif-act snooze" title="${lang==='th'?'อ่านแล้ว':'Mark Read'}" onclick="notifMarkRead(event,${n.id})"><i class="fas fa-check"></i></button>`:''}
                        <button class="hdr-notif-act dismiss" title="${lang==='th'?'ปิดการแจ้งเตือน':'Dismiss'}" onclick="notifDismiss(event,${n.id})"><i class="fas fa-times"></i></button>
                    </div>
                </a>`;
            }).join('');
        }

        function escH(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

        function updateBadge(count){
            if(count>0){
                nBadge.textContent=count>99?'99+':count;
                nBadge.classList.add('show','pulse');
                nBtn.classList.add('has-unread');
                nCount.textContent=count;nCount.style.display='inline';
                nMarkAll.disabled=false;
            } else {
                nBadge.classList.remove('show','pulse');
                nBtn.classList.remove('has-unread');
                nCount.style.display='none';
                nMarkAll.disabled=true;
            }
        }

        async function loadNotifs(){
            try{
                const scope=bellIsAdmin?'scope=all&':'';
                const d=await apiFetch('/v1/api/alerts.php?'+scope+'dismissed=false&per_page=30&_t='+Date.now());
                if(d.success){
                    const inner=d.data||{};
                    let items=inner.data||[];
                    // Sort: action_required+unread first → unread → by time
                    items.sort((a,b)=>{
                        const aA=(a.action_required&&a.action_required!='0'&&isUnread(a))?1:0;
                        const bA=(b.action_required&&b.action_required!='0'&&isUnread(b))?1:0;
                        if(aA!==bA) return bA-aA;
                        const aU=isUnread(a)?1:0,bU=isUnread(b)?1:0;
                        if(aU!==bU) return bU-aU;
                        return new Date(b.created_at)-new Date(a.created_at);
                    });
                    notifData=items.slice(0,20);
                    const unread=notifData.filter(n=>isUnread(n)).length;
                    // For admin scope=all, show unread count from own alerts only (via unread_count field)
                    const badgeCount=bellIsAdmin?unread:((inner.unread_count!==undefined)?parseInt(inner.unread_count):unread);
                    updateBadge(badgeCount);
                    renderNotifs(notifData);
                    const mb=document.getElementById('mobAlertBadge');
                    if(mb){if(badgeCount>0){mb.textContent=badgeCount;mb.style.display='flex'}else{mb.style.display='none'}}
                }
            }catch(e){console.error('Notif load error',e);}
        }

        function isUnread(n){return !n.is_read||n.is_read==='0'||n.is_read===0;}

        async function _markReadApi(ids){
            const r=await apiFetch('/v1/api/alerts.php',{method:'POST',body:JSON.stringify({action:'mark_read',alert_ids:ids})});
            if(!r.success) throw new Error(r.error||'mark_read failed');
        }

        window.notifClick=async function(e,id){
            e.preventDefault();
            const item=notifData.find(n=>n.id==id);
            if(item&&isUnread(item)){
                try{
                    await _markReadApi([id]);
                    item.is_read=1;
                    const unread=notifData.filter(n=>isUnread(n)).length;
                    updateBadge(unread);
                    const el=nList.querySelector(`[data-id="${id}"]`);
                    if(el) el.classList.remove('unread');
                }catch(e){console.warn('mark_read error',e);}
            }
            nPanel.classList.remove('show');
            const target=e.currentTarget||e.target?.closest('.hdr-notif-item');
            const link=target?target.getAttribute('href'):null;
            if(link) window.location.href=link;
        };

        window.notifDismiss=async function(e,id){
            e.preventDefault();e.stopPropagation();
            const el=nList.querySelector(`[data-id="${id}"]`);
            if(el){el.style.transition='all .25s';el.style.opacity='0';el.style.maxHeight=el.offsetHeight+'px';setTimeout(()=>{el.style.maxHeight='0';el.style.padding='0 16px';el.style.borderBottom='none'},50)}
            try{
                const r=await apiFetch('/v1/api/alerts.php',{method:'POST',body:JSON.stringify({action:'dismiss',alert_ids:[id]})});
                if(!r.success) throw new Error(r.error||'dismiss failed');
                notifData=notifData.filter(n=>n.id!=id);
                const unread=notifData.filter(n=>isUnread(n)).length;
                updateBadge(unread);
                setTimeout(()=>renderNotifs(notifData),300);
            }catch(err){
                console.warn('dismiss error',err);
                if(el){el.style.opacity='1';el.style.maxHeight='';el.style.padding=''}
            }
        };

        window.notifMarkRead=async function(e,id){
            e.preventDefault();e.stopPropagation();
            const el=nList.querySelector(`[data-id="${id}"]`);
            try{
                await _markReadApi([id]);
                const item=notifData.find(n=>n.id==id);
                if(item) item.is_read=1;
                if(el){
                    el.classList.remove('unread');
                    el.querySelector('.hdr-notif-act.snooze')?.remove();
                }
                updateBadge(notifData.filter(n=>isUnread(n)).length);
            }catch(err){console.warn('mark_read error',err);}
        };

        nMarkAll.addEventListener('click',async()=>{
            const unreadIds=notifData.filter(n=>isUnread(n)).map(n=>n.id);
            if(!unreadIds.length)return;
            nMarkAll.disabled=true;nMarkAll.textContent=lang==='th'?'กำลังอ่าน...':'Reading...';
            try{
                await _markReadApi(unreadIds);
                notifData.forEach(n=>n.is_read=1);updateBadge(0);renderNotifs(notifData);
            }catch(err){console.warn('mark_all_read error',err);}
            nMarkAll.disabled=false;nMarkAll.textContent=lang==='th'?'อ่านทั้งหมด':'Mark all read';
        });

        nBtn.addEventListener('click',e=>{
            e.stopPropagation();
            document.getElementById('profileDropdown')?.classList.remove('show');
            document.getElementById('profileToggle')?.classList.remove('open');
            nPanel.classList.toggle('show');
        });
        document.addEventListener('click',e=>{if(!nBtn.contains(e.target)&&!nPanel.contains(e.target))nPanel.classList.remove('show')});
        document.addEventListener('keydown',e=>{if(e.key==='Escape')nPanel.classList.remove('show')});

        loadNotifs();
        setInterval(loadNotifs,60000);
    })();
    /* Close sidebar on nav item click (mobile) */
    document.querySelectorAll('.sb-nav a.sb-item').forEach(a=>a.addEventListener('click',()=>{if(window.innerWidth<=768){sidebar.classList.remove('open');overlay.classList.remove('show')}}));
    function toggleSub(el){el.classList.toggle('expanded');const s=el.nextElementSibling;if(s&&s.classList.contains('sb-sub'))s.classList.toggle('show')}
    async function apiFetch(url,options={}){const t=document.cookie.split('; ').find(c=>c.startsWith('auth_token='))?.split('=')[1];const h={'Content-Type':'application/json',...(options.headers||{})};if(t)h['Authorization']='Bearer '+t;const r=await fetch(url,{...options,headers:h});if(!r.ok&&r.status===401){window.location.href='/v1/';throw new Error('Unauthorized')}return r.json()}
    function formatDate(s){if(!s)return'-';const l='<?php echo I18n::getCurrentLang(); ?>';return new Date(s).toLocaleDateString(l==='th'?'th-TH':'en-US',{year:'numeric',month:'short',day:'numeric'})}
    function statusBadge(status){const m={'active':['ci-badge-success','<?php echo __("status_active"); ?>'],'inactive':['ci-badge-default','<?php echo __("status_inactive"); ?>'],'expired':['ci-badge-danger','<?php echo __("status_expired"); ?>'],'empty':['ci-badge-warning','<?php echo __("status_empty"); ?>'],'quarantined':['ci-badge-danger','<?php echo __("status_quarantined"); ?>'],'pending':['ci-badge-warning','<?php echo __("status_pending"); ?>'],'approved':['ci-badge-success','<?php echo __("status_approved"); ?>'],'rejected':['ci-badge-danger','<?php echo __("status_rejected"); ?>'],'returned':['ci-badge-info','<?php echo __("status_returned"); ?>'],'fulfilled':['ci-badge-success','Fulfilled'],'overdue':['ci-badge-danger','Overdue']};const[c,lb]=m[status]||['ci-badge-default',status];return`<span class="ci-badge ${c}">${lb}</span>`}
    </script>
        <?php
    }
    
    public static function pageHeader(string $title, string $icon = '', string $subtitle = '', string $actions = ''): void { ?>
        <div class="ci-pg-hdr">
            <div>
                <h1 class="ci-pg-title"><?php if($icon):?><i class="<?php echo $icon;?>"></i><?php endif;?> <?php echo htmlspecialchars($title);?></h1>
                <?php if($subtitle):?><p class="ci-pg-sub"><?php echo htmlspecialchars($subtitle);?></p><?php endif;?>
            </div>
            <?php if($actions):?><div class="ci-pg-actions"><?php echo $actions;?></div><?php endif;?>
        </div>
        <?php
    }
    
    public static function statCard(string $label, string $value, string $icon, string $colorClass = 'green', string $change = ''): void { ?>
        <div class="ci-stat">
            <div class="ci-stat-icon <?php echo $colorClass;?>"><i class="<?php echo $icon;?>"></i></div>
            <div>
                <div class="ci-stat-val"><?php echo $value;?></div>
                <div class="ci-stat-lbl"><?php echo $label;?></div>
            </div>
        </div>
        <?php
    }

    public static function footer(): void { ?>
    </body>
</html>
<?php
    }
}
