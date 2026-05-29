<?php
/**
 * Internationalization (i18n) System
 * Supports Thai and English
 */

class I18n {
    private static string $currentLang = 'th';
    private static array $translations = [];
    private static array $supportedLangs = ['th', 'en'];
    
    /**
     * Initialize i18n system
     */
    public static function init(string $lang = null): void {
        // Check URL parameter
        if (isset($_GET['lang']) && in_array($_GET['lang'], self::$supportedLangs)) {
            self::$currentLang = $_GET['lang'];
            setcookie('lang', self::$currentLang, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'samesite' => 'Lax'
            ]);
        }
        // Check cookie
        elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], self::$supportedLangs)) {
            self::$currentLang = $_COOKIE['lang'];
        }
        // Check browser language
        elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (in_array($browserLang, self::$supportedLangs)) {
                self::$currentLang = $browserLang;
            }
        }
        
        self::loadTranslations();
    }
    
    /**
     * Load translation files
     */
    private static function loadTranslations(): void {
        $langFile = __DIR__ . '/../lang/' . self::$currentLang . '.php';
        if (file_exists($langFile)) {
            self::$translations = require $langFile;
        }
    }
    
    /**
     * Get translation by key
     */
    public static function get(string $key, array $params = []): string {
        $keys = explode('.', $key);
        $value = self::$translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                // Return key if translation not found
                return $key;
            }
        }
        
        // Replace parameters
        if (is_string($value) && !empty($params)) {
            foreach ($params as $param => $paramValue) {
                $value = str_replace(':' . $param, $paramValue, $value);
            }
        }
        
        return $value;
    }
    
    /**
     * Get current language
     */
    public static function getCurrentLang(): string {
        return self::$currentLang;
    }
    
    /**
     * Get supported languages
     */
    public static function getSupportedLangs(): array {
        return self::$supportedLangs;
    }
    
    /**
     * Set language
     */
    public static function setLang(string $lang): void {
        if (in_array($lang, self::$supportedLangs)) {
            self::$currentLang = $lang;
            setcookie('lang', $lang, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'samesite' => 'Lax'
            ]);
            self::loadTranslations();
        }
    }
    
    /**
     * Get language name
     */
    public static function getLangName(string $lang): string {
        $names = [
            'th' => 'ไทย',
            'en' => 'English'
        ];
        return $names[$lang] ?? $lang;
    }
    
    /**
     * Get language flag emoji
     */
    public static function getLangFlag(string $lang): string {
        $flags = [
            'th' => '🇹🇭',
            'en' => '🇬🇧'
        ];
        return $flags[$lang] ?? '🌐';
    }
}

// Helper function
function __($key, array $params = []): string {
    return I18n::get($key, $params);
}

// Initialize
I18n::init();
