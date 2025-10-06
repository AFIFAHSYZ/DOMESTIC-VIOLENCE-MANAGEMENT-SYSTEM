<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['case_id']) || !is_numeric($_GET['case_id'])) {
    die("Missing or invalid case_id in URL");
}

$case_id = (int)$_GET['case_id'];

// Adjusted SQL with victim and offender joins
$stmt = $pdo->prepare("
    SELECT 
        c.case_id,
        c.report_date,
        v.full_name AS victim_name,
        o.full_name AS offender_name,
        c.abuse_type,
        c.abuse_desc,
        c.address_line1,
        c.address_line2,
        c.city,
        c.postal_code,
        s.status_name,
        r.full_name AS lawyer_name,
        p.full_name AS officer_name,
        u.full_name AS reporter_name

    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    LEFT JOIN offender o ON c.offender_id = o.offender_id
    LEFT JOIN sys_user r ON c.assigned_lawyer = r.user_id
    LEFT JOIN sys_user p ON c.assigned_to = p.user_id
    LEFT JOIN sys_user u ON c.reported_by = u.user_id
    WHERE c.case_id = ? AND c.reported_by = ?
    LIMIT 1
");
$stmt->execute([$case_id, $user_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    echo "<p>Case not found or you are not authorized to view this case.</p>";
    exit();
}

// Fetch user info for sidebar
$userStmt = $pdo->prepare("SELECT user_id, full_name, email, profilepic FROM sys_user WHERE user_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Fetch unread message count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Details | DV Assistance System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-profile">
                        <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" 
                    alt="Profile" class="user-avatar">
                    <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="citizen_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li class="active"><a href="ctzn_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                    <li><a href="ctzn_report.php"><i class="fas fa-plus-circle"></i> Report New Case</a></li>
                    <li><a href="ctzn_messages.php"><i class="fas fa-envelope"></i> Messages
                    <span class="notification-badge"><?= $unread ?></span></a></li>
                    <li><a href="ctzn_resources.php"><i class="fas fa-book"></i> Resources</a></li>
                    <li><a href="ctzn_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a><li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1><i class="fas fa-folder-open"></i> Case Details</h1>
            </header>
            <?php
echo '
<div style="
    background: #fff3cd;
    border-left: 6px solid #ffc107;
    padding: 12px 18px;
    margin: 15px 0;
    border-radius: 6px;
    color: #856404;
    display: flex;
    align-items: center;
    font-size: 14px;
">
    <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>
    For your safety: press  <strong> Esc </strong>  button on your keyboard 3 times quickly to exit this system.
</div>
';
?>
            <a href="ctzn_cases.php" class="back-link" style="margin:20px 0; display:inline-block;">
                <i class="fas fa-arrow-left"></i> Back to My Cases
            </a>

<section class="content-section">
    <div class="form-header">
        <h2><i class="fas fa-file-alt"></i> Case Information</h2>
    </div>

    <!-- Case Overview -->
    <h3>Case Overview</h3>
    <p><strong>Case ID:</strong> <?= htmlspecialchars($case['case_id']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($case['status_name']) ?></p>
    <p><strong>Report Date:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($case['report_date']))) ?></p>
    <p><strong>Reported by:</strong> <?= htmlspecialchars($case['reporter_name'] ?? "-") ?></p>

    <hr>
    <!-- People Involved -->
    <h3>People Involved</h3>
    <ul>
        <p><strong>Victim:</strong> <?= htmlspecialchars($case['victim_name'] ?? "Not provided") ?></p>
        <li><strong>Offender:</strong> <?= htmlspecialchars($case['offender_name'] ?? "Not provided") ?></li>
        <li><strong>Assigned Officer:</strong> <?= htmlspecialchars($case['officer_name'] ?? "Not assigned") ?>
            <?php if ($case['officer_name']): ?>
                | <a href="ctzn_messages.php?to=<?= urlencode($case['officer_name']) ?>"><i class="fas fa-envelope"></i> Go To Messages</a>
            <?php endif; ?>
        </li>
                <li><strong>Assigned Lawyer:</strong> <?= htmlspecialchars($case['lawyer_name'] ?? "Not assigned") ?>
            <?php if ($case['lawyer_name']): ?>
                | <a href="ctzn_messages.php?to=<?= urlencode($case['lawyer_name']) ?>"><i class="fas fa-envelope"></i> Go To Messages</a>
            <?php endif; ?>
        </li>

    </ul>
<hr>
    <!-- Abuse Details -->
    <h3>Abuse Details</h3>
    <p><strong>Type:</strong>
        <?php
        if (!empty($case['abuse_type'])) {
            $types = explode(',', $case['abuse_type']);
            echo '<ul>';
            foreach ($types as $type) {
                echo '<li>' . htmlspecialchars(trim($type)) . '</li>';
            }
            echo '</ul>';
        } else {
            echo 'Not provided';
        }
        ?>
    </p>
    <p><strong>Description:</strong> <?= htmlspecialchars($case['abuse_desc'] ?? "Not provided") ?></p>
<hr>
    <!-- Location -->
    <h3>Location</h3>
    <ul>
        <li><strong>Address Line 1:</strong> <?= htmlspecialchars($case['address_line1'] ?? "-") ?></li>
        <li><strong>Address Line 2:</strong> <?= htmlspecialchars($case['address_line2'] ?? "-") ?></li>
        <li><strong>City:</strong> <?= htmlspecialchars($case['city'] ?? "-") ?></li>
        <li><strong>Postal Code:</strong> <?= htmlspecialchars($case['postal_code'] ?? "-") ?></li>
    </ul>

    <!-- Actions -->
    <div style="margin-top:20px;">
<a href="download_case.php?id=<?= $case['case_id'] ?>" class="btn"><i class="fas fa-download"></i> Download PDF</a>
<a href="#" onclick="window.print(); return false;" class="btn">
  <i class="fas fa-print"></i> Print
</a>    </div>
</section>
        </main>
    </div>
    


<script src="js/script.js"></script>
<script>
let escPressCount = 0;
let escTimer = null;

document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        escPressCount++;

        clearTimeout(escTimer);
        escTimer = setTimeout(() => escPressCount = 0, 3000);

        if (escPressCount >= 3) {
            window.location.replace('logout.php');
        }
    }
});
</script>


</body>
</html>

