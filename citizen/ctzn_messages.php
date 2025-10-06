<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'], $_POST['message_text'])) {
    $receiver_id = $_POST['receiver_id'];
    $message_text = trim($_POST['message_text']);
    if (!empty($receiver_id) && !empty($message_text)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (:sender, :receiver, :msg)");
        $stmt->execute(['sender' => $user_id, 'receiver' => $receiver_id, 'msg' => $message_text]);
        $success = "Message sent successfully.";
    }
}

$stmt = $pdo->prepare("
    SELECT DISTINCT ON (conversation_with) 
        m.message_id, m.message_text, m.sent_at, m.is_read,
        u.full_name AS other_party_name,
        CASE 
            WHEN u.u_is_adm IS TRUE THEN 'Admin'
            WHEN u.u_is_lenf IS TRUE THEN 'Law Enforcement'
            WHEN u.u_is_lrep IS TRUE THEN 'Legal Representative'
            WHEN u.u_is_ctzn IS TRUE THEN 'Citizen'
            ELSE 'Unknown'
        END AS other_party_role,
        CASE WHEN m.sender_id = :uid THEN 'sent' ELSE 'received' END AS message_direction,
        conversation_with,
        (
            SELECT COUNT(*) 
            FROM messages 
            WHERE sender_id = conversation_with 
              AND receiver_id = :uid 
              AND is_read = FALSE
        ) AS unread_count
    FROM (
        SELECT *, CASE WHEN sender_id = :uid THEN receiver_id ELSE sender_id END AS conversation_with
        FROM messages
        WHERE sender_id = :uid OR receiver_id = :uid
    ) m
    JOIN sys_user u ON m.conversation_with = u.user_id
    ORDER BY conversation_with, m.sent_at DESC
");
$stmt->execute(['uid' => $user_id]);
$conversations = $stmt->fetchAll();

$receivers = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.full_name,
        CASE 
            WHEN u.u_is_adm IS TRUE THEN 'Admin'
            WHEN u.u_is_lenf IS TRUE THEN 'Law Enforcement'
            WHEN u.u_is_lrep IS TRUE THEN 'Legal Representative'
            WHEN u.u_is_ctzn IS TRUE THEN 'Citizen'
            ELSE 'Unknown'
        END AS role
    FROM sys_user u
    INNER JOIN dv_case c 
        ON u.user_id = c.assigned_to 
        OR u.user_id = c.assigned_lawyer
    WHERE c.reported_by = :uid
      AND u.user_id != :uid
");
$receivers->execute(['uid' => $user_id]);
$receivers = $receivers->fetchAll();


$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Messages | DV Assistance System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../style.css">
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:2000;}
.modal-box{background:#fff;border-radius:10px;padding:20px;width:400px;max-width:90%;position:relative;}
.close-modal{position:absolute;right:15px;top:10px;font-size:22px;cursor:pointer;}
.messages-list{display:flex;flex-direction:column;gap:12px;}
.conversation-item{position:relative;display:flex;align-items:center;padding:12px;border-radius:10px;background:#fff;text-decoration:none;color:inherit;box-shadow:0 1px 3px rgba(0,0,0,0.08);transition:background 0.2s;}
.conversation-item:hover{background:#f8f9fa;}
.unread{background:#f0f8ff;}
.avatar-circle{width:42px;height:42px;border-radius:50%;object-fit:cover;margin-right:12px;flex-shrink:0;}
.conversation-details{flex:1;display:flex;flex-direction:column;margin-left:10px;}
.conversation-header{display:flex;justify-content:space-between;font-weight:bold;font-size:14px;margin-bottom:4px;}
.conversation-meta{display:flex;gap:10px;font-size:12px;color:#6c757d;margin-bottom:4px;}
.preview{font-size:13px;color:#555;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0;}
.badge{position:absolute;top:8px;right:8px;background-color:red;color:white;border-radius:50%;padding:4px 8px;font-size:0.75rem;font-weight:bold;min-width:20px;text-align:center;line-height:1;}
.btn-view{background:#007bff;color:#fff;border:none;padding:6px 12px;border-radius:5px;cursor:pointer;}
.newMessageBtn{margin-left:auto;display:inline-flex;align-items:center;gap:6px;}
.form-group select,.form-group textarea{width:100%;padding:8px;margin-top:4px;}
</style>
</head>
<body>
<div class="dashboard-container">
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="user-profile">
            <?php if ($user['profilepic']): ?>
                                <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" 
                    alt="Profile" class="user-avatar">
            <?php else: ?>
                <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
            <?php endif; ?>
            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
            <p><?= htmlspecialchars($user['email']) ?></p>
        </div>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="citizen_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="ctzn_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
            <li><a href="ctzn_report.php"><i class="fas fa-plus-circle"></i> Report New Case</a></li>
            <li class="active"><a href="ctzn_messages.php"><i class="fas fa-envelope"></i> Messages <span class="notification-badge"><?= $unread ?></span></a></li>
            <li><a href="ctzn_resources.php"><i class="fas fa-book"></i> Resources</a></li>
            <li><a href="ctzn_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</aside>

    <main class="main-content">
    <header class="content-header"><h1><i class="fas fa-envelope"></i> Messages</h1></header>

    <?php
    echo '<div style="background:#fff3cd;border-left:6px solid #ffc107;padding:12px 18px;margin:15px 0;border-radius:6px;color:#856404;display:flex;align-items:center;font-size:14px;"><i class="fas fa-exclamation-triangle" style="margin-right:10px;"></i>For your safety: press <strong>Esc</strong> button on your keyboard 3 times quickly to exit this system.</div>';
    ?>
        <button class="btn1" id="newMessageBtn"><i class="fas fa-paper-plane"></i> New Conversation</button>
    <br>

    <?php if (isset($success)): ?><p class="success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <section class="content-section">
    <?php if (count($conversations) > 0): ?>
        <div class="messages-list">
            <?php foreach ($conversations as $msg): ?>
        <a href="ctzn_messages_convo.php?user=<?= urlencode($msg['conversation_with']) ?>" 
        class="conversation-item <?= $msg['is_read'] ? 'read' : 'unread' ?>">

            <img class="avatar-circle" 
                src="../get_profilepic.php?id=<?= urlencode($msg['conversation_with']) ?>" 
                alt="<?= htmlspecialchars($msg['other_party_name']) ?>'s avatar">
            
            <div class="conversation-details">
                <div class="conversation-header">
                    <span class="name"><?= htmlspecialchars($msg['other_party_name']) ?></span>
                    <span class="date"><?= date('M d, Y h:i A', strtotime($msg['sent_at'])) ?></span>
                </div>
                <div class="conversation-meta">
                    <span class="role"><?= htmlspecialchars($msg['other_party_role']) ?></span>
                    <span class="direction"><?= $msg['message_direction'] === 'sent' ? 'Sent' : 'Received' ?></span>
                </div>
                <p class="preview"><?= htmlspecialchars($msg['message_text']) ?></p>
            </div>

            <?php if (!empty($msg['unread_count']) && $msg['unread_count'] > 0): ?>
                <span class="notification-badge"><?= $msg['unread_count'] ?></span>
            <?php endif; ?>

        </a>

            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-comment-dots"></i>
            <p>No messages yet.</p>
        </div>
    <?php endif; ?>
    </section>

    </main>
</div>

<div id="newMessageModal" class="modal-overlay">
    <div class="modal-box">
        <span class="close-modal" id="closeModal">&times;</span>
        <h2><i class="fas fa-paper-plane"></i> New Conversation</h2>
        <form method="POST" class="form">
            <div class="form-group">
                <label for="receiver_id">Send To</label>
                <select name="receiver_id" required>
                    <option value="">-- Choose recipient --</option>
                    <?php foreach ($receivers as $r): ?>
                        <option value="<?= $r['user_id'] ?>"><?= htmlspecialchars($r['full_name']) ?> (<?= $r['role'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="message_text">Message</label>
                <textarea name="message_text" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn">Send Message</button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('newMessageModal');
document.getElementById('newMessageBtn').onclick = () => modal.style.display = 'flex';
document.getElementById('closeModal').onclick = () => modal.style.display = 'none';
window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };
let escPressCount = 0; let escTimer = null;
document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        escPressCount++;
        clearTimeout(escTimer);
        escTimer = setTimeout(() => escPressCount = 0, 3000);
        if (escPressCount >= 3) { window.location.href = 'about:blank'; }
    }
});
</script>
</body>
</html>
