<?php
session_start();
require '../conn.php';
require '../session_timeout.php';

$user_id = $_SESSION['user_id'];

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $userId = $_POST['user_id'];

    if ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE SYS_USER SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE SYS_USER SET is_active = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    } elseif ($action === 'reset_password') {
        $new_password = password_hash('default123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE SYS_USER SET password = ? WHERE user_id = ?");
        $stmt->execute([$new_password, $userId]);
    } elseif ($action === 'update_role') {
        $new_role = $_POST['new_role'];
        $stmt = $pdo->prepare("UPDATE SYS_USER SET role_id = ? WHERE user_id = ?");
        $stmt->execute([$new_role, $userId]);
    }
}

// Sorting and filtering
$sort = $_GET['sort'] ?? 'name';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$params = [];

$query = "
    SELECT 
        u.user_id, 
        u.full_name, 
        u.email,
        CASE
            WHEN u.u_is_adm THEN 'Administrator'
            WHEN u.u_is_ctzn THEN 'Citizen'
            WHEN u.u_is_lenf THEN 'Law Enforcement'
            WHEN u.u_is_lrep THEN 'Legal Representative'
            ELSE 'Unknown'
        END AS role_name,
        u.is_active
    FROM SYS_USER u
    WHERE 1=1
";


if (!empty($role_filter)) {
    $query .= " AND CASE
                    WHEN u.u_is_adm THEN 'Administrator'
                    WHEN u.u_is_ctzn THEN 'Citizen'
                    WHEN u.u_is_lenf THEN 'Law Enforcement'
                    WHEN u.u_is_lrep THEN 'Legal Representative'
                    ELSE 'Unknown'
                END = ?";
    $params[] = $role_filter;
}


if ($status_filter === 'active') {
    $query .= " AND u.is_active = TRUE";
} elseif ($status_filter === 'inactive') {
    $query .= " AND u.is_active = FALSE";
}

switch ($sort) {
    case 'role':
        $query .= " ORDER BY r.role_name ASC";
        break;
    case 'status':
        $query .= " ORDER BY u.is_active DESC";
        break;
    default:
        $query .= " ORDER BY u.full_name ASC";
        break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management | Admin Dashboard</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
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
            <h1><i class="fas fa-users-cog"></i> User Management</h1>
        </header>

        <div class="content-section">

            <a href="admin_add_user.php" class="btn btn-primary mt-2" style="margin-bottom: 20px;">
                <i class="fas fa-user-plus"></i> Add New User
            </a>
            <a href="admin_verify_doc.php" class="btn btn-primary mt-2" style="margin-bottom: 20px;">
                <i class="fas fa-file"></i> Verify Document
            </a>

            <h2><i class="fas fa-users-cog"></i> Manage System Users</h2>

            <form method="get" style="margin-bottom: 15px;">
                <label for="role">Role:</label>
                <select name="role" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="Citizen" <?= $role_filter === 'Citizen' ? 'selected' : '' ?>>Citizen</option>
                    <option value="Law Enforcement" <?= $role_filter === 'Law Enforcement' ? 'selected' : '' ?>>Law Enforcement</option>
                    <option value="Legal Representative" <?= $role_filter === 'Legal Representative' ? 'selected' : '' ?>>Legal Representative</option>
                    <option value="Administrator" <?= $role_filter === 'Administrator' ? 'selected' : '' ?>>Administrator</option>
                </select>

                <label for="status">Status:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>

                <label for="sort">Sort By:</label>
                <select name="sort" onchange="this.form.submit()">
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
                    <option value="role" <?= $sort === 'role' ? 'selected' : '' ?>>Role</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
                </select>
            </form>

            <table class="styled-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role_name']) ?></td>
                            <td><?= $user['is_active'] ? 'Active' : 'Inactive' ?></td>
                            <td>
                                <!-- Deactivate/Activate -->
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <input type="hidden" name="action" value="<?= $user['is_active'] ? 'deactivate' : 'activate' ?>">
                                    <button type="submit">
                                        <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>

                                <!-- Reset Password -->
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <button type="submit">Reset Password</button>
                                </form>

                                <!-- Change Role -->
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <input type="hidden" name="action" value="update_role">
                                    <select name="new_role" onchange="this.form.submit()">
                                        <option value="">Change Role</option>
                                        <option value="1">Citizen</option>
                                        <option value="2">Law Enforcement</option>
                                        <option value="3">Legal Representative</option>
                                        <option value="4">Administrator</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

</div>
    </main>
</div>
</body>
</html>
