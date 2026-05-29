<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::getCurrentUser();
if (!$user || ($user['role_name'] ?? '') !== 'admin') {
    header('Location: /v1/'); exit;
}

$PAGE_CATALOG = [
    [
        'section' => 'main',
        'label'   => 'หน้าหลัก',
        'icon'    => 'fas fa-home',
        'bg'      => '#dbeafe',
        'color'   => '#2563eb',
        'pages'   => [
            ['key' => 'dashboard',  'label' => 'แดชบอร์ด',        'icon' => 'fas fa-atom',      'desc' => 'หน้าแรกและ ChemBot AI'],
            ['key' => 'stock',      'label' => 'คลังสารเคมี',      'icon' => 'fas fa-flask',        'desc' => 'จัดการสต็อกสารเคมีทั้งหมด'],
            ['key' => 'myroom',     'label' => 'ห้องของฉัน',        'icon' => 'fas fa-door-open',    'desc' => 'จัดการสารเคมีในห้องที่รับผิดชอบ'],
            ['key' => 'chemicals',  'label' => 'ข้อมูลสารเคมี',    'icon' => 'fas fa-search',       'desc' => 'ค้นหาและดูข้อมูลสารเคมี'],
            ['key' => 'locations',  'label' => 'สถานที่จัดเก็บ',   'icon' => 'fas fa-sitemap',   'desc' => 'อาคาร ห้อง ตู้ ชั้น สล็อต'],
            ['key' => 'lab-stores', 'label' => 'ฝ่ายปฏิบัติการ',  'icon' => 'fas fa-store',     'desc' => 'ร้านค้าและฝ่ายปฏิบัติการ'],
            ['key' => 'qr-scanner', 'label' => 'QR Scanner',       'icon' => 'fas fa-qrcode',    'desc' => 'สแกน QR Code สารเคมี'],
            ['key' => 'reports',    'label' => 'รายงาน',           'icon' => 'fas fa-chart-bar', 'desc' => 'รายงานและสถิติการใช้งาน'],
            ['key' => 'profile',    'label' => 'โปรไฟล์',          'icon' => 'fas fa-user',      'desc' => 'ข้อมูลส่วนตัวและการตั้งค่า'],
        ],
    ],
    [
        'section' => 'transactions',
        'label'   => 'ธุรกรรม',
        'icon'    => 'fas fa-exchange-alt',
        'bg'      => '#d1fae5',
        'color'   => '#059669',
        'pages'   => [
            ['key' => 'borrow',   'label' => 'ยืม-คืนสารเคมี',  'icon' => 'fas fa-exchange-alt', 'desc' => 'รายการขอยืมและคืนสารเคมี'],
            ['key' => 'activity', 'label' => 'ประวัติกิจกรรม',  'icon' => 'fas fa-history',      'desc' => 'บันทึกกิจกรรมทั้งหมด'],
            ['key' => 'disposal', 'label' => 'ถังจำหน่าย',      'icon' => 'fas fa-trash-alt',    'desc' => 'สารเคมีที่ถูกจำหน่ายออกจากระบบ'],
        ],
    ],
    [
        'section' => 'admin',
        'label'   => 'การจัดการ',
        'icon'    => 'fas fa-cogs',
        'bg'      => '#f3e8ff',
        'color'   => '#7c3aed',
        'pages'   => [
            ['key' => 'alerts',         'label' => 'ศูนย์แจ้งเตือน',    'icon' => 'fas fa-bell',            'desc' => 'การแจ้งเตือนและการเตือนภัย'],
            ['key' => 'users',          'label' => 'จัดการผู้ใช้',       'icon' => 'fas fa-users',           'desc' => 'เพิ่ม แก้ไข จัดการผู้ใช้งาน'],
            ['key' => 'user-chemicals', 'label' => 'สารเคมีรายบุคคล',   'icon' => 'fas fa-user-shield',     'desc' => 'สารเคมีที่กำหนดให้ผู้ใช้แต่ละคน'],
            ['key' => 'models3d',       'label' => 'โมเดล 3D',           'icon' => 'fas fa-cube',            'desc' => 'ไลบรารีโมเดล 3D'],
            ['key' => 'warehouses',     'label' => 'คลังสินค้า',         'icon' => 'fas fa-warehouse',       'desc' => 'จัดการคลังสารเคมี'],
            ['key' => 'containers',     'label' => 'ภาชนะบรรจุ',        'icon' => 'fas fa-box-open',        'desc' => 'จัดการภาชนะและบรรจุภัณฑ์'],
            ['key' => 'cas-map',        'label' => 'CAS Map',            'icon' => 'fas fa-project-diagram', 'desc' => 'แผนที่ CAS Number ↔ โมเดล 3D'],
            ['key' => 'settings',       'label' => 'ตั้งค่าระบบ',        'icon' => 'fas fa-gear',            'desc' => 'การตั้งค่าและการกำหนดค่าระบบ'],
            ['key' => 'system-monitor', 'label' => 'ตรวจสอบระบบ',       'icon' => 'fas fa-display',         'desc' => 'สถานะและประสิทธิภาพระบบ'],
            ['key' => 'ai-assistant',   'label' => 'AI Assistant',        'icon' => 'fas fa-robot',           'desc' => 'ผู้ช่วย AI และการวิเคราะห์'],
            ['key' => 'page-access',    'label' => 'Page Access Control','icon' => 'fas fa-shield-alt',      'desc' => 'จัดการสิทธิ์เข้าถึงหน้าต่างๆ'],
        ],
    ],
];
$totalPages = array_sum(array_map(fn($s) => count($s['pages']), $PAGE_CATALOG));

/* ─── Role default presets ─── */
$ROLE_DEFAULTS = [
    'visitor' => [
        'label' => 'Visitor — ผู้เยี่ยมชม',
        'desc'  => 'เข้าถึงหน้าพื้นฐานเท่านั้น เหมาะสำหรับผู้เยี่ยมชมหรือนักศึกษาภายนอก',
        'icon'  => 'fas fa-eye',
        'color' => '#64748b',
        'bg'    => '#f1f5f9',
        'pages' => ['dashboard','chemicals','profile'],
    ],
    'user' => [
        'label' => 'User — ผู้ใช้ทั่วไป',
        'desc'  => 'สิทธิ์มาตรฐานสำหรับนักวิจัยหรือนักศึกษาที่ทำงานในห้องปฏิบัติการ',
        'icon'  => 'fas fa-user',
        'color' => '#059669',
        'bg'    => '#d1fae5',
        'pages' => ['dashboard','stock','myroom','chemicals','locations','qr-scanner','profile','borrow','activity'],
    ],
    'lab_manager' => [
        'label' => 'Lab Manager — ผู้จัดการห้องปฏิบัติการ',
        'desc'  => 'สิทธิ์ครบถ้วนสำหรับจัดการและดำเนินงานห้องปฏิบัติการทั้งหมด',
        'icon'  => 'fas fa-user-tie',
        'color' => '#d97706',
        'bg'    => '#fef3c7',
        'pages' => ['dashboard','stock','myroom','chemicals','locations','lab-stores','qr-scanner','reports','profile',
                    'borrow','activity','disposal',
                    'alerts','containers','warehouses','user-chemicals'],
    ],
    'ceo' => [
        'label' => 'CEO — ผู้บริหาร',
        'desc'  => 'ภาพรวมระบบและรายงานสำหรับผู้บริหาร เน้นการติดตาม วิเคราะห์ และสรุปข้อมูล',
        'icon'  => 'fas fa-star',
        'color' => '#2563eb',
        'bg'    => '#dbeafe',
        'pages' => ['dashboard','stock','chemicals','locations','reports','profile',
                    'activity',
                    'alerts','system-monitor','ai-assistant'],
    ],
];

Layout::head('Page Access Control — สิทธิ์หน้า');
Layout::sidebar('page-access');
Layout::beginContent();
?>
<style>
:root{--pa:#e11d48;--pa-l:#ffe4e6;--pa-d:#be123c;--pa-r:14px;--pa-rs:10px;--pa-sh:0 1px 4px rgba(0,0,0,.06);--pa-shm:0 4px 20px rgba(0,0,0,.1)}
/* ── Hero ── */
.pa-hero{background:linear-gradient(135deg,#1a0510 0%,#7f1d1d 55%,#e11d48 100%);border-radius:var(--pa-r);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden}
.pa-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.pa-hero-ic{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.18);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;position:relative}
.pa-hero-info{position:relative}
.pa-hero-info h2{font-size:20px;font-weight:800;margin:0 0 3px}
.pa-hero-info p{font-size:12px;opacity:.75;margin:0}
.pa-hero-meta{margin-left:auto;display:flex;gap:20px;flex-shrink:0;position:relative}
.pa-hero-c{text-align:center}
.pa-hero-c .v{font-size:26px;font-weight:900;line-height:1}
.pa-hero-c .lb{font-size:10px;opacity:.7;margin-top:3px;text-transform:uppercase;letter-spacing:.5px}
.pa-hero-sep{width:1px;background:rgba(255,255,255,.2)}
/* ── Stats ── */
.pa-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:18px}
.pa-stat{background:#fff;border-radius:var(--pa-rs);padding:14px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--pa-sh);border:1.5px solid var(--border);cursor:pointer;transition:all .18s;position:relative;overflow:hidden}
.pa-stat::before{content:'';position:absolute;top:0;right:0;width:52px;height:52px;border-radius:0 var(--pa-rs) 0 52px;opacity:.05;background:currentColor;pointer-events:none}
.pa-stat::after{content:'';position:absolute;inset:0;background:currentColor;opacity:0;transition:opacity .18s;pointer-events:none}
.pa-stat:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.1)}
.pa-stat:active{transform:translateY(-1px);box-shadow:var(--pa-sh)}
.pa-stat-arrow{margin-left:auto;font-size:10px;color:var(--c3);opacity:0;transition:opacity .18s;flex-shrink:0}
.pa-stat:hover .pa-stat-arrow{opacity:1}
.pa-si{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.pa-sv{font-size:20px;font-weight:800;color:var(--c1);line-height:1}
.pa-sl{font-size:10px;color:var(--c3);margin-top:2px;text-transform:uppercase;letter-spacing:.3px}
/* ── Layout ── */
.pa-main{display:grid;grid-template-columns:260px 1fr;gap:16px;align-items:start}
/* ── Role panel ── */
.pa-role-panel{background:#fff;border-radius:var(--pa-r);box-shadow:var(--pa-sh);border:1.5px solid var(--border);overflow:hidden;position:sticky;top:16px}
.pa-role-hdr{padding:12px 16px;border-bottom:1px solid var(--border);font-size:11px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:8px}
.pa-role-list{padding:8px;display:flex;flex-direction:column;gap:4px}
.pa-role-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;cursor:pointer;transition:all .15s;border:1.5px solid transparent;border-left:3px solid transparent}
.pa-role-card:hover{background:var(--bg);border-left-color:var(--border)}
.pa-role-card:active{transform:scale(.99)}
.pa-role-card.active{background:var(--pa-l);border-color:var(--pa);border-left-color:var(--pa)}
.pa-role-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.pa-role-name{font-size:13px;font-weight:600;color:var(--c1)}
.pa-role-sub{font-size:10px;color:var(--c3);margin-top:1px}
.pa-role-badge{margin-left:auto;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;flex-shrink:0}
.pa-role-check{font-size:11px;color:#22c55e;flex-shrink:0;display:none;margin-right:4px}
.pa-role-card.has-custom .pa-role-check{display:inline}
/* ── Pages panel ── */
.pa-pages-panel{display:flex;flex-direction:column;gap:12px}
.pa-pages-empty{background:#fff;border-radius:var(--pa-r);border:1.5px solid var(--border);box-shadow:var(--pa-sh);padding:60px;text-align:center;color:var(--c3)}
.pa-pages-empty i{font-size:36px;opacity:.3;display:block;margin-bottom:12px}
.pa-pages-empty p{font-size:13px;margin:0}
/* Role header row */
.pa-role-row{display:flex;align-items:center;gap:10px;background:#fff;border-radius:var(--pa-rs);padding:12px 16px;border:1.5px solid var(--border);box-shadow:var(--pa-sh)}
.pa-role-row-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.pa-role-row-name{font-size:14px;font-weight:700;color:var(--c1)}
.pa-role-row-sub{font-size:11px;color:var(--c3);margin-top:1px}
/* ── Section card ── */
.pa-section{background:#fff;border-radius:var(--pa-r);box-shadow:var(--pa-sh);border:1.5px solid var(--border);overflow:hidden;transition:border-color .2s,box-shadow .2s}
.pa-section.has-dirty{border-color:#fcd34d;box-shadow:0 0 0 3px rgba(252,211,77,.18),var(--pa-sh)}
.pa-section-hdr{display:flex;align-items:center;gap:8px;padding:11px 14px;border-bottom:1px solid var(--border);background:linear-gradient(to bottom,#fafafa,#f5f7fa);flex-wrap:wrap;row-gap:6px}
.pa-section-ic{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.pa-section-title{font-size:13px;font-weight:700;color:var(--c1)}
.pa-section-count{font-size:11px;color:var(--c3);background:var(--bg);padding:2px 8px;border-radius:10px}
/* Dirty bar: change count + section actions */
.pa-sec-actions{margin-left:auto;display:flex;align-items:center;gap:6px}
.pa-chg-pill{font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px;background:#fef3c7;color:#d97706;border:1px solid #fcd34d;display:none;align-items:center;gap:4px}
.pa-chg-pill.show{display:flex}
.pa-sec-save-btn{font-size:11px;font-weight:700;padding:5px 12px;border-radius:7px;border:none;background:var(--pa);color:#fff;cursor:pointer;font-family:inherit;display:none;align-items:center;gap:4px;transition:background .15s}
.pa-sec-save-btn.show{display:flex}
.pa-sec-save-btn:hover{background:var(--pa-d)}
.pa-sec-save-btn:disabled{opacity:.65;cursor:not-allowed}
.pa-sec-reset-btn{font-size:10px;font-weight:600;padding:5px 10px;border-radius:7px;border:1.5px solid var(--border);background:#fff;color:var(--c2);cursor:pointer;font-family:inherit;display:none;transition:all .15s}
.pa-sec-reset-btn.show{display:block}
.pa-sec-reset-btn:hover{border-color:#94a3b8;color:var(--c1)}
/* Bulk buttons */
.pa-bulk-btns{display:flex;gap:5px}
.pa-bulk-btn{font-size:10px;font-weight:600;padding:4px 9px;border-radius:6px;border:1.5px solid var(--border);background:#fff;color:var(--c2);cursor:pointer;transition:all .15s;font-family:inherit}
.pa-bulk-btn:hover{border-color:var(--pa);color:var(--pa);background:var(--pa-l)}
/* ── Page grid ── */
.pa-page-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:0;background:var(--border)}
.pa-page-item{display:flex;align-items:center;gap:12px;padding:11px 16px;background:#fff;transition:background .12s,box-shadow .12s;cursor:pointer;position:relative}
.pa-page-item:hover{background:#f8faff}
.pa-page-item:hover .pa-page-ic{transform:scale(1.08)}
.pa-page-item:active{background:#f1f5ff}
.pa-page-item.locked{opacity:.65;pointer-events:none}
.pa-page-ic{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;transition:transform .15s,background .15s,color .15s}
.pa-page-name{font-size:12px;font-weight:600;color:var(--c1)}
.pa-page-desc{font-size:10px;color:var(--c3);margin-top:2px}
.pa-page-info{flex:1;min-width:0}
/* ── Toggle switch ── */
.pa-switch{position:relative;flex-shrink:0}
.pa-switch input{position:absolute;opacity:0;width:0;height:0}
.pa-track{width:38px;height:22px;background:#e2e8f0;border-radius:22px;display:flex;align-items:center;padding:2px;transition:background .2s;cursor:pointer}
.pa-track:hover{background:#cbd5e1}
.pa-thumb{width:18px;height:18px;background:#fff;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform .2s;flex-shrink:0}
.pa-switch input:checked+.pa-track{background:var(--pa)}
.pa-switch input:checked+.pa-track:hover{background:var(--pa-d)}
.pa-switch input:checked+.pa-track .pa-thumb{transform:translateX(16px)}
.pa-lock-ic{font-size:14px;color:#94a3b8;flex-shrink:0}
/* ── Admin notice ── */
.pa-admin-notice{background:#fff;border-radius:var(--pa-r);border:1.5px solid var(--border);box-shadow:var(--pa-sh);padding:20px;display:flex;align-items:center;gap:16px}
.pa-admin-notice-ic{width:48px;height:48px;border-radius:12px;background:#ffe4e6;color:#e11d48;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
/* ── Toast ── */
.pa-toast{position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:11px 18px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,.2);z-index:9999;display:flex;align-items:center;gap:8px;transform:translateY(80px);opacity:0;transition:transform .25s,opacity .25s;max-width:320px}
.pa-toast.show{transform:none;opacity:1}
.pa-toast.ok{background:#064e3b}
.pa-toast.err{background:#7f1d1d}
/* ── Spinner ── */
.pa-spin{text-align:center;padding:48px;color:var(--c3)}
.pa-spin i{font-size:24px;opacity:.4;display:block;margin-bottom:10px}
.pa-spin p{font-size:12px;margin:0}

/* ════════════════════════════════════════════════════════
   CONFIRMATION MODAL
════════════════════════════════════════════════════════ */
.pa-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:2000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
.pa-overlay.show{opacity:1;pointer-events:auto}
.pa-modal{background:#fff;border-radius:18px;width:500px;max-width:92vw;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 24px 64px rgba(0,0,0,.25);transform:translateY(20px) scale(.97);transition:transform .22s}
.pa-overlay.show .pa-modal{transform:none}
/* Modal header */
.pa-modal-hdr{display:flex;align-items:center;gap:14px;padding:20px 22px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.pa-modal-hdr-ic{width:44px;height:44px;border-radius:12px;background:var(--pa-l);color:var(--pa);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.pa-modal-title{font-size:15px;font-weight:800;color:var(--c1);margin:0 0 2px}
.pa-modal-sub{font-size:12px;color:var(--c3)}
.pa-modal-close{margin-left:auto;width:30px;height:30px;border-radius:8px;border:none;background:var(--bg);color:var(--c2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.pa-modal-close:hover{background:#e2e8f0;color:var(--c1)}
/* Modal body */
.pa-modal-body{padding:18px 22px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:12px}
.pc-group{border-radius:10px;overflow:hidden;border:1.5px solid}
.pc-group.pc-added{border-color:#bbf7d0}
.pc-group.pc-removed{border-color:#fecaca}
.pc-group.pc-unchanged{border-color:#e2e8f0}
.pc-group-hdr{display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:12px;font-weight:700}
.pc-added .pc-group-hdr{background:#f0fdf4;color:#15803d}
.pc-removed .pc-group-hdr{background:#fef2f2;color:#dc2626}
.pc-unchanged .pc-group-hdr{background:#f8fafc;color:var(--c3)}
.pc-items{padding:4px 0}
.pc-item{display:flex;align-items:center;gap:10px;padding:7px 14px;font-size:12px;color:var(--c2);border-top:1px solid rgba(0,0,0,.04)}
.pc-item i{width:16px;text-align:center;flex-shrink:0}
.pc-item-name{font-weight:600;color:var(--c1)}
.pc-item-desc{color:var(--c3);font-size:10px;margin-left:2px}
.pc-no-change{padding:12px 14px;font-size:12px;color:var(--c3);text-align:center}
/* Modal footer */
.pa-modal-foot{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 22px 18px;border-top:1px solid var(--border);flex-shrink:0}
.pa-modal-summary{font-size:12px;color:var(--c3);margin-right:auto}
.pa-modal-summary strong{color:var(--c1)}
.pa-btn-cancel{background:none;border:1.5px solid var(--border);color:var(--c2);padding:8px 18px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s}
.pa-btn-cancel:hover{border-color:#94a3b8;color:var(--c1)}
.pa-btn-confirm{background:var(--pa);color:#fff;border:none;padding:9px 22px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:7px;transition:background .15s}
.pa-btn-confirm:hover{background:var(--pa-d)}
.pa-btn-confirm:disabled{opacity:.65;cursor:not-allowed}

/* ── Role card default button ── */
.pa-def-btn{font-size:10px;font-weight:700;padding:4px 9px;border-radius:6px;border:1.5px solid #bfdbfe;background:#eff6ff;color:#2563eb;cursor:pointer;display:flex;align-items:center;gap:4px;transition:all .18s;font-family:inherit;white-space:nowrap;opacity:0;flex-shrink:0;line-height:1}
.pa-role-card:hover .pa-def-btn,.pa-role-card.active .pa-def-btn{opacity:1}
.pa-def-btn:hover{background:#2563eb;color:#fff;border-color:#2563eb;transform:scale(1.04)}
/* ── Default preset modal ── */
.pa-def-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:2200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
.pa-def-overlay.show{opacity:1;pointer-events:auto}
.pa-def-modal{background:#fff;border-radius:18px;width:580px;max-width:94vw;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 24px 64px rgba(0,0,0,.25);transform:translateY(20px) scale(.97);transition:transform .22s}
.pa-def-overlay.show .pa-def-modal{transform:none}
.pa-def-hdr{display:flex;align-items:center;gap:14px;padding:20px 22px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.pa-def-hdr-ic{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.pa-def-title{font-size:15px;font-weight:800;color:var(--c1);margin:0 0 3px}
.pa-def-sub{font-size:12px;color:var(--c3)}
.pa-def-close{margin-left:auto;width:30px;height:30px;border-radius:8px;border:none;background:var(--bg);color:var(--c2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.pa-def-close:hover{background:#e2e8f0;color:var(--c1)}
.pa-def-body{padding:18px 22px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:10px}
.pa-def-foot{display:flex;align-items:center;gap:10px;padding:14px 22px 18px;border-top:1px solid var(--border);flex-shrink:0}
.pa-def-summary{font-size:12px;color:var(--c3);margin-right:auto}
.pa-def-cancel{background:none;border:1.5px solid var(--border);color:var(--c2);padding:8px 18px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s}
.pa-def-cancel:hover{border-color:#94a3b8;color:var(--c1)}
.pa-def-confirm{background:#2563eb;color:#fff;border:none;padding:9px 22px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:7px;transition:background .15s}
.pa-def-confirm:hover{background:#1d4ed8}
.pa-def-confirm:disabled{opacity:.65;cursor:not-allowed}
/* Preset info card */
.pa-def-preset-card{border-radius:12px;padding:14px 16px;display:flex;gap:14px;align-items:center}
.pa-def-preset-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
/* Section group inside def modal */
.pa-def-sec-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:6px 14px 4px;opacity:.75}
.pa-def-warn{background:#fef3c7;border:1.5px solid #fcd34d;border-radius:10px;padding:10px 14px;font-size:12px;color:#92400e;display:flex;gap:10px;align-items:flex-start}

/* ════════════════════════════════════════════════════════
   STAT DETAIL MODAL
════════════════════════════════════════════════════════ */
.pa-sm-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
.pa-sm-overlay.show{opacity:1;pointer-events:auto}
.pa-sm-modal{background:#fff;border-radius:18px;width:580px;max-width:94vw;max-height:86vh;display:flex;flex-direction:column;box-shadow:0 24px 64px rgba(0,0,0,.25);transform:translateY(20px) scale(.97);transition:transform .22s}
.pa-sm-overlay.show .pa-sm-modal{transform:none}
.pa-sm-hdr{display:flex;align-items:center;gap:14px;padding:20px 22px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.pa-sm-hdr-ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.pa-sm-title{font-size:15px;font-weight:800;color:var(--c1);margin:0 0 2px}
.pa-sm-sub{font-size:12px;color:var(--c3)}
.pa-sm-close{margin-left:auto;width:30px;height:30px;border-radius:8px;border:none;background:var(--bg);color:var(--c2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.pa-sm-close:hover{background:#e2e8f0;color:var(--c1)}
.pa-sm-body{padding:18px 22px;overflow-y:auto;flex:1}
.pa-sm-foot{padding:12px 22px 16px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:flex-end;flex-shrink:0}
.pa-sm-done{background:var(--pa);color:#fff;border:none;padding:9px 22px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.pa-sm-done:hover{background:var(--pa-d)}
/* Role detail rows */
.psd-role-row{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:8px;cursor:pointer;transition:all .15s}
.psd-role-row:hover{border-color:var(--pa);background:var(--pa-l)}
.psd-role-ic{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.psd-pct-bar{height:4px;background:#e2e8f0;border-radius:4px;margin-top:6px;overflow:hidden}
.psd-pct-fill{height:100%;border-radius:4px}
.psd-badge{font-size:10px;font-weight:700;padding:3px 9px;border-radius:20px;flex-shrink:0;white-space:nowrap}
/* Page detail rows */
.psd-sec-hdr{display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px 10px 0 0;font-size:12px;font-weight:700}
.psd-sec-body{border:1.5px solid var(--border);border-top:none;border-radius:0 0 10px 10px;overflow:hidden;margin-bottom:10px}
.psd-page-row{display:flex;align-items:center;gap:10px;padding:8px 14px;border-top:1px solid var(--border);font-size:12px;background:#fff}
.psd-page-row:first-child{border-top:none}
.psd-page-ic{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
/* Section label in stat modal */
.psd-label{font-size:11px;font-weight:700;color:var(--c3);text-transform:uppercase;letter-spacing:.5px;margin:14px 0 8px}
.psd-label:first-child{margin-top:0}

@media(max-width:820px){
    .pa-main{grid-template-columns:1fr}
    .pa-role-panel{position:static}
    .pa-hero-meta{display:none}
    .pa-page-grid{grid-template-columns:1fr}
    .pa-role-list{display:flex;flex-direction:row;overflow-x:auto;gap:6px;padding:6px 8px;scrollbar-width:none}
    .pa-role-list::-webkit-scrollbar{display:none}
    .pa-role-card{flex-shrink:0;min-width:140px}
    .pa-stats{grid-template-columns:repeat(2,1fr)}
    .pa-sec-actions{width:100%;margin-left:0;justify-content:flex-end}
}
</style>

<!-- ── Hero ────────────────────────────────── -->
<div class="pa-hero">
    <div class="pa-hero-ic"><i class="fas fa-shield-alt"></i></div>
    <div class="pa-hero-info">
        <h2>Page Access Control</h2>
        <p>กำหนดสิทธิ์การมองเห็นหน้าในระบบสำหรับแต่ละ Role — บันทึกแยกส่วนได้ พร้อมสรุปการเปลี่ยนแปลง</p>
    </div>
    <div class="pa-hero-meta">
        <div class="pa-hero-c"><div class="v" id="hmRoles">—</div><div class="lb">Roles</div></div>
        <div class="pa-hero-sep"></div>
        <div class="pa-hero-c"><div class="v"><?= $totalPages ?></div><div class="lb"><?= count($PAGE_CATALOG) ?> Groups · Pages</div></div>
        <div class="pa-hero-sep"></div>
        <div class="pa-hero-c"><div class="v" id="hmConfigured">—</div><div class="lb">Configured</div></div>
    </div>
</div>

<!-- ── Stats ─────────────────────────────── -->
<div class="pa-stats">
    <div class="pa-stat" onclick="showStatDetail('roles')" title="ดูรายละเอียด Roles ทั้งหมด" style="border-color:#fecdd3">
        <div class="pa-si" style="background:#ffe4e6;color:#e11d48"><i class="fas fa-shield-alt"></i></div>
        <div style="flex:1"><div class="pa-sv" id="stRoles">—</div><div class="pa-sl">Total Roles</div></div>
        <i class="fas fa-chevron-right pa-stat-arrow"></i>
    </div>
    <div class="pa-stat" onclick="showStatDetail('pages')" title="ดูรายละเอียดหน้าทั้งหมด" style="border-color:#bfdbfe">
        <div class="pa-si" style="background:#dbeafe;color:#2563eb"><i class="fas fa-file-alt"></i></div>
        <div style="flex:1"><div class="pa-sv"><?= $totalPages ?></div><div class="pa-sl">Total Pages &middot; <?= count($PAGE_CATALOG) ?> กลุ่ม</div></div>
        <i class="fas fa-chevron-right pa-stat-arrow"></i>
    </div>
    <div class="pa-stat" onclick="showStatDetail('configured')" title="ดู Role ที่กำหนดสิทธิ์แล้ว" style="border-color:#a7f3d0">
        <div class="pa-si" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div>
        <div style="flex:1"><div class="pa-sv" id="stConfigured">—</div><div class="pa-sl">Configured Roles</div></div>
        <i class="fas fa-chevron-right pa-stat-arrow"></i>
    </div>
    <div class="pa-stat" onclick="showStatDetail('admin')" title="ดูข้อมูล Admin Lock" style="border-color:#fde68a">
        <div class="pa-si" style="background:#fef3c7;color:#d97706"><i class="fas fa-crown"></i></div>
        <div style="flex:1"><div class="pa-sv" id="stAdminCount">1</div><div class="pa-sl">Admin Lock</div></div>
        <i class="fas fa-chevron-right pa-stat-arrow"></i>
    </div>
</div>

<!-- ── Main ──────────────────────────────── -->
<div class="pa-main">
    <!-- Left: role selector -->
    <div class="pa-role-panel">
        <div class="pa-role-hdr"><i class="fas fa-users-cog"></i>&ensp;เลือก Role</div>
        <div class="pa-role-list" id="paRoleList">
            <div class="pa-spin"><i class="fas fa-spinner fa-spin"></i><p>กำลังโหลด...</p></div>
        </div>
    </div>
    <!-- Right: permission editor -->
    <div id="paPagesPanelWrap" class="pa-pages-panel">
        <div class="pa-pages-empty">
            <i class="fas fa-hand-pointer"></i>
            <p>เลือก Role ทางซ้ายเพื่อกำหนดสิทธิ์การมองเห็นหน้า</p>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     CONFIRMATION MODAL
════════════════════════════════════════ -->
<div class="pa-overlay" id="paConfirmModal">
    <div class="pa-modal">
        <div class="pa-modal-hdr">
            <div class="pa-modal-hdr-ic"><i class="fas fa-shield-alt"></i></div>
            <div>
                <div class="pa-modal-title">ยืนยันการเปลี่ยนแปลงสิทธิ์</div>
                <div class="pa-modal-sub" id="pcSubtitle">—</div>
            </div>
            <button class="pa-modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="pa-modal-body" id="pcBody"></div>
        <div class="pa-modal-foot">
            <div class="pa-modal-summary" id="pcSummary"></div>
            <button class="pa-btn-cancel" onclick="closeModal()"><i class="fas fa-times" style="margin-right:5px"></i>ยกเลิก</button>
            <button class="pa-btn-confirm" id="pcConfirmBtn" onclick="doSave()">
                <i class="fas fa-check"></i>ยืนยันการบันทึก
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     DEFAULT PRESET MODAL
════════════════════════════════════════ -->
<div class="pa-def-overlay" id="paDefaultModal">
    <div class="pa-def-modal">
        <div class="pa-def-hdr">
            <div class="pa-def-hdr-ic" id="pdefHdrIc"></div>
            <div style="flex:1;min-width:0">
                <div class="pa-def-title" id="pdefTitle"></div>
                <div class="pa-def-sub" id="pdefSub"></div>
            </div>
            <button class="pa-def-close" onclick="closeDefaultModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="pa-def-body" id="pdefBody"></div>
        <div class="pa-def-foot">
            <div class="pa-def-summary" id="pdefSummary"></div>
            <button class="pa-def-cancel" onclick="closeDefaultModal()"><i class="fas fa-times" style="margin-right:5px"></i>ยกเลิก</button>
            <button class="pa-def-confirm" id="pdefConfirmBtn" onclick="doSaveDefault()">
                <i class="fas fa-sliders"></i>บันทึกค่าเริ่มต้น
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     STAT DETAIL MODAL
════════════════════════════════════════ -->
<div class="pa-sm-overlay" id="paStatModal">
    <div class="pa-sm-modal">
        <div class="pa-sm-hdr">
            <div class="pa-sm-hdr-ic" id="psmHdrIc"></div>
            <div>
                <div class="pa-sm-title" id="psmTitle"></div>
                <div class="pa-sm-sub" id="psmSub"></div>
            </div>
            <button class="pa-sm-close" onclick="closeStatModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="pa-sm-body" id="psmBody"></div>
        <div class="pa-sm-foot">
            <button class="pa-sm-done" onclick="closeStatModal()"><i class="fas fa-check" style="margin-right:6px"></i>ปิด</button>
        </div>
    </div>
</div>

<!-- ── Toast ─────────────────────────────── -->
<div class="pa-toast" id="paToast"></div>

<script>
const PA_API  = '/v1/api/page_permissions.php';
const CATALOG = <?= json_encode($PAGE_CATALOG, JSON_UNESCAPED_UNICODE) ?>;
const ROLE_DEFAULTS = <?= json_encode($ROLE_DEFAULTS, JSON_UNESCAPED_UNICODE) ?>;

/* Role visual config */
const RC = {
    5:{bg:'#ffe4e6',color:'#e11d48',icon:'fa-crown'},
    4:{bg:'#dbeafe',color:'#2563eb',icon:'fa-star'},
    3:{bg:'#fef3c7',color:'#d97706',icon:'fa-user-tie'},
    2:{bg:'#d1fae5',color:'#059669',icon:'fa-user'},
    1:{bg:'#f1f5f9',color:'#64748b',icon:'fa-eye'},
};

let allRoles          = [];
let allPermsData      = [];  // raw get_all data [{ role, permissions }]
let selectedRoleId    = null;
let currentPerms      = {};  // live UI state  { pageKey: bool }
let savedPerms        = {};  // last-committed DB state
let customRoles       = new Set();
let pendingSaveSection = null;
let pendingDefaultRole = null;

/* ── Utilities ─────────────────────────────────── */
function esc(s){ return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function showToast(msg, type='ok'){
    const t = document.getElementById('paToast');
    t.className='pa-toast '+type;
    t.innerHTML=`<i class="fas ${type==='ok'?'fa-check-circle':'fa-exclamation-circle'}"></i> ${msg}`;
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 3400);
}

async function apiFetch(url, opts={}){
    const tk=localStorage.getItem('auth_token')||'';
    const h={'Content-Type':'application/json'};
    if(tk) h['Authorization']='Bearer '+tk;
    const r=await fetch(url,{...opts,headers:{...h,...(opts.headers||{})}});
    if(!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
}

/* ── Init ──────────────────────────────────────── */
async function init(){
    try{
        const [rolesD,allD]=await Promise.all([
            apiFetch(PA_API+'?action=roles'),
            apiFetch(PA_API+'?action=get_all'),
        ]);
        if(!rolesD.success) throw new Error(rolesD.error);
        allRoles=rolesD.data;
        customRoles.clear();
        if(allD.success){
            allPermsData=allD.data;
            allD.data.forEach(x=>{
                if(Object.keys(x.permissions).length>0) customRoles.add(parseInt(x.role.id));
            });
        }
        const adminCount=allRoles.filter(r=>r.level>=5).length;
        document.getElementById('stRoles').textContent=allRoles.length;
        document.getElementById('hmRoles').textContent=allRoles.length;
        document.getElementById('stConfigured').textContent=customRoles.size;
        document.getElementById('hmConfigured').textContent=customRoles.size;
        document.getElementById('stAdminCount').textContent=adminCount;
        renderRoleList();
    }catch(e){ showToast('โหลดข้อมูลล้มเหลว: '+e.message,'err'); }
}

/* ── Role list ─────────────────────────────────── */
function renderRoleList(){
    const list=document.getElementById('paRoleList');
    list.innerHTML=allRoles.map(r=>{
        const c=RC[r.level]||RC[1];
        const isActive=selectedRoleId==r.id;
        const hasCust=customRoles.has(parseInt(r.id));
        const isAdmin=r.level>=5;
        const hasDef=!isAdmin&&!!ROLE_DEFAULTS[r.name];
        return `<div class="pa-role-card${isActive?' active':''}${hasCust?' has-custom':''}" onclick="selectRole(${r.id})">
            <div class="pa-role-ic" style="background:${c.bg};color:${c.color}"><i class="fas ${c.icon}"></i></div>
            <div style="flex:1;min-width:0">
                <div class="pa-role-name">${esc(r.display_name)}</div>
                <div class="pa-role-sub">${esc(r.name)} &middot; Lv.${r.level}</div>
            </div>
            ${hasDef?`<button class="pa-def-btn" onclick="showDefaultModal(${r.id},event)" title="โหลด Preset ค่าเริ่มต้น"><i class="fas fa-sliders"></i></button>`:''}
            ${hasCust?'<i class="pa-role-check fas fa-circle-check" title="มีการตั้งค่า"></i>':''}
            <div class="pa-role-badge" style="background:${c.bg};color:${c.color}">
                ${isAdmin?'<i class="fas fa-lock" style="margin-right:3px;font-size:9px"></i>':''}${r.user_count}&nbsp;คน
            </div>
        </div>`;
    }).join('');
}

/* ── Select role ───────────────────────────────── */
async function selectRole(roleId){
    // Warn if any section has unsaved changes
    const hasDirty=CATALOG.some(sec=>sec.pages.some(pg=>currentPerms[pg.key]!==savedPerms[pg.key]));
    if(hasDirty && selectedRoleId && !confirm('มีส่วนที่ยังไม่ได้บันทึก\nต้องการเปลี่ยน Role โดยไม่บันทึกหรือไม่?')) return;

    selectedRoleId=roleId;
    currentPerms={};
    savedPerms={};
    renderRoleList();

    const role=allRoles.find(r=>r.id==roleId);
    if(!role) return;

    const panel=document.getElementById('paPagesPanelWrap');
    panel.innerHTML='<div class="pa-spin"><i class="fas fa-spinner fa-spin"></i><p>กำลังโหลด...</p></div>';

    if(role.level>=5){ renderAdminView(panel,role); return; }

    try{
        const d=await apiFetch(PA_API+'?action=get_role&role_id='+roleId);
        if(!d.success) throw new Error(d.error);
        CATALOG.forEach(sec=>sec.pages.forEach(pg=>{
            currentPerms[pg.key]=d.has_custom ? !!(d.data[pg.key]) : true;
        }));
        savedPerms={...currentPerms};
        renderPagePanel(panel,role);
    }catch(e){
        panel.innerHTML=`<div class="pa-pages-empty"><i class="fas fa-exclamation-triangle"></i><p>${esc(e.message)}</p></div>`;
    }
}

/* ── Admin view (read-only) ─────────────────────── */
function renderAdminView(panel,role){
    const allItems=CATALOG.flatMap(sec=>sec.pages.map(pg=>({pg,sec})));
    panel.innerHTML=`
        <div class="pa-admin-notice">
            <div class="pa-admin-notice-ic"><i class="fas fa-crown"></i></div>
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--c1);margin-bottom:3px">Administrator — Full Access</div>
                <div style="font-size:12px;color:var(--c2)">Role <strong>${esc(role.display_name)}</strong> มีสิทธิ์เข้าถึงทุกหน้าโดยอัตโนมัติ ไม่สามารถจำกัดสิทธิ์ได้</div>
            </div>
        </div>
        <div class="pa-section">
            <div class="pa-section-hdr">
                <div class="pa-section-ic" style="background:#ffe4e6;color:#e11d48"><i class="fas fa-shield-alt"></i></div>
                <div class="pa-section-title">สิทธิ์ทุกหน้า (ล็อก)</div>
                <div class="pa-section-count"><?= $totalPages ?> หน้า</div>
            </div>
            <div class="pa-page-grid">
                ${allItems.map(({pg})=>`
                    <div class="pa-page-item locked">
                        <div class="pa-page-ic" style="background:#d1fae5;color:#059669"><i class="${pg.icon}"></i></div>
                        <div class="pa-page-info"><div class="pa-page-name">${esc(pg.label)}</div><div class="pa-page-desc">${esc(pg.desc)}</div></div>
                        <i class="pa-lock-ic fas fa-lock"></i>
                    </div>`).join('')}
            </div>
        </div>`;
}

/* ── Page panel ────────────────────────────────── */
function renderPagePanel(panel,role){
    const c=RC[role.level]||RC[1];
    const totalOn=Object.values(currentPerms).filter(Boolean).length;
    const totalAll=Object.keys(currentPerms).length;

    const hasDef=!!ROLE_DEFAULTS[role.name];
    let html=`<div class="pa-role-row">
        <div class="pa-role-row-ic" style="background:${c.bg};color:${c.color}"><i class="fas ${c.icon}"></i></div>
        <div>
            <div class="pa-role-row-name">${esc(role.display_name)}</div>
            <div class="pa-role-row-sub" id="paRoleRowSub">${totalOn}/${totalAll} หน้าที่เปิดใช้งาน</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
            ${hasDef?`<button class="pa-bulk-btn" style="border-color:#2563eb;color:#2563eb;background:#eff6ff" onclick="showDefaultModal(${role.id},event)"><i class="fas fa-rotate-left" style="margin-right:3px"></i>ค่าเริ่มต้น</button>`:''}
            <button class="pa-bulk-btn" style="border-color:var(--pa);color:var(--pa)" onclick="bulkToggleAll(true)"><i class="fas fa-check-double" style="margin-right:3px"></i>เปิดทั้งหมด</button>
            <button class="pa-bulk-btn" onclick="bulkToggleAll(false)"><i class="fas fa-ban" style="margin-right:3px"></i>ปิดทั้งหมด</button>
        </div>
    </div>`;

    CATALOG.forEach(sec=>{
        const onCount=sec.pages.filter(pg=>currentPerms[pg.key]).length;
        html+=`<div class="pa-section" id="sec_${sec.section}">
            <div class="pa-section-hdr">
                <div class="pa-section-ic" style="background:${sec.bg};color:${sec.color}"><i class="${sec.icon}"></i></div>
                <div class="pa-section-title">${sec.label}</div>
                <div class="pa-section-count" id="scnt_${sec.section}">${onCount}/${sec.pages.length}</div>
                <!-- Per-section dirty indicator + actions -->
                <div class="pa-sec-actions">
                    <span class="pa-chg-pill" id="schg_${sec.section}">
                        <i class="fas fa-circle" style="font-size:6px"></i>
                        <span id="schgCount_${sec.section}">0</span> เปลี่ยนแปลง
                    </span>
                    <button class="pa-sec-reset-btn" id="secReset_${sec.section}"
                            onclick="resetSection('${sec.section}')">รีเซ็ต</button>
                    <button class="pa-sec-save-btn" id="secSaveBtn_${sec.section}"
                            onclick="requestSave('${sec.section}')">
                        <i class="fas fa-floppy-disk"></i>บันทึกส่วนนี้
                    </button>
                </div>
                <div class="pa-bulk-btns">
                    <button class="pa-bulk-btn" onclick="bulkToggle('${sec.section}',true)">เปิดทั้งหมด</button>
                    <button class="pa-bulk-btn" onclick="bulkToggle('${sec.section}',false)">ปิดทั้งหมด</button>
                </div>
            </div>
            <div class="pa-page-grid">${sec.pages.map(pg=>pageHTML(pg,sec)).join('')}</div>
        </div>`;
    });

    panel.innerHTML=html;
}

function pageHTML(pg,sec){
    const on=currentPerms[pg.key];
    return `<div class="pa-page-item" onclick="togglePage('${pg.key}','${sec.section}')">
        <div class="pa-page-ic" id="ic_${pg.key}" style="background:${on?sec.bg:'#f1f5f9'};color:${on?sec.color:'#94a3b8'}">
            <i class="${pg.icon}"></i>
        </div>
        <div class="pa-page-info">
            <div class="pa-page-name">${esc(pg.label)}</div>
            <div class="pa-page-desc">${esc(pg.desc)}</div>
        </div>
        <label class="pa-switch" onclick="event.stopPropagation()">
            <input type="checkbox" id="sw_${pg.key}" ${on?'checked':''}
                   onchange="onSwitch('${pg.key}','${sec.section}',this)">
            <div class="pa-track"><div class="pa-thumb"></div></div>
        </label>
    </div>`;
}

/* ── Toggle interactions ───────────────────────── */
function togglePage(key,section){
    const sw=document.getElementById('sw_'+key);
    if(!sw) return;
    sw.checked=!sw.checked;
    onSwitch(key,section,sw);
}

function onSwitch(key,section,sw){
    currentPerms[key]=sw.checked;
    const sec=CATALOG.find(s=>s.section===section);
    const ic=document.getElementById('ic_'+key);
    if(ic&&sec){
        ic.style.background=sw.checked?sec.bg:'#f1f5f9';
        ic.style.color=sw.checked?sec.color:'#94a3b8';
    }
    updateCounts(section);
    updateDirty(section);
}

function updateCounts(section){
    const sec=CATALOG.find(s=>s.section===section);
    if(!sec) return;
    const on=sec.pages.filter(pg=>currentPerms[pg.key]).length;
    const el=document.getElementById('scnt_'+section);
    if(el) el.textContent=on+'/'+sec.pages.length;
    // Overall row sub
    const totalOn=Object.values(currentPerms).filter(Boolean).length;
    const totalAll=Object.keys(currentPerms).length;
    const sub=document.getElementById('paRoleRowSub');
    if(sub) sub.textContent=totalOn+'/'+totalAll+' หน้าที่เปิดใช้งาน';
}

/* Update the dirty indicator for a section */
function updateDirty(section){
    const sec=CATALOG.find(s=>s.section===section);
    if(!sec) return;
    const changes=sec.pages.filter(pg=>currentPerms[pg.key]!==savedPerms[pg.key]).length;
    const pill=document.getElementById('schg_'+section);
    const cnt=document.getElementById('schgCount_'+section);
    const btn=document.getElementById('secSaveBtn_'+section);
    const rst=document.getElementById('secReset_'+section);
    const card=document.getElementById('sec_'+section);
    if(!pill||!btn) return;
    if(changes>0){
        if(cnt) cnt.textContent=changes;
        pill.classList.add('show'); btn.classList.add('show'); rst&&rst.classList.add('show');
        card&&card.classList.add('has-dirty');
    }else{
        pill.classList.remove('show'); btn.classList.remove('show'); rst&&rst.classList.remove('show');
        card&&card.classList.remove('has-dirty');
    }
}

function bulkToggle(section,val){
    const sec=CATALOG.find(s=>s.section===section);
    if(!sec) return;
    sec.pages.forEach(pg=>{
        currentPerms[pg.key]=val;
        const sw=document.getElementById('sw_'+pg.key);
        const ic=document.getElementById('ic_'+pg.key);
        if(sw) sw.checked=val;
        if(ic){ ic.style.background=val?sec.bg:'#f1f5f9'; ic.style.color=val?sec.color:'#94a3b8'; }
    });
    updateCounts(section); updateDirty(section);
}

function bulkToggleAll(val){
    CATALOG.forEach(sec=>bulkToggle(sec.section,val));
}

/* Reset a section back to saved state */
function resetSection(section){
    const sec=CATALOG.find(s=>s.section===section);
    if(!sec) return;
    sec.pages.forEach(pg=>{
        currentPerms[pg.key]=savedPerms[pg.key];
        const sw=document.getElementById('sw_'+pg.key);
        const ic=document.getElementById('ic_'+pg.key);
        const on=savedPerms[pg.key];
        if(sw) sw.checked=on;
        if(ic){ ic.style.background=on?sec.bg:'#f1f5f9'; ic.style.color=on?sec.color:'#94a3b8'; }
    });
    updateCounts(section); updateDirty(section);
}

/* ══════════════════════════════════════════════════
   CONFIRMATION MODAL
══════════════════════════════════════════════════ */
function requestSave(section){
    const sec=CATALOG.find(s=>s.section===section);
    if(!sec) return;
    const role=allRoles.find(r=>r.id==selectedRoleId);

    // Build change lists
    const added  =sec.pages.filter(pg=>!savedPerms[pg.key]&&currentPerms[pg.key]);
    const removed=sec.pages.filter(pg=>savedPerms[pg.key]&&!currentPerms[pg.key]);
    const same   =sec.pages.filter(pg=>savedPerms[pg.key]===currentPerms[pg.key]);

    if(!added.length&&!removed.length){
        showToast('ไม่มีการเปลี่ยนแปลงในส่วนนี้'); return;
    }

    // Subtitle
    document.getElementById('pcSubtitle').textContent=
        `Role: ${role?.display_name||'—'}  |  ส่วน: ${sec.label}`;

    // Body
    let body='';
    if(added.length){
        body+=`<div class="pc-group pc-added">
            <div class="pc-group-hdr"><i class="fas fa-circle-plus"></i>&ensp;เปิดการมองเห็น (${added.length} หน้า)</div>
            <div class="pc-items">${added.map(pg=>pageChangeItem(pg,'#15803d')).join('')}</div>
        </div>`;
    }
    if(removed.length){
        body+=`<div class="pc-group pc-removed">
            <div class="pc-group-hdr"><i class="fas fa-circle-minus"></i>&ensp;ปิดการมองเห็น (${removed.length} หน้า)</div>
            <div class="pc-items">${removed.map(pg=>pageChangeItem(pg,'#dc2626')).join('')}</div>
        </div>`;
    }
    if(same.length){
        body+=`<div class="pc-group pc-unchanged">
            <div class="pc-group-hdr"><i class="fas fa-minus"></i>&ensp;ไม่เปลี่ยนแปลง</div>
            <div class="pc-no-change">${same.length} หน้า คงเดิม</div>
        </div>`;
    }
    document.getElementById('pcBody').innerHTML=body;

    // Summary line
    const parts=[];
    if(added.length)   parts.push(`<span style="color:#15803d;font-weight:700">+${added.length} เปิด</span>`);
    if(removed.length) parts.push(`<span style="color:#dc2626;font-weight:700">−${removed.length} ปิด</span>`);
    document.getElementById('pcSummary').innerHTML=parts.join(' &nbsp;·&nbsp; ');

    pendingSaveSection=section;
    document.getElementById('paConfirmModal').classList.add('show');
}

function pageChangeItem(pg, color){
    return `<div class="pc-item">
        <i class="${pg.icon}" style="color:${color}"></i>
        <span class="pc-item-name">${esc(pg.label)}</span>
        <span class="pc-item-desc">— ${esc(pg.desc)}</span>
    </div>`;
}

function closeModal(){
    document.getElementById('paConfirmModal').classList.remove('show');
    pendingSaveSection=null;
}
// Close on overlay click
document.getElementById('paConfirmModal').addEventListener('click',function(e){
    if(e.target===this) closeModal();
});

async function doSave(){
    if(!pendingSaveSection||!selectedRoleId) return;
    const section=pendingSaveSection;
    const sec=CATALOG.find(s=>s.section===section);
    closeModal();

    // Build full allowed list:
    //   - for THIS section: use currentPerms (what user just changed)
    //   - for OTHER sections: use savedPerms (don't disturb their committed state)
    const pages=[];
    CATALOG.forEach(s=>{
        s.pages.forEach(pg=>{
            const allowed=s.section===section?currentPerms[pg.key]:savedPerms[pg.key];
            if(allowed) pages.push(pg.key);
        });
    });

    const btn=document.getElementById('secSaveBtn_'+section);
    if(btn){ btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>'; }

    try{
        const d=await apiFetch(PA_API+'?action=save',{
            method:'POST',
            body:JSON.stringify({role_id:selectedRoleId,pages})
        });
        if(!d.success) throw new Error(d.error||'บันทึกล้มเหลว');

        // Commit this section to savedPerms
        sec.pages.forEach(pg=>{ savedPerms[pg.key]=currentPerms[pg.key]; });
        updateDirty(section);

        // Update role list
        const roleId=parseInt(selectedRoleId);
        const allOn=CATALOG.flatMap(s=>s.pages).every(pg=>savedPerms[pg.key]);
        if(allOn) customRoles.delete(roleId); else customRoles.add(roleId);
        document.getElementById('stConfigured').textContent=customRoles.size;
        document.getElementById('hmConfigured').textContent=customRoles.size;
        renderRoleList();

        const addedN=sec.pages.filter(pg=>pages.includes(pg.key)&&!savedPerms[pg.key]).length;
        showToast(`บันทึกส่วน "${sec.label}" สำเร็จ`);
    }catch(e){
        showToast(e.message,'err');
    }finally{
        if(btn){ btn.disabled=false; btn.innerHTML='<i class="fas fa-floppy-disk"></i>บันทึกส่วนนี้'; }
    }
}

/* ══════════════════════════════════════════════════
   DEFAULT PRESET MODAL
══════════════════════════════════════════════════ */
function showDefaultModal(roleId, e){
    if(e) e.stopPropagation();
    const role = allRoles.find(r=>r.id==roleId);
    if(!role) return;
    const def = ROLE_DEFAULTS[role.name];
    if(!def) return;

    const c      = RC[role.level]||RC[1];
    const defSet = new Set(def.pages);
    const allPages = CATALOG.flatMap(s=>s.pages);
    const onPages  = allPages.filter(pg=>defSet.has(pg.key));
    const offPages = allPages.filter(pg=>!defSet.has(pg.key));

    // Header
    const hdrIc = document.getElementById('pdefHdrIc');
    hdrIc.style.cssText = `background:${def.bg};color:${def.color}`;
    hdrIc.innerHTML = `<i class="${def.icon}"></i>`;
    document.getElementById('pdefTitle').textContent = 'ค่าเริ่มต้นสำหรับ ' + role.display_name;
    document.getElementById('pdefSub').textContent = def.desc;

    // Warn if current role has a custom config that will be replaced
    const hasCust = customRoles.has(parseInt(role.id));

    let body = '';

    // Role preset card
    body += `<div class="pa-def-preset-card" style="background:${def.bg}22;border:1.5px solid ${def.bg}">
        <div class="pa-def-preset-ic" style="background:${def.bg};color:${def.color}"><i class="${def.icon}"></i></div>
        <div>
            <div style="font-size:13px;font-weight:700;color:${def.color};margin-bottom:3px">${esc(def.label)}</div>
            <div style="font-size:11px;color:var(--c3);line-height:1.55">${esc(def.desc)}</div>
        </div>
    </div>`;

    if(hasCust){
        body += `<div class="pa-def-warn">
            <i class="fas fa-triangle-exclamation" style="flex-shrink:0;margin-top:1px"></i>
            <span>Role นี้มีการกำหนดสิทธิ์อยู่แล้ว การบันทึกค่าเริ่มต้นจะ<strong>แทนที่</strong>สิทธิ์ทั้งหมดด้วย preset นี้</span>
        </div>`;
    }

    // Pages ON — grouped by section
    body += `<div class="pc-group pc-added">
        <div class="pc-group-hdr"><i class="fas fa-circle-check"></i>&ensp;เปิดการมองเห็น — ${onPages.length} หน้า</div>
        <div class="pc-items">
        ${CATALOG.map(sec=>{
            const secOn = sec.pages.filter(pg=>defSet.has(pg.key));
            if(!secOn.length) return '';
            return `<div style="padding:4px 0;border-top:1px solid rgba(0,0,0,.04)">
                <div class="pa-def-sec-label" style="color:${sec.color};background:${sec.bg}22">
                    <i class="${sec.icon}" style="margin-right:5px"></i>${sec.label}
                </div>
                ${secOn.map(pg=>`<div class="pc-item">
                    <i class="${pg.icon}" style="color:#15803d"></i>
                    <span class="pc-item-name">${esc(pg.label)}</span>
                    <span class="pc-item-desc">— ${esc(pg.desc)}</span>
                </div>`).join('')}
            </div>`;
        }).join('')}
        </div>
    </div>`;

    // Pages OFF — collapsed list
    if(offPages.length){
        body += `<div class="pc-group pc-removed">
            <div class="pc-group-hdr"><i class="fas fa-ban"></i>&ensp;ปิดการมองเห็น — ${offPages.length} หน้า</div>
            <div class="pc-items">
            ${CATALOG.map(sec=>{
                const secOff = sec.pages.filter(pg=>!defSet.has(pg.key));
                if(!secOff.length) return '';
                return `<div style="padding:4px 0;border-top:1px solid rgba(0,0,0,.04)">
                    <div class="pa-def-sec-label" style="color:${sec.color};background:${sec.bg}22">
                        <i class="${sec.icon}" style="margin-right:5px"></i>${sec.label}
                    </div>
                    ${secOff.map(pg=>`<div class="pc-item">
                        <i class="${pg.icon}" style="color:#dc2626"></i>
                        <span class="pc-item-name">${esc(pg.label)}</span>
                        <span class="pc-item-desc">— ${esc(pg.desc)}</span>
                    </div>`).join('')}
                </div>`;
            }).join('')}
            </div>
        </div>`;
    }

    document.getElementById('pdefBody').innerHTML = body;

    // Footer summary
    document.getElementById('pdefSummary').innerHTML =
        `<i class="fas fa-check" style="color:#15803d;margin-right:4px"></i>
         <strong style="color:#15803d">${onPages.length}</strong> เปิด &nbsp;·&nbsp;
         <strong style="color:#dc2626">${offPages.length}</strong> ปิด &nbsp;·&nbsp; จาก ${TOTAL_P} หน้า`;

    pendingDefaultRole = role;
    document.getElementById('paDefaultModal').classList.add('show');
}

function closeDefaultModal(){
    document.getElementById('paDefaultModal').classList.remove('show');
    pendingDefaultRole = null;
}
document.getElementById('paDefaultModal').addEventListener('click', function(e){
    if(e.target===this) closeDefaultModal();
});

async function doSaveDefault(){
    if(!pendingDefaultRole) return;
    const role = pendingDefaultRole;
    const def  = ROLE_DEFAULTS[role.name];
    if(!def) return;

    const btn = document.getElementById('pdefConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    try{
        const d = await apiFetch(PA_API+'?action=save',{
            method:'POST',
            body:JSON.stringify({role_id:role.id, pages:def.pages})
        });
        if(!d.success) throw new Error(d.error||'บันทึกล้มเหลว');

        closeDefaultModal();

        // Sync state
        const roleId = parseInt(role.id);
        if(def.pages.length < TOTAL_P) customRoles.add(roleId);
        else customRoles.delete(roleId);

        const permMap = {};
        def.pages.forEach(k=>{ permMap[k]=true; });
        const idx = allPermsData.findIndex(x=>x.role.id==role.id);
        if(idx>=0) allPermsData[idx].permissions = permMap;

        document.getElementById('stConfigured').textContent = customRoles.size;
        document.getElementById('hmConfigured').textContent = customRoles.size;
        renderRoleList();

        // If this role is open, refresh the panel live
        if(selectedRoleId==role.id){
            CATALOG.forEach(sec=>sec.pages.forEach(pg=>{
                const val = def.pages.includes(pg.key);
                currentPerms[pg.key] = val;
                savedPerms[pg.key]   = val;
            }));
            const panel = document.getElementById('paPagesPanelWrap');
            renderPagePanel(panel, role);
        }

        showToast(`ตั้งค่าเริ่มต้น "${role.display_name}" สำเร็จ — ${def.pages.length} หน้าเปิด`);
    }catch(e){
        showToast(e.message,'err');
    }finally{
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sliders"></i>บันทึกค่าเริ่มต้น';
    }
}

/* ══════════════════════════════════════════════════
   STAT DETAIL MODAL
══════════════════════════════════════════════════ */
const TOTAL_P = <?= $totalPages ?>;

function showStatDetail(type){
    const overlay = document.getElementById('paStatModal');
    const hdrIc   = document.getElementById('psmHdrIc');
    const title   = document.getElementById('psmTitle');
    const sub     = document.getElementById('psmSub');
    const body    = document.getElementById('psmBody');

    switch(type){
        case 'roles':
            hdrIc.style.cssText='background:#ffe4e6;color:#e11d48';
            hdrIc.innerHTML='<i class="fas fa-shield-alt"></i>';
            title.textContent='สรุปข้อมูล Role ทั้งหมด';
            sub.textContent=`${allRoles.length} roles ในระบบ — คลิกเพื่อแก้ไขสิทธิ์`;
            body.innerHTML=buildRolesDetail();
            break;
        case 'pages':
            hdrIc.style.cssText='background:#dbeafe;color:#2563eb';
            hdrIc.innerHTML='<i class="fas fa-file-alt"></i>';
            title.textContent='หน้าทั้งหมดในระบบ';
            sub.textContent=`${TOTAL_P} หน้า ใน ${CATALOG.length} กลุ่ม`;
            body.innerHTML=buildPagesDetail();
            break;
        case 'configured':
            hdrIc.style.cssText='background:#d1fae5;color:#059669';
            hdrIc.innerHTML='<i class="fas fa-check-circle"></i>';
            title.textContent='Role ที่กำหนดสิทธิ์แล้ว';
            sub.textContent=`${customRoles.size} จาก ${allRoles.filter(r=>r.level<5).length} roles ที่ตั้งค่าได้มีการกำหนดสิทธิ์`;
            body.innerHTML=buildConfiguredDetail();
            break;
        case 'admin':
            hdrIc.style.cssText='background:#fef3c7;color:#d97706';
            hdrIc.innerHTML='<i class="fas fa-crown"></i>';
            title.textContent='Admin Lock — สิทธิ์พิเศษ';
            sub.textContent='Role ระดับ 5 ขึ้นไปเข้าถึงทุกหน้าโดยอัตโนมัติ ไม่สามารถจำกัดได้';
            body.innerHTML=buildAdminDetail();
            break;
    }
    overlay.classList.add('show');
}

function closeStatModal(){
    document.getElementById('paStatModal').classList.remove('show');
}
document.getElementById('paStatModal').addEventListener('click',function(e){
    if(e.target===this) closeStatModal();
});

/* ── Builders ───────────────────────────────────── */
function buildRolesDetail(){
    return allRoles.map(r=>{
        const c=RC[r.level]||RC[1];
        const isAdmin=r.level>=5;
        const hasCust=customRoles.has(parseInt(r.id));
        const permData=allPermsData.find(x=>x.role.id==r.id);
        const allowedCount=permData?Object.keys(permData.permissions).length:0;
        const dispCount=isAdmin?TOTAL_P:(hasCust?allowedCount:TOTAL_P);
        const pct=TOTAL_P>0?Math.round(dispCount/TOTAL_P*100):0;
        const statusIcon=isAdmin
            ?`<i class="fas fa-crown" style="color:#d97706;margin-right:3px"></i><span style="color:#d97706">Full Access</span>`
            :hasCust
            ?`<i class="fas fa-circle-check" style="color:#059669;margin-right:3px"></i><span style="color:#059669">กำหนดเอง</span>`
            :`<i class="fas fa-circle" style="color:var(--c3);margin-right:3px;font-size:8px"></i><span style="color:var(--c3)">ค่าเริ่มต้น</span>`;
        return `<div class="psd-role-row" onclick="closeStatModal();selectRole(${r.id})">
            <div class="psd-role-ic" style="background:${c.bg};color:${c.color}"><i class="fas ${c.icon}"></i></div>
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:700;color:var(--c1)">${esc(r.display_name)}</div>
                <div style="font-size:10px;color:var(--c3);margin-top:1px">${esc(r.name)} &middot; Lv.${r.level} &middot; ${r.user_count} ผู้ใช้</div>
                <div class="psd-pct-bar"><div class="psd-pct-fill" style="width:${pct}%;background:${c.color}"></div></div>
            </div>
            <div style="text-align:right;flex-shrink:0;padding-left:10px">
                <div class="psd-badge" style="background:${c.bg};color:${c.color}">${dispCount}/${TOTAL_P}</div>
                <div style="font-size:10px;margin-top:5px;display:flex;align-items:center;justify-content:flex-end;gap:3px">${statusIcon}</div>
            </div>
        </div>`;
    }).join('');
}

function buildPagesDetail(){
    return CATALOG.map(sec=>`
        <div style="margin-bottom:12px">
            <div class="psd-sec-hdr" style="background:${sec.bg};border:1.5px solid ${sec.bg}">
                <div class="psd-page-ic" style="background:rgba(255,255,255,.5);color:${sec.color}"><i class="${sec.icon}"></i></div>
                <span style="color:${sec.color}">${esc(sec.label)}</span>
                <span style="margin-left:auto;font-size:10px;background:rgba(255,255,255,.55);color:${sec.color};padding:2px 8px;border-radius:8px;font-weight:700">${sec.pages.length} หน้า</span>
            </div>
            <div class="psd-sec-body">
                ${sec.pages.map(pg=>`
                    <div class="psd-page-row">
                        <div class="psd-page-ic" style="background:${sec.bg};color:${sec.color}"><i class="${pg.icon}"></i></div>
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:600;color:var(--c1);font-size:12px">${esc(pg.label)}</div>
                            <div style="font-size:10px;color:var(--c3)">${esc(pg.desc)}</div>
                        </div>
                        <code style="font-size:10px;background:var(--bg);padding:2px 8px;border-radius:6px;color:var(--c3);flex-shrink:0">${pg.key}</code>
                    </div>
                `).join('')}
            </div>
        </div>
    `).join('');
}

function buildConfiguredDetail(){
    const configured=allRoles.filter(r=>customRoles.has(parseInt(r.id)));
    const unconfigured=allRoles.filter(r=>!customRoles.has(parseInt(r.id))&&r.level<5);
    let html='';

    if(configured.length===0){
        html+=`<div style="text-align:center;padding:40px 20px;color:var(--c3)">
            <i class="fas fa-inbox" style="font-size:32px;opacity:.3;display:block;margin-bottom:12px"></i>
            <p style="margin:0 0 4px;font-size:13px;font-weight:600;color:var(--c2)">ยังไม่มีการกำหนดสิทธิ์เฉพาะ</p>
            <p style="margin:0;font-size:12px">ทุก Role สามารถเข้าถึงทุกหน้าได้ (ค่าเริ่มต้น)</p>
        </div>`;
    } else {
        html+=`<div class="psd-label">กำหนดสิทธิ์แล้ว (${configured.length} roles)</div>`;
        html+=configured.map(r=>{
            const c=RC[r.level]||RC[1];
            const permData=allPermsData.find(x=>x.role.id==r.id);
            const allowed=permData?Object.keys(permData.permissions):[];
            const allPages=CATALOG.flatMap(s=>s.pages);
            const restricted=allPages.filter(pg=>!allowed.includes(pg.key));
            const pct=TOTAL_P>0?Math.round(allowed.length/TOTAL_P*100):0;
            const restrictedSnip=restricted.slice(0,4).map(pg=>pg.label).join(', ')+(restricted.length>4?` +${restricted.length-4}`:'');
            return `<div class="psd-role-row" onclick="closeStatModal();selectRole(${r.id})" style="flex-direction:column;align-items:stretch;gap:8px">
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="psd-role-ic" style="background:${c.bg};color:${c.color}"><i class="fas ${c.icon}"></i></div>
                    <div style="flex:1">
                        <div style="font-size:13px;font-weight:700;color:var(--c1)">${esc(r.display_name)}</div>
                        <div style="font-size:10px;color:var(--c3)">${r.user_count} ผู้ใช้ &middot; ${esc(r.name)}</div>
                    </div>
                    <div class="psd-badge" style="background:${c.bg};color:${c.color}">${allowed.length}/${TOTAL_P} หน้า</div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--c3);margin-bottom:4px">
                        <span>เปิด ${allowed.length} &middot; ปิด ${restricted.length}</span><span>${pct}%</span>
                    </div>
                    <div class="psd-pct-bar" style="height:6px"><div class="psd-pct-fill" style="width:${pct}%;background:${c.color}"></div></div>
                </div>
                ${restricted.length>0?`<div style="font-size:10px;color:#dc2626;background:#fef2f2;padding:6px 10px;border-radius:7px;border:1px solid #fecaca">
                    <i class="fas fa-ban" style="margin-right:4px"></i>ปิด: ${esc(restrictedSnip)}
                </div>`:'<div style="font-size:10px;color:#059669;background:#f0fdf4;padding:6px 10px;border-radius:7px;border:1px solid #bbf7d0"><i class="fas fa-check" style="margin-right:4px"></i>เปิดทุกหน้า (ตั้งค่าแล้ว)</div>'}
            </div>`;
        }).join('');
    }

    if(unconfigured.length>0){
        html+=`<div class="psd-label">ค่าเริ่มต้น — เข้าถึงทุกหน้า (${unconfigured.length} roles)</div>`;
        html+=unconfigured.map(r=>{
            const c=RC[r.level]||RC[1];
            return `<div style="display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:10px;border:1.5px dashed #e2e8f0;margin-bottom:6px;opacity:.75">
                <div class="psd-role-ic" style="background:${c.bg};color:${c.color}"><i class="fas ${c.icon}"></i></div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:600;color:var(--c1)">${esc(r.display_name)}</div>
                    <div style="font-size:10px;color:var(--c3)">${r.user_count} ผู้ใช้ &middot; ไม่มีการจำกัดสิทธิ์</div>
                </div>
                <span style="font-size:10px;color:var(--c3);background:var(--bg);padding:3px 9px;border-radius:8px">ค่าเริ่มต้น</span>
            </div>`;
        }).join('');
    }
    return html;
}

function buildAdminDetail(){
    const adminRoles=allRoles.filter(r=>r.level>=5);
    return `
        <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1.5px solid #fcd34d;border-radius:12px;padding:16px 18px;margin-bottom:16px;display:flex;gap:14px">
            <div style="width:44px;height:44px;border-radius:12px;background:#d97706;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0"><i class="fas fa-crown"></i></div>
            <div>
                <div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:3px">Admin Bypass — ไม่สามารถจำกัดสิทธิ์</div>
                <div style="font-size:11px;color:#b45309;line-height:1.6">Role ระดับ 5 ขึ้นไปจะข้ามการตรวจสอบ <code style="background:rgba(0,0,0,.06);padding:1px 5px;border-radius:4px">page_permissions</code> เสมอ — ระบบแสดงเมนูทุกรายการโดยอัตโนมัติโดยไม่ต้องมี row ในฐานข้อมูล</div>
            </div>
        </div>
        ${adminRoles.map(r=>`
            <div style="display:flex;align-items:center;gap:12px;padding:11px 14px;border:1.5px solid #fcd34d;border-radius:10px;margin-bottom:8px;background:#fffbeb">
                <div style="width:36px;height:36px;border-radius:10px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0"><i class="fas fa-crown"></i></div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:700;color:#92400e">${esc(r.display_name)}</div>
                    <div style="font-size:11px;color:#b45309">${r.user_count} ผู้ใช้ &middot; Level ${r.level}</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:16px;font-weight:900;color:#d97706">${TOTAL_P}/${TOTAL_P}</div>
                    <div style="font-size:10px;color:#b45309">ทุกหน้า</div>
                </div>
            </div>
        `).join('')}
        <div class="psd-label" style="margin-top:16px">หน้าที่ Admin เข้าถึงได้ (${TOTAL_P} หน้า)</div>
        ${CATALOG.map(sec=>`
            <div style="display:flex;align-items:center;gap:10px;padding:9px 14px;background:${sec.bg};border-radius:9px;margin-bottom:5px">
                <div class="psd-page-ic" style="background:rgba(255,255,255,.5);color:${sec.color}"><i class="${sec.icon}"></i></div>
                <span style="font-size:12px;font-weight:700;color:${sec.color};flex:1">${esc(sec.label)}</span>
                <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end">
                    ${sec.pages.slice(0,3).map(pg=>`<span style="font-size:10px;background:rgba(255,255,255,.6);color:${sec.color};padding:2px 7px;border-radius:6px">${esc(pg.label)}</span>`).join('')}
                    ${sec.pages.length>3?`<span style="font-size:10px;color:${sec.color};padding:2px 4px">+${sec.pages.length-3}</span>`:''}
                </div>
            </div>
        `).join('')}
    `;
}

init();
</script>

<?php Layout::endContent(); ?>
