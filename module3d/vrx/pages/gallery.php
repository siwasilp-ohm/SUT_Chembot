<?php
require_once __DIR__ . '/../core/config.php';
require_login();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>แกลเลอรี — VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
<script src="<?= BASE_URL ?>/third_party/three.js"></script>
<script src="<?= BASE_URL ?>/third_party/GLTFLoader.js"></script>
<style>
/* ── Preview Modal ── */
.preview-backdrop {
  position:fixed; inset:0; z-index:250;
  background:rgba(0,0,0,.88); backdrop-filter:blur(12px);
  display:flex; align-items:center; justify-content:center;
  opacity:0; pointer-events:none; transition:opacity .35s ease;
}
.preview-backdrop.visible { opacity:1; pointer-events:all; }

.preview-container {
  position:relative; width:94vw; height:88vh;
  max-width:1200px;
  background:rgba(18,18,32,.95);
  border:1px solid rgba(108,92,231,.3);
  border-radius:16px; overflow:hidden;
  display:flex; flex-direction:column;
  box-shadow:0 24px 80px rgba(0,0,0,.6), 0 0 0 1px rgba(108,92,231,.1);
  transform:scale(.92) translateY(20px);
  transition:transform .35s cubic-bezier(.4,0,.2,1);
}
.preview-backdrop.visible .preview-container {
  transform:scale(1) translateY(0);
}

.preview-header {
  display:flex; align-items:center; gap:12px;
  padding:14px 20px;
  background:rgba(15,15,26,.9);
  border-bottom:1px solid rgba(108,92,231,.15);
  flex-shrink:0;
}
.preview-header-icon {
  width:36px; height:36px; border-radius:10px;
  display:flex; align-items:center; justify-content:center;
  background:rgba(108,92,231,.15);
}
.preview-header-icon svg { width:18px; height:18px; stroke:var(--primary); fill:none; stroke-width:2; }
.preview-header-info { flex:1; min-width:0; }
.preview-header-title {
  font-size:.95rem; font-weight:600; color:var(--text);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.preview-header-meta {
  font-size:.75rem; color:var(--text-secondary);
  display:flex; gap:12px; margin-top:2px;
}
.preview-header-actions { display:flex; gap:8px; }
.preview-header-actions .btn {
  padding:6px 12px; font-size:.78rem;
  border-radius:8px;
}

.preview-body {
  flex:1; position:relative; overflow:hidden;
  display:flex; align-items:center; justify-content:center;
  background:radial-gradient(ellipse at center, rgba(108,92,231,.04) 0%, transparent 70%);
}
.preview-body canvas,
.preview-body img,
.preview-body video,
.preview-body iframe {
  width:100%!important; height:100%!important;
  display:block; border:none;
}
.preview-body img {
  object-fit:contain;
}

/* Panorama hint overlay */
.pano-hint {
  position:absolute; bottom:16px; left:50%; transform:translateX(-50%);
  display:flex; align-items:center; gap:8px;
  padding:8px 16px; border-radius:20px;
  background:rgba(0,0,0,.65); backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,.1);
  color:#fff; font-size:.75rem; font-weight:500;
  pointer-events:none; z-index:10;
  animation:panoHintFade 4s ease forwards;
  white-space:nowrap;
}
.pano-hint svg { width:16px; height:16px; opacity:.8; flex-shrink:0; }
@keyframes panoHintFade {
  0%,60% { opacity:1; }
  100% { opacity:0; }
}
.pano-badge {
  position:absolute; top:12px; right:12px;
  display:flex; align-items:center; gap:5px;
  padding:5px 10px; border-radius:12px;
  background:rgba(108,92,231,.2); backdrop-filter:blur(8px);
  border:1px solid rgba(108,92,231,.3);
  color:var(--primary); font-size:.7rem; font-weight:700;
  pointer-events:none; z-index:10;
}
.pano-badge svg { width:14px; height:14px; }
.pano-zoom-info {
  position:absolute; top:12px; left:12px;
  padding:4px 10px; border-radius:10px;
  background:rgba(0,0,0,.5); backdrop-filter:blur(6px);
  color:#fff; font-size:.68rem; font-weight:500;
  pointer-events:none; z-index:10;
  opacity:0; transition:opacity .3s;
}
.pano-zoom-info.show { opacity:1; }
  object-fit:contain;
}

.preview-close {
  position:absolute; top:12px; right:12px; z-index:10;
  width:36px; height:36px; border-radius:50%;
  background:rgba(0,0,0,.5); border:1px solid rgba(255,255,255,.15);
  color:#fff; font-size:20px; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:background .2s, transform .2s;
}
.preview-close:hover { background:rgba(220,53,69,.7); transform:scale(1.1); }

.preview-hint {
  position:absolute; bottom:16px; left:50%; transform:translateX(-50%);
  font-size:.72rem; color:rgba(255,255,255,.35);
  background:rgba(0,0,0,.4); padding:4px 14px; border-radius:20px;
  pointer-events:none;
}

.preview-loading {
  position:absolute; inset:0;
  display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  gap:12px; color:var(--text-secondary);
}
.preview-loading .spinner {
  width:40px; height:40px;
  border:3px solid var(--border); border-top-color:var(--primary);
  border-radius:50%; display:inline-block;
  animation:spin .8s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }

/* Responsive */
@media (max-width:600px) {
  .preview-container { width:100vw; height:100vh; border-radius:0; }
  .preview-header { padding:10px 14px; }
  .preview-header-actions .btn span { display:none; }
}

/* ── Card Live Preview Layer ── */
.card-live-layer {
  position:absolute; inset:0; z-index:2;
  overflow:hidden;
}
.card-live-layer canvas,
.card-live-layer video,
.card-live-layer iframe {
  width:100%!important; height:100%!important; display:block;
}

/* ── Detail Live Preview Layer ── */
.detail-live-layer {
  position:absolute; inset:0; z-index:2;
  overflow:hidden;
}
.detail-live-layer canvas,
.detail-live-layer video,
.detail-live-layer img,
.detail-live-layer iframe {
  width:100%!important; height:100%!important; display:block;
}
.detail-live-layer img { object-fit:contain; }
.detail-preview-fallback {
  position:absolute; inset:0; z-index:3;
  display:flex; align-items:center; justify-content:center;
}
.detail-preview-fallback img { width:100%; height:100%; object-fit:contain; }

/* ── Detail Play Overlay ── */
.detail-play-overlay {
  position:absolute; inset:0;
  display:flex; align-items:center; justify-content:center;
  background:rgba(0,0,0,.35);
  opacity:0; transition:opacity .25s ease;
  border-radius:var(--radius);
}
.detail-preview:hover .detail-play-overlay {
  opacity:1;
}
.detail-play-overlay svg {
  filter:drop-shadow(0 2px 8px rgba(0,0,0,.5));
  transition:transform .2s ease;
}
.detail-preview:hover .detail-play-overlay svg {
  transform:scale(1.15);
}

/* ── Edit Modal ── */
.edit-backdrop {
  position:fixed; inset:0; z-index:260;
  background:rgba(0,0,0,.8); backdrop-filter:blur(8px);
  display:flex; align-items:center; justify-content:center;
  padding:20px; opacity:0; pointer-events:none;
  transition:opacity .3s ease;
}
.edit-backdrop.visible { opacity:1; pointer-events:all; }

.edit-modal {
  background:var(--bg-card);
  border:1px solid rgba(108,92,231,.3);
  border-radius:16px; width:100%; max-width:520px;
  max-height:90vh; overflow-y:auto;
  box-shadow:0 24px 80px rgba(0,0,0,.5);
  transform:scale(.92) translateY(20px);
  transition:transform .3s cubic-bezier(.4,0,.2,1);
}
.edit-backdrop.visible .edit-modal {
  transform:scale(1) translateY(0);
}

.edit-modal-header {
  display:flex; align-items:center; gap:12px;
  padding:20px 24px 16px;
  border-bottom:1px solid var(--border);
}
.edit-modal-header h2 {
  font-size:1.05rem; font-weight:600; margin:0; flex:1;
  display:flex; align-items:center; gap:8px;
}
.edit-modal-header .close-btn {
  width:32px; height:32px; border-radius:8px;
  background:transparent; border:1px solid var(--border);
  color:var(--text-secondary); font-size:18px; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:all .2s;
}
.edit-modal-header .close-btn:hover {
  background:rgba(220,53,69,.1); color:var(--danger); border-color:var(--danger);
}

.edit-modal-body { padding:20px 24px; }

.edit-field { margin-bottom:18px; }
.edit-field label {
  display:block; font-size:.78rem; font-weight:600;
  color:var(--text-secondary); margin-bottom:6px;
  text-transform:uppercase; letter-spacing:.5px;
}
.edit-field input,
.edit-field select,
.edit-field textarea {
  width:100%; padding:10px 14px;
  background:var(--bg-surface); border:1px solid var(--border);
  border-radius:10px; color:var(--text); font-size:.88rem;
  transition:border-color .2s;
}
.edit-field input:focus,
.edit-field select:focus,
.edit-field textarea:focus {
  outline:none; border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(108,92,231,.15);
}
.edit-field textarea { resize:vertical; min-height:80px; }

.edit-field-row {
  display:grid; grid-template-columns:1fr 1fr; gap:14px;
}
@media (max-width:500px) {
  .edit-field-row { grid-template-columns:1fr; }
}

.edit-toggle {
  display:flex; align-items:center; gap:10px;
  padding:10px 14px;
  background:var(--bg-surface); border:1px solid var(--border);
  border-radius:10px; cursor:pointer;
}
.edit-toggle input[type=checkbox] {
  width:18px; height:18px; accent-color:var(--primary); cursor:pointer;
}
.edit-toggle span { font-size:.88rem; color:var(--text); }

.edit-modal-footer {
  display:flex; gap:10px; justify-content:flex-end;
  padding:16px 24px 20px;
  border-top:1px solid var(--border);
}
.edit-modal-footer .btn { min-width:100px; justify-content:center; }

.edit-id-badge {
  font-size:.7rem; color:var(--text-muted);
  background:var(--bg-surface); padding:2px 8px;
  border-radius:6px; font-weight:400;
}

/* Delete confirm popup */
.del-confirm-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,.7);
  display:flex; align-items:center; justify-content:center; z-index:2100;
  animation:fadeInDel .15s ease;
}
@keyframes fadeInDel { from { opacity:0; } to { opacity:1; } }
.del-confirm-box {
  background:var(--bg-card); border-radius:var(--radius-lg);
  border:1px solid var(--border); padding:32px 28px 24px;
  max-width:380px; width:90%; text-align:center;
  animation:slideUpDel .25s ease;
}
@keyframes slideUpDel { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
.del-confirm-icon {
  width:56px; height:56px; border-radius:50%; margin:0 auto 18px;
  background:rgba(225,112,85,.1);
  display:flex; align-items:center; justify-content:center;
}
.del-confirm-icon svg { width:28px; height:28px; color:var(--danger); stroke-width:2; }
.del-confirm-box h3 { font-size:1rem; margin:0 0 8px; font-weight:700; }
.del-confirm-box p { font-size:.82rem; color:var(--text-secondary); margin:0 0 22px; line-height:1.6; }
.del-confirm-box .del-name { color:var(--primary); font-weight:700; }
.del-confirm-actions { display:flex; gap:10px; justify-content:center; }
.btn-danger-solid {
  background:var(--danger); color:#fff; border:none; border-radius:8px;
  padding:9px 22px; font-size:.82rem; cursor:pointer; font-weight:600;
  display:inline-flex; align-items:center; gap:6px; transition:all .15s;
}
.btn-danger-solid:hover { filter:brightness(1.15); box-shadow:0 4px 12px rgba(225,112,85,.25); }
.btn-danger-solid:disabled { opacity:.5; cursor:not-allowed; }

/* Edit modal footer — space-between for delete on left */
.edit-modal-footer { justify-content:space-between!important; }
.edit-footer-left { display:flex; }
.edit-footer-right { display:flex; gap:10px; }
.btn-del-ghost {
  background:none; border:1px solid rgba(225,112,85,.3); border-radius:8px;
  padding:7px 14px; color:var(--danger); font-size:.8rem; cursor:pointer;
  display:inline-flex; align-items:center; gap:5px; font-weight:600;
  transition:all .2s;
}
.btn-del-ghost:hover {
  background:rgba(225,112,85,.08); border-color:var(--danger);
  box-shadow:0 2px 8px rgba(225,112,85,.12);
}

/* ═══ Pro Toolbar ═══ */
.g-toolbar {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:14px 18px;
  margin-bottom:20px;
}
.g-toolbar-top {
  display:flex; gap:10px; flex-wrap:wrap; align-items:center;
}
.g-toolbar-top input[type="search"] {
  flex:1; min-width:160px; background:var(--bg-input);
  border:1px solid var(--border); border-radius:8px;
  padding:8px 12px; color:var(--text); font-size:.85rem;
}
.g-toolbar-top input:focus {
  outline:none; border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(108,92,231,.12);
}
.g-toolbar-top select {
  background:var(--bg-input); border:1px solid var(--border);
  border-radius:8px; padding:8px 12px; color:var(--text); font-size:.82rem;
  max-width:160px;
}
.g-toolbar-bottom {
  display:flex; gap:10px; flex-wrap:wrap; align-items:center;
  margin-top:12px; padding-top:12px; border-top:1px solid var(--border);
}

/* View mode toggle */
.view-toggle {
  display:flex; background:var(--bg-surface); border:1px solid var(--border);
  border-radius:8px; overflow:hidden;
}
.view-toggle button {
  background:none; border:none; padding:6px 10px; cursor:pointer;
  color:var(--text-muted); display:flex; align-items:center;
  transition:all .15s; position:relative;
}
.view-toggle button svg { width:16px; height:16px; }
.view-toggle button:not(:last-child)::after {
  content:''; position:absolute; right:0; top:20%; height:60%;
  width:1px; background:var(--border);
}
.view-toggle button.active {
  background:var(--primary); color:#fff;
}
.view-toggle button:hover:not(.active) {
  background:rgba(108,92,231,.08); color:var(--primary);
}

/* User filter dropdown */
.user-filter {
  position:relative; min-width:160px;
}
.user-filter-btn {
  display:flex; align-items:center; gap:8px;
  padding:7px 12px; border-radius:8px;
  background:var(--bg-input); border:1px solid var(--border);
  color:var(--text); font-size:.82rem; cursor:pointer;
  transition:all .15s; width:100%; white-space:nowrap;
}
.user-filter-btn:hover { border-color:var(--primary); }
.user-filter-btn.active { border-color:var(--primary); box-shadow:0 0 0 3px rgba(108,92,231,.12); }
.user-filter-btn .uf-avatar {
  width:22px; height:22px; border-radius:50%; flex-shrink:0;
  background:linear-gradient(135deg,var(--primary),var(--accent));
  display:flex; align-items:center; justify-content:center;
  font-size:.6rem; color:#fff; font-weight:700;
}
.user-filter-btn .uf-label { flex:1; overflow:hidden; text-overflow:ellipsis; }
.user-filter-btn .uf-chevron {
  width:14px; height:14px; opacity:.5; transition:transform .2s; flex-shrink:0;
}
.user-filter-btn.active .uf-chevron { transform:rotate(180deg); }
.user-filter-btn .uf-clear {
  width:16px; height:16px; border-radius:50%; background:rgba(255,255,255,.1);
  display:flex; align-items:center; justify-content:center;
  font-size:.65rem; color:var(--text-muted); cursor:pointer;
  transition:all .15s; flex-shrink:0;
}
.user-filter-btn .uf-clear:hover { background:var(--danger); color:#fff; }

.user-filter-dropdown {
  position:absolute; top:calc(100% + 6px); left:0; right:0;
  min-width:240px;
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,.4);
  z-index:100; overflow:hidden;
  animation:ufSlide .15s ease;
}
@keyframes ufSlide { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
.user-filter-search {
  padding:10px 12px; border-bottom:1px solid var(--border);
}
.user-filter-search input {
  width:100%; background:var(--bg-surface); border:1px solid var(--border);
  border-radius:8px; padding:7px 10px; color:var(--text); font-size:.8rem;
}
.user-filter-search input:focus { outline:none; border-color:var(--primary); }
.user-filter-list {
  max-height:240px; overflow-y:auto; padding:6px 0;
}
.user-filter-list::-webkit-scrollbar { width:4px; }
.user-filter-list::-webkit-scrollbar-thumb { background:var(--border); border-radius:4px; }
.uf-item {
  display:flex; align-items:center; gap:10px;
  padding:8px 14px; cursor:pointer; transition:all .1s;
  font-size:.82rem; color:var(--text-secondary);
}
.uf-item:hover { background:rgba(108,92,231,.08); color:var(--text); }
.uf-item.selected { background:rgba(108,92,231,.12); color:var(--primary); font-weight:600; }
.uf-item .uf-avatar {
  width:28px; height:28px; border-radius:50%; flex-shrink:0;
  background:linear-gradient(135deg,var(--primary),var(--accent));
  display:flex; align-items:center; justify-content:center;
  font-size:.7rem; color:#fff; font-weight:700;
}
.uf-item.me-item .uf-avatar { background:linear-gradient(135deg,var(--success),var(--accent)); }
.uf-item.all-item .uf-avatar { background:linear-gradient(135deg,#667,#889); }
.uf-item .uf-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.uf-item .uf-role {
  font-size:.65rem; padding:2px 6px; border-radius:8px;
  background:rgba(108,92,231,.1); color:var(--primary);
}
.uf-item .uf-count {
  font-size:.7rem; color:var(--text-muted); flex-shrink:0;
}
.uf-empty {
  padding:20px; text-align:center; color:var(--text-muted); font-size:.8rem;
}

/* ═══ List View ═══ */
.file-list {
  display:flex; flex-direction:column; gap:8px;
}
.file-list-item {
  display:flex; align-items:center; gap:14px;
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius); padding:12px 16px;
  transition:all .15s; cursor:pointer;
}
.file-list-item:hover {
  border-color:var(--primary); background:rgba(108,92,231,.02);
}
.list-thumb {
  width:48px; height:48px; border-radius:10px; overflow:hidden;
  background:var(--bg-surface); flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  border:1px solid var(--border);
}
.list-thumb img { width:100%; height:100%; object-fit:cover; }
.list-thumb svg { width:22px; height:22px; color:var(--text-muted); opacity:.4; }
.list-info { flex:1; min-width:0; }
.list-title {
  font-weight:600; font-size:.88rem; color:var(--text);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.list-meta {
  display:flex; gap:10px; font-size:.72rem; color:var(--text-muted); margin-top:3px;
  flex-wrap:wrap;
}
.list-meta .badge { font-size:.66rem; padding:1px 7px; }
.list-actions {
  display:flex; gap:4px; flex-shrink:0;
}
.list-actions .act {
  width:30px; height:30px; border-radius:8px; border:1px solid var(--border);
  background:transparent; color:var(--text-muted); cursor:pointer;
  display:flex; align-items:center; justify-content:center; transition:all .15s;
}
.list-actions .act svg { width:14px; height:14px; }
.list-actions .act:hover { border-color:var(--primary); color:var(--primary); background:rgba(108,92,231,.06); }

/* ═══ Content View ═══ */
.file-content-grid {
  display:grid; grid-template-columns:repeat(auto-fill, minmax(420px, 1fr));
  gap:20px;
}
.file-content-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); overflow:hidden;
  display:flex; flex-direction:column; transition:all .2s;
}
.file-content-card:hover { border-color:var(--primary); box-shadow:var(--shadow); }
.content-preview {
  position:relative; height:220px; background:var(--bg-surface);
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; overflow:hidden;
}
.content-preview img { width:100%; height:100%; object-fit:cover; }
.content-preview .icon-placeholder { color:var(--text-muted); opacity:.4; }
.content-preview .icon-placeholder svg { width:56px; height:56px; }
.content-preview .file-card-badge { position:absolute; top:12px; left:12px; }
.content-preview .card-play-overlay {
  position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
  background:rgba(0,0,0,.35); opacity:0; transition:opacity .25s;
}
.content-preview:hover .card-play-overlay { opacity:1; }
.content-body {
  padding:18px 20px; flex:1;
}
.content-title {
  font-weight:700; font-size:1rem; margin-bottom:6px; color:var(--text);
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
  overflow:hidden;
}
.content-desc {
  font-size:.82rem; color:var(--text-secondary); line-height:1.6;
  display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical;
  overflow:hidden; margin-bottom:12px;
}
.content-meta-row {
  display:flex; gap:12px; flex-wrap:wrap; align-items:center;
  font-size:.75rem; color:var(--text-muted);
}
.content-meta-row .meta-item {
  display:flex; align-items:center; gap:4px;
}
.content-meta-row .meta-item svg { width:12px; height:12px; }
.content-footer {
  display:flex; gap:6px; padding:12px 20px;
  border-top:1px solid var(--border);
}
.content-footer .btn { flex:1; justify-content:center; }

@media (max-width:768px) {
  .g-toolbar-top { flex-direction:column; }
  .g-toolbar-top input[type="search"] { min-width:100%; }
  .user-filter { min-width:100%; }
  .user-filter-dropdown { min-width:100%; }
  .file-content-grid { grid-template-columns:1fr; }
}
@media (max-width:500px) {
  .file-list-item { padding:10px 12px; gap:10px; }
  .list-actions .act { width:26px; height:26px; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-content">
<div class="container" id="app" v-cloak>

  <!-- Stats Row -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-value">{{ stats.total_files || 0 }}</div>
      <div class="stat-label">ทั้งหมด</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ stats.models || 0 }}</div>
      <div class="stat-label">โมเดล</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ stats.panoramas || 0 }}</div>
      <div class="stat-label">พาโนรามา</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ stats.images || 0 }}</div>
      <div class="stat-label">รูปภาพ</div>
    </div>
  </div>

  <!-- Pro Toolbar -->
  <div class="g-toolbar">
    <div class="g-toolbar-top">
      <input type="search" v-model="search" @input="debounceLoad()" placeholder="🔍 ค้นหาชื่อ, รายละเอียด...">
      <div class="user-filter" v-click-outside="closeUserFilter">
        <div class="user-filter-btn" :class="{active: userFilterOpen}" @click="userFilterOpen = !userFilterOpen">
          <div class="uf-avatar" v-if="owner">
            <template v-if="owner==='me'">👤</template>
            <template v-else>{{ selectedUserInitial }}</template>
          </div>
          <span class="uf-label">{{ selectedUserLabel }}</span>
          <span class="uf-clear" v-if="owner" @click.stop="clearUserFilter()" title="ล้าง">&times;</span>
          <svg class="uf-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="user-filter-dropdown" v-if="userFilterOpen">
          <div class="user-filter-search">
            <input type="text" v-model="userSearch" placeholder="🔍 ค้นหาผู้ใช้..." ref="userSearchInput">
          </div>
          <div class="user-filter-list">
            <div class="uf-item all-item" :class="{selected: owner===''}"
                 @click="setOwner('')">
              <div class="uf-avatar">👥</div>
              <span class="uf-name">ทุกคน</span>
            </div>
            <div class="uf-item me-item" :class="{selected: owner==='me'}"
                 @click="setOwner('me')">
              <div class="uf-avatar">👤</div>
              <span class="uf-name">ของฉัน</span>
            </div>
            <div class="uf-item" v-for="u in filteredUsers" :key="u.id"
                 :class="{selected: owner == u.id}"
                 @click="setOwner(u.id)">
              <div class="uf-avatar">{{ (u.display_name || u.username || '?').charAt(0).toUpperCase() }}</div>
              <span class="uf-name">{{ u.display_name || u.username }}</span>
              <span class="uf-role" v-if="u.role==='admin'">admin</span>
              <span class="uf-count">{{ u.file_count }} ไฟล์</span>
            </div>
            <div class="uf-empty" v-if="filteredUsers.length === 0 && userSearch">
              ไม่พบผู้ใช้ "{{ userSearch }}"
            </div>
          </div>
        </div>
      </div>
      <select v-model="sort" @change="loadFiles()">
        <option value="newest">🕐 ใหม่สุด</option>
        <option value="oldest">📅 เก่าสุด</option>
        <option value="name_asc">🔤 A→Z</option>
        <option value="name_desc">🔤 Z→A</option>
        <option value="popular">🔥 ยอดนิยม</option>
        <option value="largest">📦 ใหญ่สุด</option>
      </select>
      <div class="view-toggle">
        <button :class="{ active: viewMode==='list' }" @click="setView('list')" title="List">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </button>
        <button :class="{ active: viewMode==='content' }" @click="setView('content')" title="Content">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
        </button>
        <button :class="{ active: viewMode==='medium' }" @click="setView('medium')" title="Grid">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        </button>
      </div>
    </div>
    <div class="g-toolbar-bottom">
      <span class="cat-tag" :class="{ active: filter === 'all' }" @click="setFilter('all')">ทั้งหมด</span>
      <span class="cat-tag" :class="{ active: filter === 'model' }" @click="setFilter('model')">โมเดล 3D</span>
      <span class="cat-tag" :class="{ active: filter === 'panorama' }" @click="setFilter('panorama')">พาโนรามา</span>
      <span class="cat-tag" :class="{ active: filter === 'image' }" @click="setFilter('image')">รูปภาพ</span>
      <span class="cat-tag" :class="{ active: filter === 'video' }" @click="setFilter('video')">วิดีโอ</span>
      <span class="cat-tag" :class="{ active: filter === 'embed' }" @click="setFilter('embed')">Embed</span>
      <span style="margin-left:auto;font-size:.75rem;color:var(--text-muted);">
        {{ files.length }} รายการ · หน้า {{ page }}/{{ totalPages }}
      </span>
    </div>
  </div>

  <!-- ═══ LIST VIEW ═══ -->
  <div class="file-list" v-if="viewMode==='list' && files.length">
    <div class="file-list-item" v-for="f in files" :key="f.id" @click="showDetail(f)">
      <div class="list-thumb">
        <img v-if="f.thumbnail_url" :src="f.thumbnail_url" :alt="f.title">
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path v-if="f.category_slug==='model'" d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
          <circle v-else-if="f.category_slug==='panorama'" cx="12" cy="12" r="10"/>
          <rect v-else x="3" y="3" width="18" height="18" rx="2"/>
        </svg>
      </div>
      <div class="list-info">
        <div class="list-title">{{ f.title }}</div>
        <div class="list-meta">
          <span class="badge" :class="'badge-'+f.category_slug">{{ f.category_slug }}</span>
          <span>{{ f.uploader }}</span>
          <span>{{ timeAgo(f.created_at) }}</span>
          <span v-if="f.file_size">{{ formatSize(f.file_size) }}</span>
          <span v-if="f.view_count">👁 {{ f.view_count }}</span>
        </div>
      </div>
      <div class="list-actions" @click.stop>
        <button class="act" @click="previewFile(f)" title="Preview"><i data-feather="play"></i></button>
        <button class="act" @click="openFile(f)" title="เปิด"><i data-feather="external-link"></i></button>
        <button class="act" @click="openEdit(f)" title="แก้ไข"><i data-feather="edit-2"></i></button>
      </div>
    </div>
  </div>

  <!-- ═══ CONTENT VIEW ═══ -->
  <div class="file-content-grid" v-if="viewMode==='content' && files.length">
    <div class="file-content-card" v-for="f in files" :key="f.id">
      <div class="content-preview" @click="previewFile(f)">
        <img v-if="f.thumbnail_url" :src="f.thumbnail_url" :alt="f.title">
        <div v-else class="icon-placeholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path v-if="f.category_slug==='model'" d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            <circle v-else-if="f.category_slug==='panorama'" cx="12" cy="12" r="10"/>
            <rect v-else x="3" y="3" width="18" height="18" rx="2"/>
          </svg>
        </div>
        <span class="file-card-badge">
          <span class="badge" :class="'badge-'+f.category_slug">{{ f.category_slug }}</span>
        </span>
        <div class="card-play-overlay">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="rgba(255,255,255,.9)" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        </div>
      </div>
      <div class="content-body">
        <div class="content-title">{{ f.title }}</div>
        <div class="content-desc" v-if="f.description">{{ f.description }}</div>
        <div class="content-desc" v-else style="font-style:italic;opacity:.5;">ไม่มีคำอธิบาย</div>
        <div class="content-meta-row">
          <span class="meta-item"><i data-feather="user" style="width:12px;height:12px;"></i> {{ f.uploader }}</span>
          <span class="meta-item"><i data-feather="clock" style="width:12px;height:12px;"></i> {{ timeAgo(f.created_at) }}</span>
          <span class="meta-item" v-if="f.file_size"><i data-feather="hard-drive" style="width:12px;height:12px;"></i> {{ formatSize(f.file_size) }}</span>
          <span class="meta-item" v-if="f.view_count"><i data-feather="eye" style="width:12px;height:12px;"></i> {{ f.view_count }}</span>
        </div>
      </div>
      <div class="content-footer">
        <button class="btn btn-sm btn-primary" @click="previewFile(f)">
          <i data-feather="play"></i> Preview
        </button>
        <button class="btn btn-sm btn-outline" @click="openFile(f)">
          <i data-feather="external-link"></i> เปิด
        </button>
        <button class="btn btn-sm btn-outline" @click="showDetail(f)">
          <i data-feather="info"></i>
        </button>
        <button class="btn btn-sm btn-outline" style="color:var(--warning);" @click="openEdit(f)">
          <i data-feather="edit-2"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- ═══ MEDIUM VIEW (Original Grid) ═══ -->
  <div class="file-grid" v-if="viewMode==='medium' && files.length">
    <div class="file-card" v-for="f in files" :key="f.id">
      <div class="file-card-preview"
           :ref="'cardPreview'+f.id"
           @click="previewFile(f)">
        <div v-if="cardLive[f.id]" class="card-live-layer" :ref="'cardLive'+f.id"></div>
        <img v-if="f.thumbnail_url" :src="f.thumbnail_url" :alt="f.title" :style="{opacity: cardLive[f.id] ? 0 : 1}">
        <div v-else-if="!cardLive[f.id]" class="icon-placeholder">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path v-if="f.category_slug==='model'" d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            <circle v-else-if="f.category_slug==='panorama'" cx="12" cy="12" r="10"/>
            <rect v-else x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          </svg>
        </div>
        <span class="file-card-badge">
          <span class="badge" :class="'badge-'+f.category_slug">{{ f.category_slug }}</span>
        </span>
        <div v-if="!cardLive[f.id]" class="card-play-overlay">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="rgba(255,255,255,.9)" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        </div>
      </div>
      <div class="file-card-body">
        <div class="file-card-name">{{ f.title }}</div>
        <div class="file-card-desc" v-if="f.description">{{ f.description }}</div>
        <div class="file-card-meta">
          <span>{{ f.uploader }}</span>
          <span>{{ timeAgo(f.created_at) }}</span>
          <span v-if="f.file_size">{{ formatSize(f.file_size) }}</span>
        </div>
      </div>
      <div class="file-card-actions">
        <div class="btn-row-full">
          <button class="btn btn-sm btn-primary" @click="previewFile(f)">
            <i data-feather="play"></i> Preview
          </button>
          <button class="btn btn-sm btn-outline" @click="openFile(f)">
            <i data-feather="external-link"></i> เปิด
          </button>
        </div>
        <button class="btn btn-sm btn-outline" @click="showDetail(f)" title="รายละเอียด">
          <i data-feather="info"></i>
        </button>
        <button class="btn btn-sm btn-outline" style="color:var(--warning);" @click="openEdit(f)" title="แก้ไข">
          <i data-feather="edit-2"></i>
        </button>
        <button class="btn btn-sm btn-outline" @click="showQR(f)" v-if="f.category_slug==='model' || f.category_slug==='embed'" title="AR">
          <i data-feather="maximize"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Empty -->
  <div class="empty-state" v-if="!files.length && !loading">
    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
      <polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/>
    </svg>
    <h3>ไม่พบไฟล์</h3>
    <p>ลองเปลี่ยนตัวกรองหรืออัปโหลดไฟล์ใหม่</p>
  </div>

  <!-- Loading -->
  <div v-if="loading" style="text-align:center;padding:40px;">
    <span class="spinner" style="width:32px;height:32px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;display:inline-block;"></span>
  </div>

  <!-- Pagination -->
  <div v-if="totalPages > 1" style="display:flex;justify-content:center;gap:8px;margin-top:24px;">
    <button class="btn btn-sm btn-outline" :disabled="page <= 1" @click="page--; loadFiles();">
      <i data-feather="chevron-left"></i>
    </button>
    <span style="padding:6px 14px;font-size:.85rem;color:var(--text-secondary);">
      หน้า {{ page }} / {{ totalPages }}
    </span>
    <button class="btn btn-sm btn-outline" :disabled="page >= totalPages" @click="page++; loadFiles();">
      <i data-feather="chevron-right"></i>
    </button>
  </div>

  <!-- Detail Side Panel -->
  <div class="detail-backdrop" :class="{ visible: detail !== null }" @click.self="closeDetail()">
    <div class="detail-panel" v-if="detail">
      <button class="detail-close" @click="closeDetail()">&times;</button>
      <div class="detail-preview" ref="detailPreviewBox" style="position:relative;">
        <!-- Inline live preview renders here -->
        <div class="detail-live-layer" ref="detailLive"></div>
        <!-- Fallback if nothing loaded yet -->
        <div v-if="!detailPreviewing" class="detail-preview-fallback" @click="previewFile(detail)" style="cursor:pointer;">
          <img v-if="detail.thumbnail_url" :src="detail.thumbnail_url" :alt="detail.title">
          <div v-else style="color:var(--text-muted);opacity:.4;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="3" width="18" height="18" rx="2"/>
            </svg>
          </div>
          <div class="detail-play-overlay">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="rgba(108,92,231,.9)" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
          </div>
        </div>
      </div>
      <h2 style="font-size:1.1rem;margin-bottom:16px;">{{ detail.title }}</h2>
      <div class="info-row"><span class="info-label">หมวดหมู่</span><span class="info-value"><span class="badge" :class="'badge-'+detail.category_slug">{{ detail.category_slug }}</span></span></div>
      <div class="info-row"><span class="info-label">ผู้อัปโหลด</span><span class="info-value">{{ detail.uploader }}</span></div>
      <div class="info-row"><span class="info-label">วันที่</span><span class="info-value">{{ detail.created_at }}</span></div>
      <div class="info-row" v-if="detail.file_size"><span class="info-label">ขนาด</span><span class="info-value">{{ formatSize(detail.file_size) }}</span></div>
      <div class="info-row" v-if="detail.description"><span class="info-label">คำอธิบาย</span><span class="info-value">{{ detail.description }}</span></div>
      <div class="info-row"><span class="info-label">URL</span><span class="info-value" style="word-break:break-all;font-size:.75rem;">{{ detail.file_url }}</span></div>

      <div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap;">
        <button class="btn btn-primary btn-sm" @click="previewFile(detail)"><i data-feather="play"></i> Preview</button>
        <button class="btn btn-outline btn-sm" @click="openFile(detail)"><i data-feather="external-link"></i> เปิดดู</button>
        <button class="btn btn-outline btn-sm" style="color:var(--warning);" @click="editFromDetail()"><i data-feather="edit-2"></i> แก้ไข</button>
        <button class="btn btn-outline btn-sm" @click="copyUrl(detail)"><i data-feather="copy"></i> คัดลอก URL</button>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="edit-backdrop" :class="{ visible: editFile !== null }" @click.self="editFile=null">
    <div class="edit-modal" v-if="editFile">
      <div class="edit-modal-header">
        <h2>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          แก้ไขข้อมูล
          <span class="edit-id-badge">ID: {{ editFile.id }}</span>
        </h2>
        <button class="close-btn" @click="editFile=null">&times;</button>
      </div>
      <div class="edit-modal-body">
        <div class="edit-field">
          <label>ชื่อ / Title</label>
          <input type="text" v-model="editForm.name" placeholder="ชื่อไฟล์...">
        </div>
        <div class="edit-field">
          <label>คำอธิบาย / Description</label>
          <textarea v-model="editForm.description" placeholder="รายละเอียดเพิ่มเติม..."></textarea>
        </div>
        <div class="edit-field-row">
          <div class="edit-field">
            <label>หมวดหมู่ / Category</label>
            <select v-model="editForm.category">
              <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">{{ cat.name }}</option>
            </select>
          </div>
          <div class="edit-field">
            <label>การมองเห็น / Visibility</label>
            <select v-model="editForm.visibility">
              <option value="public">🌐 Public</option>
              <option value="unlisted">🔗 Unlisted</option>
              <option value="private">🔒 Private</option>
            </select>
          </div>
        </div>
        <div class="edit-field" v-if="editFile.category_slug==='model' || editForm.category==='model'">
          <label>AR Settings</label>
          <div style="display:flex;gap:14px;align-items:center;">
            <label class="edit-toggle" style="flex:1;">
              <input type="checkbox" v-model="editForm.ar_enabled">
              <span>เปิดใช้ AR</span>
            </label>
            <div style="flex:1;">
              <label style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px;display:block;">AR Scale</label>
              <input type="number" v-model.number="editForm.ar_scale" step="0.1" min="0.1" max="100" style="padding:8px 10px;">
            </div>
          </div>
        </div>
        <div class="edit-field" v-if="editFile.category_slug==='embed' || editForm.category==='embed'">
          <label>Embed Source URL</label>
          <input type="url" v-model="editForm.embed_src" placeholder="https://...">
        </div>
        <div class="edit-field" v-if="editFile.category_slug==='embed' || editForm.category==='embed'">
          <label>Embed Provider</label>
          <input type="text" v-model="editForm.embed_provider" placeholder="Sketchfab, YouTube, etc.">
        </div>

        <!-- File Info (read-only) -->
        <div style="margin-top:12px;padding:12px;background:var(--bg-surface);border-radius:10px;border:1px solid var(--border);">
          <div style="font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">ข้อมูลไฟล์ (อ่านอย่างเดียว)</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:.78rem;">
            <div><span style="color:var(--text-muted);">ชื่อเดิม:</span> {{ editFile.original_name }}</div>
            <div><span style="color:var(--text-muted);">ขนาด:</span> {{ formatSize(editFile.file_size) }}</div>
            <div><span style="color:var(--text-muted);">ประเภท:</span> {{ editFile.mime_type || editFile.extension }}</div>
            <div><span style="color:var(--text-muted);">อัปโหลด:</span> {{ editFile.uploader }}</div>
          </div>
        </div>
      </div>
      <div class="edit-modal-footer">
        <div class="edit-footer-left">
          <button class="btn-del-ghost" @click="confirmDeleteFromEdit()">
            <i data-feather="trash-2" style="width:14px;height:14px;"></i> ลบไฟล์
          </button>
        </div>
        <div class="edit-footer-right">
          <button class="btn btn-outline btn-sm" @click="editFile=null">ยกเลิก</button>
          <button class="btn btn-primary btn-sm" @click="saveEdit()" :disabled="editSaving">
            <span v-if="editSaving">กำลังบันทึก...</span>
            <span v-else><i data-feather="check"></i> บันทึก</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Confirm Popup -->
  <div class="del-confirm-overlay" v-if="deleteConfirm" @click.self="deleteConfirm=null">
    <div class="del-confirm-box">
      <div class="del-confirm-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <polyline points="3 6 5 6 21 6"/>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
          <line x1="10" y1="11" x2="10" y2="17"/>
          <line x1="14" y1="11" x2="14" y2="17"/>
        </svg>
      </div>
      <h3>ยืนยันการลบ</h3>
      <p>คุณแน่ใจหรือไม่ที่จะลบไฟล์<br><span class="del-name">"{{ deleteConfirm.title }}"</span><br>การกระทำนี้ไม่สามารถย้อนกลับได้</p>
      <div class="del-confirm-actions">
        <button class="btn btn-outline btn-sm" @click="deleteConfirm=null" style="min-width:90px;">ยกเลิก</button>
        <button class="btn-danger-solid" @click="doDelete()" :disabled="deleting">
          <template v-if="deleting">⏳ กำลังลบ...</template>
          <template v-else><i data-feather="trash-2" style="width:14px;height:14px;"></i> ลบเลย</template>
        </button>
      </div>
    </div>
  </div>

  <!-- Preview Modal -->
  <div class="preview-backdrop" :class="{ visible: preview !== null }" @click.self="closePreview()">
    <div class="preview-container" v-if="preview">
      <div class="preview-header">
        <div class="preview-header-icon">
          <svg viewBox="0 0 24 24">
            <path v-if="preview.category_slug==='model'" d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            <circle v-else-if="preview.category_slug==='panorama'" cx="12" cy="12" r="10"/>
            <polygon v-else-if="preview.category_slug==='video'" points="5 3 19 12 5 21 5 3"/>
            <path v-else-if="preview.category_slug==='embed'" d="M16 18l6-6-6-6M8 6l-6 6 6 6"/>
            <rect v-else x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          </svg>
        </div>
        <div class="preview-header-info">
          <div class="preview-header-title">{{ preview.title }}</div>
          <div class="preview-header-meta">
            <span><span class="badge" :class="'badge-'+preview.category_slug" style="font-size:.65rem;">{{ preview.category_slug }}</span></span>
            <span v-if="preview.file_size">{{ formatSize(preview.file_size) }}</span>
            <span>{{ preview.uploader }}</span>
          </div>
        </div>
        <div class="preview-header-actions">
          <button class="btn btn-sm btn-outline" @click="openFile(preview)"><i data-feather="external-link"></i> <span>เปิดเต็ม</span></button>
          <button class="btn btn-sm btn-outline" @click="copyUrl(preview)"><i data-feather="copy"></i></button>
          <button class="btn btn-sm btn-outline" @click="closePreview()" style="color:var(--danger);"><i data-feather="x"></i></button>
        </div>
      </div>
      <div class="preview-body" ref="previewBody">
        <div class="preview-loading" v-if="previewLoading">
          <div class="spinner"></div>
          <div>กำลังโหลด...</div>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<?php include __DIR__ . '/../includes/bottom_nav.php'; ?>

<!-- Toast -->
<div class="toast-container" id="toast-container"></div>

<script src="<?= BASE_URL ?>/third_party/vue.min.js"></script>
<script>
var BASE = '<?= BASE_URL ?>';
function showToast(msg, type) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast ' + (type||'');
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(function(){ t.remove(); }, 3000);
}

new Vue({
  el: '#app',
  data: function() {
    return {
      files: [],
      stats: {},
      filter: 'all',
      search: '',
      sort: 'newest',
      owner: '',
      userFilterOpen: false,
      userSearch: '',
      viewMode: 'medium',
      users: [],
      page: 1,
      totalPages: 1,
      loading: false,
      detail: null,
      debounceTimer: null,
      preview: null,
      previewLoading: false,
      categories: [],
      editFile: null,
      editForm: {},
      editSaving: false,
      detailPreviewing: false,
      cardLive: {},
      deleteConfirm: null,
      deleting: false
    };
  },
  directives: {
    'click-outside': {
      bind: function(el, binding) {
        el._clickOutside = function(e) {
          if (!el.contains(e.target)) binding.value();
        };
        document.addEventListener('click', el._clickOutside);
      },
      unbind: function(el) {
        document.removeEventListener('click', el._clickOutside);
      }
    }
  },
  computed: {
    filteredUsers: function() {
      var q = this.userSearch.trim().toLowerCase();
      if (!q) return this.users;
      return this.users.filter(function(u) {
        var name = (u.display_name || '').toLowerCase();
        var uname = (u.username || '').toLowerCase();
        return name.indexOf(q) !== -1 || uname.indexOf(q) !== -1;
      });
    },
    selectedUserLabel: function() {
      if (!this.owner) return '👥 ทุกคน';
      if (this.owner === 'me') return '👤 ของฉัน';
      var id = this.owner;
      var u = this.users.find(function(u) { return u.id == id; });
      return u ? (u.display_name || u.username) : 'ผู้ใช้ #' + id;
    },
    selectedUserInitial: function() {
      if (!this.owner || this.owner === 'me') return '';
      var id = this.owner;
      var u = this.users.find(function(u) { return u.id == id; });
      return u ? (u.display_name || u.username || '?').charAt(0).toUpperCase() : '?';
    }
  },
  watch: {
    userFilterOpen: function(val) {
      if (val) {
        var vm = this;
        vm.$nextTick(function() {
          if (vm.$refs.userSearchInput) vm.$refs.userSearchInput.focus();
        });
      }
    }
  },
  created: function() {
    // Non-reactive instance properties (underscore prefix not reactive in Vue data)
    this._previewRenderer = null;
    this._previewAnimId = null;
    this._previewMixer = null;
    this._cardRenderers = {};
    this._cardObserver = null;
    this._detailRenderer = null;
    this._detailAnimId = null;
    this._detailMixer = null;

    this.loadFiles();
    this.loadStats();
    this.loadCategories();
    this.loadUsers();
    var vm = this;
    window.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        if (vm.userFilterOpen) { vm.closeUserFilter(); }
        else if (vm.deleteConfirm) { vm.deleteConfirm = null; }
        else if (vm.editFile) { vm.editFile = null; }
        else if (vm.preview) { vm.closePreview(); }
        else if (vm.detail) { vm.closeDetail(); }
      }
    });
  },
  methods: {
    loadFiles: function() {
      var vm = this;
      vm.loading = true;
      var q = '?action=files&limit=12&page=' + vm.page + '&sort=' + vm.sort;
      if (vm.filter !== 'all') q += '&category=' + vm.filter;
      if (vm.search) q += '&search=' + encodeURIComponent(vm.search);
      if (vm.owner) q += '&owner=' + encodeURIComponent(vm.owner);
      fetch(BASE + '/api/index.php' + q)
        .then(function(r){ return r.json(); })
        .then(function(d) {
          vm.cleanupAllCards();
          vm.files = (d.data && d.data.files) || [];
          vm.totalPages = (d.data && d.data.total_pages) || 1;
          vm.loading = false;
          vm.$nextTick(function(){
            feather.replace();
            if (vm.viewMode === 'medium') vm.setupCardObserver();
          });
        })
        .catch(function(){ vm.loading = false; });
    },
    loadStats: function() {
      var vm = this;
      fetch(BASE + '/api/index.php?action=stats')
        .then(function(r){ return r.json(); })
        .then(function(d){ vm.stats = d.data || {}; });
    },
    loadCategories: function() {
      var vm = this;
      fetch(BASE + '/api/index.php?action=categories')
        .then(function(r){ return r.json(); })
        .then(function(d){ vm.categories = d.data || []; });
    },
    loadUsers: function() {
      var vm = this;
      fetch(BASE + '/api/index.php?action=users')
        .then(function(r){ return r.json(); })
        .then(function(d){ vm.users = d.data || []; });
    },
    setOwner: function(val) {
      this.owner = val;
      this.userFilterOpen = false;
      this.userSearch = '';
      this.page = 1;
      this.loadFiles();
    },
    clearUserFilter: function() {
      this.owner = '';
      this.userFilterOpen = false;
      this.userSearch = '';
      this.page = 1;
      this.loadFiles();
    },
    closeUserFilter: function() {
      this.userFilterOpen = false;
      this.userSearch = '';
    },
    setView: function(mode) {
      this.cleanupAllCards();
      this.viewMode = mode;
      if (mode === 'medium') {
        var vm = this;
        vm.$nextTick(function() {
          feather.replace();
          vm.setupCardObserver();
        });
      } else {
        var vm2 = this;
        vm2.$nextTick(function() { feather.replace(); });
      }
    },
    editFromDetail: function() {
      var f = this.detail;
      this.closeDetail();
      this.openEdit(f);
    },
    openEdit: function(f) {
      this.editFile = f;
      this.editForm = {
        name: f.name || f.title || '',
        description: f.description || '',
        category: f.category_slug || '',
        visibility: f.visibility || 'public',
        ar_enabled: f.ar_enabled == 1,
        ar_scale: f.ar_scale || 1,
        embed_src: f.embed_src || '',
        embed_provider: f.embed_provider || ''
      };
      var vm = this;
      vm.$nextTick(function(){ feather.replace(); });
    },
    saveEdit: function() {
      var vm = this;
      if (!vm.editForm.name || !vm.editForm.name.trim()) {
        showToast('กรุณากรอกชื่อ', 'error');
        return;
      }
      vm.editSaving = true;
      var payload = {
        name: vm.editForm.name.trim(),
        description: vm.editForm.description.trim(),
        category: vm.editForm.category,
        visibility: vm.editForm.visibility,
        ar_enabled: vm.editForm.ar_enabled ? 1 : 0,
        ar_scale: parseFloat(vm.editForm.ar_scale) || 1
      };
      // Include embed fields if embed category
      if (vm.editForm.category === 'embed') {
        payload.embed_src = vm.editForm.embed_src;
        payload.embed_provider = vm.editForm.embed_provider;
      }
      fetch(BASE + '/api/index.php?action=files&id=' + vm.editFile.id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function(r){ return r.json(); })
        .then(function(d) {
          vm.editSaving = false;
          if (d.error) {
            showToast(d.error, 'error');
          } else {
            showToast('บันทึกสำเร็จ', 'success');
            vm.editFile = null;
            vm.loadFiles();
          }
        })
        .catch(function() {
          vm.editSaving = false;
          showToast('เกิดข้อผิดพลาด', 'error');
        });
    },
    setFilter: function(f) {
      this.filter = f;
      this.page = 1;
      this.loadFiles();
    },
    debounceLoad: function() {
      var vm = this;
      clearTimeout(vm.debounceTimer);
      vm.debounceTimer = setTimeout(function() { vm.page = 1; vm.loadFiles(); }, 300);
    },
    openFile: function(f) {
      if (f.category_slug === 'embed' || f.source_type === 'embed') {
        window.location.href = BASE + '/pages/viewer.php?mode=embed&embed=' + encodeURIComponent(f.embed_src || f.file_url) + '&id=' + f.id;
      } else if (f.category_slug === 'model') {
        window.location.href = BASE + '/pages/viewer.php?src=' + encodeURIComponent(f.file_url) + '&id=' + f.id;
      } else if (f.category_slug === 'panorama') {
        window.location.href = BASE + '/pages/panorama.php?src=' + encodeURIComponent(f.file_url);
      } else if (f.file_url) {
        window.open(f.file_url, '_blank');
      }
    },
    showDetail: function(f) {
      this.cleanupDetailPreview();
      this.detailPreviewing = false;
      this.detail = f;
      var vm = this;
      vm.$nextTick(function() { vm.renderDetailInline(f); });
    },
    closeDetail: function() {
      this.cleanupDetailPreview();
      this.detailPreviewing = false;
      this.detail = null;
    },
    showQR: function(f) {
      window.location.href = BASE + '/pages/qr.php?file_id=' + f.id;
    },
    confirmDelete: function(f) {
      this.deleteConfirm = f;
      var vm = this;
      vm.$nextTick(function() { feather.replace(); });
    },
    confirmDeleteFromEdit: function() {
      if (!this.editFile) return;
      this.deleteConfirm = this.editFile;
      var vm = this;
      vm.$nextTick(function() { feather.replace(); });
    },
    doDelete: function() {
      var vm = this;
      if (!vm.deleteConfirm || vm.deleting) return;
      vm.deleting = true;
      fetch(BASE + '/api/index.php?action=files&id=' + vm.deleteConfirm.id, { method: 'DELETE' })
        .then(function(r){ return r.json(); })
        .then(function(d) {
          vm.deleting = false;
          if (d.data) {
            showToast('ลบสำเร็จ', 'success');
            vm.deleteConfirm = null;
            vm.editFile = null;
            vm.loadFiles();
            vm.loadStats();
          } else {
            showToast(d.error || 'ลบล้มเหลว', 'error');
          }
        })
        .catch(function() {
          vm.deleting = false;
          showToast('เกิดข้อผิดพลาด', 'error');
        });
    },
    copyUrl: function(f) {
      var url = window.location.origin + f.file_url;
      navigator.clipboard.writeText(url).then(function() {
        showToast('คัดลอก URL แล้ว', 'success');
      });
    },
    formatSize: function(b) {
      if (!b) return '';
      if (b < 1024) return b + ' B';
      if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
      return (b/1048576).toFixed(1) + ' MB';
    },
    /* ── Preview ── */
    previewFile: function(f) {
      var vm = this;
      vm.preview = f;
      vm.previewLoading = true;
      vm.$nextTick(function() {
        feather.replace();
        setTimeout(function() { vm.renderPreview(f); }, 100);
      });
    },
    closePreview: function() {
      // Clean up Three.js resources
      if (this._previewAnimId) cancelAnimationFrame(this._previewAnimId);
      if (this._previewRenderer) {
        this._previewRenderer.dispose();
        this._previewRenderer = null;
      }
      this._previewMixer = null;
      this._previewAnimId = null;
      // Remove dynamic content from preview body
      if (this.$refs.previewBody) {
        var body = this.$refs.previewBody;
        while (body.firstChild) {
          if (body.firstChild.__vue__) break; // don't remove Vue-managed nodes
          body.removeChild(body.firstChild);
        }
      }
      this.preview = null;
      this.previewLoading = false;
    },
    renderPreview: function(f) {
      var vm = this;
      var body = vm.$refs.previewBody;
      if (!body) return;

      var cat = f.category_slug;
      var url = f.file_url || '';

      if (cat === 'model') {
        vm.renderModel(body, url);
      } else if (cat === 'panorama') {
        vm.renderPanorama(body, url);
      } else if (cat === 'image') {
        vm.renderImage(body, url);
      } else if (cat === 'video') {
        vm.renderVideo(body, url);
      } else if (cat === 'embed' || f.source_type === 'embed') {
        vm.renderEmbed(body, f.embed_src || url);
      } else if (url) {
        vm.renderImage(body, url);
      } else {
        vm.previewLoading = false;
      }
    },
    renderModel: function(body, url) {
      var vm = this;
      var w = body.clientWidth, h = body.clientHeight;
      var scene = new THREE.Scene();
      scene.background = new THREE.Color(0x0f0f1a);

      var camera = new THREE.PerspectiveCamera(50, w / h, 0.01, 100);
      var renderer = new THREE.WebGLRenderer({ antialias: true });
      renderer.setSize(w, h);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.outputEncoding = THREE.sRGBEncoding;
      body.insertBefore(renderer.domElement, body.firstChild);
      vm._previewRenderer = renderer;

      // Lights
      scene.add(new THREE.AmbientLight(0xffffff, 0.6));
      var dir = new THREE.DirectionalLight(0xffffff, 0.8);
      dir.position.set(5, 10, 7);
      scene.add(dir);
      var dir2 = new THREE.DirectionalLight(0x6C5CE7, 0.3);
      dir2.position.set(-5, 5, -5);
      scene.add(dir2);

      // Grid
      var grid = new THREE.GridHelper(10, 20, 0x2d2d50, 0x1a1a2e);
      scene.add(grid);

      // Camera orbit
      var spherical = { theta: 0.3, phi: Math.PI / 4, radius: 3 };
      var lookAtY = 0.5;
      function updateCam() {
        camera.position.x = spherical.radius * Math.sin(spherical.phi) * Math.sin(spherical.theta);
        camera.position.y = spherical.radius * Math.cos(spherical.phi);
        camera.position.z = spherical.radius * Math.sin(spherical.phi) * Math.cos(spherical.theta);
        camera.lookAt(0, lookAtY, 0);
      }
      updateCam();

      // Mouse interaction
      var drag = false, prev = {x:0,y:0};
      renderer.domElement.addEventListener('mousedown', function(e) { drag = true; prev = {x:e.clientX, y:e.clientY}; });
      renderer.domElement.addEventListener('mousemove', function(e) {
        if (!drag) return;
        spherical.theta -= (e.clientX - prev.x) * 0.005;
        spherical.phi = Math.max(0.1, Math.min(Math.PI - 0.1, spherical.phi + (e.clientY - prev.y) * 0.005));
        prev = {x:e.clientX, y:e.clientY};
        updateCam();
      });
      window.addEventListener('mouseup', function() { drag = false; });
      renderer.domElement.addEventListener('wheel', function(e) {
        spherical.radius = Math.max(0.5, Math.min(20, spherical.radius + e.deltaY * 0.005));
        updateCam();
      });

      // Touch
      var touchS = null, pinchD = 0;
      renderer.domElement.addEventListener('touchstart', function(e) {
        if (e.touches.length === 1) touchS = {x:e.touches[0].clientX, y:e.touches[0].clientY};
        else if (e.touches.length === 2) pinchD = Math.hypot(e.touches[0].clientX-e.touches[1].clientX, e.touches[0].clientY-e.touches[1].clientY);
      });
      renderer.domElement.addEventListener('touchmove', function(e) {
        e.preventDefault();
        if (e.touches.length === 1 && touchS) {
          spherical.theta -= (e.touches[0].clientX - touchS.x) * 0.005;
          spherical.phi = Math.max(0.1, Math.min(Math.PI-0.1, spherical.phi + (e.touches[0].clientY-touchS.y)*0.005));
          touchS = {x:e.touches[0].clientX, y:e.touches[0].clientY};
          updateCam();
        } else if (e.touches.length === 2) {
          var d = Math.hypot(e.touches[0].clientX-e.touches[1].clientX, e.touches[0].clientY-e.touches[1].clientY);
          spherical.radius = Math.max(0.5, Math.min(20, spherical.radius*(pinchD/d)));
          pinchD = d; updateCam();
        }
      }, {passive:false});

      // Load model
      var loader = new THREE.GLTFLoader();
      loader.load(url, function(gltf) {
        var model = gltf.scene;
        var box = new THREE.Box3().setFromObject(model);
        var size = box.getSize(new THREE.Vector3());
        var center = box.getCenter(new THREE.Vector3());
        var maxDim = Math.max(size.x, size.y, size.z);
        var scale = 2 / maxDim;
        model.scale.setScalar(scale);
        model.position.sub(center.multiplyScalar(scale));
        scene.add(model);

        // Grid to bottom
        var wb = new THREE.Box3().setFromObject(model);
        grid.position.y = wb.min.y;
        lookAtY = (wb.min.y + wb.max.y) / 2;
        updateCam();

        if (gltf.animations && gltf.animations.length) {
          vm._previewMixer = new THREE.AnimationMixer(model);
          gltf.animations.forEach(function(clip) { vm._previewMixer.clipAction(clip).play(); });
        }
        vm.previewLoading = false;
      }, undefined, function() { vm.previewLoading = false; });

      // Auto-rotate & animate
      var clock = new THREE.Clock();
      function animate() {
        vm._previewAnimId = requestAnimationFrame(animate);
        spherical.theta += 0.003;
        updateCam();
        if (vm._previewMixer) vm._previewMixer.update(clock.getDelta());
        renderer.render(scene, camera);
      }
      animate();

      // Resize
      var onResize = function() {
        var nw = body.clientWidth, nh = body.clientHeight;
        camera.aspect = nw / nh;
        camera.updateProjectionMatrix();
        renderer.setSize(nw, nh);
      };
      window.addEventListener('resize', onResize);
    },
    renderPanorama: function(body, url) {
      var vm = this;
      // Preload image first to handle large files
      var img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = function() {
        var w = body.clientWidth, h = body.clientHeight;
        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(75, w / h, 0.1, 1000);
        var renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(w, h);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        body.insertBefore(renderer.domElement, body.firstChild);
        vm._previewRenderer = renderer;

        var tex = new THREE.Texture(img);
        tex.needsUpdate = true;
        var geom = new THREE.SphereGeometry(50, 60, 40);
        geom.scale(-1, 1, 1);
        scene.add(new THREE.Mesh(geom, new THREE.MeshBasicMaterial({ map: tex })));
        vm.previewLoading = false;

        // 360° badge
        var badge = document.createElement('div');
        badge.className = 'pano-badge';
        badge.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg> 360°';
        body.appendChild(badge);

        // Drag hint
        var hint = document.createElement('div');
        hint.className = 'pano-hint';
        hint.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg> ลากเพื่อหมุนดูรอบทิศ · Scroll ซูม';
        body.appendChild(hint);

        // Zoom info
        var zoomInfoEl = document.createElement('div');
        zoomInfoEl.className = 'pano-zoom-info';
        zoomInfoEl.textContent = 'FOV: 75°';
        body.appendChild(zoomInfoEl);
        var zoomTimer = null;

        var lon = 0, lat = 0, fov = 75, drag = false, prev = {x:0,y:0};
        renderer.domElement.addEventListener('mousedown', function(e) { drag = true; prev = {x:e.clientX,y:e.clientY}; });
        renderer.domElement.addEventListener('mousemove', function(e) {
          if (!drag) return;
          lon -= (e.clientX - prev.x) * 0.15;
          lat += (e.clientY - prev.y) * 0.15;
          lat = Math.max(-85, Math.min(85, lat));
          prev = {x:e.clientX,y:e.clientY};
        });
        window.addEventListener('mouseup', function() { drag = false; });

        // Scroll zoom (FOV)
        renderer.domElement.addEventListener('wheel', function(e) {
          e.preventDefault();
          fov = Math.max(30, Math.min(110, fov + e.deltaY * 0.05));
          camera.fov = fov;
          camera.updateProjectionMatrix();
          zoomInfoEl.textContent = 'FOV: ' + Math.round(fov) + '°';
          zoomInfoEl.classList.add('show');
          clearTimeout(zoomTimer);
          zoomTimer = setTimeout(function() { zoomInfoEl.classList.remove('show'); }, 1200);
        }, {passive:false});

        // Touch
        var touchS = null, pinchD = 0;
        renderer.domElement.addEventListener('touchstart', function(e) {
          if (e.touches.length === 1) { drag = true; touchS = {x:e.touches[0].clientX,y:e.touches[0].clientY}; }
          else if (e.touches.length === 2) pinchD = Math.hypot(e.touches[0].clientX-e.touches[1].clientX, e.touches[0].clientY-e.touches[1].clientY);
        });
        renderer.domElement.addEventListener('touchmove', function(e) {
          e.preventDefault();
          if (e.touches.length === 1 && touchS) {
            lon -= (e.touches[0].clientX - touchS.x) * 0.15;
            lat += (e.touches[0].clientY - touchS.y) * 0.15;
            lat = Math.max(-85, Math.min(85, lat));
            touchS = {x:e.touches[0].clientX,y:e.touches[0].clientY};
          } else if (e.touches.length === 2) {
            var d = Math.hypot(e.touches[0].clientX-e.touches[1].clientX, e.touches[0].clientY-e.touches[1].clientY);
            fov = Math.max(30, Math.min(110, fov * (pinchD / d)));
            camera.fov = fov;
            camera.updateProjectionMatrix();
            pinchD = d;
            zoomInfoEl.textContent = 'FOV: ' + Math.round(fov) + '°';
            zoomInfoEl.classList.add('show');
            clearTimeout(zoomTimer);
            zoomTimer = setTimeout(function() { zoomInfoEl.classList.remove('show'); }, 1200);
          }
        }, {passive:false});
        renderer.domElement.addEventListener('touchend', function() { drag = false; });

        function animate() {
          vm._previewAnimId = requestAnimationFrame(animate);
          if (!drag) lon += 0.05;
          var phi = THREE.MathUtils.degToRad(90 - lat);
          var theta = THREE.MathUtils.degToRad(lon);
          camera.lookAt(
            50 * Math.sin(phi) * Math.cos(theta),
            50 * Math.cos(phi),
            50 * Math.sin(phi) * Math.sin(theta)
          );
          renderer.render(scene, camera);
        }
        animate();

        window.addEventListener('resize', function() {
          var nw = body.clientWidth, nh = body.clientHeight;
          camera.aspect = nw / nh;
          camera.updateProjectionMatrix();
          renderer.setSize(nw, nh);
        });
      };
      img.onerror = function() {
        vm.previewLoading = false;
        var err = document.createElement('div');
        err.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-muted);gap:8px;';
        err.innerHTML = '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg><span>ไม่สามารถโหลดภาพพาโนรามาได้</span>';
        body.insertBefore(err, body.firstChild);
      };
      img.src = url;
    },
    renderImage: function(body, url) {
      var vm = this;
      var img = document.createElement('img');
      img.src = url;
      img.alt = 'Preview';
      img.style.cssText = 'width:100%;height:100%;object-fit:contain;';
      img.onload = function() { vm.previewLoading = false; };
      img.onerror = function() { vm.previewLoading = false; };
      body.insertBefore(img, body.firstChild);
    },
    renderVideo: function(body, url) {
      var vm = this;
      var video = document.createElement('video');
      video.src = url;
      video.controls = true;
      video.autoplay = true;
      video.style.cssText = 'width:100%;height:100%;background:#000;';
      video.onloadeddata = function() { vm.previewLoading = false; };
      video.onerror = function() { vm.previewLoading = false; };
      body.insertBefore(video, body.firstChild);
    },
    renderEmbed: function(body, url) {
      var vm = this;
      var iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.allow = 'autoplay; fullscreen; xr-spatial-tracking';
      iframe.style.cssText = 'width:100%;height:100%;border:none;';
      iframe.onload = function() { vm.previewLoading = false; };
      body.insertBefore(iframe, body.firstChild);
    },
    /* ── Card Inline Preview (auto via IntersectionObserver) ── */
    setupCardObserver: function() {
      var vm = this;
      // Disconnect old observer
      if (vm._cardObserver) vm._cardObserver.disconnect();

      vm._cardObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          var id = entry.target.dataset.fileId;
          if (!id) return;
          if (entry.isIntersecting && !vm.cardLive[id]) {
            var f = vm.files.find(function(x) { return String(x.id) === String(id); });
            if (f) vm.activateCardPreview(f);
          }
        });
      }, { rootMargin: '100px' });

      // Observe all card preview elements
      vm.files.forEach(function(f) {
        var refs = vm.$refs['cardPreview' + f.id];
        var el = Array.isArray(refs) ? refs[0] : refs;
        if (el) {
          el.dataset.fileId = f.id;
          vm._cardObserver.observe(el);
        }
      });
    },
    activateCardPreview: function(f) {
      var vm = this;
      var cat = f.category_slug;
      var url = f.file_url || '';
      if (cat === 'image' || !url) return;
      if (cat === 'model' && !/\.(glb|gltf)(\?|$)/i.test(url)) return;
      vm.$set(vm.cardLive, f.id, true);
      vm.$nextTick(function() {
        var container = vm.$refs['cardLive' + f.id];
        if (!container || (Array.isArray(container) && !container.length)) return;
        if (Array.isArray(container)) container = container[0];
        var w = container.clientWidth, h = container.clientHeight;
        if (cat === 'model') {
          vm.renderCardModel(f.id, container, url, w, h);
        } else if (cat === 'video') {
          vm.renderCardVideo(f.id, container, url);
        } else if (cat === 'panorama') {
          vm.renderCardPanorama(f.id, container, url, w, h);
        } else if (cat === 'embed' || f.source_type === 'embed') {
          vm.renderCardEmbed(f.id, container, f.embed_src || url);
        }
      });
    },
    cleanupAllCards: function() {
      var vm = this;
      if (vm._cardObserver) { vm._cardObserver.disconnect(); vm._cardObserver = null; }
      if (!vm._cardRenderers) return;
      Object.keys(vm._cardRenderers).forEach(function(id) {
        var info = vm._cardRenderers[id];
        if (info) {
          if (info.animId) cancelAnimationFrame(info.animId);
          if (info.renderer) info.renderer.dispose();
          if (info.video) { info.video.pause(); info.video.src = ''; }
          if (info.iframe) { info.iframe.src = ''; }
        }
      });
      vm._cardRenderers = {};
      vm.cardLive = {};
    },
    renderCardModel: function(id, el, url, w, h) {
      var vm = this;
      if (!vm._cardRenderers) vm._cardRenderers = {};
      var scene = new THREE.Scene();
      scene.background = new THREE.Color(0x12121e);
      var camera = new THREE.PerspectiveCamera(45, w / h, 0.01, 100);
      var renderer = new THREE.WebGLRenderer({ antialias: true });
      renderer.setSize(w, h);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.outputEncoding = THREE.sRGBEncoding;
      el.appendChild(renderer.domElement);
      scene.add(new THREE.AmbientLight(0xffffff, 0.6));
      var dir = new THREE.DirectionalLight(0xffffff, 0.8);
      dir.position.set(5, 10, 7); scene.add(dir);
      var theta = 0, lookY = 0.5;
      camera.position.set(0, 1.5, 3);
      var mixer = null;
      vm._cardRenderers[id] = { renderer: renderer, animId: null };
      var loader = new THREE.GLTFLoader();
      loader.load(url, function(gltf) {
        if (!vm._cardRenderers[id]) return; // already cleaned up
        var model = gltf.scene;
        var box = new THREE.Box3().setFromObject(model);
        var size = box.getSize(new THREE.Vector3());
        var center = box.getCenter(new THREE.Vector3());
        var s = 1.8 / Math.max(size.x, size.y, size.z);
        model.scale.setScalar(s);
        model.position.sub(center.multiplyScalar(s));
        scene.add(model);
        var wb = new THREE.Box3().setFromObject(model);
        lookY = (wb.min.y + wb.max.y) / 2;
        if (gltf.animations && gltf.animations.length) {
          mixer = new THREE.AnimationMixer(model);
          gltf.animations.forEach(function(c) { mixer.clipAction(c).play(); });
        }
      }, undefined, function() {
        // Load failed — silently stop
        vm.stopCardPreview({ id: id });
      });
      var clock = new THREE.Clock();
      function animate() {
        var aId = requestAnimationFrame(animate);
        if (vm._cardRenderers && vm._cardRenderers[id]) vm._cardRenderers[id].animId = aId;
        theta += 0.012;
        camera.position.x = 3 * Math.sin(theta);
        camera.position.z = 3 * Math.cos(theta);
        camera.position.y = 1.5;
        camera.lookAt(0, lookY, 0);
        if (mixer) mixer.update(clock.getDelta());
        renderer.render(scene, camera);
      }
      animate();
    },
    renderCardVideo: function(id, el, url) {
      var video = document.createElement('video');
      video.src = url;
      video.muted = true;
      video.autoplay = true;
      video.loop = true;
      video.playsInline = true;
      video.style.cssText = 'width:100%;height:100%;object-fit:cover;';
      el.appendChild(video);
      this._cardRenderers[id] = { video: video };
    },
    renderCardPanorama: function(id, el, url, w, h) {
      var vm = this;
      var img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = function() {
        if (!vm._cardRenderers) return;
        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(75, w / h, 0.1, 1000);
        var renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(w, h);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        el.appendChild(renderer.domElement);
        var tex = new THREE.Texture(img);
        tex.needsUpdate = true;
        var geom = new THREE.SphereGeometry(50, 32, 24);
        geom.scale(-1, 1, 1);
        scene.add(new THREE.Mesh(geom, new THREE.MeshBasicMaterial({ map: tex })));
        var lon = 0;
        vm._cardRenderers[id] = { renderer: renderer, animId: null };
        function animate() {
          var aId = requestAnimationFrame(animate);
          if (vm._cardRenderers && vm._cardRenderers[id]) vm._cardRenderers[id].animId = aId;
          lon += 0.15;
          var phi = THREE.MathUtils.degToRad(80);
          var th = THREE.MathUtils.degToRad(lon);
          camera.lookAt(50*Math.sin(phi)*Math.cos(th), 50*Math.cos(phi), 50*Math.sin(phi)*Math.sin(th));
          renderer.render(scene, camera);
        }
        animate();
      };
      img.onerror = function() {
        // Fallback: show static image
        var fallback = document.createElement('img');
        fallback.src = url;
        fallback.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        el.appendChild(fallback);
      };
      vm._cardRenderers[id] = { renderer: null, animId: null };
      img.src = url;
    },
    renderCardEmbed: function(id, el, url) {
      var iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.allow = 'autoplay; fullscreen';
      iframe.style.cssText = 'width:100%;height:100%;border:none;pointer-events:none;';
      el.appendChild(iframe);
      this._cardRenderers[id] = { iframe: iframe };
    },
    /* ── Detail Inline Preview ── */
    renderDetailInline: function(f) {
      var vm = this;
      var el = vm.$refs.detailLive;
      if (!el) return;
      el.innerHTML = '';
      var cat = f.category_slug;
      var url = f.file_url || '';
      var w = el.clientWidth, h = el.clientHeight || 250;
      if (!url && cat !== 'embed') return;
      if (cat === 'model' && /\.(glb|gltf)(\?|$)/i.test(url)) {
        vm.renderDetailModel(el, url, w, h);
      } else if (cat === 'model') {
        // Not a valid model URL, skip
      } else if (cat === 'image') {
        var img = document.createElement('img');
        img.src = url;
        img.style.cssText = 'width:100%;height:100%;object-fit:contain;';
        el.appendChild(img);
        vm.detailPreviewing = true;
      } else if (cat === 'video') {
        var video = document.createElement('video');
        video.src = url; video.controls = true; video.autoplay = true; video.muted = true;
        video.style.cssText = 'width:100%;height:100%;object-fit:contain;background:#000;';
        el.appendChild(video);
        vm.detailPreviewing = true;
      } else if (cat === 'panorama') {
        vm.renderDetailPanorama(el, url, w, h);
      } else if (cat === 'embed' || f.source_type === 'embed') {
        var iframe = document.createElement('iframe');
        iframe.src = f.embed_src || url;
        iframe.allow = 'autoplay; fullscreen';
        iframe.style.cssText = 'width:100%;height:100%;border:none;';
        el.appendChild(iframe);
        vm.detailPreviewing = true;
      }
    },
    renderDetailModel: function(el, url, w, h) {
      var vm = this;
      var scene = new THREE.Scene();
      scene.background = new THREE.Color(0x12121e);
      var camera = new THREE.PerspectiveCamera(45, w / h, 0.01, 100);
      var renderer = new THREE.WebGLRenderer({ antialias: true });
      renderer.setSize(w, h);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.outputEncoding = THREE.sRGBEncoding;
      el.appendChild(renderer.domElement);
      vm._detailRenderer = renderer;
      scene.add(new THREE.AmbientLight(0xffffff, 0.6));
      var dir = new THREE.DirectionalLight(0xffffff, 0.8);
      dir.position.set(5, 10, 7); scene.add(dir);
      var theta = 0, lookY = 0.5;
      camera.position.set(0, 1.5, 3);
      var loader = new THREE.GLTFLoader();
      loader.load(url, function(gltf) {
        var model = gltf.scene;
        var box = new THREE.Box3().setFromObject(model);
        var size = box.getSize(new THREE.Vector3());
        var center = box.getCenter(new THREE.Vector3());
        var s = 1.8 / Math.max(size.x, size.y, size.z);
        model.scale.setScalar(s);
        model.position.sub(center.multiplyScalar(s));
        scene.add(model);
        var wb = new THREE.Box3().setFromObject(model);
        lookY = (wb.min.y + wb.max.y) / 2;
        if (gltf.animations && gltf.animations.length) {
          vm._detailMixer = new THREE.AnimationMixer(model);
          gltf.animations.forEach(function(c) { vm._detailMixer.clipAction(c).play(); });
        }
        vm.detailPreviewing = true;
      }, undefined, function() {
        // Load error — clean up
        vm.cleanupDetailPreview();
      });
      var clock = new THREE.Clock();
      function animate() {
        vm._detailAnimId = requestAnimationFrame(animate);
        theta += 0.008;
        camera.position.x = 3 * Math.sin(theta);
        camera.position.z = 3 * Math.cos(theta);
        camera.position.y = 1.5;
        camera.lookAt(0, lookY, 0);
        if (vm._detailMixer) vm._detailMixer.update(clock.getDelta());
        renderer.render(scene, camera);
      }
      animate();
    },
    renderDetailPanorama: function(el, url, w, h) {
      var vm = this;
      var img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = function() {
        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(75, w / h, 0.1, 1000);
        var renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(w, h);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        el.appendChild(renderer.domElement);
        vm._detailRenderer = renderer;
        var tex = new THREE.Texture(img);
        tex.needsUpdate = true;
        var geom = new THREE.SphereGeometry(50, 40, 30);
        geom.scale(-1, 1, 1);
        scene.add(new THREE.Mesh(geom, new THREE.MeshBasicMaterial({ map: tex })));
        vm.detailPreviewing = true;

        // 360° badge
        var badge = document.createElement('div');
        badge.className = 'pano-badge';
        badge.style.cssText = 'font-size:.6rem;padding:3px 8px;top:8px;right:8px;';
        badge.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/></svg> 360°';
        el.appendChild(badge);

        var lon = 0, lat = 0, drag = false, prev = {x:0,y:0};
        renderer.domElement.addEventListener('mousedown', function(e) { drag = true; prev = {x:e.clientX,y:e.clientY}; });
        renderer.domElement.addEventListener('mousemove', function(e) {
          if (!drag) return;
          lon -= (e.clientX - prev.x) * 0.2;
          lat += (e.clientY - prev.y) * 0.2;
          lat = Math.max(-85, Math.min(85, lat));
          prev = {x:e.clientX,y:e.clientY};
        });
        window.addEventListener('mouseup', function() { drag = false; });

        // Touch support
        var touchS = null;
        renderer.domElement.addEventListener('touchstart', function(e) {
          if (e.touches.length === 1) { drag = true; touchS = {x:e.touches[0].clientX,y:e.touches[0].clientY}; }
        });
        renderer.domElement.addEventListener('touchmove', function(e) {
          e.preventDefault();
          if (e.touches.length === 1 && touchS) {
            lon -= (e.touches[0].clientX - touchS.x) * 0.2;
            lat += (e.touches[0].clientY - touchS.y) * 0.2;
            lat = Math.max(-85, Math.min(85, lat));
            touchS = {x:e.touches[0].clientX,y:e.touches[0].clientY};
          }
        }, {passive:false});
        renderer.domElement.addEventListener('touchend', function() { drag = false; });

        function animate() {
          vm._detailAnimId = requestAnimationFrame(animate);
          if (!drag) lon += 0.08;
          var phi = THREE.MathUtils.degToRad(90 - lat);
          var th = THREE.MathUtils.degToRad(lon);
          camera.lookAt(50*Math.sin(phi)*Math.cos(th), 50*Math.cos(phi), 50*Math.sin(phi)*Math.sin(th));
          renderer.render(scene, camera);
        }
        animate();
      };
      img.onerror = function() {
        // Fallback: show as flat image
        var fallback = document.createElement('img');
        fallback.src = url;
        fallback.style.cssText = 'width:100%;height:100%;object-fit:contain;';
        el.appendChild(fallback);
        vm.detailPreviewing = true;
      };
      img.src = url;
    },
    cleanupDetailPreview: function() {
      if (this._detailAnimId) cancelAnimationFrame(this._detailAnimId);
      if (this._detailRenderer) { this._detailRenderer.dispose(); this._detailRenderer = null; }
      this._detailMixer = null;
      this._detailAnimId = null;
      if (this.$refs.detailLive) this.$refs.detailLive.innerHTML = '';
    },
    timeAgo: function(d) {
      if (!d) return '';
      var s = Math.floor((Date.now() - new Date(d).getTime()) / 1000);
      if (s < 60) return 'เมื่อสักครู่';
      if (s < 3600) return Math.floor(s/60) + ' นาที';
      if (s < 86400) return Math.floor(s/3600) + ' ชม.';
      return Math.floor(s/86400) + ' วัน';
    }
  }
});
feather.replace();
</script>
</body>
</html>
