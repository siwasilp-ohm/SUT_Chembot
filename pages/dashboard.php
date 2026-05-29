<?php
/**
 * Dashboard – Modern Bento Grid Pro (Proxy)
 * Redirects to the AI Assistant Dashboard (Chemical Search Assistant)
 */
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::getCurrentUser();
if (!$user) {
    header('Location: /v1/pages/login.php');
    exit;
}

// Load the AI Assistant Dashboard directly
require __DIR__ . '/dashboard-assistant.php';
exit;
