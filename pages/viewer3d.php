<?php
/**
 * Standalone 3D Model Viewer
 * Self-contained Three.js viewer for Chemical Inventory
 * Supports: GLB, GLTF files
 * Params: ?src=URL&id=N&title=TEXT&transparent=1
 */
require_once __DIR__ . '/../includes/config.php';

$src = (!empty($_GET['src']) && $_GET['src'] !== 'null') ? $_GET['src'] : '';
$modelId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$title = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : 'โมเดล 3D';
$transparent = !empty($_GET['transparent']);
$embed = !empty($_GET['embed']); // minimal UI mode for iframe embedding
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?> — ChemInventory 3D</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { overflow:hidden; background:#0f0f1a; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#e0e0e0; }
<?php if ($transparent): ?>
body { background:transparent!important; }
.v3d-toolbar, .v3d-overlay { display:none!important; }
<?php endif; ?>

#viewer-canvas { position:fixed; inset:0; }
#viewer-canvas canvas { width:100%!important; height:100%!important; display:block; }

/* Loading */
.v3d-loading { position:fixed; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; z-index:100; background:#0f0f1a; transition:opacity .5s; }
.v3d-loading.hidden { opacity:0; pointer-events:none; }
.v3d-spinner { width:44px; height:44px; border:3px solid rgba(108,92,231,.2); border-top-color:#6C5CE7; border-radius:50%; animation:spin .8s linear infinite; }
.v3d-loading-text { font-size:.85rem; color:#888; }
.v3d-loading-bar { width:200px; height:4px; background:rgba(255,255,255,.08); border-radius:4px; overflow:hidden; }
.v3d-loading-fill { height:100%; background:linear-gradient(90deg,#6C5CE7,#00CEC9); border-radius:4px; transition:width .3s; width:0; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Toolbar */
.v3d-toolbar {
    position:fixed; bottom:20px; left:50%; transform:translateX(-50%); z-index:50;
    display:flex; gap:4px; padding:6px 10px;
    background:rgba(15,15,26,.92); backdrop-filter:blur(20px);
    border:1px solid rgba(108,92,231,.2); border-radius:14px;
    box-shadow:0 8px 32px rgba(0,0,0,.4);
}
.v3d-toolbar button {
    background:rgba(255,255,255,.05); border:none; color:#aaa;
    width:40px; height:40px; border-radius:10px; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    font-size:15px; transition:all .15s;
}
.v3d-toolbar button:hover { background:rgba(108,92,231,.15); color:#fff; }
.v3d-toolbar button.active { background:rgba(108,92,231,.25); color:#6C5CE7; }
.v3d-toolbar .sep { width:1px; background:rgba(255,255,255,.08); margin:6px 4px; }

/* Info Overlay */
.v3d-overlay {
    position:fixed; left:16px; bottom:80px; z-index:40;
    width:300px; max-height:calc(100vh - 140px);
    background:rgba(15,15,26,.93); backdrop-filter:blur(20px);
    border:1px solid rgba(108,92,231,.2); border-radius:14px;
    padding:0; overflow:hidden;
    transform:translateX(-120%); transition:transform .4s cubic-bezier(.4,0,.2,1);
    box-shadow:0 8px 32px rgba(0,0,0,.5);
}
.v3d-overlay.visible { transform:translateX(0); }
.v3d-overlay-inner { padding:18px; overflow-y:auto; max-height:calc(100vh - 150px); }
.v3d-overlay-inner::-webkit-scrollbar { width:3px; }
.v3d-overlay-inner::-webkit-scrollbar-thumb { background:rgba(108,92,231,.3); border-radius:3px; }
.v3d-overlay h3 { font-size:.95rem; font-weight:700; margin:0 0 12px; display:flex; align-items:center; gap:8px; }
.v3d-section { margin-top:14px; padding-top:12px; border-top:1px solid rgba(255,255,255,.06); }
.v3d-section h4 { font-size:.7rem; color:#888; text-transform:uppercase; letter-spacing:.8px; margin-bottom:8px; font-weight:600; }
.v3d-row { display:flex; justify-content:space-between; padding:4px 0; font-size:.82rem; }
.v3d-row .label { color:#888; }
.v3d-row .value { color:#e0e0e0; font-weight:500; text-align:right; max-width:170px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

/* Toast */
.v3d-toast {
    position:fixed; bottom:80px; left:50%; transform:translateX(-50%); z-index:999;
    padding:10px 24px; background:rgba(26,26,46,.95); color:#00B894;
    border:1px solid rgba(0,184,148,.3); border-radius:10px;
    font-size:.82rem; pointer-events:none; animation:toastIn .3s ease;
}
@keyframes toastIn { from { opacity:0; transform:translateX(-50%) translateY(10px); } to { opacity:1; transform:translateX(-50%) translateY(0); } }

@media (max-width:768px) {
    .v3d-overlay { left:10px; right:10px; width:auto; bottom:80px; max-height:55vh; }
    .v3d-toolbar { bottom:12px; }
    .v3d-toolbar button { width:36px; height:36px; font-size:13px; }
}

<?php if ($embed): ?>
.v3d-toolbar { bottom:8px; padding:4px 8px; }
.v3d-toolbar button { width:32px; height:32px; font-size:12px; }
<?php endif; ?>
</style>
</head>
<body>

<!-- Loading Screen -->
<div class="v3d-loading" id="loadingScreen">
    <div class="v3d-spinner"></div>
    <div class="v3d-loading-text" id="loadingText">กำลังโหลดโมเดล 3D...</div>
    <div class="v3d-loading-bar"><div class="v3d-loading-fill" id="loadingFill"></div></div>
</div>

<!-- 3D Canvas -->
<div id="viewer-canvas"></div>

<!-- Info Overlay -->
<div class="v3d-overlay" id="infoOverlay">
<div class="v3d-overlay-inner">
    <h3><i class="fas fa-cube" style="color:#6C5CE7"></i> <span id="overlayTitle"><?= $title ?></span></h3>
    <div class="v3d-section" id="sectionModel" style="display:none">
        <h4><i class="fas fa-chart-bar"></i> สถิติโมเดล</h4>
        <div class="v3d-row"><span class="label">Meshes</span><span class="value" id="dMeshes">—</span></div>
        <div class="v3d-row"><span class="label">Vertices</span><span class="value" id="dVertices">—</span></div>
        <div class="v3d-row"><span class="label">Triangles</span><span class="value" id="dTriangles">—</span></div>
        <div class="v3d-row"><span class="label">Materials</span><span class="value" id="dMaterials">—</span></div>
        <div class="v3d-row"><span class="label">Textures</span><span class="value" id="dTextures">—</span></div>
        <div class="v3d-row"><span class="label">Animations</span><span class="value" id="dAnims">—</span></div>
    </div>
    <div class="v3d-section" id="sectionFile" style="display:none">
        <h4><i class="fas fa-file"></i> ข้อมูลไฟล์</h4>
        <div class="v3d-row"><span class="label">ขนาด</span><span class="value" id="dSize">—</span></div>
        <div class="v3d-row"><span class="label">รูปแบบ</span><span class="value" id="dFormat">—</span></div>
    </div>
</div>
</div>

<?php if (!$embed): ?>
<!-- Toolbar -->
<div class="v3d-toolbar" id="toolbar">
    <button onclick="goBack()" title="กลับ"><i class="fas fa-arrow-left"></i></button>
    <div class="sep"></div>
    <button onclick="toggleOverlay()" id="btnInfo" title="ข้อมูลโมเดล"><i class="fas fa-info-circle"></i></button>
    <button onclick="toggleRotate()" id="btnRotate" class="active" title="หมุนอัตโนมัติ"><i class="fas fa-sync-alt"></i></button>
    <button onclick="resetCamera()" title="รีเซ็ตมุมมอง"><i class="fas fa-expand"></i></button>
    <button onclick="toggleWireframe()" id="btnWire" title="Wireframe"><i class="fas fa-border-all"></i></button>
    <div class="sep"></div>
    <button onclick="toggleBg()" id="btnBg" title="สลับพื้นหลัง"><i class="fas fa-palette"></i></button>
    <button onclick="openFullscreen()" title="เต็มจอ"><i class="fas fa-expand-arrows-alt"></i></button>
    <button onclick="copyUrl()" title="คัดลอก URL"><i class="fas fa-link"></i></button>
</div>
<?php else: ?>
<!-- Minimal embed toolbar -->
<div class="v3d-toolbar" id="toolbar">
    <button onclick="toggleRotate()" id="btnRotate" class="active" title="หมุนอัตโนมัติ"><i class="fas fa-sync-alt"></i></button>
    <button onclick="resetCamera()" title="รีเซ็ตมุมมอง"><i class="fas fa-expand"></i></button>
    <button onclick="openFullscreen()" title="เต็มจอ"><i class="fas fa-expand-arrows-alt"></i></button>
</div>
<?php endif; ?>

<script src="/v1/assets/js/3d/three.min.js"></script>
<script src="/v1/assets/js/3d/GLTFLoader.js"></script>
<script>
// Go back function with fallback
function goBack() {
    if (history.length > 1) {
        history.back();
    } else if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
        window.location.href = document.referrer;
    } else {
        // Default fallback - go to models page or home
        window.location.href = '/v1/pages/models3d.php';
    }
}

var modelSrc = '<?= addslashes($src) ?>';
var modelId = <?= $modelId ?>;
var isTransparent = <?= $transparent ? 'true' : 'false' ?>;
var isEmbed = <?= $embed ? 'true' : 'false' ?>;

var scene, camera, renderer, model, mixer, clock;
var autoRotate = true, wireframeMode = false, overlayVisible = false;
var bgIndex = 0;
var BG_COLORS = [0x0f0f1a, 0x1a1a2e, 0x2d2d44, 0xf5f5f5, 0xffffff, 0x000000];

// If no src but have model id, fetch from API
if (!modelSrc && modelId) {
    fetch('/v1/api/models3d.php?action=detail&id=' + modelId)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success && d.data && d.data.file_url) {
                modelSrc = d.data.file_url;
                document.getElementById('overlayTitle').textContent = d.data.label || 'โมเดล 3D';
                init3D();
            } else {
                showLoadError('ไม่พบโมเดล 3D');
            }
        })
        .catch(function() { showLoadError('ไม่สามารถโหลดข้อมูลโมเดลได้'); });
} else if (modelSrc) {
    init3D();
} else {
    showLoadError('ไม่มี URL โมเดล');
}

function showLoadError(msg) {
    var ls = document.getElementById('loadingScreen');
    ls.innerHTML = '<div style="text-align:center"><i class="fas fa-exclamation-triangle" style="font-size:48px;color:#e17055;margin-bottom:16px;display:block"></i><p style="font-size:1rem;color:#e0e0e0;margin-bottom:8px">' + msg + '</p><button onclick="goBack()" style="padding:8px 20px;background:#6C5CE7;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.85rem"><i class="fas fa-arrow-left"></i> กลับ</button></div>';
}

function init3D() {
    var container = document.getElementById('viewer-canvas');
    clock = new THREE.Clock();

    scene = new THREE.Scene();
    if (!isTransparent) {
        scene.background = new THREE.Color(BG_COLORS[0]);
    }

    camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.01, 1000);
    camera.position.set(0, 1.5, 3);

    try {
        var canvas = document.createElement('canvas');
        var testCtx = canvas.getContext('webgl2') || canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        if (!testCtx) throw new Error('WebGL not available');
        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: isTransparent });
    } catch (e) {
        console.warn('WebGL context creation failed:', e.message);
        showLoadError('WebGL ไม่พร้อมใช้งาน — เบราว์เซอร์ไม่รองรับ หรือมี WebGL context มากเกินไป');
        return;
    }
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    if (isTransparent) renderer.setClearColor(0x000000, 0);
    renderer.outputEncoding = THREE.sRGBEncoding;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;
    container.appendChild(renderer.domElement);

    // Lights
    scene.add(new THREE.AmbientLight(0xffffff, 0.6));
    var dir1 = new THREE.DirectionalLight(0xffffff, 0.8);
    dir1.position.set(5, 10, 7);
    scene.add(dir1);
    var dir2 = new THREE.DirectionalLight(0x6C5CE7, 0.3);
    dir2.position.set(-5, 5, -5);
    scene.add(dir2);

    // Grid (hidden in transparent mode)
    var grid = null;
    if (!isTransparent) {
        grid = new THREE.GridHelper(10, 20, 0x2d2d50, 0x1a1a2e);
        scene.add(grid);
    }

    // Manual orbit controls (no external dependency)
    var isDragging = false, prevMouse = { x: 0, y: 0 };
    var spherical = { theta: 0, phi: Math.PI / 4, radius: 3 };

    function updateCamera() {
        camera.position.x = spherical.radius * Math.sin(spherical.phi) * Math.sin(spherical.theta);
        camera.position.y = spherical.radius * Math.cos(spherical.phi);
        camera.position.z = spherical.radius * Math.sin(spherical.phi) * Math.cos(spherical.theta);
        camera.lookAt(0, window._lookAtY !== undefined ? window._lookAtY : 0.5, 0);
    }
    updateCamera();

    renderer.domElement.addEventListener('mousedown', function(e) { isDragging = true; prevMouse = { x: e.clientX, y: e.clientY }; autoRotate = false; updRotBtn(); });
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

    // Touch support
    var touchStart = null, lastPinchDist = 0;
    renderer.domElement.addEventListener('touchstart', function(e) {
        if (e.touches.length === 1) {
            touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
            autoRotate = false; updRotBtn();
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
    var loadingFill = document.getElementById('loadingFill');

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

        // Reposition grid to model bottom
        var worldBox = new THREE.Box3().setFromObject(model);
        if (grid) grid.position.y = worldBox.min.y;
        window._lookAtY = (worldBox.min.y + worldBox.max.y) / 2;
        updateCamera();

        // Animations
        if (gltf.animations && gltf.animations.length) {
            mixer = new THREE.AnimationMixer(model);
            gltf.animations.forEach(function(clip) { mixer.clipAction(clip).play(); });
        }

        // Collect stats
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
                        ['map','normalMap','roughnessMap','metalnessMap'].forEach(function(t) { if (m[t]) texCount++; });
                    });
                }
            }
        });

        document.getElementById('sectionModel').style.display = 'block';
        document.getElementById('dMeshes').textContent = meshCount.toLocaleString();
        document.getElementById('dVertices').textContent = vertexCount.toLocaleString();
        document.getElementById('dTriangles').textContent = Math.floor(triCount).toLocaleString();
        document.getElementById('dMaterials').textContent = matSet.size;
        document.getElementById('dTextures').textContent = texCount;
        document.getElementById('dAnims').textContent = gltf.animations ? gltf.animations.length : 0;

        // File info
        var ext = modelSrc.split('.').pop().split('?')[0].toUpperCase();
        document.getElementById('sectionFile').style.display = 'block';
        document.getElementById('dFormat').textContent = ext;

        // Hide loading
        var ls = document.getElementById('loadingScreen');
        ls.classList.add('hidden');
        setTimeout(function() { ls.style.display = 'none'; }, 600);

    }, function(xhr) {
        // Progress
        if (xhr.lengthComputable && loadingFill) {
            var pct = Math.round(xhr.loaded / xhr.total * 100);
            loadingFill.style.width = pct + '%';
            document.getElementById('loadingText').textContent = 'กำลังโหลด... ' + pct + '%';
        }
    }, function(err) {
        console.error('Model load error:', err);
        showLoadError('ไม่สามารถโหลดโมเดลได้');
    });

    window._spherical = spherical;
    window._updateCamera = updateCamera;
    window._grid = grid;

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

// ═══ Controls ═══
function toggleRotate() { autoRotate = !autoRotate; updRotBtn(); }
function updRotBtn() { var b = document.getElementById('btnRotate'); if (b) b.classList.toggle('active', autoRotate); }

function resetCamera() {
    if (!window._spherical) return;
    window._spherical.theta = 0;
    window._spherical.phi = Math.PI / 4;
    window._spherical.radius = 3;
    autoRotate = true;
    updRotBtn();
    window._updateCamera();
}

function toggleWireframe() {
    wireframeMode = !wireframeMode;
    var b = document.getElementById('btnWire'); if (b) b.classList.toggle('active', wireframeMode);
    if (model) model.traverse(function(c) {
        if (c.isMesh && c.material) {
            var mats = Array.isArray(c.material) ? c.material : [c.material];
            mats.forEach(function(m) { m.wireframe = wireframeMode; });
        }
    });
}

function toggleBg() {
    bgIndex = (bgIndex + 1) % BG_COLORS.length;
    if (scene && scene.background) scene.background = new THREE.Color(BG_COLORS[bgIndex]);
    if (window._grid) {
        var dark = BG_COLORS[bgIndex] > 0x888888;
        window._grid.material[0].color.setHex(dark ? 0xcccccc : 0x2d2d50);
        window._grid.material[1].color.setHex(dark ? 0xdddddd : 0x1a1a2e);
    }
    var b = document.getElementById('btnBg'); if (b) b.classList.toggle('active', bgIndex > 0);
}

function toggleOverlay() {
    overlayVisible = !overlayVisible;
    document.getElementById('infoOverlay').classList.toggle('visible', overlayVisible);
    var b = document.getElementById('btnInfo'); if (b) b.classList.toggle('active', overlayVisible);
}

function openFullscreen() {
    var el = document.documentElement;
    if (el.requestFullscreen) el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
}

function copyUrl() {
    navigator.clipboard.writeText(window.location.href).then(function() {
        showToast('คัดลอก URL แล้ว');
    });
}

function showToast(msg) {
    var t = document.createElement('div');
    t.className = 'v3d-toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 2500);
}
</script>
</body>
</html>
