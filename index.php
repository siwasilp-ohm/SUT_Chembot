<?php
/**
 * SUT chemBot - Main Entry Point
 * Login & Dashboard Router
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = Auth::getCurrentUser();

if ($user) {
    // User is logged in, show dashboard
    include __DIR__ . '/pages/dashboard.php';
} else {
    // Show login page
    include __DIR__ . '/pages/login.php';
}
