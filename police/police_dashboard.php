<?php
session_start();
require '../conn.php';
require '../session_timeout.php';


// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] !== 'police') {
    header("Location: unauthorized.php");
    exit();
}

// Get police user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, email, profilepic, user_id FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Police user not found.");
}

// Get only ONE most recent assigned case
$recent_cases = $pdo->prepare("
    SELECT c.case_id, c.case_id as case_ref, c.report_date, s.status_name, c.status_id, c.address_line1, c.address_line2, c.city, c.postal_code, c.state
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    WHERE c.assigned_to = ?
    ORDER BY c.report_date DESC
    LIMIT 1
");
$recent_cases->execute([$user_id]);  
$cases = $recent_cases->fetchAll();


$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

// After verifying credentials and starting session
$stmt = $pdo->prepare("INSERT INTO system_logs (user_id, event_type, description) VALUES (?, 'login', 'User logged in successfully')");
$stmt->execute([$user_id]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Police Dashboard | DV Assistance System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
        <!-- Sidebar Navigation -->
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
                <li class="active"><a href="police_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="police_cases.php"><i class="fas fa-briefcase"></i> My Cases</a></li>
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
    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-home"></i> Police Dashboard</h1>
        </header>

<?php if ($unread > 0): ?>
    <div class="message-alert">
        📩 You have <strong><?= $unread ?></strong> unread 
        message<?= $unread > 1 ? 's' : '' ?>. 
        <a href="law_messages.php">View Messages</a>.
    </div>
<?php endif; ?>

        <section class="content-section">       
            <h2><i class="fas fa-history"></i> Recent Cases</h2>

            <?php if (count($cases) > 0): ?>
                <div class="cases-list">
                    <?php foreach ($cases as $case): ?>
                        <div class="case-item">
                            <div class="case-header">
                                <h3>Case ID: <?= htmlspecialchars($case['case_ref']) ?></h3>
                                <span class="case-status <?= strtolower(str_replace(' ', '-', $case['status_name'])) ?>">
                                    <?= htmlspecialchars($case['status_name']) ?>
                                </span>
                            </div>
                            <div class="case-meta">
                                <span>
                                    <i class="fas fa-map-pin"></i>
                                    <?= htmlspecialchars($case['address_line1']) ?>
                                    <?php 
                                        if (!empty($case['address_line2'])) {
                                            echo ', ' . htmlspecialchars($case['address_line2']);
                                        }
                                    ?>
                                    , <?= htmlspecialchars($case['city']) ?>, <?= htmlspecialchars($case['postal_code']) ?>, <?= htmlspecialchars($case['state']) ?>
                                </span>
                            </div>
                            <div class="case-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($case['report_date'])) ?></span>
                            </div>
                            <div class="case-actions">
                                <a href="pol_view_details.php?case_id=<?= $case['case_id'] ?>">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No cases found</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="content-section">
            <h2><i class="fas fa-toolbox"></i> Quick Access</h2>
            <div class="resources-grid">
                <a href="police_cases.php" class="resource-card">
                    <i class="fas fa-search"></i>
                    <h3>Manage Cases</h3>
                </a>
                <a href="police_messages.php" class="resource-card">
                    <i class="fas fa-envelope-open-text"></i>
                    <h3>Secure Messaging</h3>
                </a>
                <a href="police_reports.php" class="resource-card">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Analytics & Reports</h3>
                </a>
            </div>
        </section>
    </main>
</div>

<script src="js/script.js"></script>

<script>
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => el.remove());
    }, 10000); // hides after 10 seconds
</script>
</body>
</html>