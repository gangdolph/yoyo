<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!isset($db) || !($db instanceof mysqli)) {
    $db = require __DIR__ . '/includes/db.php';
}

require 'includes/user.php';
require 'includes/tags.php';

$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$role = trim($_GET['role'] ?? '');
$tagsQuery = trim($_GET['tags'] ?? '');
$tagFilters = tags_from_input($tagsQuery);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$userResults = $listingResults = $tradeResults = [];
$totalUserPages = $totalListingPages = $totalTradePages = 0;
$searchError = false;
$errorMessage = '';

if ($q !== '') {
    // Users search
    $like = "%{$q}%";
    $countSql = "SELECT COUNT(*) FROM users WHERE username LIKE ?";
    $countParams = [$like];
    $countTypes = 's';
    if ($role !== '') {
        $countSql .= " AND status = ?";
        $countParams[] = $role;
        $countTypes .= 's';
    }
    if ($stmt = $db->prepare($countSql)) {
        $stmt->bind_param($countTypes, ...$countParams);
        if ($stmt->execute()) {
            $stmt->bind_result($totalUsers);
            $stmt->fetch();
            $totalUserPages = (int)ceil($totalUsers / $perPage);
        } else {
            error_log($stmt->error);
            http_response_code(500);
            $searchError = true;
            $errorMessage = 'Search is currently unavailable.';
        }
        $stmt->close();
    } else {
        error_log($db->error);
        http_response_code(500);
        $searchError = true;
        $errorMessage = 'Search is currently unavailable.';
    }

    $userSql = "SELECT id, username, status FROM users WHERE username LIKE ?";
    $userParams = [$like];
    $userTypes = 's';
    if ($role !== '') {
        $userSql .= " AND status = ?";
        $userParams[] = $role;
        $userTypes .= 's';
    }
    $userSql .= " ORDER BY username LIMIT ? OFFSET ?";
    $userParams[] = $perPage;
    $userParams[] = $offset;
    $userTypes .= 'ii';
    if ($stmt = $db->prepare($userSql)) {
        $stmt->bind_param($userTypes, ...$userParams);
        if ($stmt->execute()) {
            $userResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log($stmt->error);
            http_response_code(500);
            $searchError = true;
            $errorMessage = 'Search is currently unavailable.';
        }
        $stmt->close();
    } else {
        error_log($db->error);
        http_response_code(500);
        $searchError = true;
        $errorMessage = 'Search is currently unavailable.';
    }

    // Listings search
    $countSql = "SELECT COUNT(*) FROM listings WHERE (title LIKE ? OR description LIKE ? OR category LIKE ?)";
    $countParams = [$like, $like, $like];
    $countTypes = 'sss';
    if ($category !== '') {
        $countSql .= " AND category = ?";
        $countParams[] = $category;
        $countTypes .= 's';
    }
    if ($tagFilters) {
        foreach ($tagFilters as $tag) {
            $countSql .= " AND tags LIKE ?";
            $countParams[] = tag_like_parameter($tag);
            $countTypes .= 's';
        }
    }
    if ($stmt = $db->prepare($countSql)) {
        $stmt->bind_param($countTypes, ...$countParams);
        if ($stmt->execute()) {
            $stmt->bind_result($totalListings);
            $stmt->fetch();
            $totalListingPages = (int)ceil($totalListings / $perPage);
        } else {
            error_log($stmt->error);
            http_response_code(500);
            $searchError = true;
            $errorMessage = 'Search is currently unavailable.';
        }
        $stmt->close();
    } else {
        error_log($db->error);
        http_response_code(500);
        $searchError = true;
        $errorMessage = 'Search is currently unavailable.';
    }

    $listSql = "SELECT l.id, l.title, l.description, l.category, l.tags
                FROM listings l
                WHERE (l.title LIKE ? OR l.description LIKE ? OR l.category LIKE ?)";
    $listParams = [$like, $like, $like];
    $listTypes = 'sss';
    if ($category !== '') {
        $listSql .= " AND l.category = ?";
        $listParams[] = $category;
        $listTypes .= 's';
    }
    if ($tagFilters) {
        foreach ($tagFilters as $tag) {
            $listSql .= " AND l.tags LIKE ?";
            $listParams[] = tag_like_parameter($tag);
            $listTypes .= 's';
        }
    }
    $listSql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $listTypes .= 'ii';
    if ($stmt = $db->prepare($listSql)) {
        $stmt->bind_param($listTypes, ...$listParams);
        if ($stmt->execute()) {
            $listingResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log($stmt->error);
            http_response_code(500);
            $searchError = true;
            $errorMessage = 'Search is currently unavailable.';
        }
        $stmt->close();
    } else {
        error_log($db->error);
        http_response_code(500);
        $searchError = true;
        $errorMessage = 'Search is currently unavailable.';
    }

    // Trade requests search
    $countSql = "SELECT COUNT(*) FROM service_requests WHERE type='trade' AND (make LIKE ? OR model LIKE ? OR device_type LIKE ?)";
    $countParams = [$like, $like, $like];
    $countTypes = 'sss';
    if ($stmt = $db->prepare($countSql)) {
        $stmt->bind_param($countTypes, ...$countParams);
        if ($stmt->execute()) {
            $stmt->bind_result($totalTrades);
            $stmt->fetch();
            $totalTradePages = (int)ceil($totalTrades / $perPage);
        } else {
            error_log("Trade count query failed: {$stmt->error} | SQL: {$countSql}");
            http_response_code(500);
            $searchError = true;
            $errorMessage = 'Search is currently unavailable.';
        }
        $stmt->close();
    } else {
        error_log("Trade count prepare failed: {$db->error} | SQL: {$countSql}");
        http_response_code(500);
        $searchError = true;
        $errorMessage = 'Search is currently unavailable.';
    }

    $tradeSql = "SELECT r.id, r.make, r.model, r.device_type, u.username
                 FROM service_requests r
                 JOIN users u ON r.user_id = u.id
                 WHERE r.type = 'trade'
                   AND (r.make LIKE ? OR r.model LIKE ? OR r.device_type LIKE ?)
                 ORDER BY r.created_at DESC
                 LIMIT ? OFFSET ?";
    $tradeParams = [$like, $like, $like, $perPage, $offset];
    $tradeTypes = 'sssii';
    if ($stmt = $db->prepare($tradeSql)) {
        $stmt->bind_param($tradeTypes, ...$tradeParams);
        if ($stmt->execute()) {
            $tradeResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log("Trade search query failed: {$stmt->error} | SQL: {$tradeSql}");
            http_response_code(500);
            $searchError = true;
            $errorMessage = 'Search is currently unavailable.';
        }
        $stmt->close();
    } else {
        error_log("Trade search prepare failed: {$db->error} | SQL: {$tradeSql}");
        http_response_code(500);
        $searchError = true;
        $errorMessage = 'Search is currently unavailable.';
    }
}

$totalPages = max($totalUserPages, $totalListingPages, $totalTradePages);
?>
<?php require 'includes/layout.php'; ?>
  <meta charset="UTF-8">
  <title>Search Results</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container">
    <h2>Search Results</h2>
    <form method="get" class="search-filters">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search...">
      <select name="category">
        <option value="">All Categories</option>
        <option value="phone" <?= $category==='phone'?'selected':'' ?>>Phone</option>
        <option value="console" <?= $category==='console'?'selected':'' ?>>Game Console</option>
        <option value="pc" <?= $category==='pc'?'selected':'' ?>>PC</option>
        <option value="other" <?= $category==='other'?'selected':'' ?>>Other</option>
      </select>
      <select name="role">
        <option value="">All Roles</option>
        <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
        <option value="user" <?= $role==='user'?'selected':'' ?>>User</option>
      </select>
      <input type="text" name="tags" value="<?= htmlspecialchars($tagsQuery) ?>" placeholder="Tags (comma separated)">
      <button type="submit">Search</button>
    </form>
  <?php if ($q === ''): ?>
    <p>Please enter a search query.</p>
  <?php elseif ($searchError): ?>
    <p><?= htmlspecialchars($errorMessage) ?></p>
  <?php else: ?>
    <section>
      <h3>Users</h3>
      <?php if ($userResults): ?>
        <ul>
        <?php foreach ($userResults as $u): ?>
          <li><a href="view-profile.php?id=<?= $u['id']; ?>"><?= htmlspecialchars($u['username']) ?></a> (<?= htmlspecialchars($u['status']) ?>)</li>
        <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>No users found.</p>
      <?php endif; ?>
    </section>
    <section>
      <h3>Listings</h3>
      <?php if ($listingResults): ?>
        <ul class="listing-search-results">
          <?php foreach ($listingResults as $l): ?>
            <?php $resultTags = tags_from_storage($l['tags']); ?>
            <li>
              <a href="shipping.php?listing_id=<?= $l['id']; ?>"><?= htmlspecialchars($l['title']) ?></a>
              <span class="result-meta"><?= htmlspecialchars($l['category']) ?></span>
              <?php if ($resultTags): ?>
                <span class="result-tags">
                  <?php foreach ($resultTags as $tag): ?>
                    <span class="tag-chip tag-chip-static">#<?= htmlspecialchars($tag); ?></span>
                  <?php endforeach; ?>
                </span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>No listings found.</p>
      <?php endif; ?>
    </section>
    <section>
      <h3>Trade Requests</h3>
      <?php if ($tradeResults): ?>
        <ul>
        <?php foreach ($tradeResults as $t): ?>
          <li><?= htmlspecialchars($t['make']) ?> <?= htmlspecialchars($t['model']) ?> (<?= htmlspecialchars($t['device_type']) ?>) by <?= htmlspecialchars($t['username']) ?></li>
        <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>No trade requests found.</p>
      <?php endif; ?>
    </section>
    <?php if ($totalPages > 1): ?>
    <nav class="pagination">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
