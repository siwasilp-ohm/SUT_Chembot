<?php
require_once __DIR__ . '/../core/config.php';
$src = isset($_GET['src']) ? $_GET['src'] : (BASE_URL . '/assets/sample.jpg');
$title = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : 'Panorama Viewer';
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
  body { margin:0; overflow:hidden; background:#000; }
  #pano-canvas { position:fixed; inset:0; }
  #pano-canvas canvas { width:100%!important; height:100%!important; display:block; }
</style>
</head>
<body>

<div id="pano-canvas"></div>

<!-- Toolbar -->
<div class="viewer-toolbar">
  <button onclick="history.back()" title="กลับ"><i data-feather="arrow-left"></i></button>
  <div class="separator"></div>
  <button onclick="toggleAutoRotate()" id="btn-rotate" class="active" title="หมุนอัตโนมัติ"><i data-feather="refresh-cw"></i></button>
  <button onclick="resetView()" title="รีเซ็ตมุมมอง"><i data-feather="maximize"></i></button>
  <button onclick="toggleGyro()" id="btn-gyro" title="ไจโรสโคป"><i data-feather="navigation"></i></button>
</div>

<script src="<?= BASE_URL ?>/third_party/three.js"></script>
<script>
var panoSrc = '<?= addslashes($src) ?>';
var scene, camera, renderer;
var autoRotate = true;
var lon = 0, lat = 0, phi = 0, theta = 0;
var isDragging = false, prevMouse = { x:0, y:0 };
var useGyro = false, deviceOrientation = null;

function init() {
  var container = document.getElementById('pano-canvas');

  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(75, window.innerWidth/window.innerHeight, 0.1, 1100);

  renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  container.appendChild(renderer.domElement);

  // Panorama sphere (inside-out)
  var geometry = new THREE.SphereGeometry(500, 60, 40);
  geometry.scale(-1, 1, 1);
  var texture = new THREE.TextureLoader().load(panoSrc);
  texture.encoding = THREE.sRGBEncoding;
  var material = new THREE.MeshBasicMaterial({ map: texture });
  var mesh = new THREE.Mesh(geometry, material);
  scene.add(mesh);

  // Mouse controls
  renderer.domElement.addEventListener('mousedown', function(e) {
    isDragging = true; prevMouse = {x:e.clientX, y:e.clientY};
    autoRotate = false; updateRotateBtn();
  });
  renderer.domElement.addEventListener('mousemove', function(e) {
    if (!isDragging) return;
    lon -= (e.clientX - prevMouse.x) * 0.15;
    lat += (e.clientY - prevMouse.y) * 0.15;
    prevMouse = {x:e.clientX, y:e.clientY};
  });
  window.addEventListener('mouseup', function() { isDragging = false; });

  // Touch controls
  var touchStart = null;
  renderer.domElement.addEventListener('touchstart', function(e) {
    if (e.touches.length === 1) {
      touchStart = {x:e.touches[0].clientX, y:e.touches[0].clientY};
      autoRotate = false; updateRotateBtn();
    }
  });
  renderer.domElement.addEventListener('touchmove', function(e) {
    if (!touchStart || e.touches.length !== 1) return;
    e.preventDefault();
    lon -= (e.touches[0].clientX - touchStart.x) * 0.15;
    lat += (e.touches[0].clientY - touchStart.y) * 0.15;
    touchStart = {x:e.touches[0].clientX, y:e.touches[0].clientY};
  }, {passive:false});

  // FOV zoom
  renderer.domElement.addEventListener('wheel', function(e) {
    camera.fov = Math.max(30, Math.min(110, camera.fov + e.deltaY * 0.05));
    camera.updateProjectionMatrix();
  });

  // Gyroscope
  window.addEventListener('deviceorientation', function(e) {
    if (useGyro) deviceOrientation = e;
  });

  function animate() {
    requestAnimationFrame(animate);
    if (autoRotate) lon += 0.05;

    if (useGyro && deviceOrientation) {
      var alpha = deviceOrientation.alpha || 0;
      var beta = deviceOrientation.beta || 0;
      lon = alpha;
      lat = beta - 90;
    }

    lat = Math.max(-85, Math.min(85, lat));
    phi = THREE.MathUtils.degToRad(90 - lat);
    theta = THREE.MathUtils.degToRad(lon);

    var target = new THREE.Vector3(
      500 * Math.sin(phi) * Math.cos(theta),
      500 * Math.cos(phi),
      500 * Math.sin(phi) * Math.sin(theta)
    );
    camera.lookAt(target);
    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', function() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });
}

function toggleAutoRotate() {
  autoRotate = !autoRotate;
  updateRotateBtn();
}
function updateRotateBtn() {
  document.getElementById('btn-rotate').classList.toggle('active', autoRotate);
}
function resetView() { lon = 0; lat = 0; camera.fov = 75; camera.updateProjectionMatrix(); }
function toggleGyro() {
  useGyro = !useGyro;
  document.getElementById('btn-gyro').classList.toggle('active', useGyro);
  if (useGyro && typeof DeviceOrientationEvent !== 'undefined' &&
      typeof DeviceOrientationEvent.requestPermission === 'function') {
    DeviceOrientationEvent.requestPermission().then(function(r) {
      if (r !== 'granted') { useGyro = false; document.getElementById('btn-gyro').classList.remove('active'); }
    });
  }
}

init();
feather.replace();
</script>
</body>
</html>
