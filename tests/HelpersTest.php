<?php
/**
 * Unit Tests for Helper Functions
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Test Helpers
 */
class HelpersTest extends TestCase {
    
    /**
     * Test generateUniqueId
     */
    public function testGenerateUniqueId(): void {
        $id1 = generateUniqueId();
        $id2 = generateUniqueId();
        
        $this->assertNotEmpty($id1);
        $this->assertNotEmpty($id2);
        $this->assertNotEquals($id1, $id2);
        $this->assertGreaterThan(15, strlen($id1));
    }
    
    /**
     * Test generateUniqueId with prefix
     */
    public function testGenerateUniqueIdWithPrefix(): void {
        $id = generateUniqueId('CHEM-');
        
        $this->assertNotEmpty($id);
        $this->assertContains('CHEM-', $id);
    }
    
    /**
     * Test generateQrCode
     */
    public function testGenerateQrCode(): void {
        $qr = generateQrCode();
        
        $this->assertNotEmpty($qr);
        $this->assertContains('CHEM-', $qr);
        $this->assertEquals(21, strlen($qr)); // CHEM- + 6 + 8 = 21
    }
    
    /**
     * Test formatDate
     */
    public function testFormatDate(): void {
        $result = formatDate('2024-01-15');
        
        $this->assertEquals('15 Jan 2024', $result);
    }
    
    /**
     * Test formatDate with null
     */
    public function testFormatDateNull(): void {
        $result = formatDate(null);
        
        $this->assertEquals('-', $result);
    }
    
    /**
     * Test formatDateTime
     */
    public function testFormatDateTime(): void {
        $result = formatDateTime('2024-01-15 14:30:00');
        
        $this->assertEquals('15 Jan 2024 14:30', $result);
    }
    
    /**
     * Test daysUntilExpiry
     */
    public function testDaysUntilExpiry(): void {
        $future = date('Y-m-d', strtotime('+30 days'));
        $past = date('Y-m-d', strtotime('-10 days'));
        
        $this->assertGreaterThan(0, daysUntilExpiry($future));
        $this->assertLessThan(0, daysUntilExpiry($past));
    }
    
    /**
     * Test daysUntilExpiry with null
     */
    public function testDaysUntilExpiryNull(): void {
        $result = daysUntilExpiry(null);
        
        $this->assertNull($result);
    }
    
    /**
     * Test isExpiringSoon
     */
    public function testIsExpiringSoon(): void {
        $soon = date('Y-m-d', strtotime('+15 days'));
        $far = date('Y-m-d', strtotime('+60 days'));
        
        $this->assertTrue(isExpiringSoon($soon, 30));
        $this->assertFalse(isExpiringSoon($far, 30));
    }
    
    /**
     * Test isExpired
     */
    public function testIsExpired(): void {
        $past = date('Y-m-d', strtotime('-5 days'));
        $future = date('Y-m-d', strtotime('+30 days'));
        
        $this->assertTrue(isExpired($past));
        $this->assertFalse(isExpired($future));
    }
    
    /**
     * Test formatQuantity
     */
    public function testFormatQuantity(): void {
        $result = formatQuantity(500, 'mL');
        
        $this->assertContains('500.00', $result);
        $this->assertContains('mL', $result);
    }
    
    /**
     * Test calculateRemainingPercentage
     */
    public function testCalculateRemainingPercentage(): void {
        $this->assertEquals(50, calculateRemainingPercentage(100, 50));
        $this->assertEquals(25, calculateRemainingPercentage(200, 50));
        $this->assertEquals(0, calculateRemainingPercentage(0, 50));
    }
    
    /**
     * Test sanitize
     */
    public function testSanitize(): void {
        $result = sanitize('<script>alert("xss")</script>');
        
        $this->assertNotContains('<script>', $result);
        $this->assertEquals('<script>alert("xss")</script>', $result);
    }
    
    /**
     * Test truncate
     */
    public function testTruncate(): void {
        $long = 'This is a very long string that should be truncated';
        $short = 'Short';
        
        $result = truncate($long, 20);
        
        $this->assertLessThanOrEqual(20, strlen($result));
        $this->assertContains('...', $result);
        $this->assertEquals('Short', truncate($short, 20));
    }
    
    /**
     * Test slugify
     */
    public function testSlugify(): void {
        $result = slugify('Hello World! @#$%');
        
        $this->assertEquals('hello-world', $result);
    }
    
    /**
     * Test formatFileSize
     */
    public function testFormatFileSize(): void {
        $this->assertEquals('500 B', formatFileSize(500));
        $this->assertEquals('1 KB', formatFileSize(1024));
        $this->assertEquals('1 MB', formatFileSize(1024 * 1024));
    }
    
    /**
     * Test getFileExtension
     */
    public function testGetFileExtension(): void {
        $this->assertEquals('jpg', getFileExtension('image.JPG'));
        $this->assertEquals('png', getFileExtension('image.png'));
    }
    
    /**
     * Test isAllowedFileType
     */
    public function testIsAllowedFileType(): void {
        $allowed = ['jpg', 'png', 'pdf'];
        
        $this->assertTrue(isAllowedFileType('image.jpg', $allowed));
        $this->assertTrue(isAllowedFileType('document.PDF', $allowed));
        $this->assertFalse(isAllowedFileType('malicious.exe', $allowed));
    }
    
    /**
     * Test getChemicalStateIcon
     */
    public function testGetChemicalStateIcon(): void {
        $this->assertEquals('fa-cube', getChemicalStateIcon('solid'));
        $this->assertEquals('fa-tint', getChemicalStateIcon('liquid'));
        $this->assertEquals('fa-wind', getChemicalStateIcon('gas'));
    }
    
    /**
     * Test getStatusBadgeClass
     */
    public function testGetStatusBadgeClass(): void {
        $this->assertEquals('success', getStatusBadgeClass('active'));
        $this->assertEquals('danger', getStatusBadgeClass('expired'));
        $this->assertEquals('warning', getStatusBadgeClass('pending'));
    }
    
    /**
     * Test formatPhone
     */
    public function testFormatPhone(): void {
        $result = formatPhone('0812345678');
        
        $this->assertEquals('081-234-5678', $result);
    }
    
    /**
     * Test randomString
     */
    public function testRandomString(): void {
        $str1 = randomString(16);
        $str2 = randomString(16);
        
        $this->assertEquals(16, strlen($str1));
        $this->assertNotEquals($str1, $str2);
    }
    
    /**
     * Test arrayToCsv
     */
    public function testArrayToCsv(): void {
        $data = ['name', 'email', 'phone'];
        
        $result = arrayToCsv($data);
        
        $this->assertEquals('name,email,phone', $result);
    }
    
    /**
     * Test csvToArray
     */
    public function testCsvToArray(): void {
        $csv = "name,email\njohn,john@test.com";
        
        $result = csvToArray($csv);
        
        $this->assertCount(1, $result);
        $this->assertEquals(['name', 'email'], $result[0]);
    }
    
    /**
     * Test formatThaiDate
     */
    public function testFormatThaiDate(): void {
        $result = formatThaiDate('2024-01-15');
        
        $this->assertContains('มกราคม', $result);
        $this->assertContains('2567', $result);
    }
    
    /**
     * Test getGhsPictograms
     */
    public function testGetGhsPictograms(): void {
        $pictograms = ['GHS01', 'GHS02', 'GHS03'];
        
        $result = getGhsPictograms($pictograms);
        
        $this->assertCount(3, $result);
        $this->assertContains('GHS01.svg', $result[0]);
    }
    
    /**
     * Test getContainerTypeIcon
     */
    public function testGetContainerTypeIcon(): void {
        $this->assertEquals('fa-bottle-water', getContainerTypeIcon('bottle'));
        $this->assertEquals('fa-vial', getContainerTypeIcon('vial'));
    }
    
    /**
     * Test getClientIp
     */
    public function testGetClientIp(): void {
        $ip = getClientIp();
        
        $this->assertNotEmpty($ip);
    }
    
    /**
     * Test isMobile
     */
    public function testIsMobile(): void {
        // Basic check that function returns boolean
        $result = isMobile();
        
        $this->assertTrue(is_bool($result));
    }
    
    /**
     * Test getBrowser
     */
    public function testGetBrowser(): void {
        $browser = getBrowser();
        
        $this->assertNotEmpty($browser);
    }
}

/**
 * Run tests
 */
echo "Running Helper Functions Tests...\n\n";

$runner = new TestRunner();
$test = new HelpersTest();
$runner->run($test, 'HelpersTest');
$runner->printResults();

$summary = $runner->getSummary();
exit($summary['success'] ? 0 : 1);
