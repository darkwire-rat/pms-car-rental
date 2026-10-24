<?php
// GET /api/v1/auth/me.php — returns the authenticated customer.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);
api_success(['user' => $user]);
