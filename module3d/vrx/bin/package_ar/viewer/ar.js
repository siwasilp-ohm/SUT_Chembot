/**
 * VRX AR Viewer — Vue Controller
 * Handles: multi-device AR experience, scale sync, gyro, camera fallback
 */
import * as arBiz from '../utils/arBusiness.js';

new Vue({
  el: '#app',
  data: {
    loading: true,
    loadingText: 'กำลังโหลด AR Viewer...',
    fatalError: null,
    hasCamera: false,
    notice: '',
    noticeError: false,
    modelName: '',
    modelDesc: '',
    modelScalePercent: 100,
    gyroActive: false,
    modelSrc: '',
  },

  mounted: function () {
    var self = this;
    self.parseParams();

    // Replace Feather icon placeholders
    self.$nextTick(function () {
      if (typeof feather !== 'undefined') feather.replace();
    });

    if (!self.modelSrc) {
      self.fatalError = {
        title: 'ไม่พบโมเดล',
        desc: 'ไม่ได้ระบุ URL ของโมเดล 3D กรุณาเปิดผ่าน QR Code หรือหน้า QR Generator',
      };
      self.loading = false;
      return;
    }

    self.initViewer();
  },

  beforeDestroy: function () {
    arBiz.stopCamera();
    arBiz.stopDeviceMotion();
  },

  methods: {
    parseParams: function () {
      var params = new URLSearchParams(window.location.search);
      this.modelSrc = params.get('src') || '';
      this.modelName = params.get('name') || '';
      this.modelDesc = params.get('desc') || '';
    },

    initViewer: function () {
      var self = this;
      var w = window.innerWidth;
      var h = window.innerHeight;

      self.loadingText = 'กำลังเปิดกล้อง...';

      // Step 1: init scene
      arBiz.initScene('ar-canvas', w, h);

      // Step 2: start camera (never rejects)
      arBiz.startCamera('ar-camera', w, h)
        .then(function (result) {
          self.hasCamera = result.hasCamera;

          if (!result.hasCamera) {
            // Show notice based on reason
            var msg = '';
            switch (result.reason) {
              case 'denied':
                msg = 'ไม่ได้รับอนุญาตใช้กล้อง — แสดงโหมด 3D';
                break;
              case 'not_found':
                msg = 'ไม่พบกล้องในอุปกรณ์ — แสดงโหมด 3D';
                break;
              case 'not_https':
              case 'api_unavailable':
                msg = 'กล้องต้องใช้ HTTPS — แสดงโหมด 3D';
                break;
              case 'in_use':
                msg = 'กล้องถูกใช้งานอยู่ — แสดงโหมด 3D';
                break;
              default:
                msg = 'ไม่สามารถเปิดกล้องได้ — แสดงโหมด 3D';
            }
            self.showNotice(msg, false);
          }

          // Step 3: load model
          self.loadingText = 'กำลังโหลดโมเดล 3D...';
          return arBiz.loadModel(self.modelSrc);
        })
        .then(function () {
          self.loading = false;
          self.bindExtraEvents();
        })
        .catch(function (err) {
          console.error('AR init error:', err);
          self.loading = false;
          self.fatalError = {
            title: 'โหลดโมเดลไม่สำเร็จ',
            desc: 'ไม่สามารถโหลดไฟล์ 3D ได้ ตรวจสอบ URL หรือรูปแบบไฟล์ (.glb / .gltf)',
          };
        });
    },

    bindExtraEvents: function () {
      var self = this;

      // Mouse wheel for PC/Notebook zoom
      var canvas = document.getElementById('ar-canvas');
      if (canvas) {
        canvas.addEventListener('wheel', function (e) {
          arBiz.onWheel(e);
          self.syncScale();
        }, { passive: false });
      }

      // Cleanup on page exit
      window.addEventListener('beforeunload', function () {
        arBiz.stopCamera();
      });

      // HTTPS notice check
      if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        if (self.hasCamera === false) {
          // Already shown camera notice
        } else {
          self.showNotice('แนะนำใช้ HTTPS เพื่อประสบการณ์ AR ที่ดีที่สุด', false);
        }
      }
    },

    syncScale: function () {
      this.modelScalePercent = Math.round(arBiz.getModelScale() * 100);
    },

    // ---- Touch Events (delegated to arBiz) ----
    onTouchstart: function (e) {
      arBiz.onTouchstart(e);
    },
    onTouchmove: function (e) {
      arBiz.onTouchmove(e);
      // After pinch, sync scale
      if (e.touches && e.touches.length === 2) {
        this.syncScale();
      }
    },
    onTouchend: function (e) {
      arBiz.onTouchend(e);
    },

    // ---- Mouse Events ----
    onMousedown: function (e) { arBiz.onMousedown(e); },
    onMousemove: function (e) { arBiz.onMousemove(e); },
    onMouseup: function (e) { arBiz.onMouseup(e); },

    // ---- Scale Slider ----
    onScaleInput: function (e) {
      var val = parseInt(e.target.value, 10);
      this.modelScalePercent = val;
      arBiz.setModelScale(val / 100);
    },

    // ---- Gyro Toggle ----
    toggleGyro: function () {
      this.gyroActive = !this.gyroActive;
      if (this.gyroActive) {
        arBiz.startDeviceMotion();
        this.showNotice('Gyroscope เปิดใช้งาน', false);
      } else {
        arBiz.stopDeviceMotion();
        this.showNotice('Gyroscope ปิดใช้งาน', false);
      }
    },

    // ---- Reset ----
    resetView: function () {
      arBiz.resetModel();
      this.modelScalePercent = 100;
      this.showNotice('รีเซ็ตมุมมองแล้ว', false);
    },

    // ---- Fullscreen ----
    toggleFullscreen: function () {
      var doc = document.documentElement;
      if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        if (doc.requestFullscreen) doc.requestFullscreen();
        else if (doc.webkitRequestFullscreen) doc.webkitRequestFullscreen();
      } else {
        if (document.exitFullscreen) document.exitFullscreen();
        else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
      }
    },

    // ---- Nav ----
    goBack: function () {
      arBiz.stopCamera();
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = '../../index.html';
      }
    },

    // ---- Notice helper ----
    showNotice: function (msg, isError) {
      var self = this;
      self.notice = msg;
      self.noticeError = !!isError;
      setTimeout(function () {
        self.notice = '';
      }, 4000);
    },
  }
});
