<?php
/* Read-side helper for the notification endpoints. */
function notification_audience(?string $want = null): ?array
{
    $hasAdmin = !empty($_SESSION['admin_id']);
    $hasUser  = !empty($_SESSION['user']['id']);

    if ($want === 'admin' && $hasAdmin) {
        return ['type' => 'admin', 'id' => 0];
    }
    if ($want === 'user' && $hasUser) {
        return ['type' => 'user', 'id' => (int) $_SESSION['user']['id']];
    }
    // A shell that asked for a specific audience must never be answered with the other audience's session -- otherwise a leftover admin.
    if ($want === 'admin' || $want === 'user') {
        return null;
    }
    if ($hasAdmin) {
        return ['type' => 'admin', 'id' => 0];
    }
    if ($hasUser) {
        return ['type' => 'user', 'id' => (int) $_SESSION['user']['id']];
    }
    return null;
}
