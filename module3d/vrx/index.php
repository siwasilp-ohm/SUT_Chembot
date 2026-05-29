<?php
require_once __DIR__ . '/core/config.php';
if (!is_logged_in()) { header('Location: ' . BASE_URL . '/auth/login.php'); exit; }
$user = auth_user();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>VRX Studio</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-content">
<div class="container" id="app" v-cloak>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-value">{{ stats.total_files || 0 }}</div>
      <div class="stat-label">ไฟล์ทั้งหมด</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ stats.models || 0 }}</div>
      <div class="stat-label">โมเดล 3D</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ stats.panoramas || 0 }}</div>
      <div class="stat-label">พาโนรามา</div>
    </div>
    <div class="stat-card">
      <div class="stat-value">{{ stats.images || 0 }}</div>
      <div class="stat-label">รูปภาพ</div>
    </div>
  </div>

  <!-- Quick Actions -->
  <h2 style="font-size:1.1rem; margin-bottom:16px;">⚡ เมนูลัด</h2>
  <div class="file-grid" style="margin-bottom:32px;">
    <a href="<?= BASE_URL ?>/pages/upload.php" class="card" style="text-align:center; display:flex; flex-direction:column; align-items:center; gap:10px; padding:32px 20px;">
      <div style="width:50px; height:50px; border-radius:50%; background:rgba(108,92,231,.15); display:flex; align-items:center; justify-content:center;">
        <i data-feather="upload-cloud" style="width:24px; height:24px; color:var(--primary);"></i>
      </div>
      <span style="font-weight:600;">อัปโหลดไฟล์</span>
      <span style="font-size:.8rem; color:var(--text-secondary);">อัปโหลด 3D, รูปภาพ, พาโนรามา</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/gallery.php" class="card" style="text-align:center; display:flex; flex-direction:column; align-items:center; gap:10px; padding:32px 20px;">
      <div style="width:50px; height:50px; border-radius:50%; background:rgba(0,206,201,.15); display:flex; align-items:center; justify-content:center;">
        <i data-feather="grid" style="width:24px; height:24px; color:var(--accent);"></i>
      </div>
      <span style="font-weight:600;">แกลเลอรี</span>
      <span style="font-size:.8rem; color:var(--text-secondary);">จัดการไฟล์ทั้งหมด</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/viewer.php?src=<?= BASE_URL ?>/assets/robot.glb" class="card" style="text-align:center; display:flex; flex-direction:column; align-items:center; gap:10px; padding:32px 20px;">
      <div style="width:50px; height:50px; border-radius:50%; background:rgba(0,184,148,.15); display:flex; align-items:center; justify-content:center;">
        <i data-feather="box" style="width:24px; height:24px; color:var(--success);"></i>
      </div>
      <span style="font-weight:600;">3D Viewer</span>
      <span style="font-size:.8rem; color:var(--text-secondary);">ดูโมเดลตัวอย่าง</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/qr.php" class="card" style="text-align:center; display:flex; flex-direction:column; align-items:center; gap:10px; padding:32px 20px;">
      <div style="width:50px; height:50px; border-radius:50%; background:rgba(253,121,168,.15); display:flex; align-items:center; justify-content:center;">
        <i data-feather="maximize" style="width:24px; height:24px; color:var(--pink);"></i>
      </div>
      <span style="font-weight:600;">QR Code</span>
      <span style="font-size:.8rem; color:var(--text-secondary);">สร้าง QR สำหรับ AR</span>
    </a>
  </div>

  <!-- Recent Files -->
  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
    <h2 style="font-size:1.1rem;">🕐 ไฟล์ล่าสุด</h2>
    <a href="<?= BASE_URL ?>/pages/gallery.php" class="btn btn-outline btn-sm">ดูทั้งหมด</a>
  </div>
  <div class="file-grid" v-if="recent.length">
    <div class="file-card" v-for="f in recent" :key="f.id">
      <div class="file-card-preview" @click="openFile(f)">
        <img v-if="f.thumbnail_url" :src="f.thumbnail_url" :alt="f.title">
        <div v-else class="icon-placeholder">
          <i :data-feather="catIcon(f.category_slug)"></i>
        </div>
        <span class="file-card-badge">
          <span class="badge" :class="'badge-'+f.category_slug">{{ f.category_slug }}</span>
        </span>
      </div>
      <div class="file-card-body">
        <div class="file-card-name">{{ f.title }}</div>
        <div class="file-card-meta">
          <span><i data-feather="user" style="width:12px;height:12px;"></i> {{ f.uploader }}</span>
          <span>{{ timeAgo(f.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
  <div class="empty-state" v-else>
    <i data-feather="inbox"></i>
    <h3>ยังไม่มีไฟล์</h3>
    <p>เริ่มอัปโหลดไฟล์แรกของคุณ</p>
    <a href="<?= BASE_URL ?>/pages/upload.php" class="btn btn-primary mt-2">
      <i data-feather="upload-cloud"></i> อัปโหลดเลย
    </a>
  </div>
</div>
</div>

<?php include __DIR__ . '/includes/bottom_nav.php'; ?>

<script src="<?= BASE_URL ?>/third_party/vue.min.js"></script>
<script>
const BASE = '<?= BASE_URL ?>';
new Vue({
  el: '#app',
  data: function() {
    return {
      stats: {},
      recent: []
    };
  },
  created: function() { this.load(); },
  methods: {
    load: function() {
      var vm = this;
      fetch(BASE + '/api/index.php?action=stats')
        .then(function(r){ return r.json(); })
        .then(function(d){ vm.stats = d.data || {}; });
      fetch(BASE + '/api/index.php?action=files&limit=8&sort=newest')
        .then(function(r){ return r.json(); })
        .then(function(d){ vm.recent = (d.data && d.data.files) || []; })
        .then(function(){ vm.$nextTick(function(){ feather.replace(); }); });
    },
    catIcon: function(slug) {
      var map = { model:'box', panorama:'globe', image:'image', embed:'code', video:'film', document:'file-text' };
      return map[slug] || 'file';
    },
    openFile: function(f) {
      if (f.category_slug === 'model') {
        window.location.href = BASE + '/pages/viewer.php?src=' + encodeURIComponent(f.file_url);
      } else if (f.category_slug === 'panorama') {
        window.location.href = BASE + '/pages/panorama.php?src=' + encodeURIComponent(f.file_url);
      } else if (f.file_url) {
        window.open(f.file_url, '_blank');
      }
    },
    timeAgo: function(d) {
      if (!d) return '';
      var s = Math.floor((Date.now() - new Date(d).getTime()) / 1000);
      if (s < 60) return 'เมื่อสักครู่';
      if (s < 3600) return Math.floor(s/60) + ' นาทีที่แล้ว';
      if (s < 86400) return Math.floor(s/3600) + ' ชม.ที่แล้ว';
      return Math.floor(s/86400) + ' วันที่แล้ว';
    }
  }
});
feather.replace();
</script>
</body>
</html>
