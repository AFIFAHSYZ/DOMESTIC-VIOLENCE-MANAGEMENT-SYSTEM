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

// --- GET CASE ID FROM URL ---
$case_id = isset($_GET['case_id']) ? intval($_GET['case_id']) : 0;
if ($case_id <= 0) {
    die("Invalid case ID.");
}

// --- FETCH CASE ---
$stmt = $pdo->prepare("
    SELECT 
        c.*, s.status_name, 
        r.full_name AS reporter_name,
        o.full_name AS officer_name,
        l.full_name AS lawyer_name,
        v.full_name AS victim_name
    FROM dv_case c
    JOIN case_status s ON c.status_id = s.status_id
    JOIN sys_user r ON c.reported_by = r.user_id
    LEFT JOIN sys_user o ON c.assigned_to = o.user_id
    LEFT JOIN sys_user l ON c.assigned_lawyer = l.user_id
    LEFT JOIN victim v ON c.victim_id = v.victim_id
    WHERE c.case_id = ?
");
$stmt->execute([$case_id]);
$case = $stmt->fetch();
if (!$case) {
    die("Case not found.");
}

// Only allow assignment if not yet assigned
if ($case['assigned_to'] !== null || $case['assigned_lawyer'] !== null) {
    die("This case is already assigned.");
}


$stateToBranch = [
    'Johor'         => 'IPK Johor',
    'Kedah'         => 'IPK Kedah',
    'Kelantan'      => 'IPK Kelantan',
    'Melaka'        => 'IPK Melaka',
    'Negeri Sembilan'=> 'IPK Negeri Sembilan',
    'Pahang'        => 'IPK Pahang',
    'Penang'        => 'IPK Pulau Pinang',
    'Perak'         => 'IPK Perak',
    'Perlis'        => 'IPK Perlis',
    'Sabah'         => 'IPK Sabah',
    'Sarawak'       => 'IPK Sarawak',
    'Selangor'      => 'IPK Selangor',
    'Terengganu'    => 'IPK Terengganu',
    'Kuala Lumpur'  => 'IPK Kuala Lumpur',
    'Labuan'        => 'IPK Labuan',
    'Putrajaya'     => 'IPK Putrajaya'
];

$case_state = $case['state'];
$branch = isset($stateToBranch[$case_state]) ? $stateToBranch[$case_state] : '';

$officerStmt = $pdo->prepare("
    SELECT su.user_id, su.full_name , le.branch AS branch
    FROM SYS_USER su
    JOIN lawenforcement_detail le ON le.user_id = su.user_id
    WHERE role_id = 3 AND is_active = 'true' AND branch = ?
    ORDER BY full_name ASC
");
$officerStmt->execute([$branch]);
$officers = $officerStmt->fetchAll();

$lawyerStmt = $pdo->query("
    SELECT su.user_id, su.full_name, COUNT(dc.case_id) AS cases_handled
    FROM SYS_USER su
    LEFT JOIN dv_case dc ON dc.assigned_lawyer = su.user_id
    WHERE su.role_id = 4 AND su.is_active = 'true'
    GROUP BY su.user_id, su.full_name
    ORDER BY cases_handled ASC, su.full_name ASC
");
$lawyers = $lawyerStmt->fetchAll();

$successMsg = $errorMsg = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $officer_id = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : 0;
    $lawyer_id = isset($_POST['assigned_lawyer']) ? intval($_POST['assigned_lawyer']) : 0;

    // Validate selections
    $validOfficer = array_filter($officers, fn($o) => $o['user_id'] == $officer_id);
    $validLawyer = array_filter($lawyers, fn($l) => $l['user_id'] == $lawyer_id);

    if (!$validOfficer) {
        $errorMsg = "Please select a valid officer.";
    } elseif (!$validLawyer) {
        $errorMsg = "Please select a valid lawyer.";
    } else {
        // Update assignment
        $assignStmt = $pdo->prepare("UPDATE dv_case SET assigned_to = ?, assigned_lawyer = ? WHERE case_id = ?");
        $assignStmt->execute([$officer_id, $lawyer_id, $case_id]);
        $successMsg = "Officer and lawyer assigned successfully to this case.";
        // Refresh case info
        $stmt->execute([$case_id]);
        $case = $stmt->fetch();
    }
}

function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Officer &amp; Lawyer | Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
/head>
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
                <li class="active"><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-user-plus"></i> Assign Officer &amp; Lawyer</h1>
        </header>
            <a href="admin_assign_case.php" class="btn">&larr; Back to Unassigned Cases</a>
        <section class="content-section">

            <div class="form-section">
                <?php if ($successMsg): ?>
                    <div class="success-msg1"><?= h($successMsg) ?></div>
                <?php elseif ($errorMsg): ?>
                    <div class="error-msg1"><?= h($errorMsg) ?></div>
                <?php endif; ?>

                <h2>Case Details</h2>
                <div class="case-details">
                    <b>Case ID:</b> <?= h($case['case_id']) ?><br>
                    <b>Type:</b> <?= h($case['abuse_type']) ?><br>
                    <b>Status:</b> <?= h($case['status_name']) ?><br>
                    <b>Reporter:</b> <?= h($case['reporter_name']) ?><br>
                    <b>Report Date:</b> <?= date('M d, Y', strtotime($case['report_date'])) ?><br>
                    <p><strong>Victim Name:</strong> <?= htmlspecialchars($case['victim_name']) ?></p>
                    <p><strong>Abuse Type:</strong> <?= htmlspecialchars($case['abuse_type']) ?></p>
                    <p><strong>Abuse Description:</strong> <?= htmlspecialchars($case['abuse_desc']) ?></p>
                    <p><strong>Address Line 1:</strong> <?= htmlspecialchars($case['address_line1']) ?></p>
                    <p><strong>Address Line 2:</strong> <?= htmlspecialchars($case['address_line2']) ?></p>
                    <p><strong>City:</strong> <?= htmlspecialchars($case['city']) ?></p>
                    <p><strong>Postal Code:</strong> <?= htmlspecialchars($case['postal_code']) ?></p>
                    <p><strong>State:</strong> <?= htmlspecialchars($case['state']) ?></p>

                </div>
                <hr>
                <?php if (!$successMsg): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="assigned_to">Assign Officer:</label>
                        <select name="assigned_to" id="assigned_to">
                            <option value="">-- Select Officer --</option>
                            <?php foreach ($officers as $o): ?>
                                <option value="<?= h($o['user_id']) ?>" <?= (isset($_POST['assigned_to']) && $_POST['assigned_to'] == $o['user_id']) ? 'selected' : '' ?>>
                                    <?= h($o['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assigned_lawyer">Assign Lawyer:</label>
                        <select name="assigned_lawyer" id="assigned_lawyer" >
                            <option value="">-- Select Lawyer --</option>
                            <?php foreach ($lawyers as $l): ?>
                                <option value="<?= h($l['user_id']) ?>" <?= (isset($_POST['assigned_lawyer']) && $_POST['assigned_lawyer'] == $l['user_id']) ? 'selected' : '' ?>>
                                    <?= h($l['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn1"><i class="fas fa-check"></i> Assign</button>
                </form>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>