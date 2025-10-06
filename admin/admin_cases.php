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

// Abuse type options for filter
$defaultAbuseTypes = [
    "Physical Abuse",
    "Emotional/Psychological Abuse",
    "Verbal Abuse",
    "Sexual Abuse",
    "Financial/Economic Abuse",
    "Technological/Digital Abuse",
    "Cultural/Spiritual Abuse",
    "Neglect (caregiving relationships)",
    "Other"
];

// For filter dropdowns, dynamically fetch all unique types
$abuseTypesStmt = $pdo->query("SELECT DISTINCT abuse_type FROM dv_case WHERE abuse_type IS NOT NULL AND abuse_type != ''");
$dbAbuseTypes = $abuseTypesStmt->fetchAll(PDO::FETCH_COLUMN);
$abuseTypes = array_unique(array_merge($defaultAbuseTypes, $dbAbuseTypes));

// Filters
$abuse_type = isset($_GET['abuse_type']) ? trim($_GET['abuse_type']) : '';
$victim_name = isset($_GET['victim_name']) ? trim($_GET['victim_name']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$report_date = isset($_GET['report_date']) ? trim($_GET['report_date']) : '';

$where = [];
$params = [];

// Abuse Type Filter
if (!empty($abuse_type)) {
    $where[] = "c.abuse_type = ?";
    $params[] = $abuse_type;
}

// Victim Name Filter (partial match)
if (!empty($victim_name)) {
    $where[] = "v.full_name LIKE ?";
    $params[] = "%$victim_name%";
}

// Status Filter
if (!empty($status)) {
    $where[] = "s.status_name = ?";
    $params[] = $status;
}

// Report Date Filter (YYYY-MM-DD)
if (!empty($report_date)) {
    $where[] = "DATE(c.report_date) = ?";
    $params[] = $report_date;
}

$where_sql = "";
if ($where) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

// --- PAGINATION HANDLING ---
$per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) FROM dv_case c
    LEFT JOIN case_status s ON c.status_id = s.status_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    $where_sql
";
$countStmt = $pdo->prepare($count_sql);
$countStmt->execute($params);
$total_cases = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total_cases / $per_page));

// Get filtered, paginated cases
$case_sql = "
    SELECT c.case_id, c.abuse_type, c.status_id, s.status_name, c.report_date, v.full_name as victim_name
    FROM dv_case c
    LEFT JOIN case_status s ON c.status_id = s.status_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    $where_sql
    ORDER BY c.report_date DESC
    LIMIT $per_page OFFSET $offset
";
$caseStmt = $pdo->prepare($case_sql);
$caseStmt->execute($params);
$cases = $caseStmt->fetchAll();

// Fetch case statuses for dropdown
$statusStmt = $pdo->query("SELECT DISTINCT status_name FROM case_status WHERE status_name IS NOT NULL AND status_name != ''");
$statusNames = $statusStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle case status update (keep original logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['case_id'], $_POST['status_id'])) {
    $case_id = $_POST['case_id'];
    $status_id = $_POST['status_id'];

    $update = $pdo->prepare("UPDATE dv_case SET status_id = ? WHERE case_id = ?");
    $update->execute([$status_id, $case_id]);
    $message = "Case status updated successfully.";
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Oversight | Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-form input, .filter-form select { margin-right: 8px; }
        .pagination { margin: 18px 0; text-align: center; }
        .pagination a, .pagination span { margin: 0 3px; padding: 4px 10px; border-radius: 3px; border: 1px solid #ccc; text-decoration: none; color: #333; }
        .pagination .current { background: #007bff; color: #fff; border-color: #007bff; }
                .btn-outline {
            border: 1px solid #007bff;
            color: #007bff;
            background: #fff;
            padding: 4px 12px;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
            text-decoration: none;
        }
        .btn-outline:hover {
            background:rgb(84, 104, 126);
            color: #fff;
        }
    </style>
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

    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-folder-open"></i> Cases Oversight</h1>
        </header>
        <section class="content-section">
            <?php if (!empty($message)): ?>
                <div class="success-msg1"><?= h($message) ?></div>
            <?php endif; ?>

            <!-- FILTER FORM -->
            <form method="GET" class="filter-form" style="margin-bottom: 1.5em; display: flex; gap: 1em;">
                <label>
                    Type:
                    <select name="abuse_type">
                        <option value="">All</option>
                        <?php foreach ($abuseTypes as $type): ?>
                            <option value="<?= h($type) ?>" <?= $abuse_type === $type ? 'selected' : '' ?>>
                                <?= h($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Status:
                    <select name="status">
                        <option value="">All</option>
                        <?php foreach ($statusNames as $stat): ?>
                            <option value="<?= h($stat) ?>"<?= $status === $stat ? ' selected' : '' ?>><?= h($stat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Victim Name:
                    <input type="text" name="victim_name" value="<?= h($victim_name) ?>" placeholder="Victim Name" />
                </label>
                <label>
                    Report Date:
                    <input type="date" name="report_date" value="<?= h($report_date) ?>" />
                </label>
                <button type="submit" class="btn1"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($abuse_type || $victim_name || $status || $report_date): ?>
                    <a href="admin_cases.php" class="btn1" style="background:#ccc;color:#222;">Reset</a>
                <?php endif; ?>
            </form>

            <?php if (count($cases) > 0): ?>
                <table class="data-table">
                    <thead>
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
                                    <a class="btn btn-outline" href="admin_view_details.php?case_id=<?= h($case['case_id']) ?>">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No cases found.</p>
            <?php endif; ?>

            <!-- PAGINATION -->
            <div class="pagination" style="margin-top:1.5em;">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn1">&laquo; Prev</a>
            <?php endif; ?>
            Page <?= $page ?> of <?= $total_pages ?>
            <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn1">Next &raquo;</a>
            <?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>