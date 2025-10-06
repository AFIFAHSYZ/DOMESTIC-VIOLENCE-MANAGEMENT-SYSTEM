<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['case_id'])) {
    header("Location: admin_cases.php");
    exit();
}

$case_id = $_GET['case_id'];
$message = "";

// Fetch all officers and lawyers for dropdowns
$officerStmt = $pdo->prepare("SELECT user_id, full_name FROM sys_user WHERE role_id = 4");
$officerStmt->execute();
$officers = $officerStmt->fetchAll();

$lawyerStmt = $pdo->prepare("SELECT user_id, full_name FROM sys_user WHERE role_id = 3");
$lawyerStmt->execute();
$lawyers = $lawyerStmt->fetchAll();

// Handle form submission to update status, note, officer, and lawyer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_id'])) {
    $status_id = $_POST['status_id'];
    $update_note = $_POST['update_note'] ?? '';
    $assigned_officer = $_POST['assigned_officer'] ?? null;
    $assigned_lawyer = $_POST['assigned_lawyer'] ?? null;

    $stmt = $pdo->prepare("UPDATE dv_case SET status_id = ?, update_note = ?, assigned_to = ?, lawyer_id = ? WHERE case_id = ?");
    $stmt->execute([$status_id, $update_note, $assigned_officer, $assigned_lawyer, $case_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['status_update_msg'] = "✅ Case updated successfully.";
    } else {
        $_SESSION['status_update_msg'] = "⚠️ Update failed. Please check your inputs or try again.";
    }

    header("Location: law_view_details.php?case_id=" . $case_id);
    exit();
}

if (isset($_SESSION['status_update_msg'])) {
    $message = $_SESSION['status_update_msg'];
    unset($_SESSION['status_update_msg']);
}

// Get case details (including current assigned officer and lawyer)
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
    echo "<p>Case not found or you are not authorized to view this case.</p>";
    exit();
}

$userStmt = $pdo->prepare("SELECT full_name, email, profilepic FROM sys_user WHERE user_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch();

$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = FALSE");
$unreadStmt->execute(['uid' => $user_id]);
$unread = $unreadStmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Case Details | DVMS</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
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
                <li><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li class="active"><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>


    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-folder-open"></i> Case Oversight</h1>
        </header>
        <a href="admin_cases.php" class="back-link" style="margin:20px 0; display:inline-block;">
            <i class="fas fa-arrow-left"></i> Back to My Cases
        </a>

        <section class="form-card">
            <div class="form-header">
                <h2><i class="fas fa-file-alt"></i> Case Details</h2>
            </div>

            <?php if (!empty($message)): ?>
                <div class="success-msg1"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <p><strong>Case ID:</strong> <?= $case['case_id'] ?></p>
                <p><strong>Reported by:</strong> <?= htmlspecialchars($case['reporter_name']) ?></p>
                <p><strong>Report Date:</strong> <?= date('M d, Y', strtotime($case['report_date'])) ?></p>
                <p><strong>Victim Name:</strong> <?= htmlspecialchars($case['victim_name']) ?></p>
                <p><strong>Abuse Type:</strong> <?= htmlspecialchars($case['abuse_type']) ?></p>
                <p><strong>Abuse Description:</strong> <?= htmlspecialchars($case['abuse_desc']) ?></p>
                <p><strong>Address Line 1:</strong> <?= htmlspecialchars($case['address_line1']) ?></p>
                <p><strong>Address Line 2:</strong> <?= htmlspecialchars($case['address_line2']) ?></p>
                <p><strong>City:</strong> <?= htmlspecialchars($case['city']) ?></p>
                <p><strong>Postal Code:</strong> <?= htmlspecialchars($case['postal_code']) ?></p>

                <p><strong>Officer Name:</strong>
                    <select name="assigned_officer" required>
                        <?php foreach ($officers as $officer): ?>
                            <option value="<?= $officer['user_id'] ?>" <?= $case['assigned_to'] == $officer['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($officer['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p><strong>Lawyer Name:</strong>
                    <select name="assigned_lawyer" required>
                        <?php foreach ($lawyers as $lawyer): ?>
                            <option value="<?= $lawyer['user_id'] ?>" <?= $case['assigned_lawyer'] == $lawyer['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lawyer['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <hr>

                <p><strong>Status:</strong>
                    <select name="status_id" required>
                        <?php
                        $statuses = [
                            3 => "In Legal Proceedings", 4 => "Pending Court Hearing", 6 => "Legal Action Initiated",
                            7 => "Awaiting Verdict", 8 => "Under Appeal", 9 => "Resolved",
                            10 => "Closed", 11 => "Dismissed", 12 => "Settled", 13 => "Case Withdrawn"
                        ];
                        foreach ($statuses as $id => $name) {
                            $selected = $case['status_id'] == $id ? "selected" : "";
                            echo "<option value=\"$id\" $selected>$name</option>";
                        }
                        ?>
                    </select>
                </p>

                <p><strong>Update Note:</strong><span style="color: red;">*</span></p>
                <textarea name="update_note" required rows="4" style="width:100%;"><?= htmlspecialchars($case['update_note']) ?></textarea>

                <div class="btn-row" style="margin-top: 1em;">
                    <button type="submit" class="btn1"><i class="fas fa-check-circle"></i> Update Status</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>