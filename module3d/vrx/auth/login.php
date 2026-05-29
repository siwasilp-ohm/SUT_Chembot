<?php
/**
 * VRX Studio — Login Page
 */
require_once __DIR__ . '/../core/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = db()->prepare("SELECT id, username, display_name, password_hash, role, is_active FROM users WHERE username = :u1 OR email = :u2 LIMIT 1");
        $stmt->execute([':u1' => $username, ':u2' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        } elseif (!$user['is_active']) {
            $error = 'บัญชีถูกระงับ';
        } else {
            // Set session
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role']         = $user['role'];

            // Update last login
            db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")->execute([':id' => $user['id']]);

            // Redirect
            $return = $_GET['return'] ?? BASE_URL . '/';
            header('Location: ' . $return);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VRX Studio — Login</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <svg viewBox="0 0 32 32" fill="none" width="48" height="48">
                <rect width="32" height="32" rx="8" fill="url(#lg)"/>
                <path d="M8 22L16 10L24 22H8Z" fill="#fff" opacity=".9"/>
                <defs><linearGradient id="lg" x1="0" y1="0" x2="32" y2="32"><stop stop-color="#6C5CE7"/><stop offset="1" stop-color="#00CEC9"/></linearGradient></defs>
            </svg>
            <h1>VRX Studio</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="username" autocomplete="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" placeholder="••••••" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">เข้าสู่ระบบ</button>
        </form>

        <div class="auth-demo">
            <p>บัญชีทดสอบ (รหัสผ่าน: <code>123</code>)</p>
            <div class="demo-accounts">
                <button onclick="fillLogin('admin1')" class="btn btn-sm btn-outline">admin1</button>
                <button onclick="fillLogin('user1')" class="btn btn-sm btn-outline">user1</button>
                <button onclick="fillLogin('view1')" class="btn btn-sm btn-outline">view1</button>
            </div>
        </div>
    </div>

    <script>
    function fillLogin(u) {
        document.getElementById('username').value = u;
        document.getElementById('password').value = '123';
    }
    </script>
</body>
</html>
