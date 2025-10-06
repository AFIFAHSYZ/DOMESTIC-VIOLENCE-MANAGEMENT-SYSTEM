<?php
session_start();
include 'conn.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password']);

    // Get the user without 'verified'
    $stmt = $pdo->prepare("SELECT user_id, full_name, password, 
        U_IS_ADM AS u_is_adm, 
        U_IS_CTZN AS u_is_ctzn, 
        U_IS_LENF AS u_is_lenf, 
        U_IS_LREP AS u_is_lrep 
    FROM SYS_USER WHERE email = :email");
    
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If a user record is found, check the password.
    if ($user) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $user['full_name'];

            // Default verified to true for roles not needing verification
            $verified = true;

            // Fetch verification status for police
            if ($user['u_is_lenf']) {
                $stmt2 = $pdo->prepare("SELECT verified FROM lawenforcement_detail WHERE user_id = :user_id");
                $stmt2->execute(['user_id' => $user['user_id']]);
                $row = $stmt2->fetch(PDO::FETCH_ASSOC);
                $verified = $row ? (bool)$row['verified'] : false;
            }

            // Fetch verification status for lawyer
            if ($user['u_is_lrep']) {
                $stmt2 = $pdo->prepare("SELECT verified FROM legalrep_detail WHERE user_id = :user_id");
                $stmt2->execute(['user_id' => $user['user_id']]);
                $row = $stmt2->fetch(PDO::FETCH_ASSOC);
                $verified = $row ? (bool)$row['verified'] : false;
            }

            // Determine the user role based on the database flags.
            if ($user['u_is_adm']) {
                $_SESSION['role'] = 'admin';
                header("Location: admin/admin_dashboard.php");
                exit;
            } elseif ($user['u_is_ctzn']) {
                $_SESSION['role'] = 'citizen';
                header("Location: citizen/citizen_dashboard.php");
                exit;
            } elseif ($user['u_is_lenf']) {
                if ($verified) {
                    $_SESSION['role'] = 'police';
                    header("Location: police/police_dashboard.php");
                    exit;
                } else {
                    $error = "Your account is not verified yet. Please contact the administrator.";
                }
            } elseif ($user['u_is_lrep']) {
                if ($verified) {
                    $_SESSION['role'] = 'lawyer';
                    header("Location: lawyer/lawyer_dashboard.php");
                    exit;
                } else {
                    $error = "Your account is not verified yet. Please contact the administrator.";
                }
            } else {
                $error = "User role not defined.";
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Email not found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login Page</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>Domestic Violence Management System</header>

<main>
        <div class="login-container">
            <h2>Login</h2>
            <?php if (!empty($error)) echo "<div class='error-message'>$error</div>"; ?>
            <form method="POST" action="">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="submit" value="Login">
                <p class="register-link">Don't have an account? <a href="register.php">Register here</a></p>
                <p class="register-link">Forgot Password? <a href="forgotpass.php">Click here</a></p>
           </form>
        </div>
    </main>
    <footer>© 2025 DVMS. All rights reserved.</footer>
</body>
</html>