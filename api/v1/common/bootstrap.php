<?php
// Shared entry point for every /api/v1 endpoint.

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../../db.php'; // provides $pdo (PDO)

set_exception_handler(function ($e) {
  error_log('[api/v1] ' . $e->getMessage());
  api_error('Internal server error.', [], 500);
});
