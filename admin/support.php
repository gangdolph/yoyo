<?php
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/csrf.php';
require '../includes/support.php';
require '../includes/user.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: ../dashboard.php');
    exit;
}

$statusFilter = $_GET['status'] ?? null;
$allowedStatuses = ['open', 'pending', 'closed'];
if ($statusFilter && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = null;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
        $status = $_POST['status'] ?? 'open';
        $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int) $_POST['assigned_to'] : null;
        $assign_to_me = isset($_POST['assign_to_me']);

        if ($ticket_id <= 0) {
            $error = 'Invalid ticket selected.';
        } else {
            if ($assign_to_me) {
                $assigned_to = (int) $_SESSION['user_id'];
            }
            try {
                if (update_support_ticket($conn, $ticket_id, $status, $assigned_to)) {
                    $message = 'Ticket updated successfully.';
                } else {
                    $error = 'Failed to update the ticket.';
                }
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$tickets = get_support_tickets($conn, $statusFilter);
$admins = get_support_admins($conn);
?>
<?php require '../includes/layout.php'; ?>
  <title>Support Tickets</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <?php include '../includes/header.php'; ?>
  <div class="page-container">
    <h2>Support Tickets</h2>
    <p><a class="btn" href="index.php">&larr; Back to Admin Panel</a></p>
    <form method="get" class="support-filter">
      <label for="status-filter">Status:</label>
      <select id="status-filter" name="status">
        <option value=""<?= $statusFilter === null ? ' selected' : ''; ?>>All</option>
        <?php foreach ($allowedStatuses as $statusOption): ?>
          <option value="<?= $statusOption; ?>"<?= $statusFilter === $statusOption ? ' selected' : ''; ?>><?= ucfirst($statusOption); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn">Apply</button>
    </form>

    <?php if ($message): ?>
      <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif ($error): ?>
      <div class="alert error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (empty($tickets)): ?>
      <p>No support tickets found.</p>
    <?php else: ?>
      <table class="support-ticket-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Subject</th>
            <th>User</th>
            <th>Status</th>
            <th>Assigned</th>
            <th>Last Update</th>
            <th>Needs Reply</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <?php $formId = 'ticket-form-' . (int) $ticket['id']; ?>
          <tr>
            <td>#<?= (int) $ticket['id']; ?></td>
            <td><?= htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= username_with_avatar($conn, (int) $ticket['user_id'], $ticket['user_username']); ?></td>
            <td>
              <select name="status" form="<?= $formId; ?>">
                <?php foreach ($allowedStatuses as $statusOption): ?>
                  <option value="<?= $statusOption; ?>"<?= $ticket['status'] === $statusOption ? ' selected' : ''; ?>><?= ucfirst($statusOption); ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select name="assigned_to" form="<?= $formId; ?>">
                <option value="">Unassigned</option>
                <?php foreach ($admins as $admin): ?>
                  <option value="<?= (int) $admin['id']; ?>"<?= ((int) $ticket['assigned_to'] === (int) $admin['id']) ? ' selected' : ''; ?>><?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><?= htmlspecialchars($ticket['last_message_at'] ?? $ticket['updated_at'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= !empty($ticket['unread_flag']) ? '<span class="badge">New</span>' : '—'; ?></td>
            <td>
              <form id="<?= $formId; ?>" method="post" class="support-update-form">
                <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
                <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id']; ?>">
                <button type="submit" class="btn">Save</button>
                <button type="submit" name="assign_to_me" value="1" class="btn btn-secondary">Assign to Me</button>
              </form>
              <a class="btn" href="../message-thread.php?user=<?= (int) $ticket['user_id']; ?>">View Messages</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php include '../includes/footer.php'; ?>
</body>
</html>
