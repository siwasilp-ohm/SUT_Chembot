/**
 * VRX QR Scanner — Business Logic (ES Module)
 * Handles: Camera stream, QR detection via jsQR, URL parsing, scan history
 */

const SCAN_HISTORY_KEY = 'vrx_scan_history';
const MAX_SCAN_HISTORY  = 50;

/* ── Camera ── */

let _stream = null;
let _videoEl = null;
let _canvasEl = null;
let _canvasCtx = null;
let _scanning = false;
let _animFrame = null;
let _onDetect = null;
let _lastDetected = '';
let _lastDetectedTime = 0;
const DEBOUNCE_MS = 2000; // avoid double-scan of same QR

/**
 * Start camera and begin scanning loop
 * @param {HTMLVideoElement} video
 * @param {HTMLCanvasElement} canvas
 * @param {Function} onDetect  — callback(decodedText)
 * @returns {Promise<boolean>}
 */
async function startScanner(video, canvas, onDetect) {
  _videoEl = video;
  _canvasEl = canvas;
  _canvasCtx = canvas.getContext('2d', { willReadFrequently: true });
  _onDetect = onDetect;

  // Request rear camera first, fallback to any
  const constraints = {
    video: {
      facingMode: { ideal: 'environment' },
      width:  { ideal: 1280 },
      height: { ideal: 720 },
    },
    audio: false,
  };

  try {
    _stream = await navigator.mediaDevices.getUserMedia(constraints);
    video.srcObject = _stream;
    await video.play();
    _scanning = true;
    _scanLoop();
    return true;
  } catch (err) {
    console.warn('Camera access denied or unavailable:', err.message);
    // Try front camera
    try {
      constraints.video.facingMode = 'user';
      _stream = await navigator.mediaDevices.getUserMedia(constraints);
      video.srcObject = _stream;
      await video.play();
      _scanning = true;
      _scanLoop();
      return true;
    } catch (e2) {
      console.error('No camera available:', e2.message);
      return false;
    }
  }
}

/**
 * Internal scanning loop — reads frames and scans for QR
 */
function _scanLoop() {
  if (!_scanning || !_videoEl) return;

  if (_videoEl.readyState === _videoEl.HAVE_ENOUGH_DATA) {
    var w = _videoEl.videoWidth;
    var h = _videoEl.videoHeight;
    if (w > 0 && h > 0) {
      _canvasEl.width = w;
      _canvasEl.height = h;
      _canvasCtx.drawImage(_videoEl, 0, 0, w, h);

      var imgData = _canvasCtx.getImageData(0, 0, w, h);
      if (typeof jsQR !== 'undefined') {
        var code = jsQR(imgData.data, w, h, { inversionAttempts: 'dontInvert' });
        if (code && code.data) {
          var now = Date.now();
          // Debounce: don't re-fire same QR within 2s
          if (code.data !== _lastDetected || (now - _lastDetectedTime) > DEBOUNCE_MS) {
            _lastDetected = code.data;
            _lastDetectedTime = now;
            // Draw highlight on QR location
            _highlightQR(code.location);
            if (_onDetect) _onDetect(code.data);
          }
        }
      }
    }
  }

  _animFrame = requestAnimationFrame(_scanLoop);
}

/**
 * Draw green highlight around detected QR code
 */
function _highlightQR(loc) {
  if (!loc || !_canvasCtx) return;
  _canvasCtx.strokeStyle = '#00CEC9';
  _canvasCtx.lineWidth = 4;
  _canvasCtx.beginPath();
  _canvasCtx.moveTo(loc.topLeftCorner.x, loc.topLeftCorner.y);
  _canvasCtx.lineTo(loc.topRightCorner.x, loc.topRightCorner.y);
  _canvasCtx.lineTo(loc.bottomRightCorner.x, loc.bottomRightCorner.y);
  _canvasCtx.lineTo(loc.bottomLeftCorner.x, loc.bottomLeftCorner.y);
  _canvasCtx.closePath();
  _canvasCtx.stroke();
}

/**
 * Stop scanner and release camera
 */
function stopScanner() {
  _scanning = false;
  if (_animFrame) {
    cancelAnimationFrame(_animFrame);
    _animFrame = null;
  }
  if (_stream) {
    _stream.getTracks().forEach(function (t) { t.stop(); });
    _stream = null;
  }
  if (_videoEl) {
    _videoEl.srcObject = null;
  }
  _lastDetected = '';
  _lastDetectedTime = 0;
}

/**
 * Toggle camera torch/flashlight (if supported)
 */
async function toggleTorch(enable) {
  if (!_stream) return false;
  var track = _stream.getVideoTracks()[0];
  if (!track) return false;
  try {
    await track.applyConstraints({ advanced: [{ torch: enable }] });
    return true;
  } catch (e) {
    return false;
  }
}

/* ── URL Parsing ── */

/**
 * Check if a scanned URL belongs to VRX system
 * Returns parsed info or null
 */
function parseVrxUrl(url) {
  if (!url) return null;

  try {
    var parsed = new URL(url);
    var path = parsed.pathname;
    var params = parsed.searchParams;

    // Check for AR viewer pattern: .../package_ar/viewer/ar.html?src=...
    if (path.indexOf('package_ar/viewer/ar.html') >= 0 || path.indexOf('ar.html') >= 0) {
      return {
        type: 'ar',
        src: params.get('src') || '',
        name: params.get('name') || '',
        desc: params.get('desc') || '',
        originalUrl: url,
        isVrx: true,
      };
    }

    // Check for 3D viewer: .../package_3d_viewer/camera/camera.html?src=...
    if (path.indexOf('package_3d_viewer') >= 0 || path.indexOf('camera.html') >= 0) {
      return {
        type: '3d',
        src: params.get('src') || '',
        name: params.get('name') || '',
        desc: params.get('desc') || '',
        originalUrl: url,
        isVrx: true,
      };
    }

    // Check for panorama: .../package_panorama/photo/photo.html?src=...
    if (path.indexOf('package_panorama') >= 0 || path.indexOf('photo.html') >= 0) {
      return {
        type: 'panorama',
        src: params.get('src') || '',
        name: params.get('name') || '',
        desc: params.get('desc') || '',
        originalUrl: url,
        isVrx: true,
      };
    }

    // Check for gallery item: .../package_gallery/...?id=...
    if (path.indexOf('package_gallery') >= 0 || path.indexOf('gallery') >= 0) {
      return {
        type: 'gallery',
        id: params.get('id') || '',
        originalUrl: url,
        isVrx: true,
      };
    }

    // Check for embed viewer: .../package_embed/embed/embed-viewer.html?src=...
    if (path.indexOf('package_embed') >= 0 || path.indexOf('embed-viewer') >= 0) {
      return {
        type: 'embed',
        src: params.get('src') || '',
        name: params.get('name') || '',
        originalUrl: url,
        isVrx: true,
      };
    }

    // Generic VRX URL (contains 'vrx' in path)
    if (path.indexOf('vrx') >= 0 || parsed.hostname === window.location.hostname) {
      return {
        type: 'vrx-other',
        originalUrl: url,
        isVrx: true,
      };
    }
  } catch (e) {
    // Not a valid URL
  }

  // External URL or plain text
  return {
    type: 'external',
    originalUrl: url,
    isVrx: false,
  };
}

/**
 * Get type label for display
 */
function getTypeLabel(type) {
  var labels = {
    'ar':        'AR 3D Model',
    '3d':        '3D Viewer',
    'panorama':  'Panorama',
    'gallery':   'Gallery Item',
    'embed':     'Embed Viewer',
    'vrx-other': 'VRX Page',
    'external':  'External Link',
  };
  return labels[type] || 'Unknown';
}

/**
 * Get Feather icon name for content type
 */
function getTypeFeatherIcon(type) {
  var icons = {
    'ar':        'smartphone',
    '3d':        'box',
    'panorama':  'globe',
    'gallery':   'folder',
    'embed':     'monitor',
    'vrx-other': 'home',
    'external':  'external-link',
  };
  return icons[type] || 'file';
}

/**
 * Get type color for badge
 */
function getTypeColor(type) {
  var colors = {
    'ar':        '#6C5CE7',
    '3d':        '#A29BFE',
    'panorama':  '#00CEC9',
    'gallery':   '#74B9FF',
    'embed':     '#E17055',
    'vrx-other': '#FDCB6E',
    'external':  '#636E72',
  };
  return colors[type] || '#636E72';
}

/* ── Scan History ── */

function loadScanHistory() {
  try {
    return JSON.parse(localStorage.getItem(SCAN_HISTORY_KEY)) || [];
  } catch (e) {
    return [];
  }
}

function saveScanHistory(items) {
  localStorage.setItem(SCAN_HISTORY_KEY, JSON.stringify(items.slice(0, MAX_SCAN_HISTORY)));
}

function addToScanHistory(item) {
  var items = loadScanHistory();
  // Prevent duplicate consecutive scans of the same URL
  if (items.length > 0 && items[0].url === item.url) {
    // Update timestamp instead of duplicating
    items[0].scannedAt = new Date().toISOString();
    items[0].scanCount = (items[0].scanCount || 1) + 1;
    saveScanHistory(items);
    return items[0];
  }
  var entry = {
    id: Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
    url: item.url,
    type: item.type || 'external',
    name: item.name || '',
    isVrx: item.isVrx || false,
    scannedAt: new Date().toISOString(),
    scannedAtFormatted: formatDate(new Date()),
    scanCount: 1,
  };
  items.unshift(entry);
  saveScanHistory(items);
  return entry;
}

function removeFromScanHistory(id) {
  var items = loadScanHistory().filter(function (h) { return h.id !== id; });
  saveScanHistory(items);
  return items;
}

function clearScanHistory() {
  localStorage.removeItem(SCAN_HISTORY_KEY);
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

/* ── Session / Role ── */

/**
 * Get current user session from window.VRX_SESSION or fetch from API
 */
function getSession() {
  if (window.VRX_SESSION) return window.VRX_SESSION;
  return { loggedIn: false, user: null, permissions: {} };
}

/**
 * Check if current user has a permission
 */
function canDo(action) {
  var session = getSession();
  return session.permissions && session.permissions[action] === true;
}

/**
 * Fetch session from API endpoint
 */
async function fetchSession() {
  try {
    var resp = await fetch('/vrx/auth/status.php');
    var data = await resp.json();
    window.VRX_SESSION = data;
    return data;
  } catch (e) {
    return { loggedIn: false, user: null, permissions: {} };
  }
}

/**
 * Log a QR scan to the server (for analytics / activity log)
 */
async function logScanToServer(scanData) {
  try {
    await fetch('/vrx/database/api.php?action=log_scan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(scanData),
    });
  } catch (e) {
    // Silent fail — analytics logging is non-critical
  }
}

export {
  startScanner,
  stopScanner,
  toggleTorch,
  parseVrxUrl,
  getTypeLabel,
  getTypeColor,
  getTypeFeatherIcon,
  loadScanHistory,
  addToScanHistory,
  removeFromScanHistory,
  clearScanHistory,
  formatDate,
  getSession,
  canDo,
  fetchSession,
  logScanToServer,
};
