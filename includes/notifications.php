<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Return a formatted notification message for common events.
 *
 * @param string $type The notification type key
 * @param array  $context Additional data such as status or offer_id
 * @return string
 */
function notification_message($type, $context = []) {
    $templates = [
        'admin_message'   => 'You have a new message from an administrator.',
        'shipping_update' => 'Shipping details updated for trade offer #' . ($context['offer_id'] ?? ''),
    ];

    if ($type === 'order_status') {
        $orderId = $context['order_id'] ?? '';
        $statusLabel = ucfirst((string) ($context['status'] ?? ''));
        return trim("Order #$orderId status updated to $statusLabel.");
    }

    if ($type === 'support_ticket') {
        $from = $context['username'] ?? 'a user';
        $subject = $context['subject'] ?? 'Support ticket';
        return "New support ticket from $from: $subject";
    }

    if ($type === 'service_status') {
        $map = [
            'New'              => 'Your service request was received.',
            'In Progress'      => 'Your service request is now in progress.',
            'Awaiting Customer'=> 'We are awaiting your response for the service request.',
            'Completed'        => 'Your service request has been completed.',
            'Shipped'          => 'Your serviced item has been shipped.',
        ];
        $status = $context['status'] ?? '';
        return $map[$status] ?? "Your service request status changed to $status.";
    }

    return $templates[$type] ?? '';
}

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

function count_unread_messages(mysqli $db, int $userId): int {
    if ($userId <= 0) return 0;
    $sql = "SELECT COUNT(*) FROM message_requests WHERE recipient_id = ? AND read_at IS NULL";
    $stmt = $db->prepare($sql);
    try {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        return (int)$count;
    } finally {
        $stmt->close();
    }
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
