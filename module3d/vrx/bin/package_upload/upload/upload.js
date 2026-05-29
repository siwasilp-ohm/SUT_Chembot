/**
 * VRX Upload Page - Vue Controller (ES Module)
 * Now syncs to server-side database for cross-device access
 */
import * as uploadBiz from '../utils/uploadBusiness.js';
import * as previewBiz from '../utils/previewBusiness.js';

// Blob URL map (runtime only, not persisted)
const blobUrlMap = new Map();

var app = new Vue({
  el: '#app',
  data: function () {
    return {
      activeTab: 'upload',
      uploading: false,
      isDragging: false,
      importUrl: '',
      importName: '',
      importDesc: '',
      showMetaModal: false,
      pendingMeta: {},
      pendingFile: null,
      pendingBlobUrl: null,
      filterCategory: 'all',
      searchQuery: '',
      files: [],
      filteredFiles: [],
      toasts: [],

      // 3D Preview — modal (file upload)
      previewSupported: false,
      previewLoading: false,
      previewError: false,

      // 3D Preview — URL import
      urlPreviewSupported: false,
      urlPreviewLoading: false,
      urlPreviewError: false,
      urlPreviewActive: false,

      // Embed iframe
      embedCode: '',
      embedName: '',
      embedDesc: '',
      embedParsed: null,
    };
  },
  mounted: function () {
    this.refreshFiles();
    // Replace Feather icon placeholders with SVGs
    this.$nextTick(function () {
      if (typeof feather !== 'undefined') feather.replace();
    });
  },
  updated: function () {
    // Re-render Feather icons after Vue re-renders DOM
    this.$nextTick(function () {
      if (typeof feather !== 'undefined') feather.replace();
    });
  },
  watch: {
    importUrl: function (val) {
      this.urlPreviewSupported = previewBiz.isPreviewable(val || '');
      // Reset preview state when URL changes
      if (this.urlPreviewActive) {
        previewBiz.destroyPreview('urlPreviewContainer');
        this.urlPreviewActive = false;
        this.urlPreviewLoading = false;
        this.urlPreviewError = false;
      }
    },
    embedCode: function (val) {
      this.embedParsed = this.parseIframe(val);
      // Auto-fill name from title if not already set
      if (this.embedParsed && this.embedParsed.title && !this.embedName) {
        this.embedName = this.embedParsed.title;
      }
    },
  },
  methods: {
    // ---- Feather Icon Helper ----
    featherIcon(name, size = 18) {
      if (typeof feather !== 'undefined' && feather.icons[name]) {
        return feather.icons[name].toSvg({ width: size, height: size, 'stroke-width': 2 });
      }
      return `<i data-feather="${name}"></i>`;
    },

    // ---- File Selection ----
    onFileSelect: function (e) {
      const files = e.target.files;
      if (!files || files.length === 0) return;
      this.processFile(files[0]);
      // Reset input
      e.target.value = '';
    },
    onDrop: function (e) {
      this.isDragging = false;
      const files = e.dataTransfer.files;
      if (!files || files.length === 0) return;
      this.processFile(files[0]);
    },
    processFile: function (file) {
      const validation = uploadBiz.validateFile(file);
      if (!validation.valid) {
        this.showToast(validation.message, 'error');
        return;
      }
      const category = uploadBiz.categorizeFile(file.name);
      const meta = uploadBiz.createFileMetadata(file, category);
      this.pendingMeta = meta;
      this.pendingFile = file;

      // Check if 3D preview is possible
      this.previewSupported = previewBiz.isPreviewable(file.name);
      this.previewLoading = false;
      this.previewError = false;
      this.showMetaModal = true;

      // Start preview if supported
      if (this.previewSupported) {
        var self = this;
        var blobUrl = URL.createObjectURL(file);
        this.pendingBlobUrl = blobUrl;
        this.previewLoading = true;
        this.$nextTick(function () {
          self.startModalPreview(blobUrl);
        });
      }
    },

    // ---- URL Import ----
    onImportUrl: async function () {
      const validation = uploadBiz.validateUrl(this.importUrl);
      if (!validation.valid) {
        this.showToast(validation.message, 'error');
        return;
      }

      this.uploading = true;

      try {
        // Save URL to server DB
        const serverFile = await uploadBiz.saveUrlToServer(this.importUrl, {
          name: this.importName || undefined,
          description: this.importDesc || undefined,
        });

        blobUrlMap.set(serverFile.id, this.importUrl);

        this.showToast('URL imported successfully!', 'success');
      } catch (e) {
        console.error('Server URL save failed, saving locally', e);
        // Fallback: localStorage
        const meta = uploadBiz.createUrlMetadata(this.importUrl, {
          name: this.importName || undefined,
          description: this.importDesc || undefined,
        });
        meta.blobUrl = this.importUrl;
        uploadBiz.addToLibrary(meta);
        blobUrlMap.set(meta.id, this.importUrl);
        this.showToast('Saved locally (offline)', 'info');
      }

      this.uploading = false;

      // Clean up URL preview
      previewBiz.destroyPreview('urlPreviewContainer');
      this.urlPreviewActive = false;
      this.urlPreviewLoading = false;
      this.urlPreviewError = false;

      this.importUrl = '';
      this.importName = '';
      this.importDesc = '';
      this.refreshFiles();
    },

    // ---- Upload Confirm / Cancel ----
    confirmUpload: async function () {
      const file = this.pendingFile;
      const meta = this.pendingMeta;
      if (!file) return;

      // Destroy preview before closing
      previewBiz.destroyPreview('modalPreviewContainer');

      this.uploading = true;

      try {
        // Upload to server (binary + DB record)
        const serverFile = await uploadBiz.uploadFileToServer(file, {
          name: meta.name,
          description: meta.description,
          category: meta.category,
          tags: meta.tags || [],
        });

        // Keep blob URL for immediate preview in this session
        const blobUrl = this.pendingBlobUrl || uploadBiz.createBlobUrl(file);
        if (blobUrl) {
          blobUrlMap.set(serverFile.id, blobUrl);
          serverFile.blobUrl = blobUrl;
        }

        this.showToast('File uploaded successfully!', 'success');
      } catch (e) {
        console.error('Server upload failed, saving locally', e);
        // Fallback: save to localStorage only
        const thumbnail = await uploadBiz.generateThumbnail(file);
        if (thumbnail) meta.thumbnail = thumbnail;
        const blobUrl = this.pendingBlobUrl || uploadBiz.createBlobUrl(file);
        meta.blobUrl = blobUrl;
        blobUrlMap.set(meta.id, blobUrl);
        uploadBiz.addToLibrary(meta);
        this.showToast('Saved locally (offline). Will sync when connected.', 'info');
      }

      this.uploading = false;
      this.showMetaModal = false;
      this.pendingFile = null;
      this.pendingMeta = {};
      this.pendingBlobUrl = null;
      this.previewSupported = false;
      this.previewLoading = false;
      this.previewError = false;
      this.refreshFiles();
    },
    cancelUpload: function () {
      previewBiz.destroyPreview('modalPreviewContainer');
      if (this.pendingBlobUrl) {
        uploadBiz.revokeBlobUrl(this.pendingBlobUrl);
      }
      this.showMetaModal = false;
      this.pendingFile = null;
      this.pendingMeta = {};
      this.previewSupported = false;
      this.previewLoading = false;
      this.previewError = false;
    },

    // ---- File Operations ----
    deleteFile: async function (file) {
      if (!confirm('Delete "' + file.name + '"?')) return;
      // Revoke blob URL
      const blobUrl = blobUrlMap.get(file.id);
      if (blobUrl) {
        uploadBiz.revokeBlobUrl(blobUrl);
        blobUrlMap.delete(file.id);
      }

      try {
        if (file._server) {
          await uploadBiz.deleteFileOnServer(file.id);
        } else {
          uploadBiz.removeFromLibrary(file.id);
        }
      } catch (e) {
        console.error('Server delete failed', e);
        uploadBiz.removeFromLibrary(file.id);
      }
      this.showToast('File deleted', 'info');
      this.refreshFiles();
    },
    editFile: function (file) {
      this.pendingMeta = { ...file };
      this.pendingFile = null; // No new file

      // Show preview for existing 3D files
      var existingUrl = blobUrlMap.get(file.id) || file.blobUrl || file.url;
      this.previewSupported = previewBiz.isPreviewable(file.originalName || file.name) && !!existingUrl;
      this.previewLoading = false;
      this.previewError = false;
      this.showMetaModal = true;

      if (this.previewSupported) {
        var self = this;
        this.previewLoading = true;
        this.$nextTick(function () {
          self.startModalPreview(existingUrl);
        });
      }

      // Override confirm to update instead of add
      var originalConfirm = this.confirmUpload;
      this.confirmUpload = async function () {
        previewBiz.destroyPreview('modalPreviewContainer');
        const updates = {
          name: this.pendingMeta.name,
          description: this.pendingMeta.description,
        };

        try {
          if (this.pendingMeta._server) {
            await uploadBiz.updateFileOnServer(this.pendingMeta.id, updates);
          } else {
            uploadBiz.updateInLibrary(this.pendingMeta.id, {
              ...updates,
              category: this.pendingMeta.category,
            });
          }
        } catch (e) {
          console.error('Server update failed', e);
          uploadBiz.updateInLibrary(this.pendingMeta.id, {
            ...updates,
            category: this.pendingMeta.category,
          });
        }

        this.showMetaModal = false;
        this.pendingMeta = {};
        this.previewSupported = false;
        this.previewLoading = false;
        this.previewError = false;
        this.showToast('File updated!', 'success');
        this.refreshFiles();
        // Restore original
        this.confirmUpload = originalConfirm;
      }.bind(this);
    },

    // ---- View ----
    openViewer: function (file) {
      const url = blobUrlMap.get(file.id) || file.blobUrl || file.url;
      const metaParam = encodeURIComponent(JSON.stringify({
        name: file.name,
        description: file.description,
        size: file.sizeFormatted,
        date: file.uploadedAtFormatted,
        category: file.category,
        extension: file.extension,
      }));

      if (file.category === 'embed') {
        // Navigate to iframe viewer
        var embedSrc = file.embedSrc || file.url || '';
        var viewerUrl = '../../package_upload/iframe/iframe.html?src=' +
          encodeURIComponent(embedSrc) +
          '&name=' + encodeURIComponent(file.name || '') +
          '&desc=' + encodeURIComponent(file.description || '');
        window.location.href = viewerUrl;
      } else if (file.category === 'model') {
        // Navigate to 3D viewer
        const viewerUrl = '../../package_3d_viewer/camera/camera.html?src=' +
          encodeURIComponent(url || '') + '&meta=' + metaParam;
        window.location.href = viewerUrl;
      } else {
        // Navigate to panorama viewer
        const viewerUrl = '../../package_panorama/photo/photo.html?src=' +
          encodeURIComponent(url || '') + '&meta=' + metaParam;
        window.location.href = viewerUrl;
      }
    },

    // ---- QR / AR ----
    openQR: function (file) {
      const url = blobUrlMap.get(file.id) || file.blobUrl || file.url;
      var qrUrl = '../../package_ar/qr/qr.html?src=' + encodeURIComponent(url || '');
      if (file.name) qrUrl += '&name=' + encodeURIComponent(file.name);
      window.location.href = qrUrl;
    },

    // ---- Embed / iframe ----
    parseIframe: function (code) {
      if (!code || !code.trim()) return null;
      var trimmed = code.trim();

      // Try parsing as HTML iframe tag
      var parser = new DOMParser();
      var doc = parser.parseFromString(trimmed, 'text/html');
      var iframe = doc.querySelector('iframe');

      if (iframe && iframe.getAttribute('src')) {
        return {
          src: iframe.getAttribute('src'),
          title: iframe.getAttribute('title') || '',
          raw: trimmed,
        };
      }

      // Fallback: try treating input as a direct URL
      try {
        var url = new URL(trimmed);
        if (url.protocol === 'http:' || url.protocol === 'https:') {
          return {
            src: trimmed,
            title: '',
            raw: '<iframe src="' + trimmed + '" frameborder="0" allowfullscreen style="width:100%;height:100%;"></iframe>',
          };
        }
      } catch (e) { /* not a valid URL */ }

      return null;
    },

    onImportEmbed: async function () {
      var parsed = this.embedParsed;
      if (!parsed) {
        this.showToast('Invalid iframe code', 'error');
        return;
      }

      this.uploading = true;

      try {
        // Save embed to server DB
        await uploadBiz.saveEmbedToServer(parsed.src, {
          name: this.embedName || parsed.title || 'Embed',
          description: this.embedDesc || '',
          embedCode: parsed.raw,
          embedTitle: parsed.title || '',
        });
        this.showToast('Embed imported successfully!', 'success');
      } catch (e) {
        console.error('Server embed save failed, saving locally', e);
        // Fallback: localStorage
        var meta = uploadBiz.createEmbedMetadata(parsed.src, {
          name: this.embedName || parsed.title || 'Embed',
          description: this.embedDesc || '',
          embedCode: parsed.raw,
          embedTitle: parsed.title || '',
        });
        uploadBiz.addToLibrary(meta);
        this.showToast('Saved locally (offline)', 'info');
      }

      this.uploading = false;
      this.embedCode = '';
      this.embedName = '';
      this.embedDesc = '';
      this.embedParsed = null;
      this.refreshFiles();
    },

    // ---- 3D Preview (Modal — file upload) ----
    startModalPreview: function (blobUrl) {
      var self = this;
      var container = document.getElementById('modalPreviewContainer');
      if (!container) return;

      container.addEventListener('preview-loaded', function handler(e) {
        container.removeEventListener('preview-loaded', handler);
        self.previewLoading = false;
        if (!e.detail.success) {
          self.previewError = true;
        }
      });

      var w = container.clientWidth || 400;
      var h = container.clientHeight || 250;
      previewBiz.createPreview('modalPreviewContainer', blobUrl, {
        width: w,
        height: h,
        autoRotate: true,
      });
    },

    // ---- 3D Preview (URL import) ----
    previewUrl: function () {
      var url = this.importUrl.trim();
      if (!url) return;

      if (!previewBiz.isPreviewable(url)) {
        this.showToast('Preview supports GLB/GLTF files only', 'info');
        return;
      }

      // Destroy previous
      previewBiz.destroyPreview('urlPreviewContainer');
      this.urlPreviewActive = true;
      this.urlPreviewLoading = true;
      this.urlPreviewError = false;

      var self = this;
      this.$nextTick(function () {
        var container = document.getElementById('urlPreviewContainer');
        if (!container) return;

        container.addEventListener('preview-loaded', function handler(e) {
          container.removeEventListener('preview-loaded', handler);
          self.urlPreviewLoading = false;
          if (!e.detail.success) {
            self.urlPreviewError = true;
          }
        });

        var w = container.clientWidth || 400;
        var h = container.clientHeight || 250;
        previewBiz.createPreview('urlPreviewContainer', url, {
          width: w,
          height: h,
          autoRotate: true,
        });
      });
    },

    // ---- Filtering ----
    setFilter: function (category) {
      this.filterCategory = category;
      this.applyFilter();
    },
    onSearch: function () {
      this.applyFilter();
    },
    applyFilter: function () {
      var list = this.files.slice(); // Use already-loaded files
      if (this.filterCategory && this.filterCategory !== 'all') {
        list = list.filter(function (item) {
          return item.category === this.filterCategory;
        }.bind(this));
      }
      if (this.searchQuery) {
        var q = this.searchQuery.toLowerCase();
        list = list.filter(function (item) {
          return item.name.toLowerCase().indexOf(q) !== -1 ||
            (item.description && item.description.toLowerCase().indexOf(q) !== -1);
        });
      }
      this.filteredFiles = list;
    },
    refreshFiles: async function () {
      try {
        const result = await uploadBiz.loadLibraryFromServer({ limit: 500 });
        this.files = result.files;
      } catch (e) {
        console.warn('Server load failed, using localStorage', e);
        this.files = uploadBiz.loadLibrary();
      }
      this.applyFilter();
    },

    // ---- Toast ----
    showToast: function (message, type) {
      var toast = { id: Date.now(), message: message, type: type || 'info' };
      this.toasts.push(toast);
      var self = this;
      setTimeout(function () {
        self.toasts = self.toasts.filter(function (t) { return t.id !== toast.id; });
      }, 3000);
    },
  },
});
