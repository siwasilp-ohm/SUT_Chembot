<?php
require_once __DIR__ . '/../core/config.php';
require_login();
$user = auth_user();

// Load upload settings from DB
$pdo = db();
$uploadSettingsRaw = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('allowed_extensions','max_upload_size')")->fetchAll(PDO::FETCH_KEY_PAIR);
$allowedExtStr = $uploadSettingsRaw['allowed_extensions'] ?? implode(',', ALLOWED_EXT);
$allowedExtArr = array_map('trim', explode(',', $allowedExtStr));
$allowedExtArr = array_filter($allowedExtArr);
$maxUploadSize = (int)($uploadSettingsRaw['max_upload_size'] ?? MAX_UPLOAD_SIZE);
$maxUploadMB   = round($maxUploadSize / 1048576);
$acceptAttr    = implode(',', array_map(function($e){ return '.' . $e; }, $allowedExtArr));

// Load iframe auto-config settings
$iframeSettingsRaw = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'iframe_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$iframeConf = [
    'kiri_bg_theme'  => $iframeSettingsRaw['iframe_kiri_bg_theme'] ?? 'transparent',
    'kiri_auto_spin' => $iframeSettingsRaw['iframe_kiri_auto_spin'] ?? '1',
    'default_params' => $iframeSettingsRaw['iframe_default_params'] ?? 'bg_theme=transparent&auto_spin_model=1',
    'default_attrs'  => $iframeSettingsRaw['iframe_default_attrs'] ?? 'frameborder="0" allowfullscreen mozallowfullscreen webkitallowfullscreen allow="autoplay; fullscreen;" execution-while-out-of-viewport execution-while-not-rendered',
    'width'          => $iframeSettingsRaw['iframe_width'] ?? '640',
    'height'         => $iframeSettingsRaw['iframe_height'] ?? '480',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>อัปโหลด — VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
<style>
/* ═══════════════════════════════════════════════
   Upload Page — Scoped Styles
   ═══════════════════════════════════════════════ */

/* Page Hero */
.upload-hero {
  text-align:center; padding:28px 20px 20px;
  background:linear-gradient(135deg, rgba(108,92,231,.06) 0%, rgba(0,206,201,.04) 100%);
  border:1px solid var(--border); border-radius:var(--radius-lg);
  margin-bottom:24px;
}
.upload-hero h1 {
  font-size:1.3rem; font-weight:700; margin:0 0 4px;
  display:flex; align-items:center; justify-content:center; gap:10px;
}
.upload-hero p { font-size:.82rem; color:var(--text-secondary); margin:0; }

/* Mode Tabs */
.mode-tabs {
  display:flex; background:var(--bg-card); border-radius:var(--radius-lg);
  border:1px solid var(--border); overflow:hidden; margin-bottom:24px;
}
.mode-tab {
  flex:1; padding:14px 16px; text-align:center; cursor:pointer;
  background:none; border:none; color:var(--text-muted);
  font-size:.85rem; font-weight:600; transition:all .25s;
  display:flex; align-items:center; justify-content:center; gap:8px;
  position:relative;
}
.mode-tab:hover { color:var(--text); background:rgba(255,255,255,.02); }
.mode-tab.active {
  color:var(--primary); background:rgba(108,92,231,.08);
}
.mode-tab.active::after {
  content:''; position:absolute; bottom:0; left:16px; right:16px;
  height:2.5px; background:var(--primary); border-radius:2px 2px 0 0;
}
.mode-tab svg { width:18px; height:18px; flex-shrink:0; }

/* Dropzone */
.upload-drop {
  border:2px dashed var(--border); border-radius:var(--radius-lg);
  padding:48px 30px; text-align:center; cursor:pointer;
  transition:all .3s; background:rgba(108,92,231,.02);
}
.upload-drop:hover, .upload-drop.dragover {
  border-color:var(--primary); background:rgba(108,92,231,.06);
}
.upload-drop .drop-icon {
  width:56px; height:56px; margin:0 auto 14px; color:var(--text-muted);
  transition:color .3s;
}
.upload-drop:hover .drop-icon { color:var(--primary); }
.upload-drop h3 { font-size:1rem; margin-bottom:6px; font-weight:600; }
.upload-drop p { font-size:.8rem; color:var(--text-muted); margin:0; }
.upload-drop .file-types {
  margin-top:12px; display:flex; flex-wrap:wrap; gap:5px; justify-content:center;
}
.upload-drop .file-types span {
  padding:2px 10px; border-radius:12px; font-size:.65rem; font-weight:600;
  background:rgba(108,92,231,.1); color:var(--primary); text-transform:uppercase;
}

/* File Card */
.file-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); overflow:hidden; margin-top:20px;
}
.file-card-header {
  display:flex; align-items:center; gap:14px;
  padding:18px 20px; border-bottom:1px solid rgba(255,255,255,.04);
  background:rgba(108,92,231,.04);
}
.file-card-icon {
  width:44px; height:44px; border-radius:10px;
  background:rgba(108,92,231,.12); display:flex;
  align-items:center; justify-content:center; flex-shrink:0;
}
.file-card-icon svg { width:22px; height:22px; color:var(--primary); }
.file-card-meta h3 { font-size:.95rem; font-weight:600; margin:0; word-break:break-all; }
.file-card-meta p { font-size:.78rem; color:var(--text-muted); margin:2px 0 0; }
.file-card-body { padding:20px; }

/* Form Layout */
.form-row {
  display:grid; grid-template-columns:1fr 1fr; gap:16px;
}
.form-row .full { grid-column:1/-1; }
@media (max-width:560px) { .form-row { grid-template-columns:1fr; } }
.form-field { margin-bottom:16px; }
.form-field label {
  display:flex; align-items:center; gap:4px; margin-bottom:6px;
  font-size:.78rem; font-weight:600; color:var(--text-secondary);
}
.form-field label .req { color:var(--danger); font-size:.7rem; }
.form-field .hint {
  font-size:.7rem; color:var(--text-muted); margin-top:4px; line-height:1.4;
}
.form-field .hint code {
  background:rgba(108,92,231,.1); color:var(--primary);
  padding:1px 6px; border-radius:3px; font-size:.68rem;
}

/* Progress Bar */
.progress-wrap { margin:16px 0; }
.progress-info {
  display:flex; justify-content:space-between; font-size:.78rem;
  color:var(--text-secondary); margin-bottom:6px;
}
.progress-bar {
  height:6px; background:var(--bg-surface); border-radius:4px;
  overflow:hidden; position:relative;
}
.progress-fill {
  height:100%; background:linear-gradient(90deg, var(--primary), #00CEC9);
  border-radius:4px; transition:width .4s ease;
}
.progress-bar.complete .progress-fill { background:var(--success); }

/* Embed Section */
.embed-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); overflow:hidden;
}
.embed-card-header {
  padding:24px 20px 18px; text-align:center;
  background:linear-gradient(135deg, rgba(108,92,231,.07), rgba(0,206,201,.04));
  border-bottom:1px solid var(--border);
}
.embed-card-header svg { margin-bottom:8px; }
.embed-card-header h3 { font-size:1rem; font-weight:700; margin:0 0 4px; }
.embed-card-header p { font-size:.78rem; color:var(--text-muted); margin:0; }
.embed-card-body { padding:20px; }

/* Step Markers */
.step-marker {
  display:flex; align-items:center; gap:10px;
  padding-bottom:12px; margin-bottom:16px;
  border-bottom:1px solid rgba(255,255,255,.04);
}
.step-num {
  width:24px; height:24px; border-radius:50%; flex-shrink:0;
  background:var(--primary); color:#fff;
  font-size:.7rem; font-weight:700;
  display:flex; align-items:center; justify-content:center;
}
.step-label { font-size:.85rem; font-weight:600; }

/* Provider Pill */
.provider-pill {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:600;
  margin-top:5px;
}
.provider-pill.ok { background:rgba(0,184,148,.1); color:var(--success); }
.provider-pill.default { background:rgba(108,92,231,.1); color:var(--primary); }

/* Code Textarea */
.code-area {
  font-family:'Fira Code','Cascadia Code','Consolas',monospace !important;
  font-size:.8rem !important; color:#e2b714 !important;
  min-height:80px; line-height:1.55; tab-size:2;
  white-space:pre-wrap; word-break:break-all;
}
.code-area::placeholder { color:rgba(255,255,255,.15) !important; }

/* Preview Box */
.preview-box {
  border-radius:var(--radius-lg); overflow:hidden;
  border:1px solid var(--border); background:#000; position:relative;
  margin-top:12px;
}
.preview-box-inner { width:100%; aspect-ratio:16/9; min-height:280px; }
.preview-box-inner iframe { width:100%; height:100%; border:none; }
.preview-label {
  position:absolute; top:10px; left:10px; z-index:5;
  background:rgba(0,0,0,.75); backdrop-filter:blur(8px);
  padding:4px 12px; border-radius:20px; font-size:.65rem;
  color:var(--primary); display:flex; align-items:center; gap:5px;
}
.preview-empty {
  text-align:center; padding:50px 20px; color:var(--text-muted);
}
.preview-empty svg { margin-bottom:8px; opacity:.25; }
.preview-empty p { font-size:.82rem; margin:0; }

/* Action Bar */
.action-bar {
  display:flex; gap:10px; padding-top:6px;
}

/* History List */
.history-section { margin-top:28px; }
.history-title {
  font-size:.95rem; font-weight:700; margin-bottom:14px;
  display:flex; align-items:center; gap:8px;
}
.history-item {
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 18px; margin-bottom:8px;
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); transition:border-color .2s;
}
.history-item:hover { border-color:var(--primary); }
.history-info h4 { font-size:.88rem; font-weight:600; margin:0; }
.history-info p { font-size:.72rem; color:var(--text-muted); margin:3px 0 0; }
.history-info .embed-tag {
  color:var(--primary); font-weight:600; margin-right:4px;
}
.history-actions { display:flex; gap:6px; flex-shrink:0; }

/* Responsive */
@media (max-width:480px) {
  .upload-hero { padding:20px 14px; }
  .upload-hero h1 { font-size:1.1rem; }
  .file-card-body, .embed-card-body { padding:16px; }
  .history-item { flex-direction:column; align-items:flex-start; gap:10px; }
  .history-actions { width:100%; }
  .history-actions .btn { flex:1; justify-content:center; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-content">
<div class="container" id="app" v-cloak>

  <!-- Hero -->
  <div class="upload-hero">
    <h1>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      อัปโหลดไฟล์
    </h1>
    <p>อัปโหลดโมเดล 3D, รูปภาพ, วิดีโอ หรือเพิ่ม Embed จากแพลตฟอร์มภายนอก</p>
  </div>

  <!-- Mode Tabs -->
  <div class="mode-tabs">
    <button class="mode-tab" :class="{ active: mode === 'file' }" @click="switchMode('file')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      อัปโหลดไฟล์
    </button>
    <button class="mode-tab" :class="{ active: mode === 'embed' }" @click="switchMode('embed')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
      Embed / iFrame
    </button>
  </div>

  <!-- ═══════════════════════════════════════
       FILE UPLOAD
       ═══════════════════════════════════════ -->
  <div v-if="mode === 'file'">

    <!-- Dropzone -->
    <div class="upload-drop" :class="{ dragover: isDrag }"
         @dragover.prevent="isDrag=true" @dragleave="isDrag=false"
         @drop.prevent="onDrop($event)" @click="$refs.fileInput.click()">
      <svg class="drop-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
      <h3>ลากไฟล์มาวางที่นี่</h3>
      <p>หรือคลิกเพื่อเลือกไฟล์ — สูงสุด <?= $maxUploadMB ?> MB</p>
      <div class="file-types">
        <?php foreach ($allowedExtArr as $ext): ?><span><?= strtoupper(htmlspecialchars($ext)) ?></span><?php endforeach; ?>
      </div>
      <input type="file" ref="fileInput" @change="onSelect($event)" style="display:none"
             accept="<?= htmlspecialchars($acceptAttr) ?>">
    </div>

    <!-- File Detail Card -->
    <div class="file-card" v-if="file">
      <div class="file-card-header">
        <div class="file-card-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path v-if="fileForm.category==='model'" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <rect v-else-if="fileForm.category==='image'" x="3" y="3" width="18" height="18" rx="2"/><circle v-if="fileForm.category==='image'" cx="8.5" cy="8.5" r="1.5"/><polyline v-if="fileForm.category==='image'" points="21 15 16 10 5 21"/>
            <rect v-else-if="fileForm.category==='video'" x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line v-if="fileForm.category==='video'" x1="7" y1="2" x2="7" y2="22"/><line v-if="fileForm.category==='video'" x1="17" y1="2" x2="17" y2="22"/>
            <path v-else d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline v-if="!['model','image','video'].includes(fileForm.category)" points="14 2 14 8 20 8"/>
          </svg>
        </div>
        <div class="file-card-meta">
          <h3>{{ file.name }}</h3>
          <p>{{ fmtSize(file.size) }} · {{ fileExt }}</p>
        </div>
      </div>

      <div class="file-card-body">
        <div class="form-row">
          <div class="form-field full">
            <label>ชื่อ <span class="req">*</span></label>
            <input type="text" v-model="fileForm.title" placeholder="ตั้งชื่อไฟล์">
          </div>

          <div class="form-field">
            <label>หมวดหมู่</label>
            <select v-model="fileForm.category">
              <option value="model">🧊 โมเดล 3D</option>
              <option value="panorama">🌍 พาโนรามา</option>
              <option value="image">🖼️ รูปภาพ</option>
              <option value="video">🎬 วิดีโอ</option>
              <option value="document">📄 เอกสาร</option>
            </select>
          </div>

          <div class="form-field">
            <label>การเข้าถึง</label>
            <select v-model="fileForm.visibility">
              <option value="public">🌐 สาธารณะ</option>
              <option value="unlisted">🔗 Unlisted</option>
              <option value="private">🔒 ส่วนตัว</option>
            </select>
          </div>

          <div class="form-field full">
            <label>คำอธิบาย</label>
            <textarea v-model="fileForm.description" placeholder="คำอธิบายไฟล์ (ไม่บังคับ)"></textarea>
          </div>
        </div>

        <!-- Progress -->
        <div class="progress-wrap" v-if="uploading">
          <div class="progress-info">
            <span>{{ uploadStage }}</span>
            <span>{{ progress }}%</span>
          </div>
          <div class="progress-bar" :class="{ complete: progress >= 100 }">
            <div class="progress-fill" :style="{ width: progress + '%' }"></div>
          </div>
        </div>

        <div class="alert alert-error" v-if="fileError">{{ fileError }}</div>
        <div class="alert alert-success" v-if="fileSuccess">{{ fileSuccess }}</div>

        <div class="action-bar">
          <button class="btn btn-primary btn-block" @click="doUpload()" :disabled="uploading || !fileForm.title.trim()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            {{ uploading ? 'กำลังอัปโหลด...' : 'อัปโหลด' }}
          </button>
          <button class="btn btn-outline" @click="resetFile()" :disabled="uploading">ยกเลิก</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════
       EMBED / IFRAME
       ═══════════════════════════════════════ -->
  <div v-if="mode === 'embed'">
    <div class="embed-card">

      <div class="embed-card-header">
        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5">
          <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
        </svg>
        <h3>เพิ่ม Embed / iFrame</h3>
        <p>กรอก URL หรือวางโค้ด iframe พร้อมรายละเอียดเนื้อหา</p>
      </div>

      <div class="embed-card-body">

        <!-- STEP 1 -->
        <div class="step-marker">
          <span class="step-num">1</span>
          <span class="step-label">แหล่งที่มา (iFrame)</span>
        </div>

        <!-- Auto Config Kiri -->
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;margin-bottom:16px;background:linear-gradient(135deg,rgba(108,92,231,.06),rgba(0,206,201,.04));border:1px solid var(--border);border-radius:10px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.84rem;font-weight:600;margin:0;">
            <input type="checkbox" v-model="kiriAutoConfig" @change="onAutoConfigChange()" style="width:16px;height:16px;accent-color:var(--primary);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            Auto Config (Kiri Engine)
          </label>
          <span style="font-size:.72rem;color:var(--text-muted);">
            เปิดเพื่อตัดแต่ง URL อัตโนมัติ — เปลี่ยน shareModel→embed, ตัด params เก่า, ต่อท้ายค่าจากระบบ
          </span>
        </div>

        <div class="form-field">
          <label>Embed URL <span class="req">*</span></label>
          <input type="url" v-model="embedForm.src" @input="onEmbedSrcInput()"
                 placeholder="https://www.kiriengine.app/share/embed/...">
          <div class="hint">
            URL ต้นทางของ iframe เช่น <code>https://sketchfab.com/models/.../embed</code>
          </div>
          <span class="provider-pill" :class="detectedProvider ? 'ok' : 'default'" v-if="detectedProvider">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            {{ detectedProvider }}
          </span>
        </div>

        <div class="form-field">
          <label>iFrame Code <span style="font-weight:400;color:var(--text-muted);">(ไม่บังคับ)</span></label>
          <textarea class="code-area" v-model="embedCode" @paste="onPasteCode($event)" @input="parseCode()"
                    placeholder='<iframe src="..." title="..." allowfullscreen></iframe>'></textarea>
          <div class="hint">
            วาง <code>&lt;iframe&gt;</code> โค้ดเต็มจากเว็บไซต์ ระบบจะดึง URL, Title ให้อัตโนมัติ
          </div>
        </div>

        <div style="height:1px; background:var(--border); margin:22px 0;"></div>

        <!-- STEP 2 -->
        <div class="step-marker">
          <span class="step-num">2</span>
          <span class="step-label">รายละเอียดเนื้อหา</span>
        </div>

        <div class="form-row">
          <div class="form-field full">
            <label>ชื่อ <span class="req">*</span></label>
            <input type="text" v-model="embedForm.title" placeholder="ชื่อโมเดล / เนื้อหา">
          </div>

          <div class="form-field">
            <label>แพลตฟอร์ม</label>
            <select v-model="embedForm.provider">
              <option value="">— ตรวจจับอัตโนมัติ —</option>
              <option value="Kiri Engine">Kiri Engine</option>
              <option value="Sketchfab">Sketchfab</option>
              <option value="YouTube">YouTube</option>
              <option value="Matterport">Matterport</option>
              <option value="Google Maps">Google Maps</option>
              <option value="Vimeo">Vimeo</option>
              <option value="Polycam">Polycam</option>
              <option value="Luma AI">Luma AI</option>
              <option value="Other">อื่นๆ</option>
            </select>
          </div>

          <div class="form-field">
            <label>การเข้าถึง</label>
            <select v-model="embedForm.visibility">
              <option value="public">🌐 สาธารณะ</option>
              <option value="unlisted">🔗 Unlisted</option>
              <option value="private">🔒 ส่วนตัว</option>
            </select>
          </div>

          <div class="form-field full">
            <label>คำอธิบาย</label>
            <textarea v-model="embedForm.description" style="font-family:inherit;"
                      placeholder="รายละเอียดเกี่ยวกับโมเดล, เทคนิคการสแกน, แหล่งที่มา ฯลฯ"></textarea>
          </div>
        </div>

        <div style="height:1px; background:var(--border); margin:22px 0;"></div>

        <!-- STEP 3 -->
        <div class="step-marker">
          <span class="step-num">3</span>
          <span class="step-label">ตัวอย่าง Preview</span>
        </div>

        <div v-if="embedForm.src.trim()">
          <div class="preview-box">
            <div class="preview-label">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Live Preview
            </div>
            <div class="preview-box-inner" id="embed-preview-box"></div>
          </div>
          <div style="text-align:center; margin-top:8px;">
            <button class="btn btn-sm btn-outline" @click="renderEmbedPreview()" style="font-size:.75rem;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
              รีเฟรช
            </button>
          </div>
        </div>
        <div v-else class="preview-empty">
          <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
          </svg>
          <p>กรอก Embed URL เพื่อแสดงตัวอย่าง</p>
        </div>

        <div style="height:1px; background:var(--border); margin:22px 0;"></div>

        <div class="alert alert-error" v-if="embedError">{{ embedError }}</div>
        <div class="alert alert-success" v-if="embedSuccess">{{ embedSuccess }}</div>

        <div class="action-bar">
          <button class="btn btn-primary btn-block" @click="saveEmbed()"
                  :disabled="embedSaving || !embedForm.src.trim() || !embedForm.title.trim()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            {{ embedSaving ? 'กำลังบันทึก...' : 'บันทึก Embed' }}
          </button>
          <button class="btn btn-outline" @click="resetEmbed()">ยกเลิก</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════
       SESSION HISTORY
       ═══════════════════════════════════════ -->
  <div class="history-section" v-if="history.length">
    <div class="history-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      อัปโหลดล่าสุด (เซสชันนี้)
    </div>
    <div class="history-item" v-for="h in history" :key="h.id">
      <div class="history-info">
        <h4>
          <span class="embed-tag" v-if="h.source_type==='embed'">⟨/⟩</span>
          {{ h.title }}
        </h4>
        <p>{{ h.category }} · {{ h.source_type === 'embed' ? h.provider : fmtSize(h.size) }}</p>
      </div>
      <div class="history-actions">
        <button class="btn btn-sm btn-outline" @click="goViewer(h)" title="ดู">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          ดู
        </button>
        <button class="btn btn-sm btn-outline" @click="goAR(h)" title="AR"
                v-if="h.category==='model' || h.category==='embed'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
          AR
        </button>
        <a :href="BASE+'/pages/gallery.php'" class="btn btn-sm btn-outline" title="แกลเลอรี">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        </a>
      </div>
    </div>
  </div>

</div>
</div>

<?php include __DIR__ . '/../includes/bottom_nav.php'; ?>

<script src="<?= BASE_URL ?>/third_party/vue.min.js"></script>
<script>
var BASE = '<?= BASE_URL ?>';

new Vue({
  el: '#app',
  data: function() {
    return {
      BASE: BASE,
      mode: 'file',
      allowedExt: <?= json_encode(array_values($allowedExtArr)) ?>,
      maxUploadSize: <?= $maxUploadSize ?>,
      maxUploadMB: <?= $maxUploadMB ?>,

      /* ── File Upload ── */
      file: null,
      isDrag: false,
      uploading: false,
      progress: 0,
      uploadStage: 'กำลังอัปโหลด...',
      fileError: '',
      fileSuccess: '',
      fileForm: {
        title: '',
        category: 'model',
        description: '',
        visibility: 'public'
      },

      /* ── Embed ── */
      embedCode: '',
      embedForm: {
        src: '',
        title: '',
        description: '',
        visibility: 'public',
        provider: ''
      },
      detectedProvider: '',
      embedError: '',
      embedSuccess: '',
      embedSaving: false,
      previewTimer: null,
      kiriAutoConfig: true,
      kiriSettings: <?= json_encode($iframeConf, JSON_UNESCAPED_UNICODE) ?>,

      /* ── Shared ── */
      history: []
    };
  },

  computed: {
    fileExt: function() {
      if (!this.file) return '';
      return this.file.name.split('.').pop().toUpperCase();
    }
  },

  methods: {

    /* ═══════════════════════════════════════
       Mode Switching
       ═══════════════════════════════════════ */
    switchMode: function(m) {
      this.mode = m;
      if (m === 'file') this.resetEmbed();
      else this.resetFile();
    },

    /* ═══════════════════════════════════════
       File Upload
       ═══════════════════════════════════════ */
    onSelect: function(e) {
      if (e.target.files.length) this.setFile(e.target.files[0]);
    },
    onDrop: function(e) {
      this.isDrag = false;
      if (e.dataTransfer.files.length) this.setFile(e.dataTransfer.files[0]);
    },
    setFile: function(f) {
      this.file = null;
      this.fileError = '';
      this.fileSuccess = '';

      // Validate extension against allowed list
      var ext = f.name.split('.').pop().toLowerCase();
      if (this.allowedExt.indexOf(ext) < 0) {
        this.fileError = 'ไม่อนุญาตนามสกุล .' + ext + ' — อนุญาตเฉพาะ: ' + this.allowedExt.join(', ');
        return;
      }

      this.file = f;
      this.fileForm.title = f.name.replace(/\.[^.]+$/, '');

      // Auto-detect category
      if (['glb','gltf','obj','fbx','stl','dae','3ds','ply','usdz'].indexOf(ext) >= 0) this.fileForm.category = 'model';
      else if (['jpg','jpeg','png','webp','gif','svg','bmp','hdr','tiff','tif','ico'].indexOf(ext) >= 0) this.fileForm.category = 'image';
      else if (['mp4','webm','mov','avi','mkv','wmv'].indexOf(ext) >= 0) this.fileForm.category = 'video';
      else if (['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv'].indexOf(ext) >= 0) this.fileForm.category = 'document';
    },

    doUpload: function() {
      var vm = this;
      if (!vm.file) return;
      if (vm.file.size > vm.maxUploadSize) { vm.fileError = 'ไฟล์ใหญ่เกิน ' + vm.maxUploadMB + ' MB'; return; }

      vm.uploading = true;
      vm.progress = 0;
      vm.uploadStage = 'กำลังอัปโหลดไฟล์...';
      vm.fileError = '';
      vm.fileSuccess = '';

      // Step 1: Upload binary
      var fd = new FormData();
      fd.append('file', vm.file);

      var xhr = new XMLHttpRequest();
      xhr.open('POST', BASE + '/api/index.php?action=upload');
      xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) vm.progress = Math.round((e.loaded / e.total) * 90);
      };
      xhr.onload = function() {
        try {
          var res = JSON.parse(xhr.responseText);
          if (xhr.status === 200 && res.data) {
            vm.progress = 92;
            vm.uploadStage = 'กำลังบันทึกข้อมูล...';
            vm.createFileRecord(res.data);
          } else {
            vm.fileError = res.error || 'อัปโหลดล้มเหลว';
            vm.uploading = false;
          }
        } catch (err) {
          vm.fileError = 'เซิร์ฟเวอร์ตอบกลับผิดพลาด';
          vm.uploading = false;
        }
      };
      xhr.onerror = function() {
        vm.fileError = 'เครือข่ายขัดข้อง — ตรวจสอบการเชื่อมต่อ';
        vm.uploading = false;
      };
      xhr.send(fd);
    },

    createFileRecord: function(upload) {
      var vm = this;
      fetch(BASE + '/api/index.php?action=files', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: vm.fileForm.title,
          category: vm.fileForm.category,
          description: vm.fileForm.description,
          visibility: vm.fileForm.visibility,
          file_url: upload.file_url,
          file_path: upload.file_path,
          file_type: upload.mime_type || vm.file.type || '',
          file_size: upload.file_size || vm.file.size,
          extension: upload.extension,
          original_name: upload.original_name || vm.file.name,
          source_type: 'upload'
        })
      })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.data && d.data.id) {
          vm.progress = 100;
          vm.uploadStage = 'เสร็จสมบูรณ์!';
          vm.fileSuccess = 'อัปโหลดสำเร็จ — ' + vm.fileForm.title;
          vm.history.unshift({
            id: d.data.id,
            title: vm.fileForm.title,
            category: vm.fileForm.category,
            size: vm.file.size,
            file_url: upload.file_url,
            source_type: 'upload'
          });
          setTimeout(function() {
            vm.file = null;
            vm.fileForm = { title:'', category:'model', description:'', visibility:'public' };
            vm.uploading = false;
            vm.progress = 0;
          }, 1200);
        } else {
          vm.fileError = d.error || 'สร้างรายการล้มเหลว';
          vm.uploading = false;
        }
      })
      .catch(function() {
        vm.fileError = 'บันทึกข้อมูลล้มเหลว';
        vm.uploading = false;
      });
    },

    resetFile: function() {
      this.file = null;
      this.fileForm = { title:'', category:'model', description:'', visibility:'public' };
      this.fileError = '';
      this.fileSuccess = '';
      this.uploading = false;
      this.progress = 0;
    },

    /* ═══════════════════════════════════════
       Embed / iFrame
       ═══════════════════════════════════════ */
    autoConfigUrl: function(url) {
      if (!url) return url;
      // Replace sharemodel → embed (case-insensitive), keep /share/ intact
      url = url.replace(/\/sharemodel(\/|$)/gi, '/embed$1');
      // Split URL and query
      var parts = url.split('?');
      var base = parts[0];
      var origQuery = parts[1] || '';
      var ks = this.kiriSettings;

      // Parse original params into map (keeps userId from source)
      var paramMap = {};
      if (origQuery) {
        origQuery.split('&').forEach(function(p) {
          var kv = p.split('=');
          if (kv[0]) paramMap[kv[0]] = kv[1] || '';
        });
      }

      // Override only bg_theme + auto_spin_model + extras from settings
      paramMap['bg_theme'] = ks.kiri_bg_theme;
      paramMap['auto_spin_model'] = ks.kiri_auto_spin;
      if (ks.default_params) {
        ks.default_params.split('&').forEach(function(p) {
          var kv = p.split('=');
          if (kv[0] && !(kv[0] in paramMap)) paramMap[kv[0]] = kv[1] || '';
        });
      }

      var newParams = Object.keys(paramMap).map(function(k) { return k + '=' + paramMap[k]; }).join('&');
      return base + '?' + newParams;
    },

    autoConfigCode: function(code) {
      if (!code) return code;
      var vm = this;
      // Replace all src URLs in iframe tags
      return code.replace(/src\s*=\s*["']([^"']+)["']/gi, function(match, url) {
        var newUrl = vm.autoConfigUrl(url);
        return 'src="' + newUrl + '"';
      });
    },

    onAutoConfigChange: function() {
      if (this.kiriAutoConfig) {
        // Re-apply auto config to current values
        if (this.embedForm.src) {
          this.embedForm.src = this.autoConfigUrl(this.embedForm.src);
        }
        if (this.embedCode) {
          this.embedCode = this.autoConfigCode(this.embedCode);
        }
        this.renderEmbedPreview();
      }
    },

    onEmbedSrcInput: function() {
      var vm = this;
      var url = vm.embedForm.src.trim();

      // Auto-config Kiri if enabled and is kiri URL
      if (vm.kiriAutoConfig && url.indexOf('kiriengine') >= 0) {
        url = vm.autoConfigUrl(url);
        vm.embedForm.src = url;
      }

      vm.detectedProvider = vm.detectProvider(url);

      // Auto-fill provider dropdown
      if (vm.detectedProvider && !vm.embedForm.provider) {
        vm.embedForm.provider = vm.detectedProvider;
      }

      // Debounced preview
      clearTimeout(vm.previewTimer);
      if (url) {
        vm.previewTimer = setTimeout(function() { vm.renderEmbedPreview(); }, 700);
      } else {
        var c = document.getElementById('embed-preview-box');
        if (c) c.innerHTML = '';
      }
    },

    onPasteCode: function() {
      var vm = this;
      setTimeout(function() { vm.parseCode(); }, 60);
    },

    parseCode: function() {
      var vm = this;
      var code = vm.embedCode.trim();
      if (!code) return;

      // Auto-config Kiri if enabled
      if (vm.kiriAutoConfig && code.indexOf('kiriengine') >= 0) {
        code = vm.autoConfigCode(code);
        vm.embedCode = code;
      }

      var srcMatch = code.match(/src\s*=\s*["']([^"']+)["']/i);
      var titleMatch = code.match(/title\s*=\s*["']([^"']+)["']/i);

      if (srcMatch && !vm.embedForm.src) {
        vm.embedForm.src = srcMatch[1];
        vm.onEmbedSrcInput();
      }
      if (titleMatch && !vm.embedForm.title) {
        vm.embedForm.title = titleMatch[1];
      }
    },

    renderEmbedPreview: function() {
      var vm = this;
      var el = document.getElementById('embed-preview-box');
      if (!el) return;
      el.innerHTML = '';

      var src = vm.embedForm.src.trim();
      if (!src) return;

      var code = vm.embedCode.trim();

      if (code && code.match(/<iframe[\s\S]*<\/iframe>/i)) {
        var fixed = code.replace(/style\s*=\s*["'][^"']*["']/gi, '')
                        .replace(/<iframe/i, '<iframe style="width:100%;height:100%;border:none;"');
        el.innerHTML = fixed;
      } else {
        var iframe = document.createElement('iframe');
        iframe.src = src;
        iframe.style.cssText = 'width:100%;height:100%;border:none;';
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('allow', 'autoplay; fullscreen');
        el.appendChild(iframe);
      }
    },

    detectProvider: function(url) {
      if (!url) return '';
      var map = [
        ['kiriengine.app', 'Kiri Engine'],
        ['sketchfab.com', 'Sketchfab'],
        ['youtube.com', 'YouTube'], ['youtu.be', 'YouTube'],
        ['matterport.com', 'Matterport'],
        ['google.com/maps', 'Google Maps'],
        ['vimeo.com', 'Vimeo'],
        ['poly.cam', 'Polycam'],
        ['lumalabs.ai', 'Luma AI'],
      ];
      for (var i = 0; i < map.length; i++) {
        if (url.indexOf(map[i][0]) >= 0) return map[i][1];
      }
      try { return new URL(url).hostname; } catch(e) { return ''; }
    },

    saveEmbed: function() {
      var vm = this;
      var src = vm.embedForm.src.trim();
      if (!src) { vm.embedError = 'กรุณากรอก Embed URL'; return; }
      if (!vm.embedForm.title.trim()) { vm.embedError = 'กรุณากรอกชื่อ'; return; }

      // Auto-config Kiri before saving
      if (vm.kiriAutoConfig && src.indexOf('kiriengine') >= 0) {
        src = vm.autoConfigUrl(src);
        vm.embedForm.src = src;
      }

      var provider = vm.embedForm.provider || vm.detectedProvider || vm.detectProvider(src) || 'Unknown';
      var code = vm.embedCode.trim();
      if (vm.kiriAutoConfig && code && code.indexOf('kiriengine') >= 0) {
        code = vm.autoConfigCode(code);
      }
      if (!code) {
        var ks = vm.kiriSettings;
        var attrs = (vm.kiriAutoConfig && src.indexOf('kiriengine') >= 0)
          ? ks.default_attrs + ' width="' + ks.width + '" height="' + ks.height + '"'
          : 'allowfullscreen style="width:100%;height:100%;border:none;"';
        code = '<iframe src="' + src + '" ' + attrs + '></iframe>';
      }

      vm.embedSaving = true;
      vm.embedError = '';
      vm.embedSuccess = '';

      fetch(BASE + '/api/index.php?action=files', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: vm.embedForm.title.trim(),
          category: 'embed',
          description: vm.embedForm.description,
          visibility: vm.embedForm.visibility,
          source_type: 'embed',
          is_external: 1,
          embed_src: src,
          embed_code: code,
          embed_provider: provider,
          file_url: src,
          file_type: 'text/html'
        })
      })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.data && d.data.id) {
          vm.embedSuccess = 'บันทึกสำเร็จ — ' + vm.embedForm.title;
          vm.history.unshift({
            id: d.data.id,
            title: vm.embedForm.title,
            category: 'embed',
            source_type: 'embed',
            provider: provider,
            embed_src: src
          });
        } else {
          vm.embedError = d.error || 'บันทึกล้มเหลว';
        }
        vm.embedSaving = false;
      })
      .catch(function() {
        vm.embedError = 'เกิดข้อผิดพลาด — ตรวจสอบการเชื่อมต่อ';
        vm.embedSaving = false;
      });
    },

    resetEmbed: function() {
      clearTimeout(this.previewTimer);
      var c = document.getElementById('embed-preview-box');
      if (c) c.innerHTML = '';
      this.embedCode = '';
      this.embedForm = { src:'', title:'', description:'', visibility:'public', provider:'' };
      this.detectedProvider = '';
      this.embedError = '';
      this.embedSuccess = '';
    },

    /* ═══════════════════════════════════════
       Navigation & Utilities
       ═══════════════════════════════════════ */
    goViewer: function(h) {
      if (h.source_type === 'embed') {
        window.location.href = BASE + '/pages/viewer.php?mode=embed&embed=' + encodeURIComponent(h.embed_src) + '&id=' + h.id;
      } else if (h.category === 'model') {
        window.location.href = BASE + '/pages/viewer.php?src=' + encodeURIComponent(h.file_url) + '&id=' + h.id;
      } else if (h.category === 'panorama') {
        window.location.href = BASE + '/pages/panorama.php?src=' + encodeURIComponent(h.file_url);
      } else if (h.file_url) {
        window.open(h.file_url, '_blank');
      }
    },

    goAR: function(h) {
      var params = '?id=' + h.id;
      if (h.source_type === 'embed') {
        params += '&mode=embed&embed=' + encodeURIComponent(h.embed_src);
      } else {
        params += '&src=' + encodeURIComponent(h.file_url);
      }
      window.location.href = BASE + '/pages/ar.php' + params;
    },

    fmtSize: function(b) {
      if (!b) return '';
      if (b < 1024) return b + ' B';
      if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
      return (b / 1048576).toFixed(1) + ' MB';
    }
  }
});
</script>
</body>
</html>
