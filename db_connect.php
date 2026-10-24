<?php
// Php's PDO one — sec 7.3.
date_default_timezone_set('Asia/Manila');

// Same config.local.php db.php reads — one credential, one file, shared by both connection paths. example and.
$configPath = __DIR__ . '/config.local.php';
if (!file_exists($configPath)) {
  die("Missing config.local.php. Copy config.local.php.example to config.local.php and fill in this environment's database credential.");
}
$config = require $configPath;

$host = $config['host'];
$user = $config['user'];
$pass = $config['pass'];
$dbname = $config['db'];

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
$conn->query("SET time_zone = '+08:00'"); // Asia/Manila
?>
