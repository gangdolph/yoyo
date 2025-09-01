<?php
require_once __DIR__ . '/db.php';

function create_notification($conn, $user_id, $type, $message) {
    if ($stmt = $conn->prepare('INSERT INTO notifications (user_id, type, message) VALUES (?, ?, ?)')) {
        $stmt->bind_param('iss', $user_id, $type, $message);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

function get_notifications($conn, $user_id, $only_unread = false) {
    $sql = 'SELECT id, type, message, is_read, created_at FROM notifications WHERE user_id = ?';
    if ($only_unread) {
        $sql .= ' AND is_read = 0';
    }
    $sql .= ' ORDER BY created_at DESC';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $notifications;
    }
    return [];
}

function count_unread_notifications($conn, $user_id) {
    if ($stmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count;
    }
    return 0;
}

function mark_notifications_read($conn, $user_id) {
    if ($stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}
?>
