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

// --- FETCH ALL CASES WITHOUT ASSIGNED LAWYER ---
$caseStmt = $pdo->prepare("
    SELECT 
        c.case_id, c.abuse_type, c.abuse_desc, c.report_date, 
        s.status_name, 
        v.full_name AS victim_name,
        r.full_name AS reporter_name,
        c.city, c.state
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    JOIN sys_user r ON c.reported_by = r.user_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    WHERE c.assigned_lawyer IS NULL
    ORDER BY c.report_date DESC
");
$caseStmt->execute();
$cases = $caseStmt->fetchAll();

function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unassigned Pro Bono Cases | Lawyer</title>
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
            <h1><i class="fas fa-gavel"></i> Unassigned Cases</h1>
        </header>
        <section class="content-section">
            <?php if (count($cases) === 0): ?>
                <div class="info-msg1">No unassigned cases at this time.</div>
            <?php else: ?>
                <table class="case-table">
                    <thead>
                        <tr>
                            <th>Case ID</th>
                            <th>Report Date</th>
                            <th>Victim Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>State</th>
                            <th>Reporter</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cases as $c): ?>
                        <tr>
                            <td><?= h($c['case_id']) ?></td>
                            <td><?= date('M d, Y', strtotime($c['report_date'])) ?></td>
                            <td><?= h($c['victim_name']) ?></td>
                            <td><?= h($c['abuse_type']) ?></td>
                            <td><?= h($c['status_name']) ?></td>
                            <td><?= h($c['state']) ?></td>
                            <td><?= h($c['reporter_name']) ?></td>
                            <td>
                                <a href="law_pro_bono.php?case_id=<?= h($c['case_id']) ?>" class="btn1">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>