<?php
require_once __DIR__ . '/../core/config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>QR Scanner — VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
<style>
/* ═══════════════════════════════════════════════
   Scanner Page — Full Responsive + Auto Preview
   ═══════════════════════════════════════════════ */

/* Main layout */
.scan-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: start;
}

/* Card */
.scan-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.scan-card-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 10px;
}
.scan-card-header .icon-wrap {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(108,92,231,.15);
  flex-shrink: 0;
}
.scan-card-header .icon-wrap svg { width: 16px; height: 16px; color: var(--primary); }
.scan-card-header h3 { font-size: .9rem; font-weight: 600; margin: 0; }
.scan-card-body { padding: 16px; }

/* Scanner viewport */
.scanner-viewport {
  position: relative; width: 100%;
  aspect-ratio: 1;
  background: #000; border-radius: 10px;
  overflow: hidden; border: 1px solid var(--border);
}
.scanner-viewport video {
  width: 100%; height: 100%; object-fit: cover; display: block;
}
.scanner-viewport canvas { display: none; }

/* Overlay */
.scanner-overlay { position: absolute; inset: 0; pointer-events: none; }
.scanner-dim {
  position: absolute; top: 15%; left: 15%; right: 15%; bottom: 15%;
  box-shadow: 0 0 0 9999px rgba(0,0,0,.5);
  border-radius: 8px;
}
.scanner-corners { position: absolute; inset: 15%; }
.corner { position: absolute; width: 24px; height: 24px; border-color: var(--accent); border-style: solid; }
.corner.tl { top: 0; left: 0; border-width: 3px 0 0 3px; border-radius: 4px 0 0 0; }
.corner.tr { top: 0; right: 0; border-width: 3px 3px 0 0; border-radius: 0 4px 0 0; }
.corner.bl { bottom: 0; left: 0; border-width: 0 0 3px 3px; border-radius: 0 0 0 4px; }
.corner.br { bottom: 0; right: 0; border-width: 0 3px 3px 0; border-radius: 0 0 4px 0; }
.scanner-laser {
  position: absolute; left: 15%; right: 15%; height: 2px;
  background: linear-gradient(90deg, transparent 0%, var(--accent) 20%, var(--primary) 50%, var(--accent) 80%, transparent 100%);
  box-shadow: 0 0 14px var(--accent);
  animation: laserScan 2s ease-in-out infinite;
  opacity: .85;
}
@keyframes laserScan { 0%,100%{top:15%;} 50%{top:82%;} }

/* Placeholder */
.scanner-placeholder {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  width: 100%; height: 100%; min-height: 240px;
  color: var(--text-muted); gap: 12px;
}
.scanner-placeholder svg { opacity: .2; }
.scanner-placeholder p { font-size: .82rem; }

/* Status bar */
.scan-status {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 10px 14px; font-size: .76rem; color: var(--text-muted);
  border-top: 1px solid var(--border);
}
.scan-status .dot {
  width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}
.scan-status .dot.active {
  background: var(--success);
  box-shadow: 0 0 8px var(--success);
  animation: pulse 1.5s ease-in-out infinite;
}
.scan-status .dot.idle { background: var(--text-muted); }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }

/* Controls row */
.scan-controls {
  display: flex; gap: 8px; margin-top: 12px;
}
.scan-controls .btn { flex: 1; justify-content: center; }

/* ═══ Result area ═══ */
.preview-card { animation: previewIn .4s ease; }
@keyframes previewIn { from{opacity:0;transform:translateY(10px);} to{opacity:1;transform:none;} }

.preview-url-box {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 14px;
  margin-top: 12px;
  font-family: monospace;
  font-size: .75rem;
  color: var(--accent);
  word-break: break-all;
  line-height: 1.5;
  cursor: pointer;
  transition: border-color .2s;
  max-height: 60px;
  overflow-y: auto;
}
.preview-url-box:hover { border-color: var(--primary); }

.preview-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-top: 12px;
}
.preview-actions .btn { justify-content: center; font-size: .82rem; }

/* Empty result */
.scan-empty {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  min-height: 200px;
  color: var(--text-muted);
  text-align: center;
  padding: 30px 20px;
}
.scan-empty svg { opacity: .15; margin-bottom: 12px; }
.scan-empty p { font-size: .82rem; margin: 0; }
.scan-empty .sub { font-size: .72rem; color: var(--text-muted); margin-top: 4px; }

/* Manual URL */
.manual-section { margin-top: 14px; }
.manual-input-row {
  display: flex; gap: 8px;
}
.manual-input-row input {
  flex: 1; background: var(--bg-input); border: 1px solid var(--border);
  border-radius: 8px; padding: 10px 14px; color: var(--text);
  font-size: .85rem; transition: border-color .2s;
}
.manual-input-row input:focus {
  outline: none; border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(108,92,231,.15);
}

/* History */
.history-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; background: var(--bg-surface);
  border-radius: 8px; margin-bottom: 6px;
  transition: all .2s; cursor: pointer;
}
.history-item:hover { background: var(--bg-hover); }
.history-num {
  width: 22px; height: 22px; border-radius: 6px;
  background: rgba(108,92,231,.15); color: var(--primary);
  display: flex; align-items: center; justify-content: center;
  font-size: .68rem; font-weight: 700; flex-shrink: 0;
}
.history-url {
  flex: 1; font-size: .75rem; color: var(--text-secondary);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.history-item:hover .history-url { color: var(--accent); }

/* ═══ Responsive ═══ */
@media (max-width: 1024px) {
  .scan-layout {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}
@media (max-width: 768px) {
  .scan-card-body { padding: 12px; }
  .scanner-viewport { aspect-ratio: 1; border-radius: 8px; }

  .preview-actions { grid-template-columns: 1fr 1fr; gap: 6px; }
}
@media (max-width: 480px) {
  .scanner-viewport { aspect-ratio: 3/4; }

  .preview-actions { grid-template-columns: 1fr; }
  .scan-controls { flex-direction: column; }
}

/* Spinner */
.scan-spinner {
  width: 40px; height: 40px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin .8s linear infinite;
  margin: 0 auto;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Not found box */
.not-found-box {
  text-align: center;
  padding: 24px 16px;
  background: rgba(225,112,85,.06);
  border: 1px solid rgba(225,112,85,.2);
  border-radius: 10px;
  margin-bottom: 12px;
}
.not-found-box p { font-size: .88rem; color: var(--text); margin: 8px 0 0; font-weight: 500; }
.not-found-box .sub { font-size: .78rem; color: var(--text-muted); font-weight: 400; margin-top: 4px; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-content">
<div class="container" id="app" v-cloak>

  <div class="scan-layout">

    <!-- ═══ Left: Scanner ═══ -->
    <div>
      <div class="scan-card">
        <div class="scan-card-header">
          <div class="icon-wrap">
            <i data-feather="camera"></i>
          </div>
          <h3>กล้องสแกน</h3>
        </div>

        <div class="scan-card-body">
          <div class="scanner-viewport">
            <template v-if="scanning">
              <video id="scanner-video" autoplay playsinline muted></video>
              <canvas id="scanner-canvas"></canvas>
              <div class="scanner-overlay">
                <div class="scanner-dim"></div>
                <div class="scanner-corners">
                  <div class="corner tl"></div>
                  <div class="corner tr"></div>
                  <div class="corner bl"></div>
                  <div class="corner br"></div>
                </div>
                <div class="scanner-laser"></div>
              </div>
            </template>
            <template v-else>
              <div class="scanner-placeholder">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                  <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                  <circle cx="12" cy="13" r="4"/>
                </svg>
                <p>กำลังเปิดกล้อง...</p>
              </div>
            </template>
          </div>
        </div>

        <div class="scan-status">
          <span class="dot" :class="scanning ? 'active' : 'idle'"></span>
          <span v-if="scanning">กำลังสแกน... เล็งกล้องไปที่ QR Code</span>
          <span v-else-if="camError">{{ camError }}</span>
          <span v-else>กำลังเชื่อมต่อกล้อง...</span>
        </div>
      </div>

      <div class="scan-controls">
        <button v-if="!scanning" class="btn btn-primary" @click="startScan()">
          <i data-feather="camera"></i> เปิดกล้อง
        </button>
        <button v-else class="btn" style="background:var(--danger);color:#fff;" @click="stopScan()">
          <i data-feather="square"></i> หยุดสแกน
        </button>
      </div>

      <!-- Manual URL -->
      <div class="manual-section">
        <div class="scan-card">
          <div class="scan-card-header">
            <div class="icon-wrap" style="background:rgba(0,206,201,.15);">
              <i data-feather="link" style="color:var(--accent);"></i>
            </div>
            <h3>กรอก URL โดยตรง</h3>
          </div>
          <div class="scan-card-body">
            <div class="manual-input-row">
              <input type="url" v-model="manualUrl" placeholder="https://... วาง URL ที่นี่" @keyup.enter="openManual()">
              <button class="btn btn-primary" @click="openManual()" :disabled="!manualUrl.trim()">
                <i data-feather="arrow-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Right: Result ═══ -->
    <div>
      <!-- Scan checking -->
      <template v-if="checking">
        <div class="scan-card">
          <div class="scan-card-body" style="text-align:center;padding:40px 20px;">
            <div class="scan-spinner"></div>
            <p style="margin-top:14px;font-size:.88rem;color:var(--text-secondary);">กำลังตรวจสอบ QR Code ในฐานข้อมูล...</p>
            <p style="font-size:.75rem;color:var(--text-muted);margin-top:4px;word-break:break-all;">{{ lastScanned }}</p>
          </div>
        </div>
      </template>

      <!-- Not found -->
      <template v-else-if="notFound">
        <div class="scan-card preview-card">
          <div class="scan-card-header" style="border-bottom-color:rgba(225,112,85,.25);">
            <div class="icon-wrap" style="background:rgba(225,112,85,.15);">
              <i data-feather="alert-circle" style="color:var(--danger);"></i>
            </div>
            <h3>ไม่พบในฐานข้อมูล</h3>
          </div>
          <div class="scan-card-body">
            <div class="not-found-box">
              <i data-feather="search" style="width:32px;height:32px;color:var(--danger);opacity:.5;"></i>
              <p>ไม่มี QR Code นี้ในฐานข้อมูล</p>
              <p class="sub">URL ที่สแกนได้ไม่ตรงกับไฟล์ใดๆ ในระบบ</p>
            </div>
            <div class="preview-url-box" @click="copyText(lastScanned)" title="คลิกเพื่อคัดลอก">
              {{ lastScanned }}
            </div>
            <div class="preview-actions">
              <button class="btn btn-primary" @click="rescan()">
                <i data-feather="refresh-cw"></i> สแกนใหม่
              </button>
              <a :href="lastScanned" target="_blank" class="btn btn-outline">
                <i data-feather="external-link"></i> เปิด URL ตรง
              </a>
            </div>
          </div>
        </div>
      </template>

      <!-- Redirecting -->
      <template v-else-if="redirecting">
        <div class="scan-card">
          <div class="scan-card-body" style="text-align:center;padding:40px 20px;">
            <i data-feather="check-circle" style="width:40px;height:40px;color:var(--success);"></i>
            <p style="margin-top:12px;font-size:.92rem;font-weight:600;color:var(--success);">พบไฟล์แล้ว!</p>
            <p style="font-size:.82rem;color:var(--text-secondary);margin-top:4px;">กำลังเปิด Viewer...</p>
          </div>
        </div>
      </template>

      <!-- Default empty state -->
      <template v-else>
        <div class="scan-card">
          <div class="scan-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
              <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
              <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/>
              <line x1="21" y1="14" x2="21" y2="21"/><line x1="14" y1="21" x2="21" y2="21"/>
            </svg>
            <p>เล็งกล้องไปที่ QR Code</p>
            <p class="sub">ระบบจะค้นหาไฟล์ในฐานข้อมูลและเปิด Viewer ให้อัตโนมัติ</p>
          </div>
        </div>
      </template>

      <!-- History -->
      <template v-if="history.length">
        <div class="scan-card" style="margin-top:14px;">
          <div class="scan-card-header">
            <div class="icon-wrap" style="background:rgba(253,203,110,.15);">
              <i data-feather="clock" style="color:var(--warning);"></i>
            </div>
            <h3>ประวัติ ({{ history.length }})</h3>
          </div>
          <div class="scan-card-body" style="padding:10px 14px;">
            <div class="history-item" v-for="(h, i) in history" :key="i" @click="lookupAndGo(h.url)">
              <span class="history-num">{{ i + 1 }}</span>
              <span class="history-url" :style="{color: h.found ? 'var(--success)' : 'var(--danger)'}">{{ h.url }}</span>
              <span v-if="h.found" style="font-size:.65rem;color:var(--success);flex-shrink:0;">✓ พบ</span>
              <span v-else style="font-size:.65rem;color:var(--danger);flex-shrink:0;">✗ ไม่พบ</span>
            </div>
            <button v-if="history.length > 1" class="btn btn-sm btn-outline" style="margin-top:8px;width:100%;justify-content:center;color:var(--danger);" @click="history=[]">
              <i data-feather="trash-2"></i> ล้างประวัติ
            </button>
          </div>
        </div>
      </template>
    </div>

  </div><!-- /scan-layout -->

</div>
</div>

<?php include __DIR__ . '/../includes/bottom_nav.php'; ?>

<div class="toast-container" id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="<?= BASE_URL ?>/third_party/vue.min.js"></script>
<script>
var BASE = '<?= BASE_URL ?>';

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
      scanning: false,
      checking: false,
      notFound: false,
      redirecting: false,
      lastScanned: '',
      manualUrl: '',
      history: [],
      stream: null,
      animFrame: null,
      camError: ''
    };
  },
  mounted: function() {
    this.startScan();
  },
  methods: {

    /* ── Camera ── */
    startScan: function() {
      var vm = this;
      vm.scanning = true;
      vm.camError = '';
      vm.notFound = false;
      vm.checking = false;
      vm.redirecting = false;

      vm.$nextTick(function() {
        var video = document.getElementById('scanner-video');
        var canvas = document.getElementById('scanner-canvas');
        if (!video || !canvas) { vm.scanning = false; return; }
        var ctx = canvas.getContext('2d');

        navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment', width: { ideal: 720 }, height: { ideal: 720 } }
        }).then(function(stream) {
          vm.stream = stream;
          video.srcObject = stream;
          video.play();

          function scan() {
            if (!vm.scanning) return;
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
              canvas.width = video.videoWidth;
              canvas.height = video.videoHeight;
              ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
              var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
              var code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert'
              });
              if (code && code.data) {
                vm.onScanResult(code.data);
                return;
              }
            }
            vm.animFrame = requestAnimationFrame(scan);
          }
          scan();
        }).catch(function(err) {
          vm.scanning = false;
          vm.camError = 'ไม่สามารถเปิดกล้อง: ' + err.message;
          showToast('ไม่สามารถเปิดกล้อง: ' + err.message, 'error');
        });
      });
    },

    stopScan: function() {
      this.scanning = false;
      if (this.animFrame) cancelAnimationFrame(this.animFrame);
      if (this.stream) {
        this.stream.getTracks().forEach(function(t){ t.stop(); });
        this.stream = null;
      }
    },

    /* ── On QR detected → lookup in DB ── */
    onScanResult: function(data) {
      this.stopScan();
      this.lastScanned = data;
      this.lookupAndGo(data);
    },

    /* ── Open scanned URL directly ── */
    lookupAndGo: function(scannedUrl) {
      var vm = this;
      vm.lastScanned = scannedUrl;
      vm.notFound = false;
      vm.checking = false;
      vm.redirecting = true;

      vm.addHistory(scannedUrl, true);
      showToast('กำลังเปิด URL...', 'success');
      vm.$nextTick(function(){ feather.replace(); });

      setTimeout(function() {
        window.location.href = scannedUrl;
      }, 500);
    },

    /* ── Build viewer.php URL from file record ── */
    buildViewerUrl: function(file) {
      var cat = file.category_slug || '';
      var src = file.file_url || '';
      var embedSrc = file.embed_src || '';
      var id = file.id;
      var title = encodeURIComponent(file.name || 'Viewer');

      if (cat === 'embed' || file.source_type === 'embed') {
        return BASE + '/pages/viewer.php?mode=embed&embed=' + encodeURIComponent(embedSrc || src) + '&title=' + title + '&id=' + id;
      }
      if (cat === 'panorama') {
        return BASE + '/pages/panorama.php?src=' + encodeURIComponent(src) + '&title=' + title;
      }
      // Default: 3D / model / AR viewer
      return BASE + '/pages/viewer.php?src=' + encodeURIComponent(src) + '&title=' + title + '&id=' + id;
    },

    addHistory: function(url, found) {
      // Avoid duplicate at top
      if (this.history.length && this.history[0].url === url) return;
      this.history.unshift({ url: url, found: found });
      if (this.history.length > 20) this.history.pop();
    },

    rescan: function() {
      this.notFound = false;
      this.redirecting = false;
      this.checking = false;
      this.lastScanned = '';
      this.$nextTick(function() { this.startScan(); }.bind(this));
    },

    copyText: function(text) {
      navigator.clipboard.writeText(text).then(function() {
        showToast('คัดลอก URL แล้ว', 'success');
      });
    },

    openManual: function() {
      var url = this.manualUrl.trim();
      if (!url) return;
      if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
      this.stopScan();
      this.manualUrl = '';
      this.lookupAndGo(url);
    }
  },

  updated: function() { feather.replace(); },
  beforeDestroy: function() { this.stopScan(); }
});
feather.replace();
</script>
</body>
</html>
