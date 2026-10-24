<?php
// csrf.php — Phase 9. Returns the current session's CSRF token for js/csrf.js.
session_start();
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['token' => csrf_token()]);
