<?php
require '../includes/auth.php';
require '../includes/csrf.php';
require '../includes/user.php';

$threads = [];
if ($stmt = $conn->prepare("SELECT ft.id, ft.title, u.id, u.username, ft.created_at FROM forum_threads ft JOIN users u ON ft.user_id = u.id ORDER BY ft.created_at DESC")) {
  if ($stmt->execute()) {
    $stmt->bind_result($tid, $title, $uid, $uname, $created);
    while ($stmt->fetch()) {
      $threads[] = ['id' => $tid, 'title' => $title, 'user_id' => $uid, 'username' => $uname, 'created_at' => $created];
    }
  }
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title !== '' && $content !== '') {
      if ($stmt = $conn->prepare("INSERT INTO forum_threads (title, user_id, created_at) VALUES (?, ?, NOW())")) {
        $stmt->bind_param('si', $title, $_SESSION['user_id']);
        if ($stmt->execute()) {
          $thread_id = $stmt->insert_id;
          $stmt->close();
          if ($pst = $conn->prepare("INSERT INTO forum_posts (thread_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())")) {
            $pst->bind_param('iis', $thread_id, $_SESSION['user_id'], $content);
            $pst->execute();
            $pst->close();
            header("Location: thread.php?id=" . $thread_id);
            exit;
          }
        }
      }
    } else {
      $error = 'Title and content required.';
    }
  }
}
?>
<?php require '../includes/layout.php'; ?>
  <title>Forum Threads</title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main>
  <h2>Forum Threads</h2>
  <?php if (!empty($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
  <ul>
    <?php foreach ($threads as $thread): ?>
      <li>
        <a href="thread.php?id=<?= $thread['id']; ?>"><?= htmlspecialchars($thread['title']); ?></a>
        by <?= username_with_avatar($conn, $thread['user_id'], $thread['username']); ?> on <?= htmlspecialchars($thread['created_at']); ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <h3>Start New Thread</h3>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
    <div>
      <input type="text" name="title" placeholder="Thread title">
    </div>
    <div>
      <textarea name="content" placeholder="Say something..."></textarea>
    </div>
    <button type="submit">Post Thread</button>
  </form>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
