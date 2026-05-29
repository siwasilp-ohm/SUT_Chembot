/**
 * VRX 3D Viewer - Vue Controller (ES Module)
 * Supports: touch, mouse, device orientation, file upload, URL params
 */
import * as cameraBusiness from '../utils/cameraBusiness.js';

const canvasId = 'canvasWebGL';
// Default model
const defaultModelUrl = '/vrx/assets/robot.glb';

// Parse URL parameters for uploaded files
function getUrlParams() {
  const params = new URLSearchParams(window.location.search);
  return {
    src: params.get('src') || null,
    meta: params.get('meta') ? JSON.parse(decodeURIComponent(params.get('meta'))) : null,
  };
}

const urlParams = getUrlParams();

var app = new Vue({
  el: '#app',
  data: {
    isDeviceMotion: false,
    cameraActive: false,
    showInfo: !!urlParams.meta,
    notice: '',
    fileMeta: urlParams.meta || {
      name: 'robot.glb',
      description: 'Default demo model',
      size: '—',
      date: '—',
      category: 'model',
      extension: 'glb',
    },
  },
  methods: {
    toggleDeviceMotion: function () {
      if (this.isDeviceMotion) {
        this.stopDeviceMotion();
      } else {
        this.startDeviceMotion();
      }
    },
    startDeviceMotion: function () {
      cameraBusiness.startDeviceMotion();
      this.isDeviceMotion = true;
    },
    stopDeviceMotion: function () {
      cameraBusiness.stopDeviceMotion();
      this.isDeviceMotion = false;
    },
    takePhoto: async function () {
      if (!navigator.mediaDevices) {
        this.notice = 'Camera not supported in this browser';
        return;
      }
      try {
        const w = window.innerWidth;
        const h = window.innerHeight;
        const stream = await navigator.mediaDevices.getUserMedia({
          video: {
            width: w,
            height: h,
            facingMode: 'environment',
          }
        });
        var inputData = document.getElementById('inputData');
        inputData.srcObject = stream;
        this.cameraActive = true;
      } catch (err) {
        this.notice = 'Camera access denied: ' + err.message;
        console.error('takePhoto error:', err);
      }
    },
    load: function () {
      var _that = this;
      const w = window.innerWidth;
      const h = window.innerHeight;
      const modelUrl = urlParams.src || defaultModelUrl;

      // Init Three.js scene
      cameraBusiness.initThree(canvasId, modelUrl, w, h);

      // Touch events (passive: false to allow preventDefault)
      var canvasWebGL = document.getElementById(canvasId);
      canvasWebGL.addEventListener('touchstart', function (event) {
        _that.stopDeviceMotion();
        cameraBusiness.onTouchstart(event);
      }, { passive: false });
      canvasWebGL.addEventListener('touchmove', function (event) {
        cameraBusiness.onTouchmove(event);
      }, { passive: false });

      // Mouse events
      canvasWebGL.addEventListener('mousedown', function (event) {
        _that.stopDeviceMotion();
        cameraBusiness.onMousedown(event);
      });
      canvasWebGL.addEventListener('mousemove', function (event) {
        cameraBusiness.onMousemove(event);
      });
      canvasWebGL.addEventListener('mouseup', function (event) {
        cameraBusiness.onMouseup(event);
      });
    },

    openQR: function () {
      var src = urlParams.src || defaultModelUrl;
      // Ensure absolute URL for cross-page navigation
      if (src && !src.match(/^(https?:|blob:|\/)/i)) {
        src = '/vrx/' + src.replace(/^\.\.\/\.\.\//, '');
      }
      var name = this.fileMeta.name || '';
      var qrUrl = '../../package_ar/qr/qr.html?src=' + encodeURIComponent(src);
      if (name) qrUrl += '&name=' + encodeURIComponent(name);
      window.location.href = qrUrl;
    },
  },
  mounted: function () {
    this.load();
    this.$nextTick(function () { if (typeof feather !== 'undefined') feather.replace(); });
  },
});

// File upload handler
document.getElementById('uploaderInput').addEventListener('change', function (e) {
  var files = e.target.files;
  if (files.length === 0) return;

  var urlCreator = window.URL || window.webkitURL;
  if (!urlCreator) return;

  var file = files[0];
  var src = urlCreator.createObjectURL(file);
  cameraBusiness.updateModel(src);

  // Update metadata in Vue
  app.fileMeta = {
    name: file.name,
    description: 'Uploaded locally',
    size: (file.size / 1024).toFixed(1) + ' KB',
    date: new Date().toLocaleString('th-TH'),
    category: 'model',
    extension: file.name.split('.').pop().toLowerCase(),
  };
  app.showInfo = true;
});