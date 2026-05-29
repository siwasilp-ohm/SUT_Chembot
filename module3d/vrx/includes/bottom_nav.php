<?php if (!defined('BASE_URL')) { require_once __DIR__ . '/../core/config.php'; } ?>
<nav class="bottom-nav">
  <a href="<?= BASE_URL ?>/">
    <i data-feather="home"></i>
    <span>หน้าแรก</span>
  </a>
  <a href="<?= BASE_URL ?>/pages/upload.php">
    <i data-feather="upload-cloud"></i>
    <span>อัปโหลด</span>
  </a>
  <a href="<?= BASE_URL ?>/pages/gallery.php">
    <i data-feather="grid"></i>
    <span>แกลเลอรี</span>
  </a>
  <a href="<?= BASE_URL ?>/pages/qr.php">
    <i data-feather="maximize"></i>
    <span>QR</span>
  </a>
  <a href="<?= BASE_URL ?>/pages/scanner.php">
    <i data-feather="camera"></i>
    <span>สแกน</span>
  </a>
  <a href="<?= BASE_URL ?>/pages/report.php">
    <i data-feather="file-text"></i>
    <span>รายงาน</span>
  </a>
  <?php if (is_admin()): ?>
  <a href="<?= BASE_URL ?>/pages/admin.php">
    <i data-feather="settings"></i>
    <span>ตั้งค่า</span>
  </a>
  <?php endif; ?>
</nav>
