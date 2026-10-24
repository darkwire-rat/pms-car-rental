<?php
// Admin conversation inbox + thread view. ?id=N opens one thread.
session_start();
require 'db.php'; // $pdo
require_once __DIR__ . '/lib/messaging.php';
require_once __DIR__ . '/lib/booking_status.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$openId = (int) ($_GET['id'] ?? 0);
$thread = null;
$threadMessages = [];

if ($openId > 0) {
    $t = $pdo->prepare("
        SELECT c.*, u.name AS user_name, u.email AS user_email,
               b.booking_ref, v.title AS vehicle_title
        FROM conversations c
        JOIN users u ON u.id = c.user_id
        LEFT JOIN bookings b ON b.id = c.booking_id
        LEFT JOIN vehicles v ON v.id = b.vehicle_id
        WHERE c.id = ?
    ");
    $t->execute([$openId]);
    $thread = $t->fetch(PDO::FETCH_ASSOC);

    if ($thread) {
        messaging_mark_thread_read($pdo, $openId, 'admin');
        $m = $pdo->prepare("
            SELECT sender_type, sender_id, body, created_at
            FROM conversation_messages
            WHERE conversation_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $m->execute([$openId]);
        $threadMessages = $m->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Inbox: newest activity first, unread (has unseen customer messages) first.
$list = $pdo->query("
    SELECT c.id, c.subject, c.status, c.last_message_at, c.booking_id,
           u.name AS user_name,
           (SELECT COUNT(*) FROM conversation_messages m
            WHERE m.conversation_id = c.id AND m.sender_type = 'user' AND m.read_at IS NULL) AS unread,
           (SELECT m2.body FROM conversation_messages m2
            WHERE m2.conversation_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_body
    FROM conversations c
    JOIN users u ON u.id = c.user_id
    ORDER BY (unread > 0) DESC, c.last_message_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

function msg_time(string $ts): string
{
    return date('M j, Y · g:i A', strtotime($ts));
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
  <title>PMS — Messages</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">

<?php include 'includes/admin_sidebar.php'; ?>

<main class="flex-grow-1">
  <?php $topbarTitle = 'Messages'; include 'includes/admin_topbar.php'; ?>

  <div class="container-fluid py-4">
    <div class="row g-4">
      <!-- Inbox -->
      <div class="col-lg-4">
        <div class="card shadow-sm border-0">
          <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Conversations</h2></div>
          <div class="list-group list-group-flush" style="max-height:70vh;overflow-y:auto;">
            <?php if (!$list): ?>
              <div class="list-group-item text-body-secondary small">No conversations yet.</div>
            <?php endif; ?>
            <?php foreach ($list as $c): ?>
              <a href="admin_messages.php?id=<?php echo (int) $c['id']; ?>"
                 class="list-group-item list-group-item-action <?php echo $openId === (int) $c['id'] ? 'active' : ''; ?>">
                <div class="d-flex justify-content-between">
                  <span class="<?php echo $c['unread'] > 0 ? 'fw-bold' : ''; ?>"><?php echo htmlspecialchars($c['user_name']); ?></span>
                  <?php if ($c['unread'] > 0): ?><span class="badge bg-danger rounded-pill"><?php echo (int) $c['unread']; ?></span><?php endif; ?>
                </div>
                <div class="small text-truncate <?php echo $openId === (int) $c['id'] ? '' : 'text-body-secondary'; ?>"><?php echo htmlspecialchars($c['subject']); ?></div>
                <div class="small text-truncate <?php echo $openId === (int) $c['id'] ? '' : 'text-body-secondary'; ?>"><?php echo htmlspecialchars(mb_strimwidth((string) $c['last_body'], 0, 60, '…')); ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Thread -->
      <div class="col-lg-8">
        <?php if (!$thread): ?>
          <div class="card shadow-sm border-0"><div class="card-body text-body-secondary">
            <?php echo $openId > 0 ? 'Conversation not found.' : 'Select a conversation to read it.'; ?>
          </div></div>
        <?php else: ?>
          <div class="card shadow-sm border-0" id="threadCard" data-conversation-id="<?php echo (int) $thread['id']; ?>">
            <div class="card-header bg-body">
              <h2 class="h6 mb-1"><?php echo htmlspecialchars($thread['subject']); ?></h2>
              <div class="small text-body-secondary">
                <?php echo htmlspecialchars($thread['user_name']); ?> ·
                <?php echo htmlspecialchars($thread['user_email']); ?>
                <?php if ($thread['booking_id']): ?>
                  · <a href="admin_transaction_detail.php?id=<?php echo (int) $thread['booking_id']; ?>">Booking <?php echo htmlspecialchars($thread['booking_ref'] ?: ('#' . $thread['booking_id'])); ?></a>
                <?php endif; ?>
              </div>
            </div>
            <div class="card-body" id="threadMessages" style="max-height:56vh;overflow-y:auto;">
              <?php foreach ($threadMessages as $m): ?>
                <?php $mine = $m['sender_type'] === 'admin'; ?>
                <div class="d-flex mb-3 <?php echo $mine ? 'justify-content-end' : ''; ?>">
                  <div class="p-2 rounded-3 <?php echo $mine ? 'bg-primary text-white' : 'bg-body-tertiary'; ?>" style="max-width:75%;">
                    <div class="small"><?php echo nl2br(htmlspecialchars($m['body'])); ?></div>
                    <div class="small <?php echo $mine ? 'text-white-50' : 'text-body-secondary'; ?> mt-1">
                      <?php echo $mine ? 'You' : htmlspecialchars($thread['user_name']); ?> · <?php echo msg_time($m['created_at']); ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="card-footer bg-body">
              <form id="replyForm" class="d-flex gap-2">
                <textarea class="form-control" id="replyBody" rows="2" maxlength="2000" placeholder="Write a reply…" required></textarea>
                <button type="submit" class="btn btn-primary align-self-end">Send</button>
              </form>
              <div class="alert alert-danger d-none mt-2 mb-0 py-2 small" id="replyError"></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<script src="js/messaging.js?v=<?php echo filemtime(__DIR__ . '/js/messaging.js'); ?>"></script>
</body>
</html>
