<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    // Log error and redirect or show user-friendly message instead of die()
    error_log("User not found: ID $user_id");
    header("Location: ../login.php");
    exit();
}

// Fetch case summary counts grouped by status
$stmt = $pdo->prepare("SELECT status_name, total_cases FROM lawyer_case_summary(:lawyer_id)");
$stmt->execute(['lawyer_id' => $user_id]);
$case_summary = $stmt->fetchAll();

// Count total open cases (status_id != 10 means not closed)
$case_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM dv_case WHERE assigned_lawyer = ? AND status_id != 10");
$case_count_stmt->execute([$user_id]);
$total_open_cases = $case_count_stmt->fetchColumn();

// Get only ONE most recent assigned case
$recent_cases = $pdo->prepare("
    SELECT c.case_id, c.case_id as case_ref, c.report_date, s.status_name, c.status_id, c.address_line1, c.address_line2, c.city, c.postal_code, c.state
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    WHERE c.assigned_lawyer = ?
    ORDER BY c.report_date DESC
    LIMIT 1
");
$recent_cases->execute([$user_id]);  
$cases = $recent_cases->fetchAll();


// Unread messages count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread_messages = $unreadStmt->fetchColumn();

// Log user login event (maybe only once per session)
if (!isset($_SESSION['logged'])) {
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, event_type, description) VALUES (?, 'login', 'User logged in successfully')");
    $stmt->execute([$user_id]);
    $_SESSION['logged'] = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Lawyer Dashboard | DV Assistance System</title>
    <link rel="stylesheet" href="../style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .dashboard-cards { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .card { background: #fff; flex: 1 1 220px; padding: 20px; border-radius: 10px; box-shadow: 0 3px 8px rgba(0,0,0,0.1); text-align: center; color: #333; }
        .card h3 { margin: 0; font-size: 2rem; color: #007BFF; }
        .card p { margin: 5px 0 0; font-weight: 600; }
        .btn-primary { background-color: #007BFF; color: #fff; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
        .btn-primary:hover { background-color: #0056b3; }
        .notification-badge { background: #dc3545; color: #fff; font-size: 0.75rem; border-radius: 50%; padding: 3px 7px; vertical-align: top; margin-left: 5px; }
        .message-alert { position: sticky; top: 0; z-index: 1000; background: linear-gradient(90deg, #e6f0ff, #d9e6f2); color: #003366; padding: 10px 15px; font-weight: 500; border-bottom: 1px solid #b3c6d9; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .message-alert a { color: #0056b3; font-weight: 600; text-decoration: underline; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="user-profile">
                <?php if ($user['profilepic']): ?>
                <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" alt="Profile" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                <p><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li class="active"><a href="lawyer_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="lawyer_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <li><a href="law_unassigned.php"><i class="fas fa-user-plus"></i> Unassigned Cases</a></li>
                <li><a href="law_messages.php"><i class="fas fa-envelope"></i> Messages
                    <?php if ($unread_messages > 0): ?>
                        <span class="notification-badge"><?= $unread_messages ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="law_reporting.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
                <li><a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a></li>
                <li><a href="law_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>


    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-home"></i> Legal Representative Dashboard</h1>
        </header>
<?php if ($unread_messages > 0): ?>
    <div class="message-alert">
        📩 You have <strong><?= $unread_messages ?></strong> unread 
        message<?= $unread_messages > 1 ? 's' : '' ?>. 
        <a href="law_messages.php">View Messages</a>.
    </div>
<?php endif; ?>

        <section class="content-section">
            <h2>  <i class="fas fa-bar-chart"></i> Quick Stats</h2>
            <div class="dashboard-cards">
                <div class="card">
                    <h3><?= intval($total_open_cases) ?></h3>
                    <p>Open Cases</p>
                </div>
                <?php foreach ($case_summary as $row): ?>
                    <div class="card">
                        <h3><?= intval($row['total_cases']) ?></h3>
                        <p><?= htmlspecialchars($row['status_name']) ?> Cases</p>
                    </div>
                <?php endforeach; ?>
                <div class="card">
                    <h3><?= intval($unread_messages) ?></h3>
                    <p>Unread Messages</p>
                </div>
            </div>
        </section>

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
                                <a href="law_view_details.php?case_id=<?= $case['case_id'] ?>">View Details</a>
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
            <h2><i class="fas fa-tools"></i> Quick Tools</h2>
            <div class="resources-grid" style="display:flex;gap:15px;flex-wrap:wrap;">
                <a href="law_profile.php" class="resource-card" style="flex:1 1 200px; text-align:center; padding:20px; background:#fff; border-radius:10px; box-shadow:0 3px 8px rgba(0,0,0,0.1); text-decoration:none; color:#333;">
                    <i class="fas fa-user-cog" style="font-size: 2rem;"></i>
                    <h3>My Profile</h3>
                </a>
                <a href="law_unassigned.php" class="resource-card" style="flex:1 1 200px; text-align:center; padding:20px; background:#fff; border-radius:10px; box-shadow:0 3px 8px rgba(0,0,0,0.1); text-decoration:none; color:#333;">
                    <i class="fas fa-calendar-alt" style="font-size: 2rem;"></i>
                    <h3>Pro Bono Cases</h3>
                </a>
                <a href="legal_resources.php" class="resource-card" style="flex:1 1 200px; text-align:center; padding:20px; background:#fff; border-radius:10px; box-shadow:0 3px 8px rgba(0,0,0,0.1); text-decoration:none; color:#333;">
                    <i class="fas fa-scroll" style="font-size: 2rem;"></i>
                    <h3>Law References</h3>
                </a>
                <a href="law_messages.php" class="resource-card" style="flex:1 1 200px; text-align:center; padding:20px; background:#fff; border-radius:10px; box-shadow:0 3px 8px rgba(0,0,0,0.1); text-decoration:none; color:#333;">
                    <i class="fas fa-comments" style="font-size: 2rem;"></i>
                    <h3>Messages</h3>
                </a>
            </div>
        </section>
    </main>
</div>
<script>
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => el.remove());
    }, 10000); // hides after 10 seconds
</script>

</body>
</html>
