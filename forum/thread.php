<?php
require_once __DIR__ . '/../includes/require-auth.php';
require '../includes/csrf.php';
require '../includes/user.php';

$thread_id = (int)($_GET['id'] ?? 0);
$reply_to = (int)($_GET['reply_to'] ?? 0);
if ($thread_id <= 0) {
  die('Invalid thread');
}

$thread = null;
if ($stmt = $conn->prepare("SELECT ft.title, ft.status, u.id, u.username FROM forum_threads ft JOIN users u ON ft.user_id = u.id WHERE ft.id = ?")) {
  $stmt->bind_param('i', $thread_id);
  if ($stmt->execute()) {
    $stmt->bind_result($ttitle, $tstatus, $tuid, $tuser);
    if ($stmt->fetch()) {
      $thread = ['title' => $ttitle, 'status' => $tstatus, 'user_id' => $tuid, 'username' => $tuser];
    }
  }
  $stmt->close();
}
if (!$thread) {
  die('Thread not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } elseif (is_admin() && isset($_POST['close'])) {
    $stmt = $conn->prepare("UPDATE forum_threads SET status='closed' WHERE id=?");
    $stmt->bind_param('i', $thread_id);
    $stmt->execute();
    $stmt->close();
    header("Location: thread.php?id=" . $thread_id);
    exit;
  } elseif (is_admin() && isset($_POST['delist'])) {
    $stmt = $conn->prepare("UPDATE forum_threads SET status='delisted' WHERE id=?");
    $stmt->bind_param('i', $thread_id);
    $stmt->execute();
    $stmt->close();
    header('Location: index.php');
    exit;
  } elseif (is_admin() && isset($_POST['delete'])) {
    $stmt = $conn->prepare("DELETE FROM forum_threads WHERE id=?");
    $stmt->bind_param('i', $thread_id);
    $stmt->execute();
    $stmt->close();
    header('Location: index.php');
    exit;
  } else {
    $content = trim($_POST['content'] ?? '');
    $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    if ($content !== '') {
      if ($stmt = $conn->prepare("INSERT INTO forum_posts (thread_id, user_id, content, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())")) {
        $stmt->bind_param('iisi', $thread_id, $_SESSION['user_id'], $content, $parent_id);
        $stmt->execute();
        $stmt->close();
        header("Location: thread.php?id=" . $thread_id);
        exit;
      }
    } else {
      $error = 'Content required.';
    }
  }
}

$posts_by_parent = [];
if ($pst = $conn->prepare("SELECT fp.id, fp.parent_id, fp.content, fp.created_at, u.id, u.username FROM forum_posts fp JOIN users u ON fp.user_id = u.id WHERE fp.thread_id = ? ORDER BY fp.created_at")) {
  $pst->bind_param('i', $thread_id);
  if ($pst->execute()) {
    $pst->bind_result($pid, $pparent, $pcontent, $pcreated, $puid, $puname);
    while ($pst->fetch()) {
      $parent = $pparent ?? 0;
      $posts_by_parent[$parent][] = [
        'id' => $pid,
        'content' => $pcontent,
        'created_at' => $pcreated,
        'user_id' => $puid,
        'username' => $puname
      ];
    }
  }
  $pst->close();
}

function render_posts($parent_id = 0, $depth = 0) {
  global $posts_by_parent, $conn, $thread_id;
  if (empty($posts_by_parent[$parent_id])) {
    return;
  }
  foreach ($posts_by_parent[$parent_id] as $post) {
    echo '<div class="post level-' . $depth . '">';
    echo '<strong>' . username_with_avatar($conn, $post['user_id'], $post['username']) . '</strong> on ' . htmlspecialchars($post['created_at']);
    echo '<p>' . nl2br(htmlspecialchars($post['content'])) . '</p>';
    echo '<form method="get" action="thread.php#reply-form" class="inline-reply">';
    echo '<input type="hidden" name="id" value="' . $thread_id . '">';
    echo '<input type="hidden" name="reply_to" value="' . $post['id'] . '">';
    echo '<button type="submit" aria-label="Reply to this post">Reply</button>';
    echo '</form>';
    if (!empty($posts_by_parent[$post['id']])) {
      echo '<div class="post-children">';
      render_posts($post['id'], $depth + 1);
      echo '</div>';
    }
    echo '</div>';
  }
}
?>
<?php require '../includes/layout.php'; ?>
  <title><?= htmlspecialchars($thread['title']); ?></title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main>
  <h2><?= htmlspecialchars($thread['title']); ?></h2>
  <p>Started by <?= username_with_avatar($conn, $thread['user_id'], $thread['username']); ?></p>
  <?php if (is_admin()): ?>
    <form method="post" style="display:inline;" onsubmit="return confirm('Close thread?');">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <button type="submit" name="close">Close</button>
    </form>
    <form method="post" style="display:inline;" onsubmit="return confirm('Delist thread?');">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <button type="submit" name="delist">Delist</button>
    </form>
    <form method="post" style="display:inline;" onsubmit="return confirm('Delete thread?');">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <button type="submit" name="delete">Delete</button>
    </form>
  <?php endif; ?>
  <?php if (!empty($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
<?php render_posts(); ?>
  <?php if ($thread['status'] === 'closed'): ?>
    <p>This thread is closed.</p>
  <?php else: ?>
    <h3>Reply</h3>
    <form id="reply-form" method="post">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <input type="hidden" name="parent_id" value="<?= $reply_to ?: '' ?>">
      <textarea name="content" placeholder="Your reply..."></textarea>
      <button type="submit">Post Reply</button>
    </form>
  <?php endif; ?>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
