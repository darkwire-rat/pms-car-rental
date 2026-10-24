<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
  header('Location: admin-login.php');
  exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Collect form data
  $title = trim($_POST['title'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $price_per_day = $_POST['price_per_day'] ?? '';
  $units_total = $_POST['units_total'] ?? '';
  $is_active = isset($_POST['is_active']) ? 1 : 0; // Checkbox
  $seats = $_POST['seats'] ?? '';
  $fuel = trim($_POST['fuel'] ?? '');
  $transmission = trim($_POST['transmission'] ?? '');

  if ($title === '' || $category === '' || !is_numeric($price_per_day) || !is_numeric($units_total) || !is_numeric($seats)) {
    header("Location: admin_vehicles.php?error=" . urlencode('Invalid form data.'));
    exit;
  }

  // Handle image upload — validate real image content, not just extension/MIME
  $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
  $imageInfo = @exif_imagetype($_FILES["thumbnail"]["tmp_name"] ?? '');

  if (!$imageInfo || !in_array($imageInfo, $allowedTypes, true)) {
    header("Location: admin_vehicles.php?error=" . urlencode('Invalid image file.'));
    exit;
  }

  $ext = image_type_to_extension($imageInfo); // e.g. ".jpg"
  $imageName = bin2hex(random_bytes(8)) . $ext;
  $targetDir = "assets/";
  $targetFilePath = $targetDir . $imageName;

  if (move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $targetFilePath)) {
    $sql = "INSERT INTO vehicles (
      title, category, price_per_day, units_total, is_active,
      thumbnail, created_at, seats, fuel, transmission
      ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      'ssdiisiss',
      $title, $category, $price_per_day, $units_total, $is_active,
      $imageName, $seats, $fuel, $transmission
    );
    if ($stmt->execute()) {
      header("Location: admin_vehicles.php?added=1");
      exit;
    } else {
      @unlink($targetFilePath);
      header("Location: admin_vehicles.php?error=" . urlencode('Database error.'));
      exit;
    }
  } else {
    header("Location: admin_vehicles.php?error=" . urlencode('Failed to upload image.'));
    exit;
  }
}
