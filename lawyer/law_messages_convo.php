<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Redirect if not logged in as lawyer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$other_id = $_GET['user'] ?? null;

if (!$other_id) {
    die("Invalid user.");
}

// Check if lawyer has access to this user based on shared case
$accessCheck = $pdo->prepare("
    SELECT 1 FROM dv_case
    WHERE assigned_lawyer = :lawyer_id AND (reported_by = :reported OR assigned_to = :police)
");
$accessCheck->execute([
    'lawyer_id' => $user_id,
    'reported' => $other_id,
    'police' => $other_id
]);
if (!$accessCheck->fetch()) {
    die("Unauthorized access.");
}

// Fetch logged-in lawyer's info
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch other party's info
$stmt = $pdo->prepare("SELECT full_name, email FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$other_id]);
$other = $stmt->fetch();

if (!$other) {
    die("User not found.");
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text'])) {
    $text = trim($_POST['message_text']);

    if (!empty($text)) {
        // Check for duplicate within last 5 seconds
        $check = $pdo->prepare("
            SELECT 1 FROM messages 
            WHERE sender_id = ? 
              AND receiver_id = ? 
              AND message_text = ? 
      AND sent_at > (NOW() - INTERVAL '5 seconds')
        ");
        $check->execute([$user_id, $other_id, $text]);

        if (!$check->fetch()) {
            // Insert message
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, message_text) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user_id, $other_id, $text]);

            // Store in session so email is sent only once after redirect
            $_SESSION['pending_email'] = [
                'to_email' => $other['email'],
                'to_name'  => $other['full_name'],
                'from_name'=> $user['full_name'],
                'text'     => $text
            ];
        }
    }

    // Immediate redirect (no HTML output before this line!)
    header("Location: law_messages_convo.php?user=$other_id");
    exit();
}

// Send email if pending (from last request)
if (!empty($_SESSION['pending_email'])) {
    $emailData = $_SESSION['pending_email'];
    unset($_SESSION['pending_email']);

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($_ENV['SMTP_USER'], 'DVMS');
        $mail->addAddress($emailData['to_email'], $emailData['to_name']);

        $mail->isHTML(true);
        $mail->Subject = 'New message from ' . htmlspecialchars($emailData['from_name']);

        $formattedDate = date('M d, Y h:i A');
        $mail->Body = "
            <p>Dear " . htmlspecialchars($emailData['to_name']) . ", you have a new message.</p>
            <p><strong>From:</strong><br>
            " . htmlspecialchars($emailData['from_name']) . "<br>
            " . htmlspecialchars($user['email']) . "</p>
            <p><strong>Message:</strong><br>
            " . nl2br(htmlspecialchars($emailData['text'])) . "</p>
            <p><strong>Time and Date:</strong><br>
            {$formattedDate}</p>
            <p>PLease view the message on the website.</p>
     ";

        $mail->AltBody = "Dear {$emailData['to_name']}, you have a new message.\n\n" .
            "From: {$emailData['from_name']} ({$user['email']})\n\n" .
            "Message:\n{$emailData['text']}\n\n" .
            "Time and Date: {$formattedDate}\n\n" .
            "View conversation: https://localhost3000/law_messages_convo.php?user=" . urlencode($user_id);

        $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }

}


// Fetch conversation messages
$stmt = $pdo->prepare("
    SELECT * FROM messages 
    WHERE (sender_id = :uid AND receiver_id = :oid) 
       OR (sender_id = :oid AND receiver_id = :uid)
    ORDER BY sent_at ASC
");
$stmt->execute(['uid' => $user_id, 'oid' => $other_id]);
$messages = $stmt->fetchAll();

// Mark messages as read
$pdo->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ?")
    ->execute([$other_id, $user_id]);

// Get unread count for sidebar badge
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages | Lawyer</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="user-profile">
                <?php if ($user['profilepic']): ?>
                    <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" alt="Profile" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                <p><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="lawyer_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="lawyer_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <li><a href="law_unassigned.php"><i class="fas fa-user-plus"></i> Unassigned Case</a></li>
                <li class="active"><a href="law_messages.php"><i class="fas fa-envelope"></i> Messages
                    <?php if ($unread > 0): ?>
                        <span class="notification-badge"><?= $unread ?></span>
                    <?php endif; ?>
                </a></li>
                                <li><a href="law_reporting.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
<li><a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a></li>
                <li><a href="law_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-envelope"></i> Messages</h1>
        </header>

        <a href="law_messages.php" class="btn">← Back to Messages</a>

        <section class="content-section conversation-container">
            <h2 class="conversation-title"><?= htmlspecialchars($other['full_name']) ?></h2>

            <div class="conversation-thread">
                <?php foreach ($messages as $msg): ?>
                    <div class="chat-bubble <?= $msg['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                        <p><?= nl2br(htmlspecialchars($msg['message_text'])) ?></p>
                        <small><?= date('M d, Y h:i A', strtotime($msg['sent_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="POST" class="form-inline message-form">
                <textarea name="message_text" rows="2" placeholder="Type your reply..." required></textarea>
                <button type="submit" class="btn btn-primary">Send</button>
            </form>
        </section>
    </main>
</div>
<script>
    const thread = document.querySelector('.conversation-thread');
    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }
</script>

</body>
</html>
