<?php
/**
 * Authentication & Authorization System
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    private static ?array $currentUser = null;
    
    /**
     * Read a single setting from system_settings table
     */
    public static function getSystemSetting(string $key, string $default = ''): string {
        try {
            $row = Database::fetch(
                "SELECT setting_value FROM system_settings WHERE setting_key = :key",
                [':key' => $key]
            );
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Authenticate user with username/email and password
     */
    public static function login(string $username, string $password, bool $remember = false): array {
        $user = Database::fetch(
            "SELECT u.*, r.name as role_name, r.display_name as role_display, r.permissions, r.level 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE (u.username = :login OR u.email = :login) AND u.is_active = 1",
            [':login' => $username]
        );
        
        if (!$user) {
            throw new Exception('Invalid credentials');
        }
        
        // Read lockout settings from system_settings
        $lockoutEnabled = self::getSystemSetting('account_lockout_enabled', '1') === '1';
        $maxAttempts = (int) self::getSystemSetting('account_lockout_max_attempts', '5');
        $lockDuration = (int) self::getSystemSetting('account_lockout_duration', '30');
        
        // Check if account is locked (only if lockout is enabled)
        if ($lockoutEnabled && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            throw new Exception('Account is temporarily locked. Please try again later.');
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            if ($lockoutEnabled) {
                // Increment login attempts
                Database::update('users', 
                    ['login_attempts' => $user['login_attempts'] + 1],
                    'id = :id',
                    [':id' => $user['id']]
                );
                
                // Lock account after max failed attempts
                if ($user['login_attempts'] + 1 >= $maxAttempts) {
                    Database::update('users',
                        ['locked_until' => date('Y-m-d H:i:s', strtotime("+{$lockDuration} minutes"))],
                        'id = :id',
                        [':id' => $user['id']]
                    );
                }
            }
            
            throw new Exception('Invalid credentials');
        }
        
        // Reset login attempts and update last login
        Database::update('users', [
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $user['id']]);
        
        // Generate JWT token
        $token = self::generateToken($user, $remember);
        
        // Create session
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = $remember ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Sanitize IP address and User-Agent for security
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Validate and sanitize IP - handle both IPv4 and IPv6
        if ($ipAddress) {
            // Check for IPv4 first
            if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // IPv4 is fine
            } elseif (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // IPv6 addresses can be up to 45 characters
                // Ensure database column can handle this (VARCHAR(45) recommended)
                if (strlen($ipAddress) > 45) {
                    // Truncate IPv6 to fit (not ideal, but prevents DB errors)
                    $ipAddress = substr($ipAddress, 0, 45);
                }
            } else {
                // Invalid IP format
                $ipAddress = null;
            }
        }
        
        // Sanitize User-Agent (just limit length - no HTML encoding for DB storage)
        if ($userAgent) {
            $userAgent = mb_substr($userAgent, 0, 500);
            // Do NOT htmlspecialchars here - that corrupts data for database storage
        }
        
        Database::insert('user_sessions', [
            'user_id' => $user['id'],
            'session_token' => $sessionToken,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt
        ]);
        
        // Set cookies
        $cookieExpiry = $remember ? time() + (30 * 24 * 60 * 60) : 0;
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('auth_token', $token, [
            'expires' => $cookieExpiry,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        setcookie('session_token', $sessionToken, [
            'expires' => $cookieExpiry,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        return [
            'user' => self::sanitizeUserData($user),
            'token' => $token,
            'session_token' => $sessionToken
        ];
    }
    
    /**
     * Generate JWT token
     */
    public static function generateToken(array $user, bool $longLived = false): string {
        $issuedAt = time();
        $expiration = $longLived ? $issuedAt + (30 * 24 * 60 * 60) : $issuedAt + (24 * 60 * 60);
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiration,
            'sub' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role_name'],
            'role_level' => $user['level'],
            'org_id' => $user['organization_id']
        ];
        
        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }
    
    /**
     * Verify and decode JWT token
     */
    public static function verifyToken(string $token): ?array {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get current authenticated user
     */
    public static function getCurrentUser(): ?array {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }
        
        $token = $_COOKIE['auth_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        
        if (!$token) {
            return null;
        }
        
        // Remove "Bearer " prefix if present
        $token = str_replace('Bearer ', '', $token);
        
        $decoded = self::verifyToken($token);
        if (!$decoded) {
            return null;
        }
        
        $user = Database::fetch(
            "SELECT u.*, r.name as role_name, r.display_name as role_display, r.permissions, r.level,
                    o.name as org_name, l.name as lab_name,
                    d.name as department_name
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             LEFT JOIN organizations o ON u.organization_id = o.id
             LEFT JOIN labs l ON u.lab_id = l.id
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.id = :id AND u.is_active = 1",
            [':id' => $decoded['sub']]
        );
        
        if ($user) {
            $user['permissions'] = json_decode($user['permissions'], true);
            self::$currentUser = $user;
        }
        
        return $user;
    }
    
    /**
     * Check if user is authenticated
     */
    public static function check(): bool {
        return self::getCurrentUser() !== null;
    }
    
    /**
     * Check if user has specific permission
     */
    public static function can(string $permission, ?int $resourceId = null): bool {
        $user = self::getCurrentUser();
        if (!$user) return false;
        
        // Admin has all permissions
        if ($user['role_name'] === 'admin') return true;
        
        $permissions = $user['permissions'] ?? [];
        
        // Check specific permission
        $parts = explode('.', $permission);
        $current = $permissions;
        foreach ($parts as $part) {
            if (isset($current[$part])) {
                $current = $current[$part];
            } else {
                return false;
            }
        }
        
        return $current === true || $current === 1;
    }
    
    /**
     * Require authentication
     */
    public static function requireAuth(): array {
        $user = self::getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        return $user;
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission(string $permission, ?int $resourceId = null): void {
        if (!self::can($permission, $resourceId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
    }
    
    /**
     * Logout user
     */
    public static function logout(): void {
        $sessionToken = $_COOKIE['session_token'] ?? null;
        if ($sessionToken) {
            Database::delete('user_sessions', 'session_token = :token', [':token' => $sessionToken]);
        }
        
        setcookie('auth_token', '', ['expires' => 1, 'path' => '/', 'samesite' => 'Lax']);
        setcookie('session_token', '', ['expires' => 1, 'path' => '/', 'samesite' => 'Lax']);
        
        self::$currentUser = null;
    }
    
    /**
     * Register new user
     */
    public static function register(array $data): array {
        // Validate required fields
        $required = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("{$field} is required");
            }
        }

        // Resolve room_id (from rooms table — primary lab room)
        $roomId = !empty($data['room_id']) ? (int)$data['room_id'] : null;
        if (!$roomId) {
            throw new Exception('Please select a laboratory room');
        }
        $roomRow = Database::fetch(
            "SELECT r.id, r.building_id FROM rooms r WHERE r.id = :id",
            [':id' => $roomId]
        );
        if (!$roomRow) throw new Exception('Selected room is invalid');

        // Check if username exists
        $existing = Database::fetch("SELECT id FROM users WHERE username = :u", [':u' => $data['username']]);
        if ($existing) {
            throw new Exception('Username already exists');
        }

        // Check if email exists
        $existing = Database::fetch("SELECT id FROM users WHERE email = :e", [':e' => $data['email']]);
        if ($existing) {
            throw new Exception('Email already exists');
        }

        // Get default role (User)
        $role = Database::fetch("SELECT id FROM roles WHERE name = 'user'");
        if (!$role) {
            throw new Exception('Default role not found');
        }

        Database::beginTransaction();
        try {
            $userId = Database::insert('users', [
                'organization_id' => $data['organization_id'] ?? 1,
                'role_id'         => $data['role_id'] ?? $role['id'],
                'room_id'         => $roomId,
                'manager_id'      => $data['manager_id'] ?? null,
                'username'        => $data['username'],
                'email'           => $data['email'],
                'password_hash'   => password_hash($data['password'], PASSWORD_DEFAULT),
                'first_name'      => $data['first_name'],
                'last_name'       => $data['last_name'],
                'phone'           => $data['phone'] ?? null,
                'department'      => $data['department'] ?? null,
                'position'        => $data['position'] ?? null,
                'theme_preference'=> $data['theme'] ?? DEFAULT_THEME,
                'language'        => $data['language'] ?? DEFAULT_LANGUAGE
            ]);

            Database::insert('notification_settings', ['user_id' => $userId]);

            Database::commit();
            return ['user_id' => $userId, 'message' => 'Registration successful. Awaiting approval from lab manager.'];
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    private static function normalizeLabIds(array $data): array {
        $raw = $data['lab_ids'] ?? null;
        $labIds = [];

        if (is_array($raw)) {
            $labIds = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $labIds = $json;
            } else {
                $labIds = preg_split('/\s*,\s*/', trim($raw));
            }
        }

        if (!empty($data['lab_id'])) {
            $labIds[] = $data['lab_id'];
        }

        $labIds = array_map('intval', $labIds);
        $labIds = array_values(array_unique(array_filter($labIds, function ($id) {
            return $id > 0;
        })));

        return $labIds;
    }

    private static function ensureUserLabAccessTable(): void {
        Database::query(
            "CREATE TABLE IF NOT EXISTS user_lab_access (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                lab_id INT NOT NULL,
                is_primary TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_lab (user_id, lab_id),
                KEY idx_ula_lab_id (lab_id),
                KEY idx_ula_user_primary (user_id, is_primary),
                CONSTRAINT fk_ula_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_ula_lab FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    
    /**
     * Sanitize user data for output
     */
    private static function sanitizeUserData(array $user): array {
        unset($user['password_hash']);
        unset($user['api_token']);
        unset($user['login_attempts']);
        unset($user['locked_until']);
        
        $user['permissions'] = json_decode($user['permissions'], true);
        
        return $user;
    }
}
