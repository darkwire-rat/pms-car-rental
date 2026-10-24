<?php
// Unread message count for the current viewer, for the sidebar / nav badge.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/messaging.php';
header('Content-Type: application/json');

if (!empty($_SESSION['admin_id'])) {
    echo json_encode(['authenticated' => true, 'audience' => 'admin', 'count' => messaging_admin_unread($pdo)]);
    exit;
}
if (!empty($_SESSION['user']['id'])) {
    echo json_encode(['authenticated' => true, 'audience' => 'user', 'count' => messaging_user_unread($pdo, (int) $_SESSION['user']['id'])]);
    exit;
}
echo json_encode(['authenticated' => false, 'count' => 0]);
