<?php
/**
 * Centralized Error Logger for SUT chemBot
 * Provides structured logging with different levels and handlers
 */

require_once __DIR__ . '/config.php';

class ErrorLogger {
    private static ?ErrorLogger $instance = null;
    private string $logPath;
    private array $handlers = [];
    private array $config;
    
    // Log levels
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    private function __construct() {
        $this->config = [
            'log_path' => defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/',
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'max_files' => 30,
            'enable_console' => true,
            'enable_file' => true,
            'enable_email' => false,
            'email_recipients' => [],
            'environment' => defined('APP_ENV') ? APP_ENV : 'production'
        ];
        
        $this->logPath = $this->config['log_path'];
        $this->ensureLogDirectory();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): ErrorLogger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    /**
     * Log a message
     */
    public static function log(string $level, string $message, array $context = []): void {
        $logger = self::getInstance();
        $logger->write($level, $message, $context);
    }
    
    /**
     * Convenience methods for each level
     */
    public static function debug(string $message, array $context = []): void {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    public static function info(string $message, array $context = []): void {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    public static function warning(string $message, array $context = []): void {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    public static function error(string $message, array $context = []): void {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    public static function critical(string $message, array $context = []): void {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * Write log entry
     */
    private function write(string $level, string $message, array $context): void {
        // Build log entry
        $entry = $this->buildEntry($level, $message, $context);
        
        // Write to file
        if ($this->config['enable_file']) {
            $this->writeToFile($entry, $level);
        }
        
        // Write to console in development
        if ($this->config['enable_console'] && $this->config['environment'] === 'development') {
            $this->writeToConsole($entry, $level);
        }
        
        // Send email for critical errors in production
        if ($this->config['enable_email'] && $level === self::LEVEL_CRITICAL) {
            $this->sendEmailAlert($entry);
        }
    }
    
    /**
     * Build log entry structure
     */
    private function buildEntry(string $level, string $message, array $context): array {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $caller = $backtrace[2] ?? [];
        
        return [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'source' => [
                'file' => $caller['file'] ?? 'unknown',
                'line' => $caller['line'] ?? 0,
                'function' => $caller['function'] ?? 'unknown',
                'class' => $caller['class'] ?? 'unknown'
            ],
            'request' => [
                'uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
            ],
            'user' => $this->getCurrentUserInfo(),
            'environment' => $this->config['environment']
        ];
    }
    
    /**
     * Get current user info for logging
     */
    private function getCurrentUserInfo(): array {
        try {
            if (function_exists('Auth::getCurrentUser') && class_exists('Auth')) {
                $user = Auth::getCurrentUser();
                if ($user) {
                    return [
                        'id' => $user['id'] ?? null,
                        'username' => $user['username'] ?? 'unknown',
                        'role' => $user['role_name'] ?? 'guest'
                    ];
                }
            }
        } catch (Exception $e) {
            // Ignore errors when getting user info
        }
        
        return ['id' => null, 'username' => 'guest', 'role' => 'guest'];
    }
    
    /**
     * Write log to file
     */
    private function writeToFile(array $entry, string $level): void {
        // Check and rotate log file if needed
        $this->rotateLogsIfNeeded();
        
        // Determine log file based on level
        $levelFiles = [
            self::LEVEL_DEBUG => 'debug.log',
            self::LEVEL_INFO => 'info.log',
            self::LEVEL_WARNING => 'warning.log',
            self::LEVEL_ERROR => 'error.log',
            self::LEVEL_CRITICAL => 'critical.log'
        ];
        
        $filename = $levelFiles[$level] ?? 'general.log';
        $filepath = $this->logPath . $filename;
        
        // Format entry as JSON for structured logging
        $logLine = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
        
        // Add readable format for quick debugging
        $readableLine = sprintf(
            "[%s] [%s] %s | File: %s:%d | User: %s | %s\n",
            $entry['timestamp'],
            $level,
            $entry['message'],
            basename($entry['source']['file']),
            $entry['source']['line'],
            $entry['user']['username'],
            !empty($entry['context']) ? json_encode($entry['context']) : ''
        );
        
        // Write both formats
        file_put_contents($filepath, $logLine, FILE_APPEND | LOCK_EX);
        file_put_contents($this->logPath . 'combined.log', $readableLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Write log to console
     */
    private function writeToConsole(array $entry, string $level): void {
        $colors = [
            self::LEVEL_DEBUG => "\033[36m",    // Cyan
            self::LEVEL_INFO => "\033[32m",     // Green
            self::LEVEL_WARNING => "\033[33m",  // Yellow
            self::LEVEL_ERROR => "\033[31m",    // Red
            self::LEVEL_CRITICAL => "\035[35m" // Magenta
        ];
        
        $color = $colors[$level] ?? "\033[0m";
        $reset = "\033[0m";
        
        $message = sprintf(
            "[%s] %s[%s%s%s] %s in %s:%d\n",
            date('H:i:s'),
            $color,
            $level,
            $reset,
            $entry['message'],
            basename($entry['source']['file']),
            $entry['source']['line']
        );
        
        if (php_sapi_name() === 'cli') {
            echo $message;
        } else {
            // For web, you might want to use error_log or custom handler
            error_log(strip_tags($message));
        }
    }
    
    /**
     * Rotate logs if file size exceeds maximum
     */
    private function rotateLogsIfNeeded(): void {
        $files = glob($this->logPath . '*.log');
        
        foreach ($files as $file) {
            if (filesize($file) > $this->config['max_file_size']) {
                $this->rotateFile($file);
            }
        }
    }
    
    /**
     * Rotate a single log file
     */
    private function rotateFile(string $filepath): void {
        $dirname = dirname($filepath);
        $basename = basename($filepath);
        
        // Rename current file to .1
        $archivePath = $dirname . '/' . str_replace('.log', '.1.log', $basename);
        
        // If .1 already exists, rotate existing archives
        for ($i = $this->config['max_files']; $i > 1; $i--) {
            $oldFile = $dirname . '/' . str_replace('.log', '.' . $i . '.log', $basename);
            $newFile = $dirname . '/' . str_replace('.log', '.' . ($i + 1) . '.log', $basename);
            
            if (file_exists($oldFile)) {
                if ($i >= $this->config['max_files']) {
                    @unlink($oldFile); // Delete oldest
                } else {
                    @rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current to .1
        @rename($filepath, $archivePath);
    }
    
    /**
     * Send email alert for critical errors
     */
    private function sendEmailAlert(array $entry): void {
        if (empty($this->config['email_recipients'])) {
            return;
        }
        
        $subject = sprintf('[%s] CRITICAL: %s', strtoupper($this->config['environment']), $entry['message']);
        $body = $this->formatEmailBody($entry);
        
        $headers = [
            'From: ' . (defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@cheminventory.local'),
            'Content-Type: text/plain; charset=UTF-8',
            'X-Priority: 1'
        ];
        
        foreach ($this->config['email_recipients'] as $recipient) {
            @mail($recipient, $subject, $body, implode("\r\n", $headers));
        }
    }
    
    /**
     * Format email body
     */
    private function formatEmailBody(array $entry): string {
        return sprintf(
            "CRITICAL ERROR ALERT\n" .
            "====================\n\n" .
            "Time: %s\n" .
            "Level: %s\n" .
            "Message: %s\n\n" .
            "Source:\n" .
            "  File: %s\n" .
            "  Line: %d\n" .
            "  Function: %s\n\n" .
            "Request:\n" .
            "  URI: %s\n" .
            "  IP: %s\n\n" .
            "User: %s (ID: %s)\n\n" .
            "Context:\n%s",
            $entry['timestamp'],
            $entry['level'],
            $entry['message'],
            $entry['source']['file'],
            $entry['source']['line'],
            $entry['source']['function'],
            $entry['request']['uri'],
            $entry['request']['ip'],
            $entry['user']['username'],
            $entry['user']['id'] ?? 'N/A',
            json_encode($entry['context'], JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Set configuration
     */
    public function setConfig(array $config): void {
        $this->config = array_merge($this->config, $config);
    }
    
    /**
     * Get configuration
     */
    public function getConfig(): array {
        return $this->config;
    }
    
    /**
     * Clean old log files
     */
    public function cleanOldLogs(int $daysToKeep = 30): int {
        $count = 0;
        $cutoffTime = time() - ($daysToKeep * 86400);
        
        $files = glob($this->logPath . '*.log');
        
        foreach ($files as $file) {
            // Keep critical.log and combined.log
            $basename = basename($file);
            if (in_array($basename, ['critical.log', 'combined.log'])) {
                continue;
            }
            
            if (filemtime($file) < $cutoffTime) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get log statistics
     */
    public function getStats(): array {
        $stats = [
            'total_size' => 0,
            'file_count' => 0,
            'levels' => [],
            'oldest_log' => null,
            'newest_log' => null
        ];
        
        $files = glob($this->logPath . '*.log');
        
        foreach ($files as $file) {
            $basename = basename($file);
            $stats['total_size'] += filesize($file);
            $stats['file_count']++;
            
            // Count by level
            $level = str_replace('.log', '', $basename);
            $stats['levels'][$level] = filesize($file);
            
            // Track oldest/newest
            $mtime = filemtime($file);
            if ($stats['oldest_log'] === null || $mtime < $stats['oldest_log']) {
                $stats['oldest_log'] = date('Y-m-d H:i:s', $mtime);
            }
            if ($stats['newest_log'] === null || $mtime > $stats['newest_log']) {
                $stats['newest_log'] = date('Y-m-d H:i:s', $mtime);
            }
        }
        
        return $stats;
    }
}

// PHP error handler integration
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $levelMap = [
        E_ERROR => ErrorLogger::LEVEL_ERROR,
        E_WARNING => ErrorLogger::LEVEL_WARNING,
        E_PARSE => ErrorLogger::LEVEL_CRITICAL,
        E_NOTICE => ErrorLogger::LEVEL_INFO,
        E_CORE_ERROR => ErrorLogger::LEVEL_CRITICAL,
        E_CORE_WARNING => ErrorLogger::LEVEL_WARNING,
        E_COMPILE_ERROR => ErrorLogger::LEVEL_CRITICAL,
        E_COMPILE_WARNING => ErrorLogger::LEVEL_WARNING,
        E_USER_ERROR => ErrorLogger::LEVEL_ERROR,
        E_USER_WARNING => ErrorLogger::LEVEL_WARNING,
        E_USER_NOTICE => ErrorLogger::LEVEL_INFO,
        E_STRICT => ErrorLogger::LEVEL_DEBUG,
        E_RECOVERABLE_ERROR => ErrorLogger::LEVEL_ERROR,
        E_DEPRECATED => ErrorLogger::LEVEL_DEBUG,
        E_USER_DEPRECATED => ErrorLogger::LEVEL_DEBUG
    ];
    
    $level = $levelMap[$severity] ?? ErrorLogger::LEVEL_ERROR;
    
    ErrorLogger::log($level, $message, [
        'php_error' => true,
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);
    
    return true;
});

// Exception handler integration
set_exception_handler(function ($exception) {
    ErrorLogger::critical('Uncaught Exception: ' . $exception->getMessage(), [
        'exception' => [
            'class' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]
    ]);
});

// Register shutdown function for fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ErrorLogger::critical('Fatal Error: ' . $error['message'], [
            'fatal_error' => true,
            'type' => $error['type'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

// Helper function for easy logging
function logger(string $level, string $message, array $context = []): void {
    ErrorLogger::log($level, $message, $context);
}
