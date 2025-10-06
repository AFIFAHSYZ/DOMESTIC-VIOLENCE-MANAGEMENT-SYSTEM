<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
// Ensure only admin can access
if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// Define role options
$roles = [
    ['id' => 'u_is_ctzn', 'name' => 'Citizen'],
    ['id' => 'u_is_lenf', 'name' => 'Law Enforcement'],
    ['id' => 'u_is_lrep', 'name' => 'Legal Representative'],
    ['id' => 'u_is_adm',  'name' => 'Admin']
];

// Get current admin's user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, full_name, email, phone_num, profilepic FROM SYS_USER WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Admin user not found.");
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $role_id   = $_POST['role_id'] ?? '';
    $password  = password_hash('default123', PASSWORD_DEFAULT);

    // Ensure a valid role is selected
    if (!in_array($role_id, ['u_is_ctzn', 'u_is_lenf', 'u_is_lrep', 'u_is_adm'], true)) {
        $message = "Please select a valid role.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT 1 FROM SYS_USER WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $message = "Email already exists.";
            echo "<script>alert('$message');</script>";

        } else {
            // Default all roles to false
            $role_flags = [
                'u_is_ctzn' => false,
                'u_is_lenf' => false,
                'u_is_lrep' => false,
                'u_is_adm'  => false
            ];

            // Set chosen role to true
            $role_flags[$role_id] = true;
$dob = $_POST['dob'] ?? null;
$phone_num = $_POST['phone_num'] ?? null;

            // Insert new user
$stmt = $pdo->prepare("
    INSERT INTO SYS_USER (
        full_name, email, password, dob, phone_num,
        u_is_ctzn, u_is_lenf, u_is_lrep, u_is_adm,
        is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $full_name,
    $email,
    $password,
    $dob,
    $phone_num,
    (int)$role_flags['u_is_ctzn'],
    (int)$role_flags['u_is_lenf'],
    (int)$role_flags['u_is_lrep'],
    (int)$role_flags['u_is_adm'],
    1
]);

$message = "User added successfully.";
echo "<script>alert('$message');</script>";
        }
    }
}
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
                    <img src="uploads/<?= htmlspecialchars($user['profilepic']) ?>" alt="Admin Profile" class="user-avatar">
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
                <li><a href="admin_assign_case.php"><i class="fas fa-user-plus"></i> Case Assignments</a></li>
                <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Oversight</a></li>
                <li><a href="admin_reporting.php"><i class="fas fa-bar-chart"></i> Reports</a></li>
                <li><a href="admin_settings.php"><i class="fas fa-cogs"></i> Maintenance & Settings</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1>Add New User</h1>
        </header>
        <a href="admin_users.php" class="btn"><i class="fas fa-arrow-left"></i> Return</a>

        <div class="content-section">

            <form method="POST" class="form-box">
                <label>Full Name:</label>
                <input type="text" name="full_name" required>

                <label>Email:</label>
                <input type="email" name="email" required>
<label>Date of Birth:</label>
<input type="date" name="dob" required>

<label>Phone Number:</label>
<input type="text" name="phone_num" required>
                <label>Role:</label>
                <select name="role_id" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['id']) ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <p class="note">Default password will be: <strong>default123</strong></p>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Add User</button>
                    <a href="admin_users.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
