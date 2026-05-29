<?php
require_once __DIR__ . '/../core/config.php';
require_login();
$src = isset($_GET['src']) ? $_GET['src'] : '';
$fileId = isset($_GET['file_id']) ? $_GET['file_id'] : '';

// Load QR settings from database
$pdo = db();
$qrSettingsRaw = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'qr_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$qrSettings = [
    'qr_base_url'      => $qrSettingsRaw['qr_base_url'] ?? '',
    'qr_pattern_ar'    => $qrSettingsRaw['qr_pattern_ar'] ?? '{origin}{base}/pages/ar.php?src={file_url}',
    'qr_pattern_3d'    => $qrSettingsRaw['qr_pattern_3d'] ?? '{origin}{base}/pages/viewer.php?src={file_url}',
    'qr_pattern_pano'  => $qrSettingsRaw['qr_pattern_pano'] ?? '{origin}{base}/pages/panorama.php?src={file_url}',
    'qr_pattern_embed' => $qrSettingsRaw['qr_pattern_embed'] ?? '{origin}{base}/pages/viewer.php?mode=embed&embed={embed_src}&id={id}',
    'qr_size'          => $qrSettingsRaw['qr_size'] ?? '250',
    'qr_color_dark'    => $qrSettingsRaw['qr_color_dark'] ?? '#000000',
    'qr_color_light'   => $qrSettingsRaw['qr_color_light'] ?? '#ffffff',
    'qr_error_level'   => $qrSettingsRaw['qr_error_level'] ?? 'M',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QR Code Generator — VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
<style>
/* ── QR Page Styles ── */
.qr-hero {
  background: linear-gradient(135deg, rgba(108,92,231,.18) 0%, rgba(0,206,201,.10) 100%);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 28px 32px;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.qr-hero-icon {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.qr-hero-icon svg { color: #fff; }
.qr-hero-text h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 2px; }
.qr-hero-text p { font-size: .85rem; color: var(--text-secondary); margin: 0; }

/* Main grid */
.qr-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: start;
}

/* Cards */
.qr-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.qr-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 10px;
}
.qr-card-header-icon {
  width: 32px; height: 32px;
  background: rgba(108,92,231,.15);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.qr-card-header-icon svg { width: 16px; height: 16px; color: var(--primary); }
.qr-card-header h3 { font-size: .95rem; font-weight: 600; margin: 0; }
.qr-card-body { padding: 20px; }

/* Mode selector pills */
.qr-mode-pills {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.qr-mode-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--bg-surface);
  color: var(--text-secondary);
  font-size: .82rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition);
  white-space: nowrap;
}
.qr-mode-pill:hover {
  border-color: var(--primary);
  color: var(--text);
  background: rgba(108,92,231,.08);
}
.qr-mode-pill.active {
  background: linear-gradient(135deg, var(--primary), #8b7cf7);
  border-color: var(--primary);
  color: #fff;
  box-shadow: 0 2px 12px rgba(108,92,231,.35);
}
.qr-mode-pill .pill-emoji { font-size: 1rem; }

/* QR Settings badge */
.qr-settings-info {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px 16px;
  margin-top: 16px;
  cursor: pointer;
  transition: all var(--transition);
}
.qr-settings-info:hover { border-color: var(--primary); }
.qr-settings-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: .8rem;
  font-weight: 600;
  color: var(--text-secondary);
}
.qr-settings-toggle svg { width: 14px; height: 14px; transition: transform .2s; }
.qr-settings-toggle.open svg { transform: rotate(180deg); }
.qr-settings-details {
  margin-top: 8px;
  font-size: .78rem;
  color: var(--text-muted);
  line-height: 1.7;
}
.qr-settings-details .qr-setting-row {
  display: flex; align-items: center; gap: 8px;
}
.qr-setting-dot {
  width: 14px; height: 14px;
  border-radius: 4px;
  border: 1px solid var(--border);
  display: inline-block;
  flex-shrink: 0;
}

/* Generated URL */
.qr-url-preview {
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 14px;
  margin-top: 14px;
  font-size: .78rem;
  color: var(--accent);
  word-break: break-all;
  line-height: 1.5;
  max-height: 80px;
  overflow-y: auto;
}

/* Generate button */
.qr-generate-btn {
  width: 100%;
  padding: 12px 20px;
  margin-top: 18px;
  background: linear-gradient(135deg, var(--primary), #8b7cf7);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: .92rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all var(--transition);
}
.qr-generate-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 20px rgba(108,92,231,.4);
}
.qr-generate-btn:disabled {
  opacity: .45;
  cursor: not-allowed;
}
.qr-generate-btn svg { width: 18px; height: 18px; }

/* QR Preview area */
.qr-preview-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px 16px 8px;
}
.qr-canvas-wrapper {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 180px;
  min-height: 180px;
  box-shadow: 0 4px 24px rgba(0,0,0,.15);
  position: relative;
}
.qr-canvas-wrapper canvas, .qr-canvas-wrapper img {
  max-width: 100% !important;
  height: auto !important;
}
.qr-placeholder {
  color: #bbb;
  font-size: .85rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.qr-placeholder svg { width: 40px; height: 40px; color: #ccc; }

/* QR Action buttons */
.qr-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: center;
  padding: 16px 20px 20px;
}
.qr-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 8px;
  font-size: .82rem;
  font-weight: 500;
  border: 1px solid var(--border);
  background: var(--bg-surface);
  color: var(--text);
  cursor: pointer;
  transition: all var(--transition);
  text-decoration: none;
}
.qr-action-btn:hover {
  border-color: var(--primary);
  background: rgba(108,92,231,.1);
  color: var(--primary);
}
.qr-action-btn.primary {
  background: linear-gradient(135deg, var(--primary), #8b7cf7);
  border-color: var(--primary);
  color: #fff;
}
.qr-action-btn.primary:hover {
  box-shadow: 0 2px 12px rgba(108,92,231,.35);
  color: #fff;
}
.qr-action-btn svg { width: 15px; height: 15px; }

/* File Library section */
.qr-library {
  margin-top: 28px;
}
.qr-library-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  gap: 12px;
}
.qr-library-header h3 {
  font-size: 1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.qr-library-header h3 svg { width: 18px; height: 18px; color: var(--primary); }
.qr-library-count {
  font-size: .78rem;
  color: var(--text-muted);
  background: var(--bg-surface);
  padding: 4px 12px;
  border-radius: 12px;
  border: 1px solid var(--border);
}
.qr-file-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 10px;
}
.qr-file-item {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 16px;
  cursor: pointer;
  transition: all var(--transition);
  display: flex;
  align-items: center;
  gap: 12px;
}
.qr-file-item:hover {
  border-color: var(--primary);
  background: rgba(108,92,231,.06);
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(0,0,0,.15);
}
.qr-file-item .file-icon {
  width: 36px; height: 36px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  font-size: 1.1rem;
}
.qr-file-item .file-icon.model { background: rgba(108,92,231,.15); }
.qr-file-item .file-icon.panorama { background: rgba(0,206,201,.15); }
.qr-file-item .file-icon.embed { background: rgba(253,121,168,.15); }
.qr-file-item .file-icon.other { background: rgba(253,203,110,.15); }
.qr-file-item .file-info { flex: 1; min-width: 0; }
.qr-file-item .file-name {
  font-weight: 500;
  font-size: .88rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.qr-file-item .file-cat {
  font-size: .75rem;
  color: var(--text-muted);
  margin-top: 2px;
}
.qr-file-item .file-arrow {
  flex-shrink: 0;
  color: var(--text-muted);
  transition: transform .2s;
}
.qr-file-item:hover .file-arrow { transform: translateX(3px); color: var(--primary); }
.qr-file-item .file-arrow svg { width: 16px; height: 16px; }

.qr-empty {
  text-align: center;
  padding: 40px 20px;
  color: var(--text-muted);
}
.qr-empty svg { width: 36px; height: 36px; margin-bottom: 10px; color: var(--text-muted); }
.qr-empty a { color: var(--primary); font-weight: 500; }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .qr-hero { padding: 22px 24px; }
  .qr-grid {
    grid-template-columns: 1fr;
    gap: 18px;
  }
}

@media (max-width: 768px) {
  .qr-hero { padding: 18px 18px; gap: 12px; }
  .qr-hero-icon { width: 44px; height: 44px; border-radius: 11px; }
  .qr-hero-text h1 { font-size: 1.1rem; }
  .qr-hero-text p { font-size: .8rem; }

  .qr-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .qr-card-body { padding: 16px; }

  .qr-mode-pills { gap: 6px; }
  .qr-mode-pill { padding: 7px 11px; font-size: .78rem; }

  .qr-preview-area { padding: 20px 12px 8px; }
  .qr-canvas-wrapper { padding: 16px; min-width: 150px; min-height: 150px; }

  .qr-actions { padding: 12px 16px 16px; }
  .qr-action-btn { padding: 8px 12px; font-size: .8rem; }

  .qr-file-grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }
}

@media (max-width: 480px) {
  .qr-hero { flex-direction: column; text-align: center; padding: 20px 16px; }
  .qr-hero-icon { width: 48px; height: 48px; }
  .qr-hero-text h1 { font-size: 1.05rem; }

  .qr-mode-pills {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
  }
  .qr-mode-pill { justify-content: center; padding: 10px 8px; }

  .qr-generate-btn { font-size: .88rem; padding: 13px 16px; }

  .qr-actions {
    flex-direction: column;
    align-items: stretch;
  }
  .qr-action-btn { justify-content: center; }

  .qr-file-item { padding: 12px 14px; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-content">
<div class="container" id="app" v-cloak>

  <!-- Hero -->
  <div class="qr-hero">
    <div class="qr-hero-icon">
      <i data-feather="maximize" style="width:24px;height:24px;"></i>
    </div>
    <div class="qr-hero-text">
      <h1>QR Code Generator</h1>
      <p>สร้าง QR Code สำหรับ AR, 3D Viewer, Panorama และ Embed</p>
    </div>
  </div>

  <!-- Main Grid -->
  <div class="qr-grid">

    <!-- Settings Card -->
    <div class="qr-card">
      <div class="qr-card-header">
        <div class="qr-card-header-icon"><i data-feather="sliders"></i></div>
        <h3>ตั้งค่า</h3>
      </div>
      <div class="qr-card-body">

        <div class="form-group">
          <label>เลือกไฟล์จากแกลเลอรี</label>
          <select v-model="selectedFile" @change="onFileSelect()">
            <option value="">— กรอก URL เอง —</option>
            <option v-for="f in files" :key="f.id" :value="f.id">{{ f.title }} ({{ f.category_slug }})</option>
          </select>
        </div>

        <div class="form-group">
          <label>URL โมเดล / ไฟล์</label>
          <input type="url" v-model="modelUrl" placeholder="เช่น /vrx/assets/robot.glb">
        </div>

        <div class="form-group">
          <label>โหมด</label>
          <div class="qr-mode-pills">
            <button class="qr-mode-pill" :class="{active: mode==='ar'}" @click="mode='ar'">
              <span class="pill-emoji">📦</span> AR Viewer
            </button>
            <button class="qr-mode-pill" :class="{active: mode==='3d'}" @click="mode='3d'">
              <span class="pill-emoji">🔮</span> 3D Viewer
            </button>
            <button class="qr-mode-pill" :class="{active: mode==='pano'}" @click="mode='pano'">
              <span class="pill-emoji">🌄</span> Panorama
            </button>
            <button class="qr-mode-pill" :class="{active: mode==='embed'}" @click="mode='embed'">
              <span class="pill-emoji">💻</span> Embed
            </button>
            <button class="qr-mode-pill" :class="{active: mode==='url'}" @click="mode='url'">
              <span class="pill-emoji">🔗</span> Direct URL
            </button>
          </div>
        </div>

        <!-- QR Settings (collapsible) -->
        <div class="qr-settings-info" @click="showSettings = !showSettings">
          <div class="qr-settings-toggle" :class="{open: showSettings}">
            <span><i data-feather="settings" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px;"></i> การตั้งค่า QR จาก Admin</span>
            <i data-feather="chevron-down"></i>
          </div>
          <div class="qr-settings-details" v-show="showSettings" @click.stop>
            <div class="qr-setting-row">ขนาด: <strong>{{ qrSettings.qr_size }}px</strong></div>
            <div class="qr-setting-row">
              สี:
              <span class="qr-setting-dot" :style="{background: qrSettings.qr_color_dark}"></span>
              {{ qrSettings.qr_color_dark }}
              /
              <span class="qr-setting-dot" :style="{background: qrSettings.qr_color_light, border:'1px solid #555'}"></span>
              {{ qrSettings.qr_color_light }}
            </div>
            <div class="qr-setting-row">Error Level: <strong>{{ qrSettings.qr_error_level }}</strong></div>
            <div class="qr-setting-row" v-if="qrSettings.qr_base_url" style="color:var(--accent);">Base URL: {{ qrSettings.qr_base_url }}</div>
          </div>
        </div>

        <!-- Generated URL preview -->
        <div class="qr-url-preview" v-if="generatedUrl">
          {{ generatedUrl }}
        </div>

        <!-- Generate button -->
        <button class="qr-generate-btn" @click="generate()" :disabled="!modelUrl">
          <i data-feather="maximize"></i> สร้าง QR Code
        </button>

      </div>
    </div>

    <!-- QR Preview Card -->
    <div class="qr-card">
      <div class="qr-card-header">
        <div class="qr-card-header-icon"><i data-feather="image"></i></div>
        <h3>QR Code Preview</h3>
      </div>

      <div class="qr-preview-area">
        <div class="qr-canvas-wrapper" id="qr-container">
          <div v-if="!hasQR" class="qr-placeholder">
            <i data-feather="maximize"></i>
            <span>กดปุ่ม "สร้าง QR Code"<br>เพื่อเริ่มต้น</span>
          </div>
        </div>
      </div>

      <div class="qr-actions" v-if="hasQR">
        <button class="qr-action-btn" @click="downloadQR()">
          <i data-feather="download"></i> ดาวน์โหลด
        </button>
        <button class="qr-action-btn" @click="copyLink()">
          <i data-feather="copy"></i> คัดลอก URL
        </button>
        <a :href="generatedUrl" target="_blank" class="qr-action-btn primary">
          <i data-feather="external-link"></i> เปิดลิงก์
        </a>
      </div>
    </div>

  </div><!-- /qr-grid -->

  <!-- File Library -->
  <div class="qr-library">
    <div class="qr-library-header">
      <h3><i data-feather="folder"></i> ไฟล์ของคุณ</h3>
      <span class="qr-library-count" v-if="files.length">{{ files.length }} ไฟล์</span>
    </div>

    <div class="qr-file-grid" v-if="files.length">
      <div class="qr-file-item" v-for="f in files" :key="f.id" @click="selectLib(f)">
        <div class="file-icon" :class="f.category_slug || 'other'">
          <span v-if="f.category_slug==='model'">📦</span>
          <span v-else-if="f.category_slug==='panorama'">🌄</span>
          <span v-else-if="f.category_slug==='embed'">💻</span>
          <span v-else>📄</span>
        </div>
        <div class="file-info">
          <div class="file-name">{{ f.title }}</div>
          <div class="file-cat">{{ f.category_slug }}</div>
        </div>
        <div class="file-arrow"><i data-feather="chevron-right"></i></div>
      </div>
    </div>

    <div class="qr-empty" v-else>
      <i data-feather="inbox"></i>
      <p>ยังไม่มีไฟล์ — <a :href="BASE+'/pages/upload.php'">อัปโหลดก่อน</a></p>
    </div>
  </div>

</div>
</div>

<?php include __DIR__ . '/../includes/bottom_nav.php'; ?>

<div class="toast-container" id="toast-container"></div>

<script src="<?= BASE_URL ?>/third_party/vue.min.js"></script>
<script src="<?= BASE_URL ?>/third_party/qrcode.min.js"></script>
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
      BASE: BASE,
      modelUrl: '<?= addslashes($src) ?>',
      selectedFile: '',
      embedSrc: '',
      fileId: '<?= addslashes($fileId) ?>',
      initFileId: '<?= addslashes($fileId) ?>',
      mode: 'ar',
      files: [],
      hasQR: false,
      generatedUrl: '',
      qrInstance: null,
      showSettings: false,
      // QR settings from DB
      qrSettings: {
        qr_base_url: '<?= addslashes($qrSettings['qr_base_url']) ?>',
        qr_pattern_ar: '<?= addslashes($qrSettings['qr_pattern_ar']) ?>',
        qr_pattern_3d: '<?= addslashes($qrSettings['qr_pattern_3d']) ?>',
        qr_pattern_pano: '<?= addslashes($qrSettings['qr_pattern_pano']) ?>',
        qr_pattern_embed: '<?= addslashes($qrSettings['qr_pattern_embed']) ?>',
        qr_size: <?= (int)$qrSettings['qr_size'] ?>,
        qr_color_dark: '<?= addslashes($qrSettings['qr_color_dark']) ?>',
        qr_color_light: '<?= addslashes($qrSettings['qr_color_light']) ?>',
        qr_error_level: '<?= addslashes($qrSettings['qr_error_level']) ?>'
      }
    };
  },
  created: function() {
    this.loadFiles();
    // Only auto-generate on load if src was passed directly (not file_id, which loadFiles handles)
    if (this.modelUrl && !this.initFileId) {
      this.$nextTick(this.generate);
    }
  },
  methods: {
    loadFiles: function() {
      var vm = this;
      fetch(BASE + '/api/index.php?action=files&limit=100&sort=newest')
        .then(function(r){ return r.json(); })
        .then(function(d) {
          vm.files = (d.data && d.data.files) || [];
          // Auto-select file if opened with ?file_id=
          if (vm.initFileId) {
            var f = vm.files.find(function(item) { return item.id == vm.initFileId; });
            if (f) {
              vm.selectLib(f);
            }
          }
          vm.$nextTick(function(){ feather.replace(); });
        });
    },
    onFileSelect: function() {
      if (!this.selectedFile) return;
      var vm = this;
      var f = this.files.find(function(item) { return item.id == vm.selectedFile; });
      if (!f) return;
      this.selectLib(f);
    },
    selectLib: function(f) {
      this.fileId = f.id || '';
      this.embedSrc = f.embed_src || '';
      this.selectedFile = f.id;
      if (f.category_slug === 'model') {
        this.mode = 'ar';
        this.modelUrl = f.file_url || '';
      } else if (f.category_slug === 'panorama') {
        this.mode = 'pano';
        this.modelUrl = f.file_url || '';
      } else if (f.category_slug === 'embed') {
        this.mode = 'embed';
        this.modelUrl = f.embed_src || f.file_url || '';
      } else {
        this.mode = 'url';
        this.modelUrl = f.file_url || f.embed_src || '';
      }
      this.generate();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    buildUrl: function() {
      var origin = this.qrSettings.qr_base_url || window.location.origin;
      var src = this.modelUrl;
      // Make src absolute
      var srcAbs = src;
      if (src.charAt(0) === '/') srcAbs = window.location.origin + src;
      else if (src.indexOf('http') !== 0) srcAbs = window.location.origin + BASE + '/' + src;

      // Pick pattern based on mode
      var pattern = '';
      if (this.mode === 'ar') pattern = this.qrSettings.qr_pattern_ar;
      else if (this.mode === '3d') pattern = this.qrSettings.qr_pattern_3d;
      else if (this.mode === 'pano') pattern = this.qrSettings.qr_pattern_pano;
      else if (this.mode === 'embed') pattern = this.qrSettings.qr_pattern_embed;
      else return srcAbs; // direct URL mode

      // Replace placeholders
      var embedSrc = this.embedSrc || src;
      var fileId = this.fileId || '';
      var url = pattern
        .replace(/\{origin\}/g, origin)
        .replace(/\{base\}/g, BASE)
        .replace(/\{file_url_abs\}/g, encodeURIComponent(srcAbs))
        .replace(/\{file_url\}/g, encodeURIComponent(src))
        .replace(/\{embed_src\}/g, encodeURIComponent(embedSrc))
        .replace(/\{id\}/g, fileId);

      return url;
    },
    generate: function() {
      if (!this.modelUrl) return;
      this.generatedUrl = this.buildUrl();
      var container = document.getElementById('qr-container');
      container.innerHTML = '';
      var s = this.qrSettings;
      var lvlMap = { L: QRCode.CorrectLevel.L, M: QRCode.CorrectLevel.M, Q: QRCode.CorrectLevel.Q, H: QRCode.CorrectLevel.H };
      try {
        new QRCode(container, {
          text: this.generatedUrl,
          width: s.qr_size,
          height: s.qr_size,
          colorDark: s.qr_color_dark,
          colorLight: s.qr_color_light,
          correctLevel: lvlMap[s.qr_error_level] || QRCode.CorrectLevel.M
        });
        this.hasQR = true;
      } catch(e) {
        container.innerHTML = '<span style="color:var(--danger);">QR สร้างไม่สำเร็จ</span>';
      }
      this.$nextTick(function(){ feather.replace(); });
    },
    downloadQR: function() {
      var canvas = document.querySelector('#qr-container canvas');
      if (!canvas) { var img = document.querySelector('#qr-container img'); if (!img) return; window.open(img.src); return; }
      var a = document.createElement('a');
      a.href = canvas.toDataURL('image/png');
      a.download = 'vrx-qrcode.png';
      a.click();
    },
    copyLink: function() {
      navigator.clipboard.writeText(this.generatedUrl).then(function() { showToast('คัดลอก URL แล้ว', 'success'); });
    }
  }
});
feather.replace();
</script>
</body>
</html>
