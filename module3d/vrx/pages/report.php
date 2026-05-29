<?php
require_once __DIR__ . '/../core/config.php';
require_login();
$user = auth_user();
$pdo  = db();

// Load QR settings
$qrRaw = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'qr_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$qrConf = [
    'base_url'    => $qrRaw['qr_base_url'] ?? '',
    'pattern_ar'  => $qrRaw['qr_pattern_ar'] ?? '{origin}{base}/pages/ar.php?src={file_url}',
    'pattern_3d'  => $qrRaw['qr_pattern_3d'] ?? '{origin}{base}/pages/viewer.php?src={file_url}',
    'pattern_pano'=> $qrRaw['qr_pattern_pano'] ?? '{origin}{base}/pages/panorama.php?src={file_url}',
    'pattern_embed'=> $qrRaw['qr_pattern_embed'] ?? '{origin}{base}/pages/viewer.php?mode=embed&embed={file_url}',
    'size'        => (int)($qrRaw['qr_size'] ?? 250),
    'color_dark'  => $qrRaw['qr_color_dark'] ?? '#000000',
    'color_light' => $qrRaw['qr_color_light'] ?? '#ffffff',
    'error_level' => $qrRaw['qr_error_level'] ?? 'M',
];

// Fetch all files with owner info (admin sees all, user/viewer sees own)
$where = "f.status='active' AND f.deleted_at IS NULL";
if (!is_admin()) {
    $where .= " AND f.user_id = " . (int)$user['id'];
}

$files = $pdo->query("
    SELECT f.id, f.uuid, f.user_id, f.name, f.original_name, f.description,
           f.file_url, f.embed_src, f.source_type, f.embed_provider,
           f.mime_type, f.extension, f.file_size,
           f.ar_enabled, f.ar_scale, f.view_count, f.download_count,
           f.visibility, f.uploaded_at, f.updated_at,
           f.category_id,
           c.slug AS category_slug, c.name AS category_name,
           c.icon AS category_icon, c.color AS category_color,
           u.username, u.display_name AS owner_name, u.role AS owner_role
    FROM files f
    LEFT JOIN categories c ON c.id = f.category_id
    LEFT JOIN users u ON u.id = f.user_id
    WHERE $where
    ORDER BY f.id DESC
")->fetchAll();

// Categories for edit modal
$categories = $pdo->query("SELECT id, slug, name FROM categories ORDER BY sort_order")->fetchAll();

// Stats
$totalFiles  = count($files);
$totalViews  = array_sum(array_column($files, 'view_count'));
$totalSize   = array_sum(array_column($files, 'file_size'));
$catCounts   = [];
foreach ($files as $f) {
    $cat = $f['category_name'] ?? 'Unknown';
    $catCounts[$cat] = ($catCounts[$cat] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Report — VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
<style>
/* ═══════════════════════════════════════════════
   Report Page — Scoped Styles
   ═══════════════════════════════════════════════ */

/* Hero */
.report-hero {
  text-align:center; padding:28px 20px 22px;
  background:linear-gradient(135deg, rgba(108,92,231,.07) 0%, rgba(0,206,201,.05) 100%);
  border:1px solid var(--border); border-radius:var(--radius-lg);
  margin-bottom:28px;
}
.report-hero h1 {
  font-size:1.3rem; font-weight:700; margin:0 0 6px;
  display:flex; align-items:center; justify-content:center; gap:10px;
}
.report-hero p { font-size:.82rem; color:var(--text-secondary); margin:0; }

/* Stats row */
.stats-row {
  display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));
  gap:12px; margin-bottom:28px;
}
.stat-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:18px 16px;
  display:flex; align-items:center; gap:14px;
}
.stat-icon {
  width:42px; height:42px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0;
}
.stat-icon svg { width:20px; height:20px; }
.stat-value { font-size:1.3rem; font-weight:800; line-height:1; }
.stat-label { font-size:.72rem; color:var(--text-muted); margin-top:2px; }

/* Toolbar */
.report-toolbar {
  display:flex; flex-wrap:wrap; gap:10px; align-items:center;
  margin-bottom:20px; padding:14px 18px;
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg);
}
.report-toolbar input[type="text"] {
  flex:1; min-width:180px; background:var(--bg-input);
  border:1px solid var(--border); border-radius:8px;
  padding:8px 12px; color:var(--text); font-size:.85rem;
}
.report-toolbar input:focus {
  outline:none; border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(108,92,231,.12);
}
.report-toolbar select {
  background:var(--bg-input); border:1px solid var(--border);
  border-radius:8px; padding:8px 12px; color:var(--text);
  font-size:.82rem;
}

/* Table */
.report-table-wrap {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); overflow:hidden;
}
.report-table-scroll {
  overflow-x:auto;
}
.report-table {
  width:100%; border-collapse:collapse; font-size:.82rem;
}
.report-table thead {
  background:var(--bg-surface);
}
.report-table th {
  padding:12px 14px; text-align:left; font-weight:700;
  font-size:.72rem; text-transform:uppercase; letter-spacing:.5px;
  color:var(--text-muted); white-space:nowrap;
  border-bottom:2px solid var(--border);
  position:sticky; top:0; background:var(--bg-surface); z-index:2;
}
.report-table td {
  padding:12px 14px; border-bottom:1px solid var(--border);
  vertical-align:middle;
}
.report-table tbody tr {
  transition:background .15s;
}
.report-table tbody tr:hover {
  background:rgba(108,92,231,.04);
}
.report-table tbody tr:last-child td {
  border-bottom:none;
}

/* Cell helpers */
.cell-id {
  font-family:monospace; font-weight:700; color:var(--primary);
  font-size:.78rem;
}
.cell-model {
  display:flex; align-items:center; gap:10px;
  max-width:280px;
}
.cell-thumb {
  width:42px; height:42px; border-radius:8px; overflow:hidden;
  background:var(--bg-surface); border:1px solid var(--border);
  flex-shrink:0; display:flex; align-items:center; justify-content:center;
}
.cell-thumb img {
  width:100%; height:100%; object-fit:cover;
}
.cell-thumb svg { color:var(--text-muted); opacity:.4; }
.cell-name {
  font-weight:600; color:var(--text); line-height:1.3;
  overflow:hidden; text-overflow:ellipsis;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
}
.cell-ext {
  font-size:.68rem; color:var(--text-muted); font-family:monospace;
}
.cell-detail {
  font-size:.75rem; color:var(--text-secondary); line-height:1.5;
}
.cell-detail .tag {
  display:inline-flex; align-items:center; gap:4px;
  padding:2px 8px; border-radius:6px; font-size:.68rem;
  font-weight:600; white-space:nowrap;
}
.cell-owner {
  display:flex; align-items:center; gap:8px;
}
.owner-avatar {
  width:28px; height:28px; border-radius:50%;
  background:linear-gradient(135deg, var(--primary), var(--accent));
  display:flex; align-items:center; justify-content:center;
  font-weight:700; font-size:.68rem; color:#fff; flex-shrink:0;
}
.owner-info { line-height:1.3; }
.owner-name { font-weight:600; font-size:.8rem; }
.owner-role {
  font-size:.66rem; padding:1px 6px; border-radius:4px;
  display:inline-block; font-weight:600;
}
.role-admin { background:rgba(253,203,110,.15); color:var(--warning); }
.role-user  { background:rgba(108,92,231,.12); color:var(--primary); }
.role-viewer{ background:rgba(160,160,192,.12); color:var(--text-muted); }

.cell-time {
  font-size:.75rem; white-space:nowrap;
}
.cell-time .date { color:var(--text); font-weight:600; }
.cell-time .time { color:var(--text-muted); font-size:.7rem; }

.cell-qr {
  display:flex; align-items:center; justify-content:center;
}
.qr-mini {
  width:54px; height:54px; background:#fff; border-radius:6px;
  padding:3px; cursor:pointer; transition:transform .2s, box-shadow .2s;
}
.qr-mini:hover {
  transform:scale(1.6); box-shadow:var(--shadow);
  z-index:10; position:relative;
}
.qr-mini canvas { width:100%!important; height:100%!important; }

/* Print button */
.btn-print {
  background:none; border:1px solid var(--border); border-radius:8px;
  padding:8px 14px; color:var(--text-secondary); font-size:.82rem;
  cursor:pointer; display:flex; align-items:center; gap:6px;
  transition:all .2s;
}
.btn-print:hover { border-color:var(--primary); color:var(--primary); }

/* Pagination info */
.table-footer {
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 18px; font-size:.78rem; color:var(--text-muted);
  border-top:1px solid var(--border);
}

/* QR Modal */
.qr-modal-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,.7);
  display:flex; align-items:center; justify-content:center; z-index:1000;
}
.qr-modal {
  background:var(--bg-card); border-radius:var(--radius-lg);
  padding:28px; max-width:380px; width:90%;
  border:1px solid var(--border); text-align:center;
  animation:resultSlide .3s ease;
}
@keyframes resultSlide {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:translateY(0); }
}
.qr-modal h3 { font-size:.95rem; margin-bottom:4px; }
.qr-modal .qr-subtitle { font-size:.72rem; color:var(--text-muted); margin-bottom:16px; }
.qr-modal .qr-output {
  background:#fff; border-radius:10px; padding:12px;
  display:inline-block; margin-bottom:16px;
}
.qr-modal .qr-actions { display:flex; gap:8px; justify-content:center; }

/* Visibility badges */
.vis-public   { color:var(--success); }
.vis-unlisted { color:var(--warning); }
.vis-private  { color:var(--danger); }

/* No results */
.no-results {
  text-align:center; padding:60px 20px; color:var(--text-muted);
}
.no-results svg { opacity:.2; margin-bottom:16px; }

/* Actions column — sticky last */
.cell-actions {
  display:flex; gap:6px; align-items:center; justify-content:center;
}
.act-btn {
  display:inline-flex; align-items:center; gap:4px;
  padding:5px 10px; border-radius:20px; border:1px solid var(--border);
  background:var(--bg-surface); color:var(--text-muted); cursor:pointer;
  font-size:.68rem; font-weight:600; letter-spacing:.2px;
  transition:all .2s; white-space:nowrap;
}
.act-btn svg { width:12px; height:12px; }
.act-btn.act-edit:hover {
  border-color:var(--primary); color:var(--primary);
  background:rgba(108,92,231,.08); box-shadow:0 2px 8px rgba(108,92,231,.12);
}
.act-btn.act-del:hover {
  border-color:var(--danger); color:var(--danger);
  background:rgba(225,112,85,.08); box-shadow:0 2px 8px rgba(225,112,85,.12);
}
.act-btn:disabled { opacity:.25; cursor:not-allowed; pointer-events:none; }

/* Sticky last col */
.report-table th.th-actions,
.report-table td.td-actions {
  position:sticky; right:0; z-index:3;
  background:var(--bg-surface);
  border-left:2px solid var(--border);
  text-align:center;
}
.report-table td.td-actions {
  background:var(--bg-card);
}
.report-table tbody tr:hover td.td-actions {
  background:rgba(108,92,231,.04);
}

/* Edit Modal */
.edit-modal-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,.7);
  display:flex; align-items:center; justify-content:center; z-index:1000;
}
.edit-modal {
  background:var(--bg-card); border-radius:var(--radius-lg);
  padding:0; max-width:520px; width:92%;
  border:1px solid var(--border);
  animation:resultSlide .3s ease;
  max-height:90vh; overflow-y:auto;
}
.edit-modal-header {
  padding:20px 24px 16px; border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
}
.edit-modal-header h3 { font-size:.95rem; margin:0; display:flex; align-items:center; gap:8px; }
.edit-modal-header .close-btn {
  background:none; border:none; color:var(--text-muted); cursor:pointer;
  padding:4px; border-radius:6px; transition:all .15s;
}
.edit-modal-header .close-btn:hover { color:var(--text); background:var(--bg-surface); }
.edit-modal-body { padding:20px 24px; }
.edit-field { margin-bottom:16px; }
.edit-field label {
  display:block; font-size:.75rem; font-weight:600; color:var(--text-muted);
  text-transform:uppercase; letter-spacing:.3px; margin-bottom:6px;
}
.edit-field input, .edit-field select, .edit-field textarea {
  width:100%; background:var(--bg-input); border:1px solid var(--border);
  border-radius:8px; padding:10px 12px; color:var(--text); font-size:.85rem;
  font-family:inherit;
}
.edit-field input:focus, .edit-field select:focus, .edit-field textarea:focus {
  outline:none; border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(108,92,231,.12);
}
.edit-field textarea { resize:vertical; min-height:80px; }
.edit-field .field-row {
  display:grid; grid-template-columns:1fr 1fr; gap:10px;
}
.edit-modal-footer {
  padding:16px 24px 20px; border-top:1px solid var(--border);
  display:flex; gap:8px; justify-content:flex-end;
}

/* Delete Confirm */
.confirm-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,.7);
  display:flex; align-items:center; justify-content:center; z-index:1100;
}
.confirm-box {
  background:var(--bg-card); border-radius:var(--radius-lg);
  padding:28px; max-width:380px; width:90%; text-align:center;
  border:1px solid var(--border); animation:resultSlide .3s ease;
}
.confirm-box .confirm-icon {
  width:56px; height:56px; border-radius:50%; margin:0 auto 16px;
  background:rgba(225,112,85,.1); display:flex; align-items:center; justify-content:center;
}
.confirm-box .confirm-icon svg { width:28px; height:28px; color:var(--danger); }
.confirm-box h3 { font-size:1rem; margin:0 0 8px; }
.confirm-box p { font-size:.82rem; color:var(--text-secondary); margin:0 0 20px; line-height:1.5; }
.confirm-box .confirm-name { color:var(--primary); font-weight:700; }
.confirm-actions { display:flex; gap:10px; justify-content:center; }

/* Button helpers */
.btn-danger { background:var(--danger); color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:.82rem; cursor:pointer; font-weight:600; transition:all .15s; }
.btn-danger:hover { filter:brightness(1.1); }
.btn-save { background:var(--primary); color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:.82rem; cursor:pointer; font-weight:600; transition:all .15s; }
.btn-save:hover { filter:brightness(1.1); }
.btn-cancel { background:none; border:1px solid var(--border); border-radius:8px; padding:8px 18px; font-size:.82rem; cursor:pointer; color:var(--text-secondary); transition:all .15s; }
.btn-cancel:hover { border-color:var(--text-muted); }
.btn:disabled, .btn-save:disabled, .btn-danger:disabled { opacity:.5; cursor:not-allowed; }

/* Print styles */
@media print {
  .header, .bottom-nav, .report-toolbar, .btn-print,
  .report-hero, .stats-row, .toast-container, .qr-modal-overlay,
  .edit-modal-overlay, .confirm-overlay, .cell-actions { display:none!important; }
  .page-content { padding:0!important; }
  .report-table-wrap { border:1px solid #ccc; }
  .report-table th { background:#f5f5f5!important; color:#333!important; }
  .report-table td { color:#333!important; }
  body { background:#fff!important; color:#333!important; }
  .qr-mini { transform:none!important; }
}

@media (max-width:768px) {
  .stats-row { grid-template-columns:repeat(2, 1fr); }
  .report-toolbar { flex-direction:column; }
  .report-toolbar input[type="text"] { min-width:100%; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-content">
<div class="container" id="app" v-cloak>

  <!-- Hero -->
  <div class="report-hero">
    <h1>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
      </svg>
      Report
    </h1>
    <p>รายงานข้อมูลทรัพยากรทั้งหมด<?= is_admin() ? '' : ' (เฉพาะของฉัน)' ?></p>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(108,92,231,.12);">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
      </div>
      <div>
        <div class="stat-value"><?= number_format($totalFiles) ?></div>
        <div class="stat-label">ไฟล์ทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(0,206,201,.12);">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      </div>
      <div>
        <div class="stat-value"><?= number_format($totalViews) ?></div>
        <div class="stat-label">การดูรวม</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(0,184,148,.12);">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      </div>
      <div>
        <div class="stat-value"><?= $catCounts['3D Models'] ?? 0 ?></div>
        <div class="stat-label">3D Models</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(225,112,85,.12);">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      </div>
      <div>
        <div class="stat-value"><?= $catCounts['Embeds'] ?? 0 ?></div>
        <div class="stat-label">Embeds</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(253,203,110,.12);">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
      </div>
      <div>
        <div class="stat-value"><?= $catCounts['Panorama'] ?? 0 ?></div>
        <div class="stat-label">Panorama</div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="report-toolbar">
    <input type="text" v-model="search" placeholder="🔍 ค้นหาชื่อ, รายละเอียด, เจ้าของ...">
    <select v-model="filterCat">
      <option value="">ทุกหมวดหมู่</option>
      <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
    </select>
    <select v-model="filterVis">
      <option value="">ทุกสถานะ</option>
      <option value="public">🌐 Public</option>
      <option value="unlisted">🔗 Unlisted</option>
      <option value="private">🔒 Private</option>
    </select>
    <select v-model="sortBy">
      <option value="id_desc">ID ↓ (ล่าสุด)</option>
      <option value="id_asc">ID ↑ (เก่าสุด)</option>
      <option value="name_asc">ชื่อ A→Z</option>
      <option value="name_desc">ชื่อ Z→A</option>
      <option value="views_desc">ดูมากสุด</option>
      <option value="date_desc">วันที่ ↓</option>
    </select>
    <button class="btn-print" onclick="window.print()">
      <i data-feather="printer"></i> พิมพ์
    </button>
    <button class="btn-print" @click="exportCSV()">
      <i data-feather="download"></i> CSV
    </button>
  </div>

  <!-- Table -->
  <div class="report-table-wrap" v-if="filtered.length">
    <div class="report-table-scroll">
      <table class="report-table">
        <thead>
          <tr>
            <th style="width:50px;">ID</th>
            <th style="min-width:200px;">Model</th>
            <th style="min-width:180px;">Detail</th>
            <th style="min-width:130px;">Owner</th>
            <th style="min-width:110px;">Time</th>
            <th style="width:70px;text-align:center;">QR</th>
            <th v-if="canEdit" class="th-actions" style="width:130px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in filtered" :key="f.id">
            <!-- ID -->
            <td><span class="cell-id">#{{ f.id }}</span></td>

            <!-- Model -->
            <td>
              <div class="cell-model">
                <div class="cell-thumb">
                  <img v-if="f.thumbnail" :src="f.thumbnail" :alt="f.name">
                  <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  </svg>
                </div>
                <div>
                  <div class="cell-name">{{ f.name }}</div>
                  <div class="cell-ext">{{ f.extension || f.source_type }}</div>
                </div>
              </div>
            </td>

            <!-- Detail -->
            <td>
              <div class="cell-detail">
                <span class="tag" :style="'background:' + (f.category_color || '#444') + '18; color:' + (f.category_color || '#aaa')">
                  <i :data-feather="f.category_icon || 'file'" style="width:10px;height:10px;"></i>
                  {{ f.category_name || '-' }}
                </span><br>
                <span v-if="f.file_size > 0" style="font-size:.7rem;color:var(--text-muted);">{{ fmtSize(f.file_size) }} · </span>
                <span style="font-size:.7rem;" :class="'vis-' + f.visibility">{{ visLabel(f.visibility) }}</span>
                <span v-if="f.view_count > 0" style="font-size:.7rem;color:var(--text-muted);"> · 👁 {{ f.view_count }}</span>
                <span v-if="f.ar_enabled" style="font-size:.7rem;color:var(--accent);"> · AR</span>
              </div>
            </td>

            <!-- Owner -->
            <td>
              <div class="cell-owner">
                <div class="owner-avatar">{{ (f.owner_name || f.username || '?')[0].toUpperCase() }}</div>
                <div class="owner-info">
                  <div class="owner-name">{{ f.owner_name || f.username }}</div>
                  <span class="owner-role" :class="'role-' + f.owner_role">{{ f.owner_role }}</span>
                </div>
              </div>
            </td>

            <!-- Time -->
            <td>
              <div class="cell-time">
                <div class="date">{{ fmtDate(f.uploaded_at) }}</div>
                <div class="time">{{ fmtTime(f.uploaded_at) }}</div>
              </div>
            </td>

            <!-- QR -->
            <td class="cell-qr">
              <div class="qr-mini" @click="showQR(f)" :id="'qr-' + f.id"></div>
            </td>

            <!-- Actions (last) -->
            <td v-if="canEdit" class="td-actions">
              <div class="cell-actions">
                <button class="act-btn act-edit" @click="openEdit(f)"
                        :disabled="!canEditFile(f)">
                  <i data-feather="edit-2"></i> Edit
                </button>
                <button class="act-btn act-del" @click="confirmDelete(f)"
                        :disabled="!canEditFile(f)">
                  <i data-feather="trash-2"></i> Del
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="table-footer">
      <span>แสดง {{ filtered.length }} จาก {{ allFiles.length }} รายการ</span>
      <span>ขนาดรวม: {{ fmtSize(totalFilteredSize) }}</span>
    </div>
  </div>

  <!-- No results -->
  <div class="no-results" v-else>
    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
      <polyline points="14 2 14 8 20 8"/>
    </svg>
    <p style="font-size:.92rem;">ไม่พบข้อมูล</p>
    <p style="font-size:.78rem;">ลองเปลี่ยนตัวกรองหรือคำค้นหา</p>
  </div>

  <!-- QR Modal -->
  <div class="qr-modal-overlay" v-if="qrModal" @click.self="qrModal=null">
    <div class="qr-modal">
      <h3>{{ qrModal.name }}</h3>
      <div class="qr-subtitle">ID #{{ qrModal.id }} · {{ qrModal.category_name }}</div>
      <div class="qr-output" id="qr-modal-output"></div>
      <div style="font-family:monospace;font-size:.68rem;color:var(--text-muted);word-break:break-all;margin-bottom:14px;">
        {{ qrModalUrl }}
      </div>
      <div class="qr-actions">
        <button class="btn btn-primary btn-sm" @click="downloadQR()">
          <i data-feather="download"></i> ดาวน์โหลด
        </button>
        <button class="btn btn-outline btn-sm" @click="copyQRUrl()">
          <i data-feather="copy"></i> คัดลอก URL
        </button>
        <button class="btn btn-outline btn-sm" @click="qrModal=null">
          <i data-feather="x"></i> ปิด
        </button>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="edit-modal-overlay" v-if="editModal" @click.self="closeEdit()">
    <div class="edit-modal">
      <div class="edit-modal-header">
        <h3>
          <i data-feather="edit-2" style="width:16px;height:16px;"></i>
          แก้ไข #{{ editForm.id }}
        </h3>
        <button class="close-btn" @click="closeEdit()">
          <i data-feather="x" style="width:18px;height:18px;"></i>
        </button>
      </div>
      <div class="edit-modal-body">
        <div class="edit-field">
          <label>ชื่อ</label>
          <input type="text" v-model="editForm.name" placeholder="ชื่อไฟล์">
        </div>
        <div class="edit-field">
          <label>รายละเอียด</label>
          <textarea v-model="editForm.description" placeholder="คำอธิบาย..."></textarea>
        </div>
        <div class="edit-field">
          <div class="field-row">
            <div>
              <label>หมวดหมู่</label>
              <select v-model="editForm.category">
                <option value="">-- ไม่ระบุ --</option>
                <option v-for="c in allCategories" :key="c.slug" :value="c.slug">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label>การมองเห็น</label>
              <select v-model="editForm.visibility">
                <option value="public">🌐 Public</option>
                <option value="unlisted">🔗 Unlisted</option>
                <option value="private">🔒 Private</option>
              </select>
            </div>
          </div>
        </div>
        <div class="edit-field" v-if="editForm.source_type === 'embed'">
          <label>Embed URL</label>
          <input type="text" v-model="editForm.embed_src" placeholder="https://...">
        </div>
        <div class="edit-field">
          <div class="field-row">
            <div>
              <label>AR</label>
              <select v-model="editForm.ar_enabled">
                <option :value="1">✅ เปิดใช้งาน</option>
                <option :value="0">❌ ปิด</option>
              </select>
            </div>
            <div>
              <label>AR Scale</label>
              <input type="text" v-model="editForm.ar_scale" placeholder="1 1 1">
            </div>
          </div>
        </div>
      </div>
      <div class="edit-modal-footer">
        <button class="btn-cancel" @click="closeEdit()">ยกเลิก</button>
        <button class="btn-save" @click="saveEdit()" :disabled="saving">
          <template v-if="saving">⏳ กำลังบันทึก...</template>
          <template v-else>💾 บันทึก</template>
        </button>
      </div>
    </div>
  </div>

  <!-- Delete Confirm -->
  <div class="confirm-overlay" v-if="deleteTarget" @click.self="deleteTarget=null">
    <div class="confirm-box">
      <div class="confirm-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
          <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
        </svg>
      </div>
      <h3>ยืนยันการลบ</h3>
      <p>คุณต้องการลบ <span class="confirm-name">{{ deleteTarget.name }}</span> (ID #{{ deleteTarget.id }}) หรือไม่?<br>การกระทำนี้ไม่สามารถย้อนกลับได้</p>
      <div class="confirm-actions">
        <button class="btn-cancel" @click="deleteTarget=null">ยกเลิก</button>
        <button class="btn-danger" @click="doDelete()" :disabled="deleting">
          <template v-if="deleting">⏳ กำลังลบ...</template>
          <template v-else>🗑 ลบเลย</template>
        </button>
      </div>
    </div>
  </div>

</div>
</div>

<?php include __DIR__ . '/../includes/bottom_nav.php'; ?>
<div class="toast-container" id="toast-container"></div>

<script src="<?= BASE_URL ?>/third_party/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/third_party/vue.min.js"></script>
<script>
var BASE = '<?= BASE_URL ?>';
var QR_CONF = <?= json_encode($qrConf, JSON_UNESCAPED_UNICODE) ?>;
var ALL_FILES = <?= json_encode($files, JSON_UNESCAPED_UNICODE) ?>;
var AUTH_USER = <?= json_encode(['id' => $user['id'], 'role' => $user['role']], JSON_UNESCAPED_UNICODE) ?>;
var ALL_CATEGORIES = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;

function showToast(msg, type) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast ' + (type || '');
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(function(){ t.remove(); }, 3000);
}

new Vue({
  el: '#app',
  data: function() {
    return {
      allFiles: ALL_FILES,
      allCategories: ALL_CATEGORIES,
      authUser: AUTH_USER,
      search: '',
      filterCat: '',
      filterVis: '',
      sortBy: 'id_desc',
      qrModal: null,
      qrModalUrl: '',
      editModal: false,
      editForm: {},
      editOriginal: null,
      saving: false,
      deleteTarget: null,
      deleting: false
    };
  },

  computed: {
    categories: function() {
      var cats = {};
      this.allFiles.forEach(function(f) { if (f.category_name) cats[f.category_name] = true; });
      return Object.keys(cats).sort();
    },
    filtered: function() {
      var vm = this;
      var list = vm.allFiles.slice();

      // Search
      if (vm.search) {
        var q = vm.search.toLowerCase();
        list = list.filter(function(f) {
          return (f.name || '').toLowerCase().indexOf(q) >= 0 ||
                 (f.description || '').toLowerCase().indexOf(q) >= 0 ||
                 (f.owner_name || '').toLowerCase().indexOf(q) >= 0 ||
                 (f.username || '').toLowerCase().indexOf(q) >= 0 ||
                 (f.category_name || '').toLowerCase().indexOf(q) >= 0 ||
                 String(f.id) === q;
        });
      }
      // Filter category
      if (vm.filterCat) {
        list = list.filter(function(f) { return f.category_name === vm.filterCat; });
      }
      // Filter visibility
      if (vm.filterVis) {
        list = list.filter(function(f) { return f.visibility === vm.filterVis; });
      }
      // Sort
      list.sort(function(a, b) {
        switch (vm.sortBy) {
          case 'id_asc':    return a.id - b.id;
          case 'name_asc':  return (a.name || '').localeCompare(b.name || '');
          case 'name_desc': return (b.name || '').localeCompare(a.name || '');
          case 'views_desc':return (b.view_count || 0) - (a.view_count || 0);
          case 'date_desc': return (b.uploaded_at || '').localeCompare(a.uploaded_at || '');
          default:          return b.id - a.id;
        }
      });
      return list;
    },
    totalFilteredSize: function() {
      return this.filtered.reduce(function(s, f) { return s + (f.file_size || 0); }, 0);
    },
    canEdit: function() {
      return this.authUser.role === 'admin' || this.authUser.role === 'user';
    }
  },

  methods: {
    canEditFile: function(f) {
      if (this.authUser.role === 'admin') return true;
      if (this.authUser.role === 'user') return parseInt(f.user_id) === parseInt(this.authUser.id);
      return false;
    },

    openEdit: function(f) {
      if (!this.canEditFile(f)) return;
      this.editOriginal = f;
      this.editForm = {
        id: f.id,
        name: f.name || '',
        description: f.description || '',
        category: f.category_slug || '',
        visibility: f.visibility || 'public',
        embed_src: f.embed_src || '',
        ar_enabled: f.ar_enabled ? 1 : 0,
        ar_scale: f.ar_scale || '1 1 1',
        source_type: f.source_type || ''
      };
      this.editModal = true;
      this.$nextTick(function() { feather.replace(); });
    },

    closeEdit: function() {
      this.editModal = false;
      this.editForm = {};
      this.editOriginal = null;
    },

    saveEdit: function() {
      var vm = this;
      if (vm.saving) return;
      if (!vm.editForm.name) { showToast('กรุณากรอกชื่อ', 'error'); return; }
      vm.saving = true;

      var payload = {
        name: vm.editForm.name,
        description: vm.editForm.description,
        category: vm.editForm.category,
        visibility: vm.editForm.visibility,
        ar_enabled: vm.editForm.ar_enabled ? 1 : 0,
        ar_scale: vm.editForm.ar_scale
      };
      if (vm.editForm.source_type === 'embed') {
        payload.embed_src = vm.editForm.embed_src;
      }

      fetch(BASE + '/api/?action=files&id=' + vm.editForm.id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
      .then(function(res) {
        vm.saving = false;
        if (res.ok) {
          // Update local data
          var orig = vm.editOriginal;
          orig.name = vm.editForm.name;
          orig.description = vm.editForm.description;
          orig.visibility = vm.editForm.visibility;
          orig.ar_enabled = vm.editForm.ar_enabled ? 1 : 0;
          orig.ar_scale = vm.editForm.ar_scale;
          if (vm.editForm.source_type === 'embed') {
            orig.embed_src = vm.editForm.embed_src;
          }
          // Update category display
          if (vm.editForm.category) {
            var cat = vm.allCategories.find(function(c) { return c.slug === vm.editForm.category; });
            if (cat) {
              orig.category_slug = cat.slug;
              orig.category_name = cat.name;
            }
          } else {
            orig.category_slug = '';
            orig.category_name = '';
          }
          orig.updated_at = new Date().toISOString().replace('T', ' ').substring(0, 19);
          vm.closeEdit();
          showToast('บันทึกสำเร็จ!', 'success');
        } else {
          showToast(res.data.error || 'เกิดข้อผิดพลาด', 'error');
        }
      })
      .catch(function(e) {
        vm.saving = false;
        showToast('เกิดข้อผิดพลาด: ' + e.message, 'error');
      });
    },

    confirmDelete: function(f) {
      if (!this.canEditFile(f)) return;
      this.deleteTarget = f;
      this.$nextTick(function() { feather.replace(); });
    },

    doDelete: function() {
      var vm = this;
      if (vm.deleting || !vm.deleteTarget) return;
      vm.deleting = true;

      fetch(BASE + '/api/?action=files&id=' + vm.deleteTarget.id, {
        method: 'DELETE'
      })
      .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
      .then(function(res) {
        vm.deleting = false;
        if (res.ok) {
          var idx = vm.allFiles.indexOf(vm.deleteTarget);
          if (idx >= 0) vm.allFiles.splice(idx, 1);
          showToast('ลบสำเร็จ! (#' + vm.deleteTarget.id + ')', 'success');
          vm.deleteTarget = null;
        } else {
          showToast(res.data.error || 'เกิดข้อผิดพลาด', 'error');
        }
      })
      .catch(function(e) {
        vm.deleting = false;
        showToast('เกิดข้อผิดพลาด: ' + e.message, 'error');
      });
    },
    fmtSize: function(bytes) {
      if (!bytes) return '-';
      var u = ['B','KB','MB','GB'];
      var i = 0;
      while (bytes >= 1024 && i < u.length - 1) { bytes /= 1024; i++; }
      return bytes.toFixed(i ? 1 : 0) + ' ' + u[i];
    },
    fmtDate: function(dt) {
      if (!dt) return '-';
      return dt.substring(0, 10);
    },
    fmtTime: function(dt) {
      if (!dt) return '';
      return dt.substring(11, 16);
    },
    visLabel: function(v) {
      return v === 'public' ? '🌐 Public' : v === 'unlisted' ? '🔗 Unlisted' : '🔒 Private';
    },

    buildQrUrl: function(f) {
      var origin = window.location.origin;
      var base = BASE;
      var fileUrl = f.source_type === 'embed' ? (f.embed_src || '') : (f.file_url || '');
      var fileUrlAbs = /^https?:\/\//i.test(fileUrl) ? fileUrl : origin + fileUrl;

      var pattern = '';
      if (f.source_type === 'embed') pattern = QR_CONF.pattern_embed;
      else if (f.category_slug === 'model')    pattern = QR_CONF.pattern_3d;
      else if (f.category_slug === 'panorama') pattern = QR_CONF.pattern_pano;
      else pattern = QR_CONF.pattern_3d;

      return pattern
        .replace(/{origin}/g, origin)
        .replace(/{base}/g, base)
        .replace(/{file_url_abs}/g, encodeURIComponent(fileUrlAbs))
        .replace(/{file_url}/g, encodeURIComponent(fileUrl));
    },

    renderMiniQR: function() {
      var vm = this;
      vm.$nextTick(function() {
        vm.filtered.forEach(function(f) {
          var el = document.getElementById('qr-' + f.id);
          if (!el || el.querySelector('canvas')) return;
          var url = vm.buildQrUrl(f);
          new QRCode(el, {
            text: url, width: 48, height: 48,
            colorDark: QR_CONF.color_dark,
            colorLight: QR_CONF.color_light,
            correctLevel: QRCode.CorrectLevel[QR_CONF.error_level] || QRCode.CorrectLevel.M
          });
        });
        feather.replace();
      });
    },

    showQR: function(f) {
      var vm = this;
      vm.qrModal = f;
      vm.qrModalUrl = vm.buildQrUrl(f);
      vm.$nextTick(function() {
        var el = document.getElementById('qr-modal-output');
        if (el) {
          el.innerHTML = '';
          new QRCode(el, {
            text: vm.qrModalUrl,
            width: QR_CONF.size, height: QR_CONF.size,
            colorDark: QR_CONF.color_dark,
            colorLight: QR_CONF.color_light,
            correctLevel: QRCode.CorrectLevel[QR_CONF.error_level] || QRCode.CorrectLevel.M
          });
        }
        feather.replace();
      });
    },

    downloadQR: function() {
      var el = document.querySelector('#qr-modal-output canvas');
      if (!el) return;
      var link = document.createElement('a');
      link.download = 'QR_' + (this.qrModal.name || 'code') + '.png';
      link.href = el.toDataURL('image/png');
      link.click();
    },

    copyQRUrl: function() {
      navigator.clipboard.writeText(this.qrModalUrl).then(function() {
        showToast('คัดลอก URL แล้ว!', 'success');
      });
    },

    exportCSV: function() {
      var rows = [['ID','Name','Category','Source Type','Extension','Size','Visibility','Views','AR','Owner','Role','Uploaded At']];
      this.filtered.forEach(function(f) {
        rows.push([
          f.id,
          '"' + (f.name || '').replace(/"/g, '""') + '"',
          f.category_name || '',
          f.source_type || '',
          f.extension || '',
          f.file_size || 0,
          f.visibility || '',
          f.view_count || 0,
          f.ar_enabled ? 'Yes' : 'No',
          f.owner_name || f.username || '',
          f.owner_role || '',
          f.uploaded_at || ''
        ]);
      });
      var csv = rows.map(function(r) { return r.join(','); }).join('\n');
      var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
      var link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'VRX_Report_' + new Date().toISOString().substring(0, 10) + '.csv';
      link.click();
    }
  },

  watch: {
    filtered: function() { this.renderMiniQR(); }
  },

  mounted: function() {
    this.renderMiniQR();
    feather.replace();
  },

  updated: function() {
    feather.replace();
  }
});
</script>
</body>
</html>
