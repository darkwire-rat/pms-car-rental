<?php
// Same as auth/me.php, kept as its own endpoint to match the spec's profile/ grouping.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);
api_success(['user' => $user]);
