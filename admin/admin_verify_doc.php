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

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Admin user not found.");
}
$message = "";

//handle spproval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['table'], $_POST['action'])) {
    $userId = $_POST['user_id'];
    $table = $_POST['table'];
    $action = $_POST['action'];

    if (in_array($table, ['legalrep_detail', 'lawenforcement_detail'])) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE $table SET verified = TRUE WHERE user_id = ?");
            $stmt->execute([$userId]);
            $message = "User verified successfully.";
        } elseif ($action === 'reject') {
            // Optional: delete verification document here if needed
            $stmt = $pdo->prepare("UPDATE $table SET verification_doc = NULL WHERE user_id = ?");
            $stmt->execute([$userId]);
            $message = "User document rejected and removed.";
        }
    }
}

// Fetch unverified legal reps
$legalStmt = $pdo->prepare("
    SELECT s.user_id, s.full_name, s.email,
           CASE
               WHEN s.u_is_adm THEN 'Admin'
               WHEN s.u_is_lrep THEN 'Legal Representative'
               WHEN s.u_is_lenf THEN 'Law Enforcement'
               WHEN s.u_is_ctzn THEN 'Citizen'
               ELSE 'Unknown'
           END AS role_name,
           l.verification_doc,
           'legalrep_detail' AS user_table
    FROM SYS_USER s
    JOIN legalrep_detail l ON s.user_id = l.user_id
    WHERE l.verified = FALSE
");
$legalStmt->execute();
$legalReps = $legalStmt->fetchAll();

// Fetch unverified law enforcement
$lawStmt = $pdo->prepare("
    SELECT s.user_id, s.full_name, s.email,
           CASE
               WHEN s.u_is_adm THEN 'Admin'
               WHEN s.u_is_lrep THEN 'Legal Representative'
               WHEN s.u_is_lenf THEN 'Law Enforcement'
               WHEN s.u_is_ctzn THEN 'Citizen'
               ELSE 'Unknown'
           END AS role_name,
           p.verification_doc,
           'lawenforcement_detail' AS user_table
    FROM SYS_USER s
    JOIN lawenforcement_detail p ON s.user_id = p.user_id
    WHERE p.verified = FALSE
");
$lawStmt->execute();
$lawEnforcement = $lawStmt->fetchAll();

// Merge both
$pendingUsers = array_merge($legalReps, $lawEnforcement);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New User | Admin Panel</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                <li class="active"><a href="admin_users.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                <li><a href="admin_logs.php"><i class="fas fa-clipboard-list"></i> System Monitoring</a></li>
                <li ><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>
    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-id-badge"></i> Document Verification</h1>
        </header>
            <a href="admin_users.php" class="btn"><i class="fas fa-arrow-left"></i>Return</a>

        <section class="content-section">
            <?php if ($message): ?>
                <div class="success-msg1"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (count($pendingUsers) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Document</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= ucfirst($user['role_name']) ?></td>
                                <td>
                                    <?php if (!empty($user['verification_doc'])): ?>
                                    <a href="get_verdoc.php?table=<?= htmlspecialchars($user['user_table']) ?>&user_id=<?= htmlspecialchars($user['user_id']) ?>" target="_blank">View</a>                                    <?php else: ?>
                                        <span style="color:red;">No document</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Approve Button -->
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to approve this user?');">
                                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                        <input type="hidden" name="table" value="<?= $user['user_table'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn1">Approve</button>
                                    </form>

                                    <!-- Reject Button -->
                                    <form method="POST" style="display:inline-block; margin-left: 5px;" onsubmit="return confirm('Are you sure you want to reject this user? This will remove their document.');">
                                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                        <input type="hidden" name="table" value="<?= $user['user_table'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn1 reject">Reject</button>
                                    </form>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No unverified users found.</p>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
  </section>
    </main>
</div>
</body>
</html>
