<?php
require_once __DIR__ . '/../core/config.php';
$src = isset($_GET['src']) ? $_GET['src'] : (BASE_URL . '/assets/robot.glb');
$title = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : 'AR Viewer';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'model';
$embedSrc = isset($_GET['embed']) ? $_GET['embed'] : '';
$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no">
<title><?= $title ?> — AR Mode — VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
<style>
  * { box-sizing:border-box; }
  html, body { margin:0; padding:0; overflow:hidden; background:#000; width:100%; height:100%; touch-action:none; }

  /* Camera layer */
  .ar-camera-layer {
    position:fixed; inset:0; z-index:1;
  }
  .ar-camera-layer video {
    width:100%; height:100%; object-fit:cover; display:block;
  }
  .ar-camera-fallback {
    position:fixed; inset:0; z-index:1;
    background:linear-gradient(135deg, #0f0f1a 0%, #1a1a3e 50%, #2d1a4e 100%);
    display:none;
  }
  .ar-camera-fallback::after {
    content:'📷 กล้องไม่พร้อมใช้งาน';
    position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    color:rgba(255,255,255,.3); font-size:.9rem;
  }

  /* 3D overlay */
  .ar-3d-layer {
    position:fixed; inset:0; z-index:10; pointer-events:none;
  }
  .ar-3d-layer canvas { display:block; width:100%!important; height:100%!important; pointer-events:auto; }

  /* Embed overlay */
  .ar-embed-layer {
    position:fixed; z-index:10; pointer-events:auto;
    left:0; right:0; top:0; bottom:0;
    overflow:hidden;
    background:transparent;
  }
  .ar-embed-layer iframe { width:100%; height:100%; border:none; background:transparent; }

  /* HUD */
  .ar-hud-top {
    position:fixed; top:0; left:0; right:0; z-index:50;
    padding:12px 16px; padding-top: max(12px, env(safe-area-inset-top));
    display:flex; align-items:center; justify-content:space-between;
  }
  .ar-hud-btn {
    background:rgba(0,0,0,.55); backdrop-filter:blur(10px);
    border:1px solid rgba(255,255,255,.1); border-radius:50%;
    width:42px; height:42px; display:flex; align-items:center; justify-content:center;
    cursor:pointer; color:#fff; transition:all .2s;
  }
  .ar-hud-btn:hover { background:rgba(108,92,231,.5); }
  .ar-hud-btn.active { background:rgba(108,92,231,.6); border-color:var(--primary); }

  .ar-status-badge {
    background:rgba(0,0,0,.55); backdrop-filter:blur(10px);
    padding:5px 16px; border-radius:20px; font-size:.72rem; color:#fff;
    border:1px solid rgba(255,255,255,.1);
    display:flex; align-items:center; gap:6px;
  }
  .ar-status-dot {
    width:6px; height:6px; border-radius:50%; background:#00B894;
    animation:pulse 2s infinite;
  }
  @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.3; } }

  /* Bottom controls */
  .ar-controls {
    position:fixed; bottom:0; left:0; right:0; z-index:50;
    padding:16px; padding-bottom:max(16px, env(safe-area-inset-bottom));
    display:flex; flex-direction:column; align-items:center; gap:10px;
  }
  .ar-control-row {
    display:flex; gap:8px; align-items:center;
    background:rgba(0,0,0,.55); backdrop-filter:blur(10px);
    padding:8px 12px; border-radius:28px;
    border:1px solid rgba(255,255,255,.08);
  }
  .ar-ctrl-btn {
    background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.1);
    border-radius:50%; width:44px; height:44px;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; color:#fff; transition:all .2s;
  }
  .ar-ctrl-btn:active { transform:scale(.9); }
  .ar-ctrl-btn.active { background:rgba(108,92,231,.5); border-color:var(--primary); }
  .ar-ctrl-btn.primary-btn { background:var(--primary); border-color:var(--primary); width:52px; height:52px; }
  .ar-ctrl-divider { width:1px; height:28px; background:rgba(255,255,255,.15); margin:0 4px; }

  /* Gyro indicator */
  .gyro-indicator {
    position:fixed; top:65px; right:16px; z-index:50;
    background:rgba(0,0,0,.6); backdrop-filter:blur(10px);
    border:1px solid rgba(255,255,255,.1); border-radius:12px;
    padding:8px 12px; font-size:.65rem; color:var(--text-muted);
    display:none;
  }
  .gyro-indicator.visible { display:flex; flex-direction:column; gap:2px; }
  .gyro-val { color:var(--primary); font-weight:600; font-family:monospace; }
</style>
</head>
<body>

<!-- Camera Layer -->
<div class="ar-camera-layer" id="camera-layer">
  <video id="camera-feed" autoplay playsinline muted></video>
</div>
<div class="ar-camera-fallback" id="camera-fallback"></div>

<!-- 3D Layer (model mode) -->
<div class="ar-3d-layer" id="ar-3d-layer" style="<?= $mode === 'embed' ? 'display:none' : '' ?>"></div>

<!-- Embed Layer (embed mode) -->
<?php if ($mode === 'embed' && $embedSrc): ?>
<div class="ar-embed-layer" id="ar-embed-layer">
  <iframe src="<?= htmlspecialchars($embedSrc) . (strpos($embedSrc, '?') !== false ? '&' : '?') . 'transparent=1' ?>" allowfullscreen
          allow="autoplay; xr-spatial-tracking"
          style="background:transparent;"
          sandbox="allow-scripts allow-same-origin allow-popups allow-forms"></iframe>
</div>
<?php elseif ($src && $mode !== 'embed'): ?>
<div class="ar-embed-layer" id="ar-embed-layer" style="display:none;">
  <iframe id="model-iframe" allowfullscreen allow="autoplay; xr-spatial-tracking" style="background:transparent;"></iframe>
</div>
<?php endif; ?>

<!-- HUD Top -->
<div class="ar-hud-top">
  <button class="ar-hud-btn" onclick="history.back()" title="กลับ">
    <i data-feather="arrow-left" style="width:18px;height:18px;"></i>
  </button>
  <div class="ar-status-badge">
    <span class="ar-status-dot"></span>
    <span id="ar-status-text">AR Mode</span>
  </div>
  <button class="ar-hud-btn" onclick="toggleGyroInfo()" title="Gyro Info" id="btn-gyro-info">
    <i data-feather="compass" style="width:18px;height:18px;"></i>
  </button>
</div>

<!-- Gyro Indicator -->
<div class="gyro-indicator" id="gyro-indicator">
  <div>α <span class="gyro-val" id="gyro-alpha">—</span>°</div>
  <div>β <span class="gyro-val" id="gyro-beta">—</span>°</div>
  <div>γ <span class="gyro-val" id="gyro-gamma">—</span>°</div>
</div>

<!-- Bottom Controls -->
<div class="ar-controls">
  <div class="ar-control-row">
    <button class="ar-ctrl-btn" onclick="scaleDown()" title="ย่อ">
      <i data-feather="minus" style="width:18px;height:18px;"></i>
    </button>
    <button class="ar-ctrl-btn" onclick="resetModel()" title="รีเซ็ต">
      <i data-feather="rotate-ccw" style="width:18px;height:18px;"></i>
    </button>
    <button class="ar-ctrl-btn primary-btn" onclick="toggleGyro()" id="btn-gyro" title="Gyroscope">
      <i data-feather="navigation" style="width:20px;height:20px;"></i>
    </button>
    <button class="ar-ctrl-btn" onclick="toggleSpin()" id="btn-spin" class="active" title="หมุน">
      <i data-feather="refresh-cw" style="width:18px;height:18px;"></i>
    </button>
    <button class="ar-ctrl-btn" onclick="scaleUp()" title="ขยาย">
      <i data-feather="plus" style="width:18px;height:18px;"></i>
    </button>
  </div>
</div>

<script src="<?= BASE_URL ?>/third_party/three.js"></script>
<script src="<?= BASE_URL ?>/third_party/GLTFLoader.js"></script>
<script>
var BASE = '<?= BASE_URL ?>';
var modelSrc = '<?= addslashes($src) ?>';
var viewMode = '<?= $mode ?>';
var embedSrc = '<?= addslashes($embedSrc) ?>';

var scene, camera, renderer, model, mixer, clock;
var arSpin = true, modelScale = 1, baseScale = 1;
var gyroEnabled = false, gyroAvailable = false;
var gyroAlpha = 0, gyroBeta = 0, gyroGamma = 0;
var initialAlpha = null, alphaOffset = 0;
var showGyroInfo = false;
var touchRotY = 0;

/* ═══════════════════════════════════════════
   Camera Feed
   ═══════════════════════════════════════════ */
function startCamera() {
  var video = document.getElementById('camera-feed');
  var fallback = document.getElementById('camera-fallback');

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    video.style.display = 'none';
    fallback.style.display = 'block';
    return;
  }

  navigator.mediaDevices.getUserMedia({
    video: {
      facingMode: { ideal: 'environment' },
      width: { ideal: 1920 },
      height: { ideal: 1080 }
    },
    audio: false
  })
  .then(function(stream) {
    video.srcObject = stream;
    video.play();
  })
  .catch(function() {
    video.style.display = 'none';
    fallback.style.display = 'block';
  });
}

/* ═══════════════════════════════════════════
   Gyroscope / Device Orientation
   ═══════════════════════════════════════════ */
function initGyroscope() {
  // Check if DeviceOrientationEvent is available
  if (!window.DeviceOrientationEvent) {
    document.getElementById('ar-status-text').textContent = 'AR Mode (ไม่มี Gyro)';
    return;
  }

  // iOS 13+ requires permission
  if (typeof DeviceOrientationEvent.requestPermission === 'function') {
    // Will request on user tap of gyro button
    gyroAvailable = true;
    document.getElementById('ar-status-text').textContent = 'AR Mode (Gyro พร้อม)';
  } else {
    // Android / other — test with a listener
    var testHandler = function(e) {
      if (e.alpha !== null) {
        gyroAvailable = true;
        document.getElementById('ar-status-text').textContent = 'AR Mode (Gyro พร้อม)';
      }
      window.removeEventListener('deviceorientation', testHandler);
    };
    window.addEventListener('deviceorientation', testHandler);
    // Fallback if no event fires
    setTimeout(function() {
      window.removeEventListener('deviceorientation', testHandler);
      if (!gyroAvailable) {
        document.getElementById('ar-status-text').textContent = 'AR Mode (ไม่มี Gyro)';
      }
    }, 2000);
  }
}

function enableGyro() {
  if (typeof DeviceOrientationEvent.requestPermission === 'function') {
    // iOS permission request
    DeviceOrientationEvent.requestPermission()
      .then(function(state) {
        if (state === 'granted') {
          gyroEnabled = true;
          arSpin = false;
          initialAlpha = null;
          window.addEventListener('deviceorientation', handleOrientation, true);
          updateGyroBtn();
          updateSpinBtn();
        }
      })
      .catch(function(err) {
        console.warn('Gyro permission denied:', err);
      });
  } else {
    // Android — just enable
    gyroEnabled = true;
    arSpin = false;
    initialAlpha = null;
    window.addEventListener('deviceorientation', handleOrientation, true);
    updateGyroBtn();
    updateSpinBtn();
  }
}

function disableGyro() {
  gyroEnabled = false;
  window.removeEventListener('deviceorientation', handleOrientation, true);
  updateGyroBtn();
}

function handleOrientation(e) {
  if (!gyroEnabled) return;

  var alpha = e.alpha || 0;  // 0-360 compass
  var beta = e.beta || 0;    // -180 to 180 front-back
  var gamma = e.gamma || 0;  // -90 to 90 left-right

  // Set initial alpha as reference point
  if (initialAlpha === null) {
    initialAlpha = alpha;
    alphaOffset = alpha;
  }

  gyroAlpha = alpha;
  gyroBeta = beta;
  gyroGamma = gamma;

  // Update gyro indicator
  if (showGyroInfo) {
    document.getElementById('gyro-alpha').textContent = alpha.toFixed(1);
    document.getElementById('gyro-beta').textContent = beta.toFixed(1);
    document.getElementById('gyro-gamma').textContent = gamma.toFixed(1);
  }

  // Apply to camera for AR effect
  if (camera) {
    // Convert degrees to radians
    var DEG2RAD = Math.PI / 180;

    // Compute camera orientation from device orientation
    // Beta controls up-down tilt (pitch)
    // Gamma controls left-right tilt (roll)
    // Alpha controls compass direction (yaw)

    var relativeAlpha = ((alpha - alphaOffset + 360) % 360);

    // Smooth camera Y rotation (yaw from compass)
    var targetYaw = -relativeAlpha * DEG2RAD;

    // Pitch from beta (phone tilt forward/back)
    // When phone is vertical (beta=90), camera looks forward
    // When tilted down (beta>90), camera looks down
    var pitch = -(beta - 90) * DEG2RAD * 0.6;
    pitch = Math.max(-Math.PI / 3, Math.min(Math.PI / 3, pitch));

    // Roll from gamma (phone tilt left/right)
    var roll = -gamma * DEG2RAD * 0.3;

    // Apply using euler (YXZ order for device orientation)
    camera.rotation.order = 'YXZ';
    camera.rotation.y = targetYaw;
    camera.rotation.x = pitch;
    camera.rotation.z = roll;
  }

  // For embed mode — apply parallax to embed container
  if (viewMode === 'embed') {
    var embedLayer = document.getElementById('ar-embed-layer');
    if (embedLayer) {
      var relAlpha = ((alpha - alphaOffset + 360) % 360);
      if (relAlpha > 180) relAlpha -= 360;
      var tx = -(relAlpha * 0.5);
      var ty = -((beta - 90) * 0.3);
      tx = Math.max(-30, Math.min(30, tx));
      ty = Math.max(-20, Math.min(20, ty));
      embedLayer.style.transform = 'translate(' + tx + 'px, ' + ty + 'px)';
    }
  }
}

/* ═══════════════════════════════════════════
   3D Scene Init (model mode)
   ═══════════════════════════════════════════ */
function init3D() {
  if (viewMode === 'embed') return;

  clock = new THREE.Clock();
  var container = document.getElementById('ar-3d-layer');

  // Scene
  scene = new THREE.Scene();

  // Camera
  camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.01, 100);
  camera.position.set(0, 1.0, 2.5);

  // Renderer (transparent)
  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setClearColor(0x000000, 0);
  renderer.outputEncoding = THREE.sRGBEncoding;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.1;
  container.appendChild(renderer.domElement);

  // Lights — realistic AR lighting
  var amb = new THREE.AmbientLight(0xffffff, 0.7);
  scene.add(amb);

  var mainLight = new THREE.DirectionalLight(0xffffff, 0.8);
  mainLight.position.set(3, 8, 5);
  mainLight.castShadow = true;
  scene.add(mainLight);

  var fillLight = new THREE.DirectionalLight(0x6C5CE7, 0.2);
  fillLight.position.set(-3, 4, -3);
  scene.add(fillLight);

  var rimLight = new THREE.DirectionalLight(0x74b9ff, 0.15);
  rimLight.position.set(0, 2, -5);
  scene.add(rimLight);

  // Transparent ground shadow
  var shadowPlane = new THREE.Mesh(
    new THREE.PlaneGeometry(20, 20),
    new THREE.ShadowMaterial({ opacity: 0.2 })
  );
  shadowPlane.rotation.x = -Math.PI / 2;
  shadowPlane.receiveShadow = true;
  scene.add(shadowPlane);

  camera.lookAt(0, 0.5, 0);

  // Load Model
  var loader = new THREE.GLTFLoader();
  loader.load(modelSrc, function(gltf) {
    model = gltf.scene;
    var box = new THREE.Box3().setFromObject(model);
    var size = box.getSize(new THREE.Vector3());
    var center = box.getCenter(new THREE.Vector3());
    var maxDim = Math.max(size.x, size.y, size.z);
    baseScale = 1.5 / maxDim;
    model.scale.setScalar(baseScale);
    model.position.sub(center.multiplyScalar(baseScale));
    model.position.y += size.y * baseScale * 0.1;
    scene.add(model);

    if (gltf.animations && gltf.animations.length) {
      mixer = new THREE.AnimationMixer(model);
      gltf.animations.forEach(function(clip) { mixer.clipAction(clip).play(); });
    }
  }, undefined, function(err) { console.error('AR model load error:', err); });

  // Touch controls — rotate model
  var touchStartX = null, touchStartY = null;
  var modelRotX = 0;

  renderer.domElement.addEventListener('touchstart', function(e) {
    if (e.touches.length === 1) {
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
      if (!gyroEnabled) { arSpin = false; updateSpinBtn(); }
    }
  });

  renderer.domElement.addEventListener('touchmove', function(e) {
    if (!touchStartX || !model || e.touches.length !== 1) return;
    e.preventDefault();
    var dx = e.touches[0].clientX - touchStartX;
    var dy = e.touches[0].clientY - touchStartY;
    touchRotY += dx * 0.008;
    modelRotX += dy * 0.004;
    modelRotX = Math.max(-0.5, Math.min(0.5, modelRotX));
    model.rotation.y = touchRotY;
    model.rotation.x = modelRotX;
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
  }, { passive: false });

  // Mouse fallback
  var mouseDown = false;
  renderer.domElement.addEventListener('mousedown', function(e) {
    mouseDown = true; touchStartX = e.clientX; touchStartY = e.clientY;
    if (!gyroEnabled) { arSpin = false; updateSpinBtn(); }
  });
  renderer.domElement.addEventListener('mousemove', function(e) {
    if (!mouseDown || !model) return;
    var dx = e.clientX - touchStartX;
    touchRotY += dx * 0.008;
    model.rotation.y = touchRotY;
    touchStartX = e.clientX;
  });
  window.addEventListener('mouseup', function() { mouseDown = false; });

  // Animation loop
  function animate() {
    requestAnimationFrame(animate);
    var delta = clock.getDelta();
    if (mixer) mixer.update(delta);
    if (arSpin && model && !gyroEnabled) {
      model.rotation.y += 0.005;
      touchRotY = model.rotation.y;
    }
    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', function() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });
}

/* ═══════════════════════════════════════════
   Controls
   ═══════════════════════════════════════════ */
function resetModel() {
  if (model) {
    model.rotation.set(0, 0, 0);
    touchRotY = 0;
    modelScale = 1;
    model.scale.setScalar(baseScale);
  }
  if (camera) {
    camera.position.set(0, 1.0, 2.5);
    camera.rotation.set(0, 0, 0);
    camera.lookAt(0, 0.5, 0);
  }
  initialAlpha = null;
  // Reset embed position
  var embedLayer = document.getElementById('ar-embed-layer');
  if (embedLayer) embedLayer.style.transform = '';
}

function scaleUp() {
  if (model) { modelScale *= 1.25; model.scale.multiplyScalar(1.25); }
}
function scaleDown() {
  if (model) { modelScale *= 0.8; model.scale.multiplyScalar(0.8); }
}

function toggleSpin() {
  arSpin = !arSpin;
  if (arSpin) { gyroEnabled = false; disableGyro(); }
  updateSpinBtn();
}
function updateSpinBtn() {
  var b = document.getElementById('btn-spin');
  if (b) b.classList.toggle('active', arSpin);
}

function toggleGyro() {
  if (gyroEnabled) {
    disableGyro();
  } else {
    enableGyro();
  }
}
function updateGyroBtn() {
  var b = document.getElementById('btn-gyro');
  if (b) b.classList.toggle('active', gyroEnabled);
}

function toggleGyroInfo() {
  showGyroInfo = !showGyroInfo;
  document.getElementById('gyro-indicator').classList.toggle('visible', showGyroInfo);
  document.getElementById('btn-gyro-info').classList.toggle('active', showGyroInfo);
}

/* ═══════════════════════════════════════════
   Init
   ═══════════════════════════════════════════ */
startCamera();
init3D();
initGyroscope();

// Auto-enable spin
arSpin = true;
updateSpinBtn();

feather.replace();
</script>
</body>
</html>
