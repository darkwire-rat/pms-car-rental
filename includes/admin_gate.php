<?php
// Admin access is gated by a secret URL keyword instead of a public "Login as Admin" button.
if (!defined('ADMIN_ACCESS_KEYWORD')) {
  // Set 'admin_access_key' in config.local.php (gitignored). Empty = admin login disabled.
  $adminGateConfig = file_exists(__DIR__ . '/../config.local.php') ? (require __DIR__ . '/../config.local.php') : [];
  define('ADMIN_ACCESS_KEYWORD', (string) ($adminGateConfig['admin_access_key'] ?? ''));
}

/* Blocks direct access to admin-login.php unless the request carries the secret keyword. */
function require_admin_keyword() {
  if (isset($_SESSION['admin_id'])) {
    return;
  }
  $key = $_GET['key'] ?? $_POST['key'] ?? '';
  if (ADMIN_ACCESS_KEYWORD === '' || !hash_equals(ADMIN_ACCESS_KEYWORD, (string) $key)) {
    header('Location: index.php');
    exit;
  }
}
