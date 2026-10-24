<?php
// POST /api/v1/auth/logout.php — revokes the presented token only.
require_once __DIR__ . '/../common/bootstrap.php';

$raw = api_bearer_token();
if ($raw) {
  api_revoke_token($pdo, $raw);
}
api_success([], 'Logged out.');
