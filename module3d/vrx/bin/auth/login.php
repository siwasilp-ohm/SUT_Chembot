<?php
/**
 * VRX Studio — Login Page
 * Professional dark-theme login with test account panel
 */
require_once __DIR__ . '/session.php';

// Handle login form submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = vrx_login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) {
        $returnTo = $_POST['return'] ?? '/vrx/index.php';
        // Sanitize return URL
        if (strpos($returnTo, '/vrx/') !== 0) $returnTo = '/vrx/index.php';
        header('Location: ' . $returnTo);
        exit;
    }
    $error = $result['message'];
}

// If already logged in, redirect
if (vrx_is_logged_in()) {
    header('Location: /vrx/index.php');
    exit;
}

$returnTo = $_GET['return'] ?? '/vrx/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>VRX Studio — Login</title>
  <link rel="stylesheet" href="/vrx/style/modern.css">
  <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.2/dist/feather.min.js"></script>
  <style>
    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      position: relative;
      overflow: hidden;
    }

    /* Animated background */
    .login-page::before {
      content: '';
      position: absolute;
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(108,92,231,0.15) 0%, transparent 70%);
      top: -200px;
      left: -100px;
      animation: float1 15s ease-in-out infinite;
    }
    .login-page::after {
      content: '';
      position: absolute;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(0,206,201,0.1) 0%, transparent 70%);
      bottom: -150px;
      right: -100px;
      animation: float2 18s ease-in-out infinite;
    }
    @keyframes float1 {
      0%, 100% { transform: translate(0, 0); }
      50% { transform: translate(60px, 40px); }
    }
    @keyframes float2 {
      0%, 100% { transform: translate(0, 0); }
      50% { transform: translate(-40px, -30px); }
    }

    .login-wrapper {
      display: flex;
      gap: 24px;
      max-width: 900px;
      width: 100%;
      position: relative;
      z-index: 1;
    }

    /* Main Login Card */
    .login-card {
      flex: 1.2;
      background: var(--vrx-bg-card);
      border: 1px solid var(--vrx-border);
      border-radius: var(--vrx-radius-lg);
      padding: 40px 36px;
      box-shadow: var(--vrx-shadow-lg);
    }

    .login-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px;
    }
    .login-logo svg { width: 42px; height: 42px; }
    .login-logo span {
      font-size: 1.5rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--vrx-primary-light), var(--vrx-secondary));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .login-subtitle {
      color: var(--vrx-text-muted);
      font-size: 0.88rem;
      margin-bottom: 32px;
    }

    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--vrx-text-secondary);
      margin-bottom: 6px;
      letter-spacing: 0.3px;
    }
    .form-input {
      width: 100%;
      padding: 12px 16px;
      background: var(--vrx-bg);
      border: 1px solid var(--vrx-border);
      border-radius: var(--vrx-radius-sm);
      color: var(--vrx-text);
      font-size: 0.95rem;
      transition: var(--vrx-transition);
    }
    .form-input:focus {
      outline: none;
      border-color: var(--vrx-primary);
      box-shadow: 0 0 0 3px rgba(108,92,231,0.2);
    }
    .form-input::placeholder { color: var(--vrx-text-muted); }

    .login-btn {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, var(--vrx-primary), var(--vrx-primary-dark));
      border: none;
      border-radius: var(--vrx-radius-sm);
      color: #fff;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: var(--vrx-transition);
      margin-top: 8px;
      letter-spacing: 0.5px;
    }
    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(108,92,231,0.4);
    }
    .login-btn:active {
      transform: translateY(0);
    }

    .login-error {
      background: rgba(225,112,85,0.12);
      border: 1px solid rgba(225,112,85,0.3);
      color: var(--vrx-danger);
      padding: 10px 14px;
      border-radius: var(--vrx-radius-sm);
      font-size: 0.84rem;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Test Accounts Panel */
    .test-panel {
      flex: 1;
      background: var(--vrx-bg-secondary);
      border: 1px solid var(--vrx-border);
      border-radius: var(--vrx-radius-lg);
      padding: 32px 28px;
    }

    .test-panel-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--vrx-text);
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .test-panel-desc {
      color: var(--vrx-text-muted);
      font-size: 0.78rem;
      margin-bottom: 20px;
      line-height: 1.5;
    }

    .test-account {
      background: var(--vrx-bg-card);
      border: 1px solid var(--vrx-border);
      border-radius: var(--vrx-radius);
      padding: 16px;
      margin-bottom: 12px;
      cursor: pointer;
      transition: var(--vrx-transition);
      position: relative;
      overflow: hidden;
    }
    .test-account::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      border-radius: 3px 3px 0 0;
    }
    .test-account.role-admin::before { background: linear-gradient(90deg, #6C5CE7, #A29BFE); }
    .test-account.role-user::before  { background: linear-gradient(90deg, #74B9FF, #00CEC9); }
    .test-account.role-viewer::before { background: linear-gradient(90deg, #FDCB6E, #E17055); }

    .test-account:hover {
      border-color: var(--vrx-primary);
      transform: translateY(-2px);
      box-shadow: var(--vrx-glow);
    }

    .test-account-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }
    .test-account-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .test-account.role-admin .test-account-avatar { background: rgba(108,92,231,0.2); }
    .test-account.role-user .test-account-avatar  { background: rgba(116,185,255,0.2); }
    .test-account.role-viewer .test-account-avatar { background: rgba(253,203,110,0.2); }

    .test-account-name {
      font-weight: 700;
      font-size: 0.9rem;
      color: var(--vrx-text);
    }
    .test-account-role {
      font-size: 0.72rem;
      display: inline-block;
    }

    .test-account-creds {
      display: flex;
      gap: 16px;
      font-size: 0.78rem;
      color: var(--vrx-text-secondary);
      margin-bottom: 8px;
    }
    .test-account-creds code {
      background: var(--vrx-bg);
      padding: 2px 8px;
      border-radius: 4px;
      font-family: 'Consolas', monospace;
      font-size: 0.82rem;
      color: var(--vrx-primary-light);
    }

    .test-account-perms {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
    }
    .perm-tag {
      font-size: 0.65rem;
      padding: 2px 7px;
      border-radius: 8px;
      background: var(--vrx-bg-glass);
      color: var(--vrx-text-muted);
      border: 1px solid var(--vrx-border);
    }
    .perm-tag.granted {
      background: rgba(0,184,148,0.12);
      color: var(--vrx-success);
      border-color: rgba(0,184,148,0.3);
    }
    .perm-tag.denied {
      background: rgba(225,112,85,0.08);
      color: var(--vrx-text-muted);
      border-color: transparent;
      text-decoration: line-through;
      opacity: 0.5;
    }

    .test-click-hint {
      font-size: 0.7rem;
      color: var(--vrx-text-muted);
      text-align: center;
      margin-top: 16px;
      opacity: 0.7;
    }

    /* Footer */
    .login-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 0.75rem;
      color: var(--vrx-text-muted);
    }

    /* Responsive */
    @media (max-width: 768px) {
      .login-wrapper {
        flex-direction: column;
      }
      .login-card {
        padding: 28px 24px;
      }
      .test-panel {
        padding: 24px 20px;
      }
    }
  </style>
</head>
<body>
  <div class="login-page">
    <div class="login-wrapper">

      <!-- ── Login Form ── -->
      <div class="login-card">
        <div class="login-logo">
          <svg viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill="url(#lg)"/>
            <path d="M8 22L16 10L24 22H8Z" fill="white" opacity="0.9"/>
            <defs><linearGradient id="lg" x1="0" y1="0" x2="32" y2="32">
              <stop stop-color="#6C5CE7"/><stop offset="1" stop-color="#00CEC9"/>
            </linearGradient></defs>
          </svg>
          <span>VRX Studio</span>
        </div>
        <p class="login-subtitle">Sign in to manage your 3D models, panoramas, and AR experiences.</p>

        <?php if ($error): ?>
          <div class="login-error"><i data-feather="alert-triangle" style="width:14px;height:14px;vertical-align:-2px"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on">
          <input type="hidden" name="return" value="<?= htmlspecialchars($returnTo) ?>">

          <div class="form-group">
            <label for="username">Username</label>
            <input class="form-input" type="text" id="username" name="username"
                   placeholder="Enter your username" required autofocus
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input class="form-input" type="password" id="password" name="password"
                   placeholder="Enter your password" required>
          </div>

          <button type="submit" class="login-btn"><i data-feather="lock" style="width:16px;height:16px;vertical-align:-2px"></i> Sign In</button>
        </form>

        <div class="login-footer">
          VRX Studio &bull; Built with Three.js &amp; Vue.js
        </div>
      </div>

      <!-- ── Test Accounts Panel ── -->
      <div class="test-panel">
        <div class="test-panel-title">🧪 Demo Accounts</div>
        <p class="test-panel-desc">
          Click any account below to auto-fill credentials and test different permission levels.
        </p>

        <!-- Admin -->
        <div class="test-account role-admin" onclick="fillLogin('admin1','123')">
          <div class="test-account-header">
            <div class="test-account-avatar"><i data-feather="shield" style="width:24px;height:24px"></i></div>
            <div>
              <div class="test-account-name">Admin User</div>
              <span class="test-account-role" style="background:rgba(108,92,231,0.8);color:#fff;padding:2px 8px;border-radius:10px;">Admin</span>
            </div>
          </div>
          <div class="test-account-creds">
            <span>User: <code>admin1</code></span>
            <span>Pass: <code>123</code></span>
          </div>
          <div class="test-account-perms">
            <span class="perm-tag granted">✓ View</span>
            <span class="perm-tag granted">✓ Upload</span>
            <span class="perm-tag granted">✓ Edit All</span>
            <span class="perm-tag granted">✓ Delete All</span>
            <span class="perm-tag granted">✓ Users</span>
            <span class="perm-tag granted">✓ QR/AR</span>
          </div>
        </div>

        <!-- User -->
        <div class="test-account role-user" onclick="fillLogin('user1','123')">
          <div class="test-account-header">
            <div class="test-account-avatar"><i data-feather="user" style="width:24px;height:24px"></i></div>
            <div>
              <div class="test-account-name">Regular User</div>
              <span class="test-account-role" style="background:rgba(116,185,255,0.8);color:#fff;padding:2px 8px;border-radius:10px;">User</span>
            </div>
          </div>
          <div class="test-account-creds">
            <span>User: <code>user1</code></span>
            <span>Pass: <code>123</code></span>
          </div>
          <div class="test-account-perms">
            <span class="perm-tag granted">✓ View</span>
            <span class="perm-tag granted">✓ Upload</span>
            <span class="perm-tag granted">✓ Edit Own</span>
            <span class="perm-tag granted">✓ Delete Own</span>
            <span class="perm-tag denied">✗ Users</span>
            <span class="perm-tag granted">✓ QR/AR</span>
          </div>
        </div>

        <!-- Viewer -->
        <div class="test-account role-viewer" onclick="fillLogin('view1','123')">
          <div class="test-account-header">
            <div class="test-account-avatar"><i data-feather="eye" style="width:24px;height:24px"></i></div>
            <div>
              <div class="test-account-name">Viewer</div>
              <span class="test-account-role" style="background:rgba(253,203,110,0.8);color:#1a1a2e;padding:2px 8px;border-radius:10px;">Viewer</span>
            </div>
          </div>
          <div class="test-account-creds">
            <span>User: <code>view1</code></span>
            <span>Pass: <code>123</code></span>
          </div>
          <div class="test-account-perms">
            <span class="perm-tag granted">✓ View</span>
            <span class="perm-tag denied">✗ Upload</span>
            <span class="perm-tag denied">✗ Edit</span>
            <span class="perm-tag denied">✗ Delete</span>
            <span class="perm-tag denied">✗ Users</span>
            <span class="perm-tag granted">✓ AR</span>
          </div>
        </div>

        <div class="test-click-hint"><i data-feather="info" style="width:14px;height:14px;vertical-align:-2px"></i> Click a card to auto-fill the login form</div>
      </div>

    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() { if (typeof feather !== 'undefined') feather.replace(); });
    function fillLogin(user, pass) {
      document.getElementById('username').value = user;
      document.getElementById('password').value = pass;
      document.getElementById('username').focus();
      // Visual feedback
      document.querySelector('.login-btn').style.boxShadow = '0 0 20px rgba(108,92,231,0.5)';
      setTimeout(function() {
        document.querySelector('.login-btn').style.boxShadow = '';
      }, 600);
    }
  </script>
</body>
</html>
