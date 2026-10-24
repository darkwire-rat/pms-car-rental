<?php
// Customer conversation list + thread view. ?id=N opens one thread the customer owns.
session_start();
require 'db.php'; // $pdo
require_once __DIR__ . '/lib/messaging.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: index.php');
    exit;
}
$userId = (int) $user['id'];

$openId = (int) ($_GET['id'] ?? 0);
$thread = null;
$threadMessages = [];

if ($openId > 0 && messaging_user_owns_conversation($pdo, $openId, $userId)) {
    $t = $pdo->prepare("
        SELECT c.*, b.booking_ref, v.title AS vehicle_title
        FROM conversations c
        LEFT JOIN bookings b ON b.id = c.booking_id
        LEFT JOIN vehicles v ON v.id = b.vehicle_id
        WHERE c.id = ?
    ");
    $t->execute([$openId]);
    $thread = $t->fetch(PDO::FETCH_ASSOC);

    messaging_mark_thread_read($pdo, $openId, 'user');
    $m = $pdo->prepare("
        SELECT sender_type, body, created_at
        FROM conversation_messages
        WHERE conversation_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    $m->execute([$openId]);
    $threadMessages = $m->fetchAll(PDO::FETCH_ASSOC);
}

$list = $pdo->prepare("
    SELECT c.id, c.subject, c.status, c.last_message_at, c.booking_id,
           b.booking_ref, v.title AS vehicle_title,
           (SELECT COUNT(*) FROM conversation_messages m
            WHERE m.conversation_id = c.id AND m.sender_type = 'admin' AND m.read_at IS NULL) AS unread,
           (SELECT m2.body FROM conversation_messages m2
            WHERE m2.conversation_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_body
    FROM conversations c
    LEFT JOIN bookings b ON b.id = c.booking_id
    LEFT JOIN vehicles v ON v.id = b.vehicle_id
    WHERE c.user_id = ?
    ORDER BY (unread > 0) DESC, c.last_message_at DESC
");
$list->execute([$userId]);
$conversations = $list->fetchAll(PDO::FETCH_ASSOC);

function my_msg_time(string $ts): string
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PMS Car Rental — Messages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
  <?php include 'includes/client_navbar.php'; ?>
  <div style="height:80px"></div>

  <main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
      <h1 class="h3 mb-0">Messages</h1>
      <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#newMessagePanel" aria-expanded="false" aria-controls="newMessagePanel">
        <i class="fas fa-pen me-1" aria-hidden="true"></i>Contact PMS
      </button>
    </div>
    <div class="collapse mb-4" id="newMessagePanel">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h2 class="h6 mb-1">Start a conversation</h2>
          <p class="small text-body-secondary">Have a question or need assistance? Send a message to the PMS team.</p>
          <form id="newMessageForm">
            <label for="newMessageSubject" class="form-label">Subject</label>
            <input type="text" class="form-control mb-3" id="newMessageSubject" maxlength="150" placeholder="How can we help?" required>
            <label for="newMessageBody" class="form-label">Message</label>
            <textarea class="form-control mb-3" id="newMessageBody" rows="4" maxlength="2000" placeholder="Write your message..." required></textarea>
            <div class="alert alert-danger d-none mb-3 py-2 small" id="newMessageError"></div>
            <button type="submit" class="btn btn-primary">Send message</button>
          </form>
        </div>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="list-group">
          <?php if (!$conversations): ?>
            <div class="list-group-item text-body-secondary small">You have no messages yet.</div>
          <?php endif; ?>
          <?php foreach ($conversations as $c): ?>
            <a href="my_messages.php?id=<?php echo (int) $c['id']; ?>"
               class="list-group-item list-group-item-action <?php echo $openId === (int) $c['id'] ? 'active' : ''; ?>">
              <div class="d-flex justify-content-between">
                <span class="text-truncate <?php echo $c['unread'] > 0 ? 'fw-bold' : ''; ?>"><?php echo htmlspecialchars($c['subject']); ?></span>
                <?php if ($c['unread'] > 0): ?><span class="badge bg-danger rounded-pill ms-2"><?php echo (int) $c['unread']; ?></span><?php endif; ?>
              </div>
              <div class="small text-truncate <?php echo $openId === (int) $c['id'] ? '' : 'text-body-secondary'; ?>"><?php echo htmlspecialchars(mb_strimwidth((string) $c['last_body'], 0, 60, '…')); ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-lg-8">
        <?php if (!$thread): ?>
          <div class="card border-0 shadow-sm"><div class="card-body text-body-secondary">
            <?php echo $openId > 0 ? 'Conversation not found.' : 'Select a conversation to read it.'; ?>
          </div></div>
        <?php else: ?>
          <div class="card border-0 shadow-sm" id="threadCard" data-conversation-id="<?php echo (int) $thread['id']; ?>">
            <div class="card-header bg-body">
              <h2 class="h6 mb-0"><?php echo htmlspecialchars($thread['subject']); ?></h2>
              <?php if ($thread['booking_id']): ?>
                <div class="small text-body-secondary">
                  <a href="transactions.php">Booking <?php echo htmlspecialchars($thread['booking_ref'] ?: ('#' . $thread['booking_id'])); ?></a>
                </div>
              <?php endif; ?>
            </div>
            <div class="card-body" id="threadMessages" style="max-height:56vh;overflow-y:auto;">
              <?php foreach ($threadMessages as $m): ?>
                <?php $mine = $m['sender_type'] === 'user'; ?>
                <div class="d-flex mb-3 <?php echo $mine ? 'justify-content-end' : ''; ?>">
                  <div class="p-2 rounded-3 <?php echo $mine ? 'bg-primary text-white' : 'bg-body-tertiary'; ?>" style="max-width:75%;">
                    <div class="small"><?php echo nl2br(htmlspecialchars($m['body'])); ?></div>
                    <div class="small <?php echo $mine ? 'text-white-50' : 'text-body-secondary'; ?> mt-1">
                      <?php echo $mine ? 'You' : 'PMS'; ?> · <?php echo my_msg_time($m['created_at']); ?>
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
  </main>

  <?php include 'includes/client_footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="js/messaging.js?v=<?php echo filemtime(__DIR__ . '/js/messaging.js'); ?>"></script>
</body>
</html>
