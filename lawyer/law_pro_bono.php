<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    header("Location: ../login.php");
    exit();
}


// --- FETCH LAWYER USER DETAILS ---
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    die("Lawyer user not found.");
}

// --- GET CASE ID FROM URL ---
$case_id = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;
if ($case_id <= 0) {
    die("Invalid case ID.");
}

// --- FETCH CASE ---
$stmt = $pdo->prepare("
    SELECT 
        c.*, s.status_name, 
        r.full_name AS reporter_name,
        o.full_name AS officer_name,
        l.full_name AS lawyer_name,
        v.full_name AS victim_name
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    JOIN sys_user r ON c.reported_by = r.user_id
    LEFT JOIN sys_user o ON c.assigned_to = o.user_id
    LEFT JOIN sys_user l ON c.assigned_lawyer = l.user_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    WHERE c.case_id = ?
");
$stmt->execute([$case_id]);
$case = $stmt->fetch();
if (!$case) {
    die("Case not found.");
}

// Only allow take if not yet assigned to any lawyer
if ($case['assigned_lawyer'] !== null) {
    die("This case is already assigned to a lawyer.");
}

$successMsg = $errorMsg = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assign this case to the currently logged in lawyer
    $assignStmt = $pdo->prepare("UPDATE dv_case SET assigned_lawyer = ? WHERE case_id = ?");
    $assignStmt->execute([$user_id, $case_id]);
    $successMsg = "You have successfully taken this case as the pro bono lawyer.";
    // Refresh case info
    $stmt->execute([$case_id]);
    $case = $stmt->fetch();
}

function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Pro Bono Case | Lawyer</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
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
                <h3><?= h($user['full_name']) ?></h3>
                <p><?= h($user['email']) ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="lawyer_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="lawyer_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <li class="active"><a href="law_unassigned.php"><i class="fas fa-user-plus"></i> Unassigned Case</a></li>
                <li><a href="law_messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                                <li><a href="law_reporting.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
<li><a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a></li>
                <li><a href="law_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-gavel"></i> Take Pro Bono Case</h1>
        </header>
        <a href="law_unassigned.php" class="btn">&larr; Back to Case List</a>
        <section class="content-section">

            <div class="form-section">
                <?php if ($successMsg): ?>
                    <div class="success-msg1"><?= h($successMsg) ?></div>
                <?php elseif ($errorMsg): ?>
                    <div class="error-msg1"><?= h($errorMsg) ?></div>
                <?php endif; ?>

                <h2>Case Details</h2>
                <div class="case-details">
                    <b>Case ID:</b> <?= h($case['case_id']) ?><br>
                    <b>Type:</b> <?= h($case['abuse_type']) ?><br>
                    <b>Status:</b> <?= h($case['status_name']) ?><br>
                    <b>Reporter:</b> <?= h($case['reporter_name']) ?><br>
                    <b>Report Date:</b> <?= date('M d, Y', strtotime($case['report_date'])) ?><br>
                    <p><strong>Victim Name:</strong> <?= htmlspecialchars($case['victim_name']) ?></p>
                    <p><strong>Abuse Type:</strong> <?= htmlspecialchars($case['abuse_type']) ?></p>
                    <p><strong>Abuse Description:</strong> <?= htmlspecialchars($case['abuse_desc']) ?></p>
                    <p><strong>Address Line 1:</strong> <?= htmlspecialchars($case['address_line1']) ?></p>
                    <p><strong>Address Line 2:</strong> <?= htmlspecialchars($case['address_line2']) ?></p>
                    <p><strong>City:</strong> <?= htmlspecialchars($case['city']) ?></p>
                    <p><strong>Postal Code:</strong> <?= htmlspecialchars($case['postal_code']) ?></p>
                    <p><strong>State:</strong> <?= htmlspecialchars($case['state']) ?></p>
                </div>
                <hr>
                <?php if (!$successMsg): ?>
                <form method="POST">
                    <button type="submit" class="btn1"><i class="fas fa-check"></i> Take This Case</button>
                </form>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>