<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email, u.phone_num, u.profilepic,
           l.bar_registration_no, l.law_firm_name
    FROM sys_user u
    LEFT JOIN legalrep_detail l ON u.user_id = l.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("User not found.");

// Handle profile picture update
if (isset($_POST['update_pic']) && isset($_FILES['profilepic'])) {
    if ($_FILES['profilepic']['error'] === UPLOAD_ERR_OK) {

        // Validate file size (example: 2MB max)
        if ($_FILES['profilepic']['size'] > 2 * 1024 * 1024) {
            $error = "File size exceeds 2MB limit.";
        } else {
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['profilepic']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                $error = "Invalid file type. Only JPG, PNG, or GIF allowed.";
            } else {
                // Read file binary
                $imgData = file_get_contents($_FILES['profilepic']['tmp_name']);

                if ($imgData !== false) {
                    $stmt = $pdo->prepare("UPDATE SYS_USER SET profilepic = :pic WHERE user_id = :id");
                    // bindValue is safer for blobs than bindParam
                    $stmt->bindValue(':pic', $imgData, PDO::PARAM_LOB);
                    $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $success = "Profile picture updated successfully!";
                        // Add a cache-busting query string to force reload
                        $user['profilepic_version'] = time();
                    } else {
                        $error = "Failed to update profile picture in database.";
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
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_num'] ?? '');
    $law_firm_name = trim($_POST['law_firm_name'] ?? '');

    if ($name === '' || $phone === '' || $law_firm_name === '') {
        $error = "Please fill in all required fields.";
    } else {
        // Update SYS_USER
        $updateUserStmt = $pdo->prepare("UPDATE sys_user SET full_name = ?, phone_num = ? WHERE user_id = ?");
        $updateUserStmt->execute([$name, $phone, $user_id]);

        // Update legalrep_detail
        $updateLawyerStmt = $pdo->prepare("UPDATE legalrep_detail SET law_firm_name = ? WHERE user_id = ?");
        $updateLawyerStmt->execute([$law_firm_name, $user_id]);

        $success = "Profile information updated successfully!";
    }
}

// Handle password update
if (isset($_POST['update_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password === '' || $confirm_password === '') {
        $error = "Please enter and confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Assuming you have validatePasswordPolicy() function available here
        if (function_exists('validatePasswordPolicy')) {
            $policyCheck = validatePasswordPolicy($new_password);
        } else {
            $policyCheck = true; // skip if function not available
        }

        if ($policyCheck === true) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $updatePassStmt = $pdo->prepare("UPDATE sys_user SET password = ? WHERE user_id = ?");
            $updatePassStmt->execute([$hashed, $user_id]);
            $success = "Password updated successfully!";
        } else {
            $error = $policyCheck; // message from policy function
        }
    }
}

$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email, u.phone_num, u.profilepic,
           l.bar_registration_no, l.law_firm_name
    FROM sys_user u
    LEFT JOIN legalrep_detail l ON u.user_id = l.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Unread messages count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$unreadStmt->execute([$user_id]);
$unread = $unreadStmt->fetchColumn();

// Log the profile update if any post happened and no error
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $logStmt = $pdo->prepare("INSERT INTO system_logs (user_id, event_type, description) VALUES (?, 'profile_update', 'User updated profile details')");
    $logStmt->execute([$user_id]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Profile Settings | DVMS</title>
    <link rel="stylesheet" href="../style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body>
<div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-profile">
                <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" alt="Profile" class="user-avatar">
                    <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="lawyer_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="lawyer_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                <li><a href="law_unassigned.php"><i class="fas fa-user-plus"></i> Unassigned Case</a></li>
                <li><a href="law_messages.php"><i class="fas fa-envelope"></i> Messages
                    <span class="notification-badge"><?= $unread ?></span></a></li>
                                <li><a href="law_reporting.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
<li><a href="legal_resources.php"><i class="fas fa-book"></i> Legal Resources</a></li>
                <li class="active"><a href="law_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1><i class="fas fa-user-cog"></i> Profile Settings</h1>
        </header>

        <div class="alert-box">
            <?php if ($success): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php elseif ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>

        <section class="content-section">

            <!-- Profile Picture Upload -->
        <form method="POST" enctype="multipart/form-data" class="form-profile">
            <h3>Update Profile Picture</h3>
            <div class="form-group">
                <img src="../get_profilepic.php?id=<?= $user['user_id'] ?>&v=<?= $user['profilepic_version'] ?? time() ?>" 
                    alt="Profile" class="user-avatar">
                <input type="file" name="profilepic" required>
            </div>
            <input type="hidden" name="update_pic" value="1">
            <button type="submit" class="btn btn-primary">Update Picture</button>
        </form>

            <!-- Personal Info Update -->
            <form method="POST" class="form-profile">
                <h3>Update Personal Information</h3>

                <div class="form-group">
                    <label>Full Name <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required />
                </div>

                <div class="form-group">
                    <label>Email (read-only)</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly />
                </div>

                <div class="form-group">
                    <label>Phone Number <span style="color: red;">*</span></label>
                    <input type="text" name="phone_num" value="<?= htmlspecialchars($user['phone_num']) ?>" required />
                </div>

                <div class="form-group">
                    <label>Bar Registration Number (read-only)</label>
                    <input type="text" value="<?= htmlspecialchars($user['bar_registration_no']) ?>" readonly />
                </div>

                <div class="form-group">
                    <label>Law Firm Name <span style="color: red;">*</span></label>
                    <input type="text" name="law_firm_name" value="<?= htmlspecialchars($user['law_firm_name']) ?>" required />
                </div>

                <input type="hidden" name="update_info" value="1" />
                <button type="submit" class="btn btn-primary">Update Information</button>
            </form>

            <!-- Password Update -->
            <form method="POST" class="form-profile">
                <h3>Change Password</h3>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required />
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required />
                </div>

                <input type="hidden" name="update_password" value="1" />
                <button type="submit" class="btn btn-warning">Change Password</button>
            </form>

        </section>
    </main>
</div>


</body>
</html>
