<?php
// Active pickup/drop-off branches for the mobile booking form.
require_once __DIR__ . '/../common/bootstrap.php';

$stmt = $pdo->query(
  "SELECT id, name, address, city FROM locations
   WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
);
api_success(['locations' => $stmt->fetchAll()]);
