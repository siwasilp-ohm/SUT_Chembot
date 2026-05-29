<?php
/**
 * Test Bootstrap File
 * Sets up the testing environment
 */

// Define test environment
define('APP_ENV', 'testing');
define('DB_HOST', 'localhost');
define('DB_NAME', 'chem_inventory_test');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('JWT_SECRET', 'test-jwt-secret-key-for-testing-only-32chars');
define('LOG_PATH', __DIR__ . '/logs/');

// Create logs directory if not exists
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoload classes
spl_autoload_register(function ($class) {
    $prefixes = [
        'ChemInventory\\' => __DIR__ . '/../includes/',
    ];
    
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';

/**
 * Test Case Base Class
 */
class TestCase {
    protected array $assertions = [];
    protected float $startTime;
    
    public function __construct() {
        $this->startTime = microtime(true);
    }
    
    /**
     * Assert true
     */
    protected function assertTrue(bool $condition, string $message = ''): void {
        $this->assert($condition === true, $message ?: 'Expected true, got false');
    }
    
    /**
     * Assert false
     */
    protected function assertFalse(bool $condition, string $message = ''): void {
        $this->assert($condition === false, $message ?: 'Expected false, got true');
    }
    
    /**
     * Assert equals
     */
    protected function assertEquals($expected, $actual, string $message = ''): void {
        $this->assert($expected == $actual, $message ?: "Expected {$expected}, got {$actual}");
    }
    
    /**
     * Assert not equals
     */
    protected function assertNotEquals($expected, $actual, string $message = ''): void {
        $this->assert($expected != $actual, $message ?: "Expected not equals {$expected}");
    }
    
    /**
     * Assert null
     */
    protected function assertNull($value, string $message = ''): void {
        $this->assert($value === null, $message ?: 'Expected null');
    }
    
    /**
     * Assert not null
     */
    protected function assertNotNull($value, string $message = ''): void {
        $this->assert($value !== null, $message ?: 'Expected not null');
    }
    
    /**
     * Assert empty
     */
    protected function assertEmpty($value, string $message = ''): void {
        $this->assert(empty($value), $message ?: 'Expected empty');
    }
    
    /**
     * Assert not empty
     */
    protected function assertNotEmpty($value, string $message = ''): void {
        $this->assert(!empty($value), $message ?: 'Expected not empty');
    }
    
    /**
     * Assert contains
     */
    protected function assertContains(string $needle, string $haystack, string $message = ''): void {
        $this->assert(
            strpos($haystack, $needle) !== false,
            $message ?: "Expected '{$needle}' to be in '{$haystack}'"
        );
    }
    
    /**
     * Assert array has key
     */
    protected function assertArrayHasKey($key, array $array, string $message = ''): void {
        $this->assert(
            array_key_exists($key, $array),
            $message ?: "Expected key '{$key}' in array"
        );
    }
    
    /**
     * Assert count
     */
    protected function assertCount(int $expected, array $array, string $message = ''): void {
        $this->assert(
            count($array) === $expected,
            $message ?: "Expected {$expected} items, got " . count($array)
        );
    }
    
    /**
     * Assert greater than
     */
    protected function assertGreaterThan($expected, $actual, string $message = ''): void {
        $this->assert(
            $actual > $expected,
            $message ?: "Expected {$actual} to be greater than {$expected}"
        );
    }
    
    /**
     * Assert less than
     */
    protected function assertLessThan($expected, $actual, string $message = ''): void {
        $this->assert(
            $actual < $expected,
            $message ?: "Expected {$actual} to be less than {$expected}"
        );
    }
    
    /**
     * Assert throws exception
     */
    protected function assertThrows(callable $callback, string $message = ''): ?Exception {
        try {
            $callback();
            $this->assert(false, $message ?: 'Expected exception to be thrown');
            return null;
        } catch (Exception $e) {
            return $e;
        }
    }
    
    /**
     * Internal assert
     */
    private function assert(bool $condition, string $message): void {
        $this->assertions[] = [
            'passed' => $condition,
            'message' => $message,
            'time' => microtime(true) - $this->startTime
        ];
        
        if (!$condition) {
            throw new AssertionError($message);
        }
    }
    
    /**
     * Get test results
     */
    public function getResults(): array {
        $passed = count(array_filter($this->assertions, fn($a) => $a['passed']));
        $failed = count($this->assertions) - $passed;
        
        return [
            'total' => count($this->assertions),
            'passed' => $passed,
            'failed' => $failed,
            'assertions' => $this->assertions
        ];
    }
}

/**
 * Simple test runner
 */
class TestRunner {
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    
    /**
     * Run a test class
     */
    public function run(TestCase $test, string $name): void {
        try {
            // Run setup
            if (method_exists($test, 'setUp')) {
                $test->setUp();
            }
            
            // Run test methods
            $methods = get_class_methods($test);
            foreach ($methods as $method) {
                if (strpos($method, 'test') === 0) {
                    try {
                        $test->$method();
                    } catch (Exception $e) {
                        // Test method threw exception
                        $this->failed++;
                        $this->results[] = [
                            'class' => get_class($test),
                            'method' => $method,
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ];
                    }
                }
            }
            
            // Get results
            $result = $test->getResults();
            $this->passed += $result['passed'];
            $this->failed += $result['failed'];
            
            $this->results[] = [
                'class' => get_class($test),
                'status' => $result['failed'] > 0 ? 'failed' : 'passed',
                'passed' => $result['passed'],
                'failed' => $result['failed']
            ];
            
        } catch (Exception $e) {
            $this->failed++;
            $this->results[] = [
                'class' => get_class($test),
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Print results
     */
    public function printResults(): void {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "TEST RESULTS\n";
        echo str_repeat('=', 60) . "\n\n";
        
        foreach ($this->results as $result) {
            $status = strtoupper($result['status']);
            $color = $result['status'] === 'passed' ? "\033[32m" : "\033[31m";
            $reset = "\033[0m";
            
            echo "{$color}[{$status}]{$reset} ";
            echo $result['class'];
            
            if (isset($result['method'])) {
                echo "::{$result['method']}";
            }
            
            if (isset($result['passed'])) {
                echo " ({$result['passed']} passed, {$result['failed']} failed)";
            }
            
            if (isset($result['message'])) {
                echo "\n  Error: {$result['message']}";
            }
            
            echo "\n";
        }
        
        echo "\n" . str_repeat('-', 60) . "\n";
        echo "Total: {$this->passed} passed, {$this->failed} failed\n";
        echo str_repeat('=', 60) . "\n\n";
    }
    
    /**
     * Get summary
     */
    public function getSummary(): array {
        return [
            'passed' => $this->passed,
            'failed' => $this->failed,
            'total' => $this->passed + $this->failed,
            'success' => $this->failed === 0
        ];
    }
}

/**
 * Assertion Error
 */
class AssertionError extends Exception {
    public function __construct(string $message = '') {
        parent::__construct($message);
    }
}
