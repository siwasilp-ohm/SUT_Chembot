/**
 * VRX 3D Model Viewer - Business Logic (ES Module)
 * Handles Three.js scene, model loading, interaction, device orientation
 * Optimized: pixel ratio cap, dirty-flag rendering, tone mapping, memory management
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
const mouseMoveSpeed = 0.6;
let needsRender = true;  // dirty flag — only render when scene changes
let animFrameId = null;

function initThree(canvasId, modelUrl, _canvasWidth, _canvasHeight) {
  canvasWidth = _canvasWidth;
  canvasHeight = _canvasHeight;

  const canvas_webgl = document.getElementById(canvasId);
  initScene(canvas_webgl);
  loadModel(modelUrl);
}

function initScene(canvas_webgl) {
  lon = -90;
  lat = 0;

  // Init Perspective Camera
  camera = new THREE.PerspectiveCamera(75, canvasWidth / canvasHeight, 0.1, 2000);
  camera.position.set(0, 3, 6);

  scene = new THREE.Scene();

  // Improved lighting for realistic rendering
  scene.add(new THREE.AmbientLight(0xffffff, 0.6));
  const directionalLight = new THREE.DirectionalLight(0xffffff, 1.0);
  directionalLight.position.set(5, 10, 7.5);
  directionalLight.castShadow = false;
  scene.add(directionalLight);
  // Fill light from opposite side
  const fillLight = new THREE.DirectionalLight(0xaaccff, 0.3);
  fillLight.position.set(-5, 3, -5);
  scene.add(fillLight);
  // Hemisphere light for environment-like lighting
  const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 0.4);
  hemiLight.position.set(0, 20, 0);
  scene.add(hemiLight);

  // Init renderer — cap pixel ratio at 2 to prevent GPU overload on high-DPI
  renderer = new THREE.WebGLRenderer({
    canvas: canvas_webgl,
    antialias: true,
    alpha: true,
    powerPreference: 'high-performance',
  });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(canvasWidth, canvasHeight);

  // Tone mapping for HDR/PBR models
  if (THREE.ACESFilmicToneMapping !== undefined) {
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;
  }

  // sRGB encoding (compatible with both old and new Three.js)
  if (renderer.outputColorSpace !== undefined) {
    renderer.outputColorSpace = THREE.SRGBColorSpace || 'srgb';
  } else {
    renderer.outputEncoding = THREE.sRGBEncoding;
  }

  // Handle WebGL context loss gracefully
  canvas_webgl.addEventListener('webglcontextlost', function (e) {
    e.preventDefault();
    console.warn('WebGL context lost — pausing render');
    if (animFrameId) cancelAnimationFrame(animFrameId);
  });
  canvas_webgl.addEventListener('webglcontextrestored', function () {
    console.log('WebGL context restored — resuming render');
    animate();
  });

  animate();
}

function loadModel(modelUrl) {
  const loader = new THREE.GLTFLoader();

  loader.load(
    modelUrl,
    function (gltf) {
      console.log('loadModel', 'success');
      const model = gltf.scene;
      mainModel = model;
      autoFitModel(model);
      scene.add(model);
      needsRender = true;
    },
    null,
    function (error) {
      console.error('loadModel error:', error);
    }
  );
}

function updateModel(modelUrl) {
  const loader = new THREE.GLTFLoader();

  loader.load(
    modelUrl,
    function (gltf) {
      console.log('updateModel', 'success');
      const model = gltf.scene;
      // Remove and dispose old model
      if (mainModel) {
        scene.remove(mainModel);
        disposeObject(mainModel);
      }
      mainModel = model;
      autoFitModel(model);
      scene.add(model);
      needsRender = true;
    },
    null,
    function (error) {
      console.error('updateModel error:', error);
    }
  );
}

/**
 * Auto-fit model: normalize scale and center in viewport
 */
function autoFitModel(model) {
  const box = new THREE.Box3().setFromObject(model);
  const size = box.getSize(new THREE.Vector3());
  const maxDim = Math.max(size.x, size.y, size.z);
  if (maxDim > 0) {
    const scale = 4.0 / maxDim;
    model.scale.setScalar(scale);
  }
  // Re-center after scaling
  const boxScaled = new THREE.Box3().setFromObject(model);
  const center = boxScaled.getCenter(new THREE.Vector3());
  model.position.sub(center);
  // Place on ground
  const boxFinal = new THREE.Box3().setFromObject(model);
  model.position.y -= boxFinal.min.y;
}

/**
 * Dispose a Three.js object tree (geometry + materials + textures)
 */
function disposeObject(obj) {
  if (!obj) return;
  obj.traverse(function (child) {
    if (child.geometry) child.geometry.dispose();
    if (child.material) {
      const mats = Array.isArray(child.material) ? child.material : [child.material];
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

function animate() {
  animFrameId = requestAnimationFrame(animate);

  let dirty = false;

  // Manual rotation mode
  if (lon !== last_lon || lat !== last_lat) {
    last_lon = lon;
    last_lat = lat;
    deviceOrientationControl.modelRotationControl(mainModel, lon, lat, gradient, THREE);
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

  // Only render when something changed (saves GPU/battery)
  if (dirty || needsRender) {
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
  const touch = event.touches[0];
  if (!touch) return;

  const moveX = touch.pageX - touchX;
  const moveY = touch.pageY - touchY;
  lon += moveX;
  lat += moveY;
  touchX = touch.pageX;
  touchY = touch.pageY;
  gradient = Math.abs(moveX / moveY);
  needsRender = true;
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

  const moveX = event.pageX - touchX;
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
  if (mainModel) disposeObject(mainModel);
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

export {
  initThree,
  onTouchstart,
  onTouchmove,
  startDeviceMotion,
  stopDeviceMotion,
  updateModel,
  onMousedown,
  onMousemove,
  onMouseup,
  destroy,
};