<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$userId = $user['id'];
$roleLevel = (int)($user['role_level'] ?? $user['level'] ?? 0);
$isAdmin = $roleLevel >= 5;
$isManager = $roleLevel >= 3;
$TH = $lang === 'th';

Layout::head($TH ? 'คลังสารเคมี — Lab Store' : 'Lab Store');
?>
<body>
<?php Layout::sidebar('lab-stores'); Layout::beginContent(); ?>
<?php Layout::pageHeader(
    $TH ? 'คลังสารเคมีแยกตามฝ่าย' : 'Lab Chemical Stores',
    'fas fa-store',
    $TH ? 'Dashboard & รายงานแยกตามฝ่าย/งาน ที่รับผิดชอบคลังสารเคมี' : 'Dashboard & Reports by Division / Section'
); ?>

<style>
/* ============ CSS Variables ============ */
:root {
    --ls-primary: #6C5CE7;
    --ls-primary-light: #ede9fe;
    --ls-success: #059669;
    --ls-warning: #d97706;
    --ls-danger: #dc2626;
    --ls-radius: 16px;
    --ls-shadow: 0 4px 24px rgba(0,0,0,.06);
}

/* ============ KPI Cards ============ */
.ls-kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:24px}
.ls-kpi{background:#fff;border-radius:var(--ls-radius);padding:20px 18px;box-shadow:var(--ls-shadow);position:relative;overflow:hidden;transition:all .2s}
.ls-kpi:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,.1)}
.ls-kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--ls-radius) var(--ls-radius) 0 0}
.ls-kpi.c1::before{background:linear-gradient(90deg,#6C5CE7,#a855f7)}
.ls-kpi.c2::before{background:linear-gradient(90deg,#2563eb,#38bdf8)}
.ls-kpi.c3::before{background:linear-gradient(90deg,#059669,#34d399)}
.ls-kpi.c4::before{background:linear-gradient(90deg,#d97706,#fbbf24)}
.ls-kpi-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:10px}
.ls-kpi.c1 .ls-kpi-icon{background:rgba(108,92,231,.1);color:#6C5CE7}
.ls-kpi.c2 .ls-kpi-icon{background:rgba(37,99,235,.1);color:#2563eb}
.ls-kpi.c3 .ls-kpi-icon{background:rgba(5,150,105,.1);color:#059669}
.ls-kpi.c4 .ls-kpi-icon{background:rgba(217,119,6,.1);color:#d97706}
.ls-kpi-value{font-size:28px;font-weight:800;letter-spacing:-1px;color:#1a1a2e;line-height:1}
.ls-kpi-label{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.5px;margin-top:4px}

/* ============ Tab Bar ============ */
.ls-tabs{display:flex;gap:4px;margin-bottom:20px;padding:4px;background:#f1f5f9;border-radius:14px}
.ls-tab{flex:1;padding:10px 8px;font-size:12px;font-weight:700;text-align:center;border:none;border-radius:10px;cursor:pointer;background:transparent;color:#888;transition:all .2s;white-space:nowrap}
.ls-tab:hover{color:#333}
.ls-tab.active{background:#fff;color:var(--ls-primary);box-shadow:0 2px 8px rgba(0,0,0,.08)}
.ls-tab i{margin-right:4px}

/* ============ Division Cards ============ */
.ls-div-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;margin-bottom:24px}
.ls-div-card{background:#fff;border-radius:var(--ls-radius);box-shadow:var(--ls-shadow);overflow:hidden;transition:all .2s;cursor:pointer;border:2px solid transparent}
.ls-div-card:hover{border-color:var(--ls-primary);box-shadow:0 8px 32px rgba(108,92,231,.12);transform:translateY(-2px)}
.ls-div-card.expanded{border-color:var(--ls-primary)}
.ls-div-header{padding:16px 18px;display:flex;align-items:center;gap:12px}
.ls-div-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.ls-div-info{flex:1;min-width:0}
.ls-div-name{font-size:13px;font-weight:700;color:#1a1a2e;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.ls-div-sub{font-size:10px;color:#999;margin-top:2px}
.ls-div-arrow{font-size:11px;color:#ccc;transition:transform .2s}
.ls-div-card.expanded .ls-div-arrow{transform:rotate(180deg)}
.ls-div-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:#f3f4f6}
.ls-div-stat{background:#fff;padding:10px 14px;text-align:center}
.ls-div-stat-val{font-size:18px;font-weight:800;color:#1a1a2e}
.ls-div-stat-lbl{font-size:9px;color:#999;font-weight:600;text-transform:uppercase;letter-spacing:.3px}
.ls-div-progress{height:4px;background:#f3f4f6;margin:0 16px 12px;border-radius:2px;overflow:hidden}
.ls-div-progress-bar{height:100%;border-radius:2px;transition:width .6s ease}
.ls-div-expand{max-height:0;overflow:hidden;transition:max-height .4s ease}
.ls-div-card.expanded .ls-div-expand{max-height:2000px}
.ls-div-sections{padding:0 16px 14px}

/* ============ Section Rows inside division ============ */
.ls-sec-row{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:10px;background:#f9fafb;margin-bottom:6px;transition:background .15s}
.ls-sec-row:hover{background:#ede9fe}
.ls-sec-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.ls-sec-name{flex:1;font-size:12px;font-weight:600;color:#333;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-sec-pills{display:flex;gap:6px;flex-shrink:0}
.ls-sec-pill{font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px;white-space:nowrap}
.ls-sec-pill.bot{background:rgba(108,92,231,.08);color:#6C5CE7}
.ls-sec-pill.chem{background:rgba(37,99,235,.08);color:#2563eb}
.ls-sec-pill.kg{background:rgba(5,150,105,.08);color:#059669}

/* ============ Store Table ============ */
.ls-table-wrap{background:#fff;border-radius:var(--ls-radius);box-shadow:var(--ls-shadow);overflow:hidden}
.ls-table-header{padding:16px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #f3f4f6;flex-wrap:wrap}
.ls-table-title{font-size:15px;font-weight:700;color:#1a1a2e;flex:1}
.ls-search{position:relative}
.ls-search i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#bbb;font-size:12px}
.ls-search input{padding:8px 12px 8px 30px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:12px;width:220px;background:#f9fafb}
.ls-search input:focus{outline:none;border-color:var(--ls-primary);box-shadow:0 0 0 3px rgba(108,92,231,.08);background:#fff}
.ls-filter select{padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:11px;font-weight:600;background:#f9fafb;cursor:pointer}
table.ls-tbl{width:100%;border-collapse:collapse;font-size:12px}
table.ls-tbl th{padding:10px 14px;text-align:left;font-weight:700;color:#888;font-size:10px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #f3f4f6;white-space:nowrap;cursor:pointer;user-select:none}
table.ls-tbl th:hover{color:var(--ls-primary)}
table.ls-tbl th.sorted{color:var(--ls-primary)}
table.ls-tbl th .sort-icon{margin-left:4px;font-size:8px}
table.ls-tbl td{padding:10px 14px;border-bottom:1px solid #f9fafb;color:#333;vertical-align:middle}
table.ls-tbl tbody tr{transition:background .1s}
table.ls-tbl tbody tr:hover{background:#faf5ff}
.ls-store-name{font-weight:700;color:#1a1a2e;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-badge{display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:700;padding:2px 8px;border-radius:6px}
.ls-badge.div{background:var(--ls-primary-light);color:var(--ls-primary)}
.ls-badge.sec{background:#dbeafe;color:#2563eb}
.ls-bar-cell{position:relative;min-width:100px}
.ls-bar{height:6px;border-radius:3px;background:#f3f4f6;overflow:hidden}
.ls-bar-fill{height:100%;border-radius:3px;transition:width .4s ease}
.ls-bar-val{position:absolute;right:0;top:-2px;font-size:10px;font-weight:700;color:#333}
.ls-empty{text-align:center;padding:48px 20px;color:#ccc}
.ls-empty i{font-size:40px;display:block;margin-bottom:12px;opacity:.2}
.ls-empty p{font-size:13px;color:#999}

/* ============ Treemap ============ */
.ls-treemap{display:flex;flex-wrap:wrap;gap:3px;min-height:120px;margin-bottom:24px;border-radius:var(--ls-radius);overflow:hidden}
.ls-tm-item{border-radius:8px;padding:8px 10px;display:flex;flex-direction:column;justify-content:flex-end;min-height:60px;cursor:pointer;transition:all .2s;overflow:hidden;position:relative}
.ls-tm-item:hover{filter:brightness(1.1);transform:scale(1.02);z-index:2}
.ls-tm-name{font-size:9px;font-weight:700;color:rgba(255,255,255,.9);line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-tm-val{font-size:14px;font-weight:800;color:#fff}

/* ============ Chart Section ============ */
.ls-chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.ls-chart-card{background:#fff;border-radius:var(--ls-radius);box-shadow:var(--ls-shadow);padding:20px}
.ls-chart-title{font-size:14px;font-weight:700;color:#1a1a2e;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.ls-chart-title i{color:var(--ls-primary);font-size:12px}
.ls-hbar{margin-bottom:8px}
.ls-hbar-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:3px}
.ls-hbar-name{font-size:11px;font-weight:600;color:#555;max-width:70%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ls-hbar-val{font-size:11px;font-weight:700;color:#1a1a2e}
.ls-hbar-track{height:8px;background:#f3f4f6;border-radius:4px;overflow:hidden}
.ls-hbar-fill{height:100%;border-radius:4px;transition:width .6s ease}

/* ============ Pagination ============ */
.ls-pagination{display:flex;align-items:center;justify-content:center;gap:6px;padding:14px}
.ls-pagination button{padding:6px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:11px;font-weight:600;background:#fff;cursor:pointer;transition:all .15s}
.ls-pagination button:hover:not(:disabled){border-color:var(--ls-primary);color:var(--ls-primary)}
.ls-pagination button:disabled{opacity:.4;cursor:not-allowed}
.ls-pagination .pg-info{font-size:11px;color:#888;font-weight:600}

/* ============ Import Button ============ */
.ls-import-btn{padding:8px 16px;border:none;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:6px;background:var(--ls-primary);color:#fff}
.ls-import-btn:hover{filter:brightness(1.1);transform:translateY(-1px)}

/* ============ Responsive ============ */
@media(max-width:768px){
    .ls-kpi-grid{grid-template-columns:repeat(2,1fr);gap:8px}
    .ls-kpi{padding:14px 12px}
    .ls-kpi-value{font-size:22px}
    .ls-div-grid{grid-template-columns:1fr}
    .ls-chart-grid{grid-template-columns:1fr}
    .ls-table-header{flex-direction:column;align-items:stretch}
    .ls-search input{width:100%}
    .ls-tabs{overflow-x:auto;flex-wrap:nowrap}
    .ls-tab{min-width:100px}
}

/* ============ Animations ============ */
@keyframes lsFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.ls-fade{animation:lsFadeIn .35s ease both}
</style>

<!-- ===== KPI DASHBOARD ===== -->
<div class="ls-kpi-grid" id="kpiGrid">
    <div class="ls-kpi c1 ls-fade">
        <div class="ls-kpi-icon"><i class="fas fa-store"></i></div>
        <div class="ls-kpi-value" id="kpiStores">-</div>
        <div class="ls-kpi-label"><?= $TH?'คลังทั้งหมด':'Total Stores' ?></div>
    </div>
    <div class="ls-kpi c2 ls-fade" style="animation-delay:.05s">
        <div class="ls-kpi-icon"><i class="fas fa-wine-bottle"></i></div>
        <div class="ls-kpi-value" id="kpiBottles">-</div>
        <div class="ls-kpi-label"><?= $TH?'ขวดสารเคมี':'Total Bottles' ?></div>
    </div>
    <div class="ls-kpi c3 ls-fade" style="animation-delay:.1s">
        <div class="ls-kpi-icon"><i class="fas fa-flask"></i></div>
        <div class="ls-kpi-value" id="kpiChemicals">-</div>
        <div class="ls-kpi-label"><?= $TH?'ชนิดสารเคมี':'Chemical Types' ?></div>
    </div>
    <div class="ls-kpi c4 ls-fade" style="animation-delay:.15s">
        <div class="ls-kpi-icon"><i class="fas fa-weight-hanging"></i></div>
        <div class="ls-kpi-value" id="kpiWeight">-</div>
        <div class="ls-kpi-label"><?= $TH?'ปริมาณรวม (kg)':'Total Weight (kg)' ?></div>
    </div>
</div>

<!-- ===== TABS ===== -->
<div class="ls-tabs">
    <button class="ls-tab active" onclick="switchTab('overview')"><i class="fas fa-th-large"></i> <?= $TH?'ภาพรวม':'Overview' ?></button>
    <button class="ls-tab" onclick="switchTab('divisions')"><i class="fas fa-building"></i> <?= $TH?'แยกตามฝ่าย':'By Division' ?></button>
    <button class="ls-tab" onclick="switchTab('table')"><i class="fas fa-table"></i> <?= $TH?'ตารางทั้งหมด':'All Stores' ?></button>
    <button class="ls-tab" onclick="switchTab('report')"><i class="fas fa-chart-bar"></i> <?= $TH?'รายงาน':'Report' ?></button>
</div>

<!-- ===== TAB: Overview ===== -->
<div id="tabOverview">
    <!-- Treemap -->
    <div class="ls-chart-card ls-fade" style="margin-bottom:20px">
        <div class="ls-chart-title"><i class="fas fa-th"></i> <?= $TH?'สัดส่วนสารเคมีแยกตามฝ่าย (Treemap)':'Division Chemical Treemap' ?></div>
        <div id="treemap" class="ls-treemap"></div>
    </div>
    <!-- Charts -->
    <div class="ls-chart-grid">
        <div class="ls-chart-card ls-fade">
            <div class="ls-chart-title"><i class="fas fa-flask"></i> <?= $TH?'Top 10 คลังตามปริมาณ (kg)':'Top 10 Stores by Weight' ?></div>
            <div id="chartTopWeight"></div>
        </div>
        <div class="ls-chart-card ls-fade" style="animation-delay:.1s">
            <div class="ls-chart-title"><i class="fas fa-wine-bottle"></i> <?= $TH?'Top 10 คลังตามจำนวนขวด':'Top 10 Stores by Bottles' ?></div>
            <div id="chartTopBottles"></div>
        </div>
    </div>
</div>

<!-- ===== TAB: Divisions ===== -->
<div id="tabDivisions" style="display:none">
    <div class="ls-div-grid" id="divGrid"></div>
</div>

<!-- ===== TAB: All Stores Table ===== -->
<div id="tabTable" style="display:none">
    <div class="ls-table-wrap">
        <div class="ls-table-header">
            <div class="ls-table-title"><i class="fas fa-store" style="color:var(--ls-primary);margin-right:6px"></i> <?= $TH?'รายการคลังสารเคมีทั้งหมด':'All Lab Stores' ?></div>
            <div class="ls-search"><i class="fas fa-search"></i><input id="tblSearch" placeholder="<?= $TH?'ค้นหาคลัง...':'Search stores...' ?>" oninput="onTblSearch()"></div>
            <div class="ls-filter">
                <select id="tblDivFilter" onchange="loadTable()">
                    <option value=""><?= $TH?'ทุกฝ่าย':'All Divisions' ?></option>
                </select>
            </div>
            <?php if ($isManager): ?>
            <button class="ls-import-btn" onclick="importCSV()"><i class="fas fa-file-csv"></i> <?= $TH?'นำเข้า CSV':'Import CSV' ?></button>
            <?php endif; ?>
        </div>
        <div style="overflow-x:auto">
            <table class="ls-tbl" id="storeTable">
                <thead>
                    <tr>
                        <th onclick="sortTable('store_name')"><?= $TH?'ชื่อคลัง':'Store Name' ?> <span class="sort-icon"></span></th>
                        <th onclick="sortTable('division_name')"><?= $TH?'ฝ่าย':'Division' ?> <span class="sort-icon"></span></th>
                        <th onclick="sortTable('section_name')"><?= $TH?'งาน':'Section' ?> <span class="sort-icon"></span></th>
                        <th onclick="sortTable('bottle_count')" style="text-align:right"><?= $TH?'ขวด':'Bottles' ?> <span class="sort-icon"></span></th>
                        <th onclick="sortTable('chemical_count')" style="text-align:right"><?= $TH?'สารเคมี':'Chemicals' ?> <span class="sort-icon"></span></th>
                        <th onclick="sortTable('total_weight_kg')"><?= $TH?'ปริมาณ (kg)':'Weight (kg)' ?> <span class="sort-icon"></span></th>
                    </tr>
                </thead>
                <tbody id="tblBody"></tbody>
            </table>
        </div>
        <div class="ls-pagination" id="tblPagination"></div>
    </div>
</div>

<!-- ===== TAB: Report ===== -->
<div id="tabReport" style="display:none">
    <div class="ls-chart-card ls-fade" style="margin-bottom:20px">
        <div class="ls-chart-title" style="margin-bottom:12px"><i class="fas fa-chart-pie"></i> <?= $TH?'รายงานสรุปแยกตามฝ่าย':'Division Summary Report' ?></div>
        <div id="reportDivisionChart"></div>
    </div>
    <div class="ls-table-wrap">
        <div class="ls-table-header">
            <div class="ls-table-title"><i class="fas fa-file-alt" style="color:var(--ls-primary);margin-right:6px"></i> <?= $TH?'สรุปตามฝ่าย':'Summary by Division' ?></div>
        </div>
        <div style="overflow-x:auto">
            <table class="ls-tbl" id="reportTable">
                <thead>
                    <tr>
                        <th><?= $TH?'ฝ่าย':'Division' ?></th>
                        <th style="text-align:right"><?= $TH?'คลัง':'Stores' ?></th>
                        <th style="text-align:right"><?= $TH?'ขวด':'Bottles' ?></th>
                        <th style="text-align:right"><?= $TH?'สารเคมี':'Chemicals' ?></th>
                        <th style="text-align:right"><?= $TH?'ปริมาณ (kg)':'Weight (kg)' ?></th>
                        <th><?= $TH?'สัดส่วน':'Share' ?></th>
                    </tr>
                </thead>
                <tbody id="reportBody"></tbody>
            </table>
        </div>
    </div>
</div>

<?php Layout::endContent(); ?>
<script>
const TH = <?= json_encode($TH) ?>;
const API = '/v1/api/lab_stores.php';
const IS_MANAGER = <?= json_encode($isManager) ?>;

// Color palette for divisions
const COLORS = ['#6C5CE7','#2563eb','#059669','#d97706','#dc2626','#0891b2','#7c3aed','#db2777','#ea580c','#4f46e5','#0d9488','#65a30d'];

let dashData = null;
let currentTab = 'overview';
let tblSort = 'total_weight_kg';
let tblSortDir = 'desc';
let tblPage = 1;
let searchTimer = null;

// ========== INIT ==========
document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
});

async function loadDashboard() {
    try {
        const d = await apiFetch(API + '?action=dashboard');
        if (!d.success) throw new Error(d.error);
        dashData = d.data;
        renderKPIs(dashData.totals);
        renderTreemap(dashData.divisionSummary);
        renderTopCharts(dashData.topByWeight, dashData.divisionSummary);
        renderDivisionCards(dashData.divisionSummary, dashData.sectionSummary);
        populateDivFilter(dashData.divisionSummary);
        renderReport(dashData.divisionSummary);
    } catch(e) {
        console.error(e);
    }
}

// ========== KPIs ==========
function renderKPIs(t) {
    if (!t) return;
    animateNumber('kpiStores', t.total_stores);
    animateNumber('kpiBottles', t.total_bottles);
    animateNumber('kpiChemicals', t.total_chemicals);
    document.getElementById('kpiWeight').textContent = num(t.total_weight_kg);
}

function animateNumber(id, target) {
    target = parseInt(target) || 0;
    const el = document.getElementById(id);
    const dur = 600;
    const start = performance.now();
    const step = (now) => {
        const p = Math.min((now - start) / dur, 1);
        const ease = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * ease).toLocaleString();
        if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

function num(v) { return Number(v||0).toLocaleString(undefined,{maximumFractionDigits:2}); }
function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

// ========== TABS ==========
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.ls-tab').forEach((b,i) => b.classList.toggle('active', ['overview','divisions','table','report'][i] === tab));
    document.getElementById('tabOverview').style.display = tab==='overview' ? '' : 'none';
    document.getElementById('tabDivisions').style.display = tab==='divisions' ? '' : 'none';
    document.getElementById('tabTable').style.display = tab==='table' ? '' : 'none';
    document.getElementById('tabReport').style.display = tab==='report' ? '' : 'none';
    if (tab === 'table') loadTable();
}

// ========== TREEMAP ==========
function renderTreemap(divs) {
    const el = document.getElementById('treemap');
    if (!divs || !divs.length) { el.innerHTML = '<div class="ls-empty"><i class="fas fa-th"></i><p>ไม่มีข้อมูล</p></div>'; return; }
    const totalW = divs.reduce((s,d) => s + parseFloat(d.total_weight_kg||0), 0);
    if (totalW <= 0) { el.innerHTML = ''; return; }
    el.innerHTML = divs.filter(d => parseFloat(d.total_weight_kg) > 0).map((d, i) => {
        const pct = (parseFloat(d.total_weight_kg) / totalW * 100);
        const color = COLORS[i % COLORS.length];
        const shortName = d.division_name.replace(/ฝ่าย/,'').substring(0,30);
        return `<div class="ls-tm-item" style="flex-basis:${Math.max(pct, 8)}%;background:${color}" title="${esc(d.division_name)}\n${num(d.total_weight_kg)} kg (${pct.toFixed(1)}%)" onclick="filterByDiv('${esc(d.division_name)}')">
            <div class="ls-tm-val">${num(d.total_weight_kg)} <span style="font-size:9px;opacity:.7">kg</span></div>
            <div class="ls-tm-name">${esc(shortName)}</div>
        </div>`;
    }).join('');
}

// ========== TOP CHARTS ==========
function renderTopCharts(topW, divs) {
    // Top by weight
    const cw = document.getElementById('chartTopWeight');
    if (!topW || !topW.length) { cw.innerHTML = '<div class="ls-empty"><p>ไม่มีข้อมูล</p></div>'; return; }
    const maxW = Math.max(...topW.map(t => parseFloat(t.total_weight_kg)));
    cw.innerHTML = topW.map((t, i) => {
        const pct = (parseFloat(t.total_weight_kg) / maxW * 100);
        const color = COLORS[i % COLORS.length];
        return `<div class="ls-hbar">
            <div class="ls-hbar-header">
                <span class="ls-hbar-name" title="${esc(t.store_name)}">${esc(t.store_name)}</span>
                <span class="ls-hbar-val">${num(t.total_weight_kg)} kg</span>
            </div>
            <div class="ls-hbar-track"><div class="ls-hbar-fill" style="width:${pct}%;background:${color}"></div></div>
        </div>`;
    }).join('');

    // Top by bottles — derive from topW sorted by bottle_count
    const topB = [...topW].sort((a,b) => (b.bottle_count||0) - (a.bottle_count||0)).slice(0,10);
    const cb = document.getElementById('chartTopBottles');
    const maxB = Math.max(...topB.map(t => parseInt(t.bottle_count)||0));
    cb.innerHTML = topB.filter(t => parseInt(t.bottle_count) > 0).map((t, i) => {
        const pct = ((parseInt(t.bottle_count)||0) / maxB * 100);
        const color = COLORS[(i+3) % COLORS.length];
        return `<div class="ls-hbar">
            <div class="ls-hbar-header">
                <span class="ls-hbar-name" title="${esc(t.store_name)}">${esc(t.store_name)}</span>
                <span class="ls-hbar-val">${parseInt(t.bottle_count).toLocaleString()}</span>
            </div>
            <div class="ls-hbar-track"><div class="ls-hbar-fill" style="width:${pct}%;background:${color}"></div></div>
        </div>`;
    }).join('');
}

// ========== DIVISION CARDS ==========
function renderDivisionCards(divs, sections) {
    const grid = document.getElementById('divGrid');
    if (!divs || !divs.length) { grid.innerHTML = '<div class="ls-empty"><i class="fas fa-building"></i><p>ไม่มีข้อมูล</p></div>'; return; }
    const totalW = divs.reduce((s,d) => s + parseFloat(d.total_weight_kg||0), 0);
    grid.innerHTML = divs.map((d, i) => {
        const color = COLORS[i % COLORS.length];
        const pct = totalW > 0 ? (parseFloat(d.total_weight_kg) / totalW * 100) : 0;
        const secList = (sections||[]).filter(s => s.division_name === d.division_name);

        return `<div class="ls-div-card ls-fade" style="animation-delay:${i*0.04}s" onclick="toggleDiv(this)">
            <div class="ls-div-header">
                <div class="ls-div-icon" style="background:${color}15;color:${color}"><i class="fas fa-building"></i></div>
                <div class="ls-div-info">
                    <div class="ls-div-name">${esc(d.division_name)}</div>
                    <div class="ls-div-sub">${d.section_count||0} ${TH?'งาน':'sections'} · ${d.store_count||0} ${TH?'คลัง':'stores'}</div>
                </div>
                <i class="fas fa-chevron-down ls-div-arrow"></i>
            </div>
            <div class="ls-div-stats">
                <div class="ls-div-stat"><div class="ls-div-stat-val" style="color:${color}">${parseInt(d.total_bottles||0).toLocaleString()}</div><div class="ls-div-stat-lbl">${TH?'ขวด':'Bottles'}</div></div>
                <div class="ls-div-stat"><div class="ls-div-stat-val" style="color:#2563eb">${parseInt(d.total_chemicals||0).toLocaleString()}</div><div class="ls-div-stat-lbl">${TH?'สารเคมี':'Chemicals'}</div></div>
                <div class="ls-div-stat"><div class="ls-div-stat-val" style="color:#059669">${num(d.total_weight_kg)}</div><div class="ls-div-stat-lbl">kg</div></div>
            </div>
            <div class="ls-div-progress"><div class="ls-div-progress-bar" style="width:${pct}%;background:${color}"></div></div>
            <div class="ls-div-expand">
                <div class="ls-div-sections">
                    ${secList.map((s, si) => {
                        const dotColor = COLORS[(i*3 + si) % COLORS.length];
                        return `<div class="ls-sec-row">
                            <div class="ls-sec-dot" style="background:${dotColor}"></div>
                            <div class="ls-sec-name" title="${esc(s.section_name)}">${esc(s.section_name)}</div>
                            <div class="ls-sec-pills">
                                <span class="ls-sec-pill bot">${parseInt(s.total_bottles||0).toLocaleString()} ${TH?'ขวด':'bot'}</span>
                                <span class="ls-sec-pill chem">${parseInt(s.total_chemicals||0).toLocaleString()} ${TH?'สาร':'chem'}</span>
                                <span class="ls-sec-pill kg">${num(s.total_weight_kg)} kg</span>
                            </div>
                        </div>`;
                    }).join('')}
                    ${secList.length === 0 ? `<div style="text-align:center;padding:12px;color:#ccc;font-size:11px">${TH?'ไม่มีงานย่อย':'No sections'}</div>` : ''}
                </div>
            </div>
        </div>`;
    }).join('');
}

function toggleDiv(card) {
    card.classList.toggle('expanded');
}

// ========== STORE TABLE ==========
function onTblSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { tblPage = 1; loadTable(); }, 300);
}

async function loadTable() {
    const body = document.getElementById('tblBody');
    body.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#ccc"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    const params = new URLSearchParams({
        action: 'list', page: tblPage, per_page: 30,
        sort: tblSort, sort_dir: tblSortDir
    });
    const search = document.getElementById('tblSearch').value.trim();
    const div = document.getElementById('tblDivFilter').value;
    if (search) params.set('search', search);
    if (div) params.set('division', div);

    try {
        const d = await apiFetch(API + '?' + params);
        if (!d.success) throw new Error(d.error);
        const items = d.data.items || [];
        const pg = d.data.pagination;
        const maxW = items.length ? Math.max(...items.map(r => parseFloat(r.total_weight_kg)||0)) : 1;

        if (!items.length) {
            body.innerHTML = `<tr><td colspan="6"><div class="ls-empty"><i class="fas fa-store"></i><p>${TH?'ไม่พบข้อมูลคลัง':'No stores found'}</p></div></td></tr>`;
        } else {
            body.innerHTML = items.map(r => {
                const w = parseFloat(r.total_weight_kg)||0;
                const pct = maxW > 0 ? (w/maxW*100) : 0;
                const colorIdx = (dashData?.divisionSummary||[]).findIndex(d => d.division_name === r.division_name);
                const color = COLORS[(colorIdx >= 0 ? colorIdx : 0) % COLORS.length];
                return `<tr>
                    <td><div class="ls-store-name" title="${esc(r.store_name)}">${esc(r.store_name)}</div></td>
                    <td><span class="ls-badge div">${esc((r.division_name||'').replace(/ฝ่าย/,'').substring(0,25))}</span></td>
                    <td><span class="ls-badge sec">${esc((r.section_name||'').substring(0,30))}</span></td>
                    <td style="text-align:right;font-weight:700">${parseInt(r.bottle_count||0).toLocaleString()}</td>
                    <td style="text-align:right;font-weight:700">${parseInt(r.chemical_count||0).toLocaleString()}</td>
                    <td>
                        <div class="ls-bar-cell">
                            <div class="ls-bar-val">${num(r.total_weight_kg)}</div>
                            <div class="ls-bar" style="margin-top:14px"><div class="ls-bar-fill" style="width:${pct}%;background:${color}"></div></div>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        // Pagination
        const pgEl = document.getElementById('tblPagination');
        if (pg && pg.total_pages > 1) {
            pgEl.innerHTML = `
                <button ${pg.page<=1?'disabled':''} onclick="tblPage=${pg.page-1};loadTable()"><i class="fas fa-chevron-left"></i></button>
                <span class="pg-info">${pg.page} / ${pg.total_pages} (${pg.total} ${TH?'รายการ':'items'})</span>
                <button ${pg.page>=pg.total_pages?'disabled':''} onclick="tblPage=${pg.page+1};loadTable()"><i class="fas fa-chevron-right"></i></button>`;
        } else {
            pgEl.innerHTML = pg ? `<span class="pg-info">${pg.total} ${TH?'รายการ':'items'}</span>` : '';
        }

        // Update sort indicators
        const ths = document.querySelectorAll('#storeTable th');
        ths.forEach(th => {
            th.classList.remove('sorted');
            const si = th.querySelector('.sort-icon');
            if (si) si.textContent = '';
        });
        const sortIdx = ['store_name','division_name','section_name','bottle_count','chemical_count','total_weight_kg'].indexOf(tblSort);
        if (sortIdx >= 0 && ths[sortIdx]) {
            ths[sortIdx].classList.add('sorted');
            const si = ths[sortIdx].querySelector('.sort-icon');
            if (si) si.textContent = tblSortDir === 'asc' ? '▲' : '▼';
        }
    } catch(e) {
        body.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#dc2626">${esc(e.message)}</td></tr>`;
    }
}

function sortTable(col) {
    if (tblSort === col) {
        tblSortDir = tblSortDir === 'desc' ? 'asc' : 'desc';
    } else {
        tblSort = col;
        tblSortDir = 'desc';
    }
    tblPage = 1;
    loadTable();
}

function populateDivFilter(divs) {
    const sel = document.getElementById('tblDivFilter');
    (divs||[]).forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.division_name;
        opt.textContent = d.division_name;
        sel.appendChild(opt);
    });
}

function filterByDiv(name) {
    switchTab('table');
    document.getElementById('tblDivFilter').value = name;
    tblPage = 1;
    loadTable();
}

// ========== REPORT ==========
function renderReport(divs) {
    if (!divs || !divs.length) return;
    const totalW = divs.reduce((s,d) => s + parseFloat(d.total_weight_kg||0), 0);
    const maxW = Math.max(...divs.map(d => parseFloat(d.total_weight_kg||0)));

    // Chart
    const chart = document.getElementById('reportDivisionChart');
    chart.innerHTML = divs.map((d, i) => {
        const pct = maxW > 0 ? (parseFloat(d.total_weight_kg)/maxW*100) : 0;
        const color = COLORS[i % COLORS.length];
        return `<div class="ls-hbar">
            <div class="ls-hbar-header">
                <span class="ls-hbar-name" title="${esc(d.division_name)}">${esc(d.division_name)}</span>
                <span class="ls-hbar-val">${num(d.total_weight_kg)} kg · ${parseInt(d.total_bottles||0).toLocaleString()} ${TH?'ขวด':'bottles'}</span>
            </div>
            <div class="ls-hbar-track"><div class="ls-hbar-fill" style="width:${pct}%;background:${color}"></div></div>
        </div>`;
    }).join('');

    // Table
    const tbody = document.getElementById('reportBody');
    tbody.innerHTML = divs.map((d, i) => {
        const w = parseFloat(d.total_weight_kg||0);
        const share = totalW > 0 ? (w/totalW*100) : 0;
        const color = COLORS[i % COLORS.length];
        return `<tr>
            <td><div style="display:flex;align-items:center;gap:8px"><div style="width:10px;height:10px;border-radius:3px;background:${color}"></div><span style="font-weight:700">${esc(d.division_name)}</span></div></td>
            <td style="text-align:right;font-weight:600">${d.store_count}</td>
            <td style="text-align:right;font-weight:600">${parseInt(d.total_bottles||0).toLocaleString()}</td>
            <td style="text-align:right;font-weight:600">${parseInt(d.total_chemicals||0).toLocaleString()}</td>
            <td style="text-align:right;font-weight:700">${num(d.total_weight_kg)}</td>
            <td>
                <div class="ls-bar-cell">
                    <div class="ls-bar-val">${share.toFixed(1)}%</div>
                    <div class="ls-bar" style="margin-top:14px"><div class="ls-bar-fill" style="width:${share}%;background:${color}"></div></div>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ========== IMPORT CSV ==========
async function importCSV() {
    if (!confirm(TH ? 'นำเข้าข้อมูลจากไฟล์ CSV จะลบข้อมูลเดิมทั้งหมด ต้องการดำเนินการ?' : 'Import from CSV will replace all existing data. Continue?')) return;
    try {
        const d = await apiFetch(API + '?action=import_csv', { method: 'POST', body: '{}' });
        if (!d.success) throw new Error(d.error);
        const r = d.data;
        showToast((TH?'นำเข้าสำเร็จ ':'Imported ') + r.inserted + (TH?' รายการ':' records'), 'success');
        loadDashboard();
        if (currentTab === 'table') loadTable();
    } catch(e) {
        alert(e.message);
    }
}
</script>
<?php Layout::footer(); ?>
</body>
</html>
