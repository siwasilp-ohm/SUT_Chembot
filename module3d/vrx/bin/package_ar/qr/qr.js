/**
 * VRX QR Code Generator — Vue Controller
 * Handles: model selection (library + URL), QR generation, history
 */
import * as qrBiz from '../utils/qrBusiness.js';
import * as uploadBiz from '../../package_upload/utils/uploadBusiness.js';

new Vue({
  el: '#app',
  data: function () {
    return {
      sourceTab: 'library',
      library: [],
      selectedFile: null,
      urlInput: '',
      modelName: '',
      modelDesc: '',
      generated: false,
      arUrl: '',
      history: [],
      toastMsg: '',
    };
  },

  computed: {
    /**
     * Filter by 'model' category (matches uploadBusiness.js categorizeFile)
     */
    modelFiles: function () {
      return this.library.filter(function (f) {
        return f.category === 'model';
      });
    },

    canGenerate: function () {
      if (this.sourceTab === 'library') return !!this.selectedFile;
      return this.urlInput.trim().length > 0;
    },
  },

  mounted: function () {
    var self = this;

    // Load library from server API (with localStorage fallback)
    self.loadLibrary();

    // Load history
    self.history = qrBiz.loadHistory();

    // Replace Feather icon placeholders
    self.$nextTick(function () {
      if (typeof feather !== 'undefined') feather.replace();
    });

    // Check URL params (coming from Upload page "QR" button)
    var params = new URLSearchParams(window.location.search);
    var presetSrc = params.get('src');
    var presetName = params.get('name');
    if (presetSrc) {
      self.sourceTab = 'url';
      self.urlInput = presetSrc;
      if (presetName) self.modelName = presetName;
    }

    // Render mini QR codes in history
    self.$nextTick(function () {
      self.renderHistoryQRs();
    });
  },

  methods: {
    selectFile: function (file) {
      this.selectedFile = file;
      this.modelName = this.modelName || file.name || file.originalName || '';
    },

    generate: function () {
      var self = this;
      var src = '';

      if (self.sourceTab === 'library' && self.selectedFile) {
        // Use the stored URL/path for the model
        src = self.selectedFile.url || self.selectedFile.blobUrl || '';
        if (!self.modelName) {
          self.modelName = self.selectedFile.name || self.selectedFile.originalName || 'AR Model';
        }
      } else if (self.sourceTab === 'url') {
        src = self.urlInput.trim();
      }

      if (!src) {
        self.showToast('กรุณาเลือกโมเดลหรือใส่ URL');
        return;
      }

      // Build AR URL
      self.arUrl = qrBiz.buildArUrl(src, self.modelName, self.modelDesc);
      self.generated = true;

      // Generate QR
      self.$nextTick(function () {
        qrBiz.generateQRCode('qr-code-output', self.arUrl);

        // Save to history
        var entry = qrBiz.addToHistory({
          modelSrc: src,
          modelName: self.modelName || 'AR Model',
          modelDesc: self.modelDesc,
          arUrl: self.arUrl,
        });

        self.history = qrBiz.loadHistory();
        self.$nextTick(function () {
          self.renderHistoryQRs();
        });
      });
    },

    download: function () {
      var fname = (this.modelName || 'vrx-ar').replace(/[^a-zA-Z0-9_\-ก-๙]/g, '_');
      qrBiz.downloadQRCode('qr-code-output', fname);
    },

    copyLink: function () {
      var self = this;
      qrBiz.copyToClipboard(self.arUrl).then(function () {
        self.showToast('คัดลอกลิงก์แล้ว');
      });
    },

    share: function () {
      var self = this;
      qrBiz.shareUrl(self.arUrl, self.modelName || 'VRX AR 3D').then(function () {
        self.showToast('แชร์สำเร็จ');
      }).catch(function () {
        // share cancelled
      });
    },

    openAr: function () {
      if (this.arUrl) {
        window.open(this.arUrl, '_blank');
      }
    },

    loadHistoryItem: function (item) {
      this.sourceTab = 'url';
      this.urlInput = item.modelSrc;
      this.modelName = item.modelName;
      this.modelDesc = item.modelDesc;
      this.arUrl = item.arUrl;
      this.generated = true;

      var self = this;
      self.$nextTick(function () {
        qrBiz.generateQRCode('qr-code-output', self.arUrl);
      });
    },

    deleteHistoryItem: function (id, event) {
      event.stopPropagation();
      this.history = qrBiz.removeFromHistory(id);
      this.showToast('ลบประวัติแล้ว');
    },

    clearAllHistory: function () {
      qrBiz.clearHistory();
      this.history = [];
      this.showToast('ล้างประวัติทั้งหมดแล้ว');
    },

    renderHistoryQRs: function () {
      var self = this;
      self.history.forEach(function (item) {
        var el = document.getElementById('hist-qr-' + item.id);
        if (el && el.childElementCount === 0) {
          qrBiz.generateQRCode('hist-qr-' + item.id, item.arUrl, {
            size: 50,
            colorDark: '#6C5CE7',
          });
        }
      });
    },

    showToast: function (msg) {
      var self = this;
      self.toastMsg = msg;
      setTimeout(function () {
        self.toastMsg = '';
      }, 2500);
    },

    loadLibrary: function () {
      var self = this;
      // Load from server API, fallback to localStorage
      uploadBiz.loadLibraryFromServer({ limit: 500 })
        .then(function (result) {
          self.library = result.files || [];
        })
        .catch(function () {
          self.library = uploadBiz.loadLibrary();
        });
    },
  }
});
