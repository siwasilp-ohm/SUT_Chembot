/**
 * VRX AR Viewer - Business Logic (ES Module)
 * Handles: AR camera feed, 3D model rendering overlay, multi-device interaction
 * Supports: Mobile (touch + gyroscope), Tablet, PC (mouse + wheel)
 * Optimized: dirty-flag render, pixel ratio cap, tone mapping, memory management
 */
import * as deviceOrientationControl from '../../third_party/DeviceOrientationControl.js';

let camera, scene, renderer;
let touchX, touchY, device = {};
let lon = 0, lat = 0, gradient = 1;
let mainModel;
let isDeviceMotion = false;
let last_lon, last_lat, last_device = {};
let isMouseDown = false;
const mouseMoveSpeed = 0.6;
let modelScaleFactor = 1;
let baseModelScale = 1;
let cameraStream = null;
let needsRender = true;
let animFrameId = null;

/**
 * Initialize Three.js scene with transparent background for AR
 */
function initScene(canvasId, width, height) {
  const canvas = document.getElementById(canvasId);

  camera = new THREE.PerspectiveCamera(60, width / height, 0.01, 2000);
  camera.position.set(0, 1.5, 5);

  scene = new THREE.Scene();

  // Lighting setup for realistic AR rendering
  scene.add(new THREE.AmbientLight(0xffffff, 0.8));
  const dirLight = new THREE.DirectionalLight(0xffffff, 1.0);
  dirLight.position.set(5, 10, 7.5);
  scene.add(dirLight);
  const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 0.5);
  hemiLight.position.set(0, 20, 0);
  scene.add(hemiLight);
  const fillLight = new THREE.DirectionalLight(0xaaccff, 0.3);
  fillLight.position.set(-5, 3, -5);
  scene.add(fillLight);

  renderer = new THREE.WebGLRenderer({
    canvas: canvas,
    antialias: true,
    alpha: true,
    powerPreference: 'high-performance',
  });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(width, height);
  renderer.setClearColor(0x000000, 0);

  // Tone mapping for PBR/HDR models
  if (THREE.ACESFilmicToneMapping !== undefined) {
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;
  }

  if (renderer.outputColorSpace !== undefined) {
    renderer.outputColorSpace = THREE.SRGBColorSpace || 'srgb';
  } else if (renderer.outputEncoding !== undefined) {
    renderer.outputEncoding = THREE.sRGBEncoding;
  }

  // Handle WebGL context loss
  canvas.addEventListener('webglcontextlost', function (e) {
    e.preventDefault();
    if (animFrameId) cancelAnimationFrame(animFrameId);
  });
  canvas.addEventListener('webglcontextrestored', function () {
    animate();
  });

  animate();
}

/**
 * Dispose a Three.js object tree (geometry + materials + textures)
 */
function disposeObject(obj) {
  if (!obj) return;
  obj.traverse(function (child) {
    if (child.geometry) child.geometry.dispose();
    if (child.material) {
      var mats = Array.isArray(child.material) ? child.material : [child.material];
      mats.forEach(function (mat) {
        if (mat.map) mat.map.dispose();
        if (mat.normalMap) mat.normalMap.dispose();
        if (mat.roughnessMap) mat.roughnessMap.dispose();
        if (mat.metalnessMap) mat.metalnessMap.dispose();
        if (mat.emissiveMap) mat.emissiveMap.dispose();
        if (mat.aoMap) mat.aoMap.dispose();
        mat.dispose();
      });
    }
  });
}

/**
 * Load a 3D model with auto-fit (center + scale to view)
 */
function loadModel(modelUrl) {
  return new Promise(function (resolve, reject) {
    const loader = new THREE.GLTFLoader();
    loader.load(
      modelUrl,
      function (gltf) {
        const model = gltf.scene;
        if (mainModel) {
          scene.remove(mainModel);
          disposeObject(mainModel);
        }
        mainModel = model;

        // Auto-fit: normalize to view
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z);
        baseModelScale = 2.5 / (maxDim || 1);
        model.scale.setScalar(baseModelScale * modelScaleFactor);

        // Re-center
        const boxScaled = new THREE.Box3().setFromObject(model);
        const centerScaled = boxScaled.getCenter(new THREE.Vector3());
        model.position.sub(centerScaled);
        // Place on ground
        const boxFinal = new THREE.Box3().setFromObject(model);
        model.position.y -= boxFinal.min.y;

        scene.add(model);
        needsRender = true;
        resolve(model);
      },
      null,
      function (error) {
        console.error('AR loadModel error:', error);
        reject(error);
      }
    );
  });
}

/** Set model scale (1 = 100%) */
function setModelScale(scale) {
  modelScaleFactor = scale;
  if (mainModel) {
    const s = baseModelScale * scale;
    mainModel.scale.set(s, s, s);
    needsRender = true;
  }
}

/** Get current scale */
function getModelScale() {
  return modelScaleFactor;
}

/** Reset model position and rotation */
function resetModel() {
  lon = 0;
  lat = 0;
  modelScaleFactor = 1;
  if (mainModel) {
    mainModel.rotation.set(0, 0, 0);
    mainModel.scale.setScalar(baseModelScale);
    mainModel.position.set(0, 0, 0);
    const boxAfter = new THREE.Box3().setFromObject(mainModel);
    mainModel.position.y -= boxAfter.min.y;
    needsRender = true;
  }
}

/**
 * Start camera feed for AR overlay
 * Gracefully handles: no camera, permission denied, HTTP (non-HTTPS)
 */
function startCamera(videoElementId, width, height) {
  return new Promise(function (resolve) {
    const video = document.getElementById(videoElementId);

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      console.warn('Camera API not available');
      resolve({ stream: null, hasCamera: false, reason: 'api_unavailable' });
      return;
    }

    navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: width }, height: { ideal: height }, facingMode: 'environment' }
    })
    .then(function (stream) {
      cameraStream = stream;
      video.srcObject = stream;
      video.setAttribute('playsinline', '');
      video.play().catch(function () {});
      resolve({ stream: stream, hasCamera: true, reason: '' });
    })
    .catch(function (err) {
      console.warn('Camera unavailable:', err.message);
      var reason = 'unknown';
      if (err.name === 'NotAllowedError') reason = 'denied';
      else if (err.name === 'NotFoundError') reason = 'not_found';
      else if (err.name === 'NotReadableError') reason = 'in_use';
      else if (err.message && err.message.indexOf('secure') !== -1) reason = 'not_https';
      resolve({ stream: null, hasCamera: false, reason: reason });
    });
  });
}

function stopCamera() {
  if (cameraStream) {
    cameraStream.getTracks().forEach(function (t) { t.stop(); });
    cameraStream = null;
  }
}

function animate() {
  animFrameId = requestAnimationFrame(animate);

  let dirty = false;

  if (mainModel && (lon !== last_lon || lat !== last_lat)) {
    last_lon = lon;
    last_lat = lat;
    deviceOrientationControl.modelRotationControl(mainModel, lon, lat, gradient, THREE);
    dirty = true;
  }
  if (last_device.alpha !== device.alpha || last_device.beta !== device.beta || last_device.gamma !== device.gamma) {
    last_device.alpha = device.alpha;
    last_device.beta = device.beta;
    last_device.gamma = device.gamma;
    if (isDeviceMotion && camera) {
      deviceOrientationControl.deviceControl(camera, device, THREE);
      dirty = true;
    }
  }

  if ((dirty || needsRender) && renderer && scene && camera) {
    renderer.render(scene, camera);
    needsRender = false;
  }
}

// ---- Touch Events ----
function onTouchstart(event) {
  event.preventDefault();
  const touch = event.touches[0];
  if (!touch) return;
  touchX = touch.pageX;
  touchY = touch.pageY;
}

function onTouchmove(event) {
  event.preventDefault();
  if (event.touches.length === 2) { handlePinch(event); return; }
  const touch = event.touches[0];
  if (!touch) return;
  const moveX = touch.pageX - touchX;
  const moveY = touch.pageY - touchY;
  lon += moveX;
  lat += moveY;
  touchX = touch.pageX;
  touchY = touch.pageY;
  needsRender = true;
}

let lastPinchDist = 0;
function handlePinch(event) {
  const dx = event.touches[0].clientX - event.touches[1].clientX;
  const dy = event.touches[0].clientY - event.touches[1].clientY;
  const dist = Math.sqrt(dx * dx + dy * dy);
  if (lastPinchDist > 0) {
    const delta = (dist - lastPinchDist) * 0.005;
    modelScaleFactor = Math.max(0.1, Math.min(3.0, modelScaleFactor + delta));
    setModelScale(modelScaleFactor);
  }
  lastPinchDist = dist;
}

function onTouchend() { lastPinchDist = 0; }

// ---- Mouse Events ----
function onMousedown(event) {
  if (event.button !== 0) return;
  isMouseDown = true;
  touchX = event.pageX;
  touchY = event.pageY;
}
function onMousemove(event) {
  if (!isMouseDown) return;
  const moveX = event.pageX - touchX;
  const moveY = event.pageY - touchY;
  lon += moveX * mouseMoveSpeed;
  lat += moveY * mouseMoveSpeed;
  touchX = event.pageX;
  touchY = event.pageY;
  needsRender = true;
}
function onMouseup(event) {
  if (event.button !== 0) return;
  isMouseDown = false;
}

// ---- Mouse Wheel Zoom (PC/Notebook) ----
function onWheel(event) {
  event.preventDefault();
  const delta = event.deltaY > 0 ? -0.05 : 0.05;
  modelScaleFactor = Math.max(0.1, Math.min(3.0, modelScaleFactor + delta));
  setModelScale(modelScaleFactor);
  return modelScaleFactor;
}

// ---- Device Orientation ----
function deviceorientation_callback(event) {
  device = event;
  needsRender = true;
}

function startDeviceMotion() {
  if (window.DeviceOrientationEvent && window.DeviceOrientationEvent.requestPermission) {
    window.DeviceOrientationEvent.requestPermission()
      .then(function (state) {
        if (state === 'granted') {
          isDeviceMotion = true;
          window.addEventListener('deviceorientation', deviceorientation_callback, true);
        }
      })
      .catch(function (err) { console.error('DeviceOrientation permission error:', err); });
  } else {
    isDeviceMotion = true;
    window.addEventListener('deviceorientation', deviceorientation_callback, true);
  }
}

function stopDeviceMotion() {
  isDeviceMotion = false;
  window.removeEventListener('deviceorientation', deviceorientation_callback);
}

/**
 * Destroy the AR viewer — free all GPU resources
 */
function destroy() {
  if (animFrameId) cancelAnimationFrame(animFrameId);
  stopDeviceMotion();
  stopCamera();
  if (mainModel) disposeObject(mainModel);
  if (renderer) renderer.dispose();
  mainModel = null;
  scene = null;
  camera = null;
  renderer = null;
}

// Resize handler
window.addEventListener('resize', function () {
  if (!camera || !renderer) return;
  const w = window.innerWidth;
  const h = window.innerHeight;
  camera.aspect = w / h;
  camera.updateProjectionMatrix();
  renderer.setSize(w, h);
  needsRender = true;
});

export {
  initScene, loadModel, setModelScale, getModelScale, resetModel,
  startCamera, stopCamera,
  onTouchstart, onTouchmove, onTouchend,
  onMousedown, onMousemove, onMouseup, onWheel,
  startDeviceMotion, stopDeviceMotion,
  destroy,
};
