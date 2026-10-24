<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Please log in first']);
  exit;
}

$stmt = $pdo->prepare("
  SELECT b.id, b.booking_ref, b.vehicle_id, v.title AS vehicle_title,
         b.status, b.rental_date, b.return_date, b.total_amount
  FROM bookings b
  LEFT JOIN vehicles v ON b.vehicle_id = v.id
  WHERE b.user_id = ? AND b.status IN ('pending_review','info_requested','approved','paid')
  ORDER BY b.id DESC
");
$stmt->execute([$_SESSION['user']['id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'bookings' => $bookings]);
