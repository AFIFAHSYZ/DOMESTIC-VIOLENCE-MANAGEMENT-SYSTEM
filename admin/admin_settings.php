<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Admin user not found.");
}
// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_logs') {
        $older_than = $_POST['older_than'] ?? '';
        if ($older_than) {
            $stmt = $pdo->prepare("DELETE FROM system_logs WHERE DATE(event_time) < ?");
            $stmt->execute([$older_than]);
            $message = "Logs older than $older_than have been deleted.";
        }
    }
}

// Admin details
$admin_name = $_SESSION['full_name'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? 'admin@example.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance & Settings</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<body>
<div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-profile">
                    <?php if ($user['profilepic']): ?>
                        <img src="get_profilepic.php?id=<?= $user['user_id'] ?>" alt="Profile" class="user-avatar">
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
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="admin_logs.php"><i class="fas fa-clipboard-list"></i> System Monitoring</a></li>
                <li ><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li class="active"><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1>Maintenance & Settings</h1>
        </header>

        <div class="content-section">
            <?php if (isset($message)) : ?>
                <div class="success-msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <h2>System Information</h2>
            <ul>
                <li><strong>Site Name:</strong> Domestic Violence Management System</li>
                <li><strong>Support Email:</strong> support@dvms.com</li>
                <li><strong>Version:</strong> 1.0 (FYP)</li>
                <li><strong>Logged in as:</strong> <?= htmlspecialchars($admin_name) ?> (<?= htmlspecialchars($admin_email) ?>)</li>
            </ul>

            <hr>

            <h2>Maintenance Tools</h2>

            <!-- Clear Logs -->
            <form method="POST" style="margin-bottom: 20px;">
                <input type="hidden" name="action" value="clear_logs">
                <label for="older_than" >Clear logs before:</label>
                <input type="date" name="older_than" style="margin-bottom: 20px;" required>
                <button type="submit" class="btn btn-danger" style="background:#d32f2f; border:none;">Delete Old Logs</button>
            </form>

            
            <!-- Manual Backup (PostgreSQL) -->
            <form method="POST" action="backup_pgsql.php">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-download"></i> Manual Backup (SQL)
                </button>
            </form>

        </div>
    </main>
</div>
</body>
</html>
