<?php
// The "View all" target for the bell dropdown.
session_start();
require 'db.php';
require_once __DIR__ . '/lib/notify_read.php';

$who = notification_audience($_GET['audience'] ?? null);
if (!$who) {
    header('Location: index.php');
    exit;
}
$isAdmin = $who['type'] === 'admin';

$stmt = $pdo->prepare("
    SELECT id, type, title, body, link_url, booking_id, is_read, created_at
    FROM notifications
    WHERE recipient_type = ? AND recipient_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 100
");
$stmt->execute([$who['type'], $who['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$unread = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread++;
}
?>
<!doctype html>
<html lang="en">
<head>
  <script>
    (function () {
      var stored = localStorage.getItem('pms-theme');
      var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PMS — Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="<?php echo $isAdmin ? 'bg-body-tertiary d-flex min-vh-100' : ''; ?>" <?php echo $isAdmin ? 'id="adminLayout"' : ''; ?>>

<?php
if ($isAdmin) {
    include 'includes/admin_sidebar.php';
} else {
    include 'includes/client_navbar.php';
    echo '<div style="height:80px"></div>';
}
?>

<?php if ($isAdmin): ?>
<main class="flex-grow-1">
  <?php $topbarTitle = 'Notifications'; include 'includes/admin_topbar.php'; ?>
  <div class="container-fluid py-4">
<?php else: ?>
<main class="container py-5">
<?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 mb-0">Notifications</h1>
      <?php if ($unread > 0): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" id="markAllReadBtn"
                data-audience="<?php echo $who['type']; ?>">Mark all read (<?php echo $unread; ?>)</button>
      <?php endif; ?>
    </div>

    <?php if (!$notifications): ?>
      <p class="text-body-secondary">You have no notifications.</p>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($notifications as $n): ?>
          <?php
            $tag = !empty($n['link_url']) ? 'a' : 'div';
            $href = !empty($n['link_url']) ? ' href="' . htmlspecialchars($n['link_url'], ENT_QUOTES) . '"' : '';
          ?>
          <<?php echo $tag . $href; ?> class="list-group-item list-group-item-action <?php echo $n['is_read'] ? '' : 'bg-body-tertiary'; ?>">
            <div class="d-flex justify-content-between">
              <span class="<?php echo $n['is_read'] ? '' : 'fw-semibold'; ?>"><?php echo htmlspecialchars($n['title']); ?></span>
              <small class="text-body-secondary ms-2 text-nowrap"><?php echo date('M j, Y · g:i A', strtotime($n['created_at'])); ?></small>
            </div>
            <?php if (!empty($n['body'])): ?>
              <div class="small text-body-secondary"><?php echo htmlspecialchars($n['body']); ?></div>
            <?php endif; ?>
          </<?php echo $tag; ?>>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

<?php if ($isAdmin): ?>
  </div>
</main>
<?php else: ?>
</main>
<?php include 'includes/client_footer.php'; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
<script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
<?php if ($isAdmin): ?>
<script src="js/admin.js?v=<?php echo filemtime(__DIR__ . '/js/admin.js'); ?>"></script>
<?php endif; ?>
<script>
  document.getElementById('markAllReadBtn')?.addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    fetch('notifications_mark_read.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ audience: btn.dataset.audience })
    }).then(function () { location.reload(); });
  });
</script>
</body>
</html>
