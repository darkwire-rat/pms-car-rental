<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
  header('Location: admin-login.php');
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['id']) || !is_numeric($_POST['id'])) {
  header("Location: admin_vehicles.php?error=" . urlencode('Invalid request.'));
  exit;
}

$id = (int)$_POST['id'];
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$price_per_day = $_POST['price_per_day'] ?? '';
$units_total = $_POST['units_total'] ?? '';
$seats = $_POST['seats'] ?? '';
$fuel = trim($_POST['fuel'] ?? '');
$transmission = trim($_POST['transmission'] ?? '');
$is_active = isset($_POST['is_active']) ? 1 : 0;

if ($title === '' || $category === '' || !is_numeric($price_per_day) || !is_numeric($units_total) || !is_numeric($seats)) {
  header("Location: admin_vehicles.php?error=" . urlencode('Invalid form data.'));
  exit;
}

$newImageName = null;

// Handle new image upload if provided — validate real image content
if (!empty($_FILES["image"]["name"])) {
  $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
  $imageInfo = @exif_imagetype($_FILES["image"]["tmp_name"] ?? '');

  if (!$imageInfo || !in_array($imageInfo, $allowedTypes, true)) {
    header("Location: admin_vehicles.php?error=" . urlencode('Invalid image file.'));
    exit;
  }

  $ext = image_type_to_extension($imageInfo);
  $imageName = bin2hex(random_bytes(8)) . $ext;
  $targetFilePath = "assets/" . $imageName;

  if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
    $newImageName = $imageName;
  } else {
    header("Location: admin_vehicles.php?error=" . urlencode('Failed to upload image.'));
    exit;
  }
}

if ($newImageName !== null) {
  $sql = "UPDATE vehicles SET
            title = ?, category = ?, price_per_day = ?, units_total = ?,
            seats = ?, fuel = ?, transmission = ?, is_active = ?, thumbnail = ?
          WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param(
    'ssdiissisi',
    $title, $category, $price_per_day, $units_total,
    $seats, $fuel, $transmission, $is_active, $newImageName, $id
  );
} else {
  $sql = "UPDATE vehicles SET
            title = ?, category = ?, price_per_day = ?, units_total = ?,
            seats = ?, fuel = ?, transmission = ?, is_active = ?
          WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param(
    'ssdiissii',
    $title, $category, $price_per_day, $units_total,
    $seats, $fuel, $transmission, $is_active, $id
  );
}

if ($stmt->execute()) {
  header("Location: admin_vehicles.php?updated=1");
  exit;
} else {
  header("Location: admin_vehicles.php?error=" . urlencode('Database error.'));
  exit;
}
