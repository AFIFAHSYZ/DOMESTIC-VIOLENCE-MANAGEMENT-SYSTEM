<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'police') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch user data
$userStmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email, u.phone_num, u.profilepic,
           l.badge_id, l.branch
    FROM sys_user u
    LEFT JOIN lawenforcement_detail l ON u.user_id = l.user_id
    WHERE u.user_id = ?
");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("User not found.");

// Handle profile picture update
if (isset($_POST['update_pic']) && isset($_FILES['profilepic'])) {
    if ($_FILES['profilepic']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['profilepic']['size'] > 2 * 1024 * 1024) {
            $error = "File size exceeds 2MB limit.";
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['profilepic']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                $error = "Invalid file type. Only JPG, PNG, or GIF allowed.";
            } else {
                $imgData = file_get_contents($_FILES['profilepic']['tmp_name']);
                if ($imgData !== false) {
                    $picStmt = $pdo->prepare("UPDATE SYS_USER SET profilepic = :pic WHERE user_id = :id");
                    $picStmt->bindValue(':pic', $imgData, PDO::PARAM_LOB);
                    $picStmt->bindValue(':id', $user_id, PDO::PARAM_INT);
                    if ($picStmt->execute()) {
                        $logStmt = $pdo->prepare("INSERT INTO system_logs (user_id, event_type, description) VALUES (?, 'profile_update', 'Police updated profile picture')");
                        $logStmt->execute([$user_id]);
                        header("Location: police_profile.php?success=1");
                        exit();
                    } else {
                        $error = "Failed to update profile picture.";
                    }
                } else {
                    $error = "Could not read uploaded file.";
                }
            }
        }
    } else {
        $error = "File upload error code: " . $_FILES['profilepic']['error'];
    }
}

// Handle personal info update
if (isset($_POST['update_info'])) {
    $full_name = $_POST['full_name'] ?? '';
    $phone_num = $_POST['phone_num'] ?? '';

    $infoStmt = $pdo->prepare("UPDATE SYS_USER SET full_name = ?, phone_num = ? WHERE user_id = ?");
    $infoStmt->execute([$full_name, $phone_num, $user_id]);

    $logStmt = $pdo->prepare("INSERT INTO system_logs (user_id, event_type, description) VALUES (?, 'profile_update', 'Police updated personal info')");
    $logStmt->execute([$user_id]);

    header("Location: police_profile.php?success=1");
    exit();
}

// Handle password change
if (isset($_POST['update_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password && $new_password === $confirm_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $passStmt = $pdo->prepare("UPDATE SYS_USER SET password = ? WHERE user_id = ?");
        $passStmt->execute([$hashed, $user_id]);

        $logStmt = $pdo->prepare("INSERT INTO system_logs (user_id, event_type, description) VALUES (?, 'profile_update', 'Police changed password')");
        $logStmt->execute([$user_id]);

        header("Location: police_profile.php?success=1");
        exit();
    } else {
        $error = "Passwords do not match.";
    }
}

// Refresh user data after possible updates
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Unread message count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$unreadStmt->execute([$user_id]);
$unread = $unreadStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | DV Assistance System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="user-profile">
                <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= time() ?>" alt="Profile" class="user-avatar">
                <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                <p><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="police_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="police_cases.php"><i class="fas fa-briefcase"></i> My Cases</a></li>
                <li><a href="police_messages.php"><i class="fas fa-envelope"></i> Messages <span class="notification-badge"><?= $unread ?></span></a></li>
                <li><a href="police_reports.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
                <li class="active"><a href="police_profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <h1>Profile Settings</h1>
        </header>

        <div class="alert-box">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert success">Profile updated successfully!</div>
            <?php elseif ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>


        <section class="content-section">

            <!-- Profile Picture Upload -->
            <form method="POST" enctype="multipart/form-data" class="form-profile">
                <h3>Update Profile Picture</h3>
                <div class="form-group">
                    <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= time() ?>" alt="Profile" class="user-avatar">
                    <input type="file" name="profilepic" required>
                </div>
                <input type="hidden" name="update_pic" value="1">
                <button type="submit" class="btn btn-primary">Update Picture</button>
            </form>

            <!-- Personal Info -->
            <form method="POST" class="form-profile">
                <h3>Update Personal Information</h3>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email (read-only)</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_num" value="<?= htmlspecialchars($user['phone_num']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Badge ID (read-only)</label>
                    <input type="text" value="<?= htmlspecialchars($user['badge_id']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Branch (read-only)</label>
                    <input type="text" value="<?= htmlspecialchars($user['branch']) ?>" readonly>
                </div>
                <input type="hidden" name="update_info" value="1">
                <button type="submit" class="btn btn-primary">Update Information</button>
            </form>

            <!-- Password Change -->
            <form method="POST" class="form-profile">
                <h3>Change Password</h3>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <input type="hidden" name="update_password" value="1">
                <button type="submit" class="btn btn-warning">Change Password</button>
            </form>

        </section>
    </main>
</div>

</body>
</html>
