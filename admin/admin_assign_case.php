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

// --- FETCH ADMIN USER DETAILS ---
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    die("Admin user not found.");
}

// --- PAGINATION & FILTERS ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 
    ? (int) $_GET['page'] 
    : 1;

$limit = 5;
$offset = ($page - 1) * $limit;

$startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate   = !empty($_GET['end_date']) ? $_GET['end_date'] : null;

// Base WHERE clause: either officer or lawyer unassigned
$whereClauses = ["(c.assigned_to IS NULL OR c.assigned_lawyer IS NULL)"];
$params = [];

// Apply date filters
if ($startDate) {
    $whereClauses[] = "c.report_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $whereClauses[] = "c.report_date <= ?";
    $params[] = $endDate;
}

$whereSQL = implode(" AND ", $whereClauses);

// --- COUNT TOTAL ---
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dv_case c WHERE $whereSQL");
$countStmt->execute($params);
$totalCases = $countStmt->fetchColumn();
$totalPages = ceil($totalCases / $limit);

// --- FETCH CASES ---
$sql = "
    SELECT c.case_id, c.abuse_type, c.report_date, s.status_name, 
           r.full_name AS reporter_name, v.full_name AS victim_name
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    JOIN sys_user r ON c.reported_by = r.user_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    WHERE $whereSQL
    ORDER BY c.report_date DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unassigned Cases | Admin</title>
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
                    <img src="get_profilepic.php?id=<?= h($user['user_id']) ?>" alt="Profile" class="user-avatar">
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
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="admin_logs.php"><i class="fas fa-clipboard-list"></i> System Monitoring</a></li>
                <li ><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li class="active"><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-user-plus"></i> Unassigned Cases</h1>
        </header>
        <section class="content-section">

            <!-- Filter Form -->
            <form method="GET" class="row g-3 mb-4">
                <div class="filter-form">
                    <label for="start_date" class="form-label">From:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control"
                           value="<?= isset($_GET['start_date']) ? h($_GET['start_date']) : '' ?>">

                    <label for="end_date" class="form-label">To:</label>
                    <input type="date" name="end_date" id="end_date" class="form-control"
                           value="<?= isset($_GET['end_date']) ? h($_GET['end_date']) : '' ?>">

                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>

            <!-- Alert Messages -->
            <?php if ($totalCases === 0): ?>
                <div class="message-alert">No unassigned cases found for the selected filters.</div>
            <?php else: ?>
                <div class="message-alert">
                    Showing <?= count($cases) ?> case(s) out of <?= $totalCases ?> total
                </div>
            <?php endif; ?>

            <!-- Cases Table -->
            <?php if (count($cases) > 0): ?>
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Case ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Report Date</th>
                            <th>Victim</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cases as $case): ?>
                        <tr>
                            <td><?= h($case['case_id']) ?></td>
                            <td><?= h($case['abuse_type']) ?></td>
                            <td><?= h($case['status_name']) ?></td>
                            <td><?= date('M d, Y', strtotime($case['report_date'])) ?></td>
                            <td><?= h($case['victim_name']) ?></td>
                            <td>
                                <a class="btn btn-primary" href="assign_to_officer_lawyer.php?case_id=<?= h($case['case_id']) ?>">
                                    <i class="fas fa-user-plus"></i> Assign
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>
                                        <?= $startDate ? '&start_date=' . urlencode($startDate) : '' ?>
                                        <?= $endDate ? '&end_date=' . urlencode($endDate) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
