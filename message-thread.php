<?php
require 'includes/auth.php';
require 'includes/db.php';

$user_id = $_SESSION['user_id'];
$other_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
if ($other_id <= 0) {
  header('Location: messages.php');
  exit;
}

// Verify other user exists
$stmt = $conn->prepare('SELECT username FROM users WHERE id = ?');
$stmt->bind_param('i', $other_id);
$stmt->execute();
$stmt->bind_result($other_username);
if (!$stmt->fetch()) {
  $stmt->close();
  header('Location: messages.php');
  exit;
}
$stmt->close();

if (!empty($_POST['body'])) {
  $body = trim($_POST['body']);
  if ($body !== '') {
    $stmt = $conn->prepare('INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $user_id, $other_id, $body);
    $stmt->execute();
    $stmt->close();
    header("Location: message-thread.php?user=$other_id");
    exit;
  }
}

// Mark messages as read
$update = $conn->prepare('UPDATE messages SET read_at = NOW() WHERE sender_id = ? AND recipient_id = ? AND read_at IS NULL');
$update->bind_param('ii', $other_id, $user_id);
$update->execute();
$update->close();

function format_message($text) {
  $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
  $text = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $text);
  return nl2br($text);
}

$stmt = $conn->prepare('SELECT m.sender_id, m.recipient_id, m.body, m.created_at, u.username AS sender_name
  FROM messages m JOIN users u ON m.sender_id = u.id
  WHERE (m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?)
  ORDER BY m.created_at ASC');
$stmt->bind_param('iiii', $user_id, $other_id, $other_id, $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<?php require 'includes/layout.php'; ?>
  <title>Conversation with <?= htmlspecialchars($other_username) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <h2><span data-i18n="conversationWith">Conversation with</span> <?= htmlspecialchars($other_username) ?></h2>
  <div class="message-thread">
    <?php foreach ($messages as $msg): ?>
      <div class="message">
        <div class="message-sender"><?= htmlspecialchars($msg['sender_name']) ?>:</div>
        <div class="message-body"><?= format_message($msg['body']) ?></div>
        <div class="message-time"><?= htmlspecialchars($msg['created_at']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <form method="post" class="message-form">
    <div class="message-tools">
      <button type="button" data-format="bold"><strong>B</strong></button>
      <button type="button" data-format="italic"><em>I</em></button>
      <button type="button" data-insert="😊">😊</button>
      <button type="button" data-insert="( ͡° ͜ʖ ͡°)">( ͡° ͜ʖ ͡°)</button>
    </div>
    <textarea name="body" required></textarea>
    <button type="submit" class="btn" data-i18n="send">Send</button>
  </form>
  <script type="module" src="/assets/message-tools.js"></script>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
