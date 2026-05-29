/**
 * VRX File Manager - Business Logic (ES Module)
 * Handles file storage, categorization, metadata management
 * Now syncs to server-side database via REST API for cross-device access
 */

const STORAGE_KEY = 'vrx_file_library';
const API_BASE = '/vrx/database/api.php';

// File categories
const FILE_CATEGORIES = {
  MODEL_3D: { key: 'model', label: '3D Models', icon: 'box', extensions: ['.glb', '.gltf', '.obj', '.fbx', '.stl'] },
  PANORAMA: { key: 'panorama', label: 'Panorama', icon: 'globe', extensions: ['.jpg', '.jpeg', '.png', '.webp', '.hdr'] },
  IMAGE: { key: 'image', label: 'Images', icon: 'image', extensions: ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.bmp'] },
  EMBED: { key: 'embed', label: 'Embeds', icon: 'monitor', extensions: [] },
};

/**
 * Determine file category by extension
 */
function categorizeFile(filename) {
  const ext = '.' + filename.split('.').pop().toLowerCase();
  if (FILE_CATEGORIES.MODEL_3D.extensions.includes(ext)) return 'model';
  if (FILE_CATEGORIES.PANORAMA.extensions.includes(ext)) return 'panorama';
  if (FILE_CATEGORIES.IMAGE.extensions.includes(ext)) return 'image';
  return 'other';
}

/**
 * Format file size to human readable string
 */
function formatFileSize(bytes) {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + units[i];
}

/**
 * Format date to localized string
 */
function formatDate(timestamp) {
  const d = new Date(timestamp);
  return d.toLocaleDateString('th-TH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Generate unique ID
 */
function generateId() {
  return Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
}

/**
 * Create file metadata object
 */
function createFileMetadata(file, category, customData = {}) {
  return {
    id: generateId(),
    name: customData.name || file.name,
    originalName: file.name,
    description: customData.description || '',
    category: category || categorizeFile(file.name),
    size: file.size,
    sizeFormatted: formatFileSize(file.size),
    type: file.type,
    extension: file.name.split('.').pop().toLowerCase(),
    uploadedAt: Date.now(),
    uploadedAtFormatted: formatDate(Date.now()),
    tags: customData.tags || [],
  };
}

/**
 * Create metadata from URL
 */
function createUrlMetadata(url, customData = {}) {
  const filename = url.split('/').pop().split('?')[0] || 'unknown';
  const category = categorizeFile(filename);
  return {
    id: generateId(),
    name: customData.name || filename,
    originalName: filename,
    description: customData.description || '',
    category: category,
    size: 0,
    sizeFormatted: 'External',
    type: '',
    extension: filename.split('.').pop().toLowerCase(),
    uploadedAt: Date.now(),
    uploadedAtFormatted: formatDate(Date.now()),
    isExternal: true,
    url: url,
    tags: customData.tags || [],
  };
}

/**
 * Create metadata from an iframe embed code
 */
function createEmbedMetadata(src, customData = {}) {
  return {
    id: generateId(),
    name: customData.name || 'Embed',
    originalName: customData.name || 'Embed',
    description: customData.description || '',
    category: 'embed',
    size: 0,
    sizeFormatted: 'Embed',
    type: 'text/html',
    extension: 'iframe',
    uploadedAt: Date.now(),
    uploadedAtFormatted: formatDate(Date.now()),
    isExternal: true,
    isEmbed: true,
    url: src,
    embedSrc: src,
    embedCode: customData.embedCode || '',
    embedTitle: customData.embedTitle || '',
    tags: customData.tags || [],
  };
}

/**
 * Create blob URL from file
 */
function createBlobUrl(file) {
  const urlCreator = window.URL || window.webkitURL;
  if (urlCreator) {
    return urlCreator.createObjectURL(file);
  }
  return null;
}

/**
 * Revoke blob URL to prevent memory leak
 */
function revokeBlobUrl(url) {
  if (url && url.startsWith('blob:')) {
    const urlCreator = window.URL || window.webkitURL;
    if (urlCreator) {
      urlCreator.revokeObjectURL(url);
    }
  }
}

/**
 * Load file library from localStorage (offline cache)
 */
function loadLibrary() {
  try {
    const data = localStorage.getItem(STORAGE_KEY);
    return data ? JSON.parse(data) : [];
  } catch (e) {
    console.error('Failed to load library', e);
    return [];
  }
}

/**
 * Save file library to localStorage (offline cache)
 */
function saveLibrary(library) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(library));
  } catch (e) {
    console.error('Failed to save library', e);
  }
}

/* ================================================================
 *  SERVER API — Cross-device sync via REST API
 *  These async functions talk to /vrx/database/api.php
 * ================================================================ */

/**
 * Normalize a server file row into the same shape the frontend expects.
 * The DB stores fields like `file_url`, `file_size`, `category_slug`,
 * while the frontend uses `url`, `size`, `category`.
 */
function _normalizeServerFile(row) {
  return {
    id:               row.id,
    uuid:             row.uuid || '',
    name:             row.name || '',
    originalName:     row.original_name || row.name || '',
    description:      row.description || '',
    category:         row.category_slug || 'other',
    categoryName:     row.category_name || '',
    categoryIcon:     row.category_icon || 'file',
    categoryColor:    row.category_color || '#636E72',
    size:             Number(row.file_size) || 0,
    sizeFormatted:    formatFileSize(Number(row.file_size) || 0),
    type:             row.mime_type || '',
    extension:        row.extension || '',
    uploadedAt:       row.uploaded_at ? new Date(row.uploaded_at).getTime() : Date.now(),
    uploadedAtFormatted: row.uploaded_at ? formatDate(new Date(row.uploaded_at).getTime()) : '',
    isExternal:       Number(row.is_external) === 1,
    isEmbed:          row.source_type === 'embed',
    url:              row.file_url || '',
    blobUrl:          row.file_url || '',
    filePath:         row.file_path || '',
    thumbnail:        row.thumbnail_path || '',
    embedSrc:         row.embed_src || '',
    embedCode:        row.embed_code || '',
    embedTitle:       row.embed_provider || '',
    sourceType:       row.source_type || 'upload',
    visibility:       row.visibility || 'private',
    viewCount:        Number(row.view_count) || 0,
    downloadCount:    Number(row.download_count) || 0,
    likeCount:        Number(row.like_count) || 0,
    shareCount:       Number(row.share_count) || 0,
    arEnabled:        Number(row.ar_enabled) === 1,
    arScale:          Number(row.ar_scale) || 1,
    tags:             row.tags || [],
    userId:           Number(row.user_id) || 0,
    _server:          true, // Flag: this record came from the server
  };
}

/**
 * Load file library from the server API.
 * Returns an array of normalized file objects.
 * Falls back to localStorage on network error.
 */
async function loadLibraryFromServer(opts = {}) {
  try {
    const params = new URLSearchParams({ action: 'files' });
    if (opts.category && opts.category !== 'all') params.set('category', opts.category);
    if (opts.search)   params.set('search', opts.search);
    if (opts.sort)     params.set('sort', opts.sort);
    if (opts.page)     params.set('page', opts.page);
    if (opts.limit)    params.set('limit', opts.limit);

    const res = await fetch(`${API_BASE}?${params.toString()}`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('API ' + res.status);
    const json = await res.json();

    const files = (json.data || []).map(_normalizeServerFile);

    // Also update localStorage cache
    if (!opts.category && !opts.search) {
      saveLibrary(files);
    }

    return { files, pagination: json.pagination || null };
  } catch (e) {
    console.warn('loadLibraryFromServer failed, falling back to localStorage', e);
    return { files: loadLibrary(), pagination: null };
  }
}

/**
 * Upload a physical file to the server and create a DB record.
 * Returns the normalized file object on success.
 */
async function uploadFileToServer(file, metadata = {}) {
  // Step 1: Upload the binary file
  const formData = new FormData();
  formData.append('file', file);

  const uploadRes = await fetch(`${API_BASE}?action=upload`, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });
  if (!uploadRes.ok) {
    const err = await uploadRes.json().catch(() => ({}));
    throw new Error(err.error || 'Upload failed');
  }
  const uploadData = (await uploadRes.json()).data;

  // Step 2: Create the file record in the DB
  const category = metadata.category || categorizeFile(file.name);
  const sourceType = 'upload';

  const body = {
    name:          metadata.name || file.name,
    original_name: file.name,
    description:   metadata.description || '',
    category:      category,
    file_url:      uploadData.file_url,
    file_path:     uploadData.file_path,
    mime_type:     uploadData.mime_type || file.type,
    extension:     uploadData.extension,
    file_size:     uploadData.file_size || file.size,
    source_type:   sourceType,
    is_external:   0,
    visibility:    metadata.visibility || 'public',
    tags:          metadata.tags || [],
  };

  const createRes = await fetch(`${API_BASE}?action=files`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin',
  });
  if (!createRes.ok) {
    const err = await createRes.json().catch(() => ({}));
    throw new Error(err.error || 'Failed to create file record');
  }

  const result = (await createRes.json()).data;

  // Return a normalized object so the UI can update immediately
  return _normalizeServerFile({
    ...body,
    id: result.id,
    uuid: result.uuid,
    category_slug: category,
    uploaded_at: new Date().toISOString(),
  });
}

/**
 * Save a URL-imported file to the server DB.
 */
async function saveUrlToServer(url, metadata = {}) {
  const filename = url.split('/').pop().split('?')[0] || 'unknown';
  const category = metadata.category || categorizeFile(filename);

  const body = {
    name:          metadata.name || filename,
    original_name: filename,
    description:   metadata.description || '',
    category:      category,
    file_url:      url,
    extension:     filename.split('.').pop().toLowerCase(),
    source_type:   'url',
    is_external:   1,
    visibility:    metadata.visibility || 'public',
    tags:          metadata.tags || [],
  };

  const res = await fetch(`${API_BASE}?action=files`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin',
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error || 'Failed to save URL');
  }
  const result = (await res.json()).data;
  return _normalizeServerFile({
    ...body,
    id: result.id,
    uuid: result.uuid,
    category_slug: category,
    uploaded_at: new Date().toISOString(),
  });
}

/**
 * Save an embed/iframe to the server DB.
 */
async function saveEmbedToServer(src, metadata = {}) {
  const body = {
    name:           metadata.name || 'Embed',
    original_name:  metadata.name || 'Embed',
    description:    metadata.description || '',
    category:       'embed',
    file_url:       src,
    embed_src:      src,
    embed_code:     metadata.embedCode || '',
    embed_provider: metadata.embedTitle || '',
    extension:      'iframe',
    mime_type:      'text/html',
    source_type:    'embed',
    is_external:    1,
    visibility:     metadata.visibility || 'public',
    tags:           metadata.tags || [],
  };

  const res = await fetch(`${API_BASE}?action=files`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    credentials: 'same-origin',
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error || 'Failed to save embed');
  }
  const result = (await res.json()).data;
  return _normalizeServerFile({
    ...body,
    id: result.id,
    uuid: result.uuid,
    category_slug: 'embed',
    uploaded_at: new Date().toISOString(),
  });
}

/**
 * Update a file record on the server.
 */
async function updateFileOnServer(id, updates) {
  const res = await fetch(`${API_BASE}?action=files&id=${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(updates),
    credentials: 'same-origin',
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error || 'Failed to update file');
  }
  return true;
}

/**
 * Delete a file record on the server (soft-delete).
 */
async function deleteFileOnServer(id) {
  const res = await fetch(`${API_BASE}?action=files&id=${id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error || 'Failed to delete file');
  }
  return true;
}

/**
 * Get stats from the server.
 */
async function getStatsFromServer() {
  try {
    const res = await fetch(`${API_BASE}?action=stats`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('API ' + res.status);
    return (await res.json()).data;
  } catch (e) {
    console.warn('getStatsFromServer failed', e);
    return null;
  }
}

/**
 * Increment view count on server.
 */
async function trackViewOnServer(fileId) {
  try {
    await fetch(`${API_BASE}?action=view&id=${fileId}`, {
      method: 'POST',
      credentials: 'same-origin',
    });
  } catch { /* ignore */ }
}

/**
 * Add file entry to library (localStorage only — legacy)
 */
function addToLibrary(metadata) {
  const library = loadLibrary();
  library.unshift(metadata);
  saveLibrary(library);
  return library;
}

/**
 * Remove file entry from library (localStorage only — legacy)
 */
function removeFromLibrary(id) {
  let library = loadLibrary();
  library = library.filter(item => item.id !== id);
  saveLibrary(library);
  return library;
}

/**
 * Update file entry in library (localStorage only — legacy)
 */
function updateInLibrary(id, updates) {
  const library = loadLibrary();
  const index = library.findIndex(item => item.id === id);
  if (index !== -1) {
    library[index] = { ...library[index], ...updates };
    saveLibrary(library);
  }
  return library;
}

/**
 * Filter library by category
 */
function filterByCategory(category) {
  const library = loadLibrary();
  if (!category || category === 'all') return library;
  return library.filter(item => item.category === category);
}

/**
 * Search library by name
 */
function searchLibrary(query) {
  const library = loadLibrary();
  if (!query) return library;
  const lq = query.toLowerCase();
  return library.filter(item =>
    item.name.toLowerCase().includes(lq) ||
    item.description.toLowerCase().includes(lq) ||
    item.extension.toLowerCase().includes(lq)
  );
}

/**
 * Validate file type
 */
function validateFile(file) {
  const allExtensions = [
    ...FILE_CATEGORIES.MODEL_3D.extensions,
    ...FILE_CATEGORIES.PANORAMA.extensions,
    ...FILE_CATEGORIES.IMAGE.extensions,
  ];
  const ext = '.' + file.name.split('.').pop().toLowerCase();
  if (!allExtensions.includes(ext)) {
    return { valid: false, message: `Unsupported file type: ${ext}` };
  }
  // Max 100MB
  if (file.size > 100 * 1024 * 1024) {
    return { valid: false, message: 'File size exceeds 100MB limit' };
  }
  return { valid: true };
}

/**
 * Validate URL format
 */
function validateUrl(url) {
  try {
    new URL(url);
    return { valid: true };
  } catch {
    return { valid: false, message: 'Invalid URL format' };
  }
}

/**
 * Generate preview thumbnail from image file
 */
function generateThumbnail(file) {
  return new Promise((resolve) => {
    if (!file.type.startsWith('image/')) {
      resolve(null);
      return;
    }
    const reader = new FileReader();
    reader.onload = function (e) {
      const img = new Image();
      img.onload = function () {
        const canvas = document.createElement('canvas');
        const size = 200;
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');
        const scale = Math.max(size / img.width, size / img.height);
        const x = (size - img.width * scale) / 2;
        const y = (size - img.height * scale) / 2;
        ctx.drawImage(img, x, y, img.width * scale, img.height * scale);
        resolve(canvas.toDataURL('image/jpeg', 0.7));
      };
      img.onerror = () => resolve(null);
      img.src = e.target.result;
    };
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}

export {
  FILE_CATEGORIES,
  API_BASE,
  categorizeFile,
  formatFileSize,
  formatDate,
  createFileMetadata,
  createUrlMetadata,
  createEmbedMetadata,
  createBlobUrl,
  revokeBlobUrl,
  loadLibrary,
  saveLibrary,
  addToLibrary,
  removeFromLibrary,
  updateInLibrary,
  filterByCategory,
  searchLibrary,
  validateFile,
  validateUrl,
  generateThumbnail,
  generateId,
  // ── Server API ──
  loadLibraryFromServer,
  uploadFileToServer,
  saveUrlToServer,
  saveEmbedToServer,
  updateFileOnServer,
  deleteFileOnServer,
  getStatsFromServer,
  trackViewOnServer,
};
