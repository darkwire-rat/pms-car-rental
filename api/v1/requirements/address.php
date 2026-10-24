<?php
// Same fields + validation as update_address.php, Bearer-authed for the mobile client.
require_once __DIR__ . '/../common/bootstrap.php';

$user = api_require_auth($pdo);

$data = json_decode(file_get_contents('php://input'), true) ?: [];

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
  api_error('Address line 1, city and province are required.', [
    'address_line1' => 'Address line 1, city and province are required.',
  ], 422);
}

$stmt = $pdo->prepare(
  "UPDATE users
   SET phone = ?, address_line1 = ?, address_line2 = ?, city = ?, province = ?, postal_code = ?
   WHERE id = ?"
);
$stmt->execute([
  $values['phone'], $values['address_line1'], $values['address_line2'],
  $values['city'], $values['province'], $values['postal_code'], $user['id'],
]);

api_success([], 'Address saved.');
