<?php
// Landing page for PayMongo's success/cancel redirect on mobile-app checkouts.
$bookingId = (int) ($_GET['booking_id'] ?? 0);
$status = ($_GET['payment'] ?? '') === 'success' ? 'success' : 'cancelled';
$path = 'payment/' . $bookingId . '?status=' . $status;
$link = 'pmscar://' . $path;
// Chrome on Android ignores scripted custom-scheme jumps without a tap; an intent:// URL works.
$intent = 'intent://' . $path . '#Intent;scheme=pmscar;package=com.pmscarrental;end';
$isAndroid = stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Android') !== false;
$open = $isAndroid ? $intent : $link;
$title = $status === 'success' ? 'Payment received' : 'Payment cancelled';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<style>body{font-family:sans-serif;text-align:center;padding:48px 20px}a.btn{display:inline-block;margin-top:24px;padding:14px 28px;background:#1a73e8;color:#fff;border-radius:8px;text-decoration:none}p.sub{color:#666}</style>
</head>
<body>
<h2><?= htmlspecialchars($title) ?></h2>
<p>Returning to the PMS app…</p>
<a class="btn" href="<?= htmlspecialchars($open) ?>">Open PMS app</a>
<p class="sub">Not on your phone, or the app didn't open? Open the PMS app and check My Transactions.</p>
<script>setTimeout(function () { window.location.href = <?= json_encode($open) ?>; }, 300);</script>
</body>
</html>
