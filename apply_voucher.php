<?php
session_start();
include 'db_connect.php';
require 'db.php';


header('Content-Type: application/json');


// ✅ Fix 1: Correct session key (your other files use $_SESSION['user']['id'])
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}


$user_id = (int)$_SESSION['user']['id'];


// ✅ Fix 2: Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}


// ✅ Fix 3: Safely read JSON input
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
    exit;
}


$voucher_code = trim($data['voucher_code'] ?? '');
$total_amount = isset($data['total_amount']) ? floatval($data['total_amount']) : 0;


if (empty($voucher_code) || $total_amount <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters — voucher code or total amount missing',
        'received' => $data
    ]);
    exit;
}


// ✅ Fetch voucher
$stmt = $conn->prepare("SELECT * FROM vouchers WHERE code = ?");
$stmt->bind_param("s", $voucher_code);
$stmt->execute();
$result = $stmt->get_result();
$voucher = $result->fetch_assoc();


// ✅ Check if voucher exists
if (!$voucher) {
    echo json_encode(['success' => false, 'message' => 'Voucher not found']);
    exit;
}


// ✅ Compute discount
$discount = 0;
if (!empty($voucher['discount_pct']) && $voucher['discount_pct'] > 0) {
    $discount = $total_amount * ($voucher['discount_pct'] / 100);
} elseif (!empty($voucher['discount_amount']) && $voucher['discount_amount'] > 0) {
    $discount = $voucher['discount_amount'];
}


// ✅ Prevent negative totals
$final_total = max(0, $total_amount - $discount);


// ✅ Handle voucher usage on booking completion
if (!empty($data['complete'])) {
    // Start a transaction to ensure data consistency
    $conn->begin_transaction();
   
    try {
        // Get fresh data to prevent race conditions
        $stmt = $conn->prepare("SELECT usage_count, usage_limit FROM vouchers WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $voucher['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $current = $result->fetch_assoc();
       
        // Check if we can still use this voucher
        if ($current['usage_count'] >= $current['usage_limit']) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'This voucher has reached its usage limit']);
            exit;
        }
       
        // Increment the usage count
        $new_count = $current['usage_count'] + 1;
        $updateStmt = $conn->prepare("UPDATE vouchers SET usage_count = ? WHERE id = ?");
        $updateStmt->bind_param("ii", $new_count, $voucher['id']);
        $updateStmt->execute();
       
        // No need to deactivate, just let usage_limit control availability
       
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error processing voucher: ' . $e->getMessage()]);
        exit;
    }
}


// ✅ Success output
echo json_encode([
    'success' => true,
    'discount' => $discount,
    'final_total' => $final_total,
    'voucher_code' => $voucher_code,
    'message' => 'Voucher applied successfully'
]);
exit;
?>
