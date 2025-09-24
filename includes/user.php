<?php
function username_with_avatar(mysqli $conn, int $user_id, ?string $username = null): string {
    $vip = 0;
    $vip_expires = null;
    $status = 'offline';
    if ($stmt = $conn->prepare('SELECT username, vip_status, vip_expires_at, status FROM users WHERE id = ?')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($usernameFetched, $vip, $vip_expires, $statusFetched);
        $stmt->fetch();
        $stmt->close();
        if ($username === null) {
            $username = $usernameFetched;
        }
        $status = $statusFetched;
    }

    $avatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIgZmlsbD0iI2NjYyIvPjwvc3ZnPg==';
    $avatarDir = __DIR__ . '/../assets/avatars/';
    $avatarBase = '/assets/avatars/';
    $isPrivate = 0;
    if ($stmt = $conn->prepare('SELECT avatar_path, is_private FROM profiles WHERE user_id = ?')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($path, $isPrivate);
        if ($stmt->fetch() && $path) {
            $file = basename($path);
            $fullPath = $avatarDir . $file;
            if (is_file($fullPath)) {
                $avatar = $avatarBase . $file;
            }
        }
        $stmt->close();
    }

    $badge = '';
    if ($vip && (!$vip_expires || strtotime($vip_expires) > time())) {
        $badge = ' <span class="member-badge vip-badge">Member</span>';
    }

    $allowedStatuses = ['online', 'offline', 'busy', 'away'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'offline';
    }

    $display = '<span class="user-display status-' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '"><img src="' .
               htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="" class="avatar-sm">' .
               htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') . $badge . '</span>';

    $link = '<a href="view-profile.php?id=' . intval($user_id) . '">' . $display . '</a>';

    $privacyNote = '';
    if ($isPrivate) {
        $viewer = $_SESSION['user_id'] ?? 0;
        $isFriend = false;
        if ($viewer === $user_id) {
            $isFriend = true;
        } elseif ($viewer) {
            if ($stmt = $conn->prepare('SELECT 1 FROM friends WHERE ((user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)) AND status="accepted" LIMIT 1')) {
                $stmt->bind_param('iiii', $user_id, $viewer, $viewer, $user_id);
                $stmt->execute();
                $stmt->store_result();
                $isFriend = $stmt->num_rows === 1;
                $stmt->close();
            }
        }
        if (!$isFriend) {
            $privacyNote = ' <span class="private-note">🔒 Private – friends only.</span>';
        }
    }

    return $link . $privacyNote;
}
?>
