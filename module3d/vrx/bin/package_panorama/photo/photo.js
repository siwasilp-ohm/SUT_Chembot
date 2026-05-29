/**
 * VRX Panorama Viewer - Vue Controller (ES Module)
 * Supports: touch, mouse, pinch-to-zoom, wheel zoom, device orientation, URL params
 */
import * as photoBusiness from '../utils/photoBusiness.js';

const canvasId = 'canvasWebGL';
// Default panorama image
const defaultImageUrl = '/vrx/assets/sample.jpg';

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
    showInfo: !!urlParams.meta,
    notice: '',
    fileMeta: urlParams.meta || {
      name: 'sample.jpg',
      description: 'Default demo panorama',
      size: '—',
      date: '—',
      category: 'panorama',
      extension: 'jpg',
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
      photoBusiness.startDeviceMotion();
      this.isDeviceMotion = true;
    },
    stopDeviceMotion: function () {
      photoBusiness.stopDeviceMotion();
      this.isDeviceMotion = false;
    },
    load: function () {
      var _that = this;
      const imageUrl = urlParams.src || defaultImageUrl;

      // Init Three.js scene
      photoBusiness.initThree(canvasId, imageUrl, window.innerWidth, window.innerHeight);

      // Touch events
      var canvasWebGL = document.getElementById(canvasId);
      canvasWebGL.addEventListener('touchstart', function (event) {
        _that.stopDeviceMotion();
        photoBusiness.onTouchstart(event);
      });
      canvasWebGL.addEventListener('touchmove', function (event) {
        photoBusiness.onTouchmove(event);
      });
      canvasWebGL.addEventListener('touchend', function (event) {
        photoBusiness.onTouchend();
      });

      // Mouse events
      canvasWebGL.addEventListener('mousedown', function (event) {
        _that.stopDeviceMotion();
        photoBusiness.onMousedown(event);
      });
      canvasWebGL.addEventListener('mousemove', function (event) {
        photoBusiness.onMousemove(event);
      });
      canvasWebGL.addEventListener('mouseup', function (event) {
        photoBusiness.onMouseup(event);
      });

      // Mouse wheel zoom (scoped to canvas)
      canvasWebGL.addEventListener('wheel', function (event) {
        photoBusiness.onWheel(event);
      }, { passive: false });
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
  photoBusiness.updatePanorama(src, -90);

  // Update metadata in Vue
  app.fileMeta = {
    name: file.name,
    description: 'Uploaded locally',
    size: (file.size / 1024).toFixed(1) + ' KB',
    date: new Date().toLocaleString('th-TH'),
    category: 'panorama',
    extension: file.name.split('.').pop().toLowerCase(),
  };
  app.showInfo = true;
});