<?php
/**
 * QR Code Generator for Chemical Containers
 * Fixed: Uses endroid/qr-code when available, fixed GD memory leak
 */

require_once __DIR__ . '/config.php';

// Load Composer autoloader if available (for endroid/qr-code)
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class QRGenerator {
    
    /**
     * Generate QR Code for container
     */
    public static function generate(string $qrCode, ?int $chemicalId = null): string {
        $uploadDir = UPLOAD_PATH . 'qr_codes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = md5($qrCode) . '.png';
        $filepath = $uploadDir . $filename;
        $webpath = 'qr_codes/' . $filename;
        
        // Priority: 1) endroid/qr-code library, 2) GD library, 3) External API
        if (class_exists('Endroid\\QrCode\\QrCode')) {
            self::generateWithEndroid($qrCode, $filepath);
        } elseif (extension_loaded('gd')) {
            self::generateWithGD($qrCode, $filepath, $chemicalId);
        } else {
            self::generateWithAPI($qrCode, $filepath);
        }
        
        return $webpath;
    }
    
    /**
     * Generate QR using endroid/qr-code library (best quality)
     */
    private static function generateWithEndroid(string $qrCode, string $filepath): void {
        try {
            // endroid/qr-code v4 API
            $qrCodeObj = \Endroid\QrCode\QrCode::create($qrCode)
                ->setSize(400)
                ->setMargin(20);
            
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCodeObj);
            $result->saveToFile($filepath);
        } catch (\Exception $e) {
            error_log("Endroid QR generation failed: " . $e->getMessage());
            if (extension_loaded('gd')) {
                self::generateWithGD($qrCode, $filepath, null);
            } else {
                self::generateWithAPI($qrCode, $filepath);
            }
        }
    }
    
    /**
     * Generate QR using GD library (visual placeholder with finder patterns)
     */
    private static function generateWithGD(string $qrCode, string $filepath, ?int $chemicalId): void {
        $size = 400;
        $padding = 20;
        
        $image = imagecreatetruecolor($size, $size + 60);
        
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefill($image, 0, 0, $white);
        
        // Generate QR-like pattern (visual placeholder - not scannable without proper library)
        $moduleCount = 25;
        $qrData = self::createQRMatrix($qrCode, $moduleCount);
        $cellSize = ($size - 2 * $padding) / $moduleCount;
        
        for ($row = 0; $row < $moduleCount; $row++) {
            for ($col = 0; $col < $moduleCount; $col++) {
                if ($qrData[$row][$col]) {
                    imagefilledrectangle(
                        $image,
                        (int)($padding + $col * $cellSize),
                        (int)($padding + $row * $cellSize),
                        (int)($padding + ($col + 1) * $cellSize - 1),
                        (int)($padding + ($row + 1) * $cellSize - 1),
                        $black
                    );
                }
            }
        }
        
        // Add text label below QR
        $text = substr($qrCode, 0, 30);
        imagestring($image, 5, $padding, $size + 20, $text, $black);
        
        imagepng($image, $filepath);
        imagedestroy($image);
    }
    
    /**
     * Generate QR using external API
     */
    private static function generateWithAPI(string $qrCode, string $filepath): void {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($qrCode);
        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data !== false) {
            file_put_contents($filepath, $data);
        } else {
            error_log("QR API generation failed for: {$qrCode}");
        }
    }
    
    /**
     * Create QR-like matrix with finder patterns (visual placeholder)
     */
    private static function createQRMatrix(string $data, int $size): array {
        $matrix = [];
        $hash = md5($data);
        
        for ($i = 0; $i < $size; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $size; $j++) {
                // Position detection patterns (corners)
                if (self::isFinderPattern($i, $j, $size)) {
                    $matrix[$i][$j] = self::getFinderPatternValue($i, $j, $size);
                }
                // Timing patterns
                elseif ($i == 6) {
                    $matrix[$i][$j] = ($j % 2 == 0);
                } elseif ($j == 6) {
                    $matrix[$i][$j] = ($i % 2 == 0);
                }
                // Data area from hash
                else {
                    $hashIdx = ($i * $size + $j) % 32;
                    $matrix[$i][$j] = (hexdec($hash[$hashIdx]) % 2 == 0);
                }
            }
        }
        
        return $matrix;
    }
    
    private static function isFinderPattern(int $i, int $j, int $size): bool {
        return ($i < 7 && $j < 7) || 
               ($i < 7 && $j >= $size - 7) || 
               ($i >= $size - 7 && $j < 7);
    }
    
    private static function getFinderPatternValue(int $i, int $j, int $size): bool {
        // Normalize to top-left corner
        $r = $i;
        $c = $j;
        if ($j >= $size - 7) $c = $j - ($size - 7);
        if ($i >= $size - 7) $r = $i - ($size - 7);
        
        // Outer ring, inner ring, or center
        return ($r == 0 || $r == 6 || $c == 0 || $c == 6) ||
               ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
    }
    
    /**
     * Print QR code label
     */
    public static function generateLabel(array $container, array $chemical): string {
        $uploadDir = UPLOAD_PATH . 'labels/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = 'label_' . $container['id'] . '.png';
        $filepath = $uploadDir . $filename;
        
        $width = 600;
        $height = 400;
        $image = imagecreatetruecolor($width, $height);
        
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $red = imagecolorallocate($image, 220, 38, 38);
        $yellow = imagecolorallocate($image, 250, 204, 21);
        $blue = imagecolorallocate($image, 37, 99, 235);
        
        imagefill($image, 0, 0, $white);
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $black);
        
        // Chemical name
        $name = substr($chemical['name'], 0, 40);
        imagestring($image, 5, 20, 20, $name, $black);
        
        // CAS Number
        imagestring($image, 4, 20, 50, 'CAS: ' . ($chemical['cas_number'] ?? 'N/A'), $black);
        
        // QR Code placeholder area
        $qrSize = 150;
        $qrX = 420;
        $qrY = 20;
        imagefilledrectangle($image, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $black);
        imagefilledrectangle($image, $qrX + 10, $qrY + 10, $qrX + $qrSize - 10, $qrY + $qrSize - 10, $white);
        
        // GHS Pictogram colors (fixed: no memory leak)
        $pictograms = json_decode($chemical['hazard_pictograms'] ?? '[]', true);
        $pictoX = 20;
        $pictoY = 100;
        foreach (array_slice($pictograms, 0, 4) as $i => $picto) {
            $color = self::getHazardColor($image, $picto);
            imagefilledrectangle($image, $pictoX + $i * 70, $pictoY, $pictoX + $i * 70 + 60, $pictoY + 60, $color);
            imagerectangle($image, $pictoX + $i * 70, $pictoY, $pictoX + $i * 70 + 60, $pictoY + 60, $black);
        }
        
        // Signal word
        if (!empty($chemical['signal_word'])) {
            $signalColor = $chemical['signal_word'] === 'Danger' ? $red : $yellow;
            imagefilledrectangle($image, 20, 180, 200, 220, $signalColor);
            imagestring($image, 5, 30, 195, $chemical['signal_word'], $black);
        }
        
        // Container info
        imagestring($image, 3, 20, 250, 'Container: ' . ($container['container_type'] ?? ''), $black);
        imagestring($image, 3, 20, 275, 'Qty: ' . ($container['current_quantity'] ?? '') . ' ' . ($container['quantity_unit'] ?? ''), $black);
        imagestring($image, 3, 20, 300, 'Exp: ' . ($container['expiry_date'] ?? 'N/A'), $black);
        imagestring($image, 3, 20, 350, 'Owner: ' . ($container['first_name'] ?? '') . ' ' . ($container['last_name'] ?? ''), $black);
        imagestring($image, 2, 420, 180, substr($container['qr_code'] ?? '', 0, 15), $black);
        
        imagepng($image, $filepath);
        imagedestroy($image);
        
        return 'labels/' . $filename;
    }
    
    /**
     * Get hazard color - Fixed: uses shared image resource (no memory leak)
     */
    private static function getHazardColor($image, string $pictogram): int {
        $colorMap = [
            'explosive'      => [255, 165, 0],
            'flammable'      => [220, 38, 38],
            'oxidizing'      => [255, 165, 0],
            'compressed_gas' => [37, 99, 235],
            'corrosive'      => [147, 51, 234],
            'toxic'          => [220, 38, 38],
            'harmful'        => [250, 204, 21],
            'health_hazard'  => [220, 38, 38],
            'environment'    => [34, 197, 94],
        ];
        
        $rgb = $colorMap[$pictogram] ?? $colorMap['harmful'];
        return imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
    }
}
