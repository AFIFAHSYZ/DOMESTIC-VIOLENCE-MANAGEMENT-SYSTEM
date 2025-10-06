<?php
session_start();
require '../conn.php';
require '../session_timeout.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'police') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Abuse type options for filter
$abuseTypes = [
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

// Filters
$abuse_type = isset($_GET['abuse_type']) ? trim($_GET['abuse_type']) : '';
$victim_name = isset($_GET['victim_name']) ? trim($_GET['victim_name']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$report_date = isset($_GET['report_date']) ? trim($_GET['report_date']) : '';

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Fetch user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, email, profilepic, user_id FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Build query with filters
$where = "c.assigned_to = ?";
$params = [$user_id];
if ($abuse_type !== '') {
    $where .= " AND c.abuse_type = ?";
    $params[] = $abuse_type;
}
if ($victim_name !== '') {
    $where .= " AND v.full_name LIKE ?";
    $params[] = "%$victim_name%";
}
if ($status !== '') {
    $where .= " AND s.status_name = ?";
    $params[] = $status;
}
if ($report_date !== '') {
    $where .= " AND DATE(c.report_date) = ?";
    $params[] = $report_date;
}

// Count total cases for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    WHERE $where
");
$countStmt->execute($params);
$total_cases = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total_cases / $perPage));

// Fetch filtered and paginated cases
$stmt = $pdo->prepare("
    SELECT c.case_id, c.abuse_type, c.status_id, s.status_name, c.report_date, v.full_name as victim_name
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    WHERE $where
    ORDER BY c.report_date DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$cases = $stmt->fetchAll();

// Fetch statuses for filter dropdown
$statuses = $pdo->query("SELECT status_name FROM case_status")->fetchAll(PDO::FETCH_COLUMN);

// Unread messages count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assigned Cases | DVMS</title>
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
        <section class="content-section">
            <?php if (!empty($message)): ?>
                <div class="success-msg1"><?= $message ?></div>
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
                    Victim Name:
                    <input type="text" name="victim_name" value="<?= h($victim_name) ?>" placeholder="Victim Name" />
                </label>
                <label>
                    Status:
                    <select name="status">
                        <option value="">All</option>
                        <?php foreach ($statuses as $stat): ?>
                            <option value="<?= h($stat) ?>"<?= $status === $stat ? ' selected' : '' ?>><?= h($stat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Report Date:
                    <input type="date" name="report_date" value="<?= h($report_date) ?>" />
                </label>
                <button type="submit" class="btn1"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($abuse_type || $victim_name || $status || $report_date): ?>
                    <a href="police_cases.php" class="btn1" style="background:#ccc;color:#222;">Clear</a>
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
                                <td><?= $case['case_id'] ?></td>
                                <td><?= h($case['abuse_type']) ?></td>
                                <td><?= h($case['status_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($case['report_date'])) ?></td>
                                <td><?= h($case['victim_name']) ?></td>
                                <td>
                                    <a class="btn-outline" href="pol_view_details.php?case_id=<?= $case['case_id'] ?>">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

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
            <?php else: ?>
                <p>No assigned cases found.</p>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>