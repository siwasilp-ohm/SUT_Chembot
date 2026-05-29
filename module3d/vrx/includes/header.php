<?php if (!defined('BASE_URL')) { require_once __DIR__ . '/../core/config.php'; } ?>
<header class="header">
  <a href="<?= BASE_URL ?>/" class="header-logo">
    <svg viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#6C5CE7"/><text x="5" y="23" font-size="16" font-weight="bold" fill="#fff">VR</text></svg>
    <span>VRX Studio</span>
  </a>
  <nav class="header-nav">
    <a href="<?= BASE_URL ?>/">หน้าแรก</a>
    <a href="<?= BASE_URL ?>/pages/upload.php">อัปโหลด</a>
    <a href="<?= BASE_URL ?>/pages/gallery.php">แกลเลอรี</a>
    <a href="<?= BASE_URL ?>/pages/qr.php">QR Code</a>
    <a href="<?= BASE_URL ?>/pages/scanner.php">สแกน</a>
    <a href="<?= BASE_URL ?>/pages/report.php">รายงาน</a>
    <?php if (is_admin()): ?>
    <a href="<?= BASE_URL ?>/pages/admin.php" style="color:var(--warning);">⚙ ตั้งค่า</a>
    <?php endif; ?>
  </nav>
  <?php if (is_logged_in()): $u = auth_user(); ?>
  <div class="header-user">
    <span>สวัสดี, <strong><?= htmlspecialchars($u['display_name']) ?></strong></span>
    <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline btn-sm">ออก</a>
  </div>
  <?php endif; ?>
</header>
