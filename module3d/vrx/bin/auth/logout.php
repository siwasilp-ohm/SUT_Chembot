<?php
/**
 * VRX Studio — Logout
 */
require_once __DIR__ . '/session.php';
vrx_logout();
header('Location: /vrx/auth/login.php');
exit;
