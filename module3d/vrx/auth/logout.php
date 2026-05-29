<?php
/**
 * VRX Studio — Logout
 */
require_once __DIR__ . '/../core/config.php';
session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
