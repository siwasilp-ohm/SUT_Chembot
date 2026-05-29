<?php
/**
 * VRX Studio — PHP Auth & Session Core
 * Include this file at the top of any PHP page to enable authentication.
 * 
 * Roles:
 *   admin  — Full access: manage all files, users, settings
 *   user   — Upload, manage own files/models
 *   viewer — View-only access, no upload or edit
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ── Database Config ──
define('VRX_DB_HOST', 'localhost');
define('VRX_DB_NAME', 'vrx_studio');
define('VRX_DB_USER', 'root');
define('VRX_DB_PASS', '');

/**
 * Get PDO connection (singleton per request)
 */
function vrx_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . VRX_DB_HOST . ';dbname=' . VRX_DB_NAME . ';charset=utf8mb4',
                VRX_DB_USER,
                VRX_DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed. Ensure MySQL is running and vrx_studio database exists.');
        }
    }
    return $pdo;
}

/**
 * Check if user is logged in
 */
function vrx_is_logged_in() {
    return isset($_SESSION['vrx_user_id']) && $_SESSION['vrx_user_id'] > 0;
}

/**
 * Get current session user data
 */
function vrx_current_user() {
    if (!vrx_is_logged_in()) return null;
    return [
        'id'           => $_SESSION['vrx_user_id'],
        'username'     => $_SESSION['vrx_username'] ?? '',
        'display_name' => $_SESSION['vrx_display_name'] ?? '',
        'email'        => $_SESSION['vrx_email'] ?? '',
        'role'         => $_SESSION['vrx_role'] ?? 'viewer',
        'avatar_url'   => $_SESSION['vrx_avatar_url'] ?? '',
    ];
}

/**
 * Get current user role
 */
function vrx_role() {
    return $_SESSION['vrx_role'] ?? 'viewer';
}

/**
 * Check permission
 */
function vrx_can($action) {
    $role = vrx_role();
    $perms = [
        'admin'  => ['view','upload','edit','edit_own','delete','delete_own','manage_users','settings','qr','ar'],
        'user'   => ['view','upload','edit_own','delete_own','qr','ar'],
        'viewer' => ['view','ar'],
    ];
    $allowed = $perms[$role] ?? $perms['viewer'];
    return in_array($action, $allowed, true);
}

/**
 * Require login — redirect to login page if not authenticated
 */
function vrx_require_login() {
    if (!vrx_is_logged_in()) {
        $returnTo = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: /vrx/auth/login.php?return=' . urlencode($returnTo));
        exit;
    }
}

/**
 * Require a specific role
 */
function vrx_require_role($minRole) {
    vrx_require_login();
    $hierarchy = ['viewer' => 1, 'user' => 2, 'editor' => 3, 'admin' => 4];
    $currentLevel = $hierarchy[vrx_role()] ?? 0;
    $requiredLevel = $hierarchy[$minRole] ?? 0;
    if ($currentLevel < $requiredLevel) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title>
        <link rel="stylesheet" href="/vrx/style/modern.css"></head>
        <body style="display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;">
        <div><h1 style="font-size:3rem;opacity:0.3;"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></h1>
        <h2 style="color:var(--vrx-text);">Access Denied</h2>
        <p style="color:var(--vrx-text-muted);margin:12px 0 20px;">
        You need <strong>' . htmlspecialchars($minRole) . '</strong> role to access this page.<br>
        Current role: <strong>' . htmlspecialchars(vrx_role()) . '</strong></p>
        <a href="/vrx/index.php" style="color:var(--vrx-primary);">← Back to Home</a></div></body></html>';
        exit;
    }
}

/**
 * Login a user by username + password
 * Returns: ['success' => bool, 'message' => string]
 */
function vrx_login($username, $password) {
    $username = trim($username);
    if (!$username || !$password) {
        return ['success' => false, 'message' => 'Username and password are required.'];
    }

    $pdo = vrx_db();
    $stmt = $pdo->prepare('SELECT id, uuid, username, email, password_hash, display_name, avatar_url, role, is_active FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Account is disabled.'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    // Set session
    $_SESSION['vrx_user_id']      = (int)$user['id'];
    $_SESSION['vrx_username']     = $user['username'];
    $_SESSION['vrx_display_name'] = $user['display_name'] ?: $user['username'];
    $_SESSION['vrx_email']        = $user['email'];
    $_SESSION['vrx_role']         = $user['role'];
    $_SESSION['vrx_avatar_url']   = $user['avatar_url'] ?: '';

    // Update login stats
    $pdo->prepare('UPDATE users SET last_login_at = NOW(), login_count = login_count + 1 WHERE id = :id')
        ->execute([':id' => $user['id']]);

    return ['success' => true, 'message' => 'Welcome, ' . ($_SESSION['vrx_display_name']) . '!'];
}

/**
 * Logout
 */
function vrx_logout() {
    $_SESSION = [];
    if (php_sapi_name() !== 'cli' && ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        @setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    // Restart session so subsequent vrx_login() calls work
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
}

/**
 * Role badge HTML helper
 */
function vrx_role_badge($role = null) {
    $role = $role ?: vrx_role();
    $map = [
        'admin'  => ['Admin',   'background:rgba(108,92,231,0.8);color:#fff;'],
        'editor' => ['Editor',  'background:rgba(0,206,201,0.8);color:#fff;'],
        'user'   => ['User',    'background:rgba(116,185,255,0.8);color:#fff;'],
        'viewer' => ['Viewer',  'background:rgba(253,203,110,0.8);color:#1a1a2e;'],
    ];
    $info = $map[$role] ?? $map['viewer'];
    return '<span style="display:inline-block;padding:3px 10px;border-radius:14px;font-size:0.72rem;font-weight:600;' . $info[1] . '">' . $info[0] . '</span>';
}

/**
 * Output JSON for user session state (for JS consumption)
 */
function vrx_session_json() {
    $user = vrx_current_user();
    return json_encode([
        'loggedIn'    => vrx_is_logged_in(),
        'user'        => $user,
        'permissions' => [
            'view'         => vrx_can('view'),
            'upload'       => vrx_can('upload'),
            'edit_own'     => vrx_can('edit_own'),
            'edit'         => vrx_can('edit'),
            'delete_own'   => vrx_can('delete_own'),
            'delete'       => vrx_can('delete'),
            'manage_users' => vrx_can('manage_users'),
            'qr'           => vrx_can('qr'),
            'ar'           => vrx_can('ar'),
        ],
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Shared nav HTML — generates the header with auth-aware nav
 */
function vrx_nav_html($activePage = '') {
    $user = vrx_current_user();
    $loggedIn = vrx_is_logged_in();
    $base = '/vrx';
    
    $links = [
        'home'    => ['href' => "$base/index.php",                                    'label' => 'Home'],
        'upload'  => ['href' => "$base/package_upload/upload/upload.html",             'label' => 'Upload'],
        'gallery' => ['href' => "$base/package_gallery/gallery/gallery.html",          'label' => 'Gallery'],
        'qr'      => ['href' => "$base/package_ar/qr/qr.html",                        'label' => 'QR / AR'],
        'scanner' => ['href' => "$base/package_scanner/scanner/scanner.html",          'label' => 'Scanner'],
    ];

    $html = '<header class="vrx-header">';
    $html .= '<div class="vrx-logo">';
    $html .= '<svg viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="url(#g1)"/>';
    $html .= '<path d="M8 22L16 10L24 22H8Z" fill="white" opacity="0.9"/>';
    $html .= '<defs><linearGradient id="g1" x1="0" y1="0" x2="32" y2="32">';
    $html .= '<stop stop-color="#6C5CE7"/><stop offset="1" stop-color="#00CEC9"/>';
    $html .= '</linearGradient></defs></svg>';
    $html .= '<span>VRX Studio</span></div>';

    $html .= '<nav class="vrx-nav">';
    foreach ($links as $key => $link) {
        $activeClass = ($key === $activePage) ? ' class="active"' : '';
        $html .= '<a href="' . $link['href'] . '"' . $activeClass . '>' . $link['label'] . '</a>';
    }

    if ($loggedIn) {
        $html .= '<span class="vrx-nav-user">';
        $html .= vrx_role_badge();
        $html .= ' <span class="vrx-nav-username">' . htmlspecialchars($user['display_name']) . '</span>';
        $html .= ' <a href="' . $base . '/auth/logout.php" class="vrx-nav-logout" title="Logout"><i data-feather="log-out"></i></a>';
        $html .= '</span>';
    } else {
        $html .= '<a href="' . $base . '/auth/login.php" class="vrx-nav-login"><i data-feather="log-in"></i> Login</a>';
    }

    $html .= '</nav></header>';

    // Mobile Bottom Navigation Bar
    $html .= '<nav class="vrx-bottom-nav">';
    $bottomLinks = [
        'home'    => ['href' => "$base/index.php",                                    'icon' => 'home',       'label' => 'Home'],
        'upload'  => ['href' => "$base/package_upload/upload/upload.html",             'icon' => 'upload-cloud','label' => 'Upload'],
        'gallery' => ['href' => "$base/package_gallery/gallery/gallery.html",          'icon' => 'folder',     'label' => 'Gallery'],
        'qr'      => ['href' => "$base/package_ar/qr/qr.html",                        'icon' => 'smartphone', 'label' => 'QR / AR'],
        'scanner' => ['href' => "$base/package_scanner/scanner/scanner.html",          'icon' => 'camera',     'label' => 'Scanner'],
    ];
    foreach ($bottomLinks as $key => $link) {
        $activeClass = ($key === $activePage) ? ' class="active"' : '';
        $html .= '<a href="' . $link['href'] . '"' . $activeClass . '>';
        $html .= '<i data-feather="' . $link['icon'] . '"></i>';
        $html .= '<span>' . $link['label'] . '</span></a>';
    }
    $html .= '</nav>';

    return $html;
}
