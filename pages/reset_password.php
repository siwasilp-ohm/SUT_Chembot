<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/i18n.php';

$token = trim($_GET['token'] ?? '');
$lang  = I18n::getCurrentLang();
$TH    = ($lang === 'th');

// Validate token on load
$valid = false;
$userName = '';
if ($token) {
    $u = Database::fetch(
        "SELECT first_name FROM users WHERE reset_token=:t AND reset_token_expires > NOW() AND is_active=1",
        [':t' => $token]
    );
    if ($u) { $valid = true; $userName = $u['first_name']; }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $TH ? 'ตั้งรหัสผ่านใหม่' : 'Reset Password' ?> — SUT ChemBot</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1d4ed8 100%);
  font-family:'Inter','Noto Sans Thai',sans-serif;font-size:14px;padding:16px;
}
.card{
  background:#fff;border-radius:20px;padding:32px 28px;
  width:400px;max-width:94vw;
  box-shadow:0 24px 60px rgba(0,0,0,.35);
}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:24px}
.logo-ic{
  width:44px;height:44px;border-radius:12px;
  background:linear-gradient(135deg,#f97316,#fb923c);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:20px;box-shadow:0 4px 14px rgba(249,115,22,.35);
}
.logo h1{font-size:18px;font-weight:800;color:#1e293b}
.logo p{font-size:10px;color:#94a3b8;margin-top:2px}
h2{font-size:20px;font-weight:800;color:#1e293b;margin-bottom:5px}
.sub{font-size:13px;color:#64748b;margin-bottom:24px;line-height:1.5}
.fg{margin-bottom:14px}
.fg label{display:block;font-size:11px;font-weight:700;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.pw-wrap{position:relative}
.fg input{
  width:100%;padding:12px 42px 12px 14px;border:1.5px solid #e2e8f0;border-radius:10px;
  font-size:13px;color:#1e293b;font-family:inherit;transition:border-color .15s;
}
.fg input:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12)}
.pw-eye{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:#94a3b8;font-size:14px;
  padding:2px;transition:color .15s;
}
.pw-eye:hover{color:#475569}
.strength-bar{height:4px;background:#e2e8f0;border-radius:4px;margin-top:6px;overflow:hidden}
.strength-fill{height:100%;border-radius:4px;transition:width .3s,background .3s}
.strength-txt{font-size:10px;margin-top:4px;font-weight:600}
.btn{
  width:100%;padding:13px;background:#f97316;color:#fff;border:none;
  border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;
  font-family:inherit;transition:background .15s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn:hover{background:#ea580c}
.btn:disabled{opacity:.55;pointer-events:none}
.msg{
  padding:12px 14px;border-radius:10px;font-size:13px;
  display:flex;align-items:flex-start;gap:9px;margin-bottom:16px;
}
.msg-ok {background:#f0fdf4;border:1.5px solid #86efac;color:#14532d}
.msg-err{background:#fef2f2;border:1.5px solid #fca5a5;color:#7f1d1d}
.msg i{flex-shrink:0;margin-top:1px}
.back-link{
  display:flex;align-items:center;gap:7px;margin-top:16px;justify-content:center;
  font-size:12px;color:#94a3b8;text-decoration:none;transition:color .15s;
}
.back-link:hover{color:#1e293b}
/* Invalid/expired state */
.expired-ic{
  width:64px;height:64px;border-radius:20px;
  background:#fef2f2;color:#ef4444;
  display:flex;align-items:center;justify-content:center;
  font-size:26px;margin:0 auto 16px;
}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-ic"><i class="fas fa-flask"></i></div>
    <div>
      <h1>SUT ChemBot</h1>
      <p>Chemical Management System</p>
    </div>
  </div>

  <?php if (!$token || !$valid): ?>
  <!-- Invalid / expired token -->
  <div style="text-align:center;padding:8px 0 16px">
    <div class="expired-ic"><i class="fas fa-link-slash"></i></div>
    <h2 style="margin-bottom:8px"><?= $TH ? 'ลิงก์ไม่ถูกต้อง' : 'Invalid Link' ?></h2>
    <p class="sub" style="text-align:center"><?= $TH
      ? 'ลิงก์หมดอายุหรือถูกใช้ไปแล้ว กรุณาขอ Reset รหัสผ่านใหม่อีกครั้ง'
      : 'This link has expired or already been used. Please request a new reset link.' ?></p>
    <a href="/v1/pages/login.php" class="btn" style="text-decoration:none">
      <i class="fas fa-arrow-left"></i>
      <?= $TH ? 'กลับไปหน้า Login' : 'Back to Login' ?>
    </a>
  </div>

  <?php else: ?>
  <!-- Valid token — show form -->
  <h2><?= $TH ? 'ตั้งรหัสผ่านใหม่' : 'Set New Password' ?></h2>
  <p class="sub">
    <?= $TH
      ? "สวัสดี <strong>{$userName}</strong> — กรุณากรอกรหัสผ่านใหม่"
      : "Hi <strong>{$userName}</strong> — please enter your new password" ?>
  </p>

  <div id="resultMsg" style="display:none"></div>

  <form id="resetForm">
    <input type="hidden" id="resetToken" value="<?= htmlspecialchars($token) ?>">
    <div class="fg">
      <label><?= $TH ? 'รหัสผ่านใหม่' : 'New Password' ?></label>
      <div class="pw-wrap">
        <input type="password" id="newPass" placeholder="••••••••" oninput="checkStrength(this.value)" autocomplete="new-password">
        <button type="button" class="pw-eye" onclick="togglePw('newPass',this)"><i class="fas fa-eye"></i></button>
      </div>
      <div class="strength-bar"><div class="strength-fill" id="sfill"></div></div>
      <div class="strength-txt" id="stxt" style="color:#94a3b8"></div>
    </div>
    <div class="fg">
      <label><?= $TH ? 'ยืนยันรหัสผ่าน' : 'Confirm Password' ?></label>
      <div class="pw-wrap">
        <input type="password" id="confPass" placeholder="••••••••" autocomplete="new-password">
        <button type="button" class="pw-eye" onclick="togglePw('confPass',this)"><i class="fas fa-eye"></i></button>
      </div>
    </div>
    <button type="submit" class="btn" id="resetBtn">
      <i class="fas fa-lock"></i>
      <?= $TH ? 'บันทึกรหัสผ่านใหม่' : 'Save New Password' ?>
    </button>
  </form>
  <?php endif; ?>

  <a href="/v1/pages/login.php" class="back-link">
    <i class="fas fa-arrow-left"></i>
    <?= $TH ? 'กลับไปหน้า Login' : 'Back to Login' ?>
  </a>
</div>

<script>
const TH = <?= $TH ? 'true' : 'false' ?>;

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

function checkStrength(v) {
    const fill = document.getElementById('sfill');
    const txt  = document.getElementById('stxt');
    if (!fill) return;
    let score = 0;
    if (v.length >= 6)  score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const map = [
        { w: 0,    bg: '#e2e8f0', label: '' },
        { w: 20,   bg: '#ef4444', label: TH ? 'อ่อนมาก' : 'Very weak' },
        { w: 40,   bg: '#f97316', label: TH ? 'อ่อน' : 'Weak' },
        { w: 60,   bg: '#eab308', label: TH ? 'ปานกลาง' : 'Fair' },
        { w: 80,   bg: '#22c55e', label: TH ? 'แข็งแรง' : 'Strong' },
        { w: 100,  bg: '#15803d', label: TH ? 'แข็งแรงมาก' : 'Very strong' },
    ];
    const s = map[score] || map[0];
    fill.style.width    = s.w + '%';
    fill.style.background = s.bg;
    txt.textContent     = s.label;
    txt.style.color     = s.bg;
}

document.getElementById('resetForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const pass  = document.getElementById('newPass').value;
    const conf  = document.getElementById('confPass').value;
    const token = document.getElementById('resetToken').value;
    const btn   = document.getElementById('resetBtn');
    const msgEl = document.getElementById('resultMsg');

    msgEl.style.display = 'none';
    if (!pass) {
        showMsg('err', TH ? 'กรุณากรอกรหัสผ่าน' : 'Please enter a password');
        return;
    }
    if (pass !== conf) {
        showMsg('err', TH ? 'รหัสผ่านไม่ตรงกัน' : 'Passwords do not match');
        return;
    }
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${TH ? 'กำลังบันทึก...' : 'Saving...'}`;
    try {
        const r = await fetch('/v1/api/auth.php?action=do_reset_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, password: pass })
        }).then(r => r.json());
        if (!r.success) throw new Error(r.error || 'Error');
        document.getElementById('resetForm').style.display = 'none';
        showMsg('ok', TH
            ? '✓ ตั้งรหัสผ่านใหม่สำเร็จ! กำลังพาไปหน้า Login...'
            : '✓ Password updated! Redirecting to login...');
        setTimeout(() => window.location.href = '/v1/pages/login.php', 2200);
    } catch(e) {
        showMsg('err', e.message);
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-lock"></i> ${TH ? 'บันทึกรหัสผ่านใหม่' : 'Save New Password'}`;
    }
});

function showMsg(type, text) {
    const el = document.getElementById('resultMsg');
    el.className = `msg msg-${type}`;
    el.innerHTML = `<i class="fas fa-${type==='ok'?'check-circle':'exclamation-circle'}"></i><span>${text}</span>`;
    el.style.display = 'flex';
}
</script>
</body>
</html>
