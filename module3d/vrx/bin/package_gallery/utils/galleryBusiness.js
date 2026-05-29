/**
 * VRX Gallery — Business Logic (ES Module)
 * Advanced gallery data management, sorting, statistics
 * Now supports server-side data via REST API for cross-device access
 */

import {
  loadLibrary,
  saveLibrary,
  removeFromLibrary,
  updateInLibrary,
  formatFileSize,
  formatDate,
  FILE_CATEGORIES,
  // Server API
  loadLibraryFromServer,
  deleteFileOnServer,
  updateFileOnServer,
  getStatsFromServer,
  trackViewOnServer,
} from '../../package_upload/utils/uploadBusiness.js';

/* ── Re-export for convenience ── */
export {
  loadLibrary, removeFromLibrary, updateInLibrary, formatFileSize, formatDate, FILE_CATEGORIES,
  loadLibraryFromServer, deleteFileOnServer, updateFileOnServer, getStatsFromServer, trackViewOnServer,
};

/* ── Category icons / colors for badges ── */
const CATEGORY_META = {
  model:    { icon: 'box',       label: '3D Model',  color: '#6C5CE7', cls: 'badge-model' },
  panorama: { icon: 'globe',     label: 'Panorama',  color: '#00CEC9', cls: 'badge-panorama' },
  image:    { icon: 'image',     label: 'Image',     color: '#74B9FF', cls: 'badge-image' },
  embed:    { icon: 'monitor',   label: 'Embed',     color: '#FD79A8', cls: 'badge-embed' },
  video:    { icon: 'film',      label: 'Video',     color: '#FDCB6E', cls: 'badge-video' },
  document: { icon: 'file-text', label: 'Document',  color: '#DFE6E9', cls: 'badge-document' },
  other:    { icon: 'file',      label: 'Other',     color: '#FDCB6E', cls: 'badge-other' },
};

export function getCategoryMeta(cat) {
  return CATEGORY_META[cat] || CATEGORY_META.other;
}

/**
 * Render a Feather icon as inline SVG HTML string.
 * Uses the global `feather` object (loaded via CDN).
 * Falls back to a generic <i> tag if feather is not loaded.
 */
export function renderFeatherIcon(name, size = 18) {
  if (typeof feather !== 'undefined' && feather.icons[name]) {
    return feather.icons[name].toSvg({ width: size, height: size, 'stroke-width': 2 });
  }
  // Fallback: render an <i> tag that feather.replace() can pick up later
  return `<i data-feather="${name}" style="width:${size}px;height:${size}px;"></i>`;
}

/**
 * Get category icon as rendered SVG HTML
 */
export function renderCategoryIcon(cat, size = 18) {
  const meta = getCategoryMeta(cat);
  return renderFeatherIcon(meta.icon, size);
}

/* ── Compute stats from library ── */
export function computeStats(files) {
  const stats = {
    total: files.length,
    models: 0,
    panoramas: 0,
    images: 0,
    embeds: 0,
    totalSize: 0,
  };
  files.forEach(f => {
    if (f.category === 'model')    stats.models++;
    if (f.category === 'panorama') stats.panoramas++;
    if (f.category === 'image')    stats.images++;
    if (f.category === 'embed')    stats.embeds++;
    stats.totalSize += (f.size || 0);
  });
  stats.totalSizeFormatted = formatFileSize(stats.totalSize);
  return stats;
}

/* ── Count by category ── */
export function countByCategory(files) {
  const counts = { all: files.length, model: 0, panorama: 0, image: 0, embed: 0 };
  files.forEach(f => {
    if (counts[f.category] !== undefined) counts[f.category]++;
  });
  return counts;
}

/* ── Advanced sort ── */
export function sortFiles(files, sortKey) {
  const sorted = [...files];
  switch (sortKey) {
    case 'newest':
      return sorted.sort((a, b) => (b.uploadedAt || 0) - (a.uploadedAt || 0));
    case 'oldest':
      return sorted.sort((a, b) => (a.uploadedAt || 0) - (b.uploadedAt || 0));
    case 'name_asc':
      return sorted.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    case 'name_desc':
      return sorted.sort((a, b) => (b.name || '').localeCompare(a.name || ''));
    case 'largest':
      return sorted.sort((a, b) => (b.size || 0) - (a.size || 0));
    case 'smallest':
      return sorted.sort((a, b) => (a.size || 0) - (b.size || 0));
    case 'type':
      return sorted.sort((a, b) => (a.category || '').localeCompare(b.category || ''));
    default:
      return sorted;
  }
}

/* ── Filter + Search combined ── */
export function filterAndSearch(files, category, query) {
  let result = files;

  if (category && category !== 'all') {
    result = result.filter(f => f.category === category);
  }

  if (query) {
    const q = query.toLowerCase();
    result = result.filter(f =>
      (f.name || '').toLowerCase().includes(q) ||
      (f.description || '').toLowerCase().includes(q) ||
      (f.extension || '').toLowerCase().includes(q) ||
      (f.originalName || '').toLowerCase().includes(q)
    );
  }

  return result;
}

/* ── Paginate ── */
export function paginate(files, page, perPage) {
  const total = files.length;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const safePage = Math.min(Math.max(1, page), totalPages);
  const start = (safePage - 1) * perPage;
  const data = files.slice(start, start + perPage);
  return { data, page: safePage, perPage, total, totalPages };
}

/* ── Generate page range for pagination buttons ── */
export function pageRange(current, totalPages, delta = 2) {
  const range = [];
  const left  = Math.max(1, current - delta);
  const right = Math.min(totalPages, current + delta);

  if (left > 1) {
    range.push(1);
    if (left > 2) range.push('...');
  }

  for (let i = left; i <= right; i++) {
    range.push(i);
  }

  if (right < totalPages) {
    if (right < totalPages - 1) range.push('...');
    range.push(totalPages);
  }

  return range;
}

/* ── Relative time ── */
export function relativeTime(timestamp) {
  if (!timestamp) return '';
  const now = Date.now();
  const diff = now - timestamp;
  const sec = Math.floor(diff / 1000);
  if (sec < 60)    return 'Just now';
  const min = Math.floor(sec / 60);
  if (min < 60)    return `${min}m ago`;
  const hr  = Math.floor(min / 60);
  if (hr < 24)     return `${hr}h ago`;
  const day = Math.floor(hr / 24);
  if (day < 7)     return `${day}d ago`;
  if (day < 30)    return `${Math.floor(day / 7)}w ago`;
  if (day < 365)   return `${Math.floor(day / 30)}mo ago`;
  return `${Math.floor(day / 365)}y ago`;
}

/* ── Build viewer URL for a file ── */
export function getViewerUrl(file) {
  if (!file) return '#';

  if (file.category === 'embed' || file.isEmbed) {
    const params = new URLSearchParams();
    params.set('src', file.embedSrc || file.url || '');
    if (file.name) params.set('name', file.name);
    if (file.description) params.set('desc', file.description);
    return '../../package_upload/iframe/iframe.html?' + params.toString();
  }

  const src = file.url || file.blobUrl || '';
  if (!src) return '#';

  if (file.category === 'model') {
    return '../../package_3d_viewer/camera/camera.html?src=' + encodeURIComponent(src);
  }

  return '../../package_panorama/photo/photo.html?src=' + encodeURIComponent(src);
}

/* ── Build QR url ── */
export function getQRUrl(file) {
  if (!file) return '#';
  const src = file.url || file.blobUrl || '';
  return '../../package_ar/qr/qr.html?src=' + encodeURIComponent(src) + '&name=' + encodeURIComponent(file.name || '');
}

/* ── Is image that can render a thumbnail ── */
export function canShowThumbnail(file) {
  if (file.thumbnail) return true;
  if (file.category === 'image' && (file.url || file.blobUrl)) return true;
  return false;
}

/* ── Get thumbnail src ── */
export function getThumbnailSrc(file) {
  if (file.thumbnail) return file.thumbnail;
  if (file.category === 'image') return file.url || file.blobUrl || '';
  return '';
}

/* ── Is 3D model that can show canvas preview ── */
export function canShowModelPreview(file) {
  return file.category === 'model' && (file.url || file.blobUrl);
}

/* ── Storage key for view tracking ── */
const VIEW_KEY = 'vrx_gallery_views';

export function trackView(fileId) {
  // Track on server (fire-and-forget)
  trackViewOnServer(fileId);
  // Also track locally for immediate feedback
  try {
    const views = JSON.parse(localStorage.getItem(VIEW_KEY) || '{}');
    views[fileId] = (views[fileId] || 0) + 1;
    localStorage.setItem(VIEW_KEY, JSON.stringify(views));
    return views[fileId];
  } catch { return 0; }
}

export function getViews(fileId) {
  try {
    const views = JSON.parse(localStorage.getItem(VIEW_KEY) || '{}');
    return views[fileId] || 0;
  } catch { return 0; }
}
