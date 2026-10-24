<?php
// Customer's structured home address on their profile.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in first']);
    exit;
}
$userId = (int) $_SESSION['user']['id'];

csrf_require();

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$fields = [
    'phone'         => 32,
    'address_line1' => 150,
    'address_line2' => 150,
    'city'          => 100,
    'province'      => 100,
    'postal_code'   => 16,
];
$values = [];
foreach ($fields as $name => $max) {
    $values[$name] = mb_substr(trim((string) ($data[$name] ?? '')), 0, $max);
}

if ($values['address_line1'] === '' || $values['city'] === '' || $values['province'] === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Address line 1, city and province are required.']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE users
    SET phone = ?, address_line1 = ?, address_line2 = ?, city = ?, province = ?, postal_code = ?
    WHERE id = ?
");
$stmt->execute([
    $values['phone'], $values['address_line1'], $values['address_line2'],
    $values['city'], $values['province'], $values['postal_code'], $userId,
]);

echo json_encode(['success' => true]);
