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
// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset = ($page - 1) * $limit;

// Filter
$whereClause = "1=1";
$params = [];

if (!empty($_GET['user'])) {
    $whereClause .= " AND u.full_name LIKE ?";
    $params[] = '%' . $_GET['user'] . '%';
}
if (!empty($_GET['event'])) {
    $whereClause .= " AND l.event_type = ?";
    $params[] = $_GET['event'];
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $whereClause .= " AND DATE(l.event_time) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
}

// Count total records for pagination
$countQuery = "
    SELECT COUNT(*) FROM system_logs l
    JOIN SYS_USER u ON l.user_id = u.user_id
    WHERE $whereClause
";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

// Fetch logs for current page
$query = "
    SELECT l.log_id, u.full_name, u.email, l.event_type, l.description, l.event_time
    FROM system_logs l
    JOIN SYS_USER u ON l.user_id = u.user_id
    WHERE $whereClause
    ORDER BY l.event_time DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Monitoring | Admin Dashboard</title>
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
                <li class="active"><a href="admin_logs.php"><i class="fas fa-clipboard-list"></i> System Monitoring</a></li>
                <li><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1>System Monitoring</h1>
        </header>

        <div class="content-section">
            <h2><i class="fas fa-clipboard-list"></i> Activity Logs</h2>

<form method="GET" class="filter-form">
    <input type="text" name="user" placeholder="Search by user..." value="<?= htmlspecialchars($_GET['user'] ?? '') ?>">
    <select name="event">
        <option value="">All Events</option>
        <option value="login" <?= ($_GET['event'] ?? '') === 'login' ? 'selected' : '' ?>>Login</option>
        <option value="logout" <?= ($_GET['event'] ?? '') === 'logout' ? 'selected' : '' ?>>Logout</option>
        <option value="update" <?= ($_GET['event'] ?? '') === 'update' ? 'selected' : '' ?>>Data Update</option>
        <option value="error" <?= ($_GET['event'] ?? '') === 'error' ? 'selected' : '' ?>>Error</option>
    </select>
    <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
    <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
    <button type="submit" class="btn btn-outline">Filter</button>
</form>
            <!-- Logs Table -->
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Event</th>
                        <th>Description</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['full_name']) ?></td>
                                <td><?= htmlspecialchars($log['email']) ?></td>
                                <td><?= htmlspecialchars($log['event_type']) ?></td>
                                <td><?= htmlspecialchars($log['description']) ?></td>
                                <td><?= date('Y-m-d H:i:s', strtotime($log['event_time'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination Controls -->
<div class="pagination" role="navigation" aria-label="Pagination">
    <?php if ($totalPages > 1): ?>
        <?php
            $window = 3; // Number of page links to show
            $start = max(1, $page - 1);
            $end = min($totalPages, $start + $window - 1);

            // Adjust start if close to end
            if ($end - $start + 1 < $window) {
                $start = max(1, $end - $window + 1);
            }

            // Previous arrow
            if ($page > 1):
                $prevPage = $page - 1;
                $prevQuery = http_build_query(array_merge($_GET, ['page' => $prevPage]));
        ?>
            <a class="page-link" href="?<?= $prevQuery ?>" aria-label="Previous">&laquo;</a>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): 
            $pageQuery = http_build_query(array_merge($_GET, ['page' => $i]));
        ?>
            <a class="page-link<?= $i == $page ? ' active' : '' ?>"
               href="?<?= $pageQuery ?>"
               aria-current="<?= $i == $page ? 'page' : false ?>">
               <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php
            // Next arrow
            if ($page < $totalPages):
                $nextPage = $page + 1;
                $nextQuery = http_build_query(array_merge($_GET, ['page' => $nextPage]));
        ?>
            <a class="page-link" href="?<?= $nextQuery ?>" aria-label="Next">&raquo;</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
        </div>
    </main>
</div>
</body>
</html>