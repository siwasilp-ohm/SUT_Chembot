<?php require_once __DIR__ . '/auth/session.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>VRX Studio — 3D &amp; Panorama Viewer</title>
  <link rel="stylesheet" href="style/modern.css">
  <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
  <style>
    .hero {
      text-align: center;
      padding: 60px 20px 40px;
    }
    .hero h1 {
      font-size: 2.5rem;
      font-weight: 800;
      background: linear-gradient(135deg, #A29BFE, #00CEC9, #FD79A8);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 12px;
    }
    .hero p {
      color: var(--vrx-text-secondary);
      font-size: 1.05rem;
      max-width: 500px;
      margin: 0 auto;
    }
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      margin-top: 40px;
    }
    .feature-card {
      position: relative;
      overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      border-radius: 3px 3px 0 0;
    }
    .feature-card.card-model::before { background: linear-gradient(90deg, #6C5CE7, #A29BFE); }
    .feature-card.card-panorama::before { background: linear-gradient(90deg, #00CEC9, #74B9FF); }
    .feature-card.card-upload::before { background: linear-gradient(90deg, #FD79A8, #FDCB6E); }
    .feature-card.card-ar::before { background: linear-gradient(90deg, #E17055, #6C5CE7); }
    .feature-card.card-gallery::before { background: linear-gradient(90deg, #A29BFE, #00CEC9); }
    .feature-list {
      list-style: none;
      padding: 0;
      margin: 0 0 20px;
    }
    .feature-list li {
      padding: 4px 0;
      font-size: 0.85rem;
      color: var(--vrx-text-secondary);
    }
    .feature-list li::before {
      content: '✓ ';
      color: var(--vrx-success);
      font-weight: bold;
    }
    .footer {
      text-align: center;
      padding: 40px 20px;
      color: var(--vrx-text-muted);
      font-size: 0.8rem;
    }
    /* Auth banner */
    .auth-banner {
      text-align: center;
      padding: 12px;
      margin-bottom: -20px;
    }
    .auth-banner-inner {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      background: var(--vrx-bg-card);
      border: 1px solid var(--vrx-border);
      border-radius: var(--vrx-radius);
      padding: 10px 20px;
    }
    .auth-banner .welcome-text {
      font-size: 0.88rem;
      color: var(--vrx-text);
    }
    .auth-banner .vrx-btn-sm {
      padding: 6px 14px;
      font-size: 0.78rem;
      border-radius: var(--vrx-radius-sm);
    }
    /* Nav user section */
    .vrx-nav-user {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-left: 8px;
      padding-left: 12px;
      border-left: 1px solid var(--vrx-border);
    }
    .vrx-nav-username {
      font-size: 0.82rem;
      color: var(--vrx-text);
      font-weight: 500;
    }
    .vrx-nav-logout {
      font-size: 1.1rem;
      text-decoration: none;
      opacity: 0.6;
      transition: opacity 0.2s;
    }
    .vrx-nav-logout:hover { opacity: 1; }
    .vrx-card-icon svg { width: 28px; height: 28px; stroke-width: 2; }
    .vrx-btn svg { width: 16px; height: 16px; vertical-align: -3px; margin-right: 4px; stroke-width: 2; }
    @media (max-width: 768px) {
      .hero h1 { font-size: 1.8rem; }
      .hero { padding: 40px 16px 24px; }
      .cards-grid { grid-template-columns: 1fr; }
      .vrx-nav-user { display: none; }
    }
  </style>
</head>

<body>
  <!-- Header -->
  <?= vrx_nav_html('home') ?>

  <div class="vrx-container">

    <?php if (vrx_is_logged_in()): ?>
    <div class="auth-banner">
      <div class="auth-banner-inner">
        <span class="welcome-text">
          Welcome back, <strong><?= htmlspecialchars(vrx_current_user()['display_name']) ?></strong>
        </span>
        <?= vrx_role_badge() ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <div class="hero">
      <h1>VRX Studio</h1>
      <p>Experience immersive 3D models and 360° panoramas right in your browser. Upload, manage, and view with ease.</p>
    </div>

    <!-- Feature Cards -->
    <div class="cards-grid">
      <!-- 3D Viewer -->
      <div class="vrx-card feature-card card-model">
        <div class="vrx-card-icon model"><i data-feather="box"></i></div>
        <h3>3D Model Viewer</h3>
        <p>Load and interact with GLB/GLTF 3D models with real-time rendering.</p>
        <ul class="feature-list">
          <li>Touch &amp; mouse rotation</li>
          <li>Device orientation control</li>
          <li>Camera pass-through (AR)</li>
          <li>Custom model upload</li>
        </ul>
        <a href="package_3d_viewer/camera/camera.html" class="vrx-btn vrx-btn-primary vrx-btn-block">
          <i data-feather="box"></i> Open 3D Viewer
        </a>
      </div>

      <!-- Panorama Viewer -->
      <div class="vrx-card feature-card card-panorama">
        <div class="vrx-card-icon panorama"><i data-feather="globe"></i></div>
        <h3>Panorama Viewer</h3>
        <p>View 360° panoramic images with full spherical projection.</p>
        <ul class="feature-list">
          <li>Pinch-to-zoom support</li>
          <li>Mouse wheel zoom</li>
          <li>Device orientation control</li>
          <li>Custom panorama upload</li>
        </ul>
        <a href="package_panorama/photo/photo.html" class="vrx-btn vrx-btn-primary vrx-btn-block">
          <i data-feather="globe"></i> Open Panorama Viewer
        </a>
      </div>

      <!-- Upload & Manage -->
      <?php if (vrx_can('upload')): ?>
      <div class="vrx-card feature-card card-upload">
        <div class="vrx-card-icon upload"><i data-feather="upload-cloud"></i></div>
        <h3>Upload &amp; Manage</h3>
        <p>Upload files or import from URL with full metadata management.</p>
        <ul class="feature-list">
          <li>Drag &amp; drop upload</li>
          <li>Import from URL</li>
          <li>File categorization</li>
          <li>Metadata &amp; descriptions</li>
        </ul>
        <a href="package_upload/upload/upload.html" class="vrx-btn vrx-btn-primary vrx-btn-block">
          <i data-feather="upload-cloud"></i> Upload Files
        </a>
      </div>
      <?php endif; ?>

      <!-- QR / AR -->
      <div class="vrx-card feature-card card-ar">
        <div class="vrx-card-icon ar"><i data-feather="smartphone"></i></div>
        <h3>QR Code &amp; AR</h3>
        <p>Generate QR codes for your 3D models and view them in AR by scanning.</p>
        <ul class="feature-list">
          <li>QR code generation</li>
          <li>AR 3D model viewing</li>
          <li>Share via QR scan</li>
          <li>Camera pass-through AR</li>
        </ul>
        <a href="package_ar/qr/qr.html" class="vrx-btn vrx-btn-primary vrx-btn-block">
          <i data-feather="smartphone"></i> Open QR / AR
        </a>
      </div>

      <!-- QR Scanner -->
      <div class="vrx-card feature-card" style="border-top:3px solid #00CEC9;">
        <div class="vrx-card-icon" style="background:rgba(0,206,201,0.12);color:#00CEC9;"><i data-feather="camera"></i></div>
        <h3>QR Scanner</h3>
        <p>Scan QR codes from VRX system and access content with role-based controls.</p>
        <ul class="feature-list">
          <li>Camera &amp; image scanning</li>
          <li>Role-based actions</li>
          <li>Scan history tracking</li>
          <li>One-tap AR / 3D view</li>
        </ul>
        <a href="package_scanner/scanner/scanner.html" class="vrx-btn vrx-btn-primary vrx-btn-block">
          <i data-feather="camera"></i> Open Scanner
        </a>
      </div>

      <!-- File Library / Gallery -->
      <div class="vrx-card feature-card card-gallery">
        <div class="vrx-card-icon gallery"><i data-feather="folder"></i></div>
        <h3>File Library</h3>
        <p>Browse all your uploaded assets with multiple view modes and inline preview.</p>
        <ul class="feature-list">
          <li>Grid, masonry &amp; list views</li>
          <li>Search, filter &amp; sort</li>
          <li>Inline 3D &amp; image preview</li>
          <li>Detail lightbox panel</li>
        </ul>
        <a href="package_gallery/gallery/gallery.html" class="vrx-btn vrx-btn-primary vrx-btn-block">
          <i data-feather="folder"></i> Open Gallery
        </a>
      </div>

      <!-- Login / Auth card (if not logged in) -->
      <?php if (!vrx_is_logged_in()): ?>
      <div class="vrx-card feature-card" style="border-top:3px solid var(--vrx-primary);">
        <div class="vrx-card-icon"><i data-feather="lock"></i></div>
        <h3>Sign In</h3>
        <p>Login to unlock upload, editing, and full management features.</p>
        <ul class="feature-list">
          <li>3 demo accounts ready</li>
          <li>Role-based access control</li>
          <li>Admin / User / Viewer roles</li>
        </ul>
        <a href="auth/login.php" class="vrx-btn vrx-btn-primary vrx-btn-block">
          <i data-feather="lock"></i> Login
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
      VRX Studio — Built with Three.js &amp; Vue.js
    </div>
  </div>

  <!-- Inject session state for JS pages -->
  <script>
    window.VRX_SESSION = <?= vrx_session_json() ?>;
  </script>
  <script>
    // Replace <i data-feather="..."> with inline SVGs
    if (typeof feather !== 'undefined') feather.replace();
  </script>
</body>

</html>
