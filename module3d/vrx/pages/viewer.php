<?php
require_once __DIR__ . '/../core/config.php';
$src = (!empty($_GET['src']) && $_GET['src'] !== 'null') ? $_GET['src'] : '';
$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$title = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '3D Viewer';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'model';
$embedSrc = isset($_GET['embed']) ? $_GET['embed'] : '';
$transparent = !empty($_GET['transparent']);
// If no src and no embed and no id, use default robot model
if (!$src && !$embedSrc && !$fileId) {
  $src = BASE_URL . '/assets/robot.glb';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?> — VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
<style>
  body { margin:0; overflow:hidden; background:#0f0f1a; }
<?php if ($transparent): ?>
  body { background:transparent!important; }
  .viewer-toolbar, .data-overlay, #embed-viewer { display:none!important; }
<?php endif; ?>
  #viewer-canvas { position:fixed; inset:0; }
  #viewer-canvas canvas { width:100%!important; height:100%!important; display:block; }

  /* Embed viewer */
  #embed-viewer { position:fixed; inset:0; background:#000; }
  #embed-viewer iframe { width:100%; height:100%; border:none; }

  /* ─── Data Overlay Panel ─── */
  .data-overlay {
    position:fixed; left:16px; bottom:90px; z-index:60;
    width:330px; max-height:calc(100vh - 160px);
    background:rgba(15,15,26,.93);
    backdrop-filter:blur(20px) saturate(1.2);
    -webkit-backdrop-filter:blur(20px) saturate(1.2);
    border:1px solid rgba(108,92,231,.25);
    border-radius:16px;
    padding:0; overflow:hidden;
    transform:translateX(-120%);
    transition:transform .4s cubic-bezier(.4,0,.2,1);
    box-shadow:0 8px 32px rgba(0,0,0,.5), 0 0 0 1px rgba(108,92,231,.1);
  }
  .data-overlay.visible { transform:translateX(0); }

  .data-overlay-inner { padding:20px; overflow-y:auto; max-height:calc(100vh - 170px); }
  .data-overlay-inner::-webkit-scrollbar { width:3px; }
  .data-overlay-inner::-webkit-scrollbar-thumb { background:rgba(108,92,231,.3); border-radius:3px; }

  .data-overlay-header {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:12px; gap:8px;
  }
  .data-overlay-header h3 {
    font-size:1rem; font-weight:700; display:flex; align-items:center; gap:8px;
    margin:0; flex:1; min-width:0;
  }
  .data-overlay-header h3 span {
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }

  .overlay-close-btn {
    background:rgba(255,255,255,.08); border:none; color:var(--text-muted);
    cursor:pointer; padding:6px; border-radius:8px; display:flex;
    align-items:center; justify-content:center; flex-shrink:0;
    transition:all .2s;
  }
  .overlay-close-btn:hover { background:rgba(255,255,255,.15); color:#fff; }

  .overlay-badges { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px; }
  .overlay-badges .badge { font-size:.65rem; padding:3px 10px; }

  .data-desc {
    font-size:.82rem; color:var(--text-secondary); line-height:1.6;
    margin:8px 0 4px; padding:10px 12px;
    background:rgba(255,255,255,.03); border-radius:8px;
    border-left:3px solid var(--primary);
  }

  .data-section { margin-top:16px; padding-top:14px; border-top:1px solid rgba(255,255,255,.06); }
  .data-section h4 {
    font-size:.72rem; color:var(--text-muted); text-transform:uppercase;
    letter-spacing:.8px; margin-bottom:10px; font-weight:600;
  }
  .data-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:5px 0; font-size:.82rem;
  }
  .data-row .label { color:var(--text-muted); font-size:.78rem; }
  .data-row .value {
    color:var(--text); font-weight:500; text-align:right;
    max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }

  .data-actions {
    display:flex; gap:6px; flex-wrap:wrap; margin-top:16px; padding-top:12px;
    border-top:1px solid rgba(255,255,255,.06);
  }
  .data-actions .btn { font-size:.75rem; padding:6px 12px; }

  @media (max-width:768px) {
    .data-overlay { left:10px; right:10px; width:auto; bottom:80px; max-height:55vh; }
  }
</style>
</head>
<body>

<!-- 3D Canvas (model mode) -->
<div id="viewer-canvas" style="<?= $mode === 'embed' ? 'display:none' : '' ?>"></div>

<!-- Embed Viewer (embed mode) -->
<div id="embed-viewer" style="<?= ($mode === 'embed' && $embedSrc) ? '' : 'display:none;' ?>">
<?php if ($mode === 'embed' && $embedSrc): ?>
  <iframe src="<?= htmlspecialchars($embedSrc) ?>" allowfullscreen
          sandbox="allow-scripts allow-same-origin allow-popups allow-forms"></iframe>
<?php endif; ?>
</div>

<!-- Data Overlay Panel -->
<div class="data-overlay" id="data-overlay">
<div class="data-overlay-inner">

  <div class="data-overlay-header">
    <h3>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2">
        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
      </svg>
      <span id="overlay-title">Loading...</span>
    </h3>
    <button class="overlay-close-btn" onclick="toggleOverlay()" title="ซ่อน">
      <i data-feather="eye-off" style="width:16px;height:16px;"></i>
    </button>
  </div>

  <div class="overlay-badges" id="overlay-badges"></div>
  <div class="data-desc" id="overlay-desc" style="display:none;"></div>

  <!-- File Info Section -->
  <div class="data-section" id="section-file">
    <h4>📁 ข้อมูลไฟล์</h4>
    <div class="data-row"><span class="label">ผู้อัปโหลด</span><span class="value" id="d-uploader">—</span></div>
    <div class="data-row"><span class="label">หมวดหมู่</span><span class="value" id="d-category">—</span></div>
    <div class="data-row"><span class="label">วันที่สร้าง</span><span class="value" id="d-date">—</span></div>
    <div class="data-row"><span class="label">ขนาดไฟล์</span><span class="value" id="d-size">—</span></div>
    <div class="data-row"><span class="label">นามสกุล</span><span class="value" id="d-ext">—</span></div>
    <div class="data-row"><span class="label">การเข้าถึง</span><span class="value" id="d-visibility">—</span></div>
    <div class="data-row"><span class="label">ยอดดู</span><span class="value" id="d-views">0</span></div>
  </div>

  <!-- 3D Model Stats Section -->
  <div class="data-section" id="section-model" style="display:none;">
    <h4>📊 สถิติโมเดล 3D</h4>
    <div class="data-row"><span class="label">Meshes</span><span class="value" id="d-meshes">—</span></div>
    <div class="data-row"><span class="label">Vertices</span><span class="value" id="d-vertices">—</span></div>
    <div class="data-row"><span class="label">Triangles</span><span class="value" id="d-triangles">—</span></div>
    <div class="data-row"><span class="label">Materials</span><span class="value" id="d-materials">—</span></div>
    <div class="data-row"><span class="label">Textures</span><span class="value" id="d-textures">—</span></div>
    <div class="data-row"><span class="label">Animations</span><span class="value" id="d-anims">—</span></div>
  </div>

  <!-- Embed Info Section -->
  <div class="data-section" id="section-embed" style="display:none;">
    <h4>🔗 ข้อมูล Embed</h4>
    <div class="data-row"><span class="label">Provider</span><span class="value" id="d-provider">—</span></div>
    <div class="data-row"><span class="label">Source URL</span><span class="value" id="d-embed-src">—</span></div>
  </div>

  <!-- Action Buttons -->
  <div class="data-actions">
    <button class="btn btn-sm btn-primary" onclick="openAR()">
      <i data-feather="smartphone"></i> AR
    </button>
    <button class="btn btn-sm btn-outline" onclick="openQR()">
      <i data-feather="share-2"></i> QR
    </button>
    <button class="btn btn-sm btn-outline" onclick="copyViewerUrl()">
      <i data-feather="copy"></i> URL
    </button>
    <button class="btn btn-sm btn-outline" onclick="openFullscreen()">
      <i data-feather="maximize"></i>
    </button>
  </div>

</div>
</div>

<!-- Toolbar -->
<div class="viewer-toolbar" id="toolbar">
  <button onclick="history.back()" title="กลับ"><i data-feather="arrow-left"></i></button>
  <div class="separator"></div>
  <button onclick="toggleOverlay()" id="btn-data" title="ข้อมูล / ซ่อน"><i data-feather="layers"></i></button>
  <?php if ($mode !== 'embed'): ?>
  <button onclick="toggleRotate()" id="btn-rotate" class="active" title="หมุนอัตโนมัติ"><i data-feather="refresh-cw"></i></button>
  <button onclick="resetCamera()" title="รีเซ็ตมุมมอง"><i data-feather="maximize"></i></button>
  <button onclick="toggleWireframe()" id="btn-wireframe" title="Wireframe"><i data-feather="grid"></i></button>
  <?php endif; ?>
  <div class="separator"></div>
  <button onclick="openAR()" title="เปิด AR"><i data-feather="smartphone"></i></button>
  <button onclick="openQR()" title="สร้าง QR"><i data-feather="share-2"></i></button>
</div>

<script src="<?= BASE_URL ?>/third_party/three.js"></script>
<script src="<?= BASE_URL ?>/third_party/GLTFLoader.js"></script>
<script>
var BASE = '<?= BASE_URL ?>';
var modelSrc = '<?= addslashes($src) ?>';
var fileId = <?= $fileId ?>;
var viewMode = '<?= $mode ?>';
var isTransparent = <?= $transparent ? 'true' : 'false' ?>;
var embedSrc = '<?= addslashes($embedSrc) ?>';

var scene, camera, renderer, model, mixer, clock;
var autoRotate = true, wireframeMode = false;
var overlayVisible = false;
var fileData = null;

/* ═══════════════════════════════════════════
   Load file metadata from API
   ═══════════════════════════════════════════ */
function loadFileData() {
  if (fileId) {
    fetch(BASE + '/api/index.php?action=files&id=' + fileId)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.data) {
          populateOverlay(d.data);
          // Auto-switch to embed mode if this file is an embed
          if (d.data.source_type === 'embed' && viewMode !== 'embed') {
            viewMode = 'embed';
            embedSrc = d.data.embed_src || '';
            var canvas = document.getElementById('viewer-canvas');
            if (canvas) canvas.style.display = 'none';
            var ev = document.getElementById('embed-viewer');
            if (ev && embedSrc) {
              ev.style.display = 'block';
              ev.innerHTML = d.data.embed_code || '<iframe src="' + embedSrc + '" style="width:100%;height:100%;border:none" allowfullscreen></iframe>';
            }
          }
        } else { populateBasic(); }
      })
      .catch(function() { populateBasic(); });
  } else {
    // Search by URL match
    fetch(BASE + '/api/index.php?action=files&limit=200')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var files = (d.data && d.data.files) || [];
        for (var i = 0; i < files.length; i++) {
          if (files[i].file_url === modelSrc || files[i].embed_src === embedSrc) {
            populateOverlay(files[i]);
            return;
          }
        }
        populateBasic();
      })
      .catch(function() { populateBasic(); });
  }
}

function populateOverlay(f) {
  fileData = f;

  // Title
  document.getElementById('overlay-title').textContent = f.title || f.name || 'Untitled';

  // Badges
  var badges = '';
  var catSlug = f.category_slug || 'model';
  badges += '<span class="badge badge-' + catSlug + '">' + (f.category_name || catSlug) + '</span>';
  if (f.visibility && f.visibility !== 'public') {
    badges += '<span class="badge" style="background:rgba(253,203,110,.15);color:#FDCB6E;">' + f.visibility + '</span>';
  }
  if (f.source_type === 'embed') {
    badges += '<span class="badge" style="background:rgba(116,185,255,.15);color:#74b9ff;">embed</span>';
  }
  document.getElementById('overlay-badges').innerHTML = badges;

  // Description
  if (f.description) {
    document.getElementById('overlay-desc').style.display = 'block';
    document.getElementById('overlay-desc').textContent = f.description;
  }

  // File info rows
  document.getElementById('d-uploader').textContent = f.uploader || '—';
  document.getElementById('d-category').textContent = f.category_name || f.category_slug || '—';
  document.getElementById('d-date').textContent = formatDate(f.created_at || f.uploaded_at);
  document.getElementById('d-size').textContent = f.file_size ? formatSize(f.file_size) : '—';
  document.getElementById('d-ext').textContent = f.extension || '—';
  document.getElementById('d-visibility').textContent = f.visibility || 'public';
  document.getElementById('d-views').textContent = (f.view_count || 0).toLocaleString();

  // Embed section
  if (f.embed_src || f.source_type === 'embed') {
    document.getElementById('section-embed').style.display = 'block';
    document.getElementById('d-provider').textContent = f.embed_provider || '—';
    document.getElementById('d-embed-src').textContent = f.embed_src || '—';
    document.getElementById('d-embed-src').title = f.embed_src || '';
  }

  // Increment view count
  if (f.id) {
    fetch(BASE + '/api/index.php?action=view&id=' + f.id, { method: 'POST' });
  }
}

function populateBasic() {
  var name = (modelSrc && modelSrc !== 'null' ? modelSrc.split('/').pop() : '') || (embedSrc ? embedSrc.split('/').pop() : '') || 'Unknown';
  document.getElementById('overlay-title').textContent = decodeURIComponent(name);
}

function formatDate(d) {
  if (!d) return '—';
  var dt = new Date(d);
  if (isNaN(dt)) return d;
  return dt.toLocaleDateString('th-TH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
}

function formatSize(b) {
  b = parseInt(b);
  if (!b || isNaN(b)) return '—';
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
  return (b / 1048576).toFixed(1) + ' MB';
}

/* ═══════════════════════════════════════════
   Overlay toggle
   ═══════════════════════════════════════════ */
function toggleOverlay() {
  overlayVisible = !overlayVisible;
  document.getElementById('data-overlay').classList.toggle('visible', overlayVisible);
  var btn = document.getElementById('btn-data');
  if (btn) btn.classList.toggle('active', overlayVisible);
}

function copyViewerUrl() {
  navigator.clipboard.writeText(window.location.href).then(function() {
    showToastMsg('คัดลอก URL แล้ว');
  });
}

function openFullscreen() {
  var el = document.documentElement;
  if (el.requestFullscreen) el.requestFullscreen();
  else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
}

function showToastMsg(msg) {
  var t = document.createElement('div');
  t.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%);z-index:999;padding:10px 28px;background:rgba(26,26,46,.95);color:#00B894;border:1px solid rgba(0,184,148,.3);border-radius:10px;font-size:.85rem;pointer-events:none;';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(function() { t.remove(); }, 2500);
}

/* ═══════════════════════════════════════════
   3D Viewer (model mode only)
   ═══════════════════════════════════════════ */
function init3D() {
  if (viewMode === 'embed') return;
  if (!modelSrc || modelSrc === 'null') return;

  var container = document.getElementById('viewer-canvas');
  clock = new THREE.Clock();

  scene = new THREE.Scene();
  if (!isTransparent) {
    scene.background = new THREE.Color(0x0f0f1a);
  }

  camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.01, 1000);
  camera.position.set(0, 1.5, 3);

  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: isTransparent });
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  if (isTransparent) {
    renderer.setClearColor(0x000000, 0);
  }
  renderer.outputEncoding = THREE.sRGBEncoding;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.2;
  container.appendChild(renderer.domElement);

  // Lights
  scene.add(new THREE.AmbientLight(0xffffff, 0.6));
  var dir = new THREE.DirectionalLight(0xffffff, 0.8);
  dir.position.set(5, 10, 7);
  scene.add(dir);
  var dir2 = new THREE.DirectionalLight(0x6C5CE7, 0.3);
  dir2.position.set(-5, 5, -5);
  scene.add(dir2);

  // Grid (repositioned to model bottom after load) — hidden in transparent mode
  var grid = null;
  if (!isTransparent) {
    grid = new THREE.GridHelper(10, 20, 0x2d2d50, 0x1a1a2e);
    scene.add(grid);
  }

  // Orbit controls
  var isDragging = false, prevMouse = { x: 0, y: 0 };
  var spherical = { theta: 0, phi: Math.PI / 4, radius: 3 };

  function updateCamera() {
    camera.position.x = spherical.radius * Math.sin(spherical.phi) * Math.sin(spherical.theta);
    camera.position.y = spherical.radius * Math.cos(spherical.phi);
    camera.position.z = spherical.radius * Math.sin(spherical.phi) * Math.cos(spherical.theta);
    camera.lookAt(0, window._lookAtY !== undefined ? window._lookAtY : 0.5, 0);
  }
  updateCamera();

  renderer.domElement.addEventListener('mousedown', function(e) { isDragging = true; prevMouse = { x: e.clientX, y: e.clientY }; autoRotate = false; updateRotateBtn(); });
  renderer.domElement.addEventListener('mousemove', function(e) {
    if (!isDragging) return;
    spherical.theta -= (e.clientX - prevMouse.x) * 0.005;
    spherical.phi = Math.max(0.1, Math.min(Math.PI - 0.1, spherical.phi + (e.clientY - prevMouse.y) * 0.005));
    prevMouse = { x: e.clientX, y: e.clientY };
    updateCamera();
  });
  window.addEventListener('mouseup', function() { isDragging = false; });
  renderer.domElement.addEventListener('wheel', function(e) {
    spherical.radius = Math.max(0.5, Math.min(20, spherical.radius + e.deltaY * 0.005));
    updateCamera();
  });

  // Touch
  var touchStart = null, lastPinchDist = 0;
  renderer.domElement.addEventListener('touchstart', function(e) {
    if (e.touches.length === 1) {
      touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
      autoRotate = false; updateRotateBtn();
    } else if (e.touches.length === 2) {
      lastPinchDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
    }
  });
  renderer.domElement.addEventListener('touchmove', function(e) {
    e.preventDefault();
    if (e.touches.length === 1 && touchStart) {
      spherical.theta -= (e.touches[0].clientX - touchStart.x) * 0.005;
      spherical.phi = Math.max(0.1, Math.min(Math.PI - 0.1, spherical.phi + (e.touches[0].clientY - touchStart.y) * 0.005));
      touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
      updateCamera();
    } else if (e.touches.length === 2) {
      var dist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
      spherical.radius = Math.max(0.5, Math.min(20, spherical.radius * (lastPinchDist / dist)));
      lastPinchDist = dist;
      updateCamera();
    }
  }, { passive: false });

  // Load model
  var loader = new THREE.GLTFLoader();
  loader.load(modelSrc, function(gltf) {
    model = gltf.scene;
    var box = new THREE.Box3().setFromObject(model);
    var size = box.getSize(new THREE.Vector3());
    var center = box.getCenter(new THREE.Vector3());
    var maxDim = Math.max(size.x, size.y, size.z);
    var scale = 2 / maxDim;
    model.scale.setScalar(scale);
    model.position.sub(center.multiplyScalar(scale));
    scene.add(model);

    // Reposition grid to bottom of model & adjust camera target
    var worldBox = new THREE.Box3().setFromObject(model);
    if (grid) grid.position.y = worldBox.min.y;
    window._lookAtY = (worldBox.min.y + worldBox.max.y) / 2;
    updateCamera();

    if (gltf.animations && gltf.animations.length) {
      mixer = new THREE.AnimationMixer(model);
      gltf.animations.forEach(function(clip) { mixer.clipAction(clip).play(); });
    }

    // Collect model statistics
    var meshCount = 0, vertexCount = 0, triCount = 0, matSet = new Set(), texCount = 0;
    model.traverse(function(child) {
      if (child.isMesh) {
        meshCount++;
        if (child.geometry) {
          if (child.geometry.attributes.position) vertexCount += child.geometry.attributes.position.count;
          if (child.geometry.index) triCount += child.geometry.index.count / 3;
          else if (child.geometry.attributes.position) triCount += child.geometry.attributes.position.count / 3;
        }
        if (child.material) {
          var mats = Array.isArray(child.material) ? child.material : [child.material];
          mats.forEach(function(m) {
            matSet.add(m.name || m.uuid);
            if (m.map) texCount++;
            if (m.normalMap) texCount++;
            if (m.roughnessMap) texCount++;
            if (m.metalnessMap) texCount++;
          });
        }
      }
    });

    document.getElementById('section-model').style.display = 'block';
    document.getElementById('d-meshes').textContent = meshCount.toLocaleString();
    document.getElementById('d-vertices').textContent = vertexCount.toLocaleString();
    document.getElementById('d-triangles').textContent = Math.floor(triCount).toLocaleString();
    document.getElementById('d-materials').textContent = matSet.size;
    document.getElementById('d-textures').textContent = texCount;
    document.getElementById('d-anims').textContent = gltf.animations ? gltf.animations.length : 0;

    feather.replace();
  }, undefined, function(err) { console.error('Model load error:', err); });

  window._spherical = spherical;
  window._updateCamera = updateCamera;

  function animate() {
    requestAnimationFrame(animate);
    if (mixer) mixer.update(clock.getDelta());
    if (autoRotate) { spherical.theta += 0.003; updateCamera(); }
    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', function() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });
}

function toggleRotate() { autoRotate = !autoRotate; updateRotateBtn(); }
function updateRotateBtn() { var b = document.getElementById('btn-rotate'); if (b) b.classList.toggle('active', autoRotate); }
function resetCamera() {
  if (!window._spherical) return;
  window._spherical.theta = 0; window._spherical.phi = Math.PI / 4; window._spherical.radius = 3;
  window._updateCamera();
}
function toggleWireframe() {
  wireframeMode = !wireframeMode;
  var b = document.getElementById('btn-wireframe'); if (b) b.classList.toggle('active', wireframeMode);
  if (model) model.traverse(function(c) {
    if (c.isMesh && c.material) {
      var mats = Array.isArray(c.material) ? c.material : [c.material];
      mats.forEach(function(m) { m.wireframe = wireframeMode; });
    }
  });
}

function openAR() {
  var params = '';
  if (modelSrc && modelSrc !== 'null') params += '?src=' + encodeURIComponent(modelSrc);
  var sep = params ? '&' : '?';
  if (fileId) { params += sep + 'id=' + fileId; sep = '&'; }
  else if (fileData && fileData.id) { params += sep + 'id=' + fileData.id; sep = '&'; }
  if (embedSrc) { params += sep + 'embed=' + encodeURIComponent(embedSrc); sep = '&'; }
  if (viewMode === 'embed') { params += sep + 'mode=embed'; }
  window.location.href = BASE + '/pages/ar.php' + params;
}
function openQR() {
  var src = (modelSrc && modelSrc !== 'null') ? modelSrc : (embedSrc || window.location.href);
  window.location.href = BASE + '/pages/qr.php?src=' + encodeURIComponent(src);
}

/* ─── Initialize ─── */
init3D();
loadFileData();
// Auto-show overlay after a short delay
setTimeout(function() { toggleOverlay(); }, 600);
feather.replace();
</script>
</body>
</html>
