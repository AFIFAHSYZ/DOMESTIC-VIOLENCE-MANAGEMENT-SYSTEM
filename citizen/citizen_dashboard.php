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


if ($_SESSION['role'] !== 'citizen') {
    header("Location: unauthorized.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();


if (!$user) {
    die("User not found.");
}

// Get case statistics
$active_cases = $pdo->prepare("SELECT COUNT(*) FROM dv_case WHERE reported_by = ? AND status_id != 10");
$active_cases->execute([$user_id]);
$case_count = $active_cases->fetchColumn();
// Build SQL with date and status filtering if provided

$recent_cases = $pdo->prepare("
    SELECT c.case_id, c.case_id as case_ref, c.report_date, s.status_name, c.status_id, c.address_line1, c.address_line2, c.city, c.postal_code, c.state
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    WHERE c.reported_by = ?
    ORDER BY c.report_date DESC
    LIMIT 1
");
$recent_cases->execute([$user_id]);  
$cases = $recent_cases->fetchAll();

$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

$stmt = $pdo->prepare("INSERT INTO system_logs (user_id, event_type, description) VALUES (?, 'login', 'User logged in successfully')");
$stmt->execute([$user_id]);




?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | DV Assistance System</title>
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
                <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" 
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
                    <li class="active">
                        <a href="citizen_dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_cases.php">
                            <i class="fas fa-folder-open"></i> My Cases
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_report.php">
                            <i class="fas fa-plus-circle"></i> Report New Case
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_messages.php">
                            <i class="fas fa-envelope"></i> Messages
                        <span class="notification-badge"><?= $unread ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_resources.php">
                            <i class="fas fa-book"></i> Resources
                        </a>
                    </li>
                    <li>
                        <a href="ctzn_profile.php">
                            <i class="fas fa-user-cog"></i> Profile Settings
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <header class="content-header">


                <h1><i class="fas fa-home"></i> Dashboard Overview</h1>
            </header>

            <?php if ($unread > 0): ?>
    <div class="message-alert">
        📩 You have <strong><?= $unread ?></strong> unread 
        message<?= $unread > 1 ? 's' : '' ?>. 
        <a href="ctzn_messages.php">View Messages</a>.
    </div>
<?php endif; ?>
<?php
echo '
<div style="background: #fff3cd;border-left: 6px solid #ffc107;padding: 12px 18px; margin: 15px 0;border-radius: 6px;color: #856404;display: flex;align-items: center;font-size: 14px;">
    <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>
    For your safety: press  <strong> Esc </strong>  button on your keyboard 3 times quickly to exit this system.
</div>
';
?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <a href="ctzn_cases.php" style="text-decoration:none;color:inherit;">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Active Cases</h3>
                            <p><?= $case_count ?></p>
                        </div>
                    </div>
                </a>

                <a href="ctzn_messages.php" style="text-decoration:none;color:inherit;">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Unread Messages</h3>
                            <p><span><?= $unread ?></span></p>
                        </div>
                    </div>
                </a>
                <div class="stat-card1">
                    <div class="stat-icon1">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Current Date & Time</h3>
                        <p id="live-clock" aria-live="polite">Loading...</p>
                    </div>
                </div>

            </div>

        <!-- Recent Cases Section -->
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
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No cases found</p>
                    <a href="ctzn_report.php" class="btn btn-primary">Report Your First Case</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Quick Resources Section -->
        <section class="content-section">
            <h2><i class="fas fa-lightbulb"></i> Quick Resources</h2>
            <div class="resources-grid">
                <a href="https://findahelpline.com/countries/my/topics/abuse-domestic-violence" 
                class="resource-card" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-phone-alt"></i>
                    <h3>Emergency Contacts</h3>
                </a>
                <a href="https://wao.org.my/laws-on-domestic-violence/" 
                class="resource-card" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-file-pdf"></i>
                    <h3>Laws on DV</h3>
                </a>
                <a href="https://www.domesticshelters.org/help" 
                class="resource-card" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Nearby Shelters</h3>
                </a>
                <a href="https://wao.org.my/getting-help-for-domestic-violence/" 
                class="resource-card" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-users"></i>
                    <h3>Getting Help</h3>
                </a>
            </div>
        </section>
        </main>
    </div>

<!-- Escape Key Exit Script -->
<script src="js/script.js"></script>


<!-- Escape Key Exit Script -->
<script>
let escPressCount = 0;
let escTimer = null;

document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        escPressCount++;

        clearTimeout(escTimer);
        escTimer = setTimeout(() => escPressCount = 0, 3000);

        if (escPressCount >= 3) {
window.location.replace('about:blank');
        }
    }
});
</script>

<script>
function updateClock() {
    const now = new Date();
    const options = {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    };
    document.getElementById('live-clock').textContent = now.toLocaleString('en-US', options);
}

setInterval(updateClock, 1000);
updateClock(); // initial call
</script>


<script>
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => el.remove());
    }, 10000); // hides after 10 seconds
</script>
</body>
</html>