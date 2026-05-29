/**
 * Device Orientation Control Module (ES Module)
 * Handles device orientation for both 3D model rotation and panorama camera control
 * Optimized: cached vector allocations, screen orientation awareness
 */
const alphaOffset = 0;
let phi = 0, theta = 0;
const sphereRadius = 500;

// Screen orientation — updates on device rotate
let screenOrientation = 0;
if (typeof window !== 'undefined') {
  function updateOrientation() {
    screenOrientation = window.screen && window.screen.orientation
      ? window.screen.orientation.angle || 0
      : window.orientation || 0;
  }
  updateOrientation();
  window.addEventListener('orientationchange', updateOrientation);
  if (window.screen && window.screen.orientation) {
    window.screen.orientation.addEventListener('change', updateOrientation);
  }
}

// Helper for Three.js version compatibility
function degToRad(THREE, deg) {
  return THREE.MathUtils ? THREE.MathUtils.degToRad(deg) : THREE.Math.degToRad(deg);
}

/**
 * For package_3d_viewer & AR — rotate the 3D model on BOTH axes simultaneously
 */
function modelRotationControl(model, lon, lat, gradient, THREE) {
  if (!model) return;
  model.rotation.y = lon * 0.01;
  model.rotation.x = lat * 0.01;
}

// Cached target vector for panorama (avoids GC every frame)
let _lookTarget = null;

// For package_panorama — rotate the camera to look at a point on the sphere
function camaraRotationControl(camera, lon, lat, THREE) {
  if (!camera) return;

  lat = Math.max(-85, Math.min(85, lat));
  phi = degToRad(THREE, 90 - lat);
  theta = degToRad(THREE, lon);

  if (!_lookTarget) _lookTarget = new THREE.Vector3();
  _lookTarget.x = sphereRadius * Math.sin(phi) * Math.cos(theta);
  _lookTarget.y = sphereRadius * Math.cos(phi);
  _lookTarget.z = sphereRadius * Math.sin(phi) * Math.sin(theta);

  camera.lookAt(_lookTarget);
}

// Cached quaternion helpers (created once, reused every frame)
let _quaternionHelper = null;

function getSetObjectQuaternion(THREE) {
  if (_quaternionHelper) return _quaternionHelper;

  const zee = new THREE.Vector3(0, 0, 1);
  const euler = new THREE.Euler();
  const q0 = new THREE.Quaternion();
  const q1 = new THREE.Quaternion(-Math.sqrt(0.5), 0, 0, Math.sqrt(0.5)); // -PI/2 around x-axis

  _quaternionHelper = function (quaternion, alpha, beta, gamma, orient) {
    euler.set(beta, alpha, -gamma, 'YXZ');
    quaternion.setFromEuler(euler);
    quaternion.multiply(q1);
    quaternion.multiply(q0.setFromAxisAngle(zee, -orient));
  };

  return _quaternionHelper;
}

// Device orientation control — set object quaternion from device sensors
function deviceControl(model, device, THREE) {
  if (!model || !device) return;

  const alpha = device.alpha ? degToRad(THREE, device.alpha) + alphaOffset : 0;
  const beta = device.beta ? degToRad(THREE, device.beta) : 0;
  const gamma = device.gamma ? degToRad(THREE, device.gamma) : 0;
  const orient = screenOrientation ? degToRad(THREE, screenOrientation) : 0;

  getSetObjectQuaternion(THREE)(model.quaternion, alpha, beta, gamma, orient);
}

export {
  deviceControl,
  camaraRotationControl,
  modelRotationControl,
};