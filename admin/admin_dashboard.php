<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

/* ---------------------------
   AUTHENTICATION
--------------------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

/* ---------------------------
   FETCH ADMIN INFO
--------------------------- */
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT user_id, full_name, email, phone_num, profilepic 
    FROM SYS_USER 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    die("Admin user not found.");
}

/* ---------------------------
   LOG ADMIN LOGIN
--------------------------- */
$stmt = $pdo->prepare("
    INSERT INTO system_logs (user_id, event_type, description) 
    VALUES (?, 'login', 'User logged in successfully')
");
$stmt->execute([$user_id]);

/* ---------------------------
   CASE STATUS GROUPS
--------------------------- */
$active_statuses  = [2, 3, 4, 5, 6, 7, 8];
$pending_statuses = [1];
$closed_statuses  = [9, 10, 11, 12, 13, 14];

function getCaseCountByStatuses($pdo, $statuses) {
    if (empty($statuses)) return 0;
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM dv_case 
        WHERE status_id IN ($placeholders)
    ");
    $stmt->execute($statuses);
    return $stmt->fetchColumn();
}

/* ---------------------------
   DASHBOARD STATS
--------------------------- */
$total_users         = $pdo->query("SELECT COUNT(*) FROM SYS_USER")->fetchColumn();
$active_cases        = getCaseCountByStatuses($pdo, $active_statuses);
$pending_cases       = getCaseCountByStatuses($pdo, $pending_statuses);
$closed_cases        = getCaseCountByStatuses($pdo, $closed_statuses);
$pending_assignments = $pdo->query("SELECT COUNT(*) FROM dv_case WHERE assigned_to IS NULL")->fetchColumn();
$new_reports_today   = $pdo->query("SELECT COUNT(*) FROM dv_case WHERE report_date::date = CURRENT_DATE")->fetchColumn();

/* ---------------------------
   RECENT ACTIVITY
--------------------------- */
$latest_logins = $pdo->query("
    SELECT full_name, event_time 
    FROM system_logs 
    JOIN SYS_USER USING(user_id)
    WHERE event_type = 'login' 
    ORDER BY event_time DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$security_events = $pdo->query("
    SELECT description, event_time 
    FROM system_logs 
    WHERE event_type = 'security' 
    ORDER BY event_time DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | DV Assistance System</title>
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
                    <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>" 
                         alt="Profile" class="user-avatar">
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
                <li class="active"><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="admin_logs.php"><i class="fas fa-clipboard-list"></i> System Monitoring</a></li>
                <li><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Header -->
        <header class="content-header">
            <h1><i class="fas fa-home"></i> Admin Dashboard</h1>
        </header>

        <!-- Real-Time Stats -->
        <section class="content-section">
            <h2><i class="fas fa-tachometer-alt"></i> Real-Time Stats</h2>
            <div class="stats-grid">
                <div class="stat-card"><i class="fas fa-users"></i><p>Total Users</p><h3><?= $total_users ?></h3></div>
                <div class="stat-card"><i class="fas fa-folder-open"></i><p>Active Cases</p><h3><?= $active_cases ?></h3></div>
                <div class="stat-card"><i class="fas fa-hourglass-half"></i><p>Pending Cases</p><h3><?= $pending_cases ?></h3></div>
                <div class="stat-card"><i class="fas fa-check-circle"></i><p>Closed Cases</p><h3><?= $closed_cases ?></h3></div>
                <div class="stat-card"><i class="fas fa-tasks"></i><p>Pending Assignments</p><h3><?= $pending_assignments ?></h3></div>
                <div class="stat-card"><i class="fas fa-file-alt"></i><p>New Reports Today</p><h3><?= $new_reports_today ?></h3></div>
            </div>
        </section>

        <!-- Recent Activity -->
        <section class="content-section">
            <h2><i class="fas fa-bolt"></i> Recent Activity</h2>
            <div class="activity-columns">
                <div>
                    <h3><i class="fas fa-sign-in-alt"></i> Latest Logins</h3>
                    <ul>
                        <?php foreach ($latest_logins as $log): ?>
                            <li><?= htmlspecialchars($log['full_name']) ?> <small>(<?= $log['event_time'] ?>)</small></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="content-section">
            <h2><i class="fas fa-tools"></i> Quick Actions</h2>
            <div class="quick-actions-grid">
                <a href="admin_add_user.php" class="quick-action"><i class="fas fa-user-plus"></i> Add User</a>
                <a href="admin_assign_case.php" class="quick-action"><i class="fas fa-clipboard-check"></i> Assign Case</a>
                <a href="admin_reporting.php" class="quick-action"><i class="fas fa-file-export"></i> Generate Report</a>
                <a href="admin_backup.php" class="quick-action"><i class="fas fa-database"></i> Backup File</a>
            </div>
        </section>
    </main>
</div>
</body>
</html>
