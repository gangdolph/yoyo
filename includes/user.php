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
    if ($stmt = $conn->prepare('SELECT avatar_path FROM profiles WHERE user_id = ?')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($path);
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
        $badge = ' <span class="vip-badge">VIP</span>';
    }

    $allowedStatuses = ['online', 'offline', 'busy', 'away'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'offline';
    }

    return '<span class="user-display status-' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '"><img src="' .
           htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="" class="avatar-sm">' .
           htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') . $badge . '</span>';
}
?>
