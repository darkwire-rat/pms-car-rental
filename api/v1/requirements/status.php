<?php
// The 3-item checklist bookings/create.php enforces server-side before a booking can be submitted.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/requirements.php';

$user = api_require_auth($pdo);
$status = requirements_user_status($pdo, (int) $user['id']);

api_success([
  'license' => $status['license'],
  'photo' => $status['photo'],
  'address' => $status['address'],
  'complete' => $status['complete'],
]);
