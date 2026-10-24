<?php
// Lists vouchers that are actually usable right now.
require_once __DIR__ . '/../common/bootstrap.php';

$stmt = $pdo->query(
  "SELECT code, discount_amount, discount_pct FROM vouchers
   WHERE is_active = 1 AND usage_count < usage_limit
   ORDER BY created_at DESC"
);
api_success(['vouchers' => $stmt->fetchAll()]);
