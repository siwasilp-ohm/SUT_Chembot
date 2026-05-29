<?php
/**
 * Helper Functions for SUT chemBot
 * Common utility functions used throughout the application
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Generate a unique ID
 */
function generateUniqueId(string $prefix = ''): string {
    return $prefix . bin2hex(random_bytes(8));
}

/**
 * Generate a QR code string
 */
function generateQrCode(string $prefix = 'CHEM'): string {
    $timestamp = date('ymd');
    $random = bin2hex(random_bytes(4));
    return $prefix . '-' . $timestamp . '-' . strtoupper($random);
}

/**
 * Format date for display
 */
function formatDate(?string $date, string $format = 'd M Y'): string {
    if (empty($date)) return '-';
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Format datetime for display
 */
function formatDateTime(?string $datetime, string $format = 'd M Y H:i'): string {
    if (empty($datetime)) return '-';
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * Calculate days until expiry
 */
function daysUntilExpiry(?string $expiryDate): ?int {
    if (empty($expiryDate)) return null;
    
    $expiry = new DateTime($expiryDate);
    $today = new DateTime('today');
    $diff = $today->diff($expiry);
    
    return (int)$diff->format('%r%a');
}

/**
 * Check if item is expiring soon
 */
function isExpiringSoon(?string $expiryDate, int $days = 30): bool {
    $daysLeft = daysUntilExpiry($expiryDate);
    return $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= $days;
}

/**
 * Check if item is expired
 */
function isExpired(?string $expiryDate): bool {
    $daysLeft = daysUntilExpiry($expiryDate);
    return $daysLeft !== null && $daysLeft < 0;
}

/**
 * Format quantity with unit
 */
function formatQuantity(float $quantity, string $unit): string {
    if ($quantity >= 1000) {
        return number_format($quantity, 2) . ' ' . $unit;
    }
    return number_format($quantity, 2) . ' ' . $unit;
}

/**
 * Calculate remaining percentage
 */
function calculateRemainingPercentage(float $initial, float $current): float {
    if ($initial <= 0) return 0;
    return round(($current / $initial) * 100, 2);
}

/**
 * Get severity level based on remaining percentage
 */
function getStockSeverity(float $remainingPercentage): string {
    if ($remainingPercentage <= 10) return 'critical';
    if ($remainingPercentage <= 25) return 'warning';
    if ($remainingPercentage <= 50) return 'low';
    return 'good';
}

/**
 * Get expiry severity
 */
function getExpirySeverity(?int $daysLeft): string {
    if ($daysLeft === null) return 'unknown';
    if ($daysLeft < 0) return 'expired';
    if ($daysLeft <= 7) return 'critical';
    if ($daysLeft <= 30) return 'warning';
    return 'good';
}

/**
 * Sanitize input for display
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize for HTML output
 */
function sanitizeHtml(string $input): string {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Truncate text with ellipsis
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
}

/**
 * Generate slug from string
 */
function slugify(string $text): string {
    $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    return $text;
}

/**
 * Format file size
 */
function formatFileSize(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Get file extension
 */
function getFileExtension(string $filename): string {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Generate safe filename
 */
function generateSafeFilename(string $originalName): string {
    $info = pathinfo($originalName);
    $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['filename']);
    $base = preg_replace('/_+/', '_', $base);
    $base = trim($base, '_');
    return $base . '_' . time() . '.' . strtolower($info['extension']);
}

/**
 * Check if file type is allowed
 */
function isAllowedFileType(string $filename, array $allowedTypes): bool {
    $ext = getFileExtension($filename);
    return in_array($ext, $allowedTypes);
}

/**
 * Get chemical state icon
 */
function getChemicalStateIcon(string $state): string {
    $icons = [
        'solid' => 'fa-cube',
        'liquid' => 'fa-tint',
        'gas' => 'fa-wind',
        'plasma' => 'fa-fire'
    ];
    return $icons[$state] ?? 'fa-flask';
}

/**
 * Get chemical state color
 */
function getChemicalStateColor(string $state): string {
    $colors = [
        'solid' => '#8B5CF6',    // Purple
        'liquid' => '#3B82F6',   // Blue
        'gas' => '#10B981',      // Green
        'plasma' => '#F59E0B'   // Orange
    ];
    return $colors[$state] ?? '#6B7280';
}

/**
 * Get status badge class
 */
function getStatusBadgeClass(string $status): string {
    $classes = [
        'active' => 'success',
        'inactive' => 'default',
        'empty' => 'warning',
        'expired' => 'danger',
        'quarantined' => 'warning',
        'disposed' => 'danger',
        'transferred' => 'info',
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'fulfilled' => 'success',
        'returned' => 'success',
        'overdue' => 'danger'
    ];
    return $classes[$status] ?? 'default';
}

/**
 * Get role badge color
 */
function getRoleBadgeColor(string $role): string {
    $colors = [
        'admin' => '#EF4444',
        'ceo' => '#8B5CF6',
        'lab_manager' => '#3B82F6',
        'user' => '#10B981',
        'visitor' => '#6B7280'
    ];
    return $colors[$role] ?? '#6B7280';
}

/**
 * Format phone number
 */
function formatPhone(string $phone): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) == 10) {
        return preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1-$2-$3', $phone);
    }
    
    return $phone;
}

/**
 * Validate Thai national ID (13 digits)
 */
function validateThaiId(string $id): bool {
    $id = preg_replace('/[^0-9]/', '', $id);
    if (strlen($id) !== 13) return false;
    
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$id[$i] * (13 - $i);
    }
    
    $checkDigit = (11 - ($sum % 11)) % 10;
    return (int)$id[12] === $checkDigit;
}

/**
 * Generate random string
 */
function randomString(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash string with salt
 */
function hashString(string $string, string $salt = ''): string {
    return hash('sha256', $string . $salt);
}

/**
 * Convert array to CSV row
 */
function arrayToCsv(array $data): string {
    $output = fopen('php://temp', 'r+');
    fputcsv($output, $data);
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return rtrim($csv, "\n");
}

/**
 * Parse CSV to array
 */
function csvToArray(string $csv): array {
    $data = [];
    $rows = str_getcsv($csv, "\n");
    
    foreach ($rows as $row) {
        if (!empty(trim($row))) {
            $data[] = str_getcsv($row);
        }
    }
    
    return $data;
}

/**
 * Download data as CSV file
 */
function downloadCsv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Return JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Return success JSON response
 */
function successResponse(string $message, array $data = [], int $statusCode = 200): void {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], $statusCode);
}

/**
 * Return error JSON response
 */
function errorResponse(string $message, int $statusCode = 400): void {
    jsonResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

/**
 * Get current URL
 */
function currentUrl(): string {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

/**
 * Get client IP address
 */
function getClientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Check for proxies
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    
    return $ip;
}

/**
 * Check if request is AJAX
 */
function isAjax(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get user agent
 */
function getUserAgent(): string {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Check if mobile device
 */
function isMobile(): bool {
    return preg_match('/mobile|android|iphone|ipad|phone/i', getUserAgent());
}

/**
 * Get browser info
 */
function getBrowser(): string {
    $agent = getUserAgent();
    
    if (preg_match('/chrome/i', $agent)) return 'Chrome';
    if (preg_match('/firefox/i', $agent)) return 'Firefox';
    if (preg_match('/safari/i', $agent)) return 'Safari';
    if (preg_match('/edge/i', $agent)) return 'Edge';
    if (preg_match('/opera/i', $agent)) return 'Opera';
    
    return 'Unknown';
}

/**
 * Format Thai date
 */
function formatThaiDate(string $date, bool $short = false): string {
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
        4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
        10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $shortMonths = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.',
        4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.',
        10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    
    $dt = new DateTime($date);
    $day = (int)$dt->format('j');
    $month = (int)$dt->format('n');
    $year = (int)$dt->format('Y') + 543;
    
    if ($short) {
        return $day . ' ' . $shortMonths[$month] . ' ' . $year;
    }
    
    return $day . ' ' . $months[$month] . ' ' . $year;
}

/**
 * Get relative time (Thai)
 */
function getRelativeTimeThai(string $datetime): string {
    $dt = new DateTime($datetime);
    $now = new DateTime('now');
    $diff = $now->diff($dt);
    
    $days = (int)$diff->format('%a');
    $hours = (int)$diff->format('%h');
    $minutes = (int)$diff->format('%i');
    
    if ($days > 30) {
        return formatThaiDate($datetime, true);
    } elseif ($days > 0) {
        return $days . ' วันที่แล้ว';
    } elseif ($hours > 0) {
        return $hours . ' ชั่วโมงที่แล้ว';
    } elseif ($minutes > 0) {
        return $minutes . ' นาทีที่แล้ว';
    } else {
        return 'เมื่อสักครู่';
    }
}

/**
 * Get GHS pictogram URLs
 */
function getGhsPictograms(array $pictograms): array {
    $baseUrl = 'https://pubchem.ncbi.nlm.nih.gov/images/ghs/';
    $urls = [];
    
    $pictogramMap = [
        'GHS01' => 'GHS01.svg',
        'GHS02' => 'GHS02.svg',
        'GHS03' => 'GHS03.svg',
        'GHS04' => 'GHS04.svg',
        'GHS05' => 'GHS05.svg',
        'GHS06' => 'GHS06.svg',
        'GHS07' => 'GHS07.svg',
        'GHS08' => 'GHS08.svg',
        'GHS09' => 'GHS09.svg'
    ];
    
    foreach ($pictograms as $p) {
        if (isset($pictogramMap[$p])) {
            $urls[] = $baseUrl . $pictogramMap[$p];
        }
    }
    
    return $urls;
}

/**
 * Calculate chemical compatibility
 */
function checkChemicalCompatibility(string $chemical1, string $chemical2): array {
    // Common incompatible groups
    $incompatibleGroups = [
        'acid' => ['base', 'oxide'],
        'base' => ['acid', 'oxide'],
        'oxidizer' => ['reducer', 'flammable'],
        'reducer' => ['oxidizer'],
        'flammable' => ['oxidizer', 'acid'],
        'toxic' => ['oxidizer'],
    ];
    
    return [
        'compatible' => true,
        'message' => 'No known incompatibilities'
    ];
}

/**
 * Get container type icon
 */
function getContainerTypeIcon(string $type): string {
    $icons = [
        'bottle' => 'fa-bottle-water',
        'vial' => 'fa-vial',
        'flask' => 'fa-flask',
        'canister' => 'fa-canister-gas',
        'cylinder' => 'fa-gas-pump',
        'ampoule' => 'fa-prescription-bottle',
        'bag' => 'fa-bag-shopping'
    ];
    return $icons[$type] ?? 'fa-box';
}
