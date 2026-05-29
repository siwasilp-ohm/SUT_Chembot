/**
 * VRX 3D Preview - Lightweight Business Logic (ES Module)
 * Self-contained mini Three.js renderer for inline model previews.
 * Supports: auto-rotate, mouse/touch orbit, fit-to-bounds.
 */

let _instances = {};

/**
 * Create a preview renderer inside a container element.
 * Returns an instance id that can be used to destroy/update later.
 *
 * @param {string} containerId - DOM element id to mount canvas into
 * @param {string} modelUrl    - URL or blob URL of a GLB/GLTF model
 * @param {object} [opts]      - { width, height, autoRotate, bgColor }
 * @returns {string} instanceId
 */
function createPreview(containerId, modelUrl, opts) {
  const container = document.getElementById(containerId);
  if (!container) { console.error('Preview container not found:', containerId); return null; }

  const o = Object.assign({
    width: container.clientWidth || 320,
    height: container.clientHeight || 240,
    autoRotate: true,
    bgColor: 0x1a1a2e,
  }, opts || {});

  // Clean up any existing preview in this container
  destroyPreview(containerId);

  // ---- Scene ----
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(o.bgColor);

  // ---- Camera ----
  const camera = new THREE.PerspectiveCamera(50, o.width / o.height, 0.1, 1000);
  camera.position.set(0, 1.5, 4);

  // ---- Lights ----
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
  scene.add(ambientLight);
  const dirLight = new THREE.DirectionalLight(0xffffff, 0.9);
  dirLight.position.set(5, 10, 7);
  scene.add(dirLight);
  const fillLight = new THREE.DirectionalLight(0xaaccff, 0.4);
  fillLight.position.set(-5, 3, -5);
  scene.add(fillLight);

  // ---- Grid helper ----
  const grid = new THREE.GridHelper(10, 10, 0x444466, 0x333355);
  scene.add(grid);

  // ---- Renderer ----
  const canvas = document.createElement('canvas');
  canvas.style.display = 'block';
  canvas.style.width = '100%';
  canvas.style.height = '100%';
  canvas.style.borderRadius = '8px';
  container.innerHTML = '';
  container.appendChild(canvas);

  const renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: false });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(o.width, o.height);
  if (renderer.outputColorSpace !== undefined) {
    renderer.outputColorSpace = THREE.SRGBColorSpace || 'srgb';
  } else if (renderer.outputEncoding !== undefined) {
    renderer.outputEncoding = THREE.sRGBEncoding;
  }

  // ---- State ----
  let model = null;
  let animId = null;
  let rotY = 0;
  let isDragging = false;
  let prevX = 0, prevY = 0;
  let orbitLon = 0, orbitLat = 0;
  const orbitSpeed = 0.4;
  let loadingDone = false;
  let loadError = false;
  let _stopped = false;
  let needsRender = true;

  // ---- Orbit (mouse + touch) ----
  function onPointerDown(e) {
    isDragging = true;
    const pt = e.touches ? e.touches[0] : e;
    prevX = pt.clientX;
    prevY = pt.clientY;
  }
  function onPointerMove(e) {
    if (!isDragging) return;
    const pt = e.touches ? e.touches[0] : e;
    const dx = pt.clientX - prevX;
    const dy = pt.clientY - prevY;
    orbitLon += dx * orbitSpeed;
    orbitLat += dy * orbitSpeed;
    orbitLat = Math.max(-60, Math.min(60, orbitLat));
    prevX = pt.clientX;
    prevY = pt.clientY;
    needsRender = true;
  }
  function onPointerUp() { isDragging = false; }

  canvas.addEventListener('mousedown', onPointerDown);
  canvas.addEventListener('mousemove', onPointerMove);
  canvas.addEventListener('mouseup', onPointerUp);
  canvas.addEventListener('mouseleave', onPointerUp);
  canvas.addEventListener('touchstart', onPointerDown, { passive: true });
  canvas.addEventListener('touchmove', onPointerMove, { passive: true });
  canvas.addEventListener('touchend', onPointerUp);

  // ---- Animate (dirty-flag optimization) ----
  function animate() {
    if (_stopped) return;
    animId = requestAnimationFrame(animate);

    if (model) {
      if (o.autoRotate && !isDragging) {
        rotY += 0.005;
        needsRender = true;
      }
      const totalRotY = rotY + (orbitLon * Math.PI / 180);
      const totalRotX = orbitLat * Math.PI / 180;
      if (model.rotation.y !== totalRotY || model.rotation.x !== totalRotX) {
        model.rotation.y = totalRotY;
        model.rotation.x = totalRotX;
        needsRender = true;
      }
    }

    if (needsRender) {
      renderer.render(scene, camera);
      needsRender = false;
    }
  }
  animate();

  // ---- Load model ----
  const loader = new THREE.GLTFLoader();
  loader.load(
    modelUrl,
    function (gltf) {
      model = gltf.scene;
      // Auto-fit: center & scale to fit view
      const box = new THREE.Box3().setFromObject(model);
      const center = box.getCenter(new THREE.Vector3());
      const size = box.getSize(new THREE.Vector3());
      const maxDim = Math.max(size.x, size.y, size.z);
      const scale = 2.0 / (maxDim || 1);
      model.scale.setScalar(scale);
      // Re-center after scale
      const boxScaled = new THREE.Box3().setFromObject(model);
      const centerScaled = boxScaled.getCenter(new THREE.Vector3());
      model.position.sub(centerScaled);
      model.position.y += (boxScaled.getSize(new THREE.Vector3()).y) / 2;

      scene.add(model);
      loadingDone = true;

      // Dispatch event
      container.dispatchEvent(new CustomEvent('preview-loaded', { detail: { success: true } }));
    },
    function (progress) {
      // Optional progress callback
      if (progress.lengthComputable) {
        const pct = Math.round((progress.loaded / progress.total) * 100);
        container.dispatchEvent(new CustomEvent('preview-progress', { detail: { percent: pct } }));
      }
    },
    function (error) {
      console.error('Preview load error:', error);
      loadError = true;
      container.dispatchEvent(new CustomEvent('preview-loaded', { detail: { success: false, error: error } }));
    }
  );

  // ---- Store instance ----
  const instance = {
    id: containerId,
    renderer: renderer,
    scene: scene,
    camera: camera,
    canvas: canvas,
    animId: animId,
    model: null,
    getModel: function () { return model; },
    isLoaded: function () { return loadingDone; },
    hasError: function () { return loadError; },
    stopAnimate: function () { _stopped = true; if (animId) cancelAnimationFrame(animId); },
    resize: function (w, h) {
      camera.aspect = w / h;
      camera.updateProjectionMatrix();
      renderer.setSize(w, h);
      needsRender = true;
    },
  };

  _instances[containerId] = instance;
  return containerId;
}

/**
 * Destroy a preview and free all GPU resources
 */
function destroyPreview(containerId) {
  const inst = _instances[containerId];
  if (!inst) return;

  inst.stopAnimate();

  // Dispose all objects in the scene
  if (inst.scene) {
    inst.scene.traverse(function (child) {
      if (child.geometry) child.geometry.dispose();
      if (child.material) {
        var mats = Array.isArray(child.material) ? child.material : [child.material];
        mats.forEach(function (mat) {
          if (mat.map) mat.map.dispose();
          if (mat.normalMap) mat.normalMap.dispose();
          if (mat.roughnessMap) mat.roughnessMap.dispose();
          if (mat.metalnessMap) mat.metalnessMap.dispose();
          mat.dispose();
        });
      }
    });
  }

  inst.renderer.dispose();

  // Remove canvas from DOM
  const container = document.getElementById(containerId);
  if (container) container.innerHTML = '';

  delete _instances[containerId];
}

/**
 * Check if a filename is a previewable 3D model
 */
function isPreviewable(filename) {
  if (!filename) return false;
  const ext = filename.split('.').pop().toLowerCase();
  return ['glb', 'gltf'].includes(ext);
}

/**
 * Destroy all active previews
 */
function destroyAll() {
  Object.keys(_instances).forEach(function (id) {
    destroyPreview(id);
  });
}

export { createPreview, destroyPreview, isPreviewable, destroyAll };
