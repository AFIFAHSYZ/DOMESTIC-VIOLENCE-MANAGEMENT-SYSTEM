<?php
session_start();
require '../conn.php';
require '../session_timeout.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'police') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['case_id'])) {
    header("Location: police_cases.php");
    exit();
}

$case_id = $_GET['case_id'];
$message = "";

// Handle form submission to update status and note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_id'])) {
    $status_id = $_POST['status_id'];
    $update_note = $_POST['update_note'] ?? '';

$stmt = $pdo->prepare(
    "UPDATE dv_case 
     SET status_id = ?, update_note = ?, updated_by = ? 
     WHERE case_id = ? AND assigned_to = ?"
);
$stmt->execute([$status_id, $update_note, $user_id, $case_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['status_update_msg'] = "✅ Case status updated successfully.";
    } else {
        $_SESSION['status_update_msg'] = "⚠️ Update failed. You are not the assigned lawyer or the case does not exist.";
    }

    header("Location: pol_view_details.php?case_id=" . $case_id);
    exit();
}

if (isset($_SESSION['status_update_msg'])) {
    $message = $_SESSION['status_update_msg'];
    unset($_SESSION['status_update_msg']);
}


$stmt = $pdo->prepare("
    SELECT 
        c.*, s.status_name, 
        r.full_name AS reporter_name,
        p.full_name AS lawyer_name,
        v.full_name AS v_full_name
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    JOIN sys_user r ON c.reported_by = r.user_id
    JOIN sys_user p ON c.assigned_lawyer = p.user_id
    JOIN victim v ON c.victim_id = v.victim_id
    WHERE c.case_id = ? AND c.assigned_to = ?
");
$stmt->execute([$case_id, $user_id]);
$case = $stmt->fetch();

if (!$case) {
    echo "<p>Case not found or you are not authorized to view this case.</p>";
    exit();
}

// Fetch page number from query string
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 
    ? (int) $_GET['page'] 
    : 1;

$limit = 5;
$offset = ($page - 1) * $limit;

// Count total evidence for this case
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM evidence WHERE case_id = ?");
$countStmt->execute([$case_id]);
$totalEvidences = $countStmt->fetchColumn();
$totalPages = ceil($totalEvidences / $limit);

// Fetch page number from query string
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 
    ? (int) $_GET['page'] 
    : 1;

$limit = 5;
$offset = ($page - 1) * $limit;

// Count total evidence for this case
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM evidence WHERE case_id = ?");
$countStmt->execute([$case_id]);
$totalEvidences = $countStmt->fetchColumn();
$totalPages = ceil($totalEvidences / $limit);

// Fetch attached evidence for this case with LIMIT
$evidenceStmt = $pdo->prepare("
    SELECT evidence_id, description, uploaded_at, file_name, mime_type 
    FROM evidence 
    WHERE case_id = ?
    ORDER BY uploaded_at DESC
    LIMIT ? OFFSET ?
");
$evidenceStmt->bindValue(1, $case_id, PDO::PARAM_INT);
$evidenceStmt->bindValue(2, $limit, PDO::PARAM_INT);
$evidenceStmt->bindValue(3, $offset, PDO::PARAM_INT);
$evidenceStmt->execute();
$evidences = $evidenceStmt->fetchAll(PDO::FETCH_ASSOC);



// Fetch user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, email, profilepic, user_id FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Case Details | DVMS</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body>
<div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-profile">
                    <?php if ($user['profilepic']): ?>
                        <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>" alt="Profile" class="user-avatar">
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
                <li><a href="police_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li  class="active"><a href="police_cases.php"><i class="fas fa-briefcase"></i> My Cases</a></li>
                <li >
                <a href="police_messages.php"><i class="fas fa-envelope"></i> Messages
                    <?php if ($unread > 0): ?>
                        <span class="notification-badge"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
                </li>
                <li><a href="police_reports.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
                <li><a href="police_profile.php"><i class="fas fa-user-cog"></i> My Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-folder-open"></i> Assigned Cases</h1>
        </header>
        <a href="police_cases.php" class="btn" style="margin:20px 0; display:inline-block;">
            <i class="fas fa-arrow-left"></i> Back to My Cases
        </a>

            <?php if (!empty($message)): ?>
                <div class="success-msg1"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>


        <section class="content-section">
            <div class="form-header">
                <h2><i class="fas fa-file-alt"></i> Case Details</h2>
            </div>
            <form method="POST">
                <p><strong>Case ID:</strong> <?= $case['case_id'] ?></p>
                <p><strong>Reported by:</strong> <?= htmlspecialchars($case['reporter_name']) ?></p>
                <p><strong>Report Date:</strong> <?= date('M d, Y', strtotime($case['report_date'])) ?></p>
                <p><strong>Victim Name:</strong> <?= htmlspecialchars($case['v_full_name']) ?></p>
                <p><strong>Abuse Type:</strong> <?= htmlspecialchars($case['abuse_type']) ?></p>
                <p><strong>Abuse Description:</strong> <?= htmlspecialchars($case['abuse_desc']) ?></p>
                <p><strong>Address Line 1:</strong> <?= htmlspecialchars($case['address_line1']) ?></p>
                <p><strong>Address Line 2:</strong> <?= htmlspecialchars($case['address_line2']) ?></p>
                <p><strong>City:</strong> <?= htmlspecialchars($case['city']) ?></p>
                <p><strong>Postal Code:</strong> <?= htmlspecialchars($case['postal_code']) ?></p>
                <p><strong>Lawyer Name:</strong> <?= htmlspecialchars($case['lawyer_name']) ?></p>
                <hr>                    
<h3><i class="fas fa-paperclip"></i> Attached Evidence</h3>

<?php if (!empty($evidences)): ?>
    <ul style="list-style: none; padding: 0;">
        <?php foreach ($evidences as $ev): ?>
            <li style="margin-bottom: 10px;">
                <i class="fas fa-file"></i>
<!-- View -->
<a href="../get_evidence.php?id=<?= $ev['evidence_id'] ?>" target="_blank">
    <?= htmlspecialchars($ev['file_name']) ?>
</a>

<!-- Download -->
<a href="../get_evidence.php?id=<?= $ev['evidence_id'] ?>&download=1"
   style="margin-left: 8px; font-size: 0.9em; color: #007BFF;">
    <i class="fas fa-download"></i> Download
</a>
                <small style="color: gray;">
                    (Uploaded: <?= date('M d, Y H:i', strtotime($ev['uploaded_at'])) ?>)
                </small>
                <?php if (!empty($ev['description'])): ?>
                    <div style="font-size: 0.9em; color: #555;">
                        <?= nl2br(htmlspecialchars($ev['description'])) ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

<div style="text-align:center; margin-top:10px; font-size:18px;">
    <?php if ($page > 1): ?>
        <a href="?case_id=<?= $case_id ?>&page=<?= $page - 1 ?>">&lt;&lt;</a>
    <?php else: ?>
        <span style="color:#ccc;">&lt;&lt;</span>
    <?php endif; ?>

    &nbsp;&nbsp;

    <?php if ($page < $totalPages): ?>
        <a href="?case_id=<?= $case_id ?>&page=<?= $page + 1 ?>">&gt;&gt;</a>
    <?php else: ?>
        <span style="color:#ccc;">&gt;&gt;</span>
    <?php endif; ?>
</div>

<?php else: ?>
    <p style="color: gray;">No evidence files have been attached for this case.</p>
<?php endif; ?>


                <hr>
                <p><strong>Status:</strong>
                    <select name="status_id" required>
                        <?php
                        $statuses = [
                            2 => "Under Investigation", 5 => "Analysing Evidence", 6 => "Legal Action Initiated",
                        ];
                        foreach ($statuses as $id => $name) {
                            $selected = $case['status_id'] == $id ? "selected" : "";
                            echo "<option value=\"$id\" $selected>$name</option>";
                        }
                        ?>
                    </select>
                </p>

                <p><strong>Update Note:</strong><span style="color: red;">*</span></p>
                <textarea name="update_note" required rows="4" style="width:100%;"><?= htmlspecialchars($case['update_note']) ?></textarea>

                <div class="btn-row" style="margin-top: 1em;">
                    <button type="submit" class="btn1"><i class="fas fa-check-circle"></i> Update Status</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
