<?php
/**
 * VRX Studio — Session Status API
 * Returns current user session as JSON for JS consumption.
 * Called by client-side pages (gallery, upload, qr, etc.)
 */
require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo vrx_session_json();
