<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/notifications.php';

$username = '';
$status = '';
if (!empty($_SESSION['user_id'])) {
  if (!empty($_SESSION['username']) && !empty($_SESSION['status'])) {
    $username = $_SESSION['username'];
    $status = $_SESSION['status'];
  } else {
    if ($stmt = $conn->prepare('SELECT username, status FROM users WHERE id = ?')) {
      $stmt->bind_param('i', $_SESSION['user_id']);
      $stmt->execute();
      $stmt->bind_result($username, $status);
      $stmt->fetch();
      $stmt->close();
      $_SESSION['username'] = $username;
      $_SESSION['status'] = $status;
    }
  }
    $unread_messages = count_unread_messages($conn, $_SESSION['user_id']);
    $unread_notifications = count_unread_notifications($conn, $_SESSION['user_id']);
    $unread_total = $unread_messages + $unread_notifications;
  }
  $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<header class="site-header">
  <nav class="site-nav header-left">
    <a href="/index.php" class="logo-link">
      <img class="logo-img" src="/assets/logo.png" alt="SkuzE Logo">
    </a>
    <ul>
      <li><a href="/index.php" data-i18n="home">Home</a></li>
      <li><a href="/about.php" data-i18n="about">About</a></li>
      <li><a href="/help.php" data-i18n="help">Help/FAQ</a></li>
    </ul>
  </nav>
  <div class="search-container header-center">
    <form class="site-search" action="/search.php" method="get">
      <input type="text" name="q" placeholder="Search..." data-i18n-placeholder="search" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </form>
  </div>
  <nav class="site-nav header-right">
    <ul>
<?php if (empty($_SESSION['user_id'])): ?>
      <li><a href="/login.php" data-i18n="login">Login</a></li>
      <li><a href="/register.php" data-i18n="register">Register</a></li>
<?php else: ?>
      <li><a href="/dashboard.php" data-i18n="dashboard">Dashboard</a></li>
      <li>
        <a href="/notifications.php" aria-label="Notifications<?= $unread_total ? ' (' . $unread_total . ' unread)' : '' ?>">
          <img src="/assets/bell.svg" alt="">
          <?php if (!empty($unread_total)): ?><span class="badge"><?= $unread_total ?></span><?php endif; ?>
        </a>
      </li>
      <li>
        <a href="/messages.php" aria-label="Messages<?= $unread_messages ? ' (' . $unread_messages . ' unread)' : '' ?>">
          <img src="/assets/envelope.svg" alt="">
          <?php if (!empty($unread_messages)): ?><span class="badge"><?= $unread_messages ?></span><?php endif; ?>
        </a>
      </li>
      <li><a href="/logout.php" data-i18n="logout">Logout</a></li>
      <li class="user-info"><?= username_with_avatar($conn, $_SESSION['user_id'], $username) ?></li>
<?php endif; ?>
      <li class="cart-link">
        <a href="/cart.php">
          <img src="/assets/cart.svg" alt="Cart">
          <?php if (!empty($cart_count)): ?><span class="badge"><?= $cart_count ?></span><?php endif; ?>
        </a>
      </li>
      <li>
        <button id="language-toggle" type="button" aria-haspopup="menu" aria-controls="language-menu">
          <img src="/assets/flags/en.svg" alt="English">
        </button>
      </li>
      <li><button id="theme-toggle" type="button" aria-haspopup="dialog" aria-controls="theme-modal">Themes</button></li>
    </ul>
  </nav>
</header>
<div id="theme-modal" class="theme-modal" role="dialog" aria-modal="true" aria-labelledby="theme-modal-title" hidden tabindex="-1">
  <div class="modal-content">
    <h2 id="theme-modal-title">Select Theme</h2>
    <div class="theme-error" role="alert"></div>
    <div class="theme-options"></div>
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
