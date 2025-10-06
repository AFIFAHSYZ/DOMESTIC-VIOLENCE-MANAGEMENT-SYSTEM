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

// Fetch user data
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("User not found.");

// Fetch address data
$stmt = $pdo->prepare("SELECT address_line1, address_line2, city, postal_code, state FROM citizen_detail WHERE user_id = ?");
$stmt->execute([$user_id]);
$address = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

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
    $full_name = $_POST['full_name'] ?? '';
    $phone_num = $_POST['phone_num'] ?? '';
    $address_line1 = $_POST['address_line1'] ?? '';
    $address_line2 = $_POST['address_line2'] ?? '';
    $city = $_POST['city'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $state = $_POST['state'] ?? '';

    $pdo->prepare("UPDATE SYS_USER SET full_name = ?, phone_num = ? WHERE user_id = ?")
        ->execute([$full_name, $phone_num, $user_id]);

    $pdo->prepare("UPDATE citizen_detail SET address_line1 = ?, address_line2 = ?, city = ?, postal_code = ?, state = ? WHERE user_id = ?")
        ->execute([$address_line1, $address_line2, $city, $postal_code, $state, $user_id]);

    $success = "Profile information updated successfully!";
}

// Handle password update
if (isset($_POST['update_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password && $new_password === $confirm_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE SYS_USER SET password = ? WHERE user_id = ?")->execute([$hashed, $user_id]);
        $success = "Password updated successfully!";
    } else {
        $error = "Passwords do not match.";
    }
}

// Refresh user info
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Unread message count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$unreadStmt->execute([$user_id]);
$unread = $unreadStmt->fetchColumn();

// Log the profile update
$stmt = $pdo->prepare("INSERT INTO system_logs (user_id, event_type, description) VALUES (?, 'profile_update', 'User updated profile details')");
$stmt->execute([$user_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Settings | DV Assistance System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style></style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
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
                    <li ><a href="citizen_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="ctzn_cases.php"><i class="fas fa-folder-open"></i> My Cases</a></li>
                    <li><a href="ctzn_report.php"><i class="fas fa-plus-circle"></i> Report New Case</a></li>   
                    <li><a href="ctzn_messages.php"><i class="fas fa-envelope"></i> Messages
                    <span class="notification-badge"><?= $unread ?></span></a></li>
                    <li><a href="ctzn_resources.php"><i class="fas fa-book"></i> Resources</a></li>
                    <li class="active"><a href="ctzn_profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a><li>
                </ul>
            </nav>
        </aside>

    <!-- Main -->
<main class="main-content">
    <header class="content-header">
        <h1>Profile Settings</h1>
    </header>

    <div class="alert-box">
        <?php if ($success): ?>
            <div class="alert success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="alert error"><?= $error ?></div>
        <?php endif; ?>
    </div>

    <div style="background: #fff3cd; border-left: 6px solid #ffc107; padding: 12px 18px; margin: 15px 0; border-radius: 6px; color: #856404;">
        <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>
        For your safety: press <strong>Esc</strong> 3 times quickly to exit this system.
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
                <label>Street Address 1</label>
                <input type="text" name="address_line1" value="<?= htmlspecialchars($address['address_line1'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Street Address 2</label>
                <input type="text" name="address_line2" value="<?= htmlspecialchars($address['address_line2'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>City</label>
                <input type="text" name="city" value="<?= htmlspecialchars($address['city'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Postal Code</label>
                <input type="text" name="postal_code" value="<?= htmlspecialchars($address['postal_code'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>State</label>
                <input type="text" name="state" value="<?= htmlspecialchars($address['state'] ?? '') ?>">
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

<script src="js/script.js"></script>



<script>
let escPressCount = 0;
let escTimer = null;

document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        escPressCount++;
        clearTimeout(escTimer);
        escTimer = setTimeout(() => escPressCount = 0, 3000);
        if (escPressCount >= 3) {
            window.location.href = 'about:blank';
        }
    }
});
</script>


</body>
</html>