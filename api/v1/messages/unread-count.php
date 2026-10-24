<?php
// Unread admin->customer message count, for the app's nav badge.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/messaging.php';

$user = api_require_auth($pdo);
api_success(['count' => messaging_user_unread($pdo, (int) $user['id'])]);
