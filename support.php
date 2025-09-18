<?php
require 'includes/auth.php';
require 'includes/csrf.php';
require 'includes/support.php';

$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = false;
$ticketResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        try {
            $ticketResult = create_support_ticket($conn, $user_id, $subject, $message);
            $success = true;
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (RuntimeException $e) {
            error_log('Support ticket creation failed: ' . $e->getMessage());
            $error = 'We were unable to submit your request. Please try again later.';
        }
    }
}

$tickets = get_support_tickets_for_user($conn, $user_id);
$formSubject = $success ? '' : ($_POST['subject'] ?? '');
$formMessage = $success ? '' : ($_POST['message'] ?? '');
?>
<?php require 'includes/layout.php'; ?>
  <title>Contact Support</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <?php include 'includes/sidebar.php'; ?>
  <?php include 'includes/header.php'; ?>
  <div class="page-container">
    <h2>Contact Support</h2>
    <p>If you need help from the SkuzE team, send us a message and a moderator will get back to you through Messages.</p>
    <?php if ($success): ?>
      <div class="alert success">
        <p>Your support ticket has been submitted successfully.</p>
        <?php if (!empty($ticketResult['assigned_to'])): ?>
          <p>We'll reach out from the admin team shortly. Check your <a href="messages.php">messages</a> for updates.</p>
        <?php endif; ?>
      </div>
    <?php elseif ($error): ?>
      <div class="alert error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <form method="post" class="support-form">
      <input type="hidden" name="csrf_token" value="<?= generate_token(); ?>">
      <label for="support-subject">Subject</label>
      <input type="text" id="support-subject" name="subject" maxlength="255" required value="<?= htmlspecialchars($formSubject, ENT_QUOTES, 'UTF-8'); ?>">
      <label for="support-message">Message</label>
      <textarea id="support-message" name="message" rows="6" required><?= htmlspecialchars($formMessage, ENT_QUOTES, 'UTF-8'); ?></textarea>
      <button type="submit" class="btn">Submit Ticket</button>
    </form>

    <h3>Your Support Tickets</h3>
    <?php if (empty($tickets)): ?>
      <p>You have not submitted any support tickets yet.</p>
    <?php else: ?>
      <table class="support-ticket-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Assigned</th>
            <th>Last Update</th>
            <th>Conversation</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <?php
            $status = htmlspecialchars(ucfirst($ticket['status']), ENT_QUOTES, 'UTF-8');
            $assignedName = $ticket['assigned_username'] ?: ($ticket['contact_username'] ?? null);
            $assigned = $assignedName ? htmlspecialchars($assignedName, ENT_QUOTES, 'UTF-8') : 'Unassigned';
            $lastUpdate = $ticket['last_message_at'] ? htmlspecialchars($ticket['last_message_at'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($ticket['updated_at'], ENT_QUOTES, 'UTF-8');
            $contactId = $ticket['assigned_to'] ?: ($ticket['contact_id'] ?? null);
          ?>
          <tr>
            <td>#<?= (int) $ticket['id']; ?></td>
            <td><?= htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?= $status; ?></td>
            <td><?= $assigned; ?></td>
            <td><?= $lastUpdate; ?></td>
            <td>
              <?php if (!empty($contactId)): ?>
                <a class="btn" href="message-thread.php?user=<?= (int) $contactId; ?>">View Messages</a>
              <?php else: ?>
                <span>Pending</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
