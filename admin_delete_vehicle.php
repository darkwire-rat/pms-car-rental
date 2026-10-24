<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
  header('Location: admin-login.php');
  exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: admin_vehicles.php?error=" . urlencode('Invalid vehicle id.'));
  exit;
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

header("Location: admin_vehicles.php?deleted=1");
exit;
