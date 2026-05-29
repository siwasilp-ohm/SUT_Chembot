/**
 * VRX QR Business Logic (ES Module)
 * Handles: QR generation, AR URL building, history management, sharing
 */

const HISTORY_KEY = 'vrx_qr_history';
const MAX_HISTORY = 30;

/**
 * Build AR viewer URL from a model source
 * @param {string} modelSrc - model URL or relative path
 * @param {string} modelName
 * @param {string} modelDesc
 * @returns {string} Full URL to AR viewer
 */
function buildArUrl(modelSrc, modelName, modelDesc) {
  // Determine the base URL of the project (vrx root)
  var base = getProjectBaseUrl();

  // If modelSrc is a full URL (http/https/blob), use as-is
  // If it starts with / it's already an absolute path — just prepend origin
  var src = modelSrc;
  if (src.match(/^(https?:|blob:)/i)) {
    // Already absolute URL, use as-is
  } else if (src.charAt(0) === '/') {
    // Absolute path from domain root — prepend origin only
    src = window.location.origin + src;
  } else {
    // It's a relative path — make absolute from project base
    src = src.replace(/^\.\//, '');
    src = base + src;
  }

  var arViewerUrl = base + 'package_ar/viewer/ar.html';
  var params = new URLSearchParams();
  params.set('src', src);
  if (modelName) params.set('name', modelName);
  if (modelDesc) params.set('desc', modelDesc);

  return arViewerUrl + '?' + params.toString();
}

/**
 * Get the VRX project base URL robustly
 * Works regardless of which page calls it
 */
function getProjectBaseUrl() {
  var loc = window.location;
  var path = loc.pathname;

  // Find the vrx root by looking for known project folder markers
  // The project should be at some path like /vrx/ or similar
  var parts = path.split('/').filter(function (p) { return p.length > 0; });

  // Walk up from current path to find the level that contains package_ar, package_3d_viewer, etc.
  // For a page at /vrx/package_ar/qr/qr.html, we need /vrx/
  // Strategy: find index of 'package_ar' or 'package_3d_viewer' or 'package_upload'
  var markers = ['package_ar', 'package_3d_viewer', 'package_upload', 'package_panorama'];
  var rootIdx = -1;
  for (var i = 0; i < parts.length; i++) {
    for (var m = 0; m < markers.length; m++) {
      if (parts[i] === markers[m]) {
        rootIdx = i;
        break;
      }
    }
    if (rootIdx >= 0) break;
  }

  var basePath = '/';
  if (rootIdx > 0) {
    basePath = '/' + parts.slice(0, rootIdx).join('/') + '/';
  } else if (rootIdx === 0) {
    basePath = '/';
  } else {
    // Fallback: assume current directory minus last 2 parts (e.g., qr/qr.html)
    var dirParts = parts.slice(0, -2);
    basePath = '/' + (dirParts.length > 0 ? dirParts.join('/') + '/' : '');
  }

  return loc.origin + basePath;
}

/**
 * Generate QR code into a DOM element
 * @param {string} elementId - target element ID
 * @param {string} text - text/URL to encode
 * @param {object} options - size, color options
 */
function generateQRCode(elementId, text, options) {
  var el = document.getElementById(elementId);
  if (!el) return;
  el.innerHTML = '';

  var opts = options || {};
  var size = opts.size || Math.min(240, window.innerWidth - 80);

  if (typeof QRCode === 'undefined') {
    console.error('QRCode library not loaded');
    el.innerHTML = '<p style="color:red">QRCode library not loaded</p>';
    return;
  }

  new QRCode(el, {
    text: text,
    width: size,
    height: size,
    colorDark: opts.colorDark || '#1a1a2e',
    colorLight: opts.colorLight || '#ffffff',
    correctLevel: QRCode.CorrectLevel.M,
  });
}

/**
 * Download QR code as PNG
 */
function downloadQRCode(elementId, filename) {
  var el = document.getElementById(elementId);
  if (!el) return;
  var canvas = el.querySelector('canvas');
  var img = el.querySelector('img');
  var src = '';

  if (canvas) {
    src = canvas.toDataURL('image/png');
  } else if (img) {
    src = img.src;
  }

  if (!src) return;

  var link = document.createElement('a');
  link.download = (filename || 'vrx-qr') + '.png';
  link.href = src;
  link.click();
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    return navigator.clipboard.writeText(text);
  }
  // Fallback
  return new Promise(function (resolve) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    resolve();
  });
}

/**
 * Share URL using Web Share API or fallback
 */
function shareUrl(url, title) {
  if (navigator.share) {
    return navigator.share({ title: title || 'VRX AR 3D', url: url });
  }
  return copyToClipboard(url);
}

// ---- History ----

function loadHistory() {
  try {
    return JSON.parse(localStorage.getItem(HISTORY_KEY)) || [];
  } catch (e) {
    return [];
  }
}

function saveHistory(items) {
  localStorage.setItem(HISTORY_KEY, JSON.stringify(items.slice(0, MAX_HISTORY)));
}

function addToHistory(item) {
  var items = loadHistory();
  // Prevent duplicate URLs
  items = items.filter(function (h) { return h.arUrl !== item.arUrl; });
  var entry = {
    id: Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
    modelSrc: item.modelSrc,
    modelName: item.modelName || 'AR Model',
    modelDesc: item.modelDesc || '',
    arUrl: item.arUrl,
    createdAt: new Date().toISOString(),
    createdAtFormatted: formatDate(new Date()),
  };
  items.unshift(entry);
  saveHistory(items);
  return entry;
}

function removeFromHistory(id) {
  var items = loadHistory().filter(function (h) { return h.id !== id; });
  saveHistory(items);
  return items;
}

function clearHistory() {
  localStorage.removeItem(HISTORY_KEY);
}

function formatDate(date) {
  var d = date instanceof Date ? date : new Date(date);
  var day = String(d.getDate()).padStart(2, '0');
  var month = String(d.getMonth() + 1).padStart(2, '0');
  var year = d.getFullYear();
  var h = String(d.getHours()).padStart(2, '0');
  var m = String(d.getMinutes()).padStart(2, '0');
  return day + '/' + month + '/' + year + ' ' + h + ':' + m;
}

export {
  buildArUrl,
  generateQRCode,
  downloadQRCode,
  copyToClipboard,
  shareUrl,
  loadHistory,
  addToHistory,
  removeFromHistory,
  clearHistory,
  formatDate,
};
