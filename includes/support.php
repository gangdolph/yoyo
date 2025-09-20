<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';

if (!function_exists('send_email')) {
    $mailPath = __DIR__ . '/../mail.php';
    if (file_exists($mailPath)) {
        require_once $mailPath;
    }
}

/**
 * Fetch all administrators and moderators that should receive support tickets.
 *
 * @param mysqli $conn
 * @return array<int, array{id:int, username:string, email:?string}>
 */
function get_support_admins(mysqli $conn): array {
    $admins = [];
    if ($stmt = $conn->prepare("SELECT id, username, email FROM users WHERE role = 'admin' ORDER BY id ASC")) {
        $stmt->execute();
        $result = $stmt->get_result();
        $admins = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
    return $admins;
}

/**
 * Create a support ticket and fan out the initial message to all administrators.
 *
 * @param mysqli $conn
 * @param int    $user_id
 * @param string $subject
 * @param string $message
 * @return array{ticket_id:int, assigned_to:?int, admins:array}
 */
function create_support_ticket(mysqli $conn, int $user_id, string $subject, string $message): array {
    $subject = trim($subject);
    $message = trim($message);

    if ($subject === '' || $message === '') {
        throw new InvalidArgumentException('Subject and message are required.');
    }

    $admins = get_support_admins($conn);
    if (empty($admins)) {
        throw new RuntimeException('No administrators are available to receive support tickets.');
    }

    if ($stmt = $conn->prepare('SELECT username, email FROM users WHERE id = ?')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($username, $user_email);
        $stmt->fetch();
        $stmt->close();
    } else {
        $username = 'User';
        $user_email = null;
    }

    $assigned_to = $admins[0]['id'] ?? null;

    if (!($stmt = $conn->prepare('INSERT INTO support_tickets (user_id, subject, assigned_to) VALUES (?, ?, ?)')))
    {
        throw new RuntimeException('Failed to prepare ticket insert: ' . $conn->error);
    }
    $stmt->bind_param('isi', $user_id, $subject, $assigned_to);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Failed to create support ticket: ' . $stmt->error);
    }
    $ticket_id = (int) $stmt->insert_id;
    $stmt->close();

    $category = 'support';

    foreach ($admins as $admin) {
        if ($msg = $conn->prepare('INSERT INTO message_requests (sender_id, recipient_id, body, support_ticket_id, category) VALUES (?, ?, ?, ?, ?)')) {
            $msg->bind_param('iisis', $user_id, $admin['id'], $message, $ticket_id, $category);
            $msg->execute();
            $msg->close();
        }

        $context = [
            'subject'  => $subject,
            'username' => $username,
        ];
        $notification = notification_message('support_ticket', $context);
        if ($notification) {
            create_notification($conn, (int) $admin['id'], 'support_ticket', $notification);
        }

        if (!empty($admin['email']) && function_exists('send_email')) {
            $emailSubject = 'New support ticket: ' . $subject;
            $emailBody = "A new support ticket was submitted by $username.\n\nSubject: $subject\n\nMessage:\n$message\n\nView the ticket in the admin panel.";
            try {
                send_email($admin['email'], $emailSubject, $emailBody);
            } catch (Exception $e) {
                error_log('Support ticket email failed: ' . $e->getMessage());
            }
        }
    }

    return [
        'ticket_id'   => $ticket_id,
        'assigned_to' => $assigned_to,
        'admins'      => $admins,
    ];
}

/**
 * Retrieve support tickets for a specific user.
 *
 * @param mysqli $conn
 * @param int    $user_id
 * @return array<int, array<string, mixed>>
 */
function get_support_tickets_for_user(mysqli $conn, int $user_id): array {
    $tickets = [];
    $sql = 'SELECT t.id, t.subject, t.status, t.created_at, t.updated_at, t.assigned_to, '
         . 'assigned.username AS assigned_username '
         . 'FROM support_tickets t '
         . 'LEFT JOIN users assigned ON assigned.id = t.assigned_to '
         . 'WHERE t.user_id = ? '
         . 'ORDER BY t.updated_at DESC';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $tickets = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    if (empty($tickets)) {
        return [];
    }

    $linkStmt = $conn->prepare('SELECT recipient_id FROM message_requests '
        . 'WHERE support_ticket_id = ? AND recipient_id <> ? '
        . 'ORDER BY created_at ASC LIMIT 1');
    $timeStmt = $conn->prepare('SELECT MAX(created_at) FROM message_requests WHERE support_ticket_id = ?');
    foreach ($tickets as &$ticket) {
        $ticket['contact_id'] = null;
        $ticket['contact_username'] = null;
        $ticket['last_message_at'] = null;

        if ($linkStmt) {
            $linkStmt->bind_param('ii', $ticket['id'], $user_id);
            $linkStmt->execute();
            $linkStmt->bind_result($contact_id);
            if ($linkStmt->fetch()) {
                $ticket['contact_id'] = (int) $contact_id;
            }
            $linkStmt->free_result();
        }

        if (!empty($ticket['contact_id'])) {
            if ($userStmt = $conn->prepare('SELECT username FROM users WHERE id = ?')) {
                $userStmt->bind_param('i', $ticket['contact_id']);
                $userStmt->execute();
                $userStmt->bind_result($contact_username);
                if ($userStmt->fetch()) {
                    $ticket['contact_username'] = $contact_username;
                }
                $userStmt->close();
            }
        }

        if ($timeStmt) {
            $timeStmt->bind_param('i', $ticket['id']);
            $timeStmt->execute();
            $timeStmt->bind_result($last_message_at);
            if ($timeStmt->fetch()) {
                $ticket['last_message_at'] = $last_message_at;
            }
            $timeStmt->free_result();
        }
    }
    if ($linkStmt) {
        $linkStmt->close();
    }
    if ($timeStmt) {
        $timeStmt->close();
    }
    return $tickets;
}

/**
 * Retrieve support tickets for administrative review.
 *
 * @param mysqli     $conn
 * @param string|null $status
 * @return array<int, array<string, mixed>>
 */
function get_support_tickets(mysqli $conn, ?string $status = null): array {
    $tickets = [];
    $sql = 'SELECT t.id, t.user_id, t.subject, t.status, t.assigned_to, t.created_at, t.updated_at, '
         . 'requester.username AS user_username, assigned.username AS assigned_username '
         . 'FROM support_tickets t '
         . 'JOIN users requester ON requester.id = t.user_id '
         . 'LEFT JOIN users assigned ON assigned.id = t.assigned_to';
    if ($status) {
        $sql .= ' WHERE t.status = ?';
    }
    $sql .= ' ORDER BY t.updated_at DESC';

    if ($stmt = $conn->prepare($sql)) {
        if ($status) {
            $stmt->bind_param('s', $status);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $tickets = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    if (empty($tickets)) {
        return [];
    }

    $activityStmt = $conn->prepare('SELECT MAX(created_at) AS last_message_at, '
        . 'MAX(CASE WHEN sender_id = ? AND read_at IS NULL THEN 1 ELSE 0 END) AS unread_flag '
        . 'FROM message_requests WHERE support_ticket_id = ?');

    foreach ($tickets as &$ticket) {
        $ticket['last_message_at'] = null;
        $ticket['unread_flag'] = 0;
        if ($activityStmt) {
            $activityStmt->bind_param('ii', $ticket['user_id'], $ticket['id']);
            $activityStmt->execute();
            $activityStmt->bind_result($last_message_at, $unread_flag);
            if ($activityStmt->fetch()) {
                $ticket['last_message_at'] = $last_message_at;
                $ticket['unread_flag'] = (int) $unread_flag;
            }
            $activityStmt->free_result();
        }
    }

    if ($activityStmt) {
        $activityStmt->close();
    }

    return $tickets;
}

/**
 * Update the status or assignment of a support ticket.
 *
 * @param mysqli $conn
 * @param int    $ticket_id
 * @param string $status
 * @param int|null $assigned_to
 * @return bool
 */
function update_support_ticket(mysqli $conn, int $ticket_id, string $status, ?int $assigned_to): bool {
    $allowed = ['open', 'pending', 'closed'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid ticket status.');
    }

    if ($assigned_to !== null) {
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'admin'");
        if ($stmt) {
            $stmt->bind_param('i', $assigned_to);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                throw new InvalidArgumentException('Assigned user must be an administrator.');
            }
            $stmt->close();
        }
    }

    if ($stmt = $conn->prepare('UPDATE support_tickets SET status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?')) {
        $stmt->bind_param('sii', $status, $assigned_to, $ticket_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}

/**
 * Touch a support ticket when a new message arrives.
 *
 * @param mysqli   $conn
 * @param int      $ticket_id
 * @param string|null $status
 * @return void
 */
function touch_support_ticket(mysqli $conn, int $ticket_id, ?string $status = null): void {
    if ($status && !in_array($status, ['open', 'pending', 'closed'], true)) {
        $status = null;
    }

    if ($status) {
        if ($stmt = $conn->prepare('UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?')) {
            $stmt->bind_param('si', $status, $ticket_id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        if ($stmt = $conn->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?')) {
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}
