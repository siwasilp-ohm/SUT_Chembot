/**
 * VRX Panorama Viewer - Business Logic (ES Module)
 * Handles Three.js sphere rendering, panorama loading, interaction, device orientation
 * Supports: touch, mouse, pinch-to-zoom, mouse wheel zoom
 * Optimized: dirty-flag render, pixel ratio cap, memory management, immediate pinch
 */
import * as deviceOrientationControl from '../../third_party/DeviceOrientationControl.js';

let camera, scene, renderer;
let touchX, touchY, device = {};
let lon, lat, gradient;
let mainModel;
let isDeviceMotion = false;
let last_lon, last_lat, last_device = {};
let canvasWidth, canvasHeight;
let isMouseDown = false;
const mouseMoveSpeed = 0.3;
let needsRender = true;
let animFrameId = null;

function initThree(canvasId, imageUrl, _canvasWidth, _canvasHeight) {
  canvasWidth = _canvasWidth;
  canvasHeight = _canvasHeight;

  const canvas_webgl = document.getElementById(canvasId);
  initScene(canvas_webgl);
  loadPanorama(imageUrl);
}

function initScene(canvas_webgl) {
  lon = -90;
  lat = 0;

  // Init Perspective Camera
  camera = new THREE.PerspectiveCamera(75, canvasWidth / canvasHeight, 1, 1000);
  scene = new THREE.Scene();
  scene.add(new THREE.AmbientLight(0xffffff));

  // Init renderer — cap pixel ratio at 2
  renderer = new THREE.WebGLRenderer({
    canvas: canvas_webgl,
    antialias: true,
    alpha: true,
    powerPreference: 'high-performance',
  });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(canvasWidth, canvasHeight);

  // Handle WebGL context loss
  canvas_webgl.addEventListener('webglcontextlost', function (e) {
    e.preventDefault();
    if (animFrameId) cancelAnimationFrame(animFrameId);
  });
  canvas_webgl.addEventListener('webglcontextrestored', function () {
    animate();
  });

  animate();
}

function loadPanorama(imageUrl) {
  // Sphere geometry — use 60 segments for quality/performance balance
  const geometry = new THREE.SphereGeometry(500, 60, 30);
  geometry.scale(-1, 1, 1); // Invert for inside view

  const texture = new THREE.TextureLoader().load(imageUrl, function () {
    needsRender = true;
  });
  const material = new THREE.MeshBasicMaterial({ map: texture });
  const model = new THREE.Mesh(geometry, material);

  // Use MathUtils for compatibility
  const degToRad = THREE.MathUtils ? THREE.MathUtils.degToRad : THREE.Math.degToRad;
  model.rotation.set(0, degToRad(-90), 0);

  mainModel = model;
  scene.add(model);
  needsRender = true;
}

function updatePanorama(imageUrl, deg) {
  // Dispose old texture
  if (mainModel && mainModel.material && mainModel.material.map) {
    mainModel.material.map.dispose();
  }

  const texture = new THREE.TextureLoader().load(imageUrl, function () {
    needsRender = true;
  });
  mainModel.material.map = texture;

  const degToRad = THREE.MathUtils ? THREE.MathUtils.degToRad : THREE.Math.degToRad;
  mainModel.rotation.set(0, degToRad(deg), 0);

  camera.fov = 75;
  camera.updateProjectionMatrix();
  needsRender = true;
}

function animate() {
  animFrameId = requestAnimationFrame(animate);

  let dirty = false;

  // Manual rotation mode
  if (lon !== last_lon || lat !== last_lat) {
    last_lon = lon;
    last_lat = lat;
    deviceOrientationControl.camaraRotationControl(camera, lon, lat, THREE);
    dirty = true;
  }

  // Device orientation auto mode
  if (last_device.alpha !== device.alpha ||
    last_device.beta !== device.beta ||
    last_device.gamma !== device.gamma) {
    last_device.alpha = device.alpha;
    last_device.beta = device.beta;
    last_device.gamma = device.gamma;

    if (isDeviceMotion) {
      deviceOrientationControl.deviceControl(camera, device, THREE);
      dirty = true;
    }
  }

  // Only render when something changed
  if (dirty || needsRender) {
    renderer.render(scene, camera);
    needsRender = false;
  }
}

// ---- Touch Events (with pinch-to-zoom — now immediate, no setTimeout delay) ----
let lastPinchDist = 0;

function onTouchstart(event) {
  if (event.touches.length === 2) {
    const dx = event.touches[0].pageX - event.touches[1].pageX;
    const dy = event.touches[0].pageY - event.touches[1].pageY;
    lastPinchDist = Math.sqrt(dx * dx + dy * dy);
  } else {
    const touch = event.touches[0];
    if (!touch) return;
    touchX = touch.pageX;
    touchY = touch.pageY;
  }
}

function onTouchmove(event) {
  if (event.touches.length === 2) {
    const dx = event.touches[0].pageX - event.touches[1].pageX;
    const dy = event.touches[0].pageY - event.touches[1].pageY;
    const dist = Math.sqrt(dx * dx + dy * dy);

    if (lastPinchDist > 0) {
      const scale = dist / lastPinchDist;
      const clamp = THREE.MathUtils ? THREE.MathUtils.clamp : THREE.Math.clamp;
      // scale > 1 = zoom in (fov down), scale < 1 = zoom out (fov up)
      camera.fov = clamp(camera.fov / scale, 30, 110);
      camera.updateProjectionMatrix();
      needsRender = true;
    }
    lastPinchDist = dist;
  } else {
    const touch = event.touches[0];
    if (!touch) return;

    const moveX = touchX - touch.pageX;
    const moveY = touch.pageY - touchY;
    lon += moveX;
    lat += moveY;
    touchX = touch.pageX;
    touchY = touch.pageY;
    gradient = Math.abs(moveX / moveY);
    needsRender = true;
  }
}

function onTouchend() {
  lastPinchDist = 0;
}

// ---- Mouse Events ----
function onMousedown(event) {
  if (event.button !== 0) return;
  isMouseDown = true;
  touchX = event.pageX;
  touchY = event.pageY;
}

function onMousemove(event) {
  if (!isMouseDown) return;

  const moveX = touchX - event.pageX;
  const moveY = event.pageY - touchY;
  lon += moveX * mouseMoveSpeed;
  lat += moveY * mouseMoveSpeed;
  touchX = event.pageX;
  touchY = event.pageY;
  gradient = Math.abs(moveX / moveY);
  needsRender = true;
}

function onMouseup(event) {
  if (event.button !== 0) return;
  isMouseDown = false;
}

// ---- Device Orientation ----
function deviceorientation_callback(event) {
  device = event;
  needsRender = true;
}

function startDeviceMotion() {
  if (window.DeviceOrientationEvent && window.DeviceOrientationEvent.requestPermission) {
    // iOS requires explicit permission
    window.DeviceOrientationEvent.requestPermission()
      .then(function (state) {
        if (state === 'granted') {
          isDeviceMotion = true;
          window.addEventListener('deviceorientation', deviceorientation_callback, true);
        } else {
          console.log('startDeviceMotion', 'permission not granted');
        }
      })
      .catch(function (err) {
        console.error('DeviceOrientation permission error:', err);
      });
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
 * Destroy the viewer — free all GPU resources
 */
function destroy() {
  if (animFrameId) cancelAnimationFrame(animFrameId);
  stopDeviceMotion();
  if (mainModel) {
    if (mainModel.geometry) mainModel.geometry.dispose();
    if (mainModel.material) {
      if (mainModel.material.map) mainModel.material.map.dispose();
      mainModel.material.dispose();
    }
  }
  if (renderer) renderer.dispose();
  mainModel = null;
  scene = null;
  camera = null;
  renderer = null;
}

// ---- Resize Handler ----
window.addEventListener('resize', function () {
  if (!camera || !renderer) return;
  const w = window.innerWidth;
  const h = window.innerHeight;
  camera.aspect = w / h;
  camera.updateProjectionMatrix();
  renderer.setSize(w, h);
  needsRender = true;
});

// ---- Mouse Wheel Zoom (scoped to canvas, not global document) ----
function onWheel(event) {
  if (!camera) return;
  event.preventDefault();
  const clamp = THREE.MathUtils ? THREE.MathUtils.clamp : THREE.Math.clamp;
  const fov = camera.fov + event.deltaY * 0.05;
  camera.fov = clamp(fov, 30, 110);
  camera.updateProjectionMatrix();
  needsRender = true;
}

export {
  initThree,
  onTouchstart,
  onTouchmove,
  onTouchend,
  startDeviceMotion,
  stopDeviceMotion,
  updatePanorama,
  onMousedown,
  onMousemove,
  onMouseup,
  onWheel,
  destroy,
};