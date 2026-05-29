/**
 * VRX QR Scanner — Vue Controller
 * Handles: Camera scanning, result display, role-based actions, history
 */
import * as scanBiz from '../utils/scannerBusiness.js';

new Vue({
  el: '#app',
  data: {
    isScanning: false,
    flashOn: false,
    scanError: '',
    lastResult: null,
    scanHistory: [],
    session: { loggedIn: false, user: null, permissions: {} },
    toastMsg: '',
  },

  mounted: function () {
    var self = this;

    // Load session
    scanBiz.fetchSession().then(function (s) {
      self.session = s;
    });

    // Load history
    self.scanHistory = scanBiz.loadScanHistory();

    // Replace Feather icon placeholders
    self.$nextTick(function () {
      if (typeof feather !== 'undefined') feather.replace();
    });
  },

  updated: function () {
    // Re-render Feather icons after Vue updates DOM
    this.$nextTick(function () {
      if (typeof feather !== 'undefined') feather.replace();
    });
  },

  beforeDestroy: function () {
    scanBiz.stopScanner();
  },

  methods: {

    /* ── Feather Icon Helper ── */
    featherIcon: function (name, size) {
      size = size || 18;
      if (typeof feather !== 'undefined' && feather.icons[name]) {
        return feather.icons[name].toSvg({ width: size, height: size, 'stroke-width': 2 });
      }
      return '<i data-feather="' + name + '"></i>';
    },

    getTypeIcon: function (type) {
      return scanBiz.getTypeFeatherIcon(type);
    },

    /* ── Scanner Controls ── */

    startScan: function () {
      var self = this;
      self.scanError = '';
      self.lastResult = null;
      self.isScanning = true;

      self.$nextTick(function () {
        var video = document.getElementById('scanner-video');
        var canvas = document.getElementById('scanner-canvas');

        scanBiz.startScanner(video, canvas, function (decodedText) {
          self.onQrDetected(decodedText);
        }).then(function (ok) {
          if (!ok) {
            self.isScanning = false;
            self.scanError = 'ไม่สามารถเข้าถึงกล้องได้ กรุณาอนุญาตการใช้กล้อง หรือใช้ HTTPS';
          }
        });
      });
    },

    stopScan: function () {
      scanBiz.stopScanner();
      this.isScanning = false;
      this.flashOn = false;
    },

    toggleFlash: function () {
      var self = this;
      self.flashOn = !self.flashOn;
      scanBiz.toggleTorch(self.flashOn).then(function (ok) {
        if (!ok) {
          self.flashOn = false;
          self.showToast('อุปกรณ์ไม่รองรับ Flash');
        }
      });
    },

    scanAgain: function () {
      this.lastResult = null;
      this.startScan();
    },

    dismissResult: function () {
      this.lastResult = null;
    },

    /* ── QR Detection ── */

    onQrDetected: function (text) {
      var self = this;

      // Stop scanning
      scanBiz.stopScanner();
      self.isScanning = false;
      self.flashOn = false;

      // Play beep
      try {
        var beep = document.getElementById('scan-beep');
        if (beep) { beep.currentTime = 0; beep.play().catch(function(){}); }
      } catch (e) {}

      // Vibrate
      if (navigator.vibrate) navigator.vibrate(100);

      // Parse the URL
      var parsed = scanBiz.parseVrxUrl(text);

      self.lastResult = {
        url: text,
        originalUrl: text,
        type: parsed.type,
        name: parsed.name || '',
        desc: parsed.desc || '',
        src: parsed.src || '',
        isVrx: parsed.isVrx,
        typeLabel: scanBiz.getTypeLabel(parsed.type),
        typeColor: scanBiz.getTypeColor(parsed.type),
      };

      // Add to history
      scanBiz.addToScanHistory({
        url: text,
        type: parsed.type,
        name: parsed.name || parsed.src || text.slice(0, 60),
        isVrx: parsed.isVrx,
      });
      self.scanHistory = scanBiz.loadScanHistory();

      // Log to server (async, non-blocking)
      scanBiz.logScanToServer({
        url: text,
        type: parsed.type,
        isVrx: parsed.isVrx,
      });
    },

    /* ── Scan from Image File ── */

    scanFromFile: function (event) {
      var self = this;
      var file = event.target.files && event.target.files[0];
      if (!file) return;

      var img = new Image();
      var reader = new FileReader();

      reader.onload = function (e) {
        img.onload = function () {
          var canvas = document.createElement('canvas');
          canvas.width = img.width;
          canvas.height = img.height;
          var ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0);
          var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

          if (typeof jsQR !== 'undefined') {
            var code = jsQR(imageData.data, canvas.width, canvas.height);
            if (code && code.data) {
              self.onQrDetected(code.data);
            } else {
              self.showToast('❌ ไม่พบ QR Code ในรูปภาพ');
            }
          } else {
            self.showToast('❌ jsQR library not loaded');
          }
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);

      // Reset input so same file can be re-selected
      event.target.value = '';
    },

    /* ── Result Actions ── */

    openContent: function () {
      if (!this.lastResult) return;
      window.open(this.lastResult.originalUrl, '_blank');
    },

    openAr: function () {
      if (!this.lastResult) return;
      var url = this.lastResult.originalUrl;
      // If it's already an AR URL, open directly
      if (this.lastResult.type === 'ar') {
        window.open(url, '_blank');
      } else if (this.lastResult.src) {
        // Build AR URL from source
        var arBase = window.location.origin + '/vrx/package_ar/viewer/ar.html';
        var params = new URLSearchParams();
        params.set('src', this.lastResult.src);
        if (this.lastResult.name) params.set('name', this.lastResult.name);
        window.open(arBase + '?' + params.toString(), '_blank');
      }
    },

    openQrGenerator: function () {
      if (!this.lastResult) return;
      var src = this.lastResult.src || '';
      var name = this.lastResult.name || '';
      var qrUrl = '../../package_ar/qr/qr.html?src=' + encodeURIComponent(src) + '&name=' + encodeURIComponent(name);
      window.open(qrUrl, '_blank');
    },

    openManager: function () {
      // Open upload page to manage files
      window.open('../../package_upload/upload/upload.html', '_blank');
    },

    openAnalytics: function () {
      // Open gallery with stats view
      window.open('../../package_gallery/gallery/gallery.html', '_blank');
    },

    openExternal: function () {
      if (!this.lastResult) return;
      var url = this.lastResult.originalUrl;
      // Safety check for external URLs
      if (url.match(/^https?:\/\//i)) {
        window.open(url, '_blank', 'noopener,noreferrer');
      } else {
        this.copyUrl();
      }
    },

    copyUrl: function () {
      var self = this;
      if (!self.lastResult) return;
      var text = self.lastResult.originalUrl;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          self.showToast('คัดลอกแล้ว');
        });
      } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        self.showToast('คัดลอกแล้ว');
      }
    },

    shareUrl: function () {
      var self = this;
      if (!self.lastResult) return;
      if (navigator.share) {
        navigator.share({
          title: self.lastResult.name || 'VRX QR Scan',
          url: self.lastResult.originalUrl,
        }).catch(function () {});
      } else {
        self.copyUrl();
      }
    },

    downloadQr: function () {
      // Re-generate QR code and download
      var self = this;
      if (!self.lastResult || typeof QRCode === 'undefined') {
        self.showToast('QR download not available');
        return;
      }
      var tempEl = document.createElement('div');
      tempEl.style.position = 'fixed';
      tempEl.style.left = '-9999px';
      document.body.appendChild(tempEl);

      new QRCode(tempEl, {
        text: self.lastResult.originalUrl,
        width: 512,
        height: 512,
        colorDark: '#1a1a2e',
        colorLight: '#ffffff',
        correctLevel: 2, // QRCode.CorrectLevel.M
      });

      setTimeout(function () {
        var canvas = tempEl.querySelector('canvas');
        if (canvas) {
          var link = document.createElement('a');
          link.download = (self.lastResult.name || 'vrx-qr-scan') + '.png';
          link.href = canvas.toDataURL('image/png');
          link.click();
          self.showToast('ดาวน์โหลดแล้ว');
        }
        document.body.removeChild(tempEl);
      }, 300);
    },

    /* ── History ── */

    openHistoryItem: function (item) {
      var parsed = scanBiz.parseVrxUrl(item.url);
      this.lastResult = {
        url: item.url,
        originalUrl: item.url,
        type: parsed.type,
        name: parsed.name || item.name || '',
        desc: parsed.desc || '',
        src: parsed.src || '',
        isVrx: parsed.isVrx,
        typeLabel: scanBiz.getTypeLabel(parsed.type),
        typeColor: scanBiz.getTypeColor(parsed.type),
      };
    },

    deleteHistoryItem: function (id, event) {
      event.stopPropagation();
      this.scanHistory = scanBiz.removeFromScanHistory(id);
      this.showToast('ลบแล้ว');
    },

    clearHistory: function () {
      scanBiz.clearScanHistory();
      this.scanHistory = [];
      this.showToast('ล้างประวัติแล้ว');
    },

    /* ── Helpers ── */

    getTypeLabel: function (type) { return scanBiz.getTypeLabel(type); },
    getTypeColor: function (type) { return scanBiz.getTypeColor(type); },

    showToast: function (msg) {
      var self = this;
      self.toastMsg = msg;
      setTimeout(function () { self.toastMsg = ''; }, 2500);
    },
  },
});
