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
// Fetch user info for sidebar
$userStmt = $pdo->prepare("SELECT user_id, full_name, email, profilepic FROM sys_user WHERE user_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Get all statuses for filter dropdown
$statusStmt = $pdo->prepare("SELECT status_id, status_name FROM case_status ORDER BY status_name ASC");
$statusStmt->execute();
$allStatuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter values from GET request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$status_id = isset($_GET['status_id']) ? $_GET['status_id'] : '';

// Pagination settings
$limit = 5; // cases per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total cases for pagination
$countQuery = "
    SELECT COUNT(*) 
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    WHERE c.reported_by = ?
";
$countParams = [$user_id];

if ($start_date) {
    $countQuery .= " AND c.report_date >= ?";
    $countParams[] = $start_date;
}
if ($end_date) {
    $countQuery .= " AND c.report_date <= ?";
    $countParams[] = $end_date;
}
if ($status_id !== '' && $status_id !== 'all') {
    $countQuery .= " AND c.status_id = ?";
    $countParams[] = $status_id;
}

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($countParams);
$total_cases = $countStmt->fetchColumn();
$total_pages = ceil($total_cases / $limit);

$query = "
    SELECT c.case_id, c.case_id as case_ref, c.report_date, s.status_name, c.status_id, 
           c.address_line1, c.address_line2, c.city, c.postal_code, c.state
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    WHERE c.reported_by = ?
";
$params = [$user_id];

if ($start_date) {
    $query .= " AND c.report_date >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $query .= " AND c.report_date <= ?";
    $params[] = $end_date;
}
if ($status_id !== '' && $status_id !== 'all') {
    $query .= " AND c.status_id = ?";
    $params[] = $status_id;
}

$query .= " ORDER BY c.report_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$cases = $stmt->fetchAll();


$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Cases | DV Assistance System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
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
    <main class="main-content">
        <header class="content-header">
            <h1>My Reported Cases</h1>
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
        <section class="content-section">
            <form method="get" class="filter-form" style="margin-bottom:20px;display:flex;gap:10px;align-items:end;">
                <div>
                    <label for="status_id">Status:</label>
                    <select name="status_id" id="status_id">
                        <option value="all" <?= ($status_id === 'all' || $status_id === '') ? 'selected' : '' ?>>All</option>
                        <?php foreach ($allStatuses as $status): ?>
                            <option value="<?= $status['status_id'] ?>" <?= $status_id == $status['status_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['status_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="start_date">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div>
                    <label for="end_date">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>

                <div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <a href="ctzn_cases.php" class="btn btn-secondary" style="margin-left:5px;"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>
            <?php if (count($cases) > 0): ?>
                <div class="cases-list">
                    <?php foreach ($cases as $case): ?>
                        <div class="case-item">
                            <div class="case-header">
                                <h3> Case ID : <?= htmlspecialchars($case['case_ref']) ?></h3>
                                <span class="case-status <?= strtolower(str_replace(' ', '-', $case['status_name'])) ?>">
                                    <?= htmlspecialchars($case['status_name']) ?>
                                </span>
                            </div>
                            <div class="case-meta">
                                <span><i class="fas fa-map-pin"></i> <?= htmlspecialchars($case['address_line1']) ?>
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
                                <a href="ctzn_view_details.php?case_id=<?= $case['case_id'] ?>">View Details</a>
                                <?php if ($case['status_id'] == 1): ?>
                                    <a href="ctzn_edit_case.php?case_id=<?= $case['case_id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                <?php else: ?>
                                    <button type="button" class="btn-edit" disabled title="Cannot edit. This case has been updated by an officer, lawyer or admin."><i class="fas fa-edit"></i> Edit</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($total_pages > 1): ?>
<div class="pagination" style="margin-top:20px; display:flex; gap:5px;">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php
            $queryString = $_GET;
            $queryString['page'] = $i;
            $url = htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($queryString));
        ?>
        <a href="<?= $url ?>" 
           style="padding:6px 12px; border:1px solid #ccc; <?= $i == $page ? 'background:#007bff;color:white;' : 'background:white;color:black;' ?>">
           <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No cases found for the selected filters.</p>
                    <a href="ctzn_report.php" class="btn btn-primary">Report a Case</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<script src="js/script.js"></script>




<!-- Escape Key Exit Script -->
<script src="js/script.js"></script>


<!-- Escape Key Exit Script -->
<script>
(function() {
    let escPressTimes = [];

    document.removeEventListener('keydown', window._escapeHandler);

    window._escapeHandler = function(e) {
        if (e.key === "Escape") {
            const now = Date.now();
            escPressTimes.push(now);
            escPressTimes = escPressTimes.filter(ts => now - ts <= 3000);

            if (escPressTimes.length >= 3) {
                escPressTimes = [];

                // Destroy session
                fetch('logout.php', { method: 'GET', credentials: 'same-origin' })
                .finally(() => {
                    // Overwrite history
                    history.pushState(null, null, 'about:blank');
                    window.location.replace('about:blank');
                });
            }
        }
    };

    document.addEventListener('keydown', window._escapeHandler);
})();

</script>

</body>
</html>