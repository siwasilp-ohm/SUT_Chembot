/**
 * VRX Gallery — Vue Controller (ES Module)
 * Multi-view file library with search, filter, sort, pagination, detail panel
 */

import {
  loadLibrary,
  removeFromLibrary,
  computeStats,
  countByCategory,
  sortFiles,
  filterAndSearch,
  paginate,
  pageRange,
  relativeTime,
  getCategoryMeta,
  renderCategoryIcon,
  getViewerUrl,
  getQRUrl,
  canShowThumbnail,
  getThumbnailSrc,
  canShowModelPreview,
  trackView,
  // Server API
  loadLibraryFromServer,
  deleteFileOnServer,
  getStatsFromServer,
} from '../utils/galleryBusiness.js';

import { createPreview, destroyPreview, destroyAll, isPreviewable } from '../../package_upload/utils/previewBusiness.js';

/* ── Per-page default ── */
const PER_PAGE = 24;

/* ── Saved prefs from localStorage ── */
function loadPrefs() {
  try {
    return JSON.parse(localStorage.getItem('vrx_gallery_prefs') || '{}');
  } catch { return {}; }
}

function savePrefs(prefs) {
  try {
    localStorage.setItem('vrx_gallery_prefs', JSON.stringify(prefs));
  } catch { /* ignore */ }
}

/* ── Vue App ── */
const app = new Vue({
  el: '#galleryApp',

  data() {
    const prefs = loadPrefs();
    return {
      /* Source */
      allFiles: [],

      /* Filters */
      searchQuery: '',
      filterCategory: prefs.filterCategory || 'all',
      sortKey: prefs.sortKey || 'newest',

      /* View */
      viewMode: prefs.viewMode || 'grid',  // grid | large | masonry | list

      /* Pagination */
      currentPage: 1,
      perPage: PER_PAGE,

      /* Detail */
      detailFile: null,

      /* Stats */
      stats: { total: 0, models: 0, panoramas: 0, images: 0, embeds: 0, totalSize: 0, totalSizeFormatted: '0 B' },
      categoryCounts: { all: 0, model: 0, panorama: 0, image: 0, embed: 0 },

      /* Debounce */
      _searchTimer: null,

      /* 3D preview tracking */
      _activeDetailPreview: null,
    };
  },

  computed: {
    /* Filtered + sorted list (not paginated) */
    processedFiles() {
      let files = filterAndSearch(this.allFiles, this.filterCategory, this.searchQuery);
      files = sortFiles(files, this.sortKey);
      return files;
    },

    /* Paginated slice */
    pagedFiles() {
      const result = paginate(this.processedFiles, this.currentPage, this.perPage);
      return result.data;
    },

    totalPages() {
      return Math.max(1, Math.ceil(this.processedFiles.length / this.perPage));
    },

    pages() {
      return pageRange(this.currentPage, this.totalPages);
    },
  },

  watch: {
    filterCategory() {
      this.currentPage = 1;
      this.persistPrefs();
    },
    sortKey() {
      this.currentPage = 1;
      this.persistPrefs();
    },
    viewMode() {
      this.persistPrefs();
    },
    detailFile(newVal, oldVal) {
      // Clean up 3D preview when detail closes
      if (!newVal && this._activeDetailPreview) {
        destroyPreview(this._activeDetailPreview);
        this._activeDetailPreview = null;
      }
      // Load 3D preview when detail opens with a model
      if (newVal && newVal.category === 'model') {
        this.$nextTick(() => this.loadDetailModelPreview(newVal));
      }
    },
  },

  created() {
    this.refresh();
  },

  mounted() {
    this.$nextTick(() => { if (typeof feather !== 'undefined') feather.replace(); });
  },

  updated() {
    this.$nextTick(() => { if (typeof feather !== 'undefined') feather.replace(); });
  },

  beforeDestroy() {
    destroyAll();
  },

  methods: {
    /* ── Data ── */
    async refresh() {
      // Load from server API (with localStorage fallback)
      try {
        const result = await loadLibraryFromServer({ limit: 500 });
        this.allFiles = result.files;
      } catch (e) {
        console.warn('Gallery: server load failed, using localStorage', e);
        this.allFiles = loadLibrary();
      }
      this.stats = computeStats(this.allFiles);
      this.categoryCounts = countByCategory(this.allFiles);
    },

    /* ── Filter / Search ── */
    setFilter(cat) {
      this.filterCategory = cat;
    },

    onSearchChange() {
      clearTimeout(this._searchTimer);
      this._searchTimer = setTimeout(() => {
        this.currentPage = 1;
      }, 200);
    },

    /* ── View mode ── */
    setView(mode) {
      this.viewMode = mode;
    },

    /* ── Sorting (handled by computed) ── */
    applyChanges() {
      this.currentPage = 1;
    },

    /* ── Pagination ── */
    goPage(p) {
      if (p < 1 || p > this.totalPages) return;
      this.currentPage = p;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    /* ── Category helpers ── */
    getCategoryIcon(cat) {
      return getCategoryMeta(cat).icon;
    },

    getCategoryIconHtml(cat, size) {
      return renderCategoryIcon(cat, size);
    },

    getCategoryLabel(cat) {
      return getCategoryMeta(cat).label;
    },

    getBadgeClass(cat) {
      return getCategoryMeta(cat).cls;
    },

    /* ── Thumbnail helpers ── */
    canShowThumb(file) {
      return canShowThumbnail(file);
    },

    getThumbSrc(file) {
      return getThumbnailSrc(file);
    },

    /* ── Relative time ── */
    getRelTime(ts) {
      return relativeTime(ts);
    },

    /* ── Actions ── */
    openViewer(file) {
      trackView(file.id);
      const url = getViewerUrl(file);
      if (url && url !== '#') {
        window.open(url, '_blank');
      }
    },

    openQR(file) {
      const url = getQRUrl(file);
      if (url && url !== '#') {
        window.open(url, '_blank');
      }
    },

    async deleteFile(file) {
      if (!confirm('Delete "' + file.name + '"? This cannot be undone.')) return;
      try {
        if (file._server) {
          await deleteFileOnServer(file.id);
        } else {
          removeFromLibrary(file.id);
        }
      } catch (e) {
        console.error('Delete failed', e);
        // Fallback: try local removal
        removeFromLibrary(file.id);
      }
      this.refresh();
    },

    /* ── Detail Panel ── */
    openDetail(file) {
      this.detailFile = file;
      document.body.style.overflow = 'hidden';
    },

    closeDetail() {
      this.detailFile = null;
      document.body.style.overflow = '';
    },

    /* ── 3D Preview in detail panel ── */
    loadDetailModelPreview(file) {
      const containerId = 'detail-preview-3d';
      const container = document.getElementById(containerId);
      if (!container) return;

      const src = file.url || file.blobUrl;
      if (!src) return;

      // Clean previous
      if (this._activeDetailPreview) {
        destroyPreview(this._activeDetailPreview);
        this._activeDetailPreview = null;
      }

      try {
        this._activeDetailPreview = createPreview(containerId, src, {
          width: container.clientWidth || 400,
          height: container.clientHeight || 300,
          autoRotate: true,
        });
      } catch (e) {
        console.warn('Gallery: 3D preview failed', e);
      }
    },

    /* ── Persist user preferences ── */
    persistPrefs() {
      savePrefs({
        viewMode: this.viewMode,
        sortKey: this.sortKey,
        filterCategory: this.filterCategory,
      });
    },
  },
});
