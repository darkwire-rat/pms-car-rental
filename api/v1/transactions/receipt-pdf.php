<?php
// Renders the receipt as a real PDF with the PMS logo, instead of JSON.
header('Content-Type: application/json');
ini_set('display_errors', 0);

require_once __DIR__ . '/../common/response.php';
require_once __DIR__ . '/../common/auth.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../lib/fpdf.php';

$user = api_require_auth($pdo);
$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
if (!$bookingId) {
  api_error('booking_id is required.', ['booking_id' => 'booking_id is required.'], 422);
}

$stmt = $pdo->prepare(
  "SELECT b.booking_ref, b.rental_date, b.return_date, b.pickup_time, b.dropoff_time,
          b.rate, b.discount, b.total_amount, b.paid, b.status AS booking_status,
          b.contact_number, b.security_deposit, b.deposit_status,
          pl.name AS pickup_location_name, dl.name AS dropoff_location_name,
          u.name AS customer_name,
          v.title AS vehicle_title
   FROM bookings b
   JOIN users u ON u.id = b.user_id
   LEFT JOIN vehicles v ON v.id = b.vehicle_id
   LEFT JOIN locations pl ON pl.id = b.pickup_location_id
   LEFT JOIN locations dl ON dl.id = b.dropoff_location_id
   WHERE b.id = ? AND b.user_id = ?"
);
$stmt->execute([$bookingId, $user['id']]);
$r = $stmt->fetch();

if (!$r) {
  api_error('Booking not found.', [], 404);
}
if (!in_array($r['booking_status'], ['approved', 'paid', 'active', 'returned', 'completed'], true)) {
  api_error('Receipt unavailable for this booking.', [], 409);
}

$change = round((float) $r['paid'] - (float) $r['total_amount'], 2);

// FPDF's core fonts only support Windows-1252, which does not include the peso sign.
function peso($v) {
  return 'PHP ' . number_format((float) $v, 2);
}

function receipt_row($pdf, $label, $value, $bold = false) {
  $pdf->SetFont('Helvetica', 'B', 10);
  $pdf->Cell(60, 7, $label, 0, 0);
  $pdf->SetFont('Helvetica', $bold ? 'B' : '', 10);
  $pdf->Cell(0, 7, $value, 0, 1);
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 20);

// FPDF's built-in PNG parser cannot handle alpha transparency and throws a fatal error on it.
$logoSource = __DIR__ . '/../../../assets/new-logo-bg-remove.png';
$logoForPdf = null;
if (file_exists($logoSource) && function_exists('imagecreatefrompng')) {
  $src = @imagecreatefrompng($logoSource);
  if ($src) {
    $w = imagesx($src);
    $h = imagesy($src);
    $flat = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($flat, 255, 255, 255);
    imagefill($flat, 0, 0, $white);
    imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
    $logoForPdf = sys_get_temp_dir() . '/pms_receipt_logo_' . md5($logoSource) . '.png';
    imagepng($flat, $logoForPdf);
    imagedestroy($src);
    imagedestroy($flat);
  }
}
if ($logoForPdf && file_exists($logoForPdf)) {
  $pdf->Image($logoForPdf, 20, 15, 22);
}

$pdf->SetXY(50, 18);
$pdf->SetFont('Helvetica', 'B', 16);
$pdf->Cell(0, 8, 'PMS Car Rental', 0, 1);
$pdf->SetX(50);
$pdf->SetFont('Helvetica', '', 11);
$pdf->Cell(0, 6, 'Official Transaction Receipt', 0, 1);

$pdf->SetY(45);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

receipt_row($pdf, 'Booking Reference', $r['booking_ref']);
receipt_row($pdf, 'Customer', $r['customer_name']);
receipt_row($pdf, 'Contact Number', $r['contact_number']);

$pdf->Ln(4);
receipt_row($pdf, 'Vehicle', $r['vehicle_title']);
receipt_row($pdf, 'Rental Period', $r['rental_date'] . '  to  ' . $r['return_date']);
receipt_row($pdf, 'Pickup Time', $r['pickup_time']);
receipt_row($pdf, 'Return Time', $r['dropoff_time']);
if (!empty($r['pickup_location_name'])) {
  receipt_row($pdf, 'Pick-up / Drop-off', $r['pickup_location_name'] . ' -> ' . ($r['dropoff_location_name'] ?: $r['pickup_location_name']));
}

$pdf->Ln(4);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

receipt_row($pdf, 'Rate', peso($r['rate']));
if ($r['discount'] > 0) {
  receipt_row($pdf, 'Discount', '-' . peso($r['discount']));
}
receipt_row($pdf, 'Total', peso($r['total_amount']), true);
receipt_row($pdf, 'Amount Paid', peso($r['paid']));
receipt_row($pdf, 'Change', peso($change), true);

$pdf->Ln(4);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

// Security deposit — a refundable liability.
receipt_row($pdf, 'Security Deposit', peso($r['security_deposit']) . ' (refundable)');
receipt_row($pdf, 'Deposit Status', ucwords(str_replace('_', ' ', $r['deposit_status'])));

$pdf->Ln(4);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

receipt_row($pdf, 'Booking Status', ucfirst($r['booking_status']));

$pdf->Ln(10);
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->SetTextColor(140, 140, 140);
$pdf->Cell(0, 5, 'Generated ' . date('Y-m-d H:i:s'), 0, 1);
$pdf->Cell(0, 5, 'Please present this receipt and a valid ID at pickup.', 0, 1);

$filename = 'PMS-Receipt-' . $r['booking_ref'] . '.pdf';
$pdf->Output('I', $filename);
