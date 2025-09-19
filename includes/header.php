<?php
if (session_status() === PHP_SESSION_NONE) {
  if (headers_sent($sentFile, $sentLine)) {
    trigger_error(
      sprintf('Unable to start session because headers were sent in %s on line %d.', $sentFile, $sentLine),
      E_USER_WARNING
    );
  } else {
    session_start();
  }
}

$headerRequiresAuth = !defined('HEADER_SKIP_AUTH') || HEADER_SKIP_AUTH !== true;
if ($headerRequiresAuth) {
  require_once __DIR__ . '/auth.php';
}
$db = require __DIR__ . '/db.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/notifications.php';

$username = '';
$status = '';
$unread_notifications = 0;
$unread_total = 0;
$pending_requests = 0;
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$uid = isset($user_id) ? (int)$user_id : (int)($_SESSION['user_id'] ?? 0);

$unread_messages = 0;
try {
  $unread_messages = count_unread_messages($db, $uid);
} catch (Throwable $e) {
  error_log('[header.php] unread count failed: ' . $e->getMessage());
  $unread_messages = 0;
}

if ($uid > 0):
  if (!empty($_SESSION['username']) && !empty($_SESSION['status'])):
    $username = $_SESSION['username'];
    $status = $_SESSION['status'];
  else:
    if ($stmt = $db->prepare('SELECT username, status FROM users WHERE id = ?')):
      $stmt->bind_param('i', $uid);
      $stmt->execute();
      $stmt->bind_result($username, $status);
      $stmt->fetch();
      $stmt->close();
      $_SESSION['username'] = $username;
      $_SESSION['status'] = $status;
    endif;
  endif;

  $unread_notifications = count_unread_notifications($db, $uid);
  $unread_total = $unread_messages + $unread_notifications;
  if ($stmt = $db->prepare('SELECT COUNT(*) FROM friends WHERE friend_id = ? AND status = "pending"')):
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($pending_requests);
    $stmt->fetch();
    $stmt->close();
  endif;
  $cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
endif;
?>

<header class="site-header">
  <div class="header-left">
    <a href="/index.php" class="logo-link">
      <img class="logo-img" src="/assets/logo.png" alt="SkuzE Logo" loading="lazy">
    </a>
    <h1 class="logo">
      <span class="logo-word">SKUZE</span>
      <span class="logo-pronounce">sk-uh-zee</span>
    </h1>
    <nav class="site-nav">
      <a href="/index.php" data-i18n="home">Home</a>
      <a href="/about.php" data-i18n="about">About</a>
      <a href="/help.php" data-i18n="help">Help/FAQ</a>
      <a href="/support.php" data-i18n="support">Support</a>
    </nav>
  </div>
  <div class="search-container header-center">
    <form class="site-search" action="/search.php" method="get">
      <input type="text" name="q" placeholder="Search..." data-i18n-placeholder="search" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </form>
  </div>
  <div class="header-right">
<?php if ($uid <= 0): ?>
    <nav class="site-nav header-links">
      <a href="/login.php" class="btn" data-i18n="login">Login</a>
      <a href="/register.php" class="btn" data-i18n="register">Register</a>
    </nav>
<?php else: ?>
    <div class="header-user"><?= username_with_avatar($db, $uid, $username) ?></div>
    <nav class="site-nav header-links">
      <a href="/dashboard.php" class="btn" data-i18n="dashboard">Dashboard</a>
      <a href="/friend-requests.php" aria-label="Friend Requests<?= $pending_requests ? ' (' . $pending_requests . ' pending)' : '' ?>">
        <img src="/assets/user-plus.svg" alt="Friend Requests">
        <?php if (!empty($pending_requests)): ?><span class="badge"><?= $pending_requests ?></span><?php endif; ?>
      </a>
      <a href="/notifications.php" aria-label="Notifications<?= $unread_total ? ' (' . $unread_total . ' unread)' : '' ?>">
        <img src="/assets/bell.svg" alt="Notifications">
        <?php if (!empty($unread_total)): ?><span class="badge"><?= $unread_total ?></span><?php endif; ?>
      </a>
      <a href="/messages.php" aria-label="Messages<?= $unread_messages ? ' (' . $unread_messages . ' unread)' : '' ?>">
        <img src="/assets/envelope.svg" alt="Messages">
        <?php if (!empty($unread_messages)): ?><span class="badge"><?= $unread_messages ?></span><?php endif; ?>
      </a>
      <a href="/logout.php" class="btn" data-i18n="logout">Logout</a>
    </nav>
<?php endif; ?>
    <a class="header-cart" href="/cart.php">
      <img src="/assets/cart.svg" alt="Cart">
      <?php if (!empty($cart_count)): ?><span class="badge"><?= $cart_count ?></span><?php endif; ?>
    </a>
    <button class="header-language" id="language-toggle" type="button" aria-haspopup="menu" aria-controls="language-menu">
      <img src="/assets/flags/en.svg" alt="English">
    </button>
    <button class="header-theme" id="theme-toggle" type="button" aria-haspopup="dialog" aria-controls="theme-modal">Themes</button>
  </div>
</header>
<?php include __DIR__ . '/ad-slot.php'; ?>
<div id="theme-modal" class="theme-modal" role="dialog" aria-modal="true" aria-labelledby="theme-modal-title" hidden tabindex="-1">
  <div class="modal-content">
    <h2 id="theme-modal-title">Select Theme</h2>
    <div class="theme-error" role="alert"></div>
    <div class="theme-options"></div>
    <h3 class="border-heading">Borders</h3>
    <div class="border-options"></div>
    <div id="theme-preview" class="theme-preview">
      <p>Sample text</p>
      <button type="button" class="btn">Sample Button</button>
    </div>
    <button id="theme-close" type="button" class="btn">Close</button>
  </div>
</div>
<script type="module" src="/assets/admin-pattern.js" defer></script>
<script type="module" src="/assets/theme-toggle.js" defer></script>
<script type="module" src="/assets/language-toggle.js" defer></script>
