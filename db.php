<?php
// PHP's default timezone is UTC, but every stored/compared datetime in this app is Asia/Manila wall-clock time.
date_default_timezone_set('Asia/Manila');

// The connection credential lives in config.local.php instead of hardcoded here — example and.
$configPath = __DIR__ . '/config.local.php';
if (!file_exists($configPath)) {
  echo "Missing config.local.php. Copy config.local.php.example to config.local.php and fill in this environment's database credential.";
  exit;
}
$config = require $configPath;

$host = $config['host'];
$db   = $config['db'];
$user = $config['user'];
$pass = $config['pass'];
$charset = $config['charset'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
  $pdo->exec("SET time_zone = '+08:00'"); // Asia/Manila, same as db_connect.php
} catch (PDOException $e) {
  // If the DB connection fails, show a simple message and stop
  echo "Database connection failed: " . htmlspecialchars($e->getMessage());
  exit;
}
